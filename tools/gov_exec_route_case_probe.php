<?php
/**
 * tools/gov_exec_route_case_probe.php — `DC-19` مقياسُ حالةِ أحرفِ المسار
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يُقاس بالقرصِ لا بالعُرف**: يقرأ أسماءَ المجلداتِ الحقيقيّةَ ثمَّ يطابق
 *   بادئةَ كلِّ مسارِ موضعٍ **حرفًا بحرف**. فالحكمُ من الملفِّ لا من قاعدةٍ
 *   ذهنيّةٍ عن «المجلداتُ تبدأ بحرفٍ كبير».
 *
 * ◆ **ولماذا لا يظهر العطبُ هنا**: ويندوز لا يفرّق الحالةَ فيُحلُّ المساران
 *   إلى الملفِّ نفسِه — فالمقياسُ **يُثبت الغائبَ عن التشغيل** الذي يظهر عند
 *   النشرِ على نظامِ ملفاتٍ حسّاسٍ للحالة. ⛔ وصفرُ الأعطالِ على ويندوز ليس برهانًا.
 *
 * ◆ **والمقامُ معلَنٌ**: كلُّ مسارِ موضعٍ حيٍّ فيه مجلد. والملفُّ المفقودُ يُفرَز
 *   صنفًا مستقلًّا — فغيابُ ملفٍّ عطبٌ آخرُ لا انزياحُ حالة.
 *
 * التشغيل: php tools/gov_exec_route_case_probe.php [--list]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$LIST = in_array('--list', $argv, true);
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$disk = array();
foreach (scandir($ROOT) as $d) {
    if ($d === '.' || $d === '..' || !is_dir($ROOT . '/' . $d)) { continue; }
    $disk[strtolower($d)] = $d;
}
$tot = 0; $drift = array(); $noDir = array(); $noFile = array();
$q = $conn->query("SELECT DISTINCT route FROM nav_placements
                    WHERE active = 1 AND route IS NOT NULL AND route LIKE '%/%'");
while ($r = $q->fetch_row()) {
    $route = (string) $r[0];
    $tot++;
    $dir = substr($route, 0, strpos($route, '/'));
    $lc  = strtolower($dir);
    if (!isset($disk[$lc])) { $noDir[] = $route; continue; }
    if ($disk[$lc] !== $dir) { $drift[$disk[$lc]][] = $route; }
    if (!is_file($ROOT . '/' . $disk[$lc] . substr($route, strlen($dir)))) { $noFile[] = $route; }
}
$n = 0;
foreach ($drift as $g) { $n += count($g); }
printf("═ DC-19 · حالةُ أحرفِ المسار ═\n");
printf("  المقام: %d مسارَ موضعٍ حيٍّ بمجلد\n", $tot);
printf("  منزاحُ الحالةِ عن القرص: **%d** على %d مجلدًا\n", $n, count($drift));
printf("  بلا مجلدٍ على القرص: %d · بلا ملفّ: %d\n", count($noDir), count($noFile));
if ($LIST) {
    foreach ($drift as $real => $g) {
        printf("  · %-16s (%d) مثال: %s\n", $real, count($g), $g[0]);
    }
    foreach ($noFile as $x) { printf("  ✘ بلا ملفّ: %s\n", $x); }
}
/* قيدُ القياسِ في الدفتر — العدّادُ يُحدَّث بالقياسِ لا باليد */
$st = $conn->prepare("UPDATE repair01_debt_register
                         SET measured_count = ?, measured_at = NOW() WHERE class_code = 'DC-19'");
if ($st) { $st->bind_param('i', $n); $st->execute(); $st->close(); }
exit($n > 0 ? 0 : 0);
