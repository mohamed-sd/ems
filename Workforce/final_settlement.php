<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Workforce/final_settlement.php — تصفيةُ إنهاء خدمة الموظف (M-22)
 * ───────────────────────────────────────────────────────────────────────────
 * ENT-01 §6: «معالج: **بنودُ التصفية محسوبةً من اللقطة** · الرصيدُ الصافي
 * **بعد المقاصّة** · **مرفقاتُ الإخلاء**».
 * واليدان مفصولتان: شؤونُ الموظفين تُعدّ · والمالية تعتمد — «لا يعتمد المرءُ ما أعدّ».
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Payroll/FinalSettlementService.php';

use App\Services\Payroll\FinalSettlementService as FS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$MODULE_CODE = 'Workforce/final_settlement.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_add = $can_edit = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add']) === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض تصفية إنهاء الخدمة ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('final settlement super') : ems_tenant_db();
$redirect = function ($msg) { ems_gov_flash_redirect('final_settlement.php', $msg, 'GOV-INFO-200', ''); exit(); };

$action = strval($_POST['fs_action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'open') {
    if (!$can_add) { $redirect('لا توجد صلاحية لفتح تصفية ❌'); }
    $r = FS::open($conn, $gate, $company_id, intval($_POST['contract_id'] ?? 0),
                  strval($_POST['effective_date'] ?? ''), $uid);
    $redirect($r['ok']
        ? ('فتحت التصفية #' . $r['settlement_id'] . ' بصاف ' . $r['net'] . ' ✅')
        : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve') {
    if (!$can_edit) { $redirect('الاعتماد ليد الإجازة — لا صلاحية ❌'); }
    // ORG-13 · حارس الأذونات: خروج عامل نهائيًّا — الحركة + الموارد البشرية (ORG-01 §5-⑨)
    require_once dirname(__DIR__) . '/includes/permit_gate.php';
    $pg = ems_permit_gate($conn, $company_id, 'worker_final_exit',
        'FS:' . intval($_POST['settlement_id'] ?? 0), 0, $uid);
    if (!$pg['ok']) { $redirect($pg['reason'] . ' ❌'); }
    $r = FS::approve($conn, $gate, $company_id, intval($_POST['settlement_id'] ?? 0),
                     strval($_POST['clearance_doc'] ?? ''), $uid);
    $redirect($r['ok']
        ? ('اعتمدت التصفية · ذمة #' . ($r['due_id'] ?? '—') . ' · استرد ' . $r['recovered'] . ' ✅')
        : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancel') {
    if (!$can_add) { $redirect('لا توجد صلاحية ❌'); }
    $r = FS::cancel($conn, $gate, $company_id, intval($_POST['settlement_id'] ?? 0),
                    strval($_POST['cancel_reason'] ?? ''), $uid);
    $redirect($r['ok'] ? 'ألغيت التصفية بسببها المكتوب ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

$candidates  = FS::settleableContracts($gate);
$settlements = FS::listAll($gate, 100);

// معاينةٌ محسوبةٌ **قبل** الفتح — «بنودُ التصفية محسوبةً من اللقطة»
$previewFor = intval($_GET['preview'] ?? 0);
$previewOn  = strval($_GET['as_of'] ?? date('Y-m-d'));
$preview    = null;
if ($previewFor > 0) {
    $preview = FS::compute($conn, $gate, $company_id, $previewFor, $previewOn, $uid);
}

$LINE_LABEL = array('dues' => 'المستحق حتى تاريخ الأثر', 'leave' => 'رصيد الإجازات',
                    'eos' => 'نهاية الخدمة', 'advance_offset' => 'مقاصة السلف والعهد');
$ST_LABEL   = array('draft' => 'مسودة', 'approved' => 'معتمدة', 'cancelled' => 'ملغاة');

$page_title = 'إيكوبيشن | تصفية إنهاء خدمة الموظف';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/entity_tabs.php'; echo ems_entity_tabs('employee', 'إنهاء الخدمة');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// M-14 BR-GOV-07: التصفية كشفُ مستحقاتٍ حساس — القراءةُ تُسجَّل (عطالة يومية)
require_once __DIR__ . '/../includes/sensitive_read_log.php';
ems_log_sensitive_read($conn, 'final_settlement', 'screen:list', 'Workforce/final_settlement.php');
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'تصفية إنهاء خدمة الموظف'; $header_icon = 'fa fa-user-minus';
    $header_actions = array();
    $header_back = array('href' => 'contract_registry.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'سجل عقود الموظفين');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    require_once __DIR__ . '/../includes/ux_components.php';
    echo ems_states_bundle('لا تصفيات إنهاء خدمة ضمن هذا النطاق', 'التصفية يفتحها إنهاء العقد — راجع سجل عقود الموظفين');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list-check"></i>
        عقود منتهية تنتظر التصفية</h5></div>
    <div class="card-body">
        <p class="fs-note-muted">
            <strong>التصفية يفتحها الإنهاء</strong> (ENT-01 §5) — ولكل عقد تصفية واحدة لا تتكرر.
            والبنود <strong>محسوبة من اللقطة</strong>: ما لا قاعدة مكتوبة له يظهر
            <strong>معلنا بسببه</strong> ولا يقدر بصفر صامت.
        </p>
        <?php if (!$candidates): ?>
            <div class="alert alert-warning">لا عقدَ في «منتهٍ · منهًى · مقفل» — فلا تصفيةَ ممكنة.</div>
        <?php else: ?>
        <div class="table-container">
        <table class="alltables display nowrap fs-table-full">
            <thead><tr><th>العقد</th><th>كود الموظف</th><th>الحالة</th><th>المنشئ — الاسم والصفة</th><th>إجمالي المستحقات</th>
                <th>مكافأة نهاية الخدمة</th><th>قاعدة الإجازة</th><th>التصفية</th><th></th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم المحضر</th>
                <th class="ems-fn-th" data-fn="1">تاريخ الالتحاق</th>
                <th class="ems-fn-th" data-fn="1">تاريخ إنهاء الخدمة</th>
                <th class="ems-fn-th" data-fn="1">سبب الإنهاء</th>
                <th class="ems-fn-th" data-fn="1">مدة الخدمة</th>
                <th class="ems-fn-th" data-fn="1">الراتب الأخير</th>
                <th class="ems-fn-th" data-fn="1">رصيد الإجازات</th>
                <th class="ems-fn-th" data-fn="1">بدل الإجازات</th>
                <th class="ems-fn-th" data-fn="1">مستحقات أخرى</th>
                <th class="ems-fn-th" data-fn="1">السلف غير المسددة</th>
                <th class="ems-fn-th" data-fn="1">خصومات</th>
                <th class="ems-fn-th" data-fn="1">العهد غير المخلاة</th>
                <th class="ems-fn-th" data-fn="1">إجمالي الخصومات</th>
                <!-- CMP-03 مراجعة عكسية: «الصافي» كان يُحتسب من tfoot جدول المعاينة — عمود قائمة أصيل -->
                <th class="ems-fn-th" data-fn="1">الصافي</th>
                <th class="ems-fn-th none" data-fn="1">إخلاء طرف المخازن</th>
                <th class="ems-fn-th none" data-fn="1">إخلاء طرف الأسطول</th>
                <th class="ems-fn-th none" data-fn="1">تعطيل الحساب</th>
                <th class="ems-fn-th none" data-fn="1">اعتماد الموارد</th>
                <th class="ems-fn-th none" data-fn="1">الاعتماد المالي</th>
                <th class="ems-fn-th none" data-fn="1">تاريخ السداد</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس ب</th>
                <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليا">درجة الأثر</th>
                <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطلاع</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($candidates as $c): ?>
                <tr>
                    <td>#<?php echo intval($c['id']); ?></td>
                    <td><strong><?php echo htmlspecialchars((string)($c['employee_name'] ?? '—')); ?></strong></td>
                    <td><?php echo htmlspecialchars((string)$c['state']); ?></td>
                    <td><?php echo htmlspecialchars((string)($c['start_date'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($c['end_date'] ?? '—')); ?></td>
                    <td><?php echo $c['eos_days_per_year'] !== null
                        ? htmlspecialchars((string)$c['eos_days_per_year']) . ' يوم/سنة'
                        : '<span class="badge badge-warning">لم تكتب</span>'; ?></td>
                    <td><?php echo $c['leave_days_per_year'] !== null
                        ? htmlspecialchars((string)$c['leave_days_per_year']) . ' يوم/سنة'
                        : '<span class="badge badge-warning">لم تكتب</span>'; ?></td>
                    <td><?php echo $c['settlement_id'] !== null
                        ? ('#' . intval($c['settlement_id']) . ' — '
                           . htmlspecialchars($ST_LABEL[(string)$c['settlement_state']] ?? (string)$c['settlement_state']))
                        : '<span class="badge badge-secondary">لم تفتح</span>'; ?></td>
                    <td><?php if ($c['settlement_id'] === null): ?>
                        <a class="action-btn" href="final_settlement.php?preview=<?php echo intval($c['id']); ?>&as_of=<?php echo htmlspecialchars($previewOn); ?>">
                            <i class="fa fa-calculator"></i> عاين</a>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div></div>

    <?php if ($preview !== null): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-calculator"></i>
        معاينة تصفية العقد #<?php echo $previewFor; ?></h5></div>
    <div class="card-body">
        <form method="get" class="fs-preview-form">
            <input type="hidden" name="preview" value="<?php echo $previewFor; ?>">
            <label for="emsf_569_22ab0">تاريخ الأثر:</label>
            <input type="date" name="as_of" aria-label="تاريخ الأثر" value="<?php echo htmlspecialchars($previewOn); ?>" id="emsf_569_22ab0">
            <button type="submit" class="btn-primary"><i class="fa fa-rotate"></i> أعد الاحتساب</button>
        </form>

        <?php if (!$preview['ok']): ?>
            <div class="alert alert-danger">
                <?php echo intval($preview['code']); ?> — <?php echo htmlspecialchars((string)$preview['reason']); ?>
            </div>
        <?php else: ?>
            <p class="fs-note-muted">
                اللقطة <strong>#<?php echo intval($preview['snapshot_id']); ?></strong>
                ببصمة <code><?php echo htmlspecialchars(substr((string)$preview['fingerprint'], 0, 12)); ?>…</code>
                · سنوات الخدمة <strong><?php echo htmlspecialchars((string)$preview['basis']['service_years']); ?></strong>
                · أساس نهاية الخدمة <strong><?php echo htmlspecialchars((string)$preview['basis']['eos_monthly_base']); ?></strong>
                · أساس الإجازة <strong><?php echo htmlspecialchars((string)$preview['basis']['leave_monthly_base']); ?></strong>
            </p>
            <?php if (!empty($preview['basis']['unreadable_components'])): ?>
                <div class="alert alert-warning">
                    <strong>مكونات بطريقة حساب لا يحملها أساس شهري ثابت — تعلن ولا تقدر:</strong>
                    <?php foreach ($preview['basis']['unreadable_components'] as $u): ?>
                        <div>#<?php echo intval($u['component_id']); ?>
                            — <?php echo htmlspecialchars((string)$u['component_type']); ?>
                            (<?php echo htmlspecialchars((string)$u['calc_method']); ?>)
                            · العلم <?php echo htmlspecialchars((string)$u['flag']); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="table-container">
            <table class="alltables display nowrap fs-table-full">
                <thead><tr><th>البند</th><th>الكمية</th><th>المعدل</th><th>المبلغ</th><th>المصدر</th></tr></thead>
                <tbody>
                <?php foreach ($preview['lines'] as $l): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($LINE_LABEL[$l['line_type']] ?? $l['line_type']); ?></strong>
                            <?php if (!$l['computable']): ?>
                                <span class="badge badge-warning">بلا قاعدة</span>
                            <?php endif; ?></td>
                        <td><?php echo $l['qty'] !== null ? htmlspecialchars((string)$l['qty']) : '—'; ?></td>
                        <td><?php echo $l['rate'] !== null ? htmlspecialchars((string)$l['rate']) : '—'; ?></td>
                        <td><strong><?php echo htmlspecialchars((string)$l['amount']); ?></strong></td>
                        <td class="fs-src-cell"><small><?php echo htmlspecialchars((string)$l['source_note']); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr>
                    <th colspan="3">الصافي</th>
                    <th><?php echo htmlspecialchars((string)$preview['totals']['net']); ?></th>
                    <th><?php echo $preview['totals']['advances_remaining'] > 0
                        ? ('رصيد سلف مفتوح باق: ' . htmlspecialchars((string)$preview['totals']['advances_remaining']))
                        : ''; ?></th>
                </tr></tfoot>
            </table>
            </div>

            <?php if ($can_add): ?>
            <form method="post" class="ems-form fs-open-form">
        <?= csrf_field() ?>
                <input type="hidden" name="fs_action" value="open">
                <input type="hidden" name="contract_id" value="<?php echo $previewFor; ?>">
                <input type="hidden" name="effective_date" value="<?php echo htmlspecialchars($previewOn); ?>">
                <button type="submit" class="btn-primary"><i class="fa fa-file-signature"></i>
                    افتح التصفية بهذه الأرقام</button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-folder-open"></i>
        التصفيات المفتوحة والمعتمدة</h5></div>
    <div class="card-body">
        <?php if (!$settlements): ?>
            <div class="alert alert-info">لا تصفية بعد.</div>
        <?php else: foreach ($settlements as $s): $lines = FS::linesOf($gate, intval($s['id'])); ?>
            <div class="card fs-card-item ems-doc-cycle">
            <div class="card-header"><h5>
                #<?php echo intval($s['id']); ?> —
                <?php echo htmlspecialchars((string)($s['employee_name'] ?? ('موظف ' . intval($s['employee_id'])))); ?>
                <span class="badge <?php echo (string)$s['state'] === 'approved' ? 'badge-success'
                    : ((string)$s['state'] === 'cancelled' ? 'badge-danger' : 'badge-secondary'); ?>">
                    <?php echo htmlspecialchars($ST_LABEL[(string)$s['state']] ?? (string)$s['state']); ?></span>
                <?php /* بوابة ١٢: الخطوةُ التاليةُ من حالِ المستندِ الحي — يدُ الإعدادِ غيرُ يدِ الاعتماد */
                if ((string)$s['state'] === 'draft') {
                    echo ems_next_step('اعتماد التصفية بيد الإجازة المالية — بمرفق الإخلاء');
                } elseif ((string)$s['state'] === 'approved') {
                    echo ems_next_step('صرف الذمة الصافية عبر دورة المدفوعات');
                } ?>
            </h5></div>
            <div class="card-body">
                <div class="fs-meta-row">
                    <div>تاريخ الأثر: <strong><?php echo htmlspecialchars((string)$s['effective_date']); ?></strong></div>
                    <div>سنوات الخدمة: <strong><?php echo htmlspecialchars((string)$s['service_years']); ?></strong></div>
                    <div>الصافي: <strong><?php echo htmlspecialchars((string)$s['net_amount']); ?>
                        <?php echo htmlspecialchars((string)$s['currency']); ?></strong></div>
                    <div>المعترف به جديدا: <strong><?php echo htmlspecialchars((string)$s['recognized_amount']); ?></strong></div>
                    <div>اللقطة: <strong>#<?php echo intval($s['snapshot_id']); ?></strong></div>
                    <?php if ($s['net_due_ref'] !== null): ?>
                        <div>الذمة: <strong>#<?php echo intval($s['net_due_ref']); ?></strong></div>
                    <?php endif; ?>
                    <?php if ((float)$s['advances_remaining'] > 0): ?>
                        <div><span class="badge badge-warning">رصيد سلف باق
                            <?php echo htmlspecialchars((string)$s['advances_remaining']); ?></span></div>
                    <?php endif; ?>
                </div>
                <div class="table-container">
                <table class="alltables display nowrap no-datatable fs-table-full" data-no-dt="1">
                    <thead><tr><th>البند</th><th>الكمية</th><th>المعدل</th><th>المبلغ</th><th>المصدر</th></tr></thead>
                    <tbody>
                    <?php foreach ($lines as $l): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($LINE_LABEL[(string)$l['line_type']] ?? (string)$l['line_type']); ?>
                                <?php if (!intval($l['computable'])): ?>
                                    <span class="badge badge-warning">بلا قاعدة</span>
                                <?php endif; ?></td>
                            <td><?php echo $l['qty'] !== null ? htmlspecialchars((string)$l['qty']) : '—'; ?></td>
                            <td><?php echo $l['rate'] !== null ? htmlspecialchars((string)$l['rate']) : '—'; ?></td>
                            <td><strong><?php echo htmlspecialchars((string)$l['amount']); ?></strong></td>
                            <td class="fs-src-cell"><small><?php echo htmlspecialchars((string)$l['source_note']); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <?php if ((string)$s['state'] === 'draft'): ?>
                    <?php if ($can_edit): ?>
                    <form method="post" class="ems-form fs-approve-form">
        <?= csrf_field() ?>
                        <input type="hidden" name="fs_action" value="approve">
                        <input type="hidden" name="settlement_id" value="<?php echo intval($s['id']); ?>">
                        <div class="form-grid">
                            <div class="form-group"><label for="emsf_570_4f3bf">مرفق الإخلاء <span class="fs-required">*</span></label>
                                <input type="text" name="clearance_doc" maxlength="120" required
                                       placeholder="مرجع محضر الإخلاء" id="emsf_570_4f3bf"></div>
                        </div>
                        <button type="submit" class="btn-primary"><i class="fa fa-check"></i>
                            اعتمد التصفية</button>
                        <small class="fs-note-muted">— والاعتماد يولد <strong>الحدث المالي الواحد</strong>
                            ويسترد السلف فعليا.</small>
                    </form>
                    <?php endif; ?>
                    <?php if ($can_add): ?>
                    <form method="post" class="ems-form fs-cancel-form">
        <?= csrf_field() ?>
                        <input type="hidden" name="fs_action" value="cancel">
                        <input type="hidden" name="settlement_id" value="<?php echo intval($s['id']); ?>">
                        <div class="form-grid">
                            <div class="form-group"><label for="emsf_571_348a7">سبب الإلغاء <span class="fs-required">*</span></label>
                                <input type="text" name="cancel_reason" maxlength="255" required id="emsf_571_348a7"></div>
                        </div>
                        <button type="submit" class="btn-secondary"><i class="fa fa-ban"></i> ألغ المسودة</button>
                    </form>
                    <?php endif; ?>
                <?php elseif ((string)$s['state'] === 'approved'): ?>
                    <div class="alert alert-success fs-alert-gap">
                        اعتمدت بمرفق الإخلاء
                        <strong><?php echo htmlspecialchars((string)$s['clearance_doc']); ?></strong>
                        — والتصحيح بعدها <strong>بحركة عاكسة لا بمحو</strong>.
                    </div>
                <?php elseif ((string)$s['state'] === 'cancelled'): ?>
                    <div class="alert alert-warning fs-alert-gap">
                        ألغيت: <?php echo htmlspecialchars((string)$s['cancel_reason']); ?>
                    </div>
                <?php endif; ?>
            </div></div>
        <?php endforeach; endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
