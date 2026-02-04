<?php
session_start();
include '../config.php';

// تعيين نوع المحتوى كـ JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من أن الطلب من نفس الموقع
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;

if (!$contract_id) {
    die(json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']));
}

// دالة للحصول على بيانات العقد
function getContractData($contract_id, $conn) {
    $query = "SELECT * FROM contracts WHERE id = $contract_id LIMIT 1";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}

// دالة لإضافة ملاحظة
function addNote($contract_id, $note, $conn) {
    $note = mysqli_real_escape_string($conn, $note);
    $user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
    $query = "INSERT INTO contract_notes (contract_id, note, user_id, created_at) VALUES ($contract_id, '$note', $user_id, NOW())";
    return mysqli_query($conn, $query);
}

// 1. تجديد العقد
if ($action === 'renewal') {
    $new_start_date = isset($_POST['new_start_date']) ? $_POST['new_start_date'] : '';
    $new_end_date = isset($_POST['new_end_date']) ? $_POST['new_end_date'] : '';
    $contract_duration_days = isset($_POST['contract_duration_days']) ? intval($_POST['contract_duration_days']) : 0;
    
    if (empty($new_start_date) || empty($new_end_date)) {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال تاريخي البدء والانتهاء']));
    }
    
    // التحقق من صيغة التاريخ
    $start_validation = DateTime::createFromFormat('Y-m-d', $new_start_date);
    $end_validation = DateTime::createFromFormat('Y-m-d', $new_end_date);
    
    if (!$start_validation || !$end_validation) {
        die(json_encode(['success' => false, 'message' => 'صيغة التاريخ غير صحيحة']));
    }
    
    // التحقق من أن تاريخ البدء قبل تاريخ الانتهاء
    if (strtotime($new_start_date) >= strtotime($new_end_date)) {
        die(json_encode(['success' => false, 'message' => 'تاريخ البدء يجب أن يكون قبل تاريخ الانتهاء']));
    }
    
    $new_start_date = mysqli_real_escape_string($conn, $new_start_date);
    $new_end_date = mysqli_real_escape_string($conn, $new_end_date);
    
    // حساب المدة بالشهور
    $start = new DateTime($new_start_date);
    $end = new DateTime($new_end_date);
    $interval = $start->diff($end);
    $months = $interval->m + ($interval->y * 12);
    
    // إذا لم يتم إرسال contract_duration_days، نحسبه من التواريخ
    if ($contract_duration_days <= 0) {
        $contract_duration_days = $interval->days;
    }
    
    $query = "UPDATE contracts SET 
        actual_start = '$new_start_date',
        actual_end = '$new_end_date',
        contract_duration_months = $months,
        contract_duration_days = $contract_duration_days,
        status = 1,
        updated_at = NOW()
    WHERE id = $contract_id";
    
    if (mysqli_query($conn, $query)) {
        $note_text = "تم تجديد العقد من $new_start_date إلى $new_end_date (مدة: $months شهور / $contract_duration_days يوم)";
        $note_text = mysqli_real_escape_string($conn, $note_text);
        addNote($contract_id, $note_text, $conn);
        echo json_encode(['success' => true, 'message' => 'تم تجديد العقد بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد']);
    }
}

// 2. تسوية العقد (زيادة أو نقصان ساعات)
else if ($action === 'settlement') {
    $settlement_type = isset($_POST['settlement_type']) ? $_POST['settlement_type'] : ''; // increase أو decrease
    $settlement_hours = isset($_POST['settlement_hours']) ? intval($_POST['settlement_hours']) : 0;
    $settlement_reason = isset($_POST['settlement_reason']) ? $_POST['settlement_reason'] : '';
    
    if (empty($settlement_type) || $settlement_hours <= 0) {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال نوع التسوية وعدد الساعات']));
    }
    
    // التحقق من نوع التسوية
    if (!in_array($settlement_type, ['increase', 'decrease'])) {
        die(json_encode(['success' => false, 'message' => 'نوع التسوية غير صحيح']));
    }
    
    $contract = getContractData($contract_id, $conn);
    if (!$contract) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }
    
    $current_hours = $contract['forecasted_contracted_hours'];
    $new_hours = ($settlement_type === 'increase') ? 
                 $current_hours + $settlement_hours : 
                 $current_hours - $settlement_hours;
    
    if ($new_hours < 0) {
        die(json_encode(['success' => false, 'message' => 'عدد الساعات المحسوبة أقل من صفر']));
    }
    
    $settlement_type_ar = ($settlement_type === 'increase') ? 'زيادة' : 'نقصان';
    $query = "UPDATE contracts SET 
        forecasted_contracted_hours = $new_hours,
        updated_at = NOW()
    WHERE id = $contract_id";
    
    if (mysqli_query($conn, $query)) {
        $note = "تم تسوية العقد: $settlement_type_ar $settlement_hours ساعة";
        if (!empty($settlement_reason)) {
            $settlement_reason = mysqli_real_escape_string($conn, $settlement_reason);
            $note .= " - السبب: $settlement_reason";
        }
        $note = mysqli_real_escape_string($conn, $note);
        addNote($contract_id, $note, $conn);
        echo json_encode(['success' => true, 'message' => 'تم تسوية العقد بنجاح - الساعات الجديدة: ' . $new_hours]);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد']);
    }
}

// 3. إيقاف العقد
else if ($action === 'pause') {
    $pause_reason = isset($_POST['pause_reason']) ? $_POST['pause_reason'] : '';
    $pause_date = isset($_POST['pause_date']) ? $_POST['pause_date'] : date('Y-m-d');
    
    if (empty($pause_reason)) {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال سبب الإيقاف']));
    }
    
    // التحقق من صيغة التاريخ
    if (!empty($pause_date)) {
        $date_validation = DateTime::createFromFormat('Y-m-d', $pause_date);
        if (!$date_validation) {
            die(json_encode(['success' => false, 'message' => 'صيغة التاريخ غير صحيحة']));
        }
    }
    
    $pause_reason = mysqli_real_escape_string($conn, $pause_reason);
    $pause_date = mysqli_real_escape_string($conn, $pause_date);
    
    $query = "UPDATE contracts SET 
        status = 0,
        pause_reason = '$pause_reason',
        pause_date = '$pause_date',
        updated_at = NOW()
    WHERE id = $contract_id";
    
    if (mysqli_query($conn, $query)) {
        $note = "تم إيقاف العقد بتاريخ $pause_date - السبب: $pause_reason";
        $note = mysqli_real_escape_string($conn, $note);
        addNote($contract_id, $note, $conn);
        echo json_encode(['success' => true, 'message' => 'تم إيقاف العقد بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد: ' . mysqli_error($conn)]);
    }
}

// 4. استئناف العقد
else if ($action === 'resume') {
    $resume_reason = isset($_POST['resume_reason']) ? $_POST['resume_reason'] : '';
    $resume_date = isset($_POST['resume_date']) ? $_POST['resume_date'] : date('Y-m-d');
    $pause_days = isset($_POST['pause_days']) ? intval($_POST['pause_days']) : 0;
    $pause_handling = isset($_POST['pause_handling']) ? $_POST['pause_handling'] : 'extend'; // extend أو deduct
    
    // التحقق من صيغة التاريخ
    if (!empty($resume_date)) {
        $date_validation = DateTime::createFromFormat('Y-m-d', $resume_date);
        if (!$date_validation) {
            die(json_encode(['success' => false, 'message' => 'صيغة التاريخ غير صحيحة']));
        }
    }
    
    $resume_reason = mysqli_real_escape_string($conn, $resume_reason);
    $resume_date = mysqli_real_escape_string($conn, $resume_date);
    
    // حساب التاريخ الجديد للانتهاء بناءً على الخيار المحدد
    $new_end_date_sql = '';
    if ($pause_days > 0) {
        if ($pause_handling === 'extend') {
            // إضافة أيام الإيقاف إلى تاريخ الانتهاء (تمديد العقد)
            $new_end_date_sql = ", actual_end = DATE_ADD(actual_end, INTERVAL $pause_days DAY)";
        } else if ($pause_handling === 'deduct') {
            // خصم أيام الإيقاف من تاريخ الانتهاء (تقليل مدة العقد)
            $new_end_date_sql = ", actual_end = DATE_SUB(actual_end, INTERVAL $pause_days DAY)";
        }
    }
    
    $query = "UPDATE contracts SET 
        status = 1,
        pause_reason = NULL,
        resume_date = '$resume_date',
        updated_at = NOW()
        $new_end_date_sql
    WHERE id = $contract_id";
    
    if (mysqli_query($conn, $query)) {
        $note = "تم استئناف العقد بتاريخ $resume_date";
        if ($pause_days > 0) {
            $note .= " - مدة الإيقاف: $pause_days يوم";
            if ($pause_handling === 'extend') {
                $note .= " (تم تمديد العقد بإضافة $pause_days يوم إلى تاريخ الانتهاء)";
            } else if ($pause_handling === 'deduct') {
                $note .= " (تم خصم $pause_days يوم من تاريخ انتهاء العقد)";
            }
        }
        if (!empty($resume_reason)) {
            $note .= " - الملاحظات: $resume_reason";
        }
        $note = mysqli_real_escape_string($conn, $note);
        addNote($contract_id, $note, $conn);
        echo json_encode(['success' => true, 'message' => 'تم استئناف العقد بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد: ' . mysqli_error($conn)]);
    }
}

// 5. إنهاء العقد
else if ($action === 'terminate') {
    $termination_type = isset($_POST['termination_type']) ? $_POST['termination_type'] : ''; // amicable أو hardship
    $termination_reason = isset($_POST['termination_reason']) ? $_POST['termination_reason'] : '';
    
    if (empty($termination_type)) {
        die(json_encode(['success' => false, 'message' => 'الرجاء اختيار نوع الإنهاء']));
    }
    
    // التحقق من نوع الإنهاء
    if (!in_array($termination_type, ['amicable', 'hardship'])) {
        die(json_encode(['success' => false, 'message' => 'نوع الإنهاء غير صحيح']));
    }
    
    // الاحتفاظ بتاريخ الانتهاء الحالي قبل التحديث
    $contract_before_termination = getContractData($contract_id, $conn);
    $old_end_date = ($contract_before_termination && !empty($contract_before_termination['actual_end']))
        ? $contract_before_termination['actual_end']
        : 'غير محدد';

    $termination_reason = mysqli_real_escape_string($conn, $termination_reason);
    $termination_type_ar = ($termination_type === 'amicable') ? 'رضائي' : 'بسبب التعسر';
    $termination_date = date('Y-m-d');
    $query = "UPDATE contracts SET 
        status = 0,
        termination_type = '$termination_type',
        termination_reason = '$termination_reason',
        updated_at = NOW()
    WHERE id = $contract_id";
    
    if (mysqli_query($conn, $query)) {
        $note = "تم إنهاء العقد ($termination_type_ar) بتاريخ $termination_date - تاريخ الانتهاء السابق: $old_end_date";
        if (!empty($termination_reason)) {
            $note .= " - السبب: $termination_reason";
        }
        $note = mysqli_real_escape_string($conn, $note);
        addNote($contract_id, $note, $conn);
        echo json_encode(['success' => true, 'message' => 'تم إنهاء العقد بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد: ' . mysqli_error($conn)]);
    }
}

// 6. دمج عقدين
else if ($action === 'merge') {
    $merge_with_id = isset($_POST['merge_with_id']) ? intval($_POST['merge_with_id']) : 0;
    
    if ($merge_with_id <= 0 || $merge_with_id == $contract_id) {
        die(json_encode(['success' => false, 'message' => 'الرجاء اختيار عقد آخر للدمج']));
    }
    
    // الحصول على بيانات العقد المراد الدمج معه
    $contract_to_merge = getContractData($merge_with_id, $conn);
    if (!$contract_to_merge) {
        die(json_encode(['success' => false, 'message' => 'العقد المختار غير موجود']));
    }
    
    // الحصول على بيانات العقد الحالي
    $current_contract = getContractData($contract_id, $conn);
    if (!$current_contract) {
        die(json_encode(['success' => false, 'message' => 'العقد الحالي غير موجود']));
    }
    
    // التحقق من أن العقدين في نفس المنجم
    if ($contract_to_merge['mine_id'] != $current_contract['mine_id']) {
        die(json_encode(['success' => false, 'message' => 'لا يمكن دمج عقود من مناجم مختلفة']));
    }
    
    // حساب المجموع
    $current_hours = intval($current_contract['forecasted_contracted_hours']);
    $merge_hours = intval($contract_to_merge['forecasted_contracted_hours']);
    $merged_hours = $current_hours + $merge_hours;
    
    // تحديث العقد الحالي بالبيانات المدمجة
    $query = "UPDATE contracts SET 
        forecasted_contracted_hours = $merged_hours,
        merged_with = $merge_with_id,
        updated_at = NOW()
    WHERE id = $contract_id";
    
    if (mysqli_query($conn, $query)) {
        // عداد للمعدات المنسوخة
        $copied_equipments = 0;
        
        // نسخ معدات العقد المدموج إلى العقد الحالي
        $get_equipments_query = "SELECT equip_type, equip_size, equip_count, shift_hours, equip_total_month, equip_total_contract FROM contractequipments WHERE contract_id = $merge_with_id";
        $equipments_result = mysqli_query($conn, $get_equipments_query);
        
        if ($equipments_result && mysqli_num_rows($equipments_result) > 0) {
            while ($equip = mysqli_fetch_assoc($equipments_result)) {
                // إدراج المعدة في العقد الحالي
                $equip_type = mysqli_real_escape_string($conn, $equip['equip_type']);
                $equip_size = intval($equip['equip_size']);
                $equip_count = intval($equip['equip_count']);
                $shift_hours = intval($equip['shift_hours']);
                $equip_total_month = intval($equip['equip_total_month']);
                $equip_total_contract = intval($equip['equip_total_contract']);
                
                $insert_equip_query = "INSERT INTO contractequipments (contract_id, equip_type, equip_size, equip_count, shift_hours, equip_total_month, equip_total_contract) 
                    VALUES ($contract_id, '$equip_type', $equip_size, $equip_count, $shift_hours, $equip_total_month, $equip_total_contract)";
                
                if (mysqli_query($conn, $insert_equip_query)) {
                    $copied_equipments++;
                }
            }
        }
        
        // تحويل العقد المدموج إلى غير ساري (status = 0)
        $update_merged_contract = "UPDATE contracts SET 
            status = 0,
            updated_at = NOW()
        WHERE id = $merge_with_id";
        
        if (!mysqli_query($conn, $update_merged_contract)) {
            // في حالة فشل تحديث الحالة، نسجل ذلك في الملاحظات
            $error_note = "تحذير: فشل تحديث حالة العقد المدموج إلى غير ساري";
            addNote($contract_id, $error_note, $conn);
        }
        
        // إضافة ملاحظة للعقد الحالي
        $merge_note_1 = "تم دمج العقد مع العقد رقم $merge_with_id - إجمالي الساعات: $merged_hours (العقد الحالي: $current_hours + العقد المدموج: $merge_hours)";
        if ($copied_equipments > 0) {
            $merge_note_1 .= " - تم نسخ $copied_equipments معدة";
        }
        $merge_note_1 = mysqli_real_escape_string($conn, $merge_note_1);
        addNote($contract_id, $merge_note_1, $conn);
        
        // إضافة ملاحظة للعقد المدموج
        $merge_note_2 = "تم دمج هذا العقد مع العقد رقم $contract_id - تم تحويل العقد إلى غير ساري";
        $merge_note_2 = mysqli_real_escape_string($conn, $merge_note_2);
        addNote($merge_with_id, $merge_note_2, $conn);
        
        $success_message = 'تم دمج العقود بنجاح';
        if ($copied_equipments > 0) {
            $success_message .= " - تم نسخ $copied_equipments معدة";
        }
        $success_message .= " - إجمالي الساعات: $merged_hours";
        
        echo json_encode(['success' => true, 'message' => $success_message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في دمج العقود: ' . mysqli_error($conn)]);
    }
}

// 6. انتهاء العقد
elseif ($action === 'complete') {
    $complete_note = isset($_POST['complete_note']) ? mysqli_real_escape_string($conn, $_POST['complete_note']) : '';
    
    if (empty($complete_note)) {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال ملاحظات الانتهاء']));
    }
    
    // إضافة الملاحظة في جدول contract_notes
    $note_text = "انتهاء العقد: " . $complete_note;
    if (addNote($contract_id, $note_text, $conn)) {
        echo json_encode(['success' => true, 'message' => 'تم تسجيل انتهاء العقد بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في تسجيل انتهاء العقد']);
    }
}

else {
    die(json_encode(['success' => false, 'message' => 'الإجراء غير معروف']));
}

exit;
