<?php
/**
 * 2028_05_14_perm01_tickets_identity_down.php — إعادةُ هويّةِ البلاغاتِ القديمة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⚠ **والتراجعُ يُعيد الافتراق**: القائمةُ تُظهر بهويّةٍ والبابُ يُنفِذ بأخرى،
 *   ويعود `DIRECT_URL_AUTH_MISMATCH` إلى واحدٍ بعدَ صفر. لا يُتراجَع إلّا
 *   لعزلِ عطبٍ ثمَّ يُعاد التشغيلُ فورًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit('connect fail: ' . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$oldId = (int) $conn->query("SELECT id FROM modules WHERE code='Tickets/dept_inbox.php' LIMIT 1")->fetch_row()[0];
if ($oldId <= 0) { exit("لا وحدة للشاشة المدمجة — لا تراجع.\n"); }
$conn->query("UPDATE nav_items n JOIN modules m ON m.id = n.module_id
                 SET n.module_id = {$oldId}
               WHERE n.active = 1 AND n.route LIKE 'Tickets/tickets_list.php%'
                 AND m.code = 'Tickets/tickets_list.php'");
printf("بنودٌ أُعيدت: %d\n", $conn->affected_rows);

foreach (array('2028_05_14_perm01_tickets_identity.php', basename(__FILE__)) as $f) {
    $conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '" . $conn->real_escape_string($f) . "'");
}
echo "تم التراجع — وعاد الافتراق.\n";
