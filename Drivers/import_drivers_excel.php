<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'غير مصرح']));
}

include '../config.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// التحقق من رفع الملف
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    exit(json_encode(['success' => false, 'message' => 'لم يتم رفع ملف']));
}

$file = $_FILES['file'];

// التحقق من الأخطاء
if ($file['error'] !== UPLOAD_ERR_OK) {
    exit(json_encode(['success' => false, 'message' => 'حدث خطأ أثناء رفع الملف']));
}

// التحقق من نوع الملف
$allowedExtensions = ['xlsx', 'xls', 'csv'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    exit(json_encode(['success' => false, 'message' => 'نوع الملف غير مدعوم. يرجى رفع ملف Excel أو CSV']));
}

// التحقق من حجم الملف (5 ميجا)
if ($file['size'] > 5 * 1024 * 1024) {
    exit(json_encode(['success' => false, 'message' => 'حجم الملف كبير جداً (الحد الأقصى 5 ميجا)']));
}

// معالجة الملف
$errors = [];
$successCount = 0;
$failedCount = 0;

try {
    // قراءة الملف
    if ($fileExtension === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            throw new Exception('فشل فتح ملف CSV');
        }
        
        // تخطي رأس CSV
        $header = fgetcsv($handle);
        
        $rowNumber = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            if ($rowNumber > 1001) {
                $errors[] = ['row' => $rowNumber, 'error' => 'تم تجاوز الحد الأقصى للصفوف (1000 صف)'];
                break;
            }
            
            // تخطي الصفوف الفارغة
            if (empty(array_filter($data))) {
                continue;
            }
            
            $result = processDriverRow($data, $rowNumber, $conn);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
                $errors[] = ['row' => $rowNumber, 'error' => $result['message']];
            }
        }
        
        fclose($handle);
        
    } else {
        // معالجة ملفات Excel
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        // تخطي رأس الصف الأول
        array_shift($rows);
        
        $rowNumber = 1;
        foreach ($rows as $row) {
            $rowNumber++;
            
            if ($rowNumber > 1001) {
                $errors[] = ['row' => $rowNumber, 'error' => 'تم تجاوز الحد الأقصى للصفوف (1000 صف)'];
                break;
            }
            
            // تخطي الصفوف الفارغة
            if (empty(array_filter($row))) {
                continue;
            }
            
            $result = processDriverRow($row, $rowNumber, $conn);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
                $errors[] = ['row' => $rowNumber, 'error' => $result['message']];
            }
        }
    }
    
    // إعداد رسالة النتيجة
    $message = "✅ تم استيراد $successCount مشغل بنجاح";
    if ($failedCount > 0) {
        $message .= " | ❌ فشل استيراد $failedCount صف";
    }
    
    exit(json_encode([
        'success' => true,
        'message' => $message,
        'successCount' => $successCount,
        'failedCount' => $failedCount,
        'errors' => $errors
    ]));
    
} catch (Exception $e) {
    exit(json_encode([
        'success' => false,
        'message' => 'خطأ في معالجة الملف: ' . $e->getMessage()
    ]));
}

/**
 * معالجة صف واحد من البيانات
 */
function processDriverRow($row, $rowNumber, $conn) {
    // تعيين القيم مع التأكد من عدم وجود قيم فارغة
    $name = trim($row[0] ?? '');
    $driver_code = trim($row[1] ?? '');
    $nickname = trim($row[2] ?? '');
    $identity_type = trim($row[3] ?? '');
    $identity_number = trim($row[4] ?? '');
    $identity_expiry_date = trim($row[5] ?? '');
    $license_number = trim($row[6] ?? '');
    $license_type = trim($row[7] ?? '');
    $license_expiry_date = trim($row[8] ?? '');
    $license_issuer = trim($row[9] ?? '');
    $specialized_equipment = trim($row[10] ?? '');
    $years_in_field = trim($row[11] ?? '');
    $years_on_equipment = trim($row[12] ?? '');
    $skill_level = trim($row[13] ?? '');
    $certificates = trim($row[14] ?? '');
    $owner_supervisor = trim($row[15] ?? '');
    $supplier_name_or_id = trim($row[16] ?? '');
    $employment_affiliation = trim($row[17] ?? '');
    $salary_type = trim($row[18] ?? '');
    $monthly_salary = trim($row[19] ?? '');
    $email = trim($row[20] ?? '');
    $phone = trim($row[21] ?? '');
    $phone_alternative = trim($row[22] ?? '');
    $address = trim($row[23] ?? '');
    $performance_rating = trim($row[24] ?? '');
    $behavior_record = trim($row[25] ?? '');
    $accident_record = trim($row[26] ?? '');
    $health_status = trim($row[27] ?? '');
    $health_issues = trim($row[28] ?? '');
    $vaccinations_status = trim($row[29] ?? '');
    $previous_employer = trim($row[30] ?? '');
    $employment_duration = trim($row[31] ?? '');
    $reference_contact = trim($row[32] ?? '');
    $general_notes = trim($row[33] ?? '');
    $driver_status = trim($row[34] ?? '');
    $start_date = trim($row[35] ?? '');
    $status = trim($row[36] ?? '');

    // التحقق من الحقول الإلزامية
    if (empty($name)) {
        return ['success' => false, 'message' => 'اسم المشغل مطلوب'];
    }
    
    if (empty($phone)) {
        return ['success' => false, 'message' => 'رقم الهاتف الأساسي مطلوب'];
    }
    
    if (empty($driver_status)) {
        return ['success' => false, 'message' => 'حالة المشغل مطلوبة'];
    }
    
    if (empty($status) || !in_array($status, ['0', '1'])) {
        return ['success' => false, 'message' => 'حالة النظام يجب أن تكون 0 أو 1'];
    }

    // التحقق من تكرار رقم الهاتف
    $phone_escaped = mysqli_real_escape_string($conn, $phone);
    $check_phone = "SELECT id FROM drivers WHERE phone = '$phone_escaped' LIMIT 1";
    $result = mysqli_query($conn, $check_phone);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return ['success' => false, 'message' => 'رقم الهاتف موجود مسبقاً'];
    }

    // التحقق من وجود المورد إذا تم إدخاله
    $supplier_id = NULL;
    if (!empty($supplier_name_or_id)) {
        // محاولة البحث بالرقم أولاً
        if (is_numeric($supplier_name_or_id)) {
            $supplier_id_check = intval($supplier_name_or_id);
            $supplier_query = "SELECT id FROM suppliers WHERE id = $supplier_id_check LIMIT 1";
        } else {
            // البحث بالاسم
            $supplier_name_escaped = mysqli_real_escape_string($conn, $supplier_name_or_id);
            $supplier_query = "SELECT id FROM suppliers WHERE name LIKE '%$supplier_name_escaped%' LIMIT 1";
        }
        
        $supplier_result = mysqli_query($conn, $supplier_query);
        if ($supplier_result && mysqli_num_rows($supplier_result) > 0) {
            $supplier_row = mysqli_fetch_assoc($supplier_result);
            $supplier_id = $supplier_row['id'];
        }
    }

    // التحقق من صحة التواريخ
    if (!empty($identity_expiry_date) && !validateDate($identity_expiry_date)) {
        return ['success' => false, 'message' => 'تاريخ انتهاء الهوية غير صحيح (استخدم YYYY-MM-DD)'];
    }
    
    if (!empty($license_expiry_date) && !validateDate($license_expiry_date)) {
        return ['success' => false, 'message' => 'تاريخ انتهاء الرخصة غير صحيح (استخدم YYYY-MM-DD)'];
    }
    
    if (!empty($start_date) && !validateDate($start_date)) {
        return ['success' => false, 'message' => 'تاريخ البدء غير صحيح (استخدم YYYY-MM-DD)'];
    }

    // التحقق من القيم الرقمية
    if (!empty($years_in_field) && !is_numeric($years_in_field)) {
        return ['success' => false, 'message' => 'سنوات المجال يجب أن تكون رقماً'];
    }
    
    if (!empty($years_on_equipment) && !is_numeric($years_on_equipment)) {
        return ['success' => false, 'message' => 'سنوات المعدات يجب أن تكون رقماً'];
    }
    
    if (!empty($monthly_salary) && !is_numeric($monthly_salary)) {
        return ['success' => false, 'message' => 'الراتب الشهري يجب أن يكون رقماً'];
    }

    // Escape جميع البيانات
    $name = mysqli_real_escape_string($conn, $name);
    $driver_code = mysqli_real_escape_string($conn, $driver_code);
    $nickname = mysqli_real_escape_string($conn, $nickname);
    $identity_type = mysqli_real_escape_string($conn, $identity_type);
    $identity_number = mysqli_real_escape_string($conn, $identity_number);
    $identity_expiry_date_sql = !empty($identity_expiry_date) ? "'" . mysqli_real_escape_string($conn, $identity_expiry_date) . "'" : "NULL";
    $license_number = mysqli_real_escape_string($conn, $license_number);
    $license_type = mysqli_real_escape_string($conn, $license_type);
    $license_expiry_date_sql = !empty($license_expiry_date) ? "'" . mysqli_real_escape_string($conn, $license_expiry_date) . "'" : "NULL";
    $license_issuer = mysqli_real_escape_string($conn, $license_issuer);
    $specialized_equipment = mysqli_real_escape_string($conn, $specialized_equipment);
    $years_in_field_sql = !empty($years_in_field) ? intval($years_in_field) : "NULL";
    $years_on_equipment_sql = !empty($years_on_equipment) ? intval($years_on_equipment) : "NULL";
    $skill_level = mysqli_real_escape_string($conn, $skill_level);
    $certificates = mysqli_real_escape_string($conn, $certificates);
    $owner_supervisor = mysqli_real_escape_string($conn, $owner_supervisor);
    $supplier_id_sql = $supplier_id !== NULL ? $supplier_id : "NULL";
    $employment_affiliation = mysqli_real_escape_string($conn, $employment_affiliation);
    $salary_type = mysqli_real_escape_string($conn, $salary_type);
    $monthly_salary_sql = !empty($monthly_salary) ? floatval($monthly_salary) : "NULL";
    $email = mysqli_real_escape_string($conn, $email);
    $phone_alternative = mysqli_real_escape_string($conn, $phone_alternative);
    $address = mysqli_real_escape_string($conn, $address);
    $performance_rating = mysqli_real_escape_string($conn, $performance_rating);
    $behavior_record = mysqli_real_escape_string($conn, $behavior_record);
    $accident_record = mysqli_real_escape_string($conn, $accident_record);
    $health_status = mysqli_real_escape_string($conn, $health_status);
    $health_issues = mysqli_real_escape_string($conn, $health_issues);
    $vaccinations_status = mysqli_real_escape_string($conn, $vaccinations_status);
    $previous_employer = mysqli_real_escape_string($conn, $previous_employer);
    $employment_duration = mysqli_real_escape_string($conn, $employment_duration);
    $reference_contact = mysqli_real_escape_string($conn, $reference_contact);
    $general_notes = mysqli_real_escape_string($conn, $general_notes);
    $driver_status = mysqli_real_escape_string($conn, $driver_status);
    $start_date_sql = !empty($start_date) ? "'" . mysqli_real_escape_string($conn, $start_date) . "'" : "NULL";
    $status = mysqli_real_escape_string($conn, $status);

    // الاستعلام لإدخال البيانات
    $insert_query = "INSERT INTO drivers (
        name, driver_code, nickname,
        identity_type, identity_number, identity_expiry_date,
        license_number, license_type, license_expiry_date, license_issuer,
        specialized_equipment,
        years_in_field, years_on_equipment, skill_level, certificates,
        owner_supervisor, supplier_id, employment_affiliation, salary_type, monthly_salary,
        email, phone, phone_alternative, address,
        performance_rating, behavior_record, accident_record,
        health_status, health_issues, vaccinations_status,
        previous_employer, employment_duration, reference_contact, general_notes,
        driver_status, start_date, status
    ) VALUES (
        '$name', '$driver_code', '$nickname',
        '$identity_type', '$identity_number', $identity_expiry_date_sql,
        '$license_number', '$license_type', $license_expiry_date_sql, '$license_issuer',
        '$specialized_equipment',
        $years_in_field_sql, $years_on_equipment_sql, '$skill_level', '$certificates',
        '$owner_supervisor', $supplier_id_sql, '$employment_affiliation', '$salary_type', $monthly_salary_sql,
        '$email', '$phone_escaped', '$phone_alternative', '$address',
        '$performance_rating', '$behavior_record', '$accident_record',
        '$health_status', '$health_issues', '$vaccinations_status',
        '$previous_employer', '$employment_duration', '$reference_contact', '$general_notes',
        '$driver_status', $start_date_sql, '$status'
    )";

    if (mysqli_query($conn, $insert_query)) {
        return ['success' => true];
    } else {
        return ['success' => false, 'message' => 'خطأ في قاعدة البيانات: ' . mysqli_error($conn)];
    }
}

/**
 * التحقق from تنسيق التاريخ
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>
