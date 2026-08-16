<?php
/**
 * 2027_05_14_nav09_anchor_duplicates.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مرساةُ التكرارِ — الحلقةُ الأخيرةُ في مزامنةِ nav09
 *
 * القياس: الزرعُ الثاني (05_13) أدرج التكرارَ المقصودَ فاصطدم بالقيدِ الفريدِ
 * (role×route) وسقط **صامتًا** — فالعجزُ لكلِّ دورٍ = عددُ تكراراتِ ورقتِه
 * بالضبط (4 للتشغيل · 4 للصيانة · 6 للموردين…). والمولّدُ الأصليُّ يحلُّها
 * بلاحقةِ مرساةٍ في المسارِ المخزَّن (file.php#2) يمحوها الفاحصُ عند المقارنةِ
 * بقصِّ ما بعدَ العلامة — فالتكرارُ يعيش بنيويًّا والمقارنةُ بالبادئة.
 * هذه الهجرةُ تعيد زرعَ أقسامِ الورقةِ باللاحقة،
 * وتنقل زائدَ دورِ 11 (خارجَ أقسامِ الورقة) لمجموعةِ مالكِه.
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

echo "══ مرساةُ التكرار ══\n\n";
$tot = array('roles' => 0, 'inserted' => 0, 'anchored' => 0);

foreach ($doc['depts'] as $dept) {
    if (!isset($DEPT_ROLES[$dept['name']])) { continue; }
    foreach ($DEPT_ROLES[$dept['name']] as $role) {
        $tot['roles']++;
        $rows = array(); $seen = array();
        foreach ($dept['rows'] as $row) {
            if ($row['kind'] !== 'screen') { continue; }
            if (isset($SUPPRESS[$role]) && in_array($row['file'], $SUPPRESS[$role], true)) { continue; }
            $base = preg_replace('/#.*/', '', $routeOf($row['file']));
            $dbRoute = $base;
            if (isset($seen[$base])) { $seen[$base]++; $dbRoute = $base . '#' . $seen[$base]; $tot['anchored']++; }
            else { $seen[$base] = 1; }
            $rows[] = array('stage' => (int) $row['stage'], 'group' => (string) $row['group'],
                            'title' => (string) $row['title'], 'route' => $dbRoute, 'base' => $base);
        }
        if (!$rows) { continue; }

        mysqli_query($conn,
            "DELETE ni FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
             WHERE ni.role_id = $role
               AND lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'");

        $gCache = array();
        foreach ($rows as $i => $rw) {
            $gKey = $rw['stage'] . '|' . $rw['group'];
            if (!isset($gCache[$gKey])) {
                $gid = (int) $one("SELECT id FROM link_groups
                                   WHERE owner_role_id=$role AND group_code LIKE 'n9s%'
                                     AND group_code NOT LIKE 'n9s99_others%'
                                     AND stage_no={$rw['stage']} AND name='" . $esc($rw['group']) . "' LIMIT 1");
                if (!$gid) {
                    $gc = 'n9s02_' . $rw['stage'] . '_x' . ($i + 1) . '_r' . $role;
                    mysqli_query($conn, "INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                                         VALUES ('" . $esc($rw['group']) . "', '" . $esc($gc) . "', $role, 'fa fa-circle-dot', " . (100 + $i) . ", {$rw['stage']}, '', 1)");
                    $gid = (int) mysqli_insert_id($conn);
                }
                $gCache[$gKey] = $gid;
            }
            $gid = $gCache[$gKey];
            $baseQ = $esc($rw['base']);
            $mid = (int) $one("SELECT id FROM modules WHERE code='$baseQ' LIMIT 1");
            if (!$mid) {
                mysqli_query($conn, "INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                                     VALUES ('" . $esc($rw['title']) . "', '$baseQ', $role, 1, 0, 'fa fa-circle-dot', 100)");
                $mid = (int) mysqli_insert_id($conn);
            }
            $q = mysqli_query($conn, "SELECT 1 FROM role_permissions WHERE role_id=$role AND module_id=$mid");
            if (!($q && mysqli_num_rows($q))) {
                mysqli_query($conn, "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                                     VALUES ($role, $mid, 1, 0, 0, 0)");
            }
            /* المسارُ المخزَّنُ باللاحقةِ عند التكرار · ورمزُ الصلاحيةِ بالبادئةِ دومًا */
            if (!mysqli_query($conn, "INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at)
                                 VALUES ($role, 'DAILY', $gid, $mid, '" . $esc($rw['title']) . "', '" . $esc($rw['route']) . "', 'fa fa-circle-dot', " . ($i + 1) . ", '$baseQ', 1, NOW())")) {
                echo "  ⚠ دور $role: {$rw['route']} — " . mysqli_error($conn) . "\n";
                continue;
            }
            $tot['inserted']++;
        }
    }
}

/* زائدُ دورِ 11 خارجَ أقسامِ الورقة ⇐ مجموعةُ مالكِه */
$gid11 = (int) $one("SELECT id FROM link_groups WHERE group_code='n9o_extra_r11'");
if (!$gid11) {
    mysqli_query($conn, "INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                         VALUES ('إضافاتُ المالك', 'n9o_extra_r11', 11, 'fa fa-plus', 97, 97, 'إضافاتُ المالك', 1)");
    $gid11 = (int) mysqli_insert_id($conn);
}
mysqli_query($conn, "UPDATE nav_items ni JOIN link_groups lg ON lg.id=ni.group_id
                     SET ni.group_id=$gid11
                     WHERE ni.role_id=11 AND ni.route LIKE 'Financing/owners_registry%'
                       AND lg.group_code LIKE 'n9s%'");
echo '  زائدُ دورِ 11 نُقل: ' . mysqli_affected_rows($conn) . "\n";

printf("  أدوار: %d · زُرع: %d · مراسٍ: %d\n", $tot['roles'], $tot['inserted'], $tot['anchored']);
echo "\n✔ تمّت\n";
