<?php
/**
 * ra00_baseline.php — جامعُ خطِّ الأساس للمراجعة العكسية (قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لا يكتب في قاعدةِ البيانات ولا في شجرةِ المنتج — مخرجُه evidence/baseline.json
 * ◆ بصمةُ المخطط: sha1 لسلسلةِ SHOW CREATE TABLE لكلِّ الجداولِ والمناظرِ مرتبةً
 *   + بصمةٌ ثانيةٌ للقوادحِ والقيود — تُعادُ بعد أيِّ تشغيلِ مسابيرَ لإثباتِ
 *   أن البياناتِ وحدَها تحرّكت لا البنية.
 * التشغيل: php ra00_baseline.php [--schema-only]
 */
declare(strict_types=1);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
$OUT  = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$schemaOnly = in_array('--schema-only', $argv, true);

function sh(string $cmd): string {
    $o = []; @exec($cmd . ' 2>&1', $o); return trim(implode("\n", $o));
}

$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if (!$db) { fwrite(STDERR, "DB DOWN\n"); exit(2); }
$db->set_charset('utf8mb4');

/* ── بصمةُ المخطط ─────────────────────────────────────────────────────── */
$names = [];
$r = $db->query("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME");
while ($x = $r->fetch_assoc()) { $names[] = $x; }
$ddl = '';
foreach ($names as $n) {
    $q = $db->query('SHOW CREATE TABLE `' . $n['TABLE_NAME'] . '`');
    if ($q) { $row = $q->fetch_row(); $ddl .= $row[1] . "\n;;\n"; }
}
/* AUTO_INCREMENT يتحرك بالبيانات — يُنزع قبل البصمة (وإلا كذبت البصمة على DDL ثابت) */
$ddlNorm = preg_replace('/ AUTO_INCREMENT=\d+/', '', $ddl);
$schemaHash = sha1($ddlNorm);

$trg = '';
$r = $db->query("SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, ACTION_TIMING, EVENT_MANIPULATION, ACTION_STATEMENT
                 FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME");
while ($x = $r->fetch_row()) { $trg .= implode('|', $x) . "\n"; }
$triggerHash = sha1($trg);

if ($schemaOnly) {
    echo json_encode(['at' => date('c'), 'schema_sha1' => $schemaHash, 'trigger_sha1' => $triggerHash], JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

/* ── عدّاداتُ القاعدة ─────────────────────────────────────────────────── */
$one = function (string $sql) use ($db) { $r = @$db->query($sql); return $r ? (int) $r->fetch_row()[0] : null; };
$dbCounts = [
    'server'            => $db->server_info,
    'database'          => 'equipation_manage',
    'port'              => 3307,
    'base_tables'       => $one("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'"),
    'views'             => $one("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='VIEW'"),
    'triggers'          => $one("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()"),
    'foreign_keys'      => $one("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='FOREIGN KEY'"),
    'check_constraints' => $one("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()"),
    'unique_constraints'=> $one("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='UNIQUE'"),
    'tables_with_company_id' => $one("SELECT COUNT(DISTINCT TABLE_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='company_id'"),
    'migrations_applied'=> $one('SELECT COUNT(*) FROM schema_migrations'),
    'modules'           => $one('SELECT COUNT(*) FROM modules'),
    'role_permissions'  => $one('SELECT COUNT(*) FROM role_permissions'),
    'roles'             => $one('SELECT COUNT(*) FROM roles'),
    'users'             => $one('SELECT COUNT(*) FROM users'),
    'nav_items_active'  => $one('SELECT COUNT(*) FROM nav_items WHERE active=1'),
    'action_map_rows'   => $one('SELECT COUNT(*) FROM nav09_action_map'),
    'activity_logs'     => $one('SELECT COUNT(*) FROM activity_logs'),
    'business_events'   => $one('SELECT COUNT(*) FROM ems_business_events'),
];

/* ── عدّاداتُ الشجرة (بلا vendor/.git/worktrees/backups) ─────────────── */
$exclude = '#/(vendor|\.git|\.claude|node_modules|storage/backups)/#';
$phpAll = 0; $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
$dirTop = [];
foreach ($it as $f) {
    $p = str_replace('\\', '/', (string) $f);
    if (preg_match($exclude, $p)) { continue; }
    if (substr($p, -4) === '.php') {
        $phpAll++;
        $rel = substr($p, strlen($ROOT) + 1);
        $top = explode('/', $rel)[0];
        $dirTop[$top] = ($dirTop[$top] ?? 0) + 1;
    }
}
arsort($dirTop);
$treeCounts = [
    'php_files_total'    => $phpAll,
    'tests_files'        => count(glob($ROOT . '/tests/*.php')),
    'tools_files'        => count(glob($ROOT . '/tools/*.php')),
    'migration_files'    => count(glob($ROOT . '/database/migrations/*.php')),
    'php_by_top_dir'     => array_slice($dirTop, 0, 30, true),
];

/* ── Git ──────────────────────────────────────────────────────────────── */
$git = [
    'branch'        => sh('git -C ' . escapeshellarg($ROOT) . ' branch --show-current'),
    'head'          => sh('git -C ' . escapeshellarg($ROOT) . ' rev-parse HEAD'),
    'head_date'     => sh('git -C ' . escapeshellarg($ROOT) . ' log -1 --format=%ci'),
    'status_short'  => sh('git -C ' . escapeshellarg($ROOT) . ' status --short'),
    'ahead_origin_fix'   => sh('git -C ' . escapeshellarg($ROOT) . ' rev-list --count origin/fix/remediation-2026-08..HEAD'),
    'ahead_local_main'   => sh('git -C ' . escapeshellarg($ROOT) . ' rev-list --count refs/heads/main..HEAD'),
    'ahead_origin_main'  => sh('git -C ' . escapeshellarg($ROOT) . ' rev-list --count origin/main..HEAD'),
    'main_ref'      => sh('git -C ' . escapeshellarg($ROOT) . ' rev-parse refs/heads/main'),
    'note_main_reset' => 'refs/heads/main أُعيد ضبطُه إلى origin/main في 2026-08-15 19:46:58 (حدثٌ خارجيٌّ مرصود في reflog) — 553ad70 سلفٌ لِـHEAD فلا عملَ يتيمًا',
];

/* ── بصماتُ الوثائقِ الحاكمةِ ومدخلاتِ التدقيق ──────────────────────── */
$fp = [];
foreach (array_merge(
    glob($ROOT . '/docs/fix/*.docx'),
    glob($ROOT . '/docs/audit_2026-08/*.xlsx'),
    [$ROOT . '/docs/fix_2026-08/master_register.tsv',
     $ROOT . '/docs/fix_progress/INJ_findings_state.tsv',
     $ROOT . '/docs/fix_progress/FIX_rules_register.tsv',
     $ROOT . '/docs/ARCHITECTURE_CURRENT_SYSTEM_v21_ar.md']
) as $f) {
    if (is_file($f)) {
        $fp[basename($f)] = ['sha1' => sha1_file($f), 'bytes' => filesize($f), 'mtime' => date('c', filemtime($f))];
    }
}

/* ── لقطةُ البيئة (الأسرارُ محجوبة) ──────────────────────────────────── */
$env = [];
foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    if ($ln === '' || $ln[0] === '#') { continue; }
    $eq = strpos($ln, '='); if ($eq === false) { continue; }
    $k = trim(substr($ln, 0, $eq)); $v = trim(substr($ln, $eq + 1));
    $secret = preg_match('/PASS|SECRET|KEY|TOKEN|PWD/i', $k) === 1;
    $env[$k] = $secret ? '•محجوب•' : $v;
}

$baseline = [
    'measured_at'   => date('c'),
    'measured_by'   => 'reverse_audit_2026-08 / ra00_baseline.php',
    'php_cli'       => PHP_VERSION . ' (' . PHP_BINARY . ')',
    'os'            => php_uname(),
    'git'           => $git,
    'db'            => $dbCounts,
    'schema_sha1'   => $schemaHash,
    'trigger_sha1'  => $triggerHash,
    'tree'          => $treeCounts,
    'fingerprints'  => $fp,
    'env_snapshot'  => $env,
    'scope_notes'   => [
        'المسابيرُ الشاهدةُ تكتب صفوفَ اختبارٍ وتنظّفها — بصمةُ المخططِ تُعاد بعد كلِّ جولةٍ لإثباتِ ثباتِ البنية',
        'أدواتُ هذه المراجعةِ قراءةٌ فقط ولا تكتب إلا تحت docs/reverse_audit_2026-08/',
        'ملفُّ ~$X-02...docx قفلُ Word مؤقتٌ غيرُ متتبَّع — ليس تعديلَ منتج',
    ],
];

@mkdir($OUT, 0777, true);
file_put_contents($OUT . '/baseline.json', json_encode($baseline, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "baseline.json كُتب\n";
echo 'schema_sha1  = ' . $schemaHash . "\n";
echo 'trigger_sha1 = ' . $triggerHash . "\n";
echo 'php_files    = ' . $phpAll . "\n";
