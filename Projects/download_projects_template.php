<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../vendor/autoload.php';
include '../config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

// إنشاء ملف Excel جديد
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// تعيين اتجاه RTL
$sheet->setRightToLeft(true);

// تعيين عنوان الملف
$sheet->setTitle('المشاريع');

// تحديد عرض الأعمدة
$widths = ['A' => 20, 'B' => 25, 'C' => 20, 'D' => 30, 'E' => 20, 'F' => 20, 'G' => 20, 'H' => 20, 'I' => 20, 'J' => 20, 'K' => 25, 'L' => 20];
foreach ($widths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// تنسيق صف الرأس
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1a1a2e']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// إضافة رؤوس الأعمدة
$headers = [
    'A1' => 'اسم المشروع *',
    'B1' => 'اسم العميل',
    'C1' => 'كود المشروع',
    'D1' => 'الفئة',
    'E1' => 'القطاع الفرعي',
    'F1' => 'الولاية',
    'G1' => 'المنطقة',
    'H1' => 'أقرب سوق',
    'I1' => 'خط العرض',
    'J1' => 'خط الطول',
    'K1' => 'موقع المشروع',
    'L1' => 'الحالة *'
];

foreach ($headers as $cell => $header) {
    $sheet->setCellValue($cell, $header);
}

// تطبيق تنسيق الرأس
$sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(35);

// إضافة صفوف مثال مع البيانات
$exampleData = [
    ['مشروع الطريق السريع', 'وزارة البنية التحتية', 'PRJ-2026-001', 'طرق وجسور', 'الطرق السريعة', 'الخرطوم', 'الخرطوم بحري', 'سوق ليبيا', '15.5527', '32.5599', 'الخرطوم - بورتسودان', 'نشط'],
    ['مشروع محطة المياه', 'هيئة المياه', 'PRJ-2026-002', 'مياه', 'محطات المياه', 'النيل الأزرق', 'الدمازين', 'سوق الدمازين', '11.7891', '34.3592', 'الدمازين', 'نشط'],
];

$row = 2;
foreach ($exampleData as $data) {
    $col = 'A';
    foreach ($data as $value) {
        $sheet->setCellValue($col . $row, $value);
        $col++;
    }
    $row++;
}

// تنسيق صفوف البيانات
$dataStyle = [
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'cccccc']
        ]
    ]
];

$sheet->getStyle('A2:L3')->applyFromArray($dataStyle);
$sheet->getRowDimension('2')->setRowHeight(25);
$sheet->getRowDimension('3')->setRowHeight(25);

// إضافة ملاحظات وإرشادات
$sheet->setCellValue('A5', 'ملاحظات مهمة:');
$sheet->getStyle('A5')->getFont()->setBold(true)->setSize(12);

$instructions = [
    'A6' => '* الحقول المشار إليها بـ (*) إلزامية',
    'A7' => '• كود المشروع: يجب أن يكون فريداً لكل مشروع',
    'A8' => '• الحالة: يجب أن تكون "نشط" أو "غير نشط"',
    'A9' => '• الإحداثيات: خط العرض والطول (latitude/longitude)',
    'A10' => '• لا تغير رؤوس الأعمدة',
];

foreach ($instructions as $cell => $instruction) {
    $sheet->setCellValue($cell, $instruction);
    $sheet->getStyle($cell)->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF666666'));
}

// تعيين اسم الملف والتنزيل
$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="نموذج_المشاريع_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
?>
