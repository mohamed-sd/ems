<?php
/**
 * Procurement/master_data_proc.php — بيانات مرجعية للمشتريات — §15.
 * يدير على صفحة واحدة: (أ) القيم المرجعية (proc_lookup) + (ب) المخازن (proc_warehouse).
 * نمط موحّد: ترويسة + توبار + DataTables + فورم .allforms + عزل الشركة + حذف ناعم.
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/proc_helpers.php';

$ctx             = proc_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/master_data_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+البيانات+المرجعية+❌");
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$lookup_types    = proc_lookup_types();
$warehouse_types = proc_warehouse_types();

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحفظ (إضافة/تعديل) — مميّزة بحقل entity
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entity'])) {
    $entity     = $_POST['entity'];
    $id         = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;

    if ($is_editing && !$can_edit) { header("Location: master_data_proc.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: master_data_proc.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0)          { header("Location: master_data_proc.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    // ── (أ) قيمة مرجعية ──
    if ($entity === 'lookup') {
        $type  = trim($_POST['type'] ?? '');
        $name  = trim($_POST['name'] ?? '');
        $extra = trim($_POST['extra'] ?? '');

        if (!in_array($type, $lookup_types, true) || $name === '') {
            header("Location: master_data_proc.php?msg=بيانات+غير+مكتملة+❌"); exit();
        }

        if ($is_editing) {
            $sql = "UPDATE proc_lookup SET type=?, name=?, extra=?
                    WHERE id=? AND company_id=? AND COALESCE(is_deleted,0)=0";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, 'sssii', $type, $name, $extra, $id, $company_id);
                mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            }
            header("Location: master_data_proc.php?msg=تم+تعديل+القيمة+المرجعية+بنجاح+✅"); exit();
        } else {
            $sql = "INSERT INTO proc_lookup (company_id, type, name, extra, created_by)
                    VALUES (?,?,?,?,?)";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, 'isssi', $company_id, $type, $name, $extra, $current_user_id);
                mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            }
            header("Location: master_data_proc.php?msg=تمت+إضافة+القيمة+المرجعية+بنجاح+✅"); exit();
        }
    }

    // ── (ب) مخزن ──
    if ($entity === 'warehouse') {
        $name     = trim($_POST['name'] ?? '');
        $type     = trim($_POST['type'] ?? 'مخزن');
        $location = trim($_POST['location'] ?? '');
        $notes    = trim($_POST['notes'] ?? '');

        if ($name === '' || !in_array($type, $warehouse_types, true)) {
            header("Location: master_data_proc.php?msg=بيانات+غير+مكتملة+❌"); exit();
        }

        if ($is_editing) {
            $sql = "UPDATE proc_warehouse SET name=?, type=?, location=?, notes=?
                    WHERE id=? AND company_id=? AND COALESCE(is_deleted,0)=0";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, 'ssssii', $name, $type, $location, $notes, $id, $company_id);
                mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            }
            header("Location: master_data_proc.php?msg=تم+تعديل+المخزن+بنجاح+✅"); exit();
        } else {
            $code = proc_gen_code($conn, 'proc_warehouse', 'PRC-WH', $company_id);
            $sql = "INSERT INTO proc_warehouse (company_id, code, name, type, location, notes, created_by)
                    VALUES (?,?,?,?,?,?,?)";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, 'isssssi', $company_id, $code, $name, $type, $location, $notes, $current_user_id);
                mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            }
            header("Location: master_data_proc.php?msg=تمت+إضافة+المخزن+بنجاح+✅"); exit();
        }
    }

    header("Location: master_data_proc.php?msg=كيان+غير+معروف+❌"); exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم (مقيّد بالشركة)
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_lookup_id'])) {
    if (!$can_delete) { header("Location: master_data_proc.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $delete_id = intval($_GET['delete_lookup_id']);
    $sql = "UPDATE proc_lookup SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $delete_id, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: master_data_proc.php?msg=تم+حذف+القيمة+المرجعية+بنجاح+✅"); exit();
}

if (isset($_GET['delete_wh_id'])) {
    if (!$can_delete) { header("Location: master_data_proc.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $delete_id = intval($_GET['delete_wh_id']);
    $sql = "UPDATE proc_warehouse SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $delete_id, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: master_data_proc.php?msg=تم+حذف+المخزن+بنجاح+✅"); exit();
}

$page_title = 'إيكوبيشن | بيانات مرجعية — المشتريات';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main proc-master-main ems-unified-page-shell">
    <?php
    $header_title = 'بيانات مرجعية — المشتريات';
    $header_icon  = 'fa fa-sliders';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleLookupForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة قيمة مرجعية');
        $header_actions[] = array('id' => 'toggleWhForm', 'class' => 'add-btn', 'icon' => 'fas fa-warehouse', 'label' => 'إضافة مخزن');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <!-- ══════════════ (أ) القيم المرجعية ══════════════ -->
    <form id="lookupForm" action="" method="post" class="allforms">
        <input type="hidden" name="entity" value="lookup">
        <div class="card-header"><h5><i class="fas fa-list"></i> إضافة / تعديل قيمة مرجعية</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="lk_id" value="">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label>النوع <span class="required">*</span></label>
                    <select name="type" id="lk_type" required>
                        <option value="">— اختر —</option>
                        <?php foreach ($lookup_types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>الاسم <span class="required">*</span></label>
                    <input type="text" name="name" id="lk_name" required>
                </div>
                <div class="form-group">
                    <label>وصف / تفصيل</label>
                    <input type="text" name="extra" id="lk_extra">
                </div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="procToggleForm('lookupForm')"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="card-header"><h5><i class="fas fa-list"></i> القيم المرجعية</h5></div>
        <div class="form-grid">
            <div class="form-group">
                <label>تصفية حسب النوع</label>
                <select id="filterLookupType">
                    <option value="">كل الأنواع</option>
                    <?php foreach ($lookup_types as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="table-container">
            <table id="lookupTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>النوع</th><th>الاسم</th><th>وصف / تفصيل</th><th>مفعّل</th>
                </tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT id, type, name, extra, is_active FROM proc_lookup
                            WHERE $company_scope_sql AND COALESCE(is_deleted,0)=0
                            ORDER BY type ASC, name ASC";
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-type='" . htmlspecialchars((string)$row['type'], ENT_QUOTES) . "' " .
                            "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' " .
                            "data-extra='" . htmlspecialchars((string)($row['extra'] ?? ''), ENT_QUOTES) . "'";
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='javascript:void(0)' class='editLookup action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_lookup_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string)$row['type']) . "</span></td>";
                        echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['extra'] ?? '')) . "</td>";
                        echo "<td>" . ((int)$row['is_active'] === 1 ? "نعم" : "لا") . "</td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>

    <!-- ══════════════ (ب) المخازن ══════════════ -->
    <form id="whForm" action="" method="post" class="allforms">
        <input type="hidden" name="entity" value="warehouse">
        <div class="card-header"><h5><i class="fas fa-warehouse"></i> إضافة / تعديل مخزن</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="wh_id" value="">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label>اسم المخزن <span class="required">*</span></label>
                    <input type="text" name="name" id="wh_name" required>
                </div>
                <div class="form-group">
                    <label>النوع <span class="required">*</span></label>
                    <select name="type" id="wh_type" required>
                        <?php foreach ($warehouse_types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>الموقع</label>
                    <input type="text" name="location" id="wh_location">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>ملاحظات</label>
                    <input type="text" name="notes" id="wh_notes">
                </div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="procToggleForm('whForm')"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="card-header"><h5><i class="fas fa-warehouse"></i> المخازن</h5></div>
        <div class="table-container">
            <table id="whTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>الاسم</th><th>النوع</th><th>الموقع</th><th>ملاحظات</th>
                </tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT id, code, name, type, location, notes FROM proc_warehouse
                            WHERE $company_scope_sql AND COALESCE(is_deleted,0)=0
                            ORDER BY name ASC";
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' " .
                            "data-type='" . htmlspecialchars((string)$row['type'], ENT_QUOTES) . "' " .
                            "data-location='" . htmlspecialchars((string)($row['location'] ?? ''), ENT_QUOTES) . "' " .
                            "data-notes='" . htmlspecialchars((string)($row['notes'] ?? ''), ENT_QUOTES) . "'";
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='javascript:void(0)' class='editWh action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_wh_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)($row['code'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string)$row['type']) . "</span></td>";
                        echo "<td>" . htmlspecialchars((string)($row['location'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['notes'] ?? '')) . "</td>";
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
<script src="/ems/assets/vendor/pdfmake/pdfmake.min.js"></script>
<script src="/ems/assets/vendor/pdfmake/vfs_fonts.js"></script>
<script>
(function () {
    $(document).ready(function () {
        var lookupTable = $('#lookupTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });

        $('#whTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [
                { extend: 'copy', text: '📋 نسخ' },
                { extend: 'excel', text: '📊 Excel' },
                { extend: 'print', text: '🖨️ طباعة' }
            ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });

        // فلترة القيم المرجعية حسب النوع (العمود 1)
        $('#filterLookupType').on('change', function () {
            var v = this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '';
            lookupTable.column(1).search(v, true, false).draw();
        });

        // أزرار إظهار الفورمين
        var btnLk = document.getElementById('toggleLookupForm');
        if (btnLk) { btnLk.addEventListener('click', function () { window.procToggleForm('lookupForm', true); }); }
        var btnWh = document.getElementById('toggleWhForm');
        if (btnWh) { btnWh.addEventListener('click', function () { window.procToggleForm('whForm', true); }); }

        // تعديل قيمة مرجعية
        $(document).on('click', '.editLookup', function () {
            var $t = $(this);
            $('#lk_id').val($t.data('id'));
            $('#lk_type').val($t.data('type'));
            $('#lk_name').val($t.data('name'));
            $('#lk_extra').val($t.data('extra'));
            $('#lookupForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#lookupForm').offset().top }, 400);
        });

        // تعديل مخزن
        $(document).on('click', '.editWh', function () {
            var $t = $(this);
            $('#wh_id').val($t.data('id'));
            $('#wh_name').val($t.data('name'));
            $('#wh_type').val($t.data('type'));
            $('#wh_location').val($t.data('location'));
            $('#wh_notes').val($t.data('notes'));
            $('#whForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#whForm').offset().top }, 400);
        });
    });

    window.procToggleForm = function (formId, forceOpen) {
        var form = $('#' + formId);
        if (form.hasClass('allforms-visible') && !forceOpen) {
            form.removeClass('allforms-visible').slideUp();
        } else {
            document.getElementById(formId).reset();
            $('#' + formId + ' input[name="id"]').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
