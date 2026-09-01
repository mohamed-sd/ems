<?php
/**
 * Transport/transfer_requests.php — طلبات الترحيل — §4/§3.3.
 * فورم إنشاء الطلب + قائمة + اعتماد/رفض + تحويل الطلب المعتمد إلى أمر ترحيل.
 * شاشة جديدة مستقلة — كتابة على transfer_requests/transfer_orders فقط؛ قراءة مرجعية.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/w14_grid.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/trs_helpers.php';

$ctx = trs_ctx();
$is_super_admin = $ctx['is_super'];
$company_id = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = trs_page_perms($conn, 'Transport/transfer_requests.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض طلبات الترحيل ❌', 'GOV-PERM-403', ''); exit(); }

$scope = $is_super_admin ? '1=1' : ('r.company_id = ' . intval($company_id));
$srcs = trs_source_modules(); $prios = trs_priorities(); $states = trs_request_states(); $dirs = trs_directions();

/** كود طلب تسلسلي بسيط لكل شركة. */
function trs_gen_req_code($conn, $company_id) {
    $y = date('Y'); $m = date('m');
    $n = trs_gate(false)->count('transfer_requests', array(
        'whereRaw' => 'code LIKE ?', 'params' => array("REQ-$y$m-%"),
        'includeDeleted' => true, // كالعدّ الخام الأصلي
    ));
    return "REQ-$y$m-" . str_pad((string)($n + 1), 4, '0', STR_PAD_LEFT);
}

// ════════════════ معالجة POST ════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        if (!$can_add) { ems_gov_flash_redirect('transfer_requests.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
        $transfer_type_id = intval($_POST['transfer_type_id'] ?? 0);
        $source_module = trim($_POST['source_module'] ?? '');
        $requested_by_user_id = ($_POST['requested_by_user_id'] ?? '') !== '' ? intval($_POST['requested_by_user_id']) : $current_user_id;
        $project_id = ($_POST['project_id'] ?? '') !== '' ? intval($_POST['project_id']) : null;
        $from_location_id = ($_POST['from_location_id'] ?? '') !== '' ? intval($_POST['from_location_id']) : null;
        $to_location_id = ($_POST['to_location_id'] ?? '') !== '' ? intval($_POST['to_location_id']) : null;
        $reason = trim($_POST['reason'] ?? '');
        $priority = trim($_POST['priority'] ?? 'normal');
        if ($transfer_type_id <= 0 || !array_key_exists($source_module, $srcs) || $reason === '') {
            ems_gov_flash_redirect('transfer_requests.php', 'بيانات الطلب غير مكتملة (النوع/المصدر/المبرر) ❌', 'GOV-FAIL-409', ''); exit();
        }
        $code = trs_gen_req_code($conn, $company_id);
        try {
            trs_gate(false)->insert('transfer_requests', array(
                'code' => $code, 'transfer_type_id' => $transfer_type_id, 'source_module' => $source_module,
                'requested_by_user_id' => $requested_by_user_id, 'project_id' => $project_id,
                'from_location_id' => $from_location_id, 'to_location_id' => $to_location_id,
                'reason' => $reason, 'priority' => $priority, 'state' => 'submitted',
                'created_by' => $current_user_id,
            ));
        } catch (\App\Core\TenantGateException $e) {
            error_log('transfer_requests create refused: ' . $e->getMessage());
            ems_gov_flash_redirect('transfer_requests.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
        }
        ems_gov_flash_redirect('transfer_requests.php', "تم تقديم الطلب ($code) ✅", 'GOV-OK-200', ''); exit();
    }

    // اعتماد / رفض
    if ($action === 'approve' || $action === 'reject') {
        if (!$can_edit) { ems_gov_flash_redirect('transfer_requests.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
        $rid = intval($_POST['request_id'] ?? 0);
        $new_state = ($action === 'approve') ? 'approved' : 'rejected';
        try {
            trs_gate(false)->update('transfer_requests', array('state' => $new_state),
                array('id' => $rid, 'state' => 'submitted')); // قفل تفاؤلي كالأصل
        } catch (\App\Core\TenantGateException $e) {
            error_log('transfer_requests state refused: ' . $e->getMessage());
        }
        $msg = ($action === 'approve') ? 'تم اعتماد الطلب ✅' : 'تم رفض الطلب ✅';
        ems_gov_flash_redirect('transfer_requests.php', "$msg", 'GOV-INFO-200', ''); exit();
    }

    // تحويل الطلب المعتمد إلى أمر ترحيل
    if ($action === 'convert') {
        if (!$can_edit) { ems_gov_flash_redirect('transfer_requests.php', 'لا توجد صلاحية ❌', 'GOV-PERM-403', ''); exit(); }
        $rid = intval($_POST['request_id'] ?? 0);
        $direction = trim($_POST['direction'] ?? '');
        if (!array_key_exists($direction, $dirs)) { ems_gov_flash_redirect('transfer_requests.php', 'الاتجاه إلزامي لتحويل الطلب أمرا ❌', 'GOV-FAIL-409', ''); exit(); }
        // اجلب الطلب المعتمد (عبر البوابة — الكتابة التالية بشركة السياق حصرًا،
        // فالقراءة تُنطَّق بها أيضًا: تسدّ حافة super القديمة غير المتسقة)
        $req = trs_gate(false)->selectOne('transfer_requests', array(
            'where' => array('id' => $rid, 'state' => 'approved'),
        ));
        if (!$req) { ems_gov_flash_redirect('transfer_requests.php', 'الطلب غير معتمد أو غير موجود ❌', 'GOV-REF-404', ''); exit(); }
        $from_loc = intval($req['from_location_id'] ?? 0);
        $to_loc = intval($req['to_location_id'] ?? 0);
        if ($from_loc <= 0 || $to_loc <= 0) { ems_gov_flash_redirect('transfer_requests.php', 'الطلب بلا موقعي انطلاق/وصول — أكملهما أولا ❌', 'GOV-FAIL-409', ''); exit(); }

        $project_id = ($req['project_id'] !== null) ? intval($req['project_id']) : null;
        $project_days = trs_project_days($conn, $company_id, $project_id);
        $cost_bearer = trs_compute_bearer($conn, $company_id, $direction, $project_days);
        $order_no = trs_gen_order_no($conn, $company_id, $direction, '');
        $stage = 'planned';

        // كتابتان مترابطتان (إنشاء الأمر + وسم الطلب) — كانتا غير معامَلتين أصلًا
        // (فجوة تناسقٍ كامنة): تُقوَّيان بمعاملةٍ مُدارة (عقد §9) دون مساسٍ بالمسار السعيد.
        try {
            $order_id = trs_gate(false)->runInTransaction(function ($g) use (
                $order_no, $rid, $req, $direction, $project_id, $from_loc, $to_loc,
                $project_days, $cost_bearer, $stage, $current_user_id
            ) {
                $oid = $g->insert('transfer_orders', array(
                    'order_no' => $order_no, 'request_id' => $rid,
                    'transfer_type_id' => intval($req['transfer_type_id']),
                    'direction' => $direction, 'source_module' => (string)$req['source_module'],
                    'requested_by_user_id' => ($req['requested_by_user_id'] !== null) ? intval($req['requested_by_user_id']) : null,
                    'project_id' => $project_id, 'from_location_id' => $from_loc, 'to_location_id' => $to_loc,
                    'request_date' => date('Y-m-d'), 'priority' => (string)$req['priority'],
                    'project_days' => $project_days, 'cost_bearer' => $cost_bearer,
                    'stage' => $stage, 'created_by' => $current_user_id,
                ));
                $g->update('transfer_requests', array('state' => 'converted', 'order_id' => $oid), array('id' => $rid));
                return $oid;
            }, 'convert request#' . $rid);
        } catch (\App\Core\TenantGateException $e) {
            error_log('transfer_requests convert rolled back: ' . $e->getMessage());
            ems_gov_flash_redirect('transfer_requests.php', 'تعذر التحويل ❌', 'GOV-FAIL-409', ''); exit();
        }
        trs_log_event($conn, $company_id, $order_id, 'system', "تحويل الطلب {$req['code']} إلى أمر ($order_no)", 'submitted', $stage, $current_user_id, 'transport');
        ems_gov_redirect("Location: transfer_order_form.php?id=$order_id&msg=تم+تحويل+الطلب+إلى+أمر+($order_no)+✅"); exit();
    }
}

$page_title = 'إيكوبيشن | موافقات الترحيل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>
<div class="main trs-requests-main ems-unified-page-shell">
    <?php
    $header_title = 'موافقات الترحيل';
    $header_icon  = 'fa fa-file-circle-plus';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'طلب جديد'); }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php'); ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> سجل حقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_trp_transfer_requests
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'رقم الطلب' => 'g63',
            'تاريخ الطلب' => 'g64',
            'الجهة الطالبة' => 'g65',
            'مرجع أمر نقل الموارد' => 'g66',
            'نوع الحمولة' => 'g67',
            'كود المعدة/الصنف' => 'g68',
            'الوزن/الأبعاد' => 'g69',
            'من موقع' => 'g70',
            'إلى موقع' => 'g71',
            'التاريخ المطلوب' => 'g72',
            'الأولوية' => 'g73',
            'ملاحظات التحميل' => 'g74',
            'حالة الطلب' => 'g75',
            'المنشئ' => 'g76',
            'تاريخ الإنشاء' => 'g77',
            'حالة البيانات' => 'g78',
            'مرجع المصدر' => 'g79',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('trp_transfer_requests');
        echo ems_w14_grid('emsList_trp_transfer_requests', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في طلب الترحيل'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا طلبات ترحيل مقدمة بعد', 'قدم أول طلب بزر «طلب جديد» في رأس الشاشة');
    // TKT-15 · زر الإبلاغ السياقي — النقل والترحيل (§2-④)
    require_once __DIR__ . '/../includes/report_button.php';
    ems_report_button(array('screen' => 'transport'));
    ?>
    <?php trs_msg_banner(); ?>

    <form id="trsForm" action="transfer_requests.php" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="card-header"><h5><i class="fas fa-edit"></i> طلب ترحيل جديد</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label for="emsf_1596_2176e">نوع الترحيل <span class="required">*</span></label>
                <select name="transfer_type_id" required id="emsf_1596_2176e"><?php echo trs_type_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
            <div class="form-group"><label for="emsf_1597_ea58c">الإدارة المصدر <span class="required">*</span></label>
                <select name="source_module" required id="emsf_1597_ea58c"><option value="">— اختر —</option>
                <?php foreach ($srcs as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label for="emsf_1598_bb195">الجهة الطالبة</label>
                <select name="requested_by_user_id" id="emsf_1598_bb195"><?php echo trs_user_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
            <div class="form-group"><label for="emsf_1599_9d0f5">المشروع</label>
                <select name="project_id" id="emsf_1599_9d0f5"><?php echo trs_project_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
            <div class="form-group"><label for="emsf_1600_0bada">من موقع</label>
                <select name="from_location_id" id="emsf_1600_0bada"><?php echo trs_location_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
            <div class="form-group"><label for="emsf_1601_02058">إلى موقع</label>
                <select name="to_location_id" id="emsf_1601_02058"><?php echo trs_location_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
            <div class="form-group"><label for="emsf_1602_e56fc">الأولوية</label>
                <select name="priority" id="emsf_1602_e56fc"><?php foreach ($prios as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group trs-rq-full"><label for="emsf_1603_27f33">مبرر الاحتياج <span class="required">*</span></label>
                <input type="text" name="reason" required placeholder="سبب الترحيل ومصدره" id="emsf_1603_27f33"></div>
        </div></div>
        <div class="form-actions">
            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> تقديم الطلب</button>
            <button type="button" class="btn-secondary" onclick="trsToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
        </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body"><div class="table-container">
        <table id="reqTable" class="display nowrap alltables trs-rq-tbl" data-order='[[1,"desc"]]' data-state-save="false" data-scroll-x="true"><thead><tr>
            <th>الإجراءات</th><th>كود العنصر</th><th>نوع الترحيل</th><th>مصدر تحميل التكلفة</th><th>المشروع</th><th>المبرر</th><th>الأولوية</th><th>الحالة</th>
            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
            <th class="ems-fn-th" data-fn="1">رقم الطلب</th>
            <th class="ems-fn-th" data-fn="1">تاريخ الطلب</th>
            <th class="ems-fn-th" data-fn="1">الإدارة الطالبة</th>
            <th class="ems-fn-th" data-fn="1">العنصر المطلوب ترحيله</th>
            <th class="ems-fn-th" data-fn="1">من</th>
            <th class="ems-fn-th" data-fn="1">إلى</th>
            <th class="ems-fn-th" data-fn="1">التاريخ المطلوب</th>
            <th class="ems-fn-th" data-fn="1">قدمه</th>
            <th class="ems-fn-th" data-fn="1">اعتمده</th>
            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
            </tr></thead><tbody>
        <?php
        // scopedQuery (عقد §10): النص الأصلي حرفيًا + الرمز؛ types/project إثراء LEFT
        $req_rows = trs_gate($is_super_admin)->scopedQuery(
            array('scope' => array('r' => 'transfer_requests'),
                  'enrich' => array('tt' => 'transfer_types', 'p' => 'project')),
            "SELECT r.*, tt.name AS type_name, p.name AS project_name
             FROM transfer_requests r
             LEFT JOIN transfer_types tt ON tt.id = r.transfer_type_id
             LEFT JOIN project p ON p.id = r.project_id
             WHERE {TENANT_SCOPE} ORDER BY r.id DESC"
        );
        foreach ($req_rows as $row) {
            $state = $row['state'];
            $src_ar = trs_label($srcs, $row['source_module']);
            $prio_ar = trs_label($prios, $row['priority']);
            $state_ar = trs_label($states, $state);
            $scolor = array('submitted' => 'var(--c-0d6efd, #0d6efd)', 'approved' => 'var(--c-198754, #198754)',
                            'converted' => 'var(--c-212529, #212529)', 'rejected' => 'var(--c-dc3545, #dc3545)');
            $sc = $scolor[$state] ?? 'var(--c-6c757d, #6c757d)';
            echo "<tr>";
            echo "<td><div class='action-btns trs-rq-actgap'>";
            if ($can_edit && $state === 'submitted') {
                echo "<form method='post' class='trs-rq-inline'>" . csrf_field() . "<input type='hidden' name='action' value='approve'><input type='hidden' name='request_id' value='" . intval($row['id']) . "'><button class='action-btn trs-rq-ok' title='اعتماد'><i class='fas fa-check'></i></button></form>";
                echo "<form method='post' class='trs-rq-inline' onsubmit='return confirm(\"رفض الطلب؟\")'>" . csrf_field() . "<input type='hidden' name='action' value='reject'><input type='hidden' name='request_id' value='" . intval($row['id']) . "'><button class='action-btn delete' title='رفض'><i class='fas fa-times'></i></button></form>";
            }
            if ($can_edit && $state === 'approved') {
                echo "<button type='button' class='action-btn edit convertBtn' data-id='" . intval($row['id']) . "' title='تحويل لأمر'><i class='fas fa-right-to-bracket'></i></button>";
            }
            if ($state === 'converted' && $row['order_id']) {
                echo "<a href='transfer_order_form.php?id=" . intval($row['order_id']) . "' class='action-btn trs-rq-link' title='فتح الأمر'><i class='fas fa-up-right-from-square'></i></a>";
            }
            echo "</div></td>";
            echo "<td>" . htmlspecialchars((string)$row['code']) . "</td>";
            echo "<td>" . htmlspecialchars((string)($row['type_name'] ?? '—')) . "</td>";
            echo "<td>" . htmlspecialchars($src_ar) . "</td>";
            echo "<td>" . htmlspecialchars((string)($row['project_name'] ?? '—')) . "</td>";
            echo "<td>" . htmlspecialchars((string)$row['reason']) . "</td>";
            echo "<td>" . htmlspecialchars($prio_ar) . "</td>";
            echo "<td><span class='action-btn trs-rq-chip' data-allow-style style='background:$sc'>" . htmlspecialchars($state_ar) . "</span></td>";
            echo "</tr>";
        }
        ?>
        </tbody></table>
    </div></div></div>
</div>

<!-- نافذة اختيار الاتجاه عند التحويل -->
<div id="convertModal" class="trs-rq-modal">
  <div class="trs-rq-modal-box">
    <h5 class="trs-rq-modal-title"><i class="fa fa-arrows-turn-right"></i> تحويل الطلب إلى أمر</h5>
    <form method="post">
        <?= csrf_field() ?>
      <input type="hidden" name="action" value="convert">
      <input type="hidden" name="request_id" id="convert_rid" value="">
      <div class="form-group"><label for="emsf_1604_5b717">الاتجاه (إلزامي) <span class="required">*</span></label>
        <select name="direction" required class="trs-rq-dirsel" id="emsf_1604_5b717">
          <option value="">— اختر الاتجاه —</option>
          <?php foreach ($dirs as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?>
        </select></div>
      <div class="trs-rq-modal-actions">
        <button type="submit" class="btn-primary"><i class="fas fa-check"></i> تحويل</button>
        <button type="button" class="btn-secondary" onclick="document.getElementById('convertModal').classList.remove('is-open')"><i class="fas fa-times"></i> إلغاء</button>
      </div>
    </form>
  </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
(function () {
    $(document).ready(function () {
        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) toggleBtn.addEventListener('click', function () { document.getElementById('trsForm').reset(); $('#trsForm').toggleClass('allforms-visible'); });
        $(document).on('click', '.convertBtn', function () {
            $('#convert_rid').val($(this).data('id'));
            document.getElementById('convertModal').classList.add('is-open');
        });
    });
    window.trsToggleForm = function () {
        var f = $('#trsForm');
        if (f.hasClass('allforms-visible')) f.removeClass('allforms-visible').slideUp();
        else { document.getElementById('trsForm').reset(); f.addClass('allforms-visible').slideDown(); }
    };
})();
</script>
</body>
</html>
