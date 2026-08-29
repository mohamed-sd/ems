<?php
/**
 * 2027_12_31_rpr03_manual_entry_period_down.php — نقضُ اشتقاقِ فترةِ القيدِ اليدويّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والنقضُ محصورٌ بما كتبته الهجرةُ وحدَه**: تُفرَّغ `period_code` **للقيدِ
 *    اليدويِّ الحيِّ فقط** (`event_id` فارغٌ وغيرُ محذوف) — فالقيدُ المولَّدُ
 *    بمرجعِ حدثِه لم تمسَّه الهجرةُ ولا يمسُّه نقضُها.
 * ⛔ **ولا يُفرَّغ عمودٌ لصفوفٍ لم تكتبها الهجرة** — وذاك أوسعُ من النقض.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$conn->query("UPDATE fin_journal_entries
                 SET period_code = ''
               WHERE COALESCE(is_deleted,0) = 0
                 AND (event_id IS NULL OR event_id = 0)
                 AND period_code <> ''");
echo "  ✔ فُرِّغت فترةُ " . $conn->affected_rows . " قيدًا يدويًّا — والمولَّدُ لم يُمَسّ\n";

require_once __DIR__ . '/_ledger.php';
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "\n✔ نُقض الاشتقاق\n";
