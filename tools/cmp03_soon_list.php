<?php
/* tools/cmp03_soon_list.php — جرد شاشات «قريبًا» الـ56 بورقتها وعدد أعمدتها */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);
$deptDir = array();
foreach ($map as $cf => $m) {
    if ($m['real_path']) { $deptDir[] = $m['real_path']; }
}
$n = 0;
foreach ($screens as $cf => $sc) {
    $st = isset($map[$cf]) ? $map[$cf]['state'] : '(خارج القاموس)';
    $rp = isset($map[$cf]) ? $map[$cf]['real_path'] : null;
    if ($st !== 'soon' && $rp !== null) { continue; }
    $n++;
    echo sprintf("%-28s | %s | %s | أعمدة %d\n", $cf, $sc['owner'], $sc['title'], count($sc['cols']));
}
echo "──── المجموع: $n\n";
