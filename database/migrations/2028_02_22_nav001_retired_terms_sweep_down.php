<?php
/**
 * 2028_02_22_nav001_retired_terms_sweep_down.php — العكسُ من السجلِّ لا من قائمةٍ ثانية
 * @migration-objects: reverse link_groups renames using gov_cycle_name_log/FR-NAV-001
 * ◆ **العكسُ يقرأ قيدَه**: يردُّ كلَّ صفٍّ إلى `old_value` المسجَّلِ له بمعرِّفِه —
 *   فلا قائمةَ بدائلَ ثانيةٌ تُكتب هنا وتتفرّق عن الأصل.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);
$q = $conn->query("SELECT row_id, field, old_value FROM gov_cycle_name_log
                    WHERE requirement_id = 'FR-NAV-001' ORDER BY id DESC");
$n = 0;
while ($q && ($r = $q->fetch_assoc())) {
    $f = preg_replace('~[^a-z_]~', '', (string) $r['field']);
    if ($f === '') { continue; }
    $st = $conn->prepare("UPDATE link_groups SET `{$f}` = ? WHERE id = ?");
    if (!$st) { continue; }
    $st->bind_param('si', $r['old_value'], $r['row_id']);
    $st->execute();
    $st->close();
    $n++;
}
$conn->query("DELETE FROM gov_cycle_name_log WHERE requirement_id = 'FR-NAV-001'");
echo "reverted {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
