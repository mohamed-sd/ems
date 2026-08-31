<?php
/**
 * 2028_01_30_final_close_owner_assign_down.php — عكسُ إغلاقِ الملكيّة
 * ═══════════════════════════════════════════════════════════════════════════
 * يردُّ `owner_code`/`owner_rule` القبليَّين لكلِّ صفٍّ من `repair01_owner_close`
 * ويحذف صفوفَ القدراتِ المنصّيّةِ المدرَجةَ بهذه الجولةِ (الموسومةَ
 * `FINAL_CLOSE-3` في `moved_from`) ثمَّ يُسقط الجدولَ ويمحو قيدَ الدفتر.
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

$r = $conn->query("SELECT screen_id, before_owner, before_rule FROM repair01_owner_close");
if ($r) {
    $st = $conn->prepare("UPDATE repair01_screen_registry SET owner_code=?, owner_rule=? WHERE screen_id=?");
    $n = 0;
    while ($x = $r->fetch_assoc()) {
        $st->bind_param('sss', $x['before_owner'], $x['before_rule'], $x['screen_id']);
        if ($st->execute()) $n += $st->affected_rows;
    }
    echo "  ✔ رُدَّ owner_code القبليُّ لـ$n صفًّا\n";
}
$conn->query("DELETE FROM repair01_platform_capabilities WHERE moved_from='FINAL_CLOSE-3'");
echo "  ✔ حُذف من القدراتِ المنصّيّة: " . $conn->affected_rows . "\n";
$conn->query("DROP TABLE IF EXISTS repair01_owner_close");
$conn->query("DELETE FROM gov_migration_settlement WHERE filename='2028_01_30_final_close_owner_assign.php'");
$conn->query("DELETE FROM schema_migrations WHERE filename='2028_01_30_final_close_owner_assign.php'");
echo "  ✔ أُسقط الجدولُ ومُحي قيدُ الدفتر\n";
