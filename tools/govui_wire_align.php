<?php
/**
 * tools/govui_wire_align.php — مطابقةُ أهدافِ المساحةِ بأسطحِها المبنيّةِ **إسنادًا كاملًا**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ الذي يكشفه**: «الانزياح» — سلسلةٌ من الأهدافِ وُصلت كلُّها بالسطحِ
 *   المجاورِ لسطحِها، فيبدو كلُّ صفٍّ «اسمًا خاطئًا» وهو في الحقيقةِ **وصلٌ
 *   خاطئ**. وقياسُ صفٍّ بمفردِه لا يكشفه؛ يكشفه **إسنادٌ كاملٌ** يقارن
 *   مجموعَ القربِ للوصلِ القائمِ بمجموعِ القربِ لأفضلِ إسنادٍ ممكن.
 *
 * ◆ **والقربُ مقيسٌ بجذرِ الكلمةِ المطبَّعِ** (تقاطعُ المفرداتِ ذاتِ الطولِ ≥ 3
 *   بعدَ نزعِ أل التعريفِ) — ⛔ لا معجمَ مرادفاتٍ يخترع تشابهًا.
 *
 * ◆ ⛔ **لا يطبّق شيئًا**: يخرج جدولَ فرزٍ؛ والحسمُ بحكمٍ مكتوبٍ في هجرةٍ لها عكسُها.
 * التشغيل: php tools/govui_wire_align.php [--ws=DEP-14] [--gap=0.15]
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
$only = ''; $GAP = 0.15;
foreach ($argv as $a) {
    if (strpos($a, '--ws=') === 0) { $only = substr($a, 5); }
    if (strpos($a, '--gap=') === 0) { $GAP = (float) substr($a, 6); }
}

/** مفرداتُ اسمٍ — بعدَ التطبيعِ ونزعِ «ال» وإسقاطِ ما دون ثلاثةِ أحرف */
function govui_toks($s)
{
    $s = rpr02a_nz($s);
    $s = preg_replace('~[^\p{Arabic}\p{L}\p{N} ]+~u', ' ', $s);
    $o = array();
    foreach (explode(' ', $s) as $w) {
        $w = preg_replace('~^ال~u', '', trim($w));
        if (mb_strlen($w) >= 3) { $o[$w] = 1; }
    }
    return array_keys($o);
}
function govui_score($a, $b)
{
    $wa = govui_toks($a); $wb = govui_toks($b);
    if (!$wa || !$wb) { return 0.0; }
    return count(array_intersect($wa, $wb)) / max(count($wa), count($wb));
}

$cards = govui_target_cards($ROOT);
$nt = array();
$r = $conn->query("SELECT target_id, workspace_id, target_order FROM nav_targets");
while ($x = $r->fetch_assoc()) { $nt[$x['workspace_id'] . '#' . (int) $x['target_order']] = $x['target_id']; }
$plc = array();
$r = $conn->query("SELECT target_id, route, placement_type, workspace_id FROM nav_placements");
while ($x = $r->fetch_assoc()) { if ($x['target_id']) { $plc[$x['target_id']] = $x; } }
$reg = array();
$r = $conn->query("SELECT LOWER(route) rt, route AS raw, screen_id, canonical_label_ar
                     FROM repair01_screen_registry WHERE route IS NOT NULL AND route <> ''");
while ($x = $r->fetch_assoc()) { $reg[$x['rt']] = $x; }
/* مسارٌ موصولٌ بمساحةٍ أخرى لا يُقترح هنا — فالنقلُ بين المساحاتِ حكمُ مالكٍ لا مطابقةُ اسم */
$ownedElse = array();
foreach ($plc as $tid => $p) { if ($p['route']) { $ownedElse[strtolower($p['route'])] = $p['workspace_id']; } }

$report = array();
foreach ($cards as $ws => $list) {
    if ($only !== '' && $ws !== $only) { continue; }
    /* مجلَّداتُ المساحةِ = مجلَّداتُ ما وُصل بها فعلًا */
    $dirs = array();
    foreach ($list as $c) {
        $tid = isset($nt[$ws . '#' . $c['order']]) ? $nt[$ws . '#' . $c['order']] : '';
        if ($tid && isset($plc[$tid]) && $plc[$tid]['route']) { $dirs[dirname(strtolower($plc[$tid]['route']))] = 1; }
    }
    if (!$dirs) { continue; }
    /* الأسطحُ المرشَّحة: كلُّ مسجَّلٍ في تلك المجلَّداتِ غيرِ مملوكٍ لمساحةٍ أخرى */
    $pool = array();
    foreach ($reg as $rt => $g) {
        if (!isset($dirs[dirname($rt)])) { continue; }
        if (isset($ownedElse[$rt]) && $ownedElse[$rt] !== $ws) { continue; }
        $pool[$rt] = $g;
    }
    /* إسنادٌ جشِعٌ: أعلى قربٍ أوّلًا، وكلُّ سطحٍ لهدفٍ واحدٍ */
    $pairs = array();
    foreach ($list as $c) {
        $tid = isset($nt[$ws . '#' . $c['order']]) ? $nt[$ws . '#' . $c['order']] : '';
        if ($tid === '') { continue; }
        foreach ($pool as $rt => $g) { $pairs[] = array(govui_score($c['name_raw'], $g['canonical_label_ar']), $tid, $rt); }
    }
    usort($pairs, function ($a, $b) { return $b[0] <=> $a[0]; });
    $assignT = array(); $assignR = array();
    foreach ($pairs as $p) {
        if ($p[0] < 0.34) { break; }
        if (isset($assignT[$p[1]]) || isset($assignR[$p[2]])) { continue; }
        $assignT[$p[1]] = array($p[2], $p[0]); $assignR[$p[2]] = $p[1];
    }
    foreach ($list as $c) {
        $tid = isset($nt[$ws . '#' . $c['order']]) ? $nt[$ws . '#' . $c['order']] : '';
        if ($tid === '' || !isset($plc[$tid])) { continue; }
        $curRt = strtolower((string) $plc[$tid]['route']);
        $curLbl = isset($reg[$curRt]) ? $reg[$curRt]['canonical_label_ar'] : '';
        $curS = $curRt === '' ? 0.0 : govui_score($c['name_raw'], $curLbl);
        if (!isset($assignT[$tid])) { continue; }
        list($bestRt, $bestS) = $assignT[$tid];
        if ($bestRt === $curRt) { continue; }
        if ($bestS < $curS + $GAP) { continue; }
        $report[] = array('ws' => $ws, 'tid' => $tid, 'order' => $c['order'],
            'canonical' => $c['name_raw'], 'cur_route' => $plc[$tid]['route'], 'cur_label' => $curLbl,
            'cur_score' => round($curS, 2), 'best_route' => $reg[$bestRt]['raw'],
            'best_label' => $reg[$bestRt]['canonical_label_ar'], 'best_score' => round($bestS, 2),
            'best_taken_by' => isset($ownedElse[$bestRt]) ? $ownedElse[$bestRt] : '');
    }
}
foreach ($report as $o) {
    printf("%-8s %-16s (#%d)\n    حاكم: %s\n    الآن  %.2f  %-44s «%s»\n    أفضل %.2f  %-44s «%s»%s\n\n",
        $o['ws'], $o['tid'], $o['order'], $o['canonical'], $o['cur_score'], $o['cur_route'], $o['cur_label'],
        $o['best_score'], $o['best_route'], $o['best_label'],
        $o['best_taken_by'] !== '' ? '  ⚠ موصولٌ الآن بهدفٍ في ' . $o['best_taken_by'] : '  (غيرُ موصول)');
}
printf("انزياحاتٌ مرشَّحةٌ للحسم: %d\n", count($report));
file_put_contents($ROOT . '/docs/REPAIR01_20260823/GOVUI_WIRE_ALIGN.json',
    json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
