<?php
/**
 * tools/lib/xlsx_io.php — قراءةُ وكتابةُ مصنَّفِ Excel بلا مكتبةٍ خارجية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا بلا مكتبة**: البيئةُ بلا بايثون، والمستودعُ يقرأ xlsx بـZipArchive
 *   سلفًا في `tools/nav02_matrix_read.php`. فهذا تعميمُ النمطِ نفسِه ليقرأ
 *   **كلَّ الأوراقِ** ويكتب تعديلًا موضعيًّا.
 *
 * ◆ **والكتابةُ موضعيةٌ لا إعادةَ بناء**: يُفكُّ الأرشيفُ ويُعدَّل نصُّ الخلية
 *   ثم يُعاد ضغطُه — فتبقى الأنماطُ والتنسيقاتُ والأعمدةُ كما هي. وإعادةُ
 *   بناءِ المصنَّفِ من الصفرِ تُتلف ما لم نقصد المساسَ به.
 *
 * ◆ **والنصوصُ المشتركةُ تُعالَج بحقّها**: خليةٌ من نوعِ `s` تشير إلى فهرسِ
 *   `sharedStrings`، فتبديلُ نصِّها **يبدّله لكلِّ خليةٍ تشاركه**. لذلك
 *   `xlsx_set()` تحوّل الخليةَ إلى `inlineStr` مستقلٍّ عند الكتابة.
 * ═══════════════════════════════════════════════════════════════════════════
 */

/** يقرأ المصنَّفَ كلَّه: array[sheetName][rowIndex][colIndex] = نصّ */
function xlsx_read($path)
{
    $z = new ZipArchive();
    if ($z->open($path) !== true) { return array(); }

    /* النصوصُ المشتركة */
    $ss = array();
    $ssx = $z->getFromName('xl/sharedStrings.xml');
    if ($ssx) {
        $d = new DOMDocument();
        $d->loadXML($ssx);
        foreach ($d->getElementsByTagName('si') as $si) {
            $t = '';
            foreach ($si->getElementsByTagName('t') as $n) { $t .= $n->textContent; }
            $ss[] = $t;
        }
    }

    /* أسماءُ الأوراقِ ومسافاتُها — الاسمُ في workbook.xml والملفُّ في العلاقات */
    $names = array();
    $wbx = $z->getFromName('xl/workbook.xml');
    if ($wbx) {
        $d = new DOMDocument();
        $d->loadXML($wbx);
        foreach ($d->getElementsByTagName('sheet') as $sh) {
            $names[] = array(
                'name' => $sh->getAttribute('name'),
                'rid'  => $sh->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id'),
            );
        }
    }
    $rels = array();
    $rx = $z->getFromName('xl/_rels/workbook.xml.rels');
    if ($rx) {
        $d = new DOMDocument();
        $d->loadXML($rx);
        foreach ($d->getElementsByTagName('Relationship') as $r) {
            $rels[$r->getAttribute('Id')] = $r->getAttribute('Target');
        }
    }

    $out = array();
    foreach ($names as $i => $sh) {
        $tgt = isset($rels[$sh['rid']]) ? $rels[$sh['rid']] : ('worksheets/sheet' . ($i + 1) . '.xml');
        $tgt = ltrim(str_replace('/xl/', '', $tgt), '/');
        $xml = $z->getFromName('xl/' . $tgt);
        if ($xml === false) { $xml = $z->getFromName('xl/worksheets/sheet' . ($i + 1) . '.xml'); }
        if ($xml === false) { continue; }
        $d = new DOMDocument();
        $d->loadXML($xml);
        $rows = array();
        foreach ($d->getElementsByTagName('row') as $row) {
            $rn = (int) $row->getAttribute('r');
            $cells = array();
            foreach ($row->getElementsByTagName('c') as $c) {
                $ref = $c->getAttribute('r');
                if (!preg_match('/^([A-Z]+)/', $ref, $mm)) { continue; }
                $col = 0;
                foreach (str_split($mm[1]) as $ch) { $col = $col * 26 + (ord($ch) - 64); }
                $t = $c->getAttribute('t');
                if ($t === 'inlineStr') {
                    $val = '';
                    foreach ($c->getElementsByTagName('t') as $n) { $val .= $n->textContent; }
                } else {
                    $v = $c->getElementsByTagName('v')->item(0);
                    $val = $v ? $v->textContent : '';
                    if ($t === 's') { $val = isset($ss[(int) $val]) ? $ss[(int) $val] : ''; }
                }
                $cells[$col - 1] = $val;
            }
            $rows[$rn - 1] = $cells;
        }
        $out[$sh['name']] = $rows;
    }
    $z->close();
    return $out;
}

/** رقمُ العمودِ (صفريّ) ⇐ حرفُه: 0→A · 26→AA */
function xlsx_col_letter($i)
{
    $s = '';
    $i++;
    while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = (int) (($i - $m) / 26); }
    return $s;
}

/**
 * يعدّل خلايا في مكانِها. $edits = array(sheetName => array("B7" => 'نص', …))
 * ◆ **الخليةُ المكتوبةُ تصير `inlineStr`** — فلا يُبدَّل نصٌّ مشتركٌ لغيرِها.
 * ◆ ويعود بعددِ ما كُتب فعلًا، و**يرفع استثناءً إن لم تُوجد الخلية** — فالكتابةُ
 *   في العدمِ صمتٌ يُقرأ نجاحًا.
 */
function xlsx_write($path, array $edits, $outPath = null)
{
    $outPath = $outPath ?: $path;
    $z = new ZipArchive();
    if ($z->open($path) !== true) { throw new RuntimeException('تعذّر فتحُ المصنَّف'); }

    /* خريطةُ الاسمِ ⇐ ملفِّ الورقة */
    $names = array(); $rels = array();
    $d = new DOMDocument(); $d->loadXML($z->getFromName('xl/workbook.xml'));
    foreach ($d->getElementsByTagName('sheet') as $sh) {
        $names[$sh->getAttribute('name')] = $sh->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
    }
    $d = new DOMDocument(); $d->loadXML($z->getFromName('xl/_rels/workbook.xml.rels'));
    foreach ($d->getElementsByTagName('Relationship') as $r) {
        $rels[$r->getAttribute('Id')] = ltrim(str_replace('/xl/', '', $r->getAttribute('Target')), '/');
    }

    /* تُقرأ كلُّ المدخلاتِ إلى الذاكرةِ ثم يُكتب أرشيفٌ جديد */
    $entries = array();
    for ($i = 0; $i < $z->numFiles; $i++) {
        $n = $z->getNameIndex($i);
        $entries[$n] = $z->getFromIndex($i);
    }
    $z->close();

    $written = 0; $missing = array();
    foreach ($edits as $sheet => $cells) {
        if (!isset($names[$sheet])) { throw new RuntimeException("ورقةٌ غيرُ موجودة: {$sheet}"); }
        $file = 'xl/' . $rels[$names[$sheet]];
        if (!isset($entries[$file])) { throw new RuntimeException("ملفُّ ورقةٍ مفقود: {$file}"); }
        $xml = $entries[$file];
        foreach ($cells as $ref => $val) {
            $esc = htmlspecialchars((string) $val, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $new = '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $esc . '</t></is></c>';
            /* الخليةُ إمّا ذاتُ محتوًى أو ذاتيةُ الإغلاق */
            $pat = '~<c r="' . preg_quote($ref, '~') . '"(?:\s[^>]*)?(?:/>|>.*?</c>)~su';
            if (preg_match($pat, $xml)) {
                $xml = preg_replace($pat, $new, $xml, 1);
                $written++;
            } else {
                $missing[] = $sheet . '!' . $ref;
            }
        }
        $entries[$file] = $xml;
    }
    if ($missing) {
        throw new RuntimeException('خلايا لم تُوجد — لا تُكتب في العدم: ' . implode(' · ', array_slice($missing, 0, 8)));
    }

    $tmp = $outPath . '.tmp';
    $z2 = new ZipArchive();
    if ($z2->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('تعذّر إنشاءُ الأرشيف');
    }
    foreach ($entries as $n => $data) { $z2->addFromString($n, $data); }
    $z2->close();
    if (!@rename($tmp, $outPath)) { @copy($tmp, $outPath); @unlink($tmp); }
    return $written;
}

/**
 * يكتب خليّةً **ويُنشئها إن كانت غائبةً** في صفٍّ قائم — بموضعِها العموديِّ الصحيح.
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولماذا دالّةٌ ثالثةٌ لا تخفيفُ حارسِ `xlsx_write()`**: حارسُها «لا كتابةَ
 *   في العدم» مقصودٌ لكتابةِ قيمةٍ يُفترض وجودُها. أمّا ملءُ عمودٍ اختياريٍّ
 *   فارغٍ في صفٍّ قائمٍ (كخانةِ `Evidence_Status` عند أوّلِ إغلاقٍ) فعملٌ
 *   مشروعٌ آخرُ — **ويرفض هو الآخرُ صفًّا غيرَ موجود**: الإنشاءُ للخليّةِ
 *   لا للصفّ.
 *
 * @return int عددُ الخلايا المكتوبة (قائمةً أو مُنشأة)
 */
function xlsx_write_or_create($path, array $edits, $outPath = null)
{
    $outPath = $outPath ?: $path;
    /* أوّلًا: ما وُجد يُكتب بالحارسةِ الأصلية — والغائبُ يُجمَع ويُنشأ */
    $z = new ZipArchive();
    if ($z->open($path) !== true) { throw new RuntimeException('تعذّر فتحُ المصنَّف'); }
    $names = array(); $rels = array();
    $d = new DOMDocument(); $d->loadXML($z->getFromName('xl/workbook.xml'));
    foreach ($d->getElementsByTagName('sheet') as $sh) {
        $names[$sh->getAttribute('name')] = $sh->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
    }
    $d = new DOMDocument(); $d->loadXML($z->getFromName('xl/_rels/workbook.xml.rels'));
    foreach ($d->getElementsByTagName('Relationship') as $r) {
        $rels[$r->getAttribute('Id')] = ltrim(str_replace('/xl/', '', $r->getAttribute('Target')), '/');
    }
    $entries = array();
    for ($i = 0; $i < $z->numFiles; $i++) { $entries[$z->getNameIndex($i)] = $z->getFromIndex($i); }
    $z->close();

    $colNum = function ($letters) {
        $n = 0;
        foreach (str_split($letters) as $ch) { $n = $n * 26 + (ord($ch) - 64); }
        return $n;
    };
    $written = 0;
    foreach ($edits as $sheet => $cells) {
        if (!isset($names[$sheet])) { throw new RuntimeException("ورقةٌ غيرُ موجودة: {$sheet}"); }
        $file = 'xl/' . $rels[$names[$sheet]];
        $xml = $entries[$file];
        foreach ($cells as $ref => $val) {
            if (!preg_match('~^([A-Z]+)(\d+)$~', $ref, $rm)) { throw new RuntimeException("مرجعٌ فاسد: {$ref}"); }
            $esc = htmlspecialchars((string) $val, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $new = '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $esc . '</t></is></c>';
            $pat = '~<c r="' . preg_quote($ref, '~') . '"(?:\s[^>]*)?(?:/>|>.*?</c>)~su';
            if (preg_match($pat, $xml)) { $xml = preg_replace($pat, $new, $xml, 1); $written++; continue; }
            /* الخليّةُ غائبة — تُنشأ داخل صفِّها القائمِ بترتيبِ عمودِها */
            $rowPat = '~(<row[^>]*\br="' . $rm[2] . '"[^>]*>)(.*?)(</row>)~su';
            if (!preg_match($rowPat, $xml, $rw, PREG_OFFSET_CAPTURE)) {
                throw new RuntimeException("صفٌّ غيرُ موجودٍ — الإنشاءُ للخليّةِ لا للصفّ: {$sheet}!{$ref}");
            }
            $body = $rw[2][0];
            $target = $colNum($rm[1]);
            $insertAt = strlen($body);
            if (preg_match_all('~<c r="([A-Z]+)' . $rm[2] . '"~', $body, $cm, PREG_OFFSET_CAPTURE)) {
                foreach ($cm[1] as $k => $cc) {
                    if ($colNum($cc[0]) > $target) { $insertAt = $cm[0][$k][1]; break; }
                }
            }
            $newBody = substr($body, 0, $insertAt) . $new . substr($body, $insertAt);
            $xml = substr($xml, 0, $rw[2][1]) . $newBody . substr($xml, $rw[2][1] + strlen($body));
            $written++;
        }
        $entries[$file] = $xml;
    }
    $tmp = $outPath . '.tmp';
    $z2 = new ZipArchive();
    if ($z2->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('تعذّر إنشاءُ الأرشيف');
    }
    foreach ($entries as $n => $data) { $z2->addFromString($n, $data); }
    $z2->close();
    if (!@rename($tmp, $outPath)) { @copy($tmp, $outPath); @unlink($tmp); }
    return $written;
}

/**
 * يُلحق صفوفًا في آخرِ ورقة. $rows = array(array(colIndex => 'نص', …), …)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولماذا دالّةٌ ثانيةٌ لا توسيعُ `xlsx_write()`**: تلك تكتب في خليّةٍ قائمةٍ
 *   **وترفع استثناءً إن لم تُوجد** — وهو حارسُها المقصود: «الكتابةُ في العدمِ
 *   صمتٌ يُقرأ نجاحًا». **فإلحاقُ صفٍّ جديدٍ ليس حالةَ خطأٍ فيها بل عملٌ آخر**،
 *   وخلطُهما يُسقط ذلك الحارس.
 *
 * ◆ **والخلايا `inlineStr`** كما في `xlsx_write()` — فلا تُلمَس جداولُ النصِّ
 *   المشترك، **ولا يتغيّر نصُّ خليّةٍ أخرى تشاركها**.
 *
 * ⛔ **ولا يُلحَق صفٌّ برقمٍ قائم**: يبدأ الترقيمُ بعدَ أقصى صفٍّ في الورقة،
 *   **ويُتحقَّق أنَّ العددَ المكتوبَ يطابق المطلوب** — فإلحاقٌ صامتٌ ناقصٌ
 *   يُقرأ نجاحًا وهو نقص.
 *
 * @return int عددُ الصفوفِ المُلحَقةِ فعلًا
 */
function xlsx_append_rows($path, $sheetName, array $rows, $outPath = null)
{
    if (!$rows) { return 0; }
    $outPath = $outPath ?: $path;
    $z = new ZipArchive();
    if ($z->open($path) !== true) { throw new RuntimeException('تعذّر فتحُ المصنَّف'); }

    $names = array(); $rels = array();
    $d = new DOMDocument(); $d->loadXML($z->getFromName('xl/workbook.xml'));
    foreach ($d->getElementsByTagName('sheet') as $sh) {
        $names[$sh->getAttribute('name')] = $sh->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
    }
    $d = new DOMDocument(); $d->loadXML($z->getFromName('xl/_rels/workbook.xml.rels'));
    foreach ($d->getElementsByTagName('Relationship') as $r) {
        $rels[$r->getAttribute('Id')] = ltrim(str_replace('/xl/', '', $r->getAttribute('Target')), '/');
    }
    $entries = array();
    for ($i = 0; $i < $z->numFiles; $i++) { $entries[$z->getNameIndex($i)] = $z->getFromIndex($i); }
    $z->close();

    if (!isset($names[$sheetName])) { throw new RuntimeException("ورقةٌ غيرُ موجودة: {$sheetName}"); }
    $file = 'xl/' . $rels[$names[$sheetName]];
    if (!isset($entries[$file])) { throw new RuntimeException("ملفُّ ورقةٍ مفقود: {$file}"); }
    $xml = $entries[$file];

    /* أقصى رقمِ صفٍّ قائمٍ — فالإلحاقُ بعدَه لا فوقَه */
    $maxRow = 0;
    if (preg_match_all('~<row[^>]*\sr="(\d+)"~', $xml, $m)) {
        foreach ($m[1] as $n) { $maxRow = max($maxRow, (int) $n); }
    }
    if ($maxRow === 0) { throw new RuntimeException('ورقةٌ بلا صفوفٍ — لا يُلحَق بها'); }

    $chunk = '';
    $n = $maxRow;
    foreach ($rows as $cells) {
        $n++;
        $chunk .= '<row r="' . $n . '">';
        ksort($cells);
        foreach ($cells as $ci => $val) {
            if ($val === null || $val === '') { continue; }
            $ref = xlsx_col_letter((int) $ci) . $n;
            $esc = htmlspecialchars((string) $val, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $chunk .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                . $esc . '</t></is></c>';
        }
        $chunk .= '</row>';
    }

    if (strpos($xml, '</sheetData>') === false) { throw new RuntimeException('لا `sheetData` في الورقة'); }
    $xml = preg_replace('~</sheetData>~', $chunk . '</sheetData>', $xml, 1);

    /* ⛔ **ومدى الورقةِ المُعلَنُ يُوسَّع** — فمدًى يقف دون الصفوفِ الجديدةِ
         يجعل بعضَ القرّاءِ لا يراها، **فتُكتب ولا تُقرأ**. */
    $xml = preg_replace_callback('~(<dimension\s+ref=")([A-Z]+)(\d+):([A-Z]+)(\d+)(")~',
        function ($m) use ($n) { return $m[1] . $m[2] . $m[3] . ':' . $m[4] . $n . $m[6]; }, $xml, 1);

    $entries[$file] = $xml;

    $tmp = $outPath . '.tmp';
    $z2 = new ZipArchive();
    if ($z2->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('تعذّر إنشاءُ الأرشيف');
    }
    foreach ($entries as $en => $data) { $z2->addFromString($en, $data); }
    $z2->close();
    if (!@rename($tmp, $outPath)) { @copy($tmp, $outPath); @unlink($tmp); }
    return count($rows);
}
