<?php
/**
 * tools/repair01_w3_gate.php
 *   بوّابةُ المرحلةِ الثالثة — REPAIR01 · المفاتيحُ والكياناتُ الأمّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تُعيد القياسَ ولا تقرأ ما خزَّنَته الأداة** (‏_CONTEXT §قواعد القياس ١):
 *   المفاتيحُ تُقاس من `information_schema` · المعرّفاتُ البديلةُ تُمسح من
 *   المخطَّطِ الحيِّ ثم تُقارَن بالدفتر · الكياناتُ الأمُّ تُعدُّ من جداولِها ·
 *   والأحداثُ من `ems_business_events`. ولا حاجبَ واحدٌ يقرأ مخرَجَ
 *   `repair01_w3_apply.php`.
 *
 * ◆ **والحكمُ المخزَّنُ نفسُه مفحوصٌ**: `W3-03` لا يعدُّ صفوفَ `ALTERNATE_ID`
 *   فحسب — بل **يعيد اشتقاقَ الحكمِ** لكلِّ مرشَّحٍ من ثلاثةِ أرقامٍ حيّةٍ
 *   ويسقط إن خالف المخزَّنُ المقيسَ. فوسمُ جدولٍ حيٍّ «بذرةً بلا مرجع» يسقط
 *   لأنَّ القياسَ يكذّبه.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (§٣): الحواجبُ ترسو على أسماءِ الأصنافِ
 *   والأعمدةِ والقيودِ، وتُطبع برموزِها (`✘ W3-nn`).
 *
 * ◆ **و`W3-15` تُغلِّف رحلةَ الإثبات**: تشغّلها وتشترط عبورَ **كلِّ** محطّاتِها.
 *   فلا تُقبل المرحلةُ ببناءِ أسطحِها إن لم تعبر رحلتُها (§٦-أ).
 *
 * التشغيل: php tools/repair01_w3_gate.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
require_once $ROOT . '/tools/lib/repair01_w3_contracts.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
/* مرساةُ الطورِ صفرِ — **حقيقةٌ مسجَّلةٌ لا ثابتٌ حرفيّ** (RPR-AMD01) */
require_once __DIR__ . '/lib/repair01_w00_anchor.php';
$W00 = w00_anchors($conn);

while (ob_get_level()) { ob_end_clean(); }

$PASS = 0; $FAIL = 0; $LINES = array();
function w3_pad($s, $len)
{
    $n = mb_strlen($s, 'UTF-8');
    return $s . ($n < $len ? str_repeat(' ', $len - $n) : ' ');
}
function gate($code, $title, $ok, $detail)
{
    global $PASS, $FAIL, $LINES;
    if ($ok) { $PASS++; $mark = '✔'; } else { $FAIL++; $mark = '✘'; }
    $LINES[] = '  ' . $mark . ' ' . str_pad($code, 9) . w3_pad($title, 36) . $detail;
}
$one = function ($sql) use ($conn) { return repair01_w3_one($conn, $sql); };
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

echo "═══════════ بوّابةُ المرحلةِ الثالثة — REPAIR01 · المفاتيحُ والكياناتُ الأمّ ═══════════\n";

/* ══ W3-01 · المفاتيحُ الثلاثةَ عشرَ بمالكٍ واحدٍ — مقيسٌ من المخطَّط ══════ */
$keys = repair01_w3_keys();
$declared = count($keys);
$owned = 0; $badOwner = array(); $ownerSet = array(); $dupOwner = 0;
foreach ($keys as $code => $d) {
    $m = repair01_w3_measure_owner($conn, $d[2], $d[3], $d[6]);
    if ($m['table_ok'] === 1 && $m['pk_ok'] === 1) { $owned++; } else { $badOwner[] = $code; }
    $k = $d[2] . '.' . $d[3];
    if (isset($ownerSet[$k])) { $dupOwner++; } else { $ownerSet[$k] = $code; }
}
$inReg = (int) $one("SELECT COUNT(*) FROM repair01_key_registry");
$regMissing = 0;
foreach (array_keys($keys) as $code) {
    if ((int) $one("SELECT COUNT(*) FROM repair01_key_registry WHERE key_code = '" . $esc($code) . "'") !== 1) { $regMissing++; }
}
gate('W3-01', 'مفاتيحُ النطاقِ بمالكٍ واحدٍ مقيس',
     $owned === $declared && $dupOwner === 0 && $regMissing === 0 && $inReg === $declared,
     "مالكٌ مقيسٌ $owned/$declared · مالكٌ مشترَكٌ بين مفتاحَين $dupOwner · في السجلّ $inReg · ناقصٌ $regMissing"
     . ($badOwner ? ' ⇐ ' . implode('، ', $badOwner) : ''));

/* ══ W3-02 · لكلِّ مفتاحٍ قاعدةُ إنشاءٍ وقاعدةُ قراءةٍ بمرجعِهما ═══════════ */
$bareRule = (int) $one("SELECT COUNT(*) FROM repair01_key_registry
    WHERE create_rule = '' OR read_rule = '' OR create_rule_src = '' OR read_rule_src = ''
       OR owner_table = '' OR owner_column = '' OR owner_rule = '' OR src_ref = ''");
$notMeasured = (int) $one("SELECT COUNT(*) FROM repair01_key_registry WHERE owner_rule <> 'MEASURED_PK_OWNER'");
gate('W3-02', 'قاعدتا الإنشاءِ والقراءةِ بمرجعِهما', $bareRule === 0 && $notMeasured === 0,
     "مفتاحٌ بقاعدةٍ عاريةٍ $bareRule · مالكٌ غيرُ مقيسٍ $notMeasured");

/* ══ W3-03 · معرّفٌ بديلٌ للحقيقةِ نفسِها = 0 — والحكمُ يُعاد اشتقاقُه ═════ */
$live = repair01_w3_scan_aliases($conn);
$liveN = 0; $mismatch = array(); $liveAlt = 0;
foreach ($live as $key => $rows) {
    foreach ($rows as $a) {
        $liveN++;
        /* الحكمُ المُعادُ اشتقاقُه من الأرقامِ الحيّةِ الثلاثة */
        if ($a['rows'] === 0) { $want = 'SEED_NO_REFERENT'; }
        elseif ($a['rows_seed'] === $a['rows'] && $a['rows_resolvable'] === 0) { $want = 'SEED_NO_REFERENT'; }
        else { $want = 'ALTERNATE_ID'; $liveAlt++; }
        $got = (string) $one("SELECT verdict FROM repair01_key_alias
            WHERE key_code = '" . $esc($key) . "' AND alias_table = '" . $esc($a['table']) . "'
              AND alias_column = '" . $esc($a['column']) . "'");
        if ($got !== $want) { $mismatch[] = $a['table'] . '.' . $a['column'] . " (سُجّل $got · المقيس $want)"; }
    }
}
$storedN = (int) $one("SELECT COUNT(*) FROM repair01_key_alias WHERE alias_kind = 'LABEL_ONLY'");
$openAlt = (int) $one("SELECT COUNT(*) FROM repair01_key_alias
                        WHERE verdict = 'ALTERNATE_ID' AND resolved_at IS NULL");
gate('W3-03', 'معرّفٌ بديلٌ مفتوحٌ صفرٌ والحكمُ مقيس',
     $openAlt === 0 && count($mismatch) === 0 && $liveN === $storedN,
     "مرشَّحٌ حيٌّ $liveN · في الدفتر $storedN · حكمٌ يخالف المقيسَ " . count($mismatch)
     . " · معرّفٌ بديلٌ مفتوحٌ $openAlt"
     . ($mismatch ? ' ⇐ ' . implode('، ', array_slice($mismatch, 0, 2)) : ''));

/* ══ W3-04 · السجلُّ الثاني تابعٌ لا موازٍ — مقيسٌ من الجدولِ نفسِه ═══════ */
/* ⚠ **ولا يكفي «قوًى بلا وصلٍ = 0»**: القيدُ في القاعدةِ يضمنه بنيويًّا — وحاجبٌ
   يفحص ما يضمنه المخطَّطُ لا يسقط أبدًا مهما كُسر (تعريفُ الحاجبِ الأعمى · W02).
   فالقابلُ للكسرِ فعلًا **انحرافُ عددِ الموصولِ**: صفٌّ يُقطع وصلُه ويُخفَض إلى
   `IDENTITY_ONLY` يمرُّ من القيدِ سليمًا — ويُسقِط الوصلَ صامتًا. فيُقاس العددُ
   حيًّا ويُقارَن بما سجّله الدفترُ عند آخرِ حسم. */
$pRegs = repair01_w3_parallel_registers();
$pBad = 0; $pDetail = array();
foreach ($pRegs as $reg) {
    $m = repair01_w3_parallel_measure($conn, $reg);
    $recorded = (int) $one("SELECT rows_linked FROM repair01_key_alias
                             WHERE alias_table = '" . $esc($reg['table']) . "' AND alias_kind = 'PARALLEL_REGISTER'");
    if ($m['col_ok'] !== 1 || $m['in_use_unlinked'] !== 0 || $m['linked'] !== $recorded) { $pBad++; }
    $pDetail[] = $reg['table'] . ': مقامٌ ' . $m['rows'] . ' · موصولٌ حيًّا ' . $m['linked']
               . ' · في الدفتر ' . $recorded . ' · قوًى بلا وصلٍ ' . $m['in_use_unlinked'];
}
gate('W3-04', 'السجلُّ الثاني موصولٌ بلا انحراف', $pBad === 0, implode(' · ', $pDetail));

/* ══ W3-05 · DEC-OPEN-03: لا صفَّ في كيانٍ أمٍّ بلا Company_ID ═══════════ */
$mBad = 0; $mList = array(); $mN = 0;
$r = $conn->query("SELECT entity_code, table_name, company_column, rows_quarantined FROM repair01_master_entities");
while ($r && $x = $r->fetch_assoc()) {
    $mN++;
    if ($x['company_column'] === '') { continue; }              /* الجذرُ: الكيانُ نفسُه */
    $t = $x['table_name']; $c = $x['company_column'];
    if (!repair01_w3_table_exists($conn, $t) || repair01_w3_col_type($conn, $t, $c) === '') { $mBad++; $mList[] = $t; continue; }
    $inUse = repair01_w3_col_type($conn, $t, 'active') !== '' ? ' AND `active` = 1' : '';
    $n = (int) $one("SELECT COUNT(*) FROM `$t` WHERE (`$c` IS NULL OR `$c` = 0)$inUse");
    if ($n !== 0) { $mBad++; $mList[] = "$t($n)"; }
}
gate('W3-05', 'كيانٌ أمٌّ بلا Company_ID صفر', $mBad === 0 && $mN > 0,
     "كياناتٌ أمٌّ مقيسةٌ $mN · مخالفٌ $mBad" . ($mList ? ' ⇐ ' . implode('، ', $mList) : ''));

/* ══ W3-06 · الحارسُ منفَّذٌ في القاعدةِ — جسٌّ وظيفيٌّ لا قراءةُ فهرس ════ */
$g = repair01_w3_probe_person_guard($conn);
gate('W3-06', 'الحارسُ يمنع في القاعدةِ لا في الشاشة',
     $g['company_blocked'] === 1 && $g['link_blocked'] === 1,
     'إدراجٌ بلا كيانٍ ' . ($g['company_blocked'] ? 'مُنع' : '**مرّ**')
     . ' · قوًى بلا مفتاحٍ أمٍّ ' . ($g['link_blocked'] ? 'مُنع' : '**مرّ**')
     . ($g['error'] !== '' ? ' · ' . $g['error'] : ''));

/* ══ W3-07 · المحجورُ مُعلَنٌ لا صامت — المقيسُ = المُعلَنُ في القرار ════ */
$quarMeasured = (int) $one("SELECT COUNT(*) FROM persons WHERE company_id IS NULL");
$ambMeasured  = (int) $one("SELECT COUNT(*) FROM persons
                             WHERE employee_id IS NULL AND person_class = 'UNRESOLVED' AND company_id IS NOT NULL");
$declaredD1   = (int) $one("SELECT scope_rows FROM repair01_w3_decisions
                             WHERE decision_id = 'W3-D-01' AND rationale IS NOT NULL AND rationale <> ''");
gate('W3-07', 'المحجورُ والمعلَّقُ مُعلَنانِ بقرار', ($quarMeasured + $ambMeasured) === $declaredD1 && $declaredD1 > 0,
     "محجورٌ $quarMeasured · معلَّقٌ بلا حسمٍ $ambMeasured · مُعلَنٌ في W3-D-01 $declaredD1");

/* ══ W3-08 · نطاقُ المرحلةِ كاملٌ — المقامُ من السجلِّ لا من الدفتر ═══════ */
$reqN = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 3");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w3_scope");
$scopeBare = (int) $one("SELECT COUNT(*) FROM repair01_w3_scope WHERE map_rule = '' OR map_why = '' OR src_ref = ''");
$scopeOrphan = (int) $one("SELECT COUNT(*) FROM repair01_w3_scope s
    WHERE s.anchor_screen_id <> ''
      AND NOT EXISTS (SELECT 1 FROM repair01_screen_registry g WHERE g.screen_id = s.anchor_screen_id)");
gate('W3-08', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $scopeN === $reqN && $reqN > 0 && $scopeBare === 0 && $scopeOrphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $scopeBare · مِرساةٌ يتيمةٌ $scopeOrphan");

/* ══ W3-09 · الخطواتُ السبعُ لكلِّ سطحٍ في النطاق — المقامُ مُعادُ الاشتقاق ═ */
$codes = repair01_w3_scope_codes($conn);
$codesSql = "'" . implode("','", array_map($esc, $codes)) . "'";
$scopeScreens = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                             WHERE owner_code IN ($codesSql) AND on_disk = 1 AND route IS NOT NULL");
$sbN = (int) $one("SELECT COUNT(*) FROM repair01_w3_sidebar");
$sbBare = (int) $one("SELECT COUNT(*) FROM repair01_w3_sidebar
    WHERE s1_verdict = '' OR s1_rule = '' OR s2_verdict = '' OR s2_rule = ''
       OR s3_verdict = '' OR s3_rule = '' OR s4_verdict = '' OR s4_rule = ''
       OR s5_verdict = '' OR s5_rule = '' OR s6_verdict = '' OR s6_rule = ''
       OR s7_verdict = '' OR s7_rule = ''");
gate('W3-09', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح',
     $sbN === $scopeScreens && $scopeScreens > 0 && $sbBare === 0,
     "أسطحُ النطاقِ المُعادُ اشتقاقُها $scopeScreens · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBare");

/* ══ W3-10 · الخفضُ إلى تبويبٍ بمِرساةٍ مُثبَتةٍ وعذرٍ مكتوب ═════════════ */
/* ⚠ **ولا يُقاس بحكمِ آخرِ تشغيل**: الأداةُ عاديّةُ التشغيل، فالخفضُ يُوسَم
   `DEMOTED_TO_TAB` في الجولةِ التي نفّذته و`ALREADY_TAB` فيما بعد. فالمقامُ
   هنا **كلُّ سطحٍ خفضته هذه المرحلةُ** — يُشتقُّ من قاعدةِ الظهورِ في السجلِّ
   المعياريِّ (`W3_TAB_PROVEN_BY_ENTITY_TABS`) لا من حكمِ جولةٍ عابرة. */
$tabMap = repair01_w3_entity_tab_map($ROOT);
$demoted = array();
$r = $conn->query("SELECT screen_id, route FROM repair01_screen_registry
                    WHERE visibility_rule = 'W3_TAB_PROVEN_BY_ENTITY_TABS'");
while ($r && $x = $r->fetch_assoc()) { $demoted[$x['screen_id']] = $x['route']; }
$badDemote = array();
foreach ($demoted as $sid => $rt) {
    $rtE = $esc($rt); $navPred = repair01_w3_nav_pred($conn, $rt);
    $stillMenu = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active = 1");
    $excuse    = (int) $one("SELECT COUNT(*) FROM gov_nav_hidden_log WHERE ($navPred) AND doc_code = 'RPR-W03'");
    $inTabs    = isset($tabMap[strtolower($rt)]) ? 1 : 0;
    $renders   = repair01_w3_renders_tabs($ROOT, $rt);
    $vis       = (string) $one("SELECT visibility_class FROM repair01_screen_registry WHERE screen_id = '" . $esc($sid) . "'");
    if ($stillMenu !== 0 || $excuse === 0 || $inTabs !== 1 || $renders !== 1 || $vis !== 'TAB_CHILD') { $badDemote[] = $rt; }
}
/* وعكسُه: سطحٌ مُنِع خفضُه بقياسٍ **لا يجوز أن يكون قد خُفض** */
$wrongDemote = (int) $one("SELECT COUNT(*) FROM repair01_w3_sidebar s
    JOIN repair01_screen_registry g ON g.screen_id = s.screen_id
   WHERE s.s5_verdict IN ('TAB_CLAIM_UNPROVEN','TAB_BAR_NOT_RENDERED','DEMOTION_LOSES_ROLES')
     AND g.visibility_class = 'TAB_CHILD'");
/* ولا يُشترط وجودُ مخفوضٍ: المقيسُ أنّ **كلَّ ادّعاءِ أبٍ حُكم عليه** — والحكمُ
   إمّا خفضٌ مُثبَتٌ مُعذَّر، وإمّا منعٌ بقياسٍ **لم يُنفَّذ رغمَ المنع**. وحاجبٌ
   يشترط خفضًا يدفع إلى خفضٍ بلا برهانٍ ليخضرّ. */
$claims  = (int) $one("SELECT COUNT(*) FROM repair01_w3_sidebar WHERE s5_verdict <> 'NO_PARENT'");
$judged  = (int) $one("SELECT COUNT(*) FROM repair01_w3_sidebar
    WHERE s5_verdict IN ('DEMOTED_TO_TAB','ALREADY_TAB','TAB_CLAIM_UNPROVEN',
                         'TAB_BAR_NOT_RENDERED','DEMOTION_LOSES_ROLES')");
gate('W3-10', 'الخفضُ إلى تبويبٍ مُثبَتٌ ومُعذَّر',
     $claims === $judged && $claims > 0 && count($badDemote) === 0 && $wrongDemote === 0,
     "ادّعاءُ أبٍ محكومٌ $judged/$claims · مخفوضٌ بهذه المرحلةِ " . count($demoted)
     . ' · بلا مِرساةٍ أو عذرٍ ' . count($badDemote)
     . " · خُفِض رغمَ منعِ القياسِ $wrongDemote"
     . ($badDemote ? ' ⇐ ' . implode('، ', array_slice($badDemote, 0, 2)) : ''));

/* ══ W3-11 · الظهورُ بالصلاحيةِ لا بالإخفاء — مُعادُ القياسِ من الحيّ ════ */
$noPerm = array();
$r = $conn->query("SELECT screen_id, route FROM repair01_screen_registry
                   WHERE owner_code IN ($codesSql) AND on_disk = 1 AND route IS NOT NULL");
while ($r && $x = $r->fetch_assoc()) {
    $rtE = $esc($x['route']); $navPred = repair01_w3_nav_pred($conn, $x['route']);
    $rows = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active = 1");
    if ($rows === 0) { continue; }
    $coded = (int) $one("SELECT COUNT(*) FROM nav_items WHERE ($navPred) AND active = 1
                          AND permission_code IS NOT NULL AND permission_code <> ''");
    if ($coded < $rows) { $noPerm[] = $x['route']; }
}
gate('W3-11', 'كلُّ بندٍ في النطاقِ بصلاحيةٍ مُعلَنة', count($noPerm) === 0,
     'بندٌ بلا رمزِ صلاحيةٍ ' . count($noPerm)
     . (count($noPerm) ? ' ⇐ ' . implode('، ', array_slice($noPerm, 0, 3)) : ''));

/* ══ W3-12 · الربطُ بالسجلِّ المعياريِّ — مُعادُ الاشتقاقِ بالمسار ════════ */
$linkBad = 0; $linkN = 0;
$r = $conn->query("SELECT n.route, n.screen_id ns, g.screen_id gs
                     FROM nav_canonical n
                     JOIN repair01_screen_registry g ON g.route = n.route
                    WHERE g.owner_code IN ($codesSql) AND g.on_disk = 1");
while ($r && $x = $r->fetch_assoc()) { $linkN++; if ($x['ns'] !== $x['gs']) { $linkBad++; } }
gate('W3-12', 'كلُّ بندٍ مربوطٌ بـCanonical Screen_ID', $linkBad === 0 && $linkN > 0,
     "بندٌ معياريٌّ في النطاقِ $linkN · مربوطٌ خطأً أو غيرُ مربوطٍ $linkBad");

/* ══ W3-13 · حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ ═════════════════════════ */
$entMap = repair01_w3_entity_key_map();
$liveEv = array();
$r = $conn->query("SELECT DISTINCT event_key FROM ems_business_events
                   WHERE entity_type IN ('" . implode("','", array_map($esc, array_keys($entMap))) . "')");
while ($r && $x = $r->fetch_row()) { $liveEv[] = $x[0]; }
$noContract = array(); $thinContract = array();
foreach ($liveEv as $ek) {
    $n = (int) $one("SELECT COUNT(*) FROM repair01_events
                      WHERE event_code = '" . $esc($ek) . "' AND contract_status = 'RECORDED'");
    if ($n === 0) { $noContract[] = $ek; continue; }
    $thin = (int) $one("SELECT COUNT(*) FROM repair01_events
        WHERE event_code = '" . $esc($ek) . "' AND contract_status = 'RECORDED'
          AND (trigger_rule = '' OR min_payload = '' OR consumer_list IS NULL OR consumer_list = ''
               OR consumer_effect IS NULL OR consumer_effect = '' OR preconditions = ''
               OR failure_policy = '' OR compensation = '' OR idempotency_key = '' OR src_ref = '')");
    if ($thin > 0) { $thinContract[] = $ek; continue; }
    /* والمستهلِكُ في العقدِ = المستهلِكُ الحيُّ عددًا — لا قائمةٌ مجمَّدة */
    $m = repair01_w3_measure_consumers($conn, $ek);
    $stored = (string) $one("SELECT consumer_list FROM repair01_events
                              WHERE event_code = '" . $esc($ek) . "' AND contract_status = 'RECORDED' LIMIT 1");
    $storedN = count(array_filter(explode("\n", (string) $stored)));
    if ($storedN !== count($m['consumers'])) { $thinContract[] = $ek . ' (مستهلكونَ ' . $storedN . '≠' . count($m['consumers']) . ')'; }
}
gate('W3-13', 'حدثٌ حيٌّ بلا عقدِ أثرٍ صفر', count($noContract) === 0 && count($thinContract) === 0 && count($liveEv) > 0,
     'حدثٌ حيٌّ على كيانٍ أمّ ' . count($liveEv) . ' · بلا عقدٍ ' . count($noContract)
     . ' · عقدٌ ناقصٌ ' . count($thinContract)
     . ($noContract ? ' ⇐ ' . implode('، ', array_slice($noContract, 0, 2)) : '')
     . ($thinContract ? ' ⇐ ' . implode('، ', array_slice($thinContract, 0, 2)) : ''));

/* ══ W3-14 · مخزنُ W00/W01/W02 لم يُمَسَّ بهذه المرحلة ════════════════════ */
$d0 = (int) $one("SELECT COUNT(*) FROM repair01_decisions");
$s0 = (int) $one("SELECT COUNT(*) FROM repair01_source_files");
$u0 = (int) $one("SELECT COUNT(*) FROM repair01_surfaces");
/* ⚠ **إصلاحُ مقامٍ لا تخفيفُ حاجب** (RPR-PATCH-02 · 2026-08-25): كان الشرطُ
   `COUNT(*) = 651` على السجلِّ كلِّه — فيسقط الحاجبُ لمجرَّدِ **تسجيلِ شاشةٍ
   جديدة**، وهو غايةُ الحملةِ لا انتهاكُها. و٣٣٤ سطحًا تنتظر البناءَ في
   W05…W14، فكلُّ واحدٍ منها كان يكسر هذا الحاجبَ وحاجبَ W4-15 معه.
   والمقصودُ «أساسُ W00/W02 لم يُمَسّ»، وأساسُه يُعرَف بـ`origin` الثلاثة.
   فالحاجبُ الآن **أشدُّ في ثلاثةِ وجوه**: يسقط على حذفِ صفِّ أساسٍ (العددُ
   ينقص)، وعلى تحويلِ صفِّ أساسٍ إلى نموٍّ (العددُ ينقص)، وعلى **نموٍّ بلا
   ختمِ موجة** — وهو ما لم يكن يفحصه أصلًا. والنمطُ نفسُه الذي طبّقته W04
   على `repair01_events.contract_stage`. */
$BASE_ORIGINS = "'SURFACES','DISK','NAV'";
$g0    = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin IN ($BASE_ORIGINS)");
$gNew  = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin NOT IN ($BASE_ORIGINS)");
$gWild = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                      WHERE origin NOT IN ($BASE_ORIGINS) AND origin NOT REGEXP '^W[0-9]{2}$'");
$t0 = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = ''");
/* ⚠ **إصلاحُ مقامٍ لا تخفيفُ حاجب** (RPR-W04): كان الشرطُ `contract_stage <> 'W03'`
   — وهو يعدُّ **عقودَ المراحلِ التالية** أحداثًا للدراسةِ فيسقط الحاجبُ لمجرَّدِ
   أنَّ W04 كتبت عقودَها. والمقصودُ «أحداثُ الدراسةِ الـ٦٣٢ لم تُمَسّ»، ومَن لم
   تُمَسَّ يحمل `contract_stage = ''`. فالمقامُ يُقاس بما يعنيه لا بنفيِ مرحلةٍ
   واحدة — وأُضيف معه أنَّ عقودَ W03 الثلاثةَ عشرَ باقيةٌ، فالحاجبُ **أشدُّ**
   لا أخفّ: يسقط الآن على الحذفِ كما يسقط على التلويث. */
$e0 = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = ''");
$e3 = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = 'W03'");
gate('W3-14', 'أساسُ المراحلِ السابقةِ لم يُمَسّ',
     $d0 === $W00['decisions'] && $s0 === $W00['source_files'] && $u0 === $W00['surfaces'] && $g0 === $W00['registry_base'] && $gWild === 0
     && $t0 === $W00['gaps_original'] && $e0 === $W00['events_study'] && $e3 === 13,
     "قرارات $d0 · مصادر $s0 · أسطح $u0 · أساسُ السجلّ $g0 · نموٌّ مختومٌ $gNew · نموٌّ بلا ختمٍ $gWild"
     . " · فجواتٌ أصليّة $t0 · أحداثُ الدراسة $e0 · عقودُ W03 $e3");

/* ══ W3-15 · رحلةُ الإثبات — تُشغَّل هنا ويُشترط عبورُها كاملةً ═══════════ */
$jOut = array(); $jCode = 1;
@exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_w3_journey.php" 2>&1', $jOut, $jCode);
$run = (string) $one("SELECT run_id FROM repair01_w3_journey ORDER BY id DESC LIMIT 1");
$jTotal = (int) $one("SELECT COUNT(*) FROM repair01_w3_journey WHERE run_id = '" . $esc($run) . "'");
$jPass  = (int) $one("SELECT COUNT(*) FROM repair01_w3_journey WHERE run_id = '" . $esc($run) . "' AND passed = 1");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w3_journey
                       WHERE run_id = '" . $esc($run) . "' AND (business_effect = '' OR business_effect = '—')");
$jStations = (int) $one("SELECT COUNT(DISTINCT station_no) FROM repair01_w3_journey WHERE run_id = '" . $esc($run) . "'");
gate('W3-15', 'رحلةُ المفتاحِ تعبر بمحطّاتِها',
     $jCode === 0 && $jTotal > 0 && $jPass === $jTotal && $jStations >= 4 && $jNoEff === 0,
     "الجولة $run · عابرٌ $jPass/$jTotal · محطّاتٌ متمايزةٌ $jStations · بلا أثرٍ تجاريٍّ مقيسٍ $jNoEff"
     . ($jCode !== 0 ? ' · رمزُ الخروج ' . $jCode : ''));

/* ══ W3-16 · التركيبةُ الممنوعةُ مقيسةٌ ومقفلةُ الاتّجاه ═══════════════════
   ◆ **الوقوعُ الحيُّ يُقاس ولا يُدهَس**: `sec_sod_pairs` يحمل ثلاثةَ عشرَ زوجًا
     كلُّها ماليّةٌ — ولا واحدٌ من تركيباتِ هذا النطاق. فالمنعُ الحيُّ غيرُ قائمٍ
     بعدُ (W13)، والقابلُ للفعلِ الآنَ **قفلُ الاتّجاه**: العددُ لا يزيد.
   ◆ والعمودانِ في الصفِّ نفسِه (`entered_by`/`qty_decided_by` ·
     `technician_id`/`supervisor_id`) فالقياسُ ببيانِ الصفِّ لا بسلسلةٍ خارجيّة. */
$sodUnit = (int) $one("SELECT COUNT(*) FROM unit_entries
                        WHERE entered_by = qty_decided_by AND qty_decided_by IS NOT NULL");
$sodMnt  = (int) $one("SELECT COUNT(*) FROM mnt_order
                        WHERE technician_id = supervisor_id AND technician_id IS NOT NULL");
$sodDecl = (int) $one("SELECT scope_rows FROM repair01_w3_decisions
                        WHERE decision_id = 'W3-D-05' AND rationale IS NOT NULL AND rationale <> ''");
gate('W3-16', 'التركيبةُ الممنوعةُ مقفلةُ الاتّجاه', ($sodUnit + $sodMnt) <= $sodDecl && $sodDecl > 0,
     "مُدخِلٌ يحكم على كميّتِه $sodUnit · فنّيٌّ يشهد على عملِه $sodMnt · المُعلَنُ في W3-D-05 $sodDecl");

echo implode("\n", $LINES) . "\n";
echo str_repeat('─', 92) . "\n";
$keyOwned = $owned . '/' . $declared;
printf("W3 gate: %d/%d  ·  مفاتيح %s بمالكٍ واحد  ·  معرّفٌ بديلٌ %d  ·  كيانٌ أمٌّ بلا company_id %d  ·  رحلةٌ %d/%d\n",
    $PASS, $PASS + $FAIL, $keyOwned, $openAlt, $mBad, $jPass, $jTotal);
echo 'الحكم: ' . ($FAIL === 0 ? "خضراء ✔\n" : "حمراء ✘\n");
exit($FAIL === 0 ? 0 : 1);
