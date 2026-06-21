<?php
/**
 * Maintenance/master_data.php — إعدادات الصيانة / الكتالوجات الموحّدة (mnt_lookup).
 * يدير: أسباب الأعطال، أسباب التوقّف، أنواع المهام، الورش. + رابط لتصنيف الأعطال المنقول.
 * يلتزم بمعايير التوحيد (القسم 3): نفس الترويسة/التوبار/DataTables/الفورم + عزل الشركة.
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/mnt_helpers.php';

$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

// 🔐 الصلاحيات (شاشة مملوكة لدور الصيانة)
$page_permissions = check_page_permissions($conn, 'Maintenance/master_data.php');
$can_view   = $is_super_admin ? true : $page_permissions['can_view'];
$can_add    = $is_super_admin ? true : $page_permissions['can_add'];
$can_edit   = $is_super_admin ? true : $page_permissions['can_edit'];
$can_delete = $is_super_admin ? true : $page_permissions['can_delete'];

if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+إعدادات+الصيانة+❌");
    exit();
}

// أنواع الكتالوج المعتمدة (قائمة بيضاء)
$mnt_lookup_types = array('سبب عطل', 'سبب توقّف', 'نوع مهمة', 'ورشة');

// شرط عزل الشركة للعرض/التعديل
$company_scope_sql = $is_super_admin ? "1=1" : "company_id = " . intval($company_id);

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الإضافة / التعديل (prepared statements)
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id   = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;

    if ($is_editing && !$can_edit) {
        header("Location: master_data.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit();
    } elseif (!$is_editing && !$can_add) {
        header("Location: master_data.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit();
    }

    // لا إدراج/تعديل بلا شركة صالحة (عزل إجباري)
    if ($company_id <= 0) {
        header("Location: master_data.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit();
    }

    $type  = trim($_POST['type'] ?? '');
    $name  = trim($_POST['name'] ?? '');
    $extra = trim($_POST['extra'] ?? '');

    if (!in_array($type, $mnt_lookup_types, true) || $name === '') {
        header("Location: master_data.php?msg=بيانات+غير+مكتملة+❌"); exit();
    }

    if ($is_editing) {
        // التحديث مقيّد بالشركة (لا تعدّل صفّ شركة أخرى)
        $sql = "UPDATE mnt_lookup SET type = ?, name = ?, extra = ?
                 WHERE id = ? AND company_id = ? AND COALESCE(is_deleted,0)=0";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'sssii', $type, $name, $extra, $id, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header("Location: master_data.php?msg=تم+تعديل+العنصر+بنجاح+✅"); exit();
    } else {
        $sql = "INSERT INTO mnt_lookup (company_id, type, name, extra, created_by)
                VALUES (?, ?, ?, ?, ?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'isssi', $company_id, $type, $name, $extra, $current_user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header("Location: master_data.php?msg=تمت+إضافة+العنصر+بنجاح+✅"); exit();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم (مقيّد بالشركة)
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    if (!$can_delete) {
        header("Location: master_data.php?msg=لا+توجد+صلاحية+حذف+❌"); exit();
    }
    $delete_id = intval($_GET['delete_id']);
    $sql = "UPDATE mnt_lookup SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?
             WHERE id = ? AND company_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $delete_id, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: master_data.php?msg=تم+حذف+العنصر+بنجاح+✅"); exit();
}

$page_title = 'إيكوبيشن | إعدادات الصيانة';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main mnt-master-main ems-unified-page-shell">

    <?php
    $header_title   = 'إعدادات الصيانة';
    $header_icon    = 'fa fa-sliders';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة عنصر');
    }
    $header_actions[] = array('tag' => 'a', 'href' => '../Equipments/manage_failure_codes.php', 'class' => 'suppliers-header-link', 'icon' => 'fa fa-screwdriver-wrench', 'label' => 'تصنيف الأعطال');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <!-- ══ فورم إضافة / تعديل عنصر كتالوج ══ -->
    <form id="mntForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> إضافة / تعديل عنصر</h5>
        </div>
        <div class="card">
            <div class="card-body">
                <input type="hidden" name="id" id="mnt_id" value="">
                <div class="form-section">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>النوع <span class="required">*</span></label>
                            <select name="type" id="mnt_type" required>
                                <option value="">-- اختر --</option>
                                <?php foreach ($mnt_lookup_types as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>الاسم <span class="required">*</span></label>
                            <input type="text" name="name" id="mnt_name" required>
                        </div>
                        <div class="form-group">
                            <label>وصف / تفصيل</label>
                            <input type="text" name="extra" id="mnt_extra">
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                    <button type="button" class="btn-cancel" onclick="mntToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
                </div>
            </div>
        </div>
    </form>

    <!-- ══ فلتر النوع ══ -->
    <div class="card">
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>تصفية حسب النوع</label>
                    <select id="filterType">
                        <option value="">كل الأنواع</option>
                        <?php foreach ($mnt_lookup_types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table id="mntTable" class="display nowrap alltables no-datatable" style="width:100%;">
                    <thead>
                        <tr>
                            <th>الإجراءات</th>
                            <th>النوع</th>
                            <th>الاسم</th>
                            <th>وصف / تفصيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT id, type, name, extra FROM mnt_lookup
                                 WHERE $company_scope_sql AND COALESCE(is_deleted,0)=0
                                 ORDER BY type ASC, name ASC";
                        $result = mysqli_query($conn, $sql);
                        if ($result) { while ($row = mysqli_fetch_assoc($result)) {
                            $data_attrs =
                                "data-id='" . intval($row['id']) . "' " .
                                "data-type='" . htmlspecialchars((string) $row['type'], ENT_QUOTES) . "' " .
                                "data-name='" . htmlspecialchars((string) $row['name'], ENT_QUOTES) . "' " .
                                "data-extra='" . htmlspecialchars((string) ($row['extra'] ?? ''), ENT_QUOTES) . "'";

                            echo "<tr>";
                            echo "<td><div class='action-btns'>";
                            if ($can_edit) {
                                echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                            }
                            if ($can_delete) {
                                echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                            }
                            echo "</div></td>";
                            echo "<td><span class='action-btn'>" . htmlspecialchars((string) $row['type']) . "</span></td>";
                            echo "<td>" . htmlspecialchars((string) $row['name']) . "</td>";
                            echo "<td>" . htmlspecialchars((string) ($row['extra'] ?? '')) . "</td>";
                            echo "</tr>";
                        } }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- jQuery + DataTables -->
<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.html5.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/buttons.print.min.js"></script>
<script src="/ems/assets/vendor/jszip/jszip.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>

<script>
(function () {
    $(document).ready(function () {
        var table = $('#mntTable').DataTable({
            scrollX: true,
            autoWidth: false,
            stateSave: false, // فلتر خارجي ⇒ لا نحفظ الحالة (يمنع إخفاء صفوف بحالة محفوظة قديمة)
            dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });

        // فلترة حسب النوع (العمود 1)
        $('#filterType').on('change', function () {
            var v = this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '';
            table.column(1).search(v, true, false).draw();
        });

        // إظهار/إخفاء الفورم
        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                $('#mnt_id').val(''); $('#mnt_type').val(''); $('#mnt_name').val(''); $('#mnt_extra').val('');
                $('#mntForm').toggleClass('allforms-visible');
            });
        }

        // تعديل — تحميل البيانات في الفورم
        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#mnt_id').val($t.data('id'));
            $('#mnt_type').val($t.data('type'));
            $('#mnt_name').val($t.data('name'));
            $('#mnt_extra').val($t.data('extra'));
            $('#mntForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#mntForm').offset().top }, 400);
        });
    });

    window.mntToggleForm = function () {
        var form = $('#mntForm');
        if (form.hasClass('allforms-visible')) {
            form.removeClass('allforms-visible').slideUp();
        } else {
            $('#mnt_id').val(''); $('#mnt_type').val(''); $('#mnt_name').val(''); $('#mnt_extra').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
