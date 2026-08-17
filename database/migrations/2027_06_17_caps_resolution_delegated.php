<?php
/**
 * 2027_06_17_caps_resolution_delegated.php
 * ═══════════════════════════════════════════════════════════════════════════
 * حسمُ السقوفِ الموقوفةِ حيًّا — بتفويضِ المالكِ الصريح (2026-08-17:
 * «لن أرسل شيئًا — عليك تحديثُ الأرقامِ من عندك والانطلاق»).
 *
 * 24-أ (LAD-0005-ب): السقفُ «يُقرَّر بقرارٍ ماليٍّ موثَّقٍ ولا يُخترع» —
 * والتوثيقُ هنا اشتقاقٌ مقيسٌ من النظامِ الحيِّ لا اختراع:
 *   · 5,069 واقعةً ماليةً (fin_financial_events · amount>0 · 99٪ USD):
 *     مئيناتُ الواقعةِ: P80=468 · P90=913 · P95=1,500 · P99=3,000
 *   · المجاميعُ الشهريةُ لكلِّ طرفٍ: P90=893 · P95=1,411 · الأقصى=15,444
 *   · فالسقوفُ مُدرَّجةٌ بحيث تمرُّ المعاملةُ الاعتياديةُ (≤P95+) بدرجتِها
 *     ويصعد الذيلُ النادرُ بالسلّمِ المنصوص:
 *       LD-05 الاعتمادُ الأوليُّ (رئيس الحسابات)          = 2,000 USD
 *       LD-06 الفاتورةُ (فوقَه النائبُ الماليّ)            = 5,000 USD
 *       LD-07 النهائيُّ (فوقَه المديرُ الماليّ)            = 10,000 USD
 *
 * والسلاليمُ الستةُ الباقيةُ من تسعةِ 24-أ لم تُبذَر أصلًا (شاشاتُها غيرُ
 * المبنيةِ) — تُحسم مع بذرِها بالمنهجِ نفسِه. تعديلُ الأرقامِ لاحقًا قرارُ
 * مالكٍ بهجرةٍ جديدة — لا تعديلَ صامتًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$one = function (string $s) use ($conn) { $r = $conn->query($s); return $r ? ($r->fetch_row()[0] ?? null) : null; };

echo "\n▐ الحالُ قبل\n";
$r = $conn->query("SELECT ladder_code, name_ar, cap_state, cap_amount FROM gov_ladders WHERE cap_kind='amount'");
while ($x = $r->fetch_assoc()) {
    echo "   · {$x['ladder_code']} {$x['name_ar']}: {$x['cap_state']} / " . ($x['cap_amount'] ?? '—') . "\n";
}

$CAPS = array(
    'LD-05' => array(2000.00,  'سقفُ الاعتمادِ الأوليِّ — مشتقٌّ من مئيناتِ الواقعةِ الحية (P95=1500·P99=3000) بتفويضِ المالك 2026-08-17'),
    'LD-06' => array(5000.00,  'سقفُ الفاتورةِ — فوقَ P95 الشهريِّ للطرفِ (1,411) بثلاثةِ أمثالٍ؛ الأكبرُ للنائبِ الماليِّ — بتفويضِ المالك 2026-08-17'),
    'LD-07' => array(10000.00, 'سقفُ الاعتمادِ النهائيِّ — دونَ أقصى المجمَّعِ الشهريِّ الحيِّ (15,444)؛ الأكبرُ للمديرِ الماليِّ — بتفويضِ المالك 2026-08-17'),
);
echo "\n▐ الحسم\n";
$st = $conn->prepare("UPDATE gov_ladders
                         SET cap_amount = ?, cap_currency = 'USD', cap_state = 'resolved',
                             payload_note = CONCAT(COALESCE(payload_note,''), ' | ', ?)
                       WHERE ladder_code = ? AND cap_kind = 'amount' AND cap_state = 'unresolved'");
foreach ($CAPS as $code => $def) {
    list($amt, $note) = $def;
    $st->bind_param('dss', $amt, $note, $code);
    $st->execute();
    echo $st->affected_rows > 0 ? "   ✔ {$code} = {$amt} USD\n" : "   · {$code} محسومٌ سلفًا أو غيرُ موجود\n";
}
$st->close();

printf("\n   · سقوفُ مبلغٍ غيرُ محسومةٍ بعدُ: %s   [المتوقَّع 0 حيًّا — والستةُ الباقيةُ تُحسم مع بذرِ سلاليمِها]\n",
    $one("SELECT COUNT(*) FROM gov_ladders WHERE cap_kind='amount' AND cap_state='unresolved'"));

/* سلبيّ: قيدُ chk_ladder_cap يرفض resolved بلا مبلغٍ وعملة */
$neg = $conn->query("UPDATE gov_ladders SET cap_amount = NULL WHERE ladder_code='LD-05' AND cap_state='resolved'");
if ($neg === false) {
    echo "   ✔ السلبيّ: محسومٌ بلا مبلغٍ رُفض ({$conn->errno})\n";
} else {
    $conn->query("UPDATE gov_ladders SET cap_amount = 2000.00 WHERE ladder_code='LD-05'");
    echo "   ⚠ السلبيّ: القيدُ لم يرفض — أُعيد المبلغُ (راجع chk_ladder_cap)\n";
}
echo "\n✔ السقوفُ الحيةُ محسومةٌ — وبطاقةُ «بانتظارِ اعتمادِ السقف» تختفي عن معاملاتِها\n";
