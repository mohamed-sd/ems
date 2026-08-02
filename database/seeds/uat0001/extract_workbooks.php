<?php
/**
 * UAT-0001 · المرحلة أ — استخراج المصنفات التسعة إلى JSON مُطبَّع.
 *
 * يقرأ xlsx مباشرةً (ZipArchive + XMLReader) بلا PhpSpreadsheet — أسرع بمرّات
 * على مصنفاتٍ فيها آلافُ الصفوف، ويكفينا لأننا نريد القيم لا التنسيق.
 *
 * المخرج: storage/uat_import/<كتلة>__<ورقة>.json  +  _manifest.json
 * التشغيل: php database/seeds/uat0001/extract_workbooks.php [مجلد المصنفات]
 */

$srcDir = $argv[1] ?? 'C:/Users/User/Downloads';
$outDir = dirname(__DIR__, 3) . '/storage/uat_import';
if (!is_dir($outDir) && !@mkdir($outDir, 0777, true)) {
    fwrite(STDERR, "تعذّر إنشاء $outDir\n");
    exit(1);
}

/** الكتل التسع: البادئة => اسم الملف. */
$books = [
    'ب' => 'ب_المبيعات_والعقود_النهائي-1.xlsx',
    'أ' => 'أ_الأصول_والأسطول_النهائي-4.xlsx',
    'ف' => 'الأسطول_الكامل_وملاكه_النهائي-3.xlsx',
    'م' => 'م_الموردون_الخارجيون_النهائي-1.xlsx',
    'ش' => 'ش_المشغلون_النهائي-1.xlsx',
    'ل' => 'ل_الممولون_والتمويل_النهائي-3.xlsx',
    'د' => 'د_القيود_والقوائم_المالية_النهائي-3.xlsx',
    'ع' => 'ع_العمليات_والقياس_النهائي-1.xlsx',
    'ق' => 'ق_القانونية_والحوكمة_النهائي-1.xlsx',
];

/** أوراقٌ مشتركةٌ مكرَّرة في كل مصنف — تُستخرج مرةً واحدةً من «ب». */
$sharedSheets = ['ر1 سلسلة الربط الكاملة', 'ر2 سجل الكيانات الموحد', 'ر3 مصفوفة المفاتيح', 'القوائم المرجعية', 'مراجع العقود', 'مراجع الحاويات'];

// ── أدوات ────────────────────────────────────────────────────────────────────

function colIdx($ref)
{
    $c = 0;
    for ($i = 0, $L = strlen($ref); $i < $L; $i++) {
        $ch = $ref[$i];
        if ($ch < 'A' || $ch > 'Z') break;
        $c = $c * 26 + (ord($ch) - 64);
    }
    return $c - 1;
}

/** تسلسل إكسل إلى Y-m-d (نظام 1900 مع خطأ 1900 الكبيسة المعروف). */
function excelDate($serial)
{
    if (!is_numeric($serial)) return null;
    $s = (float) $serial;
    if ($s < 20000 || $s > 60000) return null;      // خارج 1954..2064 — ليس تاريخًا
    $ts = ($s - 25569) * 86400;
    return gmdate('Y-m-d', (int) $ts);
}

function isDateHeader($h)
{
    return (bool) preg_match('/تاريخ|بداية|نهاية|أول |آخر |من$|إلى$|^من$|^إلى$/u', (string) $h);
}

function readSharedStrings(ZipArchive $zip)
{
    $out = [];
    $i = $zip->locateName('xl/sharedStrings.xml');
    if ($i === false) return $out;
    $r = new XMLReader();
    $r->XML($zip->getFromIndex($i));
    $cur = null;
    while ($r->read()) {
        if ($r->nodeType === XMLReader::ELEMENT && $r->name === 'si') $cur = '';
        elseif ($r->nodeType === XMLReader::ELEMENT && $r->name === 't' && $cur !== null) $cur .= $r->readString();
        elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->name === 'si') { $out[] = $cur; $cur = null; }
    }
    $r->close();
    return $out;
}

function sheetIndex(ZipArchive $zip)
{
    $wb  = $zip->getFromName('xl/workbook.xml');
    $rel = $zip->getFromName('xl/_rels/workbook.xml.rels');
    preg_match_all('/<sheet[^>]*name="([^"]*)"[^>]*r:id="([^"]*)"/u', $wb, $m1, PREG_SET_ORDER);
    preg_match_all('/<Relationship[^>]*Id="([^"]*)"[^>]*Target="([^"]*)"/u', $rel, $m2, PREG_SET_ORDER);
    $tg = [];
    foreach ($m2 as $r) $tg[$r[1]] = ltrim(str_replace('/xl/', '', $r[2]), '/');
    $out = [];
    foreach ($m1 as $r) {
        if (!isset($tg[$r[2]])) continue;
        $out[] = ['name' => html_entity_decode($r[1], ENT_QUOTES | ENT_XML1, 'UTF-8'), 'path' => 'xl/' . $tg[$r[2]]];
    }
    return $out;
}

/** يقرأ ورقةً كاملةً كمصفوفة صفوفٍ خام (فهرس العمود => نص). */
function readSheet(ZipArchive $zip, $path, array $ss)
{
    $xml = $zip->getFromName($path);
    if ($xml === false) return [];
    $r = new XMLReader();
    $r->XML($xml);
    $rows = [];
    $row  = [];
    while ($r->read()) {
        if ($r->nodeType === XMLReader::ELEMENT && $r->name === 'row') {
            $row = [];
        } elseif ($r->nodeType === XMLReader::ELEMENT && $r->name === 'c') {
            $ref  = (string) $r->getAttribute('r');
            $t    = $r->getAttribute('t');
            $node = $r->readOuterXml();
            $val  = '';
            if (preg_match('#<v>(.*?)</v>#su', $node, $mv))       $val = $mv[1];
            elseif (preg_match('#<t[^>]*>(.*?)</t>#su', $node, $mt)) $val = $mt[1];
            if ($t === 's' && $val !== '') $val = $ss[(int) $val] ?? '';
            $val = html_entity_decode((string) $val, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $row[colIdx($ref)] = trim(preg_replace('/\s+/u', ' ', $val));
        } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->name === 'row') {
            $rows[] = $row;
        }
    }
    $r->close();
    return $rows;
}

/** صفُّ العناوين = أكثرُ الصفوف الخمسة الأولى خلايا — يميّز العنوان عن أشرطة التجميع. */
function headerRowIndex(array $rows)
{
    $best = 0; $bestN = -1;
    for ($i = 0; $i < min(5, count($rows)); $i++) {
        $n = count(array_filter($rows[$i], fn($v) => $v !== ''));
        if ($n > $bestN) { $bestN = $n; $best = $i; }
    }
    return $best;
}

// ── التنفيذ ──────────────────────────────────────────────────────────────────

$manifest = ['generated_at' => date('c'), 'source_dir' => $srcDir, 'books' => []];
$seenShared = [];

foreach ($books as $block => $fname) {
    $path = rtrim($srcDir, '/\\') . '/' . $fname;
    if (!is_file($path)) { fwrite(STDERR, "⚠ مفقود: $fname\n"); continue; }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) { fwrite(STDERR, "⚠ تعذّر فتح: $fname\n"); continue; }

    $ss = readSharedStrings($zip);
    echo "\n══ الكتلة $block · $fname\n";
    $manifest['books'][$block] = ['file' => $fname, 'sheets' => []];

    foreach (sheetIndex($zip) as $sh) {
        $name = $sh['name'];
        if (in_array($name, $sharedSheets, true)) {
            if (isset($seenShared[$name])) continue;          // مرةً واحدةً فقط
            $seenShared[$name] = true;
            $tag = 'ر';
        } else {
            $tag = $block;
        }
        if (strpos($name, 'ت00') === 0) continue;             // الفهرس والدليل — وصفٌ لا بيانات

        $rows = readSheet($zip, $sh['path'], $ss);
        if (!$rows) continue;
        $hi   = headerRowIndex($rows);
        $head = $rows[$hi];
        ksort($head);

        $data = [];
        for ($i = $hi + 1, $N = count($rows); $i < $N; $i++) {
            $raw = $rows[$i];
            if (!array_filter($raw, fn($v) => $v !== '')) continue;
            $rec = [];
            foreach ($head as $ci => $h) {
                if ($h === '') continue;
                $v = $raw[$ci] ?? '';
                if ($v !== '' && isDateHeader($h) && is_numeric($v) && ($d = excelDate($v)) !== null) $v = $d;
                if (isset($rec[$h])) {                        // عنوانٌ مكرَّر — لا يُطمَس
                    $k = $h; $n = 2;
                    while (isset($rec[$k])) { $k = $h . ' (' . $n . ')'; $n++; }
                    $rec[$k] = $v;
                } else {
                    $rec[$h] = $v;
                }
            }
            if (array_filter($rec, fn($v) => $v !== '')) $data[] = $rec;
        }

        $slug = preg_replace('/[^\p{Arabic}\p{L}\p{N}]+/u', '_', $name);
        $file = $outDir . '/' . $tag . '__' . $slug . '.json';
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $state = count($data) === 0 ? 'قالبٌ فارغ' : 'مملوءة';
        $manifest['books'][$block]['sheets'][$name] = [
            'rows'    => count($data),
            'columns' => count(array_filter($head, fn($v) => $v !== '')),
            'state'   => $state,
            'json'    => basename($file),
        ];
        printf("   %-42s %6d صفًّا · %3d عمودًا · %s\n", mb_substr($name, 0, 40), count($data), count(array_filter($head, fn($v) => $v !== '')), $state);
    }
    $zip->close();
}

file_put_contents($outDir . '/_manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n✔ المخرج في: $outDir\n";
