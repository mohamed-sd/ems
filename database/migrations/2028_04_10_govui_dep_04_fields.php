<?php
/**
 * 2028_04_10_govui_dep_04_fields.php — DEP-04 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-04
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

$sql = 'CREATE TABLE IF NOT EXISTS `flt_asset_use_rights` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الحصة\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الأصل\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الطرف المالك / صاحب حق الاستخدام\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'صفة الطرف\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نسبة حق الاستخدام التشغيلي\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاكتساب\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التخارج\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المشتري عند التخارج\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع مستند الانتقال\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مجموع الحصص المتزامنة\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة التحقق\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس السجل\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_20a85131_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'FLEET-09 - حق الاستخدام التشغيلي\'';
if ($conn->query($sql)) { echo '+ جدول flt_asset_use_rights
'; }
else { echo 'x flt_asset_use_rights: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `flt_fleet_schema_matrix` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الشيت\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاسم\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكتلة\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Grain ماذا يمثل الصف؟\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'PK\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'FKs\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر الحقيقة\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المالك\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'يسبقه\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'يليه\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'صفوف فعلية\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحكم\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_27d8900e_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'FLEET-41 - مصفوفة بنية الشيتات\'';
if ($conn->query($sql)) { echo '+ جدول flt_fleet_schema_matrix
'; }
else { echo 'x flt_fleet_schema_matrix: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `flt_asset_full_history` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الأصل\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تسلسل الواقعة\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التاريخ\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الواقعة\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الشيت المصدر\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع السجل\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وصف الواقعة\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قراءة العداد\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المشروع\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة التعاقدية\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة بعد الواقعة\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المسؤول\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستند\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_73055f84_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'FLEET-28 - لا سطر مسجل بعد في تاريخ المعدة الكامل\'';
if ($conn->query($sql)) { echo '+ جدول flt_asset_full_history
'; }
else { echo 'x flt_asset_full_history: ' . $conn->error . chr(10); }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
