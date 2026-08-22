<?php
/**
 * tools/u12_reverse_audit.php — هندسةٌ عكسيةٌ: أيطابق المبنيُّ الوثيقةَ؟
 * ═══════════════════════════════════════════════════════════════════════════
 * لا يثق هذا الفاحصُ بتقريرِ التنفيذِ ولا ببواباتِ القبول. يبدأ من الوثيقةِ
 * نفسِها: يقرأ جداولَها المستخرجةَ في `docs/update0012/extracted/*.md`، يستخرج
 * منها سجلَّ الشاشاتِ وعقودَ الأفعالِ والأرقامَ الحاكمة، ثم يقيس الحيَّ —
 * القرصَ والقاعدةَ — ويقارن صفًّا صفًّا.
 *
 * الفرقُ بينه وبين بواباتِ القبول: البوابةُ تسأل «أنجحَ ما بنيتُه؟» وهذا يسأل
 * **«أبنيتُ ما طُلب؟»** — فيكشف الغائبَ لا المعطوبَ فقط.
 *
 * ما يُقارَن:
 *   ① سجلُّ شاشاتِ M-10 الـ38 · وM-14 الـ33 — وجودًا وتسجيلًا
 *   ② عقودُ الأفعال — عددًا وحضورًا في القاموس
 *   ③ الأرقامُ الحاكمةُ المعلَنةُ في الوثيقةِ مقابلَ الحيّ
 *   ④ دليلُ الحسابات: المستويات · الأبعاد · القوائم · الهوامش
 *   ⑤ نواةُ المكوناتِ العشرون (UXR §٤-٤)
 *   ⑥ المحركاتُ: أتعمل فعلًا أم مجردُ ملفاتٍ موجودة؟
 *
 * التشغيل: php tools/u12_reverse_audit.php [--md=مسار]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$SPEC = $ROOT . '/docs/update0012/extracted';
$mdOut = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $mdOut = substr($a, 5); } }

/* ── القاعدة ────────────────────────────────────────────────────────────── */
$cfg = array('host' => 'localhost', 'port' => 3307, 'user' => 'root', 'pass' => '', 'db' => 'equipation_manage');
if (is_file($ROOT . '/.env')) {
    foreach (file($ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) { continue; }
        list($k, $v) = explode('=', $ln, 2); $k = trim($k); $v = trim($v);
        if ($k === 'DB_HOST') { $hp = explode(':', $v); $cfg['host'] = $hp[0]; if (isset($hp[1])) { $cfg['port'] = (int) $hp[1]; } }
        if ($k === 'DB_USER') { $cfg['user'] = $v; }
        if ($k === 'DB_PASS') { $cfg['pass'] = $v; }
        if ($k === 'DB_NAME') { $cfg['db'] = $v; }
    }
}
$db = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db'], $cfg['port']);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');
function n($db, $sql) { $r = @$db->query($sql); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? (int) $x[0] : 0; }
function col($db, $sql) { $o = array(); if ($r = @$db->query($sql)) { while ($x = $r->fetch_row()) { $o[] = $x[0]; } } return $o; }

/* ── قارئُ الوثيقة ──────────────────────────────────────────────────────── */
function spec($SPEC, $name) { $p = $SPEC . '/' . $name; return is_file($p) ? (string) file_get_contents($p) : ''; }

/**
 * يستخرج جدولَ سجلِّ الشاشاتِ من الوثيقة: الأعمدةُ [الشاشة · الملف · …].
 * يُرجع خريطةَ الملفِّ ⇐ الاسم.
 */
function spec_screens($doc, $heading)
{
    $out = array();
    $pos = mb_strpos($doc, $heading);
    if ($pos === false) { return $out; }
    $chunk = mb_substr($doc, $pos, 20000);
    foreach (explode("\n", $chunk) as $i => $line) {
        if ($i > 0 && strpos($line, '## ▐') === 0) { break; }
        if (strpos($line, '|') !== 0) { continue; }
        $c = array_map('trim', explode('|', trim($line, '|')));
        if (count($c) < 3) { continue; }
        if (!preg_match('~^[A-Za-z0-9_]+\.php$~', $c[1])) { continue; }
        $out[$c[1]] = $c[0];
    }
    return $out;
}

/** يستخرج رموزَ الأفعالِ من جدولِ عقودِ الأفعال */
function spec_actions($doc, $heading)
{
    $out = array();
    $pos = mb_strpos($doc, $heading);
    if ($pos === false) { return $out; }
    $chunk = mb_substr($doc, $pos, 30000);
    foreach (explode("\n", $chunk) as $i => $line) {
        if ($i > 0 && strpos($line, '## ▐') === 0) { break; }
        if (strpos($line, '|') !== 0) { continue; }
        $c = array_map('trim', explode('|', trim($line, '|')));
        foreach ($c as $cell) {
            if (preg_match('~^[a-z][a-z0-9_]*\.[a-z][a-z0-9_.]*$~', $cell)) { $out[$cell] = true; break; }
        }
    }
    return array_keys($out);
}

$M10 = spec($SPEC, 'M-10.md');
$M14 = spec($SPEC, 'M-14.md');
$UXR = spec($SPEC, 'UXR-01.md');

$rows = array();
$fail = 0; $warn = 0;
function judge(&$rows, &$fail, &$warn, $sec, $item, $want, $got, $ok, $note = '')
{
    if ($ok === true) { $mark = '✔'; }
    elseif ($ok === null) { $mark = '◐'; $warn++; }
    else { $mark = '✘'; $fail++; }
    $rows[] = array($sec, $item, $want, $got, $mark, $note);
}

/* ═══ ① سجلُّ شاشاتِ M-10 ═══════════════════════════════════════════════ */
$m10Screens = spec_screens($M10, '٦-١  سجلُّ الشاشاتِ الكامل');
$liveModules = array();
foreach (col($db, "SELECT code FROM modules") as $c) { $liveModules[strtolower(str_replace('\\', '/', $c))] = true; }
$fileMapReal = array();
if ($r = @$db->query("SELECT canonical_file, real_path, state FROM nav09_file_map")) {
    while ($x = $r->fetch_assoc()) { $fileMapReal[strtolower(trim((string) $x['canonical_file']))] = $x; }
}

/**
 * يحلُّ اسمَ الملفِّ القانونيِّ إلى مسارِه الحقيقيّ. **الحلُّ بالخريطةِ أولًا**:
 * الوثيقةُ تسمّي `invoices.php` والحيُّ `Finance/invoices_fin.php` — و`nav09_file_map`
 * هي عقدُ الترجمةِ بينهما. البحثُ بالاسمِ المجرَّدِ وحدَه يقول «غائب» عن حاضرٍ
 * مُحالٍ، وهذا خطأُ فاحصٍ لا خطأُ نظام.
 */
function resolve_screen($ROOT, $fileMapReal, $file)
{
    $k = strtolower(trim($file));
    if (isset($fileMapReal[$k]) && !empty($fileMapReal[$k]['real_path'])) {
        $rp = ltrim(str_replace('\\', '/', $fileMapReal[$k]['real_path']), '/');
        if (is_file($ROOT . '/' . $rp)) { return array($rp, $fileMapReal[$k]['state']); }
    }
    $paths = glob($ROOT . '/*/' . $file);
    if (!empty($paths)) { return array(str_replace('\\', '/', substr($paths[0], strlen($ROOT) + 1)), 'unmapped'); }
    return array(null, isset($fileMapReal[$k]) ? $fileMapReal[$k]['state'] : null);
}

$missFile = array(); $missModule = array(); $okScreens = 0; $soon10 = array();
foreach ($m10Screens as $file => $name) {
    list($rel, $state) = resolve_screen($ROOT, $fileMapReal, $file);
    if ($rel === null) {
        if ($state === 'soon') { $soon10[] = $file; }
        else { $missFile[] = $file . ' («' . $name . '»)'; }
        continue;
    }
    if (!isset($liveModules[strtolower($rel)])) { $missModule[] = $rel . ' («' . $name . '»)'; continue; }
    $okScreens++;
}
judge($rows, $fail, $warn, 'M-10', 'سجلُّ الشاشاتِ — ملفٌّ حيٌّ لكلِّ صفٍّ في الوثيقة',
    count($m10Screens), $okScreens, count($missFile) === 0 && count($missModule) === 0,
    (count($missFile) ? 'بلا ملف: ' . implode(' · ', array_slice($missFile, 0, 8)) : '')
    . (count($missModule) ? ' — بلا وحدةِ صلاحيات: ' . implode(' · ', array_slice($missModule, 0, 8)) : '')
    . (count($soon10) ? ' — معلَنٌ «قريبًا»: ' . implode(' · ', $soon10) : ''));

/* ═══ ② سجلُّ شاشاتِ M-14 ═══════════════════════════════════════════════ */
$m14Screens = spec_screens($M14, '٦-١  سجلُّ الشاشاتِ الكامل');
$miss14 = array(); $ok14 = 0; $soon14 = array();
foreach ($m14Screens as $file => $name) {
    list($rel, $state) = resolve_screen($ROOT, $fileMapReal, $file);
    if ($rel === null) {
        if ($state === 'soon') { $soon14[] = $file; } else { $miss14[] = $file . ' («' . $name . '»)'; }
        continue;
    }
    if (!isset($liveModules[strtolower($rel)])) { $miss14[] = $rel . ' (بلا وحدة)'; continue; }
    $ok14++;
}
judge($rows, $fail, $warn, 'M-14', 'سجلُّ الشاشاتِ — ملفٌّ حيٌّ لكلِّ صفٍّ في الوثيقة',
    count($m14Screens), $ok14, count($miss14) === 0,
    (count($miss14) ? 'الناقص: ' . implode(' · ', array_slice($miss14, 0, 8)) : '')
    . (count($soon14) ? ' — معلَنٌ «قريبًا»: ' . implode(' · ', $soon14) : ''));

/* ═══ ③ الأرقامُ الحاكمةُ المعلَنة ═══════════════════════════════════════ */
$declared = function ($doc, $label) {
    if (preg_match('~\|\s*' . preg_quote($label, '~') . '\s*\|\s*(\d+)~u', $doc, $m)) { return (int) $m[1]; }
    return null;
};
$m10DocScreens = $declared($M10, 'الشاشاتُ المملوكة');
$m10DocActions = $declared($M10, 'الأفعالُ بعقودها');
$m14DocScreens = $declared($M14, 'الشاشاتُ المملوكة');
$m14DocActions = $declared($M14, 'الأفعالُ بعقودها');

judge($rows, $fail, $warn, 'M-10', 'العددُ المعلَنُ في الوثيقةِ = صفوفُ سجلِّ الشاشات',
    $m10DocScreens, count($m10Screens), $m10DocScreens === count($m10Screens),
    'اتساقُ الوثيقةِ مع نفسِها');
judge($rows, $fail, $warn, 'M-14', 'العددُ المعلَنُ في الوثيقةِ = صفوفُ سجلِّ الشاشات',
    $m14DocScreens, count($m14Screens), $m14DocScreens === count($m14Screens),
    'اتساقُ الوثيقةِ مع نفسِها');

/* ═══ ④ عقودُ الأفعال ═══════════════════════════════════════════════════ */
$actsDoc10 = spec_actions($M10, '٧-١  عقودُ الأفعالِ الكاملة');
$actsDoc14 = spec_actions($M14, '٧-١  عقودُ الأفعالِ الكاملة');
$dictAll = array();
foreach (col($db, "SELECT canonical_code FROM nav09_action_map") as $c) { $dictAll[strtolower(trim($c))] = true; }
foreach (col($db, "SELECT live_code FROM nav09_action_map WHERE live_code IS NOT NULL AND live_code <> ''") as $c) { $dictAll[strtolower(trim($c))] = true; }

$notInDict10 = array();
foreach ($actsDoc10 as $a) { if (!isset($dictAll[strtolower($a)])) { $notInDict10[] = $a; } }
$notInDict14 = array();
foreach ($actsDoc14 as $a) { if (!isset($dictAll[strtolower($a)])) { $notInDict14[] = $a; } }

judge($rows, $fail, $warn, 'M-10', 'أفعالُ الوثيقةِ حاضرةٌ في قاموسِ الأفعال',
    count($actsDoc10), count($actsDoc10) - count($notInDict10), count($notInDict10) === 0,
    count($notInDict10) ? 'الغائب: ' . implode(' · ', array_slice($notInDict10, 0, 12)) : '');
judge($rows, $fail, $warn, 'M-14', 'أفعالُ الوثيقةِ حاضرةٌ في قاموسِ الأفعال',
    count($actsDoc14), count($actsDoc14) - count($notInDict14), count($notInDict14) === 0,
    count($notInDict14) ? 'الغائب: ' . implode(' · ', array_slice($notInDict14, 0, 12)) : '');

/* ═══ ⑤ دليلُ الحسابات ═════════════════════════════════════════════════ */
/* المقامُ من الورقةِ نفسِها لا من ذاكرةِ كاتبٍ: تُقرأ ورقةُ الشجرةِ في ملفِّ
   دليلِ الحسابات فتُملي عددَ الحساباتِ وتوزيعَ مستوياتِها. */
$specAcc = null; $specLevels = null;
$xlsx = $ROOT . '/docs/update0012/EQUIPATION-COA-01 — دليل الحسابات المعاد هيكلته.xlsx';
if (class_exists('ZipArchive') && is_file($xlsx)) {
    $z = new ZipArchive();
    if ($z->open($xlsx) === true) {
        $shared = array();
        if ($s = $z->getFromName('xl/sharedStrings.xml')) {
            $sx = @simplexml_load_string($s);
            if ($sx) { foreach ($sx->si as $si) { $shared[] = strip_tags($si->asXML()); } }
        }
        if ($x = $z->getFromName('xl/worksheets/sheet2.xml')) {   /* الورقة 01 — الشجرة */
            $sx = @simplexml_load_string($x);
            if ($sx) {
                $lv = array(); $cnt = 0;
                foreach ($sx->sheetData->row as $i => $row) {
                    if ($i === 0) { continue; }
                    foreach ($row->c as $c) {
                        if (preg_replace('/\d+/', '', (string) $c['r']) !== 'E') { continue; }
                        $v = (string) $c->v;
                        if ((string) $c['t'] === 's') { $v = isset($shared[(int) $v]) ? trim($shared[(int) $v]) : ''; }
                        if ($v === '') { continue; }
                        $lv[$v] = true; $cnt++;
                    }
                }
                if ($cnt > 0) { $specAcc = $cnt; $specLevels = count($lv); }
            }
        }
        $z->close();
    }
}
if ($specAcc === null) { $specAcc = 126; $specLevels = 3; }

$coaCanon = n($db, "SELECT COUNT(*) FROM fin_chart_of_accounts WHERE is_canonical = 1");
$coaLevels = n($db, "SELECT COUNT(DISTINCT acc_level) FROM fin_chart_of_accounts WHERE is_canonical = 1 AND acc_level IS NOT NULL");
$coaLegacyActive = n($db, "SELECT COUNT(*) FROM fin_chart_of_accounts WHERE is_canonical = 0 AND active = 1");
judge($rows, $fail, $warn, 'COA', 'الحساباتُ ومستوياتُها كما في ورقةِ الشجرة',
    $specAcc . ' حسابًا / ' . $specLevels . ' مستويات', $coaCanon . ' / ' . $coaLevels,
    $coaCanon === $specAcc && $coaLevels === $specLevels, 'المقامُ مقروءٌ من الورقة 01 في ملفِّ دليلِ الحسابات');
judge($rows, $fail, $warn, 'COA', 'صفرُ حسابٍ قديمٍ نشطٍ بعد الترحيل',
    0, $coaLegacyActive, $coaLegacyActive === 0, 'القديمُ يُعطَّل ولا يُحذف');

/* الأبعادُ التسعةُ أعمدةٌ مسمّاةٌ لا `dim_N` — والخريطةُ في CoaService::DIM_COLUMN */
$jlCols = array();
if ($r = @$db->query("SHOW COLUMNS FROM fin_journal_lines")) {
    while ($x = $r->fetch_assoc()) { $jlCols[strtolower($x['Field'])] = true; }
}
require_once $ROOT . '/app/Services/Finance/CoaService.php';
$dimMap = \App\Services\Finance\CoaService::DIM_COLUMN;
$dimMiss = array();
foreach ($dimMap as $d => $c) { if (!isset($jlCols[strtolower($c)])) { $dimMiss[] = $d . '⇐' . $c; } }
judge($rows, $fail, $warn, 'COA', 'الأبعادُ التسعةُ D1..D9 لها أعمدةٌ على سطرِ القيد',
    9, 9 - count($dimMiss), count($dimMiss) === 0,
    count($dimMiss) ? 'الغائب: ' . implode(' · ', $dimMiss)
        : 'أعمدةٌ مسمّاةٌ لا dim_N — الخريطةُ في CoaService::DIM_COLUMN');

$mapRows = n($db, "SELECT COUNT(*) FROM fin_coa_migration");
$orphanLines = n($db, "SELECT COUNT(*) FROM fin_journal_lines jl
                        LEFT JOIN fin_chart_of_accounts a ON a.id = jl.account_id
                       WHERE a.id IS NULL");
judge($rows, $fail, $warn, 'COA', 'صفرُ سطرِ قيدٍ يشير لحسابٍ غيرِ موجود',
    0, $orphanLines, $orphanLines === 0, 'خريطةُ الترحيل: ' . $mapRows . ' صفًّا');

/* ═══ ⑥ محركاتُ التحليلِ الماليّ ═══════════════════════════════════════ */
$ratioDefs = n($db, "SELECT COUNT(*) FROM fin_ratio_targets");
/* ◆ **التعريفُ ما يُستهلَك لا ما يُخزَّن**: الصفُّ المحجورُ (`active = 0`)
 *   باقٍ لأثرِه التدقيقيِّ ولا يقرؤه محركٌ ولا شاشة، فعدُّه تعريفًا يجعل
 *   المقياسَ يحاسب الوثيقةَ على ما لا تحكمه. و`tools/m10_ac_gate.php`
 *   يعدُّ أنواعَ العقودِ بـ`active = 1` سلفًا — فهذا توحيدٌ لا تخفيف.
 *   **وهو أشدُّ لا أرخى**: التلوّثُ يصل نشطًا فيُمسَك، والمحجورُ لا يُحسَب. */
$signalDefs = n($db, "SELECT COUNT(*) FROM fin_signal_rules WHERE active = 1");
$postRows = n($db, "SELECT COUNT(*) FROM fin_posting_matrix");
$ctypes = n($db, "SELECT COUNT(*) FROM fin_contract_types WHERE active = 1");
judge($rows, $fail, $warn, 'M-10', 'النسبُ الماليةُ 44 معرَّفة', 44, $ratioDefs, $ratioDefs === 44, '');
judge($rows, $fail, $warn, 'M-10', 'الإشاراتُ 16 معرَّفة', 16, $signalDefs, $signalDefs === 16, '');
judge($rows, $fail, $warn, 'M-10', 'مصفوفةُ الترحيلِ 27 صفًّا', 27, $postRows, $postRows === 27, '');
judge($rows, $fail, $warn, 'M-10', 'أنواعُ العقودِ 18 (EC-01..08 · FC-01..10)', 18, $ctypes, $ctypes === 18, '');

/* الأثرُ الحيّ: أحُسبت النسبُ فعلًا أم بقيت التعاريفُ وحدَها؟ */
$ratioVals = n($db, "SELECT COUNT(DISTINCT ratio_code) FROM fin_ratio_values");
judge($rows, $fail, $warn, 'M-10', 'النسبُ محسوبةٌ حيًّا لا معرَّفةً فقط',
    44, $ratioVals, $ratioVals === 44, 'قيمٌ في fin_ratio_values');

$cfBad = n($db, "SELECT COUNT(*) FROM fin_cashflow WHERE balance_ok = 0");
$eqBad = n($db, "SELECT COUNT(*) FROM fin_equity WHERE balance_ok = 0");
judge($rows, $fail, $warn, 'M-10', 'صفرُ تدفقٍ نقديٍّ مختلٍّ محفوظ', 0, $cfBad, $cfBad === 0, 'حارسُ التوازنِ يرفض قبل الحفظ');
judge($rows, $fail, $warn, 'M-10', 'صفرُ حقوقِ ملكيةٍ مختلةٍ محفوظة', 0, $eqBad, $eqBad === 0, '');

/* ═══ ⑦ نواةُ المكوناتِ العشرون ═════════════════════════════════════════ */
$comps = (string) @file_get_contents($ROOT . '/assets/js/ems-components.js');
$shell = (string) @file_get_contents($ROOT . '/assets/css/ems-shell.css');
$pageH = (string) @file_get_contents($ROOT . '/includes/page_header.php');
$UI = array(
    'UI-01 Page Header'        => strpos($pageH, 'main_head') !== false && strpos($pageH, 'ems-page-context') !== false,
    'UI-02 Toolbar'            => strpos($pageH, 'head_actions') !== false,
    'UI-03 Filter Bar'         => strpos($shell, 'ems-filter') !== false || strpos($comps, 'noResults') !== false,
    'UI-04 Saved Views'        => strpos($comps, 'EmsUI.savedViews') !== false,
    'UI-05 Data Table'         => strpos($comps, 'column-visibility.dt') !== false,
    'UI-06 Column Customizer'  => strpos($comps, 'guardColumnVisibility') !== false,
    'UI-07 KPI Card'           => strpos($comps, 'EmsUI.kpiCard') !== false,
    'UI-08 Task Card'          => strpos($comps, 'EmsUI.taskCard') !== false,
    'UI-09 Approval Card'      => strpos($comps, 'EmsUI.approvalCard') !== false,
    'UI-10 Alert List'         => strpos($comps, 'EmsUI.alertList') !== false,
    'UI-11 Empty State'        => strpos($comps, 'EmsUI.emptyState') !== false,
    'UI-12 No Results'         => strpos($comps, 'EmsUI.noResults') !== false,
    'UI-13 Error State'        => strpos($comps, 'EmsUI.errorState') !== false,
    'UI-14 Access State'       => strpos($comps, 'EmsUI.accessState') !== false,
    'UI-15 Skeleton'           => strpos($comps, 'EmsUI.skeleton') !== false,
    'UI-16 Sync State'         => strpos($comps, 'EmsUI.syncChip') !== false,
    'UI-17 Chart Guard'        => strpos($comps, 'EmsUI.chartGuard') !== false,
    'UI-18 Drawer / Modal'     => is_file($ROOT . '/assets/js/ems-details-modal.js')
                                  || strpos((string) @file_get_contents($ROOT . '/inheader.php'), 'EmsDetailsModal') !== false,
    'UI-19 Toast / Pagination' => strpos($comps, 'EmsUI.toast') !== false && strpos($comps, 'EmsUI.pagination') !== false,
    'UI-20 Import / Export'    => strpos($comps, 'EmsUI.ioPanel') !== false && strpos($comps, 'EmsUI.exportManifest') !== false,
);
$uiOk = 0; $uiMiss = array();
foreach ($UI as $k => $v) { if ($v) { $uiOk++; } else { $uiMiss[] = $k; } }
judge($rows, $fail, $warn, 'UXR', 'نواةُ المكوناتِ العشرون مبنية',
    20, $uiOk, $uiOk === 20, count($uiMiss) ? 'الغائب: ' . implode(' · ', $uiMiss) : '');

/* عقودُ المكوناتِ الثلاثةِ الجديدة — أترفض الناقصَ فعلًا؟ */
$contracts = array(
    'toast يرفض بلا echoedAt'      => strpos($comps, "echoedAt") !== false && strpos($comps, 'throw new Error') !== false,
    'pagination يرفض بلا total'    => preg_match('~pagination[\s\S]{0,600}total إلزامي~u', $comps) === 1,
    'exportManifest يرفض الناقص'   => strpos($comps, 'تسعةُ بنودٍ إلزامًا') !== false,
    'savedViews يرفض بلا screenKey'=> strpos($comps, 'screenKey إلزامي') !== false,
);
$cOk = 0; $cMiss = array();
foreach ($contracts as $k => $v) { if ($v) { $cOk++; } else { $cMiss[] = $k; } }
judge($rows, $fail, $warn, 'UXR', 'المكوناتُ ترفض النداءَ الناقصَ (العقدُ في المكوّن)',
    count($contracts), $cOk, $cOk === count($contracts), count($cMiss) ? 'بلا عقد: ' . implode(' · ', $cMiss) : '');

/* ═══ ⑧ الخدماتُ — أموجودةٌ أم عاملة؟ ═════════════════════════════════ */
$svcs = array(
    'app/Services/Finance/FinanceM10Service.php'      => array('gateChecks', 'generateEntitlement', 'budgetApprove'),
    'app/Services/Finance/CoaService.php'             => array('assertDims', 'resolveAccount', 'assertCreatable'),
    'app/Services/Finance/FinAnalysisService.php'     => array('computeRatios', 'evaluateSignals', 'generateCashflow'),
    'app/Services/Governance/GovernanceM14Service.php'=> array('decideApproval', 'reviewDenial', 'orgChange'),
);
$svcMiss = array(); $svcOk = 0;
foreach ($svcs as $f => $fns) {
    $src = (string) @file_get_contents($ROOT . '/' . $f);
    if ($src === '') { $svcMiss[] = $f . ' (غير موجود)'; continue; }
    $lack = array();
    foreach ($fns as $fn) { if (strpos($src, 'function ' . $fn) === false) { $lack[] = $fn; } }
    if ($lack) { $svcMiss[] = basename($f) . ' ينقصه ' . implode('/', $lack); } else { $svcOk++; }
}
judge($rows, $fail, $warn, 'الخدمات', 'كلُّ خدمةٍ تحمل دوالَّها المنصوصة',
    count($svcs), $svcOk, count($svcMiss) === 0, count($svcMiss) ? implode(' · ', $svcMiss) : '');

/* ═══ ⑨ البوابةُ الرباعيةُ — أرموزُ الرفضِ الأربعةُ منفَّذة؟ ═══════════ */
$m10src = (string) @file_get_contents($ROOT . '/app/Services/Finance/FinanceM10Service.php');
$codes = array('GATE-CHAIN', 'GATE-PERIOD', 'GATE-CONTRACT', 'GATE-QUOTA');
$codeMiss = array();
foreach ($codes as $c) { if (strpos($m10src, $c) === false) { $codeMiss[] = $c; } }
judge($rows, $fail, $warn, 'M-10', 'بوابةُ الاستحقاقِ الرباعيةُ برموزِ رفضِها',
    4, 4 - count($codeMiss), count($codeMiss) === 0, count($codeMiss) ? 'الغائب: ' . implode(' · ', $codeMiss) : '');

/* ═══ ⑩ الحاملُ الثلاثيُّ لرسائلِ الحوكمة ═════════════════════════════ */
$permH = (string) @file_get_contents($ROOT . '/includes/permissions_helper.php');
$inhd  = (string) @file_get_contents($ROOT . '/inheader.php');
$login = (string) @file_get_contents($ROOT . '/login.php');
$carrier = array(
    'المُودِع ems_gov_flash_redirect' => strpos($permH, 'function ems_gov_flash_redirect') !== false,
    'المصبُّ ems_gov_redirect'        => strpos($permH, 'function ems_gov_redirect') !== false,
    'الماصُّ ems_absorb_url_msg'      => strpos($permH, 'function ems_absorb_url_msg') !== false,
    /* ◆ **المقياسُ كان يبحث عن معرِّفٍ متقاعد**: الترويسةُ حملت الرسالةَ في
     *   عنصرٍ اسمُه `emsGovFlash` بأنماطٍ داخلية، ثم **هُجِّرت في
     *   2026-08-09 إلى نظامِ الرسائلِ الموحَّد** (`EmsAlert`) بقرارِ مالكٍ
     *   بعد بلاغٍ بلقطةٍ حيّة. فالطبقةُ **قائمةٌ وأفضلُ**، والغائبُ اسمُها
     *   لا فعلُها. وإضافةُ الاسمِ الميتِ لتخضرَّ البوابةُ **تلاعبٌ بالمقياس**
     *   ⇒ يُرسى القياسُ على الفعلِ: امتصاصُ `ems_flash_gov` من الجلسةِ ثم
     *   عرضُه بالحاملِ الموحَّدِ أو بلافتةِ `noscript` عند غيابِ جافاسكربت. */
    'العارضُ في الترويسة'             => (strpos($inhd, 'ems_flash_gov') !== false
                                            && (strpos($inhd, 'EmsAlert') !== false
                                                || strpos($inhd, 'emsGovFlash') !== false)),
    'العارضُ في شاشةِ الدخول'         => strpos($login, 'emsGovFlash') !== false,
);
$carOk = 0; $carMiss = array();
foreach ($carrier as $k => $v) { if ($v) { $carOk++; } else { $carMiss[] = $k; } }
judge($rows, $fail, $warn, 'UXR', 'حاملُ رسائلِ الحوكمةِ بطبقاتِه الخمس',
    count($carrier), $carOk, $carOk === count($carrier), count($carMiss) ? 'الغائب: ' . implode(' · ', $carMiss) : '');

/* ═══ ⑪ الهجراتُ مسجَّلةٌ فعلًا ═══════════════════════════════════════ */
$onDisk = glob($ROOT . '/database/migrations/2026_12_*.sql');
$applied = col($db, "SELECT filename FROM schema_migrations WHERE filename LIKE '2026\\_12\\_%'");
$appliedSet = array(); foreach ($applied as $a) { $appliedSet[$a] = true; }
$notApplied = array();
foreach ($onDisk as $f) { if (!isset($appliedSet[basename($f)])) { $notApplied[] = basename($f); } }
judge($rows, $fail, $warn, 'الهجرات', 'كلُّ هجرةٍ على القرصِ مسجَّلةٌ مطبَّقةً',
    count($onDisk), count($onDisk) - count($notApplied), count($notApplied) === 0,
    count($notApplied) ? 'غيرُ مطبَّق: ' . implode(' · ', $notApplied) : '');

/* ═══ ⑫ الشاشاتُ الجديدةُ مسجَّلةٌ في خريطةِ الملفات ═══════════════════ */
$newScreens = array_merge(array_keys($m10Screens), array_keys($m14Screens));
$notMapped = array();
foreach ($newScreens as $f) {
    if (!isset($fileMapReal[strtolower($f)])) { $notMapped[] = $f; }
}
judge($rows, $fail, $warn, 'التسجيل', 'شاشاتُ الوثيقتين في خريطةِ الملفاتِ القانونية',
    count($newScreens), count($newScreens) - count($notMapped), count($notMapped) === 0,
    count($notMapped) ? 'خارجَ الخريطة: ' . implode(' · ', array_slice($notMapped, 0, 10))
        . (count($notMapped) > 10 ? ' … و' . (count($notMapped) - 10) : '') : '');

/* ═══ ⑬ مجسّاتٌ سلوكيةٌ — أترفض المحرّكاتُ فعلًا أم تُعلن الرفضَ نصًّا؟ ═══
   الوجودُ ليس صحةً: ملفٌّ فيه اسمُ حارسٍ قد لا يحرس. هذه المجسّاتُ تستدعي
   المحرّكاتِ بمدخلٍ فاسدٍ عمدًا وتنتظر رفضًا برمزِه المنصوص. ولا تكتب شيئًا. */
require_once $ROOT . '/app/Services/Governance/GovernanceM14Service.php';
require_once $ROOT . '/app/Services/Finance/FinanceM10Service.php';

/** يستدعي دالةً وينتظر استثناءً نصُّه يحمل الرمزَ المطلوب */
function probe(callable $fn, $expectCode)
{
    try { $fn(); }
    catch (\Throwable $t) { return strpos($t->getMessage(), $expectCode) !== false; }
    return false;   /* لم يرمِ أصلًا = لا حارس */
}

$probes = array();

/* ① حارسُ الأبعاد: **حسابٌ ورقيٌّ قابلٌ للقيد** يلزمه بُعدٌ غيرُ D1، وقيدٌ يحمل
      D1 وحدَه. (اختيارُ حسابٍ تجميعيٍّ يُوقظ حارسَ المستوى أولًا فيُقاس غيرُ
      المقصود — والمجسُّ يجب أن يوقظ الحارسَ الذي يقيسه لا جارَه.) */
$needDim = null;
if ($r = @$db->query("SELECT code, required_dims FROM fin_chart_of_accounts
                       WHERE is_canonical = 1 AND is_postable = 1
                         AND required_dims LIKE '%D4%'
                       ORDER BY code LIMIT 1")) { $needDim = $r->fetch_assoc(); }
$probes['حارسُ الأبعاد يرفض قيدًا ناقصَ بُعدٍ يلزمه (COA-DIM-422)'] =
    $needDim === null ? null : probe(function () use ($db, $needDim) {
        \App\Services\Finance\CoaService::assertDims($db, 4, $needDim['code'], array('D1' => 4));
    }, 'COA-DIM-422');

/* ①-ب حارسُ المستوى: لا قيدَ على حسابٍ تجميعيّ ⇒ COA-LEVEL-422 */
$aggAcc = null;
if ($r = @$db->query("SELECT code FROM fin_chart_of_accounts
                       WHERE is_canonical = 1 AND acc_level = 1 LIMIT 1")) { $aggAcc = $r->fetch_assoc(); }
$probes['حارسُ المستوى يرفض القيدَ على حسابٍ تجميعيّ (COA-LEVEL-422)'] =
    $aggAcc === null ? null : probe(function () use ($db, $aggAcc) {
        \App\Services\Finance\CoaService::assertDims($db, 4, $aggAcc['code'], array('D1' => 4));
    }, 'COA-LEVEL-422');

/* ② حارسُ R2: اسمُ شخصٍ في شجرةِ الحسابات ⇒ COA-R2-422 */
$probes['حارسُ R2 يرفض حسابًا باسمِ شخصٍ (COA-R2-422)'] =
    probe(function () use ($db) {
        \App\Services\Finance\CoaService::assertCreatable($db, 4, '9999', 'عهدة محمد أحمد', 3, '');
    }, 'COA-R2-422');

/* ③ حارسُ M-14: سببٌ خارجَ القائمةِ المحكومة ⇒ GOV-422 */
$probes['حارسُ الأسباب يرفض سببًا خارجَ القائمة (GOV-422)'] =
    probe(function () use ($db) {
        \App\Services\Governance\GovernanceM14Service::decideApproval(
            $db, 4, 'fin_request', 1, 'rejected', 'RSN-NOT-A-REAL-CODE', '', 1, 'فاحص', '');
    }, 'GOV-422');

/* ④ حارسُ M-14: قرارٌ خارجَ (rejected · returned) ⇒ GOV-422 */
$probes['حارسُ القرار يرفض قرارًا غيرَ منصوص (GOV-422)'] =
    probe(function () use ($db) {
        \App\Services\Governance\GovernanceM14Service::decideApproval(
            $db, 4, 'fin_request', 1, 'approved', 'RSN-DOCS', '', 1, 'فاحص', '');
    }, 'GOV-422');

/* ⑤ بوابةُ الاستحقاق: واقعةٌ غيرُ موجودة ⇒ FIN-404 (fail-closed لا صمت) */
$probes['بوابةُ الاستحقاق ترفض واقعةً غيرَ موجودة (FIN-404)'] =
    probe(function () use ($db) {
        \App\Services\Finance\FinanceM10Service::gateChecks($db, 4, 999999999);
    }, 'FIN-404');

$pOk = 0; $pMiss = array(); $pSkip = 0;
foreach ($probes as $k => $v) {
    if ($v === null) { $pSkip++; continue; }
    if ($v) { $pOk++; } else { $pMiss[] = $k; }
}
judge($rows, $fail, $warn, 'سلوك', 'المحرّكاتُ ترفض المدخلَ الفاسدَ برمزِها المنصوص',
    count($probes) - $pSkip, $pOk, count($pMiss) === 0,
    (count($pMiss) ? '✘ لم يرفض: ' . implode(' · ', $pMiss) : 'استُدعيت بمدخلٍ فاسدٍ فرمت برمزِها')
    . ($pSkip ? ' — تُخطّي ' . $pSkip . ' (لا بياناتٍ للمجسّ)' : ''));

/* ═══ ⑭ القوائمُ الخمسُ ومستوياتُ الهامشِ الخمسة ═══════════════════════
   ◆ تصحيحُ فهمٍ: الوثيقةُ (الورقة 05) لا تُوسم القوائمَ الخمسَ كلَّها على الشجرة.
   S1 وS2 وحدَهما وسمٌ على الحساب · وS3 «تُنتَج من الأبعادِ لا من شجرةٍ منفصلة»
   (البُعد D2) · وS4 مصدرُها «تصنيفُ النشاطِ لكل حساب» (cashflow_activity) ·
   وS5 من طبقةِ حقوقِ الملكية. فعدُّ رموزِ القائمةِ على الشجرةِ وانتظارُ خمسةٍ
   خطأُ فاحصٍ يقيس بنيةً غيرَ التي نصّت عليها الوثيقة. */
$marginConst = array();
$faSrc = (string) @file_get_contents($ROOT . '/app/Services/Finance/FinAnalysisService.php');
if (preg_match_all("~'(M[1-5])'\s*=>~", $faSrc, $mm)) { $marginConst = array_unique($mm[1]); }
judge($rows, $fail, $warn, 'M-10', 'مستوياتُ الهامشِ الخمسةُ M1..M5 معرَّفةٌ في المحرّك',
    5, count($marginConst), count($marginConst) === 5,
    count($marginConst) ? implode(' · ', $marginConst) : 'غائبة');

$stmtTagged = col($db, "SELECT DISTINCT statement_code FROM fin_chart_of_accounts
                         WHERE is_canonical = 1 AND statement_code IS NOT NULL AND statement_code <> ''");
sort($stmtTagged);
judge($rows, $fail, $warn, 'M-10', 'S1 وS2 موسومتان على الشجرةِ (والثلاثُ الباقياتُ مشتقّات)',
    'S1 · S2', implode(' · ', $stmtTagged), $stmtTagged === array('S1', 'S2'), '');

$actCover = n($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
                     WHERE is_canonical = 1 AND (cashflow_activity IS NULL OR cashflow_activity = '')");
judge($rows, $fail, $warn, 'M-10', 'تصنيفُ نشاطِ التدفقِ على كلِّ حساب (مصدرُ S4)',
    'صفرُ حسابٍ بلا تصنيف', $actCover, $actCover === 0, '');

$engines = array('S1' => 'balanceSheet', 'S2' => 'incomeStatement', 'S3' => 'generateProjectPL',
                 'S4' => 'generateCashflow', 'S5' => 'generateEquity');
$engMiss = array();
foreach ($engines as $code => $fn) { if (strpos($faSrc, 'function ' . $fn) === false) { $engMiss[] = $code; } }
judge($rows, $fail, $warn, 'M-10', 'محرّكُ كلِّ قائمةٍ من الخمسِ مبنيّ',
    5, 5 - count($engMiss), count($engMiss) === 0,
    count($engMiss) ? 'الغائب: ' . implode(' · ', $engMiss) : implode(' · ', array_keys($engines)));

/* ═══ ⑮ إلزامُ الأبعادِ مشتقٌّ من طبيعةِ الحسابِ لا موحَّدًا ═══════════════
   الوثيقةُ تجعل لكلِّ بُعدٍ شرطَه: D4 «في كل مصروف» · D6 «في الذممِ
   والمستحقات» · D7 «في الإيرادِ وتكلفتِه» … فلو كانت كلُّ الحساباتِ تلزم D1
   وحدَه لكان النموذجُ التسعيُّ حبرًا: بنيةً حاضرةً وحكمًا معطَّلًا. */
$dimProfiles = n($db, "SELECT COUNT(DISTINCT required_dims) FROM fin_chart_of_accounts WHERE is_canonical = 1");
$expenseNoD4 = n($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
                        WHERE is_canonical = 1 AND is_postable = 1 AND account_type = 'expense'
                          AND (required_dims IS NULL OR required_dims NOT LIKE '%D4%')");
/* D7 «إلزاميٌّ في الإيرادِ وتكلفتِه» — والإيرادُ هنا التشغيليُّ (طبقةُ 41)،
   إذ لا نموذجَ عملٍ خلفَ فرقِ عملةٍ أو ربحِ بيعِ أصل. و42xx استثناءٌ معلَنٌ
   مكتوبٌ في حقلِ ملاحظةِ الحساب — لا سكوتًا. */
$revenueNoD7 = n($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
                        WHERE is_canonical = 1 AND is_postable = 1 AND account_type = 'revenue'
                          AND code LIKE '41%'
                          AND (required_dims IS NULL OR required_dims NOT LIKE '%D7%')");
$nonOpUndeclared = n($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
                            WHERE is_canonical = 1 AND is_postable = 1 AND account_type = 'revenue'
                              AND code LIKE '42%'
                              AND COALESCE(coa_note, '') NOT LIKE '%استثناءٌ معلَن: D7%'");
$penaltyNoD8 = n($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
                        WHERE is_canonical = 1 AND name LIKE '%جزاء%'
                          AND (required_dims IS NULL OR required_dims NOT LIKE '%D8%')");
judge($rows, $fail, $warn, 'COA', 'إلزامُ الأبعادِ مشتقٌّ من طبيعةِ الحسابِ لا موحَّدًا',
    '>1 ملمح', $dimProfiles . ' ملمحًا', $dimProfiles > 1,
    'ملامحُ إلزامٍ مختلفةٌ بحسبِ نوعِ الحساب');
judge($rows, $fail, $warn, 'COA', 'كلُّ مصروفٍ ورقيٍّ يلزمه D4 مركزُ التكلفة',
    0, $expenseNoD4, $expenseNoD4 === 0, 'حكمُ الورقة 02: «إلزاميٌّ في كل مصروف»');
judge($rows, $fail, $warn, 'COA', 'كلُّ إيرادٍ تشغيليٍّ (41x) يلزمه D7 نموذجُ العمل',
    0, $revenueNoD7, $revenueNoD7 === 0, 'حكمُ الورقة 02: «إلزاميٌّ في الإيرادِ وتكلفتِه»');
judge($rows, $fail, $warn, 'COA', 'كلُّ إيرادٍ غيرِ تشغيليٍّ (42x) استثناؤه من D7 **معلَنٌ** لا مسكوتٌ عنه',
    0, $nonOpUndeclared, $nonOpUndeclared === 0, 'الاستثناءُ مكتوبٌ في حقلِ ملاحظةِ الحساب');
judge($rows, $fail, $warn, 'COA', 'كلُّ حسابِ جزاءاتٍ يلزمه D8 العقد',
    0, $penaltyNoD8, $penaltyNoD8 === 0,
    'حكمُ الورقة 02: «D8 إلزاميٌّ في الإيرادِ والجزاءات» — وجزاءٌ بلا عقدٍ يرفع هامشَ العقدِ كذبًا');

/* ═══ ⑯ عمقُ الربط: أفعالُ الوثيقتين مربوطةٌ بصفحةٍ حيةٍ لا معلَّقةٍ ═══════
   حضورُ الفعلِ في القاموسِ إعلانٌ، وربطُه بصفحةٍ حيةٍ (state=bound_page) هو
   البناء. والفرقُ بينهما هو الفرقُ بين «سُجّل» و«يعمل». */
/* المبنيُّ حالتان لا واحدة: `bound_page` فعلٌ له صفحتُه · و`alias` رمزٌ
   قانونيٌّ يخدمه رمزٌ حيٌّ قائمٌ سبقه (فالوظيفةُ مبنيةٌ والاسمُ توحيدٌ).
   وعدُّ الأليَسِ «غيرَ مبنيّ» يطلب بناءَ ما هو قائمٌ ويولّد ازدواجًا. */
$specActs = array_merge($actsDoc10, $actsDoc14);
$built = array(); $blankState = 0;
if ($r = @$db->query("SELECT canonical_code, live_code, state FROM nav09_action_map")) {
    while ($x = $r->fetch_assoc()) {
        if ($x['state'] === '' || $x['state'] === null) { $blankState++; }
        $isBuilt = ($x['state'] === 'bound_page')
                || ($x['state'] === 'alias' && trim((string) $x['live_code']) !== '');
        if (!$isBuilt) { continue; }
        $keys = array(strtolower(trim((string) $x['canonical_code'])));
        if (!empty($x['live_code'])) { $keys[] = strtolower(trim((string) $x['live_code'])); }
        foreach ($keys as $k) { if ($k !== '') { $built[$k] = true; } }
    }
}
$notBound = array();
foreach ($specActs as $a) { if (!isset($built[strtolower($a)])) { $notBound[] = $a; } }
judge($rows, $fail, $warn, 'الأفعال', 'أفعالُ الوثيقتين مبنيةٌ (صفحةٌ حيةٌ أو أليسٌ لرمزٍ حي)',
    count($specActs), count($specActs) - count($notBound), count($notBound) === 0,
    count($notBound) ? 'غيرُ مبنيّ: ' . implode(' · ', array_slice($notBound, 0, 12))
        . (count($notBound) > 12 ? ' … و' . (count($notBound) - 12) : '') : '');

/* حالةٌ فارغةٌ في ENUM = صفٌّ يسقط من كلِّ عدٍّ يفرز بالحالة — والگوتشا
   المعروفةُ هنا: ENUM يبتلع '' صامتًا. */
judge($rows, $fail, $warn, 'الأفعال', 'صفرُ فعلٍ بحالةٍ فارغةٍ في القاموس',
    0, $blankState, $blankState === 0,
    'ENUM يقبل \'\' صامتًا — والصفُّ الفارغُ يسقط من كلِّ فرزٍ بالحالة');

/* ═══ ⑰ مراحلُ الدورةِ المستندية ═══════════════════════════════════════ */
$stage10 = $declared_stage = null;
if (preg_match('~\|\s*مراحلُ الدورة\s*\|\s*(\d+)~u', $M10, $mm)) { $stage10 = (int) $mm[1]; }
$stage14 = null;
if (preg_match('~\|\s*مراحلُ الدورة\s*\|\s*(\d+)~u', $M14, $mm)) { $stage14 = (int) $mm[1]; }
$liveStage10 = substr_count($M10, '## ▐ ٥-') - 1;   /* ٥-١ الدورةُ الحاكمة ليست مرحلة */
judge($rows, $fail, $warn, 'M-10', 'مراحلُ الدورةِ المعلَنةُ لها مجموعاتُ مرحلةٍ في الوثيقة',
    $stage10, $liveStage10 > 0 ? $liveStage10 : '—', $stage10 !== null && $liveStage10 >= $stage10,
    'اتساقُ الوثيقةِ — أقسامُ ٥-x');

/* ═══ ⑱ الأعمدةُ الحاكمةُ على شاشاتِ الوثيقتين ═════════════════════════ */
$govReg = 0;
$govSrc = (string) @file_get_contents($ROOT . '/includes/gov_columns.php');
if (preg_match('~function\s+ems_gov_registry\s*\(\s*\)\s*\{(.*?)\n\}~su', $govSrc, $gm)) {
    $govReg = preg_match_all("~^\s*'[a-z0-9_]+'\s*=>\s*array\(~mi", $gm[1]);
}
$govApplied = n($db, "SELECT COUNT(DISTINCT canonical_file) FROM cmp03_screen_rows");
judge($rows, $fail, $warn, 'الحوكمة', 'سجلُّ الأعمدةِ الحاكمةِ حيٌّ ومقروءٌ من مصدرٍ واحد',
    '>0', $govReg, $govReg > 0, 'includes/gov_columns.php ⇐ ems_gov_registry()');

/* ═══ ⑲ الشاشاتُ الجديدةُ تحمل الرأسَ الموحَّدَ والغلافَ الحاكم ═══════════ */
$newBuilt = array();
foreach (array_merge(array_keys($m10Screens), array_keys($m14Screens)) as $f) {
    list($rel,) = resolve_screen($ROOT, $fileMapReal, $f);
    if ($rel !== null) { $newBuilt[$rel] = true; }
}
/* ◆ **القشراتُ المشتركةُ تُقرأ كلُّها لا واحدةً**: الشاشاتُ المولَّدةُ تسكن
 *   ثلاثَ قشراتٍ (التحليلُ الماليّ · حوكمةُ الإدارة · مخاطرُ الإدارة)،
 *   وكلٌّ منها تُضمّن الرأسَ لساكنيها. وقصرُ الاتّباعِ على واحدةٍ يترك
 *   ساكنَ الأخرى مُتَّهمًا بلا ذنب. */
$faShells = array();
foreach (array('includes/fin_analysis_shell', 'includes/dept_gov_space',
               'Risk/dept_risk_space') as $shPath) {   /* قشرةُ المخاطرِ تسكن Risk/ لا includes/ */
    $shSrc = (string) @file_get_contents($ROOT . '/' . $shPath . '.php');
    if ($shSrc !== '' && strpos($shSrc, 'page_header.php') !== false) {
        $faShells[] = basename($shPath);
    }
}
$noHdr = array(); $noShell = array();
foreach (array_keys($newBuilt) as $rel) {
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($src === '' || strpos($src, 'insidebar') === false) { continue; }
    /* ◆ **المقياسُ كان يقرأ الملفَّ وحدَه فيعمى عن قشرتِه**: ستُّ شاشاتٍ
     *   ماليةٍ مولَّدةٌ على `includes/fin_analysis_shell.php`، والقشرةُ هي
     *   التي تُضمّن `page_header.php` (السطر 68) كما تُضمّن الترويسةَ
     *   والسايدبار. وقياسٌ ملفّيٌّ محضٌ أعلنها «بلا رأس» — **وهي تحمله
     *   مُصيَّرًا مرةً واحدةً بحسابٍ حيّ** (`class="main_head"` × 1 لكلٍّ).
     *   وحقنُ الرأسِ فيها لإرضاءِ القياسِ كان سيُصيّره **مرتين** — عيبًا
     *   حقيقيًّا ثمنًا لخضرةٍ كاذبة. ⇒ يُتبَع الرأسُ عبرَ القشرةِ كما يُتبَع
     *   الغلافُ في الفحصِ التالي بالضبط. */
    $viaShell = false;
    foreach ($faShells as $shName) {
        if (strpos($src, $shName . '.php') !== false) { $viaShell = true; break; }
    }
    if (strpos($src, 'page_header.php') === false && !$viaShell) { $noHdr[] = $rel; }
    if (strpos($src, 'ems_shell_axes') === false && strpos($src, 'dept_gov_space.php') === false
        && strpos($src, 'dept_risk_space.php') === false && strpos($src, 'fin_analysis_shell.php') === false) {
        $noShell[] = $rel;
    }
}
judge($rows, $fail, $warn, 'UXR', 'شاشاتُ الوثيقتين تحمل الرأسَ الموحَّد',
    count($newBuilt), count($newBuilt) - count($noHdr), count($noHdr) === 0,
    count($noHdr) ? 'بلا رأس: ' . implode(' · ', array_slice($noHdr, 0, 8)) : '');
judge($rows, $fail, $warn, 'UXR', 'شاشاتُ الوثيقتين تبذر الغلافَ الحاكمَ CM-00',
    count($newBuilt), count($newBuilt) - count($noShell), count($noShell) === 0,
    count($noShell) ? 'بلا غلاف: ' . implode(' · ', array_slice($noShell, 0, 8)) : '');

/* ═══ العرض ═══════════════════════════════════════════════════════════ */
echo "هندسةٌ عكسية — أيطابق المبنيُّ الوثيقةَ؟\n";
echo str_repeat('═', 96), "\n";
echo 'المصدر: docs/update0012/extracted/  ·  القاعدة: ' . $cfg['db'] . ':' . $cfg['port']
   . '  ·  ' . date('Y-m-d H:i') . "\n\n";
foreach ($rows as $r) {
    echo $r[4] . ' [' . str_pad($r[0], 8) . '] ' . $r[1] . "\n";
    echo '        الوثيقة: ' . $r[2] . '   ·   الحي: ' . $r[3] . "\n";
    if ($r[5] !== '') { echo '        ↳ ' . $r[5] . "\n"; }
}
echo str_repeat('─', 96), "\n";
$total = count($rows);
echo 'الفحوصُ: ' . $total . '  ·  مطابقٌ: ' . ($total - $fail - $warn)
   . '  ·  ◐ يحتاج نظرًا: ' . $warn . '  ·  ✘ مخالف: ' . $fail . "\n";
echo $fail === 0
    ? ($warn === 0 ? "🟢 المبنيُّ يطابق الوثيقةَ في كلِّ ما قيس\n" : "🟡 لا مخالفةَ — وبنودٌ تحتاج نظرًا\n")
    : "🔴 مخالفاتٌ للوثيقة — التفصيلُ أعلاه\n";

if ($mdOut !== null) {
    $md = "# هندسةٌ عكسية — أيطابق المبنيُّ الوثيقةَ؟\n\n";
    $md .= "> يقرأ جداولَ الوثائقِ من `docs/update0012/extracted/` ويقارنها بالقرصِ والقاعدةِ الحية.\n>\n";
    $md .= "> تاريخُ الفحص: **" . date('Y-m-d H:i') . "** · القاعدة `" . $cfg['db'] . ":" . $cfg['port'] . "`\n>\n";
    $md .= "> **الفرقُ عن بواباتِ القبول:** البوابةُ تسأل «أنجحَ ما بنيتُه؟» وهذا يسأل **«أبنيتُ ما طُلب؟»**.\n\n";
    $md .= "| الحكم | النطاق | البند | الوثيقة | الحي | الشاهد |\n|:--:|---|---|---|---|---|\n";
    foreach ($rows as $r) {
        $md .= '| ' . $r[4] . ' | ' . $r[0] . ' | ' . $r[1] . ' | `' . $r[2] . '` | `' . $r[3] . '` | '
            . ($r[5] !== '' ? $r[5] : '—') . " |\n";
    }
    $md .= "\n**الخلاصة:** " . $total . ' فحصًا · مطابقٌ ' . ($total - $fail - $warn)
        . ' · ◐ ' . $warn . ' · ✘ ' . $fail . "\n";
    @mkdir(dirname($mdOut), 0777, true);
    file_put_contents($mdOut, $md);
    echo "\nالمخرَجُ: " . $mdOut . "\n";
}
exit($fail === 0 ? 0 : 1);
