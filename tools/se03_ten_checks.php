<?php
/**
 * se03_ten_checks.php — الفحوصُ العشرةُ (المواصفة 70 · TSP-0129..0138)
 * ═══════════════════════════════════════════════════════════════════════════
 * المتوقَّعُ صفرٌ في كلِّ فحص.
 *
 * ◆ **الفحوصُ مُواءَمةٌ على المخططِ الحيّ ولم تُبنَ له جداولُ لتناسبَها.**
 *   المواصفةُ تفترض ستةَ جداولٍ جديدة (`cnt_*` · `sup_*`)، والقياسُ (TS-01)
 *   أظهر أن نظائرَها **قائمةٌ ومملوءة**:
 *     cnt_annual_containers · cnt_type_containers · cnt_machine_slots ·
 *     sup_slot_allocations   ⇐ `op_containers` — هرمٌ بأربعةِ مستوياتٍ و707 صفوف
 *                              (رئيسية 123 · مورد 157 · معدة 404 · مشغّل 23)
 *                              بأعمدةِ `seat_*` للخانةِ و`supplier_id` للإسناد
 *                              و`cap/allocated/consumed/remaining` للسعة.
 *     sup_handover_events    ⇐ `container_swaps` (مبادلةٌ بطرفَيها في صفٍّ واحد)
 *     sup_settlements        ⇐ `settlements` + `coverage_settlement_lines`
 *     quota_ledger           ⇐ `container_consumption`
 *   فبناءُ الستةِ كان سيُنشئ نموذجًا ثانيًا لهرمٍ حيٍّ — وهو بعينُه ما تمنعه
 *   المواصفةُ في TSP-0002. ولذلك تُرجمت الفحوصُ ولم تُترجَم البنية.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$db = @mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if (!$db) { fwrite(STDERR, "فشل الاتصال\n"); exit(2); }
$db->set_charset('utf8mb4');

$CHECKS = array(

    array('CK-01', 'مجموعُ الأبناءِ لا يتجاوز سعةَ الأب',
        "SELECT COUNT(*) FROM (
            SELECT p.id FROM op_containers p
              JOIN op_containers c ON c.parent_id = p.id AND c.is_deleted = 0
             WHERE p.is_deleted = 0
             GROUP BY p.id, p.cap_qty
            HAVING SUM(c.cap_qty) > p.cap_qty + 0.005) d",
        'op_containers — الهرمُ الحيُّ بدل cnt_annual/cnt_type'),

    array('CK-02', 'صفرُ خانةٍ بلا كودِ معدة',
        "SELECT COUNT(*) FROM op_containers
          WHERE is_deleted = 0 AND seat_no IS NOT NULL
            AND (equipment_id IS NULL OR equipment_id = 0)",
        'op_containers.seat_* بدل cnt_machine_slots'),

    array('CK-03', 'مجموعُ حصصِ الموردينَ ≤ سعةِ الحاوية',
        "SELECT COUNT(*) FROM (
            SELECT p.id FROM op_containers p
              JOIN op_containers c ON c.parent_id = p.id AND c.is_deleted = 0
                                  AND c.supplier_id IS NOT NULL
             WHERE p.is_deleted = 0
             GROUP BY p.id, p.cap_qty
            HAVING SUM(c.allocated_qty) > p.cap_qty + 0.005) d",
        'op_containers.supplier_id بدل sup_slot_allocations'),

    array('CK-04', 'صفرُ قيدِ ورديةٍ بلا معدةٍ ومشغّل',
        "SELECT COUNT(*) FROM unit_entries
          WHERE seed_tag IS NULL
            AND state NOT IN ('rejected','cancelled','superseded','reversed')
            AND (equipment_id IS NULL OR equipment_id = 0
                 OR operator_employee_id IS NULL OR operator_employee_id = 0)",
        'unit_entries بدل shift_entries'),

    array('CK-05', 'كلُّ مبادلةٍ لها طرفانِ وتاريخُ سريان',
        "SELECT COUNT(*) FROM container_swaps
          WHERE container_id IS NULL OR to_container_id IS NULL OR effective_from IS NULL",
        'container_swaps بدل sup_handover_events'),

    array('CK-06', 'صفرُ تسويةٍ بفارقِ فاتورةٍ بلا مستند',
        "SELECT COUNT(*) FROM settlements
          WHERE is_deleted = 0 AND COALESCE(invoice_diff,0) <> 0
            AND (invoice_diff_doc_ref IS NULL OR invoice_diff_doc_ref = '')",
        'settlements بدل sup_settlements'),

    array('CK-07', 'صفرُ فعلٍ غيرِ مسجَّلٍ في القاموس',
        "SELECT COUNT(*) FROM (
            SELECT a.action_code FROM actions a
             WHERE a.active = 1 AND a.action_code LIKE 'shift.%'
               AND NOT EXISTS (SELECT 1 FROM nav09_action_map m WHERE m.canonical_code = a.action_code)
            UNION ALL
            SELECT m.canonical_code FROM nav09_action_map m
             WHERE m.canonical_code LIKE 'shift.entry.%' AND (m.live_code IS NULL OR m.live_code = '')
          ) d",
        'nav09_action_map.canonical_code — و activity_logs بلا action_code'),

    array('CK-08', 'صفرُ شاشةٍ بلا وحدةِ صلاحيات',
        "SELECT COUNT(*) FROM nav_items
          WHERE active = 1 AND module_id IS NULL
            AND permission_code IS NOT NULL AND permission_code <> ''",
        'nav_items.route بدل path'),

    array('CK-09', 'صفرُ جدولٍ في النطاقِ بلا عمودِ عزل',
        "SELECT COUNT(*) FROM information_schema.TABLES t
          WHERE t.TABLE_SCHEMA = DATABASE()
            AND t.TABLE_NAME IN ('unit_entries','unit_time_log','op_containers',
                                 'container_swaps','container_consumption','settlements')
            AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS c
                            WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
                              AND c.COLUMN_NAME = 'company_id')",
        'الجداولُ الحيّةُ بدل الستةِ المقترحة'),

    array('CK-10', 'صفرُ صفٍّ مبذورٍ بلا وسم',
        "SELECT COUNT(*) FROM unit_entries
          WHERE created_at >= '2026-08-16' AND seed_tag IS NULL
            AND (note LIKE '%اختبار%' OR source_ref LIKE '%TEST%' OR source_ref LIKE '%test%'
                 OR note LIKE '%probe%')",
        'unit_entries.seed_tag'),
);

echo "══════════════════════════════════════════════════════════════════════\n";
echo "  الفحوصُ العشرة — مُواءَمةٌ على المخططِ الحيّ · المتوقَّعُ صفرٌ في كلٍّ\n";
echo "══════════════════════════════════════════════════════════════════════\n";

$pass = 0; $fail = 0; $err = 0;
$failed = array();
foreach ($CHECKS as list($id, $title, $sql, $note)) {
    $r = $db->query($sql);
    if (!$r) { $err++; printf("\n%-6s %-44s ✘ خطأُ استعلام\n        %s\n", $id, $title, $db->error); continue; }
    $n = (int) $r->fetch_row()[0];
    printf("\n%-6s %-44s %s\n", $id, $title, $n === 0 ? '✔ صفر' : "✘ $n مخالفًا");
    printf("        المصدر: %s\n", $note);
    if ($n === 0) { $pass++; } else { $fail++; $failed[$id] = $n; }
}

echo "\n══════════════════════════════════════════════════════════════════════\n";
printf("  اجتاز %d · أخفق %d · أخطأ %d (من %d)\n", $pass, $fail, $err, count($CHECKS));
if ($failed) {
    echo "  المخالف: ";
    foreach ($failed as $k => $v) { echo "$k=$v · "; }
    echo "\n";
}
echo "══════════════════════════════════════════════════════════════════════\n";
exit(($fail === 0 && $err === 0) ? 0 : 1);
