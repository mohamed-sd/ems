<?php
/**
 * tools/repair01_w12_gate.php — بوّابةُ المرحلةِ الثانيةَ عشرة (التمويلُ والممولون)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ حاجبٍ يُعيد القياسَ ولا يقرأ ما خزّنَته أداةُ الاشتقاق** — والمقامُ
 *   يُعاد بناؤه في كلِّ تشغيل.
 *
 * ◆ **وحارسُ الخلاءِ مبنيٌّ من البداية**: مجموعةٌ خاويةٌ تُخضِرُّ الحاجبَ على
 *   «تطابقِ لا شيء» — وهو النمطُ الذي كلَّف W01 و W07 و W09 جولاتِ إصلاح.
 *   فالصفرُ يمرُّ **مُعلَنًا بقرارٍ وحدَه** (`W12-D-07`) ويسقط بلا إعلان.
 *
 * ◆ **ومحورا المرحلةِ يُقاسان بنيويًّا**: `إقفالٌ واحدٌ يخدم معنيَين` و
 *   `تصميمٌ مقيَّدٌ ببياناتٍ تاريخية` — كلٌّ على خمسِ جبهاتٍ تُعاد في كلِّ نداء.
 *
 * التشغيل: php tools/repair01_w12_gate.php
 * الخروج : 0 كلُّ الحواجبِ خضراء · 1 حاجبٌ ساقط
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w12_scan.php';
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
$one = function ($sql) use ($conn) { return repair01_w12_one($conn, $sql); };

$pass = 0; $fail = 0; $rows = array();
function gate($id, $title, $ok, $detail)
{
    global $pass, $fail, $rows;
    if ($ok) { $pass++; } else { $fail++; }
    $rows[] = array($id, $title, $ok, $detail);
}

echo "═══════ بوّابةُ المرحلةِ الثانيةَ عشرة — REPAIR01 · التمويلُ والممولون ═══════\n";

/* ── حارسُ الخلاءِ: الصفرُ يمرُّ مُعلَنًا وحدَه ────────────────────────── */
$emptyDeclared = (int) $one("SELECT COUNT(*) FROM repair01_w12_decisions
                              WHERE decision_id = 'W12-D-07' AND COALESCE(rationale, '') <> ''");
$vac = function ($n) use ($emptyDeclared) { return ((int) $n === 0 && $emptyDeclared === 0); };
$vacTag = $emptyDeclared ? 'خلاءٌ مُعلَنٌ في W12-D-07 ✔' : '**خلاءٌ غيرُ مُعلَن**';

$ANCH = repair01_w12_anchors();
$NEW  = repair01_w12_new_surfaces();

/* ══ W12-01 · كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع ═══════════════════════════ */
$reqN   = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 12");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w12_scope");
$noRule = (int) $one("SELECT COUNT(*) FROM repair01_w12_scope WHERE map_rule = '' OR map_why = ''");
$orphan = (int) $one("SELECT COUNT(*) FROM repair01_w12_scope s
                       LEFT JOIN repair01_requirements r ON r.requirement_id = s.requirement_id
                                                        AND r.stage_no = 12
                      WHERE r.requirement_id IS NULL");
gate('W12-01', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $reqN > 0 && $scopeN === $reqN && $noRule === 0 && $orphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $noRule · مِرساةٌ يتيمةٌ $orphan");

/* ══ W12-02 · المِرساةُ مُثبَتةٌ من القرصِ لا مُعلَنةٌ فقط ══════════════ */
$proven = 0; $unproven = array();
foreach ($ANCH as $rid => $a) {
    $pr = repair01_w12_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $proven++; } else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }
}
$bookProven = (int) $one("SELECT COUNT(*) FROM repair01_w12_scope WHERE map_rule LIKE 'W12_ROUTE%'");
gate('W12-02', 'المِرساةُ مُثبَتةٌ من القرصِ والدفترُ يطابق المقيس',
     $proven === count($ANCH) && count($unproven) === 0 && $bookProven === $proven,
     'مُثبَتةٌ ' . $proven . ' من ' . count($ANCH) . ' · في الدفتر ' . $bookProven
     . ' · لم تُثبَت ' . count($unproven)
     . (count($unproven) ? ' ⇐ ' . implode('، ', array_slice($unproven, 0, 3)) : ''));

/* ══ W12-03 · مالكُ السطحِ يطابق مالكَ المتطلَّب ═══════════════════════ */
$mis = (int) $one("SELECT COUNT(*) FROM repair01_w12_scope WHERE owner_verdict = 'MISMATCH'");
gate('W12-03', 'صفرُ سطحٍ تحت مالكٍ غيرِ مالكِ متطلَّبِه', $mis === 0,
     "سطحٌ بمالكٍ مخالفٍ $mis");

/* ══ W12-04 · سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح ═══════════════════════ */
$routes = array();
foreach ($ANCH as $a) { if ($a['route'] !== '') { $routes[$a['route']] = true; } }
$sbN   = (int) $one("SELECT COUNT(*) FROM repair01_w12_sidebar");
$sbBad = (int) $one("SELECT COUNT(*) FROM repair01_w12_sidebar
                      WHERE s1_verdict='' OR s1_rule='' OR s2_verdict='' OR s2_rule=''
                         OR s3_verdict='' OR s3_rule='' OR s4_verdict='' OR s4_rule=''
                         OR s5_verdict='' OR s5_rule='' OR s6_verdict='' OR s6_rule=''
                         OR s7_verdict='' OR s7_rule=''");
gate('W12-04', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح', $sbN === count($routes) && $sbBad === 0,
     'أسطحُ النطاقِ المُعادُ اشتقاقُها ' . count($routes) . " · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBad");

/* ══ W12-05 · الاسمُ والمجموعةُ **صُحِّحا** لا قِيسا فقط ═════════════════ */
$lblDrift = (int) $one("SELECT COUNT(*) FROM repair01_w12_sidebar WHERE s2_verdict <> 'LABEL_MATCH'");
$grpDrift = (int) $one("SELECT COUNT(*) FROM repair01_w12_sidebar WHERE s3_verdict <> 'GROUP_MATCH'");
gate('W12-05', 'الاسمُ والمجموعةُ صُحِّحا على السجلِّ ودورةِ العمل',
     $lblDrift === 0 && $grpDrift === 0 && $sbN > 0,
     "انحرافُ اسمٍ $lblDrift · انحرافُ مجموعةٍ $grpDrift · المقامُ $sbN");

/* ══ W12-06 · حارسُ عرضٍ ومنحُ صلاحيةٍ مقيسانِ لكلِّ سطح ════════════════ */
$noGrant = (int) $one("SELECT COUNT(*) FROM repair01_w12_sidebar WHERE s6_perm_rows = 0");
$noGuard = array();
foreach (array_keys($routes) as $rt) {
    $g = repair01_w12_guard_of($ROOT, $rt);
    if ($g['kind'] === 'NONE') { $noGuard[] = $rt; }
}
gate('W12-06', 'حارسُ عرضٍ ومنحٌ مقيسانِ لكلِّ سطح', count($noGuard) === 0 && $noGrant === 0,
     'سطحٌ بلا حارسٍ ' . count($noGuard) . " · سطحٌ بلا منحٍ $noGrant"
     . (count($noGuard) ? ' ⇐ ' . implode('، ', array_slice($noGuard, 0, 2)) : ''));

/* ══ W12-07 · الترتيبُ من دورةِ العملِ لا من الأبجديّة ═══════════════════
   ◆ **يُعاد اشتقاقُ الموضعِ من الدورةِ ويُقارَن بالمخزَّن** — فترتيبٌ أبجديٌّ
     يضع الإقفالَ النهائيَّ قبل التعاقديِّ ويضع أوامرَ الدفعِ قبل الأقساط. */
$noOrder = (int) $one("SELECT COUNT(*) FROM repair01_w12_sidebar WHERE s4_verdict <> 'ORDER_FROM_CYCLE'");
$stepMismatch = 0; $stepOf = array();
foreach ($ANCH as $rid => $a) {
    if ($a['route'] === '') { continue; }
    if (!isset($stepOf[$a['route']]) || (int) $a['step'] < $stepOf[$a['route']]) {
        $stepOf[$a['route']] = (int) $a['step'];
    }
}
foreach ($stepOf as $rt => $st) {
    $got = (int) $one("SELECT s4_cycle_step FROM repair01_w12_sidebar WHERE route = '" . $esc($rt) . "'");
    if ($got !== $st) { $stepMismatch++; }
}
/* والترتيبُ **صاعدٌ مع الدورةِ** في السجلِّ المعياريِّ لا معكوسٌ ولا أبجديّ */
$inversions = 0; $prevSort = -1;
$rq = $conn->query("SELECT s.route, s.s4_cycle_step, c.sort_no
                      FROM repair01_w12_sidebar s
                      JOIN nav_canonical c ON c.route = s.route
                     ORDER BY s.s4_cycle_step");
$seenSort = array();
while ($rq && $x = $rq->fetch_assoc()) { $seenSort[] = (int) $x['sort_no']; }
for ($i = 1; $i < count($seenSort); $i++) { if ($seenSort[$i] < $seenSort[$i - 1]) { $inversions++; } }
gate('W12-07', 'الترتيبُ من دورةِ العملِ لا من الأبجديّة',
     $noOrder === 0 && $stepMismatch === 0 && $inversions === 0,
     "بلا مصدرِ ترتيبٍ $noOrder · موضعٌ يخالف المُعادَ اشتقاقُه $stepMismatch · انعكاسٌ في التصاعد $inversions");

/* ══ W12-08 · النموُّ مختومٌ والأساسُ لم يُمَسّ (RPR-PATCH-02) ═══════════ */
$base   = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                       WHERE origin IN ('SURFACES','DISK','NAV')");
$w12N   = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin = 'W12'");
$unsealed = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                         WHERE origin NOT IN ('SURFACES','DISK','NAV')
                           AND origin NOT REGEXP '^W[0-9]{2}$'");
gate('W12-08', 'النموُّ مختومٌ بموجتِه والأساسُ مُجمَّد',
     $base === 651 && $w12N === count($NEW) && $unsealed === 0,
     "أساسٌ $base (يجب 651) · نموُّ W12 $w12N من " . count($NEW) . " · نموٌّ بلا ختمٍ $unsealed");

/* ══ W12-09 · **إقفالٌ واحدٌ يخدم معنيَين — صفر** ═══════════════════════ */
$alien   = repair01_w12_alien_kind_rows($conn);
$notCal  = repair01_w12_monthly_not_calendar($conn);
$noPer   = repair01_w12_contract_without_period($conn);
$dupFin  = repair01_w12_final_duplicated($conn);
$dualCon = repair01_w12_consumer_dual_kind($conn);
$dual    = $alien + $notCal + $noPer + $dupFin + $dualCon;
gate('W12-09', 'إقفالٌ واحدٌ يخدم معنيَين — صفر', $dual === 0,
     "صنفٌ غريبٌ $alien · شهريٌّ ليس تقويميًّا $notCal · تعاقديٌّ بلا فترةٍ $noPer"
     . " · نهائيٌّ مكرَّرٌ $dupFin · غرضٌ يقرأ صنفَين $dualCon");

/* ══ W12-10 · **تصميمٌ مقيَّدٌ ببياناتٍ تاريخية — صفر** ═════════════════ */
$legIn   = repair01_w12_legacy_in_future($conn);
$capDown = repair01_w12_capability_downgraded($conn);
$colNull = repair01_w12_future_col_nullable($conn);
$allocNo = repair01_w12_allocation_without_order($conn);
$legNoEv = repair01_w12_legacy_without_evidence($conn);
$constr  = $legIn + $capDown + $colNull + $allocNo + $legNoEv;
gate('W12-10', 'تصميمٌ مقيَّدٌ ببياناتٍ تاريخية — صفر', $constr === 0,
     "تاريخيٌّ في نموذجِ المستقبلِ $legIn · قدرةٌ مخفَّضةٌ $capDown · عمودٌ يقبل العدمَ $colNull"
     . " · تخصيصٌ بلا أمرٍ $allocNo · مجمَّعٌ بلا حجّيّةٍ $legNoEv");

/* ══ W12-11 · المِرساةُ بلا كيانٍ إلزاميٍّ مُعلَنةٌ بقرارِها ═══════════ */
$unscoped = (int) $one("SELECT COUNT(*) FROM repair01_w12_scope
                         WHERE entity_scoped = 0 AND anchor_probe <> ''
                           AND map_rule = 'W12_ROUTE_TOUCHES_TABLE'");
$unscopedDec = (int) $one("SELECT scope_rows FROM repair01_w12_decisions WHERE decision_id = 'W12-D-09'");
gate('W12-11', 'المِرساةُ بلا كيانٍ إلزاميٍّ مُعلَنةٌ بعددِها في قرارِها',
     $unscoped === $unscopedDec,
     "مِرساةٌ بجدولٍ بلا كيانٍ إلزاميٍّ $unscoped · المُعلَنُ في W12-D-09 $unscopedDec");

/* ══ W12-12 · جداولُ النطاقِ تحمل الكيانَ غيرَ قابلٍ للعدم ═════════════ */
$noEnt = array();
foreach (repair01_w12_entity_tables() as $t) {
    if (!repair01_w12_entity_scoped($conn, $t)) { $noEnt[] = $t; }
}
$liveNoEnt = array();
foreach (repair01_w12_live_entity_tables() as $t) {
    if (!repair01_w12_entity_scoped($conn, $t)) { $liveNoEnt[] = $t; }
}
gate('W12-12', 'كلُّ جدولٍ في النطاقِ بكيانٍ قانونيٍّ إلزاميّ',
     count($noEnt) === 0 && count($liveNoEnt) === 0,
     'جداولُ المرحلة ' . (count(repair01_w12_entity_tables()) - count($noEnt)) . '/'
     . count(repair01_w12_entity_tables()) . ' · حيّةٌ '
     . (count(repair01_w12_live_entity_tables()) - count($liveNoEnt)) . '/'
     . count(repair01_w12_live_entity_tables())
     . (count($noEnt) || count($liveNoEnt) ? ' ⇐ ' . implode('، ', array_merge($noEnt, $liveNoEnt)) : ''));

/* ══ W12-13 · قيودُ فصلِ الأصنافِ قائمةٌ في القاعدة ════════════════════ */
$CK = array(
    array('fin_contract_close', 'chk_fcon_kind'), array('fin_contract_close', 'chk_fcon_period'),
    array('fin_monthly_close', 'chk_fmc_kind'), array('fin_monthly_close', 'chk_fmc_month'),
    array('fin_final_close', 'chk_ffin_kind'), array('fin_final_close', 'chk_ffin_appr'),
    array('fin_close_link', 'chk_fcl_self'), array('fin_close_link', 'chk_fcl_pair'),
);
$ckMiss = array();
foreach ($CK as $c) { if (!repair01_w12_check_exists($conn, $c[0], $c[1])) { $ckMiss[] = $c[1]; } }
$uqFin = (int) $one("SELECT COUNT(*) FROM information_schema.STATISTICS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fin_final_close'
                        AND INDEX_NAME = 'uq_ffin_grain' AND NON_UNIQUE = 0");
gate('W12-13', 'الفصلُ بين الأصنافِ مفروضٌ في القاعدةِ لا بالتوثيق',
     count($ckMiss) === 0 && $uqFin > 0,
     'قيودٌ قائمة ' . (count($CK) - count($ckMiss)) . '/' . count($CK)
     . ' · مفتاحُ النهائيِّ الفريد ' . ($uqFin > 0 ? 'قائم' : 'مفقود')
     . (count($ckMiss) ? ' ⇐ ' . implode('، ', $ckMiss) : ''));

/* ══ W12-14 · قيودُ فصلِ الطبقتَين قائمةٌ في القاعدة ═══════════════════ */
$LK = array(
    array('fin_payment_order', 'chk_fpo_future'), array('fin_payment_order', 'chk_fpo_req'),
    array('fin_payment_order', 'chk_fpo_appr'), array('fin_payment_order', 'chk_fpo_sod'),
    array('fin_payment_order', 'chk_fpo_exec'),
    array('fin_legacy_payment_aggregate', 'chk_flpa_layer'),
    array('fin_legacy_payment_aggregate', 'chk_flpa_evid'),
    array('fin_legacy_payment_aggregate', 'chk_flpa_alloc'),
    array('fin_payment_allocation', 'chk_fpa_order'),
);
$lkMiss = array();
foreach ($LK as $c) { if (!repair01_w12_check_exists($conn, $c[0], $c[1])) { $lkMiss[] = $c[1]; } }
gate('W12-14', 'فصلُ نموذجِ المستقبلِ عن الطبقةِ التاريخيّةِ مفروضٌ في القاعدة',
     count($lkMiss) === 0,
     'قيودٌ قائمة ' . (count($LK) - count($lkMiss)) . '/' . count($LK)
     . (count($lkMiss) ? ' ⇐ ' . implode('، ', $lkMiss) : ''));

/* ══ W12-15 · الإقفالاتُ الثلاثةُ جداولٌ متمايزةٌ بحبّاتٍ متمايزة ══════ */
$tblMiss = array();
foreach (array_keys(repair01_w12_close_tables()) as $t) {
    if (!repair01_w12_table_exists($conn, $t)) { $tblMiss[] = $t; }
}
$grainMiss = array();
if (!repair01_w12_col_exists($conn, 'fin_contract_close', 'contract_period_no')) { $grainMiss[] = 'contract_period_no'; }
if (!repair01_w12_col_exists($conn, 'fin_monthly_close', 'accounting_month')) { $grainMiss[] = 'accounting_month'; }
if (!repair01_w12_col_exists($conn, 'fin_final_close', 'last_periodic_close_id')) { $grainMiss[] = 'last_periodic_close_id'; }
$closeRows = (int) $one("SELECT COUNT(*) FROM fin_contract_close")
           + (int) $one("SELECT COUNT(*) FROM fin_monthly_close")
           + (int) $one("SELECT COUNT(*) FROM fin_final_close");
gate('W12-15', 'ثلاثةُ إقفالاتٍ ثلاثةُ جداولَ بثلاثِ حبّات',
     count($tblMiss) === 0 && count($grainMiss) === 0 && !$vac($closeRows),
     'جداولُ الإقفالِ ' . (3 - count($tblMiss)) . '/3 · أعمدةُ الحبّةِ ' . (3 - count($grainMiss))
     . '/3 · صفوفٌ حيّةٌ ' . $closeRows . ' (' . $vacTag . ')'
     . (count($tblMiss) || count($grainMiss) ? ' ⇐ ' . implode('، ', array_merge($tblMiss, $grainMiss)) : ''));

/* ══ W12-16 · ترحيلُ الرصيدِ بين الفتراتِ سليم ═════════════════════════ */
$rollBad = repair01_w12_rollforward_broken($conn);
gate('W12-16', 'الافتتاحيُّ يساوي ختاميَّ الفترةِ السابقة', $rollBad === 0,
     "سلسلةُ ترحيلٍ مكسورةٌ $rollBad");

/* ══ W12-17 · الشهريُّ يضمُّ التعاقديَّ ولا يخترع معناه ═══════════════ */
$mNoLink = repair01_w12_monthly_without_contract_link($conn);
gate('W12-17', 'شهريٌّ معتمَدٌ بلا تعاقديٍّ مربوطٍ — صفر', $mNoLink === 0,
     "شهريٌّ معتمَدٌ عددُه لا يطابق روابطَه $mNoLink");

/* ══ W12-18 · النهائيُّ لا يُعتمَد فوق ذمّةٍ أو انحرافٍ أو بلا إخلاء ═══ */
$finBad = repair01_w12_final_over_open_deviation($conn);
gate('W12-18', 'نهائيٌّ معتمَدٌ فوق استحقاقٍ أو انحرافٍ أو بلا إخلاءِ طرف — صفر',
     $finBad === 0, "اقفالٌ نهائيٌّ معتمَدٌ بموقفٍ غيرِ مستوفًى $finBad");

/* ══ W12-19 · لا عتبةٌ رقميّةٌ صلبةٌ في أدواتِ النطاقِ وخدمتِه ═════════ */
$hard = repair01_w12_hardcoded_thresholds($ROOT);
$thN  = (int) $one("SELECT COUNT(*) FROM repair01_w12_thresholds");
$thBad = (int) $one("SELECT COUNT(*) FROM repair01_w12_thresholds WHERE why = '' OR decision_ref = ''");
gate('W12-19', 'العتبةُ من السجلِّ ولا رقمَ صلبٌ في شيفرةِ النطاق',
     count($hard) === 0 && $thN > 0 && $thBad === 0,
     'مقارنةٌ صلبةٌ ' . count($hard) . " · عتباتٌ مسجَّلةٌ $thN · بلا سببٍ أو قرارٍ $thBad"
     . (count($hard) ? ' ⇐ ' . implode('، ', array_slice($hard, 0, 3)) : ''));

/* ══ W12-20 · النطاقُ يطلب الاعترافَ ولا يكتب قيدًا (§48) ══════════════ */
$writers = repair01_w12_scope_writes_journal($ROOT);
$doorOk  = repair01_w12_table_exists($conn, 'acc_recognition_request');
$svcAsks = false;
$svcPath = $ROOT . '/app/Services/Financing/FinancingCycleService.php';
if (is_file($svcPath)) {
    $src = (string) file_get_contents($svcPath);
    $svcAsks = (strpos($src, 'requestRecognition') !== false
             && strpos($src, 'EXECUTE_WITHOUT_RECOGNITION_REQUEST') !== false);
}
gate('W12-20', 'النطاقُ يصدر طلبَ اعترافٍ ولا يكتب قيدًا',
     count($writers) === 0 && $doorOk && $svcAsks,
     'ملفٌّ في النطاقِ يكتب دفترَ القيد ' . count($writers)
     . ' · بابُ الاعترافِ ' . ($doorOk ? 'قائم' : 'مفقود')
     . ' · الخدمةُ تطلب وتردُّ بلا طلبٍ ' . ($svcAsks ? 'نعم' : 'لا')
     . (count($writers) ? ' ⇐ ' . implode('، ', array_slice($writers, 0, 2)) : ''));

/* ══ W12-21 · أمرٌ منفَّذٌ بلا طلبِ اعترافٍ — صفر ══════════════════════ */
$execNoRec = repair01_w12_executed_without_recognition($conn);
$execN = (int) $one("SELECT COUNT(*) FROM fin_payment_order WHERE state = 'executed'");
gate('W12-21', 'أمرٌ منفَّذٌ بلا طلبِ اعترافٍ — صفر',
     $execNoRec === 0 && !$vac($execN),
     "منفَّذٌ بلا طلبِ اعترافٍ $execNoRec · أوامرُ منفَّذةٌ $execN (" . $vacTag . ')');

/* ══ W12-22 · آلةُ حالةٍ لكلِّ كيانٍ رئيسيٍّ بممنوعٍ صريحٍ مُسبَّب ═════ */
$stEnt   = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w12_states");
$stTr    = (int) $one("SELECT COUNT(*) FROM repair01_w12_states");
$stForb  = (int) $one("SELECT COUNT(*) FROM repair01_w12_states WHERE allowed = 0");
$stNoWhy = (int) $one("SELECT COUNT(*) FROM repair01_w12_states WHERE allowed = 0 AND forbid_why = ''");
$stThin  = (int) $one("SELECT COUNT(*) FROM repair01_w12_states
                        WHERE allowed = 1 AND (owner_role = '' OR preconditions = ''
                              OR output_doc = '' OR approval_gate = ''
                              OR reopen_rule = '' OR correct_rule = '')");
/* **وكلُّ كيانٍ رئيسيٍّ في النطاقِ له آلةٌ** — والثلاثةُ الحاكمةُ لا تُستثنى */
$mustHave = array('fin_contract_close', 'fin_monthly_close', 'fin_final_close', 'fin_payment_order',
                  'fin_legacy_payment_aggregate', 'fin_finance_contract');
$stMiss = array();
foreach ($mustHave as $e) {
    $n = (int) $one("SELECT COUNT(*) FROM repair01_w12_states WHERE entity = '" . $esc($e) . "'");
    if ($n === 0) { $stMiss[] = $e; }
}
gate('W12-22', 'آلةُ حالةٍ لكلِّ كيانٍ رئيسيٍّ بممنوعٍ صريحٍ مُسبَّب',
     $stEnt >= 8 && $stForb > 0 && $stNoWhy === 0 && $stThin === 0 && count($stMiss) === 0,
     "كياناتٌ $stEnt · انتقالاتٌ $stTr · ممنوعٌ صراحةً $stForb · بلا سببٍ $stNoWhy"
     . " · مسموحٌ ناقصُ الحقولِ $stThin · كيانٌ بلا آلةٍ " . count($stMiss)
     . (count($stMiss) ? ' ⇐ ' . implode('، ', $stMiss) : ''));

/* ══ W12-23 · فصلُ الواجباتِ بستّةِ أدوارٍ ورمزِ ردٍّ مقيسٍ من القرص ══ */
$sodN   = (int) $one("SELECT COUNT(*) FROM repair01_w12_sod");
$sodBad = (int) $one("SELECT COUNT(*) FROM repair01_w12_sod
                       WHERE initiator_role='' OR reviewer_role='' OR approver_role=''
                          OR executor_role='' OR closer_role='' OR forbidden_combo=''
                          OR enforced_by='' OR authority_rule_id='' OR deputy_role=''
                          OR scope_rule='' OR delegation=''");
$sodUnenforced = array();
foreach (repair01_w12_sod_codes() as $key => $c) {
    $p = $ROOT . '/' . $c['file'];
    $src = is_file($p) ? (string) file_get_contents($p) : '';
    if (strpos($src, "'" . $c['code'] . "'") === false) { $sodUnenforced[] = $key; }
    $inBook = (int) $one("SELECT COUNT(*) FROM repair01_w12_sod
                           WHERE process_key = '" . $esc($key) . "'
                             AND enforced_by = '" . $esc($c['code']) . "'");
    if ($inBook === 0) { $sodUnenforced[] = $key . ' (بلا صفٍّ)'; }
}
gate('W12-23', 'فصلُ الواجباتِ بستّةِ أدوارٍ وتركيبةٍ ممنوعةٍ ورمزِ ردٍّ يُنفِّذها',
     $sodN >= 9 && $sodBad === 0 && count($sodUnenforced) === 0,
     "عملياتٌ حرِجة $sodN · ناقصةُ الأدوارِ $sodBad · بلا رمزٍ في القرصِ " . count($sodUnenforced)
     . (count($sodUnenforced) ? ' ⇐ ' . implode('، ', array_slice($sodUnenforced, 0, 3)) : ''));

/* ══ W12-24 · عقدُ أثرٍ لكلِّ حدثٍ بمستهلكٍ نشطٍ بالاسم ════════════════ */
$evN = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W12'");
$evBad = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W12'
                      AND (trigger_rule='' OR min_payload='' OR consumer_list='' OR consumer_effect=''
                           OR preconditions='' OR retry_policy='' OR idempotency_key=''
                           OR failure_policy='' OR compensation='' OR contract_status <> 'RECORDED')");
$evVague = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W12'
                        AND (consumer_list LIKE '%كل المستهلكين%' OR consumer_list LIKE '%جميع%')");
$evNoSub = array(); $evNoPub = array();
foreach (repair01_w12_stage_events() as $code) {
    $inBook = (int) $one("SELECT COUNT(*) FROM repair01_events
                           WHERE wave = 'W12' AND event_code = '" . $esc($code) . "'");
    if ($inBook === 0) { $evNoSub[] = $code . ' (بلا عقد)'; continue; }
    $sub = (int) $one("SELECT COUNT(*) FROM event_consumers
                        WHERE event_name = '" . $esc($code) . "' AND active = 1");
    if ($sub === 0) { $evNoSub[] = $code; }
    /* **والناشرُ مُثبَتٌ من القرصِ لا مُعلَنٌ فقط** */
    $pubFile = $ROOT . '/' . repair01_w12_event_publisher($code);
    $src = is_file($pubFile) ? (string) file_get_contents($pubFile) : '';
    if (strpos($src, "'" . $code . "'") === false) { $evNoPub[] = $code; }
}
gate('W12-24', 'عقدُ أثرٍ لكلِّ حدثٍ بناشرٍ ومستهلكٍ نشطٍ مُثبَتَين',
     $evN === count(repair01_w12_stage_events()) && $evBad === 0 && $evVague === 0
     && count($evNoSub) === 0 && count($evNoPub) === 0 && !$vac($evN),
     "عقودٌ $evN · ناقصةٌ $evBad · مستهلكٌ مبهمٌ $evVague · بلا مشتركٍ نشطٍ " . count($evNoSub)
     . ' · بلا ناشرٍ مُثبَتٍ ' . count($evNoPub)
     . (count($evNoSub) ? ' ⇐ ' . implode('، ', array_slice($evNoSub, 0, 3)) : ''));

/* ══ W12-25 · فصلُ الواجباتِ مقيسٌ على الصفوفِ لا مُعلَنٌ فقط ═════════ */
$sodBreach = repair01_w12_close_sod_breach($conn);
gate('W12-25', 'إقفالٌ معتمَدٌ من مُعِدِّه أو بلا معتمِدٍ — صفر', $sodBreach === 0,
     "خرقُ فصلِ واجباتٍ في صفوفِ الإقفال $sodBreach");

/* ══ W12-26 · الفجواتُ السبعُ موفّاةٌ بملفٍّ قائمٍ لا بدعوى ════════════ */
$gapN = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps
                     WHERE unit = 'DEP-03' AND wave_stage = 'W12'");
$gapFilled = 0; $gapBad = array();
$rg = $conn->query("SELECT id, built_counterpart FROM repair01_target_gaps
                     WHERE unit = 'DEP-03' AND wave_stage = 'W12'");
while ($rg && $g = $rg->fetch_assoc()) {
    $bc = trim((string) $g['built_counterpart']);
    if ($bc === '') { $gapBad[] = $g['id'] . ' (بلا موفٍّ)'; continue; }
    $file = preg_replace('/\?.*$/', '', $bc);
    if (!is_file($ROOT . '/' . $file)) { $gapBad[] = $g['id'] . ' (لا ملف)'; continue; }
    $gapFilled++;
}
/* ⛔ **ومقاما الأشباحِ والأساسِ لم يتحرّكا بهذا القيد** */
$ghosts = (int) $one("SELECT COUNT(*) FROM repair01_surfaces WHERE on_disk = 0");
$gapsOrig = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = ''");
gate('W12-26', 'فجواتُ النطاقِ موفّاةٌ بملفٍّ قائمٍ والأشباحُ لم تُمَسّ',
     $gapN > 0 && $gapFilled === $gapN && count($gapBad) === 0
     && $ghosts === $W00['surfaces_ghost'] && $gapsOrig === $W00['gaps_original'],
     "فجواتُ النطاقِ $gapN · موفّاةٌ $gapFilled · بلا موفٍّ " . count($gapBad)
     . " · أشباحٌ $ghosts (يجب " . $W00['surfaces_ghost'] . ") · فجواتٌ أصليّةٌ $gapsOrig (يجب " . $W00['gaps_original'] . ")"
     . (count($gapBad) ? ' ⇐ ' . implode('، ', $gapBad) : ''));

/* ══ W12-27 · لا رمزٌ يُعرَض خامًّا — القاموسُ يغطّي قيمَ النطاق ═══════ */
$dictMiss = repair01_w12_dict_missing($conn);
$dictN = (int) $one("SELECT COUNT(*) FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W12%'");
$viewFile = $ROOT . '/Financing/w12_view.php';
$viewSrc  = is_file($viewFile) ? (string) file_get_contents($viewFile) : '';
$viewFromDict = (strpos($viewSrc, 'ems_w7_ar') !== false);
$dictDecl = repair01_w12_dict_declared($conn);
gate('W12-27', 'الرمزُ لاتينيٌّ في السجلِّ ويُعرَض عربيًّا من القاموس',
     count($dictMiss) === 0 && $dictN > 0 && $dictDecl > 0 && $viewFromDict,
     "رمزٌ بلا مسمًّى " . count($dictMiss) . " من مقامٍ $dictDecl · رموزُ الموجةِ $dictN"
     . ' · المُصيِّرُ يقرأ من القاموسِ ' . ($viewFromDict ? 'نعم' : 'لا')
     . (count($dictMiss) ? ' ⇐ ' . implode('، ', array_slice($dictMiss, 0, 4)) : ''));

/* ══ W12-28 · قراراتُ المرحلةِ وإصلاحاتُها بعلّتِها وكاشفِها ═══════════ */
$decN   = (int) $one("SELECT COUNT(*) FROM repair01_w12_decisions");
$decBad = (int) $one("SELECT COUNT(*) FROM repair01_w12_decisions
                       WHERE question = '' OR answer = '' OR COALESCE(rationale, '') = ''");
$fixN   = (int) $one("SELECT COUNT(*) FROM repair01_w12_fixes");
$fixBad = (int) $one("SELECT COUNT(*) FROM repair01_w12_fixes
                       WHERE revealed_by = '' OR why = '' OR before_num = '' OR after_num = ''");
$fixOrph = (int) $one("SELECT COUNT(*) FROM repair01_w12_fixes f
                        LEFT JOIN repair01_requirements r ON r.requirement_id = f.revealed_by
                                                         AND r.stage_no = 12
                       WHERE r.requirement_id IS NULL");
$layN   = (int) $one("SELECT COUNT(*) FROM repair01_w12_layers");
$layBad = (int) $one("SELECT COUNT(*) FROM repair01_w12_layers WHERE why = '' OR future_column = ''");
gate('W12-28', 'كلُّ قرارٍ بعلّتِه وكلُّ إصلاحٍ بمتطلَّبِه الكاشف',
     $decN >= 8 && $decBad === 0 && $fixN > 0 && $fixBad === 0 && $fixOrph === 0
     && $layN > 0 && $layBad === 0,
     "قرارات $decN · ناقصةٌ $decBad · إصلاحات $fixN · بلا كاشفٍ $fixBad"
     . " · كاشفٌ خارجَ المرحلةِ $fixOrph · قدراتُ الطبقتَين $layN · ناقصةٌ $layBad");

/* ══ W12-30 · نموُّ المرحلةِ مسجَّلٌ على قاعدةِ W03 بحكمِه المقيس ═══════
   ◆ **جدولُ نموٍّ يحمل اسمَ إنسانٍ نصًّا يدخل مدى `W3-03`** — فيُسجَّل بحكمِ
     ماسحِ W03 نفسِه. وهذا الحاجبُ **يعيد الاشتقاقَ ويقارن**: قيمةٌ مكتوبةٌ
     تخالف المقيسَ تُسقطه هنا قبل أن تسقطها W03. */
$w3Miss = array(); $w3Bad = array(); $w3Alt = 0; $w3Cand = 0;
if (is_file($ROOT . '/tools/lib/repair01_w3_scan.php')) {
    require_once $ROOT . '/tools/lib/repair01_w3_scan.php';
    $mineT = array_flip(repair01_w12_entity_tables());
    foreach (repair01_w3_scan_aliases($conn) as $key => $rowsA) {
        foreach ($rowsA as $a) {
            if (!isset($mineT[$a['table']])) { continue; }
            $w3Cand++;
            if ($a['rows'] > 0 && $a['rows_seed'] === $a['rows'] && $a['rows_resolvable'] === 0) {
                $want = 'SEED_NO_REFERENT';
            } elseif ($a['rows'] === 0) { $want = 'SEED_NO_REFERENT'; }
            else { $want = 'ALTERNATE_ID'; $w3Alt++; }
            $got = (string) $one("SELECT verdict FROM repair01_key_alias
                                   WHERE key_code = '" . $esc($key) . "'
                                     AND alias_table = '" . $esc($a['table']) . "'
                                     AND alias_column = '" . $esc($a['column']) . "'");
            if ($got === '') { $w3Miss[] = $a['table'] . '.' . $a['column']; }
            elseif ($got !== $want) { $w3Bad[] = $a['table'] . '.' . $a['column'] . " (سُجّل $got · المقيس $want)"; }
        }
    }
}
gate('W12-30', 'مرشَّحاتُ نموِّ المرحلةِ مسجَّلةٌ في دفترِ W03 بحكمِها المقيس',
     count($w3Miss) === 0 && count($w3Bad) === 0 && $w3Alt === 0,
     "مرشَّحٌ في جداولِ المرحلةِ $w3Cand · غيرُ مسجَّلٍ " . count($w3Miss)
     . ' · حكمٌ يخالف المقيسَ ' . count($w3Bad) . " · معرّفٌ بديلٌ حيٌّ $w3Alt"
     . (count($w3Miss) ? ' ⇐ ' . implode('، ', array_slice($w3Miss, 0, 2)) : '')
     . (count($w3Bad) ? ' ⇐ ' . implode('، ', array_slice($w3Bad, 0, 2)) : ''));

/* ══ W12-29 · رحلةُ التمويلِ تعبر ولا تترك أثرًا (§٦-أ) ═══════════════ */
$jOut = array(); $jCode = 1;
@exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_w12_journey.php" 2>&1', $jOut, $jCode);
/* ⚠ مُعرِّفُ الجولةِ من المخرَجِ لا من «آخرِ صفٍّ» — رحلةٌ لم تنعقد تترك سابقتَها */
$run = '';
foreach ($jOut as $l) { if (preg_match('/^RUN=(W12J-\d+)$/', trim($l), $m)) { $run = $m[1]; break; } }
$jTotal = (int) $one("SELECT COUNT(*) FROM repair01_w12_journey WHERE run_id = '" . $esc($run) . "'");
$jPass  = (int) $one("SELECT COUNT(*) FROM repair01_w12_journey WHERE run_id = '" . $esc($run) . "' AND passed = 1");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w12_journey
                       WHERE run_id = '" . $esc($run) . "' AND (business_effect = '' OR business_effect = '—')");
$jCons  = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w12_journey WHERE run_id = '" . $esc($run) . "'");
$jLegs  = (int) $one("SELECT COUNT(DISTINCT leg) FROM repair01_w12_journey WHERE run_id = '" . $esc($run) . "'");
$jCo    = (int) $one("SELECT COUNT(DISTINCT company_id) FROM repair01_w12_journey
                       WHERE run_id = '" . $esc($run) . "' AND company_id > 0");
$jLeft  = (int) $one("SELECT COUNT(*) FROM fin_contract_close WHERE close_code LIKE 'W12J-%'")
        + (int) $one("SELECT COUNT(*) FROM fin_monthly_close WHERE close_code LIKE 'W12J-%'")
        + (int) $one("SELECT COUNT(*) FROM fin_final_close WHERE close_code LIKE 'W12J-%'")
        + (int) $one("SELECT COUNT(*) FROM fin_payment_order WHERE order_code LIKE 'W12J-%'")
        + (int) $one("SELECT COUNT(*) FROM financing_operations WHERE op_code LIKE 'W12J-%'")
        + (int) $one("SELECT COUNT(*) FROM acc_recognition_request WHERE source_ref LIKE 'FPAYO:W12J-%'");
gate('W12-29', 'رحلةُ التمويلِ تعبر لكيانٍ واحدٍ ولا تترك أثرًا',
     $jCode === 0 && $run !== '' && $jTotal >= 55 && $jPass === $jTotal
     && $jCons >= 20 && $jLegs >= 8 && $jCo === 1 && $jNoEff === 0 && $jLeft === 0,
     'الجولة ' . ($run !== '' ? $run : '— لم تُعلَن —')
     . " · عابرٌ $jPass/$jTotal · أشواطٌ $jLegs · مستهلكونَ متمايزون $jCons"
     . " · كياناتٌ $jCo · بلا أثرٍ تجاريٍّ $jNoEff · أثرٌ باقٍ $jLeft"
     . ($jCode !== 0 ? ' · رمزُ الخروج ' . $jCode : ''));

/* ═══════════════════════ الطباعة ═══════════════════════ */
foreach ($rows as $x) {
    printf("  %s %-9s %-52s %s\n", $x[2] ? '✔' : '✘', $x[0], $x[1], $x[3]);
}
echo str_repeat('─', 130) . "\n";
printf("W12 gate: %d/%d  ·  إقفالٌ واحدٌ يخدم معنيَين %d · تصميمٌ مقيَّدٌ ببياناتٍ تاريخية %d"
     . "  ·  رحلةٌ %d/%d\n",
    $pass, $pass + $fail, $dual, $constr, $jPass, $jTotal);
echo ($fail === 0) ? "الحكم: خضراء ✔\n" : "الحكم: ساقطة ✘\n";
exit($fail === 0 ? 0 : 1);
