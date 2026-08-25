<?php
/**
 * tools/repair01_w4_journey.php — رحلةُ اليوم (‏W04 §٦-أ)
 * ═══════════════════════════════════════════════════════════════════════════
 * **فتحُ يومِ موقعٍ ← وردية ← حضورٌ فعليّ ← قيدٌ يوميّ ← تنفيذُ وحدة ←
 *   واقعةُ توقّفٍ تُسجَّل مرّةً واحدةً ← اعتمادٌ ميدانيّ ← محضرُ تسليمٍ ←
 *   إقفالُ اليوم — ومحاولةُ قيدٍ بعدَ الإقفالِ تُرفَض وتُقيَّد.**
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس رقمٌ يعنيه — ساعةُ تشغيلٍ منفَّذة · كميّةٌ قابلةٌ للاعتماد ·
 *   يومُ حضورٍ منسوبٌ لمشغّل · ساعةُ توقّفٍ **لا تُحتسب مرّتين**.
 *
 * ◆ **والمحطّاتُ السالبةُ محطّاتٌ**: «لا إقفالَ ووردية مفتوحةٌ بلا محضر»
 *   و«لا قيدَ بعدَ الإقفال» يُقاسان بالمحاولةِ الفعليّةِ ورقمِ الرفضِ وصفِّ
 *   القيدِ في `site_day_attempt` — لا بقراءةِ شيفرةٍ ولا بدعوى.
 *
 * ◆ **والبياناتُ لا تبقى**: كلُّ ما تكتبه الرحلةُ داخلَ معاملةٍ تُرجَع؛ ودليلُها
 *   وحدَه يُكتب بعدَ الإرجاعِ في `repair01_w4_journey`.
 *
 * التشغيل: php tools/repair01_w4_journey.php
 * الخروج : 0 عبرت كلُّ المحطّات · 1 محطّةٌ لم تعبر
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w4_scan.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
require_once $ROOT . '/app/Services/Operations/SiteDayService.php';
use App\Services\Operations\SiteDayService as SDS;

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w4_one($conn, $sql); };

/* مُعرِّفُ الجولةِ من ساعةِ القاعدة لا من ساعةِ العميل.
   ⚠ **وبدقّةِ الميكروثانية لا الثانية**: جولتانِ في الثانيةِ نفسِها تتقاسمان
     المُعرِّفَ، فتقرأ البوّابةُ صفوفَهما معًا على أنّها جولةٌ واحدةٌ —
     فتصير جولةٌ عابرةٌ «‏12/24» وتسقط. والخطأُ في المقياسِ لا في الرحلة. */
$RUN  = 'W4J-' . (string) $one("SELECT DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')");
$MARK = '__w4_journey_' . $RUN . '__';

echo "═══════════ رحلةُ اليوم — REPAIR01 · W04 ═══════════\n";
echo "الجولة: $RUN\n\n";

$ST = array();
$add = function ($no, $station, $entity, $consumer, $expected, $measured, $effect, $passed) use (&$ST) {
    $ST[] = array($no, $station, $entity, $consumer, $expected, $measured, $effect, $passed ? 1 : 0);
};

/* أرضيّةُ الرحلة: كيانٌ ذو بياناتٍ حيّة، وموقعٌ ومعدةٌ ومشروعٌ وشخصان مختلفان */
$company = (int) $one("SELECT company_id FROM unit_entries WHERE field_kind = 'FIELD_DAILY'
                        GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
if ($company <= 0) { exit("✘ لا كيانَ ذا بياناتٍ ميدانيّة — الرحلةُ لا تُشغَّل على قاعدةٍ فارغة\n"); }
$site    = (int) $one("SELECT id FROM sites WHERE company_id = $company AND is_deleted = 0 ORDER BY id LIMIT 1");
$project = (int) $one("SELECT project_id FROM unit_entries WHERE company_id = $company AND project_id IS NOT NULL
                        ORDER BY id DESC LIMIT 1");
$equip   = (int) $one("SELECT equipment_id FROM unit_entries WHERE company_id = $company AND equipment_id IS NOT NULL
                        ORDER BY id DESC LIMIT 1");
$actors = array();
$r = $conn->query("SELECT id FROM employees WHERE company_id = $company ORDER BY id LIMIT 3");
while ($r && $x = $r->fetch_row()) { $actors[] = (int) $x[0]; }
if ($site <= 0 || $equip <= 0 || $project <= 0 || count($actors) < 3) {
    exit("✘ أرضيّةٌ ناقصة (موقع $site · معدة $equip · مشروع $project · أشخاص " . count($actors) . ") — الرحلةُ لا تُشغَّل\n");
}
list($opener, $entrant, $approver) = $actors;
$DAY = (string) $one("SELECT DATE_ADD(CURDATE(), INTERVAL 3650 DAY)");   /* يومٌ لا يزاحم بياناتٍ حيّة */

/* بوابةُ العزلِ بسياقٍ خادميٍّ صريحٍ للكيانِ المقيس */
SDS::setEventConnection($conn);
$GATE = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($company, $opener, '', true));

$conn->query('SET autocommit = 0');
$conn->query('START TRANSACTION');
$ok = true;

/* ═══ ① فتحُ يومِ الموقع ═══════════════════════════════════════════════════ */
$r1 = SDS::openDay($GATE, $site, $project, $DAY, $opener, $MARK);
$dayId = (int) $r1['day_id'];
$dayRows = (int) $one("SELECT COUNT(*) FROM site_day WHERE company_id = $company AND site_id = $site
                        AND day_date = '" . $esc($DAY) . "' AND state = 'open' AND opened_by = $opener");
$p1 = ($r1['ok'] && $dayId > 0 && $dayRows === 1);
$add(1, 'فتحُ يومِ الموقع', 'site_day', '12 إدارة الموقع · site_day',
     'صفُّ يومٍ واحدٌ بحالةِ open وبكيانٍ قانونيٍّ وفاتحٍ معروف',
     $p1 ? "day_id=$dayId · صفوفٌ مطابقة=$dayRows" : 'فشل: ' . $r1['reason'],
     $p1 ? 'يومٌ ميدانيٌّ صالحٌ لاستقبالِ الوردياتِ والقيود' : '—', $p1);
$ok = $ok && $p1;

/* ═══ ② عطالةُ الفتحِ — النداءُ الثاني لا يُنشئ يومًا ثانيًا ══════════════ */
$r1b = SDS::openDay($GATE, $site, $project, $DAY, $opener, $MARK);
$dayCount = (int) $one("SELECT COUNT(*) FROM site_day WHERE company_id = $company AND site_id = $site
                         AND day_date = '" . $esc($DAY) . "'");
$p2 = ($r1b['ok'] && (int) $r1b['day_id'] === $dayId && $dayCount === 1 && $r1b['created'] === false);
$add(2, 'عطالةُ فتحِ اليومِ بمفتاحِه', 'site_day', '12 إدارة الموقع · uq_site_day',
     'النداءُ الثاني يعيد اليومَ نفسَه ولا يُنشئ صفًّا ثانيًا',
     $p2 ? "أُعيد day_id=$dayId · صفوفُ الحبّة=$dayCount" : "created=" . var_export($r1b['created'], true) . " · صفوف=$dayCount",
     $p2 ? 'يومٌ واحدٌ لكلِّ (كيان × موقع × تاريخ) — لا ازدواجَ في الحبّة' : '—', $p2);
$ok = $ok && $p2;

/* ═══ ③ فتحُ الورديةِ النهارية ═════════════════════════════════════════════ */
$r3 = SDS::openShift($GATE, $dayId, 'day', $entrant, $opener);
$shiftId = (int) $r3['shift_id'];
$shRows = (int) $one("SELECT COUNT(*) FROM site_day_shift WHERE day_id = $dayId AND shift = 'day' AND state = 'open'");
$p3 = ($r3['ok'] && $shiftId > 0 && $shRows === 1);
$add(3, 'فتحُ ورديةِ اليوم', 'site_day_shift', '12 إدارة الموقع · site_day_shift',
     'ورديةٌ واحدةٌ لكلِّ (يوم × وردية) داخلَ يومٍ مفتوح',
     $p3 ? "shift_id=$shiftId · صفوفٌ مطابقة=$shRows" : 'فشل: ' . $r3['reason'],
     $p3 ? 'وعاءٌ زمنيٌّ يقبل الحضورَ والقيدَ ولا يقبلهما خارجَه' : '—', $p3);
$ok = $ok && $p3;

/* ═══ ④ الحضورُ الفعليّ — أثرٌ عند القوى التشغيلية ═══════════════════════ */
$attOk = $conn->query("INSERT INTO attendance_days (company_id, person_id, att_date, status_code, reference_doc)
                       VALUES ($company, $entrant, '" . $esc($DAY) . "', 'P', '" . $esc($MARK) . "')");
$attN = (int) $one("SELECT COUNT(*) FROM attendance_days
                     WHERE company_id = $company AND person_id = $entrant
                       AND att_date = '" . $esc($DAY) . "' AND status_code = 'P'");
$p4 = ($attOk && $attN === 1);
$add(4, 'الحضورُ الفعليُّ للمشغّل', 'attendance_days', '13 القوى التشغيلية · attendance_days',
     'يومُ حضورٍ واحدٌ منسوبٌ للمشغّلِ في تاريخِ اليومِ الميدانيّ',
     $p4 ? "أيامُ حضورٍ مقيسة=$attN لـPerson_ID=$entrant" : 'فشل: ' . $conn->error,
     $p4 ? 'يومُ حضورٍ فعليٍّ يدخل حسابَ الأجرِ والحافزِ ونسبةِ الالتزام' : '—', $p4);
$ok = $ok && $p4;

/* ═══ ⑤ حارسُ القيدِ يسمح واليومُ مفتوح ═══════════════════════════════════ */
$g5 = SDS::assertOpenForEntry($GATE, $site, $DAY, 'day', $entrant, $MARK . '-allow');
$logAllow = (int) $one("SELECT COUNT(*) FROM site_day_attempt WHERE day_id = $dayId
                         AND outcome = 'allowed' AND reason_code = 'OPEN'");
$p5 = ($g5['ok'] && $g5['code'] === 200 && $logAllow >= 1);
$add(5, 'حارسُ القيدِ يسمح داخلَ اليومِ المفتوح', 'site_day_attempt', '12 إدارة الموقع · الحارس',
     'الحارسُ يعيد 200 ويقيّد المحاولةَ المسموحةَ — والمقامُ كاملٌ لا مختار',
     $p5 ? "الرمز=" . $g5['code'] . " · محاولاتٌ مسموحةٌ مقيَّدة=$logAllow" : 'الرمز=' . $g5['code'] . ' · ' . $g5['reason'],
     $p5 ? 'القيدُ اليوميُّ مأذونٌ به بسندٍ مقروءٍ لا بصمت' : '—', $p5);
$ok = $ok && $p5;

/* ═══ ⑥ القيدُ اليوميّ — التايم شيت ═══════════════════════════════════════ */
$tsOk = $conn->query("INSERT INTO timesheet
    (company_id, operator, employee_id, shift, date, shift_hours, executed_hours, standby_hours,
     maintenance_fault, total_fault_hours, type, time_notes, user_id, status)
    VALUES ($company, '$equip', '$entrant', 'D', '" . $esc($DAY) . "', 12, 9, 1, 2, 2, '1',
            '" . $esc($MARK) . "', $entrant, 1)");
$tsId = $tsOk ? (int) $conn->insert_id : 0;
$tsHours = (float) $one("SELECT COALESCE(SUM(executed_hours),0) FROM timesheet
                          WHERE company_id = $company AND date = '" . $esc($DAY) . "' AND operator = '$equip'");
$p6 = ($tsId > 0 && $tsHours > 0);
$add(6, 'القيدُ اليوميُّ للوردية', 'timesheet', '11 إدارة التشغيل · timesheet',
     'سجلٌّ يوميٌّ واحدٌ للحبّةِ (معدة × وردية × يوم) بساعاتٍ منفَّذةٍ مقيسة',
     $p6 ? "Timesheet_ID=$tsId · ساعاتٌ منفَّذةٌ مقيسة=$tsHours" : 'فشل: ' . $conn->error,
     $p6 ? 'ساعةُ تشغيلٍ منفَّذةٌ تدخل الاستحقاقَ والاستغلالَ ومردودَ المعدة' : '—', $p6);
$ok = $ok && $p6;

/* ═══ ⑦ تنفيذُ الوحدة — قيدٌ ميدانيٌّ موصولٌ باليوم ══════════════════════ */
$entryNo = 'W4J-' . substr($RUN, 4);
$ueOk = $conn->query("INSERT INTO unit_entries
    (company_id, entry_no, entry_date, project_id, equipment_id, operator_employee_id, unit_type, qty,
     shift, state, entered_by, field_kind, field_kind_rule, site_day_id, record_basis, note)
    VALUES ($company, '" . $esc($entryNo) . "', '" . $esc($DAY) . "', $project, $equip, $entrant,
            'ton', 150.00, 'day', 'submitted', $entrant, 'FIELD_DAILY', 'W4_HAS_EQUIPMENT', $dayId,
            'contract', '" . $esc($MARK) . "')");
$ueId = $ueOk ? (int) $conn->insert_id : 0;
$ueQty = (float) $one("SELECT COALESCE(SUM(qty),0) FROM unit_entries
                        WHERE site_day_id = $dayId AND field_kind = 'FIELD_DAILY' AND shift IS NOT NULL");
$p7 = ($ueId > 0 && $ueQty > 0);
$add(7, 'تنفيذُ وحدةِ عملٍ ميدانيّة', 'unit_entries', '12 إدارة الموقع · unit_entries',
     'صفُّ وحدةٍ بوردية وموصولٌ بيومِ الموقعِ — ولا قيدَ ميدانيٌّ بلا وردية',
     $p7 ? "Unit_ID=$ueId · كميّةٌ مقيسةٌ على اليوم=$ueQty" : 'فشل: ' . $conn->error,
     $p7 ? 'كميّةٌ منفَّذةٌ صالحةٌ للاعتمادِ ثمّ للفوترة' : '—', $p7);
$ok = $ok && $p7;

/* ═══ ⑧ التوقّفُ يُسجَّل مرّةً واحدةً — والثاني مرآةٌ بفارقِها ══════════════ */
$occKey = repair01_w4_occurrence_key($company, $DAY, 'day', $equip);
$base = array('company_id' => $company, 'occurrence_key' => $occKey, 'stop_date' => $DAY, 'shift' => 'day',
              'equipment_id' => $equip, 'site_id' => $site, 'project_id' => $project,
              'ops_state' => 'tech_breakdown', 'hours' => 2.0, 'resp_party' => 'company',
              'obligation_type' => 'equipment_readiness', 'billable' => 0,
              'authority_rule' => 'W4_UTL_CARRIES_ATTRIBUTION', 'authority_ref' => 'journey:utl');
$s1 = SDS::registerStop($GATE, array_merge($base, array('register_name' => 'unit_time_log')));
$s2 = SDS::registerStop($GATE, array_merge($base, array('register_name' => 'timesheet', 'hours' => 2.0,
                                                        'authority_ref' => 'timesheet#' . $tsId)));
$regN = (int) $one("SELECT COUNT(*) FROM ops_stop_register WHERE occurrence_key = '" . $esc($occKey) . "'");
$srcN = (int) $one("SELECT COUNT(*) FROM ops_stop_source WHERE occurrence_key = '" . $esc($occKey) . "'");
$mirN = (int) $one("SELECT COUNT(*) FROM ops_stop_source WHERE occurrence_key = '" . $esc($occKey) . "' AND role = 'MIRROR'");
$sumH = (float) $one("SELECT COALESCE(SUM(hours),0) FROM ops_stop_register WHERE occurrence_key = '" . $esc($occKey) . "'");
$p8 = ($s1['ok'] && $s2['ok'] && $regN === 1 && $srcN === 2 && $mirN === 1 && abs($sumH - 2.0) < 0.005);
$add(8, 'واقعةُ التوقّفِ تُسجَّل مرّةً واحدة', 'ops_stop_register', '11 إدارة التشغيل · ops_stop_register',
     'سجلَّانِ يدَّعيان الواقعةَ ⇒ صفُّ واقعةٍ واحدٌ وقراءتانِ إحداهما مرآة',
     $p8 ? "صفُّ الواقعة=$regN · قراءاتٌ=$srcN (مرآة $mirN) · ساعاتٌ محتسَبة=$sumH"
         : "صفُّ الواقعة=$regN · قراءات=$srcN · مرآة=$mirN · ساعات=$sumH",
     $p8 ? 'ساعتا توقّفٍ تُحتسبان مرّةً واحدةً لا مرّتين — فلا يتضاعف الخصمُ ولا الاستحقاق' : '—', $p8);
$ok = $ok && $p8;

/* ═══ ⑨ الاعتمادُ الميدانيُّ — بفاعلٍ غيرِ المُدخِل (فصلُ الواجبات) ════════ */
$apOk = $conn->query("INSERT INTO unit_approvals (company_id, entry_id, round_no, stage, decision, actor_id, note)
                      VALUES ($company, $ueId, 1, 'site', 'approved', $approver, '" . $esc($MARK) . "')");
if ($apOk) { $conn->query("UPDATE unit_entries SET state = 'site_approved' WHERE id = $ueId"); }
$apSame = (int) $one("SELECT COUNT(*) FROM unit_approvals a JOIN unit_entries e ON e.id = a.entry_id
                       WHERE a.entry_id = $ueId AND a.actor_id = e.entered_by");
$apState = (string) $one("SELECT state FROM unit_entries WHERE id = $ueId");
$p9 = ($apOk && $apSame === 0 && $apState === 'site_approved');
$add(9, 'الاعتمادُ الميدانيّ', 'unit_approvals', '12 إدارة الموقع · unit_approvals',
     'اعتمادُ المرحلةِ site بفاعلٍ غيرِ مُدخِلِ القيد — وحالةُ القيدِ تتقدَّم',
     $p9 ? "المُعتمِد=$approver · المُدخِل=$entrant · تركيبةٌ ممنوعةٌ=$apSame · الحالة=$apState"
         : "تركيبةٌ ممنوعةٌ=$apSame · الحالة=$apState",
     $p9 ? 'قيدٌ معتمدٌ ميدانيًّا يعبر إلى مرحلةِ الأطرافِ ولا يعبر بلا شاهد' : '—', $p9);
$ok = $ok && $p9;

/* ═══ ⑩ لا إقفالَ ووردية مفتوحةٌ بلا محضر — محطّةٌ سالبة ═════════════════ */
$c10 = SDS::closeDay($GATE, $dayId, $opener, $MARK);
$attOpen = (int) $one("SELECT COUNT(*) FROM site_day_attempt WHERE day_id = $dayId
                        AND attempt_kind = 'day_close' AND reason_code = 'SHIFT_STILL_OPEN'");
$stillOpen = (string) $one("SELECT state FROM site_day WHERE id = $dayId");
$p10 = (!$c10['ok'] && $c10['code'] === 409 && $attOpen === 1 && $stillOpen !== 'closed');
$add(10, 'رفضُ الإقفالِ ووردية مفتوحةٌ بلا محضر', 'site_day', '12 إدارة الموقع · محضرُ التسليم',
     'الإقفالُ يُردُّ 409 ويُقيَّد، واليومُ لا يتقدَّم إلى closed',
     $p10 ? "الرمز=" . $c10['code'] . " · وردياتٌ مفتوحة=" . $c10['open_shifts'] . " · محاولةٌ مقيَّدة=$attOpen · الحالة=$stillOpen"
          : "الرمز=" . $c10['code'] . " · محاولةٌ مقيَّدة=$attOpen · الحالة=$stillOpen",
     $p10 ? 'ورديةٌ بلا محضرِ تسليمٍ تمنع إقفالَ اليومِ فعلًا لا نصحًا' : '—', $p10);
$ok = $ok && $p10;

/* ═══ ⑪ محضرُ التسليمِ ثمّ الإقفال ═════════════════════════════════════════ */
$h11 = SDS::handOverShift($GATE, $shiftId, $approver, 'تسليم وردية — رحلة الإثبات');
$c11 = SDS::closeDay($GATE, $dayId, $opener, $MARK);
$closed = (string) $one("SELECT state FROM site_day WHERE id = $dayId");
$closedBy = (int) $one("SELECT COALESCE(closed_by,0) FROM site_day WHERE id = $dayId");
$shClosed = (int) $one("SELECT COUNT(*) FROM site_day_shift WHERE day_id = $dayId AND state = 'closed'");
$p11 = ($h11['ok'] && $c11['ok'] && $closed === 'closed' && $closedBy === $opener && $shClosed === 1);
$add(11, 'محضرُ التسليمِ ثمّ إقفالُ اليوم', 'site_day', '12 إدارة الموقع · site_day',
     'التسليمُ بمُستلِمٍ معروفٍ يفتح بابَ الإقفال — والإقفالُ بفاعلٍ ووقتٍ (chk_site_day_closed)',
     $p11 ? "الحالة=$closed · المُقفِل=$closedBy · ورديّاتٌ مُقفَلة=$shClosed" : "الحالة=$closed · تسليم=" . var_export($h11['ok'], true),
     $p11 ? 'يومٌ مُقفَلٌ بمسؤولٍ عنه — وأساسٌ لتقريرِ إقفالِ اليومِ وللإقفالِ الشهريّ' : '—', $p11);
$ok = $ok && $p11;

/* ═══ ⑫ محاولةُ قيدٍ بعدَ الإقفالِ تُرفَض وتُقيَّد — محطّةٌ سالبة ═════════ */
$g12 = SDS::assertOpenForEntry($GATE, $site, $DAY, 'day', $entrant, $MARK . '-after-close');
$rejN = (int) $one("SELECT COUNT(*) FROM site_day_attempt WHERE day_id = $dayId
                     AND outcome = 'rejected' AND reason_code = 'DAY_CLOSED' AND attempt_kind = 'unit_entry'");
$rejActor = (int) $one("SELECT COALESCE(actor_id,0) FROM site_day_attempt WHERE day_id = $dayId
                         AND reason_code = 'DAY_CLOSED' ORDER BY id DESC LIMIT 1");
$p12 = (!$g12['ok'] && $g12['reason_code'] === 'DAY_CLOSED' && $rejN === 1 && $rejActor === $entrant);
$add(12, 'قيدٌ بعدَ الإقفالِ يُرفَض ويُقيَّد', 'site_day_attempt', '12 إدارة الموقع · سجلُّ المحاولات',
     'الحارسُ يُرجع DAY_CLOSED ويكتب صفَّ محاولةٍ بفاعلِها وسببِها',
     $p12 ? "السبب=" . $g12['reason_code'] . " · محاولاتٌ مرفوضةٌ مقيَّدة=$rejN · الفاعل=$rejActor"
          : "السبب=" . $g12['reason_code'] . " · مقيَّدة=$rejN · الفاعل=$rejActor",
     $p12 ? 'الحقيقةُ الميدانيّةُ لا تُعدَّل بعد إقفالِها — والمحاولةُ دليلٌ يُراجَع لا صمتٌ' : '—', $p12);
$ok = $ok && $p12;

/* ═══ الإرجاع — لا تبقى بياناتُ الرحلةِ في القاعدة ═════════════════════════ */
$conn->query('ROLLBACK');
$conn->query('SET autocommit = 1');

$left = (int) $one("SELECT COUNT(*) FROM site_day WHERE source_ref = '" . $esc($MARK) . "'")
      + (int) $one("SELECT COUNT(*) FROM unit_entries WHERE note = '" . $esc($MARK) . "'")
      + (int) $one("SELECT COUNT(*) FROM ops_stop_register WHERE occurrence_key = '" . $esc($occKey) . "'");
if ($left !== 0) { echo "⚠ بقيت $left صفًّا من الرحلةِ بعدَ الإرجاع — عالجْ قبلَ الاعتمادِ على النتيجة\n"; $ok = false; }

/* ═══ الدليلُ يُكتب بعدَ الإرجاع ═══════════════════════════════════════════ */
foreach ($ST as $s) {
    $conn->query("INSERT INTO repair01_w4_journey (run_id, station_no, station, entity, consumer, expected, measured, business_effect, passed)
        VALUES ('" . $esc($RUN) . "'," . (int) $s[0] . ",'" . $esc($s[1]) . "','" . $esc($s[2]) . "','" . $esc($s[3]) . "',
                '" . $esc(mb_substr($s[4], 0, 380)) . "','" . $esc(mb_substr($s[5], 0, 380)) . "',
                '" . $esc(mb_substr($s[6], 0, 380)) . "'," . (int) $s[7] . ")");
}

$pass = 0;
foreach ($ST as $s) { if ($s[7] === 1) { $pass++; } else { printf("  ✘ %2d %s — %s\n", $s[0], $s[1], $s[5]); } }
foreach ($ST as $s) { if ($s[7] === 1) { printf("  ✔ %2d %-42s %s\n", $s[0], $s[1], mb_substr($s[5], 0, 70)); } }
echo str_repeat('─', 92) . "\n";
printf("رحلةُ اليوم: %d/%d محطّةً · مستهلكونَ متمايزون %d · بلا أثرٍ تجاريٍّ مقيسٍ %d\n",
    $pass, count($ST), count(array_unique(array_map(function ($s) { return $s[3]; }, $ST))),
    count(array_filter($ST, function ($s) { return $s[6] === '—' || $s[6] === ''; })));
echo 'الحكم: ' . ($ok && $pass === count($ST) ? "عبرت ✔\n" : "لم تعبر ✘\n");
exit(($ok && $pass === count($ST)) ? 0 : 1);
