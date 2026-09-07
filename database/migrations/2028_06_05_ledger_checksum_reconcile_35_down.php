<?php
/**
 * تراجعُ 2028_06_05 — يُعيد البصماتِ **من سجلِّ الأثرِ لا من تخمين**، ويقتصر
 * على صفوفِ هذه الجولةِ بشاهدِها فلا يمسُّ مصالحاتِ السابقةِ الأربع.
 * التشغيل: php database/migrations/2028_06_05_ledger_checksum_reconcile_35_down.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$t0 = microtime(true);

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

/* ⛔ **الرمزُ تاريخُ الجولةِ لا نصُّها**: نصُّ الشاهدِ قد يُصقَل بين تشغيلٍ
     وكتابةِ الملفِّ (وقع مقيسًا: صولحت 35 بنصٍّ ثمَّ صار للملفِّ نصٌّ آخرُ
     فأرجع التراجعُ **صفرًا**) — فالمطابقةُ على `2028-06-05` وهو ثابتٌ في
     كلِّ صياغةٍ لهذه الجولة، ولا يشترك فيه صفٌّ من جولةٍ أخرى. */
$REF = 'CTL · ';
$DATE = '2028-06-05';
$q = $conn->prepare("SELECT filename, old_checksum FROM `repair01_ledger_checksum_fix`
                      WHERE witness LIKE CONCAT('%', ?, '%') AND witness LIKE CONCAT('%', ?, '%')");
$q->bind_param('ss', $REF, $DATE);
$q->execute();
$res = $q->get_result();

$upd = $conn->prepare("UPDATE `schema_migrations` SET `checksum` = ? WHERE `filename` = ?");
$del = $conn->prepare("DELETE FROM `repair01_ledger_checksum_fix` WHERE `filename` = ?");
$n = 0;
while ($r = $res->fetch_assoc()) {
    $upd->bind_param('ss', $r['old_checksum'], $r['filename']);
    $upd->execute();
    $del->bind_param('s', $r['filename']);
    $del->execute();
    $n++;
}
$q->close(); $upd->close(); $del->close();
echo "  ✔ أُعيدت {$n} بصمةً إلى قيمتِها السابقة · وحُذف أثرُها\n";
