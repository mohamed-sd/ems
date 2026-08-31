<?php
/**
 * tools/repair01_w11_gate.php — بوّابةُ المرحلةِ الحاديةَ عشرة (دفاترُ الكيانات)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ حاجبٍ يُعيد القياسَ ولا يقرأ ما خزّنَته أداةُ الاشتقاق** — والمقامُ
 *   يُعاد بناؤه في كلِّ تشغيل. وحاجبٌ يفحص ما يضمنه المخطَّطُ **أعمى بالبناء**،
 *   فالحواجبُ هنا ترسو على ما **تكتبه الخدماتُ والشاشاتُ** لا على `CHECK` وحدَه.
 *
 * ◆ **وحارسُ الخلاءِ مبنيٌّ من البداية**: مجموعةٌ خاويةٌ تُخضِرُّ الحاجبَ على
 *   «تطابقِ لا شيء» — وهو النمطُ الذي كلَّف W01 و W07 و W09 جولاتِ إصلاح.
 *   فالصفرُ يمرُّ **مُعلَنًا بقرارٍ وحدَه** (`W11-D-08`) ويسقط بلا إعلان.
 *
 * التشغيل: php tools/repair01_w11_gate.php
 * الخروج : 0 كلُّ الحواجبِ خضراء · 1 حاجبٌ ساقط
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w11_scan.php';
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
$one = function ($sql) use ($conn) { return repair01_w11_one($conn, $sql); };

$pass = 0; $fail = 0; $rows = array();
function gate($id, $title, $ok, $detail)
{
    global $pass, $fail, $rows;
    if ($ok) { $pass++; } else { $fail++; }
    $rows[] = array($id, $title, $ok, $detail);
}

echo "═══════ بوّابةُ المرحلةِ الحاديةَ عشرة — REPAIR01 · دفاترُ الكيانات ═══════\n";

/* ── حارسُ الخلاءِ: الصفرُ يمرُّ مُعلَنًا وحدَه ────────────────────────── */
$emptyDeclared = (int) $one("SELECT COUNT(*) FROM repair01_w11_decisions
                              WHERE decision_id = 'W11-D-08' AND COALESCE(rationale, '') <> ''");
$vac = function ($n) use ($emptyDeclared) { return ((int) $n === 0 && $emptyDeclared === 0); };
$vacTag = $emptyDeclared ? 'خلاءٌ مُعلَنٌ في W11-D-08 ✔' : '**خلاءٌ غيرُ مُعلَن**';

/* ══ W11-01 · كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع ═══════════════════════════ */
$reqN   = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 11");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w11_scope");
$noRule = (int) $one("SELECT COUNT(*) FROM repair01_w11_scope WHERE map_rule = '' OR map_why = ''");
$orphan = (int) $one("SELECT COUNT(*) FROM repair01_w11_scope s
                       LEFT JOIN repair01_requirements r ON r.requirement_id = s.requirement_id
                                                        AND r.stage_no = 11
                      WHERE r.requirement_id IS NULL");
gate('W11-01', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $reqN > 0 && $scopeN === $reqN && $noRule === 0 && $orphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $noRule · مِرساةٌ يتيمةٌ $orphan");

/* ══ W11-02 · المِرساةُ مُثبَتةٌ من القرصِ لا مُعلَنةٌ فقط ══════════════ */
$ANCH = repair01_w11_anchors();
$proven = 0; $unproven = array();
foreach ($ANCH as $rid => $a) {
    $pr = repair01_w11_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $proven++; } else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }
}
$bookProven = (int) $one("SELECT COUNT(*) FROM repair01_w11_scope WHERE map_rule LIKE 'W11_ROUTE%'");
gate('W11-02', 'المِرساةُ مُثبَتةٌ من القرصِ والدفترُ يطابق المقيس',
     $proven === count($ANCH) && count($unproven) === 0 && $bookProven === $proven,
     'مُثبَتةٌ ' . $proven . ' من ' . count($ANCH) . ' · في الدفتر ' . $bookProven
     . ' · لم تُثبَت ' . count($unproven) . (count($unproven) ? ' ⇐ ' . implode('، ', array_slice($unproven, 0, 3)) : ''));

/* ══ W11-03 · مالكُ السطحِ المخالفُ مُعلَنٌ بقرار ═══════════════════════ */
$mis    = (int) $one("SELECT COUNT(*) FROM repair01_w11_scope WHERE owner_verdict = 'MISMATCH'");
$misDec = (int) $one("SELECT scope_rows FROM repair01_w11_decisions WHERE decision_id = 'W11-D-04'");
gate('W11-03', 'مالكُ السطحِ المخالفُ مُعلَنٌ بقرارٍ يحمل عددَه', $mis === $misDec,
     "سطحٌ تحت مالكٍ غيرِ مالكِ المتطلَّب $mis · المُعلَنُ في W11-D-04 $misDec");

/* ══ W11-04 · سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح ═══════════════════════ */
$routes = array();
foreach ($ANCH as $a) { if ($a['route'] !== '') { $routes[$a['route']] = true; } }
$sbN   = (int) $one("SELECT COUNT(*) FROM repair01_w11_sidebar");
$sbBad = (int) $one("SELECT COUNT(*) FROM repair01_w11_sidebar
                      WHERE s1_verdict='' OR s1_rule='' OR s2_verdict='' OR s2_rule=''
                         OR s3_verdict='' OR s3_rule='' OR s4_verdict='' OR s4_rule=''
                         OR s5_verdict='' OR s5_rule='' OR s6_verdict='' OR s6_rule=''
                         OR s7_verdict='' OR s7_rule=''");
gate('W11-04', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح', $sbN === count($routes) && $sbBad === 0,
     'أسطحُ النطاقِ المُعادُ اشتقاقُها ' . count($routes) . " · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBad");

/* ══ W11-05 · الاسمُ والمجموعةُ **صُحِّحا** لا قِيسا فقط ═════════════════
   ◆ الخطوتانِ ② و③ **تصحيحٌ**: انحرافٌ باقٍ يعني أنَّ الخطوةَ قِيست ولم تُنفَّذ. */
$lblDrift = (int) $one("SELECT COUNT(*) FROM repair01_w11_sidebar WHERE s2_verdict <> 'LABEL_MATCH'");
$grpDrift = (int) $one("SELECT COUNT(*) FROM repair01_w11_sidebar WHERE s3_verdict <> 'GROUP_MATCH'");
gate('W11-05', 'الاسمُ والمجموعةُ صُحِّحا على السجلِّ ودورةِ العمل',
     $lblDrift === 0 && $grpDrift === 0 && $sbN > 0,
     "انحرافُ اسمٍ $lblDrift · انحرافُ مجموعةٍ $grpDrift · المقامُ $sbN");

/* ══ W11-06 · حارسُ عرضٍ ومنحُ صلاحيةٍ مقيسانِ لكلِّ سطح ════════════════ */
$noGrant = (int) $one("SELECT COUNT(*) FROM repair01_w11_sidebar WHERE s6_perm_rows = 0");
$noGuard = array();
foreach (array_keys($routes) as $rt) {
    $g = repair01_w11_guard_of($ROOT, $rt);
    if ($g['kind'] === 'NONE') { $noGuard[] = $rt; }
}
gate('W11-06', 'حارسُ عرضٍ ومنحٌ مقيسانِ لكلِّ سطح', count($noGuard) === 0 && $noGrant === 0,
     'سطحٌ بلا حارسٍ ' . count($noGuard) . " · سطحٌ بلا منحٍ $noGrant"
     . (count($noGuard) ? ' ⇐ ' . implode('، ', array_slice($noGuard, 0, 2)) : ''));

/* ══ W11-07 · الترتيبُ من دورةِ العملِ لا من الأبجديّة ═══════════════════
   ◆ **والرسوُّ على البنيةِ لا على العبارة**: يُعاد اشتقاقُ الترتيبِ من موضعِ
     السطحِ في الدورةِ ويُقارَن بالمخزَّن — فترتيبٌ أبجديٌّ يسقط الحاجب. */
$noOrder = (int) $one("SELECT COUNT(*) FROM repair01_w11_sidebar WHERE s4_verdict <> 'ORDER_FROM_CYCLE'");
$stepMismatch = 0;
$stepOf = array();
foreach ($ANCH as $rid => $a) {
    if ($a['route'] === '') { continue; }
    if (!isset($stepOf[$a['route']]) || (int) $a['step'] < $stepOf[$a['route']]) {
        $stepOf[$a['route']] = (int) $a['step'];
    }
}
$r = $conn->query("SELECT route, s4_cycle_step FROM repair01_w11_sidebar");
while ($r && $x = $r->fetch_assoc()) {
    $rt = (string) $x['route'];
    if (!isset($stepOf[$rt]) || (int) $x['s4_cycle_step'] !== $stepOf[$rt]) { $stepMismatch++; }
}
gate('W11-07', 'الترتيبُ من دورةِ العملِ والموضعُ مُعادُ الاشتقاق',
     $noOrder === 0 && $stepMismatch === 0,
     "بلا مصدرِ ترتيبٍ $noOrder · موضعٌ يخالف الدورةَ $stepMismatch");

/* ══ W11-08 · كلُّ بندٍ مربوطٌ بمُعرِّفِ شاشتِه المعياريّ ═══════════════ */
$notLinked = (int) $one("SELECT COUNT(*) FROM repair01_w11_sidebar WHERE s7_linked = 0");
gate('W11-08', 'كلُّ بندٍ مربوطٌ بمُعرِّفِ شاشتِه المعياريّ', $notLinked === 0,
     "أسطحُ النطاقِ $sbN · غيرُ مربوطٍ $notLinked");

/* ══ W11-09 · أسطحُ النموِّ مبنيّةٌ ومحروسةٌ وموصولةٌ ومختومة ═══════════ */
$grow = array(); $growBad = array();
$r = $conn->query("SELECT screen_id, route, guard_kind FROM repair01_screen_registry WHERE origin = 'W11'");
while ($r && $x = $r->fetch_assoc()) {
    $grow[] = $x['route'];
    if (!is_file($ROOT . '/' . $x['route'])) { $growBad[] = $x['route'] . ' (لا ملفَّ)'; continue; }
    $g = repair01_w11_guard_of($ROOT, $x['route']);
    if ($g['kind'] === 'NONE' || $x['guard_kind'] !== $g['kind']) {
        $growBad[] = $x['route'] . ' (حارس ' . $g['kind'] . ')'; continue;
    }
    $nav = (int) $one("SELECT COUNT(*) FROM nav_items WHERE route = '" . $esc($x['route']) . "' AND active = 1");
    if ($nav === 0) { $growBad[] = $x['route'] . ' (بلا بندِ قائمة)'; }
}
$declared = count(repair01_w11_new_surfaces());
gate('W11-09', 'أسطحُ النموِّ مبنيّةٌ ومحروسةٌ وموصولةٌ ومختومة',
     count($grow) === $declared && count($growBad) === 0,
     'المُعلَنُ ' . $declared . ' · المختومُ ' . count($grow) . ' · مخالفٌ ' . count($growBad)
     . (count($growBad) ? ' ⇐ ' . implode('، ', array_slice($growBad, 0, 2)) : ''));

/* ══ W11-10 · آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ صريحٍ مُسبَّب ═════════════ */
$stE  = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w11_states");
$stF  = (int) $one("SELECT COUNT(*) FROM repair01_w11_states WHERE allowed = 0");
$stNo = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w11_states
                     WHERE entity NOT IN (SELECT entity FROM repair01_w11_states WHERE allowed = 0)");
$stBad = (int) $one("SELECT COUNT(*) FROM repair01_w11_states
                      WHERE (allowed = 0 AND forbid_reason = '')
                         OR (allowed = 1 AND (owner_role = '' OR precondition = '' OR official_doc = ''
                             OR approval_gate = '' OR reopen_rule = '' OR correct_rule = ''))");
gate('W11-10', 'آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ صريحٍ مُسبَّب',
     $stE >= 13 && $stF > 0 && $stNo === 0 && $stBad === 0,
     "كيانات $stE · ممنوعٌ صراحةً $stF · كيانٌ بلا ممنوعٍ $stNo · انتقالٌ ناقصُ الحقول $stBad");

/* ══ W11-11 · فصلُ الواجباتِ منفَّذٌ برمزِ ردٍّ مُثبَتٍ من القرص ═══════ */
$sodN = (int) $one("SELECT COUNT(*) FROM repair01_w11_sod");
$sodBad = array();
foreach (repair01_w11_sod_codes() as $key => $c) {
    $row = $conn->query("SELECT enforced_by FROM repair01_w11_sod
                          WHERE process_key = '" . $esc($key) . "' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row || (string) $row['enforced_by'] !== $c['code']) { $sodBad[] = $key . ' (بلا رمزٍ في الدفتر)'; continue; }
    $src = is_file($ROOT . '/' . $c['file']) ? (string) file_get_contents($ROOT . '/' . $c['file']) : '';
    if (strpos($src, "'" . $c['code'] . "'") === false) { $sodBad[] = $key . ' (' . $c['code'] . ' غيرُ مُنفَّذ)'; }
}
$sodEmpty = (int) $one("SELECT COUNT(*) FROM repair01_w11_sod
                         WHERE forbidden_combo = '' OR enforced_by = '' OR authority_rule_id = ''");
gate('W11-11', 'فصلُ الواجباتِ منفَّذٌ برمزِ ردٍّ مُثبَتٍ من القرص',
     $sodN === count(repair01_w11_sod_codes()) && count($sodBad) === 0 && $sodEmpty === 0,
     "عملياتٌ حرِجة $sodN · غيرُ منفَّذةٍ " . count($sodBad) . " · ناقصةُ الحقول $sodEmpty"
     . (count($sodBad) ? ' ⇐ ' . implode('، ', array_slice($sodBad, 0, 2)) : ''));

/* ══ W11-12 · لا إقفالَ ولا قيدَ ولا حسابَ بنكيَّ بلا كيانٍ قانونيّ ════ */
$notScoped = array();
foreach (repair01_w11_entity_tables() as $t) {
    if (!repair01_w11_entity_scoped($conn, $t)) { $notScoped[] = $t; }
}
foreach (repair01_w11_live_entity_tables() as $t) {
    if (!repair01_w11_table_exists($conn, $t)) { $notScoped[] = $t . ' (غير موجود)'; }
}
$noEntityRows = repair01_w11_close_without_entity($conn);
gate('W11-12', 'لا إقفالَ ولا قيدَ ولا حسابَ بنكيَّ بلا كيانٍ قانونيّ',
     count($notScoped) === 0 && $noEntityRows === 0,
     'جداولُ النطاقِ ' . count(repair01_w11_entity_tables()) . ' · بلا كيانٍ إلزاميٍّ '
     . count($notScoped) . " · صفٌّ حيٌّ بلا كيانٍ $noEntityRows"
     . (count($notScoped) ? ' ⇐ ' . implode('، ', array_slice($notScoped, 0, 3)) : ''));

/* ══ W11-13 · §48 لا نطاقَ يكتب قيدًا — والقيدُ على طلبٍ مقبولٍ وحدَه ══ */
$fromFin  = repair01_w11_entry_from_non_finance($conn);
$noAccept = repair01_w11_posted_without_accept($conn);
$hasChk   = repair01_w11_check_exists($conn, 'acc_recognition_request', 'chk_recreq_src');
$svc = (string) @file_get_contents($ROOT . '/app/Services/Finance/AccountingCycleService.php');
$hasCode = strpos($svc, "'SCOPE_WRITES_ENTRY'") !== false
        && strpos($svc, "'POST_WITHOUT_ACCEPTED_REQUEST'") !== false;
gate('W11-13', 'النطاقُ يطلب والماليّةُ تقرّر وتثبّت — بحارسَينِ لا بنصّ',
     $fromFin === 0 && $noAccept === 0 && $hasChk && $hasCode,
     "طلبٌ مصدرُه الماليّةُ $fromFin · مُرحَّلٌ بلا قبولٍ $noAccept · قيدُ القاعدةِ "
     . ($hasChk ? 'قائم' : 'غائب') . ' · رمزا الردِّ ' . ($hasCode ? 'منفَّذان' : 'غائبان'));

/* ══ W11-14 · قيدٌ غيرُ متوازنٍ في الدفترِ صفر ═════════════════════════ */
$unbal = repair01_w11_unbalanced_entries($conn);
$entriesN = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE state = 'posted'");
gate('W11-14', 'كلُّ قيدٍ مرحَّلٍ متوازن', $unbal === 0 && $entriesN > 0,
     "قيودٌ مرحَّلةٌ $entriesN · غيرُ متوازنٍ $unbal");

/* ══ W11-15 · فترةٌ مقفلةٌ لا تقبل ترحيلًا ═════════════════════════════ */
$badClose = repair01_w11_closed_but_postable($conn);
$closedN  = (int) $one("SELECT COUNT(*) FROM fin_financial_periods WHERE state IN ('closed','locked')");
gate('W11-15', 'الفترةُ المقفلةُ لا تقبل ترحيلًا — إثباتٌ لا إعلان',
     $badClose === 0 && !$vac($closedN),
     "فتراتٌ مقفلةٌ $closedN · تقبل الترحيلَ $badClose"
     . ($closedN === 0 ? ' · ' . $vacTag : ''));

/* ══ W11-16 · مطابقةٌ مقفلةٌ وفيها فرقٌ مفتوحٌ صفر — على الطرفَين ══════ */
$recBad  = repair01_w11_recon_closed_with_open($conn);
$bankBad = repair01_w11_bank_closed_with_open($conn);
gate('W11-16', 'لا إقفالَ لمطابقةٍ وفيها فرقٌ مفتوح', $recBad === 0 && $bankBad === 0,
     "مطابقةُ حسابٍ مقفلةٌ بفرقٍ $recBad · كشفٌ بنكيٌّ مقفلٌ بفرقٍ $bankBad");

/* ══ W11-17 · الجردُ بلجنةٍ والعهدةُ لا تتجدّد قبل تسويتها ════════════ */
$noCom = repair01_w11_count_without_committee($conn);
$dbl   = repair01_w11_custody_double_open($conn);
$chkCom = repair01_w11_check_exists($conn, 'tre_cash_count', 'chk_cc_committee');
gate('W11-17', 'الجردُ بلجنةٍ والعهدةُ لا تتجدّد قبل تسويتِها',
     $noCom === 0 && $dbl === 0 && $chkCom,
     "جردٌ معتمَدٌ بلجنةٍ ناقصةٍ $noCom · أمينٌ بعهدتَين مفتوحتَين $dbl · قيدُ اللجنةِ "
     . ($chkCom ? 'قائم' : 'غائب'));

/* ══ W11-18 · رقمٌ يخلط كيانَين بلا وسمٍ صفر ═══════════════════════════ */
$mixed = repair01_w11_mixed_untagged($conn);
$consolN = (int) $one("SELECT COUNT(*) FROM repair01_w11_consolidated");
$consolBad = (int) $one("SELECT COUNT(*) FROM repair01_w11_consolidated
                          WHERE entity_count > 1 AND (tag <> 'GROUP_PROJECTION'
                                OR tag_label_ar = '' OR read_owner = '' OR why = '')");
gate('W11-18', 'الرقمُ العابرُ للكياناتِ يُوسَم أو يُرفض',
     $mixed === 0 && $consolN > 0 && $consolBad === 0,
     "قيدٌ بوسمٍ غيرِ معروفٍ $mixed · أرقامٌ مسجَّلةٌ $consolN · مجمَّعٌ بلا وسمٍ كاملٍ $consolBad");

/* ══ W11-19 · العتبةُ من السجلِّ ولا مقارنةَ رقمٍ صلبةٍ في خدمة ════════ */
$thN  = (int) $one("SELECT COUNT(*) FROM repair01_w11_thresholds");
$thNo = (int) $one("SELECT COUNT(*) FROM repair01_w11_thresholds WHERE why = '' OR decision_ref = ''");
$hard = repair01_w11_hardcoded_thresholds($ROOT);
gate('W11-19', 'العتبةُ من السجلِّ ولا مقارنةَ رقمٍ صلبةٍ في خدمة',
     $thN > 0 && $thNo === 0 && count($hard) === 0,
     "عتباتٌ مسجَّلة $thN · بلا سببٍ أو قرارٍ $thNo · مقارنةٌ صلبةٌ " . count($hard)
     . (count($hard) ? ' ⇐ ' . implode('، ', array_slice($hard, 0, 2)) : ''));

/* ══ W11-20 · حدثُ النطاقِ بعقدٍ كاملٍ وناشرٍ مُثبَتٍ من القرص ═════════ */
$EV = repair01_w11_stage_events();
$evBad = array();
foreach ($EV as $code) {
    $row = $conn->query("SELECT trigger_rule, min_payload, consumer_list, consumer_effect,
                                preconditions, failure_policy, compensation, idempotency_key, contract_status
                           FROM repair01_events WHERE event_code = '" . $esc($code) . "' AND wave = 'W11' LIMIT 1");
    $row = $row ? $row->fetch_assoc() : null;
    if (!$row) { $evBad[] = $code . ' (بلا عقد)'; continue; }
    foreach (array('trigger_rule', 'min_payload', 'consumer_list', 'consumer_effect',
                   'preconditions', 'failure_policy', 'compensation', 'idempotency_key') as $f) {
        if (trim((string) $row[$f]) === '') { $evBad[] = $code . ' (' . $f . ' فارغ)'; continue 2; }
    }
    if ((string) $row['contract_status'] !== 'RECORDED') { $evBad[] = $code . ' (غيرُ مسجَّل)'; continue; }
    /* الناشرُ مُثبَتٌ من القرصِ لا مُعلَنٌ في الدفتر */
    $pub = $ROOT . '/' . repair01_w11_event_publisher($code);
    $src = is_file($pub) ? (string) file_get_contents($pub) : '';
    if (strpos($src, "'" . $code . "'") === false) { $evBad[] = $code . ' (بلا ناشرٍ في الشيفرة)'; continue; }
    /* والمستهلكُ نشِطٌ في الجذر — والنشرُ بلا مشتركٍ مرفوضٌ فيه */
    $sub = (int) $one("SELECT COUNT(*) FROM event_consumers
                        WHERE event_name = '" . $esc($code) . "' AND active = 1");
    if ($sub === 0) { $evBad[] = $code . ' (بلا مستهلكٍ نشط)'; }
}
gate('W11-20', 'حدثُ النطاقِ بعقدٍ كاملٍ وناشرٍ ومستهلكٍ مُثبَتَين',
     count($evBad) === 0 && count($EV) > 0,
     'أحداثُ النطاقِ ' . count($EV) . ' · ناقصٌ ' . count($evBad)
     . (count($evBad) ? ' ⇐ ' . implode('، ', array_slice($evBad, 0, 3)) : ''));

/* ══ W11-21 · التسوياتُ ليست تحت الذممِ الدائنةِ وحدَها (§٤-٤) ═════════
   ◆ **الرسوُّ على المجموعةِ المقيسةِ لا على الدعوى**: الاستحقاقاتُ والمقدَّماتُ
     والمخصَّصاتُ والأصولُ الثابتةُ والضرائبُ لها مجموعتُها في السجلِّ المعياريّ. */
$apGroup = (int) $one("SELECT COUNT(*) FROM repair01_w11_scope
                        WHERE requirement_id IN ('ACC-17','ACC-18','ACC-19','ACC-20')
                          AND group_name = 'الذمم الدائنة'");
$adjStep = (int) $one("SELECT COUNT(*) FROM repair01_w11_scope
                        WHERE requirement_id IN ('ACC-17','ACC-18','ACC-19') AND cycle_step = 4");
$adjTable = repair01_w11_table_exists($conn, 'acc_period_adjustment');
gate('W11-21', 'التسوياتُ في موضعِها من الدورةِ لا تحت الذممِ الدائنة',
     $apGroup === 0 && $adjStep === 3 && $adjTable,
     "تسويةٌ تحت الذممِ الدائنةِ $apGroup · في خطوةِ التسوياتِ $adjStep من 3 · جدولُها "
     . ($adjTable ? 'قائم' : 'غائب'));

/* ══ W11-22 · السجلُّ التابعُ يُعرَض في شاشةِ أبيه لا في سطحٍ مستقلّ ═══ */
$childBad = array();
$CHILD = array(
    'Finance/ar_claim_invoice.php'       => 'acc_invoice_line',
    'Finance/dues_fin.php'               => 'acc_supplier_accrual_line',
    'Finance/bank_reconciliation_fin.php' => 'tre_recon_difference',
);
/* ⚠ **الرسوُّ على الاسمِ المقتبَسِ لا على جزءٍ منه**: البحثُ عن `acc_invoice_line`
     يصيب أيضًا `acc_invoice_line_removed` — فيبقى الحاجبُ أخضرَ وقد نُزع السجلُّ
     التابعُ فعلًا. والفحصُ السلبيُّ كشف هذا العمى، فالرسوُّ على `'اسم'` مقتبَسًا
     كما يُكتب في نداءِ البوّابة. */
foreach ($CHILD as $rt => $tbl) {
    $src = is_file($ROOT . '/' . $rt) ? (string) file_get_contents($ROOT . '/' . $rt) : '';
    if (strpos($src, "'" . $tbl . "'") === false) { $childBad[] = $rt . ' ⇐ ' . $tbl; }
}
gate('W11-22', 'السجلُّ التابعُ في شاشةِ أبيه لا في سطحٍ مستقلّ', count($childBad) === 0,
     'سجلّاتٌ تابعةٌ ' . count($CHILD) . ' · غيرُ معروضةٍ في أبيها ' . count($childBad)
     . (count($childBad) ? ' ⇐ ' . implode('، ', $childBad) : ''));

/* ══ W11-23 · بندُ قائمةِ الإقفالِ لا يكفُّ عن الحجبِ بلا سببٍ مكتوب ═══ */
$noWhy = (int) $one("SELECT COUNT(*) FROM fin_closing_items
                      WHERE required = 1 AND blocks_close = 0 AND item_state = 'pending'
                        AND COALESCE(exception_reason, '') = ''");
$hasCols = repair01_w11_col_exists($conn, 'fin_closing_items', 'exception_reason')
        && repair01_w11_col_exists($conn, 'fin_closing_items', 'blocks_close');
gate('W11-23', 'لا بندَ يكفُّ عن الحجبِ بلا سببٍ مكتوب', $noWhy === 0 && $hasCols,
     "بندٌ غيرُ حاجبٍ بلا سببٍ $noWhy · أعمدةُ الاستثناءِ " . ($hasCols ? 'قائمة' : 'غائبة'));

/* ══ W11-24 · إعادةُ الفتحِ استثناءٌ محكومٌ لا فعلٌ عاديّ ═══════════════ */
$roN   = (int) $one("SELECT COUNT(*) FROM acc_period_reopen_request");
$roBad = (int) $one("SELECT COUNT(*) FROM acc_period_reopen_request
                      WHERE justification = '' OR authority_rule_id = '' OR scope_units = ''");
$roSelf = (int) $one("SELECT COUNT(*) FROM acc_period_reopen_request
                       WHERE state IN ('approved','applied','reclosed')
                         AND approved_by = requested_by AND approved_by > 0");
$chkRo = repair01_w11_check_exists($conn, 'acc_period_reopen_request', 'chk_reopen_full');
gate('W11-24', 'إعادةُ الفتحِ استثناءٌ محكومٌ بمبرّرٍ ونطاقٍ وسلطة',
     $roBad === 0 && $roSelf === 0 && $chkRo && !$vac($roN),
     "طلباتٌ $roN · ناقصةُ الحوكمةِ $roBad · اعتمدها طالبُها $roSelf · قيدُ القاعدةِ "
     . ($chkRo ? 'قائم' : 'غائب') . ($roN === 0 ? ' · ' . $vacTag : ''));

/* ══ W11-25 · قاموسُ الرموزِ يغطّي ما تعرضه أسطحُ النطاق ═══════════════ */
$dictN = (int) $one("SELECT COUNT(*) FROM repair01_w6_code_dict WHERE src_ref LIKE 'RPR-W11%'");
$dictBad = (int) $one("SELECT COUNT(*) FROM repair01_w6_code_dict
                        WHERE src_ref LIKE 'RPR-W11%' AND (display_ar = '' OR why = '')");
gate('W11-25', 'الرمزُ يبقى لاتينيًّا في السجلِّ ويُعرَض عربيًّا من القاموس',
     $dictN > 0 && $dictBad === 0,
     "رموزٌ مسجَّلةٌ للعرضِ $dictN · بلا مسمًّى أو سببٍ $dictBad");

/* ══ W11-26 · أساسُ المراحلِ السابقةِ لم يُمَسّ ═════════════════════════ */
$d0 = (int) $one("SELECT COUNT(*) FROM repair01_decisions");
$s0 = (int) $one("SELECT COUNT(*) FROM repair01_source_files");
$u0 = (int) $one("SELECT COUNT(*) FROM repair01_surfaces");
$BASE = "'SURFACES','DISK','NAV'";
$g0    = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin IN ($BASE)");
$gNew  = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin NOT IN ($BASE)");
$gWild = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                      WHERE origin NOT IN ($BASE) AND origin NOT REGEXP '^W[0-9]{2}$' AND origin <> 'BUILD'");
$t0 = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = ''");
$e0 = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = ''");
gate('W11-26', 'أساسُ المراحلِ السابقةِ لم يُمَسّ',
     $d0 === $W00['decisions'] && $s0 === $W00['source_files'] && $u0 === $W00['surfaces'] && $g0 === $W00['registry_base'] && $gWild === 0 && $t0 === $W00['gaps_original'] && $e0 === $W00['events_study'],
     "قرارات $d0 · مصادر $s0 · أسطح $u0 · أساسُ السجلّ $g0 · نموٌّ مختومٌ $gNew"
     . " · نموٌّ بلا ختمٍ $gWild · فجواتٌ أصليّة $t0 · أحداثُ الدراسة $e0");

/* ══ W11-27 · رحلةُ الإقفالِ تعبر ولا تترك أثرًا ═══════════════════════ */
$jOut = array(); $jCode = 1;
@exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_w11_journey.php" 2>&1', $jOut, $jCode);
/* ⚠ مُعرِّفُ الجولةِ من المخرَجِ لا من «آخرِ صفٍّ» — رحلةٌ لم تنعقد تترك سابقتَها */
$run = '';
foreach ($jOut as $l) { if (preg_match('/^RUN=(W11J-\d+)$/', trim($l), $m)) { $run = $m[1]; break; } }
$jTotal = (int) $one("SELECT COUNT(*) FROM repair01_w11_journey WHERE run_id = '" . $esc($run) . "'");
$jPass  = (int) $one("SELECT COUNT(*) FROM repair01_w11_journey WHERE run_id = '" . $esc($run) . "' AND passed = 1");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w11_journey
                       WHERE run_id = '" . $esc($run) . "' AND (business_effect = '' OR business_effect = '—')");
$jCons  = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w11_journey WHERE run_id = '" . $esc($run) . "'");
$jLegs  = (int) $one("SELECT COUNT(DISTINCT leg) FROM repair01_w11_journey WHERE run_id = '" . $esc($run) . "'");
$jCo    = (int) $one("SELECT COUNT(DISTINCT company_id) FROM repair01_w11_journey
                       WHERE run_id = '" . $esc($run) . "' AND company_id > 0");
$jLeft  = (int) $one("SELECT COUNT(*) FROM acc_recognition_request WHERE request_no LIKE 'W11J-%'")
        + (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE entry_no LIKE 'W11J-%'")
        + (int) $one("SELECT COUNT(*) FROM tre_cash_count WHERE count_no LIKE 'W11J-%'")
        + (int) $one("SELECT COUNT(*) FROM fin_financial_periods WHERE reopen_reason LIKE 'W11J-%'");
gate('W11-27', 'رحلةُ الإقفالِ تعبر لكيانٍ واحدٍ ولا تترك أثرًا',
     $jCode === 0 && $run !== '' && $jTotal >= 60 && $jPass === $jTotal
     && $jCons >= 15 && $jLegs >= 6 && $jCo === 1 && $jNoEff === 0 && $jLeft === 0,
     'الجولة ' . ($run !== '' ? $run : '— لم تُعلَن —')
     . " · عابرٌ $jPass/$jTotal · أشواطٌ $jLegs · مستهلكونَ متمايزون $jCons"
     . " · كياناتٌ $jCo · بلا أثرٍ تجاريٍّ $jNoEff · أثرٌ باقٍ $jLeft"
     . ($jCode !== 0 ? ' · رمزُ الخروج ' . $jCode : ''));

/* ══ W11-28 · قراراتُ المرحلةِ مكتملةٌ بسؤالِها وحكمِها وعلّتِها ════════ */
$decN   = (int) $one("SELECT COUNT(*) FROM repair01_w11_decisions");
$decBad = (int) $one("SELECT COUNT(*) FROM repair01_w11_decisions
                       WHERE question = '' OR ruling = '' OR COALESCE(rationale, '') = ''");
$fixN   = (int) $one("SELECT COUNT(*) FROM repair01_w11_fixes");
$fixBad = (int) $one("SELECT COUNT(*) FROM repair01_w11_fixes
                       WHERE revealed_by = '' OR reveal_why = '' OR evidence = ''");
$fixOrph = (int) $one("SELECT COUNT(*) FROM repair01_w11_fixes f
                        LEFT JOIN repair01_requirements r ON r.requirement_id = f.revealed_by
                                                         AND r.stage_no = 11
                       WHERE r.requirement_id IS NULL");
gate('W11-28', 'كلُّ قرارٍ بعلّتِه وكلُّ إصلاحٍ بمتطلَّبِه الكاشف',
     $decN >= 10 && $decBad === 0 && $fixN > 0 && $fixBad === 0 && $fixOrph === 0,
     "قرارات $decN · ناقصةٌ $decBad · إصلاحات $fixN · بلا كاشفٍ $fixBad · كاشفٌ خارجَ المرحلةِ $fixOrph");

/* ═══════════════════════ الطباعة ═══════════════════════ */
foreach ($rows as $x) {
    printf("  %s %-9s %-48s %s\n", $x[2] ? '✔' : '✘', $x[0], $x[1], $x[3]);
}
echo str_repeat('─', 124) . "\n";
printf("W11 gate: %d/%d  ·  إقفالٌ بلا company_id %d  ·  رقمٌ مختلطٌ غيرُ موسومٍ %d"
     . "  ·  قيدٌ من نطاقٍ غيرِ الماليّة %d  ·  رحلةٌ %d/%d\n",
    $pass, $pass + $fail, $noEntityRows, $mixed, $fromFin, $jPass, $jTotal);
echo ($fail === 0) ? "الحكم: خضراء ✔\n" : "الحكم: ساقطة ✘\n";
exit($fail === 0 ? 0 : 1);
