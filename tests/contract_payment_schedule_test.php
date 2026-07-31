<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-05 — اختبار قبول: خطةُ الدفع (PLAN-03 §3.5 · §3.1 · §6 · §9-⑰)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/contract_payment_schedule_test.php
 *
 * ما يُثبته:
 *   ★★★ **برهانُ P-05 الأكبر**: قبضُ **رسومِ تعبئةٍ إيرادًا** لا يزيد رصيدَ
 *       المقدم بمليم — **فلا يُستقطع من مستخلص**؛ وقبضُ **مقدمٍ مستهلَك**
 *       يزيده بكامله. (PLAN-03 §6: «الخلطُ بينها يقلب التزامًا إلى إيرادٍ».)
 *   ★ **والمعالجةُ محكومةٌ بالنوع لا بالاختيار**: ثلاثةٌ تحسمها المحاسبة،
 *     والتعبئةُ وحدَها **بنص العقد فتُعلَن ولا تُفترض**.
 *   ① **توليدٌ آليٌّ من الرأس والجدول** — شهرُ التوقف لا دفعةَ له.
 *   ② **والزائدُ يُعلَن ولا يُبتلع** ⇒ 409.
 *   ③ **والملحقُ يفتح نسخةً والقديمةُ محفوظة** (§9-⑰).
 *   ④ **والحالاتُ الخمس** — والشرطيُّ لا يتأخر.
 *
 * البذرُ معزول: عقدٌ وبندٌ في 2084 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'P05 pay test');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractPaymentScheduleService.php';
require_once dirname(__DIR__) . '/Contracts/advance_helpers.php';

use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractMonthlyPlanService as CMP;
use App\Services\Contract\ContractPaymentScheduleService as CPS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999051;
$MARK  = 'P05T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE s FROM contract_payment_schedule s JOIN contracts c ON c.id = s.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE a FROM contract_advances a JOIN contracts c ON c.id = a.contract_id
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

fwrite(STDOUT, "\n══ P-05 — خطةُ الدفع بأنماطها وأنواعِ مقدمها ══\n");

head('البذر — عقدُ 2084 بجدولٍ شهريٍّ فيه **شهرُ توقف** واحتجازٌ 5%');

$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id,
              price_currency_contract, retention_pct, advance_recovery_pct, created_at)
              VALUES ({$CO}, '2084-01-01', 365, '2084-01-01', '2084-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', 5.00, 20.00, NOW())");
$CID = intval($conn->insert_id);

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'ton', 'description' => 'نقلُ خامٍ — بندُ ' . $MARK,
    'qty_contracted' => 12000, 'unit_price' => 5.00, 'currency' => 'USD',
    'tax_status' => 'exempt', 'tax_code_id' => null,
    'valid_from' => '2084-01-01', 'valid_to' => '2084-12-31'), $ACTOR);
$LID = (int) $r['line_id'];

// جدولٌ شهري: تموزُ توقفٌ بصفر · والباقي 1090.909... ⇒ نوزّع 11 شهرًا صحيحةً
$months = array();
for ($i = 1; $i <= 12; $i++) {
    $mm = sprintf('2084-%02d', $i);
    if ($i === 7) { $months[] = array('period_month' => $mm, 'qty_planned' => 0,
                                      'month_kind' => 'shutdown', 'note' => 'توقفٌ موسمي'); continue; }
    $months[] = array('period_month' => $mm, 'qty_planned' => ($i === 12 ? 1000 : 1000));
}
// Σ = 11 × 1000 = 11,000 · والمتبقي 1000 يُضاف لكانون الأول فيُطابق 12,000
$months[11]['qty_planned'] = 2000;
$mp = CMP::savePlan($conn, $gate, $CO, $LID, 1, '2084-01-01', $months, $ACTOR);
check($CID > 0 && $LID > 0 && $mp['ok'] && abs($mp['planned'] - 12000.0) < 0.005,
      "عقدٌ #{$CID} · بندٌ #{$LID} · جدولٌ شهريٌّ Σ = 12,000 (تموزُ صفرٌ توقفًا)");
$val = CLS::contractValue($gate, $CID);
check(abs($val['by_currency']['USD'] - 60000.0) < 0.005, 'وقيمةُ العقد 60,000 USD');

// ═══ ★ المعالجةُ محكومةٌ بالنوع ═══
head('★ **المعالجةُ محكومةٌ بالنوع لا بالاختيار** (PLAN-03 §3.1 · §6)');

$bad = CPS::generate($conn, $gate, $CO, $CID, array(
    'pattern' => 'advance_then_monthly',
    'advance' => array('type' => 'recoverable', 'basis' => 'percent', 'percent' => 20,
                       'treatment' => 'revenue')), $ACTOR);
check(!$bad['ok'] && $bad['code'] === 422 && mb_strpos($bad['reason'], 'حتمًا') !== false,
      '★★ مقدمٌ مستهلَكٌ بمعالجةِ **إيراد** ⇒ **422 — معالجتُه liability حتمًا**');

$bad2 = CPS::generate($conn, $gate, $CO, $CID, array(
    'pattern' => 'advance_then_monthly',
    'advance' => array('type' => 'non_refundable_booking', 'basis' => 'percent', 'percent' => 10,
                       'treatment' => 'liability')), $ACTOR);
check(!$bad2['ok'] && $bad2['code'] === 422,
      '★ ورسومُ حجزٍ غيرِ مستردةٍ بمعالجةِ **التزام** ⇒ **422** — إيرادٌ عند الاستحقاق');

$bad3 = CPS::generate($conn, $gate, $CO, $CID, array(
    'pattern' => 'advance_then_monthly',
    'advance' => array('type' => 'mobilization', 'basis' => 'percent', 'percent' => 10)), $ACTOR);
check(!$bad3['ok'] && $bad3['code'] === 422 && mb_strpos($bad3['reason'], 'بحسب نص العقد') !== false,
      '★★ و**التعبئةُ بلا معالجةٍ معلَنةٍ ⇒ 422** — «قد تكون دَينًا أو إيرادًا بحسب نص العقد»');

$bad4 = CPS::generate($conn, $gate, $CO, $CID, array(
    'pattern' => 'advance_then_monthly',
    'advance' => array('type' => 'mobilization', 'basis' => 'percent', 'percent' => 10,
                       'treatment' => 'revenue')), $ACTOR);
check(!$bad4['ok'] && $bad4['code'] === 422 && mb_strpos($bad4['reason'], 'نصُّ العقد') !== false,
      '★ وبمعالجةٍ بلا **نصِّ العقد** ⇒ **422** — ولا معالجةَ بلا سند');

$bad5 = CPS::generate($conn, $gate, $CO, $CID, array(
    'pattern' => 'advance_then_monthly',
    'advance' => array('basis' => 'percent', 'percent' => 10)), $ACTOR);
check(!$bad5['ok'] && $bad5['code'] === 422 && mb_strpos($bad5['reason'], 'نوعُ المقدم') !== false,
      'ومقدمٌ **بلا نوع** ⇒ **422** — ولا مقدمَ بلا نوعٍ من الأربعة');

$n = intval($conn->query("SELECT COUNT(*) c FROM contract_payment_schedule WHERE contract_id={$CID}")
                  ->fetch_assoc()['c']);
check($n === 0, 'وصفرُ سطرٍ كُتب في المحاولات الخمس المرفوضة');

// ═══ ① التوليدُ الآلي ═══
head('① **توليدٌ آليٌّ من الرأس والجدول** — لا تخمينَ ولا قسمةٌ بالتساوي');

$g = CPS::generate($conn, $gate, $CO, $CID, array(
    'pattern' => 'advance_then_monthly', 'due_days' => 30,
    'advance' => array('type' => 'recoverable', 'basis' => 'percent', 'percent' => 20,
                       'due_date' => '2084-01-05')), $ACTOR);
check($g['ok'] && $g['currency'] === 'USD', '★ وُلّدت الخطة: ' . $g['reason']);
$rows = CPS::liveRows($gate, $CID);
$byKind = array();
foreach ($rows as $x) { $byKind[(string) $x['payment_kind']][] = $x; }
check(count($byKind['monthly_settlement']) === 11,
      '★★ **11 تسويةً شهريةً لا 12** — **شهرُ التوقف لا دفعةَ له** (والصفرُ لا يُطالَب به)');
check(count($byKind['advance']) === 1 && abs((float) $byKind['advance'][0]['amount_expected'] - 12000.0) < 0.005,
      'ومقدمٌ واحدٌ = 20% من 60,000 = **12,000**');
check(count($byKind['retention_release']) === 1
      && abs((float) $byKind['retention_release'][0]['amount_expected'] - 3000.0) < 0.005,
      'وردُّ محتجزٍ = 5% من 60,000 = **3,000**');
check($byKind['retention_release'][0]['due_date'] === null
      && $byKind['retention_release'][0]['due_condition'] !== null,
      '★ وردُّ المحتجز **بشرطٍ لا بتاريخ** — «تاريخُ أو شرطُ الاستحقاق»');
$dec = null;
foreach ($byKind['monthly_settlement'] as $x) {
    if ((string) $x['period_month'] === '2084-12') { $dec = $x; }
}
check($dec !== null && abs((float) $dec['amount_expected'] - 10000.0) < 0.005,
      '★★ وكانونُ الأول **10,000** (2000 طنًّا × 5) — **من الجدول لا بالقسمة**');
check((string) $dec['due_date'] === '2085-01-31',
      'ومهلةُ السداد 30 يومًا بعد انقضاء الشهر ⇒ ' . $dec['due_date']);
check(mb_strpos($g['reason'], 'ليست قيمةَ العقد') !== false,
      '★ و**Σ الخطة ليست قيمةَ العقد** معلَنةً — المقدمُ يُستهلك من المستخلصات فلا يُجمع معها');

$again = CPS::generate($conn, $gate, $CO, $CID, array('pattern' => 'monthly_claim'), $ACTOR);
check(!$again['ok'] && $again['code'] === 409 && mb_strpos($again['reason'], 'بنسخةٍ جديدة') !== false,
      'وتوليدٌ ثانٍ فوق النافذة ⇒ **409** — والتغييرُ بنسخة');

$manualMonthly = CPS::addRow($conn, $gate, $CO, $CID, array(
    'payment_kind' => 'monthly_settlement', 'amount_expected' => 500,
    'due_date' => '2084-06-30'), $ACTOR);
check(!$manualMonthly['ok'] && $manualMonthly['code'] === 422,
      '★ و**التسويةُ الشهرية لا تُدخل يدويًّا** ⇒ 422 — ولا مصدرَ ثانيًا للرقم');

$ms = CPS::addRow($conn, $gate, $CO, $CID, array(
    'payment_kind' => 'milestone', 'pattern' => 'milestone_payments',
    'amount_expected' => 2500, 'currency' => 'USD',
    'due_condition' => 'عند تسليم المرحلة الأولى وقبولِها', 'note' => 'معلَمُ ' . $MARK), $ACTOR);
check($ms['ok'] && $ms['row_id'] > 0, 'وسطرُ **معلَمٍ** يدويٌّ مقبول — «وتُعدَّل يدويًّا للمعالم»');

$noDue = CPS::addRow($conn, $gate, $CO, $CID, array(
    'payment_kind' => 'final', 'amount_expected' => 100, 'currency' => 'USD'), $ACTOR);
check(!$noDue['ok'] && $noDue['code'] === 422 && mb_strpos($noDue['reason'], 'ولا سطرَ بلا استحقاق') !== false,
      'وسطرٌ **بلا تاريخٍ ولا شرط** ⇒ **422**');

// ═══ ★★★ البرهانُ الأكبر ═══
head('★★★ **البرهان: الإيرادُ لا يدخل دفترَ السلف** — ولا يُستقطع من مستخلص');

// ⚠ `advance_balance` تُرجع **مصفوفة** {received,recovered,balance} لا رقمًا
$bal0 = advance_balance($gate, $CID);
check(abs((float) $bal0['balance']) < 0.005, 'رصيدُ المقدم قبل أيِّ قبض = **صفر**');

$advRow = $byKind['advance'][0];
$rec1 = CPS::markReceived($conn, $gate, $CO, (int) $advRow['id'], 12000, '2084-01-05',
                          'RCPT-ADV-' . $MARK, $ACTOR);
check($rec1['ok'] && $rec1['state'] === 'completed', '★ قُبض المقدمُ المستهلَك 12,000: ' . $rec1['reason']);
$bal1 = advance_balance($gate, $CID); $bal1 = (float) $bal1['balance'];
check(abs($bal1 - 12000.0) < 0.005,
      '★★ ورصيدُ المقدم صار **12,000** — دَينٌ يُستهلك من المستخلصات');
$q = $conn->query("SELECT advance_id, state FROM contract_payment_schedule
                    WHERE id=" . (int) $advRow['id'])->fetch_assoc();
check((int) $q['advance_id'] > 0, 'والسطرُ يحمل مرجعَ قبضِه في `contract_advances` — **مصدرٌ واحدٌ للرقم**');

// والآن رسومُ تعبئةٍ **إيرادًا بنص العقد**
$mob = CPS::addRow($conn, $gate, $CO, $CID, array(
    'payment_kind' => 'advance', 'pattern' => 'partial_advance',
    'amount_expected' => 5000, 'currency' => 'USD', 'due_date' => '2084-01-10',
    'advance' => array('type' => 'mobilization', 'treatment' => 'revenue',
                       'treatment_basis' => 'البند 7-3: رسومُ التعبئة غيرُ مستردةٍ وتُستحق عند الحشد'),
    'note' => 'تعبئةُ ' . $MARK), $ACTOR);
check($mob['ok'], 'وأُضيف سطرُ **رسومِ تعبئةٍ إيرادًا بنصِّ البند 7-3**');
$rec2 = CPS::markReceived($conn, $gate, $CO, (int) $mob['row_id'], 5000, '2084-01-10',
                          'RCPT-MOB-' . $MARK, $ACTOR);
check($rec2['ok'] && mb_strpos($rec2['reason'], 'لا يدخل دفترَ السلف') !== false,
      '★★ قُبضت 5,000 تعبئةً: ' . $rec2['reason']);
$bal2 = advance_balance($gate, $CID); $bal2 = (float) $bal2['balance'];
check(abs($bal2 - 12000.0) < 0.005,
      '★★★ **ورصيدُ المقدم ما زال 12,000 — لم يزد بمليم**: الإيرادُ لا يُستقطع من مستخلص');
$nAdv = intval($conn->query("SELECT COUNT(*) c FROM contract_advances WHERE contract_id={$CID}")
                     ->fetch_assoc()['c']);
check($nAdv === 1, 'و`contract_advances` فيها **صفٌّ واحدٌ لا اثنان** — الإيرادُ لم يدخلها');

$q = $conn->query("SELECT advance_id, treatment FROM contract_payment_schedule
                    WHERE id=" . (int) $mob['row_id'])->fetch_assoc();
check($q['advance_id'] === null && $q['treatment'] === 'revenue',
      'وسطرُ التعبئة **بلا مرجعٍ في دفتر السلف** أصلًا');

$conn->query("UPDATE contract_payment_schedule SET advance_id=" . (int) $q['advance_id'] . "
               WHERE id=" . (int) $mob['row_id']);
$conn->query("UPDATE contract_payment_schedule SET advance_id=1 WHERE id=" . (int) $mob['row_id']);
$q2 = $conn->query("SELECT advance_id FROM contract_payment_schedule
                     WHERE id=" . (int) $mob['row_id'])->fetch_assoc();
check($q2['advance_id'] === null,
      '★★ و**وصلُ سطرٍ إيراديٍّ بدفتر السلف بكتابةٍ مباشرة يرفضه `CHECK`**');

$conn->query("UPDATE contract_payment_schedule SET treatment='revenue'
               WHERE id=" . (int) $advRow['id']);
$q3 = $conn->query("SELECT treatment FROM contract_payment_schedule
                     WHERE id=" . (int) $advRow['id'])->fetch_assoc();
check($q3['treatment'] === 'liability',
      '★★ و**قلبُ المستهلَك إيرادًا بكتابةٍ مباشرة يرفضه `CHECK`** — الفصلُ بنيويّ');

// ═══ ② الزائدُ يُعلَن ═══
head('② **والزائدُ يُعلَن ولا يُبتلع**');

$over = CPS::markReceived($conn, $gate, $CO, (int) $ms['row_id'], 3000, '2084-05-01',
                          'RCPT-OVR-' . $MARK, $ACTOR);
check(!$over['ok'] && $over['code'] === 409 && mb_strpos($over['reason'], 'رصيدٌ دائنٌ للعميل لا إيراد') !== false,
      '★★ 3,000 على معلَمٍ متوقَّعُه 2,500 ⇒ **409**: ' . mb_substr($over['reason'], 0, 120));
$part = CPS::markReceived($conn, $gate, $CO, (int) $ms['row_id'], 1000, '2084-05-01',
                          'RCPT-PRT-' . $MARK, $ACTOR);
check($part['ok'] && abs($part['remaining'] - 1500.0) < 0.005, 'وقبضٌ جزئيٌّ 1,000 ⇒ متبقٍّ 1,500');
$q = $conn->query("SELECT remaining_amount FROM contract_payment_schedule
                    WHERE id=" . (int) $ms['row_id'])->fetch_assoc();
check(abs((float) $q['remaining_amount'] - 1500.0) < 0.005,
      'و`remaining_amount` **عمودٌ مولَّد** — لا يُكتب بيدٍ فلا ينحرف');

// ═══ ④ الحالاتُ الخمس ═══
head('④ **والحالاتُ الخمس** — والشرطيُّ لا يتأخر');

check(CPS::stateFor(0, 100, '2084-01-31', '2084-01-01') === 'not_due', 'قبل الاستحقاق: **غيرُ مستحق**');
check(CPS::stateFor(0, 100, '2084-01-31', '2084-01-31') === 'due', 'ويومَه: **مستحق**');
check(CPS::stateFor(0, 100, '2084-01-31', '2084-03-01') === 'overdue', 'وبعده بلا قبض: **متأخر**');
check(CPS::stateFor(40, 100, '2084-01-31', '2084-01-15') === 'partial', 'وبقبضٍ ناقصٍ قبله: **جزئي**');
check(CPS::stateFor(100, 100, '2084-01-31', '2085-01-01') === 'completed', 'وبالكامل: **مكتمل**');
check(CPS::stateFor(0, 100, null, '2099-01-01') === 'not_due',
      '★ و**الشرطيُّ لا يتأخر أبدًا**: ما استحقاقُه شرطٌ لا تاريخ يبقى «غيرَ مستحق»');

$moved = CPS::refreshStates($gate, $CID, '2085-06-01');
$late = intval($conn->query("SELECT COUNT(*) c FROM contract_payment_schedule
                              WHERE contract_id={$CID} AND effective_to IS NULL AND state='overdue'")
                     ->fetch_assoc()['c']);
check($moved > 0 && $late > 0, "★ وبمرور الزمن إلى 2085-06: **{$late} سطرًا متأخرًا** أُعلنت لا اكتُشفت");
$sum = CPS::summary($gate, $CID, '2085-06-01');
check($sum['overdue_rows'] === $late && $sum['overdue'] > 0, 'والملخّصُ يقولها بمبلغها: ' . $sum['note']);
check(abs($sum['liability'] - 12000.0) < 0.005 && abs($sum['revenue'] - 5000.0) < 0.005,
      '★★ والملخّصُ **يفرز المقبوضَ بمعالجته**: التزامٌ 12,000 · إيرادٌ 5,000 — ولا يُجمعان');

// ═══ ③ النسخةُ الجديدة ═══
head('③ **والملحقُ يفتح نسخةً والقديمةُ محفوظة** (§9-⑰)');

$before = intval($conn->query("SELECT COUNT(*) c FROM contract_payment_schedule WHERE contract_id={$CID}")
                       ->fetch_assoc()['c']);
$nv = CPS::newVersion($conn, $gate, $CO, $CID, '2084-07-01', 0, $ACTOR);
check($nv['ok'] && $nv['version'] === 2, '★ ' . $nv['reason']);
$after = intval($conn->query("SELECT COUNT(*) c FROM contract_payment_schedule WHERE contract_id={$CID}")
                      ->fetch_assoc()['c']);
check($after === $before * 2, "★★ والصفوفُ **تضاعفت** ({$before} ⇒ {$after}) — **القديمةُ محفوظة**");
$sealed = intval($conn->query("SELECT COUNT(*) c FROM contract_payment_schedule
                                WHERE contract_id={$CID} AND version=1 AND effective_to='2084-06-30'")
                       ->fetch_assoc()['c']);
check($sealed === $before, 'والنسخةُ 1 **مختومةٌ كلُّها في 2084-06-30**');
$liveNow = CPS::liveRows($gate, $CID);
check(count($liveNow) === $before && (int) $liveNow[0]['version'] === 2,
      'والنافذُ الآن النسخةُ 2 وحدَها');
$carried = 0.0;
foreach ($liveNow as $x) { $carried = round($carried + (float) $x['received_amount'], 2); }
check(abs($carried - 18000.0) < 0.005,
      '★★ و**المقبوضُ 18,000 رُحِّل إلى النسخة الجديدة** — القبضُ واقعةٌ لا تُلغيها نسخة');
$oldSealed = $conn->query("SELECT id FROM contract_payment_schedule
                            WHERE contract_id={$CID} AND version=1 LIMIT 1")->fetch_assoc();
$onSealed = CPS::markReceived($conn, $gate, $CO, (int) $oldSealed['id'], 10, '2084-03-01',
                              'X-' . $MARK, $ACTOR);
check(!$onSealed['ok'] && $onSealed['code'] === 423,
      'وقبضٌ على **نسخةٍ مختومة** ⇒ **423** — والقبضُ على النافذة');
$back = CPS::newVersion($conn, $gate, $CO, $CID, '2083-01-01', 0, $ACTOR);
check(!$back['ok'] && $back['code'] === 422 && mb_strpos($back['reason'], 'الزمنُ لا يرجع') !== false,
      'ونسخةٌ **تسري قبل سابقتها** ⇒ **422**');

// ═══ حدود ═══
head('حدودٌ أخرى');

$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id,
              price_currency_contract, retention_pct, created_at)
              VALUES ({$CO}, '2084-01-01', 365, '2084-01-01', '2084-12-31',
                      'خالٍ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', 0, NOW())");
$CID2 = intval($conn->insert_id);
$empty = CPS::generate($conn, $gate, $CO, $CID2, array('pattern' => 'monthly_claim'), $ACTOR);
check(!$empty['ok'] && $empty['code'] === 422 && mb_strpos($empty['reason'], 'لا جدولَ شهريًّا') !== false,
      '★ وعقدٌ **بلا جدولٍ شهريٍّ نافذ** ⇒ **422** — «تُولَّد منه لا من التخمين»');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══\n");
exit($FAIL === 0 ? 0 : 1);
