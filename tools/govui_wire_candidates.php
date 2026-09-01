<?php
/**
 * tools/govui_wire_candidates.php — أثمَّ سطحٌ **غيرُ موصولٍ** أقربُ إلى الهدف؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **السؤالُ الذي يجيب عنه**: قبلَ أن أُعيدَ تسميةَ سطحٍ باسمِ هدفِه، هل في
 *   المجلَّدِ نفسِه سطحٌ **مبنيٌّ غيرُ موصولٍ بأيِّ هدفٍ** اسمُه المخزَّنُ
 *   **أقربُ** إلى الحاكمِ من اسمِ الموصولِ الآن؟ إن كان — فالعطبُ **وصلٌ**
 *   لا تسمية، وإعادةُ التسميةِ تُثبّت الانزياحَ بدل أن تكشفه.
 *
 * ◆ **والقربُ مقيسٌ بمفرداتٍ لا برأي**: تقاطعُ كلماتِ الاسمِ بعدَ التطبيع.
 *   ولا يُقترح مرشَّحٌ إلّا إن **فاق** درجةَ الموصولِ الحاليِّ بفارقٍ معلَن.
 *
 * ⛔ **ولا يطبّق شيئًا**: يُخرج قائمةَ فرزٍ تُحسم بحكمٍ مكتوبٍ في الهجرة.
 * التشغيل: php tools/govui_wire_candidates.php [--min=0.34]
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
$MIN = 0.34;
foreach ($argv as $a) { if (strpos($a, '--min=') === 0) { $MIN = (float) substr($a, 6); } }

$M = json_decode(file_get_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_LABEL_MEASURE.json'), true);
if (!$M) { exit("⛔ شغّلْ govui_label_measure.php أوّلًا\n"); }

$wired = array();
$r = $conn->query("SELECT LOWER(route) rt FROM nav_placements WHERE route IS NOT NULL AND route <> ''");
while ($x = $r->fetch_row()) { $wired[$x[0]] = 1; }
$reg = array();
$r = $conn->query("SELECT screen_id, LOWER(route) rt, route AS raw, canonical_label_ar, on_disk
                     FROM repair01_screen_registry WHERE route IS NOT NULL AND route <> ''");
while ($x = $r->fetch_assoc()) { $reg[$x['rt']] = $x; }

function govui_sim2($a, $b)
{
    $wa = array_values(array_unique(array_filter(explode(' ', rpr02a_nz($a)))));
    $wb = array_values(array_unique(array_filter(explode(' ', rpr02a_nz($b)))));
    if (!$wa || !$wb) { return 0.0; }
    return count(array_intersect($wa, $wb)) / max(count($wa), count($wb));
}

$out = array();
foreach ($M['rows'] as $row) {
    if (!in_array($row['verdict'], array('WRONG_LABEL', 'SHARED_ROUTE_MENU_DUP', 'NOT_RENDERED'), true)) { continue; }
    if ($row['route'] === '') { continue; }
    $dir = dirname(strtolower($row['route']));
    $cur = govui_sim2($row['canonical'], $row['rendered'] !== '' ? $row['rendered'] : $row['st_reg']);
    $best = array();
    foreach ($reg as $rt => $g) {
        if (dirname($rt) !== $dir) { continue; }
        if (isset($wired[$rt])) { continue; }
        $s = govui_sim2($row['canonical'], $g['canonical_label_ar']);
        if ($s >= $MIN && $s > $cur + 0.001) { $best[] = array($s, $g['raw'], $g['canonical_label_ar']); }
    }
    if (!$best) { continue; }
    usort($best, function ($a, $b) { return $b[0] <=> $a[0]; });
    $out[] = array('ws' => $row['ws'], 'tid' => $row['target_id'], 'verdict' => $row['verdict'],
        'canonical' => $row['canonical'], 'cur_route' => $row['route'], 'cur_label' => $row['rendered'],
        'cur_sim' => round($cur, 2), 'candidates' => array_slice($best, 0, 3));
}
foreach ($out as $o) {
    printf("%-8s %-16s [%s]\n    حاكم: %s\n    الموصولُ الآن: %s «%s» (قرب %.2f)\n",
        $o['ws'], $o['tid'], $o['verdict'], $o['canonical'], $o['cur_route'], $o['cur_label'], $o['cur_sim']);
    foreach ($o['candidates'] as $c) { printf("    ⇒ مرشَّح %.2f  %-42s «%s»\n", $c[0], $c[1], $c[2]); }
    echo "\n";
}
printf("مرشَّحاتٌ أقربُ من الموصول: %d هدفًا\n", count($out));
file_put_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_WIRE_CANDIDATES.json',
    json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
