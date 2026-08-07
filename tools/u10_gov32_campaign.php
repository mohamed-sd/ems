<?php
/**
 * tools/u10_gov32_campaign.php — إكمال الأعمدة الحاكمة للوحدات الناقصة (U10-B12)
 * ───────────────────────────────────────────────────────────────────────────
 * الوحدات (مقام 288) بلا أثر gov_columns/ems-gov-th تُحصر ويُستخرج نوع النقص:
 *   · شاشة بجدول مستند → تُحقن الرؤوس الحاكمة بحسب طبيعتها (ورقة 22):
 *     «مستند يُعتمد» = السبعة كاملة · وإلا النواة الأربعة (كيان·منشئ·تاريخ·أب)
 *   · شاشة بلا جدول (لوحة/نموذج/بوابة) → تُعلن مستثناة بسببها (عرف DEF-005)
 * الخلايا يحشوها ui-unification.js مركزيًّا — والحقن رؤوس فقط + لنت لكل ملف.
 * php tools/u10_gov32_campaign.php [--apply]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once dirname(__DIR__) . '/vendor/autoload.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };
$PHP_BIN = PHP_BINARY;

/* طبيعة الشاشات من ورقة 22 (بالملف القانوني) → real_path */
$nature = array(); // real_path => الطبيعة
$F = $ROOT . '/docs/update0010/INJAZ-MASTER-MAP-1.xlsx';
$map = array();
$r = mysqli_query($conn, "SELECT canonical_file, real_path FROM nav09_file_map WHERE real_path IS NOT NULL");
while ($x = mysqli_fetch_assoc($r)) { $map[$x['canonical_file']] = $x['real_path']; }
if (is_file($F)) {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($F);
    $reader->setReadDataOnly(true);
    $reader->setLoadSheetsOnly(array('22 — طبيعة الشاشة'));
    $wb = $reader->load($F);
    $sh = $wb->getSheetByName('22 — طبيعة الشاشة');
    foreach ($sh->toArray(null, true, false, false) as $i => $row) {
        $cf = trim((string) ($row[2] ?? ''));
        $nat = trim((string) ($row[4] ?? ''));
        if ($i === 0 || $cf === '' || !isset($map[$cf])) { continue; }
        $nature[$map[$cf]] = $nat;
    }
}

$GOV7 = '                    <!-- U10-B12: النواة الحاكمة (الخلايا يحشوها ui-unification.js) -->' . "\n"
      . '                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>' . "\n"
      . '                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة">المُنشئ — الاسم والصفة</th>' . "\n"
      . '                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>' . "\n"
      . '                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد">مرجع التفويض</th>' . "\n"
      . '                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه">المرجع الأب</th>' . "\n"
      . '                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء">تاريخ الإنشاء</th>' . "\n"
      . '                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد">تاريخ الاعتماد</th>' . "\n";
$GOV4 = '                    <!-- U10-B12: النواة الحاكمة (الخلايا يحشوها ui-unification.js) -->' . "\n"
      . '                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>' . "\n"
      . '                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ السجل وبأي صفة">المُنشئ — الاسم والصفة</th>' . "\n"
      . '                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء">تاريخ الإنشاء</th>' . "\n"
      . '                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="السجل الذي تولد عنه">المرجع الأب</th>' . "\n";

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
              'Fleet','Governance','Maintenance','Movement','Operations','Portal','Procurement',
              'Settings','Suppliers','Tickets','Timesheet','Transport','Warehouse','Workforce','main');
$missing = array();
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        if (strpos($src, 'insidebar') === false) { continue; }
        if (strpos($src, 'gov_columns') !== false || strpos($src, 'ems-gov-th') !== false) { continue; }
        $missing[] = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
    }
}

$csv = $ROOT . '/docs/update0010/GOV32_LEDGER.csv';
$fh = fopen($csv, 'w');
fwrite($fh, "\xEF\xBB\xBF");
fputcsv($fh, array('screen', 'nature', 'gap_type', 'action'));

$cnt = array('inject7' => 0, 'inject4' => 0, 'declared' => 0, 'lint_fail' => 0);
foreach ($missing as $rel) {
    $file = $ROOT . '/' . $rel;
    $src = file_get_contents($file);
    $nat = isset($nature[$rel]) ? $nature[$rel] : '';
    /* موضع الحقن: نهاية أول ترويسة جدول فيها ثلاثة رؤوس فأكثر */
    $anchor = null;
    if (preg_match('~</tr>\s*</thead>~u', $src, $m, PREG_OFFSET_CAPTURE)) {
        $head = substr($src, 0, $m[0][1]);
        if (substr_count(substr($head, max(0, strrpos($head, '<thead'))), '<th') >= 3) {
            $anchor = $m[0][1];
        }
    }
    if ($anchor === null) {
        $cnt['declared']++;
        fputcsv($fh, array($rel, $nat, 'no-doc-table', 'declared — لوحة/نموذج/بوابة بلا جدول مستند (عرف DEF-005)'));
        continue;
    }
    $isDoc = ($nat === 'مستندٌ يُعتمد');
    $block = $isDoc ? $GOV7 : $GOV4;
    fputcsv($fh, array($rel, $nat, $isDoc ? 'missing-7' : 'missing-4', 'inject'));
    if (!$APPLY) { $cnt[$isDoc ? 'inject7' : 'inject4']++; continue; }
    $bak = $src;
    file_put_contents($file, substr($src, 0, $anchor) . $block . substr($src, $anchor));
    exec('"' . $PHP_BIN . '" -l ' . escapeshellarg($file) . ' 2>&1', $out, $rc);
    if ($rc !== 0) { file_put_contents($file, $bak); $cnt['lint_fail']++; continue; }
    $cnt[$isDoc ? 'inject7' : 'inject4']++;
}
fclose($fh);

$o('══ U10-B12 — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' · ناقصة: ' . count($missing) . ' ══');
$o("  حقن السبعة (مستند يعتمد): {$cnt['inject7']} · حقن النواة الأربعة: {$cnt['inject4']}");
$o("  معلنة بلا جدول مستند: {$cnt['declared']} · فشل لنت (رجعت): {$cnt['lint_fail']}");
$o('الدفتر: ' . $csv);
