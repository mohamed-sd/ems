<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
include '../config.php';
// حارس المعالج (إغلاق فئة B — مسح دَين الحارس): يرث صلاحية شاشته الأم
require_once __DIR__ . '/../includes/handler_guard.php';
ems_guard_handler($conn, 'Employees/employee_contracts.php', 'edit');

require_once '../includes/permissions_helper.php';

while (ob_get_level()) ob_end_clean();

// تعيين نوع المحتوى كـ JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من أن الطلب من نفس الموقع
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

enforce_module_permission_json($conn, 'drivers', 'edit', 'لا توجد صلاحية تعديل المشغلين/عقودهم');

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'لا يمكن تحديد الشركة الحالية']));
}

// بوابة العزل — تستبدل driverContractTenantScopeSql (وسُلَّم project/users الاحتياطي)
function dca_gate()
{
    $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    return ($role === '-1')
        ? ems_tenant_db()->forAllTenants('driver contract actions super')
        : ems_tenant_db();
}

/** تحديث عقد السائق معزولًا (NOW() → توقيت PHP). يعيد true/false. */
function dca_update($contract_id, array $data)
{
    $data['updated_at'] = date('Y-m-d H:i:s');
    try {
        dca_gate()->update('drivercontracts', $data, array('id' => intval($contract_id)));
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;

if (!$contract_id) {
    die(json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']));
}

// دالة للحصول على بيانات العقد (التوقيع محفوظ)
function getContractData($contract_id, $conn, $is_super_admin, $company_id) {
    try {
        return dca_gate()->selectOne('drivercontracts', array('where' => array('id' => intval($contract_id))));
    } catch (\Throwable $e) {
        return null;
    }
}

// دالة لإضافة ملاحظة (حقن الشركة آليًّا؛ القيم خام — الربط يتكفّل بالهروب)
function addNote($contract_id, $note, $conn) {
    try {
        dca_gate()->insert('driver_contract_notes', array(
            'contract_id' => intval($contract_id),
            'note'        => $note,
            'created_at'  => date('Y-m-d H:i:s'),
        ));
        return true;
    } catch (\Throwable $e) {
        return false;
    }
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

    // حساب المدة بالشهور
    $start = new DateTime($new_start_date);
    $end = new DateTime($new_end_date);
    $interval = $start->diff($end);
    $months = $interval->m + ($interval->y * 12);

    // إذا لم يتم إرسال contract_duration_days، نحسبه من التواريخ
    if ($contract_duration_days <= 0) {
        $contract_duration_days = $interval->days;
    }

    if (dca_update($contract_id, array(
        'actual_start' => $new_start_date,
        'actual_end' => $new_end_date,
        'contract_duration_months' => $months,
        'contract_duration_days' => $contract_duration_days,
        'status' => 1,
    ))) {
        $note_text = "تم تجديد العقد من $new_start_date إلى $new_end_date (مدة: $months شهور / $contract_duration_days يوم)";
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

    $contract = getContractData($contract_id, $conn, $is_super_admin, $company_id);
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
    if (dca_update($contract_id, array('forecasted_contracted_hours' => $new_hours))) {
        $note = "تم تسوية العقد: $settlement_type_ar $settlement_hours ساعة";
        if (!empty($settlement_reason)) {
            $note .= " - السبب: $settlement_reason";
        }
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

    if (dca_update($contract_id, array('status' => 0, 'pause_reason' => $pause_reason, 'pause_date' => $pause_date))) {
        $note = "تم إيقاف العقد بتاريخ $pause_date - السبب: $pause_reason";
        addNote($contract_id, $note, $conn);
        echo json_encode(['success' => true, 'message' => 'تم إيقاف العقد بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد']);
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

    // حساب تاريخ الانتهاء الجديد في PHP (كان DATE_ADD/DATE_SUB) من قراءةٍ معزولة
    $resume_data = array('status' => 1, 'pause_reason' => null, 'resume_date' => $resume_date);
    if ($pause_days > 0) {
        $fresh = getContractData($contract_id, $conn, $is_super_admin, $company_id);
        if ($fresh && !empty($fresh['actual_end'])) {
            $endObj = new DateTime($fresh['actual_end']);
            if ($pause_handling === 'extend') {
                $endObj->modify('+' . $pause_days . ' day');
            } elseif ($pause_handling === 'deduct') {
                $endObj->modify('-' . $pause_days . ' day');
            }
            $resume_data['actual_end'] = $endObj->format('Y-m-d');
        }
    }

    if (dca_update($contract_id, $resume_data)) {
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
        addNote($contract_id, $note, $conn);
        echo json_encode(['success' => true, 'message' => 'تم استئناف العقد بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد']);
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
    $contract_before_termination = getContractData($contract_id, $conn, $is_super_admin, $company_id);
    $old_end_date = ($contract_before_termination && !empty($contract_before_termination['actual_end']))
        ? $contract_before_termination['actual_end']
        : 'غير محدد';

    $termination_type_ar = ($termination_type === 'amicable') ? 'رضائي' : 'بسبب التعسر';
    $termination_date = date('Y-m-d');
    if (dca_update($contract_id, array('status' => 0, 'termination_type' => $termination_type, 'termination_reason' => $termination_reason))) {
        $note = "تم إنهاء العقد ($termination_type_ar) بتاريخ $termination_date - تاريخ الانتهاء السابق: $old_end_date";
        if (!empty($termination_reason)) {
            $note .= " - السبب: $termination_reason";
        }
        addNote($contract_id, $note, $conn);
        echo json_encode(['success' => true, 'message' => 'تم إنهاء العقد بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد']);
    }
}

// 6. دمج عقدين
else if ($action === 'merge') {
    $merge_with_id = isset($_POST['merge_with_id']) ? intval($_POST['merge_with_id']) : 0;

    if ($merge_with_id <= 0 || $merge_with_id == $contract_id) {
        die(json_encode(['success' => false, 'message' => 'الرجاء اختيار عقد آخر للدمج']));
    }

    // الحصول على بيانات العقد المراد الدمج معه
    $contract_to_merge = getContractData($merge_with_id, $conn, $is_super_admin, $company_id);
    if (!$contract_to_merge) {
        die(json_encode(['success' => false, 'message' => 'العقد المختار غير موجود']));
    }

    // الحصول على بيانات العقد الحالي
    $current_contract = getContractData($contract_id, $conn, $is_super_admin, $company_id);
    if (!$current_contract) {
        die(json_encode(['success' => false, 'message' => 'العقد الحالي غير موجود']));
    }

    // التحقق من أن العقدين في نفس المشروع
    if ($contract_to_merge['project_id'] != $current_contract['project_id']) {
        die(json_encode(['success' => false, 'message' => 'لا يمكن دمج عقود من مشاريع مختلفة']));
    }

    // حساب المجموع
    $current_hours = intval($current_contract['forecasted_contracted_hours']);
    $merge_hours = intval($contract_to_merge['forecasted_contracted_hours']);
    $merged_hours = $current_hours + $merge_hours;

    // تحديث العقد الحالي بالبيانات المدمجة (معزولًا)
    if (dca_update($contract_id, array('forecasted_contracted_hours' => $merged_hours, 'merged_with' => $merge_with_id))) {
        // نسخ معدات العقد المدموج إلى العقد الحالي (قراءةٌ وإدراجٌ عبر البوابة —
        // company_id تُحقن آليًّا لكل سطرٍ منسوخ)
        $copied_equipments = 0;
        try {
            $gate = dca_gate();
            $merge_rows = $gate->select('drivercontractequipments', array(
                'columns' => array('equip_type', 'equip_size', 'equip_count', 'shift_hours', 'equip_total_month', 'equip_total_contract'),
                'where'   => array('contract_id' => $merge_with_id),
            ));
            foreach ($merge_rows as $equip) {
                $equip['contract_id'] = $contract_id;
                $gate->insert('drivercontractequipments', $equip);
                $copied_equipments++;
            }
        } catch (\Throwable $e) { /* النسخ إضافي — فشله يُسجَّل أدناه ضمنيًّا بعدّاد 0 */ }

        // تحويل العقد المدموج إلى غير ساري (status = 0)
        if (!dca_update($merge_with_id, array('status' => 0))) {
            $error_note = "تحذير: فشل تحديث حالة العقد المدموج إلى غير ساري";
            addNote($contract_id, $error_note, $conn);
        }

        // إضافة ملاحظة للعقد الحالي
        $merge_note_1 = "تم دمج العقد مع العقد رقم $merge_with_id - إجمالي الساعات: $merged_hours (العقد الحالي: $current_hours + العقد المدموج: $merge_hours)";
        if ($copied_equipments > 0) {
            $merge_note_1 .= " - تم نسخ $copied_equipments معدة";
        }
        addNote($contract_id, $merge_note_1, $conn);

        // إضافة ملاحظة للعقد المدموج
        $merge_note_2 = "تم دمج هذا العقد مع العقد رقم $contract_id - تم تحويل العقد إلى غير ساري";
        addNote($merge_with_id, $merge_note_2, $conn);

        $success_message = 'تم دمج العقود بنجاح';
        if ($copied_equipments > 0) {
            $success_message .= " - تم نسخ $copied_equipments معدة";
        }
        $success_message .= " - إجمالي الساعات: $merged_hours";

        echo json_encode(['success' => true, 'message' => $success_message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطأ في دمج العقود']);
    }
}

// 7. انتهاء العقد
elseif ($action === 'complete') {
    $complete_note = isset($_POST['complete_note']) ? $_POST['complete_note'] : '';

    if (empty($complete_note)) {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال ملاحظات الانتهاء']));
    }

    // إضافة الملاحظة في جدول driver_contract_notes
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
