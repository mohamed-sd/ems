<?php
/**
 * send_broadcast.php - إرسال رسالة جماعية للمستخدمين
 * POST: message, recipients (optional - JSON array of user IDs)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'غير مصرح'], JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة'], JSON_UNESCAPED_UNICODE));
}

require_once '../config.php';

$sender_id   = intval($_SESSION['user']['id']);
$company_id  = intval($_SESSION['user']['company_id']);
$message     = isset($_POST['message']) ? trim($_POST['message']) : '';
$recipients_json = isset($_POST['recipients']) ? $_POST['recipients'] : '';

// التحقق من المدخلات
if (empty($message)) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'الرسالة فارغة'], JSON_UNESCAPED_UNICODE));
}
if (mb_strlen($message) > 2000) {
    while (ob_get_level()) ob_end_clean();
    die(json_encode(['success' => false, 'message' => 'الرسالة طويلة جداً (الحد الأقصى 2000 حرف)'], JSON_UNESCAPED_UNICODE));
}

$safe_company  = intval($company_id);
$safe_message  = mysqli_real_escape_string($conn, $message);

// تحديد المستلمين: إما قائمة محددة أو الكل
$recipient_ids = [];
if (!empty($recipients_json)) {
    // قائمة محددة
    $recipients_array = json_decode($recipients_json, true);
    if (is_array($recipients_array) && count($recipients_array) > 0) {
        foreach ($recipients_array as $id) {
            $clean_id = intval($id);
            if ($clean_id > 0 && $clean_id != $sender_id) {
                $recipient_ids[] = $clean_id;
            }
        }
    }
    
    if (empty($recipient_ids)) {
        while (ob_get_level()) ob_end_clean();
        die(json_encode(['success' => false, 'message' => 'لم يتم تحديد مستلمين صالحين'], JSON_UNESCAPED_UNICODE));
    }
    
    // التحقق من أن جميع المستلمين في نفس الشركة
    $ids_list = implode(',', $recipient_ids);
    $verify_query = "SELECT id FROM users 
                     WHERE id IN ($ids_list) 
                     AND company_id = $safe_company 
                     AND is_deleted = 0 
                     AND status = 'active'";
    $verify_result = mysqli_query($conn, $verify_query);
    
    if (!$verify_result) {
        while (ob_get_level()) ob_end_clean();
        die(json_encode(['success' => false, 'message' => 'خطأ في التحقق من المستلمين'], JSON_UNESCAPED_UNICODE));
    }
    
    // إعادة بناء القائمة من النتائج الصحيحة فقط
    $recipient_ids = [];
    while ($row = mysqli_fetch_assoc($verify_result)) {
        $recipient_ids[] = intval($row['id']);
    }
    
    if (empty($recipient_ids)) {
        while (ob_get_level()) ob_end_clean();
        die(json_encode(['success' => false, 'message' => 'لا يوجد مستلمون صالحون'], JSON_UNESCAPED_UNICODE));
    }
} else {
    // إرسال للجميع
    $users_query = "SELECT id FROM users 
                    WHERE company_id = $safe_company 
                    AND id != $sender_id 
                    AND is_deleted = 0 
                    AND status = 'active'";
    $users_result = mysqli_query($conn, $users_query);
    
    if (!$users_result) {
        while (ob_get_level()) ob_end_clean();
        die(json_encode(['success' => false, 'message' => 'خطأ في جلب المستخدمين: ' . mysqli_error($conn)], JSON_UNESCAPED_UNICODE));
    }
    
    while ($user = mysqli_fetch_assoc($users_result)) {
        $recipient_ids[] = intval($user['id']);
    }
    
    if (empty($recipient_ids)) {
        while (ob_get_level()) ob_end_clean();
        die(json_encode(['success' => false, 'message' => 'لا يوجد مستخدمون آخرون في شركتك'], JSON_UNESCAPED_UNICODE));
    }
}

// إرسال الرسالة لكل مستلم
$inserted_count = 0;
$failed_count = 0;

foreach ($recipient_ids as $receiver_id) {
    $safe_receiver = intval($receiver_id);
    
    $sql = "INSERT INTO messages (company_id, sender_id, receiver_id, message, created_at)
            VALUES ($safe_company, $sender_id, $safe_receiver, '$safe_message', NOW())";
    
    if (mysqli_query($conn, $sql)) {
        $inserted_count++;
    } else {
        $failed_count++;
    }
}

// النتيجة
if ($inserted_count > 0) {
    $message_text = $inserted_count === 1 
        ? "تم إرسال الرسالة بنجاح لمستخدم واحد" 
        : "تم إرسال الرسالة بنجاح لـ {$inserted_count} مستخدم";
    
    // إيقاف جميع output buffers قبل إرجاع JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    
    echo json_encode([
        'success'          => true,
        'message'          => $message_text,
        'recipients_count' => $inserted_count,
        'failed_count'     => $failed_count
    ], JSON_UNESCAPED_UNICODE);
} else {
    // إيقاف جميع output buffers قبل إرجاع JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    
    die(json_encode(['success' => false, 'message' => 'فشل في إرسال الرسائل'], JSON_UNESCAPED_UNICODE));
}

exit;
