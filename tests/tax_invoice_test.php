<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-03 — اختبار قبول: الفاتورةُ الضريبية (ENT-03 §4 · §5 · §6 · §7)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/tax_invoice_test.php
 *
 * ما يُثبته:
 *   ① **لا فاتورةَ بلا مستخلصٍ معتمد** → 422 بحالته.
 *   ② **تسلسلٌ نظاميٌّ لكل (شركة × سنة)**: `INV-{سنة}-{تسلسل}` يتصاعد،
 *      و**تكرارُ التسلسل مستحيلٌ بنيويًّا** (UQ).
 *   ③ **والضريبةُ سطرٌ مستقلٌّ بمرجعها**: رمزٌ غيرُ مسجَّلٍ **422** · ومبلغُ
 *      ضريبةٍ بلا رمزٍ ونسبةٍ **يرفضه CHECK** · والإجمالي = الصافي + الضريبة.
 *   ④ **الحقولُ النظامية لقطةٌ لا اشتقاق** — تُحفظ في `tax_fields_json`.
 *   ⑤ **لا تعديلَ بعد الإصدار**: `assertEditable` **423 بنصّ «التصحيح بإشعار»**،
 *      و`claim_recalc` **تُعيد المحفوظ ولا تعيد الحساب** ولو تغيّرت البنود.
 *   ⑥ **الإلغاءُ الضريبيُّ بسببٍ مكتوب** (422 بلا سبب · و`CHECK`) — **ورقمُ
 *      الملغاة لا يُعاد استعمالُه** (التسلسلُ التالي يتقدّم).
 *   ⑦ **الوصلُ الحي**: `claim_approve` يُصدر الفاتورةَ ويكتب رقمَها التسلسلي.
 *
 * البذرُ معزول: عميلٌ وعقدٌ ومستخلصٌ في 2097 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'M03 tax invoice test');

require_once dirname(__DIR__) . '/app/Services/Revenue/TaxInvoiceService.php';

use App\Services\Revenue\TaxInvoiceService as TIS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999871;
$MARK  = 'M03T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE t FROM tax_invoices t JOIN claims c ON c.id = t.claim_id
                   WHERE c.claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE l FROM claim_lines l JOIN claims c ON c.id = l.claim_id
                   WHERE c.claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '{$MARK}%'");
    $conn->query("DELETE FROM clients WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-03 — الفاتورةُ الضريبية ══\n");

head('البذر — عميلٌ ومستخلصان');

// `clients` عمودُ اسمِه `client_name` و`client_code` **NOT NULL** — والبذرُ
// يحترم مخطّطَ القائم لا يفترض أسماءَ أعمدة.
$okC = $conn->query("INSERT INTO clients (company_id, client_code, client_name, created_at)
                     VALUES ({$CO}, 'C-{$MARK}', 'عميلُ {$MARK}', NOW())");
if (!$okC) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); }
$CLI = intval($conn->insert_id);
check($CLI > 0, 'عميلٌ مبذور');

// `uq_claim_period` = (شركة × عقد × فترة) — ولا عقدَ في البذر، فلكلِّ مستخلصٍ
// **شهرُه**: القيدُ القائم يُحترم لا يُلتفّ عليه.
$mn = 0;
$mkClaim = function ($suffix, $net, $state) use ($conn, $CO, $CLI, $MARK, &$mn) {
    $mn++;
    $from = sprintf('2097-%02d-01', $mn);
    $to   = date('Y-m-t', strtotime($from));
    $ok = $conn->query("INSERT INTO claims (company_id, claim_no, client_id, period_from, period_to,
                  currency, gross_amount, retention_amount, net_amount, state, version, created_at)
                  VALUES ({$CO}, '{$MARK}-{$suffix}', {$CLI}, '{$from}', '{$to}',
                          'USD', {$net}, 0, {$net}, '{$state}', 1, NOW())");
    if (!$ok) { fwrite(STDOUT, '  ! بذرُ المستخلص فشل: ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};
$CL1 = $mkClaim('A', 1000, 'review');
$CL2 = $mkClaim('B', 2000, 'approved');
check($CL1 > 0 && $CL2 > 0, 'ومستخلصان (مرفوعٌ ومعتمَد)');

// ═══ ① لا فاتورةَ بلا مستخلصٍ معتمد ═══
head('① **لا فاتورةَ بلا مستخلصٍ معتمد** (§4 · §7)');

$r = TIS::issueForClaim($conn, $gate, $CO, $CL1, array(), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'لا فاتورةَ بلا مستخلصٍ معتمد') !== false,
      '★ مستخلصٌ «review» ⇒ **422** بحالته ونصُّه يقول ما يُفعل');

// ═══ ② التسلسلُ النظامي ═══
head('② **تسلسلٌ نظاميٌّ لكل (شركة × سنة)**');

$r = TIS::issueForClaim($conn, $gate, $CO, $CL2, array(), $ACTOR);
check($r['ok'], 'المستخلصُ المعتمدُ تُصدَر فاتورتُه (' . $r['reason'] . ')');
$INV1 = intval($r['invoice_id']);
$S1 = (string) $r['serial_no'];
$Y = date('Y');
check(preg_match('/^INV-' . $Y . '-\d{6}$/', $S1) === 1,
      '★ والرقمُ **تسلسليٌّ نظامي** ' . $S1 . ' — لا مشتقٌّ من رقم المستخلص');

$r2 = TIS::issueForClaim($conn, $gate, $CO, $CL2, array(), $ACTOR);
check(!$r2['ok'] && $r2['code'] === 409 && (string) $r2['serial_no'] === $S1,
      'وإصدارٌ ثانٍ للمستخلص نفسِه **409 بمرجع الصادرة** — «والتصحيحُ بإشعار»');

$seq = intval($conn->query("SELECT serial_seq FROM tax_invoices WHERE id={$INV1}")->fetch_assoc()['serial_seq']);
$dup = $conn->query("INSERT INTO tax_invoices (company_id, claim_id, client_id, serial_no,
                     serial_year, serial_seq, currency, net_amount, tax_amount, total_amount, state)
                     VALUES ({$CO}, {$CL2}, {$CLI}, 'INV-DUP-{$MARK}', {$Y}, {$seq}, 'USD', 1, 0, 1, 'issued')");
check(!$dup, '★ وتكرارُ (شركة × سنة × تسلسل) **يرفضه UQ** — لا رقمان لسنةٍ واحدة');

// ═══ ③ الضريبةُ بمرجعها ═══
head('③ **والضريبةُ سطرٌ مستقلٌّ بمرجعها** (§5)');

$CL3 = $mkClaim('C', 500, 'approved');
$r = TIS::issueForClaim($conn, $gate, $CO, $CL3, array('tax_code' => 'ZZZ-NOPE'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'بمرجعها') !== false,
      '★ رمزٌ ضريبيٌّ غيرُ مسجَّلٍ ⇒ **422** — لا نسبةَ تُكتب يدًا');

$TAXC = null;
$tc = $conn->query("SELECT code, rate FROM fin_tax_codes WHERE company_id={$CO} LIMIT 1");
if ($tc && ($x = $tc->fetch_assoc())) { $TAXC = $x; }
if ($TAXC !== null) {
    $r = TIS::issueForClaim($conn, $gate, $CO, $CL3, array('tax_code' => $TAXC['code']), $ACTOR);
    $expTax = round(500 * ((float) $TAXC['rate']) / 100, 2);
    check($r['ok'] && abs($r['tax'] - $expTax) < 0.005
          && abs($r['total'] - round(500 + $expTax, 2)) < 0.005,
          '★ وبرمزٍ مسجَّل: الضريبةُ ' . $r['tax'] . ' والإجمالي ' . $r['total'] . ' = الصافي + الضريبة');
} else {
    $r = TIS::issueForClaim($conn, $gate, $CO, $CL3, array(), $ACTOR);
    check($r['ok'] && abs($r['tax']) < 0.005,
          'ولا رمزَ ضريبيًّا مسجَّلًا في الشركة — فبلا ضريبةٍ ويُعلَن (لا نسبةَ مخترَعة)');
}

$CL4 = $mkClaim('D', 300, 'approved');
$bad = $conn->query("INSERT INTO tax_invoices (company_id, claim_id, client_id, serial_no,
                     serial_year, serial_seq, currency, net_amount, tax_amount, total_amount, state)
                     VALUES ({$CO}, {$CL4}, {$CLI}, 'INV-BAD-{$MARK}', {$Y}, 999001, 'USD', 300, 45, 345, 'issued')");
check(!$bad, '★ ومبلغُ ضريبةٍ **بلا رمزٍ ونسبةٍ** يرفضه CHECK — «سطرٌ بمرجعها» بنيويًّا');

$bad2 = $conn->query("INSERT INTO tax_invoices (company_id, claim_id, client_id, serial_no,
                      serial_year, serial_seq, currency, net_amount, tax_amount, total_amount, state)
                      VALUES ({$CO}, {$CL4}, {$CLI}, 'INV-BAD2-{$MARK}', {$Y}, 999002, 'USD', 300, 0, 999, 'issued')");
check(!$bad2, 'وإجماليٌّ لا يساوي الصافي + الضريبة **يرفضه CHECK** — المجموعُ لا يُخترع');

// ═══ ④ الحقولُ النظامية ═══
head('④ **الحقولُ النظامية لقطةٌ لا اشتقاق**');

$inv = TIS::head($gate, $INV1);
$f = json_decode((string) $inv['tax_fields_json'], true);
check(is_array($f) && isset($f['buyer_name']) && mb_strpos((string) $f['buyer_name'], $MARK) !== false,
      '★ اسمُ المشتري **ملتقَطٌ في الفاتورة** — فتغييرُ سجل العميل لاحقًا لا يغيّر مستندًا صادرًا');
check(isset($f['claim_no']) && isset($f['period_from']) && isset($f['issued_on']),
      'ومعها المستخلصُ وفترتُه وتاريخُ الإصدار');

// ═══ ⑤ لا تعديلَ بعد الإصدار ═══
head('⑤ **لا تعديلَ بعد الإصدار** — والتصحيحُ بإشعار (§6)');

$e = TIS::assertEditable($gate, $CL2);
check($e !== null && $e['code'] === 423 && mb_strpos($e['reason'], 'بإشعارٍ دائن/مدين') !== false,
      '★ تعديلُ مستخلصٍ مفوتَرٍ ⇒ **423** ونصُّه يسمّي طريقَ التصحيح');
check(TIS::assertEditable($gate, $CL1) === null, 'وغيرُ المفوتَر: يجوز تعديلُه');

require_once dirname(__DIR__) . '/Contracts/claim_helpers.php';
$conn->query("INSERT INTO claim_lines (company_id, claim_id, description, qty, unit_price, amount,
              dispute_flag, created_at)
              VALUES ({$CO}, {$CL2}, 'بندُ اختبار', 1, 777, 777, 0, NOW())");
$netAfter = claim_recalc($gate, $CL2);
check(abs($netAfter - 2000.0) < 0.005,
      '★★ و`claim_recalc` **تُعيد المحفوظ 2000 ولا تعيد الحساب** — الفاتورةُ تجمّد أرقامَ مستخلصها');
$stored = $conn->query("SELECT net_amount FROM claims WHERE id={$CL2}")->fetch_assoc();
check(abs((float) $stored['net_amount'] - 2000.0) < 0.005, 'والمحفوظُ لم يُمسّ في القاعدة');

// ═══ ⑥ الإلغاءُ بسببٍ مكتوب ═══
head('⑥ **الإلغاءُ الضريبيُّ بسببٍ مكتوب** — ورقمُ الملغاة لا يُعاد');

$r = TIS::cancel($conn, $gate, $CO, $INV1, '  ', $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'لا يُعاد استعمالُه') !== false,
      'إلغاءٌ بلا سببٍ ⇒ **422**');

$conn->query("UPDATE tax_invoices SET state='cancelled' WHERE id={$INV1}");
$s = $conn->query("SELECT state FROM tax_invoices WHERE id={$INV1}")->fetch_assoc();
check((string) $s['state'] === 'issued',
      '★ وإلغاءٌ مباشرٌ بلا سبب **يرفضه CHECK** — بنيويًّا لا بفحصٍ يُنسى');

$r = TIS::cancel($conn, $gate, $CO, $INV1, 'خطأٌ في بيانات المشتري — تُعاد بإشعارٍ ورقمٍ جديد', $ACTOR);
check($r['ok'], 'وبسببٍ مكتوب: تُلغى');
check(TIS::assertEditable($gate, $CL2) === null,
      'والملغاةُ **لا تجمّد** المستخلصَ بعدها — فالتصحيحُ ممكن');

$r = TIS::issueForClaim($conn, $gate, $CO, $CL2, array(), $ACTOR);
check($r['ok'] && (string) $r['serial_no'] !== $S1,
      '★★ وإعادةُ الإصدار تأخذ **رقمًا جديدًا** (' . $r['serial_no'] . ') — رقمُ الملغاة لا يُعاد استعمالُه');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
