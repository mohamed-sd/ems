<?php
/**
 * tools/injint01/sales_runtime.php — مخرَجُ أسطحِ المبيعاتِ الحيّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ثغرةُ العُدّةِ القائمة**: `engine.php:392` يرصد `Fatal error` و`Parse error`
 *   **وحدَهما**. فصفحةٌ تطبع عشرينَ `Warning: Undefined array key` تمرُّ
 *   «ناجحةً · HTTP 200» — والتحذيرُ عطبٌ منطقيٌّ مطبوعٌ لا زينة.
 *   ⇐ فهذه الأداةُ **تُعيد استعمالَ المحرِّكِ نفسِه** (لا محرِّكًا موازيًا)
 *     وتفحص الجسدَ عن العائلاتِ الأربع.
 *
 * ⛔ **ولا يُقاس التحذيرُ بمطابقةِ لفظٍ في الصفحة**: كلمةُ «تحذير» عربيّةٌ في
 *   واجهاتِ النظامِ كثيرة. فالمقياسُ **بصمةُ PHP الحرفيّة** (`Warning:` متبوعًا
 *   بمسارِ ملفٍّ ورقمِ سطر) لا اللفظ.
 *
 * ⛔ **والصفحةُ التي يردُّها الحارسُ لا تُقاس**: 302 إلى لوحةٍ ليست صفحةَ السطح،
 *   فقياسُ جسدِها قياسُ اللوحةِ لا السطح.
 *
 * التشغيل: php tools/injint01/sales_runtime.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/tests/dept_suite/engine.php';
$MF = require $ROOT . '/tests/dept_suite/manifest_sales.php';

$ctx = ds_ctx(array('base' => 'http://localhost/ems'));
list($ok, $why) = ds_login($ctx, $MF['user'], $MF['pass']);
if (!$ok) { exit("⛔ تعذّر تهيئةُ الجلسةِ عبر محرِّكِ العُدّة: $why\n"); }
printf("✔ الجلسةُ مهيّأةٌ بدور «%s»\n\n", $MF['user']);

/* ═══ العائلاتُ الأربعُ ببصماتِها الحرفيّة ═════════════════════════════════ */
$FAM = array(
    'FATAL'      => '~(Fatal error|Parse error)\s*:\s*(.{0,150})~s',
    'WARNING'    => '~Warning\s*:\s*(.{0,130})~s',
    'NOTICE'     => '~Notice\s*:\s*(.{0,130})~s',
    'DEPRECATED' => '~Deprecated\s*:\s*(.{0,130})~s',
);

$rowsOut = array(); $tot = array_fill_keys(array_keys($FAM), 0);
$screens = isset($MF['screens']) ? $MF['screens'] : array();
$i = 0; $n = count($screens);

foreach ($screens as $def) {
    $route = isset($def['route']) ? (string) $def['route'] : '';
    if ($route === '') { continue; }
    $i++;
    printf("\r  [%2d/%d] %-46s", $i, $n, mb_substr($route, 0, 44));
    list($code, $hdr, $body) = ds_req($ctx['base'] . '/' . ltrim($route, '/'), $ctx);
    $loc = '';
    if (preg_match('~Location:\s*(\S+)~i', (string) $hdr, $lm)) { $loc = $lm[1]; }

    if ($code === 302 || $code === 301) {
        $rowsOut[] = array('route' => $route, 'code' => $code, 'verdict' => 'REDIRECT',
            'detail' => 'رُدَّ إلى ' . basename($loc), 'hits' => array());
        continue;
    }
    if ($code !== 200) {
        $rowsOut[] = array('route' => $route, 'code' => $code, 'verdict' => 'HTTP_' . $code, 'detail' => '', 'hits' => array());
        continue;
    }
    $plain = strip_tags((string) $body);
    $hits = array();
    foreach ($FAM as $fam => $re) {
        if (preg_match_all($re, $plain, $mm)) {
            $cnt = count($mm[0]); $tot[$fam] += $cnt;
            $sample = trim(preg_replace('~\s+~', ' ', $mm[count($mm) - 1][0]));
            $hits[$fam] = array('count' => $cnt, 'sample' => mb_substr($sample, 0, 150));
        }
    }
    $rowsOut[] = array('route' => $route, 'code' => 200,
        'verdict' => $hits ? 'DIRTY' : 'CLEAN',
        'detail' => round(strlen((string) $body) / 1024, 1) . ' ك.ب', 'hits' => $hits);
}
echo "\r" . str_repeat(' ', 70) . "\r";

/* ═══ العرض ══════════════════════════════════════════════════════════════ */
$clean = $dirty = $redir = $bad = 0;
foreach ($rowsOut as $r) {
    if ($r['verdict'] === 'CLEAN') { $clean++; }
    elseif ($r['verdict'] === 'DIRTY') { $dirty++; }
    elseif ($r['verdict'] === 'REDIRECT') { $redir++; }
    else { $bad++; }
}
printf("══ مخرَجُ الأسطحِ الحيّ ══\n  نظيف=%d · بتحذيرات=%d · مردود=%d · خطأ HTTP=%d\n\n", $clean, $dirty, $redir, $bad);

if ($dirty) {
    echo "══ أسطحٌ تطبع أخطاءَ PHP في جسدِها ══\n";
    foreach ($rowsOut as $r) {
        if ($r['verdict'] !== 'DIRTY') { continue; }
        printf("\n  ◆ %s  (%s)\n", $r['route'], $r['detail']);
        foreach ($r['hits'] as $fam => $h) { printf("     %-11s ×%-3d %s\n", $fam, $h['count'], $h['sample']); }
    }
}
if ($redir) {
    echo "\n══ أسطحٌ في مانيفستِ المبيعاتِ يردُّها الحارس ══\n";
    foreach ($rowsOut as $r) { if ($r['verdict'] === 'REDIRECT') { printf("  · %-44s %s\n", $r['route'], $r['detail']); } }
}
if ($bad) {
    echo "\n══ أسطحٌ برمزِ HTTP غيرِ 200 ══\n";
    foreach ($rowsOut as $r) { if (strpos($r['verdict'], 'HTTP_') === 0) { printf("  · %-44s %s\n", $r['route'], $r['verdict']); } }
}

echo "\n══ الحصيلةُ بالعائلة ══\n";
foreach ($tot as $fam => $n2) { printf("  %-11s %d\n", $fam, $n2); }
file_put_contents($ROOT . '/docs/injint01/sales_runtime.json', json_encode($rowsOut, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n  التفصيل: docs/injint01/sales_runtime.json\n";
