<?php
/**
 * Finance/funding_fin.php — التمويل والالتزامات (fin_funding_facilities + fin_funding_schedules) — §3.19/§11.
 * قروض/مرابحات/إيجارات/ضمانات/اعتمادات + جدول سداد بأقساطها (total_due مولّد).
 * يولّد جدول السداد آليّاً عند الإنشاء. شاشة مستقلة — عزل شركة + حذف ناعم. لا FK للخارج.
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

$perms = fin_page_perms($conn, 'Finance/funding_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+التمويل+❌"); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$ftypes = fin_facility_types(); $purposes = fin_facility_purposes(); $fstates = fin_facility_states();

// ── تفعيل/إقفال التمويل ──
if (isset($_GET['action']) && isset($_GET['fid'])) {
    if (!$can_edit) { header("Location: funding_fin.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $fid = intval($_GET['fid']); $act = $_GET['action'];
    if ($act === 'activate') fin_gate($is_super_admin)->update('fin_funding_facilities', array('state' => 'active'), array('id' => $fid), "state IN('draft','approved')");
    elseif ($act === 'settle') fin_gate($is_super_admin)->update('fin_funding_facilities', array('state' => 'settled'), array('id' => $fid), "state='active'");
    header("Location: funding_fin.php?fid=$fid&msg=تم+تحديث+حالة+التمويل+✅"); exit();
}

// ── تسجيل سداد قسط ──
if (isset($_GET['pay_inst'])) {
    if (!$can_edit) { header("Location: funding_fin.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $iid = intval($_GET['pay_inst']); $fid = intval($_GET['fid'] ?? 0);
    // paid_amount=total_due تعبيرٌ عمودي: نقرأ القسط ثم نضع القيمة (نفس النتيجة)
    $inst = fin_gate($is_super_admin)->selectOne('fin_funding_schedules', array('columns' => array('total_due'), 'where' => array('id' => $iid)));
    if ($inst) {
        fin_gate($is_super_admin)->update('fin_funding_schedules',
            array('paid_amount' => $inst['total_due'], 'state' => 'paid'), array('id' => $iid));
    }
    header("Location: funding_fin.php?fid=$fid&msg=تم+تسجيل+سداد+القسط+✅"); exit();
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: funding_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $d = intval($_GET['delete_id']);
    fin_gate($is_super_admin)->update('fin_funding_facilities',
        array('is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $current_user_id),
        array('id' => $d), "state='draft'");
    header("Location: funding_fin.php?msg=تم+حذف+التمويل+✅"); exit();
}

// ── إنشاء تمويل + توليد جدول السداد ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['facility_type'])) {
    if (!$can_add) { header("Location: funding_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    $ftype    = trim($_POST['facility_type'] ?? '');
    $purpose  = trim($_POST['purpose'] ?? '');
    $lender   = trim($_POST['lender_name'] ?? '');
    $principal= round(floatval($_POST['principal'] ?? 0), 2);
    $rate     = ($_POST['profit_rate'] ?? '') === '' ? null : round(floatval($_POST['profit_rate']), 4);
    $currency = in_array($_POST['currency'] ?? '', array('SDG','USD'), true) ? $_POST['currency'] : 'SDG';
    $start    = trim($_POST['start_date'] ?? '') ?: date('Y-m-d');
    $insts    = max(1, min(120, intval($_POST['installments'] ?? 1)));

    if (!isset($ftypes[$ftype]) || !isset($purposes[$purpose]) || $principal <= 0) {
        header("Location: funding_fin.php?msg=بيانات+غير+صحيحة+(نوع/غرض/مبلغ)+❌"); exit();
    }
    $end = date('Y-m-d', strtotime("$start +$insts months"));
    $facility_no = fin_gen_code($conn, 'fin_funding_facilities', 'FIN-FN', $company_id);
    $princ_per = round($principal / $insts, 2);
    $total_profit = $rate !== null ? round($principal * ($rate / 100.0), 2) : 0;
    $profit_per = round($total_profit / $insts, 2);
    // التمويل + جدول أقساطه زوجٌ مترابط ذرّيًا (تمويلٌ بلا جدول = التزامٌ مكسور) — معاملة §9
    $fid = fin_gate($is_super_admin)->runInTransaction(function ($g) use ($facility_no, $ftype, $purpose, $lender, $principal, $rate, $currency, $start, $end, $current_user_id, $insts, $princ_per, $profit_per) {
        $fid = $g->insert('fin_funding_facilities', array(
            'facility_no' => $facility_no, 'facility_type' => $ftype, 'purpose' => $purpose,
            'lender_name' => $lender, 'principal' => $principal, 'profit_rate' => $rate,
            'currency' => $currency, 'start_date' => $start, 'end_date' => $end,
            'state' => 'draft', 'created_by' => $current_user_id,
        ));
        for ($i = 1; $i <= $insts; $i++) {
            $g->insert('fin_funding_schedules', array(
                'facility_id' => $fid, 'installment_no' => $i,
                'due_date' => date('Y-m-d', strtotime("$start +$i months")),
                'principal_due' => $princ_per, 'profit_due' => $profit_per,
            ));
        }
        return $fid;
    }, 'funding facility + schedule');
    header("Location: funding_fin.php?fid=$fid&msg=تم+إنشاء+التمويل+وجدول+سداده+($insts+قسط)+✅"); exit();
}

$sel_fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;

$page_title = 'إيكوبيشن | التمويل والالتزامات';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main fin-funding-main ems-unified-page-shell">
    <?php
    $header_title = 'التمويل والالتزامات'; $header_icon = 'fa fa-landmark';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة تمويل'); }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة تمويل (يولّد جدول السداد آليّاً)</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>نوع التمويل <span class="required">*</span></label>
                <select name="facility_type"><?php foreach ($ftypes as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>الغرض <span class="required">*</span></label>
                <select name="purpose"><?php foreach ($purposes as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>الممول</label><input type="text" name="lender_name" placeholder="اسم البنك/الممول"></div>
            <div class="form-group"><label>أصل التمويل <span class="required">*</span></label><input type="number" step="0.01" min="0" name="principal" required></div>
            <div class="form-group"><label>هامش الربح %</label><input type="number" step="0.01" name="profit_rate" placeholder="مثال 12"></div>
            <div class="form-group"><label>العملة</label><select name="currency"><option value="SDG">SDG</option><option value="USD">USD</option></select></div>
            <div class="form-group"><label>تاريخ البداية</label><input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>"></div>
            <div class="form-group"><label>عدد الأقساط <span class="required">*</span></label><input type="number" min="1" max="120" name="installments" value="12" required></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-cancel" onclick="$('#finForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-landmark"></i> التمويلات والالتزامات</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>الإجراءات</th><th>الرقم</th><th>النوع</th><th>الغرض</th><th>الممول</th><th>الأصل</th><th>الهامش %</th><th>الحالة</th></tr></thead>
                <tbody>
                <?php
                $fac_rows = fin_gate($is_super_admin)->select('fin_funding_facilities', array('orderBy' => 'id DESC'));
                { foreach ($fac_rows as $row) {
                    $st = (string)$row['state']; $id = intval($row['id']);
                    $tone = $st === 'active' ? 'success' : ($st === 'settled' ? 'primary' : ($st === 'closed' ? 'dark' : 'secondary'));
                    echo "<tr><td><div class='action-btns'>";
                    echo "<a href='?fid=$id' class='action-btn' title='جدول السداد'><i class='fas fa-list-check'></i></a>";
                    if ($can_edit && in_array($st, array('draft','approved'))) echo "<a href='?action=activate&fid=$id' class='action-btn edit' title='تفعيل'><i class='fas fa-play'></i></a>";
                    if ($can_edit && $st === 'active') echo "<a href='?action=settle&fid=$id' class='action-btn edit' title='تسديد' onclick='return confirm(\"وضع التمويل كمُسدَّد؟\")'><i class='fas fa-flag-checkered'></i></a>";
                    if ($can_delete && $st === 'draft') echo "<a href='?delete_id=$id' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)$row['facility_no']) . "</td>";
                    echo "<td>" . htmlspecialchars($ftypes[$row['facility_type']] ?? $row['facility_type']) . "</td>";
                    echo "<td>" . htmlspecialchars($purposes[$row['purpose']] ?? $row['purpose']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['lender_name'] ?? '—')) . "</td>";
                    echo "<td>" . number_format((float)$row['principal'], 2) . ' ' . htmlspecialchars((string)$row['currency']) . "</td>";
                    echo "<td>" . ($row['profit_rate'] !== null ? number_format((float)$row['profit_rate'], 2) . '%' : '—') . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . htmlspecialchars($fstates[$st] ?? $st) . "</span></td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <?php if ($sel_fid > 0): ?>
        <h5 style="margin:18px 0 10px"><i class="fas fa-calendar-days"></i> جدول سداد التمويل #<?php echo $sel_fid; ?></h5>
        <div class="table-container">
            <table class="alltables" style="width:100%;">
                <thead><tr><th>القسط</th><th>الاستحقاق</th><th>الأصل</th><th>الفائدة</th><th>الإجمالي</th><th>المسدَّد</th><th>الحالة</th><th>الإجراء</th></tr></thead>
                <tbody>
                <?php
                $today = date('Y-m-d'); $sum_total = 0; $sum_paid = 0;
                $sched_rows = fin_gate($is_super_admin)->select('fin_funding_schedules', array(
                    'where' => array('facility_id' => $sel_fid), 'orderBy' => 'installment_no ASC'));
                { foreach ($sched_rows as $s) {
                    $sum_total += (float)$s['total_due']; $sum_paid += (float)$s['paid_amount'];
                    $overdue = ($s['state'] !== 'paid' && $s['due_date'] < $today);
                    $st = $overdue ? 'overdue' : (string)$s['state'];
                    $tone = $st === 'paid' ? 'success' : ($st === 'overdue' ? 'danger' : ($st === 'partial' ? 'primary' : 'secondary'));
                    $lbl = array('due' => 'مستحق', 'partial' => 'جزئي', 'paid' => 'مسدَّد', 'overdue' => 'متأخر');
                    echo "<tr>";
                    echo "<td>" . intval($s['installment_no']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)$s['due_date']) . "</td>";
                    echo "<td>" . number_format((float)$s['principal_due'], 2) . "</td>";
                    echo "<td>" . number_format((float)$s['profit_due'], 2) . "</td>";
                    echo "<td>" . number_format((float)$s['total_due'], 2) . "</td>";
                    echo "<td>" . number_format((float)$s['paid_amount'], 2) . "</td>";
                    echo "<td><span class='badge badge-" . $tone . "'>" . ($lbl[$st] ?? $st) . "</span></td>";
                    echo "<td>";
                    if ($can_edit && $s['state'] !== 'paid') echo "<a href='?pay_inst=" . intval($s['id']) . "&fid=$sel_fid' class='action-btn edit' title='تسجيل سداد'><i class='fas fa-money-bill'></i></a>";
                    echo "</td></tr>";
                } }
                ?>
                </tbody>
                <tfoot><tr><th colspan="4">الإجمالي</th><th><?php echo number_format($sum_total, 2); ?></th><th><?php echo number_format($sum_paid, 2); ?></th><th colspan="2">المتبقّي: <?php echo number_format($sum_total - $sum_paid, 2); ?></th></tr></tfoot>
            </table>
        </div>
        <?php endif; ?>
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
    $('#finTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
    $('#toggleForm').on('click', function () { $('#finForm').toggleClass('allforms-visible'); });
});
</script>
</body>
</html>
