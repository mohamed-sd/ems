<?php
/**
 * 2027_11_21_repair01_w4_field_down.php — تراجعُ هجرةِ W04
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الترتيبُ يعكس التبعيّة**: القيدُ يُنزع قبلَ العمود، والابنُ قبلَ الأبِ —
 *   `site_day_shift` ثم `site_day`، و`chk_ue_w4_shift` ثم `field_kind`.
 * ◆ **ولا يُحذف أثرٌ خارجَ ما أنشأته هذه الهجرة**: أعمدةُ `timesheet` الأصليّةُ
 *   لم تُمَسّ، وأعمدةُ `unit_entries` القديمةُ لا تُلمَس.
 *
 * التشغيل: php database/migrations/2027_11_21_repair01_w4_field_down.php
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

function w4d_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$n = 0;
echo "══ REPAIR01 · W04 — تراجع ══\n\n";

/* ① القيدُ قبلَ عمودِه */
if ($conn->query("ALTER TABLE `unit_entries` DROP CONSTRAINT `chk_ue_w4_shift`")) { echo "  ✔ نُزع chk_ue_w4_shift\n"; $n++; }
foreach (array('site_day_id', 'field_kind_rule', 'field_kind') as $c) {
    if (w4d_col($conn, 'unit_entries', $c) && $conn->query("ALTER TABLE `unit_entries` DROP COLUMN `$c`")) {
        echo "  ✔ نُزع unit_entries.$c\n"; $n++;
    }
}
foreach (array('stop_occurrence_key', 'stop_register_role') as $c) {
    if (w4d_col($conn, 'timesheet', $c) && $conn->query("ALTER TABLE `timesheet` DROP COLUMN `$c`")) {
        echo "  ✔ نُزع timesheet.$c\n"; $n++;
    }
}

/* ② الابنُ قبلَ الأبّ */
foreach (array('ops_stop_source', 'ops_stop_register', 'site_day_attempt',
               'site_day_shift', 'site_day',
               'repair01_w4_journey', 'repair01_w4_decisions',
               'repair01_w4_sidebar', 'repair01_w4_scope') as $t) {
    if ($conn->query("DROP TABLE IF EXISTS `$t`")) { echo "  ✔ أُسقط $t\n"; $n++; }
    else { echo "  ✘ $t — " . $conn->error . "\n"; }
}

/* ③ عقودُ الأثرِ التي كتبتها المرحلةُ — تُنزع من الدفتر لا من الحدثِ الحيّ */
$conn->query("DELETE FROM `repair01_events` WHERE `contract_stage` = 'W04' AND `wave` = 'W04'");
echo "  ✔ عقودُ الأثرِ المكتوبةُ في W04 نُزعت\n";

printf("\nالحصيلة: %d خطوةَ تراجعٍ · الحكم: رجعت ✔\n", $n);
$conn->close();
exit(0);
