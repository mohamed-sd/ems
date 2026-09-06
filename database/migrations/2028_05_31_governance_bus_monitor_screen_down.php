<?php
/**
 * 2028_05_31_governance_bus_monitor_screen_down.php — عكسُ تسجيلِ شاشةِ النسخ.
 * ═══════════════════════════════════════════════════════════════════════════
 * يُزيل السجلّاتِ الأربعةَ التي أنشأتها الهجرةُ الصاعدةُ ولا يمسُّ الملفَّات.
 * ⛔ **ولا يحذف نسخةً احتياطيّةً واحدة**: ملفّاتُ `storage/backups` بياناتُ
 *   استعادةٍ لا أثرُ تسجيل — وعكسُ هجرةٍ لا يجوز أن يُتلف ما يحمي من الإتلاف.
 * ═══════════════════════════════════════════════════════════════════════════
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

$CODE = 'Governance/bus_monitor.php';
$c    = $conn->real_escape_string($CODE);

$mid = null;
if ($r = $conn->query("SELECT id FROM modules WHERE code='{$c}' LIMIT 1")) {
    $row = $r->fetch_row();
    if ($row) { $mid = (int) $row[0]; }
}

$conn->query("DELETE FROM gov_profile_items WHERE item_kind='screen' AND item_ref='{$c}'");
echo "  ✔ gov_profile_items — حُذف {$conn->affected_rows} صفًّا\n";

if ($mid !== null) {
    $conn->query("DELETE FROM role_permissions WHERE module_id={$mid}");
    echo "  ✔ role_permissions — حُذف {$conn->affected_rows} صفًّا\n";
}

$conn->query("DELETE FROM nav_items WHERE route='{$c}'");
echo "  ✔ nav_items — حُذف {$conn->affected_rows} صفًّا\n";

$conn->query("DELETE FROM modules WHERE code='{$c}'");
echo "  ✔ modules — حُذف {$conn->affected_rows} صفًّا\n";

echo "══ عُكس\n";
