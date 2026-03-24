<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Ø¥Ù†Ø´Ø§Ø¡ Ù…Ù„Ù Excel Ø¬Ø¯ÙŠØ¯
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ØªØ¹ÙŠÙŠÙ† Ø§ØªØ¬Ø§Ù‡ RTL
$sheet->setRightToLeft(true);

// ØªØ¹ÙŠÙŠÙ† Ø¹Ù†ÙˆØ§Ù† Ø§Ù„Ù…Ù„Ù
$sheet->setTitle('Ù†Ù…ÙˆØ°Ø¬ Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡');

// ØªØ­Ø¯ÙŠØ¯ Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø©
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(25);
$sheet->getColumnDimension('E')->setWidth(20);
$sheet->getColumnDimension('F')->setWidth(30);
$sheet->getColumnDimension('G')->setWidth(20);
$sheet->getColumnDimension('H')->setWidth(15);

// ØªÙ†Ø³ÙŠÙ‚ ØµÙ Ø§Ù„Ø±Ø£Ø³
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

// Ø¥Ø¶Ø§ÙØ© Ø±Ø¤ÙˆØ³ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø©
$headers = [
    'A1' => 'ÙƒÙˆØ¯ Ø§Ù„Ø¹Ù…ÙŠÙ„ *',
    'B1' => 'Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„ *',
    'C1' => 'Ù†ÙˆØ¹ Ø§Ù„ÙƒÙŠØ§Ù†',
    'D1' => 'ØªØµÙ†ÙŠÙ Ø§Ù„Ù‚Ø·Ø§Ø¹',
    'E1' => 'Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ',
    'F1' => 'Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ',
    'G1' => 'ÙˆØ§ØªØ³Ø§Ø¨',
    'H1' => 'Ø§Ù„Ø­Ø§Ù„Ø© *'
];

foreach ($headers as $cell => $header) {
    $sheet->setCellValue($cell, $header);
}

// ØªØ·Ø¨ÙŠÙ‚ ØªÙ†Ø³ÙŠÙ‚ Ø§Ù„Ø±Ø£Ø³
$sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(30);

// Ø¥Ø¶Ø§ÙØ© ØµÙÙˆÙ Ù…Ø«Ø§Ù„ Ù…Ø¹ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
$exampleData = [
    ['CL-001', 'Ø´Ø±ÙƒØ© Ø§Ù„Ù…Ø³ØªÙ‚Ø¨Ù„ Ù„Ù„Ù…Ù‚Ø§ÙˆÙ„Ø§Øª', 'Ø´Ø±ÙƒØ©', 'Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡Ø§Øª', '+249123456789', 'info@future-co.com', '+249123456789', 'Ù†Ø´Ø·'],
    ['CL-002', 'Ù…Ø¤Ø³Ø³Ø© Ø§Ù„Ù†Ù‡Ø¶Ø© Ø§Ù„ØªØ¬Ø§Ø±ÙŠØ©', 'Ù…Ø¤Ø³Ø³Ø©', 'Ø§Ù„Ø®Ø¯Ù…Ø§Øª', '+249987654321', 'contact@nahda.com', '+249987654321', 'Ù†Ø´Ø·'],
    ['CL-003', 'Ø§Ù„Ù‡ÙŠØ¦Ø© Ø§Ù„Ø¹Ø§Ù…Ø© Ù„Ù„Ø·Ø±Ù‚', 'Ø¬Ù‡Ø© Ø­ÙƒÙˆÙ…ÙŠØ©', 'Ø§Ù„Ø¨Ù†ÙŠØ© Ø§Ù„ØªØ­ØªÙŠØ©', '+249111222333', 'roads@gov.sd', '+249111222333', 'Ù†Ø´Ø·']
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

// ØªÙ†Ø³ÙŠÙ‚ ØµÙÙˆÙ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
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

// Ø¥Ø¶Ø§ÙØ© ÙˆØ±Ù‚Ø© ØªØ¹Ù„ÙŠÙ…Ø§Øª
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Ø§Ù„ØªØ¹Ù„ÙŠÙ…Ø§Øª');
$instructionsSheet->setRightToLeft(true);

$instructionsSheet->getColumnDimension('A')->setWidth(80);

$instructions = [
    ['ØªØ¹Ù„ÙŠÙ…Ø§Øª Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡'],
    [''],
    ['Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© (ÙŠØ¬Ø¨ Ù…Ù„Ø¤Ù‡Ø§):'],
    ['1. ÙƒÙˆØ¯ Ø§Ù„Ø¹Ù…ÙŠÙ„: Ø±Ù…Ø² ÙØ±ÙŠØ¯ Ù„ÙƒÙ„ Ø¹Ù…ÙŠÙ„ (Ù…Ø«Ø§Ù„: CL-001)'],
    ['2. Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„: Ø§Ù„Ø§Ø³Ù… Ø§Ù„ÙƒØ§Ù…Ù„ Ù„Ù„Ø¹Ù…ÙŠÙ„'],
    ['3. Ø§Ù„Ø­Ø§Ù„Ø©: Ù†Ø´Ø· Ø£Ùˆ Ù…ØªÙˆÙ‚Ù'],
    [''],
    ['Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ø§Ø®ØªÙŠØ§Ø±ÙŠØ©:'],
    ['- Ù†ÙˆØ¹ Ø§Ù„ÙƒÙŠØ§Ù†: Ø´Ø±ÙƒØ©ØŒ Ù…Ø¤Ø³Ø³Ø©ØŒ Ø¬Ù‡Ø© Ø­ÙƒÙˆÙ…ÙŠØ©ØŒ ÙØ±Ø¯ØŒ Ø£Ø®Ø±Ù‰'],
    ['- ØªØµÙ†ÙŠÙ Ø§Ù„Ù‚Ø·Ø§Ø¹: Ø§Ù„Ù†ÙØ· ÙˆØ§Ù„ØºØ§Ø²ØŒ Ø§Ù„Ø¨Ù†ÙŠØ© Ø§Ù„ØªØ­ØªÙŠØ©ØŒ Ø§Ù„Ø·Ø±Ù‚ ÙˆØ§Ù„Ø¬Ø³ÙˆØ±ØŒ Ø§Ù„Ø¥Ù†Ø´Ø§Ø¡Ø§ØªØŒ Ø§Ù„ØªØ¹Ø¯ÙŠÙ†ØŒ Ø§Ù„Ø²Ø±Ø§Ø¹Ø©ØŒ Ø§Ù„Ø®Ø¯Ù…Ø§ØªØŒ Ø£Ø®Ø±Ù‰'],
    ['- Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ: Ø±Ù‚Ù… Ø§Ù„Ø§ØªØµØ§Ù„'],
    ['- Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ: Ø¹Ù†ÙˆØ§Ù† Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ'],
    ['- ÙˆØ§ØªØ³Ø§Ø¨: Ø±Ù‚Ù… Ø§Ù„ÙˆØ§ØªØ³Ø§Ø¨'],
    [''],
    ['Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ù…Ù‡Ù…Ø©:'],
    ['âœ“ ÙƒÙˆØ¯ Ø§Ù„Ø¹Ù…ÙŠÙ„ ÙŠØ¬Ø¨ Ø£Ù† ÙŠÙƒÙˆÙ† ÙØ±ÙŠØ¯Ø§Ù‹ ÙˆÙ„Ø§ ÙŠØªÙƒØ±Ø±'],
    ['âœ“ Ø§Ø³ØªØ®Ø¯Ù… ÙˆØ±Ù‚Ø© "Ù†Ù…ÙˆØ°Ø¬ Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡" Ù„Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª'],
    ['âœ“ Ø§Ø­Ø°Ù Ø§Ù„ØµÙÙˆÙ Ø§Ù„Ù…Ø«Ø§Ù„ÙŠØ© Ù‚Ø¨Ù„ Ø§Ù„Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø£Ùˆ Ø§Ø³ØªØ¨Ø¯Ù„Ù‡Ø§ Ø¨Ø¨ÙŠØ§Ù†Ø§ØªÙƒ'],
    ['âœ“ ØªØ£ÙƒØ¯ Ù…Ù† Ù…Ù„Ø¡ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© Ø§Ù„Ù…Ø´Ø§Ø± Ø¥Ù„ÙŠÙ‡Ø§ Ø¨Ù€ *'],
    ['âœ“ Ø§Ù„ØµÙŠØºØ© Ø§Ù„Ù…Ø¯Ø¹ÙˆÙ…Ø©: .xlsx Ø£Ùˆ .xls'],
];

$instructionRow = 1;
foreach ($instructions as $instruction) {
    $instructionsSheet->setCellValue('A' . $instructionRow, $instruction[0]);
    $instructionRow++;
}

// ØªÙ†Ø³ÙŠÙ‚ Ø§Ù„Ø¹Ù†ÙˆØ§Ù† Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠ
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

// ØªÙ†Ø³ÙŠÙ‚ Ø§Ù„Ø¹Ù†Ø§ÙˆÙŠÙ† Ø§Ù„ÙØ±Ø¹ÙŠØ©
$instructionsSheet->getStyle('A3')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '764ba2']]
]);
$instructionsSheet->getStyle('A8')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '764ba2']]
]);
$instructionsSheet->getStyle('A15')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'fc4a1a']]
]);

// Ø§Ù„Ø¹ÙˆØ¯Ø© Ø¥Ù„Ù‰ Ø§Ù„ÙˆØ±Ù‚Ø© Ø§Ù„Ø£ÙˆÙ„Ù‰
$spreadsheet->setActiveSheetIndex(0);

// ØªØ­Ø¯ÙŠØ¯ Ø§Ø³Ù… Ø§Ù„Ù…Ù„Ù
$filename = 'Ù†Ù…ÙˆØ°Ø¬_Ø§Ø³ØªÙŠØ±Ø§Ø¯_Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡_' . date('Y-m-d') . '.xlsx';

// ØªØ¹ÙŠÙŠÙ† Ø§Ù„Ù‡ÙŠØ¯Ø±Ø§Øª Ù„ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù…Ù„Ù
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Ø¥Ù†Ø´Ø§Ø¡ Ø§Ù„Ù…Ù„Ù ÙˆØ¥Ø±Ø³Ø§Ù„Ù‡ Ù„Ù„Ù…ØªØµÙØ­
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>

