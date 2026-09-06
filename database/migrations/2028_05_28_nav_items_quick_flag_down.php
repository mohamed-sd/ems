<?php
/**
 * 2028_05_28_nav_items_quick_flag_down.php — عكسُ رايةِ الوصولِ السريع
 * ═══════════════════════════════════════════════════════════════════════════
 * ① الصفوفُ الساكنةُ التي أنشأتها الجولةُ تُحذف — وتُميَّز بحدَّين معًا:
 *    `active = 0` و`is_quick = 1` و`sort_order = 900`، فلا يُحذف صفٌّ سبقها.
 * ② العمودُ يُسقَط (الرايةُ كلُّها معه).
 * ③ إذنُ الكتابةِ يعود صفرًا — والعرضُ يبقى كما تركته جولةُ 2028_05_27.
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
$one = function ($s) use ($conn) { $r = $conn->query($s); return $r ? (int) ($r->fetch_row()[0] ?? 0) : -1; };

$CODE = 'Settings/links_control.php';
$MARK = 'links_control:2028_05_27';

$has = $one("SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nav_items' AND COLUMN_NAME = 'is_quick'");
if ($has > 0) {
    $conn->query("DELETE FROM nav_items WHERE active = 0 AND is_quick = 1 AND sort_order = 900");
    printf("   ① صفوفٌ ساكنةٌ محذوفة: %d\n", $conn->affected_rows);
    if (!$conn->query('ALTER TABLE nav_items DROP COLUMN is_quick')) {
        exit('⛔ تعذّر إسقاطُ العمود: ' . $conn->error . "\n");
    }
    echo "   ② أُسقط العمود\n";
} else {
    echo "   ①② لا عمودَ — لا شيءَ يُعكس\n";
}

$mid = $one("SELECT id FROM modules WHERE code = '{$CODE}' ORDER BY id LIMIT 1");
if ($mid > 0) {
    $conn->query("UPDATE role_permissions SET can_edit = 0 WHERE module_id = {$mid}");
    printf("   ③ الطبقةُ القائمة: %d صفًّا\n", $conn->affected_rows);
}
$conn->query("UPDATE gov_profile_items SET can_edit = 0 WHERE seeded_from = '{$MARK}'");
printf("   ③ طبقةُ القوالب: %d بندًا\n", $conn->affected_rows);

$left = $one("SELECT COUNT(*) FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nav_items' AND COLUMN_NAME = 'is_quick'");
printf("   المتبقّي: %d (المستهدف صفر)\n", $left);
if ($left > 0) { exit("⛔ العكسُ لم يكتمل.\n"); }

$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` = '2028_05_28_nav_items_quick_flag.php'");
echo "✔ عُكس.\n";
