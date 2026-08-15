<?php
/**
 * tools/fix_nav_dup_probe.php — أيتكرَّر مسارٌ في سايدبارِ دورٍ واحد؟
 * ═══════════════════════════════════════════════════════════════════════════
 * يُشغَّل **بعمليةٍ منفصلةٍ لكلِّ دور**. والسببُ مقيس: الجلسةُ تتلوّث بين الأدوارِ
 * داخلَ العمليةِ الواحدةِ فيرث الدورُ التالي قائمةَ سابقِه — فتظهر تكراراتٌ
 * وهميةٌ أو تختفي حقيقية. (النمطُ نفسُه في `tools/fix_nav_href_probe.php`.)
 *
 * ◆ والقياسُ **على المُخرَجِ المُصيَّرِ** لا على `nav_items`: النسخةُ الثانيةُ من
 *   «المراسلات» يطبعها المولِّدُ **ولا تُخزَّن** — فاستعلامٌ بـ`GROUP BY route`
 *   يُرجع صفرَ تكرارٍ بينما التكرارُ قائمٌ في خمسةٍ وعشرين دورًا.
 * ◆ والتطبيعُ قبل المقارنة: المرساةُ والبادئةُ وسلسلةُ الاستعلامِ تُجرَّد.
 *
 *   php tools/fix_nav_dup_probe.php <رقمُ الدور>   ⇒ يطبع DUP=n ثم التفصيل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$role = isset($argv[1]) ? (int) $argv[1] : 0;
if ($role === 0) { exit("DUP=-1 (لا دور)\n"); }

ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/dynamic_nav.php';
require_once $ROOT . '/includes/unified_nav.php';
$conn = $GLOBALS['conn'];

/* جلسةٌ صغرى — المولِّدُ يقرأ الدورَ منها */
if (!isset($_SESSION)) { $_SESSION = array(); }
$_SESSION['user'] = array('id' => 0, 'role' => (string) $role, 'company_id' => 4, 'name' => 'مسبار');

/* المجموعةُ تُصفَّر قبل التصيير — فالقياسُ لا يرث حالةَ نداءٍ سابق */
if (function_exists('ems_nav_mark_printed')) { ems_nav_mark_printed('', true); }

ob_start();
renderUnifiedNavigationV2($conn, $role, '../', array(), '');
$html = (string) ob_get_clean();

/* كلُّ `href` في المُخرَجِ — بمقياسين:
     ⓐ **هويةُ الرابط** (بالمِرساة): رابطانِ متطابقانِ تمامًا = تكرارٌ صريح.
     ⓑ **هويةُ الملفّ** (بلا مِرساة): ملفٌّ واحدٌ بمدخلين — قد يكون مقصودًا
        (قسمانِ في الشاشةِ نفسِها) وقد يكون تكرارًا؛ فيُعلَن ولا يُعَدُّ إخفاقًا
        إلا حين يكون **اللابلُ نفسَه** أيضًا. */
$byLink = array(); $byFile = array(); $labelOf = array();
if (preg_match_all('~<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>~is', $html, $m, PREG_SET_ORDER)) {
    foreach ($m as $a) {
        $h = $a[1];
        $lbl = trim(preg_replace('~\s+~u', ' ', strip_tags($a[2])));
        $kL = ems_nav_norm_route($h, false);
        $kF = ems_nav_norm_route($h, true);
        if ($kL === '' || strpos($kL, 'javascript:') === 0) { continue; }
        $byLink[$kL] = (isset($byLink[$kL]) ? $byLink[$kL] : 0) + 1;
        $byFile[$kF] = (isset($byFile[$kF]) ? $byFile[$kF] : 0) + 1;
        $labelOf[$kF][$lbl] = true;
    }
}
$dups = array();
foreach ($byLink as $k => $n) { if ($n > 1) { $dups[$k] = $n; } }

/* ملفٌّ بمدخلين **وباللابلِ نفسِه** = تكرارٌ حقيقيٌّ لا تمييزَ فيه */
$sameLabel = array();
foreach ($byFile as $k => $n) {
    if ($n > 1 && isset($labelOf[$k]) && count($labelOf[$k]) === 1) { $sameLabel[$k] = $n; }
}

fwrite(STDOUT, 'DUP=' . count($dups) . ' SAMELABEL=' . count($sameLabel) . ' role=' . $role
    . ' links=' . array_sum($byLink) . ' distinct=' . count($byLink)
    . ' files=' . count($byFile) . "\n");
foreach ($dups as $k => $n) { fwrite(STDOUT, "   ✘ رابطٌ مكرَّرٌ: {$k} ×{$n}\n"); }
foreach ($sameLabel as $k => $n) {
    fwrite(STDOUT, '   ✘ ملفٌّ بلابلٍ واحدٍ مرتين: ' . $k . ' ×' . $n
        . ' («' . implode('»، «', array_keys($labelOf[$k])) . '»)' . "\n");
}
exit((count($dups) + count($sameLabel)) === 0 ? 0 : 1);
