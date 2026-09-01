<?php
/**
 * 2028_04_06_govui_dep17_fields.php — DEP-17 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-17
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

$sql = 'CREATE TABLE IF NOT EXISTS `wh_hazmat` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الصنف\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فئة الخطورة\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التصريح النظامي\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'موقع العزل\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أمين العهدة المخول\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تتبع الدفعة إلزامي؟\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سلطة الصرف\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقابة مزدوجة؟\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيد الصلاحية\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مسار الإتلاف\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الضوابط\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_39e4bacd_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'WH-08 - ضوابط المواد الخطرة والمتفجرات\'';
if ($conn->query($sql)) { echo '+ جدول wh_hazmat
'; }
else { echo 'x wh_hazmat: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `wh_month_close` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الإقفال\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الشهر\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المخزن\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سندات إدخال الشهر\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سندات صرف الشهر\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تحويلات الشهر\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فروق جرد مسواة\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عهد مفتوحة مرحلة\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة المخزون الختامية\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مطابقة المالية\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الإقفال\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_71cd7d21_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'WH-19 - الإقفال الشهري للمخازن\'';
if ($conn->query($sql)) { echo '+ جدول wh_month_close
'; }
else { echo 'x wh_month_close: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `wh_warehouses` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود المخزن\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المخزن\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع المخزن\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأمين النافذ اليوم\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أسلوب العهدة\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ترخيص خاص\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعة التخزين\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ضوابط السلامة\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة المخزن\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_013bd2a4_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'WH-02 - سجل المخازن وأنواعها\'';
if ($conn->query($sql)) { echo '+ جدول wh_warehouses
'; }
else { echo 'x wh_warehouses: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `wh_issue_requests` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g52` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الطلب\',`g53` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الورود\',`g54` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الجهة الطالبة\',`g55` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الصرف\',`g56` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرجع الموجب\',`g57` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص المرجع\',`g58` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البنود المطلوبة\',`g59` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص الرصيد\',`g60` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأولوية\',`g61` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قرار المخزن\',`g62` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الطلب\',`g63` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g64` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g65` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g66` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_96fdd010_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'WH-09 - طلبات الصرف الواردة\'';
if ($conn->query($sql)) { echo '+ جدول wh_issue_requests
'; }
else { echo 'x wh_issue_requests: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `wh_issue_request_lines` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g67` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف البند\',`g68` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الطلب\',`g69` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الصنف\',`g70` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكمية المطلوبة\',`g71` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكمية المعتمدة\',`g72` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المصروف تراكميا\',`g73` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتبقي\',`g74` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البند\',`g75` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g76` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g77` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g78` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_b933b73a_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'WH-10 - لا سطر مسجل بعد في طلبات الصرف الواردة\'';
if ($conn->query($sql)) { echo '+ جدول wh_issue_request_lines
'; }
else { echo 'x wh_issue_request_lines: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `wh_count` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g79` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الجلسة\',`g80` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المخزن\',`g81` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أسلوب الجرد\',`g82` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الجرد\',`g83` VARCHAR(190) NULL DEFAULT NULL COMMENT \'لجنة الجرد\',`g84` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الأصناف المجرودة\',`g85` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بنود الفروق تفصيلها خ10-2\',`g86` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع التحقيق\',`g87` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الجلسة\',`g88` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g89` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g90` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g91` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g92` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g93` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g94` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_ba12c908_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'WH-16 - الجرد ومعالجة الفروقات\'';
if ($conn->query($sql)) { echo '+ جدول wh_count
'; }
else { echo 'x wh_count: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `wh_transfer` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g95` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر\',`g96` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الأمر\',`g97` VARCHAR(190) NULL DEFAULT NULL COMMENT \'من مخزن\',`g98` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إلى مخزن\',`g99` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد البنود تفصيلها خ09-2\',`g100` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مبرر التحويل\',`g101` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وسيلة النقل\',`g102` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سند الخروج\',`g103` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سند الاستلام\',`g104` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مطابقة الاستلام\',`g105` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الأمر\',`g106` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g107` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g108` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g109` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_f353830b_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'WH-14 - لا سطر مسجل بعد في التحويل بين المخازن\'';
if ($conn->query($sql)) { echo '+ جدول wh_transfer
'; }
else { echo 'x wh_transfer: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `wh_stock_proc` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g110` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g111` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الصنف\',`g112` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المخزن\',`g113` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الرصيد\',`g114` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكمية\',`g115` VARCHAR(190) NULL DEFAULT NULL COMMENT \'متوسط التكلفة\',`g116` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g117` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر حركة\',`g118` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تحت الحد الأدنى؟\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_aec1d7e4_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'WH-07 - أرصدة المخزون بحالاتها\'';
if ($conn->query($sql)) { echo '+ جدول wh_stock_proc
'; }
else { echo 'x wh_stock_proc: ' . $conn->error . chr(10); }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
