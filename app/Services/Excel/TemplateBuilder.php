<?php
/**
 * TemplateBuilder — بناء نموذج Excel جاهز للاستيراد من تعريف الكيان.
 *
 * ينتج مصنّفاً يحوي ورقتين: «بيانات» (رؤوس + أمثلة) و«التعليمات».
 *
 * @package App\Services\Excel
 */

declare(strict_types=1);

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class TemplateBuilder
{
    public static function build(EntityDefinition $def): Spreadsheet
    {
        $columns = $def->importColumns();
        $spreadsheet = Styler::newSpreadsheet('نموذج استيراد ' . $def->title, $def->title);
        $sheet = $spreadsheet->getActiveSheet();

        // عرض الأعمدة + الرؤوس.
        Styler::columnWidths($sheet, array_map(static function (Column $c) { return $c->width; }, $columns));
        Styler::headerRow($sheet, 1, array_map(static function (Column $c) { return $c->templateHeader(); }, $columns));

        // ثلاثة صفوف أمثلة.
        $exampleRows = self::exampleRows($columns, 3);
        $r = 2;
        foreach ($exampleRows as $exRow) {
            $c = 1;
            foreach ($exRow as $val) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($c) . $r, $val);
                $c++;
            }
            Styler::dataRow($sheet, $r, count($columns), ($r % 2 === 0));
            $r++;
        }
        Styler::freeze($sheet, 1);

        self::instructionsSheet($spreadsheet, $def);
        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }

    /** @return array[] */
    private static function exampleRows(array $columns, int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $row = [];
            foreach ($columns as $c) {
                /** @var Column $c */
                if ($c->type === Column::TYPE_ENUM && $c->enum) {
                    $row[] = $c->enum[0];
                } elseif ($c->example !== null) {
                    // إلحاق رقم تسلسلي بالأمثلة الفريدة لتجنّب تعارض في صفوف الأمثلة.
                    $row[] = ($c->unique && $i > 0) ? $c->example . '-' . ($i + 1) : $c->example;
                } else {
                    $row[] = '';
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private static function instructionsSheet(Spreadsheet $spreadsheet, EntityDefinition $def): void
    {
        $guide = $spreadsheet->createSheet();
        $guide->setTitle('التعليمات');
        $guide->setRightToLeft(true);
        $guide->getColumnDimension('A')->setWidth(30);
        $guide->getColumnDimension('B')->setWidth(80);

        Styler::titleRow($guide, 1, 2, 'تعليمات استيراد ' . $def->title . ' — نظام EMS');

        $headerStyle = [
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => Styler::HEADER_TEXT], 'name' => 'Arial'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => Styler::HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => Styler::BRAND_AMBER]]],
        ];
        $cellStyle = [
            'font'      => ['size' => 10, 'name' => 'Arial'],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_RIGHT],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => Styler::BORDER]]],
        ];

        $row = 3;
        $guide->setCellValue("A{$row}", 'العمود');
        $guide->setCellValue("B{$row}", 'الوصف والقيم المقبولة');
        $guide->getStyle("A{$row}:B{$row}")->applyFromArray($headerStyle);
        $row++;

        foreach ($def->importColumns() as $c) {
            /** @var Column $c */
            $desc = [];
            $desc[] = $c->required ? 'مطلوب' : 'اختياري';
            if ($c->unique) {
                $desc[] = 'فريد';
            }
            $desc[] = self::typeLabel($c);
            if ($c->type === Column::TYPE_ENUM && $c->enum) {
                $desc[] = 'القيم: ' . implode(' / ', $c->enum);
            }
            if ($c->example !== null && $c->example !== '') {
                $desc[] = 'مثال: ' . $c->example;
            }
            if ($c->hint) {
                $desc[] = $c->hint;
            }
            $guide->setCellValue("A{$row}", $c->label);
            $guide->setCellValue("B{$row}", implode(' — ', $desc));
            $guide->getStyle("A{$row}:B{$row}")->applyFromArray($cellStyle);
            $guide->getRowDimension($row)->setRowHeight(26);
            $row++;
        }

        $notes = array_merge([
            'احذف صفوف الأمثلة قبل رفع الملف.',
            'الصيغ المدعومة: xlsx و xls و csv.',
            'الحد الأقصى للصفوف في الاستيراد الواحد: ' . $def->maxRows . ' صف.',
            'يتم استيراد الصفوف الصحيحة فقط، وتُعرض الأخطاء قبل الحفظ.',
        ], $def->instructions);

        $row++;
        $guide->setCellValue("A{$row}", 'ملاحظات مهمة');
        $guide->mergeCells("A{$row}:B{$row}");
        $guide->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => Styler::TITLE_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => Styler::ROW_ALT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $row++;
        $n = 1;
        foreach ($notes as $note) {
            $guide->setCellValue("A{$row}", $n . '.');
            $guide->setCellValue("B{$row}", $note);
            $guide->getStyle("A{$row}:B{$row}")->applyFromArray($cellStyle);
            $guide->getRowDimension($row)->setRowHeight(24);
            $row++;
            $n++;
        }
    }

    private static function typeLabel(Column $c): string
    {
        switch ($c->type) {
            case Column::TYPE_INT:   return 'رقم صحيح';
            case Column::TYPE_FLOAT: return 'رقم عشري';
            case Column::TYPE_DATE:  return 'تاريخ (YYYY-MM-DD)';
            case Column::TYPE_EMAIL: return 'بريد إلكتروني';
            case Column::TYPE_PHONE: return 'رقم هاتف';
            case Column::TYPE_ENUM:  return 'قيمة من قائمة';
            default:                 return 'نص';
        }
    }
}
