<?php
/**
 * Export Handler - معالج التصدير إلى Excel و PDF
 * @version 1.0
 * @author EMS System
 */

require_once __DIR__ . '/functions.php';

// ═══════════════════════════════════════════════════════════════════════════
// تصدير إلى Excel باستخدام PHPSpreadsheet
// ═══════════════════════════════════════════════════════════════════════════

if (!function_exists('exportToExcel')) {
    function exportToExcel($filename, $sheetTitle, $headers, $data) {
        try {
            require __DIR__ . '/../../vendor/autoload.php';
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($sheetTitle);
            
            // Set RTL direction for Arabic
            $sheet->setRightToLeft(true);
            
            // إضافة رؤوس الأعمدة
            $col = 1;
            foreach ($headers as $header) {
                $cell = $sheet->getCellByColumnAndRow($col, 1);
                $cell->setValue($header);
                $cell->getFont()->setBold(true);
                $cell->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
                $cell->getFill()->getStartColor()->setARGB('FF4472C4');
                $cell->getFont()->getColor()->setARGB('FFFFFFFF');
                $col++;
            }
            
            // إضافة البيانات
            $row = 2;
            foreach ($data as $dataRow) {
                $col = 1;
                foreach ($dataRow as $value) {
                    $sheet->getCellByColumnAndRow($col, $row)->setValue($value);
                    $col++;
                }
                $row++;
            }
            
            // ضبط عرض الأعمدة
            foreach (range(1, count($headers)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }
            
            // إنشاء ملف Excel
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'خطأ في التصدير: ' . $e->getMessage());
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// تصدير إلى PDF
// ═══════════════════════════════════════════════════════════════════════════

if (!function_exists('exportToPDF')) {
    function exportToPDF($filename, $title, $htmlContent) {
        try {
            require __DIR__ . '/../../vendor/autoload.php';
            
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font' => 'Arial',
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_left' => 10,
                'margin_right' => 10
            ]);
            
            // تعيين اتجاه RTL
            $mpdf->SetDirectionality('rtl');
            
            // إضافة محتوى الصفحة
            $html = '<html dir="rtl"><head><meta charset="UTF-8"></head><body>';
            $html .= '<h1 style="text-align: center; color: #4472C4; margin-bottom: 20px;">' . htmlspecialchars($title) . '</h1>';
            $html .= $htmlContent;
            $html .= '<p style="text-align: center; color: #666; margin-top: 30px; font-size: 10px;">تم إنشاء التقرير بواسطة نظام إدارة المعدات</p>';
            $html .= '</body></html>';
            
            $mpdf->WriteHTML($html);
            $mpdf->Output($filename . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
            exit;
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'خطأ في التصدير: ' . $e->getMessage());
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// إنشاء جدول HTML للتصدير
// ═══════════════════════════════════════════════════════════════════════════

if (!function_exists('createHTMLTable')) {
    function createHTMLTable($headers, $data) {
        $html = '<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse; font-family: Arial; font-size: 11px;">';
        
        // رؤوس الجدول
        $html .= '<thead>';
        $html .= '<tr style="background-color: #4472C4; color: white; font-weight: bold;">';
        foreach ($headers as $header) {
            $html .= '<th style="text-align: right; padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr>';
        $html .= '</thead>';
        
        // بيانات الجدول
        $html .= '<tbody>';
        $rowCount = 0;
        foreach ($data as $row) {
            $rowCount++;
            $bgColor = ($rowCount % 2 == 0) ? '#f9f9f9' : '#ffffff';
            $html .= '<tr style="background-color: ' . $bgColor . ';">';
            foreach ($row as $value) {
                $html .= '<td style="text-align: right; padding: 6px; border: 1px solid #ddd;">' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        
        $html .= '</table>';
        return $html;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// معالج طلبات التصدير
// ═══════════════════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['export_action'])) {
    
    $exportAction = $_POST['export_action'];
    $reportCode = isset($_POST['report_code']) ? $_POST['report_code'] : '';
    
    // يجب تنفيذ التصدير في صفحة التقرير نفسها
    // هذا مجرد هيكل للعمل معه
}
