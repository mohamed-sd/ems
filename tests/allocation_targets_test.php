<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-07 — اختبار قبول: أهدافُ التخصيص الخمسة (الملحق §3-P-07 · §4)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/allocation_targets_test.php
 *
 * ما يُثبته:
 *   ★★★ **معيارُ §4 حرفيًّا**: «سندٌ واحدٌ على **مقدمٍ وفاتورتين**:
 *       **Σ التخصيصات = السند**».
 *   ① **Σ لا يتجاوز السند أبدًا** ⇒ 409 · و`CHECK` يحرس العدّاد.
 *   ② **والرصيدُ غيرُ المخصَّص عمودٌ ظاهرٌ** لا رقمٌ في رسالةٍ تختفي.
 *   ③ **والهدفُ يطابق نوعَه** — ولا يُخصَّص مقدمٌ على معلَم.
 *   ④ **ولا يُخصَّص قبضٌ لمسدَّدٍ ولا لمكتمل** ولا لهدفٍ مكرَّر.
 *
 * البذرُ معزول: عقدٌ وذمّتان وخطةُ دفعٍ في 2088 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'P07 alloc test');

require_once dirname(__DIR__) . '/app/Services/Revenue/CollectionService.php';

use App\Services\Revenue\CollectionService as CS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999071;
$MARK  = 'P07T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE a FROM fin_collection_allocations a JOIN fin_payments p ON p.id = a.payment_id
                   WHERE p.bank_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_payments WHERE bank_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_receivables WHERE doc_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE s FROM contract_payment_schedule s JOIN contracts c ON c.id = s.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ P-07 — أهدافُ التخصيص الخمسة ══\n");

head('البذر — عقدٌ بسطرِ مقدمٍ ومعلَمٍ · وذمّتان');
$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id,
              price_currency_contract, created_at)
              VALUES ({$CO}, '2088-01-01', 365, '2088-01-01', '2088-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', NOW())");
$CID = intval($conn->insert_id);
$conn->query("INSERT INTO contract_payment_schedule
    (company_id, contract_id, version, effective_from, seq, pattern, payment_kind,
     advance_type, treatment, amount_basis, amount_expected, currency, due_date, source, created_at)
    VALUES ({$CO}, {$CID}, 1, '2088-01-01', 1, 'advance_then_monthly', 'advance',
            'recoverable', 'liability', 'fixed', 5000, 'USD', '2088-01-10', 'generated', NOW())");
$ADV = intval($conn->insert_id);
$conn->query("INSERT INTO contract_payment_schedule
    (company_id, contract_id, version, effective_from, seq, pattern, payment_kind,
     amount_basis, amount_expected, currency, due_date, source, created_at)
    VALUES ({$CO}, {$CID}, 1, '2088-01-01', 2, 'milestone_payments', 'milestone',
            'fixed', 4000, 'USD', '2088-06-30', 'generated', NOW())");
$MST = intval($conn->insert_id);

$CLIENT = 1;
$conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
              project_id, amount, collected, due_date, state, created_at)
              VALUES ({$CO}, {$CLIENT}, 'invoice', 'INV-A-{$MARK}', {$PRJ}, 3000, 0, '2088-03-31', 'open', NOW())");
$R1 = intval($conn->insert_id);
$conn->query("INSERT INTO fin_receivables (company_id, customer_entity_id, doc_type, doc_ref,
              project_id, amount, collected, due_date, state, created_at)
              VALUES ({$CO}, {$CLIENT}, 'invoice', 'INV-B-{$MARK}', {$PRJ}, 2000, 0, '2088-04-30', 'open', NOW())");
$R2 = intval($conn->insert_id);
check($CID > 0 && $ADV > 0 && $MST > 0 && $R1 > 0 && $R2 > 0,
      "عقدٌ #{$CID} · مقدَّمٌ 5,000 (#{$ADV}) · معلَمٌ 4,000 (#{$MST}) · ذمّتان 3,000 و2,000");

// السندُ: 10,000 — والقصدُ 5,000 مقدمًا + 3,000 + 2,000 فاتورتين = **السند تمامًا**
$conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
              method, bank_ref, received_on, amount, currency, state, created_at)
              VALUES ({$CO}, 'RCT-{$MARK}', 'collection', 'customer', {$CLIENT},
                      'bank', 'BNK-{$MARK}', '2088-01-10', 10000, 'USD', 'executed', NOW())");
$PAY = intval($conn->insert_id);
$u0 = CS::unallocatedOf($gate, $PAY);
check($u0 !== null && abs($u0['unallocated'] - 10000.0) < 0.005,
      '★ وسندٌ 10,000 · **وغيرُ المخصَّص 10,000 عمودًا مولَّدًا** لا رقمًا في رسالة');

// ═══ ① Σ لا يتجاوز السند ═══
head('① **Σ التخصيصات لا تتجاوز السند أبدًا**');

$over = CS::allocate($conn, $gate, $CO, $PAY, array(
    array('target_kind' => 'advance', 'target_ref' => $ADV, 'amount' => 5000),
    array('target_kind' => 'invoice', 'target_ref' => $R1, 'amount' => 3000),
    array('target_kind' => 'invoice', 'target_ref' => $R2, 'amount' => 2000),
    array('target_kind' => 'milestone', 'target_ref' => $MST, 'amount' => 4000),
), $ACTOR);
check(!$over['ok'] && $over['code'] === 409 && mb_strpos($over['reason'], 'تتجاوز المتاحَ من السند') !== false,
      '★★ 14,000 على سندٍ 10,000 ⇒ **409 بالفائض 4,000**');
$n = intval($conn->query("SELECT COUNT(*) c FROM fin_collection_allocations WHERE payment_id={$PAY}")
                  ->fetch_assoc()['c']);
check($n === 0, 'وصفرُ سطرٍ كُتب — **الفحصُ كلُّه قبل الكتابة**');

$conn->query("UPDATE fin_payments SET allocated_amount=99999 WHERE id={$PAY}");
$q = $conn->query("SELECT allocated_amount FROM fin_payments WHERE id={$PAY}")->fetch_assoc();
check(abs((float) $q['allocated_amount']) < 0.005,
      '★★ ورفعُ العدّاد فوق مبلغ السند **بكتابةٍ مباشرة يرفضه `CHECK`**');

// ═══ ③ الهدفُ يطابق نوعَه ═══
head('③ **والهدفُ يطابق نوعَه** — ولا يُخصَّص مقدمٌ على معلَم');

$mix = CS::allocate($conn, $gate, $CO, $PAY, array(
    array('target_kind' => 'advance', 'target_ref' => $MST, 'amount' => 100)), $ACTOR);
check(!$mix['ok'] && $mix['code'] === 422 && mb_strpos($mix['reason'], 'لا يطابق نوعَ السطر') !== false,
      '★★ مقدَّمٌ على سطرِ **معلَم** ⇒ **422**');

$dup = CS::allocate($conn, $gate, $CO, $PAY, array(
    array('target_kind' => 'invoice', 'target_ref' => $R1, 'amount' => 100),
    array('target_kind' => 'invoice', 'target_ref' => $R1, 'amount' => 100)), $ACTOR);
check(!$dup['ok'] && $dup['code'] === 409 && mb_strpos($dup['reason'], 'مكرَّر') !== false,
      'وهدفٌ مكرَّرٌ في الطلب ⇒ **409**');

$ghost = CS::allocate($conn, $gate, $CO, $PAY, array(
    array('target_kind' => 'invoice', 'target_ref' => 99999999, 'amount' => 100)), $ACTOR);
check(!$ghost['ok'] && $ghost['code'] === 404, 'وهدفٌ **غيرُ موجود** ⇒ **404**');

$big = CS::allocate($conn, $gate, $CO, $PAY, array(
    array('target_kind' => 'invoice', 'target_ref' => $R2, 'amount' => 2500)), $ACTOR);
check(!$big['ok'] && $big['code'] === 409
      && mb_strpos($big['reason'], 'رصيدٌ دائنٌ للعميل لا إيراد') !== false,
      '★★ ومبلغٌ يفوق متبقّي الذمّة ⇒ **409**: «رصيدٌ دائنٌ للعميل لا إيراد»');

// ═══ ★★★ معيارُ §4 ═══
head('★★★ **معيارُ §4: سندٌ واحدٌ على مقدمٍ وفاتورتين — Σ التخصيصات = السند**');

$r = CS::allocate($conn, $gate, $CO, $PAY, array(
    array('target_kind' => 'advance', 'target_ref' => $ADV, 'amount' => 5000,
          'note' => 'المقدمُ المتعاقَدُ عليه'),
    array('target_kind' => 'invoice', 'target_ref' => $R1, 'amount' => 3000),
    array('target_kind' => 'invoice', 'target_ref' => $R2, 'amount' => 2000),
), $ACTOR);
check($r['ok'] && count($r['rows']) === 3, '★ خُصّص على **ثلاثة أهدافٍ**: ' . $r['reason']);
check(abs($r['allocated'] - 10000.0) < 0.005 && abs($r['unallocated']) < 0.005,
      '★★★ **Σ التخصيصات 10,000 = السند 10,000 بالضبط · والباقي صفر**');
check(mb_strpos($r['reason'], 'Σ التخصيصات = السند') !== false,
      'والرسالةُ تقولها نصًّا: «Σ التخصيصات = السند»');

$q = $conn->query("SELECT ROUND(SUM(amount),2) s, COUNT(*) n FROM fin_collection_allocations
                    WHERE payment_id={$PAY}")->fetch_assoc();
check(abs((float) $q['s'] - 10000.0) < 0.005 && (int) $q['n'] === 3,
      '★★ وفي القاعدة **ثلاثةُ أسطرٍ مجموعُها 10,000**');
$kinds = array();
$rs = $conn->query("SELECT target_kind, target_ref, receivable_id, amount FROM fin_collection_allocations
                     WHERE payment_id={$PAY} ORDER BY id");
while ($x = $rs->fetch_assoc()) { $kinds[] = $x; }
check($kinds[0]['target_kind'] === 'advance' && $kinds[0]['receivable_id'] === null,
      '★ وسطرُ **المقدَّم بلا ذمّةٍ أصلًا** — والفاتورةُ وحدَها تحمل ذمّة');
check($kinds[1]['target_kind'] === 'invoice' && (int) $kinds[1]['receivable_id'] === $R1,
      'وسطرُ الفاتورة يحمل ذمّتَه ومرجعَه معًا');

$u1 = CS::unallocatedOf($gate, $PAY);
check(abs($u1['allocated'] - 10000.0) < 0.005 && abs($u1['unallocated']) < 0.005,
      '★★ و**العدّادُ على السند 10,000 والرصيدُ صفرٌ** — عمودًا مولَّدًا');

// ═══ الأثرُ على الأهداف ═══
head('★ **والهدفُ يتحدث في مستلمِه — لا رقمَ ثانٍ**');

$q = $conn->query("SELECT received_amount, state FROM contract_payment_schedule WHERE id={$ADV}")->fetch_assoc();
check(abs((float) $q['received_amount'] - 5000.0) < 0.005 && $q['state'] === 'completed',
      '★★ سطرُ المقدَّم صار **مستلمًا 5,000 ومكتملًا** — في `contract_payment_schedule` نفسِه');
$q = $conn->query("SELECT collected, state FROM fin_receivables WHERE id={$R1}")->fetch_assoc();
check(abs((float) $q['collected'] - 3000.0) < 0.005 && $q['state'] === 'collected',
      'والذمّةُ الأولى **محصَّلةٌ كاملًا** — في `fin_receivables` نفسِه');

$more = CS::allocate($conn, $gate, $CO, $PAY, array(
    array('target_kind' => 'milestone', 'target_ref' => $MST, 'amount' => 1)), $ACTOR);
check(!$more['ok'] && $more['code'] === 409 && mb_strpos($more['reason'], 'تتجاوز المتاحَ') !== false,
      '★ وتخصيصٌ إضافيٌّ على سندٍ **استُنفد** ⇒ **409** — ولو بجنيهٍ واحد');

$paid = CS::allocate($conn, $gate, $CO, $PAY, array(
    array('target_kind' => 'invoice', 'target_ref' => $R1, 'amount' => 1)), $ACTOR);
check(!$paid['ok'] && $paid['code'] === 409, 'وذمّةٌ **مسدَّدةٌ كاملًا** لا تُخصَّص لها ⇒ **409**');

// ═══ ② الرصيدُ الظاهر ═══
head('② **والرصيدُ غيرُ المخصَّص ظاهرٌ** — لا رقمٌ في رسالةٍ تختفي');

$conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
              method, bank_ref, received_on, amount, currency, state, created_at)
              VALUES ({$CO}, 'RCT2-{$MARK}', 'collection', 'customer', {$CLIENT},
                      'bank', 'BNK2-{$MARK}', '2088-02-10', 6000, 'USD', 'executed', NOW())");
$PAY2 = intval($conn->insert_id);
$r2 = CS::allocate($conn, $gate, $CO, $PAY2, array(
    array('target_kind' => 'milestone', 'target_ref' => $MST, 'amount' => 4000)), $ACTOR);
check($r2['ok'] && abs($r2['unallocated'] - 2000.0) < 0.005,
      '★★ سندٌ 6,000 على معلَمٍ 4,000 ⇒ **باقٍ 2,000 معلَنًا**: ' . mb_substr($r2['reason'], 0, 100));
$q = $conn->query("SELECT unallocated_amount FROM fin_payments WHERE id={$PAY2}")->fetch_assoc();
check(abs((float) $q['unallocated_amount'] - 2000.0) < 0.005,
      '★★★ و**الرقمُ مخزَّنٌ في العمود** — يبقى بعد إغلاق الشاشة ولا يختفي');
$pending = CS::unallocatedPayments($gate);
$found = false;
foreach ($pending as $p) { if ((int) $p['id'] === $PAY2) { $found = true; } }
check($found, '★ ولوحُ «السنداتُ ذاتُ رصيدٍ غيرِ مخصَّص» يعلنه — الفجوةُ تُرى');

$disb = $conn->query("SELECT id FROM fin_payments WHERE direction='disbursement'
                       AND company_id={$CO} LIMIT 1")->fetch_assoc();
if ($disb) {
    $wrong = CS::allocate($conn, $gate, $CO, (int) $disb['id'], array(
        array('target_kind' => 'invoice', 'target_ref' => $R2, 'amount' => 1)), $ACTOR);
    check(!$wrong['ok'] && $wrong['code'] === 422
          && mb_strpos($wrong['reason'], 'للقبض لا للصرف') !== false,
          'وسندُ **صرفٍ** لا يُخصَّص على ذمّةِ عميل ⇒ **422**');
} else {
    check(true, '(لا سندَ صرفٍ في النطاق — الفحصُ متروك)');
}

fwrite(STDOUT, "\n══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══\n");
exit($FAIL === 0 ? 0 : 1);
