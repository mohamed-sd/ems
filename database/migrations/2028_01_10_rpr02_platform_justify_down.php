<?php
/**
 * 2028_01_10_rpr02_platform_justify_down.php — عكسُ موضعِ حكمِ تبريرِ المنصّة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والعكسُ يُسقط الموضعَ ولا يردُّ الأحكامَ** — فالأحكامُ تُعاد بتشغيلِ
 *   `tools/rpr02_platform_justify.php --apply` على لقطتِها.
 * ◆ **وعودةُ الأسطحِ إلى نطاقاتِها في `repair01_screen_registry` تُردُّ هنا**:
 *   ما وُسم `verdict_rule='RPR02_S54_RETURN_TO_SCOPE'` يعود `PLATFORM_SHARED`
 *   — ⛔ **فالعكسُ يعكس فعلَ الهجرةِ كلَّه لا نصفَه**.
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

$back = 0;
if ($conn->query("UPDATE `repair01_screen_registry`
                     SET `ownership_verdict` = 'PLATFORM_SHARED', `verdict_rule` = '', `verdict_at` = NULL
                   WHERE `verdict_rule` = 'RPR02_S54_RETURN_TO_SCOPE'")) {
    $back = $conn->affected_rows;
}
echo "  ✔ رُدَّ إلى `PLATFORM_SHARED`: $back سطحًا\n";

if (!$conn->query("DROP TABLE IF EXISTS `repair01_platform_justification`")) {
    exit("✘ تعذّر الإسقاط: {$conn->error}\n");
}
echo "  ✔ أُسقط `repair01_platform_justification`\n";
