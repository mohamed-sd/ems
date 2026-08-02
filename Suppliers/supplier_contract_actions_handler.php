<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
include '../config.php';
require_once '../includes/permissions_helper.php';

while (ob_get_level()) ob_end_clean();

// تعيين نوع المحتوى كـ JSON
header('Content-Type: application/json; charset=utf-8');

// التحقق من أن الطلب من نفس الموقع
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

enforce_module_permission_json($conn, 'Suppliers/supplierscontracts_details.php', 'edit', 'لا توجد صلاحية تعديل عقود الموردين');

// H-20: إجراءاتُ دورة حياة العقد أفعالُ كتابةٍ داخلية — بوابةُ المشرف الخارجي
// قراءةٌ حصرًا فتُحجب كليًّا ولو حمل حسابُه صلاحيةَ تعديلٍ موروثة
require_once __DIR__ . '/../app/Services/Portal/SupplierPortalGuard.php';
if (\App\Services\Portal\SupplierPortalGuard::isRestricted($_SESSION['user'] ?? array())) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'خارج نطاق بوابة مشرف المورد'], JSON_UNESCAPED_UNICODE));
}

$is_super_admin = isset($_SESSION['user']['role']) && (string)$_SESSION['user']['role'] === '-1';
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'لا يمكن تحديد الشركة الحالية']));
}

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): بُناة شروط النطاق أُسقطوا —
// البوابة مسؤولة النطاق، والسوبر عبر forAllTenants المسجَّل (سلوك الأصل).
$sch_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('supplier contract actions super') : ems_tenant_db();

$action = isset($_POST['action']) ? $_POST['action'] : '';
$contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;

if (!$contract_id) {
    die(json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']));
}

// دالة للحصول على بيانات العقد (النطاق عبر البوابة)
function getContractData($contract_id, $gate) {
    try {
        return $gate->selectOne('supplierscontracts', array(
            'where' => array('id' => intval($contract_id)),
        ));
    } catch (\Throwable $t) {
        return null;
    }
}

// دالة لإضافة ملاحظة (company_id تختمه البوابة للصفوف الجديدة)
function addNote($contract_id, $note, $gate) {
    try {
        $gate->insert('supplier_contract_notes', array(
            'contract_id' => intval($contract_id),
            'note'        => $note,
            'created_at'  => date('Y-m-d H:i:s'),
        ));
        return true;
    } catch (\Throwable $t) {
        return false;
    }
}

$current_contract_scope = getContractData($contract_id, $sch_gate);
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

    try {
        $sch_gate->update('supplierscontracts', array(
            'actual_start'             => $new_start_date,
            'actual_end'               => $new_end_date,
            'contract_duration_months' => $months,
            'contract_duration_days'   => $contract_duration_days,
            'status'                   => 1,
            'updated_at'               => date('Y-m-d H:i:s'),
        ), array('id' => $contract_id));
        $note_text = "تم تجديد العقد من $new_start_date إلى $new_end_date (مدة: $months شهور / $contract_duration_days يوم)";
        addNote($contract_id, $note_text, $sch_gate);
        echo json_encode(['success' => true, 'message' => 'تم تجديد العقد بنجاح']);
    } catch (\Throwable $t) {
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

    $contract = getContractData($contract_id, $sch_gate);
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
    try {
        $sch_gate->update('supplierscontracts', array(
            'forecasted_contracted_hours' => $new_hours,
            'updated_at'                  => date('Y-m-d H:i:s'),
        ), array('id' => $contract_id));
        $note = "تم تسوية العقد: $settlement_type_ar $settlement_hours ساعة";
        if (!empty($settlement_reason)) {
            $note .= " - السبب: $settlement_reason";
        }
        addNote($contract_id, $note, $sch_gate);
        echo json_encode(['success' => true, 'message' => 'تم تسوية العقد بنجاح - الساعات الجديدة: ' . $new_hours]);
    } catch (\Throwable $t) {
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

    try {
        $sch_gate->update('supplierscontracts', array(
            'status'       => 0,
            'pause_reason' => $pause_reason,
            'pause_date'   => $pause_date,
            'updated_at'   => date('Y-m-d H:i:s'),
        ), array('id' => $contract_id));
        $note = "تم إيقاف العقد بتاريخ $pause_date - السبب: $pause_reason";
        addNote($contract_id, $note, $sch_gate);
        echo json_encode(['success' => true, 'message' => 'تم إيقاف العقد بنجاح']);
    } catch (\Throwable $t) {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد: ' . $t->getMessage()]);
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

    // حساب تاريخ الانتهاء الجديد في PHP (كان DATE_ADD/DATE_SUB في SQL —
    // البوابة prepared بقيمٍ لا تعابير، والحساب مكافئ حرفيًّا على عمود DATE)
    $resume_fields = array(
        'status'       => 1,
        'pause_reason' => null,
        'resume_date'  => $resume_date,
        'updated_at'   => date('Y-m-d H:i:s'),
    );
    if ($pause_days > 0 && in_array($pause_handling, array('extend', 'deduct'), true)
        && !empty($current_contract_scope['actual_end'])) {
        try {
            $end_dt = new DateTime($current_contract_scope['actual_end']);
            $end_dt->modify(($pause_handling === 'extend' ? '+' : '-') . $pause_days . ' day');
            $resume_fields['actual_end'] = $end_dt->format('Y-m-d');
        } catch (\Throwable $t) {
            // تاريخ انتهاءٍ غير صالح — سلوك الأصل: يمضي التحديث بلا تعديل actual_end
        }
    }

    try {
        $sch_gate->update('supplierscontracts', $resume_fields, array('id' => $contract_id));
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
        addNote($contract_id, $note, $sch_gate);
        echo json_encode(['success' => true, 'message' => 'تم استئناف العقد بنجاح']);
    } catch (\Throwable $t) {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد: ' . $t->getMessage()]);
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
    $contract_before_termination = getContractData($contract_id, $sch_gate);
    $old_end_date = ($contract_before_termination && !empty($contract_before_termination['actual_end']))
        ? $contract_before_termination['actual_end']
        : 'غير محدد';

    $termination_type_ar = ($termination_type === 'amicable') ? 'رضائي' : 'بسبب التعسر';
    $termination_date = date('Y-m-d');
    try {
        $sch_gate->update('supplierscontracts', array(
            'status'             => 0,
            'termination_type'   => $termination_type,
            'termination_reason' => $termination_reason,
            'updated_at'         => date('Y-m-d H:i:s'),
        ), array('id' => $contract_id));
        $note = "تم إنهاء العقد ($termination_type_ar) بتاريخ $termination_date - تاريخ الانتهاء السابق: $old_end_date";
        if (!empty($termination_reason)) {
            $note .= " - السبب: $termination_reason";
        }
        addNote($contract_id, $note, $sch_gate);
        echo json_encode(['success' => true, 'message' => 'تم إنهاء العقد بنجاح']);
    } catch (\Throwable $t) {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث العقد: ' . $t->getMessage()]);
    }
}

// 6. دمج عقدين
else if ($action === 'merge') {
    $merge_with_id = isset($_POST['merge_with_id']) ? intval($_POST['merge_with_id']) : 0;

    if ($merge_with_id <= 0 || $merge_with_id == $contract_id) {
        die(json_encode(['success' => false, 'message' => 'الرجاء اختيار عقد آخر للدمج']));
    }

    // الحصول على بيانات العقد المراد الدمج معه
    $contract_to_merge = getContractData($merge_with_id, $sch_gate);
    if (!$contract_to_merge) {
        die(json_encode(['success' => false, 'message' => 'العقد المختار غير موجود']));
    }

    // الحصول على بيانات العقد الحالي
    $current_contract = getContractData($contract_id, $sch_gate);
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

    // تحديث العقد الحالي بالبيانات المدمجة
    try {
        $sch_gate->update('supplierscontracts', array(
            'forecasted_contracted_hours' => $merged_hours,
            'merged_with'                 => $merge_with_id,
            'updated_at'                  => date('Y-m-d H:i:s'),
        ), array('id' => $contract_id));
    } catch (\Throwable $t) {
        die(json_encode(['success' => false, 'message' => 'خطأ في دمج العقود: ' . $t->getMessage()]));
    }

    // عداد للمعدات المنسوخة
    $copied_equipments = 0;

    // نسخ معدات العقد المدموج إلى العقد الحالي (قراءةً وكتابةً عبر البوابة)
    try {
        $merge_equip_rows = $sch_gate->scopedQuery(array(
            'scope' => array('sce' => 'suppliercontractequipments'),
        ), "SELECT sce.equip_type, sce.equip_size, sce.equip_count, sce.shift_hours, sce.equip_total_month, sce.equip_total_contract
            FROM suppliercontractequipments sce
            WHERE {TENANT_SCOPE} AND sce.contract_id = ?", array($merge_with_id));
    } catch (\Throwable $t) { $merge_equip_rows = array(); }

    foreach ($merge_equip_rows as $equip) {
        try {
            $sch_gate->insert('suppliercontractequipments', array(
                'contract_id'          => $contract_id,
                'equip_type'           => $equip['equip_type'],
                'equip_size'           => intval($equip['equip_size']),
                'equip_count'          => intval($equip['equip_count']),
                'shift_hours'          => intval($equip['shift_hours']),
                'equip_total_month'    => intval($equip['equip_total_month']),
                'equip_total_contract' => intval($equip['equip_total_contract']),
            ));
            $copied_equipments++;
        } catch (\Throwable $t) {
            // سلوك الأصل: فشل نسخ معدةٍ لا يوقف الدمج — تُحتسب المنسوخة فقط
        }
    }

    // تحويل العقد المدموج إلى غير ساري (status = 0)
    try {
        $sch_gate->update('supplierscontracts', array(
            'status'     => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ), array('id' => $merge_with_id));
    } catch (\Throwable $t) {
        // في حالة فشل تحديث الحالة، نسجل ذلك في الملاحظات
        $error_note = "تحذير: فشل تحديث حالة العقد المدموج إلى غير ساري";
        addNote($contract_id, $error_note, $sch_gate);
    }

    // إضافة ملاحظة للعقد الحالي
    $merge_note_1 = "تم دمج العقد مع العقد رقم $merge_with_id - إجمالي الساعات: $merged_hours (العقد الحالي: $current_hours + العقد المدموج: $merge_hours)";
    if ($copied_equipments > 0) {
        $merge_note_1 .= " - تم نسخ $copied_equipments معدة";
    }
    addNote($contract_id, $merge_note_1, $sch_gate);

    // إضافة ملاحظة للعقد المدموج
    $merge_note_2 = "تم دمج هذا العقد مع العقد رقم $contract_id - تم تحويل العقد إلى غير ساري";
    addNote($merge_with_id, $merge_note_2, $sch_gate);

    $success_message = 'تم دمج العقود بنجاح';
    if ($copied_equipments > 0) {
        $success_message .= " - تم نسخ $copied_equipments معدة";
    }
    $success_message .= " - إجمالي الساعات: $merged_hours";

    echo json_encode(['success' => true, 'message' => $success_message]);
}

// 7. انتهاء العقد
elseif ($action === 'complete') {
    $complete_note = isset($_POST['complete_note']) ? $_POST['complete_note'] : '';

    if (empty($complete_note)) {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال ملاحظات الانتهاء']));
    }

    $complete_fields = array(
        'status'     => 0,
        'updated_at' => date('Y-m-d H:i:s'),
    );
    if (db_table_has_column($conn, 'supplierscontracts', 'contract_status')) {
        $complete_fields['contract_status'] = 'completed';
    }

    try {
        $sch_gate->update('supplierscontracts', $complete_fields, array('id' => $contract_id));
    } catch (\Throwable $t) {
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث حالة العقد: ' . $t->getMessage()]);
        exit;
    }

    // إضافة الملاحظة في جدول supplier_contract_notes
    $note_text = "تم إنهاء العقد وتحويل حالته إلى غير ساري: " . $complete_note;
    if (addNote($contract_id, $note_text, $sch_gate)) {
        echo json_encode(['success' => true, 'message' => 'تم تسجيل انتهاء العقد وتحديث حالته بنجاح']);
    } else {
        echo json_encode(['success' => true, 'message' => 'تم تحديث حالة العقد إلى غير ساري، لكن تعذر حفظ الملاحظة']);
    }
}

else {
    die(json_encode(['success' => false, 'message' => 'الإجراء غير معروف']));
}

exit;
