<?php
/**
 * إدارة أدوار الموظفين (Employee Roles) — CRUD كامل.
 * ⚠️ هذه أدوارُ الموظفين التنظيمية، منفصلةٌ تماماً عن أدوار مستخدمي النظام/الصلاحيات (جدول roles).
 * employees.employee_role_id يشير إلى employee_roles.id (مفتاح خارجي).
 * عزل الشركة: company_id=NULL أدوارٌ عامّة + أدوارٌ خاصّة بالشركة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit();
}

$page_permissions = check_page_permissions($conn, 'Employees/employee_roles.php');
$can_view   = $page_permissions['can_view'];
$can_add    = $page_permissions['can_add'];
$can_edit   = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض أدوار الموظفين ❌', 'GOV-PERM-403', ''); exit(); }

// بوابة العزل — بعد M6 نمط «عامّ أو مِلكي» يكافئ عزل البوابة (توأم job_titles)
$er_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('employee roles super manage') : ems_tenant_db();

// ── إضافة / تعديل ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $sort_order  = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
    $desc = $description !== '' ? $description : null;

    if ($name === '') {
        $error_msg = 'اسم الدور مطلوب ❌';
    } else {
        $er_data = array('name' => $name, 'description' => $desc, 'status' => $status, 'sort_order' => $sort_order);
        try {
            if ($id > 0) {
                if (!$can_edit) { ems_gov_flash_redirect('employee_roles.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
                $er_gate->update('employee_roles', $er_data, array('id' => $id));
            } else {
                if (!$can_add) { ems_gov_flash_redirect('employee_roles.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
                $er_gate->insert('employee_roles', $er_data);
            }
            ems_gov_flash_redirect('employee_roles.php', '✅ تم حفظ الدور بنجاح', 'GOV-OK-200', ''); exit();
        } catch (\Throwable $e) {
            $dup = (strpos($e->getMessage(), 'Duplicate') !== false);
            $error_msg = $dup ? 'هذا الدور موجودٌ مسبقاً ❌' : ('حدث خطأ: ' . htmlspecialchars($e->getMessage()) . ' ❌');
        }
    }
}

// ── حذف (مع منع الحذف إذا كان مستخدماً) ───────────────────────────────────────
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('employee_roles.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $id = (int) $_GET['delete_id'];
    // حارس الاستخدام معزول، والحذف الصلب عبر deleteRow
    $used = 0; $ok = false;
    try {
        $used = $er_gate->count('employees', array('where' => array('employee_role_id' => $id)));
        if ($used === 0) {
            $ok = $er_gate->deleteRow('employee_roles', $id, 'employee role delete') > 0;
        }
    } catch (\Throwable $e) { /* غير مملوك → تعذّر */ }
    if ($used > 0) {
        ems_gov_flash_redirect('employee_roles.php', "لا يمكن حذف دورٍ مستخدمٍ من قِبل $used موظف ❌", 'GOV-FAIL-409', '');
    } else {
        ems_gov_flash_redirect('employee_roles.php', $ok ? 'تم حذف الدور ✅' : 'تعذّر الحذف (خارج نطاق شركتك) ❌', $ok ? 'GOV-OK-200' : 'GOV-SCOPE-403', '');
    }
    exit();
}

// ── تحميل صفٍّ للتعديل ─────────────────────────────────────────────────────────
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    $editData = $er_gate->selectOne('employee_roles', array(
        'columns' => array('id', 'company_id', 'name', 'description', 'status', 'sort_order'),
        'where'   => array('id' => $id),
    ));
}

$page_title = "إيكوبيشن | الأدوار الوظيفية";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01 ②: أنماطٌ موضعيةٌ نُقلت أصنافًا صفحيةً ببادئةِ الشاشة er- */
.is-hidden { display: none; }
.er-note {
    margin: 6px 0 0;
    padding: 8px 12px;
    background: var(--c-rgba379923507, rgba(37,99,235,.07));
    border-radius: 8px;
    font-size: .85rem;
    color: var(--c-555555, #555);
}
.er-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; padding: 14px; }
.er-actions { padding: 0 14px 16px; display: flex; gap: 10px; }
.er-btn-cancel { background: var(--c-6b7280, #6b7280); }
.er-table-wrap { margin-top: 14px; }
.er-table-full { width: 100%; }
.er-badge-muted { opacity: .6; }
.er-empty-cell { text-align: center; color: var(--c-888888, #888); padding: 18px; }
</style>
<div class="main">
    <?php
    $header_title   = 'الأدوار الوظيفية';
    $header_icon    = 'fas fa-people-arrows';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة دور');
    }
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا أدوارَ موظفين تنظيميةً مسجَّلةً بعدُ', 'أضف أولَ دورٍ تنظيميٍّ بزرِّ «إضافة دور» في رأسِ الشاشة');
    ?>

    <?php if (!empty($_GET['msg'])): $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($error_msg)): ?>
        <div class="success-message is-error"><i class="fas fa-exclamation-circle"></i> <?= $error_msg ?></div>
    <?php endif; ?>

    <div class="er-note">
        <i class="fas fa-circle-info"></i> هذه أدوار الموظفين التنظيمية (مهنية)، وهي منفصلةٌ تماماً عن أدوار مستخدمي النظام وصلاحيات الدخول.
    </div>

    <!-- فورم إضافة/تعديل -->
    <form id="erForm" action="" method="post" class="allforms<?= $editData ? '' : ' is-hidden' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="edit_id" id="edit_id" value="<?= $editData ? intval($editData['id']) : '' ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?= $editData ? 'تعديل دور' : 'إضافة دور' ?></h5></div>
        <div class="form-grid er-grid-3">
            <div class="field">
                <label for="name"><i class="fas fa-tag"></i> اسم الدور *</label>
                <input type="text" name="name" id="name" required value="<?= htmlspecialchars($editData['name'] ?? '') ?>" placeholder="مثال: مشرف، مراقب، عمالة مساندة">
            </div>
            <div class="field">
                <label for="description"><i class="fas fa-align-right"></i> الوصف</label>
                <input type="text" name="description" id="description" value="<?= htmlspecialchars($editData['description'] ?? '') ?>" placeholder="اختياري">
            </div>
            <div class="field">
                <label for="sort_order"><i class="fas fa-sort-numeric-down"></i> ترتيب العرض</label>
                <input type="number" name="sort_order" id="sort_order" value="<?= intval($editData['sort_order'] ?? 0) ?>">
            </div>
            <div class="field">
                <label for="status"><i class="fas fa-toggle-on"></i> الحالة *</label>
                <select name="status" id="status" required>
                    <option value="1" <?= (($editData['status'] ?? 1) == 1) ? 'selected' : '' ?>>نشط ✅</option>
                    <option value="0" <?= (($editData['status'] ?? 1) == 0) ? 'selected' : '' ?>>غير نشط ⏸</option>
                </select>
            </div>
        </div>
        <div class="er-actions">
            <button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button>
            <a href="employee_roles.php" class="add-btn er-btn-cancel"><i class="fas fa-times"></i> إلغاء</a>
        </div>
    </form>

    <!-- جدول الأدوار -->
    <div class="table-wrap er-table-wrap">
        <table class="data-table er-table-full" id="erTable">
            <thead>
                <tr><th>إجراءات</th><th>#</th><th>الدور</th><th>الوصف</th><th>الموظفون</th><th>النطاق</th><th>الحالة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr>
            </thead>
            <tbody>
            <?php
            // القائمة معزولةً عبر البوابة (بعد M6)
            $er_rows = $er_gate->scopedQuery(array(
                'scope'  => array('er' => 'employee_roles'),
                'enrich' => array('e' => 'employees'),
            ), "SELECT er.*, (SELECT COUNT(*) FROM employees e WHERE e.employee_role_id = er.id) AS used_count
                    FROM employee_roles er WHERE {TENANT_SCOPE} ORDER BY er.sort_order, er.name", array());
            $i = 1;
            { foreach ($er_rows as $row):
                $is_global  = ($row['company_id'] === null);
                $can_manage = $is_super_admin || (!$is_global && intval($row['company_id']) === $company_id);
            ?>
                <tr>
                    <td><div class="action-btns">
                        <?php if ($can_edit && $can_manage): ?>
                            <a href="javascript:void(0);" class="action-btn edit" title="تعديل"
                               onclick='editER(<?= json_encode($row, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                        <?php if ($can_delete && $can_manage): ?>
                            <a href="javascript:void(0);" class="action-btn delete" title="حذف"
                               onclick="confirmDel(<?= intval($row['id']) ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>', <?= intval($row['used_count']) ?>)"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                        <?php if (!$can_manage): ?><span class="badge er-badge-muted">عامّ</span><?php endif; ?>
                    </div></td>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                    <td><?= htmlspecialchars($row['description'] ?: '-') ?></td>
                    <td><span class="badge badge-info"><?= intval($row['used_count']) ?></span></td>
                    <td><?= $is_global ? '<span class="status-pill status-warning">عامّ</span>' : '<span class="status-pill status-active">الشركة</span>' ?></td>
                    <td><?= intval($row['status']) ? '<span class="status-pill status-active">نشط</span>' : '<span class="status-pill status-inactive">غير نشط</span>' ?></td>
                </tr>
            <?php endforeach; }
            if (empty($er_rows)): ?>
                <tr><td colspan="7" class="er-empty-cell">لا توجد أدوار بعد.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('toggleForm'), form = document.getElementById('erForm');
    if (btn && form) btn.addEventListener('click', function(){ form.classList.toggle('is-hidden'); });
})();
function editER(d){
    var f = document.getElementById('erForm'); f.classList.remove('is-hidden');
    document.getElementById('edit_id').value = d.id;
    document.getElementById('name').value = d.name || '';
    document.getElementById('description').value = d.description || '';
    document.getElementById('sort_order').value = d.sort_order || 0;
    document.getElementById('status').value = parseInt(d.status) === 1 ? '1' : '0';
    window.scrollTo({ top: f.offsetTop - 90, behavior: 'smooth' });
}
function confirmDel(id, name, used){
    if (used > 0) { alert('لا يمكن حذف "' + name + '" لأنه مستخدمٌ من قِبل ' + used + ' موظف.'); return; }
    if (confirm('حذف الدور "' + name + '"؟')) window.location.href = 'employee_roles.php?delete_id=' + id;
}
</script>
</body>
</html>
