<?php
/**
 * tools/fix_nav_label_probe.php — شاهدُ عائلةِ «العنوانُ الخاطئ» (14 ملاحظة)
 * ═══════════════════════════════════════════════════════════════════════════
 * شاهدُ القبولِ في السجل: «كلُّ رابطٍ في السايدبار يفتح شاشةً عنوانُها مطابقٌ
 * لتسميته». فيُقاس وجهان لا وجهٌ واحد:
 *   ⓐ **غيابُ العنوان** — شاشةٌ بلا `$page_title` تُصدِر `<title></title>`.
 *      وهذا عيبٌ قائمٌ بذاته لا يحتاج مقارنة.
 *   ⓑ **الانفصال** — عنوانٌ موجودٌ لا يشترك مع تسميةِ الرابطِ في كلمةٍ دالةٍ
 *      واحدة. والمعيارُ «اشتراكُ كلمةٍ دالة» لا «التطابقُ الحرفيّ»: التطابقُ
 *      الحرفيُّ يرسّب فروقًا مشروعةً («العقود» مقابل «قائمة العقود»)، فيغرق
 *      العيبُ الحقيقيُّ في ضجيج. المقياسُ يخدم الكشفَ لا الشكل.
 *
 * ◆ العنوانُ يُستخرج **بالتوسيم لا بمطابقةِ نص**: `$page_title` في تعليقٍ أو
 *   في نصٍّ حرفيٍّ ليس إعلانًا. `token_get_all` وحدَه يميّز.
 *
 * التشغيل: php tools/fix_nav_label_probe.php [--json] [--all]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT   = dirname(__DIR__);
$asJson = in_array('--json', $argv, true);
$showAll = in_array('--all', $argv, true);

require_once $ROOT . '/tools/fix_lib.php';
$conn = fix_db();

/**
 * يستخرج قيمةَ `$page_title` من ملفٍّ بالتوسيم.
 * يُرجع النصَّ، أو '' إن لم يُعلَن، أو null إن أُسند تعبيرًا لا نصًّا حرفيًّا
 * (متغيّرٌ أو نداءُ دالة) — وهذه حالةٌ لا يصحُّ الحكمُ عليها ساكنًا.
 */
function label_extract_title($abs)
{
    $src = @file_get_contents($abs);
    if ($src === false) { return ''; }
    try { $tok = token_get_all($src, TOKEN_PARSE); }
    catch (\ParseError $e) { return null; }

    $n = count($tok);
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($tok[$i]) || $tok[$i][0] !== T_VARIABLE || $tok[$i][1] !== '$page_title') { continue; }
        // التالي غيرُ الفراغِ يجب أن يكون '='
        $j = $i + 1;
        while ($j < $n && is_array($tok[$j]) && $tok[$j][0] === T_WHITESPACE) { $j++; }
        if ($j >= $n || $tok[$j] !== '=') { continue; }
        $k = $j + 1;
        while ($k < $n && is_array($tok[$k]) && $tok[$k][0] === T_WHITESPACE) { $k++; }
        if ($k >= $n || !is_array($tok[$k])) { return null; }
        if ($tok[$k][0] === T_CONSTANT_ENCAPSED_STRING) {
            $v = $tok[$k][1];
            // بعدَه فاصلةٌ منقوطةٌ وإلا فهو جزءٌ من تعبيرِ وصل
            $m = $k + 1;
            while ($m < $n && is_array($tok[$m]) && $tok[$m][0] === T_WHITESPACE) { $m++; }
            if ($m < $n && $tok[$m] !== ';') { return null; }
            return trim(substr($v, 1, -1));
        }
        return null;   // تعبيرٌ لا نصٌّ حرفيّ
    }

    /* ── الشاشاتُ المولَّدة: العنوانُ يُضبط في القشرةِ لا في الملف ─────────
       القياسُ الأولُ أبلغ **52 شاشةً بلا عنوان** وكلُّها معنونة: الفاحصُ كان
       يقرأ ملفَّ الشاشةِ وحدَه، بينما `u13_screen_kit` تنفّذ
       `$page_title = 'إيكوبيشن | ' . $U13['title']` والشاشةُ تعلن عنوانَها في
       بيانها. ثم تكرّر الخطأُ مع قشرةٍ ثالثةٍ (`fin_analysis_shell`) فصارت 11.

       ◆ وهذا في ذاته شاهدُ SH-01: **ثلاثُ قشراتٍ** تعني أن كلَّ أداةِ قياسٍ
         تحتاج حالةً خاصةً لكلٍّ منها — والرابعةُ ستكسر هذا الفاحصَ صامتًا.
         ولذلك سجلٌّ صريحٌ لا سلسلةُ `if`: الإضافةُ تُرى، والغيابُ يُعلَن. */
    static $SHELLS = array(
        'u13_screen_kit'     => '$U13',
        'fin_analysis_shell' => '$FA_SCREEN',
    );
    foreach ($SHELLS as $shellFile => $_specVar) {
        // بالتوسيم لا بمطابقةِ النص: اسمُ القشرةِ في تعليقٍ ليس تضمينًا.
        if (!preg_match('/(?:require|include)(?:_once)?[^;]*' . preg_quote($shellFile, '/') . '/u', $src)) {
            continue;
        }
        if (preg_match("/'title'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/u", $src, $m)) {
            return str_replace("\\'", "'", $m[1]);
        }
        return '';   // على قشرةٍ معروفةٍ وبلا عنوانٍ في بيانِه — عيبٌ حقيقيّ
    }

    /* ── القشرةُ الرابعة: رأسٌ خاصٌّ ووسمُ <title> حرفيّ ────────────────────
       تسعٌ وثلاثون شاشةً تُصدِر `<html>` بنفسِها (وهو عينُ عيبِ SH-01) وتكتب
       عنوانَها وسمًا لا متغيّرًا. وهذه رابعُ صيغةٍ للعنوانِ في نظامٍ واحد —
       وكلُّ صيغةٍ كلّفت هذا الفاحصَ جولةَ إيجابيّاتٍ كاذبة: 52 ثم 11 ثم 1.
       ولا يُغلق هذا البابُ إلا بتوحيدِ القشرة. */
    if (preg_match('#<title>\s*(.*?)\s*</title>#us', $src, $m)) {
        $t = trim(strip_tags($m[1]));
        if ($t !== '' && strpos($t, '<?') === false) { return $t; }
    }
    return '';
}

/**
 * كلماتٌ دالةٌ مجذَّرةٌ تجذيرًا خفيفًا.
 * ◆ بلا هذا التنقيةِ يمتلئ التقريرُ بإيجابيّاتٍ كاذبة: بادئةُ العلامةِ
 *   «إيكوبيشن |» تحتلُّ العنوان، و«الموظفين» لا تطابق «الموظفون» حرفيًّا
 *   وهما كلمةٌ واحدة. ومقياسٌ ضجيجُه أكبرُ من إشارتِه يُخفي العيبَ لا يكشفه.
 */
function label_tokens($s)
{
    $stop = array('في', 'من', 'على', 'إلى', 'عن', 'قائم', 'شاش', 'إدار', 'صفح',
                  'نظام', 'كل', 'جميع', 'رئيس', 'لوح', 'سجل', 'بيان', 'إيكوبيشن', 'ems');
    $s = (string) $s;
    // بادئةُ العلامةِ التجاريةِ ليست عنوانًا
    if (($p = mb_strpos($s, '|')) !== false) { $s = mb_substr($s, $p + 1); }
    $s = preg_replace('/[^\p{Arabic}\p{L}\p{N}\s]/u', ' ', $s);
    $out = array();
    foreach (preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY) as $w) {
        $w = preg_replace('/^(وال|بال|لل|ال|و)/u', '', $w);            // أدواتٌ ملتصقة
        $w = preg_replace('/(ات|ون|ين|ية|ها|هم|تي|ان|ة)$/u', '', $w);  // لواحقُ صرفية
        if (mb_strlen($w) < 3 || in_array($w, $stop, true)) { continue; }
        $out[$w] = true;
    }
    return array_keys($out);
}

/* ── الجمع: كلُّ ما يقبله المُنتقي، بلا تكرار ─────────────────────────── */
$seen = array();
$noTitle = array();
$diverge = array();
$dynamic = array();
$checked = 0;

$r = $conn->query('SELECT DISTINCT route, label_ar FROM nav_items
                    WHERE active=1 AND route LIKE \'%.php%\' ORDER BY route');
while ($x = $r->fetch_assoc()) {
    $route = strtok($x['route'], '?#');
    $abs   = $ROOT . '/' . $route;
    if (!is_file($abs)) { continue; }                 // الروابطُ المكسورةُ شأنُ AC-N1
    $key = $route . '|' . $x['label_ar'];
    if (isset($seen[$key])) { continue; }
    $seen[$key] = true;
    $checked++;

    $title = label_extract_title($abs);
    if ($title === null) { $dynamic[] = array('route' => $route, 'label' => $x['label_ar']); continue; }
    if ($title === '')   { $noTitle[] = array('route' => $route, 'label' => $x['label_ar']); continue; }

    $a = label_tokens($x['label_ar']);
    $b = label_tokens($title);
    if ($a && $b && !array_intersect($a, $b)) {
        $diverge[] = array('route' => $route, 'label' => $x['label_ar'], 'title' => $title);
    }
}

if ($asJson) {
    echo json_encode(array('checked' => $checked, 'no_title' => $noTitle,
                           'diverge' => $diverge, 'dynamic' => count($dynamic)),
                     JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
    exit(($noTitle || $diverge) ? 1 : 0);
}

echo "═══ عائلةُ العناوين — تسميةُ الرابطِ مقابلَ عنوانِ الشاشة ═══\n";
echo "أزواجٌ مفحوصة: {$checked} · عنوانٌ ديناميٌّ (لا يُحكم ساكنًا): " . count($dynamic) . "\n\n";

echo '① بلا `$page_title` إطلاقًا — تُصدِر <title></title>: ' . count($noTitle) . "\n";
foreach (array_slice($noTitle, 0, $showAll ? 999 : 12) as $x) {
    printf("    %-44s «%s»\n", mb_substr($x['route'], 0, 42), mb_substr($x['label'], 0, 28));
}
if (!$showAll && count($noTitle) > 12) { echo '    … (+' . (count($noTitle) - 12) . ")\n"; }

echo "\n② عنوانٌ لا يشترك مع التسميةِ في كلمةٍ دالة: " . count($diverge) . "\n";
foreach (array_slice($diverge, 0, $showAll ? 999 : 15) as $x) {
    printf("    %-34s «%s»  ≠  «%s»\n", mb_substr($x['route'], 0, 32),
           mb_substr($x['label'], 0, 22), mb_substr($x['title'], 0, 22));
}
if (!$showAll && count($diverge) > 15) { echo '    … (+' . (count($diverge) - 15) . ")\n"; }

echo "\n" . str_repeat('═', 70) . "\n";
printf("الحصيلة: %d بلا عنوان · %d منفصل · من %d زوجًا\n", count($noTitle), count($diverge), $checked);
exit(($noTitle || $diverge) ? 1 : 0);
