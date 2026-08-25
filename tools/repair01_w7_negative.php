<?php
/**
 * tools/repair01_w7_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ السابعة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويُطلَب من البوّابةِ أن تسقط — ثمَّ
 *   تُرجَع الحالة. والحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على رمزِ الحاجبِ لا على عبارتِه** (§٦-ب): نصُّ حالةِ الخطأِ يطابق
 *   العبارةَ العربيّةَ فيُخضِرُّ كذبًا — فالالتقاطُ على `✘ W7-nn`.
 *
 * ◆ **وأربعةُ حواجبَ تُكسَر في الشيفرةِ لا في القاعدة** (`W7-08` بنيويًّا و
 *   `W7-09` بنيويًّا): ما يمنعه المخطَّطُ لا يُختبَر، وما تمنعه **بنيةُ دالّةٍ**
 *   لا يُختبَر إلّا بتشويهِ تلك البنيةِ ثمَّ إرجاعِها بايتًا ببايت.
 *
 * ◆ **وأربعةُ حواجبَ بلا صفوفٍ حيّةٍ تُقاس** (`W7-11`…`W7-14`): مقامُها صفرٌ
 *   اليومَ لأنَّ شهاداتِ الرحلةِ تُرجَع مع معاملتِها. **وصفرُ المقامِ يُخضِرُّ
 *   بلا فحص** — فالكسرُ هنا **إدخالُ صفٍّ مخالفٍ عمدًا** ثمَّ نزعُه: بلا ذلك
 *   يبقى أربعةُ حواجبَ خضراءَ لم تُختبَر مرّةً واحدة.
 *
 * التشغيل: php tools/repair01_w7_negative.php
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
    $r = $conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x ? $x[0] : null;
};

$PHP = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w7_gate.php';
$SVC  = $ROOT . '/app/Services/Maintenance/MaintenanceCycleService.php';

/** يشغّل البوّابةَ ويعيد [رمزُ الخروج، أيُّ حاجبٍ سقط] */
function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W7-') !== false && preg_match('/W7-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

/* الأساس: يجب أن تكون خضراءَ قبل البدء */
list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ── قيمٌ تُلتقَط قبل الكسرِ ليكون الإرجاعُ بالقيمةِ لا بالتخمين ────────── */
$svcOrig  = (string) file_get_contents($SVC);
$company  = (int) $one("SELECT company_id FROM equipments GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
$eqId     = (int) $one("SELECT id FROM equipments WHERE company_id = $company ORDER BY id LIMIT 1");
$ordId    = (int) $one("SELECT id FROM mnt_order WHERE company_id = $company AND is_deleted = 0 ORDER BY id LIMIT 1");
$trpOrd   = (int) $one("SELECT id FROM transfer_orders WHERE company_id = $company AND is_deleted = 0 ORDER BY id LIMIT 1");
$scopeReq = (string) $one("SELECT requirement_id FROM repair01_w7_scope WHERE anchor_screen_id <> '' ORDER BY requirement_id LIMIT 1");
$scopeSid = (string) $one("SELECT anchor_screen_id FROM repair01_w7_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$sbSid    = (string) $one("SELECT screen_id FROM repair01_w7_sidebar ORDER BY screen_id LIMIT 1");
$sbS1     = (string) $one("SELECT s1_verdict FROM repair01_w7_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$parentSid = (string) $one("SELECT screen_id FROM repair01_w7_sidebar WHERE s5_verdict <> 'NO_PARENT' LIMIT 1");
$parentS5  = (string) $one("SELECT s5_verdict FROM repair01_w7_sidebar WHERE screen_id = '" . $esc($parentSid) . "'");
$navRoute = (string) $one("SELECT g.route FROM repair01_screen_registry g
                             JOIN nav_items n ON n.route = g.route AND n.active = 1
                            WHERE g.origin = 'W07' AND n.permission_code <> '' LIMIT 1");
$navId    = (int) $one("SELECT id FROM nav_items WHERE route = '" . $esc($navRoute) . "' AND active = 1 LIMIT 1");
$navPerm  = (string) $one("SELECT permission_code FROM nav_items WHERE id = $navId");
$canRoute = (string) $one("SELECT n.route FROM nav_canonical n
                             JOIN repair01_screen_registry g ON g.route = n.route
                            WHERE g.origin = 'W07' LIMIT 1");
$canSid   = (string) $one("SELECT screen_id FROM nav_canonical WHERE route = '" . $esc($canRoute) . "'");
$srId     = (int) $one("SELECT id FROM mnt_safety_rule ORDER BY id LIMIT 1");
$srRule   = (string) $one("SELECT rule_ref FROM mnt_safety_rule WHERE id = $srId");
$moImpRule = (string) $one("SELECT ops_impact_rule FROM mnt_order WHERE id = $ordId");
$eqCert   = $one("SELECT w7_cert_id FROM equipments WHERE id = $eqId");
$thWhy    = (string) $one("SELECT why FROM repair01_w7_thresholds WHERE threshold_key = 'W7_REPEAT_WINDOW_DAYS'");
$thSafety = (string) $one("SELECT value_num FROM repair01_w7_thresholds WHERE threshold_key = 'W7_CERT_VALID_DAYS_SAFETY'");
$stKey    = $conn->query("SELECT entity, from_state, to_state, forbid_reason FROM repair01_w7_states
                           WHERE allowed = 0 ORDER BY entity, from_state LIMIT 1");
$stKey    = $stKey ? $stKey->fetch_assoc() : null;
$sodEnf   = (string) $one("SELECT enforced_by FROM repair01_w7_sod WHERE process_key = 'W7_PART_ISSUE'");
$evCode   = (string) $one("SELECT event_code FROM repair01_events WHERE contract_stage = 'W07' ORDER BY event_code LIMIT 1");
$growSid  = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE origin = 'W07' ORDER BY screen_id LIMIT 1");
$growGuard = (string) $one("SELECT guard_kind FROM repair01_screen_registry WHERE screen_id = '" . $esc($growSid) . "'");
$kpiId    = (int) $one("SELECT id FROM mnt_kpi_period ORDER BY id LIMIT 1");
$kpiRule  = (string) $one("SELECT derivation_rule FROM mnt_kpi_period WHERE id = $kpiId");

if ($company <= 0 || $eqId <= 0 || $ordId <= 0 || $sbSid === '' || $growSid === '' || $kpiId <= 0) {
    echo "✘ أرضيّةٌ ناقصةٌ للكسر — شغّلْ tools/repair01_w7_apply.php أوّلًا\n";
    exit(1);
}

/* مفاتيحُ صفوفِ الكسرِ المُدخَلة — تُنزع بعد كلِّ حالة */
$NEG_CERT_A = 'W7NEG-A';   /* شهادةٌ معتمَدةٌ بصلاحيةٍ مخالفةٍ لحسابِها */
$NEG_CERT_B = 'W7NEG-B';   /* شهادةٌ بتكلفةٍ مخالفةٍ لمشتقِّها */
$FUTURE = (string) $one("SELECT DATE_ADD(CURDATE(), INTERVAL 4000 DAY)");
$TODAY  = (string) $one("SELECT CURDATE()");

/* ── حالاتُ الكسر: [الحاجب، العنوان، الكسر، الإرجاع] ──────────────────── */
$cases = array(
    array('W7-01', 'نزعُ قاعدةِ ربطِ متطلَّب',
        "UPDATE repair01_w7_scope SET map_rule='' WHERE requirement_id='" . $esc($scopeReq) . "'",
        "UPDATE repair01_w7_scope SET map_rule='W7_ROUTE_TOUCHES_TABLE' WHERE requirement_id='" . $esc($scopeReq) . "'"),
    array('W7-02', 'تزييفُ مِرساةٍ في الدفترِ خلافَ المقيس',
        "UPDATE repair01_w7_scope SET anchor_screen_id='SCR-9999' WHERE requirement_id='" . $esc($scopeReq) . "'",
        "UPDATE repair01_w7_scope SET anchor_screen_id='" . $esc($scopeSid) . "' WHERE requirement_id='" . $esc($scopeReq) . "'"),
    array('W7-03', 'تحريفُ عددِ المُعلَنِ في قرارِ المالك',
        "UPDATE repair01_w7_decisions SET scope_rows=scope_rows+1 WHERE decision_id='W7-D-03'",
        "UPDATE repair01_w7_decisions SET scope_rows=scope_rows-1 WHERE decision_id='W7-D-03'"),
    array('W7-04', 'إفراغُ حكمِ خطوةٍ من الخطواتِ السبع',
        "UPDATE repair01_w7_sidebar SET s1_verdict='' WHERE screen_id='" . $esc($sbSid) . "'",
        "UPDATE repair01_w7_sidebar SET s1_verdict='" . $esc($sbS1) . "' WHERE screen_id='" . $esc($sbSid) . "'"),
    array('W7-05', 'نزعُ رمزِ الصلاحيةِ عن بندِ قائمةٍ حيّ',
        "UPDATE nav_items SET permission_code='' WHERE id=$navId",
        "UPDATE nav_items SET permission_code='" . $esc($navPerm) . "' WHERE id=$navId"),
    array('W7-06', 'فكُّ الربطِ بالسجلِّ المعياريّ',
        "UPDATE nav_canonical SET screen_id='SCR-0001' WHERE route='" . $esc($canRoute) . "'",
        "UPDATE nav_canonical SET screen_id='" . $esc($canSid) . "' WHERE route='" . $esc($canRoute) . "'"),
    /* ⚠ **والكسرُ يُختار ممّا لا يمنعه المخطَّط**: `chk_msr_rule` يمنع إفراغَ
         قاعدةِ التصنيفِ في الصفِّ نفسِه، فكسرُها هناك يُردُّ قبل أن يصل البوّابة —
         و«حاجبٌ يفحص ما يضمنه المخطَّطُ أعمى بالبناء». فالكسرُ هنا **تعطيلُ
         السجلِّ كلِّه**: وهو ما لا يمنعه `CHECK` ويُفرِغ التصنيفَ من مصدرِه. */
    array('W7-08', 'تعطيلُ سجلِّ قواعدِ السلامةِ كلِّه',
        "UPDATE mnt_safety_rule SET active=0",
        "UPDATE mnt_safety_rule SET active=1"),
    array('W7-09', 'دمجُ محورَي السلامةِ والأثرِ في قاعدةٍ واحدة',
        "UPDATE mnt_order SET ops_impact_rule=safety_rule_ref WHERE id=$ordId AND safety_rule_ref<>''",
        "UPDATE mnt_order SET ops_impact_rule='" . $esc($moImpRule) . "' WHERE id=$ordId"),
    array('W7-10', 'إعادةُ أصلٍ بشهادةٍ لا وجودَ لها',
        "UPDATE equipments SET w7_cert_id=999999 WHERE id=$eqId",
        "UPDATE equipments SET w7_cert_id=" . ($eqCert === null ? 'NULL' : (int) $eqCert) . " WHERE id=$eqId"),
    /* و`chk_w7th_why` يمنع إفراغَ العذرِ في الصفّ — فالكسرُ **نزعُ الصفِّ كلِّه** */
    array('W7-15', 'نزعُ عتبةٍ من سجلِّ العتبات',
        "DELETE FROM repair01_w7_thresholds WHERE threshold_key='W7_PM_TOLERANCE_HOURS'", null),
    /* و`chk_w7st_forbid` يمنع منعًا بلا سبب — فالكسرُ **نزعُ ممنوعاتِ كيانٍ كلِّها** */
    array('W7-16', 'كيانٌ بلا انتقالٍ ممنوعٍ صراحةً', '__STATES_DROP__', '__STATES_RESTORE__'),
    array('W7-17', 'تركيبةٌ ممنوعةٌ برمزِ ردٍّ لا وجودَ له في الشيفرة',
        "UPDATE repair01_w7_sod SET enforced_by='W7_CODE_THAT_DOES_NOT_EXIST' WHERE process_key='W7_PART_ISSUE'",
        "UPDATE repair01_w7_sod SET enforced_by='" . $esc($sodEnf) . "' WHERE process_key='W7_PART_ISSUE'"),
    array('W7-18', 'إفراغُ عقدِ أثرٍ من مستهلكيه',
        "UPDATE repair01_events SET consumer_list='' WHERE event_code='" . $esc($evCode) . "' AND contract_stage='W07'",
        null /* يُرجَع بإعادةِ تشغيلِ الأداة */),
    array('W7-19', 'نموٌّ في السجلِّ بلا ختمِ موجة',
        "INSERT INTO repair01_screen_registry (screen_id, screen_file, route, route_rule, owner_code, owner_role,
            owner_rule, lifecycle, lifecycle_rule, parent_screen_id, parent_rule, visibility_class, visibility_rule,
            on_disk, origin, guard_kind, guard_evidence, w2_why, src_ref)
         VALUES ('SCR-9990','neg.php','Maintenance/w7_negative_probe.php','NEG','DEP-14','x','NEG',
                 'LIVE_UNREGISTERED','NEG','','','MENU_ITEM','NEG',1,'NEG','SHELL','NEG','NEG','NEG')",
        "DELETE FROM repair01_screen_registry WHERE screen_id='SCR-9990'"),
    array('W7-20', 'سطحُ نموٍّ بحارسٍ يخالف المقيسَ من القرص',
        "UPDATE repair01_screen_registry SET guard_kind='NONE' WHERE screen_id='" . $esc($growSid) . "'",
        "UPDATE repair01_screen_registry SET guard_kind='" . $esc($growGuard) . "' WHERE screen_id='" . $esc($growSid) . "'"),
    /* ⚠ **ومُعرِّفُ السطرِ يُقرأ لحظةَ الكسرِ لا لحظةَ التركيب**: إرجاعُ `W7-18`
         يعيد تشغيلَ الأداةِ فتُعاد بناءُ `mnt_kpi_period` كلِّها بمُعرِّفاتٍ جديدة،
         فيُصيب الكسرُ صفرَ صفوفٍ وتبقى البوّابةُ خضراءَ على كسرٍ لم يقع. */
    array('W7-22', 'مؤشِّرٌ مشتقٌّ بلا قاعدةِ اشتقاق', '__KPI_BREAK__', '__KPI_RESTORE__'),

    /* ── حواجبُ مقامُها صفرٌ اليوم — تُكسَر بإدخالِ صفٍّ مخالفٍ عمدًا ──── */
    array('W7-11', 'شهادةٌ معتمَدةٌ بصلاحيةٍ تخالف حسابَها',
        "INSERT INTO mnt_return_cert (company_id, cert_no, order_id, equipment_id, safety_severity,
            cert_required, cert_rule, tech_complete_date, test_performed, test_result, downtime_hours,
            actual_cost, cost_rule, new_readiness_state, valid_days, valid_until, signer_kind,
            state, state_rule, approved_by, approved_at, src_ref)
         VALUES ($company,'$NEG_CERT_A',$ordId,$eqId,'major',1,'NEG','$TODAY','فحص سلبي','pass',0,
                 0,'NEG','operational',999,'$FUTURE','technician','approved','NEG',1,NOW(),'W7NEG')",
        "DELETE FROM mnt_return_cert WHERE cert_no='$NEG_CERT_A'"),
    array('W7-12', 'شهادةٌ بتكلفةٍ تخالف مشتقَّها',
        "INSERT INTO mnt_return_cert (company_id, cert_no, order_id, equipment_id, safety_severity,
            cert_required, cert_rule, test_performed, downtime_hours, actual_cost, cost_rule,
            valid_days, state, state_rule, src_ref)
         VALUES ($company,'$NEG_CERT_B',$ordId,$eqId,'major',1,'NEG','فحص سلبي',0,999999,'NEG',
                 0,'draft','NEG','W7NEG')",
        "DELETE FROM mnt_return_cert WHERE cert_no='$NEG_CERT_B'"),
    array('W7-13', 'واقعةُ تكرارٍ تخالف صلاحيةَ شهادتِها',
        null /* يُركَّب أدناه لأنّه يحتاج مُعرِّفَ الشهادةِ المُدخَلة */,
        null),
    array('W7-14', 'إقفالُ ترحيلٍ بتكلفةٍ تخالف بنودَه',
        "INSERT INTO trp_closure (company_id, order_id, cost_lines_count, total_cost, bearer_split,
            meter_posted, state, state_rule, derivation_rule, src_ref)
         VALUES ($company," . ($trpOrd > 0 ? $trpOrd : 0) . ",7,987654,'NEG',0,'draft','NEG','NEG','W7NEG')",
        "DELETE FROM trp_closure WHERE src_ref='W7NEG'"),

    /* ── حاجبانِ بنيويّانِ يُكسَرانِ في الشيفرةِ لا في القاعدة ───────────── */
    array('W7-08', 'قائمةُ أنظمةٍ صلبةٌ في شيفرةِ الخدمة (بنيويّ)', '__SVC_HARDLIST__', '__SVC_RESTORE__'),
    array('W7-09', 'محورُ السلامةِ يقرأ ساعاتِ التوقّف (بنيويّ)', '__SVC_DOWNTIME__', '__SVC_RESTORE__'),

    /* ── ورحلةُ الإثباتِ نفسُها: تُكسَر بنزعِ العتبةِ التي تحتاجها ───────── */
    array('W7-21', 'رحلةُ §18 لا تنعقد بلا عتبةِ صلاحيةٍ مسجَّلة',
        "DELETE FROM repair01_w7_thresholds WHERE threshold_key='W7_CERT_VALID_DAYS_SAFETY'",
        "INSERT INTO repair01_w7_thresholds (threshold_key, value_num, unit_ar, title_ar, why, decision_ref, src_ref)
         VALUES ('W7_CERT_VALID_DAYS_SAFETY'," . (float) $thSafety . ",'يوم',
                 'صلاحية شهادة العودة للعطل الحرج للسلامة',
                 'التكرار خلال الصلاحية يفتح تحليل السبب الجذري — والنافذة تضبط هنا ولا تكتب في الشيفرة',
                 'DEC-OPEN-12','RPR-W07 §٥ · عتبة من السجل')"),
);

$blind = 0; $done = 0; $skipped = 0;
foreach ($cases as $c) {
    list($want, $title, $break, $restore) = $c;

    /* ⓐ حالةُ W7-13 تحتاج شهادةً معتمَدةً قائمةً — تُركَّب هنا وتُنزع معها */
    if ($want === 'W7-13') {
        $conn->query("INSERT INTO mnt_return_cert (company_id, cert_no, order_id, equipment_id, safety_severity,
            cert_required, cert_rule, tech_complete_date, test_performed, test_result, downtime_hours,
            actual_cost, cost_rule, new_readiness_state, valid_days, valid_until, signer_kind,
            state, state_rule, approved_by, approved_at, src_ref)
            VALUES ($company,'W7NEG-C',$ordId,$eqId,'major',1,'NEG','$TODAY','فحص سلبي','pass',0,
                    0,'NEG','operational',60,'$FUTURE','technician','approved','NEG',1,NOW(),'W7NEG')");
        $cid = (int) $one("SELECT id FROM mnt_return_cert WHERE cert_no='W7NEG-C'");
        if ($cid <= 0) { printf("  ⚠ %-8s تعذّر تركيبُ الأرضيّة\n", $want); $skipped++; continue; }
        /* الشهادةُ صالحةٌ حتى `$FUTURE`، والتكرارُ اليومَ ⇒ المقيسُ «ضمنَ الصلاحية»=1
           والمخزَّنُ 0 — فالحاجبُ يجب أن يسقط. */
        $break = "INSERT INTO mnt_repeat_repair (company_id, equipment_id, origin_order_id, origin_cert_id,
                    tree_node, repeat_date, days_since_cert, within_validity, rca_trigger, rca_state,
                    derivation_rule, src_ref)
                  VALUES ($company,$eqId,$ordId,$cid,'NEG','$TODAY',NULL,0,'manual','open','NEG','W7NEG')";
        $restore = "DELETE FROM mnt_repeat_repair WHERE src_ref='W7NEG'";
    }

    /* ⓐ-٢ كسرٌ يُركَّب لحظةَ التنفيذِ لا لحظةَ تعريفِ الحالات */
    if ($break === '__KPI_BREAK__') {
        $kid = (int) $one("SELECT id FROM mnt_kpi_period ORDER BY id LIMIT 1");
        $krule = (string) $one("SELECT derivation_rule FROM mnt_kpi_period WHERE id = $kid");
        if ($kid <= 0) { printf("  ⚠ %-8s لا سطرَ مؤشِّرٍ يُكسَر\n", $want); $skipped++; continue; }
        /* `chk_mkp_rule` يمنع الإفراغ — فالكسرُ **نزعُ السطرِ المشتقّ** */
        $break = "DELETE FROM mnt_kpi_period WHERE id = $kid";
        $restore = null;
    }
    if ($break === '__STATES_DROP__') {
        if (!$stKey) { printf("  ⚠ %-8s لا انتقالَ ممنوعٌ يُنزَع\n", $want); $skipped++; continue; }
        $ent = $esc($stKey['entity']);
        $keep = array();
        $rr = $conn->query("SELECT * FROM repair01_w7_states WHERE entity = '$ent' AND allowed = 0");
        while ($rr && $xx = $rr->fetch_assoc()) { $keep[] = $xx; }
        $break = "DELETE FROM repair01_w7_states WHERE entity = '$ent' AND allowed = 0";
        $restore = $keep;
    }

    /* ⓑ الكسرُ البنيويُّ في ملفِّ الخدمة — نسخةٌ احتياطيّةٌ بايتًا ببايت */
    $isSvc = ($break === '__SVC_HARDLIST__' || $break === '__SVC_DOWNTIME__');
    if ($isSvc) {
        $mod = $svcOrig;
        if ($break === '__SVC_HARDLIST__') {
            $mod = str_replace("    const CERT_FLOW = array('draft', 'submitted', 'approved');",
                "    const CERT_FLOW = array('draft', 'submitted', 'approved');\n"
                . "    const W7_NEG_SYSTEMS = array('brakes', 'steering', 'lifting', 'estop', 'structural');",
                $mod);
        } else {
            $mod = str_replace("        \$systemKey = trim((string) \$systemKey);",
                "        \$systemKey = trim((string) \$systemKey);\n"
                . "        \$w7NegDowntime = 0; /* downtime */",
                $mod);
        }
        if ($mod === $svcOrig) { printf("  ⚠ %-8s تعذّر الكسرُ البنيويّ (لم يتغيّر الملفّ)\n", $want); $skipped++; continue; }
        file_put_contents($SVC, $mod);
    } elseif ($break === null || $break === '') {
        printf("  ⚠ %-8s لا كسرَ مُركَّب\n", $want); $skipped++; continue;
    } elseif ($conn->query($break) === false) {
        printf("  ⚠ %-8s تعذّر الكسر: %s\n", $want, $conn->error); $skipped++; continue;
    }

    list($code, $failed) = run_gate($PHP, $GATE);
    $caught = in_array($want, $failed, true);
    if ($caught) { printf("  ✔ %-8s %-52s سقطت كما يجب\n", $want, $title); }
    else { $blind++; printf("  ✘ %-8s %-52s **لم تسقط** — الحاجبُ أعمى (الساقط: %s)\n",
                            $want, $title, $failed ? implode(',', $failed) : 'لا شيء'); }

    /* ⓒ الإرجاع */
    if ($isSvc) {
        file_put_contents($SVC, $svcOrig);
        if ((string) file_get_contents($SVC) !== $svcOrig) { printf("  ⛔ %-8s فشلَ إرجاعُ الملفّ\n", $want); $blind++; }
    } elseif ($want === 'W7-18') {
        /* عقدُ الأثرِ يُرجَع بإعادةِ تشغيلِ الأداةِ التي كتبته */
        exec('"' . $PHP . '" "' . $ROOT . '/tools/repair01_w7_apply.php" 2>&1', $o2, $c2);
    } elseif ($want === 'W7-13') {
        $conn->query($restore);
        $conn->query("DELETE FROM mnt_return_cert WHERE cert_no='W7NEG-C'");
    } elseif ($want === 'W7-16' && is_array($restore)) {
        foreach ($restore as $row) {
            $cols = array(); $vals = array();
            foreach ($row as $k => $v) { $cols[] = '`' . $k . '`'; $vals[] = "'" . $esc($v) . "'"; }
            if ($conn->query('INSERT INTO repair01_w7_states (' . implode(',', $cols) . ') VALUES ('
                             . implode(',', $vals) . ')') === false) {
                printf("  ⛔ %-8s فشلَ إرجاعُ صفِّ حالة: %s\n", $want, $conn->error); $blind++;
            }
        }
    } elseif (in_array($want, array('W7-15', 'W7-22'), true)) {
        /* السجلّانِ المشتقّانِ يُعادانِ ببناءِ أداتِهما — لا بصفٍّ مكتوبٍ يدًا */
        exec('"' . $PHP . '" "' . $ROOT . '/tools/repair01_w7_apply.php" 2>&1', $o3, $c3);
        if ($want === 'W7-15') {
            exec('"' . $PHP . '" "' . $ROOT . '/database/migrations/2027_11_24_repair01_w7_mnt_trp.php" 2>&1', $o4, $c4);
        }
    } elseif ($restore !== null && is_string($restore) && $conn->query($restore) === false) {
        printf("  ⛔ %-8s فشلَ الإرجاع: %s\n", $want, $conn->error); $blind++;
    }
    $done++;
}

/* ── التحقّقُ من الإرجاعِ بإعادةِ تشغيلِ البوّابة ─────────────────────── */
echo "\n";
list($cz, $fz) = run_gate($PHP, $GATE);
if ($cz === 0) { echo "الإرجاع: البوّابةُ عادت خضراء ✔\n"; }
else { echo "⛔ الإرجاع فاشل — البوّابةُ ما زالت ساقطةً في: " . implode(',', $fz) . "\n"; $blind++; }

/* ولا يبقى صفُّ كسرٍ واحد */
$leftover = (int) $one("SELECT (SELECT COUNT(*) FROM mnt_return_cert WHERE src_ref='W7NEG')
                             + (SELECT COUNT(*) FROM mnt_repeat_repair WHERE src_ref='W7NEG')
                             + (SELECT COUNT(*) FROM trp_closure WHERE src_ref='W7NEG')
                             + (SELECT COUNT(*) FROM repair01_screen_registry WHERE origin='NEG')");
if ($leftover > 0) { echo "⛔ بقيَ $leftover صفَّ كسرٍ لم يُنزَع\n"; $blind++; }
else { echo "النظافة: لا صفَّ كسرٍ باقٍ ✔\n"; }

printf("\nالفحصُ السلبيّ: %d حاجبًا مُختبَرًا · مُتخطًّى %d · أعمى %d\n", $done, $skipped, $blind);
echo ($blind === 0 && $skipped === 0 ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: يوجد حاجبٌ أعمى أو غيرُ مُختبَر ✘\n");
exit(($blind === 0 && $skipped === 0) ? 0 : 1);
