<?php
/**
 * 2028_01_31_cycle_bridge_authoring_down.php — عكسُ قواعدِ التأليف
 * يحذف الصفوفَ المؤلَّفةَ ويُفرغ الملتبسَ المحسومَ ثم يعيد الحارسَ والمعجمَ القديمَين.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
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

$conn->query("DELETE FROM gov_screen_cycle WHERE bridge_rule IN ('C5_AUTHORED','C5_NOT_APPLICABLE')");
echo "  ✔ حُذف المؤلَّف: {$conn->affected_rows}\n";
$conn->query("UPDATE gov_screen_cycle SET screen_id='', bridge_rule='AMBIGUOUS_DECLARED', bridge_witness='', bridge_snapshot='' WHERE bridge_rule='C6_MANUAL_AMBIG'");
echo "  ✔ أُعيد الملتبس: {$conn->affected_rows}\n";
$conn->query("ALTER TABLE gov_screen_cycle DROP CONSTRAINT chk_cyc_bridge");
$conn->query("ALTER TABLE gov_screen_cycle ADD CONSTRAINT chk_cyc_bridge CHECK (
    (bridge_rule IN ('BASENAME_UNIQUE','PATH_OR_SCOPE_RESOLVED') AND screen_id <> '')
    OR (bridge_rule NOT IN ('BASENAME_UNIQUE','PATH_OR_SCOPE_RESOLVED') AND screen_id = ''))");
$conn->query("ALTER TABLE gov_screen_cycle MODIFY stage_kind ENUM('canonical','contextual') NOT NULL DEFAULT 'canonical'");
$conn->query("DELETE FROM gov_migration_settlement WHERE filename='2028_01_31_cycle_bridge_authoring.php'");
$conn->query("DELETE FROM schema_migrations WHERE filename='2028_01_31_cycle_bridge_authoring.php'");
echo "  ✔ أُعيد الحارسُ والمعجمُ ومُحي قيدُ الدفتر\n";
