<?php
/**
 * Tickets/ticket_recurrence.php — القوالب الدورية.
 *
 * التذاكر الدورية تُولَّد آليًّا لا تُنشأ يدويًّا: الدورة المجدوَلة تنشئ
 * التذكرة قبل موعدها بعدد أيام المهلة، ثم تدفع تاريخ التوليد التالي.
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
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../login.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-INFO-200', ''); exit(); }
$perms = tkt_page_perms($conn, 'Tickets/ticket_recurrence.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض القوالب الدورية ❌', 'GOV-PERM-403', ''); exit(); }

$units = array('day' => 'يوم', 'week' => 'أسبوع', 'month' => 'شهر', 'year' => 'سنة');
$priorities = tkt_priorities();
$roles_map = tkt_roles_map();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id = intval($_POST['id'] ?? 0);
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('ticket_recurrence.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('ticket_recurrence.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0) { ems_gov_flash_redirect('ticket_recurrence.php', 'لا يمكن الحفظ بلا شركة ❌', 'GOV-INFO-200', ''); exit(); }

    $name = trim($_POST['name'] ?? '');
    $type_id = intval($_POST['ticket_type_id'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? intval($_POST['equipment_id']) : null;
    $interval = max(1, intval($_POST['recurrence_interval'] ?? 1));
    $unit = trim($_POST['recurrence_unit'] ?? 'month');
    $next = trim($_POST['next_occurrence_date'] ?? '');
    $lead = max(0, intval($_POST['lead_time_days'] ?? 0));
    $priority = trim($_POST['default_priority'] ?? 'normal');
    $active = isset($_POST['active']) ? 1 : 0;
    if (!array_key_exists($unit, $units)) { $unit = 'month'; }
    if (!array_key_exists($priority, $priorities)) { $priority = 'normal'; }
    if ($name === '' || $type_id <= 0 || $next === '' || strtotime($next) === false) {
        ems_gov_flash_redirect('ticket_recurrence.php', 'الاسم والنوع وتاريخ التوليد التالي إلزامية ❌', 'GOV-INFO-200', ''); exit();
    }
    $data = array('name' => $name, 'ticket_type_id' => $type_id, 'equipment_id' => $equipment_id,
                  'recurrence_interval' => $interval, 'recurrence_unit' => $unit,
                  'next_occurrence_date' => date('Y-m-d', strtotime($next)),
                  'lead_time_days' => $lead, 'default_priority' => $priority, 'active' => $active);
    try {
        if ($is_editing) { tkt_gate(false)->update('ticket_recurrence_templates', $data, array('id' => $id));
            ems_gov_flash_redirect('ticket_recurrence.php', 'تم تعديل القالب ✅', 'GOV-OK-200', ''); exit(); }
        tkt_gate(false)->insert('ticket_recurrence_templates', $data);
        ems_gov_flash_redirect('ticket_recurrence.php', 'تمت إضافة القالب ✅', 'GOV-OK-200', ''); exit();
    } catch (\App\Core\TenantGateException $e) {
        error_log('recurrence template save refused: ' . $e->getMessage());
        ems_gov_flash_redirect('ticket_recurrence.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-INFO-200', ''); exit();
    }
}

$page_title = 'إيكوبيشن | البلاغات الدورية';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main tkt-rec-main ems-unified-page-shell">
    <?php
    $header_title = 'البلاغات الدورية';
    $header_icon  = 'fa fa-repeat';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة قالب'); }
    $header_back = array('href' => 'tickets_list.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php tkt_msg_banner(); ?>

    <div class="success-message is-success" style="background:#f7f3e6;color:#6b5a1e;">
        <i class="fas fa-circle-info"></i>
        تولّد <strong>دورة البلاغات المجدوَلة</strong> التذكرةَ قبل موعدها بعدد أيام المهلة، ثم تدفع «التوليد التالي» بمقدار الفاصل — فلا يُنسى تفتيشٌ ولا تجديد.
    </div>

    <form id="tktForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل قالب دوري</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="r_id" value="">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>اسم القالب <span class="required">*</span></label><input type="text" name="name" id="r_name" required></div>
                <div class="form-group"><label>نوع التذكرة المُولَّدة <span class="required">*</span></label>
                    <select name="ticket_type_id" id="r_type" required><?php echo tkt_type_options(); ?></select></div>
                <div class="form-group"><label>المعدة المستهدفة (اختياري)</label>
                    <select name="equipment_id" id="r_equipment"><?php echo tkt_equipment_options(); ?></select></div>
                <div class="form-group"><label>كل (مقدار) <span class="required">*</span></label><input type="number" min="1" name="recurrence_interval" id="r_interval" value="1" required></div>
                <div class="form-group"><label>الوحدة <span class="required">*</span></label>
                    <select name="recurrence_unit" id="r_unit"><?php foreach ($units as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>التوليد التالي <span class="required">*</span></label><input type="date" name="next_occurrence_date" id="r_next" required></div>
                <div class="form-group"><label>التوليد قبل الموعد بـ (أيام)</label><input type="number" min="0" name="lead_time_days" id="r_lead" value="0"></div>
                <div class="form-group"><label>أولوية التذكرة المُولَّدة</label>
                    <select name="default_priority" id="r_priority"><?php foreach ($priorities as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>مفعّل؟</label><label class="switch-inline"><input type="checkbox" name="active" id="r_active" value="1" checked> نعم</label></div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="tktToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body"><div class="table-container">
        <table id="tktTable" class="display nowrap alltables no-datatable" style="width:100%;">
            <thead><tr><th>الإجراءات</th><th>اسم القالب</th><th>النوع</th><th>الفاصل</th><th>التالي</th><th>المهلة</th><th>الأولوية</th><th>الحالة</th>
              <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
              <th class="ems-fn-th" data-fn="1">رقم المجموعة</th>
              <th class="ems-fn-th" data-fn="1">الموضوع</th>
              <th class="ems-fn-th" data-fn="1">الموقع أو المعدة</th>
              <th class="ems-fn-th" data-fn="1">عدد البلاغات</th>
              <th class="ems-fn-th" data-fn="1">النافذة الزمنية</th>
              <th class="ems-fn-th" data-fn="1">أول بلاغ</th>
              <th class="ems-fn-th" data-fn="1">آخر بلاغ</th>
              <th class="ems-fn-th" data-fn="1">إجمالي ساعات التوقف</th>
              <th class="ems-fn-th" data-fn="1">إجمالي التكلفة</th>
              <th class="ems-fn-th" data-fn="1">السبب الجذري المرجَّح</th>
              <th class="ems-fn-th" data-fn="1">الإجراء الجذري</th>
              <th class="ems-fn-th" data-fn="1">المسؤول</th>
              <th class="ems-fn-th" data-fn="1">تاريخ الإقفال</th>
              <th class="ems-fn-th" data-fn="1">رقم القالب</th>
              <th class="ems-fn-th none" data-fn="1">الفئة</th>
              <th class="ems-fn-th none" data-fn="1">الإدارة المستهدفة</th>
              <th class="ems-fn-th none" data-fn="1">المكلَّف الافتراضي</th>
              <th class="ems-fn-th none" data-fn="1">الدورية</th>
              <th class="ems-fn-th none" data-fn="1">يوم التوليد</th>
              <th class="ems-fn-th none" data-fn="1">آخر توليد</th>
              <th class="ems-fn-th none" data-fn="1">عدد المولَّد</th>
              <th class="ems-fn-th none" data-fn="1">نسبة الإنجاز</th>
              <th class="ems-fn-th none" data-fn="1">عرّفه</th>
              <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th none" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
              <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
              <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
              </tr></thead>
            <tbody>
            <?php
            $types = array();
            foreach (tkt_gate($is_super_admin)->select('ticket_types', array('columns' => array('id', 'name'))) as $t) { $types[intval($t['id'])] = $t['name']; }
            $rows = tkt_gate($is_super_admin)->select('ticket_recurrence_templates', array('orderBy' => 'id ASC'));
            foreach ($rows as $row) {
                $attrs = "data-id='" . intval($row['id']) . "' "
                    . "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' "
                    . "data-type='" . intval($row['ticket_type_id']) . "' "
                    . "data-equipment='" . intval($row['equipment_id']) . "' "
                    . "data-interval='" . intval($row['recurrence_interval']) . "' "
                    . "data-unit='" . htmlspecialchars((string)$row['recurrence_unit'], ENT_QUOTES) . "' "
                    . "data-next='" . htmlspecialchars((string)$row['next_occurrence_date'], ENT_QUOTES) . "' "
                    . "data-lead='" . intval($row['lead_time_days']) . "' "
                    . "data-priority='" . htmlspecialchars((string)$row['default_priority'], ENT_QUOTES) . "' "
                    . "data-active='" . intval($row['active']) . "'";
                echo "<tr><td><div class='action-btns'>";
                if ($can_edit) { echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $attrs title='تعديل'><i class='fas fa-edit'></i></a>"; }
                echo "</div></td>";
                echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                echo "<td>" . htmlspecialchars(tkt_label($types, intval($row['ticket_type_id']))) . "</td>";
                echo "<td>كل " . intval($row['recurrence_interval']) . ' ' . htmlspecialchars(tkt_label($units, $row['recurrence_unit'])) . "</td>";
                echo "<td>" . htmlspecialchars((string)$row['next_occurrence_date']) . "</td>";
                echo "<td>" . intval($row['lead_time_days']) . "</td>";
                echo "<td>" . htmlspecialchars(tkt_label($priorities, $row['default_priority'])) . "</td>";
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
            document.getElementById('tktForm').reset(); $('#r_id').val('');
            $('#tktForm').toggleClass('allforms-visible'); }); }
        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#r_id').val($t.data('id')); $('#r_name').val($t.data('name'));
            $('#r_type').val(String($t.data('type')));
            $('#r_equipment').val(String($t.data('equipment')) === '0' ? '' : String($t.data('equipment')));
            $('#r_interval').val($t.data('interval')); $('#r_unit').val($t.data('unit'));
            $('#r_next').val($t.data('next')); $('#r_lead').val($t.data('lead'));
            $('#r_priority').val($t.data('priority'));
            $('#r_active').prop('checked', String($t.data('active')) === '1');
            $('#tktForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#tktForm').offset().top }, 400);
        });
    });
    window.tktToggleForm = function () {
        var f = $('#tktForm');
        if (f.hasClass('allforms-visible')) { f.removeClass('allforms-visible').slideUp(); }
        else { document.getElementById('tktForm').reset(); $('#r_id').val(''); f.addClass('allforms-visible').slideDown(); }
    };
})();
</script>
</body>
</html>
