<?php
/**
 * 2028_03_02_govui_space_own_not_forbidden_down.php — العكسُ من القيدِ لا من قائمةٍ ثانية
 * @migration-objects: restore gov_space_appearances from govui_space_fix_log
 * ◆ يردُّ كلَّ صفٍّ إلى `old_cls` و`old_owner` المسجَّلَين له بمعرِّفِه — فلا
 *   قائمةَ بدائلَ ثانيةٌ تُكتب هنا وتتفرّق عن الأصل [[fix-the-tool-not-the-output]].
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
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);
$q = $conn->query("SELECT appearance_id, old_cls, old_owner FROM govui_space_fix_log ORDER BY id DESC");
$n = 0;
while ($q && ($r = $q->fetch_assoc())) {
    $st = $conn->prepare("UPDATE gov_space_appearances SET cls = ?, owner_dept_ar = ?, rule_step = 6 WHERE id = ?");
    if (!$st) { continue; }
    $st->bind_param('ssi', $r['old_cls'], $r['old_owner'], $r['appearance_id']);
    $st->execute(); $st->close(); $n++;
}
$conn->query("DELETE FROM govui_space_fix_log");
echo "reverted {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
