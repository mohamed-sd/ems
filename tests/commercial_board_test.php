<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-12 — اختبار قبول: اللوحةُ التجارية (§3-P-12 · §4 شرطُ إغلاق الموجة)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/commercial_board_test.php
 *
 * ما يُثبته:
 *   ★★★ **شرطُ إغلاق الموجة (§4)**: «`P-12` تعرض **الأرقام الأربعة** لعقدٍ
 *       رائدٍ واحدٍ على الأقل، **وكلُّ فجوةٍ لها مالكٌ مسمًّى**».
 *   ① **الأربعةُ من بيوتها** — ولا رقمَ يُخترع في اللوحة.
 *   ② **وثلاثُ فجواتٍ لكلٍّ مالكٌ مسمًّى** بدوره وسؤاله.
 *   ③ **ومصداقيةُ اللوحة تُعلَن مع أرقامها** — والمنفَّذُ الناقصُ **يُوسَم**.
 *   ④ **ولا تُجمع عملتان في رقم**.
 *
 * البذرُ معزول: عقودُ 2094 — تُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'P12 board test');

require_once dirname(__DIR__) . '/app/Services/Contract/CommercialBoardService.php';

use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractMonthlyPlanService as CMP;
use App\Services\Contract\PlanActualLinkService as PAL;
use App\Services\Contract\CommercialBoardService as CBD;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999121;
$MARK  = 'P12T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE u FROM unit_entries u JOIN contracts c ON c.id = u.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM claim_lines l JOIN claims c ON c.id = l.claim_id
                   WHERE c.claim_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_receivables WHERE doc_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE p FROM contract_monthly_plan p JOIN contracts c ON c.id = p.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE ln FROM client_contract_lines ln JOIN contracts c ON c.id = ln.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE b FROM contract_baseline b JOIN contracts c ON c.id = b.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ P-12 — اللوحةُ التجارية للعقود ══\n");

head('② **ثلاثُ فجواتٍ لكلٍّ مالكٌ مسمًّى** — بدوره وسؤاله');

check(count(CBD::GAP_OWNERS) === 3, '★ **ثلاثُ فجواتٍ** لا أكثرَ ولا أقل');
foreach (CBD::GAP_OWNERS as $k => $g) {
    check(trim((string) $g['owner']) !== '' && trim((string) $g['role']) !== ''
          && trim((string) $g['question']) !== '',
          '★★ «' . $g['label'] . '» ⇐ **' . $g['owner'] . '** (دور ' . $g['role']
          . ') · «' . $g['question'] . '»');
}

head('البذر — عقدٌ بمخطَّطٍ ومنفَّذٍ ومفوترٍ ومحصَّل');

$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id,
              price_currency_contract, created_at)
              VALUES ({$CO}, '2094-01-01', 365, '2094-01-01', '2094-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', NOW())");
$CID = intval($conn->insert_id);

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'ton', 'description' => 'نقلُ خامٍ — بندُ ' . $MARK,
    'qty_contracted' => 12000, 'unit_price' => 5.00, 'currency' => 'USD',
    'tax_status' => 'exempt', 'tax_code_id' => null,
    'valid_from' => '2094-01-01', 'valid_to' => '2094-12-31'), $ACTOR);
$LID = (int) $r['line_id'];
$months = array();
for ($i = 1; $i <= 12; $i++) { $months[] = array('period_month' => sprintf('2094-%02d', $i), 'qty_planned' => 1000); }
CMP::savePlan($conn, $gate, $CO, $LID, 1, '2094-01-01', $months, $ACTOR);
$MAR = intval($conn->query("SELECT id FROM contract_monthly_plan
                             WHERE line_id={$LID} AND period_month='2094-03'")->fetch_assoc()['id']);

// منفَّذٌ 800 طنًّا **موصولًا بالمفتاح** (P-09)
$conn->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, contract_id,
              contract_line_id, plan_period_id, unit_type, qty, record_basis, capacity_flag, state,
              revision_no, current_round, created_at, updated_at)
              VALUES ({$CO}, 'UE-{$MARK}-1', '2094-03-10', {$PRJ}, {$CID},
                      {$LID}, {$MAR}, 'ton', 800, 'contract', 0, 'sales_approved', 1, 1, NOW(), NOW())");
// ووحدةٌ **غيرُ موصولة** — لبرهان المصداقية
$conn->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, contract_id,
              unit_type, qty, record_basis, capacity_flag, state, revision_no, current_round,
              created_at, updated_at)
              VALUES ({$CO}, 'UE-{$MARK}-2', '2094-03-20', {$PRJ}, {$CID},
                      'ton', 150, 'contract', 0, 'sales_approved', 1, 1, NOW(), NOW())");

// مفوترٌ 3,000 ومحصَّلٌ 1,800
$conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
              project_id, amount, currency, fx_rate_recognized, base_amount, collected,
              due_date, state, created_at)
              VALUES ({$CO}, 1, 'invoice', 'INV-{$MARK}', {$PRJ}, 3000, 'USD', 1.0, 3000, 1800,
                      '2094-04-30', 'partial', NOW())");
$RID = intval($conn->insert_id);
$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, client_id, project_id,
              period_from, period_to, currency, gross_amount, retention_amount, net_amount,
              tax_amount, state, version, receivable_id, created_at)
              VALUES ({$CO}, 'CLM-{$MARK}', {$CID}, 1, {$PRJ}, '2094-03-01', '2094-03-31',
                      'USD', 3000, 0, 3000, 0, 'invoiced', 1, {$RID}, NOW())");
check($CID > 0 && $LID > 0 && $RID > 0,
      "عقدٌ #{$CID} · مخطَّطٌ 12,000×5 · منفَّذٌ 800 موصولٌ + 150 غيرُ موصول · مفوترٌ 3,000 · محصَّلٌ 1,800");

// ═══ ★★★ الأرقامُ الأربعة ═══
head('★★★ **الأرقامُ الأربعةُ في سطرٍ واحد** — كلٌّ من بيته');

$row = CBD::row($gate, $CID);
check($row['ok'], '★ سطرُ اللوحة: ' . mb_substr($row['note'], 0, 140));
check(abs($row['planned'] - 60000.0) < 0.005,
      '★★★ **المخطَّطُ 60,000** = 12,000 × 5 — من `contract_monthly_plan` × سعرِ البند');
check(abs($row['executed'] - 4000.0) < 0.005,
      '★★★ و**المنفَّذُ 4,000** = 800 × 5 — من `unit_entries` **بمفتاح P-09**');
check(abs($row['billed'] - 3000.0) < 0.005, '★★★ و**المفوتَرُ 3,000** — من `claims`');
check(abs($row['collected'] - 1800.0) < 0.005, '★★★ و**المحصَّلُ 1,800** — من `fin_receivables`');
check($row['currency'] === 'USD', 'وبعملةٍ واحدةٍ معلَنة: USD');

head('★★★ **وكلُّ فجوةٍ بمالكها**');

check(abs($row['gaps']['execution']['value'] + 56000.0) < 0.005
      && $row['gaps']['execution']['owner'] === 'التشغيل',
      '★★★ **فجوةُ التنفيذ −56,000** ⇐ مالكُها **التشغيل**');
check(abs($row['gaps']['billing']['value'] + 1000.0) < 0.005
      && $row['gaps']['billing']['owner'] === 'المبيعات',
      '★★★ و**فجوةُ الفوترة −1,000** ⇐ مالكُها **المبيعات**');
check(abs($row['gaps']['collection']['value'] + 1200.0) < 0.005
      && $row['gaps']['collection']['owner'] === 'المالية',
      '★★★ و**فجوةُ التحصيل −1,200** ⇐ مالكُها **المالية**');
$named = 0;
foreach ($row['gaps'] as $g) { if (trim((string) $g['owner']) !== '') { $named++; } }
check($named === 3, '★★★ و**الثلاثُ كلُّها بمالكٍ مسمًّى** — «وكلُّ فجوةٍ لها مالكٌ مسمًّى» (§4)');

// ═══ ③ المصداقية ═══
head('③ **ومصداقيةُ اللوحة تُعلَن مع أرقامها**');

check($row['credible'] === false,
      '★★★ و**السطرُ موسومٌ غيرَ تامٍّ** — لأن وحدةً واحدةً غيرُ موصولة');
check(mb_strpos($row['note'], 'والمنفَّذُ ناقصٌ يبدو تامًّا') !== false,
      '★★★ والسببُ **مكتوبٌ في السطر نفسِه**: «1 وحدةً غيرَ موصولةٍ — والمنفَّذُ ناقصٌ يبدو تامًّا»');
check((int) $row['coverage']['units_total'] === 2 && (int) $row['coverage']['units_linked'] === 1,
      'وتغطيةُ الوحدات معروضةٌ عدًّا: 1/2');

$UE2 = intval($conn->query("SELECT id FROM unit_entries WHERE entry_no='UE-{$MARK}-2'")->fetch_assoc()['id']);
PAL::linkUnit($conn, $gate, $CO, $UE2, array(), $ACTOR, true);
$row2 = CBD::row($gate, $CID);
check($row2['credible'] === true && abs($row2['executed'] - 4750.0) < 0.005,
      '★★★ وبوصلها: **المنفَّذُ صار 4,750** و**السطرُ صار تامًّا** — والرقمُ الناقصُ كان يبدو تامًّا');
check(abs($row2['gaps']['billing']['value'] + 1750.0) < 0.005,
      'و**فجوةُ الفوترة تغيّرت إلى −1,750** — فالمصداقيةُ ليست زينةً بل رقمًا');

// ═══ ★★★ شرطُ إغلاق الموجة ═══
head('★★★ **شرطُ إغلاق الموجة (§4)** — عقدٌ رائدٌ واحدٌ على الأقل');

$cl = CBD::closureCheck($gate);
check($cl['ok'], '★★★ ' . $cl['reason']);
$found = false;
foreach ($cl['pilots'] as $p) { if ((int) $p['contract_id'] === $CID) { $found = true; } }
check($found, '★★★ و**العقدُ المبذورُ ضمن الرائدة** بأرقامه الأربعة');
$p = null;
foreach ($cl['pilots'] as $x) { if ((int) $x['contract_id'] === $CID) { $p = $x; } }
check($p !== null && $p['planned'] > 0 && $p['executed'] > 0 && $p['billed'] > 0 && $p['collected'] > 0,
      '★★★ و**الأربعةُ كلُّها غيرُ صفرية**: '
      . ($p ? ($p['planned'] . ' · ' . $p['executed'] . ' · ' . $p['billed'] . ' · ' . $p['collected']) : ''));
check(mb_strpos($cl['reason'], 'خطَّ الأساس صار حيًّا لا وثيقة') !== false,
      '★★★ و**«دليلُ أن خطَّ الأساس صار حيًّا لا وثيقة»** معلَنٌ في النتيجة نفسِها');

// ═══ ④ لا تُجمع عملتان ═══
head('④ **ولا تُجمع عملتان في رقم**');

$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id,
              price_currency_contract, created_at)
              VALUES ({$CO}, '2094-01-01', 365, '2094-01-01', '2094-12-31',
                      'مزدوجُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', NOW())");
$CID2 = intval($conn->insert_id);
$conn->query("INSERT INTO client_contract_lines (company_id, contract_id, line_no, pricing_model,
              description, qty_contracted, unit_price, currency, valid_from, valid_to,
              tax_status, state, created_at)
              VALUES ({$CO}, {$CID2}, 1, 'ton', 'بالدولار {$MARK}', 100, 5, 'USD',
                      '2094-01-01', '2094-12-31', 'exempt', 'active', NOW()),
                     ({$CO}, {$CID2}, 2, 'hour', 'بالجنيه {$MARK}', 100, 500, 'SDG',
                      '2094-01-01', '2094-12-31', 'exempt', 'active', NOW())");
$L1 = intval($conn->query("SELECT id FROM client_contract_lines WHERE contract_id={$CID2}
                            AND line_no=1")->fetch_assoc()['id']);
$L2 = intval($conn->query("SELECT id FROM client_contract_lines WHERE contract_id={$CID2}
                            AND line_no=2")->fetch_assoc()['id']);
CMP::savePlan($conn, $gate, $CO, $L1, 1, '2094-01-01',
    array(array('period_month' => '2094-01', 'qty_planned' => 100)), $ACTOR);
CMP::savePlan($conn, $gate, $CO, $L2, 1, '2094-01-01',
    array(array('period_month' => '2094-01', 'qty_planned' => 100)), $ACTOR);
$mix = CBD::row($gate, $CID2);
check(!$mix['ok'] && mb_strpos($mix['note'], 'ولا تُجمع عملتان في رقم') !== false,
      '★★★ وعقدٌ بعملتين ⇒ **السطرُ يمتنع ويُعلن السبب**: ' . mb_substr($mix['note'], 0, 100));
check($mix['credible'] === false, 'و**موسومٌ غيرَ تام** — فلا يُقرأ رقمُه على أنه صحيح');

// ═══ اللوحةُ والمجاميع ═══
head('★ **واللوحةُ سطرٌ لكل عقدٍ نافذ** — والمجاميعُ بعملةٍ عملة');

$board = CBD::board($gate, true, 100);
check(count($board) > 0, 'اللوحةُ تُبنى: ' . count($board) . ' عقدًا نافذًا');
$mine = null;
foreach ($board as $b) { if ((int) $b['contract_id'] === $CID) { $mine = $b; } }
check($mine !== null && isset($mine['second_party']),
      'وكلُّ سطرٍ يحمل **اسمَ العميل وحالَ العقد** مع أرقامه');
$tot = CBD::totals($board);
check(count($tot) >= 1 && isset($tot['USD']),
      '★★ والمجاميعُ **مفروزةٌ بالعملة** لا مجموعًا واحدًا: ' . implode(' · ', array_keys($tot)));
$hasMix = false;
foreach ($board as $b) { if (!$b['ok'] && (int) $b['contract_id'] === $CID2) { $hasMix = true; } }
check($hasMix, 'و**العقدُ المزدوجُ يظهر في اللوحة ممتنعًا لا محذوفًا** — «الفجوةُ تُرى»');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══\n");
exit($FAIL === 0 ? 0 : 1);
