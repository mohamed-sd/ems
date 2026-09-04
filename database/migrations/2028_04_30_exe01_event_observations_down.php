<?php
/**
 * 2028_04_30_exe01_event_observations_down.php — عكسُ فصلِ القياسِ عن الحكم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العكسُ تامٌّ بلا فقدِ حقيقة**: الملاحظاتُ **قياسٌ يُعاد بناؤه** بتشغيلِ
 *   أداتِه، ولا يُشتقُّ منها حكمٌ ولا قيدٌ ولا أثرٌ ماليّ.
 *
 * ⛔ **ولا يمسُّ العكسُ `gov_event_rulings`**: الهجرةُ الأماميّةُ لم تُضف إليه
 *   عمودًا ولم تُعدِّل صفًّا — فما لم تُنشئه لا تحذفه.
 *
 * ◆ **وتُصدَّر الملاحظاتُ قبلَ الإسقاط**: لا لأنّها لا تُعاد — بل لأنَّ لقطتَها
 *   قد تكون مضت، فيُحفَظ الشاهدُ الزمنيُّ الذي لا يُعاد توليدُه.
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

$ex = $conn->query("SHOW TABLES LIKE 'gov_event_observations'");
if ($ex && $ex->num_rows > 0) {
    $res = $conn->query('SELECT * FROM `gov_event_observations` ORDER BY observation_id');
    $keep = array();
    while ($res && ($r = $res->fetch_assoc())) { $keep[] = $r; }
    if ($keep) {
        $f = __DIR__ . '/2028_04_30_exe01_event_observations.archive.json';
        file_put_contents($f, json_encode($keep, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        printf("  ⚠ صُدِّرت %d ملاحظةً إلى %s\n", count($keep), basename($f));
    }
}
if (!$conn->query('DROP VIEW IF EXISTS `v_event_ruling_current`')) { exit('⛔ ' . $conn->error . "\n"); }
echo "  − رؤية `v_event_ruling_current` أُسقطت\n";
if (!$conn->query('DROP TABLE IF EXISTS `gov_event_observations`')) { exit('⛔ ' . $conn->error . "\n"); }
echo "  − `gov_event_observations` أُسقط\n";
echo "\n◆ عُكست هجرةُ EXE-01 §4. و`gov_event_rulings` كما كان حرفًا.\n";
