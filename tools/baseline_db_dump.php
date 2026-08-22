<?php
/**
 * tools/baseline_db_dump.php — BL-20260821: تفريغ السجلات الحاكمة إلى JSON
 * قراءة فقط — لا يكتب في القاعدة شيئًا.
 * التشغيل: php tools/baseline_db_dump.php
 * الإخراج: docs/baseline_20260821/extract/*.json
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once __DIR__ . '/fix_lib.php';
$db = fix_db();
$OUT = $ROOT . '/docs/baseline_20260821/extract';
if (!is_dir($OUT)) { mkdir($OUT, 0777, true); }

function dump_q($db, $name, $sql, $out)
{
    $r = $db->query($sql);
    if (!$r) { echo "FAIL $name: " . $db->error . "\n"; return 0; }
    $rows = array();
    while ($x = $r->fetch_assoc()) { $rows[] = $x; }
    file_put_contents($out . '/' . $name . '.json', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo str_pad($name, 34) . count($rows) . "\n";
    return count($rows);
}

/* السجلات الحاكمة للشاشات */
dump_q($db, 'gov_migration_ledger', 'SELECT * FROM gov_migration_ledger ORDER BY dept, layer, stage, id', $OUT);
dump_q($db, 'gov_screen_cycle', 'SELECT * FROM gov_screen_cycle ORDER BY dept_name, stage_order, id', $OUT);
dump_q($db, 'gov_space_appearances', 'SELECT * FROM gov_space_appearances ORDER BY space_ar, tab_ar, id', $OUT);
dump_q($db, 'nav09_file_map', 'SELECT * FROM nav09_file_map ORDER BY canonical_file', $OUT);

/* التنقّل */
dump_q($db, 'nav_items', "SELECT n.*, r.name AS role_name, lg.title AS group_title, lg.sort_order AS group_sort
    FROM nav_items n
    LEFT JOIN roles r ON r.id = n.role_id
    LEFT JOIN link_groups lg ON lg.id = n.group_id
    WHERE n.active = 1
    ORDER BY n.role_id, lg.sort_order, n.sort_order", $OUT);
dump_q($db, 'link_groups', 'SELECT * FROM link_groups WHERE is_active=1 ORDER BY role_id, sort_order', $OUT);
dump_q($db, 'modules', 'SELECT * FROM modules ORDER BY id', $OUT);

/* الأدوار والصلاحيات */
dump_q($db, 'roles', 'SELECT * FROM roles ORDER BY id', $OUT);
dump_q($db, 'role_permissions', 'SELECT * FROM role_permissions ORDER BY role_id, module_id', $OUT);
dump_q($db, 'gov_profile_items', 'SELECT * FROM gov_profile_items ORDER BY id', $OUT);
dump_q($db, 'gov_role_profiles', 'SELECT * FROM gov_role_profiles ORDER BY id', $OUT);
dump_q($db, 'users_by_role', "SELECT r.id AS role_id, r.name AS role_name, COUNT(u.id) AS users_active
    FROM roles r LEFT JOIN users u ON u.role_id = r.id AND u.is_deleted = 0
    GROUP BY r.id, r.name ORDER BY r.id", $OUT);

/* الحقول والحساسية */
dump_q($db, 'gov_field_class', 'SELECT * FROM gov_field_class ORDER BY screen_code, field_key', $OUT);
dump_q($db, 'gov_data_classes', 'SELECT * FROM gov_data_classes ORDER BY id', $OUT);
dump_q($db, 'scr_sensitive_fields', 'SELECT * FROM scr_sensitive_fields ORDER BY id', $OUT);

/* السلالم والاعتماد */
dump_q($db, 'gov_ladders', 'SELECT * FROM gov_ladders ORDER BY ladder_code', $OUT);
dump_q($db, 'gov_ladder_steps', 'SELECT * FROM gov_ladder_steps ORDER BY ladder_code', $OUT);
dump_q($db, 'gov_ladder_actor_roles', 'SELECT * FROM gov_ladder_actor_roles ORDER BY id', $OUT);
dump_q($db, 'gov_journey_ladders', 'SELECT * FROM gov_journey_ladders ORDER BY journey_code, seq_no', $OUT);
dump_q($db, 'gov_authority_grants', 'SELECT * FROM gov_authority_grants ORDER BY id', $OUT);
dump_q($db, 'gov_authority_limits', 'SELECT * FROM gov_authority_limits ORDER BY id', $OUT);

/* خرائط الأثر والمخرجات */
dump_q($db, 'gov_effect_map', 'SELECT * FROM gov_effect_map ORDER BY id', $OUT);
dump_q($db, 'gov_stage_outputs', 'SELECT * FROM gov_stage_outputs ORDER BY id', $OUT);
dump_q($db, 'gov_ownership_rulings', 'SELECT * FROM gov_ownership_rulings ORDER BY id', $OUT);
dump_q($db, 'gov_doc_registry', 'SELECT * FROM gov_doc_registry ORDER BY id', $OUT);

/* الأفعال */
dump_q($db, 'nav09_action_map', 'SELECT * FROM nav09_action_map ORDER BY id', $OUT);
dump_q($db, 'actions_registry', 'SELECT * FROM actions ORDER BY id', $OUT);

/* الأحداث — أنواع وأعداد فقط (لا حمولات) */
dump_q($db, 'event_types', "SELECT event_type, COUNT(*) AS cnt, MIN(created_at) AS first_at, MAX(created_at) AS last_at
    FROM ems_business_events GROUP BY event_type ORDER BY cnt DESC", $OUT);

/* بنية القاعدة: الجداول وأعمدتها وملكية company_id */
/* ◆ صُحِّح (F-B04 · BL-20260822): كانت باستعلامَين مترابطَين لكل جدول فتعثّرت
 *   أكثر من نصف ساعة عند 618 جدولًا. التجميع يعطي الناتج نفسه في ثوانٍ. */
dump_q($db, 'db_tables', "SELECT t.TABLE_NAME, t.TABLE_TYPE, t.TABLE_ROWS,
        COUNT(c.COLUMN_NAME) AS col_count,
        SUM(c.COLUMN_NAME = 'company_id') AS has_company
    FROM information_schema.TABLES t
    LEFT JOIN information_schema.COLUMNS c
           ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
    WHERE t.TABLE_SCHEMA = DATABASE()
    GROUP BY t.TABLE_NAME, t.TABLE_TYPE, t.TABLE_ROWS
    ORDER BY t.TABLE_NAME", $OUT);
dump_q($db, 'db_fks', "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME", $OUT);

/* أعداد الصفوف الفعلية للجداول الحاكمة (TABLE_ROWS تقديري في InnoDB) */
$exact = array();
$govTables = array('ems_business_events','fin_event_links','fin_financial_events','approval_requests','approval_signatures',
    'gov_profile_items','role_permissions','nav_items','users','ems_saved_views');
foreach ($govTables as $t) {
    $r = $db->query('SELECT COUNT(*) FROM `' . $t . '`');
    $exact[$t] = $r ? (int) $r->fetch_row()[0] : null;
}
file_put_contents($OUT . '/exact_counts.json', json_encode($exact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo str_pad('exact_counts', 34) . count($exact) . "\n";
echo "تم — " . date('Y-m-d H:i:s') . "\n";
