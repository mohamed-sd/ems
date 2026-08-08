<?php
/**
 * Tickets/ticket_sla_config.php — إعداد سياسات الاستحقاق.
 *
 * قاعدة المطابقة: الأكثرُ تحديدًا يفوز (النوع ثم الأولوية ثم الوزن؛ والحقل
 * الفارغ يعني «ينطبق على الكل»)، والساعاتُ تقويميّة — والتطبيقُ الفعليّ في
 * tkt_match_sla_policy.
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
if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', ''); exit();
}
$perms = tkt_page_perms($conn, 'Tickets/ticket_sla_config.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض سياسات الاستحقاق ❌', 'GOV-PERM-403', ''); exit(); }

$priorities = tkt_priorities();
$impacts    = tkt_impacts();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id = intval($_POST['id'] ?? 0);
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('ticket_sla_config.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('ticket_sla_config.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0) { ems_gov_flash_redirect('ticket_sla_config.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $name = trim($_POST['name'] ?? '');
    $type_id  = !empty($_POST['ticket_type_id']) ? intval($_POST['ticket_type_id']) : null;
    $priority = trim($_POST['priority'] ?? '');
    $impact   = trim($_POST['business_impact'] ?? '');
    $resp = (float)($_POST['response_hours'] ?? 0);
    $reso = (float)($_POST['resolution_hours'] ?? 0);
    $remind = ($_POST['remind_before_hours'] ?? '') === '' ? null : (float)$_POST['remind_before_hours'];
    $active = isset($_POST['active']) ? 1 : 0;

    if ($priority !== '' && !array_key_exists($priority, $priorities)) { $priority = ''; }
    if ($impact !== '' && !array_key_exists($impact, $impacts)) { $impact = ''; }
    if ($name === '' || $resp <= 0 || $reso <= 0) {
        ems_gov_flash_redirect('ticket_sla_config.php', 'الاسم وساعات الاستجابة والإنجاز إلزامية ❌', 'GOV-FAIL-409', ''); exit();
    }
    $data = array(
        'name' => $name, 'ticket_type_id' => $type_id,
        'priority' => ($priority === '') ? null : $priority,
        'business_impact' => ($impact === '') ? null : $impact,
        'response_hours' => $resp, 'resolution_hours' => $reso,
        'remind_before_hours' => $remind, 'active' => $active,
    );
    try {
        if ($is_editing) {
            tkt_gate(false)->update('ticket_sla_policies', $data, array('id' => $id));
            ems_gov_flash_redirect('ticket_sla_config.php', 'تم تعديل السياسة بنجاح ✅', 'GOV-OK-200', ''); exit();
        }
        tkt_gate(false)->insert('ticket_sla_policies', $data);
        ems_gov_flash_redirect('ticket_sla_config.php', 'تمت إضافة السياسة بنجاح ✅', 'GOV-OK-200', ''); exit();
    } catch (\App\Core\TenantGateException $e) {
        error_log('sla policy save refused: ' . $e->getMessage());
        ems_gov_flash_redirect('ticket_sla_config.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
}

$page_title = 'إيكوبيشن | أزمنة الاستجابة والإنجاز';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main tkt-sla-main ems-unified-page-shell">
    <?php
    $header_title = 'أزمنة الاستجابة والإنجاز';
    $header_icon  = 'fa fa-stopwatch';
    $header_actions = array();
    if ($can_add) { $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة سياسة'); }
    $header_back = array('href' => 'tickets_list.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>
    <?php tkt_msg_banner(); ?>

    <div class="success-message is-success" style="background:#f7f3e6;color:#6b5a1e;">
        <i class="fas fa-circle-info"></i>
        قاعدة المطابقة: <strong>الأكثر تحديدًا يفوز</strong> (نوع ← أولوية ← وزن)؛ والحقل الفارغ يعني «ينطبق على الكل». الساعات <strong>تقويميّة</strong> (24/7).
    </div>

    <form id="tktForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل سياسة</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="s_id" value="">
            <div class="form-section"><div class="form-grid">
                <div class="form-group"><label>اسم السياسة <span class="required">*</span></label>
                    <input type="text" name="name" id="s_name" required></div>
                <div class="form-group"><label>النوع (فارغ = الكل)</label>
                    <select name="ticket_type_id" id="s_type"><?php echo tkt_type_options(); ?></select></div>
                <div class="form-group"><label>الأولوية (فارغ = الكل)</label>
                    <select name="priority" id="s_priority"><option value="">— الكل —</option>
                        <?php foreach ($priorities as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>الوزن (فارغ = الكل)</label>
                    <select name="business_impact" id="s_impact"><option value="">— الكل —</option>
                        <?php foreach ($impacts as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo htmlspecialchars($v); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>ساعات الاستجابة <span class="required">*</span></label>
                    <input type="number" step="0.5" min="0.5" name="response_hours" id="s_resp" required></div>
                <div class="form-group"><label>ساعات الإنجاز <span class="required">*</span></label>
                    <input type="number" step="0.5" min="0.5" name="resolution_hours" id="s_reso" required></div>
                <div class="form-group"><label>التذكير قبل (ساعات)</label>
                    <input type="number" step="0.5" min="0" name="remind_before_hours" id="s_remind"></div>
                <div class="form-group"><label>مفعّلة؟</label>
                    <label class="switch-inline"><input type="checkbox" name="active" id="s_active" value="1" checked> نعم</label></div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="tktToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body"><div class="table-container">
        <table id="tktTable" class="display nowrap alltables no-datatable" style="width:100%;">
            <thead><tr><th>الإجراءات</th><th>الاسم</th><th>النوع</th><th>الأولوية</th><th>الوزن</th><th>استجابة (س)</th><th>إنجاز (س)</th><th>تذكير قبل</th><th>الحالة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              </tr></thead>
            <tbody>
            <?php
            $types = array();
            foreach (tkt_gate($is_super_admin)->select('ticket_types', array('columns' => array('id', 'name'))) as $t) { $types[intval($t['id'])] = $t['name']; }
            $rows = tkt_gate($is_super_admin)->select('ticket_sla_policies', array('orderBy' => 'id ASC'));
            foreach ($rows as $row) {
                $attrs = "data-id='" . intval($row['id']) . "' "
                    . "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' "
                    . "data-type='" . intval($row['ticket_type_id']) . "' "
                    . "data-priority='" . htmlspecialchars((string)$row['priority'], ENT_QUOTES) . "' "
                    . "data-impact='" . htmlspecialchars((string)$row['business_impact'], ENT_QUOTES) . "' "
                    . "data-resp='" . htmlspecialchars((string)$row['response_hours'], ENT_QUOTES) . "' "
                    . "data-reso='" . htmlspecialchars((string)$row['resolution_hours'], ENT_QUOTES) . "' "
                    . "data-remind='" . htmlspecialchars((string)$row['remind_before_hours'], ENT_QUOTES) . "' "
                    . "data-active='" . intval($row['active']) . "'";
                echo "<tr><td><div class='action-btns'>";
                if ($can_edit) { echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $attrs title='تعديل'><i class='fas fa-edit'></i></a>"; }
                echo "</div></td>";
                echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['ticket_type_id'] ? tkt_label($types, intval($row['ticket_type_id'])) : 'الكل') . "</td>";
                echo "<td>" . htmlspecialchars($row['priority'] ? tkt_label($priorities, $row['priority']) : 'الكل') . "</td>";
                echo "<td>" . htmlspecialchars($row['business_impact'] ? tkt_label($impacts, $row['business_impact']) : 'الكل') . "</td>";
                echo "<td>" . htmlspecialchars((string)$row['response_hours']) . "</td>";
                echo "<td>" . htmlspecialchars((string)$row['resolution_hours']) . "</td>";
                echo "<td>" . htmlspecialchars($row['remind_before_hours'] !== null ? (string)$row['remind_before_hours'] : '—') . "</td>";
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
            document.getElementById('tktForm').reset(); $('#s_id').val('');
            $('#tktForm').toggleClass('allforms-visible'); }); }
        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#s_id').val($t.data('id')); $('#s_name').val($t.data('name'));
            $('#s_type').val(String($t.data('type')) === '0' ? '' : String($t.data('type')));
            $('#s_priority').val($t.data('priority')); $('#s_impact').val($t.data('impact'));
            $('#s_resp').val($t.data('resp')); $('#s_reso').val($t.data('reso')); $('#s_remind').val($t.data('remind'));
            $('#s_active').prop('checked', String($t.data('active')) === '1');
            $('#tktForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#tktForm').offset().top }, 400);
        });
    });
    window.tktToggleForm = function () {
        var f = $('#tktForm');
        if (f.hasClass('allforms-visible')) { f.removeClass('allforms-visible').slideUp(); }
        else { document.getElementById('tktForm').reset(); $('#s_id').val(''); f.addClass('allforms-visible').slideDown(); }
    };
})();
</script>
</body>
</html>
