<?php
/**
 * 2027_12_21_w00_reanchor_departments_outside_down.php — إعادةُ المرساةِ إلى ٥
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **ولا تُعاد إلّا مع نقضِ `2027_12_20`**: مرساةٌ عند ٥ وجدولٌ فيه ٤ تجعل
 *   `G0-05` أحمرَ بلا سببٍ حقيقيّ — **فالنقضُ الجزئيُّ أسوأُ من عدمِه**.
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

$r = $conn->query("SELECT COUNT(*) FROM repair01_departments WHERE canonical_code = 'PLATFORM'");
if ($r && (int) $r->fetch_row()[0] === 0) {
    echo "⚠ **`PLATFORM` ما يزال منقولًا** — وإعادةُ المرساةِ إلى ٥ وحدَها\n"
       . "  تجعل `G0-05` أحمرَ بلا سببٍ حقيقيّ. انقضْ `2027_12_20` أوّلًا.\n";
}
$why = 'نقض 2027_12_21 — واعادة المرساة الى قيمة ما قبل نقل PLATFORM';
$pkg = 'نقض يدوي';
$v = 5;
$st = $conn->prepare("UPDATE repair01_w00_anchor
    SET anchor_value = ?, package_ref = ?, why = ?, anchored_at = NOW(),
        anchored_by = '2027_12_21_..._down.php'
    WHERE metric = 'departments_outside'");
$st->bind_param('iss', $v, $pkg, $why);
$st->execute();
echo "✔ أُعيدت المرساةُ إلى ٥\n";
