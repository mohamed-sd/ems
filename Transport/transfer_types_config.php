<?php
/**
 * Transport/transfer_types_config.php — إعداد الأنواع الستة للترحيل (transfer_types) — §4.
 * الأنواع الستة مزروعة مسبقاً؛ هنا تُدار تسميتها ومتحمِّلها الافتراضي وتفعيلها.
 * نمط موحّد — شاشة جديدة مستقلة، لا تلمس أي جدول قائم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/trs_helpers.php';

$ctx             = trs_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = trs_page_perms($conn, 'Transport/transfer_types_config.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض أنواع الترحيل ❌', 'GOV-PERM-403', '');
    exit();
}

$categories = trs_operational_categories();
$bearers    = trs_bearers_rule();

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('transfer_types_config.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('transfer_types_config.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('transfer_types_config.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $name = trim($_POST['name'] ?? '');
    $operational_category = trim($_POST['operational_category'] ?? '');
    $default_bearer = trim($_POST['default_bearer'] ?? 'by_rule');
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '' || !array_key_exists($operational_category, $categories) || !array_key_exists($default_bearer, $bearers)) {
        ems_gov_flash_redirect('transfer_types_config.php', 'بيانات غير مكتملة ❌', 'GOV-FAIL-409', ''); exit();
    }

    try {
        if ($is_editing) {
            trs_gate(false)->update('transfer_types',
                array('name' => $name, 'operational_category' => $operational_category, 'default_bearer' => $default_bearer, 'active' => $active),
                array('id' => $id));
            ems_gov_flash_redirect('transfer_types_config.php', 'تم تعديل النوع بنجاح ✅', 'GOV-OK-200', ''); exit();
        } else {
            // كود تقني بسيط للأنواع المُضافة يدوياً
            $code = 'custom_' . trs_gen_code($conn, 'transfer_types', 'T', $company_id);
            $code = strtolower(str_replace('-', '', $code));
            trs_gate(false)->insert('transfer_types', array(
                'code' => $code, 'name' => $name, 'operational_category' => $operational_category,
                'default_bearer' => $default_bearer, 'active' => $active,
            ));
            ems_gov_flash_redirect('transfer_types_config.php', 'تمت إضافة النوع بنجاح ✅', 'GOV-OK-200', ''); exit();
        }
    } catch (\App\Core\TenantGateException $e) {
        error_log('transfer_types save refused: ' . $e->getMessage());
        ems_gov_flash_redirect('transfer_types_config.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
}

// ── حذف — سياسة «الأرشفة لا الحذف» (K9-M2b · أول تطبيقٍ على master):
//    كان DELETE صلبًا يفشل بFK RESTRICT إن كان النوع مستعملًا؛ الأرشفة تنجح
//    دائمًا: يختفي من القوائم (فلترة البوابة) وتبقى مراجع الأوامر التاريخية
//    سليمة — فرقٌ دلاليٌّ مقصودٌ بالسياسة، موثَّق. ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('transfer_types_config.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    try {
        trs_gate(false)->softDelete('transfer_types', $delete_id);
    } catch (\App\Core\TenantGateException $e) {
        error_log('transfer_types softDelete refused: ' . $e->getMessage());
        ems_gov_flash_redirect('transfer_types_config.php', 'تعذّر الحذف ❌', 'GOV-FAIL-409', ''); exit();
    }
    ems_gov_flash_redirect('transfer_types_config.php', 'تم حذف النوع بنجاح ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | إعدادات الترحيل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main trs-types-main ems-unified-page-shell">
    <?php
    $header_title = 'إعدادات الترحيل';
    $header_icon  = 'fa fa-layer-group';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة نوع');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php trs_msg_banner(); ?>

    <form id="trsForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل نوع ترحيل</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="t_id" value="">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="t_name">اسم النوع <span class="required">*</span></label>
                        <input type="text" name="name" id="t_name" required>
                    </div>
                    <div class="form-group">
                        <label for="t_category">الفئة التشغيلية <span class="required">*</span></label>
                        <select name="operational_category" id="t_category" required>
                            <?php foreach ($categories as $k => $v): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="t_bearer">المتحمِّل الافتراضي <span class="required">*</span></label>
                        <select name="default_bearer" id="t_bearer" required>
                            <?php foreach ($bearers as $k => $v): ?>
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
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" onclick="trsToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="trsTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>الاسم</th><th>الفئة التشغيلية</th><th>المتحمِّل الافتراضي</th><th>الحالة</th>
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
                    $type_rows = trs_gate($is_super_admin)->select('transfer_types', array(
                        'columns' => array('id', 'code', 'name', 'operational_category', 'default_bearer', 'active'),
                        'orderBy' => 'id ASC',
                    ));
                    { foreach ($type_rows as $row) {
                        $cat_ar = trs_label($categories, $row['operational_category']);
                        $bearer_ar = trs_label($bearers, $row['default_bearer']);
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' " .
                            "data-category='" . htmlspecialchars((string)$row['operational_category'], ENT_QUOTES) . "' " .
                            "data-bearer='" . htmlspecialchars((string)$row['default_bearer'], ENT_QUOTES) . "' " .
                            "data-active='" . intval($row['active']) . "'";
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)$row['code']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($cat_ar) . "</td>";
                        echo "<td>" . htmlspecialchars($bearer_ar) . "</td>";
                        echo "<td>" . ((int)$row['active'] === 1 ? "<span class='action-btn' style='color:#1e7e34'>مفعّل</span>" : "<span class='action-btn' style='color:#c0392b'>معطّل</span>") . "</td>";
                        echo "</tr>";
                    } }
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
        $('#trsTable').DataTable({
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
                document.getElementById('trsForm').reset();
                $('#t_id').val('');
                $('#trsForm').toggleClass('allforms-visible');
            });
        }

        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#t_id').val($t.data('id'));
            $('#t_name').val($t.data('name'));
            $('#t_category').val($t.data('category'));
            $('#t_bearer').val($t.data('bearer'));
            $('#t_active').prop('checked', String($t.data('active')) === '1');
            $('#trsForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#trsForm').offset().top }, 400);
        });
    });

    window.trsToggleForm = function () {
        var form = $('#trsForm');
        if (form.hasClass('allforms-visible')) {
            form.removeClass('allforms-visible').slideUp();
        } else {
            document.getElementById('trsForm').reset();
            $('#t_id').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
