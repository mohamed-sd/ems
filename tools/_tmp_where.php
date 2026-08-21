<?php
/* أينَ يظهر رابطُ الشاشةِ الموحَّدةِ لكلِّ دورٍ — قياسٌ على المُصيَّرِ الحيّ */
if (PHP_SAPI !== 'cli') { exit("CLI\n"); }
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
$ROUTE = 'finrequests/request_form.php';
$users = uxp_role_users($conn);
$r = $conn->query("SELECT id,name,status FROM roles WHERE id <> -1 ORDER BY id+0");
$roles = array(); while ($x = $r->fetch_assoc()) { $roles[] = $x; }
printf("%-4s %-30s %-24s %-22s %s\n", 'دور', 'الاسم', 'المجموعة', 'القسم', 'التسمية');
echo str_repeat('-', 108) . "\n";
$grp = array(); $miss = array();
foreach ($roles as $ro) {
    $rid = (int) $ro['id'];
    $pos = uxp_render_role($conn, $rid, isset($users[$rid]) ? $users[$rid] : null);
    $hit = null;
    foreach ($pos as $p) { if (strtolower(uxp_norm($p['href'])) === $ROUTE) { $hit = $p; break; } }
    if ($hit) {
        printf("%-4d %-30s %-24s %-22s %s\n", $rid, mb_substr($ro['name'],0,28), $hit['group'], mb_substr($hit['section'],0,20), $hit['label']);
        $grp[$hit['group']] = (isset($grp[$hit['group']]) ? $grp[$hit['group']] : 0) + 1;
    } else {
        printf("%-4d %-30s %-24s %-22s %s\n", $rid, mb_substr($ro['name'],0,28), '- غائب -', '', '(روابطُه ' . count($pos) . ')');
        $miss[] = $rid;
    }
}
echo str_repeat('-', 108) . "\n";
foreach ($grp as $g => $n) { echo "  «{$g}» : {$n} دورًا\n"; }
echo "  غائبٌ عن: " . (count($miss) ? implode(' · ', $miss) : 'لا أحد') . "\n";
