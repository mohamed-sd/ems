<?php
/**
 * 2028_03_13_govui_iaf_field_closure_down.php — العكسُ من العقدِ لا من الذاكرة
 * @migration-objects: revert gov_field_class labels, drop added columns
 * ◆ **مصدرُ الرجوعِ العقدُ** (`_iaf_field_closure_spec.php`) — والسجلُّ دليلُ ما
 *   جرى لا شرطُ الرجوع. فلو ضاع السجلُّ (وقد ضاع مرّةً ببوّابةِ المستأجر)
 *   بقي الطريقُ مفتوحًا.
 * ⛔ **ولا يُسقَط عمودٌ ولا يُردُّ وسمٌ إلّا إن كان على حالِ ما كتبته الدفعة** —
 *   فوسمٌ غيَّره غيرُنا بعدُ يبقى له، وعمودٌ فيه بياناتٌ **لا يُسقَط صامتًا**
 *   بل يُسمّى ويُترَك.
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
$BATCH = 'IAF_FIELD_CLOSURE';
$SPEC  = require __DIR__ . '/_iaf_field_closure_spec.php';

$nDel = 0; $nDrop = 0; $nBack = 0; $kept = array();

foreach ($SPEC as $screen => $s) {
    $tbl = $s['table'];

    /* ① القيدُ يُحذف ثمَّ العمودُ يُسقَط — والعمودُ ذو البياناتِ يُترَك مسمًّى */
    foreach ($s['add'] as $a) {
        $key = $a[0];
        $st = $conn->prepare("DELETE FROM gov_field_class WHERE screen_code = ? AND field_key = ? AND label_ar = ?");
        $st->bind_param('sss', $screen, $key, $a[2]); $st->execute();
        $nDel += $st->affected_rows; $st->close();

        $q = $conn->query("SHOW COLUMNS FROM `{$tbl}` LIKE '" . $conn->real_escape_string($key) . "'");
        if (!$q || !$q->num_rows) { continue; }
        $r = $conn->query("SELECT COUNT(*) FROM `{$tbl}` WHERE `{$key}` IS NOT NULL");
        $used = $r ? (int) $r->fetch_row()[0] : 0;
        if ($used > 0) { $kept[] = "{$tbl}.{$key} ({$used} صفًّا فيه قيمة)"; continue; }
        if ($conn->query("ALTER TABLE `{$tbl}` DROP COLUMN `{$key}`")) { $nDrop++; }
    }

    /* ② الأوسمةُ تُردُّ إلى مفردةِ التنفيذِ — إن كانت ما تزال على ما كتبناه */
    foreach ($s['rename'] as $key => $r) {
        list($new, $was) = $r;
        $st = $conn->prepare("UPDATE gov_field_class SET label_ar = ?
                               WHERE screen_code = ? AND field_key = ? AND label_ar = ?");
        $st->bind_param('ssss', $was, $screen, $key, $new); $st->execute();
        $nBack += $st->affected_rows; $st->close();
    }
}

$conn->query("DELETE FROM govui_field_closure_log WHERE batch = '{$BATCH}'");
printf("قيود محذوفة %d · أعمدة مُسقَطة %d · أوسمة مردودة %d\n", $nDel, $nDrop, $nBack);
if ($kept) { echo "أُبقيت لبياناتها:\n"; foreach ($kept as $x) { echo "  - {$x}\n"; } }
ems_migration_recorded(__FILE__, $conn, (int) round((microtime(true) - $t0) * 1000));
