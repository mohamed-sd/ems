<?php
/**
 * 2028_04_17_navarch02_lifecycle_heads_down.php — عكسُ رؤوسِ الطيِّ المُنشأة
 * ◆ يحذف الرؤوسَ التي أنشأتها الهجرةُ وحدَها — بشهادةِ `source_ref` — بعد
 *   فكِّ ارتباطِ أيِّ موضعٍ بها (‏`fk_np_grp` يمنع حذفَ رأسٍ مرتبط).
 * ⛔ ولا يُنشئ هذا الملفُّ شيئًا — عكسٌ محضٌ [[rpr0-migration-ledger-gate]].
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("connect fail\n"); }
$conn->set_charset('utf8mb4');

$ids = array();
/* بصمةُ الهجرةِ في `source_ref` — ولا يُحذَف رأسٌ لم تُنشئه هي */
$r = $conn->query("SELECT id FROM nav_lifecycle_groups
                    WHERE source_ref LIKE '%gov_target_nav.group_ar%'");
while ($r && ($x = $r->fetch_row())) { $ids[] = (int) $x[0]; }
if ($ids) {
    $in = implode(',', $ids);
    $conn->query("UPDATE nav_workspace_placements SET group_id = NULL WHERE group_id IN ({$in})");
    echo "= فُكَّ ارتباطُ " . $conn->affected_rows . " موضعًا\n";
    $conn->query("UPDATE nav_placements SET active = 0 WHERE group_id IN ({$in})");
    $conn->query("DELETE FROM nav_lifecycle_groups WHERE id IN ({$in})");
    echo "- رؤوسٌ: " . $conn->affected_rows . "\n";
} else {
    echo "= لا رأسَ من هذه الهجرة\n";
}
$conn->query("DELETE FROM `schema_migrations`
               WHERE `filename` = '2028_04_17_navarch02_lifecycle_heads.php'");
echo "- قيدُ الدفتر: " . $conn->affected_rows . "\n";
