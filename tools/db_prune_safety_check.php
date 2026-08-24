<?php
/**
 * db_prune_safety_check.php — فحصُ أمانٍ قبلَ تقليمِ أيِّ جدول (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * يجيب عن ثلاثةِ أسئلةٍ لا يجوز الحذفُ قبلها:
 *   ① من يشير إلى هذا الجدولِ بمفتاحٍ أجنبيّ؟ (الحذفُ يُيتِّم أو يُرفَض)
 *   ② ما قواعدُ الحذفِ على تلك المفاتيح؟ (CASCADE يحذف أبعدَ ممّا تقصد)
 *   ③ ما أعمدةُ الحالةِ المتاحةُ للتقليمِ الانتقائيِّ وتوزيعُ قيمِها؟
 *
 * التشغيل: php tools/db_prune_safety_check.php <table> [<table> …]
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$ROOT = dirname(__DIR__);
$tables = array_values(array_filter(array_slice($argv, 1), static fn($a) => !str_starts_with($a, '--')));
if (!$tables) {
    exit("الاستعمال: php tools/db_prune_safety_check.php <table> [<table> …]\n");
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
$dbEsc = $m->real_escape_string($db);

foreach ($tables as $t) {
    $e = $m->real_escape_string($t);
    echo "\n" . str_repeat('═', 78) . "\n";
    printf("▐ %s\n", $t);
    echo str_repeat('═', 78) . "\n";

    $c = $m->query("SELECT COUNT(*) c FROM `{$e}`");
    if (!$c) {
        echo "  ✘ الجدولُ غيرُ موجود\n";
        continue;
    }
    printf("  الصفوف: %s\n", number_format((int) $c->fetch_assoc()['c']));

    // ① من يشير إليه؟
    $r = $m->query(
        "SELECT k.TABLE_NAME, k.COLUMN_NAME, k.CONSTRAINT_NAME, r.DELETE_RULE
           FROM information_schema.KEY_COLUMN_USAGE k
           JOIN information_schema.REFERENTIAL_CONSTRAINTS r
             ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA
          WHERE k.REFERENCED_TABLE_SCHEMA = '{$dbEsc}' AND k.REFERENCED_TABLE_NAME = '{$e}'"
    );
    $n = $r ? $r->num_rows : 0;
    printf("\n  ① يشير إليه %d مفتاحًا أجنبيًّا%s\n", $n, $n ? ':' : ' — الحذفُ لا يُيتِّم شيئًا');
    while ($r && ($x = $r->fetch_assoc())) {
        printf("      %-34s .%-22s ON DELETE %s\n", $x['TABLE_NAME'], $x['COLUMN_NAME'], $x['DELETE_RULE']);
    }

    // ② إلامَ يشير هو؟ (لمعرفةِ ما إذا كان ابنًا في سلسلة)
    $r2 = $m->query(
        "SELECT REFERENCED_TABLE_NAME, COLUMN_NAME
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = '{$dbEsc}' AND TABLE_NAME = '{$e}' AND REFERENCED_TABLE_NAME IS NOT NULL"
    );
    $n2 = $r2 ? $r2->num_rows : 0;
    printf("\n  ② يشير هو إلى %d جدولًا%s\n", $n2, $n2 ? ':' : '');
    while ($r2 && ($x = $r2->fetch_assoc())) {
        printf("      %s → %s\n", $x['COLUMN_NAME'], $x['REFERENCED_TABLE_NAME']);
    }

    // ③ أعمدةُ الحالةِ وتوزيعُها — أساسُ التقليمِ الانتقائيِّ الآمن
    $r3 = $m->query(
        "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = '{$dbEsc}' AND TABLE_NAME = '{$e}'
            AND (COLUMN_NAME LIKE '%status%' OR COLUMN_NAME LIKE '%state%'
                 OR COLUMN_NAME LIKE '%is_%' OR COLUMN_NAME LIKE '%delivered%'
                 OR COLUMN_NAME LIKE '%read%' OR COLUMN_NAME LIKE '%done%')"
    );
    echo "\n  ③ أعمدةُ حالةٍ صالحةٌ للتقليمِ الانتقائيّ:\n";
    $any = false;
    while ($r3 && ($x = $r3->fetch_assoc())) {
        $any = true;
        $col = $m->real_escape_string($x['COLUMN_NAME']);
        $d = $m->query("SELECT `{$col}` v, COUNT(*) c FROM `{$e}` GROUP BY `{$col}` ORDER BY c DESC LIMIT 6");
        $parts = array();
        while ($d && ($y = $d->fetch_assoc())) {
            $parts[] = sprintf('%s=%s', $y['v'] === null ? 'NULL' : $y['v'], number_format((int) $y['c']));
        }
        printf("      %-24s %s\n", $x['COLUMN_NAME'], implode(' · ', $parts));
    }
    if (!$any) {
        echo "      — لا عمودَ حالةٍ معروف\n";
    }
}
echo "\nتقريرٌ فقط — لم يُحذف شيء.\n";
