<?php
/**
 * tools/scrdes_compare.php — مقارنُ تصميم الأعمدة (SCR-DES-01) بالواقع الحي
 * ───────────────────────────────────────────────────────────────────────────
 * لكل شاشةٍ في ورقة الإدارة: أعمدتُها المصمَّمةُ في المستند مقابل أعمدةِ
 * جداولها الفعلية (رؤوس <th> في ملفها الحي عبر قاموس المواءمة):
 *   مطابق  — في المستند والنظام معًا
 *   ناقص   — في المستند وليس في النظام (يُبنى)
 *   زائد   — في النظام وليس في المستند (يُضاف للمستند بالأخضر)
 * التشغيل: php tools/scrdes_compare.php --sheet="01 · إدارة الموقع"
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$sheetName = '01 · إدارة الموقع';
foreach ($argv as $a) { if (strpos($a, '--sheet=') === 0) { $sheetName = substr($a, 8); } }

/* قاموس المواءمة: قانوني → مساره الحي */
$map = array();
$r = mysqli_query($conn, "SELECT canonical_file, state, real_path FROM nav09_file_map");
while ($x = mysqli_fetch_assoc($r)) { $map[$x['canonical_file']] = $x; }

$norm = function ($s) {
    $s = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $s)));
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = preg_replace('/[ًٌٍَُِّْ]/u', '', $s);
    return $s;
};

/* استخراجُ رؤوس الجداول من ملفٍّ حي: <th>…</th> بكل صيغها */
function extractHeads($path, $norm) {
    $src = @file_get_contents($path);
    if ($src === false) { return null; }
    $heads = array();
    if (preg_match_all('/<th\b[^>]*>(.*?)<\/th>/su', $src, $m)) {
        foreach ($m[1] as $h) {
            // رأسٌ قد يحوي php echo لعنوانٍ ثابت — نبقي النص الثابت فقط
            $h = preg_replace('/<\?php.*?\?>/su', '', $h);
            $h = trim(strip_tags($h));
            if ($h !== '' && mb_strlen($h) < 60 && !preg_match('/^[#\d\W]+$/u', $h)) {
                $heads[$norm($h)] = $h;
            }
        }
    }
    return $heads; // normalized → original
}

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($ROOT . '/tmp_SCRDES.xlsx');
$reader->setReadDataOnly(true);
$wb = $reader->load($ROOT . '/tmp_SCRDES.xlsx');
$s = $wb->getSheetByName($sheetName);
if (!$s) { die("ورقةٌ غيرُ موجودة: $sheetName\n"); }

$rows = $s->toArray(null, true, false, false);
$screens = array(); $cur = null;
foreach ($rows as $i => $r) {
    $c0 = trim((string) ($r[0] ?? ''));
    if (mb_substr($c0, 0, 1) === '■') {
        // «■  اسم الشاشة   ·   file.php   ·  …»
        if (preg_match('/■\s*(.+?)\s+·\s+([a-z0-9_.]+\.php)/u', $c0, $m)) {
            $cur = count($screens);
            $screens[] = array('title' => trim($m[1]), 'file' => trim($m[2]), 'cols' => array());
        } else { $cur = null; }
        continue;
    }
    if ($cur !== null && empty($screens[$cur]['cols'])) {
        // أولُ صفٍّ بعد الرأس متعددُ الخلايا = أعمدةُ التصميم
        $cells = array(); foreach ($r as $c) { $c = trim((string) $c); if ($c !== '') { $cells[] = $c; } }
        if (count($cells) >= 4) { $screens[$cur]['cols'] = $cells; }
    }
}

echo "الورقة: $sheetName — شاشاتٌ مصمَّمة: " . count($screens) . "\n";
echo str_repeat('═', 70) . "\n";
$tot = array('match' => 0, 'missing' => 0, 'extra' => 0, 'soon' => 0, 'notable' => 0);
foreach ($screens as $sc) {
    $cf = $sc['file'];
    $st = isset($map[$cf]) ? $map[$cf]['state'] : '؟';
    $real = isset($map[$cf]) ? $map[$cf]['real_path'] : null;
    echo "■ {$sc['title']}  ({$cf})";
    if ($st === 'soon' || $real === null) {
        echo "  → 🟡 الشاشةُ كلُّها «قريبًا» — أعمدتُها الـ" . count($sc['cols']) . " كلُّها ناقصة\n";
        $tot['soon']++; $tot['missing'] += count($sc['cols']);
        continue;
    }
    echo "  → $real\n";
    $heads = extractHeads($ROOT . '/' . $real, $norm);
    if ($heads === null) { echo "   ⚠ تعذّرت قراءةُ الملف\n"; continue; }
    if (!$heads) {
        echo "   ◌ الشاشةُ الحيةُ بلا جدولِ رؤوسٍ (لوحةٌ/بطاقات/نموذج) — أعمدةُ المستند الـ" . count($sc['cols']) . " تُقاس يدويًّا\n";
        $tot['notable']++;
        continue;
    }
    $docNorm = array();
    foreach ($sc['cols'] as $c) { $docNorm[$norm($c)] = $c; }
    $match = array_intersect_key($docNorm, $heads);
    $missing = array_diff_key($docNorm, $heads);
    $extra = array_diff_key($heads, $docNorm);
    $tot['match'] += count($match); $tot['missing'] += count($missing); $tot['extra'] += count($extra);
    echo "   ✔ مطابق: " . count($match) . " │ 🟡 ناقص في النظام: " . count($missing) . " │ 🟢 زائد في النظام: " . count($extra) . "\n";
    if ($missing) { echo "     🟡 " . implode(' · ', array_slice(array_values($missing), 0, 12)) . "\n"; }
    if ($extra)   { echo "     🟢 " . implode(' · ', array_slice(array_values($extra), 0, 12)) . "\n"; }
}
echo str_repeat('═', 70) . "\n";
printf("الإجمالي: مطابق=%d · ناقص=%d · زائد=%d · شاشات قريبًا=%d · شاشات بلا جدول=%d\n",
    $tot['match'], $tot['missing'], $tot['extra'], $tot['soon'], $tot['notable']);
