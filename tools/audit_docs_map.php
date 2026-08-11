<?php
/**
 * tools/audit_docs_map.php — خريطةُ مستنداتِ التدقيقِ والتصحيح، مستندًا مستندًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ المصدران **لا يُجمعان** (حكمٌ نافذٌ من معمارية v21 §9):
 *     `docs/fix/`          — 4 مستنداتِ **تصحيحٍ** (docx): أحكامٌ ومعاييرُ قبول.
 *     `docs/audit_2026-08/` — 7 مصنَّفاتِ **تدقيقٍ** (xlsx): الملاحظاتُ الميدانية
 *                             بدليلِ «ملف:سطر» واختبارِ قبولٍ لكلِّ ملاحظة.
 *   والأولُ **خلاصةُ** الثاني، فجمعُ الرقمين تضخيمٌ كاذب.
 *
 * ◆ وهذه الأداةُ تقرأ xlsx **خامًّا** (zip + XML) بلا مكتبة: الغرضُ عدُّ ما في
 *   كلِّ ورقةٍ وأعمدتِها لا تنسيقُها — فلا حاجةَ لمحرّكِ جداول.
 *
 * ◆ گوتشا مقيسة: خليةٌ نوعُها `s` قيمتُها **فهرسٌ** في `sharedStrings.xml` لا نصٌّ؛
 *   ومن قرأها نصًّا حصل على أرقامٍ محلَّ الكلام. وخليةٌ `inlineStr` نصُّها داخلَها.
 *
 * التشغيل: php tools/audit_docs_map.php [--json=مسار] [--dump=مجلد]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
if (!class_exists('ZipArchive')) { fwrite(STDERR, "ZipArchive غيرُ متاح\n"); exit(1); }

$jsonOut = null; $dumpDir = null;
foreach ($argv as $a) {
    if (strpos($a, '--json=') === 0) { $jsonOut = substr($a, 7); }
    if (strpos($a, '--dump=') === 0) { $dumpDir = substr($a, 7); }
}
if ($dumpDir && !is_dir($dumpDir)) { @mkdir($dumpDir, 0777, true); }

/* ══ قارئُ xlsx خامّ ═══════════════════════════════════════════════════ */
function xlsx_shared_strings(ZipArchive $z)
{
    $out = array();
    $xml = $z->getFromName('xl/sharedStrings.xml');
    if ($xml === false) { return $out; }
    /* كلُّ <si> عنصرٌ واحد؛ وقد يحوي عدةَ <t> (نصٌّ منسَّقٌ بأجزاء) فتُلحَق. */
    if (preg_match_all('#<si>(.*?)</si>#s', $xml, $m)) {
        foreach ($m[1] as $si) {
            $t = '';
            if (preg_match_all('#<t[^>]*>(.*?)</t>#s', $si, $tt)) { $t = implode('', $tt[1]); }
            $out[] = html_entity_decode($t, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
    }
    return $out;
}

function xlsx_sheets(ZipArchive $z)
{
    $out = array();
    $wb = $z->getFromName('xl/workbook.xml');
    if ($wb === false) { return $out; }
    $rels = (string) $z->getFromName('xl/_rels/workbook.xml.rels');
    $relMap = array();
    if (preg_match_all('#<Relationship[^>]*Id="([^"]+)"[^>]*Target="([^"]+)"#', $rels, $rm, PREG_SET_ORDER)) {
        foreach ($rm as $r) { $relMap[$r[1]] = ltrim(str_replace('/xl/', '', $r[2]), '/'); }
    }
    if (preg_match_all('#<sheet[^>]*name="([^"]*)"[^>]*r:id="([^"]+)"#', $wb, $m, PREG_SET_ORDER)) {
        foreach ($m as $s) {
            $out[] = array('name' => html_entity_decode($s[1], ENT_QUOTES | ENT_XML1, 'UTF-8'),
                           'path' => isset($relMap[$s[2]]) ? 'xl/' . $relMap[$s[2]] : null);
        }
    }
    return $out;
}

/** صفوفُ ورقةٍ: مصفوفةُ مصفوفاتٍ (عمود→قيمة نصية). */
function xlsx_rows(ZipArchive $z, $path, array $ss, $maxRows = 100000)
{
    $xml = $z->getFromName($path);
    if ($xml === false) { return array(); }
    $rows = array();
    if (!preg_match_all('#<row[^>]*>(.*?)</row>#s', $xml, $rm)) { return $rows; }
    foreach ($rm[1] as $i => $rowXml) {
        if ($i >= $maxRows) { break; }
        $cells = array();
        if (preg_match_all('#<c\s+([^>]*)>(.*?)</c>#s', $rowXml, $cm, PREG_SET_ORDER)) {
            foreach ($cm as $c) {
                $attrs = $c[1]; $body = $c[2];
                $ref = ''; $type = '';
                if (preg_match('#r="([A-Z]+)\d+"#', $attrs, $x)) { $ref = $x[1]; }
                if (preg_match('#t="([^"]+)"#', $attrs, $x)) { $type = $x[1]; }
                $val = '';
                if ($type === 'inlineStr') {
                    if (preg_match_all('#<t[^>]*>(.*?)</t>#s', $body, $tt)) { $val = implode('', $tt[1]); }
                } elseif (preg_match('#<v>(.*?)</v>#s', $body, $vx)) {
                    $val = $vx[1];
                    if ($type === 's') { $idx = (int) $val; $val = isset($ss[$idx]) ? $ss[$idx] : ''; }
                }
                $cells[$ref] = trim(html_entity_decode($val, ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }
        }
        $rows[] = $cells;
    }
    return $rows;
}

/* ══ ① مصنَّفاتُ التدقيق ═══════════════════════════════════════════════ */
echo "══════════════════════════════════════════════════════════════════════\n";
echo " خريطةُ مستنداتِ التدقيقِ والتصحيح — " . date('Y-m-d H:i') . "\n";
echo "══════════════════════════════════════════════════════════════════════\n";
echo "\n╔══ ① مصنَّفاتُ التدقيق — docs/audit_2026-08 (الملاحظاتُ الميدانية)\n";

$AUDIT = array();
foreach (glob($ROOT . '/docs/audit_2026-08/*.xlsx') as $f) {
    $base = basename($f);
    $z = new ZipArchive();
    if ($z->open($f) !== true) { echo "  ✘ تعذّر فتحُ {$base}\n"; continue; }
    $ss = xlsx_shared_strings($z);
    $sheets = xlsx_sheets($z);
    echo "\n  ■ {$base}  (" . round(filesize($f) / 1024) . "ك · نصوصٌ مشتركة: " . count($ss) . ")\n";
    $fileTotal = 0; $sheetInfo = array();
    foreach ($sheets as $sh) {
        if ($sh['path'] === null) { continue; }
        $rows = xlsx_rows($z, $sh['path'], $ss);
        $n = count($rows);
        /* ◆ گوتشا مقيسة: الصفُّ الأولُ في هذه المصنَّفات **عنوانٌ مدمَجٌ بخليةٍ
             واحدة**، فمن قرأه ترويسةً أعلن «عمودٌ واحد» لكلِّ ورقة. الترويسةُ
             الحقيقيةُ هي **أعرضُ صفٍّ في أوائلِ الورقة**، وصفوفُ البياناتِ ما
             بعدها — لا «المجموع ناقص واحد». */
        $hdrIdx = 0; $hdrWidth = 0;
        for ($i = 0; $i < min($n, 8); $i++) {
            if (count($rows[$i]) > $hdrWidth) { $hdrWidth = count($rows[$i]); $hdrIdx = $i; }
        }
        $hdr = $n > 0 ? array_values($rows[$hdrIdx]) : array();
        $dataRows = max(0, $n - $hdrIdx - 1);
        $fileTotal += $dataRows;
        $sheetInfo[] = array('name' => $sh['name'], 'rows' => $dataRows, 'cols' => count($hdr),
                            'header_row' => $hdrIdx + 1, 'headers' => $hdr);
        printf("      %-40s صفوفٌ: %-5d أعمدة: %-3d (ترويسةٌ في السطر %d)\n",
            mb_substr($sh['name'], 0, 38), $dataRows, count($hdr), $hdrIdx + 1);
        if ($dataRows > 0 && count($hdr) > 1) {
            echo '          ' . mb_substr(implode(' | ', array_slice($hdr, 0, 9)), 0, 170) . "\n";
        }
        if ($dumpDir) {
            $out = array();
            foreach ($rows as $r) { $out[] = implode("\t", array_values($r)); }
            file_put_contents($dumpDir . '/' . preg_replace('/[^A-Za-z0-9_]+/', '_', $base . '__' . $sh['name']) . '.tsv',
                implode("\n", $out));
        }
    }
    $z->close();
    $AUDIT[$base] = array('total' => $fileTotal, 'sheets' => $sheetInfo);
    echo '      ── مجموعُ صفوفِ البيانات: ' . $fileTotal . "\n";
}

/* ══ ② مستنداتُ التصحيح ═══════════════════════════════════════════════ */
echo "\n╔══ ② مستنداتُ التصحيح — docs/fix (الأحكامُ ومعاييرُ القبول)\n";
$FIX = array();
foreach (glob($ROOT . '/docs/fix/*.docx') as $f) {
    $base = basename($f);
    $z = new ZipArchive();
    if ($z->open($f) !== true) { echo "  ✘ تعذّر فتحُ {$base}\n"; continue; }
    $xml = (string) $z->getFromName('word/document.xml');
    $z->close();
    /* نصُّ المستندِ بفواصلِ فقراتٍ وصفوفِ جداول */
    $txt = preg_replace('#</w:p>#', "\n", $xml);
    $txt = preg_replace('#</w:tr>#', "\n", $txt);
    $txt = preg_replace('#</w:tc>#', "\t", $txt);
    $txt = strip_tags($txt);
    $txt = html_entity_decode($txt, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $lines = array_values(array_filter(array_map('trim', explode("\n", $txt)), static function ($s) { return $s !== ''; }));
    /* الأحكامُ برموزها — الرمزُ المعياريُّ في هذه الحزمة: حرفان-رقمان */
    $codes = array();
    if (preg_match_all('/\b((?:AC|CS|FN|GT|SH|RF|MD|CM|AS)-[A-Z]?\d{1,2}[a-z]?)\b/u', $txt, $cm)) {
        foreach ($cm[1] as $c) { $codes[$c] = isset($codes[$c]) ? $codes[$c] + 1 : 1; }
    }
    $FIX[$base] = array('lines' => count($lines), 'codes' => $codes, 'chars' => mb_strlen($txt));
    echo "\n  ■ {$base}\n";
    echo '      أسطرٌ: ' . count($lines) . ' · محارف: ' . mb_strlen($txt)
       . ' · **رموزُ أحكامٍ متمايزة: ' . count($codes) . "**\n";
    if ($codes) {
        ksort($codes);
        echo '      ' . implode(' · ', array_keys($codes)) . "\n";
    }
    if ($dumpDir) { file_put_contents($dumpDir . '/' . preg_replace('/[^A-Za-z0-9_]+/', '_', $base) . '.txt', implode("\n", $lines)); }
}

echo "\n" . str_repeat('═', 70) . "\n";
$auditTotal = 0;
foreach ($AUDIT as $v) { $auditTotal += $v['total']; }
$allCodes = array();
foreach ($FIX as $v) { foreach (array_keys($v['codes']) as $c) { $allCodes[$c] = 1; } }
echo 'مصنَّفاتُ تدقيقٍ: ' . count($AUDIT) . ' · مجموعُ صفوفِها: ' . $auditTotal . "\n";
echo 'مستنداتُ تصحيحٍ: ' . count($FIX) . ' · رموزُ أحكامٍ متمايزةٌ فيها: ' . count($allCodes) . "\n";
echo "◆ ولا يُجمع الرقمان: الثاني خلاصةُ الأول.\n";
echo str_repeat('═', 70) . "\n";

if ($jsonOut) {
    file_put_contents($jsonOut, json_encode(array('audit' => $AUDIT, 'fix' => $FIX), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "كُتب JSON: {$jsonOut}\n";
}
exit(0);
