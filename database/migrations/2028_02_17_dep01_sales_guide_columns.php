<?php
/**
 * 2028_02_17_dep01_sales_guide_columns.php — DEP-01 · إدارة المبيعات التعاقدية والعقود — جداولُ مواضعِ الدليل (GOV_EXEC §5)
 * @migration-objects: tables for DEP-01
 * مولَّدةٌ من `tools/gov_exec_dept_build.php --emit` على مواصفةِ الإدارة —
 * وأسماءُ الأعمدةِ تعليقُها اسمُ الحقلِ في ورقةِ الدليلِ حرفًا.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$SQL = array(
    <<<'SQL'
ALTER TABLE `activities`
    ADD COLUMN `client_no` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم العميل',
    ADD COLUMN `client_name` VARCHAR(80) NULL DEFAULT NULL COMMENT 'اسم العميل (بحث)',
    ADD COLUMN `project_no` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم المشروع',
    ADD COLUMN `opportunity_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الفرصة',
    ADD COLUMN `contract_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع العقد',
    ADD COLUMN `next_action` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الإجراء التالي',
    ADD COLUMN `next_action_date` DATE NULL DEFAULT NULL COMMENT 'تاريخه',
    ADD COLUMN `container_key` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مفتاح دورة الالتزام المصدر',
    ADD COLUMN `evidence_level` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مستوى الحجية',
    ADD COLUMN `retro_value_basis` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'أساس القيمة الرجعية'
SQL,
    <<<'SQL'
ALTER TABLE `fin_precontract_review`
    ADD COLUMN `client_no` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم العميل',
    ADD COLUMN `client_name` VARCHAR(80) NULL DEFAULT NULL COMMENT 'اسم العميل (بحث)',
    ADD COLUMN `project_no` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم المشروع',
    ADD COLUMN `final_offer_match` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مطابقة العرض النهائي',
    ADD COLUMN `scope_review` VARCHAR(255) NULL DEFAULT NULL COMMENT 'النطاق',
    ADD COLUMN `prices_review` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الأسعار',
    ADD COLUMN `quantities_review` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكميات',
    ADD COLUMN `currency_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'العملة',
    ADD COLUMN `payment_terms` VARCHAR(255) NULL DEFAULT NULL COMMENT 'شروط الدفع',
    ADD COLUMN `advance_terms` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المقدم',
    ADD COLUMN `guarantee_terms` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الضمان',
    ADD COLUMN `penalty_terms` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الغرامات',
    ADD COLUMN `client_obligations` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التزامات العميل',
    ADD COLUMN `commercial_risks` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المخاطر التجارية',
    ADD COLUMN `open_notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الملاحظات المفتوحة',
    ADD COLUMN `sign_readiness` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة الجاهزية للتوقيع',
    ADD COLUMN `closed_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الإقفال',
    ADD COLUMN `contract_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع العقد',
    ADD COLUMN `notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات',
    ADD COLUMN `container_key` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مفتاح دورة الالتزام المصدر',
    ADD COLUMN `evidence_level` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مستوى الحجية',
    ADD COLUMN `retro_value_basis` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'أساس القيمة الرجعية',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
ALTER TABLE `contractequipments`
    ADD COLUMN `line_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم البند · Line_ID',
    ADD COLUMN `client_no` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم العميل',
    ADD COLUMN `client_name` VARCHAR(80) NULL DEFAULT NULL COMMENT 'اسم العميل (بحث)',
    ADD COLUMN `business_model` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نموذج العمل',
    ADD COLUMN `monthly_unit_basis` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'أساس الوحدة الشهري',
    ADD COLUMN `price_version_from` DATE NULL DEFAULT NULL COMMENT 'سريان النسخة السعرية',
    ADD COLUMN `price_state` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'حالة سعر البند',
    ADD COLUMN `pricing_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس التسعير (رأس العقد)',
    ADD COLUMN `price_source_text` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'نص السعر كما ورد بالمصدر',
    ADD COLUMN `shortfall_rule` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحد الأدنى/قاعدة العجز',
    ADD COLUMN `mix_valid_from` DATE NULL DEFAULT NULL COMMENT 'سريان التركيبة الحالية',
    ADD COLUMN `container_key` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع دورة الالتزام',
    ADD COLUMN `target_source` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مصدر المستهدف',
    ADD COLUMN `notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
ALTER TABLE `contracts`
    ADD COLUMN `contract_code` VARCHAR(60) NULL DEFAULT NULL COMMENT 'كود العقد',
    ADD COLUMN `client_no` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم العميل',
    ADD COLUMN `client_name` VARCHAR(80) NULL DEFAULT NULL COMMENT 'اسم العميل (بحث)',
    ADD COLUMN `business_model` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نموذج العمل',
    ADD COLUMN `contract_evidence_level` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حجية العقد',
    ADD COLUMN `obl_fuel` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'الوقود',
    ADD COLUMN `obl_oils` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الزيوت',
    ADD COLUMN `obl_maintenance` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الصيانة',
    ADD COLUMN `obl_spare_parts` VARCHAR(255) NULL DEFAULT NULL COMMENT 'قطع الغيار',
    ADD COLUMN `obl_operators` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المشغلون (السائقون)',
    ADD COLUMN `obl_housing` VARCHAR(255) NULL DEFAULT NULL COMMENT 'السكن والإعاشة',
    ADD COLUMN `obl_mobilization` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ترحيل الذهاب',
    ADD COLUMN `obl_demobilization` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ترحيل العودة',
    ADD COLUMN `obl_insurance` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التأمين',
    ADD COLUMN `obl_damage` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الضرر',
    ADD COLUMN `obl_waiting` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الانتظار',
    ADD COLUMN `obl_breakdown` VARCHAR(255) NULL DEFAULT NULL COMMENT 'العطل',
    ADD COLUMN `obl_violations` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المخالفات',
    ADD COLUMN `obl_min_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'الحد الأدنى للساعات',
    ADD COLUMN `obl_operating_guarantee` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ضمان التشغيل',
    ADD COLUMN `obl_site_schedule` VARCHAR(255) NULL DEFAULT NULL COMMENT 'جدول عمل الموقع',
    ADD COLUMN `obl_violation_deduction` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'خصم ساعات المخالفة',
    ADD COLUMN `obl_unpaid_stoppage` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'التوقف غير المدفوع',
    ADD COLUMN `obl_termination` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الإنهاء',
    ADD COLUMN `obl_renewal` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التجديد',
    ADD COLUMN `obl_governing_law` VARCHAR(255) NULL DEFAULT NULL COMMENT 'القانون الحاكم',
    ADD COLUMN `obl_specific_bearing` VARCHAR(255) NULL DEFAULT NULL COMMENT 'بنود تحمُّل محددة',
    ADD COLUMN `obl_specific_terms` VARCHAR(255) NULL DEFAULT NULL COMMENT 'شروط تعاقدية محددة',
    ADD COLUMN `obl_silent_items` VARCHAR(255) NULL DEFAULT NULL COMMENT 'بنود مسكوت عنها',
    ADD COLUMN `obl_fill_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة تعبئة المصفوفة',
    ADD COLUMN `obl_evidence_level` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مستوى الحجية',
    ADD COLUMN `obl_source_text` VARCHAR(500) NULL DEFAULT NULL COMMENT 'نص المصفوفة كما ورد بالمصدر',
    ADD COLUMN `obl_derivation_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس الاشتقاق',
    ADD COLUMN `obl_notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
ALTER TABLE `contract_monthly_plan`
    ADD COLUMN `row_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم السطر',
    ADD COLUMN `container_key` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مفتاح دورة الالتزام',
    ADD COLUMN `client_no` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم العميل',
    ADD COLUMN `client_name` VARCHAR(80) NULL DEFAULT NULL COMMENT 'اسم العميل (بحث)',
    ADD COLUMN `business_model` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نموذج العمل',
    ADD COLUMN `renewal_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم التجديد',
    ADD COLUMN `month_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الشهر',
    ADD COLUMN `month_start` DATE NULL DEFAULT NULL COMMENT 'بداية الشهر',
    ADD COLUMN `month_end` DATE NULL DEFAULT NULL COMMENT 'نهاية الشهر',
    ADD COLUMN `line_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'البند',
    ADD COLUMN `machines_count` DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'عدد الآليات',
    ADD COLUMN `unit_basis` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'أساس الوحدة',
    ADD COLUMN `line_monthly_target` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المستهدف الشهري للبند',
    ADD COLUMN `full_monthly_target` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المستهدف الشهري الكامل',
    ADD COLUMN `uom_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوحدة',
    ADD COLUMN `target_source` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مصدر المستهدف',
    ADD COLUMN `notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات'
SQL,
    <<<'SQL'
ALTER TABLE `contract_commitments`
    ADD COLUMN `client_no` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم العميل',
    ADD COLUMN `client_name` VARCHAR(80) NULL DEFAULT NULL COMMENT 'اسم العميل (بحث)',
    ADD COLUMN `business_model` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نموذج العمل',
    ADD COLUMN `contract_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم العقد',
    ADD COLUMN `renewal_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم التجديد (دورة الالتزام)',
    ADD COLUMN `cycle_kind` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع دورة الالتزام',
    ADD COLUMN `contractual_from` DATE NULL DEFAULT NULL COMMENT 'السريان التعاقدي من',
    ADD COLUMN `contractual_to` DATE NULL DEFAULT NULL COMMENT 'إلى',
    ADD COLUMN `executive_from` DATE NULL DEFAULT NULL COMMENT 'التنفيذي من ◄',
    ADD COLUMN `executive_to` DATE NULL DEFAULT NULL COMMENT 'إلى ◄',
    ADD COLUMN `cycle_months` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'أشهر دورة الالتزام',
    ADD COLUMN `cycle_capacity` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'سعة دورة الالتزام (المستهدف)',
    ADD COLUMN `uom_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوحدة',
    ADD COLUMN `elapsed_target` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المستهدف المنقضي ◄',
    ADD COLUMN `recorded_monthly_plan` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المخطط الشهري المسجَّل',
    ADD COLUMN `executed_qty` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المنفَّذ',
    ADD COLUMN `measured_qty` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المنجز/المقاس',
    ADD COLUMN `achievement_pct` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'نسبة التحقق',
    ADD COLUMN `coverage_gap` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'فجوة التغطية',
    ADD COLUMN `cycle_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة دورة الالتزام',
    ADD COLUMN `evidence_level` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحجية',
    ADD COLUMN `cycle_notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات',
    ADD COLUMN `source_cycle_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة دورة الالتزام كما وردت بالمصدر',
    ADD COLUMN `previous_version` VARCHAR(255) NULL DEFAULT NULL COMMENT 'النسخة السابقة',
    ADD COLUMN `version_kind` VARCHAR(255) NULL DEFAULT NULL COMMENT 'تكييف النسخة',
    ADD COLUMN `changed_vs_previous` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ما الذي تغيّر عن النسخة السابقة',
    ADD COLUMN `change_events_count` INT NULL DEFAULT NULL COMMENT 'عدد وقائع التغيير',
    ADD COLUMN `trips_derived` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'النقلات ◄ (نقل)',
    ADD COLUMN `operating_hours_derived` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'ساعات التشغيل المنجزة ◄ (تخريم)',
    ADD COLUMN `cycle_pattern` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نمط دورة الالتزام ▼',
    ADD COLUMN `measure_unit` VARCHAR(80) NULL DEFAULT NULL COMMENT 'وحدة القياس ▼',
    ADD COLUMN `contracted_machines_count` DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'عدد الآليات المتعاقدة (= عدد الوحدات التعاقدية)',
    ADD COLUMN `min_guarantee` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الضمان الأدنى — عند نمط الضمان والعتبة',
    ADD COLUMN `billing_threshold` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'عتبة الفوترة — عند نمط الضمان والعتبة',
    ADD COLUMN `new_container_reason` VARCHAR(80) NULL DEFAULT NULL COMMENT 'سبب فتح حاوية جديدة ▼'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `sal_monthly_container` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف السطر',
    `container_key` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مفتاح دورة الالتزام السنوية ◄',
    `cycle_month_no` INT NULL DEFAULT NULL COMMENT 'رقم دورة الالتزام الشهرية (1..12 بترتيب أشهر دورة الالتزام لا الميلادية)',
    `month_start` DATE NULL DEFAULT NULL COMMENT 'بداية الشهر ◄',
    `month_end` DATE NULL DEFAULT NULL COMMENT 'نهاية الشهر ◄',
    `line_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع الآلية/البند ◄',
    `uom_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'وحدة القياس ◄',
    `monthly_capacity_target` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'سعة دورة الالتزام الشهرية (المستهدف) ◄',
    `slot_monthly_basis` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'أساس الوحدة التعاقدية الشهري ◄',
    `contract_units_count` DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'عدد الوحدات التعاقدية ◄',
    `elapsed_at_measure` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المنقضي بتاريخ القياس ◄',
    `executed_actual` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المنفَّذ فعلًا ◄',
    `achievement_pct` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'نسبة التحقق ◄',
    `coverage_gap` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'فجوة التغطية ◄',
    `loss_not_carried` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الفاقد (لا يُرحَّل) ◄',
    `partial_month_prorated` VARCHAR(80) NULL DEFAULT NULL COMMENT 'شهر جزئي بالتناسب؟ ▼',
    `cycle_month_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة دورة الالتزام الشهرية ▼',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_148a845b_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-01 — الحاويات الشهرية والفاقد · الحبة: دورةُ التزامٍ × شهرٌ — سطرُ حاويةٍ واحد'
SQL,
    <<<'SQL'
ALTER TABLE `monthly_performance`
    ADD COLUMN `row_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم السطر',
    ADD COLUMN `container_key` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مفتاح دورة الالتزام',
    ADD COLUMN `contract_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود العقد',
    ADD COLUMN `client_no` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم العميل',
    ADD COLUMN `client_name` VARCHAR(80) NULL DEFAULT NULL COMMENT 'اسم العميل (بحث)',
    ADD COLUMN `business_model` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نموذج العمل',
    ADD COLUMN `renewal_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم التجديد',
    ADD COLUMN `month_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الشهر',
    ADD COLUMN `month_from` DATE NULL DEFAULT NULL COMMENT 'من',
    ADD COLUMN `month_to` DATE NULL DEFAULT NULL COMMENT 'إلى',
    ADD COLUMN `line_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'البند',
    ADD COLUMN `machines_count` DECIMAL(12,3) NULL DEFAULT NULL COMMENT 'عدد الآليات ◄',
    ADD COLUMN `monthly_target` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المستهدف الشهري',
    ADD COLUMN `executed_qty` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الكمية المنفَّذة ◄',
    ADD COLUMN `uom_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوحدة',
    ADD COLUMN `operating_hours` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'ساعات التشغيل ◄',
    ADD COLUMN `trips_count` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'النقلات ◄',
    ADD COLUMN `deducted_standby` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'مخصومة/استعداد ◄',
    ADD COLUMN `deducted_work` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'مخصومة/عمل ◄',
    ADD COLUMN `added_work` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'مضافة/عمل ◄',
    ADD COLUMN `added_standby` DECIMAL(14,2) NULL DEFAULT NULL COMMENT 'مضافة/استعداد ◄',
    ADD COLUMN `measured_qty` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الكمية المنجزة (المقاسة) ◄',
    ADD COLUMN `executed_achievement` VARCHAR(255) NULL DEFAULT NULL COMMENT 'تحقق المنفَّذ',
    ADD COLUMN `measured_achievement` VARCHAR(255) NULL DEFAULT NULL COMMENT 'تحقق المنجز',
    ADD COLUMN `computed_revenue_usd` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'الإيراد المحسوب ($) ◄',
    ADD COLUMN `computed_revenue_sdg` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'الإيراد المحسوب (ج.س) ◄',
    ADD COLUMN `statement_source` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مصدر البيان',
    ADD COLUMN `perf_notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات',
    ADD COLUMN `billed_qty` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الكمية المفوترة للعميل ◄',
    ADD COLUMN `billed_usd` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'المفوتر للعميل ($) ◄',
    ADD COLUMN `unbilled_executed` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'منفَّذ غير مفوتر',
    ADD COLUMN `unclaimed_revenue` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'إيراد غير مطالَب به ($)',
    ADD COLUMN `invoice_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع الفاتورة',
    ADD COLUMN `contract_currency` VARCHAR(255) NULL DEFAULT NULL COMMENT 'عملة العقد',
    ADD COLUMN `revenue_columns_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة أعمدة الإيراد'
SQL,
    <<<'SQL'
ALTER TABLE `contract_amendments`
    ADD COLUMN `client_no` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم العميل',
    ADD COLUMN `client_name` VARCHAR(80) NULL DEFAULT NULL COMMENT 'اسم العميل (بحث)',
    ADD COLUMN `doc_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الوثيقة',
    ADD COLUMN `renewal_cycle` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التجديد (دورة الالتزام)',
    ADD COLUMN `doc_text_adaptation` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الوثيقة / التكييف النصي',
    ADD COLUMN `event_adaptation` VARCHAR(255) NULL DEFAULT NULL COMMENT 'تكييف الحدث',
    ADD COLUMN `signed_on` DATE NULL DEFAULT NULL COMMENT 'توقيعها',
    ADD COLUMN `contractual_from` DATE NULL DEFAULT NULL COMMENT 'السريان التعاقدي من',
    ADD COLUMN `contractual_to` DATE NULL DEFAULT NULL COMMENT 'إلى',
    ADD COLUMN `executive_from` DATE NULL DEFAULT NULL COMMENT 'التنفيذي من ◄',
    ADD COLUMN `executive_to` DATE NULL DEFAULT NULL COMMENT 'إلى ◄',
    ADD COLUMN `doc_target` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'مستهدف الوثيقة',
    ADD COLUMN `uom_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوحدة',
    ADD COLUMN `cycles_effect` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الأثر على دورات الالتزام',
    ADD COLUMN `evidence_level` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحجية',
    ADD COLUMN `doc_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة الوثيقة',
    ADD COLUMN `amend_notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات'
SQL,
);
$n = 0;
foreach ($SQL as $s) {
    if (!$conn->query($s)) {
        $msg = $conn->error;
        if (stripos($msg, 'Duplicate column') !== false) { continue; }
        exit("⛔ {$msg}\n  في: " . substr($s, 0, 120) . "\n");
    }
    $n++;
}
echo "✔ {$n} جملةً نُفِّذت\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
