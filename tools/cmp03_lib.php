<?php
/**
 * tools/cmp03_lib.php — مكتبة CMP-03 المشتركة (تطابق منطق scrdes_compare.php v2 حرفيًّا)
 * تستعملها أدوات الموجات: cmp03_probe · cmp03_apply · cmp03_synonyms · فحص السبعة الحاكمة.
 * لا تغيّر هنا حكمًا قياسيًّا إلا وغيّرته في scrdes_compare.php معه — فهما مرآتان.
 */

/* طبقة الحوكمة المشتركة (CMP-03 ورقة 04) */
function cmp03_gov_labels() {
    return array('الكيان','المرفق','مرجع التفويض','تاريخ الاعتماد','تاريخ الإنشاء','مركز التكلفة',
        'سعر الصرف ومصدره','المرجع الأب','المُنشئ — الاسم والصفة','العملة','المعتمِد — الاسم والصفة',
        'الحالة','مفتاح منع التكرار','معكوس بـ','عكس عن','درجة الأثر','سجل الاطّلاع','سعر الصرف',
        'المستند المرفق','المرفقات','المعتمِد المطلوب','الجهة المُنشئة','مركز التكلفة المحمَّل',
        'العملة الأساسية','مصدر سعر الصرف');
}
function cmp03_ui_cols() {
    return array('اجراءات','الاجراءات','اجراء','الاجراء','تحديد','actions','action','#','id','م');
}

function cmp03_norm($s) {
    $s = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $s)));
    $s = preg_replace('/[()\[\]«»"\'؟?—–\-·—]/u', ' ', $s);
    $s = str_replace(array('أ','إ','آ'), 'ا', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace('ى', 'ي', $s);
    $s = preg_replace('/[ًٌٍَُِّْ]/u', '', $s);
    return preg_replace('/\s+/u', ' ', trim($s));
}

function cmp03_stems($n) {
    $stop = array('و','في','من','الي','على','عن','او','ثم','—','·');
    $out = array();
    foreach (explode(' ', $n) as $w) {
        $w = preg_replace('/^(ال|بال|لل|وال|فال|كال)/u', '', $w);
        if ($w === '' || in_array($w, $stop, true) || mb_strlen($w) < 2) { continue; }
        $out[$w] = 1;
    }
    return $out;
}

function cmp03_sim($a, $b) {
    if ($a === $b) { return 1.0; }
    if ($a !== '' && $b !== '' && (mb_strpos($a, $b) !== false || mb_strpos($b, $a) !== false)) { return 1.0; }
    $sa = cmp03_stems($a); $sb = cmp03_stems($b);
    if (!$sa || !$sb) { return 0.0; }
    $inter = count(array_intersect_key($sa, $sb));
    return $inter / min(count($sa), count($sb));
}

/* رؤوس <th> من مصدر الملف — كما يستخرجها المقارن (نصيًّا لا تنفيذًا) */
function cmp03_extract_heads($path) {
    $src = @file_get_contents($path);
    if ($src === false) { return null; }
    $heads = array();
    if (preg_match_all('/<th\b[^>]*>(.*?)<\/th>/su', $src, $m)) {
        foreach ($m[1] as $h) {
            $h = preg_replace('/<\?php.*?\?>/su', '', $h);
            $h = trim(strip_tags($h));
            if ($h !== '' && mb_strlen($h) < 60 && !preg_match('/^[#\d\W]+$/u', $h)) {
                $heads[cmp03_norm($h)] = $h;
            }
        }
    }
    return $heads;
}

/* شاشات المستند الفريدة بأعمدتها من tmp_SCRDES.xlsx */
function cmp03_doc_screens($ROOT, $onlySheet = null) {
    require_once $ROOT . '/vendor/autoload.php';
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($ROOT . '/tmp_SCRDES.xlsx');
    $reader->setReadDataOnly(true);
    $wb = $reader->load($ROOT . '/tmp_SCRDES.xlsx');
    $screens = array();
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
    return $screens;
}

function cmp03_file_map($conn) {
    $map = array();
    $r = mysqli_query($conn, "SELECT canonical_file, state, real_path FROM nav09_file_map");
    while ($x = mysqli_fetch_assoc($r)) { $map[$x['canonical_file']] = $x; }
    return $map;
}

/* حكم شاشة واحدة كما يحكمها المقارن: يعيد [match, synPairs(doc→sys), missGov[], missFn[], extra[]] */
function cmp03_judge($docCols, $heads) {
    $govNorm = array();
    foreach (cmp03_gov_labels() as $g) { $govNorm[cmp03_norm($g)] = 1; }
    foreach (cmp03_ui_cols() as $u) { unset($heads[cmp03_norm($u)]); }
    $docN = array(); foreach ($docCols as $c) { $docN[cmp03_norm($c)] = $c; }
    $match = array_intersect_key($docN, $heads);
    $docLeft = array_diff_key($docN, $match);
    $sysLeft = array_diff_key($heads, $match);
    $pairs = array();
    foreach ($docLeft as $dn => $dOrig) {
        foreach ($sysLeft as $hn => $hOrig) {
            $s = cmp03_sim($dn, $hn);
            if ($s >= 0.6) { $pairs[] = array($s, $dn, $hn); }
        }
    }
    usort($pairs, function ($a, $b) { return $b[0] <=> $a[0]; });
    $usedD = array(); $usedH = array(); $syn = array();
    foreach ($pairs as $p) {
        if (isset($usedD[$p[1]]) || isset($usedH[$p[2]])) { continue; }
        $usedD[$p[1]] = 1; $usedH[$p[2]] = 1;
        $syn[$p[1]] = $p[2]; // docNorm → sysNorm
    }
    $missGov = array(); $missFn = array();
    foreach ($docLeft as $dn => $dOrig) {
        if (isset($usedD[$dn])) { continue; }
        if (isset($govNorm[$dn])) { $missGov[$dn] = $dOrig; } else { $missFn[$dn] = $dOrig; }
    }
    $extra = array();
    foreach ($sysLeft as $hn => $hOrig) { if (!isset($usedH[$hn])) { $extra[$hn] = $hOrig; } }
    return array('match' => $match, 'syn' => $syn, 'missGov' => $missGov, 'missFn' => $missFn, 'extra' => $extra);
}
