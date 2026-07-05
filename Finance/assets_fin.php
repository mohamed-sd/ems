<?php
/**
 * Finance/assets_fin.php — الأصول الثابتة والإهلاك (fin_assets + fin_depreciation).
 * سجلّ الأصول (القيمة الدفترية عمود مولّد) + احتساب إهلاك القسط الثابت شهريًا.
 * شاشة مستقلة — عزل شركة + حذف ناعم. المعدات تُقرأ قراءةً فقط (ربط اختياري).
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/assets_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+الأصول+❌"); exit(); }
$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);

// ── احتساب إهلاك الشهر (القسط الثابت) ──
if (isset($_GET['run_dep'])) {
    if (!$can_edit) { header("Location: assets_fin.php?msg=لا+توجد+صلاحية+الاحتساب+❌"); exit(); }
    if (!fin_verify_action_token()) { header("Location: assets_fin.php?msg=رمز+الحماية+غير+صالح+❌"); exit(); }
    $period = date('Y-m'); $today = date('Y-m-d'); $n = 0; $sum = 0;
    $res = mysqli_query($conn, "SELECT id, acquisition_cost, salvage_value, useful_life_months, accumulated_depreciation
                                FROM fin_assets WHERE $company_scope_sql AND COALESCE(is_deleted,0)=0 AND state='active'");
    if ($res) { while ($a = mysqli_fetch_assoc($res)) {
        $aid = intval($a['id']);
        // تخطّي إن كان الشهر محتسبًا (فريد على asset+period)
        $ex = mysqli_query($conn, "SELECT 1 FROM fin_depreciation WHERE company_id=$company_id AND asset_id=$aid AND period_ref='$period' LIMIT 1");
        if ($ex && mysqli_num_rows($ex) > 0) { continue; }
        $life = max(1, intval($a['useful_life_months']));
        $depreciable = (float)$a['acquisition_cost'] - (float)$a['salvage_value'];
        $monthly = round($depreciable / $life, 2);
        $remaining = round($depreciable - (float)$a['accumulated_depreciation'], 2);
        $dep = min($monthly, max(0, $remaining));
        if ($dep <= 0) { continue; }
        mysqli_query($conn, "INSERT INTO fin_depreciation (company_id, asset_id, period_ref, depreciation_amount, run_date, created_by)
                             VALUES ($company_id, $aid, '$period', $dep, '$today', $current_user_id)");
        mysqli_query($conn, "UPDATE fin_assets SET accumulated_depreciation = accumulated_depreciation + $dep,
                             state = CASE WHEN (accumulated_depreciation + $dep) >= ($depreciable) THEN 'fully_depreciated' ELSE state END
                             WHERE id=$aid AND company_id=$company_id");
        $n++; $sum += $dep;
    } }
    header("Location: assets_fin.php?msg=تم+احتساب+إهلاك+$period+لعدد+$n+أصل+(إجمالي+" . number_format($sum, 0) . ")+✅"); exit();
}

// ── حفظ أصل ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    if (!$can_add) { header("Location: assets_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $code = trim($_POST['code'] ?? ''); $name = trim($_POST['name'] ?? ''); $cat = trim($_POST['category'] ?? '');
    $eqid = intval($_POST['equipment_id'] ?? 0) ?: null;
    $adate = trim($_POST['acquisition_date'] ?? '') ?: null;
    $cost = round(floatval($_POST['acquisition_cost'] ?? 0), 2);
    $salv = round(floatval($_POST['salvage_value'] ?? 0), 2);
    $life = max(1, intval($_POST['useful_life_months'] ?? 60));
    if ($code === '' || $name === '' || $cost <= 0) { header("Location: assets_fin.php?msg=بيانات+الأصل+غير+مكتملة+❌"); exit(); }
    $sql = "INSERT INTO fin_assets (company_id, code, name, category, equipment_id, acquisition_date, acquisition_cost, salvage_value, useful_life_months, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'isssisddii', $company_id, $code, $name, $cat, $eqid, $adate, $cost, $salv, $life, $current_user_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_errno($conn) === 1062) { mysqli_stmt_close($stmt); header("Location: assets_fin.php?msg=كود+الأصل+مكرر+❌"); exit(); }
        mysqli_stmt_close($stmt);
    }
    header("Location: assets_fin.php?msg=تمت+إضافة+الأصل+✅"); exit();
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: assets_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['delete_id']);
    mysqli_query($conn, "UPDATE fin_assets SET is_deleted=1, deleted_at=NOW(), deleted_by=$current_user_id WHERE id=$d AND company_id=$company_id");
    header("Location: assets_fin.php?msg=تم+حذف+الأصل+✅"); exit();
}

$page_title = 'إيكوبيشن | الأصول الثابتة';
include '../inheader.php';
include '../insidebar.php';
$state_lbl = array('active' => 'نشط', 'fully_depreciated' => 'مُهلَك بالكامل', 'disposed' => 'مستبعَد');
?>
<div class="main fin-assets-main ems-unified-page-shell">
    <?php
    $header_title = 'الأصول الثابتة والإهلاك'; $header_icon = 'fa fa-building-flag';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة أصل'); }
    if ($can_edit) { $header_actions[] = array('href' => '?run_dep=1&_t=' . fin_action_token(), 'class' => 'add-btn', 'icon' => 'fas fa-calculator', 'label' => 'احتساب إهلاك الشهر'); }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة أصل ثابت</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>الكود <span class="required">*</span></label><input type="text" name="code" required></div>
            <div class="form-group"><label>اسم الأصل <span class="required">*</span></label><input type="text" name="name" required></div>
            <div class="form-group"><label>الفئة</label><input type="text" name="category" placeholder="معدات/مباني/سيارات"></div>
            <div class="form-group"><label>المعدة المرتبطة</label><select name="equipment_id"><?php echo fin_equipment_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label>تاريخ الاقتناء</label><input type="date" name="acquisition_date" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="form-group"><label>تكلفة الاقتناء <span class="required">*</span></label><input type="number" step="0.01" min="0" name="acquisition_cost" required></div>
            <div class="form-group"><label>القيمة التخريدية</label><input type="number" step="0.01" min="0" name="salvage_value" value="0"></div>
            <div class="form-group"><label>العمر الإنتاجي (شهر)</label><input type="number" min="1" name="useful_life_months" value="60"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#finForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <p class="text-muted" style="margin:0 0 10px"><i class="fas fa-circle-info"></i> الإهلاك بطريقة القسط الثابت: (التكلفة − التخريدية) ÷ العمر الإنتاجي شهريًا. «احتساب إهلاك الشهر» لا يكرّر نفس الشهر.</p>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>الكود</th><th>الأصل</th><th>الفئة</th><th>التكلفة</th><th>مجمّع الإهلاك</th><th>القيمة الدفترية</th><th>العمر(شهر)</th><th>الحالة</th></tr></thead>
                <tbody>
                <?php
                $sql = "SELECT * FROM fin_assets WHERE $company_scope_sql AND COALESCE(is_deleted,0)=0 ORDER BY code ASC";
                if ($res = mysqli_query($conn, $sql)) { while ($row = mysqli_fetch_assoc($res)) {
                    $st = (string)$row['state'];
                    $tone = $st === 'active' ? 'success' : ($st === 'fully_depreciated' ? 'secondary' : 'dark');
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)$row['code']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['category'] ?? '—')) . "</td>";
                    echo "<td>" . number_format((float)$row['acquisition_cost'], 2) . "</td>";
                    echo "<td>" . number_format((float)$row['accumulated_depreciation'], 2) . "</td>";
                    echo "<td><strong>" . number_format((float)$row['book_value'], 2) . "</strong></td>";
                    echo "<td>" . intval($row['useful_life_months']) . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . htmlspecialchars($state_lbl[$st] ?? $st) . "</span></td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <h5 style="margin:18px 0 10px"><i class="fas fa-clock-rotate-left"></i> سجلّ الإهلاك</h5>
        <div class="table-container">
            <table id="depTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الأصل</th><th>الفترة</th><th>مبلغ الإهلاك</th><th>تاريخ الاحتساب</th></tr></thead>
                <tbody>
                <?php
                $sql = "SELECT d.*, a.name AS asset_name FROM fin_depreciation d
                        LEFT JOIN fin_assets a ON a.id=d.asset_id
                        WHERE d.company_id" . ($is_super_admin ? " > 0" : " = " . intval($company_id)) . " ORDER BY d.id DESC LIMIT 200";
                if ($res = mysqli_query($conn, $sql)) { while ($row = mysqli_fetch_assoc($res)) {
                    echo "<tr><td>" . htmlspecialchars((string)($row['asset_name'] ?? '')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['period_ref']) . "</td>";
                    echo "<td>" . number_format((float)$row['depreciation_amount'], 2) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$row['run_date']) . "</td></tr>";
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
    $('#finTable, #depTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
    $('#toggleForm').on('click', function () { $('#finForm').toggleClass('allforms-visible'); });
});
</script>
</body>
</html>
