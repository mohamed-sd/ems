<?php
/**
 * 2027_05_13_nav09_resync_exact.php
 * ═══════════════════════════════════════════════════════════════════════════
 * المزامنةُ الحرفيةُ الثانية — عيبانِ في الأولى (2027_05_12) صحّحهما القياس:
 *   ① نسختُ DEPT_ROLES يدويًّا فاختلف اسمُ قسمٍ («الإدارة التنفيذية» لا «مكتب
 *     الرئيس») فسقط دورُ 9 كلُّه من المزامنة — الآن **تُستخرج القوائمُ من نصِّ
 *     الفاحصِ نفسِه وقتَ التشغيل**: مصدرٌ واحدٌ لا نسختان تتفرّقان (درسُ
 *     counter-parity بعينِه).
 *   ② أسقطتُ الصفوفَ المكررةَ المقصودةَ (مرساةُ #) فنقص العددُ 1–6 لكلِّ دور —
 *     الورقةُ تكرّر الشاشةَ عمدًا في موضعين والفاحصُ يتوقع الاثنين.
 * إعادةُ الزرعِ تحذف n9s للدورِ وتبنيه من الورقةِ فتصحّح الأولى تلقائيًّا.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/tools/nav09_read.php';

/* القوائمُ من الفاحصِ نفسِه — لا نسخَ يدويًّا بعد اليوم */
$src = (string) file_get_contents($ROOT . '/tools/nav09_verify.php');
if (!preg_match('/\$DEPT_ROLES\s*=\s*(array\s*\(.*?\));/s', $src, $m)) { exit("✘ تعذّر استخراجُ DEPT_ROLES\n"); }
$DEPT_ROLES = eval('return ' . $m[1] . ';');
$SUPPRESS = array();
if (preg_match('/\$SUPPRESS\s*=\s*(array\s*\(.*?\));/s', $src, $m)) { $SUPPRESS = eval('return ' . $m[1] . ';'); }

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

echo "══ المزامنةُ الحرفية (أقسام: " . count($DEPT_ROLES) . ") ══\n\n";
$tot = array('moved' => 0, 'inserted' => 0, 'grants' => 0, 'groups' => 0, 'roles' => 0);

foreach ($doc['depts'] as $dept) {
    if (!isset($DEPT_ROLES[$dept['name']])) { continue; }
    foreach ($DEPT_ROLES[$dept['name']] as $role) {
        $tot['roles']++;
        /* المتوقعُ بترتيبِ الورقةِ — **مع التكرارِ المقصود** */
        $rows = array(); $seenBase = array();
        foreach ($dept['rows'] as $row) {
            if ($row['kind'] !== 'screen') { continue; }
            if (isset($SUPPRESS[$role]) && in_array($row['file'], $SUPPRESS[$role], true)) { continue; }
            $base = preg_replace('/#.*/', '', $routeOf($row['file']));
            $seenBase[$base] = 1;
            $rows[] = array('stage' => (int) $row['stage'], 'group' => (string) $row['group'],
                            'title' => (string) $row['title'], 'route' => $base);
        }
        if (!$rows) { continue; }

        /* الزائدُ الحيُّ يُنقل لمجموعةِ المالك (لأدوارٍ لم تُزامَن سابقًا كدور 9) */
        $extraGid = 0;
        $res = mysqli_query($conn,
            "SELECT ni.id, ni.route FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
             WHERE ni.role_id = $role AND ni.active = 1
               AND lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'");
        while ($x = mysqli_fetch_assoc($res)) {
            if (isset($seenBase[preg_replace('/#.*/', '', $x['route'])])) { continue; }
            if (!$extraGid) {
                $extraGid = (int) $one("SELECT id FROM link_groups WHERE group_code='n9o_extra_r$role'");
                if (!$extraGid) {
                    mysqli_query($conn, "INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
                                         VALUES ('إضافاتُ المالك', 'n9o_extra_r$role', $role, 'fa fa-plus', 97, 97, 'إضافاتُ المالك', 1)");
                    $extraGid = (int) mysqli_insert_id($conn);
                }
            }
            mysqli_query($conn, "UPDATE nav_items SET group_id=$extraGid WHERE id=" . (int) $x['id']);
            $tot['moved']++;
        }

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
                    $tot['groups']++;
                }
                $gCache[$gKey] = $gid;
            }
            $gid = $gCache[$gKey];
            $codeQ = $esc($rw['route']);
            $mid = (int) $one("SELECT id FROM modules WHERE code='$codeQ' LIMIT 1");
            if (!$mid) {
                mysqli_query($conn, "INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                                     VALUES ('" . $esc($rw['title']) . "', '$codeQ', $role, 1, 0, 'fa fa-circle-dot', 100)");
                $mid = (int) mysqli_insert_id($conn);
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
printf("  أدوارٌ زُومنت: %d · نُقل زائدًا: %d · زُرع: %d · منح: %d · مجموعات: %d\n",
    $tot['roles'], $tot['moved'], $tot['inserted'], $tot['grants'], $tot['groups']);
echo "\n✔ تمّت\n";
