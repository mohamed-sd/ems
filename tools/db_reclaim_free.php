<?php
/**
 * db_reclaim_free.php — استردادُ المساحةِ المهجورةِ داخلَ الجداول
 * ═══════════════════════════════════════════════════════════════════════════
 * ما المشكلة:
 *   InnoDB لا يُعيد المساحةَ للقرصِ بعدَ الحذف؛ تبقى «مهجورةً» داخلَ ملفِّ .ibd
 *   ويظهر مقدارُها في information_schema.TABLES.DATA_FREE.
 *
 * ◆ ولا يُصلحها `OPTIMIZE TABLE`: على InnoDB يُعيد «status OK» ويُحوَّل داخليًّا
 *   إلى إعادةِ بناءٍ قد لا تُصغِّر شيئًا. الفعّالُ هو `ALTER TABLE … FORCE`.
 *
 * لا يحذف هذا الملفُّ صفًّا واحدًا — يُعيد بناءَ الجدولِ بمحتواه كاملًا.
 *
 * التشغيل:
 *   php tools/db_reclaim_free.php                    # عرضٌ فقط
 *   php tools/db_reclaim_free.php --apply
 *   php tools/db_reclaim_free.php --apply --min-mb=1
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
$minMb = 1.0;
foreach ($argv as $a) {
    if (preg_match('/^--min-mb=([\d.]+)$/', $a, $m)) {
        $minMb = max(0.1, (float) $m[1]);
    }
}

$env = array();
foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || $line[0] === ';' || !str_contains($line, '=')) {
        continue;
    }
    list($k, $v) = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}

list($host, $port) = array_pad(explode(':', $env['DB_HOST'] ?? 'localhost'), 2, 3306);
$db = $env['DB_NAME'] ?? '';

$m = @new mysqli($host, $env['DB_ADMIN_USER'] ?? 'root', $env['DB_ADMIN_PASS'] ?? '', $db, (int) $port);
if ($m->connect_errno) {
    fwrite(STDERR, "تعذَّر الاتصال: {$m->connect_error}\n");
    exit(2);
}
$m->set_charset('utf8mb4');

$pick = static function (mysqli $m, string $db, float $minMb): array {
    $out = array();
    $st = $m->prepare(
        "SELECT TABLE_NAME, TABLE_ROWS, DATA_FREE, DATA_LENGTH + INDEX_LENGTH AS USED
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' AND ENGINE = 'InnoDB'
            AND DATA_FREE >= ?
          ORDER BY DATA_FREE DESC"
    );
    $bytes = (int) ($minMb * 1048576);
    $st->bind_param('si', $db, $bytes);
    $st->execute();
    $r = $st->get_result();
    while ($x = $r->fetch_assoc()) {
        $out[] = $x;
    }
    return $out;
};

$targets = $pick($m, $db, $minMb);
$freeTot = 0;
foreach ($targets as $t) {
    $freeTot += (int) $t['DATA_FREE'];
}

printf("جداولُ فيها مساحةٌ مهجورةٌ ≥ %.1f م.ب : %d\n", $minMb, count($targets));
printf("مجموعُ المهجور : %.1f م.ب\n\n", $freeTot / 1048576);

foreach ($targets as $t) {
    printf("  %-40s صفوف=%-9d مهجور=%.1f م.ب\n",
        substr($t['TABLE_NAME'], 0, 40), (int) $t['TABLE_ROWS'], $t['DATA_FREE'] / 1048576);
}

if (!$apply) {
    echo "\nعرضٌ فقط. أضِفْ --apply لإعادةِ البناء (لا يُحذف صفٌّ واحد).\n";
    exit(0);
}

echo "\n";
$ok = 0;
$fail = 0;
foreach ($targets as $t) {
    $name = $t['TABLE_NAME'];
    $rowsBefore = (int) $m->query('SELECT COUNT(*) c FROM `' . $m->real_escape_string($name) . '`')->fetch_assoc()['c'];
    $t0 = microtime(true);
    if (!$m->query('ALTER TABLE `' . $m->real_escape_string($name) . '` FORCE')) {
        printf("  ✘ %-40s %s\n", $name, $m->error);
        $fail++;
        continue;
    }
    $rowsAfter = (int) $m->query('SELECT COUNT(*) c FROM `' . $m->real_escape_string($name) . '`')->fetch_assoc()['c'];
    // ◆ حارسُ سلامة: عددُ الصفوفِ يجب ألّا يتغيّر — الإعادةُ بناءٌ لا حذف.
    $flag = ($rowsBefore === $rowsAfter) ? '✔' : '⚠ تغيَّر عددُ الصفوف!';
    printf("  %s %-40s %d صفًّا · %.1fث\n", $flag, $name, $rowsAfter, microtime(true) - $t0);
    $ok++;
}

$after = $pick($m, $db, 0.1);
$freeNow = 0;
foreach ($after as $t) {
    $freeNow += (int) $t['DATA_FREE'];
}
printf("\n✔ أُعيد بناءُ %d جدولًا · فشل %d\n", $ok, $fail);
printf("المهجورُ: %.1f م.ب ⇐ %.1f م.ب  (حُرِّر %.1f م.ب)\n",
    $freeTot / 1048576, $freeNow / 1048576, ($freeTot - $freeNow) / 1048576);
