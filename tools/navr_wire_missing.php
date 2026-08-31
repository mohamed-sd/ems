<?php
/**
 * tools/navr_wire_missing.php — ربطُ المبنيِّ غيرِ الظاهرِ ببندِ دورِه (NAVR·المطلوب ٧)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الصنفُ المقيس: شاشةٌ مبنيّةٌ بموضعِها في `nav_placements` (MENU_ITEM) —
 *   **ولا صفَّ لها في `nav_items` لدورِ مساحتِها** ⇒ القفلُ ③ من الأربعة
 *   [[screen-grant-four-locks]] — فلا رابطَ ولو صحّت الصلاحيّاتُ كلُّها.
 *   (الأقفالُ ①② فُتحت بالأداةِ المعتمدةِ `repair01_grant_wave_screens --apply`
 *   والقياسُ بعدها: ناقصٌ 0/0 — و④ «الغيابُ ليس منعًا».)
 *
 * ◆ النمطُ نمطُ `ctl_build_register` حرفًا: مجموعةُ `link_groups` باسمِ مجموعةِ
 *   الدليلِ للدورِ (تُوجد أو تُنشأ برأسِ طيٍّ مؤخَّرِ المرحلة) · البابُ الأغلبُ
 *   للدورِ · التسميةُ المعياريّةُ من السجلّ · `permission_code = route` فيحكمه
 *   حارسُ العرض.
 *
 * ◆ ⛔ ودورٌ ممنوعٌ من الشاشةِ صلاحيةً **لا يُربَط** — يُسمّى: الربطُ بندٌ
 *   والصلاحيةُ طبقةٌ أخرى لا تُفتح من هنا.
 *
 * التشغيل: php tools/navr_wire_missing.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$APPLY = in_array('--apply', $argv, true);
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { $q = $conn->query($sql); $r = $q ? $q->fetch_row() : null; return $r ? $r[0] : null; };

echo "══ ربطُ المبنيِّ غيرِ الظاهر " . ($APPLY ? '(كتابة)' : '(قياسٌ فقط)') . " ══\n";

/* المساحاتُ وأدوارُها */
$ws = array();
$q = $conn->query("SELECT wr.workspace_id, wr.role_id FROM nav_ws_roles wr WHERE wr.binding='PRIMARY'");
while ($x = $q->fetch_assoc()) { $ws[$x['workspace_id']] = (int) $x['role_id']; }

$wired = 0; $existed = 0; $noPerm = array(); $failed = array();
foreach ($ws as $wsId => $rid) {
    /* بنودُ الدورِ القائمةُ — بمفتاحِ المسارِ المطبَّع */
    $have = array();
    $q = $conn->query("SELECT route FROM nav_items WHERE role_id = {$rid}");
    while ($x = $q->fetch_assoc()) {
        $k = strtolower(trim(preg_replace('~^(\.\./)+~', '', preg_replace('/[?#].*$/u', '', $x['route'])), '/'));
        $have[$k] = true;
    }
    /* البابُ الأغلبُ للدورِ — لا اختراعَ بابٍ جديد */
    $door = $one("SELECT door FROM nav_items WHERE role_id = {$rid} AND active = 1
                   GROUP BY door ORDER BY COUNT(*) DESC LIMIT 1");
    if ($door === null) { $door = 'DAILY'; }

    $q = $conn->query("SELECT p.route, p.screen_id, p.sort_no, g.label_ar AS grp, g.sort_no AS gsort
                         FROM nav_placements p
                         JOIN nav_lifecycle_groups g ON g.id = p.group_id
                        WHERE p.workspace_id = '" . $e($wsId) . "' AND p.active = 1
                          AND p.placement_type = 'MENU_ITEM' AND p.route IS NOT NULL
                        ORDER BY g.sort_no, p.sort_no");
    while ($x = $q->fetch_assoc()) {
        $k = strtolower(trim($x['route'], '/'));
        if (isset($have[$k])) { $existed++; continue; }

        /* هويّةُ الشاشة: مسارُها الحقيقيُّ بحرفِه واسمُها المعياريُّ ووحدتُها */
        $reg = $conn->query("SELECT route, canonical_label_ar FROM repair01_screen_registry
                              WHERE screen_id = '" . $e($x['screen_id']) . "' LIMIT 1")->fetch_assoc();
        if (!$reg) { $failed[] = "{$wsId}: {$x['route']} — لا صفَّ سجلٍّ لمعرِّفِه"; continue; }
        $routeReal = (string) $reg['route'];
        $label = (string) $reg['canonical_label_ar'];
        $mid = $one("SELECT id FROM modules WHERE code = '" . $e($routeReal) . "' LIMIT 1");
        if ($mid === null) { $failed[] = "{$wsId}: {$routeReal} — لا وحدةَ بكودِه"; continue; }

        /* ⛔ الصلاحيةُ تُقاس ولا تُفتح من هنا */
        $can = $one("SELECT can_view FROM role_permissions WHERE role_id = {$rid} AND module_id = " . (int) $mid);
        if ((int) $can !== 1) { $noPerm[] = "{$wsId}·r{$rid}: {$routeReal}"; continue; }

        if (!$APPLY) { $wired++; continue; }

        /* مجموعةُ الدورِ باسمِ مجموعةِ الدليل — تُوجد أو تُنشأ (نمطُ CTL) */
        $gid = $one("SELECT id FROM link_groups WHERE owner_role_id = {$rid} AND is_active = 1
                      AND name = '" . $e($x['grp']) . "' LIMIT 1");
        if ($gid === null) {
            $conn->query("INSERT INTO link_groups (name, owner_role_id, icon, display_order, is_active, stage_no, stage_title)
                VALUES ('" . $e($x['grp']) . "', {$rid}, 'fa fa-folder', " . (90 + (int) $x['gsort']) . ", 1, 90, '" . $e($x['grp']) . "')");
            $gid = $conn->insert_id;
        }
        $sort = ((int) $x['gsort'] * 1000) + (int) $x['sort_no'];
        $ok = $conn->query("INSERT INTO nav_items
                (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
                VALUES ({$rid}, '" . $e($door) . "', " . (int) $gid . ", " . (int) $mid . ",
                        '" . $e($label) . "', '" . $e($routeReal) . "', 'fa fa-file', {$sort},
                        '" . $e($routeReal) . "', 1)");
        if ($ok) { $wired++; }
        else { $failed[] = "{$wsId}: {$routeReal} — {$conn->error}"; }
    }
}

printf("\n── الحصيلة ──\n  قائمٌ سلفًا: %d · %s: %d\n",
    $existed, $APPLY ? 'رُبط' : 'سيُربَط', $wired);
if ($noPerm) {
    echo "  ⛔ ممنوعٌ صلاحيةً — لا يُربَط ويُسمّى (" . count($noPerm) . "):\n";
    foreach (array_slice($noPerm, 0, 12) as $x) { echo "     · {$x}\n"; }
}
foreach ($failed as $f) { echo "  ✘ {$f}\n"; }
if (!$APPLY) { echo "\nقياسٌ فقط — أعد التشغيل بـ--apply.\n"; }
