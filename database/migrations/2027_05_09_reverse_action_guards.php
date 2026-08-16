<?php
/**
 * 2027_05_09_reverse_action_guards.php
 * ═══════════════════════════════════════════════════════════════════════════
 * الفعلُ العاكسُ للخصمِ سُجِّل (2027_05_08) ناسخًا المعالجَ **بلا guards_json**
 * فرسب act③ «كتابةٌ بلا حارس». الحارسُ يُنسخ من أصلِه — فالعاكسُ يمرُّ من بابِ
 * الحراسةِ نفسِه الذي يمرُّ منه الأصل، لا من بابٍ أخف.
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

$conn->query("UPDATE actions r
              JOIN actions o ON o.action_code = 'screen.workforce.deduction.approve_step'
              SET r.guards_json = o.guards_json,
                  r.precondition_expr = o.precondition_expr,
                  r.module_id = o.module_id
              WHERE r.action_code = 'screen.workforce.deduction.reverse_step'
                AND (r.guards_json IS NULL OR r.guards_json = '')");
echo 'نُسخ حارسُ الأصلِ إلى العاكس: ' . $conn->affected_rows . " صف\n";
$r = $conn->query("SELECT guards_json FROM actions WHERE action_code='screen.workforce.deduction.reverse_step'");
$g = $r ? (string) $r->fetch_row()[0] : '';
echo 'حراسُ العاكس: ' . ($g !== '' ? $g : '✘ فارغ!') . "\n";
echo ($g !== '' ? "\n✔ تمّت\n" : "\n✘ إخفاق\n");
if ($g === '') { exit(1); }
