<?php
/**
 * 2028_02_01_gov077_consumer_key_hygiene_down.php — عكسُ نظافةِ المفتاح
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: constraint:chk_evdeliv_key_machine (drop), table:ems_delivery_key_quarantine (restore+drop)
 * يُسقط القيدَ المانعَ · يعيد المحجورَ إلى دفترِ التسليماتِ بهويّتِه · يُسقط جدولَ الحجر.
 * التشغيل: php database/migrations/2028_02_01_gov077_consumer_key_hygiene_down.php
 * ═══════════════════════════════════════════════════════════════════════════
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$conn->query("ALTER TABLE `ems_event_deliveries` DROP CONSTRAINT `chk_evdeliv_key_machine`");
echo "① القيدُ أُسقط\n";
$conn->query("INSERT IGNORE INTO `ems_event_deliveries` (`id`,`consumer`,`consumer_key`,`event_id`,`outbox_id`,`state`,`seed_tag`)
    SELECT `id`,`consumer`,`consumer_key`,`event_id`,`outbox_id`,`state`,`seed_tag`
      FROM `ems_delivery_key_quarantine`");
printf("② أُعيد %d صفًّا محجورًا\n", $conn->affected_rows);
$conn->query("DROP TABLE IF EXISTS `ems_delivery_key_quarantine`");
echo "③ جدولُ الحجرِ أُسقط\n";

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
echo "✔ العكسُ اكتمل وقُيّد\n";
