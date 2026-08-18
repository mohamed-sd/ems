<?php
/**
 * Transport/transfer_close_cost.php — إقفالُ الأمر وتحميلُ التكلفة (★ · S-08)
 * ───────────────────────────────────────────────────────────────────────────
 * آخرُ الدورة: الأمرُ الواصلُ المُسلَّمُ يُقفل بتكلفته الفعلية محمَّلةً على
 * مشروعه (analytic_cost_center) — «أمرُ ترحيلٍ مقفَلٌ بتكلفةٍ محمَّلةٍ على
 * مشروعها» (شاهدُ UAT-01 §11-⑪).
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/post_contract.php';
require_once __DIR__ . '/../app/Services/Transport/TransferDeliveryService.php';

// CS-01 · RF-02 — الحارسُ فوقَ المعالج (كان ‎UPDATE‎ في السطرِ 25 و‎insidebar‎ في 55).
enforce_current_page_view_permission($conn, '../main/dashboard.php');

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$msg = '';

/* ══ FN-08 خطوة ④ — «ولا إقفالَ بتكلفةٍ قبلَ تخزينِ مستندِ التسليم» ═══════
   الحارسُ الترتيبيُّ داخلَ الخدمة: تُحمَّل تكلفةٌ على مشروعٍ **بسندٍ يُثبت
   وصولَ البضاعةِ** لا بادعاءِ مرحلة. */
$__pc = ems_post_contract($conn, array(
    'action'  => 'trs.transfer.close_with_cost',
    'perm'    => 'can_edit',
    'trigger' => 'close_id',
    'idem'    => array(
        'order' => intval($_POST['close_id'] ?? 0),
        'cost'  => (string) floatval($_POST['actual_cost'] ?? 0),
    ),
    'validate' => function (array $in) {
        $oid  = intval($in['close_id'] ?? 0);
        $cost = floatval($in['actual_cost'] ?? 0);
        if ($oid <= 0)  { return array('ok' => false, 'msg' => 'أمرٌ غيرُ صالح (422)'); }
        if ($cost <= 0) { return array('ok' => false, 'msg' => 'التكلفةُ الفعليةُ إلزاميةٌ للإقفال — ولا إقفالَ بتكلفةٍ صفر (422)'); }
        return array('ok' => true, 'data' => array('oid' => $oid, 'cost' => $cost));
    },
));
if (!$__pc['ok'] && $__pc['msg'] !== '') { $msg = $__pc['msg']; }
if ($__pc['replay'])                     { $msg = $__pc['msg']; }
if ($__pc['run'] && $__pc['ok']) {
    $svc = new \App\Services\Transport\TransferDeliveryService($conn);
    $res = $svc->closeWithCost($company_id, (int) $__pc['data']['oid'], (float) $__pc['data']['cost'], $uid);
    $msg = $res['msg'];
    if (!empty($res['ok'])) { ems_pc_idem_mark($conn, $__pc['idem'], $__pc['code'], 'transfer_orders#' . (int) $__pc['data']['oid']); }
}

/* ══ INJ-0317 — «لكلِّ عمودٍ مصدرٌ ومالكٌ ومعنًى» ═══════════════════════════
     كان الجدولُ يعلن خمسةَ أعمدةِ بياناتٍ ثم **ثلاثةً وعشرين رأسًا بلا خليةٍ من
     الخادم**، والتعليقُ نفسُه يقرّ: «الخلايا يحشوها ui-unification.js حتى ربط
     المصدر». فالرأسُ يَعِد بعمودٍ لا وجودَ له — و**رأسٌ يعد بما لا يفي أسوأُ من
     غيابِه** لأنَّه يُقرأ بيانًا.
   ◆ والحكمُ من نصِّ القبولِ حرفًا: «<td> مقابلٌ بقيمةٍ من الخادم **أو لا يظهر
     الرأسُ أصلًا»**. فخمسةَ عشرَ رأسًا وُصلت بمصدرِها المخزَّن، **وثمانيةٌ رُفعت**
     لأنَّها بلا سجلٍّ في القاعدةِ إطلاقًا (اعتمادُ مديرِ النقلِ والمالية ورقمُ
     القيدِ ومرجعُ التفويضِ وتاريخُ الاعتمادِ والعكسانِ ودرجةُ الأثر) — ولا
     يُخترع لها عمودٌ لتُملأ بفراغ.
   ◆ والتجميعُ في الاستعلامِ بمُجمِّعاتٍ فرعيةٍ لا بحلقةٍ لكلِّ صفّ. */
$rows = array();
$r = mysqli_query($conn,
    "SELECT o.id, o.order_no, o.estimated_cost_usd, o.actual_cost_usd, o.project_id,
            p.name AS project_name, o.arrival_datetime,
            o.notes, o.cost_bearer, o.analytic_cost_center, o.stage, o.sync_uuid,
            o.created_at, o.request_id, o.tariff_currency,
            u.name AS creator_name, cmp.company_name AS entity_name,
            rq.code AS request_code,
            (SELECT d.doc_ref FROM transfer_delivery_docs d
              WHERE d.company_id = o.company_id AND d.order_id = o.id ORDER BY d.id DESC LIMIT 1) AS doc_ref,
            (SELECT GROUP_CONCAT(DISTINCT cl.cost_type ORDER BY cl.cost_type SEPARATOR ' · ')
               FROM transfer_cost_lines cl
              WHERE cl.company_id = o.company_id AND cl.order_id = o.id AND cl.is_deleted = 0) AS cost_types,
            (SELECT SUM(cl.amount_usd) FROM transfer_cost_lines cl
              WHERE cl.company_id = o.company_id AND cl.order_id = o.id AND cl.is_deleted = 0) AS lines_usd,
            (SELECT MAX(cl.fx_rate) FROM transfer_cost_lines cl
              WHERE cl.company_id = o.company_id AND cl.order_id = o.id AND cl.is_deleted = 0) AS fx_rate,
            (SELECT COUNT(*) FROM transfer_attachments a
              WHERE a.company_id = o.company_id AND a.order_id = o.id AND a.is_deleted = 0) AS att_n
     FROM transfer_orders o
     LEFT JOIN project p ON p.id = o.project_id
     LEFT JOIN users u ON u.id = o.created_by
     LEFT JOIN admin_companies cmp ON cmp.id = o.company_id
     LEFT JOIN transfer_requests rq ON rq.id = o.request_id
     WHERE o.company_id = $company_id AND o.is_deleted = 0 AND o.stage = 'arrived'
     ORDER BY o.arrival_datetime");
/* ◆ ولا يبتلع `if ($r)` رسوبَ الاستعلامِ صامتًا — الفشلُ يُعلَن برمزٍ (CS-12) */
if ($r === false) { $msg = 'TRS-500 · تعذّرت قراءةُ أوامرِ الإقفال — ' . mysqli_error($conn); }
else { while ($x = mysqli_fetch_assoc($r)) { $rows[] = $x; } }

$page_title = 'إقفال الأمر وتحميل التكلفة';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01: أنماطُ شاشةِ إقفالِ الأمرِ وتحميلِ التكلفةِ أصنافًا */
.trs-cc-row{display:flex;gap:6px}
.trs-cc-cost{max-width:130px}
</style>
<div class="main" dir="rtl">
  <?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ بدلَ الرأسِ اليدويّ —
   شريطُ أفعالٍ واحدٌ وسطرُ سياقٍ ومنفذُ بلاغٍ من مصدرٍ واحد. */
$header_icon = 'fa fa-lock';
$header_title_html = htmlspecialchars('إقفالُ الأمر وتحميلُ التكلفة', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا أوامرَ بانتظارِ الإقفالِ وتحميلِ التكلفة', 'وثّقِ التسليمَ في شاشةِ «الوصولُ والتسليم» ليصيرَ الأمرُ قابلًا للإقفال');
?>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <table class="table table-striped" data-no-dt>
    <!-- INJ-0317 · كلُّ رأسٍ هنا له خليةٌ من الخادمِ في كلِّ صفّ. والرؤوسُ الثمانيةُ
         التي كانت بلا سجلٍّ في القاعدةِ رُفعت ولم تُملأ بفراغ:
         اعتمده مدير النقل · اعتمدته المالية · رقم القيد · مرجع التفويض ·
         تاريخ الاعتماد · معكوس بـ · عكس عن · درجة الأثر. -->
    <thead><tr><th>أمر الترحيل</th><th>المشروعُ المحمَّل</th><th>المقدَّرة $</th><th>الفعلية $</th><th>تاريخ الإقفال</th>
              <th class="ems-fn-th" data-fn="1">رقم المحضر</th>
              <th class="ems-fn-th" data-fn="1">بند التكلفة</th>
              <th class="ems-fn-th" data-fn="1">الوصف</th>
              <th class="ems-fn-th" data-fn="1">المبلغ</th>
              <th class="ems-fn-th" data-fn="1">المستند المؤيد</th>
              <th class="ems-fn-th" data-fn="1">المتحمل</th>
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              <th class="ems-gov-th" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
              <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
    <tbody>
    <?php if (empty($rows)): ?><tr><td colspan="20" class="text-center text-muted">لا أوامرَ بانتظار الإقفال</td></tr><?php endif; ?>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td><?= htmlspecialchars($o['order_no'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars($o['project_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= number_format(floatval($o['estimated_cost_usd']), 2) ?></td>
        <td>
          <?php $rid = intval($o['id']); if (!$o['doc_ref']): ?>
            <!-- FIXC-0048: لا نموذجَ إقفالٍ أصلًا قبلَ تخزينِ مستندِ التسليم —
                 والحارسُ في الخدمةِ يرفضه ولو أُرسل يدويًّا (لا اتكالَ على العرض). -->
            <span class="text-muted">لا مستندَ تسليمٍ مخزَّن —
              <a href="transfer_arrival.php">وثّقِ التسليمَ أولًا</a></span>
        </td>
        <td>—</td>
          <?php else: ?>
          <form method="post" class="trs-cc-row">
        <?= csrf_field() ?>
            <input type="hidden" name="close_id" value="<?= $rid ?>">
            <label class="visually-hidden" for="cls_cost_<?= $rid ?>">التكلفةُ الفعلية</label>
            <input aria-label="التكلفةُ الفعليةُ بالدولار" id="cls_cost_<?= $rid ?>" type="number" step="0.01" min="0.01" name="actual_cost" class="form-control form-control-sm trs-cc-cost"
                   value="<?= htmlspecialchars($o['actual_cost_usd'] ?: $o['estimated_cost_usd'], ENT_QUOTES, 'UTF-8') ?>" required>
            <small class="text-muted">سند: <?= htmlspecialchars($o['doc_ref'], ENT_QUOTES, 'UTF-8') ?></small>
        </td>
        <td><button class="action-btn" type="submit"><i class="fa fa-lock"></i> أقفل وحمّل</button></form></td>
          <?php endif; ?>
        <?php
        /* INJ-0317 · الخمسةَ عشرَ عمودًا الباقيةُ من مصادرِها المخزَّنة — لا حشوَ
           من المتصفّح. و«—» تعني «لا قيمةَ لهذا الصفّ» لا «لا مصدرَ للعمود». */
        $cell = function ($v) {
            $v = trim((string) $v);
            echo '<td' . ($v === '' ? ' class="ems-gov-empty"' : '') . '>'
               . htmlspecialchars($v === '' ? '—' : $v, ENT_QUOTES, 'UTF-8') . '</td>';
        };
        $cell($o['doc_ref']);                                            // رقم المحضر
        $cell($o['cost_types']);                                         // بند التكلفة
        $cell($o['notes']);                                              // الوصف
        $cell($o['lines_usd'] !== null ? number_format((float) $o['lines_usd'], 2) : '');  // المبلغ
        $cell((int) $o['att_n'] > 0 ? ((int) $o['att_n'] . ' مرفقًا') : '');               // المستند المؤيد
        $cell($o['cost_bearer']);                                        // المتحمل
        $cell($o['entity_name']);                                        // الكيان
        $cell($o['created_at']);                                         // تاريخ الإنشاء
        $cell($o['request_code'] !== null ? ('طلبُ ترحيلٍ ' . $o['request_code']) : '');   // المرجع الأب
        $cell($o['creator_name']);                                       // المُنشئ
        $cell($o['stage']);                                              // الحالة
        $cell($o['sync_uuid']);                                          // مفتاح منع التكرار
        $cell($o['analytic_cost_center']);                               // مركز التكلفة
        $cell($o['fx_rate'] !== null ? ('سعرُ بندِ التكلفة ' . rtrim(rtrim((string) $o['fx_rate'], '0'), '.')) : ''); // سعر الصرف ومصدره
        $cell($o['tariff_currency']);                                    // العملة
        ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
