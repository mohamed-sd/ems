<?php
/**
 * 2027_12_03_repair01_w14_control_down.php — تراجعُ هجرةِ W14
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يُسقط ما أنشأته الهجرةُ ولا يمسُّ جدولًا حيًّا سابقًا لها.**
 *   و`iaf_findings` **جدولٌ حيّ**: يُنزَع منه القيدانِ والعمودانِ اللذانِ
 *   أضافتهما الهجرةُ وحدَها — ⛔ ولا يُحذف صفٌّ ولا عمودٌ آخر.
 *
 * ⛔ **ولا `DROP DATABASE` ولا `TRUNCATE`** — `ems_app` لا يملك `DROP` أصلًا،
 *   والتراجعُ يُشغَّل بمستخدمِ الهجرات.
 *
 * التشغيل: php database/migrations/2027_12_03_repair01_w14_control_down.php
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
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $done++; return true; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++; return false;
};
$has = function ($t) use ($conn) {
    $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
};
$col = function ($t, $c) use ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};

echo "══ تراجعُ REPAIR01 · W14 ══\n\n";

/* ① نزعُ ما أُضيف إلى الجدولِ الحيِّ — ولا يُمَسُّ سواه */
if ($has('iaf_findings')) {
    foreach (array('chk_iaf_result_dept', 'chk_iaf_close_dept') as $c) {
        $x = $conn->query("SELECT 1 FROM information_schema.CHECK_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$c'");
        if ($x && $x->num_rows) { $run("ALTER TABLE `iaf_findings` DROP CONSTRAINT `$c`", "نزع القيد $c"); }
    }
    foreach (array('result_set_by_dept', 'result_closed_by_dept') as $c) {
        if ($col('iaf_findings', $c)) { $run("ALTER TABLE `iaf_findings` DROP COLUMN `$c`", "نزع العمود $c"); }
    }
}

/* ② إسقاطُ جداولِ الموجةِ — بالترتيبِ العكسيِّ لإنشائها */
$TABLES = array(
    'iaf_function_risk', 'iaf_sample', 'iaf_evidence_request', 'iaf_program',
    'rsk_closure', 'rsk_event', 'rsk_trigger', 'rsk_taxonomy',
    'gov_request_type', 'gov_committee', 'gov_audit_followup', 'gov_corrective_action',
    'gov_breach', 'gov_investigation', 'gov_integrity_report', 'gov_sod_conflict',
    'gov_conduct_ack', 'gov_gift_disclosure', 'gov_related_party', 'gov_conflict_disclosure',
    'gov_filing', 'gov_compliance_due', 'gov_obligation', 'gov_policy',
    'ctl_deviation', 'ctl_classification_rule',
    'repair01_w14_domains', 'repair01_w14_nav_moves', 'repair01_w14_journey',
    'repair01_w14_fixes', 'repair01_w14_thresholds', 'repair01_w14_sod',
    'repair01_w14_states', 'repair01_w14_deferred', 'repair01_w14_decisions',
    'repair01_w14_sidebar', 'repair01_w14_scope',
);
foreach ($TABLES as $t) {
    if ($has($t)) { $run("DROP TABLE `$t`", "إسقاط $t"); }
}

/* ③ عقودُ الأثرِ الخاصّةُ بالموجة */
$run("DELETE FROM repair01_events WHERE wave = 'W14'", 'حذف عقود أثر W14');

echo "\n──────────────────────────────────────────────────────────────\n";
printf("منفَّذٌ %d · فشلٌ %d\n", $done, $err);
echo $err === 0 ? "الحكم: تراجعت ✔\n" : "الحكم: فشل التراجع ✘\n";
exit($err === 0 ? 0 : 1);
