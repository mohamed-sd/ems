<?php
/**
 * A-02 · بذرُ سجل الأفعال من الموجود — ACT-01 §9-①
 * ─────────────────────────────────────────────────
 * «يُستخرج آليًّا من قوالب الشاشات ومن حارس الأفعال القائم — فالأساسُ
 * موجودٌ ولا يُبدأ من الصفر».
 *
 * المصدران:
 *   ① ems_action_guard_registry() — كلُّ معالج AJAX المسجَّل (المسارُ + الفعل).
 *   ② الأفعالُ الحاكمةُ العشرة (ACT-01 §6) — تُبذر بعقودها المعروفة من خدماتها.
 *
 * idempotent: INSERT ... ON DUPLICATE KEY UPDATE — يُعاد تشغيلُه بأمان.
 * التشغيل: php tools/act_seed_actions.php
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/action_guard.php';

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) { fwrite(STDERR, "لا اتصال\n"); exit(1); }
mysqli_set_charset($conn, 'utf8mb4');

$ins = mysqli_prepare($conn,
    "INSERT INTO actions (action_code, name_ar, module_id, placement, handler_path, handler_class, handler_method,
                          is_write, guards_json, reverse_action_code, is_financial, owner_doc, active)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)
     ON DUPLICATE KEY UPDATE name_ar=VALUES(name_ar), handler_path=VALUES(handler_path),
       handler_class=VALUES(handler_class), handler_method=VALUES(handler_method),
       is_write=VALUES(is_write), guards_json=VALUES(guards_json),
       reverse_action_code=VALUES(reverse_action_code), is_financial=VALUES(is_financial)");

$count = 0;
function seed($code,$name,$mod,$place,$path,$cls,$meth,$write,$guards,$rev,$fin,$doc){
    global $ins,$count;
    $g = $guards ? json_encode($guards, JSON_UNESCAPED_UNICODE) : null;
    mysqli_stmt_bind_param($ins,'ssissssissis',$code,$name,$mod,$place,$path,$cls,$meth,$write,$g,$rev,$fin,$doc);
    if (!mysqli_stmt_execute($ins)) { fwrite(STDERR, "✘ $code: ".mysqli_error($GLOBALS['conn'])."\n"); return; }
    $count++;
}

// ── ① من حارس الأفعال القائم — كلُّ معالجٍ فعلٌ ─────────────────────────────
$registry = ems_action_guard_registry();
foreach ($registry as $path => $meta) {
    $verb   = $meta['action'];                        // view · auto · public
    $isWrite = ($verb !== 'view' && $verb !== 'public') ? 1 : 0;
    $slug   = str_replace(array('/', '.php'), array('.', ''), strtolower($path));
    $code   = 'ajax.' . $slug;                        // فريدٌ ومشتقٌّ من المسار
    // الحرّاسُ الموروثة من نقطة الاعتراض المركزية (config.php)
    $guards = $isWrite
        ? array('session','ajax_origin','rate_limit','action_permission','tenant_isolation','csrf')
        : array('session','ajax_origin','rate_limit','action_permission','tenant_isolation');
    seed($code, 'معالجُ ' . basename($path, '.php'), null,
         $isWrite ? 'row' : 'context', $path, null, null,
         $isWrite, $guards, null, 0, 'ADR-06');
}

// ── ② الأفعالُ الحاكمةُ العشرة (ACT-01 §6) — بعقودها من خدماتها ────────────
$G = array('session','permission','tenant_isolation','authority','self_approval','sod','period_lock');
// الأصنافُ والدوالُّ حقيقيةٌ مقيسةٌ من المستودع — لا مخترعة (تقرير الدَّين الأول كشف 17 مخترعًا)
$ten = array(
    // code · name · handler_class · method · reverse · financial
    array('unit.chain.approve','اعتمادُ حلقةٍ في سلسلة الوحدة','App\\Services\\Policy\\UnitJourneyService','approveLink','unit.chain.reverse',1),
    array('entitlement.gate.approve','بوابةُ الاستحقاق المالي','App\\Services\\Policy\\UnitJourneyService','postEntitlement','entitlement.gate.reverse',1),
    array('invoice.issue','إصدارُ فاتورةٍ ضريبية','App\\Services\\Revenue\\TaxInvoiceService','issueForClaim','invoice.cancel',1),
    array('collection.record','تسجيلُ تحصيل','App\\Services\\Revenue\\CollectionService','record','collection.reverse',1),
    array('payroll.run','فتحُ مسيّر الرواتب','App\\Services\\Payroll\\PayrollRunService','openRun','payroll.reverse',1),
    array('supplier.settlement.approve','اعتمادُ تسوية تغطية مورد','App\\Services\\Capacity\\SubstituteCoverageService','settle','supplier.settlement.reverse',1),
    array('unit.state.change','تغييرُ حالة وحدة','App\\Services\\Governance\\UnitStateChangeService','apply','unit.state.revert',0),
    array('coverage.substitute.activate','تفعيلُ تغطيةٍ بديلة','App\\Services\\Capacity\\SubstituteCoverageService','approve','coverage.substitute.end',1),
    array('capacity.consume','استهلاكُ قدرةٍ من الدفتر','App\\Services\\Capacity\\CapacityLedgerService','appendLine','unit.chain.reverse',1),
    array('period.provisions.run','تشغيلُ الدوريات الآلية','App\\Services\\Finance\\PeriodicEventService','runProvisions','period.provisions.reverse',1),
);
foreach ($ten as $t) {
    seed($t[0], $t[1], null, 'header', null, $t[2], $t[3], 1, $G, $t[4], $t[5], 'ACT-01');
}
// أفعالُ العكس — عاكسةٌ بطبيعتها: عكسُها هو الأصلُ الذي تعكسه (self-inverse pair)
// فتحمل reverse_action_code يشير إلى أصلها — ولا تُترك NULL فتكسر الفحص ⑦
$reverses = array(
    array('unit.chain.reverse','عكسُ استهلاك وحدةٍ بأسطرٍ عاكسة','App\\Services\\Capacity\\CapacityLedgerService','reverse','unit.chain.approve'),
    array('invoice.cancel','إلغاءُ فاتورةٍ بإشعارٍ لا حذف','App\\Services\\Revenue\\TaxInvoiceService','cancel','invoice.issue'),
    array('collection.reverse','عكسُ تخصيص تحصيل','App\\Services\\Revenue\\CollectionService','allocate','collection.record'),
    array('payroll.reverse','عكسُ خصومات مسيّر','App\\Services\\Payroll\\OffsetService','reverseRunDeductions','payroll.run'),
    array('supplier.settlement.reverse','تعديلُ حصةٍ أصليةٍ بقرار','App\\Services\\Capacity\\SubstituteCoverageService','modifyOriginalShare','supplier.settlement.approve'),
    array('entitlement.gate.reverse','اعتراضُ سطرٍ في السلسلة','App\\Services\\Policy\\UnitJourneyService','objectLine','entitlement.gate.approve'),
    array('unit.state.revert','طلبُ تغييرٍ عكسيٍّ لحالة وحدة','App\\Services\\Governance\\UnitStateChangeService','request','unit.state.change'),
    array('coverage.substitute.end','تسويةُ تغطيةٍ وإقفالُها','App\\Services\\Capacity\\SubstituteCoverageService','settle','coverage.substitute.activate'),
    array('period.provisions.reverse','عكسُ حدثٍ دوريٍّ بمرجعه','App\\Services\\Finance\\PeriodicEventService','runProvisions','period.provisions.run'),
);
foreach ($reverses as $t) {
    seed($t[0], $t[1], null, 'header', null, $t[2], $t[3], 1, $G, $t[4], 1, 'ACT-01');
}

echo "بُذر/حُدّث $count فعلًا\n";
$r = mysqli_query($conn, "SELECT COUNT(*) c, SUM(is_write) w, SUM(is_financial) f FROM actions WHERE active=1");
$x = mysqli_fetch_assoc($r);
echo "الإجمالي: {$x['c']} · كتابة: {$x['w']} · مالي: {$x['f']}\n";
