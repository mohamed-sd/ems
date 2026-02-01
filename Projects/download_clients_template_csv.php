<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// إعداد الترويسة لتحميل ملف CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="نموذج_العملاء.csv"');

// فتح مخرج الملف
$output = fopen('php://output', 'w');

// إضافة BOM لدعم UTF-8 في Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// إضافة صف الرأس
$headers = [
    'كود العميل',
    'اسم العميل',
    'نوع الكيان',
    'القطاع/التصنيف',
    'الهاتف',
    'البريد الإلكتروني',
    'واتساب',
    'الحالة'
];

fputcsv($output, $headers);

// إضافة بيانات نموذجية (3 صفوف كمثال)
$sample_data = [
    ['C001', 'شركة النيل للمقاولات', 'شركة', 'المقاولات العامة', '01012345678', 'info@nile-contractors.com', '01012345678', 'نشط'],
    ['C002', 'مؤسسة الأهرامات للتطوير', 'مؤسسة', 'التطوير العقاري', '01098765432', 'contact@ahram-dev.com', '01098765432', 'نشط'],
    ['C003', 'الشركة المصرية للبناء', 'شركة', 'البناء والتشييد', '01156789012', 'info@egy-construction.com', '01156789012', 'نشط']
];

foreach ($sample_data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
