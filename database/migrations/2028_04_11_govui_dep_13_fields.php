<?php
/**
 * 2028_04_11_govui_dep_13_fields.php — DEP-13 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-13
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

$sql = 'CREATE TABLE IF NOT EXISTS `wf_coverage_lines` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المشروع\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفئة\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المطلوب\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتوفر الجاهز\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المخصص\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العجز\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مسار السد\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'شاغر حرج؟\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة السطر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_4386424f_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'WRK-03 - لا سطر مسجل بعد في المطلوب مقابل المتوفر\'';
if ($conn->query($sql)) { echo '+ جدول wf_coverage_lines
'; }
else { echo 'x wf_coverage_lines: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `wf_housing_units` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الوحدة\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الوحدة\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السعة\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المشغولة\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الشاغرة\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المشرف\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الصيانة\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الوحدة\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_aa457a7c_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'WRK-16 - وحدات السكن والإعاشة\'';
if ($conn->query($sql)) { echo '+ جدول wf_housing_units
'; }
else { echo 'x wf_housing_units: ' . $conn->error . chr(10); }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
