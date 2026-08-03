<?php
/**
 * tools/cmp03_extras_sheet.php — مولّد ورقة قرار المالك للزائد (CMP-03 الموجة ⑦)
 * ───────────────────────────────────────────────────────────────────────────
 * «خبرة تشغيلية راكمها النظام — بعضها يستحق الدخول» (ورقة 06). لكل عمودٍ زائدٍ
 * في شاشةٍ مقارنةٍ صفُّ قرارٍ بحكم CMP مقترح:
 *   «يدخل المستند» — عمودُ بياناتٍ حقيقيٍّ راكمه التشغيل (الافتراض)، ويقوّى
 *     إن كان موثقًا بالاسم نفسه في شاشةِ مستندٍ أخرى.
 *   «مرشح للإلغاء» — قريبُ التشابه بعمودٍ مستنديٍّ في الشاشة نفسها (شبهة
 *     ازدواج) أو من ألفاظ الواجهة.
 * القرار قرارُ المالك وحده — العمود الأخير فارغ. المطبق: cmp03_extras_apply.php
 *
 * المخرج: docs/CMP03_EXTRAS_DECISION_ar.csv (BOM للأكسل) + خلاصة عد.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
require_once __DIR__ . '/cmp03_lib.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);

$screens = cmp03_doc_screens($ROOT);
$map = cmp03_file_map($conn);

/* فهرس: أي تسمية موثقة في أي شاشة مستند */
$docAnywhere = array();
foreach ($screens as $cf => $sc) {
    foreach ($sc['cols'] as $c) { $docAnywhere[cmp03_norm($c)][$cf] = 1; }
}

$uiWords = array('تفاصيل','عرض','خيارات','ملاحظه','ملاحظات','الوصف المختصر');

$rows = array(); $nKeep = 0; $nDrop = 0;
foreach ($screens as $cf => $sc) {
    if (!isset($map[$cf]) || $map[$cf]['state'] === 'soon' || !$map[$cf]['real_path']) { continue; }
    $heads = cmp03_extract_heads($ROOT . '/' . $map[$cf]['real_path']);
    if (!$heads) { continue; }
    $j = cmp03_judge($sc['cols'], $heads);
    if (!$j['extra']) { continue; }
    $docN = array(); foreach ($sc['cols'] as $c) { $docN[cmp03_norm($c)] = $c; }
    foreach ($j['extra'] as $hn => $orig) {
        $verdict = 'يدخل المستند'; $why = 'خبرة تشغيلية راكمها النظام';
        if (in_array($hn, array_map('cmp03_norm', $uiWords), true)) {
            $verdict = 'مرشح للإلغاء'; $why = 'من ألفاظ الواجهة لا البيانات';
        } else {
            $bestSim = 0.0; $bestDoc = '';
            foreach ($docN as $dn => $dOrig) {
                $s = cmp03_sim($hn, $dn);
                if ($s > $bestSim) { $bestSim = $s; $bestDoc = $dOrig; }
            }
            if ($bestSim >= 0.4) {
                $verdict = 'مرشح للإلغاء';
                $why = 'شبهة ازدواج مع «' . $bestDoc . '» في الشاشة نفسها';
            } elseif (isset($docAnywhere[$hn])) {
                $others = array_diff(array_keys($docAnywhere[$hn]), array($cf));
                if ($others) { $why = 'موثق بالاسم نفسه في: ' . implode(' · ', array_slice($others, 0, 3)); }
            }
        }
        $verdict === 'يدخل المستند' ? $nKeep++ : $nDrop++;
        $rows[] = array($map[$cf]['real_path'], $cf, $sc['title'], $sc['owner'], $orig, $verdict, $why, '');
    }
}

$f = $ROOT . '/docs/CMP03_EXTRAS_DECISION_ar.csv';
$fh = fopen($f, 'w');
fwrite($fh, "\xEF\xBB\xBF"); // BOM — الأكسل يقرأ العربية
fputcsv($fh, array('الملف','الشاشة القانونية','اسم الشاشة','الورقة المالكة','العمود الزائد','حكم CMP المقترح','السبب','قرار المالك (يدخل المستند / يُلغى)'));
foreach ($rows as $r) { fputcsv($fh, $r); }
fclose($fh);

echo "✎ docs/CMP03_EXTRAS_DECISION_ar.csv — " . count($rows) . " صفًّا (مقترح الدخول: $nKeep · مرشح للإلغاء: $nDrop)\n";
echo "القرار قرار المالك — التطبيق بعد ملء العمود الأخير: php tools/cmp03_extras_apply.php --diff\n";
