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

// Ø¥Ù†Ø´Ø§Ø¡ Ù…Ù„Ù Excel Ø¬Ø¯ÙŠØ¯
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// ØªØ¹ÙŠÙŠÙ† Ø§ØªØ¬Ø§Ù‡ RTL
$sheet->setRightToLeft(true);
$sheet->setTitle('Ù†Ù…ÙˆØ°Ø¬ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†');

// ØªØ­Ø¯ÙŠØ¯ Ø¹Ø±Ø¶ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø©
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

// ØªÙ†Ø³ÙŠÙ‚ Ø±Ø¤ÙˆØ³ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª (Ø§Ù„ØµÙ Ø§Ù„Ø£ÙˆÙ„)
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

// ØªÙ†Ø³ÙŠÙ‚ Ø±Ø¤ÙˆØ³ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø© (Ø§Ù„ØµÙ Ø§Ù„Ø«Ø§Ù†ÙŠ)
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

// Ø¥Ø¶Ø§ÙØ© Ø±Ø¤ÙˆØ³ Ø§Ù„Ù…Ø¬Ù…ÙˆØ¹Ø§Øª (Ø§Ù„ØµÙ 1)
$sheet->mergeCells('A1:E1');
$sheet->setCellValue('A1', 'ðŸ“‹ Ø§Ù„Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø§Ù„Ø£Ø³Ø§Ø³ÙŠØ©');
$sheet->getStyle('A1:E1')->applyFromArray($groupHeaderStyle);

$sheet->mergeCells('F1:I1');
$sheet->setCellValue('F1', 'âš–ï¸ Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù‚Ø§Ù†ÙˆÙ†ÙŠØ©');
$sheet->getStyle('F1:I1')->applyFromArray($groupHeaderStyle);

$sheet->mergeCells('J1:M1');
$sheet->setCellValue('J1', 'ðŸ“ž Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ØªÙˆØ§ØµÙ„');
$sheet->getStyle('J1:M1')->applyFromArray($groupHeaderStyle);

$sheet->mergeCells('N1:O1');
$sheet->setCellValue('N1', 'ðŸ‘¤ Ø¬Ù‡Ø© Ø§Ù„Ø§ØªØµØ§Ù„');
$sheet->getStyle('N1:O1')->applyFromArray($groupHeaderStyle);

$sheet->mergeCells('P1:Q1');
$sheet->setCellValue('P1', 'â„¹ï¸ Ù…Ø¹Ù„ÙˆÙ…Ø§Øª Ø¥Ø¶Ø§ÙÙŠØ©');
$sheet->getStyle('P1:Q1')->applyFromArray($groupHeaderStyle);

$sheet->getRowDimension('1')->setRowHeight(30);

// Ø¥Ø¶Ø§ÙØ© Ø±Ø¤ÙˆØ³ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø© Ø§Ù„ØªÙØµÙŠÙ„ÙŠØ© (Ø§Ù„ØµÙ 2)
$headers = [
    'A2' => 'ÙƒÙˆØ¯ Ø§Ù„Ù…ÙˆØ±Ø¯ *',
    'B2' => 'Ø§Ø³Ù… Ø§Ù„Ù…ÙˆØ±Ø¯ *',
    'C2' => 'Ù†ÙˆØ¹ Ø§Ù„Ù…ÙˆØ±Ø¯ *',
    'D2' => 'Ø·Ø¨ÙŠØ¹Ø© Ø§Ù„ØªØ¹Ø§Ù…Ù„',
    'E2' => 'Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª',
    'F2' => 'Ø§Ù„Ø³Ø¬Ù„ Ø§Ù„ØªØ¬Ø§Ø±ÙŠ',
    'G2' => 'Ù†ÙˆØ¹ Ø§Ù„Ù‡ÙˆÙŠØ©',
    'H2' => 'Ø±Ù‚Ù… Ø§Ù„Ù‡ÙˆÙŠØ©',
    'I2' => 'ØªØ§Ø±ÙŠØ® Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„Ù‡ÙˆÙŠØ©',
    'J2' => 'Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ',
    'K2' => 'Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ *',
    'L2' => 'Ù‡Ø§ØªÙ Ø¨Ø¯ÙŠÙ„',
    'M2' => 'Ø§Ù„Ø¹Ù†ÙˆØ§Ù† Ø§Ù„ÙƒØ§Ù…Ù„',
    'N2' => 'Ø§Ø³Ù… Ø¬Ù‡Ø© Ø§Ù„Ø§ØªØµØ§Ù„',
    'O2' => 'Ù‡Ø§ØªÙ Ø¬Ù‡Ø© Ø§Ù„Ø§ØªØµØ§Ù„',
    'P2' => 'Ø­Ø§Ù„Ø© Ø§Ù„ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ù…Ø§Ù„ÙŠ',
    'Q2' => 'Ø§Ù„Ø­Ø§Ù„Ø© *'
];

foreach ($headers as $cell => $header) {
    $sheet->setCellValue($cell, $header);
}

// ØªØ·Ø¨ÙŠÙ‚ ØªÙ†Ø³ÙŠÙ‚ Ø±Ø¤ÙˆØ³ Ø§Ù„Ø£Ø¹Ù…Ø¯Ø©
$sheet->getStyle('A2:Q2')->applyFromArray($headerStyle);
$sheet->getRowDimension('2')->setRowHeight(35);

// Ø¥Ø¶Ø§ÙØ© ØµÙÙˆÙ Ù…Ø«Ø§Ù„
$exampleData = [
    [
        'SUP-001', 
        'Ø´Ø±ÙƒØ© Ø§Ù„Ù†ÙŠÙ„ Ù„Ù„Ù…Ø¹Ø¯Ø§Øª Ø§Ù„Ø«Ù‚ÙŠÙ„Ø©', 
        'Ø´Ø±ÙƒØ© Ù…Ø­Ù„ÙŠØ©', 
        'Ù…Ø¨Ø§Ø´Ø±',
        'Ø­ÙØ§Ø±, Ù‚Ù„Ø§Ø¨, Ù„ÙˆØ¯Ø±',
        'CR-123456',
        'Ø¨Ø·Ø§Ù‚Ø© Ø´Ø®ØµÙŠØ©',
        '123456789',
        '2027-12-31',
        'info@nile-equip.com',
        '+249123456789',
        '+249987654321',
        'Ø§Ù„Ø®Ø±Ø·ÙˆÙ… - Ø´Ø§Ø±Ø¹ Ø§Ù„Ù†ÙŠÙ„',
        'Ø£Ø­Ù…Ø¯ Ù…Ø­Ù…Ø¯',
        '+249123456789',
        'Ù…Ø³Ø¬Ù„',
        '1'
    ],
    [
        'SUP-002', 
        'Ù…Ø¤Ø³Ø³Ø© Ø§Ù„Ù…Ø³ØªÙ‚Ø¨Ù„ Ù„Ù„Ø¢Ù„ÙŠØ§Øª', 
        'Ù…Ø¤Ø³Ø³Ø© ÙØ±Ø¯ÙŠØ©', 
        'ÙˆØ³ÙŠØ·',
        'Ù‚Ù„Ø§Ø¨, Ø±Ø§ÙØ¹Ø©',
        'CR-789012',
        'Ø¬ÙˆØ§Ø² Ø³ÙØ±',
        'P987654321',
        '2028-06-30',
        'contact@mustaqbal.sd',
        '+249111222333',
        '+249444555666',
        'Ø£Ù… Ø¯Ø±Ù…Ø§Ù† - Ø§Ù„Ù…ÙˆØ±Ø¯Ø©',
        'Ù…Ø­Ù…Ø¯ Ø£Ø­Ù…Ø¯',
        '+249111222333',
        'ØºÙŠØ± Ù…Ø³Ø¬Ù„',
        '1'
    ],
    [
        'SUP-003', 
        'Ø´Ø±ÙƒØ© Ø§Ù„Ø¬Ø²ÙŠØ±Ø© Ù„Ù„Ù†Ù‚Ù„', 
        'Ø´Ø±ÙƒØ© Ù…Ø³Ø§Ù‡Ù…Ø©', 
        'Ù…Ø¨Ø§Ø´Ø±',
        'Ù‚Ù„Ø§Ø¨, Ù†Ø§Ù‚Ù„Ø©',
        'CR-345678',
        'Ø¨Ø·Ø§Ù‚Ø© Ø´Ø®ØµÙŠØ©',
        '987654321',
        '2026-12-31',
        'info@gezira-trans.com',
        '+249777888999',
        '+249666555444',
        'Ø§Ù„Ø¬Ø²ÙŠØ±Ø© - Ù…Ø¯Ù†ÙŠ',
        'Ø¹Ù„ÙŠ Ø­Ø³Ù†',
        '+249777888999',
        'Ù…Ø³Ø¬Ù„',
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

$sheet->getStyle('A2:Q' . ($row - 1))->applyFromArray($dataStyle);

// Ø¥Ø¶Ø§ÙØ© ÙˆØ±Ù‚Ø© ØªØ¹Ù„ÙŠÙ…Ø§Øª
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Ø§Ù„ØªØ¹Ù„ÙŠÙ…Ø§Øª');
$instructionsSheet->setRightToLeft(true);
$instructionsSheet->getColumnDimension('A')->setWidth(85);

$instructions = [
    ['ØªØ¹Ù„ÙŠÙ…Ø§Øª Ø§Ø³ØªÙŠØ±Ø§Ø¯ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†'],
    [''],
    ['Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© (ÙŠØ¬Ø¨ Ù…Ù„Ø¤Ù‡Ø§):'],
    ['1. ÙƒÙˆØ¯ Ø§Ù„Ù…ÙˆØ±Ø¯: Ø±Ù…Ø² ÙØ±ÙŠØ¯ Ù„ÙƒÙ„ Ù…ÙˆØ±Ø¯ (Ù…Ø«Ø§Ù„: SUP-001)'],
    ['2. Ø§Ø³Ù… Ø§Ù„Ù…ÙˆØ±Ø¯: Ø§Ù„Ø§Ø³Ù… Ø§Ù„ÙƒØ§Ù…Ù„ Ù„Ù„Ù…ÙˆØ±Ø¯'],
    ['3. Ù†ÙˆØ¹ Ø§Ù„Ù…ÙˆØ±Ø¯: Ø´Ø±ÙƒØ© Ù…Ø­Ù„ÙŠØ©ØŒ Ù…Ø¤Ø³Ø³Ø© ÙØ±Ø¯ÙŠØ©ØŒ Ø´Ø±ÙƒØ© Ù…Ø³Ø§Ù‡Ù…Ø©ØŒ Ø´Ø±ÙƒØ© Ø£Ø¬Ù†Ø¨ÙŠØ©ØŒ ÙˆÙƒÙŠÙ„ ØªØ¬Ø§Ø±ÙŠØŒ Ù…Ù‚Ø§ÙˆÙ„ØŒ Ø£Ø®Ø±Ù‰'],
    ['4. Ø±Ù‚Ù… Ø§Ù„Ù‡Ø§ØªÙ: Ø±Ù‚Ù… Ø§Ù„Ø§ØªØµØ§Ù„ Ø§Ù„Ø±Ø¦ÙŠØ³ÙŠ'],
    ['5. Ø§Ù„Ø­Ø§Ù„Ø©: 1 (Ù†Ø´Ø·) Ø£Ùˆ 0 (Ù…Ø¹Ù„Ù‚)'],
    [''],
    ['Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ø§Ø®ØªÙŠØ§Ø±ÙŠØ©:'],
    ['- Ø·Ø¨ÙŠØ¹Ø© Ø§Ù„ØªØ¹Ø§Ù…Ù„: Ù…Ø¨Ø§Ø´Ø±ØŒ ÙˆØ³ÙŠØ·ØŒ ÙˆÙƒÙŠÙ„ØŒ Ø£Ø®Ø±Ù‰'],
    ['- Ø£Ù†ÙˆØ§Ø¹ Ø§Ù„Ù…Ø¹Ø¯Ø§Øª: Ø­ÙØ§Ø±ØŒ Ù‚Ù„Ø§Ø¨ØŒ Ù„ÙˆØ¯Ø±ØŒ Ø¬Ø±ÙŠØ¯Ø±ØŒ Ø±Ø§ÙØ¹Ø©ØŒ Ø£Ø³ÙÙ„ØªØŒ ÙƒØ³Ø§Ø±Ø©ØŒ Ø®Ù„Ø§Ø·Ø©ØŒ Ù…ÙˆÙ„Ø¯ØŒ Ø¶Ø§ØºØ· Ù‡ÙˆØ§Ø¡ØŒ Ù†Ø§Ù‚Ù„Ø© Ù…ÙŠØ§Ù‡ØŒ ØµÙ‡Ø±ÙŠØ¬ ÙˆÙ‚ÙˆØ¯ØŒ Ù†Ø§Ù‚Ù„Ø© Ø¹Ù…Ø§Ù„ØŒ Ø³ÙŠØ§Ø±Ø© Ø®Ø¯Ù…Ø©ØŒ Ø£Ø®Ø±Ù‰ (Ø§ÙØµÙ„ Ø¨ÙØ§ØµÙ„Ø©)'],
    ['- Ø§Ù„Ø³Ø¬Ù„ Ø§Ù„ØªØ¬Ø§Ø±ÙŠ: Ø±Ù‚Ù… Ø§Ù„Ø³Ø¬Ù„ Ø§Ù„ØªØ¬Ø§Ø±ÙŠ'],
    ['- Ù†ÙˆØ¹ Ø§Ù„Ù‡ÙˆÙŠØ©: Ø¨Ø·Ø§Ù‚Ø© Ø´Ø®ØµÙŠØ©ØŒ Ø¬ÙˆØ§Ø² Ø³ÙØ±ØŒ Ø±Ø®ØµØ© ØªØ¬Ø§Ø±ÙŠØ©ØŒ Ø£Ø®Ø±Ù‰'],
    ['- Ø±Ù‚Ù… Ø§Ù„Ù‡ÙˆÙŠØ©: Ø±Ù‚Ù… Ø§Ù„Ù‡ÙˆÙŠØ© Ø£Ùˆ Ø§Ù„ÙˆØ«ÙŠÙ‚Ø©'],
    ['- ØªØ§Ø±ÙŠØ® Ø§Ù†ØªÙ‡Ø§Ø¡ Ø§Ù„Ù‡ÙˆÙŠØ©: Ø¨ØµÙŠØºØ© YYYY-MM-DD (Ù…Ø«Ø§Ù„: 2027-12-31)'],
    ['- Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ: Ø¹Ù†ÙˆØ§Ù† Ø§Ù„Ø¨Ø±ÙŠØ¯ Ø§Ù„Ø¥Ù„ÙƒØªØ±ÙˆÙ†ÙŠ'],
    ['- Ù‡Ø§ØªÙ Ø¨Ø¯ÙŠÙ„: Ø±Ù‚Ù… Ù‡Ø§ØªÙ Ø¥Ø¶Ø§ÙÙŠ'],
    ['- Ø§Ù„Ø¹Ù†ÙˆØ§Ù† Ø§Ù„ÙƒØ§Ù…Ù„: Ø§Ù„Ø¹Ù†ÙˆØ§Ù† Ø§Ù„ØªÙØµÙŠÙ„ÙŠ'],
    ['- Ø§Ø³Ù… Ø¬Ù‡Ø© Ø§Ù„Ø§ØªØµØ§Ù„: Ø§Ø³Ù… Ø§Ù„Ø´Ø®Øµ Ø§Ù„Ù…Ø³Ø¤ÙˆÙ„'],
    ['- Ù‡Ø§ØªÙ Ø¬Ù‡Ø© Ø§Ù„Ø§ØªØµØ§Ù„: Ø±Ù‚Ù… Ù‡Ø§ØªÙ Ø§Ù„Ø´Ø®Øµ Ø§Ù„Ù…Ø³Ø¤ÙˆÙ„'],
    ['- Ø­Ø§Ù„Ø© Ø§Ù„ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ù…Ø§Ù„ÙŠ: Ù…Ø³Ø¬Ù„ØŒ ØºÙŠØ± Ù…Ø³Ø¬Ù„ØŒ Ù…Ø¹Ù„Ù‚ØŒ Ø£Ø®Ø±Ù‰'],
    [''],
    ['Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ù…Ù‡Ù…Ø©:'],
    ['âœ“ ÙƒÙˆØ¯ Ø§Ù„Ù…ÙˆØ±Ø¯ ÙŠØ¬Ø¨ Ø£Ù† ÙŠÙƒÙˆÙ† ÙØ±ÙŠØ¯Ø§Ù‹ ÙˆÙ„Ø§ ÙŠØªÙƒØ±Ø±'],
    ['âœ“ Ø§Ø³ØªØ®Ø¯Ù… ÙˆØ±Ù‚Ø© "Ù†Ù…ÙˆØ°Ø¬ Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†" Ù„Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª'],
    ['âœ“ Ø§Ø­Ø°Ù Ø§Ù„ØµÙÙˆÙ Ø§Ù„Ù…Ø«Ø§Ù„ÙŠØ© Ø£Ùˆ Ø§Ø³ØªØ¨Ø¯Ù„Ù‡Ø§ Ø¨Ø¨ÙŠØ§Ù†Ø§ØªÙƒ Ø§Ù„ÙØ¹Ù„ÙŠØ©'],
    ['âœ“ ØªØ£ÙƒØ¯ Ù…Ù† Ù…Ù„Ø¡ Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø­Ù‚ÙˆÙ„ Ø§Ù„Ù…Ø·Ù„ÙˆØ¨Ø© Ø§Ù„Ù…Ø´Ø§Ø± Ø¥Ù„ÙŠÙ‡Ø§ Ø¨Ù€ *'],
    ['âœ“ Ø¹Ù†Ø¯ Ø¥Ø¯Ø®Ø§Ù„ Ø£Ù†ÙˆØ§Ø¹ Ù…Ø¹Ø¯Ø§Øª Ù…ØªØ¹Ø¯Ø¯Ø©ØŒ Ø§ÙØµÙ„Ù‡Ø§ Ø¨ÙØ§ØµÙ„Ø© (Ù…Ø«Ø§Ù„: Ø­ÙØ§Ø±, Ù‚Ù„Ø§Ø¨, Ù„ÙˆØ¯Ø±)'],
    ['âœ“ Ø§Ù„ØµÙŠØºØ© Ø§Ù„Ù…Ø¯Ø¹ÙˆÙ…Ø©: .xlsx Ø£Ùˆ .xls Ø£Ùˆ .csv'],
    ['âœ“ Ø§Ù„Ø­Ø¯ Ø§Ù„Ø£Ù‚ØµÙ‰: 1000 ØµÙ ÙÙŠ Ø§Ù„Ù…Ù„Ù Ø§Ù„ÙˆØ§Ø­Ø¯'],
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

// Ø§Ù„Ø¹ÙˆØ¯Ø© Ø¥Ù„Ù‰ Ø§Ù„ÙˆØ±Ù‚Ø© Ø§Ù„Ø£ÙˆÙ„Ù‰
$spreadsheet->setActiveSheetIndex(0);

// ØªØ­Ø¯ÙŠØ¯ Ø§Ø³Ù… Ø§Ù„Ù…Ù„Ù
$filename = 'Ù†Ù…ÙˆØ°Ø¬_Ø§Ø³ØªÙŠØ±Ø§Ø¯_Ø§Ù„Ù…ÙˆØ±Ø¯ÙŠÙ†_' . date('Y-m-d') . '.xlsx';

// ØªØ¹ÙŠÙŠÙ† Ø§Ù„Ù‡ÙŠØ¯Ø±Ø§Øª Ù„ØªØ­Ù…ÙŠÙ„ Ø§Ù„Ù…Ù„Ù
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Ø¥Ù†Ø´Ø§Ø¡ Ø§Ù„Ù…Ù„Ù ÙˆØ¥Ø±Ø³Ø§Ù„Ù‡ Ù„Ù„Ù…ØªØµÙØ­
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>

