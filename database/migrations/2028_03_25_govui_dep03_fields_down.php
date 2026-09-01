<?php
/**
 * 2028_03_25_govui_dep03_fields_down.php — DEP-03 · العكس
 * @migration-objects: columns for DEP-03
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

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g442'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g442` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g442 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g442`")) { echo "- fin_ref_list.g442\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g441'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g441` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g441 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g441`")) { echo "- fin_ref_list.g441\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g440'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g440` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g440 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g440`")) { echo "- fin_ref_list.g440\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g439'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g439` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g439 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g439`")) { echo "- fin_ref_list.g439\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g438'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g438` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g438 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g438`")) { echo "- fin_ref_list.g438\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g437'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g437` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g437 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g437`")) { echo "- fin_ref_list.g437\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g436'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g436` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g436 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g436`")) { echo "- fin_ref_list.g436\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g435'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g435` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g435 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g435`")) { echo "- fin_ref_list.g435\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g434'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g434` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g434 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g434`")) { echo "- fin_ref_list.g434\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g433'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g433` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g433 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g433`")) { echo "- fin_ref_list.g433\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g432'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g432` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g432 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g432`")) { echo "- fin_ref_list.g432\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g431'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g431` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g431 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g431`")) { echo "- fin_ref_list.g431\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g430'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g430` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g430 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g430`")) { echo "- fin_ref_list.g430\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g429'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g429` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g429 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g429`")) { echo "- fin_ref_list.g429\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g428'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g428` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g428 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g428`")) { echo "- fin_ref_list.g428\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g427'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g427` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g427 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g427`")) { echo "- fin_ref_list.g427\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g426'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g426` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g426 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g426`")) { echo "- fin_ref_list.g426\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g425'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g425` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g425 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g425`")) { echo "- fin_ref_list.g425\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g424'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g424` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g424 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g424`")) { echo "- fin_ref_list.g424\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g423'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g423` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g423 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g423`")) { echo "- fin_ref_list.g423\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g422'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g422` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g422 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g422`")) { echo "- fin_ref_list.g422\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g421'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g421` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g421 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g421`")) { echo "- fin_ref_list.g421\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g420'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g420` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g420 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g420`")) { echo "- fin_ref_list.g420\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_ref_list` LIKE 'g419'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_ref_list` WHERE `g419` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_ref_list.g419 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_ref_list` DROP COLUMN `g419`")) { echo "- fin_ref_list.g419\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g418'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g418` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g418 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g418`")) { echo "- fin_final_close.g418\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g417'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g417` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g417 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g417`")) { echo "- fin_final_close.g417\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g416'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g416` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g416 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g416`")) { echo "- fin_final_close.g416\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g415'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g415` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g415 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g415`")) { echo "- fin_final_close.g415\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g414'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g414` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g414 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g414`")) { echo "- fin_final_close.g414\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g413'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g413` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g413 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g413`")) { echo "- fin_final_close.g413\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g412'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g412` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g412 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g412`")) { echo "- fin_final_close.g412\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g411'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g411` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g411 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g411`")) { echo "- fin_final_close.g411\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g410'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g410` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g410 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g410`")) { echo "- fin_final_close.g410\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g409'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g409` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g409 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g409`")) { echo "- fin_final_close.g409\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g408'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g408` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g408 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g408`")) { echo "- fin_final_close.g408\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g407'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g407` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g407 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g407`")) { echo "- fin_final_close.g407\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g406'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g406` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g406 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g406`")) { echo "- fin_final_close.g406\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g405'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g405` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g405 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g405`")) { echo "- fin_final_close.g405\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g404'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g404` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g404 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g404`")) { echo "- fin_final_close.g404\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g403'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g403` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g403 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g403`")) { echo "- fin_final_close.g403\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g402'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g402` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g402 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g402`")) { echo "- fin_final_close.g402\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g401'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g401` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g401 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g401`")) { echo "- fin_final_close.g401\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g400'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g400` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g400 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g400`")) { echo "- fin_final_close.g400\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_final_close` LIKE 'g399'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_final_close` WHERE `g399` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_final_close.g399 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_final_close` DROP COLUMN `g399`")) { echo "- fin_final_close.g399\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g364'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g364` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g364 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g364`")) { echo "- fin_payment_allocation.g364\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g363'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g363` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g363 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g363`")) { echo "- fin_payment_allocation.g363\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g362'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g362` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g362 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g362`")) { echo "- fin_payment_allocation.g362\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g361'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g361` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g361 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g361`")) { echo "- fin_payment_allocation.g361\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g360'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g360` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g360 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g360`")) { echo "- fin_payment_allocation.g360\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g359'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g359` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g359 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g359`")) { echo "- fin_payment_allocation.g359\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g358'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g358` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g358 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g358`")) { echo "- fin_payment_allocation.g358\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g357'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g357` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g357 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g357`")) { echo "- fin_payment_allocation.g357\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g356'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g356` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g356 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g356`")) { echo "- fin_payment_allocation.g356\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g355'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g355` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g355 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g355`")) { echo "- fin_payment_allocation.g355\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g354'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g354` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g354 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g354`")) { echo "- fin_payment_allocation.g354\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g353'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g353` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g353 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g353`")) { echo "- fin_payment_allocation.g353\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g352'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g352` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g352 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g352`")) { echo "- fin_payment_allocation.g352\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g351'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g351` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g351 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g351`")) { echo "- fin_payment_allocation.g351\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_allocation` LIKE 'g350'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_allocation` WHERE `g350` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_allocation.g350 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_allocation` DROP COLUMN `g350`")) { echo "- fin_payment_allocation.g350\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g349'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g349` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g349 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g349`")) { echo "- fin_payment_order.g349\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g348'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g348` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g348 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g348`")) { echo "- fin_payment_order.g348\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g347'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g347` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g347 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g347`")) { echo "- fin_payment_order.g347\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g346'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g346` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g346 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g346`")) { echo "- fin_payment_order.g346\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g345'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g345` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g345 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g345`")) { echo "- fin_payment_order.g345\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g344'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g344` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g344 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g344`")) { echo "- fin_payment_order.g344\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g343'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g343` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g343 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g343`")) { echo "- fin_payment_order.g343\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g342'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g342` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g342 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g342`")) { echo "- fin_payment_order.g342\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g341'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g341` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g341 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g341`")) { echo "- fin_payment_order.g341\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g340'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g340` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g340 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g340`")) { echo "- fin_payment_order.g340\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g339'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g339` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g339 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g339`")) { echo "- fin_payment_order.g339\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g338'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g338` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g338 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g338`")) { echo "- fin_payment_order.g338\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g337'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g337` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g337 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g337`")) { echo "- fin_payment_order.g337\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g336'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g336` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g336 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g336`")) { echo "- fin_payment_order.g336\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g335'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g335` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g335 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g335`")) { echo "- fin_payment_order.g335\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g334'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g334` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g334 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g334`")) { echo "- fin_payment_order.g334\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g333'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g333` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g333 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g333`")) { echo "- fin_payment_order.g333\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g332'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g332` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g332 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g332`")) { echo "- fin_payment_order.g332\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g331'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g331` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g331 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g331`")) { echo "- fin_payment_order.g331\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g330'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g330` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g330 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g330`")) { echo "- fin_payment_order.g330\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g329'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g329` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g329 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g329`")) { echo "- fin_payment_order.g329\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g328'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g328` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g328 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g328`")) { echo "- fin_payment_order.g328\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_payment_order` LIKE 'g327'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_payment_order` WHERE `g327` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_payment_order.g327 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_payment_order` DROP COLUMN `g327`")) { echo "- fin_payment_order.g327\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g300'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g300` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g300 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g300`")) { echo "- fin_contract_close.g300\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g299'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g299` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g299 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g299`")) { echo "- fin_contract_close.g299\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g298'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g298` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g298 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g298`")) { echo "- fin_contract_close.g298\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g297'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g297` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g297 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g297`")) { echo "- fin_contract_close.g297\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g296'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g296` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g296 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g296`")) { echo "- fin_contract_close.g296\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g295'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g295` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g295 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g295`")) { echo "- fin_contract_close.g295\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g294'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g294` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g294 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g294`")) { echo "- fin_contract_close.g294\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g293'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g293` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g293 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g293`")) { echo "- fin_contract_close.g293\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g292'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g292` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g292 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g292`")) { echo "- fin_contract_close.g292\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g291'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g291` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g291 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g291`")) { echo "- fin_contract_close.g291\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g290'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g290` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g290 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g290`")) { echo "- fin_contract_close.g290\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g289'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g289` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g289 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g289`")) { echo "- fin_contract_close.g289\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g288'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g288` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g288 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g288`")) { echo "- fin_contract_close.g288\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g287'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g287` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g287 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g287`")) { echo "- fin_contract_close.g287\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g286'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g286` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g286 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g286`")) { echo "- fin_contract_close.g286\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g285'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g285` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g285 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g285`")) { echo "- fin_contract_close.g285\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g284'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g284` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g284 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g284`")) { echo "- fin_contract_close.g284\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g283'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g283` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g283 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g283`")) { echo "- fin_contract_close.g283\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g282'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g282` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g282 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g282`")) { echo "- fin_contract_close.g282\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g281'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g281` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g281 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g281`")) { echo "- fin_contract_close.g281\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g280'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g280` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g280 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g280`")) { echo "- fin_contract_close.g280\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g279'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g279` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g279 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g279`")) { echo "- fin_contract_close.g279\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g278'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g278` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g278 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g278`")) { echo "- fin_contract_close.g278\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g277'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g277` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g277 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g277`")) { echo "- fin_contract_close.g277\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g276'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g276` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g276 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g276`")) { echo "- fin_contract_close.g276\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g275'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g275` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g275 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g275`")) { echo "- fin_contract_close.g275\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g274'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g274` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g274 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g274`")) { echo "- fin_contract_close.g274\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g273'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g273` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g273 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g273`")) { echo "- fin_contract_close.g273\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g272'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g272` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g272 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g272`")) { echo "- fin_contract_close.g272\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g271'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g271` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g271 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g271`")) { echo "- fin_contract_close.g271\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g270'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g270` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g270 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g270`")) { echo "- fin_contract_close.g270\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_close` LIKE 'g269'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_close` WHERE `g269` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_close.g269 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_close` DROP COLUMN `g269`")) { echo "- fin_contract_close.g269\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g252'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g252` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g252 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g252`")) { echo "- financing_operations.g252\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g251'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g251` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g251 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g251`")) { echo "- financing_operations.g251\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g250'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g250` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g250 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g250`")) { echo "- financing_operations.g250\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g249'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g249` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g249 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g249`")) { echo "- financing_operations.g249\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g248'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g248` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g248 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g248`")) { echo "- financing_operations.g248\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g247'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g247` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g247 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g247`")) { echo "- financing_operations.g247\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g246'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g246` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g246 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g246`")) { echo "- financing_operations.g246\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g245'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g245` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g245 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g245`")) { echo "- financing_operations.g245\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g244'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g244` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g244 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g244`")) { echo "- financing_operations.g244\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g243'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g243` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g243 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g243`")) { echo "- financing_operations.g243\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g242'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g242` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g242 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g242`")) { echo "- financing_operations.g242\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g241'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g241` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g241 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g241`")) { echo "- financing_operations.g241\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g240'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g240` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g240 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g240`")) { echo "- financing_operations.g240\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g239'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g239` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g239 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g239`")) { echo "- financing_operations.g239\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g238'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g238` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g238 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g238`")) { echo "- financing_operations.g238\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g237'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g237` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g237 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g237`")) { echo "- financing_operations.g237\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g236'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g236` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g236 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g236`")) { echo "- financing_operations.g236\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g235'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g235` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g235 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g235`")) { echo "- financing_operations.g235\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g234'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g234` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g234 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g234`")) { echo "- financing_operations.g234\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g233'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g233` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g233 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g233`")) { echo "- financing_operations.g233\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g232'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g232` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g232 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g232`")) { echo "- financing_operations.g232\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g231'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g231` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g231 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g231`")) { echo "- financing_operations.g231\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g230'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g230` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g230 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g230`")) { echo "- financing_operations.g230\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g229'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g229` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g229 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g229`")) { echo "- financing_operations.g229\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g228'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g228` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g228 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g228`")) { echo "- financing_operations.g228\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g227'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g227` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g227 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g227`")) { echo "- financing_operations.g227\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g226'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g226` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g226 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g226`")) { echo "- financing_operations.g226\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g225'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g225` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g225 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g225`")) { echo "- financing_operations.g225\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g224'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g224` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g224 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g224`")) { echo "- financing_operations.g224\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g223'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g223` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g223 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g223`")) { echo "- financing_operations.g223\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g222'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g222` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g222 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g222`")) { echo "- financing_operations.g222\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g221'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g221` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g221 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g221`")) { echo "- financing_operations.g221\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g220'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g220` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g220 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g220`")) { echo "- financing_operations.g220\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g219'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g219` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g219 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g219`")) { echo "- financing_operations.g219\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g218'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g218` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g218 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g218`")) { echo "- financing_operations.g218\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g217'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g217` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g217 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g217`")) { echo "- financing_operations.g217\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g216'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g216` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g216 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g216`")) { echo "- financing_operations.g216\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g215'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g215` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g215 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g215`")) { echo "- financing_operations.g215\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g214'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g214` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g214 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g214`")) { echo "- financing_operations.g214\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g213'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g213` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g213 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g213`")) { echo "- financing_operations.g213\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g212'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g212` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g212 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g212`")) { echo "- financing_operations.g212\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `financing_operations` LIKE 'g211'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `financing_operations` WHERE `g211` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي financing_operations.g211 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `financing_operations` DROP COLUMN `g211`")) { echo "- financing_operations.g211\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g210'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g210` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g210 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g210`")) { echo "- fin_contract_covenant.g210\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g209'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g209` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g209 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g209`")) { echo "- fin_contract_covenant.g209\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g208'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g208` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g208 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g208`")) { echo "- fin_contract_covenant.g208\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g207'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g207` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g207 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g207`")) { echo "- fin_contract_covenant.g207\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g206'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g206` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g206 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g206`")) { echo "- fin_contract_covenant.g206\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g205'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g205` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g205 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g205`")) { echo "- fin_contract_covenant.g205\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g204'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g204` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g204 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g204`")) { echo "- fin_contract_covenant.g204\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g203'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g203` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g203 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g203`")) { echo "- fin_contract_covenant.g203\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g202'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g202` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g202 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g202`")) { echo "- fin_contract_covenant.g202\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g201'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g201` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g201 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g201`")) { echo "- fin_contract_covenant.g201\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g200'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g200` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g200 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g200`")) { echo "- fin_contract_covenant.g200\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g199'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g199` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g199 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g199`")) { echo "- fin_contract_covenant.g199\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g198'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g198` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g198 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g198`")) { echo "- fin_contract_covenant.g198\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g197'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g197` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g197 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g197`")) { echo "- fin_contract_covenant.g197\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g196'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g196` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g196 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g196`")) { echo "- fin_contract_covenant.g196\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g195'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g195` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g195 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g195`")) { echo "- fin_contract_covenant.g195\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g194'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g194` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g194 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g194`")) { echo "- fin_contract_covenant.g194\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g193'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g193` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g193 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g193`")) { echo "- fin_contract_covenant.g193\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g192'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g192` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g192 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g192`")) { echo "- fin_contract_covenant.g192\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g191'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g191` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g191 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g191`")) { echo "- fin_contract_covenant.g191\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g190'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g190` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g190 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g190`")) { echo "- fin_contract_covenant.g190\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g189'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g189` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g189 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g189`")) { echo "- fin_contract_covenant.g189\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g188'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g188` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g188 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g188`")) { echo "- fin_contract_covenant.g188\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g187'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g187` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g187 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g187`")) { echo "- fin_contract_covenant.g187\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g186'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g186` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g186 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g186`")) { echo "- fin_contract_covenant.g186\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g185'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g185` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g185 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g185`")) { echo "- fin_contract_covenant.g185\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g184'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g184` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g184 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g184`")) { echo "- fin_contract_covenant.g184\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g183'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g183` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g183 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g183`")) { echo "- fin_contract_covenant.g183\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g182'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g182` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g182 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g182`")) { echo "- fin_contract_covenant.g182\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g181'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g181` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g181 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g181`")) { echo "- fin_contract_covenant.g181\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g180'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g180` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g180 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g180`")) { echo "- fin_contract_covenant.g180\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g179'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g179` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g179 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g179`")) { echo "- fin_contract_covenant.g179\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g178'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g178` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g178 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g178`")) { echo "- fin_contract_covenant.g178\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g177'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g177` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g177 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g177`")) { echo "- fin_contract_covenant.g177\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g176'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g176` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g176 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g176`")) { echo "- fin_contract_covenant.g176\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g175'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g175` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g175 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g175`")) { echo "- fin_contract_covenant.g175\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_covenant` LIKE 'g174'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_covenant` WHERE `g174` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_covenant.g174 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_covenant` DROP COLUMN `g174`")) { echo "- fin_contract_covenant.g174\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g173'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g173` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g173 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g173`")) { echo "- fin_contract_term.g173\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g172'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g172` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g172 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g172`")) { echo "- fin_contract_term.g172\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g171'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g171` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g171 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g171`")) { echo "- fin_contract_term.g171\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g170'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g170` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g170 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g170`")) { echo "- fin_contract_term.g170\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g169'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g169` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g169 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g169`")) { echo "- fin_contract_term.g169\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g168'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g168` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g168 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g168`")) { echo "- fin_contract_term.g168\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g167'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g167` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g167 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g167`")) { echo "- fin_contract_term.g167\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g166'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g166` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g166 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g166`")) { echo "- fin_contract_term.g166\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g165'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g165` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g165 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g165`")) { echo "- fin_contract_term.g165\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g164'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g164` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g164 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g164`")) { echo "- fin_contract_term.g164\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g163'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g163` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g163 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g163`")) { echo "- fin_contract_term.g163\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g162'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g162` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g162 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g162`")) { echo "- fin_contract_term.g162\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g161'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g161` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g161 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g161`")) { echo "- fin_contract_term.g161\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g160'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g160` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g160 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g160`")) { echo "- fin_contract_term.g160\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g159'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g159` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g159 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g159`")) { echo "- fin_contract_term.g159\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g158'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g158` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g158 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g158`")) { echo "- fin_contract_term.g158\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g157'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g157` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g157 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g157`")) { echo "- fin_contract_term.g157\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g156'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g156` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g156 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g156`")) { echo "- fin_contract_term.g156\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g155'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g155` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g155 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g155`")) { echo "- fin_contract_term.g155\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g154'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g154` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g154 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g154`")) { echo "- fin_contract_term.g154\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g153'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g153` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g153 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g153`")) { echo "- fin_contract_term.g153\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g152'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g152` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g152 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g152`")) { echo "- fin_contract_term.g152\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g151'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g151` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g151 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g151`")) { echo "- fin_contract_term.g151\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_contract_term` LIKE 'g150'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_contract_term` WHERE `g150` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_contract_term.g150 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_contract_term` DROP COLUMN `g150`")) { echo "- fin_contract_term.g150\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g149'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g149` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g149 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g149`")) { echo "- fin_finance_contract.g149\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g148'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g148` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g148 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g148`")) { echo "- fin_finance_contract.g148\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g147'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g147` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g147 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g147`")) { echo "- fin_finance_contract.g147\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g146'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g146` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g146 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g146`")) { echo "- fin_finance_contract.g146\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g145'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g145` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g145 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g145`")) { echo "- fin_finance_contract.g145\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g144'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g144` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g144 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g144`")) { echo "- fin_finance_contract.g144\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g143'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g143` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g143 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g143`")) { echo "- fin_finance_contract.g143\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g142'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g142` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g142 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g142`")) { echo "- fin_finance_contract.g142\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g141'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g141` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g141 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g141`")) { echo "- fin_finance_contract.g141\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g140'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g140` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g140 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g140`")) { echo "- fin_finance_contract.g140\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g139'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g139` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g139 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g139`")) { echo "- fin_finance_contract.g139\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g138'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g138` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g138 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g138`")) { echo "- fin_finance_contract.g138\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g137'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g137` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g137 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g137`")) { echo "- fin_finance_contract.g137\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g136'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g136` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g136 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g136`")) { echo "- fin_finance_contract.g136\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g135'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g135` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g135 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g135`")) { echo "- fin_finance_contract.g135\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g134'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g134` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g134 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g134`")) { echo "- fin_finance_contract.g134\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g133'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g133` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g133 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g133`")) { echo "- fin_finance_contract.g133\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g132'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g132` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g132 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g132`")) { echo "- fin_finance_contract.g132\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g131'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g131` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g131 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g131`")) { echo "- fin_finance_contract.g131\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g130'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g130` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g130 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g130`")) { echo "- fin_finance_contract.g130\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g129'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g129` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g129 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g129`")) { echo "- fin_finance_contract.g129\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g128'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g128` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g128 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g128`")) { echo "- fin_finance_contract.g128\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g127'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g127` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g127 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g127`")) { echo "- fin_finance_contract.g127\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g126'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g126` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g126 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g126`")) { echo "- fin_finance_contract.g126\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g125'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g125` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g125 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g125`")) { echo "- fin_finance_contract.g125\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g124'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g124` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g124 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g124`")) { echo "- fin_finance_contract.g124\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g123'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g123` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g123 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g123`")) { echo "- fin_finance_contract.g123\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g122'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g122` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g122 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g122`")) { echo "- fin_finance_contract.g122\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_finance_contract` LIKE 'g121'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_finance_contract` WHERE `g121` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_finance_contract.g121 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_finance_contract` DROP COLUMN `g121`")) { echo "- fin_finance_contract.g121\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g120'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g120` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g120 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g120`")) { echo "- fin_precontract_review.g120\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g119'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g119` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g119 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g119`")) { echo "- fin_precontract_review.g119\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g118'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g118` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g118 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g118`")) { echo "- fin_precontract_review.g118\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g117'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g117` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g117 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g117`")) { echo "- fin_precontract_review.g117\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g116'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g116` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g116 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g116`")) { echo "- fin_precontract_review.g116\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g115'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g115` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g115 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g115`")) { echo "- fin_precontract_review.g115\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g114'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g114` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g114 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g114`")) { echo "- fin_precontract_review.g114\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g113'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g113` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g113 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g113`")) { echo "- fin_precontract_review.g113\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g112'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g112` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g112 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g112`")) { echo "- fin_precontract_review.g112\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g111'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g111` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g111 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g111`")) { echo "- fin_precontract_review.g111\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g110'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g110` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g110 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g110`")) { echo "- fin_precontract_review.g110\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g109'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g109` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g109 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g109`")) { echo "- fin_precontract_review.g109\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g108'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g108` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g108 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g108`")) { echo "- fin_precontract_review.g108\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g107'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g107` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g107 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g107`")) { echo "- fin_precontract_review.g107\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g106'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g106` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g106 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g106`")) { echo "- fin_precontract_review.g106\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g105'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g105` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g105 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g105`")) { echo "- fin_precontract_review.g105\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g104'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g104` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g104 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g104`")) { echo "- fin_precontract_review.g104\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g103'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g103` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g103 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g103`")) { echo "- fin_precontract_review.g103\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g102'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g102` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g102 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g102`")) { echo "- fin_precontract_review.g102\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_precontract_review` LIKE 'g101'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_precontract_review` WHERE `g101` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_precontract_review.g101 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_precontract_review` DROP COLUMN `g101`")) { echo "- fin_precontract_review.g101\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g100'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g100` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g100 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g100`")) { echo "- fin_funding_offer.g100\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g99'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g99` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g99 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g99`")) { echo "- fin_funding_offer.g99\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g98'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g98` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g98 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g98`")) { echo "- fin_funding_offer.g98\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g97'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g97` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g97 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g97`")) { echo "- fin_funding_offer.g97\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g96'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g96` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g96 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g96`")) { echo "- fin_funding_offer.g96\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g95'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g95` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g95 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g95`")) { echo "- fin_funding_offer.g95\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g94'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g94` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g94 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g94`")) { echo "- fin_funding_offer.g94\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g93'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g93` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g93 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g93`")) { echo "- fin_funding_offer.g93\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g92'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g92` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g92 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g92`")) { echo "- fin_funding_offer.g92\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g91'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g91` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g91 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g91`")) { echo "- fin_funding_offer.g91\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g90'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g90` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g90 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g90`")) { echo "- fin_funding_offer.g90\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g89'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g89` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g89 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g89`")) { echo "- fin_funding_offer.g89\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g88'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g88` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g88 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g88`")) { echo "- fin_funding_offer.g88\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g87'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g87` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g87 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g87`")) { echo "- fin_funding_offer.g87\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g86'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g86` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g86 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g86`")) { echo "- fin_funding_offer.g86\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g85'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g85` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g85 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g85`")) { echo "- fin_funding_offer.g85\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g84'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g84` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g84 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g84`")) { echo "- fin_funding_offer.g84\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g83'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g83` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g83 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g83`")) { echo "- fin_funding_offer.g83\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g82'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g82` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g82 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g82`")) { echo "- fin_funding_offer.g82\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g81'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g81` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g81 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g81`")) { echo "- fin_funding_offer.g81\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g80'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g80` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g80 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g80`")) { echo "- fin_funding_offer.g80\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g79'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g79` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g79 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g79`")) { echo "- fin_funding_offer.g79\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g78'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g78` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g78 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g78`")) { echo "- fin_funding_offer.g78\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g77'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g77` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g77 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g77`")) { echo "- fin_funding_offer.g77\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g76'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g76` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g76 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g76`")) { echo "- fin_funding_offer.g76\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g75'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g75` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g75 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g75`")) { echo "- fin_funding_offer.g75\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g74'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g74` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g74 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g74`")) { echo "- fin_funding_offer.g74\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g73'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g73` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g73 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g73`")) { echo "- fin_funding_offer.g73\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g72'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g72` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g72 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g72`")) { echo "- fin_funding_offer.g72\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g71'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g71` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g71 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g71`")) { echo "- fin_funding_offer.g71\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g70'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g70` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g70 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g70`")) { echo "- fin_funding_offer.g70\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_offer` LIKE 'g69'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_offer` WHERE `g69` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_offer.g69 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_offer` DROP COLUMN `g69`")) { echo "- fin_funding_offer.g69\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g68'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g68` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g68 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g68`")) { echo "- fin_funding_need.g68\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g67'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g67` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g67 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g67`")) { echo "- fin_funding_need.g67\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g66'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g66` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g66 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g66`")) { echo "- fin_funding_need.g66\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g65'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g65` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g65 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g65`")) { echo "- fin_funding_need.g65\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g64'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g64` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g64 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g64`")) { echo "- fin_funding_need.g64\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g63'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g63` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g63 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g63`")) { echo "- fin_funding_need.g63\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g62'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g62` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g62 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g62`")) { echo "- fin_funding_need.g62\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g61'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g61` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g61 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g61`")) { echo "- fin_funding_need.g61\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g60'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g60` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g60 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g60`")) { echo "- fin_funding_need.g60\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g59'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g59` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g59 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g59`")) { echo "- fin_funding_need.g59\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g58'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g58` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g58 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g58`")) { echo "- fin_funding_need.g58\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g57'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g57` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g57 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g57`")) { echo "- fin_funding_need.g57\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g56'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g56` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g56 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g56`")) { echo "- fin_funding_need.g56\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g55'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g55` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g55 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g55`")) { echo "- fin_funding_need.g55\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g54'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g54` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g54 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g54`")) { echo "- fin_funding_need.g54\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g53'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g53` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g53 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g53`")) { echo "- fin_funding_need.g53\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g52'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g52` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g52 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g52`")) { echo "- fin_funding_need.g52\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g51'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g51` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g51 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g51`")) { echo "- fin_funding_need.g51\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g50'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g50` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g50 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g50`")) { echo "- fin_funding_need.g50\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g49'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g49` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g49 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g49`")) { echo "- fin_funding_need.g49\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g48'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g48` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g48 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g48`")) { echo "- fin_funding_need.g48\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g47'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g47` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g47 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g47`")) { echo "- fin_funding_need.g47\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g46'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g46` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g46 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g46`")) { echo "- fin_funding_need.g46\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g45'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g45` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g45 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g45`")) { echo "- fin_funding_need.g45\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g44'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g44` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g44 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g44`")) { echo "- fin_funding_need.g44\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_funding_need` LIKE 'g43'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_funding_need` WHERE `g43` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_funding_need.g43 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_funding_need` DROP COLUMN `g43`")) { echo "- fin_funding_need.g43\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g42'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g42` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g42 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g42`")) { echo "- fin_financier_contact.g42\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g41'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g41` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g41 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g41`")) { echo "- fin_financier_contact.g41\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g40'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g40` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g40 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g40`")) { echo "- fin_financier_contact.g40\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g39'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g39` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g39 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g39`")) { echo "- fin_financier_contact.g39\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g38'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g38` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g38 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g38`")) { echo "- fin_financier_contact.g38\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g37'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g37` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g37 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g37`")) { echo "- fin_financier_contact.g37\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g36'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g36` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g36 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g36`")) { echo "- fin_financier_contact.g36\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g35'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g35` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g35 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g35`")) { echo "- fin_financier_contact.g35\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g34'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g34` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g34 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g34`")) { echo "- fin_financier_contact.g34\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g33'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g33` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g33 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g33`")) { echo "- fin_financier_contact.g33\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g32'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g32` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g32 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g32`")) { echo "- fin_financier_contact.g32\n"; }
}

$q = $conn->query("SHOW COLUMNS FROM `fin_financier_contact` LIKE 'g31'");
if ($q && $q->num_rows) {
    $r = $conn->query("SELECT COUNT(*) FROM `fin_financier_contact` WHERE `g31` IS NOT NULL");
    $n = $r ? (int) $r->fetch_row()[0] : 0;
    if ($n > 0) { echo "أُبقي fin_financier_contact.g31 لبياناتِه ($n)\n"; }
    elseif ($conn->query("ALTER TABLE `fin_financier_contact` DROP COLUMN `g31`")) { echo "- fin_financier_contact.g31\n"; }
}

$r = $conn->query('SELECT COUNT(*) FROM `fin_close_audit`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fin_close_audit لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fin_close_audit`')) { echo '- جدول fin_close_audit
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fin_migration_map`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fin_migration_map لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fin_migration_map`')) { echo '- جدول fin_migration_map
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fin_asset_disposal`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fin_asset_disposal لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fin_asset_disposal`')) { echo '- جدول fin_asset_disposal
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fin_capital_balance`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fin_capital_balance لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fin_capital_balance`')) { echo '- جدول fin_capital_balance
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fin_monthly_close_stmt`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fin_monthly_close_stmt لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fin_monthly_close_stmt`')) { echo '- جدول fin_monthly_close_stmt
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fin_financier_due`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fin_financier_due لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fin_financier_due`')) { echo '- جدول fin_financier_due
'; }

$r = $conn->query('SELECT COUNT(*) FROM `fin_financier`');
$n = $r ? (int) $r->fetch_row()[0] : 0;
if ($n > 0) { echo 'ابقي fin_financier لبياناته: ' . $n . chr(10); }
elseif ($conn->query('DROP TABLE IF EXISTS `fin_financier`')) { echo '- جدول fin_financier
'; }

ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
