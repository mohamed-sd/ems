<?php
include '../config.php';
require_login();
require_once '../includes/approval_workflow.php';

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE));
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    die(json_encode(['success' => false, 'message' => 'رمز التحقق غير صالح'], JSON_UNESCAPED_UNICODE));
}

$api_action = isset($_POST['api_action']) ? trim($_POST['api_action']) : '';
$user_id = approval_get_user_id();

if ($api_action === 'create') {
    $entity_type = isset($_POST['entity_type']) ? trim($_POST['entity_type']) : '';
    $entity_id = isset($_POST['entity_id']) ? intval($_POST['entity_id']) : 0;
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $payload_raw = isset($_POST['payload']) ? $_POST['payload'] : '';

    $payload = json_decode($payload_raw, true);
    if (!is_array($payload)) {
        die(json_encode(['success' => false, 'message' => 'payload غير صالح'], JSON_UNESCAPED_UNICODE));
    }

    $result = approval_create_request($entity_type, $entity_id, $action, $payload, $user_id, $conn);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($api_action === 'approve') {
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    // «من أنشأ لا يعتمد» بنيويًّا (E-04 UXP-090 · self.approval=never)
    require_once __DIR__ . '/../includes/self_approval_guard.php';
    $__rq = approval_stmt_execute($conn, "SELECT requested_by FROM approval_requests WHERE id = ?", array($request_id));
    $__reqBy = 0;
    if ($__rq) {                                   // يعيد mysqli_stmt لا نتيجةً
        $__rs = $__rq->get_result();
        if ($__rs && ($__row = $__rs->fetch_assoc())) { $__reqBy = intval($__row['requested_by']); }
        $__rq->close();
    }
    $__sa = ems_no_self_approval($conn, $__reqBy, $user_id, 'طلب اعتماد #' . $request_id,
        intval($_SESSION['user']['company_id'] ?? 0));
    if ($__sa !== null) {
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => $__sa['reason']), JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = approval_approve_request($request_id, $user_id, $conn, $note);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($api_action === 'reject') {
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $result = approval_reject_request($request_id, $user_id, $conn, $reason);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'message' => 'الإجراء غير معروف'], JSON_UNESCAPED_UNICODE);
exit;
