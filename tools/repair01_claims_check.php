<?php
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/lib/xlsx_io.php';
$conn = $GLOBALS['conn']; mysqli_set_charset($conn, 'utf8mb4');
$D = __DIR__ . '/../docs/REPAIR01_20260823/';

echo "══ ادّعاءاتُ الملفِّ 10 مقابلَ المقيس ══\n\n";
$w10 = xlsx_read($D . '10 · المصالحة مع النظام.xlsx');

/* 03 مبني بلا مقابل / 04 مستهدف غير مبني / 06 تكرار / 07 غير محسوم / 08 مؤشرات */
foreach (array('03_مبني_بلا_مقابل'=>3,'04_مستهدف_غير_مبني'=>3,'06_تكرار_الشاشات'=>3,'07_غير_المحسوم'=>3,'08_مؤشرات_المشاكل'=>3) as $sh=>$hdr) {
    if (!isset($w10[$sh])) { echo "  ✗ $sh مفقود\n"; continue; }
    $n = 0; foreach ($w10[$sh] as $ri=>$r) { if ($ri<=$hdr) continue; if (trim(implode('',$r))!=='') $n++; }
    printf("  %-26s %d صفًّا\n", $sh, $n);
}

/* 05 التداخلات: تصنيفات */
$s5 = $w10['05_التداخلات_والملكية'];
$cls = array(); $own = array();
foreach ($s5 as $ri=>$r) { if ($ri<=3) continue;
    $c = trim(isset($r[4])?$r[4]:''); if($c!=='') $cls[$c] = (isset($cls[$c])?$cls[$c]:0)+1;
    $o = trim(isset($r[3])?$r[3]:''); if($o!=='') $own[$o] = (isset($own[$o])?$own[$o]:0)+1;
}
echo "\n  05_التداخلات › التصنيف:\n"; arsort($cls);
foreach ($cls as $k=>$v) printf("     %-40s %d\n", mb_substr($k,0,40), $v);
echo "  05_التداخلات › مالكٌ مجهول: " . (isset($own['غير معروف'])?$own['غير معروف']:0)
   . " · " . (isset($own['—'])?$own['—']:0) . "(—)\n";

/* شاشاتٌ على القرص خارج السجلّات */
echo "\n══ القرصُ مقابلَ السجلّات ══\n";
$disk = array();
foreach (glob(__DIR__.'/../*', GLOB_ONLYDIR) as $dir) {
    $b = basename($dir);
    if (in_array($b, array('vendor','tools','database','tests','includes','node_modules','docs','.git','assets','uploads','logs','backups'))) continue;
    foreach (glob($dir.'/*.php') as $f) $disk[$b.'/'.basename($f)] = 1;
}
echo "  على القرص (مجلّداتُ إدارات): " . count($disk) . "\n";
$reg = array();
$r = $conn->query("SELECT DISTINCT screen_file FROM gov_screen_cycle WHERE screen_file<>''");
while ($x=$r->fetch_assoc()) $reg[trim($x['screen_file'])] = 1;
$r = $conn->query("SELECT DISTINCT route FROM nav_canonical_current WHERE route<>''");
if ($r) while ($x=$r->fetch_assoc()) { $rt = trim($x['route']); $rt = preg_replace('#^\.\./#','',$rt); $rt = preg_replace('#\?.*$#','',$rt); if($rt!=='') $reg[$rt]=1; }
echo "  في السجلّات (cycle+canonical_current): " . count($reg) . "\n";
$outside = array_diff_key($disk, $reg);
echo "  على القرصِ خارجَ السجلّات: " . count($outside) . "\n";
echo "     عيّنة: " . implode(' · ', array_slice(array_keys($outside),0,12)) . "\n";
