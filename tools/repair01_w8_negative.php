<?php
/**
 * tools/repair01_w8_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الثامنة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويُطلَب من البوّابةِ أن تسقط —
 *   ثمَّ تُرجَع الحالة. والحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على رمزِ الحاجبِ لا على عبارتِه** (‏_CONTEXT §قواعد القياس ③):
 *   يُلتقط `W8-\d+` من سطرِ السقوطِ — ونصُّ حالةِ الخطأِ لا يُطابَق أبدًا.
 *
 * ◆ **ولا يُختبَر ما يضمنه المخطَّط** (تعريفُ الحاجبِ الأعمى · W02): «إصلاحٌ
 *   بلا كاشف» و«عتبةٌ بلا عذر» يمنعهما `CHECK`، فلا موضعَ لهما هنا —
 *   والمُختبَرُ ما **تستطيع** القاعدةُ قبولَه.
 *
 * ◆ **والكسرُ يقع حيث يقرأ الحاجب**: حاجبٌ يقرأ ملفًّا يُكسَر بملفِّه، وحاجبٌ
 *   يشغّل الرحلةَ من جديدٍ لا يُكسَر بصفوفِ جولةٍ سابقةٍ **يدهسها هو**.
 *
 * ◆ **والإرجاعُ يُتحقَّق منه** بإعادةِ تشغيلِ البوّابةِ ووجوبِ عودتها خضراء.
 *
 * التشغيل: php tools/repair01_w8_negative.php
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

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w8_gate.php';
$e = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };

/** يشغّل البوّابةَ ويعيد [رمزُ الخروج، أيُّ حاجبٍ سقط] — بالرمزِ لا بالعبارة */
function w8_run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W8-') !== false && preg_match('/W8-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, array_values(array_unique($failed)));
}

list($c0, $f0) = w8_run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ══ قيمٌ تُلتقط قبلَ الكسرِ ليكونَ الإرجاعُ بالقيمةِ لا بالتخمين ═══════ */
$g = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : null; };
$snap = array();

$snap['scopeReq']  = $g("SELECT requirement_id FROM repair01_w8_scope ORDER BY requirement_id LIMIT 1");
$snap['scopeRule'] = $g("SELECT map_rule FROM repair01_w8_scope ORDER BY requirement_id LIMIT 1");
$snap['sbSid']     = $g("SELECT screen_id FROM repair01_w8_sidebar ORDER BY screen_id LIMIT 1");
$snap['sbS1']      = $g("SELECT s1_verdict FROM repair01_w8_sidebar ORDER BY screen_id LIMIT 1");
$snap['navId']     = $g("SELECT n.id FROM nav_items n JOIN repair01_screen_registry g ON g.route = n.route
                          WHERE g.owner_code IN ('DEP-01','DEP-02') AND n.active = 1
                            AND n.permission_code IS NOT NULL AND n.permission_code <> '' LIMIT 1");
$snap['navPerm']   = $g("SELECT permission_code FROM nav_items WHERE id = " . (int) $snap['navId']);
$snap['canRoute']  = $g("SELECT c.route FROM nav_canonical c JOIN repair01_screen_registry g ON g.route = c.route
                          WHERE g.owner_code IN ('DEP-01','DEP-02') AND g.on_disk = 1
                            AND c.screen_id = g.screen_id LIMIT 1");
$snap['canSid']    = $g("SELECT screen_id FROM nav_canonical WHERE route = '" . $e($snap['canRoute']) . "'");
$snap['d04rows']   = $g("SELECT scope_rows FROM repair01_w8_decisions WHERE decision_id = 'W8-D-04'");
$snap['d10rows']   = $g("SELECT scope_rows FROM repair01_w8_decisions WHERE decision_id = 'W8-D-10'");
$snap['fixKey']    = $g("SELECT fix_key FROM repair01_w8_fixes ORDER BY fix_key LIMIT 1");
$snap['fixRev']    = $g("SELECT revealed_by FROM repair01_w8_fixes ORDER BY fix_key LIMIT 1");

/* ⛔ **والكيانُ المُختبَرُ يُختار بأقلِّ ممنوعٍ لا بالأبجديّة**: كيانٌ بثلاثةِ
     ممنوعاتٍ يحتاج كسرَ ثلاثةِ صفوفٍ وإرجاعَها، وكلُّ صفٍّ فرصةُ إرجاعٍ ناقصٍ
     يترك الدفترَ مختلًّا **والبوّابةَ خضراء** (فهي تشترط ≥١ لا العددَ نفسَه).
     فالكيانُ ذو الممنوعِ الواحدِ يجعل الكسرَ والإرجاعَ صفًّا واحدًا محسومًا. */
$snap['stEnt']  = $g("SELECT entity FROM repair01_w8_states WHERE allowed = 0
                       GROUP BY entity ORDER BY COUNT(*) ASC, entity LIMIT 1");
$snap['stFrom'] = $g("SELECT from_state FROM repair01_w8_states WHERE allowed = 0
                       AND entity = '" . $e($snap['stEnt']) . "' LIMIT 1");
$snap['stTo']   = $g("SELECT to_state FROM repair01_w8_states WHERE allowed = 0
                       AND entity = '" . $e($snap['stEnt']) . "' LIMIT 1");
$snap['stWhy']  = $g("SELECT forbid_reason FROM repair01_w8_states WHERE allowed = 0
                       AND entity = '" . $e($snap['stEnt']) . "' LIMIT 1");
$snap['stForbidTotal'] = $g("SELECT COUNT(*) FROM repair01_w8_states WHERE allowed = 0");

$snap['sodKey']  = $g("SELECT process_key FROM repair01_w8_sod ORDER BY process_key LIMIT 1");
$snap['sodEnf']  = $g("SELECT enforced_by FROM repair01_w8_sod ORDER BY process_key LIMIT 1");
$snap['evCode']  = $g("SELECT event_code FROM repair01_events WHERE contract_stage = 'W08' ORDER BY event_code LIMIT 1");
$snap['evCons']  = $g("SELECT consumer_list FROM repair01_events WHERE event_code = '" . $e($snap['evCode']) . "'");
$snap['evEff']   = $g("SELECT consumer_effect FROM repair01_events WHERE event_code = '" . $e($snap['evCode']) . "'");
$snap['gapId']   = $g("SELECT id FROM repair01_target_gaps WHERE origin_stage = 'W02' AND wave_stage <> '' ORDER BY id LIMIT 1");
$snap['gapWave'] = $g("SELECT wave_stage FROM repair01_target_gaps WHERE id = " . (int) $snap['gapId']);
$snap['regKey']  = $g("SELECT check_key FROM repair01_w8_regression WHERE phase = 'AFTER' ORDER BY check_key LIMIT 1");
$snap['regMeas'] = $g("SELECT measured FROM repair01_w8_regression WHERE phase = 'AFTER' AND check_key = '" . $e($snap['regKey']) . "'");
$snap['d07']     = $conn->query("SELECT * FROM repair01_w8_decisions WHERE decision_id='W8-D-07'")->fetch_assoc();
$snap['diskSid'] = $g("SELECT screen_id FROM repair01_screen_registry WHERE origin='DISK' ORDER BY screen_id LIMIT 1");

/* ══ ① حالاتُ الكسرِ في الدفاترِ والسجلّ ═══════════════════════════════ */
$cases = array(
 array('W8-01', 'نزعُ قاعدةِ الربطِ عن متطلَّب',
   "UPDATE repair01_w8_scope SET map_rule='' WHERE requirement_id='" . $e($snap['scopeReq']) . "'",
   "UPDATE repair01_w8_scope SET map_rule='" . $e($snap['scopeRule']) . "' WHERE requirement_id='" . $e($snap['scopeReq']) . "'"),

 array('W8-02', 'تحويلُ مِرساةٍ مُثبَتةٍ إلى مُعرِّفٍ يتيم',
   "UPDATE repair01_w8_scope SET anchor_screen_id='SCR-9999' WHERE requirement_id='SAL-01'",
   "UPDATE repair01_w8_scope SET anchor_screen_id=(SELECT screen_id FROM repair01_screen_registry
      WHERE route='Clients/clients.php') WHERE requirement_id='SAL-01'"),

 array('W8-03', 'تحريكُ عددِ مالكٍ مخالفٍ عن المُعلَن',
   "UPDATE repair01_w8_decisions SET scope_rows=scope_rows+1 WHERE decision_id='W8-D-03'",
   "UPDATE repair01_w8_decisions SET scope_rows=scope_rows-1 WHERE decision_id='W8-D-03'"),

 array('W8-04', 'نزعُ حكمِ خطوةٍ من السبع',
   "UPDATE repair01_w8_sidebar SET s1_verdict='' WHERE screen_id='" . $e($snap['sbSid']) . "'",
   "UPDATE repair01_w8_sidebar SET s1_verdict='" . $e($snap['sbS1']) . "' WHERE screen_id='" . $e($snap['sbSid']) . "'"),

 array('W8-05', 'نزعُ رمزِ الصلاحيةِ عن بندٍ حيّ',
   "UPDATE nav_items SET permission_code='' WHERE id=" . (int) $snap['navId'],
   "UPDATE nav_items SET permission_code='" . $e($snap['navPerm']) . "' WHERE id=" . (int) $snap['navId']),

 array('W8-07', 'تحريكُ عددِ «بلا مصدرِ ترتيبٍ» عن المُعلَن',
   "UPDATE repair01_w8_decisions SET scope_rows=scope_rows+3 WHERE decision_id='W8-D-02'",
   "UPDATE repair01_w8_decisions SET scope_rows=scope_rows-3 WHERE decision_id='W8-D-02'"),

 array('W8-08', 'فكُّ ربطِ بندٍ معياريٍّ بمُعرِّفِ شاشتِه',
   "UPDATE nav_canonical SET screen_id='' WHERE route='" . $e($snap['canRoute']) . "'",
   "UPDATE nav_canonical SET screen_id='" . $e($snap['canSid']) . "' WHERE route='" . $e($snap['canRoute']) . "'"),

 array('W8-09', 'تزييفُ رقمٍ مخزَّنٍ في شوطِ الانحدارِ اللاحق',
   "UPDATE repair01_w8_regression SET measured=measured+7 WHERE phase='AFTER' AND check_key='" . $e($snap['regKey']) . "'",
   "UPDATE repair01_w8_regression SET measured=" . (int) $snap['regMeas'] . " WHERE phase='AFTER' AND check_key='" . $e($snap['regKey']) . "'"),

 array('W8-10', 'قلبُ فحصٍ عابرٍ في الأساسِ إلى ساقطٍ بعده',
   "UPDATE repair01_w8_regression SET verdict='FAIL' WHERE phase='AFTER' AND check_key='SAL_CLIENT_TENANT'",
   "UPDATE repair01_w8_regression SET verdict='PASS' WHERE phase='AFTER' AND check_key='SAL_CLIENT_TENANT'"),

 array('W8-11', 'تحريكُ العددِ المُعلَنِ عن السقوطِ المقيس',
   "UPDATE repair01_w8_decisions SET scope_rows=scope_rows+1 WHERE decision_id='W8-D-04'",
   "UPDATE repair01_w8_decisions SET scope_rows=" . (int) $snap['d04rows'] . " WHERE decision_id='W8-D-04'"),

 array('W8-11', 'نزعُ إعلانِ سقوطٍ بالكامل',
   "DELETE FROM repair01_w8_decisions WHERE decision_id='W8-D-07'", '@restore_d07'),

 /* ⛔ «إصلاحٌ بلا كاشف» يمنعه `chk_w8fx_rev` في المخطَّط — والمُختبَرُ هنا ما
      **يستطيع** المخطَّطُ قبولَه: كاشفٌ قائمٌ لكنَّه خارجَ نطاقِ المرحلة. */
 array('W8-12', 'إسنادُ إصلاحٍ إلى كاشفٍ خارجَ نطاقِ المرحلة',
   "UPDATE repair01_w8_fixes SET revealed_by='MNT-07' WHERE fix_key='" . $e($snap['fixKey']) . "'",
   "UPDATE repair01_w8_fixes SET revealed_by='" . $e($snap['fixRev']) . "' WHERE fix_key='" . $e($snap['fixKey']) . "'"),

 array('W8-13', 'نزعُ الممنوعِ الصريحِ عن كيانٍ كامل',
   "UPDATE repair01_w8_states SET allowed=1, owner_role='x', precondition='x', official_doc='x',
      approval_gate='x', reopen_rule='x', correct_rule='x', forbid_reason=''
    WHERE entity='" . $e($snap['stEnt']) . "' AND allowed=0",
   "UPDATE repair01_w8_states SET allowed=0, owner_role='', precondition='', official_doc='',
      approval_gate='', reopen_rule='', correct_rule='', forbid_reason='" . $e($snap['stWhy']) . "'
    WHERE entity='" . $e($snap['stEnt']) . "' AND from_state='" . $e($snap['stFrom']) . "'
      AND to_state='" . $e($snap['stTo']) . "'"),

 array('W8-14', 'إعلانُ إنفاذٍ بلا تصنيفٍ في فصلِ الواجبات',
   "UPDATE repair01_w8_sod SET enforced_by='يمنعه النظام' WHERE process_key='" . $e($snap['sodKey']) . "'",
   "UPDATE repair01_w8_sod SET enforced_by='" . $e($snap['sodEnf']) . "' WHERE process_key='" . $e($snap['sodKey']) . "'"),

 array('W8-14', 'تحريكُ عددِ «بلا حارسٍ» عن المُعلَن',
   "UPDATE repair01_w8_decisions SET scope_rows=scope_rows+1 WHERE decision_id='W8-D-10'",
   "UPDATE repair01_w8_decisions SET scope_rows=" . (int) $snap['d10rows'] . " WHERE decision_id='W8-D-10'"),

 array('W8-15', 'نزعُ أثرِ المستهلكِ عن عقدِ حدث',
   "UPDATE repair01_events SET consumer_effect='' WHERE event_code='" . $e($snap['evCode']) . "'",
   "UPDATE repair01_events SET consumer_effect='" . $e($snap['evEff']) . "' WHERE event_code='" . $e($snap['evCode']) . "'"),

 array('W8-15', 'تعميمُ المستهلكِ بدل تسميتِه',
   "UPDATE repair01_events SET consumer_list='كل المستهلكين' WHERE event_code='" . $e($snap['evCode']) . "'",
   "UPDATE repair01_events SET consumer_list='" . $e($snap['evCons']) . "' WHERE event_code='" . $e($snap['evCode']) . "'"),

 array('W8-15', 'إسقاطُ عقدِ الأثرِ عن حدثٍ حيّ',
   "UPDATE repair01_events SET contract_status='NONE' WHERE event_code='" . $e($snap['evCode']) . "'",
   "UPDATE repair01_events SET contract_status='RECORDED' WHERE event_code='" . $e($snap['evCode']) . "'"),

 array('W8-18', 'مساسُ أساسِ السجلِّ بتحويلِ صفٍّ إلى نموّ',
   "UPDATE repair01_screen_registry SET origin='W08' WHERE screen_id='" . $e($snap['diskSid']) . "'",
   "UPDATE repair01_screen_registry SET origin='DISK' WHERE screen_id='" . $e($snap['diskSid']) . "'"),

 array('W8-20', 'إعادةُ وسمِ فجوةٍ بموجةٍ تخالف مرحلةَ وحدتِها',
   "UPDATE repair01_target_gaps SET wave_stage='W99' WHERE id=" . (int) $snap['gapId'],
   "UPDATE repair01_target_gaps SET wave_stage='" . $e($snap['gapWave']) . "' WHERE id=" . (int) $snap['gapId']),

 /* ⛔ **ولا تُكسَر الرحلةُ بصفوفِها**: البوّابةُ **تشغّلها من جديد** فتدهس أيَّ
      تعديلٍ على جولةٍ سابقة. فالكسرُ يقع على ما **تقيسه** الرحلةُ: تحريكُ إعلانِ
      المقامِ الصفريِّ (`W8-D-11`) يُسقط محطّتَي بنودِ العرضِ والتفاوض — وهو عينُ
      حارسِ الخلاءِ الذي بُني لأجلِه. */
 array('W8-21', 'تحريكُ إعلانِ المقامِ الصفريِّ عن محطّتَي الرحلة',
   "UPDATE repair01_w8_decisions SET scope_rows=scope_rows+5 WHERE decision_id='W8-D-11'",
   "UPDATE repair01_w8_decisions SET scope_rows=scope_rows-5 WHERE decision_id='W8-D-11'"),

 array('W8-22', 'نزعُ المتطلَّبِ الكاشفِ عن فحصِ انحدار',
   "UPDATE repair01_w8_regression SET revealed_by='MNT-07' WHERE phase='AFTER' AND check_key='SAL_CLIENT_TENANT'",
   "UPDATE repair01_w8_regression SET revealed_by='SAL-01' WHERE phase='AFTER' AND check_key='SAL_CLIENT_TENANT'"),
);

/* ══ ② حالاتُ الكسرِ على القرص ══════════════════════════════════════════
   ⛔ **والكسرُ ينزع كلَّ صنفِ حارسٍ يقيسه الكاشف**: `supplier_bank.php` يضمُّ
      `insidebar.php` حارسَ قشرةٍ إلى جانبِ حارسِ الصفحة، ونزعُ الأوّلِ وحدَه
      كسرٌ ناقصٌ يُقرأ «حاجبًا أعمى» وهو حاجبٌ يقظ.
   ═══════════════════════════════════════════════════════════════════════ */
$fileCases = array(
 array('W8-06', 'نزعُ كلِّ حارسٍ من ملفِّ سطحٍ في النطاق', 'Suppliers/supplier_bank.php',
       array('enforce_current_page_view_permission' => 'ems_w8_neg_no_guard',
             'check_page_permissions'               => 'ems_w8_neg_no_guard2',
             "\$_SESSION['user']"                   => "\$_ems_w8_neg['user']",
             'ems_gov_flash_redirect'               => 'ems_w8_neg_no_redirect',
             'insidebar.php'                        => 'ems_w8_neg_no_shell.php')),

 array('W8-16', 'نزعُ ناشرِ حدثٍ من ملفِّه', 'app/Services/Settlement/SettlementService.php',
       array("'settlement.approved'" => "'settlement.approved__w8neg'")),

 array('W8-17', 'كتابةُ مقارنةِ عتبةٍ صلبةٍ في أداةِ نطاق', 'tools/lib/repair01_w8_scan.php',
       array('function repair01_w8_entity_types()' =>
             "function repair01_w8_neg_threshold(\$v) { return (\$v >= 85); }\n"
             . "function repair01_w8_entity_types()")),

 array('W8-20', 'إرجاعُ اشتقاقِ الموجةِ إلى خريطةٍ صلبة', 'tools/lib/repair01_w2_scan.php',
       array('repair01_requirements WHERE unit LIKE' => 'repair01_requirements WHERE unit__w8neg LIKE')),
);

$blind = 0; $done = 0;

echo "① الكسرُ في الدفاترِ والسجلّ ──────────────────────────────────\n";
foreach ($cases as $c) {
    list($want, $title, $break, $restore) = $c;
    if ($conn->query($break) === false) { printf("  ⚠ %-8s تعذّر الكسر: %s\n", $want, $conn->error); continue; }
    list($code, $failed) = w8_run_gate($PHP, $GATE);
    $caught = in_array($want, $failed, true);
    if ($caught) { printf("  ✔ %-8s %-48s سقطت كما يجب\n", $want, $title); }
    else { $blind++; printf("  ✘ %-8s %-48s **لم تسقط** — أعمى (الساقط: %s)\n", $want, $title, $failed ? implode(',', $failed) : 'لا شيء'); }

    if ($restore === '@restore_d07') {
        $d = $snap['d07'];
        $ok = $conn->query("INSERT INTO repair01_w8_decisions (decision_id,question,ruling,rationale,scope_rows)
              VALUES ('" . $e($d['decision_id']) . "','" . $e($d['question']) . "','" . $e($d['ruling'])
            . "','" . $e($d['rationale']) . "'," . (int) $d['scope_rows'] . ")") !== false;
    } else {
        $ok = ($conn->query($restore) !== false);
    }
    if (!$ok) { printf("  ⛔ %-8s فشلَ الإرجاع: %s\n", $want, $conn->error); $blind++; }
    $done++;
}

echo "\n② الكسرُ على القرصِ — الحاجبُ الذي يقرأ ملفًّا يُكسَر بملفِّه ────\n";
foreach ($fileCases as $fc) {
    list($want, $title, $rel, $subs) = $fc;
    $abs = $ROOT . '/' . $rel;
    $orig = @file_get_contents($abs);
    if ($orig === false) { printf("  ⚠ %-8s تعذّر قراءةُ %s\n", $want, $rel); continue; }
    $mut = $orig;
    foreach ($subs as $from => $to) { $mut = str_replace($from, $to, $mut); }
    if ($mut === $orig) { printf("  ⚠ %-8s لم يتغيّر الملفُّ %s — الكسرُ لم يقع\n", $want, $rel); continue; }
    try {
        file_put_contents($abs, $mut);
        list($code, $failed) = w8_run_gate($PHP, $GATE);
        $caught = in_array($want, $failed, true);
        if ($caught) { printf("  ✔ %-8s %-48s سقطت كما يجب\n", $want, $title); }
        else { $blind++; printf("  ✘ %-8s %-48s **لم تسقط** — أعمى (الساقط: %s)\n", $want, $title, $failed ? implode(',', $failed) : 'لا شيء'); }
        $done++;
    } finally {
        file_put_contents($abs, $orig);
    }
}

/* ══ التحقّقُ من الإرجاع ═══════════════════════════════════════════════ */
echo "\n";
list($cz, $fz) = w8_run_gate($PHP, $GATE);
if ($cz === 0) { echo "الإرجاع: البوّابةُ عادت خضراء ✔\n"; }
else { echo "⛔ الإرجاع فاشل — البوّابةُ ما زالت ساقطةً في: " . implode(',', $fz) . "\n"; $blind++; }

/* ⛔ **والأخضرُ لا يكفي دليلًا على الإرجاع**: البوّابةُ تشترط ≥١ ممنوعٍ لكلِّ
     كيانٍ لا العددَ نفسَه، فإرجاعٌ ناقصٌ لصفٍّ من ثلاثةٍ **يمرُّ تحتها**.
     فيُقارَن العددُ الكلّيُّ بما التُقط قبلَ أوّلِ كسر. */
$stNow = (int) $g("SELECT COUNT(*) FROM repair01_w8_states WHERE allowed = 0");
if ($stNow === (int) $snap['stForbidTotal']) {
    echo "الممنوعُ الصريحُ عاد كما كان: $stNow صفًّا ✔\n";
} else {
    printf("⛔ الممنوعُ الصريحُ لم يعد: كان %d وصار %d\n", (int) $snap['stForbidTotal'], $stNow);
    $blind++;
}

printf("\nالفحصُ السلبيّ: %d حالةَ كسرٍ · أعمى %d\n", $done, $blind);
echo ($blind === 0 ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: يوجد حاجبٌ أعمى ✘\n");
exit($blind === 0 ? 0 : 1);
