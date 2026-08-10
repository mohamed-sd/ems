<?php
/**
 * tools/fix_cs12_reasons.php — تعبئةُ أسبابِ التجاهلِ من شهادةِ الكودِ نفسِه
 * ═══════════════════════════════════════════════════════════════════════════
 * الجولةُ الثالثةُ والأخيرةُ في CS-12. تركت الجولةُ الثانيةُ أسبابًا فارغةً حيث
 * لم يكن للمبرمجِ تعليق — والفاحصُ يرفض الفارغَ عن حقّ: **وسمٌ بلا تبريرٍ
 * تصديقٌ على النفس**.
 *
 * ◆ والسببُ ليس مفقودًا — هو مكتوبٌ في موضعٍ آخر: نصُّ `error_log` داخلَ
 *   الكتلةِ نفسِها يحمل وصفَ المبرمجِ للعملية («EventPublisher pre-dup heal»
 *   · «dead-letter alert failed»). فيُنقل إلى موضعِه الصحيحِ بدل أن يُلفَّق.
 *
 * ◆ وما لا نصَّ له: يُوصَف **بما يفعله الكودُ حرفيًّا** لا بتطمينٍ مولَّد —
 *   «قراءةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة» جملةٌ صادقةٌ تُظهر الدَّينَ ولا تدفنه.
 *   والفرقُ جوهريّ: عبارةٌ مطمئنةٌ تكتبها آلةٌ تُغلق البابَ على مراجعةٍ لازمة.
 *
 * ◆ ويحوّل `ems_catch_log` ذا السقّاطةِ غيرِ المفحوصةِ إلى `ems_catch_ignored`:
 *   قائمةٌ فارغةٌ لا يفحصها أحدٌ **هي** تجاهلٌ — والتسميةُ الصادقةُ أولى.
 *
 * التشغيل: php tools/fix_cs12_reasons.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
require_once $ROOT . '/tools/fix_lib.php';
require_once $ROOT . '/tools/fix_checks.php';

$scope = array('app/Services/', 'app/Core/', 'includes/');
$stamp = 'cs12r_' . gmdate('Ymd_His');
$backupDir = $ROOT . '/storage/backups/' . $stamp;

$files = 0; $filled = 0; $converted = 0;

foreach (fix_php_files($ROOT) as $rel) {
    $in = false;
    foreach ($scope as $d) { if (strpos($rel, $d) === 0) { $in = true; break; } }
    if (!$in || $rel === 'includes/catch_log.php') { continue; }

    $abs = $ROOT . '/' . $rel;
    $src = (string) @file_get_contents($abs);
    if ($src === '' || strpos($src, 'ems_catch_') === false) { continue; }
    $orig = $src;

    /* ① تعبئةُ سببٍ فارغٍ من نصِّ error_log المجاورِ في الكتلةِ نفسِها */
    /* ◆ النافذةُ تمتدُّ على السطرِ نفسِه: أغلبُ الكتلِ سطرٌ واحد
         `catch (…) { ems_catch_ignored($t, __METHOD__, ''); error_log('…'); }`
       واشتراطُ سطرٍ جديدٍ قبل `}` أضاع 31 سببًا مكتوبًا سلفًا. */
    $src = preg_replace_callback(
        '/(ems_catch_ignored\s*\(\s*\$(\w+)\s*,\s*__METHOD__\s*,\s*\'\'\s*\);)([^\n]{0,400})/u',
        function ($m) use (&$filled) {
            $tail = $m[3];
            $why = '';
            // ① نصُّ سجلٍّ مجاورٌ — وصفُ المبرمجِ للعمليةِ بلفظه
            if (preg_match('/error_log\s*\(\s*([\'"])(.*?)\1/us', $tail, $lm)) {
                $why = trim(preg_replace('/\s*[:—#-]\s*$/u', '', $lm[2]));
            }
            // ② وسمُ معاملةٍ مُرِّر للمُغلِّف — يحمل اسمَ العمليةِ أيضًا
            if ($why === '' && preg_match('/,\s*([\'"])([^\'"]{6,})\1\s*\)\s*;?\s*$/u', $tail, $tm)) {
                $why = trim($tm[2]);
            }
            // ③ سقّاطةٌ تُسند قيمةً — يُوصف الأثرُ حرفيًّا
            if ($why === '' && preg_match('/\$(\w+)(\[[^\]]*\])?\s*=\s*([^;]+);/u', $tail, $am)) {
                $why = 'فشلٌ يُعامَل بقيمةٍ افتراضية — $' . $am[1] . ($am[2] ?? '') . ' = ' . trim($am[3]);
            }
            if ($why === '' && preg_match('~//\s*(.+)~u', $tail, $cm)) { $why = trim($cm[1]); }
            if ($why === '') { return $m[0]; }
            $filled++;
            $q = str_replace("'", "\\'", mb_substr($why, 0, 120));
            return "ems_catch_ignored(\${$m[2]}, __METHOD__, '{$q}');" . $tail;
        },
        $src
    );

    /* ② سقّاطةٌ غيرُ مفحوصةٍ ⇒ تسميتُها الصادقةُ «تجاهلٌ» لا «تسجيل» */
    $src = preg_replace_callback(
        '/ems_catch_log\s*\(\s*\$(\w+)\s*,\s*__METHOD__\s*\);\s*(\$(\w+)\s*=\s*(null|array\(\)|\[\]|0|0\.0|\'\'|""|false|true)\s*;)/u',
        function ($m) use (&$converted, $src) {
            $var = $m[1]; $assign = $m[2]; $sent = $m[3]; $val = $m[4];
            $shape = in_array($val, array('array()', '[]'), true) ? 'كقائمةٍ فارغة'
                   : ($val === 'null' ? 'كغيابٍ للسجل' : "بقيمةِ {$val}");
            $converted++;
            $why = "قراءةٌ/كتابةٌ فاشلةٌ تُعامَل {$shape} — \${$sent}";
            return "ems_catch_ignored(\${$var}, __METHOD__, '" . str_replace("'", "\\'", $why) . "'); " . $assign;
        },
        $src
    );

    if ($src === $orig) { continue; }
    try { token_get_all($src, TOKEN_PARSE); }
    catch (\ParseError $e) { echo "✘ {$rel} — يكسر التركيب، يُترك\n"; continue; }

    if ($apply) {
        $bdir = $backupDir . '/' . dirname($rel);
        if (!is_dir($bdir)) { @mkdir($bdir, 0777, true); }
        @copy($abs, $backupDir . '/' . $rel);
        file_put_contents($abs, $src);
    }
    $files++;
}

echo "\n" . str_repeat('═', 70) . "\n";
printf("%s: %d ملفًّا · سببٌ مُستخرَجٌ من الكود %d · تسميةٌ صُحّحت %d\n",
       $apply ? 'طُبِّق' : 'سيُطبَّق', $files, $filled, $converted);
if ($apply) { echo "النسخُ الاحتياطيّ: storage/backups/{$stamp}\n"; }
echo str_repeat('═', 70) . "\n";
