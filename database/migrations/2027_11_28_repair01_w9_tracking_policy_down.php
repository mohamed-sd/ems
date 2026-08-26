<?php
/**
 * 2027_11_28_repair01_w9_tracking_policy_down.php — تراجعُ سياسةِ التتبّع
 * ═══════════════════════════════════════════════════════════════════════════
 * ⚠ **يُسقط سجلَّ السياسةِ والدفعاتِ والأرقامِ التسلسليّةِ وبياناتِها** — ولا
 *   يُشغَّل إلّا بقصدِ التراجعِ الكامل عن جوابِ `DEC-OPEN-15`.
 * ◆ **والأعلامُ الثنائيّةُ من W09 لا تُنزَع هنا** — بوّاباتُ W09 تقرؤها،
 *   ونزعُها يُسقط مرحلةً مُغلَقة. تراجعُها في هجرةِ W09 وحدَها.
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

$done = 0; $err = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; }
    else { echo "  ✘ $label — " . $conn->error . "\n"; $err++; }
};
$dropCol = function ($t, $c) use ($conn, &$done, &$err) {
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    if (!$r || $r->num_rows === 0) { echo "  ↷ $t.$c غير موجود\n"; return; }
    if ($conn->query("ALTER TABLE `$t` DROP COLUMN `$c`") === true) { echo "  ✔ $t.$c\n"; $done++; }
    else { echo "  ✘ $t.$c — " . $conn->error . "\n"; $err++; }
};

echo "══ تراجعُ سياسةِ التتبّع (DEC-OPEN-15) ══\n\n";
foreach (array('proc_requalification', 'proc_expiry_override', 'proc_track_gap',
               'proc_serial', 'proc_lot', 'proc_track_policy') as $t) {
    $run("DROP TABLE IF EXISTS `$t`", $t);
}
echo "\nالأعمدةُ المحلولةُ على الصنف ──────────────────────────────────\n";
foreach (array('track_lot_level', 'track_serial_level', 'track_mfg_level', 'track_expiry_level',
               'track_warranty_level', 'expiry_enforce', 'issue_policy', 'requalify',
               'policy_scope', 'policy_version') as $c) { $dropCol('proc_item', $c); }
foreach (array('lot_id', 'mfg_date', 'warranty_until') as $c) { $dropCol('proc_receipt_line', $c); }

echo "\n───────────────────────────────────────────────────────────────\n";
echo "الخلاصة: نُفِّذ $done · أخطاء $err\n";
exit($err > 0 ? 1 : 0);
