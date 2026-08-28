<?php
/**
 * tools/repair01_ops_nav_probe.php — سايدبارُ `محمد` كما يراه هو، مُصيَّرًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والقياسُ على الصفوفِ ليس قياسًا على الشاشة**: صفٌّ `active = 1` قد لا
 *   يُصيَّر (‏حارسُ التوأمِ · كابحُ المساحةِ · غلافٌ خلا من روابطِه · بوابةُ
 *   المجالِ المقيَّد fail-closed)، **وبنيةٌ صحيحةٌ وشاشةٌ فارغةٌ هي بعينِها
 *   ملاحظةُ المالكِ في `RPR-SUP-03`**. ⇒ يُصيَّر السايدبارُ بجلسةِ المستخدمِ
 *   نفسِه (`users.id = 4` · الدور 1 · `co4`) ويُقرأ من HTML.
 *
 * التشغيل: php tools/repair01_ops_nav_probe.php [--save <اسم>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';

$UID = 4; $ROLE = 1;                       /* `محمد` — مسؤول التشغيل */
$SAVE = '';
foreach ($argv as $i => $a) { if ($a === '--save' && isset($argv[$i + 1])) { $SAVE = $argv[$i + 1]; } }

$html = uxp_render_role_html($conn, $ROLE, $UID);
$pos  = uxp_parse_nav_html($html);

$byGroup = array();
foreach ($pos as $p) {
    $g = $p['group'];
    if (!isset($byGroup[$g])) { $byGroup[$g] = array(); }
    $byGroup[$g][] = $p;
}
printf("\n═══ سايدبارُ `محمد` مُصيَّرًا — الدور %d · المستخدم %d ═══\n", $ROLE, $UID);
printf("  رؤوسٌ: %d · روابطُ مُصيَّرة: %d\n\n", count($byGroup), count($pos));
foreach ($byGroup as $g => $items) {
    printf("  ▸ %-46s (%d)\n", mb_substr($g, 0, 44), count($items));
    $sec = null;
    foreach ($items as $p) {
        $s = ($p['section'] !== '' && $p['section'] !== $g) ? $p['section'] : '';
        if ($s !== $sec) { $sec = $s; if ($s !== '') { printf("     ── %s\n", $s); } }
        printf("        %-36s %s\n", mb_substr($p['label'], 0, 34), uxp_norm($p['href']));
    }
}
if ($SAVE !== '') {
    $dir = $ROOT . '/docs/REPAIR01_20260823/evidence';
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }
    file_put_contents($dir . '/ops_sidebar_' . $SAVE . '.json',
        json_encode(array('role' => $ROLE, 'uid' => $UID, 'positions' => $pos),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n✔ حُفظ evidence/ops_sidebar_$SAVE.json\n";
}
