<?php
/**
 * tools/govui_wiring_triage.php — فرزُ عطبِ الوصلِ قبلَ تصحيحِ الاسم (§9 · §22)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الفخُّ الذي يتجنّبه**: إعادةُ تسميةِ سطحٍ **موصولٍ بالخطأ** تُثبّت العطبَ
 *   ولا تُصلحه — «الاسمُ المطابقُ حرفًا قد يكون الشاشةَ الخطأ»
 *   [[repair01-ops-sidebar-guide11]]. فقبلَ أيِّ تسميةٍ يُفرز الوصل.
 *
 * ◆ **وشاهدُ التقاطعِ مقيسٌ لا مُخمَّن**: إن كان **المعروضُ** لهدفٍ يساوي
 *   **الحاكمَ لهدفٍ آخرَ في المساحةِ نفسِها** ⇒ `CROSSED` — سطحانِ تبادلا
 *   موضعَيهما. وإن تشارك هدفانِ `MENU_ITEM` مسارًا واحدًا ⇒ `MENU_DUP`.
 *
 * ◆ **والمرشَّحُ من القرصِ**: ملفٌّ في مجلَّدِ المساحةِ **غيرُ موصولٍ بأيِّ هدفٍ**
 *   واسمُه في سجلِّ الشاشاتِ أقربُ إلى الحاكم — يُعرَض بدرجةِ قربِه، ولا يُطبَّق
 *   إلّا بحكمٍ مكتوبٍ في `govui_wiring_ruling.php`.
 *
 * التشغيل: php tools/govui_wiring_triage.php [--ws=DEP-10]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/tools/govui_lib.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$only = ''; foreach ($argv as $a) { if (strpos($a, '--ws=') === 0) { $only = substr($a, 5); } }

$M = json_decode(file_get_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_LABEL_MEASURE.json'), true);
if (!$M) { exit("⛔ شغّلْ govui_label_measure.php أوّلًا\n"); }

/* الحاكمُ بالمساحة — لكشفِ التقاطع */
$canonByWs = array();
foreach ($M['rows'] as $r) { $canonByWs[$r['ws']][$r['target_id']] = $r['canonical_norm']; }

/* المساراتُ الموصولةُ — وما عداها في المجلَّدِ مرشَّح */
$wired = array();
$r = $conn->query("SELECT LOWER(route) rt FROM nav_placements WHERE route IS NOT NULL AND route <> ''");
while ($x = $r->fetch_row()) { $wired[$x[0]] = 1; }
/* سجلُّ الشاشاتِ — الاسمُ المخزَّنُ للمرشَّحِ ومَبناه */
$regByRoute = array();
$r = $conn->query("SELECT screen_id, LOWER(route) rt, canonical_label_ar, lifecycle, on_disk
                     FROM repair01_screen_registry WHERE route IS NOT NULL AND route <> ''");
while ($x = $r->fetch_assoc()) { $regByRoute[$x['rt']] = $x; }

/** درجةُ قربٍ بسيطةٌ: تقاطعُ الكلماتِ بعدَ التطبيع */
function govui_sim($a, $b)
{
    $wa = array_filter(explode(' ', rpr02a_nz($a)));
    $wb = array_filter(explode(' ', rpr02a_nz($b)));
    if (!$wa || !$wb) { return 0.0; }
    $i = count(array_intersect($wa, $wb));
    return $i / max(count($wa), count($wb));
}

$cases = array();
foreach ($M['rows'] as $r) {
    if ($only !== '' && $r['ws'] !== $only) { continue; }
    $kind = '';
    if ($r['verdict'] === 'SHARED_ROUTE_MENU_DUP') { $kind = 'MENU_DUP'; }
    if ($r['verdict'] === 'WRONG_LABEL' && $r['rendered'] !== '') {
        foreach ($canonByWs[$r['ws']] as $otid => $cn) {
            if ($otid !== $r['target_id'] && rpr02a_nz($r['rendered']) === $cn) { $kind = 'CROSSED:' . $otid; break; }
        }
    }
    if ($r['verdict'] === 'NOT_RENDERED') { $kind = 'BUILT_NOT_RENDERED'; }
    if ($kind === '') { continue; }
    $cases[] = array('ws' => $r['ws'], 'tid' => $r['target_id'], 'kind' => $kind,
        'canonical' => $r['canonical'], 'rendered' => $r['rendered'], 'route' => $r['route']);
}

/* مرشَّحاتُ القرصِ لكلِّ حالةٍ — من مجلَّدِ المسارِ الحاليِّ نفسِه */
foreach ($cases as $i => $c) {
    $dir = $c['route'] !== '' ? dirname($c['route']) : '';
    $cand = array();
    foreach ($regByRoute as $rt => $g) {
        if ($dir !== '' && dirname($rt) !== $dir) { continue; }
        if (isset($wired[$rt])) { continue; }
        $s = govui_sim($c['canonical'], $g['canonical_label_ar']);
        if ($s >= 0.34) { $cand[] = sprintf('%s(%.2f · %s)', $rt, $s, $g['canonical_label_ar']); }
    }
    usort($cand, function ($a, $b) { return strcmp($b, $a); });
    $cases[$i]['candidates'] = array_slice($cand, 0, 4);
}

foreach ($cases as $c) {
    printf("%-8s %-16s %-22s\n    حاكم: %s\n    معروض: %s   [%s]\n", $c['ws'], $c['tid'], $c['kind'],
        $c['canonical'], $c['rendered'] === '' ? '— لا يُصيَّر' : $c['rendered'], $c['route']);
    if ($c['candidates']) { echo "    مرشَّح: " . implode("\n            ", $c['candidates']) . "\n"; }
    echo "\n";
}
printf("المجموع: %d حالةَ وصلٍ للفرز\n", count($cases));
file_put_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_WIRING_TRIAGE.json',
    json_encode($cases, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
