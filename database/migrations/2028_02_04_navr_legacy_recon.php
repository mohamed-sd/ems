<?php
/**
 * 2028_02_04_navr_legacy_recon.php — سجلُّ مصالحةِ إرثِ الملاحة (§٢٠)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: table:gov_legacy_nav_recon
 * «لا تحذف gov_target_nav بالجملة — غيرُ صالحٍ كTarget لكنه Evidence عن
 * الوضعِ السابق». كلُّ صفٍّ يُصنَّف بحكمٍ من ستّة، والمصالحةُ **لا تتحوّل
 * Reverse Alignment جديدًا**: Current يقترح Finding ولا يعتمد نفسَه Target.
 * التشغيل: php database/migrations/2028_02_04_navr_legacy_recon.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `gov_legacy_nav_recon` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `gtn_id` INT NOT NULL COMMENT 'صف gov_target_nav المصالَح',
    `role_id` INT NOT NULL,
    `route` VARCHAR(190) NOT NULL,
    `group_ar` VARCHAR(150) NULL,
    `doc_code` VARCHAR(40) NULL,
    `verdict` ENUM('MATCHES_GOVERNING_TARGET','APPROVED_POST_GUIDE_ADDITION','VALID_UTILITY',
                   'DUPLICATE','CURRENT_ONLY_UNGOVERNED','SUPERSEDED') NOT NULL,
    `basis` VARCHAR(300) NOT NULL,
    `reconciled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_gtn` (`gtn_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='§٢٠: مصالحةُ إرثِ gov_target_nav — Current يقترح ولا يعتمد نفسَه'");
if (!$ok) { exit("⛔ فشل: {$conn->error}\n"); }
echo "✔ gov_legacy_nav_recon قائم\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
