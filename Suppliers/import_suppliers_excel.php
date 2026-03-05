<?php
// إيقاف عرض الأخطاء في المخرجات
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// تنظيف أي مخرجات سابقة
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

// الاتصال بقاعدة البيانات
require_once '../config.php';
require_once '../includes/permissions_helper.php';

enforce_module_permission_json($conn, 'suppliers', 'add', 'لا توجد صلاحية لإضافة الموردين');

// تنظيف أي مخرجات
ob_end_clean();

// تعيين الترويسة
header('Content-Type: application/json; charset=utf-8');

try {
    // التحقق من طريقة الطلب
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير صحيحة');
    }

    // التحقق من وجود الملف
    if (!isset($_FILES['excel_file'])) {
        throw new Exception('لم يتم رفع ملف');
    }

    // التحقق من خطأ الرفع
    $upload_error = $_FILES['excel_file']['error'];
    if ($upload_error !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'حجم الملف أكبر من الحد المسموح به في الإعدادات',
            UPLOAD_ERR_FORM_SIZE => 'حجم الملف أكبر من الحد المسموح',
            UPLOAD_ERR_PARTIAL => 'تم رفع الملف جزئياً فقط',
            UPLOAD_ERR_NO_FILE => 'لم يتم رفع أي ملف',
            UPLOAD_ERR_NO_TMP_DIR => 'المجلد المؤقت غير موجود',
            UPLOAD_ERR_CANT_WRITE => 'فشل حفظ الملف',
            UPLOAD_ERR_EXTENSION => 'امتداد PHP أوقف رفع الملف'
        ];
        $error_msg = isset($error_messages[$upload_error]) ? $error_messages[$upload_error] : 'خطأ غير معروف في رفع الملف';
        throw new Exception($error_msg);
    }

    // التحقق من وجود الملف المؤقت
    if (!isset($_FILES['excel_file']['tmp_name']) || empty($_FILES['excel_file']['tmp_name'])) {
        throw new Exception('لم يتم العثور على الملف المؤقت');
    }

    $file = $_FILES['excel_file']['tmp_name'];
    $file_name = $_FILES['excel_file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // التحقق من وجود الملف
    if (!file_exists($file)) {
        throw new Exception('الملف المرفوع غير موجود');
    }

    // التحقق من امتداد الملف
    if (!in_array($file_ext, ['xlsx', 'xls', 'csv'])) {
        throw new Exception('صيغة الملف غير مدعومة. استخدم .xlsx, .xls أو .csv');
    }

    // التحقق من صلاحيات قراءة الملف
    if (!is_readable($file)) {
        throw new Exception('لا يمكن قراءة الملف المرفوع');
    }

    // التحقق من حجم الملف (الحد الأقصى 5 ميجا)
    $file_size = $_FILES['excel_file']['size'];
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file_size > $max_size) {
        throw new Exception('حجم الملف كبير جداً. الحد الأقصى 5 ميجا');
    }

    if ($file_size === 0) {
        throw new Exception('الملف فارغ');
    }

    $added = 0;
    $skipped = 0;
    $errors = [];

    $rows = [];

    // محاولة قراءة الملف
    if ($file_ext === 'csv') {
        // قراءة CSV
        $handle = @fopen($file, "r");
        if ($handle === FALSE) {
            throw new Exception('فشل فتح ملف CSV');
        }

        // قراءة صف الرأس
        $header = fgetcsv($handle, 10000, ",");
        if ($header === FALSE) {
            fclose($handle);
            throw new Exception('ملف CSV فارغ أو تالف');
        }

        // قراءة البيانات
        $row_count = 0;
        while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
            $rows[] = $data;
            $row_count++;

            // حد أقصى 1000 صف
            if ($row_count > 1000) {
                fclose($handle);
                throw new Exception('الملف يحتوي على أكثر من 1000 صف. قم بتقسيمه إلى ملفات أصغر');
            }
        }
        fclose($handle);

        if (empty($rows)) {
            throw new Exception('الملف لا يحتوي على بيانات');
        }

    } else {
        // محاولة استخدام PhpSpreadsheet لملفات Excel
        if (file_exists('../vendor/autoload.php')) {
            require_once '../vendor/autoload.php';
            
            // استخدام الاسم الكامل بدلاً من use
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // إزالة صف الرأس
            array_shift($rows);
            
        } else {
            throw new Exception('مكتبة PhpSpreadsheet غير مثبتة. الرجاء تحويل الملف إلى CSV أولاً، أو تثبيت المكتبة باستخدام: composer require phpoffice/phpspreadsheet');
        }
    }

    // معالجة البيانات
    foreach ($rows as $index => $row) {
        $row_num = $index + 2; // +2 لأننا بدأنا من الصف 2 (بعد الهيدر)

        // تخطي الصفوف الفارغة
        if (empty($row[0]) && empty($row[1])) {
            continue;
        }

        // استخراج البيانات (17 عمود كما في النموذج)
        $supplier_code = trim($row[0] ?? '');
        $name = trim($row[1] ?? '');
        $supplier_type = trim($row[2] ?? '');
        $dealing_nature = trim($row[3] ?? '');
        $equipment_types = trim($row[4] ?? '');
        $commercial_registration = trim($row[5] ?? '');
        $identity_type = trim($row[6] ?? '');
        $identity_number = trim($row[7] ?? '');
        $identity_expiry_date = trim($row[8] ?? '');
        $email = trim($row[9] ?? '');
        $phone = trim($row[10] ?? '');
        $phone_alternative = trim($row[11] ?? '');
        $full_address = trim($row[12] ?? '');
        $contact_person_name = trim($row[13] ?? '');
        $contact_person_phone = trim($row[14] ?? '');
        $financial_registration_status = trim($row[15] ?? '');
        $status = trim($row[16] ?? '1');

        // التحقق من الحقول المطلوبة
        if (empty($supplier_code)) {
            $errors[] = "الصف $row_num: كود المورد مطلوب";
            $skipped++;
            continue;
        }

        if (empty($name)) {
            $errors[] = "الصف $row_num: اسم المورد مطلوب";
            $skipped++;
            continue;
        }

        if (empty($phone)) {
            $errors[] = "الصف $row_num: رقم الهاتف مطلوب";
            $skipped++;
            continue;
        }

        // تنظيف البيانات
        $supplier_code = mysqli_real_escape_string($conn, $supplier_code);
        $name = mysqli_real_escape_string($conn, $name);
        $supplier_type = mysqli_real_escape_string($conn, $supplier_type);
        $dealing_nature = mysqli_real_escape_string($conn, $dealing_nature);
        $equipment_types = mysqli_real_escape_string($conn, $equipment_types);
        $commercial_registration = mysqli_real_escape_string($conn, $commercial_registration);
        $identity_type = mysqli_real_escape_string($conn, $identity_type);
        $identity_number = mysqli_real_escape_string($conn, $identity_number);
        $identity_expiry_date = !empty($identity_expiry_date) ? mysqli_real_escape_string($conn, $identity_expiry_date) : null;
        $email = mysqli_real_escape_string($conn, $email);
        $phone = mysqli_real_escape_string($conn, $phone);
        $phone_alternative = mysqli_real_escape_string($conn, $phone_alternative);
        $full_address = mysqli_real_escape_string($conn, $full_address);
        $contact_person_name = mysqli_real_escape_string($conn, $contact_person_name);
        $contact_person_phone = mysqli_real_escape_string($conn, $contact_person_phone);
        $financial_registration_status = mysqli_real_escape_string($conn, $financial_registration_status);
        $status = mysqli_real_escape_string($conn, $status);

        // التحقق من عدم تكرار كود المورد
        $check_query = "SELECT id FROM suppliers WHERE supplier_code = '$supplier_code'";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = "الصف $row_num: كود المورد '$supplier_code' موجود مسبقاً";
            $skipped++;
            continue;
        }

        // إدراج المورد
        $identity_expiry_sql = $identity_expiry_date ? "'$identity_expiry_date'" : "NULL";
        
        $insert_query = "INSERT INTO suppliers 
            (supplier_code, name, supplier_type, dealing_nature, equipment_types, 
             commercial_registration, identity_type, identity_number, identity_expiry_date,
             email, phone, phone_alternative, full_address, contact_person_name, 
             contact_person_phone, financial_registration_status, status) 
            VALUES 
            ('$supplier_code', '$name', '$supplier_type', '$dealing_nature', '$equipment_types',
             '$commercial_registration', '$identity_type', '$identity_number', $identity_expiry_sql,
             '$email', '$phone', '$phone_alternative', '$full_address', '$contact_person_name',
             '$contact_person_phone', '$financial_registration_status', '$status')";

        if (mysqli_query($conn, $insert_query)) {
            $added++;
        } else {
            $errors[] = "الصف $row_num: خطأ في إضافة المورد - " . mysqli_error($conn);
            $skipped++;
        }
    }

    // إرسال النتيجة
    echo json_encode([
        'success' => true,
        'message' => 'تم الاستيراد بنجاح',
        'added' => $added,
        'skipped' => $skipped,
        'errors' => $errors
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // معالجة الأخطاء
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
?>
