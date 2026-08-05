<?php
/**
 * tools/ton_meter_hours_report.php — تقرير الموروث: طن/متر بلا أسطر زمن (E-02 UN-02)
 * يكتب docs/E02_TON_METER_MISSING_HOURS_ar.csv للمواقع كي تستكمل ساعاتها —
 * لا يُردم رقمٌ لم يقله أحد (قرار المالك 2026-08-05). ويطبع ملخصًا بالمشروع.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/unit_chain_helpers.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$sql = "SELECT ue.company_id, ue.entry_no, ue.entry_date, ue.unit_type, ue.qty,
               ue.project_id, p.name AS project_name,
               ue.equipment_id, e.name AS equipment_name,
               ue.source_ref, ue.state
          FROM unit_entries ue
          LEFT JOIN project p ON p.id = ue.project_id
          LEFT JOIN equipments e ON e.id = ue.equipment_id
         WHERE ue.unit_type IN ('ton','meter')
           AND NOT EXISTS (SELECT 1 FROM unit_time_log l WHERE l.entry_id = ue.id)
         ORDER BY ue.project_id, ue.entry_date";
$r = mysqli_query($conn, $sql);
if (!$r) { fwrite(STDERR, "SQL: " . mysqli_error($conn) . "\n"); exit(1); }

$out = dirname(__DIR__) . '/docs/E02_TON_METER_MISSING_HOURS_ar.csv';
$f = fopen($out, 'w');
fwrite($f, "\xEF\xBB\xBF"); // BOM لعرضٍ عربيٍّ سليم في Excel
fputcsv($f, array('الشركة', 'رقم الصف', 'التاريخ', 'النوع', 'الكمية', 'المشروع', 'المعدة', 'المرجع', 'الحالة'));
$byProject = array();
$n = 0;
while ($x = mysqli_fetch_assoc($r)) {
    fputcsv($f, array(
        $x['company_id'], $x['entry_no'], $x['entry_date'],
        $x['unit_type'] === 'ton' ? 'طن' : 'متر', $x['qty'],
        $x['project_name'] ?: ('#' . $x['project_id']),
        $x['equipment_name'] ?: ('#' . $x['equipment_id']),
        $x['source_ref'], $x['state'],
    ));
    $k = $x['project_name'] ?: ('#' . $x['project_id']);
    $byProject[$k] = ($byProject[$k] ?? 0) + 1;
    $n++;
}
fclose($f);

echo "كُتب: docs/E02_TON_METER_MISSING_HOURS_ar.csv — {$n} صفًّا\n\nالتوزيع بالمشروع:\n";
arsort($byProject);
foreach ($byProject as $p => $c) { echo sprintf("  %-40s %d\n", mb_substr($p, 0, 38), $c); }
