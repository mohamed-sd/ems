<?php
/**
 * إدارة السائقين والمشغّلين (Equipment Operators) — CRUD كامل.
 * كل مشغّلٍ هو موظفٌ (employee_id فريد → employees.id)، ويحمل بيانات الرخصة/التشغيل فقط.
 * «جميع السائقين/المشغلين موظفون، وليس كل الموظفين سائقين/مشغلين.»
 * يكتب في equipment_operators ويزامن employees.license_* (المرآة التي تقرأها الشاشات القائمة).
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }

include '../config.php';
include '../includes/permissions_helper.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$page_permissions = check_page_permissions($conn, 'Employees/equipment_operators.php');
$can_view   = $page_permissions['can_view'];
$can_add    = $page_permissions['can_add'];
$can_edit   = $page_permissions['can_edit'];
$can_delete = $page_permissions['can_delete'];
if (!$can_view) { header("Location: ../login.php?msg=لا+توجد+صلاحية+عرض+المشغلين+❌"); exit(); }

$emp_scope     = $is_super_admin ? "" : " AND e.company_id = " . intval($company_id) . " ";
$op_scope      = $is_super_admin ? "" : " AND o.company_id = " . intval($company_id) . " ";
$update_scope  = $is_super_admin ? "" : " AND company_id = " . intval($company_id);

function ems_license_validity($expiry) {
    if (empty($expiry) || $expiry === '0000-00-00') return ['دائم', 'status-active'];
    $t = new DateTime('today');
    $e = DateTime::createFromFormat('Y-m-d', substr((string) $expiry, 0, 10));
    if (!$e) return ['دائم', 'status-active'];
    if ($e < $t) return ['منتهٍ', 'status-inactive'];
    $thr = (clone $t)->modify('+30 day');
    return ($e <= $thr) ? ['قارب الانتهاء', 'status-warning'] : ['ساري', 'status-active'];
}

/** مزامنة بيانات الرخصة إلى سجل الموظف (المرآة القديمة التي تقرأها الشاشات الأخرى). */
function ems_op_sync_employee($conn, $emp_id, $vals, $scope) {
    $emp_id = intval($emp_id); if ($emp_id <= 0) return;
    $stmt = $conn->prepare("UPDATE employees SET
        license_number=?, license_type=?, license_expiry_date=?, license_issuer=?,
        license_issue_date=?, license_grade=?, specialized_equipment=COALESCE(?, specialized_equipment)
        WHERE id=?" . $scope);
    if (!$stmt) return;
    $stmt->bind_param('sssssssi',
        $vals['lnum'], $vals['ltype'], $vals['lexp'], $vals['liss'], $vals['lid'], $vals['lgrade'], $vals['opcat'], $emp_id);
    $stmt->execute(); $stmt->close();
}

// ── إضافة / تعديل ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = intval($_POST['id'] ?? 0);
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: equipment_operators.php?msg=لا+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add)  { header("Location: equipment_operators.php?msg=لا+صلاحية+إضافة+❌"); exit(); }

    $employee_id = intval($_POST['employee_id'] ?? 0);
    $f = function ($k) { $v = trim($_POST[$k] ?? ''); return $v !== '' ? $v : null; };
    $lnum = $f('license_number'); $ltype = $f('license_type'); $lgrade = $f('license_grade');
    $liss = $f('license_issuer'); $lid = $f('license_issue_date'); $lexp = $f('license_expiry_date');
    $opcat = $f('operating_categories'); $drv = $f('driving_authorizations'); $notes = $f('notes');
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $sync = ['lnum' => $lnum, 'ltype' => $ltype, 'lexp' => $lexp, 'liss' => $liss, 'lid' => $lid, 'lgrade' => $lgrade, 'opcat' => $opcat];

    if (!$is_editing) {
        if ($employee_id <= 0) { header("Location: equipment_operators.php?msg=يجب+اختيار+موظف+❌"); exit(); }
        // امنع التكرار (employee_id فريد) + تأكّد أن الموظف ضمن الشركة
        $chk = $conn->prepare("SELECT o.id FROM equipment_operators o WHERE o.employee_id = ? LIMIT 1");
        $chk->bind_param('i', $employee_id); $chk->execute();
        if ($chk->get_result()->fetch_assoc()) { $chk->close(); header("Location: equipment_operators.php?msg=هذا+الموظف+مسجّلٌ+مشغّلاً+مسبقاً+❌"); exit(); }
        $chk->close();
        $cid = $is_super_admin ? null : $company_id;
        $stmt = $conn->prepare("INSERT INTO equipment_operators
            (company_id, employee_id, license_number, license_type, license_grade, license_issuer,
             license_issue_date, license_expiry_date, operating_categories, driving_authorizations, status, notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('iissssssssis', $cid, $employee_id, $lnum, $ltype, $lgrade, $liss, $lid, $lexp, $opcat, $drv, $status, $notes);
        $ok = $stmt->execute(); $stmt->close();
        if ($ok) ems_op_sync_employee($conn, $employee_id, $sync, $update_scope);
        header("Location: equipment_operators.php?msg=" . ($ok ? "✅+تم+تسجيل+المشغّل" : "❌+تعذّر+الحفظ")); exit();
    } else {
        $stmt = $conn->prepare("UPDATE equipment_operators SET
            license_number=?, license_type=?, license_grade=?, license_issuer=?, license_issue_date=?,
            license_expiry_date=?, operating_categories=?, driving_authorizations=?, status=?, notes=?
            WHERE id=?" . $update_scope);
        $stmt->bind_param('ssssssssisi', $lnum, $ltype, $lgrade, $liss, $lid, $lexp, $opcat, $drv, $status, $notes, $id);
        $ok = $stmt->execute(); $stmt->close();
        // اجلب employee_id للمزامنة
        $eg = $conn->prepare("SELECT employee_id FROM equipment_operators WHERE id = ? LIMIT 1");
        $eg->bind_param('i', $id); $eg->execute(); $erow = $eg->get_result()->fetch_assoc(); $eg->close();
        if ($ok && $erow) ems_op_sync_employee($conn, intval($erow['employee_id']), $sync, $update_scope);
        header("Location: equipment_operators.php?edit=" . $id . "&msg=" . ($ok ? "✅+تم+تحديث+بيانات+المشغّل" : "❌+تعذّر+التحديث")); exit();
    }
}

// ── حذف (سجل المشغّل فقط — لا يُحذف الموظف) ─────────────────────────────────────
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: equipment_operators.php?msg=لا+صلاحية+حذف+❌"); exit(); }
    $id = (int) $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM equipment_operators WHERE id = ?" . $update_scope);
    $stmt->bind_param('i', $id); $ok = $stmt->execute(); $stmt->close();
    header("Location: equipment_operators.php?msg=" . ($ok ? "✅+تم+حذف+سجل+المشغّل" : "❌+تعذّر+الحذف")); exit();
}

// ── تحميل صفٍّ للتعديل ─────────────────────────────────────────────────────────
$edit = null; $edit_id = intval($_GET['edit'] ?? 0);
if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT o.*, e.name AS emp_name FROM equipment_operators o
            LEFT JOIN employees e ON e.id = o.employee_id
            WHERE o.id = ?" . ($is_super_admin ? "" : " AND o.company_id = " . intval($company_id)) . " LIMIT 1");
    $stmt->bind_param('i', $edit_id); $stmt->execute(); $edit = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

// كل موظفي الشركة: غير المسجّلين كمشغّلين أولاً (للإضافة)، والمسجّلون يُنقلون للتعديل.
$avail = [];
if ($can_add && !$edit) {
    $q = mysqli_query($conn, "SELECT e.id, e.name, COALESCE(jt.name, e.employee_type) AS title,
            (SELECT o.id FROM equipment_operators o WHERE o.employee_id = e.id LIMIT 1) AS op_id
            FROM employees e LEFT JOIN job_titles jt ON jt.id = e.job_title_id
            WHERE 1=1 $emp_scope
            ORDER BY (SELECT COUNT(*) FROM equipment_operators o WHERE o.employee_id = e.id) ASC,
                     COALESCE(jt.is_operator,0) DESC, e.name");
    if ($q) while ($r = mysqli_fetch_assoc($q)) $avail[] = $r;
}

$page_title = "إيكوبيشن | السائقون والمشغّلون";
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main">
    <?php
    $header_title   = 'السائقون والمشغّلون';
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
            <div class="field"><label><i class="fas fa-id-card"></i> نوع/فئة الرخصة</label><input type="text" name="license_type" value="<?= htmlspecialchars($edit['license_type'] ?? '') ?>"></div>
            <div class="field"><label><i class="fas fa-ranking-star"></i> درجة الرخصة</label><input type="text" name="license_grade" value="<?= htmlspecialchars($edit['license_grade'] ?? '') ?>"></div>
            <div class="field"><label><i class="fas fa-building-shield"></i> جهة الإصدار</label><input type="text" name="license_issuer" value="<?= htmlspecialchars($edit['license_issuer'] ?? '') ?>"></div>
            <div class="field"><label><i class="fas fa-calendar-plus"></i> تاريخ الإصدار</label><input type="date" name="license_issue_date" value="<?= htmlspecialchars($edit['license_issue_date'] ?? '') ?>"></div>
            <div class="field"><label><i class="fas fa-calendar-xmark"></i> تاريخ الانتهاء</label><input type="date" name="license_expiry_date" value="<?= htmlspecialchars($edit['license_expiry_date'] ?? '') ?>"></div>
            <div class="field"><label><i class="fas fa-truck-monster"></i> فئات التشغيل/المعدات</label><input type="text" name="operating_categories" value="<?= htmlspecialchars($edit['operating_categories'] ?? '') ?>" placeholder="مثال: حفّارات، شيولات"></div>
            <div class="field"><label><i class="fas fa-key"></i> صلاحيات القيادة/التشغيل</label><input type="text" name="driving_authorizations" value="<?= htmlspecialchars($edit['driving_authorizations'] ?? '') ?>"></div>
            <div class="field">
                <label><i class="fas fa-toggle-on"></i> الحالة</label>
                <select name="status">
                    <option value="1" <?= (($edit['status'] ?? 1) == 1) ? 'selected' : '' ?>>نشط ✅</option>
                    <option value="0" <?= (($edit['status'] ?? 1) == 0) ? 'selected' : '' ?>>غير نشط ⏸</option>
                </select>
            </div>
            <div class="field" style="grid-column:1/-1;"><label><i class="fas fa-align-right"></i> ملاحظات</label><textarea name="notes" rows="2"><?= htmlspecialchars($edit['notes'] ?? '') ?></textarea></div>
        </div>
        <div style="padding:0 14px 16px;display:flex;gap:10px;">
            <button type="submit" class="add-btn"><i class="fas fa-save"></i> حفظ</button>
            <a href="equipment_operators.php" class="add-btn" style="background:#6b7280;"><i class="fas fa-times"></i> إلغاء</a>
        </div>
    </form>

    <div class="table-wrap" style="margin-top:14px;">
        <table class="data-table" id="opTable" style="width:100%;">
            <thead>
                <tr><th>إجراءات</th><th>#</th><th>المشغّل</th><th>المسمى</th><th>رقم الرخصة</th><th>الفئة</th><th>الانتهاء</th><th>الصلاحية</th><th>الحالة</th></tr>
            </thead>
            <tbody>
            <?php
            $sql = "SELECT o.*, e.name AS emp_name, COALESCE(jt.name, e.employee_type) AS title
                    FROM equipment_operators o
                    LEFT JOIN employees e ON e.id = o.employee_id
                    LEFT JOIN job_titles jt ON jt.id = e.job_title_id
                    WHERE 1=1 $op_scope ORDER BY o.id DESC";
            $res = mysqli_query($conn, $sql);
            $i = 1;
            if ($res) { while ($row = mysqli_fetch_assoc($res)):
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
            <?php endwhile; }
            if (!$res || $i === 1): ?>
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
