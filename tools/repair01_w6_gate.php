<?php
/**
 * tools/repair01_w6_gate.php
 *   بوّابةُ المرحلةِ السادسة — REPAIR01 · نقاءُ لغةِ الواجهة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **تُعيد القياسَ ولا تقرأ ما خزَّنَته الأداة** (‏_CONTEXT §قواعد القياس ١):
 *   كلُّ رقمٍ هنا يُشتقُّ من الجداولِ الحيّةِ أو من **النصِّ المُصيَّرِ نفسِه**
 *   عبر `tools/lib/repair01_w6_scan.php` — ولا يُقرأ عمودُ `dia_after` ولا
 *   مخرَجُ `repair01_ui_purity.php`. بوّابةٌ تقرأ مخرَجَ الأداةِ التي تفحصها
 *   حشوٌ لا فحص.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة** (‏§٣): الحواجبُ ترسو على أسماءِ
 *   الأعمدةِ والمفاتيحِ والأصنافِ وتُطبع برموزِها (`✘ W6-nn`) — لا على عبارةٍ
 *   عربيّةٍ يطابقها نصُّ حالةِ الخطأِ فيُخضِرَّ كذبًا.
 *
 * ◆ **ولا حاجبَ يفحص ما يضمنه المخطَّط** (تعريفُ الحاجبِ الأعمى · W02):
 *   لا `CHECK` يمنع التشكيلَ في `repair01_ui_labels` — الردُّ في الخدمةِ،
 *   و`W6-07` يشترط أن يكون الردُّ **قد وقع فعلًا** (صفٌّ في سجلِّ الرفض)
 *   لا أن يكون مُعلَنًا في شيفرة.
 *
 * ◆ **و`W6-18` تُغلِّف رحلةَ النصّ**: تشغّلها وتشترط عبورَ **كلِّ** محطّاتِها
 *   وأثرًا تجاريًّا مقيسًا عند كلِّ مستهلك — فلا تُقبل المرحلةُ ببناءِ أدواتِها
 *   إن لم تعبر رحلتُها (§٦-أ).
 *
 * التشغيل: php tools/repair01_w6_gate.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/tools/lib/repair01_w6_scan.php';
require_once $ROOT . '/tools/lib/repair01_w6_files.php';
require_once $ROOT . '/tools/lib/repair01_w6_transform.php';
require_once $ROOT . '/tools/lib/repair01_debt_scan.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
/* مرساةُ الطورِ صفرِ — **حقيقةٌ مسجَّلةٌ لا ثابتٌ حرفيّ** (RPR-AMD01) */
require_once __DIR__ . '/lib/repair01_w00_anchor.php';
$W00 = w00_anchors($conn);


use App\Services\Ui\UiPurity as P;

$PASS = 0; $FAIL = 0; $LINES = array();
function w6_pad($s, $len)
{
    $n = mb_strlen($s, 'UTF-8');
    return $s . ($n < $len ? str_repeat(' ', $len - $n) : ' ');
}
function gate($code, $title, $ok, $detail)
{
    global $PASS, $FAIL, $LINES;
    if ($ok) { $PASS++; $mark = '✔'; } else { $FAIL++; $mark = '✘'; }
    $LINES[] = '  ' . $mark . ' ' . str_pad($code, 9) . w6_pad($title, 42) . $detail;
}
$one = function ($sql) use ($conn) { return repair01_w6_one($conn, $sql); };
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

echo "═══════════ بوّابةُ المرحلةِ السادسة — REPAIR01 · نقاءُ لغةِ الواجهة ═══════════\n";

/* ══ القياسُ المُعادُ مرّةً واحدةً لكلِّ الحواجب ═══════════════════════════ */
$SRC   = repair01_w6_scan_sources($conn);
$REN   = repair01_w6_scan_rendered($ROOT, $conn);
$CYC   = repair01_w6_scan_cycle($conn);
$LEN   = repair01_w6_scan_length($ROOT, $conn);
$UNREG = repair01_w6_unregistered($ROOT, $conn);
$DEP   = repair01_w6_deprecated_live($conn);
$RAW   = repair01_w6_raw_codes($ROOT, $conn);
$LEAK  = repair01_w6_dev_only_leak($ROOT, $conn);

$sDia = 0; $sTech = 0; $sEq = 0; $sRows = 0;
foreach ($SRC as $m) { $sRows += $m['rows']; $sDia += $m['dia']; $sTech += $m['tech']; $sEq += $m['eq']; }
$dia  = $sDia  + count($REN['dia'])  + count($CYC['dia']);
$tech = $sTech + count($REN['tech']) + count($CYC['tech']);
$eq   = $sEq   + count($REN['eq'])   + count($CYC['eq']);

/* ══ W6-01 · النطاقُ مُعلَنٌ كاملًا — والمقامُ يشمل ما لا يُنقّى ═════════
     ⚠ **والمقامُ نما بمصدرٍ لا شكلَ له كإخوتِه** (‏الجولةُ الثانية · §٤-٥):
       `screen_files.php_text` — ملفُّ الشاشةِ نفسُه. فالمعرَّفُ سبعةَ عشرَ
       جدولًا **وواحدٌ**؛ ولو بقي الشرطُ على السبعةَ عشرَ لَسقطت البوّابةُ على
       اكتمالِ مقامِها هي — وهو الخطأُ نفسُه الذي أصلحه RPR-PATCH-02. */
$declared = (int) $one("SELECT COUNT(*) FROM repair01_w6_scope");
$defined  = count(repair01_w6_sources()) + 1;
$bare     = (int) $one("SELECT COUNT(*) FROM repair01_w6_scope
                         WHERE renderer = '' OR visibility_class = '' OR map_why = '' OR src_ref = ''");
$visBad   = (int) $one("SELECT COUNT(*) FROM repair01_w6_scope
                         WHERE visibility_class NOT IN ('USER_VISIBLE','AUDITOR_VISIBLE','ADMIN_VISIBLE','DEVELOPER_ONLY')");
gate('W6-01', 'النطاقُ مُعلَنٌ كاملًا بمُصيِّرِه وصنفِ ظهورِه',
     $declared === $defined && $defined > 0 && $bare === 0 && $visBad === 0,
     "مصادرُ الدفتر $declared · المعرَّفةُ $defined · بلا مُصيِّرٍ أو صنفٍ $bare · صنفٌ خارجَ الأربعة $visBad");

/* ══ W6-02 · تشكيلٌ ظاهرٌ صفر — في الصفوفِ وفي المُصيَّرِ معًا ══════════ */
gate('W6-02', 'تشكيلٌ ظاهرٌ صفر', $dia === 0 && $sRows > 0,
     "صفوفُ المصادر $sRows · تشكيلٌ في الصفوف $sDia · في المُصيَّر " . count($REN['dia'])
     . ' · في سطرِ الدورة ' . count($CYC['dia'])
     . (count($REN['dia']) ? ' ⇐ ' . $REN['dia'][0] : ''));

/* ══ W6-03 · مصطلحٌ تقنيٌّ ظاهرٌ صفر ═══════════════════════════════════ */
gate('W6-03', 'مصطلحٌ تقنيٌّ ظاهرٌ صفر', $tech === 0,
     "في الصفوف $sTech · في المُصيَّر " . count($REN['tech']) . ' · في سطرِ الدورة ' . count($CYC['tech'])
     . (count($CYC['tech']) ? ' ⇐ ' . $CYC['tech'][0] : ''));

/* ══ W6-04 · معادلةٌ ظاهرةٌ صفر ════════════════════════════════════════ */
gate('W6-04', 'معادلةٌ ظاهرةٌ صفر', $eq === 0,
     "في الصفوف $sEq · في المُصيَّر " . count($REN['eq']) . ' · في سطرِ الدورة ' . count($CYC['eq'])
     . (count($CYC['eq']) ? ' ⇐ ' . $CYC['eq'][0] : ''));

/* ══ W6-05 · اسمٌ مُصيَّرٌ خارجَ السجلِّ صفر ═══════════════════════════ */
gate('W6-05', 'اسمٌ مُصيَّرٌ خارجَ السجلِّ صفر',
     count($UNREG['missing']) === 0 && $UNREG['rendered'] > 0,
     'مُصيَّرٌ ' . $UNREG['rendered'] . ' · خارجَ السجلِّ ' . count($UNREG['missing'])
     . (count($UNREG['missing']) ? ' ⇐ ' . $UNREG['missing'][0] : ''));

/* ══ W6-06 · مسمًّى متقاعدٌ حيٌّ صفر — والقديمُ محفوظٌ لا محذوف ═════════ */
gate('W6-06', 'مسمًّى متقاعدٌ حيٌّ صفر',
     count($DEP['alive']) === 0 && $DEP['checked'] > 0,
     'متقاعدٌ مسجَّلٌ ' . $DEP['checked'] . ' · حيٌّ ' . count($DEP['alive'])
     . (count($DEP['alive']) ? ' ⇐ ' . $DEP['alive'][0] : ''));

/* ══ W6-07 · الردُّ **واقعٌ** لا مُعلَنٌ — ولا مخالفٌ في السجلّ ═════════
     الرسوُّ على البنية: صفوفُ سجلِّ الرفضِ بأصنافِها، **ومسحُ السجلِّ نفسِه**
     بالفواحصِ الأربعة. ولو كان الردُّ في `CHECK` لَما وقع صفٌّ يُقاس. */
$rejDia  = (int) $one("SELECT COUNT(*) FROM repair01_w6_reject_log WHERE reject_code = 'DIACRITICS'");
$rejTech = (int) $one("SELECT COUNT(*) FROM repair01_w6_reject_log WHERE reject_code = 'TECH_TERM'");
$rejNone = (int) $one("SELECT COUNT(*) FROM repair01_w6_reject_log WHERE reject_code = 'NOT_REGISTERED'");
$dirtyReg = 0; $dirtySample = '';
/* ⚠ **والنقطتانِ من المخالفاتِ هنا أيضًا** (‏الجولةُ الثانية): السجلُّ
     تراكميٌّ ولا يُمحى منه صفّ، فبقيت فيه ٣٥ صيغةً كتبتها الجولةُ الأولى
     بنقطتَين وإن لم يعد يُصيَّرها مصدر. وسجلٌّ حاكمٌ يحمل اسمًا تمنعه القاعدةُ
     ليس حاكمًا. والمتقاعدُ مستثنًى: هو **دليلُ الاستبدالِ** لا اسمٌ حيّ. */
$q = $conn->query("SELECT technical_key, arabic_ui_label FROM repair01_ui_labels
                    WHERE arabic_ui_label <> '' AND label_state <> 'DEPRECATED'");
while ($q && $x = $q->fetch_assoc()) {
    if (P::hasDiacritics($x['arabic_ui_label']) || P::hasTechTerm($x['arabic_ui_label'])
        || P::hasEquation($x['arabic_ui_label'])
        || P::hasNameColon(P::maskProtected($x['arabic_ui_label']))) {
        $dirtyReg++;
        if ($dirtySample === '') { $dirtySample = $x['technical_key']; }
    }
}
gate('W6-07', 'الردُّ واقعٌ ومُقيَّدٌ والسجلُّ نظيف',
     $rejDia > 0 && $rejTech > 0 && $rejNone > 0 && $dirtyReg === 0,
     "قيدُ تشكيلٍ $rejDia · قيدُ مصطلحٍ $rejTech · قيدُ غيابٍ $rejNone · مخالفٌ في السجلِّ $dirtyReg"
     . ($dirtySample !== '' ? " ⇐ $dirtySample" : ''));

/* ══ W6-08 · الطولُ الزائدُ لا يتجاوز سقفَه المُعلَن ═══════════════════ */
$declLen = (int) $one("SELECT scope_rows FROM repair01_w6_decisions
                        WHERE decision_id = 'W6-D-08' AND rationale IS NOT NULL AND rationale <> ''");
$overN = count($LEN['over']);
$renderedOver = 0;
foreach ($LEN['over'] as $o) { if (mb_strpos($o, 'بندٌ مُصيَّر') === 0) { $renderedOver++; } }
gate('W6-08', 'الطولُ الزائدُ تحت سقفِه ولا يُصيَّر',
     count($LEN['limits']) >= 4 && $overN <= $declLen && $renderedOver === 0,
     'حدودٌ ' . count($LEN['limits']) . " · مفحوصٌ {$LEN['checked']} · زائدٌ $overN"
     . " · المُعلَنُ في W6-D-08 $declLen · منه مُصيَّرٌ $renderedOver");

/* ══ W6-09 · لا رمزَ داخليٌّ خامٌّ مُصيَّرٌ والقاموسُ يغطّي المُعلَنَ ═══ */
$dictThree = (int) $one("SELECT COUNT(*) FROM repair01_w6_code_dict
                          WHERE raw_code IN ('NEEDS_SOURCE','NOT_YET_OCCURRED','NOT_APPLICABLE')
                            AND display_ar <> ''");
gate('W6-09', 'لا رمزَ داخليًّا خامًّا في نصٍّ مُصيَّر',
     count($RAW['raw']) === 0 && $dictThree === 3 && $RAW['dict'] > 3,
     'قاموسٌ ' . $RAW['dict'] . " رمزًا · الثلاثةُ المُعلَنةُ $dictThree · خامٌّ مُصيَّرٌ " . count($RAW['raw'])
     . (count($RAW['raw']) ? ' ⇐ ' . $RAW['raw'][0] : ''));

/* ══ W6-10 · صنفُ الظهورِ يُنفَّذ — `DEVELOPER_ONLY` لا يصل المستخدم ═══ */
$visN = (int) $one("SELECT COUNT(DISTINCT visibility_class) FROM repair01_ui_labels WHERE visibility_class <> ''");
$notRendered = (int) $one("SELECT COUNT(*) FROM repair01_w6_scope WHERE is_rendered = 0");
$notRenderedBad = (int) $one("SELECT COUNT(*) FROM repair01_w6_scope
                               WHERE is_rendered = 0 AND visibility_class = 'USER_VISIBLE'");
gate('W6-10', 'صنفُ الظهورِ مُنفَّذٌ لا مُعلَنٌ فقط',
     count($LEAK['leaked']) === 0 && $visN >= 1 && $notRendered > 0 && $notRenderedBad === 0,
     'أصنافٌ مستعمَلةٌ ' . $visN . ' · مصدرٌ لا يُصيَّر ' . $notRendered
     . " · منه مُصنَّفٌ للمستخدمِ $notRenderedBad · تسريبُ تقنيٍّ " . count($LEAK['leaked']));

/* ══ W6-11 · المولِّدُ مُصلَحٌ قبل المولَّد — رسوٌّ على بنيةِ الشيفرة ════ */
$wisSrc = (string) @file_get_contents($ROOT . '/app/Services/Work/WorkItemService.php');
$joinGone = mb_strpos($wisSrc, "' — ' . \$act['screen_title']") === false
         && mb_strpos($wisSrc, '$act[\'label_ar\'] . \' — \'') === false;
$readsReg = mb_strpos($wisSrc, 'UiLabelRegistry::label') !== false;
$keepsScreen = mb_strpos($wisSrc, "'source_screen' => (string) \$act['canonical_file']") !== false;
gate('W6-11', 'المولِّدُ يقرأ السجلَّ بلا شرطةِ ربط',
     $joinGone && $readsReg && $keepsScreen,
     'شرطةُ الربطِ ' . ($joinGone ? 'مرفوعة' : 'قائمة')
     . ' · قراءةُ السجلِّ ' . ($readsReg ? 'قائمة' : 'غائبة')
     . ' · الشاشةُ محفوظةٌ في source_screen ' . ($keepsScreen ? 'نعم' : 'لا'));

/* ══ W6-12 · العتبةُ من السجلِّ لا من الشيفرة (‏§٥) ════════════════════ */
$thN   = (int) $one("SELECT COUNT(*) FROM repair01_w6_thresholds WHERE value_no > 0");
$thBare = (int) $one("SELECT COUNT(*) FROM repair01_w6_thresholds WHERE why = '' OR applies_to = '' OR src_ref = ''");
$hard = 0; $hardWhere = '';
foreach (array('/tools/repair01_ui_purity.php', '/tools/repair01_w6_gate.php',
               '/tools/lib/repair01_w6_scan.php', '/app/Services/Ui/UiPurity.php',
               '/app/Services/Ui/UiLabelRegistry.php') as $rel) {
    $s = (string) @file_get_contents($ROOT . $rel);
    if (preg_match('~mb_strlen\s*\([^)]*\)\s*[<>]=?\s*\d~', $s)) { $hard++; $hardWhere = $rel; }
}
gate('W6-12', 'حدُّ الطولِ من السجلِّ لا من الشيفرة',
     $thN >= 4 && $thBare === 0 && $hard === 0,
     "عتباتٌ مسجَّلةٌ $thN · بلا عذرٍ أو مرجعٍ $thBare · مقارنةُ طولٍ صلبةٌ في أداةٍ $hard"
     . ($hardWhere !== '' ? " ⇐ $hardWhere" : ''));

/* ══ W6-13 · صنفا الدَّينِ مقفلا الاتّجاهِ من اليومِ الأوّل (‏§٤-٩) ════ */
$uiCls = repair01_ui_debt_classes();
$baseFile = $ROOT . '/docs/update0012/debt_baseline.json';
$bj = is_file($baseFile) ? json_decode((string) file_get_contents($baseFile), true) : null;
$baseDebts = (is_array($bj) && isset($bj['debts'])) ? $bj['debts'] : array();
$rp = repair01_debt_measure($ROOT, $conn);
$lockOk = true; $lockDetail = array();
foreach (array_keys($uiCls) as $k) {
    $now  = isset($rp['counts'][$k]) ? $rp['counts'][$k] : null;
    $base = isset($baseDebts[$k]) ? (int) $baseDebts[$k] : null;
    if ($now === null || $base === null || $now > $base) { $lockOk = false; }
    $lockDetail[] = $k . ' ' . ($now === null ? '—' : $now) . '/' . ($base === null ? '—' : $base);
}
gate('W6-13', 'صنفا الدَّينِ مقفلا الاتّجاه', $lockOk && count($uiCls) === 2,
     implode(' · ', $lockDetail) . ' · أصنافٌ ' . count($uiCls));

/* ══ W6-14 · آلةُ الحالةِ لكلِّ كيانٍ — بممنوعٍ صريحٍ لا مسكوتٍ عنه ════ */
$entN   = (int) $one("SELECT COUNT(DISTINCT entity) FROM repair01_w6_states");
$allow  = (int) $one("SELECT COUNT(*) FROM repair01_w6_states WHERE allowed = 1");
$deny   = (int) $one("SELECT COUNT(*) FROM repair01_w6_states WHERE allowed = 0");
$noRule = (int) $one("SELECT COUNT(*) FROM repair01_w6_states
                       WHERE allowed = 1 AND (owner_role = '' OR precondition = ''
                          OR official_doc = '' OR approval_gate = '' OR reopen_rule = '' OR correct_rule = '')");
$denyBare = (int) $one("SELECT COUNT(*) FROM repair01_w6_states WHERE allowed = 0 AND precondition = ''");
$entNoDeny = (int) $one("SELECT COUNT(*) FROM (SELECT entity FROM repair01_w6_states
                          GROUP BY entity HAVING SUM(allowed = 0) = 0) z");
gate('W6-14', 'آلةُ حالةٍ لكلِّ كيانٍ بممنوعٍ صريح',
     $entN >= 2 && $allow > 0 && $deny > 0 && $noRule === 0 && $denyBare === 0 && $entNoDeny === 0,
     "كياناتٌ $entN · مسموحٌ $allow · ممنوعٌ صراحةً $deny · مسموحٌ ناقصُ الحقول $noRule"
     . " · ممنوعٌ بلا سبب $denyBare · كيانٌ بلا ممنوعٍ $entNoDeny");

/* ══ W6-15 · فصلُ الواجباتِ بالأدوارِ الستّةِ وتركيبةٍ ممنوعةٍ صراحةً ══ */
$sodN = (int) $one("SELECT COUNT(*) FROM repair01_w6_sod");
$sodBare = (int) $one("SELECT COUNT(*) FROM repair01_w6_sod
                        WHERE initiator_role = '' OR reviewer_role = '' OR approver_role = ''
                           OR executor_role = '' OR closer_role = '' OR forbidden_combo = ''
                           OR authority_rule_id = '' OR deputy_role = '' OR scope_rule = ''
                           OR delegation = '' OR effective_date IS NULL");
$sodSelf = (int) $one("SELECT COUNT(*) FROM repair01_w6_sod
                        WHERE initiator_role = approver_role OR executor_role = closer_role");
gate('W6-15', 'فصلُ الواجباتِ بستّةِ أدوارٍ وتركيبةٍ ممنوعة',
     $sodN > 0 && $sodBare === 0 && $sodSelf === 0,
     "عملياتٌ $sodN · صفٌّ ناقصٌ $sodBare · دورٌ يجمع اعتمادَه أو إقفالَه $sodSelf");

/* ══ W6-16 · حدثُ المرحلةِ بعقدِ أثرٍ واحدٍ كاملٍ بمستهلكيه بالاسم ════ */
$evN = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = 'W06'");
$evDup = (int) $one("SELECT COUNT(*) FROM (SELECT event_code FROM repair01_events
                      WHERE contract_stage = 'W06' GROUP BY event_code HAVING COUNT(*) > 1) z");
$evThin = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = 'W06'
                       AND (trigger_rule = '' OR min_payload = '' OR consumer_list IS NULL
                         OR consumer_list = '' OR consumer_effect IS NULL OR consumer_effect = ''
                         OR preconditions = '' OR retry_policy = '' OR idempotency_key = ''
                         OR failure_policy = '' OR compensation = '')");
$evAll  = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE contract_stage = 'W06'
                       AND consumer_effect LIKE '%كلُّ المستهلكين%'");
gate('W6-16', 'حدثُ المرحلةِ بعقدِ أثرٍ واحدٍ كامل',
     $evN > 0 && $evDup === 0 && $evThin === 0 && $evAll === 0,
     "عقودٌ $evN · مكرَّرٌ $evDup · ناقصٌ $evThin · «كلُّ المستهلكين» $evAll");

/* ══ W6-17 · أساسُ المراحلِ السابقةِ لم يُمَسّ ══════════════════════════ */
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
$k0 = (int) $one("SELECT COUNT(*) FROM repair01_key_registry");
gate('W6-17', 'أساسُ المراحلِ السابقةِ لم يُمَسّ',
     $d0 === $W00['decisions'] && $s0 === $W00['source_files'] && $u0 === $W00['surfaces'] && $g0 === $W00['registry_base'] && $gWild === 0
     && $t0 === $W00['gaps_original'] && $e0 === $W00['events_study'] && $e3 === 13 && $e4 === 3 && $e5 > 0 && $k0 === 13,
     "قرارات $d0 · مصادر $s0 · أسطح $u0 · أساسُ السجلّ $g0 · نموٌّ مختومٌ $gNew · بلا ختمٍ $gWild"
     . " · فجواتٌ $t0 · أحداثُ الدراسة $e0 · عقود W03 $e3 · W04 $e4 · W05 $e5 · مفاتيح $k0");

/* ══ W6-18 · رحلةُ النصّ — تُشغَّل هنا ويُشترط عبورُها كاملةً (§٦-أ) ══════
     ⚠ ومُعرِّفُ الجولةِ يُقرأ من مخرَجِ الرحلةِ لا من «آخرِ صفٍّ في الجدول»:
       رحلةٌ لم تنعقد تترك دليلَ الجولةِ السابقةِ قائمًا فتُخضِرُّ على عبورٍ
       لم يقع. ولا مُعرِّفَ في المخرَجِ ⇒ لا رحلةَ ⇒ الحاجبُ يسقط. */
$jOut = array(); $jCode = 1;
@exec('"' . PHP_BINARY . '" "' . $ROOT . '/tools/repair01_w6_journey.php" 2>&1', $jOut, $jCode);
$run = '';
foreach ($jOut as $l) { if (preg_match('/^RUN=(W6J-\d+)$/', trim($l), $m)) { $run = $m[1]; break; } }
$jTotal = (int) $one("SELECT COUNT(*) FROM repair01_w6_journey WHERE run_id = '" . $esc($run) . "'");
$jPass  = (int) $one("SELECT COUNT(*) FROM repair01_w6_journey WHERE run_id = '" . $esc($run) . "' AND passed = 1");
$jNoEff = (int) $one("SELECT COUNT(*) FROM repair01_w6_journey
                       WHERE run_id = '" . $esc($run) . "' AND (business_effect = '' OR business_effect = '—')");
$jCons  = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w6_journey
                       WHERE run_id = '" . $esc($run) . "' AND consumer <> ''");
$jNoTxt = (int) $one("SELECT COUNT(*) FROM repair01_w6_journey
                       WHERE run_id = '" . $esc($run) . "' AND rendered_text = ''");
gate('W6-18', 'رحلةُ النصِّ تعبر بمحطّاتِها',
     $run !== '' && $jTotal > 0 && $jPass === $jTotal && $jNoEff === 0 && $jNoTxt === 0 && $jCons >= 8,
     "الجولة " . ($run !== '' ? $run : '—') . " · عابرٌ $jPass/$jTotal · مستهلكونَ متمايزون $jCons"
     . " · بلا أثرٍ تجاريٍّ $jNoEff · بلا نصٍّ مُصيَّرٍ $jNoTxt");

/* ══ W6-19 · تشكيلٌ في **ملفّاتِ الشاشاتِ** صفر (‏الجولةُ الثانية · §٤-٥) ══
     ⚠ **وهذا هو الحاجبُ الذي لم يكن**: الجولةُ الأولى خرجت خضراءَ ١٨/١٨ وهي
       تفحص سبعةَ جداولَ فقط، و٨٧٢ ملفَّ شاشةٍ خارجَ مقامِها. والقياسُ هنا
       **يُعاد من الملفّاتِ نفسِها** لا من `repair01_w6_file_log` — «بوّابةٌ
       تقرأ مخرَجَ الأداةِ التي تفحصها حشوٌ لا فحص».
     ◆ **والمُعذَرُ مشروطٌ بإعلانِه**: ما أُعفي لاقترانِه **يجب أن يكون مكتوبًا
       في `repair01_w6_coupled` بعددِه**، وإلّا كان الصفرُ سترًا لا نقاءً. */
$FSC = repair01_w6_files_scan($ROOT, $conn);
$cpDecl = (int) $one("SELECT COUNT(*) FROM repair01_w6_coupled");
$cpBare = (int) $one("SELECT COUNT(*) FROM repair01_w6_coupled WHERE couple_kind = '' OR first_seen = '' OR why = ''");
$cpVocab = count(repair01_w6_coupled_vocab($ROOT, $conn));
gate('W6-19', 'تشكيلٌ في ملفّاتِ الشاشاتِ صفر',
     $FSC['ui'] === 0 && $FSC['files'] > 0 && $FSC['unparsed'] === 0
     && $cpDecl === $cpVocab && $cpDecl > 0 && $cpBare === 0,
     'ملفّاتٌ ' . $FSC['files'] . ' · تشكيلُ واجهةٍ ' . $FSC['ui']
     . ' · مقترنٌ مُعذَرٌ ' . ($FSC['coupled'] + $FSC['excused'])
     . ' · معجمٌ مُعلَنٌ ' . $cpDecl . '/' . $cpVocab . ' · تعليقٌ خارجَ المقام ' . $FSC['comment']
     . ($FSC['ui'] ? ' ⇐ ' . key($FSC['top']) : ''));

/* ══ W6-20 · **نقطتانِ في اسمِ عنصرٍ صفر** (‏القاعدة ③ · §٤-٤) ════════════
     ⚠ **والجولةُ الأولى هي التي أدخلتها**: `stripDecoration` كانت تحوّل
       الشرطةَ ` — ` إلى `: ` فولّدت ٣٩٨ اسمًا مخالفًا — والبوّابةُ عمياءُ عنها
       لأنَّ كاشفَها لم يكن يعرف النقطتَين. والحاجبُ يرسو على **الكاشفِ
       والمصادرِ** لا على عبارة، ويقيس المخزَّنَ **والمُصيَّرَ** معًا. */
$colonRows = 0; $colonSample = '';
foreach (repair01_w6_rendered_sources() as $ck => $csrc) {
    foreach (repair01_w6_read($conn, $csrc) as $rk => $v) {
        if (P::hasNameColon(P::maskProtected($v, (int) $csrc['composite'] === 1))) {
            $colonRows++;
            if ($colonSample === '') { $colonSample = $ck . ' › ' . mb_substr($v, 0, 50); }
        }
    }
}
$colonRendered = 0;
foreach (array('groups', 'sections', 'labels') as $b) {
    foreach (array_keys(repair01_w6_rendered_text($ROOT, $conn)[$b]) as $s) {
        if (P::hasNameColon($s)) { $colonRendered++; if ($colonSample === '') { $colonSample = 'مُصيَّر › ' . $s; } }
    }
}
/* والكاشفُ نفسُه يجب أن يكون حيًّا: نصٌّ فيه نقطتانِ يُرَدُّ، ووقتٌ لا يُرَدّ */
$detectorLive = P::hasNameColon('حالة الطلب: معتمد') && !P::hasNameColon('لحظة القراءة 14:30');
/* ⚠ **والفحصُ على `stripDecoration` نفسِها لا على مخرَجِ `purifyGenerated`**:
     الأخيرةُ تنزع النقطتَين في خطوةٍ تالية، فلو ارتدَّت قاعدةُ الشرطةِ إلى
     حالِ الجولةِ الأولى لَستَرَ الحزامُ الثاني الارتدادَ وخرج المخرَجُ نظيفًا —
     **والحاجبُ يقرأ سلامةً لعطبٍ قائم**. قِيس في الفحصِ السلبيِّ: الكسرُ لم
     يُسقِط الحاجب. فالرسوُّ على **القاعدةِ التي أمر بإصلاحِها §٤-٤** لا على
     أثرِها بعد أن يُصلحه غيرُها. */
$dashKeeps = mb_strpos(P::stripDecoration('لوحة الإدارة — النقل'), ':') === false
          && mb_strpos(P::purifyGenerated('لوحة الإدارة — النقل'), ':') === false;
gate('W6-20', 'نقطتانِ في اسمِ عنصرٍ صفر',
     $colonRows === 0 && $colonRendered === 0 && $detectorLive && $dashKeeps,
     "في المصادر $colonRows · في المُصيَّر $colonRendered · الكاشفُ "
     . ($detectorLive ? 'حيّ' : 'أعمى') . ' · الشرطةُ لا تصير نقطتَين '
     . ($dashKeeps ? 'نعم' : 'لا') . ($colonSample !== '' ? " ⇐ $colonSample" : ''));

/* ══ الحكم ═══════════════════════════════════════════════════════════════ */
foreach ($LINES as $l) { echo $l . "\n"; }
echo str_repeat('─', 108) . "\n";
printf("W6 gate: %d/%d  ·  تشكيلٌ في الجداول %d · في ملفّاتِ الشاشات %d · نقطتانِ في اسمٍ %d · مصطلحٌ تقنيٌّ %d · معادلةٌ %d · اسمٌ خارجَ السجلّ %d · متقاعدٌ حيٌّ %d  ·  رحلةٌ %d/%d\n",
    $PASS, $PASS + $FAIL, $dia, $FSC['ui'], $colonRows + $colonRendered, $tech, $eq,
    count($UNREG['missing']), count($DEP['alive']), $jPass, $jTotal);
echo 'الحكم: ' . ($FAIL === 0 ? "خضراء ✔\n" : "ساقطةٌ في $FAIL ✘\n");
exit($FAIL === 0 ? 0 : 1);
