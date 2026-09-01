<?php
/**
 * 2028_02_18_dep11_ops_guide_columns.php — DEP-11 · إدارة التشغيل — جداولُ مواضعِ الدليل (GOV_EXEC §5)
 * @migration-objects: tables for DEP-11
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
CREATE TABLE IF NOT EXISTS `ops_room_kpi` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `kpi_id` VARCHAR(60) NULL DEFAULT NULL COMMENT 'معرّف المؤشر',
    `kpi_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المؤشر — KPI Catalog ◄',
    `value` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'القيمة ◄',
    `uom` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الوحدة ◄',
    `period_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الفترة ▼',
    `source_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المصدر ◄',
    `control_limit` VARCHAR(120) NULL DEFAULT NULL COMMENT 'الحد المضبوط ◄',
    `state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الحالة ◄',
    `trend` VARCHAR(255) NULL DEFAULT NULL COMMENT 'اتجاه الوتيرة ◄',
    `deviation_owner` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مسؤول الانحراف ◄',
    `updated_on` DATETIME NULL DEFAULT NULL COMMENT 'آخر تحديث ◄',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_da5c18e3_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-11 — غرفة العمليات · الحبة: مؤشرُ غرفةِ عملياتٍ واحدٌ في فترة'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `ops_seasonal_factor` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `factor_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف المعامل',
    `season` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموسم',
    `from_date` DATE NULL DEFAULT NULL COMMENT 'من تاريخ',
    `to_date` DATE NULL DEFAULT NULL COMMENT 'إلى تاريخ',
    `business_model` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نموذج العمل ▼',
    `calibration_factor` DECIMAL(10,4) NULL DEFAULT NULL COMMENT 'معامل المعايرة',
    `calc_basis` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أساس الاحتساب',
    `apply_scope` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نطاق التطبيق ▼',
    `factor_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة المعامل ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_d0e2a57f_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-11 — الخطة الموسمية ومعاملاتها · الحبة: معاملُ معايرةٍ موسميٌّ واحدٌ في مدّة'
SQL,
    <<<'SQL'
ALTER TABLE `daily_plan_lines`
    ADD COLUMN `row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف سطر التوزيع',
    ADD COLUMN `plan_day` DATE NULL DEFAULT NULL COMMENT 'يوم التوزيع',
    ADD COLUMN `project_ref` VARCHAR(60) NULL DEFAULT NULL COMMENT 'رقم المشروع ◄',
    ADD COLUMN `site_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع ◄',
    ADD COLUMN `work_zone` VARCHAR(255) NULL DEFAULT NULL COMMENT 'منطقة العمل/الجبهة',
    ADD COLUMN `zone_supervisor` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مشرف المنطقة ◄',
    ADD COLUMN `equipment_type` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع المعدة ◄',
    ADD COLUMN `equipment_tech_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة المعدة الفنية ◄',
    ADD COLUMN `critical_maint_block` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حاجب الصيانة الحرجة ◄',
    ADD COLUMN `operator_qualified` VARCHAR(255) NULL DEFAULT NULL COMMENT 'تأهيل المشغّل على النوع ◄',
    ADD COLUMN `operator_license_valid` VARCHAR(255) NULL DEFAULT NULL COMMENT 'رخصة المشغّل سارية؟ ◄',
    ADD COLUMN `dispatch_conflict` VARCHAR(255) NULL DEFAULT NULL COMMENT 'تعارض التوزيع ◄',
    ADD COLUMN `zone_daily_target` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'مستهدف المنطقة لليوم',
    ADD COLUMN `row_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة السطر ▼',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `ops_resource_move_order` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `order_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الأمر',
    `order_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الأمر',
    `resource_kind` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع المورد المنقول ▼',
    `resource_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود المورد المنقول ◄',
    `from_project` VARCHAR(255) NULL DEFAULT NULL COMMENT 'من مشروع ◄',
    `from_site` VARCHAR(255) NULL DEFAULT NULL COMMENT 'من موقع ◄',
    `to_project` VARCHAR(255) NULL DEFAULT NULL COMMENT 'إلى مشروع ◄',
    `to_site` VARCHAR(255) NULL DEFAULT NULL COMMENT 'إلى موقع ◄',
    `move_reason` VARCHAR(80) NULL DEFAULT NULL COMMENT 'سبب الحركة ▼',
    `requested_exec_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ التنفيذ المطلوب',
    `month_plan_effect` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أثر الحركة على خطة الشهر',
    `needs_transfer_order` VARCHAR(80) NULL DEFAULT NULL COMMENT 'يتطلب أمر ترحيل؟ ▼',
    `transfer_order_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم أمر الترحيل المتفرع ◄',
    `order_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الأمر ▼',
    `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_382c739c_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-11 — طلب وقرار حركة الموارد · الحبة: أمرُ حركةِ موردٍ واحدٌ من موقعٍ إلى موقع'
SQL,
    <<<'SQL'
ALTER TABLE `shift_period_logs`
    ADD COLUMN `daily_log_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف السجل اليومي ◄',
    ADD COLUMN `time_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة الزمن ▼',
    ADD COLUMN `from_time` TIME NULL DEFAULT NULL COMMENT 'من الساعة',
    ADD COLUMN `to_time` TIME NULL DEFAULT NULL COMMENT 'إلى الساعة',
    ADD COLUMN `duration_mins` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'المدة ◄',
    ADD COLUMN `stop_reason_l2` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مستوى السبب الثاني ◄',
    ADD COLUMN `stop_reason_l3` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مستوى السبب الثالث ◄',
    ADD COLUMN `stop_owner` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'مسؤول التوقف ◄',
    ADD COLUMN `client_obligation_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع الالتزام في عقد العميل ◄',
    ADD COLUMN `billing_effect` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أثر الفوترة ◄',
    ADD COLUMN `supplier_unit_effect` VARCHAR(80) NULL DEFAULT NULL COMMENT 'أثر وحدة المورد ◄',
    ADD COLUMN `operator_wage_effect` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أثر أجر المشغّل ◄',
    ADD COLUMN `stop_decision_required` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'قرار التوقف مطلوب؟ ◄',
    ADD COLUMN `stop_decision_no` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'رقم قرار التوقف ◄',
    ADD COLUMN `field_note` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظة ميدانية',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
ALTER TABLE `ops_stop_register`
    ADD COLUMN `decision_no` VARCHAR(500) NULL DEFAULT NULL COMMENT 'رقم القرار',
    ADD COLUMN `event_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الواقعة',
    ADD COLUMN `stop_period_ref` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'معرّف فترة التوقف ◄',
    ADD COLUMN `stop_reason_ref` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'سبب التوقف ◄',
    ADD COLUMN `accumulated_stop_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'ساعات التوقف المتراكمة ◄',
    ADD COLUMN `mandatory_sla` VARCHAR(80) NULL DEFAULT NULL COMMENT 'المهلة الإلزامية ◄',
    ADD COLUMN `sla_elapsed` DATE NULL DEFAULT NULL COMMENT 'انقضت المهلة؟ ◄',
    ADD COLUMN `decision_reason` VARCHAR(500) NULL DEFAULT NULL COMMENT 'مبرر القرار',
    ADD COLUMN `readiness_effect` VARCHAR(500) NULL DEFAULT NULL COMMENT 'أثر القرار على الجاهزية ▼',
    ADD COLUMN `decision_effective_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ نفاذ القرار',
    ADD COLUMN `decision_state` VARCHAR(500) NULL DEFAULT NULL COMMENT 'حالة القرار ▼',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `ops_deviation_report` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف السطر',
    `period_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الفترة ◄',
    `project_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المشروع ◄',
    `equipment_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المعدة ◄',
    `calibrated_plan` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المخطط المعاير ◄',
    `approved_executed` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'المنفَّذ المعتمد ◄',
    `gap` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الفجوة ◄',
    `deviation_pct` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'نسبة الانحراف ◄',
    `control_limit` VARCHAR(120) NULL DEFAULT NULL COMMENT 'الحد المضبوط ◄',
    `limit_exceeded` VARCHAR(255) NULL DEFAULT NULL COMMENT 'تجاوز الحد؟ ◄',
    `catchup_rate` DECIMAL(18,3) NULL DEFAULT NULL COMMENT 'الوتيرة المطلوبة للحاق ◄',
    `top_three_reasons` VARCHAR(255) NULL DEFAULT NULL COMMENT 'أكبر ثلاثة أسباب ◄',
    `deviation_owner` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مسؤول الانحراف ◄',
    `escalation_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة التصعيد ◄',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_84896734_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-11 — تقرير الانحراف والتصعيد · الحبة: سطرُ انحرافٍ واحدٌ لمشروعٍ ومعدّةٍ في فترة'
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
