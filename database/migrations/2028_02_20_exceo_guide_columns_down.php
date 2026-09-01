<?php
/**
 * 2028_02_20_exceo_guide_columns_down.php — العكسُ المسوّى (GOV_EXEC §5)
 * @migration-objects: reverse tables for EX-CEO
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
DROP TABLE IF EXISTS `exec_meeting`
SQL,
    <<<'SQL'
ALTER TABLE `exec_assignments`
    DROP COLUMN `assignment_code`,
    DROP COLUMN `entity_ref`,
    DROP COLUMN `assignee_person_ref`,
    DROP COLUMN `position_scope`,
    DROP COLUMN `granted_authorities`,
    DROP COLUMN `approval_cap`,
    DROP COLUMN `assignment_allowance`,
    DROP COLUMN `excluded_scope`,
    DROP COLUMN `from_date`,
    DROP COLUMN `to_date`,
    DROP COLUMN `governance_registry_ref`,
    DROP COLUMN `assignment_state`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`,
    DROP COLUMN `created_by`
SQL,
    <<<'SQL'
DROP TABLE IF EXISTS `exec_doc_review_note`
SQL,
    <<<'SQL'
ALTER TABLE `exec_contract_signings`
    DROP COLUMN `document_ref`,
    DROP COLUMN `document_type`,
    DROP COLUMN `owning_department`,
    DROP COLUMN `counterparty_ref`,
    DROP COLUMN `doc_value`,
    DROP COLUMN `doc_currency`,
    DROP COLUMN `doc_term`,
    DROP COLUMN `key_obligations`,
    DROP COLUMN `legal_review_status`,
    DROP COLUMN `financial_review_status`,
    DROP COLUMN `compliance_status`,
    DROP COLUMN `previous_approvals`,
    DROP COLUMN `doc_exceptions`,
    DROP COLUMN `final_document_link`,
    DROP COLUMN `ceo_signature_status`,
    DROP COLUMN `delegation_ref`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
    <<<'SQL'
ALTER TABLE `exec_approvals`
    DROP COLUMN `item_code`,
    DROP COLUMN `item_category`,
    DROP COLUMN `item_kind`,
    DROP COLUMN `source_dept`,
    DROP COLUMN `txn_ref`,
    DROP COLUMN `project_ref`,
    DROP COLUMN `budget_status`,
    DROP COLUMN `matrix_rule_ref`,
    DROP COLUMN `prior_levels`,
    DROP COLUMN `deputy_recommendation`,
    DROP COLUMN `finance_review_status`,
    DROP COLUMN `risk_level`,
    DROP COLUMN `ceo_decision`,
    DROP COLUMN `decision_conditions`,
    DROP COLUMN `data_state`,
    DROP COLUMN `src_ref`
SQL,
);
$n = 0;
foreach ($SQL as $s) {
    if (!$conn->query($s)) {
        $msg = $conn->error;
        if (stripos($msg, "check that column") !== false || stripos($msg, "doesn't exist") !== false) { continue; }
        exit("⛔ {$msg}\n  في: " . substr($s, 0, 120) . "\n");
    }
    $n++;
}
echo "✔ {$n} جملةً نُفِّذت\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
