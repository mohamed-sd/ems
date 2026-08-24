<?php
/**
 * 2027_11_13_repair01_stage_no.php
 * ═══════════════════════════════════════════════════════════════════════════
 * REPAIR01 — عمودُ المرحلةِ في سجلِّ المتطلَّبات.
 *
 * ◆ ملفُّ مرحلةٍ يقول «١١٩ متطلَّبًا» ولا يقول **أيَّها** يُجبر الجلسةَ على
 *   اشتقاقِ نطاقِها بنفسِها — وهو أضيعُ ما يضيع في يومِ تنفيذ. فيُسنَد كلُّ
 *   متطلَّبٍ إلى مرحلتِه بقاعدةٍ صريحةٍ في `tools/repair01_stage_assign.php`،
 *   ويُطبَع النطاقُ حرفًا في ملفِّ المرحلة.
 * ◆ يبقى NULL مسموحًا: متطلَّبٌ جديدٌ يدخل بلا مرحلةٍ فيلتقطه الإسنادُ لاحقًا،
 *   والبوّابةُ تُسقط إن بقي بلا إسنادٍ عند الإغلاق.
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

$has = $conn->query("SHOW COLUMNS FROM `repair01_requirements` LIKE 'stage_no'");
if ($has && $has->num_rows > 0) { exit("= stage_no قائمٌ سلفًا\n"); }

if ($conn->query("ALTER TABLE `repair01_requirements`
                    ADD COLUMN `stage_no` TINYINT UNSIGNED NULL AFTER `wave`,
                    ADD KEY `k_stage` (`stage_no`)") === false) {
    exit("✘ {$conn->error}\n");
}
echo "✔ stage_no أُضيف إلى repair01_requirements\n";
