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
$roots_count0 = intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]);

// الأقسام 1-6 = عقد «وضع off مطابقٌ لسلوك اليوم» — حتميةٌ مهما كان علم البيئة.
// القسم 7 يدير وضعه بنفسه ويعيد التجاوز null في الختام.
EventPublisher::$rootModeOverride = 'off';

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

echo "── 7) الجذر المحايد ADR-15: off مطابقٌ · publish كتابةٌ مزدوجةٌ ذرّية ──\n";
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
$regDef = \App\Core\TenantRegistry::get('ems_business_events');
ok('السجل: ems_business_events قناة محرّكٍ (T_RESTRICTED) لا شاشات', $regDef !== null && $regDef['type'] === \App\Core\TenantRegistry::T_RESTRICTED);

// أ) off: لا جذر ولا ربط — سلوك اليوم حرفيًا
EventPublisher::$rootModeOverride = 'off';
$rootsA = intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]);
$rOff = EventPublisher::publish($conn, valid_event($MARK, '70'));
$rowOff = $conn->query("SELECT root_event_id FROM fin_financial_events WHERE id = {$rOff['id']}")->fetch_assoc();
ok('off: الإسقاط بلا root_event_id (سلوك اليوم حرفيًا)', $rOff['root_event_id'] === null && $rowOff['root_event_id'] === null);
ok('off: صفر جذورٍ جديدة', intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]) === $rootsA);

// ب) publish: الحقيقة أولًا ثم الإسقاط مربوطًا بها
EventPublisher::$rootModeOverride = 'publish';
$rOn = EventPublisher::publish($conn, valid_event($MARK, '71'));
ok('publish: الناتج يحمل root_event_id', $rOn['duplicate'] === false && intval($rOn['root_event_id']) > 0);
$fin = $conn->query("SELECT * FROM fin_financial_events WHERE id = {$rOn['id']}")->fetch_assoc();
$rt  = $conn->query("SELECT * FROM ems_business_events WHERE id = " . intval($rOn['root_event_id']))->fetch_assoc();
ok('الإسقاط المالي مربوطٌ بحقيقته', $rt && intval($fin['root_event_id']) === intval($rt['id']));
ok('الحقيقة تحمل نفس العقد (مفتاح/شركة/كيان/عطالة/سلسلة)',
    $rt['event_key'] === 'equipment.hour_logged' && intval($rt['company_id']) === 4
    && $rt['entity_type'] === 'timesheet' && intval($rt['entity_id']) === 990071
    && $rt['idempotency_key'] === $fin['idempotency_key']
    && $rt['correlation_id'] === $fin['correlation_id']);
$beNum = 0;
if (preg_match('/^BE-(\d{4,})$/', $rt['event_no'], $mNo)) { $beNum = intval($mNo[1]); }
ok('حقيقة recorded بترقيم BE يواصل بعد الردم (≥11) وevent_uuid ULID',
    $rt['event_status'] === 'recorded' && $beNum >= 11
    && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $rt['event_uuid']) === 1);

// ج) العطالة المزدوجة: إعادة نشر نفس العملية لا تُنشئ جذرًا ثانيًا
$rootsB = intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]);
$dup2 = EventPublisher::publish($conn, valid_event($MARK, '71'));
ok('إعادة النشر: duplicate=true ونفس الجذر القائم', $dup2['duplicate'] === true && intval($dup2['root_event_id']) === intval($rOn['root_event_id']));
ok('صفر جذرٍ مكرّر (العدّ ثابت)', intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]) === $rootsB);

// د) الشفاء الذاتي: إسقاطُ off اليتيم يُربَط عند إعادة نشره تحت publish
$heal = EventPublisher::publish($conn, valid_event($MARK, '70'));
$rowHeal = $conn->query("SELECT root_event_id FROM fin_financial_events WHERE id = {$rOff['id']}")->fetch_assoc();
ok('الشفاء الذاتي: المكرّر ربط الإسقاط اليتيم بجذرٍ وليد', $heal['duplicate'] === true && $rowHeal['root_event_id'] !== null && intval($heal['root_event_id']) === intval($rowHeal['root_event_id']));

// هـ) الذرّية المزدوجة: rollback يمحو الحقيقة وإسقاطها معًا
$finC  = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
$rootsC = intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]);
$conn->begin_transaction();
$rTx2 = EventPublisher::publish($conn, valid_event($MARK, '72'));
$conn->rollback();
ok('rollback: لا حقيقة ولا إسقاط (ذرّية الكتابة المزدوجة)',
    intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]) === $finC
    && intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]) === $rootsC);

// و) الردم الصادق قائم: كل صفوف الدفتر الأساس مربوطةٌ بجذرٍ legacy
ok('الردم: صفر صفِّ أساسٍ بلا جذر (root_event_id IS NULL)',
    intval($conn->query("SELECT COUNT(*) FROM fin_financial_events WHERE root_event_id IS NULL AND notes NOT LIKE 'K3TEST_%'")->fetch_row()[0]) === 0);

echo "── 8) publishFact (§9.3 D05): حقيقةٌ بلا إسقاطٍ مالي ──\n";
// أ) off: لا-عملية موثقة تعيد null — مفتاح الإطفاء الواحد للعائلة
EventPublisher::$rootModeOverride = 'off';
$finB8 = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
$rootsB8 = intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]);
$factEvent = valid_event($MARK, '80');
$factEvent['event_key'] = 'request.submitted';
$factEvent['category'] = 'financial';
$factEvent['entity_type'] = 'fin_request';
$factEvent['idempotency_key'] = 'fact:test:' . getmypid();
$fOff = EventPublisher::publishFact($conn, $factEvent);
ok('off: publishFact = null وصفر كتابة', $fOff === null
    && intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]) === $rootsB8);

// ب) publish: الحقيقة تُكتب في الجذر والدفتر المالي لا يُلمس إطلاقًا
EventPublisher::$rootModeOverride = 'publish';
$fOn = EventPublisher::publishFact($conn, $factEvent);
$factRow = $conn->query("SELECT * FROM ems_business_events WHERE id = " . intval($fOn['id']))->fetch_assoc();
ok('publish: حقيقة request.submitted كُتبت في الجذر', $fOn !== null && $factRow
    && $factRow['event_key'] === 'request.submitted' && $factRow['event_status'] === 'recorded');
ok('الدفتر المالي لم يُلمس (حقيقةٌ بلا إسقاط — العدّ ثابت)',
    intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]) === $finB8);
ok('لا إسقاط يشير إلى حقيقة الدورة (root يتيمٌ عمدًا هنا)',
    intval($conn->query("SELECT COUNT(*) FROM fin_financial_events WHERE root_event_id = " . intval($fOn['id']))->fetch_row()[0]) === 0);

// ج) عطالة الحقيقة: نفس المفتاح ⇒ نفس الصف بلا ازدواج
$rootsC8 = intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]);
$fDup = EventPublisher::publishFact($conn, $factEvent);
ok('عطالة الحقيقة: نفس id وصفر صفٍّ جديد', intval($fDup['id']) === intval($fOn['id'])
    && intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]) === $rootsC8);

// د) العقد يسري على الحقائق أيضًا: مفتاحٌ فاسد يُرفض
$badFact = $factEvent; $badFact['event_key'] = 'NotDotted';
$thrown8 = false;
try { EventPublisher::publishFact($conn, $badFact); } catch (EventValidationException $x) { $thrown8 = true; }
ok('حقيقةٌ بمفتاحٍ فاسد ⇒ رفض §9', $thrown8);
EventPublisher::$rootModeOverride = null;

} catch (\Throwable $t) {
    $FAIL++;
    echo "  ✘ استثناء غير متوقع: " . $t->getMessage() . "\n";
}

// ── تنظيف كامل ──
@$conn->rollback(); // تحصين: لو انقطع القسم 4 قبل rollback فلا تجري DELETEs داخل معاملةٍ ستتراجع
EventPublisher::$rootModeOverride = null;
$conn->query("DELETE FROM fin_financial_events WHERE notes LIKE 'K3TEST_%'"); // يشمل بقايا أي تشغيلٍ سابقٍ منقطع (الإسقاط أولًا — FK)
$conn->query("DELETE FROM ems_business_events WHERE payload LIKE '%K3TEST_%'"); // جذور الاختبار بعد إسقاطاتها
// متتالية EV تُحذف (لا تصادم — الأساس FIN-EV-…)؛ متتالية BE تبقى مرتفعةً عمدًا
// (حذفها يعيد الترقيم لـBE-0001 المحجوز للردم — فجوات الترقيم مقبولة).
$conn->query("DELETE FROM ems_sequences WHERE scope = 'fin_financial_events:EV:4'");
$final = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0]);
ok('teardown: العدّ عاد لما قبل الاختبار', $final === $count0);
$rootsFinal = intval($conn->query("SELECT COUNT(*) FROM ems_business_events")->fetch_row()[0]);
ok('teardown: الجذور عادت لخط الأساس (الردم وحده)', $rootsFinal === $roots_count0);

echo str_repeat('═', 50) . "\n";
echo "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
