<?php
/* tools/baseline_db_dump2.php — BL-20260821: إعادة الاستعلامات الفاشلة بأسماء الأعمدة الحقيقية */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();
$OUT = $ROOT . '/docs/baseline_20260821/extract';

function dump_q($db, $name, $sql, $out)
{
    $r = $db->query($sql);
    if (!$r) { echo "FAIL $name: " . $db->error . "\n"; return; }
    $rows = array();
    while ($x = $r->fetch_assoc()) { $rows[] = $x; }
    file_put_contents($out . '/' . $name . '.json', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo str_pad($name, 34) . count($rows) . "\n";
}

dump_q($db, 'nav_items', "SELECT n.*, r.name AS role_name, lg.name AS group_name, lg.group_code, lg.display_order AS group_order, lg.stage_no
    FROM nav_items n
    LEFT JOIN roles r ON r.id = n.role_id
    LEFT JOIN link_groups lg ON lg.id = n.group_id
    WHERE n.active = 1
    ORDER BY n.role_id, lg.display_order, n.sort_order", $OUT);
dump_q($db, 'link_groups', 'SELECT * FROM link_groups WHERE is_active=1 ORDER BY owner_role_id, display_order', $OUT);
dump_q($db, 'gov_profile_items', 'SELECT * FROM gov_profile_items ORDER BY profile_id, item_kind, item_ref', $OUT);
dump_q($db, 'gov_role_profiles', 'SELECT * FROM gov_role_profiles ORDER BY 1', $OUT);
dump_q($db, 'gov_ladder_actor_roles', 'SELECT * FROM gov_ladder_actor_roles ORDER BY actor_code', $OUT);
dump_q($db, 'gov_authority_grants', 'SELECT * FROM gov_authority_grants ORDER BY grant_id', $OUT);
dump_q($db, 'gov_ownership_rulings', 'SELECT * FROM gov_ownership_rulings ORDER BY route', $OUT);
dump_q($db, 'nav09_action_map', 'SELECT * FROM nav09_action_map ORDER BY canonical_code', $OUT);
dump_q($db, 'actions_registry', 'SELECT * FROM actions ORDER BY action_code', $OUT);
dump_q($db, 'event_types', "SELECT category, event_key, COUNT(*) AS cnt, SUM(delivered_ok) AS delivered_ok_sum,
        SUM(delivered_failed) AS delivered_failed_sum, SUM(in_dlq) AS dlq_sum,
        SUM(is_training) AS training_cnt, MIN(occurred_at) AS first_at, MAX(occurred_at) AS last_at
    FROM ems_business_events GROUP BY category, event_key ORDER BY cnt DESC", $OUT);
echo "تم\n";
