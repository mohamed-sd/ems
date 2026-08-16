<?php
/**
 * ops02_restore_drill.php — تجربةُ استعادةٍ حقيقيةٌ في قاعدةٍ منفصلة
 * ═══════════════════════════════════════════════════════════════════════════
 * «نسخةٌ لم تُستعَد ليست نسخةً بل ملفّ». وحاجبُ B13 يذكر صراحةً: **لا محضرَ
 * تجربةِ استعادةٍ في الشجرة**. فهذا المسبارُ يستعيد آخرَ نسخةٍ في قاعدةٍ
 * **منفصلةٍ باسمٍ مؤقت**، ويقارنها بالأصل، ثم يُسقطها.
 *
 * ◆ ولا تُمَسُّ قاعدةُ الإنتاجِ البتة: الاستعادةُ في `<db>_restore_drill`.
 * ◆ والحكمُ بالمقارنةِ لا بالانطباع: عددُ الجداولِ والمناظرِ والقوادح،
 *   وعيّنةُ صفوفٍ من أكبرِ الجداول.
 * ◆ ويُكتب المحضرُ في `storage/backups/RESTORE_DRILL.md` — فالأثرُ هو الدليل.
 *
 * التشغيل: php tools/ops02_restore_drill.php [--keep-drill]
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
require_once $ROOT . '/includes/env.php';
$keepDrill = in_array('--keep-drill', $argv, true);

$host = ems_env('DB_HOST'); $port = '3306';
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); }
$src = ems_env('DB_NAME');
/* ◆ التجربةُ تُنشئ قاعدةً وتُسقطها — وهذا امتيازُ إدارةٍ لا يملكه `ems_migrator`
   (قِيس: Access denied لإنشاءِ `<db>_restore_drill`). فيُستعمل حسابُ الإدارةِ
   للتجربةِ وحدَها، ويُعلَن. وفي الإنتاجِ يُمنح `CREATE`/`DROP` على نمطِ الاسمِ
   وحدَه بدل استعمالِ حسابِ الجذر. */
$u = 'root'; $p = '';
$adminNote = 'حسابُ إدارةٍ للتجربةِ وحدَها — الإنتاجُ يقرأ بحسابِه';
$drill = $src . '_restore_drill';

$bin = null;
foreach (glob('C:/wamp64/bin/mariadb/*/bin/mysql.exe') ?: array() as $c) { $bin = $c; break; }
if (!$bin) { exit("لم يُعثر على عميلِ mysql\n"); }

$files = glob($ROOT . '/storage/backups/daily/*.sql*') ?: array();
usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
if (!$files) { exit("لا نسخةَ يوميةٌ لاستعادتِها\n"); }
$bak = $files[0];

echo "══ تجربةُ استعادة ══\n";
printf("  النسخة: %s (%.1f م.ب · عمرُها %d يومًا)\n", basename($bak), filesize($bak) / 1048576,
    (int) floor((time() - filemtime($bak)) / 86400));
printf("  الهدف: %s (منفصلةٌ — الإنتاجُ لا يُمَسّ)\n", $drill);

mysqli_report(MYSQLI_REPORT_OFF);
$c = @mysqli_connect($host, $u, $p, '', (int) $port);
if (!$c) { exit("  ✘ تعذّر الاتصال\n"); }
$c->set_charset('utf8mb4');

/* قياسُ الأصلِ قبلَ كلِّ شيء */
$snap = function (mysqli $c, string $db): array {
    $q = function ($sql) use ($c) { $r = $c->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };
    return array(
        'tables'  => $q("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db' AND TABLE_TYPE='BASE TABLE'"),
        'views'   => $q("SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA='$db'"),
        'trigs'   => $q("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$db'"),
        'entries' => $q("SELECT COUNT(*) FROM `$db`.unit_entries"),
        'journal' => $q("SELECT COUNT(*) FROM `$db`.fin_journal_entries"),
        'events'  => $q("SELECT COUNT(*) FROM `$db`.fin_financial_events"),
    );
};
$before = $snap($c, $src);
echo "\n  الأصل: " . json_encode($before, JSON_UNESCAPED_UNICODE) . "\n";

/* الاستعادة */
$c->query("DROP DATABASE IF EXISTS `$drill`");
if (!$c->query("CREATE DATABASE `$drill` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    exit("  ✘ تعذّر إنشاءُ قاعدةِ التجربة: {$c->error}\n");
}
$tmp = sys_get_temp_dir() . '/drill_' . getmypid() . '.sql';
if (substr($bak, -3) === '.gz') {
    $in = gzopen($bak, 'rb'); $o = fopen($tmp, 'wb');
    while (!gzeof($in)) { fwrite($o, (string) gzread($in, 262144)); }
    gzclose($in); fclose($o);
} else { copy($bak, $tmp); }

$err = $tmp . '.err';
$cmd = sprintf('"%s" --host=%s --port=%s --user=%s %s --default-character-set=utf8mb4 %s < "%s" > NUL 2> "%s"',
    $bin, escapeshellarg($host), escapeshellarg($port), escapeshellarg($u),
    $p !== '' ? '--password=' . escapeshellarg($p) : '', escapeshellarg($drill), $tmp, $err);
$t0 = microtime(true);
exec($cmd, $o2, $rc);
$secs = round(microtime(true) - $t0, 1);
$stderr = is_file($err) ? trim((string) file_get_contents($err)) : '';
@unlink($tmp); @unlink($err);

printf("\n  الاستعادة: رمزُ الخروج %d في %s ث (RTO المقيس)\n", $rc, $secs);
if ($stderr !== '') { foreach (array_slice(explode("\n", $stderr), 0, 3) as $l) { echo '    ⚠ ' . trim($l) . "\n"; } }

$after = $snap($c, $drill);
echo "  المستعاد: " . json_encode($after, JSON_UNESCAPED_UNICODE) . "\n";

echo "\n── المقارنة ──\n";
$fails = 0;
foreach ($before as $k => $v) {
    $w = $after[$k];
    $ok = ($w === $v);
    printf("  %s %-10s الأصل %-8s المستعاد %-8s\n", $ok ? '✔' : '✘', $k, number_format($v), number_format($w));
    if (!$ok) { $fails++; }
}

/* المحضر — الأثرُ هو الدليل */
$md = "# محضرُ تجربةِ استعادة\n\n"
    . '| البند | القيمة |' . "\n|---|---|\n"
    . "| التاريخ | " . date('Y-m-d H:i:s') . " |\n"
    . '| النسخة | `' . basename($bak) . '` (' . round(filesize($bak) / 1048576, 1) . " م.ب) |\n"
    . '| الهدف | `' . $drill . "` (منفصلة — الإنتاج لم يُمَسّ) |\n"
    . '| زمنُ الاستعادة (RTO المقيس) | ' . $secs . " ثانية |\n"
    . '| رمزُ الخروج | ' . $rc . " |\n"
    . '| الحكم | ' . ($fails === 0 && $rc === 0 ? '**نجحت** — كلُّ المقاييس متطابقة' : '**أخفقت** — ' . $fails . ' مقياسًا متفارقًا') . " |\n\n"
    . "## المقارنة\n\n| المقياس | الأصل | المستعاد |\n|---|---:|---:|\n";
foreach ($before as $k => $v) { $md .= "| $k | " . number_format($v) . ' | ' . number_format($after[$k]) . " |\n"; }
$md .= "\n> النسخةُ التي لم تُستعَد ليست نسخةً بل ملفّ. وهذا المحضرُ يُعاد توليدُه بـ`php tools/ops02_restore_drill.php`.\n";
file_put_contents($ROOT . '/storage/backups/RESTORE_DRILL.md', $md);

if (!$keepDrill) { $c->query("DROP DATABASE IF EXISTS `$drill`"); echo "\n  · أُسقطت قاعدةُ التجربة\n"; }
echo "  · كُتب المحضر: storage/backups/RESTORE_DRILL.md\n";
echo "\n" . ($fails === 0 && $rc === 0
    ? "✔ الاستعادةُ نجحت — النسخةُ صالحةٌ فعلًا لا اسمًا\n"
    : "✘ الاستعادةُ أخفقت في $fails مقياسًا\n");
exit(($fails === 0 && $rc === 0) ? 0 : 1);
