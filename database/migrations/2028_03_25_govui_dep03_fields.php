<?php
/**
 * 2028_03_25_govui_dep03_fields.php — DEP-03 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-03
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

$sql = 'CREATE TABLE IF NOT EXISTS `fin_financier` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الممول\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الممول بالمصدر (ل02)\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاسم القانوني\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الممول\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التصنيف العلائقي\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'شريحة الأهمية\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الدولة\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السجل التجاري\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة التأهيل والعناية\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملات\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نماذج التمويل المقبولة\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بداية العلاقة\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر نشاط\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مدة العلاقة (سنة)\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة العلاقة\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مدير العلاقة\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'هاتف المسؤول\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بريد المسؤول\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اتفاقية إطارية\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سقف التمويل المعتمد\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد العمليات\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رأس المال الممول $\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رأس المال الممول QAR\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رأس المال الممول SDG\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الرصيد القائم $\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأرباح التعاقدية $\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحجية\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Source_Row_Ref\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_6c1c4b27_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'FIN-01 - ممول واحد\'';
if ($conn->query($sql)) { echo '+ جدول fin_financier
'; }
else { echo 'x fin_financier: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `fin_financier_due` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g253` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الاستحقاق\',`g254` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود العملية\',`g255` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الممول\',`g256` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم الممول (بحث)\',`g257` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g258` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستحق حتى الأفق\',`g259` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المدفوع (الدفتر)\',`g260` VARCHAR(190) NULL DEFAULT NULL COMMENT \'صافي المستحق غير المسدد\',`g261` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس الاحتساب\',`g262` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Record_Basis\',`g263` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Derivation_Rule\',`g264` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Confidence\',`g265` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Needs_Review\',`g266` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g267` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Source_Row_Ref\',`g268` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_54e6141f_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'FIN-14 - استحقاق ممول لفترة\'';
if ($conn->query($sql)) { echo '+ جدول fin_financier_due
'; }
else { echo 'x fin_financier_due: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `fin_monthly_close_stmt` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g301` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Monthly_Close_ID\',`g302` VARCHAR(190) NULL DEFAULT NULL COMMENT \'FOP_ID\',`g303` VARCHAR(190) NULL DEFAULT NULL COMMENT \'FCON_ID\',`g304` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Financier_ID\',`g305` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g306` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الشهر المحاسبي (Calendar Month)\',`g307` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بداية الشهر\',`g308` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نهاية الشهر\',`g309` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رصيد أول الشهر\',`g310` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الإقفالات التعاقدية بالشهر\',`g311` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإقفالات التعاقدية (مراجع)\',`g312` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستحق خلال الشهر\',`g313` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المدفوعات الفعلية خلال الشهر\',`g314` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المخصص خلال الشهر\',`g315` VARCHAR(190) NULL DEFAULT NULL COMMENT \'دفعات مقدمة/غير مخصصة\',`g316` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتأخر خلال الشهر\',`g317` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رصيد آخر الشهر\',`g318` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اختبار الترحيل الشهري\',`g319` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مطابقة كشف الممول\',`g320` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الإقفال الشهري\',`g321` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعد\',`g322` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g323` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g324` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g325` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g326` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_06f8e82c_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'FIN-16 - اقفال شهري لعقد تمويل\'';
if ($conn->query($sql)) { echo '+ جدول fin_monthly_close_stmt
'; }
else { echo 'x fin_monthly_close_stmt: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `fin_capital_balance` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g365` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود العملية\',`g366` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الممول\',`g367` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم الممول (بحث)\',`g368` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g369` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رأس المال المعتمد\',`g370` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المسدد من الأصل\',`g371` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتبقي من الأصل\',`g372` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العائد التعاقدي\',`g373` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المدفوع للعائد\',`g374` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتبقي من العائد\',`g375` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إجمالي المدفوع (الدفتر)\',`g376` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إجمالي الالتزام المتبقي\',`g377` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر حركة\',`g378` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة المالية\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_dd119daa_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'FIN-20 - رصيد راس مال لعملية تمويل\'';
if ($conn->query($sql)) { echo '+ جدول fin_capital_balance
'; }
else { echo 'x fin_capital_balance: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `fin_asset_disposal` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g379` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الانتقال\',`g380` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النطاق\',`g381` VARCHAR(190) NULL DEFAULT NULL COMMENT \'FOP_ID\',`g382` VARCHAR(190) NULL DEFAULT NULL COMMENT \'FCON_ID\',`g383` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Financier_ID\',`g384` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود العين/الآلية\',`g385` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ترتيب الانتقال\',`g386` VARCHAR(190) NULL DEFAULT NULL COMMENT \'من (المالك السابق)\',`g387` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إلى (المالك الجديد)\',`g388` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحصة %\',`g389` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التاريخ\',`g390` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة البيع\',`g391` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g392` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الخروج\',`g393` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستند\',`g394` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاعتماد\',`g395` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحجية\',`g396` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g397` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Source_Row_Ref\',`g398` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_40608314_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'FIN-23 - انتقال ملكية اصل واحد\'';
if ($conn->query($sql)) { echo '+ جدول fin_asset_disposal
'; }
else { echo 'x fin_asset_disposal: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `fin_migration_map` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g443` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النطاق\',`g444` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المصدر/الشيت السابق\',`g445` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العمود/الوصف\',`g446` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوجهة/القرار\',`g447` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التصنيف\',`g448` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظة\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_d768b0f4_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'FIN-27 - سطر خريطة ترحيل\'';
if ($conn->query($sql)) { echo '+ جدول fin_migration_map
'; }
else { echo 'x fin_migration_map: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `fin_close_audit` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g449` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القسم\',`g450` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البند\',`g451` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة/النتيجة\',`g452` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g453` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظة\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_cb287638_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'FIN-28 - بند مراجعة اغلاق\'';
if ($conn->query($sql)) { echo '+ جدول fin_close_audit
'; }
else { echo 'x fin_close_audit: ' . $conn->error . chr(10); }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g31'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g31 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g31` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود الجهة'")) {
    echo "+ fin_financier_contact.g31\n";
} else { echo "x fin_financier_contact.g31: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g32'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g32 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g32` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود الممول'")) {
    echo "+ fin_financier_contact.g32\n";
} else { echo "x fin_financier_contact.g32: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g33'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g33 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g33` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اسم الممول (بحث)'")) {
    echo "+ fin_financier_contact.g33\n";
} else { echo "x fin_financier_contact.g33: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g34'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g34 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g34` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الاسم'")) {
    echo "+ fin_financier_contact.g34\n";
} else { echo "x fin_financier_contact.g34: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g35'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g35 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g35` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الدور'")) {
    echo "+ fin_financier_contact.g35\n";
} else { echo "x fin_financier_contact.g35: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g36'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g36 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g36` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مفوض توقيع؟'")) {
    echo "+ fin_financier_contact.g36\n";
} else { echo "x fin_financier_contact.g36: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g37'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g37 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g37` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الهاتف'")) {
    echo "+ fin_financier_contact.g37\n";
} else { echo "x fin_financier_contact.g37: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g38'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g38 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g38` VARCHAR(190) NULL DEFAULT NULL COMMENT 'البريد'")) {
    echo "+ fin_financier_contact.g38\n";
} else { echo "x fin_financier_contact.g38: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g39'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g39 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g39` VARCHAR(190) NULL DEFAULT NULL COMMENT 'سريان من'")) {
    echo "+ fin_financier_contact.g39\n";
} else { echo "x fin_financier_contact.g39: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g40'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g40 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g40` VARCHAR(190) NULL DEFAULT NULL COMMENT 'إلى'")) {
    echo "+ fin_financier_contact.g40\n";
} else { echo "x fin_financier_contact.g40: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g41'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g41 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g41` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الحالة'")) {
    echo "+ fin_financier_contact.g41\n";
} else { echo "x fin_financier_contact.g41: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g42'");
if ($q && $q->num_rows) { echo "= fin_financier_contact.g42 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_financier_contact` ADD COLUMN `g42` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ fin_financier_contact.g42\n";
} else { echo "x fin_financier_contact.g42: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g43'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g43 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g43` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود الحاجة'")) {
    echo "+ fin_funding_need.g43\n";
} else { echo "x fin_funding_need.g43: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g44'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g44 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g44` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العملية الناتجة'")) {
    echo "+ fin_funding_need.g44\n";
} else { echo "x fin_funding_need.g44: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g45'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g45 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g45` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Selected_Financier_ID نتيجة لاحقة بعد العروض والاختيار'")) {
    echo "+ fin_funding_need.g45\n";
} else { echo "x fin_funding_need.g45: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g46'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g46 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g46` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Requesting_Department الإدارة الطالبة'")) {
    echo "+ fin_funding_need.g46\n";
} else { echo "x fin_funding_need.g46: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g47'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g47 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g47` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Requested_By الطالب'")) {
    echo "+ fin_funding_need.g47\n";
} else { echo "x fin_funding_need.g47: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g48'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g48 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g48` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Required_By_Date'")) {
    echo "+ fin_funding_need.g48\n";
} else { echo "x fin_funding_need.g48: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g49'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g49 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g49` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Priority'")) {
    echo "+ fin_funding_need.g49\n";
} else { echo "x fin_funding_need.g49: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g50'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g50 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g50` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Need_Approval اعتماد الحاجة'")) {
    echo "+ fin_funding_need.g50\n";
} else { echo "x fin_funding_need.g50: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g51'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g51 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g51` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Approved_Amount'")) {
    echo "+ fin_funding_need.g51\n";
} else { echo "x fin_funding_need.g51: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g52'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g52 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g52` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اسم الممول (بحث)'")) {
    echo "+ fin_funding_need.g52\n";
} else { echo "x fin_funding_need.g52: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g53'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g53 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g53` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الأصل المطلوب'")) {
    echo "+ fin_funding_need.g53\n";
} else { echo "x fin_funding_need.g53: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g54'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g54 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g54` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العين'")) {
    echo "+ fin_funding_need.g54\n";
} else { echo "x fin_funding_need.g54: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g55'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g55 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g55` VARCHAR(190) NULL DEFAULT NULL COMMENT 'القيمة (من العملية)'")) {
    echo "+ fin_funding_need.g55\n";
} else { echo "x fin_funding_need.g55: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g56'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g56 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g56` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ fin_funding_need.g56\n";
} else { echo "x fin_funding_need.g56: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g57'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g57 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g57` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نموذج التمويل'")) {
    echo "+ fin_funding_need.g57\n";
} else { echo "x fin_funding_need.g57: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g58'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g58 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g58` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Need_Date'")) {
    echo "+ fin_funding_need.g58\n";
} else { echo "x fin_funding_need.g58: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g59'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g59 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g59` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Need_Must_Precede'")) {
    echo "+ fin_funding_need.g59\n";
} else { echo "x fin_funding_need.g59: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g60'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g60 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g60` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المشروع/عقد العميل'")) {
    echo "+ fin_funding_need.g60\n";
} else { echo "x fin_funding_need.g60: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g61'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g61 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g61` VARCHAR(190) NULL DEFAULT NULL COMMENT 'سبب الحاجة'")) {
    echo "+ fin_funding_need.g61\n";
} else { echo "x fin_funding_need.g61: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g62'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g62 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g62` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Record_Basis'")) {
    echo "+ fin_funding_need.g62\n";
} else { echo "x fin_funding_need.g62: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g63'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g63 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g63` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Derivation_Rule'")) {
    echo "+ fin_funding_need.g63\n";
} else { echo "x fin_funding_need.g63: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g64'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g64 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g64` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Confidence'")) {
    echo "+ fin_funding_need.g64\n";
} else { echo "x fin_funding_need.g64: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g65'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g65 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g65` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Needs_Review'")) {
    echo "+ fin_funding_need.g65\n";
} else { echo "x fin_funding_need.g65: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g66'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g66 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g66` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ fin_funding_need.g66\n";
} else { echo "x fin_funding_need.g66: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g67'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g67 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g67` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Source_Row_Ref'")) {
    echo "+ fin_funding_need.g67\n";
} else { echo "x fin_funding_need.g67: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g68'");
if ($q && $q->num_rows) { echo "= fin_funding_need.g68 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_need` ADD COLUMN `g68` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات'")) {
    echo "+ fin_funding_need.g68\n";
} else { echo "x fin_funding_need.g68: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g69'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g69 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g69` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العرض'")) {
    echo "+ fin_funding_offer.g69\n";
} else { echo "x fin_funding_offer.g69: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g70'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g70 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g70` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود الحاجة'")) {
    echo "+ fin_funding_offer.g70\n";
} else { echo "x fin_funding_offer.g70: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g71'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g71 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g71` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العملية الناتجة'")) {
    echo "+ fin_funding_offer.g71\n";
} else { echo "x fin_funding_offer.g71: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g72'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g72 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g72` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود الممول'")) {
    echo "+ fin_funding_offer.g72\n";
} else { echo "x fin_funding_offer.g72: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g73'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g73 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g73` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اسم الممول (بحث)'")) {
    echo "+ fin_funding_offer.g73\n";
} else { echo "x fin_funding_offer.g73: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g74'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g74 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g74` VARCHAR(190) NULL DEFAULT NULL COMMENT 'صفة العرض'")) {
    echo "+ fin_funding_offer.g74\n";
} else { echo "x fin_funding_offer.g74: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g75'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g75 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g75` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم النسخة'")) {
    echo "+ fin_funding_offer.g75\n";
} else { echo "x fin_funding_offer.g75: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g76'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g76 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g76` VARCHAR(190) NULL DEFAULT NULL COMMENT 'النسخة الأساس'")) {
    echo "+ fin_funding_offer.g76\n";
} else { echo "x fin_funding_offer.g76: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g77'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g77 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g77` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ما الذي تغير عن سابقتها'")) {
    echo "+ fin_funding_offer.g77\n";
} else { echo "x fin_funding_offer.g77: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g78'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g78 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g78` VARCHAR(190) NULL DEFAULT NULL COMMENT 'من اقترح التغيير'")) {
    echo "+ fin_funding_offer.g78\n";
} else { echo "x fin_funding_offer.g78: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g79'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g79 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g79` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ التغيير'")) {
    echo "+ fin_funding_offer.g79\n";
} else { echo "x fin_funding_offer.g79: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g80'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g80 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g80` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة التفاوض'")) {
    echo "+ fin_funding_offer.g80\n";
} else { echo "x fin_funding_offer.g80: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g81'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g81 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g81` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نموذج التمويل'")) {
    echo "+ fin_funding_offer.g81\n";
} else { echo "x fin_funding_offer.g81: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g82'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g82 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g82` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رأس المال'")) {
    echo "+ fin_funding_offer.g82\n";
} else { echo "x fin_funding_offer.g82: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g83'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g83 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g83` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ fin_funding_offer.g83\n";
} else { echo "x fin_funding_offer.g83: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g84'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g84 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g84` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نسبة الأرباح %'")) {
    echo "+ fin_funding_offer.g84\n";
} else { echo "x fin_funding_offer.g84: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g85'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g85 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g85` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة الأرباح'")) {
    echo "+ fin_funding_offer.g85\n";
} else { echo "x fin_funding_offer.g85: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g86'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g86 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g86` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المقدم'")) {
    echo "+ fin_funding_offer.g86\n";
} else { echo "x fin_funding_offer.g86: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g87'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g87 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g87` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المدة (شهر)'")) {
    echo "+ fin_funding_offer.g87\n";
} else { echo "x fin_funding_offer.g87: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g88'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g88 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g88` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عدد الأقساط'")) {
    echo "+ fin_funding_offer.g88\n";
} else { echo "x fin_funding_offer.g88: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g89'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g89 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g89` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة القسط'")) {
    echo "+ fin_funding_offer.g89\n";
} else { echo "x fin_funding_offer.g89: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g90'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g90 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g90` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الضمانات'")) {
    echo "+ fin_funding_offer.g90\n";
} else { echo "x fin_funding_offer.g90: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g91'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g91 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g91` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ العرض'")) {
    echo "+ fin_funding_offer.g91\n";
} else { echo "x fin_funding_offer.g91: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g92'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g92 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g92` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Offer_Must_Precede'")) {
    echo "+ fin_funding_offer.g92\n";
} else { echo "x fin_funding_offer.g92: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g93'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g93 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g93` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عروض المنافسين'")) {
    echo "+ fin_funding_offer.g93\n";
} else { echo "x fin_funding_offer.g93: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g94'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g94 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g94` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Record_Basis'")) {
    echo "+ fin_funding_offer.g94\n";
} else { echo "x fin_funding_offer.g94: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g95'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g95 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g95` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Derivation_Rule'")) {
    echo "+ fin_funding_offer.g95\n";
} else { echo "x fin_funding_offer.g95: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g96'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g96 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g96` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Confidence'")) {
    echo "+ fin_funding_offer.g96\n";
} else { echo "x fin_funding_offer.g96: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g97'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g97 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g97` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Needs_Review'")) {
    echo "+ fin_funding_offer.g97\n";
} else { echo "x fin_funding_offer.g97: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g98'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g98 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g98` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ fin_funding_offer.g98\n";
} else { echo "x fin_funding_offer.g98: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g99'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g99 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g99` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Source_Row_Ref'")) {
    echo "+ fin_funding_offer.g99\n";
} else { echo "x fin_funding_offer.g99: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g100'");
if ($q && $q->num_rows) { echo "= fin_funding_offer.g100 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_funding_offer` ADD COLUMN `g100` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات'")) {
    echo "+ fin_funding_offer.g100\n";
} else { echo "x fin_funding_offer.g100: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g101'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g101 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g101` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود المراجعة'")) {
    echo "+ fin_precontract_review.g101\n";
} else { echo "x fin_precontract_review.g101: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g102'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g102 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g102` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع الحاجة + العرض المختار + مسودة العقد'")) {
    echo "+ fin_precontract_review.g102\n";
} else { echo "x fin_precontract_review.g102: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g103'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g103 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g103` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود الممول'")) {
    echo "+ fin_precontract_review.g103\n";
} else { echo "x fin_precontract_review.g103: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g104'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g104 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g104` VARCHAR(190) NULL DEFAULT NULL COMMENT 'واقعة الاختيار والتعاقد'")) {
    echo "+ fin_precontract_review.g104\n";
} else { echo "x fin_precontract_review.g104: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g105'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g105 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g105` VARCHAR(190) NULL DEFAULT NULL COMMENT 'أهلية الممول (KYC/سجل)'")) {
    echo "+ fin_precontract_review.g105\n";
} else { echo "x fin_precontract_review.g105: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g106'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g106 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g106` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اكتمال المستندات'")) {
    echo "+ fin_precontract_review.g106\n";
} else { echo "x fin_precontract_review.g106: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g107'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g107 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g107` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مراجعة النموذج'")) {
    echo "+ fin_precontract_review.g107\n";
} else { echo "x fin_precontract_review.g107: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g108'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g108 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g108` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مراجعة رأس المال والعائد'")) {
    echo "+ fin_precontract_review.g108\n";
} else { echo "x fin_precontract_review.g108: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g109'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g109 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g109` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مراجعة السداد والملكية'")) {
    echo "+ fin_precontract_review.g109\n";
} else { echo "x fin_precontract_review.g109: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g110'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g110 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g110` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الضمانات'")) {
    echo "+ fin_precontract_review.g110\n";
} else { echo "x fin_precontract_review.g110: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g111'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g111 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g111` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المعالجة المحاسبية المطلوبة'")) {
    echo "+ fin_precontract_review.g111\n";
} else { echo "x fin_precontract_review.g111: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g112'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g112 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g112` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المراجع'")) {
    echo "+ fin_precontract_review.g112\n";
} else { echo "x fin_precontract_review.g112: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g113'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g113 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g113` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المعتمد'")) {
    echo "+ fin_precontract_review.g113\n";
} else { echo "x fin_precontract_review.g113: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g114'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g114 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g114` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Review_Must_Precede'")) {
    echo "+ fin_precontract_review.g114\n";
} else { echo "x fin_precontract_review.g114: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g115'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g115 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g115` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Record_Basis'")) {
    echo "+ fin_precontract_review.g115\n";
} else { echo "x fin_precontract_review.g115: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g116'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g116 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g116` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Derivation_Rule'")) {
    echo "+ fin_precontract_review.g116\n";
} else { echo "x fin_precontract_review.g116: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g117'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g117 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g117` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Confidence'")) {
    echo "+ fin_precontract_review.g117\n";
} else { echo "x fin_precontract_review.g117: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g118'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g118 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g118` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Needs_Review'")) {
    echo "+ fin_precontract_review.g118\n";
} else { echo "x fin_precontract_review.g118: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g119'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g119 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g119` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ fin_precontract_review.g119\n";
} else { echo "x fin_precontract_review.g119: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g120'");
if ($q && $q->num_rows) { echo "= fin_precontract_review.g120 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_precontract_review` ADD COLUMN `g120` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Source_Row_Ref'")) {
    echo "+ fin_precontract_review.g120\n";
} else { echo "x fin_precontract_review.g120: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g121'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g121 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g121` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العقد'")) {
    echo "+ fin_finance_contract.g121\n";
} else { echo "x fin_finance_contract.g121: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g122'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g122 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g122` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مصدر العقد'")) {
    echo "+ fin_finance_contract.g122\n";
} else { echo "x fin_finance_contract.g122: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g123'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g123 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g123` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع العقد بالمصدر'")) {
    echo "+ fin_finance_contract.g123\n";
} else { echo "x fin_finance_contract.g123: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g124'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g124 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g124` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المرجع الخارجي (رقم عقد الممول)'")) {
    echo "+ fin_finance_contract.g124\n";
} else { echo "x fin_finance_contract.g124: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g125'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g125 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g125` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الكيان المتعاقد (الشركة)'")) {
    echo "+ fin_finance_contract.g125\n";
} else { echo "x fin_finance_contract.g125: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g126'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g126 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g126` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم النسخة'")) {
    echo "+ fin_finance_contract.g126\n";
} else { echo "x fin_finance_contract.g126: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g127'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g127 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g127` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ التوقيع'")) {
    echo "+ fin_finance_contract.g127\n";
} else { echo "x fin_finance_contract.g127: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g128'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g128 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g128` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود الممول'")) {
    echo "+ fin_finance_contract.g128\n";
} else { echo "x fin_finance_contract.g128: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g129'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g129 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g129` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اسم الممول (بحث)'")) {
    echo "+ fin_finance_contract.g129\n";
} else { echo "x fin_finance_contract.g129: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g130'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g130 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g130` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نموذج التمويل'")) {
    echo "+ fin_finance_contract.g130\n";
} else { echo "x fin_finance_contract.g130: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g131'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g131 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g131` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ fin_finance_contract.g131\n";
} else { echo "x fin_finance_contract.g131: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g132'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g132 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g132` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رأس المال'")) {
    echo "+ fin_finance_contract.g132\n";
} else { echo "x fin_finance_contract.g132: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g133'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g133 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g133` VARCHAR(190) NULL DEFAULT NULL COMMENT 'بداية العقد'")) {
    echo "+ fin_finance_contract.g133\n";
} else { echo "x fin_finance_contract.g133: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g134'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g134 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g134` VARCHAR(190) NULL DEFAULT NULL COMMENT 'آخر حركة'")) {
    echo "+ fin_finance_contract.g134\n";
} else { echo "x fin_finance_contract.g134: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g135'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g135 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g135` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المدة (شهر)'")) {
    echo "+ fin_finance_contract.g135\n";
} else { echo "x fin_finance_contract.g135: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g136'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g136 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g136` VARCHAR(190) NULL DEFAULT NULL COMMENT 'النهاية التعاقدية'")) {
    echo "+ fin_finance_contract.g136\n";
} else { echo "x fin_finance_contract.g136: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g137'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g137 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g137` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة المستند'")) {
    echo "+ fin_finance_contract.g137\n";
} else { echo "x fin_finance_contract.g137: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g138'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g138 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g138` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة المراجعة القانونية'")) {
    echo "+ fin_finance_contract.g138\n";
} else { echo "x fin_finance_contract.g138: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g139'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g139 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g139` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة الاعتماد'")) {
    echo "+ fin_finance_contract.g139\n";
} else { echo "x fin_finance_contract.g139: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g140'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g140 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g140` VARCHAR(190) NULL DEFAULT NULL COMMENT 'من اعتمد'")) {
    echo "+ fin_finance_contract.g140\n";
} else { echo "x fin_finance_contract.g140: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g141'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g141 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g141` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد'")) {
    echo "+ fin_finance_contract.g141\n";
} else { echo "x fin_finance_contract.g141: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g142'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g142 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g142` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع آخر ملحق نافذ'")) {
    echo "+ fin_finance_contract.g142\n";
} else { echo "x fin_finance_contract.g142: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g143'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g143 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g143` VARCHAR(190) NULL DEFAULT NULL COMMENT 'آلية الإنهاء/الإقفال'")) {
    echo "+ fin_finance_contract.g143\n";
} else { echo "x fin_finance_contract.g143: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g144'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g144 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g144` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عدد العمليات تحته'")) {
    echo "+ fin_finance_contract.g144\n";
} else { echo "x fin_finance_contract.g144: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g145'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g145 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g145` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة العقد'")) {
    echo "+ fin_finance_contract.g145\n";
} else { echo "x fin_finance_contract.g145: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g146'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g146 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g146` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الحجية'")) {
    echo "+ fin_finance_contract.g146\n";
} else { echo "x fin_finance_contract.g146: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g147'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g147 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g147` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ fin_finance_contract.g147\n";
} else { echo "x fin_finance_contract.g147: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g148'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g148 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g148` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Source_Row_Ref'")) {
    echo "+ fin_finance_contract.g148\n";
} else { echo "x fin_finance_contract.g148: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g149'");
if ($q && $q->num_rows) { echo "= fin_finance_contract.g149 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_finance_contract` ADD COLUMN `g149` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات'")) {
    echo "+ fin_finance_contract.g149\n";
} else { echo "x fin_finance_contract.g149: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g150'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g150 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g150` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العقد'")) {
    echo "+ fin_contract_term.g150\n";
} else { echo "x fin_contract_term.g150: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g151'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g151 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g151` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العملية'")) {
    echo "+ fin_contract_term.g151\n";
} else { echo "x fin_contract_term.g151: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g152'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g152 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g152` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود الممول'")) {
    echo "+ fin_contract_term.g152\n";
} else { echo "x fin_contract_term.g152: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g153'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g153 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g153` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اسم الممول (بحث)'")) {
    echo "+ fin_contract_term.g153\n";
} else { echo "x fin_contract_term.g153: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g154'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g154 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g154` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نموذج التمويل'")) {
    echo "+ fin_contract_term.g154\n";
} else { echo "x fin_contract_term.g154: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g155'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g155 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g155` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ fin_contract_term.g155\n";
} else { echo "x fin_contract_term.g155: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g156'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g156 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g156` VARCHAR(190) NULL DEFAULT NULL COMMENT 'سريان من'")) {
    echo "+ fin_contract_term.g156\n";
} else { echo "x fin_contract_term.g156: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g157'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g157 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g157` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رأس المال'")) {
    echo "+ fin_contract_term.g157\n";
} else { echo "x fin_contract_term.g157: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g158'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g158 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g158` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نسبة المقدم %'")) {
    echo "+ fin_contract_term.g158\n";
} else { echo "x fin_contract_term.g158: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g159'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g159 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g159` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة المقدم'")) {
    echo "+ fin_contract_term.g159\n";
} else { echo "x fin_contract_term.g159: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g160'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g160 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g160` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نسبة الأرباح %'")) {
    echo "+ fin_contract_term.g160\n";
} else { echo "x fin_contract_term.g160: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g161'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g161 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g161` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة الأرباح التعاقدية'")) {
    echo "+ fin_contract_term.g161\n";
} else { echo "x fin_contract_term.g161: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g162'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g162 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g162` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رسوم إدارية'")) {
    echo "+ fin_contract_term.g162\n";
} else { echo "x fin_contract_term.g162: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g163'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g163 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g163` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رسوم تأمين'")) {
    echo "+ fin_contract_term.g163\n";
} else { echo "x fin_contract_term.g163: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g164'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g164 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g164` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المدة (شهر)'")) {
    echo "+ fin_contract_term.g164\n";
} else { echo "x fin_contract_term.g164: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g165'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g165 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g165` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نظام الأقساط'")) {
    echo "+ fin_contract_term.g165\n";
} else { echo "x fin_contract_term.g165: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g166'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g166 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g166` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عدد الأقساط'")) {
    echo "+ fin_contract_term.g166\n";
} else { echo "x fin_contract_term.g166: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g167'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g167 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g167` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة القسط'")) {
    echo "+ fin_contract_term.g167\n";
} else { echo "x fin_contract_term.g167: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g168'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g168 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g168` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تحويل الملكية في نهاية التمويل التفصيل'")) {
    echo "+ fin_contract_term.g168\n";
} else { echo "x fin_contract_term.g168: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g169'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g169 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g169` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حفظ مستندات الملكية التفصيل'")) {
    echo "+ fin_contract_term.g169\n";
} else { echo "x fin_contract_term.g169: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g170'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g170 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g170` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الحجية'")) {
    echo "+ fin_contract_term.g170\n";
} else { echo "x fin_contract_term.g170: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g171'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g171 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g171` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ fin_contract_term.g171\n";
} else { echo "x fin_contract_term.g171: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g172'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g172 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g172` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Source_Row_Ref'")) {
    echo "+ fin_contract_term.g172\n";
} else { echo "x fin_contract_term.g172: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g173'");
if ($q && $q->num_rows) { echo "= fin_contract_term.g173 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_term` ADD COLUMN `g173` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات'")) {
    echo "+ fin_contract_term.g173\n";
} else { echo "x fin_contract_term.g173: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g174'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g174 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g174` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العقد'")) {
    echo "+ fin_contract_covenant.g174\n";
} else { echo "x fin_contract_covenant.g174: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g175'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g175 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g175` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود الممول'")) {
    echo "+ fin_contract_covenant.g175\n";
} else { echo "x fin_contract_covenant.g175: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g176'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g176 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g176` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اسم الممول (بحث)'")) {
    echo "+ fin_contract_covenant.g176\n";
} else { echo "x fin_contract_covenant.g176: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g177'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g177 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g177` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نموذج التمويل'")) {
    echo "+ fin_contract_covenant.g177\n";
} else { echo "x fin_contract_covenant.g177: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g178'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g178 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g178` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مستوى الحجية'")) {
    echo "+ fin_contract_covenant.g178\n";
} else { echo "x fin_contract_covenant.g178: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g179'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g179 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g179` VARCHAR(190) NULL DEFAULT NULL COMMENT 'توفير رأس المال'")) {
    echo "+ fin_contract_covenant.g179\n";
} else { echo "x fin_contract_covenant.g179: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g180'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g180 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g180` VARCHAR(190) NULL DEFAULT NULL COMMENT 'توقيت إتاحة التمويل'")) {
    echo "+ fin_contract_covenant.g180\n";
} else { echo "x fin_contract_covenant.g180: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g181'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g181 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g181` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اختيار الأصل'")) {
    echo "+ fin_contract_covenant.g181\n";
} else { echo "x fin_contract_covenant.g181: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g182'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g182 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g182` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اختيار البائع'")) {
    echo "+ fin_contract_covenant.g182\n";
} else { echo "x fin_contract_covenant.g182: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g183'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g183 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g183` VARCHAR(190) NULL DEFAULT NULL COMMENT 'دفع قيمة الأصل للبائع'")) {
    echo "+ fin_contract_covenant.g183\n";
} else { echo "x fin_contract_covenant.g183: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g184'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g184 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g184` VARCHAR(190) NULL DEFAULT NULL COMMENT 'التسليم والاستلام'")) {
    echo "+ fin_contract_covenant.g184\n";
} else { echo "x fin_contract_covenant.g184: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g185'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g185 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g185` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الفحص والقبول'")) {
    echo "+ fin_contract_covenant.g185\n";
} else { echo "x fin_contract_covenant.g185: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g186'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g186 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g186` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تسجيل الأصل'")) {
    echo "+ fin_contract_covenant.g186\n";
} else { echo "x fin_contract_covenant.g186: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g187'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g187 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g187` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الترخيص'")) {
    echo "+ fin_contract_covenant.g187\n";
} else { echo "x fin_contract_covenant.g187: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g188'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g188 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g188` VARCHAR(190) NULL DEFAULT NULL COMMENT 'التأمين'")) {
    echo "+ fin_contract_covenant.g188\n";
} else { echo "x fin_contract_covenant.g188: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g189'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g189 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g189` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رسوم التأمين'")) {
    echo "+ fin_contract_covenant.g189\n";
} else { echo "x fin_contract_covenant.g189: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g190'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g190 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g190` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الضرائب والرسوم'")) {
    echo "+ fin_contract_covenant.g190\n";
} else { echo "x fin_contract_covenant.g190: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g191'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g191 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g191` VARCHAR(190) NULL DEFAULT NULL COMMENT 'التشغيل'")) {
    echo "+ fin_contract_covenant.g191\n";
} else { echo "x fin_contract_covenant.g191: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g192'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g192 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g192` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الصيانة'")) {
    echo "+ fin_contract_covenant.g192\n";
} else { echo "x fin_contract_covenant.g192: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g193'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g193 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g193` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قطع الغيار'")) {
    echo "+ fin_contract_covenant.g193\n";
} else { echo "x fin_contract_covenant.g193: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g194'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g194 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g194` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مخاطر الهلاك والتلف'")) {
    echo "+ fin_contract_covenant.g194\n";
} else { echo "x fin_contract_covenant.g194: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g195'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g195 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g195` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حفظ مستندات الملكية'")) {
    echo "+ fin_contract_covenant.g195\n";
} else { echo "x fin_contract_covenant.g195: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g196'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g196 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g196` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الضمانات والرهن'")) {
    echo "+ fin_contract_covenant.g196\n";
} else { echo "x fin_contract_covenant.g196: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g197'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g197 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g197` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مسؤولية التعطل وعدم الانتفاع'")) {
    echo "+ fin_contract_covenant.g197\n";
} else { echo "x fin_contract_covenant.g197: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g198'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g198 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g198` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تحويل الملكية في نهاية التمويل'")) {
    echo "+ fin_contract_covenant.g198\n";
} else { echo "x fin_contract_covenant.g198: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g199'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g199 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g199` VARCHAR(190) NULL DEFAULT NULL COMMENT 'التسوية المبكرة'")) {
    echo "+ fin_contract_covenant.g199\n";
} else { echo "x fin_contract_covenant.g199: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g200'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g200 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g200` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رسوم التمويل الإدارية'")) {
    echo "+ fin_contract_covenant.g200\n";
} else { echo "x fin_contract_covenant.g200: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g201'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g201 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g201` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الإخطارات'")) {
    echo "+ fin_contract_covenant.g201\n";
} else { echo "x fin_contract_covenant.g201: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g202'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g202 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g202` VARCHAR(190) NULL DEFAULT NULL COMMENT 'توفير المستندات'")) {
    echo "+ fin_contract_covenant.g202\n";
} else { echo "x fin_contract_covenant.g202: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g203'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g203 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g203` VARCHAR(190) NULL DEFAULT NULL COMMENT 'إجراءات الإقفال'")) {
    echo "+ fin_contract_covenant.g203\n";
} else { echo "x fin_contract_covenant.g203: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g204'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g204 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g204` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تفصيل الحسم (المرجع بسجل البنود)'")) {
    echo "+ fin_contract_covenant.g204\n";
} else { echo "x fin_contract_covenant.g204: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g205'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g205 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g205` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عدد الالتزامات المحسومة'")) {
    echo "+ fin_contract_covenant.g205\n";
} else { echo "x fin_contract_covenant.g205: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g206'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g206 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g206` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة اكتمال المصفوفة'")) {
    echo "+ fin_contract_covenant.g206\n";
} else { echo "x fin_contract_covenant.g206: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g207'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g207 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g207` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع العقد/المادة'")) {
    echo "+ fin_contract_covenant.g207\n";
} else { echo "x fin_contract_covenant.g207: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g208'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g208 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g208` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المعبئ'")) {
    echo "+ fin_contract_covenant.g208\n";
} else { echo "x fin_contract_covenant.g208: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g209'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g209 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g209` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ التعبئة'")) {
    echo "+ fin_contract_covenant.g209\n";
} else { echo "x fin_contract_covenant.g209: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g210'");
if ($q && $q->num_rows) { echo "= fin_contract_covenant.g210 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_covenant` ADD COLUMN `g210` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ fin_contract_covenant.g210\n";
} else { echo "x fin_contract_covenant.g210: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g211'");
if ($q && $q->num_rows) { echo "= financing_operations.g211 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g211` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العملية'")) {
    echo "+ financing_operations.g211\n";
} else { echo "x financing_operations.g211: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g212'");
if ($q && $q->num_rows) { echo "= financing_operations.g212 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g212` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العملية بالمصدر (ل03)'")) {
    echo "+ financing_operations.g212\n";
} else { echo "x financing_operations.g212: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g213'");
if ($q && $q->num_rows) { echo "= financing_operations.g213 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g213` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العقد'")) {
    echo "+ financing_operations.g213\n";
} else { echo "x financing_operations.g213: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g214'");
if ($q && $q->num_rows) { echo "= financing_operations.g214 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g214` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود الممول'")) {
    echo "+ financing_operations.g214\n";
} else { echo "x financing_operations.g214: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g215'");
if ($q && $q->num_rows) { echo "= financing_operations.g215 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g215` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اسم الممول (بحث)'")) {
    echo "+ financing_operations.g215\n";
} else { echo "x financing_operations.g215: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g216'");
if ($q && $q->num_rows) { echo "= financing_operations.g216 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g216` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نموذج التمويل'")) {
    echo "+ financing_operations.g216\n";
} else { echo "x financing_operations.g216: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g217'");
if ($q && $q->num_rows) { echo "= financing_operations.g217 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g217` VARCHAR(190) NULL DEFAULT NULL COMMENT 'النموذج الاقتصادي (المصدر)'")) {
    echo "+ financing_operations.g217\n";
} else { echo "x financing_operations.g217: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g218'");
if ($q && $q->num_rows) { echo "= financing_operations.g218 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g218` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ financing_operations.g218\n";
} else { echo "x financing_operations.g218: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g219'");
if ($q && $q->num_rows) { echo "= financing_operations.g219 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g219` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تصنيف العين'")) {
    echo "+ financing_operations.g219\n";
} else { echo "x financing_operations.g219: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g220'");
if ($q && $q->num_rows) { echo "= financing_operations.g220 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g220` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع العين'")) {
    echo "+ financing_operations.g220\n";
} else { echo "x financing_operations.g220: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g221'");
if ($q && $q->num_rows) { echo "= financing_operations.g221 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g221` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العين'")) {
    echo "+ financing_operations.g221\n";
} else { echo "x financing_operations.g221: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g222'");
if ($q && $q->num_rows) { echo "= financing_operations.g222 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g222` VARCHAR(190) NULL DEFAULT NULL COMMENT 'أول حركة'")) {
    echo "+ financing_operations.g222\n";
} else { echo "x financing_operations.g222: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g223'");
if ($q && $q->num_rows) { echo "= financing_operations.g223 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g223` VARCHAR(190) NULL DEFAULT NULL COMMENT 'آخر حركة'")) {
    echo "+ financing_operations.g223\n";
} else { echo "x financing_operations.g223: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g224'");
if ($q && $q->num_rows) { echo "= financing_operations.g224 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g224` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رأس المال المعتمد'")) {
    echo "+ financing_operations.g224\n";
} else { echo "x financing_operations.g224: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g225'");
if ($q && $q->num_rows) { echo "= financing_operations.g225 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g225` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مصدر رأس المال'")) {
    echo "+ financing_operations.g225\n";
} else { echo "x financing_operations.g225: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g226'");
if ($q && $q->num_rows) { echo "= financing_operations.g226 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g226` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نسبة الممول في الأصل'")) {
    echo "+ financing_operations.g226\n";
} else { echo "x financing_operations.g226: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g227'");
if ($q && $q->num_rows) { echo "= financing_operations.g227 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g227` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نسبة المقدم'")) {
    echo "+ financing_operations.g227\n";
} else { echo "x financing_operations.g227: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g228'");
if ($q && $q->num_rows) { echo "= financing_operations.g228 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g228` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة المقدم'")) {
    echo "+ financing_operations.g228\n";
} else { echo "x financing_operations.g228: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g229'");
if ($q && $q->num_rows) { echo "= financing_operations.g229 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g229` VARCHAR(190) NULL DEFAULT NULL COMMENT 'إضافة رأس مال'")) {
    echo "+ financing_operations.g229\n";
} else { echo "x financing_operations.g229: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g230'");
if ($q && $q->num_rows) { echo "= financing_operations.g230 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g230` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رسوم إدارية'")) {
    echo "+ financing_operations.g230\n";
} else { echo "x financing_operations.g230: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g231'");
if ($q && $q->num_rows) { echo "= financing_operations.g231 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g231` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رسوم تأمين'")) {
    echo "+ financing_operations.g231\n";
} else { echo "x financing_operations.g231: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g232'");
if ($q && $q->num_rows) { echo "= financing_operations.g232 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g232` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نسبة الأرباح'")) {
    echo "+ financing_operations.g232\n";
} else { echo "x financing_operations.g232: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g233'");
if ($q && $q->num_rows) { echo "= financing_operations.g233 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g233` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة الأرباح التعاقدية'")) {
    echo "+ financing_operations.g233\n";
} else { echo "x financing_operations.g233: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g234'");
if ($q && $q->num_rows) { echo "= financing_operations.g234 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g234` VARCHAR(190) NULL DEFAULT NULL COMMENT 'APR (المصدر)'")) {
    echo "+ financing_operations.g234\n";
} else { echo "x financing_operations.g234: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g235'");
if ($q && $q->num_rows) { echo "= financing_operations.g235 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g235` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المدة (شهر)'")) {
    echo "+ financing_operations.g235\n";
} else { echo "x financing_operations.g235: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g236'");
if ($q && $q->num_rows) { echo "= financing_operations.g236 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g236` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نظام الأقساط'")) {
    echo "+ financing_operations.g236\n";
} else { echo "x financing_operations.g236: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g237'");
if ($q && $q->num_rows) { echo "= financing_operations.g237 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g237` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عدد الأقساط'")) {
    echo "+ financing_operations.g237\n";
} else { echo "x financing_operations.g237: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g238'");
if ($q && $q->num_rows) { echo "= financing_operations.g238 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g238` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قيمة القسط'")) {
    echo "+ financing_operations.g238\n";
} else { echo "x financing_operations.g238: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g239'");
if ($q && $q->num_rows) { echo "= financing_operations.g239 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g239` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الاستحقاق بالدفتر'")) {
    echo "+ financing_operations.g239\n";
} else { echo "x financing_operations.g239: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g240'");
if ($q && $q->num_rows) { echo "= financing_operations.g240 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g240` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الدفعات بالدفتر'")) {
    echo "+ financing_operations.g240\n";
} else { echo "x financing_operations.g240: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g241'");
if ($q && $q->num_rows) { echo "= financing_operations.g241 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g241` VARCHAR(190) NULL DEFAULT NULL COMMENT 'صفوف الدفتر'")) {
    echo "+ financing_operations.g241\n";
} else { echo "x financing_operations.g241: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g242'");
if ($q && $q->num_rows) { echo "= financing_operations.g242 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g242` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عدد تغيرات العقد'")) {
    echo "+ financing_operations.g242\n";
} else { echo "x financing_operations.g242: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g243'");
if ($q && $q->num_rows) { echo "= financing_operations.g243 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g243` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة اكتمال البيانات (المصدر)'")) {
    echo "+ financing_operations.g243\n";
} else { echo "x financing_operations.g243: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g244'");
if ($q && $q->num_rows) { echo "= financing_operations.g244 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g244` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الحالة التشغيلية'")) {
    echo "+ financing_operations.g244\n";
} else { echo "x financing_operations.g244: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g245'");
if ($q && $q->num_rows) { echo "= financing_operations.g245 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g245` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الحجية'")) {
    echo "+ financing_operations.g245\n";
} else { echo "x financing_operations.g245: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g246'");
if ($q && $q->num_rows) { echo "= financing_operations.g246 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g246` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ financing_operations.g246\n";
} else { echo "x financing_operations.g246: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g247'");
if ($q && $q->num_rows) { echo "= financing_operations.g247 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g247` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Source_Row_Ref'")) {
    echo "+ financing_operations.g247\n";
} else { echo "x financing_operations.g247: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g248'");
if ($q && $q->num_rows) { echo "= financing_operations.g248 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g248` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات'")) {
    echo "+ financing_operations.g248\n";
} else { echo "x financing_operations.g248: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g249'");
if ($q && $q->num_rows) { echo "= financing_operations.g249 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g249` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نسبة اكتمال الدورة المستندية'")) {
    echo "+ financing_operations.g249\n";
} else { echo "x financing_operations.g249: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g250'");
if ($q && $q->num_rows) { echo "= financing_operations.g250 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g250` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حلقات الدورة المفقودة'")) {
    echo "+ financing_operations.g250\n";
} else { echo "x financing_operations.g250: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g251'");
if ($q && $q->num_rows) { echo "= financing_operations.g251 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g251` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Asset_Sourcing_Mode'")) {
    echo "+ financing_operations.g251\n";
} else { echo "x financing_operations.g251: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g252'");
if ($q && $q->num_rows) { echo "= financing_operations.g252 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `financing_operations` ADD COLUMN `g252` VARCHAR(190) NULL DEFAULT NULL COMMENT 'استثناء التسلسل'")) {
    echo "+ financing_operations.g252\n";
} else { echo "x financing_operations.g252: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g269'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g269 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g269` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Close_ID'")) {
    echo "+ fin_contract_close.g269\n";
} else { echo "x fin_contract_close.g269: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g270'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g270 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g270` VARCHAR(190) NULL DEFAULT NULL COMMENT 'FOP_ID'")) {
    echo "+ fin_contract_close.g270\n";
} else { echo "x fin_contract_close.g270: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g271'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g271 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g271` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Financier_ID'")) {
    echo "+ fin_contract_close.g271\n";
} else { echo "x fin_contract_close.g271: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g272'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g272 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g272` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ fin_contract_close.g272\n";
} else { echo "x fin_contract_close.g272: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g273'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g273 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g273` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع الفترة'")) {
    echo "+ fin_contract_close.g273\n";
} else { echo "x fin_contract_close.g273: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g274'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g274 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g274` VARCHAR(190) NULL DEFAULT NULL COMMENT 'بداية الفترة'")) {
    echo "+ fin_contract_close.g274\n";
} else { echo "x fin_contract_close.g274: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g275'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g275 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g275` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نهاية الفترة'")) {
    echo "+ fin_contract_close.g275\n";
} else { echo "x fin_contract_close.g275: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g276'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g276 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g276` VARCHAR(190) NULL DEFAULT NULL COMMENT 'شهر نهاية الفترة (وسم)'")) {
    echo "+ fin_contract_close.g276\n";
} else { echo "x fin_contract_close.g276: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g277'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g277 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g277` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Monthly_Close_ID'")) {
    echo "+ fin_contract_close.g277\n";
} else { echo "x fin_contract_close.g277: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g278'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g278 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g278` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رصيد أصل افتتاحي'")) {
    echo "+ fin_contract_close.g278\n";
} else { echo "x fin_contract_close.g278: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g279'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g279 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g279` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رصيد عائد افتتاحي'")) {
    echo "+ fin_contract_close.g279\n";
} else { echo "x fin_contract_close.g279: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g280'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g280 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g280` VARCHAR(190) NULL DEFAULT NULL COMMENT 'أصل مستحق بالفترة'")) {
    echo "+ fin_contract_close.g280\n";
} else { echo "x fin_contract_close.g280: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g281'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g281 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g281` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عائد مستحق بالفترة'")) {
    echo "+ fin_contract_close.g281\n";
} else { echo "x fin_contract_close.g281: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g282'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g282 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g282` VARCHAR(190) NULL DEFAULT NULL COMMENT 'إجمالي مستحق الفترة'")) {
    echo "+ fin_contract_close.g282\n";
} else { echo "x fin_contract_close.g282: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g283'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g283 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g283` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رسوم مستحقة'")) {
    echo "+ fin_contract_close.g283\n";
} else { echo "x fin_contract_close.g283: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g284'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g284 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g284` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تعديلات معتمدة ±'")) {
    echo "+ fin_contract_close.g284\n";
} else { echo "x fin_contract_close.g284: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g285'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g285 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g285` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مدفوعات مخصصة للفترة'")) {
    echo "+ fin_contract_close.g285\n";
} else { echo "x fin_contract_close.g285: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g286'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g286 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g286` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رصيد أصل ختامي'")) {
    echo "+ fin_contract_close.g286\n";
} else { echo "x fin_contract_close.g286: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g287'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g287 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g287` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رصيد عائد ختامي'")) {
    echo "+ fin_contract_close.g287\n";
} else { echo "x fin_contract_close.g287: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g288'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g288 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g288` VARCHAR(190) NULL DEFAULT NULL COMMENT 'إجمالي الرصيد الختامي'")) {
    echo "+ fin_contract_close.g288\n";
} else { echo "x fin_contract_close.g288: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g289'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g289 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g289` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المتأخر من الفترة'")) {
    echo "+ fin_contract_close.g289\n";
} else { echo "x fin_contract_close.g289: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g290'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g290 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g290` VARCHAR(190) NULL DEFAULT NULL COMMENT 'أيام التأخير'")) {
    echo "+ fin_contract_close.g290\n";
} else { echo "x fin_contract_close.g290: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g291'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g291 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g291` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الاستحقاق التالي'")) {
    echo "+ fin_contract_close.g291\n";
} else { echo "x fin_contract_close.g291: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g292'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g292 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g292` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة الإقفال'")) {
    echo "+ fin_contract_close.g292\n";
} else { echo "x fin_contract_close.g292: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g293'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g293 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g293` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المنشئ'")) {
    echo "+ fin_contract_close.g293\n";
} else { echo "x fin_contract_close.g293: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g294'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g294 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g294` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المراجع'")) {
    echo "+ fin_contract_close.g294\n";
} else { echo "x fin_contract_close.g294: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g295'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g295 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g295` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المعتمد'")) {
    echo "+ fin_contract_close.g295\n";
} else { echo "x fin_contract_close.g295: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g296'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g296 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g296` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد'")) {
    echo "+ fin_contract_close.g296\n";
} else { echo "x fin_contract_close.g296: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g297'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g297 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g297` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع كشف الحساب'")) {
    echo "+ fin_contract_close.g297\n";
} else { echo "x fin_contract_close.g297: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g298'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g298 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g298` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ fin_contract_close.g298\n";
} else { echo "x fin_contract_close.g298: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g299'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g299 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g299` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات'")) {
    echo "+ fin_contract_close.g299\n";
} else { echo "x fin_contract_close.g299: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g300'");
if ($q && $q->num_rows) { echo "= fin_contract_close.g300 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_contract_close` ADD COLUMN `g300` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اختبار الترحيل (Opening=Closing السابق)'")) {
    echo "+ fin_contract_close.g300\n";
} else { echo "x fin_contract_close.g300: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g327'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g327 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g327` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود السداد'")) {
    echo "+ fin_payment_order.g327\n";
} else { echo "x fin_payment_order.g327: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g328'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g328 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g328` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العملية'")) {
    echo "+ fin_payment_order.g328\n";
} else { echo "x fin_payment_order.g328: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g329'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g329 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g329` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ fin_payment_order.g329\n";
} else { echo "x fin_payment_order.g329: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g330'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g330 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g330` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المبلغ المدفوع (مجمع)'")) {
    echo "+ fin_payment_order.g330\n";
} else { echo "x fin_payment_order.g330: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g331'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g331 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g331` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عدد صفوف الدفتر'")) {
    echo "+ fin_payment_order.g331\n";
} else { echo "x fin_payment_order.g331: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g332'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g332 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g332` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الفترة'")) {
    echo "+ fin_payment_order.g332\n";
} else { echo "x fin_payment_order.g332: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g333'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g333 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g333` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود أمر الدفع'")) {
    echo "+ fin_payment_order.g333\n";
} else { echo "x fin_payment_order.g333: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g334'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g334 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g334` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معتمد الأمر'")) {
    echo "+ fin_payment_order.g334\n";
} else { echo "x fin_payment_order.g334: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g335'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g335 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g335` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المرجع البنكي'")) {
    echo "+ fin_payment_order.g335\n";
} else { echo "x fin_payment_order.g335: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g336'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g336 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g336` VARCHAR(190) NULL DEFAULT NULL COMMENT 'أمر الدفع'")) {
    echo "+ fin_payment_order.g336\n";
} else { echo "x fin_payment_order.g336: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g337'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g337 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g337` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الحجية'")) {
    echo "+ fin_payment_order.g337\n";
} else { echo "x fin_payment_order.g337: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g338'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g338 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g338` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ fin_payment_order.g338\n";
} else { echo "x fin_payment_order.g338: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g339'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g339 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g339` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Source_Row_Ref'")) {
    echo "+ fin_payment_order.g339\n";
} else { echo "x fin_payment_order.g339: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g340'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g340 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g340` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات'")) {
    echo "+ fin_payment_order.g340\n";
} else { echo "x fin_payment_order.g340: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g341'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g341 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g341` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الطلب'")) {
    echo "+ fin_payment_order.g341\n";
} else { echo "x fin_payment_order.g341: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g342'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g342 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g342` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المبلغ المطلوب'")) {
    echo "+ fin_payment_order.g342\n";
} else { echo "x fin_payment_order.g342: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g343'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g343 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g343` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المبلغ المعتمد'")) {
    echo "+ fin_payment_order.g343\n";
} else { echo "x fin_payment_order.g343: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g344'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g344 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g344` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة الأمر'")) {
    echo "+ fin_payment_order.g344\n";
} else { echo "x fin_payment_order.g344: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g345'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g345 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g345` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معتمد الأمر (مستقبلي)'")) {
    echo "+ fin_payment_order.g345\n";
} else { echo "x fin_payment_order.g345: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g346'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g346 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g346` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ التنفيذ الفعلي'")) {
    echo "+ fin_payment_order.g346\n";
} else { echo "x fin_payment_order.g346: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g347'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g347 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g347` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المبلغ المنفذ'")) {
    echo "+ fin_payment_order.g347\n";
} else { echo "x fin_payment_order.g347: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g348'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g348 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g348` VARCHAR(190) NULL DEFAULT NULL COMMENT 'البنك/طريقة السداد'")) {
    echo "+ fin_payment_order.g348\n";
} else { echo "x fin_payment_order.g348: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g349'");
if ($q && $q->num_rows) { echo "= fin_payment_order.g349 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_order` ADD COLUMN `g349` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة المطابقة'")) {
    echo "+ fin_payment_order.g349\n";
} else { echo "x fin_payment_order.g349: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g350'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g350 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g350` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود التخصيص'")) {
    echo "+ fin_payment_allocation.g350\n";
} else { echo "x fin_payment_allocation.g350: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g351'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g351 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g351` VARCHAR(190) NULL DEFAULT NULL COMMENT 'FOP_ID'")) {
    echo "+ fin_payment_allocation.g351\n";
} else { echo "x fin_payment_allocation.g351: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g352'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g352 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g352` VARCHAR(190) NULL DEFAULT NULL COMMENT 'FINS_ID'")) {
    echo "+ fin_payment_allocation.g352\n";
} else { echo "x fin_payment_allocation.g352: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g353'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g353 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g353` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Contractual_Close_ID'")) {
    echo "+ fin_payment_allocation.g353\n";
} else { echo "x fin_payment_allocation.g353: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g354'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g354 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g354` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Monthly_Close_ID'")) {
    echo "+ fin_payment_allocation.g354\n";
} else { echo "x fin_payment_allocation.g354: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g355'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g355 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g355` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع المكون'")) {
    echo "+ fin_payment_allocation.g355\n";
} else { echo "x fin_payment_allocation.g355: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g356'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g356 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g356` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ fin_payment_allocation.g356\n";
} else { echo "x fin_payment_allocation.g356: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g357'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g357 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g357` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المبلغ المخصص'")) {
    echo "+ fin_payment_allocation.g357\n";
} else { echo "x fin_payment_allocation.g357: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g358'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g358 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g358` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Outstanding قبل'")) {
    echo "+ fin_payment_allocation.g358\n";
} else { echo "x fin_payment_allocation.g358: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g359'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g359 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g359` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ التخصيص'")) {
    echo "+ fin_payment_allocation.g359\n";
} else { echo "x fin_payment_allocation.g359: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g360'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g360 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g360` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قاعدة التخصيص'")) {
    echo "+ fin_payment_allocation.g360\n";
} else { echo "x fin_payment_allocation.g360: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g361'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g361 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g361` VARCHAR(190) NULL DEFAULT NULL COMMENT 'علم تجاوز'")) {
    echo "+ fin_payment_allocation.g361\n";
} else { echo "x fin_payment_allocation.g361: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g362'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g362 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g362` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ fin_payment_allocation.g362\n";
} else { echo "x fin_payment_allocation.g362: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g363'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g363 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g363` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اختبار Σ لكل دفعة'")) {
    echo "+ fin_payment_allocation.g363\n";
} else { echo "x fin_payment_allocation.g363: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g364'");
if ($q && $q->num_rows) { echo "= fin_payment_allocation.g364 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_payment_allocation` ADD COLUMN `g364` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اختبار ≤ المستحق'")) {
    echo "+ fin_payment_allocation.g364\n";
} else { echo "x fin_payment_allocation.g364: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g399'");
if ($q && $q->num_rows) { echo "= fin_final_close.g399 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g399` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود العملية'")) {
    echo "+ fin_final_close.g399\n";
} else { echo "x fin_final_close.g399: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g400'");
if ($q && $q->num_rows) { echo "= fin_final_close.g400 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g400` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ fin_final_close.g400\n";
} else { echo "x fin_final_close.g400: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g401'");
if ($q && $q->num_rows) { echo "= fin_final_close.g401 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g401` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المتبقي من الأصل'")) {
    echo "+ fin_final_close.g401\n";
} else { echo "x fin_final_close.g401: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g402'");
if ($q && $q->num_rows) { echo "= fin_final_close.g402 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g402` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المتبقي من العائد'")) {
    echo "+ fin_final_close.g402\n";
} else { echo "x fin_final_close.g402: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g403'");
if ($q && $q->num_rows) { echo "= fin_final_close.g403 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g403` VARCHAR(190) NULL DEFAULT NULL COMMENT 'استحقاقات مفتوحة'")) {
    echo "+ fin_final_close.g403\n";
} else { echo "x fin_final_close.g403: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g404'");
if ($q && $q->num_rows) { echo "= fin_final_close.g404 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g404` VARCHAR(190) NULL DEFAULT NULL COMMENT 'انحرافات غير محسومة (مرجع ت22)'")) {
    echo "+ fin_final_close.g404\n";
} else { echo "x fin_final_close.g404: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g405'");
if ($q && $q->num_rows) { echo "= fin_final_close.g405 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g405` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حكم الملكية (مرجع ت12/ت23)'")) {
    echo "+ fin_final_close.g405\n";
} else { echo "x fin_final_close.g405: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g406'");
if ($q && $q->num_rows) { echo "= fin_final_close.g406 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g406` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تسوية مبكرة (FSET)'")) {
    echo "+ fin_final_close.g406\n";
} else { echo "x fin_final_close.g406: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g407'");
if ($q && $q->num_rows) { echo "= fin_final_close.g407 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g407` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة الإقفال'")) {
    echo "+ fin_final_close.g407\n";
} else { echo "x fin_final_close.g407: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g408'");
if ($q && $q->num_rows) { echo "= fin_final_close.g408 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g408` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات'")) {
    echo "+ fin_final_close.g408\n";
} else { echo "x fin_final_close.g408: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g409'");
if ($q && $q->num_rows) { echo "= fin_final_close.g409 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g409` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ طلب الإقفال'")) {
    echo "+ fin_final_close.g409\n";
} else { echo "x fin_final_close.g409: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g410'");
if ($q && $q->num_rows) { echo "= fin_final_close.g410 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g410` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الإقفال الفعلي'")) {
    echo "+ fin_final_close.g410\n";
} else { echo "x fin_final_close.g410: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g411'");
if ($q && $q->num_rows) { echo "= fin_final_close.g411 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g411` VARCHAR(190) NULL DEFAULT NULL COMMENT 'آخر إقفال دوري'")) {
    echo "+ fin_final_close.g411\n";
} else { echo "x fin_final_close.g411: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g412'");
if ($q && $q->num_rows) { echo "= fin_final_close.g412 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g412` VARCHAR(190) NULL DEFAULT NULL COMMENT 'آخر دفعة ومرجعها'")) {
    echo "+ fin_final_close.g412\n";
} else { echo "x fin_final_close.g412: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g413'");
if ($q && $q->num_rows) { echo "= fin_final_close.g413 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g413` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اكتمال نقل الملكية'")) {
    echo "+ fin_final_close.g413\n";
} else { echo "x fin_final_close.g413: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g414'");
if ($q && $q->num_rows) { echo "= fin_final_close.g414 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g414` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع مستند الملكية'")) {
    echo "+ fin_final_close.g414\n";
} else { echo "x fin_final_close.g414: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g415'");
if ($q && $q->num_rows) { echo "= fin_final_close.g415 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g415` VARCHAR(190) NULL DEFAULT NULL COMMENT 'إخلاء الطرف/شهادة الإقفال'")) {
    echo "+ fin_final_close.g415\n";
} else { echo "x fin_final_close.g415: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g416'");
if ($q && $q->num_rows) { echo "= fin_final_close.g416 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g416` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المراجع'")) {
    echo "+ fin_final_close.g416\n";
} else { echo "x fin_final_close.g416: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g417'");
if ($q && $q->num_rows) { echo "= fin_final_close.g417 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g417` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المعتمد'")) {
    echo "+ fin_final_close.g417\n";
} else { echo "x fin_final_close.g417: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g418'");
if ($q && $q->num_rows) { echo "= fin_final_close.g418 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_final_close` ADD COLUMN `g418` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد'")) {
    echo "+ fin_final_close.g418\n";
} else { echo "x fin_final_close.g418: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g419'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g419 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g419` VARCHAR(190) NULL DEFAULT NULL COMMENT 'USD'")) {
    echo "+ fin_ref_list.g419\n";
} else { echo "x fin_ref_list.g419: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g420'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g420 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g420` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرابحة'")) {
    echo "+ fin_ref_list.g420\n";
} else { echo "x fin_ref_list.g420: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g421'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g421 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g421` VARCHAR(190) NULL DEFAULT NULL COMMENT 'CONFIRMED_DOCUMENT'")) {
    echo "+ fin_ref_list.g421\n";
} else { echo "x fin_ref_list.g421: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g422'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g422 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g422` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Open'")) {
    echo "+ fin_ref_list.g422\n";
} else { echo "x fin_ref_list.g422: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g423'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g423 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g423` VARCHAR(190) NULL DEFAULT NULL COMMENT 'High'")) {
    echo "+ fin_ref_list.g423\n";
} else { echo "x fin_ref_list.g423: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g424'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g424 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g424` VARCHAR(190) NULL DEFAULT NULL COMMENT 'SUPPLIER'")) {
    echo "+ fin_ref_list.g424\n";
} else { echo "x fin_ref_list.g424: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g425'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g425 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g425` VARCHAR(190) NULL DEFAULT NULL COMMENT 'على الشركة'")) {
    echo "+ fin_ref_list.g425\n";
} else { echo "x fin_ref_list.g425: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g426'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g426 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g426` VARCHAR(190) NULL DEFAULT NULL COMMENT 'بنك'")) {
    echo "+ fin_ref_list.g426\n";
} else { echo "x fin_ref_list.g426: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g427'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g427 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g427` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نشط'")) {
    echo "+ fin_ref_list.g427\n";
} else { echo "x fin_ref_list.g427: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g428'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g428 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g428` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مكتمل'")) {
    echo "+ fin_ref_list.g428\n";
} else { echo "x fin_ref_list.g428: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g429'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g429 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g429` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مطروحة'")) {
    echo "+ fin_ref_list.g429\n";
} else { echo "x fin_ref_list.g429: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g430'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g430 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g430` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مستلم'")) {
    echo "+ fin_ref_list.g430\n";
} else { echo "x fin_ref_list.g430: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g431'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g431 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g431` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معتمد'")) {
    echo "+ fin_ref_list.g431\n";
} else { echo "x fin_ref_list.g431: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g432'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g432 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g432` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نافذ'")) {
    echo "+ fin_ref_list.g432\n";
} else { echo "x fin_ref_list.g432: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g433'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g433 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g433` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مستحق تاريخيا'")) {
    echo "+ fin_ref_list.g433\n";
} else { echo "x fin_ref_list.g433: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g434'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g434 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g434` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Direct'")) {
    echo "+ fin_ref_list.g434\n";
} else { echo "x fin_ref_list.g434: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g435'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g435 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g435` VARCHAR(190) NULL DEFAULT NULL COMMENT 'إعادة جدولة'")) {
    echo "+ fin_ref_list.g435\n";
} else { echo "x fin_ref_list.g435: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g436'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g436 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g436` VARCHAR(190) NULL DEFAULT NULL COMMENT 'على الممول'")) {
    echo "+ fin_ref_list.g436\n";
} else { echo "x fin_ref_list.g436: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g437'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g437 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g437` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مقفلة مغطاة'")) {
    echo "+ fin_ref_list.g437\n";
} else { echo "x fin_ref_list.g437: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g438'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g438 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g438` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مؤهلة للإقفال'")) {
    echo "+ fin_ref_list.g438\n";
} else { echo "x fin_ref_list.g438: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g439'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g439 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g439` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الشيت'")) {
    echo "+ fin_ref_list.g439\n";
} else { echo "x fin_ref_list.g439: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g440'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g440 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g440` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الحقل'")) {
    echo "+ fin_ref_list.g440\n";
} else { echo "x fin_ref_list.g440: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g441'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g441 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g441` VARCHAR(190) NULL DEFAULT NULL COMMENT 'النوع'")) {
    echo "+ fin_ref_list.g441\n";
} else { echo "x fin_ref_list.g441: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g442'");
if ($q && $q->num_rows) { echo "= fin_ref_list.g442 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `fin_ref_list` ADD COLUMN `g442` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تصنيف الفراغ (ما يعنيه الفراغ)'")) {
    echo "+ fin_ref_list.g442\n";
} else { echo "x fin_ref_list.g442: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
