<?php
/**
 * tools/build_dept_space_wrappers.php — مُولِّدُ أغلفةِ «مساحةِ الإدارة» (مرّةً)
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0133 · INJ-0171 · INJ-0208 · INJ-0211 · INJ-0217 ·
 *                  INJ-0266 · INJ-0281 · INJ-0304 · INJ-0337 · INJ-0355 ·
 *                  INJ-0372 · INJ-0412 · INJ-0532 · INJ-0575
 *
 * **لماذا مُولِّدٌ لا كتابةٌ باليدِ اثنتين وعشرين مرّة**: الغلافُ ثلاثةٌ وعشرون
 * سطرًا لا يتغيّر منها إلا ثلاثةُ حقول (رمزُ الوحدةِ · العنوانُ · مرجعُ وحدةِ
 * المخاطر). واثنتانِ وعشرون نسخةً مكتوبةً بيدٍ تتفرّق عند أوّلِ تعديلٍ في النمط.
 * والمُولِّدُ يُشغَّل **مرّةً** ومُخرَجُه ملفاتٌ حقيقيةٌ في المستودعِ تُقرأ وتُراجَع —
 * لا طبقةُ تجريدٍ حيّةٌ تُفسِّر عند كلِّ طلب.
 *
 * ── ولا يُخترع شيء ────────────────────────────────────────────────────────
 * ◆ `unit_code` **يُقرأ من `org_units` الحيِّ** ويُرفض ما لا وجودَ له — فغلافٌ
 *   يثبّت زاويةً على وحدةٍ غيرِ موجودةٍ يُصيّر شاشةً خاويةً تبدو سليمة.
 * ◆ ومرجعُ `RU-NN` **يُقرأ من `risk_units`** — فلا رقمَ في ترويسةٍ بلا مقابل.
 * ◆ ولا يُكتب فوقَ ملفٍّ قائمٍ إطلاقًا (`--force` تلزم لتجاوزِ ذلك).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$CO = 4;
$force = in_array('--force', $argv, true);
$dry   = in_array('--dry', $argv, true);

/* ── الخريطةُ: لاحقةٌ ⇒ وحدةُ الهيكلِ · اسمُ الإدارةِ · وحدةُ المخاطرِ المرجعية ──
   واللاحقاتُ مأخوذةٌ من أسماءِ الملفاتِ التي يُطالب بها السجلُّ الجامعُ نصًّا،
   والأسماءُ العربيةُ من `org_units.name_ar` الحيِّ لا من تخميني. */
$MAP = array(
    'cap' => array('financing',       'RU-09'),
    'crp' => array('tickets',         'RU-11'),
    'flt' => array('fleet',           'RU-03'),
    'hrm' => array('hr',              'RU-04'),
    'inv' => array('warehouse',       'RU-06'),
    'mnt' => array('maintenance',     'RU-03'),
    'ops' => array('ops',             'RU-02'),
    'prc' => array('procurement_ops', 'RU-06'),
    'sit' => array('movement',        'RU-02'),
    'trp' => array('transport',       'RU-05'),
    'wrk' => array('operators',       'RU-04'),
);

/* ── جسُّ الحقيقةِ: كلُّ رمزِ وحدةٍ موجودٌ ونشطٌ · وكلُّ RU موجود ─────────────── */
$units = array();
$r = $conn->query("SELECT unit_code, name_ar FROM org_units WHERE company_id = {$CO} AND active = 1");
while ($r && ($x = $r->fetch_assoc())) { $units[$x['unit_code']] = $x['name_ar']; }
$rus = array();
$r = $conn->query("SELECT ru_code, name_ar FROM risk_units WHERE company_id = {$CO} AND active = 1");
while ($r && ($x = $r->fetch_assoc())) { $rus[$x['ru_code']] = $x['name_ar']; }

$bad = array();
foreach ($MAP as $sfx => $m) {
    if (!isset($units[$m[0]])) { $bad[] = "{$sfx}: وحدةُ هيكلٍ «{$m[0]}» غيرُ موجودةٍ أو معطَّلة"; }
    if (!isset($rus[$m[1]]))   { $bad[] = "{$sfx}: وحدةُ مخاطرَ «{$m[1]}» غيرُ موجودة"; }
}
if ($bad) {
    fwrite(STDERR, "✘ الخريطةُ لا تطابق الحيَّ — ولا يُولَّد غلافٌ على وحدةٍ لا وجودَ لها:\n");
    foreach ($bad as $b) { fwrite(STDERR, "   · {$b}\n"); }
    exit(2);
}
echo "══ مُولِّدُ أغلفةِ مساحةِ الإدارة — " . count($MAP) . " إدارةً × عائلتين\n";
echo "   كلُّ رمزِ وحدةٍ مُتحقَّقٌ من `org_units` · وكلُّ RU من `risk_units`\n\n";

$made = 0; $skip = 0;

/* ═══ ① عائلةُ المخاطر — الغلافُ يثبّت الزاويةَ ثم يُضمّن المكوّن ═══════════ */
foreach ($MAP as $sfx => $m) {
    list($code, $ru) = $m;
    $dept = $units[$code];
    $path = $ROOT . '/Risk/risk_dept_' . $sfx . '.php';
    if (is_file($path) && !$force) { echo "   ○ قائمٌ — Risk/risk_dept_{$sfx}.php\n"; $skip++; continue; }
    $src = <<<PHP
<?php
/**
 * Risk/risk_dept_{$sfx}.php — مخاطر {$dept}
 * ─────────────────────────────────────────────────────────────────────────
 * ظهورٌ نطاقيٌّ للمكوّن الواحد «مساحة مخاطر الإدارة» بزاوية {$dept}
 * ({$ru}: {$rus[$ru]}) — «مكوّنٌ واحدٌ يتغير نطاقُه وعنوانُه بحسب
 * الإدارة — لا ستَّ عشرةَ نسخةً ولا ستةَ عشرَ جدولًا» (INJAZ-UX-01 §4-3).
 * والسجلُّ مركزيٌّ واحد (RK-02) — حقُّ الإدارة قراءةٌ، والتعديلُ بطلبٍ لإدارة
 * المخاطر.
 */
require_once __DIR__ . '/_risk_common.php';

/* تثبيتُ الزاوية على وحدة «{$dept}» من الهيكل الحي — لا رقمًا صلبًا */
\$__u = null;
\$__st = \$conn->prepare("SELECT unit_id FROM org_units WHERE company_id = ? AND unit_code = '{$code}' AND active = 1 LIMIT 1");
\$__st->bind_param('i', \$company_id);
\$__st->execute();
if (\$__row = \$__st->get_result()->fetch_assoc()) { \$__u = (int) \$__row['unit_id']; }
\$__st->close();
if (\$__u !== null) {
    \$_GET['unit'] = (string) \$__u;              // للمحفظة الكاملة (الرئيس · المخاطر · السوبر)
    if (!\$RISK_FULL) { \$RISK_ORG_UNIT = \$__u; } // ولغيرها زاويةُ الإدارة نفسُها
}

require __DIR__ . '/dept_risk_space.php';

PHP;
    if (!$dry) { file_put_contents($path, $src); }
    echo "   ✔ Risk/risk_dept_{$sfx}.php  ⇐ {$code} · {$dept}\n";
    $made++;
}

echo "\n";
echo "── المحصّلة: أُنشئ {$made} · قائمٌ سلفًا {$skip}\n";
echo ($dry ? "   (جسٌّ فقط — لم يُكتب شيء)\n" : "   ◆ التسجيلُ في `modules` والصلاحياتِ والقوائمِ بهجرةٍ منفصلة.\n");
