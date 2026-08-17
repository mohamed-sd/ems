<?php
/**
 * tools/uxui_group_isolation.php — عزلُ المجموعاتِ: المحورُ الذي لم يُقَس
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العلّةُ التي يكشفها** — جولةُ عزلِ الإداراتِ صنّفت **٨٨٧ زوجًا من
 *   (مساحة × مسار)** وأزالت الممنوعَ منها. و**المجموعةُ لم تُصنَّف قطُّ**:
 *   `gov_space_appearances` تحمل `tab_ar` **وصفًا** للتبويبِ القبليِّ (١٢٧ قيمة)
 *   ولا تحمل له `cls` ولا `ownership` ولا `decision`. فالعزلُ وقع على الرابطِ
 *   وحدَه، **ورأسُ المجموعةِ نجا**.
 *
 * ◆ **وU5 لا تكشفه**: هي ترسِّب المجموعةَ التي تُصيَّر **بصفرِ عناصر**. أمّا
 *   مجموعةٌ نزلت من أربعةِ روابطَ إلى واحدٍ فتمرُّ خضراء — والمستخدمُ يرى
 *   تبويبَ دورةِ إدارةٍ أخرى يحمل بندًا يتيمًا.
 *
 * ◆ **ثلاثةُ مقاماتٍ لا مقامٌ واحد** — ولا تُخلط:
 *   ① المجموعةُ الأجنبيةُ في **مساحةِ إدارة**: صفرُ `OWNED` وصفرُ
 *      `DEPT_SELF_VIEW` وصفرُ `PERSONAL_SPACE` — أي لا شيءَ فيها لهذه الإدارة.
 *      (والمساحةُ الرقابيةُ والتنفيذيةُ تُستثنى: لا تملك بحكمِ طبيعتِها.)
 *   ② المجموعةُ **المبتورة**: أُزيل منها ممنوعٌ وبقيَ رأسُها مُصيَّرًا.
 *   ③ المجموعةُ التي **اختفت** بإزالةِ آخرِ بندٍ فيها — وهي السلوكُ الصحيح.
 *
 * التشغيل: php tools/uxui_group_isolation.php [--all]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF); mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME']; $_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php'; require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php'; require_once $ROOT . '/includes/status_display.php';
while (ob_get_level() > 0) { ob_end_clean(); }
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$ALL = in_array('--all', $argv, true);

$sp = array();
$r = mysqli_query($conn, "SELECT space_ar, role_id FROM gov_space_roles ORDER BY space_ar");
while ($x = mysqli_fetch_assoc($r)) { $sp[$x['space_ar']] = (int) $x['role_id']; }
$kind = array();
$r = mysqli_query($conn, "SELECT DISTINCT space_ar, space_kind FROM gov_space_appearances");
while ($x = mysqli_fetch_assoc($r)) { $kind[$x['space_ar']] = $x['space_kind']; }
$cls = array(); $forb = array();
$r = mysqli_query($conn, "SELECT space_ar, route, cls FROM gov_space_appearances");
while ($x = mysqli_fetch_assoc($r)) {
    $lc = mb_strtolower($x['route']);
    $cls[$x['space_ar']][$lc] = $x['cls'];
    if ($x['cls'] === 'FORBIDDEN') { $forb[$x['space_ar']][$lc] = 1; }
}
$grp = array();
$r = mysqli_query($conn, "SELECT route, group_name FROM nav_canonical");
while ($x = mysqli_fetch_assoc($r)) { $grp[mb_strtolower($x['route'])] = $x['group_name']; }

function ugi_norm($h) { $h = preg_replace('~^(\.\./)+~', '', trim($h)); $h = preg_replace('~[#?].*$~', '', $h); return mb_strtolower($h); }

$deptG = 0; $foreign = array(); $gutted = array(); $vanished = 0; $touched = 0;
foreach ($sp as $space => $rid) {
    $k = isset($kind[$space]) ? $kind[$space] : '—';
    $now = array();
    foreach (uxp_render_role($conn, $rid) as $p) {
        $rt = ugi_norm($p['href']);
        $now[$p['group']][] = isset($cls[$space][$rt]) ? $cls[$space][$rt] : 'NO_ROW';
    }
    $hadForb = array();
    if (isset($forb[$space])) {
        foreach (array_keys($forb[$space]) as $rt) {
            $g = isset($grp[$rt]) ? $grp[$rt] : '(بلا مجموعة)';
            $hadForb[$g] = (isset($hadForb[$g]) ? $hadForb[$g] : 0) + 1;
        }
    }
    foreach ($hadForb as $g => $removed) {
        $touched++;
        if (isset($now[$g])) { $gutted[] = array($space, $g, $removed, count($now[$g])); } else { $vanished++; }
    }
    if ($k !== 'DEPARTMENT') { continue; }
    foreach ($now as $g => $cs) {
        $deptG++;
        $own = 0;
        foreach ($cs as $c) { if ($c === 'OWNED' || $c === 'DEPT_SELF_VIEW' || $c === 'PERSONAL_SPACE') { $own++; } }
        if ($own === 0) {
            $cnt = array();
            foreach ($cs as $c) { $cnt[$c] = (isset($cnt[$c]) ? $cnt[$c] : 0) + 1; }
            arsort($cnt);
            $parts = array(); foreach ($cnt as $kk => $vv) { $parts[] = "{$kk}:{$vv}"; }
            $foreign[] = array($space, $g, count($cs), implode(' · ', $parts));
        }
    }
}
$lim = $ALL ? 9999 : 15;
echo "════ عزلُ المجموعات — المحورُ غيرُ المصنَّف ════\n\n";

/* ═══ ⓪ الجوابُ المباشر: أتظهر لكلِّ إدارةٍ مجموعتُها وحدَها؟ ═══════════════
 * ◆ ثلاثةُ أحوالٍ لا حالان: **خالصةٌ** (كلُّ بنودِها للإدارةِ أو شخصية) ·
 *   **مختلطةٌ** (فيها بندٌ لها وبندٌ لغيرِها) · **أجنبيةٌ** (صفرُ بندٍ لها).
 *   وحصرُها في «أجنبية/غيرِ أجنبية» يُخفي المختلطةَ — وهي أخبثُ الحالَين:
 *   المجموعةُ تبدو مشروعةً بعنوانِها وتحمل ما ليس لصاحبِها. */
$PURE = array('OWNED' => 1, 'DEPT_SELF_VIEW' => 1, 'PERSONAL_SPACE' => 1);
$P = array(0, 0, 0, 0);
$lines = array();
foreach ($sp as $space => $rid) {
    if ((isset($kind[$space]) ? $kind[$space] : '') !== 'DEPARTMENT') { continue; }
    $g = array();
    foreach (uxp_render_role($conn, $rid) as $p) {
        $rt = ugi_norm($p['href']);
        $g[$p['group']][] = isset($cls[$space][$rt]) ? $cls[$space][$rt] : 'NO_ROW';
    }
    $pure = 0; $mix = 0; $frn = 0;
    foreach ($g as $cs) {
        $o = 0; $f = 0;
        foreach ($cs as $c) { if (isset($PURE[$c])) { $o++; } else { $f++; } }
        if ($f === 0) { $pure++; } elseif ($o === 0) { $frn++; } else { $mix++; }
    }
    $tot = count($g);
    $P[0] += $tot; $P[1] += $pure; $P[2] += $mix; $P[3] += $frn;
    $lines[] = sprintf("   %-24s %6d %7d %8d %8d   %5.1f٪", mb_substr($space, 0, 22), $tot, $pure, $mix, $frn, $tot ? $pure * 100 / $tot : 0);
}
echo "▐ ⓪ أتظهر لكلِّ إدارةٍ مجموعتُها وحدَها؟\n";
printf("   %-24s %6s %7s %8s %8s   %s\n", 'المساحة', 'مجموع', 'خالصة', 'مختلطة', 'أجنبية', 'الخالصة');
echo '   ' . str_repeat('─', 62) . "\n";
foreach ($lines as $l) { echo $l . "\n"; }
echo '   ' . str_repeat('─', 62) . "\n";
printf("   %-24s %6d %7d %8d %8d   %5.1f٪\n", 'الإجمالي', $P[0], $P[1], $P[2], $P[3], $P[0] ? $P[1] * 100 / $P[0] : 0);
echo "   ◆ **الجواب: لا** — " . ($P[2] + $P[3]) . " من {$P[0]} مجموعةً (" . round(($P[2] + $P[3]) * 100 / max(1, $P[0]), 1) . "٪) تحمل ما ليس لإدارتِها.\n\n";

echo "▐ ① المجموعةُ الأجنبيةُ في مساحةِ إدارة\n";
echo "   المقام: {$deptG} مجموعةً مُصيَّرةً في مساحاتِ الإداراتِ الاثنتَي عشرة · **أجنبيةٌ " . count($foreign) . "** ("
   . ($deptG ? round(count($foreign) * 100 / $deptG, 1) : 0) . "٪)\n\n";
usort($foreign, function ($a, $b) { return $b[2] - $a[2]; });
foreach (array_slice($foreign, 0, $lim) as $x) { printf("   %-24s │ %-30s │ %2d │ %s\n", mb_substr($x[0], 0, 22), mb_substr($x[1], 0, 28), $x[2], $x[3]); }
if (count($foreign) > $lim) { echo "   … و" . (count($foreign) - $lim) . " أخرى (--all)\n"; }

echo "\n▐ ② المجموعةُ المبتورة — أُزيلت روابطُها وبقيَ رأسُها\n";
$g2 = count($gutted);
echo "   مجموعاتٌ مسَّها العزل: {$touched} · **مبتورةٌ باقية: {$g2}** · اختفت كاملةً: {$vanished}\n\n";
usort($gutted, function ($a, $b) { return $b[2] - $a[2]; });
foreach (array_slice($gutted, 0, $lim) as $x) { printf("   %-24s │ %-30s │ أُزيل %2d · بقيَ %2d\n", mb_substr($x[0], 0, 22), mb_substr($x[1], 0, 28), $x[2], $x[3]); }
if ($g2 > $lim) { echo "   … و" . ($g2 - $lim) . " أخرى (--all)\n"; }

echo "\n◆ لا يُرسِّب: العلاجُ يقتضي **قرارًا** — انظر التقرير.\n";
echo "  السببُ أن `SHARED_WORK_ITEM` و`CONTEXTUAL_READ_ONLY` (٥١ و٦٥ ظهورًا) نصَّت ف٢٠-١ على\n";
echo "  إخراجِهما من دورةِ الإدارةِ الأجنبية، **وإخراجُهما اليومَ يُفقد المسارَ**: مركزُ العملِ لم\n";
echo "  يُثبَت أنه يُبلغ الستةَ عشرَ مسارًا، والمنظرُ المقيَّدُ `view_fields` فارغٌ في ٦٥ من ٦٥.\n";
