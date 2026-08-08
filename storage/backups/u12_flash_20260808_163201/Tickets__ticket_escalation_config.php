<?php
/**
 * Tickets/ticket_escalation_config.php — إعداد سلّم التصعيد.
 *
 * مستوياتُ التصعيد مجرّدةٌ وتُترجَم إلى أدوارٍ حقيقيّةٍ في النظام عبر
 * tkt_escalation_target_role باستثمار شجرة الأدوار: رئيس القسم = الإدارة
 * المالكة · مدير الإدارة = الدور الأب لها · مدير التشغيل · الإدارة العليا.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/tkt_helpers.php';

$ctx = tkt_ctx();
$is_super_admin = $ctx['is_super'];
$company_id = $ctx['company_id'];
if (!$is_super_admin && $company_id <= 0) { header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+❌"); exit(); }
$perms = tkt_page_perms($conn, 'Tickets/ticket_escalation_config.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit'];
if (!$can_view) { header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+سلّم+التصعيد+❌"); exit(); }

$levels = array(
    'responsible'  => 'المسؤول / الإدارة المالكة',
    'dept_head'    => 'رئيس القسم (الإدارة المالكة)',
    'dept_manager' => 'مدير الإدارة (الأب في شجرة الأدوار)',
    'ops_manager'  => 'مدير التشغيل',
    'top_mgmt'     => 'الإدارة العليا (مدير البلاغات)',
);
$channels = array('in_app' => 'داخل النظام', 'email' => 'بريد', 'both' => 'كلاهما');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id = intval($_POST['id'] ?? 0);
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: ticket_escalation_config.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: ticket_escalation_config.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0) { header("Location: ticket_escalation_config.php?msg=لا+يمكن+الحفظ+بلا+شركة+❌"); exit(); }

    $name = trim($_POST['name'] ?? '');
    $level_no = intval($_POST['level_no'] ?? 1);
    $after = (float)($_POST['escalate_after_hours'] ?? 0);
    $to_role = trim($_POST['escalate_to_role'] ?? '');
    $channel = trim($_POST['notify_channel'] ?? 'in_app');
    $active = isset($_POST['active']) ? 1 : 0;
    if (!array_key_exists($to_role, $levels)) { $to_role = ''; }
    if (!array_key_exists($channel, $channels)) { $channel = 'in_app'; }
    if ($name === '' || $to_role === '' || $after <= 0 || $level_no < 1 || $level_no > 5) {
        header("Location: ticket_escalation_config.php?msg=بيانات+غير+مكتملة+(المستوى+1..5)+❌"); exit();
    }
    $data = array('name' => $name, 'level_no' => $level_no, 'escalate_after_hours' => $after,
                  'escalate_to_role' => $to_role, 'notify_channel' => $channel, 'active' => $active);
    try {
        if ($is_editing) { tkt_gate(false)->update('ticket_escalation_rules', $data, array('id' => $id));
            header("Location: ticket_escalation_config.php?msg=تم+تعديل+القاعدة+✅"); exit(); }
        tkt_gate(false)->insert('ticket_escalation_rules', $data);
        header("Location: ticket_escalation_config.php?msg=تمت+إضافة+القاعدة+✅"); exit();
    } catch (\App\Core\TenantGateException $e) {
        error_log('escalation rule save refused: ' . $e->getMessage());
        header("Location: ticket_escalation_config.php?msg=حدث+خطأ+أثناء+الحفظ+❌"); exit();
    }
}

$page_title = 'إيكوبيشن | سلّم التصعيد';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main tkt-esc-main ems-unified-page-shell">
    <?php
    $header_title = 'سلّم التصعيد';
    $header_icon  = 'fa fa-arrow-up-right-dots';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة مستوى'); }
    $header_back = array('href' => 'tickets_list.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php tkt_msg_banner(); ?>

    <div class="success-message is-success" style="background:#f7f3e6;color:#6b5a1e;">
        <i class="fas fa-circle-info"></i>
        يعمل السلّم عبر <strong>دورة البلاغات المجدوَلة</strong>: عند تجاوز موعد الإنجاز بالساعات المحدَّدة يُرسَل إشعارٌ للدور الهدف (يُحسب من شجرة الأدوار الحيّة) ويُقيَّد حدثُ تصعيدٍ على التذكرة.
    </div>

    <form id="tktForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل مستوى تصعيد</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="e_id" value="">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>اسم القاعدة <span class="required">*</span></label><input type="text" name="name" id="e_name" required></div>
                <div class="form-group"><label>المستوى (1..5) <span class="required">*</span></label><input type="number" min="1" max="5" name="level_no" id="e_level" value="1" required></div>
                <div class="form-group"><label>يُصعَّد بعد (ساعات من تجاوز الإنجاز) <span class="required">*</span></label><input type="number" step="0.5" min="0.5" name="escalate_after_hours" id="e_after" required></div>
                <div class="form-group"><label>الجهة المُصعَّد إليها <span class="required">*</span></label>
                    <select name="escalate_to_role" id="e_to" required>
                        <?php foreach ($levels as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>قناة الإشعار</label>
                    <select name="notify_channel" id="e_channel">
                        <?php foreach ($channels as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>مفعّلة؟</label><label class="switch-inline"><input type="checkbox" name="active" id="e_active" value="1" checked> نعم</label></div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="tktToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body"><div class="table-container">
        <table id="tktTable" class="display nowrap alltables no-datatable" style="width:100%;">
            <thead><tr><th>الإجراءات</th><th>المستوى قبل</th><th>الاسم</th><th>بعد (ساعات)</th><th>الجهة</th><th>القناة</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم التصعيد</th>
              <th class="ems-fn-th" data-fn="1">البلاغ</th>
              <th class="ems-fn-th" data-fn="1">المهلة المتجاوزة</th>
              <th class="ems-fn-th" data-fn="1">المستوى بعد</th>
              <th class="ems-fn-th" data-fn="1">المصعَّد إليه</th>
              <th class="ems-fn-th" data-fn="1">تاريخ التصعيد</th>
              <th class="ems-fn-th" data-fn="1">مدة التجاوز</th>
              <th class="ems-fn-th" data-fn="1">المالك الأصلي</th>
              <th class="ems-fn-th" data-fn="1">هل بقي مسؤولًا؟</th>
              <th class="ems-fn-th" data-fn="1">الإجراء المتخذ</th>
              <th class="ems-fn-th" data-fn="1">تاريخ رفع التصعيد</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              </tr></thead>
            <tbody>
            <?php
            $rows = tkt_gate($is_super_admin)->select('ticket_escalation_rules', array('orderBy' => 'level_no ASC, id ASC'));
            foreach ($rows as $row) {
                $attrs = "data-id='" . intval($row['id']) . "' "
                    . "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' "
                    . "data-level='" . intval($row['level_no']) . "' "
                    . "data-after='" . htmlspecialchars((string)$row['escalate_after_hours'], ENT_QUOTES) . "' "
                    . "data-to='" . htmlspecialchars((string)$row['escalate_to_role'], ENT_QUOTES) . "' "
                    . "data-channel='" . htmlspecialchars((string)$row['notify_channel'], ENT_QUOTES) . "' "
                    . "data-active='" . intval($row['active']) . "'";
                echo "<tr><td><div class='action-btns'>";
                if ($can_edit) { echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $attrs title='تعديل'><i class='fas fa-edit'></i></a>"; }
                echo "</div></td>";
                echo "<td><strong>" . intval($row['level_no']) . "</strong></td>";
                echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                echo "<td>" . htmlspecialchars((string)$row['escalate_after_hours']) . "</td>";
                echo "<td>" . htmlspecialchars(tkt_label($levels, $row['escalate_to_role'])) . "</td>";
                echo "<td>" . htmlspecialchars(tkt_label($channels, $row['notify_channel'])) . "</td>";
                echo "<td>" . tkt_active_badge($row['active']) . "</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div></div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
(function () {
    $(document).ready(function () {
        $('#tktTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false,
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
        var b = document.getElementById('toggleForm');
        if (b) { b.addEventListener('click', function () {
            document.getElementById('tktForm').reset(); $('#e_id').val('');
            $('#tktForm').toggleClass('allforms-visible'); }); }
        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#e_id').val($t.data('id')); $('#e_name').val($t.data('name'));
            $('#e_level').val($t.data('level')); $('#e_after').val($t.data('after'));
            $('#e_to').val($t.data('to')); $('#e_channel').val($t.data('channel'));
            $('#e_active').prop('checked', String($t.data('active')) === '1');
            $('#tktForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#tktForm').offset().top }, 400);
        });
    });
    window.tktToggleForm = function () {
        var f = $('#tktForm');
        if (f.hasClass('allforms-visible')) { f.removeClass('allforms-visible').slideUp(); }
        else { document.getElementById('tktForm').reset(); $('#e_id').val(''); f.addClass('allforms-visible').slideDown(); }
    };
})();
</script>
</body>
</html>
