<?php
/**
 * يستخرج نصَّ وثائقِ FIX بصيغةِ Markdown مع **صفوفِ الجداول** سليمةً —
 * فجردُ الأحكامِ يقرأ البنودَ الذريةَ من صفوفٍ تبدأ بـ`| FIXA-0001-أ |`.
 * القراءةُ من `<w:tr>`/`<w:tc>` لا من تسطيحِ الفقراتِ وحدَها.
 */
$ROOT = dirname(__DIR__);
if (php_sapi_name() !== 'cli') { exit("CLI فقط
"); }
$OUT  = $argv[1] ?? sys_get_temp_dir();
$DOCS = array(
    '01' => 'FIX-01 — التصحيح البنيوي.docx',
    '02' => 'FIX-02 — تصحيح القشرة والنظام التصميمي.docx',
    '03' => 'FIX-03 — تصحيح المالية والمراجعة.docx',
);

function w_text($xml)
{
    $xml = preg_replace('#<w:tab[^>]*/>#', ' ', $xml);
    $xml = preg_replace('#<w:br[^>]*/>#', ' ', $xml);
    $t = strip_tags($xml);
    $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $t));
}

foreach ($DOCS as $k => $fn) {
    $z = new ZipArchive();
    if ($z->open($ROOT . '/docs/fix/' . $fn) !== true) { echo "تعذّر فتح {$fn}\n"; continue; }
    $xml = $z->getFromName('word/document.xml');
    $z->close();

    $lines = array(); $rows = 0;
    // الجسمُ يُقطَّع إلى وحداتٍ: صفُّ جدولٍ أو فقرة — بترتيبِ ورودِها
    if (preg_match_all('#<w:tr\b[\s\S]*?</w:tr>|<w:p\b[\s\S]*?</w:p>#', $xml, $m)) {
        foreach ($m[0] as $unit) {
            if (strpos($unit, '<w:tr') === 0) {
                $cells = array();
                if (preg_match_all('#<w:tc\b[\s\S]*?</w:tc>#', $unit, $cm)) {
                    foreach ($cm[0] as $c) { $cells[] = w_text($c); }
                }
                if ($cells && implode('', $cells) !== '') {
                    $lines[] = '| ' . implode(' | ', $cells) . ' |';
                    $rows++;
                }
            } else {
                $t = w_text($unit);
                if ($t !== '') { $lines[] = $t; }
            }
        }
    }
    file_put_contents($OUT . '/fix' . $k . '.md', implode("\n", $lines));
    echo "fix{$k}.md: " . count($lines) . " سطرًا · منها {$rows} صفَّ جدول\n";
}
