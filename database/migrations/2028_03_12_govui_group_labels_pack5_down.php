<?php
/**
 * 2028_03_12_govui_group_labels_pack5_down.php — العكسُ من القيدِ نفسِه
 * @migration-objects: restore nav_lifecycle_groups.label_ar from govui_label_log
 * ◆ يُميَّز صفُّ هذه الدفعةِ بمخزنِه وبسببِه (`GOV_UI_RELABEL`) — فلا يُعكَس
 *   معه نزعُ `(Overview)` الذي كتبته دفعةٌ سابقةٌ في المخزنِ نفسِه.
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
$q = $conn->query("SELECT id, store_key, old_label FROM govui_label_log
                    WHERE store = 'nav_lifecycle_groups.label_ar'
                      AND reason LIKE 'GOV_UI_RELABEL%' ORDER BY id DESC");
$n = 0; $ids = array();
while ($q && ($r = $q->fetch_assoc())) {
    $st = $conn->prepare("UPDATE nav_lifecycle_groups SET label_ar = ? WHERE id = ?");
    if (!$st) { continue; }
    $gid = (int) $r['store_key'];
    $st->bind_param('si', $r['old_label'], $gid);
    $st->execute(); $st->close(); $n++; $ids[] = (int) $r['id'];
}
if ($ids) { $conn->query("DELETE FROM govui_label_log WHERE id IN (" . implode(',', $ids) . ")"); }
echo "reverted {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
