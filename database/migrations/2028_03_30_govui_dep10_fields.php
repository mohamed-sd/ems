<?php
/**
 * 2028_03_30_govui_dep10_fields.php — DEP-10 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-10
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

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_ticket_contextual_open` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البلاغ\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الشاشة المصدر\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مسار الشاشة\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع السجل المفتوح\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة المالكة للشاشة\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المبلغ\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'صفته وقت الإبلاغ\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت الإبلاغ\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فئة المشكلة\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'طبيعتها\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأولوية المقترحة\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وصف موجز\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرفق اختياري\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تصنيف آلي مقترح\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة المستقبلة\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر البلاغ\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البلاغ\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_22717b03_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-04 - الإبلاغ السياقي من داخل الشاشة\'';
if ($conn->query($sql)) { echo '+ جدول tkt_ticket_contextual_open
'; }
else { echo 'x tkt_ticket_contextual_open: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_ticket_form` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البلاغ\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت التسجيل\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قناة التسجيل\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Reporter_ID\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Reporter_Name\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Reporter_Department\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Reporter_Entity\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Reporter_Contact\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Subject_Type\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Subject_ID\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Subject_Name\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Subject_Owning_Department\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفئة\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الطبيعة\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأولوية\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى السرية\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وصف البلاغ\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرفقات\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Ticket_Owner\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Assigned_Department\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Resolution_Owner\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مهلة المعالجة\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البلاغ\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_f0b9380d_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-06 - تسجيل البلاغ\'';
if ($conn->query($sql)) { echo '+ جدول tkt_ticket_form
'; }
else { echo 'x tkt_ticket_form: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_escalation` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g65` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف التصعيد\',`g66` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البلاغ\',`g67` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المهلة الأصلية\',`g68` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التجاوز\',`g69` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستوى\',`g70` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المخطر\',`g71` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت التصعيد\',`g72` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاستجابة\',`g73` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت الاستجابة\',`g74` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة التصعيد\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_4598d5bf_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-11 - تصعيد البلاغ\'';
if ($conn->query($sql)) { echo '+ جدول tkt_escalation
'; }
else { echo 'x tkt_escalation: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_tickets_list` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g75` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g76` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نطاق العرض\',`g77` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البلاغ\',`g78` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التسجيل\',`g79` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفئة\',`g80` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأولوية\',`g81` VARCHAR(190) NULL DEFAULT NULL COMMENT \'محل البلاغ\',`g82` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المكلف\',`g83` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكيان المنشأ في إدارتنا\',`g84` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مهلة SLA\',`g85` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتبقي/التأخير\',`g86` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى التصعيد\',`g87` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ينتظر تحققا؟\',`g88` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البلاغ\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_4504509a_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-02 - صندوق بلاغات الإدارة\'';
if ($conn->query($sql)) { echo '+ جدول tkt_tickets_list
'; }
else { echo 'x tkt_tickets_list: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_resolution_actions` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g89` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الإجراء\',`g90` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البلاغ\',`g91` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تسلسل الإجراء\',`g92` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المكلف\',`g93` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإجراء المتخذ\',`g94` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الإجراء في شاشة الإدارة\',`g95` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نتيجة الإجراء\',`g96` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سبب التعليق\',`g97` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مدة التعليق\',`g98` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت الإجراء\',`g99` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g100` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g101` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g102` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_0c3d6047_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-09 - إجراءات معالجة البلاغ\'';
if ($conn->query($sql)) { echo '+ جدول tkt_resolution_actions
'; }
else { echo 'x tkt_resolution_actions: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_ticket_sla_config` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g103` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g104` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع البلاغ\',`g105` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأولوية\',`g106` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة المسؤولة\',`g107` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Response SLA\',`g108` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Resolution SLA\',`g109` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سلم التصعيد\',`g110` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سريان المصفوفة\',`g111` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة السطر\',`g112` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g113` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g114` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g115` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_ba1d5019_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-03 - مصفوفة مهل المعالجة للبلاغات\'';
if ($conn->query($sql)) { echo '+ جدول tkt_ticket_sla_config
'; }
else { echo 'x tkt_ticket_sla_config: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_routing` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g128` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف التوجيه\',`g129` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البلاغ\',`g130` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تسلسل التوجيه\',`g131` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع التوجيه\',`g132` VARCHAR(190) NULL DEFAULT NULL COMMENT \'من إدارة\',`g133` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إلى إدارة\',`g134` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التصنيف قبل\',`g135` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التصنيف بعد\',`g136` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سبب التصحيح\',`g137` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أثر المهلة\',`g138` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت التوجيه\',`g139` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g140` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g141` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g142` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_57145091_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-07 - تاريخ توجيه البلاغ\'';
if ($conn->query($sql)) { echo '+ جدول tkt_routing
'; }
else { echo 'x tkt_routing: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_communications` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g143` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف التواصل\',`g144` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البلاغ\',`g145` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاتجاه\',`g146` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الطرف\',`g147` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القناة\',`g148` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملخص التواصل\',`g149` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ضمن مستوى السرية\',`g150` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت التواصل\',`g151` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g152` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g153` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g154` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_d20a9335_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-10 - مراسلات البلاغ\'';
if ($conn->query($sql)) { echo '+ جدول tkt_communications
'; }
else { echo 'x tkt_communications: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_dashboard_kpi` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g155` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المؤشر\',`g156` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المؤشر\',`g157` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g158` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة\',`g159` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g160` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تحديث\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_fbcf747e_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-01 - لا سطر مسجل بعد في لوحة مركز البلاغات\'';
if ($conn->query($sql)) { echo '+ جدول tkt_dashboard_kpi
'; }
else { echo 'x tkt_dashboard_kpi: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_subject_types` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g161` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود النوع\',`g162` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم النوع\',`g163` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السجل المرجعي\',`g164` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مفتاح الربط\',`g165` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة المالكة\',`g166` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أمثلة\',`g167` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة النوع\',`g168` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g169` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g170` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g171` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_718e7822_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-05 - لا سطر مسجل بعد في أنواع محل البلاغ المعتمدة\'';
if ($conn->query($sql)) { echo '+ جدول tkt_subject_types
'; }
else { echo 'x tkt_subject_types: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tkt_assignment` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g172` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الإسناد\',`g173` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البلاغ\',`g174` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تسلسل الإسناد\',`g175` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المكلف\',`g176` VARCHAR(190) NULL DEFAULT NULL COMMENT \'صفة المكلف\',`g177` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت الإسناد\',`g178` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت الاستلام\',`g179` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سبب التغيير\',`g180` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الإسناد\',`g181` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g182` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g183` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g184` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_844f20b2_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TKT-08 - تاريخ إسناد البلاغ\'';
if ($conn->query($sql)) { echo '+ جدول tkt_assignment
'; }
else { echo 'x tkt_assignment: ' . $conn->error . chr(10); }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g49'");
if ($q && $q->num_rows) { echo "= tkt_verification.g49 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g49` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معرف الإغلاق'")) {
    echo "+ tkt_verification.g49\n";
} else { echo "x tkt_verification.g49: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g50'");
if ($q && $q->num_rows) { echo "= tkt_verification.g50 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g50` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم البلاغ'")) {
    echo "+ tkt_verification.g50\n";
} else { echo "x tkt_verification.g50: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g51'");
if ($q && $q->num_rows) { echo "= tkt_verification.g51 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g51` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملخص المعالجة'")) {
    echo "+ tkt_verification.g51\n";
} else { echo "x tkt_verification.g51: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g52'");
if ($q && $q->num_rows) { echo "= tkt_verification.g52 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g52` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Resolved في'")) {
    echo "+ tkt_verification.g52\n";
} else { echo "x tkt_verification.g52: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g53'");
if ($q && $q->num_rows) { echo "= tkt_verification.g53 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g53` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع التحقق'")) {
    echo "+ tkt_verification.g53\n";
} else { echo "x tkt_verification.g53: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g54'");
if ($q && $q->num_rows) { echo "= tkt_verification.g54 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g54` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تأكيد المبلغ'")) {
    echo "+ tkt_verification.g54\n";
} else { echo "x tkt_verification.g54: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g55'");
if ($q && $q->num_rows) { echo "= tkt_verification.g55 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g55` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تقييم الرضا'")) {
    echo "+ tkt_verification.g55\n";
} else { echo "x tkt_verification.g55: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g56'");
if ($q && $q->num_rows) { echo "= tkt_verification.g56 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g56` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع الإغلاق'")) {
    echo "+ tkt_verification.g56\n";
} else { echo "x tkt_verification.g56: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g57'");
if ($q && $q->num_rows) { echo "= tkt_verification.g57 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g57` VARCHAR(190) NULL DEFAULT NULL COMMENT 'البلاغ الأصل عند التكرار'")) {
    echo "+ tkt_verification.g57\n";
} else { echo "x tkt_verification.g57: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g58'");
if ($q && $q->num_rows) { echo "= tkt_verification.g58 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g58` VARCHAR(190) NULL DEFAULT NULL COMMENT 'سبب الإلغاء'")) {
    echo "+ tkt_verification.g58\n";
} else { echo "x tkt_verification.g58: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g59'");
if ($q && $q->num_rows) { echo "= tkt_verification.g59 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g59` VARCHAR(190) NULL DEFAULT NULL COMMENT 'وقت الإغلاق'")) {
    echo "+ tkt_verification.g59\n";
} else { echo "x tkt_verification.g59: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g60'");
if ($q && $q->num_rows) { echo "= tkt_verification.g60 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g60` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة الإغلاق'")) {
    echo "+ tkt_verification.g60\n";
} else { echo "x tkt_verification.g60: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g61'");
if ($q && $q->num_rows) { echo "= tkt_verification.g61 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g61` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المنشئ'")) {
    echo "+ tkt_verification.g61\n";
} else { echo "x tkt_verification.g61: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g62'");
if ($q && $q->num_rows) { echo "= tkt_verification.g62 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g62` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الإنشاء'")) {
    echo "+ tkt_verification.g62\n";
} else { echo "x tkt_verification.g62: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g63'");
if ($q && $q->num_rows) { echo "= tkt_verification.g63 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g63` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ tkt_verification.g63\n";
} else { echo "x tkt_verification.g63: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_verification` LIKE 'g64'");
if ($q && $q->num_rows) { echo "= tkt_verification.g64 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_verification` ADD COLUMN `g64` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع المصدر'")) {
    echo "+ tkt_verification.g64\n";
} else { echo "x tkt_verification.g64: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g116'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g116 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g116` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معرف الإعادة'")) {
    echo "+ tkt_reopen.g116\n";
} else { echo "x tkt_reopen.g116: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g117'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g117 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g117` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم البلاغ'")) {
    echo "+ tkt_reopen.g117\n";
} else { echo "x tkt_reopen.g117: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g118'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g118 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g118` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تسلسل الإعادة'")) {
    echo "+ tkt_reopen.g118\n";
} else { echo "x tkt_reopen.g118: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g119'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g119 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g119` VARCHAR(190) NULL DEFAULT NULL COMMENT 'طالب الإعادة'")) {
    echo "+ tkt_reopen.g119\n";
} else { echo "x tkt_reopen.g119: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g120'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g120 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g120` VARCHAR(190) NULL DEFAULT NULL COMMENT 'سبب الإعادة'")) {
    echo "+ tkt_reopen.g120\n";
} else { echo "x tkt_reopen.g120: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g121'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g121 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g121` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع الإغلاق السابق'")) {
    echo "+ tkt_reopen.g121\n";
} else { echo "x tkt_reopen.g121: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g122'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g122 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g122` VARCHAR(190) NULL DEFAULT NULL COMMENT 'وقت الإعادة'")) {
    echo "+ tkt_reopen.g122\n";
} else { echo "x tkt_reopen.g122: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g123'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g123 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g123` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المسار بعد الإعادة'")) {
    echo "+ tkt_reopen.g123\n";
} else { echo "x tkt_reopen.g123: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g124'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g124 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g124` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المنشئ'")) {
    echo "+ tkt_reopen.g124\n";
} else { echo "x tkt_reopen.g124: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g125'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g125 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g125` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الإنشاء'")) {
    echo "+ tkt_reopen.g125\n";
} else { echo "x tkt_reopen.g125: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g126'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g126 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g126` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ tkt_reopen.g126\n";
} else { echo "x tkt_reopen.g126: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tkt_reopen` LIKE 'g127'");
if ($q && $q->num_rows) { echo "= tkt_reopen.g127 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tkt_reopen` ADD COLUMN `g127` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع المصدر'")) {
    echo "+ tkt_reopen.g127\n";
} else { echo "x tkt_reopen.g127: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
