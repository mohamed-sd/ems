<?php
/**
 * tools/u12_denial_destination.php — وجهةُ المنعِ تتبع سببَه
 * ═══════════════════════════════════════════════════════════════════════════
 * كشفَ المسحُ الحيُّ (u12_screen_smoke) أنَّ إحدى وثلاثين شاشةً ترتدُّ بمستخدمٍ
 * داخلٍ إلى شاشةِ الدخول. والسببُ ليس انتهاءَ جلسةٍ بل نقصَ صلاحية — فالوجهةُ
 * تكذب على المستخدم: يظنُّ أنّه خرج، وهو في الحقيقةِ ممنوعٌ من شاشةٍ واحدة.
 *
 * القاعدةُ (UI-13): الوجهةُ تتبع السبب.
 *   GOV-PERM-403  نقصُ صلاحيةٍ لشاشةٍ بعينها ⇐ لوحةُ المستخدم، فمعه طريقُ رجوع.
 *   GOV-SCOPE-403 لا بيئةَ شركةٍ صالحة      ⇐ شاشةُ الدخول (الجلسةُ نفسُها لا تصلح).
 *   ما عداهما                               ⇐ لا يُمسّ.
 *
 * لا يُمسُّ إلا نداءُ ems_gov_flash_redirect الذي وجهتُه login.php ورمزُه
 * GOV-PERM-403 — وهو النداءُ الذي أثبت المسحُ الحيُّ خطأَ وجهتِه.
 *
 * التشغيل: php tools/u12_denial_destination.php [--dry]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);
$BACKUP = $ROOT . '/storage/backups/u12_denialdest_' . date('Ymd_His');
if (!$dry) { @mkdir($BACKUP, 0777, true); }

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

$php = PHP_BINARY;
$files = 0; $calls = 0; $revert = 0; $locked = 0;

foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
        if (strpos($src, 'insidebar') === false) { continue; }
        if (strpos($src, 'login.php') === false) { continue; }

        $orig = $src;
        $n = 0;
        /* الوجهةُ نصٌّ حرفيٌّ ينتهي بـlogin.php، والرمزُ GOV-PERM-403 */
        $src = preg_replace_callback(
            "~ems_gov_flash_redirect\(\s*'((?:\.\./)*)login\.php'\s*,(.*?),\s*'GOV-PERM-403'~su",
            function ($m) use (&$n) {
                $n++;
                $up = $m[1] === '' ? './' : $m[1];
                return "ems_gov_flash_redirect('" . $up . "main/dashboard.php'," . $m[2] . ", 'GOV-PERM-403'";
            }, $src);

        if ($n === 0) { continue; }
        if ($dry) { $files++; $calls += $n; continue; }
        if (!is_writable($f)) { $locked++; continue; }

        @copy($f, $BACKUP . '/' . str_replace(array('/', '\\'), '__', $rel));
        if (@file_put_contents($f, $src) === false) { $locked++; continue; }
        $lint = array(); $rc = 0;
        exec('"' . $php . '" -l ' . escapeshellarg($f) . ' 2>&1', $lint, $rc);
        if ($rc !== 0) { file_put_contents($f, $orig); $revert++; continue; }
        $files++; $calls += $n;
    }
}

echo 'وجهةُ المنعِ تتبع سببَه — نقصُ الصلاحيةِ يعود للوحة' . ($dry ? '  [تشغيلٌ جافّ]' : '') . "\n";
echo str_repeat('═', 58), "\n";
echo "ملفاتٌ عولجت: {$files}\n";
echo "نداءاتٌ أُعيدت وجهتُها: {$calls}\n";
echo "تراجعٌ: {$revert}  ·  مقفلٌ: {$locked}\n";
if (!$dry) { echo "\nالنسخُ الاحتياطية: " . substr($BACKUP, strlen($ROOT) + 1) . "\n"; }
exit(0);
