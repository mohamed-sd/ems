<?php
/**
 * tools/repair01_w9_gate.php — بوّابةُ المرحلةِ التاسعة (المشتريات والمخازن)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ حاجبٍ يُعيد القياسَ ولا يقرأ ما خزّنَته أداةُ الاشتقاق** — والمقامُ
 *   يُعاد بناؤه في كلِّ تشغيل. وحاجبٌ يفحص ما يضمنه المخطَّطُ **أعمى بالبناء**.
 *
 * ◆ **وحارسُ الخلاءِ مبنيٌّ من البداية**: مجموعةٌ خاويةٌ تُخضِرُّ الحاجبَ على
 *   «تطابقِ لا شيء» — وهو النمطُ الذي كلَّف W01 و W07 جولةَ إصلاح. فالصفرُ
 *   يمرُّ **مُعلَنًا بقرارٍ وحدَه** (`W9-D-09`) ويسقط بلا إعلان.
 *
 * ◆ **والمرحلةُ مفتوحةٌ بقرارِ المالك**: `DEC-OPEN-15` حاجبٌ بنيويٌّ لم يُجَب،
 *   والبناءُ مضى بأمرٍ مكتوب. فـ`W9-24` **يقفل الاتّجاهَين**: تأجيلٌ بلا
 *   تسجيلٍ خرقٌ، وتسجيلٌ باقٍ بعد الجوابِ تقادُم. والحكمُ النهائيُّ يقول
 *   **«خضراءُ المبنيِّ ومفتوحةٌ بالمؤجَّل»** — ولا يُعلَن إغلاق.
 *
 * التشغيل: php tools/repair01_w9_gate.php
 * الخروج : 0 كلُّ الحواجبِ خضراء · 1 حاجبٌ ساقط
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w9_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w9_one($conn, $sql); };

$pass = 0; $fail = 0; $rows = array();
function gate($id, $title, $ok, $detail)
{
    global $pass, $fail, $rows;
    if ($ok) { $pass++; } else { $fail++; }
    $rows[] = array($id, $title, $ok, $detail);
}

echo "═══════ بوّابةُ المرحلةِ التاسعة — REPAIR01 · المشتريات والمخازن ═══════\n";

/* ── حارسُ الخلاءِ: الصفرُ يمرُّ مُعلَنًا وحدَه ────────────────────────── */
$emptyDeclared = (int) $one("SELECT COUNT(*) FROM repair01_w9_decisions
                              WHERE decision_id = 'W9-D-09' AND COALESCE(rationale, '') <> ''");
$vac = function ($n) use ($emptyDeclared) { return ((int) $n === 0 && $emptyDeclared === 0); };
$vacTag = $emptyDeclared ? 'خلاءٌ مُعلَنٌ في W9-D-09 ✔' : '**خلاءٌ غيرُ مُعلَن**';

/* ══ W9-01 · كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع ═══════════════════════════ */
$reqN = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 9");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w9_scope");
$noRule = (int) $one("SELECT COUNT(*) FROM repair01_w9_scope WHERE map_rule = '' OR map_why = ''");
$orphan = (int) $one("SELECT COUNT(*) FROM repair01_w9_scope s
                       LEFT JOIN repair01_requirements r ON r.requirement_id = s.requirement_id AND r.stage_no = 9
                      WHERE r.requirement_id IS NULL");
gate('W9-01', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $reqN > 0 && $scopeN === $reqN && $noRule === 0 && $orphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $noRule · مِرساةٌ يتيمةٌ $orphan");

/* ══ W9-02 · المِرساةُ مُثبَتةٌ من القرصِ لا مُعلَنةٌ فقط ═══════════════ */
$ANCH = repair01_w9_anchors();
$proven = 0; $unproven = array();
foreach ($ANCH as $rid => $a) {
    $pr = repair01_w9_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $proven++; } else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }
}
$bookProven = (int) $one("SELECT COUNT(*) FROM repair01_w9_scope WHERE map_rule LIKE 'W9_ROUTE%'");
gate('W9-02', 'المِرساةُ مُثبَتةٌ من القرصِ والدفترُ يطابق المقيس',
     $proven === count($ANCH) && count($unproven) === 0 && $bookProven === $proven,
     'مُثبَتةٌ ' . $proven . ' من ' . count($ANCH) . ' · في الدفتر ' . $bookProven
     . ' · لم تُثبَت ' . count($unproven) . (count($unproven) ? ' ⇐ ' . implode('، ', array_slice($unproven, 0, 3)) : ''));

/* ══ W9-03 · مالكُ السطحِ المخالفُ مُعلَنٌ بقرار ═══════════════════════ */
$mis = (int) $one("SELECT COUNT(*) FROM repair01_w9_scope WHERE owner_verdict = 'MISMATCH'");
$misDec = (int) $one("SELECT scope_rows FROM repair01_w9_decisions WHERE decision_id = 'W9-D-04'");
gate('W9-03', 'مالكُ السطحِ المخالفُ مُعلَنٌ بقرارٍ يحمل عددَه',
     $mis === $misDec,
     "سطحٌ تحت مالكٍ غيرِ مالكِ المتطلَّب $mis · المُعلَنُ في W9-D-04 $misDec");

/* ══ W9-04 · سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح ═══════════════════════ */
$routes = array();
foreach ($ANCH as $a) { if ($a['route'] !== '') { $routes[$a['route']] = true; } }
$sbN = (int) $one("SELECT COUNT(*) FROM repair01_w9_sidebar");
$sbBad = (int) $one("SELECT COUNT(*) FROM repair01_w9_sidebar
                      WHERE s1_verdict='' OR s1_rule='' OR s2_verdict='' OR s2_rule=''
                         OR s3_verdict='' OR s3_rule='' OR s4_verdict='' OR s4_rule=''
                         OR s5_verdict='' OR s5_rule='' OR s6_verdict='' OR s6_rule=''
                         OR s7_verdict='' OR s7_rule=''");
gate('W9-04', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح',
     $sbN === count($routes) && $sbBad === 0,
     'أسطحُ النطاقِ المُعادُ اشتقاقُها ' . count($routes) . " · في الدفتر $sbN · خطوةٌ بلا حكمٍ أو قاعدةٍ $sbBad");

/* ══ W9-05 · كلُّ سطحٍ حيٍّ بمنحِ صلاحيةٍ مقيس ═════════════════════════ */
$noGrant = (int) $one("SELECT COUNT(*) FROM repair01_w9_sidebar WHERE s6_perm_rows = 0");
gate('W9-05', 'كلُّ سطحٍ حيٍّ بمنحِ صلاحيةٍ مقيس', $noGrant === 0, "سطحٌ بلا منحٍ واحد $noGrant");

/* ══ W9-06 · حارسُ عرضٍ مقيسٌ في ملفِّ كلِّ سطح ═══════════════════════ */
$noGuard = array();
foreach (array_keys($routes) as $rt) {
    $g = repair01_w9_guard_of($ROOT, $rt);
    if ($g['kind'] === 'NONE') { $noGuard[] = $rt; }
}
gate('W9-06', 'حارسُ عرضٍ مقيسٌ في ملفِّ كلِّ سطح', count($noGuard) === 0,
     'سطحٌ بلا حارسٍ مقيسٍ ' . count($noGuard) . (count($noGuard) ? ' ⇐ ' . implode('، ', array_slice($noGuard, 0, 2)) : ''));

/* ══ W9-07 · مصدرُ الترتيبِ من السجلِّ لا يدويًّا ══════════════════════ */
$noOrder = (int) $one("SELECT COUNT(*) FROM repair01_w9_sidebar WHERE s4_verdict = 'NO_ORDER_SOURCE'");
$orderDec = (int) $one("SELECT scope_rows FROM repair01_w9_decisions WHERE decision_id = 'W9-D-10'");
gate('W9-07', 'مصدرُ الترتيبِ من السجلِّ والفاقدُ مُعلَن', $noOrder === $orderDec,
     "بلا مصدرِ ترتيبٍ $noOrder · المُعلَنُ في W9-D-10 $orderDec");

/* ══ W9-08 · كلُّ بندٍ مربوطٌ بمُعرِّفِ شاشتِه المعياريّ ═══════════════ */
$notLinked = (int) $one("SELECT COUNT(*) FROM repair01_w9_sidebar WHERE s7_linked = 0");
gate('W9-08', 'كلُّ بندٍ مربوطٌ بمُعرِّفِ شاشتِه المعياريّ', $notLinked === 0,
     'أسطحُ النطاقِ ' . $sbN . " · غيرُ مربوطٍ $notLinked");

/* ══ W9-09 · أسطحُ النموِّ مبنيّةٌ ومحروسةٌ وموصولةٌ ومختومة ══════════ */
$grow = array(); $growBad = array();
$r = $conn->query("SELECT screen_id, route, guard_kind FROM repair01_screen_registry WHERE origin = 'W09'");
while ($r && $x = $r->fetch_assoc()) {
    $grow[] = $x['route'];
    if (!is_file($ROOT . '/' . $x['route'])) { $growBad[] = $x['route'] . ' (لا ملفَّ)'; continue; }
    $g = repair01_w9_guard_of($ROOT, $x['route']);
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
$declaredNew = count(repair01_w9_new_surfaces());
gate('W9-09', 'أسطحُ النموِّ مبنيّةٌ ومحروسةٌ وموصولة',
     count($grow) === $declaredNew && count($growBad) === 0,
     'أسطحُ النموِّ المختومةُ W09 ' . count($grow) . " من $declaredNew · ناقصةٌ " . count($growBad)
     . (count($growBad) ? ' ⇐ ' . implode('، ', array_slice($growBad, 0, 2)) : ''));

/* ══ W9-10 · آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ صريحٍ مُسبَّب ═════════════ */
$ents = repair01_w9_entity_types();
$stEnt = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w9_states");
$stAll = (int) $one("SELECT COUNT(*) FROM repair01_w9_states");
$stForbid = (int) $one("SELECT COUNT(*) FROM repair01_w9_states WHERE allowed = 0");
$noForbid = (int) $one("SELECT COUNT(*) FROM (SELECT entity FROM repair01_w9_states
                          GROUP BY entity HAVING SUM(allowed = 0) = 0) t");
$badAllow = (int) $one("SELECT COUNT(*) FROM repair01_w9_states WHERE allowed = 1
                         AND (owner_role='' OR precondition='' OR official_doc=''
                              OR approval_gate='' OR reopen_rule='' OR correct_rule='')");
$badForbid = (int) $one("SELECT COUNT(*) FROM repair01_w9_states WHERE allowed = 0 AND forbid_reason = ''");
gate('W9-10', 'آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ صريحٍ مُسبَّب',
     $stEnt === count($ents) && $noForbid === 0 && $badAllow === 0 && $badForbid === 0,
     'كيانات ' . $stEnt . ' من ' . count($ents) . " · انتقالات $stAll · ممنوعٌ صراحةً $stForbid"
     . " · كيانٌ بلا ممنوعٍ $noForbid · مسموحٌ ناقصُ الحقول $badAllow · ممنوعٌ بلا سبب $badForbid");

/* ══ W9-11 · فصلُ الواجباتِ منفَّذٌ برمزِ ردٍّ لا مُعلَنٌ فقط ═════════ */
$sodN = (int) $one("SELECT COUNT(*) FROM repair01_w9_sod");
$sodBad = (int) $one("SELECT COUNT(*) FROM repair01_w9_sod
                       WHERE initiator_role='' OR approver_role='' OR executor_role=''
                          OR closer_role='' OR forbidden_combo='' OR enforced_by=''");
$sodSelf = (int) $one("SELECT COUNT(*) FROM repair01_w9_sod WHERE approver_role = executor_role");
/* ورمزُ الردِّ **يُثبَت من القرص** لا يُصدَّق من السجلّ */
$svcSrc = '';
foreach (array('/app/Services/Procurement/ProcurementCycleService.php',
               '/app/Services/Warehouse/WarehouseCycleService.php') as $f) {
    if (is_file($ROOT . $f)) { $svcSrc .= (string) file_get_contents($ROOT . $f); }
}
$codeMissing = array();
$r = $conn->query("SELECT process_key, enforced_by FROM repair01_w9_sod");
while ($r && $x = $r->fetch_assoc()) {
    if (strpos($svcSrc, (string) $x['enforced_by']) === false) { $codeMissing[] = $x['process_key']; }
}
gate('W9-11', 'فصلُ الواجباتِ منفَّذٌ برمزِ ردٍّ مُثبَتٍ من القرص',
     $sodN > 0 && $sodBad === 0 && $sodSelf === 0 && count($codeMissing) === 0,
     "عملياتٌ $sodN · صفٌّ ناقصٌ $sodBad · معتمِدٌ هو المنفِّذُ $sodSelf · رمزُ ردٍّ بلا تنفيذٍ " . count($codeMissing)
     . (count($codeMissing) ? ' ⇐ ' . implode('، ', array_slice($codeMissing, 0, 2)) : ''));

/* ══ W9-12 · عدّاداتُ الحزمةِ مشتقّةٌ وتطابق مقامَها ═══════════════════ */
$pkgDrift = repair01_w9_package_drift($conn);
$pkgN = (int) $one("SELECT COUNT(*) FROM proc_package WHERE COALESCE(is_deleted,0)=0");
gate('W9-12', 'عدّاداتُ الحزمةِ مشتقّةٌ ومطابقةٌ — ولا خلاءَ صامت',
     count($pkgDrift) === 0 && !$vac($pkgN),
     "$vacTag · حزمٌ مقيسة $pkgN · تخالف مشتقَّها " . count($pkgDrift));

/* ══ W9-13 · أمرُ الشراءِ بلا سندٍ تنافسيٍّ مُعلَنٌ بعددِه ════════════ */
$noBasis = repair01_w9_orders_without_basis($conn);
$basisDec = (int) $one("SELECT scope_rows FROM repair01_w9_decisions WHERE decision_id = 'W9-D-03'");
gate('W9-13', 'أمرُ الشراءِ بلا سندٍ تنافسيٍّ مُعلَنٌ بعددِه المقيس',
     count($noBasis) === $basisDec,
     'أوامرُ بلا محضرٍ وبلا سببِ مباشرةٍ ' . count($noBasis) . " · المُعلَنُ في W9-D-03 $basisDec");

/* ══ W9-14 · الأدنى في المحضرِ مشتقٌّ ومطابق ═══════════════════════════ */
$awDrift = repair01_w9_award_drift($conn);
$awN = (int) $one("SELECT COUNT(*) FROM proc_award");
gate('W9-14', 'الأدنى في محضرِ الترسيةِ مشتقٌّ ومطابق — ولا خلاءَ صامت',
     count($awDrift) === 0 && !$vac($awN),
     "$vacTag · محاضرُ مقيسة $awN · تخالف مشتقَّها " . count($awDrift));

/* ══ W9-15 · أيّامُ التأخّرِ مشتقّةٌ ومطابقة ═══════════════════════════ */
$dlDrift = repair01_w9_delay_drift($conn);
$dlN = (int) $one("SELECT COUNT(*) FROM proc_delivery_event");
gate('W9-15', 'أيّامُ التأخّرِ مشتقّةٌ ومطابقة — ولا خلاءَ صامت',
     count($dlDrift) === 0 && !$vac($dlN),
     "$vacTag · أحداثُ توريدٍ مقيسة $dlN · تخالف مشتقَّها " . count($dlDrift));

/* ══ W9-16 · المطابقةُ ثلاثيّةٌ وسماحُها من السجلّ ═════════════════════ */
$TH = repair01_w9_thresholds($conn);
$hasTol = isset($TH['PRC_MATCH_TOLERANCE_PCT']);
$mtN = (int) $one("SELECT COUNT(*) FROM proc_invoice_match");
$mtBad = (int) $one("SELECT COUNT(*) FROM proc_invoice_match
                      WHERE within_tol = 0 AND verdict = 'MATCHED' AND COALESCE(var_decision,'') = ''");
gate('W9-16', 'المطابقةُ ثلاثيّةٌ وسماحُها من السجلِّ ولا مرورَ بلا قرار',
     $hasTol && $mtBad === 0,
     'عتبةُ السماحِ مسجَّلةٌ ' . ($hasTol ? 'نعم' : '**لا**') . " · مطابقاتٌ مقيسة $mtN"
     . " · خارجَ السماحِ ومرَّ بلا قرارٍ $mtBad");

/* ══ W9-17 · رصيدُ الحالةِ مشتقٌّ ومطابقٌ للحركات ═════════════════════ */
$ssDrift = repair01_w9_stock_state_drift($conn);
$ssN = (int) $one("SELECT COUNT(*) FROM proc_stock_state WHERE state_key = 'GOOD'");
$ssNoRule = (int) $one("SELECT COUNT(*) FROM proc_stock_state WHERE COALESCE(derive_rule,'') = ''");
gate('W9-17', 'رصيدُ الحالةِ مشتقٌّ ومطابقٌ للحركات — ولا خلاءَ صامت',
     count($ssDrift) === 0 && $ssNoRule === 0 && !$vac($ssN),
     "$vacTag · أسطرُ رصيدٍ صالحٍ مقيسة $ssN · تخالف مشتقَّها " . count($ssDrift) . " · بلا قاعدةٍ $ssNoRule");

/* ══ W9-18 · فرقُ الجردِ لا يُقفل بلا قرارِ تسويةٍ مُسبَّب ════════════ */
$cntOpen = repair01_w9_count_open_diffs($conn);
$csN = (int) $one("SELECT COUNT(*) FROM proc_count_session");
gate('W9-18', 'فرقُ الجردِ لا يُقفل بلا قرارِ تسويةٍ — ولا خلاءَ صامت',
     count($cntOpen) === 0 && !$vac($csN),
     "$vacTag · جلساتٌ مقيسة $csN · فرقٌ في جلسةٍ معتمَدةٍ بلا قرارٍ " . count($cntOpen));

/* ══ W9-19 · الإقفالُ الشهريُّ بمعادلةٍ تنطبق ═════════════════════════ */
$clsBad = repair01_w9_close_unbalanced($conn);
$clsN = (int) $one("SELECT COUNT(*) FROM proc_wh_close WHERE state = 'closed'");
gate('W9-19', 'الإقفالُ الشهريُّ بمعادلةٍ تنطبق — ولا خلاءَ صامت',
     count($clsBad) === 0 && !$vac($clsN),
     "$vacTag · إقفالاتٌ مقيسة $clsN · معادلةٌ لا تنطبق " . count($clsBad));

/* ══ W9-20 · العتبةُ من السجلِّ ولا مقارنةَ رقمٍ صلبة ═════════════════ */
$thN = count($TH);
$thBad = 0;
foreach ($TH as $t) { if ($t['why'] === '' || $t['ref'] === '') { $thBad++; } }
$hard = repair01_w9_hardcoded_thresholds($ROOT);
$thRead = (int) preg_match_all('~self::threshold\(~', $svcSrc);
gate('W9-20', 'العتبةُ من السجلِّ ولا مقارنةَ رقمٍ صلبةٍ في خدمة',
     $thN > 0 && $thBad === 0 && count($hard) === 0 && $thRead >= 4,
     "عتباتٌ مسجَّلة $thN · بلا عذرٍ أو مرجعٍ $thBad · مقارنةٌ صلبةٌ " . count($hard)
     . " · تُقرأ في الخدمةِ $thRead" . (count($hard) ? ' ⇐ ' . implode('، ', array_slice($hard, 0, 2)) : ''));

/* ══ W9-21 · حدثُ النطاقِ بعقدٍ كاملٍ وناشرٍ مُثبَتٍ من القرص ═════════ */
$EV = repair01_w9_stage_events();
$evN = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W09'");
$evBad = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W09'
                      AND (trigger_rule='' OR min_payload='' OR consumer_list='' OR consumer_effect=''
                           OR preconditions='' OR failure_policy='' OR compensation='' OR idempotency_key='')");
$evAll = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W09'
                      AND (consumer_list LIKE '%كل المستهلكين%' OR consumer_list LIKE '%كلُّ المستهلكين%')");
$evDup = (int) $one("SELECT COUNT(*) FROM (SELECT event_code FROM repair01_events WHERE wave = 'W09'
                       GROUP BY event_code HAVING COUNT(*) > 1) t");
$noPub = array();
foreach ($EV as $code) { if (strpos($svcSrc, "'" . $code . "'") === false) { $noPub[] = $code; } }
gate('W9-21', 'حدثُ النطاقِ بعقدٍ كاملٍ وناشرٍ مُثبَتٍ من القرص',
     $evN === count($EV) && $evBad === 0 && $evAll === 0 && $evDup === 0 && count($noPub) === 0,
     "أحداثُ النطاق $evN من " . count($EV) . " · عقدٌ ناقصٌ $evBad · «كلُّ المستهلكين» $evAll"
     . " · مكرَّرٌ $evDup · بلا ناشرٍ مُثبَتٍ " . count($noPub));

/* ══ W9-22 · مفرداتُ حركةِ المخزنِ حيّةٌ لا مخترَعة ═══════════════════ */
$invented = (int) $one("SELECT COUNT(*) FROM proc_stock_move
                         WHERE move_type IN ('in','out','adjust','damage','quarantine')");
$svcInvented = preg_match("~'move_type'\s*=>\s*'(in|out|adjust)'~", $svcSrc) ? 1 : 0;
$readsSource = (strpos($svcSrc, 'StockMoveService::INBOUND') !== false) ? 1 : 0;
gate('W9-22', 'مفرداتُ حركةِ المخزنِ حيّةٌ تُقرأ من مصدرِ القادح',
     $invented === 0 && $svcInvented === 0 && $readsSource === 1,
     "صفوفٌ بمفردةٍ مخترَعةٍ $invented · حرفيّةٌ مخترَعةٌ في خدمةٍ $svcInvented"
     . ' · تقرأ StockMoveService::INBOUND ' . ($readsSource ? 'نعم' : '**لا**'));

/* ══ W9-23 · أساسُ المراحلِ السابقةِ لم يُمَسّ ═════════════════════════ */
$decN  = (int) $one("SELECT COUNT(*) FROM repair01_decisions");
$srcN  = (int) $one("SELECT COUNT(*) FROM repair01_source_files");
$surfN = (int) $one("SELECT COUNT(*) FROM repair01_surfaces");
$baseN = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                      WHERE origin IN ('SURFACES','DISK','NAV')");
$growN = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin REGEXP '^W[0-9]{2}$'");
$unstamped = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                          WHERE origin NOT IN ('SURFACES','DISK','NAV') AND origin NOT REGEXP '^W[0-9]{2}$'");
/* ⚠ **مقاما الفجواتِ يُفصلان**: `174` فجوةُ الدراسةِ الأصليّةُ بلا موجةِ منشأ،
     و`160` نُقلت من خانةِ المبنيِّ في W02 وتحمل `origin_stage='W02'`. وجمعُهما
     في رقمٍ واحدٍ (‏334) يُخفي أيَّهما نما — فالمقامانِ يُعلَنانِ منفصلَين. */
$gapOrig = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE COALESCE(origin_stage,'') = ''");
$gapW02  = (int) $one("SELECT COUNT(*) FROM repair01_target_gaps WHERE origin_stage = 'W02'");
gate('W9-23', 'أساسُ المراحلِ السابقةِ لم يُمَسّ',
     $decN === 108 && $srcN === 13 && $surfN === 664 && $baseN === 651 && $unstamped === 0
     && $gapOrig === 174 && $gapW02 === 160,
     "قرارات $decN · مصادر $srcN · أسطح $surfN · أساسُ السجلّ $baseN · نموٌّ مختومٌ $growN"
     . " · بلا ختمٍ $unstamped · فجواتٌ أصليّة $gapOrig · منقولةٌ في W02 $gapW02");

/* ══ W9-24 · المؤجَّلُ مسجَّلٌ ومقفولٌ في الاتّجاهَين ══════════════════ */
$blockOpen = (int) $one("SELECT COUNT(*) FROM repair01_decisions
                          WHERE decision_id = 'DEC-OPEN-15' AND status <> 'APPROVED'");
$dfAll = (int) $one("SELECT COUNT(*) FROM repair01_w9_deferred");
$dfOpen = (int) $one("SELECT COUNT(*) FROM repair01_w9_deferred WHERE consumed = 0");
$dfBad = (int) $one("SELECT COUNT(*) FROM repair01_w9_deferred
                      WHERE blocked_by = '' OR resume_step = '' OR part_built = ''
                         OR part_waiting = '' OR probe_sql = ''");
$declared = repair01_w9_deferred_rows();
/* ⛔ **القفلُ في الاتّجاهَين**: الحاجبُ مفتوحٌ ⇒ لا بدَّ من صفوفٍ مؤجَّلةٍ كاملةٍ
     غيرِ مستهلَكة. والحاجبُ مُغلَقٌ ⇒ لا يبقى مؤجَّلٌ غيرُ مستهلَك. */
$twoWay = ($blockOpen > 0)
        ? ($dfOpen === count($declared) && $dfAll === count($declared) && $dfBad === 0)
        : ($dfOpen === 0);
/* واستعلامُ الإثباتِ يُشغَّل فعلًا — فالانتظارُ يُقاس ولا يُدَّعى */
$probeLive = 0;
$r = $conn->query("SELECT defer_key, probe_sql FROM repair01_w9_deferred WHERE consumed = 0");
while ($r && $x = $r->fetch_assoc()) {
    $v = repair01_w9_one($conn, (string) $x['probe_sql']);
    if ($v !== null && (int) $v === 0) { $probeLive++; }
}
gate('W9-24', 'المؤجَّلُ مسجَّلٌ ومقفولٌ في الاتّجاهَين',
     $twoWay && $probeLive === $dfOpen,
     ($blockOpen > 0 ? 'DEC-OPEN-15 مفتوح' : 'DEC-OPEN-15 مُغلَق')
     . " · بنودٌ مؤجَّلةٌ $dfAll · غيرُ مستهلَكةٍ $dfOpen · ناقصةٌ $dfBad"
     . " · إثباتُ الانتظارِ مقيسٌ $probeLive من $dfOpen");

/* ══ W9-25 · رحلةُ التوريدِ تعبر ولا تترك أثرًا ════════════════════════ */
$jOut = array(); $jCode = 1;
@exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_w9_journey.php" 2>&1', $jOut, $jCode);
/* ⚠ مُعرِّفُ الجولةِ من المخرَجِ لا من «آخرِ صفٍّ» — رحلةٌ لم تنعقد تترك سابقتَها */
$run = '';
foreach ($jOut as $l) { if (preg_match('/^RUN=(W9J-\d+)$/', trim($l), $m)) { $run = $m[1]; break; } }
$jTotal = (int) $one("SELECT COUNT(*) FROM repair01_w9_journey WHERE run_id = '" . $esc($run) . "'");
$jPass  = (int) $one("SELECT COUNT(*) FROM repair01_w9_journey WHERE run_id = '" . $esc($run) . "' AND passed = 1");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w9_journey
                       WHERE run_id = '" . $esc($run) . "' AND (business_effect = '' OR business_effect = '—')");
$jCons  = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w9_journey WHERE run_id = '" . $esc($run) . "'");
$jLeft  = (int) $one("SELECT COUNT(*) FROM proc_stock_move WHERE note LIKE 'W09 %'")
        + (int) $one("SELECT COUNT(*) FROM proc_rfq WHERE code LIKE 'W9J-%'")
        + (int) $one("SELECT COUNT(*) FROM proc_package WHERE code LIKE 'W9J-%'");
gate('W9-25', 'رحلةُ التوريدِ تعبر ولا تترك أثرًا',
     $jCode === 0 && $run !== '' && $jTotal >= 28 && $jPass === $jTotal
     && $jCons >= 12 && $jNoEff === 0 && $jLeft === 0,
     'الجولة ' . ($run !== '' ? $run : '— لم تُعلَن —')
     . " · عابرٌ $jPass/$jTotal · مستهلكونَ متمايزون $jCons · بلا أثرٍ تجاريٍّ $jNoEff"
     . " · أثرٌ باقٍ $jLeft" . ($jCode !== 0 ? ' · رمزُ الخروج ' . $jCode : ''));

/* ═══════════════════════ الطباعة ═══════════════════════ */
foreach ($rows as $x) {
    printf("  %s %-8s %-46s %s\n", $x[2] ? '✔' : '✘', $x[0], $x[1], $x[3]);
}
echo str_repeat('─', 120) . "\n";
printf("W9 gate: %d/%d  ·  أمرٌ بلا سندٍ %d  ·  مفردةٌ مخترَعة %d  ·  مؤجَّلٌ غيرُ مستهلَك %d  ·  رحلةٌ %d/%d\n",
    $pass, $pass + $fail, count($noBasis), $invented, $dfOpen, $jPass, $jTotal);
echo 'الحكم: ' . ($fail === 0
        ? "خضراءُ المبنيِّ ✔  ·  ⛔ **والمرحلةُ مفتوحةٌ**: $dfOpen بندًا مؤجَّلًا بـDEC-OPEN-15 — لا إعلانَ إغلاق\n"
        : "ساقطة ✘\n");
exit($fail === 0 ? 0 : 1);
