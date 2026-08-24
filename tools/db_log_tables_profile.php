<?php
/**
 * db_log_tables_profile.php — تشريحُ الجداولِ السجلّيةِ قبلَ أيِّ تقليم (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * قرارُ الاستبقاءِ لا يُتَّخذ بحجمِ الجدولِ وحدَه بل بمداه الزمنيِّ ومعدَّلِ نموِّه:
 * جدولٌ بـ200 ألفِ صفٍّ يغطّي سنةً غيرُ جدولٍ بالعددِ نفسِه يغطّي أسبوعًا.
 * يعرض لكلِّ جدول: الصفوف · أقدمَ وأحدثَ طابع · الأيام · صفوفًا/يوم ·
 * وكم يبقى لو استُبقيت آخرُ N يومًا.
 *
 * التشغيل: php tools/db_log_tables_profile.php [--keep-days=30]
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$ROOT = dirname(__DIR__);
$keepDays = 30;
foreach ($argv as $a) {
    if (preg_match('/^--keep-days=(\d+)$/', $a, $m)) {
        $keepDays = max(1, (int) $m[1]);
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

// المرشَّحون: جداولُ سجلٍّ/طابورٍ/إشعارٍ/أرشيف — لا جداولَ عملٍ.
$CANDIDATES = array(
    'activity_logs', 'ems_business_events', 'ems_event_deliveries', 'ems_job_queue',
    'guard_denials', 'personal_notifications', 'fin_notifications',
    'gov_test_residue_archive', 'gov_space_appearances', 'gov_migration_ledger',
    'fin_financial_events', 'fin_event_links', 'fin_event_effects',
    'fin_journal_entries', 'fin_journal_lines', 'work_items', 'cmp03_screen_rows',
);

// نبحث عن عمودِ الطابعِ الزمنيِّ في كلِّ جدولٍ بأشهرِ الأسماء.
$TIME_COLS = array('created_at', 'occurred_at', 'logged_at', 'event_time', 'created_on', 'timestamp', 'date_created');

printf("%-30s %9s %-12s %-12s %6s %8s %10s\n",
    'الجدول', 'الصفوف', 'أقدم', 'أحدث', 'أيام', 'صفوف/يوم', "يبقى ({$keepDays}ي)");
echo str_repeat('-', 96) . "\n";

$totRows = 0;
$totKeep = 0;
foreach ($CANDIDATES as $t) {
    $esc = $m->real_escape_string($t);

    $chk = $m->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA='" .
        $m->real_escape_string($db) . "' AND TABLE_NAME='{$esc}'");
    if (!$chk || $chk->num_rows === 0) {
        printf("%-30s %s\n", $t, '— غير موجود');
        continue;
    }

    $rows = (int) $m->query("SELECT COUNT(*) c FROM `{$esc}`")->fetch_assoc()['c'];
    $totRows += $rows;

    // أيُّ عمودِ وقتٍ متاح؟
    $col = null;
    foreach ($TIME_COLS as $c) {
        $q = $m->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" .
            $m->real_escape_string($db) . "' AND TABLE_NAME='{$esc}' AND COLUMN_NAME='" .
            $m->real_escape_string($c) . "'");
        if ($q && $q->num_rows > 0) {
            $col = $c;
            break;
        }
    }

    if ($col === null) {
        printf("%-30s %9d %s\n", substr($t, 0, 30), $rows, '— لا عمودَ وقتٍ معروف');
        $totKeep += $rows;
        continue;
    }

    $ce = $m->real_escape_string($col);
    $r = $m->query("SELECT MIN(`{$ce}`) a, MAX(`{$ce}`) b FROM `{$esc}`")->fetch_assoc();
    $keep = (int) $m->query(
        "SELECT COUNT(*) c FROM `{$esc}` WHERE `{$ce}` >= DATE_SUB(NOW(), INTERVAL {$keepDays} DAY)"
    )->fetch_assoc()['c'];
    $totKeep += $keep;

    $days = 0;
    if ($r['a'] && $r['b']) {
        $days = max(1, (int) ((strtotime($r['b']) - strtotime($r['a'])) / 86400));
    }

    printf("%-30s %9d %-12s %-12s %6d %8.0f %10d\n",
        substr($t, 0, 30), $rows,
        $r['a'] ? substr($r['a'], 0, 10) : '—',
        $r['b'] ? substr($r['b'], 0, 10) : '—',
        $days, $days ? $rows / $days : 0, $keep);
}

echo str_repeat('=', 96) . "\n";
printf("مجموعُ صفوفِ المرشَّحين: %s · يبقى باستبقاءِ %d يومًا: %s (‎-%.1f٪)\n",
    number_format($totRows), $keepDays, number_format($totKeep),
    $totRows ? (1 - $totKeep / $totRows) * 100 : 0);
echo "\nهذا تقريرٌ فقط — لم يُحذف شيء.\n";
