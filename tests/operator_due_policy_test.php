<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-02 §8.2 — اختبار قبول محرّك سياسات مستحقّات المشغّلين + المطابقة المالية ④
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/operator_due_policy_test.php
 *
 * ثلاث مراحل:
 *   ① المحرّك وحده — سياساتٌ مبذورةٌ بأرقامٍ تُحسب يدويًّا هنا في التعليقات،
 *      وكلُّ ناتجٍ يُقارن بالرقم المحسوب لا بناتج المحرّك نفسه (لا حكمَ ذاتي).
 *   ② مصدرُ القراءة القانوني — resolveTimesheet بالمصدرين (timesheet/legal)
 *      على الخمسين النظيفة: **صفرُ فرقٍ** في حكم العميل والمورد لكل صف.
 *   ③ سياساتُ الخمسين — بذرٌ تجريبيٌّ موسومٌ ومستحقاتٌ متوقعةٌ محسوبة.
 *
 * قاعدة عدم التلفيق حاضرة: لا سياسة ⇒ «لا حكم» معلَنًا لا صفرًا صامتًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Unit/OperatorDue.php';
require_once dirname(__DIR__) . '/app/Services/EffectFanout.php';

use App\Core\TenantContext;
use App\Core\TenantDb;
use App\Services\Unit\OperatorDue;
use App\Services\EffectFanout;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$CO = 4;
$ACTOR = 1;
$conn = $GLOBALS['conn'];
$ctx = TenantContext::forSystem($CO, $ACTOR, '', true);
$gate = new TenantDb($conn, $ctx);

const WIN_FROM = '2027-02-01';
const WIN_TO   = '2027-02-14';
$TESTEMP = 9901; // مشغّلُ اختبار المرحلة ① — معرّفٌ لا يصادم الحقيقيين

$teardown = function () use ($conn, $CO, $TESTEMP) {
    $conn->query("DELETE FROM contract_hour_policies WHERE company_id={$CO} AND operator_id={$TESTEMP}");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ UX-02 §8.2 — محرّك مستحقّات المشغّلين والمطابقة المالية ══\n");

// ═══════════════════════════════════════════════════════════════════════════
// ① المحرّك وحده — أرقامٌ محسوبةٌ يدويًّا
// ═══════════════════════════════════════════════════════════════════════════
head('① المحرّك — لا سياسةَ = لا حكمَ معلَن');

$dayCtx = array('employee_id' => $TESTEMP, 'work_date' => '2027-03-01',
    'project_id' => 4, 'equipment_type' => 1,
    'states' => array('actual_work' => 8.0, 'standby' => 2.0),
    'unit_type' => 'hour', 'unit_qty' => 8.0);

$r = OperatorDue::compute($conn, $CO, $dayCtx);
check(!$r['ok'] && strpos($r['reason'], 'لا سياسةَ') !== false,
    'لا سياسة ⇒ «لا حكم» بسببه المعلَن — لا صفرٌ صامتٌ ولا تخمين');

head('① المحرّك — أساس التشغيل الفعلي بمعدله');
// سياسة: actual_work × 100 → 8 × 100 = 800 (محسوبة يدويًّا)
// E-24: البذرُ يعلن `policy_state` صراحةً — الافتراضُ `draft` **لا يسعّر** عمدًا
$gate->insert('contract_hour_policies', array('party_scope' => 'operator', 'ruling' => 'full',
    'policy_state' => 'active',
    'operator_id' => $TESTEMP, 'work_model' => 'hour', 'pay_basis' => 'actual',
    'rate' => 100, 'currency' => 'SDG', 'is_trial' => 1, 'note' => 'اختبار ①'));
$r = OperatorDue::compute($conn, $CO, $dayCtx);
check($r['ok'] && $r['amount'] === 800.0, "8 ساعات × 100 = 800 (الناتج: {$r['amount']})");
check($r['currency'] === 'SDG' && $r['is_trial'] === true, 'العملة SDG والوسم تجريبي');

head('① المحرّك — الاستعداد أساسٌ مستقل (§8.2: يجوز للمشغّل وحده)');
// + سياسة standby × 40 → 2 × 40 = 80 ⇒ المجموع 880
// E-24: البذرُ يعلن `policy_state` صراحةً — الافتراضُ `draft` **لا يسعّر** عمدًا
$gate->insert('contract_hour_policies', array('party_scope' => 'operator', 'ruling' => 'full',
    'policy_state' => 'active',
    'operator_id' => $TESTEMP, 'work_model' => 'hour', 'pay_basis' => 'standby',
    'rate' => 40, 'currency' => 'SDG', 'is_trial' => 1, 'note' => 'اختبار ①'));
$r = OperatorDue::compute($conn, $CO, $dayCtx);
check($r['ok'] && $r['amount'] === 880.0, "800 + (2 استعداد × 40) = 880 (الناتج: {$r['amount']})");
check(count($r['lines']) === 2, 'سطران في التفصيل — الشفافية أمام المراجع');

head('① المحرّك — الحدُّ الأدنى والأقصى يقصّان');
// سياسة أخصّ بنطاق المشروع 4: actual_work × 100 بحدٍّ أقصى 500 ⇒ تغلب الافتراضية
// و800 تُقصّ إلى 500 ⇒ المجموع 500 + 80 = 580
// E-24: البذرُ يعلن `policy_state` صراحةً — الافتراضُ `draft` **لا يسعّر** عمدًا
$gate->insert('contract_hour_policies', array('party_scope' => 'operator', 'ruling' => 'full',
    'policy_state' => 'active',
    'operator_id' => $TESTEMP, 'work_model' => 'hour', 'pay_basis' => 'actual',
    'rate' => 100, 'max_amount' => 500, 'scope_type' => 'project', 'scope_id' => 4,
    'currency' => 'SDG', 'is_trial' => 1, 'note' => 'اختبار ① أخصّ'));
$r = OperatorDue::compute($conn, $CO, $dayCtx);
check($r['ok'] && $r['amount'] === 580.0,
    "الأخصُّ (مشروع 4، سقف 500) غلب الافتراضية: 500 + 80 = 580 (الناتج: {$r['amount']})");
$clampLine = null;
foreach ($r['lines'] as $l) { if ($l['basis'] === 'actual') { $clampLine = $l; } }
check($clampLine !== null && $clampLine['clamped'] === 'max' && $clampLine['scope'] === 'project',
    'سطرُ التفصيل يعلن القصَّ (max) والنطاقَ (project)');

head('① المحرّك — نطاقُ مشروعٍ آخر لا ينطبق');
$otherCtx = $dayCtx; $otherCtx['project_id'] = 2;
$r = OperatorDue::compute($conn, $CO, $otherCtx);
check($r['ok'] && $r['amount'] === 880.0,
    "في مشروعٍ آخر تعود الافتراضية: 880 (الناتج: {$r['amount']})");

head('① المحرّك — أساسُ الإنتاج (طن) لا يلمس الساعات');
// مشغّل طنّ: ton × 5 → 120 طن × 5 = 600؛ ولا شيءَ عن الساعات (لا سياسة ساعة)
$conn->query("DELETE FROM contract_hour_policies WHERE company_id={$CO} AND operator_id={$TESTEMP}");
// E-24: البذرُ يعلن `policy_state` صراحةً — الافتراضُ `draft` **لا يسعّر** عمدًا
$gate->insert('contract_hour_policies', array('party_scope' => 'operator', 'ruling' => 'full',
    'policy_state' => 'active',
    'operator_id' => $TESTEMP, 'work_model' => 'ton', 'pay_basis' => 'ton',
    'rate' => 5, 'currency' => 'SDG', 'is_trial' => 1, 'note' => 'اختبار ① طن'));
$tonCtx = array('employee_id' => $TESTEMP, 'work_date' => '2027-03-01',
    'project_id' => 1, 'equipment_type' => 2,
    'states' => array('actual_work' => 9.0),
    'unit_type' => 'ton', 'unit_qty' => 120.0);
$r = OperatorDue::compute($conn, $CO, $tonCtx);
check($r['ok'] && $r['amount'] === 600.0, "120 طن × 5 = 600 (الناتج: {$r['amount']})");

// واقعةُ ساعةٍ لنفس المشغّل: سياسةُ نموذج «طن» خارجُ نموذج الواقعة ⇒ لا حكم
$r = OperatorDue::compute($conn, $CO, $dayCtx);
check(!$r['ok'] && strpos($r['reason'], 'لا سياسةَ') !== false,
    'سياسةُ نموذجِ طنٍّ على واقعةِ ساعة: مرشِّحُ النموذج يستبعدها — لا حكمَ ولا تلفيق');

head('① المحرّك — الحضورُ يومٌ واحدٌ لا ساعات');
$conn->query("DELETE FROM contract_hour_policies WHERE company_id={$CO} AND operator_id={$TESTEMP}");
// E-24: البذرُ يعلن `policy_state` صراحةً — الافتراضُ `draft` **لا يسعّر** عمدًا
$gate->insert('contract_hour_policies', array('party_scope' => 'operator', 'ruling' => 'full',
    'policy_state' => 'active',
    'operator_id' => $TESTEMP, 'work_model' => 'hour', 'pay_basis' => 'attendance',
    'rate' => 250, 'currency' => 'SDG', 'is_trial' => 1, 'note' => 'اختبار ① حضور'));
$r = OperatorDue::compute($conn, $CO, $dayCtx);
check($r['ok'] && $r['amount'] === 250.0, "حضورُ يومٍ = 250 مهما بلغت الساعات (الناتج: {$r['amount']})");

head('① المحرّك — المركّبُ يُتخطّى معلَنًا');
// E-24: البذرُ يعلن `policy_state` صراحةً — الافتراضُ `draft` **لا يسعّر** عمدًا
$gate->insert('contract_hour_policies', array('party_scope' => 'operator', 'ruling' => 'full',
    'policy_state' => 'active',
    'operator_id' => $TESTEMP, 'work_model' => 'hour', 'pay_basis' => 'composite',
    'rate' => 999, 'currency' => 'SDG', 'is_trial' => 1, 'note' => 'اختبار ① مركب'));
$r = OperatorDue::compute($conn, $CO, $dayCtx);
check($r['ok'] && $r['amount'] === 250.0 && count($r['skipped']) === 1
      && strpos($r['skipped'][0]['reason'], 'المركّب') !== false,
    'صفُّ composite لم يُحسب وأُعلن تخطّيه — لا صيغةَ فلا رقم');

$teardown();

// ═══════════════════════════════════════════════════════════════════════════
// ② مصدرُ القراءة القانوني — المصدران يقولان الشيءَ نفسه على الخمسين
// ═══════════════════════════════════════════════════════════════════════════
head('② مصدرُ ④ — resolveTimesheet بالمصدرين على الخمسين: صفرُ فرقٍ ماليّ');

$tsRows = $conn->query(
    "SELECT t.id FROM timesheet t
      WHERE t.`date` BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "' ORDER BY t.id")
    ->fetch_all(MYSQLI_ASSOC);
check(count($tsRows) === 50, 'الخمسون النظيفة حاضرة — شغّل seed_unit_reconcile_50.php إن نقصت');

$diffs = array();
$legalUsed = 0;
foreach ($tsRows as $row) {
    $tsId = (int) $row['id'];
    EffectFanout::$fanoutSourceOverride = 'timesheet';
    $old = EffectFanout::resolveTimesheet($conn, $tsId);
    EffectFanout::$fanoutSourceOverride = 'legal';
    $new = EffectFanout::resolveTimesheet($conn, $tsId);
    EffectFanout::$fanoutSourceOverride = null;

    if ($new['legal_source']) { $legalUsed++; }
    foreach (array('client', 'supplier') as $party) {
        $o = $old[$party]; $n = $new[$party];
        // الحكم الكامل: الصلاحية والوحدة والكمية والسعر والعملة
        $sigO = ($o['ok'] ? '1' : '0') . '|' . $o['unit'] . '|' . round((float) $o['qty'], 2)
              . '|' . $o['price'] . '|' . $o['currency'];
        $sigN = ($n['ok'] ? '1' : '0') . '|' . $n['unit'] . '|' . round((float) $n['qty'], 2)
              . '|' . $n['price'] . '|' . $n['currency'];
        if ($sigO !== $sigN) {
            $diffs[] = "TS#{$tsId} {$party}: قديم[{$sigO}] ≠ قانوني[{$sigN}]";
        }
    }
}
check($legalUsed === 50, "المصدر القانوني وُجد واستُعمل للخمسين كلِّها: {$legalUsed}/50");
check(empty($diffs), 'حكمُ العميل والمورد متطابقٌ حرفيًّا بالمصدرين — 100 مقارنة (50×2)');
foreach (array_slice($diffs, 0, 10) as $d) { fwrite(STDOUT, "      {$d}\n"); }

// صفٌّ تاريخيٌّ بلا مرآة: المصدر القانوني يرتدّ للأعمدة القديمة بلا كسر
$legacyTs = $conn->query("SELECT id FROM timesheet WHERE `date` < '2027-01-01' ORDER BY id LIMIT 1")->fetch_assoc();
EffectFanout::$fanoutSourceOverride = 'legal';
$lr = EffectFanout::resolveTimesheet($conn, (int) $legacyTs['id']);
EffectFanout::$fanoutSourceOverride = null;
check($lr !== null && $lr['legal_source'] === false,
    'صفٌّ بلا مرآة: ارتدادٌ معلَنٌ للأعمدة القديمة (legal_source=false) — تعايشٌ لا كسر');

// ═══════════════════════════════════════════════════════════════════════════
// ③ سياساتُ الخمسين — بذرٌ تجريبيٌّ ومستحقاتٌ متوقعة
// ═══════════════════════════════════════════════════════════════════════════
head('③ سياسات الخمسين — بذرٌ تجريبيٌّ موسومٌ ومستحقاتٌ محسوبة');

// المشغّلون الحقيقيون في النافذة — سياساتُهم تُبذر عطالةً (تُحذف وتُعاد)
$conn->query("DELETE FROM contract_hour_policies WHERE company_id={$CO} AND note LIKE 'RECON50%'");
$empRows = $conn->query(
    "SELECT DISTINCT employee_id FROM timesheet
      WHERE `date` BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "'
        AND employee_id IS NOT NULL AND employee_id <> ''")->fetch_all(MYSQLI_ASSOC);
$empN = count($empRows);
check($empN >= 4, "مشغّلو النافذة (بإسناد البذرة اليومي): {$empN}");

// البذرة الموحّدة (تجريبية معلَنة): تشغيلٌ فعلي 100/ساعة · استعداد 40/ساعة ·
// طن 5/طن · متر 20/متر — قيمٌ للاختبار تُستبدل من الشاشة.
// **السريان محصورٌ بنافذة المطابقة**: سياسةٌ تجريبيةٌ بلا حدودٍ طالت كلَّ
// التواريخ فولّدت مستحقًّا في حزمة بوابة التحويل (بذرتها للمشغّل 8 بتاريخٍ
// آخر) — التجربةُ تُسيَّج بنطاقها لا تُطلق على النظام.
foreach ($empRows as $er) {
    $emp = (int) $er['employee_id'];
    foreach (array(
        array('hour', 'actual', 100), array('hour', 'standby', 40),
        array('ton', 'ton', 5), array('meter', 'meter', 20),
    ) as $p) {
        // E-24: البذرُ يعلن `policy_state` صراحةً — الافتراضُ `draft` **لا يسعّر** عمدًا
$gate->insert('contract_hour_policies', array('party_scope' => 'operator', 'ruling' => 'full',
    'policy_state' => 'active',
            'operator_id' => $emp, 'work_model' => $p[0], 'pay_basis' => $p[1],
            'rate' => $p[2], 'currency' => 'SDG', 'is_trial' => 1,
            'effective_from' => WIN_FROM, 'effective_to' => WIN_TO,
            'note' => 'RECON50 — سياسة تجريبية تُستبدل من الشاشة'));
    }
}
$polN = (int) $conn->query("SELECT COUNT(*) c FROM contract_hour_policies
    WHERE company_id={$CO} AND note LIKE 'RECON50%'")->fetch_assoc()['c'];
check($polN === $empN * 4, "سياساتُ البذرة ({$empN} مشغّل × 4 أسس): {$polN}");

// المستحق المتوقع لكل صفٍّ يُحسب هنا من أعمدة الدوام مباشرةً (حسابٌ مستقلٌّ
// عن المحرّك): ساعات العمل×100 + استعداد×40، أو طن×5، أو متر×20.
$expTotal = 0.0; $gotTotal = 0.0; $rowDiffs = array(); $noRuling = 0;
$rows50 = $conn->query(
    "SELECT t.id, t.employee_id, t.executed_hours, t.standby_hours, t.tons_count, t.meters_count,
            u.id AS entry_id
       FROM timesheet t
       JOIN unit_entries u ON u.sync_uuid = CONCAT('ts:', t.id)
      WHERE t.`date` BETWEEN '" . WIN_FROM . "' AND '" . WIN_TO . "' ORDER BY t.id")
    ->fetch_all(MYSQLI_ASSOC);

foreach ($rows50 as $row) {
    $meters = (float) $row['meters_count']; $tons = (float) $row['tons_count'];
    $hours = (float) $row['executed_hours']; $stby = (float) $row['standby_hours'];
    if ($meters > 0)   { $exp = $meters * 20; }
    elseif ($tons > 0) { $exp = $tons * 5; }
    else               { $exp = $hours * 100 + $stby * 40; }
    $exp = round($exp, 2);

    $dctx = OperatorDue::ctxFromUnitEntry($conn, $CO, (int) $row['entry_id']);
    $due = OperatorDue::compute($conn, $CO, $dctx);
    $got = $due['ok'] ? $due['amount'] : 0.0;
    if (!$due['ok'] && $exp > 0) { $noRuling++; }
    if (abs($got - $exp) > 0.005) {
        $rowDiffs[] = "TS#{$row['id']}: متوقع {$exp} ≠ محرك {$got}" . (!$due['ok'] ? " ({$due['reason']})" : '');
    }
    $expTotal += $exp; $gotTotal += $got;
}
check(empty($rowDiffs) && $noRuling === 0,
    'خمسون مستحقًّا طابقت الحسابَ اليدوي صفًّا صفًّا');
foreach (array_slice($rowDiffs, 0, 10) as $d) { fwrite(STDOUT, "      {$d}\n"); }
check(abs($expTotal - $gotTotal) < 0.01,
    'المجموع: متوقع ' . round($expTotal, 2) . ' = محرك ' . round($gotTotal, 2) . ' SDG');

// يوما التوقف الكامل: عملٌ صفر ⇒ لا مستحقَ تشغيلٍ (لا استعدادَ فيهما أيضًا)
$stopRows = $conn->query(
    "SELECT t.id, u.id AS entry_id FROM timesheet t
       JOIN unit_entries u ON u.sync_uuid = CONCAT('ts:', t.id)
      WHERE t.`date` = DATE_ADD('" . WIN_FROM . "', INTERVAL 12 DAY)
        AND t.executed_hours = 0")->fetch_all(MYSQLI_ASSOC);
$stopOk = true;
foreach ($stopRows as $srw) {
    $dctx = OperatorDue::ctxFromUnitEntry($conn, $CO, (int) $srw['entry_id']);
    $due = OperatorDue::compute($conn, $CO, $dctx);
    if ($due['ok'] && $due['amount'] > 0) { $stopOk = false; }
}
check($stopOk && count($stopRows) === 2,
    'يوما التوقف الكامل: صفرُ مستحقٍّ — العطلُ ليس تشغيلًا ولا استعدادًا');

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
