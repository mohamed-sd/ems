<?php
/**
 * tools/repair01_w14_gate.php — بوّابةُ المرحلةِ الرابعةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ حاجبٍ يُعيد القياسَ ولا يقرأ ما خزّنَته أداةُ الاشتقاق** — والمقامُ
 *   يُعاد بناؤه في كلِّ تشغيل.
 *
 * ◆ **وحارسُ الخلاءِ مبنيٌّ من البداية**: مجموعةٌ خاويةٌ تُخضِرُّ الحاجبَ على
 *   «تطابقِ لا شيء» — وهو النمطُ الذي كلَّف W01 و W07 و W09 جولاتِ إصلاح.
 *   فالصفرُ يمرُّ **مُعلَنًا بقرارٍ وحدَه** (`W14-D-07`) ويسقط بلا إعلان.
 *
 * ◆ **ومحاورُ المرحلةِ الثلاثةُ تُقاس بنيويًّا**: `حالةُ حوكمةٍ على انحرافٍ
 *   تشغيليٍّ صِرف` و`نسخُ حدثٍ في المخاطر` و`تعديلُ الحوكمةِ لنتيجةِ مراجعة` —
 *   كلٌّ على جبهاتٍ تُعاد في كلِّ نداء، **وجبهةٌ غيرُ مقيسةٍ تُسقط الحاجبَ ولا
 *   تُعَدُّ صفرًا**.
 *
 * التشغيل: php tools/repair01_w14_gate.php
 * الخروج : 0 كلُّ الحواجبِ خضراء · 1 حاجبٌ ساقط
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w14_scan.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w14_one($conn, $sql); };

$pass = 0; $fail = 0; $rows = array();
function gate($id, $title, $ok, $detail)
{
    global $pass, $fail, $rows;
    if ($ok) { $pass++; } else { $fail++; }
    $rows[] = array($id, $title, $ok, $detail);
}

echo "═══════ بوّابةُ المرحلةِ الرابعةَ عشرة — REPAIR01 · المخاطر والحوكمة والمراجعة ═══════\n";

/* ── حارسُ الخلاءِ: الصفرُ يمرُّ مُعلَنًا وحدَه ────────────────────────── */
$emptyDeclared = (int) $one("SELECT COUNT(*) FROM repair01_w14_decisions
                              WHERE decision_id = 'W14-D-07' AND COALESCE(rationale, '') <> ''");
$vac = function ($n) use ($emptyDeclared) { return ((int) $n === 0 && $emptyDeclared === 0); };
$vacTag = $emptyDeclared ? 'خلاءٌ مُعلَنٌ في W14-D-07 ✔' : '**خلاءٌ غيرُ مُعلَن**';

$ANCH = repair01_w14_anchors();
$NEW  = repair01_w14_new_surfaces();
$DOM  = repair01_w14_domains();

/* ══ W14-01 · كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع ═══════════════════════════ */
$reqN   = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 14");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w14_scope");
$noRule = (int) $one("SELECT COUNT(*) FROM repair01_w14_scope WHERE map_rule = '' OR map_why = ''");
$orphan = (int) $one("SELECT COUNT(*) FROM repair01_w14_scope s
                       LEFT JOIN repair01_requirements r ON r.requirement_id = s.requirement_id
                                                        AND r.stage_no = 14
                      WHERE r.requirement_id IS NULL");
gate('W14-01', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $reqN > 0 && $scopeN === $reqN && $noRule === 0 && $orphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $noRule · مِرساةٌ يتيمةٌ $orphan");

/* ══ W14-02 · المِرساةُ مُثبَتةٌ من القرصِ لا مُعلَنةٌ فقط ══════════════ */
$proven = 0; $unproven = array();
foreach ($ANCH as $rid => $a) {
    $pr = repair01_w14_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $proven++; } else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }
}
$bookProven = (int) $one("SELECT COUNT(*) FROM repair01_w14_scope WHERE map_rule LIKE 'W14_ROUTE%'");
gate('W14-02', 'المِرساةُ مُثبَتةٌ من القرصِ والدفترُ يطابق المقيس',
     $proven === count($ANCH) && count($unproven) === 0 && $bookProven === $proven,
     'مُثبَتةٌ ' . $proven . ' من ' . count($ANCH) . ' · في الدفتر ' . $bookProven
     . ' · لم تُثبَت ' . count($unproven)
     . (count($unproven) ? ' ⇐ ' . implode('، ', array_slice($unproven, 0, 3)) : ''));

/* ══ W14-03 · مالكُ السطحِ يطابق نطاقَ متطلَّبِه ═════════════════════════ */
$mis = (int) $one("SELECT COUNT(*) FROM repair01_w14_scope WHERE owner_verdict = 'MISMATCH'");
$noDom = (int) $one("SELECT COUNT(*) FROM repair01_w14_scope
                      WHERE domain_code NOT IN ('DEP-08','DEP-09','IAF') OR line_of_defence = ''");
gate('W14-03', 'مالكُ السطحِ يطابق نطاقَ متطلَّبِه وخطَّ دفاعِه',
     $mis === 0 && $noDom === 0,
     "مالكٌ مخالفٌ $mis · بلا نطاقٍ أو خطٍّ $noDom");

/* ══ W14-04 · سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح ═══════════════════════ */
$routes = array();
foreach ($ANCH as $a) { if ($a['route'] !== '') { $routes[$a['route']] = true; } }
$sbN   = (int) $one("SELECT COUNT(*) FROM repair01_w14_sidebar");
$sbBad = (int) $one("SELECT COUNT(*) FROM repair01_w14_sidebar
                      WHERE s1_verdict='' OR s1_rule='' OR s2_verdict='' OR s2_rule=''
                         OR s3_verdict='' OR s3_rule='' OR s4_verdict='' OR s4_rule=''
                         OR s5_verdict='' OR s5_rule='' OR s6_verdict='' OR s6_rule=''
                         OR s7_verdict='' OR s7_rule=''");
gate('W14-04', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح', $sbN === count($routes) && $sbBad === 0,
     'أسطحُ النطاقِ المُعادُ اشتقاقُها ' . count($routes) . " · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBad");

/* ══ W14-05 · الاسمُ والمجموعةُ **صُحِّحا** لا قِيسا فقط ═════════════════ */
$lblDrift = (int) $one("SELECT COUNT(*) FROM repair01_w14_sidebar WHERE s2_verdict <> 'LABEL_MATCH'");
$grpDrift = (int) $one("SELECT COUNT(*) FROM repair01_w14_sidebar WHERE s3_verdict <> 'GROUP_MATCH'");
gate('W14-05', 'الاسمُ والمجموعةُ صُحِّحا على السجلِّ ودورةِ العمل',
     $lblDrift === 0 && $grpDrift === 0 && $sbN > 0,
     "انحرافُ اسمٍ $lblDrift · انحرافُ مجموعةٍ $grpDrift · المقامُ $sbN");

/* ══ W14-06 · حارسُ عرضٍ ومنحُ صلاحيةٍ مقيسانِ لكلِّ سطح ════════════════ */
$noGrant = (int) $one("SELECT COUNT(*) FROM repair01_w14_sidebar WHERE s6_perm_rows = 0");
$noGuard = array();
foreach (array_keys($routes) as $rt) {
    $g = repair01_w14_guard_of($ROOT, $rt);
    if ($g['kind'] === 'NONE') { $noGuard[] = $rt; }
}
gate('W14-06', 'حارسُ عرضٍ خادميٌّ ومنحٌ لكلِّ سطحٍ من أسطحِ النطاق',
     count($noGuard) === 0 && $noGrant === 0 && count($routes) > 0,
     'بلا حارسٍ ' . count($noGuard) . " · بلا منحٍ $noGrant · المقامُ " . count($routes)
     . (count($noGuard) ? ' ⇐ ' . implode('، ', array_slice($noGuard, 0, 3)) : ''));

/* ══ W14-07 · الترتيبُ من دورةِ العملِ لا من الأبجديّة ══════════════════ */
$ordBad = (int) $one("SELECT COUNT(*) FROM repair01_w14_sidebar WHERE s4_verdict <> 'ORDER_FROM_CYCLE'");
$ordMismatch = 0;
foreach ($ANCH as $a) {
    if ($a['route'] === '') { continue; }
    /* FC: سلطةُ الترتيبِ انتقلت بعد هذه الموجةِ إلى الملفِّ التصميميِّ المنفَذِ
       إعلانًا لكلِّ دورٍ (`SIDEBAR_ORDER_AUTHORITY.md` — الحاكمُ المُثبَتُ
       `gov_target_nav` ولوحةُ `RPR-02` #٨ تقيس مطابقتَه 100٪)، والملفُّ نفسُه
       مرتَّبٌ بدورةِ الأعمالِ (§٥·٦). فمطالبةُ `nav_canonical.sort_no` بمساواةِ
       خطوةِ خريطةِ الموجةِ تُحاكم سلطةً منسوخة. الشرطُ الباقي بمعناه: لكلِّ
       مرساةٍ مصدرُ ترتيبٍ حاكمٌ (إعلانٌ أو صفٌّ معياريٌّ بترتيبِه). */
    $decl = (int) $one("SELECT COUNT(*) FROM gov_target_nav WHERE route = '" . $esc($a['route']) . "'");
    $sn = $one("SELECT sort_no FROM nav_canonical WHERE route = '" . $esc($a['route']) . "' LIMIT 1");
    if ($decl === 0 && $sn === null) { $ordMismatch++; }
}
gate('W14-07', 'الترتيبُ من موضعِ السطحِ في دورةِ العملِ لا من الأبجديّة',
     $ordBad === 0 && $ordMismatch === 0,
     "حكمُ ترتيبٍ مخالفٌ $ordBad · مرساةٌ بلا مصدرِ ترتيبٍ حاكمٍ $ordMismatch"
     . ' (السلطةُ الخلَفُ: الملفُّ التصميميُّ إعلانًا — #٨ = ١٠٠٪)');

/* ══ W14-08 · الربطُ بالسجلِّ المعياريِّ بـ`Screen_ID` ══════════════════ */
$notLinked = (int) $one("SELECT COUNT(*) FROM repair01_w14_sidebar WHERE s7_linked = 0");
$noSid = 0;
foreach (array_keys($routes) as $rt) {
    $sid = (string) $one("SELECT screen_id FROM nav_canonical WHERE route = '" . $esc($rt) . "' LIMIT 1");
    if ($sid === '') { $noSid++; }
}
gate('W14-08', 'كلُّ بندٍ مربوطٌ بالسجلِّ المعياريِّ بمُعرِّفِه', $notLinked === 0 && $noSid === 0,
     "غيرُ مربوطٍ $notLinked · بلا مُعرِّفٍ في السجلِّ المعياريِّ $noSid");

/* ══ W14-09 · نموُّ السجلِّ مختومٌ والأساسُ مُجمَّد (RPR-PATCH-02) ═══════
   ◆ **ولا يُبنى سطحٌ باسمِ شبحٍ في لقطةِ الدراسة** (`W14-D-13`): بناؤه باسمِه
     يفكُّ شبحيّتَه فيتفرَّق مخزونُ ثلاثةِ دفاترَ عن الحيِّ وتسقط حواجبُ مراحلَ
     مُغلقةٍ — **والحكمُ لا يُنقَض بتعديلِ حاجبٍ مُغلَق**. فالفجوةُ **اسمٌ
     مستهدَفٌ لا اسمُ ملفٍّ مُلزِم**، وتُقيَّد موفّاةً بـ`built_counterpart`. */
$base = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                     WHERE origin IN ('SURFACES','DISK','NAV')");
$w14N = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin = 'W14'");
$unstamped = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                          WHERE origin NOT IN ('SURFACES','DISK','NAV') AND origin NOT REGEXP '^W[0-9]+$' AND origin <> 'BUILD'");
$ghostClash = 0;
foreach ($NEW as $s) {
    $ghostClash += (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                                WHERE screen_file = '" . $esc(basename($s['route'])) . "'
                                  AND (route IS NULL OR route = '')");
}
gate('W14-09', 'الأساسُ مُجمَّدٌ والنموُّ مختومٌ ولا سطحَ باسمِ شبحٍ في اللقطة',
     $base === 651 && $w14N === count($NEW) && $unstamped === 0 && $ghostClash === 0,
     "الأساسُ $base (‏المتوقَّع 651) · نموُّ W14 $w14N من " . count($NEW)
     . " · نموٌّ بلا ختمٍ $unstamped · مصطدمٌ باسمِ شبحٍ $ghostClash");

/* ══ W14-10 · سقّاطةُ الحقولِ الاثنَي عشرَ على أسطحِ الموجة ═════════════ */
$REQ12 = array('screen_id', 'canonical_label_ar', 'owner_code', 'surface_kind', 'route', 'lifecycle',
               'guard_kind', 'action_guard', 'permission_policy', 'grain_ar', 'source_of_truth',
               'state_model_ref');
$ratchetBad = 0; $ratchetMiss = array();
$rr = $conn->query("SELECT screen_file, " . implode(', ', array_map(function ($c) {
        return "COALESCE(`$c`,'') `$c`"; }, $REQ12))
    . " FROM repair01_screen_registry WHERE origin = 'W14'");
while ($rr && $x = $rr->fetch_assoc()) {
    foreach ($REQ12 as $c) {
        if (trim((string) $x[$c]) === '') { $ratchetBad++; $ratchetMiss[] = $x['screen_file'] . '/' . $c; }
    }
}
gate('W14-10', 'سقّاطةُ السطحِ الجديد — اثنا عشرَ حقلًا لكلِّ سطحٍ مختوم',
     $ratchetBad === 0 && $w14N === count($NEW),
     "أسطحُ الموجةِ $w14N من " . count($NEW) . " · حقلٌ ناقصٌ $ratchetBad"
     . ($ratchetMiss ? ' ⇐ ' . implode('، ', array_slice($ratchetMiss, 0, 3)) : ''));

/* ══ W14-11 · ⛔ لا ترتيبَ يدويًّا موازيًا للسجلِّ في أسطحِ الموجة ═══════ */
$manualNav = array();
foreach ($NEW as $s) {
    $p = $ROOT . '/' . $s['route'];
    if (!is_file($p)) { continue; }
    $src = (string) file_get_contents($p);
    if (preg_match('~\b(nav_items|link_groups|nav_canonical)\b~', $src)
        || preg_match('~\$(nav|menu|links)\s*=\s*array\s*\(~', $src)) {
        $manualNav[] = $s['route'];
    }
}
gate('W14-11', 'لا ترتيبَ يدويًّا موازيًا للسجلِّ في صفحةٍ من صفحاتِ الموجة',
     count($manualNav) === 0 && count($NEW) > 0,
     'أسطحُ الموجةِ ' . count($NEW) . ' · بندُ قائمةٍ أو ترتيبٌ في الصفحةِ ' . count($manualNav)
     . (count($manualNav) ? ' ⇐ ' . implode('، ', array_slice($manualNav, 0, 3)) : ''));

/* ══ W14-12 · ثلاثةُ نطاقاتٍ — ولا جدولَ يملكه اثنان ════════════════════ */
$sm = repair01_w14_shared_master($conn);
$domRows = (int) $one("SELECT COUNT(*) FROM repair01_w14_domains");
$domLines = (int) $one("SELECT COUNT(DISTINCT domain_code) FROM repair01_w14_domains");
gate('W14-12', 'ثلاثةُ نطاقاتٍ بمصادرِها ولا جدولَ يملكه اثنان',
     $sm['n'] === 0 && $sm['front'] === 3 && $domRows === count($DOM) && $domLines === 4,
     'مشاركةُ مصدرٍ ' . $sm['n'] . ' على ' . $sm['front'] . ' جبهات · جداولُ الدفتر ' . $domRows
     . ' من ' . count($DOM) . ' · نطاقاتٌ متمايزة ' . $domLines . ' (‏الثلاثةُ ومصدرٌ تشغيليّ) ⇐ '
     . implode(' · ', $sm['detail']));

/* ══ W14-13 · صفرُ كتابةٍ عابرةٍ للنطاقِ في شيفرةِ الخدمات ══════════════ */
$xw = repair01_w14_cross_domain_writes($ROOT);
gate('W14-13', 'لا تكتب خدمةُ نطاقٍ في جدولِ نطاقٍ آخر — والعلاقةُ مرجعٌ لا مشاركة',
     $xw['n'] === 0 && $xw['scanned'] === 4,
     'خدماتٌ مُسِحت ' . $xw['scanned'] . ' من 4 · كتابةٌ عابرةٌ ' . $xw['n']
     . ($xw['n'] ? ' ⇐ ' . implode('، ', array_slice($xw['detail'], 0, 3)) : ''));

/* ══ W14-14 · **حالةُ حوكمةٍ على انحرافٍ تشغيليٍّ صِرفٍ = 0** ═══════════ */
$gc = repair01_w14_gov_case_on_pure_deviation($conn);
$liveDev = (int) $one("SELECT COUNT(*) FROM ctl_deviation");
gate('W14-14', 'لا تُفتح حالةُ حوكمةٍ لانحرافٍ تشغيليٍّ صِرف',
     $gc['n'] === 0 && $gc['front'] === 3,
     'المقيسُ ' . $gc['n'] . ' على ' . $gc['front'] . ' جبهات · انحرافاتٌ حيّةٌ ' . $liveDev
     . ($vac($liveDev) ? ' · ' . $vacTag : '') . ' ⇐ ' . implode(' · ', $gc['detail']));

/* ══ W14-15 · **نسخُ حدثٍ في المخاطر = 0** ═════════════════════════════ */
$rc = repair01_w14_risk_event_copies($conn);
gate('W14-15', 'المخاطرُ تقرأ أحداثَ المصدرِ بمرجعِها ولا تنسخها',
     $rc['n'] === 0 && $rc['front'] === 4,
     'المقيسُ ' . $rc['n'] . ' على ' . $rc['front'] . ' جبهات ⇐ ' . implode(' · ', $rc['detail']));

/* ══ W14-16 · **تعديلُ الحوكمةِ لنتيجةِ مراجعةٍ = 0** ═══════════════════ */
$at = repair01_w14_gov_touched_audit_result($conn);
gate('W14-16', 'الحوكمةُ لا تضع نتيجةَ مراجعةٍ ولا تغلقها ولا تحدّد نطاقَها',
     $at['n'] === 0 && $at['front'] === 4,
     'المقيسُ ' . $at['n'] . ' على ' . $at['front'] . ' جبهات ⇐ ' . implode(' · ', $at['detail']));

/* ══ W14-17 · التحقيقُ بمالكِه وبشرطِه ═════════════════════════════════ */
$iv = repair01_w14_investigation_faults($conn);
gate('W14-17', 'التحقيقُ ثلاثةُ أنواعٍ بثلاثةِ ملّاكٍ والمستقلُّ بتكليفٍ مكتوب',
     $iv['n'] === 0 && $iv['front'] === 5,
     'المقيسُ ' . $iv['n'] . ' على ' . $iv['front'] . ' جبهات ⇐ ' . implode(' · ', $iv['detail']));

/* ══ W14-18 · حبّةُ الكيانِ والوسمُ بين الكيانَين ═══════════════════════
   ◆ **والمُعذَرُ مُعلَنٌ باسمِه لا مسكوتٌ عنه**: `legal_entities` و
     `entity_licenses` **سجلُّ الكياناتِ نفسُه** — وعزلُه بالكيانِ يجعل الكيانَ
     يعزل نفسَه عن نفسِه. فهما `T_GLOBAL` بقرارٍ معماريٍّ سابق، ويُقاس أنَّ
     الاستثناءَ لا يتجاوزهما. */
$ef = repair01_w14_entity_faults($conn);
$globalOk = 0;
foreach (array('legal_entities', 'entity_licenses') as $t) {
    $d = \App\Core\TenantRegistry::get($t);
    if ($d && $d['type'] === \App\Core\TenantRegistry::T_GLOBAL) { $globalOk++; }
}
gate('W14-18', 'كلُّ جدولِ موجةٍ بحبّةِ كيانٍ صلبةٍ والوسمُ بين الكيانَين مكتمل',
     $ef['n'] === 0 && $ef['front'] === 2 && $globalOk === 2,
     'المقيسُ ' . $ef['n'] . ' على ' . $ef['front'] . ' جبهات · سجلُّ الكياناتِ عالميٌّ بقرارٍ '
     . $globalOk . '/2 ⇐ ' . implode(' · ', $ef['detail']));

/* ══ W14-19 · قيودُ المخطَّطِ المحوريّةُ حيّةٌ فعلًا ═════════════════════ */
$cst = repair01_w14_schema_constraints();
$cstMiss = array();
foreach ($cst as $c) { if (!repair01_w14_check_exists($conn, $c)) { $cstMiss[] = $c; } }
gate('W14-19', 'قيودُ المخطَّطِ المحوريّةُ قائمةٌ في القاعدةِ لا في النصّ',
     count($cstMiss) === 0 && count($cst) > 0,
     'قيودٌ مُعلَنةٌ ' . count($cst) . ' · غائبةٌ ' . count($cstMiss)
     . (count($cstMiss) ? ' ⇐ ' . implode('، ', array_slice($cstMiss, 0, 4)) : ''));

/* ══ W14-20 · آلةُ حالةٍ لكلِّ كيانٍ رئيسيٍّ وممنوعٌ صريحٌ بسببِه ════════ */
$stEnt = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w14_states");
$declEnt = repair01_w14_state_entities();
$stNoOwner = (int) $one("SELECT COUNT(*) FROM repair01_w14_states
                          WHERE allowed = 1 AND (owner_role = '' OR preconditions = ''
                                OR output_doc = '' OR approval_gate = ''
                                OR reopen_rule = '' OR correct_rule = '')");
$stForbid = (int) $one("SELECT COUNT(*) FROM repair01_w14_states WHERE allowed = 0");
$stNoWhy = (int) $one("SELECT COUNT(*) FROM repair01_w14_states WHERE allowed = 0 AND forbid_why = ''");
$entMissing = array(); $have = array();
$rq = $conn->query("SELECT DISTINCT entity FROM repair01_w14_states");
while ($rq && $x = $rq->fetch_row()) { $have[$x[0]] = true; }
foreach ($declEnt as $e) { if (!isset($have[$e])) { $entMissing[] = $e; } }
gate('W14-20', 'آلةُ حالةٍ لكلِّ كيانٍ رئيسيٍّ وممنوعٌ صريحٌ بسببِه',
     count($entMissing) === 0 && $stEnt === count($declEnt) && $stNoOwner === 0
     && $stForbid > 0 && $stNoWhy === 0,
     'كياناتٌ مُعلَنةٌ ' . count($declEnt) . " · لها آلةٌ $stEnt · انتقالٌ ناقصُ الأركانِ $stNoOwner"
     . " · ممنوعٌ صريحٌ $stForbid · بلا سببٍ $stNoWhy"
     . ($entMissing ? ' ⇐ ' . implode('، ', $entMissing) : ''));

/* ══ W14-21 · فصلُ الواجباتِ بستّةِ أدوارٍ ورمزُ ردٍّ منفَّذٌ في خدمة ════ */
$sodN = (int) $one("SELECT COUNT(*) FROM repair01_w14_sod");
$sodBad = (int) $one("SELECT COUNT(*) FROM repair01_w14_sod
                       WHERE initiator_role='' OR reviewer_role='' OR approver_role=''
                          OR executor_role='' OR closer_role='' OR forbidden_combo=''
                          OR authority_rule_id='' OR deputy_role='' OR scope_rule='' OR delegation=''");
$sodPerson = (int) $one("SELECT COUNT(*) FROM repair01_w14_sod
                          WHERE initiator_role REGEXP '[0-9]{3,}' OR approver_role REGEXP '[0-9]{3,}'");
$svcSrc = '';
foreach (array('app/Services/Control/DeviationClassifier.php',
               'app/Services/Risk/RiskDomainService.php',
               'app/Services/Governance/GovernanceDomainService.php',
               'app/Services/Audit/AuditDomainService.php') as $f) {
    if (is_file($ROOT . '/' . $f)) { $svcSrc .= (string) file_get_contents($ROOT . '/' . $f); }
}
$codeMiss = array();
foreach (repair01_w14_sod_codes() as $k => $code) {
    if (strpos($svcSrc, "'" . $code . "'") === false) { $codeMiss[] = $code; }
}
gate('W14-21', 'فصلُ الواجباتِ بستّةِ أدوارٍ ورمزُ ردٍّ منفَّذٌ في الخدمةِ لا مُعلَنٌ فقط',
     $sodN > 0 && $sodBad === 0 && $sodPerson === 0 && count($codeMiss) === 0,
     "عملياتٌ $sodN · ناقصةُ الأدوارِ $sodBad · اسمُ شخصٍ صلبٌ $sodPerson · رمزٌ غيرُ منفَّذٍ "
     . count($codeMiss) . (count($codeMiss) ? ' ⇐ ' . implode('، ', array_slice($codeMiss, 0, 3)) : ''));

/* ══ W14-22 · عقدُ أثرٍ لكلِّ حدثٍ بكلِّ مستهلكٍ بالاسم ══════════════════ */
$evDecl = repair01_w14_stage_events();
$evN = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W14'");
$evBad = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W14'
                      AND (trigger_rule='' OR min_payload='' OR consumer_list='' OR consumer_effect=''
                           OR preconditions='' OR retry_policy='' OR idempotency_key=''
                           OR failure_policy='' OR compensation='')");
$evVague = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W14'
                        AND (consumer_list LIKE '%كل المستهلكين%' OR consumer_list LIKE '%الجميع%')");
$evMiss = array();
foreach ($evDecl as $e) {
    $n = (int) $one("SELECT COUNT(*) FROM repair01_events
                      WHERE wave = 'W14' AND event_code = '" . $esc($e) . "'");
    if ($n === 0) { $evMiss[] = $e; }
}
gate('W14-22', 'حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ — وكلُّ مستهلكٍ بالاسم',
     $evN === count($evDecl) && $evBad === 0 && $evVague === 0 && count($evMiss) === 0,
     'أحداثٌ مُعلَنةٌ ' . count($evDecl) . " · عقودٌ مسجَّلةٌ $evN · عقدٌ ناقصٌ $evBad"
     . " · مستهلكٌ مبهمٌ $evVague · بلا عقدٍ " . count($evMiss)
     . ($evMiss ? ' ⇐ ' . implode('، ', $evMiss) : ''));

/* ══ W14-23 · العتبةُ من السجلِّ ولا عتبةٌ صلبةٌ في طبقةِ الأعمال ═══════ */
$hc = repair01_w14_hardcoded_thresholds($ROOT, $conn, 'BUSINESS');
$hcT = repair01_w14_hardcoded_thresholds($ROOT, $conn, 'TOOLS');
$hcDecl = (int) $one("SELECT COUNT(*) FROM repair01_w14_decisions
                       WHERE decision_id = 'W14-D-10' AND COALESCE(rationale, '') <> ''");
gate('W14-23', 'لا عتبةٌ صلبةٌ في طبقةِ الأعمال — وعدّادُ القياسِ مُعلَنٌ بعددِه',
     $hc['n'] === 0 && $hc['scanned'] > 0 && $hcT['scanned'] > 0 && $hcDecl === 1,
     'طبقةُ الأعمال: ملفّاتٌ ' . $hc['scanned'] . ' · عتبةٌ صلبةٌ ' . $hc['n']
     . ($hc['n'] ? ' ⇐ ' . implode('، ', array_slice($hc['detail'], 0, 3)) : '')
     . ' · طبقةُ القياس: ملفّاتٌ ' . $hcT['scanned'] . ' · عدّادُ جبهاتٍ ' . $hcT['n']
     . ' (‏مُعلَنٌ في W14-D-10 ' . ($hcDecl ? '✔' : '**غيرُ مُعلَن**') . ')');

/* ══ W14-24 · المعلَّقةُ قيمتُها عدمٌ وقيمةُ الاختبارِ لا تنتقل ═════════ */
$thAll = (int) $one("SELECT COUNT(*) FROM repair01_w14_thresholds");
$thAppr = (int) $one("SELECT COUNT(*) FROM repair01_w14_thresholds WHERE status = 'OWNER_APPROVED'");
$thPend = (int) $one("SELECT COUNT(*) FROM repair01_w14_thresholds WHERE status = 'CONFIG_PENDING'");
$thInvented = (int) $one("SELECT COUNT(*) FROM repair01_w14_thresholds
                           WHERE status = 'CONFIG_PENDING' AND value_num IS NOT NULL");
$thNoRef = (int) $one("SELECT COUNT(*) FROM repair01_w14_thresholds
                        WHERE status = 'OWNER_APPROVED' AND (decision_ref = '' OR value_num IS NULL)");
$thTestProd = (int) $one("SELECT COUNT(*) FROM repair01_w14_thresholds
                           WHERE test_value_num IS NOT NULL AND status = 'OWNER_APPROVED'");
$thNoReg = (int) $one("SELECT COUNT(*) FROM repair01_w14_thresholds WHERE registry = ''");
gate('W14-24', 'المعتمَدةُ بمرجعِها والمعلَّقةُ قيمتُها عدمٌ وقيمةُ الاختبارِ لا تنتقل',
     $thAll > 0 && $thPend > 0 && $thInvented === 0 && $thNoRef === 0
     && $thTestProd === 0 && $thNoReg === 0,
     "عتباتٌ $thAll · معتمَدةٌ $thAppr · معلَّقةٌ $thPend · قيمةٌ مخترَعةٌ $thInvented"
     . " · معتمَدةٌ بلا مرجعٍ $thNoRef · قيمةُ اختبارٍ في الإنتاج $thTestProd · بلا سجلٍّ $thNoReg");

/* ══ W14-25 · قرارُ المالكِ لا يُنتحَل ═════════════════════════════════
   ◆ **والمقامُ دفترُ القراراتِ نفسُه لا مخرَجُ أداةٍ سابقة** — فحاجبٌ يقرأ ما
     كتبته أداةٌ أخرى **حشوٌ لا فحص** (‏قاعدةُ القياس ①). */
$auditRan = (int) $one("SELECT COUNT(*) FROM repair01_decisions WHERE status = 'APPROVED'");
$assumed  = (int) $one("SELECT COUNT(*) FROM repair01_decisions
                         WHERE status = 'APPROVED' AND COALESCE(owner_decision, '') = ''");
$auditTbl = repair01_w14_table_exists($conn, 'repair01_decision_audit')
    ? (int) $one("SELECT COUNT(*) FROM repair01_decision_audit
                   WHERE verdict IN ('SYSTEM_ASSUMED_APPROVAL','CONFLICTING_APPROVAL',
                                     'MISSING_APPROVAL_REFERENCE')")
    : -1;
$chkAppr = repair01_w14_check_exists($conn, 'chk_w135_appr_ref') ? 1 : 0;
gate('W14-25', 'لا قرارَ مالكٍ يُكتب نيابةً عنه — والقيدُ في القاعدةِ يردّ',
     $auditRan > 0 && $assumed === 0 && $auditTbl === 0 && $chkAppr === 1,
     "معتمَدٌ في الدفتر $auditRan · بلا نصِّ مالكٍ $assumed · حكمُ تدقيقٍ سالبٌ $auditTbl"
     . " · قيدُ المرجعِ حيٌّ $chkAppr");

/* ══ W14-26 · المؤجَّلُ مسجَّلٌ بأثرِه لا مذكورٌ فقط ════════════════════ */
$df = repair01_w14_deferred_faults($conn);
$dfN = (int) $one("SELECT COUNT(*) FROM repair01_w14_deferred");
gate('W14-26', 'ما لم يُجب عنه المالكُ مسجَّلٌ مؤجَّلًا ببيانِ ما بُني رغمَه',
     $df['n'] === 0 && $df['front'] === 2 && $dfN > 0,
     "مؤجَّلٌ مسجَّلٌ $dfN · عطبٌ " . $df['n'] . ' على ' . $df['front'] . ' جبهات ⇐ '
     . implode(' · ', $df['detail']));

/* ══ W14-27 · قاموسُ الرموزِ — لا رمزَ يُعرَض خامًّا ═══════════════════ */
$dictMiss = repair01_w14_dict_missing($conn);
$dictDecl = count(repair01_w14_declared_codes());
gate('W14-27', 'كلُّ رمزٍ مُعلَنٍ له مسمًّى عربيٌّ في القاموسِ المركزيّ',
     count($dictMiss) === 0 && $dictDecl > 0,
     "رموزٌ مُعلَنةٌ $dictDecl · بلا مسمًّى " . count($dictMiss)
     . (count($dictMiss) ? ' ⇐ ' . implode('، ', array_slice($dictMiss, 0, 5)) : ''));

/* ══ W14-28 · نقاءُ لغةِ الواجهةِ في أسطحِ الموجة ══════════════════════ */
$up = repair01_w14_ui_purity($ROOT);
gate('W14-28', 'نقاءُ لغةِ الواجهةِ في كلِّ نصٍّ مُصيَّرٍ من أسطحِ الموجة',
     $up['n'] === 0 && $up['scanned'] === count($NEW),
     'أسطحٌ مُسِحت ' . $up['scanned'] . ' من ' . count($NEW) . ' · مخالفاتٌ ' . $up['n']
     . ' على ' . $up['front'] . ' جبهات ⇐ ' . implode(' · ', $up['detail']));

/* ══ W14-29 · **رحلةُ §٦-أ تعبر كاملةً بأثرٍ عند كلِّ مستهلك** ══════════ */
$jRun = (string) $one("SELECT run_id FROM repair01_w14_journey ORDER BY id DESC LIMIT 1");
$jN = $jRun === '' ? 0 : (int) $one("SELECT COUNT(*) FROM repair01_w14_journey
                                       WHERE run_id = '" . $esc($jRun) . "'");
$jPass = $jRun === '' ? 0 : (int) $one("SELECT COUNT(*) FROM repair01_w14_journey
                                          WHERE run_id = '" . $esc($jRun) . "' AND passed = 1");
$jNoEffect = $jRun === '' ? 0 : (int) $one("SELECT COUNT(*) FROM repair01_w14_journey
                                              WHERE run_id = '" . $esc($jRun) . "' AND business_effect = ''");
$jLegs = $jRun === '' ? 0 : (int) $one("SELECT COUNT(DISTINCT leg) FROM repair01_w14_journey
                                          WHERE run_id = '" . $esc($jRun) . "'");
$jCons = $jRun === '' ? 0 : (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w14_journey
                                          WHERE run_id = '" . $esc($jRun) . "'");
$jCo = $jRun === '' ? 0 : (int) $one("SELECT COUNT(DISTINCT company_id) FROM repair01_w14_journey
                                        WHERE run_id = '" . $esc($jRun) . "'");
gate('W14-29', 'رحلةُ الضابطِ تعبر كاملةً بأثرٍ تجاريٍّ عند كلِّ مستهلك',
     $jN > 0 && $jPass === $jN && $jNoEffect === 0 && $jLegs >= 7 && $jCons >= 12 && $jCo === 1,
     "محطّاتٌ $jN · عبرت $jPass · بلا أثرٍ تجاريٍّ $jNoEffect · أشواطٌ $jLegs"
     . " · مستهلكونَ متمايزون $jCons · كياناتٌ $jCo");

/* ══ W14-30 · ⛔ لا استعلامَ خامٌّ في سطحٍ من أسطحِ الموجة ══════════════ */
$rawQ = array();
foreach ($NEW as $s) {
    $p = $ROOT . '/' . $s['route'];
    if (!is_file($p)) { continue; }
    $src = (string) file_get_contents($p);
    if (preg_match('~\$conn\s*->\s*query\s*\(~', $src) || preg_match('~\bmysqli_query\s*\(~', $src)) {
        $rawQ[] = $s['route'];
    }
}
gate('W14-30', 'القراءةُ كلُّها عبرَ بوّابةِ العزلِ — ولا استعلامَ خامٌّ في سطحٍ جديد',
     count($rawQ) === 0 && count($NEW) > 0,
     'أسطحُ الموجةِ ' . count($NEW) . ' · باستعلامٍ خامٍّ ' . count($rawQ)
     . (count($rawQ) ? ' ⇐ ' . implode('، ', array_slice($rawQ, 0, 3)) : ''));

/* ══ W14-31 · العائلاتُ الأربعُ ولا خامسة ══════════════════════════════ */
$famRows = (int) $one("SELECT COUNT(DISTINCT family_code) FROM rsk_taxonomy");
$famRoots = (int) $one("SELECT COUNT(*) FROM rsk_taxonomy WHERE depth_no = 1 AND state = 'active'");
$famOut = (int) $one("SELECT COUNT(*) FROM rsk_taxonomy WHERE family_code NOT IN
                       ('OPERATIONAL','CAPITAL','CUSTOMER_CONTRACTUAL','PROCUREMENT_SUPPLY')");
$evOut = (int) $one("SELECT COUNT(*) FROM rsk_event WHERE family_code NOT IN
                      ('OPERATIONAL','CAPITAL','CUSTOMER_CONTRACTUAL','PROCUREMENT_SUPPLY')");
gate('W14-31', 'العائلاتُ الأربعُ معتمَدةٌ ولا خامسةَ في الشجرةِ ولا في الأحداث',
     $famRows === 4 && $famRoots === 4 && $famOut === 0 && $evOut === 0,
     "عائلاتٌ في الشجرةِ $famRows · عقدُ جذرٍ نافذةٌ $famRoots · خارجَ الأربعِ في الشجرةِ $famOut"
     . " · في الأحداثِ $evOut");

/* ══ W14-32 · لكلِّ جدولِ نطاقٍ خدمةٌ واحدةٌ تكتب فيه ═══════════════════ */
$svcDecl = array();
foreach ($DOM as $t => $d) { $svcDecl[$d[7]] = true; }
$svcMiss = array();
foreach (array_keys($svcDecl) as $f) { if (!is_file($ROOT . '/' . $f)) { $svcMiss[] = $f; } }
$domNoSvc = (int) $one("SELECT COUNT(*) FROM repair01_w14_domains WHERE service_file = ''");
$domTblMissing = 0;
foreach (array_keys($DOM) as $t) { if (!repair01_w14_table_exists($conn, $t)) { $domTblMissing++; } }
gate('W14-32', 'لكلِّ جدولِ نطاقٍ خدمةٌ واحدةٌ تكتب فيه وكلُّها قائمةٌ على القرص',
     count($svcMiss) === 0 && $domNoSvc === 0 && $domTblMissing === 0 && count($svcDecl) === 4,
     'خدماتٌ مُعلَنةٌ ' . count($svcDecl) . ' · غائبةٌ ' . count($svcMiss)
     . " · جدولٌ بلا خدمةٍ $domNoSvc · جدولٌ غائبٌ $domTblMissing");

/* ══ W14-33 · مصفوفةُ الواجهةِ وسجلُّ المساحاتِ لكلِّ سطحٍ مُصيَّر ══════ */
$MTX = $ROOT . '/docs/uxui_matrix_20260818.csv';
$mtxHit = 0;
if (is_file($MTX)) {
    $mx = strtolower((string) file_get_contents($MTX));
    foreach ($NEW as $s) { if (strpos($mx, strtolower($s['route'])) !== false) { $mtxHit++; } }
}
$spaceHit = 0;
if (repair01_w14_table_exists($conn, 'gov_space_appearances')) {
    foreach ($NEW as $s) {
        $spaceHit += (int) $one("SELECT COUNT(*) FROM gov_space_appearances
                                  WHERE route = '" . $esc($s['route']) . "' AND src_class = 'RPR-W14'") > 0 ? 1 : 0;
    }
}
gate('W14-33', 'كلُّ سطحٍ مُصيَّرٍ له صفٌّ في مصفوفةِ الواجهةِ وفي سجلِّ المساحات',
     $mtxHit === count($NEW) && $spaceHit === count($NEW),
     'في المصفوفةِ ' . $mtxHit . ' من ' . count($NEW) . ' · في سجلِّ المساحاتِ ' . $spaceHit);

/* ══ W14-34 · القراراتُ والإصلاحاتُ مسجَّلةٌ بمرجعِها ═══════════════════ */
$decN = (int) $one("SELECT COUNT(*) FROM repair01_w14_decisions");
$decBad = (int) $one("SELECT COUNT(*) FROM repair01_w14_decisions
                       WHERE question='' OR answer='' OR rationale='' OR src_ref=''");
$fixN = (int) $one("SELECT COUNT(*) FROM repair01_w14_fixes");
$fixBad = (int) $one("SELECT COUNT(*) FROM repair01_w14_fixes
                       WHERE revealed_by='' OR before_num='' OR after_num='' OR why=''");
$fixOrphan = (int) $one("SELECT COUNT(*) FROM repair01_w14_fixes f
                          LEFT JOIN repair01_requirements r ON r.requirement_id = f.revealed_by
                                                           AND r.stage_no = 14
                         WHERE r.requirement_id IS NULL");
gate('W14-34', 'كلُّ قرارٍ بسببِه وكلُّ إصلاحٍ بمتطلَّبِه الكاشف',
     $decN > 0 && $decBad === 0 && $fixN > 0 && $fixBad === 0 && $fixOrphan === 0,
     "قراراتٌ $decN · ناقصةٌ $decBad · إصلاحاتٌ $fixN · ناقصةٌ $fixBad · بلا متطلَّبٍ كاشفٍ $fixOrphan");

/* ══ W14-35 · المخرَجاتُ الحاكمةُ مكتوبةٌ على القرص ═════════════════════ */
$docs = array('W14_STATE_MACHINES.md', 'W14_SOD.md', 'W14_EVENT_CONTRACTS.md',
              'W14_JOURNEY_EVIDENCE.md', 'W14_CLOSURE.md');
$docMiss = array();
foreach ($docs as $d) {
    $p = $ROOT . '/docs/REPAIR01_20260823/plan/' . $d;
    if (!is_file($p) || filesize($p) < 512) { $docMiss[] = $d; }
}
gate('W14-35', 'المخرَجاتُ الحاكمةُ مكتوبةٌ على القرصِ لا موعودة',
     count($docMiss) === 0,
     'وثائقُ مطلوبةٌ ' . count($docs) . ' · ناقصةٌ ' . count($docMiss)
     . (count($docMiss) ? ' ⇐ ' . implode('، ', $docMiss) : ''));

/* ═══════════════════════════════════════════════════════════════════════════
   الطباعةُ والحكم
   ═══════════════════════════════════════════════════════════════════════════ */
foreach ($rows as $r) {
    printf("  %s %-8s %s\n       %s\n", $r[2] ? '✔' : '✘', $r[0], $r[1], $r[3]);
}

echo "\n────────────────────────────────────────────────────────────────────────\n";
printf("W14 gate: %d/%d  ·  حالةُ حوكمةٍ على انحرافٍ تشغيليٍّ صرفٍ %d · نسخُ حدثٍ في المخاطر %d"
     . " · تعديلُ الحوكمةِ لنتيجةِ مراجعة %d  ·  رحلةٌ %d/%d\n",
    $pass, $pass + $fail, $gc['n'], $rc['n'], $at['n'], $jPass, $jN);
echo $fail === 0 ? "الحكم: خضراء ✔\n" : "الحكم: ساقطة ✘\n";
exit($fail === 0 ? 0 : 1);
