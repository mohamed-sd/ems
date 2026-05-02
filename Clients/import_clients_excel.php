<?php
/**
 * استيراد العملاء من ملف Excel أو CSV
 * يدعم الترميز العربي بالكامل مع PhpSpreadsheet
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// تنظيف أي مخرجات سابقة
while (ob_get_level()) {
    ob_end_clean();
}

session_start();

header('Content-Type: application/json; charset=utf-8');

// دالة للخروج بخطأ
function import_fail($msg) {
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    import_fail('غير مصرح - يرجى تسجيل الدخول');
}

// التحقق من معرف الشركة
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    import_fail('الحساب غير مرتبط بشركة');
}

$created_by = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

// الاتصال بقاعدة البيانات
require_once '../config.php';
require_once '../includes/permissions_helper.php';

// التحقق من صلاحية الإضافة
$module_result = $conn->query(
    "SELECT id FROM modules 
     WHERE code = 'Clients/clients.php' OR code = 'clients'
        OR code LIKE '%clients.php%' OR name LIKE '%عملاء%'
     LIMIT 1"
);
$module_info = $module_result ? $module_result->fetch_assoc() : null;
$module_id   = $module_info ? $module_info['id'] : null;

$can_add = false;
if ($module_id) {
    $perms   = get_module_permissions($conn, $module_id);
    $can_add = $perms['can_add'];
}

if (!$can_add) {
    import_fail('لا توجد صلاحية لاستيراد العملاء');
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_fail('طريقة الطلب غير صحيحة');
}

// التحقق من وجود الملف
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE  => 'حجم الملف يتجاوز الحد المسموح به في إعدادات الخادم',
        UPLOAD_ERR_FORM_SIZE => 'حجم الملف يتجاوز الحد المسموح',
        UPLOAD_ERR_PARTIAL   => 'تم رفع الملف جزئياً فقط',
        UPLOAD_ERR_NO_FILE   => 'لم يتم اختيار ملف',
        UPLOAD_ERR_NO_TMP_DIR => 'مجلد الملفات المؤقتة غير موجود',
        UPLOAD_ERR_CANT_WRITE => 'فشل حفظ الملف المؤقت',
    ];
    $err_code = isset($_FILES['excel_file']) ? $_FILES['excel_file']['error'] : UPLOAD_ERR_NO_FILE;
    import_fail($upload_errors[$err_code] ?? 'خطأ غير معروف في رفع الملف');
}

$tmp_file  = $_FILES['excel_file']['tmp_name'];
$orig_name = $_FILES['excel_file']['name'];
$file_ext  = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

if (!file_exists($tmp_file) || !is_readable($tmp_file)) {
    import_fail('لا يمكن قراءة الملف المرفوع');
}

if ($_FILES['excel_file']['size'] === 0) {
    import_fail('الملف فارغ');
}

if ($_FILES['excel_file']['size'] > 5 * 1024 * 1024) {
    import_fail('حجم الملف يتجاوز الحد الأقصى (5 ميجابايت)');
}

if (!in_array($file_ext, ['xlsx', 'xls', 'csv'])) {
    import_fail('صيغة الملف غير مدعومة. الصيغ المقبولة: xlsx, xls, csv');
}

// ════════════════════════════════════════════
// قراءة بيانات الملف
// ════════════════════════════════════════════
$rows = [];

try {
    if ($file_ext === 'csv') {
        // ── قراءة CSV مع دعم كامل لـ UTF-8 والعربية ──
        $csv_content = file_get_contents($tmp_file);

        // إزالة BOM (UTF-8) إن وُجد
        if (substr($csv_content, 0, 3) === "\xEF\xBB\xBF") {
            $csv_content = substr($csv_content, 3);
        }

        // تحويل الترميز إذا لم يكن UTF-8 (في حال توفر mbstring)
        if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding')) {
            if (!mb_check_encoding($csv_content, 'UTF-8')) {
                $csv_content = mb_convert_encoding($csv_content, 'UTF-8', 'Windows-1256');
            }
        }

        // تحليل CSV
        $lines = explode("\n", str_replace("\r\n", "\n", str_replace("\r", "\n", $csv_content)));
        $first_line = true;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // تحليل السطر مع دعم القيم المحاطة بعلامات اقتباس
            $parsed = str_getcsv($line, ',', '"');

            if ($first_line) {
                // تخطي صف الرأس
                $first_line = false;
                continue;
            }
            $rows[] = $parsed;

            if (count($rows) > 1000) {
                import_fail('الملف يحتوي على أكثر من 1000 صف. يرجى تقسيمه إلى ملفات أصغر');
            }
        }
    } else {
        // ── قراءة XLSX / XLS مع دعم العربية ──
        if (!class_exists('ZipArchive')) {
            import_fail('لا يمكن قراءة ملفات Excel حالياً لأن إضافة ZIP غير مفعلة على الخادم. استخدم CSV الآن أو فعّل php_zip ثم أعد المحاولة.');
        }

        if (!file_exists('../vendor/autoload.php')) {
            import_fail('مكتبة PhpSpreadsheet غير متوفرة. استخدم CSV الآن أو ثبّت الاعتمادات عبر Composer.');
        }

        require_once '../vendor/autoload.php';

        $reader      = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmp_file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tmp_file);
        $worksheet   = $spreadsheet->getActiveSheet();

        $all_rows = $worksheet->toArray(null, true, true, false);

        if (empty($all_rows)) {
            import_fail('الملف لا يحتوي على بيانات');
        }

        // إزالة صف الرأس (الصف الأول)
        array_shift($all_rows);
        $rows = $all_rows;
    }
} catch (\Exception $e) {
    import_fail('فشل قراءة الملف: ' . $e->getMessage());
}

if (empty($rows)) {
    import_fail('الملف لا يحتوي على بيانات بعد صف الرأس');
}

// ════════════════════════════════════════════
// معالجة الصفوف وإدراجها في قاعدة البيانات
// ════════════════════════════════════════════
$added   = 0;
$skipped = 0;
$errors  = [];

// القيم المقبولة للحالة
$valid_statuses = ['نشط', 'متوقف'];

foreach ($rows as $index => $row) {
    $row_num = $index + 2; // رقم الصف الحقيقي في الملف (الهيدر = 1)

    // تخطي الصفوف الفارغة تماماً
    $row_clean = array_filter(array_map('trim', $row), function($v){ return $v !== '' && $v !== null; });
    if (empty($row_clean)) {
        continue;
    }

    // استخراج وتنظيف الحقول حسب ترتيب الأعمدة في النموذج
    $client_code     = trim($row[0] ?? '');
    $client_name     = trim($row[1] ?? '');
    $entity_type     = trim($row[2] ?? '');
    $sector_category = trim($row[3] ?? '');
    $phone           = trim($row[4] ?? '');
    $email           = trim($row[5] ?? '');
    $whatsapp        = trim($row[6] ?? '');
    $status_raw      = trim($row[7] ?? '');

    // إزالة BOM من أول حقل (قد يكون في CSV)
    $client_code = ltrim($client_code, "\xEF\xBB\xBF");

    // ── التحقق من الحقول المطلوبة ──
    if ($client_code === '') {
        $errors[] = "الصف {$row_num}: كود العميل مطلوب ولا يمكن أن يكون فارغاً";
        $skipped++;
        continue;
    }

    if ($client_name === '') {
        $errors[] = "الصف {$row_num}: اسم العميل مطلوب ولا يمكن أن يكون فارغاً";
        $skipped++;
        continue;
    }

    // تحقق البريد الإلكتروني إن وُجد
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "الصف {$row_num}: صيغة البريد الإلكتروني غير صحيحة ({$email})";
        $skipped++;
        continue;
    }

    // تحديد الحالة: افتراضي "نشط" إذا كانت فارغة أو غير معروفة
    $status = in_array($status_raw, $valid_statuses) ? $status_raw : 'نشط';

    // ── فحص التكرار بنطاق الشركة ──
    $esc_code    = mysqli_real_escape_string($conn, $client_code);
    $check_query = "SELECT id FROM clients 
                    WHERE client_code = '{$esc_code}' 
                      AND company_id = {$company_id} 
                      AND is_deleted = 0 
                    LIMIT 1";
    $check_res   = mysqli_query($conn, $check_query);

    if ($check_res && mysqli_num_rows($check_res) > 0) {
        $errors[] = "الصف {$row_num}: كود العميل «{$client_code}» موجود مسبقاً - تم التخطي";
        $skipped++;
        continue;
    }

    // ── إدراج العميل ──
    $esc_name     = mysqli_real_escape_string($conn, $client_name);
    $esc_entity   = mysqli_real_escape_string($conn, $entity_type);
    $esc_sector   = mysqli_real_escape_string($conn, $sector_category);
    $esc_phone    = mysqli_real_escape_string($conn, $phone);
    $esc_email    = mysqli_real_escape_string($conn, $email);
    $esc_whatsapp = mysqli_real_escape_string($conn, $whatsapp);
    $esc_status   = mysqli_real_escape_string($conn, $status);

    $insert_sql = "INSERT INTO clients 
        (company_id, client_code, client_name, entity_type, sector_category,
         phone, email, whatsapp, status, created_by, is_deleted)
        VALUES 
        ({$company_id}, '{$esc_code}', '{$esc_name}', '{$esc_entity}', '{$esc_sector}',
         '{$esc_phone}', '{$esc_email}', '{$esc_whatsapp}', '{$esc_status}', {$created_by}, 0)";

    if (mysqli_query($conn, $insert_sql)) {
        $added++;
    } else {
        $errors[] = "الصف {$row_num}: فشل إضافة العميل «{$client_name}» - " . mysqli_error($conn);
        $skipped++;
    }
}

// ════════════════════════════════════════════
// إرسال النتيجة
// ════════════════════════════════════════════
echo json_encode([
    'success' => true,
    'message' => "تم الاستيراد: {$added} عميل تم إضافتهم" . ($skipped > 0 ? "، {$skipped} تم تخطيهم" : ''),
    'added'   => $added,
    'skipped' => $skipped,
    'errors'  => $errors,
], JSON_UNESCAPED_UNICODE);
exit;

