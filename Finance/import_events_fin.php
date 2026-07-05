<?php
/**
 * Finance/import_events_fin.php — الربط التلقائي من الإدارات (نموذج السحب) — §16.
 * ★ صفر كتابة على أي جدول قائم ★ — المالية تقرأ proc_order/mnt_order (SELECT فقط)
 * وتُنشئ أحداثاً في fin_financial_events فقط. إزالة التكرار عبر source_ref.
 */
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = fin_page_perms($conn, 'Finance/import_events_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+الاستيراد+❌"); exit(); }
$cid = intval($company_id);

/** أحداث المشتريات غير المستوردة (proc_order → مصروف). READ ONLY على proc_order. */
function fin_import_proc($conn, $cid, $uid)
{
    $sql = "INSERT INTO fin_financial_events
              (company_id, event_no, event_type, source_module, source_ref, amount, currency, state, notes, created_by)
            SELECT po.company_id, CONCAT('FIN-EV-IMP-', po.id), 'expense', 'procurement', po.code,
                   po.total_amount, COALESCE(po.currency,'SDG'), 'draft', CONCAT('استيراد آلي من أمر شراء ', po.code), $uid
            FROM proc_order po
            WHERE po.company_id = $cid AND COALESCE(po.is_deleted,0) = 0 AND COALESCE(po.total_amount,0) > 0
              AND po.code IS NOT NULL AND po.code <> ''
              AND NOT EXISTS (SELECT 1 FROM fin_financial_events fe
                              WHERE fe.company_id = po.company_id AND fe.source_module='procurement'
                                AND fe.source_ref = po.code AND COALESCE(fe.is_deleted,0)=0)";
    mysqli_query($conn, $sql);
    return mysqli_affected_rows($conn);
}

/** أحداث الصيانة غير المستوردة (mnt_order → مصروف بأبعاده). READ ONLY على mnt_order. */
function fin_import_mnt($conn, $cid, $uid)
{
    $sql = "INSERT INTO fin_financial_events
              (company_id, event_no, event_type, source_module, source_ref, amount, currency, equipment_id, project_id, state, notes, created_by)
            SELECT mo.company_id, CONCAT('FIN-EV-IMM-', mo.id), 'expense', 'maintenance', mo.code,
                   mo.total_cost, 'SDG', mo.equipment_id, mo.project_id, 'draft', CONCAT('استيراد آلي من أمر صيانة ', mo.code), $uid
            FROM mnt_order mo
            WHERE mo.company_id = $cid AND COALESCE(mo.is_deleted,0) = 0 AND COALESCE(mo.total_cost,0) > 0
              AND mo.code IS NOT NULL AND mo.code <> ''
              AND NOT EXISTS (SELECT 1 FROM fin_financial_events fe
                              WHERE fe.company_id = mo.company_id AND fe.source_module='maintenance'
                                AND fe.source_ref = mo.code AND COALESCE(fe.is_deleted,0)=0)";
    mysqli_query($conn, $sql);
    return mysqli_affected_rows($conn);
}

if (isset($_GET['gen_proc'])) {
    if (!$can_add) { header("Location: import_events_fin.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $n = fin_import_proc($conn, $cid, $current_user_id);
    header("Location: import_events_fin.php?msg=تم+توليد+$n+حدث+من+المشتريات+✅"); exit();
}
if (isset($_GET['gen_mnt'])) {
    if (!$can_add) { header("Location: import_events_fin.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $n = fin_import_mnt($conn, $cid, $current_user_id);
    header("Location: import_events_fin.php?msg=تم+توليد+$n+حدث+من+الصيانة+✅"); exit();
}

$page_title = 'إيكوبيشن | استيراد أحداث الإدارات';
include '../inheader.php';
include '../insidebar.php';

// عدّادات المرشّحين (قراءة فقط)
$proc_pending = (int) (mysqli_query($conn, "SELECT COUNT(*) c FROM proc_order po WHERE po.company_id=$cid AND COALESCE(po.is_deleted,0)=0 AND COALESCE(po.total_amount,0)>0 AND po.code IS NOT NULL AND po.code<>'' AND NOT EXISTS (SELECT 1 FROM fin_financial_events fe WHERE fe.company_id=po.company_id AND fe.source_module='procurement' AND fe.source_ref=po.code AND COALESCE(fe.is_deleted,0)=0)")->fetch_assoc()['c'] ?? 0);
$mnt_pending  = (int) (mysqli_query($conn, "SELECT COUNT(*) c FROM mnt_order mo WHERE mo.company_id=$cid AND COALESCE(mo.is_deleted,0)=0 AND COALESCE(mo.total_cost,0)>0 AND mo.code IS NOT NULL AND mo.code<>'' AND NOT EXISTS (SELECT 1 FROM fin_financial_events fe WHERE fe.company_id=mo.company_id AND fe.source_module='maintenance' AND fe.source_ref=mo.code AND COALESCE(fe.is_deleted,0)=0)")->fetch_assoc()['c'] ?? 0);
?>
<div class="main fin-import-main ems-unified-page-shell">
    <?php
    $header_title = 'استيراد أحداث الإدارات (نموذج السحب)'; $header_icon = 'fa fa-file-import';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <div class="card"><div class="card-body">
        <div class="success-message is-success" style="background:#eef6ff;border-color:#b6d4fe;color:#084298">
            <i class="fas fa-shield-halved"></i>
            المالية تقرأ أوامر المشتريات والصيانة <strong>قراءةً فقط</strong> وتولّد أحداثاً مالية جديدة —
            <strong>دون أي تعديل على النظام القائم</strong>. الأحداث المولّدة تبدأ «مسودة» وتمرّ بدورة الاعتماد كالمعتاد.
        </div>

        <div class="form-grid" style="margin-top:12px">
            <div class="card" style="text-align:center"><div class="card-body">
                <h5><i class="fas fa-file-invoice-dollar"></i> المشتريات (أوامر الشراء)</h5>
                <p style="font-size:26px;margin:8px 0"><strong><?php echo $proc_pending; ?></strong></p>
                <p class="text-muted">أمر شراء لم يُولّد له حدث بعد</p>
                <?php if ($can_add && $proc_pending > 0): ?>
                    <a href="?gen_proc=1" class="btn-save" style="display:inline-block;text-decoration:none" onclick="return confirm('توليد <?php echo $proc_pending; ?> حدث مصروف من المشتريات؟')"><i class="fas fa-bolt"></i> توليد أحداث المشتريات</a>
                <?php elseif ($proc_pending === 0): ?>
                    <span class="badge badge-success">لا جديد — كل الأوامر مستوردة</span>
                <?php endif; ?>
            </div></div>

            <div class="card" style="text-align:center"><div class="card-body">
                <h5><i class="fas fa-screwdriver-wrench"></i> الصيانة (أوامر الصيانة)</h5>
                <p style="font-size:26px;margin:8px 0"><strong><?php echo $mnt_pending; ?></strong></p>
                <p class="text-muted">أمر صيانة بتكلفة لم يُولّد له حدث بعد</p>
                <?php if ($can_add && $mnt_pending > 0): ?>
                    <a href="?gen_mnt=1" class="btn-save" style="display:inline-block;text-decoration:none" onclick="return confirm('توليد <?php echo $mnt_pending; ?> حدث مصروف من الصيانة؟')"><i class="fas fa-bolt"></i> توليد أحداث الصيانة</a>
                <?php elseif ($mnt_pending === 0): ?>
                    <span class="badge badge-success">لا جديد — كل الأوامر مستوردة</span>
                <?php endif; ?>
            </div></div>
        </div>
    </div></div>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-list"></i> الأحداث المستوردة</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr><th>رقم الحدث</th><th>المصدر</th><th>المرجع</th><th>المبلغ</th><th>الحالة</th><th>ملاحظة</th></tr></thead>
                <tbody>
                <?php
                $scope = fin_scope('company_id', $is_super_admin, $company_id);
                $states = fin_event_states(); $mods = fin_source_modules();
                $sql = "SELECT * FROM fin_financial_events WHERE $scope AND COALESCE(is_deleted,0)=0
                        AND source_module IN('procurement','maintenance') AND event_no LIKE 'FIN-EV-IM%' ORDER BY id DESC";
                if ($res = mysqli_query($conn, $sql)) { while ($row = mysqli_fetch_assoc($res)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars((string)$row['event_no']) . "</td>";
                    echo "<td><span class='badge badge-primary'>" . htmlspecialchars($mods[$row['source_module']] ?? $row['source_module']) . "</span></td>";
                    echo "<td>" . htmlspecialchars((string)($row['source_ref'] ?? '')) . "</td>";
                    echo "<td>" . number_format((float)$row['amount'], 2) . " " . htmlspecialchars((string)$row['currency']) . "</td>";
                    echo "<td><span class='badge badge-" . fin_state_tone($row['state']) . "'>" . htmlspecialchars($states[$row['state']] ?? $row['state']) . "</span></td>";
                    echo "<td>" . htmlspecialchars((string)($row['notes'] ?? '')) . "</td>";
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
    $('#finTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
});
</script>
</body>
</html>
