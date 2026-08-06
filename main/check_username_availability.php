<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();

// تضمين ملف الاتصال بقاعدة البيانات
require_once '../config.php';
// حارس المعالج (إغلاق فئة B — مسح دَين الحارس): يرث صلاحية شاشته الأم
require_once __DIR__ . '/../includes/handler_guard.php';
ems_guard_handler($conn, 'main/users.php', 'add');


// تأكد أن الاستجابة JSON خالصة بدون أي مخرجات إضافية من الـ bootstrap
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

// التحقق من أن الطلب هو POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['available' => false, 'message' => 'طريقة الطلب غير صحيحة']));
}

// الحصول على اسم المستخدم من الطلب
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;

$current_company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$users_not_deleted_sql = 'COALESCE(is_deleted,0)=0'; // العمود قائم بالترحيلات — سقط فحص db_table_has_column

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
    // في حالة التعديل، نتجاهل السجل الحالي - التحقق عالمي عبر جميع الشركات
    $query = "SELECT id FROM users WHERE username = '$username_escaped' AND id != $uid AND $users_not_deleted_sql";
} else {
    // في حالة الإضافة - التحقق عالمي عبر جميع الشركات
    $query = "SELECT id FROM users WHERE username = '$username_escaped' AND $users_not_deleted_sql";
}

// لا نضيف فلتر company_id - الفحص عالمي لمنع تكرار أسماء المستخدمين عبر كل الشركات

$query .= " LIMIT 1";

// [مُستثنى موثَّق — قراءة تفرُّدٍ عالمية] اسم الدخول هوية منصّةٍ عابرة للشركات بدلالة
// الأصل الصريحة أعلاه؛ بوابة العزل تعزل users بالشركة وforAllTenants للسوبر حصرًا،
// فيبقى هذا الاستعلام الوحيد خامًا بانتظار قناة قراءةٍ عالمية في دفعة المزوّد (admin/).
$result = mysqli_query($conn, $query);

if (!$result) {
    die(json_encode(['available' => false, 'message' => 'حدث خطأ: ' . mysqli_error($conn)]));
}

if (mysqli_num_rows($result) > 0) {
    // اسم المستخدم محجوز
    die(json_encode([
        'available' => false,
        'message' => 'اسم المستخدم محجوز',
        'taken' => true
    ]));
} else {
    // اسم المستخدم متاح
    die(json_encode([
        'available' => true,
        'message' => 'اسم المستخدم متاح',
        'taken' => false
    ]));
}
?>
