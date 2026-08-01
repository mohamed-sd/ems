<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * §5 من الملحق — اختبار قبول: مواءمةُ M-03 · M-04 · M-06 مع خط الأساس التجاري
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/plan03_alignment_test.php
 *
 * ما يُثبته (PLAN-03 §5 — **توسيعًا لا هدمًا**):
 *   ① **M-03**: الفاتورةُ تقرأ **بندَ البيع** لكل سطر (`contract_line_id` ·
 *      مفتاحُ P-09) — والفاقدُه **يُعلَن غيرَ موصولٍ** ولا يُخمَّن؛ والقيمةُ
 *      **ثلاثيةٌ متسقة**: قبل الضريبة + الضريبة = شاملة.
 *   ② **M-04**: الكشفُ يحمل طبقةَ **المخطَّط** (خطة P-03 × سعر P-02 بنسخة
 *      الفترة) **ولا تدخل الرصيدَ الجاري** · و**رصيدُ المقدم المتبقي** يُعلَن
 *      من دفتر M-01 نفسِه · و**المحتجزُ بتاريخ ردّه** من سجل الضمانات P-06 ·
 *      وخطابُ الضمان **لا يظهر رقمًا** (التزامٌ خارج الميزانية).
 *   ③ **M-06**: النزاعُ **يسمّي بندَ البيع** المتنازَعَ عليه من مفتاح سطره —
 *      وغيابُه **يُعلَن** (contract_line_id = null) ولا يُخترع.
 *
 * البذرُ معزول: عقدُ 2087 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'PLAN03 §5 alignment test');

require_once dirname(__DIR__) . '/app/Services/Revenue/TaxInvoiceService.php';
require_once dirname(__DIR__) . '/app/Services/Revenue/ClientStatementService.php';
require_once dirname(__DIR__) . '/app/Services/Revenue/ClaimDisputeService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractLineService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractMonthlyPlanService.php';

use App\Services\Revenue\TaxInvoiceService as TIS;
use App\Services\Revenue\ClientStatementService as CSS;
use App\Services\Revenue\ClaimDisputeService as CDS;
use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractMonthlyPlanService as CMP;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999055;
$MARK  = 'P35T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE t FROM tax_invoices t JOIN claims c ON c.id = t.claim_id
                   WHERE c.claim_no LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM claim_lines l JOIN claims c ON c.id = l.claim_id
                   WHERE c.claim_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '%{$MARK}%'");
    $conn->query("DELETE a FROM contract_advances a JOIN contracts c ON c.id = a.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE g FROM contract_guarantees g JOIN contracts c ON c.id = g.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE p FROM contract_monthly_plan p JOIN contracts c ON c.id = p.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE ln FROM client_contract_lines ln JOIN contracts c ON c.id = ln.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE b FROM contract_baseline b JOIN contracts c ON c.id = b.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM clients WHERE client_name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ §5 — مواءمةُ M-03 · M-04 · M-06 مع خط الأساس التجاري ══\n");

head('البذر — عقدٌ ببندٍ وخطةٍ ومستخلصٍ بسطرين (موصولٍ وغيرِ موصول)');

$conn->query("INSERT INTO clients (company_id, client_code, client_name, created_at)
              VALUES ({$CO}, 'C-{$MARK}', 'عميلُ {$MARK}', NOW())");
$CLI = intval($conn->insert_id);
$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id,
              price_currency_contract, created_at)
              VALUES ({$CO}, '2087-01-01', 365, '2087-01-01', '2087-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', NOW())");
$CID = intval($conn->insert_id);

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'ton', 'description' => 'نقلُ خامٍ — بندُ ' . $MARK,
    'qty_contracted' => 1200, 'unit_price' => 10.00, 'currency' => 'USD',
    'tax_status' => 'exempt', 'tax_code_id' => null,
    'valid_from' => '2087-01-01', 'valid_to' => '2087-12-31'), $ACTOR);
$LID = (int) $r['line_id'];
$months = array();
for ($i = 1; $i <= 12; $i++) { $months[] = array('period_month' => sprintf('2087-%02d', $i), 'qty_planned' => 100); }
$sv = CMP::savePlan($conn, $gate, $CO, $LID, 1, '2087-01-01', $months, $ACTOR);
$MAR = intval($conn->query("SELECT id FROM contract_monthly_plan
                             WHERE line_id={$LID} AND period_month='2087-03'")->fetch_assoc()['id']);
check($CID > 0 && $LID > 0 && $MAR > 0, "عقدٌ #{$CID} · بندٌ #{$LID} · خطةُ 12 شهرًا × 100 طن × 10");

$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, client_id, project_id,
              period_from, period_to, currency, gross_amount, retention_amount, net_amount,
              tax_amount, state, version, created_at)
              VALUES ({$CO}, 'CLM-{$MARK}', {$CID}, {$CLI}, {$PRJ}, '2087-03-01', '2087-03-31',
                      'USD', 900, 0, 900, 0, 'approved', 1, NOW())");
$CLM = intval($conn->insert_id);
$conn->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref,
              contract_line_id, plan_period_id, work_date, equipment_ref, unit_type, qty,
              unit_price, amount, created_at)
              VALUES ({$CO}, {$CLM}, 'timesheet', 900001, {$LID}, {$MAR}, '2087-03-10',
                      'EQ-{$MARK}', 'ton', 60, 10, 600, NOW())");
$LN_LINKED = intval($conn->insert_id);
$conn->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref,
              work_date, equipment_ref, unit_type, qty, unit_price, amount, created_at)
              VALUES ({$CO}, {$CLM}, 'timesheet', 900002, '2087-03-20',
                      'EQ-{$MARK}', 'ton', 30, 10, 300, NOW())");
$LN_ORPHAN = intval($conn->insert_id);
check($CLM > 0 && $LN_LINKED > 0 && $LN_ORPHAN > 0,
      "مستخلصٌ #{$CLM} بسطرين: موصولٌ ببند البيع #{$LID} وغيرُ موصول");

// المقدمُ والضمانات — لطبقات M-04
$conn->query("INSERT INTO contract_advances (company_id, contract_id, advance_no, amount, currency,
              received_date, doc_ref, state, recorded_by, recorded_at, created_at)
              VALUES ({$CO}, {$CID}, 'ADV-{$MARK}', 2000, 'USD', '2087-01-15',
                      'DOC-{$MARK}', 'recorded', {$ACTOR}, NOW(), NOW())");
$ADV = intval($conn->insert_id);
$conn->query("INSERT INTO contract_guarantees (company_id, contract_id, kind, nature,
              deductible_from_claim, amount, currency, due_release_date, release_condition,
              state, created_by, created_at)
              VALUES ({$CO}, {$CID}, 'cash_retention', 'asset', 1, 500, 'USD', '2088-06-30',
                      'استلامٌ ابتدائي', 'active', {$ACTOR}, NOW())");
$conn->query("INSERT INTO contract_guarantees (company_id, contract_id, kind, nature,
              deductible_from_claim, amount, currency, expiry_date, state, created_by, created_at)
              VALUES ({$CO}, {$CID}, 'bank_guarantee', 'off_balance', 0, 9999, 'USD',
                      '2088-12-31', 'active', {$ACTOR}, NOW())");
check($ADV > 0, 'مقدمٌ 2,000 مسجَّلٌ · محتجزٌ 500 بتاريخ ردٍّ · خطابُ ضمانٍ 9,999 خارجَ الميزانية');

// ═══ ③ M-06 — النزاعُ يسمّي بندَ البيع ═══
head('③ M-06 — النزاعُ **يسمّي بندَ بيعه** والفاقدُه يُعلَن');

$d1 = CDS::raise($conn, $gate, $CO, $LN_LINKED,
    array('reason' => 'كميةٌ مختلَفٌ عليها', 'doc_ref' => 'OBJ-' . $MARK . '-1'), $ACTOR);
check($d1['ok'] && (int) $d1['contract_line_id'] === $LID,
      "★ نزاعُ السطر الموصول يعود بـ`contract_line_id` = {$LID} — **البندُ مسمًّى من مفتاح P-09 لا مخمَّنًا**");

$d2 = CDS::raise($conn, $gate, $CO, $LN_ORPHAN,
    array('reason' => 'سعرٌ مختلَفٌ عليه', 'doc_ref' => 'OBJ-' . $MARK . '-2'), $ACTOR);
check($d2['ok'] && $d2['contract_line_id'] === null,
      '★ ونزاعُ السطر غيرِ الموصول يعود بـnull — **الغيابُ يُعلَن ولا يُخترع بند**');

$dl = CDS::linesOf($gate, $CLM);
$byId = array();
foreach ($dl as $x) { $byId[(int) $x['id']] = $x; }
check(isset($byId[$LN_LINKED]) && (int) $byId[$LN_LINKED]['sale_line_no'] > 0
      && strpos((string) $byId[$LN_LINKED]['sale_line_desc'], $MARK) !== false,
      '★ `linesOf` تحمل بندَ البيع (رقمَه ووصفَه ونموذجَه) للسطر الموصول');
check(isset($byId[$LN_ORPHAN]) && $byId[$LN_ORPHAN]['sale_line_no'] === null,
      'وتحمل nullًا معلَنًا لغير الموصول — الشاشةُ توسمه «غيرُ موصول»');

// الحسمُ ردًّا ليعود الصافي كاملًا قبل الفوترة
$r1 = CDS::resolve($conn, $gate, $CO, $LN_LINKED, 'rejected', 'حُسم بالمطابقة', $ACTOR);
$r2 = CDS::resolve($conn, $gate, $CO, $LN_ORPHAN, 'rejected', 'حُسم بالمطابقة', $ACTOR);
check($r1['ok'] && $r2['ok'] && abs($r2['net'] - 900.0) < 0.005,
      'النزاعان حُسما ردًّا والصافي عاد 900 — (سلوكُ M-06 القائمُ لم يُهدم)');

// ═══ ① M-03 — الفاتورةُ ببند بيعها وقيمتِها الثلاثية ═══
head('① M-03 — الفاتورةُ تقرأ بندَ البيع وتُظهر القيمةَ ثلاثيًّا');

$iv = TIS::issueForClaim($conn, $gate, $CO, $CLM, array(), $ACTOR);
check($iv['ok'], '★ فاتورةٌ صدرت: ' . ($iv['serial_no'] ?? $iv['reason']));
check(abs(($iv['net'] + $iv['tax']) - $iv['total']) < 0.005,
      '★ **القيمةُ ثلاثيةٌ متسقة**: قبل الضريبة ' . $iv['net'] . ' + الضريبة ' . $iv['tax']
      . ' = شاملة ' . $iv['total']);

$il = TIS::linesOf($gate, $CLM);
$byId = array();
foreach ($il as $x) { $byId[(int) $x['id']] = $x; }
check(count($il) === 2, 'أسطرُ الفاتورة تُقرأ من مصدرها الحي (سطران)');
check(isset($byId[$LN_LINKED]) && (int) $byId[$LN_LINKED]['contract_line_id'] === $LID
      && strpos((string) $byId[$LN_LINKED]['sale_line_desc'], $MARK) !== false
      && (string) $byId[$LN_LINKED]['sale_line_model'] === 'ton'
      && (string) $byId[$LN_LINKED]['sale_tax_status'] === 'exempt',
      '★ السطرُ الموصول يحمل بندَ بيعه: الوصفَ والنموذجَ والحالةَ الضريبية — **من `client_contract_lines` لا من نص**');
check(isset($byId[$LN_ORPHAN]) && ($byId[$LN_ORPHAN]['contract_line_id'] === null
      || (int) $byId[$LN_ORPHAN]['contract_line_id'] === 0),
      '★ والسطرُ غيرُ الموصول **يُعلَن** — لا يُخفى ولا يُنسب لبندٍ بالحدس');

// ═══ ② M-04 — طبقةُ المخطَّط والمقدمُ المتبقي والمحتجزُ بتاريخه ═══
head('② M-04 — الكشفُ بطبقة المخطَّط ورصيدِ المقدم وتاريخِ ردِّ المحتجز');

$stmt = CSS::build($gate, $CLI, '2087-01-01', '2087-12-31');
check(isset($stmt['layers']['planned']), '★ طبقةُ **المخطَّط** موجودةٌ في الكشف');
check(abs($stmt['totals']['planned'] - 12000.0) < 0.005,
      '★★ **المخطَّطُ 12,000** = 1,200 طن × 10 — من `contract_monthly_plan` × سعرِ البند (نسخةُ الفترة)');
$plRow = isset($stmt['layers']['planned']['rows'][0]) ? $stmt['layers']['planned']['rows'][0] : null;
check($plRow !== null && $plRow['source_kind'] === 'monthly_plan' && !$plRow['orphan'],
      'وسطرُ المخطَّط برابط مصدره (شاشةُ الخطة الشهرية) لا يتيمًا');

// الرصيدُ الجاري لم يتغيّر بالمخطَّط: فاتورةُ 900 − تحصيلٌ 0 = 900
check(abs($stmt['totals']['balance'] - 900.0) < 0.005,
      '★★ **الرصيدُ الجاري 900 = الفواتيرُ وحدَها** — المخطَّطُ مقارِنٌ **لا يدخل الرصيد**');

$notes = implode(' | ', $stmt['notes']);
check(strpos($notes, 'لا تدخل الرصيد الجاري') !== false,
      'وإعلانُ «المخطَّطُ لا يدخل الرصيد» مكتوبٌ في الكشف');
check(strpos($notes, 'الرصيدُ المتبقي 2000') !== false,
      '★ **رصيدُ المقدم المتبقي 2,000** معلَنٌ — من دفتر M-01 (`advance_balance`) لا من حسابٍ ثانٍ');
check(strpos($notes, '2088-06-30') !== false,
      '★ والمحتجزُ **بتاريخ ردّه 2088-06-30** — من سجل الضمانات P-06');
check(strpos($notes, 'خارج الميزانية') !== false,
      '★ وخطابُ الضمان **معلَنٌ التزامًا خارج الميزانية**');
// خطابُ الضمان 9,999 لا يظهر رقمًا في أي طبقة
$found9999 = false;
foreach ($stmt['layers'] as $ly) {
    foreach ($ly['rows'] as $rw) { if (abs((float) $rw['amount'] - 9999.0) < 0.005) { $found9999 = true; } }
}
check(!$found9999, '★★ خطابُ الضمان 9,999 **لا يظهر رقمًا في أي طبقة** — «لا يُخصم ولا يظهر أصلًا» (§9-⑦ من PLAN-03)');

// المقدمةُ في طبقتها (2,000) والمحتجزُ في طبقته (من المستخلص لا من سجل الضمان — لا عدَّ مزدوجًا)
check(abs($stmt['totals']['advance'] - 2000.0) < 0.005, 'المقدمةُ 2,000 في طبقتها');
check(abs($stmt['totals']['retention'] - 0.0) < 0.005,
      'والمحتجزُ من المستخلصات وحدَها (هنا 0) — **سجلُّ الضمان يعلن التاريخَ ولا يكرّر المبلغ**');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
