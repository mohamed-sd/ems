<?php
/**
 * 2027_11_16_repair01_w2_registry_down.php
 * ═══════════════════════════════════════════════════════════════════════════
 * تراجعُ W02 — نزعُ السجلِّ المعياريِّ ومرجعِه ودفترِ قراراتِ المرحلة.
 *
 * ◆ يُشغَّل ضمنَ تسلسلِ التراجعِ الموثَّقِ في `W02_CLOSURE.md §١٠`، ولا يُشغَّل
 *   منفردًا: بوّابةُ W02 تقرأ هذه الجداولَ فتسقط بعد نزعِها — وهذا مقصود.
 *
 * ◆ **ولا يمسُّ الحيَّ**: صفوفُ `gov_screen_cycle` و`nav_items` التي كتبها
 *   إسقاطُ المرحلةِ تُرجَع بـ`php tools/repair01_w2_project.php --revert`
 *   لا بهذه الهجرة — لأنَّها **بياناتُ تشغيلٍ حيّةٌ** لا بنيةَ حملة، وحذفُ
 *   جدولٍ لا يعيد صفًّا كان قبله.
 *
 * ◆ **والصفوفُ المنقولةُ إلى `repair01_target_gaps`** تُميَّز بـ`origin_stage='W02'`
 *   فتُحذف وحدَها ويبقى الـ١٧٤ الأصليّ — لا مسحَ للدفترِ كلِّه.
 *
 * التشغيل: php database/migrations/2027_11_16_repair01_w2_registry_down.php
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

function w2d_has_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$gone = 0; $skip = 0; $err = 0;

/* ① الصفوفُ المنقولةُ إلى دفترِ الفجواتِ بهذه المرحلة — بوسمِها لا بالجملة */
if (w2d_has_col($conn, 'repair01_target_gaps', 'origin_stage')) {
    if ($conn->query("DELETE FROM `repair01_target_gaps` WHERE `origin_stage` = 'W02'") === false) {
        $err++; echo "✘ حذفُ فجواتِ W02 : {$conn->error}\n";
    } else { echo "✔ فجواتُ W02 المنقولةُ: {$conn->affected_rows} صفًّا\n"; }
}

/* ② أعمدةُ الوسمِ في دفترِ الفجوات */
foreach (array('origin_stage', 'origin_note', 'wave_stage') as $c) {
    if (!w2d_has_col($conn, 'repair01_target_gaps', $c)) { $skip++; echo "= repair01_target_gaps.$c (غيرُ موجود)\n"; continue; }
    if ($conn->query("ALTER TABLE `repair01_target_gaps` DROP COLUMN `$c`") === false) {
        $err++; echo "✘ repair01_target_gaps.$c : {$conn->error}\n";
    } else { $gone++; echo "✔ نُزع repair01_target_gaps.$c\n"; }
}

/* ③ مرجعُ دفترِ الأسطحِ */
if (!w2d_has_col($conn, 'repair01_surfaces', 'screen_id')) { $skip++; echo "= repair01_surfaces.screen_id (غيرُ موجود)\n"; }
elseif ($conn->query("ALTER TABLE `repair01_surfaces` DROP COLUMN `screen_id`") === false) {
    $err++; echo "✘ repair01_surfaces.screen_id : {$conn->error}\n";
} else { $gone++; echo "✔ نُزع repair01_surfaces.screen_id\n"; }

/* ④ الجدولان — `ems_app` بلا DROP؛ الهجرةُ بمستخدمِ الهجرات وحدَه */
foreach (array('repair01_screen_registry', 'repair01_w2_decisions') as $t) {
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    if (!$r || $r->num_rows === 0) { $skip++; echo "= $t (غيرُ موجود)\n"; continue; }
    if ($conn->query("DROP TABLE `$t`") === false) { $err++; echo "✘ $t : {$conn->error}\n"; }
    else { $gone++; echo "✔ نُزع $t\n"; }
}

echo "\nنُزع: $gone  ·  متروك: $skip  ·  أخطاء: $err\n";
exit($err === 0 ? 0 : 1);
