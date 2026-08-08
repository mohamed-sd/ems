<?php
/**
 * إدارة المسميات الوظيفية (Job Titles) — CRUD كامل.
 * employees.job_title_id يشير إلى job_titles.id (مفتاح خارجي، لا نصّ ثابت).
 * عزل الشركة: company_id=NULL مسمّياتٌ عامّة (للجميع) + مسمّيات خاصّة بالشركة.
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

$page_permissions = check_page_permissions($conn, 'Employees/job_titles.php');
$can_view   = $page_permissions['can_view'];
$can_add    = $page_permissions['can_add'];
$can_edit   = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المسميات الوظيفية ❌', 'GOV-PERM-403', ''); exit(); }

// بوابة العزل — بعد تعبئة M6 لا صفوف عامّة (NULL)، فنمط «عامّ أو مِلكي» القديم
// يكافئ عزل البوابة المباشر. السوبر → عابرٌ مُسجَّل يدير الكل.
$jt_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('job titles super manage') : ems_tenant_db();

// ── إضافة / تعديل ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $is_operator = isset($_POST['is_operator']) ? 1 : 0;
    $status      = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $sort_order  = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
    $desc = $description !== '' ? $description : null;

    if ($name === '') {
        $error_msg = 'اسم المسمى الوظيفي مطلوب ❌';
    } else {
        $jt_data = array('name' => $name, 'description' => $desc, 'is_operator' => $is_operator, 'status' => $status, 'sort_order' => $sort_order);
        try {
            if ($id > 0) {
                if (!$can_edit) { ems_gov_flash_redirect('job_titles.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
                $jt_gate->update('job_titles', $jt_data, array('id' => $id));
            } else {
                if (!$can_add) { ems_gov_flash_redirect('job_titles.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
                $jt_gate->insert('job_titles', $jt_data);
            }
            ems_gov_flash_redirect('job_titles.php', '✅ تم حفظ المسمى الوظيفي بنجاح', 'GOV-OK-200', ''); exit();
        } catch (\Throwable $e) {
            $dup = (strpos($e->getMessage(), 'Duplicate') !== false);
            $error_msg = $dup ? 'هذا المسمى موجودٌ مسبقاً ❌' : ('حدث خطأ: ' . htmlspecialchars($e->getMessage()) . ' ❌');
        }
    }
}

// ── حذف (مع منع الحذف إذا كان مستخدماً) ───────────────────────────────────────
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('job_titles.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $id = (int) $_GET['delete_id'];
    // حارس الاستخدام معزولٌ بالشركة، والحذف الصلب عبر deleteRow (كيانٌ بلا أبٍ إلزاميّ)
    $used = 0; $ok = false;
    try {
        $used = $jt_gate->count('employees', array('where' => array('job_title_id' => $id)));
        if ($used === 0) {
            $ok = $jt_gate->deleteRow('job_titles', $id, 'job title delete') > 0;
        }
    } catch (\Throwable $e) { /* غير مملوك/سياق ناقص → تعذّر */ }
    if ($used > 0) {
        ems_gov_flash_redirect('job_titles.php', 'لا يمكن حذف مسمى مستخدمٍ من قِبل $used موظف ❌', 'GOV-FAIL-409', '');
    } else {
        ems_gov_flash_redirect('job_titles.php', $ok ? 'تم حذف المسمى الوظيفي ✅' : 'تعذّر الحذف (خارج نطاق شركتك) ❌', $ok ? 'GOV-OK-200' : 'GOV-SCOPE-403', '');
    }
    exit();
}

// ── تحميل صفٍّ للتعديل ─────────────────────────────────────────────────────────
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    // معزولٌ بالشركة (كان الجلب بلا نطاق — أشدّ الآن، والكتابة كانت أصلًا محكومة)
    $editData = $jt_gate->selectOne('job_titles', array(
        'columns' => array('id', 'company_id', 'name', 'description', 'is_operator', 'status', 'sort_order'),
        'where'   => array('id' => $id),
    ));
}

$page_title = "إيكوبيشن | المسميات الوظيفية";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main">
    <?php
    $header_title   = 'المسميات الوظيفية';
    $header_icon    = 'fas fa-user-tag';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة مسمى وظيفي');
    }
    $header_back = array('href' => 'employees.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'سجل الموظفين');
    include('../includes/page_header.php');
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

    <!-- فورم إضافة/تعديل -->
    <form id="jtForm" action="" method="post" class="allforms" style="<?= $editData ? '' : 'display:none;' ?>">
        <input type="hidden" name="edit_id" id="edit_id" value="<?= $editData ? intval($editData['id']) : '' ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?= $editData ? 'تعديل مسمى وظيفي' : 'إضافة مسمى وظيفي' ?></h5></div>
        <div class="form-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:14px;">
            <div class="field">
                <label><i class="fas fa-tag"></i> اسم المسمى *</label>
                <input type="text" name="name" id="name" required value="<?= htmlspecialchars($editData['name'] ?? '') ?>" placeholder="مثال: مهندس، فني، سائق">
            </div>
            <div class="field">
                <label><i class="fas fa-align-right"></i> الوصف</label>
                <input type="text" name="description" id="description" value="<?= htmlspecialchars($editData['description'] ?? '') ?>" placeholder="اختياري">
            </div>
            <div class="field">
                <label><i class="fas fa-sort-numeric-down"></i> ترتيب العرض</label>
                <input type="number" name="sort_order" id="sort_order" value="<?= intval($editData['sort_order'] ?? 0) ?>">
            </div>
            <div class="field" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_operator" id="is_operator" value="1" <?= (!empty($editData['is_operator'])) ? 'checked' : '' ?>>
                <label for="is_operator" style="margin:0;">مشغّل معدات (سائق/مشغّل)</label>
            </div>
            <div class="field">
                <label><i class="fas fa-toggle-on"></i> الحالة *</label>
                <select name="status" id="status" required>
                    <option value="1" <?= (($editData['status'] ?? 1) == 1) ? 'selected' : '' ?>>نشط ✅</option>
                    <option value="0" <?= (($editData['status'] ?? 1) == 0) ? 'selected' : '' ?>>غير نشط ⏸</option>
                </select>
            </div>
        </div>
        <div style="padding:0 14px 16px;display:flex;gap:10px;">
            <button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button>
            <a href="job_titles.php" class="add-btn" style="background:#6b7280;"><i class="fas fa-times"></i> إلغاء</a>
        </div>
    </form>

    <!-- جدول المسميات -->
    <div class="table-wrap" style="margin-top:14px;">
        <table class="data-table" id="jtTable" style="width:100%;">
            <thead>
                <tr><th>إجراءات</th><th>#</th><th>المسمى</th><th>الوصف</th><th>مشغّل؟</th><th>الموظفون</th><th>النطاق</th><th>الحالة</th>
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
            // القائمة معزولةً عبر البوابة (بعد M6 يكافئ نمطَ «عامّ أو مِلكي»)
            $jt_rows = $jt_gate->scopedQuery(array(
                'scope'  => array('jt' => 'job_titles'),
                'enrich' => array('e' => 'employees'),
            ), "SELECT jt.*, (SELECT COUNT(*) FROM employees e WHERE e.job_title_id = jt.id) AS used_count
                    FROM job_titles jt WHERE {TENANT_SCOPE} ORDER BY jt.sort_order, jt.name", array());
            $i = 1;
            { foreach ($jt_rows as $row):
                $is_global   = ($row['company_id'] === null);
                $can_manage  = $is_super_admin || (!$is_global && intval($row['company_id']) === $company_id);
            ?>
                <tr>
                    <td><div class="action-btns">
                        <?php if ($can_edit && $can_manage): ?>
                            <a href="javascript:void(0);" class="action-btn edit" title="تعديل"
                               onclick='editJT(<?= json_encode($row, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS) ?>)'><i class="fas fa-edit"></i></a>
                        <?php endif; ?>
                        <?php if ($can_delete && $can_manage): ?>
                            <a href="javascript:void(0);" class="action-btn delete" title="حذف"
                               onclick="confirmDel(<?= intval($row['id']) ?>, '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>', <?= intval($row['used_count']) ?>)"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                        <?php if (!$can_manage): ?><span class="badge" style="opacity:.6;">عامّ</span><?php endif; ?>
                    </div></td>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                    <td><?= htmlspecialchars($row['description'] ?: '-') ?></td>
                    <td><?= intval($row['is_operator']) ? '<span class="badge badge-info">مشغّل</span>' : '—' ?></td>
                    <td><span class="badge badge-info"><?= intval($row['used_count']) ?></span></td>
                    <td><?= $is_global ? '<span class="status-pill status-warning">عامّ</span>' : '<span class="status-pill status-active">الشركة</span>' ?></td>
                    <td><?= intval($row['status']) ? '<span class="status-pill status-active">نشط</span>' : '<span class="status-pill status-inactive">غير نشط</span>' ?></td>
                </tr>
            <?php endforeach; }
            if (empty($jt_rows)): ?>
                <tr><td colspan="8" style="text-align:center;color:#888;padding:18px;">لا توجد مسمّيات بعد.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('toggleForm'), form = document.getElementById('jtForm');
    if (btn && form) btn.addEventListener('click', function(){ form.style.display = (form.style.display === 'none' || !form.style.display) ? 'block' : 'none'; });
})();
function editJT(d){
    var f = document.getElementById('jtForm'); f.style.display = 'block';
    document.getElementById('edit_id').value = d.id;
    document.getElementById('name').value = d.name || '';
    document.getElementById('description').value = d.description || '';
    document.getElementById('sort_order').value = d.sort_order || 0;
    document.getElementById('is_operator').checked = (parseInt(d.is_operator) === 1);
    document.getElementById('status').value = parseInt(d.status) === 1 ? '1' : '0';
    window.scrollTo({ top: f.offsetTop - 90, behavior: 'smooth' });
}
function confirmDel(id, name, used){
    if (used > 0) { alert('لا يمكن حذف "' + name + '" لأنه مستخدمٌ من قِبل ' + used + ' موظف.'); return; }
    if (confirm('حذف المسمى "' + name + '"؟')) window.location.href = 'job_titles.php?delete_id=' + id;
}
</script>
</body>
</html>
