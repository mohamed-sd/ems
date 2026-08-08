<?php
/**
 * Contracts/contract_monthly_plan.php — الجدولُ الشهريُّ للعقد (P-03)
 * ───────────────────────────────────────────────────────────────────────────
 * PLAN-03 §2: «الجدولُ الشهري بنسخه و**قيدِ Σ الكميات = المتعاقَد**».
 * والشاشةُ تُري **الفجوةَ نفسَها**: Σ الأشهر مقابلَ المتعاقَد، والأشهرَ الغائبة.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Contract/ContractMonthlyPlanService.php';

use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractMonthlyPlanService as CMP;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit();
}

$MODULE_CODE = 'Contracts/contract_monthly_plan.php';
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
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+الجدول+الشهري+❌"); exit(); }

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('monthly plan super') : ems_tenant_db();
$LID  = isset($_GET['line']) ? intval($_GET['line']) : 0;
$VER  = isset($_GET['v']) ? max(1, intval($_GET['v'])) : 1;
$redirect = function ($msg, $l = 0, $v = 1) {
    header("Location: contract_monthly_plan.php?msg=" . rawurlencode($msg)
        . ($l > 0 ? ('&line=' . $l . '&v=' . $v) : ''));
    exit();
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && strval($_POST['mp_action'] ?? '') !== '') {
    if (!$can_edit) { $redirect('لا توجد صلاحية ❌'); }
    $act = strval($_POST['mp_action']);
    $lid = intval($_POST['line_id'] ?? 0);
    $ver = max(1, intval($_POST['plan_version'] ?? 1));

    if ($act === 'save') {
        $months = array();
        foreach (($_POST['qty'] ?? array()) as $mm => $q) {
            $q = trim(strval($q));
            if ($q === '') { continue; }
            $months[] = array(
                'period_month' => strval($mm),
                'qty_planned' => $q,
                'month_kind' => strval($_POST['kind'][$mm] ?? 'normal'),
                'note' => strval($_POST['mnote'][$mm] ?? ''),
            );
        }
        $r = CMP::savePlan($conn, $gate, $company_id, $lid, $ver,
                           strval($_POST['effective_from'] ?? ''), $months, $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $lid, $ver);
    }
    if ($act === 'seal') {
        $r = CMP::seal($conn, $gate, $company_id, $lid, $ver, $uid);
        $redirect($r['ok'] ? ($r['reason'] . ' ✅') : ($r['code'] . ' — ' . $r['reason'] . ' ❌'), $lid, $ver);
    }
}

$lines = array();
try {
    $lines = $gate->scopedQuery(array('scope' => array('l' => 'client_contract_lines')),
        "SELECT l.* FROM client_contract_lines l
          WHERE {TENANT_SCOPE} AND COALESCE(l.is_deleted,0)=0 AND l.state = 'active'
          ORDER BY l.contract_id DESC, l.line_no LIMIT 200");
} catch (\Throwable $t) { $lines = array(); }

$line = $LID > 0 ? CLS::lineOf($gate, $LID) : null;
$rows = $LID > 0 ? CMP::rowsOf($gate, $LID, $VER) : array();
$vers = $LID > 0 ? CMP::versionsOf($gate, $LID) : array();
$miss = $LID > 0 ? CMP::missingMonths($gate, $LID, $VER) : array();
$sum  = $LID > 0 ? CMP::versionSum($gate, $LID, $VER) : 0.0;

$KIND_AR = CMP::KIND_AR;
$byMonth = array();
foreach ($rows as $r) { $byMonth[(string) $r['period_month']] = $r; }

// شبكةُ أشهر البند كاملةً — والغائبُ يظهر فارغًا لا مخفيًّا
$grid = array();
if ($line) {
    $from = substr((string) $line['valid_from'], 0, 7);
    $to = ($line['valid_to'] !== null && (string) $line['valid_to'] !== '')
          ? substr((string) $line['valid_to'], 0, 7) : $from;
    $cur = $from; $g = 0;
    while ($cur <= $to && $g++ < 240) { $grid[] = $cur; $cur = date('Y-m', strtotime($cur . '-01 +1 month')); }
}

$page_title = 'إيكوبيشن | الجدول الشهري للعقد';
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
// NAV-01 §8 (update0006-b): الشاشةُ قسمٌ من ملف العقد الأم لا صفحةٌ يتيمة
$cf_contract_id = intval($_GET['contract'] ?? $_GET['id'] ?? 0); $cf_active = 'monthly';
if ($cf_contract_id > 0) include __DIR__ . '/../includes/contract_file_tabs.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'الجدول الشهري للعقد'; $header_icon = 'fa fa-calendar-days';
    $header_actions = array();
    $header_back = array('href' => 'contract_lines.php', 'class' => '',
                         'icon' => 'fas fa-arrow-right', 'label' => 'بنود العقد');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) { echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>'; }
    ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;line-height:1.8;margin:0">
            <i class="fas fa-circle-info"></i>
            الكميةُ المتعاقَدةُ <strong>ليست رقمًا يُقسَّم بالتساوي</strong> — بل <strong>منحنًى</strong>:
            شهرُ تعبئةٍ جزئيةٍ وشهرُ توقفٍ موسميٍّ وأشهرٌ اعتيادية.
            و<strong>Σ الأشهر لا يتجاوز المتعاقَد أبدًا</strong>، و<strong>المساواةُ التامة شرطُ الختم</strong>.
            و<strong>صفرٌ يعني توقفًا معلَنًا</strong> لا غيابَ بيان.
        </p>
    </div></div>

    <div class="card"><div class="card-header"><h5><i class="fa fa-list-ol"></i> بنودُ البيع النافذة</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
            <thead><tr><th>العقد</th><th>#</th><th>الوصف</th><th>المتعاقَد</th><th>المخطَّط</th>
                <th>الفجوة</th><th>مختوم</th><th></th>
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
            <?php foreach ($lines as $l):
                $gap = round((float)$l['qty_contracted'] - (float)$l['qty_planned_total'], 2); ?>
                <tr><td>#<?php echo intval($l['contract_id']); ?></td>
                    <td><?php echo intval($l['line_no']); ?></td>
                    <td style="white-space:normal"><?php echo htmlspecialchars((string)$l['description']); ?></td>
                    <td><?php echo htmlspecialchars((string)$l['qty_contracted']); ?></td>
                    <td><?php echo htmlspecialchars((string)$l['qty_planned_total']); ?></td>
                    <td><span class="badge <?php echo abs($gap) < 0.005 ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $gap; ?></span></td>
                    <td><?php echo $l['plan_sealed_version'] !== null
                        ? ('<span class="badge badge-info">نسخة ' . intval($l['plan_sealed_version']) . '</span>')
                        : '—'; ?></td>
                    <td><a class="action-btn" href="?line=<?php echo intval($l['id']); ?>&v=1">
                        <i class="fa fa-calendar-days"></i> الجدول</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$lines): ?><tr><td colspan="8"><em>لا بنودَ بيعٍ نافذة — ابدأ من «بنود العقد»</em></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div></div></div>

    <?php if ($line): ?>
    <div class="card"><div class="card-header"><h5><i class="fa fa-calendar-days"></i>
        جدولُ البند #<?php echo intval($line['line_no']); ?> —
        <?php echo htmlspecialchars((string)$line['description']); ?></h5></div>
    <div class="card-body">
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
            <span class="badge badge-secondary" style="padding:6px 12px">المتعاقَد
                <?php echo htmlspecialchars((string)$line['qty_contracted']); ?></span>
            <span class="badge badge-info" style="padding:6px 12px">Σ النسخة <?php echo $VER; ?>:
                <?php echo $sum; ?></span>
            <?php $gap = round((float)$line['qty_contracted'] - $sum, 2); ?>
            <span class="badge <?php echo abs($gap) < 0.005 ? 'badge-success' : 'badge-warning'; ?>"
                style="padding:6px 12px">الفجوة <?php echo $gap; ?>
                <?php echo abs($gap) < 0.005 ? '— **يُختم**' : '— **لا يُختم**'; ?></span>
            <?php if ($miss): ?>
                <span class="badge badge-warning" style="padding:6px 12px">
                    <?php echo count($miss); ?> شهرًا غائبًا</span>
            <?php endif; ?>
        </div>

        <?php if ($vers): ?>
        <div style="margin-bottom:10px">النسخ:
            <?php foreach ($vers as $v): ?>
                <a class="badge <?php echo (int)$v['plan_version'] === $VER ? 'badge-info' : 'badge-secondary'; ?>"
                   style="text-decoration:none;padding:5px 10px;margin-left:4px"
                   href="?line=<?php echo $LID; ?>&v=<?php echo intval($v['plan_version']); ?>">
                    <?php echo intval($v['plan_version']); ?>
                    (<?php echo htmlspecialchars((string)$v['effective_from']); ?> ·
                    <?php echo htmlspecialchars((string)$v['planned']); ?>)</a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post" class="ems-form">
            <input type="hidden" name="mp_action" value="save">
            <input type="hidden" name="line_id" value="<?php echo $LID; ?>">
            <input type="hidden" name="plan_version" value="<?php echo $VER; ?>">
            <div class="form-group" style="max-width:280px;margin-bottom:12px">
                <label>سريانُ النسخة <span style="color:#c00">*</span></label>
                <input type="date" name="effective_from" required
                       value="<?php echo htmlspecialchars((string)($rows ? $rows[0]['effective_from'] : $line['valid_from'])); ?>">
            </div>
            <div class="table-container">
            <table class="alltables display nowrap no-datatable" data-no-dt="1" style="width:100%">
                <thead><tr><th>الشهر</th><th>الكمية</th><th>طبيعتُه</th><th>ملاحظة</th></tr></thead>
                <tbody>
                <?php foreach ($grid as $mm): $r = isset($byMonth[$mm]) ? $byMonth[$mm] : null; ?>
                    <tr<?php echo $r === null ? " style='background:#fff7ed'" : ''; ?>>
                        <td><strong><?php echo htmlspecialchars($mm); ?></strong>
                            <?php echo $r === null ? '<span class="badge badge-warning">غائب</span>' : ''; ?></td>
                        <td><input type="number" step="0.01" min="0" style="width:120px"
                            name="qty[<?php echo $mm; ?>]"
                            value="<?php echo $r !== null ? htmlspecialchars((string)$r['qty_planned']) : ''; ?>"></td>
                        <td><select name="kind[<?php echo $mm; ?>]">
                            <?php foreach ($KIND_AR as $k => $v): ?>
                                <option value="<?php echo $k; ?>"
                                    <?php echo ($r !== null && (string)$r['month_kind'] === $k) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?></select></td>
                        <td><input type="text" maxlength="200" style="width:220px"
                            name="mnote[<?php echo $mm; ?>]"
                            value="<?php echo $r !== null ? htmlspecialchars((string)($r['note'] ?? '')) : ''; ?>"></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php if ($can_edit): ?>
            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                <button type="submit" class="btn-save"><i class="fa fa-save"></i> احفظ النسخة <?php echo $VER; ?></button>
            </div>
            <?php endif; ?>
        </form>

        <?php if ($can_edit): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
            <form method="post" style="display:inline">
                <input type="hidden" name="mp_action" value="seal">
                <input type="hidden" name="line_id" value="<?php echo $LID; ?>">
                <input type="hidden" name="plan_version" value="<?php echo $VER; ?>">
                <button type="submit" class="btn-save"><i class="fa fa-stamp"></i>
                    اختم النسخة <?php echo $VER; ?></button>
            </form>
            <a class="btn-save" style="text-decoration:none"
               href="?line=<?php echo $LID; ?>&v=<?php echo $VER + 1; ?>">
                <i class="fa fa-copy"></i> نسخةٌ جديدة (<?php echo $VER + 1; ?>)</a>
        </div>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
