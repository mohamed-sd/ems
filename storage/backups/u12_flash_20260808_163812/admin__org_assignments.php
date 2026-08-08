<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): SCN-769 · SCN-770 · SCN-771 · SCN-773 · SCN-774
/**
 * admin/org_assignments.php — شاشة التكليفات التنظيمية (update0004 · ORG-15)
 * ───────────────────────────────────────────────────────────────────────────
 * ORG-01 §2: «التكليف سجل تنظيمي رسمي لا تعديل صلاحية يدوي» — الإنشاء
 * والإنهاء والتعليق عبر AssignmentService بحرّاسه (422/403/409)، وكل فعل
 * سطر Insert-only في assignment_audit.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Org/AssignmentService.php';
require_once __DIR__ . '/../app/Services/Org/OrgAuthorityResolver.php';
require_once __DIR__ . '/../includes/screen_contract.php';

use App\Services\Org\AssignmentService as ASV;

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
$uid            = intval($_SESSION['user']['id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php"); exit(); }
if ($is_super_admin && $company_id <= 0) { $company_id = 4; }

$MODULE_CODE = 'admin/org_assignments.php';
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
if (!$can_view) { header("Location: ../main/dashboard.php?msg=" . rawurlencode('لا صلاحية لشاشة التكليفات ❌')); exit(); }

$msg = strval($_GET['msg'] ?? '');

// ── الأفعال — POST عادي (نمط Maintenance/orders.php) وكل حارس في الخدمة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['org_action'])) {
    if (!$can_edit) { header("Location: org_assignments.php?msg=" . rawurlencode('لا صلاحية تعديل ❌')); exit(); }
    $act = strval($_POST['org_action']);

    if ($act === 'create') {
        $lines = array();
        if (intval($_POST['line_operational'] ?? 0) > 0) {
            $lines[] = array('line_type' => 'operational', 'reports_to_assignment_id' => intval($_POST['line_operational']));
        }
        if (intval($_POST['line_functional'] ?? 0) > 0) {
            $lines[] = array('line_type' => 'functional', 'reports_to_assignment_id' => intval($_POST['line_functional']));
        }
        $r = ASV::create($conn, array(
            'company_id' => $company_id,
            'person_id' => intval($_POST['person_id'] ?? 0),
            'assignment_type_code' => strval($_POST['assignment_type_code'] ?? ''),
            'org_unit_id' => intval($_POST['org_unit_id'] ?? 0),
            'scope_type' => strval($_POST['scope_type'] ?? ''),
            'scope_id' => ($_POST['scope_id'] ?? '') !== '' ? intval($_POST['scope_id']) : null,
            'valid_from' => strval($_POST['valid_from'] ?? ''),
            'valid_to' => strval($_POST['valid_to'] ?? ''),
            'decided_by_person_id' => $uid,
            'decision_ref' => strval($_POST['decision_ref'] ?? '') ?: null,
            'deputy_person_id' => ($_POST['deputy_person_id'] ?? '') !== '' ? intval($_POST['deputy_person_id']) : null,
            'reporting_lines' => $lines,
        ));
        header("Location: org_assignments.php?msg=" . rawurlencode($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌')));
        exit();
    }
    if ($act === 'end' || $act === 'suspend') {
        $asgId = intval($_POST['asg_id'] ?? 0);
        $r = $act === 'end'
            ? ASV::end($conn, $asgId, $uid, strval($_POST['reason'] ?? ''))
            : ASV::suspend($conn, $asgId, $uid, strval($_POST['reason'] ?? ''));
        header("Location: org_assignments.php?msg=" . rawurlencode($r['reason'] . ($r['ok'] ? ' ✅' : ' ❌')));
        exit();
    }
}

// ── البيانات ──
$types = array();
$r = $conn->query("SELECT type_code, name_ar, level, requires_functional_line FROM org_assignment_types WHERE active=1 ORDER BY level, type_code");
while ($r && ($x = $r->fetch_assoc())) { $types[] = $x; }
$units = array();
$r = $conn->query("SELECT unit_id, unit_code, name_ar FROM org_units WHERE company_id={$company_id} AND active=1 ORDER BY unit_id");
while ($r && ($x = $r->fetch_assoc())) { $units[] = $x; }
$sitesList = array();
$r = $conn->query("SELECT id, name FROM sites WHERE company_id={$company_id} AND is_deleted=0 ORDER BY name");
while ($r && ($x = $r->fetch_assoc())) { $sitesList[] = $x; }
$usersList = array();
$r = $conn->query("SELECT id, name FROM users WHERE company_id={$company_id} ORDER BY name");
while ($r && ($x = $r->fetch_assoc())) { $usersList[] = $x; }
$activeAsg = array();
$r = $conn->query("SELECT a.asg_id, t.name_ar, u2.name person FROM org_assignments a
                    JOIN org_assignment_types t ON t.type_code=a.assignment_type_code
                    LEFT JOIN users u2 ON u2.id=a.person_id
                   WHERE a.company_id={$company_id} AND a.state='active' ORDER BY a.asg_id DESC LIMIT 200");
while ($r && ($x = $r->fetch_assoc())) { $activeAsg[] = $x; }

$rows = array();
$r = $conn->query(
    "SELECT a.*, t.name_ar type_name, t.level, ou.name_ar unit_name,
            u1.name person_name, u3.name deputy_name,
            CASE a.scope_type WHEN 'site' THEN s.name ELSE CONCAT(a.scope_type,':',a.scope_id) END scope_label
       FROM org_assignments a
       JOIN org_assignment_types t ON t.type_code = a.assignment_type_code
       JOIN org_units ou ON ou.unit_id = a.org_unit_id
       LEFT JOIN users u1 ON u1.id = a.person_id
       LEFT JOIN users u3 ON u3.id = a.deputy_person_id
       LEFT JOIN sites s ON a.scope_type='site' AND s.id = a.scope_id
      WHERE a.company_id = {$company_id}
      ORDER BY (a.state='active') DESC, a.valid_to ASC LIMIT 500");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

// سجل تكليف واحد
$auditRows = array(); $auditFor = intval($_GET['audit'] ?? 0);
if ($auditFor > 0) {
    $r = $conn->query("SELECT g.* FROM assignment_audit g
                        JOIN org_assignments a ON a.asg_id = g.asg_id
                       WHERE g.asg_id={$auditFor} AND a.company_id={$company_id} ORDER BY g.at DESC");
    while ($r && ($x = $r->fetch_assoc())) { $auditRows[] = $x; }
}

$page_title = 'إيكوبيشن | التكليفات التنظيمية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'التكليفات التنظيمية'; $header_icon = 'fa fa-id-badge';
    $header_actions = array();
    if ($can_edit) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'تكليف جديد');
    }
    include('../includes/page_header.php');

    ems_screen_about(
        'التكليف سجل تنظيمي بنطاق ومدة ومصدر قرار ونائب — لا تعديل صلاحية يدويًّا. '
        . 'انتهاء المدة يُسقط الصلاحية آليًّا، والموقعي له خطّا تبعية (تشغيلي وفني)، '
        . 'ومدير حركة واحد نشط لكل موقع.',
        array('أنشئ التكليف بمدة ونطاق إلزاميين',
              'الموقعي يحتاج الخطين معًا وإلا رُفض 422',
              'الإنهاء قرار موثَّق يسقط بصلاحيته فورًا — والسجل لا يُمحى'));

    if ($msg !== '') { echo '<div class="alert alert-info">' . htmlspecialchars($msg) . '</div>'; }
    ?>

    <?php if ($can_edit): ?>
    <form method="post" action="org_assignments.php" class="allforms" id="asgForm">
        <input type="hidden" name="org_action" value="create">
        <h5>تكليف جديد — المدة والنطاق إلزاميان (ORG-01 §2)</h5>
        <div class="form-row" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
            <div class="form-group"><label>الشخص *</label>
                <select name="person_id" required><option value="">— اختر —</option>
                    <?php foreach ($usersList as $u) { echo '<option value="' . intval($u['id']) . '">' . htmlspecialchars($u['name']) . '</option>'; } ?>
                </select></div>
            <div class="form-group"><label>نوع التكليف *</label>
                <select name="assignment_type_code" id="asgType" required><option value="">— اختر —</option>
                    <?php foreach ($types as $t) { echo '<option value="' . htmlspecialchars($t['type_code']) . '" data-func="' . intval($t['requires_functional_line']) . '">' . htmlspecialchars($t['name_ar']) . ' (' . ($t['level'] === 'site' ? 'موقعي' : 'مركزي') . ')</option>'; } ?>
                </select></div>
            <div class="form-group"><label>الوحدة *</label>
                <select name="org_unit_id" required><option value="">— اختر —</option>
                    <?php foreach ($units as $u) { echo '<option value="' . intval($u['unit_id']) . '">' . htmlspecialchars($u['name_ar']) . '</option>'; } ?>
                </select></div>
            <div class="form-group"><label>مرجع القرار</label><input type="text" name="decision_ref" placeholder="OPS-2026-…"></div>
            <div class="form-group"><label>نوع النطاق *</label>
                <select name="scope_type" required>
                    <option value="site">موقع</option><option value="project">مشروع</option>
                    <option value="site_group">مجموعة مواقع (0 = الكل)</option>
                </select></div>
            <div class="form-group"><label>الموقع / معرف النطاق *</label>
                <select name="scope_id" required>
                    <option value="0">— كل المواقع (site_group) —</option>
                    <?php foreach ($sitesList as $s) { echo '<option value="' . intval($s['id']) . '">' . htmlspecialchars($s['name']) . '</option>'; } ?>
                </select></div>
            <div class="form-group"><label>من *</label><input type="date" name="valid_from" required></div>
            <div class="form-group"><label>إلى * (لا تكليف مفتوح المدة)</label><input type="date" name="valid_to" required></div>
            <div class="form-group"><label>النائب المعتمد</label>
                <select name="deputy_person_id"><option value="">— بلا —</option>
                    <?php foreach ($usersList as $u) { echo '<option value="' . intval($u['id']) . '">' . htmlspecialchars($u['name']) . '</option>'; } ?>
                </select></div>
            <div class="form-group"><label>الخط التشغيلي (للموقعي *)</label>
                <select name="line_operational"><option value="">— اختر تكليفًا —</option>
                    <?php foreach ($activeAsg as $a) { echo '<option value="' . intval($a['asg_id']) . '">#' . intval($a['asg_id']) . ' ' . htmlspecialchars($a['name_ar'] . ' — ' . ($a['person'] ?: '')) . '</option>'; } ?>
                </select></div>
            <div class="form-group"><label>الخط الفني (للموقعي *)</label>
                <select name="line_functional"><option value="">— اختر تكليفًا —</option>
                    <?php foreach ($activeAsg as $a) { echo '<option value="' . intval($a['asg_id']) . '">#' . intval($a['asg_id']) . ' ' . htmlspecialchars($a['name_ar'] . ' — ' . ($a['person'] ?: '')) . '</option>'; } ?>
                </select></div>
        </div>
        <button type="submit" class="btn-save">حفظ التكليف</button>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <div class="table-container">
        <table class="alltables display nowrap" style="width:100%">
            <thead><tr><th>#</th><th>الشخص</th><th>نوع التكليف</th><th>الوحدة المكلَّف بها</th><th>النطاق</th>
                <th>من تاريخ</th><th>إلى تاريخ</th><th>النائب</th><th>الحالة</th><th>إجراءات</th>
                <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                <th class="ems-fn-th" data-fn="1">رقم التكليف</th>
                <th class="ems-fn-th" data-fn="1">الموظف</th>
                <th class="ems-fn-th" data-fn="1">المسمى الأصلي</th>
                <th class="ems-fn-th" data-fn="1">التكليف الجديد</th>
                <th class="ems-fn-th" data-fn="1">سقف الاعتماد</th>
                <th class="ems-fn-th" data-fn="1">بدل التكليف</th>
                <th class="ems-fn-th" data-fn="1">أصدره</th>
                <th class="ems-fn-th" data-fn="1">مرجع القرار</th>
                <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                </tr></thead>
            <tbody>
            <?php foreach ($rows as $x): ?>
                <tr>
                    <td><?php echo intval($x['asg_id']); ?></td>
                    <td><?php echo htmlspecialchars($x['person_name'] ?: ('#' . $x['person_id'])); ?></td>
                    <td><?php echo htmlspecialchars($x['type_name']); ?></td>
                    <td><?php echo htmlspecialchars($x['unit_name']); ?></td>
                    <td><?php echo htmlspecialchars($x['scope_label']); ?></td>
                    <td><?php echo htmlspecialchars($x['valid_from']); ?></td>
                    <td><?php echo htmlspecialchars($x['valid_to']); ?></td>
                    <td><?php echo htmlspecialchars($x['deputy_name'] ?: '—'); ?></td>
                    <td><?php echo $x['state'] === 'active' ? '<span class="badge badge-success">نافذ</span>'
                            : ($x['state'] === 'suspended' ? '<span class="badge badge-warning">معلَّق</span>'
                            : '<span class="badge badge-secondary">منتهٍ</span>'); ?></td>
                    <td>
                        <a class="action-btn" href="?audit=<?php echo intval($x['asg_id']); ?>" title="السجل"><i class="fas fa-history"></i></a>
                        <?php if ($can_edit && $x['state'] !== 'ended'): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('إنهاء التكليف؟ قرار موثَّق يسقط بالصلاحية فورًا');">
                            <input type="hidden" name="org_action" value="end">
                            <input type="hidden" name="asg_id" value="<?php echo intval($x['asg_id']); ?>">
                            <input type="hidden" name="reason" value="إنهاء من الشاشة">
                            <button type="submit" class="action-btn delete" title="إنهاء"><i class="fas fa-stop-circle"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div></div>

    <?php if ($auditFor > 0): ?>
    <div class="card"><div class="card-header"><h5><i class="fas fa-history"></i> سجل التكليف #<?php echo $auditFor; ?> — Insert-only</h5></div>
    <div class="card-body"><div class="table-container">
        <table class="alltables display nowrap" style="width:100%" data-no-dt="1">
            <thead><tr><th>الوقت</th><th>الفعل</th><th>سبب التكليف</th><th>قبل</th><th>بعد</th><th>بواسطة</th></tr></thead>
            <tbody>
            <?php foreach ($auditRows as $g): ?>
                <tr><td><?php echo htmlspecialchars($g['at']); ?></td>
                    <td><?php echo htmlspecialchars($g['action']); ?></td>
                    <td><?php echo htmlspecialchars($g['reason'] ?: '—'); ?></td>
                    <td><small><?php echo htmlspecialchars((string) $g['before_json']); ?></small></td>
                    <td><small><?php echo htmlspecialchars((string) $g['after_json']); ?></small></td>
                    <td><?php echo intval($g['by_person_id']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script>
document.getElementById('toggleForm')?.addEventListener('click', function () {
    document.getElementById('asgForm')?.classList.toggle('allforms-visible');
});
</script>
</body>
</html>
