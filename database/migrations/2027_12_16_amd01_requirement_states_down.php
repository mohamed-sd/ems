<?php
/**
 * 2027_12_16_amd01_requirement_states_down.php — نقضُ موضعِ الحكم
 * ⛔ **والنقضُ يمحو أحكامًا** — فيُقاس المحكومُ ويُردُّ النقضُ إن وُجد.
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
$r = $conn->query("SELECT COUNT(*) FROM repair01_requirements WHERE amd01_state IS NOT NULL");
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) {
    exit("⛔ **$n متطلبًا محكومٌ عليه** — والنقضُ يمحو الأحكامَ. صرِّحْ بالمحو أوّلًا.\n");
}
foreach (array('state_snapshot','state_at','identity_status','state_evidence',
               'proof_contract','requirement_type','amd01_state') as $c) {
    $r = $conn->query("SHOW COLUMNS FROM `repair01_requirements` LIKE '$c'");
    if ($r && $r->num_rows) {
        $conn->query("ALTER TABLE `repair01_requirements` DROP COLUMN `$c`");
        echo "  ✔ أُسقط `$c`\n";
    }
}
echo "✔ نُقض موضعُ الحكم\n";
