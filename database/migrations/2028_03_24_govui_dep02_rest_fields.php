<?php
/**
 * 2028_03_24_govui_dep02_rest_fields.php — DEP-02 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for DEP-02
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

$sql = 'CREATE TABLE IF NOT EXISTS `sup_contact_delegate` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`c30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الجهة\',`c31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`c32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المورد (بحث)\',`c33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاسم\',`c34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الصفة/الدور\',`c35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع التفويض\',`c36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستند التفويض\',`c37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سريان التفويض من\',`c38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إلى\',`c39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الهاتف الأساسي\',`c40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'هاتف بديل\',`c41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البريد\',`c42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة جهة الاتصال\',`c43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحجية\',`c44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`c45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_6d308ef1_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SUP-02 - جهة اتصال واحدة لمورد\'';
if ($conn->query($sql)) { echo '+ جدول sup_contact_delegate
'; }
else { echo 'x sup_contact_delegate: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sup_equipment_available` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`c46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود المعدة\',`c47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التسلسل الزمني للإسناد\',`c48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`c49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المورد (بحث)\',`c50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الآلية/البند\',`c51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'بادئة النوع (v4)\',`c52` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم اللوحة (كما ورد)\',`c53` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رمز اللوحة\',`c54` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم اللوحة (رقمي)\',`c55` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الشاسي\',`c56` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سنة الصنع\',`c57` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الملكية\',`c58` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المالك القانوني للمعدة\',`c59` VARCHAR(190) NULL DEFAULT NULL COMMENT \'هاتف المالك\',`c60` VARCHAR(190) NULL DEFAULT NULL COMMENT \'صفة المورد تجاه المعدة\',`c61` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستند حق التقديم (نوع/رقم/تاريخ/جهة)\',`c62` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة التحقق من الصفة\',`c63` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ التحقق\',`c64` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتحقق\',`c65` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مخاطر الصفة\',`c66` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وسم «لا ترسمل»\',`c67` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة المعدة\',`c68` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ أول إسناد\',`c69` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ آخر إسناد\',`c70` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أشهر منذ آخر نشاط\',`c71` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الوحدات التعاقدية التي خدمها\',`c72` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر عميل خدمه\',`c73` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس حالة المعدة\',`c74` VARCHAR(190) NULL DEFAULT NULL COMMENT \'علم تعارض الحالة\',`c75` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الحالة\',`c76` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معدة مساندة؟\',`c77` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرادفات أكواد قديمة\',`c78` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر التوثيق\',`c79` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_881dec8f_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SUP-04 - معدة واحدة متاحة لمورد\'';
if ($conn->query($sql)) { echo '+ جدول sup_equipment_available
'; }
else { echo 'x sup_equipment_available: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sup_rfq_review` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`c80` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الترشيح\',`c81` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الاحتياج\',`c82` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود العقد\',`c83` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`c84` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المورد (بحث)\',`c85` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الآلية/البند\',`c86` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود المعدة المعروضة\',`c87` VARCHAR(190) NULL DEFAULT NULL COMMENT \'السعر المعروض\',`c88` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`c89` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ العرض\',`c90` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص التأهيل القانوني\',`c91` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص الحساب البنكي\',`c92` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص تعارض المصالح\',`c93` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص السقف الائتماني\',`c94` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص الهامش (ضمن الحد)\',`c95` VARCHAR(190) NULL DEFAULT NULL COMMENT \'فحص توافق الحصة مع الاحتياج\',`c96` VARCHAR(190) NULL DEFAULT NULL COMMENT \'استثناء معتمد (مرجع)\',`c97` VARCHAR(190) NULL DEFAULT NULL COMMENT \'جاهزية التوقيع\',`c98` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`c99` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`c100` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`c101` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ المراجعة\',`c102` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الترشيح\',`c103` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سبب الاختيار/الاستبعاد\',`c104` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود عقد المورد الناتج\',`c105` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`c106` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_2446c825_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SUP-06 - ترشيح واحد بمراجعة تعاقده\'';
if ($conn->query($sql)) { echo '+ جدول sup_rfq_review
'; }
else { echo 'x sup_rfq_review: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sup_contract_register` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`c107` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود عقد المورد\',`c108` VARCHAR(190) NULL DEFAULT NULL COMMENT \'التسلسل الزمني للعقد\',`c109` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`c110` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المورد (بحث)\',`c111` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود العقد (العميل)\',`c112` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم العميل\',`c113` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم العميل (بحث)\',`c114` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نموذج العمل\',`c115` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم عقد المورد (رتبة الانضمام)\',`c116` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تسلسل عقد المورد بالشركة\',`c117` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مفتاح دورة الالتزام\',`c118` VARCHAR(190) NULL DEFAULT NULL COMMENT \'استثناء تسوية فقط؟\',`c119` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع اعتماد الاستثناء\',`c120` VARCHAR(190) NULL DEFAULT NULL COMMENT \'توقيع الوثيقة\',`c121` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البداية التعاقدية (مستنتجة)\',`c122` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النهاية التعاقدية (مستنتجة)\',`c123` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس استنتاج المدة\',`c124` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البداية التنفيذية\',`c125` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النهاية التنفيذية\',`c126` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الانضمام (أول يوم عمل)\',`c127` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة العقد\',`c128` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أساس حالة العقد\',`c129` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تعارض مع حالة عقد العميل\',`c130` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تكييف الوثيقة\',`c131` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستوى الحجية\',`c132` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`c133` VARCHAR(190) NULL DEFAULT NULL COMMENT \'شروط السداد\',`c134` VARCHAR(190) NULL DEFAULT NULL COMMENT \'موقع التنفيذ/المنجم\',`c135` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة الشهرية التعاقدية\',`c136` VARCHAR(190) NULL DEFAULT NULL COMMENT \'قيمة العقد التقديرية\',`c137` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد البنود\',`c138` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الوحدات التعاقدية المسندة\',`c139` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نسخة العقد الحالية\',`c140` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع آخر ملحق (م21)\',`c141` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الإغلاق\',`c142` VARCHAR(190) NULL DEFAULT NULL COMMENT \'علم انتهاء قبل عقد العميل\',`c143` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حلقات الحجية المتوفرة (من 20)\',`c144` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نسبة اكتمال الملف المستندي\',`c145` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحلقات المفقودة\',`c146` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البيانات\',`c147` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`c148` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`c149` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`c150` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`c151` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد الداخلي\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_1026c2a1_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SUP-08 - عقد مورد واحد\'';
if ($conn->query($sql)) { echo '+ جدول sup_contract_register
'; }
else { echo 'x sup_contract_register: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sup_contract_line` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`c152` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البند\',`c153` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود عقد المورد\',`c154` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`c155` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المورد (بحث)\',`c156` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم بند عقد العميل المقابل\',`c157` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نموذج العمل\',`c158` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الآلية/البند\',`c159` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وحدة القياس\',`c160` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحصة الشهرية المتعاقدة\',`c161` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر الوحدة الأساسي\',`c162` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر الوحدة الإضافي\',`c163` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر السداد ≤15 يوما\',`c164` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر السداد 15 45\',`c165` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر السداد >45\',`c166` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المبلغ الشهري الأساسي\',`c167` VARCHAR(190) NULL DEFAULT NULL COMMENT \'عدد الورديات\',`c168` VARCHAR(190) NULL DEFAULT NULL COMMENT \'العملة\',`c169` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سريان التركيبة من\',`c170` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إلى\',`c171` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Pricing_Model\',`c172` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Billing_UOM\',`c173` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Minimum_Qty (أساس الوحدة التعاقدية)\',`c174` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Minimum_Period\',`c175` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Guaranteed_Qty\',`c176` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Threshold_Qty\',`c177` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Shortfall_Bearer\',`c178` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Pricing_Reference\',`c179` VARCHAR(190) NULL DEFAULT NULL COMMENT \'النسخة السعرية\',`c180` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة البند\',`c181` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر السعر/الحجية\',`c182` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_d4ffd5c0_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SUP-09 - بند واحد في عقد مورد\'';
if ($conn->query($sql)) { echo '+ جدول sup_contract_line
'; }
else { echo 'x sup_contract_line: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sup_entitlement` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`c183` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الاستحقاق\',`c184` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفترة (شهر)\',`c185` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`c186` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المورد (بحث)\',`c187` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود عقد المورد\',`c188` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم البند\',`c189` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وحدة القياس\',`c190` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مفتاح الشهر (YYYYMM)\',`c191` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الآلية/البند\',`c192` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدات المعتمدة\',`c193` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الاتفاق الشهري $\',`c194` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر الساعة $ (مطبق)\',`c195` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر الإضافية $ (مطبق)\',`c196` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر الساعة ج.س (مطبق)\',`c197` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر طن ويست (مطبق)\',`c198` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر طن خام (مطبق)\',`c199` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر متر تفجير (مطبق)\',`c200` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سعر متر G.C (مطبق)\',`c201` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستحق الساعات $\',`c202` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستحق الساعات ج.س\',`c203` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مبلغ الإضافية $\',`c204` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستحق الأطنان $\',`c205` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستحق تفجير $\',`c206` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستحق G.C $\',`c207` VARCHAR(190) NULL DEFAULT NULL COMMENT \'(−) الخصومات\',`c208` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إجمالي الاستحقاق $\',`c209` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إجمالي الاستحقاق ج.س\',`c210` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستلم الكلي $ (مصدر)\',`c211` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتبقي $ (مصدر)\',`c212` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المتبقي ج.س (مصدر)\',`c213` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة الاستحقاق\',`c214` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الإثبات\',`c215` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع الأداء (م14)\',`c216` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر السعر\',`c217` VARCHAR(190) NULL DEFAULT NULL COMMENT \'البيان (مصدر)\',`c218` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_95c4ea86_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SUP-20 - استحقاق مورد لشهر\'';
if ($conn->query($sql)) { echo '+ جدول sup_entitlement
'; }
else { echo 'x sup_entitlement: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sup_dashboard_kpi` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`c275` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المؤشر\',`c276` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`c277` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعادلة/المصدر\',`c278` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الفترة\',`c279` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظة\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_c9c8c194_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SUP-30 - مؤشر واحد للوحة الموردين\'';
if ($conn->query($sql)) { echo '+ جدول sup_dashboard_kpi
'; }
else { echo 'x sup_dashboard_kpi: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `sup_performance_unit` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`c280` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم السطر\',`c281` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود الوحدة التعاقدية\',`c282` VARCHAR(190) NULL DEFAULT NULL COMMENT \'كود عقد المورد\',`c283` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم المورد\',`c284` VARCHAR(190) NULL DEFAULT NULL COMMENT \'اسم المورد (بحث)\',`c285` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نموذج العمل\',`c286` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم الشهر\',`c287` VARCHAR(190) NULL DEFAULT NULL COMMENT \'من\',`c288` VARCHAR(190) NULL DEFAULT NULL COMMENT \'إلى\',`c289` VARCHAR(190) NULL DEFAULT NULL COMMENT \'وحدة القياس\',`c290` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الآلية/البند\',`c291` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رمز الآلية (مصدر)\',`c292` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رقم اللوحة\',`c293` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ساعات الاتفاق (متعاقد)\',`c294` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أطنان متفق عليها\',`c295` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أمتار متفق عليها\',`c296` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنفذ (الأساس)\',`c297` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ساعات أساسية (مصدر)\',`c298` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أطنان ويست\',`c299` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أطنان خام\',`c300` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أمتار تفجير\',`c301` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أمتار G.C\',`c302` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مضافة عمل\',`c303` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستند المضافة عمل\',`c304` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مضافة تعطل\',`c305` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مضافة استعداد\',`c306` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مستند المضافة استعداد\',`c307` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مخصومة استعداد\',`c308` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مخصومة عمل\',`c309` VARCHAR(190) NULL DEFAULT NULL COMMENT \'سبب الخصم\',`c310` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع قرار الخصم\',`c311` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معتمد التسوية\',`c312` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الساعات الفعلية\',`c313` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الساعات الكلية\',`c314` VARCHAR(190) NULL DEFAULT NULL COMMENT \'صافي وحدات المورد المعتمدة\',`c315` VARCHAR(190) NULL DEFAULT NULL COMMENT \'منجزة العميل (قراءة)\',`c316` VARCHAR(190) NULL DEFAULT NULL COMMENT \'تاريخ الاعتماد\',`c317` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مصدر البيان\',`c318` VARCHAR(190) NULL DEFAULT NULL COMMENT \'طريقة الربط بالحصة\',`c319` VARCHAR(190) NULL DEFAULT NULL COMMENT \'حالة المورد وقت القيد\',`c320` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ملاحظات\',`c321` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المنشئ\',`c322` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المراجع\',`c323` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المعتمد\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_83e66d38_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'SUP-17 - سطر اداء لوحدة تعاقدية في شهر\'';
if ($conn->query($sql)) { echo '+ جدول sup_performance_unit
'; }
else { echo 'x sup_performance_unit: ' . $conn->error . chr(10); }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c1'");
if ($q && $q->num_rows) { echo "= suppliers.c1 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c1` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم المورد'")) {
    echo "+ suppliers.c1\n";
} else { echo "x suppliers.c1: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c2'");
if ($q && $q->num_rows) { echo "= suppliers.c2 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c2` VARCHAR(190) NULL DEFAULT NULL COMMENT 'التسلسل الزمني للتعامل'")) {
    echo "+ suppliers.c2\n";
} else { echo "x suppliers.c2: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c3'");
if ($q && $q->num_rows) { echo "= suppliers.c3 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c3` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الاسم القانوني'")) {
    echo "+ suppliers.c3\n";
} else { echo "x suppliers.c3: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c4'");
if ($q && $q->num_rows) { echo "= suppliers.c4 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c4` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الاسم المختصر'")) {
    echo "+ suppliers.c4\n";
} else { echo "x suppliers.c4: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c5'");
if ($q && $q->num_rows) { echo "= suppliers.c5 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c5` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المرادف القديم'")) {
    echo "+ suppliers.c5\n";
} else { echo "x suppliers.c5: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c6'");
if ($q && $q->num_rows) { echo "= suppliers.c6 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c6` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تصنيف المورد'")) {
    echo "+ suppliers.c6\n";
} else { echo "x suppliers.c6: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c7'");
if ($q && $q->num_rows) { echo "= suppliers.c7 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c7` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مصدر القيد'")) {
    echo "+ suppliers.c7\n";
} else { echo "x suppliers.c7: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c8'");
if ($q && $q->num_rows) { echo "= suppliers.c8 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c8` VARCHAR(190) NULL DEFAULT NULL COMMENT 'فئة مصدر القدرة'")) {
    echo "+ suppliers.c8\n";
} else { echo "x suppliers.c8: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c9'");
if ($q && $q->num_rows) { echo "= suppliers.c9 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c9` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نمط التعاقد'")) {
    echo "+ suppliers.c9\n";
} else { echo "x suppliers.c9: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c10'");
if ($q && $q->num_rows) { echo "= suppliers.c10 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c10` VARCHAR(190) NULL DEFAULT NULL COMMENT 'طبيعة المورد (مالك/وسيط)'")) {
    echo "+ suppliers.c10\n";
} else { echo "x suppliers.c10: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c11'");
if ($q && $q->num_rows) { echo "= suppliers.c11 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c11` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة المورد'")) {
    echo "+ suppliers.c11\n";
} else { echo "x suppliers.c11: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c12'");
if ($q && $q->num_rows) { echo "= suppliers.c12 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c12` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ آخر نشاط تشغيلي'")) {
    echo "+ suppliers.c12\n";
} else { echo "x suppliers.c12: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c13'");
if ($q && $q->num_rows) { echo "= suppliers.c13 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c13` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ آخر حركة مالية'")) {
    echo "+ suppliers.c13\n";
} else { echo "x suppliers.c13: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c14'");
if ($q && $q->num_rows) { echo "= suppliers.c14 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c14` VARCHAR(190) NULL DEFAULT NULL COMMENT 'أشهر منذ آخر نشاط'")) {
    echo "+ suppliers.c14\n";
} else { echo "x suppliers.c14: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c15'");
if ($q && $q->num_rows) { echo "= suppliers.c15 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c15` VARCHAR(190) NULL DEFAULT NULL COMMENT 'دليل آخر نشاط'")) {
    echo "+ suppliers.c15\n";
} else { echo "x suppliers.c15: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c16'");
if ($q && $q->num_rows) { echo "= suppliers.c16 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c16` VARCHAR(190) NULL DEFAULT NULL COMMENT 'أساس الحالة'")) {
    echo "+ suppliers.c16\n";
} else { echo "x suppliers.c16: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c17'");
if ($q && $q->num_rows) { echo "= suppliers.c17 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c17` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ أول تعامل'")) {
    echo "+ suppliers.c17\n";
} else { echo "x suppliers.c17: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c18'");
if ($q && $q->num_rows) { echo "= suppliers.c18 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c18` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الدولة'")) {
    echo "+ suppliers.c18\n";
} else { echo "x suppliers.c18: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c19'");
if ($q && $q->num_rows) { echo "= suppliers.c19 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c19` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المدينة'")) {
    echo "+ suppliers.c19\n";
} else { echo "x suppliers.c19: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c20'");
if ($q && $q->num_rows) { echo "= suppliers.c20 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c20` VARCHAR(190) NULL DEFAULT NULL COMMENT 'السجل التجاري'")) {
    echo "+ suppliers.c20\n";
} else { echo "x suppliers.c20: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c21'");
if ($q && $q->num_rows) { echo "= suppliers.c21 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c21` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نماذج التعامل'")) {
    echo "+ suppliers.c21\n";
} else { echo "x suppliers.c21: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c22'");
if ($q && $q->num_rows) { echo "= suppliers.c22 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c22` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملات المتعامل بها'")) {
    echo "+ suppliers.c22\n";
} else { echo "x suppliers.c22: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c23'");
if ($q && $q->num_rows) { echo "= suppliers.c23 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c23` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عدد الحصص'")) {
    echo "+ suppliers.c23\n";
} else { echo "x suppliers.c23: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c24'");
if ($q && $q->num_rows) { echo "= suppliers.c24 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c24` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عدد المعدات المسندة'")) {
    echo "+ suppliers.c24\n";
} else { echo "x suppliers.c24: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c25'");
if ($q && $q->num_rows) { echo "= suppliers.c25 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c25` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة التأهيل (م03)'")) {
    echo "+ suppliers.c25\n";
} else { echo "x suppliers.c25: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c26'");
if ($q && $q->num_rows) { echo "= suppliers.c26 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c26` VARCHAR(190) NULL DEFAULT NULL COMMENT 'التصنيف الاستراتيجي (م20)'")) {
    echo "+ suppliers.c26\n";
} else { echo "x suppliers.c26: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c27'");
if ($q && $q->num_rows) { echo "= suppliers.c27 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c27` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مستوى الحجية'")) {
    echo "+ suppliers.c27\n";
} else { echo "x suppliers.c27: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c28'");
if ($q && $q->num_rows) { echo "= suppliers.c28 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c28` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مصدر التوثيق'")) {
    echo "+ suppliers.c28\n";
} else { echo "x suppliers.c28: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c29'");
if ($q && $q->num_rows) { echo "= suppliers.c29 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `suppliers` ADD COLUMN `c29` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات'")) {
    echo "+ suppliers.c29\n";
} else { echo "x suppliers.c29: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c219'");
if ($q && $q->num_rows) { echo "= sup_violations.c219 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c219` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم القيد'")) {
    echo "+ sup_violations.c219\n";
} else { echo "x sup_violations.c219: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c220'");
if ($q && $q->num_rows) { echo "= sup_violations.c220 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c220` VARCHAR(190) NULL DEFAULT NULL COMMENT 'التاريخ'")) {
    echo "+ sup_violations.c220\n";
} else { echo "x sup_violations.c220: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c221'");
if ($q && $q->num_rows) { echo "= sup_violations.c221 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c221` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم المورد'")) {
    echo "+ sup_violations.c221\n";
} else { echo "x sup_violations.c221: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c222'");
if ($q && $q->num_rows) { echo "= sup_violations.c222 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c222` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اسم المورد (بحث)'")) {
    echo "+ sup_violations.c222\n";
} else { echo "x sup_violations.c222: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c223'");
if ($q && $q->num_rows) { echo "= sup_violations.c223 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c223` VARCHAR(190) NULL DEFAULT NULL COMMENT 'كود عقد المورد'")) {
    echo "+ sup_violations.c223\n";
} else { echo "x sup_violations.c223: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c224'");
if ($q && $q->num_rows) { echo "= sup_violations.c224 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c224` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع الجزاء/المطالبة'")) {
    echo "+ sup_violations.c224\n";
} else { echo "x sup_violations.c224: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c225'");
if ($q && $q->num_rows) { echo "= sup_violations.c225 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c225` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الوصف'")) {
    echo "+ sup_violations.c225\n";
} else { echo "x sup_violations.c225: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c226'");
if ($q && $q->num_rows) { echo "= sup_violations.c226 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c226` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المرجع التعاقدي (بند)'")) {
    echo "+ sup_violations.c226\n";
} else { echo "x sup_violations.c226: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c227'");
if ($q && $q->num_rows) { echo "= sup_violations.c227 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c227` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المبلغ/الأثر'")) {
    echo "+ sup_violations.c227\n";
} else { echo "x sup_violations.c227: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c228'");
if ($q && $q->num_rows) { echo "= sup_violations.c228 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c228` VARCHAR(190) NULL DEFAULT NULL COMMENT 'العملة'")) {
    echo "+ sup_violations.c228\n";
} else { echo "x sup_violations.c228: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c229'");
if ($q && $q->num_rows) { echo "= sup_violations.c229 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c229` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الحالة'")) {
    echo "+ sup_violations.c229\n";
} else { echo "x sup_violations.c229: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c230'");
if ($q && $q->num_rows) { echo "= sup_violations.c230 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c230` VARCHAR(190) NULL DEFAULT NULL COMMENT 'القرار'")) {
    echo "+ sup_violations.c230\n";
} else { echo "x sup_violations.c230: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c231'");
if ($q && $q->num_rows) { echo "= sup_violations.c231 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c231` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معتمد القرار'")) {
    echo "+ sup_violations.c231\n";
} else { echo "x sup_violations.c231: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c232'");
if ($q && $q->num_rows) { echo "= sup_violations.c232 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c232` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مرجع الخصم بالتسوية (م17)'")) {
    echo "+ sup_violations.c232\n";
} else { echo "x sup_violations.c232: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c233'");
if ($q && $q->num_rows) { echo "= sup_violations.c233 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c233` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ sup_violations.c233\n";
} else { echo "x sup_violations.c233: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c234'");
if ($q && $q->num_rows) { echo "= sup_violations.c234 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_violations` ADD COLUMN `c234` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات'")) {
    echo "+ sup_violations.c234\n";
} else { echo "x sup_violations.c234: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c235'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c235 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c235` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم المورد'")) {
    echo "+ supplier_evaluations.c235\n";
} else { echo "x supplier_evaluations.c235: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c236'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c236 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c236` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اسم المورد (بحث)'")) {
    echo "+ supplier_evaluations.c236\n";
} else { echo "x supplier_evaluations.c236: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c237'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c237 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c237` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الفترة'")) {
    echo "+ supplier_evaluations.c237\n";
} else { echo "x supplier_evaluations.c237: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c238'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c238 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c238` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عقود نشطة'")) {
    echo "+ supplier_evaluations.c238\n";
} else { echo "x supplier_evaluations.c238: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c239'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c239 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c239` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عقود منتهية بلا إغلاق'")) {
    echo "+ supplier_evaluations.c239\n";
} else { echo "x supplier_evaluations.c239: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c240'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c240 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c240` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حصص (خانات)'")) {
    echo "+ supplier_evaluations.c240\n";
} else { echo "x supplier_evaluations.c240: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c241'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c241 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c241` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معدات مسندة'")) {
    echo "+ supplier_evaluations.c241\n";
} else { echo "x supplier_evaluations.c241: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c242'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c242 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c242` VARCHAR(190) NULL DEFAULT NULL COMMENT 'انكشاف الاستحقاق $'")) {
    echo "+ supplier_evaluations.c242\n";
} else { echo "x supplier_evaluations.c242: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c243'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c243 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c243` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المدفوع $'")) {
    echo "+ supplier_evaluations.c243\n";
} else { echo "x supplier_evaluations.c243: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c244'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c244 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c244` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الرصيد المستحق $'")) {
    echo "+ supplier_evaluations.c244\n";
} else { echo "x supplier_evaluations.c244: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c245'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c245 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c245` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نسبة التركز من محفظة الاستحقاق'")) {
    echo "+ supplier_evaluations.c245\n";
} else { echo "x supplier_evaluations.c245: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c246'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c246 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c246` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Σ المستهدف (تجميعي عبر الوحدات)'")) {
    echo "+ supplier_evaluations.c246\n";
} else { echo "x supplier_evaluations.c246: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c247'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c247 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c247` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Σ المنفذ (تجميعي)'")) {
    echo "+ supplier_evaluations.c247\n";
} else { echo "x supplier_evaluations.c247: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c248'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c248 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c248` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نسبة تحقق الحصص'")) {
    echo "+ supplier_evaluations.c248\n";
} else { echo "x supplier_evaluations.c248: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c249'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c249 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c249` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Σ مخصومة عمل (ساعات)'")) {
    echo "+ supplier_evaluations.c249\n";
} else { echo "x supplier_evaluations.c249: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c250'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c250 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c250` VARCHAR(190) NULL DEFAULT NULL COMMENT 'معدل الخصومات'")) {
    echo "+ supplier_evaluations.c250\n";
} else { echo "x supplier_evaluations.c250: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c251'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c251 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c251` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نسبة معدات غير متحقق الصفة'")) {
    echo "+ supplier_evaluations.c251\n";
} else { echo "x supplier_evaluations.c251: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c252'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c252 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c252` VARCHAR(190) NULL DEFAULT NULL COMMENT 'موردون بديلون لأضيق نوع'")) {
    echo "+ supplier_evaluations.c252\n";
} else { echo "x supplier_evaluations.c252: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c253'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c253 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c253` VARCHAR(190) NULL DEFAULT NULL COMMENT 'عقود تنتهي قبل عقد العميل'")) {
    echo "+ supplier_evaluations.c253\n";
} else { echo "x supplier_evaluations.c253: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c254'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c254 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c254` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تصنيف المخاطرة المركب'")) {
    echo "+ supplier_evaluations.c254\n";
} else { echo "x supplier_evaluations.c254: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c255'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c255 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c255` VARCHAR(190) NULL DEFAULT NULL COMMENT 'درجة المخاطرة'")) {
    echo "+ supplier_evaluations.c255\n";
} else { echo "x supplier_evaluations.c255: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c256'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c256 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c256` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الالتزام بالمواعيد %'")) {
    echo "+ supplier_evaluations.c256\n";
} else { echo "x supplier_evaluations.c256: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c257'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c257 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c257` VARCHAR(190) NULL DEFAULT NULL COMMENT 'جودة المعدات (1 5)'")) {
    echo "+ supplier_evaluations.c257\n";
} else { echo "x supplier_evaluations.c257: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c258'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c258 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c258` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الاستجابة للبلاغات'")) {
    echo "+ supplier_evaluations.c258\n";
} else { echo "x supplier_evaluations.c258: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c259'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c259 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c259` VARCHAR(190) NULL DEFAULT NULL COMMENT 'التصنيف الاستراتيجي'")) {
    echo "+ supplier_evaluations.c259\n";
} else { echo "x supplier_evaluations.c259: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c260'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c260 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c260` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظات الأداء'")) {
    echo "+ supplier_evaluations.c260\n";
} else { echo "x supplier_evaluations.c260: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c261'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c261 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c261` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المقيم'")) {
    echo "+ supplier_evaluations.c261\n";
} else { echo "x supplier_evaluations.c261: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c262'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c262 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c262` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تاريخ التقييم'")) {
    echo "+ supplier_evaluations.c262\n";
} else { echo "x supplier_evaluations.c262: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c263'");
if ($q && $q->num_rows) { echo "= supplier_evaluations.c263 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_evaluations` ADD COLUMN `c263` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة الاعتماد'")) {
    echo "+ supplier_evaluations.c263\n";
} else { echo "x supplier_evaluations.c263: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c264'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c264 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c264` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم السطر'")) {
    echo "+ supplier_capacity.c264\n";
} else { echo "x supplier_capacity.c264: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c265'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c265 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c265` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مصدر القدرة'")) {
    echo "+ supplier_capacity.c265\n";
} else { echo "x supplier_capacity.c265: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c266'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c266 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c266` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المرجع (كود عقد مورد / كود أصل)'")) {
    echo "+ supplier_capacity.c266\n";
} else { echo "x supplier_capacity.c266: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c267'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c267 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c267` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نوع الآلية/البند'")) {
    echo "+ supplier_capacity.c267\n";
} else { echo "x supplier_capacity.c267: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c268'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c268 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c268` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الطاقة الشهرية'")) {
    echo "+ supplier_capacity.c268\n";
} else { echo "x supplier_capacity.c268: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c269'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c269 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c269` VARCHAR(190) NULL DEFAULT NULL COMMENT 'وحدة القياس'")) {
    echo "+ supplier_capacity.c269\n";
} else { echo "x supplier_capacity.c269: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c270'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c270 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c270` VARCHAR(190) NULL DEFAULT NULL COMMENT 'تكلفة الوحدة (قراءة)'")) {
    echo "+ supplier_capacity.c270\n";
} else { echo "x supplier_capacity.c270: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c271'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c271 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c271` VARCHAR(190) NULL DEFAULT NULL COMMENT 'نظام المصدر'")) {
    echo "+ supplier_capacity.c271\n";
} else { echo "x supplier_capacity.c271: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c272'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c272 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c272` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الحالة'")) {
    echo "+ supplier_capacity.c272\n";
} else { echo "x supplier_capacity.c272: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c273'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c273 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c273` VARCHAR(190) NULL DEFAULT NULL COMMENT 'حالة البيانات'")) {
    echo "+ supplier_capacity.c273\n";
} else { echo "x supplier_capacity.c273: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c274'");
if ($q && $q->num_rows) { echo "= supplier_capacity.c274 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `supplier_capacity` ADD COLUMN `c274` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ملاحظة الفصل'")) {
    echo "+ supplier_capacity.c274\n";
} else { echo "x supplier_capacity.c274: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
