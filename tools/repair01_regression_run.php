<?php
/**
 * tools/repair01_regression_run.php — الانحدارُ الشاملُ ببصمةِ لقطةٍ واحدة
 * ═══════════════════════════════════════════════════════════════════════════
 * **البندُ ⑧ من أمرِ المالك 2026-08-27** · وقاعدتُه الدستوريّةُ في البند ⑬:
 * «**لا يجوز تعديلُ النظامِ أثناءَ نافذةِ قياسٍ رسميّةٍ تُستخدم لإصدارِ
 * `Baseline` أو تقريرِ إغلاق**»، **وكلُّ `Measurement Report` يحمل**
 * `Snapshot ID` · `Commit Hash` · `Schema Version` · `Measured At` ·
 * `Tool Version`.
 *
 * ◆ **ولذلك تُلتقط البصمةُ قبلَ التشغيلِ وبعدَه**: فإن تغيّرت الشجرةُ أثناءَ
 *   الجولةِ **فالتقريرُ لا يمثّل أيَّ نسخةٍ فعليّةٍ من النظام** — ويُرسَّب
 *   ⛔ ولا يُنشَر.
 *
 * ◆ **والنطاقُ يُعلَن ولا يُدَّعى شمولُه**: يُشغَّل **كلُّ** حاجبِ موجةٍ وسقّاطةٍ
 *   وفاحصٍ في العائلاتِ المسمّاة، **ويُطبع ما لم يُشغَّل** — فتقريرٌ يقول
 *   «الكلُّ أخضر» بلا مقامٍ معلَنٍ دعوى لا قياس.
 *
 * ⛔ **ولا يُصلح هذا المُشغِّلُ شيئًا**: يقرأ ويُحصي. والإصلاحُ أثناءَ نافذةِ
 *   القياسِ هو بعينِه ما ينهى عنه البند ⑬.
 *
 * التشغيل: php tools/repair01_regression_run.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);

/* ═══ بصمةُ اللقطة — تُقرأ لا تُكتب ═══════════════════════════════════════ */
$git = function ($args) use ($ROOT) {
    $o = array();
    exec('git -C ' . escapeshellarg($ROOT) . ' ' . $args . ' 2>&1', $o);
    return trim(implode(' ', $o));
};
$fingerprint = function () use ($git, $conn) {
    $tbl = (int) $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetch_row()[0];
    $col = (int) $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA = DATABASE()")->fetch_row()[0];
    $reg = (int) $conn->query("SELECT COUNT(*) FROM repair01_screen_registry")->fetch_row()[0];
    return array(
        'commit' => $git('rev-parse HEAD'),
        'branch' => $git('rev-parse --abbrev-ref HEAD'),
        'dirty'  => ($git('status --porcelain') !== ''),
        'schema' => $tbl . 'T/' . $col . 'C',
        'registry' => $reg,
    );
};
$before = $fingerprint();
$startedAt = date('Y-m-d H:i:s');

echo "\n═══ الانحدارُ الشامل — البندُ ⑧ ═══\n";
printf("  الالتزام: %s (%s)\n", substr($before['commit'], 0, 8), $before['branch']);
printf("  المخطَّط: %s · سجلُّ الشاشات: %d\n", $before['schema'], $before['registry']);
printf("  البدء: %s\n", $startedAt);
if ($before['dirty']) {
    echo "\n⛔ **الشجرةُ غيرُ نظيفة** — ونافذةُ القياسِ تشترط شجرةً مُلزَمة.\n";
    echo "   والتقريرُ الصادرُ عن شجرةٍ متغيّرةٍ **لا يمثّل أيَّ نسخةٍ فعليّة**.\n";
    exit(2);
}

/* ═══ النطاق: عائلاتٌ مسمّاةٌ ومقامُها مُعلَن ═══════════════════════════════ */
$FAM = array(
    'بوّاباتُ الموجات'   => 'tools/repair01_w*_gate.php',
    'حواجبُ الحملة'      => 'tools/repair01_w135_gate.php',
    'سقّاطاتُ الدَّين'    => 'tools/u12_debt_ratchet.php',
    'حواجبُ الواجهة'     => 'tools/uxw_gates.php',
    'شواهدُ W13.5'      => 'tests/w135_*.php',
    'شواهدُ الحملة'      => 'tests/edc_*.php',
);
$rows = array(); $pass = 0; $fail = 0; $err = 0;
foreach ($FAM as $fam => $glob) {
    foreach (glob($ROOT . '/' . $glob) as $f) {
        $rel = substr(strtr($f, DIRECTORY_SEPARATOR, '/'), strlen(strtr($ROOT, DIRECTORY_SEPARATOR, '/')) + 1);
        $o = array(); $rc = 0;
        exec('"' . PHP_BINARY . '" ' . escapeshellarg($f) . ' 2>&1', $o, $rc);
        $txt = implode("\n", $o);
        /* ◆ **رمزُ الخروجِ وحدَه لا يكفي**: أدواتٌ تطبع «ساقطة» وتخرج صفرًا.
             فيُقرأ الحكمُ المطبوعُ أيضًا — **ورسوبٌ بأيِّ الوجهَين رسوب**. */
        $said = (bool) preg_match('~(✘|ساقط|حمراء|رسب\s*[1-9]|لم يعبر|دَينٌ زاد)~u', $txt);
        $v = ($rc === 0 && !$said) ? 'PASS' : (($rc > 1) ? 'ERROR' : 'FAIL');
        if ($v === 'PASS') { $pass++; } elseif ($v === 'ERROR') { $err++; } else { $fail++; }
        /* سطرُ الحكمِ الأخيرُ — دليلٌ لا زينة */
        $last = '';
        foreach (array_reverse($o) as $l) {
            if (preg_match('~(الحكم|النتيجة|gate:|السقّاطة|مجتاز)~u', $l)) { $last = trim($l); break; }
        }
        $rows[] = array($fam, $rel, $v, $rc, mb_substr($last, 0, 110));
    }
}

$after = $fingerprint();
$endedAt = date('Y-m-d H:i:s');
$stable = ($before['commit'] === $after['commit'] && $before['schema'] === $after['schema'] && !$after['dirty']);

echo "\n";
$curFam = '';
foreach ($rows as $x) {
    if ($x[0] !== $curFam) { $curFam = $x[0]; echo "── $curFam ──\n"; }
    printf("  %s %-46s %s\n",
        ($x[2] === 'PASS' ? '✔' : ($x[2] === 'ERROR' ? '⚠' : '✘')), $x[1], $x[4]);
}

echo "\n────────────────────────────────────────────────────────────────────\n";
printf("**نجح %d · رسب %d · عطبٌ في التشغيل %d · المجموع %d**\n", $pass, $fail, $err, count($rows));
printf("انتهى: %s\n", $endedAt);
if (!$stable) {
    echo "⛔ **البصمةُ تغيّرت أثناءَ الجولة** — والتقريرُ لا يمثّل نسخةً واحدة.\n";
    printf("   قبل: %s / %s · بعد: %s / %s · متّسخة: %s\n",
        substr($before['commit'], 0, 8), $before['schema'],
        substr($after['commit'], 0, 8), $after['schema'], $after['dirty'] ? 'نعم' : 'لا');
} else {
    echo "✔ **البصمةُ ثابتةٌ من البدءِ إلى الختام** — التقريرُ يمثّل نسخةً واحدةً بعينِها.\n";
}

if ($MD) {
    $sid = 'SNAP-' . substr($before['commit'], 0, 8) . '-' . str_replace(array('-', ' ', ':'), '', $startedAt);
    $o  = "# تقريرُ الانحدارِ الشامل — البندُ ⑧\n\n";
    $o .= "> ⛔ **مولَّدٌ من التشغيلِ الحيّ**: `php tools/repair01_regression_run.php --md`\n\n";
    $o .= "## بصمةُ اللقطة (‏البند ⑬)\n\n| الحقل | القيمة |\n|---|---|\n";
    $o .= "| `Snapshot ID` | `$sid` |\n";
    $o .= "| `Commit Hash` | `{$before['commit']}` |\n";
    $o .= "| `Branch` | `{$before['branch']}` |\n";
    $o .= "| `Schema Version` | `{$before['schema']}` |\n";
    $o .= "| `Registry Version` | {$before['registry']} صفًّا |\n";
    $o .= "| `Measured At` | $startedAt ← $endedAt |\n";
    $o .= "| `Tool Version` | `repair01_regression_run.php` |\n";
    $o .= "| **ثباتُ البصمة** | " . ($stable ? '✔ ثابتةٌ من البدءِ إلى الختام' : '⛔ **تغيّرت — التقريرُ لا يمثّل نسخةً واحدة**') . " |\n\n";
    $o .= sprintf("**نجح %d · رسب %d · عطبٌ في التشغيل %d · المجموع %d**\n\n", $pass, $fail, $err, count($rows));
    $curFam = '';
    foreach ($rows as $x) {
        if ($x[0] !== $curFam) { $curFam = $x[0]; $o .= "\n## $curFam\n\n| الأداة | الحكم | الدليل |\n|---|---|---|\n"; }
        $o .= sprintf("| `%s` | %s | %s |\n", $x[1],
            ($x[2] === 'PASS' ? '✔' : ($x[2] === 'ERROR' ? '⚠ عطب' : '✘ رسب')), str_replace('|', '·', $x[4]));
    }
    @mkdir($ROOT . '/docs/REPAIR01_20260823', 0777, true);
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/REGRESSION_REPORT.md', $o);
    echo "✔ كُتب docs/REPAIR01_20260823/REGRESSION_REPORT.md\n";
}
exit(($fail + $err) === 0 && $stable ? 0 : 1);
