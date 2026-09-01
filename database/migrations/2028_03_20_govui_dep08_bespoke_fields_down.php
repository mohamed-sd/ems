<?php
/**
 * 2028_03_20_govui_dep08_bespoke_fields_down.php — العكس
 * @migration-objects: drop DEP-08 bespoke columns
 * ⛔ ولا يُسقَط عمودٌ فيه بياناتٌ صامتًا — يُسمَّى ويُترَك.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$DROP = array(
    array('governance_flags', 'activation_pattern'),
    array('entity_licenses', 'reviewed_by'), array('entity_licenses', 'renewal_owner'),
    array('entity_licenses', 'guard_rule_ref'), array('entity_licenses', 'is_critical'),
    array('entity_licenses', 'contract_ref'),
    array('signing_authorities', 'reviewed_by'), array('signing_authorities', 'sub_delegable'),
    array('legal_entities', 'reviewed_by'), array('legal_entities', 'registered_capital'),
    array('legal_entities', 'legal_rep'),
    array('guard_denials', 'request_source'), array('guard_denials', 'owner_dept'),
    array('guard_denials', 'actor_role_id'),
    array('dr_drills', 'reviewed_by'), array('dr_drills', 'data_integrity'),
    array('dr_drills', 'rto_target_seconds'), array('dr_drills', 'drill_cycle'),
);
foreach ($DROP as $d) {
    list($t, $c) = $d;
    $q = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    if (!$q || !$q->num_rows) { echo "= {$t}.{$c} غيرُ موجود\n"; continue; }
    $r = $conn->query("SELECT COUNT(*) FROM `{$t}` WHERE `{$c}` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي {$t}.{$c} لبياناتِه ({$n})\n"; continue; }
    if ($conn->query("ALTER TABLE `{$t}` DROP COLUMN `{$c}`")) { echo "- {$t}.{$c}\n"; }
    else { echo "x {$t}.{$c}: " . $conn->error . "\n"; }
}

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
