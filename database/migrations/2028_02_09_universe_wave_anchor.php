<?php
/**
 * 2028_02_09_universe_wave_anchor.php — مفردةُ مطابقةٍ جديدة: مرساةُ الموجة (GOV_EXEC §5)
 * ═══════════════════════════════════════════════════════════════════════════
 * @migration-objects: alter:repair01_target_universe(match_method +WAVE_ANCHOR)
 * أحكامُ الكونِ قيست بمطابقةِ الاسمِ قبل أن ترسو الموجاتُ متطلّباتِها على
 * شاشاتِها بمراسٍ مقيسةٍ من القرص (repair01_w*_scope.anchor_screen_id) —
 * فالجسرُ يستنفد دفاترَ المراسي قبل حكمِ NOT_BUILT (§13: لا NO_SPEC قبل
 * استنفادِ مصادرِ النطاق). المفردةُ الجديدةُ تسمّي هذا الطريقَ باسمِه.
 * التشغيل: php database/migrations/2028_02_09_universe_wave_anchor.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
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
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$t0 = microtime(true);

$ok = $conn->query("ALTER TABLE repair01_target_universe
    MODIFY match_method ENUM('EXACT_UNIT','EXACT_ANY','COMPOUND_SPLIT','CONTAINMENT_CANDIDATE','WAVE_ANCHOR','NONE')
        NOT NULL DEFAULT 'NONE'
        COMMENT 'طريق المطابقة — WAVE_ANCHOR: مرساة دفتر موجة مثبتة من القرص'");
if (!$ok) { exit("⛔ فشل: {$conn->error}\n"); }
echo "✔ WAVE_ANCHOR أُضيفت لمفرداتِ المطابقة\n";
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
