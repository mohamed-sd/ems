<?php
/** tools/repair01_docx_read.php — استخراجُ نصِّ docx بلا مكتبة */
$path = $argv[1];
$z = new ZipArchive();
if ($z->open($path) !== true) { fwrite(STDERR, "cannot open\n"); exit(1); }
$xml = $z->getFromName('word/document.xml');
$z->close();
$d = new DOMDocument();
$d->loadXML($xml);
$W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
$out = array();
foreach ($d->getElementsByTagNameNS($W, 'p') as $p) {
    $t = '';
    foreach ($p->getElementsByTagNameNS($W, 't') as $n) { $t .= $n->textContent; }
    // نمطُ الفقرة (عنوان؟)
    $style = '';
    foreach ($p->getElementsByTagNameNS($W, 'pStyle') as $s) { $style = $s->getAttributeNS($W, 'val'); }
    $t = trim($t);
    if ($t === '') continue;
    $out[] = ($style && stripos($style, 'Head') !== false ? "\n### [$style] " : '') . $t;
}
// الجداول
$tables = $d->getElementsByTagNameNS($W, 'tbl')->length;
echo implode("\n", $out);
echo "\n\n[[ فقرات: " . count($out) . " · جداول: $tables ]]\n";
