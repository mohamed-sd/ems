<?php
/**
 * tools/u12_bare_denial.php — المنعُ العاري يصير حكمًا محكومًا
 * ═══════════════════════════════════════════════════════════════════════════
 * كشفَ المسحُ الحيُّ بستةِ أدوارٍ أنَّ إحدى عشرةَ شاشةً تردُّ صفحةً عاريةً عند
 * المنع: نصٌّ بلا قشرةٍ ولا رمزٍ ولا طريقِ رجوع — أو نافذةَ alert من المتصفحِ
 * تقفز بالمستخدمِ بلا أثر. وكلاهما يخالف UI-13: «ما حدث + كيف يُصحَّح + رمزُ
 * الخطأ» داخلَ الشاشة.
 *
 * شكلان يُعالَجان (وحدَهما — لا يُمسُّ حارسُ كتابةٍ في مسارِ POST):
 *   ① http_response_code(403); exit('403 — نصّ');
 *   ② echo "<script>alert('نصّ'); window.location.href='وجهة';</script>"; exit();
 *
 * التشغيل: php tools/u12_bare_denial.php [--dry]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);
$BACKUP = $ROOT . '/storage/backups/u12_baredenial_' . date('Ymd_His');
if (!$dry) { @mkdir($BACKUP, 0777, true); }

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

$php = PHP_BINARY;
$files = 0; $n403 = 0; $nAlert = 0; $revert = 0; $locked = 0;
$touched = array();

foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
        if (strpos($src, 'insidebar') === false) { continue; }
        $orig = $src;
        $a = 0; $b = 0;

        /* ① منعٌ عارٍ برمز 403 — الوجهةُ لوحةُ المستخدمِ ومعه سببٌ ورمز.
              يُمسك الشكلان: exit('403 — …') وdie('…') بعد http_response_code(403). */
        $src = preg_replace_callback(
            "~http_response_code\s*\(\s*403\s*\)\s*;\s*(?:die|exit)\s*\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*\)\s*;~su",
            function ($m) use (&$a, $rel) {
                $a++;
                $why = trim(str_replace(array("\\'", '\\\\'), array("'", '\\'), $m[1]));
                $up = str_repeat('../', substr_count($rel, '/'));
                return "ems_gov_flash_redirect('" . $up . "main/dashboard.php', '"
                    . str_replace("'", "\\'", $why) . " ❌', 'GOV-PERM-403', "
                    . "'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');";
            }, $src);

        $src = preg_replace_callback(
            "~(?:http_response_code\s*\(\s*403\s*\)\s*;\s*)?exit\s*\(\s*'403\s*—\s*((?:[^'\\\\]|\\\\.)*)'\s*\)\s*;~su",
            function ($m) use (&$a, $rel) {
                $a++;
                $why = trim(str_replace(array("\\'", '\\\\'), array("'", '\\'), $m[1]));
                $up = str_repeat('../', substr_count($rel, '/'));
                return "ems_gov_flash_redirect('" . $up . "main/dashboard.php', '"
                    . str_replace("'", "\\'", $why) . " ❌', 'GOV-PERM-403', "
                    . "'اطلب المنحةَ من مدير الصلاحيات إن كانت ضمن عملك');";
            }, $src);

        /* ② نافذةُ المتصفحِ alert ثم قفزة — تصير رسالةً محكومةً في الوجهة.
              الوجهةُ نصٌّ حرفيٌّ بلا وصلٍ: أمّا الوجهةُ المبنيةُ بوصلِ تعابيرَ
              (`'oprators.php" . ($p ? … : "") . "'`) فتُترك ليدٍ بشريةٍ — إذ لا
              تُفكَّك بأمانٍ، وتحويلُها خطأً أسوأُ من تركِها معلَنةً دَينًا. */
        $src = preg_replace_callback(
            '~echo\s+(["\'])<script>\s*alert\(\s*(["\'])([^"\']*)\2\s*\)\s*;\s*window\.location\.href\s*=\s*'
            . '(["\'])([^"\']*)\4\s*;?\s*</script>\1\s*;~su',
            function ($m) use (&$b) {
                $b++;
                $msg = trim($m[3]);
                $to = trim($m[5]);
                $code = (mb_strpos($msg, 'صلاحي') !== false) ? 'GOV-PERM-403' : 'GOV-SCOPE-403';
                return "ems_gov_flash_redirect('" . str_replace("'", "\\'", $to) . "', '"
                    . str_replace("'", "\\'", $msg) . "', '" . $code . "', '');";
            }, $src);

        if ($a === 0 && $b === 0) { continue; }
        if (strpos($src, 'permissions_helper.php') === false) {
            $src = preg_replace('~(<\?php\s*\n)~', "$1require_once __DIR__ . '/../includes/permissions_helper.php';\n", $src, 1);
        }
        if ($dry) { $files++; $n403 += $a; $nAlert += $b; $touched[] = $rel; continue; }
        if (!is_writable($f)) { $locked++; continue; }

        @copy($f, $BACKUP . '/' . str_replace(array('/', '\\'), '__', $rel));
        if (@file_put_contents($f, $src) === false) { $locked++; continue; }
        $lint = array(); $rc = 0;
        exec('"' . $php . '" -l ' . escapeshellarg($f) . ' 2>&1', $lint, $rc);
        if ($rc !== 0) {
            file_put_contents($f, $orig);
            $revert++;
            $touched[] = '✘ ' . $rel . ' — تراجعٌ: ' . trim(implode(' ', $lint));
            continue;
        }
        $files++; $n403 += $a; $nAlert += $b; $touched[] = $rel;
    }
}

echo 'المنعُ العاري يصير حكمًا محكومًا' . ($dry ? '  [تشغيلٌ جافّ]' : '') . "\n";
echo str_repeat('═', 52), "\n";
echo "ملفاتٌ عولجت: {$files}\n";
echo "منعٌ عارٍ 403 حُوّل: {$n403}\n";
echo "نافذةُ alert حُوّلت: {$nAlert}\n";
echo "تراجعٌ: {$revert}  ·  مقفلٌ: {$locked}\n";
foreach ($touched as $t) { echo '  · ' . $t . "\n"; }
if (!$dry) { echo "\nالنسخُ الاحتياطية: " . substr($BACKUP, strlen($ROOT) + 1) . "\n"; }
exit(0);
