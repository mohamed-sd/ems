<?php
/**
 * 2028_02_07_gov_decision_propagation.php — سجلُّ إسقاطِ القرارات (GOV_EXEC §8)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: table:gov_decision_propagation
 * «لكلِّ قرارٍ معتمدٍ أثرُه على كلِّ الطبقاتِ وتحقُّقُ نفاذِه — ولا يُقبل
 * صحيحٌ في Registry غيرُ نافذٍ في Runtime». كلُّ قرارٍ معتمدٍ يحمل هنا حكمَ
 * نفاذِه بمجسِّه المسمّى — والفارغُ «غيرُ مقيس» لا «نافذ» (م111).
 * التشغيل: php database/migrations/2028_02_07_gov_decision_propagation.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `gov_decision_propagation` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `decision_id` VARCHAR(32) NOT NULL,
    `verdict` ENUM('RUNTIME_VERIFIED','RUNTIME_PRESENT','TARGET_PROPAGATED_BUILD_PENDING',
                   'BLOCKED_OWNER_VALUES','BLOCKED_ENVIRONMENT','UNPROPAGATED') NOT NULL,
    `probe_kind` VARCHAR(40) NOT NULL COMMENT 'TABLE_PROBE/ENGINE_FILE/REQ_LEDGER/MANUAL/…',
    `probe_ref` VARCHAR(400) NOT NULL COMMENT 'اسمُ الجدولِ/الملفِّ/الشاهدِ الذي سُبر',
    `basis` VARCHAR(500) NOT NULL,
    `measured_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `snapshot_id` VARCHAR(48) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dec` (`decision_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='GOV_EXEC §8: حكمُ نفاذِ كلِّ قرارٍ معتمدٍ بمجسِّه — APPROVED_DECISION_WITH_UNPROPAGATED_IMPACT يقاس منه'");
if (!$ok) { exit("⛔ فشل: {$conn->error}\n"); }
echo "✔ gov_decision_propagation قائم\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
