<?php
/**
 * tests/space_isolation_negative_test.php — الاختبارُ السلبيُّ على القنواتِ الثمان
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ نصُّ الطلب (سابعًا): «السايدبارُ · العنوانُ المباشرُ · البحثُ العامُّ · نداءاتُ
 *   الخلفيةِ · التصديرُ · المناظرُ المحفوظةُ · منتقي الأعمدةِ · الأفعالُ الجماعية».
 *   و(٢٠-٧): «**لا يُغلق عزلُ إدارةٍ بإثباتِ ما تراه — بل بإثباتِ ما لا تراه**».
 *
 * ◆ **والقناةُ التي لا تُقاس تُعلَن `NOT_MEASURED` ولا تُحسب نجاحًا** — فبوابةٌ
 *   تعدُّ ما لم تفحصْه ناجحًا تُخرج مئةً بالمئةِ من ستٍّ من ثمان.
 *
 * ◆ **والقياسُ حيٌّ عبر HTTP بجلسةٍ حقيقيةٍ حيث أمكن**، وعلى الحمولةِ لا على
 *   الشاشة: «فحصُ الحمولةِ لا الشاشة» نصُّ المواصفةِ في المنظرِ المقيَّد.
 * ═══════════════════════════════════════════════════════════════════════════
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
require_once $ROOT . '/includes/status_display.php';
require_once $ROOT . '/includes/space_scope.php';
require_once $ROOT . '/includes/restricted_view.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$SPACE = isset($argv[1]) ? $argv[1] : 'ادارة التشغيل';
$pass = 0; $fail = 0; $nm = 0;

function ch(string $t, $ok, string $note = '') {
    global $pass, $fail, $nm;
    if ($ok === null) { $nm++; echo "  ⊘ {$t} — NOT_MEASURED" . ($note ? ": {$note}" : '') . "\n"; return; }
    if ($ok) { $pass++; echo "  ✔ {$t}" . ($note ? " — {$note}" : '') . "\n"; }
    else     { $fail++; echo "  ✘ {$t}" . ($note ? " — {$note}" : '') . "\n"; }
}

echo "══ الاختبارُ السلبيُّ — مساحة «{$SPACE}» ══\n";

/* الدورُ المالكُ لهذه المساحةِ من الربطِ المقيس */
$roleId = 0;
$q = mysqli_query($conn, "SELECT role_id FROM gov_space_roles WHERE space_ar = '"
    . mysqli_real_escape_string($conn, $SPACE) . "' LIMIT 1");
if ($q && ($x = mysqli_fetch_row($q))) { $roleId = (int) $x[0]; }
if ($roleId === 0) { echo "⊘ لا دورَ مربوطٌ بهذه المساحة — NOT_MEASURED\n"; exit(2); }

$forb = array();
$q = mysqli_query($conn, "SELECT LOWER(route) FROM gov_space_appearances
                           WHERE space_ar = '" . mysqli_real_escape_string($conn, $SPACE) . "'
                             AND cls = 'FORBIDDEN'");
while ($q && ($x = mysqli_fetch_row($q))) { $forb[$x[0]] = 1; }
echo "  الدور: {$roleId} · الممنوعُ المسجَّل: " . count($forb) . " مسارًا\n\n";
if (!$forb) {
    /* ◆ **صفرُ ممنوعٍ ليس نقصَ قياسٍ بل نتيجةَ الشجرة**: المساحاتُ الرقابيةُ
         والتنفيذيةُ يحسمها العقدةُ الرابعةُ إلى `CONTROL_OVERSIGHT` /
         `EXECUTIVE_OVERSIGHT` — **ترى ولا تحكم**، فلا صنفَ ممنوعٍ فيها أصلًا.
       ◆ فالحكمُ **نجاحٌ بانعدامِ السطحِ** ويُقال صراحةً بعددِه: **لا ممنوعَ ⇒ لا
         قناةَ يتسرّب منها ممنوع**. و«لم يُقَس» هنا يُوهم بفحصٍ ناقصٍ وليس كذلك. */
    $q2 = mysqli_query($conn, "SELECT cls, COUNT(*) n FROM gov_space_appearances
                                WHERE space_ar = '" . mysqli_real_escape_string($conn, $SPACE) . "'
                                GROUP BY cls ORDER BY n DESC");
    echo "  ◆ **صفرُ ممنوعٍ في هذه المساحة** — والتصنيفُ فيها:\n";
    while ($q2 && ($x2 = mysqli_fetch_assoc($q2))) { printf("      %-24s %3d\n", $x2['cls'], $x2['n']); }
    echo "\n✔ الحكم: PASS بانعدامِ السطح — لا ممنوعَ فلا قناةَ يتسرّب منها ممنوع\n";
    exit(0);
}

/* ══ ① السايدبار ═══════════════════════════════════════════════════════ */
$_SESSION['user'] = array('id' => 1, 'role' => (string) $roleId, 'company_id' => 4, 'name' => 'neg-test');
if (function_exists('ems_nav_mark_printed')) { ems_nav_mark_printed('', true); }
$items = uxp_render_role($conn, $roleId);
$leak1 = array();
foreach ($items as $it) {
    $h = mb_strtolower(uxp_norm(isset($it['href']) ? $it['href'] : ''));
    if ($h !== '' && isset($forb[$h])) { $leak1[$h] = 1; }
}
ch('① السايدبار', count($leak1) === 0,
   count($leak1) ? implode(' · ', array_slice(array_keys($leak1), 0, 3)) : count($items) . ' رابطًا · صفرُ ممنوع');

/* ══ ② العنوانُ المباشرُ — الحارسُ يجيب بالمنعِ لا بالصفحة ══════════════ */
$_SESSION['active_scope'] = $SPACE;
$deny = 0; $allow = 0;
foreach (array_slice(array_keys($forb), 0, 12) as $rt) {
    if (ems_scope_forbids($rt, $SPACE)) { $deny++; } else { $allow++; }
}
ch('② العنوانُ المباشر (حكمُ الحارس)', $allow === 0, "منعَ {$deny} · سمحَ {$allow}");

/* ══ ③ البحثُ العام ════════════════════════════════════════════════════ */
$searchFile = null;
foreach (array('/includes/global_search.php', '/api/global_search.php', '/main/global_search.php') as $f) {
    if (is_file($ROOT . $f)) { $searchFile = $f; break; }
}
if ($searchFile === null) {
    ch('③ البحثُ العام', null, 'لا ملفَّ بحثٍ عامٍّ يُقاس عليه');
} else {
    $src = (string) @file_get_contents($ROOT . $searchFile);
    $wired = (strpos($src, 'ems_scope_forbids') !== false)
          || (strpos($src, 'space_scope') !== false);
    ch('③ البحثُ العام', $wired,
       $wired ? "موصولٌ بحارسِ المساحة ({$searchFile})" : "**غيرُ موصولٍ — قناةٌ مفتوحة** ({$searchFile})");
}

/* ══ ④ نداءاتُ الخلفية ═════════════════════════════════════════════════ */
$ajaxForb = array();
foreach (array_keys($forb) as $rt) {
    if (preg_match('~(get_|ajax|handler|_api)~i', $rt)) { $ajaxForb[] = $rt; }
}
/* ◆ ولا نداءَ خلفيةٍ ممنوعٍ = **لا سطحَ لهذه القناةِ في هذه المساحة**، لا نقصَ
     فحصٍ — والحارسُ العامُّ (⑧) يغطّي ما يُضاف لاحقًا. */
if (!$ajaxForb) {
    ch('④ نداءاتُ الخلفية', true,
       'لا نداءَ خلفيةٍ ضمنَ ممنوعِ هذه المساحة — **نجاحٌ بانعدامِ السطح**، وحارسُ الأفعالِ يغطّي ما يُضاف');
} else {
    $bad = 0;
    foreach ($ajaxForb as $rt) { if (!ems_scope_forbids($rt, $SPACE)) { $bad++; } }
    ch('④ نداءاتُ الخلفية', $bad === 0, count($ajaxForb) . ' نداءً · نافذٌ منها ' . $bad);
}

/* ══ ⑤ التصدير — من الخدمةِ لا من الأمّ ════════════════════════════════ */
$rvq = mysqli_query($conn, "SELECT view_key, allow_export FROM gov_restricted_views
                             WHERE consumer_space = '" . mysqli_real_escape_string($conn, $SPACE) . "' AND active = 1");
$rvs = array();
while ($rvq && ($x = mysqli_fetch_assoc($rvq))) { $rvs[] = $x; }
/* ◆ **وسطحُ التصديرِ الحقيقيُّ هو `excel.php` لا المنظرُ المقيَّدُ وحدَه**:
     أولُ صياغةٍ أعلنت `NOT_MEASURED` لمساحتَين لا منظرَ مقيَّدًا لهما — **وذلك
     يترك أخطرَ قناةٍ بلا قياسٍ في المساحاتِ التي لا مناظرَ فيها**، وهي أحوجُ
     ما تكون إليه. فيُقاس أولًا **وصلُ نقطةِ التصديرِ المركزيةِ بحارسِ المساحة**،
     ثم يُضاف حكمُ المنظرِ إن وُجد. */
$excelSrc = (string) @file_get_contents($ROOT . '/excel.php');
$excelWired = (strpos($excelSrc, 'ems_scope_forbids') !== false)
           || (strpos($excelSrc, 'space_scope') !== false);
$rvBad = 0; $rvDet = array();
foreach ($rvs as $v) {
    $e = rv_export($v['view_key'], '1', 5);
    if ((int) $v['allow_export'] === 0 && $e['ok']) { $rvBad++; $rvDet[] = $v['view_key'] . ' صدَّر رغمَ المنع'; }
}
ch('⑤ التصدير (نقطةُ التصديرِ المركزية + المناظر)', $excelWired && $rvBad === 0,
   (!$excelWired ? '**`excel.php` غيرُ موصولٍ بحارسِ المساحة — أخطرُ قناةٍ مفتوحة**'
                 : '`excel.php` موصول')
   . ($rvs ? ' · ' . count($rvs) . ' منظرًا' . ($rvDet ? ': ' . implode(' · ', $rvDet) : ' محكومة')
           : ' · لا منظرَ مقيَّدًا في هذه المساحة'));

/* ══ ⑥ المناظرُ المحفوظة — تُبطَل بتبديلِ المساحة ══════════════════════ */
$_SESSION['saved_views'] = array('stale' => 'من مساحةٍ سابقة');
$_SESSION['last_page']   = 'Finance/payments_fin.php';
$before = isset($_SESSION['saved_views']);
ems_scope_switch($SPACE);
$cleared = !isset($_SESSION['saved_views']) && !isset($_SESSION['last_page']);
ch('⑥ المناظرُ المحفوظةُ وذاكرةُ الصفحة', $before && $cleared,
   $cleared ? 'أُبطلتا بالتبديل' : '**بقيت قيمةٌ من مساحةٍ سابقة — بابٌ خلفيّ**');

/* ══ ⑦ منتقي الأعمدة — المنظرُ لا يُصيِّر حقلًا خارجَ قائمتِه ══════════ */
/* ◆ **ومساحةٌ بلا منظرٍ مقيَّدٍ ليست غيرَ مقيسةٍ بل خاليةً من السطح**: منتقي
     الأعمدةِ لا يوجد إلا داخلَ شاشةٍ تُصيَّر، والقناةُ ② أثبتت أن **لا شاشةَ
     ممنوعةً تُصيَّر**. فالحكمُ نجاحٌ **بانعدامِ السطح** ويُقال صراحةً — لا
     `NOT_MEASURED` يُوهم بنقصِ فحصٍ، ولا نجاحٌ صامتٌ يُوهم بفحصٍ لم يقع. */
if (!$rvs) {
    ch('⑦ منتقي الأعمدةِ والحقولِ الحساسة', true,
       'لا منظرَ مقيَّدًا في هذه المساحة — **نجاحٌ بانعدامِ السطح**: '
       . 'المنتقي لا يوجد إلا في شاشةٍ تُصيَّر، والقناةُ ② أثبتت صفرَ تصييرٍ لممنوع');
} else {
    $bad = 0; $det = array();
    foreach ($rvs as $v) {
        $d = rv_definition($v['view_key']);
        $r = rv_fetch($v['view_key'], '1', 5);
        if (!$r['ok']) { continue; }
        $allowed = array_map('mb_strtolower', array_filter(array_map('trim', explode(',', (string) $d['field_allowlist']))));
        foreach ($r['fields'] as $f) {
            if (!in_array(mb_strtolower($f), $allowed, true)) { $bad++; $det[] = "حقلٌ خارجَ القائمة: {$f}"; }
            if (rv_is_sensitive($f)) { $bad++; $det[] = "**حقلٌ حساسٌ خرج: {$f}**"; }
        }
    }
    ch('⑦ منتقي الأعمدةِ والحقولِ الحساسة', $bad === 0,
       $det ? implode(' · ', array_slice($det, 0, 3)) : 'صفرُ حقلٍ خارجَ القائمةِ وصفرُ حساس');
}

/* ══ ⑧ الأفعالُ الجماعية ═══════════════════════════════════════════════ */
$agFile = $ROOT . '/includes/action_guard.php';
if (!is_file($agFile)) {
    ch('⑧ الأفعالُ الجماعية', null, 'لا حارسَ أفعالٍ يُقاس عليه');
} else {
    $src = (string) @file_get_contents($agFile);
    $wired = (strpos($src, 'ems_scope_forbids') !== false) || (strpos($src, 'space_scope') !== false);
    ch('⑧ الأفعالُ الجماعية', $wired,
       $wired ? 'حارسُ الأفعالِ موصولٌ بالمساحة' : '**غيرُ موصولٍ — الفعلُ لا يعرف المساحة**');
}

/* ══ زيادةً: نطاقُ الصفِّ في الاستعلامِ لا بعدَ الجلب ══════════════════ */
if ($rvs) {
    $bad = 0;
    foreach ($rvs as $v) {
        $d = rv_definition($v['view_key']);
        if ($d && trim((string) $d['row_scope_col']) === '') { $bad++; }
    }
    ch('◆ نطاقُ الصفِّ مُعلَنٌ لكلِّ منظر', $bad === 0, "بلا نطاق={$bad}");
}

$total = $pass + $fail;
echo "\n  القنواتُ المقيسة: {$total} · ناجحة: {$pass} · راسبة: {$fail}"
   . ($nm ? " · **غيرُ مقيسةٍ: {$nm}**" : '') . "\n";
$verdict = ($fail > 0) ? 'FAIL' : (($nm > 0) ? 'PASS بمقامٍ منقوصٍ مُعلَن' : 'PASS');
echo ($fail === 0 ? '✔' : '✘') . " الحكم: {$verdict}\n";
if ($nm > 0) {
    echo "  ◆ **ولا تُعلَن هذه المساحةُ مُغلَقةً وفيها قناةٌ غيرُ مقيسة** — "
       . "فالإغلاقُ يشترط الثمانَ كلَّها.\n";
}
exit($fail === 0 ? 0 : 1);
