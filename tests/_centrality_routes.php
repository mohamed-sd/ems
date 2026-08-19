<?php
/**
 * tests/_centrality_routes.php — مصدرُ نظراءِ اختبارِ المركزية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يطبعُ مساراتِ سايدبارِ دورٍ **كما تُصيَّر** — من `uxp_render_role` نفسِها،
 *   وهي المصدرُ القانونيُّ في هذه الجولة. ولا يُكشَط الهبوطُ: `dashboard.php`
 *   موجِّهٌ لا صفحة، فكشطُه أعطى نظيرَين اثنَين ومقامًا كاذبًا.
 * ◆ منفصلٌ عن الاختبارِ لأن مولِّدَ التنقلِ يفتحُ جلسةً ويطبعُ ما يطبع، فيُعزَل
 *   في عمليةٍ خاصةٍ حتى لا يختلطَ مخرَجُه بالقياس.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(0);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
require_once $ROOT . '/includes/status_display.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$conn = isset($conn) && $conn ? $conn : (isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null);
if (!$conn) { require_once $ROOT . '/tools/fix_lib.php'; $conn = fix_db(); }

$ROLE = isset($argv[1]) ? (int) $argv[1] : 1;
$items = uxp_render_role($conn, $ROLE);
$seen = array();
foreach ($items as $it) {
    $h = isset($it['href']) ? (string) $it['href'] : '';
    if ($h === '' || $h === '#') { continue; }
    $h = preg_replace('/[?#].*$/', '', $h);
    $h = preg_replace('#^(\.\./)+#', '', $h);
    $h = ltrim($h, '/');
    if (substr($h, -4) !== '.php') { continue; }
    if (stripos($h, 'logout') !== false || stripos($h, 'login') !== false) { continue; }
    if (!is_file($ROOT . '/' . $h)) { continue; }
    if (isset($seen[$h])) { continue; }
    $seen[$h] = true;
    echo $h . "\n";
}
