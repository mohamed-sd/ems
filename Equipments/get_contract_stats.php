<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();

while (ob_get_level()) ob_end_clean();

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف الشركة غير متوفر']);
    exit;
}

// بوابة العزل — تستبدل سلالمَ النطاق اليدوية الثلاث (وفيها احتياطي created_by الميت:
// يشير لعمود project.company_client_id غير الموجود). contracts/supplierscontracts
// لها company_id مقيسةً، فالعزل مباشرٌ عبر البوابة.
$stats_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('contract stats super view') : ems_tenant_db();

$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

if ($contract_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']);
    exit;
}

// جلب بيانات العقد مع عدد المعدات من contractequipments (معزولًا عبر البوابة؛
// جداول الاستعلامات الفرعية تُعلَن إثراءً)
try {
    $contract_rows = $stats_gate->scopedQuery(array(
        'scope'  => array('c' => 'contracts'),
        'enrich' => array('p' => 'project', 'ce' => 'contractequipments'),
    ), "SELECT c.*, p.name as project_name,
                   (SELECT COALESCE(SUM(ce.equip_count), 0) FROM contractequipments ce WHERE ce.contract_id = c.id) as equipment_count,
                   (SELECT COALESCE(SUM(ce.equip_count_basic), 0) FROM contractequipments ce WHERE ce.contract_id = c.id) as equipment_count_basic,
                   (SELECT COALESCE(SUM(ce.equip_count_backup), 0) FROM contractequipments ce WHERE ce.contract_id = c.id) as equipment_count_backup
                   FROM contracts c
                   LEFT JOIN project p ON c.project_id = p.id
                                     WHERE {TENANT_SCOPE}
                                         AND c.id = ?",
        array($contract_id));
} catch (\Throwable $t) {
    echo json_encode(['success' => false, 'message' => 'خطأ في الاستعلام']);
    exit;
}

if (empty($contract_rows)) {
    echo json_encode(['success' => false, 'message' => 'العقد غير موجود']);
    exit;
}

$contract = $contract_rows[0];
$project_id = intval($contract['project_id']);

// جلب عقود الموردين المرتبطة بنفس المشروع مع عدد المعدات (معزولًا عبر البوابة)
try {
    $supplier_rows = $stats_gate->scopedQuery(array(
        'scope'  => array('sc' => 'supplierscontracts'),
        'enrich' => array('s' => 'suppliers', 'sce' => 'suppliercontractequipments'),
    ), "SELECT
    sc.id,
    sc.supplier_id,
    s.name as supplier_name,
    sc.forecasted_contracted_hours,
    (SELECT COALESCE(SUM(sce.equip_count), 0) FROM suppliercontractequipments sce WHERE sce.contract_id = sc.id) as equipment_count,
    (SELECT COALESCE(SUM(sce.equip_count_basic), 0) FROM suppliercontractequipments sce WHERE sce.contract_id = sc.id) as equipment_count_basic,
    (SELECT COALESCE(SUM(sce.equip_count_backup), 0) FROM suppliercontractequipments sce WHERE sce.contract_id = sc.id) as equipment_count_backup
FROM supplierscontracts sc
LEFT JOIN suppliers s ON sc.supplier_id = s.id
WHERE {TENANT_SCOPE}
    AND sc.project_id = ? AND sc.status = 1
ORDER BY s.name",
        array($project_id));
} catch (\Throwable $t) {
    $supplier_rows = array();
}
$suppliers = [];
$total_supplier_hours = 0;
$total_supplier_equipment = 0;

if (!empty($supplier_rows)) {
    foreach ($supplier_rows as $row) {
        // تفاصيل معدات عقد المورد (تجميعٌ) — **العزل عبر الأب** (sc) لا السطور:
        // sce.company_id كانت NULL تاريخيًّا (تُعبَّأ بترحيل M4)، والأب مقيسٌ بالشركة،
        // فالنطاق على العقد الأمّ يعزل صحًّا مهما كانت حال السطور. et كتالوج عام.
        try {
            $equip_details_rows = $stats_gate->scopedQuery(array(
                'scope'  => array('sc' => 'supplierscontracts'),
                'enrich' => array('sce' => 'suppliercontractequipments'),
            ), "SELECT
                                        et.type as type_name,
                                        sce.equip_type,
                                        SUM(sce.equip_count) as total_count,
                                        SUM(sce.equip_count_basic) as total_count_basic,
                                        SUM(sce.equip_count_backup) as total_count_backup,
                                        SUM(sce.equip_total_contract) as total_hours
                                 FROM supplierscontracts sc
                                 LEFT JOIN suppliercontractequipments sce ON sce.contract_id = sc.id
                                 LEFT JOIN equipments_types et ON sce.equip_type = et.id
                                 WHERE {TENANT_SCOPE} AND sc.id = ? AND sce.id IS NOT NULL
                                 GROUP BY sce.equip_type, et.type",
                array($row['id']));
        } catch (\Throwable $t) {
            $equip_details_rows = array();
        }

        $equipment_breakdown = [];

        foreach ($equip_details_rows as $equip) {
            // عدد الآليات المشغّلة فعليًا — JOIN الداخلي على equipments → LEFT + شرط
            // e.id IS NOT NULL (عقد الإثراء)، والعزل عبر الرمز يغني عن o.company_id اليدوي
            try {
                $op_rows = $stats_gate->scopedQuery(array(
                    'scope'  => array('o' => 'operations'),
                    'enrich' => array('e' => 'equipments'),
                ), "SELECT COUNT(DISTINCT o.equipment) as operating_count
                                      FROM operations o
                                      LEFT JOIN equipments e ON o.equipment = e.id
                                      WHERE {TENANT_SCOPE}
                                        AND e.id IS NOT NULL
                                        AND e.suppliers = ?
                                        AND e.type = ?
                                        AND o.project_id = ?
                                        AND o.status = 1",
                    array($row['supplier_id'], intval($equip['equip_type']), $project_id));
            } catch (\Throwable $t) {
                $op_rows = array();
            }
            $operating_count = intval($op_rows[0]['operating_count'] ?? 0);

            $contracted_count = intval($equip['total_count']);
            $remaining_count = max(0, $contracted_count - $operating_count);

            $equipment_breakdown[] = [
                'type' => $equip['type_name'] ?: 'غير محدد',
                'type_id' => $equip['equip_type'],
                'count' => $contracted_count,
                'count_basic' => intval($equip['total_count_basic']) ?: 0,
                'count_backup' => intval($equip['total_count_backup']) ?: 0,
                'operating_count' => $operating_count,
                'remaining_count' => $remaining_count,
                'hours' => floatval($equip['total_hours'])
            ];
        }

        $suppliers[] = [
            'id' => $row['id'],
            'supplier_id' => $row['supplier_id'],
            'supplier_name' => $row['supplier_name'] ?: 'غير محدد',
            'hours' => floatval($row['forecasted_contracted_hours']),
            'equipment_count' => intval($row['equipment_count']),
            'equipment_count_basic' => intval($row['equipment_count_basic']) ?: 0,
            'equipment_count_backup' => intval($row['equipment_count_backup']) ?: 0,
            'equipment_breakdown' => $equipment_breakdown
        ];
        $total_supplier_hours += floatval($row['forecasted_contracted_hours']);
        $total_supplier_equipment += intval($row['equipment_count']);
    }
}

$response = [
    'success' => true,
    'contract' => [
        'contract_date' => $contract['contract_signing_date'],
        'duration' => $contract['contract_duration_months'] . ' شهر',
        'total_hours' => floatval($contract['forecasted_contracted_hours']),
        'equipment_count' => intval($contract['equipment_count']),
        'equipment_count_basic' => intval($contract['equipment_count_basic']) ?: 0,
        'equipment_count_backup' => intval($contract['equipment_count_backup']) ?: 0,
        'project_name' => $contract['project_name']
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
