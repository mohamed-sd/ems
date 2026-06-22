<?php
// بدء output buffering من البداية لمنع أي output غير متوقع
ob_start();

session_start();

if (!isset($_SESSION['user'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'غير مصرح'], JSON_UNESCAPED_UNICODE));
}

include '../config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['success' => false, 'message' => 'معرف المشغل مفقود'], JSON_UNESCAPED_UNICODE));
}

$driver_id = intval($_GET['id']);

// التحقق من صلاحيات الشركة وعزل البيانات
$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;

// فحص وجود عمود company_id
$drivers_has_company = false;
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'company_id'");
if ($check_column && mysqli_num_rows($check_column) > 0) {
    $drivers_has_company = true;
}

// بناء شرط WHERE مع عزل البيانات
$where_clause = "id = $driver_id";
if (!$is_super_admin) {
    if ($drivers_has_company) {
        $where_clause .= " AND company_id = $company_id";
    } else {
        // إذا لم يكن هناك company_id في الجدول، استخدم الطريقة البديلة
        $where_clause .= " AND EXISTS (
            SELECT 1
            FROM drivercontracts dsc
            INNER JOIN project sp ON sp.id = dsc.project_id
            INNER JOIN users su ON su.id = sp.created_by
            WHERE dsc.driver_id = employees.id
              AND su.company_id = $company_id
        )";
    }
}

$query = "SELECT * FROM employees WHERE $where_clause LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result) {
    // خطأ في الاستعلام
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات: ' . mysqli_error($conn)
    ], JSON_UNESCAPED_UNICODE));
}

if (mysqli_num_rows($result) > 0) {
    $driver = mysqli_fetch_assoc($result);

    // تنظيف output buffer وطباعة JSON فقط
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => true,
        'driver' => $driver
    ], JSON_UNESCAPED_UNICODE));
} else {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'المشغل غير موجود أو ليس لديك صلاحية الوصول إليه'
    ], JSON_UNESCAPED_UNICODE));
}
