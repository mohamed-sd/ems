<?php
/**
 * Finance/periodic_events_fin.php — الدورياتُ المالية (M-41)
 * ───────────────────────────────────────────────────────────────────────────
 * SPEC-01 #23 · #30 · #22 — ثلاثُ دورياتٍ بمفاتيحها في شاشةٍ واحدة:
 * مخصصُ الصيانة (المعدة × الفترة) · قسطُ التمويل (الالتزام × القسط) ·
 * الإقرارُ الضريبي (الفترة). و**كلُّ رقمٍ هنا محسوبٌ من مصدره** — ولا حقلَ
 * لكتابة مبلغٍ بيد.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';
require_once __DIR__ . '/../app/Services/Finance/PeriodicEventService.php';

use App\Services\Finance\PeriodicEventService as PES;

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $uid = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/periodic_events_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_edit = $perms['can_edit'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الدوريات ❌', 'GOV-PERM-403', ''); exit(); }

$gate = fin_gate($is_super_admin);
$redirect = function ($msg) { ems_gov_flash_redirect('periodic_events_fin.php', $msg, 'GOV-INFO-200', ''); exit(); };

$period = (isset($_GET['period']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['period']))
          ? (string) $_GET['period'] : date('Y-m', strtotime('first day of last month'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    $act = strval($_POST['per_action'] ?? '');
    $p   = (preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['period'] ?? ''))) ? (string) $_POST['period'] : $period;
    if ($act === 'provisions') {
        $r = PES::runProvisions($conn, $gate, $company_id, $p, $uid, 'screen');
        $redirect(($r['ok'] ? $r['reason'] . ' ✅' : $r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
    if ($act === 'installments') {
        $r = PES::accrueInstallments($conn, $gate, $company_id, date('Y-m-t', strtotime($p . '-01')), $uid);
        $redirect(($r['ok'] ? $r['reason'] . ' ✅' : $r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
    if ($act === 'tax_return') {
        $r = PES::fileTaxReturn($conn, $gate, $company_id, $p, $uid);
        $redirect(($r['ok'] ? $r['reason'] . ' ✅' : $r['code'] . ' — ' . $r['reason'] . ' ❌'));
    }
    if ($act === 'add_rule') {
        $rate = trim(strval($_POST['rate'] ?? ''));
        $from = strval($_POST['effective_from'] ?? '');
        if ($rate === '' || !is_numeric($rate) || (float) $rate <= 0) { $redirect('المعدّلُ رقمٌ موجبٌ إلزامي ❌'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $redirect('تاريخُ السريان إلزامي ❌'); }
        $eq = intval($_POST['equipment_id'] ?? 0);
        $ty = intval($_POST['equipment_type'] ?? 0);
        try {
            $gate->insert('fin_maint_provision_rules', array(
                'equipment_id' => $eq > 0 ? $eq : null,
                'equipment_type' => $ty > 0 ? $ty : null,
                'basis' => in_array(strval($_POST['basis'] ?? ''), array('hour', 'unit'), true)
                           ? strval($_POST['basis']) : 'hour',
                'rate' => round((float) $rate, 4),
                'currency' => mb_substr(trim(strval($_POST['currency'] ?? 'SDG')), 0, 8) ?: 'SDG',
                'effective_from' => $from, 'state' => 'active',
                'note' => mb_substr(trim(strval($_POST['note'] ?? '')), 0, 200) ?: null,
                'created_by' => $uid,
            ));
            $redirect('أُضيفت قاعدةُ المخصص ✅');
        } catch (\Throwable $t) { $redirect('تعذّرت الإضافة: ' . $t->getMessage() . ' ❌'); }
    }
}

$rules = array();
try {
    $rules = $gate->scopedQuery(array('scope' => array('r' => 'fin_maint_provision_rules')),
        "SELECT r.* FROM fin_maint_provision_rules r WHERE {TENANT_SCOPE}
           AND COALESCE(r.is_deleted,0)=0 ORDER BY r.state, r.effective_from DESC, r.id DESC LIMIT 100");
} catch (\Throwable $t) { $rules = array(); }
$provs = PES::provisionsOf($gate, $period);
$insts = PES::dueInstallments($gate, date('Y-m-t', strtotime($period . '-01')));
$rets  = PES::returnsOf($gate);

$page_title = 'إيكوبيشن | الدوريات المالية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'الدوريات المالية'; $header_icon = 'fa fa-repeat';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php fin_msg_banner(); ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;line-height:1.8;margin:0 0 10px">
            <i class="fas fa-circle-info"></i>
            ثلاثُ دورياتٍ <strong>كلٌّ بمفتاحها الذي يمنع التكرار</strong>:
            مخصصُ الصيانة <code>(المعدة × الفترة)</code> · قسطُ التمويل <code>(الالتزام × القسط)</code> ·
            الإقرارُ الضريبي <code>(الفترة)</code>.
            و<strong>لا كتابةَ يدويةً على الدفتر</strong>: كلُّ مبلغٍ محسوبٌ من مصدره —
            ولا يقع شيءٌ منها في <strong>فترةٍ مقفلة</strong>.
        </p>
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <label for="emsf_254_00734">الفترة:</label>
            <input type="month" name="period" value="<?php echo htmlspecialchars($period); ?>" id="emsf_254_00734">
            <button type="submit" class="btn-primary"><i class="fa fa-filter"></i> اعرض</button>
        </form>
        <?php if ($can_edit): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
            <?php foreach (array(
                'provisions'   => array('مخصصُ الصيانة', 'fa-screwdriver-wrench'),
                'installments' => array('أقساطُ التمويل المستحقة', 'fa-file-invoice-dollar'),
                'tax_return'   => array('الإقرارُ الضريبي', 'fa-percent'),
            ) as $k => $v): ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="per_action" value="<?php echo $k; ?>">
                <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                <button type="submit" class="btn-primary"><i class="fa <?php echo $v[1]; ?>"></i>
                    شغّل <?php echo $v[0]; ?></button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div></div>

    <?php if ($can_edit): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-plus"></i> قاعدةُ مخصصِ صيانةٍ جديدة</h5></div>
    <div class="card-body">
        <form method="post" class="ems-form">
            <input type="hidden" name="per_action" value="add_rule">
            <div class="form-grid">
                <div class="form-group"><label for="emsf_255_f7046">معدةٌ بعينها <small>— 0 = الأعمّ</small></label>
                    <input type="number" name="equipment_id" min="0" value="0" id="emsf_255_f7046"></div>
                <div class="form-group"><label for="emsf_256_a476e">نوعُ المعدة <small>— 0 = أي نوع</small></label>
                    <input type="number" name="equipment_type" min="0" value="0" id="emsf_256_a476e"></div>
                <div class="form-group"><label for="emsf_257_a43c9">الأساس</label>
                    <select name="basis" id="emsf_257_a43c9"><option value="hour">ساعة</option><option value="unit">وحدة</option></select></div>
                <div class="form-group"><label for="emsf_258_b3336">المعدّل <span style="color:#c00">*</span></label>
                    <input type="number" name="rate" step="0.0001" min="0.0001" required id="emsf_258_b3336"></div>
                <div class="form-group"><label for="emsf_259_38d9e">العملة</label><input type="text" name="currency" value="SDG" maxlength="8" id="emsf_259_38d9e"></div>
                <div class="form-group"><label for="emsf_260_e7a32">سريان من <span style="color:#c00">*</span></label>
                    <input type="date" name="effective_from" required id="emsf_260_e7a32"></div>
                <div class="form-group"><label for="emsf_261_3ad4a">مرجعُ القاعدة</label><input type="text" name="note" maxlength="200" id="emsf_261_3ad4a"></div>
            </div>
            <div style="margin-top:12px"><button type="submit" class="btn-primary"><i class="fa fa-save"></i> أضف القاعدة</button></div>
        </form>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-screwdriver-wrench"></i>
        ① قواعدُ المخصص ومخصصاتُ <?php echo htmlspecialchars($period); ?></h5></div>
    <div class="card-body">
        <div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>القاعدة</th><th>المعدة</th><th>النوع</th><th>الأساس</th><th>المعدّل</th>
                <th>السريان</th><th>الحال</th></tr></thead>
            <tbody>
            <?php foreach ($rules as $r): ?>
                <tr><td>#<?php echo intval($r['id']); ?></td>
                    <td><?php echo $r['equipment_id'] !== null ? intval($r['equipment_id']) : '<em>الأعمّ</em>'; ?></td>
                    <td><?php echo $r['equipment_type'] !== null ? intval($r['equipment_type']) : '—'; ?></td>
                    <td><?php echo (string)$r['basis'] === 'hour' ? 'ساعة' : 'وحدة'; ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$r['rate']); ?></strong>
                        <?php echo htmlspecialchars((string)$r['currency']); ?></td>
                    <td style="direction:ltr"><?php echo htmlspecialchars((string)$r['effective_from']); ?>
                        → <?php echo htmlspecialchars((string)($r['effective_to'] ?? '…')); ?></td>
                    <td><?php echo (string)$r['state'] === 'active'
                        ? '<span class="badge badge-success">سارية</span>'
                        : '<span class="badge badge-secondary">منتهية</span>'; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$rules): ?><tr><td colspan="7"><em>لا قواعدَ — وبلا قاعدةٍ <strong>لا مخصص</strong></em></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
        <div class="table-container" style="margin-top:12px">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>المعدة</th><th>الأساس</th><th>الكمية</th><th>المعدّل</th><th>المبلغ</th>
                <th>القاعدة</th><th>الحدث</th></tr></thead>
            <tbody>
            <?php foreach ($provs as $p): ?>
                <tr><td><?php echo intval($p['equipment_id']); ?></td>
                    <td><?php echo (string)$p['basis'] === 'hour' ? 'ساعة' : 'وحدة'; ?></td>
                    <td><?php echo htmlspecialchars((string)$p['qty']); ?></td>
                    <td><?php echo htmlspecialchars((string)$p['rate']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$p['amount']); ?></strong>
                        <?php echo htmlspecialchars((string)$p['currency']); ?></td>
                    <td>#<?php echo intval($p['rule_id']); ?></td>
                    <td><?php echo $p['event_id'] !== null
                        ? ('<span class="badge badge-success">#' . intval($p['event_id']) . '</span>')
                        : '<span class="badge badge-warning">بلا حدث</span>'; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$provs): ?><tr><td colspan="7"><em>لا مخصصَ لهذه الفترة بعد</em></td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-file-invoice-dollar"></i>
        ② أقساطُ التمويل المستحقة حتى نهاية <?php echo htmlspecialchars($period); ?></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>الالتزام</th><th>القسط</th><th>الاستحقاق</th><th>الأصل</th><th>الربح</th>
                <th>الإجمالي</th><th>الحال</th><th>الاعتراف</th></tr></thead>
            <tbody>
            <?php foreach ($insts as $s): ?>
                <tr><td><?php echo htmlspecialchars((string)($s['facility_no'] ?? ('#' . intval($s['facility_id'])))); ?></td>
                    <td><?php echo intval($s['installment_no']); ?></td>
                    <td><?php echo htmlspecialchars((string)$s['due_date']); ?></td>
                    <td><?php echo htmlspecialchars((string)$s['principal_due']); ?></td>
                    <td><?php echo htmlspecialchars((string)$s['profit_due']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$s['total_due']); ?></strong>
                        <?php echo htmlspecialchars((string)($s['currency'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)$s['state']); ?></td>
                    <td><?php echo $s['event_id'] !== null
                        ? ('<span class="badge badge-success">حدث #' . intval($s['event_id']) . '</span>')
                        : '<span class="badge badge-warning">بلا اعتراف</span>'; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$insts): ?><tr><td colspan="8"><em>لا قسطَ مستحقًّا حتى هذا التاريخ</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-percent"></i>
        ③ الإقراراتُ الضريبية</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>الفترة</th><th>المبيعاتُ الخاضعة</th><th>ضريبةُ المخرجات</th>
                <th>المشتريات</th><th>ضريبةُ المدخلات</th><th>الصافي</th><th>الحركات</th>
                <th>الحال</th><th>الحدث</th>
                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($rets as $t): ?>
                <tr><td><strong><?php echo htmlspecialchars((string)$t['period_ref']); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)$t['taxable_sales']); ?></td>
                    <td><?php echo htmlspecialchars((string)$t['output_tax']); ?></td>
                    <td><?php echo htmlspecialchars((string)$t['taxable_purchases']); ?></td>
                    <td><?php echo htmlspecialchars((string)$t['input_tax']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)$t['net_tax']); ?></strong></td>
                    <td><?php echo intval($t['lines_count']) === 0
                        ? '<span class="badge badge-warning">صفر — يُعلَن</span>'
                        : intval($t['lines_count']); ?></td>
                    <td><?php echo (string)$t['state'] === 'filed'
                        ? '<span class="badge badge-success">مقدَّم</span>'
                        : '<span class="badge badge-secondary">مسودة</span>'; ?></td>
                    <td><?php echo $t['event_id'] !== null ? ('#' . intval($t['event_id'])) : '—'; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$rets): ?><tr><td colspan="9"><em>لا إقراراتٍ بعد</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
