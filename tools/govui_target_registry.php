<?php
/**
 * tools/govui_target_registry.php — `Target Registry` بأعمدةِ §4 التسعةَ عشرَ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **من الملفَّين لا من السايدبار** (§1 · §4): كلُّ عمودٍ له **مصدرُه المسمّى**،
 *   وما لا يُصرِّح به الملفُّ يُملأ بحكمٍ مكتوبٍ أو يُترك مُعلَنَ الفراغ —
 *   ⛔ ولا خانةَ تُملأ بالاجتهادِ الصامت.
 *
 * ◆ **وتصنيفُ الأسطحِ السبعةُ (§8)** يُشتقُّ بترتيبِ أسبقيّةٍ معلَن:
 *   `NOT_BUILT` ⇐ لا مسارَ في موضعِه ·
 *   `LANDING_PAGE` ⇐ مجموعتُه في الدليلِ **«اللوحة — خارج الدورة»** حرفًا،
 *      والدليلُ نفسُه يقول «خارج الدورة» فهي صفحةُ هبوطِ المساحةِ لا مرحلةً فيها ·
 *   `TAB_CHILD` ⇐ نوعُ البطاقةِ يبدأ بـ`Child` أو موضعُه تبويب ·
 *   `PROJECTION` ⇐ موضعُه إسقاطٌ (§13) ·
 *   `UTILITY` ⇐ نوعُ البطاقةِ «مصالحة تاريخية» — أداةُ تسويةٍ لا شاشةَ دورة ·
 *   `DIRECT_ONLY` ⇐ موضعُه مباشرٌ بحكمٍ مكتوب · وما بقي `MENU_ITEM`.
 *
 * ◆ **و`Required_Roles` من جسرِ المساحةِ⇒الدور** (`nav_ws_roles`) — ومساحةٌ بلا
 *   دورٍ حيٍّ تُكتب `BLOCKED_ROLE_BINDING` **باسمِ حاجزِها** لا فارغةً (§19).
 * ◆ **و`State_Model_Ref` من دفترِ المتطلّباتِ** (`sm_model_ref` بشاهدِه) عبرَ
 *   كونِ الأهدافِ — وغيابُه يُكتب `—` ولا يُخترع.
 *
 * المخرَج: جدولُ `govui_target_registry` + `docs/…/GOVUI_TARGET_REGISTRY.md`
 * التشغيل: php tools/govui_target_registry.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/govui_lib.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);

/* ◆ الجدولُ يُنشأ في هجرتِه (2028_03_05) — و`ems_app` بلا `CREATE` بقرارِ فصلِ
   المسارَين (ADR-04)، فالأداةُ تملأ ولا تُنشئ. */

/* ── المصادر ─────────────────────────────────────────────────────────────── */
$cards = govui_target_cards($ROOT);
$nt = array();
$r = $conn->query("SELECT target_id, workspace_id, target_order FROM nav_targets");
while ($x = $r->fetch_assoc()) { $nt[$x['workspace_id'] . '#' . (int) $x['target_order']] = $x['target_id']; }
$ws = array();
$r = $conn->query("SELECT workspace_id, kind, dept_code FROM nav_workspaces");
while ($x = $r->fetch_assoc()) { $ws[$x['workspace_id']] = $x; }
$plc = array();
$r = $conn->query("SELECT p.target_id, p.screen_id, p.route, p.placement_type, p.group_id,
                          g.sort_no AS gsort FROM nav_placements p
                     LEFT JOIN nav_lifecycle_groups g ON g.id = p.group_id");
while ($x = $r->fetch_assoc()) { if ($x['target_id']) { $plc[$x['target_id']] = $x; } }
$roles = array();
$r = $conn->query("SELECT w.workspace_id, r.name, w.binding FROM nav_ws_roles w JOIN roles r ON r.id = w.role_id");
while ($x = $r->fetch_assoc()) { $roles[$x['workspace_id']][] = $x['name'] . ($x['binding'] === 'PRIMARY' ? '' : ' (ثانويّ)'); }
/* آلةُ الحالةِ من دفترِ المتطلّباتِ عبرَ كونِ الأهداف — بشاهدِها لا بتخمين */
$sm = array();
$r = $conn->query("SELECT u.screen_id, q.sm_model_ref
                     FROM repair01_target_universe u
                     JOIN repair01_requirements q ON q.requirement_id = u.requirement_id
                    WHERE u.screen_id <> '' AND q.sm_model_ref IS NOT NULL AND q.sm_model_ref <> ''");
while ($x = $r->fetch_assoc()) { $sm[$x['screen_id']] = $x['sm_model_ref']; }
$snap = 'BL-' . date('Ymd') . '-' . trim((string) shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD'));

/* ── الحكمُ صفًّا صفًّا ────────────────────────────────────────────────────── */
$LANDING = 'اللوحه — خارج الدوره';                  /* مطبَّعًا بـgovui_gz */
$ins = $conn->prepare("REPLACE INTO govui_target_registry
  (target_id, workspace_id, workspace_type, department_id, group_id, canonical_group_label,
   group_order, target_screen_id, canonical_screen_label, screen_order, surface_type, surface_rule,
   grain, owner, source_of_truth, required_roles, state_model_ref, source_file, source_sheet,
   source_row, built_route, built_at_snapshot)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
if (!$ins) { exit("⛔ prepare: {$conn->error}\n"); }

$n = 0; $byType = array(); $rowsOut = array();
foreach ($cards as $wsid => $list) {
    $srcFile = (strpos($wsid, 'EX-') === 0) ? '02 · القيادة.xlsx' : '01 · الدليل المعماري.xlsx';
    foreach ($list as $c) {
        $tid = isset($nt[$wsid . '#' . $c['order']]) ? $nt[$wsid . '#' . $c['order']] : '';
        if ($tid === '') { continue; }
        $p = isset($plc[$tid]) ? $plc[$tid] : null;
        $route = $p ? (string) $p['route'] : '';
        $ptype = $p ? (string) $p['placement_type'] : 'NOT_BUILT';
        $sid = $p ? (string) $p['screen_id'] : '';

        /* ── تصنيفُ §8 بأسبقيّةٍ معلَنة ── */
        if ($route === '' || $ptype === 'NOT_BUILT') {
            $st = 'NOT_BUILT'; $rule = 'لا مسارَ مبنيًّا في موضعِ الهدف';
        } elseif ($c['group'] === $LANDING) {
            $st = 'LANDING_PAGE'; $rule = 'مجموعةُ الدليلِ «اللوحة — خارج الدورة» — صفحةُ هبوطِ المساحةِ لا مرحلةٌ في دورتِها';
        } elseif (stripos($c['type'], 'Child') === 0 || $ptype === 'TAB_CHILD') {
            $st = 'TAB_CHILD'; $rule = 'نوعُ البطاقةِ «' . $c['type'] . '» أو موضعُه تبويبٌ في أبيه';
        } elseif ($ptype === 'PROJECTION') {
            $st = 'PROJECTION'; $rule = '§13: إسقاطٌ يقرأ من الإدارةِ المالكةِ ولا يُنشئ مصدرَ حقيقةٍ موازيًا';
        } elseif (stripos($c['type'], 'مصالحة تاريخية') !== false) {
            $st = 'UTILITY'; $rule = 'نوعُ البطاقةِ «مصالحة تاريخية» — أداةُ تسويةٍ لا شاشةَ دورة';
        } elseif ($ptype === 'DIRECT_ONLY') {
            $st = 'DIRECT_ONLY'; $rule = 'موضعٌ مباشرٌ بحكمٍ مكتوبٍ في govui_wiring_log';
        } else {
            $st = 'MENU_ITEM'; $rule = 'مرحلةٌ في دورةِ المساحةِ ببندِ قائمة';
        }

        $wsRow = isset($ws[$wsid]) ? $ws[$wsid] : array('kind' => '', 'dept_code' => '');
        $req = isset($roles[$wsid]) ? implode(' · ', $roles[$wsid]) : 'BLOCKED_ROLE_BINDING — لا دورَ حيًّا مربوطًا بهذه المساحة';
        $smRef = ($sid !== '' && isset($sm[$sid])) ? $sm[$sid] : '—';
        $gid = $p ? $p['group_id'] : null;
        $gord = $p ? $p['gsort'] : null;
        $dept = (string) ($wsRow['dept_code'] === null ? '' : $wsRow['dept_code']);
        $sot = mb_substr((string) $c['sot'], 0, 1000);
        $grain = mb_substr((string) $c['grain'], 0, 400);

        $ins->bind_param('ssssisisssssssssssisss',
            $tid, $wsid, $wsRow['kind'], $dept, $gid, $c['group_raw'], $gord, $sid,
            $c['name_raw'], $c['order'], $st, $rule, $grain, $c['owner'], $sot, $req, $smRef,
            $srcFile, $c['sheet'], $c['row'], $route, $snap);
        if (!$ins->execute()) { exit("⛔ {$tid}: {$conn->error}\n"); }
        $byType[$st] = (isset($byType[$st]) ? $byType[$st] : 0) + 1;
        $rowsOut[] = array($tid, $wsid, $st, $c['name_raw'], $c['group_raw'], $route);
        $n++;
    }
}
printf("سجلُّ الأهداف: %d صفًّا بتسعةَ عشرَ عمودًا · اللقطة %s\n", $n, $snap);
ksort($byType);
foreach ($byType as $k => $v) { printf("  %-14s %d\n", $k, $v); }

if ($MD) {
    $md = "# `GOVUI_TARGET_REGISTRY` — سجلُّ الأهدافِ بأعمدةِ §4 التسعةَ عشرَ\n\n"
        . "> مولَّدٌ حيًّا من `01 · الدليل المعماري` و`02 · القيادة` بـ`tools/govui_target_registry.php` · اللقطة **{$snap}**\n"
        . "> ⛔ **ولا خانةَ بلا مصدرٍ**: كلُّ صفٍّ يحمل ملفَّه وورقتَه وصفَّه، وكلُّ صنفٍ يحمل قاعدتَه.\n\n"
        . "## تصنيفُ الأسطحِ السبعةُ (§8)\n\n| الصنف | العدد |\n|---|---|\n";
    foreach ($byType as $k => $v) { $md .= "| `{$k}` | **{$v}** |\n"; }
    $md .= "| **المجموع** | **{$n}** |\n\n## الصفوف\n\n"
         . "| `Target_ID` | `Workspace` | `Surface_Type` | `Canonical_Screen_Label` | `Canonical_Group_Label` | المسارُ المبنيّ |\n|---|---|---|---|---|---|\n";
    foreach ($rowsOut as $x) {
        $md .= "| `{$x[0]}` | {$x[1]} | `{$x[2]}` | {$x[3]} | {$x[4]} | " . ($x[5] === '' ? '—' : "`{$x[5]}`") . " |\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_TARGET_REGISTRY.md', $md);
    echo "⇐ docs/REPAIR01_20260823/GOVUI_TARGET_REGISTRY.md\n";
}
