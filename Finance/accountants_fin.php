<?php
/**
 * Finance/accountants_fin.php — المحاسبون الموزّعون والوحدات المالية — §2.1/§3.3/§3.4.
 * الوحدات المالية (fin_units) + محاسب لكل إدارة بتبعيّة مزدوجة (fin_accountants).
 * شاشة مستقلة — عزل شركة + حذف ناعم. الموظفون يُقرأون قراءةً فقط من employees.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/accountants_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+المحاسبين+❌"); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$modules_lbl = fin_source_modules();

// ── حفظ وحدة مالية ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unit_code'])) {
    if (!$can_add) { header("Location: accountants_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $code = trim($_POST['unit_code'] ?? ''); $name = trim($_POST['unit_name'] ?? ''); $note = trim($_POST['role_note'] ?? '');
    if ($code === '' || $name === '') { header("Location: accountants_fin.php?msg=بيانات+الوحدة+غير+مكتملة+❌"); exit(); }
    $sql = "INSERT INTO fin_units (company_id, code, name, role_note, created_by) VALUES (?,?,?,?,?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'isssi', $company_id, $code, $name, $note, $current_user_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_errno($conn) === 1062) { mysqli_stmt_close($stmt); header("Location: accountants_fin.php?msg=كود+الوحدة+مكرر+❌"); exit(); }
        mysqli_stmt_close($stmt);
    }
    header("Location: accountants_fin.php?msg=تمت+إضافة+الوحدة+✅"); exit();
}

// ── حفظ محاسب إدارة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_module'])) {
    if (!$can_add) { header("Location: accountants_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $emp = intval($_POST['employee_id'] ?? 0);
    $mod = isset($modules_lbl[$_POST['admin_module'] ?? '']) ? $_POST['admin_module'] : '';
    $unit = intval($_POST['finance_unit_id'] ?? 0);
    $spec = trim($_POST['specialization'] ?? '');
    $limit = ($_POST['review_limit_usd'] ?? '') === '' ? null : round(floatval($_POST['review_limit_usd']), 2);
    if ($emp <= 0 || $mod === '' || $unit <= 0) { header("Location: accountants_fin.php?msg=بيانات+المحاسب+غير+مكتملة+❌"); exit(); }
    $sql = "INSERT INTO fin_accountants (company_id, employee_id, admin_module, finance_unit_id, specialization, review_limit_usd, created_by) VALUES (?,?,?,?,?,?,?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iisisdi', $company_id, $emp, $mod, $unit, $spec, $limit, $current_user_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_errno($conn) === 1062) { mysqli_stmt_close($stmt); header("Location: accountants_fin.php?msg=المحاسب+مُسنَد+لهذه+الإدارة+مسبقاً+❌"); exit(); }
        mysqli_stmt_close($stmt);
    }
    header("Location: accountants_fin.php?msg=تمت+إضافة+المحاسب+✅"); exit();
}

// ── حذف ناعم ──
if (isset($_GET['del_unit'])) {
    if (!$can_delete) { header("Location: accountants_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['del_unit']);
    mysqli_query($conn, "UPDATE fin_units SET is_deleted=1, deleted_at=NOW(), deleted_by=$current_user_id WHERE id=$d AND company_id=$company_id");
    header("Location: accountants_fin.php?msg=تم+حذف+الوحدة+✅"); exit();
}
if (isset($_GET['del_acct'])) {
    if (!$can_delete) { header("Location: accountants_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['del_acct']);
    mysqli_query($conn, "UPDATE fin_accountants SET is_deleted=1, deleted_at=NOW(), deleted_by=$current_user_id WHERE id=$d AND company_id=$company_id");
    header("Location: accountants_fin.php?msg=تم+حذف+المحاسب+✅"); exit();
}

$page_title = 'إيكوبيشن | المحاسبون والوحدات';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main fin-acct-main ems-unified-page-shell">
    <?php
    $header_title = 'المحاسبون والوحدات المالية'; $header_icon = 'fa fa-users-gear';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleUnit', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'وحدة مالية');
        $header_actions[] = array('id' => 'toggleAcct', 'class' => 'add-btn', 'icon' => 'fas fa-user-plus', 'label' => 'محاسب إدارة');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="unitForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-building-columns"></i> وحدة مالية</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>الكود <span class="required">*</span></label><input type="text" name="unit_code" required placeholder="gl / ar / ap / treasury"></div>
            <div class="form-group"><label>الاسم <span class="required">*</span></label><input type="text" name="unit_name" required></div>
            <div class="form-group" style="grid-column:1/-1"><label>دور الوحدة</label><input type="text" name="role_note"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#unitForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <form id="acctForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-user-tie"></i> محاسب إدارة (تبعيّة مزدوجة)</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>الموظف المحاسب <span class="required">*</span></label><select name="employee_id" required><?php echo fin_employee_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>الإدارة المتبوعة <span class="required">*</span></label><select name="admin_module" required><option value="">— اختر —</option><?php foreach ($modules_lbl as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>الوحدة المالية <span class="required">*</span></label><select name="finance_unit_id" required><?php echo fin_unit_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>التخصص</label><input type="text" name="specialization" placeholder="مبيعات/موردين/قوى..."></div>
            <div class="form-group"><label>حد المراجعة (USD)</label><input type="number" step="0.01" name="review_limit_usd"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#acctForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-building-columns"></i> الوحدات المالية الداخلية</h5>
        <div class="table-container">
            <table id="uTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>الكود</th><th>الاسم</th><th>الدور</th></tr></thead>
                <tbody>
                <?php
                $sql = "SELECT * FROM fin_units WHERE $company_scope_sql AND COALESCE(is_deleted,0)=0 ORDER BY code ASC";
                if ($res = mysqli_query($conn, $sql)) { while ($row = mysqli_fetch_assoc($res)) {
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) echo "<a href='?del_unit=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td><strong>" . htmlspecialchars((string)$row['code']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['role_note'] ?? '—')) . "</td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <h5 style="margin:18px 0 10px"><i class="fas fa-user-tie"></i> محاسبو الإدارات (النموذج الموزّع)</h5>
        <div class="table-container">
            <table id="aTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>المحاسب</th><th>الإدارة المتبوعة</th><th>الوحدة المالية</th><th>التخصص</th><th>حد المراجعة</th></tr></thead>
                <tbody>
                <?php
                $sql = "SELECT a.*, e.name AS emp_name, u.code AS unit_code, u.name AS unit_name
                        FROM fin_accountants a
                        LEFT JOIN employees e ON e.id = a.employee_id
                        LEFT JOIN fin_units u ON u.id = a.finance_unit_id
                        WHERE a.company_id" . ($is_super_admin ? " > 0" : " = " . intval($company_id)) . " AND COALESCE(a.is_deleted,0)=0
                        ORDER BY a.admin_module ASC";
                if ($res = mysqli_query($conn, $sql)) { while ($row = mysqli_fetch_assoc($res)) {
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) echo "<a href='?del_acct=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)($row['emp_name'] ?? ('#' . $row['employee_id']))) . "</td>";
                    echo "<td><span class='badge badge-primary'>" . htmlspecialchars($modules_lbl[$row['admin_module']] ?? $row['admin_module']) . "</span></td>";
                    echo "<td>" . htmlspecialchars((string)($row['unit_code'] ? $row['unit_code'] . ' — ' . $row['unit_name'] : '—')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['specialization'] ?? '—')) . "</td>";
                    echo "<td>" . ($row['review_limit_usd'] !== null ? number_format((float)$row['review_limit_usd'], 2) . ' USD' : '—') . "</td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
$(document).ready(function () {
    $('#uTable, #aTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
    $('#toggleUnit').on('click', function () { $('#unitForm').toggleClass('allforms-visible'); });
    $('#toggleAcct').on('click', function () { $('#acctForm').toggleClass('allforms-visible'); });
});
</script>
</body>
</html>
