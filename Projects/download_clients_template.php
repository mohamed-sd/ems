<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../vendor/autoload.php';

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
$sheet->setTitle('نموذج العملاء');

// تحديد عرض الأعمدة
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(25);
$sheet->getColumnDimension('E')->setWidth(20);
$sheet->getColumnDimension('F')->setWidth(30);
$sheet->getColumnDimension('G')->setWidth(20);
$sheet->getColumnDimension('H')->setWidth(15);

// تنسيق صف الرأس
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '667eea']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
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
    'A1' => 'كود العميل *',
    'B1' => 'اسم العميل *',
    'C1' => 'نوع الكيان',
    'D1' => 'تصنيف القطاع',
    'E1' => 'رقم الهاتف',
    'F1' => 'البريد الإلكتروني',
    'G1' => 'واتساب',
    'H1' => 'الحالة *'
];

foreach ($headers as $cell => $header) {
    $sheet->setCellValue($cell, $header);
}

// تطبيق تنسيق الرأس
$sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(30);

// إضافة صفوف مثال مع البيانات
$exampleData = [
    ['CL-001', 'شركة المستقبل للمقاولات', 'شركة', 'الإنشاءات', '+249123456789', 'info@future-co.com', '+249123456789', 'نشط'],
    ['CL-002', 'مؤسسة النهضة التجارية', 'مؤسسة', 'الخدمات', '+249987654321', 'contact@nahda.com', '+249987654321', 'نشط'],
    ['CL-003', 'الهيئة العامة للطرق', 'جهة حكومية', 'البنية التحتية', '+249111222333', 'roads@gov.sd', '+249111222333', 'نشط']
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
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ]
];

$sheet->getStyle('A2:H' . ($row - 1))->applyFromArray($dataStyle);

// إضافة ورقة تعليمات
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('التعليمات');
$instructionsSheet->setRightToLeft(true);

$instructionsSheet->getColumnDimension('A')->setWidth(80);

$instructions = [
    ['تعليمات استيراد العملاء'],
    [''],
    ['الحقول المطلوبة (يجب ملؤها):'],
    ['1. كود العميل: رمز فريد لكل عميل (مثال: CL-001)'],
    ['2. اسم العميل: الاسم الكامل للعميل'],
    ['3. الحالة: نشط أو متوقف'],
    [''],
    ['الحقول الاختيارية:'],
    ['- نوع الكيان: شركة، مؤسسة، جهة حكومية، فرد، أخرى'],
    ['- تصنيف القطاع: النفط والغاز، البنية التحتية، الطرق والجسور، الإنشاءات، التعدين، الزراعة، الخدمات، أخرى'],
    ['- رقم الهاتف: رقم الاتصال'],
    ['- البريد الإلكتروني: عنوان البريد الإلكتروني'],
    ['- واتساب: رقم الواتساب'],
    [''],
    ['ملاحظات مهمة:'],
    ['✓ كود العميل يجب أن يكون فريداً ولا يتكرر'],
    ['✓ استخدم ورقة "نموذج العملاء" لإضافة البيانات'],
    ['✓ احذف الصفوف المثالية قبل الاستيراد أو استبدلها ببياناتك'],
    ['✓ تأكد من ملء جميع الحقول المطلوبة المشار إليها بـ *'],
    ['✓ الصيغة المدعومة: .xlsx أو .xls'],
];

$instructionRow = 1;
foreach ($instructions as $instruction) {
    $instructionsSheet->setCellValue('A' . $instructionRow, $instruction[0]);
    $instructionRow++;
}

// تنسيق العنوان الرئيسي
$instructionsSheet->getStyle('A1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['rgb' => '667eea']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER
    ]
]);

// تنسيق العناوين الفرعية
$instructionsSheet->getStyle('A3')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '764ba2']]
]);
$instructionsSheet->getStyle('A8')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '764ba2']]
]);
$instructionsSheet->getStyle('A15')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'fc4a1a']]
]);

// العودة إلى الورقة الأولى
$spreadsheet->setActiveSheetIndex(0);

// تحديد اسم الملف
$filename = 'نموذج_استيراد_العملاء_' . date('Y-m-d') . '.xlsx';

// تعيين الهيدرات لتحميل الملف
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// إنشاء الملف وإرساله للمتصفح
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
