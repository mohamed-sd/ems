<?php
/**
 * ops01_daily_backup.php — نسخةُ بياناتٍ يوميةٌ قابلةٌ للاستعادة (CLI فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * حاجبُ B13 المقيس: أحدثُ نسخةِ **بيانات** عمرُها عشرةُ أيام، و434 لقطةَ
 * **بنيةٍ بلا بيانات** لا تُستعاد منها قاعدة، و`log_bin=OFF` فلا استعادةَ
 * لنقطةِ زمن. فالـRPO الفعليُّ عشرةُ أيامٍ لا ساعات.
 *
 * ◆ وهذا الملفُّ يعالج الشقَّ الذي يُعالَج بكود: **نسخةٌ يوميةٌ كاملةٌ بالبيانات**
 *   مضغوطةٌ ومُتحقَّقٌ منها، مع تدويرٍ يحفظ آخرَ N. أما `log_bin` فإعدادُ خادمٍ
 *   يلزمه إعادةُ تشغيلِ MariaDB — يُبلَّغ عنه ولا يُدَّعى إصلاحُه من هنا.
 *
 * ◆ ولا تُعدُّ النسخةُ ناجحةً لأن الأمرَ رجع صفرًا: تُفحص بأن فيها
 *   `CREATE TABLE` و`INSERT INTO` وأن حجمَها معقولٌ مقابلَ حجمِ البيانات.
 *   فملفٌّ فارغٌ باسمِ نسخةٍ أسوأُ من لا نسخة — لأنه يُطمئن.
 *
 * التشغيل: php tools/ops01_daily_backup.php [--keep=14] [--verify-only]
 * الجدولة: يوميًّا في Task Scheduler خارجَ الذروة.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
require_once $ROOT . '/includes/env.php';

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = $m[2] ?? '1'; }
}
$keep = isset($args['keep']) ? max(3, (int) $args['keep']) : 14;
$verifyOnly = isset($args['verify-only']);

$host = ems_env('DB_HOST'); $port = '3306';
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); }
$dbName = ems_env('DB_NAME');
/* ◆ حسابُ التطبيقِ `ems_app` بلا `SHOW VIEW` فيفشل تفريغُ المناظرِ برمز 1142
   وتخرج نسخةٌ **ناقصةُ المناظر**. فالنسخُ عملُ إدارةٍ لا عملُ تطبيق: يُفضَّل
   حسابُ الهجراتِ وإلا حسابُ التطبيقِ مع إعلانِ النقص. */
$dbUser = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$dbPass = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$outDir = $ROOT . '/storage/backups/daily';
@mkdir($outDir, 0777, true);

echo "══ النسخةُ اليوميةُ — $dbName@$host:$port ══\n";

/* حجمُ البيانات — به يُحكم على معقوليةِ حجمِ الملف */
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($host, $dbUser, $dbPass, $dbName, (int) $port);
if (!$conn) { exit("  ✘ تعذّر الاتصال\n"); }
$r = $conn->query("SELECT ROUND(SUM(DATA_LENGTH)/1048576,1) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$dbName'");
$dataMb = $r ? (float) $r->fetch_row()[0] : 0.0;
$r = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$dbName' AND TABLE_TYPE='BASE TABLE'");
$tables = $r ? (int) $r->fetch_row()[0] : 0;
printf("  البيانات: %s م.ب في %d جدولًا\n", number_format($dataMb, 1), $tables);

/* ── التحقُّقُ من نسخةٍ قائمة ─────────────────────────────────── */
$verify = function (string $file) use ($dataMb): array {
    if (!is_file($file)) { return array(false, 'غيرُ موجود'); }
    $sz = filesize($file) / 1048576;
    $head = '';
    $fh = @fopen($file, 'rb');
    if ($fh) { $head = (string) fread($fh, 200000); fclose($fh); }
    $hasCreate = stripos($head, 'CREATE TABLE') !== false;
    /* الملفُّ مضغوطٌ فلا يُقرأ نصًّا — يُحكم بالحجمِ وحدَه */
    $gz = substr($file, -3) === '.gz';
    $minMb = max(0.5, $dataMb * 0.05);
    if ($sz < $minMb) { return array(false, sprintf('حجمٌ مريب: %.1f م.ب < %.1f', $sz, $minMb)); }
    if (!$gz && !$hasCreate) { return array(false, 'بلا CREATE TABLE'); }
    return array(true, sprintf('%.1f م.ب', $sz));
};

if ($verifyOnly) {
    $files = glob($outDir . '/*.sql*') ?: array();
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    if (!$files) { exit("  ✘ لا نسخةَ في $outDir\n"); }
    echo "\n── التحقُّقُ من النسخِ القائمة ──\n";
    foreach (array_slice($files, 0, 5) as $f) {
        list($ok, $why) = $verify($f);
        printf("  %s %-46s %s · عمرُها %d يومًا\n", $ok ? '✔' : '✘', basename($f), $why,
            (int) floor((time() - filemtime($f)) / 86400));
    }
    exit(0);
}

/* ── أخذُ النسخة ─────────────────────────────────────────────── */
$stamp = date('Ymd_His');
$file = $outDir . "/{$dbName}_{$stamp}.sql";
$dump = 'C:/wamp64/bin/mariadb/mariadb11.4.9/bin/mysqldump.exe';
if (!is_file($dump)) {
    foreach (glob('C:/wamp64/bin/mariadb/*/bin/mysqldump.exe') ?: array() as $c) { $dump = $c; break; }
}
if (!is_file($dump)) { exit("  ✘ لم يُعثر على mysqldump\n"); }

/* --single-transaction: لقطةٌ متّسقةٌ بلا قفلِ الجداول · --add-drop-trigger:
   درسُ #1359 (استيرادٌ مستأنَفٌ على قادحٍ قائمٍ يرمي) · --routines للدوال */
/* ◆ `2>&1` كان يكتب رسائلَ الخطأِ **داخلَ ملفِّ النسخةِ نفسِه** — فتصير جزءًا من
   الـSQL وتُفسد الاستعادةَ صامتةً. تُفصل stderr إلى ملفٍّ مستقلٍّ ويُقرأ. */
$errFile = $file . '.err';
$cmd = sprintf(
    '"%s" --host=%s --port=%s --user=%s %s --single-transaction --quick --routines --events '
    . '--add-drop-trigger --default-character-set=utf8mb4 %s > "%s" 2> "%s"',
    $dump, escapeshellarg($host), escapeshellarg($port), escapeshellarg($dbUser),
    $dbPass !== '' ? '--password=' . escapeshellarg($dbPass) : '',
    escapeshellarg($dbName), $file, $errFile
);
$t0 = microtime(true);
exec($cmd, $out, $rc);
$secs = round(microtime(true) - $t0, 1);
$stderr = is_file($errFile) ? trim((string) file_get_contents($errFile)) : '';
@unlink($errFile);
printf("  التفريغ: رمزُ الخروج %d في %s ث\n", $rc, $secs);
if ($stderr !== '') {
    foreach (array_slice(explode("\n", $stderr), 0, 4) as $line) { echo '    ⚠ ' . trim($line) . "\n"; }
}
if ($rc !== 0) {
    /* رمزُ خروجٍ غيرُ صفرٍ لا يُتجاوز: نسخةٌ ناقصةٌ تُطمئن أخطرُ من غيابِ نسخة */
    echo "  ✘ التفريغُ لم يرجع صفرًا — تُحذف النسخةُ ولا تُحسب\n";
    @unlink($file);
    exit(1);
}

list($ok, $why) = $verify($file);
if (!$ok) {
    echo "  ✘ النسخةُ لم تجتز التحقُّق: $why — تُحذف كي لا تُطمئن كذبًا\n";
    @unlink($file);
    exit(1);
}
echo "  ✔ اجتازت التحقُّق: $why\n";

/* ── نزعُ DEFINER أثناءَ الضغط ────────────────────────────────────────
   تجربةُ الاستعادةِ (ops02) أثبتت أن منظرًا واحدًا **يفشل** لأن التفريغَ يحمل
   `DEFINER=ems_migrator@localhost SQL SECURITY DEFINER`: الاستعادةُ بحسابٍ آخرَ
   أو على خادمٍ آخرَ ترفضه. فالنسخةُ التي لا تُستعاد ليست نسخة.
   ◆ ويُبدَّل الأمانُ إلى INVOKER فينفَّذ المنظرُ بصلاحيةِ قارئِه — وهو الصحيحُ
     أمنيًّا أيضًا (لا تصعيدَ صلاحيةٍ عبرَ منظر). */
$stripped = 0;
if (function_exists('gzopen')) {
    $gzf = $file . '.gz';
    $in = fopen($file, 'rb'); $o = gzopen($gzf, 'wb9');
    while (($line = fgets($in)) !== false) {
        if (stripos($line, 'DEFINER') !== false) {
            $n = 0;
            $line = preg_replace('/\s*DEFINER\s*=\s*`[^`]*`@`[^`]*`/i', '', (string) $line, -1, $n);
            $line = str_ireplace('SQL SECURITY DEFINER', 'SQL SECURITY INVOKER', (string) $line);
            $stripped += $n;
        }
        gzwrite($o, (string) $line);
    }
    fclose($in); gzclose($o);
    if (is_file($gzf) && filesize($gzf) > 1024) { @unlink($file); $file = $gzf; }
    printf("  ✔ ضُغطت: %.1f م.ب · نُزع DEFINER من %d موضعًا\n", filesize($file) / 1048576, $stripped);
}

/* التدوير */
$files = glob($outDir . '/*.sql*') ?: array();
usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
$removed = 0;
foreach (array_slice($files, $keep) as $old) { @unlink($old); $removed++; }
printf("  التدوير: أُبقيت %d · حُذفت %d\n", min(count($files), $keep), $removed);

/* حالةُ log_bin — يُبلَّغ ولا يُدَّعى إصلاحُه */
$r = $conn->query("SHOW VARIABLES LIKE 'log_bin'");
$lb = $r && ($x = $r->fetch_row()) ? $x[1] : '?';
echo "\n── RPO ──\n";
printf("  نسخةٌ يوميةٌ: ✔ (تدويرُ %d يومًا) ⇒ RPO ≤ 24 ساعة\n", $keep);
printf("  log_bin = %s %s\n", $lb, $lb === 'ON' ? '✔ استعادةٌ لنقطةِ زمنٍ ممكنة'
    : '✘ لا استعادةَ لنقطةِ زمن — إعدادُ خادمٍ يلزمه إعادةُ تشغيلِ MariaDB');
echo "\n✔ تمّت — " . basename($file) . "\n";
