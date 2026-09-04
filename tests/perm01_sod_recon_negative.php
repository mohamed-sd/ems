<?php
/**
 * tests/perm01_sod_recon_negative.php — PERM-01 §3-④ · الاختبارُ السالبُ للحدِّ الصلب
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المصدرُ الحاكم** `FIN-TRE-01 · FTRE-0061` واختبارُ قبولِه المكتوبُ فيه:
 *   «منفِّذُ الدفعِ يُرفض إعدادُه المطابقةَ». وهذا الملفُّ يُثبته حيًّا لا نصًّا.
 *
 * ⛔ **والاختبارُ السالبُ بلا ضابطٍ موجبٍ لا يُثبت شيئًا**: منعٌ يقع على الجميع
 *   ليس فصلَ واجبات بل تعطيل. فلكلِّ منعٍ هنا نظيرُه المسموح.
 *
 * ◆ ويُثبت الشقَّين معًا: بوّابةُ **الإنشاء** (`autoMatch`) وبوّابةُ **الاعتماد**
 *   (`close`) — فمن نفَّذ لا يُنشئ المطابقةَ ولا يُقفلها ولو أنشأها غيرُه.
 *
 * التشغيل: php tests/perm01_sod_recon_negative.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'PERM01 sod test');

require_once dirname(__DIR__) . '/app/Services/Finance/BankReconService.php';
require_once dirname(__DIR__) . '/app/Services/Finance/ReconSodGuard.php';

use App\Services\Finance\BankReconService as BRS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate = ems_tenant_db();
$CO   = 4;
$MARK = 'P01SOD' . getmypid();

/* الفاعلان: المنفِّذُ ومَن لم ينفِّذ — والفرقُ بينهما هو كلُّ ما يُقاس. */
$EXECUTOR = 907;   /* حاملُ دورِ منفِّذِ المدفوعاتِ البنكية */
$OTHER    = 908;   /* حاملُ دورِ مُعِدِّ المطابقة */

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE m FROM bank_recon_matches m
                    JOIN bank_statement_lines l ON l.id = m.statement_line_id
                    JOIN bank_statements s ON s.id = l.statement_id
                   WHERE s.statement_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM bank_statement_lines l JOIN bank_statements s ON s.id = l.statement_id
                   WHERE s.statement_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM bank_statements WHERE statement_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_payments WHERE payment_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM fin_bank_accounts WHERE name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM sec_sod_denials WHERE source_ref LIKE '%{$MARK}%' OR detail LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ PERM-01 §3-④ — مَن نفَّذ لا يطابق ما نفَّذه (FTRE-0061) ══\n");

/* ═══ البذر ═══ */
head('البذر — حسابٌ وسندٌ نفَّذه ' . $EXECUTOR . ' وكشفٌ بسطرٍ يطابقه');
$conn->query("INSERT INTO fin_bank_accounts (company_id, name, bank_name, account_number,
              currency, opening_balance, active, created_at, updated_at)
              VALUES ({$CO}, 'حسابُ {$MARK}', 'بنكُ الاختبار', 'ACC-{$MARK}', 'SDG', 0, 1, NOW(), NOW())");
$ACC = (int) $conn->insert_id;

$conn->query("INSERT INTO fin_payments (company_id, payment_no, direction, party_type, party_ref,
              amount, currency, method, bank_ref, received_on, state, executed_by, created_at)
              VALUES ({$CO}, '{$MARK}-P1', 'collection', 'client', 1, 1000, 'SDG', 'bank',
                      'BR-{$MARK}-A', '2085-04-10', 'draft', {$EXECUTOR}, '2085-04-10 09:00:00')");
$P1 = (int) $conn->insert_id;
check($ACC > 0 && $P1 > 0, "حسابٌ #{$ACC} وسندٌ #{$P1} منفَّذٌ بـ{$EXECUTOR}");

$headRow = array('bank_account_id' => $ACC, 'statement_ref' => 'ST-' . $MARK,
                 'period_from' => '2085-04-01', 'period_to' => '2085-04-30',
                 'opening_balance' => 0, 'closing_balance' => 1000, 'currency' => 'SDG');
$lines = array(array('txn_date' => '2085-04-10', 'direction' => 'deposit', 'amount' => 1000,
                     'bank_ref' => 'BR-' . $MARK . '-A', 'description' => 'تحصيلٌ بمرجعه'));
$imp = BRS::import($conn, $gate, $CO, $headRow, $lines, $OTHER);
$SID = (int) ($imp['statement_id'] ?? 0);
check($imp['ok'] && $SID > 0, "كشفٌ #{$SID} بسطرٍ واحد");

/* ═══ ① السالب — المنفِّذُ يُرفض ═══ */
head('① **السالب** — المنفِّذُ نفسُه يحاول المطابقة');
$r = BRS::autoMatch($conn, $gate, $CO, $SID, $EXECUTOR);
check((int) $r['code'] === 403, '★★ رُدَّ بـ**403** — والمقيسُ ' . (int) $r['code']);
check(mb_strpos((string) $r['reason'], 'فصل الواجبات') !== false,
      'والسببُ يُسمّي الضابطَ لا رسالةً عامّة: ' . mb_substr((string) $r['reason'], 0, 60));
$w = (int) $conn->query("SELECT COUNT(*) c FROM bank_recon_matches m
                           JOIN bank_statement_lines l ON l.id = m.statement_line_id
                          WHERE l.statement_id = {$SID}")->fetch_assoc()['c'];
check($w === 0, '★★ و**صفرُ سطرٍ كُتب** — الردُّ سبق أوّلَ كتابةٍ لا بعدَها');

$d = (int) $conn->query("SELECT COUNT(*) c FROM sec_sod_denials
                          WHERE pair_code='SOD-06' AND attempted_by={$EXECUTOR}
                            AND source_ref='statement:{$SID}'")->fetch_assoc()['c'];
check($d === 1, '★★ والمنعُ **مقيَّدٌ في sec_sod_denials** — منعٌ لا يراه السجلُّ لا يُحتسب');

/* ═══ ② الموجب — غيرُ المنفِّذِ يمضي ═══ */
head('② **الضابطُ الموجب** — فاعلٌ آخرُ يطابق');
$r2 = BRS::autoMatch($conn, $gate, $CO, $SID, $OTHER);
check($r2['ok'] && (int) $r2['code'] === 200,
      '★★ مضى بـ200 — فالمنعُ **فصلُ واجباتٍ لا تعطيلٌ عامّ**');
check((int) $r2['matched'] === 1, 'وطابق سطرًا واحدًا: ' . (int) $r2['matched']);

/* ═══ ③ بوّابةُ الاعتماد — المنفِّذُ لا يُقفل ولو طابق غيرُه ═══ */
head('③ **بوّابةُ الإقفال** — المنفِّذُ لا يعتمد مطابقةَ دفعتِه');
$r3 = BRS::close($conn, $gate, $CO, $SID, $EXECUTOR);
check((int) $r3['code'] === 403, '★★ الإقفالُ رُدَّ بـ**403** — والمقيسُ ' . (int) $r3['code']);
/* ⛔ العدُّ يُقيَّد **بكشفِ هذه الجولة**: صفوفُ جولةٍ سابقةٍ تحمل وسمًا آخرَ
   فتُورَث في عدٍّ مطلقٍ ويرسب الفاحصُ بلا عطبٍ في المنتَج. */
$d2 = (int) $conn->query("SELECT COUNT(*) c FROM sec_sod_denials
                           WHERE pair_code='SOD-06' AND attempted_by={$EXECUTOR}
                             AND source_ref='statement:{$SID}'")->fetch_assoc()['c'];
check($d2 === 2, 'ومنعان مقيَّدان لهذا الكشف (إنشاءٌ وإقفال): ' . $d2);

$r4 = BRS::close($conn, $gate, $CO, $SID, $OTHER);
check((int) $r4['code'] !== 403,
      '★★ وغيرُ المنفِّذِ لا يردُّه هذا الحارس (رمزُه ' . (int) $r4['code'] . ') — ضابطٌ موجب');

/* ═══ الحصيلة ═══ */
fwrite(STDOUT, "\n──────────────────────────────────────────────────────────\n");
fwrite(STDOUT, "النتيجة: {$PASS} نجاح · {$FAIL} رسوب\n");
fwrite(STDOUT, $FAIL === 0
    ? "✔ P0_SOD_RUNTIME_NEGATIVE_TESTS = 100% على تركيبةِ SOD-06\n"
    : "✘ الحدُّ الصلبُ غيرُ مُثبَت\n");
exit($FAIL === 0 ? 0 : 1);
