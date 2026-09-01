<?php
/**
 * tools/govui_label_measure.php — قياسُ `LABEL_CONFORMANCE` (§7 · §19)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: `CURRENT_UI_LABEL = EFFECTIVE_CANONICAL_LABEL`.
 *   **والمعروضُ يُقرأ من التصييرِ الحيِّ** (§16 · [[render-not-store-rule]])
 *   — ⛔ لا من `nav_canonical` وحدَه، فمخزنٌ يوافق الحاكمَ ومُصيَّرٌ يخالفه
 *   يعطي أخضرَ كاذبًا.
 *
 * ◆ **وأربعةُ مخازنِ عرضٍ تُقاس معًا** لأنَّ أيًّا منها قد يغلب:
 *   `nav_canonical.canonical_ar` (APPROVED يغلب — `unified_nav.php:1081`) ·
 *   `nav_canonical_current.cur_label` · `nav_items.label_ar` · `modules.name` ·
 *   ويُضاف `repair01_screen_registry.canonical_label_ar` سجلَّ الحقيقةِ للمبنيّ.
 *
 * ◆ **والمقامُ يُعلَن باستبعادِه** (§19): المقيسُ هو الهدفُ **المبنيُّ
 *   المُصيَّرُ** في مساحةٍ لها دورٌ حيّ. وما لا يُصيَّر يُسمّى حاجزُه ولا يُبتلع.
 *
 * المخرَج: docs/REPAIR01_20260823/GOVUI_LABEL_MEASURE.json + ملخّصٌ على الشاشة
 * التشغيل: php tools/govui_label_measure.php [--ws=DEP-10]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/tools/govui_lib.php';
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

$only = '';
foreach ($argv as $a) { if (strpos($a, '--ws=') === 0) { $only = substr($a, 5); } }

/* ═══ ① الحاكمُ — من الملفَّين مباشرةً ═══ */
$cards = govui_target_cards($ROOT);

/* ═══ ② النسبُ — الهدفُ بمعرِّفِه من `nav_targets` بـ(مساحة, ترتيب) ═══ */
$byOrder = array();
$r = $conn->query("SELECT target_id, workspace_id, target_order, canonical_title FROM nav_targets");
while ($x = $r->fetch_assoc()) { $byOrder[$x['workspace_id'] . '#' . (int) $x['target_order']] = $x; }

/* ═══ ③ الموضعُ والمبنيُّ ═══ */
$plc = array();
$r = $conn->query("SELECT p.target_id, p.screen_id, p.route, p.placement_type, p.group_id,
                          p.sort_no, g.label_ar AS glabel, g.sort_no AS gsort
                     FROM nav_placements p JOIN nav_lifecycle_groups g ON g.id = p.group_id");
while ($x = $r->fetch_assoc()) { if ($x['target_id']) { $plc[$x['target_id']] = $x; } }

/* ═══ ④ مخازنُ العرضِ الأربعة ═══ */
$nz = 'rpr02a_nz';
$base = function ($s) { return strtolower(ltrim(preg_replace('~[?#].*$~', '', preg_replace('~^(\.\./)+~', '', trim((string) $s))), '/')); };
$canon = array(); $canonSt = array();
$r = $conn->query("SELECT route, canonical_ar, status FROM nav_canonical");
while ($x = $r->fetch_assoc()) { $canon[$base($x['route'])] = $x['canonical_ar']; $canonSt[$base($x['route'])] = $x['status']; }
$reg = array();
$r = $conn->query("SELECT screen_id, canonical_label_ar FROM repair01_screen_registry");
while ($x = $r->fetch_assoc()) { $reg[$x['screen_id']] = $x['canonical_label_ar']; }
$mods = array();
$r = $conn->query("SELECT code, name FROM modules");
while ($x = $r->fetch_assoc()) { $mods[$base($x['code'])] = $x['name']; }
$navi = array();                                   /* role#route ⇒ label */
$r = $conn->query("SELECT role_id, route, label_ar FROM nav_items WHERE active = 1");
while ($x = $r->fetch_assoc()) { $navi[(int) $x['role_id'] . '#' . $base($x['route'])] = $x['label_ar']; }

/* ═══ ④ب المسارُ المشترَك — مَن يملك بندَ السايدبار؟ (§8 · §9) ═══
   ◆ سطحٌ واحدٌ قد يخدم هدفَين: أبًا في السايدبارِ وابنًا تبويبًا فيه.
     `nav_items` مفتاحُه `(role, route)` فلا يحمل المسارُ اسمَين — فالاسمُ
     المُصيَّرُ اسمُ **مالكِ البند**، وابنُ التبويبِ يُقاس في صفحتِه لا في القائمة.
   ◆ **وهدفانِ `MENU_ITEM` على مسارٍ واحدٍ عطبٌ يُسمّى** لا يُبتلع: بندٌ واحدٌ
     لاسمَين حاكمَين — يُقيَّد `SHARED_ROUTE_MENU_DUP`. */
$r = $conn->query("SELECT target_id, workspace_id FROM nav_targets");
$tws = array(); while ($x = $r->fetch_assoc()) { $tws[$x['target_id']] = $x['workspace_id']; }
$routeGroup = array();
foreach ($plc as $tid => $p) {
    if (!$p['route']) { continue; }
    $w = isset($tws[$tid]) ? $tws[$tid] : '';
    $routeGroup[$w . '|' . $base($p['route'])][] = $tid;
}
$shareRole = array();                               /* target ⇒ OWNER | TAB_ON_PARENT | MENU_DUP */
foreach ($routeGroup as $g => $ids) {
    if (count($ids) === 1) { $shareRole[$ids[0]] = 'OWNER'; continue; }
    $menus = array();
    foreach ($ids as $tid) { if ($plc[$tid]['placement_type'] === 'MENU_ITEM') { $menus[] = $tid; } }
    if (count($menus) > 1) {                        /* بندانِ في القائمةِ لمسارٍ واحدٍ — عطبٌ يُسمّى */
        sort($ids);
        foreach ($ids as $i => $tid) { $shareRole[$tid] = $i === 0 ? 'OWNER' : 'MENU_DUP'; }
    } else {                                        /* أبٌ واحدٌ (أو لا أبَ) وبقيّتُهم تبويباتٌ فيه */
        sort($ids);
        $own = count($menus) === 1 ? $menus[0] : $ids[0];
        foreach ($ids as $tid) { $shareRole[$tid] = ($tid === $own) ? 'OWNER' : 'TAB_ON_PARENT'; }
    }
}

/* ═══ ⑤ الدورُ الحيُّ لكلِّ مساحةٍ — والغيابُ حاجزٌ يُسمّى ═══ */
$wsRole = array();
$r = $conn->query("SELECT workspace_id, role_id FROM nav_ws_roles WHERE binding = 'PRIMARY'");
while ($x = $r->fetch_assoc()) { $wsRole[$x['workspace_id']] = (int) $x['role_id']; }
/* `WS-MY` مساحةٌ شخصيّةٌ تُحقن في كلِّ دور — فتُقاس بدورٍ مسبارٍ مُعلَنٍ لا تُستثنى */
$PROBE = 24;
if (!isset($wsRole['WS-MY'])) { $wsRole['WS-MY'] = $PROBE; }

/* ═══ ⑥ التصييرُ الحيُّ — عمليّةٌ نقيّةٌ لكلِّ دور ═══ */
$rendered = array();                               /* ws ⇒ base ⇒ ['l'=>label,'g'=>head,'s'=>sub] */
foreach ($wsRole as $ws => $rid) {
    if ($only !== '' && $ws !== $only) { continue; }
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($ROOT . '/tools/lib/render_role_cli.php') . ' ' . $rid;
    $json = shell_exec($cmd . ' 2>' . (DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null'));
    $j = json_decode((string) $json, true);
    if (!is_array($j) || !isset($j['positions'])) { fwrite(STDERR, "⛔ تعذّر تصييرُ {$ws} (دور {$rid})\n"); continue; }
    /* ⛔ **والمدخلُ الثاني المقصودُ لا يدهس الأوّل**: `route#2` مدخلٌ ثانٍ باسمِه
       له (`unified_nav.php` — `$isVariant`)، وقاعدتُه هي المسارُ نفسُه. فلو
       كُتب بعدَ الأصلِ لقرأ القياسُ اسمَ المنظرِ بدلَ اسمِ الشاشةِ — أخضرُ
       كاذبٌ معكوس. فالأصلُ (بلا `#` ولا `?`) يغلب، والمنظرُ لا يحلُّ محلَّه. */
    foreach ($j['positions'] as $p) {
        $b = $base($p['h']);
        $isVariant = (strpbrk((string) $p['h'], '#?') !== false);
        if ($isVariant && isset($rendered[$ws][$b])) { continue; }
        $rendered[$ws][$b] = $p;
    }
}

/* ═══ ⑦ الحكمُ صفًّا صفًّا ═══ */
$rows = array(); $stat = array();
foreach ($cards as $ws => $list) {
    if ($only !== '' && $ws !== $only) { continue; }
    foreach ($list as $c) {
        $k = $ws . '#' . $c['order'];
        $t = isset($byOrder[$k]) ? $byOrder[$k] : null;
        $tid = $t ? $t['target_id'] : '';
        $p = ($tid && isset($plc[$tid])) ? $plc[$tid] : null;
        $b = $p && $p['route'] ? $base($p['route']) : '';
        $rn = ($b !== '' && isset($rendered[$ws][$b])) ? $rendered[$ws][$b] : null;

        $row = array(
            'ws' => $ws, 'order' => $c['order'], 'target_id' => $tid,
            'canonical' => $c['name_raw'], 'canonical_norm' => $c['name'],
            'stored_target' => $t ? $t['canonical_title'] : '',
            'screen_id' => $p ? (string) $p['screen_id'] : '',
            'route' => $p ? (string) $p['route'] : '',
            'ptype' => $p ? $p['placement_type'] : '',
            'group_canonical' => $c['group_raw'], 'group_stored' => $p ? $p['glabel'] : '',
            'rendered' => $rn ? $rn['l'] : '',
            'st_canon' => ($b !== '' && isset($canon[$b])) ? $canon[$b] : '',
            'st_canon_status' => ($b !== '' && isset($canonSt[$b])) ? $canonSt[$b] : '',
            'st_reg' => ($p && $p['screen_id'] && isset($reg[$p['screen_id']])) ? $reg[$p['screen_id']] : '',
            'st_navi' => (isset($wsRole[$ws]) && $b !== '' && isset($navi[$wsRole[$ws] . '#' . $b])) ? $navi[$wsRole[$ws] . '#' . $b] : '',
            'st_mod' => ($b !== '' && isset($mods[$b])) ? $mods[$b] : '',
            'share' => ($tid && isset($shareRole[$tid])) ? $shareRole[$tid] : '',
            'verdict' => '',
        );

        if (!$p || $p['placement_type'] === 'NOT_BUILT' || $p['route'] === '' || $p['route'] === null) {
            $row['verdict'] = 'NOT_BUILT';
        } elseif (!isset($wsRole[$ws])) {
            $row['verdict'] = 'NO_LIVE_ROLE';
        } elseif ($row['share'] === 'TAB_ON_PARENT') {
            $row['verdict'] = 'TAB_ON_PARENT';
        } elseif ($row['share'] === 'MENU_DUP') {
            $row['verdict'] = 'SHARED_ROUTE_MENU_DUP';
        } elseif ($p['placement_type'] === 'TAB_CHILD') {
            $row['verdict'] = 'TAB_SURFACE';   /* §8: ابنُ التبويبِ ليس بندَ سايدبارٍ — اسمُه في صفحتِه */
        } elseif ($rn === null) {
            $row['verdict'] = 'NOT_RENDERED';
        } elseif ($nz($rn['l']) === $c['name']) {
            $row['verdict'] = 'EXACT_MATCH';
        } else {
            $row['verdict'] = 'WRONG_LABEL';
        }
        $rows[] = $row;
        $stat[$ws][$row['verdict']] = (isset($stat[$ws][$row['verdict']]) ? $stat[$ws][$row['verdict']] : 0) + 1;
    }
}

/* ═══ ⑧ الإخراج ═══ */
$tot = array();
foreach ($stat as $ws => $m) { foreach ($m as $v => $n) { $tot[$v] = (isset($tot[$v]) ? $tot[$v] : 0) + $n; } }
$num = isset($tot['EXACT_MATCH']) ? $tot['EXACT_MATCH'] : 0;
$den = $num + (isset($tot['WRONG_LABEL']) ? $tot['WRONG_LABEL'] : 0);
$out = array('metric' => 'LABEL_CONFORMANCE', 'numerator' => $num, 'denominator' => $den,
    'excluded' => array('NOT_BUILT' => isset($tot['NOT_BUILT']) ? $tot['NOT_BUILT'] : 0,
        'NOT_RENDERED' => isset($tot['NOT_RENDERED']) ? $tot['NOT_RENDERED'] : 0,
        'NO_LIVE_ROLE' => isset($tot['NO_LIVE_ROLE']) ? $tot['NO_LIVE_ROLE'] : 0,
        'TAB_ON_PARENT' => isset($tot['TAB_ON_PARENT']) ? $tot['TAB_ON_PARENT'] : 0,
        'TAB_SURFACE' => isset($tot['TAB_SURFACE']) ? $tot['TAB_SURFACE'] : 0,
        'SHARED_ROUTE_MENU_DUP' => isset($tot['SHARED_ROUTE_MENU_DUP']) ? $tot['SHARED_ROUTE_MENU_DUP'] : 0),
    'universe' => count($rows), 'by_ws' => $stat, 'rows' => $rows);
$dst = $ROOT . '/docs/REPAIR01_20260823/GOVUI_LABEL_MEASURE.json';
file_put_contents($dst, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

printf("LABEL_CONFORMANCE = %d/%d  (كون %d · غير مبني %d · غير مصير %d · بلا دور حي %d)\n",
    $num, $den, count($rows), $out['excluded']['NOT_BUILT'], $out['excluded']['NOT_RENDERED'], $out['excluded']['NO_LIVE_ROLE']);
foreach ($stat as $ws => $m) {
    ksort($m);
    $line = array(); foreach ($m as $v => $n) { $line[] = "$v=$n"; }
    printf("  %-8s %s\n", $ws, implode(' · ', $line));
}
echo "⇐ $dst\n";
