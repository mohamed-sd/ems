<?php
/**
 * 2028_02_15_dep13_workforce_guide_tables.php — DEP-13 · إدارة القوى التشغيلية — جداولُ مواضعِ الدليل (GOV_EXEC §5)
 * @migration-objects: tables for DEP-13
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
CREATE TABLE IF NOT EXISTS `wf_dashboard_kpi` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `kpi_id` VARCHAR(60) NULL DEFAULT NULL COMMENT 'معرّف المؤشر',
    `kpi_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المؤشر ◄',
    `category` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الفئة ◄',
    `value` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'القيمة ◄',
    `uom` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوحدة ◄',
    `state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحالة ◄',
    `updated_on` DATETIME NULL DEFAULT NULL COMMENT 'آخر تحديث ◄',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_2d391cea_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-13 — لوحة القوى التشغيلية · الحبة: مؤشرٌ واحدٌ من مؤشراتِ القوى'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `wf_nomination` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `nomination_no` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف الترشيح',
    `requirement_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف الاحتياج ◄',
    `person_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفرد ◄',
    `category_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الفئة ◄',
    `nomination_source` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مصدر الترشيح ▼',
    `prequal_check` VARCHAR(255) NULL DEFAULT NULL COMMENT 'فحص التأهيل المبدئي ◄',
    `license_check` VARCHAR(255) NULL DEFAULT NULL COMMENT 'فحص الرخصة ◄',
    `interview_result` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نتيجة المقابلة/الاختبار',
    `decision` VARCHAR(500) NULL DEFAULT NULL COMMENT 'القرار ▼',
    `contract_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع التعاقد ◄',
    `nomination_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الترشيح ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_abc68a16_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-13 — الترشيح والاختيار للتغطية · الحبة: ترشيحُ فردٍ واحدٍ على احتياجٍ واحد'
SQL,
    <<<'SQL'
ALTER TABLE `worker_contract`
    ADD COLUMN `row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف السطر ◄',
    ADD COLUMN `person_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفرد ◄',
    ADD COLUMN `project_contract_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع عقد المشروع ب05 ◄',
    ADD COLUMN `project_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المشروع ◄',
    ADD COLUMN `valid_from` DATE NULL DEFAULT NULL COMMENT 'من ◄',
    ADD COLUMN `valid_to` DATE NULL DEFAULT NULL COMMENT 'إلى ◄',
    ADD COLUMN `end_trigger` VARCHAR(255) NULL DEFAULT NULL COMMENT 'محفّز الانتهاء ◄',
    ADD COLUMN `contract_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة العقد ◄'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `wf_category` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `category_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفئة',
    `category_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'اسم الفئة',
    `job_family` VARCHAR(255) NULL DEFAULT NULL COMMENT 'العائلة الوظيفية ◄',
    `org_structure_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع الهيكل النافذ ◄',
    `equipment_applies` VARCHAR(80) NULL DEFAULT NULL COMMENT 'تنطبق عليها معدة؟ ▼',
    `matrix_requirements` VARCHAR(255) NULL DEFAULT NULL COMMENT 'متطلبات المصفوفة المرجعية ◄',
    `title_examples` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أمثلة مسميات',
    `category_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الفئة ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_e2e7ea84_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-13 — تصنيف الفئات التشغيلية · الحبة: فئةٌ تشغيليةٌ واحدة'
SQL,
    <<<'SQL'
ALTER TABLE `equipment_operators`
    ADD COLUMN `person_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفرد',
    ADD COLUMN `person_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الاسم ◄',
    ADD COLUMN `wf_category` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الفئة التشغيلية ▼',
    ADD COLUMN `affiliation` VARCHAR(80) NULL DEFAULT NULL COMMENT 'التبعية ▼',
    ADD COLUMN `distribution_track` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مسار التوزيع ▼',
    ADD COLUMN `qualified_equipment_types` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أنواع المعدات المؤهَّل عليها ◄',
    ADD COLUMN `current_site` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع الحالي ◄',
    ADD COLUMN `current_allocation` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التخصيص الحالي ◄',
    ADD COLUMN `rotation_pattern` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نمط التناوب ◄',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
ALTER TABLE `worker_qualification`
    ADD COLUMN `qual_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف التأهيل',
    ADD COLUMN `certificate_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الشهادة',
    ADD COLUMN `practical_test_result` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نتيجة الاختبار العملي',
    ADD COLUMN `qual_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة التأهيل ◄',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `wf_qualification_matrix` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `rule_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف القاعدة',
    `type_or_category` VARCHAR(255) NULL DEFAULT NULL COMMENT 'النوع/الفئة',
    `min_proficiency` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مستوى التأهيل الأدنى ▼',
    `required_license` VARCHAR(80) NULL DEFAULT NULL COMMENT 'فئة الرخصة المطلوبة ▼',
    `medical_required` VARCHAR(80) NULL DEFAULT NULL COMMENT 'فحص طبي مطلوب؟ ▼',
    `recert_period` VARCHAR(80) NULL DEFAULT NULL COMMENT 'دورية إعادة الاعتماد',
    `supervised_exception` VARCHAR(80) NULL DEFAULT NULL COMMENT 'استثناء بإشراف؟ ▼',
    `rule_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة القاعدة ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_c408c457_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-13 — مصفوفة التأهيل والجاهزية · الحبة: قاعدةُ تأهيلٍ واحدةٌ لنوعٍ أو فئة'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `wf_project_allocation` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `allocation_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم التخصيص',
    `requirement_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف الاحتياج المغطَّى ◄',
    `person_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفرد ◄',
    `qualification_check` VARCHAR(255) NULL DEFAULT NULL COMMENT 'فحص التأهيل ◄',
    `project_contract_check` VARCHAR(255) NULL DEFAULT NULL COMMENT 'فحص عقد المشروع ◄',
    `project_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المشروع ◄',
    `site_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع ◄',
    `from_date` DATE NULL DEFAULT NULL COMMENT 'من تاريخ',
    `to_date` DATE NULL DEFAULT NULL COMMENT 'إلى تاريخ',
    `housing_unit_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'وحدة السكن ◄',
    `allocation_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة التخصيص ▼',
    `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_69539f61_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-13 — التخصيص للمشروع · الحبة: تخصيصُ فردٍ واحدٍ لمشروعٍ واحدٍ في مدّة'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `wf_equipment_shift_assignment` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `assignment_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم التكليف',
    `allocation_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم تخصيص المشروع ◄',
    `person_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفرد ◄',
    `equipment_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود المعدة ◄',
    `equipment_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع المعدة ◄',
    `matrix_check` VARCHAR(255) NULL DEFAULT NULL COMMENT 'فحص المصفوفة ◄',
    `shift` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوردية ▼',
    `from_date` DATE NULL DEFAULT NULL COMMENT 'من تاريخ',
    `to_date` DATE NULL DEFAULT NULL COMMENT 'إلى تاريخ',
    `approved_backup` VARCHAR(255) NULL DEFAULT NULL COMMENT 'بديل معتمد ◄',
    `end_reason` VARCHAR(80) NULL DEFAULT NULL COMMENT 'سبب الإنهاء ▼',
    `assignment_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة التكليف ▼',
    `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_56f60216_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-13 — تخصيص المعدة والوردية · الحبة: تكليفُ فردٍ على معدّةٍ في وردية'
SQL,
    <<<'SQL'
ALTER TABLE `worker_movement`
    ADD COLUMN `row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف السطر',
    ADD COLUMN `row_date` DATE NULL DEFAULT NULL COMMENT 'التاريخ',
    ADD COLUMN `person_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفرد ◄',
    ADD COLUMN `row_kind` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع السطر ▼',
    ADD COLUMN `presence_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة التواجد ▼',
    ADD COLUMN `span_from` VARCHAR(80) NULL DEFAULT NULL COMMENT 'من ◄',
    ADD COLUMN `span_to` VARCHAR(80) NULL DEFAULT NULL COMMENT 'إلى ◄',
    ADD COLUMN `transfer_order_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع أمر النقل ◄',
    ADD COLUMN `site_presence` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التواجد بالموقع ◄',
    ADD COLUMN `row_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة السطر ▼',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
ALTER TABLE `operator_rotations`
    ADD COLUMN `cycle_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف الدورة',
    ADD COLUMN `person_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفرد ◄',
    ADD COLUMN `rotation_pattern` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نمط التناوب ▼',
    ADD COLUMN `cycle_start_date` VARCHAR(255) NULL DEFAULT NULL COMMENT 'بداية الدورة',
    ADD COLUMN `leave_start` VARCHAR(255) NULL DEFAULT NULL COMMENT 'بداية الإجازة',
    ADD COLUMN `leave_end` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نهاية الإجازة',
    ADD COLUMN `leave_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع الإجازة ▼',
    ADD COLUMN `assigned_backup` VARCHAR(255) NULL DEFAULT NULL COMMENT 'البديل المكلَّف ◄',
    ADD COLUMN `backup_qual_check` VARCHAR(255) NULL DEFAULT NULL COMMENT 'فحص تأهيل البديل ◄',
    ADD COLUMN `swap_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة التبادل ▼',
    ADD COLUMN `coverage_effect` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أثر التغطية ◄',
    ADD COLUMN `cycle_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الدورة ▼',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
ALTER TABLE `worker_evaluation`
    ADD COLUMN `row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف السطر ◄',
    ADD COLUMN `period_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الفترة ◄',
    ADD COLUMN `person_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفرد ◄',
    ADD COLUMN `category_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الفئة ◄',
    ADD COLUMN `approved_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'ساعات معتمدة ◄',
    ADD COLUMN `approved_units` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'وحدات معتمدة ◄',
    ADD COLUMN `field_days` INT NULL DEFAULT NULL COMMENT 'أيام ميدان ◄',
    ADD COLUMN `shift_compliance` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الالتزام بالوردية ◄',
    ADD COLUMN `period_incidents` VARCHAR(255) NULL DEFAULT NULL COMMENT 'وقائع الفترة ◄',
    ADD COLUMN `performance_index` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'مؤشر الأداء ◄'
SQL,
    <<<'SQL'
ALTER TABLE `worker_settlement`
    ADD COLUMN `settlement_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم التسوية',
    ADD COLUMN `allocation_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم التخصيص ◄',
    ADD COLUMN `person_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفرد ◄',
    ADD COLUMN `end_reason` VARCHAR(255) NULL DEFAULT NULL COMMENT 'سبب الإنهاء ◄',
    ADD COLUMN `housing_cleared` VARCHAR(255) NULL DEFAULT NULL COMMENT 'إخلاء السكن ◄',
    ADD COLUMN `custody_returned` VARCHAR(255) NULL DEFAULT NULL COMMENT 'عُهد مردودة ◄',
    ADD COLUMN `custody_pending` VARCHAR(255) NULL DEFAULT NULL COMMENT 'عُهد معلَّقة ◄',
    ADD COLUMN `due_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس المستحق ◄',
    ADD COLUMN `paying_party` VARCHAR(255) NULL DEFAULT NULL COMMENT 'جهة الصرف ◄',
    ADD COLUMN `hr_clearance_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع تصفية الموارد ◄',
    ADD COLUMN `settlement_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة التسوية ▼',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `wf_field_incident` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `incident_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الواقعة',
    `incident_date` DATE NULL DEFAULT NULL COMMENT 'التاريخ',
    `person_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود الفرد ◄',
    `affiliation_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'التبعية ◄',
    `incident_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع الواقعة ▼',
    `incident_desc` VARCHAR(500) NULL DEFAULT NULL COMMENT 'وصف الواقعة',
    `evidence_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الدليل/المرفق',
    `referred_to` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الإحالة إلى ◄',
    `case_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع القضية ب08-0 ◄',
    `supplier_settlement_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع تسوية المورد ◄',
    `incident_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الواقعة ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_c2f0f2af_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-13 — وقائع الميدان والإحالة التأديبية · الحبة: واقعةُ ميدانٍ واحدةٌ على فردٍ واحد'
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
