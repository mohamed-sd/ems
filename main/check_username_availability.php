<?php
header('Content-Type: application/json; charset=utf-8');

// تضمين ملف الاتصال بقاعدة البيانات
require_once '../config.php';

// التحقق من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['available' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

// الحصول على اسم المستخدم من الطلب
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

// التحقق من أن اسم المستخدم غير فارغ
if (empty($username)) {
    die(json_encode(['available' => false, 'message' => 'يرجى إدخال اسم المستخدم']));
}

// التحقق من طول اسم المستخدم
if (strlen($username) < 3) {
    die(json_encode(['available' => false, 'message' => 'اسم المستخدم يجب أن يكون 3 أحرف على الأقل']));
}

// الاستعلام عن وجود اسم المستخدم
$username_escaped = mysqli_real_escape_string($conn, $username);

if ($uid > 0) {
    // في حالة التعديل، نتجاهل السجل الحالي
    $query = "SELECT id FROM users WHERE username = '$username_escaped' AND id != $uid LIMIT 1";
} else {
    // في حالة الإضافة
    $query = "SELECT id FROM users WHERE username = '$username_escaped' LIMIT 1";
}

$result = mysqli_query($conn, $query);

if (!$result) {
    die(json_encode(['available' => false, 'message' => 'حدث خطأ: ' . mysqli_error($conn)]));
}

if (mysqli_num_rows($result) > 0) {
    // اسم المستخدم موجود بالفعل
    die(json_encode([
        'available' => false,
        'message' => '❌ اسم المستخدم موجود مسبقاً يرجى اختيار اسم آخر',
        'taken' => true
    ]));
} else {
    // اسم المستخدم متوفر
    die(json_encode([
        'available' => true,
        'message' => '✅ اسم المستخدم متوفر',
        'taken' => false
    ]));
}
?>
