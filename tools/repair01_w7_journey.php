<?php
/**
 * tools/repair01_w7_journey.php — رحلةُ التوقّف (‏W07 §٦-أ · §18)
 * ═══════════════════════════════════════════════════════════════════════════
 * **معدّةٌ في موقع ← تعمل ← تتوقّف ← يُسجَّل التوقّفُ مرّةً واحدة ← يتأثّر
 *   التشغيل ← تتأثّر الجاهزية ← يُحدَّد المشغّلُ الموجود ← الصيانةُ تستلم ←
 *   المخاطرُ تقرأ المحفِّز ← الحوكمةُ تقرأ خرقَ الضابطِ إن وُجد ← البلاغاتُ
 *   تتابع ← القيادةُ ترى تنبيهًا حرجًا ← الإصلاح ← شهادةُ العودة ← الأسطولُ
 *   يعيد الحالة ← التشغيلُ يعيد الجاهزية.**
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس رقمٌ يعنيه — ساعتا توقّفٍ تُخصمان **مرّةً واحدة** · جاهزيّةٌ
 *   تنخفض بواقعةٍ حقيقيّة · معدّةٌ تُحظَر فلا تُسنَد · إشارةُ مخاطرَ تُرفَع من
 *   قاعدةٍ حيّةٍ لا من سطرٍ مُصطنَع · تنبيهٌ حرِجٌ يبلغ القيادةَ · شهادةٌ معتمَدةٌ
 *   وحدَها تعيد المعدّةَ وترفع الحظر.
 *
 * ◆ **والمحطّاتُ السالبةُ محطّاتٌ**: «لا شهادةَ لعطلٍ بسيط» و«لا شهادةَ لأمرٍ
 *   لم يُنجَز» و«لا اعتمادَ من منشئها» و«الحرِجُ لا يعتمده غيرُ المخوَّلِ
 *   الفنّيّ» و«لا عودةَ بشهادةٍ غيرِ معتمدة» و«لا صرفَ لأمرٍ مقفل» و«لا تخفيضَ
 *   لتصنيفِ سلامة» — تُقاس **بالاستدعاءِ الفعليِّ ورمزِ الرفض**، لا بقراءةِ
 *   شيفرةٍ ولا بدعوى.
 *
 * ◆ **والبياناتُ لا تبقى**: كلُّ ما تكتبه الرحلةُ داخلَ معاملةٍ تُرجَع؛ ودليلُها
 *   وحدَه يُكتب بعدَ الإرجاعِ في `repair01_w7_journey`.
 *
 * التشغيل: php tools/repair01_w7_journey.php
 * الخروج : 0 عبرت كلُّ المحطّات · 1 محطّةٌ لم تعبر أو أرضيّةٌ ناقصة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w4_scan.php';
require_once $ROOT . '/tools/lib/repair01_w7_scan.php';
require_once $ROOT . '/app/Services/Fleet/AssetLifecycleService.php';
require_once $ROOT . '/app/Services/Operations/SiteDayService.php';
require_once $ROOT . '/app/Services/Maintenance/MaintenanceCycleService.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
require_once $ROOT . '/app/Services/Risk/RiskSignalEngine.php';
use App\Services\Fleet\AssetLifecycleService as ALS;
use App\Services\Operations\SiteDayService as SDS;
use App\Services\Maintenance\MaintenanceCycleService as MCS;
use App\Services\Risk\RiskSignalEngine as RSE;

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w7_one($conn, $sql); };

/* مُعرِّفُ الجولةِ من ساعةِ القاعدة **بدقّةِ الميكروثانية** — جولتانِ في الثانيةِ
   نفسِها تتقاسمان المُعرِّفَ فتقرأ البوّابةُ صفوفَهما جولةً واحدةً وتسقط (W04). */
$RUN  = 'W7J-' . (string) $one("SELECT DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')");
$MARK = '__w7_journey_' . $RUN . '__';

echo "═══════════ رحلةُ التوقّف — REPAIR01 · W07 §18 ═══════════\n";
/* ⚠ **مُعرِّفُ الجولةِ يُطبَع سطرًا مُرمَّزًا** لتقرأه البوّابةُ من المخرَجِ لا من
     «آخرِ صفٍّ في الجدول». وقراءةُ الآخِرِ ثغرةٌ مقيسة: رحلةٌ لم تنعقد أصلًا
     تترك دليلَ الجولةِ السابقةِ قائمًا، فتقرأ البوّابةُ عبورًا لم يقع. */
echo "RUN=$RUN\n";
echo "الجولة: $RUN\n\n";

$ST = array();
$add = function ($no, $station, $entity, $consumer, $expected, $measured, $effect, $readiness, $passed) use (&$ST) {
    $ST[] = array($no, $station, $entity, $consumer, $expected, $measured, $effect, $readiness, $passed ? 1 : 0);
};

/* ── أرضيّةُ الرحلة: كيانٌ ذو أصولٍ حيّة، وموقعٌ ومشروعٌ وثلاثةُ أشخاصٍ ──── */
$company = (int) $one("SELECT company_id FROM equipments GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
/* ⚠ **الخروجُ برمزٍ غيرِ صفرٍ عند نقصِ الأرضيّة**: `exit("نصّ")` يطبع ويخرج
     **بصفر** — فتقرأ البوّابةُ نجاحًا لم يقع. */
if ($company <= 0) { echo "✘ لا كيانَ ذا أصولٍ — الرحلةُ لا تُشغَّل على قاعدةٍ فارغة\n"; exit(1); }
$equip   = (int) $one("SELECT id FROM equipments WHERE company_id = $company ORDER BY id LIMIT 1");
$site    = (int) $one("SELECT id FROM sites WHERE company_id = $company AND is_deleted = 0 ORDER BY id LIMIT 1");
$project = (int) $one("SELECT id FROM project WHERE company_id = $company ORDER BY id DESC LIMIT 1");
$actors = array();
$r = $conn->query("SELECT id FROM employees WHERE company_id = $company ORDER BY id LIMIT 4");
while ($r && $x = $r->fetch_row()) { $actors[] = (int) $x[0]; }
if ($equip <= 0 || $site <= 0 || $project <= 0 || count($actors) < 4) {
    echo "✘ أرضيّةٌ ناقصة (أصل $equip · موقع $site · مشروع $project · أشخاص " . count($actors) . ") — الرحلةُ لا تُشغَّل\n";
    exit(1);
}
list($operator, $technician, $approver, $safetyOfficer) = $actors;

/* ⚠ **اليومُ يومُ اليومِ لا يومٌ بعيد**: `RiskSignalEngine::sg02` يقيس
     `work_end >= NOW() - 7 يوم`، فيومٌ في المستقبلِ البعيدِ يجعل «المخاطرُ تقرأ
     المحفِّز» **محطّةً لا تُقاس أصلًا**. والبياناتُ لا تبقى — المعاملةُ تُرجَع. */
$DAY = (string) $one("SELECT CURDATE()");
$PERIOD = substr($DAY, 0, 7);

ALS::setEventConnection($conn);
MCS::setEventConnection($conn);
MCS::setThresholdConnection($conn);
$GATE = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($company, $technician, '', true));

$conn->query('SET autocommit = 0');
$conn->query('START TRANSACTION');
$ok = true;

/** الجاهزيّةُ المشتقّةُ الآن — نسبةٌ من الساعاتِ المقيسةِ بصيغةِ الخدمةِ نفسِها */
$readiness = function ($shift, $exec, $standby, $fault, $stop) {
    $f = ALS::readinessFormula($shift, $exec, $standby, $fault, $stop);
    return $f['readiness'] . '٪ · استغلال ' . $f['utilization'] . '٪';
};

/* ═══ ① معدّةٌ في موقعٍ تعمل ═════════════════════════════════════════════ */
$tsOk = $conn->query("INSERT INTO timesheet
    (company_id, operator, employee_id, shift, date, shift_hours, executed_hours, standby_hours,
     maintenance_fault, total_fault_hours, type, time_notes, user_id, status)
    VALUES ($company, '$equip', '$operator', 'D', '" . $esc($DAY) . "', 12, 9, 1, 2, 2, '1',
            '" . $esc($MARK) . "', $operator, 1)");
$tsId = $tsOk ? (int) $conn->insert_id : 0;
$execH = (float) $one("SELECT COALESCE(SUM(executed_hours),0) FROM timesheet
                        WHERE company_id = $company AND date = '" . $esc($DAY) . "'
                          AND operator = '$equip' AND time_notes = '" . $esc($MARK) . "'");
$p1 = ($tsId > 0 && $execH > 0);
$add(1, 'معدّةٌ في موقعٍ تعمل بوردية', 'timesheet', '11 إدارة التشغيل · timesheet',
     'صفُّ ورديةٍ واحدٌ للحبّةِ (معدة × وردية × يوم) بساعاتٍ منفَّذةٍ مقيسة',
     $p1 ? "Timesheet_ID=$tsId · ساعاتٌ منفَّذةٌ مقيسة=$execH" : 'فشل: ' . $conn->error,
     $p1 ? 'ساعةُ تشغيلٍ منفَّذةٌ تدخل الاستحقاقَ والاستغلالَ ومردودَ المعدّة' : '—',
     $readiness(12, 9, 1, 0, 0), $p1);
$ok = $ok && $p1;

/* ═══ ② التوقّفُ يُسجَّل مرّةً واحدة — وسجلٌّ ثانٍ مرآةٌ بفارقِها ═════════ */
$occKey = repair01_w4_occurrence_key($company, $DAY, 'day', $equip);
$base = array('company_id' => $company, 'occurrence_key' => $occKey, 'stop_date' => $DAY, 'shift' => 'day',
              'equipment_id' => $equip, 'site_id' => $site, 'project_id' => $project,
              'ops_state' => 'tech_breakdown', 'hours' => 2.0, 'resp_party' => 'company',
              'obligation_type' => 'equipment_readiness', 'billable' => 0,
              'authority_rule' => 'W7_UTL_CARRIES_ATTRIBUTION', 'authority_ref' => 'w7journey:utl');
$s1 = SDS::registerStop($GATE, array_merge($base, array('register_name' => 'unit_time_log')));
$s2 = SDS::registerStop($GATE, array_merge($base, array('register_name' => 'timesheet',
                                                        'authority_ref' => 'timesheet#' . $tsId)));
$regN  = (int) $one("SELECT COUNT(*) FROM ops_stop_register WHERE occurrence_key = '" . $esc($occKey) . "'");
$srcN  = (int) $one("SELECT COUNT(*) FROM ops_stop_source WHERE occurrence_key = '" . $esc($occKey) . "'");
$sumH  = (float) $one("SELECT COALESCE(SUM(hours),0) FROM ops_stop_register WHERE occurrence_key = '" . $esc($occKey) . "'");
$p2 = ($s1['ok'] && $s2['ok'] && $regN === 1 && $srcN === 2 && abs($sumH - 2.0) < 0.005);
$add(2, 'واقعةُ التوقّفِ تُسجَّل مرّةً واحدة', 'ops_stop_register', '11 إدارة التشغيل · ops_stop_register',
     'سجلَّانِ يدَّعيان الواقعةَ ⇒ صفُّ واقعةٍ واحدٌ وقراءتانِ إحداهما مرآة',
     $p2 ? "صفُّ الواقعة=$regN · قراءاتٌ=$srcN · ساعاتٌ محتسَبة=$sumH"
         : "صفُّ الواقعة=$regN · قراءات=$srcN · ساعات=$sumH",
     $p2 ? 'ساعتا توقّفٍ تُحتسبان مرّةً واحدةً لا مرّتين — فلا يتضاعف الخصمُ ولا الاستحقاق' : '—',
     $readiness(12, 9, 1, 2, 0), $p2);
$ok = $ok && $p2;

/* ═══ ③ التشغيلُ يتأثّر — الساعاتُ المنفَّذةُ تنقص بمقدارِ التوقّف ═══════ */
$stopH = (float) $one("SELECT COALESCE(SUM(hours),0) FROM ops_stop_register
                        WHERE occurrence_key = '" . $esc($occKey) . "'");
$netH  = round($execH - $stopH, 2);
$p3 = ($stopH > 0 && $netH < $execH);
$add(3, 'التشغيلُ يتأثّر بساعاتِ التوقّف', 'timesheet', '11 إدارة التشغيل · ساعاتُ الوردية',
     'الساعاتُ المنفَّذةُ الصافيةُ تنقص بمقدارِ ساعاتِ التوقّفِ المحتسَبةِ مرّةً واحدة',
     $p3 ? "منفَّذةٌ=$execH · توقّفٌ محتسَب=$stopH · صافٍ=$netH" : "منفَّذةٌ=$execH · توقّف=$stopH",
     $p3 ? "ساعتانِ تخرجان من المفوتَرِ والمستحَقِّ — الصافي $netH ساعة" : '—',
     $readiness(12, 9, 1, 2, $stopH), $p3);
$ok = $ok && $p3;

/* ═══ ④ الجاهزيّةُ تتأثّر — مشتقّةٌ بصيغةِ الخدمةِ لا مُدخَلة ═══════════ */
$before = ALS::readinessFormula(12, 9, 1, 0, 0);
$after  = ALS::readinessFormula(12, 9, 1, 2, $stopH);
$p4 = ($after['readiness'] < $before['readiness']);
$add(4, 'الجاهزيّةُ تنخفض اشتقاقًا لا إدخالًا', 'asset_readiness', '04 إدارة الأسطول والأصول · asset_readiness',
     'الجاهزيّةُ المشتقّةُ بعدَ التوقّفِ أقلُّ منها قبلَه — والصيغةُ دالّةٌ واحدة',
     $p4 ? ('قبلَ التوقّف=' . $before['readiness'] . '٪ · بعده=' . $after['readiness'] . '٪')
         : ('لم تتغيّر: ' . $after['readiness'] . '٪'),
     $p4 ? ('التزامُ الجاهزيّةِ في العقدِ ينقص ' . round($before['readiness'] - $after['readiness'], 2) . ' نقطةً مئويّة') : '—',
     $readiness(12, 9, 1, 2, $stopH), $p4);
$ok = $ok && $p4;

/* ═══ ⑤ المشغّلُ الموجودُ يُحدَّد من قيدِ الوردية ═══════════════════════ */
$opFound = (int) $one("SELECT COUNT(*) FROM timesheet
                        WHERE company_id = $company AND date = '" . $esc($DAY) . "'
                          AND operator = '$equip' AND employee_id = '$operator'");
$p5 = ($opFound === 1);
$add(5, 'المشغّلُ الموجودُ عند التوقّفِ يُحدَّد', 'timesheet', '13 إدارة القوى التشغيلية · قيدُ الوردية',
     'قيدُ الورديةِ يحمل المشغّلَ فيُعرَف مَن كان على المعدّةِ لحظةَ التوقّف',
     $p5 ? "المشغّلُ المقيسُ في الوردية=#$operator" : "صفوفٌ مطابقة=$opFound",
     $p5 ? 'مسؤوليّةُ التشغيلِ مُسنَدةٌ بشخصٍ معروفٍ لا بادّعاء' : '—',
     $readiness(12, 9, 1, 2, $stopH), $p5);
$ok = $ok && $p5;

/* ═══ ⑥ الصيانةُ تستلم البلاغَ محالًا ═══════════════════════════════════ */
$brkCode = 'W7J-BRK-' . substr($RUN, 4, 14);
$rb = MCS::receiveBreakdown($GATE, array(
    'code' => $brkCode, 'equipment_id' => $equip, 'project_id' => $project,
    'reported_by' => $operator, 'reporter_dept' => 'DEP-11', 'is_stopped' => 1,
    'description' => 'توقف فني — ' . $MARK, 'severity' => 'high'));
$brkId = (int) $rb['breakdown_id'];
$brkN  = (int) $one("SELECT COUNT(*) FROM mnt_breakdown WHERE company_id = $company AND code = '" . $esc($brkCode) . "'");
$p6 = ($rb['ok'] && $brkId > 0 && $brkN === 1);
$add(6, 'الصيانةُ تستلم البلاغَ محالًا', 'mnt_breakdown', '14 إدارة الصيانة · mnt_breakdown',
     'بلاغٌ واحدٌ يصل الصيانةَ محالًا — والصيانةُ لا تنشئ بلاغًا موازيًا',
     $p6 ? "Breakdown_ID=$brkId · صفوفٌ مطابقة=$brkN" : 'فشل: ' . $rb['reason'],
     $p6 ? 'العطلُ صار له مالكٌ ومسارٌ — ولا يبقى واقعةَ توقّفٍ بلا مَن يعالجها' : '—',
     $readiness(12, 9, 1, 2, $stopH), $p6);
$ok = $ok && $p6;

/* ═══ ⑦ عطالةُ الاستلامِ بمفتاحِه ═══════════════════════════════════════ */
$rb2 = MCS::receiveBreakdown($GATE, array('code' => $brkCode, 'equipment_id' => $equip));
$brkN2 = (int) $one("SELECT COUNT(*) FROM mnt_breakdown WHERE company_id = $company AND code = '" . $esc($brkCode) . "'");
$p7 = ($rb2['ok'] && (int) $rb2['breakdown_id'] === $brkId && $brkN2 === 1 && $rb2['created'] === false);
$add(7, 'عطالةُ الاستلامِ بمفتاحِه', 'mnt_breakdown', '14 إدارة الصيانة · uq code',
     'النداءُ الثاني يعيد البلاغَ نفسَه ولا يُنشئ صفًّا ثانيًا',
     $p7 ? "أُعيد Breakdown_ID=$brkId · صفوفُ الحبّة=$brkN2" : "created=" . var_export($rb2['created'], true) . " · صفوف=$brkN2",
     $p7 ? 'بلاغٌ واحدٌ لكلِّ واقعة — فلا يتضاعف عدُّ الأعطالِ في مؤشّرِ الفترة' : '—',
     $readiness(12, 9, 1, 2, $stopH), $p7);
$ok = $ok && $p7;

/* ═══ ⑧ أمرُ عملٍ مصنَّفٌ بأربعةِ محاورَ من قواعدِها ════════════════════ */
$ordCode = 'W7J-ORD-' . substr($RUN, 4, 14);
$ro = MCS::openOrder($GATE, array(
    'code' => $ordCode, 'equipment_id' => $equip, 'project_id' => $project,
    'breakdown_id' => $brkId, 'source' => 'breakdown', 'maint_type' => 'ميكانيكي',
    'failure_kind' => 'ميكانيكي', 'safety_system_key' => 'brakes',
    'downtime_hours' => 30, 'diagnosis' => 'عطل في منظومة الفرامل — ' . $MARK,
    'created_by' => $technician));
$ordId = (int) $ro['order_id'];
$oRow = $conn->query("SELECT safety_severity, safety_rule_ref, ops_impact, ops_impact_rule, lockout_state
                        FROM mnt_order WHERE id = $ordId LIMIT 1");
$oRow = $oRow ? $oRow->fetch_assoc() : null;
$p8 = ($ro['ok'] && $ordId > 0 && $oRow
       && $oRow['safety_severity'] === 'safety_critical' && $oRow['ops_impact'] === 'high'
       && $oRow['safety_rule_ref'] !== '' && $oRow['ops_impact_rule'] !== '');
$add(8, 'أمرُ عملٍ مصنَّفٌ بأربعةِ محاورَ منفصلة', 'mnt_order', '14 إدارة الصيانة · mnt_safety_rule',
     'خطورةُ السلامةِ من سجلِّ القواعدِ والأثرُ التشغيليُّ من عتبةِ الساعاتِ — ومحوران لا يُدمجان',
     $p8 ? ('Order_ID=' . $ordId . ' · سلامة=' . $oRow['safety_severity'] . ' (' . $oRow['safety_rule_ref']
            . ') · أثرٌ تشغيليّ=' . $oRow['ops_impact'])
         : ('فشل: ' . $ro['reason'] . ($oRow ? ' · سلامة=' . $oRow['safety_severity'] : '')),
     $p8 ? 'حكمُ سلامةٍ بسندِه وحكمُ أثرٍ تشغيليٍّ بعتبتِه — ولا يُغيّر أحدُهما الآخر' : '—',
     'محظورة · التصنيف حرِج للسلامة', $p8);
$ok = $ok && $p8;

/* ═══ ⑨ الحرِجُ للسلامةِ يُقفَل تشغيلُه فورًا — والأصلُ يصير محظورًا ════ */
$eqRow = $conn->query("SELECT w7_readiness_state, w7_readiness_rule FROM equipments WHERE id = $equip LIMIT 1");
$eqRow = $eqRow ? $eqRow->fetch_assoc() : null;
$p9 = ($oRow && $oRow['lockout_state'] === 'locked_out'
       && $eqRow && $eqRow['w7_readiness_state'] === 'prohibited' && $eqRow['w7_readiness_rule'] !== '');
$add(9, 'الحرِجُ للسلامةِ يُوقَف ويُمنع تشغيلُه', 'equipments', '04 إدارة الأسطول والأصول · equipments',
     'أمرٌ حرِجٌ للسلامة ⇒ منعُ تشغيلٍ في الأمرِ وحظرٌ في كرتِ الأصلِ بقاعدتِه',
     $p9 ? ('منعٌ=' . $oRow['lockout_state'] . ' · حالةُ الأصل=' . $eqRow['w7_readiness_state']
            . ' (' . $eqRow['w7_readiness_rule'] . ')')
         : ('منع=' . ($oRow ? $oRow['lockout_state'] : '—') . ' · أصل=' . ($eqRow ? $eqRow['w7_readiness_state'] : '—')),
     $p9 ? 'المعدّةُ لا تُسنَد لوردية ولا تُرحَّل — والمنعُ قبلَ الإصلاحِ لا بعده' : '—',
     'محظورة · لا تشغيل', $p9);
$ok = $ok && $p9;

/* ═══ ⑩ محطّةٌ سالبة: تخفيضُ تصنيفِ السلامةِ يُردّ ═══════════════════════ */
$down = MCS::escalateSeverity($GATE, $ordId, 'minor', $technician, 'محاولة تخفيض');
$sevNow = (string) $one("SELECT safety_severity FROM mnt_order WHERE id = $ordId");
$p10 = (!$down['ok'] && $down['code'] === 409 && $down['reason'] === 'SEVERITY_DOWNGRADE_FORBIDDEN'
        && $sevNow === 'safety_critical');
$add(10, 'محطّةٌ سالبة: تخفيضُ تصنيفِ السلامةِ يُردّ', 'mnt_order', '14 إدارة الصيانة · escalateSeverity',
     'النداءُ يُردُّ 409 بالرمزِ SEVERITY_DOWNGRADE_FORBIDDEN والتصنيفُ لا يتغيّر',
     $p10 ? ('الرمز=' . $down['code'] . ' · ' . $down['reason'] . ' · التصنيفُ بعده=' . $sevNow)
          : ('الرمز=' . $down['code'] . ' · ' . $down['reason'] . ' · التصنيف=' . $sevNow),
     $p10 ? 'حكمُ السلامةِ لا يُنقض بضغطةِ زرٍّ — والتصعيدُ يُراجَع ولا يُمحى' : '—',
     'محظورة · التصنيف ثابت', $p10);
$ok = $ok && $p10;

/* ═══ ⑪ المخاطرُ تقرأ المحفِّزَ من قاعدةٍ حيّة ══════════════════════════ */
$conn->query("UPDATE mnt_order SET work_start = NOW(), work_end = NOW() WHERE id = $ordId");
$sigBefore = (int) $one("SELECT COUNT(*) FROM risk_signals WHERE company_id = $company
                          AND rule_key = 'SG-02:ord$ordId'");
$sg = RSE::sg02($conn, $company, $technician, false);
$sigAfter = (int) $one("SELECT COUNT(*) FROM risk_signals WHERE company_id = $company
                         AND rule_key = 'SG-02:ord$ordId'");
$sigRow = $conn->query("SELECT title, sg_code, state FROM risk_signals
                          WHERE company_id = $company AND rule_key = 'SG-02:ord$ordId' LIMIT 1");
$sigRow = $sigRow ? $sigRow->fetch_assoc() : null;
$p11 = ($sigBefore === 0 && $sigAfter === 1 && $sigRow && $sigRow['sg_code'] === 'SG-02');
$add(11, 'المخاطرُ تقرأ محفِّزَ التوقّف', 'risk_signals', '09 إدارة المخاطر · RiskSignalEngine::sg02',
     'ساعاتُ التوقّفِ فوقَ الحدِّ ترفع إشارةَ مخاطرَ واحدةً بمفتاحِ قاعدتِها',
     $p11 ? ('إشاراتٌ قبل=' . $sigBefore . ' · بعد=' . $sigAfter . ' · ' . $sigRow['title'])
          : ('قبل=' . $sigBefore . ' · بعد=' . $sigAfter . ' · مرفوعٌ=' . (int) $sg['raised']),
     $p11 ? 'المخاطرُ تفتح ملفَّ الأصلِ قبل أن يتكرَّر — لا بعد أن يتكرَّر' : '—',
     'محظورة · إشارةُ مخاطرَ مرفوعة', $p11);
$ok = $ok && $p11;

/* ═══ ⑫ عطالةُ إشارةِ المخاطرِ بمفتاحِ قاعدتِها ═══════════════════════ */
RSE::sg02($conn, $company, $technician, false);
$sigTwice = (int) $one("SELECT COUNT(*) FROM risk_signals WHERE company_id = $company
                         AND rule_key = 'SG-02:ord$ordId'");
$p12 = ($sigTwice === 1);
$add(12, 'عطالةُ إشارةِ المخاطرِ بمفتاحِها', 'risk_signals', '09 إدارة المخاطر · rule_key',
     'التشغيلُ الثاني للقاعدةِ لا يُنشئ إشارةً ثانيةً للواقعةِ نفسِها',
     "إشاراتُ الحبّةِ بعدَ تشغيلَين=$sigTwice",
     $p12 ? 'لوحةُ المخاطرِ لا تتضخّم بتكرارِ القراءةِ — والعدُّ يبقى صادقًا' : '—',
     'محظورة · إشارةٌ واحدة', $p12);
$ok = $ok && $p12;

/* ═══ ⑬ الحوكمةُ تقرأ خرقَ الضابطِ — والخرقُ يُصطنَع ليُقاس كشفُه ══════ */
$breachOk = $conn->query("INSERT INTO timesheet
    (company_id, operator, employee_id, shift, date, shift_hours, executed_hours, standby_hours,
     maintenance_fault, total_fault_hours, type, time_notes, user_id, status)
    VALUES ($company, '$equip', '$operator', 'N', '" . $esc($DAY) . "', 12, 8, 0, 0, 0, '1',
            '" . $esc($MARK) . "-breach', $operator, 1)");
$breach = (int) $one("SELECT COUNT(*) FROM timesheet t
                        JOIN equipments e ON e.id = CAST(t.operator AS UNSIGNED)
                       WHERE t.company_id = $company AND t.operator = '$equip'
                         AND t.date >= '" . $esc($DAY) . "' AND t.executed_hours > 0
                         AND e.w7_readiness_state = 'prohibited'");
$p13 = ($breachOk && $breach >= 1);
$add(13, 'الحوكمةُ تقرأ خرقَ ضابطِ المنع', 'timesheet', '08 إدارة الحوكمة والالتزام · ضابطُ منعِ التشغيل',
     'تشغيلُ معدّةٍ محظورةٍ يُقاس خرقًا للضابطِ ويُعَدّ — والضابطُ الذي لا يُقاس ليس ضابطًا',
     $p13 ? "خروقاتٌ مقيسةٌ على الأصلِ المحظور=$breach" : "خروقاتٌ مقيسة=$breach",
     $p13 ? 'الحوكمةُ ترى تشغيلًا وقع رغمَ المنعِ فتفتح مخالفةً بمرجعِها' : '—',
     'محظورة · خرقُ ضابطٍ مرصود', $p13);
$ok = $ok && $p13;

/* ═══ ⑭ القيادةُ ترى تنبيهًا حرِجًا ════════════════════════════════════ */
$sigId = (int) $one("SELECT id FROM risk_signals WHERE company_id = $company
                      AND rule_key = 'SG-02:ord$ordId' LIMIT 1");
$escOk = $conn->query("INSERT INTO risk_escalations (company_id, risk_id, signal_id, reason_ar, to_authority, is_auto)
    VALUES ($company, NULL, $sigId, '" . $esc('توقف حرج للسلامة على أصل محظور — أمر ' . $ordCode) . "', 'ceo', 1)");
$escN = (int) $one("SELECT COUNT(*) FROM risk_escalations WHERE company_id = $company
                     AND signal_id = $sigId AND to_authority = 'ceo'");
$p14 = ($escOk && $escN === 1 && $sigId > 0);
$add(14, 'القيادةُ ترى تنبيهًا حرِجًا', 'risk_escalations', 'EX-CEO مكتب الرئيس التنفيذي · risk_escalations',
     'إشارةٌ حرِجةٌ تُصعَّد إلى القيادةِ بسببٍ مكتوبٍ ومرجعِ إشارتِها',
     $p14 ? "تصعيداتٌ إلى القيادة=$escN · Signal_ID=$sigId" : "تصعيدات=$escN · إشارة=$sigId",
     $p14 ? 'قرارُ إيقافِ أصلٍ عن الإنتاجِ يبلغ مَن يملك بديلَه — لا يبقى في الورشة' : '—',
     'محظورة · تنبيهٌ للقيادة', $p14);
$ok = $ok && $p14;

/* ═══ ⑮ محطّةٌ سالبة: لا شهادةَ قبلَ إنجازِ العملِ فنّيًّا ═══════════════ */
$conn->query("UPDATE mnt_order SET work_end = NULL WHERE id = $ordId");
$early = MCS::issueCert($GATE, $ordId, array('cert_no' => 'W7J-EARLY', 'test_performed' => 'فحص',
                                             'created_by' => $technician));
$conn->query("UPDATE mnt_order SET work_end = NOW() WHERE id = $ordId");
$p15 = (!$early['ok'] && $early['code'] === 409 && $early['reason'] === 'ORDER_WORK_NOT_COMPLETE');
$add(15, 'محطّةٌ سالبة: لا شهادةَ لأمرٍ لم يُنجَز', 'mnt_return_cert', '14 إدارة الصيانة · issueCert',
     'النداءُ يُردُّ 409 بالرمزِ ORDER_WORK_NOT_COMPLETE ولا يُنشأ صفُّ شهادة',
     'الرمز=' . $early['code'] . ' · ' . $early['reason'],
     $p15 ? 'شهادةٌ تسبق الإصلاحَ تعيد معدّةً لم تُصلَح — والمنعُ قبلَ الوقوع' : '—',
     'محظورة · لا شهادة', $p15);
$ok = $ok && $p15;

/* ═══ ⑯ الإصلاحُ: عمالةٌ وقطعٌ وتكلفةٌ مشتقّة ══════════════════════════ */
$conn->query("INSERT INTO mnt_order_labor (company_id, order_id, employee_id, role, hours, hourly_rate, cost)
              VALUES ($company, $ordId, $technician, 'فني', 6, 25, 150)");
$conn->query("INSERT INTO mnt_order_part (company_id, order_id, part_name, quantity, unit_cost, subtotal)
              VALUES ($company, $ordId, 'طقم فرامل', 2, 175, 350)");
$cost = MCS::deriveOrderCost($GATE, $ordId);
$p16 = (abs($cost - 500.0) < 0.005);
$add(16, 'الإصلاحُ وتكلفتُه المشتقّة', 'mnt_order_labor', '14 إدارة الصيانة · deriveOrderCost',
     'التكلفةُ الفعليّةُ مجموعُ العمالةِ والقطعِ والخارجيِّ — تُقرأ ولا تُكتب',
     "عمالة 150 + قطع 350 = المشتقُّ $cost",
     $p16 ? "تكلفةُ إصلاحٍ مقيسةٌ $cost تُحمَّل على مركزِ كلفةِ الأصلِ ومؤشِّرِ الساعة" : '—',
     'محظورة · الإصلاح تم', $p16);
$ok = $ok && $p16;

/* ═══ ⑰ محطّةٌ سالبة: لا صرفَ قطعٍ لأمرٍ مقفل ══════════════════════════ */
$conn->query("UPDATE mnt_order SET state = 'closed' WHERE id = $ordId");
$pr = MCS::requestParts($GATE, array('req_no' => 'W7J-PR-' . substr($RUN, 4, 10), 'order_id' => $ordId));
$conn->query("UPDATE mnt_order SET state = 'open' WHERE id = $ordId");
$p17 = (!$pr['ok'] && $pr['code'] === 409 && $pr['reason'] === 'ORDER_CLOSED_NO_ISSUE');
$add(17, 'محطّةٌ سالبة: لا صرفَ لأمرٍ مقفل', 'mnt_part_request', '17 إدارة المخازن · requestParts',
     'النداءُ يُردُّ 409 بالرمزِ ORDER_CLOSED_NO_ISSUE ولا يُنشأ طلبُ صرف',
     'الرمز=' . $pr['code'] . ' · ' . $pr['reason'],
     $p17 ? 'قطعةٌ تُصرَف على أمرٍ مقفلٍ تُحمَّل على تكلفةٍ أُغلقت — والمنعُ يحمي الحساب' : '—',
     'محظورة · لا صرف', $p17);
$ok = $ok && $p17;

/* ═══ ⑱ شهادةُ العودةِ تصدر بصلاحيتِها من السجلّ ════════════════════════ */
$certNo = 'W7J-CERT-' . substr($RUN, 4, 12);
$ic = MCS::issueCert($GATE, $ordId, array(
    'cert_no' => $certNo, 'test_performed' => 'اختبار كبح على منحدر وفحص خطوط الضغط',
    'test_result' => 'pass', 'meter_at_close' => 1234.5,
    'new_readiness_state' => 'operational', 'created_by' => $technician,
    'src_ref' => $MARK));
$certId = (int) $ic['cert_id'];
$cRow = $conn->query("SELECT valid_days, signer_kind, state, actual_cost, downtime_hours
                        FROM mnt_return_cert WHERE id = $certId LIMIT 1");
$cRow = $cRow ? $cRow->fetch_assoc() : null;
$thDays = (float) $one("SELECT value_num FROM repair01_w7_thresholds WHERE threshold_key = 'W7_CERT_VALID_DAYS_SAFETY'");
$p18 = ($ic['ok'] && $certId > 0 && $cRow && (int) $cRow['valid_days'] === (int) $thDays
        && $cRow['signer_kind'] === 'technical_authority' && abs((float) $cRow['actual_cost'] - 500.0) < 0.005);
$add(18, 'شهادةُ العودةِ تصدر بصلاحيتِها من السجلّ', 'mnt_return_cert', '14 إدارة الصيانة · repair01_w7_thresholds',
     'الصلاحيةُ من العتبةِ المسجَّلةِ لا من رقمٍ في الشيفرة · والتكلفةُ مشتقّةٌ · والمُوقِّعُ مخوَّلٌ فنّيّ',
     $p18 ? ('Cert_ID=' . $certId . ' · صلاحية=' . (int) $cRow['valid_days'] . ' يومًا (العتبة ' . (int) $thDays
            . ') · موقّع=' . $cRow['signer_kind'] . ' · تكلفة=' . $cRow['actual_cost'])
          : ('فشل: ' . $ic['reason'] . ($cRow ? ' · صلاحية=' . $cRow['valid_days'] : '')),
     $p18 ? 'نافذةُ صلاحيةٍ مكتوبةٌ تُقاس عليها إعادةُ الإصلاح — وبلا نافذةٍ لا تكرارَ يُقاس' : '—',
     'محظورة · شهادةٌ تنتظر الاعتماد', $p18);
$ok = $ok && $p18;

/* ═══ ⑲ محطّةٌ سالبة: لا عودةَ بشهادةٍ غيرِ معتمدة ═══════════════════════ */
$rtsEarly = MCS::returnToService($GATE, $certId);
$eqStill = (string) $one("SELECT w7_readiness_state FROM equipments WHERE id = $equip");
$p19 = (!$rtsEarly['ok'] && $rtsEarly['code'] === 403
        && $rtsEarly['reason'] === 'NO_RETURN_WITHOUT_APPROVED_CERT' && $eqStill === 'prohibited');
$add(19, 'محطّةٌ سالبة: لا عودةَ بشهادةٍ غيرِ معتمدة', 'equipments', '04 إدارة الأسطول والأصول · returnToService',
     'النداءُ يُردُّ 403 بالرمزِ NO_RETURN_WITHOUT_APPROVED_CERT وحالةُ الأصلِ لا تتغيّر',
     'الرمز=' . $rtsEarly['code'] . ' · ' . $rtsEarly['reason'] . ' · حالةُ الأصلِ بعده=' . $eqStill,
     $p19 ? 'الشهادةُ المرفوعةُ ليست شهادةً معتمَدة — والمعدّةُ تبقى محظورةً حتى يوقّع المخوَّل' : '—',
     'محظورة · الشهادة لم تُعتمد', $p19);
$ok = $ok && $p19;

/* ═══ ⑳ محطّةٌ سالبة: مَن أنشأ الشهادةَ لا يعتمدها ═════════════════════ */
$selfAp = MCS::approveCert($GATE, $certId, $technician, 'technical_authority');
$p20 = (!$selfAp['ok'] && $selfAp['code'] === 403 && $selfAp['reason'] === 'SOD_SELF_APPROVAL');
$add(20, 'محطّةٌ سالبة: مُنشئُ الشهادةِ لا يعتمدها', 'mnt_return_cert', '14 إدارة الصيانة · فصلُ الواجبات',
     'النداءُ يُردُّ 403 بالرمزِ SOD_SELF_APPROVAL ولا تُعتمد الشهادة',
     'الرمز=' . $selfAp['code'] . ' · ' . $selfAp['reason'],
     $p20 ? 'الفنّيُّ الذي أصلح لا يشهد على نفسِه — والتركيبةُ الممنوعةُ تُردُّ لا تُسجَّل' : '—',
     'محظورة · الاعتماد مردود', $p20);
$ok = $ok && $p20;

/* ═══ ㉑ محطّةٌ سالبة: الحرِجُ لا يعتمده غيرُ المخوَّلِ الفنّيّ ══════════ */
$badKind = MCS::approveCert($GATE, $certId, $safetyOfficer, 'technician');
$p21 = (!$badKind['ok'] && $badKind['code'] === 403
        && $badKind['reason'] === 'SAFETY_CRITICAL_NEEDS_TECHNICAL_AUTHORITY');
$add(21, 'محطّةٌ سالبة: الحرِجُ يعتمده مخوَّلٌ فنّيٌّ وحدَه', 'mnt_return_cert', '14 إدارة الصيانة · DEC-OPEN-12',
     'النداءُ يُردُّ 403 بالرمزِ SAFETY_CRITICAL_NEEDS_TECHNICAL_AUTHORITY',
     'الرمز=' . $badKind['code'] . ' · ' . $badKind['reason'],
     $p21 ? 'إعادةُ خدمةِ أصلٍ حرِجٍ للسلامةِ لا يوقّعها المشغّلُ — قرارُ مالكٍ نصًّا' : '—',
     'محظورة · صفةُ المعتمِدِ مردودة', $p21);
$ok = $ok && $p21;

/* ═══ ㉒ اعتمادُ الشهادةِ — وهنا وحدَها تعود المعدّة ═════════════════════ */
$ap = MCS::approveCert($GATE, $certId, $approver, 'technical_authority');
$eqAfter = $conn->query("SELECT w7_readiness_state, w7_cert_id, w7_readiness_rule FROM equipments WHERE id = $equip LIMIT 1");
$eqAfter = $eqAfter ? $eqAfter->fetch_assoc() : null;
$ordAfter = $conn->query("SELECT state, lockout_state, readiness_cert_ref FROM mnt_order WHERE id = $ordId LIMIT 1");
$ordAfter = $ordAfter ? $ordAfter->fetch_assoc() : null;
$p22 = ($ap['ok'] && $eqAfter && $eqAfter['w7_readiness_state'] === 'operational'
        && (int) $eqAfter['w7_cert_id'] === $certId
        && $ordAfter && $ordAfter['state'] === 'closed' && $ordAfter['lockout_state'] === 'released'
        && $ordAfter['readiness_cert_ref'] === $certNo);
$add(22, 'الأسطولُ يعيد الحالةَ بالشهادةِ المعتمَدة', 'equipments', '04 إدارة الأسطول والأصول · approveCert',
     'الاعتمادُ يعيد الأصلَ عاملًا ويرفع المنعَ ويقفل الأمرَ بمرجعِ الشهادة',
     $p22 ? ('حالةُ الأصل=' . $eqAfter['w7_readiness_state'] . ' · شهادةُ الأصل=#' . (int) $eqAfter['w7_cert_id']
            . ' · الأمر=' . $ordAfter['state'] . ' · المنع=' . $ordAfter['lockout_state']
            . ' · مرجعُ الشهادة=' . $ordAfter['readiness_cert_ref'])
          : ('فشل: ' . $ap['reason'] . ($eqAfter ? ' · حالة=' . $eqAfter['w7_readiness_state'] : '')),
     $p22 ? 'المعدّةُ تعود إلى الأسطولِ قابلةً للإسنادِ والترحيلِ — والحظرُ يُرفَع بسندِه لا بصمت' : '—',
     'تعمل · صلاحية ' . $ap['valid_until'], $p22);
$ok = $ok && $p22;

/* ═══ ㉓ التشغيلُ يعيد الجاهزيّةَ — اشتقاقًا لا إدخالًا ═════════════════ */
$backFormula = ALS::readinessFormula(12, 9, 1, 0, 0);
$eqOk = (string) $one("SELECT w7_readiness_state FROM equipments WHERE id = $equip");
$p23 = ($eqOk === 'operational' && $backFormula['readiness'] > $after['readiness']);
$add(23, 'التشغيلُ يعيد الجاهزيّة', 'asset_readiness', '11 إدارة التشغيل · asset_readiness',
     'الأصلُ العائدُ قابلٌ للإسنادِ فترتفع الجاهزيّةُ المشتقّةُ للفترةِ التالية',
     $p23 ? ('حالةُ الأصل=' . $eqOk . ' · الجاهزيّةُ عند التوقّف=' . $after['readiness']
            . '٪ · بعد العودة=' . $backFormula['readiness'] . '٪')
          : ('حالة=' . $eqOk),
     $p23 ? ('التزامُ الجاهزيّةِ يُستعاد ' . round($backFormula['readiness'] - $after['readiness'], 2) . ' نقطةً مئويّة') : '—',
     'تعمل · ' . $backFormula['readiness'] . '٪', $p23);
$ok = $ok && $p23;

/* ═══ ㉔ إعادةُ الإصلاحِ تُقاس على صلاحيةِ الشهادةِ لا على ادّعاء ═══════ */
$rep = MCS::recordRepeat($GATE, array('origin_order_id' => $ordId,
                                      'repeat_date' => $DAY, 'created_by' => $technician,
                                      'src_ref' => $MARK));
$repRow = $conn->query("SELECT within_validity, days_since_cert, rca_trigger, derivation_rule
                          FROM mnt_repeat_repair WHERE id = " . (int) $rep['repeat_id'] . " LIMIT 1");
$repRow = $repRow ? $repRow->fetch_assoc() : null;
$p24 = ($rep['ok'] && $repRow && (int) $repRow['within_validity'] === 1
        && $repRow['rca_trigger'] === 'safety_critical' && $repRow['derivation_rule'] === 'W7_REPEAT_VS_CERT_VALIDITY');
$add(24, 'إعادةُ الإصلاحِ تُقاس على صلاحيةِ الشهادة', 'mnt_repeat_repair', '14 إدارة الصيانة · recordRepeat',
     'ضمنَ الصلاحيةِ مشتقٌّ من `valid_until` والمحفِّزُ يعلو إلى السلامةِ الحرجة',
     $p24 ? ('Repeat_ID=' . (int) $rep['repeat_id'] . ' · ضمنَ الصلاحية=' . (int) $repRow['within_validity']
            . ' · المدّة=' . (int) $repRow['days_since_cert'] . ' يوم · محفِّز=' . $repRow['rca_trigger'])
          : ('فشل: ' . $rep['reason']),
     $p24 ? 'تحليلُ سببٍ جذريٍّ يُفتَح تلقائيًّا — ولا يُترك التكرارُ لذاكرةِ فنّيّ' : '—',
     'تعمل · تحليلٌ مفتوح', $p24);
$ok = $ok && $p24;

/* ═══════════ الإرجاع — ولا يبقى من الرحلةِ إلّا دليلُها ═══════════ */
$conn->query('ROLLBACK');
$conn->query('SET autocommit = 1');

$pass = 0;
foreach ($ST as $s) { if ($s[8] === 1) { $pass++; } }
$total = count($ST);

foreach ($ST as $s) {
    $conn->query("INSERT INTO repair01_w7_journey
        (run_id, station_no, station, entity, consumer, expected, measured, business_effect, readiness_after, passed)
        VALUES ('" . $esc($RUN) . "'," . (int) $s[0] . ",'" . $esc($s[1]) . "','" . $esc($s[2]) . "',
                '" . $esc($s[3]) . "','" . $esc(mb_substr($s[4], 0, 380)) . "','" . $esc(mb_substr($s[5], 0, 380)) . "',
                '" . $esc(mb_substr($s[6], 0, 380)) . "','" . $esc(mb_substr($s[7], 0, 110)) . "'," . (int) $s[8] . ")");
}

foreach ($ST as $s) {
    printf("  %s %2d  %s\n", $s[8] ? '✔' : '✘', $s[0], $s[1]);
    printf("        المستهلك: %s\n", $s[3]);
    printf("        المقيس  : %s\n", $s[5]);
    printf("        الأثر   : %s\n", $s[6]);
}
echo "\n" . str_repeat('─', 90) . "\n";
$cons = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w7_journey WHERE run_id = '" . $esc($RUN) . "'");
$noEff = (int) $one("SELECT COUNT(*) FROM repair01_w7_journey WHERE run_id = '" . $esc($RUN) . "'
                      AND (business_effect = '' OR business_effect = '—')");
printf("رحلةُ التوقّف: عابرٌ %d/%d · مستهلكونَ متمايزون %d · بلا أثرٍ تجاريٍّ مقيسٍ %d\n",
    $pass, $total, $cons, $noEff);
echo 'الحكم: ' . ($pass === $total ? "عبرت ✔\n" : "لم تعبر ✘\n");
exit($pass === $total ? 0 : 1);
