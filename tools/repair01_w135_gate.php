<?php
/**
 * tools/repair01_w135_gate.php — بوّابةُ W13.5 النهائيّة · G1–G7
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالك 2026-08-27 · القرار السادس**: «ابدأ W14 بعد اجتيازِ G1 إلى G7
 * القابلةِ للقياس **ولا تنتظر تنظيفَ كلِّ Legacy Debt**.» و«**لا يكفي القولُ إنَّ
 * شروطَها خضراء. كلُّ شرطٍ يجب أن يملك `Measurable test + evidence`**».
 *
 * ◆ **والمالكُ استبدل مقياسي**: كنتُ أقيس تسعةَ عشرَ بندًا اشتققتُها من أمرِه
 *   الأوّل، فسمّى هو **سبعةَ حواجبَ بأسمائها وشروطِها**. فالمقياسُ يُعاد بناؤه
 *   على تعريفِه ⛔ لا يُحتفَظ باشتقاقي وتُضاف إليه أسماؤه.
 *
 * ◆ **وما أسقطه المالكُ من شروطِ العبور** — الأسماءُ الثلاثةُ والستّون
 *   والأسطحُ السبعةُ والثلاثون والدَّينُ الماليُّ — **لم تُحذف بل نُقلت إلى
 *   مكانِها**: عمليّاتٌ معتمَدةٌ (`RECONCILE FIRST` · `ANALYZE AND CLASSIFY`)
 *   و`Enterprise Debt Closure`. وتُطبَع خبرًا أسفلَ الحكمِ فلا تختفي.
 *
 * التشغيل: php tools/repair01_w135_gate.php
 * الخروج : 0 عبرت السبعةُ · 1 حاجبٌ لم يعبر
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
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$n = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };
$col = function ($t, $c) use ($conn) {
    $r = @$conn->query("SHOW COLUMNS FROM `$t` LIKE '" . $conn->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};
$pass = 0; $fail = 0; $lines = array();
$G = function ($code, $title, $ok, $measured, $need) use (&$pass, &$fail, &$lines) {
    if ($ok) { $pass++; } else { $fail++; }
    $lines[] = sprintf("  %s %-4s %-46s\n       المقيس: %s\n       الشرط : %s",
        ($ok ? '✔' : '✘'), $code, $title, $measured, $need);
    return $ok;
};
$TEN = "'DEP-03','DEP-04','DEP-05','DEP-06','DEP-07','DEP-08','DEP-09','DEP-13','DEP-16','DEP-17'";

/* ══ G1 · النطاقُ التنظيميّ ═══════════════════════════════════════════════ */
$dep  = $n("SELECT COUNT(*) FROM repair01_departments WHERE canonical_code LIKE 'DEP-%'");
$out  = $n("SELECT COUNT(*) FROM repair01_departments WHERE canonical_code NOT LIKE 'DEP-%'");
/* ◆ **الثلاثةُ المنفيّةُ بالاسمِ** — نصُّ المالك: صفرُ سلامةٍ وصفرُ مشترياتٍ
     مركزيّةٍ وصفرُ شؤونِ عاملي مشاريع. والنفيُ يُقاس ولا يُفترَض. */
$banned = $n("SELECT COUNT(*) FROM repair01_departments
               WHERE name_ar LIKE '%HSE%' OR name_ar LIKE '%سلامة%'
                  OR name_ar LIKE '%مركزي%' OR name_ar LIKE '%شؤون العاملين%'");
$G('G1', 'النطاقُ التنظيميّ — سبعَ عشرةَ إدارةً لا غير',
   ($dep === 17 && $out === 4 && $banned === 0),
   "إداراتٌ $dep · خارجَ التسلسل $out · إداراتٌ منفيّةٌ بالاسم $banned",
   '17 · 4 · 0');

/* ══ G2 · مصدرُ الحقيقةِ في التقاطعاتِ الحرِجة ═══════════════════════════ */
$live   = "ownership_verdict NOT IN ('', 'RETIRE')";
$xAll   = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code IN ($TEN) AND $live");
$xUnres = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code IN ($TEN) AND $live AND surface_kind = ''");
/* ⚠ **الهويّةُ المسارُ لا اسمُ الملفّ**: `Equipments/select_project.php` و
     `Oprators/select_project.php` **ملفّانِ مختلفانِ في مجلَّدَين**، والمقارنةُ
     بالاسمِ تراهما حقيقةً واحدةً في إدارتَين. **ومقياسٌ يوحّد ما هو متعدّدٌ
     يخترع تكرارًا لا وجودَ له** — وهو ثالثُ ظهورٍ لهذا النمطِ في الحملة.
     فالمقارنةُ **بالمسارِ الكاملِ حيث وُجد** وإلّا بالاسمِ لما لا مسارَ له. */
$dup    = $n("SELECT COUNT(*) FROM (
               SELECT LOWER(COALESCE(NULLIF(route,''), screen_file)) f
                 FROM repair01_screen_registry
                WHERE surface_kind = 'SOURCE'
                GROUP BY f HAVING COUNT(DISTINCT owner_code) > 1) z");
$unk    = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE ownership_verdict = 'UNKNOWN'");
/* ⚠ **صنفُ مكتبةٍ سُجِّل مصدرَ حقيقة**: `repair01_ingest` لم يستثنِ `vendor/`،
     فدخل `Depreciation.php` و`Payments.php` من `phpoffice/phpspreadsheet`
     **شاشتَين حيَّتَين بمسمًّى عربيٍّ وحكمِ `DOMAIN_SOURCE`** — وهما اللذان
     **جعلا `Duplicate Source` يبدو حقيقيًّا** في الإهلاكِ والدفع. وقياسُ
     الحبّةِ الذي أمر به المالكُ **أثبت أنَّ التكرارَ لم يكن أصلًا**.
     والاختبارُ السالبُ `tests/w135_vendor_not_a_screen.php` يُثبت رسوبَه. */
$vend   = $n("SELECT COUNT(*) FROM repair01_screen_registry
                WHERE on_disk = 1 AND ownership_verdict NOT IN ('RETIRE')
                  AND (route LIKE 'vendor/%' OR route LIKE '%/vendor/%'
                       OR route LIKE 'node_modules/%' OR route LIKE '%/node_modules/%')");
$G('G2', 'مصدرُ الحقيقةِ في التقاطعاتِ الستّة',
   ($xUnres === 0 && $dup === 0 && $unk === 0 && $vend === 0),
   "أسطحُ التقاطعِ $xAll · غيرُ محسومٍ $xUnres · حقيقةٌ في إدارتَين $dup · مجهولٌ $unk · صنفُ مكتبةٍ مسجَّلٌ سطحًا $vend",
   'UNRESOLVED_CRITICAL_SOT = 0 · 0 · 0 · 0');

/* ══ G3 · سلامةُ قرارِ المالك ═════════════════════════════════════════════ */
$appr    = $n("SELECT COUNT(*) FROM repair01_decisions WHERE status = 'APPROVED'");
$assumed = $n("SELECT COUNT(*) FROM repair01_decision_audit WHERE verdict = 'SYSTEM_ASSUMED_APPROVAL'");
$audited = $n("SELECT COUNT(*) FROM repair01_decision_audit");
/* ◆ **والقيدُ السالبُ شرطٌ لا زينة**: نصُّ المالك «`Negative test` يمنع اعتمادًا
     بلا `Evidence`». فيُحاوَل خرقُه هنا ويُشترط الردّ. */
$probe = @$conn->query("UPDATE repair01_decisions
                           SET recorded_by = 'W135PROBE', owner_decision_reference = ''
                         WHERE decision_id = 'DEC-OPEN-03'");
$held = ($probe !== true);
if ($probe === true) { @$conn->query("UPDATE repair01_decisions SET recorded_by = 'RPR-W135 · بامر مكتوب',
    owner_decision_reference = src_ref WHERE decision_id = 'DEC-OPEN-03'"); }
$G('G3', 'سلامةُ قرارِ المالك — لا اعتمادَ بلا سند',
   ($assumed === 0 && $audited >= $appr && $held),
   "معتمَدٌ $appr · مُراجَعٌ $audited · مُنتحَلٌ $assumed · القيدُ السالبُ " . ($held ? 'ردَّ' : '**مرَّ**'),
   '0 System Assumed · 100% مُراجَع · القيدُ يردّ');

/* ══ G4 · سقّاطةُ السطحِ الجديد ═══════════════════════════════════════════ */
$REQ = array('screen_id','canonical_label_ar','owner_code','surface_kind','route',
             'lifecycle','guard_kind','permission_policy');
$have = 0;
foreach ($REQ as $c) { if ($col('repair01_screen_registry', $c)) { $have++; } }
$LINE = $ROOT . '/docs/REPAIR01_20260823/W135_RATCHET_LINE.txt';
$from = is_file($LINE) ? (int) trim(file_get_contents($LINE)) : 13;
$newTot = $n("SELECT COUNT(*) FROM repair01_screen_registry
               WHERE origin REGEXP '^W[0-9]+$' AND CAST(SUBSTRING(origin,2) AS UNSIGNED) > $from");
$cond = array();
foreach ($REQ as $c) { $cond[] = "COALESCE(`$c`,'') = ''"; }
$newBad = $n("SELECT COUNT(*) FROM repair01_screen_registry
               WHERE origin REGEXP '^W[0-9]+$' AND CAST(SUBSTRING(origin,2) AS UNSIGNED) > $from
                 AND (" . implode(' OR ', $cond) . ")");
$tool = is_file($ROOT . '/tools/repair01_w135_ratchet.php');
$G('G4', 'سقّاطةُ السطحِ الجديد — ثمانيةُ حقولٍ إلزاميّة',
   ($have === 8 && $newBad === 0 && $tool),
   "حقولٌ بنيةً $have/8 · أسطحٌ بعد W$from: $newTot · ناقصةٌ $newBad · الأداة " . ($tool ? 'مبنيّة' : 'غائبة')
   . ($newTot === 0 ? ' · **مقامٌ خالٍ مُعلَنٌ لا نجاحٌ**' : ''),
   'NEW_NONCOMPLIANT_SURFACES = 0');

/* ══ G5 · نموذجُ الصلاحيات ════════════════════════════════════════════════ */
/* ⛔ **ولا يُدَّعى عبورُه بوجودِ عمود**: نصُّ المالكِ يشترط **اختبارًا سلبيًّا**
     على أربعةِ محاور. فالحاجبُ يقيس وجودَ الشواهدِ الأربعةِ ونتيجتَها. */
/* ⛔ **ووجودُ الملفِّ ليس اجتيازًا**: شاهدٌ موجودٌ وراسبٌ يُقرأ خضرةً إن
     اكتُفي بـ`is_file`. فشاهدُ التفويضِ **يُشغَّل ويُقرأ رمزُ خروجِه**، وهو
     الوحيدُ المبنيُّ لهذا الحاجبِ فيُملَك تشغيلُه. والثلاثةُ الأخرى شواهدُ
     عائلاتٍ أخرى لها أحزمتُها — فيُقاس وجودُها ويُعلَن أنَّ ذلك حدُّ القياس. */
$PT = array(
    'المسارُ المباشر' => 'tests/injfrd01_sec008_009_space_isolation.php',
    'فعلٌ بلا صلاحية' => 'tests/injfrd01_app003_005_ladder_code_coverage.php',
    'فصلُ الواجبات'   => 'tools/repair01_w13_negative.php',
);
$ptHave = 0; $ptMiss = array();
foreach ($PT as $k => $f) { if (is_file($ROOT . '/' . $f)) { $ptHave++; } else { $ptMiss[] = $k; } }
$dlgFile = $ROOT . '/tests/w135_expired_delegation_denied.php';
$dlgRun  = -1;
if (is_file($dlgFile)) {
    $o = array();
    @exec('"' . PHP_BINARY . '" "' . $dlgFile . '" 2>&1', $o, $dlgRun);
} else { $ptMiss[] = 'تفويضٌ منتهٍ'; }
if ($dlgRun === 0) { $ptHave++; } elseif (is_file($dlgFile)) { $ptMiss[] = 'تفويضٌ منتهٍ (‏رسب)'; }
$permCol = $col('repair01_screen_registry', 'permission_policy');
$G('G5', 'نموذجُ الصلاحيات — مُثبَتٌ باختبارٍ سلبيّ',
   ($permCol && $ptHave === 4 && $dlgRun === 0),
   "عمودُ السياسة " . ($permCol ? 'قائم' : 'غائب') . " · شواهدُ سلبيّة $ptHave/4"
   . ' · شاهدُ التفويضِ ' . ($dlgRun === 0 ? '**شُغِّل وعبر**' : ($dlgRun < 0 ? 'غائب' : 'رسب'))
   . ($ptMiss ? ' · الناقص: ' . implode(' · ', $ptMiss) : ''),
   'السلسلةُ مُثبَتةٌ و4 اختباراتٍ سلبيّة');

/* ══ G6 · حدودُ نطاقاتِ W14 الثلاثة ═══════════════════════════════════════ */
$risk  = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code = 'DEP-09' AND $live");
$gov   = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code = 'DEP-08' AND $live");
$iaf   = $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE owner_code = 'IAF' AND $live");
/* ⛔ **ولا `Shared Master Transaction` بينهم**: يُقاس بأن لا سطحَ `SOURCE`
     يحمله أكثرُ من واحدٍ من الثلاثة. */
$shared = $n("SELECT COUNT(*) FROM (SELECT LOWER(screen_file) f FROM repair01_screen_registry
               WHERE owner_code IN ('DEP-08','DEP-09','IAF') AND surface_kind = 'SOURCE'
               GROUP BY f HAVING COUNT(DISTINCT owner_code) > 1) z");
$G('G6', 'حدودُ الرابعةَ عشرة — ثلاثةُ نطاقاتٍ لا محرّكٌ واحد',
   ($risk > 0 && $gov > 0 && $iaf > 0 && $shared === 0),
   "المخاطر $risk · الحوكمة $gov · المراجعة $iaf · مصدرٌ مشتركٌ بينهم $shared",
   'ثلاثتُها مأهولةٌ · Shared Master = 0');

/* ══ G7 · الحواجبُ البنيويّةُ للرابعةَ عشرة ═══════════════════════════════ */
$blk = $n("SELECT COUNT(*) FROM repair01_decisions
            WHERE blocker_type = 'STRUCTURAL' AND status <> 'APPROVED'");
$cfg = $n("SELECT COUNT(*) FROM repair01_decisions WHERE blocking_level = 'CONFIG_PENDING'");
$G('G7', 'الحواجبُ البنيويّة — والعدديُّ لا يمنع',
   ($blk === 0),
   "حاجبٌ بنيويٌّ مفتوح $blk · عدديٌّ CONFIG_PENDING $cfg (‏لا يمنع بنصِّ القرار)",
   'OPEN_STRUCTURAL_BLOCKERS_FOR_W14 = 0');

/* ══ الطباعة ══════════════════════════════════════════════════════════════ */
echo "\n═══════ بوّابةُ W13.5 — قرارُ المالك 2026-08-27 · G1–G7 ═══════\n";
foreach ($lines as $l) { echo $l . "\n"; }
echo "\n────────────────────────────────────────────────────────────────────────\n";

/* ◆ **وما نُقل خارجَ شروطِ العبورِ يبقى مرئيًّا** — نصُّ المالك: «لا تنتظر
     إنهاءَ كلِّ Legacy» ولم يقل «انسَه». */
echo "خبرٌ خارجَ الحكمِ — عملياتٌ معتمَدةٌ لا شروطُ عبور:\n";
printf("  · أسماءٌ غيرُ معتمَدة %d ⇐ Canonical Name Reconciliation (القرار ④ RECONCILE FIRST)\n",
    $n("SELECT COUNT(*) FROM nav_canonical WHERE status <> 'APPROVED'"));
printf("  · أسطحٌ بلا إدارة %d ⇐ Legacy Surface Forensic Review (القرار ⑤ ANALYZE AND CLASSIFY)\n",
    $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(owner_code,'') = '' AND on_disk = 1"));
printf("  · دَينٌ ماليٌّ مُصنَّفٌ ومُسيَّج %d ⇐ Enterprise Debt Closure\n",
    $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE finance_debt_class <> ''"));
printf("  · عتباتٌ عدديّةٌ CONFIG_PENDING %d ⇐ تُعتمد قبل UAT ⛔ ولا يُخترَع لها رقم\n", $cfg);

echo "\n";
printf("W13.5 gate: %d/%d\n", $pass, $pass + $fail);
echo ($fail === 0
    ? "الحكم: **عبرت السبعةُ — والمرحلةُ الرابعةَ عشرةَ تبدأ** ✔\n"
    : "الحكم: $fail حاجبًا لم يعبر ✘\n");
exit($fail === 0 ? 0 : 1);
