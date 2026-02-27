<?php
/**
 * تحميل نموذج Excel لاستيراد المعدات
 * يقوم بإنشاء ملف Excel يحتوي على:
 * 1. ورقة بيانات المعدات مع الأعمدة المطلوبة
 * 2. ورقة التعليمات والأمثلة
 * 3. أمثلة لكيفية ملء البيانات
 */

require_once '../config.php';
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
$sheet->setTitle('قالب المعدات');
$sheet->setRightToLeft(true);

// تحديد الأعمدة
$columns = [
    'A' => ['title' => 'كود المعدة *', 'width' => 15, 'example' => 'EQ-001'],
    'B' => ['title' => 'اسم المورد *', 'width' => 20, 'example' => 'شركة المعدات الثقيلة'],
    'C' => ['title' => 'نوع المعدة *', 'width' => 18, 'example' => 'حفار'],
    'D' => ['title' => 'اسم المعدة *', 'width' => 20, 'example' => 'حفار كاتربيلر 320'],
    'E' => ['title' => 'رقم المعدة/التسلسلي', 'width' => 20, 'example' => 'EXC-2024-001'],
    'F' => ['title' => 'رقم الهيكل', 'width' => 20, 'example' => 'CAT320-ABC123456'],
    'G' => ['title' => 'الماركة/الشركة المصنعة', 'width' => 20, 'example' => 'كاتربيلر'],
    'H' => ['title' => 'الموديل/الطراز', 'width' => 15, 'example' => '320D'],
    'I' => ['title' => 'سنة الصنع', 'width' => 12, 'example' => '2018'],
    'J' => ['title' => 'سنة الاستيراد', 'width' => 12, 'example' => '2020'],
    'K' => ['title' => 'حالة المعدة', 'width' => 20, 'example' => 'في حالة جيدة'],
    'L' => ['title' => 'ساعات التشغيل', 'width' => 15, 'example' => '5400'],
    'M' => ['title' => 'حالة المحرك', 'width' => 15, 'example' => 'جيدة'],
    'N' => ['title' => 'حالة الإطارات', 'width' => 15, 'example' => 'N/A'],
    'O' => ['title' => 'اسم المالك الفعلي', 'width' => 20, 'example' => 'محمد علي أحمد'],
    'P' => ['title' => 'نوع المالك', 'width' => 18, 'example' => 'مالك فردي'],
    'Q' => ['title' => 'رقم هاتف المالك', 'width' => 18, 'example' => '+249-912345678'],
    'R' => ['title' => 'علاقة المالك بالمورد', 'width' => 25, 'example' => 'تابع للمورد (مملوكة للمورد نفسه)'],
    'S' => ['title' => 'رقم الترخيص', 'width' => 18, 'example' => 'VEH-2024-12345'],
    'T' => ['title' => 'جهة الترخيص', 'width' => 18, 'example' => 'المرور'],
    'U' => ['title' => 'تاريخ انتهاء الترخيص', 'width' => 18, 'example' => '2025-12-31'],
    'V' => ['title' => 'رقم شهادة الفحص', 'width' => 18, 'example' => 'INS-2024-001'],
    'W' => ['title' => 'تاريخ آخر فحص', 'width' => 15, 'example' => '2024-06-15'],
    'X' => ['title' => 'الموقع الحالي', 'width' => 20, 'example' => 'منجم الذهب الشرقي'],
    'Y' => ['title' => 'حالة التوفر', 'width' => 18, 'example' => 'متاحة للعمل'],
    'Z' => ['title' => 'القيمة المقدرة (دولار)', 'width' => 18, 'example' => '150000'],
    'AA' => ['title' => 'سعر التأجير اليومي (دولار)', 'width' => 20, 'example' => '500'],
    'AB' => ['title' => 'سعر التأجير الشهري (دولار)', 'width' => 20, 'example' => '10000'],
    'AC' => ['title' => 'التأمين/الضمان', 'width' => 18, 'example' => 'مؤمن بالكامل'],
    'AD' => ['title' => 'تاريخ آخر صيانة', 'width' => 15, 'example' => '2024-05-10'],
    'AE' => ['title' => 'ملاحظات عامة', 'width' => 30, 'example' => 'معدة موثوقة، تحتاج صيانة دورية'],
    'AF' => ['title' => 'الحالة (1=نشط, 0=غير نشط)', 'width' => 20, 'example' => '1']
];

// تنسيق صف الرأس
$headerRow = 1;
foreach ($columns as $col => $data) {
    $cell = $col . $headerRow;
    $sheet->setCellValue($cell, $data['title']);
    $sheet->getColumnDimension($col)->setWidth($data['width']);
}

// تنسيق الرأس (لون ذهبي)
$sheet->getStyle('A1:AF1')->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E8B800']
    ],
    'font' => [
        'bold' => true,
        'size' => 12,
        'color' => ['rgb' => '0C1C3E']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '0C1C3E']
        ]
    ]
]);

// إضافة صفوف أمثلة (3 أمثلة)
$exampleData = [
    [
        'EQ-001', 'شركة المعدات الثقيلة', 'حفار', 'حفار كاتربيلر 320', 
        'EXC-2024-001', 'CAT320-ABC123456', 'كاتربيلر', '320D', 
        '2018', '2020', 'في حالة جيدة', '5400', 'جيدة', 'N/A',
        'محمد علي أحمد', 'مالك فردي', '+249-912345678', 
        'تابع للمورد (مملوكة للمورد نفسه)',
        'VEH-2024-12345', 'المرور', '2025-12-31', 
        'INS-2024-001', '2024-06-15',
        'منجم الذهب الشرقي', 'متاحة للعمل',
        '150000', '500', '10000', 'مؤمن بالكامل',
        '2024-05-10', 'معدة موثوقة، تحتاج صيانة دورية كل 3 أشهر', '1'
    ],
    [
        'EQ-002', 'مؤسسة النقل', 'قلاب', 'شاحنة قلاب هيونداي', 
        'TRK-2024-002', 'HYN-DEF789012', 'هيونداي', 'HD270', 
        '2019', '2021', 'جديدة نسبياً (أقل من سنة استخدام)', '2800', 'ممتازة', 'جيدة',
        'أحمد محمد علي', 'شركة متخصصة', '+249-923456789', 
        'مالك مباشر (يتعاقد معنا مباشرة)',
        'VEH-2024-67890', 'وزارة النقل', '2026-03-15', 
        'INS-2024-002', '2024-07-20',
        'مستودع الخرطوم', 'قيد الاستخدام',
        '80000', '300', '7000', 'مؤمن جزئياً',
        '2024-06-01', 'شاحنة جديدة بحالة ممتازة', '1'
    ],
    [
        'EQ-003', 'شركة المعدات الثقيلة', 'لودر', 'لودر كوماتسو', 
        'LDR-2024-003', 'KOM-GHI345678', 'كوماتسو', 'WA380', 
        '2017', '2019', 'في حالة متوسطة', '8900', 'متوسطة', 'N/A',
        'عبدالله حسن', 'مؤسسة', '+249-934567890', 
        'تحت وساطة المورد (المورد يدير المعدة نيابة عنه)',
        'VEH-2024-11111', 'المرور', '2025-08-20', 
        '', '2024-04-10',
        'منجم النحاس الغربي', 'تحت الصيانة',
        '120000', '450', '9000', 'غير مؤمن',
        '2024-03-15', 'تحتاج إصلاحات دورية للمحرك', '1'
    ]
];

$row = 2;
foreach ($exampleData as $data) {
    $col = 'A';
    foreach ($data as $value) {
        $sheet->setCellValue($col . $row, $value);
        $col++;
    }
    
    // تنسيق صف المثال
    $sheet->getStyle('A' . $row . ':AF' . $row)->applyFromArray([
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F0F9FF']
        ],
        'font' => [
            'size' => 11
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
    
    $row++;
}

// تجميد الصف الأول
$sheet->freezePane('A2');

// ===== إضافة ورقة التعليمات =====
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('التعليمات');
$instructionsSheet->setRightToLeft(true);

// عنوان ورقة التعليمات
$instructionsSheet->setCellValue('A1', 'تعليمات استيراد المعدات من Excel');
$instructionsSheet->mergeCells('A1:D1');
$instructionsSheet->getStyle('A1')->applyFromArray([
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0C1C3E']
    ],
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);
$instructionsSheet->getRowDimension(1)->setRowHeight(30);

$instructionsText = [
    ['', 'القواعد الأساسية', '', ''],
    ['1', 'الحقول المطلوبة (يجب ملؤها):', '', ''],
    ['', '• كود المعدة', '', ''],
    ['', '• اسم المورد (يجب أن يكون موجوداً في قاعدة البيانات)', '', ''],
    ['', '• نوع المعدة (يجب أن يكون موجوداً في قاعدة البيانات)', '', ''],
    ['', '• اسم المعدة', '', ''],
    ['', '', '', ''],
    ['2', 'التنسيقات المطلوبة:', '', ''],
    ['', '• التواريخ: يجب أن تكون بصيغة YYYY-MM-DD (مثال: 2024-12-31)', '', ''],
    ['', '• الأرقام: استخدم أرقام فقط بدون فواصل (مثال: 150000 وليس 150,000)', '', ''],
    ['', '• الحالة: 1 للنشط، 0 لغير نشط', '', ''],
    ['', '', '', ''],
    ['3', 'الحقول ذات القيم المحددة:', '', ''],
    ['', '• حالة المعدة: جديدة (لم تستخدم) | جديدة نسبياً (أقل من سنة استخدام) | في حالة جيدة | في حالة متوسطة | في حالة ضعيفة | محتاجة إصلاح فوري | معطلة مؤقتاً | مستعملة بكثافة', '', ''],
    ['', '• حالة المحرك: ممتازة | جيدة | متوسطة | محتاجة صيانة | محتاجة إصلاح', '', ''],
    ['', '• حالة الإطارات: N/A | جديدة | جيدة | متوسطة | محتاجة تبديل', '', ''],
    ['', '• نوع المالك: مالك فردي | شركة متخصصة | مؤسسة | أخرى', '', ''],
    ['', '• علاقة المالك بالمورد: مالك مباشر (يتعاقد معنا مباشرة) | تحت وساطة المورد (المورد يدير المعدة نيابة عنه) | تابع للمورد (مملوكة للمورد نفسه) | غير محدد', '', ''],
    ['', '• حالة التوفر: متاحة للعمل | قيد الاستخدام | تحت الصيانة | محجوزة | معطلة | في المستودع | مبيعة/مسحوبة', '', ''],
    ['', '• التأمين/الضمان: مؤمن بالكامل | مؤمن جزئياً | غير مؤمن | جاري التأمين', '', ''],
    ['', '', '', ''],
    ['4', 'ملاحظات مهمة:', '', ''],
    ['', '• تأكد من إدخال اسم المورد ونوع المعدة بشكل صحيح (يجب أن يطابقوا ما هو موجود في النظام)', '', ''],
    ['', '• كود المعدة يجب أن يكون فريداً (غير مكرر)', '', ''],
    ['', '• الحقول الاختيارية يمكن تركها فارغة', '', ''],
    ['', '• إذا كانت المعدة لا تحتوي على إطارات (مثل الحفارات)، ضع "N/A" في حقل حالة الإطارات', '', ''],
    ['', '• القيم المالية يجب أن تكون بالدولار الأمريكي', '', ''],
    ['', '', '', ''],
    ['5', 'خطوات الاستيراد:', '', ''],
    ['', '1. املأ البيانات في ورقة "قالب المعدات"', '', ''],
    ['', '2. احذف الأمثلة (الصفوف 2-4) قبل الرفع', '', ''],
    ['', '3. احفظ الملف بصيغة .xlsx أو .xls أو .csv', '', ''],
    ['', '4. ارفع الملف من صفحة إدارة المعدات', '', ''],
    ['', '5. راجع النتائج بعد الاستيراد', '', ''],
];

$row = 2;
foreach ($instructionsText as $textRow) {
    $col = 'A';
    foreach ($textRow as $cell) {
        $instructionsSheet->setCellValue($col . $row, $cell);
        $col++;
    }
    
    // تنسيق العناوين الرئيسية
    if ($textRow[1] && preg_match('/^[0-9]+$/', $textRow[0])) {
        $instructionsSheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8B800']
            ],
            'font' => [
                'bold' => true,
                'size' => 12
            ]
        ]);
    }
    
    $row++;
}

// تعيين عرض الأعمدة في ورقة التعليمات
$instructionsSheet->getColumnDimension('A')->setWidth(5);
$instructionsSheet->getColumnDimension('B')->setWidth(100);
$instructionsSheet->getColumnDimension('C')->setWidth(5);
$instructionsSheet->getColumnDimension('D')->setWidth(5);

// تنسيق عام لورقة التعليمات
$instructionsSheet->getStyle('A2:D' . ($row - 1))->applyFromArray([
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_RIGHT,
        'vertical' => Alignment::VERTICAL_TOP,
        'wrapText' => true
    ],
    'font' => [
        'size' => 11
    ]
]);

// تعيين ورقة المعدات كورقة نشطة
$spreadsheet->setActiveSheetIndex(0);

// حفظ الملف وتحميله
$filename = 'قالب_استيراد_المعدات_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
