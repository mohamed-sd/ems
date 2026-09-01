<?php
/**
 * 2028_02_14_dep12_site_guide_tables.php — DEP-12 · إدارة الموقع — جداولُ مواضعِ الدليل (GOV_EXEC §5)
 * @migration-objects: tables for DEP-12
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
CREATE TABLE IF NOT EXISTS `site_dashboard_kpi` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `kpi_id` VARCHAR(60) NULL DEFAULT NULL COMMENT 'معرّف المؤشر',
    `site_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع ◄',
    `kpi_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المؤشر — KPI Catalog ◄',
    `value` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'القيمة ◄',
    `uom` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوحدة ◄',
    `state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحالة ◄',
    `updated_on` DATETIME NULL DEFAULT NULL COMMENT 'آخر تحديث ◄',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_8ac00dd1_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — لوحة الموقع · الحبة: مؤشرٌ واحدٌ لموقعٍ واحد'
SQL,
    <<<'SQL'
ALTER TABLE `sites`
    ADD COLUMN `site_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الموقع',
    ADD COLUMN `project_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'اسم المشروع ◄',
    ADD COLUMN `client_contract_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود عقد العميل ◄',
    ADD COLUMN `region` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الولاية/المنطقة',
    ADD COLUMN `coordinates` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الإحداثيات',
    ADD COLUMN `work_zones` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مناطق العمل/الجبهات',
    ADD COLUMN `shifts_count` VARCHAR(40) NULL DEFAULT NULL COMMENT 'عدد الورديات ▼',
    ADD COLUMN `equipment_capacity` VARCHAR(120) NULL DEFAULT NULL COMMENT 'الطاقة الاستيعابية للمعدات',
    ADD COLUMN `housing_facilities` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مرافق السكن ◄',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_readiness_item` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `item_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف البند',
    `site_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الموقع ◄',
    `readiness_item` VARCHAR(80) NULL DEFAULT NULL COMMENT 'بند التجهيز ▼',
    `owner_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المسؤول ◄',
    `completion_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع الإنجاز ◄',
    `result` VARCHAR(80) NULL DEFAULT NULL COMMENT 'النتيجة ▼',
    `completion_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الإنجاز ◄',
    `readiness_declaration` VARCHAR(255) NULL DEFAULT NULL COMMENT 'إعلان الجاهزية ◄',
    `item_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة البند ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_12e9c539_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — تجهيز الموقع والجاهزية · الحبة: بندُ تجهيزٍ واحدٌ على موقعٍ واحد'
SQL,
    <<<'SQL'
ALTER TABLE `site_day`
    ADD COLUMN `day_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف اليوم',
    ADD COLUMN `shift` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوردية ▼',
    ADD COLUMN `supervisor_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مشرف الوردية ◄',
    ADD COLUMN `received_distribution` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التوزيع المستلَم ◄',
    ADD COLUMN `equipment_present` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معدات حاضرة ◄',
    ADD COLUMN `equipment_absent` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معدات متخلفة',
    ADD COLUMN `operators_present` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مشغّلون حاضرون ◄',
    ADD COLUMN `operators_absent` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مشغّلون متخلفون',
    ADD COLUMN `substitutes_activated` VARCHAR(255) NULL DEFAULT NULL COMMENT 'بدلاء مفعَّلون',
    ADD COLUMN `weather_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الطقس ▼',
    ADD COLUMN `opening_note` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظة الافتتاح',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_day_unit` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف الصف',
    `day_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف يوم الموقع ◄',
    `equipment_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود المعدة ◄',
    `operator_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المشغّل ◄',
    `work_zone` VARCHAR(255) NULL DEFAULT NULL COMMENT 'منطقة العمل ◄',
    `unit_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع الوحدة ◄',
    `measured_qty` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الكمية المقيسة',
    `measure_method` VARCHAR(80) NULL DEFAULT NULL COMMENT 'وسيلة القياس ▼',
    `field_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المرجع الميداني',
    `recorded_at` DATETIME NULL DEFAULT NULL COMMENT 'وقت التسجيل ◄',
    `offline_recorded` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مسجَّل دون اتصال؟ ◄',
    `row_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الصف ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_cc4a5351_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — تسجيل وحدات اليوم · الحبة: سطرُ وحدةٍ منجزةٍ في يومِ موقعٍ'
SQL,
    <<<'SQL'
ALTER TABLE `site_day_shift`
    ADD COLUMN `shift_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف الوردية',
    ADD COLUMN `span_text` VARCHAR(255) NULL DEFAULT NULL COMMENT 'بداية ونهاية ◄',
    ADD COLUMN `permits_valid` VARCHAR(255) NULL DEFAULT NULL COMMENT 'تصاريح العمل سارية؟ ◄',
    ADD COLUMN `unsafe_conditions` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ظروف غير آمنة مرصودة ◄',
    ADD COLUMN `stop_work` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stop-Work؟ ◄',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
ALTER TABLE `ops_stop_register`
    ADD COLUMN `stop_code` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم القرار',
    ADD COLUMN `reason_tree_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'سبب التصنيف من الشجرة ◄',
    ADD COLUMN `billing_effect` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أثر الفوترة ◄',
    ADD COLUMN `contract_unit_effect` VARCHAR(80) NULL DEFAULT NULL COMMENT 'أثر الوحدة التعاقدية ◄',
    ADD COLUMN `decision_doc` VARCHAR(500) NULL DEFAULT NULL COMMENT 'مستند القرار',
    ADD COLUMN `ops_endorsement` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مصادقة التشغيل ◄',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_day_approval` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `minutes_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم المحضر',
    `day_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف يوم الموقع ◄',
    `site_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع ◄',
    `day_date_ref` DATE NULL DEFAULT NULL COMMENT 'تاريخ اليوم ◄',
    `records_count` INT NULL DEFAULT NULL COMMENT 'عدد سجلات اليوم ◄',
    `records_matched` VARCHAR(255) NULL DEFAULT NULL COMMENT 'سجلات مطابقة ◄',
    `records_modified` INT NULL DEFAULT NULL COMMENT 'سجلات معدَّلة قبل الاعتماد',
    `records_rejected` INT NULL DEFAULT NULL COMMENT 'سجلات مرفوضة',
    `reject_reason` VARCHAR(255) NULL DEFAULT NULL COMMENT 'سبب الرفض',
    `happened_declaration` VARCHAR(80) NULL DEFAULT NULL COMMENT 'إقرار «حدثت فعلًا» ▼',
    `site_manager_signature` VARCHAR(255) NULL DEFAULT NULL COMMENT 'توقيع مدير الموقع',
    `pdf_export` VARCHAR(255) NULL DEFAULT NULL COMMENT 'تصدير PDF ◄',
    `minutes_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة المحضر ▼',
    `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_4b2825fd_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — محضر اعتماد الموقع · الحبة: محضرُ اعتمادٍ واحدٌ ليومِ موقع'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_shift_handover` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `minutes_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم المحضر',
    `site_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع ◄',
    `handover_date` DATE NULL DEFAULT NULL COMMENT 'التاريخ',
    `shift_out` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوردية المُسلِّمة ▼',
    `supervisor_out` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مشرفها ◄',
    `shift_in` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوردية المستلِمة ▼',
    `supervisor_in` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مشرفها ◄',
    `equipment_running` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معدات عاملة ◄',
    `equipment_stopped` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'معدات متوقفة ◄',
    `custody_handed` VARCHAR(255) NULL DEFAULT NULL COMMENT 'عُهد مسلَّمة',
    `open_notes_carried` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظات مفتوحة مرحَّلة',
    `meter_readings` VARCHAR(255) NULL DEFAULT NULL COMMENT 'قراءات عدّادات عند التسليم',
    `signature_out` VARCHAR(255) NULL DEFAULT NULL COMMENT 'توقيع المُسلِّم',
    `signature_in` VARCHAR(255) NULL DEFAULT NULL COMMENT 'توقيع المستلِم',
    `minutes_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة المحضر ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_7aca8a56_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — محضر تسليم واستلام الورديات · الحبة: محضرُ تسليمٍ واحدٌ بين وردِيَّتَين'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_state_change_request` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `request_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الطلب',
    `site_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع ◄',
    `request_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الطلب',
    `request_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع الطلب ▼',
    `subject_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'العنصر المعني ◄',
    `current_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحالة الحالية ◄',
    `target_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الحالة المطلوبة ▼',
    `reason` VARCHAR(255) NULL DEFAULT NULL COMMENT 'السبب',
    `priority` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الأولوية ▼',
    `evidence_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المرفق/الدليل',
    `decision_owner` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الجهة المالكة للقرار ◄',
    `ticket_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم البلاغ/الأمر المتفرع ◄',
    `request_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الطلب ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_bb6275a9_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — طلبات تغيير الحالة ومعالجة المتعثر · الحبة: طلبُ تغييرِ حالةٍ واحدٌ على عنصرٍ واحد'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_request_item` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `item_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف البند',
    `request_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الطلب ◄',
    `item_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الصنف ◄',
    `requested_qty` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الكمية المطلوبة',
    `uom` VARCHAR(80) NULL DEFAULT NULL COMMENT 'وحدة القياس ◄',
    `received_cumulative` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المستلَم تراكميًّا ◄',
    `remaining` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المتبقي ◄',
    `item_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة البند ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_0d8dda7f_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — بنود طلب الموقع · الحبة: بندُ صنفٍ واحدٌ في طلبِ موقع'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_supply_request` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `request_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الطلب',
    `site_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع ◄',
    `request_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الطلب',
    `request_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع الطلب ▼',
    `warehouse_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المخزن المستهدف ◄',
    `items_text` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الأصناف المطلوبة',
    `quantities` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكميات',
    `justification` VARCHAR(500) NULL DEFAULT NULL COMMENT 'مبرر الطلب',
    `custody_receiver` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مستلِم العهدة ◄',
    `issue_voucher_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم سند الصرف ◄',
    `actual_receipt_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الاستلام الفعلي',
    `receipt_match` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مطابقة الاستلام ▼',
    `request_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الطلب ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_bd16f28b_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — طلبات الموقع للصرف والاستلام · الحبة: طلبُ صرفٍ واحدٌ من موقعٍ إلى مخزن'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_request_batch` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `batch_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف الدفعة',
    `request_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الطلب ◄',
    `batch_seq` INT NULL DEFAULT NULL COMMENT 'تسلسل الدفعة',
    `issue_voucher_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم سند الصرف ◄',
    `received_items` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الأصناف المستلمة ◄',
    `receipt_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الاستلام',
    `custody_receiver` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مستلِم العهدة ◄',
    `batch_match` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مطابقة الدفعة ▼',
    `batch_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الدفعة ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_cfec3ac1_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — دفعات استلام طلب الموقع · الحبة: دفعةُ استلامٍ واحدةٌ على طلبِ موقع'
SQL,
    <<<'SQL'
ALTER TABLE `tre_petty_expense`
    ADD COLUMN `expense_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الصرف',
    ADD COLUMN `site_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الموقع ◄',
    ADD COLUMN `expense_item` VARCHAR(80) NULL DEFAULT NULL COMMENT 'بند الصرف ▼',
    ADD COLUMN `currency_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'العملة ◄',
    ADD COLUMN `field_justification` VARCHAR(500) NULL DEFAULT NULL COMMENT 'مبرر الصرف الميداني',
    ADD COLUMN `local_supplier` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المورد المحلي',
    ADD COLUMN `charged_to` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التحميل على ◄',
    ADD COLUMN `over_limit` VARCHAR(255) NULL DEFAULT NULL COMMENT 'تجاوز الحد؟ ◄',
    ADD COLUMN `override_approval_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع اعتماد التجاوز ◄',
    ADD COLUMN `treasury_settlement_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع التسوية بالخزينة ◄',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_day_close_report` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `report_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف التقرير',
    `site_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع ◄',
    `report_date` DATE NULL DEFAULT NULL COMMENT 'التاريخ ◄',
    `attendance_summary` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ملخص الحضور ◄',
    `equipment_worked` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معدات عملت ◄',
    `units_approved` VARCHAR(255) NULL DEFAULT NULL COMMENT 'وحدات معتمدة ◄',
    `actual_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'ساعات فعلي ◄',
    `stop_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'ساعات توقف ◄',
    `hse_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة HSE لليوم ▼',
    `incidents` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حوادث وظروف غير آمنة ◄',
    `stop_work_events` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Stop-Work خلال اليوم ◄',
    `permits_validity` VARCHAR(255) NULL DEFAULT NULL COMMENT 'سريان تصاريح العمل ◄',
    `day_tickets` VARCHAR(255) NULL DEFAULT NULL COMMENT 'بلاغات اليوم ◄',
    `day_minutes` VARCHAR(255) NULL DEFAULT NULL COMMENT 'محاضر اليوم ◄',
    `signed_pdf_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'رابط PDF الموقَّع ◄',
    `day_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة اليوم ◄',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_923bf5c7_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — تقرير إقفال يوم الموقع · الحبة: تقريرُ إقفالٍ واحدٌ ليومِ موقع'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_suspension` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `decision_no` VARCHAR(500) NULL DEFAULT NULL COMMENT 'رقم القرار',
    `site_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الموقع ◄',
    `suspension_reason` VARCHAR(80) NULL DEFAULT NULL COMMENT 'سبب الإيقاف ▼',
    `from_date` DATE NULL DEFAULT NULL COMMENT 'من تاريخ',
    `expected_duration` VARCHAR(80) NULL DEFAULT NULL COMMENT 'المدة المتوقعة',
    `resource_effect` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أثر الموارد ◄',
    `contract_effect` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أثر العقد ◄',
    `parties_notified` VARCHAR(255) NULL DEFAULT NULL COMMENT 'إخطار الأطراف ◄',
    `resumption_minutes` VARCHAR(255) NULL DEFAULT NULL COMMENT 'محضر الاستئناف ◄',
    `decision_state` VARCHAR(500) NULL DEFAULT NULL COMMENT 'حالة القرار ▼',
    `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_2694f287_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — الإيقاف المؤقت للموقع · الحبة: قرارُ إيقافٍ مؤقّتٍ واحدٌ لموقع'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_closure_item` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `item_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف البند',
    `site_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الموقع ◄',
    `closure_item` VARCHAR(80) NULL DEFAULT NULL COMMENT 'بند الإغلاق ▼',
    `owner_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المسؤول ◄',
    `completion_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع الإنجاز ◄',
    `result` VARCHAR(80) NULL DEFAULT NULL COMMENT 'النتيجة ▼',
    `final_handover_minutes` VARCHAR(255) NULL DEFAULT NULL COMMENT 'محضر التسليم النهائي ◄',
    `item_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة البند ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_1a1446fe_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — إغلاق الموقع وتسريحه · الحبة: بندُ إغلاقٍ واحدٌ على موقعٍ واحد'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `site_reference_registry` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف السطر',
    `reference_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع المرجع ▼',
    `reference_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المرجع ◄',
    `reference_owner` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مالكه ◄',
    `last_updated_on` DATETIME NULL DEFAULT NULL COMMENT 'آخر تحديث ◄',
    `access_scope` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نطاق الاطّلاع ◄',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_36a75df4_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-12 — سجلات الموقع المرجعية · الحبة: سطرُ مرجعٍ واحدٌ يخدم الموقع'
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
