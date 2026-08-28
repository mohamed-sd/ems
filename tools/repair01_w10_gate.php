<?php
/**
 * tools/repair01_w10_gate.php — بوّابةُ المرحلةِ العاشرة (شقُّ المالية والخزينة)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ حاجبٍ يُعيد القياسَ ولا يقرأ ما خزّنَته الأداة**: `W10-03` يعيد اشتقاقَ
 *   الشقِّ كلِّه من المخزنِ ويقارنه بالدفترِ صفًّا صفًّا — فدفترٌ يُقرأ من نفسِه
 *   حشوٌ لا فحص.
 *
 * ◆ **وحارسُ الخلاءِ مبنيٌّ من البداية**: مقامٌ خاوٍ يُخضِرُّ الحاجبَ على «تطابقِ
 *   لا شيء». فكلُّ حاجبٍ هنا يشترط **مقامًا موجبًا** إلى جانبِ حكمِه، والصفرُ
 *   يمرُّ مُعلَنًا بقرارٍ وحدَه.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة**: `W10-15` يكشف عطبَ المولِّدِ بشكلِ
 *   الإسنادِ في شيفرتِه لا بعبارةٍ عربيّةٍ في تعليق.
 *
 * التشغيل: php tools/repair01_w10_gate.php
 * الخروج : 0 كلُّ الحواجبِ خضراء · 1 حاجبٌ ساقط
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w10_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
/* مرساةُ الطورِ صفرِ — **حقيقةٌ مسجَّلةٌ لا ثابتٌ حرفيّ** (RPR-AMD01) */
require_once __DIR__ . '/lib/repair01_w00_anchor.php';
$W00 = w00_anchors($conn);


$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$E   = function ($s) use ($conn) { return "'" . $conn->real_escape_string((string) $s) . "'"; };
$one = function ($sql) use ($conn) { return repair01_w10_one($conn, $sql); };

$pass = 0; $fail = 0; $rows = array();
function gate($id, $title, $ok, $detail)
{
    global $pass, $fail, $rows;
    if ($ok) { $pass++; } else { $fail++; }
    $rows[] = array($id, $title, $ok, $detail);
}

echo "═══════ بوّابةُ المرحلةِ العاشرة — REPAIR01 · شقُّ المالية والخزينة ═══════\n";

/* ── حارسُ الخلاء: الصفرُ يمرُّ مُعلَنًا في قرارٍ وحدَه ───────────────────── */
$emptyDeclared = (int) $one("SELECT COUNT(*) FROM repair01_w10_decisions
                              WHERE decision_id = 'W10-D-04' AND rationale <> ''");
$vac = function ($n) use ($emptyDeclared) { return ((int) $n === 0 && $emptyDeclared === 0); };
$vacTag = $emptyDeclared ? 'خلاءٌ مُعلَنٌ ✔' : '**خلاءٌ غيرُ مُعلَن**';

/* ══ W10-01 · الوحدةُ المشقوقةُ مُعلَنةٌ بشقَّيها وقاعدةِ كلٍّ منهما ═══════ */
$units = repair01_w10_split_units($conn);
$badUnit = 0;
foreach ($units as $nm => $sides) {
    if (count($sides) < 2) { $badUnit++; continue; }
    foreach ($sides as $c => $meta) { if (trim($meta['rule']) === '') { $badUnit++; } }
}
gate('W10-01', 'الوحدةُ المشقوقةُ مُعلَنةٌ بشقَّيها وقاعدةِ كلٍّ منهما',
     count($units) > 0 && $badUnit === 0,
     'وحداتٌ مشقوقةٌ ' . count($units) . " · شقٌّ بلا قاعدةٍ أو وحدةٌ بشقٍّ واحدٍ $badUnit");

/* ══ W10-02 · كلُّ سطحٍ في النطاقِ بحكمٍ وقاعدةٍ وعذرٍ ومرساة ══════════ */
$scope = repair01_w10_scope_rows($conn);
$bookN = (int) $one("SELECT COUNT(*) FROM repair01_w10_split");
$bookBad = (int) $one("SELECT COUNT(*) FROM repair01_w10_split
                        WHERE resolved_code = '' OR split_rule = '' OR split_why = ''");
gate('W10-02', 'كلُّ سطحٍ في النطاقِ بحكمٍ وقاعدةٍ وعذرٍ مكتوب',
     count($scope) > 0 && $bookN === count($scope) && $bookBad === 0,
     'أسطحُ النطاقِ المُعادُ اشتقاقُها ' . count($scope) . " · في الدفتر $bookN · بلا قاعدةٍ $bookBad");

/* ══ W10-03 · الحكمُ المشتقُّ يطابق المخزَّنَ صفًّا صفًّا ══════════════════
   ⚠ **وهذا هو الحاجبُ الذي يمنع «دفترًا يقرأ نفسَه»**: الاشتقاقُ يُعاد كاملًا
     من المخزنِ ويُقارَن بما كتبَته الأداة. واختلافُ صفٍّ واحدٍ يُسقط. */
$derived = repair01_w10_resolve_all($conn);
$stored = array();
$r = $conn->query("SELECT scope_key, resolved_code, split_rule FROM repair01_w10_split");
while ($r && $x = $r->fetch_assoc()) { $stored[$x['scope_key']] = $x; }
$drift = array();
foreach ($derived as $k => $v) {
    if (!isset($stored[$k])) { $drift[] = $k . ' (غائبٌ من الدفتر)'; continue; }
    if ($stored[$k]['resolved_code'] !== $v['resolved_code'] || $stored[$k]['split_rule'] !== $v['split_rule']) {
        $drift[] = $k . ' (' . $stored[$k]['resolved_code'] . '≠' . $v['resolved_code'] . ')';
    }
}
gate('W10-03', 'الحكمُ المشتقُّ يطابق المخزَّنَ صفًّا صفًّا',
     count($derived) > 0 && count($drift) === 0 && count($stored) === count($derived),
     'مشتقٌّ ' . count($derived) . ' · مخزَّنٌ ' . count($stored) . ' · يخالف ' . count($drift)
     . (count($drift) ? ' ⇐ ' . implode('، ', array_slice($drift, 0, 3)) : ''));

/* ══ W10-04 · المفرداتُ مشتقّةٌ من المخزنِ لا مكتوبةً في الشيفرة ════════ */
$vocabDerived = repair01_w10_derive_vocab($conn);
$uniqD = array();
foreach ($vocabDerived as $v) {
    $kk = $v['norm'] . '|' . $v['side'];
    if (!isset($uniqD[$kk]) || $v['weight'] > $uniqD[$kk]['weight']) { $uniqD[$kk] = $v; }
}
$vocabStored = (int) $one("SELECT COUNT(*) FROM repair01_w10_vocab");
$vocabNoSrc = (int) $one("SELECT COUNT(*) FROM repair01_w10_vocab WHERE src_kind = '' OR src_ref = ''");
$srcKinds = (int) $one("SELECT COUNT(DISTINCT src_kind) FROM repair01_w10_vocab");
gate('W10-04', 'المفرداتُ مشتقّةٌ من المخزنِ ومطابقةٌ لما فيه',
     count($uniqD) > 0 && $vocabStored === count($uniqD) && $vocabNoSrc === 0 && $srcKinds >= 3,
     'مشتقّةٌ ' . count($uniqD) . " · مخزَّنةٌ $vocabStored · بلا مصدرٍ $vocabNoSrc · مصادرُ $srcKinds");

/* ══ W10-05 · السجلّانِ لا يتفرّقان في مالكِ سطحٍ مشترك ══════════════════ */
require_once $ROOT . '/app/Services/Governance/DeptSplitService.php';
$conf = \App\Services\Governance\DeptSplitService::detectConflict($conn);
$shared = (int) $one("SELECT COUNT(*) FROM repair01_w10_split WHERE in_surfaces = 1 AND in_registry = 1");
gate('W10-05', 'السجلّانِ لا يتفرّقانِ في مالكِ سطحٍ مشترك',
     count($conf) === 0 && $shared > 0,
     "أسطحٌ في السجلَّينِ معًا $shared · متنازعةٌ " . count($conf));

/* ══ W10-06 · مجموعُ الشقَّينِ = مقامُ الوحدةِ الأصليّة (‏§٦) ═════════════ */
$sumBad = array(); $unitTotals = array();
foreach (array_keys($units) as $nm) {
    $tot = (int) $one("SELECT COUNT(*) FROM repair01_w10_split WHERE legacy_unit = " . $E($nm));
    $sum = 0; $per = array();
    foreach (array_keys($units[$nm]) as $code) {
        $c = (int) $one("SELECT COUNT(*) FROM repair01_w10_split
                          WHERE legacy_unit = " . $E($nm) . " AND resolved_code = " . $E($code));
        $sum += $c; $per[] = $code . ':' . $c;
    }
    /* الشاشةُ العابرةُ قد تحمل رمزًا خارجَ شقَّي وحدتِها — تُعدُّ ولا تُخفى */
    $out = $tot - $sum;
    $unitTotals[] = mb_substr($nm, 0, 18) . ' ⇒ ' . implode(' + ', $per) . ' من ' . $tot;
    if ($tot === 0) { $sumBad[] = $nm . ' (مقامٌ خاوٍ)'; }
    if ($out !== (int) $one("SELECT COUNT(*) FROM repair01_w10_split
                              WHERE legacy_unit = " . $E($nm) . " AND split_rule = 'W10_CROSS_UNIT_KEPT'
                                AND resolved_code NOT IN (" . implode(',', array_map($E, array_keys($units[$nm]))) . ")")) {
        $sumBad[] = $nm . ' (فارقٌ غيرُ مُعلَنٍ ' . $out . ')';
    }
}
$finSurf5 = (int) $one("SELECT COUNT(*) FROM repair01_surfaces
                         WHERE dept_legacy = 'المالية والخزينة' AND canonical_code = 'DEP-05'");
$finSurf6 = (int) $one("SELECT COUNT(*) FROM repair01_surfaces
                         WHERE dept_legacy = 'المالية والخزينة' AND canonical_code = 'DEP-06'");
$finSurfN = (int) $one("SELECT COUNT(*) FROM repair01_surfaces WHERE dept_legacy = 'المالية والخزينة'");
gate('W10-06', 'مجموعُ الشقَّينِ يساوي مقامَ الوحدةِ الأصليّة',
     count($sumBad) === 0 && ($finSurf5 + $finSurf6) === $finSurfN && $finSurf5 > 0 && $finSurf6 > 0,
     "دفترُ الأسطح DEP-05 $finSurf5 + DEP-06 $finSurf6 = $finSurfN · وحدةٌ مختلَّةٌ " . count($sumBad)
     . ' · ' . implode(' · ', $unitTotals));

/* ══ W10-07 · مفتاحٌ تاريخيٌّ مكسورٌ صفر (‏§٦) ═══════════════════════════ */
$orphanSurf = (int) $one("SELECT COUNT(*) FROM repair01_surfaces s
                           LEFT JOIN repair01_screen_registry r ON r.screen_id = s.screen_id
                          WHERE s.screen_id <> '' AND r.screen_id IS NULL");
$badId = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                      WHERE screen_id NOT REGEXP '^SCR-[0-9]{4}$'");
$aliasSplit = (int) $one("SELECT COUNT(*) FROM repair01_key_alias
                           WHERE key_code LIKE 'DEP-0%' OR key_code LIKE 'EX-%'");
$navOrphan = (int) $one("SELECT COUNT(*) FROM nav_canonical
                          WHERE screen_id <> '' AND screen_id NOT IN
                                (SELECT screen_id FROM repair01_screen_registry)");
gate('W10-07', 'مفتاحٌ تاريخيٌّ مكسورٌ صفر',
     $orphanSurf === 0 && $badId === 0 && $aliasSplit === 0 && $navOrphan === 0,
     "مرجعٌ يتيمٌ في دفترِ الأسطح $orphanSurf · مُعرِّفٌ مخالفُ الصيغة $badId"
     . " · بديلُ مفتاحٍ للشقّ $aliasSplit · مُعرِّفُ ملاحةٍ يتيمٌ $navOrphan");

/* ══ W10-08 · كلُّ مؤشِّرٍ حيٍّ باسمِ الوحدةِ الأمِّ مترجَمٌ في الجسر ════ */
/* ⚠ **والمقامُ مفاتيحُ متمايزةٌ لا صفوف**: `nav_canonical` تحمل صفَّين لمسارٍ
     واحد، والجسرُ مفتاحُه المؤشِّرُ نفسُه. فمقارنةُ عددِ الصفوفِ بعددِ المفاتيحِ
     تُسقط الحاجبَ على تكرارٍ في الحيِّ لا على نقصٍ في الجسر — **والتكرارُ
     يُعلَن بعددِه** في `W10-D-09` ولا يُصلَح هنا. */
$liveP = 0; $liveRows = 0;
foreach (repair01_w10_pointer_sources() as $src) {
    if (repair01_w10_one($conn, "SHOW TABLES LIKE " . $E($src['table'])) === null) { continue; }
    $in = implode(',', array_map($E, array_keys($units)));
    $liveRows += (int) $one("SELECT COUNT(*) FROM `" . $src['table'] . "`
                              WHERE `" . $src['col'] . "` IN ($in)");
    $liveP += (int) $one("SELECT COUNT(DISTINCT `" . $src['key'] . "`) FROM `" . $src['table'] . "`
                           WHERE `" . $src['col'] . "` IN ($in)");
}
$dupP = $liveRows - $liveP;
$dupDec = (int) $one("SELECT scope_rows FROM repair01_w10_decisions WHERE decision_id = 'W10-D-09'");
$brN = (int) $one("SELECT COUNT(*) FROM repair01_w10_bridge");
$brBad = (int) $one("SELECT COUNT(*) FROM repair01_w10_bridge
                      WHERE resolved_code = '' OR bridge_rule = '' OR bridge_why = '' OR probe_sql = ''");
gate('W10-08', 'كلُّ مؤشِّرٍ حيٍّ باسمِ الوحدةِ الأمِّ مترجَمٌ في الجسر',
     $liveP > 0 && $brN === $liveP && $brBad === 0 && $dupP === $dupDec,
     "مفاتيحُ حيّةٌ متمايزةٌ $liveP (صفوفٌ $liveRows) · في الجسر $brN · ناقصُ الحقول $brBad"
     . " · مفتاحٌ مكرَّرٌ في الحيِّ $dupP · المُعلَنُ في W10-D-09 $dupDec");

/* ══ W10-09 · استعلامُ الإثباتِ يُشغَّل فعلًا لا يُعلَن ══════════════════ */
$probeOk = 0; $probeAll = 0; $probeBad = array();
$r = $conn->query("SELECT host_table, pointer_key, probe_sql FROM repair01_w10_bridge");
$pr = array();
while ($r && $x = $r->fetch_assoc()) { $pr[] = $x; }
foreach ($pr as $x) {
    $probeAll++;
    $v = repair01_w10_one($conn, (string) $x['probe_sql']);
    if ($v !== null && (int) $v > 0) { $probeOk++; }
    else { $probeBad[] = $x['host_table'] . ':' . mb_substr($x['pointer_key'], 0, 28); }
}
gate('W10-09', 'استعلامُ إثباتِ الرابطِ القديمِ يُشغَّل ويجد صفَّه',
     $probeAll > 0 && $probeOk === $probeAll,
     "أُثبت $probeOk من $probeAll" . (count($probeBad) ? ' ⇐ ' . implode('، ', array_slice($probeBad, 0, 2)) : ''));

/* ══ W10-10 · الاسمُ القديمُ لم يُدهَس في جدولٍ حيّ ═════════════════════ */
$overwritten = 0;
foreach (repair01_w10_pointer_sources() as $src) {
    if (repair01_w10_one($conn, "SHOW TABLES LIKE " . $E($src['table'])) === null) { continue; }
    $overwritten += (int) $one("SELECT COUNT(*) FROM `" . $src['table'] . "`
                                 WHERE `" . $src['col'] . "` REGEXP '^(DEP-[0-9]{2}|EX-[A-Z]{3})$'");
}
gate('W10-10', 'الاسمُ القديمُ لم يُدهَس برمزٍ معياريٍّ في جدولٍ حيّ',
     $overwritten === 0, "خلايا كُتب فيها الرمزُ مكانَ الاسمِ الحيِّ $overwritten");

/* ══ W10-11 · سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطحٍ حيّ ═════════════════ */
$sbN = (int) $one("SELECT COUNT(*) FROM repair01_w10_sidebar");
$sbBad = (int) $one("SELECT COUNT(*) FROM repair01_w10_sidebar
                      WHERE s1_verdict='' OR s1_rule='' OR s2_verdict='' OR s2_rule=''
                         OR s3_verdict='' OR s3_rule='' OR s4_verdict='' OR s4_rule=''
                         OR s5_verdict='' OR s5_rule='' OR s6_verdict='' OR s6_rule=''
                         OR s7_verdict='' OR s7_rule=''");
$liveScope = 0;
foreach ($derived as $v) { if ($v['route'] !== '' && is_file($ROOT . '/' . $v['route'])) { $liveScope++; } }
gate('W10-11', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطحٍ حيّ',
     $liveScope > 0 && $sbN === $liveScope && $sbBad === 0,
     "أسطحٌ حيّةٌ مُعادُ قياسُها $liveScope · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBad");

/* ══ W10-12 · حارسُ عرضٍ لكلِّ سطحٍ حيٍّ — والفاقدُ مُعلَنٌ بعددِه ═══════ */
$noGuard = (int) $one("SELECT COUNT(*) FROM repair01_w10_sidebar WHERE s6_verdict = 'NO_SERVER_GUARD'");
$noGuardDec = (int) $one("SELECT scope_rows FROM repair01_w10_decisions WHERE decision_id = 'W10-D-08'");
gate('W10-12', 'حارسُ عرضٍ لكلِّ سطحٍ حيٍّ والفاقدُ مُعلَنٌ بعددِه',
     $noGuard === $noGuardDec,
     "بلا حارسٍ مقيسٍ $noGuard · المُعلَنُ في W10-D-08 $noGuardDec");

/* ══ W10-13 · الشاشةُ العابرةُ للوحداتِ مُعلَنةٌ ولم يُدهَس مالكُها ═════ */
$cross = (int) $one("SELECT COUNT(*) FROM repair01_w10_split WHERE split_rule = 'W10_CROSS_UNIT_KEPT'");
$crossMoved = (int) $one("SELECT COUNT(*) FROM repair01_w10_split
                           WHERE split_rule = 'W10_CROSS_UNIT_KEPT' AND (moved_surface = 1 OR moved_registry = 1)");
gate('W10-13', 'الشاشةُ العابرةُ للوحداتِ لم يُدهَس مالكُها',
     $crossMoved === 0 && !$vac($cross),
     "$vacTag · عابرةٌ للوحدات $cross · دُهس مالكُها $crossMoved");

/* ══ W10-14 · المالكُ المحسومُ بترتيبِ الصفوفِ مُعلَنٌ بعددِه المقيس ═════ */
$arb = repair01_w10_arbitrary_rows($conn);
$arbBook = (int) $one("SELECT COUNT(*) FROM repair01_w10_split WHERE arbitrary_before = 1");
$arbDec = (int) $one("SELECT scope_rows FROM repair01_w10_decisions WHERE decision_id = 'W10-D-03'");
gate('W10-14', 'المالكُ المحسومُ بترتيبِ الصفوفِ مُعلَنٌ بعددِه المقيس',
     count($arb) === $arbBook && $arbBook === $arbDec && $arbBook > 0,
     'مقيسٌ ' . count($arb) . " · في الدفتر $arbBook · المُعلَنُ في W10-D-03 $arbDec");

/* ══ W10-15 · مولِّدُ W02 لا يعيد العطبَ عند إعادةِ تشغيلِه ═════════════ */
$gen = repair01_w10_generator_defect($ROOT);
gate('W10-15', 'مولِّدُ السجلِّ لا يعيد حسمَ الشقِّ بترتيبِ الصفوف',
     $gen['defect'] === false,
     ($gen['blind_map'] ? 'خريطةٌ بمفتاحِ الاسمِ نعم' : 'خريطةٌ بمفتاحِ الاسمِ لا')
     . ' · يستثني المشقوقَ ' . ($gen['guards_split'] ? 'نعم' : '**لا**') . ' ⇐ ' . $gen['why']);

/* ══ W10-16 · آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ صريحٍ مُسبَّب ══════════════ */
$ents = repair01_w10_entity_types();
$stEnt = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w10_states");
$stAll = (int) $one("SELECT COUNT(*) FROM repair01_w10_states");
$stForbid = (int) $one("SELECT COUNT(*) FROM repair01_w10_states WHERE allowed = 0");
$noForbid = (int) $one("SELECT COUNT(*) FROM (SELECT entity FROM repair01_w10_states
                          GROUP BY entity HAVING SUM(allowed = 0) = 0) t");
$badAllow = (int) $one("SELECT COUNT(*) FROM repair01_w10_states WHERE allowed = 1
                         AND (owner_role='' OR precondition='' OR official_doc=''
                              OR approval_gate='' OR reopen_rule='' OR correct_rule='')");
$badForbid = (int) $one("SELECT COUNT(*) FROM repair01_w10_states WHERE allowed = 0 AND forbid_reason = ''");
gate('W10-16', 'آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ صريحٍ مُسبَّب',
     $stEnt === count($ents) && $noForbid === 0 && $badAllow === 0 && $badForbid === 0,
     'كيانات ' . $stEnt . ' من ' . count($ents) . " · انتقالات $stAll · ممنوعٌ صراحةً $stForbid"
     . " · كيانٌ بلا ممنوعٍ $noForbid · مسموحٌ ناقصٌ $badAllow · ممنوعٌ بلا سبب $badForbid");

/* ══ W10-17 · فصلُ الواجباتِ منفَّذٌ برمزِ ردٍّ مُثبَتٍ من القرص ════════ */
$svc = (string) @file_get_contents($ROOT . '/app/Services/Governance/DeptSplitService.php');
$sodN = (int) $one("SELECT COUNT(*) FROM repair01_w10_sod");
$sodBad = (int) $one("SELECT COUNT(*) FROM repair01_w10_sod
                       WHERE initiator_role='' OR approver_role='' OR executor_role=''
                          OR closer_role='' OR forbidden_combo='' OR enforced_by=''
                          OR authority_rule_id='' OR deputy_role='' OR delegation_rule=''");
$sodSelf = (int) $one("SELECT COUNT(*) FROM repair01_w10_sod WHERE approver_role = executor_role");
$codeMissing = array();
$r = $conn->query("SELECT process_key, enforced_by FROM repair01_w10_sod");
while ($r && $x = $r->fetch_assoc()) {
    if (strpos($svc, (string) $x['enforced_by']) === false) { $codeMissing[] = $x['process_key']; }
}
gate('W10-17', 'فصلُ الواجباتِ منفَّذٌ برمزِ ردٍّ مُثبَتٍ من القرص',
     $sodN > 0 && $sodBad === 0 && $sodSelf === 0 && count($codeMissing) === 0,
     "عملياتٌ $sodN · صفٌّ ناقصٌ $sodBad · معتمِدٌ هو المنفِّذُ $sodSelf · رمزٌ بلا تنفيذٍ " . count($codeMissing)
     . (count($codeMissing) ? ' ⇐ ' . implode('، ', array_slice($codeMissing, 0, 2)) : ''));

/* ══ W10-18 · عقدُ أثرٍ كاملٌ وناشرٌ ومستهلكٌ مُثبَتانِ من القرصِ والسجلّ ═ */
$EV = repair01_w10_stage_events();
$evN = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W10'");
$evBad = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W10'
                      AND (trigger_rule='' OR min_payload='' OR consumer_list='' OR consumer_effect=''
                           OR preconditions='' OR failure_policy='' OR compensation='' OR idempotency_key='')");
$evAll = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W10'
                      AND (consumer_list LIKE '%كل المستهلكين%' OR consumer_list LIKE '%كلُّ المستهلكين%')");
$noPub = array(); $noSub = array();
foreach ($EV as $code) {
    if (strpos($svc, "'" . $code . "'") === false) { $noPub[] = $code; }
    $n = (int) $one("SELECT COUNT(*) FROM event_consumers WHERE event_name = " . $E($code) . " AND active = 1");
    if ($n === 0) { $noSub[] = $code; }
}
gate('W10-18', 'عقدُ أثرٍ كاملٌ لكلِّ حدثٍ بناشرٍ ومستهلكٍ مُثبَتَين',
     $evN === count($EV) && $evBad === 0 && $evAll === 0 && count($noPub) === 0 && count($noSub) === 0,
     'أحداثُ النطاق ' . $evN . ' من ' . count($EV) . " · عقدٌ ناقصٌ $evBad · «كلُّ المستهلكين» $evAll"
     . ' · بلا ناشرٍ ' . count($noPub) . ' · بلا مشتركٍ نشطٍ ' . count($noSub));

/* ══ W10-19 · العتبةُ من السجلِّ ولا رقمَ صلبٌ في أدواتِ النطاق ═════════ */
$TH = repair01_w10_thresholds($conn);
$thBad = 0;
foreach ($TH as $t) { if ($t['why'] === '' || $t['ref'] === '') { $thBad++; } }
$hard = array();
foreach (array('/tools/lib/repair01_w10_scan.php', '/tools/lib/repair01_w10_sidebar.php',
               '/tools/repair01_w10_journey.php', '/app/Services/Governance/DeptSplitService.php',
               '/app/Services/Governance/SplitProjectionConsumer.php') as $f) {
    $src = (string) @file_get_contents($ROOT . $f);
    /* مقارنةُ رقمٍ صلبةٌ في سطرٍ تنفيذيّ — والرسوُّ على شكلِ المقارنةِ لا على عبارة */
    /* ⚠ **والسهمُ ليس مقارنة**: `'approver' => 10` يطابق `[<>]\s*\d+` فيُحمِّر
         الحاجبَ على مفتاحِ مصفوفةٍ لا على عتبةٍ صلبة — والاستثناءُ بالنظرِ إلى
         الحرفِ الذي قبلَ السهمِ لا بحذفِ الكشفِ كلِّه. */
    if (preg_match_all('~(?<![=!])[<>]=?\s*([0-9]{2,})\b~', $src, $mm)) {
        foreach ($mm[1] as $num) { if ((int) $num > 9) { $hard[] = basename($f) . ':' . $num; } }
    }
}
$readsRegistry = (strpos((string) @file_get_contents($ROOT . '/tools/lib/repair01_w10_scan.php'),
                         'repair01_w10_thresholds') !== false);
gate('W10-19', 'العتبةُ من السجلِّ ولا مقارنةَ رقمٍ صلبةٍ في أدواتِ النطاق',
     count($TH) >= 5 && $thBad === 0 && count($hard) === 0 && $readsRegistry,
     'عتباتٌ مسجَّلةٌ ' . count($TH) . " · بلا عذرٍ أو مرجعٍ $thBad · مقارنةٌ صلبةٌ " . count($hard)
     . ' · تُقرأ من السجلِّ ' . ($readsRegistry ? 'نعم' : '**لا**')
     . (count($hard) ? ' ⇐ ' . implode('، ', array_slice($hard, 0, 3)) : ''));

/* ══ W10-20 · أساسُ المراحلِ السابقةِ لم يُمَسّ ═════════════════════════ */
$decN  = (int) $one("SELECT COUNT(*) FROM repair01_decisions");
$srcN  = (int) $one("SELECT COUNT(*) FROM repair01_source_files");
$surfN = (int) $one("SELECT COUNT(*) FROM repair01_surfaces");
$baseN = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin IN ('SURFACES','DISK','NAV')");
$growN = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin REGEXP '^W[0-9]{2}$'");
$unst  = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                      WHERE origin NOT IN ('SURFACES','DISK','NAV') AND origin NOT REGEXP '^W[0-9]{2}$'");
$gapOrig = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE COALESCE(origin_stage,'') = ''");
$gapW02  = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = 'W02'");
$dvpSurf = (int) $one("SELECT COUNT(*) FROM repair01_surfaces WHERE canonical_code = 'EX-DVP'");
gate('W10-20', 'أساسُ المراحلِ السابقةِ لم يُمَسّ',
     $decN === $W00['decisions'] && $srcN === $W00['source_files'] && $surfN === $W00['surfaces'] && $baseN === $W00['registry_base'] && $unst === 0
     && $gapOrig === $W00['gaps_original'] && $gapW02 === 160 && $dvpSurf === 0,
     "قرارات $decN · مصادر $srcN · أسطح $surfN · أساسُ السجلّ $baseN · نموٌّ مختومٌ $growN"
     . " · بلا ختمٍ $unst · فجواتٌ أصليّة $gapOrig · منقولةٌ في W02 $gapW02 · سطحُ نوّابٍ مبنيٌّ $dvpSurf");

/* ══ W10-21 · شقُّ دفترِ الفجواتِ مكتملٌ بلا دهسِ وحدتِه القديمة ════════ */
/* ⚠ **الشقُّ اكتمل فانتقلت مفردتُه — والقاعدةُ لم تنتقل** (RPR-02-A · الطور صفر):
     كان المقياسُ يسأل `unit IN (الأسماءِ الحيّةِ القديمة)` ويشترط بقاءَ
     `unit = 'مكتب الرئيس التنفيذي والنواب'` صفوفًا. وقد وحَّد الطورُ صفرُ عمودَ
     `unit` **رموزًا معياريّةً** (‏بندٌ من التحقُّقِ الخماسيِّ · 21 رمزًا مميَّزًا)،
     **فصار الاسمُ القديمُ مفردةً لا وجودَ لها** والاستعلامُ يعيد صفرًا.
   ◆ **ولم يُدهَس الاسمُ بل انتقل إلى بيتِه**: `repair01_dept_crosswalk` هو
     الجسرُ الذي يحمل `legacy_name ⇐ canonical_code` — **وهو مصدرُ الحقيقةِ
     للاسمِ القديمِ لا صفُّ الفجوة**، وإبقاؤه في العمودَين معًا تكرارٌ.
   ⇒ فالمقامُ يُشتقّ من الجسرِ نفسِه (‏لا رمزٌ مكتوبٌ حرفيًّا)، **والقاعدةُ
     بحرفِها**: كلُّ فجوةٍ في وحدةٍ مشقوقةٍ تحمل رمزَها وقاعدتَها وسببَها،
     ⛔ **والاسمُ القديمُ يبقى محفوظًا — يُطلَب في بيتِه** فيسقط الحاجبُ لو مُحي. */
$splitCodes = array();
foreach ($units as $legacy => $inner) { foreach (array_keys($inner) as $c) { $splitCodes[$c] = 1; } }
$splitCodes = array_keys($splitCodes);
$inCodes = implode(',', array_map($E, $splitCodes));
/* ⛔ **ومقامُ الشقِّ هو الدفترُ الأصليُّ وحدَه**: صفوفُ `origin_stage = 'W02'`
     نموٌّ نُقل بعد الشقِّ (‏70 صفًّا مصدرُها `live:gov_screen_cycle`)، ولم تكن
     يومًا تحت الاسمِ المشقوق. وضمُّها يجعل المقامَ 100 والمشقوقَ 30 فيسقط
     الحاجبُ على نموٍّ لا على نقصِ شقّ — **ومقامٌ أوسعُ من قاعدتِه يكذب**. */
$SCOPE_W = "unit IN ($inCodes) AND COALESCE(origin_stage,'') = ''";
$gapScope = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE $SCOPE_W");
$gapSplit = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps
                         WHERE $SCOPE_W
                           AND split_code <> '' AND split_rule <> '' AND split_why <> ''");
$gapDvp = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE split_code = 'EX-DVP'");
/* الاسمُ القديمُ محفوظٌ في الجسر — لا في صفِّ الفجوة */
$legacyKept = (int) $one("SELECT COUNT(DISTINCT legacy_name) FROM repair01_dept_crosswalk WHERE verdict = 'SPLIT'");
gate('W10-21', 'شقُّ دفترِ الفجواتِ مكتملٌ والاسمُ القديمُ محفوظٌ في الجسر',
     $gapScope > 0 && $gapSplit === $gapScope && $gapDvp > 0
     && $legacyKept === count($units) && $legacyKept > 0,
     "رموزُ الشقِّ " . count($splitCodes) . " · فجواتُها $gapScope · مشقوقةٌ بقاعدةٍ $gapSplit"
     . " · للنوّاب $gapDvp · أسماءٌ قديمةٌ محفوظةٌ في الجسر $legacyKept من " . count($units));

/* ══ W10-22 · رحلةُ الشقِّ تعبر ولا تترك أثرًا ═════════════════════════ */
$jOut = array(); $jCode = 1;
@exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_w10_journey.php" 2>&1', $jOut, $jCode);
$run = '';
foreach ($jOut as $l) { if (preg_match('/^RUN=(W10J-\d+)$/', trim($l), $m)) { $run = $m[1]; break; } }
$jTotal = (int) $one("SELECT COUNT(*) FROM repair01_w10_journey WHERE run_id = " . $E($run));
$jPass  = (int) $one("SELECT COUNT(*) FROM repair01_w10_journey WHERE run_id = " . $E($run) . " AND passed = 1");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w10_journey
                       WHERE run_id = " . $E($run) . " AND (business_effect = '' OR business_effect = '—')");
$jCons  = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w10_journey WHERE run_id = " . $E($run));
$jLegs  = (int) $one("SELECT COUNT(DISTINCT leg) FROM repair01_w10_journey WHERE run_id = " . $E($run));
$jLeft  = (int) $one("SELECT COUNT(*) FROM ems_business_events
                       WHERE event_key IN (" . implode(',', array_map($E, $EV)) . ")")
        + (int) $one("SELECT COUNT(*) FROM ems_event_outbox
                       WHERE event_code IN (" . implode(',', array_map($E, $EV)) . ")");
gate('W10-22', 'رحلةُ الشقِّ تعبر ولا تترك أثرًا',
     $jCode === 0 && $run !== '' && $jTotal >= 34 && $jPass === $jTotal
     && $jCons >= 14 && $jLegs >= 5 && $jNoEff === 0 && $jLeft === 0,
     'الجولة ' . ($run !== '' ? $run : '— لم تُعلَن —')
     . " · عابرٌ $jPass/$jTotal · أشواطٌ $jLegs · مستهلكونَ متمايزون $jCons"
     . " · بلا أثرٍ تجاريٍّ $jNoEff · أثرٌ باقٍ $jLeft" . ($jCode !== 0 ? ' · رمزُ الخروج ' . $jCode : ''));

/* ═══════════════════════ الطباعة ═══════════════════════ */
foreach ($rows as $x) {
    printf("  %s %-9s %-48s %s\n", $x[2] ? '✔' : '✘', $x[0], $x[1], $x[3]);
}
echo str_repeat('─', 124) . "\n";
printf("W10 gate: %d/%d  ·  DEP-05 %d + DEP-06 %d = %d  ·  مفتاحٌ تاريخيٌّ مكسور %d  ·  الجسر %d  ·  رحلةٌ %d/%d\n",
    $pass, $pass + $fail, $finSurf5, $finSurf6, $finSurfN,
    $orphanSurf + $badId + $aliasSplit + $navOrphan, $brN, $jPass, $jTotal);
echo 'الحكم: ' . ($fail === 0 ? "خضراء ✔\n" : "ساقطة ✘\n");
exit($fail === 0 ? 0 : 1);
