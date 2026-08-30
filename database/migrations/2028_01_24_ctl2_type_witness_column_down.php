<?php
/**
 * 2028_01_24_ctl2_type_witness_column_down.php — التراجع: نزعُ عمودِ الشاهد
 * ◆ يمحو العمودَ وما فيه — والأنواعُ نفسُها (`requirement_type`) لا تُمَسُّ
 *   هنا؛ ردُّها بتصفيرِ ما شاهدُه من هذه الجولةِ قبل النزع.
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

$has = $conn->query("SHOW COLUMNS FROM `repair01_requirements` LIKE 'type_witness'");
if ($has && $has->num_rows > 0) {
    /* ردُّ أنواعِ هذه الجولةِ أوّلًا — ما شاهدُه من عمودِ الجولةِ يعود بلا نوع */
    $conn->query("UPDATE `repair01_requirements`
        SET requirement_type = NULL, proof_contract = ''
      WHERE type_witness IS NOT NULL AND type_witness <> ''");
    printf("  ✔ رُدَّ نوعُ %d متطلبًا صُنِّف بهذه الجولة\n", $conn->affected_rows);
    $ok = $conn->query("ALTER TABLE `repair01_requirements` DROP COLUMN `type_witness`");
    if (!$ok) { exit("✘ {$conn->error}\n"); }
    echo "  ✔ نُزع `type_witness`\n";
} else {
    echo "  ✔ العمودُ غيرُ موجودٍ — لا فعل\n";
}
echo "\n✔ تراجعُ عمودِ الشاهدِ تامّ\n";
