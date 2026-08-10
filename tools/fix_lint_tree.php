<?php
/**
 * tools/fix_lint_tree.php — مسحُ الشجرةِ: تركيبٌ **ودلالة**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ سببُ وجودِه — وقد قِيس لا فُرِض: قيدُ «`namespace` أولُ عبارة» قيدُ
 *   **تصريفٍ** لا تحليل، فلا يراه `token_get_all(..., TOKEN_PARSE)` **ولا
 *   يراه `php -l`**. جُرِّب: أُقحم `$x=1;` قبلَ `namespace` فقال `php -l`
 *   «لا أخطاء» والملفُّ ينفجر فورَ تضمينِه. ولا يظهر إلا بالتنفيذ.
 *
 * ◆ وهذا ما وقع: أعلنتُ مسحًا «3571/3571 نظيفًا» والشجرةُ فيها 74 ملفًّا
 *   مكسورًا، ولم ينكشف إلا بتصييرٍ حيٍّ أظهر 11 دورًا بصفرِ قائمة.
 *
 * ◆ القاعدتان المستفادتان:
 *   ① **لا مرجعَ فوقَ التنفيذ**: أداتا الفحصِ الساكنِ كلتاهما عمياوان هنا.
 *   ② وكلُّ فحصٍ يُعاير بإفسادِ مفحوصِه قبل الوثوقِ به — وهذا الفاحصُ عُوير:
 *      صفرُ عيبٍ على الشجرةِ النظيفة، وواحدٌ فورَ الإقحام.
 *
 * الفحوص:
 *   ① تركيبٌ بالتحليلِ الكامل (كلُّ الشجرة · سريع).
 *   ② `namespace` مسبوقًا بعبارةٍ تنفيذية (كلُّ الشجرة · سريع).
 *   ③ `declare(strict_types)` بعد عبارةٍ — القيدُ نفسُه بصورةٍ أخرى.
 *   ④ `php -l` المرجعيُّ على ما مرَّرتَه بـ`--files=`.
 *
 * التشغيل: php tools/fix_lint_tree.php [--files=a.php,b.php]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/fix_lib.php';

$refFiles = array();
foreach ($argv as $a) {
    if (strpos($a, '--files=') === 0) {
        $refFiles = array_filter(array_map('trim', explode(',', substr($a, 8))));
    }
}

$n = 0; $bad = array();
foreach (fix_php_files($ROOT) as $rel) {
    $abs = $ROOT . '/' . $rel;
    $src = (string) @file_get_contents($abs);
    if ($src === '') { continue; }
    $n++;

    /* ① التركيب */
    try { $toks = token_get_all($src, TOKEN_PARSE); }
    catch (\ParseError $e) { $bad[] = array($rel, 'تركيب: ' . $e->getMessage()); continue; }

    /* ②③ ترتيبُ `namespace` و`declare` — يُقرأ من التوسيمِ لا بمطابقةِ نص،
          فاسمُ `namespace` داخلَ تعليقٍ أو نصٍّ ليس تصريحًا. */
    /* ◆ `declare(strict_types=1);` مسموحٌ قبلَ `namespace` — ويجب تخطّي
         **الجملةِ كاملةً** لا الكلمةِ المفتاحيةِ وحدَها. تخطّي `T_DECLARE` فقط
         يجعل `strict_types` و`1` تُقرآن «عبارةً تنفيذية» فيُتَّهم 22 ملفًّا
         سليمًا — وقد أعلن `php -l` براءتَها. المرجعُ يحكم على الفاحصِ لا العكس. */
    $sawExecutable = false;
    $skipToSemi = false;
    foreach ($toks as $t) {
        $s  = is_array($t) ? $t[1] : $t;
        $id = is_array($t) ? $t[0] : null;

        if ($skipToSemi) { if ($s === ';') { $skipToSemi = false; } continue; }
        if ($id === T_DECLARE) { $skipToSemi = true; continue; }

        if ($id === T_OPEN_TAG || $id === T_OPEN_TAG_WITH_ECHO || $id === T_WHITESPACE
            || $id === T_COMMENT || $id === T_DOC_COMMENT || $id === T_INLINE_HTML
            || $id === T_CLOSE_TAG) { continue; }
        if ($id === T_NAMESPACE) {
            if ($sawExecutable) { $bad[] = array($rel, 'دلالة: `namespace` مسبوقٌ بعبارةٍ تنفيذية'); }
            break;
        }
        if ($id === null && trim($s) === '') { continue; }
        $sawExecutable = true;
    }
}

/* ④ المرجعُ الحقيقيُّ على المتغيّر */
$refBad = 0;
foreach ($refFiles as $f) {
    $p = is_file($f) ? $f : $ROOT . '/' . $f;
    if (!is_file($p)) { continue; }
    $out = array(); $code = 0;
    @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($p) . ' 2>&1', $out, $code);
    if ($code !== 0) { $bad[] = array($f, 'php -l: ' . trim(implode(' ', $out))); $refBad++; }
}

echo str_repeat('═', 70) . "\n";
printf("مُحلَّلٌ %d ملفًّا · مرجعيٌّ (php -l) %d · معيبٌ %d\n", $n, count($refFiles), count($bad));
foreach (array_slice($bad, 0, 25) as $b) { echo '  ✗ ' . $b[0] . ' — ' . mb_substr($b[1], 0, 80) . "\n"; }
if (count($bad) > 25) { echo '  … (+' . (count($bad) - 25) . ")\n"; }
echo str_repeat('═', 70) . "\n";
exit(empty($bad) ? 0 : 1);
