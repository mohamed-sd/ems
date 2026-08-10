<?php
/**
 * إدارة السائقين والمشغّلين (Equipment Operators) — CRUD كامل.
 * كل مشغّلٍ هو موظفٌ (employee_id فريد → employees.id)، ويحمل بيانات الرخصة/التشغيل فقط.
 * «جميع السائقين/المشغلين موظفون، وليس كل الموظفين سائقين/مشغلين.»
 * يكتب في equipment_operators ويزامن employees.license_* (المرآة التي تقرأها الشاشات القائمة).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$page_permissions = check_page_permissions($conn, 'Employees/equipment_operators.php');
$can_view   = $page_permissions['can_view'];
$can_add    = $page_permissions['can_add'];
$can_edit   = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المشغلين ❌', 'GOV-PERM-403', ''); exit(); }

// بوابة العزل — تستبدل نطاقات e/o/update اليدوية الثلاثة
$op_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('equipment operators super') : ems_tenant_db();

function ems_license_validity($expiry) {
    if (empty($expiry) || $expiry === '0000-00-00') return ['دائم', 'status-active'];
    $t = new DateTime('today');
    $e = DateTime::createFromFormat('Y-m-d', substr((string) $expiry, 0, 10));
    if (!$e) return ['دائم', 'status-active'];
    if ($e < $t) return ['منتهٍ', 'status-inactive'];
    $thr = (clone $t)->modify('+30 day');
    return ($e <= $thr) ? ['قارب الانتهاء', 'status-warning'] : ['ساري', 'status-active'];
}

/** مزامنة بيانات الرخصة إلى سجل الموظف (المرآة القديمة التي تقرأها الشاشات الأخرى).
 *  COALESCE(?, col) القديم = «لا تغيّر العمود عند NULL» → إسقاط العمود من التحديث. */
function ems_op_sync_employee($conn, $emp_id, $vals, $scope) {
    $emp_id = intval($emp_id); if ($emp_id <= 0) return;
    $data = array(
        'license_number'      => $vals['lnum'],
        'license_type'        => $vals['ltype'],
        'license_expiry_date' => $vals['lexp'],
        'license_issuer'      => $vals['liss'],
        'license_issue_date'  => $vals['lid'],
        'license_grade'       => $vals['lgrade'],
    );
    if ($vals['opcat'] !== null) {
        $data['specialized_equipment'] = $vals['opcat'];
    }
    try {
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        $gate = ($role === '-1') ? ems_tenant_db()->forAllTenants('operator mirror sync') : ems_tenant_db();
        $gate->update('employees', $data, array('id' => $emp_id));
    } catch (\Throwable $e) { /* المرآة إضافية — فشلها لا يقطع الحفظ */ }
}

// ── إضافة / تعديل ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = intval($_POST['id'] ?? 0);
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('equipment_operators.php', 'لا صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add)  { ems_gov_flash_redirect('equipment_operators.php', 'لا صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }

    $employee_id = intval($_POST['employee_id'] ?? 0);
    $f = function ($k) { $v = trim($_POST[$k] ?? ''); return $v !== '' ? $v : null; };
    $lnum = $f('license_number'); $ltype = $f('license_type'); $lgrade = $f('license_grade');
    $liss = $f('license_issuer'); $lid = $f('license_issue_date'); $lexp = $f('license_expiry_date');
    $opcat = $f('operating_categories'); $drv = $f('driving_authorizations'); $notes = $f('notes');
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $sync = ['lnum' => $lnum, 'ltype' => $ltype, 'lexp' => $lexp, 'liss' => $liss, 'lid' => $lid, 'lgrade' => $lgrade, 'opcat' => $opcat];

    $op_data = array(
        'license_number' => $lnum, 'license_type' => $ltype, 'license_grade' => $lgrade,
        'license_issuer' => $liss, 'license_issue_date' => $lid, 'license_expiry_date' => $lexp,
        'operating_categories' => $opcat, 'driving_authorizations' => $drv,
        'status' => $status, 'notes' => $notes,
    );
    if (!$is_editing) {
        if ($employee_id <= 0) { ems_gov_flash_redirect('equipment_operators.php', 'يجب اختيار موظف ❌', 'GOV-FAIL-409', ''); exit(); }
        // امنع التكرار (employee_id فريد) — معزولًا بالشركة
        $dup = $op_gate->selectOne('equipment_operators', array('columns' => array('id'), 'where' => array('employee_id' => $employee_id)));
        if ($dup) { ems_gov_flash_redirect('equipment_operators.php', 'هذا الموظف مسجّلٌ مشغّلاً مسبقاً ❌', 'GOV-FAIL-409', ''); exit(); }
        $ok = false;
        try {
            $op_data['employee_id'] = $employee_id;
            $ok = ((int) $op_gate->insert('equipment_operators', $op_data)) > 0;
        } catch (\Throwable $e) { $ok = false; }
        if ($ok) ems_op_sync_employee($conn, $employee_id, $sync, '');
        ems_gov_flash_redirect('equipment_operators.php', $ok ? 'تم تسجيل المشغّل ✅' : 'تعذّر الحفظ ❌', $ok ? 'GOV-OK-200' : 'GOV-FAIL-409', '');
    } else {
        $ok = false;
        try {
            $op_gate->update('equipment_operators', $op_data, array('id' => $id));
            $ok = true;
        } catch (\Throwable $e) { $ok = false; }
        // اجلب employee_id للمزامنة (معزولًا)
        $erow = $op_gate->selectOne('equipment_operators', array('columns' => array('employee_id'), 'where' => array('id' => $id)));
        if ($ok && $erow) ems_op_sync_employee($conn, intval($erow['employee_id']), $sync, '');
        ems_gov_flash_redirect('equipment_operators.php?edit=' . $id, $ok ? 'تم تحديث بيانات المشغّل ✅' : 'تعذّر التحديث ❌', $ok ? 'GOV-OK-200' : 'GOV-FAIL-409', '');
    }
}

// ── حذف (سجل المشغّل فقط — لا يُحذف الموظف) ─────────────────────────────────────
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('equipment_operators.php', 'لا صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $id = (int) $_GET['delete_id'];
    // حذفٌ صلبٌ عبر deleteChild (الشركة + الموظف الأب المملوك المتحقَّق)
    $ok = false;
    try {
        $row = $op_gate->selectOne('equipment_operators', array('columns' => array('id', 'employee_id'), 'where' => array('id' => $id)));
        if ($row) {
            $ok = $op_gate->deleteChild('equipment_operators', $id, 'employees', intval($row['employee_id']), 'employee_id', 'operator delete') > 0;
        }
    } catch (\Throwable $e) { $ok = false; }
    ems_gov_flash_redirect('equipment_operators.php', $ok ? 'تم حذف سجل المشغّل ✅' : 'تعذّر الحذف ❌', $ok ? 'GOV-OK-200' : 'GOV-FAIL-409', '');
}

// ── تحميل صفٍّ للتعديل ─────────────────────────────────────────────────────────
$edit = null; $edit_id = intval($_GET['edit'] ?? 0);
if ($edit_id > 0) {
    $edit_rows = $op_gate->scopedQuery(array(
        'scope'  => array('o' => 'equipment_operators'),
        'enrich' => array('e' => 'employees'),
    ), "SELECT o.*, e.name AS emp_name FROM equipment_operators o
            LEFT JOIN employees e ON e.id = o.employee_id
            WHERE {TENANT_SCOPE} AND o.id = ? LIMIT 1", array($edit_id));
    $edit = $edit_rows[0] ?? null;
}

// كل موظفي الشركة: غير المسجّلين كمشغّلين أولاً (للإضافة)، والمسجّلون يُنقلون للتعديل.
$avail = [];
if ($can_add && !$edit) {
    $avail = $op_gate->scopedQuery(array(
        'scope'  => array('e' => 'employees'),
        'enrich' => array('jt' => 'job_titles', 'o' => 'equipment_operators'),
    ), "SELECT e.id, e.name, COALESCE(jt.name, e.employee_type) AS title,
            (SELECT o.id FROM equipment_operators o WHERE o.employee_id = e.id LIMIT 1) AS op_id
            FROM employees e LEFT JOIN job_titles jt ON jt.id = e.job_title_id
            WHERE {TENANT_SCOPE}
            ORDER BY (SELECT COUNT(*) FROM equipment_operators o WHERE o.employee_id = e.id) ASC,
                     COALESCE(jt.is_operator,0) DESC, e.name", array());
}

$page_title = "إيكوبيشن | المشغّلون والسائقون";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main">
    <?php
    $header_title   = 'المشغّلون والسائقون';
    $header_icon    = 'fas fa-id-card-clip';
    $header_actions = array();
    if ($can_add) $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'تسجيل مشغّل');
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])): $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <form id="opForm" action="" method="post" class="allforms" style="<?= $edit ? '' : 'display:none;' ?>">
        <input type="hidden" name="id" value="<?= $edit ? intval($edit['id']) : 0 ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?= $edit ? 'تعديل بيانات المشغّل' : 'تسجيل سائق/مشغّل' ?></h5></div>
        <div class="form-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:14px;">
            <div class="field">
                <label><i class="fas fa-user"></i> الموظف</label>
                <?php if ($edit): ?>
                    <input type="text" value="<?= htmlspecialchars($edit['emp_name'] ?? '-') ?>" disabled>
                <?php else: ?>
                    <select name="employee_id" id="employee_select" required onchange="emsOpPick(this)">
                        <option value="">— اختر موظفاً —</option>
                        <?php foreach ($avail as $a): $reg = !empty($a['op_id']); ?>
                            <option value="<?= intval($a['id']) ?>" data-opid="<?= $reg ? intval($a['op_id']) : '' ?>">
                                <?= htmlspecialchars($a['name']) ?> — <?= htmlspecialchars($a['title'] ?: '') ?><?= $reg ? ' • مسجّل (تعديل)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:#888;display:block;margin-top:4px;">غير المسجّلين أولاً؛ اختيار موظفٍ «مسجّل» ينقلك لتعديل بياناته.</small>
                <?php endif; ?>
            </div>
            <div class="field"><label><i class="fas fa-hashtag"></i> رقم الرخصة</label><input type="text" name="license_number" value="<?= htmlspecialchars($edit['license_number'] ?? '') ?>"></div>
            <div class="field"><label for="emsf_89_b5109"><i class="fas fa-id-card"></i> نوع/فئة الرخصة</label><input type="text" name="license_type" value="<?= htmlspecialchars($edit['license_type'] ?? '') ?>" id="emsf_89_b5109"></div>
            <div class="field"><label for="emsf_90_50da8"><i class="fas fa-ranking-star"></i> درجة الرخصة</label><input type="text" name="license_grade" value="<?= htmlspecialchars($edit['license_grade'] ?? '') ?>" id="emsf_90_50da8"></div>
            <div class="field"><label for="emsf_91_17293"><i class="fas fa-building-shield"></i> جهة الإصدار</label><input type="text" name="license_issuer" value="<?= htmlspecialchars($edit['license_issuer'] ?? '') ?>" id="emsf_91_17293"></div>
            <div class="field"><label for="emsf_92_847b4"><i class="fas fa-calendar-plus"></i> تاريخ الإصدار</label><input type="date" name="license_issue_date" value="<?= htmlspecialchars($edit['license_issue_date'] ?? '') ?>" id="emsf_92_847b4"></div>
            <div class="field"><label for="emsf_93_771d1"><i class="fas fa-calendar-xmark"></i> تاريخ الانتهاء</label><input type="date" name="license_expiry_date" value="<?= htmlspecialchars($edit['license_expiry_date'] ?? '') ?>" id="emsf_93_771d1"></div>
            <div class="field"><label for="emsf_94_02c94"><i class="fas fa-truck-monster"></i> فئات التشغيل/المعدات</label><input type="text" name="operating_categories" value="<?= htmlspecialchars($edit['operating_categories'] ?? '') ?>" placeholder="مثال: حفّارات، شيولات" id="emsf_94_02c94"></div>
            <div class="field"><label for="emsf_95_4c8c5"><i class="fas fa-key"></i> صلاحيات القيادة/التشغيل</label><input type="text" name="driving_authorizations" value="<?= htmlspecialchars($edit['driving_authorizations'] ?? '') ?>" id="emsf_95_4c8c5"></div>
            <div class="field">
                <label for="emsf_96_2c24f"><i class="fas fa-toggle-on"></i> الحالة</label>
                <select name="status" id="emsf_96_2c24f">
                    <option value="1" <?= (($edit['status'] ?? 1) == 1) ? 'selected' : '' ?>>نشط ✅</option>
                    <option value="0" <?= (($edit['status'] ?? 1) == 0) ? 'selected' : '' ?>>غير نشط ⏸</option>
                </select>
            </div>
            <div class="field" style="grid-column:1/-1;"><label for="emsf_97_fae6a"><i class="fas fa-align-right"></i> ملاحظات</label><textarea name="notes" rows="2" id="emsf_97_fae6a"><?= htmlspecialchars($edit['notes'] ?? '') ?></textarea></div>
        </div>
        <div style="padding:0 14px 16px;display:flex;gap:10px;">
            <button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button>
            <a href="equipment_operators.php" class="add-btn" style="background:#6b7280;"><i class="fas fa-times"></i> إلغاء</a>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px;">
        <table class="data-table" id="opTable" style="width:100%;">
            <thead>
                <tr><th>إجراءات</th><th>#</th><th>كود المشغّل</th><th>المسمى</th><th>الرخصة</th><th>فئة الرخصة</th><th>تاريخ انتهاء الرخصة</th><th>الصلاحية</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">كود الموظف</th>
              <th class="ems-fn-th" data-fn="1">الاسم الرباعي</th>
              <th class="ems-fn-th" data-fn="1">رقم الهوية</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الميلاد</th>
              <th class="ems-fn-th" data-fn="1">التبعية</th>
              <th class="ems-fn-th" data-fn="1">مسار التوزيع</th>
              <th class="ems-fn-th" data-fn="1">المورد التابع له</th>
              <th class="ems-fn-th" data-fn="1">أساس حافز الإنتاج</th>
              <th class="ems-fn-th" data-fn="1">قيمة الحافز للوحدة</th>
              <th class="ems-fn-th" data-fn="1">الوردية المشتركة</th>
              <th class="ems-fn-th" data-fn="1">الموضع فيها</th>
              <th class="ems-fn-th" data-fn="1">رقم عقد العمل</th>
              <th class="ems-fn-th" data-fn="1">الفحص الطبي</th>
              <th class="ems-fn-th none" data-fn="1">تاريخ انتهاء الفحص</th>
              <th class="ems-fn-th none" data-fn="1">الموقع الحالي</th>
              <th class="ems-fn-th none" data-fn="1">المعدة المكلَّف عليها</th>
              <th class="ems-fn-th none" data-fn="1">سجّله</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              </tr>
            </thead>
            <tbody>
            <?php
            // القائمة معزولةً عبر البوابة
            $op_rows = $op_gate->scopedQuery(array(
                'scope'  => array('o' => 'equipment_operators'),
                'enrich' => array('e' => 'employees', 'jt' => 'job_titles'),
            ), "SELECT o.*, e.name AS emp_name, COALESCE(jt.name, e.employee_type) AS title
                    FROM equipment_operators o
                    LEFT JOIN employees e ON e.id = o.employee_id
                    LEFT JOIN job_titles jt ON jt.id = e.job_title_id
                    WHERE {TENANT_SCOPE} ORDER BY o.id DESC", array());
            $i = 1;
            { foreach ($op_rows as $row):
                list($vtext, $vclass) = ems_license_validity($row['license_expiry_date']);
            ?>
                <tr>
                    <td><div class="action-btns">
                        <?php if ($can_edit): ?><a href="equipment_operators.php?edit=<?= intval($row['id']) ?>" class="action-btn edit" title="تعديل"><i class="fas fa-edit"></i></a><?php endif; ?>
                        <?php if ($can_delete): ?><a href="javascript:void(0);" class="action-btn delete" title="حذف" onclick="confirmDel(<?= intval($row['id']) ?>, '<?= htmlspecialchars($row['emp_name'], ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></a><?php endif; ?>
                    </div></td>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($row['emp_name'] ?: '-') ?></strong></td>
                    <td><?= htmlspecialchars($row['title'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['license_number'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['license_type'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($row['license_expiry_date'] ?: '-') ?></td>
                    <td><span class="status-pill <?= $vclass ?>"><?= $vtext ?></span></td>
                    <td><?= intval($row['status']) ? '<span class="status-pill status-active">نشط</span>' : '<span class="status-pill status-inactive">غير نشط</span>' ?></td>
                </tr>
            <?php endforeach; }
            if (empty($op_rows)): ?>
                <tr><td colspan="9" style="text-align:center;color:#888;padding:18px;">لا يوجد مشغّلون مسجّلون بعد.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('toggleForm'), form = document.getElementById('opForm');
    if (btn && form) btn.addEventListener('click', function(){ form.style.display = (form.style.display === 'none' || !form.style.display) ? 'block' : 'none'; });
})();
<?php if ($edit): ?>
// وضع التعديل: افتح الفورم المملوء بالبيانات وانتقل إليه (لا يُغلَق)
document.addEventListener('DOMContentLoaded', function(){
    var f = document.getElementById('opForm');
    if (f) { f.style.display = 'block'; window.scrollTo({ top: Math.max(0, f.offsetTop - 90), behavior: 'smooth' }); }
});
<?php endif; ?>
function emsOpPick(sel){
    var opt = sel.options[sel.selectedIndex];
    var opid = opt ? opt.getAttribute('data-opid') : '';
    if (opid) { window.location.href = 'equipment_operators.php?edit=' + opid; } // مسجّل مسبقاً → تعديل
}
function confirmDel(id, name){ if (confirm('حذف سجل المشغّل "' + name + '"؟ (لن يُحذف الموظف نفسه)')) window.location.href = 'equipment_operators.php?delete_id=' + id; }
</script>
</body>
</html>
