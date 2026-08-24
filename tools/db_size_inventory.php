<?php
/**
 * db_size_inventory.php — جردُ حجمِ القاعدةِ الدقيق (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * يعطي لكلِّ جدولٍ: الصفوف · حجمَ البيانات · حجمَ الفهارس · والمساحةَ المهجورة
 * (DATA_FREE) — وهي المساحةُ التي يستردُّها `ALTER TABLE … FORCE` بلا حذفِ صف.
 * لا يكتب شيئًا ولا يُعدّل شيئًا.
 *
 * التشغيل: php tools/db_size_inventory.php [--top=30] [--csv]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$ROOT = dirname(__DIR__);

// نقرأ .env مباشرةً — لا نمرُّ بـconfig.php لأنه يبتلع مخرَجَ CLI.
$env = array();
foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || $line[0] === ';') { continue; }
    if (!str_contains($line, '=')) { continue; }
    list($k, $v) = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}

$hostRaw = $env['DB_HOST'] ?? 'localhost';
$port    = 3306;
if (str_contains($hostRaw, ':')) { list($hostRaw, $port) = explode(':', $hostRaw, 2); $port = (int) $port; }

$mysqli = @new mysqli($hostRaw, $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '', $env['DB_NAME'] ?? '', $port);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "تعذَّر الاتصال ({$hostRaw}:{$port}): {$mysqli->connect_error}\n");
    exit(2);
}
$mysqli->set_charset('utf8mb4');

$db = $env['DB_NAME'];
printf("الخادم : %s\n", $mysqli->server_info);
printf("القاعدة: %s @ %s:%d\n\n", $db, $hostRaw, $port);

$top = 30;
foreach ($argv as $a) { if (preg_match('/^--top=(\d+)$/', $a, $m)) { $top = (int) $m[1]; } }
$csv = in_array('--csv', $argv, true);

$sql = "SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, DATA_FREE
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY (DATA_LENGTH + INDEX_LENGTH + DATA_FREE) DESC";
$st = $mysqli->prepare($sql);
$st->bind_param('s', $db);
$st->execute();
$res = $st->get_result();

$rows = array();
$tot = array('rows' => 0, 'data' => 0, 'idx' => 0, 'free' => 0, 'n' => 0);
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
    $tot['n']++;
    $tot['rows'] += (int) $r['TABLE_ROWS'];
    $tot['data'] += (int) $r['DATA_LENGTH'];
    $tot['idx']  += (int) $r['INDEX_LENGTH'];
    $tot['free'] += (int) $r['DATA_FREE'];
}

$mb = static fn($b) => $b / 1048576;

if ($csv) {
    echo "table,engine,rows,data_mb,index_mb,free_mb,total_mb\n";
    foreach ($rows as $r) {
        printf("%s,%s,%d,%.2f,%.2f,%.2f,%.2f\n", $r['TABLE_NAME'], $r['ENGINE'], (int) $r['TABLE_ROWS'],
            $mb($r['DATA_LENGTH']), $mb($r['INDEX_LENGTH']), $mb($r['DATA_FREE']),
            $mb($r['DATA_LENGTH'] + $r['INDEX_LENGTH'] + $r['DATA_FREE']));
    }
} else {
    printf("%-38s %10s %9s %8s %8s %9s\n", 'الجدول', 'الصفوف', 'بيانات', 'فهارس', 'مهجور', 'المجموع');
    echo str_repeat('-', 88) . "\n";
    foreach (array_slice($rows, 0, $top) as $r) {
        printf("%-38s %10d %8.1f%s %7.1f%s %7.1f%s %8.1f%s\n",
            substr($r['TABLE_NAME'], 0, 38), (int) $r['TABLE_ROWS'],
            $mb($r['DATA_LENGTH']), 'م', $mb($r['INDEX_LENGTH']), 'م',
            $mb($r['DATA_FREE']), 'م',
            $mb($r['DATA_LENGTH'] + $r['INDEX_LENGTH'] + $r['DATA_FREE']), 'م');
    }
}

echo "\n" . str_repeat('=', 88) . "\n";
printf("جداول: %d · صفوف (تقديرية): %s\n", $tot['n'], number_format($tot['rows']));
printf("بيانات: %.1f م.ب · فهارس: %.1f م.ب · مهجور: %.1f م.ب · المجموع: %.1f م.ب\n",
    $mb($tot['data']), $mb($tot['idx']), $mb($tot['free']),
    $mb($tot['data'] + $tot['idx'] + $tot['free']));
printf("⇐ المهجورُ %.1f م.ب يُستردُّ بـ ALTER TABLE … FORCE بلا حذفِ صفٍّ واحد.\n", $mb($tot['free']));
