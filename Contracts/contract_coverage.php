<?php
/**
 * Contracts/contract_coverage.php — تبويبُ «التغطيةُ التعاقدية» (CAP-35/36 · CAP-01 §11)
 * ───────────────────────────────────────────────────────────────────────────
 * «الواجهةُ تعرض ما يفهمه صاحبُ العمل» (§1): المستوياتُ الثلاثة داخل ملف العقد —
 *   نوعُ المعدة ← توزيعُ الموردين ← المعداتُ المخصَّصة
 * والفجوةُ **بالساعات لا بالعدد فقط** (§10-①) · والمؤشراتُ التسعةُ لأداء المورد
 * تُعرض معًا (§9.1 — ⑦ لا تدخل ④ ولا ترفع ⑥ يفرضها المجمِّع لا الشاشة).
 * ولا عنصرَ في القائمة الجانبية باسم «الحاويات» — الدخولُ من ملف العقد.
 * قراءةٌ حصرًا — صفرُ كتابةٍ من هذه الشاشة.
 * والسرية (§3 · C7): لا مالكَ ولا ممولَ ولا شروطَ تمويلٍ هنا — المجالُ المقيَّدُ بيتُه FIN.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../app/Services/Capacity/SupplierPerformanceAggregator.php';
require_once __DIR__ . '/../app/Services/Capacity/BalanceCalculator.php';

use App\Services\Capacity\SupplierPerformanceAggregator as AGG;

$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) { header('Location: ../login.php'); exit(); }
$gate = ems_tenant_db();

$module_info = null;
try {
    $module_info = $gate->selectOne('modules', array(
        'columns' => array('id'), 'where' => array('code' => 'Contracts/contract_coverage.php')));
} catch (\Throwable $t) { $module_info = null; }
$can_view = false;
if ($module_info) {
    $perms = get_module_permissions($conn, $module_info['id']);
    $can_view = $perms['can_view'];
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض التغطية التعاقدية ❌', 'GOV-PERM-403', '');
    exit();
}

$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;
if ($contract_id <= 0) { header('Location: contracts.php'); exit(); }
$cv_e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$period = date('Y-m');

// ── المستوى ①: التزاماتُ أنواع المعدات — المتعاقدُ والمغطى والفجوةُ بالساعات ──
$obligations = array();
try {
    $obligations = $gate->scopedQuery(array('scope' => array('c' => 'contract_commitments')),
        "SELECT c.id, c.commitment_code, c.equipment_type_code, c.primary_units_contracted,
                c.standby_units_required, c.standby_units_allowed,
                c.qty_per_primary_unit_month, c.measure_code, c.plan_state, c.sigma_exception_ref
           FROM contract_commitments c
          WHERE {TENANT_SCOPE} AND c.contract_ref = ? AND c.is_deleted = 0
            AND c.equipment_type_code IS NOT NULL
          ORDER BY c.id", array($contract_id));
} catch (\Throwable $t) { $obligations = array(); }

$today = date('Y-m-d');
$rows1 = array(); $total_gap_hours = 0.0;
foreach ($obligations as $obl) {
    $oblId = (int) $obl['id'];
    $covered = 0;
    try {
        $r = $gate->scopedQuery(array('scope' => array('c' => 'op_containers', 's' => 'seat_assignments')),
            "SELECT COUNT(DISTINCT c.id) n FROM op_containers c
               JOIN seat_assignments s ON s.container_id = c.id AND s.state = 'active'
                    AND (s.assignment_role <> 'احتياطي' OR s.activation_state = 'active')
                    AND s.date_from <= ? AND (s.date_to IS NULL OR s.date_to >= ?)
              WHERE {TENANT_SCOPE} AND c.obl_id = ? AND c.seat_no IS NOT NULL AND c.is_deleted = 0",
            array($today, $today, $oblId));
        $covered = $r ? (int) $r[0]['n'] : 0;
    } catch (\Throwable $t) { $covered = 0; }
    $target = $obl['primary_units_contracted'] !== null ? (int) $obl['primary_units_contracted'] : 0;
    $qtyMonth = $obl['qty_per_primary_unit_month'] !== null ? (float) $obl['qty_per_primary_unit_month'] : 0.0;
    $gapUnits = max(0, $target - $covered);
    $gapHours = round($gapUnits * $qtyMonth, 2);
    $total_gap_hours += $gapHours;
    $rows1[] = array('obl' => $obl, 'covered' => $covered, 'gap_units' => $gapUnits,
        'gap_hours' => $gapHours, 'hours_month' => round($target * $qtyMonth, 2),
        'pct' => $target > 0 ? round($covered / $target * 100) : 0);
}

// ── المستوى ②: توزيعُ الموردين لكل التزام + المؤشراتُ التسعة (§9.1) ──
$suppliersOf = function ($oblId) use ($gate, $period) {
    try {
        $lines = $gate->scopedQuery(array(
                'scope' => array('l' => 'supplier_contract_lines', 'h' => 'supplier_contracts'),
                'enrich' => array('s' => 'suppliers')),
            "SELECT l.id, l.primary_units_committed, l.standby_units_allowed, l.replacement_sla_hours,
                    h.supplier_id, s.name AS supplier_name
               FROM supplier_contract_lines l
               JOIN supplier_contracts h ON h.id = l.contract_id AND COALESCE(h.is_deleted,0) = 0
               LEFT JOIN suppliers s ON s.id = h.supplier_id
              WHERE {TENANT_SCOPE} AND l.contract_obligation_ref = ? AND l.is_deleted = 0
                AND l.state = 'active'
              ORDER BY l.id", array((int) $oblId));
    } catch (\Throwable $t) { return array(); }
    foreach ($lines as &$ln) {
        try { $ln['nine'] = AGG::nineIndicators($gate, (int) $ln['id'], $period); }
        catch (\Throwable $t) { $ln['nine'] = null; }
    }
    return $lines;
};

// ── المستوى ③: المعداتُ المخصَّصة لكل التزام ──
$seatsOf = function ($oblId) use ($gate) {
    try {
        return $gate->scopedQuery(array(
                'scope' => array('c' => 'op_containers', 's' => 'seat_assignments'),
                'enrich' => array('e' => 'equipments')),
            "SELECT c.seat_no, s.id AS asg_id, s.date_from, s.date_to, s.assignment_role,
                    s.activation_state, s.planned_qty_month, s.measure_code, e.name AS eq_label, s.equipment_id
               FROM op_containers c
               JOIN seat_assignments s ON s.container_id = c.id
               LEFT JOIN equipments e ON e.id = s.equipment_id
              WHERE {TENANT_SCOPE} AND c.obl_id = ? AND c.seat_no IS NOT NULL AND c.is_deleted = 0
              ORDER BY c.seat_no, s.date_from", array((int) $oblId));
    } catch (\Throwable $t) { return array(); }
};

$page_title = 'التغطية التعاقدية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell scr-contract-coverage">
    <?php
    $header_title = 'التغطية التعاقدية — عقد #' . $contract_id;
    $header_icon = 'fas fa-shield-halved';
    $header_actions = array();
    $header_back = array('href' => 'contracts_details.php?id=' . $contract_id, 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'ملف العقد');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا
    echo ems_states_bundle('لا بنودَ تغطيةٍ محسوبةً لهذا العقد',
                           'افتح «ملفَّ العقد» وسجّل بنودَ الخدمةِ وساعاتِها ثمّ عُد إلى هذه الشاشة');
    ?>

    <!-- الفجوةُ بالساعات — تتصدر لوحةَ العقد (§10-①) -->
    <div class="cov-gap-banner <?php echo $total_gap_hours > 0 ? 'is-gap' : 'is-ok'; ?>">
        <i class="fas <?php echo $total_gap_hours > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?>"></i>
        <?php if ($total_gap_hours > 0): ?>
            فجوةُ التغطية الآن: <strong><?php echo number_format($total_gap_hours, 2); ?> ساعةً شهريةً</strong>
            غيرُ مغطاة — بالساعات لا بالعدد فقط، فالفجوةُ التي تُكتشف آخرَ الشهر خسارةٌ وقعت
        <?php else: ?>
            التغطيةُ مكتملةٌ اليوم — صفرُ ساعاتٍ غيرِ مغطاة
        <?php endif; ?>
    </div>

    <!-- المستوى ① · التزاماتُ أنواع المعدات -->
    <div class="card"><div class="card-header"><h5><i class="fas fa-layer-group"></i> نوعُ المعدة — الالتزامُ والتغطيةُ والفجوة</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="display no-datatable cov-table">
            <thead><tr>
                <th>نوع المعدة</th><th>العدد المتعاقد عليه</th><th>المغطى</th><th>فجوة التغطية</th>
                <th>ساعة شهرية</th><th>الفجوة بالساعات</th><th>التغطية٪</th><th>الحالة</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم البند</th>
                <th class="ems-fn-th" data-fn="1">العقد</th>
                <th class="ems-fn-th" data-fn="1">نموذج العمل</th>
                <th class="ems-fn-th" data-fn="1">وحدة العمل</th>
                <th class="ems-fn-th" data-fn="1">الوحدات الاحتياطية</th>
                <th class="ems-fn-th" data-fn="1">عدد الورديات المتفق عليها</th>
                <th class="ems-fn-th" data-fn="1">وحدات الوردية الواحدة</th>
                <th class="ems-fn-th" data-fn="1">الساعات الشهرية للوحدة</th>
                <th class="ems-fn-th" data-fn="1">إجمالي ساعات العقد</th>
                <th class="ems-fn-th" data-fn="1">الحد الأدنى المضمون</th>
                <th class="ems-fn-th" data-fn="1">الساعات المعرَّضة للخطر</th>
                <th class="ems-fn-th" data-fn="1">قاعدة الزيادة</th>
                <th class="ems-fn-th" data-fn="1">سعر الوحدة</th>
                <th class="ems-fn-th" data-fn="1">تاريخ السريان</th>
                <th class="ems-fn-th none" data-fn="1">نسخة القاعدة المستعملة</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php if (empty($rows1)): ?>
                <tr><td colspan="8">لا التزاماتِ أنواعٍ معرَّفةً لهذا العقد — عرِّفها في شاشة التزامات العقود</td></tr>
            <?php endif; ?>
            <?php foreach ($rows1 as $r): $o = $r['obl']; ?>
                <tr>
                    <td><strong><?php echo $cv_e($o['equipment_type_code']); ?></strong>
                        <small>(<?php echo $cv_e($o['commitment_code']); ?>)</small></td>
                    <td><?php echo (int) $o['primary_units_contracted']; ?>
                        <small>+ احتياطي ≤ <?php echo $cv_e($o['standby_units_allowed'] ?? '—'); ?></small></td>
                    <td><?php echo (int) $r['covered']; ?></td>
                    <td class="<?php echo $r['gap_units'] > 0 ? 'cov-bad' : ''; ?>"><?php echo (int) $r['gap_units']; ?></td>
                    <td><?php echo number_format($r['hours_month'], 2) . ' ' . $cv_e($o['measure_code'] ?? 'hour'); ?></td>
                    <td class="<?php echo $r['gap_hours'] > 0 ? 'cov-bad' : 'cov-ok'; ?>">
                        <strong><?php echo number_format($r['gap_hours'], 2); ?></strong></td>
                    <td><?php echo (int) $r['pct']; ?>٪</td>
                    <td><?php echo $cv_e($o['plan_state']); ?>
                        <?php if (!empty($o['sigma_exception_ref'])): ?>
                            <small title="اعتمادٌ بفجوةٍ ظاهرةٍ بقرار استثناء">⚠ <?php echo $cv_e($o['sigma_exception_ref']); ?></small>
                        <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php foreach ($rows1 as $r): $o = $r['obl']; $oblId = (int) $o['id'];
        $sup = $suppliersOf($oblId); $seats = $seatsOf($oblId); ?>
    <!-- المستوى ② · توزيعُ الموردين — داخل نوع «<?php echo $cv_e($o['equipment_type_code']); ?>» -->
    <div class="card cov-lvl2"><div class="card-header">
        <h5><i class="fas fa-truck-field"></i> توزيعُ الموردين — <?php echo $cv_e($o['equipment_type_code']); ?></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="display no-datatable cov-table">
            <thead><tr>
                <th>المتاح من الموردين</th><th>وحداته</th><th>ساعته الشهرية (مشتقة)</th><th>مهلة الإحلال</th>
                <th colspan="9">المؤشراتُ التسعة — <?php echo $cv_e($period); ?> (⑦ لا تدخل ④ ولا ترفع ⑥)</th>
            </tr>
            <tr class="cov-sub">
                <th colspan="4"></th>
                <th>① المخطط</th><th>② بالأساسية</th><th>③ باحتياطيّه</th><th>④ التنفيذ (②+③)</th>
                <th>قاعدة العجز</th><th>⑥ النسبة</th><th>⑦ تغطيةٌ أعطاها</th><th>⑧ غُطّي عنه</th><th>⑨ المتبقي</th>
            </tr></thead>
            <tbody>
            <?php if (empty($sup)): ?>
                <tr><td colspan="13">لا حصصَ موردين مرتبطةً بهذا الالتزام بعد</td></tr>
            <?php endif; ?>
            <?php foreach ($sup as $ln): $n = $ln['nine']; ?>
                <tr>
                    <td><strong><?php echo $cv_e($ln['supplier_name'] ?? ('مورد #' . $ln['supplier_id'])); ?></strong></td>
                    <td><?php echo (int) $ln['primary_units_committed']; ?>
                        <small>+ احتياطي ≤ <?php echo $cv_e($ln['standby_units_allowed'] ?? '—'); ?></small></td>
                    <td><?php echo $n ? number_format($n['planned'], 2) : '—'; ?></td>
                    <td><?php echo $ln['replacement_sla_hours'] !== null ? $cv_e($ln['replacement_sla_hours']) . ' س' : '—'; ?></td>
                    <?php if ($n): ?>
                        <td><?php echo number_format($n['planned'], 1); ?></td>
                        <td><?php echo number_format($n['executed_primary'], 1); ?></td>
                        <td><?php echo number_format($n['executed_standby'], 1); ?></td>
                        <td><strong><?php echo number_format($n['executed_share_total'], 1); ?></strong></td>
                        <td class="<?php echo $n['share_gap'] > 0 ? 'cov-bad' : ''; ?>"><?php echo number_format($n['share_gap'], 1); ?></td>
                        <td><?php echo number_format($n['share_execution_pct'], 1); ?>٪</td>
                        <td class="cov-sep"><?php echo number_format($n['exceptional_coverage_given'], 1); ?> ← بندٌ مستقل</td>
                        <td><?php echo number_format($n['coverage_received'], 1); ?></td>
                        <td><?php echo number_format($n['remaining_contract_qty'], 1); ?></td>
                    <?php else: ?>
                        <td colspan="9">—</td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <!-- المستوى ③ · المعداتُ المخصَّصة -->
    <div class="card cov-lvl3"><div class="card-header">
        <h5><i class="fas fa-truck-monster"></i> المعداتُ المخصَّصة — <?php echo $cv_e($o['equipment_type_code']); ?></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="display no-datatable cov-table">
            <thead><tr>
                <th>الوحدة التعاقدية</th><th>المعدة</th><th>المتاح من أسطولنا</th><th>إجمالي الساعات الشهرية</th>
                <th>الدور</th><th>الحصة الشهرية المخططة</th>
            </tr></thead>
            <tbody>
            <?php if (empty($seats)): ?>
                <tr><td colspan="6">لا معداتٍ مخصَّصةً بعد — الفجوةُ كاملة</td></tr>
            <?php endif; ?>
            <?php foreach ($seats as $s): ?>
                <tr>
                    <td>الآلية رقم <?php echo (int) $s['seat_no']; ?></td>
                    <td><?php echo $cv_e($s['eq_label'] ?? ('معدة #' . $s['equipment_id'])); ?></td>
                    <td><?php echo $cv_e($s['date_from']); ?></td>
                    <td><?php echo $cv_e($s['date_to'] ?? 'جالسة الآن'); ?></td>
                    <td><?php echo $cv_e($s['assignment_role']); ?>
                        <?php if ($s['assignment_role'] === 'احتياطي' && $s['activation_state'] !== 'active'): ?>
                            <small>(غيرُ مفعَّلة — صفرُ ساعات)</small>
                        <?php endif; ?></td>
                    <td><?php echo $s['planned_qty_month'] !== null
                        ? number_format((float) $s['planned_qty_month'], 2) . ' ' . $cv_e($s['measure_code'] ?? '') : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endforeach; ?>
</div>

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
</body>
</html>
