<?php
/**
 * tools/repair01_w4_gate.php
 *   بوّابةُ المرحلةِ الرابعة — REPAIR01 · الحقيقةُ الميدانية
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تُعيد القياسَ ولا تقرأ ما خزَّنَته الأداة** (‏_CONTEXT §قواعد القياس ١):
 *   وقائعُ التوقّفِ تُقاس من `unit_time_log` و`timesheet` **حصرًا** ثمّ تُقارَن
 *   بالدفتر · تصنيفُ القيدِ يُعاد اشتقاقُه من الأعمدةِ الحيّةِ لا يُقرأ من
 *   `field_kind` · والمِرساةُ تُعاد إثباتُها من القرصِ في كلِّ تشغيل.
 *
 * ◆ **ولا حاجبَ يفحص ما يضمنه المخطَّط** (تعريفُ الحاجبِ الأعمى · W02):
 *   `chk_ue_w4_shift` يمنع «قيدًا ميدانيًّا بلا وردية» — فالحاجبُ الحيُّ هنا
 *   `W4-09`: **التصنيفُ نفسُه** يُعاد اشتقاقُه، فوسمُ قيدٍ يحمل معدةً «إسقاطًا
 *   تعاقديًّا» يمرُّ من القيدِ سليمًا ويسقط هنا.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (§٣): الحواجبُ ترسو على أسماءِ الأعمدةِ
 *   والمفاتيحِ والمفرداتِ وتُطبع برموزِها (`✘ W4-nn`).
 *
 * ◆ **و`W4-16` تُغلِّف رحلةَ اليوم**: تشغّلها وتشترط عبورَ **كلِّ** محطّاتِها —
 *   فلا تُقبل المرحلةُ ببناءِ أسطحِها إن لم تعبر رحلتُها (§٦-أ).
 *
 * التشغيل: php tools/repair01_w4_gate.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/tools/lib/repair01_w4_scan.php';
require_once $ROOT . '/tools/lib/repair01_w4_contracts.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }

$PASS = 0; $FAIL = 0; $LINES = array();
function w4_pad($s, $len)
{
    $n = mb_strlen($s, 'UTF-8');
    return $s . ($n < $len ? str_repeat(' ', $len - $n) : ' ');
}
function gate($code, $title, $ok, $detail)
{
    global $PASS, $FAIL, $LINES;
    if ($ok) { $PASS++; $mark = '✔'; } else { $FAIL++; $mark = '✘'; }
    $LINES[] = '  ' . $mark . ' ' . str_pad($code, 9) . w4_pad($title, 38) . $detail;
}
$one = function ($sql) use ($conn) { return repair01_w4_one($conn, $sql); };
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

echo "═══════════ بوّابةُ المرحلةِ الرابعة — REPAIR01 · الحقيقةُ الميدانية ═══════════\n";

/* ══ W4-01 · نطاقُ المرحلةِ كاملٌ — المقامُ من السجلِّ لا من الدفتر ═══════ */
$reqN   = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 4");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w4_scope");
$bare   = (int) $one("SELECT COUNT(*) FROM repair01_w4_scope WHERE map_rule = '' OR map_why = '' OR src_ref = ''");
$orphan = (int) $one("SELECT COUNT(*) FROM repair01_w4_scope s
    WHERE s.anchor_screen_id <> ''
      AND NOT EXISTS (SELECT 1 FROM repair01_screen_registry g WHERE g.screen_id = s.anchor_screen_id)");
gate('W4-01', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $scopeN === $reqN && $reqN > 0 && $bare === 0 && $orphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $bare · مِرساةٌ يتيمةٌ $orphan");

/* ══ W4-02 · المِرساةُ تُعاد إثباتُها من القرص — والدفترُ يُقارَن بها ═════ */
$ANCH = repair01_w4_anchors();
$mismatchAnchor = array(); $provenN = 0; $unprovenN = 0;
$r = $conn->query("SELECT requirement_id, anchor_screen_id, map_rule FROM repair01_w4_scope");
while ($r && $x = $r->fetch_assoc()) {
    $rid = $x['requirement_id'];
    if (!isset($ANCH[$rid])) { continue; }
    $p = repair01_w4_prove_anchor($conn, $ROOT, $ANCH[$rid]);
    if ($p['verdict'] === 'ANCHORED') { $provenN++; }
    elseif ($p['verdict'] !== 'NOT_BUILT') { $unprovenN++; $mismatchAnchor[] = $rid . ' (' . $p['verdict'] . ')'; }
    if ($p['sid'] !== $x['anchor_screen_id'] || $p['rule'] !== $x['map_rule']) {
        $mismatchAnchor[] = $rid . ' (الدفتر ' . ($x['anchor_screen_id'] !== '' ? $x['anchor_screen_id'] : '—')
                          . ' · المقيس ' . ($p['sid'] !== '' ? $p['sid'] : '—') . ')';
    }
}
gate('W4-02', 'المِرساةُ مُثبَتةٌ من القرصِ لا مُعلَنةٌ فقط',
     count($mismatchAnchor) === 0 && $provenN > 0,
     "مِرساةٌ مُثبَتةٌ $provenN · لم تُثبَت $unprovenN · الدفترُ يخالف المقيسَ " . count($mismatchAnchor)
     . (count($mismatchAnchor) ? ' ⇐ ' . implode('، ', array_slice($mismatchAnchor, 0, 2)) : ''));

/* ══ W4-03 · مالكُ السطحِ المخالفُ مُعلَنٌ بقرارٍ لا مدهوسٌ ولا مسكوتٌ عنه ═ */
$mismOwner = (int) $one("SELECT COUNT(*) FROM repair01_w4_scope WHERE owner_verdict = 'MISMATCH'");
$declD5    = (int) $one("SELECT scope_rows FROM repair01_w4_decisions
                          WHERE decision_id = 'W4-D-05' AND rationale IS NOT NULL AND rationale <> ''");
gate('W4-03', 'مالكُ السطحِ المخالفُ مُعلَنٌ بقرار', $mismOwner === $declD5 && $declD5 > 0,
     "سطحٌ تحت مالكٍ غيرِ مالكِ المتطلَّب $mismOwner · المُعلَنُ في W4-D-05 $declD5");

/* ══ W4-04 · الخطواتُ السبعُ لكلِّ سطحٍ في النطاق — مقامٌ مُعادُ الاشتقاق ══ */
$codes = repair01_w4_scope_codes($conn);
$codesSql = "'" . implode("','", array_map($esc, $codes)) . "'";
$scopeScreens = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                             WHERE owner_code IN ($codesSql) AND on_disk = 1 AND route IS NOT NULL");
$sbN = (int) $one("SELECT COUNT(*) FROM repair01_w4_sidebar");
$sbBare = (int) $one("SELECT COUNT(*) FROM repair01_w4_sidebar
    WHERE s1_verdict = '' OR s1_rule = '' OR s2_verdict = '' OR s2_rule = ''
       OR s3_verdict = '' OR s3_rule = '' OR s4_verdict = '' OR s4_rule = ''
       OR s5_verdict = '' OR s5_rule = '' OR s6_verdict = '' OR s6_rule = ''
       OR s7_verdict = '' OR s7_rule = ''");
gate('W4-04', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح',
     $sbN === $scopeScreens && $scopeScreens > 0 && $sbBare === 0,
     "أسطحُ النطاقِ المُعادُ اشتقاقُها $scopeScreens · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBare");

/* ══ W4-05 · الظهورُ بالصلاحيةِ لا بالإخفاء — مُعادُ القياسِ من الحيّ ════ */
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
gate('W4-05', 'كلُّ بندٍ في النطاقِ بصلاحيةٍ مُعلَنة', count($noPerm) === 0,
     'بندٌ بلا رمزِ صلاحيةٍ ' . count($noPerm)
     . (count($noPerm) ? ' ⇐ ' . implode('، ', array_slice($noPerm, 0, 3)) : ''));

/* ══ W4-06 · الربطُ بالسجلِّ المعياريِّ — مُعادُ الاشتقاقِ بالمسار ════════ */
$linkBad = 0; $linkN = 0;
$r = $conn->query("SELECT n.route, n.screen_id ns, g.screen_id gs
                     FROM nav_canonical n
                     JOIN repair01_screen_registry g ON g.route = n.route
                    WHERE g.owner_code IN ($codesSql) AND g.on_disk = 1");
while ($r && $x = $r->fetch_assoc()) { $linkN++; if ($x['ns'] !== $x['gs']) { $linkBad++; } }
gate('W4-06', 'كلُّ بندٍ مربوطٌ بـCanonical Screen_ID', $linkBad === 0 && $linkN > 0,
     "بندٌ معياريٌّ في النطاقِ $linkN · مربوطٌ خطأً أو غيرُ مربوطٍ $linkBad");

/* ══ W4-07 · ادّعاءُ الأبِ محكومٌ كلُّه — والخفضُ مُثبَتٌ ومُعذَّر ════════ */
$claims = (int) $one("SELECT COUNT(*) FROM repair01_w4_sidebar WHERE s5_verdict <> 'NO_PARENT'");
$judged = (int) $one("SELECT COUNT(*) FROM repair01_w4_sidebar
    WHERE s5_verdict IN ('DEMOTED_TO_TAB','ALREADY_TAB','TAB_CLAIM_UNPROVEN',
                         'TAB_BAR_NOT_RENDERED','DEMOTION_LOSES_ROLES')");
$wrongDemote = (int) $one("SELECT COUNT(*) FROM repair01_w4_sidebar s
    JOIN repair01_screen_registry g ON g.screen_id = s.screen_id
   WHERE s.s5_verdict IN ('TAB_CLAIM_UNPROVEN','TAB_BAR_NOT_RENDERED','DEMOTION_LOSES_ROLES')
     AND g.visibility_class = 'TAB_CHILD' AND g.visibility_rule = 'W4_TAB_PROVEN_BY_ENTITY_TABS'");
gate('W4-07', 'ادّعاءُ الأبِ محكومٌ والخفضُ مُثبَت', $claims === $judged && $claims > 0 && $wrongDemote === 0,
     "ادّعاءُ أبٍ محكومٌ $judged/$claims · خُفِض رغمَ منعِ القياسِ $wrongDemote");

/* ══ W4-08 · مفرداتُ الوردية كلُّها مُجسَّرة — وإلا لم يلتقِ السجلّان ════
   ⚠ **وهذا ليس تجميلًا**: بلا جسرِ مفرداتٍ لا ينعقد الانضمامُ بين
     `timesheet` و`unit_time_log` أصلًا، فيبدو «التوقّفُ المزدوجُ صفرًا»
     لأنَّ المقياسَ لا يعمل — لا لأنَّ الازدواجَ غيرُ قائم. */
$vocab = repair01_w4_shift_vocab($conn);
$entShift = (int) $one("SELECT COUNT(DISTINCT shift) FROM unit_entries WHERE shift IS NOT NULL");
gate('W4-08', 'مفرداتُ الورديةِ كلُّها مُجسَّرة', count($vocab['unmapped']) === 0 && count($vocab['live']) > 0,
     'مفرداتٌ حيّةٌ في التايم شيت ' . count($vocab['live']) . ' · بلا جسرٍ ' . count($vocab['unmapped'])
     . " · مفرداتُ القيدِ $entShift"
     . (count($vocab['unmapped']) ? ' ⇐ ' . implode('، ', $vocab['unmapped']) : ''));

/* ══ W4-09 · تصنيفُ القيدِ يُعاد اشتقاقُه — والمخزَّنُ يُقارَن بالمقيس ═══ */
$cls = repair01_w4_classify_entries($conn);
gate('W4-09', 'تصنيفُ القيدِ مقيسٌ لا مُدَّعًى',
     count($cls['mislabeled']) === 0 && count($cls['unruled']) === 0 && $cls['field'] > 0,
     'قيدٌ ميدانيٌّ ' . $cls['field'] . ' · إسقاطٌ تعاقديٌّ ' . $cls['projection']
     . ' · بلا قاعدةٍ ' . count($cls['unruled']) . ' · تصنيفٌ يخالف المقيسَ ' . count($cls['mislabeled'])
     . (count($cls['mislabeled']) ? ' ⇐ ' . implode('، ', array_slice($cls['mislabeled'], 0, 3)) : ''));

/* ══ W4-10 · قيدٌ يوميٌّ بلا وردية = 0 ═══════════════════════════════════ */
$noShift = repair01_w4_daily_without_shift($conn);
$projDeclared = (int) $one("SELECT scope_rows FROM repair01_w4_decisions WHERE decision_id = 'W4-D-04'");
$projLive = (int) $one("SELECT COUNT(*) FROM unit_entries WHERE field_kind = 'CONTRACT_PROJECTION'");
gate('W4-10', 'قيدٌ يوميٌّ بلا وردية صفر', $noShift === 0 && $projLive === $projDeclared,
     "قيدٌ ميدانيٌّ بلا وردية $noShift · إسقاطٌ معفًى بقاعدتِه $projLive · المُعلَنُ في W4-D-04 $projDeclared");

/* ══ W4-11 · توقّفٌ مزدوجُ التسجيل = 0 — مقيسٌ من السجلَّين الحيَّين ══════
   الواقعةُ «مزدوجةُ التسجيل» متى ادّعاها السجلّانِ **ولم تُصالَح**: صفُّ واقعةٍ
   واحدٌ يحملها، وقراءتانِ إحداهما `AUTHORITY` والأخرى `MIRROR`. */
$occ = repair01_w4_stop_occurrences($conn);
$dbl = repair01_w4_double_registered($occ);
$unrec = array();
foreach ($dbl as $k => $o) {
    $reg = (int) $one("SELECT COUNT(*) FROM ops_stop_register WHERE occurrence_key = '" . $esc($k) . "'");
    $auth = (int) $one("SELECT COUNT(*) FROM ops_stop_source WHERE occurrence_key = '" . $esc($k) . "' AND role = 'AUTHORITY'");
    $mir  = (int) $one("SELECT COUNT(*) FROM ops_stop_source WHERE occurrence_key = '" . $esc($k) . "' AND role = 'MIRROR'");
    if ($reg !== 1 || $auth !== 1 || $mir !== 1) {
        $unrec[] = $o['date'] . '/' . $o['shift'] . '/' . $o['equipment'] . " (سجل $reg · حاكم $auth · مرآة $mir)";
    }
}
gate('W4-11', 'توقّفٌ مزدوجُ التسجيلِ صفر', count($unrec) === 0,
     'وقائعُ التوقّفِ المقيسة ' . count($occ) . ' · يدّعيها سجلّانِ ' . count($dbl)
     . ' · بلا مصالحةٍ ' . count($unrec)
     . (count($unrec) ? ' ⇐ ' . implode('، ', array_slice($unrec, 0, 2)) : ''));

/* ══ W4-12 · قراءةُ الدفترِ تطابق الحيَّ — ولا واقعةَ حيّةٍ خارجَ السجلّ ══ */
$missOcc = 0; $stale = array();
foreach ($occ as $k => $o) {
    $row = $conn->query("SELECT hours, authority FROM ops_stop_register WHERE occurrence_key = '" . $esc($k) . "' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { $missOcc++; continue; }
    $liveAuth = $o['utl_hours'] > 0 ? round($o['utl_hours'], 2) : round($o['ts_hours'], 2);
    if (abs((float) $row['hours'] - $liveAuth) > 0.005) { $stale[] = $o['date'] . '/' . $o['equipment']; continue; }
    if ($o['utl_hours'] > 0 && $o['ts_hours'] > 0) {
        $mirRead = $one("SELECT hours_read FROM ops_stop_source
                          WHERE occurrence_key = '" . $esc($k) . "' AND register_name = 'timesheet'");
        if ($mirRead === '' || abs((float) $mirRead - round($o['ts_hours'], 2)) > 0.005) { $stale[] = $o['date'] . '/mirror/' . $o['equipment']; }
    }
}
$openVar   = (int) $one("SELECT COUNT(*) FROM ops_stop_source WHERE variance_rule = 'W4_MIRROR_VARIANCE_OPEN'");
$declD3    = (int) $one("SELECT scope_rows FROM repair01_w4_decisions
                          WHERE decision_id = 'W4-D-03' AND rationale IS NOT NULL AND rationale <> ''");
gate('W4-12', 'قراءةُ السجلِّ تطابق الحيَّ والفارقُ مُعلَن',
     $missOcc === 0 && count($stale) === 0 && $openVar === $declD3,
     "واقعةٌ حيّةٌ خارجَ السجلّ $missOcc · قراءةٌ متقادمة " . count($stale)
     . " · فارقٌ مفتوحٌ $openVar · المُعلَنُ في W4-D-03 $declD3"
     . (count($stale) ? ' ⇐ ' . implode('، ', array_slice($stale, 0, 2)) : ''));

/* ══ W4-13 · حدثٌ بلا عقدِ أثرٍ صفر · وعقدانِ لحدثٍ صفر · وعقدٌ ناقصٌ صفر ══ */
$ents = "'" . implode("','", array_map($esc, repair01_w4_entity_types())) . "'";
$keys = array();
$r = $conn->query("SELECT DISTINCT event_key FROM ems_business_events WHERE entity_type IN ($ents)");
while ($r && $x = $r->fetch_row()) { $keys[$x[0]] = true; }
foreach (repair01_w4_stage_events() as $ek) { $keys[$ek] = true; }
$noContract = array(); $dupContract = array(); $thin = array();
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
}
gate('W4-13', 'حدثُ النطاقِ بعقدِ أثرٍ واحدٍ كامل',
     count($noContract) === 0 && count($dupContract) === 0 && count($thin) === 0 && count($keys) > 0,
     'أحداثُ النطاق ' . count($keys) . ' · بلا عقدٍ ' . count($noContract)
     . ' · بعقدَين ' . count($dupContract) . ' · عقدٌ ناقصٌ ' . count($thin)
     . ($noContract ? ' ⇐ ' . implode('، ', array_slice($noContract, 0, 2)) : '')
     . ($dupContract ? ' ⇐ ' . implode('، ', array_slice($dupContract, 0, 2)) : '')
     . ($thin ? ' ⇐ ' . implode('، ', array_slice($thin, 0, 2)) : ''));

/* ══ W4-14 · التركيبةُ الممنوعةُ مقيسةٌ ومقفلةُ الاتّجاه ═════════════════
   ◆ العمودانِ في الصفِّ نفسِه — فالقياسُ ببيانِ الصفِّ لا بسلسلةٍ خارجيّة. */
$sodQty  = (int) $one("SELECT COUNT(*) FROM unit_entries
                        WHERE entered_by = qty_decided_by AND qty_decided_by IS NOT NULL");
$sodAppr = (int) $one("SELECT COUNT(*) FROM unit_approvals a JOIN unit_entries e ON e.id = a.entry_id
                        WHERE a.actor_id = e.entered_by AND a.actor_id IS NOT NULL");
$declD6  = (int) $one("SELECT scope_rows FROM repair01_w4_decisions
                        WHERE decision_id = 'W4-D-06' AND rationale IS NOT NULL AND rationale <> ''");
gate('W4-14', 'التركيبةُ الممنوعةُ مقفلةُ الاتّجاه', ($sodQty + $sodAppr) <= $declD6 && $declD6 > 0,
     "مُدخِلٌ يحكم على كميّتِه $sodQty · مُدخِلٌ يعتمد قيدَه $sodAppr · المُعلَنُ في W4-D-06 $declD6");

/* ══ W4-15 · مخزنُ المراحلِ السابقةِ لم يُمَسّ ══════════════════════════ */
$d0 = (int) $one("SELECT COUNT(*) FROM repair01_decisions");
$s0 = (int) $one("SELECT COUNT(*) FROM repair01_source_files");
$u0 = (int) $one("SELECT COUNT(*) FROM repair01_surfaces");
$g0 = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry");
$t0 = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = ''");
$e0 = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = ''");
$e3 = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = 'W03'");
$k0 = (int) $one("SELECT COUNT(*) FROM repair01_key_registry");
gate('W4-15', 'مخزنُ المراحلِ السابقةِ لم يُمَسّ',
     $d0 === 108 && $s0 === 13 && $u0 === 664 && $g0 === 651 && $t0 === 174 && $e0 === 632 && $e3 === 13 && $k0 === 13,
     "قرارات $d0 · مصادر $s0 · أسطح $u0 · سجلُّ الشاشات $g0 · فجواتٌ أصليّة $t0 · أحداثُ الدراسة $e0 · عقودُ W03 $e3 · مفاتيح $k0");

/* ══ W4-16 · رحلةُ اليوم — تُشغَّل هنا ويُشترط عبورُها كاملةً ════════════ */
$jOut = array(); $jCode = 1;
@exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_w4_journey.php" 2>&1', $jOut, $jCode);
$run = (string) $one("SELECT run_id FROM repair01_w4_journey ORDER BY id DESC LIMIT 1");
$jTotal = (int) $one("SELECT COUNT(*) FROM repair01_w4_journey WHERE run_id = '" . $esc($run) . "'");
$jPass  = (int) $one("SELECT COUNT(*) FROM repair01_w4_journey WHERE run_id = '" . $esc($run) . "' AND passed = 1");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w4_journey
                       WHERE run_id = '" . $esc($run) . "' AND (business_effect = '' OR business_effect = '—')");
$jCons  = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w4_journey WHERE run_id = '" . $esc($run) . "'");
gate('W4-16', 'رحلةُ اليومِ تعبر بمحطّاتِها',
     $jCode === 0 && $jTotal > 0 && $jPass === $jTotal && $jCons >= 5 && $jNoEff === 0,
     "الجولة $run · عابرٌ $jPass/$jTotal · مستهلكونَ متمايزون $jCons · بلا أثرٍ تجاريٍّ مقيسٍ $jNoEff"
     . ($jCode !== 0 ? ' · رمزُ الخروج ' . $jCode : ''));

echo implode("\n", $LINES) . "\n";
echo str_repeat('─', 100) . "\n";
printf("W4 gate: %d/%d  ·  توقّفٌ مزدوجُ التسجيل %d  ·  قيدٌ يوميٌّ بلا وردية %d  ·  وقائعُ توقّفٍ %d  ·  رحلةٌ %d/%d\n",
    $PASS, $PASS + $FAIL, count($unrec), $noShift, count($occ), $jPass, $jTotal);
echo 'الحكم: ' . ($FAIL === 0 ? "خضراء ✔\n" : "حمراء ✘\n");
exit($FAIL === 0 ? 0 : 1);
