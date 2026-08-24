<?php
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');

/* القرص — بالاسم المجرَّد */
$disk = array();
foreach (glob(__DIR__.'/../*', GLOB_ONLYDIR) as $dir) {
    $b = basename($dir);
    if (in_array($b, array('vendor','tools','database','tests','includes','node_modules','docs','.git','assets','uploads','logs','backups','scripts','.ssdiff'))) continue;
    foreach (glob($dir.'/*.php') as $f) {
        $bn = basename($f);
        if (preg_match('/_(handler|api|ajax|export|print|partial)\.php$/i', $bn)) continue; // ليست شاشات
        $disk[$bn] = $b . '/' . $bn;
    }
}
echo "على القرص (شاشاتٌ مرشَّحة، بلا handler/api/ajax/export/print): " . count($disk) . "\n";

$reg = array();
$r = $conn->query("SELECT DISTINCT screen_file FROM gov_screen_cycle WHERE screen_file<>''");
while ($x=$r->fetch_assoc()) $reg[basename(trim($x['screen_file']))] = 'cycle';
$r = $conn->query("SELECT DISTINCT route FROM nav_canonical_current WHERE route<>''");
while ($x=$r->fetch_assoc()) { $rt = preg_replace('#\?.*$#','',trim($x['route'])); if($rt!=='') $reg[basename($rt)] = isset($reg[basename($rt)])?'both':'nav'; }
$r = $conn->query("SELECT DISTINCT link FROM nav_items WHERE link<>''");
if ($r) while ($x=$r->fetch_assoc()) { $rt = preg_replace('#\?.*$#','',trim($x['link'])); if($rt!=='') { $b=basename($rt); if(!isset($reg[$b])) $reg[$b]='nav_items'; } }
echo "في السجلّات (cycle + canonical_current + nav_items): " . count($reg) . "\n\n";

$outside = array_diff_key($disk, $reg);
$ghost   = array_diff_key($reg, $disk);
echo "▸ على القرصِ خارجَ كلِّ السجلّات : " . count($outside) . "\n";
foreach (array_slice($outside, 0, 20) as $bn => $path) echo "    · $path\n";
if (count($outside)>20) echo "    … و" . (count($outside)-20) . " غيرها\n";
echo "\n▸ في السجلّاتِ بلا ملفٍّ على القرص (شبح) : " . count($ghost) . "\n";
foreach (array_slice(array_keys($ghost), 0, 12) as $bn) echo "    · $bn (" . $ghost[$bn] . ")\n";
if (count($ghost)>12) echo "    … و" . (count($ghost)-12) . " غيرها\n";
