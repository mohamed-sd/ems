<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// إعدادات RTL
$spreadsheet->getActiveSheet()->setRightToLeft(true);

// تعيين عرض الأعمدة
$columnWidths = [
    'A' => 20,  // اسم المشغل
    'B' => 15,  // الكود
    'C' => 15,  // الكنية
    'D' => 20,  // نوع الهوية
    'E' => 20,  // رقم الهوية
    'F' => 15,  // تاريخ انتهاء الهوية
    'G' => 20,  // رقم الرخصة
    'H' => 25,  // نوع الرخصة
    'I' => 15,  // تاريخ انتهاء الرخصة
    'J' => 25,  // جهة إصدار الرخصة
    'K' => 30,  // المعدات المتخصصة
    'L' => 15,  // سنوات المجال
    'M' => 15,  // سنوات المعدات
    'N' => 25,  // مستوى الكفاءة
    'O' => 30,  // الشهادات
    'P' => 25,  // المالك/المشرف
    'Q' => 20,  // اسم المورد (أو رقمه)
    'R' => 20,  // تبعية المشغل
    'S' => 15,  // نوع الراتب
    'T' => 15,  // الراتب الشهري
    'U' => 25,  // البريد الإلكتروني
    'V' => 15,  // رقم الهاتف الأساسي
    'W' => 15,  // رقم الهاتف البديل
    'X' => 30,  // العنوان
    'Y' => 20,  // تقييم الأداء
    'Z' => 20,  // سجل السلوك
    'AA' => 20, // سجل الحوادث
    'AB' => 20, // الحالة الصحية
    'AC' => 30, // المشاكل الصحية
    'AD' => 20, // التطعيمات
    'AE' => 25, // جهة التوظيف السابقة
    'AF' => 15, // مدة العمل
    'AG' => 30, // مرجع الاتصال
    'AH' => 40, // ملاحظات عامة
    'AI' => 15, // حالة المشغل
    'AJ' => 15, // تاريخ البدء
    'AK' => 10  // حالة النظام
];

foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// رأس الجدول (صف 1)
$headers = [
    'A1' => 'اسم المشغل/السائق *',
    'B1' => 'الكود الفريد',
    'C1' => 'اسم الشهرة/الكنية',
    'D1' => 'نوع الهوية',
    'E1' => 'رقم الهوية',
    'F1' => 'تاريخ انتهاء الهوية',
    'G1' => 'رقم رخصة القيادة',
    'H1' => 'نوع رخصة القيادة',
    'I1' => 'تاريخ انتهاء الرخصة',
    'J1' => 'جهة إصدار الرخصة',
    'K1' => 'المعدات المتخصصة (مفصولة بفواصل)',
    'L1' => 'سنوات المجال',
    'M1' => 'سنوات المعدات',
    'N1' => 'مستوى الكفاءة',
    'O1' => 'الشهادات والتدريبات',
    'P1' => 'المالك/المشرف المباشر',
    'Q1' => 'اسم المورد أو رقمه',
    'R1' => 'تبعية المشغل',
    'S1' => 'نوع الراتب',
    'T1' => 'الراتب الشهري',
    'U1' => 'البريد الإلكتروني',
    'V1' => 'رقم الهاتف الأساسي *',
    'W1' => 'رقم الهاتف البديل',
    'X1' => 'العنوان',
    'Y1' => 'تقييم الأداء',
    'Z1' => 'سجل السلوك',
    'AA1' => 'سجل الحوادث',
    'AB1' => 'الحالة الصحية',
    'AC1' => 'المشاكل الصحية',
    'AD1' => 'التطعيمات',
    'AE1' => 'جهة التوظيف السابقة',
    'AF1' => 'مدة العمل',
    'AG1' => 'مرجع الاتصال',
    'AH1' => 'ملاحظات عامة',
    'AI1' => 'حالة المشغل *',
    'AJ1' => 'تاريخ البدء (YYYY-MM-DD)',
    'AK1' => 'حالة النظام (1=مفعل، 0=موقف) *'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// تنسيق رأس الجدول
$headerRange = 'A1:AK1';
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 11,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
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
]);

$sheet->getRowDimension(1)->setRowHeight(40);

// صف مثال (صف 2)
$exampleData = [
    'محمد أحمد علي',
    'OPR-001-2026',
    'أبو محمد',
    'بطاقة هوية وطنية',
    '123456789123',
    '2028-12-31',
    'DL-2024-456789',
    'فئة د (شاحنات ثقيلة)',
    '2027-06-30',
    'إدارة المرور - الخرطوم',
    'حفارة (Excavator), شاحنة قلابة (Dump Truck)',
    '8',
    '5',
    'خبير (5-10 سنوات)',
    'شهادة تشغيل حفارات من معهد التعدين',
    'محمد علي',
    'شركة المعدات الذهبية (أو رقم المورد)',
    'تابع لمالك المعدة مباشرة',
    'شهري',
    '3500',
    'mohammed@example.com',
    '+249-9-123-4567',
    '+249-9-765-4321',
    'شارع النيل، الخرطوم',
    'ممتاز',
    'ممتاز (لا توجد شكاوى)',
    'نظيف (لا توجد حوادث)',
    'سليم تماماً',
    '',
    'محدثة',
    'شركة الذهب للتعدين',
    '3 سنوات',
    'محمود أحمد - مدير الأسطول (09-123-4567)',
    'مشغل موثوق وذو كفاءة عالية',
    'نشط',
    '2024-01-15',
    '1'
];

$row = 2;
$col = 'A';
foreach ($exampleData as $data) {
    $sheet->setCellValue($col . $row, $data);
    $col++;
}

// تنسيق صف المثال
$sheet->getStyle('A2:AK2')->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E7E6E6']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_RIGHT,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ]
]);

// إضافة صفحة التعليمات
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('التعليمات');
$instructionsSheet->setRightToLeft(true);

$instructionsSheet->setCellValue('A1', 'تعليمات استخدام نموذج استيراد المشغلين');
$instructionsSheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E78']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$instructionsSheet->mergeCells('A1:D1');

$instructions = [
    ['📌 إرشادات عامة:', ''],
    ['', '1. احتفظ بصف الرأس (الصف الأول) كما هو ولا تقم بتعديله'],
    ['', '2. الصف الثاني يحتوي على مثال - يمكن حذفه أو تعديله'],
    ['', '3. أضف بيانات المشغلين الجدد بدءاً من الصف الثالث'],
    ['', '4. الحقول المميزة بعلامة (*) إلزامية'],
    ['', '5. احفظ الملف بصيغة .xlsx أو .xls أو قم بتحويله إلى .csv'],
    ['', ''],
    ['⚠️ تنبيهات:', ''],
    ['', '• التواريخ يجب أن تكون بصيغة YYYY-MM-DD (مثال: 2026-12-31)'],
    ['', '• حالة النظام: 1 = مفعل، 0 = موقف'],
    ['', '• المعدات المتخصصة: يمكن كتابة أكثر من معدة مفصولة بفواصل'],
    ['', '• أرقام الهواتف يجب أن تكون فريدة (لا تتكرر)'],
    ['', '• المورد: يمكن كتابة اسم المورد أو رقم معرفه من جدول الموردين'],
    ['', ''],
    ['📋 القيم المتاحة للحقول:', ''],
    ['', ''],
    ['نوع الهوية:', 'بطاقة هوية وطنية، جواز سفر، بطاقة لاجئ، رخصة قيادة، بطاقة أخرى'],
    ['نوع الرخصة:', 'فئة أ (دراجات)، فئة ب (سيارات)، فئة ج (شاحنات خفيفة)، فئة د (شاحنات ثقيلة)، فئة هـ (حافلات)، متعددة الفئات، غير محدد'],
    ['المعدات المتخصصة:', 'حفارة (Excavator)، مثقاب/مكنة تخريم، دوزر، شاحنة قلابة، شاحنة تناكر، جرافة، ممهدة، معدات أخرى'],
    ['مستوى الكفاءة:', 'مبتدئ (أقل من سنة)، متدرب (1-2 سنة)، كفء (3-5 سنوات)، خبير (5-10 سنوات)، سيد حرفة (أكثر من 10 سنوات)'],
    ['تبعية المشغل:', 'تابع لمالك المعدة مباشرة، تابع للمورد/الوسيط، تابع لشركة متخصصة في التشغيل، مقاول مستقل'],
    ['نوع الراتب:', 'يومي، أسبوعي، شهري، حسب الإنتاجية، حسب المشروع'],
    ['تقييم الأداء:', 'ممتاز، جيد جداً، جيد، مقبول، ضعيف، غير محدد'],
    ['سجل السلوك:', 'ممتاز (لا توجد شكاوى)، جيد (شكاوى نادرة)، مقبول (بعض الشكاوى)، ضعيف (شكاوى متكررة)، غير محدد'],
    ['سجل الحوادث:', 'نظيف (لا توجد حوادث)، حادث واحد (طفيف)، حادثان (متوسط)، ثلاثة حوادث فأكثر (خطير)، غير محدد'],
    ['الحالة الصحية:', 'سليم تماماً، بحالة جيدة، بحالة مقبولة، محتاج متابعة طبية، غير محدد'],
    ['التطعيمات:', 'محدثة، قديمة، لا يوجد فحص، قيد الفحص'],
    ['حالة المشغل:', 'نشط، معلق، مفصول، في إجازة، تحت التقييم']
];

$row = 3;
foreach ($instructions as $instruction) {
    $instructionsSheet->setCellValue('A' . $row, $instruction[0]);
    $instructionsSheet->setCellValue('B' . $row, $instruction[1]);
    $instructionsSheet->getRowDimension($row)->setRowHeight(20);
    $row++;
}

$instructionsSheet->getColumnDimension('A')->setWidth(25);
$instructionsSheet->getColumnDimension('B')->setWidth(80);

$instructionsSheet->getStyle('A3:A' . ($row-1))->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => '0070C0']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]
]);

// العودة للصفحة الأولى
$spreadsheet->setActiveSheetIndex(0);

// تحميل الملف
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="نموذج_استيراد_المشغلين_شامل_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
