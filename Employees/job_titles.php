<?php
/**
 * إدارة المسميات الوظيفية (Job Titles) — CRUD كامل.
 * employees.job_title_id يشير إلى job_titles.id (مفتاح خارجي، لا نصّ ثابت).
 * عزل الشركة: company_id=NULL مسمّياتٌ عامّة (للجميع) + مسمّيات خاصّة بالشركة.
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

$page_permissions = check_page_permissions($conn, 'Employees/job_titles.php');
$can_view   = $page_permissions['can_view'];
$can_add    = $page_permissions['can_add'];
$can_edit   = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];
if (!$can_view) { header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+المسميات+الوظيفية+❌"); exit(); }

// صفّ الشركة لإسناده عند الإضافة (NULL للمشرف العام = مسمّى عامّ)
$new_company_id = $is_super_admin ? null : $company_id;
// شرط قابلية الإدارة (تعديل/حذف): المشرف العام يدير الكل؛ الشركة تدير صفوفها فقط (لا تلمس العامّ)
$manage_scope = $is_super_admin ? "" : " AND company_id = " . intval($company_id) . " ";

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
        if ($id > 0) {
            if (!$can_edit) { header("Location: job_titles.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
            $stmt = $conn->prepare("UPDATE job_titles SET name=?, description=?, is_operator=?, status=?, sort_order=? WHERE id=? $manage_scope");
            $stmt->bind_param('ssiiii', $name, $desc, $is_operator, $status, $sort_order, $id);
        } else {
            if (!$can_add) { header("Location: job_titles.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
            $stmt = $conn->prepare("INSERT INTO job_titles (company_id, name, description, is_operator, status, sort_order) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('issiii', $new_company_id, $name, $desc, $is_operator, $status, $sort_order);
        }
        if ($stmt->execute()) {
            header("Location: job_titles.php?msg=✅+تم+حفظ+المسمى+الوظيفي+بنجاح"); exit();
        } else {
            $dup = (strpos($conn->error, 'Duplicate') !== false);
            $error_msg = $dup ? 'هذا المسمى موجودٌ مسبقاً ❌' : ('حدث خطأ: ' . htmlspecialchars($conn->error) . ' ❌');
        }
        $stmt->close();
    }
}

// ── حذف (مع منع الحذف إذا كان مستخدماً) ───────────────────────────────────────
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: job_titles.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $id = (int) $_GET['delete_id'];
    $chk = $conn->prepare("SELECT COUNT(*) c FROM employees WHERE job_title_id = ?");
    $chk->bind_param('i', $id); $chk->execute();
    $used = (int) $chk->get_result()->fetch_assoc()['c']; $chk->close();
    if ($used > 0) {
        header("Location: job_titles.php?msg=لا+يمكن+حذف+مسمى+مستخدمٍ+من+قِبل+$used+موظف+❌");
    } else {
        $stmt = $conn->prepare("DELETE FROM job_titles WHERE id = ? $manage_scope");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute(); $stmt->close();
        header("Location: job_titles.php?msg=" . ($ok ? "✅+تم+حذف+المسمى+الوظيفي" : "تعذّر+الحذف+(قد+يكون+مسمّى+عامّاً)+❌"));
    }
    exit();
}

// ── تحميل صفٍّ للتعديل ─────────────────────────────────────────────────────────
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT id, company_id, name, description, is_operator, status, sort_order FROM job_titles WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

$page_title = "إيكوبيشن | المسميات الوظيفية";
include '../inheader.php';
include '../insidebar.php';
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
                <tr><th>إجراءات</th><th>#</th><th>المسمى</th><th>الوصف</th><th>مشغّل؟</th><th>الموظفون</th><th>النطاق</th><th>الحالة</th></tr>
            </thead>
            <tbody>
            <?php
            $where = $is_super_admin ? "1=1" : "(jt.company_id IS NULL OR jt.company_id = " . intval($company_id) . ")";
            $sql = "SELECT jt.*, (SELECT COUNT(*) FROM employees e WHERE e.job_title_id = jt.id) AS used_count
                    FROM job_titles jt WHERE $where ORDER BY jt.sort_order, jt.name";
            $res = mysqli_query($conn, $sql);
            $i = 1;
            if ($res) { while ($row = mysqli_fetch_assoc($res)):
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
            <?php endwhile; }
            if (!$res || $i === 1): ?>
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
