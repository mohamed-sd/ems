<?php
/**
 * 2028_03_28_govui_dep06_fields.php — DEP-06 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-06
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

$sql = 'CREATE TABLE IF NOT EXISTS `tre_payment_queue` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g1` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الطلب\',`g2` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الطلب\',`g3` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الإدارة الطالبة\',`g4` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع المستفيد\',`g5` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستفيد\',`g6` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموجب المعتمد\',`g7` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص البوابة\',`g8` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة الطلب\',`g9` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g10` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر التمويل المستخدم\',`g11` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص إتاحة المصدر\',`g12` VARCHAR(190) NULL DEFAULT NULL COMMENT \'طريقة الدفع\',`g13` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاستحقاق\',`g14` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الطلب\',`g15` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g16` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g17` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g18` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g19` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g20` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_373a1791_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-08 - مدفوعات معتمدة بانتظار التنفيذ\'';
if ($conn->query($sql)) { echo '+ جدول tre_payment_queue
'; }
else { echo 'x tre_payment_queue: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_pay_batch` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأمر\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الطلب\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص اكتمال الاعتماد\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستفيد\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوعاء الصارف\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص رصيد الوعاء\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع الأول\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع الثاني\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص سريان التفويض\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع التنفيذ البنكي\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التنفيذ\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'انعكاس الذمم\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الأمر\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_2cdb9220_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-09 - أمر الدفع والتنفيذ\'';
if ($conn->query($sql)) { echo '+ جدول tre_pay_batch
'; }
else { echo 'x tre_pay_batch: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_vessels` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الوعاء\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم الوعاء\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الوعاء\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البنك/الموقع\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الحساب\',`g49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المفوضون بالتوقيع\',`g51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع التفويض\',`g52` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حد الصندوق\',`g53` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أمين الصندوق\',`g54` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الرصيد الدفتري\',`g55` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الوعاء\',`g56` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g57` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g58` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g59` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g60` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g61` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g62` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_ad45c5c8_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-02 - الحسابات البنكية والصناديق\'';
if ($conn->query($sql)) { echo '+ جدول tre_vessels
'; }
else { echo 'x tre_vessels: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_fx_deals` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g63` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الصفقة\',`g64` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التنفيذ\',`g65` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة المشتراة\',`g66` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة المدفوعة\',`g67` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المبلغ المشترى\',`g68` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر الصفقة\',`g69` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المكافئ\',`g70` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السعر المرجعي م09\',`g71` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفرق عن المرجعي\',`g72` VARCHAR(190) NULL DEFAULT NULL COMMENT \'جهة التنفيذ\',`g73` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الغرض\',`g74` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الحركة\',`g75` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الصفقة\',`g76` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g77` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g78` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g79` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_f05221b3_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-12 - تنفيذ عمليات الصرف الأجنبي\'';
if ($conn->query($sql)) { echo '+ جدول tre_fx_deals
'; }
else { echo 'x tre_fx_deals: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_instruments` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g80` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الأداة\',`g81` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاتجاه\',`g82` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الأداة\',`g83` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الأداة\',`g84` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البنك المسحوب عليه\',`g85` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة الأداة\',`g86` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g87` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الطرف\',`g88` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاستحقاق\',`g89` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرجع الموجب\',`g90` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوعاء\',`g91` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الأداة\',`g92` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g93` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g94` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g95` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_78d984d8_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-06 - سجل الأدوات المالية\'';
if ($conn->query($sql)) { echo '+ جدول tre_instruments
'; }
else { echo 'x tre_instruments: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_guarantees` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g96` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الأداة\',`g97` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النوع\',`g98` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستفيد\',`g99` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العقد المرتبط\',`g100` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g101` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g102` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نسبة الغطاء النقدي\',`g103` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التسهيل المستخدم\',`g104` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإصدار\',`g105` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الانتهاء\',`g106` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التمديدات\',`g107` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع ح04 النظامي\',`g108` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الأداة\',`g109` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g110` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g111` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`g112` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`g113` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`g114` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g115` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_f36f0aa1_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-15 - خطابات الضمان والاعتمادات المستندية\'';
if ($conn->query($sql)) { echo '+ جدول tre_guarantees
'; }
else { echo 'x tre_guarantees: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_petty_cash` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g116` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف العهدة\',`g117` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أمين العهدة\',`g118` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الموقع\',`g119` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حد العهدة\',`g120` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الفتح\',`g121` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السقف الزمني\',`g122` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المصروف الموثق\',`g123` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستندات المرفقة\',`g124` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتبقي\',`g125` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التسوية\',`g126` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نتيجة التسوية\',`g127` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التجديد\',`g128` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة العهدة\',`g129` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g130` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g131` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g132` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_32b111df_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-17 - عهد النثرية وتسويتها\'';
if ($conn->query($sql)) { echo '+ جدول tre_petty_cash
'; }
else { echo 'x tre_petty_cash: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_cash_moves` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g133` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف الحركة\',`g134` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التاريخ\',`g135` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوعاء\',`g136` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الحركة\',`g137` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g138` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g139` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوعاء المقابل\',`g140` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرجع الموجب\',`g141` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الرصيد بعد الحركة\',`g142` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الحركة\',`g143` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g144` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g145` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g146` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_f9715c5b_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-10 - حركة الخزينة والصناديق\'';
if ($conn->query($sql)) { echo '+ جدول tre_cash_moves
'; }
else { echo 'x tre_cash_moves: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_allocations` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g147` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف التخصيص\',`g148` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف التحصيل\',`g149` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الفاتورة\',`g150` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة الفاتورة\',`g151` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المخصص عليها\',`g152` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتبقي بعده\',`g153` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الفاتورة بعده\',`g154` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g155` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g156` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g157` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_d74cd853_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-07 - تخصيص التحصيل على الفواتير\'';
if ($conn->query($sql)) { echo '+ جدول tre_allocations
'; }
else { echo 'x tre_allocations: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_transfers` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g158` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم التحويل\',`g159` VARCHAR(190) NULL DEFAULT NULL COMMENT \'من وعاء\',`g160` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إلى وعاء\',`g161` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g162` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g163` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الغرض\',`g164` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع تفويض الموقع\',`g165` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع التنفيذ البنكي\',`g166` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تأكيد الوصول\',`g167` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة التحويل\',`g168` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g169` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g170` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g171` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_b0555693_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-11 - التحويلات بين الحسابات\'';
if ($conn->query($sql)) { echo '+ جدول tre_transfers
'; }
else { echo 'x tre_transfers: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_bank_reconciliation_fin` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g186` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المطابقة\',`g187` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحساب البنكي\',`g188` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الشهر\',`g189` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رصيد الكشف البنكي\',`g190` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رصيد الدفتر\',`g191` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفرق\',`g192` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بنود الفروق\',`g193` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سبب الفرق\',`g194` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معالجة الفرق\',`g195` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفرق المتبقي\',`g196` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرفق الكشف\',`g197` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة المطابقة\',`g198` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g199` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g200` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g201` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_5b4f706c_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-13 - المطابقة البنكية\'';
if ($conn->query($sql)) { echo '+ جدول tre_bank_reconciliation_fin
'; }
else { echo 'x tre_bank_reconciliation_fin: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_beneficiary` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g202` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المستفيد\',`g203` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المستفيد\',`g204` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع المستفيد\',`g205` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الحساب/IBAN\',`g206` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البنك\',`g207` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وثيقة التحقق\',`g208` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التحقق\',`g209` VARCHAR(190) NULL DEFAULT NULL COMMENT \'محقق مستقل\',`g210` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تغيير حساب معلق؟\',`g211` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة التحقق\',`g212` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`g213` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإنشاء\',`g214` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`g215` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع المصدر\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_8ba7d1df_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-03 - سجل المستفيدين والتحقق\'';
if ($conn->query($sql)) { echo '+ جدول tre_beneficiary
'; }
else { echo 'x tre_beneficiary: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `tre_dashboard_kpi` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g216` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المؤشر\',`g217` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المؤشر KPI Catalog\',`g218` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g219` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`g220` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g221` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تحديث\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_70f799f1_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'TRS-01 - مؤشر سيولة واحد\'';
if ($conn->query($sql)) { echo '+ جدول tre_dashboard_kpi
'; }
else { echo 'x tre_dashboard_kpi: ' . $conn->error . chr(10); }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g172'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g172 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g172` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معرف الجلسة'")) {
    echo "+ tre_cash_count.g172\n";
} else { echo "x tre_cash_count.g172: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g173'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g173 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g173` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الصندوق'")) {
    echo "+ tre_cash_count.g173\n";
} else { echo "x tre_cash_count.g173: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g174'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g174 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g174` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع الجرد'")) {
    echo "+ tre_cash_count.g174\n";
} else { echo "x tre_cash_count.g174: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g175'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g175 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g175` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الجرد'")) {
    echo "+ tre_cash_count.g175\n";
} else { echo "x tre_cash_count.g175: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g176'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g176 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g176` VARCHAR(190) NULL DEFAULT NULL COMMENT 'لجنة الجرد'")) {
    echo "+ tre_cash_count.g176\n";
} else { echo "x tre_cash_count.g176: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g177'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g177 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g177` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الرصيد الدفتري'")) {
    echo "+ tre_cash_count.g177\n";
} else { echo "x tre_cash_count.g177: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g178'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g178 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g178` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العد الفعلي'")) {
    echo "+ tre_cash_count.g178\n";
} else { echo "x tre_cash_count.g178: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g179'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g179 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g179` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تفصيل الفئات النقدية'")) {
    echo "+ tre_cash_count.g179\n";
} else { echo "x tre_cash_count.g179: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g180'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g180 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g180` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معالجة الفرق'")) {
    echo "+ tre_cash_count.g180\n";
} else { echo "x tre_cash_count.g180: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g181'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g181 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g181` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة الجلسة'")) {
    echo "+ tre_cash_count.g181\n";
} else { echo "x tre_cash_count.g181: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g182'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g182 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g182` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المنشئ'")) {
    echo "+ tre_cash_count.g182\n";
} else { echo "x tre_cash_count.g182: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g183'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g183 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g183` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ الإنشاء'")) {
    echo "+ tre_cash_count.g183\n";
} else { echo "x tre_cash_count.g183: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g184'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g184 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g184` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ tre_cash_count.g184\n";
} else { echo "x tre_cash_count.g184: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `tre_cash_count` LIKE 'g185'");
if ($q && $q->num_rows) { echo "= tre_cash_count.g185 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `tre_cash_count` ADD COLUMN `g185` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع المصدر'")) {
    echo "+ tre_cash_count.g185\n";
} else { echo "x tre_cash_count.g185: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
