<?php
/**
 * 2028_01_20_ctl_event_registries_down.php — تراجعُ سجلَّي الأحداث
 * ⛔ الجدولان مشتقّان من المخزنِ الحيِّ — إسقاطُهما لا يفقد حقيقةً أصليّةً،
 *   ويُعادان بإعادةِ الهجرةِ والأداة. **إلّا `replayed`**: صفٌّ صُرِّف فعلًا
 *   لا يُسقط جدولُه — والحارسُ أدناه يرفض.
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

$r = @$conn->query("SELECT COALESCE(SUM(replayed),0) FROM repair01_backlog_disposition");
if ($r && ($x = $r->fetch_row()) && (int) $x[0] > 0) {
    exit("⛔ **{$x[0]} حدثًا صُرِّف فعلًا** — سجلُّ تصريفٍ وقع أثرُه لا يُسقط\n");
}
$conn->query("DROP TABLE IF EXISTS `repair01_event_effect_crosswalk`");
$conn->query("DROP TABLE IF EXISTS `repair01_backlog_disposition`");
echo "  ✔ أُسقط السجلّان — ولم يكن فيهما تصريفٌ واقع\n";
