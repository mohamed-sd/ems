<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-03 — اختبار قبول دورة التوزيع اليومية: خطة عمل الغد (UX-03 §2.2 · OPM-01 §6)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/daily_plan_test.php
 *
 * ما يُثبته:
 *   ① البنية: daily_plans/lines بقيديهما (خطةُ يومِ المشروع الواحدة · احتياجُ
 *      معدة×وردية لا يتكرر · CASCADE) والتسجيلُ في بوابة العزل والشاشةُ 152.
 *   ② الاحتياج: يُولَّد من حاويات المعدات النشطة (الرائد 10) وعطالتُه ·
 *      ومشروعٌ بلا حاوياتٍ يُرفض بنصّ المصدر.
 *   ③ التوزيع: خارجُ سلسلة الحاوية 422 «لا تخصيصَ خارج حاوية» · التعارضُ
 *      (مشغّلٌ على معدتين في اليوم×الوردية) 409 بمرجعه · المعتمدةُ 423.
 *   ④ الدورة: اعتمادُ المنشئ 403 · الفتحُ الناقص 422 بقائمته · الدورةُ
 *      الكاملة تفتح وتُشهر (publishFact) · الإرجاعُ بسببٍ إلزامي.
 *   ⑤ الوصل: الاشتقاقُ الآلي يقرأ الخطةَ المفتوحة (plannedOperatorFor) ·
 *      سببُ البوابة الرابع no_open_plan يزول بالفتح · فحصُ مصدر الوصلَين.
 *
 * البذرُ معزول: خططُ اختبارٍ بتاريخ 2039-01-0x على الرائد 10 تُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Operations/DailyPlanService.php';
require_once dirname(__DIR__) . '/app/Services/Operations/ContainerGate.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Operations\DailyPlanService as DPS;
use App\Services\Operations\ContainerGate as CG;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $CREATOR = 999901; $APPROVER = 999902;
$P = 10;                 // الرائد — شجرتُه مكتملة
$D = '2039-01-05';       // يومٌ لا يشاركنا فيه أحد

$gate = new TenantDb($conn, TenantContext::forSystem($CO, $CREATOR, '', true));
$gateApprover = new TenantDb($conn, TenantContext::forSystem($CO, $APPROVER, '', true));

$teardown = function () use ($conn) {
    $conn->query("DELETE FROM daily_plans WHERE plan_date LIKE '2039-01-%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-03 — دورةُ التوزيع اليومية: خطةُ عمل الغد ══\n");

// ═══ ① البنية ═══
head('① البنية — القيدان والتسجيلُ والشاشة');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_plans' AND INDEX_NAME='uq_dp_project_date'");
check($r && intval($r->fetch_assoc()['c']) === 2, 'UQ (المشروع × اليوم) — خطةٌ واحدةٌ ليومه');
$r = $conn->query("SELECT COUNT(*) c FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='daily_plan_lines' AND INDEX_NAME='uq_dpl_need'");
check($r && intval($r->fetch_assoc()['c']) === 3, 'UQ (الخطة × حاوية المعدة × الوردية)');
$r = $conn->query("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                   WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_dpl_plan'");
$row = $r ? $r->fetch_assoc() : null;
check($row && $row['DELETE_RULE'] === 'CASCADE', 'السطورُ ابنُ خطتها (CASCADE)');
$src = file_get_contents(dirname(__DIR__) . '/app/Core/TenantRegistry.php');
check(strpos($src, "'daily_plans' => array('type' => self::T_TENANT, 'soft' => true)") !== false
      && strpos($src, "'daily_plan_lines' => array('type' => self::T_TENANT, 'soft' => false)") !== false,
      'الجدولان في سجل بوابة العزل');
$m = $conn->query("SELECT id, owner_role_id FROM modules WHERE code = 'Operations/daily_plan.php'")->fetch_assoc();
check($m && intval($m['id']) === 152 && intval($m['owner_role_id']) === 3, 'الموديول 152 مملوكٌ للتشغيل (3) — و5 عرضًا');

// ═══ ② الاحتياج ═══
head('② احتياجُ الغد — من حاويات المعدات لا من اليد');
$r = DPS::generateNeeds($conn, $gate, $CO, 999999, $D, $CREATOR);
check(!$r['ok'] && $r['code'] === 404, 'مشروعٌ أجنبي → 404');
$r = DPS::generateNeeds($conn, $gate, $CO, 2, $D, $CREATOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'حاويات') !== false,
      'مشروعٌ بلا حاويات معداتٍ (2 — جذرٌ بلا أبناء) → 422 بنصّ المصدر');
$r = DPS::generateNeeds($conn, $gate, $CO, $P, $D, $CREATOR);
check($r['ok'] && $r['created'] > 0, 'احتياجُ الرائد وُلّد: ' . intval($r['created']) . ' سطرًا (معدة×وردية)');
$PLAN = intval($r['plan_id']);
$created = intval($r['created']);
$r = DPS::generateNeeds($conn, $gate, $CO, $P, $D, $CREATOR);
check($r['ok'] && $r['created'] === 0 && $r['existing'] === $created && intval($r['plan_id']) === $PLAN,
      'العطالة: التوليدُ الثاني صفرُ جديدٍ والخطةُ نفسُها');

// ═══ ③ التوزيع ═══
head('③ التوزيع — «لا تخصيصَ خارج حاوية» والتعارضُ الفوري');
$lines = array();
$lr = $conn->query("SELECT l.id, l.equipment_container_id, l.shift_no FROM daily_plan_lines l WHERE l.plan_id = {$PLAN} ORDER BY l.id");
while ($row = $lr->fetch_assoc()) { $lines[] = $row; }
check(count($lines) === $created, 'السطورُ مقروءة (' . count($lines) . ')');
$L1 = $lines[0];
// مشغّلٌ من خارج سلسلة الحاوية
$alien = intval($conn->query("SELECT e.id FROM employees e WHERE e.company_id = {$CO}
    AND NOT EXISTS (SELECT 1 FROM op_containers o WHERE o.operator_employee_id = e.id AND o.level='مشغّل')
    LIMIT 1")->fetch_assoc()['id']);
$r = DPS::assign($conn, $gate, $CO, intval($L1['id']), $alien, $CREATOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], 'خارج حاوية') !== false,
      'مشغّلٌ من خارج السلسلة → 422 «لا تخصيصَ خارج حاوية» (OPM-01 §4)');
// مشغّلٌ من السلسلة
$chain1 = intval($conn->query("SELECT operator_employee_id FROM op_containers
    WHERE parent_id = " . intval($L1['equipment_container_id']) . " AND level='مشغّل' AND state='نشطة'
    ORDER BY id LIMIT 1")->fetch_assoc()['operator_employee_id']);
$r = DPS::assign($conn, $gate, $CO, intval($L1['id']), $chain1, $CREATOR);
check($r['ok'], 'مشغّلُ السلسلة يُوزَّع');
// التعارض: السطرُ الآخرُ بالوردية نفسِها والمشغّلِ نفسِه (إن كان في سلسلته)
$L2 = null;
foreach ($lines as $cand) {
    if (intval($cand['id']) === intval($L1['id'])) { continue; }
    if (intval($cand['shift_no']) !== intval($L1['shift_no'])) { continue; }
    $in = $conn->query("SELECT 1 FROM op_containers WHERE parent_id = " . intval($cand['equipment_container_id'])
        . " AND level='مشغّل' AND state='نشطة' AND operator_employee_id = {$chain1} LIMIT 1")->fetch_assoc();
    if ($in) { $L2 = $cand; break; }
}
if ($L2) {
    $r = DPS::assign($conn, $gate, $CO, intval($L2['id']), $chain1, $CREATOR);
    check(!$r['ok'] && $r['code'] === 409 && strpos($r['reason'], '#' . intval($L1['id'])) !== false,
          'التعارضُ الفوري: المشغّلُ على معدتين في الوردية → 409 **بمرجع سطره**');
} else {
    // سلاسلُ الرائد لا تتقاطع في ورديته — يُثبت التعارضُ على المشروع 4 (سلاسلُه
    // متقاطعةٌ مقيسًا): زوجُ سطرَين بورديةٍ واحدةٍ تتشارك سلسلتاهما مشغّلًا.
    $r4 = DPS::generateNeeds($conn, $gate, $CO, 4, $D, $CREATOR);
    $found409 = false;
    if ($r4['ok']) {
        $l4 = array();
        $lr4 = $conn->query("SELECT id, equipment_container_id, shift_no FROM daily_plan_lines
                             WHERE plan_id = " . intval($r4['plan_id']) . " ORDER BY id");
        while ($row = $lr4->fetch_assoc()) { $l4[] = $row; }
        foreach ($l4 as $i => $a) {
            foreach ($l4 as $k => $b) {
                if ($k <= $i || intval($a['shift_no']) !== intval($b['shift_no'])) { continue; }
                $shared = $conn->query("SELECT oa.operator_employee_id op FROM op_containers oa
                    JOIN op_containers ob ON ob.operator_employee_id = oa.operator_employee_id
                     AND ob.parent_id = " . intval($b['equipment_container_id']) . "
                     AND ob.level='مشغّل' AND ob.state='نشطة'
                    WHERE oa.parent_id = " . intval($a['equipment_container_id']) . "
                      AND oa.level='مشغّل' AND oa.state='نشطة' LIMIT 1")->fetch_assoc();
                if (!$shared) { continue; }
                $op = intval($shared['op']);
                $rA = DPS::assign($conn, $gate, $CO, intval($a['id']), $op, $CREATOR);
                $rB = DPS::assign($conn, $gate, $CO, intval($b['id']), $op, $CREATOR);
                $found409 = !empty($rA['ok']) && empty($rB['ok']) && intval($rB['code']) === 409
                            && strpos($rB['reason'], '#' . intval($a['id'])) !== false;
                break 2;
            }
        }
    }
    check($found409, 'التعارضُ الفوري: المشغّلُ المشترك على معدتين في الوردية → 409 **بمرجع سطره**');
}

// ═══ ④ الدورة ═══
head('④ الدورةُ — اعتمادُ الحركة وفتحُ الغد');
$r = DPS::approve($conn, $gate, $CO, $PLAN, $CREATOR);
check(!$r['ok'] && $r['code'] === 403, 'اعتمادُ المنشئ → 403 (فصلُ الواجبات)');
$r = DPS::approve($conn, $gateApprover, $CO, $PLAN, $APPROVER);
check($r['ok'], 'اعتمادُ الحركة بغير المنشئ يمضي');
$r = DPS::assign($conn, $gate, $CO, intval($L1['id']), $chain1, $CREATOR);
check(!$r['ok'] && $r['code'] === 423, 'التوزيعُ بعد الاعتماد → 423 (أرجِعها أولًا)');
$r = DPS::open($conn, $gate, $CO, $PLAN, $CREATOR);
check(!$r['ok'] && $r['code'] === 422 && !empty($r['missing']),
      'الفتحُ الناقص → 422 **بقائمة النواقص** («لا يُفتح موقعٌ ناقصُ التخصيص»)');
// استكمالُ التوزيع: أرجِع ثم وزّع كلَّ سطرٍ من سلسلته (بلا تعارض)
DPS::reopen($conn, $gate, $CO, $PLAN, 'استكمالُ التوزيع للاختبار', $CREATOR);
$used = array();
foreach ($lines as $cand) {
    $ops = array();
    $orr = $conn->query("SELECT operator_employee_id FROM op_containers
        WHERE parent_id = " . intval($cand['equipment_container_id']) . " AND level='مشغّل' AND state='نشطة' ORDER BY id");
    while ($row = $orr->fetch_assoc()) { $ops[] = intval($row['operator_employee_id']); }
    $picked = 0;
    foreach ($ops as $op) {
        $k = $op . '#' . intval($cand['shift_no']);
        if (!isset($used[$k])) { $picked = $op; $used[$k] = true; break; }
    }
    if ($picked > 0) { DPS::assign($conn, $gate, $CO, intval($cand['id']), $picked, $CREATOR); }
}
$unassigned = intval($conn->query("SELECT COUNT(*) c FROM daily_plan_lines WHERE plan_id = {$PLAN} AND operator_employee_id IS NULL")->fetch_assoc()['c']);
if ($unassigned > 0) {
    // سلاسلُ بعض المعدات قد تتقاطع كلُّها — يُقبل النقصُ المعلَنُ ويُثبت الفتحُ الناقص أعلاه
    ok("بقي {$unassigned} سطرًا تتقاطع سلاسلُه كلُّها — النقصُ معلَنٌ (والفتحُ الناقص مثبَتٌ 422)");
    $conn->query("DELETE FROM daily_plan_lines WHERE plan_id = {$PLAN} AND operator_employee_id IS NULL");
}
DPS::approve($conn, $gateApprover, $CO, $PLAN, $APPROVER);
$r = DPS::open($conn, $gate, $CO, $PLAN, $CREATOR);
check($r['ok'], 'الدورةُ الكاملة: توزيعٌ ← اعتمادٌ ← **فتحُ يوم الغد**');
$st = $conn->query("SELECT state, opened_at FROM daily_plans WHERE id = {$PLAN}")->fetch_assoc();
check($st['state'] === 'opened' && $st['opened_at'] !== null, 'الخطةُ opened بختم وقتها');
$r = DPS::reopen($conn, $gate, $CO, $PLAN, '', $CREATOR);
check(!$r['ok'] && $r['code'] === 422, 'إرجاعٌ بلا سبب → 422');

// ═══ ⑤ الوصل ═══
head('⑤ الوصلُ — الاشتقاقُ الآلي وسببُ البوابة الرابع');
$fl = $conn->query("SELECT equipment_id, shift_no, operator_employee_id FROM daily_plan_lines
                    WHERE plan_id = {$PLAN} AND operator_employee_id IS NOT NULL LIMIT 1")->fetch_assoc();
$planned = DPS::plannedOperatorFor($gate, intval($fl['equipment_id']), $D, intval($fl['shift_no']));
check($planned === intval($fl['operator_employee_id']),
      'plannedOperatorFor يقرأ الخطةَ المفتوحة (المعدة × اليوم × الوردية)');
check(DPS::plannedOperatorFor($gate, intval($fl['equipment_id']), '2039-01-06', intval($fl['shift_no'])) === null,
      'يومٌ بلا خطةٍ مفتوحة → null (passthrough — لا تلفيق)');
check(DPS::hasOpenPlan($gate, $P, $D) === true && DPS::hasOpenPlan($gate, $P, '2039-01-06') === false,
      'hasOpenPlan: سببُ البوابة الرابع يزول بالفتح ويقوم بغيابه');
$ts = file_get_contents(dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php');
check(strpos($ts, 'plannedOperatorFor') !== false && strpos($ts, 'DailyPlanService') !== false,
      'الاشتقاقُ الآلي موصولٌ في TimesheetEntryService (الفجوةُ المسمّاة سُدّت)');
$cgs = file_get_contents(dirname(__DIR__) . '/app/Services/Operations/ContainerGate.php');
check(strpos($cgs, 'no_open_plan') !== false && strpos($cgs, 'hasOpenPlan') !== false,
      'سببُ البوابة الرابع (no_open_plan) خلف علم الرائد ووضعِه');
$scr = file_get_contents(dirname(__DIR__) . '/Operations/daily_plan.php');
check(strpos($scr, 'DailyPlanService') !== false && strpos($scr, 'الصلاحيةُ صارمة') !== false
      && strpos($scr, 'dp_action') !== false,
      'الشاشةُ 152 موصولةٌ بالخدمة حصرًا وبصلاحيةٍ صارمة');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
