<?php
/**
 * 2028_04_02_govui_dep14_fields.php — DEP-14 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-14
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

$sql = 'CREATE TABLE IF NOT EXISTS `mnt_breakdown_intake` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الاستقبال\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البلاغ\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ البلاغ\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المبلغ\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود المعدة\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وصف العطل\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عقدة الشجرة المبدئية\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'درجة الخطورة\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعدة متوقفة؟\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أثر الإيقاف\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قرار الاستقبال\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم طلب الفحص المتفرع\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الاستقبال\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'شدة العطل الفني\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مدة التوقف\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأثر التشغيلي\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قابلية المنع\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التكرار\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أداء الاستجابة\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سبب التأخير\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سلسلة المسؤولية\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت توقف المعدة\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت إبلاغ المشغل\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت استلام الصيانة\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت بدء التشخيص\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت انتهاء التشخيص\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت طلب القطعة\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت توفر القطعة\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت وصولها للموقع\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت حضور الفني\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت بدء الإصلاح\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت انتهاء الإصلاح\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت الاختبار\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت التصديق\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت عودة المعدة للخدمة\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إجمالي التوقف\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'زمن الإصلاح الفعلي\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_f684fa24_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MNT-04 - البلاغ الفني واستقبال العطل\'';
if ($conn->query($sql)) { echo '+ جدول mnt_breakdown_intake
'; }
else { echo 'x mnt_breakdown_intake: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `mnt_workshop` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود القدرة\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النوع\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاسم\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التخصصات\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى الفني\',`g49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الشهادات وصلاحيتها\',`g50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الطاقة اليومية (ساعات/أوامر)\',`g51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'متاح الآن؟\',`g52` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التبعية\',`g53` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع العقد عند الخارجي\',`g54` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة القدرة\',`g55` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g56` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g57` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g58` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_66f8f27b_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MNT-03 - الورش والفنيون\'';
if ($conn->query($sql)) { echo '+ جدول mnt_workshop
'; }
else { echo 'x mnt_workshop: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `mnt_work_orders` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g59` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف البند\',`g60` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر\',`g61` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تسلسل البند\',`g62` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع البند\',`g63` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الصنف\',`g64` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوصف\',`g65` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكمية المطلوبة\',`g66` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكمية المصروفة\',`g67` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم سند الصرف\',`g68` VARCHAR(190) NULL DEFAULT NULL COMMENT \'جهة الخدمة الخارجية\',`g69` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التكلفة\',`g70` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ضمان مورد؟\',`g71` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البند\',`g72` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g73` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g74` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g75` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_9ee20d1f_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MNT-10 - أمر العمل\'';
if ($conn->query($sql)) { echo '+ جدول mnt_work_orders
'; }
else { echo 'x mnt_work_orders: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `mnt_preventive_plans` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g76` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g77` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود المعدة\',`g78` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع المعدة\',`g79` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر الفاصل\',`g80` VARCHAR(190) NULL DEFAULT NULL COMMENT \'دورة الوقائية\',`g81` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فاصل الأصل المخصص\',`g82` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ساعات الدورة\',`g83` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قراءة آخر وقائية\',`g84` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قراءة العداد الحالية\',`g85` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتبقي للاستحقاق\',`g86` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاستحقاق المتوقع\',`g87` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بنود الدورة القياسية\',`g88` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الاستحقاق\',`g89` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر المتولد\',`g90` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g91` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g92` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g93` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_d5747984_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MNT-13 - الخطة الوقائية بالساعات\'';
if ($conn->query($sql)) { echo '+ جدول mnt_preventive_plans
'; }
else { echo 'x mnt_preventive_plans: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `mnt_dashboard_kpi` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g94` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المؤشر\',`g95` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المؤشر KPI Catalog\',`g96` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g97` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة\',`g98` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحد المقبول\',`g99` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g100` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تحديث\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_6bc65acd_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MNT-01 - لا سطر مسجل بعد في لوحة الصيانة والجاهزية\'';
if ($conn->query($sql)) { echo '+ جدول mnt_dashboard_kpi
'; }
else { echo 'x mnt_dashboard_kpi: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `mnt_repeat_repairs` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g101` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الواقعة\',`g102` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود المعدة\',`g103` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر الأصلي\',`g104` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عقدة الشجرة\',`g105` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التكرار\',`g106` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المدة منذ الشهادة\',`g107` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ضمن صلاحية الشهادة؟\',`g108` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تحليل السبب الجذري RCA\',`g109` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر الجديد\',`g110` VARCHAR(190) NULL DEFAULT NULL COMMENT \'محفز RCA\',`g111` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القرار\',`g112` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الواقعة\',`g113` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g114` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g115` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g116` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_44184061_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MNT-16 - سجل إعادة الإصلاح\'';
if ($conn->query($sql)) { echo '+ جدول mnt_repeat_repairs
'; }
else { echo 'x mnt_repeat_repairs: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `mnt_kpis` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g129` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g130` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفترة\',`g131` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النطاق\',`g132` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعدة/النوع\',`g133` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الأعطال\',`g134` VARCHAR(190) NULL DEFAULT NULL COMMENT \'متوسط الزمن بين الأعطال\',`g135` VARCHAR(190) NULL DEFAULT NULL COMMENT \'متوسط زمن الإصلاح\',`g136` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نسبة الجاهزية\',`g137` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أوامر الوقائية المنفذة\',`g138` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الالتزام بالوقائية\',`g139` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تكلفة الصيانة للساعة\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_0e0723ff_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MNT-17 - مؤشرات الصيانة الدورية\'';
if ($conn->query($sql)) { echo '+ جدول mnt_kpis
'; }
else { echo 'x mnt_kpis: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `mnt_part_requests` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g140` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الطلب\',`g141` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر\',`g142` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الطلب\',`g143` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المخزن\',`g144` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البنود المطلوبة\',`g145` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأولوية\',`g146` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستلم العهدة\',`g147` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم سند الصرف\',`g148` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مطابقة الاستلام\',`g149` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الطلب\',`g150` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g151` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g152` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g153` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_71fdca74_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MNT-11 - لا سطر مسجل بعد في طلب صرف القطع لأمر العمل\'';
if ($conn->query($sql)) { echo '+ جدول mnt_part_requests
'; }
else { echo 'x mnt_part_requests: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `mnt_external_repairs` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g154` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g155` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر\',`g156` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النوع\',`g157` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الجهة الخارجية/المورد\',`g158` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع العقد/الضمان\',`g159` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نطاق العمل\',`g160` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التكلفة المقدرة\',`g161` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التكلفة الفعلية\',`g162` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نتيجة المطالبة\',`g163` VARCHAR(190) NULL DEFAULT NULL COMMENT \'محضر الاستلام\',`g164` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة السطر\',`g165` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g166` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g167` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g168` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_abcddada_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'MNT-12 - الإصلاح الخارجي ومطالبات الضمان\'';
if ($conn->query($sql)) { echo '+ جدول mnt_external_repairs
'; }
else { echo 'x mnt_external_repairs: ' . $conn->error . chr(10); }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g117'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g117 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g117` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معرف السطر'")) {
    echo "+ mnt_daily_care.g117\n";
} else { echo "x mnt_daily_care.g117: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g118'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g118 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g118` VARCHAR(190) NULL DEFAULT NULL COMMENT 'التاريخ'")) {
    echo "+ mnt_daily_care.g118\n";
} else { echo "x mnt_daily_care.g118: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g119'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g119 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g119` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود المعدة'")) {
    echo "+ mnt_daily_care.g119\n";
} else { echo "x mnt_daily_care.g119: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g120'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g120 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g120` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المهمة'")) {
    echo "+ mnt_daily_care.g120\n";
} else { echo "x mnt_daily_care.g120: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g121'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g121 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g121` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المنفذ'")) {
    echo "+ mnt_daily_care.g121\n";
} else { echo "x mnt_daily_care.g121: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g122'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g122 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g122` VARCHAR(190) NULL DEFAULT NULL COMMENT 'النتيجة'")) {
    echo "+ mnt_daily_care.g122\n";
} else { echo "x mnt_daily_care.g122: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g123'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g123 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g123` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظة غير طبيعية'")) {
    echo "+ mnt_daily_care.g123\n";
} else { echo "x mnt_daily_care.g123: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g124'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g124 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g124` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة السطر'")) {
    echo "+ mnt_daily_care.g124\n";
} else { echo "x mnt_daily_care.g124: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g125'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g125 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g125` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المنشئ'")) {
    echo "+ mnt_daily_care.g125\n";
} else { echo "x mnt_daily_care.g125: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g126'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g126 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g126` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الإنشاء'")) {
    echo "+ mnt_daily_care.g126\n";
} else { echo "x mnt_daily_care.g126: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g127'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g127 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g127` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ mnt_daily_care.g127\n";
} else { echo "x mnt_daily_care.g127: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `mnt_daily_care` LIKE 'g128'");
if ($q && $q->num_rows) { echo "= mnt_daily_care.g128 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `mnt_daily_care` ADD COLUMN `g128` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع المصدر'")) {
    echo "+ mnt_daily_care.g128\n";
} else { echo "x mnt_daily_care.g128: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
