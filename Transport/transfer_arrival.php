<?php
/**
 * Transport/transfer_arrival.php — الوصولُ والتسليم (★ النقل · update0007 S-08)
 * ───────────────────────────────────────────────────────────────────────────
 * الأوامرُ الواصلةُ بانتظار تأكيدِ التسليم — والتسليمُ يقدّمها إلى الإقفال
 * (شاشةُ «إقفال الأمر وتحميل التكلفة» تُتمّ الدورة).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Transport/TransferDeliveryService.php';

// CS-01 · RF-02 — الحارسُ فوقَ المعالج (كان الإدراجُ في السطرِ 22 و‎insidebar‎ في 43).
enforce_current_page_view_permission($conn, '../main/dashboard.php');

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

/* ══ FN-08 (P0) — «مستندُ التسليمِ لا يُخزَّن وتكرارُ الإرسالِ يُدرج صفًّا» ══
   الحكمُ انتقل إلى خدمةِ النطاق: مستندٌ مخزَّنٌ بمرجعِه ووقتِه وشاهدِه، وحدثٌ
   واحدٌ مهما تكرر الإرسال (فريدٌ مركَّبٌ في القاعدةِ لا فحصٌ في التطبيق). */
$__pc = ems_post_contract($conn, array(
    'action'  => 'trs.transfer.confirm_delivery',
    'perm'    => 'can_edit',
    'trigger' => 'deliver_id',
    /* INJ-0548: مفتاحُ العطالةِ يضمُّ **مفتاحَ العميلِ** (`ems_idem`) — فإعادةُ
       الإرسالِ من صندوقِ الميدانِ بعد عودةِ الشبكةِ تحمل المفتاحَ نفسَه، فيعرف
       الخادمُ أنَّها هي هي ولا يكتب مرتين. وغيابُه لا يضرُّ المسارَ العادي. */
    'idem'    => array('order' => intval($_POST['deliver_id'] ?? 0),
                       'cli'   => trim((string) ($_POST['ems_idem'] ?? ''))),
    'validate' => function (array $in) {
        $oid = intval($in['deliver_id'] ?? 0);
        $wit = trim($in['witness_name'] ?? '');
        if ($oid <= 0) { return array('ok' => false, 'msg' => 'أمرٌ غيرُ صالح (422)'); }
        if ($wit === '') { return array('ok' => false, 'msg' => 'شاهدُ التسليم إلزاميّ (422)'); }
        return array('ok' => true, 'data' => array(
            'oid' => $oid, 'note' => trim($in['delivery_note'] ?? ''), 'witness' => $wit,
        ));
    },
));
if (!$__pc['ok'] && $__pc['msg'] !== '') { $msg = $__pc['msg']; }
if ($__pc['replay'])                     { $msg = $__pc['msg']; }
if ($__pc['run'] && $__pc['ok']) {
    $svc = new \App\Services\Transport\TransferDeliveryService($conn);
    $res = $svc->confirmDelivery($company_id, (int) $__pc['data']['oid'],
        (string) $__pc['data']['note'], (string) $__pc['data']['witness'], $uid);
    $msg = $res['msg'];
    if (!empty($res['ok']) && empty($res['replay'])) {
        ems_pc_idem_mark($conn, $__pc['idem'], $__pc['code'], 'transfer_delivery_docs#' . $res['doc_id']);
    }
}

$rows = array();
$r = mysqli_query($conn,
    "SELECT o.id, o.order_no, o.arrival_datetime, tl.name AS to_loc, e.name AS vehicle,
            (SELECT COUNT(*) FROM transfer_events ev WHERE ev.order_id = o.id AND ev.event_type = 'delivered') AS delivered,
            (SELECT d.doc_ref FROM transfer_delivery_docs d
              WHERE d.company_id = o.company_id AND d.order_id = o.id ORDER BY d.id DESC LIMIT 1) AS doc_ref
     FROM transfer_orders o
     LEFT JOIN trs_locations tl ON tl.id = o.to_location_id
     LEFT JOIN equipments e ON e.id = o.vehicle_id
     WHERE o.company_id = $company_id AND o.is_deleted = 0 AND o.stage = 'arrived'
     ORDER BY o.arrival_datetime DESC");
if ($r) while ($x = mysqli_fetch_assoc($r)) $rows[] = $x;

$page_title = 'الوصول والتسليم';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01: أنماطُ شاشةِ الوصولِ والتسليمِ أصنافًا برموزِ الألوان */
.trs-ar-ok{background:var(--c-198754, #198754)}
.trs-ar-wait{background:var(--c-6c757d, #6c757d)}
.trs-ar-w150{max-width:150px}
.trs-ar-w180{max-width:180px}
</style>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-flag-checkered';
$header_title_html = htmlspecialchars('الوصولُ والتسليم', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا أوامرَ واصلةً بانتظارِ توثيقِ التسليم', 'أكّدِ الوصولَ من شاشةِ «الحركةُ في الطريق» ليظهرَ الأمرُ هنا');
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <thead><tr><th>الأمر</th><th>الوجهة</th><th>المركبة</th><th>وصل</th><th>التسليم</th><th>إجراء</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted">لا أوامرَ واصلةً بانتظار التسليم</td></tr><?php endif; ?>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td><?= htmlspecialchars($o['order_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['to_loc'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['vehicle'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['arrival_datetime'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $o['doc_ref']
              ? '<span class="badge trs-ar-ok">مُسلَّم</span> <small class="text-muted">' . htmlspecialchars($o['doc_ref'], ENT_QUOTES, 'UTF-8') . '</small>'
              : '<span class="badge trs-ar-wait">بانتظار التسليم</span>' ?></td>
        <td>
          <?php if (!$o['doc_ref']): ?>
          <?php $rid = intval($o['id']); ?>
          <?php /* INJ-0548: نموذجُ «وصلت» موسومٌ لصندوقِ الإرسالِ دونَ اتصال —
                   بلا شبكةٍ يُحفظ محليًّا ويُعلَن «بانتظار المزامنة»، وعند العودةِ
                   يُرسَل مرةً واحدةً بمفتاحِ عطالتِه. */ ?>
          <form method="post" class="ta-arrival-form" data-ems-outbox="1"
                data-ems-outbox-label="تأكيدُ وصول">
        <?= csrf_field() ?>
            <input type="hidden" name="deliver_id" value="<?= $rid ?>">
            <label class="visually-hidden" for="dlv_wit_<?= $rid ?>">شاهدُ التسليم</label>
            <input aria-label="شاهدُ التسليم" id="dlv_wit_<?= $rid ?>" type="text" name="witness_name" required
                   class="form-control form-control-sm trs-ar-w150" placeholder="شاهدُ التسليم *">
            <label class="visually-hidden" for="dlv_note_<?= $rid ?>">ملاحظةُ التسليم</label>
            <input aria-label="ملاحظةُ التسليم" id="dlv_note_<?= $rid ?>" type="text" name="delivery_note"
                   class="form-control form-control-sm trs-ar-w180" placeholder="ملاحظةُ التسليم">
            <button class="action-btn" type="submit">تأكيدُ التسليم</button>
          </form>
          <?php else: ?>
          <!-- FIXC-0047: الرابطُ المباشرُ للإقفالِ يظهر **بعدَ** تأكيدِ الوصولِ لا قبلَه -->
          <a class="action-btn" href="transfer_close_cost.php?id=<?= intval($o['id']) ?>">أقفل بتكلفته ←</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
