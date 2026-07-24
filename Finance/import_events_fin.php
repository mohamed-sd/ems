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

/**
 * مرشّحو الاستيراد لمصدرٍ ما: أوامرُ بمبلغٍ موجب وكودٍ غير فارغ لم يُولَّد لها حدثٌ بعد.
 * NOT EXISTS المترابط الأصلي (مزدوج النطاق على جدولين مستأجرين) → قراءتان معزولتان
 * عبر البوابة تُطابَقان في PHP (نمط bank_reconciliation المثبَت). القراءتان تُحقنان
 * company_id تلقائيًّا وتستبعدان is_deleted (يوافق po.company_id=fe.company_id=$cid
 * وCOALESCE(is_deleted,0)=0 الأصليَّين — الحدث المؤرشَف لا يمنع إعادة التوليد).
 */
function fin_pending_import($gate, $srcTable, $amtCol, $module)
{
    $cands = $gate->select($srcTable, array('whereRaw' => "COALESCE(`$amtCol`,0) > 0 AND `code` IS NOT NULL AND `code` <> ''"));
    $used  = $gate->select('fin_financial_events', array('columns' => array('source_ref'), 'where' => array('source_module' => $module)));
    $usedSet = array();
    foreach ($used as $u) { $usedSet[(string)$u['source_ref']] = true; }
    $out = array();
    foreach ($cands as $c) { if (!isset($usedSet[(string)$c['code']])) { $out[] = $c; } }
    return $out;
}

/** أحداث المشتريات غير المستوردة (proc_order → مصروف). قراءة فقط على proc_order. */
function fin_import_proc($gate, $uid)
{
    $n = 0;
    foreach (fin_pending_import($gate, 'proc_order', 'total_amount', 'procurement') as $po) {
        $gate->insert('fin_financial_events', array(
            'event_no'      => 'FIN-EV-IMP-' . intval($po['id']),
            'event_type'    => 'expense',
            'source_module' => 'procurement',
            'source_ref'    => $po['code'],
            'amount'        => $po['total_amount'],
            'currency'      => ($po['currency'] !== null && $po['currency'] !== '') ? $po['currency'] : 'SDG',
            'state'         => 'draft',
            'notes'         => 'استيراد آلي من أمر شراء ' . $po['code'],
            'created_by'    => $uid,
        ));
        $n++;
    }
    return $n;
}

/** أحداث الصيانة غير المستوردة (mnt_order → مصروف بأبعاده). قراءة فقط على mnt_order. */
function fin_import_mnt($gate, $uid)
{
    $n = 0;
    foreach (fin_pending_import($gate, 'mnt_order', 'total_cost', 'maintenance') as $mo) {
        $gate->insert('fin_financial_events', array(
            'event_no'      => 'FIN-EV-IMM-' . intval($mo['id']),
            'event_type'    => 'expense',
            'source_module' => 'maintenance',
            'source_ref'    => $mo['code'],
            'amount'        => $mo['total_cost'],
            'currency'      => 'SDG',
            'equipment_id'  => $mo['equipment_id'],
            'project_id'    => $mo['project_id'],
            'state'         => 'draft',
            'notes'         => 'استيراد آلي من أمر صيانة ' . $mo['code'],
            'created_by'    => $uid,
        ));
        $n++;
    }
    return $n;
}

if (isset($_GET['gen_proc'])) {
    if (!$can_add) { header("Location: import_events_fin.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $n = fin_import_proc(ems_tenant_db(), $current_user_id);
    header("Location: import_events_fin.php?msg=تم+توليد+$n+حدث+من+المشتريات+✅"); exit();
}
if (isset($_GET['gen_mnt'])) {
    if (!$can_add) { header("Location: import_events_fin.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    $n = fin_import_mnt(ems_tenant_db(), $current_user_id);
    header("Location: import_events_fin.php?msg=تم+توليد+$n+حدث+من+الصيانة+✅"); exit();
}

$page_title = 'إيكوبيشن | استقبال معاملات الإدارات';
include '../inheader.php';
include '../insidebar.php';

// عدّادات المرشّحين (قراءة فقط) — نفس منطق قراءتَي fin_pending_import عبر البوابة
$proc_pending = count(fin_pending_import(ems_tenant_db(), 'proc_order', 'total_amount', 'procurement'));
$mnt_pending  = count(fin_pending_import(ems_tenant_db(), 'mnt_order', 'total_cost', 'maintenance'));
?>
<div class="main fin-import-main ems-unified-page-shell">
    <?php
    $header_title = 'استقبال معاملات الإدارات (نموذج السحب)'; $header_icon = 'fa fa-file-import';
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
                $states = fin_event_states(); $mods = fin_source_modules();
                // العزل عبر البوابة (super→كل الشركات via forAllTenants، يوافق fin_scope '1=1')
                $imported = fin_gate($is_super_admin)->select('fin_financial_events', array(
                    'whereRaw' => "source_module IN('procurement','maintenance') AND event_no LIKE 'FIN-EV-IM%'",
                    'orderBy'  => 'id DESC',
                ));
                foreach ($imported as $row) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars((string)$row['event_no']) . "</td>";
                    echo "<td><span class='badge badge-primary'>" . htmlspecialchars($mods[$row['source_module']] ?? $row['source_module']) . "</span></td>";
                    echo "<td>" . htmlspecialchars((string)($row['source_ref'] ?? '')) . "</td>";
                    echo "<td>" . number_format((float)$row['amount'], 2) . " " . htmlspecialchars((string)$row['currency']) . "</td>";
                    echo "<td><span class='badge badge-" . fin_state_tone($row['state']) . "'>" . htmlspecialchars($states[$row['state']] ?? $row['state']) . "</span></td>";
                    echo "<td>" . htmlspecialchars((string)($row['notes'] ?? '')) . "</td>";
                    echo "</tr>";
                }
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
