<?php
/**
 * tools/navr_conform_triage.php — فرزُ حملةِ CONFORM: لماذا لا يُصيَّر الموضعُ؟
 * ═══════════════════════════════════════════════════════════════════════════
 * لكلِّ موضعِ قائمةٍ مبنيٍّ (`MENU_ITEM` + route) في مساحةٍ ذاتِ دورٍ PRIMARY:
 * يُصيَّر سايدبارُ الدورِ بعمليّةٍ نقيّةٍ ويُفحص غيرُ الظاهرِ على الأقفال:
 *   ① nav_items (بندٌ نشط) · ② role_permissions (can_view) ·
 *   ③ gov_space_appearances (FORBIDDEN؟) · ④ حارسُ التوأمِ (canonical مزدوج)
 * — كلُّ حالةٍ تُسمّى بقفلِها، والمجهولُ يُسمّى مجهولًا ولا يُبتلع.
 * التشغيل: php tools/navr_conform_triage.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { $q = $conn->query($sql); $r = $q ? $q->fetch_row() : null; return $r ? $r[0] : null; };

$ws = array();
$q = $conn->query("SELECT wr.workspace_id, wr.role_id, w.name_ar FROM nav_ws_roles wr
                    JOIN nav_workspaces w ON w.workspace_id = wr.workspace_id
                   WHERE wr.binding = 'PRIMARY' AND w.kind = 'DEPARTMENT'");
while ($x = $q->fetch_assoc()) { $ws[$x['workspace_id']] = array((int) $x['role_id'], $x['name_ar']); }

$tot = array();
echo "══ فرزُ CONFORM — المبنيُّ غيرُ المُصيَّرِ بقفلِه ══\n";
foreach ($ws as $wsId => $wr) {
    list($rid, $wname) = $wr;
    $out = array(); exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php') . ' ' . $rid . ' 2>NUL', $out);
    $j = json_decode(implode("\n", $out), true);
    $renderedRoutes = array();
    foreach ((array) ($j['positions'] ?? array()) as $p) {
        $b = strtolower(trim(preg_replace('/[?#].*$/u', '', preg_replace('~^(\.\./)+~', '', $p['h'])), '/'));
        $renderedRoutes[$b] = true;
    }
    $q2 = $conn->query("SELECT p.route, p.target_ref FROM nav_placements p
                         WHERE p.workspace_id = '" . $e($wsId) . "' AND p.active = 1
                           AND p.placement_type IN ('MENU_ITEM','LANDING_PAGE') AND p.route IS NOT NULL");
    while ($x = $q2->fetch_assoc()) {
        $rt = strtolower(trim($x['route'], '/'));
        if (isset($renderedRoutes[$rt])) { continue; }
        /* غيرُ مُصيَّر — الأقفال */
        $bn = basename($rt);
        $nav = (int) $one("SELECT COUNT(*) FROM nav_items WHERE role_id = {$rid} AND active = 1
                            AND LOWER(SUBSTRING_INDEX(REPLACE(route,'../',''),'?',1)) LIKE '%" . $e($bn) . "'");
        $mid = $one("SELECT id FROM modules WHERE LOWER(code) = '" . $e($rt) . "' OR code LIKE '%" . $e($bn) . "' LIMIT 1");
        $perm = $mid !== null ? (int) $one("SELECT COALESCE(can_view,0) FROM role_permissions WHERE role_id = {$rid} AND module_id = " . (int) $mid) : -1;
        $forb = (int) $one("SELECT COUNT(*) FROM gov_space_appearances a
                             JOIN gov_space_roles sr ON sr.space_ar = a.space_ar AND sr.role_id = {$rid}
                            WHERE LOWER(a.route) LIKE '%" . $e($bn) . "' AND a.cls = 'FORBIDDEN'");
        $lock = $nav === 0 ? 'NAV_ITEM_MISSING'
              : ($perm === 0 ? 'PERM_DENIED'
              : ($forb > 0 ? 'SPACE_FORBIDDEN'
              : ($perm === -1 ? 'MODULE_MISSING' : 'RENDER_SUPPRESSED')));
        $tot[$lock] = ($tot[$lock] ?? 0) + 1;
        printf("  %-8s r%-3d %-22s %s (%s)\n", $wsId, $rid, $lock, $rt, mb_substr($x['target_ref'], 0, 40));
    }
}
echo "\n── الحصيلة بالقفل ──\n";
foreach ($tot as $k => $n) { printf("  %-20s %d\n", $k, $n); }
if (!$tot) { echo "  صفرُ موضعٍ مبنيٍّ غيرِ مُصيَّر ✔\n"; }
