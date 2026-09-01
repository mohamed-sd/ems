<?php
/**
 * 2028_04_05_govui_dep09_fields.php — DEP-09 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-09
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

$sql = 'CREATE TABLE IF NOT EXISTS `rsk_risk_register` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_ID\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عنوان الخطر\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عقدة التصنيف\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العائلة\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر التحديد\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مفتاح الحدث/السجل الأصلي\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكيان المتأثر\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الكيان\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة التشغيلية المتأثرة\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وصف الخطر\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_Owner\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التحديد\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تقييم\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستوى المتبقي الحالي\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الخطر\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_20c52e6e_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'RSK-03 - سجل المخاطر المؤسسي\'';
if ($conn->query($sql)) { echo '+ جدول rsk_risk_register
'; }
else { echo 'x rsk_risk_register: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `rsk_risk_events` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الحدث\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_ID\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر الحدث\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مفتاح السجل الأصلي\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قراءة الحدث\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السبب من مصدره\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الجهة المتسببة\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المدة/الحجم\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأثر الإنتاجي\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأثر المالي\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأثر التعاقدي\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تكرار السبب\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قاعدة الخطر المتحققة\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الحدث\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_198c9b12_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'RSK-04 - أحداث المخاطر والخسائر\'';
if ($conn->query($sql)) { echo '+ جدول rsk_risk_events
'; }
else { echo 'x rsk_risk_events: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `rsk_risk_treatments` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الإجراء\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_ID\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مسار المعالجة\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وصف الإجراء\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المالك\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة المنفذة\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Due_Date\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستوى المستهدف بعد الإجراء\',`g49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أيام التأخير\',`g50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'دليل الإنجاز\',`g51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إعادة التقييم بعده\',`g52` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الإجراء\',`g53` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g54` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g55` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g56` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_4abc7987_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'RSK-07 - خطط معالجة المخاطر\'';
if ($conn->query($sql)) { echo '+ جدول rsk_risk_treatments
'; }
else { echo 'x rsk_risk_treatments: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `rsk_risk_escalations` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g57` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف التصعيد\',`g58` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_ID\',`g59` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مسبب التصعيد\',`g60` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستوى\',`g61` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المخطر\',`g62` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت التصعيد\',`g63` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاستجابة/القرار\',`g64` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع قيادة ر11\',`g65` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة التصعيد\',`g66` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g67` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g68` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g69` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_bd690e24_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'RSK-10 - لا سطر مسجل بعد في تصعيدات المخاطر\'';
if ($conn->query($sql)) { echo '+ جدول rsk_risk_escalations
'; }
else { echo 'x rsk_risk_escalations: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `rsk_risk_closure` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g70` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الإغلاق\',`g71` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_ID\',`g72` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس الإغلاق\',`g73` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع إعادة التقييم الختامية\',`g74` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستوى المتبقي عند الإغلاق\',`g75` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع القبول الرسمي\',`g76` VARCHAR(190) NULL DEFAULT NULL COMMENT \'دليل الإغلاق\',`g77` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإغلاق\',`g78` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إعادة فتح؟\',`g79` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الإغلاق\',`g80` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g81` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g82` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g83` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_989faf81_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'RSK-12 - سجل الإغلاق والأدلة\'';
if ($conn->query($sql)) { echo '+ جدول rsk_risk_closure
'; }
else { echo 'x rsk_risk_closure: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `rsk_risk_reports` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g84` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g85` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الدورية\',`g86` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفترة\',`g87` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العائلة\',`g88` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البند\',`g89` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g90` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاتجاه\',`g91` VARCHAR(190) NULL DEFAULT NULL COMMENT \'يستلزم قرارا؟\',`g92` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الخطر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_4cbe85b4_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'RSK-13 - تقارير المخاطر الدورية\'';
if ($conn->query($sql)) { echo '+ جدول rsk_risk_reports
'; }
else { echo 'x rsk_risk_reports: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `rsk_risk_taxonomy` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g93` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود العقدة\',`g94` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العائلة\',`g95` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفئة\',`g96` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الخطر\',`g97` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أمثلة نموذجية\',`g98` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر الحدث الأصلي\',`g99` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مقياس الأثر المعتمد\',`g100` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة العقدة\',`g101` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g102` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g103` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g104` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_56c5dfef_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'RSK-02 - تصنيف المخاطر\'';
if ($conn->query($sql)) { echo '+ جدول rsk_risk_taxonomy
'; }
else { echo 'x rsk_risk_taxonomy: ' . $conn->error . chr(10); }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
