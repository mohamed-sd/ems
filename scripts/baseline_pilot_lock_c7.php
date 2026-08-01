<?php
/**
 * scripts/baseline_pilot_lock_c7.php — إغلاق خط الأساس للعقد الرائد #7 (PLAN-05 البوابة ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * «خطُّ أساسٍ تجاريٌّ مكتملٌ لعقدٍ رائدٍ واحدٍ بدورة قفله — لا لكل العقود».
 *
 * المكوّنات تُشتق من شروط العقد 7 الفعلية (contractequipments #28):
 *   600 ساعة تعاقدية إجمالية · 10.00 USD/ساعة · مدة 2026-06-30 → 2026-07-30.
 * لا اختلاق: الكمية والسعر والعملة والمدة كلها من رأس العقد وبنوده المسجَّلة.
 *
 * إيدمبوتنت: ما وُجد قائمًا لا يُعاد إنشاؤه. اليدان: المراجع #4 والمعتمِد #6.
 * التشغيل: php scripts/baseline_pilot_lock_c7.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'baseline pilot lock');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractBaselineService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractMonthlyPlanService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractPaymentScheduleService.php';

use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractMonthlyPlanService as CMP;
use App\Services\Contract\ContractPaymentScheduleService as CPS;
use App\Services\Contract\ContractBaselineService as CBS;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate = ems_tenant_db();
$CO = 4; $CID = 7;
$REVIEWER = 4;   // مسؤول التشغيل — يراجع
$APPROVER = 6;   // يسن سيد احمد — يعتمد ويقفل (يدٌ ثانية)

function say($m) { fwrite(STDOUT, $m . PHP_EOL); }

// ① بند البيع — من شروط العقد الفعلية (إن لم يوجد)
$lines = CLS::linesOf($gate, $CID, false);
if (count($lines) === 0) {
    $r = CLS::add($conn, $gate, $CO, $CID, array(
        'pricing_model' => 'hour',
        'description' => 'تشغيل معدات بالساعة — مشروع صافولا (بند العقد 7 من contractequipments#28)',
        'qty_contracted' => 600, 'unit_price' => 10.00, 'currency' => 'USD',
        'tax_status' => 'exempt', 'tax_code_id' => null,
        'valid_from' => '2026-06-30', 'valid_to' => '2026-07-30'), $REVIEWER);
    if (empty($r['ok']) && empty($r['line_id'])) { say('✘ فشل إنشاء البند: ' . json_encode($r, JSON_UNESCAPED_UNICODE)); exit(1); }
    say('✔ بند بيع #' . $r['line_id'] . ' — 600 ساعة × 10.00 USD');
    $lines = CLS::linesOf($gate, $CID, false);
} else {
    say('✔ بند بيع قائم (' . count($lines) . ') — لا يعاد إنشاؤه');
}
$LID = (int) $lines[0]['id'];

// ② الجدول الشهري + الختم — الكمية منحنى المدة الفعلية (يونيو جزئي + يوليو)
if ($lines[0]['plan_sealed_version'] === null) {
    $months = array(
        array('period_month' => '2026-06', 'qty_planned' => 20),
        array('period_month' => '2026-07', 'qty_planned' => 580),
    );
    CMP::savePlan($conn, $gate, $CO, $LID, 1, '2026-06-30', $months, $REVIEWER);
    CMP::seal($conn, $gate, $CO, $LID, 1, $REVIEWER);
    say('✔ جدول شهري مختوم — Σ 600 = المتعاقد');
} else {
    say('✔ جدول مختوم قائم — نسخة ' . $lines[0]['plan_sealed_version']);
}

// ③ خطة الدفع — مستخلص شهري باستحقاق 30 يومًا (نمط العقد: مقدم + شهري)
$hasPay = $conn->query("SELECT COUNT(*) c FROM contract_payment_schedule WHERE contract_id={$CID} AND effective_to IS NULL")->fetch_assoc();
if ((int) $hasPay['c'] === 0) {
    CPS::generate($conn, $gate, $CO, $CID, array('pattern' => 'monthly_claim', 'due_days' => 30), $REVIEWER);
    say('✔ خطة دفع مولَّدة — مستخلص شهري');
} else {
    say('✔ خطة دفع نافذة قائمة (' . $hasPay['c'] . ' سطرًا)');
}

// ④ الجاهزية ثم الدورة: draft → reviewed → approved → locked (يدان)
$rd = CBS::readiness($gate, $CID);
say(($rd['ok'] ? '✔ ' : '✘ ') . $rd['note']);
if (!$rd['ok']) { exit(1); }

$cur = CBS::current($gate, $CID);
if (!$cur) { $op = CBS::open($conn, $gate, $CO, $CID, $REVIEWER); say('✔ ' . $op['reason']); $cur = CBS::current($gate, $CID); }
$state = (string) $cur['state'];
say('حالة خط الأساس الحالية: ' . $state);

$steps = array('draft' => array('reviewed', 'approved', 'locked'),
               'reviewed' => array('approved', 'locked'),
               'approved' => array('locked'),
               'locked' => array());
foreach ($steps[$state] as $to) {
    $actor = ($to === 'reviewed') ? $REVIEWER : $APPROVER;
    $t = CBS::transition($conn, $gate, $CO, $CID, $to, $actor, 'إغلاق البوابة ① — العقد الرائد');
    say(($t['ok'] ? '✔ ' : '✘ ') . $to . ': ' . $t['reason']);
    if (!$t['ok']) { exit(1); }
}

$q = $conn->query("SELECT state, version, fingerprint, comp_lines, comp_plan_sealed, comp_payment_rows, comp_sites, locked_by, locked_at FROM contract_baseline WHERE contract_id={$CID} AND state='locked'")->fetch_assoc();
say('النتيجة: ' . json_encode($q, JSON_UNESCAPED_UNICODE));
exit($q ? 0 : 1);
