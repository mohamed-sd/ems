<?php
/** بوابة الطلب المالي D05 — مكتب محاسب الإدارة (§3.2): ضبط الحساب والأبعاد وولادة الحدث event_id */
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
$gate = $is_super ? ems_tenant_db()->forAllTenants('fin accountant desk super') : ems_tenant_db();

$__pp = check_page_permissions($conn, 'FinRequests/accountant_desk.php');
if (!$is_super && !$__pp['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', '❌ لا صلاحية', 'GOV-PERM-403', '');
    exit();
}

$catalog = finreq_catalog();
$routing_all = finreq_active_routing($gate);
$route_map = array();
foreach ($routing_all as $rt) { $route_map[$rt['source_module']] = $rt; }

// المعتمَد إداريًّا بانتظار ولادة حدثه (event_id فارغ) — قاعدة العبور
$rows = $gate->select('fin_requests', array(
    'whereRaw' => "state = 'pending_approval' AND event_id IS NULL",
    'params' => array(),
    'orderBy' => 'id DESC', 'limit' => 300,
));

// دليل الحسابات للاختيار (قاعدة الحساب: شأن المحاسب لا الطالب) — القابل للترحيل النشط فقط
$accounts = $gate->select('fin_chart_of_accounts', array(
    'columns' => array('id', 'code', 'name'),
    'where'   => array('active' => 1, 'is_postable' => 1),
    'orderBy' => 'code ASC', 'limit' => 500,
));

// بنود موازنة الشركة بمتبقيها (بوابة الميزانية §3.4-②) — القائمة بدل الرقم الحر
$budget_lines = finreq_budget_lines($gate);

$page_title = 'إيكوبيشن | مكتب المحاسب';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include('../inheader.php');
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell finreq-main ems-doc-cycle">
    <?php
    $header_title   = 'مكتب المحاسب — ولادة الحدث المالي';
    $header_icon    = 'fa fa-calculator';
    $header_actions = array(array('href' => 'finance_gateway.php', 'class' => 'add-btn', 'icon' => 'fa fa-building-columns', 'label' => 'بوابة المالية'));
    $header_back    = array('href' => '../main/dashboard.php', 'class' => 'back-btn', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑫: شاشةُ دورةٍ مستندية — كلُّ ما يَرِدُ هنا معتمَدٌ إداريًّا (pending_approval بلا حدث)
    // وخطوتُه التاليةُ التي تنطق بها الشاشةُ نفسُها: ولادةُ الحدثِ المالي.
    echo ems_next_step('ولادة الحدث المالي (D04)');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ)
    echo ems_states_bundle('لا مهامَّ تنتظر دورَك', 'ستظهر المعاملاتُ الواردةُ هنا فورَ إحالتِها');
    ?>
    <?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

    <?php if (isset($_GET['msg']) && trim($_GET['msg']) !== ''): ?>
        <div class="alert alert-info fad-alert"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>

    <?php foreach ($rows as $r):
        $rt = isset($route_map[$r['source_module']]) ? $route_map[$r['source_module']] : null;
    ?>
        <div class="card fad-card">
            <div class="card-header fad-card-head">
                <h5><i class="<?php echo htmlspecialchars($catalog[$r['request_type']]['icon']); ?>"></i>
                    <?php echo htmlspecialchars($r['request_no']); ?> — <?php echo htmlspecialchars($catalog[$r['request_type']]['label']); ?>
                    <?php echo finreq_state_badge($r['state']); ?>
                    <span class="badge bg-info"><?php echo htmlspecialchars($rt ? $rt['module_label'] : $r['source_module']); ?></span>
                    <?php if (intval($r['is_exception']) === 1): ?><span class="badge bg-danger">🚨 استثناء طارئ معتمد</span><?php endif; ?>
                    <?php if (strval($r['need_class']) === 'urgent'): ?><span class="badge bg-warning">⚡ عاجل — نصف المدد</span><?php endif; ?>
                </h5>
                <a href="request_form.php?id=<?php echo intval($r['id']); ?>" class="btn btn-sm btn-secondary"><i class="fa fa-eye"></i> التفاصيل والسجل</a>
            </div>
            <div class="card-body">
                <div class="fad-facts">
                    <div><strong>المبرّر:</strong> <?php echo htmlspecialchars($r['justification']); ?></div>
                    <div><strong>المستفيد:</strong> <?php echo htmlspecialchars($r['beneficiary_name'] ?? '-'); ?> (<?php echo htmlspecialchars($r['beneficiary_type']); ?>)</div>
                    <div><strong>المبلغ:</strong> <?php echo number_format(floatval($r['amount']), 2) . ' ' . htmlspecialchars($r['currency']); ?></div>
                    <div><strong>المرجع المصدري:</strong> <?php echo htmlspecialchars($r['source_ref'] ?? '—'); ?></div>
                </div>
                <?php $frag = finreq_fragmentation($gate, $r); if ($frag): ?>
                    <div class="alert alert-warning fad-warn">
                        ⚠️ كشف التجزئة (§8.5): <?php echo intval($frag['count']); ?> طلباتٍ حيّةٍ لنفس المستفيد والنوع خلال 30 يومًا
                        بمجموع <strong><?php echo number_format($frag['total'], 2) . ' ' . htmlspecialchars($r['currency']); ?></strong>
                        — طبّق مستوى الاعتماد على المجموع لا على هذا الطلب وحده (مصفوفة D04).
                    </div>
                <?php endif; ?>
                <form action="request_actions.php" method="post" class="allforms allforms-visible">
        <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="acct_forward">
                    <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                    <div class="form-grid">
                        <div>
                            <label for="emsf_165_22162">الحساب من الدليل * (قاعدة الحساب §5)</label>
                            <select name="account_id" required id="emsf_165_22162">
                                <option value="">— اختر الحساب —</option>
                                <?php foreach ($accounts as $a): ?>
                                    <option value="<?php echo intval($a['id']); ?>"><?php echo htmlspecialchars($a['code'] . ' — ' . $a['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="emsf_166_f0eb7">بند الموازنة (بوابة ② — التجاوز يستلزم استثناء §8.3)</label>
                            <select name="budget_line_id" id="emsf_166_f0eb7">
                                <option value="">— خارج الموازنة (يُدوَّن بقرارك) —</option>
                                <?php
                                $suggest = finreq_budget_category_for($r['request_type'], $r['source_module']);
                                foreach ($budget_lines as $bl):
                                    $over = floatval($r['amount']) > $bl['remaining'];
                                ?>
                                    <option value="<?php echo intval($bl['id']); ?>" <?php echo ($bl['category'] === $suggest && !$over) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($bl['category']); ?> — المتبقي <?php echo number_format($bl['remaining'], 2); ?>
                                        من <?php echo number_format(floatval($bl['planned_amount']), 2); ?><?php echo $over ? ' ⛔ لا يكفي' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div><label for="emsf_167_981ed">مركز التكلفة</label><input type="text" name="cost_center" maxlength="60" id="emsf_167_981ed" aria-label="مركز التكلفة" value="<?php echo htmlspecialchars($r['cost_center'] ?? ''); ?>"></div>
                        <div><label for="emsf_168_6e530">المشروع</label><input type="number" name="project_id" min="1" id="emsf_168_6e530" aria-label="المشروع" value="<?php echo htmlspecialchars($r['project_id'] ?? ''); ?>"></div>
                        <div><label for="emsf_169_8be43">المعدة</label><input type="number" name="equipment_id" min="1" id="emsf_169_8be43" aria-label="المعدة" value="<?php echo htmlspecialchars($r['equipment_id'] ?? ''); ?>"></div>
                        <div class="fad-self-end">
                            <button type="submit" class="btn btn-primary"><i class="fa fa-baby-carriage"></i> ولادة الحدث المالي (D04)</button>
                        </div>
                    </div>
                </form>
                <div class="fad-actions">
                    <form action="request_actions.php" method="post" class="fad-inline-form">
        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="return_request">
                        <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                        <input type="hidden" name="back" value="accountant_desk.php">
                        <input type="text" name="reason" placeholder="إعادة للمصدر بسببٍ (نقص تصنيف/أبعاد)" required class="fad-w240" aria-label="إعادة للمصدر بسببٍ (نقص تصنيف/أبعاد)">
                        <button type="submit" class="btn btn-secondary"><i class="fa fa-rotate-left"></i> إعادة للمصدر</button>
                    </form>
                    <?php if (intval($r['duplicate_flag']) === 1): ?>
                    <form action="request_actions.php" method="post" class="fad-inline-form">
        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="merge">
                        <input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
                        <input type="hidden" name="back" value="accountant_desk.php">
                        <input type="text" name="merge_into_no" placeholder="رقم الطلب الأصل FR-…" required class="fad-w160" aria-label="رقم الطلب الأصل FR-…">
                        <input type="text" name="reason" placeholder="سبب الدمج" required class="fad-w150" aria-label="سبب الدمج">
                        <button type="submit" class="btn btn-secondary"><i class="fa fa-code-merge"></i> دمج المكرّر</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; if (!$rows): ?>
        <?php echo ems_state('empty', 'لا مهامَّ تنتظر دورَك', 'ستظهر المعاملاتُ الواردةُ هنا فورَ إحالتِها'); ?>
    <?php endif; ?>
</div>
</body>
</html>
