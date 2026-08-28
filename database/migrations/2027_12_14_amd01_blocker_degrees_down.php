<?php
/**
 * 2027_12_14_amd01_blocker_degrees_down.php — نقضُ درجاتِ الحجبِ الست
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **القواعدُ تُسقَط قبلَ أعمدتِها** · ⛔ **ولا تُضيَّق مفردةٌ فيها قيمٌ حيّة**:
 *   تضييقُ `ENUM` فوقَ صفٍّ يحمل قيمةً مُزالةً يجعلها فارغةً صامتًا — فتُفحص
 *   أوّلًا ويُردّ النقضُ إن وُجدت.
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

foreach (array('chk_dec_cfg_stage', 'chk_dec_verdict_ref') as $c) {
    $r = $conn->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$c'");
    if ($r && (int) $r->fetch_row()[0] > 0) {
        $conn->query("ALTER TABLE `repair01_decisions` DROP CONSTRAINT `$c`");
        echo "  ✔ أُسقطت `$c`\n";
    }
}
$r = $conn->query("SELECT COUNT(*) FROM repair01_decisions
                    WHERE blocking_level IN ('STRUCTURAL_BLOCKER','BUILD_BLOCKER','ENFORCEMENT_BLOCKER')");
$live = $r ? (int) $r->fetch_row()[0] : 0;
if ($live > 0) {
    exit("⛔ **$live صفًّا يحمل مفردةً من الجديدةِ** — والتضييقُ يمحوها صامتًا. أعِدْ حكمَها أوّلًا.\n");
}
$conn->query("ALTER TABLE `repair01_decisions`
    MODIFY COLUMN `blocking_level` enum('STRUCTURAL_TARGET_BLOCKER','READY_TO_BUILD_BLOCKER',
        'UAT_BLOCKER','GO_LIVE_BLOCKER','CONFIG_PENDING','NONE') NULL");
echo "  ✔ ضُيِّقت المفردةُ إلى القديمة\n";
foreach (array('amd01_verdict_ref', 'amd01_verdict', 'config_pending_stage') as $c) {
    $r = $conn->query("SHOW COLUMNS FROM `repair01_decisions` LIKE '$c'");
    if ($r && $r->num_rows) {
        $conn->query("ALTER TABLE `repair01_decisions` DROP COLUMN `$c`");
        echo "  ✔ أُسقط `$c`\n";
    }
}
echo "✔ نُقضت درجاتُ الحجبِ الست\n";
