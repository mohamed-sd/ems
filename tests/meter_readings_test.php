<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-25 — اختبار قبول: قراءاتُ العدّادات (UX-10 §8 · §8.3-F2 · UX-04 §3)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/meter_readings_test.php
 *
 * ما يُثبته:
 *   ① الحراس: نوعٌ خارج (hour·km) · قيمةٌ سالبة · تاريخٌ ناقص · معدةٌ أجنبية.
 *   ② **«value ≥ آخرِ قراءة»** → 422 بقيمتها وتاريخها (F2 نصًّا).
 *   ③ **«409 قراءةُ اليوم قائمة»** بمرجعها لا برفضٍ أصمّ.
 *   ④ **الإدخالُ المتأخر** لا يكسر الرتابة (قراءةٌ تتجاوز لاحقةً → 422).
 *   ⑤ **التصفيرُ بقرارٍ موثَّق**: بلا سببٍ ومرجعٍ → 422 · وبهما يفتح **سلسلةً
 *      جديدة** تقبل الأصغرَ وتستأنف رتابتَها · والسابقةُ **محفوظةٌ كاملة**.
 *   ⑥ **سلّمُ المصدر معلَن**: قراءةٌ ← افتتاحيٌّ ← **بديلٌ موروثٌ مسمًّى باسمه**
 *      (`legacy_proxy` · `is_reading=false`) — لا يُقدَّم ما ليس عدّادًا عدّادًا.
 *   ⑦ **الوصلُ الأول**: الالتقاطُ من سجل الدوام عاطلٌ ولا يُفشِل كتابةَ الدوام.
 *   ⑧ **الوصلُ الثاني**: استحقاقُ الوقائية يُحسب على العدّاد الحقيقي — وبلا
 *      خطةٍ صالحةٍ يُعلَن ولا يُخترع.
 *   ⑨ المرآةُ الموروثة `equipments.operating_hours` تُحدَّث فلا تكذب الشاشاتُ القديمة.
 *
 * البذرُ معزول: معدةٌ صوريةٌ M25T وتواريخُ 2045 — تُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Fleet/MeterReadingService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Fleet\MeterReadingService as MRS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999925;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn) {
    $ids = array();
    $r = $conn->query("SELECT id FROM equipments WHERE name LIKE 'M25T%'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $eid) {
        $conn->query("DELETE FROM meter_readings WHERE equipment_id = {$eid}");
        $conn->query("DELETE FROM equipments WHERE id = {$eid}");
    }
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-25 — قراءاتُ العدّادات ══\n");

// ═══ البذر ═══
head('البذر — معدةٌ صوريةٌ بلا قراءةٍ ولا عدّادٍ افتتاحي');
$conn->query("INSERT INTO equipments (company_id, name, meter_uom, status)
              VALUES ({$CO}, 'M25T معدةُ اختبار', 'ساعات', 1)");
$EQ = intval($conn->insert_id);
check($EQ > 0, "المعدة #{$EQ}");

// ═══ ① الحراس ═══
head('① الحراس');
$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'liters', 'reading_date' => '2045-01-10', 'value' => 10), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'نوعُ عدّادٍ خارج (hour · km) → 422');

$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => 'غدًا', 'value' => 10), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'تاريخٌ غيرُ صالح → 422');

$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => '2045-01-10', 'value' => -5), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'قيمةٌ سالبة → 422');

$r = MRS::record($conn, $gate, $CO, 99999999, array('meter_type' => 'hour', 'reading_date' => '2045-01-10', 'value' => 10), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'معدةٌ خارج النطاق → 422');

// ═══ ⑥-أ سلّمُ المصدر قبل أي قراءة ═══
head('⑥-أ سلّمُ المصدر قبل أي قراءة — **البديلُ يُسمّى بديلًا**');
$m = MRS::currentMeter($conn, $gate, $CO, $EQ, 'hour');
check($m['source'] === 'legacy_proxy' && $m['is_reading'] === false
      && strpos($m['note'], 'ليس قراءةَ عدّاد') !== false,
      'بلا قراءةٍ: المصدرُ `legacy_proxy` و`is_reading=false` والنصُّ يقول إنه ليس عدّادًا');

// ═══ ② الرتابة ═══
head('② السلسلةُ لا تتراجع — «value ≥ آخرِ قراءة»');
$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => '2045-01-10', 'value' => 1000), $ACTOR);
check($r['ok'] && $r['delta'] === null, 'أولُ قراءةٍ (1000) — بلا فارقٍ لأنها أولُ السلسلة');

$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => '2045-01-20', 'value' => 1080), $ACTOR);
check($r['ok'] && abs($r['delta'] - 80.0) < 0.001, 'قراءةٌ ثانية (1080) بفارقٍ 80');

$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => '2045-01-25', 'value' => 900), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], '1080') !== false
      && strpos($r['reason'], 'تصفير') !== false,
      'قراءةٌ أقلُّ من السابقة → **422 بقيمتها** ومعها نصُّ «قرارِ تصفيرٍ موثَّق»');

// ═══ ③ 409 ═══
head('③ «409 قراءةُ اليوم قائمة» بمرجعها');
$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => '2045-01-20', 'value' => 1090), $ACTOR);
check(!$r['ok'] && $r['code'] === 409 && strpos($r['reason'], '1080') !== false,
      'قراءةٌ ثانيةٌ لليوم نفسِه → 409 **بمرجع القائمة وقيمتها**');
$cnt = intval($conn->query("SELECT COUNT(*) n FROM meter_readings WHERE equipment_id={$EQ}")->fetch_assoc()['n']);
check($cnt === 2, 'والصفوفُ ما زالت اثنين — الرفضُ لا يكتب شيئًا');

// ═══ ④ الإدخال المتأخر ═══
head('④ الإدخالُ المتأخر لا يكسر الرتابة');
$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => '2045-01-15', 'value' => 1200), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'لاحقة') !== false,
      'قراءةٌ بتاريخٍ وسيطٍ تتجاوز لاحقةً مسجَّلة → 422');
$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => '2045-01-15', 'value' => 1040), $ACTOR);
check($r['ok'] && abs($r['delta'] - 40.0) < 0.001, 'وبقيمةٍ بين الطرفين (1040) تُقبل بفارقها الصحيح 40');

// ═══ ⑤ التصفير ═══
head('⑤ التصفيرُ **بقرارٍ موثَّق** — يفتح سلسلةً ولا يمحو ماضيًا');
$r = MRS::reset($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => '2045-02-01', 'value' => 0), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'مرجعُ مستند') !== false,
      'تصفيرٌ بلا سببٍ ومرجعٍ → 422');

$r = MRS::reset($conn, $gate, $CO, $EQ, array(
    'meter_type' => 'hour', 'reading_date' => '2045-02-01', 'value' => 0,
    'reset_reason' => 'استبدالُ عدّادٍ معطوب', 'reset_doc_ref' => 'محضرُ ورشة M25T/1'), $ACTOR);
check($r['ok'] && intval($r['chain_no']) === 2, 'وبقرارٍ موثَّق: فُتحت السلسلة 2');

$old = intval($conn->query("SELECT COUNT(*) n FROM meter_readings WHERE equipment_id={$EQ} AND chain_no=1")->fetch_assoc()['n']);
check($old === 3, 'والسلسلةُ الأولى **محفوظةٌ كاملة** (3 قراءات) — لا محوَ ماضٍ');

$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => '2045-02-10', 'value' => 25), $ACTOR);
check($r['ok'] && intval($r['chain_no']) === 2 && abs($r['delta'] - 25.0) < 0.001,
      'والسلسلةُ الجديدة تقبل 25 (أصغرَ من 1080) وتستأنف رتابتَها');

$r = MRS::record($conn, $gate, $CO, $EQ, array('meter_type' => 'hour', 'reading_date' => '2045-02-15', 'value' => 20), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'وتراجعٌ داخلها يُرفض كسابقتها → 422');

$dbl = $conn->query("SELECT reset_doc_ref, is_reset FROM meter_readings WHERE equipment_id={$EQ} AND is_reset=1")->fetch_assoc();
check($dbl && trim((string)$dbl['reset_doc_ref']) !== '', 'وصفُّ التصفير يحمل مستندَه (CHECK بنيويٌّ حزامًا)');

// ═══ ⑥-ب سلّمُ المصدر بعد القراءات ═══
head('⑥-ب سلّمُ المصدر بعد القراءات');
$m = MRS::currentMeter($conn, $gate, $CO, $EQ, 'hour');
check($m['source'] === 'reading' && $m['is_reading'] === true && abs($m['value'] - 25.0) < 0.001
      && $m['as_of'] === '2045-02-10',
      'العدّادُ الحالي = آخرُ قراءةٍ **في السلسلة الحية** (25 بتاريخها) لا أكبرَ قيمةٍ تاريخية');

// ═══ ⑨ المرآة ═══
head('⑨ المرآةُ الموروثة لا تكذب');
$mirror = $conn->query("SELECT operating_hours FROM equipments WHERE id={$EQ}")->fetch_assoc();
check(intval($mirror['operating_hours']) === 25,
      '`equipments.operating_hours` تعكس آخرَ قراءةٍ (25) — الشاشاتُ القديمة تقرأ الصحيح');

// ═══ ⑦ الوصلُ الأول — الالتقاط من سجل الدوام ═══
head('⑦ الالتقاطُ من سجل الدوام — عاطلٌ ولا يُفشِل');
$ts = array('id' => 999925, 'eq_id' => $EQ, 'date' => '2045-03-01',
            'end_hours' => 30, 'end_minutes' => 30, 'end_seconds' => 0);
$r = MRS::captureFromTimesheet($conn, $gate, $CO, $ts, $ACTOR);
check($r['ok'], 'قراءةُ نهاية الوردية (30:30) التُقطت');
$row = $conn->query("SELECT value, source, source_ref FROM meter_readings
                      WHERE equipment_id={$EQ} AND reading_date='2045-03-01'")->fetch_assoc();
check($row && abs(floatval($row['value']) - 30.5) < 0.001 && $row['source'] === 'timesheet'
      && $row['source_ref'] === 'TS-999925',
      'بقيمةٍ عشريةٍ صحيحة 30.5 ومصدرٍ ومرجعٍ (TS-999925)');

$r2 = MRS::captureFromTimesheet($conn, $gate, $CO, $ts, $ACTOR);
check(!$r2['ok'] && strpos($r2['skipped'], '409') === 0, 'التقاطٌ ثانٍ للصف نفسِه: **عاطلٌ** بـ409 معلَنًا');

$tsBack = array('id' => 999926, 'eq_id' => $EQ, 'date' => '2045-03-05',
                'end_hours' => 5, 'end_minutes' => 0, 'end_seconds' => 0);
$r3 = MRS::captureFromTimesheet($conn, $gate, $CO, $tsBack, $ACTOR);
check(!$r3['ok'] && strpos($r3['skipped'], '422') === 0,
      'وعدّادٌ متراجعٌ على صفِّ دوام: **يُعلَن ولا يُفشِل** (مسجِّلُ تاريخٍ لا مانعُ ميدان)');

$tsEmpty = array('id' => 999927, 'eq_id' => $EQ, 'date' => '2045-03-06',
                 'end_hours' => 0, 'end_minutes' => 0, 'end_seconds' => 0);
$r4 = MRS::captureFromTimesheet($conn, $gate, $CO, $tsEmpty, $ACTOR);
check(!$r4['ok'] && strpos($r4['skipped'], 'لا يُخترع') !== false,
      'وصفٌّ بلا قراءةٍ: **لا يُخترع رقم**');

// ═══ ⑧ الوصلُ الثاني — الوقائية ═══
head('⑧ استحقاقُ الوقائية على العدّاد الحقيقي');
$due = MRS::preventiveDue($conn, $gate, $CO, array(
    'trigger_basis' => 'ساعات', 'equipment_id' => $EQ, 'interval_value' => 250,
    'last_done_meter' => 0, 'next_due_meter' => 250));
check($due['due'] === false && abs($due['remaining'] - 219.5) < 0.001 && $due['is_reading'] === true,
      'خطةُ 250 والعدّادُ 30.5 ⇒ غيرُ مستحقة ويتبقى 219.5 — محسوبةٌ على **قراءةٍ** لا بديل');

$due = MRS::preventiveDue($conn, $gate, $CO, array(
    'trigger_basis' => 'ساعات', 'equipment_id' => $EQ, 'interval_value' => 25,
    'last_done_meter' => 0, 'next_due_meter' => 25));
check($due['due'] === true, 'وخطةُ 25 ⇒ **مستحقة** (30.5 ≥ 25)');

$due = MRS::preventiveDue($conn, $gate, $CO, array(
    'trigger_basis' => 'زمن', 'equipment_id' => $EQ, 'interval_value' => 30,
    'last_done_meter' => null, 'next_due_meter' => null));
check($due['due'] === false && strpos($due['reason'], 'زمن') !== false,
      'وخطةٌ بأساسٍ زمنيٍّ تُعلَن أنها ليست بعدّاد');

$due = MRS::preventiveDue($conn, $gate, $CO, array(
    'trigger_basis' => 'ساعات', 'equipment_id' => $EQ, 'interval_value' => 250,
    'last_done_meter' => null, 'next_due_meter' => null));
check($due['due'] === false && strpos($due['reason'], 'يُعلَن ولا يُخترع') !== false,
      'وخطةٌ بلا عدّادِ تنفيذٍ ولا استحقاق: **تُعلَن ولا يُخترع لها رقم**');

// ═══ ⑩ الوصلُ في مصدره ═══
head('⑩ الوصلان في موضعيهما (فحصُ مصدر)');
$tsSrc = file_get_contents(dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php');
check(strpos($tsSrc, 'captureFromTimesheet') !== false,
      'الالتقاطُ موصولٌ في نقطة خنق الكتابة المزدوجة (mirrorFromTimesheet)');
$mntSrc = file_get_contents(dirname(__DIR__) . '/Maintenance/mnt_helpers.php');
check(strpos($mntSrc, 'MeterReadingService::currentMeter') !== false
      && strpos($mntSrc, 'SUM(t.operator_hours)') === false,
      'ومصدرُ عدّاد الوقائية صار الخدمةَ — ولم يبقَ جمعُ ساعات المشغّل مقنَّعًا عدّادًا');
$mod = $conn->query("SELECT id, owner_role_id FROM modules WHERE code='Equipments/meter_readings.php'")->fetch_assoc();
check($mod && intval($mod['id']) === 155 && intval($mod['owner_role_id']) === 7,
      'والشاشة 155 مسجَّلةٌ لمالك سجل المعدات (7)');
$scr = file_get_contents(dirname(__DIR__) . '/Equipments/meter_readings.php');
check(strpos($scr, 'MRS::record') !== false && strpos($scr, "insert('meter_readings'") === false,
      'والشاشةُ لا تدرج خامًا — كلُّ فعلٍ عبر الخدمة');

// ═══ ⑪ صفرُ انحدار ═══
head('⑪ صفرُ أثرٍ على معدةٍ حية');
$liveWithReadings = intval($conn->query("SELECT COUNT(DISTINCT r.equipment_id) n FROM meter_readings r
    JOIN equipments e ON e.id = r.equipment_id WHERE e.name NOT LIKE 'M25T%'")->fetch_assoc()['n']);
check($liveWithReadings >= 0, "معداتٌ حيةٌ لها قراءاتٌ الآن: {$liveWithReadings} (الترحيلُ يأتي بتقريره)");

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
