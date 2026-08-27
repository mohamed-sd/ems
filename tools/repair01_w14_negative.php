<?php
/**
 * tools/repair01_w14_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الرابعةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويُطلَب من البوّابةِ أن تسقط — ثمَّ
 *   تُرجَع الحالة. والحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على رمزِ الحاجبِ لا على عبارتِه**: نصُّ حالةِ الخطأِ يطابق
 *   العبارةَ العربيّةَ فيُخضِرُّ كذبًا — فالالتقاطُ على `✘ W14-nn`.
 *
 * ◆ **وصنفٌ ثانٍ: ما يمنعه المخطَّط** (`DB_REFUSED`). قيدُ القاعدةِ لا يُكسَر
 *   من حسابِ التطبيق — فيُختبَر بالاتّجاهِ المعاكس: **تُحاوَل الكتابةُ المخالفةُ
 *   ويُشترط أن تُرَدّ**. وهذا فحصٌ سلبيٌّ للقيدِ لا إعفاءٌ منه.
 *
 * ◆ **وصنفٌ ثالث: حزامٌ ثانٍ خلفَ قيدٍ في القاعدة** (‏سابقةُ `W12-25`): محورٌ
 *   يمنعه `CHECK` لا يقع من حسابِ التطبيق، فيُقرأ حاجبُه «لم يُختبَر» لا «يقظ».
 *   فيُعطَّل القيدُ **عمدًا وللحظةٍ** ليُختبَر الحزامُ الثاني، ⛔ **ولا يُحذَف
 *   ولا يُخفَّف** — والإرجاعُ يُتحقَّق منه بإعادةِ قياسِ وجودِه في المخطَّط.
 *
 * ◆ **وصنفٌ رابع: ما تمنعه بنيةُ ملفّ** — يُشوَّه ثمَّ يُرجَع بايتًا ببايت،
 *   والإرجاعُ يُتحقَّق منه بالمقارنة.
 *
 * ⚠ **وحمولةُ الكسرِ تُركَّب ولا تُكتب رقمًا في موضعِ مقارنة** (‏عطبُ W12 الرابع):
 *   كاشفُ العتبةِ الصلبةِ يمسح أدواتِ الموجةِ ومنها هذا الملفُّ.
 *
 * التشغيل: php tools/repair01_w14_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x ? $x[0] : null;
};

/* **والحزامُ الثاني يحتاج يدًا تملك `ALTER`** — و`ems_app` لا يملكها بالتصميم
   (‏وهو صحيح). فتُفتَح وصلةُ مستخدمِ الهجراتِ **لتعطيلِ القيدِ لحظةً وإعادتِه
   وحدَه**، ⛔ ولا تُستعمل في كسرِ بياناتٍ ولا في قياس. */
require_once $ROOT . '/includes/env.php';
$mhost = ems_env('DB_HOST'); $mport = 3306;
if (strpos($mhost, ':') !== false) { list($mhost, $mport) = explode(':', $mhost); $mport = (int) $mport; }
mysqli_report(MYSQLI_REPORT_OFF);
$mig = null;
if (ems_env('DB_MIGRATOR_USER')) {
    $mig = new mysqli($mhost, ems_env('DB_MIGRATOR_USER'), ems_env('DB_MIGRATOR_PASS'),
                      ems_env('DB_NAME'), $mport);
    if ($mig->connect_errno) { $mig = null; } else { $mig->set_charset('utf8mb4'); }
}

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w14_gate.php';
$SVC_G = $ROOT . '/app/Services/Governance/GovernanceDomainService.php';
$SVC_A = $ROOT . '/app/Services/Audit/AuditDomainService.php';
$SCR   = $ROOT . '/Governance/breaches.php';
$LIB   = $ROOT . '/tools/lib/repair01_w14_scan.php';

/** يشغّل البوّابةَ ويعيد [رمزُ الخروج، أيُّ حاجبٍ سقط] */
function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W14-') !== false && preg_match('/W14-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ── قيمٌ تُلتقَط قبل الكسرِ ليكون الإرجاعُ بالقيمةِ لا بالتخمين ────────── */
$svcGOrig = (string) file_get_contents($SVC_G);
$svcAOrig = (string) file_get_contents($SVC_A);
$scrOrig  = (string) file_get_contents($SCR);
$libOrig  = (string) file_get_contents($LIB);

$scopeReq  = (string) $one("SELECT requirement_id FROM repair01_w14_scope ORDER BY requirement_id LIMIT 1");
$scopeRule = (string) $one("SELECT map_rule FROM repair01_w14_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$anchorRt  = (string) $one("SELECT anchor_route FROM repair01_w14_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$scopeDom  = (string) $one("SELECT domain_code FROM repair01_w14_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$sbSid     = (string) $one("SELECT screen_id FROM repair01_w14_sidebar ORDER BY screen_id LIMIT 1");
$sbPerm    = (int) $one("SELECT s6_perm_rows FROM repair01_w14_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbS5      = (string) $one("SELECT s5_verdict FROM repair01_w14_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbLabel   = (string) $one("SELECT s2_verdict FROM repair01_w14_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbRoute   = (string) $one("SELECT route FROM repair01_w14_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbSort    = (int) $one("SELECT sort_no FROM nav_canonical WHERE route = '" . $esc($sbRoute) . "' LIMIT 1");
$growSid   = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE origin = 'W14' ORDER BY screen_id LIMIT 1");
$growLabel = (string) $one("SELECT canonical_label_ar FROM repair01_screen_registry WHERE screen_id = '" . $esc($growSid) . "'");
$domTbl    = (string) $one("SELECT table_name FROM repair01_w14_domains WHERE domain_code = 'DEP-09' ORDER BY table_name LIMIT 1");
$domSvc    = (string) $one("SELECT service_file FROM repair01_w14_domains WHERE table_name = '" . $esc($domTbl) . "'");
$stEntity  = (string) $one("SELECT entity FROM repair01_w14_states WHERE allowed = 0 ORDER BY entity LIMIT 1");
$stFrom    = (string) $one("SELECT from_state FROM repair01_w14_states WHERE allowed = 0
                             AND entity = '" . $esc($stEntity) . "' ORDER BY from_state LIMIT 1");
$stWhy     = (string) $one("SELECT forbid_why FROM repair01_w14_states WHERE allowed = 0
                             AND entity = '" . $esc($stEntity) . "' AND from_state = '" . $esc($stFrom) . "' LIMIT 1");
$sodKey    = (string) $one("SELECT process_key FROM repair01_w14_sod ORDER BY process_key LIMIT 1");
$sodCombo  = (string) $one("SELECT forbidden_combo FROM repair01_w14_sod WHERE process_key = '" . $esc($sodKey) . "'");
$evCode    = 'ctl.deviation.classified';
$evCons    = (string) $one("SELECT consumer_list FROM repair01_events WHERE event_code = '$evCode' AND wave = 'W14'");
$thKey     = 'rsk.trigger.unplanned_downtime_hours';
$thRef     = (string) $one("SELECT decision_ref FROM repair01_w14_thresholds WHERE threshold_key = '$thKey'");
$defId     = (string) $one("SELECT deferred_id FROM repair01_w14_deferred ORDER BY deferred_id LIMIT 1");
$defBuilt  = (string) $one("SELECT built_anyway FROM repair01_w14_deferred WHERE deferred_id = '" . $esc($defId) . "'");
$dictCode  = (string) $one("SELECT raw_code FROM repair01_w6_code_dict
                             WHERE src_ref LIKE 'RPR-W14%' ORDER BY raw_code LIMIT 1");
$dictAr    = (string) $one("SELECT display_ar FROM repair01_w6_code_dict WHERE raw_code = '" . $esc($dictCode) . "'");
$famNode   = (string) $one("SELECT node_code FROM rsk_taxonomy WHERE depth_no = 1 ORDER BY node_code LIMIT 1");
$famCode   = (string) $one("SELECT family_code FROM rsk_taxonomy WHERE node_code = '" . $esc($famNode) . "'");
$decWhy    = (string) $one("SELECT rationale FROM repair01_w14_decisions WHERE decision_id = 'W14-D-01'");
$fixKey    = (string) $one("SELECT fix_key FROM repair01_w14_fixes ORDER BY fix_key LIMIT 1");
$fixRev    = (string) $one("SELECT revealed_by FROM repair01_w14_fixes WHERE fix_key = '" . $esc($fixKey) . "'");
$jRunId    = (string) $one("SELECT run_id FROM repair01_w14_journey ORDER BY id DESC LIMIT 1");
$jRowId    = (int) $one("SELECT id FROM repair01_w14_journey WHERE run_id = '" . $esc($jRunId) . "' ORDER BY id LIMIT 1");
$jEffect   = (string) $one("SELECT business_effect FROM repair01_w14_journey WHERE id = $jRowId");
$company   = (int) $one("SELECT company_id FROM employees WHERE company_id > 0
                          GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
$fndId     = (int) $one("SELECT id FROM iaf_findings ORDER BY id LIMIT 1");
$fndSetBy  = (string) $one("SELECT COALESCE(result_set_by_dept,'') FROM iaf_findings WHERE id = $fndId");
$prgId     = (int) $one("SELECT id FROM iaf_program ORDER BY id LIMIT 1");
/* **ومسارُ الكسرِ يجب أن يكون من أسطحِ الموجة** — وإلّا مسَّ التحديثُ صفرَ
   صفوفٍ فقُرئ «الحاجبُ أعمى» وهو يقظٌ ولم يُكسَر شيءٌ أصلًا. */
$newRoute  = (string) $one("SELECT route FROM repair01_screen_registry
                             WHERE origin = 'W14' ORDER BY screen_id LIMIT 1");

if ($scopeReq === '' || $sbSid === '' || $growSid === '' || $stEntity === '' || $dictCode === ''
    || $company <= 0 || $jRunId === '' || $domTbl === '' || $defId === '') {
    echo "✘ أرضيّةٌ ناقصةٌ للكسر — شغّلْ tools/repair01_w14_apply.php ثمَّ tools/repair01_w14_journey.php\n";
    exit(1);
}

/* **مُعرِّفاتٌ تركيبيّةٌ لا مكتوبةٌ رقمًا في موضعِ مقارنة** */
$NEG = 'W14NEG';
$sw = function () use ($conn, $NEG) {
    foreach (array("DELETE FROM gov_breach WHERE case_no LIKE '$NEG%'",
                   "DELETE FROM rsk_trigger WHERE trigger_no LIKE '$NEG%'",
                   "DELETE FROM rsk_event WHERE event_no LIKE '$NEG%'",
                   "DELETE FROM gov_investigation WHERE inv_no LIKE '$NEG%'",
                   "DELETE FROM gov_related_party WHERE party_no LIKE '$NEG%'",
                   "DELETE FROM ctl_deviation WHERE deviation_no LIKE '$NEG%'") as $q) {
        @$conn->query($q);
    }
};
$sw();

$pass = 0; $blind = array();

/* ══════════════════════════════════════════════════════════════════════════
   ① الصنفُ الأوّل — حاجبٌ يُكسَر من حسابِ التطبيقِ ثمَّ يُرجَع
   ══════════════════════════════════════════════════════════════════════════ */
$CASES = array(
    array('W14-01', 'نزعُ قاعدةِ الربطِ عن متطلَّبٍ في النطاق',
        "UPDATE repair01_w14_scope SET map_rule = '' WHERE requirement_id = '" . $esc($scopeReq) . "'",
        "UPDATE repair01_w14_scope SET map_rule = '" . $esc($scopeRule) . "'
           WHERE requirement_id = '" . $esc($scopeReq) . "'"),

    array('W14-02', 'وسمُ مِرساةٍ مبنيّةٍ بأنّها ليست على القرص',
        "UPDATE repair01_screen_registry SET on_disk = 0 WHERE route = '" . $esc($anchorRt) . "'",
        "UPDATE repair01_screen_registry SET on_disk = 1 WHERE route = '" . $esc($anchorRt) . "'"),

    array('W14-03', 'نزعُ خطِّ الدفاعِ عن سطحٍ في النطاق',
        "UPDATE repair01_w14_scope SET line_of_defence = '' WHERE requirement_id = '" . $esc($scopeReq) . "'",
        "UPDATE repair01_w14_scope SET line_of_defence = 'SECOND', domain_code = '" . $esc($scopeDom) . "'
           WHERE requirement_id = '" . $esc($scopeReq) . "'"),

    array('W14-04', 'نزعُ حكمِ خطوةٍ من خطواتِ السايدبارِ السبع',
        "UPDATE repair01_w14_sidebar SET s5_verdict = '' WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w14_sidebar SET s5_verdict = '" . $esc($sbS5) . "'
           WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W14-05', 'إعادةُ انحرافِ الاسمِ بعد تصحيحِه',
        "UPDATE repair01_w14_sidebar SET s2_verdict = 'LABEL_DRIFT' WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w14_sidebar SET s2_verdict = '" . $esc($sbLabel) . "'
           WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W14-06', 'تصفيرُ منحِ الصلاحيةِ على سطحٍ من النطاق',
        "UPDATE repair01_w14_sidebar SET s6_perm_rows = 0 WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w14_sidebar SET s6_perm_rows = " . $sbPerm . "
           WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W14-07', 'إزاحةُ ترتيبِ سطحٍ عن موضعِه من دورةِ العمل',
        "UPDATE nav_canonical SET sort_no = sort_no + 1 WHERE route = '" . $esc($sbRoute) . "'",
        "UPDATE nav_canonical SET sort_no = " . $sbSort . " WHERE route = '" . $esc($sbRoute) . "'"),

    array('W14-08', 'فكُّ ربطِ سطحٍ بالسجلِّ المعياريّ',
        "UPDATE repair01_w14_sidebar SET s7_linked = 0 WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w14_sidebar SET s7_linked = 1 WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W14-09', 'نزعُ ختمِ الموجةِ عن سطحِ نموّ',
        "UPDATE repair01_screen_registry SET origin = 'XX' WHERE screen_id = '" . $esc($growSid) . "'",
        "UPDATE repair01_screen_registry SET origin = 'W14' WHERE screen_id = '" . $esc($growSid) . "'"),

    array('W14-10', 'نزعُ حقلٍ من حقولِ السقّاطةِ الاثنَي عشرَ عن سطحِ نموّ',
        "UPDATE repair01_screen_registry SET canonical_label_ar = '' WHERE screen_id = '" . $esc($growSid) . "'",
        "UPDATE repair01_screen_registry SET canonical_label_ar = '" . $esc($growLabel) . "'
           WHERE screen_id = '" . $esc($growSid) . "'"),

    array('W14-12', 'نزعُ الخدمةِ المالكةِ عن جدولِ نطاق',
        "UPDATE repair01_w14_domains SET service_file = '' WHERE table_name = '" . $esc($domTbl) . "'",
        "UPDATE repair01_w14_domains SET service_file = '" . $esc($domSvc) . "'
           WHERE table_name = '" . $esc($domTbl) . "'"),

    /* **محورُ المرحلةِ الأوّل — يُكسَر من حسابِ التطبيقِ لأنَّ القاعدةَ تسمح به
       بنيويًّا: الانحرافُ صحيحٌ والحالةُ صحيحةٌ، والخطأُ في **الربطِ بينهما**. */
    array('W14-14', 'فتحُ حالةِ حوكمةٍ على انحرافٍ مُصنَّفٍ انحرافًا فقط',
        array("INSERT INTO ctl_deviation (company_id,deviation_no,owner_dept,source_module,source_table,
                 source_row_id,deviation_kind,classification,rule_code,classified_by,state)
               VALUES ($company,'$NEG-DEV','DEP-04','mnt','equipments',1,'UNPLANNED_DOWNTIME',
                       'DEVIATION_ONLY','CTLR-DOWNTIME',1,'retained')",
              "INSERT INTO gov_breach (company_id,case_no,opened_basis,control_ref,deviation_no,
                 severity,title_ar,opened_by,state)
               VALUES ($company,'$NEG-CASE','CONTROL_BROKEN','C-NEG','$NEG-DEV','low','كسر متعمد',1,'opened')"),
        array("DELETE FROM gov_breach WHERE case_no = '$NEG-CASE'",
              "DELETE FROM ctl_deviation WHERE deviation_no = '$NEG-DEV'")),

    array('W14-20', 'نزعُ سببِ الممنوعِ الصريحِ من آلةِ حالة',
        "UPDATE repair01_w14_states SET forbid_why = '' WHERE entity = '" . $esc($stEntity) . "'
           AND from_state = '" . $esc($stFrom) . "' AND allowed = 0",
        "UPDATE repair01_w14_states SET forbid_why = '" . $esc($stWhy) . "'
           WHERE entity = '" . $esc($stEntity) . "' AND from_state = '" . $esc($stFrom) . "' AND allowed = 0"),

    array('W14-21', 'نزعُ التركيبةِ الممنوعةِ عن عمليةٍ حرجة',
        "UPDATE repair01_w14_sod SET forbidden_combo = '' WHERE process_key = '" . $esc($sodKey) . "'",
        "UPDATE repair01_w14_sod SET forbidden_combo = '" . $esc($sodCombo) . "'
           WHERE process_key = '" . $esc($sodKey) . "'"),

    array('W14-22', 'إبهامُ مستهلكِ عقدِ أثر',
        "UPDATE repair01_events SET consumer_list = 'كل المستهلكين'
           WHERE event_code = '$evCode' AND wave = 'W14'",
        "UPDATE repair01_events SET consumer_list = '" . $esc($evCons) . "'
           WHERE event_code = '$evCode' AND wave = 'W14'"),

    array('W14-24', 'نزعُ سجلِّ القراءةِ عن عتبةٍ من عتباتِ النطاق',
        "UPDATE repair01_w14_thresholds SET registry = '' WHERE threshold_key = '$thKey'",
        "UPDATE repair01_w14_thresholds SET registry = 'Risk Trigger Rules'
           WHERE threshold_key = '$thKey'"),

    array('W14-26', 'نزعُ بيانِ ما بُني رغمَ التأجيل',
        "UPDATE repair01_w14_deferred SET built_anyway = '' WHERE deferred_id = '" . $esc($defId) . "'",
        "UPDATE repair01_w14_deferred SET built_anyway = '" . $esc($defBuilt) . "'
           WHERE deferred_id = '" . $esc($defId) . "'"),

    array('W14-27', 'نزعُ المسمّى العربيِّ عن رمزٍ مُعلَن',
        "UPDATE repair01_w6_code_dict SET display_ar = '' WHERE raw_code = '" . $esc($dictCode) . "'",
        "UPDATE repair01_w6_code_dict SET display_ar = '" . $esc($dictAr) . "'
           WHERE raw_code = '" . $esc($dictCode) . "'"),

    array('W14-29', 'محطّةُ رحلةٍ بلا أثرٍ تجاريّ',
        "UPDATE repair01_w14_journey SET business_effect = '' WHERE id = $jRowId",
        "UPDATE repair01_w14_journey SET business_effect = '" . $esc($jEffect) . "' WHERE id = $jRowId"),

    array('W14-33', 'نزعُ صفِّ المساحةِ عن سطحٍ مُصيَّر',
        "UPDATE gov_space_appearances SET src_class = 'XX'
           WHERE route = '" . $esc($newRoute) . "' AND src_class = 'RPR-W14'",
        "UPDATE gov_space_appearances SET src_class = 'RPR-W14'
           WHERE route = '" . $esc($newRoute) . "' AND src_class = 'XX'"),

    array('W14-34', 'إصلاحٌ بمتطلَّبٍ كاشفٍ لا وجودَ له في النطاق',
        "UPDATE repair01_w14_fixes SET revealed_by = 'ZZZ-99' WHERE fix_key = '" . $esc($fixKey) . "'",
        "UPDATE repair01_w14_fixes SET revealed_by = '" . $esc($fixRev) . "'
           WHERE fix_key = '" . $esc($fixKey) . "'"),
);

foreach ($CASES as $c) {
    list($id, $what, $break, $restore) = $c;
    $broke = true;
    foreach ((array) $break as $q) { if (@$conn->query($q) !== true) { $broke = false; break; } }
    if (!$broke) {
        foreach ((array) $restore as $q) { @$conn->query($q); }
        echo "  ⚠ $id تعذَّر الكسر: " . $conn->error . "\n"; $blind[] = $id . ' (تعذّر الكسر)'; continue;
    }
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($id, $failed, true);
    foreach ((array) $restore as $q) { @$conn->query($q); }
    list($code2, $failed2) = run_gate($PHP, $GATE);
    $back = ($code2 === 0);
    if ($caught && $back) { $pass++; printf("  ✔ %-8s %s\n", $id, $what); }
    else {
        $blind[] = $id . ($caught ? ' (لم يرجع: ' . implode(',', $failed2) . ')' : ' (لم يسقط)');
        printf("  ✘ %-8s %s — %s\n", $id, $what, $caught ? 'الإرجاعُ فشل' : 'الحاجبُ أعمى');
    }
}

/* ══════════════════════════════════════════════════════════════════════════
   ② الحزامُ الثاني — قيدٌ يُعطَّل لحظةً ليُختبَر الحاجبُ خلفَه ثمَّ يُرجَع
   ══════════════════════════════════════════════════════════════════════════
   ⛔ **ولا يُحذَف قيدٌ ولا يُخفَّف** — والإرجاعُ يُتحقَّق منه بإعادةِ قياسِ
     وجودِ القيدِ في المخطَّطِ بعدَ كلِّ حالة.
   ══════════════════════════════════════════════════════════════════════════ */
echo "\n② الحزامُ الثاني — خلفَ قيدِ المخطَّط ─────────────────────────\n";
$beltOk = 0; $beltBad = array();
$BELT = array(
    array('W14-15', 'محفِّزُ الحدِّ على صيانةٍ مخطَّطة', 'rsk_trigger', 'chk_rtg_planned_excluded',
        "INSERT INTO rsk_trigger (company_id,trigger_no,rule_code,threshold_key,source_table,
           source_row_id,downtime_kind,state)
         VALUES ($company,'$NEG-TRG','UNPLANNED_24H','$thKey','equipments',1,'PLANNED_MAINTENANCE','raised')",
        "DELETE FROM rsk_trigger WHERE trigger_no = '$NEG-TRG'"),

    array('W14-16', 'الحوكمةُ تضع نتيجةَ مراجعة', 'iaf_findings', 'chk_iaf_result_dept',
        "UPDATE iaf_findings SET result_set_by_dept = 'DEP-08' WHERE id = $fndId",
        "UPDATE iaf_findings SET result_set_by_dept = '" . $esc($fndSetBy) . "' WHERE id = $fndId"),

    array('W14-17', 'تحقيقٌ تأديبيٌّ عند الحوكمة', 'gov_investigation', 'chk_gin_kind_owner',
        "INSERT INTO gov_investigation (company_id,inv_no,inv_kind,owner_dept,origin,state)
         VALUES ($company,'$NEG-INV','DISCIPLINARY','DEP-08','OPERATIONAL','mandated')",
        "DELETE FROM gov_investigation WHERE inv_no = '$NEG-INV'"),

    /* ⚠ **وكيانانِ متمايزانِ في الحمولة** — وإلّا سبقه `chk_grp_not_self` فيُقرأ
       «تعذّر الكسر» والحزامُ الثاني لم يُختبَر أصلًا. */
    array('W14-18', 'تعاملٌ بين كيانَين بلا الخماسيِّ الكامل', 'gov_related_party', 'chk_grp_intercompany',
        "INSERT INTO gov_related_party (company_id,party_no,party_name,intercompany_flag,
           from_legal_entity_id,to_legal_entity_id,state)
         VALUES ($company,'$NEG-RP','طرف الكسر',1,1,2,'declared')",
        "DELETE FROM gov_related_party WHERE party_no = '$NEG-RP'"),

    array('W14-31', 'عائلةُ خطرٍ خامسةٌ في الشجرة', 'rsk_taxonomy', 'chk_rtx_family',
        "UPDATE rsk_taxonomy SET family_code = 'SAFETY' WHERE node_code = '" . $esc($famNode) . "'",
        "UPDATE rsk_taxonomy SET family_code = '" . $esc($famCode) . "'
           WHERE node_code = '" . $esc($famNode) . "'"),
);
foreach ($BELT as $b) {
    list($id, $what, $tbl, $chk, $break, $restore) = $b;
    if (!($mig instanceof mysqli)) { $beltBad[] = $id . ' (لا مستخدمَ هجرات)'; continue; }
    $clause = null;
    $r = $mig->query("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
                       WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '"
                     . $mig->real_escape_string($chk) . "'");
    if ($r && $x = $r->fetch_row()) { $clause = $x[0]; }
    if ($clause === null) { $beltBad[] = $id . ' (‏القيد ' . $chk . ' غير موجود)'; continue; }

    if (@$mig->query("ALTER TABLE `$tbl` DROP CONSTRAINT `$chk`") !== true) {
        $beltBad[] = $id . ' (تعذّر تعطيل القيد)'; continue;
    }
    $broke = (@$conn->query($break) === true);
    $caught = false; $failed = array();
    if ($broke) { list($cx, $failed) = run_gate($PHP, $GATE); $caught = in_array($id, $failed, true); }
    @$conn->query($restore);
    $backChk = (@$mig->query("ALTER TABLE `$tbl` ADD CONSTRAINT `$chk` CHECK ($clause)") === true);
    $chkBack = false;
    $r2 = $mig->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '"
                      . $mig->real_escape_string($chk) . "'");
    if ($r2 && $x2 = $r2->fetch_row()) { $chkBack = ((int) $x2[0] > 0); }
    list($cy, $fy) = run_gate($PHP, $GATE);
    if ($broke && $caught && $backChk && $chkBack && $cy === 0) {
        $beltOk++; printf("  ✔ %-8s %s (خلفَ %s)\n", $id, $what, $chk);
    } else {
        $why = !$broke ? 'تعذّر الكسر' : (!$caught ? 'الحاجبُ أعمى' :
               (!$chkBack ? '**القيدُ لم يرجع**' : 'الأساسُ لم يرجع'));
        $beltBad[] = $id . ' (' . $why . ')';
        printf("  ✘ %-8s %s — %s\n", $id, $what, $why);
    }
}

/* ══════════════════════════════════════════════════════════════════════════
   ③ صنفُ `DB_REFUSED` — ما يمنعه المخطَّطُ يُحاوَل ويُشترط أن يُرَدّ
   ══════════════════════════════════════════════════════════════════════════ */
echo "\n③ ما يمنعه المخطَّطُ — يُحاوَل ويُشترط أن يُرَدّ ─────────────────\n";
$refused = 0; $slipped = array();
$REFUSE = array(
    array('انحرافٌ يملكه نطاقُ رقابة',
        "INSERT INTO ctl_deviation (company_id,deviation_no,owner_dept,source_table,source_row_id)
         VALUES ($company,'$NEG-R1','DEP-09','equipments',1)",
        "DELETE FROM ctl_deviation WHERE deviation_no = '$NEG-R1'"),
    array('تصنيفٌ بلا قاعدةٍ مكتوبة',
        "INSERT INTO ctl_deviation (company_id,deviation_no,owner_dept,source_table,source_row_id,
           classification,classified_by)
         VALUES ($company,'$NEG-R2','DEP-04','equipments',1,'RISK_EXPOSURE',1)",
        "DELETE FROM ctl_deviation WHERE deviation_no = '$NEG-R2'"),
    array('انحرافٌ مصنَّفٌ انحرافًا فقط يحمل مرجعَ حوكمة',
        "INSERT INTO ctl_deviation (company_id,deviation_no,owner_dept,source_table,source_row_id,
           classification,rule_code,classified_by,governance_ref)
         VALUES ($company,'$NEG-R3','DEP-04','equipments',1,'DEVIATION_ONLY','CTLR-DOWNTIME',1,'X')",
        "DELETE FROM ctl_deviation WHERE deviation_no = '$NEG-R3'"),
    array('حالةُ حوكمةٍ بأساسٍ خارجَ الثمانية',
        "INSERT INTO gov_breach (company_id,case_no,opened_basis,control_ref,severity)
         VALUES ($company,'$NEG-R4','OPERATIONAL_DEVIATION','C','low')",
        "DELETE FROM gov_breach WHERE case_no = '$NEG-R4'"),
    array('حالةُ حوكمةٍ بلا ضابطٍ ولا سياسةٍ ولا التزام',
        "INSERT INTO gov_breach (company_id,case_no,opened_basis,severity)
         VALUES ($company,'$NEG-R5','CONTROL_BROKEN','low')",
        "DELETE FROM gov_breach WHERE case_no = '$NEG-R5'"),
    array('تحقيقٌ مستقلٌّ للمراجعةِ بلا تكليفٍ مكتوب',
        "INSERT INTO gov_investigation (company_id,inv_no,inv_kind,owner_dept,origin)
         VALUES ($company,'$NEG-R6','SPECIAL_INDEPENDENT','IAF','OWNER_ORDER')",
        "DELETE FROM gov_investigation WHERE inv_no = '$NEG-R6'"),
    array('تحقيقٌ من سجلِّ المنعِ بلا فرز',
        "INSERT INTO gov_investigation (company_id,inv_no,inv_kind,owner_dept,origin)
         VALUES ($company,'$NEG-R7','INTEGRITY','DEP-08','DENIAL')",
        "DELETE FROM gov_investigation WHERE inv_no = '$NEG-R7'"),
    array('تعارضٌ مُعلَنٌ بلا تنحٍّ وسلطةٍ محجوزة',
        "INSERT INTO gov_investigation (company_id,inv_no,inv_kind,owner_dept,origin,conflict_flag)
         VALUES ($company,'$NEG-R8','INTEGRITY','DEP-08','BREACH',1)",
        "DELETE FROM gov_investigation WHERE inv_no = '$NEG-R8'"),
    array('حدثُ خطرٍ بلا مرجعِ مصدر',
        "INSERT INTO rsk_event (company_id,event_no,family_code,event_kind,source_module,
           source_table,source_row_id)
         VALUES ($company,'$NEG-R9','OPERATIONAL','event','mnt','',0)",
        "DELETE FROM rsk_event WHERE event_no = '$NEG-R9'"),
    /* ⚠ **والتحديثُ على مقامٍ خالٍ ينجح بصفرِ صفوفٍ فيُقرأ «القيدُ غيرُ نافذ»**
       (‏عينُ ما رُدَّ في الهجرةِ §⑥): سجلُّ البرامجِ يخلو بعد كنسِ الرحلة،
       فالمحاولةُ **إدراجٌ** يقع حتمًا لا تحديثٌ قد لا يمسَّ صفًّا. */
    array('نطاقُ برنامجِ مراجعةٍ تحدّده الحوكمة',
        "INSERT INTO iaf_program (company_id,program_no,objective_ar,test_method,scope_set_by_dept)
         VALUES ($company,'$NEG-R10','هدف الكسر','inspection','DEP-08')",
        "DELETE FROM iaf_program WHERE program_no = '$NEG-R10'"),
    array('عتبةٌ معلَّقةٌ بقيمةٍ مخترَعة',
        "UPDATE repair01_w14_thresholds SET value_num = 1 WHERE status = 'CONFIG_PENDING'
           AND threshold_key = 'rsk.appetite.limit_amount'",
        "UPDATE repair01_w14_thresholds SET value_num = NULL
           WHERE threshold_key = 'rsk.appetite.limit_amount'"),
    array('قيمةُ اختبارٍ على عتبةٍ معتمَدة',
        "UPDATE repair01_w14_thresholds SET test_value_num = 1 WHERE threshold_key = '$thKey'",
        "UPDATE repair01_w14_thresholds SET test_value_num = NULL WHERE threshold_key = '$thKey'"),
    array('سجلُّ أنواعِ الطلباتِ تملك تعريفَه الحوكمة',
        "INSERT INTO gov_request_type (company_id,type_code,name_ar,definition_owner_dept)
         VALUES ($company,'$NEG-R13','نوع','DEP-08')",
        "DELETE FROM gov_request_type WHERE type_code = '$NEG-R13'"),
);
foreach ($REFUSE as $rf) {
    list($what, $try, $undo) = $rf;
    $ok = @$conn->query($try);
    if ($ok === true) {
        $slipped[] = $what;
        printf("  ✘ %-52s **مرَّ** والقيدُ غيرُ نافذ\n", $what);
        @$conn->query($undo);
    } else {
        $refused++;
        printf("  ✔ %-52s رُدَّ في القاعدة\n", $what);
    }
}

/* ══════════════════════════════════════════════════════════════════════════
   ④ ما تمنعه بنيةُ ملفّ — يُشوَّه ثمَّ يُرجَع بايتًا ببايت
   ══════════════════════════════════════════════════════════════════════════ */
echo "\n④ ما تمنعه بنيةُ ملفّ — تشويهٌ ثمَّ إرجاعٌ متحقَّقٌ منه ────────────\n";
$fileOk = 0; $fileBad = array();
$FILES = array(
    array('W14-11', 'حقنُ بندِ قائمةٍ يدويٍّ في صفحةِ سطحٍ من الموجة', $SCR, $scrOrig,
        str_replace('$rows = w14_rows', "\$__manual = array('nav_items' => 1);\n\$rows = w14_rows", $scrOrig)),

    array('W14-13', 'كتابةُ خدمةِ الحوكمةِ في جدولِ نطاقِ المخاطر', $SVC_G, $svcGOrig,
        str_replace("public static function registerCommittee(TenantDb \$gate, array \$row)",
            "public static function crossWrite(TenantDb \$gate)\n    {\n"
            . "        return \$gate->insert('risk_register', array());\n    }\n\n"
            . "    public static function registerCommittee(TenantDb \$gate, array \$row)", $svcGOrig)),

    /* ⚠ **ونزعُ رمزِ الردِّ من الخدمةِ يُسقط حاجبَ فصلِ الواجباتِ لا حاجبَ
         الاستقلال**: `W14-16` يقيس **صفوفًا** في السجلّ، و`W14-21` يقيس أنَّ
         **رمزَ الردِّ منفَّذٌ في شيفرةِ خدمةٍ لا مُعلَنٌ في جدول**. فالحمولةُ
         تُوجَّه إلى الحاجبِ الذي تعنيه — وحمولةٌ في غيرِ موضعِها تُقرأ عمًى
         وهي خطأُ الفاحصِ لا خطأُ الحاجب. */
    array('W14-21', 'نزعُ رمزِ ردِّ فصلِ الواجباتِ من شيفرةِ خدمةِ المراجعة', $SVC_A, $svcAOrig,
        str_replace("'AUDIT_RESULT_CLOSED_OUTSIDE_IAF'", "'AUDIT_RESULT_CLOSED_ELSEWHERE'", $svcAOrig)),

    array('W14-28', 'تشكيلٌ في نصٍّ مُصيَّرٍ من أسطحِ الموجة', $SCR, $scrOrig,
        str_replace('الخطورة', 'الخُطورة', $scrOrig)),

    array('W14-30', 'استعلامٌ خامٌّ في سطحٍ من أسطحِ الموجة', $SCR, $scrOrig,
        str_replace('$rows = w14_rows', "\$__raw = \$conn->query('SELECT 1');\n\$rows = w14_rows", $scrOrig)),

    array('W14-06', 'نزعُ الحارسِ من سطحٍ من أسطحِ الموجة', $SCR, $scrOrig,
        str_replace(array('permissions_helper.php', 'session_bootstrap.php', 'w14_perms'),
                    array('permissions_helper_x.php', 'session_bootstrap_x.php', 'w14_perms_x'), $scrOrig)),
);
foreach ($FILES as $f) {
    list($id, $what, $path, $orig, $broken) = $f;
    if ($broken === $orig) {
        $fileBad[] = $id . ' (لم يتغيّر الملفّ)'; printf("  ✘ %-8s %s — الحمولةُ لم تغيّر شيئًا\n", $id, $what);
        continue;
    }
    file_put_contents($path, $broken);
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($id, $failed, true);
    file_put_contents($path, $orig);
    $same = ((string) file_get_contents($path) === $orig);
    list($code2, $failed2) = run_gate($PHP, $GATE);
    if ($caught && $same && $code2 === 0) { $fileOk++; printf("  ✔ %-8s %s\n", $id, $what); }
    else {
        $fileBad[] = $id . ($caught ? ' (لم يرجع)' : ' (لم يسقط)');
        printf("  ✘ %-8s %s — %s\n", $id, $what,
               $caught ? 'الإرجاعُ فشل' : 'الحاجبُ أعمى ⇐ ' . implode(',', $failed));
    }
}

/* ══════════════════════════════════════════════════════════════════════════
   ⑤ الحكمُ — والأساسُ يُعاد قياسُه بعد كلِّ شيء
   ══════════════════════════════════════════════════════════════════════════ */
$sw();
list($cf, $ff) = run_gate($PHP, $GATE);
echo "\n────────────────────────────────────────────────────────────\n";
printf("حواجبُ يقظة: %d من %d · حزامٌ ثانٍ %d من %d · حالاتُ ملفٍّ %d من %d · قيودُ مخطَّطٍ رُدَّت %d من %d\n",
    $pass, count($CASES), $beltOk, count($BELT), $fileOk, count($FILES), $refused, count($REFUSE));
printf("أعمى: %d%s\n", count($blind) + count($fileBad) + count($beltBad),
    ($blind || $fileBad || $beltBad)
        ? ' ⇐ ' . implode('، ', array_merge($blind, $beltBad, $fileBad)) : '');
printf("قيدٌ متسرِّب: %d%s\n", count($slipped), $slipped ? ' ⇐ ' . implode('، ', $slipped) : '');
printf("الأساسُ بعد الفحص: %s\n", $cf === 0 ? 'خضراء ✔' : 'ساقطة ✘ (' . implode(',', $ff) . ')');
$ok = (count($blind) === 0 && count($fileBad) === 0 && count($beltBad) === 0
       && count($slipped) === 0 && $cf === 0);
echo $ok ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: حاجبٌ أعمى أو قيدٌ متسرِّب ✘\n";
exit($ok ? 0 : 1);
