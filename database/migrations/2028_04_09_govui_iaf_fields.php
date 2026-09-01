<?php
/**
 * 2028_04_09_govui_iaf_fields.php — IAF · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for IAF
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

$sql = 'CREATE TABLE IF NOT EXISTS `iaf_evidence_requests` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الطلب\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المهمة\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الجهة الخاضعة\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الدليل المطلوب\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المستند المتوقع\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الطلب\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المهلة\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاستلام\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أيام التأخير\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اكتمال الدليل\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أثر النقص على المهمة\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الطلب\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_89e2b918_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'IAF-08 - طلبات الأدلة\'';
if ($conn->query($sql)) { echo '+ جدول iaf_evidence_requests
'; }
else { echo 'x iaf_evidence_requests: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `iaf_audit_programs` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الخطوة\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المهمة\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تسلسل الخطوة\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الهدف الرقابي\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الضابط المختبر\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أسلوب الاختبار\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حجم العينة المخطط\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنفذ\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النتيجة الأولية\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع ورقة العمل\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الخطوة\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_500ac13d_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'IAF-07 - برامج المراجعة\'';
if ($conn->query($sql)) { echo '+ جدول iaf_audit_programs
'; }
else { echo 'x iaf_audit_programs: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `iaf_test_samples` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المفردة\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الخطوة\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المفردة في مصدرها\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المجتمع المسحوب منه\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حجم المجتمع\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أسلوب السحب\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نتيجة الفحص\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وصف الانحراف\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة الأثر\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الملاحظة المتفرعة\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_22bfcab1_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'IAF-09 - العينات ونتائج الاختبارات\'';
if ($conn->query($sql)) { echo '+ جدول iaf_test_samples
'; }
else { echo 'x iaf_test_samples: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `iaf_function_risks` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_ID بسجل المخاطر\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الخطر\',`g49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوصف\',`g50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستوى المتبقي\',`g51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الضابط القائم\',`g52` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعالجة\',`g53` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المالك\',`g54` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الخطر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_a2cce5f5_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'IAF-17 - مخاطر وظيفة المراجعة\'';
if ($conn->query($sql)) { echo '+ جدول iaf_function_risks
'; }
else { echo 'x iaf_function_risks: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `iaf_dashboard_kpi` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g55` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المؤشر\',`g56` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المؤشر KPI Catalog\',`g57` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفترة\',`g58` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g59` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستهدف\',`g60` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الانحراف\',`g61` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g62` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تحديث\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_c9d9defb_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'IAF-01 - لا سطر مسجل بعد في لوحة المراجعة الداخلية\'';
if ($conn->query($sql)) { echo '+ جدول iaf_dashboard_kpi
'; }
else { echo 'x iaf_dashboard_kpi: ' . $conn->error . chr(10); }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
