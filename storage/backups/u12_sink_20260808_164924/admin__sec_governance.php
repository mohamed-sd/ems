<?php
/**
 * admin/sec_governance.php — مركز حوكمة الصلاحيات بمجموعاته الثماني
 * (update0004 · SEC-26 · SEC-01 §10.1)
 * ───────────────────────────────────────────────────────────────────────────
 * ① لوحة الحوكمة ② العمل اليومي (المعالج) ③ السجلات ④ الاعتماد ⑤ المتابعة
 * ⑥ الإغلاق (المراجعة الدورية · التأسيس) ⑦ التقارير (لماذا يملك فلان؟)
 * ⑧ الإعدادات (القوالب · الحقول الحساسة · الحراس)
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/screen_contract.php';
require_once __DIR__ . '/../app/Services/Security/PermissionExplainService.php';
require_once __DIR__ . '/../app/Services/Security/PermissionReviewService.php';
require_once __DIR__ . '/../app/Services/Security/PermissionChangeWorkflow.php';

use App\Services\Security\PermissionExplainService as PEX;
use App\Services\Security\PermissionReviewService as PRS;
use App\Services\Security\PermissionChangeWorkflow as PCW;

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
if ($is_super_admin && $company_id <= 0) { $company_id = 4; }

$MODULE_CODE = 'admin/sec_governance.php';
$can_view = $is_super_admin; $can_edit = $is_super_admin;
if (!$is_super_admin) {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_edit FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = intval($row['can_view']) === 1;
        $can_edit = intval($row['can_edit']) === 1;
    }
    $st->close();
}
if (!$can_view) { header("Location: ../main/dashboard.php?msg=" . rawurlencode('لا صلاحية لمركز الحوكمة ❌')); exit(); }

$msg = strval($_GET['msg'] ?? '');
$explainResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gov_action'])) {
    $act = strval($_POST['gov_action']);
    if ($act === 'explain') {
        // ⑦ التقارير — «لماذا يملك فلان هذه الصلاحية؟» (أهم خدمات المراجع)
        $explainResult = PEX::explain($conn, intval($_POST['person_id'] ?? 0), $company_id,
            strval($_POST['permission_code'] ?? ''), strval($_POST['scope'] ?? ''));
    } elseif ($act === 'open_cycle' && $can_edit) {
        $r = PRS::openCycle($conn, $company_id, intval($_POST['org_unit_id'] ?? 0),
            strval($_POST['period'] ?? ''), intval($_POST['manager_person_id'] ?? 0));
        header("Location: sec_governance.php?msg=" . rawurlencode($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌')));
        exit();
    } elseif ($act === 'decide_pcr' && $can_edit) {
        $r = PCW::decide($conn, intval($_POST['req_id'] ?? 0), $uid,
            strval($_POST['decision'] ?? 'approve'), 'من مركز الحوكمة');
        header("Location: sec_governance.php?msg=" . rawurlencode($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌')));
        exit();
    } elseif ($act === 'apply_pcr' && $can_edit) {
        $r = PCW::apply($conn, intval($_POST['req_id'] ?? 0), $uid);
        header("Location: sec_governance.php?msg=" . rawurlencode($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌')));
        exit();
    }
}

$q1 = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? $r->fetch_assoc() : null; };
$qa = function ($sql) use ($conn) { $r = $conn->query($sql); return $r ? $r->fetch_all(MYSQLI_ASSOC) : array(); };

// ① لوحة الحوكمة
$board = array(
    'expired_ex' => $q1("SELECT COUNT(*) c FROM permission_exceptions WHERE company_id={$company_id} AND state='expired'")['c'] ?? 0,
    'ended_asg' => $q1("SELECT COUNT(*) c FROM org_assignments WHERE company_id={$company_id} AND state='ended'")['c'] ?? 0,
    'denials' => $q1("SELECT COUNT(*) c FROM guard_denials WHERE company_id={$company_id} AND at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['c'] ?? 0,
    'bg_active' => $q1("SELECT COUNT(*) c FROM permission_exceptions WHERE company_id={$company_id} AND is_break_glass=1 AND state='active'")['c'] ?? 0,
);
// ④ الاعتماد — طلبات التغيير المعلقة
$pcrs = $qa("SELECT r.req_id, r.person_id, r.change_kind, r.risk_level, r.state, r.reason,
                    (SELECT COUNT(*) FROM permission_approval_steps s WHERE s.req_id=r.req_id AND s.mandatory=1 AND (s.decision IS NULL OR s.decision<>'approve')) remaining
               FROM permission_change_requests r
              WHERE r.company_id={$company_id} AND r.state IN ('pending','approved')
              ORDER BY r.req_id DESC LIMIT 30");
// ⑤ المتابعة
$dormant = $qa("SELECT u.id, u.name FROM users u WHERE u.company_id={$company_id}
                 AND NOT EXISTS (SELECT 1 FROM activity_logs al WHERE al.user_id=u.id AND al.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)) LIMIT 15");
$expiring = $qa("SELECT 'استثناء' kind, ex_id id, permission_code code, valid_to ts FROM permission_exceptions
                  WHERE company_id={$company_id} AND state='active' AND valid_to <= DATE_ADD(NOW(), INTERVAL 30 DAY)
                UNION ALL
                SELECT 'تكليف', asg_id, assignment_type_code, CONCAT(valid_to,' 23:59') FROM org_assignments
                  WHERE company_id={$company_id} AND state='active' AND valid_to <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) LIMIT 20");
$orphans = $qa("SELECT DISTINCT rp.role_id, m.code FROM role_permissions rp
                 LEFT JOIN modules m ON m.id = rp.module_id
                WHERE m.id IS NULL LIMIT 10");
// ⑥ الإغلاق
$cycles = $qa("SELECT c.cycle_id, c.period, c.state, c.due_at, u.name_ar unit_name,
                      (SELECT COUNT(*) FROM permission_review_lines l WHERE l.cycle_id=c.cycle_id AND l.decision IS NULL) open_lines
                 FROM permission_review_cycles c JOIN org_units u ON u.unit_id=c.org_unit_id
                WHERE c.company_id={$company_id} ORDER BY c.cycle_id DESC LIMIT 10");
$founding = $qa("SELECT mode, enabled, started_at, ends_at FROM founding_mode");
// ⑧ الإعدادات
$tplStats = $qa("SELECT t.tpl_kind, COUNT(*) total,
                        SUM(EXISTS(SELECT 1 FROM permission_template_versions v WHERE v.tpl_id=t.tpl_id AND v.state='published')) published
                   FROM permission_templates t GROUP BY t.tpl_kind");
$guards = $qa("SELECT guard_code, name_ar, overridable FROM guard_override_policies ORDER BY FIELD(overridable,'never','break_glass_only','with_compensating_control')");
$units = $qa("SELECT unit_id, name_ar FROM org_units WHERE company_id={$company_id} AND active=1");
$usersList = $qa("SELECT id, name FROM users WHERE company_id={$company_id} ORDER BY name");

$page_title = 'إيكوبيشن | مركز حوكمة الصلاحيات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'مركز حوكمة الصلاحيات — المجموعات الثماني'; $header_icon = 'fa fa-shield-alt';
    $header_actions = array(array('id' => 'wizardLink', 'class' => 'add-btn', 'icon' => 'fas fa-user-plus',
        'label' => 'إعداد موظف (١١ خطوة)', 'href' => 'sec_employee_wizard.php'));
    include('../includes/page_header.php');

    ems_screen_about(
        'الصلاحية تُشتق ولا تُمنح: مركز مدير الصلاحيات بمجموعاته الثماني (SEC-01 §10.1) — '
        . 'يعرّف الشخص بطبقاته السبع فيمنحه النظام قالبه، ويفسّر مصدر كل صلاحية، ولا يمنح نفسه.',
        array('«لماذا يملك فلان هذه الصلاحية؟» في التقارير — أهم خدمة للمراجع',
              'المراجعة النصف سنوية بندًا بندًا — وما لم يُوقَّع يُصعَّد',
              'الحراس السبعة عشر تُقرأ سياساتهم ولا تُستثنى'));

    if ($msg !== '') { echo '<div class="alert alert-info">' . htmlspecialchars($msg) . '</div>'; }
    ?>

    <div class="card"><div class="card-header"><h5>① لوحة الحوكمة</h5></div>
    <div class="card-body" style="display:flex;gap:14px;flex-wrap:wrap">
        <div class="badge badge-warning" style="font-size:14px;padding:8px 14px">استثناءات منتهية: <strong><?php echo intval($board['expired_ex']); ?></strong></div>
        <div class="badge badge-secondary" style="font-size:14px;padding:8px 14px">تكليفات منقضية: <strong><?php echo intval($board['ended_asg']); ?></strong></div>
        <div class="badge badge-danger" style="font-size:14px;padding:8px 14px">محاولات مرفوضة (7 أيام): <strong><?php echo intval($board['denials']); ?></strong></div>
        <div class="badge badge-danger" style="font-size:14px;padding:8px 14px">كسر زجاج نشط: <strong><?php echo intval($board['bg_active']); ?></strong></div>
    </div></div>

    <div class="card"><div class="card-header"><h5>⑦ التقارير — لماذا يملك فلان هذه الصلاحية؟</h5></div>
    <div class="card-body">
        <form method="post" class="allforms allforms-visible" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <input type="hidden" name="gov_action" value="explain">
            <div class="form-group"><label>الشخص</label>
                <select name="person_id"><?php foreach ($usersList as $u) { echo '<option value="' . intval($u['id']) . '"' . (isset($_POST['person_id']) && intval($_POST['person_id']) === intval($u['id']) ? ' selected' : '') . '>' . htmlspecialchars($u['name']) . '</option>'; } ?></select></div>
            <div class="form-group"><label>الصلاحية</label><input type="text" name="permission_code" value="<?php echo htmlspecialchars($_POST['permission_code'] ?? ''); ?>" placeholder="unit.approve" required></div>
            <div class="form-group"><label>النطاق</label><input type="text" name="scope" value="<?php echo htmlspecialchars($_POST['scope'] ?? ''); ?>" placeholder="site:18" required></div>
            <button type="submit" class="btn-save">فسّر</button>
        </form>
        <?php if ($explainResult !== null): ?>
        <pre style="direction:ltr;text-align:left;background:#f7f7f7;padding:12px;border-radius:8px;max-height:320px;overflow:auto"><?php
            echo htmlspecialchars(json_encode($explainResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-header"><h5>④ الاعتماد — طلبات تغيير الصلاحيات</h5></div>
    <div class="card-body">
        <?php if (!$pcrs) { ems_state_empty('لا طلبات تنتظر — نظيف ✨'); } else { ?>
        <div class="table-container"><table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>#</th><th>الشخص</th><th>نوع الفحص</th><th>المخاطرة</th><th>السبب</th><th>الباقي</th><th>الحالة</th><th></th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم المراجعة</th>
              <th class="ems-fn-th" data-fn="1">تاريخ المراجعة</th>
              <th class="ems-fn-th" data-fn="1">الحساب أو الدور</th>
              <th class="ems-fn-th" data-fn="1">الواجبان المتعارضان</th>
              <th class="ems-fn-th" data-fn="1">درجة الخطورة</th>
              <th class="ems-fn-th" data-fn="1">الاستثناءات القائمة</th>
              <th class="ems-fn-th" data-fn="1">الإجراء المتخذ</th>
              <th class="ems-fn-th" data-fn="1">تاريخ التنفيذ</th>
              <th class="ems-fn-th" data-fn="1">المراجع</th>
              <th class="ems-fn-th" data-fn="1">اعتمده</th>
              <th class="ems-fn-th" data-fn="1">تاريخ المراجعة القادمة</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              </tr></thead>
            <tbody>
            <?php foreach ($pcrs as $x): ?>
                <tr><td><?php echo intval($x['req_id']); ?></td>
                    <td>#<?php echo intval($x['person_id']); ?></td>
                    <td><?php echo htmlspecialchars($x['change_kind']); ?></td>
                    <td><?php echo htmlspecialchars($x['risk_level']); ?></td>
                    <td><small><?php echo htmlspecialchars(mb_substr($x['reason'], 0, 60)); ?></small></td>
                    <td><?php echo intval($x['remaining']); ?></td>
                    <td><?php echo htmlspecialchars($x['state']); ?></td>
                    <td>
                    <?php if ($can_edit && $x['state'] === 'pending'): ?>
                        <form method="post" style="display:inline"><input type="hidden" name="gov_action" value="decide_pcr">
                            <input type="hidden" name="req_id" value="<?php echo intval($x['req_id']); ?>">
                            <input type="hidden" name="decision" value="approve">
                            <button class="btn-save" type="submit">أوافق (بدوري)</button></form>
                    <?php elseif ($can_edit && $x['state'] === 'approved'): ?>
                        <form method="post" style="display:inline"><input type="hidden" name="gov_action" value="apply_pcr">
                            <input type="hidden" name="req_id" value="<?php echo intval($x['req_id']); ?>">
                            <button class="btn-save" type="submit">طبّق</button></form>
                    <?php endif; ?>
                    </td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php } ?>
    </div></div>

    <div class="card"><div class="card-header"><h5>⑤ المتابعة — الخامل والمنتهي وبلا مصدر</h5></div>
    <div class="card-body" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px">
        <div><strong>خامل 90 يومًا (<?php echo count($dormant); ?>)</strong><ul style="max-height:160px;overflow:auto">
            <?php foreach ($dormant as $d) { echo '<li>' . htmlspecialchars($d['name']) . '</li>'; } if (!$dormant) { echo '<li style="color:#888">لا خامل</li>'; } ?></ul></div>
        <div><strong>ينتهي خلال 30 يومًا (<?php echo count($expiring); ?>)</strong><ul style="max-height:160px;overflow:auto">
            <?php foreach ($expiring as $e) { echo '<li>' . htmlspecialchars($e['kind'] . ' #' . $e['id'] . ' — ' . $e['code'] . ' · ' . $e['ts']) . '</li>'; } if (!$expiring) { echo '<li style="color:#888">لا شيء</li>'; } ?></ul></div>
        <div><strong>صلاحيات بلا مصدر (<?php echo count($orphans); ?>)</strong><ul style="max-height:160px;overflow:auto">
            <?php foreach ($orphans as $o) { echo '<li>دور ' . intval($o['role_id']) . ' → موديول محذوف</li>'; } if (!$orphans) { echo '<li style="color:#888">صفر — كل صلاحية لها مصدر</li>'; } ?></ul></div>
    </div></div>

    <div class="card"><div class="card-header"><h5>⑥ الإغلاق — المراجعة الدورية ووضع التأسيس</h5></div>
    <div class="card-body">
        <?php if ($can_edit): ?>
        <form method="post" class="allforms allforms-visible" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <input type="hidden" name="gov_action" value="open_cycle">
            <div class="form-group"><label>الوحدة</label>
                <select name="org_unit_id"><?php foreach ($units as $u) { echo '<option value="' . intval($u['unit_id']) . '">' . htmlspecialchars($u['name_ar']) . '</option>'; } ?></select></div>
            <div class="form-group"><label>الفترة</label><input type="text" name="period" placeholder="2026-H2" required></div>
            <div class="form-group"><label>المدير الموقِّع</label>
                <select name="manager_person_id"><?php foreach ($usersList as $u) { echo '<option value="' . intval($u['id']) . '">' . htmlspecialchars($u['name']) . '</option>'; } ?></select></div>
            <button type="submit" class="btn-save">افتح مراجعة نصف سنوية</button>
        </form>
        <?php endif; ?>
        <div class="table-container"><table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>الدورة</th><th>الوحدة</th><th>الفترة</th><th>المهلة</th><th>بنود بلا قرار</th><th>الحالة</th></tr></thead>
            <tbody><?php foreach ($cycles as $c) {
                echo '<tr><td>' . intval($c['cycle_id']) . '</td><td>' . htmlspecialchars($c['unit_name'])
                    . '</td><td>' . htmlspecialchars($c['period']) . '</td><td>' . htmlspecialchars($c['due_at'])
                    . '</td><td>' . intval($c['open_lines']) . '</td><td>' . htmlspecialchars($c['state']) . '</td></tr>';
            } ?></tbody></table></div>
        <div style="margin-top:10px">
            <?php foreach ($founding as $f) {
                echo '<span class="badge ' . (intval($f['enabled']) ? 'badge-danger' : 'badge-secondary') . '" style="font-size:13px;padding:6px 12px;margin-inline-end:8px">وضع '
                    . htmlspecialchars($f['mode']) . ': ' . (intval($f['enabled']) ? ('مفعَّل حتى ' . $f['ends_at']) : 'مطفأ') . '</span>';
            } ?>
        </div>
    </div></div>

    <div class="card"><div class="card-header"><h5>⑧ الإعدادات — القوالب والحراس</h5></div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div><strong>القوالب بأصنافها</strong>
            <div class="table-container"><table class="alltables" style="width:100%" data-no-dt="1">
            <thead><tr><th>الصنف</th><th>عدد الحسابات المتأثرة</th><th>منشور</th></tr></thead>
            <tbody><?php foreach ($tplStats as $t) {
                echo '<tr><td>' . htmlspecialchars($t['tpl_kind']) . '</td><td>' . intval($t['total']) . '</td><td>' . intval($t['published']) . '</td></tr>';
            } ?></tbody></table></div></div>
        <div><strong>الحراس السبعة عشر — الاسم يصف السياسة</strong>
            <ul style="max-height:220px;overflow:auto">
            <?php foreach ($guards as $g) {
                $c = $g['overridable'] === 'never' ? '#c0392b' : ($g['overridable'] === 'break_glass_only' ? '#e67e22' : '#2980b9');
                echo '<li><span style="color:' . $c . '">●</span> ' . htmlspecialchars($g['name_ar']) . ' <small>(' . htmlspecialchars($g['overridable']) . ')</small></li>';
            } ?></ul></div>
    </div></div>

    <div class="card"><div class="card-body">
        <strong>② العمل اليومي:</strong> <a class="btn-save" href="sec_employee_wizard.php">معالج إعداد موظف — إحدى عشرة خطوة ▸</a>
        &nbsp; <strong>③ السجلات:</strong>
        <a class="btn-save" href="org_assignments.php">التكليفات ▸</a>
        <a class="btn-save" href="../admin/permissions/">قوالب الصلاحيات القديمة ▸</a>
        <a class="btn-save" href="../Governance/signing_authority.php">التفويضات ▸</a>
    </div></div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
