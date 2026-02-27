<?php
/**
 * معالج استيراد المعدات من Excel/CSV
 * يقوم بقراءة ملف Excel أو CSV ومعالجة البيانات وإدخالها في قاعدة البيانات
 */

require_once '../config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

header('Content-Type: application/json; charset=utf-8');

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير صحيحة'
    ]));
}

// التحقق من وجود ملف
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    die(json_encode([
        'success' => false,
        'message' => 'لم يتم رفع الملف بشكل صحيح. الرجاء المحاولة مرة أخرى.'
    ]));
}

$file = $_FILES['excel_file'];
$fileTmpPath = $file['tmp_name'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// التحقق من صيغة الملف
$allowedExtensions = ['xlsx', 'xls', 'csv'];
if (!in_array($fileExtension, $allowedExtensions)) {
    die(json_encode([
        'success' => false,
        'message' => 'صيغة الملف غير مدعومة. يرجى رفع ملف Excel (.xlsx, .xls) أو CSV.'
    ]));
}

// التحقق من حجم الملف (أقصى حد 5 ميجا)
if ($fileSize > 5 * 1024 * 1024) {
    die(json_encode([
        'success' => false,
        'message' => 'حجم الملف كبير جداً. الحد الأقصى 5 ميجا بايت.'
    ]));
}

try {
    // قراءة الملف
    if ($fileExtension === 'csv') {
        $reader = new Csv();
        $reader->setInputEncoding('UTF-8');
        $reader->setDelimiter(',');
        $reader->setEnclosure('"');
        $reader->setSheetIndex(0);
        $spreadsheet = $reader->load($fileTmpPath);
    } else {
        $spreadsheet = IOFactory::load($fileTmpPath);
    }

    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // التحقق من وجود بيانات
    if (count($rows) < 2) {
        die(json_encode([
            'success' => false,
            'message' => 'الملف فارغ أو لا يحتوي على بيانات. يجب أن يحتوي على صف رأس وصف واحد على الأقل من البيانات.'
        ]));
    }

    // إزالة صف الرأس
    $header = array_shift($rows);

    // متغيرات لتتبع النتائج
    $added = 0;
    $skipped = 0;
    $errors = [];

    // معالجة كل صف
    foreach ($rows as $rowIndex => $row) {
        $rowNumber = $rowIndex + 2; // +2 لأن الصف الأول هو الرأس والصفوف تبدأ من 1

        // تخطي الصفوف الفارغة
        if (empty(array_filter($row))) {
            continue;
        }

        // استخراج البيانات من الصف (32 عمود)
        $code = isset($row[0]) ? mysqli_real_escape_string($conn, trim($row[0])) : '';
        $supplier_name = isset($row[1]) ? mysqli_real_escape_string($conn, trim($row[1])) : '';
        $type_name = isset($row[2]) ? mysqli_real_escape_string($conn, trim($row[2])) : '';
        $name = isset($row[3]) ? mysqli_real_escape_string($conn, trim($row[3])) : '';
        $serial_number = isset($row[4]) ? mysqli_real_escape_string($conn, trim($row[4])) : '';
        $chassis_number = isset($row[5]) ? mysqli_real_escape_string($conn, trim($row[5])) : '';
        $manufacturer = isset($row[6]) ? mysqli_real_escape_string($conn, trim($row[6])) : '';
        $model = isset($row[7]) ? mysqli_real_escape_string($conn, trim($row[7])) : '';
        $manufacturing_year = isset($row[8]) && is_numeric($row[8]) ? intval($row[8]) : 'NULL';
        $import_year = isset($row[9]) && is_numeric($row[9]) ? intval($row[9]) : 'NULL';
        $equipment_condition = isset($row[10]) ? mysqli_real_escape_string($conn, trim($row[10])) : 'في حالة جيدة';
        $operating_hours = isset($row[11]) && is_numeric($row[11]) ? intval($row[11]) : 0;
        $engine_condition = isset($row[12]) ? mysqli_real_escape_string($conn, trim($row[12])) : 'جيدة';
        $tires_condition = isset($row[13]) ? mysqli_real_escape_string($conn, trim($row[13])) : 'N/A';
        $actual_owner_name = isset($row[14]) ? mysqli_real_escape_string($conn, trim($row[14])) : '';
        $owner_type = isset($row[15]) ? mysqli_real_escape_string($conn, trim($row[15])) : '';
        $owner_phone = isset($row[16]) ? mysqli_real_escape_string($conn, trim($row[16])) : '';
        $owner_supplier_relation = isset($row[17]) ? mysqli_real_escape_string($conn, trim($row[17])) : '';
        $license_number = isset($row[18]) ? mysqli_real_escape_string($conn, trim($row[18])) : '';
        $license_authority = isset($row[19]) ? mysqli_real_escape_string($conn, trim($row[19])) : '';
        $license_expiry_date = isset($row[20]) && !empty(trim($row[20])) ? "'" . mysqli_real_escape_string($conn, trim($row[20])) . "'" : 'NULL';
        $inspection_certificate_number = isset($row[21]) ? mysqli_real_escape_string($conn, trim($row[21])) : '';
        $last_inspection_date = isset($row[22]) && !empty(trim($row[22])) ? "'" . mysqli_real_escape_string($conn, trim($row[22])) . "'" : 'NULL';
        $current_location = isset($row[23]) ? mysqli_real_escape_string($conn, trim($row[23])) : '';
        $availability_status = isset($row[24]) ? mysqli_real_escape_string($conn, trim($row[24])) : 'متاحة للعمل';
        $estimated_value = isset($row[25]) && is_numeric($row[25]) ? floatval($row[25]) : 0;
        $daily_rental_price = isset($row[26]) && is_numeric($row[26]) ? floatval($row[26]) : 0;
        $monthly_rental_price = isset($row[27]) && is_numeric($row[27]) ? floatval($row[27]) : 0;
        $insurance_status = isset($row[28]) ? mysqli_real_escape_string($conn, trim($row[28])) : '';
        $last_maintenance_date = isset($row[29]) && !empty(trim($row[29])) ? "'" . mysqli_real_escape_string($conn, trim($row[29])) . "'" : 'NULL';
        $general_notes = isset($row[30]) ? mysqli_real_escape_string($conn, trim($row[30])) : '';
        $status = isset($row[31]) && ($row[31] == '0' || $row[31] == '1') ? intval($row[31]) : 1;

        // التحقق من الحقول المطلوبة
        if (empty($code)) {
            $errors[] = "الصف $rowNumber: كود المعدة مطلوب";
            $skipped++;
            continue;
        }

        if (empty($supplier_name)) {
            $errors[] = "الصف $rowNumber: اسم المورد مطلوب";
            $skipped++;
            continue;
        }

        if (empty($type_name)) {
            $errors[] = "الصف $rowNumber: نوع المعدة مطلوب";
            $skipped++;
            continue;
        }

        if (empty($name)) {
            $errors[] = "الصف $rowNumber: اسم المعدة مطلوب";
            $skipped++;
            continue;
        }

        // البحث عن ID المورد
        $supplier_query = "SELECT id FROM suppliers WHERE name = '$supplier_name' AND status = 1 LIMIT 1";
        $supplier_result = mysqli_query($conn, $supplier_query);
        
        if (!$supplier_result || mysqli_num_rows($supplier_result) == 0) {
            $errors[] = "الصف $rowNumber: المورد '$supplier_name' غير موجود في النظام";
            $skipped++;
            continue;
        }
        
        $supplier_row = mysqli_fetch_assoc($supplier_result);
        $supplier_id = intval($supplier_row['id']);

        // البحث عن ID نوع المعدة
        $type_query = "SELECT id FROM equipments_types WHERE type = '$type_name' AND status = 1 LIMIT 1";
        $type_result = mysqli_query($conn, $type_query);
        
        if (!$type_result || mysqli_num_rows($type_result) == 0) {
            $errors[] = "الصف $rowNumber: نوع المعدة '$type_name' غير موجود في النظام";
            $skipped++;
            continue;
        }
        
        $type_row = mysqli_fetch_assoc($type_result);
        $type_id = intval($type_row['id']);

        // التحقق من عدم تكرار كود المعدة
        $check_query = "SELECT id FROM equipments WHERE code = '$code' LIMIT 1";
        $check_result = mysqli_query($conn, $check_query);
        
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $errors[] = "الصف $rowNumber: كود المعدة '$code' موجود مسبقاً";
            $skipped++;
            continue;
        }

        // إدخال البيانات
        $insert_query = "INSERT INTO equipments 
            (suppliers, code, type, name, status, 
             serial_number, chassis_number, manufacturer, model, 
             manufacturing_year, import_year, equipment_condition, 
             operating_hours, engine_condition, tires_condition,
             actual_owner_name, owner_type, owner_phone, owner_supplier_relation,
             license_number, license_authority, license_expiry_date,
             inspection_certificate_number, last_inspection_date,
             current_location, availability_status,
             estimated_value, daily_rental_price, monthly_rental_price, 
             insurance_status, general_notes, last_maintenance_date) 
            VALUES 
            ($supplier_id, '$code', $type_id, '$name', $status,
             '$serial_number', '$chassis_number', '$manufacturer', '$model',
             $manufacturing_year, $import_year, '$equipment_condition',
             $operating_hours, '$engine_condition', '$tires_condition',
             '$actual_owner_name', '$owner_type', '$owner_phone', '$owner_supplier_relation',
             '$license_number', '$license_authority', $license_expiry_date,
             '$inspection_certificate_number', $last_inspection_date,
             '$current_location', '$availability_status',
             $estimated_value, $daily_rental_price, $monthly_rental_price,
             '$insurance_status', '$general_notes', $last_maintenance_date)";

        if (mysqli_query($conn, $insert_query)) {
            $added++;
        } else {
            $errors[] = "الصف $rowNumber: خطأ في إضافة المعدة - " . mysqli_error($conn);
            $skipped++;
        }
    }

    // إعداد الرسالة النهائية
    if ($added > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'تم استيراد المعدات بنجاح',
            'added' => $added,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'لم يتم إضافة أي معدة. يرجى التحقق من البيانات والمحاولة مرة أخرى.',
            'added' => 0,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء معالجة الملف: ' . $e->getMessage()
    ]);
}

exit;
