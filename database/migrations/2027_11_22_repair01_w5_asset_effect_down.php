<?php
/**
 * 2027_11_22_repair01_w5_asset_effect_down.php — تراجعُ هجرةِ W05
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الترتيبُ يعكس التبعيّة**: الابنُ قبلَ أبيه (`asset_source_check` قبل
 *   `asset_intake` — بينهما مفتاحٌ أجنبيّ)، والأعمدةُ المُلحَقةُ بـ`equipments`
 *   تُنزع أخيرًا لأنَّ لا شيءَ يعتمد عليها.
 *
 * ⚠ **ولا يُنزع ما لم تُنشئه هذه الهجرة**: `equipments` نفسُه لا يُمَسّ إلّا في
 *   أعمدتِه الثلاثة، و`asset_ownership_shares` (‏المصدرُ الحيُّ الذي قِيس منه
 *   حقُّ الاستخدام) **لا يُمَسّ أصلًا** — الهجرةُ لم تكتب فيه حرفًا.
 *
 * التشغيل: php database/migrations/2027_11_22_repair01_w5_asset_effect_down.php
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

function w5d_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

echo "══ تراجعُ REPAIR01 · W05 ══\n\n";
$ok = 0; $err = 0;

/* ① الابنُ قبلَ أبيه */
foreach (array(
    'asset_source_check', 'asset_inspection_order', 'asset_intake',
    'asset_use_right', 'asset_assignment', 'asset_exit',
    'asset_readiness', 'wf_coverage',
    'repair01_w5_journey', 'repair01_w5_decisions', 'repair01_w5_sidebar', 'repair01_w5_scope',
) as $t) {
    if ($conn->query("DROP TABLE IF EXISTS `$t`") === true) { echo "  ✔ نُزع $t\n"; $ok++; }
    else { echo "  ✘ $t — " . $conn->error . "\n"; $err++; }
}

/* ② أعمدةُ كرتِ الأصلِ الملحقة */
foreach (array('intake_id', 'lifecycle_rule', 'lifecycle_state') as $col) {
    if (!w5d_col($conn, 'equipments', $col)) { echo "  ↷ equipments.$col غيرُ موجود\n"; continue; }
    if ($conn->query("ALTER TABLE `equipments` DROP COLUMN `$col`") === true) { echo "  ✔ نُزع equipments.$col\n"; $ok++; }
    else { echo "  ✘ equipments.$col — " . $conn->error . "\n"; $err++; }
}

echo "\n" . str_repeat('─', 70) . "\n";
printf("نُزع %d · أخطاء %d\n", $ok, $err);
echo 'الحكم: ' . ($err === 0 ? "رجعت ✔\n" : "فيها خطأ ✘\n");
exit($err === 0 ? 0 : 1);
