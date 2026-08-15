<?php
/**
 * tools/fix_cron_guard_adopt.php — تبنّي حارسِ الجدولةِ في كلِّ ملفٍّ دوريّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0025
 *
 * يُدرج `require_once includes/cron_guard.php; ems_cron_guard('<الملف>');`
 * **بعد `config.php` مباشرةً** — فالحارسُ يحتاج الاتصالَ ليكتب صفَّ الرفض،
 * ويسبق أوّلَ عملٍ فعليّ.
 *
 * ◆ **ولا يُكتب ملفٌّ لا يجتاز `php -l`**: التعديلُ يُبنى في الذاكرةِ ويُفحص
 *   في ملفٍّ مؤقّتٍ ثم يُكتب. وهذه القاعدةُ أنقذت شاشتين من التلفِ حين حاولت
 *   أداةٌ سابقةٌ معالجةَ PHP نصًّا.
 * ◆ ولا يُلمس ملفٌّ يحمل الحارسَ سلفًا — فالتشغيلُ مرارًا لا يضاعف.
 *
 * التشغيل: php tools/fix_cron_guard_adopt.php [--run]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$RUN  = in_array('--run', $argv, true);

$files = array();
foreach (glob($ROOT . '/*/cron_*.php') as $f) { $files[] = str_replace('\\', '/', $f); }
foreach (glob($ROOT . '/cron/*.php') as $f) { $files[] = str_replace('\\', '/', $f); }
sort($files);

echo "══ تبنّي حارسِ الجدولة — " . count($files) . " ملفًّا ══\n\n";
$done = 0; $already = 0; $skipped = array();

foreach ($files as $abs) {
    $rel = ltrim(str_replace($ROOT, '', $abs), '/');
    $src = (string) @file_get_contents($abs);
    if ($src === '') { $skipped[] = $rel . ' (فارغ)'; continue; }

    if (strpos($src, 'ems_cron_guard(') !== false) { $already++; echo "  ○ يحمله سلفًا — {$rel}\n"; continue; }

    /* موضعُ الإدراج: بعد آخرِ `require`/`include` لـ`config.php` */
    if (!preg_match('~^.*(?:require_once|require|include_once|include)[^\n]*config\.php[^\n]*\n~m', $src, $m, PREG_OFFSET_CAPTURE)) {
        $skipped[] = $rel . ' (لا سطرَ config)';
        echo "  ⚠ لا سطرَ `config.php` — {$rel}\n";
        continue;
    }
    $at = $m[0][1] + strlen($m[0][0]);
    $ins = "require_once __DIR__ . '/" . (strpos($rel, '/') !== false ? '../' : '') . "includes/cron_guard.php';\n"
         . "ems_cron_guard('" . basename($rel) . "'); // INJ-0025: لا تُشغَّل من المتصفّح\n";
    $out = substr($src, 0, $at) . $ins . substr($src, $at);

    /* حارسُ البناء: لا يُكتب ما لا يُحلَّل */
    $tmp = sys_get_temp_dir() . '/cronguard_' . getmypid() . '.php';
    file_put_contents($tmp, $out);
    $lint = array(); $rc = 1;
    @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $lint, $rc);
    @unlink($tmp);
    if ($rc !== 0) {
        $skipped[] = $rel . ' (فشلَ الفحصُ النحويّ)';
        echo "  ✘ لم يُكتب — الفحصُ النحويُّ رسب: {$rel}\n";
        continue;
    }

    if ($RUN) { file_put_contents($abs, $out); }
    $done++;
    echo '  ' . ($RUN ? '✔' : '·') . " {$rel}\n";
}

echo "\n  " . ($RUN ? 'أُضيف' : 'سيُضاف') . ": {$done} · يحمله سلفًا: {$already}"
   . (count($skipped) ? ' · تُخُطّي: ' . count($skipped) : '') . "\n";
if (!$RUN) { echo "  (جافٌّ — أعد التشغيلَ بـ`--run` للكتابة)\n"; }
