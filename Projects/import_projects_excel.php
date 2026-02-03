<?php
use PhpOffice\PhpSpreadsheet\IOFactory;

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

session_start();

if (!isset($_SESSION['user'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

require_once '../config.php';

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('طريقة الطلب غير صحيحة');
    }

    if (!isset($_FILES['file'])) {
        throw new Exception('لم يتم رفع ملف');
    }

    $upload_error = $_FILES['file']['error'];
    if ($upload_error !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'حجم الملف أكبر من الحد المسموح به في الإعدادات',
            UPLOAD_ERR_FORM_SIZE => 'حجم الملف أكبر من الحد المسموح',
            UPLOAD_ERR_PARTIAL => 'تم رفع الملف جزئياً فقط',
            UPLOAD_ERR_NO_FILE => 'لم يتم رفع أي ملف',
        ];
        throw new Exception($error_messages[$upload_error] ?? 'حدث خطأ في الرفع');
    }

    $file_type = mime_content_type($_FILES['file']['tmp_name']);
    $allowed_types = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('نوع الملف غير مدعوم. يرجى رفع ملف Excel');
    }

    require_once '../vendor/autoload.php';

    $reader = IOFactory::createReaderForFile($_FILES['file']['tmp_name']);
    $spreadsheet = $reader->load($_FILES['file']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();

    $rows = $sheet->toArray();
    
    if (count($rows) < 2) {
        throw new Exception('الملف فارغ أو لا يحتوي على بيانات صحيحة');
    }

    $header_row = $rows[0];
    $required_columns = ['اسم المشروع', 'الحالة'];
    
    foreach ($required_columns as $required) {
        if (!in_array($required, $header_row)) {
            throw new Exception("العمود المطلوب '$required' غير موجود في الملف");
        }
    }

    $imported_count = 0;
    $error_count = 0;
    $error_messages_list = [];

    $created_by = $_SESSION['user']['id'];
    $created_at = date('Y-m-d H:i:s');

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        
        if (empty(array_filter($row))) {
            continue;
        }

        try {
            $name = isset($row[0]) ? trim($row[0]) : '';
            $client = isset($row[1]) ? trim($row[1]) : '';
            $project_code = isset($row[2]) ? trim($row[2]) : '';
            $category = isset($row[3]) ? trim($row[3]) : '';
            $sub_sector = isset($row[4]) ? trim($row[4]) : '';
            $state = isset($row[5]) ? trim($row[5]) : '';
            $region = isset($row[6]) ? trim($row[6]) : '';
            $nearest_market = isset($row[7]) ? trim($row[7]) : '';
            $latitude = isset($row[8]) ? trim($row[8]) : '';
            $longitude = isset($row[9]) ? trim($row[9]) : '';
            $location = isset($row[10]) ? trim($row[10]) : '';
            $status = isset($row[11]) ? trim($row[11]) : 'نشط';

            if (empty($name)) {
                throw new Exception('اسم المشروع مطلوب في الصف ' . ($i + 1));
            }

            // تحويل حالة النص إلى قيمة رقمية
            $status_value = ($status === 'نشط' || $status === '1') ? 1 : 0;

            $name_escaped = mysqli_real_escape_string($conn, $name);
            $client_escaped = mysqli_real_escape_string($conn, $client);
            $project_code_escaped = mysqli_real_escape_string($conn, $project_code);
            $category_escaped = mysqli_real_escape_string($conn, $category);
            $sub_sector_escaped = mysqli_real_escape_string($conn, $sub_sector);
            $state_escaped = mysqli_real_escape_string($conn, $state);
            $region_escaped = mysqli_real_escape_string($conn, $region);
            $nearest_market_escaped = mysqli_real_escape_string($conn, $nearest_market);
            $latitude_escaped = mysqli_real_escape_string($conn, $latitude);
            $longitude_escaped = mysqli_real_escape_string($conn, $longitude);
            $location_escaped = mysqli_real_escape_string($conn, $location);

            $query = "INSERT INTO operationproject 
                (name, client, project_code, category, sub_sector, state, region, nearest_market, latitude, longitude, location, status, created_by, updated_at) 
                VALUES 
                ('$name_escaped', '$client_escaped', '$project_code_escaped', '$category_escaped', '$sub_sector_escaped', '$state_escaped', '$region_escaped', '$nearest_market_escaped', '$latitude_escaped', '$longitude_escaped', '$location_escaped', $status_value, $created_by, '$created_at')";

            if (mysqli_query($conn, $query)) {
                $imported_count++;
            } else {
                throw new Exception('خطأ في قاعدة البيانات: ' . mysqli_error($conn));
            }
        } catch (Exception $e) {
            $error_count++;
            $error_messages_list[] = 'الصف ' . ($i + 1) . ': ' . $e->getMessage();
        }
    }

    $message = "تم استيراد $imported_count مشروع بنجاح";
    if ($error_count > 0) {
        $message .= " وفشل استيراد $error_count مشروع. الأخطاء: " . implode("; ", array_slice($error_messages_list, 0, 5));
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'imported_count' => $imported_count,
        'error_count' => $error_count,
        'errors' => $error_messages_list
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ: ' . $e->getMessage()
    ]);
    exit;
}
?>
