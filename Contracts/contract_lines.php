<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/contract_lines.php — بنودُ عقد العميل وقيمتُه (P-02)
 * ───────────────────────────────────────────────────────────────────────────
 * PLAN-03 §2: «بنودُ المبيعات بنموذجها وسعرها وسريانها وحالتها الضريبية —
 * **وفصلُها عن خطة الموارد**».
 * والشاشةُ تُري **الفصلَ نفسَه**: التزاماتُ الكمية تصلح بنودًا، والتزاماتُ الطاقة
 * **مُعلَنةٌ مستبعَدة** — «ولا تدخل القيمة».
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/ContractLineService.php';

use App\Services\Contract\ContractLineService as CLS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit();
}

$MODULE_CODE = 'Contracts/contract_lines.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) { $can_view = $can_add = $can_edit = true; }
else {
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
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض بنود العقود ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('contract lines super') : ems_tenant_db();
$sel  = isset($_GET['contract']) ? intval($_GET['contract']) : 0;
$asOf = (isset($_GET['as_of']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['as_of']))
        ? (string) $_GET['as_of'] : '';
$redirect = function ($msg, $c = 0) {
    ems_gov_flash_redirect(ems_flash_to('contract_lines.php', ($c > 0 ? '&contract=' . $c : '')), $msg, 'GOV-INFO-200', '');
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['cl_action'] ?? '') !== '') {
    $act = strval($_POST['cl_action']);
    $cid = intval($_POST['contract_id'] ?? 0);
    if ($act === 'add') {
        if (!$can_add) { $redirect('لا توجد صلاحية ❌', $cid); }
        $r = CLS::add($conn, $gate, $company_id, $cid, array(
            'source_commitment_id' => intval($_POST['source_commitment_id'] ?? 0),
            'pricing_model' => strval($_POST['pricing_model'] ?? ''),
            'description' => strval($_POST['description'] ?? ''),
            'qty_contracted' => strval($_POST['qty_contracted'] ?? ''),
            'unit_price' => strval($_POST['unit_price'] ?? ''),
            'currency' => strval($_POST['currency'] ?? 'SDG'),
            'valid_from' => strval($_POST['valid_from'] ?? ''),
            'valid_to' => strval($_POST['valid_to'] ?? ''),
            'tax_status' => strval($_POST['tax_status'] ?? 'taxable'),
            'tax_code_id' => intval($_POST['tax_code_id'] ?? 0),
            'note' => strval($_POST['note'] ?? ''),
        ), $uid);
        $redirect($r['ok'] ? 'أضيف البند ✅' : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
    if ($act === 'reprice') {
        if (!$can_edit) { $redirect('لا توجد صلاحية ❌', $cid); }
        $r = CLS::reprice($conn, $gate, $company_id, intval($_POST['line_id'] ?? 0),
                          strval($_POST['new_price'] ?? ''), strval($_POST['effective_from'] ?? ''),
                          $uid, strval($_POST['note'] ?? ''));
        $redirect($r['ok'] ? ('نسخة جديدة #' . $r['new_line_id'] . ' — والقديمة أغلقت بسعرها ✅')
                           : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $cid);
    }
}

$contracts = array();
try {
    $contracts = $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
        "SELECT c.id, c.project_id, c.second_party, c.contract_status, c.actual_start, c.actual_end
           FROM contracts c WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
          ORDER BY c.id DESC LIMIT 200");
} catch (\Throwable $t) { $contracts = array(); }

$lines = $sel > 0 ? CLS::linesOf($gate, $sel) : array();
$split = $sel > 0 ? CLS::commitmentsSplit($gate, $sel) : array('billable' => array(), 'capacity' => array(), 'unknown' => array());
$value = $sel > 0 ? CLS::contractValue($gate, $sel, $asOf !== '' ? $asOf : null) : null;
$taxes = CLS::taxCodes($gate);

$MODEL_AR = array('hour' => 'ساعة', 'ton' => 'طن', 'trip' => 'نقلة', 'meter' => 'متر',
                  'cbm' => 'متر مكعب', 'day' => 'يوم', 'shift' => 'وردية',
                  'lump_sum' => 'مقطوع', 'standby' => 'استعداد');
$TAX_AR = array('taxable' => 'خاضع', 'exempt' => 'معفى', 'zero_rated' => 'صفري',
                'reverse_charge' => 'عكس التكليف');
$ST_AR = array('draft' => 'مسودة', 'active' => 'نافذ', 'superseded' => 'مستبدل', 'ended' => 'منتهٍ');

$page_title = 'إيكوبيشن | بنود عقد العميل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'lines';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'بنود عقد العميل وقيمته'; $header_icon = 'fa fa-list-ol';
    $header_actions = array();
    $header_back = array('href' => 'contract_sites.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'نطاقات العقد');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا بنود بيع مسجلة على هذا العقد بعد',
                           'اختر عقدا من جدول العقود ثم أضف أول بند بيع بنموذجه وسعره وسريانه');
    ?>

    <style>
    .cl-note{color:var(--c-4b5563, #4b5563);line-height:1.8;margin:0}
    .cl-muted{color:var(--c-ink-500, #6b7280)}
    .cl-note-muted{color:var(--c-ink-500, #6b7280);margin:0}
    .cl-req{color:var(--c-state-danger-strong, #c00)}
    .cl-table{width:100%}
    .cl-wrap{white-space:normal}
    .cl-ltr{direction:ltr}
    .cl-filter-form{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
    .cl-badges{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px}
    .cl-badge-pad{padding:8px 14px}
    .cl-badge-value{padding:8px 14px;font-size:15px}
    .cl-alert-gap{margin-top:12px}
    .cl-row-past{opacity:.6}
    .cl-inline-form{display:flex;gap:4px;flex-wrap:wrap}
    .cl-mini-btn{border:0;padding:5px 8px}
    .cl-add-form{margin-top:14px}
    .cl-actions{margin-top:12px}
    .cl-w90{width:90px}
    .cl-w140{width:140px}
    </style>

    <div class="card"><div class="card-body">
        <p class="cl-note">
            <i class="fas fa-circle-info"></i>
            <strong>هذا الجدولُ وحدَه يحمل المال.</strong> والتزاماتُ الطاقة
            (عددُ المعدات · ساعاتُ الإتاحة · دعمُ الطاقة) <strong>لا تدخل القيمة أبدًا</strong> —
            بيتُها خطةُ الموارد. وخلطُهما <strong>يضاعف الإيراد</strong> في عقدٍ واحد.
            والسعرُ يتغير <strong>بنسخةٍ تُخلِف</strong> لا بتعديلٍ رجعيّ.
        </p>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-file-contract"></i> العقود</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable cl-table" data-no-dt="1">
            <thead><tr><th>#</th><th>الطرفُ الثاني</th><th>المدة</th><th>الحال</th><th>بنودُ البيع</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($contracts as $c):
                $n = count(CLS::linesOf($gate, (int) $c['id'], false)); ?>
                <tr><td><?php echo intval($c['id']); ?></td>
                    <td><?php echo htmlspecialchars((string)($c['second_party'] ?? '—')); ?></td>
                    <td class="cl-ltr"><?php echo htmlspecialchars((string)($c['actual_start'] ?? '…')); ?>
                        → <?php echo htmlspecialchars((string)($c['actual_end'] ?? '…')); ?></td>
                    <td><?php echo htmlspecialchars((string)($c['contract_status'] ?? '—')); ?></td>
                    <td><span class="badge <?php echo $n > 0 ? 'badge-success' : 'badge-secondary'; ?>"><?php echo $n; ?></span></td>
                    <td><a class="action-btn" href="?contract=<?php echo intval($c['id']); ?>">
                        <i class="fa fa-eye"></i> افتح</a></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($sel > 0 && $value !== null): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-sack-dollar"></i>
        قيمة العقد #<?php echo $sel; ?></h5></div>
    <div class="card-body">
        <form method="get" class="cl-filter-form">
            <input type="hidden" name="contract" value="<?php echo $sel; ?>">
            <label for="emsf_46_ee666">القيمة بتاريخ:</label>
            <input type="date" name="as_of" id="emsf_46_ee666" value="<?php echo htmlspecialchars($asOf); ?>">
            <button type="submit" class="btn-primary"><i class="fa fa-calculator"></i> احسب</button>
            <small class="cl-muted">— فارغ = القيمة النافذة اليوم · وبتاريخ = <strong>ما حكم ذلك اليوم</strong></small>
        </form>
        <div class="cl-badges">
            <?php foreach ($value['by_currency'] as $cur => $amt): ?>
                <span class="badge badge-success cl-badge-value">
                    <?php echo number_format($amt, 2); ?> <?php echo htmlspecialchars((string)$cur); ?></span>
            <?php endforeach; ?>
            <?php if (!$value['by_currency']): ?>
                <span class="badge badge-secondary cl-badge-pad">لا بنود بيع نافذة</span>
            <?php endif; ?>
        </div>
        <p class="cl-note-muted"><?php echo htmlspecialchars($value['note']); ?></p>

        <?php if ($value['excluded']): ?>
        <div class="alert alert-warning cl-alert-gap">
            <strong>مستبعد من القيمة — التزامات طاقة لا تفوتر:</strong>
            <?php foreach ($value['excluded'] as $e): ?>
                <div>• <?php echo htmlspecialchars((string)$e['code']); ?> —
                    <strong><?php echo htmlspecialchars((string)$e['label']); ?></strong>
                    (<?php echo htmlspecialchars((string)$e['qty']); ?>)</div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list-ol"></i> البنود</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable cl-table" data-no-dt="1">
            <thead><tr><th>#</th><th>الوصف</th><th>النموذج</th><th>الكمية</th><th>السعر</th>
                <th>القيمة</th><th>السريان</th><th>الضريبة</th><th>الحال</th>
                <?php if ($can_edit) echo '<th>إعادة تسعير</th>'; ?>
                <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr<?php echo (string)$l['state'] !== 'active' ? ' class="cl-row-past"' : ''; ?>>
                    <td><?php echo intval($l['line_no']); ?></td>
                    <td class="cl-wrap"><?php echo htmlspecialchars((string)$l['description']); ?>
                        <?php if ($l['supersedes_line_id'] !== null): ?>
                            <div><small>يخلف البند #<?php echo intval($l['supersedes_line_id']); ?></small></div>
                        <?php endif; ?></td>
                    <td><?php echo htmlspecialchars($MODEL_AR[(string)$l['pricing_model']] ?? (string)$l['pricing_model']); ?></td>
                    <td><?php echo htmlspecialchars((string)$l['qty_contracted']); ?></td>
                    <td><?php echo htmlspecialchars((string)$l['unit_price']); ?></td>
                    <td><strong><?php echo number_format((float)$l['qty_contracted'] * (float)$l['unit_price'], 2); ?></strong>
                        <?php echo htmlspecialchars((string)$l['currency']); ?></td>
                    <td class="cl-ltr"><?php echo htmlspecialchars((string)$l['valid_from']); ?>
                        → <?php echo htmlspecialchars((string)($l['valid_to'] ?? '…')); ?></td>
                    <td><?php echo htmlspecialchars($TAX_AR[(string)$l['tax_status']] ?? (string)$l['tax_status']); ?>
                        <?php echo $l['tax_code_id'] !== null ? ('<small>#' . intval($l['tax_code_id']) . '</small>') : ''; ?></td>
                    <td><span class="badge <?php echo (string)$l['state'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                        <?php echo htmlspecialchars($ST_AR[(string)$l['state']] ?? (string)$l['state']); ?></span></td>
                    <?php if ($can_edit): ?>
                    <td><?php if ((string)$l['state'] === 'active'): ?>
                        <form method="post" class="cl-inline-form">
        <?php echo csrf_field(); ?>
                            <input type="hidden" name="cl_action" value="reprice">
                            <input type="hidden" name="contract_id" value="<?php echo $sel; ?>">
                            <input type="hidden" name="line_id" value="<?php echo intval($l['id']); ?>">
                            <input type="number" name="new_price" step="0.0001" min="0.0001" required
                                   placeholder="السعر الجديد" class="cl-w90" aria-label="السعر الجديد للبند">
                            <input type="date" name="effective_from" class="cl-w140" required aria-label="تاريخ سريان السعر الجديد">
                            <button type="submit" class="badge badge-info cl-mini-btn">أخلف</button>
                        </form>
                    <?php else: ?>—<?php endif; ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lines): ?><tr><td colspan="10"><em>لا بنود بيع بعد</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($can_add): ?>
    <form method="post" class="ems-form cl-add-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="cl_action" value="add">
        <input type="hidden" name="contract_id" value="<?php echo $sel; ?>">
        <div class="form-grid">
            <div class="form-group"><label for="emsf_47_02dd8">مشتق من التزام <small>— الكميات وحدها</small></label>
                <select name="source_commitment_id" id="emsf_47_02dd8">
                    <option value="0">— بلا اشتقاق —</option>
                    <?php foreach ($split['billable'] as $m): ?>
                        <option value="<?php echo intval($m['id']); ?>">
                            <?php echo htmlspecialchars((string)$m['commitment_code']); ?> —
                            <?php echo htmlspecialchars((string)$m['commitment_type']); ?>
                            (<?php echo htmlspecialchars((string)$m['qty']); ?>
                            <?php echo htmlspecialchars((string)($m['unit_type'] ?? '')); ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?php if ($split['capacity']): ?>
                <small class="cl-req">⚠ التزامات الطاقة **لا تظهر هنا**:
                    <?php $names = array(); foreach ($split['capacity'] as $m) { $names[] = (string)$m['commitment_code']; }
                          echo htmlspecialchars(implode(' · ', $names)); ?></small>
                <?php endif; ?></div>
            <div class="form-group"><label for="emsf_48_04c07">النموذج <span class="cl-req">*</span></label>
                <select name="pricing_model" required id="emsf_48_04c07">
                    <?php foreach ($MODEL_AR as $k => $v): ?>
                        <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?></select></div>
            <div class="form-group"><label for="emsf_49_156b9">الوصف</label><input type="text" name="description" maxlength="200" id="emsf_49_156b9"></div>
            <div class="form-group"><label for="emsf_50_5ed2f">الكمية <span class="cl-req">*</span></label>
                <input type="number" name="qty_contracted" step="0.01" min="0.01" required id="emsf_50_5ed2f"></div>
            <div class="form-group"><label for="emsf_51_1ad79">سعر الوحدة <span class="cl-req">*</span></label>
                <input type="number" name="unit_price" step="0.0001" min="0.0001" required id="emsf_51_1ad79"></div>
            <div class="form-group"><label for="emsf_52_ff69f">العملة</label><input type="text" name="currency" value="SDG" maxlength="8" id="emsf_52_ff69f"></div>
            <div class="form-group"><label for="emsf_53_0548a">سريان من <span class="cl-req">*</span></label>
                <input type="date" name="valid_from" required id="emsf_53_0548a"></div>
            <div class="form-group"><label for="emsf_54_bc668">سريان إلى</label><input type="date" name="valid_to" id="emsf_54_bc668"></div>
            <div class="form-group"><label for="emsf_55_127c2">الحالة الضريبية</label>
                <select name="tax_status" id="emsf_55_127c2">
                    <?php foreach ($TAX_AR as $k => $v): ?>
                        <option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option>
                    <?php endforeach; ?></select></div>
            <div class="form-group"><label for="emsf_56_0fe40">الرمز الضريبي <small>— إلزامي للخاضع</small></label>
                <select name="tax_code_id" id="emsf_56_0fe40"><option value="0">—</option>
                    <?php foreach ($taxes as $t): ?>
                        <option value="<?php echo intval($t['id']); ?>">
                            <?php echo htmlspecialchars((string)$t['code']); ?>
                            (<?php echo htmlspecialchars((string)$t['rate']); ?>%)</option>
                    <?php endforeach; ?></select></div>
            <div class="form-group"><label for="emsf_57_93be6">ملاحظة</label><input type="text" name="note" maxlength="200" id="emsf_57_93be6"></div>
        </div>
        <div class="cl-actions"><button type="submit" class="btn-primary">
            <i class="fa fa-plus"></i> أضف بند بيع</button></div>
    </form>
    <?php endif; ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
