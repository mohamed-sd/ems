<?php
/**
 * إدارة أدوار الموظفين (Employee Roles) — CRUD كامل.
 * ⚠️ هذه أدوارُ الموظفين التنظيمية، منفصلةٌ تماماً عن أدوار مستخدمي النظام/الصلاحيات (جدول roles).
 * employees.employee_role_id يشير إلى employee_roles.id (مفتاح خارجي).
 * عزل الشركة: company_id=NULL أدوارٌ عامّة + أدوارٌ خاصّة بالشركة.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit();
}

$page_permissions = check_page_permissions($conn, 'Employees/employee_roles.php');
$can_view   = $page_permissions['can_view'];
$can_add    = $page_permissions['can_add'];
$can_edit   = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];
if (!$can_view) { header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+أدوار+الموظفين+❌"); exit(); }

$new_company_id = $is_super_admin ? null : $company_id;
$manage_scope = $is_super_admin ? "" : " AND company_id = " . intval($company_id) . " ";

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
        if ($id > 0) {
            if (!$can_edit) { header("Location: employee_roles.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
            $stmt = $conn->prepare("UPDATE employee_roles SET name=?, description=?, status=?, sort_order=? WHERE id=? $manage_scope");
            $stmt->bind_param('ssiii', $name, $desc, $status, $sort_order, $id);
        } else {
            if (!$can_add) { header("Location: employee_roles.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
            $stmt = $conn->prepare("INSERT INTO employee_roles (company_id, name, description, status, sort_order) VALUES (?,?,?,?,?)");
            $stmt->bind_param('issii', $new_company_id, $name, $desc, $status, $sort_order);
        }
        if ($stmt->execute()) {
            header("Location: employee_roles.php?msg=✅+تم+حفظ+الدور+بنجاح"); exit();
        } else {
            $dup = (strpos($conn->error, 'Duplicate') !== false);
            $error_msg = $dup ? 'هذا الدور موجودٌ مسبقاً ❌' : ('حدث خطأ: ' . htmlspecialchars($conn->error) . ' ❌');
        }
        $stmt->close();
    }
}

// ── حذف (مع منع الحذف إذا كان مستخدماً) ───────────────────────────────────────
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: employee_roles.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $id = (int) $_GET['delete_id'];
    $chk = $conn->prepare("SELECT COUNT(*) c FROM employees WHERE employee_role_id = ?");
    $chk->bind_param('i', $id); $chk->execute();
    $used = (int) $chk->get_result()->fetch_assoc()['c']; $chk->close();
    if ($used > 0) {
        header("Location: employee_roles.php?msg=لا+يمكن+حذف+دورٍ+مستخدمٍ+من+قِبل+$used+موظف+❌");
    } else {
        $stmt = $conn->prepare("DELETE FROM employee_roles WHERE id = ? $manage_scope");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute(); $stmt->close();
        header("Location: employee_roles.php?msg=" . ($ok ? "✅+تم+حذف+الدور" : "تعذّر+الحذف+(قد+يكون+دوراً+عامّاً)+❌"));
    }
    exit();
}

// ── تحميل صفٍّ للتعديل ─────────────────────────────────────────────────────────
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT id, company_id, name, description, status, sort_order FROM employee_roles WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

$page_title = "إيكوبيشن | أدوار الموظفين";
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main">
    <?php
    $header_title   = 'أدوار الموظفين';
    $header_icon    = 'fas fa-people-arrows';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة دور');
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

    <div style="margin:6px 0 0;padding:8px 12px;background:rgba(37,99,235,.07);border-radius:8px;font-size:.85rem;color:#555;">
        <i class="fas fa-circle-info"></i> هذه أدوار الموظفين التنظيمية (مهنية)، وهي منفصلةٌ تماماً عن أدوار مستخدمي النظام وصلاحيات الدخول.
    </div>

    <!-- فورم إضافة/تعديل -->
    <form id="erForm" action="" method="post" class="allforms" style="<?= $editData ? '' : 'display:none;' ?>">
        <input type="hidden" name="edit_id" id="edit_id" value="<?= $editData ? intval($editData['id']) : '' ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?= $editData ? 'تعديل دور' : 'إضافة دور' ?></h5></div>
        <div class="form-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:14px;">
            <div class="field">
                <label><i class="fas fa-tag"></i> اسم الدور *</label>
                <input type="text" name="name" id="name" required value="<?= htmlspecialchars($editData['name'] ?? '') ?>" placeholder="مثال: مشرف، مراقب، عمالة مساندة">
            </div>
            <div class="field">
                <label><i class="fas fa-align-right"></i> الوصف</label>
                <input type="text" name="description" id="description" value="<?= htmlspecialchars($editData['description'] ?? '') ?>" placeholder="اختياري">
            </div>
            <div class="field">
                <label><i class="fas fa-sort-numeric-down"></i> ترتيب العرض</label>
                <input type="number" name="sort_order" id="sort_order" value="<?= intval($editData['sort_order'] ?? 0) ?>">
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
            <a href="employee_roles.php" class="add-btn" style="background:#6b7280;"><i class="fas fa-times"></i> إلغاء</a>
        </div>
    </form>

    <!-- جدول الأدوار -->
    <div class="table-wrap" style="margin-top:14px;">
        <table class="data-table" id="erTable" style="width:100%;">
            <thead>
                <tr><th>إجراءات</th><th>#</th><th>الدور</th><th>الوصف</th><th>الموظفون</th><th>النطاق</th><th>الحالة</th></tr>
            </thead>
            <tbody>
            <?php
            $where = $is_super_admin ? "1=1" : "(er.company_id IS NULL OR er.company_id = " . intval($company_id) . ")";
            $sql = "SELECT er.*, (SELECT COUNT(*) FROM employees e WHERE e.employee_role_id = er.id) AS used_count
                    FROM employee_roles er WHERE $where ORDER BY er.sort_order, er.name";
            $res = mysqli_query($conn, $sql);
            $i = 1;
            if ($res) { while ($row = mysqli_fetch_assoc($res)):
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
                        <?php if (!$can_manage): ?><span class="badge" style="opacity:.6;">عامّ</span><?php endif; ?>
                    </div></td>
                    <td><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                    <td><?= htmlspecialchars($row['description'] ?: '-') ?></td>
                    <td><span class="badge badge-info"><?= intval($row['used_count']) ?></span></td>
                    <td><?= $is_global ? '<span class="status-pill status-warning">عامّ</span>' : '<span class="status-pill status-active">الشركة</span>' ?></td>
                    <td><?= intval($row['status']) ? '<span class="status-pill status-active">نشط</span>' : '<span class="status-pill status-inactive">غير نشط</span>' ?></td>
                </tr>
            <?php endwhile; }
            if (!$res || $i === 1): ?>
                <tr><td colspan="7" style="text-align:center;color:#888;padding:18px;">لا توجد أدوار بعد.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function(){
    var btn = document.getElementById('toggleForm'), form = document.getElementById('erForm');
    if (btn && form) btn.addEventListener('click', function(){ form.style.display = (form.style.display === 'none' || !form.style.display) ? 'block' : 'none'; });
})();
function editER(d){
    var f = document.getElementById('erForm'); f.style.display = 'block';
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
