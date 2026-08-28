<?php
/**
 * tools/baseline_db_dump5.php — BL-20260828: تفريغ سجل REPAIR01 الرسمي
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لماذا: النظام صار يملك **سجل شاشات وحقول وملكية رسميًّا** (`repair01_*`)
 *   بمعرّفات `SCR-nnnn` ورموز إدارات `DEP-nn` — وهو المعرّف المشترك الذي
 *   تطلبه الحزمة. فيُقرأ مصدرًا حاكمًا، ويُصالَح معه استخراجُ القرص لا يُستبدل به.
 * ◆ قراءة فقط.
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();
$OUT = $ROOT . '/docs/baseline_20260821/extract';

function dq($db, $name, $sql, $out)
{
    $r = $db->query($sql);
    if (!$r) { echo "FAIL $name: " . $db->error . "\n"; return; }
    $rows = array();
    while ($x = $r->fetch_assoc()) { $rows[] = $x; }
    file_put_contents($out . '/' . $name . '.json', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    printf("%-34s %6d\n", $name, count($rows));
}

dq($db, 'rp01_screen_registry', 'SELECT * FROM repair01_screen_registry ORDER BY screen_id', $OUT);
dq($db, 'rp01_departments', 'SELECT * FROM repair01_departments ORDER BY display_order', $OUT);
dq($db, 'rp01_ownership', 'SELECT * FROM repair01_ownership ORDER BY id', $OUT);
dq($db, 'rp01_surfaces', 'SELECT * FROM repair01_surfaces ORDER BY id', $OUT);
dq($db, 'rp01_fields', 'SELECT * FROM repair01_fields ORDER BY id', $OUT);
dq($db, 'rp01_requirements', 'SELECT * FROM repair01_requirements ORDER BY requirement_id', $OUT);
dq($db, 'rp01_decisions', 'SELECT * FROM repair01_decisions ORDER BY 1', $OUT);
dq($db, 'rp01_debt_register', 'SELECT * FROM repair01_debt_register ORDER BY 1', $OUT);
dq($db, 'rp01_target_gaps', 'SELECT * FROM repair01_target_gaps ORDER BY 1', $OUT);
dq($db, 'rp01_key_registry', 'SELECT * FROM repair01_key_registry ORDER BY 1', $OUT);
dq($db, 'rp01_master_entities', 'SELECT * FROM repair01_master_entities ORDER BY 1', $OUT);
dq($db, 'rp01_w16_scorecard', 'SELECT * FROM repair01_w16_scorecard ORDER BY 1', $OUT);
dq($db, 'rp01_w16_baseline', 'SELECT * FROM repair01_w16_baseline ORDER BY 1', $OUT);
echo "تم\n";
