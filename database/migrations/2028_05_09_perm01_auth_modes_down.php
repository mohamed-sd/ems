<?php
/**
 * 2028_05_09_perm01_auth_modes_down.php — عكسُ سجلِّ أوضاعِ الانتقال
 * ◆ عكسٌ نظيفٌ بلا علامةِ ماء: جدولٌ واحدٌ أُنشئ ولم يُكتب في قائمٍ حرفًا.
 * ⚠ وإسقاطُه يُعيد السقوطَ إلى الجدولِ القديمِ ممكنًا — فالقارئُ يعامل غيابَ
 *   السجلِّ معاملةَ «لا وضعَ معلَن» ويسقط كما كان. قرارٌ يُرى لا يُغفَل.
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
$r = @$conn->query("SELECT COUNT(*) FROM `perm01_auth_mode`");
echo "- أوضاعٌ ستُفقد: " . ($r ? (int) $r->fetch_row()[0] : 0) . "\n";
$conn->query("DROP TABLE IF EXISTS `perm01_auth_mode`");
echo "- جدولٌ أُسقط: perm01_auth_mode\n";
$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` IN ('2028_05_09_perm01_auth_modes.php',
                                    '2028_05_09_perm01_auth_modes_down.php')");
echo "- قيدا الدفتر: " . $conn->affected_rows . "\n\nعُكست.\n";
