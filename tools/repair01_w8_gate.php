<?php
/**
 * tools/repair01_w8_gate.php — بوّابةُ المرحلةِ الثامنة (المبيعات والموردون)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تُعيد القياسَ ولا تقرأ ما خزَّنَته الأداة** (‏_CONTEXT §قواعد القياس ①):
 *   المِرساةُ تُعاد إثباتُها من القرص · الخطواتُ السبعُ يُعاد اشتقاقُ مقامِها ·
 *   الانحدارُ يُعاد تشغيلُه **حيًّا** ثمَّ يُقارَن بالمخزَّن — فبوّابةٌ تقرأ
 *   مخرَجَ الأداةِ التي تفحصها حشوٌ لا فحص.
 *
 * ◆ **و«انحدارٌ ساقطٌ 0» تُقاس على ما لم يُعلَن**: سقوطٌ **مُعلَنٌ بقرارٍ يحمل
 *   عددَه المقيسَ حرفًا** ليس سقوطًا مسكوتًا عنه — وهو نمطُ `W5-D-09` و`W7-D-04`.
 *   ولحظةَ يتحرَّك العددُ عن المُعلَنِ تسقط البوّابة. **والاتّجاهُ مقفلٌ في
 *   الجهتَين**: زيادةٌ خرقٌ ونقصانٌ إعلانٌ متقادم.
 *
 * ◆ **والتراجعُ وحدَه يُسقط لا السقوطُ الموروث**: `BASELINE` مرَّ و`AFTER` سقط
 *   ⇒ تراجعٌ. وهو المعنى الحرفيُّ لـ«شغّل Regression أوّلًا» (§19).
 *
 * ◆ **و`W8-21` تُغلِّف رحلتَي الإثبات**: تشغّلهما وتشترط عبورَهما **كاملتَين**
 *   — فلا تُقبل المرحلةُ إن لم تعبرا ولو كانت كلُّ بوّاباتِها خضراء (§٦-أ).
 *
 * التشغيل: php tools/repair01_w8_gate.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/tools/lib/repair01_w8_scan.php';
require_once $ROOT . '/tools/lib/repair01_w8_contracts.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
/* مرساةُ الطورِ صفرِ — **حقيقةٌ مسجَّلةٌ لا ثابتٌ حرفيّ** (RPR-AMD01) */
require_once __DIR__ . '/lib/repair01_w00_anchor.php';
$W00 = w00_anchors($conn);

while (ob_get_level()) { ob_end_clean(); }

$PASS = 0; $FAIL = 0; $LINES = array();
function w8_pad($s, $len)
{
    $n = mb_strlen($s, 'UTF-8');
    return $s . ($n < $len ? str_repeat(' ', $len - $n) : ' ');
}
function gate($code, $title, $ok, $detail)
{
    global $PASS, $FAIL, $LINES;
    if ($ok) { $PASS++; $mark = '✔'; } else { $FAIL++; $mark = '✘'; }
    $LINES[] = '  ' . $mark . ' ' . str_pad($code, 9) . w8_pad($title, 46) . $detail;
}
$one = function ($sql) use ($conn) { return repair01_w8_one($conn, $sql); };
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

echo "═══════ بوّابةُ المرحلةِ الثامنة — REPAIR01 · المبيعاتُ والموردون ═══════\n";

/* ══ W8-01 · نطاقُ المرحلةِ كاملٌ — المقامُ من السجلِّ لا من الدفتر ═══════ */
$reqN   = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 8");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w8_scope");
$bare   = (int) $one("SELECT COUNT(*) FROM repair01_w8_scope WHERE map_rule = '' OR map_why = '' OR src_ref = ''");
$orphan = (int) $one("SELECT COUNT(*) FROM repair01_w8_scope s
    WHERE s.anchor_screen_id <> ''
      AND NOT EXISTS (SELECT 1 FROM repair01_screen_registry g WHERE g.screen_id = s.anchor_screen_id)");
gate('W8-01', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $scopeN === $reqN && $reqN > 0 && $bare === 0 && $orphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $bare · مِرساةٌ يتيمةٌ $orphan");

/* ══ W8-02 · المِرساةُ تُعاد إثباتُها من القرص — والدفترُ يُقارَن بها ═════ */
$ANCH = repair01_w8_anchors();
$mismatchAnchor = array(); $provenN = 0; $gapN = 0; $unprovenN = 0;
$r = $conn->query("SELECT requirement_id, anchor_screen_id, map_rule FROM repair01_w8_scope");
while ($r && $x = $r->fetch_assoc()) {
    $rid = $x['requirement_id'];
    if (!isset($ANCH[$rid])) { $unprovenN++; $mismatchAnchor[] = $rid . ' (بلا مِرساةٍ مُعلَنة)'; continue; }
    $p = repair01_w8_prove_anchor($conn, $ROOT, $ANCH[$rid]);
    if ($p['verdict'] === 'ANCHORED') { $provenN++; }
    elseif ($p['verdict'] === 'NOT_BUILT') { $gapN++; }
    else { $unprovenN++; $mismatchAnchor[] = $rid . ' (' . $p['verdict'] . ')'; }
    if ($p['sid'] !== $x['anchor_screen_id'] || $p['rule'] !== $x['map_rule']) {
        $mismatchAnchor[] = $rid . ' (الدفتر ' . ($x['anchor_screen_id'] !== '' ? $x['anchor_screen_id'] : '—')
                          . ' · المقيس ' . ($p['sid'] !== '' ? $p['sid'] : '—') . ')';
    }
}
$gapDecl = (int) $one("SELECT scope_rows FROM repair01_w8_decisions WHERE decision_id = 'W8-D-01'");
gate('W8-02', 'المِرساةُ مُثبَتةٌ من القرصِ أو فجوةٌ مُعلَنة',
     count($mismatchAnchor) === 0 && $unprovenN === 0 && ($provenN + $gapN) === $reqN && $gapN === $gapDecl,
     "مُثبَتةٌ $provenN · فجوةٌ $gapN (المُعلَنُ في W8-D-01 $gapDecl) · لم تُثبَت $unprovenN"
     . (count($mismatchAnchor) ? ' ⇐ ' . implode('، ', array_slice($mismatchAnchor, 0, 2)) : ''));

/* ══ W8-03 · مالكُ السطحِ المخالفُ مُعلَنٌ بقرارٍ لا مدهوسٌ ولا مسكوتٌ عنه ═ */
$mismOwner = (int) $one("SELECT COUNT(*) FROM repair01_w8_scope WHERE owner_verdict = 'MISMATCH'");
$declD3    = (int) $one("SELECT scope_rows FROM repair01_w8_decisions
                          WHERE decision_id = 'W8-D-03' AND rationale IS NOT NULL AND rationale <> ''");
gate('W8-03', 'مالكُ السطحِ المخالفُ مُعلَنٌ بقرار', $mismOwner === $declD3 && $declD3 > 0,
     "سطحٌ تحت مالكٍ غيرِ مالكِ المتطلَّب $mismOwner · المُعلَنُ في W8-D-03 $declD3");

/* ══ W8-04 · الخطواتُ السبعُ لكلِّ سطحٍ في النطاق — مقامٌ مُعادُ الاشتقاق ══ */
$codes = repair01_w8_scope_codes($conn);
$codesSql = "'" . implode("','", array_map($esc, $codes)) . "'";
$scopeScreens = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                             WHERE owner_code IN ($codesSql) AND on_disk = 1 AND route IS NOT NULL");
$sbN = (int) $one("SELECT COUNT(*) FROM repair01_w8_sidebar");
$sbBare = (int) $one("SELECT COUNT(*) FROM repair01_w8_sidebar
    WHERE s1_verdict = '' OR s1_rule = '' OR s2_verdict = '' OR s2_rule = ''
       OR s3_verdict = '' OR s3_rule = '' OR s4_verdict = '' OR s4_rule = ''
       OR s5_verdict = '' OR s5_rule = '' OR s6_verdict = '' OR s6_rule = ''
       OR s7_verdict = '' OR s7_rule = ''");
gate('W8-04', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح',
     $sbN === $scopeScreens && $scopeScreens > 0 && $sbBare === 0,
     "أسطحُ النطاقِ المُعادُ اشتقاقُها $scopeScreens · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBare");

/* ══ W8-05 · الظهورُ بالصلاحيةِ لا بالإخفاء — مُعادُ القياسِ من الحيّ ════ */
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
gate('W8-05', 'كلُّ بندٍ حيٍّ مرشَّحٌ بصلاحيّة', count($noPerm) === 0,
     'بندٌ حيٌّ بلا رمزِ صلاحيّةٍ ' . count($noPerm)
     . (count($noPerm) ? ' ⇐ ' . implode('، ', array_slice($noPerm, 0, 2)) : ''));

/* ══ W8-06 · حارسُ عرضٍ على الخادمِ لكلِّ سطحٍ — مقيسٌ من الملفِّ لا مُدَّعًى ═ */
$noGuard = array();
$r = $conn->query("SELECT screen_id, route FROM repair01_screen_registry
                    WHERE owner_code IN ($codesSql) AND on_disk = 1 AND route IS NOT NULL");
while ($r && $x = $r->fetch_assoc()) {
    $g = repair01_w8_guard_of($ROOT, $x['route']);
    if ($g['kind'] === 'NONE') { $noGuard[] = $x['route']; }
}
gate('W8-06', 'حارسُ عرضٍ مقيسٌ في ملفِّ كلِّ سطح', count($noGuard) === 0,
     'سطحٌ بلا حارسٍ مقيسٍ ' . count($noGuard)
     . (count($noGuard) ? ' ⇐ ' . implode('، ', array_slice($noGuard, 0, 2)) : ''));

/* ══ W8-07 · الترتيبُ من السجلِّ — والفاقدُ مُعلَنٌ لا مرتَّبٌ بالأبجديّة ══ */
$noOrder = (int) $one("SELECT COUNT(*) FROM repair01_w8_sidebar WHERE s4_verdict = 'NO_ORDER_SOURCE'");
$declD2  = (int) $one("SELECT scope_rows FROM repair01_w8_decisions WHERE decision_id = 'W8-D-02'");
gate('W8-07', 'مصدرُ الترتيبِ من السجلِّ والفاقدُ مُعلَن', $noOrder === $declD2,
     "بلا مصدرِ ترتيبٍ $noOrder · المُعلَنُ في W8-D-02 $declD2");

/* ══ W8-08 · الربطُ بـCanonical Screen_ID — مُعادُ القياسِ من السجلّ ═════ */
$linkedBad = (int) $one("SELECT COUNT(*) FROM nav_canonical c
    JOIN repair01_screen_registry g ON g.route = c.route
   WHERE g.owner_code IN ($codesSql) AND g.on_disk = 1
     AND (c.screen_id IS NULL OR c.screen_id = '' OR c.screen_id <> g.screen_id)");
$linkedN = (int) $one("SELECT COUNT(*) FROM nav_canonical c
    JOIN repair01_screen_registry g ON g.route = c.route
   WHERE g.owner_code IN ($codesSql) AND g.on_disk = 1 AND c.screen_id = g.screen_id");
gate('W8-08', 'كلُّ بندٍ معياريٍّ مربوطٌ بمُعرِّفِ شاشتِه', $linkedBad === 0 && $linkedN > 0,
     "بندٌ معياريٌّ في النطاقِ $linkedN · مربوطٌ خطأً أو غيرُ مربوطٍ $linkedBad");

/* ══ W8-09 · الانحدارُ يُعاد تشغيلُه حيًّا — والمخزَّنُ يُقارَن بالمقيس ═══ */
$live = repair01_w8_run_regression($conn);
$drift = array();
foreach ($live as $key => $m) {
    $st = $conn->query("SELECT measured, denominator, verdict FROM repair01_w8_regression
                         WHERE phase='AFTER' AND check_key='" . $esc($key) . "'");
    $st = $st ? $st->fetch_assoc() : null;
    if (!$st) { $drift[] = $key . ' (بلا شوطٍ لاحق)'; continue; }
    if ((int) $st['measured'] !== (int) $m['measured'] || (int) $st['denominator'] !== (int) $m['denom']) {
        $drift[] = $key . ' (المخزَّن ' . (int) $st['measured'] . '/' . (int) $st['denominator']
                 . ' · المقيس ' . (int) $m['measured'] . '/' . (int) $m['denom'] . ')';
    }
}
gate('W8-09', 'الانحدارُ مُعادُ التشغيلِ والمخزَّنُ يطابقه', count($drift) === 0 && count($live) > 0,
     'فحوصٌ ' . count($live) . ' · انحرافٌ بين المخزَّنِ والمقيسِ ' . count($drift)
     . (count($drift) ? ' ⇐ ' . implode('، ', array_slice($drift, 0, 2)) : ''));

/* ══ W8-10 · تراجعٌ صفر — عابرٌ في الأساسِ ساقطٌ بعده ═════════════════ */
$baseN = (int) $one("SELECT COUNT(*) FROM repair01_w8_regression WHERE phase='BASELINE'");
$regressed = array();
$r = $conn->query("SELECT b.check_key FROM repair01_w8_regression b
                     JOIN repair01_w8_regression a ON a.check_key = b.check_key AND a.phase='AFTER'
                    WHERE b.phase='BASELINE' AND b.verdict='PASS' AND a.verdict <> 'PASS'");
while ($r && $x = $r->fetch_assoc()) { $regressed[] = $x['check_key']; }
gate('W8-10', 'تراجعٌ صفرٌ عن شوطِ الأساس', count($regressed) === 0 && $baseN > 0,
     "شوطُ الأساس $baseN فحصًا · تراجعَ " . count($regressed)
     . (count($regressed) ? ' ⇐ ' . implode('، ', $regressed) : ''));

/* ══ W8-11 · انحدارٌ ساقطٌ غيرُ مُعلَنٍ صفر — والمُعلَنُ بعددِه حرفًا ════ */
$DECLARED = repair01_w8_declared_failures();
$undeclared = array(); $declDrift = array(); $declaredN = 0;
foreach ($live as $key => $m) {
    if ($m['verdict'] === 'PASS') { continue; }
    if (!isset($DECLARED[$key])) { $undeclared[] = $key . ' (' . $m['measured'] . ')'; continue; }
    $did = $DECLARED[$key];
    $rows = $one("SELECT scope_rows FROM repair01_w8_decisions WHERE decision_id = '" . $esc($did) . "'");
    if ($rows === null) { $undeclared[] = $key . ' (قرارٌ مفقودٌ ' . $did . ')'; continue; }
    if ((int) $rows !== (int) $m['measured']) {
        $declDrift[] = $key . ' (المُعلَنُ ' . (int) $rows . ' · المقيسُ ' . (int) $m['measured'] . ')';
        continue;
    }
    $declaredN++;
}
gate('W8-11', 'كلُّ سقوطٍ مُعلَنٌ بقرارٍ يحمل عددَه',
     count($undeclared) === 0 && count($declDrift) === 0,
     "ساقطٌ مُعلَنٌ بعددِه $declaredN · بلا إعلانٍ " . count($undeclared) . ' · عددٌ يخالف الإعلانَ ' . count($declDrift)
     . (count($undeclared) ? ' ⇐ ' . implode('، ', $undeclared) : '')
     . (count($declDrift) ? ' ⇐ ' . implode('، ', $declDrift) : ''));

/* ══ W8-12 · تعديلٌ بلا متطلَّبٍ كاشفٍ صفر ═══════════════════════════ */
$fixN = (int) $one("SELECT COUNT(*) FROM repair01_w8_fixes");
$fixBare = (int) $one("SELECT COUNT(*) FROM repair01_w8_fixes
                        WHERE revealed_by = '' OR reveal_why = '' OR evidence = ''");
$fixOrphan = (int) $one("SELECT COUNT(*) FROM repair01_w8_fixes f
    WHERE NOT EXISTS (SELECT 1 FROM repair01_requirements r
                       WHERE r.requirement_id = f.revealed_by AND r.stage_no = 8)");
gate('W8-12', 'كلُّ إصلاحٍ بمتطلَّبٍ كاشفٍ من نطاقِ المرحلة',
     $fixN > 0 && $fixBare === 0 && $fixOrphan === 0,
     "إصلاحاتٌ $fixN · بلا كاشفٍ أو دليلٍ $fixBare · كاشفٌ خارجَ النطاقِ $fixOrphan");

/* ══ W8-13 · آلةُ حالةٍ لكلِّ كيانٍ رئيسيٍّ — والممنوعُ بسببِه المكتوب ═══ */
$entities = repair01_w8_entity_types();
$missSM = array();
foreach ($entities as $e) {
    $n = (int) $one("SELECT COUNT(*) FROM repair01_w8_states WHERE entity = '" . $esc($e) . "'");
    $no = (int) $one("SELECT COUNT(*) FROM repair01_w8_states WHERE entity = '" . $esc($e) . "' AND allowed = 0");
    if ($n === 0 || $no === 0) { $missSM[] = $e . ' (' . $n . '/' . $no . ')'; }
}
$smBare = (int) $one("SELECT COUNT(*) FROM repair01_w8_states WHERE allowed = 0 AND forbid_reason = ''");
gate('W8-13', 'آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ مُسبَّب',
     count($missSM) === 0 && $smBare === 0,
     'كياناتٌ ' . count($entities) . ' · بلا آلةٍ أو بلا ممنوعٍ ' . count($missSM) . ' · ممنوعٌ بلا سببٍ ' . $smBare
     . (count($missSM) ? ' ⇐ ' . implode('، ', array_slice($missSM, 0, 2)) : ''));

/* ══ W8-14 · فصلُ الواجبات — ستّةُ أدوارٍ وتركيبةٌ ممنوعةٌ وإنفاذٌ مصنَّف ═ */
$sodN = (int) $one("SELECT COUNT(*) FROM repair01_w8_sod");
$sodBare = (int) $one("SELECT COUNT(*) FROM repair01_w8_sod
    WHERE initiator_role='' OR reviewer_role='' OR approver_role='' OR executor_role=''
       OR closer_role='' OR forbidden_combo='' OR authority_rule_id='' OR deputy_role=''
       OR scope_rule='' OR delegation='' OR effective_date IS NULL");
$sodUnclass = (int) $one("SELECT COUNT(*) FROM repair01_w8_sod
    WHERE enforced_by NOT LIKE 'ENFORCED:%' AND enforced_by NOT LIKE 'NOT_ENFORCED:%'");
$sodNotEnf = (int) $one("SELECT COUNT(*) FROM repair01_w8_sod WHERE enforced_by LIKE 'NOT_ENFORCED:%'");
$declD10 = (int) $one("SELECT scope_rows FROM repair01_w8_decisions WHERE decision_id = 'W8-D-10'");
gate('W8-14', 'فصلُ واجباتٍ كاملُ الأدوارِ ومصنَّفُ الإنفاذ',
     $sodN > 0 && $sodBare === 0 && $sodUnclass === 0 && $sodNotEnf === $declD10,
     "عملياتٌ $sodN · ناقصةٌ $sodBare · إنفاذٌ غيرُ مصنَّفٍ $sodUnclass · بلا حارسٍ $sodNotEnf (المُعلَنُ في W8-D-10 $declD10)");

/* ══ W8-15 · حدثُ النطاقِ بعقدِ أثرٍ واحدٍ كامل ═══════════════════════ */
$EV = repair01_w8_stage_events();
$noContract = array(); $partial = array(); $generic = 0;
foreach ($EV as $code) {
    $row = $conn->query("SELECT contract_status, trigger_rule, min_payload, consumer_list, consumer_effect,
                                preconditions, failure_policy, compensation, idempotency_key
                           FROM repair01_events WHERE event_code = '" . $esc($code) . "'");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row || $row['contract_status'] !== 'RECORDED') { $noContract[] = $code; continue; }
    foreach (array('trigger_rule', 'min_payload', 'consumer_list', 'consumer_effect',
                   'preconditions', 'failure_policy', 'compensation', 'idempotency_key') as $f) {
        if (trim((string) $row[$f]) === '') { $partial[] = $code . '/' . $f; }
    }
    if (mb_strpos((string) $row['consumer_list'], 'كل المستهلكين') !== false) { $generic++; }
}
$dupe = (int) $one("SELECT COUNT(*) FROM (SELECT event_code FROM repair01_events
                     WHERE contract_stage='W08' GROUP BY event_code HAVING COUNT(*) > 1) x");
gate('W8-15', 'حدثُ النطاقِ بعقدِ أثرٍ واحدٍ كامل',
     count($noContract) === 0 && count($partial) === 0 && $generic === 0 && $dupe === 0,
     'أحداثُ النطاق ' . count($EV) . ' · بلا عقدٍ ' . count($noContract) . ' · ناقصٌ ' . count($partial)
     . " · «كلُّ المستهلكين» $generic · مكرَّرٌ $dupe"
     . (count($noContract) ? ' ⇐ ' . implode('، ', array_slice($noContract, 0, 2)) : ''));

/* ══ W8-16 · ناشرُ كلِّ حدثٍ مُثبَتٌ من القرصِ لا مُعلَنٌ فقط ═══════════ */
$EMIT = repair01_w8_stage_event_emitters();
$noEmitter = array();
foreach ($EMIT as $code => $file) {
    $p = $ROOT . '/' . $file;
    $src = is_file($p) ? (string) file_get_contents($p) : '';
    if ($src === '' || strpos($src, "'" . $code . "'") === false) { $noEmitter[] = $code; }
}
gate('W8-16', 'ناشرُ كلِّ حدثٍ مُثبَتٌ من القرص', count($noEmitter) === 0 && count($EMIT) > 0,
     'أحداثٌ ' . count($EMIT) . ' · بلا ناشرٍ مُثبَتٍ ' . count($noEmitter)
     . (count($noEmitter) ? ' ⇐ ' . implode('، ', array_slice($noEmitter, 0, 2)) : ''));

/* ══ W8-17 · العتبةُ من السجلِّ — ولا رقمَ صلبٌ في أدواتِ النطاق ════════ */
$thN = (int) $one("SELECT COUNT(*) FROM repair01_w8_thresholds");
$thBare = (int) $one("SELECT COUNT(*) FROM repair01_w8_thresholds WHERE why = '' OR decision_ref = ''");
$hard = array();
foreach (array('tools/lib/repair01_w8_scan.php', 'tools/repair01_w8_apply.php',
               'tools/repair01_w8_gate.php', 'tools/repair01_w8_journey.php') as $f) {
    $src = @file_get_contents($ROOT . '/' . $f);
    if ($src === false) { continue; }
    /* ⛔ الرسوُّ على **مقارنةِ عتبةٍ** لا على أيِّ رقم: عدُّ الصفوفِ مشروعٌ
         و`> 100000` أو `>= 85` في نصِّ أداةٍ من أدواتِ النطاقِ ليس كذلك. */
    if (preg_match('~[<>]=?\s*(\d{2,})\s*(?:\)|;|&&|\|\|)~', $src, $m)) { $hard[] = $f . ' (' . $m[1] . ')'; }
}
gate('W8-17', 'العتبةُ من السجلِّ ولا مقارنةَ رقمٍ صلبة',
     $thN > 0 && $thBare === 0 && count($hard) === 0,
     "عتباتٌ $thN · بلا عذرٍ أو مرجعٍ $thBare · مقارنةٌ صلبةٌ في أداةٍ " . count($hard)
     . (count($hard) ? ' ⇐ ' . implode('، ', $hard) : ''));

/* ══ W8-18 · أساسُ المراحلِ السابقةِ لم يُمَسّ ════════════════════════ */
$dec  = (int) $one("SELECT COUNT(*) FROM repair01_decisions");
$srcF = (int) $one("SELECT COUNT(*) FROM repair01_source_files");
$surf = (int) $one("SELECT COUNT(*) FROM repair01_surfaces");
$base = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin IN ('SURFACES','DISK','NAV')");
$grow = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin NOT IN ('SURFACES','DISK','NAV') AND origin <> ''");
$unst = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin = ''");
$gapsO = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = ''");
$evStudy = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = ''");
gate('W8-18', 'أساسُ المراحلِ السابقةِ لم يُمَسّ',
     $dec === $W00['decisions'] && $srcF === $W00['source_files'] && $surf === $W00['surfaces'] && $base === $W00['registry_base'] && $unst === 0 && $gapsO === $W00['gaps_original'],
     "قرارات $dec · مصادر $srcF · أسطح $surf · أساسُ السجلّ $base · نموٌّ مختومٌ $grow · بلا ختمٍ $unst"
     . " · فجواتٌ أصليّة $gapsO · أحداثُ الدراسة $evStudy");

/* ══ W8-19 · لا نموَّ في السجلِّ من هذه المرحلة — انحدارٌ لا بناء ═══════ */
$w8grow = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin = 'W08'");
gate('W8-19', 'صفرُ سطحٍ جديدٍ — المرحلةُ انحدارٌ لا بناء', $w8grow === 0,
     "أسطحٌ مختومةٌ W08 $w8grow · والوحدتانِ مرجعيّتان (§19)");

/* ══ W8-20 · موجةُ كلِّ فجوةٍ من السجلِّ لا من خريطةٍ صلبة ══════════════ */
$gapBad = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps g
   WHERE g.origin_stage='W02' AND g.wave_stage<>''
     AND g.wave_stage <> CONCAT('W', LPAD((SELECT MAX(r.stage_no) FROM repair01_requirements r
          WHERE r.unit LIKE CONCAT(SUBSTRING(g.unit,5),' %')), 2, '0'))
     AND (SELECT MAX(r.stage_no) FROM repair01_requirements r
          WHERE r.unit LIKE CONCAT(SUBSTRING(g.unit,5),' %')) IS NOT NULL");
$mapSrc = (string) @file_get_contents($ROOT . '/tools/lib/repair01_w2_scan.php');
$mapFromRegistry = (strpos($mapSrc, 'repair01_requirements WHERE unit LIKE') !== false);
$w8gaps = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE wave_stage = 'W08'");
gate('W8-20', 'موجةُ الفجوةِ مشتقّةٌ من السجلِّ ومطابقةٌ لمرحلتِها',
     $gapBad === 0 && $mapFromRegistry,
     "فجوةٌ بموجةٍ تخالف مرحلةَ وحدتِها $gapBad · المشتقُّ من السجلِّ " . ($mapFromRegistry ? 'نعم' : 'لا')
     . " · فجواتُ W08 $w8gaps");

/* ══ W8-21 · رحلتا الإثباتِ تعبران بمحطّاتِهما (‏§٦-أ) ════════════════ */
$jr = 0; $jout = array();
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/repair01_w8_journey.php') . ' 2>&1', $jout, $jr);
$jRun = (string) $one("SELECT run_id FROM repair01_w8_journey ORDER BY id DESC LIMIT 1");
$jTot = (int) $one("SELECT COUNT(*) FROM repair01_w8_journey WHERE run_id = '" . $esc($jRun) . "'");
$jOk  = (int) $one("SELECT COUNT(*) FROM repair01_w8_journey WHERE run_id = '" . $esc($jRun) . "' AND passed = 1");
$jCons = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w8_journey WHERE run_id = '" . $esc($jRun) . "'");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w8_journey WHERE run_id = '" . $esc($jRun) . "' AND business_effect = ''");
$jKeys = (int) $one("SELECT COUNT(DISTINCT journey_key) FROM repair01_w8_journey WHERE run_id = '" . $esc($jRun) . "'");
gate('W8-21', 'رحلتا العميلِ والمورِّدِ تعبران بمحطّاتِهما',
     $jr === 0 && $jTot > 0 && $jOk === $jTot && $jNoEff === 0 && $jKeys === 2,
     "الجولة $jRun · عابرٌ $jOk/$jTot · رحلتان $jKeys · مستهلكونَ متمايزون $jCons · بلا أثرٍ تجاريٍّ $jNoEff");

/* ══ W8-22 · دفترُ الانحدارِ بشوطَيه — والأساسُ لا يُدهَس ════════════ */
$afterN = (int) $one("SELECT COUNT(*) FROM repair01_w8_regression WHERE phase='AFTER'");
$baseRuns = (int) $one("SELECT COUNT(DISTINCT run_id) FROM repair01_w8_regression WHERE phase='BASELINE'");
$noRev = (int) $one("SELECT COUNT(*) FROM repair01_w8_regression r
    WHERE NOT EXISTS (SELECT 1 FROM repair01_requirements q
                       WHERE q.requirement_id = r.revealed_by AND q.stage_no = 8)");
gate('W8-22', 'شوطا الانحدارِ مسجَّلانِ وكلُّ فحصٍ بكاشفِه',
     $baseN === $afterN && $baseN > 0 && $baseRuns === 1 && $noRev === 0,
     "أساسٌ $baseN (جولةٌ واحدةٌ " . ($baseRuns === 1 ? 'نعم' : 'لا') . ") · لاحقٌ $afterN · فحصٌ بكاشفٍ خارجَ النطاقِ $noRev");

/* ═══ الطباعة ═══════════════════════════════════════════════════════════ */
echo implode("\n", $LINES) . "\n";
echo str_repeat('─', 118) . "\n";
$undeclN = count($undeclared) + count($declDrift);
printf("W8 gate: %d/%d  ·  انحدارٌ ساقطٌ غيرُ مُعلَنٍ %d  ·  تراجعٌ %d  ·  تعديلٌ بلا متطلَّبٍ كاشفٍ %d  ·  رحلتان %d/%d\n",
       $PASS, $PASS + $FAIL, $undeclN, count($regressed), $fixBare + $fixOrphan, $jOk, $jTot);
echo 'الحكم: ' . ($FAIL === 0 ? "خضراء ✔\n" : "حمراء ✘ ($FAIL ساقطة)\n");
exit($FAIL === 0 ? 0 : 1);
