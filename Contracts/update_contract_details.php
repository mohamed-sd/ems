<?php
session_start();
if (!isset($_SESSION['user'])) {
    die(json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']));
}

include '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;

if ($contract_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']));
}

// 1. تحديث معلومات المشروع
if ($action === 'update_project_info') {
    $grace_period = isset($_POST['grace_period']) ? intval($_POST['grace_period']) : 0;
    $daily_operators = isset($_POST['daily_operators']) ? intval($_POST['daily_operators']) : 0;
    
    $query = "UPDATE contracts SET 
        grace_period_days = $grace_period,
        daily_operators = $daily_operators,
        updated_at = NOW()
    WHERE id = $contract_id";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'تم تحديث معلومات المشروع بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في التحديث: ' . mysqli_error($conn)]);
    }
}

// 2. تحديث الخدمات
else if ($action === 'update_services') {
    $transportation = isset($_POST['transportation']) ? mysqli_real_escape_string($conn, $_POST['transportation']) : '';
    $accommodation = isset($_POST['accommodation']) ? mysqli_real_escape_string($conn, $_POST['accommodation']) : '';
    $place_for_living = isset($_POST['place_for_living']) ? mysqli_real_escape_string($conn, $_POST['place_for_living']) : '';
    $workshop = isset($_POST['workshop']) ? mysqli_real_escape_string($conn, $_POST['workshop']) : '';
    
    $query = "UPDATE contracts SET 
        transportation = '$transportation',
        accommodation = '$accommodation',
        place_for_living = '$place_for_living',
        workshop = '$workshop',
        updated_at = NOW()
    WHERE id = $contract_id";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'تم تحديث الخدمات بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في التحديث: ' . mysqli_error($conn)]);
    }
}

// 3. تحديث أطراف العقد
else if ($action === 'update_parties') {
    $first_party = isset($_POST['first_party']) ? mysqli_real_escape_string($conn, $_POST['first_party']) : '';
    $second_party = isset($_POST['second_party']) ? mysqli_real_escape_string($conn, $_POST['second_party']) : '';
    $witness_one = isset($_POST['witness_one']) ? mysqli_real_escape_string($conn, $_POST['witness_one']) : '';
    $witness_two = isset($_POST['witness_two']) ? mysqli_real_escape_string($conn, $_POST['witness_two']) : '';
    
    $query = "UPDATE contracts SET 
        first_party = '$first_party',
        second_party = '$second_party',
        witness_one = '$witness_one',
        witness_two = '$witness_two',
        updated_at = NOW()
    WHERE id = $contract_id";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'تم تحديث أطراف العقد بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في التحديث: ' . mysqli_error($conn)]);
    }
}

// 4. تحديث البيانات المالية
else if ($action === 'update_payment') {
    $price_currency_contract = isset($_POST['price_currency_contract']) ? mysqli_real_escape_string($conn, $_POST['price_currency_contract']) : '';
    $paid_contract = isset($_POST['paid_contract']) ? mysqli_real_escape_string($conn, $_POST['paid_contract']) : '';
    $payment_time = isset($_POST['payment_time']) ? mysqli_real_escape_string($conn, $_POST['payment_time']) : '';
    $guarantees = isset($_POST['guarantees']) ? mysqli_real_escape_string($conn, $_POST['guarantees']) : '';
    $payment_date = isset($_POST['payment_date']) ? mysqli_real_escape_string($conn, $_POST['payment_date']) : '';
    
    $query = "UPDATE contracts SET 
        price_currency_contract = '$price_currency_contract',
        paid_contract = '$paid_contract',
        payment_time = '$payment_time',
        guarantees = '$guarantees',
        payment_date = '$payment_date',
        updated_at = NOW()
    WHERE id = $contract_id";
    
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'تم تحديث البيانات المالية بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في التحديث: ' . mysqli_error($conn)]);
    }
}

else {
    die(json_encode(['success' => false, 'message' => 'الإجراء غير معروف']));
}

exit;
?>
