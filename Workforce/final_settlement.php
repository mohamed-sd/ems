<?php
/**
 * Workforce/final_settlement.php — تصفيةُ إنهاء خدمة الموظف (M-22)
 * ───────────────────────────────────────────────────────────────────────────
 * ENT-01 §6: «معالج: **بنودُ التصفية محسوبةً من اللقطة** · الرصيدُ الصافي
 * **بعد المقاصّة** · **مرفقاتُ الإخلاء**».
 * واليدان مفصولتان: شؤونُ الموظفين تُعدّ · والمالية تعتمد — «لا يعتمد المرءُ ما أعدّ».
 */
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
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
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
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+تصفية+إنهاء+الخدمة+❌");
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('final settlement super') : ems_tenant_db();
$redirect = function ($msg) { header("Location: final_settlement.php?msg=" . rawurlencode($msg)); exit(); };

$action = strval($_POST['fs_action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'open') {
    if (!$can_add) { $redirect('لا توجد صلاحية لفتح تصفية ❌'); }
    $r = FS::open($conn, $gate, $company_id, intval($_POST['contract_id'] ?? 0),
                  strval($_POST['effective_date'] ?? ''), $uid);
    $redirect($r['ok']
        ? ('فُتحت التصفيةُ #' . $r['settlement_id'] . ' بصافٍ ' . $r['net'] . ' ✅')
        : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'approve') {
    if (!$can_edit) { $redirect('الاعتمادُ ليد الإجازة — لا صلاحية ❌'); }
    $r = FS::approve($conn, $gate, $company_id, intval($_POST['settlement_id'] ?? 0),
                     strval($_POST['clearance_doc'] ?? ''), $uid);
    $redirect($r['ok']
        ? ('اعتُمدت التصفية · ذمّةٌ #' . ($r['due_id'] ?? '—') . ' · استُرد ' . $r['recovered'] . ' ✅')
        : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancel') {
    if (!$can_add) { $redirect('لا توجد صلاحية ❌'); }
    $r = FS::cancel($conn, $gate, $company_id, intval($_POST['settlement_id'] ?? 0),
                    strval($_POST['cancel_reason'] ?? ''), $uid);
    $redirect($r['ok'] ? 'أُلغيت التصفيةُ بسببها المكتوب ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'));
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

$LINE_LABEL = array('dues' => 'المستحقُّ حتى تاريخ الأثر', 'leave' => 'رصيدُ الإجازات',
                    'eos' => 'نهايةُ الخدمة', 'advance_offset' => 'مقاصّةُ السلف والعهد');
$ST_LABEL   = array('draft' => 'مسودة', 'approved' => 'معتمدة', 'cancelled' => 'ملغاة');

$page_title = 'إيكوبيشن | تصفية إنهاء خدمة الموظف';
include '../inheader.php';
include '../insidebar.php';
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
    ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list-check"></i>
        عقودٌ منتهيةٌ تنتظر التصفية</h5></div>
    <div class="card-body">
        <p style="color:#666">
            <strong>التصفيةُ يفتحها الإنهاء</strong> (ENT-01 §5) — ولكل عقدٍ تصفيةٌ واحدةٌ لا تتكرر.
            والبنودُ <strong>محسوبةٌ من اللقطة</strong>: ما لا قاعدةَ مكتوبةً له يظهر
            <strong>معلَنًا بسببه</strong> ولا يُقدَّر بصفرٍ صامت.
        </p>
        <?php if (!$candidates): ?>
            <div class="alert alert-warning">لا عقدَ في «منتهٍ · منهًى · مقفل» — فلا تصفيةَ ممكنة.</div>
        <?php else: ?>
        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>العقد</th><th>الموظف</th><th>الحال</th><th>من</th><th>إلى</th>
                <th>قاعدةُ نهاية الخدمة</th><th>قاعدةُ الإجازة</th><th>التصفية</th><th></th></tr></thead>
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
                        : '<span class="badge badge-warning">لم تُكتب</span>'; ?></td>
                    <td><?php echo $c['leave_days_per_year'] !== null
                        ? htmlspecialchars((string)$c['leave_days_per_year']) . ' يوم/سنة'
                        : '<span class="badge badge-warning">لم تُكتب</span>'; ?></td>
                    <td><?php echo $c['settlement_id'] !== null
                        ? ('#' . intval($c['settlement_id']) . ' — '
                           . htmlspecialchars($ST_LABEL[(string)$c['settlement_state']] ?? (string)$c['settlement_state']))
                        : '<span class="badge badge-secondary">لم تُفتح</span>'; ?></td>
                    <td><?php if ($c['settlement_id'] === null): ?>
                        <a class="action-btn" href="final_settlement.php?preview=<?php echo intval($c['id']); ?>&as_of=<?php echo htmlspecialchars($previewOn); ?>">
                            <i class="fa fa-calculator"></i> عايِن</a>
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
        معاينةُ تصفية العقد #<?php echo $previewFor; ?></h5></div>
    <div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
            <input type="hidden" name="preview" value="<?php echo $previewFor; ?>">
            <label>تاريخُ الأثر:</label>
            <input type="date" name="as_of" value="<?php echo htmlspecialchars($previewOn); ?>">
            <button type="submit" class="btn-save"><i class="fa fa-rotate"></i> أعِد الاحتساب</button>
        </form>

        <?php if (!$preview['ok']): ?>
            <div class="alert alert-danger">
                <?php echo intval($preview['code']); ?> — <?php echo htmlspecialchars((string)$preview['reason']); ?>
            </div>
        <?php else: ?>
            <p style="color:#666">
                اللقطةُ <strong>#<?php echo intval($preview['snapshot_id']); ?></strong>
                ببصمة <code><?php echo htmlspecialchars(substr((string)$preview['fingerprint'], 0, 12)); ?>…</code>
                · سنواتُ الخدمة <strong><?php echo htmlspecialchars((string)$preview['basis']['service_years']); ?></strong>
                · أساسُ نهاية الخدمة <strong><?php echo htmlspecialchars((string)$preview['basis']['eos_monthly_base']); ?></strong>
                · أساسُ الإجازة <strong><?php echo htmlspecialchars((string)$preview['basis']['leave_monthly_base']); ?></strong>
            </p>
            <?php if (!empty($preview['basis']['unreadable_components'])): ?>
                <div class="alert alert-warning">
                    <strong>مكوّناتٌ بطريقةِ حسابٍ لا يحملها أساسٌ شهريٌّ ثابت — تُعلَن ولا تُقدَّر:</strong>
                    <?php foreach ($preview['basis']['unreadable_components'] as $u): ?>
                        <div>#<?php echo intval($u['component_id']); ?>
                            — <?php echo htmlspecialchars((string)$u['component_type']); ?>
                            (<?php echo htmlspecialchars((string)$u['calc_method']); ?>)
                            · العلَم <?php echo htmlspecialchars((string)$u['flag']); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="table-container">
            <table class="alltables display nowrap" style="width:100%">
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
                        <td style="white-space:normal"><small><?php echo htmlspecialchars((string)$l['source_note']); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr>
                    <th colspan="3">الرصيدُ الصافي بعد المقاصّة</th>
                    <th><?php echo htmlspecialchars((string)$preview['totals']['net']); ?></th>
                    <th><?php echo $preview['totals']['advances_remaining'] > 0
                        ? ('رصيدُ سلفٍ مفتوحٌ باقٍ: ' . htmlspecialchars((string)$preview['totals']['advances_remaining']))
                        : ''; ?></th>
                </tr></tfoot>
            </table>
            </div>

            <?php if ($can_add): ?>
            <form method="post" class="ems-form" style="margin-top:14px">
                <input type="hidden" name="fs_action" value="open">
                <input type="hidden" name="contract_id" value="<?php echo $previewFor; ?>">
                <input type="hidden" name="effective_date" value="<?php echo htmlspecialchars($previewOn); ?>">
                <button type="submit" class="btn-save"><i class="fa fa-file-signature"></i>
                    افتح التصفيةَ بهذه الأرقام</button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>

    <div class="card"><div class="card-header"><h5><i class="fa fa-folder-open"></i>
        التصفياتُ المفتوحة والمعتمدة</h5></div>
    <div class="card-body">
        <?php if (!$settlements): ?>
            <div class="alert alert-info">لا تصفيةَ بعد.</div>
        <?php else: foreach ($settlements as $s): $lines = FS::linesOf($gate, intval($s['id'])); ?>
            <div class="card" style="margin-bottom:14px">
            <div class="card-header"><h5>
                #<?php echo intval($s['id']); ?> —
                <?php echo htmlspecialchars((string)($s['employee_name'] ?? ('موظف ' . intval($s['employee_id'])))); ?>
                <span class="badge <?php echo (string)$s['state'] === 'approved' ? 'badge-success'
                    : ((string)$s['state'] === 'cancelled' ? 'badge-danger' : 'badge-secondary'); ?>">
                    <?php echo htmlspecialchars($ST_LABEL[(string)$s['state']] ?? (string)$s['state']); ?></span>
            </h5></div>
            <div class="card-body">
                <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:10px">
                    <div>تاريخُ الأثر: <strong><?php echo htmlspecialchars((string)$s['effective_date']); ?></strong></div>
                    <div>سنواتُ الخدمة: <strong><?php echo htmlspecialchars((string)$s['service_years']); ?></strong></div>
                    <div>الصافي: <strong><?php echo htmlspecialchars((string)$s['net_amount']); ?>
                        <?php echo htmlspecialchars((string)$s['currency']); ?></strong></div>
                    <div>المعترَفُ به جديدًا: <strong><?php echo htmlspecialchars((string)$s['recognized_amount']); ?></strong></div>
                    <div>اللقطة: <strong>#<?php echo intval($s['snapshot_id']); ?></strong></div>
                    <?php if ($s['net_due_ref'] !== null): ?>
                        <div>الذمّة: <strong>#<?php echo intval($s['net_due_ref']); ?></strong></div>
                    <?php endif; ?>
                    <?php if ((float)$s['advances_remaining'] > 0): ?>
                        <div><span class="badge badge-warning">رصيدُ سلفٍ باقٍ
                            <?php echo htmlspecialchars((string)$s['advances_remaining']); ?></span></div>
                    <?php endif; ?>
                </div>
                <div class="table-container">
                <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
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
                            <td style="white-space:normal"><small><?php echo htmlspecialchars((string)$l['source_note']); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <?php if ((string)$s['state'] === 'draft'): ?>
                    <?php if ($can_edit): ?>
                    <form method="post" class="ems-form" style="margin-top:12px">
                        <input type="hidden" name="fs_action" value="approve">
                        <input type="hidden" name="settlement_id" value="<?php echo intval($s['id']); ?>">
                        <div class="form-grid">
                            <div class="form-group"><label>مرفقُ الإخلاء <span style="color:#c00">*</span></label>
                                <input type="text" name="clearance_doc" maxlength="120" required
                                       placeholder="مرجعُ محضر الإخلاء"></div>
                        </div>
                        <button type="submit" class="btn-save"><i class="fa fa-check"></i>
                            اعتمد التصفية</button>
                        <small style="color:#666">— والاعتمادُ يولّد <strong>الحدثَ الماليَّ الواحد</strong>
                            ويسترد السلفَ فعليًّا.</small>
                    </form>
                    <?php endif; ?>
                    <?php if ($can_add): ?>
                    <form method="post" class="ems-form" style="margin-top:8px">
                        <input type="hidden" name="fs_action" value="cancel">
                        <input type="hidden" name="settlement_id" value="<?php echo intval($s['id']); ?>">
                        <div class="form-grid">
                            <div class="form-group"><label>سببُ الإلغاء <span style="color:#c00">*</span></label>
                                <input type="text" name="cancel_reason" maxlength="255" required></div>
                        </div>
                        <button type="submit" class="btn-cancel"><i class="fa fa-ban"></i> ألغِ المسودة</button>
                    </form>
                    <?php endif; ?>
                <?php elseif ((string)$s['state'] === 'approved'): ?>
                    <div class="alert alert-success" style="margin-top:10px">
                        اعتُمدت بمرفق الإخلاء
                        <strong><?php echo htmlspecialchars((string)$s['clearance_doc']); ?></strong>
                        — والتصحيحُ بعدها <strong>بحركةٍ عاكسةٍ لا بمحو</strong>.
                    </div>
                <?php elseif ((string)$s['state'] === 'cancelled'): ?>
                    <div class="alert alert-warning" style="margin-top:10px">
                        أُلغيت: <?php echo htmlspecialchars((string)$s['cancel_reason']); ?>
                    </div>
                <?php endif; ?>
            </div></div>
        <?php endforeach; endif; ?>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
