<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-03 §8.3/§8.4 — اختبار قبول خدمة إدخال الوحدات TimesheetEntryService
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/timesheet_entry_service_test.php
 *
 * حالات §8.4 الخمس بقالب FES §10.8 (Input ← DB · Event · Ledger · Notification):
 *   T1 يوم نظيف · T2 المزامنة المكررة · T3 حارس الطاقة · T4 الاكتمال ·
 *   T5 الإعادة بالرقم نفسه — + المرآة (طور الكتابة المزدوجة) والتعايش.
 *
 * حاكمان في طور الكتابة المزدوجة:
 *   • Ledger: «لا شيء قبل الاكتمال» — وهنا لا شيء *حتى بعده*: التحويل لخطوة ④.
 *   • timesheet قراءةٌ محضة: عدُّ صفوفه قبل الحزمة = بعدها حرفيًّا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

error_reporting(E_ALL & ~E_DEPRECATED);

// موضوعُ هذه الحزمة آلةُ الحالات لا وثائقُ الأهلية — ووقائعُها بتاريخ 2031
// فيحجبها حارسُ الوثائق بحقّ (أيُّ رخصةٍ ساريةٍ اليوم منتهيةٌ يومَ 2031).
// التحييدُ قبل config لأن ems_env تُخزّن القيمَ عند أول نداء. (انظر _guard_env.php)
require_once __DIR__ . '/_guard_env.php';
ems_test_env_override(array('EMS_DOC_EXPIRY_GUARD' => 'off'));

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php';

use App\Core\TenantContext;
use App\Core\TenantDb;
use App\Services\Unit\CapacityGuard;
use App\Services\Unit\TimesheetEntryService as Svc;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)   { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m)  { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$CO = 4;            // شركة co4 — بيئة الاختبار المعتمدة
$EQ = 26;           // معدةٌ لها صفُّ تشغيلٍ حيٌّ بمشروعٍ وموردٍ (اشتقاقٌ حقيقيٌّ لا مبذور)
$ACTOR = 1;
$DAY = '2031-03-03'; // مستقبلٌ بعيدٌ — لا يلامس مفاتيح الأيام الحيّة
$MARK = 'UESVC_' . getmypid();

$conn = $GLOBALS['conn'];
$ctx = TenantContext::forSystem($CO, $ACTOR, '', true);
$gate = new TenantDb($conn, $ctx);

$DAYS = "'2031-03-03','2031-03-04'"; // يوما T1 وT3 — كلاهما يُكنس
$teardown = function () use ($conn, $DAYS) {
    $conn->query("DELETE FROM ems_business_events WHERE entity_type='unit_entry' AND entity_id IN
                    (SELECT id FROM unit_entries WHERE entry_date IN ({$DAYS}))");
    $conn->query("DELETE FROM unit_capacity_flags WHERE entry_id IN
                    (SELECT id FROM unit_entries WHERE entry_date IN ({$DAYS}))");
    $conn->query("DELETE FROM unit_approvals WHERE entry_id IN
                    (SELECT id FROM unit_entries WHERE entry_date IN ({$DAYS}))");
    $conn->query("DELETE FROM unit_time_log WHERE log_date IN ({$DAYS})");
    $conn->query("DELETE FROM unit_entries WHERE entry_date IN ({$DAYS})");
};
register_shutdown_function($teardown);
$teardown();

$tsBefore = (int) $conn->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c'];
$ledgerBefore = (int) $conn->query("SELECT COUNT(*) c FROM fin_financial_events")->fetch_assoc()['c'];

fwrite(STDOUT, "\n══ UX-03 §8.4 — خدمة إدخال الوحدات ══\n");

// ═══ T1 · يوم نظيف ═══
head('T1 يوم نظيف — الإدخال بالمدخلات الستة والاشتقاق من صف التشغيل');

$in = array(
    'company_id' => $CO, 'equipment_id' => $EQ, 'shift' => 'day', 'date' => $DAY,
    'qty' => 9.5, 'unit_type' => 'hour',
    'time_lines' => array(
        array('hours' => 8.0, 'ops_state' => 'actual_work', 'resp_party' => 'none'),
        array('hours' => 1.5, 'ops_state' => 'standby', 'resp_party' => 'client'),
        array('hours' => 0.5, 'ops_state' => 'tech_breakdown', 'resp_party' => 'company', 'cause_note' => 'WO-5511'),
    ),
    'source_ref' => $MARK . '-T1',
    'operator_employee_id' => 3,
);
$r1 = Svc::submit($conn, $gate, $in, $ACTOR);
check($r1['ok'] && $r1['code'] === 201, 'الرد 201 — وحدةٌ وليدة (§8.3)');
$e1 = $r1['entry'];
check($e1 !== null && $e1['state'] === 'submitted', 'الحالة Submitted');
check($e1 !== null && preg_match('/^UNT-\d{6}$/', $e1['entry_no']) === 1,
    "هويةٌ خادميةٌ ثابتة: {$e1['entry_no']} (§3.1)");
check($e1 !== null && (int) $e1['project_id'] > 0 && $e1['supplier_entity_id'] !== null,
    'الاشتقاق §5.1: المشروع والمورد جُلبا من صف التشغيل لا من المُدخِل');
check($e1 !== null && count($e1['time_lines']) === 3,
    'ثلاثة سطور زمنٍ بحالاتها — زمنُ العمل منفصلٌ عن وحدة العقد (§5.1)');
check($e1 !== null && (float) $e1['qty'] === 9.5 && $e1['unit_type'] === 'hour',
    'وحدة العقد 9.5 ساعة — حقلٌ مستقلٌّ لا مجموعَ سطور الزمن');
check(empty($r1['warnings']), 'يومٌ نظيف: صفرُ تحذيرات');

$evCount = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
    WHERE event_key='operations.unit.submitted' AND entity_type='unit_entry'
      AND entity_id={$e1['id']}")->fetch_assoc()['c'];
check($evCount === 1, 'Event: UnitSubmitted نُشر مرةً واحدة');

// ═══ T2 · المزامنة المكررة ═══
head('T2 المزامنة — مفتاح (المعدة×التاريخ×الوردية) يرجع القائم لا يكرره');

$r2 = Svc::submit($conn, $gate, $in, $ACTOR);
check($r2['ok'] && $r2['code'] === 200 && !empty($r2['existing']),
    'الرد 200 بالمرجع القائم — «لا تكرار» (§8.3)');
check((int) $r2['entry']['id'] === (int) $e1['id'], 'المرجعُ هو الوحدة الأولى نفسها');
$cnt = (int) $conn->query("SELECT COUNT(*) c FROM unit_entries WHERE entry_date='{$DAY}'")->fetch_assoc()['c'];
check($cnt === 1, 'DB: صفرُ تكرارٍ — صفٌّ واحدٌ لليوم');
$evCount = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
    WHERE event_key='operations.unit.submitted' AND entity_id={$e1['id']}")->fetch_assoc()['c'];
check($evCount === 1, 'Event: مرةً — لم يُنشر عن المزامنة المكررة');

// والوردية الأخرى ليست تكرارًا — مفتاحٌ آخر
$in2 = $in;
$in2['shift'] = 'night';
$in2['qty'] = 4.0;
$in2['source_ref'] = $MARK . '-T2N';
$in2['time_lines'] = array(array('hours' => 4.0, 'ops_state' => 'actual_work', 'resp_party' => 'none'));
$r2n = Svc::submit($conn, $gate, $in2, $ACTOR);
check($r2n['ok'] && $r2n['code'] === 201, 'الوردية الليلية لليوم نفسه وحدةٌ جديدة — المفتاح ثلاثي');

// ═══ 422 · نقص الإلزامي ═══
head('422 — نقصُ إلزاميٍّ يُرَدّ بقائمته (§8.3)');
$rBad = Svc::submit($conn, $gate, array('company_id' => $CO, 'equipment_id' => $EQ), $ACTOR);
check(!$rBad['ok'] && $rBad['code'] === 422, 'الرد 422');
check(in_array('qty', $rBad['missing'], true) && in_array('time_lines', $rBad['missing'], true)
   && in_array('source_ref', $rBad['missing'], true), 'القائمة تسمّي الناقص: qty · time_lines · source_ref');

// ═══ T3 · حارس الطاقة ═══
head('T3 حارس الطاقة — يُحفظ Submitted معلَّمًا ويُمنع اعتمادُ الموقع قبل السبب');

$DAY3 = '2031-03-04';
$in3 = array(
    'company_id' => $CO, 'equipment_id' => $EQ, 'shift' => 'day', 'date' => $DAY3,
    'qty' => 26.0, 'unit_type' => 'hour',
    'time_lines' => array(array('hours' => 26.0, 'ops_state' => 'actual_work', 'resp_party' => 'none')),
    'source_ref' => $MARK . '-T3', 'operator_employee_id' => 3,
);
$r3 = Svc::submit($conn, $gate, $in3, $ACTOR);
check($r3['ok'] && $r3['code'] === 201, 'التجاوز لا يُمنع إدخالُه (§3.10) — حُفظ');
check($r3['entry']['state'] === 'submitted' && (int) $r3['entry']['capacity_flag'] === 1,
    'DB: Submitted معلَّمًا capacity_flag=1');
check(!empty($r3['warnings']) && $r3['warnings'][0]['type'] === 'capacity',
    'UnitDTO يحمل تحذيرَه: تجاوزُ طاقةٍ معلَّم (§8.3)');

$e3 = (int) $r3['entry']['id'];
$ap3 = Svc::approve($conn, $gate, $CO, $e3, 'site', $ACTOR);
check(!$ap3['ok'] && $ap3['code'] === 422, 'اعتمادُ الموقع يُرفض 422 قبل السبب (§8.4-T3)');
check(!empty($ap3['reasons']) && strpos(implode(' ', $ap3['reasons']), 'السبب') !== false,
    'وبالسبب المطلوب نصًّا في الرفض');

// التخليص الصريح ثم الاعتماد يمرّ
CapacityGuard::clear($conn, $gate, $CO, $e3, 'equipment', 'ورديةٌ إضافيةٌ معتمدة', true, $ACTOR);
CapacityGuard::clear($conn, $gate, $CO, $e3, 'operator', 'مشغّلٌ ثانٍ حاضر', true, $ACTOR);
$ap3b = Svc::approve($conn, $gate, $CO, $e3, 'site', $ACTOR);
check($ap3b['ok'] && $ap3b['state'] === 'site_approved', 'بعد تخليص المحورين: SiteApproved');

// ═══ T4 · الاكتمال ═══
head('T4 الاكتمال — السلسلة تمشي حتى SalesApproved ولا مالَ في طور الكتابة المزدوجة');

$e1id = (int) $e1['id'];
$s = Svc::approve($conn, $gate, $CO, $e1id, 'site', $ACTOR);
check($s['ok'] && $s['state'] === 'site_approved', 'اعتماد الموقع: SiteApproved');

$s = Svc::approve($conn, $gate, $CO, $e1id, 'supplier', $ACTOR);
check($s['ok'] && $s['state'] === 'parties_review',
    'بطاقة المورد وحدها: parties_review — المشغّلُ معنيٌّ ولم يحكم بعد');
$s = Svc::approve($conn, $gate, $CO, $e1id, 'operator', $ACTOR);
check($s['ok'] && $s['state'] === 'parties_approved', 'اكتمال بطاقات المعنيّين: PartiesApproved');

$sDup = Svc::approve($conn, $gate, $CO, $e1id, 'supplier', $ACTOR);
check($sDup['ok'] && $sDup['code'] === 200 && !empty($sDup['existing']),
    'قرارُ مرحلةٍ مكرر في الجولة نفسها: يُرجَع عطالةً لا يُدرَج');

$sEarly = Svc::approve($conn, $gate, $CO, $e3, 'sales', $ACTOR);
check(!$sEarly['ok'] && $sEarly['code'] === 409,
    'المبيعات قبل اكتمال الأطراف تُرفض 409 — الآلة تفرض ترتيبَها (§8.2)');

$s = Svc::approve($conn, $gate, $CO, $e1id, 'sales', $ACTOR);
check($s['ok'] && $s['state'] === 'sales_approved', 'اعتماد المبيعات: اكتمالُ السلسلة');

$evCC = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
    WHERE event_key='operations.unit.chain_completed' AND entity_id={$e1id}")->fetch_assoc()['c'];
check($evCC === 1, 'Event: ChainCompleted نُشر');

$ledgerAfter = (int) $conn->query("SELECT COUNT(*) c FROM fin_financial_events")->fetch_assoc()['c'];
check($ledgerAfter === $ledgerBefore,
    'Ledger: صفرُ قيدٍ — التحويل المالي لخطوة ④ لا لهذا الطور (تعايشٌ لا استبدال)');

// ═══ T5 · الإعادة بالرقم نفسه ═══
head('T5 الإعادة — ReturnedToSite بسببٍ وبالرقم نفسه وجولةٍ جديدة (§8.2)');

$rNoReason = Svc::returnToSite($conn, $gate, $CO, $e3, 'supplier', '', $ACTOR);
check(!$rNoReason['ok'] && $rNoReason['code'] === 422, 'إعادةٌ بلا سببٍ لا تقع');

$entryNoBefore = $conn->query("SELECT entry_no FROM unit_entries WHERE id={$e3}")->fetch_assoc()['entry_no'];
$ret = Svc::returnToSite($conn, $gate, $CO, $e3, 'supplier', 'الكمية لا تطابق كشف الموقع', $ACTOR);
check($ret['ok'] && (int) $ret['round'] === 2, 'الإعادة فتحت الجولة 2');
$row = $conn->query("SELECT entry_no, state, current_round FROM unit_entries WHERE id={$e3}")->fetch_assoc();
check($row['state'] === 'returned', 'DB: الحالة returned');
check($row['entry_no'] === $entryNoBefore, "بالرقم نفسه: {$row['entry_no']} لم يتغيّر");

$evRet = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
    WHERE event_key='operations.unit.returned' AND entity_id={$e3}")->fetch_assoc()['c'];
check($evRet === 1, 'Event: UnitReturned بسببه');

// الاستكمال: إعادة إرسالٍ ثم اعتمادُ الجولة الثانية يمرّ رغم قرار الجولة الأولى
$rs = Svc::resubmit($conn, $gate, $CO, $e3, $ACTOR);
check($rs['ok'], 'إعادة الإرسال من returned إلى submitted');
$ap5 = Svc::approve($conn, $gate, $CO, $e3, 'site', $ACTOR);
check($ap5['ok'] && $ap5['state'] === 'site_approved',
    'اعتماد الموقع في الجولة 2 يمرّ — round_no حلّ قيد uq_stage_once (قرار المالك)');
$apCount = (int) $conn->query("SELECT COUNT(*) c FROM unit_approvals
    WHERE entry_id={$e3} AND stage='site'")->fetch_assoc()['c'];
check($apCount === 2, 'وتاريخُ الجولتين كاملٌ: قراران لمرحلة site بجولتين');

// ═══ المرآة — طور الكتابة المزدوجة ═══
head('المرآة — صفُّ دوامٍ حيٌّ يُمرآة عطالةً بلا حدثٍ مزدوج');

// ⚠️ عزلٌ عن مجموعة المطابقة (2027-02): تلك الصفوفُ ممرآةٌ سلفًا بالكتابة
// المزدوجة، فاختيارُ أحدثِها كان يُرجع 200 ثم يحذف مرآةً ليست لهذه الحزمة.
// المرآةُ هنا تُختبَر على صفٍّ تاريخيٍّ لا مرآةَ له.
$tsRow = $conn->query("SELECT t.id FROM timesheet t JOIN operations o ON o.id=t.operator
    WHERE o.company_id={$CO} AND o.equipment IS NOT NULL AND t.executed_hours > 0
      AND t.`date` < '2027-01-01'
      AND NOT EXISTS (SELECT 1 FROM unit_entries u WHERE u.sync_uuid = CONCAT('ts:', t.id))
    ORDER BY t.id DESC LIMIT 1")->fetch_assoc();
check($tsRow !== null, 'صفُّ دوامٍ حيٌّ للمرآة موجود');
$tsId = (int) $tsRow['id'];

$evBefore = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events")->fetch_assoc()['c'];
$m1 = Svc::mirrorFromTimesheet($conn, $gate, $tsId, $ACTOR);
check($m1['ok'] && $m1['code'] === 201, "المرآة أنشأت الواقعة (TS-{$tsId})");
$m2 = Svc::mirrorFromTimesheet($conn, $gate, $tsId, $ACTOR);
check($m2['ok'] && $m2['code'] === 200 && !empty($m2['existing']),
    'المرآة الثانية أرجعت القائم — عطالةٌ كاملة');
check((int) $m2['entry_id'] === (int) $m1['entry_id'], 'بالمرجع نفسه');
$evAfter = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events")->fetch_assoc()['c'];
check($evAfter === $evBefore, 'صفرُ حدثٍ من المرآة — الواقعة ممثَّلةٌ في الناقل من مسارها الحي');

$mrow = $conn->query("SELECT source_ref, sync_uuid FROM unit_entries WHERE id={$m1['entry_id']}")->fetch_assoc();
check($mrow['source_ref'] === 'TS-' . $tsId && $mrow['sync_uuid'] === 'ts:' . $tsId,
    'النسبُ صادق: source_ref وsync_uuid يشيران للصف الحي');

// ═══ مرآة الاعتماد — إعادةُ تشغيل السلسلة القديمة بترتيبها ═══
head('مرآة الاعتماد — صفٌّ بلغ الرابع قبل العلم تُستكمل سلسلتُه كاملة');

$ts4 = $conn->query("SELECT ta.timesheet_id id FROM timesheet_approvals ta
    JOIN timesheet t ON t.id=ta.timesheet_id
    JOIN operations o ON o.id=t.operator
    WHERE ta.approval_level=4 AND ta.status=1 AND o.equipment IS NOT NULL
      AND t.`date` < '2027-01-01'
      AND NOT EXISTS (SELECT 1 FROM unit_entries u WHERE u.sync_uuid = CONCAT('ts:', t.id))
    ORDER BY ta.timesheet_id LIMIT 1")->fetch_assoc();
check($ts4 !== null, 'صفٌّ حيٌّ بلغ الاعتماد الرابع موجود');
if ($ts4 !== null) {
    $ts4id = (int) $ts4['id'];
    $m4 = Svc::mirrorFromTimesheet($conn, $gate, $ts4id, $ACTOR);
    check($m4['ok'], "مرآة الصف {$ts4id} أُنشئت");
    $ap4 = Svc::mirrorApproval($conn, $gate, $ts4id, 4, $ACTOR);
    check($ap4['ok'], 'إعادة تشغيل السلسلة (L1→L4) نجحت دفعةً واحدة');
    $st4 = $conn->query("SELECT state FROM unit_entries WHERE id={$m4['entry_id']}")->fetch_assoc();
    check($st4['state'] === 'parties_approved',
        'الحالة parties_approved — لا مبيعاتَ في المسار القديم فلا تُلفَّق (§8.2)');
    $nAp = (int) $conn->query("SELECT COUNT(*) c FROM unit_approvals
        WHERE entry_id={$m4['entry_id']}")->fetch_assoc()['c'];
    check($nAp === 4, "أربعةُ قراراتٍ ممرآةٌ بمراحلها (site·supplier·fleet·operator): {$nAp}");
    // عطالة الإعادة: تشغيلٌ ثانٍ لا يضاعف
    Svc::mirrorApproval($conn, $gate, $ts4id, 4, $ACTOR);
    $nAp2 = (int) $conn->query("SELECT COUNT(*) c FROM unit_approvals
        WHERE entry_id={$m4['entry_id']}")->fetch_assoc()['c'];
    check($nAp2 === 4, 'إعادةُ التشغيل الثانية عطالة: لا قرارَ مكرر');
    $conn->query("DELETE FROM unit_approvals WHERE entry_id={$m4['entry_id']}");
    $conn->query("DELETE FROM unit_capacity_flags WHERE entry_id={$m4['entry_id']}");
    $conn->query("DELETE FROM unit_time_log WHERE entry_id={$m4['entry_id']}");
    $conn->query("DELETE FROM unit_entries WHERE id={$m4['entry_id']}");
}

// تنظيف المرآة (تاريخها حيٌّ لا يخص يومَي الاختبار)
$conn->query("DELETE FROM unit_capacity_flags WHERE entry_id={$m1['entry_id']}");
$conn->query("DELETE FROM unit_time_log WHERE entry_id={$m1['entry_id']}");
$conn->query("DELETE FROM unit_entries WHERE id={$m1['entry_id']}");

// ═══ التعايش ═══
head('التعايش — المسار الحي قراءةٌ محضة');
$tsAfter = (int) $conn->query("SELECT COUNT(*) c FROM timesheet")->fetch_assoc()['c'];
check($tsAfter === $tsBefore, "timesheet: {$tsBefore} → {$tsAfter} صفًّا — لم يُمسّ");

fwrite(STDOUT, "\n══════════════════════════════════════════════════\n");
fwrite(STDOUT, "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n");
exit($FAIL === 0 ? 0 : 1);
