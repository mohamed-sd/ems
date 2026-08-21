<?php
/**
 * tests/injfix02_space_classification_ratchet.php
 *   INJ-FIX-02 · NF-24 — توسيعُ GAP-22 المعتمَدة (الفشلُ المفتوح)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المبدأُ القائمُ «الغيابُ ليس منعًا»** يترك كلَّ مسارٍ خارجَ سجلِّ التصنيفِ
 *   **مفتوحًا افتراضًا**. والملحقُ قدَّرها ~٥٠ مسارًا · **والمقيسُ ١٤**.
 *
 * ◆ **وهذه سقّاطةٌ لا قلب**: قلبُ العزلِ إلى الإغلاقِ الافتراضيِّ **تغييرُ وصولٍ
 *   حيٍّ** يمرُّ ببروتوكولِ القلبِ بقرارِ مالك — ومعيارُ `NF-24` نفسُه يشترط
 *   «فورَ اكتمالِ التصنيف». **فالتصنيفُ أولًا، والقلبُ بعدَه بتاريخٍ معلَن.**
 *   وما تفعله هذه السقّاطةُ أن **تمنع الدَّينَ من الازدياد** ريثما يكتمل.
 *
 * ◆ **وتُرسِّب عند الانخفاضِ أيضًا** — فسقّاطةٌ لا تُشدُّ تصير سقفًا يُنسى.
 *
 * التشغيل: php tests/injfix02_space_classification_ratchet.php [--retighten]
 * الخروج : 0 نجاح · 1 رسوب
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$BASELINE_FILE = $ROOT . '/docs/INJFIX01/evidence/NF-24_unclassified_baseline.json';
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}
function base_of($r)
{
    return mb_strtolower(basename(preg_replace('/[?\#].*$/', '', (string) $r)));
}

/* ══ المقياس — مسارٌ نشطٌ في التنقّلِ بلا صفٍّ في سجلِّ التصنيف ═══════════ */
$classified = array();
$q = $conn->query("SELECT DISTINCT `route` FROM `gov_space_appearances`");
while ($q && $x = $q->fetch_row()) { $b = base_of($x[0]); if ($b !== '') { $classified[$b] = 1; } }

$unclassified = array();
$q = $conn->query("SELECT DISTINCT `route` FROM `nav_items`
                    WHERE `active` = 1 AND COALESCE(`route`,'') <> ''");
while ($q && $x = $q->fetch_row()) {
    $b = base_of($x[0]);
    if ($b !== '' && !isset($classified[$b])) { $unclassified[$b] = 1; }
}
ksort($unclassified);
$now = array_keys($unclassified);

echo "══ NF-24 · مساراتٌ نشطةٌ خارجَ سجلِّ التصنيف (مفتوحةٌ افتراضًا) ══\n";
printf("  المقيسُ الآن: %d\n", count($now));

/* ══ خطُّ الأساس ═══════════════════════════════════════════════════════════ */
if (in_array('--retighten', $argv, true) || !is_file($BASELINE_FILE)) {
    $dir = dirname($BASELINE_FILE);
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }
    file_put_contents($BASELINE_FILE, json_encode(array(
        'gap' => 'GAP-22 · NF-24',
        'meaning' => 'مساراتٌ نشطةٌ في التنقّلِ بلا صفٍّ في gov_space_appearances ⇒ مفتوحةٌ افتراضًا',
        'count' => count($now), 'routes' => $now,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "  ↦ شُدَّ خطُّ الأساسِ إلى " . count($now) . "\n";
}
$base = json_decode((string) file_get_contents($BASELINE_FILE), true);
$baseN = (int) ($base['count'] ?? 0);
$baseR = (array) ($base['routes'] ?? array());

echo "\n══ الحكم ══\n";
$new = array_values(array_diff($now, $baseR));
chk(count($new) === 0,
    '**صفرُ مسارٍ جديدٍ خارجَ التصنيف** — ' . count($new) . ' جديد'
    . (count($new) ? ' — ' . implode(' · ', $new) : ''));
chk(count($now) <= $baseN, "لا ازديادَ في العدد — " . count($now) . " ≤ {$baseN}");
if (count($now) < $baseN) {
    chk(false, "◆ انخفض إلى " . count($now) . " من {$baseN} — **تُشدُّ السقّاطة**: "
             . "php tests/injfix02_space_classification_ratchet.php --retighten");
}

echo "\n── الدَّينُ المُعلَنُ ──\n";
foreach ($now as $r) { echo "  · {$r}\n"; }
echo "◆ ولا تُغلق هذه السقّاطةُ GAP-22 — تمنع ازديادَه.\n";
echo "◆ والقلبُ إلى الإغلاقِ الافتراضيِّ **تغييرُ وصولٍ حيّ** بقرارِ مالكٍ وتاريخٍ معلَن،\n";
echo "   ومعيارُ NF-24 نفسُه يشترطه «فورَ اكتمالِ التصنيف» — فالتصنيفُ أولًا.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
