<?php
/**
 * tools/navr_import_exec.php — استيرادُ مواضعِ القيادةِ التنفيذيّة (CL-NAVR-EX · §١٣)
 * ═══════════════════════════════════════════════════════════════════════════
 * المصدرُ الحاكمُ لمساحتَي `EX-CEO` و`EX-DVP` هو **ملفُّ القيادة**
 * `02 · القيادة.xlsx` (خريطةُ المصادر) — بالمحلِّلِ الواحدِ نفسِه
 * (`rpr02a_read_cards`) مع إعادةِ تعيينِ رمزَي الورقتَين (01⇒EX-CEO · 02⇒EX-DVP)
 * لأنَّ اصطلاحَ الترقيمِ يصطدم برموزِ الإدارات.
 * ◆ `EX-DVP` بلا دورٍ حيٍّ — المواضعُ تُسجَّل والربطُ يُقيَّد Finding (حكم DEP-08).
 * التشغيل: php tools/navr_import_exec.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/navr_bridge.php';
$APPLY = in_array('--apply', $argv, true);
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

$SRC = 'GUIDE-IMPORT·02 القيادة';
$REMAP = array('DEP-01' => 'EX-CEO', 'DEP-02' => 'EX-DVP');
$cards = rpr02a_read_cards($ROOT . '/docs/REPAIR01_20260823/02 · القيادة.xlsx');
$bridge = navr_label_routes($conn);
$reqState = navr_req_state($conn);

$spec = array();
foreach ($cards as $c) {
    if (rpr02a_is_doc($c)) { continue; }
    if (!isset($REMAP[$c['code']])) { continue; }
    $k = $REMAP[$c['code']];
    if (!isset($spec[$k])) { $spec[$k] = array('groups' => array(), 'group_labels' => array(), 'screens' => array()); }
    $gn = navr_gz($c['group']);
    if (!in_array($gn, $spec[$k]['groups'], true)) {
        $spec[$k]['groups'][] = $gn;
        $spec[$k]['group_labels'][$gn] = trim((string) $c['group']);
    }
    $spec[$k]['screens'][] = array('i' => count($spec[$k]['screens']) + 1,
        'group' => $gn, 'name' => rpr02a_nz($c['name']), 'raw' => $c['name'], 'type' => (string) $c['type']);
}

$tot = array('groups' => 0, 'pl' => 0, 'menu' => 0, 'nb' => 0, 'unres' => 0);
echo "══ استيرادُ مواضعِ القيادة " . ($APPLY ? '(كتابة)' : '(قياس)') . " ══\n";
foreach ($spec as $code => $S) {
    printf("  %s: %d مجموعات · %d شاشة\n", $code, count($S['groups']), count($S['screens']));
    $gid = array();
    foreach ($S['groups'] as $i => $g) {
        $sort = $i + 1;
        $gl = isset($S['group_labels'][$g]) ? $S['group_labels'][$g] : $g;
        if ($APPLY) {
            $conn->query("INSERT INTO nav_lifecycle_groups (workspace_id, group_key, label_ar, sort_no, source_ref)
                VALUES ('" . $e($code) . "', '" . $e($g) . "', '" . $e($gl) . "', {$sort}, '{$SRC}')
                ON DUPLICATE KEY UPDATE sort_no = VALUES(sort_no), label_ar = VALUES(label_ar)");
        }
        $r = $conn->query("SELECT id FROM nav_lifecycle_groups WHERE workspace_id='" . $e($code) . "' AND group_key='" . $e($g) . "'");
        $gid[$g] = ($r && $r->num_rows) ? (int) $r->fetch_row()[0] : 0;
        $tot['groups']++;
    }
    $sg = array();
    foreach ($S['screens'] as $sc) {
        list($sid, $route, $how) = navr_resolve_screen($conn, $code, $sc['name'], $bridge);
        $state = isset($reqState[$sc['name']]) ? $reqState[$sc['name']] : '';
        if ($sid !== null && $state === 'NOT_IMPLEMENTED') { $state = ''; }
        $ptype = $sid === null ? 'NOT_BUILT' : 'MENU_ITEM';
        if ($sid === null && $state !== '' && $state !== 'NOT_IMPLEMENTED' && $state !== 'NO_REQ_ROW') { $tot['unres']++; }
        $tot[$ptype === 'MENU_ITEM' ? 'menu' : 'nb']++;
        $g = $sc['group'];
        $sg[$g] = ($sg[$g] ?? 0) + 1;
        if (!$APPLY || empty($gid[$g])) { continue; }
        $tid = 'NT-' . $code . '-' . str_pad((string) $sc['i'], 3, '0', STR_PAD_LEFT);
        $conn->query("INSERT INTO nav_targets
                (target_id, source_doc, sheet_code, row_no, canonical_title, workspace_id, group_key, target_order, visibility_class)
            VALUES ('" . $e($tid) . "', '02 · القيادة.xlsx', '" . $e($code) . "', " . (int) $sc['i'] . ",
                    '" . $e(mb_substr($sc['raw'], 0, 180)) . "', '" . $e($code) . "', '" . $e($g) . "',
                    " . (int) $sc['i'] . ", '" . $e($ptype) . "')
            ON DUPLICATE KEY UPDATE canonical_title = VALUES(canonical_title), visibility_class = VALUES(visibility_class)");
        $tref = $code . '·' . $sc['i'] . '·' . mb_substr($sc['name'], 0, 120);
        $sidSql = $sid !== null ? "'" . $e($sid) . "'" : 'NULL';
        $rtSql  = $route !== null ? "'" . $e($route) . "'" : 'NULL';
        $ok = $conn->query("INSERT INTO nav_placements
                (workspace_id, screen_id, route, target_ref, target_id, group_id, sort_no, placement_type, source_ref)
            VALUES ('" . $e($code) . "', {$sidSql}, {$rtSql}, '" . $e($tref) . "', '" . $e($tid) . "',
                    " . (int) $gid[$g] . ", " . (int) $sg[$g] . ", '" . $e($ptype) . "', '{$SRC}·" . $e($how) . "')
            ON DUPLICATE KEY UPDATE
                target_id = VALUES(target_id),
                screen_id = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(screen_id), screen_id),
                route = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(route), route),
                group_id = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(group_id), group_id),
                sort_no = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(sort_no), sort_no),
                placement_type = IF(source_ref LIKE 'GUIDE-IMPORT%', VALUES(placement_type), placement_type)");
        if ($ok) { $tot['pl']++; }
    }
}
/* EX-DVP بلا دورٍ حيّ — Finding كحكم DEP-08 */
if ($APPLY) {
    $conn->query("INSERT INTO gov_nav_findings (kind, role_id, workspace_id, detail)
        VALUES ('NO_ROLE_BINDING', NULL, 'EX-DVP',
                'مساحةٌ تنفيذيّةٌ بمواضعِ ملفِّ القيادةِ بلا دورِ نوّابٍ حيٍّ — الربطُ متى أُنشئ الدور (حكم §٢٧)')
        ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = NOW()");
}
printf("\n── الحصيلة ──\n  مجموعات %d · مواضع %s%d · MENU %d · NOT_BUILT %d · مبنيٌّ بلا جسر %d\n",
    $tot['groups'], $APPLY ? 'كُتبت ' : 'ستُكتب ', $tot['pl'] ?: ($tot['menu'] + $tot['nb']), $tot['menu'], $tot['nb'], $tot['unres']);
if (!$APPLY) { echo "قياسٌ فقط — أعد بـ--apply.\n"; }
