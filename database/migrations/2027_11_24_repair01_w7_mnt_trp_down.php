<?php
/**
 * 2027_11_24_repair01_w7_mnt_trp_down.php — تراجعُ هجرةِ W07
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لا يُنزع ما لم تُنشئه هذه الهجرة**: `mnt_order` و`equipments` و
 *   `transfer_orders` و`transfer_cost_lines` و`transfer_delivery_docs`
 *   **جداولُ حيّةٌ سابقة** — لا تُسقَط، ويُنزع منها ما أُلحق بها هنا وحدَه.
 *
 * ◆ **والقيدُ يُنزع قبلَ عمودِه**: `chk_mo_override` يذكر عمودَين، فإسقاطُ
 *   العمودِ قبلَ القيدِ يترك القيدَ يشير إلى ما لا وجودَ له.
 *
 * التشغيل: php database/migrations/2027_11_24_repair01_w7_mnt_trp_down.php
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

function w7d_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}
function w7d_chk(mysqli $c, $t, $name)
{
    $r = $c->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $c->real_escape_string($t) . "'
                       AND CONSTRAINT_NAME = '" . $c->real_escape_string($name) . "'");
    return $r && $r->num_rows > 0;
}

echo "══ تراجعُ REPAIR01 · W07 ══\n\n";
$ok = 0; $err = 0;

/* ① القيودُ قبلَ أعمدتِها */
foreach (array(
    array('mnt_order', 'chk_mo_override'),
    array('mnt_order', 'chk_mo_lockout'),
    array('equipments', 'chk_eq_w7_limits'),
) as $c) {
    if (!w7d_chk($conn, $c[0], $c[1])) { echo "  ↷ {$c[0]}.{$c[1]} غيرُ موجود\n"; continue; }
    if ($conn->query("ALTER TABLE `{$c[0]}` DROP CONSTRAINT `{$c[1]}`") === true) { echo "  ✔ نُزع {$c[0]}.{$c[1]}\n"; $ok++; }
    else { echo "  ✘ {$c[0]}.{$c[1]} — " . $conn->error . "\n"; $err++; }
}

/* ② جداولُ المرحلةِ — الكياناتُ ثمّ الدفاتر */
foreach (array(
    'mnt_return_cert', 'mnt_repeat_repair', 'mnt_part_request',
    'mnt_external_repair', 'mnt_daily_care', 'mnt_kpi_period', 'mnt_safety_rule',
    'trp_origin_handover', 'trp_trip_leg', 'trp_damage_claim', 'trp_closure', 'trp_kpi_period',
    'repair01_w7_journey', 'repair01_w7_decisions', 'repair01_w7_sidebar',
    'repair01_w7_states', 'repair01_w7_sod',
    'repair01_w7_scope', 'repair01_w7_thresholds',
) as $t) {
    if ($conn->query("DROP TABLE IF EXISTS `$t`") === true) { echo "  ✔ نُزع $t\n"; $ok++; }
    else { echo "  ✘ $t — " . $conn->error . "\n"; $err++; }
}

/* ③ الأعمدةُ الملحقةُ بجداولَ حيّةٍ سابقة */
$cols = array(
    'mnt_order' => array('failure_kind', 'safety_severity', 'safety_rule_ref', 'safety_system_key',
                         'severity_override_by', 'severity_override_reason', 'ops_impact', 'ops_impact_rule',
                         'lockout_state', 'lockout_at', 'lockout_by', 'w7_state_rule', 'w7_cert_id'),
    'equipments' => array('w7_readiness_state', 'w7_readiness_rule', 'w7_operating_limits', 'w7_cert_id'),
);
foreach ($cols as $t => $list) {
    foreach ($list as $col) {
        if (!w7d_col($conn, $t, $col)) { echo "  ↷ $t.$col غيرُ موجود\n"; continue; }
        if ($conn->query("ALTER TABLE `$t` DROP COLUMN `$col`") === true) { echo "  ✔ نُزع $t.$col\n"; $ok++; }
        else { echo "  ✘ $t.$col — " . $conn->error . "\n"; $err++; }
    }
}

echo "\n" . str_repeat('─', 70) . "\n";
printf("نُزع %d · أخطاء %d\n", $ok, $err);
echo 'الحكم: ' . ($err === 0 ? "رجعت ✔\n" : "فيها خطأ ✘\n");
exit($err === 0 ? 0 : 1);
