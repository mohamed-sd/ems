<?php
/**
 * tools/baseline_db_dump3.php — BL-20260821b: تفريغ السجلات الحاكمة المستجدّة
 * بعد جولتَي INJ-FIX-01/02 (15 جدولًا جديدًا). قراءة فقط.
 * التشغيل: php tools/baseline_db_dump3.php
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();
$OUT = $ROOT . '/docs/baseline_20260821/extract';
if (!is_dir($OUT)) { mkdir($OUT, 0777, true); }

function dq($db, $name, $sql, $out)
{
    $r = $db->query($sql);
    if (!$r) { echo "FAIL $name: " . $db->error . "\n"; return; }
    $rows = array();
    while ($x = $r->fetch_assoc()) { $rows[] = $x; }
    file_put_contents($out . '/' . $name . '.json', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo str_pad($name, 36) . count($rows) . "\n";
}

/* سجلات الأحكام المستجدّة — كل صفٍّ حكمُ مالكٍ بشاهد */
dq($db, 'gov_event_rulings', 'SELECT * FROM gov_event_rulings ORDER BY event_key', $OUT);
dq($db, 'gov_path_rulings', 'SELECT * FROM gov_path_rulings ORDER BY id', $OUT);
dq($db, 'gov_dead_letter_rulings', 'SELECT * FROM gov_dead_letter_rulings ORDER BY id', $OUT);
dq($db, 'gov_finance_gate_policy', 'SELECT * FROM gov_finance_gate_policy ORDER BY id', $OUT);
dq($db, 'gov_delegation_state', 'SELECT * FROM gov_delegation_state ORDER BY id', $OUT);
dq($db, 'gov_sensitive_policy_debt', 'SELECT * FROM gov_sensitive_policy_debt ORDER BY id', $OUT);
dq($db, 'gov_topbar_exemptions', 'SELECT * FROM gov_topbar_exemptions ORDER BY id', $OUT);
dq($db, 'gov_nav_reference_standard', 'SELECT * FROM gov_nav_reference_standard ORDER BY layer_no, stage_order', $OUT);
dq($db, 'gov_nav_stage_bridge', 'SELECT * FROM gov_nav_stage_bridge ORDER BY id', $OUT);
dq($db, 'ems_event_delivery_orphans', 'SELECT * FROM ems_event_delivery_orphans ORDER BY id', $OUT);

/* عدّادات الأرشفة — لا حاجة لحمولاتها */
$counts = array();
foreach (array('gov_key_pollution_archive', 'gov_test_residue_archive', 'gov_cycle_consumers_backup',
    'ems_job_schedule_alert_backup', 'work_delegations_seed_archive') as $t) {
    $r = $db->query('SELECT COUNT(*) FROM `' . $t . '`');
    $counts[$t] = $r ? (int) $r->fetch_row()[0] : null;
}
file_put_contents($OUT . '/archive_counts.json', json_encode($counts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo str_pad('archive_counts', 36) . count($counts) . "\n";
