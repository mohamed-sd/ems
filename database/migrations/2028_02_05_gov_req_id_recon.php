<?php
/**
 * 2028_02_05_gov_req_id_recon.php — سجلُّ مصالحةِ معرِّفاتِ المتطلّباتِ بين نسخِ الحزمةِ الحاكمة (م 121)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: table:gov_req_id_recon
 * «نسبُ الهدفِ لا ينقطع عند البناء — معرِّفٌ ثابتٌ منذ تعريفِه». عندما تعيد
 * حزمةٌ حاكمةٌ جديدةٌ ترقيمَ معرِّفاتٍ (كإدراجِ WH-03 «إسناد أمناء المخازن»
 * في -3 فانزياحِ WH-03..18 إلى WH-04..19) تُسجَّل المصالحةُ هنا صفًّا صفًّا
 * بالاسمِ المطبَّعِ لا بالرقم — والترحيلُ تنفّذه أداةُ `tools/gov_exec_wh_recon.php`
 * حاملةً الأحكامَ والأدلّةَ مع المعرِّفِ الجديدِ بلا دهس.
 * التشغيل: php database/migrations/2028_02_05_gov_req_id_recon.php
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

$ok = $conn->query("CREATE TABLE IF NOT EXISTS `gov_req_id_recon` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `pack_ref` VARCHAR(60) NOT NULL COMMENT 'مرجعُ نسخةِ الحزمةِ المسبِّبة',
    `unit` VARCHAR(160) NOT NULL,
    `old_id` VARCHAR(32) NULL COMMENT 'NULL لهدفٍ جديدٍ لا سلفَ له',
    `new_id` VARCHAR(32) NOT NULL,
    `surface_norm` VARCHAR(255) NOT NULL COMMENT 'الاسمُ المطبَّعُ — مفتاحُ المزاوجةِ لا الرقم',
    `kind` ENUM('UNCHANGED','SHIFTED','NEW_TARGET') NOT NULL,
    `basis` VARCHAR(400) NOT NULL,
    `reconciled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pack_new` (`pack_ref`, `new_id`),
    KEY `ix_old` (`old_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='م 121: مصالحةُ معرِّفاتِ المتطلّباتِ بين نسخِ الحزمةِ — بالاسمِ المطبَّعِ والحكمُ يُرحَّل لا يُدهَس'");
if (!$ok) { exit("⛔ فشل: {$conn->error}\n"); }
echo "✔ gov_req_id_recon قائم\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
