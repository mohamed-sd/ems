<?php
/**
 * tools/repair01_w13_gate.php — بوّابةُ المرحلةِ الثالثةَ عشرة (الموارد والبلاغات)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ حاجبٍ يُعيد القياسَ ولا يقرأ ما خزّنَته أداةُ الاشتقاق** — والمقامُ
 *   يُعاد بناؤه في كلِّ تشغيل.
 *
 * ◆ **وحارسُ الخلاءِ مبنيٌّ من البداية**: مجموعةٌ خاويةٌ تُخضِرُّ الحاجبَ على
 *   «تطابقِ لا شيء» — وهو النمطُ الذي كلَّف W01 و W07 و W09 جولاتِ إصلاح.
 *   فالصفرُ يمرُّ **مُعلَنًا بقرارٍ وحدَه** (`W13-D-07`) ويسقط بلا إعلان.
 *
 * ◆ **ومحورا المرحلةِ يُقاسان بنيويًّا**: `طرفٌ من الأربعةِ مدموج` و
 *   `تنفيذُ حلٍّ مملوكٌ للبلاغات` — كلٌّ على خمسِ جبهاتٍ تُعاد في كلِّ نداء،
 *   **وجبهةٌ غيرُ مقيسةٍ تُسقط الحاجبَ ولا تُعَدُّ صفرًا**.
 *
 * التشغيل: php tools/repair01_w13_gate.php
 * الخروج : 0 كلُّ الحواجبِ خضراء · 1 حاجبٌ ساقط
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w13_scan.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w13_one($conn, $sql); };

$pass = 0; $fail = 0; $rows = array();
function gate($id, $title, $ok, $detail)
{
    global $pass, $fail, $rows;
    if ($ok) { $pass++; } else { $fail++; }
    $rows[] = array($id, $title, $ok, $detail);
}

echo "═══════ بوّابةُ المرحلةِ الثالثةَ عشرة — REPAIR01 · الموارد البشرية والبلاغات ═══════\n";

/* ── حارسُ الخلاءِ: الصفرُ يمرُّ مُعلَنًا وحدَه ────────────────────────── */
$emptyDeclared = (int) $one("SELECT COUNT(*) FROM repair01_w13_decisions
                              WHERE decision_id = 'W13-D-07' AND COALESCE(rationale, '') <> ''");
$vac = function ($n) use ($emptyDeclared) { return ((int) $n === 0 && $emptyDeclared === 0); };
$vacTag = $emptyDeclared ? 'خلاءٌ مُعلَنٌ في W13-D-07 ✔' : '**خلاءٌ غيرُ مُعلَن**';

$ANCH = repair01_w13_anchors();
$NEW  = repair01_w13_new_surfaces();

/* ══ W13-01 · كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع ═══════════════════════════ */
$reqN   = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 13");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w13_scope");
$noRule = (int) $one("SELECT COUNT(*) FROM repair01_w13_scope WHERE map_rule = '' OR map_why = ''");
$orphan = (int) $one("SELECT COUNT(*) FROM repair01_w13_scope s
                       LEFT JOIN repair01_requirements r ON r.requirement_id = s.requirement_id
                                                        AND r.stage_no = 13
                      WHERE r.requirement_id IS NULL");
gate('W13-01', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $reqN > 0 && $scopeN === $reqN && $noRule === 0 && $orphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $noRule · مِرساةٌ يتيمةٌ $orphan");

/* ══ W13-02 · المِرساةُ مُثبَتةٌ من القرصِ لا مُعلَنةٌ فقط ══════════════ */
$proven = 0; $unproven = array();
foreach ($ANCH as $rid => $a) {
    $pr = repair01_w13_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $proven++; } else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }
}
$bookProven = (int) $one("SELECT COUNT(*) FROM repair01_w13_scope WHERE map_rule LIKE 'W13_ROUTE%'");
gate('W13-02', 'المِرساةُ مُثبَتةٌ من القرصِ والدفترُ يطابق المقيس',
     $proven === count($ANCH) && count($unproven) === 0 && $bookProven === $proven,
     'مُثبَتةٌ ' . $proven . ' من ' . count($ANCH) . ' · في الدفتر ' . $bookProven
     . ' · لم تُثبَت ' . count($unproven)
     . (count($unproven) ? ' ⇐ ' . implode('، ', array_slice($unproven, 0, 3)) : ''));

/* ══ W13-03 · مالكُ السطحِ يطابق مالكَ المتطلَّبِ **أو مُعلَنٌ بعددِه** ══
   ◆ **والانحرافُ يُعلَن ولا يُقلَب من داخلِ مرحلةٍ تستفيد منه** (`W13-D-11`):
     خمسةُ أسطحٍ مالكُها الحيُّ `DEP-13` ومالكُ متطلَّبِها `DEP-07`. والحاجبُ
     يسقط إن **زاد** العددُ أو إن لمس الانحرافُ سطحًا غيرَ الخمسة. */
$mis   = (int) $one("SELECT COUNT(*) FROM repair01_w13_scope WHERE owner_verdict = 'MISMATCH'");
$misOk = (int) $one("SELECT scope_rows FROM repair01_w13_decisions WHERE decision_id = 'W13-D-11'");
$misWhy = (string) $one("SELECT rationale FROM repair01_w13_decisions WHERE decision_id = 'W13-D-11'");
$misOut = (int) $one("SELECT COUNT(*) FROM repair01_w13_scope
                       WHERE owner_verdict = 'MISMATCH' AND owner_measured <> 'DEP-13'");
gate('W13-03', 'مالكٌ مخالفٌ مُعلَنٌ بعددِه ولا يزيد',
     $mis === $misOk && $misOut === 0 && $misWhy !== '',
     "مخالفٌ مقيسٌ $mis · مُعلَنٌ $misOk · خارجَ الإعلانِ $misOut");

/* ══ W13-04 · سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح ═══════════════════════ */
$routes = array();
foreach ($ANCH as $a) { if ($a['route'] !== '') { $routes[$a['route']] = true; } }
$sbN   = (int) $one("SELECT COUNT(*) FROM repair01_w13_sidebar");
$sbBad = (int) $one("SELECT COUNT(*) FROM repair01_w13_sidebar
                      WHERE s1_verdict='' OR s1_rule='' OR s2_verdict='' OR s2_rule=''
                         OR s3_verdict='' OR s3_rule='' OR s4_verdict='' OR s4_rule=''
                         OR s5_verdict='' OR s5_rule='' OR s6_verdict='' OR s6_rule=''
                         OR s7_verdict='' OR s7_rule=''");
gate('W13-04', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح', $sbN === count($routes) && $sbBad === 0,
     'أسطحُ النطاقِ المُعادُ اشتقاقُها ' . count($routes) . " · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBad");

/* ══ W13-05 · الاسمُ والمجموعةُ **صُحِّحا** لا قِيسا فقط ═════════════════ */
$lblDrift = (int) $one("SELECT COUNT(*) FROM repair01_w13_sidebar WHERE s2_verdict <> 'LABEL_MATCH'");
$grpDrift = (int) $one("SELECT COUNT(*) FROM repair01_w13_sidebar WHERE s3_verdict <> 'GROUP_MATCH'");
gate('W13-05', 'الاسمُ والمجموعةُ صُحِّحا على السجلِّ ودورةِ العمل',
     $lblDrift === 0 && $grpDrift === 0 && $sbN > 0,
     "انحرافُ اسمٍ $lblDrift · انحرافُ مجموعةٍ $grpDrift · المقامُ $sbN");

/* ══ W13-06 · حارسُ عرضٍ ومنحُ صلاحيةٍ مقيسانِ لكلِّ سطح ════════════════ */
$noGrant = (int) $one("SELECT COUNT(*) FROM repair01_w13_sidebar WHERE s6_perm_rows = 0");
$noGuard = array();
foreach (array_keys($routes) as $rt) {
    $g = repair01_w13_guard_of($ROOT, $rt);
    if ($g['kind'] === 'NONE') { $noGuard[] = $rt; }
}
gate('W13-06', 'حارسُ عرضٍ ومنحٌ مقيسانِ لكلِّ سطح', count($noGuard) === 0 && $noGrant === 0,
     'سطحٌ بلا حارسٍ ' . count($noGuard) . " · سطحٌ بلا منحٍ $noGrant"
     . (count($noGuard) ? ' ⇐ ' . implode('، ', array_slice($noGuard, 0, 2)) : ''));

/* ══ W13-07 · الترتيبُ من دورةِ العملِ لا من الأبجديّة ═══════════════════
   ◆ **يُعاد اشتقاقُ الموضعِ من الدورةِ ويُقارَن بالمخزَّن** — ثمَّ يُفحَص تصاعدُ
     الترتيبِ مع الدورة: فترتيبٌ أبجديٌّ يضع الإغلاقَ قبل التوجيهِ ويضع التصفيةَ
     قبل التعيين، فيقرأ المستخدمُ الدورةَ معكوسة. */
$noOrder = (int) $one("SELECT COUNT(*) FROM repair01_w13_sidebar WHERE s4_verdict <> 'ORDER_FROM_CYCLE'");
$stepOf = array(); $stepMismatch = 0;
foreach ($ANCH as $a) {
    if ($a['route'] === '') { continue; }
    if (!isset($stepOf[$a['route']]) || (int) $a['step'] < $stepOf[$a['route']]) {
        $stepOf[$a['route']] = (int) $a['step'];
    }
}
foreach ($stepOf as $rt => $st) {
    $stored = (int) $one("SELECT s4_cycle_step FROM repair01_w13_sidebar WHERE route = '" . $esc($rt) . "' LIMIT 1");
    if ($stored !== $st) { $stepMismatch++; }
}
$inversions = 0; $prevStep = -1; $prevSort = -1;
$ro = $conn->query("SELECT s4_cycle_step, s4_order_no FROM repair01_w13_sidebar ORDER BY s4_cycle_step");
while ($ro && $rx = $ro->fetch_row()) {
    $st = (int) $rx[0]; $so = (int) $rx[1];
    if ($prevStep >= 0 && $st > $prevStep && $so < $prevSort) { $inversions++; }
    $prevStep = $st; $prevSort = $so;
}
gate('W13-07', 'الترتيبُ من دورةِ العملِ ومتصاعدٌ معها',
     $noOrder === 0 && $stepMismatch === 0 && $inversions === 0,
     "بلا مصدرِ ترتيبٍ $noOrder · موضعٌ مخالفٌ للمشتقّ $stepMismatch · انعكاسٌ في التصاعد $inversions");

/* ══ W13-08 · الأبُ والتبويبُ والربطُ بالسجلِّ المعياريّ ════════════════ */
$noLink = (int) $one("SELECT COUNT(*) FROM repair01_w13_sidebar WHERE s7_linked = 0");
$noPar  = (int) $one("SELECT COUNT(*) FROM repair01_w13_sidebar
                       WHERE s5_verdict NOT IN ('MENU_ITEM','TAB_IN_PARENT')");
$noS1   = (int) $one("SELECT COUNT(*) FROM repair01_w13_sidebar
                       WHERE s1_verdict NOT IN ('ACTIVE_APPROVED','DISABLED_WITH_REASON')");
gate('W13-08', 'الأبُ والتبويبُ وحكمُ التفعيلِ والربطُ بالسجلِّ المعياريّ',
     $noLink === 0 && $noPar === 0 && $noS1 === 0,
     "غيرُ مربوطٍ $noLink · بلا حكمِ أبٍ $noPar · بلا حكمِ تفعيلٍ $noS1");

/* ══ W13-09 · النموُّ مختومٌ والأساسُ لم يُمَسّ (RPR-PATCH-02) ═══════════ */
$base   = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                       WHERE origin IN ('SURFACES','DISK','NAV')");
$w13N   = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin = 'W13'");
$unstamped = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                          WHERE origin NOT IN ('SURFACES','DISK','NAV')
                            AND origin NOT REGEXP '^W[0-9]+$'");
gate('W13-09', 'أساسُ السجلِّ مُجمَّدٌ والنموُّ مختومٌ بموجتِه',
     $base === 651 && $w13N === count($NEW) && $unstamped === 0,
     "الأساس $base (المتوقَّع 651) · نموُّ W13 $w13N من " . count($NEW) . " · نموٌّ بلا ختمٍ $unstamped");

/* ══ W13-10 · **المحورُ ① — طرفٌ من الأربعةِ مدموج** ════════════════════
   ◆ خمسُ جبهاتٍ تُعاد في كلِّ نداء، **وجبهةٌ غيرُ مقيسةٍ تُسقط ولا تُعَدُّ صفرًا**. */
$mg = repair01_w13_party_merged($conn);
$mgTxt = array();
foreach ($mg['fronts'] as $k => $v) { $mgTxt[] = $k . '=' . ($v < 0 ? 'غير مقيس' : $v); }
gate('W13-10', 'طرفٌ من الأربعةِ مدموجٌ صفرٌ على خمسِ جبهات',
     $mg['total'] === 0 && count($mg['unmeasured']) === 0,
     'المجموع ' . $mg['total'] . ' · ' . implode(' · ', $mgTxt));

/* ══ W13-11 · **المحورُ ② — تنفيذُ حلٍّ مملوكٌ للبلاغات** ═══════════════ */
$cr = repair01_w13_resolution_owned_by_crp($conn);
$crTxt = array();
foreach ($cr['fronts'] as $k => $v) { $crTxt[] = $k . '=' . ($v < 0 ? 'غير مقيس' : $v); }
gate('W13-11', 'تنفيذُ حلٍّ مملوكٌ للبلاغاتِ صفرٌ على خمسِ جبهات',
     $cr['total'] === 0 && count($cr['unmeasured']) === 0,
     'المجموع ' . $cr['total'] . ' · ' . implode(' · ', $crTxt));

/* ══ W13-12 · الأطرافُ الأربعةُ مُعلَنةٌ ولكلٍّ قيدُ منعِ دمجٍ في القاعدة ══ */
$pN = (int) $one("SELECT COUNT(*) FROM repair01_w13_parties");
$pBad = (int) $one("SELECT COUNT(*) FROM repair01_w13_parties
                     WHERE key_column = '' OR db_constraint = '' OR merge_rule = '' OR why = ''");
$pDead = array();
foreach (repair01_w13_party_roles() as $p) {
    $live = repair01_w13_check_exists($conn, 'tkt_party', $p[7])
         || (int) $one("SELECT COUNT(*) FROM information_schema.STATISTICS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tkt_party'
                           AND INDEX_NAME = '" . $esc($p[7]) . "'") > 0;
    if (!$live) { $pDead[] = $p[0]; }
}
gate('W13-12', 'أربعةُ أطرافٍ مُعلَنةٌ ولكلٍّ قيدُه في القاعدة',
     $pN === 4 && $pBad === 0 && count($pDead) === 0,
     "أطرافٌ $pN · بلا قاعدةٍ $pBad · قيدٌ غيرُ حيٍّ " . count($pDead)
     . (count($pDead) ? ' ⇐ ' . implode('، ', $pDead) : ''));

/* ══ W13-13 · قيودُ القاعدةِ الحاكمةُ حيّةٌ لا مُعلَنةٌ فقط ═════════════ */
$CHK = array(
    'tkt_party' => array('chk_tkp_role', 'chk_tkp_actor', 'chk_tkp_subject_typed',
                         'chk_tkp_own_crp', 'chk_tkp_res_not_crp'),
    'tkt_routing_history' => array('chk_tkr_kind', 'chk_tkr_move', 'chk_tkr_reason',
                                   'chk_tkr_rule', 'chk_tkr_not_crp'),
    'tkt_assignment_history' => array('chk_tka_reason', 'chk_tka_person', 'chk_tka_not_crp'),
    'tkt_resolution_action' => array('chk_tra_not_crp', 'chk_tra_ref', 'chk_tra_action'),
    'tkt_verification' => array('chk_tkv_state', 'chk_tkv_res_not_crp', 'chk_tkv_verifier',
                                'chk_tkv_close', 'chk_tkv_auto_not_critical', 'chk_tkv_window'),
    'tkt_reopen' => array('chk_tkro_reason', 'chk_tkro_note', 'chk_tkro_back'),
    'tkt_subject_type' => array('chk_tkst_ref', 'chk_tkst_kind'),
    'hr_employee_document' => array('chk_hred_file', 'chk_hred_state', 'chk_hred_mand'),
    'hr_onboarding_item' => array('chk_hron_state', 'chk_hron_waiver', 'chk_hron_done'),
    'hr_job_movement' => array('chk_hrjm_kind', 'chk_hrjm_doc', 'chk_hrjm_appr', 'chk_hrjm_to'),
    'hr_training_record' => array('chk_hrtr_kind', 'chk_hrtr_cert', 'chk_hrtr_mand'),
    'hr_performance_review' => array('chk_hrpr_self', 'chk_hrpr_kind', 'chk_hrpr_final'),
    'hr_disciplinary_case' => array('chk_hrdc_self', 'chk_hrdc_owner', 'chk_hrdc_iaf',
                                    'chk_hrdc_investigator', 'chk_hrdc_decider', 'chk_hrdc_dec',
                                    'chk_hrdc_inv'),
    'hr_disciplinary_stage' => array('chk_hrds_stage', 'chk_hrds_doc', 'chk_hrds_note'),
    'hr_benefit_enrollment' => array('chk_hrbe_ref', 'chk_hrbe_state', 'chk_hrbe_span'),
);
$chkN = 0; $chkMiss = array();
foreach ($CHK as $t => $names) {
    foreach ($names as $nm) {
        if (repair01_w13_check_exists($conn, $t, $nm)) { $chkN++; } else { $chkMiss[] = "$t.$nm"; }
    }
}
gate('W13-13', 'قيودُ القاعدةِ الحاكمةُ حيّةٌ في المخطَّط', count($chkMiss) === 0,
     "قيودٌ حيّةٌ $chkN · مفقودةٌ " . count($chkMiss)
     . (count($chkMiss) ? ' ⇐ ' . implode('، ', array_slice($chkMiss, 0, 3)) : ''));

/* ══ W13-14 · جداولُ المرحلةِ بكيانٍ قانونيٍّ إلزاميّ ═══════════════════ */
$W13T = array('tkt_party', 'tkt_subject_type', 'tkt_routing_history', 'tkt_assignment_history',
              'tkt_resolution_action', 'tkt_verification', 'tkt_reopen',
              'hr_employee_document', 'hr_onboarding_item', 'hr_job_movement',
              'hr_training_record', 'hr_performance_review', 'hr_disciplinary_case',
              'hr_disciplinary_stage', 'hr_benefit_enrollment');
$scopedN = 0; $unscoped = array();
foreach ($W13T as $t) {
    if (repair01_w13_scope_verdict($conn, $t) === 'COLUMN_NOT_NULL') { $scopedN++; }
    else { $unscoped[] = $t; }
}
gate('W13-14', 'كلُّ جدولٍ بنَته المرحلةُ يحمل الكيانَ غيرَ قابلٍ للعدم',
     count($unscoped) === 0 && $scopedN === count($W13T),
     "مقيَّدٌ بالكيان $scopedN من " . count($W13T)
     . (count($unscoped) ? ' ⇐ ' . implode('، ', $unscoped) : ''));

/* ══ W13-15 · الأعمدةُ الحيّةُ التي تقبل العدمَ مُعلَنةٌ بعددِها ═════════ */
$DECL = array('employees', 'ticket_escalations', 'ticket_participants', 'ticket_responses');
$declRows = 0; $tightened = 0;
foreach ($DECL as $t) {
    $declRows += (int) $one("SELECT COUNT(*) FROM `$t` WHERE company_id IS NULL");
}
foreach (array('workforce_requirement', 'worker_evaluation', 'job_titles',
               'ticket_communications', 'worker_leave_absence') as $t) {
    if (repair01_w13_scope_verdict($conn, $t) === 'COLUMN_NOT_NULL') { $tightened++; }
}
$declared06 = (int) $one("SELECT scope_rows FROM repair01_w13_decisions WHERE decision_id = 'W13-D-06'");
gate('W13-15', 'صفٌّ حيٌّ بلا كيانٍ مُعلَنٌ بعددِه ولا يزيد وخمسةٌ شُدِّدت',
     $declRows === $declared06 && $tightened === 5,
     "صفوفٌ بلا كيانٍ مقيسة $declRows · مُعلَنٌ $declared06 · أعمدةٌ شُدِّدت $tightened من 5");

/* ══ W13-16 · آلةُ حالةٍ لكلِّ كيانٍ رئيسيٍّ بممنوعٍ صريحٍ بسبب ═════════ */
$stEnt = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w13_states");
$stAll = (int) $one("SELECT COUNT(*) FROM repair01_w13_states");
$stForb = (int) $one("SELECT COUNT(*) FROM repair01_w13_states WHERE allowed = 0");
$stNoWhy = (int) $one("SELECT COUNT(*) FROM repair01_w13_states WHERE allowed = 0 AND forbid_why = ''");
$stNoRule = (int) $one("SELECT COUNT(*) FROM repair01_w13_states
                         WHERE allowed = 1 AND (owner_role = '' OR preconditions = ''
                            OR output_doc = '' OR reopen_rule = '' OR correct_rule = '')");
$entNoForb = (int) $one("SELECT COUNT(*) FROM (
                           SELECT entity FROM repair01_w13_states GROUP BY entity
                            HAVING SUM(allowed = 0) = 0) x");
$ENTS = repair01_w13_state_entities();
$entMissing = array();
foreach ($ENTS as $e) {
    if ((int) $one("SELECT COUNT(*) FROM repair01_w13_states
                     WHERE entity = '" . $esc($e) . "'") === 0) { $entMissing[] = $e; }
}
gate('W13-16', 'آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ صريحٍ بسبب',
     $stEnt > 0 && $stForb > 0 && $stNoWhy === 0 && $stNoRule === 0 && $entNoForb === 0
     && count($entMissing) === 0 && $stEnt === count($ENTS),
     "كيانات $stEnt من " . count($ENTS) . " · انتقالات $stAll · ممنوعٌ صراحةً $stForb"
     . " · ممنوعٌ بلا سببٍ $stNoWhy · مسموحٌ بلا قاعدةٍ $stNoRule · كيانٌ بلا ممنوعٍ $entNoForb"
     . ' · كيانٌ مُعلَنٌ بلا آلةٍ ' . count($entMissing)
     . (count($entMissing) ? ' ⇐ ' . implode('، ', $entMissing) : ''));

/* ══ W13-17 · فصلُ الواجباتِ بستّةِ أدوارٍ ورمزُ ردٍّ **مُنفَّذٌ في الخدمة** ═
   ◆ **والرمزُ لا يُعَدُّ منفَّذًا لأنّه مكتوبٌ في الدفتر** — يُقاس وجودُه في
     ملفِّ الخدمةِ نصًّا، فإعلانُ فصلِ واجباتٍ بلا رمزِ ردٍّ في الشيفرةِ وعدٌ لا وفاء. */
$sodN = (int) $one("SELECT COUNT(*) FROM repair01_w13_sod");
$sodBad = (int) $one("SELECT COUNT(*) FROM repair01_w13_sod
                       WHERE initiator_role='' OR reviewer_role='' OR approver_role=''
                          OR executor_role='' OR closer_role='' OR forbidden_combo=''
                          OR authority_rule_id='' OR deputy_role='' OR scope_rule='' OR delegation=''");
$svc = is_file($ROOT . '/app/Services/People/PeopleCycleService.php')
     ? (string) file_get_contents($ROOT . '/app/Services/People/PeopleCycleService.php') : '';
$sodDead = array();
$rs = $conn->query("SELECT process_key, enforced_by FROM repair01_w13_sod");
while ($rs && $sx = $rs->fetch_assoc()) {
    if ((string) $sx['enforced_by'] === '' || strpos($svc, (string) $sx['enforced_by']) === false) {
        $sodDead[] = (string) $sx['process_key'];
    }
}
gate('W13-17', 'فصلُ الواجباتِ بستّةِ أدوارٍ ورمزُ ردٍّ منفَّذٌ في الخدمة',
     $sodN === 12 && $sodBad === 0 && count($sodDead) === 0,
     "عملياتٌ $sodN · ناقصةُ دورٍ $sodBad · رمزٌ غيرُ منفَّذٍ " . count($sodDead)
     . (count($sodDead) ? ' ⇐ ' . implode('، ', array_slice($sodDead, 0, 3)) : ''));

/* ══ W13-18 · عقدُ أثرٍ لكلِّ حدثٍ بمستهلكٍ بالاسمِ لا «كلُّ المستهلكين» ══ */
$evN = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W13'");
$evBad = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W13'
                      AND (consumer_list = '' OR consumer_effect = '' OR min_payload = ''
                        OR preconditions = '' OR idempotency_key = '' OR failure_policy = ''
                        OR compensation = '' OR trigger_rule = '')");
$evVague = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W13'
                        AND (consumer_list LIKE '%كل المستهلكين%' OR consumer_list LIKE '%الجميع%')");
$declaredEv = repair01_w13_stage_events();
$evMissing = array();
foreach ($declaredEv as $code) {
    if ((int) $one("SELECT COUNT(*) FROM repair01_events
                     WHERE wave = 'W13' AND event_code = '" . $esc($code) . "'") === 0) {
        $evMissing[] = $code;
    }
}
gate('W13-18', 'عقدُ أثرٍ لكلِّ حدثٍ بمستهلكٍ بالاسم',
     $evN === count($declaredEv) && $evBad === 0 && $evVague === 0 && count($evMissing) === 0,
     "أحداثٌ مُعلَنةٌ " . count($declaredEv) . " · عقودٌ $evN · ناقصُ عقدٍ $evBad · مستهلكٌ مبهمٌ $evVague"
     . ' · بلا عقدٍ ' . count($evMissing)
     . (count($evMissing) ? ' ⇐ ' . implode('، ', array_slice($evMissing, 0, 3)) : ''));

/* ══ W13-19 · وحدثٌ يُطلقه النطاقُ بلا عقدٍ لا يُنفَّذ ═══════════════════
   ◆ **يُقاس من الشيفرةِ لا من الدفتر**: كلُّ مفتاحِ حدثٍ يمرّره الملفُّ إلى
     الناشرِ يجب أن يكون له صفٌّ في `repair01_events`. */
$emitted = array();
if ($svc !== '' && preg_match_all("~emit\\(\\s*'([a-z0-9_.]+)'~", $svc, $mm)) {
    $emitted = array_values(array_unique($mm[1]));
}
$noContract = array();
foreach ($emitted as $code) {
    if ((int) $one("SELECT COUNT(*) FROM repair01_events
                     WHERE wave = 'W13' AND event_code = '" . $esc($code) . "'") === 0) {
        $noContract[] = $code;
    }
}
gate('W13-19', 'كلُّ حدثٍ تُطلقه الخدمةُ له عقدُ أثرٍ مسجَّل',
     count($emitted) > 0 && count($noContract) === 0,
     'أحداثٌ مُطلَقةٌ في الشيفرة ' . count($emitted) . ' · بلا عقدٍ ' . count($noContract)
     . (count($noContract) ? ' ⇐ ' . implode('، ', $noContract) : ''));

/* ══ W13-20 · العتباتُ من السجلِّ ولا مقارنةٌ صلبةٌ في شيفرةِ النطاق ═════ */
$thN = (int) $one("SELECT COUNT(*) FROM repair01_w13_thresholds");
$thBad = (int) $one("SELECT COUNT(*) FROM repair01_w13_thresholds
                      WHERE why = '' OR decision_ref = '' OR unit_ar = '' OR title_ar = ''");
$hard = repair01_w13_hardcoded_thresholds($ROOT, $conn);
gate('W13-20', 'العتباتُ من السجلِّ ولا مقارنةٌ صلبةٌ في شيفرةِ النطاق',
     $thN === 10 && $thBad === 0 && count($hard) === 0,
     "عتباتٌ $thN · بلا سببٍ أو قرارٍ $thBad · مقارنةٌ صلبةٌ " . count($hard)
     . (count($hard) ? ' ⇐ ' . implode('، ', array_slice($hard, 0, 3)) : ''));

/* ══ W13-21 · محرّكُ `DEC-OPEN-05` قابلُ الضبطِ **والقرارُ يبقى لمالكِه** ═══
   ◆ §٤-٤ تطلب **مؤقّتًا قابلَ الضبط** لا قلبَ حالةِ قرار. فالقيمتانِ مسجَّلتانِ
     بمرجعِ قرارِهما وتُقرآنِ من السجلّ، والقيدُ حيٌّ في القاعدة —
   ⛔ **والقرارُ يبقى مصنَّفًا عتبةً مؤجَّلةَ الإعداد**: `blocking_level` صفةُ
     قرارٍ لا حالتُه، و`G0-04` يعدُّ الاثنتَي عشرةَ ثابتة. فقلبُه من داخلِ مرحلةٍ
     تستفيد منه **انتحالٌ لقرارِ مالكٍ لم يصدر** ويُسقط بوّابةَ الأساسِ معًا. */
$d05 = $conn->query("SELECT status, blocking_level, blocker_type FROM repair01_decisions
                      WHERE decision_id = 'DEC-OPEN-05'");
$d05 = $d05 ? $d05->fetch_assoc() : null;
$winN = (float) $one("SELECT value_num FROM repair01_w13_thresholds
                       WHERE threshold_key = 'TKT_VERIFY_WINDOW_NORMAL_H'");
$winC = (float) $one("SELECT value_num FROM repair01_w13_thresholds
                       WHERE threshold_key = 'TKT_VERIFY_WINDOW_CRITICAL_H'");
$refN = (string) $one("SELECT decision_ref FROM repair01_w13_thresholds
                        WHERE threshold_key = 'TKT_VERIFY_WINDOW_NORMAL_H'");
$refC = (string) $one("SELECT decision_ref FROM repair01_w13_thresholds
                        WHERE threshold_key = 'TKT_VERIFY_WINDOW_CRITICAL_H'");
$critChk = repair01_w13_check_exists($conn, 'tkt_verification', 'chk_tkv_auto_not_critical');
$cfgN = (int) $one("SELECT COUNT(*) FROM repair01_decisions WHERE blocking_level = 'CONFIG_PENDING'");
$d03Why = (string) $one("SELECT rationale FROM repair01_w13_decisions WHERE decision_id = 'W13-D-03'");
gate('W13-21', 'محرّكُ نافذةِ التحقّقِ من السجلِّ والقرارُ يبقى مصنَّفًا لمالكِه',
     $d05 && (string) $d05['blocker_type'] === 'THRESHOLD'
     && (string) $d05['blocking_level'] === 'CONFIG_PENDING'
     && $refN === 'DEC-OPEN-05' && $refC === 'DEC-OPEN-05'
     && $winN > 0 && $winC > 0 && $winC < $winN && $critChk && $d03Why !== '',
     'الصنف ' . ($d05 ? $d05['blocker_type'] : 'مفقود') . ' · الحجب '
     . ($d05 ? $d05['blocking_level'] : 'مفقود') . " · نافذةُ العادي $winN · الحرج $winC"
     . ' · مرجعُ القرارِ في السجلّ ' . ($refN === 'DEC-OPEN-05' && $refC === 'DEC-OPEN-05' ? 'نعم' : 'لا')
     . ' · قيدُ منعِ الإغلاقِ الآليِّ للحرِج ' . ($critChk ? 'حيّ' : 'مفقود')
     . " · تصنيفُ العتبات $cfgN");

/* ══ W13-22 · جوابُ `DEC-OPEN-16` مكتوبٌ ومطبَّقٌ في المخطَّط ════════════ */
$d16 = $conn->query("SELECT status, blocking_level, owner_decision FROM repair01_decisions
                      WHERE decision_id = 'DEC-OPEN-16'");
$d16 = $d16 ? $d16->fetch_assoc() : null;
$ownChk = repair01_w13_check_exists($conn, 'hr_disciplinary_case', 'chk_hrdc_owner');
$iafChk = repair01_w13_check_exists($conn, 'hr_disciplinary_case', 'chk_hrdc_iaf');
$structOpen = (int) $one("SELECT COUNT(*) FROM repair01_decisions
                           WHERE blocker_type = 'STRUCTURAL' AND status = 'NEEDS_OWNER_DECISION'");
/* ⚠ **الرسوُّ كان على كلمةٍ في نصٍّ حُرّ** — كان يشترط ورودَ `IAF` و`DEP-08`
     حرفًا في `owner_decision`. وهذا يقلب المعادلةَ: **يصير نصُّ المالكِ مطالَبًا
     بأن يطابق الكاشف** بدل أن يقيس الكاشفُ ما نفَّذَته الشيفرة. ووقع فعلًا:
     أمرُ المالكِ المكتوب 2026-08-26 حسم المسألةَ بثلاثِ إداراتٍ **ولم يكتب
     الرمزَ اللاتينيَّ `IAF`** — فأحمرَّ الحاجبُ على جوابٍ صحيح.
   ◆ **فالرسوُّ صار على المخطَّطِ نفسِه**: نصُّ قيدِ القاعدةِ يُقرأ من
     `information_schema` ويُشترط أن يحصر المالكَ في الثلاثة. وهذا يقيس
     **ما يُنفَّذ** لا ما يُكتب.
   ◆ **وشرطٌ ثالثٌ من أمرِ المالك (البند 11)**: `OWNER_APPROVED` لا يُقبَل بلا
     **مرجعِ جوابٍ بدليل** — ومرجعٌ يشير إلى موضعِ السؤالِ في المصنَّفِ ليس
     جوابًا. وغيابُ هذا الشرطِ هو ما سمح بأن يُكتب قرارٌ نيابةً عن المالك. */
$ownClause = (string) $one("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
                             WHERE CONSTRAINT_SCHEMA = DATABASE()
                               AND CONSTRAINT_NAME = 'chk_hrdc_owner'");
$threeDept = (mb_strpos($ownClause, 'DEP-07') !== false
           && mb_strpos($ownClause, 'DEP-08') !== false
           && mb_strpos($ownClause, 'IAF') !== false);
$d16Ref = $d16 ? (string) $one("SELECT src_ref FROM repair01_decisions WHERE decision_id = 'DEC-OPEN-16'") : '';
$refOk  = ($d16Ref !== '' && !preg_match('~^\d+\s*›~u', $d16Ref));
gate('W13-22', 'جوابُ DEC-OPEN-16 بمرجعٍ يُثبته ومطبَّقٌ بثلاثِ إداراتٍ وتكليفٍ موثَّق',
     $d16 && (string) $d16['status'] === 'APPROVED' && (string) $d16['blocking_level'] === 'NONE'
     && $refOk && $threeDept && $ownChk && $iafChk,
     'الحالة ' . ($d16 ? $d16['status'] : 'مفقود')
     . ' · مرجعٌ يسمّي الجوابَ ' . ($refOk ? 'نعم' : 'لا — يشير إلى موضعِ السؤال')
     . ' · قيدُ الإداراتِ الثلاث ' . ($threeDept ? 'يحصرها' : 'لا يحصرها')
     . ' · قيدُ تكليفِ المراجعةِ الداخلية ' . ($iafChk ? 'حيّ' : 'مفقود')
     . " · حاجبٌ بنيويٌّ مفتوحٌ بعده $structOpen");

/* ══ W13-23 · القضيّةُ ثلاثُ مراحلَ والخصمُ فرعٌ بمرجعِ قرارِها ══════════ */
$dedNoCase = repair01_w13_deduction_without_case($conn);
$skipped   = repair01_w13_case_stage_skipped($conn);
$caseRows  = (int) $one("SELECT COUNT(*) FROM hr_disciplinary_case");
gate('W13-23', 'قضيّةٌ بثلاثِ مراحلَ وخصمٌ بمرجعِ قرارِها',
     $dedNoCase === 0 && $skipped === 0 && $dedNoCase >= 0 && $skipped >= 0
     && !$vac($caseRows),
     "خصمٌ بلا قرارِ قضيّةٍ $dedNoCase · قضيّةٌ قفزت مرحلةً $skipped · قضايا $caseRows"
     . ($caseRows === 0 ? ' (' . $vacTag . ')' : ''));

/* ══ W13-24 · لا إغلاقَ بلا تحقّقٍ ولا تحقّقَ من المنفِّذِ نفسِه ════════ */
$closeNoVer = (int) $one("SELECT COUNT(*) FROM tkt_verification
                           WHERE closed_at IS NOT NULL AND verified_at IS NULL");
$selfVer    = (int) $one("SELECT COUNT(*) FROM tkt_verification
                           WHERE verified_by IS NOT NULL AND verified_by = resolved_by");
$autoCrit   = (int) $one("SELECT COUNT(*) FROM tkt_verification
                           WHERE verify_kind = 'AUTO_WINDOW' AND priority_code = 'critical'");
$noWindow   = (int) $one("SELECT COUNT(*) FROM tkt_verification WHERE window_hours <= 0");
gate('W13-24', 'لا إغلاقَ بلا تحقّقٍ ولا تحقّقَ من المنفِّذِ ولا آليَّ للحرِج',
     $closeNoVer === 0 && $selfVer === 0 && $autoCrit === 0 && $noWindow === 0,
     "إغلاقٌ بلا تحقّقٍ $closeNoVer · تحقّقٌ من المنفِّذِ $selfVer · آليٌّ للحرِج $autoCrit · بلا نافذةٍ $noWindow");

/* ══ W13-25 · التوجيهُ والإسنادُ لا يقعانِ على إدارةِ البلاغات ═══════════ */
$rtCrp = repair01_w13_routed_to_crp($conn);
$asCrp = repair01_w13_assigned_to_crp($conn);
$ackMissing = (int) $one("SELECT COUNT(*) FROM tkt_assignment_history a
                           JOIN tkt_verification v ON v.ticket_id = a.ticket_id AND v.state = 'closed'
                          WHERE a.received_at IS NULL");
gate('W13-25', 'التوجيهُ والإسنادُ خارجَ البلاغاتِ ولا مكلَّفَ بلا وقتِ استلام',
     $rtCrp === 0 && $asCrp === 0 && $ackMissing === 0,
     "توجيهٌ للمركزِ $rtCrp · إسنادٌ للمركزِ $asCrp · بلاغٌ مغلقٌ بإسنادٍ بلا استلامٍ $ackMissing");

/* ══ W13-26 · كتالوجُ محلِّ البلاغِ بسجلٍّ مرجعيٍّ **مُثبَتٍ من المخطَّط** ══
   ◆ **نوعٌ يشير إلى جدولٍ لا وجودَ له كتالوجٌ يعِد ولا يفي** — سابقةُ `NF-09`. */
$stN = (int) $one("SELECT COUNT(*) FROM tkt_subject_type WHERE active = 1");
$stGhost = 0; $stNames = array();
$rt2 = $conn->query("SELECT type_code, ref_table FROM tkt_subject_type WHERE active = 1");
while ($rt2 && $tx = $rt2->fetch_assoc()) {
    if (!repair01_w13_table_exists($conn, (string) $tx['ref_table'])) {
        $stGhost++; $stNames[] = (string) $tx['type_code'];
    }
}
gate('W13-26', 'كلُّ نوعِ محلِّ بلاغٍ بسجلٍّ مرجعيٍّ قائمٍ في المخطَّط',
     $stN > 0 && $stGhost === 0,
     "أنواعٌ مفعَّلةٌ $stN · بلا سجلٍّ قائمٍ $stGhost"
     . (count($stNames) ? ' ⇐ ' . implode('، ', array_slice($stNames, 0, 3)) : ''));

/* ══ W13-27 · قاموسُ العرضِ — كلُّ رمزٍ مُعلَنٍ بمسمًّى عربيّ ═══════════
   ◆ **مقامٌ ثابتٌ لا يخلو** (‏درسُ `W12-27`): الرموزُ التي أعلنَتها الموجةُ
     نفسُها، فالحاجبُ يقظٌ من أوّلِ يومٍ لا من أوّلِ صفٍّ حيّ. */
$dictMiss = repair01_w13_dict_missing($conn);
$declaredCodes = count(repair01_w13_declared_codes());
gate('W13-27', 'كلُّ رمزٍ مُعلَنٍ له مسمًّى عربيٌّ في القاموسِ المركزيّ',
     count($dictMiss) === 0 && $declaredCodes > 0,
     "رموزٌ مُعلَنةٌ $declaredCodes · بلا مسمًّى " . count($dictMiss)
     . (count($dictMiss) ? ' ⇐ ' . implode('، ', array_slice($dictMiss, 0, 3)) : ''));

/* ══ W13-28 · فجواتُ النطاقِ موفّاةٌ ومُؤجَّلُها مُعلَنٌ بعددِه ═════════ */
$gapAll  = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps
                        WHERE unit IN ('DEP-07','DEP-10') AND wave_stage = 'W13'");
$gapDone = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps
                        WHERE unit IN ('DEP-07','DEP-10') AND wave_stage = 'W13'
                          AND built_counterpart <> '' AND built_counterpart <> '—'");
$iafAll  = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps
                        WHERE unit = 'IAF' AND wave_stage = 'W13'");
$iafDecl = (int) $one("SELECT scope_rows FROM repair01_w13_decisions WHERE decision_id = 'W13-D-08'");
$ghosts  = (int) $one("SELECT COUNT(*) FROM repair01_surfaces WHERE on_disk = 0");
/* **والمقامُ الأصليُّ فجواتُ الدراسةِ وحدَها** — و`origin_stage = 'W02'` صفوفٌ
   نُقلت من خانةِ المبنيِّ في W02، فعدُّها مع الأصلِ يجعل الأساسَ ٣٣٤ لا ١٧٤. */
$gapsAll = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = ''");
gate('W13-28', 'فجواتُ الوحدتَين موفّاةٌ ومُؤجَّلُ المراجعةِ مُعلَنٌ بعددِه',
     $gapAll > 0 && $gapDone === $gapAll && $iafAll === $iafDecl
     && $ghosts === 257 && $gapsAll === 174,
     "فجواتُ الوحدتَين $gapAll · موفّاةٌ $gapDone · فجواتُ المراجعةِ $iafAll مُعلَنٌ $iafDecl"
     . " · أشباحٌ $ghosts · فجواتٌ أصليّةٌ $gapsAll");

/* ══ W13-29 · قراراتُ المرحلةِ كلُّها بمبرِّرٍ ونطاقٍ مقيس ═══════════════ */
$decN = (int) $one("SELECT COUNT(*) FROM repair01_w13_decisions");
$decBad = (int) $one("SELECT COUNT(*) FROM repair01_w13_decisions
                       WHERE question = '' OR answer = '' OR rationale = ''");
gate('W13-29', 'قراراتُ المرحلةِ كلُّها بسؤالٍ وجوابٍ ومبرِّر',
     $decN > 0 && $decBad === 0, "قرارات $decN · ناقصةٌ $decBad");

/* ══ W13-30 · كلُّ إصلاحٍ بمتطلَّبِه الكاشف ═════════════════════════════ */
$fxN = (int) $one("SELECT COUNT(*) FROM repair01_w13_fixes");
$fxBad = (int) $one("SELECT COUNT(*) FROM repair01_w13_fixes
                      WHERE revealed_by = '' OR before_num = '' OR after_num = '' OR why = ''");
$fxOrphan = (int) $one("SELECT COUNT(*) FROM repair01_w13_fixes f
                         LEFT JOIN repair01_requirements r ON r.requirement_id = f.revealed_by
                                                          AND r.stage_no = 13
                        WHERE r.requirement_id IS NULL");
gate('W13-30', 'كلُّ إصلاحٍ بمتطلَّبِه الكاشفِ من نطاقِ المرحلة',
     $fxN > 0 && $fxBad === 0 && $fxOrphan === 0,
     "إصلاحات $fxN · ناقصةٌ $fxBad · بمتطلَّبٍ خارجَ النطاقِ $fxOrphan");

/* ══ W13-31 · رحلةُ الإثباتِ عبرت كاملةً بأثرٍ تجاريٍّ عند كلِّ مستهلك ═══ */
$run = (string) $one("SELECT run_id FROM repair01_w13_journey ORDER BY measured_at DESC LIMIT 1");
$jAll = (int) $one("SELECT COUNT(*) FROM repair01_w13_journey WHERE run_id = '" . $esc($run) . "'");
$jOk  = (int) $one("SELECT COUNT(*) FROM repair01_w13_journey
                     WHERE run_id = '" . $esc($run) . "' AND passed = 1");
$jNoEffect = (int) $one("SELECT COUNT(*) FROM repair01_w13_journey
                          WHERE run_id = '" . $esc($run) . "' AND business_effect = ''");
$jCons = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w13_journey
                      WHERE run_id = '" . $esc($run) . "'");
$jComp = (int) $one("SELECT COUNT(DISTINCT company_id) FROM repair01_w13_journey
                      WHERE run_id = '" . $esc($run) . "'");
$jLegs = (int) $one("SELECT COUNT(DISTINCT leg) FROM repair01_w13_journey
                      WHERE run_id = '" . $esc($run) . "'");
gate('W13-31', 'رحلةُ العاملِ عبرت كاملةً بأثرٍ تجاريٍّ عند كلِّ مستهلك',
     $run !== '' && $jAll > 0 && $jOk === $jAll && $jNoEffect === 0 && $jCons > $jLegs && $jComp === 1,
     "الجولة $run · محطّات $jOk/$jAll · أشواط $jLegs · مستهلكون $jCons · كياناتٌ $jComp"
     . " · بلا أثرٍ تجاريٍّ $jNoEffect");

/* ══ W13-32 · أثرُ الرحلةِ مكنوسٌ — لا صفَّ اختبارٍ باقٍ في الحيّ ════════ */
$leftJ = 0;
foreach (array("SELECT COUNT(*) FROM tkt_party WHERE src_ref LIKE 'W13J%'",
               "SELECT COUNT(*) FROM tickets WHERE ticket_no LIKE 'W13J%'",
               "SELECT COUNT(*) FROM employees WHERE employee_code LIKE 'W13J%'",
               "SELECT COUNT(*) FROM hr_disciplinary_case WHERE case_no LIKE 'W13J%'",
               "SELECT COUNT(*) FROM payroll_deductions WHERE note LIKE 'W13J%'",
               "SELECT COUNT(*) FROM rec_vacancies WHERE vacancy_no LIKE 'W13J%'") as $q) {
    $leftJ += (int) $one($q);
}
gate('W13-32', 'أثرُ الرحلةِ مكنوسٌ ولا صفَّ اختبارٍ باقٍ في الحيّ', $leftJ === 0,
     "صفوفٌ باقيةٌ بوسمِ الرحلة $leftJ");

/* ══ W13-33 · نموُّ المرحلةِ لا يخلق معرِّفًا بديلًا (قاعدةُ W03) ════════
   ◆ **يُقاس بقاعدةِ W03 نفسِها**: عمودٌ نصّيٌّ يحمل اسمَ إنسانٍ في جدولٍ بلا
     مفتاحٍ صحيحٍ إلى `employees`. وجداولُ هذه الموجةِ **تحمل المفاتيحَ لا
     الأسماء** — والحاجبُ يسقط إن أُضيف عمودُ اسمٍ لاحقًا. */
$nameCols = array();
foreach ($W13T as $t) {
    $rc = @$conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $esc($t) . "'
                            AND DATA_TYPE IN ('varchar','char','text')
                            AND (COLUMN_NAME LIKE '%person_name%' OR COLUMN_NAME LIKE '%employee_name%'
                              OR COLUMN_NAME LIKE '%actor_name%' OR COLUMN_NAME = 'name'
                              OR COLUMN_NAME LIKE '%reporter_name%')");
    while ($rc && $nx = $rc->fetch_row()) { $nameCols[] = $t . '.' . $nx[0]; }
}
gate('W13-33', 'جداولُ الموجةِ تحمل مفاتيحَ الأشخاصِ لا أسماءَهم', count($nameCols) === 0,
     'عمودُ اسمِ إنسانٍ في جداولِ الموجة ' . count($nameCols)
     . (count($nameCols) ? ' ⇐ ' . implode('، ', $nameCols) : ''));

/* ═══════════════════════════════════════════════════════════════════════════
   العرضُ والحكم
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n";
foreach ($rows as $r) {
    printf("%s %-8s %s\n     %s\n", $r[2] ? '✔' : '✘', $r[0], $r[1], $r[3]);
}
echo str_repeat('─', 110) . "\n";
printf("W13 gate: %d/%d  ·  طرفٌ من الأربعةِ مدموجٌ %d · تنفيذُ حلٍّ مملوكٌ للبلاغاتِ %d  ·  رحلةٌ %d/%d\n",
    $pass, $pass + $fail, $mg['total'], $cr['total'], $jOk, $jAll);
echo $fail === 0 ? "الحكم: خضراء ✔\n" : "الحكم: ساقطة ✘\n";
exit($fail === 0 ? 0 : 1);
