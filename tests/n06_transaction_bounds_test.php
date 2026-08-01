<?php
/**
 * tests/n06_transaction_bounds_test.php — N-06 حدود المعاملة (البوابة ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * يختبر الثوابت لا الأعداد (PLAN-05 §11):
 *   ① الذرية: نشرٌ داخل معاملةٍ تُدحرج → صفرُ حدثٍ باقٍ (حدث+آثار كلٌّ أو لا شيء).
 *   ② التصاعد والعزل: مستهلكٌ فاشلٌ دومًا → محاولاتٌ بموعدٍ تصاعديٍّ ثم
 *      Dead-Letter **بإنذارٍ مسجَّل** والمؤشر يتقدم خلفه (لا تجميد).
 *   ③ processed_operations: ادعاءٌ أولُ يمرّ والثاني يُرفض — بمفتاح
 *      (المستهلك × المستند × الأثر) · وإعادةُ إرسالٍ متعمَّدة = صفرُ تكرار.
 *   ④ التعويض: عكسُ حدثٍ بمرجعه — الأصل reversed والمعوِّض يشير إليه،
 *      وتكرارُ العكس يعيد المرجعَ القائم (عاطل) · والأصل لا يُحذف.
 * التشغيل: php tests/n06_transaction_bounds_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/app/Core/EventPublisher.php';
require_once $ROOT . '/app/Core/EventDispatcher.php';
require_once $ROOT . '/app/Core/ProcessedOperations.php';
require_once $ROOT . '/app/Services/CompensationService.php';

use App\Core\EventPublisher;
use App\Core\EventDispatcher;
use App\Core\ProcessedOperations;
use App\Services\CompensationService;

EventPublisher::$rootModeOverride = 'publish';

$fails = 0;
$T = 'n06test';
function ok($cond, $label) {
    global $fails;
    echo ($cond ? '  ✔ ' : '  ✘ ') . $label . PHP_EOL;
    if (!$cond) { $fails++; }
}
function ev(array $extra = array()) {
    static $n = 0; $n++;
    return array_merge(array(
        'event_key' => 'system.probe.recorded',
        'category' => 'operational',
        'source_module' => 'system',
        'company_id' => 4,
        'entity_type' => 'n06_probe',
        'entity_id' => 900000 + $n,
        'occurred_at' => gmdate('Y-m-d H:i:s'),
        'created_by' => 1,
        'amount' => 100.00,
        'quantity' => 5,
        'unit' => 'hour',
        'payload' => array('probe' => 'n06', 'n' => $n),
    ), $extra);
}

// ── كنس آثار تشغيلٍ سابق (المسبار حصرًا — لا مساس ببيانات حية) ──
$conn->query("DELETE FROM fin_financial_events WHERE entity_type = 'n06_probe'");
$conn->query("DELETE FROM ems_business_events WHERE entity_type = 'n06_probe'");
$conn->query("DELETE FROM processed_operations WHERE consumer LIKE 'n06%'");
$conn->query("DELETE FROM ems_event_consumers WHERE consumer LIKE 'n06%'");
$conn->query("DELETE FROM ems_event_deliveries WHERE consumer LIKE 'n06%'");
$conn->query("DELETE FROM ems_event_dead_letter WHERE consumer LIKE 'n06%'");
$conn->query("DELETE FROM fin_notifications WHERE title LIKE '%إنذار الناقل%' AND company_id = 4");

echo "① الذرية — نشرٌ داخل معاملةٍ تُدحرج:\n";
$conn->begin_transaction();
$r = EventPublisher::publish($conn, ev(array('idempotency_key' => $T . ':atomic:1')));
$conn->rollback();
$q = $conn->query("SELECT COUNT(*) c FROM fin_financial_events WHERE idempotency_key = '{$T}:atomic:1'")->fetch_assoc();
ok(intval($q['c']) === 0, 'الدحرجة أزالت الحدث وإسقاطه معًا — كلٌّ أو لا شيء');
$q = $conn->query("SELECT COUNT(*) c FROM ems_business_events WHERE idempotency_key = '{$T}:atomic:1'")->fetch_assoc();
ok(intval($q['c']) === 0, 'الجذر المحايد دُحرج معه — لا صادرَ يتيمًا');

echo "② التصاعد والعزل بإنذار:\n";
$r1 = EventPublisher::publish($conn, ev(array('idempotency_key' => $T . ':poison:1')));
$eid = intval($r1['id']);
$disp = new EventDispatcher($conn, array('max_attempts' => 5, 'backoff' => false));
$disp->register('n06_fail', function ($event, $c) { throw new RuntimeException('n06 فشل مقصود'); }, $eid - 1);
$dl = 0; $cycles = 0;
for ($i = 0; $i < 10; $i++) {
    $cycles++;
    $st = $disp->runOnce();
    if ($st['n06_fail']['dead_lettered'] > 0) { $dl = 1; break; }
}
ok($dl === 1 && $cycles === 6, "خمس محاولاتٍ فاشلة ثم العزل في الدورة السادسة (الفعلي: {$cycles})");
$q = $conn->query("SELECT COUNT(*) c FROM ems_event_dead_letter WHERE consumer = 'n06_fail' AND event_id = {$eid}")->fetch_assoc();
ok(intval($q['c']) === 1, 'الحدث السام معزولٌ في الرسائل الميتة');
$q = $conn->query("SELECT COUNT(*) c FROM fin_notifications WHERE title LIKE '%إنذار الناقل%' AND title LIKE '%{$eid}%'")->fetch_assoc();
ok(intval($q['c']) === 1, 'العزل بإنذارٍ مسجَّلٍ لا بصمت');
$q = $conn->query("SELECT cursor_event_id FROM ems_event_consumers WHERE consumer = 'n06_fail'")->fetch_assoc();
ok(intval($q['cursor_event_id']) >= $eid, 'المؤشر تقدم خلف المعزول — الطابور لا يتجمد');

// التصاعد الزمني: فشلٌ واحد بالتصاعد مفعَّلًا → next_retry_at في المستقبل
$r2 = EventPublisher::publish($conn, ev(array('idempotency_key' => $T . ':poison:2')));
$eid2 = intval($r2['id']);
$disp2 = new EventDispatcher($conn, array('max_attempts' => 5));
$disp2->register('n06_backoff', function ($event, $c) { throw new RuntimeException('n06 فشل مقصود'); }, $eid2 - 1);
$disp2->runOnce();
$q = $conn->query("SELECT next_retry_at > NOW() AS future, attempts FROM ems_event_deliveries WHERE consumer = 'n06_backoff' AND event_id = {$eid2}")->fetch_assoc();
ok($q && intval($q['future']) === 1 && intval($q['attempts']) === 1, 'الفشل جدول محاولةً تاليةً تصاعديةً في المستقبل');
$st = $disp2->runOnce();
ok($st['n06_backoff']['processed'] === 0 && $st['n06_backoff']['failed'] === 0, 'محاولةٌ غير مستحقةٍ لا تُلتقط قبل موعدها');

echo "③ processed_operations — (المستهلك × المستند × الأثر):\n";
$c1 = ProcessedOperations::claim($conn, 'n06_consumer', 'n06_probe', 77, 'revenue', $eid);
$c2 = ProcessedOperations::claim($conn, 'n06_consumer', 'n06_probe', 77, 'revenue', $eid);
ok($c1 === true && $c2 === false, 'الادعاء الأول يمرّ والثاني يُرفض — صفرُ تكرارٍ عند إعادة التشغيل');
$c3 = ProcessedOperations::claim($conn, 'n06_consumer', 'n06_probe', 77, 'supplier_due', $eid);
ok($c3 === true, 'أثرٌ آخرُ للمستند نفسه ادعاءٌ مستقل — المفتاح ثلاثي لا ثنائي');
ok(ProcessedOperations::isProcessed($conn, 'n06_consumer', 'n06_probe', 77, 'revenue') === true, 'القراءة بلا ادعاءٍ تصدُق');

// إعادة إرسالٍ متعمدة عبر الناشر نفسه (عطالة idempotency_key)
$a = EventPublisher::publish($conn, ev(array('idempotency_key' => $T . ':dup:1', 'entity_id' => 900777)));
$b = EventPublisher::publish($conn, ev(array('idempotency_key' => $T . ':dup:1', 'entity_id' => 900777)));
ok(intval($a['id']) === intval($b['id']) && !empty($b['duplicate']), 'إعادة الإرسال المتعمدة تعيد المرجع القائم — صفرُ حدثٍ ثانٍ');

echo "④ التعويض — العكس بمرجعه:\n";
$orig = EventPublisher::publish($conn, ev(array('idempotency_key' => $T . ':comp:1', 'amount' => 250.00)));
$oid = intval($orig['id']);
$rev = CompensationService::reverseEvent($conn, $oid, 'فشل جزئي مفتعل — اختبار N-06', 1);
$rid = intval($rev['reversal_id']);
$q = $conn->query("SELECT event_status FROM fin_financial_events WHERE id = {$oid}")->fetch_assoc();
$q2 = $conn->query("SELECT reverses_event_id FROM fin_financial_events WHERE id = {$rid}")->fetch_assoc();
ok($q['event_status'] === 'reversed' && intval($q2['reverses_event_id']) === $oid, 'الأصل معلَّمٌ reversed والمعوِّض يشير إليه بمرجعه — لا يتيم');
$q = $conn->query("SELECT amount FROM fin_financial_events WHERE id = {$rid}")->fetch_assoc();
ok(abs(floatval($q['amount']) + 250.00) < 0.005, 'المعوِّض بالمبلغ السالب نفسه (ADR-18)');
$rev2 = CompensationService::reverseEvent($conn, $oid, 'تكرار العكس', 1);
ok(intval($rev2['reversal_id']) === $rid && $rev2['duplicate'] === true, 'تكرار العكس عاطلٌ — يعيد المرجع القائم');
$q = $conn->query("SELECT COUNT(*) c FROM fin_financial_events WHERE id = {$oid}")->fetch_assoc();
ok(intval($q['c']) === 1, 'الأصل باقٍ — صفرُ سطرٍ محذوف');

// ── كنس ──
$conn->query("DELETE FROM fin_financial_events WHERE entity_type = 'n06_probe'");
$conn->query("DELETE FROM ems_business_events WHERE entity_type = 'n06_probe'");
$conn->query("DELETE FROM processed_operations WHERE consumer LIKE 'n06%'");
$conn->query("DELETE FROM ems_event_consumers WHERE consumer LIKE 'n06%'");
$conn->query("DELETE FROM ems_event_deliveries WHERE consumer LIKE 'n06%'");
$conn->query("DELETE FROM ems_event_dead_letter WHERE consumer LIKE 'n06%'");
$conn->query("DELETE FROM fin_notifications WHERE title LIKE '%إنذار الناقل%' AND company_id = 4");

echo PHP_EOL . ($fails === 0 ? "N-06: كل الفحوص خضراء ✔" : "N-06: {$fails} فحص أحمر ✘") . PHP_EOL;
exit($fails === 0 ? 0 : 1);
