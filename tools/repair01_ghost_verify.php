<?php
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn']; mysqli_set_charset($conn,'utf8mb4');
$root = realpath(__DIR__ . '/..');

/* فهرسٌ عوديٌّ كاملٌ لكلِّ ملفّات php في الشجرة */
$all = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $p = $f->getPathname();
    if (strpos($p, '\.git') !== false || strpos($p,'node_modules') !== false) continue;
    if (substr($p,-4) !== '.php') continue;
    $all[strtolower(basename($p))][] = str_replace($root.DIRECTORY_SEPARATOR,'',$p);
}
echo "ملفّاتُ php في الشجرة كلِّها: " . count($all) . " اسمًا متفرّدًا\n\n";

$rows = array(); $r = $conn->query("SELECT dept_name, screen_title, screen_file, stage_kind FROM gov_screen_cycle WHERE screen_file<>''");
while ($x = $r->fetch_assoc()) $rows[] = $x;

$ghost = array(); $ok = 0; $byDept = array(); $byKind = array();
$seen = array();
foreach ($rows as $x) {
    $bn = strtolower(basename(trim($x['screen_file'])));
    if (isset($seen[$bn])) continue; $seen[$bn] = 1;
    if (isset($all[$bn])) { $ok++; continue; }
    $ghost[$bn] = $x;
    $d = $x['dept_name']; $byDept[$d] = (isset($byDept[$d])?$byDept[$d]:0)+1;
    $k = $x['stage_kind']; $byKind[$k] = (isset($byKind[$k])?$byKind[$k]:0)+1;
}
echo "═══ ملفّاتُ gov_screen_cycle المتفرّدة: " . count($seen) . " ═══\n";
echo "  موجودٌ على القرص : $ok\n";
echo "  ✗ بلا ملفٍّ إطلاقًا : " . count($ghost) . "\n\n";
echo "التوزيعُ على الإدارات:\n"; arsort($byDept);
foreach ($byDept as $d=>$n) printf("   %-34s %d\n", mb_substr($d,0,34), $n);
echo "\nstage_kind: "; foreach ($byKind as $k=>$n) echo "$k=$n  ";
echo "\n";

/* كم صفًّا من الـ664 يستند إلى شبح؟ */
$gset = array_keys($ghost); $gmap = array_flip($gset); $rowsGhost = 0;
foreach ($rows as $x) if (isset($gmap[strtolower(basename(trim($x['screen_file'])))])) $rowsGhost++;
printf("\nصفوفُ gov_screen_cycle المستندةُ إلى شبح: %d من %d (%.1f%%)\n", $rowsGhost, count($rows), 100*$rowsGhost/count($rows));
