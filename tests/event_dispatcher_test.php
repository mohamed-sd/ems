<?php
/**
 * اختبارات موزّع الأحداث — EventDispatcher (K4)
 * تدقيقات المستخدم الثلاثة: انهيار حقيقي بين المعالجة والـCursor (exactly-once
 * بالأثر) · حدثٌ سام → Dead-Letter والعامل يواصل · استقلال Cursor كل مستهلك.
 * التشغيل: php tests/event_dispatcher_test.php — رمز الخروج 0/1.
 * وضع الطفل (عامل حقيقي منفصل): --child <consumer> [crash_after_id] [max_attempts]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
require_once dirname(__DIR__) . '/app/Core/EventDispatcher.php';

use App\Core\EventPublisher;
use App\Core\EventDispatcher;

function db()
{
    $c = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
    if ($c->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
    $c->set_charset('utf8mb4');
    return $c;
}

/**
 * معالجا الاختبار (يُشتقّان من اسم المستهلك):
 *  • *_fx_* / *_cx_*: مستهلك سليم — يشترك في probe.source_logged فقط؛ ينشر حدثًا
 *    مشتقًا عبر EventPublisher وارثًا correlation الأصل (عطالة أثرٍ حتمية —
 *    نمط مستهلك المالية في K5 نفسه). غيره: نجاح بلا أثر.
 *  • *_ps_*: حسّاس للسم — payload فيه poison=true ⇒ استثناء؛ غيره كالسليم.
 */
function make_handler($consumer)
{
    return function (array $event, mysqli $conn) use ($consumer) {
        if ($event['event_key'] !== 'probe.source_logged') {
            return; // ليس من اشتراكه — نجاح صامت (فلترة المستهلك بنوع الحدث)
        }
        $payload = json_decode((string) $event['payload'], true) ?: array();
        if (strpos($consumer, '_ps_') !== false && !empty($payload['poison'])) {
            throw new RuntimeException('poison event refused by ' . $consumer);
        }
        $short = (strpos($consumer, '_ps_') !== false) ? 'k4ps' : 'k4fx';
        EventPublisher::publish($conn, array(
            'event_key'      => 'analytics.probe_derived',
            'category'       => 'analytics',
            'source_module'  => 'system',
            'company_id'     => 4,
            'entity_type'    => $short,                       // يفصل أثر كل مستهلك
            'entity_id'      => intval($event['id']),         // عطالة حتمية من معرّف الأصل
            'occurred_at'    => '2026-07-08 10:00:00',
            'created_by'     => 1,
            'payload'        => array('derived_from' => intval($event['id'])),
            'correlation_id' => $event['correlation_id'],     // وراثة السلسلة (K3)
            'notes'          => 'K4TEST_DERIVED',
        ));
    };
}

// ═════ وضع الطفل: عامل توزيعٍ حقيقي في عمليةٍ منفصلة ═════
if (isset($argv[1]) && $argv[1] === '--child') {
    $consumer = (string) $argv[2];
    $crashAfter = isset($argv[3]) && $argv[3] !== '0' ? intval($argv[3]) : null;
    $max = isset($argv[4]) ? intval($argv[4]) : 3;
    $conn = db();
    $d = new EventDispatcher($conn, array('max_attempts' => $max, 'crash_after_event' => $crashAfter));
    $d->register($consumer, make_handler($consumer));
    $stats = $d->runOnce();
    echo json_encode($stats), "\n";
    exit(0);
}

// ═════ الوالد: التدقيقات ═════
$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}

$conn = db();
$PID = getmypid();
$CX = "k4t_cx_{$PID}"; $PS = "k4t_ps_{$PID}"; $FX = "k4t_fx_{$PID}";
$PHP_BIN = PHP_BINARY;
$SELF = __FILE__;

function spawn($consumer, $crashAfter = 0, $max = 3)
{
    global $PHP_BIN, $SELF;
    $cmd = escapeshellarg($PHP_BIN) . ' ' . escapeshellarg($SELF) . " --child {$consumer} {$crashAfter} {$max} 2>&1";
    exec($cmd, $out, $code);
    // آخر سطرٍ غير فارغ = JSON الإحصاءات (تحذيرات تحميل الإضافات قد تسبقه في الدمج)
    $last = '';
    for ($i = count($out) - 1; $i >= 0; $i--) { if (trim($out[$i]) !== '') { $last = trim($out[$i]); break; } }
    return array($code, implode("\n", $out), $last);
}
function cursor_of(mysqli $c, $name)
{
    $r = $c->query("SELECT cursor_event_id FROM ems_event_consumers WHERE consumer='{$name}'")->fetch_row();
    return $r ? intval($r[0]) : -1;
}
function derived_count(mysqli $c, $short, $srcId)
{
    return intval($c->query("SELECT COUNT(*) FROM fin_financial_events WHERE event_key='analytics.probe_derived' AND entity_type='{$short}' AND entity_id={$srcId}")->fetch_row()[0]);
}
function publish_source(mysqli $c, $n, $poison = false)
{
    return EventPublisher::publish($c, array(
        'event_key' => 'probe.source_logged', 'category' => 'operational', 'source_module' => 'system',
        'company_id' => 4, 'entity_type' => 'k4src', 'entity_id' => 880000 + $n,
        'occurred_at' => '2026-07-08 09:30:00', 'created_by' => 1,
        'payload' => array('n' => $n, 'poison' => $poison), 'notes' => 'K4TEST_SRC',
    ));
}

// تنظيف بقايا أي تشغيلٍ سابق
$conn->query("DELETE FROM fin_financial_events WHERE notes IN ('K4TEST_SRC','K4TEST_DERIVED')");
$conn->query("DELETE FROM ems_event_consumers WHERE consumer LIKE 'k4t_%'");
$conn->query("DELETE FROM ems_event_deliveries WHERE consumer LIKE 'k4t_%'");
$conn->query("DELETE FROM ems_event_dead_letter WHERE consumer LIKE 'k4t_%'");

try {

echo "── تدقيق 1: exactly-once بالأثر — انهيار حقيقي بين المعالجة والـCursor ──\n";
$eA = publish_source($conn, 1);
list($code, $out) = spawn($CX, $eA['id']);          // الطفل يعالج ثم يُقتل قبل الـCursor
ok('العامل انهار فعلًا (exit=97 بحقنٍ بعد المعالجة قبل الـCursor)', $code === 97 && strpos($out, 'CRASH INJECTED') !== false);
ok('الأثر وقع مرةً قبل الانهيار (المشتق موجود)', derived_count($conn, 'k4fx', $eA['id']) === 1);
ok('الـCursor لم يتقدّم (الانهيار قبل تحديثه)', cursor_of($conn, $CX) < $eA['id']);
list($code2, $out2, $json2) = spawn($CX);            // استئنافٌ حقيقي — إعادة تسليمٍ حتمية
$stats2 = json_decode($json2, true);
ok('الاستئناف أعاد التسليم وعالج بنجاح (عطالة الأثر امتصّته)', $code2 === 0 && $stats2[$CX]['processed'] >= 1);
ok('الأثر بقي واحدًا — exactly-once بالأثر رغم تسليمين', derived_count($conn, 'k4fx', $eA['id']) === 1);
ok('الـCursor تقدّم بعد الاستئناف', cursor_of($conn, $CX) >= $eA['id']);

echo "── تدقيق 2: الحدث السام → Dead-Letter بعد الاستنفاد والعامل يواصل خلفه ──\n";
$eP = publish_source($conn, 2, true);                // سامّ لمستهلك ps
$eN = publish_source($conn, 3);                      // سليم بعده مباشرة
$startPs = cursor_of($conn, $PS);
for ($run = 1; $run <= 4; $run++) { spawn($PS, 0, 3); }
$dlq = $conn->query("SELECT attempts FROM ems_event_dead_letter WHERE consumer='{$PS}' AND event_id={$eP['id']}")->fetch_assoc();
ok('السامّ في الرسائل الميتة بعد استنفاد 3 محاولات', $dlq !== null && intval($dlq['attempts']) === 3);
ok('العامل واصل خلف السامّ (السليم التالي عولج)', derived_count($conn, 'k4ps', $eN['id']) === 1);
ok('الـCursor تجاوز السامّ ووصل السليم', cursor_of($conn, $PS) >= $eN['id']);
ok('السامّ نفسه بلا أثرٍ مشتق (رُفض كل مرة)', derived_count($conn, 'k4ps', $eP['id']) === 0);

echo "── تدقيق 3: استقلال Cursor كل مستهلك ──\n";
// FX يُسجَّل الآن ويعالج كل شيء (السمّ لا يعنيه — حساسية ps فقط)
list($codeF, $outF) = spawn($FX);
ok('المستهلك السليم عالج كل الأحداث الثلاثة في دورةٍ واحدة', $codeF === 0 && derived_count($conn, 'k4fx', $eP['id']) === 1 && derived_count($conn, 'k4fx', $eN['id']) === 1);
$cFX = cursor_of($conn, $FX); $cPS = cursor_of($conn, $PS); $cCX = cursor_of($conn, $CX);
ok('مؤشرات الثلاثة مستقلة ومختلفة المواضع', $cFX >= $eN['id'] && $cCX >= $eA['id'] && $cFX !== $cCX);
ok('تعثّر ps التاريخي لم يمسّ تقدّم fx إطلاقًا', $cFX >= $cPS);
// إثبات إضافي: أثناء دورات فشل ps (المحاولات 1-3) كان cursor غيره حرًّا — مضمّن أعلاه بترتيب التنفيذ.

echo "── الترتيب داخل المستهلك: بالمعرّف الخادمي تصاعديًا ──\n";
$ids = $conn->query("SELECT entity_id FROM fin_financial_events WHERE event_key='analytics.probe_derived' AND entity_type='k4fx' AND notes='K4TEST_DERIVED' ORDER BY id ASC")->fetch_all(MYSQLI_NUM);
$flat = array_map(function ($r) { return intval($r[0]); }, $ids);
$sorted = $flat; sort($sorted);
ok('آثار fx بترتيب معرّفات المصدر تصاعديًا', $flat === $sorted && count($flat) === 3);

} catch (Throwable $t) {
    $FAIL++;
    echo "  ✘ استثناء غير متوقع: " . $t->getMessage() . "\n";
}

// ═════ teardown كامل ═════
@$conn->rollback();
$conn->query("DELETE FROM fin_financial_events WHERE notes IN ('K4TEST_SRC','K4TEST_DERIVED')");
$conn->query("DELETE FROM ems_event_consumers WHERE consumer LIKE 'k4t_%'");
$conn->query("DELETE FROM ems_event_deliveries WHERE consumer LIKE 'k4t_%'");
$conn->query("DELETE FROM ems_event_dead_letter WHERE consumer LIKE 'k4t_%'");
// ⚠️ متتالية EV **لا تُحذف**: إعادتها للصفر تصطدم بأرقامٍ إنتاجيةٍ قائمة
// (uq_fin_event_no) منذ صارت بوابة D05 تلد أحداثًا حقيقية. فجوات الترقيم مقبولة.
$left = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events WHERE notes LIKE 'K4TEST%'")->fetch_row()[0]);
ok('teardown: صفر بقايا', $left === 0);

echo str_repeat('═', 50) . "\n";
echo "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
