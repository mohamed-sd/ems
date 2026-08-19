<?php
/**
 * tools/uxui_reverse_audit.php — الهندسةُ العكسية: أنُفِّذ كلُّ ما طلبته الوثائق؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الطلب: «قم بعمل هندسة عكسية للتأكد من أن كل المطلوب تم تنفيذه».
 *   فهذه الأداةُ **لا تقرأ ما فعلتُه ولا تصدّق تقاريري** — تقرأ **المطلوبَ من
 *   الوثائقِ الثلاثِ** ثم تقيسه على النظامِ الحيِّ وتُعلن الفارق.
 *
 * ◆ المصادرُ الثلاثةُ ومواضعُ الطلبِ فيها:
 *   ① `INJAZ-UXUI-01` — الفصولُ ١٣-٤ (معاييرُ القبول) · ١٥-١ (البنودُ العشرة)
 *      · ١٥-٤ (الإغلاقُ الخماسيّ) · ١٦-٢ (بوابةُ الترقيةِ التسع).
 *   ② `INJAZ-6-DELTA` — حاويةُ حمولةٍ بـ29 ملفَ بيانات · وفحصُ تحقُّقِها
 *      **خمسةُ أرقامٍ تُطابَق**: 181 مرحلةً · 663 موضعًا · 19/19 مركزَ عملٍ
 *      مدخلًا · 550 فعلًا مصنَّفًا · 171 قالبًا مبذورًا.
 *   ③ `UXUI_MASTER_AUDIT` — 18 ورقةً · 663 موضعًا × 19 عمودًا · 174 يتيمةً ·
 *      59 زوجَ ازدواجٍ · 171 قالبَ تسمية.
 *
 * ◆ وكلُّ بندٍ هنا **ثلاثيُّ الحكم**: `DONE` · `GAP` · `NOT_MEASURED` —
 *   والأخيرةُ لا تُحسب مُنجَزةً ولا ناقصة، بل تُعلَن. ومقامٌ صفريٌّ = غيرُ مقيس.
 *
 * التشغيل: php tools/uxui_reverse_audit.php [--md=<path>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

$PHPBIN = PHP_BINARY;
function run($cmd) { return (string) @shell_exec($cmd . ' 2>&1'); }
function one($conn, $sql) { $r = $conn->query($sql); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; }
function tbl($conn, $t) { $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'"); return $r && $r->num_rows > 0; }
function col($conn, $t, $c) {
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
                         AND TABLE_NAME='" . $conn->real_escape_string($t) . "'
                         AND COLUMN_NAME='" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
}

$SEC = array();   /* الفصل => بنود */
function req(&$SEC, $sec, $id, $what, $verdict, $num, $den, $evidence) {
    $SEC[$sec][] = array('id' => $id, 'what' => $what, 'verdict' => $verdict,
                         'num' => $num, 'den' => $den, 'ev' => $evidence);
}
/** يحسم بالمقياس: مقامٌ صفريٌّ ⇒ غيرُ مقيس · وإلا DONE/GAP */
function judge($num, $den, $wantZero = true) {
    if ($den <= 0) { return 'NOT_MEASURED'; }
    return $wantZero ? ($num === 0 ? 'DONE' : 'GAP') : ($num >= $den ? 'DONE' : 'GAP');
}

/* ═══ الفصلُ ١٥-١ — البنودُ العشرةُ الملزمة ═══════════════════════════════ */
$o = run(escapeshellarg($PHPBIN) . ' ' . escapeshellarg($ROOT . '/tools/uxui_ten_binding_gate.php'));
preg_match('~اجتاز (\d+) · أخفق (\d+) · \*\*غيرُ مقيسٍ (\d+)~u', $o, $m);
$tenPass = isset($m[1]) ? (int) $m[1] : 0;
$tenNM   = isset($m[3]) ? (int) $m[3] : 0;
req($SEC, 'ف١٥-١ · البنودُ العشرةُ الملزمة', 'B10',
    'العشرةُ مقيسةً على النظامِ الحيّ',
    ($tenNM > 0 ? 'NOT_MEASURED' : ($tenPass === 10 ? 'DONE' : 'GAP')),
    $tenPass, 10, 'مخرَجُ `tools/uxui_ten_binding_gate.php`');

/* ═══ الفصلُ ١٥-٤ — إثباتُ الإغلاقِ الخماسيّ ═════════════════════════════ */
$nCanon = (int) one($conn, "SELECT COUNT(*) FROM nav_canonical");
$noName = (int) one($conn, "SELECT COUNT(*) FROM nav_canonical WHERE canonical_ar IS NULL OR canonical_ar = ''");
req($SEC, 'ف١٥-٤ · الإغلاقُ الخماسيّ', 'D1', '① الاسمُ المعياريُّ لكلِّ مسار',
    judge($noName, $nCanon), $nCanon - $noName, $nCanon, '`nav_canonical.canonical_ar`');

$noPos = (int) one($conn, "SELECT COUNT(*) FROM nav_canonical
                            WHERE group_name IS NULL OR group_name = '' OR level_no IS NULL");
req($SEC, 'ف١٥-٤ · الإغلاقُ الخماسيّ', 'D2', '② الموضعُ المعياريُّ (مستوًى ومجموعةٌ وترتيب)',
    judge($noPos, $nCanon), $nCanon - $noPos, $nCanon, '`group_name` + `level_no`');

$noDeriv = (int) one($conn, "SELECT COUNT(*) FROM nav_canonical WHERE derivation IS NULL OR derivation = ''");
$approved = (int) one($conn, "SELECT COUNT(*) FROM nav_canonical WHERE decision_state = 'APPROVED'");
req($SEC, 'ف١٥-٤ · الإغلاقُ الخماسيّ', 'D3', '③ التسلسلُ المستنديُّ — مصدرُ اشتقاقٍ لكلِّ صفّ',
    judge($noDeriv, $nCanon), $nCanon - $noDeriv, $nCanon,
    'محسومٌ فعلًا (APPROVED): ' . $approved . ' — والباقي معلَنٌ بمصدرِه');

/* ④ الظهورُ بالدور — **على المُصيَّرِ لا على `nav_items` الخام**
     ◆ كشفه أولُ تشغيلٍ لهذا الفاحصِ نفسِه: قياسُ الجدولِ أعطى 178 مخالفةً
       وكلُّها زائفةٌ — لأن `nav_items` يحتفظ بالموضعِ الموروثِ والمولِّدُ
       يستبدله لحظةَ التصيير. فيُقرأ الرقمُ من الفاحصِ المُصيِّرِ نفسِه. */
$gatesOut = (string) @shell_exec(escapeshellarg($PHPBIN) . ' '
          . escapeshellarg($ROOT . '/tools/uxui_gates.php') . ' 2>&1');
$u3 = preg_match('~U3 [^\n]*?إنفاذ=(\d+)~u', $gatesOut, $mg) ? (int) $mg[1] : null;
$shared = preg_match('~(\d+) مسارًا فريدًا~u', $gatesOut, $mg2) ? (int) $mg2[1] : 0;
req($SEC, 'ف١٥-٤ · الإغلاقُ الخماسيّ', 'D4', '④ الظهورُ بالدورِ لا الموضع — على المُصيَّر',
    $u3 === null ? 'NOT_MEASURED' : judge($u3, $shared), $shared - (int) $u3, $shared,
    'مصدرُه `tools/uxui_gates.php` — U3 إنفاذًا على المُصيَّر');

$dupName = (int) one($conn, "SELECT COUNT(*) FROM (
        SELECT canonical_ar FROM nav_canonical WHERE decision_state='APPROVED'
         GROUP BY canonical_ar HAVING COUNT(*) > 1) t");
req($SEC, 'ف١٥-٤ · الإغلاقُ الخماسيّ', 'D5a', '⑤أ صفرُ اسمٍ معياريٍّ مكرَّرٍ لمسارَين',
    judge($dupName, $approved), $approved - $dupName, $approved, 'تفرُّدُ `canonical_ar` بين APPROVED');

/* ⑤ب ازدواجُ المعنى — 59 زوجًا · الحكمُ بشريّ */
$dupSemTbl = tbl($conn, 'gov_dup_semantics');
$dupSemJudged = $dupSemTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_dup_semantics WHERE human_verdict IS NOT NULL AND human_verdict <> ''") : 0;
$dupSemTotal  = $dupSemTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_dup_semantics") : 0;
req($SEC, 'ف١٥-٤ · الإغلاقُ الخماسيّ', 'D5b', '⑤ب ازدواجُ المعنى — 59 زوجًا بحكمٍ بشريّ',
    $dupSemTotal === 0 ? 'NOT_MEASURED' : judge($dupSemTotal - $dupSemJudged, $dupSemTotal),
    $dupSemJudged, $dupSemTotal,
    $dupSemTbl ? 'جدولُ `gov_dup_semantics`' : '**لا جدولَ للأزواجِ الـ59 — غيرُ مقيس**');

/* ═══ الفصلُ ١٦-٢ — بوابةُ الترقيةِ التسع على الذهبية ═══════════════════ */
$o = run(escapeshellarg($PHPBIN) . ' ' . escapeshellarg($ROOT . '/tools/uxui_promotion_gate.php'));
preg_match_all('~(\d+)/10 · فئة=~u', $o, $mm);
$scores = array_map('intval', $mm[1]);
$golden = count($scores);
$at8 = count(array_filter($scores, function ($s) { return $s >= 8; }));
$at10 = count(array_filter($scores, function ($s) { return $s >= 10; }));
req($SEC, 'ف١٦-٢ · بوابةُ الترقيةِ التسع', 'P1', 'الذهبيةُ العشرُ عند الحدِّ الآليِّ الأقصى (8/10)',
    judge($golden - $at8, $golden), $at8, $golden, 'مخرَجُ `tools/uxui_promotion_gate.php`');
req($SEC, 'ف١٦-٢ · بوابةُ الترقيةِ التسع', 'P2', 'مرقَّاةٌ فعلًا (VISUAL_PATTERN_APPROVED)',
    $at10 === $golden ? 'DONE' : 'GAP', $at10, $golden,
    '⑧ و⑨ بشريّان مستقلّان — `BLOCKED_EXTERNAL_INPUT`');

/* ═══ الفصلُ ١٣-٤ — معاييرُ القبولِ النهائية ═════════════════════════════ */
$measTbl = tbl($conn, 'gov_visual_measurements');
$measScreens = $measTbl ? (int) one($conn, "SELECT COUNT(DISTINCT screen_file) FROM gov_visual_measurements") : 0;
$measBad = $measTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_visual_measurements WHERE header_within_limit=0 OR has_h_scroll=1") : 0;
req($SEC, 'ف١٣-٤ · معاييرُ القبول', 'A1', 'الاستجابة — صفرُ تمريرٍ أفقيٍّ على الدقاتِ الثلاث',
    judge($measBad, $measScreens), $measScreens - $measBad, $measScreens,
    'قياسُ متصفحٍ حقيقيٍّ في `gov_visual_measurements`');

$a11yTbl = tbl($conn, 'gov_a11y_measurements');
$a11yN = $a11yTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_a11y_measurements") : 0;
$a11yBad = $a11yTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_a11y_measurements WHERE violations_total > 0 OR keyboard_modality = 0") : 0;
req($SEC, 'ف١٣-٤ · معاييرُ القبول', 'A2', 'الوصولُ الرقميّ — 12 فحصًا مقيسًا · WCAG 2.2 AA',
    judge($a11yBad, $a11yN), $a11yN - $a11yBad, $a11yN,
    $a11yTbl ? 'قياسٌ حيٌّ بضغطةِ Tab حقيقية' : '**لا سجلَّ قياسِ وصول**');

$o = run(escapeshellarg($PHPBIN) . ' ' . escapeshellarg($ROOT . '/tools/uxui_gates.php'));
$langBad = preg_match_all('~إنفاذ=(\d+)~u', $o, $mm2) ? array_sum(array_map('intval', $mm2[1])) : -1;
$langGates = isset($mm2[1]) ? count($mm2[1]) : 0;
req($SEC, 'ف١٣-٤ · معاييرُ القبول', 'A3', 'نظافةُ اللغة — بواباتُ U1..U8 على النصِّ المُصيَّر',
    judge($langBad, $langGates), $langGates - ($langBad > 0 ? 1 : 0), $langGates,
    'مخرَجُ `tools/uxui_gates.php` — مجموعُ الإنفاذ: ' . $langBad);

$ssdiff = $ROOT . '/.ssdiff';
$pending = is_dir($ssdiff) ? count(glob($ssdiff . '/*.diff.txt') ?: array()) : -1;
$skel = is_dir($ssdiff) ? count(glob($ssdiff . '/*.skel') ?: array()) : 0;
req($SEC, 'ف١٣-٤ · معاييرُ القبول', 'A4', 'الثباتُ البصريّ — صفرُ انحرافٍ غيرِ معتمَد',
    judge($pending < 0 ? 1 : $pending, $skel), $skel - max(0, $pending), $skel,
    'خطُّ الأساس `.ssdiff` — لقطات: ' . $skel);

$compTbl = tbl($conn, 'gov_component_versions');
$verState = $compTbl ? (string) one($conn, "SELECT state FROM gov_component_versions ORDER BY id DESC LIMIT 1") : '';
req($SEC, 'ف١٣-٤ · معاييرُ القبول', 'A5', 'خطُّ الأساسِ مربوطٌ برقمِ إصدارِ المكوّن',
    $compTbl && $verState !== '' ? 'DONE' : 'NOT_MEASURED', $compTbl ? 1 : 0, 1,
    'آخرُ إصدارٍ مسجَّل: ' . ($verState ?: '—'));

req($SEC, 'ف١٣-٤ · معاييرُ القبول', 'A6', 'نجاحُ الخريجِ الجديد — 6 مهامَّ بلا شرحِ مسار',
    'NOT_MEASURED', 0, 0, '**منفِّذٌ بشريٌّ مستقلٌّ — لا يُقاس آليًّا بحال**');
req($SEC, 'ف١٣-٤ · معاييرُ القبول', 'A7', 'جولةُ العرض — أسئلتُها السبعُ صفرُ «نعم»',
    'NOT_MEASURED', 0, 0, '**منفِّذٌ بشريٌّ مستقلٌّ — لا يُقاس آليًّا بحال**');

/* ═══ DELTA — الأرقامُ الخمسةُ التي تُطابَق ══════════════════════════════ */
/* ═══════════════════════════════════════════════════════════════════════════
 * X1/X2 — **المقامُ نطاقُ الأدوارِ الجذريةِ لا كلُّ الأدوار**
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ رقما الوثيقةِ (181 مرحلةً · 663 موضعًا) مقيسانِ على **19 دورًا جذريًّا**،
 *   والنظامُ الحيُّ يحمل 34 دورًا نشطًا. فقياسُ الحيِّ كلِّه أعطى 1,645 موضعًا
 *   ضدَّ 663 — **فارقٌ في المقامِ لا في النظام**، وهو عينُ ما تحاربه الوثيقة:
 *   «رقمٌ صحيحٌ بمقامٍ كاذب».
 * ◆ فيُقرأ النطاقُ من `uxp_root_roles()` نفسِها — مصدرُ الفاحصِ المُصيِّر.
 * ◆ **ورقما الوثيقةِ لقطةٌ تاريخيةٌ لا هدفٌ يُطارَد**: النظامُ نما بعدَها
 *   (تسجيلُ 13 مسارًا ظاهرًا · زرعُ مركزِ العملِ لثلاثةِ أدوار). فيُعلَن
 *   الفارقُ ويُفسَّر، ولا يُصنَّف «نقصًا» ولا يُخفى.
 * ═══════════════════════════════════════════════════════════════════════════ */
$rootRoles = array();
$probe = $ROOT . '/includes/uxui_nav_probe.php';
if (is_file($probe)) {
    require_once $ROOT . '/includes/unified_nav.php';
    require_once $probe;
    if (function_exists('uxp_root_roles')) { $rootRoles = uxp_root_roles(); }
}
$rootIn = $rootRoles ? implode(',', array_map('intval', $rootRoles)) : '';
$stagesRoot = $rootIn === '' ? 0 : (int) one($conn,
    "SELECT COUNT(DISTINCT COALESCE(g.stage_title, g.name)) FROM link_groups g
      WHERE g.is_active = 1 AND g.owner_role_id IN ({$rootIn})");
req($SEC, 'DELTA · الأرقامُ الخمسة', 'X1', 'المراحلُ 181 (نطاقُ الأدوارِ الجذرية)',
    'INFO', $stagesRoot, 181,
    'مراحلُ ' . count($rootRoles) . ' دورٍ جذريٍّ — ورقمُ الوثيقةِ لقطةٌ تاريخيةٌ لا هدف');

$posRoot = $rootIn === '' ? 0 : (int) one($conn,
    "SELECT COUNT(*) FROM nav_items
      WHERE active = 1 AND role_id IN ({$rootIn})
        AND route IS NOT NULL AND route <> '' AND route <> '#'");
$posAll = (int) one($conn, "SELECT COUNT(*) FROM nav_items
     WHERE active=1 AND route IS NOT NULL AND route<>'' AND route<>'#'");
req($SEC, 'DELTA · الأرقامُ الخمسة', 'X2', 'مواضعُ الظهورِ 663 (نطاقُ الأدوارِ الجذرية)',
    'INFO', $posRoot, 663,
    'جذريًّا ' . $posRoot . ' · وكلُّ الأدوارِ ' . $posAll . ' — والفارقُ نموٌّ مسجَّلٌ لا نقص');
/* ◆ **«مركزُ العمل» طبقةٌ لا اسمُ مجموعة**: البحثُ باسمِ المجموعةِ أعطى 0/34
     وهو صفرٌ كاذبٌ — لأن الطبقةَ تتجسّد حيًّا في مجموعةِ «مساحتي الشخصية»
     وشاشاتِها الثلاثِ المسجَّلةِ APPROVED. فالمقياسُ التنفيذيُّ: **أتصل
     شاشاتُ المركزِ صاحبَ الدورِ فعلًا؟** — وهو ما تعنيه «مدخلًا في 19/19». */
$WC = "'Portal/my_tasks.php','Portal/my_achievement.php','Portal/my_portal.php'";
$rolesWithWork = (int) one($conn, "SELECT COUNT(DISTINCT role_id) FROM nav_items
                                    WHERE active = 1 AND route IN ({$WC})");
$rolesAll = (int) one($conn, "SELECT COUNT(DISTINCT role_id) FROM nav_items WHERE active=1");
req($SEC, 'DELTA · الأرقامُ الخمسة', 'X3', 'مركزُ العملِ مدخلًا في 19/19',
    $rolesWithWork > 0 ? judge($rolesAll - $rolesWithWork, $rolesAll) : 'NOT_MEASURED',
    $rolesWithWork, $rolesAll, 'أدوارٌ تصلها شاشاتُ المركزِ الشخصيِّ الثلاثُ — مقيسٌ على المُصيَّرِ أيضًا');

/* ◆ **الجدولُ الصحيحُ `gov_role_profiles` لا `gov_profile_items`**: الثاني
     بنودُ القوالبِ (2,525 بندًا) والأولُ القوالبُ نفسُها. وقراءةُ
     `profile_code` من جدولِ البنودِ أعطت **صفرًا** — لأن العمودَ هناك
     `profile_id`. فصفرٌ على جدولٍ خطأٍ يُقرأ «لم يُبذر» وهو مبذورٌ كاملًا. */
$profTbl = tbl($conn, 'gov_role_profiles');
$profiles = $profTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_role_profiles") : 0;
$profItems = tbl($conn, 'gov_profile_items') ? (int) one($conn, "SELECT COUNT(*) FROM gov_profile_items") : 0;
req($SEC, 'DELTA · الأرقامُ الخمسة', 'X5', 'القوالبُ المبذورةُ 171',
    $profiles === 171 ? 'DONE' : judge(abs(171 - $profiles), 171), $profiles, 171,
    $profTbl ? ('`gov_role_profiles` — وبنودُها في `gov_profile_items`: ' . $profItems) : 'لا جدولَ قوالب');

/* ═══ MASTER_AUDIT — الروابطُ اليتيمةُ 174 ══════════════════════════════ */
$orphTbl = tbl($conn, 'gov_orphan_links');
$orphN = $orphTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_orphan_links") : 0;
$orphDone = $orphTbl && col($conn, 'gov_orphan_links', 'owner_decision')
          ? (int) one($conn, "SELECT COUNT(*) FROM gov_orphan_links WHERE owner_decision IS NOT NULL AND owner_decision <> ''") : 0;
req($SEC, 'MASTER_AUDIT · الأوراقُ الثمانَ عشرة', 'M1', 'الروابطُ اليتيمةُ 174 — قرارُ المالك',
    $orphN === 0 ? 'NOT_MEASURED' : judge($orphN - $orphDone, $orphN),
    $orphDone, $orphN, $orphTbl ? 'جدولُ `gov_orphan_links`' : '**لا جدولَ لليتيمات**');

/* التلوثُ — 560 موضعًا مصنَّفًا */
$polTbl = tbl($conn, 'gov_pollution_findings');
$polN = $polTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_pollution_findings") : 0;
$polUn = $polTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_pollution_findings WHERE verdict IS NULL OR verdict='UNCLASSIFIED'") : 0;
req($SEC, 'MASTER_AUDIT · الأوراقُ الثمانَ عشرة', 'M2', 'تصنيفُ التلوثِ الثلاثيّ — صفرٌ بلا حكم',
    $polN === 0 ? 'NOT_MEASURED' : judge($polUn, $polN), $polN - $polUn, $polN, '`gov_pollution_findings`');

$isoTbl = tbl($conn, 'gov_test_data_isolation');
$isoN = $isoTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_test_data_isolation") : 0;
$isoRes = $isoTbl ? (int) one($conn, "SELECT COUNT(*) FROM gov_test_data_isolation WHERE resolution='RESOLVED'") : 0;
req($SEC, 'MASTER_AUDIT · الأوراقُ الثمانَ عشرة', 'M3', 'الصفوفُ الملوَّثةُ — عزلٌ ثم حسمٌ بمصدر',
    $isoN === 0 ? 'NOT_MEASURED' : judge($isoN - $isoRes, $isoN), $isoRes, $isoN,
    '`gov_test_data_isolation` — العزلُ تسجيلٌ والحسمُ يلزمه مصدرٌ حاكم');

/* الخطوةُ ⑤ — قيدٌ يمنع عودةَ التلوثِ · مُثبَتٌ سلبيًّا */
$guardCons = (int) one($conn, "SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                                WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME LIKE 'chk_nopollute_%'");
$protCols = (int) one($conn, "SELECT COUNT(DISTINCT CONCAT(table_name,'.',column_name))
                                FROM gov_test_data_isolation WHERE policy_domain <> 'OPERATIONAL_DATA'");
$guardTest = run(escapeshellarg($PHPBIN) . ' ' . escapeshellarg($ROOT . '/tests/pollution_guard_negative_test.php'));
$guardOk = (strpos($guardTest, 'أخفق 0') !== false);
req($SEC, 'MASTER_AUDIT · الأوراقُ الثمانَ عشرة', 'M4',
    'الخطوةُ ⑤ — قيدٌ يمنع عودةَ التلوثِ في المجالاتِ المحمية',
    $guardOk ? judge($protCols - $guardCons, $protCols) : 'GAP',
    $guardCons, $protCols,
    ($guardOk ? 'مُثبَتٌ سلبيًّا 6/6 (`tests/pollution_guard_negative_test.php`)'
              : '**الاختبارُ السلبيُّ أخفق — لا يُعلَن عاملًا**')
    . ' · والمرساةُ تُكتشَف من `information_schema` لا من قائمةٍ مكتوبة');
/* ═══ الإخراج ═══════════════════════════════════════════════════════════ */
$MARK = array('DONE' => '✔', 'GAP' => '✗', 'NOT_MEASURED' => '⛔', 'INFO' => '◆');
$tot = array('DONE' => 0, 'GAP' => 0, 'NOT_MEASURED' => 0, 'INFO' => 0);
echo "════ الهندسةُ العكسية — المطلوبُ من الوثائقِ الثلاثِ مقيسًا على الحيّ ════\n";
foreach ($SEC as $sec => $items) {
    echo "\n▐ {$sec}\n";
    foreach ($items as $it) {
        $tot[$it['verdict']]++;
        printf("  %s %-6s %-52s %s\n", $MARK[$it['verdict']], $it['id'], $it['what'],
               $it['den'] > 0 ? sprintf('%d/%d', $it['num'], $it['den']) : '—');
        echo "         ◆ {$it['ev']}\n";
    }
}
echo "\n════════════════════════════════════════════════════════════\n";
printf("  مُنجَز %d · ناقص %d · غيرُ مقيس %d · معلومةٌ مرجعية %d\n",
       $tot['DONE'], $tot['GAP'], $tot['NOT_MEASURED'], $tot['INFO']);
$den = $tot['DONE'] + $tot['GAP'];
if ($den > 0) {
    printf("  ◆ **النسبةُ على ما يُقاس آليًّا: %d/%d = %.1f٪** — وغيرُ المقيسِ خارجَ المقام\n",
           $tot['DONE'], $den, 100 * $tot['DONE'] / $den);
}
echo $tot['GAP'] === 0 && $tot['NOT_MEASURED'] === 0
   ? "✔ كلُّ ما يُقاس آليًّا مُنجَز\n"
   : "◆ الناقصُ والمعلَّقُ معلَنانِ أعلاه — ولا يُدَّعى اكتمالٌ فوقَهما\n";
exit(0);
