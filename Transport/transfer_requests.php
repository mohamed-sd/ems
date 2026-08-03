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
include '../includes/permissions_helper.php';
require_once __DIR__ . '/trs_helpers.php';

$ctx = trs_ctx();
$is_super_admin = $ctx['is_super'];
$company_id = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }

$perms = trs_page_perms($conn, 'Transport/transfer_requests.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+طلبات+الترحيل+❌"); exit(); }

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
        if (!$can_add) { header("Location: transfer_requests.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
        $transfer_type_id = intval($_POST['transfer_type_id'] ?? 0);
        $source_module = trim($_POST['source_module'] ?? '');
        $requested_by_user_id = ($_POST['requested_by_user_id'] ?? '') !== '' ? intval($_POST['requested_by_user_id']) : $current_user_id;
        $project_id = ($_POST['project_id'] ?? '') !== '' ? intval($_POST['project_id']) : null;
        $from_location_id = ($_POST['from_location_id'] ?? '') !== '' ? intval($_POST['from_location_id']) : null;
        $to_location_id = ($_POST['to_location_id'] ?? '') !== '' ? intval($_POST['to_location_id']) : null;
        $reason = trim($_POST['reason'] ?? '');
        $priority = trim($_POST['priority'] ?? 'normal');
        if ($transfer_type_id <= 0 || !array_key_exists($source_module, $srcs) || $reason === '') {
            header("Location: transfer_requests.php?msg=بيانات+الطلب+غير+مكتملة+(النوع/المصدر/المبرّر)+❌"); exit();
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
            header("Location: transfer_requests.php?msg=حدث+خطأ+أثناء+الحفظ+❌"); exit();
        }
        header("Location: transfer_requests.php?msg=تم+تقديم+الطلب+($code)+✅"); exit();
    }

    // اعتماد / رفض
    if ($action === 'approve' || $action === 'reject') {
        if (!$can_edit) { header("Location: transfer_requests.php?msg=لا+توجد+صلاحية+❌"); exit(); }
        $rid = intval($_POST['request_id'] ?? 0);
        $new_state = ($action === 'approve') ? 'approved' : 'rejected';
        try {
            trs_gate(false)->update('transfer_requests', array('state' => $new_state),
                array('id' => $rid, 'state' => 'submitted')); // قفل تفاؤلي كالأصل
        } catch (\App\Core\TenantGateException $e) {
            error_log('transfer_requests state refused: ' . $e->getMessage());
        }
        $msg = ($action === 'approve') ? 'تم+اعتماد+الطلب+✅' : 'تم+رفض+الطلب+✅';
        header("Location: transfer_requests.php?msg=$msg"); exit();
    }

    // تحويل الطلب المعتمد إلى أمر ترحيل
    if ($action === 'convert') {
        if (!$can_edit) { header("Location: transfer_requests.php?msg=لا+توجد+صلاحية+❌"); exit(); }
        $rid = intval($_POST['request_id'] ?? 0);
        $direction = trim($_POST['direction'] ?? '');
        if (!array_key_exists($direction, $dirs)) { header("Location: transfer_requests.php?msg=الاتجاه+إلزامي+لتحويل+الطلب+أمراً+❌"); exit(); }
        // اجلب الطلب المعتمد (عبر البوابة — الكتابة التالية بشركة السياق حصرًا،
        // فالقراءة تُنطَّق بها أيضًا: تسدّ حافة super القديمة غير المتسقة)
        $req = trs_gate(false)->selectOne('transfer_requests', array(
            'where' => array('id' => $rid, 'state' => 'approved'),
        ));
        if (!$req) { header("Location: transfer_requests.php?msg=الطلب+غير+معتمد+أو+غير+موجود+❌"); exit(); }
        $from_loc = intval($req['from_location_id'] ?? 0);
        $to_loc = intval($req['to_location_id'] ?? 0);
        if ($from_loc <= 0 || $to_loc <= 0) { header("Location: transfer_requests.php?msg=الطلب+بلا+موقعَي+انطلاق/وصول+—+أكملهما+أولاً+❌"); exit(); }

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
            header("Location: transfer_requests.php?msg=تعذّر+التحويل+❌"); exit();
        }
        trs_log_event($conn, $company_id, $order_id, 'system', "تحويل الطلب {$req['code']} إلى أمر ($order_no)", 'submitted', $stage, $current_user_id, 'transport');
        header("Location: transfer_order_form.php?id=$order_id&msg=تم+تحويل+الطلب+إلى+أمر+($order_no)+✅"); exit();
    }
}

$page_title = 'إيكوبيشن | موافقات الترحيل';
include '../inheader.php';
include '../insidebar.php';
?>
<div class="main trs-requests-main ems-unified-page-shell">
    <?php
    $header_title = 'موافقات الترحيل';
    $header_icon  = 'fa fa-file-circle-plus';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'طلب جديد'); }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // TKT-15 · زر الإبلاغ السياقي — النقل والترحيل (§2-④)
    require_once __DIR__ . '/../includes/report_button.php';
    ems_report_button(array('screen' => 'transport'));
    ?>
    <?php trs_msg_banner(); ?>

    <form id="trsForm" action="transfer_requests.php" method="post" class="allforms">
        <input type="hidden" name="action" value="create">
        <div class="card-header"><h5><i class="fas fa-edit"></i> طلب ترحيل جديد</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label>نوع الترحيل <span class="required">*</span></label>
                <select name="transfer_type_id" required><?php echo trs_type_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
            <div class="form-group"><label>الإدارة المصدر <span class="required">*</span></label>
                <select name="source_module" required><option value="">— اختر —</option>
                <?php foreach ($srcs as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label>الجهة الطالبة</label>
                <select name="requested_by_user_id"><?php echo trs_user_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
            <div class="form-group"><label>المشروع</label>
                <select name="project_id"><?php echo trs_project_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
            <div class="form-group"><label>من موقع</label>
                <select name="from_location_id"><?php echo trs_location_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
            <div class="form-group"><label>إلى موقع</label>
                <select name="to_location_id"><?php echo trs_location_options($conn, $is_super_admin, $company_id, 0); ?></select></div>
            <div class="form-group"><label>الأولوية</label>
                <select name="priority"><?php foreach ($prios as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group" style="grid-column:1/-1"><label>مبرّر الاحتياج <span class="required">*</span></label>
                <input type="text" name="reason" required placeholder="سبب الترحيل ومصدره"></div>
        </div></div>
        <div class="form-actions">
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> تقديم الطلب</button>
            <button type="button" class="btn-cancel" onclick="trsToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
        </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body"><div class="table-container">
        <table id="reqTable" class="display nowrap alltables no-datatable" style="width:100%"><thead><tr>
            <th>الإجراءات</th><th>الكود</th><th>النوع</th><th>المصدر</th><th>المشروع</th><th>المبرّر</th><th>الأولوية</th><th>الحالة</th>
        
            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
            <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
            <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
            <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
            <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
            <th class="ems-gov-th" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
            <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
            <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
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
            $scolor = array('submitted' => '#0d6efd', 'approved' => '#198754', 'converted' => '#212529', 'rejected' => '#dc3545');
            $sc = $scolor[$state] ?? '#6c757d';
            echo "<tr>";
            echo "<td><div class='action-btns' style='gap:4px'>";
            if ($can_edit && $state === 'submitted') {
                echo "<form method='post' style='display:inline'><input type='hidden' name='action' value='approve'><input type='hidden' name='request_id' value='" . intval($row['id']) . "'><button class='action-btn' style='color:#198754' title='اعتماد'><i class='fas fa-check'></i></button></form>";
                echo "<form method='post' style='display:inline' onsubmit='return confirm(\"رفض الطلب؟\")'><input type='hidden' name='action' value='reject'><input type='hidden' name='request_id' value='" . intval($row['id']) . "'><button class='action-btn delete' title='رفض'><i class='fas fa-times'></i></button></form>";
            }
            if ($can_edit && $state === 'approved') {
                echo "<button type='button' class='action-btn edit convertBtn' data-id='" . intval($row['id']) . "' title='تحويل لأمر'><i class='fas fa-right-to-bracket'></i></button>";
            }
            if ($state === 'converted' && $row['order_id']) {
                echo "<a href='transfer_order_form.php?id=" . intval($row['order_id']) . "' class='action-btn' style='color:#0d6efd' title='فتح الأمر'><i class='fas fa-up-right-from-square'></i></a>";
            }
            echo "</div></td>";
            echo "<td>" . htmlspecialchars((string)$row['code']) . "</td>";
            echo "<td>" . htmlspecialchars((string)($row['type_name'] ?? '—')) . "</td>";
            echo "<td>" . htmlspecialchars($src_ar) . "</td>";
            echo "<td>" . htmlspecialchars((string)($row['project_name'] ?? '—')) . "</td>";
            echo "<td>" . htmlspecialchars((string)$row['reason']) . "</td>";
            echo "<td>" . htmlspecialchars($prio_ar) . "</td>";
            echo "<td><span class='action-btn' style='color:#fff;background:$sc;border-radius:12px;padding:2px 10px'>" . htmlspecialchars($state_ar) . "</span></td>";
            echo "</tr>";
        }
        ?>
        </tbody></table>
    </div></div></div>
</div>

<!-- نافذة اختيار الاتجاه عند التحويل -->
<div id="convertModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;min-width:320px;max-width:90%">
    <h5 style="margin-top:0"><i class="fa fa-arrows-turn-right"></i> تحويل الطلب إلى أمر</h5>
    <form method="post">
      <input type="hidden" name="action" value="convert">
      <input type="hidden" name="request_id" id="convert_rid" value="">
      <div class="form-group"><label>الاتجاه (إلزامي) <span class="required">*</span></label>
        <select name="direction" required style="width:100%;padding:8px">
          <option value="">— اختر الاتجاه —</option>
          <?php foreach ($dirs as $k => $v) echo "<option value='$k'>" . htmlspecialchars($v) . "</option>"; ?>
        </select></div>
      <div style="display:flex;gap:8px;margin-top:16px">
        <button type="submit" class="btn-save"><i class="fas fa-check"></i> تحويل</button>
        <button type="button" class="btn-cancel" onclick="document.getElementById('convertModal').style.display='none'"><i class="fas fa-times"></i> إلغاء</button>
      </div>
    </form>
  </div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
(function () {
    $(document).ready(function () {
        $('#reqTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, order: [[1, 'desc']], "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) toggleBtn.addEventListener('click', function () { document.getElementById('trsForm').reset(); $('#trsForm').toggleClass('allforms-visible'); });
        $(document).on('click', '.convertBtn', function () {
            $('#convert_rid').val($(this).data('id'));
            document.getElementById('convertModal').style.display = 'flex';
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
