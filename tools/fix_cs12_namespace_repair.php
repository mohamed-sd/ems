<?php
/**
 * tools/fix_cs12_namespace_repair.php — إصلاحُ حقنٍ سبق `namespace`
 * ═══════════════════════════════════════════════════════════════════════════
 * عطلٌ أحدثته جولاتُ CS-12: حُقن `require_once …/catch_log.php` مباشرةً بعد
 * `<?php`، وفي الملفاتِ ذاتِ `namespace` **يجب أن يكون التصريحُ أولَ عبارة**.
 * فانكسرت هذه الملفاتُ عند التنفيذِ لا عند التحليل.
 *
 * ◆ والدرسُ أثمنُ من العطل: **`token_get_all(..., TOKEN_PARSE)` ليس `php -l`.**
 *   الأولُ يتحقق من التركيبِ فقط، وترتيبُ `namespace` قيدٌ **دلاليّ** يُرفع عند
 *   التصريف. فمسحي الشاملُ أعلن «3571/3571 نظيفًا» والشجرةُ فيها 60 ملفًّا
 *   مكسورًا. أيُّ فاحصٍ أسرعُ من المرجعِ يجب أن يُقاس عليه قبل الوثوقِ به.
 *
 * العلاج: يُنقل الحقنُ إلى ما بعدَ سطرِ `namespace ...;` مباشرةً.
 * التشغيل: php tools/fix_cs12_namespace_repair.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
require_once $ROOT . '/tools/fix_lib.php';

$fixed = 0; $checked = 0;
foreach (fix_php_files($ROOT) as $rel) {
    $abs = $ROOT . '/' . $rel;
    $src = (string) @file_get_contents($abs);
    if (strpos($src, 'catch_log.php') === false) { continue; }
    if (!preg_match('/^\s*namespace\s+[^;]+;/m', $src)) { continue; }
    $checked++;

    // موضعُ الحقنِ وموضعُ التصريح
    if (!preg_match('/^require_once __DIR__ \. \'[^\']*\/includes\/catch_log\.php\';\r?\n/m', $src, $im, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    if (!preg_match('/^\s*namespace\s+[^;]+;\r?\n/m', $src, $nm, PREG_OFFSET_CAPTURE)) { continue; }
    if ($im[0][1] > $nm[0][1]) { continue; }   // الحقنُ بعدَه سلفًا — سليم

    $inject = $im[0][0];
    $new = substr_replace($src, '', $im[0][1], strlen($inject));      // ارفعْه من مكانه
    if (!preg_match('/^\s*namespace\s+[^;]+;\r?\n/m', $new, $nm2, PREG_OFFSET_CAPTURE)) { continue; }
    $at = $nm2[0][1] + strlen($nm2[0][0]);
    $new = substr_replace($new, "\n" . $inject, $at, 0);              // وضعْه بعدَ التصريح

    if ($apply) { file_put_contents($abs, $new); }
    $fixed++;
    if (!$apply && $fixed <= 8) { echo "  {$rel}\n"; }
}

echo ($apply ? 'أُصلح' : 'سيُصلَح') . ": {$fixed} ملفًّا (من {$checked} ذي namespace)\n";
