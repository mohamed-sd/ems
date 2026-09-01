<?php
/**
 * 2028_03_26_govui_exceo_fields.php — EX-CEO · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for EX-CEO
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

$sql = 'CREATE TABLE IF NOT EXISTS `exec_board_kpi` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المؤشر\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكيان\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المحور\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود المؤشر KPI Catalog\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المؤشر\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النطاق\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع النطاق\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة/العملة\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستهدف\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الانحراف\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاتجاه\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة المالكة\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رابط النزول للمصدر\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تحديث\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_f6c19be1_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-01 - مؤشر واحد للوحة القيادة\'';
if ($conn->query($sql)) { echo '+ جدول exec_board_kpi
'; }
else { echo 'x exec_board_kpi: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_org_project` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف البطاقة\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع البطاقة\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Department_ID/Project_ID\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاسم\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التبعية التنظيمية\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Head المسؤول\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Overall_Status\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'KPI_Status\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Open_Requests\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Pending_Approvals\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Critical_Issues\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Overdue_Actions\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Budget_Status\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Compliance_Status\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Last_Daily_Report\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Last_Weekly_Report\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Last_Monthly_Close\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رابط النزول\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_52bd7719_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-02 - ادارة او مشروع واحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_org_project
'; }
else { echo 'x exec_org_project: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_daily_report` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التاريخ\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المشروع\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الخطة اليومية\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنفذ\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نسبة الإنجاز\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ساعات تشغيل المعدات\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إجمالي ساعات التوقف\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد أنواع التوقف تفصيلها ر03-2\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعدات العاملة\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعدات المتوقفة\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأعطال الحرجة\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القوى الموجودة\',`g49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النقص\',`g50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إصابات مضيعة للوقت LTI\',`g51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حوادث عالية الجهد HiPo\',`g52` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أحداث إيقاف العمل Stop-Work\',`g53` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحوادث البيئية\',`g54` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البلاغات الحرجة\',`g55` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الترحيلات المهمة\',`g56` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المواد الحرجة\',`g57` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد القرارات المطلوبة تفصيلها ر03-3\',`g58` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الانحرافات الحرجة تفصيلها ر03-3\',`g59` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رابط النزول للسجل الأصلي\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_d78d9fe3_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-03 - تقرير يوم واحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_daily_report
'; }
else { echo 'x exec_daily_report: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_daily_stop` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g60` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g61` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف سطر اليوم\',`g62` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المشروع\',`g63` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع\',`g64` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع التوقف\',`g65` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ساعات النوع\',`g66` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مسؤول التوقف\',`g67` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أثر الفوترة\',`g68` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع السجل الأصلي\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_7030b0b1_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-04 - توقف واحد في يوم\'';
if ($conn->query($sql)) { echo '+ جدول exec_daily_stop
'; }
else { echo 'x exec_daily_stop: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_daily_deviation` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g69` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف البند\',`g70` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف سطر اليوم\',`g71` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع البند\',`g72` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المشروع\',`g73` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع\',`g74` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوصف\',`g75` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المسؤول\',`g76` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرجع الأصلي\',`g77` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإجراء المقرر Decision Event\',`g78` VARCHAR(190) NULL DEFAULT NULL COMMENT \'موعد الحل Decision Event\',`g79` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_029511dd_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-05 - انحراف واحد في يوم\'';
if ($conn->query($sql)) { echo '+ جدول exec_daily_deviation
'; }
else { echo 'x exec_daily_deviation: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_weekly_report` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g80` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g81` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأسبوع\',`g82` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المحور\',`g83` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المؤشر\',`g84` VARCHAR(190) NULL DEFAULT NULL COMMENT \'هذا الأسبوع\',`g85` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأسبوع السابق\',`g86` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستهدف\',`g87` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الانحراف\',`g88` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاتجاه Trend\',`g89` VARCHAR(190) NULL DEFAULT NULL COMMENT \'توقع الأسبوع القادم Forecast\',`g90` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التوقع مقابل الميزانية\',`g91` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد القرارات غير المنفذة\',`g92` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قرار الاجتماع المتفرع\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_e3d0079e_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-06 - تقرير اسبوع واحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_weekly_report
'; }
else { echo 'x exec_weekly_report: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_monthly_pack` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g93` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g94` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الشهر\',`g95` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المحور\',`g96` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البند\',`g97` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفعلي\',`g98` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستهدف\',`g99` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الانحراف\',`g100` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاتجاه\',`g101` VARCHAR(190) NULL DEFAULT NULL COMMENT \'توقع الشهر الكامل\',`g102` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Outlook 60/90 يوما\',`g103` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Forecast vs Budget\',`g104` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بند النظرة الأمامية\',`g105` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مراجعة النائب المختص\',`g106` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظة تنفيذية Decision Event\',`g107` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القرار/الإجراء المتفرع\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_3d26094a_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-07 - حزمة شهر واحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_monthly_pack
'; }
else { echo 'x exec_monthly_pack: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_request_queue` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g108` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Request_ID\',`g109` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Source_Department\',`g110` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Source_Screen\',`g111` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Request_Type\',`g112` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Request_Title\',`g113` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Requested_By\',`g114` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Requested_Date\',`g115` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Amount\',`g116` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Currency\',`g117` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Project\',`g118` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Priority\',`g119` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_Level\',`g120` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Previous_Approvals\',`g121` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سقف الإدارة المصدر\',`g122` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مقدار التجاوز عن السقف\',`g123` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سبب الرفع للأعلى\',`g124` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Current_Approval_Level\',`g125` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Required_By\',`g126` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Supporting_Documents\',`g127` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Recommendation\',`g128` VARCHAR(190) NULL DEFAULT NULL COMMENT \'CEO_Decision\',`g129` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Decision_Conditions\',`g130` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Decision_Date\',`g131` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g132` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g133` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g134` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_f069163a_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-08 - طلب واحد مرفوع\'';
if ($conn->query($sql)) { echo '+ جدول exec_request_queue
'; }
else { echo 'x exec_request_queue: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_contract_registry` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g135` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف السطر\',`g136` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع العقد\',`g137` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة المالكة\',`g138` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع العقد في مصدره\',`g139` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكيان\',`g140` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الطرف الآخر\',`g141` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g142` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g143` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نموذج العمل\',`g144` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سريان من\',`g145` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إلى\',`g146` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أيام حتى الانتهاء\',`g147` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الالتزامات الحرجة القائمة\',`g148` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الكفالات والضمانات المرتبطة\',`g149` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة العقد\',`g150` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع التوقيع\',`g151` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رابط النزول للسجل الأصلي\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_f1ddcff6_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-12 - عقد واحد في السجل الموحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_contract_registry
'; }
else { echo 'x exec_contract_registry: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_redline_breach` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g152` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Exception_ID\',`g153` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Source_Department\',`g154` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Source_Record\',`g155` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Rule_Breached\',`g156` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Red_Line_Type\',`g157` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Severity\',`g158` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Financial_Exposure\',`g159` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Operational_Exposure\',`g160` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Legal_Exposure\',`g161` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Compliance_Exposure\',`g162` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Waivability_Type\',`g163` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Requested_Exception\',`g164` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Business_Justification\',`g165` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Department_Recommendation\',`g166` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Deputy_Recommendation\',`g167` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Escalation_To\',`g168` VARCHAR(190) NULL DEFAULT NULL COMMENT \'CEO_Decision\',`g169` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Conditions\',`g170` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Valid_From\',`g171` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Valid_To\',`g172` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Evidence\',`g173` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Closure\',`g174` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g175` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g176` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g177` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_57018cf8_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-13 - تجاوز خط احمر واحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_redline_breach
'; }
else { echo 'x exec_redline_breach: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_reserved_matter` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g178` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المسألة\',`g179` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع المسألة\',`g180` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأساس في وثائق الشركة\',`g181` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السلطة المخولة\',`g182` VARCHAR(190) NULL DEFAULT NULL COMMENT \'هل تحال بالكامل أم برأي الرئيس؟\',`g183` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الوارد\',`g184` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة المصدر\',`g185` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة/الأثر\',`g186` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رأي الرئيس المرفوع\',`g187` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإحالة\',`g188` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قرار الجهة الحاكمة\',`g189` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ القرار\',`g190` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة المسألة\',`g191` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g192` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g193` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g194` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g195` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g196` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g197` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_49d91b69_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-14 - مسالة محجوزة واحدة\'';
if ($conn->query($sql)) { echo '+ جدول exec_reserved_matter
'; }
else { echo 'x exec_reserved_matter: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_critical_exception` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g198` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف البند\',`g199` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الاستثناء\',`g200` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة المصدر\',`g201` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قاعدة المنع/السياسة\',`g202` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المبرر\',`g203` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المدة المطلوبة\',`g204` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رأي الحوكمة\',`g205` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رأي النائب المختص\',`g206` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التعرض المقدر\',`g207` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_Appetite_Status\',`g208` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القرار\',`g209` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قبول المخاطرة موثق؟\',`g210` VARCHAR(190) NULL DEFAULT NULL COMMENT \'شروط إضافية\',`g211` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البند\',`g212` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g213` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g214` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g215` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_f968f12d_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-15 - استثناء حرج واحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_critical_exception
'; }
else { echo 'x exec_critical_exception: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_assurance_report` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g216` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الوارد\',`g217` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع التقرير/الملاحظة\',`g218` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الوارد\',`g219` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الجهة الخاضعة\',`g220` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الرأي العام\',`g221` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الملاحظات الحرجة\',`g222` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتأخرة\',`g223` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتكررة\',`g224` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التعرض المقدر\',`g225` VARCHAR(190) NULL DEFAULT NULL COMMENT \'توصية المراجعة\',`g226` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قرار الرئيس\',`g227` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المكلف بالتنفيذ\',`g228` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مهلة التنفيذ\',`g229` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المتابعة ر15\',`g230` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الوارد\',`g231` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g232` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g233` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g234` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_2164fadd_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-16 - تقرير تاكيد مستقل واحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_assurance_report
'; }
else { echo 'x exec_assurance_report: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_escalation` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g235` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف التصعيد\',`g236` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وقت الوصول\',`g237` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر التصعيد\',`g238` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرجع الأصلي\',`g239` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المسار المقطوع\',`g240` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملخص الحالة\',`g241` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأثر الجاري\',`g242` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القرار\',`g243` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المكلف بالتنفيذ\',`g244` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مهلة التنفيذ\',`g245` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة التصعيد\',`g246` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g247` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g248` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g249` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_8d65183d_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-17 - تصعيد واحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_escalation
'; }
else { echo 'x exec_escalation: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_crisis_case` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g250` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Crisis_ID\',`g251` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Type\',`g252` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Severity\',`g253` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Site/Project\',`g254` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Declared_At\',`g255` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Incident_Commander مرجع القائد\',`g256` VARCHAR(190) NULL DEFAULT NULL COMMENT \'People_Impact\',`g257` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Operational_Impact\',`g258` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Financial_Impact\',`g259` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Legal_Regulatory_Impact\',`g260` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الحدث الأصلي\',`g261` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Immediate_Actions Decision Event\',`g262` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Decisions_Required\',`g263` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Next_Update_At\',`g264` VARCHAR(190) NULL DEFAULT NULL COMMENT \'External_Communication_Status\',`g265` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Current_Status\',`g266` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Stand_Down_Date\',`g267` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g268` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g269` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g270` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_e74ef9fc_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-18 - حالة ازمة واحدة\'';
if ($conn->query($sql)) { echo '+ جدول exec_crisis_case
'; }
else { echo 'x exec_crisis_case: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_strategic_decision` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g271` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Decision_ID\',`g272` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الكيان\',`g273` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع القرار\',`g274` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Proposal_Source\',`g275` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Business_Case\',`g276` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الخيارات المطروحة\',`g277` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الخيار المختار\',`g278` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مبرر الاختيار\',`g279` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Financial_Impact\',`g280` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_Assessment\',`g281` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Legal_Review_Status\',`g282` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Finance_Review_Status\',`g283` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Compliance_Review_Status\',`g284` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Risk_Review_Status\',`g285` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Relevant_Deputy_Recommendation\',`g286` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص اكتمال بوابة المراجعات\',`g287` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Recommendation\',`g288` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Decision\',`g289` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Effective_Date\',`g290` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Owner مرجع مالك التنفيذ\',`g291` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Milestones\',`g292` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Follow_Up\',`g293` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Closure\',`g294` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g295` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g296` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g297` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g298` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g299` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g300` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_21570c71_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-19 - قرار استراتيجي واحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_strategic_decision
'; }
else { echo 'x exec_strategic_decision: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_leadership_appointment` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g317` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الطلب\',`g318` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الطلب\',`g319` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المسمى القيادي من الهيكل\',`g320` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة التنظيمية\',`g321` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرشح/المعني\',`g322` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الشخص بالموارد\',`g323` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سقف الاعتماد الممنوح\',`g324` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بدل التكليف\',`g325` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إفصاح طرف ذي علاقة\',`g326` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مراجعة الموارد البشرية\',`g327` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مراجعة الحوكمة\',`g328` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قاعدة AAM المفعلة\',`g329` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قرار الرئيس\',`g330` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الإحالة للسلطة المحجوزة\',`g331` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ النفاذ\',`g332` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الطلب\',`g333` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g334` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g335` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g336` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g337` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g338` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g339` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_ecf6d84c_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-23 - موافقة تعيين واحدة\'';
if ($conn->query($sql)) { echo '+ جدول exec_leadership_appointment
'; }
else { echo 'x exec_leadership_appointment: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_meeting_decision` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g340` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Decision_ID\',`g341` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Meeting_ID\',`g342` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Topic\',`g343` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Decision\',`g344` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Owner مرجع مسؤول التنفيذ\',`g345` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Department مرجع الإدارة\',`g346` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Due_Date\',`g347` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Priority\',`g348` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Status\',`g349` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Evidence يرفقه المنفذ\',`g350` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Closed_Date\',`g351` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g352` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g353` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g354` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_e4c65084_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-25 - قرار اجتماع واحد\'';
if ($conn->query($sql)) { echo '+ جدول exec_meeting_decision
'; }
else { echo 'x exec_meeting_decision: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `exec_action_followup` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g355` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Action_ID\',`g356` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر القرار\',`g357` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرجع الأصلي\',`g358` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموضوع\',`g359` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة\',`g360` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المسؤول\',`g361` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Due_Date\',`g362` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Priority\',`g363` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Status\',`g364` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أيام التأخير\',`g365` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Evidence\',`g366` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Closure\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_37e70378_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'CEO-26 - قرار تنفيذي متابع\'';
if ($conn->query($sql)) { echo '+ جدول exec_action_followup
'; }
else { echo 'x exec_action_followup: ' . $conn->error . chr(10); }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g301'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g301 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g301` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الكيان'")) {
    echo "+ exec_project_charters.g301\n";
} else { echo "x exec_project_charters.g301: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g302'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g302 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g302` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اسم المشروع'")) {
    echo "+ exec_project_charters.g302\n";
} else { echo "x exec_project_charters.g302: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g303'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g303 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g303` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم العميل'")) {
    echo "+ exec_project_charters.g303\n";
} else { echo "x exec_project_charters.g303: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g304'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g304 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g304` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع العقد'")) {
    echo "+ exec_project_charters.g304\n";
} else { echo "x exec_project_charters.g304: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g305'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g305 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g305` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نموذج العمل'")) {
    echo "+ exec_project_charters.g305\n";
} else { echo "x exec_project_charters.g305: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g306'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g306 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g306` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المدة'")) {
    echo "+ exec_project_charters.g306\n";
} else { echo "x exec_project_charters.g306: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g307'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g307 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g307` VARCHAR(190) NULL DEFAULT NULL COMMENT 'صلاحياته الممنوحة'")) {
    echo "+ exec_project_charters.g307\n";
} else { echo "x exec_project_charters.g307: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g308'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g308 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g308` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اكتمال الإفادات الست'")) {
    echo "+ exec_project_charters.g308\n";
} else { echo "x exec_project_charters.g308: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g309'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g309 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g309` VARCHAR(190) NULL DEFAULT NULL COMMENT 'قرار الفتح'")) {
    echo "+ exec_project_charters.g309\n";
} else { echo "x exec_project_charters.g309: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g310'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g310 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g310` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ القرار'")) {
    echo "+ exec_project_charters.g310\n";
} else { echo "x exec_project_charters.g310: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g311'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g311 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g311` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة الميثاق'")) {
    echo "+ exec_project_charters.g311\n";
} else { echo "x exec_project_charters.g311: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g312'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g312 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g312` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المنشئ'")) {
    echo "+ exec_project_charters.g312\n";
} else { echo "x exec_project_charters.g312: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g313'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g313 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g313` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الإنشاء'")) {
    echo "+ exec_project_charters.g313\n";
} else { echo "x exec_project_charters.g313: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g314'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g314 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g314` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المراجع'")) {
    echo "+ exec_project_charters.g314\n";
} else { echo "x exec_project_charters.g314: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g315'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g315 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g315` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ exec_project_charters.g315\n";
} else { echo "x exec_project_charters.g315: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_project_charters` LIKE 'g316'");
if ($q && $q->num_rows) { echo "= exec_project_charters.g316 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_project_charters` ADD COLUMN `g316` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع المصدر'")) {
    echo "+ exec_project_charters.g316\n";
} else { echo "x exec_project_charters.g316: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
