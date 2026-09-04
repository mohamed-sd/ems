<?php
/**
 * 2028_05_08_perm01_change_log_down.php — عكسُ سجلِّ أثرِ التغيير
 * ◆ **عكسٌ نظيفٌ بلا علامةِ ماء**: الهجرةُ أنشأت جدولًا واحدًا ولم تكتب في
 *   جدولٍ قائمٍ حرفًا — فإسقاطُه يُعيد القاعدةَ إلى ما كانت.
 * ⚠ **وإسقاطُه يُفقد الأثرَ المكتوبَ فيه**: فإن كان قد سُجِّل تغييرٌ حقيقيٌّ
 *   فالعكسُ يمحوه. ولذلك يُطبَع عددُ الصفوفِ قبلَ الإسقاطِ — قرارٌ يُرى لا يُغفَل.
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ.
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
if ($conn->connect_errno) { exit("connect fail: " . $conn->connect_error . "\n"); }
$conn->set_charset('utf8mb4');

$r = @$conn->query("SELECT COUNT(*) FROM `perm_change_log`");
$n = $r ? (int) $r->fetch_row()[0] : 0;
echo "- أثرٌ مسجَّلٌ سيُفقد بالإسقاط: {$n} صفًّا\n";
$conn->query("DROP TABLE IF EXISTS `perm_change_log`");
echo "- جدولٌ أُسقط: perm_change_log\n";
$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` IN ('2028_05_08_perm01_change_log.php',
                                    '2028_05_08_perm01_change_log_down.php')");
echo "- قيدا الدفتر: " . $conn->affected_rows . "\n\nعُكست.\n";
