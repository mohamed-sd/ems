<?php
/**
 * 2027_05_12_nav09_resync_from_sheet.php
 * ═══════════════════════════════════════════════════════════════════════════
 * مزامنةُ أسطحِ n9s من ورقةِ NAV-09 الحاكمةِ — تسجيلًا لا بناءً
 *
 * القياس (tools/nav09_diff.php): مخالفاتُ «موضع 25» كلُّها روابطُ غائبةٌ
 * **ملفاتُها موجودة** (251) وزوائدُ (16) — صفرُ شاشةٍ تحتاج بناءً. فالحكم:
 *   ① الزائدُ عن الورقةِ لا يُحذف — يُنقل إلى مجموعةِ مالكٍ `n9o_extra_rN`
 *     (البادئةُ n9o معفاةٌ من مقارنةِ الورقةِ بالتصميم) فيبقى حيًّا لمستخدميه.
 *   ② ثم يُعاد زرعُ صفوفِ n9s للدورِ **بترتيبِ الورقةِ حرفًا** (المرحلة ⇐
 *     المجموعة ⇐ الشاشة) — فأمانةُ الموضعِ تُقارن تسلسلًا لا عددًا فقط.
 *   ③ كلُّ رابطٍ بوحدتِه: وحدةُ صلاحياتٍ تُنشأ إن غابت، ومنحةُ عرضٍ للدورِ
 *     إن غابت — «ووحدةُ صلاحياتٍ لكلِّ شاشةٍ قبلَ رابطِها» (TS-07).
 * ◆ مرآةُ الفاحصِ حرفًا: كتمُ المالكِ (approvals_inbox لدور 1) وroutOf عبر
 *   nav09_file_map — فما يُزرع هو عينُ ما سيُقارَن به.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/tools/nav09_read.php';

$DEPT_ROLES = array(
    'مكتب الرئيس التنفيذي' => array(9), 'إدارة الموقع' => array(6, 7), 'إدارة التشغيل' => array(1),
    'إدارة الصيانة' => array(13, 14), 'النقل والترحيل' => array(23), 'المشتريات' => array(16),
    'المخازن' => array(25), 'المبيعات والعقود' => array(12), 'إدارة الموردين' => array(2, 8),
    'إدارة الأسطول' => array(3, 10, 11), 'القوى التشغيلية' => array(27),
    'الموارد البشرية' => array(4), 'المالية والخزينة' => array(17, 18, 19, 20, 21, 22),
    'التمويل والملكية' => array(26), 'مركز البلاغات' => array(24),
    'الحوكمة والالتزام' => array(15),
);
$SUPPRESS = array(1 => array('approvals_inbox.php'));

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

echo "══ مزامنةُ n9s من الورقة ══\n\n";
$tot = array('moved' => 0, 'inserted' => 0, 'modules' => 0, 'grants' => 0, 'groups' => 0);

foreach ($doc['depts'] as $dept) {
    if (!isset($DEPT_ROLES[$dept['name']])) { continue; }
    foreach ($DEPT_ROLES[$dept['name']] as $role) {
        /* المتوقعُ بترتيبِ الورقة */
        $rows = array(); $seen = array();
        foreach ($dept['rows'] as $row) {
            if ($row['kind'] !== 'screen') { continue; }
            if (isset($SUPPRESS[$role]) && in_array($row['file'], $SUPPRESS[$role], true)) { continue; }
            $route = $routeOf($row['file']);
            $base = preg_replace('/#.*/', '', $route);
            if (isset($seen[$base])) { continue; }
            $seen[$base] = 1;
            $rows[] = array('stage' => (int) $row['stage'], 'group' => (string) $row['group'],
                            'title' => (string) $row['title'], 'route' => $base);
        }
        if (!$rows) { continue; }

        /* ① نقلُ الزائدِ الحيِّ إلى مجموعةِ مالك */
        $extraGid = 0;
        $res = mysqli_query($conn,
            "SELECT ni.id, ni.route FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
             WHERE ni.role_id = $role AND ni.active = 1
               AND lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'");
        $extras = array();
        while ($x = mysqli_fetch_assoc($res)) {
            if (!isset($seen[preg_replace('/#.*/', '', $x['route'])])) { $extras[] = $x; }
        }
        foreach ($extras as $x) {
            if (!$extraGid) {
                $extraGid = (int) $one("SELECT id FROM link_groups WHERE group_code='n9o_extra_r$role'");
                if (!$extraGid) {
                    mysqli_query($conn, "INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                                         VALUES ('إضافاتُ المالك', 'n9o_extra_r$role', $role, 'fa fa-plus', 97, 97, 'إضافاتُ المالك', 1)");
                    $extraGid = (int) mysqli_insert_id($conn);
                    $tot['groups']++;
                }
            }
            mysqli_query($conn, "UPDATE nav_items SET group_id=$extraGid WHERE id=" . (int) $x['id']);
            $tot['moved']++;
        }

        /* ② إعادةُ الزرعِ بترتيبِ الورقة — حذفُ صفوفِ n9s للدورِ ثم إدراجٌ نظيف */
        mysqli_query($conn,
            "DELETE ni FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
             WHERE ni.role_id = $role
               AND lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'");

        $ord = 0; $gCache = array();
        foreach ($rows as $i => $rw) {
            $gKey = $rw['stage'] . '|' . $rw['group'];
            if (!isset($gCache[$gKey])) {
                $gid = (int) $one("SELECT id FROM link_groups
                                   WHERE owner_role_id=$role AND group_code LIKE 'n9s%'
                                     AND group_code NOT LIKE 'n9s99_others%'
                                     AND stage_no={$rw['stage']} AND name='" . $esc($rw['group']) . "' LIMIT 1");
                if (!$gid) {
                    $ord++;
                    $gc = 'n9s02_' . $rw['stage'] . '_x' . ($i + 1) . '_r' . $role;
                    mysqli_query($conn, "INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                                         VALUES ('" . $esc($rw['group']) . "', '" . $esc($gc) . "', $role, 'fa fa-circle-dot', " . (100 + $i) . ", {$rw['stage']}, '', 1)");
                    $gid = (int) mysqli_insert_id($conn);
                    $tot['groups']++;
                }
                $gCache[$gKey] = $gid;
            }
            $gid = $gCache[$gKey];

            /* الوحدةُ والمنحة */
            $codeQ = $esc($rw['route']);
            $mid = (int) $one("SELECT id FROM modules WHERE code='$codeQ' LIMIT 1");
            if (!$mid) {
                mysqli_query($conn, "INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                                     VALUES ('" . $esc($rw['title']) . "', '$codeQ', $role, 1, 0, 'fa fa-circle-dot', 100)");
                $mid = (int) mysqli_insert_id($conn);
                $tot['modules']++;
            }
            $q = mysqli_query($conn, "SELECT 1 FROM role_permissions WHERE role_id=$role AND module_id=$mid");
            if (!($q && mysqli_num_rows($q))) {
                mysqli_query($conn, "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
                                     VALUES ($role, $mid, 1, 0, 0, 0)");
                $tot['grants']++;
            }
            mysqli_query($conn, "INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active, created_at)
                                 VALUES ($role, 'DAILY', $gid, $mid, '" . $esc($rw['title']) . "', '$codeQ', 'fa fa-circle-dot', " . ($i + 1) . ", '$codeQ', 1, NOW())");
            $tot['inserted']++;
        }
    }
}

printf("  نُقل زائدًا: %d · زُرع: %d · وحداتٌ جديدة: %d · منحُ عرض: %d · مجموعاتٌ: %d\n",
    $tot['moved'], $tot['inserted'], $tot['modules'], $tot['grants'], $tot['groups']);
echo "\n✔ تمّت — شغّل nav09_verify للحكم\n";
