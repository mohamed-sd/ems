<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-13 — اختبار قبول: حالتا Invoiced وClosed ومطابقةُ فاتورة المورد
 *        (ENT-02 §4 · §5)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/settlement_invoice_close_test.php
 *
 * ما يُثبته:
 *   ① الحالتان الناقصتان صارتا في ENUM — والدورةُ الثمانيةُ كاملة.
 *   ② **الفاتورةُ تُطابَق ولا تعدّل**: مبلغٌ مطابقٌ ⇒ `invoiced` بفرقٍ صفر.
 *   ③ **«فرقٌ بقرارٍ لا تعديلًا صامتًا»**: اختلافٌ بلا سببٍ ومستند → 422 ·
 *      وبهما يُسجَّل الفرقُ **والصافي لا يُمسّ** (§5: الاعترافُ من التسوية).
 *   ④ **قيدُ CHECK بنيويٌّ**: فرقٌ بلا سببٍ ومستندٍ **مستحيلٌ** ولو التفّ أحدٌ
 *      على الخدمة وكتب مباشرةً.
 *   ⑤ حراسُ الحالة: الفاتورةُ لا تُستقبل على مسودة · ولا على تسويةِ عامل.
 *   ⑥ **الإقفال**: للمدفوعة وحدَها · و«لا بندَ معلَّقًا» (اعتراضٌ مفتوح → 423) ·
 *      والمقفلةُ **لا تعود** (لا فاتورةَ بعدها).
 *
 * البذرُ معزول: تسوياتٌ M13T وفتراتُ 2052 — تُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Settlement/SettlementService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Settlement\SettlementService as SS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $PREP = 999801; $OFFICER = 999802;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $OFFICER, '', true));

$teardown = function () use ($conn) {
    $conn->query("DELETE FROM settlement_lines
                   WHERE settlement_id IN (SELECT id FROM settlements WHERE settlement_no LIKE 'M13T%')");
    $conn->query("DELETE FROM settlements WHERE settlement_no LIKE 'M13T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-13 — Invoiced وClosed ومطابقةُ الفاتورة ══\n");

head('البذر');
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$EMP = intval($conn->query("SELECT id FROM employees WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);

/** تسويةٌ بحالةٍ ومبلغٍ — وتُعلن فشلَ بذرها بدل أن يمضي صامتًا. */
$mk = function ($no, $party, $ref, $month, $net, $state, $objections = 0)
        use ($conn, $CO, $PREP) {
    $okH = $conn->query("INSERT INTO settlements (company_id, settlement_no, party_type, party_ref,
        party_name, period_from, period_to, currency, gross_amount, charges_amount, net_amount,
        state, open_objections, prepared_by) VALUES ({$CO}, '{$no}', '{$party}', {$ref}, 'M13T',
        '2052-{$month}-01', '2052-{$month}-28', 'SDG', {$net}, 0, {$net}, '{$state}',
        {$objections}, {$PREP})");
    if (!$okH) { bad('تعذّر بذرُ ' . $no . ': ' . $conn->error); return 0; }
    return intval($conn->insert_id);
};

// ═══ ① الحالتان في ENUM ═══
head('① الدورةُ الثمانيةُ كاملة');
$col = $conn->query("SHOW COLUMNS FROM settlements LIKE 'state'")->fetch_assoc();
check(strpos($col['Type'], "'invoiced'") !== false && strpos($col['Type'], "'closed'") !== false,
      'ENUM يحمل `invoiced` و`closed` — الحالتان المفقودتان أُضيفتا');
foreach (array('draft','review','approved','payment_requested','paid','cancelled') as $old) {
    if (strpos($col['Type'], "'" . $old . "'") === false) { bad('فُقدت الحالةُ القديمة ' . $old); }
}
ok('والستُّ القديمةُ محفوظةٌ كلُّها — لا حالةَ ضاعت');

// ═══ ② المطابقةُ التامة ═══
head('② «الفاتورةُ مستندٌ يُطابَق به» — مطابقةٌ تامّة');
$S1 = $mk('M13T-1', 'supplier', $SUP, '01', 4700, 'approved');
$r = SS::markInvoiced($gate, $conn, $S1, array(
    'invoice_no' => 'INV-M13T-1', 'invoice_date' => '2052-02-05', 'invoice_amount' => 4700), $OFFICER);
check($r['ok'] && $r['matched'] === true && abs($r['diff']) < 0.005, 'فاتورةٌ 4700 = الصافي ⇒ مطابقةٌ بفرقٍ صفر');
$row = $conn->query("SELECT state, invoice_no, invoice_diff, net_amount FROM settlements WHERE id={$S1}")->fetch_assoc();
check($row['state'] === 'invoiced' && $row['invoice_no'] === 'INV-M13T-1',
      'والحالةُ صارت `invoiced` برقم فاتورتها');
check(abs(floatval($row['net_amount']) - 4700.0) < 0.005, 'والصافي كما اعتُمد');

// ═══ ③ الفرقُ بقرار ═══
head('③ **«اختلافُها يفتح فرقًا بقرارٍ لا تعديلًا صامتًا»**');
$S2 = $mk('M13T-2', 'supplier', $SUP, '02', 4700, 'payment_requested');
$r = SS::markInvoiced($gate, $conn, $S2, array(
    'invoice_no' => 'INV-M13T-2', 'invoice_date' => '2052-03-05', 'invoice_amount' => 4850), $OFFICER);
check(!$r['ok'] && $r['code'] === 422 && abs($r['diff'] - 150.0) < 0.005
      && strpos($r['reason'], 'سببٌ ومستند') !== false,
      'فرقُ 150 **بلا سببٍ ومستند** → 422 بالفرق مسمًّى');
$still = $conn->query("SELECT state FROM settlements WHERE id={$S2}")->fetch_assoc();
check($still['state'] === 'payment_requested', 'والحالةُ لم تتغير — الرفضُ لا يكتب شيئًا');

$r = SS::markInvoiced($gate, $conn, $S2, array(
    'invoice_no' => 'INV-M13T-2', 'invoice_date' => '2052-03-05', 'invoice_amount' => 4850,
    'diff_reason' => 'رسومُ نقلٍ إضافيةٌ باتفاقٍ لاحق', 'diff_doc_ref' => 'محضر 2052/14'), $OFFICER);
check($r['ok'] && $r['matched'] === false && abs($r['diff'] - 150.0) < 0.005,
      'وبسببٍ ومستندٍ: يُقبل ويُسجَّل الفرقُ 150');
$row = $conn->query("SELECT state, net_amount, invoice_amount, invoice_diff, invoice_diff_reason,
                            invoice_diff_doc_ref FROM settlements WHERE id={$S2}")->fetch_assoc();
check(abs(floatval($row['net_amount']) - 4700.0) < 0.005 && abs(floatval($row['invoice_amount']) - 4850.0) < 0.005,
      '**والصافي لم يُمسّ** (4700) والفاتورةُ محفوظةٌ كما وردت (4850) — §5: الاعترافُ من التسوية');
check(trim((string)$row['invoice_diff_reason']) !== '' && trim((string)$row['invoice_diff_doc_ref']) !== '',
      'والفرقُ يحمل سببَه ومستندَه');

// ═══ ④ قيدُ CHECK ═══
head('④ «الفرقُ بقرارٍ» **بنيويًّا** لا بفحص الخدمة وحدَه');
$S3 = $mk('M13T-3', 'supplier', $SUP, '03', 1000, 'approved');
$conn->query("UPDATE settlements SET invoice_diff = 250, invoice_diff_reason = NULL,
                     invoice_diff_doc_ref = NULL WHERE id={$S3}");
$chk = $conn->query("SELECT invoice_diff FROM settlements WHERE id={$S3}")->fetch_assoc();
check($chk['invoice_diff'] === null,
      'كتابةٌ مباشرةٌ لفرقٍ بلا سببٍ ومستند **يرفضها CHECK** — الصفُّ لم يتغير');

// ═══ ⑤ حراسُ الحالة والطرف ═══
head('⑤ حراسُ الحالة والطرف');
$S4 = $mk('M13T-4', 'supplier', $SUP, '04', 900, 'draft');
$r = SS::markInvoiced($gate, $conn, $S4, array(
    'invoice_no' => 'X', 'invoice_date' => '2052-05-01', 'invoice_amount' => 900), $OFFICER);
check(!$r['ok'] && $r['code'] === 409, 'فاتورةٌ على **مسودة** → 409');

$S5 = $mk('M13T-5', 'employee', $EMP, '05', 700, 'approved');
$r = SS::markInvoiced($gate, $conn, $S5, array(
    'invoice_no' => 'Y', 'invoice_date' => '2052-06-01', 'invoice_amount' => 700), $OFFICER);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'العاملُ لا يُصدر فاتورة') !== false,
      'وعلى تسويةِ **عامل** → 422 (العاملُ لا يُصدر فاتورة)');

$r = SS::markInvoiced($gate, $conn, $S1, array(
    'invoice_no' => 'Z', 'invoice_date' => '2052-02-06', 'invoice_amount' => 4700), $OFFICER);
check(!$r['ok'] && $r['code'] === 409, 'وفاتورةٌ ثانيةٌ على `invoiced` → 409');

// ═══ ⑥ الإقفال ═══
head('⑥ الإقفال — «لا بندَ معلَّقًا» والمقفلةُ لا تعود');
$r = SS::close($gate, $conn, $S1, $OFFICER);
check(!$r['ok'] && $r['code'] === 409, 'إقفالُ `invoiced` → 409 (الإقفالُ للمدفوعة وحدَها)');

$S6 = $mk('M13T-6', 'supplier', $SUP, '06', 2000, 'paid', 2);
$r = SS::close($gate, $conn, $S6, $OFFICER);
check(!$r['ok'] && $r['code'] === 423 && strpos($r['reason'], 'معترَضًا') !== false,
      'ومدفوعةٌ فيها بندان معترَضان → **423** («لا بندَ معلَّقًا»)');

$conn->query("UPDATE settlements SET open_objections = 0 WHERE id={$S6}");
$r = SS::close($gate, $conn, $S6, $OFFICER);
check($r['ok'] && strpos($r['reason'], 'بعكسٍ موثَّق') !== false,
      'وبعد حسمها: تُقفل — والرسالةُ تقول «التصحيحُ بعدها بعكسٍ موثَّقٍ لا بتعديل»');
$row = $conn->query("SELECT state, closed_by, closed_at FROM settlements WHERE id={$S6}")->fetch_assoc();
check($row['state'] === 'closed' && intval($row['closed_by']) === $OFFICER && $row['closed_at'] !== null,
      'والحالةُ `closed` بمُقفِلها ووقتِه');

$r = SS::close($gate, $conn, $S6, $OFFICER);
check(!$r['ok'] && $r['code'] === 409, 'وإقفالٌ ثانٍ → 409');
$r = SS::markInvoiced($gate, $conn, $S6, array(
    'invoice_no' => 'W', 'invoice_date' => '2052-07-01', 'invoice_amount' => 2000), $OFFICER);
check(!$r['ok'] && $r['code'] === 409, 'وفاتورةٌ على مقفلة → 409 — **المقفلةُ لا تعود**');

// ═══ ⑦ التدقيق ═══
head('⑦ أثرُ التدقيق (N-02)');
$aud = intval($conn->query("SELECT COUNT(*) n FROM activity_logs
    WHERE module_name='settlements' AND action_type IN ('invoiced','close')
      AND record_id IN ({$S1},{$S2},{$S6})")->fetch_assoc()['n']);
check($aud >= 3, "كلُّ فوترةٍ وإقفالٍ مسجَّلٌ بقيم قبل/بعد ({$aud})");

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
