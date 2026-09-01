<?php
/**
 * 2028_03_11_govui_duplicate_labels_down.php — العكسُ من القيدِ نفسِه
 * @migration-objects: restore nav_canonical / repair01_screen_registry / nav_items / modules
 * ◆ صفوفُ هذه الجولةِ تُميَّز بـ`target_id LIKE 'DUP-%'` في `govui_label_log`.
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
$q = $conn->query("SELECT id, store, store_key, old_label FROM govui_label_log
                    WHERE target_id LIKE 'DUP-%' ORDER BY id DESC");
$n = 0; $ids = array();
while ($q && ($r = $q->fetch_assoc())) {
    $st = null;
    switch ($r['store']) {
        case 'nav_canonical.canonical_ar':
            $st = $conn->prepare("UPDATE nav_canonical SET canonical_ar = ? WHERE LOWER(route) = LOWER(?)");
            $st->bind_param('ss', $r['old_label'], $r['store_key']); break;
        case 'nav_canonical.status':
            $st = $conn->prepare("UPDATE nav_canonical SET status = ?, merge_into = NULL WHERE LOWER(route) = LOWER(?)");
            $st->bind_param('ss', $r['old_label'], $r['store_key']); break;
        case 'repair01_screen_registry.canonical_label_ar':
            $st = $conn->prepare("UPDATE repair01_screen_registry SET canonical_label_ar = ? WHERE screen_id = ?");
            $st->bind_param('ss', $r['old_label'], $r['store_key']); break;
        case 'nav_items.label_ar':
            if (preg_match('~^id=(\d+)~', $r['store_key'], $m)) {
                $id = (int) $m[1];
                $st = $conn->prepare("UPDATE nav_items SET label_ar = ? WHERE id = ?");
                $st->bind_param('si', $r['old_label'], $id);
            }
            break;
        case 'modules.name':
            if (preg_match('~^id=(\d+)$~', $r['store_key'], $m)) {
                $id = (int) $m[1];
                $st = $conn->prepare("UPDATE modules SET name = ? WHERE id = ?");
                $st->bind_param('si', $r['old_label'], $id);
            }
            break;
    }
    if ($st) { $st->execute(); $st->close(); $n++; $ids[] = (int) $r['id']; }
}
if ($ids) { $conn->query("DELETE FROM govui_label_log WHERE id IN (" . implode(',', $ids) . ")"); }
echo "reverted {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
