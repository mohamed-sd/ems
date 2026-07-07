<?php
/**
 * Transport/transfer_types_config.php — إعداد الأنواع الستة للترحيل (transfer_types) — §4.
 * الأنواع الستة مزروعة مسبقاً؛ هنا تُدار تسميتها ومتحمِّلها الافتراضي وتفعيلها.
 * نمط موحّد — شاشة جديدة مستقلة، لا تلمس أي جدول قائم.
 */
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
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$perms = trs_page_perms($conn, 'Transport/transfer_types_config.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+أنواع+الترحيل+❌");
    exit();
}

$categories = trs_operational_categories();
$bearers    = trs_bearers_rule();

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: transfer_types_config.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: transfer_types_config.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0)         { header("Location: transfer_types_config.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $name = trim($_POST['name'] ?? '');
    $operational_category = trim($_POST['operational_category'] ?? '');
    $default_bearer = trim($_POST['default_bearer'] ?? 'by_rule');
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '' || !array_key_exists($operational_category, $categories) || !array_key_exists($default_bearer, $bearers)) {
        header("Location: transfer_types_config.php?msg=بيانات+غير+مكتملة+❌"); exit();
    }

    if ($is_editing) {
        $sql = "UPDATE transfer_types SET name=?, operational_category=?, default_bearer=?, active=?
                WHERE id=? AND company_id=?";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'sssiii', $name, $operational_category, $default_bearer, $active, $id, $company_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        header("Location: transfer_types_config.php?msg=تم+تعديل+النوع+بنجاح+✅"); exit();
    } else {
        // كود تقني بسيط للأنواع المُضافة يدوياً
        $code = 'custom_' . trs_gen_code($conn, 'transfer_types', 'T', $company_id);
        $code = strtolower(str_replace('-', '', $code));
        $sql = "INSERT INTO transfer_types (company_id, code, name, operational_category, default_bearer, active)
                VALUES (?,?,?,?,?,?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'issssi', $company_id, $code, $name, $operational_category, $default_bearer, $active);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        header("Location: transfer_types_config.php?msg=تمت+إضافة+النوع+بنجاح+✅"); exit();
    }
}

// ── حذف (فعلي؛ FK RESTRICT يمنع حذف نوع مستخدم في أوامر/طلبات) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: transfer_types_config.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $delete_id = intval($_GET['delete_id']);
    $sql = "DELETE FROM transfer_types WHERE id=? AND company_id=?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'ii', $delete_id, $company_id);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: transfer_types_config.php?msg=تعذّر+الحذف+—+النوع+مستخدم+في+أوامر+ترحيل+❌"); exit();
        }
        mysqli_stmt_close($stmt);
    }
    header("Location: transfer_types_config.php?msg=تم+حذف+النوع+بنجاح+✅"); exit();
}

$page_title = 'إيكوبيشن | أنواع الترحيل';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main trs-types-main ems-unified-page-shell">
    <?php
    $header_title = 'إعداد أنواع الترحيل';
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
                        <label>اسم النوع <span class="required">*</span></label>
                        <input type="text" name="name" id="t_name" required>
                    </div>
                    <div class="form-group">
                        <label>الفئة التشغيلية <span class="required">*</span></label>
                        <select name="operational_category" id="t_category" required>
                            <?php foreach ($categories as $k => $v): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>المتحمِّل الافتراضي <span class="required">*</span></label>
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
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="trsToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="trsTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>الاسم</th><th>الفئة التشغيلية</th><th>المتحمِّل الافتراضي</th><th>الحالة</th>
                </tr></thead>
                <tbody>
                    <?php
                    $scope = $is_super_admin ? '1=1' : ('company_id = ' . intval($company_id));
                    $sql = "SELECT id, code, name, operational_category, default_bearer, active
                            FROM transfer_types WHERE $scope ORDER BY id ASC";
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
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
