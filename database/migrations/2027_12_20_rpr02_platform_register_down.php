<?php
/**
 * 2027_12_20_rpr02_platform_register_down.php — إعادةُ `PLATFORM` إلى جدولِ الإدارات
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والإعادةُ تُفسد مقامَين مرّةً أخرى** — `repair01_w135_gate` G1 يرسُب،
 *   و`baseline_xlsx_build.php:154` يطبع ٢٢ تحت عنوانٍ يُعدِّد ٢١.
 *   فلا تُشغَّل هذه إلّا **بقرارِ مالكٍ صريحٍ ينسخ `RPR-02` §٥·٢**.
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

$r = @$conn->query("SELECT name_ar FROM repair01_platform_capabilities
                     WHERE capability_code = 'PLATFORM'");
if ($r && $r->num_rows) {
    $n = $r->fetch_row()[0];
    $st = $conn->prepare("INSERT IGNORE INTO repair01_departments
        (canonical_code, display_order, name_ar, sector, parent_code, note)
        VALUES ('PLATFORM', NULL, ?, 'OUTSIDE', '', 'اعيد بنقض 2027_12_20')");
    $st->bind_param('s', $n);
    $st->execute();
    echo "  ✔ أُعيد الصفُّ — ⛔ والمقامُ صار ٢٢ ثانيةً\n";
}
$conn->query("DROP TABLE IF EXISTS `repair01_platform_capabilities`");
echo "✔ نُقض النقلُ — ولا يُقرأ هذا إصلاحًا\n";
