<?php
/**
 * 2027_11_27_repair01_w10_split_down.php — تراجعُ المرحلةِ العاشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⚠ **يُسقط ثمانيةَ سجلّاتٍ وبياناتِها** — ولا يُشغَّل إلّا بقصدِ التراجعِ الكامل.
 * وقبله يُشغَّل `php tools/repair01_w10_apply.php --revert` لإرجاعِ الملكيّاتِ
 * إلى ما كانت عليه؛ فهذا الملفُّ يهدم السجلَّ ولا يعرف ما كان قبله.
 *
 * ⛔ **ولا يُمَسُّ جدولٌ حيٌّ هنا** — المرحلةُ لم تُنشئ عمودًا في جدولِ أعمالٍ ولا
 *   حذفت صفًّا منه: الشقُّ قرارُ ملكيّةٍ في سجلِّ الحملةِ والجسرُ يترجم.
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

$tables = array(
    'repair01_w10_journey', 'repair01_w10_decisions', 'repair01_w10_sod',
    'repair01_w10_states', 'repair01_w10_sidebar', 'repair01_w10_bridge',
    'repair01_w10_split', 'repair01_w10_thresholds', 'repair01_w10_vocab',
);
$done = 0; $err = 0;
foreach ($tables as $t) {
    if ($conn->query("DROP TABLE IF EXISTS `$t`") === true) { echo "  ✔ أُسقط $t\n"; $done++; }
    else { echo "  ✘ $t — " . $conn->error . "\n"; $err++; }
}
echo "───────────────────────────────────────────────────────────────\n";
echo "التراجع: أُسقط $done · أخطاء $err\n";
exit($err > 0 ? 1 : 0);
