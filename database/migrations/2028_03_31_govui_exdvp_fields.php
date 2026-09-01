<?php
/**
 * 2028_03_31_govui_exdvp_fields.php — EX-DVP · أعمدةُ حقولٍ لا نظيرَ لها في المخزن
 * @migration-objects: columns for EX-DVP
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

$sql = 'CREATE TABLE IF NOT EXISTS `dvp_delegations` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g21` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Delegation_ID\',`g22` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Delegate_From\',`g23` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Delegate_To\',`g24` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Scope\',`g25` VARCHAR(190) NULL DEFAULT NULL COMMENT \'From_Date\',`g26` VARCHAR(190) NULL DEFAULT NULL COMMENT \'To_Date\',`g27` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Approval_Level\',`g28` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Exclusions\',`g29` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Status\',`g30` VARCHAR(190) NULL DEFAULT NULL COMMENT \'مرجع سجل الحوكمة\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_21e1a12c_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'VP-12 - الإنابات والتفويضات\'';
if ($conn->query($sql)) { echo '+ جدول dvp_delegations
'; }
else { echo 'x dvp_delegations: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `dvp_vp_pending_actions` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g31` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف البند\',`g32` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Deputy_Role\',`g33` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المصدر\',`g34` VARCHAR(190) NULL DEFAULT NULL COMMENT \'نوع الفعل\',`g35` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المرجع\',`g36` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المهلة\',`g37` VARCHAR(190) NULL DEFAULT NULL COMMENT \'أيام التأخير\',`g38` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الأولوية\',`g39` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_43c25a14_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'VP-10 - الإجراءات والقرارات المطلوبة مني\'';
if ($conn->query($sql)) { echo '+ جدول dvp_vp_pending_actions
'; }
else { echo 'x dvp_vp_pending_actions: ' . $conn->error . chr(10); }

$sql = 'CREATE TABLE IF NOT EXISTS `dvp_dashboard_kpi` (`id` INT NOT NULL AUTO_INCREMENT,`company_id` INT NOT NULL DEFAULT 0 COMMENT \'بوابة المستأجر\',`g40` VARCHAR(190) NULL DEFAULT NULL COMMENT \'معرف المؤشر\',`g41` VARCHAR(190) NULL DEFAULT NULL COMMENT \'Deputy_Role\',`g42` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المحور\',`g43` VARCHAR(190) NULL DEFAULT NULL COMMENT \'ضمن Default Scope؟\',`g44` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المؤشر\',`g45` VARCHAR(190) NULL DEFAULT NULL COMMENT \'القيمة\',`g46` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الوحدة/العملة\',`g47` VARCHAR(190) NULL DEFAULT NULL COMMENT \'المستهدف\',`g48` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الانحراف\',`g49` VARCHAR(190) NULL DEFAULT NULL COMMENT \'الحالة\',`g50` VARCHAR(190) NULL DEFAULT NULL COMMENT \'رابط النزول\',`g51` VARCHAR(190) NULL DEFAULT NULL COMMENT \'آخر تحديث\',`created_at` DATETIME NULL DEFAULT NULL,`created_by` INT NULL DEFAULT NULL,`updated_at` DATETIME NULL DEFAULT NULL,PRIMARY KEY (`id`),KEY `ix_8b9c80a4_co` (`company_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT \'VP-01 - لا سطر مسجل بعد في لوحة النائب\'';
if ($conn->query($sql)) { echo '+ جدول dvp_dashboard_kpi
'; }
else { echo 'x dvp_dashboard_kpi: ' . $conn->error . chr(10); }

$q = $conn->query("SHOW COLUMNS FROM `exec_daily_report` LIKE 'g1'");
if ($q && $q->num_rows) { echo "= exec_daily_report.g1 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_daily_report` ADD COLUMN `g1` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Deputy_Role'")) {
    echo "+ exec_daily_report.g1\n";
} else { echo "x exec_daily_report.g1: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_daily_report` LIKE 'g2'");
if ($q && $q->num_rows) { echo "= exec_daily_report.g2 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_daily_report` ADD COLUMN `g2` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ضمن Scope؟'")) {
    echo "+ exec_daily_report.g2\n";
} else { echo "x exec_daily_report.g2: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_request_queue` LIKE 'g3'");
if ($q && $q->num_rows) { echo "= exec_request_queue.g3 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_request_queue` ADD COLUMN `g3` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Deputy_Role'")) {
    echo "+ exec_request_queue.g3\n";
} else { echo "x exec_request_queue.g3: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_request_queue` LIKE 'g4'");
if ($q && $q->num_rows) { echo "= exec_request_queue.g4 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_request_queue` ADD COLUMN `g4` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Approval_Scope'")) {
    echo "+ exec_request_queue.g4\n";
} else { echo "x exec_request_queue.g4: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_request_queue` LIKE 'g5'");
if ($q && $q->num_rows) { echo "= exec_request_queue.g5 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_request_queue` ADD COLUMN `g5` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Status'")) {
    echo "+ exec_request_queue.g5\n";
} else { echo "x exec_request_queue.g5: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_request_queue` LIKE 'g6'");
if ($q && $q->num_rows) { echo "= exec_request_queue.g6 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_request_queue` ADD COLUMN `g6` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Deputy_Decision'")) {
    echo "+ exec_request_queue.g6\n";
} else { echo "x exec_request_queue.g6: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_action_followup` LIKE 'g7'");
if ($q && $q->num_rows) { echo "= exec_action_followup.g7 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_action_followup` ADD COLUMN `g7` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Deputy_Role'")) {
    echo "+ exec_action_followup.g7\n";
} else { echo "x exec_action_followup.g7: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g8'");
if ($q && $q->num_rows) { echo "= exec_org_project.g8 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_org_project` ADD COLUMN `g8` VARCHAR(190) NULL DEFAULT NULL COMMENT 'وضع العرض'")) {
    echo "+ exec_org_project.g8\n";
} else { echo "x exec_org_project.g8: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g9'");
if ($q && $q->num_rows) { echo "= exec_org_project.g9 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_org_project` ADD COLUMN `g9` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Deputy_Role'")) {
    echo "+ exec_org_project.g9\n";
} else { echo "x exec_org_project.g9: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g10'");
if ($q && $q->num_rows) { echo "= exec_org_project.g10 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_org_project` ADD COLUMN `g10` VARCHAR(190) NULL DEFAULT NULL COMMENT 'الإدارة'")) {
    echo "+ exec_org_project.g10\n";
} else { echo "x exec_org_project.g10: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g11'");
if ($q && $q->num_rows) { echo "= exec_org_project.g11 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_org_project` ADD COLUMN `g11` VARCHAR(190) NULL DEFAULT NULL COMMENT 'ضمن نطاقي؟'")) {
    echo "+ exec_org_project.g11\n";
} else { echo "x exec_org_project.g11: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g12'");
if ($q && $q->num_rows) { echo "= exec_org_project.g12 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_org_project` ADD COLUMN `g12` VARCHAR(190) NULL DEFAULT NULL COMMENT 'خارج النطاق قراءة فقط'")) {
    echo "+ exec_org_project.g12\n";
} else { echo "x exec_org_project.g12: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g13'");
if ($q && $q->num_rows) { echo "= exec_org_project.g13 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_org_project` ADD COLUMN `g13` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Pending_Requests'")) {
    echo "+ exec_org_project.g13\n";
} else { echo "x exec_org_project.g13: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_org_project` LIKE 'g14'");
if ($q && $q->num_rows) { echo "= exec_org_project.g14 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_org_project` ADD COLUMN `g14` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Critical_Risks'")) {
    echo "+ exec_org_project.g14\n";
} else { echo "x exec_org_project.g14: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_weekly_report` LIKE 'g15'");
if ($q && $q->num_rows) { echo "= exec_weekly_report.g15 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_weekly_report` ADD COLUMN `g15` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Deputy_Role'")) {
    echo "+ exec_weekly_report.g15\n";
} else { echo "x exec_weekly_report.g15: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_weekly_report` LIKE 'g16'");
if ($q && $q->num_rows) { echo "= exec_weekly_report.g16 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_weekly_report` ADD COLUMN `g16` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Deputy_Action المتفرع'")) {
    echo "+ exec_weekly_report.g16\n";
} else { echo "x exec_weekly_report.g16: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_monthly_pack` LIKE 'g17'");
if ($q && $q->num_rows) { echo "= exec_monthly_pack.g17 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_monthly_pack` ADD COLUMN `g17` VARCHAR(190) NULL DEFAULT NULL COMMENT 'Deputy_Role'")) {
    echo "+ exec_monthly_pack.g17\n";
} else { echo "x exec_monthly_pack.g17: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_monthly_pack` LIKE 'g18'");
if ($q && $q->num_rows) { echo "= exec_monthly_pack.g18 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_monthly_pack` ADD COLUMN `g18` VARCHAR(190) NULL DEFAULT NULL COMMENT 'مراجعة النائب Decision Event'")) {
    echo "+ exec_monthly_pack.g18\n";
} else { echo "x exec_monthly_pack.g18: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_monthly_pack` LIKE 'g19'");
if ($q && $q->num_rows) { echo "= exec_monthly_pack.g19 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_monthly_pack` ADD COLUMN `g19` VARCHAR(190) NULL DEFAULT NULL COMMENT 'اكتملت المراجعة؟'")) {
    echo "+ exec_monthly_pack.g19\n";
} else { echo "x exec_monthly_pack.g19: " . $conn->error . "\n"; }

$q = $conn->query("SHOW COLUMNS FROM `exec_monthly_pack` LIKE 'g20'");
if ($q && $q->num_rows) { echo "= exec_monthly_pack.g20 قائمٌ سلفًا\n"; }
elseif ($conn->query("ALTER TABLE `exec_monthly_pack` ADD COLUMN `g20` VARCHAR(190) NULL DEFAULT NULL COMMENT 'انعكس في حزمة الرئيس'")) {
    echo "+ exec_monthly_pack.g20\n";
} else { echo "x exec_monthly_pack.g20: " . $conn->error . "\n"; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
