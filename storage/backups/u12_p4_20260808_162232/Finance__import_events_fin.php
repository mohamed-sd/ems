<?php
/**
 * Finance/import_events_fin.php — الربط التلقائي من الإدارات (نموذج السحب) — §16.
 * ★ صفر كتابة على أي جدول قائم ★ — المالية تقرأ proc_order/mnt_order (SELECT فقط)
 * وتولّد أحداثها عبر الناشر EventPublisher حصرًا (ADR-15: الحقيقة تسبق إسقاطها —
 * كل حدثٍ يحمل root_event_id ونسبَ مستنده entity_type/entity_id). إزالة التكرار
 * مزدوجة: مطابقة source_ref للعرض + عطالة بنيوية بمفتاح (المستند × النوع).
 * سُدّ هنا خرقُ الكتابة المباشرة على الدفتر (v8 §15-أ · UX-02 §9-①).
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

/**
 * توليد حدث استيرادٍ واحد عبر الناشر (القناة المحكومة) — لا كتابة مباشرة.
 * الشركة من صفّ المستند لا من الجلسة (يصحّ للسوبر، ويطابق درس cron/forSystem).
 * كل حدثٍ في معاملته: الجذر وإسقاطه ذرّيان معًا، وفشل صفٍّ لا يُسقط البقية.
 */
function fin_import_publish($conn, array $rows, array $cfg, $uid)
{
    require_once __DIR__ . '/../app/Core/EventPublisher.php';
    $n = 0;
    foreach ($rows as $row) {
        $docCompany = intval($row['company_id'] ?? 0);
        if ($docCompany <= 0) { error_log('fin import ' . $cfg['module'] . ' ' . $row['code'] . ': صف مستندٍ بلا شركة — تخطٍّ'); continue; }
        $conn->begin_transaction();
        try {
            \App\Core\EventPublisher::publish($conn, array(
                'event_key'         => $cfg['event_key'],
                'category'          => 'financial',
                'source_module'     => $cfg['module'],
                'company_id'        => $docCompany,
                'entity_type'       => $cfg['entity_type'],
                'entity_id'         => intval($row['id']),
                'occurred_at'       => gmdate('Y-m-d H:i:s'),
                'created_by'        => intval($uid) > 0 ? intval($uid) : 1,
                'idempotency_key'   => $cfg['idem_prefix'] . intval($row['id']),
                'legacy_event_type' => 'expense',
                'amount'            => $row[$cfg['amount_col']],
                'currency'          => (isset($row['currency']) && $row['currency'] !== null && $row['currency'] !== '') ? $row['currency'] : 'SDG',
                'source_ref'        => $row['code'],
                'equipment_id'      => (isset($row['equipment_id']) && intval($row['equipment_id']) > 0) ? intval($row['equipment_id']) : null,
                'project_id'        => (isset($row['project_id']) && intval($row['project_id']) > 0) ? intval($row['project_id']) : null,
                'notes'             => $cfg['note_prefix'] . $row['code'],
                'payload'           => array('order_id' => intval($row['id']), 'order_code' => (string)$row['code'], 'channel' => 'import_events_fin'),
            ));
            $conn->commit();
            $n++;
        } catch (\Throwable $t) {
            $conn->rollback();
            error_log('fin import ' . $cfg['module'] . ' ' . $row['code'] . ': ' . $t->getMessage());
        }
    }
    return $n;
}

/**
 * أحداث المشتريات غير المنشورة (proc_order → مصروف).
 * ─────────────────────────────────────────────────────────────────────────
 * منذ 2026-07-27: **الاستلامُ النهائي** ينشر الأثرَ من منبعه (UX-09 §2 و§5.1-③)،
 * وهذه الشاشةُ شبكةُ أمانٍ لا طريق. وتستدعي ناشرَ المشتريات نفسَه
 * (`proc_publish_order_cost`) — فالبوابةُ واحدةٌ في القناتين: **لا مصروفَ عن
 * أمرٍ لم تصل بضاعتُه** (كان الزرُّ ينشر المسوداتِ والمؤكَّدَ بلا فحصِ حالة).
 */
function fin_import_proc($conn, $gate, $uid)
{
    require_once __DIR__ . '/../Procurement/proc_helpers.php';
    $n = 0;
    foreach (fin_pending_import($gate, 'proc_order', 'total_amount', 'procurement') as $row) {
        if (proc_publish_order_cost($conn, intval($row['id']), $uid, 'import_events_fin') === 'published') { $n++; }
    }
    return $n;
}

/**
 * أحداث الصيانة غير المنشورة (mnt_order → مصروف بأبعاده).
 * ─────────────────────────────────────────────────────────────────────────
 * منذ 2026-07-27 صار **إقفالُ الأمر** ينشر أثرَه من منبعه (UX-04 §8.2 · FES §7)،
 * فهذه الشاشةُ لم تعد الطريقَ بل **شبكةَ أمانٍ** للأوامر المقفلة قبل التغيير أو
 * التي تعثّر نشرُها. ولذلك تستدعي ناشرَ الصيانة نفسَه (`mnt_publish_order_cost`)
 * لا نسخةً ثانيةً من العقد — فتعريفُ الحدث واحدٌ في وحدته المالكة، ومفتاحُ
 * العطالة نفسُه يمنع أي ازدواج بين القناتين.
 */
function fin_import_mnt($conn, $gate, $uid)
{
    require_once __DIR__ . '/../Maintenance/mnt_helpers.php';
    $n = 0;
    foreach (fin_pending_import($gate, 'mnt_order', 'total_cost', 'maintenance') as $row) {
        if (mnt_publish_order_cost($conn, intval($row['id']), $uid, 'import_events_fin') === 'published') { $n++; }
    }
    return $n;
}

if (isset($_GET['gen_proc'])) {
    if (!$can_add) { header("Location: import_events_fin.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    if (!fin_verify_action_token()) { header("Location: import_events_fin.php?msg=رمز+الحماية+غير+صالح+❌"); exit(); }
    $n = fin_import_proc($conn, ems_tenant_db(), $current_user_id);
    header("Location: import_events_fin.php?msg=تم+توليد+$n+حدث+من+المشتريات+✅"); exit();
}
if (isset($_GET['gen_mnt'])) {
    if (!$can_add) { header("Location: import_events_fin.php?msg=لا+توجد+صلاحية+❌"); exit(); }
    if (!fin_verify_action_token()) { header("Location: import_events_fin.php?msg=رمز+الحماية+غير+صالح+❌"); exit(); }
    $n = fin_import_mnt($conn, ems_tenant_db(), $current_user_id);
    header("Location: import_events_fin.php?msg=تم+توليد+$n+حدث+من+الصيانة+✅"); exit();
}

$page_title = 'إيكوبيشن | استقبال معاملات الإدارات';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

// عدّادات المرشّحين (قراءة فقط) — نفس منطق قراءتَي fin_pending_import عبر البوابة
// العدّادُ يعكس ما سيُنشر فعلًا — لا ما يمرّ بالمرشِّح الخام: أمرٌ لم تصل بضاعتُه
// يُستبعد بالبوابة (proc_order_expense_states)، فلا يَعِد الزرُّ بعددٍ ثم يولّد أقلّ.
require_once __DIR__ . '/../Procurement/proc_helpers.php';
$proc_pending = 0;
foreach (fin_pending_import(ems_tenant_db(), 'proc_order', 'total_amount', 'procurement') as $__po) {
    if (in_array((string) $__po['state'], proc_order_expense_states(), true)) { $proc_pending++; }
}
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
                    <a href="?gen_proc=1&_t=<?php echo fin_action_token(); ?>" class="btn-save" style="display:inline-block;text-decoration:none" onclick="return confirm('توليد <?php echo $proc_pending; ?> حدث مصروف من المشتريات؟')"><i class="fas fa-bolt"></i> توليد أحداث المشتريات</a>
                <?php elseif ($proc_pending === 0): ?>
                    <span class="badge badge-success">لا جديد — كل الأوامر مستوردة</span>
                <?php endif; ?>
            </div></div>

            <div class="card" style="text-align:center"><div class="card-body">
                <h5><i class="fas fa-screwdriver-wrench"></i> الصيانة (أوامر الصيانة)</h5>
                <p style="font-size:26px;margin:8px 0"><strong><?php echo $mnt_pending; ?></strong></p>
                <p class="text-muted">أمر صيانة بتكلفة لم يُولّد له حدث بعد</p>
                <?php if ($can_add && $mnt_pending > 0): ?>
                    <a href="?gen_mnt=1&_t=<?php echo fin_action_token(); ?>" class="btn-save" style="display:inline-block;text-decoration:none" onclick="return confirm('توليد <?php echo $mnt_pending; ?> حدث مصروف من الصيانة؟')"><i class="fas fa-bolt"></i> توليد أحداث الصيانة</a>
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
                <thead><tr><th>رقم الحدث</th><th>المصدر</th><th>المرجع</th><th>المبلغ</th><th>الحالة</th><th>ملاحظة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead>
                <tbody>
                <?php
                $states = fin_event_states(); $mods = fin_source_modules();
                // العزل عبر البوابة (super→كل الشركات via forAllTenants، يوافق fin_scope '1=1')
                // المرشّح بنسب المستند لا بنمط الترقيم — أحداث الناشر EV-… والقديمة FIN-EV-IM… معًا
                $imported = fin_gate($is_super_admin)->select('fin_financial_events', array(
                    'whereRaw' => "source_module IN('procurement','maintenance') AND (entity_type IN('proc_order','mnt_order') OR event_no LIKE 'FIN-EV-IM%')",
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
