<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-09-② — اختبار قبول: المسارُ الزمني (ENT-01 §3-① · §4 · PLAN-01 §6.1-②)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/payroll_time_path_test.php
 *
 * معيارُ القبول النصّي: «**عشرةُ موظفين يدويًّا = آليًّا بلا فرق**».
 *
 * فالاختبارُ يبذر **عشرَ حالاتٍ متمايزة** ويحمل لكلٍّ **رقمَها المحسوبَ يدويًّا
 * في نصّه** (لا مشتقًّا من الكود)، ثم يقارن. أيُّ فرقٍ = فشل.
 *
 * الفترة: 2047-03-01 → 2047-03-31 = **31 يومًا** (كلُّ الحسابات أدناه عليها).
 *
 *  #   الحالة                          الحسابُ اليدوي                = المتوقَّع
 *  ①  شهرٌ كاملٌ بسيط                  3100                          = 3100.00
 *  ②  أساسيٌّ + بدلُ نسبةٍ 20٪          3100 + 620                    = 3720.00
 *  ③  بدءٌ 03-16 (16 يومًا)            3100 × 16/31                  = 1600.00
 *  ④  نهايةٌ 03-10 (10 أيام)           3100 × 10/31                  = 1000.00
 *  ⑤  بدلٌ يسري 03-21 (11 يومًا)       3100 + 310 × 11/31            = 3210.00
 *  ⑥  غيابٌ مصنَّفٌ يُخصم 5 أيام        3100 − (3100/31)×5            = 2600.00
 *  ⑦  إجازةٌ مصنَّفةٌ مدفوعةٌ 7 أيام     3100 − 0                      = 3100.00
 *  ⑧  غيابٌ **غيرُ مصنَّف** 4 أيام      3100 − 0 (يُعلَن لا يُخصم)      = 3100.00
 *  ⑨  إضافيٌّ 10 ساعاتٍ بمعدل 25       3100 + 250                    = 3350.00
 *  ⑩  إضافيٌّ 8 ساعاتٍ **بلا معدل**    3100 + 0 (يُعلَن لا يُخترع)     = 3100.00
 *
 * البذرُ معزول: عقودٌ بوسم H092T وتواريخُ 2047 — تُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/EmployeeContractService.php';
require_once dirname(__DIR__) . '/app/Services/Payroll/PayrollRunService.php';
require_once dirname(__DIR__) . '/app/Services/Payroll/TimePathService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\EmployeeContractService as ECS;
use App\Services\Payroll\PayrollRunService as PRS;
use App\Services\Payroll\TimePathService as TPS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999952;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

const TAG = 'H092T المسارُ الزمني';
const P_FROM = '2047-03-01';
const P_TO   = '2047-03-31';
const P_DAYS = 31;

$teardown = function () use ($conn) {
    $ids = array();
    $r = $conn->query("SELECT id FROM employee_contracts WHERE relation_type = '" . TAG . "'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM payroll_lines WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM payroll_run_blocks WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM pay_components WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM contract_snapshots WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM employee_contracts WHERE id = {$cid}");
    }
    $conn->query("DELETE t FROM payroll_time_inputs t JOIN payroll_runs r ON r.id = t.run_id
                   WHERE r.period_from = '" . P_FROM . "'");
    $conn->query("DELETE l FROM payroll_lines l JOIN payroll_runs r ON r.id = l.run_id
                   WHERE r.period_from = '" . P_FROM . "'");
    $conn->query("DELETE b FROM payroll_run_blocks b JOIN payroll_runs r ON r.id = b.run_id
                   WHERE r.period_from = '" . P_FROM . "'");
    $conn->query("DELETE FROM payroll_runs WHERE period_from = '" . P_FROM . "'");
    $conn->query("DELETE FROM worker_leave_absence WHERE reason LIKE 'H092T%'");
    $conn->query("DELETE FROM payroll_absence_types WHERE label_ar LIKE 'H092T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-09-② — المسارُ الزمني: عشرةُ موظفين يدويًّا = آليًّا ══\n");

// ═══ البذر ═══
head('البذر — عشرةُ عقودٍ دائمةٍ بحالاتٍ متمايزة');
$emps = array();
$r = $conn->query("SELECT id FROM employees WHERE company_id={$CO} ORDER BY id LIMIT 10");
while ($x = $r->fetch_assoc()) { $emps[] = intval($x['id']); }
check(count($emps) === 10, 'عشرةُ أشخاصٍ من السجل الحي');
$pm = intval($conn->query("SELECT id FROM pay_models WHERE code='fixed_allowances' LIMIT 1")->fetch_assoc()['id']);

/**
 * ينشئ عقدًا دائمًا **مسودةً** ويعيد معرّفَه.
 * ⚠ لا يُفعَّل هنا: المكوّناتُ تُضاف على المحرَّر حصرًا (حارسُ H-08-② — النافذُ
 * 423 «بملحق»)، فالتفعيلُ بعد اكتمال المكوّنات بـ`$activate`.
 */
$mkContract = function ($empId, $start, $end) use ($conn, $gate, $CO, $ACTOR, $pm) {
    $r = ECS::createHead($conn, $gate, $CO, array(
        'employee_id' => $empId, 'category' => 'permanent', 'pay_model_id' => $pm,
        'relation_type' => TAG, 'start_date' => $start, 'end_date' => $end), $ACTOR);
    return !empty($r['ok']) ? intval($r['id']) : 0;
};
/** يجعل العقدَ مقروءًا في الاحتساب بعد اكتمال مكوّناته. */
$activate = function ($cid) use ($conn) {
    if ((int) $cid > 0) { $conn->query("UPDATE employee_contracts SET state='active' WHERE id=" . (int) $cid); }
};
$addComp = function ($cid, $data) use ($conn, $gate, $CO, $ACTOR) {
    $r = ECS::addComponent($conn, $gate, $CO, $cid, $data, $ACTOR);
    return !empty($r['ok']) ? intval($r['id']) : 0;
};

$C = array();
// ① شهرٌ كامل
$C[1] = $mkContract($emps[0], '2047-01-01', '2047-12-31');
$addComp($C[1], array('component_type' => 'basic', 'calc_method' => 'fixed_amount',
    'value' => 3100, 'periodicity' => 'monthly', 'valid_from' => '2047-01-01'));
// ② + بدلُ نسبةٍ 20٪
$C[2] = $mkContract($emps[1], '2047-01-01', '2047-12-31');
$addComp($C[2], array('component_type' => 'basic', 'calc_method' => 'fixed_amount',
    'value' => 3100, 'periodicity' => 'monthly', 'valid_from' => '2047-01-01'));
$addComp($C[2], array('component_type' => 'housing', 'calc_method' => 'pct_basic',
    'rate' => 20, 'periodicity' => 'monthly', 'valid_from' => '2047-01-01'));
// ③ بدءٌ منتصفَ الشهر
$C[3] = $mkContract($emps[2], '2047-03-16', '2047-12-31');
$addComp($C[3], array('component_type' => 'basic', 'calc_method' => 'fixed_amount',
    'value' => 3100, 'periodicity' => 'monthly', 'valid_from' => '2047-03-16'));
// ④ نهايةٌ منتصفَ الشهر
$C[4] = $mkContract($emps[3], '2047-01-01', '2047-03-10');
$addComp($C[4], array('component_type' => 'basic', 'calc_method' => 'fixed_amount',
    'value' => 3100, 'periodicity' => 'monthly', 'valid_from' => '2047-01-01'));
// ⑤ بدلٌ يسري متأخرًا
$C[5] = $mkContract($emps[4], '2047-01-01', '2047-12-31');
$addComp($C[5], array('component_type' => 'basic', 'calc_method' => 'fixed_amount',
    'value' => 3100, 'periodicity' => 'monthly', 'valid_from' => '2047-01-01'));
$addComp($C[5], array('component_type' => 'transport', 'calc_method' => 'fixed_amount',
    'value' => 310, 'periodicity' => 'monthly', 'valid_from' => '2047-03-21'));
// ⑥⑦⑧ غيابٌ بأنواعه
foreach (array(6, 7, 8) as $i) {
    $C[$i] = $mkContract($emps[$i - 1], '2047-01-01', '2047-12-31');
    $addComp($C[$i], array('component_type' => 'basic', 'calc_method' => 'fixed_amount',
        'value' => 3100, 'periodicity' => 'monthly', 'valid_from' => '2047-01-01'));
}
// ⑨ إضافيٌّ بمعدلٍ من العقد
$C[9] = $mkContract($emps[8], '2047-01-01', '2047-12-31');
$addComp($C[9], array('component_type' => 'basic', 'calc_method' => 'fixed_amount',
    'value' => 3100, 'periodicity' => 'monthly', 'valid_from' => '2047-01-01'));
$addComp($C[9], array('component_type' => 'other_allowance', 'calc_method' => 'per_hour',
    'rate' => 25, 'periodicity' => 'monthly', 'valid_from' => '2047-01-01'));
// ⑩ إضافيٌّ بلا معدل
$C[10] = $mkContract($emps[9], '2047-01-01', '2047-12-31');
$addComp($C[10], array('component_type' => 'basic', 'calc_method' => 'fixed_amount',
    'value' => 3100, 'periodicity' => 'monthly', 'valid_from' => '2047-01-01'));

// التفعيلُ بعد اكتمال المكوّنات — لا قبله (حارسُ «النافذُ لا يُعدَّل مباشرةً»)
foreach ($C as $cid) { $activate($cid); }
check(count(array_filter($C)) === 10, 'عشرةُ عقودٍ نافذةٍ بمكوّناتها');
$compCount = intval($conn->query("SELECT COUNT(*) n FROM pay_components pc
    JOIN employee_contracts c ON c.id = pc.contract_id
   WHERE c.relation_type = '" . TAG . "'")->fetch_assoc()['n']);
// 10 أساسيّ + بدلُ نسبةٍ (②) + بدلٌ متأخرُ السريان (⑤) + per_hour (⑨) = 13
check($compCount === 13, "المكوّناتُ المبذورة: {$compCount} (10 أساسيّ + نسبةٌ + بدلٌ متأخر + per_hour)");

// كتالوجُ الغياب: نوعٌ يُخصم · نوعٌ مدفوع · (والثالثُ يبقى غيرَ مصنَّف)
$conn->query("INSERT INTO payroll_absence_types (company_id, event_type, deducts, deduct_percent, label_ar)
              VALUES ({$CO}, 'H092T-غياب', 1, 100.00, 'H092T غيابٌ يُخصم')");
$conn->query("INSERT INTO payroll_absence_types (company_id, event_type, deducts, deduct_percent, label_ar)
              VALUES ({$CO}, 'H092T-إجازة', 0, 100.00, 'H092T إجازةٌ مدفوعة')");
// ⑥ خمسةُ أيامِ غيابٍ مصنَّف (03-05 → 03-09)
$conn->query("INSERT INTO worker_leave_absence (company_id, employee_id, event_class, event_type,
              date_from, date_to, state, reason) VALUES ({$CO}, {$emps[5]}, 'طارئ', 'H092T-غياب',
              '2047-03-05', '2047-03-09', 'معتمد', 'H092T ⑥')");
// ⑦ سبعةُ أيامِ إجازةٍ مدفوعة (03-10 → 03-16)
$conn->query("INSERT INTO worker_leave_absence (company_id, employee_id, event_class, event_type,
              date_from, date_to, state, reason) VALUES ({$CO}, {$emps[6]}, 'مخطّط', 'H092T-إجازة',
              '2047-03-10', '2047-03-16', 'معتمد', 'H092T ⑦')");
// ⑧ أربعةُ أيامٍ بنوعٍ غيرِ مصنَّف (03-02 → 03-05)
$conn->query("INSERT INTO worker_leave_absence (company_id, employee_id, event_class, event_type,
              date_from, date_to, state, reason) VALUES ({$CO}, {$emps[7]}, 'طارئ', 'H092T-مجهول',
              '2047-03-02', '2047-03-05', 'معتمد', 'H092T ⑧')");
ok('كتالوجُ الغياب مبذورٌ (يُخصم · مدفوع) — والثالثُ **متروكٌ بلا تصنيفٍ عمدًا**');

// ═══ الدورة ═══
head('الدورةُ — فتحٌ فربطُ لقطاتٍ فمسارٌ زمني');
$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => P_FROM, 'period_to' => P_TO, 'category_filter' => 'permanent'), $ACTOR);
check($r['ok'], 'الدورةُ فُتحت (31 يومًا)');
$RUN = intval($r['run_id']);

// مدخلا الإضافي — بمستنديهما (⑨ و⑩)
$ri = TPS::recordTimeInput($conn, $gate, $CO, $RUN, array(
    'person_id' => $emps[8], 'kind' => 'overtime_hours', 'qty' => 10, 'doc_ref' => 'H092T/OT/9'), $ACTOR);
check($ri['ok'], '⑨ عشرُ ساعاتِ إضافيٍّ بمستندها');
$ri = TPS::recordTimeInput($conn, $gate, $CO, $RUN, array(
    'person_id' => $emps[9], 'kind' => 'overtime_hours', 'qty' => 8, 'doc_ref' => 'H092T/OT/10'), $ACTOR);
check($ri['ok'], '⑩ ثمانُ ساعاتٍ بمستندها');
$ri = TPS::recordTimeInput($conn, $gate, $CO, $RUN, array(
    'person_id' => $emps[0], 'kind' => 'overtime_hours', 'qty' => 3, 'doc_ref' => ''), $ACTOR);
check(!$ri['ok'] && $ri['code'] === 422, 'ومدخلٌ **بلا مستندٍ → 422** («ولا خصمَ بلا مستند» والزيادةُ مثلُه)');

$r = PRS::bindSnapshots($conn, $gate, $CO, $RUN, $ACTOR);
check($r['ok'], "ربطُ اللقطات: {$r['lines']} سطرًا · {$r['blocked']} مانعًا · {$r['excluded']} مستبعَدًا");

$r = TPS::compute($conn, $gate, $CO, $RUN, $ACTOR);
check($r['ok'], "المسارُ الزمني: {$r['reason']}");

// ═══ المقارنةُ اليدوية ═══
head('**عشرةُ موظفين يدويًّا = آليًّا بلا فرق**');

/** صافي شخصٍ من الدورة (المكوّناتُ + الإضافيُّ − الخصم). */
$netOf = function ($personId) use ($conn, $RUN) {
    $row = $conn->query("SELECT ROUND(COALESCE(SUM(amount),0),2) s FROM payroll_lines
                          WHERE run_id={$RUN} AND person_id={$personId}")->fetch_assoc();
    return round(floatval($row['s']), 2);
};

// الأرقامُ اليمنى محسوبةٌ يدويًّا في ترويسة الملف — لا تُشتقّ من الكود
$expected = array(
    1  => array(3100.00, 'شهرٌ كاملٌ بسيط: 3100'),
    2  => array(3720.00, 'أساسيٌّ 3100 + بدلُ 20٪ = 620 ⇒ 3720'),
    3  => array(1600.00, 'بدءٌ 03-16 ⇒ 3100 × 16/31 = 1600'),
    4  => array(1000.00, 'نهايةٌ 03-10 ⇒ 3100 × 10/31 = 1000'),
    5  => array(3210.00, 'بدلٌ يسري 03-21 ⇒ 3100 + 310 × 11/31 = 3210'),
    6  => array(2600.00, 'غيابٌ مصنَّفٌ 5 أيام ⇒ 3100 − 500 = 2600'),
    7  => array(3100.00, 'إجازةٌ مدفوعةٌ 7 أيام ⇒ لا خصم = 3100'),
    8  => array(3100.00, 'غيابٌ **غيرُ مصنَّف** ⇒ يُعلَن ولا يُخصم = 3100'),
    9  => array(3350.00, 'إضافيٌّ 10 × 25 (معدلُ العقد) ⇒ 3350'),
    10 => array(3100.00, 'إضافيٌّ 8 ساعاتٍ **بلا معدلٍ في العقد** ⇒ لا يُخترع = 3100'),
);
$diffs = 0;
foreach ($expected as $i => $exp) {
    $actual = $netOf($emps[$i - 1]);
    $okRow = abs($actual - $exp[0]) < 0.005;
    if (!$okRow) { $diffs++; }
    check($okRow, sprintf('%02d) %s — آليًّا %s', $i, $exp[1], number_format($actual, 2)));
}
check($diffs === 0, "**صفرُ فرقٍ في العشرة** (الفروق: {$diffs})");

// ═══ البراهينُ الجانبية ═══
head('البراهينُ الجانبية');
$prorated = $conn->query("SELECT entitled_days, period_days, amount FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$emps[2]} AND line_kind='component'")->fetch_assoc();
check($prorated && floatval($prorated['entitled_days']) === 16.0 && floatval($prorated['period_days']) === 31.0,
      '**كلُّ رقمٍ ينقر لمصدره**: السطرُ يحمل 16/31 فيُرى لماذا صار جزءًا من الشهر');

$ded = $conn->query("SELECT amount, qty, note, line_kind FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$emps[5]} AND line_kind='absence_deduction'")->fetch_assoc();
check($ded && floatval($ded['amount']) === -500.0 && floatval($ded['qty']) === 5.0
      && strpos($ded['note'], 'غياب#') !== false,
      '**الخصمُ سطرٌ ظاهرٌ سالبٌ بمرجعه** (−500 · 5 أيام · رقمُ واقعة الغياب) لا نقصٌ صامت');

$undecl = $conn->query("SELECT amount, calc_state, note FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$emps[7]} AND line_kind='absence_deduction'")->fetch_assoc();
check($undecl && $undecl['amount'] === null && $undecl['calc_state'] === 'pending_slice'
      && strpos($undecl['note'], 'غيرُ مصنَّف') !== false,
      'والنوعُ غيرُ المصنَّف **سطرٌ معلَنٌ بلا مبلغ** — ينتظر قرارَ التصنيف ولا يُخصم تخمينًا');

$otNo = $conn->query("SELECT amount, calc_state, note FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$emps[9]} AND line_kind='overtime'")->fetch_assoc();
check($otNo && $otNo['amount'] === null && strpos($otNo['note'], 'لا معدلَ ساعةٍ في العقد') !== false,
      'وإضافيٌّ بلا معدلٍ في العقد: **يُعلَن ولا يصير مالًا باجتهاد** (§4 نصًّا)');

$otYes = $conn->query("SELECT amount, rate FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$emps[8]} AND line_kind='overtime'")->fetch_assoc();
check($otYes && floatval($otYes['amount']) === 250.0 && floatval($otYes['rate']) === 25.0,
      'وبمعدلٍ في العقد: 250 بمعدله 25 — «من العقد لا من اجتهاد»');

// المسارُ المشروعيُّ لا يُمسّ هنا
$proj = $conn->query("SELECT COUNT(*) n FROM payroll_lines
    WHERE run_id={$RUN} AND path='project' AND calc_state='computed'")->fetch_assoc();
check(intval($proj['n']) === 0, 'وصفرُ سطرٍ مشروعيٍّ احتُسب هنا — المسارُ الإنتاجيُّ بيتُه الشريحة ③');

// ═══ العطالة ═══
head('العطالة — إعادةُ التشغيل لا تضاعف');
$before = $netOf($emps[5]);
TPS::compute($conn, $gate, $CO, $RUN, $ACTOR);
$after = $netOf($emps[5]);
check(abs($before - $after) < 0.005, "إعادةُ المسار الزمني: الصافي ثابتٌ ({$before} = {$after})");
$dedCount = intval($conn->query("SELECT COUNT(*) n FROM payroll_lines
    WHERE run_id={$RUN} AND person_id={$emps[5]} AND line_kind='absence_deduction'")->fetch_assoc()['n']);
check($dedCount === 1, 'وسطرُ الخصم واحدٌ لا اثنان (المولَّداتُ تُبنى من الصفر)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
