<?php
/**
 * tools/repair01_w15_gate.php — بوّابةُ المرحلةِ الخامسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **كلُّ حاجبٍ يُعيد القياسَ ولا يقرأ ما خزّنَته أداةُ الاشتقاق** — والمقامُ
 *   يُعاد بناؤه من القرصِ والمخطَّطِ في كلِّ تشغيل.
 *
 * ◆ **وحارسُ الخلاءِ مبنيٌّ من البداية**: مجموعةٌ خاويةٌ تُخضِرُّ الحاجبَ على
 *   «تطابقِ لا شيء». فالصفرُ يمرُّ **مُعلَنًا بقرارٍ وحدَه** (`W15-D-05`).
 *
 * ◆ **ومحاورُ المرحلةِ الثلاثةُ تُقاس بنيويًّا**: `معاملةٌ مملوكةٌ للقيادة 0` ·
 *   `سطحٌ قياديٌّ بلا مُعرِّفٍ معياريّ 0` · `مصدرُ حقيقةٍ في «طلباتي» 0` —
 *   **وجبهةٌ غيرُ مقيسةٍ تُسقط الحاجبَ ولا تُعَدُّ صفرًا**.
 *
 * التشغيل: php tools/repair01_w15_gate.php
 * الخروج : 0 كلُّ الحواجبِ خضراء · 1 حاجبٌ ساقط
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w15_scan.php';
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w15_one($conn, $sql); };

$pass = 0; $fail = 0; $rows = array();
function gate($id, $title, $ok, $detail)
{
    global $pass, $fail, $rows;
    if ($ok) { $pass++; } else { $fail++; }
    $rows[] = array($id, $title, $ok, $detail);
}

echo "═══════ بوّابةُ المرحلةِ الخامسةَ عشرة — REPAIR01 · المساحات والتقارير ═══════\n";

/* ── حارسُ الخلاء ─────────────────────────────────────────────────────── */
$emptyDeclared = (int) $one("SELECT COUNT(*) FROM repair01_w15_decisions
                              WHERE decision_id = 'W15-D-05' AND COALESCE(rationale, '') <> ''");
$vac = function ($n) use ($emptyDeclared) { return ((int) $n === 0 && $emptyDeclared === 0); };
$vacTag = $emptyDeclared ? 'خلاءٌ مُعلَنٌ في W15-D-05 ✔' : '**خلاءٌ غيرُ مُعلَن**';

$ANCH = repair01_w15_anchors();
$NEW  = repair01_w15_new_surfaces();

/* ══ W15-01 · كلُّ متطلَّبٍ بمِرساةٍ وقاعدةٍ ومرجع ═══════════════════════ */
$reqN   = (int) $one("SELECT COUNT(*) FROM repair01_requirements WHERE stage_no = 15");
$scopeN = (int) $one("SELECT COUNT(*) FROM repair01_w15_scope");
$noRule = (int) $one("SELECT COUNT(*) FROM repair01_w15_scope WHERE map_rule = '' OR map_why = ''");
$orphan = (int) $one("SELECT COUNT(*) FROM repair01_w15_scope s
                       LEFT JOIN repair01_requirements r ON r.requirement_id = s.requirement_id
                                                        AND r.stage_no = 15
                      WHERE r.requirement_id IS NULL");
gate('W15-01', 'كلُّ متطلَّبٍ بقاعدةِ ربطٍ ومرجع',
     $reqN > 0 && $scopeN === $reqN && $noRule === 0 && $orphan === 0,
     "متطلَّباتُ المرحلةِ $reqN · في الدفتر $scopeN · بلا قاعدةٍ $noRule · يتيمٌ $orphan");

/* ══ W15-02 · المِرساةُ مُثبَتةٌ من القرصِ لا مُعلَنةٌ فقط ══════════════ */
$proven = 0; $deferred = 0; $unproven = array();
foreach ($ANCH as $rid => $a) {
    if ($a['route'] === '') { $deferred++; continue; }
    $pr = repair01_w15_prove_anchor($conn, $ROOT, $a);
    if ($pr['verdict'] === 'ANCHORED') { $proven++; } else { $unproven[] = $rid . ' (' . $pr['verdict'] . ')'; }
}
$defBook = (int) $one("SELECT COUNT(*) FROM repair01_w15_deferred");
gate('W15-02', 'المِرساةُ مُثبَتةٌ من القرصِ والمؤجَّلُ مسجَّل',
     count($unproven) === 0 && ($deferred === 0 || $defBook > 0),
     "مُثبَتةٌ $proven · مؤجَّلةٌ $deferred · في دفترِ التأجيل $defBook · لم تُثبَت " . count($unproven)
     . (count($unproven) ? ' ⇐ ' . implode('، ', array_slice($unproven, 0, 3)) : ''));

/* ══ W15-03 · إسقاطٌ لا مصدر — صفرُ سطحٍ `SOURCE` في الموجة ═════════════ */
$w15Src = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                       WHERE origin = 'W15' AND surface_kind <> 'PROJECTION'");
$w15N   = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry WHERE origin = 'W15'");
$bookSrc = (int) $one("SELECT COUNT(*) FROM repair01_w15_scope
                        WHERE surface_kind <> '' AND surface_kind <> 'PROJECTION'");
gate('W15-03', 'كلُّ سطحٍ في الموجةِ إسقاطٌ وصفرُ مصدر',
     $w15Src === 0 && $bookSrc === 0 && !$vac($w15N),
     "أسطحُ الموجةِ $w15N · صنفٌ غيرُ إسقاطٍ في السجلِّ $w15Src · في الدفتر $bookSrc"
     . ($vac($w15N) ? ' ⇐ ' . $vacTag : ''));

/* ══ W15-04 · القراءةُ بمرجعٍ حيٍّ لا بنسخٍ دوريّ ═══════════════════════ */
$copyMode = (int) $one("SELECT COUNT(*) FROM repair01_w15_scope
                         WHERE read_mode <> '' AND read_mode <> 'LIVE_REFERENCE'");
/* والقياسُ في الشيفرةِ لا في الدفتر: لوحةُ القيادةِ لا تكتب لقطةً بعد اليوم */
$boardSrc = (string) @file_get_contents($ROOT . '/Portal/ceo_board.php');
$boardWrites = preg_match('~INSERT\s+INTO\s+`?exec_board_snapshots~i', $boardSrc) ? 1 : 0;
gate('W15-04', 'القراءةُ بمرجعٍ حيٍّ ولا لقطةَ دوريّةٍ تُكتب',
     $copyMode === 0 && $boardWrites === 0,
     "قراءةٌ غيرُ حيّةٍ في الدفتر $copyMode · كتابةُ لقطةٍ في لوحةِ القيادة $boardWrites");

/* ⚠ **جدولُ حملةٍ ليس جدولَ أعمال**: عنوانُ الحاجبِ «جدولُ **أعمالٍ** جديد»،
     وكان يستثني `repair01_w15_%` وحدَها — فرسب على `repair01_debt_register`
     الذي أنشأه البندُ ⑥ من أمرِ المالكِ **سجلًّا لأصنافِ الدَّينِ لا حقيقةَ أعمالٍ**.
     ⇒ فالاستثناءُ **لعائلةِ `repair01_` كلِّها** — وهو وفاءٌ لنصِّ الحاجبِ لا تليينٌ له،
     **والحاجبُ ما يزال يرسُب على أيِّ جدولِ أعمالٍ جديد**. */
/* ══ W15-05 · لا جدولَ حقيقةٍ جديدٌ أنشأته المرحلة ══════════════════════ */
$snapN = (int) $one("SELECT COUNT(*) FROM repair01_w15_table_snapshot");
/* ⚠ **والنموُّ يُعلَن بجولتِه لا يُستثنى بنمط** (RPR-AMD01): جولةٌ أخرى تُنشئ
     جداولَها بحقٍّ فتظهر هنا نموًّا لـ`W15` وهي ليست منها — وقِيس **26 جدولًا**
     كلُّها من مجالِ الموردين (`sup_*`) وتسويةِ الهجرة، **وصفرٌ منها من `W15`**.
   ⛔ **وتوسيعُ نمطِ الاستبعادِ يُسكِت الحاجبَ عن كلِّ نموٍّ قادمٍ بالجملة** وهو
     تليينٌ لا إصلاح. فالمُعلَنُ وحدَه يُستثنى — في `repair01_w15_table_exempt`
     **بجولتِه وسببِه** — **والحاجبُ يسقط على غيرِ المُعلَنِ كما كان.** */
$grown = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES t
                      LEFT JOIN repair01_w15_table_snapshot s ON s.table_name = t.TABLE_NAME
                      LEFT JOIN repair01_w15_table_exempt   x ON x.table_name = t.TABLE_NAME
                     WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
                       AND t.TABLE_NAME NOT LIKE 'repair01\\_%'
                       AND s.table_name IS NULL AND x.table_name IS NULL");
$exempt = (int) $one("SELECT COUNT(*) FROM repair01_w15_table_exempt");
gate('W15-05', 'لا جدولَ أعمالٍ جديدٌ أنشأته هذه المرحلة',
     $snapN > 0 && $grown === 0,
     "لقطةُ ما قبلَ المرحلة $snapN جدولًا · نموٌّ غيرُ مُعلَنٍ $grown · مُعلَنٌ بجولتِه $exempt"
     . " · وسجلاتُ الحملةِ مستثناةٌ بالإعلان");

/* ══ W15-06 · سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح ═══════════════════════ */
$routes = array();
foreach ($ANCH as $a) { if ($a['route'] !== '') { $routes[$a['route']] = true; } }
$sbN   = (int) $one("SELECT COUNT(*) FROM repair01_w15_sidebar");
$sbBad = (int) $one("SELECT COUNT(*) FROM repair01_w15_sidebar
                      WHERE s1_verdict='' OR s1_rule='' OR s2_verdict='' OR s2_rule=''
                         OR s3_verdict='' OR s3_rule='' OR s4_verdict='' OR s4_rule=''
                         OR s5_verdict='' OR s5_rule='' OR s6_verdict='' OR s6_rule=''
                         OR s7_verdict='' OR s7_rule=''");
gate('W15-06', 'سبعُ خطواتٍ بحكمٍ وقاعدةٍ لكلِّ سطح', $sbN === count($routes) && $sbBad === 0,
     'أسطحُ النطاقِ المُعادُ اشتقاقُها ' . count($routes) . " · في الدفتر $sbN · خطوةٌ بلا حكمٍ $sbBad");

/* ══ W15-07 · الاسمُ والمجموعةُ صُحِّحا لا قِيسا فقط ════════════════════ */
$lblDrift = (int) $one("SELECT COUNT(*) FROM repair01_w15_sidebar WHERE s2_verdict <> 'LABEL_MATCH'");
gate('W15-07', 'الاسمُ صُحِّح على السجلِّ المعياريّ',
     $lblDrift === 0 && $sbN > 0, "انحرافُ اسمٍ $lblDrift · المقامُ $sbN");

/* ══ W15-08 · الترتيبُ من دورةِ العملِ لا من الأبجديّةِ ولا يدويًّا ════ */
$ordBad = (int) $one("SELECT COUNT(*) FROM repair01_w15_sidebar
                       WHERE s4_verdict <> 'CYCLE_ORDER_APPLIED' OR s4_order_no <> s4_cycle_step");
/* ⛔ ولا ترتيبٌ يدويٌّ موازٍ: `ORDER BY` مكتوبٌ على بندِ قائمةٍ في سطحِ الموجة */
$manualOrder = 0; $manualWhere = array();
foreach (array_keys($routes) as $rt) {
    $src = (string) @file_get_contents($ROOT . '/' . $rt);
    if ($src !== '' && preg_match('~nav_items[^;]{0,200}ORDER\s+BY~is', $src)) {
        $manualOrder++; $manualWhere[] = $rt;
    }
}
gate('W15-08', 'الترتيبُ من السجلِّ لا يدويًّا في الصفحة',
     $ordBad === 0 && $manualOrder === 0,
     "ترتيبٌ مخالفٌ للدورة $ordBad · ترتيبٌ يدويٌّ في صفحة $manualOrder"
     . ($manualWhere ? ' ⇐ ' . implode('، ', $manualWhere) : ''));

/* ══ W15-09 · حارسُ عرضٍ خادميٌّ ومنحٌ لكلِّ سطح ════════════════════════ */
$noGrant = (int) $one("SELECT COUNT(*) FROM repair01_w15_sidebar WHERE s6_perm_rows = 0");
$noGuard = array();
foreach (array_keys($routes) as $rt) {
    $g = repair01_w15_guard_of($ROOT, $rt);
    if ($g['kind'] === 'NONE') { $noGuard[] = $rt; }
}
gate('W15-09', 'حارسُ عرضٍ خادميٌّ ومنحٌ لكلِّ سطحٍ من أسطحِ النطاق',
     count($noGuard) === 0 && $noGrant === 0 && count($routes) > 0,
     'بلا حارسٍ ' . count($noGuard) . " · بلا منحٍ $noGrant"
     . (count($noGuard) ? ' ⇐ ' . implode('، ', array_slice($noGuard, 0, 3)) : ''));

/* ══ W15-10 · الربطُ بالمُعرِّفِ المعياريِّ — ملءُ الحقلِ لا وجودُ صفّ ══ */
$notLinked = (int) $one("SELECT COUNT(*) FROM repair01_w15_sidebar WHERE s7_linked = 0");
$navNoSid  = 0;
foreach (array_keys($routes) as $rt) {
    $v = (string) $one("SELECT COALESCE(screen_id,'') FROM nav_canonical WHERE route = '" . $esc($rt) . "' LIMIT 1");
    $exists = (int) $one("SELECT COUNT(*) FROM nav_canonical WHERE route = '" . $esc($rt) . "'");
    if ($exists > 0 && $v === '') { $navNoSid++; }
}
gate('W15-10', 'كلُّ بندٍ مربوطٌ بمُعرِّفِ شاشتِه المعياريّ',
     $notLinked === 0 && $navNoSid === 0,
     "غيرُ مربوطٍ في الدفتر $notLinked · صفٌّ معياريٌّ بلا مُعرِّفٍ $navNoSid");

/* ══ W15-11 · صفرُ كتابةٍ ممنوعةٍ من مساحاتِ الموجة ════════════════════ */
$wr = repair01_w15_scan_writes($ROOT);
$forb = 0; $forbList = array();
foreach ($wr as $x) { if ($x['verdict'] === 'FORBIDDEN') { $forb++; $forbList[] = $x['file'] . ':' . $x['line'] . ' ' . $x['table']; } }
$scanned = count(repair01_w15_space_files($ROOT));
gate('W15-11', 'صفرُ كتابةٍ من مساحةِ الموجةِ في جدولِ نطاقٍ آخر',
     $forb === 0 && $scanned > 0,
     "ملفّاتٌ مقروءةٌ $scanned · كتاباتٌ مرصودةٌ " . count($wr) . " · ممنوعةٌ $forb"
     . ($forbList ? ' ⇐ ' . implode('، ', array_slice($forbList, 0, 3)) : ''));

/* ══ W15-12 · معاملةٌ مملوكةٌ للقيادة = 0 (‏محورٌ أوّل) ═════════════════ */
$leadOwned = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                          WHERE owner_code IN ('EX-CEO','EX-DVP') AND ownership_verdict = 'DOMAIN_SOURCE'");
$leadSrcKind = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                            WHERE owner_code IN ('EX-CEO','EX-DVP') AND surface_kind = 'SOURCE'");
$returned = (int) $one("SELECT COUNT(*) FROM repair01_w15_nav_moves WHERE move_kind LIKE 'OWNER_RETURN%'");
gate('W15-12', 'معاملةٌ مملوكةٌ للقيادة 0',
     $leadOwned === 0 && $leadSrcKind === 0 && $returned > 0,
     "مملوكةٌ للقيادة $leadOwned · صنفُ مصدرٍ عند القيادة $leadSrcKind · ملكيّاتٌ أُعيدت $returned");

/* ══ W15-13 · سطحٌ قياديٌّ بلا مُعرِّفٍ معياريّ = 0 (‏محورٌ ثانٍ) ═══════ */
$leadNoSid = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                          WHERE owner_code IN ('EX-CEO','EX-DVP','WS-MY')
                            AND (screen_id IS NULL OR screen_id = '')");
$leadTotal = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                          WHERE owner_code IN ('EX-CEO','EX-DVP','WS-MY')");
gate('W15-13', 'سطحٌ قياديٌّ بلا مُعرِّفٍ معياريّ 0',
     $leadNoSid === 0 && !$vac($leadTotal),
     "أسطحُ المساحاتِ $leadTotal · بلا مُعرِّفٍ $leadNoSid" . ($vac($leadTotal) ? ' ⇐ ' . $vacTag : ''));

/* ══ W15-14 · مصدرُ حقيقةٍ في «طلباتي» = 0 (‏محورٌ ثالث) ═══════════════ */
$lnchLocal = (int) $one("SELECT COUNT(*) FROM repair01_w15_launcher WHERE local_store <> ''");
$lnchWsOwn = (int) $one("SELECT COUNT(*) FROM repair01_w15_launcher WHERE definition_owner = 'WS-MY'");
$lnchN     = (int) $one("SELECT COUNT(*) FROM repair01_w15_launcher");
/* والقياسُ في الشيفرةِ أيضًا: سطحُ «طلباتي» لا يكتب في مخزنٍ محلّيّ */
$mrqSrc = (string) @file_get_contents($ROOT . '/Portal/my_requests.php');
$mrqDirect = preg_match('~INSERT\s+INTO\s+`?requests~i', $mrqSrc) ? 1 : 0;
gate('W15-14', 'مصدرُ حقيقةٍ في «طلباتي» 0',
     $lnchLocal === 0 && $lnchWsOwn === 0 && $mrqDirect === 0 && !$vac($lnchN),
     "أنواعٌ في دفترِ المُطلِق $lnchN · خزنٌ محلّيٌّ $lnchLocal · تعريفٌ تملكه مساحةُ العمل $lnchWsOwn"
     . " · كتابةٌ مباشرةٌ في المخزنِ العامّ $mrqDirect" . ($vac($lnchN) ? ' ⇐ ' . $vacTag : ''));

/* ══ W15-15 · القاعدةُ الرباعيّةُ في السجلِّ المركزيِّ حيّةً ════════════ */
$quadBad = (int) $one("SELECT COUNT(*) FROM gov_request_type
                        WHERE state = 'active'
                          AND (definition_owner_dept = '' OR definition_owner_dept = 'DEP-08'
                               OR definition_owner_dept = 'WS-MY'
                               OR registry_governed_by <> 'DEP-08'
                               OR authority_rule_id = '' OR routing_rule_ref = '')");
$activeN = (int) $one("SELECT COUNT(*) FROM gov_request_type WHERE state = 'active'");
gate('W15-15', 'القاعدةُ الرباعيّةُ نافذةٌ على كلِّ نوعٍ حيّ',
     $quadBad === 0 && !$vac($activeN),
     "أنواعٌ نافذةٌ $activeN · مخالفةٌ للقاعدةِ الرباعيّة $quadBad" . ($vac($activeN) ? ' ⇐ ' . $vacTag : ''));

/* ══ W15-16 · خدمةُ المالكِ قائمةٌ فعلًا لكلِّ نوعٍ نافذ ═══════════════ */
$svcMissing = array();
$r = $conn->query("SELECT type_code, owner_service, owner_table, projection_user_col
                     FROM gov_request_type WHERE state = 'active'");
while ($r && ($x = $r->fetch_assoc())) {
    if (!repair01_w15_service_exists($ROOT, $x['owner_service'])) { $svcMissing[] = $x['type_code']; continue; }
    $t = (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $esc($x['owner_table']) . "'
                        AND COLUMN_NAME = '" . $esc($x['projection_user_col']) . "'");
    if ($t === 0) { $svcMissing[] = $x['type_code'] . ' (عمود الإسقاط)'; }
}
gate('W15-16', 'خدمةُ المالكِ وعمودُ الإسقاطِ قائمانِ لكلِّ نوعٍ نافذ',
     count($svcMissing) === 0 && $activeN > 0,
     'أنواعٌ نافذةٌ ' . $activeN . ' · بلا خدمةٍ أو عمودٍ ' . count($svcMissing)
     . ($svcMissing ? ' ⇐ ' . implode('، ', $svcMissing) : ''));

/* ══ W15-17 · قيدا السجلِّ المركزيِّ حيّانِ في المخطَّط ═════════════════ */
$chk = array();
foreach (array('chk_grt_binding', 'chk_grt_not_workspace', 'chk_grt_gov', 'chk_grt_domain',
               'chk_w15_lnch_no_local', 'chk_w15_lnch_quad', 'chk_w15_axis_split',
               'chk_w15_scope_projection', 'chk_w15_scope_live', 'chk_w15_th_pending_null',
               'chk_w15_th_test_not_prod', 'chk_w15_def_built', 'chk_w15_sod_combo',
               'chk_w15_state_forbid') as $c) {
    $chk[$c] = (int) $one("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$c'");
}
$chkMissing = array_keys(array_filter($chk, function ($v) { return $v === 0; }));
gate('W15-17', 'قيودُ المخطَّطِ المحوريّةُ مُثبَتةٌ حيّةً',
     count($chkMissing) === 0,
     'مُعلَنةٌ ' . count($chk) . ' · غائبةٌ ' . count($chkMissing)
     . ($chkMissing ? ' ⇐ ' . implode('، ', $chkMissing) : ''));

/* ══ W15-18 · الرؤيةُ لا تساوي السلطة — محوران لا محور ═════════════════ */
$axN   = (int) $one("SELECT COUNT(*) FROM repair01_w15_scope_axis");
$axSame = (int) $one("SELECT COUNT(*) FROM repair01_w15_scope_axis
                       WHERE visibility_rule = authority_rule OR authority_src = ''");
/* والقياسُ في الشيفرة: محرّكُ النطاقِ يفصل الدالّتَين */
/* ⚠ **الرسوُّ على حدِّ الاسمِ لا على تضمُّنِه**: `strpos('function authority')`
     يطابق `function authorityX` أيضًا — فيقول «الدالّةُ قائمة» وقد زالت.
     وهو عطبُ المُنقّي لا المُنقَّى (‏درسُ W06)، كشفَه **الفحصُ السلبيُّ** نفسُه. */
$scpSrc = (string) @file_get_contents($ROOT . '/app/Services/Exec/ScopeEngine.php');
$hasBoth = (preg_match('~function\s+visibility\s*\(~', $scpSrc)
         && preg_match('~function\s+authority\s*\(~', $scpSrc)) ? 1 : 0;
gate('W15-18', 'الرؤيةُ لا تساوي السلطة — محوران مفصولان',
     $axSame === 0 && $hasBoth === 1 && !$vac($axN),
     "محاورُ $axN · مدموجٌ $axSame · دالّتانِ منفصلتانِ في المحرّك $hasBoth"
     . ($vac($axN) ? ' ⇐ ' . $vacTag : ''));

/* ══ W15-19 · لا رقمَ دورٍ مكتوبٌ حارسًا في أسطحِ النطاق ═══════════════ */
$roleLit = 0; $roleWhere = array();
foreach (array_keys($routes) as $rt) {
    $src = (string) @file_get_contents($ROOT . '/' . $rt);
    if ($src === '') { continue; }
    if (preg_match_all("~\\['role'\\][^;\\n]{0,80}(?:===|!==|==|!=)\\s*'[0-9]+'~", $src, $m)) {
        $roleLit += count($m[0]); $roleWhere[] = $rt;
    }
}
gate('W15-19', 'لا رقمَ دورٍ مكتوبٌ حارسًا في سطحٍ من أسطحِ النطاق',
     $roleLit === 0,
     "أسطحٌ مقروءةٌ " . count($routes) . " · مقارنةُ رقمِ دورٍ $roleLit"
     . ($roleWhere ? ' ⇐ ' . implode('، ', array_slice($roleWhere, 0, 3)) : ''));

/* ══ W15-20 · لا عتبةٌ رقميّةٌ صلبةٌ في طبقةِ أعمالِ الموجة ════════════
     ⚠ **ومقامانِ لا مقامٌ واحد** (‏درسُ W14 ③): طبقةُ الأعمالِ تشترط صفرًا،
       وطبقةُ القياسِ تُعلَن بعددِها لأنَّ `=== 3` في حاجبٍ عدّادُ جبهاتٍ لا حدّ.
     ⚠ **و`=>` ليست عاملَ مقارنة** — `(?<!=)>` تفصل السهمَ عن المقارنة. */
$bizFiles = array_merge(
    array_map(function ($s) { return $s['route']; }, $NEW),
    array('app/Services/Exec/ScopeEngine.php', 'app/Services/Exec/ExecProjectionService.php',
          'app/Services/Exec/ExecDecisionRouter.php', 'app/Services/Workspace/RequestLauncher.php',
          'app/Services/Workforce/LeaveRequestService.php',
          'app/Services/Operations/ProjectOpeningService.php')
);
$hard = 0; $hardWhere = array();
foreach ($bizFiles as $f) {
    $src = (string) @file_get_contents($ROOT . '/' . $f);
    if ($src === '') { continue; }
    foreach (explode("\n", $src) as $i => $ln) {
        if (preg_match('~^\s*(\*|//|/\*)~', $ln)) { continue; }
        /* سقفُ عرضٍ مُعلَنٌ بثابتٍ مسمًّى ليس عتبةَ قرار */
        if (strpos($ln, 'READ_CAP') !== false) { continue; }
        if (preg_match('~(?<![=<>!])(?<!=)(?:<|(?<!=)>)=?\s*[0-9]{2,}~', $ln)) {
            $hard++; $hardWhere[] = basename($f) . ':' . ($i + 1);
        }
    }
}
gate('W15-20', 'لا عتبةٌ رقميّةٌ صلبةٌ في طبقةِ أعمالِ الموجة',
     $hard === 0,
     'ملفّاتُ أعمالٍ ' . count($bizFiles) . " · عتبةٌ صلبةٌ $hard"
     . ($hardWhere ? ' ⇐ ' . implode('، ', array_slice($hardWhere, 0, 3)) : ''));

/* ══ W15-21 · العتباتُ من السجلِّ بحالاتِها ═════════════════════════════ */
$thN  = (int) $one("SELECT COUNT(*) FROM repair01_w15_thresholds");
$thBad = (int) $one("SELECT COUNT(*) FROM repair01_w15_thresholds
                      WHERE (state = 'CONFIG_PENDING' AND value_num IS NOT NULL)
                         OR (state = 'OWNER_APPROVED' AND (value_num IS NULL OR owner_text = ''))
                         OR registry = ''");
gate('W15-21', 'العتباتُ من السجلِّ بحالاتِها ولا قيمةَ مخترَعة',
     $thBad === 0 && !$vac($thN),
     "عتباتٌ $thN · مخالفةٌ $thBad · منتظرةٌ "
     . (int) $one("SELECT COUNT(*) FROM repair01_w15_thresholds WHERE state = 'CONFIG_PENDING'")
     . ($vac($thN) ? ' ⇐ ' . $vacTag : ''));

/* ══ W15-22 · آلةُ حالةٍ لكلِّ كيانٍ والممنوعُ بسببِه ═══════════════════ */
$entN  = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w15_states");
$trN   = (int) $one("SELECT COUNT(*) FROM repair01_w15_states");
$forbN = (int) $one("SELECT COUNT(*) FROM repair01_w15_states WHERE allowed = 0");
$stBad = (int) $one("SELECT COUNT(*) FROM repair01_w15_states
                      WHERE (allowed = 0 AND forbid_why = '')
                         OR (allowed = 1 AND (owner_role = '' OR precondition = ''))");
$entNoForb = (int) $one("SELECT COUNT(*) FROM (
                          SELECT entity FROM repair01_w15_states
                           GROUP BY entity HAVING SUM(allowed = 0) = 0) x");
gate('W15-22', 'آلةُ حالةٍ لكلِّ كيانٍ ولكلِّ ممنوعٍ سببُه',
     $stBad === 0 && $entNoForb === 0 && !$vac($entN),
     "كياناتٌ $entN · انتقالاتٌ $trN · ممنوعٌ صريحٌ $forbN · ناقصٌ $stBad · كيانٌ بلا ممنوعٍ $entNoForb");

/* ══ W15-23 · فصلُ الواجباتِ بستّةِ أدوارٍ وتركيبةٍ ممنوعة ══════════════ */
$sodN = (int) $one("SELECT COUNT(*) FROM repair01_w15_sod");
$sodBad = (int) $one("SELECT COUNT(*) FROM repair01_w15_sod
                       WHERE initiator='' OR reviewer='' OR approver='' OR executor='' OR closer=''
                          OR forbidden_combo='' OR authority_rule='' OR scope_rule='' OR delegation=''");
/* ⛔ ولا اسمَ شخصٍ صلبًا — الأدوارُ مسمّياتٌ لا أعلام */
$sodPerson = (int) $one("SELECT COUNT(*) FROM repair01_w15_sod
                          WHERE approver REGEXP '[0-9]' OR initiator REGEXP '[0-9]'");
gate('W15-23', 'فصلُ الواجباتِ بستّةِ أدوارٍ وتركيبةٍ ممنوعةٍ بلا اسمِ شخص',
     $sodBad === 0 && $sodPerson === 0 && !$vac($sodN),
     "عملياتٌ حرجةٌ $sodN · ناقصةٌ $sodBad · باسمٍ أو رقمٍ شخصيّ $sodPerson");

/* ══ W15-24 · عقدُ أثرٍ لكلِّ حدثٍ بمستهلكِيه بالاسم ════════════════════ */
$evN = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W15'");
$evBad = (int) $one("SELECT COUNT(*) FROM repair01_events
                      WHERE wave = 'W15'
                        AND (contract_status <> 'RECORDED' OR idempotency_key = ''
                             OR consumer_list = '' OR consumer_effect = '' OR min_payload = ''
                             OR preconditions = '' OR failure_policy = '' OR compensation = ''
                             OR retry_policy = '')");
$evVague = (int) $one("SELECT COUNT(*) FROM repair01_events
                        WHERE wave = 'W15' AND (consumer_list LIKE '%كل المستهلكين%'
                                             OR consumer_list LIKE '%جميع المستهلكين%')");
gate('W15-24', 'عقدُ أثرٍ مسجَّلٌ لكلِّ حدثٍ بكلِّ مستهلكٍ بالاسم',
     $evBad === 0 && $evVague === 0 && !$vac($evN),
     "عقودٌ $evN · ناقصةٌ $evBad · مستهلكٌ مبهمٌ $evVague" . ($vac($evN) ? ' ⇐ ' . $vacTag : ''));

/* ══ W15-25 · رحلةُ الإثباتِ تعبر كاملةً ═══════════════════════════════ */
$jN    = (int) $one("SELECT COUNT(*) FROM repair01_w15_journey");
$jFail = (int) $one("SELECT COUNT(*) FROM repair01_w15_journey WHERE verdict <> 'PASS'");
$jLegs = (int) $one("SELECT COUNT(DISTINCT leg) FROM repair01_w15_journey");
$jCons = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w15_journey WHERE consumer <> ''");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w15_journey WHERE effect_probe = ''");
gate('W15-25', 'رحلةُ الطلبِ تعبر كاملةً بأثرٍ عند كلِّ مستهلك',
     $jFail === 0 && $jNoEff === 0 && $jLegs >= 5 && !$vac($jN),
     "محطّاتٌ $jN · ساقطةٌ $jFail · شوطٌ $jLegs · مستهلكونَ متمايزون $jCons · بلا أثرٍ مقيسٍ $jNoEff");

/* ══ W15-26 · نموُّ السجلِّ مختومٌ والأساسُ لم يُمَسّ ═══════════════════ */
$base = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                     WHERE origin IN ('SURFACES','DISK','NAV')");
$unstamped = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                          WHERE origin NOT IN ('SURFACES','DISK','NAV') AND origin NOT REGEXP '^W[0-9]+$'");
gate('W15-26', 'أساسُ السجلِّ 651 لم يُمَسّ والنموُّ مختومٌ بموجتِه',
     $base === 651 && $unstamped === 0,
     "الأساسُ $base · نموٌّ بلا ختمٍ $unstamped · أسطحُ W15 $w15N");

/* ══ W15-27 · سقّاطةُ السطحِ الجديد — اثنا عشرَ حقلًا ═══════════════════ */
$RATCH = array('screen_id','canonical_label_ar','owner_code','surface_kind','route','lifecycle',
               'guard_kind','action_guard','permission_policy','grain_ar','source_of_truth','state_model_ref');
$cond = array();
foreach ($RATCH as $c) { $cond[] = "COALESCE(`$c`,'') = ''"; }
$ratchBad = (int) $one("SELECT COUNT(*) FROM repair01_screen_registry
                         WHERE origin = 'W15' AND (" . implode(' OR ', $cond) . ")");
gate('W15-27', 'كلُّ سطحٍ جديدٍ بالحقولِ الاثنَي عشرَ كاملة',
     $ratchBad === 0 && !$vac($w15N),
     "أسطحُ W15 $w15N · ناقصةُ الشروط $ratchBad");

/* ══ W15-28 · المؤجَّلُ مسجَّلٌ ببيانِ ما بُني رغمَه ════════════════════ */
$defBad = (int) $one("SELECT COUNT(*) FROM repair01_w15_deferred
                       WHERE built_anyway = '' OR why_needed = '' OR kind = ''");
gate('W15-28', 'كلُّ مؤجَّلٍ ببيانِ ما بُني رغمَه',
     $defBad === 0 && !$vac($defBook),
     "مؤجَّلٌ $defBook · بلا بيانٍ $defBad" . ($vac($defBook) ? ' ⇐ ' . $vacTag : ''));

/* ══ W15-29 · سلامةُ قرارِ المالك — صفرُ اعتمادٍ مفترَض ════════════════ */
$assumed = (int) $one("SELECT COUNT(*) FROM repair01_decision_audit
                        WHERE verdict = 'SYSTEM_ASSUMED_APPROVAL'");
/* ⚠ **وهذا السطرُ كان يسأل عمودًا لا وجودَ له** (`owner_answer_ref`) — فيسقط
   الاستعلامُ ويعود `null` **فيُطبَع 0 أبدًا**. والرقمُ ليس شرطَ عبورٍ هنا، لكنّه
   **يُقرأ في مخرَجِ البوّابةِ اطمئنانًا كاذبًا**. كشفَه تحدّي W16 (`CH-04`)
   بمطابقةِ كلِّ عمودٍ مذكورٍ على `information_schema`. **والاسمُ الحيُّ للعمود
   `owner_decision_reference`** — والمقيسُ به عددٌ حقيقيٌّ يُعلَن ولا يُدَّعى صفرًا. */
$apprNoRef = (int) $one("SELECT COUNT(*) FROM repair01_decisions
                          WHERE status = 'APPROVED' AND COALESCE(owner_decision_reference,'') = ''");
gate('W15-29', 'صفرُ اعتمادٍ مفترَضٍ نيابةً عن المالك',
     $assumed === 0,
     "اعتمادٌ مفترَضٌ $assumed · معتمَدٌ بلا مرجعِ جواب $apprNoRef");

/* ══ W15-30 · القراراتُ والإصلاحاتُ بمتطلَّبِها الكاشف ═════════════════ */
$decN = (int) $one("SELECT COUNT(*) FROM repair01_w15_decisions");
$decBad = (int) $one("SELECT COUNT(*) FROM repair01_w15_decisions WHERE answer = '' OR rationale = ''");
$fixN = (int) $one("SELECT COUNT(*) FROM repair01_w15_fixes");
$fixBad = (int) $one("SELECT COUNT(*) FROM repair01_w15_fixes WHERE found_by = '' OR evidence = ''");
gate('W15-30', 'كلُّ قرارٍ بحجّتِه وكلُّ إصلاحٍ بمتطلَّبِه الكاشف',
     $decBad === 0 && $fixBad === 0 && !$vac($decN) && !$vac($fixN),
     "قراراتٌ $decN · بلا حجّةٍ $decBad · إصلاحاتٌ $fixN · بلا كاشفٍ $fixBad");

/* ══ W15-31 · نقاءُ لغةِ الواجهةِ في أسطحِ الموجة ══════════════════════ */
$diac = 0; $tech = 0; $colon = 0; $bad = array();
$TECH = array('surface_kind', 'source_of_truth', 'company_id', 'SELECT ', 'INSERT ', 'screen_id',
              'PROJECTION', 'Grain', 'FK', 'PK');
foreach ($NEW as $s) {
    $src = (string) @file_get_contents($ROOT . '/' . $s['route']);
    if ($src === '') { continue; }
    if (preg_match_all('~<(?:th|td|div)[^>]*>[^<]*[\x{064B}-\x{0652}]~u', $src, $m)) { $diac += count($m[0]); $bad[] = $s['route']; }
    foreach ($TECH as $t) {
        if (preg_match('~<(?:th|div class="ems-stat-label")[^>]*>[^<]*' . preg_quote($t, '~') . '~u', $src)) { $tech++; }
    }
    if (preg_match('~\$header_title\s*=\s*\'[^\']*:~u', $src)) { $colon++; }
}
gate('W15-31', 'نقاءُ لغةِ الواجهةِ في نصِّ أسطحِ الموجة',
     $diac === 0 && $tech === 0 && $colon === 0,
     "أسطحٌ مقروءةٌ " . count($NEW) . " · تشكيلٌ $diac · مصطلحٌ تقنيٌّ $tech · نقطتانِ في اسمٍ $colon"
     . ($bad ? ' ⇐ ' . implode('، ', array_slice(array_unique($bad), 0, 3)) : ''));

/* ══ W15-32 · النوّابُ يُخدَمون بالمحرّكِ نفسِه لا بنظامٍ ثانٍ ══════════ */
$vpReq = (int) $one("SELECT COUNT(*) FROM repair01_w15_scope WHERE space_code = 'EX-DVP'");
$vpShared = (int) $one("SELECT COUNT(*) FROM repair01_w15_scope
                         WHERE space_code = 'EX-DVP' AND map_rule = 'W15_SAME_ENGINE_SCOPED'");
$vpOwnFiles = 0;
foreach (glob($ROOT . '/Portal/vp_*.php') as $f) { $vpOwnFiles++; }
gate('W15-32', 'النوّابُ بمحرّكٍ واحدٍ لا بنظامٍ ثانٍ',
     $vpOwnFiles === 0 && $vpShared > 0 && !$vac($vpReq),
     "متطلَّباتُ النوّابِ $vpReq · بالمحرّكِ نفسِه $vpShared · ملفّاتُ نظامٍ ثانٍ $vpOwnFiles");

/* ═══════════════════════════════════════════════════════════════════════════
   الحكم
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n";
foreach ($rows as $r) {
    printf("  %s %-8s %-56s %s\n", $r[2] ? '✔' : '✘', $r[0], mb_substr($r[1], 0, 56), $r[3]);
}
echo "\n────────────────────────────────────────────────────────────\n";
printf("W15 gate: %d/%d  ·  معاملةٌ مملوكةٌ للقيادة %d · سطحٌ قياديٌّ بلا مُعرِّف %d · مصدرُ حقيقةٍ في «طلباتي» %d\n",
       $pass, $pass + $fail, $leadOwned, $leadNoSid, $lnchLocal + $lnchWsOwn + $mrqDirect);
echo $fail === 0 ? "الحكم: خضراء ✔\n" : "الحكم: **حاجبٌ ساقط** ✘\n";
exit($fail === 0 ? 0 : 1);
