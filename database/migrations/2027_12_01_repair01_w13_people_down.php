<?php
/**
 * 2027_12_01_repair01_w13_people_down.php — تراجعُ المرحلةِ الثالثةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **التراجعُ ينزع ما بنَته الهجرةُ ولا يمسُّ حيًّا سابقًا**: جداولُ W13 تُسقَط،
 *   **والأعمدةُ الحيّةُ التي شُدِّدت تُرخى كما كانت** — فالتراجعُ يعيد الحالةَ
 *   لا يترك أثرًا نصفيًّا.
 *
 * ⛔ **ولا يُمَسُّ عمودٌ لم تُنشئه هذه الهجرة** — والابنُ يُسقَط قبل أبيه.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$done = 0; $err = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};
function w13d_tbl(mysqli $c, $t)
{
    $r = $c->query("SHOW TABLES LIKE '" . $c->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
}

echo "══ REPAIR01 · W13 — تراجعُ الموارد البشرية والبلاغات ══\n\n";

/* الابنُ قبل الأب */
$DROP = array(
    'hr_disciplinary_stage', 'hr_disciplinary_case',
    'hr_benefit_enrollment', 'hr_performance_review', 'hr_training_record',
    'hr_job_movement', 'hr_onboarding_item', 'hr_employee_document',
    'tkt_reopen', 'tkt_verification', 'tkt_resolution_action',
    'tkt_assignment_history', 'tkt_routing_history',
    'tkt_party', 'tkt_subject_type',
    'repair01_w13_parties', 'repair01_w13_nav_moves', 'repair01_w13_journey',
    'repair01_w13_fixes', 'repair01_w13_thresholds', 'repair01_w13_sod',
    'repair01_w13_states', 'repair01_w13_decisions',
    'repair01_w13_sidebar', 'repair01_w13_scope',
);
foreach ($DROP as $t) {
    if (!w13d_tbl($conn, $t)) { echo "  ↷ $t غير موجود\n"; continue; }
    $run("DROP TABLE `$t`", $t);
}

/* **والعمودُ المشدَّدُ يُرخى كما كان** — وإلّا بقي أثرُ المرحلةِ بعد تراجعِها */
echo "\nإرخاءُ الأعمدةِ التي شُدِّدت ────────────────────────────────────\n";
$LOOSE = array(
    array('workforce_requirement', 'company_id', 'INT UNSIGNED NULL DEFAULT NULL'),
    array('worker_evaluation',     'company_id', 'INT UNSIGNED NULL DEFAULT NULL'),
    array('job_titles',            'company_id', 'INT UNSIGNED NULL DEFAULT NULL'),
    array('ticket_communications', 'company_id', 'INT UNSIGNED NULL DEFAULT NULL'),
    array('worker_leave_absence',  'company_id', 'INT UNSIGNED NULL DEFAULT NULL'),
);
foreach ($LOOSE as $l) {
    list($tbl, $col, $ddl) = $l;
    if (!w13d_tbl($conn, $tbl)) { echo "  ↷ $tbl غير موجود\n"; continue; }
    $c = $conn->query("SHOW COLUMNS FROM `$tbl` LIKE '$col'");
    $cx = $c ? $c->fetch_assoc() : null;
    if (!$cx) { echo "  ↷ $tbl.$col غير موجود\n"; continue; }
    if (strtoupper((string) $cx['Null']) === 'YES') { echo "  ↷ $tbl.$col مُرخًى سلفًا\n"; continue; }
    $run("ALTER TABLE `$tbl` MODIFY `$col` $ddl", "$tbl.$col");
}

echo "\n──────────────────────────────────────────────────────────────\n";
printf("منفَّذٌ %d · فشل %d\n", $done, $err);
echo $err === 0 ? "الحكم: رجعت ✔\n" : "الحكم: فشل التراجع ✘\n";
exit($err === 0 ? 0 : 1);
