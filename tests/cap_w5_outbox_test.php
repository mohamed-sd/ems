<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0005 · الموجة ⑤ — الصادرُ والذريةُ والأحداث (CAP-26→30 · §14)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/cap_w5_outbox_test.php
 *
 * ما يُثبته بأسماء الحالات:
 *   C27 فشلُ إحدى الكتابات الخمس → تُلغى المعاملةُ كلُّها — صفرُ وحدةٍ وصفرُ
 *       استهلاكٍ وصفرُ صفٍّ في الصادر.
 *   C28 فشلُ النشر بعد COMMIT ثم إعادةُ المحاولة → ينجح النشرُ ولا تُستهلك
 *       الحصةُ ثانيةً — والإعادةُ تصاعديةٌ بساعة القاعدة.
 *   C24 الأسطرُ تُكتب مرةً واحدةً بمفتاحها.
 *   C25 إعادةُ إرسال الوحدة نفسِها → 409 بمرجع السطر القائم — عبر الطبقات
 *       (دفترٌ · صادرٌ · جذرٌ) (CAP-30).
 *   + قاموسُ الأحداث الستة وبوابةُ الأثر المالي (§14-◆ · CAP-29).
 *
 * البذرُ معزول: سجلٌّ صوري 99942045 ومفاتيح CAPW5T — يُكنس قبل وبعد.
 * ═══════════════════════════════════════════════════════════════════════════
  * ⇐ شواهدُ أحكامٍ: FIXA-0005-ب
 * (رُبطت بمراجعةٍ خصمٍ 2026-08-12 — الحجّةُ وسببُ قبولِها في docs/fix_progress/BINDINGS.md)
*/

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/CapacityAtomicCommit.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Capacity\CapacityAtomicCommit as ATM;
use App\Services\Capacity\CapacityOutbox as OBX;
use App\Services\Capacity\CapacityEvents as EVT;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999902; $REC = 99942045;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn, $REC) {
    $conn->query("DELETE FROM capacity_outbox WHERE idempotency_key LIKE 'CAPW5T%'");
    $conn->query("DELETE FROM capacity_consumption_ledger WHERE unit_record_id = {$REC}");
    $conn->query("UPDATE unit_entries SET note = NULL WHERE note LIKE 'CAPW5T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0005 · الموجة ⑤ — الصادرُ والذريةُ والأحداث ══\n");

// ═══ ① CAP-29 · القاموس والبوابة ═══
head('① أحداثُ المجال الستة وبوابةُ الأثر المالي (§14-◆)');
check(count(EVT::ALL) === 6, 'القاموسُ ستةُ أحداثٍ بمفاتيح عقد الناشر');
$r = EVT::financialEffectAllowed(EVT::CAPACITY_CONSUMED);
check($r['allowed'], '① CapacityConsumed ماليُّ الأثر بطبيعته');
$r = EVT::financialEffectAllowed(EVT::STANDBY_ACTIVATED, array('standby_compensation_type' => null));
check(!$r['allowed'], '⑤ StandbyActivated بلا نصِّ مقابلٍ → لا حدثَ مالي: ' . $r['reason']);
$r = EVT::financialEffectAllowed(EVT::STANDBY_ACTIVATED, array('standby_compensation_type' => 'readiness_allowance'));
check($r['allowed'], '⑤ وبنصِّ بدلِ جاهزيةٍ → يُسمح');
$r = EVT::financialEffectAllowed(EVT::GAP_OPENED, array('shortfall_rule' => 'invoice_actual'));
check(!$r['allowed'], '⑥ CoverageGapOpened نطاقيٌّ — الجزاءُ من قاعدة العقد لا الحدث');
$r = EVT::financialEffectAllowed(EVT::GAP_OPENED, array('shortfall_rule' => 'penalty'));
check($r['allowed'], '⑥ وبقاعدة جزاءٍ صريحةٍ → يُسمح');

// ═══ ② CAP-28 · C27 ═══
head('② الذريةُ — C27: فشلُ واحدةٍ يُلغي الخمس');
$ENTRY = intval($conn->query("SELECT id FROM unit_entries WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$mkLine = function ($ver, $measure = 'hour') use ($REC) {
    return array('unit_record_id' => $REC, 'unit_record_version' => $ver,
        'effect_type' => 'client_obligation', 'effect_target_type' => 'client',
        'effect_target_ref' => 'contract:7', 'measure_code' => $measure,
        'qty' => 10, 'period' => '2042-06');
};
$mkEvent = function ($suffix) use ($REC) {
    return array('event_key' => EVT::CAPACITY_CONSUMED, 'entity_type' => 'unit_entry',
        'entity_id' => $REC, 'quantity' => 10, 'unit' => 'hour',
        'payload' => array('test' => 'CAPW5T'), 'idempotency_key' => 'CAPW5T:' . $suffix,
        'created_by' => 1);
};
// الكتابةُ ① تلمس سجلًّا حيًّا (note) — لنرى التراجعَ يمحو أثرَها
$r = ATM::run($gate, array(
    'fix_unit' => function ($g) use ($ENTRY) {
        $g->update('unit_entries', array('note' => 'CAPW5T fixation'), array('id' => $ENTRY));
    },
    'lines'  => array($mkLine(1), $mkLine(2, 'cbm')), // الثاني بمقياسٍ فاسد → فشل ③
    'events' => array($mkEvent('c27')),
), $ACTOR);
check(!$r['ok'], 'المعاملةُ رفضت: ' . $r['reason']);
$led = intval($conn->query("SELECT COUNT(*) n FROM capacity_consumption_ledger WHERE unit_record_id = {$REC}")->fetch_assoc()['n']);
$obx = intval($conn->query("SELECT COUNT(*) n FROM capacity_outbox WHERE idempotency_key LIKE 'CAPW5T%'")->fetch_assoc()['n']);
$fix = intval($conn->query("SELECT COUNT(*) n FROM unit_entries WHERE id = {$ENTRY} AND note = 'CAPW5T fixation'")->fetch_assoc()['n']);
check($led === 0 && $obx === 0 && $fix === 0,
      'C27: صفرُ وحدةٍ (تثبيتُها ارتدّ) وصفرُ استهلاكٍ وصفرُ صفٍّ في الصادر');

// ═══ ③ الكتاباتُ الخمس تنجح معًا ═══
head('③ الكتاباتُ الخمسُ الذرية تكتمل');
$r = ATM::run($gate, array(
    'fix_unit' => function ($g) use ($ENTRY) {
        $g->update('unit_entries', array('note' => 'CAPW5T v1'), array('id' => $ENTRY));
    },
    'lines'  => array($mkLine(1)),
    'events' => array($mkEvent('ok1')),
), $ACTOR);
check($r['ok'] && count($r['led_ids']) === 1 && count($r['obx_ids']) === 1,
      'اكتملت: ' . $r['reason']);
$OBX1 = $r['obx_ids'][0];
$st = $conn->query("SELECT state FROM capacity_outbox WHERE obx_id = {$OBX1}")->fetch_assoc();
check($st['state'] === 'pending', 'صفُّ الصادر كُتب داخل المعاملة **بلا نشر** — pending (§14-⑤)');

// ═══ ④ C24/C25 · CAP-30 ═══
head('④ منعُ التكرار عبر الطبقات — C24 · C25');
$r2 = ATM::run($gate, array(
    'lines'  => array($mkLine(1)),
    'events' => array($mkEvent('ok1-retry')),
), $ACTOR);
check(!$r2['ok'] && intval($r2['code']) === 409 && intval($r2['existing_led_id']) === intval($r['led_ids'][0]),
      'C25: إعادةُ إرسال الوحدة نفسِها → 409 بمرجع السطر القائم led#' . $r2['existing_led_id']);
$led = intval($conn->query("SELECT COUNT(*) n FROM capacity_consumption_ledger WHERE unit_record_id = {$REC}")->fetch_assoc()['n']);
$obx = intval($conn->query("SELECT COUNT(*) n FROM capacity_outbox WHERE idempotency_key LIKE 'CAPW5T%'")->fetch_assoc()['n']);
check($led === 1 && $obx === 1, 'C24: سطرٌ واحدٌ وصفُّ صادرٍ واحدٌ — صفرُ خصمٍ ثانٍ في كل الطبقات');

// ═══ ⑤ CAP-27 · C28 ═══
head('⑤ العاملُ والإعادةُ التصاعدية — C28');
// فشلُ النشر الأول: نُفسد created_by (العقدُ §9 يرفض صفرًا) — فشلٌ بياناتيٌّ حقيقي
$conn->query("UPDATE capacity_outbox SET created_by = 0 WHERE obx_id = {$OBX1}");
$r = OBX::drain($conn, $gate, 50, true);
$row = $conn->query("SELECT state, attempts, next_attempt_at, last_error FROM capacity_outbox WHERE obx_id = {$OBX1}")->fetch_assoc();
check($r['retried'] === 1 && $row['state'] === 'pending' && intval($row['attempts']) === 1
   && $row['next_attempt_at'] !== null,
      'C28-أ: فشلُ النشر بعد COMMIT → جدولةٌ تصاعدية (محاولة 1 · موعدٌ لاحقٌ بساعة القاعدة): ' . $row['last_error']);
$ledBefore = intval($conn->query("SELECT COUNT(*) n FROM capacity_consumption_ledger WHERE unit_record_id = {$REC}")->fetch_assoc()['n']);
// الإصلاحُ ثم الإعادة — والموعدُ يُستحق الآن
$conn->query("UPDATE capacity_outbox SET created_by = {$ACTOR}, next_attempt_at = NOW() WHERE obx_id = {$OBX1}");
$r = OBX::drain($conn, $gate, 50, true);
$row = $conn->query("SELECT state, published_event_id FROM capacity_outbox WHERE obx_id = {$OBX1}")->fetch_assoc();
check($r['published'] === 1 && $row['state'] === 'published' && intval($row['published_event_id']) > 0,
      'C28-ب: إعادةُ المحاولة تنجح — الحدثُ في الجذر #' . $row['published_event_id']);
$ledAfter = intval($conn->query("SELECT COUNT(*) n FROM capacity_consumption_ledger WHERE unit_record_id = {$REC}")->fetch_assoc()['n']);
check($ledAfter === $ledBefore, 'C28-ج: **ولا تُستهلك الحصةُ ثانيةً** — الدفترُ كما كان (' . $ledAfter . ')');
$evCount = intval($conn->query("SELECT COUNT(*) n FROM ems_business_events
    WHERE idempotency_key = 'CAPW5T:ok1'")->fetch_assoc()['n']);
check($evCount === 1, 'حدثٌ واحدٌ في الجذر بمفتاحه — العطالةُ عبر الطبقات (CAP-30)');
// نشرٌ ثالثٌ قسرًا: المنشورُ لا يُلتقط أصلًا (state=published خارج الالتقاط)
$r = OBX::drain($conn, $gate, 50, true);
check($r['drained'] === 0, 'المنشورُ لا يُعاد التقاطُه — العاملُ يقرأ المعلَّقَ المستحقَّ وحدَه');
// حدثٌ خارج القاموس يُرفض في الصادر
$bad = OBX::enqueue($gate, array('event_key' => 'capacity.unknown_thing', 'entity_type' => 'x',
    'entity_id' => 1, 'payload' => array(), 'idempotency_key' => 'CAPW5T:bad'));
check(!$bad['ok'] && intval($bad['code']) === 422, 'حدثٌ خارج قاموس الستة → 422 (CAP-29)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
