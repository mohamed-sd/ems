<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// إعداد الترويسة لتحميل ملف CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="نموذج_العملاء_' . date('Y-m-d') . '.csv"');

// فتح مخرج الملف
$output = fopen('php://output', 'w');

// إضافة BOM لدعم UTF-8 في Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// إضافة صف الرأس
$headers = [
    'كود العميل',
    'اسم العميل',
    'نوع الكيان',
    'تصنيف القطاع',
    'الهاتف',
    'البريد الإلكتروني',
    'واتساب',
    'الحالة'
];

fputcsv($output, $headers);

// إضافة بيانات نموذجية (3 صفوف كمثال)
$sample_data = [
    ['CL-001', 'شركة المستقبل للمقاولات', 'حكومي', 'بنية تحتية', '+249123456789', 'info@future-co.com', '+249123456789', 'نشط'],
    ['CL-002', 'مؤسسة النهضة التجارية', 'خاص', 'خدمات', '+249987654321', 'contact@nahda.com', '+249987654321', 'نشط'],
    ['CL-003', 'الهيئة العامة للطرق', 'حكومي', 'بنية تحتية', '+249111222333', 'roads@gov.sd', '+249111222333', 'نشط']
];

foreach ($sample_data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
