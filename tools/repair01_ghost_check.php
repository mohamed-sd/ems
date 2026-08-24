<?php
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/lib/xlsx_io.php';
$conn = $GLOBALS['conn']; mysqli_set_charset($conn,'utf8mb4');
$w10 = xlsx_read(__DIR__.'/../docs/REPAIR01_20260823/10 · المصالحة مع النظام.xlsx');

/* أشباحُ القاعدة */
$disk = array();
foreach (glob(__DIR__.'/../*', GLOB_ONLYDIR) as $d) {
    $b=basename($d);
    if (in_array($b,array('vendor','tools','database','tests','includes','node_modules','docs','.git','assets','uploads','logs','backups','.ssdiff'))) continue;
    foreach (glob($d.'/*.php') as $f) $disk[basename($f)]=1;
}
$ghost=array();
$r=$conn->query("SELECT DISTINCT dept_name, screen_title, screen_file FROM gov_screen_cycle WHERE screen_file<>''");
while($x=$r->fetch_assoc()) if(!isset($disk[basename(trim($x['screen_file']))])) $ghost[basename(trim($x['screen_file']))]=$x;
echo "أشباحُ gov_screen_cycle (مسجَّلٌ بلا ملفّ): ".count($ghost)."\n";

/* شيت 04 مستهدف غير مبني */
$s4=$w10['04_مستهدف_غير_مبني']; $t=array();
foreach($s4 as $ri=>$row){ if($ri<=3) continue; $v=trim(isset($row[1])?$row[1]:''); if($v!=='') $t[$v]=trim(isset($row[0])?$row[0]:''); }
echo "شيت 04 «مستهدف غير مبني»: ".count($t)." سطحًا\n";
echo "  عيّنة: ".implode(' · ',array_slice(array_keys($t),0,6))."\n\n";

/* شيت 03 مبني بلا مقابل */
$s3=$w10['03_مبني_بلا_مقابل']; $b3=array();
foreach($s3 as $ri=>$row){ if($ri<=3) continue; $f=trim(isset($row[2])?$row[2]:''); if($f!=='') $b3[basename($f)]=1; }
echo "شيت 03 «مبني بلا مقابل»: ".count($b3)." ملفًّا\n";
$g3=count(array_intersect_key($ghost,$b3));
echo "  منها أشباحٌ في القاعدة: $g3\n\n";

/* هل الأشباح موسومةٌ في الوثيقة أصلًا؟ */
$s2=$w10['02_شاشة_بشاشة']; $d2=array();
foreach($s2 as $ri=>$row){ if($ri<=1) continue; $f=trim(isset($row[2])?$row[2]:''); if($f!=='') $d2[basename($f)]=trim(isset($row[6])?$row[6]:'');}
$inDoc=array_intersect_key($ghost,$d2);
echo "الأشباحُ الظاهرةُ في شيت 02 كشاشاتٍ «مبنيّة»: ".count($inDoc)."\n";
$cls=array(); foreach($inDoc as $k=>$_) { $c=$d2[$k]; $cls[$c]=(isset($cls[$c])?$cls[$c]:0)+1; }
arsort($cls); foreach($cls as $k=>$v) printf("   %-34s %d\n", mb_substr($k,0,34), $v);
echo "\n   عيّنةُ أشباحٍ موصوفةٍ بأنّها مبنيّة:\n";
$i=0; foreach($inDoc as $f=>$_){ if($i++>=8) break; printf("     %-30s [%s] %s\n", $f, $d2[$f], mb_substr($ghost[$f]['dept_name'],0,20)); }
