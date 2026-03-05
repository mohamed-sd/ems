<?php
include '../config.php';
require_login();
require_once '../includes/approval_workflow.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
$user_id = approval_get_user_id();

if ($contract_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']));
}

function getContractForDetailsUpdate($contract_id, $conn) {
    $query = "SELECT * FROM contracts WHERE id = $contract_id LIMIT 1";
    $result = mysqli_query($conn, $query);
    return $result ? mysqli_fetch_assoc($result) : null;
}

function submitContractDetailsApproval($contract_id, $action, $new_data, $old_data, $note, $user_id, $conn) {
    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => intval($contract_id)],
            'data' => $new_data
        ],
        [
            'db_action' => 'insert',
            'table' => 'contract_notes',
            'data' => [
                'contract_id' => intval($contract_id),
                'note' => $note,
                'user_id' => intval($user_id),
                'created_at' => approval_now()
            ]
        ]
    ];

    return approval_create_request('contract', intval($contract_id), $action, [
        'summary' => [
            'old_values' => $old_data,
            'new_values' => $new_data
        ],
        'operations' => $operations
    ], $user_id, $conn);
}

$contract_before = getContractForDetailsUpdate($contract_id, $conn);
if (!$contract_before) {
    die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
}

// 1. تحديث معلومات المشروع
if ($action === 'update_project_info') {
    $grace_period = isset($_POST['grace_period']) ? intval($_POST['grace_period']) : 0;
    $daily_operators = isset($_POST['daily_operators']) ? intval($_POST['daily_operators']) : 0;

    $new_data = [
        'grace_period_days' => $grace_period,
        'daily_operators' => $daily_operators,
        'updated_at' => approval_now()
    ];
    $old_data = [
        'grace_period_days' => $contract_before['grace_period_days'],
        'daily_operators' => $contract_before['daily_operators']
    ];

    $result = submitContractDetailsApproval(
        $contract_id,
        'update_project_info',
        $new_data,
        $old_data,
        'طلب تحديث معلومات المشروع بالعقد',
        $user_id,
        $conn
    );
    echo json_encode($result);
}

// 2. تحديث الخدمات
else if ($action === 'update_services') {
    $transportation = isset($_POST['transportation']) ? mysqli_real_escape_string($conn, $_POST['transportation']) : '';
    $accommodation = isset($_POST['accommodation']) ? mysqli_real_escape_string($conn, $_POST['accommodation']) : '';
    $place_for_living = isset($_POST['place_for_living']) ? mysqli_real_escape_string($conn, $_POST['place_for_living']) : '';
    $workshop = isset($_POST['workshop']) ? mysqli_real_escape_string($conn, $_POST['workshop']) : '';

    $new_data = [
        'transportation' => $transportation,
        'accommodation' => $accommodation,
        'place_for_living' => $place_for_living,
        'workshop' => $workshop,
        'updated_at' => approval_now()
    ];
    $old_data = [
        'transportation' => $contract_before['transportation'],
        'accommodation' => $contract_before['accommodation'],
        'place_for_living' => $contract_before['place_for_living'],
        'workshop' => $contract_before['workshop']
    ];

    $result = submitContractDetailsApproval(
        $contract_id,
        'update_services',
        $new_data,
        $old_data,
        'طلب تحديث الخدمات بالعقد',
        $user_id,
        $conn
    );
    echo json_encode($result);
}

// 3. تحديث أطراف العقد
else if ($action === 'update_parties') {
    $first_party = isset($_POST['first_party']) ? mysqli_real_escape_string($conn, $_POST['first_party']) : '';
    $second_party = isset($_POST['second_party']) ? mysqli_real_escape_string($conn, $_POST['second_party']) : '';
    $witness_one = isset($_POST['witness_one']) ? mysqli_real_escape_string($conn, $_POST['witness_one']) : '';
    $witness_two = isset($_POST['witness_two']) ? mysqli_real_escape_string($conn, $_POST['witness_two']) : '';

    $new_data = [
        'first_party' => $first_party,
        'second_party' => $second_party,
        'witness_one' => $witness_one,
        'witness_two' => $witness_two,
        'updated_at' => approval_now()
    ];
    $old_data = [
        'first_party' => $contract_before['first_party'],
        'second_party' => $contract_before['second_party'],
        'witness_one' => $contract_before['witness_one'],
        'witness_two' => $contract_before['witness_two']
    ];

    $result = submitContractDetailsApproval(
        $contract_id,
        'update_parties',
        $new_data,
        $old_data,
        'طلب تحديث أطراف العقد',
        $user_id,
        $conn
    );
    echo json_encode($result);
}

// 4. تحديث البيانات المالية
else if ($action === 'update_payment') {
    $price_currency_contract = isset($_POST['price_currency_contract']) ? mysqli_real_escape_string($conn, $_POST['price_currency_contract']) : '';
    $paid_contract = isset($_POST['paid_contract']) ? mysqli_real_escape_string($conn, $_POST['paid_contract']) : '';
    $payment_time = isset($_POST['payment_time']) ? mysqli_real_escape_string($conn, $_POST['payment_time']) : '';
    $guarantees = isset($_POST['guarantees']) ? mysqli_real_escape_string($conn, $_POST['guarantees']) : '';
    $payment_date = isset($_POST['payment_date']) ? mysqli_real_escape_string($conn, $_POST['payment_date']) : '';

    $new_data = [
        'price_currency_contract' => $price_currency_contract,
        'paid_contract' => $paid_contract,
        'payment_time' => $payment_time,
        'guarantees' => $guarantees,
        'payment_date' => $payment_date,
        'updated_at' => approval_now()
    ];
    $old_data = [
        'price_currency_contract' => $contract_before['price_currency_contract'],
        'paid_contract' => $contract_before['paid_contract'],
        'payment_time' => $contract_before['payment_time'],
        'guarantees' => $contract_before['guarantees'],
        'payment_date' => $contract_before['payment_date']
    ];

    $result = submitContractDetailsApproval(
        $contract_id,
        'update_payment',
        $new_data,
        $old_data,
        'طلب تحديث البيانات المالية للعقد',
        $user_id,
        $conn
    );
    echo json_encode($result);
}

else {
    die(json_encode(['success' => false, 'message' => 'الإجراء غير معروف']));
}

exit;
?>
