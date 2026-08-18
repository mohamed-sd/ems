<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 · ناقلُ الأحداث — إثباتٌ إيجابيٌّ وسلبيٌّ مُشغَّلان
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/eng01_bus_test.php
 *
 * ما يُثبته:
 *   B1 ✚ النشرُ داخلَ المعاملة: الواقعةُ وصفُّ الصادرِ وصفوفُ التسليمِ معًا.
 *   B2 ✖ انهيارُ المعاملةِ يُلغي الثلاثةَ معًا — لا حدثَ لواقعةٍ لم تقع.
 *   B3 ✖ نشرُ نوعٍ لا مشتركَ له مرفوضٌ قبلَ الكتابة (CK-11 · chk_consumers).
 *   B4 ✚ التسليمُ ينجح ويكتب مرجعًا — والقيدُ chk_result يمنع نجاحًا بلا مرجع.
 *   B5 ✖ نجاحٌ بلا مرجعٍ مرفوضٌ في القاعدةِ نفسِها.
 *   B6 ✚ خمسُ إعاداتٍ لتسليمٍ واحدٍ تُنتج أثرًا واحدًا (F-14 · uq_idem).
 *   B7 ✚ التباعدُ المتزايد 1·4·16·64·256 ثم dlq (F-13 — من القاعدةِ لا PHP).
 *   B8 ✖ فشلٌ بلا رمزٍ مرفوض (chk_fail).
 *   B9 ✚ ثلاثةُ عمّالٍ على تسليمٍ واحدٍ: واحدٌ يلتقط والباقونَ لا أثرَ لهم.
 *
 * البذرُ معزول: وسمُ ENG01BUS يُكنس قبل وبعد — «اكنس بالعائلة».
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
require_once dirname(__DIR__) . '/app/Services/Bus/EventOutboxFanout.php';
require_once dirname(__DIR__) . '/app/Services/Bus/EventDeliveryWorker.php';

use App\Core\EventPublisher;
use App\Services\Bus\EventOutboxFanout;
use App\Services\Bus\EventDeliveryWorker;

while (ob_get_level() > 0) { ob_end_clean(); }
$db = $conn;

const TAG   = 'ENG01BUS';
const CO    = 4;
const EVKEY = 'probe.engone.bus';
const EVNONE= 'probe.engone.nocons';

$pass = 0; $fail = 0;
function ok($id, $msg)   { global $pass; $pass++; echo "  ✔ $id  $msg\n"; }
function no($id, $msg)   { global $fail; $fail++; echo "  ✘ $id  $msg\n"; }
function check($id, $cond, $msg) { $cond ? ok($id, $msg) : no($id, $msg); }

// ───────────────────────────── الكنسُ بالعائلة ─────────────────────────────
function sweep(\mysqli $db) {
    $db->query("DELETE d FROM `ems_event_deliveries` d
                JOIN `ems_business_events` e ON e.id = d.outbox_id
                WHERE e.`event_key` IN ('" . EVKEY . "','" . EVNONE . "')");
    $db->query("DELETE FROM `ems_event_deliveries` WHERE `fail_text` LIKE '%" . TAG . "%'");
    $db->query("DELETE FROM `ems_business_events` WHERE `event_key` IN ('" . EVKEY . "','" . EVNONE . "')");
    $db->query("DELETE FROM `fin_notifications` WHERE `title` LIKE '%" . TAG . "%'");
    $db->query("DELETE FROM `event_consumers` WHERE `event_name` IN ('" . EVKEY . "','" . EVNONE . "')");
}
sweep($db);

echo "\n═══════════════════════════════════════════════════════════════\n";
echo " ENG-01 · ناقلُ الأحداث — الإثباتُ المُشغَّل\n";
echo "═══════════════════════════════════════════════════════════════\n";

// ═══════════════ مستهلكٌ اختباريٌّ يتحكَّم فيه الاختبار ═══════════════
class Eng01ProbeConsumer
{
    /** @var string 'ok'|'throw'|'empty' */
    public static $mode = 'ok';
    public static $calls = 0;
    public function handle(array $event, \mysqli $conn)
    {
        self::$calls++;
        if (self::$mode === 'throw') { throw new \RuntimeException(TAG . ' فشلٌ مقصود'); }
        if (self::$mode === 'empty') { return ''; }
        // أثرٌ حقيقيٌّ مرئيّ: إنذارٌ مسجَّل
        $t = TAG . ' أثرُ التسليم على الواقعة #' . $event['id'];
        $st = $conn->prepare("INSERT INTO `fin_notifications` (`company_id`,`target_level`,`title`,`link`)
                              VALUES (?, 'all', ?, 'Governance/bus_board.php')");
        $co = (int) $event['company_id'];
        $st->bind_param('is', $co, $t);
        $st->execute();
        $id = (int) $conn->insert_id;
        $st->close();
        return 'fin_notifications#' . $id;
    }
}

// اشتراكٌ واحدٌ للنوعِ المفحوص
$db->query("INSERT INTO `event_consumers`
   (`event_name`,`consumer_class`,`consumer_method`,`produces`,`active`,`consumer_key`,`max_attempts`,`timeout_seconds`)
   VALUES ('" . EVKEY . "','Eng01ProbeConsumer','handle','write',1,'eng01_probe',5,60)");

$publish = function (array $extra = array()) use ($db) {
    return EventPublisher::publishFact($db, array_merge(array(
        'company_id'    => CO,
        'event_key'     => EVKEY,
        'category'      => 'analytics',
        'source_module' => 'system',
        'entity_type'   => 'probe',
        'entity_id'     => random_int(900000, 999999),
        'occurred_at'   => date('Y-m-d H:i:s'),
        'payload'       => array('tag' => TAG),
        'created_by'    => 1,
    ), $extra));
};

// ═════════ B1 ✚ النشرُ يكتب الواقعةَ والصادرَ والتسليمَ داخلَ معاملةٍ واحدة ═════════
echo "\n▐ B1 ✚ النشرُ داخلَ المعاملةِ نفسِها\n";
$db->begin_transaction();
$r1 = $publish();
$rootId = (int) $r1['id'];
$inTx = (int) $db->query("SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `outbox_id`={$rootId}")->fetch_row()[0];
$decl  = (int) $db->query("SELECT `consumers_declared` FROM `ems_business_events` WHERE `id`={$rootId}")->fetch_row()[0];
$db->commit();
check('B1-a', $rootId > 0, "الواقعةُ كُتبت — id=$rootId");
check('B1-b', $inTx === 1, "صفُّ تسليمٍ واحدٌ ظهر داخلَ المعاملةِ نفسِها (وجد $inTx)");
check('B1-c', $decl === 1, "consumers_declared=$decl مثبَّتٌ لحظةَ النشر");

// ═════════ B2 ✖ انهيارُ المعاملةِ يُلغي الثلاثةَ معًا ═════════
echo "\n▐ B2 ✖ الارتدادُ يُلغي الواقعةَ وصادرَها وتسليمَها\n";
$db->begin_transaction();
$r2 = $publish();
$rollbackId = (int) $r2['id'];
$db->rollback();
$evLeft = (int) $db->query("SELECT COUNT(*) FROM `ems_business_events` WHERE `id`={$rollbackId}")->fetch_row()[0];
$dvLeft = (int) $db->query("SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `outbox_id`={$rollbackId}")->fetch_row()[0];
check('B2-a', $evLeft === 0, "صفرُ واقعةٍ بعدَ الارتداد (بقي $evLeft)");
check('B2-b', $dvLeft === 0, "صفرُ تسليمٍ بعدَ الارتداد (بقي $dvLeft) — لا حدثَ لواقعةٍ لم تقع");

// ═════════ B3 ✖ نشرُ نوعٍ لا مشتركَ له مرفوضٌ قبلَ الكتابة ═════════
echo "\n▐ B3 ✖ «لا يُنشر حدثٌ لا اشتراكَ له» (CK-11)\n";
$before = (int) $db->query("SELECT COUNT(*) FROM `ems_business_events` WHERE `event_key`='" . EVNONE . "'")->fetch_row()[0];
$refused = false; $why = '';
try {
    $publish(array('event_key' => EVNONE));
} catch (\Throwable $t) { $refused = true; $why = $t->getMessage(); }
$after = (int) $db->query("SELECT COUNT(*) FROM `ems_business_events` WHERE `event_key`='" . EVNONE . "'")->fetch_row()[0];
check('B3-a', $refused, 'رُفض النشرُ — ' . mb_substr($why, 0, 90));
check('B3-b', $after === $before, "ولم يُكتب صفٌّ ($before → $after)");

// ═════════ B4 ✚ التسليمُ ينجح بمرجعٍ ═════════
echo "\n▐ B4 ✚ التسليمُ ينجح ويكتب مرجعَ أثرِه\n";
Eng01ProbeConsumer::$mode = 'ok';
Eng01ProbeConsumer::$calls = 0;
$w = new EventDeliveryWorker($db, 'W-' . TAG . '-1');
$dId = (int) $db->query("SELECT `id` FROM `ems_event_deliveries` WHERE `outbox_id`={$rootId}")->fetch_row()[0];
$res = $w->deliverOne($dId);
$row = $db->query("SELECT `state`,`result_ref`,`attempt_no` FROM `ems_event_deliveries` WHERE `id`={$dId}")->fetch_assoc();
check('B4-a', $res === 'processed', "الحكم: $res");
check('B4-b', $row['state'] === 'processed', "الحالة: {$row['state']}");
check('B4-c', !empty($row['result_ref']), "المرجع: {$row['result_ref']}");
$okCnt = (int) $db->query("SELECT `delivered_ok` FROM `ems_business_events` WHERE `id`={$rootId}")->fetch_row()[0];
check('B4-d', $okCnt === 1, "عدّادُ الصادرِ delivered_ok=$okCnt");

// ═════════ B5 ✖ نجاحٌ بلا مرجعٍ مرفوضٌ في القاعدة ═════════
echo "\n▐ B5 ✖ chk_result — نجاحٌ بلا مرجعٍ مرفوضٌ بنيويًّا\n";
$db->query("UPDATE `ems_event_deliveries` SET `result_ref`=NULL WHERE `id`={$dId}");
$errno = $db->errno; $err = $db->error;
$still = $db->query("SELECT `result_ref` FROM `ems_event_deliveries` WHERE `id`={$dId}")->fetch_row()[0];
check('B5-a', $errno !== 0, 'رفضت القاعدةُ التفريغَ — ' . mb_substr($err, 0, 70));
check('B5-b', $still !== null, 'والمرجعُ ما يزال قائمًا');

// ═════════ B6 ✚ خمسُ إعاداتٍ لتسليمٍ واحدٍ = أثرٌ واحد ═════════
echo "\n▐ B6 ✚ خمسُ إعاداتٍ تُنتج أثرًا واحدًا (F-14 · uq_idem)\n";
$db->begin_transaction();
$r6 = $publish();
$ob6 = (int) $r6['id'];
$db->commit();
$before6 = (int) $db->query("SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `outbox_id`={$ob6}")->fetch_row()[0];
// إعادةُ فتحِ المروحةِ خمسَ مراتٍ — كما لو أُعيد النشرُ خمسًا
for ($i = 0; $i < 5; $i++) { EventOutboxFanout::open($db, $ob6, EVKEY, CO); }
$after6 = (int) $db->query("SELECT COUNT(*) FROM `ems_event_deliveries` WHERE `outbox_id`={$ob6}")->fetch_row()[0];
check('B6-a', $before6 === 1 && $after6 === 1, "صفوفُ التسليم: $before6 قبلَ خمسِ إعاداتٍ و$after6 بعدَها");

// خمسُ نداءاتِ تسليمٍ متتاليةٍ على الصفِّ نفسِه — أثرٌ واحدٌ لا خمسة
Eng01ProbeConsumer::$calls = 0;
$d6 = (int) $db->query("SELECT `id` FROM `ems_event_deliveries` WHERE `outbox_id`={$ob6}")->fetch_row()[0];
$notifBefore = (int) $db->query("SELECT COUNT(*) FROM `fin_notifications` WHERE `title` LIKE '%" . TAG . "%'")->fetch_row()[0];
$outcomes = array();
for ($i = 0; $i < 5; $i++) { $outcomes[] = var_export($w->deliverOne($d6), true); }
$notifAfter = (int) $db->query("SELECT COUNT(*) FROM `fin_notifications` WHERE `title` LIKE '%" . TAG . "%'")->fetch_row()[0];
$effects = $notifAfter - $notifBefore;
check('B6-b', $effects === 1, "خمسُ نداءاتٍ ⇐ $effects أثرًا (نداءُ المعالج: " . Eng01ProbeConsumer::$calls . ")");
echo "        نتائجُ النداءاتِ الخمسة: " . implode(' · ', $outcomes) . "\n";

// ═════════ B7 ✚ التباعدُ المتزايد 1·4·16·64·256 ثم dlq ═════════
echo "\n▐ B7 ✚ التباعدُ المتزايد ثم صندوقُ الموتى (F-13)\n";
$db->begin_transaction();
$r7 = $publish();
$ob7 = (int) $r7['id'];
$db->commit();
$d7 = (int) $db->query("SELECT `id` FROM `ems_event_deliveries` WHERE `outbox_id`={$ob7}")->fetch_row()[0];
Eng01ProbeConsumer::$mode = 'throw';
$gaps = array(); $states = array();
for ($i = 0; $i < 7; $i++) {
    // إتاحةُ الالتقاطِ فورًا لقياسِ الفجوةِ المحسوبةِ لا لانتظارِها
    $db->query("UPDATE `ems_event_deliveries` SET `next_attempt_at`=NOW(3) WHERE `id`={$d7}");
    $out = $w->deliverOne($d7);
    // القياسُ بالميكروثانيةِ لا بالثانية: NOW(3) في القراءةِ متأخرٌ كسرًا عن
    // NOW(3) في الكتابة، وTIMESTAMPDIFF(SECOND) يبتر الكسرَ فيقرأ 1 صفرًا.
    $row = $db->query("SELECT `state`,`attempt_no`,
                              TIMESTAMPDIFF(MICROSECOND, NOW(3), `next_attempt_at`)/1000000 AS gap,
                              `fail_code`
                         FROM `ems_event_deliveries` WHERE `id`={$d7}")->fetch_assoc();
    $states[] = $row['state'];
    if ($row['state'] === 'failed') { $gaps[] = (int) ceil((float) $row['gap']); }
    if ($row['state'] === 'dlq') { break; }
}
$expected = array(1, 4, 16, 64, 256);
$gotSeq = array_slice($gaps, 0, 5);
check('B7-a', $gotSeq === $expected,
    'المتتالية: [' . implode('·', $gotSeq) . '] والمعلَنة [' . implode('·', $expected) . ']');
$finalState = $db->query("SELECT `state`,`fail_code` FROM `ems_event_deliveries` WHERE `id`={$d7}")->fetch_assoc();
check('B7-b', $finalState['state'] === 'dlq', "الحالةُ النهائية: {$finalState['state']} برمزٍ {$finalState['fail_code']}");
$inDlq = (int) $db->query("SELECT `in_dlq` FROM `ems_business_events` WHERE `id`={$ob7}")->fetch_row()[0];
check('B7-c', $inDlq === 1, "صفُّ الصادرِ موسومٌ in_dlq=$inDlq");

// ═════════ B8 ✖ فشلٌ بلا رمزٍ مرفوض ═════════
echo "\n▐ B8 ✖ chk_fail — فشلٌ بلا رمزٍ مرفوضٌ بنيويًّا\n";
$db->query("UPDATE `ems_event_deliveries` SET `fail_code`=NULL WHERE `id`={$d7}");
$e8 = $db->errno; $m8 = $db->error;
$fc = $db->query("SELECT `fail_code` FROM `ems_event_deliveries` WHERE `id`={$d7}")->fetch_row()[0];
check('B8-a', $e8 !== 0, 'رفضت القاعدةُ — ' . mb_substr($m8, 0, 70));
check('B8-b', $fc !== null, "والرمزُ باقٍ: $fc");

// ═════════ B9 ✚ ثلاثةُ عمّالٍ على تسليمٍ واحد ═════════
echo "\n▐ B9 ✚ ثلاثةُ عمّالٍ معًا — أثرٌ واحدٌ لا ثلاثة\n";
Eng01ProbeConsumer::$mode = 'ok';
$db->begin_transaction();
$r9 = $publish();
$ob9 = (int) $r9['id'];
$db->commit();
$d9 = (int) $db->query("SELECT `id` FROM `ems_event_deliveries` WHERE `outbox_id`={$ob9}")->fetch_row()[0];
$nBefore = (int) $db->query("SELECT COUNT(*) FROM `fin_notifications` WHERE `title` LIKE '%" . TAG . "%'")->fetch_row()[0];
$w1 = new EventDeliveryWorker($db, 'W-A'); $w2 = new EventDeliveryWorker($db, 'W-B'); $w3 = new EventDeliveryWorker($db, 'W-C');
$rs = array($w1->deliverOne($d9), $w2->deliverOne($d9), $w3->deliverOne($d9));
$nAfter = (int) $db->query("SELECT COUNT(*) FROM `fin_notifications` WHERE `title` LIKE '%" . TAG . "%'")->fetch_row()[0];
$won = count(array_filter($rs, function ($x) { return $x === 'processed'; }));
$lost = count(array_filter($rs, function ($x) { return $x === null; }));
check('B9-a', $won === 1, "عاملٌ واحدٌ التقط ($won) والباقونَ لم يلتقطوا ($lost)");
check('B9-b', ($nAfter - $nBefore) === 1, 'الأثر: ' . ($nAfter - $nBefore) . ' لا ثلاثة');

// ───────────────────────────── النتيجة ─────────────────────────────
echo "\n═══════════════════════════════════════════════════════════════\n";
printf(" النتيجة: %d ناجحًا · %d ساقطًا\n", $pass, $fail);
echo "═══════════════════════════════════════════════════════════════\n";

sweep($db);
echo " كُنس البذرُ (" . TAG . ")\n\n";
exit($fail === 0 ? 0 : 1);
