<?php
/**
 * tools/repair01_w5_gate.php
 *   بوّابةُ المرحلةِ الخامسة — REPAIR01 · أثرُ الأصلِ والقوى
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تُعيد القياسَ ولا تقرأ ما خزَّنَته الأداة** (‏_CONTEXT §قواعد القياس ١):
 *   جسرُ التشغيلةِ يُعاد بناؤه من `operations` · نوافذُ التزامنِ تُعاد من
 *   `asset_ownership_shares` · الجاهزيّةُ تُعاد من التايم شيتِ وسجلِّ التوقُّفِ
 *   بصيغةِ الخدمةِ نفسِها · التغطيةُ تُعاد من `workforce_requirement` · وحالةُ
 *   الأصلِ تُعاد من وقائعِها. ثمَّ يُقارَن المخزَّنُ بالمقيس.
 *
 * ◆ **ولا حاجبَ يفحص ما يضمنه المخطَّط** (تعريفُ الحاجبِ الأعمى · W02):
 *   `chk_aex_perm` يمنع خروجًا دائمًا بلا مرجعٍ ماليّ — فالحاجبُ الحيُّ هنا
 *   ليس ذلك، بل **مطابقةُ المشتقِّ لحسابِه** و**وسمُ التزامنِ لنافذتِه**:
 *   كلاهما ممكنُ الكذبِ في القاعدةِ ويسقط هنا.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (§٣): الحواجبُ ترسو على أسماءِ الأعمدةِ
 *   والقواعدِ والمفاتيحِ وتُطبع برموزِها (`✘ W5-nn`).
 *
 * ◆ **و`W5-16` تُغلِّف رحلةَ الأصل**: تشغّلها وتشترط عبورَ **كلِّ** محطّاتِها —
 *   فلا تُقبل المرحلةُ ببناءِ أسطحِها إن لم تعبر رحلتُها (§٦-أ).
 *
 * التشغيل: php tools/repair01_w5_gate.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/app/Services/Fleet/AssetLifecycleService.php';
require_once $ROOT . '/tools/lib/repair01_w5_scan.php';
require_once $ROOT . '/tools/lib/repair01_w5_contracts.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
/* مرساةُ الطورِ صفرِ — **حقيقةٌ مسجَّلةٌ لا ثابتٌ حرفيّ** (RPR-AMD01) */
require_once __DIR__ . '/lib/repair01_w00_anchor.php';
$W00 = w00_anchors($conn);

while (ob_get_level()) { ob_end_clean(); }
use App\Services\Fleet\AssetLifecycleService as ALS;

$PASS = 0; $FAIL = 0; $LINES = array();
function w5_pad($s, $len)
{
    $n = mb_strlen($s, 'UTF-8');
    return $s . ($n < $len ? str_repeat(' ', $len - $n) : ' ');
}
function gate($code, $title, $ok, $detail)
{
    global $PASS, $FAIL, $LINES;
    if ($ok) { $PASS++; $mark = '✔'; } else { $FAIL++; $mark = '✘'; }
    $LINES[] = '  ' . $mark . ' ' . str_pad($code, 9) . w5_pad($title, 40) . $detail;
}
$one = function ($sql) use ($conn) { return repair01_w5_one($conn, $sql); };
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

echo "═══════════ بوّابةُ المرحلةِ الخامسة — REPAIR01 · أثرُ الأصلِ والقوى ═══════════\n";

/* ══ W5-01 · نطاقُ المرحلةِ كاملٌ — المقامُ من السجلِّ لا من الدفتر ═══════ */
$reqN   = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 5");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w5_scope");
$bare   = (int) $one("SELECT COUNT(*) FROM repair01_w5_scope WHERE map_rule = '' OR map_why = '' OR src_ref = ''");
$orphan = (int) $one("SELECT COUNT(*) FROM repair01_w5_scope s
    WHERE s.anchor_screen_id <> ''
      AND NOT EXISTS (SELECT 1 FROM repair01_screen_registry g WHERE g.screen_id = s.anchor_screen_id)");
gate('W5-01', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $scopeN === $reqN && $reqN > 0 && $bare === 0 && $orphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $bare · مِرساةٌ يتيمةٌ $orphan");

/* ══ W5-02 · المِرساةُ تُعاد إثباتُها من القرص — والدفترُ يُقارَن بها ═════ */
$ANCH = repair01_w5_anchors();
$mismatchAnchor = array(); $provenN = 0; $unprovenN = 0;
$r = $conn->query("SELECT requirement_id, anchor_screen_id, map_rule FROM repair01_w5_scope");
while ($r && $x = $r->fetch_assoc()) {
    $rid = $x['requirement_id'];
    if (!isset($ANCH[$rid])) { continue; }
    $p = repair01_w5_prove_anchor($conn, $ROOT, $ANCH[$rid]);
    if ($p['verdict'] === 'ANCHORED') { $provenN++; }
    elseif ($p['verdict'] !== 'NOT_BUILT') { $unprovenN++; $mismatchAnchor[] = $rid . ' (' . $p['verdict'] . ')'; }
    if ($p['sid'] !== $x['anchor_screen_id'] || $p['rule'] !== $x['map_rule']) {
        $mismatchAnchor[] = $rid . ' (الدفتر ' . ($x['anchor_screen_id'] !== '' ? $x['anchor_screen_id'] : '—')
                          . ' · المقيس ' . ($p['sid'] !== '' ? $p['sid'] : '—') . ')';
    }
}
gate('W5-02', 'المِرساةُ مُثبَتةٌ من القرصِ لا مُعلَنةٌ فقط',
     count($mismatchAnchor) === 0 && $provenN > 0,
     "مِرساةٌ مُثبَتةٌ $provenN · لم تُثبَت $unprovenN · الدفترُ يخالف المقيسَ " . count($mismatchAnchor)
     . (count($mismatchAnchor) ? ' ⇐ ' . implode('، ', array_slice($mismatchAnchor, 0, 2)) : ''));

/* ══ W5-03 · مالكُ السطحِ المخالفُ مُعلَنٌ بقرارٍ لا مدهوسٌ ولا مسكوتٌ عنه ═ */
$mismOwner = (int) $one("SELECT COUNT(*) FROM repair01_w5_scope WHERE owner_verdict = 'MISMATCH'");
$declD10   = (int) $one("SELECT scope_rows FROM repair01_w5_decisions
                          WHERE decision_id = 'W5-D-10' AND rationale IS NOT NULL AND rationale <> ''");
gate('W5-03', 'مالكُ السطحِ المخالفُ مُعلَنٌ بقرار', $mismOwner === $declD10 && $declD10 > 0,
     "سطحٌ تحت مالكٍ غيرِ مالكِ المتطلَّب $mismOwner · المُعلَنُ في W5-D-10 $declD10");

/* ══ W5-04 · الخطواتُ السبعُ لكلِّ سطحٍ في النطاق — مقامٌ مُعادُ الاشتقاق ══ */
$codes = repair01_w5_scope_codes($conn);
$codesSql = "'" . implode("','", array_map($esc, $codes)) . "'";
$scopeScreens = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                             WHERE owner_code IN ($codesSql) AND on_disk = 1 AND route IS NOT NULL");
$sbN = (int) $one("SELECT COUNT(*) FROM repair01_w5_sidebar");
$sbBare = (int) $one("SELECT COUNT(*) FROM repair01_w5_sidebar
    WHERE s1_verdict = '' OR s1_rule = '' OR s2_verdict = '' OR s2_rule = ''
       OR s3_verdict = '' OR s3_rule = '' OR s4_verdict = '' OR s4_rule = ''
       OR s5_verdict = '' OR s5_rule = '' OR s6_verdict = '' OR s6_rule = ''
       OR s7_verdict = '' OR s7_rule = ''");
gate('W5-04', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح',
     $sbN === $scopeScreens && $scopeScreens > 0 && $sbBare === 0,
     "أسطحُ النطاقِ المُعادُ اشتقاقُها $scopeScreens · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBare");

/* ══ W5-05 · الظهورُ بالصلاحيةِ لا بالإخفاء — مُعادُ القياسِ من الحيّ ════ */
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
gate('W5-05', 'كلُّ بندٍ في النطاقِ بصلاحيةٍ مُعلَنة', count($noPerm) === 0,
     'بندٌ بلا رمزِ صلاحيةٍ ' . count($noPerm)
     . (count($noPerm) ? ' ⇐ ' . implode('، ', array_slice($noPerm, 0, 3)) : ''));

/* ══ W5-06 · الربطُ بالسجلِّ المعياريِّ — مُعادُ الاشتقاقِ بالمسار ════════ */
$linkBad = 0; $linkN = 0;
$r = $conn->query("SELECT n.route, n.screen_id ns, g.screen_id gs
                     FROM nav_canonical n
                     JOIN repair01_screen_registry g ON g.route = n.route
                    WHERE g.owner_code IN ($codesSql) AND g.on_disk = 1");
while ($r && $x = $r->fetch_assoc()) { $linkN++; if ($x['ns'] !== $x['gs']) { $linkBad++; } }
gate('W5-06', 'كلُّ بندٍ مربوطٌ بـCanonical Screen_ID', $linkBad === 0 && $linkN > 0,
     "بندٌ معياريٌّ في النطاقِ $linkN · مربوطٌ خطأً أو غيرُ مربوطٍ $linkBad");

/* ══ W5-07 · ادّعاءُ الأبِ محكومٌ كلُّه — والخفضُ مُثبَتٌ ومُعذَّر ════════ */
$claims = (int) $one("SELECT COUNT(*) FROM repair01_w5_sidebar WHERE s5_verdict <> 'NO_PARENT'");
$judged = (int) $one("SELECT COUNT(*) FROM repair01_w5_sidebar
    WHERE s5_verdict IN ('DEMOTED_TO_TAB','ALREADY_TAB','TAB_CLAIM_UNPROVEN',
                         'TAB_BAR_NOT_RENDERED','DEMOTION_LOSES_ROLES')");
gate('W5-07', 'ادّعاءُ الأبِ محكومٌ والخفضُ مُثبَت', $claims === $judged && $claims > 0,
     "ادّعاءُ أبٍ محكومٌ $judged/$claims");

/* ══ W5-08 · جسرُ التشغيلةِ يُعاد بناؤه — والعمودُ ليس مفتاحَ أصلٍ ════════
   ⚠ **وهذا ليس تجميلًا**: قراءةُ `timesheet.operator` أصلًا مباشرةً تُسند
     ساعاتِ ١٠٨ أصولٍ إلى ٨. فالحاجبُ يشترط أن يُعاد بناءُ الجسرِ وأن يكون
     ما لا يُحَلُّ **مُعلَنًا بعددِه** لا مسكوتًا عنه. */
$bridge = repair01_w5_asset_bridge($conn);
$measure = repair01_w5_readiness_measure($conn);
$resolved = 0; $unresolved = 0;
foreach ($measure as $m) { if ($m['resolved']) { $resolved++; } else { $unresolved++; } }
$declD8 = (int) $one("SELECT scope_rows FROM repair01_w5_decisions
                       WHERE decision_id = 'W5-D-08' AND rationale IS NOT NULL AND rationale <> ''");
$naive = (int) $one("SELECT COUNT(DISTINCT CAST(t.operator AS UNSIGNED)) FROM timesheet t
                      JOIN equipments e ON e.id = CAST(t.operator AS UNSIGNED)
                     WHERE t.operator REGEXP '^[0-9]+$'");
gate('W5-08', 'جسرُ التشغيلةِ مُعادُ البناءِ وما لا يُحَلُّ مُعلَن',
     count($bridge) > 0 && $unresolved === $declD8 && $resolved > 0,
     'تشغيلاتٌ تحمل أصلًا ' . count($bridge) . " · شهرُ أصلٍ محلولٌ $resolved · بلا أصلٍ $unresolved"
     . " · المُعلَنُ في W5-D-08 $declD8 · والقراءةُ المباشرةُ تعطي $naive أصلًا فقط");

/* ══ W5-09 · حقُّ الاستخدامِ منقولٌ كلُّه والتزامنُ موسومٌ بنافذتِه ═══════
   المقارنةُ **على النوافذِ لا على الصفوف**: مجموعةُ النوافذِ التي تحمل صفًّا
   موسومًا `OPEN` يجب أن تساوي مجموعةَ النوافذِ المقيسةِ فوقَ المئة. */
$windows = repair01_w5_ownership_windows($conn);
$concWin = repair01_w5_concurrent_windows($windows);
$openWin = array();
$r = $conn->query("SELECT equipment_id, valid_from FROM asset_use_right
                    WHERE concurrency_rule = 'W5_CONCURRENT_CLAIM_OPEN'");
while ($r && $x = $r->fetch_assoc()) { $openWin[(int) $x['equipment_id'] . '|' . $x['valid_from']] = true; }
$winDiff = array_merge(
    array_diff(array_keys($concWin), array_keys($openWin)),
    array_diff(array_keys($openWin), array_keys($concWin)));
$srcRows = (int) $one("SELECT COUNT(*) FROM asset_ownership_shares
                        WHERE asset_kind = 'equipment' AND valid_from IS NOT NULL
                          AND percent > 0 AND percent <= 100");
$urRows  = (int) $one("SELECT COUNT(*) FROM asset_use_right WHERE source_register = 'asset_ownership_shares'");
$urBare  = (int) $one("SELECT COUNT(*) FROM asset_use_right WHERE concurrency_rule = ''");
/* الحبّةُ الواحدةُ بصفَّين تُطوى حتمًا — والمطويُّ مُعلَنٌ ونصُّه في الصفِّ الناجي */
$dupes = repair01_w5_ownership_dupes($conn);
$collapsed = repair01_w5_ownership_collapsed($dupes);
$declD12 = (int) $one("SELECT scope_rows FROM repair01_w5_decisions
                        WHERE decision_id = 'W5-D-12' AND rationale IS NOT NULL AND rationale <> ''");
$dupNoteMissing = 0;
foreach (array_keys($dupes) as $dk) {
    list($eqD, , $fromD) = explode('|', $dk);
    $n = (int) $one("SELECT COUNT(*) FROM asset_use_right
                      WHERE equipment_id = " . (int) $eqD . " AND valid_from = '" . $esc($fromD) . "'
                        AND concurrency_note LIKE '%share#%'");
    if ($n === 0) { $dupNoteMissing++; }
}
gate('W5-09', 'حقُّ الاستخدامِ منقولٌ وتزامنُه موسومٌ بنافذتِه',
     count($winDiff) === 0 && $urRows === ($srcRows - $collapsed) && $urBare === 0
     && $collapsed === $declD12 && $dupNoteMissing === 0,
     'نوافذُ مقيسة ' . count($windows) . ' · فوقَ المئة ' . count($concWin)
     . ' · نوافذُ موسومةٌ ' . count($openWin) . ' · فارقٌ ' . count($winDiff)
     . " · منقولٌ $urRows من " . ($srcRows - $collapsed) . " · مطويٌّ $collapsed = W5-D-12 $declD12"
     . " · مطويٌّ بلا نصٍّ $dupNoteMissing · بلا قاعدةٍ $urBare"
     . (count($winDiff) ? ' ⇐ ' . implode('، ', array_slice($winDiff, 0, 2)) : ''));

/* ══ W5-10 · الجاهزيّةُ مشتقّةٌ لا مُدخَلة — تُعاد بصيغةِ الخدمةِ نفسِها ═══ */
$rdStale = array(); $rdMissing = 0; $rdRuleless = 0;
foreach ($measure as $k => $m) {
    if (!$m['resolved']) { continue; }
    $row = $conn->query("SELECT readiness_pct, utilization_pct, derivation_rule, derived_from
                           FROM asset_readiness
                          WHERE company_id = " . (int) $m['company'] . " AND equipment_id = " . (int) $m['equipment'] . "
                            AND period = '" . $esc($m['period']) . "' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { $rdMissing++; continue; }
    if ($row['derivation_rule'] === '' || $row['derived_from'] === '') { $rdRuleless++; continue; }
    $f = ALS::readinessFormula($m['shift'], $m['executed'], $m['standby'], $m['fault'], $m['stop']);
    if (abs((float) $row['readiness_pct'] - $f['readiness']) > 0.005
        || abs((float) $row['utilization_pct'] - $f['utilization']) > 0.005) {
        $rdStale[] = $m['equipment'] . '/' . $m['period'] . ' (المخزَّن ' . $row['readiness_pct']
                   . ' · المقيس ' . $f['readiness'] . ')';
    }
}
$rdExtra = (int) $one("SELECT COUNT(*) FROM asset_readiness") - $resolved;
gate('W5-10', 'الجاهزيّةُ مشتقّةٌ ومطابقةٌ لحسابِها',
     $rdMissing === 0 && count($rdStale) === 0 && $rdRuleless === 0 && $rdExtra === 0 && $resolved > 0,
     "صفوفٌ مقيسة $resolved · ناقصةٌ $rdMissing · زائدةٌ $rdExtra · بلا قاعدةٍ $rdRuleless"
     . ' · تخالف حسابَها ' . count($rdStale)
     . (count($rdStale) ? ' ⇐ ' . implode('، ', array_slice($rdStale, 0, 2)) : ''));

/* ══ W5-11 · التغطيةُ مشتقّةٌ والمُعلَنُ مرآةٌ بفارقِها ══════════════════ */
$cov = repair01_w5_coverage_measure($conn);
$covStale = array(); $covMissing = 0; $covVar = 0;
foreach ($cov as $id => $c) {
    if ($c['variance_rule'] === 'W5_COVERAGE_VARIANCE_OPEN') { $covVar++; }
    $row = $conn->query("SELECT gap_qty, surplus_qty, coverage_state, declared_state, variance_rule, derivation_rule
                           FROM wf_coverage WHERE requirement_id = " . (int) $id . " LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { $covMissing++; continue; }
    if ((int) $row['gap_qty'] !== $c['gap'] || (int) $row['surplus_qty'] !== $c['surplus']
        || $row['coverage_state'] !== $c['state'] || $row['variance_rule'] !== $c['variance_rule']
        || $row['declared_state'] !== $c['declared_state'] || $row['derivation_rule'] === '') {
        $covStale[] = 'req#' . $id;
    }
}
$declD4 = (int) $one("SELECT scope_rows FROM repair01_w5_decisions
                       WHERE decision_id = 'W5-D-04' AND rationale IS NOT NULL AND rationale <> ''");
$vocab = repair01_w5_coverage_vocab($conn);
gate('W5-11', 'التغطيةُ مشتقّةٌ والمُعلَنُ مرآةٌ بفارقِها',
     $covMissing === 0 && count($covStale) === 0 && $covVar === $declD4
     && count($vocab['unmapped']) === 0 && count($cov) > 0,
     'سطورٌ مقيسة ' . count($cov) . " · ناقصةٌ $covMissing · تخالف حسابَها " . count($covStale)
     . " · فارقٌ مفتوحٌ $covVar · المُعلَنُ في W5-D-04 $declD4 · مفردةٌ بلا جسرٍ " . count($vocab['unmapped'])
     . (count($covStale) ? ' ⇐ ' . implode('، ', array_slice($covStale, 0, 2)) : ''));

/* ══ W5-12 · حالةُ الأصلِ مشتقّةٌ لا مُدخَلة — تُعاد من وقائعِها ═════════ */
$lc = repair01_w5_lifecycle_measure($conn);
$lcStale = array(); $lcBare = 0;
foreach ($lc as $eq => $v) {
    $row = $conn->query("SELECT lifecycle_state, lifecycle_rule FROM equipments WHERE id = " . (int) $eq . " LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { continue; }
    if ($row['lifecycle_rule'] === '') { $lcBare++; continue; }
    if ($row['lifecycle_state'] !== $v['state'] || $row['lifecycle_rule'] !== $v['rule']) {
        $lcStale[] = '#' . $eq . ' (المخزَّن ' . $row['lifecycle_state'] . ' · المقيس ' . $v['state'] . ')';
    }
}
gate('W5-12', 'حالةُ الأصلِ مشتقّةٌ لا مُدَّعاة',
     count($lcStale) === 0 && $lcBare === 0 && count($lc) > 0,
     'أصولٌ مقيسة ' . count($lc) . " · بلا قاعدةٍ $lcBare · حالةٌ تخالف المقيسَ " . count($lcStale)
     . (count($lcStale) ? ' ⇐ ' . implode('، ', array_slice($lcStale, 0, 2)) : ''));

/* ══ W5-13 · حدثٌ بلا عقدِ أثرٍ صفر · وعقدانِ لحدثٍ صفر · وعقدٌ ناقصٌ صفر ══ */
$ents = "'" . implode("','", array_map($esc, repair01_w5_entity_types())) . "'";
$keys = array();
$r = $conn->query("SELECT DISTINCT event_key FROM ems_business_events WHERE entity_type IN ($ents)");
while ($r && $x = $r->fetch_row()) { $keys[$x[0]] = true; }
foreach (repair01_w5_stage_events() as $ek) { $keys[$ek] = true; }
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
gate('W5-13', 'حدثُ النطاقِ بعقدِ أثرٍ واحدٍ كامل',
     count($noContract) === 0 && count($dupContract) === 0 && count($thin) === 0 && count($keys) > 0,
     'أحداثُ النطاق ' . count($keys) . ' · بلا عقدٍ ' . count($noContract)
     . ' · بعقدَين ' . count($dupContract) . ' · عقدٌ ناقصٌ ' . count($thin)
     . ($noContract ? ' ⇐ ' . implode('، ', array_slice($noContract, 0, 2)) : '')
     . ($dupContract ? ' ⇐ ' . implode('، ', array_slice($dupContract, 0, 2)) : '')
     . ($thin ? ' ⇐ ' . implode('، ', array_slice($thin, 0, 2)) : ''));

/* ══ W5-14 · التركيبةُ الممنوعةُ مقيسةٌ ومقفلةُ الاتّجاه ═════════════════ */
$sodOrd = (int) $one("SELECT COUNT(*) FROM asset_inspection_order o
                        JOIN asset_intake i ON i.id = o.intake_id
                       WHERE o.ordered_by = i.requested_by AND o.ordered_by IS NOT NULL");
$sodVer = (int) $one("SELECT COUNT(*) FROM asset_source_check c
                        JOIN asset_intake i ON i.id = c.intake_id
                       WHERE c.verified_by = i.requested_by AND c.verified_by IS NOT NULL");
$declD6 = (int) $one("SELECT scope_rows FROM repair01_w5_decisions
                       WHERE decision_id = 'W5-D-06' AND rationale IS NOT NULL AND rationale <> ''");
gate('W5-14', 'التركيبةُ الممنوعةُ مقفلةُ الاتّجاه', ($sodOrd + $sodVer) <= $declD6 && $declD6 >= 0,
     "طالبٌ يأمر بتفتيشِ طلبِه $sodOrd · طالبٌ يحقّق مصدرَه $sodVer · المُعلَنُ في W5-D-06 $declD6");

/* ══ W5-15 · أساسُ المراحلِ السابقةِ لم يُمَسَّ والنموُّ مختوم ═══════════ */
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
$k0 = (int) $one("SELECT COUNT(*) FROM repair01_key_registry");
gate('W5-15', 'أساسُ المراحلِ السابقةِ لم يُمَسّ',
     $d0 === $W00['decisions'] && $s0 === $W00['source_files'] && $u0 === $W00['surfaces'] && $g0 === $W00['registry_base'] && $gWild === 0
     && $t0 === $W00['gaps_original'] && $e0 === $W00['events_study'] && $e3 === 13 && $e4 === 3 && $k0 === 13,
     "قرارات $d0 · مصادر $s0 · أسطح $u0 · أساسُ السجلّ $g0 · نموٌّ مختومٌ $gNew · نموٌّ بلا ختمٍ $gWild"
     . " · فجواتٌ أصليّة $t0 · أحداثُ الدراسة $e0 · عقودُ W03 $e3 · عقودُ W04 $e4 · مفاتيح $k0");

/* ══ W5-16 · رحلةُ الأصل — تُشغَّل هنا ويُشترط عبورُها كاملةً ════════════ */
$jOut = array(); $jCode = 1;
@exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_w5_journey.php" 2>&1', $jOut, $jCode);
/* ⚠ **مُعرِّفُ الجولةِ يُقرأ من مخرَجِ الرحلةِ لا من «آخرِ صفٍّ في الجدول»**:
     رحلةٌ لم تنعقد تترك دليلَ الجولةِ السابقةِ قائمًا، فقراءةُ الآخِرِ تُخضِرُّ
     على عبورٍ لم يقع. ولا مُعرِّفَ في المخرَجِ ⇒ لا رحلةَ ⇒ الحاجبُ يسقط. */
$run = '';
foreach ($jOut as $l) { if (preg_match('/^RUN=(W5J-\d+)$/', trim($l), $m)) { $run = $m[1]; break; } }
$jTotal = (int) $one("SELECT COUNT(*) FROM repair01_w5_journey WHERE run_id = '" . $esc($run) . "'");
$jPass  = (int) $one("SELECT COUNT(*) FROM repair01_w5_journey WHERE run_id = '" . $esc($run) . "' AND passed = 1");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w5_journey
                       WHERE run_id = '" . $esc($run) . "' AND (business_effect = '' OR business_effect = '—')");
$jCons  = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w5_journey WHERE run_id = '" . $esc($run) . "'");
$jRead  = (int) $one("SELECT COUNT(DISTINCT readiness_after) FROM repair01_w5_journey WHERE run_id = '" . $esc($run) . "'");
gate('W5-16', 'رحلةُ الأصلِ تعبر بمحطّاتِها',
     $jCode === 0 && $run !== '' && $jTotal > 0 && $jPass === $jTotal
     && $jCons >= 5 && $jNoEff === 0 && $jRead >= 5,
     'الجولة ' . ($run !== '' ? $run : '— لم تُعلَن —')
     . " · عابرٌ $jPass/$jTotal · مستهلكونَ متمايزون $jCons · حالاتُ جاهزيّةٍ متمايزة $jRead"
     . " · بلا أثرٍ تجاريٍّ مقيسٍ $jNoEff" . ($jCode !== 0 ? ' · رمزُ الخروج ' . $jCode : ''));

/* ══ W5-17 · سجلُّ التوقُّفِ المخلوطُ مُعلَنٌ بعددِه لا مسكوتٌ عنه ═══════ */
$stopOrphan = (int) $one("SELECT COUNT(*) FROM ops_stop_register o
                           LEFT JOIN equipments e ON e.id = o.equipment_id
                          WHERE e.id IS NULL AND o.authority = 'timesheet'");
$stopUtl    = (int) $one("SELECT COUNT(*) FROM ops_stop_register o
                           LEFT JOIN equipments e ON e.id = o.equipment_id
                          WHERE e.id IS NULL AND o.authority = 'unit_time_log'");
$declD9 = (int) $one("SELECT scope_rows FROM repair01_w5_decisions
                       WHERE decision_id = 'W5-D-09' AND rationale IS NOT NULL AND rationale <> ''");
gate('W5-17', 'خلطُ فضاءِ المفاتيحِ مُعلَنٌ بعددِه',
     $stopOrphan === $declD9 && $stopUtl === 0,
     "يتيمٌ بحاكمِ التايم شيت $stopOrphan · المُعلَنُ في W5-D-09 $declD9 · يتيمٌ بحاكمِ السجلِّ الزمنيّ $stopUtl");

/* ══ W5-18 · أسطحُ النموِّ مبنيّةٌ ومحروسةٌ وموصولةٌ ومختومة ═════════════ */
$grow = array(); $growBad = array();
$r = $conn->query("SELECT screen_id, route, guard_kind, visibility_class FROM repair01_screen_registry
                    WHERE origin = 'W05'");
while ($r && $x = $r->fetch_assoc()) {
    $grow[] = $x['route'];
    if (!is_file($ROOT . '/' . $x['route'])) { $growBad[] = $x['route'] . ' (لا ملفَّ)'; continue; }
    $g = repair01_w5_guard_of($ROOT, $x['route']);
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
gate('W5-18', 'أسطحُ النموِّ مبنيّةٌ ومحروسةٌ وموصولة',
     count($grow) === 6 && count($growBad) === 0,
     'أسطحُ النموِّ المختومةُ W05 ' . count($grow) . ' · ناقصةٌ ' . count($growBad)
     . (count($growBad) ? ' ⇐ ' . implode('، ', array_slice($growBad, 0, 2)) : ''));

echo implode("\n", $LINES) . "\n";
echo str_repeat('─', 108) . "\n";
printf("W5 gate: %d/%d  ·  أثرٌ بلا واقعةِ مصدرٍ %d  ·  جاهزيّةٌ مُدخَلةٌ يدويًّا %d  ·  حقٌّ متزامنٌ مفتوحٌ %d  ·  رحلةٌ %d/%d\n",
    $PASS, $PASS + $FAIL, count($noContract), count($rdStale) + $rdRuleless, count($concWin), $jPass, $jTotal);
echo 'الحكم: ' . ($FAIL === 0 ? "خضراء ✔\n" : "حمراء ✘\n");
exit($FAIL === 0 ? 0 : 1);
