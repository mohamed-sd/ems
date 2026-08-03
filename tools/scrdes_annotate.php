<?php
/**
 * tools/scrdes_annotate.php — مُلوِّنُ SCR-DES-01 بالمقارنة الحية (اعتماد المالك)
 * ───────────────────────────────────────────────────────────────────────────
 * الألوان الخمسة:
 *   🟦 أزرق FFBDD7EE  مطابق (في المستند والنظام)
 *   🟡 أصفر FFFFE699  ناقص (في المستند لا النظام) — وكتلةُ «قريبًا» كلُّها
 *   🟢 أخضر FFC6EFCE  زائد (في النظام لا المستند) — يُلحق بذيل صف الأعمدة
 *   🟧 برتقالي FFF8CBAD  شاشةٌ حيةٌ بلا جدول (لوحة/بطاقات/نموذج)
 * المرادفات السياقية: عامُّ النظام (الرقم/التاريخ/النوع…) يطابق «X المستند»
 * إن كان في كتلة الشاشة عمودٌ واحدٌ بذلك الجذع. و«إجراءات» مستثناةٌ من الزائد.
 * الناتج: docs/files/SCR-DES-01-ملون.xlsx + ورقتا دليل الألوان والخلاصة.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

$C_MATCH = 'FFBDD7EE'; $C_MISS = 'FFFFE699'; $C_EXTRA = 'FFC6EFCE'; $C_BOARD = 'FFF8CBAD';

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
$UI_COLS = array('اجراءات', 'الاجراءات', 'اجراء', 'الاجراء', 'actions', 'action', '#', 'id', 'م');
/* جذوعُ العامّ: عامُّ النظام يلتقي بعمودِ مستندٍ وحيدٍ من جذعه داخل الشاشة */
$GENERIC = array('الرقم' => 'رقم', 'رقم' => 'رقم', 'الكود' => 'رقم', 'كود' => 'رقم',
    'التاريخ' => 'تاريخ', 'تاريخ' => 'تاريخ', 'النوع' => 'نوع', 'نوع' => 'نوع',
    'الحاله' => 'حاله', 'حاله' => 'حاله', 'الاسم' => 'اسم', 'اسم' => 'اسم',
    'الوصف' => 'وصف', 'الكميه' => 'كميه', 'المبلغ' => 'مبلغ', 'الاجمالي' => 'اجمالي',
    'من' => 'من', 'الى' => 'الى', 'الانتهاء' => 'انتهاء', 'الموظف' => 'موظف',
    'المعده' => 'معده', 'المشروع' => 'مشروع', 'الموقع' => 'موقع', 'المورد' => 'مورد');

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
/** جذعُ عمودٍ: أولُ كلمةٍ معياريةً (رقم الطلب → رقم) */
function stemOf($n) { $w = explode(' ', $n); return $w[0]; }

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($ROOT . '/tmp_SCRDES.xlsx');
$wb = $reader->load($ROOT . '/tmp_SCRDES.xlsx');

$summary = array();
foreach ($wb->getSheetNames() as $sheetName) {
    if (!preg_match('/^\d{2} · /u', $sheetName)) { continue; }
    $ws = $wb->getSheetByName($sheetName);
    $maxRow = $ws->getHighestDataRow();
    $tot = array('match' => 0, 'miss' => 0, 'extra' => 0, 'soon' => 0, 'board' => 0, 'screens' => 0);

    for ($row = 1; $row <= $maxRow; $row++) {
        $c0 = trim((string) $ws->getCell('A' . $row)->getValue());
        if (mb_substr($c0, 0, 1) !== '■') { continue; }
        if (!preg_match('/■\s*(.+?)\s+·\s+([a-z0-9_.]+\.php)/u', $c0, $m)) { continue; }
        $cf = trim($m[2]);
        $tot['screens']++;

        /* صفُّ الأعمدة: أولُ صفٍّ تالٍ بأربعِ خلايا فأكثر */
        $hdrRow = null; $docCols = array();
        for ($rr = $row + 1; $rr <= min($row + 4, $maxRow); $rr++) {
            $cells = array();
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($ws->getHighestDataColumn($rr));
            for ($cc = 1; $cc <= $maxCol; $cc++) {
                $v = trim((string) $ws->getCell([$cc, $rr])->getValue());
                if ($v !== '') { $cells[$cc] = $v; }
            }
            if (count($cells) >= 4) { $hdrRow = $rr; $docCols = $cells; break; }
        }
        if ($hdrRow === null) { continue; }

        $st = isset($map[$cf]) ? $map[$cf]['state'] : 'soon';
        $real = isset($map[$cf]) ? $map[$cf]['real_path'] : null;

        $paint = function ($col, $rowN, $argb) use ($ws) {
            $ws->getCell([$col, $rowN])->getStyle()
               ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
               ->getStartColor()->setARGB($argb);
        };

        if ($st === 'soon' || $real === null) {                     /* 🟡 كتلة قريبًا */
            $tot['soon']++; $tot['miss'] += count($docCols);
            $paint(1, $row, $C_MISS);
            foreach ($docCols as $cc => $v) { $paint($cc, $hdrRow, $C_MISS); }
            $ws->setCellValue([count($docCols) + 2, $row], '🟡 قريبًا — الشاشة لم تُبنَ بعد');
            continue;
        }
        $heads = extractHeads($ROOT . '/' . $real, $norm);
        if (!$heads) {                                              /* 🟧 لوحة بلا جدول */
            $tot['board']++;
            $paint(1, $row, $C_BOARD);
            $ws->setCellValue([count($docCols) + 2, $row],
                '🟧 الشاشة الحية لوحة/بطاقات بلا جدول (' . $real . ') — تُقاس يدويًّا');
            continue;
        }

        /* استثناء أعمدة التحكم من النظام */
        foreach ($UI_COLS as $u) { unset($heads[$norm($u)]); }

        $docNorm = array(); foreach ($docCols as $cc => $v) { $docNorm[$norm($v)] = $cc; }
        /* جذوعُ المستند لمطابقة العامّ السياقية */
        $stemCount = array(); $stemCol = array();
        foreach ($docNorm as $dn => $cc) { $st2 = stemOf($dn); $stemCount[$st2] = ($stemCount[$st2] ?? 0) + 1; $stemCol[$st2] = $dn; }

        $matchedDoc = array(); $extra = array();
        foreach ($heads as $hn => $orig) {
            if (isset($docNorm[$hn])) { $matchedDoc[$hn] = 1; continue; }
            if (isset($GENERIC[$hn])) {
                $st2 = $GENERIC[$hn];
                if (($stemCount[$st2] ?? 0) === 1 && !isset($matchedDoc[$stemCol[$st2]])) {
                    $matchedDoc[$stemCol[$st2]] = 1; continue;      /* عامٌّ التقى بوحيد جذعه */
                }
            }
            $extra[$hn] = $orig;
        }
        foreach ($docNorm as $dn => $cc) {
            $isMatch = isset($matchedDoc[$dn]);
            $paint($cc, $hdrRow, $isMatch ? $C_MATCH : $C_MISS);
            $isMatch ? $tot['match']++ : $tot['miss']++;
        }
        if ($extra) {                                               /* 🟢 الزائد يُلحق بذيل الصف */
            $startCol = max(array_keys($docCols)) + 2;
            $ws->setCellValue([$startCol, $hdrRow], '⇐ زائد في النظام:');
            $paint($startCol, $hdrRow, $C_EXTRA);
            $i = 1;
            foreach ($extra as $orig) {
                $ws->setCellValue([$startCol + $i, $hdrRow], $orig);
                $paint($startCol + $i, $hdrRow, $C_EXTRA);
                $i++; $tot['extra']++;
            }
        }
    }
    $summary[$sheetName] = $tot;
    echo sprintf("%s: شاشات=%d مطابق=%d ناقص=%d زائد=%d قريبًا=%d لوحات=%d\n",
        $sheetName, $tot['screens'], $tot['match'], $tot['miss'], $tot['extra'], $tot['soon'], $tot['board']);
}

/* ورقةُ دليل الألوان والخلاصة تتصدران المصنف */
$legend = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($wb, '00 — دليل الألوان والخلاصة');
$wb->addSheet($legend, 0);
$legend->setRightToLeft(true);
$legend->setCellValue('A1', 'SCR-DES-01 — المقارنة الملونة بالنظام الحي · ' . 'أُنتجت آليًّا بمقارنة أعمدة كل شاشة برؤوس جداول ملفها الفعلي');
$rows = array(
    array('اللون', 'المعنى'),
    array('مطابق', 'العمود في المستند وموجود في النظام'),
    array('ناقص', 'في المستند ولا وجود له في النظام — يُبنى (وكتلة «قريبًا» كلها)'),
    array('زائد', 'موجود في النظام وغير مذكور في المستند — أُلحق أخضر بذيل صف الأعمدة'),
    array('لوحة', 'الشاشة الحية لوحة/بطاقات بلا جدول — تُقاس يدويًّا'),
);
$colors = array(null, $C_MATCH, $C_MISS, $C_EXTRA, $C_BOARD);
foreach ($rows as $i => $rw) {
    $legend->setCellValue('A' . ($i + 3), $rw[0]);
    $legend->setCellValue('B' . ($i + 3), $rw[1]);
    if ($colors[$i]) {
        $legend->getCell('A' . ($i + 3))->getStyle()->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB($colors[$i]);
    }
}
$legend->setCellValue('A10', 'الخلاصة بالإدارة:');
$legend->fromArray(array('الإدارة', 'شاشات', 'مطابق', 'ناقص', 'زائد', 'قريبًا', 'لوحات'), null, 'A11');
$rn = 12;
foreach ($summary as $sn => $t) {
    $legend->fromArray(array($sn, $t['screens'], $t['match'], $t['miss'], $t['extra'], $t['soon'], $t['board']), null, 'A' . $rn);
    $rn++;
}
$legend->getColumnDimension('A')->setWidth(30);
$legend->getColumnDimension('B')->setWidth(80);

$out = $ROOT . '/docs/files/SCR-DES-01-ملون.xlsx';
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($wb);
$writer->save($out);
echo "✔ حُفظ: docs/files/SCR-DES-01-ملون.xlsx\n";
