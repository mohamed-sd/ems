<?php
/**
 * tools/export_hosting.php — التصدير الجذري لهوستينجر بضغطة واحدة
 * ───────────────────────────────────────────────────────────────────────────
 * يغني عن phpMyAdmin كليًّا: يصدّر القاعدة الحية (بنية + بيانات + قوادح)
 * بmariadb-dump ثم يعقّمها في الطريق (نزع DEFINER · قلب SQL SECURITY ·
 * تطبيع ترتيبات 0900) ويسلّم ملفًا واحدًا جاهزًا للرفع على سطح المكتب.
 * التشغيل: EXPORT_FOR_HOSTINGER.bat (ضغطتان) أو:
 *   php tools/export_hosting.php
 */
if (PHP_SAPI !== 'cli') { die("CLI only\n"); }
require_once __DIR__ . '/../includes/env.php';

$host = ems_env('DB_HOST', 'localhost:3307');
$port = 3307;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$user = ems_env('DB_MIGRATOR_USER', ems_env('DB_USER'));
$pass = ems_env('DB_MIGRATOR_PASS', ems_env('DB_PASS'));
$db   = ems_env('DB_NAME', 'equipation_manage');

$dumpBin = 'C:\\wamp64\\bin\\mariadb\\mariadb11.4.9\\bin\\mariadb-dump.exe';
if (!is_file($dumpBin)) { fwrite(STDERR, "✘ mariadb-dump غير موجود: $dumpBin\n"); exit(1); }

$desktop = getenv('USERPROFILE') . '\\Desktop';
if (!is_dir($desktop)) { $desktop = dirname(__DIR__); }
$out = $desktop . '\\' . $db . '_hosting_' . date('Ymd_His') . '.sql';

fwrite(STDOUT, "① التصدير من القاعدة الحية ($db @ $host:$port)...\n");
$cmd = '"' . $dumpBin . '"'
     . ' --host=' . escapeshellarg($host) . ' --port=' . $port
     . ' --user=' . escapeshellarg($user) . ' --password=' . escapeshellarg($pass)
     . ' --default-character-set=utf8mb4 --single-transaction --triggers --routines --events'
     . ' --skip-lock-tables ' . escapeshellarg($db);

$proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
if (!is_resource($proc)) { fwrite(STDERR, "✘ تعذر تشغيل المصدّر\n"); exit(1); }

$dst = fopen($out, 'wb');
$counts = array('definer' => 0, 'security' => 0, 'collate' => 0);
$bytes = 0;
while (($line = fgets($pipes[1])) !== false) {
    $n = 0;
    $line = preg_replace('/DEFINER\s*=\s*(`[^`]+`|\'[^\']+\'|\w+)\s*@\s*(`[^`]+`|\'[^\']+\'|[\w.%-]+)\s*/i', '', $line, -1, $n);
    $counts['definer'] += $n;
    $line = preg_replace('/SQL\s+SECURITY\s+DEFINER/i', 'SQL SECURITY INVOKER', $line, -1, $n);
    $counts['security'] += $n;
    $line = str_replace(array('utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_cs'), 'utf8mb4_unicode_ci', $line, $n);
    $counts['collate'] += $n;
    $bytes += fwrite($dst, $line);
}
$err = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$rc = proc_close($proc);
fclose($dst);

/* أخطاء المصدر قاتلة — لا ملف ناقص يُسلَّم أبدًا */
$err = trim(preg_replace('/^.*Using a password.*$/m', '', (string) $err));
if ($rc !== 0 || $err !== '' || $bytes < 100000) {
    @unlink($out);
    fwrite(STDERR, "✘ فشل التصدير (rc=$rc · " . number_format($bytes) . " بايت)\n" . ($err !== '' ? "   $err\n" : ''));
    exit(1);
}

/* شاهد الاكتمال: الدمب ينتهي بسطر الإكمال المعياري */
$tail = '';
$fh = fopen($out, 'rb');
fseek($fh, max(0, $bytes - 400));
$tail = stream_get_contents($fh);
fclose($fh);
$complete = strpos($tail, 'Dump completed') !== false;

fwrite(STDOUT, "② عُقّم في الطريق: {$counts['definer']} DEFINER · {$counts['security']} SQL SECURITY · {$counts['collate']} ترتيب 0900\n");
fwrite(STDOUT, "③ الحجم: " . number_format($bytes) . " بايت · " . ($complete ? "الاكتمال مثبت (Dump completed)" : "⚠ لم يُعثر على ختم الاكتمال — لا ترفعه") . "\n");
fwrite(STDOUT, ($complete ? "✔ الملف الجاهز للرفع:\n   $out\n" : "✘ أعد المحاولة\n"));
exit($complete ? 0 : 1);
