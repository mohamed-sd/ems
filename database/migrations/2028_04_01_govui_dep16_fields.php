<?php
/**
 * 2028_04_01_govui_dep16_fields.php — DEP-16 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-16
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

$sql = 'CREATE TABLE IF NOT EXISTS `prc_proc_po_amendments` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النوع\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر/الطلب\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المبرر\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قاعدة AAM المفعلة\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مسار الموافقة\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قرار الاعتماد\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأثر المالي\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بنود متأثرة\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة السطر\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_5b30ae1f_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-13 - استثناءات الشراء وتعديلات الأوامر\'';
if ($conn->query($sql)) { echo '+ جدول prc_proc_po_amendments
'; }
else { echo 'x prc_proc_po_amendments: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_proc_offers` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف العرض\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم طلب العروض\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المورد\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاستلام\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة العرض\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مدة التوريد\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'شروط الدفع المعروضة\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'صلاحية العرض\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التقييم الفني\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات الفحص\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الترتيب المالي\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة العرض\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_4c0cbc81_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-08 - عروض الموردين المستلمة\'';
if ($conn->query($sql)) { echo '+ جدول prc_proc_offers
'; }
else { echo 'x prc_proc_offers: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_proc_award_minutes` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المحضر\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم طلب العروض\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العروض المقارنة\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'جدول المقارنة\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العرض المرسى عليه\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة الترسية\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مبرر الاختيار\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تفصيل المبرر\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أعضاء اللجنة\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الترسية\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g52` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_822112ff_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-10 - محضر المقارنة والترسية\'';
if ($conn->query($sql)) { echo '+ جدول prc_proc_award_minutes
'; }
else { echo 'x prc_proc_award_minutes: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_proc_supplier_eval` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g53` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g54` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفترة\',`g55` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`g56` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الأوامر\',`g57` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمتها\',`g58` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الالتزام بالمواعيد\',`g59` VARCHAR(190) NULL DEFAULT NULL COMMENT \'متوسط التأخير\',`g60` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نسبة رفض الفحص\',`g61` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فروق المطابقة\',`g62` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المؤشر المركب\',`g63` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التصنيف الناتج\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_46381e7f_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-16 - تقييم أداء التوريد\'';
if ($conn->query($sql)) { echo '+ جدول prc_proc_supplier_eval
'; }
else { echo 'x prc_proc_supplier_eval: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_proc_packages` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g64` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الحزمة\',`g65` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فترة التجميع\',`g66` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نطاق الحزمة\',`g67` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الطلبات المضمومة\',`g68` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد البنود\',`g69` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التقدير الإجمالي\',`g70` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مبرر التمرير المنفرد\',`g71` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قناة الشراء\',`g72` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الحزمة\',`g73` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g74` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g75` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g76` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_9bc3cc52_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-04 - تجميع الطلبات وخطة الشراء\'';
if ($conn->query($sql)) { echo '+ جدول prc_proc_packages
'; }
else { echo 'x prc_proc_packages: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_proc_delivery_track` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g77` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g78` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر\',`g79` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الحدث\',`g80` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الحدث\',`g81` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكمية المشمولة\',`g82` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم سند الإدخال\',`g83` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نتيجة الفحص\',`g84` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أيام التأخير\',`g85` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إخطار المورد\',`g86` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة السطر\',`g87` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g88` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g89` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g90` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_0c02b020_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-14 - متابعة التوريد والاستلام\'';
if ($conn->query($sql)) { echo '+ جدول prc_proc_delivery_track
'; }
else { echo 'x prc_proc_delivery_track: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_orders_proc` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g91` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر\',`g92` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الأمر\',`g93` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المحضر\',`g94` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`g95` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عقد إطاري مرجعي\',`g96` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد البنود تفصيلها ش07-2\',`g97` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة الإجمالية\',`g98` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g99` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت الدفع\',`g100` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الاستلام\',`g101` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مكان التسليم\',`g102` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التوريد المتفق\',`g103` VARCHAR(190) NULL DEFAULT NULL COMMENT \'غرامة التأخير\',`g104` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الأمر\',`g105` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g106` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g107` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g108` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g109` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g110` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g111` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_d3960121_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-11 - أوامر الشراء\'';
if ($conn->query($sql)) { echo '+ جدول prc_orders_proc
'; }
else { echo 'x prc_orders_proc: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_proc_rfq` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g112` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الدعوة\',`g113` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف RFQ\',`g114` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`g115` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الدعوة\',`g116` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تأكيد الاستلام\',`g117` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاستجابة\',`g118` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع العرض الوارد\',`g119` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الدعوة\',`g120` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g121` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g122` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g123` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_73ce9f60_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-07 - دعوات الموردين للعروض\'';
if ($conn->query($sql)) { echo '+ جدول prc_proc_rfq
'; }
else { echo 'x prc_proc_rfq: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_offer_compare` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g124` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g125` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف العرض\',`g126` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع بند الطلب\',`g127` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الصنف\',`g128` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر الوحدة المعروض\',`g129` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكمية\',`g130` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مدة التوريد للبند\',`g131` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بديل مقترح؟\',`g132` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظة فنية\',`g133` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ترتيب البند ماليا\',`g134` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g135` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g136` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g137` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_ef072d1b_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-09 - عروض الموردين المستلمة\'';
if ($conn->query($sql)) { echo '+ جدول prc_offer_compare
'; }
else { echo 'x prc_offer_compare: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_requests` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g138` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الطلب\',`g139` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الطلب\',`g140` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الجهة الطالبة\',`g141` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر الاحتياج\',`g142` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`g143` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التصنيف التشغيلي\',`g144` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأولوية\',`g145` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المشروع المحمل\',`g146` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مركز التكلفة\',`g147` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد البنود تفصيلها ش02-2\',`g148` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التاريخ المطلوب\',`g149` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التقدير المبدئي\',`g150` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الطلب\',`g151` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g152` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g153` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g154` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g155` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g156` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_a10386e7_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-02 - لا سطر مسجل بعد في طلبات الشراء\'';
if ($conn->query($sql)) { echo '+ جدول prc_requests
'; }
else { echo 'x prc_requests: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_package_lines` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g157` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف العضوية\',`g158` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الحزمة\',`g159` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم طلب الشراء\',`g160` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بنود الطلب المشمولة\',`g161` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الضم\',`g162` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة العضوية\',`g163` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g164` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g165` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g166` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_7b730486_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-05 - تجميع الطلبات وخطة الشراء\'';
if ($conn->query($sql)) { echo '+ جدول prc_package_lines
'; }
else { echo 'x prc_package_lines: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `prc_dashboard_kpi` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g167` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المؤشر\',`g168` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المؤشر KPI Catalog\',`g169` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g170` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة\',`g171` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g172` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تحديث\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_877ad4d5_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'PRC-01 - لا سطر مسجل بعد في لوحة المشتريات\'';
if ($conn->query($sql)) { echo '+ جدول prc_dashboard_kpi
'; }
else { echo 'x prc_dashboard_kpi: ' . $conn->error . chr(10); }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g173'");
if ($q && $q->num_rows) { echo "= proc_order.g173 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g173` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم المطابقة'")) {
    echo "+ proc_order.g173\n";
} else { echo "x proc_order.g173: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g174'");
if ($q && $q->num_rows) { echo "= proc_order.g174 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g174` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم المورد'")) {
    echo "+ proc_order.g174\n";
} else { echo "x proc_order.g174: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g175'");
if ($q && $q->num_rows) { echo "= proc_order.g175 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g175` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم الأمر'")) {
    echo "+ proc_order.g175\n";
} else { echo "x proc_order.g175: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g176'");
if ($q && $q->num_rows) { echo "= proc_order.g176 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g176` VARCHAR(190) NULL DEFAULT NULL COMMENT 'سندات الإدخال'")) {
    echo "+ proc_order.g176\n";
} else { echo "x proc_order.g176: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g177'");
if ($q && $q->num_rows) { echo "= proc_order.g177 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g177` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة الأمر'")) {
    echo "+ proc_order.g177\n";
} else { echo "x proc_order.g177: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g178'");
if ($q && $q->num_rows) { echo "= proc_order.g178 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g178` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة المستلم'")) {
    echo "+ proc_order.g178\n";
} else { echo "x proc_order.g178: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g179'");
if ($q && $q->num_rows) { echo "= proc_order.g179 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g179` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تصنيف الفرق'")) {
    echo "+ proc_order.g179\n";
} else { echo "x proc_order.g179: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g180'");
if ($q && $q->num_rows) { echo "= proc_order.g180 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g180` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة الفرق'")) {
    echo "+ proc_order.g180\n";
} else { echo "x proc_order.g180: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g181'");
if ($q && $q->num_rows) { echo "= proc_order.g181 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g181` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تفسير الفرق'")) {
    echo "+ proc_order.g181\n";
} else { echo "x proc_order.g181: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g182'");
if ($q && $q->num_rows) { echo "= proc_order.g182 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g182` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نتيجة المطابقة'")) {
    echo "+ proc_order.g182\n";
} else { echo "x proc_order.g182: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g183'");
if ($q && $q->num_rows) { echo "= proc_order.g183 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g183` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الإحالة للمالية'")) {
    echo "+ proc_order.g183\n";
} else { echo "x proc_order.g183: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g184'");
if ($q && $q->num_rows) { echo "= proc_order.g184 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g184` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة المطابقة'")) {
    echo "+ proc_order.g184\n";
} else { echo "x proc_order.g184: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g185'");
if ($q && $q->num_rows) { echo "= proc_order.g185 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g185` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المنشئ'")) {
    echo "+ proc_order.g185\n";
} else { echo "x proc_order.g185: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g186'");
if ($q && $q->num_rows) { echo "= proc_order.g186 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g186` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الإنشاء'")) {
    echo "+ proc_order.g186\n";
} else { echo "x proc_order.g186: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g187'");
if ($q && $q->num_rows) { echo "= proc_order.g187 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g187` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المراجع'")) {
    echo "+ proc_order.g187\n";
} else { echo "x proc_order.g187: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g188'");
if ($q && $q->num_rows) { echo "= proc_order.g188 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g188` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المعتمد'")) {
    echo "+ proc_order.g188\n";
} else { echo "x proc_order.g188: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g189'");
if ($q && $q->num_rows) { echo "= proc_order.g189 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g189` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد'")) {
    echo "+ proc_order.g189\n";
} else { echo "x proc_order.g189: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g190'");
if ($q && $q->num_rows) { echo "= proc_order.g190 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g190` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ proc_order.g190\n";
} else { echo "x proc_order.g190: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `proc_order` LIKE 'g191'");
if ($q && $q->num_rows) { echo "= proc_order.g191 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `proc_order` ADD COLUMN `g191` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع المصدر'")) {
    echo "+ proc_order.g191\n";
} else { echo "x proc_order.g191: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
