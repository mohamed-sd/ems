<?php
/**
 * 2028_04_13_govui_dep_01_fields.php — DEP-01 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-01
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

$sql = 'CREATE TABLE IF NOT EXISTS `sal_contracts` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود العقد\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العميل\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم العميل (بحث)\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المشروع\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نموذج العمل\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العقد بالمنظومة\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تسلسل الشركة\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'توقيع الوثيقة\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البداية التعاقدية\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النهاية التعاقدية\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البداية التنفيذية\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النهاية التنفيذية\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة العقد\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد دورات الالتزام (التجديدات)\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدات المتعاقدة الحالية\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السعة الشهرية الحالية\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة خط الأساس\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحجية/مصدر التوثيق\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس التسعير (كما ورد)\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر الوحدة (كما ورد)\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الضريبة\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الدفع والفوترة\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوديعة/الدفعة المقدمة\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوقود\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السكن والإعاشة\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الصيانة وقطع الغيار\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التأمين\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المشغلون\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النقل والتعبئة\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحد الأدنى للساعات\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ضمان التشغيل\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'جدول عمل الموقع\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'خصم ساعات المخالفة\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التوقف غير المدفوع\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإنهاء\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التجديد\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القانون الحاكم\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المسؤول التجاري\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الخدمة المقدمة\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس التعاقد (الوحدة)\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مكان إبرام العقد (مؤكد)\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المكان المرجح تاريخيا\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس المكان المرجح\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى حجية المكان المرجح\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة كما وردت بالمصدر\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس تعديل الحالة\',`g49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نموذج التسعير\',`g50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وحدة الفوترة\',`g51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحد الأدنى (كمية)\',`g52` VARCHAR(190) NULL DEFAULT NULL COMMENT \'دورية الحد الأدنى\',`g53` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكمية المضمونة\',`g54` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عتبة الفوترة\',`g55` VARCHAR(190) NULL DEFAULT NULL COMMENT \'متحمل العجز\',`g56` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قاعدة العجز\',`g57` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بنية السعر\',`g58` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد النسخ/المكونات السعرية\',`g59` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع تسعيري\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_85d2853e_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SAL-11 - سجل عقود المشاريع\'';
if ($conn->query($sql)) { echo '+ جدول sal_contracts
'; }
else { echo 'x sal_contracts: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sal_client_need_rfq` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g60` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الطلب\',`g61` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العميل\',`g62` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم العميل (بحث)\',`g63` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المشروع\',`g64` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الفرصة\',`g65` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الطلب\',`g66` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نطاق الطلب\',`g67` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الخدمة المطلوبة\',`g68` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نموذج العمل المطلوب\',`g69` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة\',`g70` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكمية/الحجم المطلوب\',`g71` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أنواع الآليات المطلوبة\',`g72` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الآليات\',`g73` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المدة (أشهر)\',`g74` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البداية المتوقعة\',`g75` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النهاية المتوقعة\',`g76` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس البداية المتوقعة\',`g77` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس النهاية المتوقعة\',`g78` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة بيانات التواريخ\',`g79` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتطلبات التجارية الأساسية\',`g80` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاستلام\',`g81` VARCHAR(190) NULL DEFAULT NULL COMMENT \'موعد الرد بالعرض\',`g82` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g83` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع العقد الناتج\',`g84` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`g85` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مفتاح دورة الالتزام المصدر\',`g86` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى الحجية\',`g87` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس القيمة الرجعية\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_4cb3e9c2_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SAL-06 - احتياج العميل وطلب العرض\'';
if ($conn->query($sql)) { echo '+ جدول sal_client_need_rfq
'; }
else { echo 'x sal_client_need_rfq: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sal_clients` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g88` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العميل\',`g89` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاسم القانوني\',`g90` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاسم المختصر\',`g91` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تصنيف العميل\',`g92` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس التصنيف\',`g93` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة العميل\',`g94` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القطاع\',`g95` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الدولة\',`g96` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المدينة/المنطقة\',`g97` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم التسجيل\',`g98` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الرقم الضريبي\',`g99` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مالك الحساب\',`g100` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر التعرف\',`g101` VARCHAR(190) NULL DEFAULT NULL COMMENT \'درجة الأولوية\',`g102` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التصنيف الائتماني\',`g103` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حد الائتمان ($)\',`g104` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حد الائتمان (ج.س)\',`g105` VARCHAR(190) NULL DEFAULT NULL COMMENT \'شروط الدفع الافتراضية\',`g106` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ أول تعامل\',`g107` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد العقود\',`g108` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العقود الجارية\',`g109` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر نشاط تنفيذي\',`g110` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نماذج التعامل\',`g111` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`g112` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أنواع الخدمات\',`g113` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملات المتعامل بها\',`g114` VARCHAR(190) NULL DEFAULT NULL COMMENT \'دورية الفوترة بالمصدر\',`g115` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد المشاريع\',`g116` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى حجية بيانات العميل\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_6d49bdff_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SAL-01 - سجل العملاء\'';
if ($conn->query($sql)) { echo '+ جدول sal_clients
'; }
else { echo 'x sal_clients: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sal_quotation_negotiation` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g141` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الواقعة\',`g142` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع السجل\',`g143` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العرض\',`g144` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع العقد\',`g145` VARCHAR(190) NULL DEFAULT NULL COMMENT \'دورة الالتزام الجديدة\',`g146` VARCHAR(190) NULL DEFAULT NULL COMMENT \'دورة الالتزام السابقة\',`g147` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نطاق المقارنة\',`g148` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التاريخ\',`g149` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع التغيير\',`g150` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قبل\',`g151` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بعد\',`g152` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأثر التجاري\',`g153` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوثيقة المرجعية\',`g154` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السبب/الدليل\',`g155` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الطرف الطالب\',`g156` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g157` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`g158` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مفتاح دورة الالتزام المصدر\',`g159` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى الحجية\',`g160` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس القيمة الرجعية\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_4c666965_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SAL-09 - التفاوض ومراجعات العرض\'';
if ($conn->query($sql)) { echo '+ جدول sal_quotation_negotiation
'; }
else { echo 'x sal_quotation_negotiation: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sal_claims` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g161` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المطالبة\',`g162` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مفتاح دورة الالتزام\',`g163` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود العقد\',`g164` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العميل\',`g165` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم العميل (بحث)\',`g166` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفترة من\',`g167` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إلى\',`g168` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكمية المنجزة المرجعية\',`g169` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة\',`g170` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاستحقاق المحسوب ($)\',`g171` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاستحقاق المحسوب (ج.س)\',`g172` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة المطالب بها ($)\',`g173` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المطالب بها (ج.س)\',`g174` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع القياس/المستخلص\',`g175` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة اعتماد العميل\',`g176` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التسليم للمالية\',`g177` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الفاتورة\',`g178` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة المتابعة\',`g179` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`g180` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المفوتر للعميل ($)\',`g181` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستحق غير مطالب به ($)\',`g182` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة التحصيل\',`g183` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس حالة التحصيل\',`g184` VARCHAR(190) NULL DEFAULT NULL COMMENT \'دليل القياس/التسوية بالمصدر\',`g185` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى الحجية\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_6bc1fc6f_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SAL-18 - المطالبات والتسليم للمالية\'';
if ($conn->query($sql)) { echo '+ جدول sal_claims
'; }
else { echo 'x sal_claims: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sal_quotations` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g186` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العرض الداخلي\',`g187` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الفرصة\',`g188` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الطلب\',`g189` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العميل\',`g190` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم العميل (بحث)\',`g191` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المشروع\',`g192` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العرض الرسمي\',`g193` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإصدار\',`g194` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإرسال\',`g195` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس التاريخ\',`g196` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النسخة\',`g197` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نموذج العمل\',`g198` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g199` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مدة السريان\',`g200` VARCHAR(190) NULL DEFAULT NULL COMMENT \'شروط الدفع/الفوترة\',`g201` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة العرض\',`g202` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رد العميل\',`g203` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة القرار\',`g204` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ القرار\',`g205` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة العرض ($)\',`g206` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة العرض (ج.س)\',`g207` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع العقد الناتج\',`g208` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`g209` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مفتاح دورة الالتزام المصدر\',`g210` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى الحجية\',`g211` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس القيمة الرجعية\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_7d8acf80_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SAL-07 - لا سطر مسجل بعد في سجل العروض\'';
if ($conn->query($sql)) { echo '+ جدول sal_quotations
'; }
else { echo 'x sal_quotations: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sal_projects` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g212` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المشروع\',`g213` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العميل\',`g214` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم العميل (بحث)\',`g215` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المشروع\',`g216` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوصف\',`g217` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع (نطاق تنفيذ)\',`g218` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القطاع\',`g219` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة المشروع\',`g220` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ البداية\',`g221` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المسؤول التجاري\',`g222` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة التقديرية ($)\',`g223` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة التقديرية (ج.س)\',`g224` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد العقود\',`g225` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`g226` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود المشروع لدى العميل\',`g227` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإقليم/الولاية\',`g228` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تسلسل مشروع العميل\',`g229` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الخدمة\',`g230` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نموذج العمل\',`g231` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس التسمية والحدود\',`g232` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قاعدة التجميع\',`g233` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى الحجية\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_d980aada_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SAL-03 - سجل المشاريع\'';
if ($conn->query($sql)) { echo '+ جدول sal_projects
'; }
else { echo 'x sal_projects: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sal_client_contacts` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g234` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم جهة الاتصال\',`g235` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العميل\',`g236` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم العميل (بحث)\',`g237` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاسم\',`g238` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المسمى الوظيفي\',`g239` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الهاتف\',`g240` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البريد\',`g241` VARCHAR(190) NULL DEFAULT NULL COMMENT \'دوره في القرار\',`g242` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g243` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`g244` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g245` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أثر البحث في المصادر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_a2c2be7e_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SAL-02 - لا سطر مسجل بعد في جهات اتصال العملاء\'';
if ($conn->query($sql)) { echo '+ جدول sal_client_contacts
'; }
else { echo 'x sal_client_contacts: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sal_commercial_board` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g246` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البيان\',`g247` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g248` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة/العملة\',`g249` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظة\',`g250` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر الرسم 1 نسبة التحقق التعاقدي حسب نموذج العمل\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_104a6d3f_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SAL-20 - لا سطر مسجل بعد في لوحة المبيعات\'';
if ($conn->query($sql)) { echo '+ جدول sal_commercial_board
'; }
else { echo 'x sal_commercial_board: ' . $conn->error . chr(10); }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g117'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g117 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g117` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم البند'")) {
    echo "+ sal_quotation_lines.g117\n";
} else { echo "x sal_quotation_lines.g117: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g118'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g118 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g118` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم العرض'")) {
    echo "+ sal_quotation_lines.g118\n";
} else { echo "x sal_quotation_lines.g118: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g119'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g119 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g119` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع العقد'")) {
    echo "+ sal_quotation_lines.g119\n";
} else { echo "x sal_quotation_lines.g119: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g120'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g120 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g120` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع البند'")) {
    echo "+ sal_quotation_lines.g120\n";
} else { echo "x sal_quotation_lines.g120: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g121'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g121 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g121` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع الخدمة'")) {
    echo "+ sal_quotation_lines.g121\n";
} else { echo "x sal_quotation_lines.g121: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g122'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g122 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g122` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع المعدة/البند'")) {
    echo "+ sal_quotation_lines.g122\n";
} else { echo "x sal_quotation_lines.g122: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g123'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g123 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g123` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نموذج العمل'")) {
    echo "+ sal_quotation_lines.g123\n";
} else { echo "x sal_quotation_lines.g123: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g124'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g124 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g124` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عدد المعدات'")) {
    echo "+ sal_quotation_lines.g124\n";
} else { echo "x sal_quotation_lines.g124: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g125'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g125 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g125` VARCHAR(190) NULL DEFAULT NULL COMMENT 'أساس الوحدة الشهري'")) {
    echo "+ sal_quotation_lines.g125\n";
} else { echo "x sal_quotation_lines.g125: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g126'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g126 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g126` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المدة (أشهر)'")) {
    echo "+ sal_quotation_lines.g126\n";
} else { echo "x sal_quotation_lines.g126: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g127'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g127 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g127` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الكمية/المستهدف'")) {
    echo "+ sal_quotation_lines.g127\n";
} else { echo "x sal_quotation_lines.g127: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g128'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g128 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g128` VARCHAR(190) NULL DEFAULT NULL COMMENT 'وحدة القياس'")) {
    echo "+ sal_quotation_lines.g128\n";
} else { echo "x sal_quotation_lines.g128: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g129'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g129 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g129` VARCHAR(190) NULL DEFAULT NULL COMMENT 'سعر الوحدة'")) {
    echo "+ sal_quotation_lines.g129\n";
} else { echo "x sal_quotation_lines.g129: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g130'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g130 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g130` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ sal_quotation_lines.g130\n";
} else { echo "x sal_quotation_lines.g130: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g131'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g131 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g131` VARCHAR(190) NULL DEFAULT NULL COMMENT 'القيمة'")) {
    echo "+ sal_quotation_lines.g131\n";
} else { echo "x sal_quotation_lines.g131: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g132'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g132 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g132` VARCHAR(190) NULL DEFAULT NULL COMMENT 'سريان النسخة السعرية'")) {
    echo "+ sal_quotation_lines.g132\n";
} else { echo "x sal_quotation_lines.g132: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g133'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g133 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g133` VARCHAR(190) NULL DEFAULT NULL COMMENT 'أساس السعر'")) {
    echo "+ sal_quotation_lines.g133\n";
} else { echo "x sal_quotation_lines.g133: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g134'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g134 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g134` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الضريبة كما وردت'")) {
    echo "+ sal_quotation_lines.g134\n";
} else { echo "x sal_quotation_lines.g134: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g135'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g135 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g135` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نص السعر كما ورد بالمصدر'")) {
    echo "+ sal_quotation_lines.g135\n";
} else { echo "x sal_quotation_lines.g135: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g136'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g136 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g136` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ sal_quotation_lines.g136\n";
} else { echo "x sal_quotation_lines.g136: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g137'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g137 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g137` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات تجارية'")) {
    echo "+ sal_quotation_lines.g137\n";
} else { echo "x sal_quotation_lines.g137: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g138'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g138 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g138` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مفتاح دورة الالتزام المصدر'")) {
    echo "+ sal_quotation_lines.g138\n";
} else { echo "x sal_quotation_lines.g138: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g139'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g139 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g139` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مستوى الحجية'")) {
    echo "+ sal_quotation_lines.g139\n";
} else { echo "x sal_quotation_lines.g139: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sal_quotation_lines` LIKE 'g140'");
if ($q && $q->num_rows) { echo "= sal_quotation_lines.g140 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sal_quotation_lines` ADD COLUMN `g140` VARCHAR(190) NULL DEFAULT NULL COMMENT 'أساس القيمة الرجعية'")) {
    echo "+ sal_quotation_lines.g140\n";
} else { echo "x sal_quotation_lines.g140: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
