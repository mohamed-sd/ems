<?php
/**
 * 2028_01_13_rpr02_s3_nav_group_bind_down.php — تراجعُ ربطِ المجموعاتِ المعتمدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **والتراجعُ يردُّ المخزنَ إلى ما كان — من الجدولِ نفسِه لا من ذاكرةٍ ولا
 *   بإعادةِ تشغيلِ أداة**: لكلِّ صفٍّ `before_group_id` يُكتب في `nav_items`،
 *   ثمَّ تُسقَط المجموعاتُ التي أنشأها الربطُ **إن بقيت بلا ساكنٍ حيّ**
 *   (‏ومجموعةٌ سكنها بندٌ آخرُ بعدَ الربطِ لا تُحذف — الحذفُ يُتِّم بندًا حيًّا).
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

$made = array(); $back = 0;
$q = @$conn->query("SELECT nav_item_id, before_group_id, after_group_id, group_created
                      FROM repair01_nav_group_bind WHERE applied_at IS NOT NULL");
while ($q && ($x = $q->fetch_assoc())) {
    $bg = ($x['before_group_id'] === null) ? 'NULL' : (int) $x['before_group_id'];
    if ($conn->query("UPDATE `nav_items` SET `group_id` = $bg WHERE `id` = " . (int) $x['nav_item_id'])) {
        $back += $conn->affected_rows;
    }
    if ((int) $x['group_created'] === 1) { $made[(int) $x['after_group_id']] = 1; }
}
echo "  ✔ رُدَّ إلى ما قبلَ الربط: $back بندًا\n";

$dropped = 0; $kept = 0;
foreach (array_keys($made) as $gid) {
    $c = (int) $conn->query("SELECT COUNT(*) FROM `nav_items` WHERE `group_id` = $gid")->fetch_row()[0];
    if ($c > 0) { $kept++; continue; }
    if ($conn->query("DELETE FROM `link_groups` WHERE `id` = $gid")) { $dropped += $conn->affected_rows; }
}
echo "  ✔ أُسقطت مجموعاتٌ أُنشئت وبقيت بلا ساكن: $dropped · وأُبقيت مسكونةٌ: $kept\n";

if (!$conn->query("DROP TABLE IF EXISTS `repair01_nav_group_bind`")) {
    exit("✘ تعذّر الإسقاط: {$conn->error}\n");
}
echo "  ✔ أُسقط `repair01_nav_group_bind`\n";
