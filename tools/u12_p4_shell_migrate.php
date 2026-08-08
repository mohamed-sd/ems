<?php
/**
 * tools/u12_p4_shell_migrate.php — P4: ترحيلُ الشاشاتِ إلى الغلافِ الحاكم
 * ═══════════════════════════════════════════════════════════════════════════
 * المرجع: UXR-01 §٤-٤ المرحلةُ P4 «ترحيلُ الشاشاتِ الباقية» · وMD-04 بوابةُ
 * التبنّي G5-B «أتستدعي الشاشاتُ المكوناتَ فعلًا؟» — وهي البوابةُ الحقيقية.
 *
 * ما يفعله: يحقن بذرَ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ في كلِّ شاشةٍ حيةٍ
 * تفتقده — سطرين قبلَ ترويسةِ الصفحةِ مباشرةً، بالصلاحياتِ المحلولةِ إن وُجدت:
 *   require_once …/includes/screen_contract.php;
 *   ems_shell_axes(<المرجعُ المحليُّ للصلاحيات>);
 * فتصير الشاشةُ تعرف حالتَها الخمسَ (بيانات · صلاحية · تحرير · اتصال · حداثة)
 * ويقرؤها EmsUI بدل أن يجمعها كلُّ مبرمجٍ بطريقته.
 *
 * الحارساتُ (لا كسرَ لشاشةٍ عاملة):
 *   · لا يمسُّ ملفًا يحقن سلفًا · ولا ملفًا بلا insidebar (ليس شاشةً حية).
 *   · لا يمسُّ معالجًا (actions/api) ولا ملفًا بلا ترويسةِ صفحة.
 *   · يفحص صحةَ الصياغةِ بعد كلِّ حقنٍ ويتراجع عن الملفِ إن فسد.
 *   · نسخةٌ احتياطيةٌ لكل ملفٍ مُسّ قبل المساس.
 *
 * التشغيل: php tools/u12_p4_shell_migrate.php [--dry] [--limit=N]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$dry = in_array('--dry', $argv, true);
$limit = 0;
foreach ($argv as $a) { if (preg_match('/^--limit=(\d+)$/', $a, $m)) { $limit = (int) $m[1]; } }

$BACKUP = $ROOT . '/storage/backups/u12_p4_' . date('Ymd_His');
if (!$dry) { @mkdir($BACKUP, 0777, true); }

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

$files = array();
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) { $files[] = $f; }
}

$php = PHP_BINARY;
$stat = array('scanned' => 0, 'already' => 0, 'skipped' => 0, 'migrated' => 0, 'reverted' => 0);
$skipReasons = array();

foreach ($files as $f) {
    if ($limit > 0 && $stat['migrated'] >= $limit) { break; }
    $src = (string) file_get_contents($f);
    $rel = substr($f, strlen($ROOT) + 1);

    if (strpos($src, 'insidebar') === false) { continue; }   // ليست شاشةً حية
    $stat['scanned']++;

    if (strpos($src, 'ems_shell_axes') !== false
        || strpos($src, 'dept_risk_space.php') !== false
        || strpos($src, 'dept_gov_space.php') !== false
        || strpos($src, 'fin_analysis_shell.php') !== false) {
        $stat['already']++;
        continue;
    }

    /* موضعُ الحقن: قبلَ أولِ تضمينٍ للترويسةِ أو السايدبار — فالمحاورُ تُبذر
       قبل التصيير. والتضمينُ قد يقع في وسطِ سطرٍ لا في أوله (نمطٌ شائعٌ في
       الحوزة: `$page_title="…"; include '../inheader.php';`) فالمطابقةُ عامةٌ
       لا مُلزَمةٌ ببدايةِ السطر. */
    $pos = null; $indent = '';
    foreach (array('inheader\.php', 'insidebar\.php') as $needle) {
        if (preg_match('~(^[ \t]*)?(include|require)(_once)?[ \t]*\(?[ \t]*[\'"][^\'"]*' . $needle . '[\'"]~mi',
            $src, $mm, PREG_OFFSET_CAPTURE)) {
            $pos = $mm[0][1];
            $indent = isset($mm[1][0]) ? $mm[1][0] : '';
            break;
        }
    }
    if ($pos === null) {
        $stat['skipped']++;
        $skipReasons[] = $rel . ' — بلا تضمينِ ترويسةٍ ولا سايدبار';
        continue;
    }
    /* إن كان التضمينُ في وسطِ سطرٍ فالحقنُ يبدأ بسطرٍ جديدٍ لئلا يلتصق بما قبله */
    $atLineStart = ($pos === 0) || ($src[$pos - 1] === "\n");
    $lead = $atLineStart ? '' : "\n";

    /* المرجعُ المحليُّ للصلاحيات — أشهرُ أربعةِ أسماءٍ في الحوزة، وإلا null */
    $permVar = 'null';
    foreach (array('$__pp', '$perms', '$permissions', '$pp') as $cand) {
        if (strpos($src, $cand) !== false) {
            $permVar = "isset({$cand}) ? {$cand} : null";
            break;
        }
    }

    /* عمقُ المسارِ النسبيِّ للجذر (شاشاتُ الجذرِ الأولِ كلُّها في مجلدٍ واحد) */
    $inject = $lead . $indent . "// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير\n"
        . $indent . "require_once __DIR__ . '/../includes/screen_contract.php';\n"
        . $indent . "ems_shell_axes({$permVar});\n" . $indent;

    $out = substr($src, 0, $pos) . $inject . substr($src, $pos);

    if ($dry) { $stat['migrated']++; continue; }

    @copy($f, $BACKUP . '/' . str_replace(array('/', '\\'), '__', $rel));
    file_put_contents($f, $out);

    /* حارسُ الصياغة: أيُّ فسادٍ يُرجع الملفَ كما كان */
    $lintOut = array(); $rc = 0;
    exec('"' . $php . '" -l ' . escapeshellarg($f) . ' 2>&1', $lintOut, $rc);
    if ($rc !== 0) {
        file_put_contents($f, $src);
        $stat['reverted']++;
        $skipReasons[] = $rel . ' — تراجعٌ: ' . trim(implode(' ', $lintOut));
        continue;
    }
    $stat['migrated']++;
}

echo "P4 — ترحيلُ الشاشاتِ إلى الغلافِ الحاكم" . ($dry ? '  [تشغيلٌ جافّ]' : '') . "\n";
echo str_repeat('═', 62), "\n";
echo "شاشاتٌ حيةٌ مفحوصة: {$stat['scanned']}\n";
echo "تبذر الغلافَ سلفًا: {$stat['already']}\n";
echo "رُحّلت الآن: {$stat['migrated']}\n";
echo "تُخطّيت (بلا ترويسة): {$stat['skipped']}\n";
echo "تراجعٌ لفسادِ صياغة: {$stat['reverted']}\n";
if ($skipReasons) {
    echo "\nالتفصيل:\n";
    foreach (array_slice($skipReasons, 0, 25) as $s) { echo '  · ' . $s . "\n"; }
    if (count($skipReasons) > 25) { echo '  … و' . (count($skipReasons) - 25) . " أخرى\n"; }
}
if (!$dry) { echo "\nالنسخُ الاحتياطية: " . substr($BACKUP, strlen($ROOT) + 1) . "\n"; }
$total = $stat['already'] + $stat['migrated'];
echo "\nالتبنّي بعد الترحيل: {$total}/{$stat['scanned']}"
   . ($stat['scanned'] > 0 ? ' = ' . round($total / $stat['scanned'] * 100, 1) . '٪' : '') . "\n";
exit(0);
