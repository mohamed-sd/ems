<?php
/**
 * Styler — التنسيق الموحّد لملفات Excel وفق الهوية البصرية للنظام.
 *
 * الهوية: ذهبي/كهرماني (#F2AA2A) على أسود فحمي (#0F1115) — متطابقة مع
 * design-tokens.css. كل تنسيق احترافي (رؤوس، تجميد، RTL، حدود، تواريخ) يمرّ هنا.
 *
 * @package App\Services\Excel
 */

declare(strict_types=1);

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class Styler
{
    // ── ألوان الهوية البصرية ──
    public const BRAND_BLACK   = '0F1115'; // خلفية العنوان
    public const BRAND_AMBER   = 'F2AA2A'; // العلامة الذهبية
    public const HEADER_BG      = '1C1917'; // رؤوس الأعمدة (رمادي داكن جداً)
    public const HEADER_TEXT    = 'FFFFFF';
    public const TITLE_TEXT     = '0F1115';
    public const ROW_ALT        = 'FFF5E8'; // كريمي فاتح (--brand-cream-light)
    public const ROW_BASE       = 'FFFFFF';
    public const BORDER         = 'E7E5E4';
    public const TOTAL_BG       = 'F2AA2A';
    public const STATUS_OK_BG    = 'ECF1DE';
    public const STATUS_OK_TX    = '365314';
    public const STATUS_OFF_BG   = 'F5DCDC';
    public const STATUS_OFF_TX   = '7F1D1D';

    /** إنشاء مصنّف جديد مهيّأ بخصائص المستند + ورقة RTL. */
    public static function newSpreadsheet(string $title, string $sheetTitle): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('EMS — Equipation')
            ->setTitle($title)
            ->setDescription($title . ' — ' . date('Y-m-d H:i'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::safeSheetTitle($sheetTitle));
        $sheet->setRightToLeft(true);
        return $spreadsheet;
    }

    /** صف العنوان الكبير الممتد عبر كل الأعمدة. */
    public static function titleRow(Worksheet $sheet, int $row, int $colCount, string $text): void
    {
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", $text);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::BRAND_AMBER], 'name' => 'Arial'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BRAND_BLACK]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(36);
    }

    /** صف رؤوس الأعمدة. */
    public static function headerRow(Worksheet $sheet, int $row, array $headers): void
    {
        $colCount = count($headers);
        $col = 1;
        foreach ($headers as $h) {
            $cell = Coordinate::stringFromColumnIndex($col) . $row;
            $sheet->setCellValue($cell, $h);
            $col++;
        }
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::HEADER_TEXT], 'name' => 'Arial'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BRAND_AMBER]]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(30);
    }

    /** تطبيق نمط على صف بيانات (مع تلوين تبادلي). */
    public static function dataRow(Worksheet $sheet, int $row, int $colCount, bool $alt): void
    {
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $fill = $alt ? self::ROW_ALT : self::ROW_BASE;
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font'      => ['size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '1C1917']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    /** تلوين خلية الحالة (نشط/متوقف). */
    public static function statusCell(Worksheet $sheet, string $cell, string $value): void
    {
        $active = in_array($value, ['نشط', 'active', 'متاحة للعمل'], true);
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $active ? self::STATUS_OK_TX : self::STATUS_OFF_TX]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $active ? self::STATUS_OK_BG : self::STATUS_OFF_BG]],
        ]);
    }

    /** صف الإجمالي بلون الهوية. */
    public static function totalRow(Worksheet $sheet, int $row, int $colCount, string $text): void
    {
        $lastCol = Coordinate::stringFromColumnIndex($colCount);
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", $text);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::BRAND_BLACK], 'name' => 'Arial'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::TOTAL_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(28);
    }

    /** ضبط عرض الأعمدة. */
    public static function columnWidths(Worksheet $sheet, array $widths): void
    {
        $col = 1;
        foreach ($widths as $w) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth((float) $w);
            $col++;
        }
    }

    /** تجميد الصفوف العلوية (الرأس). */
    public static function freeze(Worksheet $sheet, int $belowRow): void
    {
        $sheet->freezePane('A' . ($belowRow + 1));
    }

    /** اسم ورقة صالح (≤ 31 حرفاً، بدون رموز ممنوعة). */
    public static function safeSheetTitle(string $title): string
    {
        $title = str_replace(['\\', '/', '?', '*', ':', '[', ']'], ' ', $title);
        return mb_substr(trim($title), 0, 31);
    }
}
