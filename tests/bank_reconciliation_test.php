<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-13 — اختبار قبول: المطابقةُ البنكية (SPEC-01 #19 · UX-02 §15.2-ب)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/bank_reconciliation_test.php
 *
 * ما يُثبته:
 *   ① **استيرادٌ Idempotent بمفتاح السطر**: الملفُّ نفسُه مرتين ⇒ **صفرُ سطرٍ
 *      مكرر** · وسطرٌ **بلا مرجعٍ بنكيٍّ يُرفض** ولا يُخترع له مفتاح.
 *   ② **المضاهاةُ الآلية بقاعدتها**: بالمرجع أولًا ثم (المبلغ + التاريخ ± أيام)
 *      — **والقاعدةُ المطبَّقةُ تُحفظ** · و**بلا نظيرٍ يُعلَن** ولا يُخترع سند.
 *   ③ **ولا سندٌ يُطابَق مرتين**.
 *   ④ **والفرقُ يُفتح بسببٍ** (422 بلا سبب · و`CHECK` يرفض الكتابةَ المباشرة)
 *      **ويُحسم بقرارٍ يولّد قيدَ تسويةٍ بمرجع الفرق** — والسندُ لا يُمسّ.
 *   ⑤ **ولا إقفالَ وفرقٌ مفتوح** (423 بأسمائها) — وبعد الحسم يُقفل.
 *   ⑥ **والفرقُ عمودٌ مولَّدٌ لا يُكتب**.
 *
 * البذرُ معزول: حسابٌ بنكيٌّ وكشفٌ وسنداتٌ في 2085 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'H13 recon test');

require_once dirname(__DIR__) . '/app/Services/Finance/BankReconService.php';

use App\Services\Finance\BankReconService as BRS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$PREP  = 999131;
$MARK  = 'H13T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM fin_financial_events WHERE idempotency_key IN (
                    SELECT CONCAT('recon_adj:', m.id) FROM bank_recon_matches m
                      JOIN bank_statement_lines l ON l.id = m.statement_line_id
                      JOIN bank_statements s ON s.id = l.statement_id
                     WHERE s.statement_ref LIKE '%{$MARK}%')");
    $conn->query("DELETE m FROM bank_recon_matches m
                    JOIN bank_statement_lines l ON l.id = m.statement_line_id
                    JOIN bank_statements s ON s.id = l.statement_id
                   WHERE s.statement_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM bank_statement_lines l JOIN bank_statements s ON s.id = l.statement_id
                   WHERE s.statement_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM bank_statements WHERE statement_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_payments WHERE payment_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_bank_accounts WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-13 — المطابقةُ البنكية ══\n");

head('البذر — حسابٌ بنكيٌّ وثلاثةُ سنداتٍ في النظام');

$conn->query("INSERT INTO fin_bank_accounts (company_id, name, bank_name, account_number,
              currency, opening_balance, active, created_at, updated_at)
              VALUES ({$CO}, 'حسابُ {$MARK}', 'بنكُ الاختبار', 'ACC-{$MARK}', 'SDG', 0, 1, NOW(), NOW())");
$ACC = intval($conn->insert_id);

$mkPay = function ($no, $dir, $amt, $bref, $date) use ($conn, $CO, $MARK) {
    $conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type,
                  party_ref, amount, currency, method, bank_ref, received_on, state, created_at)
                  VALUES ({$CO}, '{$MARK}-{$no}', '{$dir}', 'client', 1, {$amt}, 'SDG', 'bank',
                          '{$bref}', '{$date}', 'draft', '{$date} 09:00:00')");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
// P1 يُطابَق بالمرجع · P2 بالمبلغ والتاريخ · P3 بمبلغٍ مختلفٍ ⇒ فرق
// ⚠ الاتجاهُ `disbursement` لا `payment` — وENUM يبتلع الخطأ صامتًا (گوتشا مقيسة)
$P1 = $mkPay('P1', 'collection', 1000, 'BR-' . $MARK . '-A', '2085-03-10');
$P2 = $mkPay('P2', 'collection', 2500, 'BR-' . $MARK . '-ZZ', '2085-03-12');
$P3 = $mkPay('P3', 'disbursement', 3000, 'BR-' . $MARK . '-C', '2085-03-15');
check($ACC > 0 && $P1 > 0 && $P2 > 0 && $P3 > 0, "حسابٌ #{$ACC} وثلاثةُ سندات");

$head = array('bank_account_id' => $ACC, 'statement_ref' => 'ST-' . $MARK,
              'period_from' => '2085-03-01', 'period_to' => '2085-03-31',
              'opening_balance' => 0, 'closing_balance' => 500, 'currency' => 'SDG');
$lines = array(
    array('txn_date' => '2085-03-10', 'direction' => 'deposit', 'amount' => 1000,
          'bank_ref' => 'BR-' . $MARK . '-A', 'description' => 'تحصيلٌ بمرجعه'),
    array('txn_date' => '2085-03-13', 'direction' => 'deposit', 'amount' => 2500,
          'bank_ref' => 'BR-' . $MARK . '-B', 'description' => 'تحصيلٌ بمبلغه وتاريخه'),
    array('txn_date' => '2085-03-15', 'direction' => 'withdrawal', 'amount' => 3200,
          'bank_ref' => 'BR-' . $MARK . '-C', 'description' => 'صرفٌ بفرقٍ 200'),
    array('txn_date' => '2085-03-20', 'direction' => 'deposit', 'amount' => 777,
          'bank_ref' => 'BR-' . $MARK . '-D', 'description' => 'بلا نظيرٍ في النظام'),
    array('txn_date' => '2085-03-21', 'direction' => 'deposit', 'amount' => 50,
          'bank_ref' => '', 'description' => 'سطرٌ بلا مرجعٍ — يُرفض'),
);

// ═══ ① الاستيراد ═══
head('① **استيرادٌ Idempotent بمفتاح السطر**');

$r = BRS::import($conn, $gate, $CO, $head, $lines, $PREP);
check($r['ok'] && $r['inserted'] === 4 && count($r['rejected']) === 1,
      '★★ أُدرج **4 أسطر** و**رُفض سطرٌ بلا مرجعٍ بنكيّ** — ولا يُخترع له مفتاح');
check(mb_strpos((string) $r['rejected'][0]['reason'], 'بلا مرجعٍ بنكيّ') !== false,
      'وسببُ الرفض مكتوب: ' . $r['rejected'][0]['reason']);
$SID = (int) $r['statement_id'];

$r2 = BRS::import($conn, $gate, $CO, $head, $lines, $PREP);
check($r2['ok'] && (int) $r2['statement_id'] === $SID && $r2['inserted'] === 0 && $r2['skipped'] === 4,
      '★★ وإعادةُ استيراد الملف نفسِه: **صفرُ سطرٍ جديد و4 متخطّاة** — والكشفُ نفسُه لا كشفٌ ثانٍ');
$n = intval($conn->query("SELECT COUNT(*) c FROM bank_statement_lines WHERE statement_id={$SID}")
                  ->fetch_assoc()['c']);
check($n === 4, 'والعددُ في القاعدة **4** بعد استيرادين');

$r3 = BRS::import($conn, $gate, $CO, array_merge($head, array('statement_ref' => '')), $lines, $PREP);
check(!$r3['ok'] && $r3['code'] === 422, 'وكشفٌ بلا مرجعٍ ⇒ **422**');

// ═══ ② المضاهاةُ بقاعدتها ═══
head('② **المضاهاةُ الآلية بقاعدتها**');

$m = BRS::autoMatch($conn, $gate, $CO, $SID, $PREP);
check($m['ok'] && $m['matched'] === 2 && $m['differences'] === 1 && $m['none'] === 1,
      '★★ **مطابقان · فرقٌ واحد · بلا نظيرٍ واحد** — ' . $m['reason']);

$rows = BRS::linesOf($gate, $SID);
$byRef = array();
foreach ($rows as $x) { $byRef[(string) $x['bank_ref']] = $x; }

$a = $byRef['BR-' . $MARK . '-A'];
check((int) $a['payment_id'] === $P1 && mb_strpos((string) $a['rule_note'], 'المرجعُ البنكيُّ مطابق') !== false,
      '★ السطرُ A طُوبق **بالمرجع** — والقاعدةُ محفوظةٌ في الصف');
$b = $byRef['BR-' . $MARK . '-B'];
check((int) $b['payment_id'] === $P2 && mb_strpos((string) $b['rule_note'], 'المبلغُ 2500') !== false,
      '★★ والسطرُ B طُوبق **بالمبلغ والتاريخ ± 3 أيام** رغم اختلاف المرجع — والقاعدةُ مسمّاة');
$d = $byRef['BR-' . $MARK . '-D'];
check($d['payment_id'] === null && (string) $d['match_state'] === 'no_counterpart'
      && (string) $d['match_row_state'] === 'open_difference',
      '★★ والسطرُ D **بلا نظيرٍ يُعلَن** ويُفتح فرقًا يحجب الإقفال — ولا يُخترع له سند');

$c = $byRef['BR-' . $MARK . '-C'];
check((int) $c['payment_id'] === $P3 && abs((float) $c['difference'] - 200.0) < 0.005
      && (string) $c['match_state'] === 'difference',
      '★★ والسطرُ C **فرقُه 200** (3200 بنكًا مقابل 3000 نظامًا) — محسوبًا لا مكتوبًا');

// ═══ ⑥ الفرقُ مولَّد ═══
head('⑥ **والفرقُ عمودٌ مولَّدٌ لا يُكتب**');
$bad = $conn->query("UPDATE bank_recon_matches SET difference=1 WHERE id=" . intval($c['match_id']));
$chk = $conn->query("SELECT difference FROM bank_recon_matches WHERE id=" . intval($c['match_id']))->fetch_assoc();
check($bad === false || abs((float) $chk['difference'] - 200.0) < 0.005,
      '★★ كتابةُ الفرق **ترفضها البنية** — فلا ينحرف عن طرفيه');

// ═══ ③ ولا سندٌ يُطابَق مرتين ═══
head('③ **ولا سندٌ يُطابَق مرتين**');
$extra = array(array('txn_date' => '2085-03-10', 'direction' => 'deposit', 'amount' => 1000,
                     'bank_ref' => 'BR-' . $MARK . '-A2', 'description' => 'سطرٌ يشبه A'));
BRS::import($conn, $gate, $CO, $head, $extra, $PREP);
$m2 = BRS::autoMatch($conn, $gate, $CO, $SID, $PREP);
$rows = BRS::linesOf($gate, $SID);
foreach ($rows as $x) { if ((string) $x['bank_ref'] === 'BR-' . $MARK . '-A2') { $a2 = $x; } }
check(isset($a2) && $a2['payment_id'] === null,
      '★★ سطرٌ ثانٍ بمبلغ 1000 وتاريخه **لا يلتقط السندَ المرتبط سلفًا** — بلا نظير');

// ═══ ④ الفرقُ يُفتح بسببٍ ويُحسم بقرار ═══
head('④ **والفرقُ يُفتح بسببٍ ويُحسم بقرارٍ يولّد قيدَ تسوية**');

$MID = (int) $c['match_id'];
$o = BRS::openDifference($conn, $gate, $CO, $MID, '   ', $PREP);
check(!$o['ok'] && $o['code'] === 422 && mb_strpos($o['reason'], 'سببُ الفرق إلزامي') !== false,
      'فتحُ فرقٍ بلا سببٍ ⇒ **422**');

$conn->query("UPDATE bank_recon_matches SET state='open_difference', difference_reason=NULL
               WHERE id={$MID}");
$st = $conn->query("SELECT state FROM bank_recon_matches WHERE id={$MID}")->fetch_assoc();
check((string) $st['state'] === 'matched',
      '★★ وفرقٌ مكتوبٌ مباشرةً بلا سببٍ **يرفضه CHECK** — بنيويًّا لا بفحصٍ يُنسى');

$o = BRS::openDifference($conn, $gate, $CO, $MID, 'رسومٌ بنكيةٌ 200 لم تُسجَّل في السند', $PREP);
check($o['ok'], 'وبسببٍ مكتوبٍ يُفتح الفرق');

$res = BRS::resolveDifference($conn, $gate, $CO, $MID, 'adjust', '', $PREP);
check(!$res['ok'] && $res['code'] === 422, 'وحسمٌ بلا سببٍ ⇒ **422**');

$res = BRS::resolveDifference($conn, $gate, $CO, $MID, 'adjust', 'قيدُ رسومٍ بنكية', $PREP);
check($res['ok'] && (int) $res['event_id'] > 0 && abs($res['amount'] - 200.0) < 0.005,
      '★★ والحسمُ **يولّد قيدَ تسويةٍ #' . intval($res['event_id']) . ' بفرقِ 200**');

$ev = $conn->query("SELECT event_key, idempotency_key, amount FROM fin_financial_events
                     WHERE idempotency_key='recon_adj:{$MID}'")->fetch_assoc();
check($ev && (string) $ev['event_key'] === 'finance.recon_adjustment.posted'
      && abs((float) $ev['amount'] - 200.0) < 0.005,
      '★ `finance.recon_adjustment.posted` **بمرجع الفرق** لا بمرجع السند');

$pay = $conn->query("SELECT amount FROM fin_payments WHERE id={$P3}")->fetch_assoc();
check(abs((float) $pay['amount'] - 3000.0) < 0.005,
      '★★ **والسندُ الأصليُّ لم يُمسّ** (3000 كما كان) — «التصحيحُ بقيدٍ لا بتعديل»');

$res2 = BRS::resolveDifference($conn, $gate, $CO, $MID, 'adjust', 'ثانٍ', $PREP);
check(!$res2['ok'] && $res2['code'] === 409, 'وحسمٌ ثانٍ ⇒ **409** بمرجع قيده');

// ═══ ⑤ لا إقفالَ وفرقٌ مفتوح ═══
head('⑤ **ولا إقفالَ وفرقٌ مفتوح**');

$cl = BRS::close($conn, $gate, $CO, $SID, $PREP);
check(!$cl['ok'] && $cl['code'] === 423 && mb_strpos($cl['reason'], 'لا إقفالَ وفرقٌ مفتوح') !== false,
      '★★ الإقفالُ ⇒ **423** والأسبابُ مسمّاة: ' . mb_substr($cl['reason'], 0, 90));

// حسمُ البواقي: بلا النظيرَين
foreach (BRS::linesOf($gate, $SID) as $x) {
    if ((string) $x['match_row_state'] === 'open_difference' && $x['match_id'] !== null) {
        BRS::resolveDifference($conn, $gate, $CO, (int) $x['match_id'], 'reject',
            'إيداعٌ يخصُّ شركةً شقيقة — لا نظيرَ له عندنا', $PREP);
    }
}
$cl = BRS::close($conn, $gate, $CO, $SID, $PREP);
check(!$cl['ok'] && $cl['code'] === 423,
      '★ ومع ذلك لا يُقفل: أسطرُ «بلا نظير» بقيت غيرَ مستقرةٍ على «مطابق» — ' . mb_substr($cl['reason'], 0, 70));

// وسمُها مطابقةً بقرارٍ صريح (القبولُ اليدويُّ بعد الحسم)
$conn->query("UPDATE bank_statement_lines l JOIN bank_recon_matches m ON m.statement_line_id=l.id
                 SET l.match_state='matched'
               WHERE l.statement_id={$SID} AND m.state='rejected'");
$cl = BRS::close($conn, $gate, $CO, $SID, $PREP);
check($cl['ok'], '★★ وبعد استقرار كل سطرٍ: **أُقفل الكشفُ بصفر فرقٍ مفتوح**');

$s = $conn->query("SELECT state, closed_at, closed_by FROM bank_statements WHERE id={$SID}")->fetch_assoc();
check((string) $s['state'] === 'closed' && $s['closed_at'] !== null && $s['closed_by'] !== null,
      'والإقفالُ بوقته ومُقفِله — و`CHECK` يوجبهما');

$r4 = BRS::import($conn, $gate, $CO, $head, $lines, $PREP);
check(!$r4['ok'] && $r4['code'] === 423, '★ واستيرادٌ على كشفٍ مقفل ⇒ **423**');

// ═══ البطاقات ═══
head('بطاقتا §19 — نسبةُ المطابقة والفروقُ المفتوحة');
$st = BRS::stats($gate, $SID);
check($st['lines'] === 5 && $st['open_diff'] === 0 && $st['rate'] > 0,
      'الأسطرُ 5 · نسبةُ المطابقة ' . $st['rate'] . '٪ · فروقٌ مفتوحة ' . $st['open_diff']);

// ═══ العزل ═══
head('العزلُ محفوظ');
$_SESSION['user']['company_id'] = 1;
$otherGate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::fromSession());
$leak = $otherGate->selectOne('bank_statements', array('where' => array('id' => $SID)));
check($leak === null, '★ كشفُ شركةٍ لا يُقرأ من نطاقٍ آخر — صفرُ تسريب');
$_SESSION['user']['company_id'] = $CO;

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
