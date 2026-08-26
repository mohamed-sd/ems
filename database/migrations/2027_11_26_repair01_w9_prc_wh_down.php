<?php
/**
 * 2027_11_26_repair01_w9_prc_wh_down.php — تراجعُ هجرةِ W09
 * ═══════════════════════════════════════════════════════════════════════════
 * ⚠ **يُسقط جداولَ المرحلةِ وبياناتَها** — ولا يُشغَّل إلّا بقصدِ التراجعِ الكامل.
 * ◆ **والأعمدةُ الملحقةُ بجداولَ حيّةٍ تُنزَع أيضًا** — وهي التي أضافتها §② و§④ و§⑤.
 * ◆ **والترتيبُ يحترم المفاتيحَ الأجنبيّة**: الأبناءُ قبل الآباء.
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

$done = 0; $err = 0;
$run = function ($sql, $label) use ($conn, &$done, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; }
    else { echo "  ✘ $label — " . $conn->error . "\n"; $err++; }
};
$dropCol = function ($t, $c) use ($conn, &$done, &$err) {
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    if (!$r || $r->num_rows === 0) { echo "  ↷ $t.$c غير موجود\n"; return; }
    if ($conn->query("ALTER TABLE `$t` DROP COLUMN `$c`") === true) { echo "  ✔ $t.$c\n"; $done++; }
    else { echo "  ✘ $t.$c — " . $conn->error . "\n"; $err++; }
};

echo "══ تراجعُ REPAIR01 · W09 ══\n\n";

/* الأبناءُ أوّلًا */
foreach (array(
    'proc_count_line', 'proc_count_session', 'proc_transfer_line', 'proc_transfer',
    'proc_issue_request_line', 'proc_issue_request', 'proc_wh_close',
    'proc_hazmat_control', 'proc_stock_state',
    'proc_supplier_eval', 'proc_invoice_match', 'proc_delivery_event', 'proc_po_amendment',
    'proc_award', 'proc_offer_line', 'proc_offer', 'proc_rfq_invite', 'proc_rfq',
    'proc_package_member', 'proc_package', 'proc_item_track_rule',
    'repair01_w9_fixes', 'repair01_w9_thresholds', 'repair01_w9_sod', 'repair01_w9_states',
    'repair01_w9_journey', 'repair01_w9_deferred', 'repair01_w9_decisions',
    'repair01_w9_sidebar', 'repair01_w9_scope',
) as $t) {
    $run("DROP TABLE IF EXISTS `$t`", $t);
}

echo "\nالأعمدةُ الملحقةُ بجداولَ حيّة ─────────────────────────────────\n";
foreach (array('track_lot', 'track_serial', 'track_expiry', 'track_rule_ref') as $c) { $dropCol('proc_item', $c); }
foreach (array('package_id', 'award_minute_id', 'direct_reason', 'amend_count') as $c) { $dropCol('proc_order', $c); }
foreach (array('qty_received', 'qty_accepted', 'qty_rejected', 'reject_reason',
               'lot_no', 'serial_no', 'expiry_date', 'unit_cost', 'order_line_id') as $c) {
    $dropCol('proc_receipt_line', $c);
}

echo "\n───────────────────────────────────────────────────────────────\n";
echo "الخلاصة: نُفِّذ $done · أخطاء $err\n";
exit($err > 0 ? 1 : 0);
