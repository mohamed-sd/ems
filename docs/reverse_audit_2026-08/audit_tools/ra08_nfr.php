<?php
/**
 * ra08_nfr.php — قياسُ المتطلباتِ غيرِ الوظيفية (قراءةٌ فقط · GET وحدَه)
 * ═══════════════════════════════════════════════════════════════════════════
 * ① الأداء: زمنُ الاستجابةِ لكلِّ شاشةٍ تُصيَّر فعلًا للمخوَّل (p50/p95/p99/max)
 *   ◆ جولتان: باردةٌ ثم دافئة — ويُبلَّغ عن الاثنتين لا عن الأفضلِ وحدَه.
 * ② حجمُ الحمولة: صفحاتٌ تتجاوز 1 ميجابايت (كلفةُ شبكةٍ ومتصفح)
 * ③ ناقلُ الأحداث: منشورٌ · مستهلكون · تسليمات · DLQ · تأخُّرُ أقدمِ غيرِ مُسلَّم
 * ④ طابورُ المهامِ والوظائفُ المجدولة
 * ⑤ الاستعادةُ والنسخُ الاحتياطي: أدلةٌ على القرصِ لا ادعاءاتٌ في وثيقة
 * ⑥ إعداداتُ القاعدةِ الحاكمةُ للأداء
 * ⑦ عقودُ API والرموز
 * ◆ ما لا يُقاس يُوسم Not Measured — ولا يُحسب في أيِّ مقام.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/ra08_cookies';

$ACC = ['user' => 'محمد', 'pass' => '12345678'];

function http(string $url, string $jar, ?array $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $t0 = microtime(true);
    $resp = curl_exec($ch);
    $ms = (microtime(true) - $t0) * 1000;
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $ttfb = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) * 1000;
    curl_close($ch);
    $headers = substr((string) $resp, 0, $hlen);
    $body = substr((string) $resp, $hlen);
    $loc = preg_match('/^Location:\s*(.+)$/mi', $headers, $m) ? trim($m[1]) : null;
    return ['code' => $code, 'body' => $body, 'location' => $loc,
            'ms' => round($ms, 1), 'ttfb' => round($ttfb, 1), 'bytes' => strlen($body)];
}

function pct(array $sorted, float $p): float {
    if (!$sorted) { return 0.0; }
    $i = (int) ceil($p / 100 * count($sorted)) - 1;
    return round($sorted[max(0, min($i, count($sorted) - 1))], 1);
}

$OUT = ['measured_at' => gmdate('c'), 'notes' => []];

/* ═══ ① + ② الأداءُ والحمولة ═══════════════════════════════════ */
@unlink($JAR);
$g = http($BASE . '/login.php', $JAR);
if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g['body'], $m)) {
    fwrite(STDERR, "تعذّر قراءةُ رمزِ CSRF — الأداءُ لن يُقاس\n");
    $OUT['performance'] = ['status' => 'NOT_MEASURED', 'why' => 'login_csrf_unreadable'];
} else {
    $lg = http($BASE . '/login.php', $JAR, ['csrf_token' => $m[1], 'username' => $ACC['user'], 'password' => $ACC['pass']]);
    $ok = in_array($lg['code'], [301, 302, 303], true) && stripos((string) $lg['location'], 'login.php') === false;
    if (!$ok) {
        $OUT['performance'] = ['status' => 'NOT_MEASURED', 'why' => 'login_failed', 'code' => $lg['code']];
    } else {
        /* الأهداف: الشاشاتُ التي صُيِّرت فعلًا للمخوَّل في جولةِ ra05 (200 + قشرة) */
        $targets = [];
        foreach (file($EV . '/live_http_admin.jsonl', FILE_IGNORE_NEW_LINES) as $line) {
            $j = json_decode($line, true);
            if (($j['code'] ?? 0) === 200 && ($j['shell'] ?? false)) { $targets[] = $j['path']; }
        }
        sort($targets);
        $rounds = [];
        foreach (['cold', 'warm'] as $round) {
            $rows = [];
            foreach ($targets as $p) {
                $r = http($BASE . '/' . $p, $JAR);
                $rows[] = ['path' => $p, 'code' => $r['code'], 'ms' => $r['ms'], 'ttfb' => $r['ttfb'], 'bytes' => $r['bytes']];
            }
            $ms = array_column($rows, 'ms'); sort($ms);
            $rounds[$round] = [
                'n' => count($rows),
                'p50' => pct($ms, 50), 'p95' => pct($ms, 95), 'p99' => pct($ms, 99),
                'max' => $ms ? round(max($ms), 1) : 0, 'min' => $ms ? round(min($ms), 1) : 0,
                'mean' => $ms ? round(array_sum($ms) / count($ms), 1) : 0,
                'over_1000ms' => count(array_filter($ms, fn($v) => $v > 1000)),
                'over_3000ms' => count(array_filter($ms, fn($v) => $v > 3000)),
                'rows' => $rows,
            ];
            echo "جولة $round: n={$rounds[$round]['n']} p50={$rounds[$round]['p50']} p95={$rounds[$round]['p95']} max={$rounds[$round]['max']}\n";
        }
        // أبطأُ عشرٍ وأثقلُ عشرٍ من الجولةِ الدافئة
        $warm = $rounds['warm']['rows'];
        usort($warm, fn($a, $b) => $b['ms'] <=> $a['ms']);
        $slowest = array_slice($warm, 0, 12);
        $heavy = $rounds['warm']['rows'];
        usort($heavy, fn($a, $b) => $b['bytes'] <=> $a['bytes']);
        $heaviest = array_slice($heavy, 0, 12);
        $OUT['performance'] = [
            'status' => 'MEASURED', 'targets' => count($targets),
            'cold' => array_diff_key($rounds['cold'], ['rows' => 1]),
            'warm' => array_diff_key($rounds['warm'], ['rows' => 1]),
            'slowest' => $slowest, 'heaviest' => $heaviest,
            'payload_over_1mb' => count(array_filter($rounds['warm']['rows'], fn($r) => $r['bytes'] > 1048576)),
            'payload_over_5mb' => count(array_filter($rounds['warm']['rows'], fn($r) => $r['bytes'] > 5242880)),
        ];
        file_put_contents($EV . '/nfr_timings.jsonl',
            implode("\n", array_map(fn($r) => json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $rounds['warm']['rows'])) . "\n");
    }
}

/* ═══ ③④ ناقلُ الأحداثِ وطابورُ المهام ═══════════════════════════ */
$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');
$one = function (string $sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };
$all = function (string $sql) use ($db) { $r = $db->query($sql); if (!$r) { return ['ERR' => $db->error]; } $o = []; while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };

$OUT['event_bus'] = [
    'business_events'      => (int) $one("SELECT COUNT(*) FROM ems_business_events"),
    'consumers_registered' => (int) $one("SELECT COUNT(*) FROM ems_event_consumers"),
    'deliveries'           => (int) $one("SELECT COUNT(*) FROM ems_event_deliveries"),
    'dead_letter'          => (int) $one("SELECT COUNT(*) FROM ems_event_dead_letter"),
    'processed_events'     => (int) $one("SELECT COUNT(*) FROM ems_processed_events"),
    'financial_events'     => (int) $one("SELECT COUNT(*) FROM fin_financial_events"),
    'fin_events_posted'    => (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE journal_entry_id IS NOT NULL AND journal_entry_id>0"),
    'fin_events_draft'     => (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE state='draft'"),
    'oldest_event'         => $one("SELECT MIN(created_at) FROM ems_business_events"),
    'newest_event'         => $one("SELECT MAX(created_at) FROM ems_business_events"),
    'delivery_coverage_pct'=> null,
];
$be = $OUT['event_bus']['business_events'];
$OUT['event_bus']['delivery_coverage_pct'] = $be ? round($OUT['event_bus']['deliveries'] / $be * 100, 3) : null;

$OUT['job_queue'] = [
    'rows'      => (int) $one("SELECT COUNT(*) FROM ems_job_queue"),
    'by_state'  => $all("SELECT state, COUNT(*) n FROM ems_job_queue GROUP BY state"),
    'dead'      => (int) $one("SELECT COUNT(*) FROM ems_job_queue WHERE state='dead'"),
    'oldest'    => $one("SELECT MIN(created_at) FROM ems_job_queue"),
    'dead_types'=> $all("SELECT job_type, COUNT(*) n, MAX(attempts) attempts, SUBSTRING(MAX(last_error),1,180) sample_error FROM ems_job_queue WHERE state='dead' GROUP BY job_type"),
];
$OUT['outbox'] = ['capacity_outbox' => (int) $one("SELECT COUNT(*) FROM capacity_outbox")];

/* ═══ ⑤ الاستعادةُ والنسخُ الاحتياطي — أدلةٌ على القرص ═══════════ */
$bk = [];
foreach ([
    'app/Install/Installer.php', 'database/schema/schema.sql', 'database/baseline',
    'EXPORT_FOR_HOSTINGER.bat', 'storage/backups', 'docs/nfr/N-26_infra_readiness_ar.md',
] as $rel) {
    $p = $ROOT . '/' . $rel;
    $bk[$rel] = ['exists' => file_exists($p), 'is_dir' => is_dir($p),
                 'size' => is_file($p) ? filesize($p) : null,
                 'mtime' => file_exists($p) ? gmdate('c', filemtime($p)) : null];
}
// نسخُ التفريغِ الموجودةُ فعلًا (الجذر + storage/backups + database/baseline)
$dumps = [];
foreach (["$ROOT/*.sql", "$ROOT/*.gz", "$ROOT/storage/backups/*", "$ROOT/database/baseline/*.sql"] as $pat) {
    foreach (glob($pat) ?: [] as $f) {
        if (!is_file($f)) { continue; }
        $dumps[] = ['file' => str_replace($ROOT . '/', '', str_replace('\\', '/', $f)),
                    'size_mb' => round(filesize($f) / 1048576, 2), 'mtime' => gmdate('c', filemtime($f)),
                    'age_days' => (int) floor((time() - filemtime($f)) / 86400)];
    }
}
usort($dumps, fn($a, $b) => strcmp($b['mtime'], $a['mtime']));
$newest = $dumps[0] ?? null;
$OUT['backup_restore'] = [
    'artifacts' => $bk, 'dumps' => $dumps,
    'newest_dump' => $newest,
    'newest_dump_age_days' => $newest['age_days'] ?? null,
    'automated_scheduled_backup' => 'NOT_FOUND — التفريغُ يدويٌّ عبر EXPORT_FOR_HOSTINGER.bat',
    'binlog_pitr' => 'انظر db_settings.log_bin — الاستعادةُ لنقطةِ زمنٍ ممكنةٌ فقط إن كان ON',
    'rpo_declared' => 'NOT_DECLARED', 'rto_declared' => 'NOT_DECLARED',
    'restore_drill_evidence' => 'NONE — لا محضرَ تجربةِ استعادةٍ في الشجرة',
];

/* ═══ ⑥ إعداداتُ القاعدة ═══════════════════════════════════════ */
$vars = [];
foreach (['long_query_time','slow_query_log','max_connections','innodb_buffer_pool_size',
          'innodb_flush_log_at_trx_commit','sync_binlog','log_bin','sql_mode','wait_timeout',
          'transaction_isolation','character_set_server'] as $v) {
    $r = $db->query("SHOW VARIABLES LIKE '$v'");
    $vars[$v] = $r && ($x = $r->fetch_row()) ? $x[1] : null;
}
$OUT['db_settings'] = $vars;
$OUT['db_scale'] = [
    'tables'      => (int) $one("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='equipation_manage' AND TABLE_TYPE='BASE TABLE'"),
    'data_mb'     => round((float) $one("SELECT SUM(DATA_LENGTH)/1048576 FROM information_schema.TABLES WHERE TABLE_SCHEMA='equipation_manage'"), 1),
    'index_mb'    => round((float) $one("SELECT SUM(INDEX_LENGTH)/1048576 FROM information_schema.TABLES WHERE TABLE_SCHEMA='equipation_manage'"), 1),
    'tables_no_pk'=> $all("SELECT t.TABLE_NAME FROM information_schema.TABLES t LEFT JOIN information_schema.TABLE_CONSTRAINTS c ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME AND c.CONSTRAINT_TYPE='PRIMARY KEY' WHERE t.TABLE_SCHEMA='equipation_manage' AND t.TABLE_TYPE='BASE TABLE' AND c.CONSTRAINT_NAME IS NULL"),
    'biggest'     => $all("SELECT TABLE_NAME, TABLE_ROWS, ROUND(DATA_LENGTH/1048576,1) data_mb, ROUND(INDEX_LENGTH/1048576,1) idx_mb FROM information_schema.TABLES WHERE TABLE_SCHEMA='equipation_manage' ORDER BY DATA_LENGTH DESC LIMIT 10"),
];

/* ═══ ⑦ عقودُ API ═══════════════════════════════════════════════ */
$apiFiles = [];
foreach (['api', 'API'] as $d) {
    if (is_dir($ROOT . '/' . $d)) {
        foreach (glob($ROOT . "/$d/*.php") ?: [] as $f) { $apiFiles[] = $d . '/' . basename($f); }
    }
}
$OUT['api'] = [
    'tokens_table_rows' => (int) $one("SELECT COUNT(*) FROM api_tokens"),
    'api_dir_files'     => $apiFiles,
    'openapi_spec'      => file_exists($ROOT . '/openapi.yaml') || file_exists($ROOT . '/openapi.json') ? 'FOUND' : 'NOT_FOUND',
    'versioning'        => 'NOT_DECLARED',
];

/* ═══ ⑧ التزامن — غيرُ مقيسٍ في هذه الجولةِ (مُعلَن) ═══════════ */
$OUT['concurrency'] = ['status' => 'NOT_MEASURED',
    'why' => 'لا أداةَ حملٍ في النطاقِ المسموح (قراءةٌ فقط · بيئةُ تطويرٍ محليةٌ بمستخدمٍ واحد)',
    'required_before_launch' => true];

file_put_contents($EV . '/nfr.json', json_encode($OUT, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nكُتب: evidence/nfr.json\n";
echo "ناقلُ الأحداث: منشور={$OUT['event_bus']['business_events']} · مستهلكون={$OUT['event_bus']['consumers_registered']} · تسليمات={$OUT['event_bus']['deliveries']} ({$OUT['event_bus']['delivery_coverage_pct']}٪) · DLQ={$OUT['event_bus']['dead_letter']}\n";
echo "وقائعُ مالية={$OUT['event_bus']['financial_events']} · مُرحَّلٌ للدفتر={$OUT['event_bus']['fin_events_posted']} · مسودة={$OUT['event_bus']['fin_events_draft']}\n";
