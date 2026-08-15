<?php
/**
 * tests/nav_duplicate_links_test.php — شاهدُ «لا مسارَ يتكرَّر في قائمةِ دور»
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0414 · INJ-0489 · INJ-0540 · INJ-0448 · INJ-0222
 *                  INJ-0562 · INJ-0127 · INJ-0132 · INJ-0154 · INJ-0428
 *                  INJ-0512 · INJ-0513 · INJ-0554
 *
 * **العلّةُ الواحدةُ لثلاثةَ عشرَ بندًا**: «المراسلات» تُطبع مرتين في سايدبارِ
 * خمسةٍ وعشرين دورًا — مرةً محقونةً في المولِّدِ ومرةً من صفٍّ في `nav_items`.
 * وبنودٌ أخرى من النوعِ نفسِه: مسارٌ واحدٌ بمِرساتين أو ببادئتين.
 *
 * ── وفخّانِ مقيسانِ عولجا هنا صراحةً ────────────────────────────────────────
 *   ① **لا يُقاس باستعلامٍ على `nav_items`**: النسخةُ الثانيةُ يطبعها المولِّدُ
 *      **ولا تُخزَّن**، فـ`GROUP BY route` يُرجع صفرَ تكرارٍ والتكرارُ قائمٌ في
 *      خمسةٍ وعشرين دورًا. فالقياسُ **على المُخرَجِ المُصيَّرِ حيًّا**.
 *   ② **وبعمليةٍ منفصلةٍ لكلِّ دور**: الجلسةُ تتلوّث بين الأدوارِ داخلَ العمليةِ
 *      الواحدةِ فيرث الدورُ التالي قائمةَ سابقِه.
 *
 * ── والتطبيعُ قبل المقارنة ─────────────────────────────────────────────────
 * `../chats/index.php#n9g7i1` و`chats/index.php` **ملفٌّ واحد**. فتُجرَّد المرساةُ
 * والبادئةُ النسبيةُ وسلسلةُ الاستعلامِ — وإلا مرَّ التكرارُ صامتًا (وهو جذرُ
 * سبعةِ بنودٍ في السجل).
 *
 * ◆ ولا يُقبل قياسٌ فارغ: يُشترط أن يُصيَّر لكلِّ دورٍ **رابطٌ واحدٌ على الأقل**،
 *   وإلا كان «صفرُ تكرارٍ» خضرةً كاذبةً على سايدبارٍ فارغ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ لا مسارَ يتكرَّر في قائمةِ دورٍ واحد');

/* ── ① الحارسُ في المولِّدِ نفسِه ─────────────────────────────────────────── */
$dyn = (string) @file_get_contents($ROOT . '/includes/dynamic_nav.php');
$nav = (string) @file_get_contents($ROOT . '/includes/unified_nav.php');
$ok(strpos($dyn, "ems_nav_mark_printed(\$link['code'] . '||'") !== false,
    '**حارسُ التكرارِ في `printNavLinkItem`** — مخنقُ كلِّ رابطٍ في السايدبار، بالمسارِ والتسمية');
$ok(strpos($nav, 'function ems_nav_norm_route') !== false
    && strpos($nav, "preg_replace('~^(\\.\\./)+~', '', \$r)") !== false,
    'والتطبيعُ يجرّد البادئةَ النسبيةَ وسلسلةَ الاستعلامِ قبل المقارنة');
/* ◆ والمِرساةُ **لا تُجرَّد في الحارس**: مِرساتانِ مختلفتانِ مدخلانِ مقصودان.
     وأوّلُ صياغةٍ جرّدتها فابتلعت أربعين رابطًا مشروعًا (1276 ⇒ 1236). */
$ok(strpos($nav, '$kBare  = $hasAnchor ? null :') !== false
    && strpos($nav, '$kLabel = $file') !== false,
    'والقاعدةُ شرطان: نفسُ الملفِّ بنفسِ التسمية · أو بلا مِرساةٍ في الطرفين');
$ok(strpos($nav, "ems_nav_mark_printed('chats/index.php||المراسلات')") !== false
    && strpos($nav, "ems_nav_mark_printed('main/role_board.php||الرئيسية')") !== false,
    'والرابطانِ المحقونانِ يُسجَّلانِ بصيغةِ المفتاحِ نفسِها — فلا يُطبعانِ من مصدرين');

/* ── ② الصفوفُ المكرَّرةُ حُذفت والأرشيفُ قائم ──────────────────────────────── */
$rows = 0;
$r = $conn->query("SELECT COUNT(*) FROM nav_items WHERE route LIKE '%chats/index.php%'");
if ($r) { $rows = (int) $r->fetch_row()[0]; }
$ok($rows === 0, "صفرُ صفِّ «مراسلات» في `nav_items` ({$rows}) — والمحقونُ يحمل شارةَ غيرِ المقروء");
$arch = 0;
$r = $conn->query('SELECT COUNT(*) FROM nav_items_archive_chats');
if ($r) { $arch = (int) $r->fetch_row()[0]; }
$ok($arch > 0, "والمحذوفُ مؤرشفٌ ({$arch} صفًّا) — فالردُّ ممكنٌ بأمرٍ واحد");

/* ── ③ القياسُ الحيُّ: كلُّ دورٍ بعمليةٍ منفصلة ──────────────────────────────── */
$roles = array();
$r = $conn->query('SELECT DISTINCT role_id FROM nav_items ORDER BY CAST(role_id AS UNSIGNED)');
while ($r && ($x = $r->fetch_row())) { $roles[] = (int) $x[0]; }
$ok(count($roles) >= 10, 'أدوارٌ لها قوائمُ تُقاس (' . count($roles) . ')');

$php = PHP_BINARY;
$probe = $ROOT . '/tools/fix_nav_dup_probe.php';
$ok(is_file($probe), 'ومِسبارُ التكرارِ مبنيّ');

$bad = array(); $empty = array(); $checked = 0; $totalLinks = 0;
foreach ($roles as $rid) {
    $out = array(); $rc = 1;
    @exec(escapeshellarg($php) . ' ' . escapeshellarg($probe) . ' ' . escapeshellarg((string) $rid) . ' 2>&1', $out, $rc);
    $line = implode(' ', $out);
    $checked++;
    /* المقياسانِ معًا: رابطٌ مكرَّرٌ حرفيًّا **و**ملفٌّ بالتسميةِ نفسِها مرتين */
    if (preg_match('~DUP=(\d+) SAMELABEL=(\d+).*links=(\d+)~', $line, $m)) {
        $totalLinks += (int) $m[3];
        if ((int) $m[1] > 0 || (int) $m[2] > 0) {
            $bad[] = 'دور ' . $rid . ' (رابط=' . $m[1] . ' لابل=' . $m[2] . ')';
        }
        if ((int) $m[3] === 0) { $empty[] = $rid; }
    } else {
        $bad[] = 'دور ' . $rid . ' (مخرجٌ غيرُ مقروء)';
    }
}
$ok($checked === count($roles), "صُيِّرت قوائمُ {$checked} دورٍ **بعمليةٍ منفصلةٍ لكلٍّ**");
$ok($totalLinks > 200, "وبمجموعِ {$totalLinks} رابطٍ مُصيَّرٍ — فالقياسُ ليس على قوائمَ فارغة");
$ok(empty($empty), 'ولا دورَ بقائمةٍ فارغة', 'فارغة: ' . implode(' · ', array_slice($empty, 0, 6)));
$ok(empty($bad), '**صفرُ مسارٍ يتكرَّر في أيِّ دور** (بعد تجريدِ المرساةِ والبادئة)',
    implode(' · ', array_slice($bad, 0, 6)));

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
