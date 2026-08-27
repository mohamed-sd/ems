<?php
/**
 * 2027_12_07_repair01_w16_baseline_down.php — نزعُ دفاترِ المرحلةِ السادسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **ولا يُنزَع دفترٌ يحمل ختمَ مالك**: إن كان `repair01_w16_baseline` يحمل
 *   صفًّا حالتُه `OWNER_APPROVED` فالنزعُ **يمحو قرارَ مالكٍ**، والأداةُ تقف.
 *   والتجاوزُ بـ`--force` بعد قراءةِ ما يُمحى.
 *
 * التشغيل: php database/migrations/2027_12_07_repair01_w16_baseline_down.php
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

$force = in_array('--force', $argv, true);
$r = @$conn->query("SELECT COUNT(*) FROM repair01_w16_baseline WHERE state = 'OWNER_APPROVED'");
$approved = $r ? (int) $r->fetch_row()[0] : 0;
if ($approved > 0 && !$force) {
    exit("⛔ سجلُّ الإصدارِ يحمل $approved ختمَ مالكٍ — النزعُ يمحو قرارَه. أضِفْ --force بعد قراءتِه.\n");
}

$drop = array('repair01_w16_fixes', 'repair01_w16_deferred', 'repair01_w16_decisions',
              'repair01_w16_baseline', 'repair01_w16_tabs', 'repair01_w16_uat',
              'repair01_w16_challenge', 'repair01_w16_scorecard', 'repair01_w16_axes',
              'repair01_w16_layers');
$n = 0;
foreach ($drop as $t) {
    $q = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    if (!$q || $q->num_rows === 0) { continue; }
    if (!$conn->query("DROP TABLE `$t`")) { exit("✘ تعذّر نزعُ $t: {$conn->error}\n"); }
    $n++; echo "  ✔ نُزع: $t\n";
}
echo "\n✔ نُزع $n دفترًا من دفاترِ W16\n";
