<?php
/**
 * tools/nav09_sweep_others.php — كنّاسُ «أخرى» (NAV-09 ⓪-7 · مقترحُ المالك ①)
 * ───────────────────────────────────────────────────────────────────────────
 * «أيُّ صفحةٍ موجودةٍ في النظام وغيرِ موجودةٍ في المستند — ضعها تحت مجموعةٍ
 * باسم أخرى حتى أنظرَ في أمرها.»
 *
 * لكل دورٍ من أدوار الإدارات الخمس عشرة: روابطُه القديمة (خارج المولَّد n9s):
 *   - مسارُها مغطًّى في المولَّد → تُعطَّل (حلّت محلها البنية القانونية)
 *   - غيرُ مغطًّى → تنتقل إلى مجموعة «أخرى — للمراجعة» (مرحلة 99 · مطوية)
 * ويكتب ورقةَ القرار docs/NAV09_OTHERS_DECISION_ar.md صفًّا صفًّا. آمنُ الإعادة.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$ROLES = array(1, 2, 3, 4, 6, 7, 8, 10, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27);
$normalize = function ($route) { return strtolower(preg_replace('/[#?].*$/', '', trim($route))); };

$moved = 0; $retired = 0; $doc = array();
foreach ($ROLES as $role) {
    /* المسارات المغطاة في المولَّد لهذا الدور */
    $covered = array();
    $r = mysqli_query($conn, "SELECT ni.route FROM nav_items ni JOIN link_groups lg ON lg.id = ni.group_id
                              WHERE ni.role_id = $role AND lg.group_code LIKE 'n9s%'");
    while ($x = mysqli_fetch_row($r)) { $covered[$normalize($x[0])] = 1; }
    if (!$covered) { continue; } // دورٌ لم يولَّد له — لا يُمسّ

    /* الروابط القديمة الحية خارج المولَّد */
    $olds = array();
    $r = mysqli_query($conn, "SELECT ni.id, ni.label_ar, ni.route FROM nav_items ni
                              LEFT JOIN link_groups lg ON lg.id = ni.group_id
                              WHERE ni.role_id = $role AND ni.active = 1
                                AND (lg.id IS NULL OR (lg.group_code NOT LIKE 'n9s%' AND lg.group_code NOT LIKE 'n9o%'))");
    while ($x = mysqli_fetch_assoc($r)) { $olds[] = $x; }
    if (!$olds) { continue; }

    /* مجموعة «أخرى» لهذا الدور — مرحلة 99 كي تنزل ذيلًا وتُطوى */
    $gcode = "n9s99_others_r$role";
    $r = mysqli_query($conn, "SELECT id FROM link_groups WHERE group_code = '$gcode'");
    if ($r && ($g = mysqli_fetch_row($r))) { $gid = intval($g[0]); }
    else {
        mysqli_query($conn, "INSERT INTO link_groups (name, group_code, owner_role_id, icon, display_order, stage_no, stage_title, is_active)
            VALUES ('أخرى — للمراجعة', '$gcode', $role, 'fa fa-box-archive', 9900, 99, 'خارج الوثيقة — بانتظار قرار المالك', 1)")
            or die('✘ ' . mysqli_error($conn) . "\n");
        $gid = mysqli_insert_id($conn);
    }

    foreach ($olds as $o) {
        $key = $normalize($o['route']);
        if (isset($covered[$key])) {
            mysqli_query($conn, "UPDATE nav_items SET active = 0 WHERE id = {$o['id']}");
            $retired++;
        } else {
            mysqli_query($conn, "UPDATE nav_items SET group_id = $gid, door = 'DAILY' WHERE id = {$o['id']}");
            $moved++;
            $doc[$role][] = $o;
        }
    }
}
echo "عُطّل (غطاه المولَّد): $retired · نُقل إلى «أخرى»: $moved\n";

/* ورقةُ القرار — من عضوية «أخرى» الحالية كلِّها لا من المنقول في هذا التشغيل وحدَه */
$doc = array();
$r = mysqli_query($conn, "SELECT ni.role_id, ni.label_ar, ni.route FROM nav_items ni
                          JOIN link_groups lg ON lg.id = ni.group_id
                          WHERE lg.group_code LIKE 'n9s99_others%' AND ni.active = 1
                          ORDER BY ni.role_id, ni.label_ar");
while ($x = mysqli_fetch_assoc($r)) { $doc[$x['role_id']][] = $x; $moved = max($moved, 0); }
$totalDoc = 0; foreach ($doc as $rows) { $totalDoc += count($rows); }
$moved = $totalDoc;
$f = fopen(__DIR__ . '/../docs/NAV09_OTHERS_DECISION_ar.md', 'w');
fwrite($f, "# «أخرى — للمراجعة» — ورقةُ قرار المالك\n");
fwrite($f, "**المصدر:** شاشاتٌ حيةٌ في النظام لا تذكرها NAV-09 — نُقلت إلى مجموعةٍ مطويةٍ بذيل قائمة إدارتها (مقترح المالك ①).\n");
fwrite($f, "**القرار لكل صف:** إبقاء (تُنسَّب لمجموعةٍ قانونية) · دمج (في شاشةٍ قانونية بعدّاد تحويل) · تجميد.\n\n");
fwrite($f, "| الدور | الشاشة | المسار | القرار |\n|---|---|---|---|\n");
foreach ($doc as $role => $rows) {
    foreach ($rows as $o) {
        fwrite($f, "| $role | {$o['label_ar']} | `{$o['route']}` | ☐ |\n");
    }
}
fclose($f);
echo "ورقةُ القرار: docs/NAV09_OTHERS_DECISION_ar.md (" . $moved . " صفًّا)\n";
