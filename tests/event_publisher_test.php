<?php
/**
 * اختبارات ناشر الأحداث المؤسسي — EventPublisher (K3)
 * تدقيقات المستخدم الثلاثة: حقن فشلٍ وسط المعاملة (صفر يتيم) · رفض كل حقلٍ
 * إلزاميٍّ على حدة · العطالة بإعادة نشرٍ فعلية على مستوى الدالة.
 * التشغيل: php tests/event_publisher_test.php — رمز الخروج 0/1.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';

use App\Core\EventPublisher;
use App\Core\EventValidationException;

$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}

// مطابقة بيئة التطبيق: config.php يضبط MYSQLI_REPORT_OFF (تدفق false لا استثناءات)
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');

$MARK = 'K3TEST_' . getmypid();
$count0 = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);

/** حدث صالح كامل (جذر) — قابل للتعديل لكل اختبار. */
function valid_event($MARK, $suffix = '1')
{
    return array(
        'event_key'     => 'equipment.hour_logged',
        'category'      => 'operational',
        'source_module' => 'movement',
        'company_id'    => 4,
        'entity_type'   => 'timesheet',
        'entity_id'     => 990000 + intval($suffix),
        'occurred_at'   => '2026-07-08 09:00:00',
        'created_by'    => 1,
        'payload'       => array('hours' => 1, 'marker' => $MARK, 'n' => $suffix),
        'equipment_id'  => 1,
        'quantity'      => 1,
        'unit'          => 'hour',
        'source_ref'    => $MARK . '_' . $suffix,
        'notes'         => $MARK,
    );
}

try {

echo "── 1) المسار الصالح: كل حقول العقد تُكتب ──\n";
$r1 = EventPublisher::publish($conn, valid_event($MARK, '1'));
ok('نشر ناجح بمعرّف خادمي', $r1['duplicate'] === false && $r1['id'] > 0);
$row = $conn->query("SELECT * FROM fin_financial_events WHERE id = {$r1['id']}")->fetch_assoc();
ok('event_key/category/schema_version مكتوبة', $row['event_key'] === 'equipment.hour_logged' && $row['category'] === 'operational' && intval($row['schema_version']) === 1);
ok('entity/occurred_at/payload مكتوبة', $row['entity_type'] === 'timesheet' && intval($row['entity_id']) === 990001 && $row['occurred_at'] === '2026-07-08 09:00:00' && strpos($row['payload'], $MARK) !== false);
ok('correlation ULID + idempotency حتمي', preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $row['correlation_id']) === 1 && $row['idempotency_key'] === 'equipment.hour_logged:timesheet:990001');
ok('event_no من المتتالية الذرّية بصيغة EV-nnnn', preg_match('/^EV-\d{4,}$/', $row['event_no']) === 1);
ok('الجسر: event_type=enterprise (معزول عن فلاتر المالية القديمة)', $row['event_type'] === 'enterprise');
ok('المرجع الرقمي equipment_id=1 كما مُرّر — مرجع لا نسخة', intval($row['equipment_id']) === 1 && $row['contract_id'] === null);

echo "── 2) تدقيق 2: رفض كل حقلٍ إلزاميٍّ على حدة (§9-1) ──\n";
foreach (EventPublisher::MANDATORY as $f) {
    $e = valid_event($MARK, '50');
    unset($e[$f]);
    $before = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
    $thrown = false;
    try { EventPublisher::publish($conn, $e); } catch (EventValidationException $x) { $thrown = true; }
    $after = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
    ok("إسقاط {$f} وحده ⇒ رفض + صفر إدراج", $thrown && $after === $before);
}

echo "── 3) رفض الصيغ الفاسدة والمراجع النصية ──\n";
foreach (array(
    'event_key فاسد'          => array('event_key' => 'NotDotted'),
    'category خارج السبعة'    => array('category' => 'weird'),
    'entity_id نصي'           => array('entity_id' => 'ABC-77'),
    'contract_id نصي (مرجع)'  => array('contract_id' => 'عقد عمر هاشم'),
    'occurred_at فاسد'        => array('occurred_at' => '08/07/2026'),
) as $label => $patch) {
    $e = array_merge(valid_event($MARK, '60'), $patch);
    $thrown = false;
    try { EventPublisher::publish($conn, $e); } catch (EventValidationException $x) { $thrown = true; }
    ok("{$label} ⇒ رفض", $thrown);
}

echo "── 4) تدقيق 1: الذرّية بحقن فشلٍ متعمّدٍ وسط المعاملة ──\n";
$before = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
$conn->begin_transaction();
$rTx = EventPublisher::publish($conn, valid_event($MARK, '2'));
ok('النشر داخل المعاملة نجح (قبل الحقن)', $rTx['duplicate'] === false && $rTx['id'] > 0);
// حقن الفشل المتعمّد: عبارة فاسدة بعد كتابة الحدث وقبل الـcommit — كما في انهيار عملية مصدرٍ حقيقية.
$inject = @$conn->query("UPDATE no_such_table_k3 SET x=1");
ok('الحقن فشل فعلًا (محاكاة انهيار المصدر)', $inject === false);
$conn->rollback();
$after = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
ok('rollback ⇒ صفر حدثٍ يتيم (العدّ لم يتغيّر)', $after === $before);
$gone = $conn->query("SELECT COUNT(*) FROM fin_financial_events WHERE id = {$rTx['id']}")->fetch_row()[0];
ok('صفّ الحدث المكتوب داخل المعاملة تراجع كليًّا', intval($gone) === 0);
// وبعد التراجع: نفس العملية المنطقية تُنشر بنجاح (المفتاح تحرّر مع التراجع — لا قفل ميت)
$rRetry = EventPublisher::publish($conn, valid_event($MARK, '2'));
ok('إعادة النشر بعد التراجع تنجح (المفتاح تحرّر مع rollback)', $rRetry['duplicate'] === false && $rRetry['id'] > 0);

echo "── 5) تدقيق 3: العطالة بإعادة نشرٍ فعلية (مستوى الدالة) ──\n";
$before = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
$dup = EventPublisher::publish($conn, valid_event($MARK, '2')); // نفس العملية المنطقية للمرة الثانية
$after = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
ok('نفس الحدث المنطقي ⇒ duplicate=true بلا استثناء', $dup['duplicate'] === true);
ok('يعيد معرّف الحدث القائم نفسه', $dup['id'] === $rRetry['id']);
ok('نفس المفتاح الحتمي', $dup['idempotency_key'] === $rRetry['idempotency_key']);
ok('صفر صفٍّ جديد (العدّ ثابت)', $after === $before);

echo "── 6) وراثة Correlation للمشتقات (Fan-Out جاهز لK5) ──\n";
$root = EventPublisher::publish($conn, valid_event($MARK, '3'));
$derived = valid_event($MARK, '4');
$derived['event_key'] = 'finance.cost_recognized';
$derived['category'] = 'financial';
$derived['source_module'] = 'finance';
$derived['entity_type'] = 'fin_event';
$derived['correlation_id'] = $root['correlation_id']; // المشتق يرث الجذر حرفيًا
$d = EventPublisher::publish($conn, $derived);
$dRow = $conn->query("SELECT correlation_id FROM fin_financial_events WHERE id = {$d['id']}")->fetch_assoc();
ok('المشتق خزّن correlation الجذر حرفيًا', $dRow['correlation_id'] === $root['correlation_id']);
$chain = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events WHERE correlation_id = '{$root['correlation_id']}'")->fetch_row()[0]);
ok('سلسلة الأثر تُجمع بمعرّفٍ واحد (جذر+مشتق)', $chain === 2);
$root2 = EventPublisher::publish($conn, valid_event($MARK, '5'));
ok('جذرٌ جديد بلا تمرير = correlation جديد', $root2['correlation_id'] !== $root['correlation_id']);

} catch (\Throwable $t) {
    $FAIL++;
    echo "  ✘ استثناء غير متوقع: " . $t->getMessage() . "\n";
}

// ── تنظيف كامل ──
@$conn->rollback(); // تحصين: لو انقطع القسم 4 قبل rollback فلا تجري DELETEs داخل معاملةٍ ستتراجع
$conn->query("DELETE FROM fin_financial_events WHERE notes LIKE 'K3TEST_%'"); // يشمل بقايا أي تشغيلٍ سابقٍ منقطع
$conn->query("DELETE FROM ems_sequences WHERE scope = 'fin_financial_events:EV:4'");
$final = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
ok('teardown: العدّ عاد لما قبل الاختبار', $final === $count0);

echo str_repeat('═', 50) . "\n";
echo "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
