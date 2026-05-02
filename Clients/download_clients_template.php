<?php
/**
 * تحميل نموذج Excel لاستيراد العملاء
 * يدعم العربية بالكامل مع PhpSpreadsheet
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

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ── إنشاء الملف ──
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('EMS System')
    ->setTitle('نموذج استيراد العملاء')
    ->setDescription('نموذج Excel لاستيراد بيانات العملاء');

// ════════════════════════════════════════════
// ورقة 1: نموذج البيانات
// ════════════════════════════════════════════
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('نموذج العملاء');
$sheet->setRightToLeft(true);

// عرض الأعمدة
$colWidths = ['A' => 18, 'B' => 35, 'C' => 20, 'D' => 25, 'E' => 20, 'F' => 35, 'G' => 20, 'H' => 15];
foreach ($colWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}
$sheet->getRowDimension('1')->setRowHeight(35);

// تنسيق صف الرأس
$headerStyle = [
    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11, 'name' => 'Arial'],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '2c5282']]],
];

// رؤوس الأعمدة - تطابق تماماً حقول جدول clients
$headers = [
    'A1' => "كود العميل\n(مطلوب - فريد)",
    'B1' => "اسم العميل\n(مطلوب)",
    'C1' => "نوع الكيان",
    'D1' => "تصنيف القطاع",
    'E1' => "رقم الهاتف",
    'F1' => "البريد الإلكتروني",
    'G1' => "واتساب",
    'H1' => "الحالة\n(نشط / متوقف)",
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}
$sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

// ── صفوف أمثلة ──
$examples = [
    ['CLT-0001', 'شركة النيل للمقاولات', 'حكومي', 'بنية تحتية', '+249123456789', 'nile@example.com', '+249123456789', 'نشط'],
    ['CLT-0002', 'مؤسسة الخرطوم التجارية', 'خاص', 'خدمات', '+249987654321', 'khartoum@example.com', '+249987654321', 'نشط'],
    ['CLT-0003', 'الهيئة العامة للطرق والجسور', 'حكومي', 'نقل ومواصلات', '+249111222333', 'roads@gov.sd', '', 'نشط'],
];

$dataStyle = [
    'font'      => ['name' => 'Arial', 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E0']]],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F7FAFC']],
];

$r = 2;
foreach ($examples as $ex) {
    $c = 'A';
    foreach ($ex as $val) {
        $sheet->setCellValue($c . $r, $val);
        $c++;
    }
    $sheet->getRowDimension($r)->setRowHeight(25);
    $r++;
}
$sheet->getStyle('A2:H' . ($r - 1))->applyFromArray($dataStyle);

// تجميد الصف الأول
$sheet->freezePane('A2');

// ════════════════════════════════════════════
// ورقة 2: التعليمات
// ════════════════════════════════════════════
$guide = $spreadsheet->createSheet();
$guide->setTitle('التعليمات');
$guide->setRightToLeft(true);
$guide->getColumnDimension('A')->setWidth(10);
$guide->getColumnDimension('B')->setWidth(90);
$guide->getRowDimension(1)->setRowHeight(40);

// عنوان
$guide->mergeCells('A1:B1');
$guide->setCellValue('A1', 'تعليمات استيراد العملاء - نظام EMS');
$guide->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);

$rows_guide = [
    [3,  'العمود', 'الوصف والقيم المقبولة'],
    [4,  'A - كود العميل', 'مطلوب - يجب أن يكون فريداً (مثال: CLT-0001)'],
    [5,  'B - اسم العميل', 'مطلوب - الاسم الكامل للعميل أو الشركة'],
    [6,  'C - نوع الكيان', 'اختياري - مثال: حكومي / خاص / مختلط / دولي / غير ربحي'],
    [7,  'D - تصنيف القطاع', 'اختياري - مثال: بنية تحتية / تعدين / خدمات / نفط وغاز / زراعة'],
    [8,  'E - رقم الهاتف', 'اختياري - رقم الهاتف الكامل مع رمز الدولة (مثال: +249123456789)'],
    [9,  'F - البريد الإلكتروني', 'اختياري - يجب أن يكون بصيغة صحيحة (مثال: name@domain.com)'],
    [10, 'G - واتساب', 'اختياري - رقم الواتساب (مثال: +249123456789)'],
    [11, 'H - الحالة', 'اختياري - نشط (افتراضي) أو متوقف'],
    [13, 'ملاحظات مهمة:', ''],
    [14, '1.', 'احذف صفوف الأمثلة في ورقة "نموذج العملاء" قبل رفع الملف'],
    [15, '2.', 'كود العميل يجب أن يكون فريداً - أي كود مكرر سيتم تخطيه'],
    [16, '3.', 'الحقول المطلوبة: كود العميل + اسم العميل فقط'],
    [17, '4.', 'الصيغ المدعومة للرفع: .xlsx أو .csv'],
    [18, '5.', 'الحد الأقصى للصفوف في الاستيراد الواحد: 1000 عميل'],
];

$headerRowStyle = [
    'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2d6a4f']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1b4332']]],
];
$noteStyle = [
    'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '9b2226'], 'name' => 'Arial'],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'fff3cd']],
];
$cellStyle = [
    'font'      => ['size' => 10, 'name' => 'Arial'],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E0']]],
];

foreach ($rows_guide as $row_data) {
    [$row_num, $col_a, $col_b] = $row_data;
    $guide->setCellValue('A' . $row_num, $col_a);
    $guide->setCellValue('B' . $row_num, $col_b);
    $guide->getRowDimension($row_num)->setRowHeight(28);

    if ($row_num === 3) {
        $guide->getStyle('A3:B3')->applyFromArray($headerRowStyle);
    } elseif ($row_num === 13) {
        $guide->getStyle('A13:B13')->applyFromArray($noteStyle);
    } else {
        $guide->getStyle('A' . $row_num . ':B' . $row_num)->applyFromArray($cellStyle);
    }
}

// العودة للورقة الأولى
$spreadsheet->setActiveSheetIndex(0);

// ── إرسال الملف للمتصفح ──
$ascii_name  = 'clients_import_template_' . date('Y-m-d') . '.xlsx';
$utf8_name   = 'نموذج_استيراد_العملاء_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $ascii_name . '"; filename*=UTF-8\'\'' . rawurlencode($utf8_name));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

