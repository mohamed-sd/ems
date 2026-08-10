<?php
require_once __DIR__ . '/../includes/permissions_helper.php';
/**
 * Operations/daily_plan.php — خطةُ عمل الغد: مساحةُ التوزيع (H-03 · UX-03 §2.2)
 * ───────────────────────────────────────────────────────────────────────────
 * الدورة: احتياجُ الغد (معدة×وردية من حاويات المشروع) ← توزيعُ المشغّلين
 * (من سلسلة حاوية كل معدةٍ حصرًا — «لا تخصيصَ خارج حاوية») بتحذير تعارضٍ
 * فوري ← اعتمادُ الحركة (لا اعتمادَ لمن أنشأ) ← فتحُ يوم الغد («لا يُفتح
 * موقعٌ ناقصُ التخصيص»). **الحكمُ كلُّه في DailyPlanService لا هنا.**
 *
 * **الصلاحيةُ صارمة** (نمطُ التسويات): الوحدةُ بكودها الحرفي وغيابُها منع.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
require_once __DIR__ . '/../app/Services/Operations/DailyPlanService.php';

use App\Services\Operations\DailyPlanService as DPS;

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$uid            = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

// ── صلاحيةٌ صارمة: الوحدةُ بكودها الحرفي وغيابُها منع ─────────────────────
$MODULE_CODE = 'Operations/daily_plan.php';
$can_view = $can_add = $can_edit = false;
if ($is_super_admin) {
    $can_view = $can_add = $can_edit = true;
} else {
    $st = $conn->prepare("SELECT rp.can_view, rp.can_add, rp.can_edit
                            FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.role_id = ? LIMIT 1");
    $rid = intval($current_role);
    $st->bind_param('si', $MODULE_CODE, $rid);
    $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) {
        $can_view = (intval($row['can_view']) === 1);
        $can_add  = (intval($row['can_add'])  === 1);
        $can_edit = (intval($row['can_edit']) === 1);
    }
    $st->close();
}
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض خطة الغد ❌', 'GOV-PERM-403', '');
    exit();
}

$gate = $is_super_admin ? ems_tenant_db()->forAllTenants('daily plan super') : ems_tenant_db();

$sel_project = intval($_GET['project'] ?? $_POST['project_id'] ?? 0);
$sel_date    = strval($_GET['date'] ?? $_POST['plan_date'] ?? date('Y-m-d', strtotime('+1 day')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sel_date)) { $sel_date = date('Y-m-d', strtotime('+1 day')); }

$dp_back = function ($msg) use ($sel_project, $sel_date) {
    ems_gov_redirect("Location: daily_plan.php?project={$sel_project}&date={$sel_date}&msg=" . rawurlencode($msg));
    exit();
};

// ── الأفعال (POST — الحكمُ في الخدمة) ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dp_action'])) {
    if (!$can_add && !$can_edit) { $dp_back('لا توجد صلاحية لهذا الإجراء ❌'); }
    $act = strval($_POST['dp_action']);
    if ($act === 'generate') {
        $r = DPS::generateNeeds($conn, $gate, $company_id, $sel_project, $sel_date, $uid);
        $dp_back($r['ok'] ? ('وُلّد الاحتياج: ' . $r['created'] . ' سطرًا جديدًا · ' . $r['existing'] . ' قائمًا ✅')
                          : ($r['reason'] . ' ❌'));
    } elseif ($act === 'assign') {
        $r = DPS::assign($conn, $gate, $company_id, intval($_POST['line_id'] ?? 0),
                         intval($_POST['operator_employee_id'] ?? 0), $uid);
        $dp_back($r['ok'] ? 'وُزّع ✅' : ($r['reason'] . ' ❌ (' . intval($r['code']) . ')'));
    } elseif ($act === 'approve') {
        $r = DPS::approve($conn, $gate, $company_id, intval($_POST['plan_id'] ?? 0), $uid);
        $dp_back($r['ok'] ? 'اعتمدت الحركةُ الخطة ✅' : ($r['reason'] . ' ❌ (' . intval($r['code']) . ')'));
    } elseif ($act === 'open') {
        $r = DPS::open($conn, $gate, $company_id, intval($_POST['plan_id'] ?? 0), $uid);
        $dp_back($r['ok'] ? 'فُتح يومُ الغد وأُشعرت المواقع ✅'
                          : ($r['reason'] . (empty($r['missing']) ? '' : ' — ' . implode(' · ', $r['missing'])) . ' ❌'));
    } elseif ($act === 'reopen') {
        $r = DPS::reopen($conn, $gate, $company_id, intval($_POST['plan_id'] ?? 0),
                         strval($_POST['reason'] ?? ''), $uid);
        $dp_back($r['ok'] ? 'أُرجعت الخطةُ للمسودة بسببها ✅' : ($r['reason'] . ' ❌'));
    }
    $dp_back('إجراءٌ غير معروف ❌');
}

// ── القراءة ────────────────────────────────────────────────────────────────
$projects_options = array();
try {
    $projects_options = $gate->scopedQuery(array('scope' => array('p' => 'project')),
        "SELECT p.id, p.name FROM project p
         WHERE {TENANT_SCOPE} AND COALESCE(p.is_deleted,0)=0 ORDER BY p.name");
} catch (\Throwable $t) { $projects_options = array(); }

$plan = null; $lines = array(); $chains = array();
if ($sel_project > 0) {
    try {
        $rows = $gate->scopedQuery(array('scope' => array('dp' => 'daily_plans')),
            "SELECT dp.* FROM daily_plans dp
             WHERE {TENANT_SCOPE} AND dp.project_id = ? AND dp.plan_date = ?
               AND COALESCE(dp.is_deleted,0)=0 LIMIT 1", array($sel_project, $sel_date));
        $plan = $rows ? $rows[0] : null;
    } catch (\Throwable $t) { $plan = null; }
    if ($plan) {
        try {
            $lines = $gate->scopedQuery(array(
                'scope'  => array('l' => 'daily_plan_lines'),
                'enrich' => array('e' => 'equipments', 'emp' => 'employees'),
            ), "SELECT l.*, e.name AS equipment_name, emp.name AS operator_name
                FROM daily_plan_lines l
                LEFT JOIN equipments e ON e.id = l.equipment_id
                LEFT JOIN employees emp ON emp.id = l.operator_employee_id
                WHERE {TENANT_SCOPE} AND l.plan_id = ?
                ORDER BY l.equipment_id, l.shift_no", array(intval($plan['id'])));
        } catch (\Throwable $t) { $lines = array(); }
        // سلسلةُ كل حاوية معدة (مشغّلوها المرشّحون — «لا تخصيصَ خارج حاوية»)
        foreach ($lines as $l) {
            $ecId = intval($l['equipment_container_id']);
            if (isset($chains[$ecId])) { continue; }
            try {
                $chains[$ecId] = $gate->scopedQuery(array(
                    'scope'  => array('o' => 'op_containers'),
                    'enrich' => array('emp' => 'employees'),
                ), "SELECT o.operator_employee_id, o.role_kind, emp.name
                    FROM op_containers o
                    LEFT JOIN employees emp ON emp.id = o.operator_employee_id
                    WHERE {TENANT_SCOPE} AND o.parent_id = ? AND o.level = 'مشغّل'
                      AND o.state = 'نشطة' AND COALESCE(o.is_deleted,0)=0
                    ORDER BY FIELD(COALESCE(o.role_kind,''),'أساسي','بديل أول','بديل ثانٍ','مشترك',''), o.id",
                    array($ecId));
            } catch (\Throwable $t) { $chains[$ecId] = array(); }
        }
        // H-04: المناوبُ المقترَح من جدول الدورات — اقتراحٌ لا فرضٌ (القرارُ للموزّع)
        require_once __DIR__ . '/../app/Services/Operations/RotationSwapService.php';
        $onDuty = array();
        foreach (array_keys($chains) as $ecId2) {
            $d = \App\Services\Operations\RotationSwapService::onDuty($gate, $ecId2, $sel_date);
            $onDuty[$ecId2] = $d['on_duty'];
        }
    }
}
$STATES = array('draft' => 'مسودة (توزيع)', 'approved' => 'معتمدةُ الحركة', 'opened' => 'مفتوحة ✓', 'closed' => 'مقفلة');
$editable = $plan && strval($plan['state']) === 'draft' && ($can_add || $can_edit);

$page_title = 'إيكوبيشن | خطة عمل الغد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title = 'خطة عمل الغد — مساحة التوزيع'; $header_icon = 'fa fa-calendar-day';
    $header_actions = array();
    $header_back = array('href' => 'containers.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'الحاويات');
    include('../includes/page_header.php');
    if (isset($_GET['msg'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_GET['msg']) . '</div>';
    }
    ?>

    <div class="card"><div class="card-body">
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <strong>المشروع:</strong>
            <select name="project" onchange="this.form.submit()" style="min-width:220px">
                <option value="0">— اختر —</option>
                <?php foreach ($projects_options as $p): ?>
                    <option value="<?php echo intval($p['id']); ?>" <?php echo $sel_project === intval($p['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <strong>اليوم:</strong>
            <input type="date" name="date" value="<?php echo htmlspecialchars($sel_date); ?>" onchange="this.form.submit()">
        </form>
    </div></div>

    <?php if ($sel_project > 0): ?>
    <div class="card"><div class="card-body">
        <?php if (!$plan): ?>
            <p>لا خطةَ لهذا اليوم بعد — الاحتياجُ يُولَّد من حاويات المعدات النشطة (لا من اليد).</p>
            <?php if ($can_add): ?>
            <form method="post" style="display:inline">
                <input type="hidden" name="dp_action" value="generate">
                <input type="hidden" name="project_id" value="<?php echo $sel_project; ?>">
                <input type="hidden" name="plan_date" value="<?php echo htmlspecialchars($sel_date); ?>">
                <button type="submit" class="btn-save"><i class="fa fa-bolt"></i> ولّد احتياجَ اليوم</button>
            </form>
            <?php endif; ?>
        <?php else: ?>
            <h5>
                خطة <?php echo htmlspecialchars($plan['plan_date']); ?> —
                <span class="badge <?php echo $plan['state'] === 'opened' ? 'badge-success'
                    : ($plan['state'] === 'approved' ? 'badge-info' : 'badge-secondary'); ?>">
                    <?php echo $STATES[$plan['state']] ?? htmlspecialchars($plan['state']); ?>
                </span>
                <?php if (!empty($plan['reopen_reason'])): ?>
                    <small title="سبب آخر إرجاع">↩ <?php echo htmlspecialchars($plan['reopen_reason']); ?></small>
                <?php endif; ?>
            </h5>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0">
                <?php if ($editable): ?>
                <form method="post" style="display:inline">
                    <input type="hidden" name="dp_action" value="generate">
                    <input type="hidden" name="project_id" value="<?php echo $sel_project; ?>">
                    <input type="hidden" name="plan_date" value="<?php echo htmlspecialchars($sel_date); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-primary">حدّث الاحتياج</button>
                </form>
                <form method="post" style="display:inline">
                    <input type="hidden" name="dp_action" value="approve">
                    <input type="hidden" name="plan_id" value="<?php echo intval($plan['id']); ?>">
                    <input type="hidden" name="project_id" value="<?php echo $sel_project; ?>">
                    <input type="hidden" name="plan_date" value="<?php echo htmlspecialchars($sel_date); ?>">
                    <button type="submit" class="btn btn-sm btn-info" title="لا اعتمادَ لمن أنشأ">اعتمادُ الحركة</button>
                </form>
                <?php elseif ($plan['state'] === 'approved' && $can_edit): ?>
                <form method="post" style="display:inline">
                    <input type="hidden" name="dp_action" value="open">
                    <input type="hidden" name="plan_id" value="<?php echo intval($plan['id']); ?>">
                    <input type="hidden" name="project_id" value="<?php echo $sel_project; ?>">
                    <input type="hidden" name="plan_date" value="<?php echo htmlspecialchars($sel_date); ?>">
                    <button type="submit" class="btn btn-sm btn-success" title="لا يُفتح موقعٌ ناقصُ التخصيص">افتح يومَ الغد</button>
                </form>
                <?php endif; ?>
                <?php if (in_array(strval($plan['state']), array('approved', 'opened'), true) && $can_edit): ?>
                <form method="post" style="display:inline-flex;gap:4px">
                    <input type="hidden" name="dp_action" value="reopen">
                    <input type="hidden" name="plan_id" value="<?php echo intval($plan['id']); ?>">
                    <input type="hidden" name="project_id" value="<?php echo $sel_project; ?>">
                    <input type="hidden" name="plan_date" value="<?php echo htmlspecialchars($sel_date); ?>">
                    <input type="text" name="reason" placeholder="سبب الإرجاع (إلزامي)" style="width:160px" aria-label="سبب الإرجاع (إلزامي)">
                    <button type="submit" class="btn btn-sm btn-outline-warning">أرجِع للمسودة</button>
                </form>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <table class="alltables display no-datatable" style="width:100%">
                    <thead><tr>
                        <th>نوع المعدة</th><th>الوردية</th><th>المشغّل الموزَّع</th>
                        <?php if ($editable): ?><th>التوزيع (من سلسلة حاويتها حصرًا)</th><?php endif; ?>
                        <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                        <th class="ems-fn-th" data-fn="1">رقم الخطة</th>
                        <th class="ems-fn-th" data-fn="1">تاريخ التنفيذ</th>
                        <th class="ems-fn-th" data-fn="1">الموقع</th>
                        <th class="ems-fn-th" data-fn="1">المشروع</th>
                        <th class="ems-fn-th" data-fn="1">رقم العقد</th>
                        <th class="ems-fn-th" data-fn="1">كود المعدة</th>
                        <th class="ems-fn-th" data-fn="1">المشغّل الأساسي</th>
                        <th class="ems-fn-th" data-fn="1">المشغّل البديل</th>
                        <th class="ems-fn-th" data-fn="1">الساعات المخططة</th>
                        <th class="ems-fn-th" data-fn="1">الكمية المستهدفة</th>
                        <th class="ems-fn-th" data-fn="1">جبهة العمل</th>
                        <th class="ems-fn-th" data-fn="1">حالة الجاهزية</th>
                        <th class="ems-fn-th" data-fn="1">أعدّها</th>
                        <th class="ems-fn-th" data-fn="1">اعتمدها</th>
                        <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                        <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                        <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                        <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                        <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                        <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                        <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                        <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                        </tr></thead>
                    <tbody>
                    <?php foreach ($lines as $l): $ecId = intval($l['equipment_container_id']); ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($l['equipment_name'] ?? ('#' . intval($l['equipment_id']))); ?></strong></td>
                            <td><?php echo intval($l['shift_no']); ?></td>
                            <td>
                                <?php if ($l['operator_employee_id']): ?>
                                    <span class="badge badge-success"><?php echo htmlspecialchars($l['operator_name'] ?? ('#' . intval($l['operator_employee_id']))); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-danger" title="لا يُفتح موقعٌ ناقصُ التخصيص">بلا مشغّل</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($editable): ?>
                            <td>
                                <form method="post" style="display:inline-flex;gap:4px">
                                    <input type="hidden" name="dp_action" value="assign">
                                    <input type="hidden" name="line_id" value="<?php echo intval($l['id']); ?>">
                                    <input type="hidden" name="project_id" value="<?php echo $sel_project; ?>">
                                    <input type="hidden" name="plan_date" value="<?php echo htmlspecialchars($sel_date); ?>">
                                    <select name="operator_employee_id">
                                        <option value="0">— أزل التخصيص —</option>
                                        <?php foreach (($chains[$ecId] ?? array()) as $c): ?>
                                            <option value="<?php echo intval($c['operator_employee_id']); ?>">
                                                <?php echo htmlspecialchars(($c['name'] ?? ('#' . intval($c['operator_employee_id'])))
                                                    . ($c['role_kind'] ? ' (' . $c['role_kind'] . ')' : '')
                                                    . ((isset($onDuty[$ecId]) && $onDuty[$ecId] === intval($c['operator_employee_id']))
                                                        ? ' ★ مناوبُ اليوم' : '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="action-btn edit" title="وزّع"><i class="fas fa-user-check"></i></button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$lines): ?>
                        <tr><td colspan="4">لا سطورَ بعد — ولّد الاحتياجَ من الحاويات</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div></div>
    <?php endif; ?>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
</body>
</html>
