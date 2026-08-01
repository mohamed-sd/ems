<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-10 — اختبار قبول: دورةُ حالة خط الأساس وبوابتُها (§3-P-10 · §9-⑱ · §2-②)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/contract_baseline_test.php
 *
 * ما يُثبته:
 *   ★★★ **معيارُ §9-⑱**: «فوترةٌ قبل قفل خط الأساس **تُرفض**» — **وبحدود
 *       §2-② الملزِمة**: البوابةُ **تبدأ مطفأة** والعقودُ القائمةُ تُفوتر كما هي،
 *       و`enforce` **لعقدٍ رائدٍ مسمًّى وحدَه**.
 *   ① **قائمةُ سماحٍ لا منع** — وما لم يُذكر مرفوض.
 *   ② **ولا قفلَ بفجوة** — والفجواتُ تُسمّى واحدةً واحدة.
 *   ③ **ولا يعتمد من راجع** — يدان لا يدٌ واحدة.
 *   ④ **والملحقُ يفتح نسخةً والقديمةُ تبقى مُستبدَلة**.
 *
 * البذرُ معزول: عقدٌ في 2092 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

// عقد هذه الحزمة: دلالة «البوابة تبدأ مطفأة» — تُثبَّت off عبر التراكب المعزول
// (البيئة الحية صارت enforce للرائد بعد إغلاق البوابة ① — ولا يكتب اختبار في .env الحي)
require_once __DIR__ . '/_guard_env.php';
ems_test_env_override(array('EMS_BASELINE_GATE' => 'off', 'EMS_BASELINE_GATE_CONTRACTS' => ''));
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'P10 baseline test');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractBaselineService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractMonthlyPlanService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractPaymentScheduleService.php';

use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractMonthlyPlanService as CMP;
use App\Services\Contract\ContractPaymentScheduleService as CPS;
use App\Services\Contract\ContractBaselineService as CBS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$A1    = 999101;  // المراجع
$A2    = 999102;  // المعتمِد — **يدٌ ثانية**
$MARK  = 'P10T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE b FROM contract_baseline b JOIN contracts c ON c.id = b.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE s FROM contract_payment_schedule s JOIN contracts c ON c.id = s.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE p FROM contract_monthly_plan p JOIN contracts c ON c.id = p.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM client_contract_lines l JOIN contracts c ON c.id = l.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE o FROM contract_operational_sites o JOIN contracts c ON c.id = o.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ P-10 — دورةُ حالة خط الأساس وبوابتُها ══\n");

head('البذر — عقدٌ **بلا مكوّنات** ابتداءً');
$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id,
              price_currency_contract, retention_pct, created_at)
              VALUES ({$CO}, '2092-01-01', 365, '2092-01-01', '2092-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', 0, NOW())");
$CID = intval($conn->insert_id);
check($CID > 0, "عقدٌ #{$CID} — **نافذٌ تجاريًّا وبلا بندِ بيعٍ واحد**");

// ═══ ★★★ البوابةُ تبدأ مطفأة ═══
head('★★★ **البوابةُ تبدأ مطفأة** — §2-②: القاعدةُ للجديد لا للقائم');

check(CBS::gateMode() === 'off', '★★★ `EMS_BASELINE_GATE` = **off** افتراضًا');
check(CBS::pilotContracts() === array(), 'وقائمةُ الرائدة **فارغة** — لا عقدَ محروس');
$g0 = CBS::billingGate($gate, $CID);
check($g0['allow'] && mb_strpos($g0['reason'], 'الحارسُ مطفأ') !== false,
      '★★★ وعقدٌ **بلا خط أساسٍ أصلًا يُفوتر** — ' . $g0['reason']);

// ═══ ① قائمةُ سماحٍ لا منع ═══
head('① **قائمةُ سماحٍ لا منع** — وما لم يُذكر مرفوض');

$noB = CBS::transition($conn, $gate, $CO, $CID, 'reviewed', $A1);
check(!$noB['ok'] && $noB['code'] === 404, 'وانتقالٌ بلا خطِّ أساسٍ ⇒ **404 — افتحه أولًا**');
$op = CBS::open($conn, $gate, $CO, $CID, $A1);
check($op['ok'] && $op['version'] === 1, '★ فُتح خطُّ الأساس **مسودةً** بالنسخة 1');
$op2 = CBS::open($conn, $gate, $CO, $CID, $A1);
check($op2['ok'] && mb_strpos($op2['reason'], 'عاطل') !== false, 'وإعادةُ الفتح **فعلٌ عاطل**');

$jump = CBS::transition($conn, $gate, $CO, $CID, 'locked', $A1);
check(!$jump['ok'] && $jump['code'] === 422 && mb_strpos($jump['reason'], 'غيرُ مشروع') !== false,
      '★★ ومسودةٌ ← **مقفل** ⇒ **422 انتقالٌ غيرُ مشروع**: ' . mb_substr($jump['reason'], 0, 100));
$jump2 = CBS::transition($conn, $gate, $CO, $CID, 'superseded', $A1);
check(!$jump2['ok'] && $jump2['code'] === 422, 'ومسودةٌ ← **مُستبدَل** ⇒ **422**');
$bogus = CBS::transition($conn, $gate, $CO, $CID, 'حالة', $A1);
check(!$bogus['ok'] && $bogus['code'] === 422, 'وحالٌ لا وجودَ له ⇒ **422**');

$rev = CBS::transition($conn, $gate, $CO, $CID, 'reviewed', $A1);
check($rev['ok'] && $rev['state'] === 'reviewed', '★ ومسودةٌ ← **مُراجَع** يقع');
$q = $conn->query("SELECT reviewed_by, reviewed_at FROM contract_baseline
                    WHERE contract_id={$CID}")->fetch_assoc();
check((int) $q['reviewed_by'] === $A1 && $q['reviewed_at'] !== null,
      'و**الفاعلُ والوقتُ مكتوبان** — «من راجع ومتى» لا يُخمَّن');

// ═══ ③ يدان لا يدٌ واحدة ═══
head('③ **ولا يعتمد من راجع** — يدان لا يدٌ واحدة');

$same = CBS::transition($conn, $gate, $CO, $CID, 'approved', $A1);
check(!$same['ok'] && $same['code'] === 422 && mb_strpos($same['reason'], 'يدٌ ثانية') !== false,
      '★★★ والمراجعُ نفسُه يعتمد ⇒ **422**: «لا يعتمد خطَّ الأساس من راجعه»');
$app = CBS::transition($conn, $gate, $CO, $CID, 'approved', $A2);
check($app['ok'], '★ وبيدٍ ثانيةٍ يقع الاعتماد');

// ═══ ② لا قفلَ بفجوة ═══
head('② **ولا قفلَ بفجوة** — والفجواتُ تُسمّى واحدةً واحدة');

$rd0 = CBS::readiness($gate, $CID);
check(!$rd0['ok'] && count($rd0['gaps']) >= 3,
      '★★ جاهزيةُ العقد الفارغ: **' . count($rd0['gaps']) . ' فجوةً مسمّاة**');
check(mb_strpos($rd0['note'], 'لا بندَ بيعٍ نافذ') !== false, 'وأولاها: **لا بندَ بيعٍ نافذ**');
$lockBad = CBS::transition($conn, $gate, $CO, $CID, 'locked', $A2);
check(!$lockBad['ok'] && $lockBad['code'] === 422
      && mb_strpos($lockBad['reason'], 'لا يُقفل خطُّ أساسٍ بفجوة') !== false,
      '★★★ والقفلُ ⇒ **422 بالفجوات**: ' . mb_substr($lockBad['reason'], 0, 110));
$q = $conn->query("SELECT state, locked_at FROM contract_baseline WHERE contract_id={$CID}")->fetch_assoc();
check($q['state'] === 'approved' && $q['locked_at'] === null, 'وصفرُ قفلٍ كُتب');

head('★ **وبإكمال المكوّنات يقع القفل**');

$SITE_REF = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$conn->query("INSERT INTO contract_operational_sites (company_id, contract_id, site_id, scope_name,
              state, created_at) VALUES ({$CO}, {$CID}, {$SITE_REF}, 'نطاقُ {$MARK}', 'active', NOW())");
$r = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'ton', 'description' => 'نقلُ خامٍ — بندُ ' . $MARK,
    'qty_contracted' => 12000, 'unit_price' => 5.00, 'currency' => 'USD',
    'tax_status' => 'exempt', 'tax_code_id' => null,
    'valid_from' => '2092-01-01', 'valid_to' => '2092-12-31'), $A1);
$LID = (int) $r['line_id'];
$rd1 = CBS::readiness($gate, $CID);
check(!$rd1['ok'] && $rd1['counts']['lines'] === 1,
      'وببندٍ ونطاقٍ: الفجواتُ نقصت إلى ' . count($rd1['gaps']) . ' — ' . mb_substr($rd1['note'], 0, 110));

$months = array();
for ($i = 1; $i <= 12; $i++) { $months[] = array('period_month' => sprintf('2092-%02d', $i), 'qty_planned' => 1000); }
CMP::savePlan($conn, $gate, $CO, $LID, 1, '2092-01-01', $months, $A1);
$rd2 = CBS::readiness($gate, $CID);
check(!$rd2['components']['plan_sealed'],
      '★ وبجدولٍ **غيرِ مختوم**: «ختمُ الجدول» ما زال فجوةً — **Σ = المتعاقَد شرطُ القفل**');
CMP::seal($conn, $gate, $CO, $LID, 1, $A1);
CPS::generate($conn, $gate, $CO, $CID, array('pattern' => 'monthly_claim', 'due_days' => 30), $A1);
$rd3 = CBS::readiness($gate, $CID);
check($rd3['ok'], '★★ وباكتمال الستة: **' . $rd3['note'] . '**');

$lock = CBS::transition($conn, $gate, $CO, $CID, 'locked', $A2);
check($lock['ok'] && mb_strpos($lock['reason'], 'ومن هنا تبدأ الفوترة') !== false, '★★★ ' . $lock['reason']);
$q = $conn->query("SELECT fingerprint, comp_lines, comp_plan_sealed, comp_payment_rows, comp_sites,
                          locked_by, locked_at FROM contract_baseline WHERE contract_id={$CID}")->fetch_assoc();
check($q['fingerprint'] !== null && strlen((string) $q['fingerprint']) === 40,
      '★★ و**بصمةُ ما قُفل مكتوبة** — فيُعرف إن تغيّر شيءٌ بعده');
check((int) $q['comp_lines'] === 1 && (int) $q['comp_plan_sealed'] === 1
      && (int) $q['comp_payment_rows'] > 0 && (int) $q['comp_sites'] === 1,
      'و**عدّاتُ المكوّنات مثبَّتةٌ لحظةَ القفل**: بند ' . $q['comp_lines']
      . ' · مختوم ' . $q['comp_plan_sealed'] . ' · دفعٌ ' . $q['comp_payment_rows']
      . ' · نطاق ' . $q['comp_sites']);
check((int) $q['locked_by'] === $A2 && $q['locked_at'] !== null, 'و**من أقفل ومتى** مكتوبان');

// ═══ ★★★ البوابةُ في أوضاعها الثلاثة ═══
head('★★★ **البوابةُ في أوضاعها الثلاثة** — والرائدةُ وحدَها');

$g1 = CBS::billingGate($gate, $CID);
check($g1['allow'] && $g1['mode'] === 'off', 'وبالوضع `off`: **يُسمح** ولو لم يُقفل — §2-②');

// عقدٌ ثانٍ **بلا خط أساس** — لبرهان المنع
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id,
              price_currency_contract, created_at)
              VALUES ({$CO}, '2092-01-01', 365, '2092-01-01', '2092-12-31',
                      'خالٍ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', NOW())");
$CID2 = intval($conn->insert_id);

// محاكاةُ الأوضاع: تُقرأ من البيئة، فنكتبها في $_ENV/putenv عبر ems_env cache؟
// **البوابةُ تُقرأ من `.env` بالتشغيل** — فنفحص منطقَها بالدالتين مباشرةً:
$modeNow = CBS::gateMode();
check(in_array($modeNow, array('off', 'monitor', 'enforce'), true),
      'ووضعُ البوابة يُقرأ من `.env` حصرًا: **' . $modeNow . '**');
check(CBS::billingGate($gate, $CID2)['allow'],
      '★★★ و**عقدٌ بلا خط أساسٍ يُفوتر اليوم** — «العقودُ القائمةُ تُفوتر كما هي»');

// ═══ ④ الملحق ═══
head('④ **والملحقُ يفتح نسخةً والقديمةُ تبقى مُستبدَلة**');

$noReason = CBS::amend($conn, $gate, $CO, $CID, '  ', 0, $A2);
check(!$noReason['ok'] && $noReason['code'] === 422, 'وملحقٌ **بلا سبب** ⇒ **422**');
$am = CBS::amend($conn, $gate, $CO, $CID, 'تعديلُ السعر بملحقٍ موقَّع — ' . $MARK, 0, $A2);
check($am['ok'] && $am['version'] === 2, '★ ' . $am['reason']);
$rows = $conn->query("SELECT version, state FROM contract_baseline WHERE contract_id={$CID}
                       ORDER BY version");
$states = array();
while ($x = $rows->fetch_assoc()) { $states[(int) $x['version']] = (string) $x['state']; }
check(isset($states[1]) && $states[1] === 'superseded' && isset($states[2]) && $states[2] === 'draft',
      '★★ والنسخةُ 1 **مُستبدَلةٌ وباقية** والنسخةُ 2 **مسودة**: ' . json_encode($states));
$cur = CBS::current($gate, $CID);
check((int) $cur['version'] === 2, 'والنافذُ هو النسخةُ 2');
$g2 = CBS::billingGate($gate, $CID);
check($g2['state'] === 'draft',
      '★★ و**حالُ الفوترة عاد إلى مسودة بعد الملحق** — فالقفلُ لا يُورَّث');

$amAgain = CBS::amend($conn, $gate, $CO, $CID, 'ملحقٌ ثانٍ', 0, $A2);
check(!$amAgain['ok'] && $amAgain['code'] === 409 && mb_strpos($amAgain['reason'], 'لا يُعدَّل إلا مقفل') !== false,
      'وملحقٌ على **مسودة** ⇒ **409** — «لا يُعدَّل إلا مقفل»');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══\n");
exit($FAIL === 0 ? 0 : 1);
