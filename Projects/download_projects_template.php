<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
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

// Ø¥Ù†Ø´Ø§Ø¡ Ù…Ù„Ù Excel Ø¬Ø¯ÙŠØ¯
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ØªØ¹ÙŠÙŠÙ† Ø§ØªØ¬Ø§Ù‡ RTL
$sheet->setRightToLeft(true);

// ØªØ¹ÙŠÙŠÙ† Ø¹Ù†ÙˆØ§Ù† Ø§Ù„Ù…Ù„Ù
$sheet->setTitle('Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹');

// ØªØ­Ø¯ÙŠØ¯ Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø©
$widths = ['A' => 20, 'B' => 25, 'C' => 20, 'D' => 30, 'E' => 20, 'F' => 20, 'G' => 20, 'H' => 20, 'I' => 20, 'J' => 20, 'K' => 25, 'L' => 20];
foreach ($widths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// ØªÙ†Ø³ÙŠÙ‚ ØµÙ Ø§Ù„Ø±Ø£Ø³
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

// Ø¥Ø¶Ø§ÙØ© Ø±Ø¤ÙˆØ³ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø©
$headers = [
    'A1' => 'Ø§Ø³Ù… Ø§Ù„Ù…Ø´Ø±ÙˆØ¹ *',
    'B1' => 'Ø§Ø³Ù… Ø§Ù„Ø¹Ù…ÙŠÙ„',
    'C1' => 'ÙƒÙˆØ¯ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹',
    'D1' => 'Ø§Ù„ÙØ¦Ø©',
    'E1' => 'Ø§Ù„Ù‚Ø·Ø§Ø¹ Ø§Ù„ÙØ±Ø¹ÙŠ',
    'F1' => 'Ø§Ù„ÙˆÙ„Ø§ÙŠØ©',
    'G1' => 'Ø§Ù„Ù…Ù†Ø·Ù‚Ø©',
    'H1' => 'Ø£Ù‚Ø±Ø¨ Ø³ÙˆÙ‚',
    'I1' => 'Ø®Ø· Ø§Ù„Ø¹Ø±Ø¶',
    'J1' => 'Ø®Ø· Ø§Ù„Ø·ÙˆÙ„',
    'K1' => 'Ù…ÙˆÙ‚Ø¹ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹',
    'L1' => 'Ø§Ù„Ø­Ø§Ù„Ø© *'
];

foreach ($headers as $cell => $header) {
    $sheet->setCellValue($cell, $header);
}

// ØªØ·Ø¨ÙŠÙ‚ ØªÙ†Ø³ÙŠÙ‚ Ø§Ù„Ø±Ø£Ø³
$sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(35);

// Ø¥Ø¶Ø§ÙØ© ØµÙÙˆÙ Ù…Ø«Ø§Ù„ Ù…Ø¹ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª
$exampleData = [
    ['Ù…Ø´Ø±ÙˆØ¹ Ø§Ù„Ø·Ø±ÙŠÙ‚ Ø§Ù„Ø³Ø±ÙŠØ¹', 'ÙˆØ²Ø§Ø±Ø© Ø§Ù„Ø¨Ù†ÙŠØ© Ø§Ù„ØªØ­ØªÙŠØ©', 'PRJ-2026-001', 'Ø·Ø±Ù‚ ÙˆØ¬Ø³ÙˆØ±', 'Ø§Ù„Ø·Ø±Ù‚ Ø§Ù„Ø³Ø±ÙŠØ¹Ø©', 'Ø§Ù„Ø®Ø±Ø·ÙˆÙ…', 'Ø§Ù„Ø®Ø±Ø·ÙˆÙ… Ø¨Ø­Ø±ÙŠ', 'Ø³ÙˆÙ‚ Ù„ÙŠØ¨ÙŠØ§', '15.5527', '32.5599', 'Ø§Ù„Ø®Ø±Ø·ÙˆÙ… - Ø¨ÙˆØ±ØªØ³ÙˆØ¯Ø§Ù†', 'Ù†Ø´Ø·'],
    ['Ù…Ø´Ø±ÙˆØ¹ Ù…Ø­Ø·Ø© Ø§Ù„Ù…ÙŠØ§Ù‡', 'Ù‡ÙŠØ¦Ø© Ø§Ù„Ù…ÙŠØ§Ù‡', 'PRJ-2026-002', 'Ù…ÙŠØ§Ù‡', 'Ù…Ø­Ø·Ø§Øª Ø§Ù„Ù…ÙŠØ§Ù‡', 'Ø§Ù„Ù†ÙŠÙ„ Ø§Ù„Ø£Ø²Ø±Ù‚', 'Ø§Ù„Ø¯Ù…Ø§Ø²ÙŠÙ†', 'Ø³ÙˆÙ‚ Ø§Ù„Ø¯Ù…Ø§Ø²ÙŠÙ†', '11.7891', '34.3592', 'Ø§Ù„Ø¯Ù…Ø§Ø²ÙŠÙ†', 'Ù†Ø´Ø·'],
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

// Ø¥Ø¶Ø§ÙØ© Ù…Ù„Ø§Ø­Ø¸Ø§Øª ÙˆØ¥Ø±Ø´Ø§Ø¯Ø§Øª
$sheet->setCellValue('A5', 'Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ù…Ù‡Ù…Ø©:');
$sheet->getStyle('A5')->getFont()->setBold(true)->setSize(12);

$instructions = [
    'A6' => '* Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ù…Ø´Ø§Ø± Ø¥Ù„ÙŠÙ‡Ø§ Ø¨Ù€ (*) Ø¥Ù„Ø²Ø§Ù…ÙŠØ©',
    'A7' => 'â€¢ ÙƒÙˆØ¯ Ø§Ù„Ù…Ø´Ø±ÙˆØ¹: ÙŠØ¬Ø¨ Ø£Ù† ÙŠÙƒÙˆÙ† ÙØ±ÙŠØ¯Ø§Ù‹ Ù„ÙƒÙ„ Ù…Ø´Ø±ÙˆØ¹',
    'A8' => 'â€¢ Ø§Ù„Ø­Ø§Ù„Ø©: ÙŠØ¬Ø¨ Ø£Ù† ØªÙƒÙˆÙ† "Ù†Ø´Ø·" Ø£Ùˆ "ØºÙŠØ± Ù†Ø´Ø·"',
    'A9' => 'â€¢ Ø§Ù„Ø¥Ø­Ø¯Ø§Ø«ÙŠØ§Øª: Ø®Ø· Ø§Ù„Ø¹Ø±Ø¶ ÙˆØ§Ù„Ø·ÙˆÙ„ (latitude/longitude)',
    'A10' => 'â€¢ Ù„Ø§ ØªØºÙŠØ± Ø±Ø¤ÙˆØ³ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø©',
];

foreach ($instructions as $cell => $instruction) {
    $sheet->setCellValue($cell, $instruction);
    $sheet->getStyle($cell)->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF666666'));
}

// ØªØ¹ÙŠÙŠÙ† Ø§Ø³Ù… Ø§Ù„Ù…Ù„Ù ÙˆØ§Ù„ØªÙ†Ø²ÙŠÙ„
$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Ù†Ù…ÙˆØ°Ø¬_Ø§Ù„Ù…Ø´Ø§Ø±ÙŠØ¹_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
?>

