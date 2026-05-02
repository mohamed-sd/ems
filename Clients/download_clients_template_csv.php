<?php
/**
 * تحميل نموذج CSV لاستيراد العملاء
 * مع دعم كامل لترميز UTF-8 والعربية
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

// تنظيف أي مخرجات سابقة
while (ob_get_level()) {
    ob_end_clean();
}

$ascii_name = 'clients_import_template_' . date('Y-m-d') . '.csv';
$utf8_name  = 'نموذج_استيراد_العملاء_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $ascii_name . '"; filename*=UTF-8\'\'' . rawurlencode($utf8_name));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// فتح مجرى الإخراج
$output = fopen('php://output', 'w');

// BOM لضمان ظهور العربية بشكل صحيح في Excel
fputs($output, "\xEF\xBB\xBF");

// صف الرأس - بنفس ترتيب حقول جدول clients
fputcsv($output, [
    'كود العميل',        // A - client_code  (مطلوب)
    'اسم العميل',        // B - client_name  (مطلوب)
    'نوع الكيان',        // C - entity_type
    'تصنيف القطاع',      // D - sector_category
    'رقم الهاتف',        // E - phone
    'البريد الإلكتروني', // F - email
    'واتساب',            // G - whatsapp
    'الحالة',            // H - status (نشط / متوقف)
]);

// صفوف أمثلة
$samples = [
    ['CLT-0001', 'شركة النيل للمقاولات',          'حكومي', 'بنية تحتية',    '+249123456789', 'nile@example.com',     '+249123456789', 'نشط'],
    ['CLT-0002', 'مؤسسة الخرطوم التجارية',        'خاص',   'خدمات',         '+249987654321', 'khartoum@example.com', '+249987654321', 'نشط'],
    ['CLT-0003', 'الهيئة العامة للطرق والجسور',   'حكومي', 'نقل ومواصلات',  '+249111222333', 'roads@gov.sd',         '',             'نشط'],
];

foreach ($samples as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();


