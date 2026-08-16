<?php
/**
 * 2027_05_15_nav09_relocate_collisions.php
 * ═══════════════════════════════════════════════════════════════════════════
 * الخمسةُ الأخيرة: صفوفٌ حيّةٌ لنفسِ (الدور × المسار) تعيش **خارجَ n9s**
 * (نُقلت زوائدَ سابقًا أو من مجموعاتٍ قديمة) فيمسكها القيدُ الفريدُ
 * `uq_nav_role_route` ويمنع زرعَ الصفِّ في موضعِ الورقة. والحكم: **النقلُ لا
 * الإدراع** — الصفُّ القائمُ يُنقل إلى مجموعةِ n9s المتوقعةِ بعنوانِ الورقةِ
 * وترتيبِها، فيبقى صفًّا واحدًا في موضعِه الصحيح.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/tools/nav09_read.php';

$src = (string) file_get_contents($ROOT . '/tools/nav09_verify.php');
preg_match('/\$DEPT_ROLES\s*=\s*(array\s*\(.*?\));/s', $src, $m);
$DEPT_ROLES = eval('return ' . $m[1] . ';');
$SUPPRESS = array();
if (preg_match('/\$SUPPRESS\s*=\s*(array\s*\(.*?\));/s', $src, $m2)) { $SUPPRESS = eval('return ' . $m2[1] . ';'); }

$doc = Nav09Reader::load($ROOT . '/docs/files/NAV-09-current.xlsx');
$map = array();
$r = mysqli_query($conn, "SELECT canonical_file, state, real_path FROM nav09_file_map");
while ($x = mysqli_fetch_assoc($r)) { $map[$x['canonical_file']] = $x; }
$routeOf = function ($cf) use ($map) {
    if (isset($map[$cf]) && $map[$cf]['state'] !== 'soon' && $map[$cf]['real_path'] !== null) {
        return $map[$cf]['real_path'];
    }
    return 'main/soon.php?screen=' . $cf;
};
$one = function (string $s) use ($conn) { $r = mysqli_query($conn, $s); return $r ? mysqli_fetch_row($r)[0] : null; };
$esc = function ($s) use ($conn) { return mysqli_real_escape_string($conn, (string) $s); };

echo "══ نقلُ التصادماتِ إلى مواضعِها ══\n\n";
$fixed = 0;
foreach ($doc['depts'] as $dept) {
    if (!isset($DEPT_ROLES[$dept['name']])) { continue; }
    foreach ($DEPT_ROLES[$dept['name']] as $role) {
        $seen = array(); $i = -1;
        foreach ($dept['rows'] as $row) {
            if ($row['kind'] !== 'screen') { continue; }
            if (isset($SUPPRESS[$role]) && in_array($row['file'], $SUPPRESS[$role], true)) { continue; }
            $i++;
            $base = preg_replace('/#.*/', '', $routeOf($row['file']));
            $dbRoute = $base;
            if (isset($seen[$base])) { $seen[$base]++; $dbRoute = $base . '#' . $seen[$base]; }
            else { $seen[$base] = 1; }

            /* أفي n9s صفٌّ لهذا الموضع؟ */
            $has = (int) $one("SELECT COUNT(*) FROM nav_items ni JOIN link_groups lg ON lg.id=ni.group_id
                               WHERE ni.role_id=$role AND ni.route='" . $esc($dbRoute) . "'
                                 AND lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'");
            if ($has) { continue; }

            /* الصفُّ القائمُ خارجَ n9s (بالبادئة) يُنقل */
            $nid = (int) $one("SELECT ni.id FROM nav_items ni JOIN link_groups lg ON lg.id=ni.group_id
                               WHERE ni.role_id=$role AND ni.route='" . $esc($base) . "'
                                 AND (lg.group_code NOT LIKE 'n9s%' OR lg.group_code LIKE 'n9s99_others%')
                               LIMIT 1");
            if (!$nid) { echo "  ⚠ دور $role: $dbRoute لا صفَّ قائمًا يُنقل ولا مزروعًا\n"; continue; }

            $gid = (int) $one("SELECT id FROM link_groups
                               WHERE owner_role_id=$role AND group_code LIKE 'n9s%'
                                 AND group_code NOT LIKE 'n9s99_others%'
                                 AND stage_no={$row['stage']} AND name='" . $esc((string) $row['group']) . "' LIMIT 1");
            if (!$gid) { echo "  ⚠ دور $role: مجموعةُ «{$row['group']}» غائبة\n"; continue; }
            mysqli_query($conn, "UPDATE nav_items
                                 SET group_id=$gid, label_ar='" . $esc((string) $row['title']) . "',
                                     route='" . $esc($dbRoute) . "', sort_order=" . ($i + 1) . ", active=1
                                 WHERE id=$nid");
            echo "  ✔ دور $role: نُقل $base إلى [{$row['stage']}·{$row['group']}]\n";
            $fixed++;
        }
    }
}
echo "\n  نُقل: $fixed\n✔ تمّت\n";
