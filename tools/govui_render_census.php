<?php
/**
 * tools/govui_render_census.php — إحصاءُ الروابطِ المُصيَّرةِ لكلِّ دورٍ حيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **بوّابةُ ارتدادٍ لا تقرير**: تغييرُ اسمٍ قد **يُسقط رابطًا** — توأمانِ صارا
 *   باسمٍ واحدٍ فابتلع حارسُ التكرارِ ثانيَهما (قِيس في الدور ٢٤ وكُتب في
 *   `unified_nav.php` تعليقًا). فالعددُ يُلتقط قبلَ الدفعةِ وبعدَها،
 *   **والنقصانُ عطبٌ يوقف** لا يُبتلع.
 *
 * التشغيل:
 *   php tools/govui_render_census.php <out.json>
 *   php tools/govui_render_census.php --diff <before.json> <after.json>
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$NULLDEV = (DIRECTORY_SEPARATOR === '/') ? '/dev/null' : 'NUL';

if (in_array('--diff', $argv, true)) {
    $a = json_decode(file_get_contents($argv[2]), true);
    $b = json_decode(file_get_contents($argv[3]), true);
    $bad = 0;
    foreach ($a as $rid => $n) {
        $m = isset($b[$rid]) ? $b[$rid] : 0;
        if ($m !== $n) {
            printf("  دور %-4s %4d ⇒ %4d  %s\n", $rid, $n, $m, $m < $n ? '⛔ نقص' : '＋ زيادة');
        }
        if ($m < $n) { $bad++; }
    }
    printf("أدوارٌ نقصت: %d من %d\n", $bad, count($a));
    exit($bad > 0 ? 1 : 0);
}

ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$out = array();
$r = $conn->query("SELECT DISTINCT role_id FROM nav_items WHERE active = 1 ORDER BY role_id");
while ($x = $r->fetch_row()) {
    $rid = (int) $x[0];
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php')
         . ' ' . $rid . ' 2>' . $NULLDEV;
    $j = json_decode((string) shell_exec($cmd), true);
    $out[$rid] = (is_array($j) && isset($j['positions'])) ? count($j['positions']) : -1;
}
$dst = isset($argv[1]) ? $argv[1] : ($ROOT . '/docs/REPAIR01_20260823/GOVUI_RENDER_CENSUS.json');
file_put_contents($dst, json_encode($out, JSON_PRETTY_PRINT));
printf("أدوارٌ مُصيَّرة: %d · مجموعُ الروابط: %d ⇐ %s\n", count($out), array_sum($out), $dst);
