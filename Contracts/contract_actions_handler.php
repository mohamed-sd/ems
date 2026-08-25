<?php
include '../config.php';
// حارس المعالج (إغلاق فئة B — مسح دَين الحارس): يرث صلاحية شاشته الأم
require_once __DIR__ . '/../includes/handler_guard.php';
ems_guard_handler($conn, 'Contracts/contracts.php', 'edit');

require_login();
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

// بوابة العزل — تستبدل contractTenantScopeSql القديم (وفيه احتياطي project/users).
function contract_actions_gate()
{
    $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    return ($role === '-1')
        ? ems_tenant_db()->forAllTenants('contract actions super')
        : ems_tenant_db();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$contract_id) {
    die(json_encode(['success' => false, 'message' => 'معرف العقد غير صحيح']));
}

function getContractData($contract_id, $conn, $is_super_admin, $company_id) {
    // التوقيع محفوظ؛ العزل عبر البوابة (وتستثني المحذوف ناعمًا — العقود المؤرشفة
    // لم تعد قابلةً لإجراءات دورة الحياة، وهو الأصحّ دلاليًّا).
    try {
        return contract_actions_gate()->selectOne('contracts', array(
            'where' => array('id' => intval($contract_id)),
        ));
    } catch (\Throwable $e) {
        return null;
    }
}

function addContractNote($contract_id, $note, $user_id, $conn) {
    // إدراجٌ عبر البوابة (company_id تُحقن آليًّا؛ NOW() → توقيت PHP)
    try {
        contract_actions_gate()->insert('contract_notes', array(
            'contract_id' => intval($contract_id),
            'note'        => $note,
            'user_id'     => intval($user_id),
            'created_at'  => date('Y-m-d H:i:s'),
        ));
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

function executeOperations($operations, $conn) {
    // معاملةٌ مُدارة عبر البوابة: كل عمليةٍ داخلها تمرّ بحُرّاس العزل كاملةً،
    // وأي فشلٍ = تراجعُ الكل (الذرّية كالأصل، والعزل مكسبٌ جديد).
    try {
        contract_actions_gate()->runInTransaction(function ($gate) use ($operations) {
            foreach ($operations as $op) {
                if ($op['db_action'] === 'update') {
                    $gate->update($op['table'], $op['data'], $op['where']);
                } elseif ($op['db_action'] === 'insert') {
                    $gate->insert($op['table'], $op['data']);
                }
            }
        }, 'contract actions batch');
        return ['success' => true, 'message' => 'تم تنفيذ العملية بنجاح'];
    } catch (\Throwable $e) {
        return ['success' => false, 'message' => 'فشل في تنفيذ العملية: ' . $e->getMessage()];
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// إسقاط الملاحق (D02 · توحيد مصدر الحقيقة): كل إجراءٍ ماديّ يُولّد صفَّ ملحقٍ مبنينًا
// يُدفَع إلى مصفوفة $operations نفسها ⇒ يُثبَّت أو يتراجع مع تغيير العقد ذرّيًّا.
// ══════════════════════════════════════════════════════════════════════════════

// الكود التالي (AMD-NNNN) مقيَّدًا بشركة العقد — يُحاكي مولّد شاشة الملاحق حرفيًّا.
function amd_next_code($conn, $company_id)
{
    $company_id = intval($company_id);
    $sql = "SELECT amendment_code FROM contract_amendments
            WHERE company_id = ? AND amendment_code REGEXP '^AMD-[0-9]+$' AND is_deleted = 0
            ORDER BY CAST(SUBSTRING(amendment_code, 5) AS UNSIGNED) DESC LIMIT 1";
    $next = 1;
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $company_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $next = intval(substr($row['amendment_code'], 4)) + 1;
            }
        }
        $stmt->close();
    }
    return 'AMD-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

// يبني عمليّة إدراج ملحق. company_id صريحٌ من العقد المتحقَّق ⇒ يصحّ في وضع السوبر
// (forAllTenants) أيضًا، حيث لا يحقن العزلُ شركةَ السياق.
function amd_op($conn, $contract, $user_id, $type, array $fields = array())
{
    $company_id = intval($contract['company_id']);
    $data = array(
        'company_id'      => $company_id,
        'amendment_code'  => amd_next_code($conn, $company_id),
        'contract_id'     => intval($contract['id']),
        'amend_type'      => $type,
        'amend_date'      => isset($fields['amend_date']) ? $fields['amend_date'] : date('Y-m-d'),
        'reason'          => isset($fields['reason']) ? $fields['reason'] : null,
        'old_value'       => isset($fields['old_value']) ? $fields['old_value'] : null,
        'new_value'       => isset($fields['new_value']) ? $fields['new_value'] : null,
        'effect_price'    => isset($fields['effect_price']) ? $fields['effect_price'] : null,
        'effect_qty'      => isset($fields['effect_qty']) ? $fields['effect_qty'] : null,
        'effect_duration' => isset($fields['effect_duration']) ? $fields['effect_duration'] : null,
        'effect_summary'  => isset($fields['effect_summary']) ? $fields['effect_summary'] : null,
        'created_by'      => intval($user_id),
    );
    return array('db_action' => 'insert', 'table' => 'contract_amendments', 'data' => $data);
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
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $note_text = "تم تجديد العقد من $new_start_date إلى $new_end_date (مدة: $months شهور / $contract_duration_days يوم)";

    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => $contract_id],
            'data' => $new_data
        ]
    ];

    // إسقاط الملحق (تجديد) داخل معاملة العقد نفسها
    $operations[] = amd_op($conn, $contract_before, $user_id, 'تجديد', array(
        'amend_date'      => $new_start_date,
        'old_value'       => (string) $contract_before['actual_end'],
        'new_value'       => $new_end_date,
        'effect_duration' => intval($contract_duration_days),
        'effect_summary'  => $note_text,
    ));

    $result = executeOperations($operations, $conn);
    if ($result['success']) {
        addContractNote($contract_id, $note_text, $user_id, $conn);
    }

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
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => $contract_id],
            'data' => $new_data
        ]
    ];

    // إسقاط الملحق (تسوية النطاق) داخل معاملة العقد نفسها — الكمية بإشارتها
    $amd_settle_type = ($settlement_type === 'increase') ? 'زيادة نطاق' : 'تخفيض نطاق';
    $amd_settle_qty  = ($settlement_type === 'increase') ? $settlement_hours : -$settlement_hours;
    $operations[] = amd_op($conn, $contract, $user_id, $amd_settle_type, array(
        'old_value'      => (string) $current_hours,
        'new_value'      => (string) $new_hours,
        'effect_qty'     => $amd_settle_qty,
        'reason'         => $settlement_reason !== '' ? $settlement_reason : null,
        'effect_summary' => $note,
    ));

    // M-10 (CON-02 §6): «الحاوياتُ تُعاد موازنتُها عند تغيّر الكمية» — عملياتُ
    // الموازنة تنضم لمعاملة الملحق نفسِها؛ وفشلُ الفحص المسبق (تخفيضٌ دون
    // المخصَّص للموردين) يُسقط الملحقَ كلَّه — لا ملحقَ بلا موازنته.
    require_once __DIR__ . '/../app/Services/Contract/ClientAmendmentEffects.php';
    $rebalance = \App\Services\Contract\ClientAmendmentEffects::rebalanceOps(
        contract_actions_gate(), $contract, $amd_settle_qty);
    if (!$rebalance['ok']) {
        die(json_encode(['success' => false, 'message' => $rebalance['reason']]));
    }
    foreach ($rebalance['ops'] as $rop) { $operations[] = $rop; }
    if ($rebalance['note'] !== '') { $note .= ' · ' . $rebalance['note']; }

    $result = executeOperations($operations, $conn);
    if ($result['success']) {
        addContractNote($contract_id, $note, $user_id, $conn);
    }

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
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $note = "تم إيقاف العقد بتاريخ $pause_date - السبب: $pause_reason";

    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => $contract_id],
            'data' => $new_data
        ]
    ];

    // إسقاط الملحق (إيقاف) داخل معاملة العقد نفسها
    $operations[] = amd_op($conn, $contract_before, $user_id, 'إيقاف', array(
        'amend_date'     => $pause_date,
        'new_value'      => $pause_date,
        'reason'         => $pause_reason,
        'effect_summary' => $note,
    ));

    $result = executeOperations($operations, $conn);
    if ($result['success']) {
        addContractNote($contract_id, $note, $user_id, $conn);
    }

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
        'updated_at' => date('Y-m-d H:i:s')
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
        ]
    ];

    // إسقاط الملحق (استئناف) داخل معاملة العقد نفسها — أثر المدة بإشارته (تمديد/خصم)
    $amd_resume_dur = 0;
    if ($pause_days > 0) {
        $amd_resume_dur = ($pause_handling === 'deduct') ? -$pause_days : $pause_days;
    }
    $operations[] = amd_op($conn, $contract_before, $user_id, 'استئناف', array(
        'amend_date'      => $resume_date,
        'old_value'       => (string) $contract_before['actual_end'],
        'new_value'       => $new_end_date,
        'effect_duration' => $amd_resume_dur !== 0 ? $amd_resume_dur : null,
        'reason'          => $resume_reason !== '' ? $resume_reason : null,
        'effect_summary'  => $note,
    ));

    $result = executeOperations($operations, $conn);
    if ($result['success']) {
        addContractNote($contract_id, $note, $user_id, $conn);
    }

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
        'updated_at' => date('Y-m-d H:i:s')
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
        ]
    ];

    // إسقاط الملحق (إنهاء) داخل معاملة العقد نفسها
    $operations[] = amd_op($conn, $contract_before, $user_id, 'إنهاء', array(
        'amend_date'     => $termination_date,
        'old_value'      => $termination_type_ar,
        'reason'         => $termination_reason !== '' ? $termination_reason : null,
        'effect_summary' => $note,
    ));

    $result = executeOperations($operations, $conn);
    if ($result['success']) {
        addContractNote($contract_id, $note, $user_id, $conn);
    }

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

    if ($contract_to_merge['project_id'] != $current_contract['project_id']) {
        die(json_encode(['success' => false, 'message' => 'لا يمكن دمج عقود من مشاريع مختلفة']));
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
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]
    ];

    $copied_equipments = 0;
    // سطور معدات العقد المدموج — معزولةً عبر البوابة (تستفيد من تعبئة M4)
    try {
        $merge_equip_rows = contract_actions_gate()->select('contractequipments', array(
            'columns' => array('equip_type', 'equip_size', 'equip_count', 'shift_hours', 'equip_total_month', 'equip_total_contract'),
            'where'   => array('contract_id' => $merge_with_id),
        ));
    } catch (\Throwable $e) {
        $merge_equip_rows = array();
    }

    if (!empty($merge_equip_rows)) {
        foreach ($merge_equip_rows as $equip) {
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
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ];

    $merge_note_1 = "تم دمج العقد مع العقد رقم $merge_with_id - إجمالي الساعات: $merged_hours";
    if ($copied_equipments > 0) {
        $merge_note_1 .= " - تم نسخ $copied_equipments معدة";
    }

    $merge_note_2 = "تم دمج هذا العقد مع العقد رقم $contract_id - تم تحويل العقد إلى غير ساري";

    // إسقاط الملحق (دمج) على العقد الباقي داخل المعاملة نفسها
    $operations[] = amd_op($conn, $current_contract, $user_id, 'دمج', array(
        'old_value'      => (string) $current_hours,
        'new_value'      => (string) $merged_hours,
        'effect_qty'     => intval($merge_hours),
        'effect_summary' => $merge_note_1,
    ));

    $result = executeOperations($operations, $conn);
    if ($result['success']) {
        addContractNote($contract_id, $merge_note_1, $user_id, $conn);
        addContractNote($merge_with_id, $merge_note_2, $user_id, $conn);
    }

    echo json_encode($result);
}

// 7. انتهاء العقد
else if ($action === 'complete') {
    $complete_note = isset($_POST['complete_note']) ? trim($_POST['complete_note']) : '';

    if ($complete_note === '') {
        die(json_encode(['success' => false, 'message' => 'الرجاء إدخال ملاحظات الانتهاء']));
    }

    $operations = [
        [
            'db_action' => 'update',
            'table' => 'contracts',
            'where' => ['id' => $contract_id],
            'data' => [
                'status' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]
    ];

    // إسقاط الملحق (انتهاء) داخل معاملة العقد نفسها
    $operations[] = amd_op($conn, $current_contract_scope, $user_id, 'انتهاء', array(
        'reason'         => $complete_note,
        'effect_summary' => 'انتهاء العقد وتحويل حالته إلى غير ساري',
    ));

    $result = executeOperations($operations, $conn);
    if ($result['success']) {
        $note_text = 'انتهاء العقد وتحويل حالته إلى غير ساري: ' . $complete_note;
        addContractNote($contract_id, $note_text, $user_id, $conn);
        $result = ['success' => true, 'message' => 'تم تسجيل انتهاء العقد وتحويل الحالة إلى غير ساري بنجاح'];
    }

    echo json_encode($result);
}

// M-10. تغيير ملتزمٍ في المصفوفة — ملحقُ «تغيير التزامات» يولّد صفَّ الالتزام
// مسودةً بسريانه ذريًّا (CON-02 §6 · A6) — والإجازةُ تبقى بيد المالية (ق-18).
else if ($action === 'change_obligation') {
    $contract = getContractData($contract_id, $conn, $is_super_admin, $company_id);
    if (!$contract) {
        die(json_encode(['success' => false, 'message' => 'العقد غير موجود']));
    }
    require_once __DIR__ . '/../app/Services/Contract/ClientAmendmentEffects.php';
    $r = \App\Services\Contract\ClientAmendmentEffects::changeObligation(
        $conn, contract_actions_gate(), intval($contract['company_id']), $contract_id, array(
            'obligation_type'   => strval($_POST['obligation_type'] ?? ''),
            'obligor'           => strval($_POST['obligor'] ?? ''),
            'effect_on_billing' => strval($_POST['effect_on_billing'] ?? ''),
            'effective_from'    => strval($_POST['effective_from'] ?? ''),
            'reason'            => strval($_POST['reason'] ?? ''),
        ), $user_id);
    if ($r['ok']) {
        addContractNote($contract_id,
            'ملحق تغيير التزامات #' . intval($r['amendment_id'])
            . ' — ولد بند مصفوفة مسودة #' . intval($r['obligation_id']) . ' ينتظر إجازة المالية',
            $user_id, $conn);
    }
    echo json_encode(['success' => (bool) $r['ok'], 'message' => $r['ok']
        ? ('أنشئ الملحق وولد بند المصفوفة مسودة — بانتظار إجازة المالية ✅')
        : ($r['reason'] . ' (' . intval($r['code']) . ')'),
        'amendment_id' => $r['amendment_id'], 'obligation_id' => $r['obligation_id']]);
}

else {
    die(json_encode(['success' => false, 'message' => 'الإجراء غير معروف']));
}

exit;
?>
