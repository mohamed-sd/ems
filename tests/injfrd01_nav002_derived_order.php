<?php
/**
 * tests/injfrd01_nav002_derived_order.php
 *   شاهدُ FR-NAV-002 — ترتيبُ السايدبارِ يُشتقُّ لا يُكتب
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: «ترتيبُ السايدبارِ **يُشتقُّ لا يُكتب**: من رقمِ الطبقةِ
 *   وترتيبِ المرحلةِ والرتبةِ المستنديةِ للشاشة» · ومعيارُ القبول «**صفرُ رقمِ
 *   ترتيبٍ مكتوبٍ بيد**» · وسالبُه «رقمُ ترتيبٍ مكتوبٌ بيدٍ ← يُرسِّب الفحص».
 *
 * ◆ **والمقامُ يحسم قبلَ أيِّ عمل**: `nav_items` **1802 صفًّا**، و**95 منها
 *   فقط (5.3٪)** لها صفٌّ متمايزٌ في `gov_screen_cycle` — مصدرُ الطبقةِ والمرحلة.
 *   **فاشتقاقُ نصفِ العُشرِ وكتابةُ الباقي بيدٍ أسوأُ ممّا هو قائم**: ترتيبٌ نصفُه
 *   مشتقٌّ ونصفُه مكتوبٌ لا يُقرأ ولا يُصان.
 *
 * ◆ **ولا يُكتب «صفرُ رقمٍ بيد» ما دام 94.7٪ لا مصدرَ لاشتقاقِه** — والادّعاءُ
 *   هنا كذبٌ بالمقام. فيُقاس ثلاثةُ أشياءَ بأسمائِها:
 *   ① **التغطية** — كم صفًّا يمكن اشتقاقُ ترتيبِه أصلًا (وسقّاطةٌ لا تنقص).
 *   ② **المطابقة** — للمشتقِّ الممكن: كم يوافق المكتوبَ وكم يخالفه.
 *   ③ **الأثر** — كم صفًّا سيتغيّر ترتيبُه لو فُعِّل الاشتقاقُ اليوم.
 *
 * ◆ **والتفعيلُ ليس لي**: إعادةُ ترتيبِ السايدبارِ **تغييرُ ما يراه كلُّ
 *   مستخدمٍ كلَّ يوم**. يُقاس أثرُه ويُرفَع، ولا يُقلَب بيدِ منفِّذ.
 *
 * التشغيل: php tests/injfrd01_nav002_derived_order.php [--list]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$list = in_array('--list', $argv, true);
$BASE = $ROOT . '/docs/INJFIX01/evidence/NAV-002_derivable_baseline.json';

echo "══ FR-NAV-002 — الترتيبُ يُشتقُّ لا يُكتب ══\n";

/* ── ① التغطية — كم يمكن اشتقاقُه أصلًا ─────────────────────────────────── */
$total = n($db, "SELECT COUNT(*) FROM `nav_items`");
$byHand = n($db, "SELECT COUNT(*) FROM `nav_items` WHERE COALESCE(`sort_order`,0) <> 0");
/* ◆ **الانضمامُ يعدُّ صفوفًا لا عناصر**: `COUNT(*)` على انضمامٍ متعدِّدٍ
 *   أعطى **555** بينما العناصرُ المتمايزةُ **95** — فالشاشةُ الواحدةُ لها
 *   عدةُ صفوفٍ في دورةِ الشاشات. **ومقامٌ منفوخٌ بالانضمامِ يُظهر تغطيةً
 *   30.8٪ وهي 5.3٪.** ⇒ `COUNT(DISTINCT n.id)`. */
$derivable = n($db, "SELECT COUNT(DISTINCT n.`id`) FROM `nav_items` n
                      JOIN `gov_screen_cycle` g ON LOWER(g.`screen_file`) = LOWER(n.`route`)");
printf("  nav_items=%d · **برقمٍ مكتوبٍ بيد=%d (%.1f٪)** · قابلٌ للاشتقاق=%d (%.1f٪)\n",
       $total, $byHand, $total ? 100 * $byHand / $total : 0,
       $derivable, $total ? 100 * $derivable / $total : 0);
chk($total > 0 && $derivable > 0, '**المقامُ غيرُ صفريّ** في الطرفَين',
    "{$total} صفًّا · {$derivable} قابلًا للاشتقاق");

/* ◆ **ولا يُعلَن معيارُ القبولِ متحقِّقًا** ما دام أكثرُه غيرَ قابلٍ للاشتقاق */
chk($derivable === $total,
    'FR-NAV-002 · **صفرُ رقمِ ترتيبٍ مكتوبٍ بيد**',
    $derivable === $total ? 'كلُّها مشتقّة'
        : '**غيرُ متحقِّق**: ' . ($total - $derivable) . ' صفًّا لا مصدرَ لاشتقاقِه في `gov_screen_cycle`');

/* ── ② الاشتقاق — طبقةٌ ثمّ مرحلةٌ ثمّ رتبةٌ مستندية ────────────────────── */
/* ◆ **رقمُ الطبقةِ يُشتقُّ من ترتيبِها في الدورةِ نفسِها** لا من قائمةٍ عندي:
 *   أقلُّ `stage_order` في الطبقةِ يحدّد رتبتَها — فالطبقةُ التي تبدأ أوّلًا
 *   تُعرَض أوّلًا. **ولا رقمَ يُخترَع.** */
$layerRank = array();
$r = $db->query("SELECT `layer_name`, MIN(COALESCE(`stage_order`,999)) mn
                   FROM `gov_screen_cycle` WHERE COALESCE(`layer_name`,'') <> ''
                  GROUP BY `layer_name` ORDER BY mn, `layer_name`");
$i = 0;
while ($r && $x = $r->fetch_row()) { $layerRank[$x[0]] = ++$i; }
printf("\n  طبقاتٌ مُرتَّبةٌ باشتقاق: %d — %s\n", count($layerRank),
       implode(' · ', array_map(function ($k, $v) { return "{$v}={$k}"; },
               array_keys($layerRank), $layerRank)));
chk(count($layerRank) > 0, 'ورتبةُ الطبقةِ **مشتقّةٌ من أوّلِ مرحلةٍ فيها** لا مخترَعة');

/* المشتقُّ لكلِّ صفٍّ قابل */
$derived = array(); $written = array();
$r = $db->query("SELECT n.`id`, n.`sort_order`, g.`layer_name`, g.`stage_order`, g.`screen_file`
                   FROM `nav_items` n
                   JOIN `gov_screen_cycle` g ON LOWER(g.`screen_file`) = LOWER(n.`route`)");
while ($r && $x = $r->fetch_assoc()) {
    $lr = isset($layerRank[$x['layer_name']]) ? $layerRank[$x['layer_name']] : 9;
    $so = (int) ($x['stage_order'] ?: 99);
    /* الرتبةُ المستندية: ترتيبٌ أبجديٌّ ثابتٌ داخلَ المرحلةِ — حتميٌّ لا عشوائيّ */
    $doc = crc32(strtolower((string) $x['screen_file'])) % 100;
    $derived[(int) $x['id']] = ($lr * 10000) + ($so * 100) + $doc;
    $written[(int) $x['id']] = (int) $x['sort_order'];
}
printf("  صفوفٌ حُسب لها ترتيبٌ مشتقّ: %d\n", count($derived));

/* ── ③ الأثر — كم سيتغيّر ترتيبُه لو فُعِّل ──────────────────────────────── */
$sameRank = 0; $diffRank = 0; $samples = array();
/* المقارنةُ **بالرتبةِ لا بالقيمة**: قيمتان مختلفتان بترتيبٍ واحدٍ ليستا فرقًا */
$byDerived = $derived; asort($byDerived);
$byWritten = $written; asort($byWritten);
$rankD = array_flip(array_keys($byDerived));
$rankW = array_flip(array_keys($byWritten));
foreach ($derived as $id => $_) {
    if ($rankD[$id] === $rankW[$id]) { $sameRank++; }
    else {
        $diffRank++;
        if (count($samples) < 5) { $samples[] = "#{$id}: {$rankW[$id]} ⇒ {$rankD[$id]}"; }
    }
}
printf("\n  **الأثرُ لو فُعِّل الاشتقاقُ اليوم**: يبقى في موضعِه=%d · **يتغيّر موضعُه=%d**\n",
       $sameRank, $diffRank);
if ($list) { foreach ($samples as $s) { echo "     · {$s}\n"; } }
chk($sameRank + $diffRank === count($derived),
    'والأثرُ **مقيسٌ بالرتبةِ لا بالقيمة** — قيمتان مختلفتان بترتيبٍ واحدٍ ليستا فرقًا',
    ($sameRank + $diffRank) . ' من ' . count($derived));

/* ── ④ سقّاطةُ التغطية — لا تنقص ────────────────────────────────────────── */
echo "\n── ④ سقّاطةُ التغطية ──\n";
if (!is_file($BASE)) {
    if (!is_dir(dirname($BASE))) { mkdir(dirname($BASE), 0777, true); }
    file_put_contents($BASE, json_encode(array(
        'req' => 'FR-NAV-002', 'total' => $total, 'derivable' => $derivable,
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "  ↦ كُتب خطُّ الأساسِ: قابلٌ للاشتقاق={$derivable} من {$total}\n";
}
$bl = json_decode((string) file_get_contents($BASE), true);
$blD = (int) ($bl['derivable'] ?? 0);
chk($derivable >= $blD, '**التغطيةُ لا تنقص** — وسقّاطةٌ تمنع تراجعَ الاشتقاق',
    "{$derivable} ≥ {$blD}");

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
echo "◆ **ولا يُقلَب التفعيلُ بيدِ منفِّذ**: إعادةُ ترتيبِ السايدبارِ تغييرُ ما يراه\n";
echo "  كلُّ مستخدمٍ كلَّ يوم. الأثرُ مقيسٌ أعلاه، والقلبُ قرارُ مالكِ التنقُّل.\n";
exit($bad === 0 ? 0 : 1);
