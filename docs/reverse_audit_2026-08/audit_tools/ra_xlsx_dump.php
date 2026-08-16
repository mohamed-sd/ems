<?php
/**
 * ra_xlsx_dump.php — مُفرِّغُ أوراقِ XLSX إلى TSV (قراءةٌ فقط)
 * التشغيل: php ra_xlsx_dump.php <ملف.xlsx> <اسم الورقة|*> [ملف الإخراج]
 * ◆ يدعم sharedStrings وinlineStr · يفصل الخلايا بـTAB والصفوف بسطر.
 * ◆ عمودُ الخلية يُقرأ من مرجعِها (r="C5") فلا تنزاح الأعمدةُ الفارغة.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
if ($argc < 3) { fwrite(STDERR, "usage: ra_xlsx_dump.php file.xlsx sheet|* [out]\n"); exit(2); }
[$self, $file, $want] = $argv;
$outPath = $argv[3] ?? null;

$z = new ZipArchive();
if ($z->open($file) !== true) { fwrite(STDERR, "فشل فتح $file\n"); exit(2); }

$ss = [];
if (($sx = $z->getFromName('xl/sharedStrings.xml')) !== false) {
    $x = simplexml_load_string($sx);
    foreach ($x->si as $si) {
        $t = '';
        foreach ($si->xpath('.//*[local-name()="t"]') as $n) { $t .= (string) $n; }
        $ss[] = $t;
    }
}
$wb   = simplexml_load_string($z->getFromName('xl/workbook.xml'));
$rels = simplexml_load_string($z->getFromName('xl/_rels/workbook.xml.rels'));
$rmap = [];
foreach ($rels->Relationship as $r) { $rmap[(string) $r['Id']] = (string) $r['Target']; }

$colIdx = function (string $ref): int {
    preg_match('/^([A-Z]+)/', $ref, $m); $c = 0;
    foreach (str_split($m[1]) as $ch) { $c = $c * 26 + (ord($ch) - 64); }
    return $c - 1;
};

$out = $outPath ? fopen($outPath, 'w') : STDOUT;
foreach ($wb->sheets->sheet as $s) {
    $name = (string) $s['name'];
    if ($want !== '*' && trim($name) !== trim($want)) { continue; }
    $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
    $t = ltrim($rmap[$rid], '/');
    if (strpos($t, 'xl/') !== 0) { $t = 'xl/' . $t; }
    $sh = simplexml_load_string($z->getFromName($t));
    if ($want === '*') { fwrite($out, "### SHEET: $name\n"); }
    foreach ($sh->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $v = (string) $c->v;
            $type = (string) $c['t'];
            if ($type === 's') { $v = $ss[(int) $v] ?? ''; }
            if ($type === 'inlineStr') {
                $v = '';
                foreach ($c->xpath('.//*[local-name()="t"]') as $n) { $v .= (string) $n; }
            }
            $cells[$colIdx((string) $c['r'])] = str_replace(["\t", "\r", "\n"], [' ', '', ' ⏎ '], $v);
        }
        if (!$cells) { continue; }
        $max = max(array_keys($cells));
        $line = [];
        for ($i = 0; $i <= $max; $i++) { $line[] = $cells[$i] ?? ''; }
        $txt = implode("\t", $line);
        if (trim($txt) !== '') { fwrite($out, $txt . "\n"); }
    }
}
if ($outPath) { fclose($out); echo "كُتب: $outPath\n"; }
