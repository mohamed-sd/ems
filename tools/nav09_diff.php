<?php
/**
 * nav09_diff.php — فرقُ ورقةِ NAV-09 الحاكمةِ عن الحيِّ، بندًا بندًا (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * nav09_verify يقول «العدد ≠ المتوقع» ولا يقول أيُّ الصفوفِ غابت. هذه الأداةُ
 * تعيد منطقَه نفسَه (Nav09Reader + كتمُ المالك) وتفصّل لكلِّ دور:
 *   MISSING_HAS_FILE  غائبٌ عن الحيِّ وملفُّه موجود ⇐ يُسجَّل رابطًا
 *   MISSING_NO_FILE   غائبٌ وملفُّه غيرُ موجود ⇐ يحتاج بناءً (لا يُدّعى)
 *   EXTRA             حيٌّ في n9s وليس في الورقة ⇐ يُنقل لمجموعةِ مالكٍ n9o
 *   ORDER_DRIFT       موجودٌ لكن بموضعٍ مخالف
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/nav09_read.php';

$ROOT = dirname(__DIR__);
$doc = Nav09Reader::load($ROOT . '/docs/files/NAV-09-current.xlsx');

/* مرآةُ إعداداتِ nav09_verify حرفيًّا */
$src = (string) file_get_contents(__DIR__ . '/nav09_verify.php');
$DEPT_ROLES = array();
if (preg_match('/\$DEPT_ROLES\s*=\s*(array\s*\(.*?\));/s', $src, $m)) { $DEPT_ROLES = eval('return ' . $m[1] . ';'); }
$SUPPRESS = array();
if (preg_match('/\$SUPPRESS\s*=\s*(array\s*\(.*?\));/s', $src, $m)) { $SUPPRESS = eval('return ' . $m[1] . ';'); }
if (!$DEPT_ROLES) { exit("تعذّر قراءةُ DEPT_ROLES من الفاحص\n"); }
/* routeOf الحقيقي: canonical_file ⇐ real_path عبر nav09_file_map (كما في الفاحص حرفًا) */
$map = array();
$r = mysqli_query($conn, "SELECT canonical_file, state, real_path FROM nav09_file_map");
while ($x = mysqli_fetch_assoc($r)) { $map[$x['canonical_file']] = $x; }
$routeOf = function ($cf) use ($map) {
    if (isset($map[$cf]) && $map[$cf]['state'] !== 'soon' && $map[$cf]['real_path'] !== null) {
        return $map[$cf]['real_path'];
    }
    return 'main/soon.php?screen=' . $cf;
};

$sum = array('miss_file' => 0, 'miss_build' => 0, 'extra' => 0);
foreach ($doc['depts'] as $dept) {
    if (!isset($DEPT_ROLES[$dept['name']])) { continue; }
    foreach ($DEPT_ROLES[$dept['name']] as $role) {
        $expected = array(); $seenR = array();
        foreach ($dept['rows'] as $row) {
            if ($row['kind'] !== 'screen') { continue; }
            if (isset($SUPPRESS[$role]) && in_array($row['file'], $SUPPRESS[$role], true)) { continue; }
            $route = $routeOf($row['file']);
            $base = preg_replace('/#.*/', '', $route);
            $expected[$base] = array('stage' => $row['stage'], 'group' => (string) $row['group'],
                                     'title' => $row['title'], 'file' => $row['file']);
        }
        $got = array();
        $r = mysqli_query($conn,
            "SELECT ni.id, lg.stage_no, lg.name gname, ni.label_ar, ni.route
             FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
             WHERE ni.role_id = $role AND ni.active = 1
               AND lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'");
        while ($x = mysqli_fetch_assoc($r)) { $got[preg_replace('/#.*/', '', $x['route'])] = $x; }

        $missF = array(); $missB = array(); $extra = array();
        foreach ($expected as $route => $e) {
            if (isset($got[$route])) { continue; }
            $file = $ROOT . '/' . preg_replace('/\?.*/', '', $route);
            if (is_file($file)) { $missF[] = $route . '  [' . $e['stage'] . '·' . $e['group'] . ']'; }
            else { $missB[] = $route; }
        }
        foreach ($got as $route => $g) {
            if (!isset($expected[$route])) { $extra[] = $route . '  (#' . $g['id'] . ')'; }
        }
        if (!$missF && !$missB && !$extra) { continue; }
        printf("\n▐ %s — دور %d  (متوقع %d · حي %d)\n", $dept['name'], $role, count($expected), count($got));
        foreach ($missF as $x) { echo "   ⊕ غائبٌ وملفُّه موجود: $x\n"; }
        foreach ($missB as $x) { echo "   ✂ يحتاج بناءً: $x\n"; }
        foreach ($extra as $x) { echo "   ⊖ زائدٌ عن الورقة: $x\n"; }
        $sum['miss_file'] += count($missF); $sum['miss_build'] += count($missB); $sum['extra'] += count($extra);
    }
}
printf("\n══ الجملة: يُسجَّل رابطًا=%d · يحتاج بناءً=%d · زائدٌ يُنقل=%d ══\n",
    $sum['miss_file'], $sum['miss_build'], $sum['extra']);
