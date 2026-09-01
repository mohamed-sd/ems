<?php
/**
 * 2028_02_19_dep14_mnt_guide_columns.php — DEP-14 · إدارة الصيانة — جداولُ مواضعِ الدليل (GOV_EXEC §5)
 * @migration-objects: tables for DEP-14
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
ALTER TABLE `failure_codes`
    ADD COLUMN `node_level` VARCHAR(80) NULL DEFAULT NULL COMMENT 'المستوى ▼',
    ADD COLUMN `parent_node` VARCHAR(255) NULL DEFAULT NULL COMMENT 'العقدة الأم ◄',
    ADD COLUMN `node_desc` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الوصف',
    ADD COLUMN `billing_effect` VARCHAR(80) NULL DEFAULT NULL COMMENT 'أثر الفوترة ▼',
    ADD COLUMN `supplier_unit_effect` VARCHAR(80) NULL DEFAULT NULL COMMENT 'أثر وحدة المورد ▼',
    ADD COLUMN `operator_wage_effect` VARCHAR(80) NULL DEFAULT NULL COMMENT 'أثر أجر المشغّل ▼',
    ADD COLUMN `stops_readiness` VARCHAR(80) NULL DEFAULT NULL COMMENT 'يوقف الجاهزية؟ ▼',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
ALTER TABLE `mnt_inspection`
    ADD COLUMN `row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف السطر',
    ADD COLUMN `fleet_order_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع أمر التفتيش بالأسطول ◄',
    ADD COLUMN `returned_card_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع بطاقة التفتيش المعادة ◄',
    ADD COLUMN `row_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة السطر ▼',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `mnt_downtime_segment` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `segment_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف القطاع',
    `stop_event_ref` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'مرجع واقعة التوقف ◄',
    `equipment_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود المعدة ◄',
    `segment_seq` INT NULL DEFAULT NULL COMMENT 'تسلسل القطاع',
    `segment_kind` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نوع القطاع ▼',
    `segment_owner` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مالك القطاع ◄',
    `segment_start` DATETIME NULL DEFAULT NULL COMMENT 'بداية القطاع ◄',
    `segment_end` DATETIME NULL DEFAULT NULL COMMENT 'نهاية القطاع ◄',
    `segment_duration` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'مدة القطاع ◄',
    `segment_share_pct` DECIMAL(8,2) NULL DEFAULT NULL COMMENT 'نسبته من إجمالي التوقف ◄',
    `segment_reason` VARCHAR(255) NULL DEFAULT NULL COMMENT 'سبب القطاع',
    `owner_ack` VARCHAR(80) NULL DEFAULT NULL COMMENT 'إقرار صاحب القطاع ▼',
    `is_disputed` VARCHAR(255) NULL DEFAULT NULL COMMENT 'متنازَع عليه؟ ◄',
    `ops_manager_ruling` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حكم مدير التشغيل ◄',
    `recurrence_case_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع قضية التكرار ◄',
    `segment_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة القطاع ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_981cb2f2_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-14 — تقطيع مسؤولية التوقف · الحبة: قطاعُ مسؤوليةٍ واحدٌ داخلَ واقعةِ توقّف'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `mnt_diagnosis_request` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `diagnosis_no` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الفحص',
    `intake_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف الاستقبال ◄',
    `equipment_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'كود المعدة ◄',
    `inspector_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الفاحص ◄',
    `inspect_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الفحص',
    `meter_at_inspect` VARCHAR(255) NULL DEFAULT NULL COMMENT 'قراءة العدّاد عند الفحص',
    `final_tree_node` VARCHAR(80) NULL DEFAULT NULL COMMENT 'عقدة الشجرة النهائية ▼',
    `diagnosis_desc` VARCHAR(500) NULL DEFAULT NULL COMMENT 'وصف التشخيص',
    `expected_parts` VARCHAR(255) NULL DEFAULT NULL COMMENT 'القطع المتوقعة',
    `estimated_repair_time` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'الزمن التقديري للإصلاح',
    `repair_place` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مكان الإصلاح ▼',
    `needs_warranty_supplier` VARCHAR(80) NULL DEFAULT NULL COMMENT 'يتطلب مورد الضمان؟ ▼',
    `inspect_result` VARCHAR(80) NULL DEFAULT NULL COMMENT 'نتيجة الفحص ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_d93aaa7b_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'DEP-14 — طلب الفحص والتشخيص · الحبة: فحصٌ وتشخيصٌ واحدٌ يسبق أمرَ عمل'
SQL,
    <<<'SQL'
ALTER TABLE `mnt_order`
    ADD COLUMN `open_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الفتح',
    ADD COLUMN `diagnosis_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم الفحص ◄',
    ADD COLUMN `equipment_type_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نوع المعدة ◄',
    ADD COLUMN `site_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الموقع ◄',
    ADD COLUMN `tree_node_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'عقدة الشجرة ◄',
    ADD COLUMN `order_labor_summary` VARCHAR(255) NULL DEFAULT NULL COMMENT 'عمالة الأمر ◄ — تفصيلها ص05-2',
    ADD COLUMN `meter_at_open` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'قراءة العدّاد عند الفتح ◄',
    ADD COLUMN `planned_time` DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'الزمن المخطط',
    ADD COLUMN `target_finish_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الإنجاز المستهدف',
    ADD COLUMN `accumulated_downtime_hours` DECIMAL(12,2) NULL DEFAULT NULL COMMENT 'ساعات التوقف المتراكمة ◄',
    ADD COLUMN `estimated_parts_cost` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'التكلفة التقديرية للقطع ◄',
    ADD COLUMN `reviewer` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المراجع',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL COMMENT 'المعتمِد',
    ADD COLUMN `approved_at` DATETIME NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
ALTER TABLE `mnt_order_labor`
    ADD COLUMN `row_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف السطر',
    ADD COLUMN `labor_date` DATE NULL DEFAULT NULL COMMENT 'التاريخ',
    ADD COLUMN `tech_note` VARCHAR(500) NULL DEFAULT NULL COMMENT 'ملاحظة فنية',
    ADD COLUMN `row_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة السطر ▼',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
ALTER TABLE `mnt_return_cert`
    ADD COLUMN `cert_validity` VARCHAR(80) NULL DEFAULT NULL COMMENT 'صلاحية الشهادة',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼'
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
