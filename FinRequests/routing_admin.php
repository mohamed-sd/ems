<?php
/** بوابة الطلب المالي D05 — إدارة التوجيه: تفعيل الإدارات وأدوار المراجعة/الاعتماد
 *  (قرار «الجميع ينشئ عبر مفتاح تفعيلٍ لكل إدارة» — التعميم من هنا لا من الكود).
 *  للمدير المالي (17) والسوبر. المحاسب يُحلّ من fin_accountants ولا يُحرَّر هنا. */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/_finreq_helpers.php';

$role = strval($_SESSION['user']['role']);
$user_id = intval($_SESSION['user']['id']);
$is_super = ($role === '-1');
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin routing admin super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/routing_admin.php');
if (!$is_super && !($__pp['can_view'] && $role === '17')) {
    header('Location: ../main/dashboard.php?msg=' . urlencode('❌ إدارة التوجيه للمدير المالي حصرًا'));
    exit();
}

$modules_all = array(
    'sales' => 'المبيعات والإيرادات', 'suppliers' => 'الموردون', 'workforce' => 'القوى التشغيلية',
    'procurement' => 'المشتريات', 'warehouse' => 'المخازن', 'maintenance' => 'الصيانة',
    'projects' => 'المشاريع', 'revenue' => 'الإيرادات', 'assets' => 'الأصول',
    'treasury' => 'الخزينة', 'general' => 'عام',
);

// معالجة الحفظ (POST هنا نفسه — شاشة إعدادات صغيرة)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sm = isset($_POST['source_module']) && isset($modules_all[$_POST['source_module']]) ? $_POST['source_module'] : '';
    $requester_roles = preg_replace('/[^0-9,]/', '', (string)($_POST['requester_roles'] ?? ''));
    $reviewer = intval($_POST['reviewer_role_id'] ?? 0);
    $manager = intval($_POST['manager_role_id'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($sm === '' || $requester_roles === '' || $reviewer <= 0 || $manager <= 0) {
        header('Location: routing_admin.php?msg=' . urlencode('❌ أكمل الإدارة والأدوار الثلاثة'));
        exit();
    }
    try {
        $existing = finreq_routing_row($gate, $sm);
        $data = array(
            'module_label' => $modules_all[$sm],
            'requester_roles' => $requester_roles,
            'reviewer_role_id' => $reviewer,
            'manager_role_id' => $manager,
            'is_active' => $active,
        );
        $gate->runInTransaction(function ($g) use ($existing, $data, $sm) {
            if ($existing) {
                $g->update('fin_request_routing', $data, array('id' => intval($existing['id'])));
            } else {
                $data['source_module'] = $sm;
                $g->insert('fin_request_routing', $data);
            }
        });
        if (function_exists('log_security_event')) {
            log_security_event('finreq_routing_change', 'module=' . $sm . ' active=' . $active . ' by=' . $user_id);
        }
        header('Location: routing_admin.php?msg=' . urlencode('✅ حُفظ توجيه ' . $modules_all[$sm]));
        exit();
    } catch (\Throwable $t) {
        error_log('finreq routing save: ' . $t->getMessage());
        header('Location: routing_admin.php?msg=' . urlencode('❌ تعذّر الحفظ'));
        exit();
    }
}

$rows = $gate->select('fin_request_routing', array('orderBy' => 'module_label ASC'));
$roles_map = array();
try {
    foreach ($gate->select('roles', array('columns' => array('id', 'name'), 'orderBy' => 'id ASC')) as $rm) {
        $roles_map[intval($rm['id'])] = $rm['name'];
    }
} catch (\Throwable $t) { /* عرض */ }
$accountants = array();
try {
    foreach ($gate->select('fin_accountants', array('columns' => array('admin_module', 'employee_id'), 'where' => array('active' => 1))) as $ac) {
        $accountants[$ac['admin_module']] = intval($ac['employee_id']);
    }
} catch (\Throwable $t) { /* عرض */ }

$page_title = 'إيكوبيشن | قواعد توجيه الطلبات';
include('../inheader.php');
include('../insidebar.php');
?>
<div class="main ems-unified-page-shell finreq-main">
    <?php
    $header_title   = 'قواعد توجيه الطلبات';
    $header_icon    = 'fa fa-route';
    $header_actions = array(array('href' => 'finance_gateway.php', 'class' => 'add-btn', 'icon' => 'fa fa-building-columns', 'label' => 'بوابة المالية'));
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (isset($_GET['msg']) && trim($_GET['msg']) !== ''): ?>
        <div class="alert alert-info" style="margin-bottom:14px;font-weight:700;"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-header"><h5><i class="fas fa-table"></i> صفوف التوجيه القائمة</h5></div>
        <div class="card-body">
            <table class="table table-bordered no-datatable" data-no-dt="1">
                <thead><tr><th>الإدارة الطالبة</th><th>أدوار الإنشاء</th><th>المراجع (رئيس مباشر)</th><th>المعتمد (مدير الإدارة)</th><th>محاسبها (fin_accountants)</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم القاعدة</th>
              <th class="ems-fn-th" data-fn="1">نوع الطلب</th>
              <th class="ems-fn-th" data-fn="1">حد المبلغ من</th>
              <th class="ems-fn-th" data-fn="1">حد المبلغ إلى</th>
              <th class="ems-fn-th" data-fn="1">المعتمِد الأول</th>
              <th class="ems-fn-th" data-fn="1">المعتمِد الثاني</th>
              <th class="ems-fn-th" data-fn="1">المعتمِد الثالث</th>
              <th class="ems-fn-th" data-fn="1">الإدارة العامة مطلوبة؟</th>
              <th class="ems-fn-th" data-fn="1">درجة الخطورة</th>
              <th class="ems-fn-th" data-fn="1">مهلة كل حلقة</th>
              <th class="ems-fn-th" data-fn="1">تاريخ السريان</th>
              <th class="ems-fn-th" data-fn="1">عرّفها</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
              </tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['module_label']); ?></strong> <code><?php echo htmlspecialchars($r['source_module']); ?></code></td>
                        <td><?php
                            $names = array();
                            foreach (explode(',', $r['requester_roles']) as $rid) { $names[] = $roles_map[intval($rid)] ?? $rid; }
                            echo htmlspecialchars(implode(' · ', $names));
                        ?></td>
                        <td><?php echo htmlspecialchars($roles_map[intval($r['reviewer_role_id'])] ?? $r['reviewer_role_id']); ?></td>
                        <td><?php echo htmlspecialchars($roles_map[intval($r['manager_role_id'])] ?? $r['manager_role_id']); ?></td>
                        <td><?php echo isset($accountants[$r['source_module']]) ? ('موظف #' . $accountants[$r['source_module']]) : '<span class="badge bg-warning">بلا تعيين — يغطيه المحاسب المركزي (18)</span>'; ?></td>
                        <td><?php echo intval($r['is_active']) === 1 ? '<span class="badge bg-success">مفعّلة</span>' : '<span class="badge bg-secondary">موقوفة</span>'; ?></td>
                    </tr>
                <?php endforeach; if (!$rows): ?>
                    <tr><td colspan="6">لا صفوف توجيهٍ بعد</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5><i class="fa fa-plus-circle"></i> إضافة/تعديل توجيه إدارة</h5></div>
        <div class="card-body">
            <form method="post" class="allforms allforms-visible">
                <div class="form-grid">
                    <div>
                        <label>الإدارة *</label>
                        <select name="source_module" required>
                            <?php foreach ($modules_all as $k => $v): ?>
                                <option value="<?php echo $k; ?>"><?php echo $v; ?> (<?php echo $k; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>أدوار الإنشاء (أرقامًا بفواصل) *</label><input type="text" name="requester_roles" placeholder="13,14" required></div>
                    <div>
                        <label>دور المراجع (الرئيس المباشر) *</label>
                        <select name="reviewer_role_id" required>
                            <?php foreach ($roles_map as $rid => $rname): ?><option value="<?php echo $rid; ?>"><?php echo htmlspecialchars($rname); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>دور المعتمد (مدير الإدارة) *</label>
                        <select name="manager_role_id" required>
                            <?php foreach ($roles_map as $rid => $rname): ?><option value="<?php echo $rid; ?>"><?php echo htmlspecialchars($rname); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div style="align-self:end;">
                        <label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" name="is_active" value="1"> مفعّلة (يبدأ الإنشاء فورًا)</label>
                    </div>
                    <div style="align-self:end;"><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> حفظ التوجيه</button></div>
                </div>
            </form>
            <p style="margin-top:10px;color:#6b4e2a;">💡 التعميم المتدرج (القرار المقفل): فعّل إدارةً جديدة هنا متى نجحت سابقتها — لا حاجة لأي تعديل كود.</p>
        </div>
    </div>
</div>
</body>
</html>
