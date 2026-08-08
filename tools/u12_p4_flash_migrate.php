<?php
/**
 * tools/u12_p4_flash_migrate.php — UI-DEF-06: صفرُ رسالةٍ في الرابط
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: UXR-01 · UI-13 «رسائلُ النظامِ داخلَ الشاشةِ برمزٍ محكوم — ولا رسالةَ
 * في شريطِ العنوان» · والعيبُ الحيُّ UI-DEF-06 «رسالةُ حوكمةٍ تظهر في شريط
 * العنوانِ ولا تُعرض في الشاشة — الحكمُ ضاع والمستخدمُ لا يراه».
 *
 * ما يفعله: يحوّل كلَّ تحويلٍ يحمل رسالةً في الرابط
 *     header("Location: X?msg=" . urlencode('نصّ')); exit();
 * إلى حاملِ الرسائلِ داخلَ الشاشةِ برمزٍ محكوم:
 *     ems_gov_flash_redirect('X', 'نصّ', 'رمز', '');
 * ويضمن تضمينَ permissions_helper (موضعُ الدالة) قبل الاستعمال.
 *
 * الحارساتُ: نسخةٌ احتياطيةٌ لكل ملف · فحصُ صياغةٍ بعد كلِّ تحويلٍ وتراجعٌ
 * عند الفساد · ولا يمسُّ تحويلًا بلا رسالة.
 *
 * التشغيل: php tools/u12_p4_flash_migrate.php [--dry]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);
$BACKUP = $ROOT . '/storage/backups/u12_flash_' . date('Ymd_His');
if (!$dry) { @mkdir($BACKUP, 0777, true); }

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

/** رمزٌ محكومٌ يُشتق من نصِّ الرسالةِ — فالمستخدمُ يرى سببًا لا نصًّا عائمًا */
function flash_code($msg)
{
    $m = (string) $msg;
    if (mb_strpos($m, 'صلاحي') !== false) { return 'GOV-PERM-403'; }
    if (mb_strpos($m, 'غير موجود') !== false || mb_strpos($m, 'غير صحيح') !== false) { return 'GOV-REF-404'; }
    if (mb_strpos($m, 'نطاق') !== false) { return 'GOV-SCOPE-403'; }
    if (mb_strpos($m, '✅') !== false || mb_strpos($m, 'تم ') !== false) { return 'GOV-OK-200'; }
    if (mb_strpos($m, 'تعذّر') !== false || mb_strpos($m, 'تعذر') !== false) { return 'GOV-FAIL-409'; }
    return 'GOV-INFO-200';
}

$php = PHP_BINARY;
$stat = array('files' => 0, 'converted' => 0, 'reverted' => 0, 'left' => 0);
$leftDetail = array();

foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        $rel = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
        if (strpos($src, 'insidebar') === false) { continue; }
        if (strpos($src, '?msg=') === false) { continue; }

        $orig = $src;
        $n = 0;

        /* النمطُ الأولُ: header("Location: X?msg=" . urlencode('نصّ')); */
        $src = preg_replace_callback(
            '~header\s*\(\s*(["\'])Location:\s*([^"\'?]+)\?msg=\1\s*\.\s*(?:raw)?urlencode\s*\(\s*([\'"])(.*?)\3\s*\)\s*\)\s*;~su',
            function ($m) use (&$n) {
                $n++;
                $to = trim($m[2]);
                $msg = $m[4];
                $code = flash_code($msg);
                return "ems_gov_flash_redirect('" . str_replace("'", "\\'", $to) . "', '"
                    . str_replace("'", "\\'", $msg) . "', '" . $code . "', '');";
            }, $src);

        /* النمطُ الثالث: header('Location: X?msg=' . urlencode($متغيّر)) — شائعٌ
           في دوالِّ الرجوعِ المساعدةِ (clm_back وأخواتها). النصُّ متغيّرٌ فيُمرَّر
           كما هو ويُعطى رمزًا عامًّا محكومًا. */
        $src = preg_replace_callback(
            '~header\s*\(\s*([\'"])Location:\s*([^\'"?]+)\?msg=\1\s*\.\s*(?:raw)?urlencode\s*\(\s*(\$[A-Za-z_][A-Za-z0-9_\[\]\'"->]*)\s*\)\s*\)\s*;~su',
            function ($m) use (&$n) {
                $n++;
                $to = trim($m[2]);
                return "ems_gov_flash_redirect('" . str_replace("'", "\\'", $to) . "', "
                    . $m[3] . ", 'GOV-INFO-200', '');";
            }, $src);

        /* النمطُ الثاني: header("Location: X?msg=نصٌّ+مُرمَّز"); */
        $src = preg_replace_callback(
            '~header\s*\(\s*(["\'])Location:\s*([^"\'?]+)\?msg=([^"\']*)\1\s*\)\s*;~su',
            function ($m) use (&$n) {
                $n++;
                $to = trim($m[2]);
                $msg = str_replace('+', ' ', urldecode($m[3]));
                $code = flash_code($msg);
                return "ems_gov_flash_redirect('" . str_replace("'", "\\'", $to) . "', '"
                    . str_replace("'", "\\'", $msg) . "', '" . $code . "', '');";
            }, $src);

        if ($n === 0) {
            $stat['left']++;
            $leftDetail[] = $rel . ' — نمطٌ غيرُ مطابقٍ يُراجَع يدويًّا';
            continue;
        }

        /* الدالةُ في permissions_helper — يُضمَّن إن غاب */
        if (strpos($src, 'permissions_helper.php') === false) {
            $src = preg_replace('~(<\?php\s*\n)~', "$1require_once __DIR__ . '/../includes/permissions_helper.php';\n", $src, 1);
        }
        /* exit(); بعد التحويلِ صار زائدًا — الدالةُ تخرج بنفسها، وبقاؤه غيرُ ضار */

        $stat['files']++;
        $stat['converted'] += $n;
        if ($dry) { continue; }

        if (!is_writable($f)) {
            $stat['left']++;
            $leftDetail[] = $rel . ' — ملفٌّ غيرُ قابلٍ للكتابة (مقفلٌ خارجيًّا) — يُعالَج لاحقًا';
            continue;
        }
        @copy($f, $BACKUP . '/' . str_replace(array('/', '\\'), '__', $rel));
        if (@file_put_contents($f, $src) === false) {
            $stat['left']++;
            $leftDetail[] = $rel . ' — تعذّرت الكتابة';
            continue;
        }
        $lint = array(); $rc = 0;
        exec('"' . $php . '" -l ' . escapeshellarg($f) . ' 2>&1', $lint, $rc);
        if ($rc !== 0) {
            file_put_contents($f, $orig);
            $stat['reverted']++;
            $stat['files']--;
            $stat['converted'] -= $n;
            $leftDetail[] = $rel . ' — تراجعٌ: ' . trim(implode(' ', $lint));
        }
    }
}

echo 'UI-DEF-06 — صفرُ رسالةٍ في الرابط' . ($dry ? '  [تشغيلٌ جافّ]' : '') . "\n";
echo str_repeat('═', 60), "\n";
echo "ملفاتٌ عولجت: {$stat['files']}\n";
echo "تحويلاتٌ حُوّلت لحاملِ الشاشة: {$stat['converted']}\n";
echo "تراجعٌ لفسادِ صياغة: {$stat['reverted']}\n";
echo "بلا نمطٍ مطابق: {$stat['left']}\n";
if ($leftDetail) {
    echo "\nالتفصيل:\n";
    foreach (array_slice($leftDetail, 0, 20) as $s) { echo '  · ' . $s . "\n"; }
}
if (!$dry) { echo "\nالنسخُ الاحتياطية: " . substr($BACKUP, strlen($ROOT) + 1) . "\n"; }
exit(0);
