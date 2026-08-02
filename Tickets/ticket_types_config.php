<?php
/**
 * Tickets/ticket_types_config.php — إعداد أنواع البلاغات ومسار التوجيه.
 *
 * كل نوعٍ يحدّد الإدارة المالكة التي يُوجَّه إليها البلاغ، وجدولَ التنفيذ
 * المرتبط به (من قائمةٍ بيضاء). الأنواع الافتراضية عامةٌ لكل الشركات وتُعرض
 * للقراءة فقط؛ وما تضيفه الشركة قابلٌ للتحرير والتعطيل — بلا حذف.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/tkt_helpers.php';

$ctx             = tkt_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$perms = tkt_page_perms($conn, 'Tickets/ticket_types_config.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+أنواع+البلاغات+❌");
    exit();
}

$natures    = tkt_natures();
$ref_tables = tkt_ref_tables();
$owner_ids  = tkt_owner_role_ids();
$roles_map  = tkt_roles_map();

// ── حفظ (إضافة/تعديل — صفوف الشركة حصرًا؛ الصفوف العامة قراءة) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: ticket_types_config.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: ticket_types_config.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0)          { header("Location: ticket_types_config.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $name           = trim($_POST['name'] ?? '');
    $owner_role_id  = intval($_POST['owner_role_id'] ?? 0);
    $default_nature = trim($_POST['default_nature'] ?? 'request');
    $ref_table      = trim($_POST['ref_table'] ?? '');
    $active         = isset($_POST['active']) ? 1 : 0;

    if ($name === '' || !in_array($owner_role_id, $owner_ids, true) || !array_key_exists($default_nature, $natures)
        || ($ref_table !== '' && !array_key_exists($ref_table, $ref_tables))) {
        header("Location: ticket_types_config.php?msg=بيانات+غير+مكتملة+❌"); exit();
    }
    $ref_table_val = ($ref_table === '') ? null : $ref_table;

    try {
        if ($is_editing) {
            // الصفوف العامة (company_id NULL) محمية: تحقق خادمي قبل البوابة (وهي ترفضها أيضًا)
            $row = tkt_gate(false)->selectOne('ticket_types', array(
                'columns' => array('id', 'company_id'), 'where' => array('id' => $id)));
            if (!$row || $row['company_id'] === null) {
                header("Location: ticket_types_config.php?msg=الأنواع+العامة+للقراءة+فقط+❌"); exit();
            }
            tkt_gate(false)->update('ticket_types',
                array('name' => $name, 'owner_role_id' => $owner_role_id,
                      'default_nature' => $default_nature, 'ref_table' => $ref_table_val, 'active' => $active),
                array('id' => $id));
            header("Location: ticket_types_config.php?msg=تم+تعديل+النوع+بنجاح+✅"); exit();
        } else {
            $code = tkt_gen_code('ticket_types');
            tkt_gate(false)->insert('ticket_types', array(
                'code' => $code, 'name' => $name, 'owner_role_id' => $owner_role_id,
                'default_nature' => $default_nature, 'ref_table' => $ref_table_val, 'active' => $active,
            ));
            header("Location: ticket_types_config.php?msg=تمت+إضافة+النوع+بنجاح+✅"); exit();
        }
    } catch (\App\Core\TenantGateException $e) {
        error_log('ticket_types save refused: ' . $e->getMessage());
        header("Location: ticket_types_config.php?msg=حدث+خطأ+أثناء+الحفظ+❌"); exit();
    }
}

$page_title = 'إيكوبيشن | إعداد أنواع البلاغات';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main tkt-types-main ems-unified-page-shell">
    <?php
    $header_title = 'إعداد أنواع البلاغات';
    $header_icon  = 'fa fa-route';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة نوع');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php tkt_msg_banner(); ?>

    <form id="tktForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل نوع بلاغ</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="t_id" value="">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>اسم النوع <span class="required">*</span></label>
                        <input type="text" name="name" id="t_name" required>
                    </div>
                    <div class="form-group">
                        <label>الإدارة المالكة (التوجيه) <span class="required">*</span></label>
                        <select name="owner_role_id" id="t_owner" required>
                            <?php foreach ($owner_ids as $rid): ?>
                                <option value="<?php echo intval($rid); ?>"><?php echo htmlspecialchars(tkt_label($roles_map, $rid)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الطبيعة الافتراضية <span class="required">*</span></label>
                        <select name="default_nature" id="t_nature" required>
                            <?php foreach ($natures as $k => $v): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>نموذج التنفيذ المرتبط</label>
                        <select name="ref_table" id="t_ref">
                            <option value="">— بلا نموذج —</option>
                            <?php foreach ($ref_tables as $k => $v): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>مفعّل؟</label>
                        <label class="switch-inline"><input type="checkbox" name="active" id="t_active" value="1" checked> نعم، مفعّل</label>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="tktToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="tktTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>الاسم</th><th>الإدارة المالكة</th><th>نموذج التنفيذ</th><th>الطبيعة</th><th>النطاق</th><th>الحالة</th>
                </tr></thead>
                <tbody>
                    <?php
                    $type_rows = tkt_gate($is_super_admin)->select('ticket_types', array(
                        'columns' => array('id', 'company_id', 'code', 'name', 'owner_role_id', 'default_nature', 'ref_table', 'active'),
                        'orderBy' => 'id ASC',
                    ));
                    foreach ($type_rows as $row) {
                        $is_own = ($row['company_id'] !== null); // صف شركةٍ قابل للتحرير؛ العام قراءة
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' " .
                            "data-owner='" . intval($row['owner_role_id']) . "' " .
                            "data-nature='" . htmlspecialchars((string)$row['default_nature'], ENT_QUOTES) . "' " .
                            "data-ref='" . htmlspecialchars((string)$row['ref_table'], ENT_QUOTES) . "' " .
                            "data-active='" . intval($row['active']) . "'";
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit && $is_own) {
                            echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)$row['code']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars(tkt_label($roles_map, intval($row['owner_role_id']))) . "</td>";
                        echo "<td>" . htmlspecialchars($row['ref_table'] !== null && $row['ref_table'] !== '' ? tkt_label($ref_tables, $row['ref_table']) : '—') . "</td>";
                        echo "<td>" . htmlspecialchars(tkt_label($natures, $row['default_nature'])) . "</td>";
                        echo "<td>" . tkt_scope_badge($row['company_id']) . "</td>";
                        echo "<td>" . tkt_active_badge($row['active']) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script>
(function () {
    $(document).ready(function () {
        $('#tktTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });

        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                document.getElementById('tktForm').reset();
                $('#t_id').val('');
                $('#tktForm').toggleClass('allforms-visible');
            });
        }

        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#t_id').val($t.data('id'));
            $('#t_name').val($t.data('name'));
            $('#t_owner').val(String($t.data('owner')));
            $('#t_nature').val($t.data('nature'));
            $('#t_ref').val($t.data('ref'));
            $('#t_active').prop('checked', String($t.data('active')) === '1');
            $('#tktForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#tktForm').offset().top }, 400);
        });
    });

    window.tktToggleForm = function () {
        var form = $('#tktForm');
        if (form.hasClass('allforms-visible')) {
            form.removeClass('allforms-visible').slideUp();
        } else {
            document.getElementById('tktForm').reset();
            $('#t_id').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
