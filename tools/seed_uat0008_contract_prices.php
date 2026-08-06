<?php
/**
 * tools/seed_uat0008_contract_prices.php — إصلاح أسطر أسعار عقود UAT (E-01)
 * ───────────────────────────────────────────────────────────────────────────
 * دفعة UAT-B07 كتبت في contractequipments سطرًا فاسدًا (نصٌّ في خانة النوع
 * وسعر 0) فتعذّر تحويل 3,828 يومًا بسبب «لا سعر وحدةٍ لنوع المعدة».
 * هذا باذرُ بيئة تجربة معلَن (كأخوته seed_uat*): يزرع سطرَ سعرٍ لكل
 * (عقد × نوع معدة) تستعمله وقائع السلسلة العالقة ولا سطرَ صالحًا له.
 * السعر الموحَّد المعلَن: 20 دولار/ساعة (سعرُ النوع 1 في العقد المرجعي 5).
 * لا يمسّ سطرًا قائمًا صالحًا، ولا يعمل إلا حيث السطر غائبٌ أو صفري.
 *
 * الاستعمال: php tools/seed_uat0008_contract_prices.php [--apply]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$PRICE = 20.00; $UNIT = 'ساعة'; $CUR = 'دولار';

fwrite(STDOUT, "════ باذر أسعار UAT — " . ($APPLY ? 'تنفيذ' : 'معاينة') . " ════\n");

$sql = "SELECT DISTINCT op.contract_id, op.equipment_type
          FROM unit_entries ue
          JOIN timesheet t ON ue.sync_uuid LIKE 'ts:%'
                          AND t.id = CAST(SUBSTRING(ue.sync_uuid, 4) AS UNSIGNED)
          JOIN operations op ON op.id = t.operator
         WHERE ue.state = 'sales_approved'
           AND op.contract_id > 0 AND op.equipment_type > 0
           AND NOT EXISTS (SELECT 1 FROM contractequipments x
                            WHERE x.contract_id = op.contract_id
                              AND x.equip_type = op.equipment_type
                              AND x.equip_price > 0)
         ORDER BY op.contract_id, op.equipment_type";
$r = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$pairs = array();
while ($x = mysqli_fetch_assoc($r)) { $pairs[] = $x; }
fwrite(STDOUT, "أزواج (عقد × نوع) بلا سطر سعرٍ صالح: " . count($pairs) . "\n");

$ins = $conn->prepare("INSERT INTO contractequipments (contract_id, equip_type, equip_price, equip_unit, equip_price_currency)
                       VALUES (?,?,?,?,?)");
$n = 0;
foreach ($pairs as $p) {
    fwrite(STDOUT, "  عقد {$p['contract_id']} × نوع {$p['equipment_type']} ← {$PRICE} {$CUR}/{$UNIT}\n");
    if ($APPLY) {
        $cid = intval($p['contract_id']); $et = intval($p['equipment_type']);
        $ins->bind_param('iidss', $cid, $et, $PRICE, $UNIT, $CUR);
        if ($ins->execute()) { $n++; } else { fwrite(STDOUT, "  ✘ " . $ins->error . "\n"); }
    }
}
$ins->close();
fwrite(STDOUT, $APPLY ? "✔ زُرع {$n} سطرًا\n" : "— معاينة فقط (--apply للتنفيذ)\n");
