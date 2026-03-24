<?php
include '../config.php';
require_login();
require_once '../includes/approval_workflow.php';
require_once '../includes/permissions_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

enforce_module_permission_json($conn, 'contracts', 'edit', 'لا توجد صلاحية تعديل العقود');

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'لا يمكن تحديد الشركة الحالية']));
}

function contractTenantScopeSql($conn, $is_super_admin, $company_id, $alias)
{
    if ($is_super_admin) {
        return '1=1';
    }

    $safe_company_id = intval($company_id);
    $a = $alias !== '' ? $alias . '.' : '';

    if (db_table_has_column($conn, 'contracts', 'company_id')) {
        return $a . "company_id = " . $safe_company_id;
    }

    return "EXISTS (
        SELECT 1
        FROM mines m
        JOIN project p ON p.id = m.project_id
        JOIN users u ON u.project_id = p.id
        WHERE m.id = " . $a . "mine_id
          AND u.company_id = " . $safe_company_id . "
    )";
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
$user_id = approval_get_user_id();

if (!$contract_id) {
    die(json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']));
}

function getContractData($contract_id, $conn, $is_super_admin, $company_id) {
    $tenant_scope = contractTenantScopeSql($conn, $is_super_admin, $company_id, 'c');
    $query = "SELECT c.* FROM contracts c WHERE c.id = $contract_id AND $tenant_scope LIMIT 1";
    $result = mysqli_query($conn, $query);
    return $result ? mysqli_fetch_assoc($result) : null;
}

function contractNoteOperation($contract_id, $note, $user_id) {
    return [
        'db_action' => 'insert',
        'table' => 'contract_notes',
        'data' => [
            'contract_id' => intval($contract_id),
            'note' => $note,
            'user_id' => intval($user_id),
            'created_at' => approval_now()
        ]
    ];
}

function enqueueContractApproval($contract_id, $action, $payload_summary, $operations, $conn) {
    $requested_by = approval_get_user_id();
    $payload = [
        'summary' => $payload_summary,
        'operations' => $operations
    ];

    return approval_create_request('contract', intval($contract_id), $action, $payload, $requested_by, $conn);
}

$current_contract_scope = getContractData($contract_id, $conn, $is_super_admin, $company_id);
if (!$current_contract_scope) {
    die(json_encode(['success' => false, 'message' => 'العقد غير موجود أو خارج نطاق الشركة']));
}

// 1. تجديد العقد
if ($action === 'renewal') {
    $new_start_date = isset($_POST['new_start_date']) ? $_POST['new_start_date'] : '';
    $new_end_date = isset($_POST['new_end_date']) ? $_POST['new_end_date'] : '';
    $contract_duration_days = isset($_POST['contract_duration_days']) ? intval($_POST['contract_duration_days']) : 0;

    if (empty($new_start_date) || empty($new_end_date)) {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال تاريخي البدء والانتهاء']));
    }

    $start_validation = DateTime::createFromFormat('Y-m-d', $new_start_date);
    $end_validation = DateTime::createFromFormat('Y-m-d', $new_end_date);
    if (!$start_validation || !$end_validation) {
        die(json_encode(['success' => false, 'message' => 'صيغة التاريخ غير صحيحة']));
    }

    if (strtotime($new_start_date) >= strtotime($new_end_date)) {
        die(json_encode(['success' => false, 'message' => 'تاريخ البدء يجب أن يكون قبل تاريخ الانتهاء']));
    }

    $contract_before = getContractData($contract_id, $conn, $is_super_admin, $company_id);
    if (!$contract_before) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }

    $start = new DateTime($new_start_date);
    $end = new DateTime($new_end_date);
    $interval = $start->diff($end);
    $months = $interval->m + ($interval->y * 12);
    if ($contract_duration_days <= 0) {
        $contract_duration_days = $interval->days;
    }

    $new_data = [
        'actual_start' => $new_start_date,
        'actual_end' => $new_end_date,
        'contract_duration_months' => intval($months),
        'contract_duration_days' => intval($contract_duration_days),
        'status' => 1,
        'updated_at' => approval_now()
    ];

    $note_text = "تم تجديد العقد من $new_start_date إلى $new_end_date (مدة: $months شهور / $contract_duration_days يوم)";

    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => $contract_id],
            'data' => $new_data
        ],
        contractNoteOperation($contract_id, $note_text, $user_id)
    ];

    $result = enqueueContractApproval($contract_id, 'renewal', [
        'old_values' => [
            'actual_start' => $contract_before['actual_start'],
            'actual_end' => $contract_before['actual_end'],
            'status' => $contract_before['status']
        ],
        'new_values' => $new_data
    ], $operations, $conn);

    echo json_encode($result);
}

// 2. تسوية العقد
else if ($action === 'settlement') {
    $settlement_type = isset($_POST['settlement_type']) ? $_POST['settlement_type'] : '';
    $settlement_hours = isset($_POST['settlement_hours']) ? intval($_POST['settlement_hours']) : 0;
    $settlement_reason = isset($_POST['settlement_reason']) ? trim($_POST['settlement_reason']) : '';

    if (empty($settlement_type) || $settlement_hours <= 0) {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال نوع التسوية وعدد الساعات']));
    }

    if (!in_array($settlement_type, ['increase', 'decrease'])) {
        die(json_encode(['success' => false, 'message' => 'نوع التسوية غير صحيح']));
    }

    $contract = getContractData($contract_id, $conn, $is_super_admin, $company_id);
    if (!$contract) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }

    $current_hours = intval($contract['forecasted_contracted_hours']);
    $new_hours = ($settlement_type === 'increase') ? $current_hours + $settlement_hours : $current_hours - $settlement_hours;

    if ($new_hours < 0) {
        die(json_encode(['success' => false, 'message' => 'عدد الساعات المحسوبة أقل من صفر']));
    }

    $settlement_type_ar = ($settlement_type === 'increase') ? 'زيادة' : 'نقصان';
    $note = "تم تسوية العقد: $settlement_type_ar $settlement_hours ساعة";
    if ($settlement_reason !== '') {
        $note .= " - السبب: $settlement_reason";
    }

    $new_data = [
        'forecasted_contracted_hours' => intval($new_hours),
        'updated_at' => approval_now()
    ];

    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => $contract_id],
            'data' => $new_data
        ],
        contractNoteOperation($contract_id, $note, $user_id)
    ];

    $result = enqueueContractApproval($contract_id, 'settlement', [
        'old_values' => ['forecasted_contracted_hours' => $current_hours],
        'new_values' => ['forecasted_contracted_hours' => $new_hours],
        'settlement_type' => $settlement_type
    ], $operations, $conn);

    echo json_encode($result);
}

// 3. إيقاف العقد
else if ($action === 'pause') {
    $pause_reason = isset($_POST['pause_reason']) ? trim($_POST['pause_reason']) : '';
    $pause_date = isset($_POST['pause_date']) ? $_POST['pause_date'] : date('Y-m-d');

    if ($pause_reason === '') {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال سبب الإيقاف']));
    }

    if (!empty($pause_date)) {
        $date_validation = DateTime::createFromFormat('Y-m-d', $pause_date);
        if (!$date_validation) {
            die(json_encode(['success' => false, 'message' => 'صيغة التاريخ غير صحيحة']));
        }
    }

    $contract_before = getContractData($contract_id, $conn, $is_super_admin, $company_id);
    if (!$contract_before) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }

    $new_data = [
        'status' => 0,
        'pause_reason' => $pause_reason,
        'pause_date' => $pause_date,
        'updated_at' => approval_now()
    ];

    $note = "تم إيقاف العقد بتاريخ $pause_date - السبب: $pause_reason";

    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => $contract_id],
            'data' => $new_data
        ],
        contractNoteOperation($contract_id, $note, $user_id)
    ];

    $result = enqueueContractApproval($contract_id, 'pause', [
        'old_values' => ['status' => $contract_before['status'], 'pause_reason' => $contract_before['pause_reason']],
        'new_values' => $new_data
    ], $operations, $conn);

    echo json_encode($result);
}

// 4. استئناف العقد
else if ($action === 'resume') {
    $resume_reason = isset($_POST['resume_reason']) ? trim($_POST['resume_reason']) : '';
    $resume_date = isset($_POST['resume_date']) ? $_POST['resume_date'] : date('Y-m-d');
    $pause_days = isset($_POST['pause_days']) ? intval($_POST['pause_days']) : 0;
    $pause_handling = isset($_POST['pause_handling']) ? $_POST['pause_handling'] : 'extend';

    if (!empty($resume_date)) {
        $date_validation = DateTime::createFromFormat('Y-m-d', $resume_date);
        if (!$date_validation) {
            die(json_encode(['success' => false, 'message' => 'صيغة التاريخ غير صحيحة']));
        }
    }

    $contract_before = getContractData($contract_id, $conn, $is_super_admin, $company_id);
    if (!$contract_before) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }

    $new_end_date = $contract_before['actual_end'];
    if ($pause_days > 0 && !empty($contract_before['actual_end'])) {
        $endDateObj = new DateTime($contract_before['actual_end']);
        if ($pause_handling === 'extend') {
            $endDateObj->modify('+' . $pause_days . ' day');
        } elseif ($pause_handling === 'deduct') {
            $endDateObj->modify('-' . $pause_days . ' day');
        }
        $new_end_date = $endDateObj->format('Y-m-d');
    }

    $new_data = [
        'status' => 1,
        'pause_reason' => null,
        'resume_date' => $resume_date,
        'actual_end' => $new_end_date,
        'updated_at' => approval_now()
    ];

    $note = "تم استئناف العقد بتاريخ $resume_date";
    if ($pause_days > 0) {
        $note .= " - مدة الإيقاف: $pause_days يوم";
        $note .= ($pause_handling === 'deduct') ? " (تم خصم أيام الإيقاف من تاريخ الانتهاء)" : " (تم تمديد العقد بإضافة أيام الإيقاف)";
    }
    if ($resume_reason !== '') {
        $note .= " - الملاحظات: $resume_reason";
    }

    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => $contract_id],
            'data' => $new_data
        ],
        contractNoteOperation($contract_id, $note, $user_id)
    ];

    $result = enqueueContractApproval($contract_id, 'resume', [
        'old_values' => [
            'status' => $contract_before['status'],
            'actual_end' => $contract_before['actual_end']
        ],
        'new_values' => $new_data,
        'pause_days' => $pause_days,
        'pause_handling' => $pause_handling
    ], $operations, $conn);

    echo json_encode($result);
}

// 5. إنهاء العقد
else if ($action === 'terminate') {
    $termination_type = isset($_POST['termination_type']) ? $_POST['termination_type'] : '';
    $termination_reason = isset($_POST['termination_reason']) ? trim($_POST['termination_reason']) : '';

    if (empty($termination_type)) {
        die(json_encode(['success' => false, 'message' => 'الرجاء اختيار نوع الإنهاء']));
    }

    if (!in_array($termination_type, ['amicable', 'hardship'])) {
        die(json_encode(['success' => false, 'message' => 'نوع الإنهاء غير صحيح']));
    }

    $contract_before = getContractData($contract_id, $conn, $is_super_admin, $company_id);
    if (!$contract_before) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }

    $termination_type_ar = ($termination_type === 'amicable') ? 'رضائي' : 'بسبب التعسر';
    $termination_date = date('Y-m-d');
    $old_end_date = !empty($contract_before['actual_end']) ? $contract_before['actual_end'] : 'غير محدد';

    $new_data = [
        'status' => 0,
        'termination_type' => $termination_type,
        'termination_reason' => $termination_reason,
        'updated_at' => approval_now()
    ];

    $note = "تم إنهاء العقد ($termination_type_ar) بتاريخ $termination_date - تاريخ الانتهاء السابق: $old_end_date";
    if ($termination_reason !== '') {
        $note .= " - السبب: $termination_reason";
    }

    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => $contract_id],
            'data' => $new_data
        ],
        contractNoteOperation($contract_id, $note, $user_id)
    ];

    $result = enqueueContractApproval($contract_id, 'terminate', [
        'old_values' => [
            'status' => $contract_before['status'],
            'termination_type' => $contract_before['termination_type']
        ],
        'new_values' => $new_data
    ], $operations, $conn);

    echo json_encode($result);
}

// 6. دمج عقدين
else if ($action === 'merge') {
    $merge_with_id = isset($_POST['merge_with_id']) ? intval($_POST['merge_with_id']) : 0;

    if ($merge_with_id <= 0 || $merge_with_id == $contract_id) {
        die(json_encode(['success' => false, 'message' => 'الرجاء اختيار عقد آخر للدمج']));
    }

    $contract_to_merge = getContractData($merge_with_id, $conn, $is_super_admin, $company_id);
    $current_contract = getContractData($contract_id, $conn, $is_super_admin, $company_id);

    if (!$contract_to_merge || !$current_contract) {
        die(json_encode(['success' => false, 'message' => 'أحد العقود غير موجود']));
    }

    if ($contract_to_merge['mine_id'] != $current_contract['mine_id']) {
        die(json_encode(['success' => false, 'message' => 'لا يمكن دمج عقود من مناجم مختلفة']));
    }

    $current_hours = intval($current_contract['forecasted_contracted_hours']);
    $merge_hours = intval($contract_to_merge['forecasted_contracted_hours']);
    $merged_hours = $current_hours + $merge_hours;

    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => $contract_id],
            'data' => [
                'forecasted_contracted_hours' => $merged_hours,
                'merged_with' => $merge_with_id,
                'updated_at' => approval_now()
            ]
        ]
    ];

    $copied_equipments = 0;
    $equipments_query = "SELECT equip_type, equip_size, equip_count, shift_hours, equip_total_month, equip_total_contract
                         FROM contractequipments
                         WHERE contract_id = $merge_with_id";
    $equipments_result = mysqli_query($conn, $equipments_query);

    if ($equipments_result && mysqli_num_rows($equipments_result) > 0) {
        while ($equip = mysqli_fetch_assoc($equipments_result)) {
            $operations[] = [
                'db_action' => 'insert',
                'table' => 'contractequipments',
                'data' => [
                    'contract_id' => $contract_id,
                    'equip_type' => $equip['equip_type'],
                    'equip_size' => intval($equip['equip_size']),
                    'equip_count' => intval($equip['equip_count']),
                    'shift_hours' => intval($equip['shift_hours']),
                    'equip_total_month' => intval($equip['equip_total_month']),
                    'equip_total_contract' => intval($equip['equip_total_contract'])
                ]
            ];
            $copied_equipments++;
        }
    }

    $operations[] = [
        'db_action' => 'update',
        'table' => 'contracts',
        'where' => ['id' => $merge_with_id],
        'data' => [
            'status' => 0,
            'updated_at' => approval_now()
        ]
    ];

    $merge_note_1 = "تم دمج العقد مع العقد رقم $merge_with_id - إجمالي الساعات: $merged_hours";
    if ($copied_equipments > 0) {
        $merge_note_1 .= " - تم نسخ $copied_equipments معدة";
    }

    $merge_note_2 = "تم دمج هذا العقد مع العقد رقم $contract_id - تم تحويل العقد إلى غير ساري";

    $operations[] = contractNoteOperation($contract_id, $merge_note_1, $user_id);
    $operations[] = contractNoteOperation($merge_with_id, $merge_note_2, $user_id);

    $result = enqueueContractApproval($contract_id, 'merge', [
        'merge_with_id' => $merge_with_id,
        'old_values' => [
            'current_contract_hours' => $current_hours,
            'merge_contract_hours' => $merge_hours
        ],
        'new_values' => [
            'current_contract_hours' => $merged_hours,
            'merged_contract_status' => 0
        ],
        'copied_equipments' => $copied_equipments
    ], $operations, $conn);

    echo json_encode($result);
}

// 7. انتهاء العقد
else if ($action === 'complete') {
    $complete_note = isset($_POST['complete_note']) ? trim($_POST['complete_note']) : '';

    if ($complete_note === '') {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال ملاحظات الانتهاء']));
    }

    $note_text = 'انتهاء العقد: ' . $complete_note;

    $operations = [
        contractNoteOperation($contract_id, $note_text, $user_id)
    ];

    $result = enqueueContractApproval($contract_id, 'complete', [
        'new_values' => ['note' => $note_text]
    ], $operations, $conn);

    echo json_encode($result);
}

else {
    die(json_encode(['success' => false, 'message' => 'الإجراء غير معروف']));
}

exit;
?>