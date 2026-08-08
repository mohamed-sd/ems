<?php
/**
 * Tickets/ticket_categories_config.php — إعداد التصنيف الفنّي للبلاغات.
 *
 * التصنيفات الافتراضية (محرك · هيدروليك · كهرباء · ...) عامةٌ لكل الشركات
 * وتُعرض للقراءة فقط؛ وما تضيفه الشركة قابلٌ للتحرير والتعطيل — بلا حذف.
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

$perms = tkt_page_perms($conn, 'Tickets/ticket_categories_config.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+التصنيف+الفني+❌");
    exit();
}

// ── حفظ (إضافة/تعديل — صفوف الشركة حصرًا؛ الصفوف العامة قراءة) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: ticket_categories_config.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: ticket_categories_config.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0)          { header("Location: ticket_categories_config.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $name       = trim($_POST['name'] ?? '');
    $applies_to = trim($_POST['applies_to'] ?? '');
    $active     = isset($_POST['active']) ? 1 : 0;

    if ($name === '') {
        header("Location: ticket_categories_config.php?msg=بيانات+غير+مكتملة+❌"); exit();
    }
    $applies_val = ($applies_to === '') ? null : mb_substr($applies_to, 0, 40);

    try {
        if ($is_editing) {
            // الصفوف العامة (company_id NULL) محمية: تحقق خادمي قبل البوابة (وهي ترفضها أيضًا)
            $row = tkt_gate(false)->selectOne('ticket_categories', array(
                'columns' => array('id', 'company_id'), 'where' => array('id' => $id)));
            if (!$row || $row['company_id'] === null) {
                header("Location: ticket_categories_config.php?msg=التصنيفات+العامة+للقراءة+فقط+❌"); exit();
            }
            tkt_gate(false)->update('ticket_categories',
                array('name' => $name, 'applies_to' => $applies_val, 'active' => $active),
                array('id' => $id));
            header("Location: ticket_categories_config.php?msg=تم+تعديل+التصنيف+بنجاح+✅"); exit();
        } else {
            $code = tkt_gen_code('ticket_categories');
            tkt_gate(false)->insert('ticket_categories', array(
                'code' => $code, 'name' => $name, 'applies_to' => $applies_val, 'active' => $active,
            ));
            header("Location: ticket_categories_config.php?msg=تمت+إضافة+التصنيف+بنجاح+✅"); exit();
        }
    } catch (\App\Core\TenantGateException $e) {
        error_log('ticket_categories save refused: ' . $e->getMessage());
        header("Location: ticket_categories_config.php?msg=حدث+خطأ+أثناء+الحفظ+❌"); exit();
    }
}

$page_title = 'إيكوبيشن | تصنيفات الأعطال والبلاغات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main tkt-categories-main ems-unified-page-shell">
    <?php
    $header_title = 'تصنيفات الأعطال والبلاغات';
    $header_icon  = 'fa fa-tags';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة تصنيف');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php tkt_msg_banner(); ?>

    <form id="tktForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل تصنيف فنّي</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="c_id" value="">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>اسم التصنيف <span class="required">*</span></label>
                        <input type="text" name="name" id="c_name" required>
                    </div>
                    <div class="form-group">
                        <label>ينطبق على (اختياري)</label>
                        <input type="text" name="applies_to" id="c_applies" maxlength="40" placeholder="مثال: حفّارات · شاحنات">
                    </div>
                    <div class="form-group">
                        <label>مفعّل؟</label>
                        <label class="switch-inline"><input type="checkbox" name="active" id="c_active" value="1" checked> نعم، مفعّل</label>
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
                    <th>الإجراءات</th><th>الكود</th><th>الاسم</th><th>ينطبق على</th><th>النطاق</th><th>الحالة</th>
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
                    $cat_rows = tkt_gate($is_super_admin)->select('ticket_categories', array(
                        'columns' => array('id', 'company_id', 'code', 'name', 'applies_to', 'active'),
                        'orderBy' => 'id ASC',
                    ));
                    foreach ($cat_rows as $row) {
                        $is_own = ($row['company_id'] !== null);
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' " .
                            "data-applies='" . htmlspecialchars((string)$row['applies_to'], ENT_QUOTES) . "' " .
                            "data-active='" . intval($row['active']) . "'";
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit && $is_own) {
                            echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)$row['code']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['applies_to'] !== null && $row['applies_to'] !== '' ? (string)$row['applies_to'] : '—') . "</td>";
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
                $('#c_id').val('');
                $('#tktForm').toggleClass('allforms-visible');
            });
        }

        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#c_id').val($t.data('id'));
            $('#c_name').val($t.data('name'));
            $('#c_applies').val($t.data('applies'));
            $('#c_active').prop('checked', String($t.data('active')) === '1');
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
            $('#c_id').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
