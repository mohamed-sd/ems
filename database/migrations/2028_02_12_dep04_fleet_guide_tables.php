<?php
/**
 * 2028_02_12_dep04_fleet_guide_tables.php — DEP-04 · إدارة الأسطول والأصول — جداولُ مواضعِ الدليل (GOV_EXEC §5)
 * @migration-objects: tables for DEP-04
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
CREATE TABLE IF NOT EXISTS `flt_power_source` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `source_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود المصدر',
    `source_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'اسم المصدر',
    `definition` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التعريف',
    `is_capitalized` VARCHAR(80) NULL DEFAULT NULL COMMENT 'يُرسمل؟ ▼',
    `has_depreciation` VARCHAR(80) NULL DEFAULT NULL COMMENT 'يُحتسب له إهلاك؟ ▼',
    `in_fleet_register` VARCHAR(80) NULL DEFAULT NULL COMMENT 'يدخل سجل الأسطول؟ ▼',
    `governing_doc` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المستند الحاكم',
    `accounting_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المرجع المحاسبي',
    `financial_owner` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مالك الحقيقة المالية',
    `operational_owner` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مالك الحقيقة التشغيلية',
    `current_count` INT NULL DEFAULT NULL COMMENT 'العدد الحالي ◄',
    `count_uom` VARCHAR(80) NULL DEFAULT NULL COMMENT 'وحدة العدد',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_18b7e8e9_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — مصادر القدرة · الحبة: مصدرُ قدرةٍ واحدٌ — سطرٌ لكلِّ مصدر'
SQL,
    <<<'SQL'
ALTER TABLE `equipments_types`
    ADD COLUMN `class_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رمز التصنيف',
    ADD COLUMN `type_en` VARCHAR(255) NULL DEFAULT NULL COMMENT 'النوع بالإنجليزية',
    ADD COLUMN `main_category` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الفئة الرئيسية',
    ADD COLUMN `sub_category` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الفئة الفرعية',
    ADD COLUMN `policy_note` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظة السياسة',
    ADD COLUMN `ifrs_class` VARCHAR(255) NULL DEFAULT NULL COMMENT 'فئة IFRS · PP&E Class'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_inspection_card` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `card_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم البطاقة',
    `order_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم أمر التفتيش',
    `asset_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الأصل',
    `inspection_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع التفتيش ◄',
    `inspector` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المفتش',
    `executor_party` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الجهة المنفِّذة ◄',
    `start_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ بدء التفتيش',
    `start_time` TIME NULL DEFAULT NULL COMMENT 'وقت البدء',
    `end_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الانتهاء',
    `end_time` TIME NULL DEFAULT NULL COMMENT 'وقت الانتهاء',
    `location` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع',
    `project_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المشروع ◄',
    `meter_reading` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'قراءة العداد',
    `last_meter` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'العداد بآخر تفتيش ◄',
    `hours_since_last` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'الساعات منذ آخر تفتيش ◄',
    `engine_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة المحرك ▼',
    `hydraulic_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'النظام الهيدروليكي ▼',
    `transmission_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'ناقل الحركة ▼',
    `electrical_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'النظام الكهربائي ▼',
    `brakes_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الفرامل ▼',
    `tires_tracks_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الإطارات أو الجنازير ▼',
    `body_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الجسم والهيكل ▼',
    `cabin_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الكبينة ▼',
    `safety_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'معدات السلامة ▼',
    `leaks_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'التسريبات ▼',
    `type_specific_items` VARCHAR(255) NULL DEFAULT NULL COMMENT 'عناصر خاصة بنوع المعدة',
    `change_vs_last` VARCHAR(80) NULL DEFAULT NULL COMMENT 'التغير عن آخر تفتيش ▼',
    `change_desc` VARCHAR(500) NULL DEFAULT NULL COMMENT 'وصف التغير',
    `operator_at_event` TIME NULL DEFAULT NULL COMMENT 'المشغل وقت الواقعة',
    `operator_liability` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة مسؤولية المشغل ▼',
    `investigation_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع التحقيق',
    `inspection_result` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نتيجة التفتيش ▼',
    `technical_notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الملاحظات الفنية',
    `resulting_action` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الإجراء الناتج ▼',
    `work_order_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم أمر الصيانة',
    `breakdown_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع سجل العطل',
    `technical_state_effect` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أثر على الحالة الفنية ◄',
    `report_attachment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مرفق تقرير التفتيش',
    `photos_attachment` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مرفق الصور',
    `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    `review_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ المراجعة',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    `record_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس السجل ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_2569d639_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — بطاقة التفتيش · الحبة: بطاقةُ تفتيشٍ واحدةٌ — أصلٌ × أمرُ تفتيش'
SQL,
    <<<'SQL'
ALTER TABLE `equipments`
    ADD COLUMN `class_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رمز التصنيف ◄',
    ADD COLUMN `old_code_main` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكود القديم الرئيسي',
    ADD COLUMN `old_code_alt` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكود القديم الثانوي',
    ADD COLUMN `project_asset_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود المعدة بالمشروع',
    ADD COLUMN `power_source_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود مصدر القدرة',
    ADD COLUMN `purchase_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الشراء ▼',
    ADD COLUMN `supplier_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود المورد',
    ADD COLUMN `supplier_contract_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع عقد المورد',
    ADD COLUMN `finance_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المالي',
    ADD COLUMN `ifrs_class` VARCHAR(255) NULL DEFAULT NULL COMMENT 'فئة IFRS ◄',
    ADD COLUMN `chassis_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة الشاسيه ◄',
    ADD COLUMN `sticker_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الملصق',
    ADD COLUMN `parent_asset_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الأصل الرئيسي',
    ADD COLUMN `link_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع الارتباط ▼',
    ADD COLUMN `purchase_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الشراء',
    ADD COLUMN `receipt_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الاستلام',
    ADD COLUMN `entry_basis` DATE NULL DEFAULT NULL COMMENT 'أساس تاريخ الدخول ▼',
    ADD COLUMN `first_operation_site` VARCHAR(255) NULL DEFAULT NULL COMMENT 'موقع أول تشغيل',
    ADD COLUMN `technical_class` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التصنيف الفني ◄',
    ADD COLUMN `current_project` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المشروع الحالي ◄',
    ADD COLUMN `operating_party` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الجهة المشغلة ◄',
    ADD COLUMN `current_unit_key` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مفتاح الوحدة الحالي ◄',
    ADD COLUMN `last_meter_reading` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'آخر قراءة عداد ◄',
    ADD COLUMN `last_meter_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ آخر قراءة ◄',
    ADD COLUMN `capacity_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'الطاقة الإنتاجية — مرجع ◄',
    ADD COLUMN `exit_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الخروج من الخدمة',
    ADD COLUMN `exit_reason` VARCHAR(80) NULL DEFAULT NULL COMMENT 'سبب الخروج ▼',
    ADD COLUMN `exit_record_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع سجل الخروج',
    ADD COLUMN `evidence_level` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مستوى الحجية ▼',
    ADD COLUMN `file_completeness_pct` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'نسبة اكتمال الملف ◄',
    ADD COLUMN `missing_links` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الحلقات المفقودة ◄',
    ADD COLUMN `verification_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة التحقق ▼',
    ADD COLUMN `exception_flag` VARCHAR(255) NULL DEFAULT NULL COMMENT 'علامة استثناء ◄',
    ADD COLUMN `confidence_grade` VARCHAR(80) NULL DEFAULT NULL COMMENT 'درجة الثقة ▼',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `record_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس السجل ▼',
    ADD COLUMN `card_src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `card_data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼'
SQL,
    <<<'SQL'
ALTER TABLE `equipment_documents`
    ADD COLUMN `is_mandatory` VARCHAR(80) NULL DEFAULT NULL COMMENT 'إلزامي؟ ▼',
    ADD COLUMN `days_to_expiry` INT NULL DEFAULT NULL COMMENT 'أيام حتى الانتهاء ◄',
    ADD COLUMN `coverage_value` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'قيمة التغطية',
    ADD COLUMN `bearer_party` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الجهة المتحمِّلة ▼',
    ADD COLUMN `storage_place` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مكان الحفظ',
    ADD COLUMN `renewal_owner` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مسؤول التجديد',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    ADD COLUMN `record_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس السجل ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼'
SQL,
    <<<'SQL'
ALTER TABLE `fleet_equipment_component`
    ADD COLUMN `component_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود المكوّن',
    ADD COLUMN `description` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الوصف',
    ADD COLUMN `manufacturer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الشركة المصنعة',
    ADD COLUMN `meter_at_install` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'العداد عند التركيب',
    ADD COLUMN `expected_life_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'العمر المتوقع (ساعة)',
    ADD COLUMN `current_meter` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'العداد الحالي ◄',
    ADD COLUMN `consumption_pct` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'نسبة الاستهلاك ◄',
    ADD COLUMN `removal_reason` VARCHAR(80) NULL DEFAULT NULL COMMENT 'سبب الفك ▼',
    ADD COLUMN `moved_to_asset` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نُقل إلى أصل',
    ADD COLUMN `work_order_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع أمر الصيانة',
    ADD COLUMN `is_capitalized` VARCHAR(80) NULL DEFAULT NULL COMMENT 'يُرسمل؟ ▼',
    ADD COLUMN `capitalization_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع قرار الرسملة',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    ADD COLUMN `record_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس السجل ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_daily_operating_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `log_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود السجل',
    `asset_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الأصل',
    `log_date` DATE NULL DEFAULT NULL COMMENT 'التاريخ',
    `shift` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوردية ▼',
    `operator_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم المشغل',
    `unit_key` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مفتاح الوحدة ◄',
    `project_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المشروع ◄',
    `site_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع ◄',
    `meter_start` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'العداد أول الوردية',
    `meter_end` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'العداد آخر الوردية',
    `meter_diff` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'فرق العداد ◄',
    `work_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'ساعات العمل الفعلي',
    `standby_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'ساعات الاستعداد',
    `stop_maintenance` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'توقف صيانة',
    `stop_operational` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'توقف تشغيلي',
    `stop_no_operator` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'توقف عدم توفر مشغل',
    `stop_site_client` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'توقف الموقع أو العميل',
    `stop_fuel` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'توقف الوقود',
    `stop_transfer` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'توقف الترحيل',
    `stop_parts_wait` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'توقف انتظار قطع',
    `stop_other` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'توقف أخرى',
    `stop_total` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'إجمالي التوقف ◄',
    `day_total_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'إجمالي ساعات اليوم ◄',
    `tons_moved` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'الأطنان المنقولة',
    `meters_done` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'الأمتار المنجزة',
    `fuel_issued` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'الوقود المصروف',
    `breakdown_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع العطل',
    `data_entry_by` INT NULL DEFAULT NULL COMMENT 'مدخل البيانات',
    `confidence_grade` VARCHAR(80) NULL DEFAULT NULL COMMENT 'درجة الثقة ▼',
    `approval_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الاعتماد ▼',
    `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    `review_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ المراجعة',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    `record_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس السجل ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_19201a85_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — السجل اليومي للتشغيل · الحبة: أصلٌ × يومٌ × وردية — سطرٌ واحد'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_technical_state` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `asset_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الأصل',
    `technical_class` VARCHAR(80) NULL DEFAULT NULL COMMENT 'التصنيف الفني ▼',
    `last_assessment_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ آخر تقييم',
    `last_inspection_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع آخر تفتيش ◄',
    `operating_age_years` INT NULL DEFAULT NULL COMMENT 'العمر التشغيلي (سنة) ◄',
    `accumulated_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'الساعات المتراكمة ◄',
    `capacity_consumption_pct` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'نسبة استهلاك الطاقة ◄',
    `failures_12m` INT NULL DEFAULT NULL COMMENT 'عدد الأعطال آخر ١٢ شهرًا ◄',
    `downtime_hours_12m` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'ساعات التوقف آخر ١٢ شهرًا ◄',
    `readiness_pct` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'نسبة الجاهزية ◄',
    `maintenance_cost_total` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'تكلفة الصيانة التراكمية ◄',
    `cost_to_value_pct` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'نسبة التكلفة إلى القيمة ◄',
    `class_reason` VARCHAR(80) NULL DEFAULT NULL COMMENT 'سبب التصنيف',
    `required_action` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الإجراء المطلوب',
    `replacement_candidate` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرشحة للاستبدال؟ ▼',
    `candidacy_reason` VARCHAR(500) NULL DEFAULT NULL COMMENT 'مبرر الترشيح',
    `management_decision` VARCHAR(80) NULL DEFAULT NULL COMMENT 'قرار الإدارة ▼',
    `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    `record_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس السجل ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_6e85637b_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — الحالة الفنية · الحبة: أصلٌ واحدٌ — حالتُه الفنيةُ الجاريةُ سطرٌ واحد'
SQL,
    <<<'SQL'
ALTER TABLE `fleet_equipment_history`
    ADD COLUMN `change_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم التغيّر',
    ADD COLUMN `change_seq` INT NULL DEFAULT NULL COMMENT 'تسلسل التغيّر',
    ADD COLUMN `change_time` TIME NULL DEFAULT NULL COMMENT 'وقت التغيّر',
    ADD COLUMN `change_reason` VARCHAR(80) NULL DEFAULT NULL COMMENT 'سبب التغيّر ▼',
    ADD COLUMN `cause_event` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الواقعة المسبِّبة',
    ADD COLUMN `prev_state_days` INT NULL DEFAULT NULL COMMENT 'مدة الحالة السابقة (يوم) ◄',
    ADD COLUMN `meter_reading` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'قراءة العداد',
    ADD COLUMN `changed_by_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'منفّذ التغيير',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    ADD COLUMN `record_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس السجل ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼'
SQL,
    <<<'SQL'
ALTER TABLE `mnt_breakdown`
    ADD COLUMN `failure_start_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ بداية العطل',
    ADD COLUMN `failure_start_time` TIME NULL DEFAULT NULL COMMENT 'وقت البداية',
    ADD COLUMN `discovery_source` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مصدر الاكتشاف ▼',
    ADD COLUMN `report_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع البلاغ',
    ADD COLUMN `operator_at_failure` TIME NULL DEFAULT NULL COMMENT 'المشغل وقت العطل',
    ADD COLUMN `site_location` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع',
    ADD COLUMN `unit_key` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مفتاح الوحدة ◄',
    ADD COLUMN `meter_reading` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'قراءة العداد',
    ADD COLUMN `downtime_start` DATETIME NULL DEFAULT NULL COMMENT 'بداية التوقف',
    ADD COLUMN `referred_to_maintenance` VARCHAR(80) NULL DEFAULT NULL COMMENT 'أُحيل للصيانة ▼',
    ADD COLUMN `referral_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الإحالة',
    ADD COLUMN `diagnosis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التشخيص ◄',
    ADD COLUMN `root_cause` VARCHAR(255) NULL DEFAULT NULL COMMENT 'السبب الجذري ◄',
    ADD COLUMN `return_service_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ العودة للخدمة',
    ADD COLUMN `return_service_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع إعادة الخدمة',
    ADD COLUMN `downtime_end` DATETIME NULL DEFAULT NULL COMMENT 'نهاية التوقف',
    ADD COLUMN `downtime_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'مدة التوقف (ساعة) ◄',
    ADD COLUMN `operator_liability` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة مسؤولية المشغل ▼',
    ADD COLUMN `investigation_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع التحقيق',
    ADD COLUMN `bearer_party` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الطرف المتحمِّل ▼',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `review_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ المراجعة',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    ADD COLUMN `record_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس السجل ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_exception_register` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `exception_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الاستثناء',
    `exception_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع الاستثناء ▼',
    `description` VARCHAR(500) NULL DEFAULT NULL COMMENT 'وصف الاستثناء',
    `affected_assets` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الأصول المتأثرة والقياس',
    `severity` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الخطورة ▼',
    `recommended_action` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الإجراء الموصى',
    `owner_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المسؤول',
    `accounting_legal_impact` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الأثر المحاسبي أو القانوني',
    `state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الحالة ▼',
    `due_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الاستحقاق',
    `closed_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الإغلاق',
    `resolution` VARCHAR(500) NULL DEFAULT NULL COMMENT 'كيف حُسم',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_e5bcd6bd_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — سجل الاستثناءات · الحبة: استثناءٌ واحدٌ بأصولِه المتأثرة'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_external_auditor_note` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `item_no` VARCHAR(255) NULL DEFAULT NULL COMMENT 'م',
    `severity` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الخطورة',
    `category` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الفئة',
    `item_or_asset` VARCHAR(255) NULL DEFAULT NULL COMMENT 'البند أو الأصل',
    `problem_desc` VARCHAR(500) NULL DEFAULT NULL COMMENT 'وصف المشكلة',
    `required_action` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الإجراء المطلوب',
    `proposed_owner` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المسؤول المقترح',
    `target_date` DATE NULL DEFAULT NULL COMMENT 'التاريخ المستهدف',
    `accounting_impact` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الأثر المحاسبي',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_10263d04_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — ملاحظات المراجع الخارجي · الحبة: ملاحظةُ مراجعٍ خارجيٍّ واحدةٌ'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_source_conflict` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `asset_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود العين',
    `conflict_point` VARCHAR(255) NULL DEFAULT NULL COMMENT 'موضع التضارب',
    `source_one` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المصدر الأول',
    `value_one` VARCHAR(255) NULL DEFAULT NULL COMMENT 'قيمته',
    `source_two` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المصدر الثاني',
    `value_two` VARCHAR(255) NULL DEFAULT NULL COMMENT 'قيمته',
    `difference` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الفرق',
    `preference` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الترجيح',
    `required_fix` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التصحيح المطلوب',
    `severity` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الخطورة',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_6d401030_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — التضاربات بين المصادر · الحبة: تضاربٌ واحدٌ بين مصدرَين على عينٍ واحدة'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_open_point` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `point_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم النقطة',
    `source` VARCHAR(80) NULL DEFAULT NULL COMMENT 'المصدر ▼',
    `sheet_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الشيت',
    `row_no` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الصف',
    `asset_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود العين أو الأصل',
    `operation_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود العملية',
    `financier` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الممول',
    `finance_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع التمويل',
    `affected_field` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحقل المتأثر',
    `current_value` VARCHAR(255) NULL DEFAULT NULL COMMENT 'القيمة الحالية',
    `problem_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع المشكلة ▼',
    `note_text` VARCHAR(500) NULL DEFAULT NULL COMMENT 'نص الملاحظة',
    `severity` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الخطورة ▼',
    `responsible_party` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الجهة المسؤولة',
    `required_action` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الإجراء المطلوب',
    `state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الحالة ▼',
    `approved_decision` VARCHAR(500) NULL DEFAULT NULL COMMENT 'القرار المعتمد',
    `document_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المستند',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `resolved_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الحسم',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_053d27e8_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — النقاط غير المحسومة · الحبة: نقطةٌ واحدةٌ غيرُ محسومةٍ بمصدرِها وصفِّها'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_management_decision` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `decision_no` VARCHAR(500) NULL DEFAULT NULL COMMENT 'رقم القرار',
    `requested_decision` VARCHAR(500) NULL DEFAULT NULL COMMENT 'القرار المطلوب',
    `evidence_current_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الدليل والوضع الحالي',
    `option_a` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الخيار (أ) وأثره',
    `option_b` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الخيار (ب) وأثره',
    `technical_recommendation` VARCHAR(500) NULL DEFAULT NULL COMMENT 'التوصية الفنية',
    `owner_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المسؤول',
    `deadline` DATE NULL DEFAULT NULL COMMENT 'المهلة',
    `approved_decision` VARCHAR(500) NULL DEFAULT NULL COMMENT 'القرار المعتمد',
    `decision_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ القرار',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_46562155_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — حزمة القرارات الإدارية · الحبة: قرارٌ إداريٌّ واحدٌ بخيارَيه'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_dashboard_kpi` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `indicator` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المؤشر',
    `value` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'القيمة ◄',
    `uom` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوحدة',
    `source_sheet` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الشيت المصدر',
    `acceptable_limit` VARCHAR(120) NULL DEFAULT NULL COMMENT 'الحد المقبول',
    `state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحالة ◄',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_807118b7_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — لوحة الأسطول · الحبة: مؤشرٌ واحدٌ في لوحةِ الأسطول'
SQL,
    <<<'SQL'
ALTER TABLE `fleet_depreciation_profile`
    ADD COLUMN `rate_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس المعدل',
    ADD COLUMN `standard_source` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المصدر المعياري'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_use_right_range` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `ownership_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة الملكية',
    `range_from` VARCHAR(80) NULL DEFAULT NULL COMMENT 'من',
    `range_to` VARCHAR(80) NULL DEFAULT NULL COMMENT 'إلى',
    `explanation` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الشرح',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_0e6a626f_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — نطاقات حق الاستخدام · الحبة: نطاقُ ترقيمٍ واحدٌ لحالةِ ملكية'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_code_reconciliation` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `unified_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكود الموحد الجديد',
    `old_code_main` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكود القديم (رئيسي)',
    `old_code_alt` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكود القديم (ثانوي)',
    `asset_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'اسم الأصل',
    `source_system` VARCHAR(255) NULL DEFAULT NULL COMMENT 'النظام المصدر',
    `match_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة المطابقة',
    `match_notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات المطابقة',
    `matched_by_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'طُوبِق بواسطة',
    `match_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ المطابقة',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_d9a3504c_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — مصالحة مطابقة الأكواد · الحبة: كودٌ موحَّدٌ واحدٌ مقابلَ أكوادِه القديمة'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_numbering_bridge` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `plate_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم اللوحة',
    `old_codes` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الأكواد القديمة',
    `old_codes_count` INT NULL DEFAULT NULL COMMENT 'عددها',
    `current_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكود الحالي المشغَّل',
    `current_codes_count` INT NULL DEFAULT NULL COMMENT 'عدد الأكواد الحالية',
    `equipment_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع المعدة',
    `owner_supplier` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المورد المالك',
    `hours_done` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'الساعات المنجزة',
    `match_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة المطابقة',
    `approved_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكود المعتمد',
    `unification_decision` VARCHAR(255) NULL DEFAULT NULL COMMENT 'قرار التوحيد',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_ba94d245_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — مصالحة جسر الترقيم · الحبة: لوحةٌ واحدةٌ بأكوادِها القديمةِ والحالية'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_owner_reconciliation` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `equipment_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود المعدة',
    `equipment_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع المعدة',
    `plate_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم اللوحة',
    `supplier_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم المورد',
    `supplier_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'اسم المورد',
    `supplier_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع المورد',
    `supplier_nature` VARCHAR(255) NULL DEFAULT NULL COMMENT 'طبيعة المورد',
    `real_owner_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'اسم المالك الحقيقي',
    `owner_phone` VARCHAR(255) NULL DEFAULT NULL COMMENT 'هاتف المالك',
    `old_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكود القديم',
    `first_operation` DATE NULL DEFAULT NULL COMMENT 'أول تشغيل',
    `last_operation` DATE NULL DEFAULT NULL COMMENT 'آخر تشغيل',
    `months_count` INT NULL DEFAULT NULL COMMENT 'عدد الأشهر',
    `hours_done` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'الساعات المنجزة',
    `contracts_count` INT NULL DEFAULT NULL COMMENT 'عدد العقود',
    `commitment_cycles` INT NULL DEFAULT NULL COMMENT 'عدد دورات الالتزام',
    `supplier_assets_registered` VARCHAR(255) NULL DEFAULT NULL COMMENT 'آليات المورد بالسجل',
    `chassis_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الشاسيه',
    `service_entry_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ دخول الخدمة',
    `notes` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_d27c5de2_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — مصالحة الأسطول بالملاك · الحبة: معدّةٌ واحدةٌ مقابلَ مالكِها ومورِّدِها'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_register_operation_recon` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `asset_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الأصل',
    `unified_desc` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الوصف الموحد',
    `class_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رمز التصنيف',
    `ownership_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع الملكية',
    `operating_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحالة التشغيلية',
    `register_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'الساعات المتراكمة بالسجل',
    `intersection_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة التقاطع',
    `timesheet_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'ساعات التايم شيت',
    `timesheet_supplier` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المورد بالتايم شيت',
    `first_operation` DATE NULL DEFAULT NULL COMMENT 'أول تشغيل',
    `last_operation` DATE NULL DEFAULT NULL COMMENT 'آخر تشغيل',
    `commitment_cycles` INT NULL DEFAULT NULL COMMENT 'عدد دورات الالتزام',
    `hours_diff` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'فرق الساعات',
    `diff_explanation` VARCHAR(500) NULL DEFAULT NULL COMMENT 'تفسير الفرق',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_3407858e_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — مصالحة السجل بالتشغيل · الحبة: أصلٌ واحدٌ — ساعاتُ سجلِّه مقابلَ ساعاتِ تشغيلِه'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `flt_financed_asset_recon` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `finance_asset_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود العين بالتمويل',
    `fleet_matched_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكود المطابق بالأسطول',
    `classification` VARCHAR(80) NULL DEFAULT NULL COMMENT 'التصنيف',
    `asset_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'النوع',
    `finance_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع التمويل',
    `owner_financier` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المالك / الممول',
    `match_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة المطابقة',
    `approved_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكود المعتمد',
    `coding_decision` VARCHAR(255) NULL DEFAULT NULL COMMENT 'قرار التكويد',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_aae56584_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-04 — مصالحة الأعيان الممولة · الحبة: عينٌ ممولةٌ واحدةٌ مقابلَ كودِها بالأسطول'
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
