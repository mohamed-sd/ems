<?php
/**
 * 2028_03_23_govui_dep02_guide2_fields.php — DEP-02 · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
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

$q = $conn->query("SHOW COLUMNS FROM `sup_need` LIKE 'need_no'");
if ($q && $q->num_rows) { echo "= sup_need.need_no قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_need` ADD COLUMN `need_no` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم الاحتياج'")) {
    echo "+ sup_need.need_no\n";
} else { echo "x sup_need.need_no: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_need` LIKE 'obligation_gap'");
if ($q && $q->num_rows) { echo "= sup_need.obligation_gap قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_need` ADD COLUMN `obligation_gap` VARCHAR(190) NULL DEFAULT NULL COMMENT 'فجوة الالتزام'")) {
    echo "+ sup_need.obligation_gap\n";
} else { echo "x sup_need.obligation_gap: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_account` LIKE 'settle_no'");
if ($q && $q->num_rows) { echo "= sup_account.settle_no قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_account` ADD COLUMN `settle_no` VARCHAR(190) NULL DEFAULT NULL COMMENT 'رقم الإقفال'")) {
    echo "+ sup_account.settle_no\n";
} else { echo "x sup_account.settle_no: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_account` LIKE 'creator_name'");
if ($q && $q->num_rows) { echo "= sup_account.creator_name قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_account` ADD COLUMN `creator_name` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المنشئ'")) {
    echo "+ sup_account.creator_name\n";
} else { echo "x sup_account.creator_name: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_account` LIKE 'reviewer_name'");
if ($q && $q->num_rows) { echo "= sup_account.reviewer_name قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_account` ADD COLUMN `reviewer_name` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المراجع'")) {
    echo "+ sup_account.reviewer_name\n";
} else { echo "x sup_account.reviewer_name: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_account` LIKE 'approver_name'");
if ($q && $q->num_rows) { echo "= sup_account.approver_name قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_account` ADD COLUMN `approver_name` VARCHAR(190) NULL DEFAULT NULL COMMENT 'المعتمد'")) {
    echo "+ sup_account.approver_name\n";
} else { echo "x sup_account.approver_name: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `sup_account` LIKE 'approved_date'");
if ($q && $q->num_rows) { echo "= sup_account.approved_date قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `sup_account` ADD COLUMN `approved_date` DATE NULL DEFAULT NULL COMMENT 'تاريخ الاعتماد'")) {
    echo "+ sup_account.approved_date\n";
} else { echo "x sup_account.approved_date: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
