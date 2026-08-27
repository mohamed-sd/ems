<?php
/**
 * tools/lib/xlsx_out.php — كاتبُ مصنَّفٍ من العدم (‏بلا مكتبةٍ خارجية)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **و`xlsx_write` القائمةُ تعدّل خلايا في مكانِها ولا تُنشئ مصنَّفًا** — فهذه
 *   تكمّلها ولا تحلّ محلَّها.
 *
 * ◆ **كلُّ خليّةٍ `inlineStr`**: لا `sharedStrings` ولا `styles` — فالمصنَّفُ
 *   **إسقاطُ بياناتٍ لا مستندٌ مُنسَّق**، والتنسيقُ يُغري بتحريرِه يدويًّا.
 *
 * الاستعمال:
 *   xlsx_create($path, array('اسم الورقة' => array(array('ع1','ع2'), array('ق1','ق2'))));
 * ═══════════════════════════════════════════════════════════════════════════
 */

/** اسمُ ورقةٍ صالحٌ: ≤31 حرفًا وبلا الرموزِ الممنوعةِ في OOXML. */
function xlsx_sheet_name($name)
{
    $n = str_replace(array('[', ']', ':', '*', '?', '/', '\\'), ' ', (string) $name);
    $n = trim(preg_replace('~\s+~u', ' ', $n));
    if ($n === '') { $n = 'ورقة'; }
    return mb_substr($n, 0, 31);
}

/** حرفُ العمودِ من رقمِه الصفريّ. */
function xlsx_out_col($i)
{
    $s = ''; $i++;
    while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = (int) (($i - $m) / 26); }
    return $s;
}

/**
 * يُنشئ مصنَّفًا كاملًا. `$sheets` = array(sheetName => array(row => array(cell,…)))
 * ⛔ **ولا يكتب فوق ملفٍّ إلّا بالسماحِ الصريح** — والكتابةُ فوق مصدرٍ مجمَّدٍ
 *   تمحو دليلَ الدخول.
 */
function xlsx_create($path, array $sheets, $overwrite = true)
{
    if (!$overwrite && is_file($path)) { throw new RuntimeException('الملفُّ قائمٌ ولا يُكتب فوقه: ' . $path); }
    if (!$sheets) { throw new RuntimeException('لا ورقةَ واحدةَ — ومصنَّفٌ بلا ورقةٍ لا يُفتح'); }

    $names = array(); $i = 0;
    foreach ($sheets as $n => $_) {
        $nm = xlsx_sheet_name($n);
        while (in_array($nm, $names, true)) { $nm = mb_substr($nm, 0, 28) . '_' . (++$i); }
        $names[] = $nm;
    }

    $ct  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
         . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
         . '<Default Extension="xml" ContentType="application/xml"/>'
         . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    $wb  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
         . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $wr  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $parts = array();

    $k = 0;
    foreach ($sheets as $orig => $rows) {
        $k++;
        $file = 'xl/worksheets/sheet' . $k . '.xml';
        $ct  .= '<Override PartName="/' . $file . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wb  .= '<sheet name="' . htmlspecialchars($names[$k - 1], ENT_QUOTES | ENT_XML1, 'UTF-8')
              . '" sheetId="' . $k . '" r:id="rId' . $k . '"/>';
        $wr  .= '<Relationship Id="rId' . $k . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $k . '.xml"/>';

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $rn = 0;
        foreach ((array) $rows as $row) {
            $rn++;
            $xml .= '<row r="' . $rn . '">';
            $cn = 0;
            foreach ((array) $row as $cell) {
                $ref = xlsx_out_col($cn) . $rn; $cn++;
                $v = (string) $cell;
                if ($v === '') { continue; }
                /* رموزُ التحكُّمِ تُكسِر ملفَّ XML فتُنزع قبل الكتابة */
                $v = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~u', ' ', $v);
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                      . htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $parts[$file] = $xml;
    }

    $ct .= '</Types>';
    $wb .= '</sheets></workbook>';
    $wr .= '</Relationships>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
          . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
          . '<Relationship Id="rIdWb" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
          . '</Relationships>';

    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) { throw new RuntimeException('تعذّر إنشاءُ المجلَّد: ' . $dir); }

    $tmp = $path . '.tmp';
    $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('تعذّر إنشاءُ الأرشيف');
    }
    $z->addFromString('[Content_Types].xml', $ct);
    $z->addFromString('_rels/.rels', $rels);
    $z->addFromString('xl/workbook.xml', $wb);
    $z->addFromString('xl/_rels/workbook.xml.rels', $wr);
    foreach ($parts as $n => $d) { $z->addFromString($n, $d); }
    $z->close();
    if (!@rename($tmp, $path)) { @copy($tmp, $path); @unlink($tmp); }
    return count($sheets);
}
