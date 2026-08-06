<?php
/**
 * tools/seed_mnt_order_bridge_demo.php — بذرةُ عرض لجسر الصيانة↔المشتريات (③)
 * ═══════════════════════════════════════════════════════════════════════════
 * أمرُ صيانةٍ مفتوحٌ (تنفيذ · مصدره بلاغ = معدةٌ واقفة) بقطعتين: واحدةٌ تطابق
 * الكتالوج بالاسم («فلتر هواء» → SP-002) وأخرى حرة — لإثبات المسارين.
 * إدراجٌ فقط، idempotent بالكود MNT-BRDG-01.
 *
 * php tools/seed_mnt_order_bridge_demo.php --apply
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/env.php';

$APPLY = in_array('--apply', $argv, true);
$conn = new mysqli(ems_env('DB_HOST', 'localhost'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), (int) ems_env('DB_PORT', 3306));
$conn->set_charset('utf8mb4');
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$r = $conn->query("SELECT id FROM mnt_order WHERE company_id = 4 AND code = 'MNT-BRDG-01'");
if ($r && $r->num_rows) { $o('البذرة قائمة (#' . $r->fetch_assoc()['id'] . ') ✓'); exit(0); }
$cols = array();
$r = $conn->query('SHOW COLUMNS FROM mnt_order');
while ($c = $r->fetch_assoc()) { $cols[$c['Field']] = true; }
$o('سيُدرج أمر صيانة MNT-BRDG-01 (تنفيذ · بلاغ · معدة 725) + قطعتان');
if (!$APPLY) { $o('— dry-run'); exit(0); }

$conn->begin_transaction();
try {
    $fields = array('company_id' => 4, 'code' => "'MNT-BRDG-01'", 'state' => "'تنفيذ'",
                    'equipment_id' => 725, 'created_at' => 'NOW()');
    if (isset($cols['source']))     { $fields['source'] = "'بلاغ'"; }
    if (isset($cols['created_by'])) { $fields['created_by'] = 71; }
    $conn->query('INSERT INTO mnt_order (' . implode(',', array_keys($fields)) . ') VALUES (' . implode(',', array_values($fields)) . ')');
    $mid = intval($conn->insert_id);
    $conn->query("INSERT INTO mnt_order_part (company_id, order_id, part_name, quantity, unit_cost, subtotal, is_major_component, created_at)
                  VALUES (4, $mid, 'فلتر هواء', 2, 0, 0, 0, NOW())");
    $conn->query("INSERT INTO mnt_order_part (company_id, order_id, part_name, quantity, unit_cost, subtotal, is_major_component, created_at)
                  VALUES (4, $mid, 'خرطوم هيدروليك 3/4 بوصة', 1, 0, 0, 1, NOW())");
    $conn->commit();
    $o("✔ أُدرج أمر الصيانة #$mid بقطعتيه");
} catch (\Throwable $t) {
    $conn->rollback();
    $o('✘ ROLLED BACK: ' . $t->getMessage());
    exit(1);
}
