<?php
/**
 * tools/baseline_db_dump4.php — BL-20260821b: ذيلُ التفريغ بصيغةٍ تجميعيةٍ سريعة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ لماذا هذا الملف: `baseline_db_dump.php` يبني `db_tables` باستعلامَين
 *   **مترابطَين** (correlated) لكلِّ جدول — وعند 618 جدولًا تعثّر أكثرَ من نصفِ
 *   ساعة. والصيغةُ التجميعيةُ (JOIN + GROUP BY) تُنتج الجدولَ نفسَه في ثوانٍ.
 *   فالقياسُ واحدٌ والمنهجُ أسرع — لا يُغيَّر مقامٌ ولا تعريف.
 * ◆ قراءة فقط.
 * التشغيل: php tools/baseline_db_dump4.php
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
    $t0 = microtime(true);
    $r = $db->query($sql);
    if (!$r) { echo "FAIL $name: " . $db->error . "\n"; return; }
    $rows = array();
    while ($x = $r->fetch_assoc()) { $rows[] = $x; }
    file_put_contents($out . '/' . $name . '.json', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    printf("%-24s %6d  (%.1fs)\n", $name, count($rows), microtime(true) - $t0);
}

/* بنيةُ القاعدة — تجميعٌ لا ترابط */
dq($db, 'db_tables', "SELECT t.TABLE_NAME, t.TABLE_TYPE, t.TABLE_ROWS,
        COUNT(c.COLUMN_NAME) AS col_count,
        SUM(c.COLUMN_NAME = 'company_id') AS has_company
    FROM information_schema.TABLES t
    LEFT JOIN information_schema.COLUMNS c
           ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
    WHERE t.TABLE_SCHEMA = DATABASE()
    GROUP BY t.TABLE_NAME, t.TABLE_TYPE, t.TABLE_ROWS
    ORDER BY t.TABLE_NAME", $OUT);

dq($db, 'db_fks', "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME", $OUT);

/* أعدادٌ دقيقة — COUNT(*) لا TABLE_ROWS التقديري */
$exact = array();
foreach (array('ems_business_events','fin_event_links','fin_financial_events','fin_journal_entries',
    'fin_journal_lines','approval_requests','approval_steps','approval_signatures','approval_links',
    'gov_profile_items','role_permissions','nav_items','users','ems_saved_views','ems_job_queue',
    'ems_event_deliveries','gov_ownership_rulings','gov_space_appearances','gov_screen_cycle',
    'gov_migration_ledger','gov_field_class','scr_sensitive_fields','settlements','payroll_runs') as $t) {
    $r = $db->query('SELECT COUNT(*) FROM `' . $t . '`');
    $exact[$t] = $r ? (int) $r->fetch_row()[0] : null;
}
file_put_contents($OUT . '/exact_counts.json', json_encode($exact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo str_pad('exact_counts', 24) . count($exact) . "\n";

/* أنواعُ الأحداث — العمودان الحقيقيان category/event_key */
dq($db, 'event_types', "SELECT category, event_key, COUNT(*) AS cnt,
        SUM(delivered_ok) AS delivered_ok_sum, SUM(delivered_failed) AS delivered_failed_sum,
        SUM(in_dlq) AS dlq_sum, SUM(is_training) AS training_cnt,
        MIN(occurred_at) AS first_at, MAX(occurred_at) AS last_at
    FROM ems_business_events GROUP BY category, event_key ORDER BY cnt DESC", $OUT);

/* السجلاتُ التي فشلت في dump.php بأسماءِ أعمدةٍ خاطئة — تُعاد هنا صحيحة */
dq($db, 'nav_items', "SELECT n.*, r.name AS role_name, lg.name AS group_name, lg.group_code,
        lg.display_order AS group_order, lg.stage_no
    FROM nav_items n
    LEFT JOIN roles r ON r.id = n.role_id
    LEFT JOIN link_groups lg ON lg.id = n.group_id
    WHERE n.active = 1
    ORDER BY n.role_id, lg.display_order, n.sort_order", $OUT);
dq($db, 'link_groups', 'SELECT * FROM link_groups WHERE is_active=1 ORDER BY owner_role_id, display_order', $OUT);
dq($db, 'gov_profile_items', 'SELECT * FROM gov_profile_items ORDER BY profile_id, item_kind, item_ref', $OUT);
dq($db, 'gov_role_profiles', 'SELECT * FROM gov_role_profiles ORDER BY 1', $OUT);
dq($db, 'gov_ownership_rulings', 'SELECT * FROM gov_ownership_rulings ORDER BY route', $OUT);
dq($db, 'nav09_action_map', 'SELECT * FROM nav09_action_map ORDER BY canonical_code', $OUT);
dq($db, 'actions_registry', 'SELECT * FROM actions ORDER BY action_code', $OUT);
echo "تم\n";
