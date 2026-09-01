<?php
/**
 * 2028_03_24_govui_dep02_rest_fields_down.php — DEP-02 · العكس
 * @migration-objects: columns for DEP-02
 * مولَّدةٌ من `tools/govui_field_close.php` على مواصفةِ الإدارة —
 * واسمُ العمودِ تعليقُه اسمُ الحقلِ في ورقةِ الدليل.
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

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c274'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c274` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c274 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c274`")) { echo "- supplier_capacity.c274\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c273'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c273` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c273 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c273`")) { echo "- supplier_capacity.c273\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c272'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c272` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c272 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c272`")) { echo "- supplier_capacity.c272\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c271'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c271` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c271 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c271`")) { echo "- supplier_capacity.c271\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c270'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c270` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c270 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c270`")) { echo "- supplier_capacity.c270\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c269'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c269` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c269 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c269`")) { echo "- supplier_capacity.c269\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c268'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c268` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c268 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c268`")) { echo "- supplier_capacity.c268\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c267'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c267` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c267 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c267`")) { echo "- supplier_capacity.c267\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c266'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c266` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c266 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c266`")) { echo "- supplier_capacity.c266\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c265'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c265` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c265 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c265`")) { echo "- supplier_capacity.c265\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_capacity` LIKE 'c264'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_capacity` WHERE `c264` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_capacity.c264 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_capacity` DROP COLUMN `c264`")) { echo "- supplier_capacity.c264\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c263'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c263` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c263 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c263`")) { echo "- supplier_evaluations.c263\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c262'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c262` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c262 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c262`")) { echo "- supplier_evaluations.c262\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c261'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c261` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c261 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c261`")) { echo "- supplier_evaluations.c261\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c260'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c260` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c260 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c260`")) { echo "- supplier_evaluations.c260\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c259'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c259` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c259 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c259`")) { echo "- supplier_evaluations.c259\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c258'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c258` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c258 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c258`")) { echo "- supplier_evaluations.c258\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c257'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c257` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c257 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c257`")) { echo "- supplier_evaluations.c257\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c256'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c256` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c256 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c256`")) { echo "- supplier_evaluations.c256\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c255'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c255` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c255 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c255`")) { echo "- supplier_evaluations.c255\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c254'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c254` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c254 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c254`")) { echo "- supplier_evaluations.c254\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c253'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c253` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c253 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c253`")) { echo "- supplier_evaluations.c253\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c252'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c252` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c252 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c252`")) { echo "- supplier_evaluations.c252\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c251'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c251` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c251 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c251`")) { echo "- supplier_evaluations.c251\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c250'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c250` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c250 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c250`")) { echo "- supplier_evaluations.c250\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c249'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c249` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c249 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c249`")) { echo "- supplier_evaluations.c249\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c248'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c248` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c248 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c248`")) { echo "- supplier_evaluations.c248\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c247'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c247` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c247 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c247`")) { echo "- supplier_evaluations.c247\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c246'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c246` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c246 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c246`")) { echo "- supplier_evaluations.c246\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c245'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c245` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c245 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c245`")) { echo "- supplier_evaluations.c245\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c244'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c244` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c244 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c244`")) { echo "- supplier_evaluations.c244\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c243'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c243` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c243 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c243`")) { echo "- supplier_evaluations.c243\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c242'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c242` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c242 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c242`")) { echo "- supplier_evaluations.c242\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c241'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c241` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c241 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c241`")) { echo "- supplier_evaluations.c241\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c240'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c240` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c240 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c240`")) { echo "- supplier_evaluations.c240\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c239'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c239` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c239 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c239`")) { echo "- supplier_evaluations.c239\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c238'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c238` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c238 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c238`")) { echo "- supplier_evaluations.c238\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c237'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c237` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c237 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c237`")) { echo "- supplier_evaluations.c237\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c236'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c236` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c236 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c236`")) { echo "- supplier_evaluations.c236\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `supplier_evaluations` LIKE 'c235'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `supplier_evaluations` WHERE `c235` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي supplier_evaluations.c235 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `supplier_evaluations` DROP COLUMN `c235`")) { echo "- supplier_evaluations.c235\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c234'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c234` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c234 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c234`")) { echo "- sup_violations.c234\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c233'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c233` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c233 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c233`")) { echo "- sup_violations.c233\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c232'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c232` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c232 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c232`")) { echo "- sup_violations.c232\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c231'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c231` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c231 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c231`")) { echo "- sup_violations.c231\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c230'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c230` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c230 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c230`")) { echo "- sup_violations.c230\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c229'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c229` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c229 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c229`")) { echo "- sup_violations.c229\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c228'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c228` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c228 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c228`")) { echo "- sup_violations.c228\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c227'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c227` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c227 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c227`")) { echo "- sup_violations.c227\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c226'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c226` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c226 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c226`")) { echo "- sup_violations.c226\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c225'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c225` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c225 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c225`")) { echo "- sup_violations.c225\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c224'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c224` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c224 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c224`")) { echo "- sup_violations.c224\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c223'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c223` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c223 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c223`")) { echo "- sup_violations.c223\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c222'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c222` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c222 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c222`")) { echo "- sup_violations.c222\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c221'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c221` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c221 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c221`")) { echo "- sup_violations.c221\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c220'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c220` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c220 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c220`")) { echo "- sup_violations.c220\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `sup_violations` LIKE 'c219'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `sup_violations` WHERE `c219` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي sup_violations.c219 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `sup_violations` DROP COLUMN `c219`")) { echo "- sup_violations.c219\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c29'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c29` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c29 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c29`")) { echo "- suppliers.c29\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c28'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c28` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c28 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c28`")) { echo "- suppliers.c28\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c27'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c27` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c27 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c27`")) { echo "- suppliers.c27\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c26'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c26` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c26 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c26`")) { echo "- suppliers.c26\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c25'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c25` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c25 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c25`")) { echo "- suppliers.c25\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c24'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c24` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c24 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c24`")) { echo "- suppliers.c24\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c23'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c23` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c23 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c23`")) { echo "- suppliers.c23\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c22'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c22` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c22 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c22`")) { echo "- suppliers.c22\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c21'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c21` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c21 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c21`")) { echo "- suppliers.c21\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c20'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c20` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c20 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c20`")) { echo "- suppliers.c20\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c19'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c19` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c19 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c19`")) { echo "- suppliers.c19\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c18'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c18` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c18 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c18`")) { echo "- suppliers.c18\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c17'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c17` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c17 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c17`")) { echo "- suppliers.c17\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c16'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c16` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c16 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c16`")) { echo "- suppliers.c16\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c15'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c15` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c15 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c15`")) { echo "- suppliers.c15\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c14'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c14` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c14 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c14`")) { echo "- suppliers.c14\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c13'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c13` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c13 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c13`")) { echo "- suppliers.c13\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c12'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c12` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c12 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c12`")) { echo "- suppliers.c12\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c11'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c11` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c11 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c11`")) { echo "- suppliers.c11\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c10'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c10` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c10 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c10`")) { echo "- suppliers.c10\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c9'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c9` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c9 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c9`")) { echo "- suppliers.c9\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c8'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c8` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c8 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c8`")) { echo "- suppliers.c8\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c7'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c7` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c7 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c7`")) { echo "- suppliers.c7\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c6'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c6` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c6 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c6`")) { echo "- suppliers.c6\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c5'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c5` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c5 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c5`")) { echo "- suppliers.c5\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c4'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c4` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c4 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c4`")) { echo "- suppliers.c4\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c3'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c3` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c3 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c3`")) { echo "- suppliers.c3\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c2'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c2` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c2 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c2`")) { echo "- suppliers.c2\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `suppliers` LIKE 'c1'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `suppliers` WHERE `c1` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي suppliers.c1 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `suppliers` DROP COLUMN `c1`")) { echo "- suppliers.c1\n"; }
}

$r = $conn->query('SELECT COUNT(*) FROM `sup_performance_unit`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sup_performance_unit لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sup_performance_unit`')) { echo '- جدول sup_performance_unit
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sup_dashboard_kpi`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sup_dashboard_kpi لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sup_dashboard_kpi`')) { echo '- جدول sup_dashboard_kpi
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sup_entitlement`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sup_entitlement لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sup_entitlement`')) { echo '- جدول sup_entitlement
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sup_contract_line`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sup_contract_line لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sup_contract_line`')) { echo '- جدول sup_contract_line
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sup_contract_register`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sup_contract_register لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sup_contract_register`')) { echo '- جدول sup_contract_register
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sup_rfq_review`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sup_rfq_review لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sup_rfq_review`')) { echo '- جدول sup_rfq_review
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sup_equipment_available`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sup_equipment_available لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sup_equipment_available`')) { echo '- جدول sup_equipment_available
'; }

$r = $conn->query('SELECT COUNT(*) FROM `sup_contact_delegate`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي sup_contact_delegate لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `sup_contact_delegate`')) { echo '- جدول sup_contact_delegate
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
