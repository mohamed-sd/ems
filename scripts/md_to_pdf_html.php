<?php
/**
 * scripts/md_to_pdf_html.php — مُحوِّل Markdown → HTML مهيَّأٌ للطباعة (RTL عربي)
 * ───────────────────────────────────────────────────────────────────────────
 * مبنيٌّ للكتالوجات (docs/EMS_CATALOG*.md): عناوين · جداول · قوائم · اقتباسات
 * · كتل شيفرة (تُعرض LTR لأن فيها رسومًا نصية) · روابط · فواصل.
 *
 * الاستعمال:
 *   php scripts/md_to_pdf_html.php <input.md> <output.html> "<عنوان الغلاف>" "<العنوان الفرعي>"
 *
 * ثم يُطبع الناتج بـ Chrome:
 *   chrome --headless --disable-gpu --print-to-pdf=out.pdf --no-pdf-header-footer file:///...
 */

if ($argc < 3) {
    fwrite(STDERR, "usage: php md_to_pdf_html.php <in.md> <out.html> [title] [subtitle]\n");
    exit(1);
}

$in       = $argv[1];
$out      = $argv[2];
$title    = isset($argv[3]) ? $argv[3] : 'كتالوج';
$subtitle = isset($argv[4]) ? $argv[4] : '';

$md = file_get_contents($in);
if ($md === false) { fwrite(STDERR, "cannot read $in\n"); exit(1); }
$md = str_replace("\r\n", "\n", $md);

// ── مساعدات ────────────────────────────────────────────────────────────────
function esc($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** تنسيقات السطر: كود مضمَّن ثم غامق ثم روابط. */
function inline($s)
{
    $codes = array();
    $s = preg_replace_callback('/`([^`]+)`/u', function ($m) use (&$codes) {
        $codes[] = $m[1];
        return "\x01" . (count($codes) - 1) . "\x01";
    }, $s);

    $s = esc($s);
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/u', '<em>$1</em>', $s);
    // روابط داخلية إلى مراسٍ أو ملفات — تُعرض نصًّا في PDF مع إبقاء الرابط
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/u', function ($m) {
        $href = $m[2];
        if (strpos($href, '#') === 0) { return '<a href="' . esc($href) . '">' . $m[1] . '</a>'; }
        return '<a class="ext" href="' . esc($href) . '">' . $m[1] . '</a>';
    }, $s);

    $s = preg_replace_callback('/\x01(\d+)\x01/', function ($m) use ($codes) {
        return '<code>' . esc($codes[intval($m[1])]) . '</code>';
    }, $s);
    return $s;
}

/** هل السطر فاصلُ جدولٍ (|---|---|)؟ */
function isTableSep($l) { return (bool) preg_match('/^\s*\|?[\s:\-\|]+\|[\s:\-\|]*$/', $l) && strpos($l, '-') !== false; }
function isTableRow($l) { return strpos(ltrim($l), '|') === 0; }

function splitRow($l)
{
    $l = trim($l);
    $l = preg_replace('/^\|/', '', $l);
    $l = preg_replace('/\|$/', '', $l);
    return array_map('trim', explode('|', $l));
}

// ── التحويل ────────────────────────────────────────────────────────────────
$lines = explode("\n", $md);
$n = count($lines);
$html = '';
$i = 0;
$slugCount = array();
$toc = array();

while ($i < $n) {
    $line = $lines[$i];
    $t = rtrim($line);

    // كتلة شيفرة
    if (preg_match('/^\s*```/', $t)) {
        $i++;
        $buf = array();
        while ($i < $n && !preg_match('/^\s*```/', $lines[$i])) { $buf[] = $lines[$i]; $i++; }
        $i++;
        $html .= '<pre class="art">' . esc(implode("\n", $buf)) . "</pre>\n";
        continue;
    }

    // فاصل أفقي
    if (preg_match('/^\s*---+\s*$/', $t)) { $html .= "<hr>\n"; $i++; continue; }

    // عنوان
    if (preg_match('/^(#{1,6})\s+(.*)$/u', $t, $m)) {
        $lvl = strlen($m[1]);
        $txt = trim($m[2]);
        $slug = 'h' . $lvl . '-' . (isset($slugCount[$txt]) ? ++$slugCount[$txt] : ($slugCount[$txt] = 1)) . '-' . substr(md5($txt), 0, 6);
        if ($lvl <= 2) { $toc[] = array('lvl' => $lvl, 'txt' => $txt, 'slug' => $slug); }
        $html .= '<h' . $lvl . ' id="' . $slug . '">' . inline($txt) . '</h' . $lvl . ">\n";
        $i++;
        continue;
    }

    // جدول
    if (isTableRow($t) && $i + 1 < $n && isTableSep($lines[$i + 1])) {
        $head = splitRow($t);
        $i += 2;
        $rows = array();
        while ($i < $n && isTableRow($lines[$i])) { $rows[] = splitRow($lines[$i]); $i++; }
        $html .= "<table>\n<thead><tr>";
        foreach ($head as $c) { $html .= '<th>' . inline($c) . '</th>'; }
        $html .= "</tr></thead>\n<tbody>\n";
        foreach ($rows as $r) {
            $html .= '<tr>';
            foreach ($r as $c) { $html .= '<td>' . inline($c) . '</td>'; }
            $html .= "</tr>\n";
        }
        $html .= "</tbody></table>\n";
        continue;
    }

    // اقتباس (قد يمتد أسطرًا)
    if (preg_match('/^\s*>\s?(.*)$/u', $t, $m)) {
        $buf = array($m[1]);
        $i++;
        while ($i < $n && preg_match('/^\s*>\s?(.*)$/u', rtrim($lines[$i]), $m2)) { $buf[] = $m2[1]; $i++; }
        $inner = '';
        $para = array();
        foreach ($buf as $b) {
            if (trim($b) === '') {
                if ($para) { $inner .= '<p>' . inline(implode(' ', $para)) . '</p>'; $para = array(); }
            } else { $para[] = $b; }
        }
        if ($para) { $inner .= '<p>' . inline(implode(' ', $para)) . '</p>'; }
        $html .= '<blockquote>' . $inner . "</blockquote>\n";
        continue;
    }

    // قائمة (مرقّمة أو نقطية، بمستويين)
    if (preg_match('/^(\s*)([-*]|\d+\.)\s+(.*)$/u', $t, $m)) {
        $stack = array();  // كل عنصر: array(indent, tag)
        $buf = '';
        while ($i < $n && preg_match('/^(\s*)([-*]|\d+\.)\s+(.*)$/u', rtrim($lines[$i]), $mm)) {
            $indent = strlen(str_replace("\t", '    ', $mm[1]));
            $tag = ($mm[2] === '-' || $mm[2] === '*') ? 'ul' : 'ol';
            $txt = $mm[3];

            while ($stack && $indent < $stack[count($stack) - 1][0]) {
                $popped = array_pop($stack);
                $buf .= '</li></' . $popped[1] . '>';
            }
            if (!$stack || $indent > $stack[count($stack) - 1][0]) {
                $buf .= '<' . $tag . '><li>';
                $stack[] = array($indent, $tag);
            } else {
                $buf .= '</li><li>';
            }
            $buf .= inline($txt);
            $i++;
        }
        while ($stack) { $popped = array_pop($stack); $buf .= '</li></' . $popped[1] . '>'; }
        $html .= $buf . "\n";
        continue;
    }

    // سطر فارغ
    if (trim($t) === '') { $i++; continue; }

    // فقرة
    $para = array($t);
    $i++;
    while ($i < $n) {
        $nx = rtrim($lines[$i]);
        if (trim($nx) === '' || preg_match('/^(#{1,6})\s/', $nx) || isTableRow($nx)
            || preg_match('/^\s*>/', $nx) || preg_match('/^\s*```/', $nx)
            || preg_match('/^\s*---+\s*$/', $nx) || preg_match('/^(\s*)([-*]|\d+\.)\s+/u', $nx)) { break; }
        $para[] = $nx;
        $i++;
    }
    $html .= '<p>' . inline(implode(' ', $para)) . "</p>\n";
}

// ── فهرس الطباعة ───────────────────────────────────────────────────────────
$tocHtml = '';
if ($toc) {
    $tocHtml = '<nav class="toc"><h2 class="toc-title">محتويات الملف</h2><ul>';
    foreach ($toc as $t2) {
        if ($t2['lvl'] > 2) { continue; }
        $tocHtml .= '<li class="lvl' . $t2['lvl'] . '"><a href="#' . $t2['slug'] . '">' . inline($t2['txt']) . '</a></li>';
    }
    $tocHtml .= '</ul></nav>';
}

$css = <<<'CSS'
@page { size: A4; margin: 16mm 14mm 18mm 14mm; }
* { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body {
  direction: rtl; text-align: right;
  font-family: "Segoe UI", "Tahoma", "Arial", sans-serif;
  font-size: 10.5pt; line-height: 1.75; color: #1f2328; margin: 0;
}
.cover { height: 250mm; display: flex; flex-direction: column; justify-content: center;
  align-items: center; text-align: center; page-break-after: always; }
.cover .brand { font-size: 13pt; letter-spacing: 2px; color: #8a7420; font-weight: 700; margin-bottom: 10mm; }
.cover h1 { font-size: 30pt; font-weight: 800; color: #1f2328; margin: 0 0 6mm; border: 0; padding: 0; }
.cover .sub { font-size: 13pt; color: #55606b; max-width: 130mm; line-height: 1.9; }
.cover .rule { width: 60mm; height: 4px; background: #e2b93b; margin: 8mm auto; border-radius: 2px; }
.cover .meta { margin-top: 14mm; font-size: 10pt; color: #7b8794; }

h1 { font-size: 19pt; font-weight: 800; color: #14212e; margin: 0 0 6mm;
     padding-bottom: 3mm; border-bottom: 3px solid #e2b93b; page-break-before: always;
     page-break-after: avoid; }
h1:first-of-type { page-break-before: avoid; }
h2 { font-size: 14.5pt; font-weight: 800; color: #1b2b3a; margin: 9mm 0 3mm;
     padding-right: 4mm; border-right: 5px solid #e2b93b; page-break-after: avoid; }
h3 { font-size: 12pt; font-weight: 700; color: #24384a; margin: 6mm 0 2.5mm; page-break-after: avoid; }
h4 { font-size: 11pt; font-weight: 700; color: #3a4a58; margin: 4mm 0 2mm; page-break-after: avoid; }
p { margin: 0 0 3mm; orphans: 3; widows: 3; }
hr { border: 0; border-top: 1px solid #e3e6ea; margin: 7mm 0; }
a { color: #1a5b8f; text-decoration: none; }
a.ext { color: #1f2328; }

strong { font-weight: 800; color: #14212e; }
code { font-family: Consolas, "Courier New", monospace; font-size: 9pt; direction: ltr;
  unicode-bidi: embed; background: #f3f4f6; border: 1px solid #e3e6ea; border-radius: 3px;
  padding: 0 3px; color: #a03050; }

/* الرسوم النصية: الاتجاه LTR ليحفظ محاذاة الأسهم والأعمدة، مع isolate
   لا bidi-override — فالأخيرة تقلب الكلمات العربية داخل الرسم. */
pre.art { direction: ltr; text-align: left; unicode-bidi: isolate;
  font-family: Consolas, "Courier New", monospace; font-size: 8.4pt; line-height: 1.45;
  background: #fbfaf5; border: 1px solid #e6e0c8; border-right: 4px solid #e2b93b;
  border-radius: 4px; padding: 4mm 5mm; overflow: hidden; white-space: pre;
  page-break-inside: avoid; margin: 0 0 4mm; }

blockquote { margin: 0 0 4mm; padding: 3mm 5mm 3mm 4mm; background: #fdfbf2;
  border-right: 4px solid #e2b93b; border-radius: 0 4px 4px 0; page-break-inside: avoid; }
blockquote p { margin: 0 0 2mm; } blockquote p:last-child { margin: 0; }

ul, ol { margin: 0 0 3.5mm; padding-right: 7mm; }
li { margin-bottom: 1.4mm; }
li > ul, li > ol { margin-top: 1.4mm; margin-bottom: 0; }

table { width: 100%; border-collapse: collapse; margin: 0 0 5mm; font-size: 9.4pt;
  page-break-inside: auto; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; page-break-after: auto; }
th { background: #1b2b3a; color: #fff; font-weight: 700; text-align: right;
  padding: 2mm 2.5mm; border: 1px solid #2c3f52; }
td { padding: 1.8mm 2.5mm; border: 1px solid #dfe3e8; vertical-align: top; }
tbody tr:nth-child(even) td { background: #f8f9fa; }

nav.toc { page-break-after: always; }
nav.toc .toc-title { font-size: 17pt; border-right: 0; border-bottom: 3px solid #e2b93b;
  padding: 0 0 3mm; margin: 0 0 6mm; }
nav.toc ul { list-style: none; padding: 0; margin: 0; }
nav.toc li { padding: 1.6mm 0; border-bottom: 1px dotted #dfe3e8; }
nav.toc li.lvl1 { font-weight: 800; font-size: 11.5pt; margin-top: 3mm; color: #14212e; }
nav.toc li.lvl2 { padding-right: 7mm; font-size: 10pt; color: #44525f; }
CSS;

$doc = '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
     . '<title>' . esc($title) . '</title><style>' . $css . '</style></head><body>'
     . '<section class="cover">'
     . '<div class="brand">EQUIPATION · إنجاز</div>'
     . '<h1>' . esc($title) . '</h1>'
     . '<div class="rule"></div>'
     . ($subtitle !== '' ? '<div class="sub">' . esc($subtitle) . '</div>' : '')
     . '<div class="meta">' . esc(date('Y-m-d')) . '</div>'
     . '</section>'
     . $tocHtml
     . $html
     . '</body></html>';

file_put_contents($out, $doc);
echo 'ok: ' . $out . ' (' . number_format(strlen($doc)) . " bytes, " . count($toc) . " toc entries)\n";
