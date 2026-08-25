<?php
/**
 * 2027_11_18_repair01_w3_masters_down.php — تراجعُ W03
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الترتيبُ مقصود**: القوادحُ أوّلًا — قادحٌ باقٍ على عمودٍ محذوفٍ يمنع
 *   كلَّ كتابةٍ في `persons` بخطأٍ غامض.
 * ◆ **وأعمدةُ عقدِ الأثرِ في `repair01_events` تُنزع كلُّها**: الجدولُ نفسُه
 *   من مخزنِ W00 ولا يُحذف — والمنزوعُ ما أضافته هذه المرحلةُ وحدَه.
 * ◆ `nav_canonical.screen_id` عمودٌ مضافٌ لا يقرؤه مُصيِّرٌ حيّ — نزعُه لا
 *   يُطفئ رابطًا (وهذا مقيسٌ: المُصيِّرُ يقرأ `route` و`canonical_ar`
 *   و`group_name` و`sort_no` و`status` و`anchor_key` — لا `screen_id`).
 *
 * التشغيل: php database/migrations/2027_11_18_repair01_w3_masters_down.php
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

function w3d_col(mysqli $c, $t, $col)
{
    $r = $c->query("SHOW COLUMNS FROM `$t` LIKE '" . $c->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

$n = 0; $err = 0;
$run = function ($sql, $label) use ($conn, &$n, &$err) {
    if ($conn->query($sql) === true) { echo "  ✔ $label\n"; $n++; return; }
    echo "  ✘ $label — " . $conn->error . "\n"; $err++;
};

echo "══ تراجعُ REPAIR01 · W03 ══\n\n";

/* ① القوادحُ أوّلًا */
foreach (array('trg_persons_w3_bi', 'trg_persons_w3_bu') as $t) {
    $run("DROP TRIGGER IF EXISTS `$t`", "قادح $t");
}

/* ② أعمدةُ persons */
foreach (array('w3_link_rule', 'person_class', 'employee_id', 'company_id') as $c) {
    if (w3d_col($conn, 'persons', $c)) { $run("ALTER TABLE `persons` DROP COLUMN `$c`", "persons.$c"); }
    else { echo "  ⟳ persons.$c منزوعٌ سلفًا\n"; }
}

/* ③ nav_canonical.screen_id */
if (w3d_col($conn, 'nav_canonical', 'screen_id')) { $run("ALTER TABLE `nav_canonical` DROP COLUMN `screen_id`", 'nav_canonical.screen_id'); }
else { echo "  ⟳ nav_canonical.screen_id منزوعٌ سلفًا\n"; }

/* ④ أعمدةُ عقدِ الأثرِ في repair01_events — الجدولُ من مخزنِ W00 فلا يُحذف */
foreach (array('trigger_rule', 'min_payload', 'consumer_list', 'consumer_effect', 'preconditions',
               'failure_policy', 'compensation', 'contract_status', 'contract_rule', 'contract_stage') as $c) {
    if (w3d_col($conn, 'repair01_events', $c)) { $run("ALTER TABLE `repair01_events` DROP COLUMN `$c`", "repair01_events.$c"); }
    else { echo "  ⟳ repair01_events.$c منزوعٌ سلفًا\n"; }
}

/* ⑤ جداولُ المرحلة */
foreach (array('repair01_w3_journey', 'repair01_w3_sidebar', 'repair01_w3_scope',
               'repair01_w3_decisions', 'repair01_master_entities', 'repair01_key_alias',
               'repair01_key_registry') as $t) {
    $run("DROP TABLE IF EXISTS `$t`", "جدول $t");
}

echo "\n";
printf("الحصيلة: %d خطوةً · أخطاء %d\n", $n, $err);
echo ($err === 0 ? "الحكم: رجعت ✔\n" : "الحكم: أخطاء ✘\n");
$conn->close();
exit($err === 0 ? 0 : 1);
