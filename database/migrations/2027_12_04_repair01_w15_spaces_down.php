<?php
/**
 * 2027_12_04_repair01_w15_spaces_down.php — تراجعُ هجرةِ W15
 * ═══════════════════════════════════════════════════════════════════════════
 * ينزع **دفاترَ الحملةِ وحدَها** وما أضافته هذه الهجرةُ إلى `gov_request_type`.
 *
 * ⛔ **ولا يُمَسُّ جدولُ أعمالٍ ولا صفٌّ حيّ** — هذه المرحلةُ لم تُنشئ جدولَ
 *   حقيقةٍ أصلًا، فلا شيءَ من الأعمالِ يُنزَع.
 *
 * ⛔ **وقراراتُ المالكِ لا تُقلَب بإرجاعِ هجرة** — `DEC-OPEN-17` و`DEC-OPEN-03`
 *   جوابُهما قرارُ مالكٍ لا أثرُ أداة.
 *
 * التشغيل: php database/migrations/2027_12_04_repair01_w15_spaces_down.php
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
    echo "  ↷ $label — " . $conn->error . "\n"; return false;
};

echo "══ تراجعُ REPAIR01 · W15 ══\n\n";

$LEDGERS = array(
    'repair01_w15_launcher', 'repair01_w15_scope_axis', 'repair01_w15_space_writes',
    'repair01_w15_table_snapshot', 'repair01_w15_thresholds', 'repair01_w15_nav_moves',
    'repair01_w15_fixes', 'repair01_w15_journey', 'repair01_w15_sod', 'repair01_w15_states',
    'repair01_w15_deferred', 'repair01_w15_decisions', 'repair01_w15_sidebar', 'repair01_w15_scope',
);
foreach ($LEDGERS as $t) { $run("DROP TABLE IF EXISTS `$t`", $t); }

echo "\nرابطةُ المالكِ في السجلِّ المركزيّ ───────────────────────────\n";
$has = function ($name) use ($conn) {
    $r = $conn->query("SELECT 1 FROM information_schema.CHECK_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$name'");
    return $r && $r->num_rows > 0;
};
foreach (array('chk_grt_binding', 'chk_grt_not_workspace') as $c) {
    if ($has($c)) { $run("ALTER TABLE `gov_request_type` DROP CONSTRAINT `$c`", $c); }
}
foreach (array('owner_table', 'owner_service', 'projection_user_col') as $c) {
    $r = $conn->query("SHOW COLUMNS FROM `gov_request_type` LIKE '$c'");
    if ($r && $r->num_rows > 0) { $run("ALTER TABLE `gov_request_type` DROP COLUMN `$c`", "gov_request_type.$c"); }
}

echo "\n────────────────────────────────────────────────────────────\n";
printf("منزوع %d\n", $done);
exit(0);
