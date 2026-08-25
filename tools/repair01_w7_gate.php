<?php
/**
 * tools/repair01_w7_gate.php
 *   بوّابةُ المرحلةِ السابعة — REPAIR01 · الصيانةُ والنقلُ ورحلةُ التوقّف
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تُعيد القياسَ ولا تقرأ ما خزَّنَته الأداة** (‏_CONTEXT §قواعد القياس ①):
 *   صلاحيةُ الشهادةِ تُعاد من تاريخِ الإنجازِ ونافذةِ السجلّ · تكلفتُها تُعاد
 *   من عمالةِ الأمرِ وقطعِه · «ضمنَ الصلاحية» يُعاد من `valid_until` · وتكلفةُ
 *   الإقفالِ تُعاد من بنودِ الأمر. ثمّ يُقارَن المخزَّنُ بالمقيس.
 *
 * ◆ **ولا حاجبَ يفحص ما يضمنه المخطَّطُ** (تعريفُ الحاجبِ الأعمى · W02):
 *   `chk_mrc_appr` يمنع اعتمادًا بلا مُعتمِدٍ داخلَ الصفّ — فالحاجبُ الحيُّ هنا
 *   ليس ذلك، بل **مطابقةُ المشتقِّ لحسابِه** و**منعُ العودةِ بلا شهادةٍ معتمَدة**
 *   و**فصلُ محورَي السلامةِ والأثرِ التشغيليِّ في الشيفرةِ نفسِها**: ثلاثتُها
 *   ممكنةُ الكذبِ في القاعدةِ وتسقط هنا.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (§٣): أسماءُ الجداولِ والأعمدةِ والقواعدِ
 *   ورموزُ الردّ — لا عباراتٌ عربيّةٌ يطابقها نصُّ حالةِ الخطأ.
 *
 * ◆ **و`W7-21` تُغلِّف رحلةَ التوقّف**: تشغّلها وتشترط عبورَ **كلِّ** محطّاتِها
 *   — فلا تُقبل المرحلةُ ببناءِ أسطحِها إن لم تعبر رحلتُها (§٦-أ).
 *
 * التشغيل: php tools/repair01_w7_gate.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/tools/lib/repair01_w7_scan.php';
require_once $ROOT . '/tools/lib/repair01_w7_contracts.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$PASS = 0; $FAIL = 0; $LINES = array();
function w7_pad($s, $len)
{
    $n = mb_strlen($s, 'UTF-8');
    return $s . ($n < $len ? str_repeat(' ', $len - $n) : ' ');
}
function gate($code, $title, $ok, $detail)
{
    global $PASS, $FAIL, $LINES;
    if ($ok) { $PASS++; $mark = '✔'; } else { $FAIL++; $mark = '✘'; }
    $LINES[] = '  ' . $mark . ' ' . str_pad($code, 9) . w7_pad($title, 44) . $detail;
}
$one = function ($sql) use ($conn) { return repair01_w7_one($conn, $sql); };
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

echo "═══════════ بوّابةُ المرحلةِ السابعة — REPAIR01 · الصيانةُ والنقل ═══════════\n";

/* ══ W7-01 · نطاقُ المرحلةِ كاملٌ — المقامُ من السجلِّ لا من الدفتر ═══════ */
$reqN   = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 7");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w7_scope");
$bare   = (int) $one("SELECT COUNT(*) FROM repair01_w7_scope WHERE map_rule = '' OR map_why = '' OR src_ref = ''");
$orphan = (int) $one("SELECT COUNT(*) FROM repair01_w7_scope s
    WHERE s.anchor_screen_id <> ''
      AND NOT EXISTS (SELECT 1 FROM repair01_screen_registry g WHERE g.screen_id = s.anchor_screen_id)");
gate('W7-01', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $scopeN === $reqN && $reqN > 0 && $bare === 0 && $orphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $bare · مِرساةٌ يتيمةٌ $orphan");

/* ══ W7-02 · المِرساةُ تُعاد إثباتُها من القرص — والدفترُ يُقارَن بها ═════ */
$ANCH = repair01_w7_anchors();
$mismatchAnchor = array(); $provenN = 0; $unprovenN = 0;
$r = $conn->query("SELECT requirement_id, anchor_screen_id, map_rule FROM repair01_w7_scope");
while ($r && $x = $r->fetch_assoc()) {
    $rid = $x['requirement_id'];
    if (!isset($ANCH[$rid])) { continue; }
    $p = repair01_w7_prove_anchor($conn, $ROOT, $ANCH[$rid]);
    if ($p['verdict'] === 'ANCHORED') { $provenN++; }
    elseif ($p['verdict'] !== 'NOT_BUILT') { $unprovenN++; $mismatchAnchor[] = $rid . ' (' . $p['verdict'] . ')'; }
    if ($p['sid'] !== $x['anchor_screen_id'] || $p['rule'] !== $x['map_rule']) {
        $mismatchAnchor[] = $rid . ' (الدفتر ' . ($x['anchor_screen_id'] !== '' ? $x['anchor_screen_id'] : '—')
                          . ' · المقيس ' . ($p['sid'] !== '' ? $p['sid'] : '—') . ')';
    }
}
gate('W7-02', 'المِرساةُ مُثبَتةٌ من القرصِ لا مُعلَنةٌ فقط',
     count($mismatchAnchor) === 0 && $provenN === $reqN,
     "مِرساةٌ مُثبَتةٌ $provenN من $reqN · لم تُثبَت $unprovenN · الدفترُ يخالف المقيسَ " . count($mismatchAnchor)
     . (count($mismatchAnchor) ? ' ⇐ ' . implode('، ', array_slice($mismatchAnchor, 0, 2)) : ''));

/* ══ W7-03 · مالكُ السطحِ المخالفُ مُعلَنٌ بقرارٍ لا مدهوسٌ ولا مسكوتٌ عنه ═ */
$mismOwner = (int) $one("SELECT COUNT(*) FROM repair01_w7_scope WHERE owner_verdict = 'MISMATCH'");
$declD3    = (int) $one("SELECT scope_rows FROM repair01_w7_decisions
                          WHERE decision_id = 'W7-D-03' AND rationale IS NOT NULL AND rationale <> ''");
gate('W7-03', 'مالكُ السطحِ المخالفُ مُعلَنٌ بقرار', $mismOwner === $declD3 && $declD3 > 0,
     "سطحٌ تحت مالكٍ غيرِ مالكِ المتطلَّب $mismOwner · المُعلَنُ في W7-D-03 $declD3");

/* ══ W7-04 · الخطواتُ السبعُ لكلِّ سطحٍ في النطاق — مقامٌ مُعادُ الاشتقاق ══ */
$codes = repair01_w7_scope_codes($conn);
$codesSql = "'" . implode("','", array_map($esc, $codes)) . "'";
$scopeScreens = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                             WHERE owner_code IN ($codesSql) AND on_disk = 1 AND route IS NOT NULL");
$sbN = (int) $one("SELECT COUNT(*) FROM repair01_w7_sidebar");
$sbBare = (int) $one("SELECT COUNT(*) FROM repair01_w7_sidebar
    WHERE s1_verdict = '' OR s1_rule = '' OR s2_verdict = '' OR s2_rule = ''
       OR s3_verdict = '' OR s3_rule = '' OR s4_verdict = '' OR s4_rule = ''
       OR s5_verdict = '' OR s5_rule = '' OR s6_verdict = '' OR s6_rule = ''
       OR s7_verdict = '' OR s7_rule = ''");
gate('W7-04', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح',
     $sbN === $scopeScreens && $scopeScreens > 0 && $sbBare === 0,
     "أسطحُ النطاقِ المُعادُ اشتقاقُها $scopeScreens · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBare");

/* ══ W7-05 · الظهورُ بالصلاحيةِ لا بالإخفاء — مُعادُ القياسِ من الحيّ ════ */
$noPerm = array();
$r = $conn->query("SELECT screen_id, route FROM repair01_screen_registry
                    WHERE owner_code IN ($codesSql) AND on_disk = 1 AND route IS NOT NULL");
while ($r && $x = $r->fetch_assoc()) {
    $navPred = repair01_w3_nav_pred($conn, $x['route']);
    $rows = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active = 1");
    if ($rows === 0) { continue; }
    $coded = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active = 1
                          AND permission_code IS NOT NULL AND permission_code <> ''");
    if ($coded < $rows) { $noPerm[] = $x['route']; }
}
gate('W7-05', 'كلُّ بندٍ في النطاقِ بصلاحيةٍ مُعلَنة', count($noPerm) === 0,
     'بندٌ بلا رمزِ صلاحيةٍ ' . count($noPerm)
     . (count($noPerm) ? ' ⇐ ' . implode('، ', array_slice($noPerm, 0, 3)) : ''));

/* ══ W7-06 · الربطُ بالسجلِّ المعياريِّ — مُعادُ الاشتقاقِ بالمسار ════════ */
$linkBad = 0; $linkN = 0;
$r = $conn->query("SELECT n.route, n.screen_id ns, g.screen_id gs
                     FROM nav_canonical n
                     JOIN repair01_screen_registry g ON g.route = n.route
                    WHERE g.owner_code IN ($codesSql) AND g.on_disk = 1");
while ($r && $x = $r->fetch_assoc()) { $linkN++; if ($x['ns'] !== $x['gs']) { $linkBad++; } }
gate('W7-06', 'كلُّ بندٍ مربوطٌ بـCanonical Screen_ID', $linkBad === 0 && $linkN > 0,
     "بندٌ معياريٌّ في النطاقِ $linkN · مربوطٌ خطأً أو غيرُ مربوطٍ $linkBad");

/* ══ W7-07 · ادّعاءُ الأبِ محكومٌ كلُّه — والخفضُ مُثبَتٌ ومُعذَّر ════════ */
$claims = (int) $one("SELECT COUNT(*) FROM repair01_w7_sidebar WHERE s5_verdict <> 'NO_PARENT'");
$judged = (int) $one("SELECT COUNT(*) FROM repair01_w7_sidebar
    WHERE s5_verdict IN ('DEMOTED_TO_TAB','ALREADY_TAB','TAB_CLAIM_UNPROVEN',
                         'TAB_BAR_NOT_RENDERED','DEMOTION_LOSES_ROLES')");
gate('W7-07', 'ادّعاءُ الأبِ محكومٌ والخفضُ مُثبَت', $claims === $judged,
     "ادّعاءُ أبٍ محكومٌ $judged/$claims");

/* ══ W7-08 · تصنيفُ السلامةِ سجلٌّ بحسبِ النوعِ — ⛔ لا قائمةَ صلبةً في الشيفرة
   ◆ `DEC-OPEN-12` ③ نصًّا: «التصنيفُ قابلٌ للتهيئةِ بحسبِ نوعِ المعدّة —
     ⛔ لا قائمةَ صلبةً واحدةً لكلِّ المعدّات». فالحاجبُ يشترط سجلًّا مأهولًا
     **ويرسو على البنية**: `classifySafety` تقرأ `mnt_safety_rule` ولا تحمل
     مصفوفةَ أنظمةٍ ولا تُقارن اسمَ نظامٍ حرفيًّا. */
$srN     = (int) $one("SELECT COUNT(*) FROM mnt_safety_rule WHERE active = 1");
$srBare  = (int) $one("SELECT COUNT(*) FROM mnt_safety_rule WHERE rule_ref = '' OR system_ar = ''");
$srCrit  = (int) $one("SELECT COUNT(*) FROM mnt_safety_rule WHERE default_severity = 'safety_critical'");
$srCos   = (int) $one("SELECT COUNT(*) FROM mnt_safety_rule
                        WHERE default_severity = 'safety_critical'
                          AND (requires_cert = 0 OR requires_lockout = 0 OR approver_kind <> 'technical_authority')");
$svcSrc  = (string) @file_get_contents($ROOT . '/app/Services/Maintenance/MaintenanceCycleService.php');
$readsReg = (mb_strpos($svcSrc, "'mnt_safety_rule'") !== false);
$hardList = 0;
foreach (array('brakes', 'steering', 'lifting', 'estop', 'structural') as $k) {
    if (preg_match("~'" . $k . "'~", $svcSrc)) { $hardList++; }
}
gate('W7-08', 'تصنيفُ السلامةِ سجلٌّ لا قائمةٌ في الشيفرة',
     $srN > 0 && $srBare === 0 && $srCrit > 0 && $srCos === 0 && $readsReg && $hardList === 0,
     "قواعدُ سلامةٍ نشِطة $srN · بلا قاعدةٍ $srBare · حرِجٌ $srCrit · حرِجٌ ناقصُ الشروط $srCos"
     . ' · الخدمةُ تقرأ السجلَّ ' . ($readsReg ? 'نعم' : 'لا')
     . " · أنظمةٌ صلبةٌ في الشيفرة $hardList");

/* ══ W7-09 · أربعةُ محاورَ تُفصل ولا تُدمَج — بنيةً وبياناتٍ معًا ═══════
   ◆ `DEC-OPEN-12` ②: «مدّةُ التوقّفِ لا تغيّر تصنيفَ السلامةِ تلقائيًّا».
     والفحصُ **على بنيةِ الدالّتَين**: `classifySafety` لا تذكر `downtime` ولا
     تعيد `ops_impact`، و`computeOpsImpact` لا تعيد `safety_severity`.
     ⛔ وهذا ما لا يمنعه مخطَّطٌ ولا `CHECK` — ويسقط هنا وحدَه. */
$fnSafety = '';
if (preg_match('~function classifySafety\(.*?\n    \}~s', $svcSrc, $m)) { $fnSafety = $m[0]; }
$fnImpact = '';
if (preg_match('~function computeOpsImpact\(.*?\n    \}~s', $svcSrc, $m)) { $fnImpact = $m[0]; }
$safetyClean = ($fnSafety !== '' && mb_strpos($fnSafety, 'downtime') === false
                && mb_strpos($fnSafety, 'ops_impact') === false);
$impactClean = ($fnImpact !== '' && mb_strpos($fnImpact, 'safety_severity') === false
                && mb_strpos($fnImpact, 'mnt_safety_rule') === false);
$axesBare = (int) $one("SELECT COUNT(*) FROM mnt_order
                         WHERE is_deleted = 0 AND safety_severity IS NOT NULL
                           AND (safety_rule_ref = '' OR ops_impact_rule = '' OR ops_impact IS NULL)");
$sameRule = (int) $one("SELECT COUNT(*) FROM mnt_order
                         WHERE is_deleted = 0 AND safety_rule_ref <> '' AND safety_rule_ref = ops_impact_rule");
gate('W7-09', 'أربعةُ محاورَ تُفصل ولا تُدمَج',
     $safetyClean && $impactClean && $axesBare === 0 && $sameRule === 0,
     'محورُ السلامةِ لا يقرأ التوقّفَ ' . ($safetyClean ? 'نعم' : 'لا')
     . ' · محورُ الأثرِ لا يعيد السلامةَ ' . ($impactClean ? 'نعم' : 'لا')
     . " · أمرٌ بمحورٍ بلا قاعدةٍ $axesBare · قاعدةٌ واحدةٌ للمحورَين $sameRule");

/* ══ W7-10 · «الشهادةُ وحدَها تعيد المعدّة» — مُعادُ القياسِ من الحيّ ════ */
$retNoCert = (int) $one("SELECT COUNT(*) FROM equipments e
                          WHERE e.w7_cert_id IS NOT NULL
                            AND NOT EXISTS (SELECT 1 FROM mnt_return_cert c
                                             WHERE c.id = e.w7_cert_id AND c.state = 'approved')");
$retNoRule = (int) $one("SELECT COUNT(*) FROM equipments
                          WHERE w7_readiness_state IS NOT NULL AND w7_readiness_rule = ''");
$closedNoCert = (int) $one("SELECT COUNT(*) FROM mnt_order o
                             WHERE o.w7_cert_id IS NOT NULL
                               AND NOT EXISTS (SELECT 1 FROM mnt_return_cert c
                                                WHERE c.id = o.w7_cert_id AND c.state = 'approved')");
$certDuty = repair01_w7_cert_duty($conn);
$dutyOpen = 0;
foreach ($certDuty as $o) { if ($o['state'] === 'closed' && $o['approved'] === 0) { $dutyOpen++; } }
/* ══ حارسُ الخلاء (RPR-W07 · تكملة) ═══════════════════════════════════════
   ◆ خمسةُ حواجبَ في هذه المرحلةِ تقيس **دورةَ شهادةِ العودة**، والمقامُ صفرٌ:
     `mnt_return_cert` خاوٍ — فالمحرّكُ **مبنيٌّ ولم يُمارَس**. و«صفرُ مخالفةٍ
     من صفرِ صفوف» ليس نجاحًا بل تطابقُ لا شيء.
   ◆ **والحكمُ خبرٌ لا خلل** — بشرطِ أن يُعلَن. فالخلاءُ يمرُّ **مُعلَنًا وحدَه**
     في `W7-D-08`، ويسقط صامتًا لو حُذف الإعلان. وهو النمطُ نفسُه الذي
     أعلنته W04 (‏`objection_state` ٦٥٨ صفًّا كلُّها `none`) وW05
     (‏`unit_approvals` ٩٧٦ قرارًا بصفرِ رفض) ورُفع إلى القبولِ البشريِّ في W16. */
$emptyDeclared = (int) $one("SELECT COUNT(*) FROM repair01_w7_decisions
                              WHERE decision_id = 'W7-D-08' AND COALESCE(rationale, '') <> ''");
$vac = function ($n) use ($emptyDeclared) { return ((int) $n === 0 && $emptyDeclared === 0); };

gate('W7-10', 'لا عودةَ للخدمةِ بلا شهادةٍ معتمَدة — ولا خلاءَ صامت',
     $retNoCert === 0 && $retNoRule === 0 && $closedNoCert === 0 && $dutyOpen === 0
     && !$vac(count($certDuty)),
     "أصلٌ عائدٌ بشهادةٍ غيرِ معتمدة $retNoCert · حالةٌ بلا قاعدةٍ $retNoRule"
     . " · أمرٌ مقفلٌ بشهادةٍ غيرِ معتمدة $closedNoCert · أمرٌ مقفلٌ بلا شهادةٍ يوجبها تصنيفُه $dutyOpen"
     . ' · أوامرُ تُوجب الشهادةَ ' . count($certDuty)
     . (count($certDuty) === 0 ? ($emptyDeclared ? ' · الخلاءُ مُعلَنٌ في W7-D-08 ✔' : ' · **خلاءٌ غيرُ مُعلَن**') : ''));

/* ══ W7-11 · صلاحيةُ الشهادةِ مُعادةُ الحسابِ من العتبةِ لا مقروءةٌ من العمود ═ */
$val = repair01_w7_cert_validity($conn);
$valStale = array(); $valNoTh = 0;
foreach ($val as $id => $v) {
    if ($v['measured_days'] === null) { $valNoTh++; continue; }
    if ($v['stored'] !== $v['measured'] || (int) $v['stored_days'] !== (int) $v['measured_days']) {
        $valStale[] = '#' . $id . ' (المخزَّن ' . $v['stored'] . ' · المقيس ' . $v['measured'] . ')';
    }
}
gate('W7-11', 'صلاحيةُ الشهادةِ تطابق حسابَها — ولا خلاءَ صامت',
     count($valStale) === 0 && $valNoTh === 0 && !$vac(count($val)),
     (count($val) === 0 ? ($emptyDeclared ? 'خلاءٌ مُعلَنٌ في W7-D-08 ✔ · ' : '**خلاءٌ غيرُ مُعلَن** · ') : '')
     . 'شهاداتٌ معتمَدةٌ مقيسة ' . count($val) . ' · بلا عتبةٍ في السجلِّ ' . $valNoTh
     . ' · تخالف حسابَها ' . count($valStale)
     . (count($valStale) ? ' ⇐ ' . implode('، ', array_slice($valStale, 0, 2)) : ''));

/* ══ W7-12 · تكلفةُ الشهادةِ مشتقّةٌ من الأمرِ لا مُدخَلة ════════════════ */
$cost = repair01_w7_cert_cost($conn);
$costStale = array();
foreach ($cost as $id => $c) {
    if (abs($c['stored'] - $c['measured']) > 0.005) {
        $costStale[] = '#' . $id . ' (المخزَّن ' . $c['stored'] . ' · المقيس ' . $c['measured'] . ')';
    }
}
$costNoRule = (int) $one("SELECT COUNT(*) FROM mnt_return_cert WHERE cost_rule = ''");
gate('W7-12', 'تكلفةُ الشهادةِ مشتقّةٌ ومطابقةٌ لحسابِها — ولا خلاءَ صامت',
     count($costStale) === 0 && $costNoRule === 0 && !$vac(count($cost)),
     (count($cost) === 0 ? ($emptyDeclared ? 'خلاءٌ مُعلَنٌ في W7-D-08 ✔ · ' : '**خلاءٌ غيرُ مُعلَن** · ') : '')
     . 'شهاداتٌ مقيسة ' . count($cost) . " · بلا قاعدةِ تكلفةٍ $costNoRule · تخالف حسابَها " . count($costStale)
     . (count($costStale) ? ' ⇐ ' . implode('، ', array_slice($costStale, 0, 2)) : ''));

/* ══ W7-13 · «ضمنَ الصلاحية» مُعادُ الاشتقاقِ من الشهادةِ لا مقروءٌ من العمود ═ */
$rep = repair01_w7_repeat_measure($conn);
$repStale = array();
foreach ($rep as $id => $v) {
    if ($v['stored'] !== $v['measured']
        || ($v['days_measured'] !== null && (int) $v['days_stored'] !== (int) $v['days_measured'])) {
        $repStale[] = '#' . $id . ' (المخزَّن ' . $v['stored'] . ' · المقيس ' . $v['measured'] . ')';
    }
}
$repNoRule = (int) $one("SELECT COUNT(*) FROM mnt_repeat_repair WHERE derivation_rule = ''");
gate('W7-13', 'تكرارُ الإصلاحِ يطابق صلاحيةَ شهادتِه — ولا خلاءَ صامت',
     count($repStale) === 0 && $repNoRule === 0 && !$vac(count($rep)),
     (count($rep) === 0 ? ($emptyDeclared ? 'خلاءٌ مُعلَنٌ في W7-D-08 ✔ · ' : '**خلاءٌ غيرُ مُعلَن** · ') : '')
     . 'وقائعُ تكرارٍ مقيسة ' . count($rep) . " · بلا قاعدةٍ $repNoRule · تخالف حسابَها " . count($repStale)
     . (count($repStale) ? ' ⇐ ' . implode('، ', array_slice($repStale, 0, 2)) : ''));

/* ══ W7-14 · تكلفةُ الإقفالِ مشتقّةٌ من البنودِ — والحبّةُ مفصولة ═══════ */
$clo = repair01_w7_closure_cost($conn);
$cloStale = array();
foreach ($clo as $id => $c) {
    if (abs($c['stored'] - $c['measured']) > 0.005 || $c['lines_stored'] !== $c['lines_measured']) {
        $cloStale[] = '#' . $id . ' (المخزَّن ' . $c['stored'] . ' · المقيس ' . $c['measured'] . ')';
    }
}
$cloNoRule = (int) $one("SELECT COUNT(*) FROM trp_closure WHERE derivation_rule = ''");
$cloNoDoc  = (int) $one("SELECT COUNT(*) FROM trp_closure
                          WHERE state IN ('submitted','approved') AND delivery_doc_id IS NULL");
gate('W7-14', 'إقفالُ الترحيلِ مشتقٌّ ومسنَدٌ بمحضرِه — ولا خلاءَ صامت',
     count($cloStale) === 0 && $cloNoRule === 0 && $cloNoDoc === 0 && !$vac(count($clo)),
     (count($clo) === 0 ? ($emptyDeclared ? 'خلاءٌ مُعلَنٌ في W7-D-08 ✔ · ' : '**خلاءٌ غيرُ مُعلَن** · ') : '')
     . 'إقفالاتٌ مقيسة ' . count($clo) . " · بلا قاعدةٍ $cloNoRule · بلا محضرٍ $cloNoDoc"
     . ' · تخالف حسابَها ' . count($cloStale)
     . (count($cloStale) ? ' ⇐ ' . implode('، ', array_slice($cloStale, 0, 2)) : ''));

/* ══ W7-15 · العتبةُ من السجلِّ لا من الشيفرة (‏§٥) ══════════════════════ */
$thN    = (int) $one("SELECT COUNT(*) FROM repair01_w7_thresholds");
$thBare = (int) $one("SELECT COUNT(*) FROM repair01_w7_thresholds WHERE why = '' OR decision_ref = '' OR src_ref = ''");
$hard = 0; $hardWhere = '';
foreach (array('/app/Services/Maintenance/MaintenanceCycleService.php',
               '/app/Services/Transport/TransferCycleService.php',
               '/tools/lib/repair01_w7_scan.php', '/tools/repair01_w7_gate.php') as $rel) {
    $s = (string) @file_get_contents($ROOT . $rel);
    /* مقارنةُ ساعاتٍ أو أيّامٍ برقمٍ صلبٍ أكبرَ من واحد — لا `> 0` ولا `=== 1` */
    if (preg_match('~\$(?:downtime|hours|days|valid|grace)\w*\s*[<>]=?\s*(?:[2-9]|\d{2,})~i', $s)) {
        $hard++; $hardWhere = $rel;
    }
}
$thUsed = 0;
foreach (repair01_w7_thresholds($conn) as $k => $v) {
    foreach (array('/app/Services/Maintenance/MaintenanceCycleService.php',
                   '/app/Services/Transport/TransferCycleService.php') as $rel) {
        if (mb_strpos((string) @file_get_contents($ROOT . $rel), $k) !== false) { $thUsed++; break; }
    }
}
gate('W7-15', 'حدُّ العتبةِ من السجلِّ لا من الشيفرة',
     $thN >= 8 && $thBare === 0 && $hard === 0 && $thUsed >= 4,
     "عتباتٌ مسجَّلةٌ $thN · بلا عذرٍ أو مرجعٍ $thBare · مقارنةٌ صلبةٌ في أداةٍ $hard"
     . ($hardWhere !== '' ? " ⇐ $hardWhere" : '') . " · عتبةٌ تُقرأ في خدمةٍ $thUsed");

/* ══ W7-16 · آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ صريحٍ وسببٍ مكتوب ═══════════ */
$stEnt   = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w7_states");
$stAllow = (int) $one("SELECT COUNT(*) FROM repair01_w7_states WHERE allowed = 1");
$stForb  = (int) $one("SELECT COUNT(*) FROM repair01_w7_states WHERE allowed = 0");
$stThin  = (int) $one("SELECT COUNT(*) FROM repair01_w7_states WHERE allowed = 1
                        AND (owner_role = '' OR `precondition` = '' OR official_doc = ''
                             OR approval_gate = '' OR reopen_rule = '' OR correct_rule = '')");
$stNoWhy = (int) $one("SELECT COUNT(*) FROM repair01_w7_states WHERE allowed = 0 AND forbid_reason = ''");
$stEntNoForb = (int) $one("SELECT COUNT(*) FROM (
    SELECT entity FROM repair01_w7_states GROUP BY entity
     HAVING SUM(CASE WHEN allowed = 0 THEN 1 ELSE 0 END) = 0) z");
gate('W7-16', 'آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ صريح',
     $stEnt >= 8 && $stThin === 0 && $stNoWhy === 0 && $stEntNoForb === 0 && $stForb > 0,
     "كياناتٌ $stEnt · مسموحٌ $stAllow · ممنوعٌ صراحةً $stForb · مسموحٌ ناقصُ الحقول $stThin"
     . " · ممنوعٌ بلا سبب $stNoWhy · كيانٌ بلا ممنوعٍ $stEntNoForb");

/* ══ W7-17 · فصلُ الواجباتِ بستّةِ أدوارٍ — ورمزُ ردٍّ ينفّذه فعلًا ═══════
   ◆ **ولا إعلانَ بلا تنفيذ**: كلُّ رمزِ ردٍّ في `enforced_by` **يجب أن يوجد
     حرفيًّا في إحدى خدمتَي النطاق** — وإلّا كانت المصفوفةُ سردًا لا ضابطًا. */
$sodN = (int) $one("SELECT COUNT(*) FROM repair01_w7_sod");
$sodThin = (int) $one("SELECT COUNT(*) FROM repair01_w7_sod
    WHERE initiator_role = '' OR reviewer_role = '' OR approver_role = ''
       OR executor_role = '' OR closer_role = '' OR forbidden_combo = ''
       OR authority_rule_id = '' OR deputy_role = '' OR scope_rule = '' OR delegation = ''
       OR effective_date IS NULL");
$sodSame = (int) $one("SELECT COUNT(*) FROM repair01_w7_sod
                        WHERE initiator_role = approver_role OR executor_role = approver_role");
$mntSrc = $svcSrc;
$trpSrc = (string) @file_get_contents($ROOT . '/app/Services/Transport/TransferCycleService.php');
$sodUnenforced = array();
$r = $conn->query("SELECT process_key, enforced_by FROM repair01_w7_sod");
while ($r && $x = $r->fetch_assoc()) {
    foreach (preg_split('~\s*·\s*~u', (string) $x['enforced_by']) as $code) {
        $code = trim($code);
        if ($code === '') { continue; }
        if (mb_strpos($mntSrc, $code) === false && mb_strpos($trpSrc, $code) === false) {
            $sodUnenforced[] = $x['process_key'] . ':' . $code;
        }
    }
}
gate('W7-17', 'فصلُ الواجباتِ منفَّذٌ برمزِ ردٍّ لا مُعلَنٌ فقط',
     $sodN >= 6 && $sodThin === 0 && $sodSame === 0 && count($sodUnenforced) === 0,
     "عملياتٌ $sodN · صفٌّ ناقصٌ $sodThin · دورٌ يجمع اعتمادَه أو تنفيذَه $sodSame"
     . ' · رمزُ ردٍّ بلا تنفيذٍ ' . count($sodUnenforced)
     . (count($sodUnenforced) ? ' ⇐ ' . implode('، ', array_slice($sodUnenforced, 0, 2)) : ''));

/* ══ W7-18 · حدثٌ بلا عقدِ أثرٍ صفر · وعقدانِ لحدثٍ صفر · وعقدٌ ناقصٌ صفر ══ */
$ents = "'" . implode("','", array_map($esc, repair01_w7_entity_types())) . "'";
$keys = array();
$r = $conn->query("SELECT DISTINCT event_key FROM ems_business_events WHERE entity_type IN ($ents)");
while ($r && $x = $r->fetch_row()) { $keys[$x[0]] = true; }
foreach (repair01_w7_stage_events() as $ek) { $keys[$ek] = true; }
$noContract = array(); $dupContract = array(); $thin = array(); $generic = array();
foreach (array_keys($keys) as $ek) {
    $n = (int) $one("SELECT COUNT(*) FROM repair01_events
                      WHERE event_code = '" . $esc($ek) . "' AND contract_status = 'RECORDED'");
    if ($n === 0) { $noContract[] = $ek; continue; }
    if ($n > 1)  { $dupContract[] = $ek . " ($n)"; continue; }
    $bad = (int) $one("SELECT COUNT(*) FROM repair01_events
        WHERE event_code = '" . $esc($ek) . "' AND contract_status = 'RECORDED'
          AND (trigger_rule = '' OR min_payload = '' OR consumer_list IS NULL OR consumer_list = ''
               OR consumer_effect IS NULL OR consumer_effect = '' OR preconditions = ''
               OR failure_policy = '' OR compensation = '' OR idempotency_key = '' OR src_ref = '')");
    if ($bad > 0) { $thin[] = $ek; }
    $gen = (int) $one("SELECT COUNT(*) FROM repair01_events
        WHERE event_code = '" . $esc($ek) . "' AND contract_status = 'RECORDED'
          AND consumer_list LIKE '%كل المستهلكين%'");
    if ($gen > 0) { $generic[] = $ek; }
}
gate('W7-18', 'حدثُ النطاقِ بعقدِ أثرٍ واحدٍ كامل',
     count($noContract) === 0 && count($dupContract) === 0 && count($thin) === 0
     && count($generic) === 0 && count($keys) > 0,
     'أحداثُ النطاق ' . count($keys) . ' · بلا عقدٍ ' . count($noContract)
     . ' · بعقدَين ' . count($dupContract) . ' · عقدٌ ناقصٌ ' . count($thin)
     . ' · «كلُّ المستهلكين» ' . count($generic)
     . ($noContract ? ' ⇐ ' . implode('، ', array_slice($noContract, 0, 2)) : '')
     . ($thin ? ' ⇐ ' . implode('، ', array_slice($thin, 0, 2)) : ''));

/* ══ W7-19 · أساسُ المراحلِ السابقةِ لم يُمَسَّ والنموُّ مختوم ═══════════ */
$d0 = (int) $one("SELECT COUNT(*) FROM repair01_decisions");
$s0 = (int) $one("SELECT COUNT(*) FROM repair01_source_files");
$u0 = (int) $one("SELECT COUNT(*) FROM repair01_surfaces");
$BASE_ORIGINS = "'SURFACES','DISK','NAV'";
$g0    = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin IN ($BASE_ORIGINS)");
$gNew  = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin NOT IN ($BASE_ORIGINS)");
$gWild = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                      WHERE origin NOT IN ($BASE_ORIGINS) AND origin NOT REGEXP '^W[0-9]{2}$'");
$t0 = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = ''");
$e0 = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = ''");
$e3 = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = 'W03'");
$e4 = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = 'W04'");
$e5 = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = 'W05'");
$e7 = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = 'W07'");
$k0 = (int) $one("SELECT COUNT(*) FROM repair01_key_registry");
gate('W7-19', 'أساسُ المراحلِ السابقةِ لم يُمَسّ',
     $d0 === 108 && $s0 === 13 && $u0 === 664 && $g0 === 651 && $gWild === 0
     && $t0 === 174 && $e0 === 632 && $e3 === 13 && $e4 === 3 && $e5 === 9 && $k0 === 13 && $e7 > 0,
     "قرارات $d0 · مصادر $s0 · أسطح $u0 · أساسُ السجلّ $g0 · نموٌّ مختومٌ $gNew · بلا ختمٍ $gWild"
     . " · فجواتٌ $t0 · أحداثُ الدراسة $e0 · عقود W03 $e3 · W04 $e4 · W05 $e5 · W07 $e7 · مفاتيح $k0");

/* ══ W7-20 · أسطحُ النموِّ مبنيّةٌ ومحروسةٌ وموصولةٌ ومختومة ═════════════ */
$grow = array(); $growBad = array();
$r = $conn->query("SELECT screen_id, route, guard_kind, visibility_class FROM repair01_screen_registry
                    WHERE origin = 'W07'");
while ($r && $x = $r->fetch_assoc()) {
    $grow[] = $x['route'];
    if (!is_file($ROOT . '/' . $x['route'])) { $growBad[] = $x['route'] . ' (لا ملفَّ)'; continue; }
    $g = repair01_w7_guard_of($ROOT, $x['route']);
    if ($g['kind'] === 'NONE' || $x['guard_kind'] !== $g['kind']) { $growBad[] = $x['route'] . ' (حارس ' . $g['kind'] . ')'; continue; }
    $mod = (int) $one("SELECT COUNT(*) FROM modules WHERE code = '" . $esc($x['route']) . "'");
    $nav = (int) $one("SELECT COUNT(*) FROM nav_items WHERE route = '" . $esc($x['route']) . "' AND active = 1");
    $can = (int) $one("SELECT COUNT(*) FROM nav_canonical WHERE route = '" . $esc($x['route']) . "'
                        AND screen_id = '" . $esc($x['screen_id']) . "'");
    $prm = (int) $one("SELECT COUNT(*) FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                        WHERE m.code = '" . $esc($x['route']) . "' AND rp.can_view = 1");
    if ($mod !== 1 || $nav === 0 || $can !== 1 || $prm === 0) {
        $growBad[] = $x['route'] . " (موديول $mod · بند $nav · معياريّ $can · منح $prm)";
    }
}
gate('W7-20', 'أسطحُ النموِّ مبنيّةٌ ومحروسةٌ وموصولة',
     count($grow) === 11 && count($growBad) === 0,
     'أسطحُ النموِّ المختومةُ W07 ' . count($grow) . ' · ناقصةٌ ' . count($growBad)
     . (count($growBad) ? ' ⇐ ' . implode('، ', array_slice($growBad, 0, 2)) : ''));

/* ══ W7-21 · رحلةُ التوقّف §18 — تُشغَّل هنا ويُشترط عبورُها كاملةً ══════ */
$jOut = array(); $jCode = 1;
@exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_w7_journey.php" 2>&1', $jOut, $jCode);
/* ⚠ **مُعرِّفُ الجولةِ يُقرأ من مخرَجِ الرحلةِ لا من «آخرِ صفٍّ في الجدول»**:
     رحلةٌ لم تنعقد تترك دليلَ الجولةِ السابقةِ قائمًا، فقراءةُ الآخِرِ تُخضِرُّ
     على عبورٍ لم يقع. ولا مُعرِّفَ في المخرَجِ ⇒ لا رحلةَ ⇒ الحاجبُ يسقط. */
$run = '';
foreach ($jOut as $l) { if (preg_match('/^RUN=(W7J-\d+)$/', trim($l), $m)) { $run = $m[1]; break; } }
$jTotal = (int) $one("SELECT COUNT(*) FROM repair01_w7_journey WHERE run_id = '" . $esc($run) . "'");
$jPass  = (int) $one("SELECT COUNT(*) FROM repair01_w7_journey WHERE run_id = '" . $esc($run) . "' AND passed = 1");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w7_journey
                       WHERE run_id = '" . $esc($run) . "' AND (business_effect = '' OR business_effect = '—')");
$jCons  = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w7_journey WHERE run_id = '" . $esc($run) . "'");
$jRead  = (int) $one("SELECT COUNT(DISTINCT readiness_after) FROM repair01_w7_journey WHERE run_id = '" . $esc($run) . "'");
gate('W7-21', 'رحلةُ التوقّفِ تعبر بمحطّاتِها',
     $jCode === 0 && $run !== '' && $jTotal >= 20 && $jPass === $jTotal
     && $jCons >= 10 && $jNoEff === 0 && $jRead >= 5,
     'الجولة ' . ($run !== '' ? $run : '— لم تُعلَن —')
     . " · عابرٌ $jPass/$jTotal · مستهلكونَ متمايزون $jCons · حالاتُ جاهزيّةٍ متمايزة $jRead"
     . " · بلا أثرٍ تجاريٍّ مقيسٍ $jNoEff" . ($jCode !== 0 ? ' · رمزُ الخروج ' . $jCode : ''));

/* ══ W7-22 · المؤشّراتُ مشتقّةٌ بقاعدتِها ولا تُدخَل ════════════════════ */
$kpiN     = (int) $one("SELECT COUNT(*) FROM mnt_kpi_period");
$kpiBare  = (int) $one("SELECT COUNT(*) FROM mnt_kpi_period WHERE derivation_rule = '' OR derived_from = ''");
$trpN     = (int) $one("SELECT COUNT(*) FROM trp_kpi_period");
$trpBare  = (int) $one("SELECT COUNT(*) FROM trp_kpi_period WHERE derivation_rule = '' OR derived_from = ''");
$kpiOrph  = (int) $one("SELECT COUNT(*) FROM mnt_kpi_period k WHERE k.scope_kind = 'equipment'
                         AND NOT EXISTS (SELECT 1 FROM equipments e WHERE e.id = k.scope_ref)");
/* ⚠ **والمقامُ يُعاد اشتقاقُه من الحيِّ لا يُقرأ من الجدولِ المشتقّ**: عدُّ
     الصفوفِ وحدَه يُخضِرُّ على جدولٍ ناقصٍ — سطرٌ يُنزَع فلا يشعر به أحد.
     فالحاجبُ يعيد بناءَ الحبّةِ (كيان × فترة × أصل) من `mnt_order` المضمومِ
     إلى `equipments`، ويشترط **صفرَ ناقصٍ وصفرَ زائد**. */
$kpiWant = array();
$r = $conn->query("SELECT o.company_id c, DATE_FORMAT(COALESCE(o.work_end, o.created_at), '%Y-%m') p,
                          o.equipment_id e
                     FROM mnt_order o JOIN equipments q ON q.id = o.equipment_id
                    WHERE o.is_deleted = 0 AND o.equipment_id > 0
                    GROUP BY o.company_id, p, o.equipment_id");
while ($r && $x = $r->fetch_assoc()) { $kpiWant[$x['c'] . '|' . $x['p'] . '|' . $x['e']] = true; }
$kpiHave = array();
$r = $conn->query("SELECT company_id c, period p, scope_ref e FROM mnt_kpi_period WHERE scope_kind = 'equipment'");
while ($r && $x = $r->fetch_assoc()) { $kpiHave[$x['c'] . '|' . $x['p'] . '|' . $x['e']] = true; }
$kpiMissing = count(array_diff_key($kpiWant, $kpiHave));
$kpiExtra   = count(array_diff_key($kpiHave, $kpiWant));

$trpWant = array();
$r = $conn->query("SELECT o.company_id c,
                          DATE_FORMAT(COALESCE(o.arrival_datetime, o.planned_date, o.request_date), '%Y-%m') p
                     FROM transfer_orders o WHERE o.is_deleted = 0
                    GROUP BY o.company_id, p HAVING p IS NOT NULL");
while ($r && $x = $r->fetch_assoc()) { $trpWant[$x['c'] . '|' . $x['p']] = true; }
$trpHave = array();
$r = $conn->query("SELECT company_id c, period p FROM trp_kpi_period");
while ($r && $x = $r->fetch_assoc()) { $trpHave[$x['c'] . '|' . $x['p']] = true; }
$trpMissing = count(array_diff_key($trpWant, $trpHave));
$trpExtra   = count(array_diff_key($trpHave, $trpWant));
/* ⛔ **والمُقصى مُعلَنٌ بعددِه لا مسكوتٌ عنه** (‏«لا سقفَ صامتًا»): أوامرُ الصيانةِ
     على أصولٍ معدومةٍ تُقصى من الاشتقاق، والحاجبُ يشترط أن يساوي عددُها المقيسُ
     ما أُعلن في `W7-D-07` — فالإقصاءُ الصامتُ يُقرأ تغطيةً كاملةً وليس كذلك. */
$orphOrders = (int) $one("SELECT COUNT(*) FROM mnt_order o LEFT JOIN equipments e ON e.id = o.equipment_id
                           WHERE o.is_deleted = 0 AND o.equipment_id > 0 AND e.id IS NULL");
$declD7 = (int) $one("SELECT scope_rows FROM repair01_w7_decisions
                       WHERE decision_id = 'W7-D-07' AND rationale IS NOT NULL AND rationale <> ''");
gate('W7-22', 'المؤشّراتُ مشتقّةٌ ومقامُها مُعادُ البناء',
     $kpiN > 0 && $kpiBare === 0 && $trpN > 0 && $trpBare === 0 && $kpiOrph === 0
     && $orphOrders === $declD7 && $kpiMissing === 0 && $kpiExtra === 0
     && $trpMissing === 0 && $trpExtra === 0,
     "أسطرُ الصيانة $kpiN (ناقصٌ $kpiMissing · زائدٌ $kpiExtra) · أسطرُ الترحيل $trpN"
     . " (ناقصٌ $trpMissing · زائدٌ $trpExtra) · بلا قاعدةٍ " . ($kpiBare + $trpBare)
     . " · سطرٌ لأصلٍ غيرِ موجود $kpiOrph · أمرٌ على أصلٍ معدومٍ $orphOrders · المُعلَنُ في W7-D-07 $declD7");

echo implode("\n", $LINES) . "\n";
echo str_repeat('─', 118) . "\n";
printf("W7 gate: %d/%d  ·  عودةٌ بلا شهادةٍ معتمدة %d  ·  محاورُ مدموجة %d  ·  عتبةٌ صلبة %d  ·  رحلةٌ %d/%d\n",
    $PASS, $PASS + $FAIL, $retNoCert + $closedNoCert,
    ($safetyClean && $impactClean ? 0 : 1) + $sameRule, $hard, $jPass, $jTotal);
echo 'الحكم: ' . ($FAIL === 0 ? "خضراء ✔\n" : "حمراء ✘\n");
exit($FAIL === 0 ? 0 : 1);
