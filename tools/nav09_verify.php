<?php
/**
 * tools/nav09_verify.php — فاحصُ مطابقة المولَّد للوثيقة (NAV-09)
 * ───────────────────────────────────────────────────────────────────────────
 * أربعةُ محاورَ يجب أن تصفر جميعًا:
 *  ① أمانةُ الموضع: لكل دورٍ، تسلسلُ (مرحلة ← مجموعة ← شاشة) المولَّدُ يطابق
 *     ورقةَ إدارته في المصنّف صفًّا صفًّا — لا زيادةَ ولا نقصانَ ولا انزياح.
 *  ② سلامةُ الوجهة: كلُّ مسارٍ مولَّدٍ إما ملفٌّ موجودٌ على القرص أو «قريبًا»
 *     لقانونيٍّ معرَّفٍ في القاموس.
 *  ③ الصلاحيات: لا رابطَ حيًّا لدورٍ صفُّ صلاحياته على وحدة وجهته يمنع العرض
 *     (can_view=0 صراحةً) — «لا يُعرض ما لا يُملك».
 *  ④ المجالُ المقيَّد: شاشاتُ Financing لا تظهر إلا لمن بوابتُها تفتح له.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/nav09_read.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

$DEPT_ROLES = array(
    'الإدارة التنفيذية' => array(9),
    'إدارة الموقع' => array(6, 7), 'إدارة التشغيل' => array(1),
    'إدارة الصيانة' => array(13, 14), 'النقل والترحيل' => array(23),
    'المشتريات' => array(16), 'المخازن' => array(25),
    'المبيعات والعقود' => array(12), 'إدارة الموردين' => array(2, 8),
    'القوى التشغيلية' => array(27), 'إدارة الأسطول' => array(3, 10),
    'الموارد البشرية' => array(4), 'المالية والخزينة' => array(17, 18, 19, 20, 21, 22),
    'التمويل والملكية' => array(26), 'مركز البلاغات' => array(24),
    'الحوكمة والالتزام' => array(15),
);

/* كتمُ المالك — مرآةُ قاعدة nav09_import.php حرفيًّا (2026-08-03):
   الصندوقُ الجامع يُسقط من التشغيل، فالمتوقعُ هنا يُسقطه أيضًا وإلا كذب العدّ */
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

$fails = array('موضع' => array(), 'وجهة' => array(), 'صلاحية' => array(), 'مقيد' => array());

/* ── ① أمانةُ الموضع: التسلسل المتوقع من الورقة == المولَّد لكل دور ─────── */
foreach ($doc['depts'] as $dept) {
    if (!isset($DEPT_ROLES[$dept['name']])) { continue; }
    foreach ($DEPT_ROLES[$dept['name']] as $role) {
        /* المتوقع: [ [stage, group, title, route], ... ] بترتيب الورقة (مع مرساة التكرار)
           لكل دورٍ على حدة — فكتمُ المالك ($SUPPRESS) قاعدةٌ دوريّة */
        $expected = array(); $seen = array();
        foreach ($dept['rows'] as $row) {
            if ($row['kind'] !== 'screen') { continue; }
            if (isset($SUPPRESS[$role]) && in_array($row['file'], $SUPPRESS[$role], true)) { continue; }
            $route = $routeOf($row['file']);
            if (isset($seen[$route])) { $route .= '#'; } // التكرار المقصود بمرساة — تُقارن بالبادئة
            $seen[$routeBase = preg_replace('/#.*/', '', $route)] = 1;
            $expected[] = array($row['stage'], (string) $row['group'], $row['title'], $routeBase);
        }
        $got = array();
        $r = mysqli_query($conn,
            "SELECT lg.stage_no, lg.name gname, ni.label_ar, ni.route
             FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
             WHERE ni.role_id = $role AND ni.active = 1
               AND lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'
             ORDER BY lg.display_order, ni.sort_order");
        while ($x = mysqli_fetch_assoc($r)) {
            $got[] = array(intval($x['stage_no']), $x['gname'], $x['label_ar'],
                           preg_replace('/#.*/', '', $x['route']));
        }
        if (count($expected) !== count($got)) {
            $fails['موضع'][] = "«{$dept['name']}» دور $role: العدد " . count($got) . " ≠ المتوقع " . count($expected);
            continue;
        }
        foreach ($expected as $i => $e) {
            $g = $got[$i];
            if ($e[0] !== $g[0] || $e[1] !== $g[1] || $e[2] !== $g[2] || $e[3] !== $g[3]) {
                /* الوجهةُ جزءٌ من المقارنةِ فلتكن جزءًا من البلاغ: بلاغٌ يعرض
                   الحقولَ الثلاثةَ المتطابقةَ ويُخفي الرابعَ المختلفَ يقول
                   «مختلفان» ويُري متطابقين — فيُضيَّع وقتُ من يقرأه. */
                $fails['موضع'][] = "«{$dept['name']}» دور $role صف " . ($i + 1)
                    . ": متوقع [م{$e[0]}·{$e[1]}·{$e[2]}·{$e[3]}]"
                    . " ووجد [م{$g[0]}·{$g[1]}·{$g[2]}·{$g[3]}]";
            }
        }
    }
}

/* ── ② سلامةُ الوجهة ──────────────────────────────────────────────────── */
$r = mysqli_query($conn,
    "SELECT DISTINCT ni.route FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
     WHERE ni.active = 1 AND lg.group_code LIKE 'n9s%'");
while ($x = mysqli_fetch_row($r)) {
    $route = preg_replace('/[#?].*$/', '', $x[0]);
    if (strpos($x[0], 'main/soon.php?screen=') === 0) {
        $cf = substr($x[0], strlen('main/soon.php?screen='));
        $cf = preg_replace('/#.*/', '', $cf);
        if (!isset($map[$cf])) { $fails['وجهة'][] = "«قريبًا» لقانونيٍّ خارج القاموس: $cf"; }
        continue;
    }
    if (!is_file($ROOT . '/' . $route)) { $fails['وجهة'][] = "مسارٌ لا ملفَ له: {$x[0]}"; }
}

/* ── ③ الصلاحيات: can_view=0 صريحةٌ على وحدة الوجهة لهذا الدور ─────────── */
$mods = array(); // path → module_id (أدنى id — عرف الحارس)
$r = mysqli_query($conn, "SELECT MIN(id) id, TRIM(LEADING '/' FROM code) p FROM modules WHERE code IS NOT NULL AND code <> '' GROUP BY p");
while ($x = mysqli_fetch_assoc($r)) { $mods[strtolower($x['p'])] = intval($x['id']); }
$perm = array(); // "role|module" → can_view
$r = mysqli_query($conn, "SELECT role_id, module_id, can_view FROM role_permissions");
while ($x = mysqli_fetch_assoc($r)) { $perm[$x['role_id'] . '|' . $x['module_id']] = intval($x['can_view']); }
$unguarded = 0;
$r = mysqli_query($conn,
    "SELECT ni.role_id, ni.route, ni.label_ar FROM nav_items ni
     JOIN link_groups lg ON lg.id = ni.group_id
     WHERE ni.active = 1 AND lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'
       AND ni.route NOT LIKE 'main/soon.php%'");
while ($x = mysqli_fetch_assoc($r)) {
    $p = strtolower(preg_replace('/[#?].*$/', '', $x['route']));
    if (!isset($mods[$p])) { $unguarded++; continue; }        // شاشةٌ بلا وحدة صلاحيات — الحارس يتركها مفتوحة
    $k = $x['role_id'] . '|' . $mods[$p];
    if (isset($perm[$k]) && $perm[$k] === 0) {
        $fails['صلاحية'][] = "دور {$x['role_id']}: «{$x['label_ar']}» ({$x['route']}) — can_view=0 صريحة";
    }
}

/* ── ④ المجالُ المقيَّد: صفوفُ Financing الخام لا تتجاوز إعلانَ الوثيقة —
 *     والتصييرُ الفعليُّ محجوبٌ بلا منحةٍ (بوابةُ البادئة في unified_nav،
 *     وتُقاس حيًّا بجلب سايدبار حسابٍ بلا منحة) ─────────────────────────── */
$finDeclared = array(); // role → [route => 1] مما أعلنته أوراقُ الإدارات
foreach ($doc['depts'] as $dept) {
    if (!isset($DEPT_ROLES[$dept['name']])) { continue; }
    foreach ($dept['rows'] as $row) {
        if ($row['kind'] !== 'screen') { continue; }
        $rt = preg_replace('/#.*/', '', $routeOf($row['file']));
        if (strpos($rt, 'Financing/') !== 0) { continue; }
        foreach ($DEPT_ROLES[$dept['name']] as $role) { $finDeclared[$role][$rt] = 1; }
    }
}
$r = mysqli_query($conn,
    "SELECT DISTINCT ni.role_id, ni.route FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
     WHERE ni.active = 1 AND lg.group_code LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9s99_others%'
       AND ni.route LIKE 'Financing/%'");
while ($x = mysqli_fetch_assoc($r)) {
    $rt = preg_replace('/#.*/', '', $x['route']);
    if (!isset($finDeclared[intval($x['role_id'])][$rt])) {
        $fails['مقيد'][] = "دور {$x['role_id']}: $rt خارج إعلان الوثيقة";
    }
}

/* ── ⑤ التكرار (UI-DEF-05): لا عنصرين بنفس التسمية والمسار داخل المجموعة
 *     الواحدة، ولا عنصرَ موروثٍ (خارج مجموعات n9) يوازي عنصرًا مولَّدًا لنفس
 *     الدور والمسار. الظهورُ المتعدد عبر مجموعاتٍ مختلفةٍ مقصودٌ بالوثيقة
 *     (عدّاد الظهور 1075 > 206 شاشة) فلا يُحتسب. تصادمُ «رأسٌ يطابق رابطَه
 *     الوحيد» يُكبح في المصيّر (unified_nav) بنيويًّا. ──────────────────── */
$r = mysqli_query($conn,
    "SELECT ni.role_id, ni.group_id, ni.label_ar, SUBSTRING_INDEX(ni.route,'#',1) base, COUNT(*) c
     FROM nav_items ni WHERE ni.active = 1
     GROUP BY ni.role_id, ni.group_id, ni.label_ar, base HAVING c > 1");
while ($x = mysqli_fetch_assoc($r)) {
    $fails['تكرار'][] = "دور {$x['role_id']}: «{$x['label_ar']}» مكررة {$x['c']}× داخل المجموعة {$x['group_id']}";
}
$r = mysqli_query($conn,
    "SELECT ni.role_id, ni.label_ar, SUBSTRING_INDEX(ni.route,'#',1) base,
            SUM(CASE WHEN lg.group_code LIKE 'n9%' THEN 1 ELSE 0 END) n9,
            SUM(CASE WHEN lg.group_code IS NULL OR lg.group_code NOT LIKE 'n9%' THEN 1 ELSE 0 END) legacy
     FROM nav_items ni LEFT JOIN link_groups lg ON lg.id = ni.group_id
     WHERE ni.active = 1
     GROUP BY ni.role_id, ni.label_ar, base HAVING n9 > 0 AND legacy > 0");
while ($x = mysqli_fetch_assoc($r)) {
    $fails['تكرار'][] = "دور {$x['role_id']}: «{$x['label_ar']}» ({$x['base']}) موروثٌ يوازي المولَّد";
}
if (!isset($fails['تكرار'])) { $fails['تكرار'] = array(); }

/* ── التقرير ──────────────────────────────────────────────────────────── */
$total = 0;
foreach ($fails as $axis => $list) {
    $n = count($list); $total += $n;
    echo ($n === 0 ? '✔' : '✘') . " $axis: $n\n";
    foreach (array_slice($list, 0, 12) as $f) { echo "    · $f\n"; }
    if ($n > 12) { echo '    … و' . ($n - 12) . " أخرى\n"; }
}
echo "شاشاتٌ حيةٌ بلا وحدة صلاحيات (يحكمها حارس الشاشة نفسها): $unguarded\n";
echo str_repeat('─', 50) . "\n";
echo $total === 0 ? "الحكم: ✔ المولَّدُ يطابق الوثيقةَ حرفًا — ولا رابطَ في غير موضعه\n"
                  : "الحكم: ✘ $total مخالفة\n";
exit($total === 0 ? 0 : 1);
