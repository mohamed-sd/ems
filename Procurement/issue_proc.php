<?php
/**
 * Procurement/issue_proc.php — الصرف وتحميل التكلفة + سلسلة عهدة الصرف
 *   (proc_issue + proc_issue_line + proc_stock_move + proc_custody) — §15.8 / §15.9 / §11.
 *
 * عند الحفظ لكل سطر: (1) حركة مخزون صرف في proc_stock_move، (2) سجل عهدة صرف في proc_custody
 * يحمل أبعاد التكلفة (معدة/مشروع/أمر صيانة). قاعدة §15.8: لا صرف بلا مستلِم وبُعد تحميلٍ واحدٍ على الأقل.
 * قراءة مراجع المعدات/المشاريع/أوامر الصيانة قراءةً فقط — لا كتابة على أي جدول قائم.
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

$perms = proc_page_perms($conn, 'Procurement/issue_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+الصرف+❌");
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$states     = proc_issue_states();
$maint_types = array('وقائية', 'تصحيحية', 'رأسمالية');

// خيارات أوامر الصيانة (قراءة فقط من mnt_order)
$mo_scope = $is_super_admin ? '1=1' : ('company_id = ' . intval($company_id));
$mnt_orders_sql = "SELECT id, CONCAT(COALESCE(NULLIF(code,''),CONCAT('#',id))) AS label
                   FROM mnt_order WHERE $mo_scope AND COALESCE(is_deleted,0)=0 ORDER BY id DESC";

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['holder_name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: issue_proc.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: issue_proc.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0)         { header("Location: issue_proc.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $warehouse_id = ($_POST['warehouse_id'] ?? '') !== '' ? intval($_POST['warehouse_id']) : null;
    $holder_name = trim($_POST['holder_name'] ?? '');
    $issue_date = ($_POST['issue_date'] ?? '') !== '' ? trim($_POST['issue_date']) : null;
    $equipment_id = ($_POST['equipment_id'] ?? '') !== '' ? intval($_POST['equipment_id']) : null;
    $project_id   = ($_POST['project_id'] ?? '') !== '' ? intval($_POST['project_id']) : null;
    $maintenance_order_id = ($_POST['maintenance_order_id'] ?? '') !== '' ? intval($_POST['maintenance_order_id']) : null;
    $maint_type = trim($_POST['maint_type'] ?? '');
    $contract_id = ($_POST['contract_id'] ?? '') !== '' ? intval($_POST['contract_id']) : null;
    $supplier_id = ($_POST['supplier_id'] ?? '') !== '' ? intval($_POST['supplier_id']) : null;
    $state = trim($_POST['state'] ?? 'مسودة');
    $notes = trim($_POST['notes'] ?? '');

    // قاعدة §15.8: لا صرف بلا مستلِم وبُعد تحميلٍ واحدٍ على الأقل
    if ($holder_name === '') { header("Location: issue_proc.php?msg=المستلِم+إلزامي+❌"); exit(); }
    if ($equipment_id === null && $project_id === null && $maintenance_order_id === null) {
        header("Location: issue_proc.php?msg=لا+صرف+بلا+بُعد+تحميل+(معدة/مشروع/أمر)+❌"); exit();
    }
    if ($maint_type !== '' && !in_array($maint_type, $maint_types, true)) { $maint_type = ''; }
    if (!in_array($state, $states, true)) { $state = 'مسودة'; }

    // احسب الإجمالي
    $item_ids = $_POST['line_item_id'] ?? array();
    $item_names = $_POST['line_item_name'] ?? array();
    $qtys = $_POST['line_qty'] ?? array();
    $costs = $_POST['line_cost'] ?? array();
    $total = 0.0;
    for ($i = 0; $i < count($item_names); $i++) {
        if (trim($item_names[$i] ?? '') === '') { continue; }
        $total += ((float)($qtys[$i] ?? 0)) * ((float)($costs[$i] ?? 0));
    }

    mysqli_begin_transaction($conn);
    try {
        if ($is_editing) {
            $sql = "UPDATE proc_issue SET warehouse_id=?, holder_name=?, issue_date=?, equipment_id=?, project_id=?,
                    maintenance_order_id=?, maint_type=?, contract_id=?, supplier_id=?, total_cost=?, state=?, notes=?
                    WHERE id=? AND company_id=? AND COALESCE(is_deleted,0)=0";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'issiiisiidssii', $warehouse_id, $holder_name, $issue_date, $equipment_id, $project_id,
                $maintenance_order_id, $maint_type, $contract_id, $supplier_id, $total, $state, $notes, $id, $company_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            $issue_id = $id;
            // نظّف السطور والحركات والعهدة السابقة لهذا الصرف (مقيّد بالشركة)
            foreach (array('proc_issue_line' => 'issue_id', 'proc_custody' => 'issue_id') as $tbl => $col) {
                $d = mysqli_prepare($conn, "DELETE FROM $tbl WHERE $col=? AND company_id=?");
                mysqli_stmt_bind_param($d, 'ii', $issue_id, $company_id);
                mysqli_stmt_execute($d); mysqli_stmt_close($d);
            }
            $dm = mysqli_prepare($conn, "DELETE FROM proc_stock_move WHERE ref_type='proc_issue' AND ref_id=? AND company_id=?");
            mysqli_stmt_bind_param($dm, 'ii', $issue_id, $company_id);
            mysqli_stmt_execute($dm); mysqli_stmt_close($dm);
        } else {
            $code = proc_gen_code($conn, 'proc_issue', 'PRC-ISS', $company_id);
            $sql = "INSERT INTO proc_issue (company_id, code, warehouse_id, holder_name, issue_date, equipment_id, project_id,
                    maintenance_order_id, maint_type, contract_id, supplier_id, total_cost, state, notes, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'isissiiisiidssi', $company_id, $code, $warehouse_id, $holder_name, $issue_date,
                $equipment_id, $project_id, $maintenance_order_id, $maint_type, $contract_id, $supplier_id, $total, $state,
                $notes, $current_user_id);
            mysqli_stmt_execute($stmt);
            $issue_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
        }

        // أدرج السطور + حركة مخزون صرف + عهدة صرف لكل سطر
        $ln = mysqli_prepare($conn, "INSERT INTO proc_issue_line (company_id, issue_id, item_id, item_name, qty, unit_cost, subtotal)
                                     VALUES (?,?,?,?,?,?,?)");
        $mv = mysqli_prepare($conn, "INSERT INTO proc_stock_move (company_id, item_id, warehouse_id, move_type, qty, ref_type, ref_id, note, created_by)
                                     VALUES (?,?,?,'صرف',?,'proc_issue',?,?,?)");
        $cu = mysqli_prepare($conn, "INSERT INTO proc_custody (company_id, issue_id, issue_line_id, item_id, item_name, holder_name,
                                     transfer_date, equipment_id, project_id, maintenance_order_id, qty_issued, qty_returned, qty_consumed, state, created_by)
                                     VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?,'مصروفة',?)");
        for ($i = 0; $i < count($item_names); $i++) {
            $iname = trim($item_names[$i] ?? '');
            if ($iname === '') { continue; }
            $iid = (isset($item_ids[$i]) && $item_ids[$i] !== '') ? intval($item_ids[$i]) : null;
            $qty = (float)($qtys[$i] ?? 1);
            $cost = (float)($costs[$i] ?? 0);
            $sub = $qty * $cost;

            mysqli_stmt_bind_param($ln, 'iissddd', $company_id, $issue_id, $iid, $iname, $qty, $cost, $sub);
            mysqli_stmt_execute($ln);
            $line_id = mysqli_insert_id($conn);

            // حركة مخزون صرف (فقط لو ارتبط السطر بصنف كتالوج ومخزن)
            if ($iid !== null) {
                $mv_note = 'صرف ' . $iname;
                // ترتيب الربط: company_id, item_id, warehouse_id, qty, ref_id, note, created_by
                mysqli_stmt_bind_param($mv, 'iiidisi', $company_id, $iid, $warehouse_id, $qty, $issue_id, $mv_note, $current_user_id);
                mysqli_stmt_execute($mv);
            }

            // عهدة صرف تحمل الأبعاد
            $qc = $qty; // المستهلك مبدئياً = المصروف (يُعدّل عند الإرجاع)
            mysqli_stmt_bind_param($cu, 'iiiisssiiiddi', $company_id, $issue_id, $line_id, $iid, $iname, $holder_name,
                $issue_date, $equipment_id, $project_id, $maintenance_order_id, $qty, $qc, $current_user_id);
            mysqli_stmt_execute($cu);
        }
        mysqli_stmt_close($ln);
        mysqli_stmt_close($mv);
        mysqli_stmt_close($cu);
        mysqli_commit($conn);
    } catch (\Throwable $e) {
        mysqli_rollback($conn);
        header("Location: issue_proc.php?msg=تعذّر+الحفظ+❌"); exit();
    }
    header("Location: issue_proc.php?msg=" . ($is_editing ? 'تم+تعديل+الصرف+بنجاح+✅' : 'تم+الصرف+بنجاح+✅')); exit();
}

// ── حذف ناعم (يبطل الرأس؛ الحركات/العهدة تبقى للأثر ما لم يُعاد الحفظ) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: issue_proc.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $delete_id = intval($_GET['delete_id']);
    mysqli_begin_transaction($conn);
    try {
        $sql = "UPDATE proc_issue SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $delete_id, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        // اعكس أثر المخزون والعهدة لهذا الصرف
        $dm = mysqli_prepare($conn, "DELETE FROM proc_stock_move WHERE ref_type='proc_issue' AND ref_id=? AND company_id=?");
        mysqli_stmt_bind_param($dm, 'ii', $delete_id, $company_id);
        mysqli_stmt_execute($dm); mysqli_stmt_close($dm);
        $dc = mysqli_prepare($conn, "DELETE FROM proc_custody WHERE issue_id=? AND company_id=?");
        mysqli_stmt_bind_param($dc, 'ii', $delete_id, $company_id);
        mysqli_stmt_execute($dc); mysqli_stmt_close($dc);
        mysqli_commit($conn);
    } catch (\Throwable $e) { mysqli_rollback($conn); }
    header("Location: issue_proc.php?msg=تم+حذف+الصرف+بنجاح+✅"); exit();
}

// ── تحميل للتعديل ──
$edit = null; $edit_lines = array();
if (isset($_GET['edit_id']) && $can_edit) {
    $eid = intval($_GET['edit_id']);
    $q = mysqli_prepare($conn, "SELECT * FROM proc_issue WHERE id=? AND " . proc_scope('company_id', $is_super_admin, $company_id) . " AND COALESCE(is_deleted,0)=0 LIMIT 1");
    mysqli_stmt_bind_param($q, 'i', $eid);
    mysqli_stmt_execute($q);
    $r = mysqli_stmt_get_result($q);
    $edit = $r ? mysqli_fetch_assoc($r) : null;
    mysqli_stmt_close($q);
    if ($edit) {
        $lq = mysqli_prepare($conn, "SELECT * FROM proc_issue_line WHERE issue_id=? ORDER BY id ASC");
        mysqli_stmt_bind_param($lq, 'i', $eid);
        mysqli_stmt_execute($lq);
        $lr = mysqli_stmt_get_result($lq);
        while ($lr && ($lrow = mysqli_fetch_assoc($lr))) { $edit_lines[] = $lrow; }
        mysqli_stmt_close($lq);
    }
}

$page_title = 'إيكوبيشن | الصرف والعهدة';
include '../inheader.php';
include '../insidebar.php';

/** صف سطر صرف. */
function proc_iss_line_row($conn, $is_super_admin, $company_id, $line = null)
{
    $iid = $line ? intval($line['item_id']) : 0;
    $iname = $line ? htmlspecialchars((string)$line['item_name'], ENT_QUOTES) : '';
    $qty = $line ? htmlspecialchars((string)$line['qty'], ENT_QUOTES) : '1';
    $cost = $line ? htmlspecialchars((string)$line['unit_cost'], ENT_QUOTES) : '0';
    $opts = proc_items_options($conn, $is_super_admin, $company_id, $iid);
    return '<div class="proc-line form-grid" style="align-items:end;margin-bottom:8px">'
        . '<div class="form-group"><label>الصنف (كتالوج)</label><select name="line_item_id[]" class="line-item">' . $opts . '</select></div>'
        . '<div class="form-group"><label>اسم الصنف <span class="required">*</span></label><input type="text" name="line_item_name[]" class="line-name" value="' . $iname . '" required></div>'
        . '<div class="form-group"><label>الكمية</label><input type="number" step="0.01" name="line_qty[]" class="line-qty" value="' . $qty . '"></div>'
        . '<div class="form-group"><label>تكلفة الوحدة</label><input type="number" step="0.01" name="line_cost[]" class="line-cost" value="' . $cost . '"></div>'
        . '<div class="form-group"><button type="button" class="btn-cancel removeLine"><i class="fas fa-times"></i></button></div>'
        . '</div>';
}
?>

<div class="main proc-issue-main ems-unified-page-shell">
    <?php
    $header_title = 'الصرف وتحميل التكلفة';
    $header_icon  = 'fa fa-hand-holding-box';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'صرف جديد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <form id="procForm" action="issue_proc.php" method="post" class="allforms<?php echo $edit ? ' allforms-visible' : ''; ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?php echo $edit ? 'تعديل صرف' : 'صرف جديد'; ?></h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" value="<?php echo $edit ? intval($edit['id']) : ''; ?>">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>المخزن المصروف منه</label>
                        <select name="warehouse_id"><?php echo proc_warehouses_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['warehouse_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label>المستلِم <span class="required">*</span></label>
                        <input type="text" name="holder_name" value="<?php echo $edit ? htmlspecialchars((string)$edit['holder_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>تاريخ الصرف</label>
                        <input type="date" name="issue_date" value="<?php echo $edit ? htmlspecialchars((string)$edit['issue_date']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>المعدة <small>(بُعد تكلفة)</small></label>
                        <select name="equipment_id"><?php echo proc_equipment_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['equipment_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label>المشروع <small>(بُعد تكلفة)</small></label>
                        <select name="project_id"><?php echo proc_project_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['project_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label>أمر الصيانة <small>(بُعد تكلفة)</small></label>
                        <select name="maintenance_order_id"><?php echo proc_options_from_query($conn, $mnt_orders_sql, $edit ? intval($edit['maintenance_order_id']) : 0, '— بلا أمر صيانة —'); ?></select>
                    </div>
                    <div class="form-group">
                        <label>نوع الصيانة</label>
                        <select name="maint_type">
                            <option value="">— بلا —</option>
                            <?php foreach ($maint_types as $mt): $sel = ($edit && $edit['maint_type'] === $mt) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($mt); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($mt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>المورد التشغيلي</label>
                        <select name="supplier_id"><?php echo proc_suppliers_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['supplier_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label>معرّف العقد (اختياري)</label>
                        <input type="number" name="contract_id" value="<?php echo $edit && $edit['contract_id'] !== null ? intval($edit['contract_id']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>الحالة</label>
                        <select name="state">
                            <?php foreach ($states as $st): $sel = ($edit && $edit['state'] === $st) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>ملاحظات</label>
                        <input type="text" name="notes" value="<?php echo $edit ? htmlspecialchars((string)$edit['notes']) : ''; ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="card-header"><h5><i class="fas fa-list"></i> الأصناف المصروفة</h5></div>
                <div id="linesBody">
                    <?php
                    if ($edit && !empty($edit_lines)) {
                        foreach ($edit_lines as $l) { echo proc_iss_line_row($conn, $is_super_admin, $company_id, $l); }
                    } else {
                        echo proc_iss_line_row($conn, $is_super_admin, $company_id, null);
                    }
                    ?>
                </div>
                <button type="button" id="addLine" class="add-btn" style="margin-top:6px"><i class="fas fa-plus"></i> إضافة سطر</button>
                <div style="margin-top:10px;font-weight:700">إجمالي التكلفة: <span id="issTotal">0.00</span></div>
                <p style="margin-top:6px;color:#666">القاعدة: لا يُصرف صنفٌ دون مستلِمٍ وبُعد تحميلٍ واحدٍ على الأقل (معدة/مشروع/أمر صيانة).</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ الصرف</button>
                <a href="issue_proc.php" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>

    <template id="lineTemplate">
        <?php echo proc_iss_line_row($conn, $is_super_admin, $company_id, null); ?>
    </template>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>المستلِم</th><th>التاريخ</th><th>المعدة</th>
                    <th>نوع الصيانة</th><th>الإجمالي</th><th>الحالة</th><th>عدد الأصناف</th>
                </tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT i.id, i.code, i.holder_name, i.issue_date, i.maint_type, i.total_cost, i.state, i.equipment_id,
                            e.code AS equip_code, e.name AS equip_name,
                            (SELECT COUNT(*) FROM proc_issue_line l WHERE l.issue_id=i.id) AS line_count
                            FROM proc_issue i LEFT JOIN equipments e ON e.id=i.equipment_id
                            WHERE " . proc_scope('i.company_id', $is_super_admin, $company_id) . " AND COALESCE(i.is_deleted,0)=0
                            ORDER BY i.id DESC";
                    $result = mysqli_query($conn, $sql);
                    if ($result) { while ($row = mysqli_fetch_assoc($result)) {
                        $equip = trim((string)($row['equip_code'] ?? '') . ' ' . (string)($row['equip_name'] ?? ''));
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='?edit_id=" . intval($row['id']) . "' class='action-btn edit' title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)($row['code'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['holder_name']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['issue_date'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars($equip) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['maint_type'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars(number_format((float)$row['total_cost'], 2)) . "</td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string)$row['state']) . "</span></td>";
                        echo "<td>" . intval($row['line_count']) . "</td>";
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
    function recalcTotal() {
        var t = 0;
        $('#linesBody .proc-line').each(function () {
            var q = parseFloat($(this).find('.line-qty').val()) || 0;
            var c = parseFloat($(this).find('.line-cost').val()) || 0;
            t += q * c;
        });
        $('#issTotal').text(t.toFixed(2));
    }
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
        if (toggleBtn) { toggleBtn.addEventListener('click', function () { $('#procForm').toggleClass('allforms-visible'); }); }
        $('#addLine').on('click', function () {
            var tpl = document.getElementById('lineTemplate');
            document.getElementById('linesBody').appendChild(document.importNode(tpl.content, true));
        });
        $(document).on('click', '.removeLine', function () {
            var rows = $('#linesBody .proc-line');
            if (rows.length > 1) { $(this).closest('.proc-line').remove(); }
            else { $(this).closest('.proc-line').find('input,select').val(''); }
            recalcTotal();
        });
        $(document).on('input', '.line-qty, .line-cost', recalcTotal);
        $(document).on('change', '.line-item', function () {
            var txt = $(this).find('option:selected').text().trim();
            var $name = $(this).closest('.proc-line').find('.line-name');
            if (txt && !$name.val()) {
                var parts = txt.split(' — ');
                $name.val(parts.length > 1 ? parts[1] : txt);
            }
        });
        recalcTotal();
    });
})();
</script>
</body>
</html>
