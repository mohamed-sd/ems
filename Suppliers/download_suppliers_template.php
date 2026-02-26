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

// إنشاء ملف Excel جديد
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// تعيين اتجاه RTL
$sheet->setRightToLeft(true);
$sheet->setTitle('نموذج الموردين');

// تحديد عرض الأعمدة
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(35);
$sheet->getColumnDimension('F')->setWidth(20);
$sheet->getColumnDimension('G')->setWidth(20);
$sheet->getColumnDimension('H')->setWidth(20);
$sheet->getColumnDimension('I')->setWidth(25);
$sheet->getColumnDimension('J')->setWidth(20);
$sheet->getColumnDimension('K')->setWidth(20);
$sheet->getColumnDimension('L')->setWidth(20);
$sheet->getColumnDimension('M')->setWidth(30);
$sheet->getColumnDimension('N')->setWidth(25);
$sheet->getColumnDimension('O')->setWidth(20);
$sheet->getColumnDimension('P')->setWidth(25);
$sheet->getColumnDimension('Q')->setWidth(15);

// تنسيق رؤوس المجموعات (الصف الأول)
$groupHeaderStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4f46e5']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_MEDIUM,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// تنسيق رؤوس الأعمدة (الصف الثاني)
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 10
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '667eea']
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

// إضافة رؤوس المجموعات (الصف 1)
$sheet->mergeCells('A1:E1');
$sheet->setCellValue('A1', '📋 المعلومات الأساسية');
$sheet->getStyle('A1:E1')->applyFromArray($groupHeaderStyle);

$sheet->mergeCells('F1:I1');
$sheet->setCellValue('F1', '⚖️ البيانات القانونية');
$sheet->getStyle('F1:I1')->applyFromArray($groupHeaderStyle);

$sheet->mergeCells('J1:M1');
$sheet->setCellValue('J1', '📞 بيانات التواصل');
$sheet->getStyle('J1:M1')->applyFromArray($groupHeaderStyle);

$sheet->mergeCells('N1:O1');
$sheet->setCellValue('N1', '👤 جهة الاتصال');
$sheet->getStyle('N1:O1')->applyFromArray($groupHeaderStyle);

$sheet->mergeCells('P1:Q1');
$sheet->setCellValue('P1', 'ℹ️ معلومات إضافية');
$sheet->getStyle('P1:Q1')->applyFromArray($groupHeaderStyle);

$sheet->getRowDimension('1')->setRowHeight(30);

// إضافة رؤوس الأعمدة التفصيلية (الصف 2)
$headers = [
    'A2' => 'كود المورد *',
    'B2' => 'اسم المورد *',
    'C2' => 'نوع المورد *',
    'D2' => 'طبيعة التعامل',
    'E2' => 'أنواع المعدات',
    'F2' => 'السجل التجاري',
    'G2' => 'نوع الهوية',
    'H2' => 'رقم الهوية',
    'I2' => 'تاريخ انتهاء الهوية',
    'J2' => 'البريد الإلكتروني',
    'K2' => 'رقم الهاتف *',
    'L2' => 'هاتف بديل',
    'M2' => 'العنوان الكامل',
    'N2' => 'اسم جهة الاتصال',
    'O2' => 'هاتف جهة الاتصال',
    'P2' => 'حالة التسجيل المالي',
    'Q2' => 'الحالة *'
];

foreach ($headers as $cell => $header) {
    $sheet->setCellValue($cell, $header);
}

// تطبيق تنسيق رؤوس الأعمدة
$sheet->getStyle('A2:Q2')->applyFromArray($headerStyle);
$sheet->getRowDimension('2')->setRowHeight(35);

// إضافة صفوف مثال
$exampleData = [
    [
        'SUP-001', 
        'شركة النيل للمعدات الثقيلة', 
        'شركة محلية', 
        'مباشر',
        'حفار, قلاب, لودر',
        'CR-123456',
        'بطاقة شخصية',
        '123456789',
        '2027-12-31',
        'info@nile-equip.com',
        '+249123456789',
        '+249987654321',
        'الخرطوم - شارع النيل',
        'أحمد محمد',
        '+249123456789',
        'مسجل',
        '1'
    ],
    [
        'SUP-002', 
        'مؤسسة المستقبل للآليات', 
        'مؤسسة فردية', 
        'وسيط',
        'قلاب, رافعة',
        'CR-789012',
        'جواز سفر',
        'P987654321',
        '2028-06-30',
        'contact@mustaqbal.sd',
        '+249111222333',
        '+249444555666',
        'أم درمان - الموردة',
        'محمد أحمد',
        '+249111222333',
        'غير مسجل',
        '1'
    ],
    [
        'SUP-003', 
        'شركة الجزيرة للنقل', 
        'شركة مساهمة', 
        'مباشر',
        'قلاب, ناقلة',
        'CR-345678',
        'بطاقة شخصية',
        '987654321',
        '2026-12-31',
        'info@gezira-trans.com',
        '+249777888999',
        '+249666555444',
        'الجزيرة - مدني',
        'علي حسن',
        '+249777888999',
        'مسجل',
        '1'
    ]
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

$sheet->getStyle('A2:Q' . ($row - 1))->applyFromArray($dataStyle);

// إضافة ورقة تعليمات
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('التعليمات');
$instructionsSheet->setRightToLeft(true);
$instructionsSheet->getColumnDimension('A')->setWidth(85);

$instructions = [
    ['تعليمات استيراد الموردين'],
    [''],
    ['الحقول المطلوبة (يجب ملؤها):'],
    ['1. كود المورد: رمز فريد لكل مورد (مثال: SUP-001)'],
    ['2. اسم المورد: الاسم الكامل للمورد'],
    ['3. نوع المورد: شركة محلية، مؤسسة فردية، شركة مساهمة، شركة أجنبية، وكيل تجاري، مقاول، أخرى'],
    ['4. رقم الهاتف: رقم الاتصال الرئيسي'],
    ['5. الحالة: 1 (نشط) أو 0 (معلق)'],
    [''],
    ['الحقول الاختيارية:'],
    ['- طبيعة التعامل: مباشر، وسيط، وكيل، أخرى'],
    ['- أنواع المعدات: حفار، قلاب، لودر، جريدر، رافعة، أسفلت، كسارة، خلاطة، مولد، ضاغط هواء، ناقلة مياه، صهريج وقود، ناقلة عمال، سيارة خدمة، أخرى (افصل بفاصلة)'],
    ['- السجل التجاري: رقم السجل التجاري'],
    ['- نوع الهوية: بطاقة شخصية، جواز سفر، رخصة تجارية، أخرى'],
    ['- رقم الهوية: رقم الهوية أو الوثيقة'],
    ['- تاريخ انتهاء الهوية: بصيغة YYYY-MM-DD (مثال: 2027-12-31)'],
    ['- البريد الإلكتروني: عنوان البريد الإلكتروني'],
    ['- هاتف بديل: رقم هاتف إضافي'],
    ['- العنوان الكامل: العنوان التفصيلي'],
    ['- اسم جهة الاتصال: اسم الشخص المسؤول'],
    ['- هاتف جهة الاتصال: رقم هاتف الشخص المسؤول'],
    ['- حالة التسجيل المالي: مسجل، غير مسجل، معلق، أخرى'],
    [''],
    ['ملاحظات مهمة:'],
    ['✓ كود المورد يجب أن يكون فريداً ولا يتكرر'],
    ['✓ استخدم ورقة "نموذج الموردين" لإضافة البيانات'],
    ['✓ احذف الصفوف المثالية أو استبدلها ببياناتك الفعلية'],
    ['✓ تأكد من ملء جميع الحقول المطلوبة المشار إليها بـ *'],
    ['✓ عند إدخال أنواع معدات متعددة، افصلها بفاصلة (مثال: حفار, قلاب, لودر)'],
    ['✓ الصيغة المدعومة: .xlsx أو .xls أو .csv'],
    ['✓ الحد الأقصى: 1000 صف في الملف الواحد'],
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
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '4f46e5']]
]);
$instructionsSheet->getStyle('A8')->applyFromArray([
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '764ba2']]
]);
$instructionsSheet->getStyle('A15')->applyFromArray([
    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '059669']]
]);
$instructionsSheet->getStyle('A17')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '2563eb']]
]);
$instructionsSheet->getStyle('A20')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '7c3aed']]
]);
$instructionsSheet->getStyle('A23')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'ea580c']]
]);
$instructionsSheet->getStyle('A26')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'dc2626']]
]);
$instructionsSheet->getStyle('A29')->applyFromArray([
    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '0891b2']]
]);
$instructionsSheet->getStyle('A32')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'fc4a1a']]
]);

// العودة إلى الورقة الأولى
$spreadsheet->setActiveSheetIndex(0);

// تحديد اسم الملف
$filename = 'نموذج_استيراد_الموردين_' . date('Y-m-d') . '.xlsx';

// تعيين الهيدرات لتحميل الملف
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// إنشاء الملف وإرساله للمتصفح
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
