<?php
/**
 * 2028_04_08_govui_ws_my_fields.php — WS-MY · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for WS-MY
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

$sql = 'CREATE TABLE IF NOT EXISTS `my_reports` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البلاغ\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التسجيل\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفئة\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الطبيعة\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملخص البلاغ\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة المعالجة\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البلاغ\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ينتظر تأكيدي؟\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تأكيد الإغلاق\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تقييم الرضا\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_5512ea37_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MY-05 - لا سطر مسجل بعد في البلاغات المسجَّلة\'';
if ($conn->query($sql)) { echo '+ جدول my_reports
'; }
else { echo 'x my_reports: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `my_achievement` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحساب\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المدى\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مؤشر الإنجاز\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بلغة الدور\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مقارنة بالمدى السابق\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تحديث\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_930468dd_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MY-01 - مؤشرات الإنجاز الشخصي\'';
if ($conn->query($sql)) { echo '+ جدول my_achievement
'; }
else { echo 'x my_achievement: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `my_portal` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المكون\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحساب\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الدور\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المكون\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'محتواه الحي\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدره\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تحديث\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_0c1f4200_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MY-02 - البوابة الشخصية\'';
if ($conn->query($sql)) { echo '+ جدول my_portal
'; }
else { echo 'x my_portal: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `my_tasks` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المهمة\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع المهمة\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر المهمة\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الشاشة الأصلية\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرجع\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مهلة المهمة\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأولوية\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة المهمة\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سبب التأجيل\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت الإنجاز\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_00f92ce7_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MY-03 - المهام المسنَدة\'';
if ($conn->query($sql)) { echo '+ جدول my_tasks
'; }
else { echo 'x my_tasks: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `my_user_capacities` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الصفة\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الصفة\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدرها\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النطاق\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سارية من\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إلى\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نشطة الآن؟\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تبديل\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع التفويض عند الإنابة\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الصفة\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_4d4272f4_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MY-06 - الصفات الوظيفية والتبديل بينها\'';
if ($conn->query($sql)) { echo '+ جدول my_user_capacities
'; }
else { echo 'x my_user_capacities: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `my_requests` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الطلب\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الطلب\',`g49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الطلب\',`g50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تفاصيل الطلب\',`g51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرفق\',`g52` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الجهة المالكة للقرار\',`g53` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مسار الاعتماد\',`g54` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الطلب\',`g55` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قرار الجهة\',`g56` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ القرار\',`g57` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g58` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g59` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g60` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_7eb81554_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MY-04 - لا سطر مسجل بعد في الطلبات المقدَّمة\'';
if ($conn->query($sql)) { echo '+ جدول my_requests
'; }
else { echo 'x my_requests: ' . $conn->error . chr(10); }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
