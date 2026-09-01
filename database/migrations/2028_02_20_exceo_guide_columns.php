<?php
/**
 * 2028_02_20_exceo_guide_columns.php — EX-CEO · مساحة الرئيس التنفيذي — جداولُ مواضعِ الدليل (GOV_EXEC §5)
 * @migration-objects: tables for EX-CEO
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
ALTER TABLE `exec_approvals`
    ADD COLUMN `item_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'معرّف البند',
    ADD COLUMN `item_category` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الفئة ▼',
    ADD COLUMN `item_kind` VARCHAR(80) NULL DEFAULT NULL COMMENT 'النوع ▼',
    ADD COLUMN `source_dept` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الإدارة المصدر ◄',
    ADD COLUMN `txn_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المعاملة ◄',
    ADD COLUMN `project_ref` VARCHAR(120) NULL DEFAULT NULL COMMENT 'Project ◄',
    ADD COLUMN `budget_status` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Budget_Status ◄',
    ADD COLUMN `matrix_rule_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'قاعدة المصفوفة المفعِّلة ◄',
    ADD COLUMN `prior_levels` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المستويات السابقة ◄',
    ADD COLUMN `deputy_recommendation` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Relevant_Deputy_Recommendation ◄',
    ADD COLUMN `finance_review_status` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Finance_Review_Status ◄',
    ADD COLUMN `risk_level` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Risk_Level ◄',
    ADD COLUMN `ceo_decision` VARCHAR(80) NULL DEFAULT NULL COMMENT 'CEO_Decision ▼',
    ADD COLUMN `decision_conditions` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Decision_Conditions',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
ALTER TABLE `exec_contract_signings`
    ADD COLUMN `document_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Document_ID',
    ADD COLUMN `document_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'Document_Type ▼',
    ADD COLUMN `owning_department` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Owning_Department ◄',
    ADD COLUMN `counterparty_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Counterparty ◄',
    ADD COLUMN `doc_value` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'Value ◄',
    ADD COLUMN `doc_currency` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Currency ◄',
    ADD COLUMN `doc_term` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Term ◄',
    ADD COLUMN `key_obligations` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Key_Obligations ◄',
    ADD COLUMN `legal_review_status` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Legal_Review_Status ◄',
    ADD COLUMN `financial_review_status` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Financial_Review_Status ◄',
    ADD COLUMN `compliance_status` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Compliance_Status ◄',
    ADD COLUMN `previous_approvals` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Previous_Approvals ◄',
    ADD COLUMN `doc_exceptions` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Exceptions ◄',
    ADD COLUMN `final_document_link` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Final_Document_Link ◄',
    ADD COLUMN `ceo_signature_status` VARCHAR(80) NULL DEFAULT NULL COMMENT 'CEO_Signature_Status ▼',
    ADD COLUMN `delegation_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع التفويض عند الإنابة ◄',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `exec_doc_review_note` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `note_no` VARCHAR(500) NULL DEFAULT NULL COMMENT 'رقم الملاحظة',
    `document_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Document_ID ◄',
    `entity_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكيان ◄',
    `observed_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الرصد',
    `observer_party` VARCHAR(80) NULL DEFAULT NULL COMMENT 'الجهة الراصدة ▼',
    `note_kind` VARCHAR(500) NULL DEFAULT NULL COMMENT 'نوع الملاحظة ▼',
    `note_grade` VARCHAR(500) NULL DEFAULT NULL COMMENT 'درجة الملاحظة ▼',
    `affected_clause` VARCHAR(255) NULL DEFAULT NULL COMMENT 'البند المتأثر ◄',
    `note_desc` VARCHAR(500) NULL DEFAULT NULL COMMENT 'وصف الملاحظة',
    `potential_impact` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الأثر المحتمل',
    `value_at_risk` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'القيمة المعرَّضة',
    `currency_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'العملة ◄',
    `blocks_approval` VARCHAR(80) NULL DEFAULT NULL COMMENT 'يحجب الاعتماد؟ ▼',
    `required_action` VARCHAR(500) NULL DEFAULT NULL COMMENT 'الإجراء المطلوب',
    `action_owner` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المسؤول ◄',
    `action_deadline` DATE NULL DEFAULT NULL COMMENT 'مهلة المعالجة',
    `action_document` VARCHAR(255) NULL DEFAULT NULL COMMENT 'مستند المعالجة',
    `closed_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الإقفال ◄',
    `note_state` VARCHAR(500) NULL DEFAULT NULL COMMENT 'حالة الملاحظة ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_20fd707a_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'EX-CEO — ملاحظات مراجعة الوثيقة قبل التوقيع · الحبة: ملاحظةُ مراجعةٍ واحدةٌ على وثيقةٍ قبلَ توقيعِها'
SQL,
    <<<'SQL'
ALTER TABLE `exec_assignments`
    ADD COLUMN `assignment_code` VARCHAR(80) NULL DEFAULT NULL COMMENT 'رقم التكليف',
    ADD COLUMN `entity_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الكيان ◄',
    ADD COLUMN `assignee_person_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المكلَّف — مرجع الشخص',
    ADD COLUMN `position_scope` VARCHAR(255) NULL DEFAULT NULL COMMENT 'المنصب/النطاق',
    ADD COLUMN `granted_authorities` VARCHAR(255) NULL DEFAULT NULL COMMENT 'الصلاحيات الممنوحة',
    ADD COLUMN `approval_cap` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'سقف الاعتماد الممنوح',
    ADD COLUMN `assignment_allowance` DECIMAL(18,2) NULL DEFAULT NULL COMMENT 'بدل التكليف',
    ADD COLUMN `excluded_scope` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ما لا يشمله',
    ADD COLUMN `from_date` DATE NULL DEFAULT NULL COMMENT 'من تاريخ',
    ADD COLUMN `to_date` DATE NULL DEFAULT NULL COMMENT 'إلى تاريخ',
    ADD COLUMN `governance_registry_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع سجل الحوكمة النافذ ◄',
    ADD COLUMN `assignment_state` VARCHAR(80) NULL DEFAULT NULL COMMENT 'حالة التكليف ▼',
    ADD COLUMN `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    ADD COLUMN `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    ADD COLUMN `created_by` INT NULL DEFAULT NULL COMMENT 'المُنشئ'
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS `exec_meeting` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `company_id` INT NOT NULL DEFAULT 0,
    `meeting_code` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Meeting_ID',
    `meeting_type` VARCHAR(80) NULL DEFAULT NULL COMMENT 'Meeting_Type ▼',
    `meeting_date` DATE NULL DEFAULT NULL COMMENT 'Date',
    `chair_ref` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Chair — مرجع رئيس الاجتماع',
    `participants` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Participants',
    `agenda` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Agenda',
    `documents` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Documents',
    `minutes_status` VARCHAR(80) NULL DEFAULT NULL COMMENT 'Minutes_Status ▼',
    `data_state` VARCHAR(255) NULL DEFAULT NULL COMMENT 'حالة البيانات ▼',
    `src_ref` VARCHAR(80) NULL DEFAULT NULL COMMENT 'مرجع المصدر',
    `created_by` INT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ix_8b8f0c80_co` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT 'EX-CEO — اجتماعات الإدارة العليا · الحبة: اجتماعُ إدارةٍ عليا واحدٌ'
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
