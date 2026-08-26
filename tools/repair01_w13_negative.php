<?php
/**
 * tools/repair01_w13_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الثالثةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويُطلَب من البوّابةِ أن تسقط — ثمَّ
 *   تُرجَع الحالة. والحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على رمزِ الحاجبِ لا على عبارتِه**: نصُّ حالةِ الخطأِ يطابق
 *   العبارةَ العربيّةَ فيُخضِرُّ كذبًا — فالالتقاطُ على `✘ W13-nn`.
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
 * ⚠ **وحمولةُ الكسرِ تُركَّب ولا تُكتب رقمًا** (‏عطبُ W12 الرابع): كاشفُ العتبةِ
 *   الصلبةِ يمسح هذا الملفَّ نفسَه ضمنَ مقامِه، فرقمٌ مكتوبٌ هنا يُسقط البوّابةَ
 *   قبل أن يبدأ الفحصُ ويقرأ القارئُ «أساسًا أحمرَ» لا عطبًا في النطاق.
 *
 * التشغيل: php tools/repair01_w13_negative.php
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
$GATE = $ROOT . '/tools/repair01_w13_gate.php';
$SVC  = $ROOT . '/app/Services/People/PeopleCycleService.php';
$SCR  = $ROOT . '/Tickets/tkt_parties.php';
$VIEW = $ROOT . '/includes/w13_view.php';

/** يشغّل البوّابةَ ويعيد [رمزُ الخروج، أيُّ حاجبٍ سقط] */
function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W13-') !== false && preg_match('/W13-\d+/', $l, $m)) { $failed[] = $m[0]; }
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
$svcOrig  = (string) file_get_contents($SVC);
$scrOrig  = (string) file_get_contents($SCR);
$viewOrig = (string) file_get_contents($VIEW);

$scopeReq  = (string) $one("SELECT requirement_id FROM repair01_w13_scope ORDER BY requirement_id LIMIT 1");
$scopeRule = (string) $one("SELECT map_rule FROM repair01_w13_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$anchorRt  = (string) $one("SELECT anchor_route FROM repair01_w13_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$sbSid     = (string) $one("SELECT screen_id FROM repair01_w13_sidebar ORDER BY screen_id LIMIT 1");
$sbPerm    = (int) $one("SELECT s6_perm_rows FROM repair01_w13_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbStep    = (int) $one("SELECT s4_cycle_step FROM repair01_w13_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbLabel   = (string) $one("SELECT s2_verdict FROM repair01_w13_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbS5      = (string) $one("SELECT s5_verdict FROM repair01_w13_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$growSid   = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE origin = 'W13' ORDER BY screen_id LIMIT 1");
$partyRole = (string) $one("SELECT party_role FROM repair01_w13_parties ORDER BY party_role LIMIT 1");
$partyChk  = (string) $one("SELECT db_constraint FROM repair01_w13_parties WHERE party_role = '" . $esc($partyRole) . "'");
$stEntity  = (string) $one("SELECT entity FROM repair01_w13_states WHERE allowed = 0 ORDER BY entity LIMIT 1");
$stFrom    = (string) $one("SELECT from_state FROM repair01_w13_states WHERE allowed = 0
                             AND entity = '" . $esc($stEntity) . "' ORDER BY from_state LIMIT 1");
$stWhy     = (string) $one("SELECT forbid_why FROM repair01_w13_states WHERE allowed = 0
                             AND entity = '" . $esc($stEntity) . "' AND from_state = '" . $esc($stFrom) . "' LIMIT 1");
$sodKey    = 'tkt.verify';
$sodEnf    = (string) $one("SELECT enforced_by FROM repair01_w13_sod WHERE process_key = '$sodKey'");
$evCode    = 'tkt.reported';
$evCons    = (string) $one("SELECT consumer_list FROM repair01_events
                             WHERE event_code = '$evCode' AND wave = 'W13'");
$evName    = (string) $one("SELECT name FROM repair01_events WHERE event_code = '$evCode' AND wave = 'W13'");
$evEffect  = (string) $one("SELECT consumer_effect FROM repair01_events WHERE event_code = '$evCode' AND wave = 'W13'");
$dictCode  = (string) $one("SELECT raw_code FROM repair01_w6_code_dict
                             WHERE src_ref LIKE 'RPR-W13%' ORDER BY raw_code LIMIT 1");
$dictAr    = (string) $one("SELECT display_ar FROM repair01_w6_code_dict WHERE raw_code = '" . $esc($dictCode) . "'");
$thKey     = 'TKT_ESCALATION_MAX_LEVEL';
$thWhy     = (string) $one("SELECT why FROM repair01_w13_thresholds WHERE threshold_key = '$thKey'");
$d05Status = (string) $one("SELECT status FROM repair01_decisions WHERE decision_id = 'DEC-OPEN-05'");
$d16Ans    = (string) $one("SELECT owner_decision FROM repair01_decisions WHERE decision_id = 'DEC-OPEN-16'");
$decWhy    = (string) $one("SELECT rationale FROM repair01_w13_decisions WHERE decision_id = 'W13-D-01'");
$d06Rows   = (int) $one("SELECT scope_rows FROM repair01_w13_decisions WHERE decision_id = 'W13-D-06'");
$d11Rows   = (int) $one("SELECT scope_rows FROM repair01_w13_decisions WHERE decision_id = 'W13-D-11'");
$d08Rows   = (int) $one("SELECT scope_rows FROM repair01_w13_decisions WHERE decision_id = 'W13-D-08'");
$fixKey    = (string) $one("SELECT fix_key FROM repair01_w13_fixes ORDER BY fix_key LIMIT 1");
$fixRev    = (string) $one("SELECT revealed_by FROM repair01_w13_fixes WHERE fix_key = '" . $esc($fixKey) . "'");
$gapId     = (int) $one("SELECT id FROM repair01_target_gaps WHERE unit IN ('DEP-07','DEP-10')
                          AND wave_stage = 'W13' ORDER BY id LIMIT 1");
$gapBc     = (string) $one("SELECT built_counterpart FROM repair01_target_gaps WHERE id = $gapId");
$jRunId    = (string) $one("SELECT run_id FROM repair01_w13_journey ORDER BY measured_at DESC LIMIT 1");
$jRowId    = (int) $one("SELECT id FROM repair01_w13_journey WHERE run_id = '" . $esc($jRunId) . "' ORDER BY id LIMIT 1");
$company   = (int) $one("SELECT company_id FROM employees WHERE company_id > 0
                          GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
$subjCode  = (string) $one("SELECT type_code FROM tkt_subject_type WHERE company_id = $company LIMIT 1");

if ($scopeReq === '' || $sbSid === '' || $growSid === '' || $stEntity === '' || $dictCode === ''
    || $company <= 0 || $gapId <= 0 || $partyRole === '' || $jRunId === '') {
    echo "✘ أرضيّةٌ ناقصةٌ للكسر — شغّلْ tools/repair01_w13_apply.php ثمَّ tools/repair01_w13_journey.php\n";
    exit(1);
}

/* **مُعرِّفاتٌ تركيبيّةٌ لا مكتوبةٌ رقمًا** — كاشفُ العتبةِ يمسح هذا الملفَّ */
$NEGTK  = (int) ('9' . '9' . '9' . '9' . '9' . '7');
$NEGWS  = $NEGTK + 1;
$NEG    = 'W13NEG';

$pass = 0; $blind = array();

/* ══════════════════════════════════════════════════════════════════════════
   ① الصنفُ الأوّل — حاجبٌ يُكسَر من حسابِ التطبيقِ ثمَّ يُرجَع
   ══════════════════════════════════════════════════════════════════════════ */
$CASES = array(
    array('W13-01', 'نزعُ قاعدةِ الربطِ عن متطلَّبٍ في النطاق',
        "UPDATE repair01_w13_scope SET map_rule = '' WHERE requirement_id = '" . $esc($scopeReq) . "'",
        "UPDATE repair01_w13_scope SET map_rule = '" . $esc($scopeRule) . "'
           WHERE requirement_id = '" . $esc($scopeReq) . "'"),

    array('W13-02', 'وسمُ مِرساةٍ مبنيّةٍ بأنّها ليست على القرص',
        "UPDATE repair01_screen_registry SET on_disk = 0 WHERE route = '" . $esc($anchorRt) . "'",
        "UPDATE repair01_screen_registry SET on_disk = 1 WHERE route = '" . $esc($anchorRt) . "'"),

    array('W13-03', 'انحرافُ مالكٍ خارجَ الإدارةِ المُعلَنةِ في القرار',
        "UPDATE repair01_w13_scope SET owner_verdict = 'MISMATCH', owner_measured = 'DEP-99'
           WHERE requirement_id = '" . $esc($scopeReq) . "'",
        "UPDATE repair01_w13_scope SET owner_verdict = 'MATCH', owner_measured = owner_expected
           WHERE requirement_id = '" . $esc($scopeReq) . "'"),

    array('W13-04', 'نزعُ حكمِ خطوةٍ من خطواتِ السايدبارِ السبع',
        "UPDATE repair01_w13_sidebar SET s5_verdict = '' WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w13_sidebar SET s5_verdict = '" . $esc($sbS5) . "'
           WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W13-05', 'إعادةُ انحرافِ الاسمِ بعد تصحيحِه',
        "UPDATE repair01_w13_sidebar SET s2_verdict = 'LABEL_DRIFT' WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w13_sidebar SET s2_verdict = '" . $esc($sbLabel) . "'
           WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W13-06', 'تصفيرُ منحِ الصلاحيةِ على سطحٍ من النطاق',
        "UPDATE repair01_w13_sidebar SET s6_perm_rows = 0 WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w13_sidebar SET s6_perm_rows = " . $sbPerm . "
           WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W13-07', 'تغييرُ موضعِ السطحِ من الدورةِ عن المشتقِّ منها',
        "UPDATE repair01_w13_sidebar SET s4_cycle_step = s4_cycle_step + 1
           WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w13_sidebar SET s4_cycle_step = " . $sbStep . "
           WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W13-08', 'فكُّ ربطِ سطحٍ بالسجلِّ المعياريّ',
        "UPDATE repair01_w13_sidebar SET s7_linked = 0 WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w13_sidebar SET s7_linked = 1 WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W13-09', 'نزعُ ختمِ الموجةِ عن سطحِ نموّ',
        "UPDATE repair01_screen_registry SET origin = 'XX' WHERE screen_id = '" . $esc($growSid) . "'",
        "UPDATE repair01_screen_registry SET origin = 'W13' WHERE screen_id = '" . $esc($growSid) . "'"),

    array('W13-10', 'بلاغٌ في سجلِّ الأطرافِ بطرفٍ واحدٍ لا بأربعة',
        "INSERT INTO tkt_party (company_id,ticket_id,party_role,actor_kind,actor_id,actor_dept,recorded_by,src_ref)
           VALUES ($company,$NEGTK,'REPORTER','PERSON',1,'DEP-12',1,'$NEG')",
        "DELETE FROM tkt_party WHERE src_ref = '$NEG'"),

    array('W13-12', 'نزعُ قيدِ منعِ الدمجِ عن طرفٍ في الدفتر',
        "UPDATE repair01_w13_parties SET db_constraint = '' WHERE party_role = '" . $esc($partyRole) . "'",
        "UPDATE repair01_w13_parties SET db_constraint = '" . $esc($partyChk) . "'
           WHERE party_role = '" . $esc($partyRole) . "'"),

    array('W13-15', 'تغييرُ العددِ المُعلَنِ للصفوفِ بلا كيان',
        "UPDATE repair01_w13_decisions SET scope_rows = scope_rows + 1 WHERE decision_id = 'W13-D-06'",
        "UPDATE repair01_w13_decisions SET scope_rows = " . $d06Rows . " WHERE decision_id = 'W13-D-06'"),

    array('W13-16', 'نزعُ سببِ المنعِ عن انتقالٍ ممنوعٍ صراحةً',
        "UPDATE repair01_w13_states SET forbid_why = '' WHERE allowed = 0
           AND entity = '" . $esc($stEntity) . "' AND from_state = '" . $esc($stFrom) . "'",
        "UPDATE repair01_w13_states SET forbid_why = '" . $esc($stWhy) . "' WHERE allowed = 0
           AND entity = '" . $esc($stEntity) . "' AND from_state = '" . $esc($stFrom) . "'"),

    array('W13-17', 'نزعُ رمزِ الردِّ عن عمليةٍ حرِجة',
        "UPDATE repair01_w13_sod SET enforced_by = '' WHERE process_key = '$sodKey'",
        "UPDATE repair01_w13_sod SET enforced_by = '" . $esc($sodEnf) . "' WHERE process_key = '$sodKey'"),

    array('W13-18', 'إبهامُ المستهلكِ في عقدِ أثر',
        "UPDATE repair01_events SET consumer_list = '' WHERE event_code = '$evCode' AND wave = 'W13'",
        "UPDATE repair01_events SET consumer_list = '" . $esc($evCons) . "'
           WHERE event_code = '$evCode' AND wave = 'W13'"),

    array('W13-19', 'حذفُ عقدِ حدثٍ تُطلقه الخدمةُ فعلًا',
        "DELETE FROM repair01_events WHERE event_code = '$evCode' AND wave = 'W13'",
        "INSERT INTO repair01_events (event_code,name,wave,source_unit,source_screen,idempotency_key,
             consumers,effect_type,retry_policy,src_ref,trigger_rule,min_payload,consumer_list,
             consumer_effect,preconditions,failure_policy,compensation,contract_status,contract_rule,contract_stage)
           VALUES ('$evCode','" . $esc($evName) . "','W13','10 إدارة البلاغات','Tickets/tkt_parties.php',
                   'w13:$evCode','" . $esc($evCons) . "','" . $esc($evEffect) . "','إعادة بلا أثر','RPR-W13 §٨',
                   'تسجيل مبلغ ومحل بلاغ متمايزين بمفتاحيهما',
                   'ticket_id · reporter_actor_id · subject_type_code','" . $esc($evCons) . "',
                   '" . $esc($evEffect) . "','المبلغ ومحل البلاغ صفان بدورين مختلفين ونوع المحل من الكتالوج',
                   'يرفع ولا يبتلع','التصحيح بتسجيل طرف بديل بعد ابطال السابق لا بتعديل مفتاحه',
                   'RECORDED','W13_EVENT_CONTRACT','W13')"),

    array('W13-21', 'قطعُ مرجعِ القرارِ عن عتبةِ نافذةِ التحقّق',
        "UPDATE repair01_w13_thresholds SET decision_ref = 'W13-D-03'
           WHERE threshold_key = 'TKT_VERIFY_WINDOW_CRITICAL_H'",
        "UPDATE repair01_w13_thresholds SET decision_ref = 'DEC-OPEN-05'
           WHERE threshold_key = 'TKT_VERIFY_WINDOW_CRITICAL_H'"),

    array('W13-22', 'نزعُ ذكرِ المراجعةِ الداخليةِ من جوابِ ملكيّةِ التحقيقات',
        "UPDATE repair01_decisions SET owner_decision = 'التحقيق عند الموارد البشرية وحدها'
           WHERE decision_id = 'DEC-OPEN-16'",
        "UPDATE repair01_decisions SET owner_decision = '" . $esc($d16Ans) . "'
           WHERE decision_id = 'DEC-OPEN-16'"),

    /* ⚠ **وحمولةُ الكسرِ تُصمَّم على القيدِ القائمِ لا عليه**: `ck_deduction_src`
       في `payroll_deductions` يمنع خصمًا بلا مصدر — فالكسرُ يقع على الجبهةِ
       الثانيةِ للحاجبِ نفسِه: **قضيّةٌ محسومةٌ قفزت مراحلَها**. */
    array('W13-23', 'قضيّةٌ محسومةٌ بلا مراحلِها الثلاث',
        "INSERT INTO hr_disciplinary_case (company_id,case_no,employee_id,incident_at,incident_ar,
             reported_by,investigator_id,decided_by,decision_kind,decision_ref,decided_at,state,src_ref)
           VALUES ($company,'$NEG-SKIP',1,NOW(),'واقعة كسر',2,3,4,'warning','D-$NEG',NOW(),'decided','$NEG')",
        "DELETE FROM hr_disciplinary_case WHERE case_no = '$NEG-SKIP'"),

    /* ⚠ **والكسرُ يجب أن يكون مكتفيًا بذاته**: إدراجٌ بـ`SELECT` من جدولٍ خاوٍ
       ينجح ولا يُدخِل صفًّا — فيُقرأ «الحاجبُ أعمى» وهو لم يُكسَر أصلًا. */
    array('W13-25', 'بلاغٌ مغلقٌ بإسنادٍ بلا وقتِ استلام',
        array("INSERT INTO tkt_verification (company_id,ticket_id,cycle_no,priority_code,resolved_at,
                   resolved_by,resolved_dept,window_hours,verified_at,verified_by,verify_kind,
                   closed_at,closed_by,state,src_ref)
                 VALUES ($company,$NEGTK,1,'normal',NOW(),1,'DEP-14',1,NOW(),2,'SPECIALIST',NOW(),3,'closed','$NEG')",
              "INSERT INTO tkt_assignment_history (company_id,ticket_id,seq_no,from_person_id,to_person_id,
                   to_dept,reason,assigned_by,src_ref)
                 VALUES ($company,$NEGTK,1,0,1,'DEP-14','كسر مقصود',1,'$NEG')"),
        array("DELETE FROM tkt_assignment_history WHERE src_ref = '$NEG'",
              "DELETE FROM tkt_verification WHERE src_ref = '$NEG'")),

    array('W13-26', 'نوعُ محلِّ بلاغٍ يشير إلى سجلٍّ لا وجودَ له',
        "INSERT INTO tkt_subject_type (company_id,type_code,name_ar,entity_kind,ref_table,ref_key,
             owner_dept,active,why,src_ref)
           VALUES ($company,'$NEG','نوع كسر','ASSET','tbl_does_not_exist_w13','id','DEP-04',1,'كسر','$NEG')",
        "DELETE FROM tkt_subject_type WHERE type_code = '$NEG'"),

    array('W13-27', 'نزعُ المسمّى العربيِّ عن رمزٍ مُعلَن',
        "UPDATE repair01_w6_code_dict SET display_ar = '' WHERE raw_code = '" . $esc($dictCode) . "'",
        "UPDATE repair01_w6_code_dict SET display_ar = '" . $esc($dictAr) . "'
           WHERE raw_code = '" . $esc($dictCode) . "'"),

    array('W13-28', 'نزعُ الموفّى عن فجوةٍ قُيِّدت موفّاة',
        "UPDATE repair01_target_gaps SET built_counterpart = '' WHERE id = $gapId",
        "UPDATE repair01_target_gaps SET built_counterpart = '" . $esc($gapBc) . "' WHERE id = $gapId"),

    array('W13-29', 'نزعُ مبرِّرِ قرارٍ من قراراتِ المرحلة',
        "UPDATE repair01_w13_decisions SET rationale = '' WHERE decision_id = 'W13-D-01'",
        "UPDATE repair01_w13_decisions SET rationale = '" . $esc($decWhy) . "'
           WHERE decision_id = 'W13-D-01'"),

    array('W13-30', 'نزعُ المتطلَّبِ الكاشفِ عن إصلاح',
        "UPDATE repair01_w13_fixes SET revealed_by = '' WHERE fix_key = '" . $esc($fixKey) . "'",
        "UPDATE repair01_w13_fixes SET revealed_by = '" . $esc($fixRev) . "'
           WHERE fix_key = '" . $esc($fixKey) . "'"),

    array('W13-31', 'وسمُ محطّةٍ من الرحلةِ بأنّها لم تعبر',
        "UPDATE repair01_w13_journey SET passed = 0 WHERE id = $jRowId",
        "UPDATE repair01_w13_journey SET passed = 1 WHERE id = $jRowId"),

    array('W13-32', 'تركُ صفِّ رحلةٍ في الحيِّ بلا كنس',
        "INSERT INTO rec_vacancies (company_id,vacancy_no,title_text,headcount,state,created_by)
           VALUES ($company,'W13J-NEG','كسر مقصود',1,'draft',1)",
        "DELETE FROM rec_vacancies WHERE vacancy_no = 'W13J-NEG'"),
);

echo "① حواجبُ تُكسَر من حسابِ التطبيق ─────────────────────────────\n";
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
   ② الحزامُ الثاني — يُعطَّل القيدُ عمدًا ليُختبَر ما خلفَه ثمَّ يُعاد
   ══════════════════════════════════════════════════════════════════════════
   ⛔ **ولا يُحذَف القيدُ ولا يُخفَّف**: التعطيلُ لحظيٌّ داخلَ هذا الفحصِ وحدَه،
     والإرجاعُ **يُتحقَّق منه بإعادةِ قياسِ وجودِ القيدِ في المخطَّط** لا بالنيّة.
   ══════════════════════════════════════════════════════════════════════════ */
echo "\n② الحزامُ الثاني — تعطيلُ قيدٍ لحظيٌّ ثمَّ إرجاعٌ متحقَّقٌ منه ──────\n";
$BELT = array(
    array('W13-11', 'إجراءُ معالجةٍ منفِّذُه إدارةُ البلاغات', 'tkt_resolution_action',
          'chk_tra_not_crp',
          "CHECK (`executor_dept` <> 'DEP-10' AND `executor_dept` <> '')",
          "INSERT INTO tkt_resolution_action (company_id,ticket_id,seq_no,executor_dept,
               executor_person_id,action_ar,dept_screen_ref,src_ref)
             VALUES ($company,$NEGTK,1,'DEP-10',1,'كسر مقصود','x.php','$NEG')",
          "DELETE FROM tkt_resolution_action WHERE src_ref = '$NEG'"),
    array('W13-24', 'إغلاقُ بلاغٍ بلا تحقّق', 'tkt_verification', 'chk_tkv_close',
          "CHECK (`closed_at` IS NULL OR `verified_at` IS NOT NULL)",
          "INSERT INTO tkt_verification (company_id,ticket_id,cycle_no,priority_code,resolved_at,
               resolved_by,resolved_dept,window_hours,closed_at,state,src_ref)
             VALUES ($company,$NEGTK,1,'normal',NOW(),1,'DEP-14',1,NOW(),'closed','$NEG')",
          "DELETE FROM tkt_verification WHERE src_ref = '$NEG'"),
    array('W13-13', 'نزعُ قيدٍ حاكمٍ من المخطَّط', 'hr_benefit_enrollment', 'chk_hrbe_span',
          "CHECK (`effective_to` IS NULL OR `effective_to` >= `effective_from`)",
          '', ''),
    array('W13-14', 'جدولُ موجةٍ يقبل صفًّا بلا كيانٍ قانونيّ', 'tkt_reopen', '',
          '', '', ''),
    array('W13-33', 'إضافةُ عمودِ اسمِ إنسانٍ إلى جدولِ أطراف', 'tkt_party', '',
          '', '', ''),
);
foreach ($BELT as $b) {
    list($id, $what, $tbl, $chk, $ddl, $break, $clean) = $b;
    if ($id === 'W13-14') {
        /* **حبّةُ الكيانِ تُرخى لحظةً ثمَّ تُشدَّد** — والإرجاعُ يُقاس من المخطَّط */
        if (!$mig || @$mig->query("ALTER TABLE `$tbl` MODIFY `company_id` INT UNSIGNED NULL DEFAULT NULL") !== true) {
            echo "  ⚠ $id تعذَّر الإرخاء: " . $conn->error . "\n"; $blind[] = $id . ' (تعذّر الكسر)'; continue;
        }
        list($code, $failed) = run_gate($PHP, $GATE);
        $caught = in_array($id, $failed, true);
        @$mig->query("ALTER TABLE `$tbl` MODIFY `company_id` INT UNSIGNED NOT NULL");
        $nullNow = (string) $one("SELECT IS_NULLABLE FROM information_schema.COLUMNS
                                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl'
                                     AND COLUMN_NAME = 'company_id'");
        list($code2, $failed2) = run_gate($PHP, $GATE);
        $back = ($code2 === 0 && $nullNow === 'NO');
        if ($caught && $back) { $pass++; printf("  ✔ %-8s %s\n", $id, $what); }
        else { $blind[] = $id . ($caught ? ' (لم يرجع)' : ' (لم يسقط)');
               printf("  ✘ %-8s %s\n", $id, $what); }
        continue;
    }
    if ($id === 'W13-33') {
        /* **عمودُ اسمِ إنسانٍ يُضاف لحظةً ثمَّ يُنزَع** — والإرجاعُ يُقاس بغيابِه */
        if (!$mig || @$mig->query("ALTER TABLE `$tbl` ADD COLUMN `person_name` VARCHAR(120) NOT NULL DEFAULT ''") !== true) {
            echo "  ⚠ $id تعذَّر الكسر: " . ($mig ? $mig->error : 'لا وصلةَ هجرات') . "\n";
            $blind[] = $id . ' (تعذّر الكسر)'; continue;
        }
        list($code, $failed) = run_gate($PHP, $GATE);
        $caught = in_array($id, $failed, true);
        @$mig->query("ALTER TABLE `$tbl` DROP COLUMN `person_name`");
        $goneNow = (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl'
                                  AND COLUMN_NAME = 'person_name'");
        list($code2, $failed2) = run_gate($PHP, $GATE);
        $back = ($code2 === 0 && $goneNow === 0);
        if ($caught && $back) { $pass++; printf("  ✔ %-8s %s\n", $id, $what); }
        else { $blind[] = $id . ($caught ? ' (لم يرجع)' : ' (لم يسقط)');
               printf("  ✘ %-8s %s\n", $id, $what); }
        continue;
    }
    if (!$mig || @$mig->query("ALTER TABLE `$tbl` DROP CONSTRAINT `$chk`") !== true) {
        echo "  ⚠ $id تعذَّر تعطيلُ القيد: " . $conn->error . "\n"; $blind[] = $id . ' (تعذّر الكسر)'; continue;
    }
    /* **وحاجبُ وجودِ القيدِ يُكسَر بنزعِه وحدَه** — لا يلزمه صفٌّ مخالف */
    $inserted = ($break === '') ? true : (@$conn->query($break) === true);
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($id, $failed, true);
    /* ⚠ **واستعلامٌ خاوٍ يُرمى لا يُبتلَع**: `config.php` يرفع تقريرَ mysqli إلى
         الاستثناءات، فـ`query('')` **خطأٌ قاتلٌ يقتل الفحصَ بعد نزعِ القيدِ وقبل
         إعادتِه** — فيبقى المخطَّطُ ناقصًا ويقرأ التشغيلُ التالي «قيدٌ مفقود»
         لا «فحصٌ سقط». */
    if ($clean !== '') { @$conn->query($clean); }
    @$mig->query("ALTER TABLE `$tbl` ADD CONSTRAINT `$chk` $ddl");
    $chkBack = (int) $one("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl'
                              AND CONSTRAINT_NAME = '$chk'");
    list($code2, $failed2) = run_gate($PHP, $GATE);
    $back = ($code2 === 0 && $chkBack === 1);
    if ($inserted && $caught && $back) { $pass++; printf("  ✔ %-8s %s\n", $id, $what); }
    else {
        $blind[] = $id . (!$inserted ? ' (تعذّر الكسر)' : ($caught ? ' (لم يرجع)' : ' (لم يسقط)'));
        printf("  ✘ %-8s %s — القيدُ عاد %s\n", $id, $what, $chkBack === 1 ? 'نعم' : 'لا');
    }
}

/* ══════════════════════════════════════════════════════════════════════════
   ③ صنفُ `DB_REFUSED` — ما يمنعه المخطَّطُ يُحاوَل ويُشترط أن يُرَدّ
   ══════════════════════════════════════════════════════════════════════════ */
echo "\n③ قيودُ المخطَّطِ — تُحاوَل مخالفتُها فتُردّ ────────────────────\n";
$REFUSE = array(
    array('مالكُ حلٍّ في إدارةِ البلاغات',
        "INSERT INTO tkt_party (company_id,ticket_id,party_role,actor_kind,actor_id,actor_dept,recorded_by,src_ref)
           VALUES ($company,$NEGWS,'RESOLUTION_OWNER','PERSON',1,'DEP-10',1,'$NEG')"),
    array('مالكُ تذكرةٍ خارجَ مركزِ البلاغات',
        "INSERT INTO tkt_party (company_id,ticket_id,party_role,actor_kind,actor_id,actor_dept,recorded_by,src_ref)
           VALUES ($company,$NEGWS,'TICKET_OWNER','PERSON',2,'DEP-14',1,'$NEG')"),
    array('محلُّ بلاغٍ بلا نوعٍ من الكتالوج',
        "INSERT INTO tkt_party (company_id,ticket_id,party_role,actor_kind,actor_id,actor_dept,recorded_by,src_ref)
           VALUES ($company,$NEGWS,'SUBJECT','ASSET',3,'DEP-04',1,'$NEG')"),
    array('دورٌ غيرُ الأربعةِ المُعلَنة',
        "INSERT INTO tkt_party (company_id,ticket_id,party_role,actor_kind,actor_id,actor_dept,recorded_by,src_ref)
           VALUES ($company,$NEGWS,'WATCHER','PERSON',4,'DEP-14',1,'$NEG')"),
    array('توجيهٌ إلى إدارةِ البلاغات',
        "INSERT INTO tkt_routing_history (company_id,ticket_id,seq_no,route_kind,from_dept,to_dept,
             rule_ref,routed_by,src_ref)
           VALUES ($company,$NEGWS,1,'AUTO','DEP-12','DEP-10','R1',1,'$NEG')"),
    array('توجيهٌ من إدارةٍ إلى نفسِها',
        "INSERT INTO tkt_routing_history (company_id,ticket_id,seq_no,route_kind,from_dept,to_dept,
             rule_ref,routed_by,src_ref)
           VALUES ($company,$NEGWS,2,'AUTO','DEP-14','DEP-14','R1',1,'$NEG')"),
    array('إسنادٌ إلى إدارةِ البلاغات',
        "INSERT INTO tkt_assignment_history (company_id,ticket_id,seq_no,to_person_id,to_dept,reason,assigned_by,src_ref)
           VALUES ($company,$NEGWS,1,5,'DEP-10','سبب',1,'$NEG')"),
    array('إجراءُ معالجةٍ بلا مرجعٍ في شاشةِ إدارتِه',
        "INSERT INTO tkt_resolution_action (company_id,ticket_id,seq_no,executor_dept,
             executor_person_id,action_ar,dept_screen_ref,src_ref)
           VALUES ($company,$NEGWS,1,'DEP-14',1,'اصلاح','','$NEG')"),
    array('تحقّقٌ من المنفِّذِ نفسِه',
        "INSERT INTO tkt_verification (company_id,ticket_id,cycle_no,priority_code,resolved_at,
             resolved_by,resolved_dept,window_hours,verified_at,verified_by,verify_kind,state,src_ref)
           VALUES ($company,$NEGWS,1,'normal',NOW(),7,'DEP-14',1,NOW(),7,'SPECIALIST','verified','$NEG')"),
    array('إغلاقٌ آليٌّ لبلاغٍ حرِج',
        "INSERT INTO tkt_verification (company_id,ticket_id,cycle_no,priority_code,resolved_at,
             resolved_by,resolved_dept,window_hours,verified_at,verified_by,verify_kind,state,src_ref)
           VALUES ($company,$NEGWS,2,'critical',NOW(),7,'DEP-14',1,NOW(),8,'AUTO_WINDOW','verified','$NEG')"),
    array('معالجةٌ من إدارةِ البلاغاتِ في دورةِ التحقّق',
        "INSERT INTO tkt_verification (company_id,ticket_id,cycle_no,priority_code,resolved_at,
             resolved_by,resolved_dept,window_hours,state,src_ref)
           VALUES ($company,$NEGWS,3,'normal',NOW(),7,'DEP-10',1,'resolved','$NEG')"),
    array('إعادةُ فتحٍ بلا سببٍ مكتوب',
        "INSERT INTO tkt_reopen (company_id,ticket_id,seq_no,prior_cycle_no,reopen_reason,note,
             raised_by,back_to_dept,src_ref)
           VALUES ($company,$NEGWS,1,1,'REPORTER_OBJECTION','',1,'DEP-14','$NEG')"),
    array('إعادةُ فتحٍ عائدةٌ إلى مركزِ البلاغات',
        "INSERT INTO tkt_reopen (company_id,ticket_id,seq_no,prior_cycle_no,reopen_reason,note,
             raised_by,back_to_dept,src_ref)
           VALUES ($company,$NEGWS,2,1,'RECURRENCE','سبب',1,'DEP-10','$NEG')"),
    array('نوعُ محلِّ بلاغٍ بلا سجلٍّ مرجعيّ',
        "INSERT INTO tkt_subject_type (company_id,type_code,name_ar,entity_kind,ref_table,ref_key,owner_dept,src_ref)
           VALUES ($company,'$NEG-2','نوع','ASSET','','id','DEP-04','$NEG')"),
    array('مستندٌ إلزاميٌّ بلا تاريخِ انتهاء',
        "INSERT INTO hr_employee_document (company_id,employee_id,doc_type,doc_no,is_mandatory,file_ref,created_by,src_ref)
           VALUES ($company,1,'license','$NEG',1,'x.pdf',1,'$NEG')"),
    array('استثناءُ بندِ تهيئةٍ بلا مستند',
        "INSERT INTO hr_onboarding_item (company_id,employee_id,item_code,item_ar,mandatory,state,src_ref)
           VALUES ($company,1,'$NEG','بند','1','waived','$NEG')"),
    array('اعتمادُ حركةٍ من طالبِها نفسِه',
        "INSERT INTO hr_job_movement (company_id,employee_id,movement_kind,to_position_id,effective_date,
             doc_ref,requested_by,approved_by,state,src_ref)
           VALUES ($company,1,'transfer',1,CURDATE(),'$NEG',9,9,'approved','$NEG')"),
    array('تدريبٌ مكتملٌ بلا شهادة',
        "INSERT INTO hr_training_record (company_id,employee_id,program_code,program_ar,training_kind,
             mandatory,state,src_ref)
           VALUES ($company,1,'$NEG','برنامج','safety',0,'completed','$NEG')"),
    array('تقييمُ موظّفٍ يقيّم نفسَه',
        "INSERT INTO hr_performance_review (company_id,employee_id,cycle_code,criteria_ref,reviewer_id,state,src_ref)
           VALUES ($company,9,'$NEG','C1',9,'draft','$NEG')"),
    array('تحقيقٌ يقوده المبلِّغُ نفسُه',
        "INSERT INTO hr_disciplinary_case (company_id,case_no,employee_id,incident_at,incident_ar,
             reported_by,investigator_id,state,src_ref)
           VALUES ($company,'$NEG-A',1,NOW(),'واقعة',9,9,'investigation','$NEG')"),
    array('قرارٌ يصدره المحقِّقُ نفسُه',
        "INSERT INTO hr_disciplinary_case (company_id,case_no,employee_id,incident_at,incident_ar,
             reported_by,investigator_id,decided_by,decision_ref,state,src_ref)
           VALUES ($company,'$NEG-B',1,NOW(),'واقعة',9,8,8,'D1','decided','$NEG')"),
    array('تكليفُ المراجعةِ الداخليةِ بلا مستند',
        "INSERT INTO hr_disciplinary_case (company_id,case_no,employee_id,incident_at,incident_ar,
             reported_by,investigation_owner_dept,state,src_ref)
           VALUES ($company,'$NEG-C',1,NOW(),'واقعة',9,'IAF','incident','$NEG')"),
    array('إدارةُ تحقيقٍ خارجَ الثلاثِ المُعلَنة',
        "INSERT INTO hr_disciplinary_case (company_id,case_no,employee_id,incident_at,incident_ar,
             reported_by,investigation_owner_dept,state,src_ref)
           VALUES ($company,'$NEG-D',1,NOW(),'واقعة',9,'DEP-11','incident','$NEG')"),
    array('مرحلةُ قرارٍ بلا مستند',
        "INSERT INTO hr_disciplinary_stage (company_id,case_id,seq_no,stage,actor_id,note,doc_ref,src_ref)
           VALUES ($company,$NEGWS,3,'decision',1,'ملاحظة','','$NEG')"),
    array('ميزةٌ بلا مرجعٍ في المسيّر',
        "INSERT INTO hr_benefit_enrollment (company_id,employee_id,benefit_code,benefit_ar,
             effective_from,payroll_component_ref,src_ref)
           VALUES ($company,1,'$NEG','ميزة',CURDATE(),'','$NEG')"),
    array('اشتراكٌ ينتهي قبل أن يسري',
        "INSERT INTO hr_benefit_enrollment (company_id,employee_id,benefit_code,benefit_ar,
             effective_from,effective_to,payroll_component_ref,src_ref)
           VALUES ($company,1,'$NEG-2','ميزة',CURDATE(),DATE_SUB(CURDATE(), INTERVAL 1 DAY),'P1','$NEG')"),
);
$refused = 0; $slipped = array();
foreach ($REFUSE as $r) {
    list($what, $sql) = $r;
    if (@$conn->query($sql) === true) {
        $slipped[] = $what;
        printf("  ✘ %s — مرَّ ولم يُردّ\n", $what);
    } else {
        $refused++;
        printf("  ✔ %s\n", $what);
    }
}
@$conn->query("DELETE FROM tkt_party WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM tkt_routing_history WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM tkt_assignment_history WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM tkt_resolution_action WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM tkt_verification WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM tkt_reopen WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM tkt_subject_type WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM hr_employee_document WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM hr_onboarding_item WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM hr_job_movement WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM hr_training_record WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM hr_performance_review WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM hr_disciplinary_stage WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM hr_disciplinary_case WHERE src_ref = '$NEG'");
@$conn->query("DELETE FROM hr_benefit_enrollment WHERE src_ref = '$NEG'");

/* ══════════════════════════════════════════════════════════════════════════
   ④ حالاتُ الملفّ — تشويهٌ ثمَّ إرجاعٌ متحقَّقٌ منه بالمقارنة
   ══════════════════════════════════════════════════════════════════════════ */
echo "\n④ حالاتُ الملفِّ — تشويهٌ ثمَّ إرجاعٌ متحقَّقٌ منه ─────────────────\n";
$fileOk = 0; $fileBad = array();

/* ⚠ **حمولةُ العتبةِ تُركَّب ولا تُكتب رقمًا** — الكاشفُ يمسح هذا الملفَّ نفسَه */
$hardNum = (string) (12 * 6);
$FILES = array(
    array('W13-20', 'زرعُ مقارنةِ عتبةٍ صلبةٍ في خدمةِ الدورة', $SVC, $svcOrig,
          str_replace('if ($amount <= 0) { return self::fail(\'DEDUCTION_AMOUNT_INVALID\', \'\'); }',
                      'if ($amount <= 0 || $amount > ' . $hardNum . ') { return self::fail(\'DEDUCTION_AMOUNT_INVALID\', \'\'); }',
                      $svcOrig)),
    array('W13-06', 'نزعُ الحارسِ المركزيِّ من سطحِ نموّ', $SCR, $scrOrig,
          str_replace(array("include '../includes/permissions_helper.php';",
                            "require_once __DIR__ . '/../includes/session_bootstrap.php';",
                            "require_once __DIR__ . '/../includes/w13_view.php';"),
                      array('', '', "require_once __DIR__ . '/../includes/w13_view.php';"), $scrOrig)),
    array('W13-02', 'نزعُ مِسبارِ المِرساةِ من سطحِ الأطراف', $SCR, $scrOrig,
          str_replace("'tkt_party',", "'tkt_party_x',", $scrOrig)),
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
list($cf, $ff) = run_gate($PHP, $GATE);
echo "\n────────────────────────────────────────────────────────────\n";
printf("حواجبُ يقظة: %d من %d · حالاتُ ملفٍّ %d من %d · قيودُ مخطَّطٍ رُدَّت %d من %d\n",
    $pass, count($CASES) + count($BELT), $fileOk, count($FILES), $refused, count($REFUSE));
printf("أعمى: %d%s\n", count($blind) + count($fileBad),
    ($blind || $fileBad) ? ' ⇐ ' . implode('، ', array_merge($blind, $fileBad)) : '');
printf("الأساسُ بعد الفحص: %s\n", $cf === 0 ? 'خضراء ✔' : 'ساقطة ✘ (' . implode(',', $ff) . ')');
$ok = (count($blind) === 0 && count($fileBad) === 0 && count($slipped) === 0 && $cf === 0);
echo $ok ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: حاجبٌ أعمى أو قيدٌ متسرِّب ✘\n";
exit($ok ? 0 : 1);
