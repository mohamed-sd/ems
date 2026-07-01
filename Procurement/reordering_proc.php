<?php
/**
 * Procurement/reordering_proc.php — قواعد إعادة الطلب (proc_orderpoint).
 * نمط موحّد: ترويسة + توبار + DataTables + فورم .allforms + عزل الشركة + حذف ناعم.
 * شاشة جديدة مستقلة تماماً — لا تلمس أي جدول قائم.
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

$perms = proc_page_perms($conn, 'Procurement/reordering_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+قواعد+إعادة+الطلب+❌");
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$modes = array('يدوي', 'تلقائي');

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: reordering_proc.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: reordering_proc.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0)         { header("Location: reordering_proc.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $item_id = intval($_POST['item_id'] ?? 0);
    $warehouse_id = ($_POST['warehouse_id'] ?? '') !== '' ? intval($_POST['warehouse_id']) : null;
    $min_qty = (float)($_POST['min_qty'] ?? 0);
    $max_qty = (float)($_POST['max_qty'] ?? 0);
    $trigger_qty = (float)($_POST['trigger_qty'] ?? 0);
    $safety_stock = (float)($_POST['safety_stock'] ?? 0);
    $mode = trim($_POST['mode'] ?? 'يدوي');

    if ($item_id <= 0 || !in_array($mode, $modes, true)) {
        header("Location: reordering_proc.php?msg=بيانات+غير+مكتملة+❌"); exit();
    }

    if ($is_editing) {
        $sql = "UPDATE proc_orderpoint SET item_id=?, warehouse_id=?, min_qty=?, max_qty=?, trigger_qty=?, safety_stock=?, mode=?
                WHERE id=? AND company_id=? AND COALESCE(is_deleted,0)=0";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'iiddddsii', $item_id, $warehouse_id, $min_qty, $max_qty, $trigger_qty,
                $safety_stock, $mode, $id, $company_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        header("Location: reordering_proc.php?msg=تم+تعديل+قاعدة+إعادة+الطلب+بنجاح+✅"); exit();
    } else {
        $sql = "INSERT INTO proc_orderpoint (company_id, item_id, warehouse_id, min_qty, max_qty, trigger_qty, safety_stock, mode, created_by)
                VALUES (?,?,?,?,?,?,?,?,?)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'iiiddddsi', $company_id, $item_id, $warehouse_id, $min_qty, $max_qty,
                $trigger_qty, $safety_stock, $mode, $current_user_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        header("Location: reordering_proc.php?msg=تمت+إضافة+قاعدة+إعادة+الطلب+بنجاح+✅"); exit();
    }
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: reordering_proc.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $delete_id = intval($_GET['delete_id']);
    $sql = "UPDATE proc_orderpoint SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $delete_id, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: reordering_proc.php?msg=تم+حذف+قاعدة+إعادة+الطلب+بنجاح+✅"); exit();
}

// ── تعبئة نموذج التعديل عبر ?edit_id (السيليكتات) ──
$edit_row = null;
if (isset($_GET['edit_id'])) {
    $edit_id = intval($_GET['edit_id']);
    $sql = "SELECT id, item_id, warehouse_id, min_qty, max_qty, trigger_qty, safety_stock, mode
            FROM proc_orderpoint WHERE id=? AND $company_scope_sql AND COALESCE(is_deleted,0)=0 LIMIT 1";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $edit_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $edit_row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }
}

$sel_item      = $edit_row ? intval($edit_row['item_id']) : 0;
$sel_warehouse = $edit_row ? intval($edit_row['warehouse_id']) : 0;

$page_title = 'إيكوبيشن | قواعد إعادة الطلب';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main proc-reordering-main ems-unified-page-shell">
    <?php
    $header_title = 'قواعد إعادة الطلب';
    $header_icon  = 'fa fa-arrows-rotate';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة قاعدة');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <!-- فورم إضافة/تعديل -->
    <form id="procForm" action="" method="post" class="allforms<?php echo $edit_row ? ' allforms-visible' : ''; ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل قاعدة إعادة طلب</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="p_id" value="<?php echo $edit_row ? intval($edit_row['id']) : ''; ?>">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>الصنف <span class="required">*</span></label>
                        <select name="item_id" id="p_item" required>
                            <?php echo proc_items_options($conn, $is_super_admin, $company_id, $sel_item); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>المخزن</label>
                        <select name="warehouse_id" id="p_warehouse">
                            <?php echo proc_warehouses_options($conn, $is_super_admin, $company_id, $sel_warehouse); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الحد الأدنى (Min)</label>
                        <input type="number" step="0.01" name="min_qty" id="p_min" value="<?php echo $edit_row ? htmlspecialchars((string)$edit_row['min_qty'], ENT_QUOTES) : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label>الحد الأقصى (Max)</label>
                        <input type="number" step="0.01" name="max_qty" id="p_max" value="<?php echo $edit_row ? htmlspecialchars((string)$edit_row['max_qty'], ENT_QUOTES) : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label>نقطة إعادة الطلب (ROP)</label>
                        <input type="number" step="0.01" name="trigger_qty" id="p_trigger" value="<?php echo $edit_row ? htmlspecialchars((string)$edit_row['trigger_qty'], ENT_QUOTES) : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label>مخزون الأمان</label>
                        <input type="number" step="0.01" name="safety_stock" id="p_safety" value="<?php echo $edit_row ? htmlspecialchars((string)$edit_row['safety_stock'], ENT_QUOTES) : '0'; ?>">
                    </div>
                    <div class="form-group">
                        <label>الوضع</label>
                        <select name="mode" id="p_mode">
                            <?php foreach ($modes as $m): ?>
                                <option value="<?php echo htmlspecialchars($m); ?>"<?php echo ($edit_row && $edit_row['mode'] === $m) ? ' selected' : ''; ?>><?php echo htmlspecialchars($m); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <p class="form-hint" style="grid-column:1/-1;color:#666;font-size:13px;margin-top:8px;">
                    <i class="fas fa-info-circle"></i>
                    نقطة إعادة الطلب ≈ (متوسط الاستهلاك اليومي × مدة التوريد) + مخزون الأمان
                </p>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-cancel" onclick="procToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الصنف</th><th>المخزن</th><th>Min</th><th>Max</th>
                    <th>ROP</th><th>مخزون الأمان</th><th>الوضع</th>
                </tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT op.id, op.min_qty, op.max_qty, op.trigger_qty, op.safety_stock, op.mode,
                                   i.name AS item_name, w.name AS warehouse_name
                            FROM proc_orderpoint op
                            LEFT JOIN proc_item i ON i.id = op.item_id
                            LEFT JOIN proc_warehouse w ON w.id = op.warehouse_id
                            WHERE " . proc_scope('op.company_id', $is_super_admin, $company_id) . " AND COALESCE(op.is_deleted,0)=0
                            ORDER BY i.name ASC";
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='?edit_id=" . intval($row['id']) . "' class='action-btn edit' title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)($row['item_name'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['warehouse_name'] ?? '—')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['min_qty']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['max_qty']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['trigger_qty']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['safety_stock']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['mode']) . "</td>";
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
        $('#procTable').DataTable({
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
                window.location.href = 'reordering.php';
            });
        }

        <?php if ($edit_row): ?>
        $('html, body').animate({ scrollTop: $('#procForm').offset().top }, 400);
        <?php endif; ?>
    });

    window.procToggleForm = function () {
        window.location.href = 'reordering.php';
    };
})();
</script>
</body>
</html>
