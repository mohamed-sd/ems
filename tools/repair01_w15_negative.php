<?php
/**
 * tools/repair01_w15_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الخامسةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا نكسر كلَّ حاجبٍ على حِدةٍ ونطلب منه أن يسقط — ثمّ نُرجع.
 *   والحاجبُ الذي لا يسقط عند كسرِه **أعمى والمرحلةُ غيرُ مُغلقة**.
 *
 * ◆ **والرسوُّ على البنيةِ لا العبارة**: الالتقاطُ برمزِ الحاجبِ `W15-nn` لا
 *   بنصِّ رسالتِه — فنصُّ الخطأِ يطابق العبارةَ فيُخضِرُّ كذبًا.
 *
 * ◆ **وحزامٌ ثانٍ لقيودِ المخطَّط**: بعضُ الأحكامِ تقع في القاعدةِ لا في الأداة،
 *   فتُختبَر **بمحاولةِ كتابةٍ تُردّ** — والقيدُ الذي يقبل ما يجب أن يردَّه
 *   حاجبٌ أعمى في المخطَّطِ لا في الشيفرة.
 *
 * ◆ **وحزامٌ ثالثٌ لحالاتِ الملفّ**: أحكامُ «كتابةٌ ممنوعة» و«رقمُ دورٍ حارسًا»
 *   و«لقطةٌ دوريّة» تُقاس من القرصِ — فتُكسَر **بتحريرِ نسخةٍ من الملفِّ ثمّ
 *   إرجاعِه حرفًا**، والإرجاعُ يُتحقَّق منه بتجزئةٍ لا بالظنّ.
 *
 * التشغيل: php tools/repair01_w15_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w15_gate.php';

function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W15-') !== false && preg_match('/W15-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode('،', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

$blind = 0; $done = 0;
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/* ═══════════════════════════════════════════════════════════════════════════
   ① الحزامُ الأوّل — كسرٌ في الدفاتر
   ═══════════════════════════════════════════════════════════════════════════ */
echo "① كسرٌ في الدفاتر ─────────────────────────────────────────────\n";

$anyReq = (string) (function ($c) { $r = $c->query("SELECT requirement_id FROM repair01_w15_scope ORDER BY requirement_id LIMIT 1");
                                    $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : ''; })($conn);
$anySid = (string) (function ($c) { $r = $c->query("SELECT screen_id FROM repair01_screen_registry WHERE origin='W15' ORDER BY screen_id LIMIT 1");
                                    $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : ''; })($conn);
$anySb  = (string) (function ($c) { $r = $c->query("SELECT screen_id FROM repair01_w15_sidebar ORDER BY screen_id LIMIT 1");
                                    $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : ''; })($conn);
$anyTh  = (string) (function ($c) { $r = $c->query("SELECT th_key FROM repair01_w15_thresholds ORDER BY th_key LIMIT 1");
                                    $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : ''; })($conn);

$cases = array(
array('W15-01', 'متطلَّبٌ بلا قاعدةِ ربط',
    "UPDATE repair01_w15_scope SET map_rule='' WHERE requirement_id='$anyReq'",
    "UPDATE repair01_w15_scope SET map_rule='W15_LIVE_SURFACE' WHERE requirement_id='$anyReq'"),
array('W15-03', 'سطحٌ في الموجةِ يُعلَن مصدرًا',
    "UPDATE repair01_screen_registry SET surface_kind='SOURCE' WHERE screen_id='$anySid'",
    "UPDATE repair01_screen_registry SET surface_kind='PROJECTION' WHERE screen_id='$anySid'"),
/* ⚠ **بعضُ أحكامِ البوّابةِ لا يمكن كسرُها من الدفترِ لأنَّ القاعدةَ أشدُّ منها**:
     `W15-04` و`W15-14` و`W15-15` و`W15-18` و`W15-21` — قيودُ المخطَّطِ تردُّ
     الكسرَ قبل أن يبلغَ البوّابة. **وهذا ليس عمًى بل حزامانِ لا حزام**، ولذلك
     تُختبَر في الحزامِ الثاني (‏قيدُ المخطَّط) وفي الثالثِ (‏حالةُ الملفّ).
     ⛔ **ولا يُخفَّف قيدٌ ليُكسَر حاجب.**
     و`W15-13` **يحرسه المفتاحُ الأساسيُّ نفسُه** (`screen_id` مفتاحٌ لا يخلو)،
     و`W15-17` **هو الحزامُ الثاني** فلا يُختبَر بنفسِه. */
array('W15-05', 'جدولُ أعمالٍ ينمو بعدَ لقطةِ المرحلة',
    "DELETE FROM repair01_w15_table_snapshot WHERE table_name='exec_decisions'",
    "INSERT INTO repair01_w15_table_snapshot (table_name) VALUES ('exec_decisions')"),
array('W15-06', 'خطوةُ سايدبارٍ بلا حكم',
    "UPDATE repair01_w15_sidebar SET s5_verdict='' WHERE screen_id='$anySb'",
    "UPDATE repair01_w15_sidebar SET s5_verdict='MENU_ITEM_STANDALONE' WHERE screen_id='$anySb'"),
array('W15-07', 'انحرافُ اسمٍ عن السجلِّ المعياريّ',
    "UPDATE repair01_w15_sidebar SET s2_verdict='LABEL_DRIFT' WHERE screen_id='$anySb'",
    "UPDATE repair01_w15_sidebar SET s2_verdict='LABEL_MATCH' WHERE screen_id='$anySb'"),
array('W15-08', 'ترتيبٌ يخالف دورةَ العمل',
    "UPDATE repair01_w15_sidebar SET s4_order_no=s4_cycle_step+7 WHERE screen_id='$anySb'",
    "UPDATE repair01_w15_sidebar SET s4_order_no=s4_cycle_step WHERE screen_id='$anySb'"),
array('W15-10', 'بندٌ بلا ربطٍ بالمُعرِّفِ المعياريّ',
    "UPDATE repair01_w15_sidebar SET s7_linked=0 WHERE screen_id='$anySb'",
    "UPDATE repair01_w15_sidebar SET s7_linked=1 WHERE screen_id='$anySb'"),
array('W15-09', 'سطحٌ بلا منحِ صلاحيةٍ لأيِّ دور',
    "UPDATE repair01_w15_sidebar SET s6_perm_rows=0 WHERE screen_id='$anySb'",
    null),
array('W15-12', 'معاملةُ إدارةٍ تعود ملكيّتُها للقيادة',
    "UPDATE repair01_screen_registry SET owner_code='EX-CEO', ownership_verdict='DOMAIN_SOURCE'
      WHERE route='Finance/budget_master.php'",
    "UPDATE repair01_screen_registry SET owner_code='DEP-05', ownership_verdict='DOMAIN_SOURCE'
      WHERE route='Finance/budget_master.php'"),
array('W15-16', 'نوعٌ نافذٌ بخدمةِ مالكٍ غيرِ موجودة',
    "UPDATE gov_request_type SET owner_service='App\\\\Services\\\\Nope\\\\Ghost::createFromLauncher'
      WHERE type_code='HR_LEAVE'",
    "UPDATE gov_request_type SET owner_service='App\\\\Services\\\\Workforce\\\\LeaveRequestService::createFromLauncher'
      WHERE type_code='HR_LEAVE'"),
array('W15-22', 'كيانٌ يفقد كلَّ انتقالاتِه الممنوعة',
    "DELETE FROM repair01_w15_states WHERE entity='crisis_activation' AND allowed=0",
    null),
array('W15-23', 'عمليّةٌ حرجةٌ بلا نطاقٍ للسلطة',
    "UPDATE repair01_w15_sod SET scope_rule='' WHERE process_key='exec_financial_approval'",
    null),
array('W15-29', 'اعتمادٌ يُفترَض نيابةً عن المالك',
    "UPDATE repair01_decision_audit SET verdict='SYSTEM_ASSUMED_APPROVAL'
      WHERE decision_id=(SELECT z.d FROM (SELECT MIN(decision_id) d FROM repair01_decision_audit) z)",
    null),
array('W15-24', 'عقدُ أثرٍ بمستهلكٍ مبهم',
    "UPDATE repair01_events SET consumer_list='كل المستهلكين' WHERE wave='W15' AND event_code='ws.request.launched'",
    null),
array('W15-25', 'محطّةُ رحلةٍ تسقط',
    "UPDATE repair01_w15_journey SET verdict='FAIL' WHERE leg='MIRROR'",
    "UPDATE repair01_w15_journey SET verdict='PASS' WHERE leg='MIRROR'"),
array('W15-26', 'صفُّ أساسٍ يتحوَّل إلى نموّ',
    "UPDATE repair01_screen_registry SET origin='W15' WHERE route='Portal/ceo_board.php'",
    "UPDATE repair01_screen_registry SET origin='SURFACES' WHERE route='Portal/ceo_board.php'"),
array('W15-27', 'سطحٌ جديدٌ بحقلٍ ناقصٍ من الاثنَي عشر',
    "UPDATE repair01_screen_registry SET grain_ar='' WHERE screen_id='$anySid'",
    null),
array('W15-28', 'مؤجَّلٌ بلا بيانِ ما بُني رغمَه',
    "UPDATE repair01_w15_deferred SET why_needed='' WHERE deferred_id='W15-P-01'",
    null),
array('W15-30', 'إصلاحٌ بلا متطلَّبٍ كاشف',
    "UPDATE repair01_w15_fixes SET evidence='' WHERE fix_id='W15-F-01'",
    null),
array('W15-32', 'متطلَّبُ نائبٍ يُنزَع عن المحرّكِ الواحد',
    "UPDATE repair01_w15_scope SET map_rule='W15_NEW_PROJECTION' WHERE space_code='EX-DVP'",
    null),
);

/* التقاطُ ما يُرجَع بالقيمة */
$cap = array();
foreach (array(
    'W15-09' => array("SELECT s6_perm_rows FROM repair01_w15_sidebar WHERE screen_id='$anySb'"),
    'W15-23' => array('SELECT scope_rule FROM repair01_w15_sod WHERE process_key=\'exec_financial_approval\''),
    'W15-24' => array('SELECT consumer_list FROM repair01_events WHERE wave=\'W15\' AND event_code=\'ws.request.launched\''),
    'W15-27' => array("SELECT grain_ar FROM repair01_screen_registry WHERE screen_id='$anySid'"),
    'W15-28' => array('SELECT why_needed FROM repair01_w15_deferred WHERE deferred_id=\'W15-P-01\''),
    'W15-29' => array('SELECT CONCAT(decision_id,"|",verdict) FROM repair01_decision_audit ORDER BY decision_id LIMIT 1'),
    'W15-30' => array('SELECT evidence FROM repair01_w15_fixes WHERE fix_id=\'W15-F-01\''),
) as $k => $q) {
    $r = $conn->query($q[0]); $x = $r ? $r->fetch_row() : null; $cap[$k] = $x ? (string) $x[0] : '';
}
/* صفوفُ الانتقالاتِ الممنوعةِ تُلتقط كاملةً — فالإرجاعُ إعادةُ إدراجٍ لا تحديث */
$crisisForb = array();
$r = $conn->query("SELECT entity,from_state,to_state,owner_role,precondition,official_doc,approval_gate,
                          reopen_rule,correct_rule,forbid_why,src_ref
                     FROM repair01_w15_states WHERE entity='crisis_activation' AND allowed=0");
while ($r && ($x = $r->fetch_assoc())) { $crisisForb[] = $x; }
$vpRules = array();
$r = $conn->query("SELECT requirement_id, map_rule FROM repair01_w15_scope WHERE space_code='EX-DVP'");
while ($r && ($x = $r->fetch_assoc())) { $vpRules[$x['requirement_id']] = $x['map_rule']; }

foreach ($cases as $c) {
    list($want, $title, $break, $restore) = $c;
    if ($restore === null) {
        switch ($want) {
            case 'W15-09': $restore = "UPDATE repair01_w15_sidebar SET s6_perm_rows=" . (int) $cap[$want]
                                    . " WHERE screen_id='$anySb'"; break;
            case 'W15-22': $restore = ''; break;
            case 'W15-23': $restore = "UPDATE repair01_w15_sod SET scope_rule='" . $esc($cap[$want])
                                    . "' WHERE process_key='exec_financial_approval'"; break;
            case 'W15-29':
                $p = explode("|", $cap[$want], 2);
                $restore = "UPDATE repair01_decision_audit SET verdict='" . $esc(isset($p[1]) ? $p[1] : '')
                         . "' WHERE decision_id='" . $esc($p[0]) . "'"; break;
            case 'W15-24': $restore = "UPDATE repair01_events SET consumer_list='" . $esc($cap[$want])
                                    . "' WHERE wave='W15' AND event_code='ws.request.launched'"; break;
            case 'W15-27': $restore = "UPDATE repair01_screen_registry SET grain_ar='" . $esc($cap[$want])
                                    . "' WHERE screen_id='$anySid'"; break;
            case 'W15-28': $restore = "UPDATE repair01_w15_deferred SET why_needed='" . $esc($cap[$want])
                                    . "' WHERE deferred_id='W15-P-01'"; break;
            case 'W15-30': $restore = "UPDATE repair01_w15_fixes SET evidence='" . $esc($cap[$want])
                                    . "' WHERE fix_id='W15-F-01'"; break;
            case 'W15-32': $restore = ''; break;
        }
    }
    if ($conn->query($break) === false) { printf("  ⚠ %-8s تعذّر الكسر: %s\n", $want, $conn->error); continue; }
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($want, $failed, true);
    if ($caught) { printf("  ✔ %-8s %-42s سقطت كما يجب\n", $want, mb_substr($title, 0, 42)); }
    else { $blind++; printf("  ✘ %-8s %-42s **لم تسقط** (الساقط: %s)\n", $want, mb_substr($title, 0, 42),
                            $failed ? implode('،', $failed) : 'لا شيء'); }
    if ($want === 'W15-32') {
        foreach ($vpRules as $rid => $rule) {
            $conn->query("UPDATE repair01_w15_scope SET map_rule='" . $esc($rule) . "' WHERE requirement_id='" . $esc($rid) . "'");
        }
    } elseif ($want === 'W15-22') {
        foreach ($crisisForb as $x) {
            $conn->query("INSERT INTO repair01_w15_states
                (entity,from_state,to_state,allowed,owner_role,precondition,official_doc,
                 approval_gate,reopen_rule,correct_rule,forbid_why,src_ref)
                VALUES ('" . $esc($x['entity']) . "','" . $esc($x['from_state']) . "','" . $esc($x['to_state']) . "',0,
                        '" . $esc($x['owner_role']) . "','" . $esc($x['precondition']) . "','" . $esc($x['official_doc']) . "',
                        '" . $esc($x['approval_gate']) . "','" . $esc($x['reopen_rule']) . "','" . $esc($x['correct_rule']) . "',
                        '" . $esc($x['forbid_why']) . "','" . $esc($x['src_ref']) . "')");
        }
    } elseif ($restore !== '' && $conn->query($restore) === false) {
        printf("  ⛔ %-8s فشلَ الإرجاع: %s\n", $want, $conn->error); $blind++;
    }
    $done++;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ② الحزامُ الثاني — قيودُ المخطَّطِ تُختبَر بمحاولةِ كتابةٍ تُردّ
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n② قيودُ المخطَّطِ — كتابةٌ يجب أن تُردَّ في القاعدة ─────────────\n";
$CO = (int) (function ($c) { $r = $c->query("SELECT company_id FROM gov_request_type WHERE state='active' LIMIT 1");
                             $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : 0; })($conn);
$schema = array(
array('chk_grt_not_workspace', 'مساحةُ عملي تملك تعريفَ طلب',
    "INSERT INTO gov_request_type (company_id,type_code,version_no,name_ar,definition_owner_dept,
      registry_governed_by,authority_rule_id,routing_rule_ref,permission_policy,state,
      owner_table,owner_service,projection_user_col,src_ref)
     VALUES ($CO,'W15NEG_WS',1,'ن','WS-MY','DEP-08','A','R','P','draft','t','s','c','W15NEG')",
    "DELETE FROM gov_request_type WHERE type_code='W15NEG_WS'"),
array('chk_grt_binding', 'نوعٌ نافذٌ بلا رابطةِ مالك',
    "INSERT INTO gov_request_type (company_id,type_code,version_no,name_ar,definition_owner_dept,
      registry_governed_by,authority_rule_id,routing_rule_ref,permission_policy,state,
      owner_table,owner_service,projection_user_col,src_ref)
     VALUES ($CO,'W15NEG_NB',1,'ن','DEP-14','DEP-08','A','R','P','active','','','','W15NEG')",
    "DELETE FROM gov_request_type WHERE type_code='W15NEG_NB'"),
array('chk_grt_gov', 'الحوكمةُ لا تحكم السجلّ',
    "INSERT INTO gov_request_type (company_id,type_code,version_no,name_ar,definition_owner_dept,
      registry_governed_by,authority_rule_id,routing_rule_ref,permission_policy,state,
      owner_table,owner_service,projection_user_col,src_ref)
     VALUES ($CO,'W15NEG_GV',1,'ن','DEP-14','DEP-14','A','R','P','draft','t','s','c','W15NEG')",
    "DELETE FROM gov_request_type WHERE type_code='W15NEG_GV'"),
array('chk_w15_scope_projection', 'صنفُ سطحٍ غيرُ إسقاطٍ في دفترِ النطاق',
    "INSERT INTO repair01_w15_scope (requirement_id,surface_kind) VALUES ('W15NEG_K','SOURCE')",
    "DELETE FROM repair01_w15_scope WHERE requirement_id='W15NEG_K'"),
array('chk_w15_scope_live', 'قراءةٌ بنسخةٍ دوريّةٍ في دفترِ النطاق',
    "INSERT INTO repair01_w15_scope (requirement_id,read_mode) VALUES ('W15NEG_R','SNAPSHOT_COPY')",
    "DELETE FROM repair01_w15_scope WHERE requirement_id='W15NEG_R'"),
array('chk_w15_lnch_no_local', 'مخزنٌ محلّيٌّ في دفترِ المُطلِق',
    "INSERT INTO repair01_w15_launcher (type_code,definition_owner,registry_gov,authority_rule,
      routing_rule,owner_table,owner_service,projection_col,local_store)
     VALUES ('W15NEG_L','DEP-14','DEP-08','A','R','t','s','c','requests')",
    "DELETE FROM repair01_w15_launcher WHERE type_code='W15NEG_L'"),
array('chk_w15_lnch_quad', 'مساحةُ عملي مالكةُ تعريفٍ في دفترِ المُطلِق',
    "INSERT INTO repair01_w15_launcher (type_code,definition_owner,registry_gov,authority_rule,
      routing_rule,owner_table,owner_service,projection_col,local_store)
     VALUES ('W15NEG_Q','WS-MY','DEP-08','A','R','t','s','c','')",
    "DELETE FROM repair01_w15_launcher WHERE type_code='W15NEG_Q'"),
array('chk_w15_axis_split', 'الرؤيةُ تساوي السلطةَ في محورٍ واحد',
    "INSERT INTO repair01_w15_scope_axis (axis_key,visibility_rule,authority_rule,authority_src)
     VALUES ('W15NEG_A','نفس النص','نفس النص','x')",
    "DELETE FROM repair01_w15_scope_axis WHERE axis_key='W15NEG_A'"),
array('chk_w15_th_pending_null', 'عتبةٌ منتظرةٌ بقيمةٍ مخترَعة',
    "INSERT INTO repair01_w15_thresholds (th_key,state,value_num,registry)
     VALUES ('W15NEG_T','CONFIG_PENDING',100000,'x')",
    "DELETE FROM repair01_w15_thresholds WHERE th_key='W15NEG_T'"),
array('chk_w15_th_test_not_prod', 'قيمةُ اختبارٍ على عتبةٍ معتمَدة',
    "INSERT INTO repair01_w15_thresholds (th_key,state,value_num,test_value,registry,owner_text)
     VALUES ('W15NEG_TP','OWNER_APPROVED',5,9,'x','نص')",
    "DELETE FROM repair01_w15_thresholds WHERE th_key='W15NEG_TP'"),
array('chk_w15_def_built', 'مؤجَّلٌ بلا بيانِ ما بُني رغمَه',
    "INSERT INTO repair01_w15_deferred (deferred_id,kind,raised_at,built_anyway)
     VALUES ('W15NEG_D','STRUCTURAL','2026-08-27','')",
    "DELETE FROM repair01_w15_deferred WHERE deferred_id='W15NEG_D'"),
array('chk_w15_state_forbid', 'انتقالٌ ممنوعٌ بلا سبب',
    "INSERT INTO repair01_w15_states (entity,from_state,to_state,allowed,forbid_why)
     VALUES ('W15NEG_E','a','b',0,'')",
    "DELETE FROM repair01_w15_states WHERE entity='W15NEG_E'"),
array('chk_w15_sod_combo', 'عمليّةٌ حرجةٌ بلا تركيبةٍ ممنوعة',
    "INSERT INTO repair01_w15_sod (process_key,initiator,reviewer,approver,executor,closer,
      forbidden_combo,authority_rule) VALUES ('W15NEG_S','a','b','c','d','e','','A')",
    "DELETE FROM repair01_w15_sod WHERE process_key='W15NEG_S'"),
);
$schemaBlind = 0; $schemaDone = 0;
foreach ($schema as $s) {
    list($name, $title, $break, $clean) = $s;
    $ok = @$conn->query($break);
    if ($ok === false) { printf("  ✔ %-28s %-40s رُدَّت في القاعدة\n", $name, mb_substr($title, 0, 40)); }
    else {
        $schemaBlind++;
        printf("  ✘ %-28s %-40s **قُبلت** — القيدُ أعمى\n", $name, mb_substr($title, 0, 40));
        @$conn->query($clean);
    }
    $schemaDone++;
}

/* ═══════════════════════════════════════════════════════════════════════════
   ③ الحزامُ الثالث — حالاتُ الملفّ: الكسرُ على القرصِ والإرجاعُ بتجزئة
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n③ حالاتُ الملفِّ — كسرٌ على القرصِ وإرجاعٌ متحقَّقٌ بتجزئة ────────\n";
$fileCases = array(
    array('W15-11', 'كتابةٌ من مساحةِ القيادةِ في جدولِ نطاقٍ آخر',
          'Portal/exec_daily_stops.php',
          "// negative probe\n\$x = \"INSERT INTO ops_stop_register (id) VALUES (0)\";\n"),
    array('W15-04', 'لوحةُ القيادةِ تعود إلى اللقطةِ الدوريّة',
          'Portal/ceo_board.php',
          "// negative probe\n\$y = \"INSERT INTO exec_board_snapshots (id) VALUES (0)\";\n"),
    array('W15-19', 'رقمُ دورٍ يعود حارسًا في سطحِ قيادة',
          'Portal/exec_escalations.php',
          "// negative probe\n\$z = (\$_SESSION['role'] === '9');\n"),
    array('W15-14', '«طلباتي» تعود إلى الكتابةِ في المخزنِ العامّ',
          'Portal/my_requests.php',
          "// negative probe\n\$w = \"INSERT INTO requests (id) VALUES (0)\";\n"),
    array('W15-20', 'عتبةٌ رقميّةٌ صلبةٌ تعود إلى خدمةِ الموجة',
          'app/Services/Exec/ExecDecisionRouter.php',
          "// negative probe\n\$t = 0; if (\$t > 500000) { \$t = 1; }\n"),
    array('W15-08', 'ترتيبٌ يدويٌّ موازٍ للسجلِّ في صفحة',
          'Portal/exec_actions_followup.php',
          "// negative probe\n\$q = \"SELECT * FROM nav_items WHERE active=1 ORDER BY sort_order\";\n"),
);
/* ⚠ **وحالتانِ تُكسَرانِ بالاستبدالِ لا بالإلحاق** — فالإلحاقُ لا ينزع ما يقيسه
     الحاجب. والإرجاعُ بالنصِّ الأصليِّ متحقَّقًا بتجزئتِه. */
$replaceCases = array(
    array('W15-02', 'المِرساةُ تفقد مِسبارَها على القرص',
          'Portal/exec_daily_stops.php', "'ops_stop_register'", "'zz_no_such_table'"),
    array('W15-18', 'محرّكُ النطاقِ يفقد دالّةَ السلطة',
          'app/Services/Exec/ScopeEngine.php', 'function authority', 'function authorityX'),
    array('W15-31', 'تشكيلٌ يعود إلى رأسِ عمودٍ مُصيَّر',
          'Portal/exec_delegations.php', '<th>النائب</th>', '<th>النائبُ</th>'),
);
$fileBlind = 0; $fileDone = 0;
foreach ($fileCases as $fc) {
    list($want, $title, $rel, $inject) = $fc;
    $path = $ROOT . '/' . $rel;
    if (!is_file($path)) { printf("  ⚠ %-8s %s غير موجود\n", $want, $rel); continue; }
    $orig = file_get_contents($path);
    $hash = hash('sha256', $orig);
    file_put_contents($path, $orig . "\n<?php\n" . $inject);
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($want, $failed, true);
    if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", $want, mb_substr($title, 0, 46)); }
    else { $fileBlind++; printf("  ✘ %-8s %-46s **لم تسقط** (الساقط: %s)\n", $want, mb_substr($title, 0, 46),
                                $failed ? implode('،', $failed) : 'لا شيء'); }
    file_put_contents($path, $orig);
    if (hash('sha256', (string) file_get_contents($path)) !== $hash) {
        printf("  ⛔ %-8s **الإرجاعُ لم يطابق التجزئةَ** في %s\n", $want, $rel); $fileBlind++;
    }
    $fileDone++;
}
foreach ($replaceCases as $rc) {
    list($want, $title, $rel, $from, $to) = $rc;
    $path = $ROOT . '/' . $rel;
    if (!is_file($path)) { printf("  ⚠ %-8s %s غير موجود\n", $want, $rel); continue; }
    $orig = file_get_contents($path);
    $hash = hash('sha256', $orig);
    if (strpos($orig, $from) === false) {
        printf("  ⚠ %-8s لم يُعثر على موضعِ الكسرِ في %s\n", $want, $rel); continue;
    }
    file_put_contents($path, str_replace($from, $to, $orig));
    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($want, $failed, true);
    if ($caught) { printf("  ✔ %-8s %-46s سقطت كما يجب\n", $want, mb_substr($title, 0, 46)); }
    else { $fileBlind++; printf("  ✘ %-8s %-46s **لم تسقط** (الساقط: %s)\n", $want, mb_substr($title, 0, 46),
                                $failed ? implode('،', $failed) : 'لا شيء'); }
    file_put_contents($path, $orig);
    if (hash('sha256', (string) file_get_contents($path)) !== $hash) {
        printf("  ⛔ %-8s **الإرجاعُ لم يطابق التجزئةَ** في %s\n", $want, $rel); $fileBlind++;
    }
    $fileDone++;
}

/* ═══════════════════════════════════════════════════════════════════════════
   التحقّقُ من الإرجاع
   ═══════════════════════════════════════════════════════════════════════════ */
echo "\n";
list($cz, $fz) = run_gate($PHP, $GATE);
if ($cz === 0) { echo "الإرجاع: البوّابةُ عادت خضراء ✔\n"; }
else { echo "⛔ الإرجاع فاشل — البوّابةُ ما زالت ساقطةً في: " . implode('،', $fz) . "\n"; $blind++; }

echo "\n────────────────────────────────────────────────────────────\n";
printf("الفحصُ السلبيّ: دفاترُ %d (أعمى %d) · قيودُ مخطَّطٍ %d (أعمى %d) · حالاتُ ملفٍّ %d (أعمى %d)\n",
       $done, $blind, $schemaDone, $schemaBlind, $fileDone, $fileBlind);
$total = $blind + $schemaBlind + $fileBlind;
echo ($total === 0 ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: يوجد حاجبٌ أعمى ✘\n");
exit($total === 0 ? 0 : 1);
