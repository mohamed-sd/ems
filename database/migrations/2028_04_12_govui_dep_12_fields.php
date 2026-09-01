<?php
/**
 * 2028_04_12_govui_dep_12_fields.php — DEP-12 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-12
 * مولَّدةٌ من `tools/govui_field_close.php` على مواصفةِ الإدارة —
 * واسمُ العمودِ تعليقُه اسمُ الحقلِ في ورقةِ الدليل.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
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

$sql = 'CREATE TABLE IF NOT EXISTS `site_gate_equip` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الإذن\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الكيان\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الكيان\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اتجاه الحركة\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت الحركة\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الموقع\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع التخصيص الساري\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مطابقة التخصيص\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرافق/سائق\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الغرض\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر الإذن\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'واقعة بلا إذن؟\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الإذن\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_93cdae6f_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SITE-04 - أذون دخول وخروج المعدات والمشغّلين\'';
if ($conn->query($sql)) { echo '+ جدول site_gate_equip
'; }
else { echo 'x site_gate_equip: ' . $conn->error . chr(10); }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
