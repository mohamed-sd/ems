<?php
/**
 * tools/scrdes_compare.php — مقارنُ أعمدة الشاشات v2 (منهج CMP-03 ورقة 99)
 * ───────────────────────────────────────────────────────────────────────────
 * أربعةُ أحكامٍ لا ثلاثة: مطابقٌ · مرادفٌ (ليس نقصًا) · ناقصٌ فعلًا · زائد.
 * وأربعُ قواعد: القياسُ بالشاشة الفريدة (وعمودُ «تظهر في») · المطابقةُ بالمعنى
 * (تقاطعُ الجذوع ≥ 60٪ أو احتواءٌ — بلا متعدد-لواحد) · الناقصُ يُفرز صنفين
 * (حوكمةٌ مشتركةٌ تُبنى مرةً · ووظيفيٌّ شاشةً شاشة) · ويُستبعد ما لا يُقارن
 * (غيرُ المبنية · اللوحاتُ · أعمدةُ الواجهة).
 *
 * التشغيل: php tools/scrdes_compare.php [--sheet="01 · إدارة الموقع"] [--totals]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$onlySheet = null; $totalsOnly = in_array('--totals', $argv, true);
foreach ($argv as $a) { if (strpos($a, '--sheet=') === 0) { $onlySheet = substr($a, 8); } }

$map = array();
$r = mysqli_query($conn, "SELECT canonical_file, state, real_path FROM nav09_file_map");
while ($x = mysqli_fetch_assoc($r)) { $map[$x['canonical_file']] = $x; }

/* طبقةُ الحوكمة المشتركة (CMP-03 ورقة 04) — تُفرز من الناقص الوظيفي */
$GOV = array('الكيان','المرفق','مرجع التفويض','تاريخ الاعتماد','تاريخ الإنشاء','مركز التكلفة',
    'سعر الصرف ومصدره','المرجع الأب','المُنشئ — الاسم والصفة','العملة','المعتمِد — الاسم والصفة',
    'الحالة','مفتاح منع التكرار','معكوس بـ','عكس عن','درجة الأثر','سجل الاطّلاع','سعر الصرف',
    'المستند المرفق','المرفقات','المعتمِد المطلوب','الجهة المُنشئة','مركز التكلفة المحمَّل',
    'العملة الأساسية','مصدر سعر الصرف');
$UI_COLS = array('اجراءات','الاجراءات','اجراء','الاجراء','تحديد','actions','action','#','id','م');

$norm = function ($s) {
    $s = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $s)));
    $s = preg_replace('/[()\[\]«»"\'؟?—–\-·—]/u', ' ', $s);
    $s = str_replace(array('أ','إ','آ'), 'ا', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace('ى', 'ي', $s);
    $s = preg_replace('/[ًٌٍَُِّْ]/u', '', $s);
    return preg_replace('/\s+/u', ' ', trim($s));
};
/* كلماتُ الجذوع بعد نزع أل التعريف والحشو */
$stems = function ($n) {
    $stop = array('و','في','من','الي','على','عن','او','ثم','—','·');
    $out = array();
    foreach (explode(' ', $n) as $w) {
        $w = preg_replace('/^(ال|بال|لل|وال|فال|كال)/u', '', $w);
        if ($w === '' || in_array($w, $stop, true) || mb_strlen($w) < 2) { continue; }
        $out[$w] = 1;
    }
    return $out;
};
/* درجةُ المرادفة: تقاطعُ الجذوع على الأصغر — أو احتواءُ أحدهما الآخر */
$sim = function ($a, $b) use ($stems, $norm) {
    if ($a === $b) { return 1.0; }
    if ($a !== '' && $b !== '' && (mb_strpos($a, $b) !== false || mb_strpos($b, $a) !== false)) { return 1.0; }
    $sa = $stems($a); $sb = $stems($b);
    if (!$sa || !$sb) { return 0.0; }
    $inter = count(array_intersect_key($sa, $sb));
    return $inter / min(count($sa), count($sb));
};

function extractHeads($path, $norm) {
    $src = @file_get_contents($path);
    if ($src === false) { return null; }
    $heads = array();
    if (preg_match_all('/<th\b[^>]*>(.*?)<\/th>/su', $src, $m)) {
        foreach ($m[1] as $h) {
            $h = preg_replace('/<\?php.*?\?>/su', '', $h);
            $h = trim(strip_tags($h));
            if ($h !== '' && mb_strlen($h) < 60 && !preg_match('/^[#\d\W]+$/u', $h)) {
                $heads[$norm($h)] = $h;
            }
        }
    }
    return $heads;
}

/* قراءةُ المصنّف: الشاشاتُ الفريدةُ بأعمدتها وعدِّ ظهورها */
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($ROOT . '/tmp_SCRDES.xlsx');
$reader->setReadDataOnly(true);
$wb = $reader->load($ROOT . '/tmp_SCRDES.xlsx');
$screens = array(); // file → [title, appear, cols[], owner]
foreach ($wb->getSheetNames() as $sheetName) {
    if (!preg_match('/^\d{2} · /u', $sheetName)) { continue; }
    if ($onlySheet !== null && $sheetName !== $onlySheet) { continue; }
    $rows = $wb->getSheetByName($sheetName)->toArray(null, true, false, false);
    for ($i = 0; $i < count($rows); $i++) {
        $c0 = trim((string) ($rows[$i][0] ?? ''));
        if (mb_substr($c0, 0, 1) !== '■' || !preg_match('/■\s*(.+?)\s+·\s+([a-z0-9_.]+\.php)/u', $c0, $m)) { continue; }
        $cf = trim($m[2]);
        if (!isset($screens[$cf])) { $screens[$cf] = array('title' => trim($m[1]), 'appear' => 0, 'cols' => array(), 'owner' => $sheetName); }
        $screens[$cf]['appear']++;
        if (empty($screens[$cf]['cols'])) {
            for ($j = $i + 1; $j <= min($i + 4, count($rows) - 1); $j++) {
                $cells = array();
                foreach ($rows[$j] as $c) { $c = trim((string) $c); if ($c !== '') { $cells[] = $c; } }
                if (count($cells) >= 3) { $screens[$cf]['cols'] = $cells; break; }
            }
        }
    }
}

$T = array('match' => 0, 'syn' => 0, 'missGov' => 0, 'missFn' => 0, 'extra' => 0,
           'soon' => 0, 'board' => 0, 'compared' => 0);
$govNorm = array(); foreach ($GOV as $g) { $govNorm[$norm($g)] = 1; }

foreach ($screens as $cf => $sc) {
    $st = isset($map[$cf]) ? $map[$cf]['state'] : 'soon';
    $real = isset($map[$cf]) ? $map[$cf]['real_path'] : null;
    if ($st === 'soon' || $real === null) { $T['soon']++; continue; }        // لا تُقارن
    $heads = extractHeads($ROOT . '/' . $real, $norm);
    if ($heads === null) { continue; }
    if (!$heads) { $T['board']++; continue; }                                 // لوحةٌ لا تُقارن
    foreach ($UI_COLS as $u) { unset($heads[$norm($u)]); }
    $T['compared']++;

    $docN = array(); foreach ($sc['cols'] as $c) { $docN[$norm($c)] = $c; }
    /* استبعاد أعمدة الواجهة متناظرٌ: ما استُبعد من النظام يُستبعد من المستند
       (وإلا بقي «الإجراء» المستندي ناقصًا لا يُصفَّر) — اجتهاد J-05 في CMP03_EXECUTION_LOG */
    foreach ($UI_COLS as $u) { unset($docN[$norm($u)]); }
    /* ① مطابقٌ حرفي */
    $match = array_intersect_key($docN, $heads);
    $docLeft = array_diff_key($docN, $match);
    $sysLeft = array_diff_key($heads, $match);
    /* ② مرادفٌ ≥60٪ — أفضلُ زوجٍ أولًا وبلا متعدد-لواحد */
    $pairs = array();
    foreach ($docLeft as $dn => $dOrig) {
        foreach ($sysLeft as $hn => $hOrig) { $s = $sim($dn, $hn); if ($s >= 0.6) { $pairs[] = array($s, $dn, $hn); } }
    }
    usort($pairs, function ($a, $b) { return $b[0] <=> $a[0]; });
    $usedD = array(); $usedH = array(); $syn = 0;
    foreach ($pairs as $p) {
        if (isset($usedD[$p[1]]) || isset($usedH[$p[2]])) { continue; }
        $usedD[$p[1]] = 1; $usedH[$p[2]] = 1; $syn++;
    }
    /* ③ الناقصُ فعلًا مفروزًا · ④ الزائد */
    $missG = 0; $missF = 0;
    foreach ($docLeft as $dn => $dOrig) {
        if (isset($usedD[$dn])) { continue; }
        isset($govNorm[$dn]) ? $missG++ : $missF++;
    }
    $extra = count($sysLeft) - count($usedH);

    $T['match'] += count($match); $T['syn'] += $syn;
    $T['missGov'] += $missG; $T['missFn'] += $missF; $T['extra'] += $extra;

    if (!$totalsOnly) {
        printf("■ %s (%s) ×%d → %s\n   🟦%d 🟨%d 🟧حوكمة%d+وظيفي%d 🟩%d\n",
            $sc['title'], $cf, $sc['appear'], $real, count($match), $syn, $missG, $missF, $extra);
    }
}
echo str_repeat('═', 66) . "\n";
printf("شاشاتٌ فريدة: %d · قورنت: %d · قريبًا (لا تُقارن): %d · لوحات (لا تُقارن): %d\n",
    count($screens), $T['compared'], $T['soon'], $T['board']);
printf("🟦 مطابق=%d · 🟨 مرادف=%d · 🟧 ناقصٌ فعلًا=%d (حوكمة %d + وظيفي %d) · 🟩 زائد=%d\n",
    $T['match'], $T['syn'], $T['missGov'] + $T['missFn'], $T['missGov'], $T['missFn'], $T['extra']);
