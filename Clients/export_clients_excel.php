<?php
/**
 * تصدير بيانات العملاء إلى ملف Excel
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

require_once '../config.php';
require_once '../includes/permissions_helper.php';

// التحقق من معرف الشركة
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.'));
    exit();
}

// التحقق من صلاحية القراءة
$module_result = $conn->query(
    "SELECT id FROM modules 
     WHERE code = 'Clients/clients.php' OR code = 'clients'
        OR code LIKE '%clients.php%' OR name LIKE '%عملاء%'
     LIMIT 1"
);
$module_info = $module_result ? $module_result->fetch_assoc() : null;
$module_id   = $module_info ? $module_info['id'] : null;

$can_view = true; // افتراضي: السماح بالقراءة
if ($module_id) {
    $perms    = get_module_permissions($conn, $module_id);
    $can_view = $perms['can_view'] ?? true;
}

if (!$can_view) {
    header('Location: clients.php?msg=' . urlencode('لا توجد صلاحية لتصدير العملاء'));
    exit();
}

// ── جلب بيانات العملاء ──
$sql = "SELECT 
            client_code     AS `كود العميل`,
            client_name     AS `اسم العميل`,
            entity_type     AS `نوع الكيان`,
            sector_category AS `تصنيف القطاع`,
            phone           AS `رقم الهاتف`,
            email           AS `البريد الإلكتروني`,
            whatsapp        AS `واتساب`,
            status          AS `الحالة`,
            DATE_FORMAT(created_at, '%Y-%m-%d') AS `تاريخ الإضافة`
        FROM clients
        WHERE company_id = {$company_id}
          AND is_deleted = 0
        ORDER BY id ASC";

$result = mysqli_query($conn, $sql);
if (!$result) {
    header('Location: clients.php?msg=' . urlencode('خطأ في جلب البيانات: ' . mysqli_error($conn)));
    exit();
}

$clients_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $clients_data[] = $row;
}
mysqli_free_result($result);

// ════════════════════════════════════════════
// إنشاء ملف Excel
// ════════════════════════════════════════════
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('EMS System')
    ->setTitle('قائمة العملاء')
    ->setDescription('تصدير بيانات العملاء - ' . date('Y-m-d'));

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('بيانات العملاء');
$sheet->setRightToLeft(true);

// أعمدة التصدير (بنفس ترتيب الاستيراد + تاريخ الإضافة)
$columns = [
    'A' => ['header' => 'كود العميل',          'width' => 18],
    'B' => ['header' => 'اسم العميل',           'width' => 35],
    'C' => ['header' => 'نوع الكيان',           'width' => 20],
    'D' => ['header' => 'تصنيف القطاع',         'width' => 25],
    'E' => ['header' => 'رقم الهاتف',           'width' => 20],
    'F' => ['header' => 'البريد الإلكتروني',    'width' => 35],
    'G' => ['header' => 'واتساب',               'width' => 20],
    'H' => ['header' => 'الحالة',               'width' => 15],
    'I' => ['header' => 'تاريخ الإضافة',        'width' => 20],
];

// عرض الأعمدة
foreach ($columns as $col => $info) {
    $sheet->getColumnDimension($col)->setWidth($info['width']);
}

// ── صف الترويسة الأول: اسم الشركة والتاريخ ──
$last_col = array_key_last($columns);
$sheet->mergeCells('A1:' . $last_col . '1');
$sheet->setCellValue('A1', 'قائمة العملاء - تاريخ التصدير: ' . date('Y-m-d H:i'));
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(35);

// ── صف رؤوس الأعمدة ──
$headerStyle = [
    'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2c5282']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1a365d']]],
];

foreach ($columns as $col => $info) {
    $sheet->setCellValue($col . '2', $info['header']);
}
$sheet->getStyle('A2:' . $last_col . '2')->applyFromArray($headerStyle);
$sheet->getRowDimension(2)->setRowHeight(28);

// تجميد الصفين الأولين
$sheet->freezePane('A3');

// ── صفوف البيانات ──
$col_keys = array_keys($columns);
$db_keys  = [
    'كود العميل', 'اسم العميل', 'نوع الكيان', 'تصنيف القطاع',
    'رقم الهاتف', 'البريد الإلكتروني', 'واتساب', 'الحالة', 'تاريخ الإضافة',
];

$row_num    = 3;
$total_rows = count($clients_data);

foreach ($clients_data as $client) {
    foreach ($col_keys as $i => $col) {
        $val = $client[$db_keys[$i]] ?? '';
        $sheet->setCellValue($col . $row_num, $val);
    }

    // تلوين تبادلي للصفوف
    $fill_color = ($row_num % 2 === 0) ? 'EBF4FF' : 'FFFFFF';

    $sheet->getStyle('A' . $row_num . ':' . $last_col . $row_num)->applyFromArray([
        'font'      => ['size' => 10, 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill_color]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E0']]],
    ]);

    // تلوين خلية الحالة
    $status_val = $client['الحالة'] ?? '';
    if ($status_val === 'نشط') {
        $sheet->getStyle('H' . $row_num)->applyFromArray([
            'font' => ['color' => ['rgb' => '276749'], 'bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C6F6D5']],
        ]);
    } elseif ($status_val === 'متوقف') {
        $sheet->getStyle('H' . $row_num)->applyFromArray([
            'font' => ['color' => ['rgb' => '9B2226'], 'bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FED7D7']],
        ]);
    }

    $sheet->getRowDimension($row_num)->setRowHeight(22);
    $row_num++;
}

// ── صف الإجمالي ──
if ($total_rows > 0) {
    $sheet->mergeCells('A' . $row_num . ':H' . $row_num);
    $sheet->setCellValue('A' . $row_num, 'إجمالي العملاء: ' . $total_rows);
    $sheet->getStyle('A' . $row_num . ':' . $last_col . $row_num)->applyFromArray([
        'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2d6a4f']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension($row_num)->setRowHeight(28);
}

// ── إرسال الملف للمتصفح ──
$ascii_name = 'clients_export_' . date('Y-m-d') . '.xlsx';
$utf8_name  = 'تصدير_العملاء_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $ascii_name . '"; filename*=UTF-8\'\'' . rawurlencode($utf8_name));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
