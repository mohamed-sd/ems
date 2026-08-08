<?php
/**
 * tools/u12_flash_recode.php — ضبطُ رمزِ الرسالةِ بعد الترحيل
 * ═══════════════════════════════════════════════════════════════════════════
 * المُرحِّلُ الأولُ اشتقَّ الرموزَ بجدولٍ ناقصٍ لا يعرف «❌» فأعطى رسائلَ فشلٍ
 * رمزَ خبرٍ (GOV-INFO-200). واللونُ في الحاملِ المركزيِّ يتبع الرمزَ لا النصَّ،
 * فرمزٌ خاطئٌ يعني لونًا خاطئًا — والمستخدمُ يقرأ الحكمَ من اللونِ أولًا.
 * هذه الأداةُ تعيد اشتقاقَ الرمزِ لكلِّ نداءٍ رسالتُه نصٌّ حرفيّ، وتنظّف
 * وصلَ النصِّ الفارغِ («'' . basename(...)») الذي خلّفه التفكيك.
 *
 * التشغيل: php tools/u12_flash_recode.php [--dry]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports','includes');

/** جدولُ الرموزِ الكاملُ — النجاحُ أولًا ثم الحوكمةُ ثم الفشلُ ثم الخبر */
function rc_code($m)
{
    $m = (string) $m;
    if ($m === '') { return 'GOV-INFO-200'; }
    if (mb_strpos($m, '✅') !== false || mb_strpos($m, 'تمّ') !== false
        || mb_strpos($m, 'تم ') !== false || mb_strpos($m, 'حُفظ') !== false) { return 'GOV-OK-200'; }
    if (mb_strpos($m, 'صلاحي') !== false || mb_strpos($m, 'مصرح') !== false
        || mb_strpos($m, 'مصرّح') !== false) { return 'GOV-PERM-403'; }
    if (mb_strpos($m, 'نطاق') !== false || mb_strpos($m, 'بيئة شركة') !== false) { return 'GOV-SCOPE-403'; }
    if (mb_strpos($m, 'غير موجود') !== false || mb_strpos($m, 'غير صحيح') !== false
        || mb_strpos($m, 'لم يُعثر') !== false) { return 'GOV-REF-404'; }
    if (mb_strpos($m, 'تعذّر') !== false || mb_strpos($m, 'تعذر') !== false
        || mb_strpos($m, 'فشل') !== false || mb_strpos($m, 'خطأ') !== false
        || mb_strpos($m, '❌') !== false) { return 'GOV-FAIL-409'; }
    return 'GOV-INFO-200';
}

$php = PHP_BINARY;
$files = 0; $fixed = 0; $cleaned = 0; $reverted = 0;
$BACKUP = $ROOT . '/storage/backups/u12_recode_' . date('Ymd_His');
if (!$dry) { @mkdir($BACKUP, 0777, true); }

foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        if (strpos($src, 'ems_gov_flash_redirect') === false) { continue; }
        $orig = $src;
        $nFix = 0; $nCln = 0;

        /* الرسالةُ نصٌّ حرفيٌّ مفردُ الاقتباس ⇒ الرمزُ يُشتق منها */
        $src = preg_replace_callback(
            "~ems_gov_flash_redirect\(\s*(.*?),\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'(GOV-[A-Z]+-\d+)'~su",
            function ($m) use (&$nFix) {
                $msg = str_replace(array("\\'", '\\\\'), array("'", '\\'), $m[2]);
                $want = rc_code($msg);
                if ($want === $m[3]) { return $m[0]; }
                $nFix++;
                return "ems_gov_flash_redirect(" . $m[1] . ", '" . $m[2] . "', '" . $want . "'";
            }, $src);

        /* وصلُ نصٍّ فارغٍ خلّفه التفكيك */
        $src = preg_replace_callback(
            "~ems_gov_flash_redirect\(\s*''\s*\.\s*~su",
            function ($m) use (&$nCln) { $nCln++; return 'ems_gov_flash_redirect('; }, $src);

        if ($nFix === 0 && $nCln === 0) { continue; }
        $files++; $fixed += $nFix; $cleaned += $nCln;
        if ($dry) { continue; }

        @copy($f, $BACKUP . '/' . str_replace(array('/', '\\'), '__', substr($f, strlen($ROOT) + 1)));
        file_put_contents($f, $src);
        $lint = array(); $rc = 0;
        exec('"' . $php . '" -l ' . escapeshellarg($f) . ' 2>&1', $lint, $rc);
        if ($rc !== 0) { file_put_contents($f, $orig); $reverted++; $files--; $fixed -= $nFix; $cleaned -= $nCln; }
    }
}

echo 'ضبطُ رمزِ رسالةِ الحوكمة' . ($dry ? '  [تشغيلٌ جافّ]' : '') . "\n";
echo str_repeat('═', 50), "\n";
echo "ملفاتٌ مُسّت: {$files}\n";
echo "رموزٌ صُحّحت: {$fixed}\n";
echo "وصلُ فراغٍ نُظّف: {$cleaned}\n";
echo "تراجعٌ: {$reverted}\n";
exit(0);
