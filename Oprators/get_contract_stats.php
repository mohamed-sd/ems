<?php
session_start();

while (ob_get_level()) ob_end_clean();

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$is_role10 = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] == "10";
$user_contract_id = $is_role10 ? intval($_SESSION['user']['contract_id']) : 0;

$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

if ($is_role10 && $user_contract_id > 0 && $contract_id !== $user_contract_id) {
    echo json_encode(['success' => false, 'message' => 'لا توجد صلاحية لهذا العقد']);
    exit;
}

if ($contract_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']);
    exit;
}

$contract_query = "SELECT c.*, p.name as project_name,
                   (SELECT COALESCE(SUM(ce.equip_count), 0) FROM contractequipments ce WHERE ce.contract_id = c.id) as equipment_count
                   FROM contracts c
                   LEFT JOIN project p ON c.project_id = p.id
                   WHERE c.id = $contract_id";
$contract_result = mysqli_query($conn, $contract_query);

if (!$contract_result) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاستعلام: ' . mysqli_error($conn)]);
    exit;
}

if (mysqli_num_rows($contract_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'العقد غير موجود']);
    exit;
}

$contract = mysqli_fetch_assoc($contract_result);
$project_id = intval($contract['project_id']);

// أساس احتساب الهدف: أيام العقد الفعلية. الهدف اليومي = نصيب الآلية ÷ الأيام، والشهري = اليومي × 30.
// استخدام الأيام الفعلية أدقّ من تقريب الأشهر ويتجنّب تشويه العقود الأقصر من شهر.
$dur_days = intval($contract['contract_duration_days']);

if ($is_role10) {
    $user_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
    if ($user_project_id > 0 && $project_id !== $user_project_id) {
        echo json_encode(['success' => false, 'message' => 'لا توجد صلاحية لهذا المشروع']);
        exit;
    }
}

$active_equipment_query = "SELECT COUNT(*) as active_count
                          FROM operations o
                          WHERE o.status = 1
                          AND o.contract_id = $contract_id";
$active_equipment_result = mysqli_query($conn, $active_equipment_query);
$active_equipment_count = 0;
if ($active_equipment_result && mysqli_num_rows($active_equipment_result) > 0) {
    $active_equipment_row = mysqli_fetch_assoc($active_equipment_result);
    $active_equipment_count = intval($active_equipment_row['active_count']);
}

$suppliers_query = "SELECT
    sc.id,
    sc.supplier_id,
    s.name as supplier_name,
    sc.forecasted_contracted_hours,
    (SELECT COALESCE(SUM(sce.equip_count), 0) FROM suppliercontractequipments sce WHERE sce.contract_id = sc.id) as equipment_count,
    (SELECT COALESCE(SUM(sce.equip_count_basic), 0) FROM suppliercontractequipments sce WHERE sce.contract_id = sc.id) as equipment_count_basic,
    (SELECT COALESCE(SUM(sce.equip_count_backup), 0) FROM suppliercontractequipments sce WHERE sce.contract_id = sc.id) as equipment_count_backup
FROM supplierscontracts sc
LEFT JOIN suppliers s ON sc.supplier_id = s.id
WHERE sc.project_id = $project_id AND sc.status = 1
ORDER BY s.name";

$suppliers_result = mysqli_query($conn, $suppliers_query);
$suppliers = [];
$total_supplier_hours = 0;
$total_supplier_equipment = 0;
$total_contract_primary = 0; // إجمالي الآليات الأساسية في العقد (المقسوم عليه للهدف العام)

if ($suppliers_result) {
    while ($row = mysqli_fetch_assoc($suppliers_result)) {
        $equip_details_query = "SELECT
                                        et.type as type_name,
                                        sce.equip_type,
                                        SUM(sce.equip_count) as total_count,
                                        SUM(sce.equip_count_basic) as total_basic,
                                        SUM(sce.equip_count_backup) as total_backup,
                                        SUM(sce.equip_total_contract) as total_hours
                                 FROM suppliercontractequipments sce
                                 LEFT JOIN equipments_types et ON sce.equip_type = et.id
                                 WHERE sce.contract_id = " . $row['id'] . "
                                 GROUP BY sce.equip_type, et.type";
        $equip_details_result = mysqli_query($conn, $equip_details_query);

        $equipment_breakdown = [];
        $total_added_to_operations = 0;
        $contracted_type_ids = [];

        if ($equip_details_result) { while ($equip = mysqli_fetch_assoc($equip_details_result)) {
            $equip_type_id = intval($equip['equip_type']);
            $contracted_count = intval($equip['total_count']);
            $contracted_type_ids[$equip_type_id] = true;

            // إصلاح العدّ المزدوج: نطابق كل عملية بـ«نوع فعّال واحد» (النوع المخزّن إن وُجد،
            // وإلا نوع المعدة الفعلي) بدل (OR) التي كانت تحسب العملية الواحدة في نوعين.
            $added_query = "SELECT COUNT(*) as added_count
                           FROM operations o
                           LEFT JOIN equipments e ON o.equipment = e.id
                           WHERE o.status = 1
                           AND o.project_id = $project_id
                           AND o.supplier_id = " . intval($row['supplier_id']) . "
                           AND (CASE WHEN CAST(o.equipment_type AS UNSIGNED) > 0
                                     THEN CAST(o.equipment_type AS UNSIGNED) ELSE e.type END) = $equip_type_id";
            $added_result = mysqli_query($conn, $added_query);
            $added_row = $added_result ? mysqli_fetch_assoc($added_result) : null;
            $added_count = intval($added_row['added_count'] ?? 0);
            $total_added_to_operations += $added_count;

            // لا نُصفّر المتبقي السالب — نُبقيه ليكشف التجاوز (overage موجب عند المضاف > المتعاقد).
            $remaining = $contracted_count - $added_count;
            $overage = $added_count > $contracted_count ? ($added_count - $contracted_count) : 0;

            // الهدف التقريبي للآلية: ساعات النوع ÷ أساسيات النوع (الاحتياطية هدفها صفر) ثم اشتقاق الشهري واليومي
            $primary_n = intval($equip['total_basic'] ?? 0);
            $type_hours = floatval($equip['total_hours']);
            $per_primary_total = $primary_n > 0 ? $type_hours / $primary_n : 0;
            $per_primary_daily = ($per_primary_total > 0 && $dur_days > 0) ? $per_primary_total / $dur_days : 0;
            $per_primary_monthly = $per_primary_daily * 30;

            $equipment_breakdown[] = [
                'type' => $equip['type_name'] ?: 'غير محدد',
                'type_id' => $equip_type_id,
                'count' => $contracted_count,
                'count_basic' => intval($equip['total_basic'] ?? 0),
                'count_backup' => intval($equip['total_backup'] ?? 0),
                'hours' => floatval($equip['total_hours']),
                'hours_per_primary' => round($per_primary_total, 1),
                'monthly_per_primary' => round($per_primary_monthly, 1),
                'daily_per_primary' => round($per_primary_daily, 1),
                'added_count' => $added_count,
                'remaining' => $remaining,
                'overage' => $overage,
                'out_of_contract' => false
            ];
        } }

        // أنواع مُضافة «خارج العقد»: تُعرض لتظهر كل المعدات المضافة للمورّد (متعاقد=0، تجاوز=المضاف).
        $extra_query = "SELECT
                            (CASE WHEN CAST(o.equipment_type AS UNSIGNED) > 0
                                  THEN CAST(o.equipment_type AS UNSIGNED) ELSE e.type END) AS eff_type,
                            COUNT(*) AS added_count
                        FROM operations o
                        LEFT JOIN equipments e ON o.equipment = e.id
                        WHERE o.status = 1
                          AND o.project_id = $project_id
                          AND o.supplier_id = " . intval($row['supplier_id']) . "
                        GROUP BY eff_type";
        $extra_result = mysqli_query($conn, $extra_query);
        if ($extra_result) {
            while ($ex = mysqli_fetch_assoc($extra_result)) {
                $ex_type = intval($ex['eff_type']);
                if ($ex_type <= 0 || isset($contracted_type_ids[$ex_type])) {
                    continue; // ضمن العقد (مُحتسب سلفاً) أو نوع غير صالح
                }
                $ex_added = intval($ex['added_count']);
                $tn = '';
                $tn_res = mysqli_query($conn, "SELECT type FROM equipments_types WHERE id = $ex_type LIMIT 1");
                if ($tn_res && mysqli_num_rows($tn_res) > 0) {
                    $tn = mysqli_fetch_assoc($tn_res)['type'];
                }
                $total_added_to_operations += $ex_added; // يدخل في إجمالي المضاف للمورّد
                $equipment_breakdown[] = [
                    'type' => $tn !== '' ? $tn : ('نوع #' . $ex_type),
                    'type_id' => $ex_type,
                    'count' => 0,
                    'count_basic' => 0,
                    'count_backup' => 0,
                    'hours' => 0,
                    'added_count' => $ex_added,
                    'remaining' => -$ex_added,
                    'overage' => $ex_added,
                    'out_of_contract' => true
                ];
            }
        }

        $equipment_count = intval($row['equipment_count']);
        // لا نُصفّر المتبقي — نُبقيه (قد يكون سالباً) ليكشف التجاوز في الواجهة.
        $remaining_to_add = $equipment_count - $total_added_to_operations;
        $supplier_overage = $total_added_to_operations > $equipment_count ? ($total_added_to_operations - $equipment_count) : 0;

        $suppliers[] = [
            'id' => $row['id'],
            'supplier_id' => $row['supplier_id'],
            'supplier_name' => $row['supplier_name'] ?: 'غير محدد',
            'hours' => floatval($row['forecasted_contracted_hours']),
            'equipment_count' => $equipment_count,
            'equipment_count_basic' => intval($row['equipment_count_basic']),
            'equipment_count_backup' => intval($row['equipment_count_backup']),
            'added_to_equipments' => $total_added_to_operations,
            'remaining_to_add' => $remaining_to_add,
            'overage' => $supplier_overage,
            'equipment_breakdown' => $equipment_breakdown
        ];
        $total_supplier_hours += floatval($row['forecasted_contracted_hours']);
        $total_supplier_equipment += $equipment_count;
        $total_contract_primary += intval($row['equipment_count_basic']);
    }
}

// الهدف العام التقريبي للآلية على مستوى العقد = إجمالي ساعات العقد ÷ إجمالي الأساسيات
$overall_total_hours = floatval($contract['forecasted_contracted_hours']);
$overall_per_primary_total = $total_contract_primary > 0 ? $overall_total_hours / $total_contract_primary : 0;
$overall_per_primary_daily = ($overall_per_primary_total > 0 && $dur_days > 0) ? $overall_per_primary_total / $dur_days : 0;
$overall_per_primary_monthly = $overall_per_primary_daily * 30;

$response = [
    'success' => true,
    'contract' => [
        'contract_date' => $contract['contract_signing_date'],
        'duration' => $contract['contract_duration_months'] . ' شهر',
        'total_hours' => floatval($contract['forecasted_contracted_hours']),
        'equipment_count' => $active_equipment_count,
        'project_name' => $contract['project_name'],
        'total_primary' => $total_contract_primary,
        'target_per_primary_total' => round($overall_per_primary_total, 1),
        'target_per_primary_monthly' => round($overall_per_primary_monthly, 1),
        'target_per_primary_daily' => round($overall_per_primary_daily, 1)
    ],
    'suppliers' => $suppliers,
    'summary' => [
        'suppliers_count' => count($suppliers),
        'total_supplier_hours' => $total_supplier_hours,
        'total_supplier_equipment' => $total_supplier_equipment
    ]
];

echo json_encode($response);
?>
