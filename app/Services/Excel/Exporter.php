<?php
/**
 * Exporter — تصدير بيانات كيان إلى ملف Excel منسّق بالهوية البصرية.
 *
 * يستقبل صفوف بيانات (مجلوبة ومُنطَّقة مسبقاً) ويبني مصنّفاً جاهزاً للبثّ.
 *
 * @package App\Services\Excel
 */

declare(strict_types=1);

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class Exporter
{
    /**
     * @param EntityDefinition $def
     * @param array[]          $rows  صفوف مفتاحها اسم الحقل (field => value).
     */
    public static function build(EntityDefinition $def, array $rows): Spreadsheet
    {
        $columns = $def->exportColumns();
        $colCount = count($columns);

        $spreadsheet = Styler::newSpreadsheet('قائمة ' . $def->title, $def->title);
        $sheet = $spreadsheet->getActiveSheet();

        Styler::columnWidths($sheet, array_map(static function (Column $c) { return $c->width; }, $columns));

        // صف العنوان + صف الرؤوس.
        Styler::titleRow($sheet, 1, $colCount, 'قائمة ' . $def->title . ' — تاريخ التصدير: ' . date('Y-m-d H:i'));
        Styler::headerRow($sheet, 2, array_map(static function (Column $c) { return $c->label; }, $columns));
        Styler::freeze($sheet, 2);

        // صفوف البيانات.
        $rowNum = 3;
        $statusFieldIndex = null;
        foreach ($columns as $i => $c) {
            if ($c->type === Column::TYPE_ENUM || $c->field === 'status') {
                $statusFieldIndex = $i;
            }
        }

        foreach ($rows as $data) {
            $col = 1;
            foreach ($columns as $c) {
                $val = $data[$c->field] ?? '';
                $cell = Coordinate::stringFromColumnIndex($col) . $rowNum;
                // الأرقام تُكتب كأرقام، والنصوص كنص صريح لتفادي تحويل Excel.
                if (in_array($c->type, [Column::TYPE_INT, Column::TYPE_FLOAT], true) && is_numeric($val)) {
                    $sheet->setCellValue($cell, $val + 0);
                } else {
                    $sheet->setCellValueExplicit(
                        $cell,
                        (string) $val,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                }
                $col++;
            }
            Styler::dataRow($sheet, $rowNum, $colCount, ($rowNum % 2 === 1));

            if ($statusFieldIndex !== null) {
                $statusCol = Coordinate::stringFromColumnIndex($statusFieldIndex + 1);
                $statusVal = (string) ($data[$columns[$statusFieldIndex]->field] ?? '');
                if ($statusVal !== '') {
                    Styler::statusCell($sheet, $statusCol . $rowNum, $statusVal);
                }
            }
            $rowNum++;
        }

        if (count($rows) > 0) {
            Styler::totalRow($sheet, $rowNum, $colCount, 'الإجمالي: ' . count($rows) . ' ' . $def->title);
        } else {
            Styler::totalRow($sheet, $rowNum, $colCount, 'لا توجد بيانات للتصدير');
        }

        return $spreadsheet;
    }
}
