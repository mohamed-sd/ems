<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Contracts/contract_resource_plan.php — خطةُ موارد العقد (P-04)
 * ───────────────────────────────────────────────────────────────────────────
 * PLAN-03 §2: «خطةُ الموارد بحصص الأنواع — **تُغذّي الحاويات ولا تدخل القيمة**».
 * والشاشةُ تُري **الفصلَ نفسَه**: طاقةٌ بالحصص وأعدادٌ بالورديّات — **ولا خانةَ
 * سعرٍ واحدة**. والفجوةُ إلى المائة ظاهرةٌ لا مخفيّة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/ContractResourcePlanService.php';

use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractResourcePlanService as CRP;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit();
}

$MODULE_CODE = 'Contracts/contract_resource_plan.php';
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
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض خطة الموارد ❌', 'GOV-PERM-403', ''); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('resource plan super') : ems_tenant_db();
$LID  = isset($_GET['line']) ? intval($_GET['line']) : 0;
$redirect = function ($msg, $l = 0) {
    ems_gov_flash_redirect(ems_flash_to('contract_resource_plan.php', ($l > 0 ? ('&line=' . $l) : '')), $msg, 'GOV-INFO-200', '');
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['rp_action'] ?? '') !== '') {
    if (!$can_edit) { $redirect('لا توجد صلاحية ❌'); }
    $act = strval($_POST['rp_action']);
    $lid = intval($_POST['line_id'] ?? 0);

    if ($act === 'save') {
        $rows = array();
        foreach (($_POST['share'] ?? array()) as $tid => $sh) {
            $sh = trim(strval($sh));
            $cb = trim(strval($_POST['basic'][$tid] ?? ''));
            if ($sh === '' && $cb === '') { continue; }
            $rows[] = array(
                'equipment_type_id' => intval($tid),
                'capacity_share_percent' => ($sh === '' ? 0 : $sh),
                'share_kind' => strval($_POST['kind'][$tid] ?? 'productive'),
                'count_basic' => intval($_POST['basic'][$tid] ?? 0),
                'count_backup' => intval($_POST['backup'][$tid] ?? 0),
                'shifts_per_day' => intval($_POST['shifts'][$tid] ?? 1),
                'hours_per_shift' => strval($_POST['hours'][$tid] ?? 0),
                'operators_count' => intval($_POST['ops'][$tid] ?? 0),
                'supervisors_count' => intval($_POST['sup'][$tid] ?? 0),
                'technicians_count' => intval($_POST['tec'][$tid] ?? 0),
                'assistants_count' => intval($_POST['asst'][$tid] ?? 0),
                'equipment_size' => intval($_POST['size'][$tid] ?? 0),
                'operational_site_id' => intval($_POST['site'][$tid] ?? 0),
                'note' => strval($_POST['rnote'][$tid] ?? ''),
            );
        }
        $r = CRP::savePlan($conn, $gate, $company_id, $lid, $rows, $uid,
                           strval($_POST['valid_from'] ?? ''));
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $lid);
    }
    if ($act === 'end_row') {
        $r = CRP::endRow($conn, $gate, $company_id, intval($_POST['row_id'] ?? 0),
                         strval($_POST['end_reason'] ?? ''), $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $lid);
    }
}

$lines = array();
try {
    $lines = $gate->scopedQuery(array('scope' => array('l' => 'client_contract_lines')),
        "SELECT l.* FROM client_contract_lines l
          WHERE {TENANT_SCOPE} AND COALESCE(l.is_deleted,0)=0 AND l.state = 'active'
          ORDER BY l.contract_id DESC, l.line_no LIMIT 200");
} catch (\Throwable $t) { $lines = array(); }

$line  = $LID > 0 ? CLS::lineOf($gate, $LID) : null;
$types = CRP::activeTypes($gate);
$live  = $LID > 0 ? CRP::liveRows($gate, $LID) : array();
$all   = $LID > 0 ? CRP::allRows($gate, $LID) : array();
$share = $LID > 0 ? CRP::shareTotal($gate, $LID) : 0.0;
$gap   = round(100 - $share, 3);
$cap   = $LID > 0 ? CRP::plannedCapacity($gate, $LID) : array('ok' => false, 'reason' => '', 'rows' => array());
$crew  = $LID > 0 ? CRP::crewDemand($gate, $LID) : array();

$byType = array();
foreach ($live as $r) { $byType[(int) $r['equipment_type_id']] = $r; }
$KIND_AR = CRP::KIND_AR;

$sites = array();
if ($line) {
    try {
        $sites = $gate->scopedQuery(array('scope' => array('s' => 'contract_operational_sites')),
            "SELECT s.id, s.scope_name FROM contract_operational_sites s
              WHERE {TENANT_SCOPE} AND s.contract_id = ? AND COALESCE(s.is_deleted,0)=0
              ORDER BY s.id", array((int) $line['contract_id']));
    } catch (\Throwable $t) { $sites = array(); }
}

$page_title = 'إيكوبيشن | خطة موارد العقد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'resource';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'خطة موارد العقد'; $header_icon = 'fa fa-truck-ramp-box';
    $header_actions = array();
    $header_back = array('href' => 'contract_lines.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'بنود العقد');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا خطة موارد مسجلة لهذا البند بعد',
                           'اختر بند بيع نافذا من الجدول أعلاه ثم وزع الحصص حتى تكتمل المائة');
    ?>

    <style>
    .crp-note{color:var(--c-4b5563, #4b5563);line-height:1.8;margin:0}
    .crp-table{width:100%}
    .crp-wrap{white-space:normal}
    .crp-badges{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
    .crp-badge-pad{padding:6px 12px}
    .crp-field-narrow{max-width:280px;margin-bottom:12px}
    .crp-row-new{background:var(--c-fff7ed, #fff7ed)}
    .crp-actions{margin-top:12px}
    .crp-inline-form{display:flex;gap:4px}
    .crp-w70{width:70px}
    .crp-w80{width:80px}
    .crp-w90{width:90px}
    .crp-w150{width:150px}
    .crp-w180{width:180px}
    </style>

    <div class="card"><div class="card-body">
        <p class="crp-note">
            <i class="fas fa-circle-info"></i>
            خطةُ الموارد تقول <strong>كيف تُنتَج كميةُ البند</strong> — لا <strong>بكم تُباع</strong>.
            فليس فيها سعرٌ ولا عملةٌ ولا مبلغ: <strong>تُغذّي الحاويات ولا تدخل القيمة</strong>.
            و<strong>Σ الحصص لا تتجاوز المائة</strong>، و<strong>المائةُ التامة شرطُ الاكتمال</strong>،
            و<strong>صفرُ الحصة يُفسَّر باسمه</strong> (احتياطيٌّ أو مساند).
        </p>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list-ol"></i> بنودُ البيع النافذة</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable crp-table" data-no-dt="1">
            <thead><tr><th>العقد</th><th>#</th><th>الوصف</th><th>النموذج</th><th>المتعاقَد</th>
                <th>Σ الحصص</th><th>الفجوة</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($lines as $l):
                $sh = (float) $l['resource_share_total']; $g = round(100 - $sh, 3); ?>
                <tr><td>#<?php echo intval($l['contract_id']); ?></td>
                    <td><?php echo intval($l['line_no']); ?></td>
                    <td class="crp-wrap"><?php echo htmlspecialchars((string)$l['description']); ?></td>
                    <td><?php echo htmlspecialchars((string)$l['pricing_model']); ?></td>
                    <td><?php echo htmlspecialchars((string)$l['qty_contracted']); ?></td>
                    <td><?php echo $sh; ?>%</td>
                    <td><span class="badge <?php echo abs($g) < 0.0005 ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $g; ?>%</span></td>
                    <td><a class="action-btn" href="?line=<?php echo intval($l['id']); ?>">
                        <i class="fa fa-truck-ramp-box"></i> الخطة</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$lines): ?><tr><td colspan="8"><em>لا بنود بيع نافذة — ابدأ من «بنود العقد»</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($line): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-truck-ramp-box"></i>
        خطة البند #<?php echo intval($line['line_no']); ?> —
        <?php echo htmlspecialchars((string)$line['description']); ?></h5></div>
    <div class="card-body">
        <div class="crp-badges">
            <span class="badge badge-secondary crp-badge-pad">المتعاقَد
                <?php echo htmlspecialchars((string)$line['qty_contracted']); ?></span>
            <span class="badge badge-info crp-badge-pad">Σ الحصص <?php echo $share; ?>%</span>
            <span class="badge crp-badge-pad <?php echo abs($gap) < 0.0005 ? 'badge-success' : 'badge-warning'; ?>"
                >الفجوة <?php echo $gap; ?>%
                <?php echo abs($gap) < 0.0005 ? '— مكتملة' : '— غير مكتملة'; ?></span>
            <?php if ($cap['ok']): ?>
                <span class="badge badge-secondary crp-badge-pad">الطاقة المخططة
                    <?php echo $cap['total'] . ' ' . htmlspecialchars((string)$cap['unit']); ?></span>
            <?php elseif ($LID > 0 && isset($cap['reason']) && $cap['reason'] !== ''): ?>
                <span class="badge badge-warning crp-badge-pad">
                    <?php echo htmlspecialchars(strip_tags(str_replace('**', '', (string)$cap['reason']))); ?></span>
            <?php endif; ?>
            <?php if ($crew): ?>
                <span class="badge badge-secondary crp-badge-pad">
                    طلب العمالة: <?php echo intval($crew['operators']); ?> مشغلا ·
                    <?php echo intval($crew['supervisors']); ?> مشرفا ·
                    <?php echo intval($crew['technicians']); ?> فنيا ·
                    <?php echo intval($crew['assistants']); ?> مساعدا</span>
            <?php endif; ?>
        </div>

        <form method="post" class="ems-form">
        <?php echo csrf_field(); ?>
            <input type="hidden" name="rp_action" value="save">
            <input type="hidden" name="line_id" value="<?php echo $LID; ?>">
            <div class="form-group crp-field-narrow">
                <label for="emsf_76_afeca">سريان الخطة</label>
                <input type="date" name="valid_from" id="emsf_76_afeca"
                       value="<?php echo htmlspecialchars((string)($live ? $live[0]['valid_from'] : $line['valid_from'])); ?>">
            </div>
            <div class="table-container">
            <table class="alltables display nowrap no-datatable crp-table" data-no-dt="1">
                <thead><tr><th>نوع المعدة</th><th>الحصة %</th><th>الصفة</th><th>أساسية</th><th>احتياطية</th>
                    <th>ورديات</th><th>ساعات/وردية</th><th>مشغلون</th><th>مشرفون</th><th>فنيون</th>
                    <th>مساعدون</th><th>الحجم</th><th>الموقع</th><th>ملاحظة</th><th>الطاقة</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    </tr></thead>
                <tbody>
                <?php foreach ($types as $tid => $tname): $r = isset($byType[$tid]) ? $byType[$tid] : null;
                    $capQty = null;
                    foreach ($cap['rows'] as $cr) { if ((int)$cr['equipment_type_id'] === (int)$tid) { $capQty = $cr['planned_qty']; } } ?>
                    <tr<?php echo $r === null ? ' class="crp-row-new"' : ''; ?>>
                        <td><strong><?php echo htmlspecialchars((string)$tname); ?></strong></td>
                        <td><input type="number" step="0.001" min="0" max="100" class="crp-w90" aria-label="حصة الطاقة بالمائة"
                            name="share[<?php echo intval($tid); ?>]"
                            value="<?php echo $r !== null ? htmlspecialchars((string)$r['capacity_share_percent']) : ''; ?>"></td>
                        <td><select aria-label="صفة الحصة — منتجة أو احتياطية" name="kind[<?php echo intval($tid); ?>]">
                            <?php foreach ($KIND_AR as $k => $v): ?>
                                <option value="<?php echo $k; ?>"
                                    <?php echo ($r !== null && (string)$r['share_kind'] === $k) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?></select></td>
                        <td><input type="number" min="0" class="crp-w70" aria-label="عدد المعدات الأساسية" name="basic[<?php echo intval($tid); ?>]"
                            value="<?php echo $r !== null ? intval($r['count_basic']) : ''; ?>"></td>
                        <td><input type="number" min="0" class="crp-w70" aria-label="عدد المعدات الاحتياطية" name="backup[<?php echo intval($tid); ?>]"
                            value="<?php echo $r !== null ? intval($r['count_backup']) : ''; ?>"></td>
                        <td><input type="number" min="1" max="4" class="crp-w70" aria-label="عدد الورديات في اليوم" name="shifts[<?php echo intval($tid); ?>]"
                            value="<?php echo $r !== null ? intval($r['shifts_per_day']) : 1; ?>"></td>
                        <td><input type="number" step="0.25" min="0" max="24" class="crp-w80" aria-label="ساعات الوردية الواحدة" name="hours[<?php echo intval($tid); ?>]"
                            value="<?php echo $r !== null ? htmlspecialchars((string)$r['hours_per_shift']) : ''; ?>"></td>
                        <td><input type="number" min="0" class="crp-w70" aria-label="عدد المشغلين" name="ops[<?php echo intval($tid); ?>]"
                            value="<?php echo $r !== null ? intval($r['operators_count']) : ''; ?>"></td>
                        <td><input type="number" min="0" class="crp-w70" aria-label="عدد المشرفين" name="sup[<?php echo intval($tid); ?>]"
                            value="<?php echo $r !== null ? intval($r['supervisors_count']) : ''; ?>"></td>
                        <td><input type="number" min="0" class="crp-w70" aria-label="عدد الفنيين" name="tec[<?php echo intval($tid); ?>]"
                            value="<?php echo $r !== null ? intval($r['technicians_count']) : ''; ?>"></td>
                        <td><input type="number" min="0" class="crp-w70" aria-label="عدد المساعدين" name="asst[<?php echo intval($tid); ?>]"
                            value="<?php echo $r !== null ? intval($r['assistants_count']) : ''; ?>"></td>
                        <td><input type="number" min="0" class="crp-w80" aria-label="حجم المعدة" name="size[<?php echo intval($tid); ?>]"
                            value="<?php echo ($r !== null && $r['equipment_size'] !== null) ? intval($r['equipment_size']) : ''; ?>"></td>
                        <td><select aria-label="الموقع التشغيلي للحصة" name="site[<?php echo intval($tid); ?>]">
                            <option value="0">— كل العقد —</option>
                            <?php foreach ($sites as $s): ?>
                                <option value="<?php echo intval($s['id']); ?>"
                                    <?php echo ($r !== null && (int)$r['operational_site_id'] === (int)$s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$s['scope_name']); ?></option>
                            <?php endforeach; ?></select></td>
                        <td><input type="text" maxlength="200" class="crp-w180" aria-label="ملاحظة على صف الخطة" name="rnote[<?php echo intval($tid); ?>]"
                            value="<?php echo $r !== null ? htmlspecialchars((string)($r['note'] ?? '')) : ''; ?>"></td>
                        <td><?php echo $capQty !== null
                            ? ('<span class="badge badge-info">' . $capQty . '</span>') : '—'; ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$types): ?><tr><td colspan="15"><em>لا أنواع معدات نشطة</em></td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
            <?php if ($can_edit): ?>
            <div class="crp-actions">
                <button type="submit" class="btn-primary"><i class="fa fa-save"></i> احفظ الخطة</button>
            </div>
            <?php endif; ?>
        </form>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-clock-rotate-left"></i>
        سجل الخطة — <strong>والمنتهية تبقى للتاريخ</strong></h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable crp-table" data-no-dt="1">
            <thead><tr><th>#</th><th>النوع</th><th>الحصة</th><th>الصفة</th><th>من</th><th>إلى</th>
                <th>الحال</th><th>سبب الإنهاء</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($all as $r): ?>
                <tr><td><?php echo intval($r['id']); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['type_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['capacity_share_percent']); ?>%</td>
                    <td><?php echo htmlspecialchars(isset($KIND_AR[(string)$r['share_kind']])
                        ? $KIND_AR[(string)$r['share_kind']] : (string)$r['share_kind']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['valid_from']); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['valid_to'] ?? '—')); ?></td>
                    <td><span class="badge <?php echo (string)$r['state'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                        <?php echo htmlspecialchars((string)$r['state']); ?></span></td>
                    <td class="crp-wrap"><?php echo htmlspecialchars((string)($r['end_reason'] ?? '—')); ?></td>
                    <td><?php if ($can_edit && (string)$r['state'] !== 'ended'): ?>
                        <form method="post" class="crp-inline-form">
        <?php echo csrf_field(); ?>
                            <input type="hidden" name="rp_action" value="end_row">
                            <input type="hidden" name="line_id" value="<?php echo $LID; ?>">
                            <input type="hidden" name="row_id" value="<?php echo intval($r['id']); ?>">
                            <input type="text" name="end_reason" class="crp-w150" placeholder="سبب الإنهاء" required aria-label="سبب الإنهاء">
                            <button type="submit" class="action-btn"><i class="fa fa-ban"></i> أنه</button>
                        </form>
                    <?php else: ?>—<?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$all): ?><tr><td colspan="9"><em>لا صفوف بعد</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
