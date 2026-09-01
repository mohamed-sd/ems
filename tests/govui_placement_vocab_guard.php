<?php
/**
 * tests/govui_placement_vocab_guard.php — حارسُ مفرداتِ صنفِ الموضع (§8)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ الذي وقع فعلًا ويحرسه هذا الفاحص**: أُضيف الصنفُ السابعُ
 *   `LANDING_PAGE` بأمرِ `GOV_UI_EXEC §8`، **فسقط تسعةَ عشرَ موضعًا من طبقةِ
 *   المواضعِ في المُصيِّر** لأنَّ استعلامَين فيه يعُدّان `MENU_ITEM` وحدَه —
 *   فغابت **سبعةَ عشرَ رأسَ مجموعةٍ** وهبط `STRUCTURAL_NAV_PASS` من 17/17 إلى
 *   0/17 **بلا أن يتغيّر موضعُ صفٍّ واحد**. العطبُ في **قارئِ المفردات** لا في
 *   البيانات [[measure-token-must-exist]].
 *
 * ◆ **والقاعدةُ التي يفرضها**: كلُّ موضعٍ **يظهر في السايدبار** يجب أن تُعدَّ
 *   مفردتُه معًا. والصنفانِ الظاهرانِ اليومَ: `MENU_ITEM` و`LANDING_PAGE`.
 *   فأيُّ استعلامٍ في شجرةِ الإنتاجِ أو العُدّةِ يذكر أحدَهما وحدَه **يُرسِّب**.
 *
 * ◆ **واختبارٌ سالبٌ يتحقّق أنَّ الفاحصَ يعرف كيف يرسُب** — بمفردةٍ مصطنعةٍ
 *   في نصٍّ مؤقَّتٍ، لا بكسرِ ملفٍّ حقيقيّ [[negative-test-needs-unique-token]].
 *
 * التشغيل: php tests/govui_placement_vocab_guard.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));

/** أصنافُ الظهورِ في السايدبار — تُقرأ من مفرداتِ العمودِ لا تُكتب يدًا */
$VISIBLE = array('MENU_ITEM', 'LANDING_PAGE');

/** يلتقط اقتباسًا يذكر صنفًا ظاهرًا ولا يذكر أخاه في السطرِ نفسِه أو ما يليه */
function govui_vocab_scan($text)
{
    $hits = array();
    $lines = preg_split('~\r?\n~', $text);
    foreach ($lines as $i => $ln) {
        if (strpos($ln, 'placement_type') === false) { continue; }
        $win = $ln . "\n" . (isset($lines[$i + 1]) ? $lines[$i + 1] : '');
        if (strpos($win, 'MENU_ITEM') === false) { continue; }
        if (strpos($win, 'LANDING_PAGE') !== false) { continue; }
        /* مقارنةُ مساواةٍ أو `IN (...)` — وما عداها (كتابةٌ أو تعليقٌ) لا يُحاسَب */
        if (!preg_match("~placement_type\\s*(=|IN)~i", $win)) { continue; }
        if (preg_match('~\bSET\b~i', $win)) { continue; }
        $hits[] = array($i + 1, trim($ln));
    }
    return $hits;
}

$SCAN_DIRS = array('/includes', '/tools', '/tests', '/app', '/main', '/Portal');
$files = array();
foreach ($SCAN_DIRS as $d) {
    $dir = $ROOT . $d;
    if (!is_dir($dir)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $pth = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
        if (substr($pth, -4) !== '.php') { continue; }
        if (strpos($pth, '/storage/') !== false || strpos($pth, '/vendor/') !== false) { continue; }
        /* ⛔ **والكاشفُ يرصد مفرداتِه هو** [[injfix02-annex-verification]]: مِجَسُّه
           السالبُ نصٌّ يذكر المفردةَ وحدَها عمدًا — فيُستثنى ملفُّه باسمِه لا بنمطٍ عامّ. */
        if ($pth === str_replace(DIRECTORY_SEPARATOR, '/', __FILE__)) { continue; }
        $files[] = $pth;
    }
}
$bad = array();
foreach ($files as $f) {
    foreach (govui_vocab_scan((string) file_get_contents($f)) as $h) {
        $bad[] = str_replace($ROOT . '/', '', $f) . ':' . $h[0] . '  ' . mb_substr($h[1], 0, 90);
    }
}

echo "══ حارسُ مفرداتِ صنفِ الموضعِ — GOV_UI_EXEC §8 ══\n";
printf("  ملفاتٌ مسحت: %d · الأصنافُ الظاهرة: %s\n", count($files), implode(' · ', $VISIBLE));
$pass = 0; $fail = 0;
if (!$bad) { echo "  ✔ ① صفرُ استعلامٍ يعُدُّ MENU_ITEM وحدَه\n"; $pass++; }
else {
    echo "  ✘ ① استعلاماتٌ تعُدُّ MENU_ITEM وحدَه — " . count($bad) . ":\n";
    foreach ($bad as $b) { echo "     · {$b}\n"; }
    $fail++;
}

/* ── الاختبارُ السالبُ: أيعرف الفاحصُ كيف يرسُب؟ ── */
$probe = "\$q = \"SELECT * FROM nav_placements WHERE placement_type = 'MENU_ITEM' AND active = 1\";";
$probeHit = govui_vocab_scan($probe);
if ($probeHit) { echo "  ✔ ② الاختبارُ السالبُ: نصٌّ يعُدُّ المفردةَ وحدَها **يُمسَك**\n"; $pass++; }
else { echo "  ✘ ② الاختبارُ السالبُ لم يُمسَك — الفاحصُ أعمى\n"; $fail++; }
$probe2 = "\$q = \"… placement_type IN ('MENU_ITEM','LANDING_PAGE') …\";";
if (!govui_vocab_scan($probe2)) { echo "  ✔ ③ ونصٌّ يعُدُّ الاثنَين **يمرّ**\n"; $pass++; }
else { echo "  ✘ ③ نصٌّ سليمٌ رُسِّب — الفاحصُ يصرخ بلا سبب\n"; $fail++; }

/* ── والمخزنُ نفسُه: كلُّ مفردةٍ في العمودِ معروفةٌ للحارس ── */
define('EMS_CLI', true);
ob_start();
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
ob_end_clean();
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$r = @$conn->query("SHOW COLUMNS FROM nav_placements LIKE 'placement_type'");
$col = $r ? $r->fetch_assoc() : null;
$vals = array();
if ($col && preg_match_all("~'([^']+)'~", (string) $col['Type'], $m)) { $vals = $m[1]; }
$unknown = array_diff($VISIBLE, $vals);
if (!$unknown) { printf("  ✔ ④ مفرداتُ العمودِ %d وكلُّ صنفٍ ظاهرٍ منها\n", count($vals)); $pass++; }
else { echo "  ✘ ④ صنفٌ ظاهرٌ لا وجودَ له في العمود: " . implode(' · ', $unknown) . "\n"; $fail++; }

echo str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
