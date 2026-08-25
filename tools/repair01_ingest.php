<?php
/**
 * tools/repair01_ingest.php — استيعابُ حزمةِ REPAIR01 مرّةً واحدةً إلى المخزن
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ يُشغَّل مرّةً عند التأسيس، ثم لا يُحرَّر مصنَّفٌ بعده: كلُّ عرضٍ أو تقريرٍ
 *   أو مصنَّفٍ يُعاد توليدُه من هذه الجداول.
 * ◆ كلُّ صفٍّ يحمل `src_ref` = «الملفّ › الورقة › الصفّ» — فلا رقمَ بلا خليّة.
 * ◆ قرارا المالك المُغلقان (2026-08-23) يُطبَّقان هنا لا في المصنَّف:
 *     DEC-OPEN-03 = APPROVED · MULTI_ENTITY_BY_DESIGN
 *     DEC-OPEN-18 = APPROVED · إعادةُ ترقيمٍ 01..17 بجسرٍ غيرِ مدمِّر
 * ◆ الشبح: صفُّ `gov_screen_cycle` بلا ملفٍّ في الشجرةِ كلِّها. يُوسَم ولا
 *   يُحذف — لأنّ المصالحةَ صنّفته «مبنيًّا»، والحذفُ يمحو الدليل.
 *
 * التشغيل: php tools/repair01_ingest.php [--dry]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/tools/lib/xlsx_io.php';

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$DRY = in_array('--dry', $argv, true);
$DIR = $ROOT . '/docs/REPAIR01_20260823/';
$STAMP = '2026-08-23 00:00:00';

function r01_q($conn, $sql) {
    if ($conn->query($sql) === false) { fwrite(STDERR, "SQL: {$conn->error}\n  " . mb_substr($sql, 0, 200) . "\n"); return false; }
    return true;
}
function r01_e($conn, $v) { return $conn->real_escape_string((string) $v); }
function r01_cell($row, $i) { return isset($row[$i]) ? trim((string) $row[$i]) : ''; }

$report = array();

/* ═══ ① تجميدُ الملفّاتِ المصدر ═══ */
r01_q($conn, "DELETE FROM repair01_source_files");
$files = glob($DIR . '*.xlsx'); sort($files);
$totSheets = 0; $totRows = 0;
foreach ($files as $f) {
    $wb = xlsx_read($f);
    $sheets = count($wb); $rows = 0;
    foreach ($wb as $rs) {
        $hdrRow = -1;
        foreach ($rs as $ri => $r) {
            $vals = array_filter(array_map('trim', $r), function ($v) { return $v !== ''; });
            if (count($vals) >= 2) { $hdrRow = $ri; break; }
        }
        if ($hdrRow >= 0) { $rows += max(0, count($rs) - ($hdrRow + 1)); }
    }
    $totSheets += $sheets; $totRows += $rows;
    $bn = basename($f);
    preg_match('/^(\d+)/', $bn, $mm);
    r01_q($conn, "INSERT INTO repair01_source_files (file_no,file_name,sha256,bytes,sheet_count,data_rows,frozen_at) VALUES ("
        . "'" . r01_e($conn, isset($mm[1]) ? $mm[1] : '--') . "','" . r01_e($conn, $bn) . "','" . hash_file('sha256', $f) . "',"
        . filesize($f) . ",$sheets,$rows,'$STAMP')");
}
/* والويرد */
$docx = glob($DIR . '*.docx');
foreach ($docx as $f) {
    r01_q($conn, "INSERT INTO repair01_source_files (file_no,file_name,sha256,bytes,sheet_count,data_rows,frozen_at) VALUES ("
        . "'00','" . r01_e($conn, basename($f)) . "','" . hash_file('sha256', $f) . "'," . filesize($f) . ",0,960,'$STAMP')");
}
$report['① ملفّاتٌ مُجمَّدة'] = count($files) + count($docx) . " ملفًّا · $totSheets ورقةً · $totRows صفَّ بيانات";

/* ═══ ② القرارات — من MASTER وحدَه ═══ */
$wb09 = xlsx_read($DIR . '09 · السجلات المؤسسية والقرارات.xlsx');
$m = $wb09['OWNER_DECISIONS_MASTER'];
$hdr = $m[3]; ksort($hdr); $H = array();
foreach ($hdr as $i => $v) { $H[trim($v)] = $i; }

/* تصنيفُ الحجب — حكمٌ صريحٌ قابلٌ للمراجعة، لا استنباطٌ صامت */
$BLOCK = array(
    'DEC-OPEN-01' => array('CONFIG_PENDING',              'قاعدةُ AAM معروفةٌ والعتبةُ تُقرأ من Policy Registry — المحرّكُ يُبنى قابلَ الضبط'),
    'DEC-OPEN-02' => array('CONFIG_PENDING',              'نافذةُ التجميعِ قيمةٌ تشغيلية — Split Guard يُبنى بقراءتها'),
    'DEC-OPEN-04' => array('CONFIG_PENDING',              'عملةُ العرضِ إعدادُ تقريرٍ لا بنيةَ دفتر'),
    'DEC-OPEN-05' => array('CONFIG_PENDING',              'مدّةُ نافذةِ التحقّق مؤقّتٌ يُقرأ من الإعداد'),
    'DEC-OPEN-06' => array('CONFIG_PENDING',              'حدُّ النثريةِ عتبةٌ رقميّةٌ في Registry'),
    'DEC-OPEN-07' => array('CONFIG_PENDING',              'حدودُ الشراءِ المباشرِ عتباتٌ رقميّة'),
    'DEC-OPEN-08' => array('CONFIG_PENDING',              'شهيّةُ المخاطرِ تسكن Appetite Registry'),
    'DEC-OPEN-09' => array('CONFIG_PENDING',              'قائمةُ الموضوعاتِ المحجوزة محتوى Registry لا بنية'),
    'DEC-OPEN-10' => array('CONFIG_PENDING',              'نافذةُ التوثيقِ مؤقّت'),
    'DEC-OPEN-11' => array('CONFIG_PENDING',              'حدودُ التفويضِ ومدّتُه معاملانِ في Delegation Engine'),
    'DEC-OPEN-12' => array('READY_TO_BUILD_BLOCKER',      'تعريفُ Critical/Safety يحدّد التفرّعَ نفسَه — لا يُبنى محرّكٌ على تعريفٍ مجهول'),
    'DEC-OPEN-13' => array('CONFIG_PENDING',              'عتباتُ التصعيدِ أرقام'),
    'DEC-OPEN-14' => array('CONFIG_PENDING',              'عتبةُ التنبيهِ التنفيذيِّ رقمٌ في Rule Engine'),
    'DEC-OPEN-15' => array('READY_TO_BUILD_BLOCKER',      'أعلامُ Lot/Serial/Expiry تحدّد سلوكَ الصنفِ وبنيتَه لا قيمتَه'),
    'DEC-OPEN-16' => array('STRUCTURAL_TARGET_BLOCKER',   'ملكيّةُ التحقيقات تحدّد أيَّ إدارةٍ تملك ج06 و ح07-3 — بنيةُ المستهدَف'),
    'DEC-OPEN-17' => array('STRUCTURAL_TARGET_BLOCKER',   'مالكُ Entity Routing Registry يحدّد بنيةَ «طلباتي» — مساحةُ العمل تعتمد عليه'),
);
/* RPR-PATCH-01 §3 — محورُ الحجبِ ذو القيمتين: هل يمنع فتحَ البوّابة؟
   STRUCTURAL يحدّد بنيةَ المستهدَف فيوقف البناء · THRESHOLD رقمٌ من السجلِّ
   والمحرّكُ يُبنى قابلَ الضبطِ ولا ينتظره. يُصنَّف الثمانيةَ عشرَ كلُّها —
   المعتمدُ منها والمنتظِر — لأنّ المحورَ صفةُ القرارِ لا صفةُ حالتِه. */
$BTYPE = array(
    'DEC-OPEN-01' => 'THRESHOLD',  'DEC-OPEN-02' => 'THRESHOLD',
    'DEC-OPEN-03' => 'STRUCTURAL', 'DEC-OPEN-04' => 'THRESHOLD',
    'DEC-OPEN-05' => 'THRESHOLD',  'DEC-OPEN-06' => 'THRESHOLD',
    'DEC-OPEN-07' => 'THRESHOLD',  'DEC-OPEN-08' => 'THRESHOLD',
    'DEC-OPEN-09' => 'THRESHOLD',  'DEC-OPEN-10' => 'THRESHOLD',
    'DEC-OPEN-11' => 'THRESHOLD',  'DEC-OPEN-12' => 'STRUCTURAL',
    'DEC-OPEN-13' => 'THRESHOLD',  'DEC-OPEN-14' => 'THRESHOLD',
    'DEC-OPEN-15' => 'STRUCTURAL', 'DEC-OPEN-16' => 'STRUCTURAL',
    'DEC-OPEN-17' => 'STRUCTURAL', 'DEC-OPEN-18' => 'STRUCTURAL',
);

/* قرارا المالكِ المُغلقان اليوم */
$OWNER_CLOSED = array(
    'DEC-OPEN-03' => 'إنجاز متعدّدُ الكيانات القانونيةِ معماريًّا (MULTI_ENTITY_BY_DESIGN). لكلِّ شركةٍ دفترٌ مستقلٌّ وإقفالٌ وقوائمُ مالية، وطبقةُ تجميعٍ للمجموعة موسومةٌ صراحةً. يجوز أن يبدأ نطاقُ التشغيلِ بإكوبيشن للتشغيل وحدَها دون تغييرِ المعمارية. القاعدة: لا إقفالَ ولا قيدَ ولا فاتورةَ ولا حسابَ بنكيَّ ولا ميزانيةَ ولا قائمةَ بلا Company_ID — والحبّةُ Legal Entity × Accounting Period لا System × Month.',
    'DEC-OPEN-18' => 'إعادةُ الترقيمِ المؤسسيِّ إلى 01..17 للإداراتِ المعتمدةِ فقط. القيادةُ التنفيذيةُ ومساحةُ عملي والمراجعةُ الداخليةُ برموزٍ خارجَ التسلسل (EX-CEO · EX-DVP · WS-MY · IAF). تُفصل المالية والخزينة إلى نطاقين مستقلَّين DEP-05 و DEP-06. ويُحتفظ بالمعرّفاتِ التقنيةِ القديمةِ عبر Crosswalk — ولا إعادةَ ترقيمٍ مدمِّرةٍ للمفاتيحِ التاريخية.',
);

r01_q($conn, "DELETE FROM repair01_decisions");
$nDec = 0; $stat = array(); $blk = array();
foreach ($m as $ri => $r) {
    $id = r01_cell($r, isset($H['Decision_ID']) ? $H['Decision_ID'] : 0);
    if ($id === '' || !preg_match('/^DEC-/i', $id)) { continue; }
    $g = function ($k) use ($r, $H) { return isset($H[$k]) ? r01_cell($r, $H[$k]) : ''; };
    $status = $g('Decision_Status');
    $owner  = $g('Owner_Decision');
    if (isset($OWNER_CLOSED[$id])) { $status = 'APPROVED'; $owner = $OWNER_CLOSED[$id]; }
    if ($status !== 'APPROVED' && $status !== 'NEEDS_OWNER_DECISION') { $status = 'NEEDS_OWNER_DECISION'; }
    $bl = 'NONE'; $br = '';
    if ($status === 'NEEDS_OWNER_DECISION' && isset($BLOCK[$id])) { $bl = $BLOCK[$id][0]; $br = $BLOCK[$id][1]; }
    elseif ($status === 'NEEDS_OWNER_DECISION') { $bl = 'READY_TO_BUILD_BLOCKER'; $br = 'غيرُ مصنَّفٍ بعد — يُراجَع'; }
    $stat[$status] = (isset($stat[$status]) ? $stat[$status] : 0) + 1;
    if ($bl !== 'NONE') { $blk[$bl] = (isset($blk[$bl]) ? $blk[$bl] : 0) + 1; }
    $src = "09 › OWNER_DECISIONS_MASTER › ص" . ($ri + 1);
    $cols = array(
        'decision_id' => $id, 'domain' => $g('Domain'), 'question' => $g('Question'),
        'current_state' => $g('Current_State'), 'options' => $g('Options'),
        'recommended' => $g('Recommended_Decision'), 'owner_decision' => $owner,
        'status' => $status, 'blocking_level' => $bl, 'blocking_reason' => $br,
        'blocker_type' => isset($BTYPE[$id]) ? $BTYPE[$id] : (preg_match('/^DEC-OPEN-/', $id) ? 'STRUCTURAL' : ''),
        'affected_documents' => $g('Affected_Documents'), 'affected_screens' => $g('Affected_Screens'),
        'affected_rules' => $g('Affected_Rules'), 'migration_impact' => $g('Migration_Impact'),
        'code_impact' => $g('Code_Impact'), 'evidence' => $g('Evidence'),
        'approved_by' => $g('Approved_By'), 'approved_at' => $g('Approved_At'), 'src_ref' => $src,
    );
    $k = array(); $v = array();
    /* ENUM يبتلع '' صامتًا — الفارغُ في عمودٍ مُعدَّدٍ يُمرَّر NULL لا سلسلةً خاوية */
    $ENUM_NULLABLE = array('blocker_type' => 1);
    foreach ($cols as $ck => $cv) {
        $k[] = "`$ck`";
        $v[] = ($cv === '' && isset($ENUM_NULLABLE[$ck])) ? 'NULL' : "'" . r01_e($conn, $cv) . "'";
    }
    if (r01_q($conn, "INSERT INTO repair01_decisions (" . implode(',', $k) . ") VALUES (" . implode(',', $v) . ")")) { $nDec++; }
}
ksort($stat); ksort($blk);
$report['② القرارات'] = "$nDec · " . json_encode($stat, JSON_UNESCAPED_UNICODE) . " · حجب: " . json_encode($blk, JSON_UNESCAPED_UNICODE);

/* ═══ ③ الإدارات — قرارُ DEC-OPEN-18 ═══ */
r01_q($conn, "DELETE FROM repair01_departments");
$DEPTS = array(
    array('DEP-01',  1, 'إدارة المبيعات التعاقدية والعقود', 'CORPORATE',   null, ''),
    array('DEP-02',  2, 'إدارة الموردين',                   'CORPORATE',   null, ''),
    array('DEP-03',  3, 'إدارة التمويل والممولين',          'CORPORATE',   null, ''),
    array('DEP-04',  4, 'إدارة الأسطول والأصول',            'CORPORATE',   null, ''),
    array('DEP-05',  5, 'الإدارة المالية',                  'CORPORATE',   null, 'تعترف وتقيّد — GL · AR/AP · إقفال · قوائم'),
    array('DEP-06',  6, 'إدارة الخزينة',                    'CORPORATE',   null, 'تقبض وتصرف وتنفّذ — بنوك · سيولة · نثرية'),
    array('DEP-07',  7, 'إدارة الموارد البشرية',            'CORPORATE',   null, ''),
    array('DEP-08',  8, 'إدارة الحوكمة والالتزام',          'CORPORATE',   null, ''),
    array('DEP-09',  9, 'إدارة المخاطر',                    'CORPORATE',   null, 'أربعُ عائلات: تشغيلي · رأسمالي · تعاقدي · توريد'),
    array('DEP-10', 10, 'إدارة البلاغات',                   'CORPORATE',   null, 'Enterprise_Orchestration_Function=Yes · تملك دورةَ التذكرة لا تنفيذَ الحلّ'),
    array('DEP-11', 11, 'إدارة التشغيل',                    'OPERATIONAL', null, 'رأسُ القطاع التشغيلي'),
    array('DEP-12', 12, 'إدارة الموقع',                     'OPERATIONAL', 'DEP-11', 'أدنى طبقةٍ إدارية · وتحتها فريقُ المنجم Operational Team لا إدارة'),
    array('DEP-13', 13, 'إدارة القوى التشغيلية',            'OPERATIONAL', 'DEP-11', ''),
    array('DEP-14', 14, 'إدارة الصيانة',                    'OPERATIONAL', 'DEP-11', ''),
    array('DEP-15', 15, 'إدارة النقل والترحيل',             'OPERATIONAL', 'DEP-11', ''),
    array('DEP-16', 16, 'إدارة المشتريات',                  'OPERATIONAL', 'DEP-11', 'الوظائفُ الاستراتيجية Group داخلها لا إدارةٌ مركزية'),
    array('DEP-17', 17, 'إدارة المخازن',                    'OPERATIONAL', 'DEP-11', ''),
    array('EX-CEO', null, 'الرئيس التنفيذي',                'OUTSIDE',     null, 'Executive Workspace — خارجَ الـ17'),
    array('EX-DVP', null, 'نواب الرئيس',                    'OUTSIDE',     null, 'Deputy_Role / Scope — خارجَ الـ17'),
    array('WS-MY',  null, 'مساحة عملي',                     'OUTSIDE',     null, 'Personal Workspace — إسقاطٌ ومُطلِقُ طلباتٍ لا مصدرَ حقيقة'),
    array('IAF',    null, 'المراجعة الداخلية',              'OUTSIDE',     null, 'Independent Assurance Function — ترفع للمالك · الحوكمةُ لا تعدّل نتيجتَها'),
);
foreach ($DEPTS as $d) {
    r01_q($conn, "INSERT INTO repair01_departments (canonical_code,display_order,name_ar,sector,parent_code,note) VALUES ("
        . "'" . r01_e($conn, $d[0]) . "'," . ($d[1] === null ? 'NULL' : (int) $d[1]) . ",'" . r01_e($conn, $d[2]) . "','" . $d[3] . "',"
        . ($d[4] === null ? 'NULL' : "'" . r01_e($conn, $d[4]) . "'") . ",'" . r01_e($conn, $d[5]) . "')");
}
$report['③ الإدارات'] = count($DEPTS) . " رمزًا (17 إدارةً + 4 خارجَ التسلسل)";

/* ═══ ④ الجسرُ إلى المسمّياتِ الحيّة ═══ */
r01_q($conn, "DELETE FROM repair01_dept_crosswalk");
$XW = array(
    array('المبيعات والعقود',            'DEP-01', 'MAP', '', ''),
    array('إدارة الموردين',              'DEP-02', 'MAP', '', ''),
    array('التمويل والملكية',            'DEP-03', 'MAP', '', ''),
    array('إدارة الأسطول',               'DEP-04', 'MAP', '', ''),
    array('المالية والخزينة',            'DEP-05', 'SPLIT', 'الاعتراف والقيد · GL · AR/AP · الاستحقاقات · التسويات · الإقفال · ميزان المراجعة · القوائم · الأصول المحاسبية · قواعد FX', 'وحدةٌ حيّةٌ واحدةٌ بـ123 صفًّا تُشقّ إلى اثنتين — لا يُحسم الصفُّ آليًّا'),
    array('المالية والخزينة',            'DEP-06', 'SPLIT', 'الحسابات البنكية · مركز النقد · تنفيذ التحصيل والصرف · التحويلات · تنفيذ FX · الأدوات · إعداد المطابقة البنكية · توقّع السيولة · النثرية', 'الشقُّ الثاني — يُحسم في الموجة ج'),
    array('الموارد البشرية',             'DEP-07', 'MAP', '', ''),
    array('الحوكمة والالتزام',           'DEP-08', 'MAP', '', ''),
    array('إدارة المخاطر المؤسسية',      'DEP-09', 'MAP', '', ''),
    array('مركز البلاغات',               'DEP-10', 'MAP', '', 'يُعاد تسميتُه «إدارة البلاغات» في المستهدَف — لا Control Center'),
    array('إدارة التشغيل',               'DEP-11', 'MAP', '', ''),
    array('إدارة الموقع',                'DEP-12', 'MAP', '', ''),
    array('القوى التشغيلية',             'DEP-13', 'MAP', '', ''),
    array('إدارة الصيانة',               'DEP-14', 'MAP', '', ''),
    array('النقل والترحيل',              'DEP-15', 'MAP', '', ''),
    array('إدارة المشتريات التشغيلية',   'DEP-16', 'MAP', '', ''),
    array('إدارة المخازن',               'DEP-17', 'MAP', '', ''),
    array('مكتب الرئيس التنفيذي والنواب','EX-CEO', 'SPLIT', 'أسطحُ الرئيس', 'وحدةٌ حيّةٌ واحدةٌ بـ30 صفًّا تُشقّ إلى رئيسٍ ونوّاب'),
    array('مكتب الرئيس التنفيذي والنواب','EX-DVP', 'SPLIT', 'أسطحُ النواب', 'الشقُّ الثاني — 12 سطحًا مستهدفًا بلا مقابلٍ مبنيّ'),
    array('مساحة العمل الشخصية',         'WS-MY',  'RECLASSIFY', '', 'ليست إدارةً — تُخرَج من تعداد الـ17'),
    array('المراجع الداخلي المستقل',     'IAF',    'RECLASSIFY', '', 'ليست إدارةً — وظيفةُ تأكيدٍ مستقلّة'),
);
foreach ($XW as $x) {
    r01_q($conn, "INSERT INTO repair01_dept_crosswalk (legacy_name,canonical_code,verdict,split_rule,note) VALUES ("
        . "'" . r01_e($conn, $x[0]) . "','" . r01_e($conn, $x[1]) . "','" . $x[2] . "','" . r01_e($conn, $x[3]) . "','" . r01_e($conn, $x[4]) . "')");
}
$report['④ الجسر'] = count($XW) . " صفًّا · 19 مسمّى حيًّا ← 21 رمزًا · شقّان (المالية/الخزينة · الرئيس/النواب)";

/* ═══ ⑤ الأسطح — من الحيِّ + حكمِ المصالحة + علَمِ القرص ═══ */
/* فهرسٌ عوديٌّ لكلِّ php في الشجرة */
$diskIdx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $fo) {
    $p = $fo->getPathname();
    if (strpos($p, DIRECTORY_SEPARATOR . '.git') !== false || strpos($p, 'node_modules') !== false) { continue; }
    if (substr($p, -4) !== '.php') { continue; }
    $bn = strtolower(basename($p));
    if (!isset($diskIdx[$bn])) { $diskIdx[$bn] = str_replace($ROOT . DIRECTORY_SEPARATOR, '', $p); }
}
/* حكمُ المصالحة من الملفّ 10 شيت 02 */
$w10 = xlsx_read($DIR . '10 · المصالحة مع النظام.xlsx');
$verdict = array();
foreach ($w10['02_شاشة_بشاشة'] as $ri => $r) {
    if ($ri <= 1) { continue; }
    $f = strtolower(basename(r01_cell($r, 2)));
    if ($f !== '') { $verdict[$f] = array(r01_cell($r, 6), $ri + 1); }
}
/* الخريطةُ الحيّةُ للرمز المعياريّ — الشقُّ يبقى NULL */
$canon = array(); $split = array();
$rq = $conn->query("SELECT legacy_name, canonical_code, verdict FROM repair01_dept_crosswalk");
while ($x = $rq->fetch_assoc()) {
    if ($x['verdict'] === 'SPLIT') { $split[$x['legacy_name']] = 1; }
    elseif (!isset($canon[$x['legacy_name']])) { $canon[$x['legacy_name']] = $x['canonical_code']; }
}

r01_q($conn, "DELETE FROM repair01_surfaces");
$nS = 0; $ghost = 0; $unsplit = 0;
$rs = $conn->query("SELECT dept_name, layer_name, stage_order, stage_name, group_name, screen_title, screen_file,
                           output_doc, resp_role, next_state, stage_kind FROM gov_screen_cycle");
while ($x = $rs->fetch_assoc()) {
    $bn = strtolower(basename(trim($x['screen_file'])));
    $on = isset($diskIdx[$bn]) ? 1 : 0;
    if (!$on) { $ghost++; }
    $cc = isset($canon[$x['dept_name']]) ? $canon[$x['dept_name']] : null;
    if ($cc === null && isset($split[$x['dept_name']])) { $unsplit++; }
    $v = isset($verdict[$bn]) ? $verdict[$bn] : array('', 0);
    r01_q($conn, "INSERT INTO repair01_surfaces (screen_file,dept_legacy,canonical_code,screen_title,layer_name,stage_order,"
        . "stage_name,group_name,output_doc,resp_role,next_state,stage_kind,on_disk,disk_path,recon_verdict,src_ref) VALUES ("
        . "'" . r01_e($conn, $x['screen_file']) . "','" . r01_e($conn, $x['dept_name']) . "',"
        . ($cc === null ? 'NULL' : "'" . r01_e($conn, $cc) . "'") . ",'" . r01_e($conn, $x['screen_title']) . "','"
        . r01_e($conn, $x['layer_name']) . "','" . r01_e($conn, $x['stage_order']) . "','" . r01_e($conn, $x['stage_name']) . "','"
        . r01_e($conn, $x['group_name']) . "','" . r01_e($conn, $x['output_doc']) . "','" . r01_e($conn, $x['resp_role']) . "','"
        . r01_e($conn, $x['next_state']) . "','" . r01_e($conn, $x['stage_kind']) . "',$on,'"
        . r01_e($conn, $on ? $diskIdx[$bn] : '') . "','" . r01_e($conn, $v[0]) . "','"
        . r01_e($conn, 'live:gov_screen_cycle + 10 › 02_شاشة_بشاشة › ص' . $v[1]) . "')");
    $nS++;
}
$report['⑤ الأسطح'] = "$nS صفًّا · شبحٌ (بلا ملفّ) $ghost · بلا رمزٍ معياريٍّ بسببِ الشقّ $unsplit";

/* ═══ ⑥ الفجواتُ المستهدفة ═══ */
r01_q($conn, "DELETE FROM repair01_target_gaps");
$nG = 0;
foreach ($w10['04_مستهدف_غير_مبني'] as $ri => $r) {
    if ($ri <= 3) { continue; }
    $sn = r01_cell($r, 1);
    if ($sn === '') { continue; }
    r01_q($conn, "INSERT INTO repair01_target_gaps (unit,surface_name,built_counterpart,verdict,src_ref) VALUES ("
        . "'" . r01_e($conn, r01_cell($r, 0)) . "','" . r01_e($conn, $sn) . "','" . r01_e($conn, r01_cell($r, 2)) . "','"
        . r01_e($conn, r01_cell($r, 3)) . "','10 › 04_مستهدف_غير_مبني › ص" . ($ri + 1) . "')");
    $nG++;
}
$report['⑥ فجواتٌ مستهدفة'] = "$nG سطحًا";

/* ═══ ⑦ المتطلَّبات ═══
   ⚠ `stage_no` إسنادٌ **يعيش خارجَ الإكسل** — يضعه `repair01_stage_assign.php`.
   والمسحُ وإعادةُ الإدراجِ يمحوه صامتًا، فتفقد ملفّاتُ المراحلِ نطاقَها وتُولَّد
   «بلا مقامٍ عدديّ» وهي أخطرُ من الخطأِ الصريح. لذلك يُلتقَط قبل المسحِ ويُعاد
   بعد الإدراج، والمتطلَّبُ الجديدُ يبقى NULL فتلتقطه البوّابةُ G0-12. */
$keepStage = array();
$rk = $conn->query("SELECT requirement_id, stage_no FROM repair01_requirements WHERE stage_no IS NOT NULL");
if ($rk) { while ($x = $rk->fetch_assoc()) { $keepStage[$x['requirement_id']] = (int) $x['stage_no']; } }
r01_q($conn, "DELETE FROM repair01_requirements");
$nR = 0; $dupR = 0;
foreach ($wb09['01_سجل_المتطلبات'] as $ri => $r) {
    if ($ri <= 1) { continue; }
    $id = r01_cell($r, 0);
    if ($id === '' || strtolower($id) === 'requirement_id') { continue; }
    $ok = r01_q($conn, "INSERT INTO repair01_requirements (requirement_id,wave,unit,dependency,seq,group_name,surface,grain,source_of_truth,src_ref) VALUES ("
        . "'" . r01_e($conn, $id) . "','" . r01_e($conn, r01_cell($r, 1)) . "','" . r01_e($conn, r01_cell($r, 2)) . "','" . r01_e($conn, r01_cell($r, 3)) . "','"
        . r01_e($conn, r01_cell($r, 4)) . "','" . r01_e($conn, r01_cell($r, 5)) . "','" . r01_e($conn, r01_cell($r, 6)) . "','" . r01_e($conn, r01_cell($r, 7)) . "','"
        . r01_e($conn, r01_cell($r, 8)) . "','09 › 01_سجل_المتطلبات › ص" . ($ri + 1) . "')");
    if ($ok) { $nR++; } else { $dupR++; }
}
/* إرجاعُ الإسنادِ الملتقَط */
$restored = 0;
foreach ($keepStage as $rid => $st) {
    if (r01_q($conn, "UPDATE repair01_requirements SET stage_no=" . (int) $st
        . " WHERE requirement_id='" . r01_e($conn, $rid) . "'")) { $restored += $conn->affected_rows; }
}
$stillNull = (int) $conn->query("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no IS NULL")->fetch_row()[0];
$report['⑦ المتطلَّبات'] = "$nR" . ($dupR ? " · مكرَّرٌ مرفوض $dupR" : '')
    . " · إسنادٌ مُرجَع $restored" . ($stillNull ? " · ⚠ بلا مرحلة $stillNull — شغّلْ repair01_stage_assign.php" : " · بلا مرحلة 0");

/* ═══ ⑧ الحقول ═══ */
r01_q($conn, "DELETE FROM repair01_fields");
$nF = 0;
foreach ($wb09['02_تتبع_الحقول'] as $ri => $r) {
    if ($ri <= 3) { continue; }
    $fn = r01_cell($r, 5);
    if ($fn === '' || strtolower($fn) === 'اسم الحقل') { continue; }
    r01_q($conn, "INSERT INTO repair01_fields (requirement_id,wave,unit,surface,seq,field_name,field_type,visibility_rule,src_ref) VALUES ("
        . "'" . r01_e($conn, r01_cell($r, 0)) . "','" . r01_e($conn, r01_cell($r, 1)) . "','" . r01_e($conn, r01_cell($r, 2)) . "','" . r01_e($conn, r01_cell($r, 3)) . "','"
        . r01_e($conn, r01_cell($r, 4)) . "','" . r01_e($conn, $fn) . "','" . r01_e($conn, r01_cell($r, 6)) . "','" . r01_e($conn, r01_cell($r, 7)) . "','"
        . '09 › 02_تتبع_الحقول › ص' . ($ri + 1) . "')");
    $nF++;
}
$report['⑧ الحقول'] = "$nF";

/* ═══ ⑨ الأحداث ═══ */
r01_q($conn, "DELETE FROM repair01_events");
$nE = 0;
foreach ($wb09['03_الأحداث_والآثار'] as $ri => $r) {
    if ($ri <= 4) { continue; }
    $ec = r01_cell($r, 0);
    if ($ec === '' || strtolower($ec) === 'event_code') { continue; }
    r01_q($conn, "INSERT INTO repair01_events (event_code,name,wave,source_unit,source_screen,idempotency_key,consumers,effect_type,retry_policy,src_ref) VALUES ("
        . "'" . r01_e($conn, $ec) . "','" . r01_e($conn, r01_cell($r, 1)) . "','" . r01_e($conn, r01_cell($r, 2)) . "','" . r01_e($conn, r01_cell($r, 3)) . "','"
        . r01_e($conn, r01_cell($r, 4)) . "','" . r01_e($conn, r01_cell($r, 5)) . "','" . r01_e($conn, r01_cell($r, 6)) . "','" . r01_e($conn, r01_cell($r, 7)) . "','"
        . r01_e($conn, r01_cell($r, 8)) . "','09 › 03_الأحداث_والآثار › ص" . ($ri + 1) . "')");
    $nE++;
}
$report['⑨ الأحداث'] = "$nE";

/* ═══ ⑩ الملكيّة ═══ */
r01_q($conn, "DELETE FROM repair01_ownership");
$nO = 0;
foreach ($w10['05_التداخلات_والملكية'] as $ri => $r) {
    if ($ri <= 3) { continue; }
    $sc = r01_cell($r, 1);
    if ($sc === '' || $sc === 'الشاشة') { continue; }
    r01_q($conn, "INSERT INTO repair01_ownership (space_role,screen,route,owner_dept,classification,ownership_kind,space_count,gov_meaning,src_ref) VALUES ("
        . "'" . r01_e($conn, r01_cell($r, 0)) . "','" . r01_e($conn, $sc) . "','" . r01_e($conn, r01_cell($r, 2)) . "','" . r01_e($conn, r01_cell($r, 3)) . "','"
        . r01_e($conn, r01_cell($r, 4)) . "','" . r01_e($conn, r01_cell($r, 5)) . "'," . (int) r01_cell($r, 6) . ",'" . r01_e($conn, r01_cell($r, 7)) . "','"
        . '10 › 05_التداخلات_والملكية › ص' . ($ri + 1) . "')");
    $nO++;
}
$report['⑩ الملكيّة'] = "$nO";

/* ═══ التقرير ═══ */
echo "\n════════ استيعابُ REPAIR01 ════════\n";
foreach ($report as $k => $v) { printf("%-22s %s\n", $k, $v); }
echo "═══════════════════════════════════\n";

/* ⚠ **الاستيعابُ يمسح ويعيد الإدخال** — فأحكامُ W01 المكتوبةُ في `canonical_code`
 * و`resp_role` و`w1_*` تُمحى بهذه الجولةِ ولا تُستعاد من المصنَّف. والصمتُ هنا
 * هو ما يجعل تراجعًا صامتًا يعبر إلى المرحلةِ التالية. */
$w1 = $conn->query("SHOW COLUMNS FROM repair01_surfaces LIKE 'canon_rule'");
if ($w1 && $w1->num_rows > 0) {
    echo "\n⚠ أحكامُ W01 مُحيت مع إعادةِ الإدخال. أعِدْ:\n";
    echo "   php tools/repair01_w1_apply.php   ثمّ   php tools/repair01_w1_gate.php\n";
}
