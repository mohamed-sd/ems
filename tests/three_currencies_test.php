<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-08 — اختبار قبول: العملاتُ الثلاث والفروقُ الأربعة (§4 · §9-⑨ · §9-⑫)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/three_currencies_test.php
 *
 * ما يُثبته:
 *   ★★★ **معيارُ §9-⑨ حرفيًّا**: «قبضٌ بعملةٍ أخرى: **الذمةُ تُطفأ بالمعادل،
 *       والمتبقي رصيدٌ غيرُ مسددٍ لا فرقَ صرف**، وفرقُ الصرف بسطره في العملة
 *       الوظيفية».
 *   ★★ **والفروقُ الأربعةُ لا تُخلط** — ولكلٍّ بيتُه المعلَن.
 *   ① **العملاتُ الثلاث** بأسمائها لا بمواضعها.
 *   ② **وذمّةٌ بلا عملةٍ لا يُخصَّص لها** — وهو ما كانت عليه البنيةُ قبل P-08.
 *   ③ **وفرقٌ غيرُ محقَّقٍ لا يمسّ رصيدًا ولا يُقفل ذمّة**.
 *   ④ **وزيادةُ السداد رصيدٌ دائنٌ لا إيرادٌ ولا فرقُ صرف**.
 *
 * البذرُ معزول: عقدٌ وذممٌ بالجنيه والدولار في 2089 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'P08 fx test');

require_once dirname(__DIR__) . '/app/Services/Revenue/CollectionService.php';
require_once dirname(__DIR__) . '/app/Services/Finance/FxSettlementService.php';
require_once dirname(__DIR__) . '/includes/fx.php';

use App\Services\Revenue\CollectionService as CS;
use App\Services\Finance\FxSettlementService as FX;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999081;
$MARK  = 'P08T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE d FROM fin_fx_differences d
                   WHERE d.note LIKE '%{$MARK}%' OR d.source_ref IN
                   (SELECT a.id FROM fin_collection_allocations a JOIN fin_payments p ON p.id = a.payment_id
                     WHERE p.bank_ref LIKE '%{$MARK}%')");
    $conn->query("DELETE a FROM fin_collection_allocations a JOIN fin_payments p ON p.id = a.payment_id
                   WHERE p.bank_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE d FROM fin_fx_differences d JOIN fin_receivables r ON r.id = d.source_ref
                   WHERE d.source_kind='revaluation' AND r.doc_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_payments WHERE bank_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_receivables WHERE doc_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ P-08 — العملاتُ الثلاث والفروقُ الأربعة ══\n");

head('① **العملاتُ الثلاث** بأسمائها لا بمواضعها');

$FN = (string) ems_fx_base_currency();
check($FN !== '', 'العملةُ الوظيفية (أساسُ الشركة): **' . $FN . '**');

$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id,
              price_currency_contract, created_at)
              VALUES ({$CO}, '2089-01-01', 365, '2089-01-01', '2089-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'جنيه', NOW())");
$CID = intval($conn->insert_id);
$three = FX::threeCurrencies($gate, $CID, 'USD');
check($three['contract'] === 'SDG' && $three['settlement'] === 'USD' && $three['functional'] === $FN,
      '★★ عقدٌ **SDG** · سدادٌ **USD** · وظيفيةٌ **' . $FN . '**: ' . $three['note']);
check(mb_strpos($three['note'], 'لا تُجمع في رقم') !== false,
      'و«**ثلاثُ عملاتٍ لا تُجمع في رقم**» معلَنةً');

head('② **وذمّةٌ بلا عملةٍ لا يُخصَّص لها** — وهو ما كانت عليه البنيةُ قبل P-08');

$CLIENT = 1;
/* INJ-0036: كلُّ ذمّةِ بذرٍ تشير إلى فاتورةٍ صادرةٍ حقيقية */
require_once __DIR__ . '/_source_doc_seed.php';
$TI_X = seed_source_invoice($conn, $CO, 'INV-X-' . $MARK, $CLIENT, 1000, 'USD', 0);
$conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
              source_doc_id, project_id, amount, currency, collected, due_date, state, created_at)
              VALUES ({$CO}, {$CLIENT}, 'invoice', 'INV-X-{$MARK}', {$TI_X}, {$PRJ}, 1000, '', 0,
                      '2089-03-31', 'open', NOW())");
$RX = intval($conn->insert_id);
$conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
              method, bank_ref, received_on, amount, currency, state, created_at)
              VALUES ({$CO}, 'RCTX-{$MARK}', 'collection', 'customer', {$CLIENT},
                      'bank', 'BNKX-{$MARK}', '2089-02-01', 500, 'USD', 'executed', NOW())");
$PX = intval($conn->insert_id);
$bad = CS::allocate($conn, $gate, $CO, $PX, array(
    array('target_kind' => 'invoice', 'target_ref' => $RX, 'amount' => 500)), $ACTOR);
check(!$bad['ok'] && $bad['code'] === 422 && mb_strpos($bad['reason'], 'بلا عملةٍ مسجَّلة') !== false,
      '★★ ذمّةٌ **بلا عملة** ⇒ **422**: «ولا يُخصَّص قبضٌ لمبلغٍ لا يُعرف بأيِّ عملةٍ هو»');

head('★★★ **معيارُ §9-⑨: الذمةُ تُطفأ بالمعادل والمتبقي رصيدٌ غيرُ مسدد**');

// ذمّةٌ بالجنيه 10,000,000 اعتُرف بها بسعرٍ مجمَّد · وقبضٌ بالدولار
$RATE = (float) FX::rateOf('SDG', '2089-02-01');
check($RATE !== null && $RATE > 0, 'سعرُ الجنيه إلى الأساس: ' . $RATE);
$TI_SDG = seed_source_invoice($conn, $CO, 'INV-SDG-' . $MARK, $CLIENT, 10000000, 'SDG', 0);
$conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
              source_doc_id, project_id, amount, currency, fx_rate_recognized, base_amount, collected,
              due_date, state, created_at)
              VALUES ({$CO}, {$CLIENT}, 'invoice', 'INV-SDG-{$MARK}', {$TI_SDG}, {$PRJ}, 10000000, 'SDG',
                      {$RATE}, ROUND(10000000*{$RATE},2), 0, '2089-03-31', 'open', NOW())");
$RS = intval($conn->insert_id);

// قبضٌ بالدولار 1,000 = 1,000/0.000185 ≈ 5,405,405.41 جنيهًا
$conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
              method, bank_ref, received_on, amount, currency, state, created_at)
              VALUES ({$CO}, 'RCT1-{$MARK}', 'collection', 'customer', {$CLIENT},
                      'bank', 'BNK1-{$MARK}', '2089-02-01', 1000, 'USD', 'executed', NOW())");
$P1 = intval($conn->insert_id);

$r = CS::allocate($conn, $gate, $CO, $P1, array(
    array('target_kind' => 'invoice', 'target_ref' => $RS, 'amount' => 1000)), $ACTOR);
check($r['ok'], '★ خُصّص 1,000 USD على ذمّةٍ بالجنيه: ' . mb_substr($r['reason'], 0, 130));
$equiv = round(1000 / $RATE, 2);
$a = $conn->query("SELECT pay_currency, target_currency, amount, amount_target, base_amount, fx_diff_base
                    FROM fin_collection_allocations WHERE payment_id={$P1}")->fetch_assoc();
check($a['pay_currency'] === 'USD' && $a['target_currency'] === 'SDG',
      '★ والسطرُ يحمل **عملتَي طرفيه**: سدادٌ USD · هدفٌ SDG');
check(abs((float) $a['amount_target'] - $equiv) < 1.0,
      '★★★ و**المعادلُ ' . $a['amount_target'] . ' جنيهًا** = 1,000 USD بسعر ' . $RATE);
$q = $conn->query("SELECT collected, outstanding, state FROM fin_receivables WHERE id={$RS}")->fetch_assoc();
check(abs((float) $q['collected'] - (float) $a['amount_target']) < 0.01,
      '★★★ و**الذمّةُ أُطفئت بالمعادل** لا بالرقم الخام (1,000)');
check(abs((float) $q['outstanding'] - round(10000000 - (float) $a['amount_target'], 2)) < 0.01
      && $q['state'] === 'partial',
      '★★★ و**المتبقي ' . $q['outstanding'] . ' رصيدٌ غيرُ مسددٍ باقٍ في الذمّة** — **لا فرقَ صرفٍ يُقفل به الباب**');

head('★★ **وفرقُ الصرف بسطره في العملة الوظيفية**');

$fxRows = $conn->query("SELECT d.* FROM fin_fx_differences d
                         JOIN fin_collection_allocations a ON a.id = d.source_ref
                        WHERE a.payment_id={$P1} AND d.kind='realized'");
$n = $fxRows->num_rows;
check($n === 0, 'وبسعرٍ واحدٍ لم يتغيّر ⇒ **صفرُ فرقٍ محقق** — و«صفرٌ ليس فرقًا» فلا سطرَ ضوضاء');

// والآن ذمّةٌ اعتُرف بها بسعرٍ **أعلى** ثم قُبضت بسعر اليوم ⇒ فرقٌ محقق
$OLD = $RATE * 1.20;
$TI_OLD = seed_source_invoice($conn, $CO, 'INV-OLD-' . $MARK, $CLIENT, 6000000, 'SDG', 0);
$conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
              source_doc_id, project_id, amount, currency, fx_rate_recognized, base_amount, collected,
              due_date, state, created_at)
              VALUES ({$CO}, {$CLIENT}, 'invoice', 'INV-OLD-{$MARK}', {$TI_OLD}, {$PRJ}, 6000000, 'SDG',
                      {$OLD}, ROUND(6000000*{$OLD},2), 0, '2089-03-31', 'open', NOW())");
$RO = intval($conn->insert_id);
$conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
              method, bank_ref, received_on, amount, currency, state, created_at)
              VALUES ({$CO}, 'RCT2-{$MARK}', 'collection', 'customer', {$CLIENT},
                      'bank', 'BNK2-{$MARK}', '2089-02-01', 1000, 'USD', 'executed', NOW())");
$P2 = intval($conn->insert_id);
$r2 = CS::allocate($conn, $gate, $CO, $P2, array(
    array('target_kind' => 'invoice', 'target_ref' => $RO, 'amount' => 1000)), $ACTOR);
check($r2['ok'] && abs($r2['fx_diff']) > 0.005, '★★ ' . mb_substr($r2['reason'], 0, 150));
$d = $conn->query("SELECT d.kind, d.from_currency, d.functional_currency, d.amount, d.source_kind
                    FROM fin_fx_differences d
                    JOIN fin_collection_allocations a ON a.id = d.source_ref
                   WHERE a.payment_id={$P2} AND d.source_kind='allocation'")->fetch_assoc();
check($d !== null && $d['kind'] === 'realized' && $d['functional_currency'] === $FN,
      '★★★ و**سطرُ فرقٍ محقَّقٍ بالعملة الوظيفية ' . $FN . '** — بابٌ غيرُ باب الذمّة');
check((float) $d['amount'] < 0,
      'وقيمتُه سالبةٌ (**خسارةُ صرف**): الذمّةُ اعتُرف بها بسعرٍ أعلى ⇒ ' . $d['amount'] . ' ' . $FN);
$again = CS::allocate($conn, $gate, $CO, $P2, array(
    array('target_kind' => 'invoice', 'target_ref' => $RO, 'amount' => 1)), $ACTOR);
$cnt = intval($conn->query("SELECT COUNT(*) c FROM fin_fx_differences d
                             JOIN fin_collection_allocations a ON a.id = d.source_ref
                            WHERE a.payment_id={$P2}")->fetch_assoc()['c']);
check($cnt === 1, '★ و**فرقٌ واحدٌ لكل مصدر** — والعطالةُ بمفتاحٍ فريد');

head('③ **وفرقٌ غيرُ محقَّقٍ لا يمسّ رصيدًا ولا يُقفل ذمّة**');

$before = $conn->query("SELECT outstanding, collected FROM fin_receivables WHERE id={$RS}")->fetch_assoc();
$rev = FX::revalueOpen($conn, $gate, $CO, '2089-06-30', $ACTOR, false);
check($rev['ok'], 'إعادةُ تقييمٍ **بلا تطبيق**: ' . $rev['note']);
$nDry = intval($conn->query("SELECT COUNT(*) c FROM fin_fx_differences WHERE source_kind='revaluation'
                              AND source_ref={$RS}")->fetch_assoc()['c']);
check($nDry === 0, 'و**التجريبُ لا يكتب** — لا سطرَ في السجل');
$after = $conn->query("SELECT outstanding, collected FROM fin_receivables WHERE id={$RS}")->fetch_assoc();
check($before['outstanding'] === $after['outstanding'] && $before['collected'] === $after['collected'],
      '★★★ و**لا رصيدَ تغيّر ولا ذمّةَ أُقفلت** بإعادة التقييم — «تقديرٌ يُعاد كل فترة»');
check(mb_strpos($rev['note'], 'ولا رصيدَ تغيّر') !== false, 'والقاعدةُ معلَنةٌ في النتيجة نصًّا');

head('④ **وزيادةُ السداد رصيدٌ دائنٌ لا إيرادٌ ولا فرقُ صرف**');

$conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
              method, bank_ref, received_on, amount, currency, state, created_at)
              VALUES ({$CO}, 'RCT3-{$MARK}', 'collection', 'customer', {$CLIENT},
                      'bank', 'BNK3-{$MARK}', '2089-03-01', 900, 'USD', 'executed', NOW())");
$P3 = intval($conn->insert_id);
$r3 = CS::allocate($conn, $gate, $CO, $P3, array(
    array('target_kind' => 'invoice', 'target_ref' => $RS, 'amount' => 400)), $ACTOR);
check($r3['ok'] && abs($r3['unallocated'] - 500.0) < 0.005,
      '★ سندٌ 900 خُصّص منه 400 ⇒ **500 زيادةً**');
$q = $conn->query("SELECT unallocated_amount FROM fin_payments WHERE id={$P3}")->fetch_assoc();
check(abs((float) $q['unallocated_amount'] - 500.0) < 0.005,
      '★★ و**بيتُها `fin_payments.unallocated_amount`** لا سجلُّ الفروق');
$inFx = intval($conn->query("SELECT COUNT(*) c FROM fin_fx_differences d
                              JOIN fin_collection_allocations a ON a.id = d.source_ref
                             WHERE a.payment_id={$P3}")->fetch_assoc()['c']);
check($inFx === 0, '★★★ و**لا سطرَ لها في سجل فروق الصرف** — زيادةُ سدادٍ ليست فرقَ صرف');

$wrong = FX::recordDiff($conn, $gate, $CO, array(
    'kind' => 'overpayment', 'source_kind' => 'allocation', 'source_ref' => 1,
    'from_currency' => 'USD', 'amount' => 500), $ACTOR);
check(!$wrong['ok'] && $wrong['code'] === 422 && mb_strpos($wrong['reason'], 'ولا يُنقلان إلى هنا') !== false,
      '★★★ ومحاولةُ **نقلِها إلى سجل الفروق تُرفض 422** بنصِّ بيتها الصحيح');
$zero = FX::recordDiff($conn, $gate, $CO, array(
    'kind' => 'realized', 'source_kind' => 'allocation', 'source_ref' => 987654,
    'from_currency' => 'USD', 'amount' => 0), $ACTOR);
check($zero['ok'] && mb_strpos($zero['reason'], 'صفرٌ ليس فرقًا') !== false,
      'و**صفرٌ ليس فرقًا** — فلا سطرَ يُخفي الفروقَ الحقيقية');

head('★★ **والأربعةُ تُعرض منفصلةً** — ولا تُجمع في رقم');

$four = FX::fourfold($gate, $CLIENT);
check(count($four['unpaid']) > 0 && isset($four['unpaid']['SDG']),
      '① رصيدٌ غيرُ مسدد **بعملة كل ذمّة**: ' . json_encode($four['unpaid'], JSON_UNESCAPED_UNICODE));
check(abs($four['realized']) > 0.005, '② فرقٌ محقَّق: ' . $four['realized'] . ' ' . $FN);
check($four['overpayment'] >= 500.0 - 0.005, '④ زيادةُ سداد: ' . $four['overpayment']);
check(mb_strpos($four['note'], 'أربعةٌ لا تُخلط ولا تُجمع في رقم') !== false,
      '★★★ و**«أربعةٌ لا تُخلط ولا تُجمع في رقم»** معلَنةً في النتيجة نفسِها');
check(count(FX::DIFF_KINDS) === 4 && count(FX::DIFF_HOME) === 4,
      'ولكلٍّ **بيتٌ مسمًّى**: ' . implode(' · ', FX::DIFF_HOME));

fwrite(STDOUT, "\n══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══\n");
exit($FAIL === 0 ? 0 : 1);
