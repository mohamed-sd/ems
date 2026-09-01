<?php
/**
 * 2028_03_04_govui_canonical_labels_down.php — العكسُ من القيدِ لا من قائمةٍ ثانية
 * @migration-objects: restore nav_targets/nav_canonical/repair01_screen_registry/nav_items/modules
 * ◆ كلُّ صفٍّ يعود إلى `old_label` المسجَّلِ له بمخزنِه ومفتاحِه — والمفتاحُ
 *   مكتوبٌ في القيدِ نفسِه فلا تُعاد قراءةُ الملفِّ الحاكمِ هنا.
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
$q = $conn->query("SELECT target_id, store, store_key, old_label FROM govui_label_log
                    WHERE store <> 'SKIPPED' ORDER BY id DESC");
$n = 0;
while ($q && ($r = $q->fetch_assoc())) {
    $ok = false;
    switch ($r['store']) {
        case 'nav_targets.canonical_title':
            $st = $conn->prepare("UPDATE nav_targets SET canonical_title = ? WHERE target_id = ?");
            $st->bind_param('ss', $r['old_label'], $r['store_key']); $ok = $st->execute(); $st->close();
            break;
        case 'nav_canonical.canonical_ar':
            $st = $conn->prepare("UPDATE nav_canonical SET canonical_ar = ? WHERE LOWER(route) = LOWER(?)");
            $st->bind_param('ss', $r['old_label'], $r['store_key']); $ok = $st->execute(); $st->close();
            break;
        case 'repair01_screen_registry.canonical_label_ar':
            $st = $conn->prepare("UPDATE repair01_screen_registry SET canonical_label_ar = ? WHERE screen_id = ?");
            $st->bind_param('ss', $r['old_label'], $r['store_key']); $ok = $st->execute(); $st->close();
            break;
        case 'nav_items.label_ar':
            if (preg_match('~^id=(\d+)~', $r['store_key'], $m)) {
                $id = (int) $m[1];
                $st = $conn->prepare("UPDATE nav_items SET label_ar = ? WHERE id = ?");
                $st->bind_param('si', $r['old_label'], $id); $ok = $st->execute(); $st->close();
            }
            break;
        case 'modules.name':
            if (preg_match('~^id=(\d+)$~', $r['store_key'], $m)) {
                $id = (int) $m[1];
                $st = $conn->prepare("UPDATE modules SET name = ? WHERE id = ?");
                $st->bind_param('si', $r['old_label'], $id); $ok = $st->execute(); $st->close();
            }
            break;
    }
    if ($ok) { $n++; }
}
$conn->query("DELETE FROM govui_label_log");
echo "reverted {$n}\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
