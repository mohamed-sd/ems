<?php
/**
 * tools/ctl_route_classify.php — تصنيفُ المساراتِ العاريةِ في `nav_route_group`
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ أمرُ SIDEBAR_DIRECTION_FIX §٣·٢: صفوفُ `gov_target_nav` بلا مقابلٍ في
 *   `nav_route_group` تُرفع مجموعاتُها أبوابًا لأن لا رأسَ لها — «أدخِلْها
 *   بمجموعتِها من الاثنتَي عشرة، بمرجعِ متطلبِها».
 * ◆ **والمجموعةُ تُقاس لا تُخترع** — قناتان بترتيبٍ صارم:
 *   C1 أغلبيّةُ المجلدِ: المسارُ يرث الرمزَ الغالبَ على المصنَّفِ من مجلدِه
 *      (إجماعًا ≥⅔ وبعيّنةٍ ≥3) — فالمجلدُ وحدةُ نطاقٍ في هذا المستودع.
 *   C2 أغلبيّةُ إدارةِ متطلبِه: رمزُ المصنَّفِ الغالبُ على شاشاتِ إدارةِ
 *      متطلبِه المطابقِ (بالجسرِ) بالشرطَين نفسِهما.
 *   ⛔ وما لم تحسمه القناتان يُعلَن مفتوحًا بمرشَّحيه — لا يُلفَّق.
 *
 * التشغيل: php tools/ctl_route_classify.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };
$APPLY = in_array('--apply', $argv, true);

$snap = null;
$r = $conn->query("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc()['snapshot_id']; }
if (!$snap && $APPLY) { exit("⛔ لا نافذةَ قياسٍ مفتوحة\n"); }
$sid = $snap ? $snap : 'DRY';

$normR = function ($s) { return strtolower(trim(preg_replace('~[?#].*$~', '', (string) $s), '/')); };

/* المصنَّفُ القائم */
$classified = array(); $byDir = array();
$r = $conn->query("SELECT route, group_code FROM nav_route_group");
while ($x = $r->fetch_assoc()) {
    $b = $normR($x['route']);
    $classified[$b] = (string) $x['group_code'];
    $d = strpos($b, '/') !== false ? substr($b, 0, strpos($b, '/')) : '';
    if ($d !== '') { $byDir[$d][(string) $x['group_code']] = 1 + (isset($byDir[$d][(string) $x['group_code']]) ? $byDir[$d][(string) $x['group_code']] : 0); }
}
/* الاثنتا عشرةَ — ولا رمزَ خارجَها يُكتب */
$TAX = array();
$r = $conn->query("SELECT code FROM nav_group_taxonomy");
while ($x = $r->fetch_row()) { $TAX[(string) $x[0]] = 1; }

/* الجسرُ والمتطلبات — لقناةِ C2 */
$scr2req = array(); $reqUnit = array();
$r = $conn->query("SELECT u.screen_id, u.requirement_id FROM repair01_target_universe u WHERE u.verdict='MATCHED'");
while ($x = $r->fetch_row()) { $scr2req[$x[0]] = $x[1]; }
$r = $conn->query("SELECT requirement_id, unit FROM repair01_requirements");
while ($x = $r->fetch_assoc()) { $reqUnit[$x['requirement_id']] = (string) $x['unit']; }
$routeScr = array(); $scrRoute = array();
$r = $conn->query("SELECT screen_id, route FROM repair01_screen_registry WHERE route <> ''");
while ($x = $r->fetch_assoc()) { $routeScr[$normR($x['route'])] = $x['screen_id']; $scrRoute[$x['screen_id']] = $normR($x['route']); }
/* رمزُ إدارةٍ غالبٌ: unit ⇒ عدُّ رموزِ شاشاتِها المصنَّفة */
$unitCode = array();
foreach ($scr2req as $scr => $rq) {
    $u0 = isset($reqUnit[$rq]) ? $reqUnit[$rq] : '';
    $b0 = isset($scrRoute[$scr]) ? $scrRoute[$scr] : '';
    if ($u0 === '' || $b0 === '' || !isset($classified[$b0])) { continue; }
    $unitCode[$u0][$classified[$b0]] = 1 + (isset($unitCode[$u0][$classified[$b0]]) ? $unitCode[$u0][$classified[$b0]] : 0);
}
$dominant = function ($counts) {
    if (!$counts) { return array('', 0, 0); }
    arsort($counts);
    $tot = array_sum($counts);
    $top = array_key_first($counts);
    return array($top, $counts[$top], $tot);
};

/* العراةُ: صفوفُ gov_target_nav بلا مقابلٍ في nav_route_group */
$bare = array();
$r = $conn->query("SELECT DISTINCT route FROM gov_target_nav WHERE route NOT LIKE 'GAP:%'");
while ($x = $r->fetch_row()) {
    $b = $normR($x[0]);
    if ($b !== '' && !isset($classified[$b])) { $bare[$b] = (string) $x[0]; }
}
printf("═══ تصنيفُ المساراتِ العارية — لقطة %s ═══\n", $sid);
printf("  عارٍ (فريدًا): %d\n\n", count($bare));

$plan = array(); $open = array();
foreach ($bare as $b => $rawRoute) {
    $d = strpos($b, '/') !== false ? substr($b, 0, strpos($b, '/')) : '';
    $rq = (isset($routeScr[$b]) && isset($scr2req[$routeScr[$b]])) ? $scr2req[$routeScr[$b]] : '';
    /* C1: أغلبية المجلد */
    list($c1, $n1, $t1) = $dominant(isset($byDir[$d]) ? $byDir[$d] : array());
    if ($c1 !== '' && $t1 >= 3 && $n1 * 3 >= $t1 * 2 && isset($TAX[$c1])) {
        $plan[$b] = array('code' => $c1, 'why' => "C1 أغلبيةُ المجلدِ `$d/`: $n1 من $t1 مصنَّفًا على `$c1`",
                          'req' => $rq, 'raw' => $rawRoute);
        continue;
    }
    /* C2: أغلبية إدارة متطلبه */
    $u0 = ($rq !== '' && isset($reqUnit[$rq])) ? $reqUnit[$rq] : '';
    list($c2, $n2, $t2) = $dominant(($u0 !== '' && isset($unitCode[$u0])) ? $unitCode[$u0] : array());
    if ($c2 !== '' && $t2 >= 3 && $n2 * 3 >= $t2 * 2 && isset($TAX[$c2])) {
        $plan[$b] = array('code' => $c2, 'why' => "C2 أغلبيةُ إدارةِ متطلبِه `$u0`: $n2 من $t2 على `$c2`",
                          'req' => $rq, 'raw' => $rawRoute);
        continue;
    }
    $open[$b] = "C1(`$d/`: $c1 $n1/$t1) · C2(`$u0`: $c2 $n2/$t2)";
}
printf("  محسومٌ قياسًا: %d · مفتوحٌ بمرشَّحيه: %d\n", count($plan), count($open));
$byCode = array();
foreach ($plan as $x) { $byCode[$x['code']] = 1 + (isset($byCode[$x['code']]) ? $byCode[$x['code']] : 0); }
arsort($byCode);
foreach ($byCode as $c => $n) { printf("    %-12s %d\n", $c, $n); }
if ($open) {
    echo "  ── المفتوحُ (لا يُلفَّق) ──\n";
    foreach ($open as $b => $w) { echo "    ⛔ $b — $w\n"; }
}

if ($APPLY && $plan) {
    $n = 0;
    foreach ($plan as $b => $x) {
        $route = $x['raw'];
        $ok = $conn->query("INSERT INTO nav_route_group (route, group_code)
              VALUES ('" . $e($route) . "', '" . $e($x['code']) . "')
              ON DUPLICATE KEY UPDATE group_code = group_code");
        if (!$ok) { exit("✘ $b: {$conn->error}\n"); }
        $n++;
    }
    printf("\n  ✔ صُنِّف **%d** مسارًا — والشاهدُ قناتا القياسِ أعلاه · لقطة %s\n", $n, $sid);
}
