<?php
/**
 * Finance/management_accounting_fin.php — المحاسبة الإدارية — §3.20/§3.21/§14.
 * مراكز التكلفة/الربح (شجرة) + التخصيص الداخلي والتسويات البينية (fin_cost_centers + fin_internal_allocations).
 * شاشة مستقلة — عزل شركة + حذف ناعم. لا FK للخارج.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/management_accounting_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+المحاسبة+الإدارية+❌"); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$center_types = fin_center_types(); $alloc_types = fin_alloc_types(); $owner_modules = fin_dept_modules();

// ── حفظ مركز تكلفة/ربح ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['center_code'])) {
    if (!$can_add) { header("Location: management_accounting_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $code = trim($_POST['center_code'] ?? ''); $name = trim($_POST['center_name'] ?? '');
    $ctype = ($_POST['center_type'] ?? '') === 'profit' ? 'profit' : 'cost';
    $parent = intval($_POST['parent_id'] ?? 0) ?: null;
    $owner = isset($owner_modules[$_POST['owner_module'] ?? '']) ? $_POST['owner_module'] : null;
    if ($code === '' || $name === '') { header("Location: management_accounting_fin.php?msg=بيانات+المركز+غير+مكتملة+❌"); exit(); }
    // حساب المستوى من الأب
    $level = 0;
    if ($parent) {
        $pc = fin_gate($is_super_admin)->selectOne('fin_cost_centers', array('columns' => array('level'), 'where' => array('id' => $parent)));
        if ($pc) { $level = intval($pc['level']) + 1; }
    }
    try {
        fin_gate($is_super_admin)->insert('fin_cost_centers', array(
            'code' => $code, 'name' => $name, 'center_type' => $ctype, 'parent_id' => $parent,
            'owner_module' => $owner, 'level' => $level, 'created_by' => $current_user_id,
        ));
    } catch (\App\Core\TenantGateException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) { header("Location: management_accounting_fin.php?msg=كود+المركز+مكرر+❌"); exit(); }
        error_log('fin_cost_centers insert refused: ' . $e->getMessage());
        header("Location: management_accounting_fin.php?msg=حدث+خطأ+أثناء+الحفظ+❌"); exit();
    }
    header("Location: management_accounting_fin.php?msg=تمت+إضافة+المركز+✅"); exit();
}

// ── حفظ تخصيص/تسوية ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alloc_type'])) {
    if (!$can_add) { header("Location: management_accounting_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $atype = ($_POST['alloc_type'] ?? '') === 'intercompany_settlement' ? 'intercompany_settlement' : 'internal_allocation';
    $from = intval($_POST['from_center_id'] ?? 0) ?: null;
    $to   = intval($_POST['to_center_id'] ?? 0) ?: null;
    $basis= trim($_POST['basis'] ?? '');
    $amount = round(floatval($_POST['amount'] ?? 0), 2);
    if ($amount <= 0) { header("Location: management_accounting_fin.php?msg=مبلغ+غير+صحيح+❌"); exit(); }
    fin_gate($is_super_admin)->insert('fin_internal_allocations', array(
        'alloc_type' => $atype, 'from_center_id' => $from, 'to_center_id' => $to,
        'basis' => $basis, 'amount' => $amount, 'state' => 'draft', 'created_by' => $current_user_id,
    ));
    header("Location: management_accounting_fin.php?msg=تمت+إضافة+الحركة+✅"); exit();
}

// ── حذف ناعم ──
if (isset($_GET['del_center'])) {
    if (!$can_delete) { header("Location: management_accounting_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['del_center']);
    fin_gate($is_super_admin)->softDelete('fin_cost_centers', $d);
    header("Location: management_accounting_fin.php?msg=تم+حذف+المركز+✅"); exit();
}
if (isset($_GET['del_alloc'])) {
    if (!$can_delete) { header("Location: management_accounting_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['del_alloc']);
    fin_gate($is_super_admin)->softDelete('fin_internal_allocations', $d);
    header("Location: management_accounting_fin.php?msg=تم+حذف+الحركة+✅"); exit();
}

$page_title = 'إيكوبيشن | المحاسبة الإدارية';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main fin-mgmt-main ems-unified-page-shell">
    <?php
    $header_title = 'المحاسبة الإدارية'; $header_icon = 'fa fa-diagram-project';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleCenter', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'مركز تكلفة/ربح');
        $header_actions[] = array('id' => 'toggleAlloc', 'class' => 'add-btn', 'icon' => 'fas fa-arrows-turn-to-dots', 'label' => 'تخصيص/تسوية');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="centerForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-sitemap"></i> مركز تكلفة/ربح</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>الكود <span class="required">*</span></label><input type="text" name="center_code" required></div>
            <div class="form-group"><label>الاسم <span class="required">*</span></label><input type="text" name="center_name" required></div>
            <div class="form-group"><label>النوع</label><select name="center_type"><?php foreach ($center_types as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>المركز الأب</label><select name="parent_id"><?php echo fin_center_options($conn, $is_super_admin, $company_id, 0, '— بلا أب (جذر) —'); ?></select></div>
            <div class="form-group"><label>الإدارة المالكة</label><select name="owner_module"><option value="">— بلا —</option><?php foreach ($owner_modules as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#centerForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <form id="allocForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-arrows-turn-to-dots"></i> تخصيص داخلي / تسوية بينية</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>النوع</label><select name="alloc_type"><?php foreach ($alloc_types as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>من مركز</label><select name="from_center_id"><?php echo fin_center_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>إلى مركز</label><select name="to_center_id"><?php echo fin_center_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>الأساس</label><input type="text" name="basis" placeholder="ساعات / استخدام / عدد"></div>
            <div class="form-group"><label>المبلغ <span class="required">*</span></label><input type="number" step="0.01" min="0" name="amount" required></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#allocForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-sitemap"></i> مراكز التكلفة والربح</h5>
        <div class="table-container">
            <table id="ccTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>الكود</th><th>الاسم</th><th>النوع</th><th>الأب</th><th>الإدارة</th><th>المستوى</th></tr></thead>
                <tbody>
                <?php
                $cc_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('cc' => 'fin_cost_centers'), 'enrich' => array('pc' => 'fin_cost_centers')),
                    "SELECT cc.*, pc.name AS parent_name FROM fin_cost_centers cc
                     LEFT JOIN fin_cost_centers pc ON pc.id = cc.parent_id
                     WHERE {TENANT_SCOPE} AND COALESCE(cc.is_deleted,0)=0
                     ORDER BY cc.code ASC");
                { foreach ($cc_rows as $row) {
                    $t = (string)$row['center_type'];
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) echo "<a href='?del_center=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)$row['code']) . "</td>";
                    echo "<td>" . str_repeat('— ', intval($row['level'])) . htmlspecialchars((string)$row['name']) . "</td>";
                    echo "<td><span class='badge badge-" . ($t === 'profit' ? 'success' : 'primary') . "'>" . htmlspecialchars($center_types[$t] ?? $t) . "</span></td>";
                    echo "<td>" . htmlspecialchars((string)($row['parent_name'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars($owner_modules[$row['owner_module']] ?? '—') . "</td>";
                    echo "<td>" . intval($row['level']) . "</td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <h5 style="margin:18px 0 10px"><i class="fas fa-arrows-turn-to-dots"></i> التخصيص الداخلي والتسويات البينية</h5>
        <div class="table-container">
            <table id="iaTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>النوع</th><th>من</th><th>إلى</th><th>الأساس</th><th>المبلغ</th><th>الحالة</th></tr></thead>
                <tbody>
                <?php
                $ia_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('ia' => 'fin_internal_allocations'),
                          'enrich' => array('fc' => 'fin_cost_centers', 'tc' => 'fin_cost_centers')),
                    "SELECT ia.*, fc.name AS from_name, tc.name AS to_name FROM fin_internal_allocations ia
                     LEFT JOIN fin_cost_centers fc ON fc.id = ia.from_center_id
                     LEFT JOIN fin_cost_centers tc ON tc.id = ia.to_center_id
                     WHERE {TENANT_SCOPE} AND COALESCE(ia.is_deleted,0)=0
                     ORDER BY ia.id DESC");
                { foreach ($ia_rows as $row) {
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) echo "<a href='?del_alloc=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars($alloc_types[$row['alloc_type']] ?? $row['alloc_type']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['from_name'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['to_name'] ?? '—')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['basis'] ?? '—')) . "</td>";
                    echo "<td>" . number_format((float)$row['amount'], 2) . "</td>";
                    echo "<td><span class='badge badge-secondary'>" . htmlspecialchars((string)$row['state']) . "</span></td>";
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
    $('#ccTable, #iaTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
    $('#toggleCenter').on('click', function () { $('#centerForm').toggleClass('allforms-visible'); });
    $('#toggleAlloc').on('click', function () { $('#allocForm').toggleClass('allforms-visible'); });
});
</script>
</body>
</html>
