<?php
/**
 * 2028_04_28_resync_gov_profile_items_down.php — عكسُ إعادةِ المزامنة
 * ◆ **العكسُ دقيقٌ لا يدويّ**: `item_id` تسلسليٌّ، والهجرةُ كتبت علامةَ الماءِ
 *   (أقصى معرِّفٍ قبلَ إدراجِها). فيحذف العكسُ ما **فوقَها** حصرًا — ولا يُمَسُّ
 *   صفٌّ سبق تلك اللحظة.
 * ⛔ **ويرفض العملَ بلا علامةِ ماء**: حذفٌ بلا حدٍّ مقروءٍ قد يمحو بذرًا أصليًّا،
 *   فالغيابُ يوقف العكسَ ولا يُخمَّن.
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ [[rpr0-migration-ledger-gate]].
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
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');

$stampFile = __DIR__ . '/2028_04_28_resync_gov_profile_items.watermark.json';
if (!is_file($stampFile)) {
    exit("✘ رُفض العكس: لا علامةَ ماءٍ في " . basename($stampFile) . " — والحذفُ بلا حدٍّ يمحو بذرًا أصليًّا.\n");
}
$stamp = json_decode((string) file_get_contents($stampFile), true);
if (!is_array($stamp) || !isset($stamp['watermark'])) {
    exit("✘ رُفض العكس: علامةُ الماءِ غيرُ مقروءة.\n");
}
$wm = (int) $stamp['watermark'];
echo "◆ علامةُ الماء: {$wm} · أُضيف وقتَها: " . (int) ($stamp['added'] ?? 0) . " بندًا\n";

$n = (int) $conn->query("SELECT COUNT(*) FROM gov_profile_items WHERE item_id > {$wm}")->fetch_row()[0];
echo "- بنودٌ فوقَ العلامةِ الآن: {$n}\n";
$conn->query("DELETE FROM gov_profile_items WHERE item_id > {$wm}");
echo "- حُذف: " . $conn->affected_rows . " بندًا\n";

@unlink($stampFile);
$conn->query("DELETE FROM `schema_migrations` WHERE `filename` = '2028_04_28_resync_gov_profile_items.php'");
echo '- قيدُ الدفتر: ' . $conn->affected_rows . "\n";
