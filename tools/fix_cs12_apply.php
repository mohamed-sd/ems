<?php
/**
 * tools/fix_cs12_apply.php — إضافةُ تسجيلِ الاستثناءِ إلى كتلِ catch الصامتة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لا يغيّر مسارَ التحكُّم إطلاقًا: يُدرِج `ems_catch_log($v, __METHOD__);`
 *   في أولِ الكتلةِ ويترك الجسمَ كما هو. فالسلوكُ قبلُ وبعدُ واحد، والمكسبُ
 *   أن الفشلَ صار مقروءًا.
 *
 * ◆ **لا يُمسُّ إلا ما جسمُه إسناداتٌ بسيطة**: `$x = null;` · `$x = array();`
 *   · `$x = 0;` — وهي حالةُ «السقّاطةِ المفحوصةِ بعدها». وأيُّ كتلةٍ فيها
 *   شرطٌ أو حلقةٌ أو نداءٌ تُترك ليدٍ بشرية: التحويلُ الآليُّ على منطقٍ لا
 *   يفهمه هو مصدرُ الأعطالِ التي تأتي بعد «التصحيح».
 *
 * ◆ وينسخ كلَّ ملفٍّ قبل مسِّه إلى `storage/backups/` (وهو مستثنًى من git)،
 *   ثم **يُمسح المشروعُ كلُّه** تركيبًا بعد الانتهاء — لأن فحصَ الملفِّ وحدَه
 *   لا يكفي: تسرّبت من قبلُ ثلاثةُ ملفاتٍ مكسورةٍ رغم التراجعِ الفرديّ.
 *
 * التشغيل: php tools/fix_cs12_apply.php [--apply]   (بلا العلم: عرضٌ فقط)
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
require_once $ROOT . '/tools/fix_lib.php';
require_once $ROOT . '/tools/fix_checks.php';

$scope = array('app/Services/', 'app/Core/', 'includes/');
$stamp = 'cs12_' . gmdate('Ymd_His');
$backupDir = $ROOT . '/storage/backups/' . $stamp;

$touchedFiles = 0; $touchedBlocks = 0; $skipped = 0;

foreach (fix_php_files($ROOT) as $rel) {
    $in = false;
    foreach ($scope as $d) { if (strpos($rel, $d) === 0) { $in = true; break; } }
    if (!$in) { continue; }
    if ($rel === 'includes/catch_log.php') { continue; }

    $abs = $ROOT . '/' . $rel;
    $src = (string) @file_get_contents($abs);
    if ($src === '' || strpos($src, 'catch') === false) { continue; }

    /* المطابقة: `catch (أنواع $var) {  إسناداتٌ بسيطةٌ فقط  }`
       ◆ الجسمُ مقيَّدٌ بـ`[^{}();]` قبل `=` وبقيمةٍ بسيطة — فلا نداءَ ولا شرط. */
    $pattern = '/catch\s*\(([^)]*?)\$(\w+)\s*\)\s*\{((?:\s*\$\w+\s*=\s*(?:null|array\(\)|\[\]|0|\'\'|""|false|true)\s*;)+)\s*\}/u';

    $fileBlocks = 0;
    $new = preg_replace_callback($pattern, function ($m) use (&$fileBlocks) {
        $types = $m[1]; $var = $m[2]; $body = $m[3];
        if (strpos($body, 'ems_catch_log') !== false) { return $m[0]; }   // عاطلٌ: لا يُضاف مرتين
        $fileBlocks++;
        return 'catch (' . $types . '$' . $var . ') { ems_catch_log($' . $var . ', __METHOD__);'
             . rtrim($body) . ' }';
    }, $src);

    if ($new === null) { echo "⚠ فشلت المطابقةُ في {$rel} — يُترك\n"; $skipped++; continue; }
    if ($fileBlocks === 0) { continue; }

    /* ضمانُ تحميلِ المسجِّل — بعد أولِ `<?php` مباشرةً وقبل أيِّ استعمال */
    if (strpos($new, 'catch_log.php') === false) {
        $needle = "<?php\n";
        $pos = strpos($new, $needle);
        if ($pos === false) { echo "⚠ لا وسمَ فتحٍ في {$rel} — يُترك\n"; $skipped++; continue; }
        $depth = substr_count($rel, '/');
        $up = str_repeat('/..', $depth);
        $inject = "require_once __DIR__ . '" . $up . "/includes/catch_log.php';\n";
        $new = substr($new, 0, $pos + strlen($needle)) . $inject . substr($new, $pos + strlen($needle));
    }

    /* التركيبُ قبل الكتابة — ملفٌّ مكسورٌ لا يُكتب أصلًا */
    try { token_get_all($new, TOKEN_PARSE); }
    catch (\ParseError $e) {
        echo "✘ {$rel} — التحويلُ يكسر التركيب، يُترك: " . $e->getMessage() . "\n";
        $skipped++;
        continue;
    }

    if ($apply) {
        $bdir = $backupDir . '/' . dirname($rel);
        if (!is_dir($bdir)) { @mkdir($bdir, 0777, true); }
        @copy($abs, $backupDir . '/' . $rel);
        file_put_contents($abs, $new);
    }
    $touchedFiles++;
    $touchedBlocks += $fileBlocks;
    if (!$apply && $touchedFiles <= 10) { printf("  %-56s %d كتلة\n", $rel, $fileBlocks); }
}

echo "\n" . str_repeat('═', 70) . "\n";
printf("%s: %d ملفًّا · %d كتلة · متروكٌ %d\n",
       $apply ? 'طُبِّق' : 'سيُطبَّق', $touchedFiles, $touchedBlocks, $skipped);
if ($apply) { echo "النسخُ الاحتياطيّ: storage/backups/{$stamp}\n"; }
echo str_repeat('═', 70) . "\n";
