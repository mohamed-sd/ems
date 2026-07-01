<?php
/**
 * Procurement/orders_proc.php — أوامر الشراء (proc_order + proc_order_line) — §15.2.
 * رأس + سطور. الإجمالي = مجموع (كمية×سعر). قاعدة: لا يغادر الأمر «مسودة» بلا مرجع اعتماد مالي.
 * شاشة جديدة مستقلة — لا تلمس أي جدول قائم.
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

$perms = proc_page_perms($conn, 'Procurement/orders_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+أوامر+الشراء+❌");
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$classifications = proc_classifications();
$states       = proc_order_states();
$currencies   = proc_currencies();
$pay_times    = proc_payment_times();
$recv_types   = proc_receipt_types();

// خيارات طلبات الشراء المفتوحة (للربط)
$req_scope = proc_scope('company_id', $is_super_admin, $company_id);
$requests_sql = "SELECT id, CONCAT(COALESCE(NULLIF(code,''),CONCAT('#',id)),' — ',op_classification) AS label
                 FROM proc_request WHERE $req_scope AND COALESCE(is_deleted,0)=0 ORDER BY id DESC";

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['currency'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: orders_proc.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: orders_proc.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0)         { header("Location: orders_proc.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $supplier_id = ($_POST['supplier_id'] ?? '') !== '' ? intval($_POST['supplier_id']) : null;
    $request_id  = ($_POST['request_id'] ?? '') !== '' ? intval($_POST['request_id']) : null;
    $fin_approval_ref = trim($_POST['fin_approval_ref'] ?? '');
    $op_classification = trim($_POST['op_classification'] ?? 'استهلاكية');
    $currency = trim($_POST['currency'] ?? 'SDG');
    $fx_rate  = (float)($_POST['fx_rate'] ?? 1);
    $payment_time = trim($_POST['payment_time'] ?? 'فوري');
    $expected_receipt_type = trim($_POST['expected_receipt_type'] ?? 'مخزن');
    $state = trim($_POST['state'] ?? 'مسودة');
    $notes = trim($_POST['notes'] ?? '');

    if (!in_array($op_classification, $classifications, true)) { $op_classification = 'استهلاكية'; }
    if (!in_array($currency, $currencies, true)) { $currency = 'SDG'; }
    if (!in_array($payment_time, $pay_times, true)) { $payment_time = 'فوري'; }
    if (!in_array($expected_receipt_type, $recv_types, true)) { $expected_receipt_type = 'مخزن'; }
    if (!in_array($state, $states, true)) { $state = 'مسودة'; }

    // قاعدة §14: لا يصدر أمر (يغادر مسودة) بلا مرجع اعتماد مالي
    if ($state !== 'مسودة' && $fin_approval_ref === '') {
        header("Location: orders_proc.php?msg=لا+يصدر+الأمر+بلا+مرجع+اعتماد+مالي+❌"); exit();
    }

    // احسب السطور والإجمالي
    $item_ids = $_POST['line_item_id'] ?? array();
    $item_names = $_POST['line_item_name'] ?? array();
    $qtys = $_POST['line_qty'] ?? array();
    $prices = $_POST['line_price'] ?? array();
    $classes = $_POST['line_class'] ?? array();
    $total = 0.0;
    for ($i = 0; $i < count($item_names); $i++) {
        if (trim($item_names[$i] ?? '') === '') { continue; }
        $total += ((float)($qtys[$i] ?? 0)) * ((float)($prices[$i] ?? 0));
    }

    mysqli_begin_transaction($conn);
    try {
        if ($is_editing) {
            $sql = "UPDATE proc_order SET supplier_id=?, request_id=?, fin_approval_ref=?, op_classification=?, currency=?,
                    fx_rate=?, payment_time=?, expected_receipt_type=?, total_amount=?, state=?, notes=?
                    WHERE id=? AND company_id=? AND COALESCE(is_deleted,0)=0";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'iisssdssdssii', $supplier_id, $request_id, $fin_approval_ref, $op_classification,
                $currency, $fx_rate, $payment_time, $expected_receipt_type, $total, $state, $notes, $id, $company_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            $order_id = $id;
            $d = mysqli_prepare($conn, "DELETE FROM proc_order_line WHERE order_id=? AND company_id=?");
            mysqli_stmt_bind_param($d, 'ii', $order_id, $company_id);
            mysqli_stmt_execute($d); mysqli_stmt_close($d);
        } else {
            $code = proc_gen_code($conn, 'proc_order', 'PRC-PO', $company_id);
            $sql = "INSERT INTO proc_order (company_id, code, supplier_id, request_id, fin_approval_ref, op_classification,
                    currency, fx_rate, payment_time, expected_receipt_type, total_amount, state, notes, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'isiisssdssdssi', $company_id, $code, $supplier_id, $request_id, $fin_approval_ref,
                $op_classification, $currency, $fx_rate, $payment_time, $expected_receipt_type, $total, $state, $notes, $current_user_id);
            mysqli_stmt_execute($stmt);
            $order_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
        }

        $ln = mysqli_prepare($conn, "INSERT INTO proc_order_line (company_id, order_id, item_id, item_name, qty, unit_price, op_classification, subtotal)
                                     VALUES (?,?,?,?,?,?,?,?)");
        for ($i = 0; $i < count($item_names); $i++) {
            $iname = trim($item_names[$i] ?? '');
            if ($iname === '') { continue; }
            $iid = (isset($item_ids[$i]) && $item_ids[$i] !== '') ? intval($item_ids[$i]) : null;
            $qty = (float)($qtys[$i] ?? 1);
            $price = (float)($prices[$i] ?? 0);
            $cls = trim($classes[$i] ?? '');
            if (!in_array($cls, $classifications, true)) { $cls = $op_classification; }
            $sub = $qty * $price;
            mysqli_stmt_bind_param($ln, 'iissddsd', $company_id, $order_id, $iid, $iname, $qty, $price, $cls, $sub);
            mysqli_stmt_execute($ln);
        }
        mysqli_stmt_close($ln);
        mysqli_commit($conn);
    } catch (\Throwable $e) {
        mysqli_rollback($conn);
        header("Location: orders_proc.php?msg=تعذّر+الحفظ+❌"); exit();
    }
    header("Location: orders_proc.php?msg=" . ($is_editing ? 'تم+تعديل+الأمر+بنجاح+✅' : 'تمت+إضافة+الأمر+بنجاح+✅')); exit();
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: orders_proc.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $delete_id = intval($_GET['delete_id']);
    $sql = "UPDATE proc_order SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $delete_id, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: orders_proc.php?msg=تم+حذف+الأمر+بنجاح+✅"); exit();
}

// ── تحميل أمر للتعديل ──
$edit = null; $edit_lines = array();
if (isset($_GET['edit_id']) && $can_edit) {
    $eid = intval($_GET['edit_id']);
    $q = mysqli_prepare($conn, "SELECT * FROM proc_order WHERE id=? AND " . proc_scope('company_id', $is_super_admin, $company_id) . " AND COALESCE(is_deleted,0)=0 LIMIT 1");
    mysqli_stmt_bind_param($q, 'i', $eid);
    mysqli_stmt_execute($q);
    $r = mysqli_stmt_get_result($q);
    $edit = $r ? mysqli_fetch_assoc($r) : null;
    mysqli_stmt_close($q);
    if ($edit) {
        $lq = mysqli_prepare($conn, "SELECT * FROM proc_order_line WHERE order_id=? ORDER BY id ASC");
        mysqli_stmt_bind_param($lq, 'i', $eid);
        mysqli_stmt_execute($lq);
        $lr = mysqli_stmt_get_result($lq);
        while ($lr && ($lrow = mysqli_fetch_assoc($lr))) { $edit_lines[] = $lrow; }
        mysqli_stmt_close($lq);
    }
}

$page_title = 'إيكوبيشن | أوامر الشراء';
include '../inheader.php';
include '../insidebar.php';

/** صف سطر أمر شراء. */
function proc_ord_line_row($conn, $is_super_admin, $company_id, $classifications, $line = null)
{
    $iid = $line ? intval($line['item_id']) : 0;
    $iname = $line ? htmlspecialchars((string)$line['item_name'], ENT_QUOTES) : '';
    $qty = $line ? htmlspecialchars((string)$line['qty'], ENT_QUOTES) : '1';
    $price = $line ? htmlspecialchars((string)$line['unit_price'], ENT_QUOTES) : '0';
    $cls = $line ? (string)($line['op_classification'] ?? '') : '';
    $opts = proc_items_options($conn, $is_super_admin, $company_id, $iid);
    $clsopts = '<option value="">— تصنيف السطر —</option>';
    foreach ($classifications as $c) {
        $sel = ($c === $cls) ? ' selected' : '';
        $clsopts .= '<option value="' . htmlspecialchars($c) . '"' . $sel . '>' . htmlspecialchars($c) . '</option>';
    }
    return '<div class="proc-line form-grid" style="align-items:end;margin-bottom:8px">'
        . '<div class="form-group"><label>الصنف (كتالوج)</label><select name="line_item_id[]" class="line-item">' . $opts . '</select></div>'
        . '<div class="form-group"><label>اسم الصنف <span class="required">*</span></label><input type="text" name="line_item_name[]" class="line-name" value="' . $iname . '" required></div>'
        . '<div class="form-group"><label>الكمية</label><input type="number" step="0.01" name="line_qty[]" class="line-qty" value="' . $qty . '"></div>'
        . '<div class="form-group"><label>سعر الوحدة</label><input type="number" step="0.01" name="line_price[]" class="line-price" value="' . $price . '"></div>'
        . '<div class="form-group"><label>تصنيف السطر</label><select name="line_class[]">' . $clsopts . '</select></div>'
        . '<div class="form-group"><button type="button" class="btn-cancel removeLine"><i class="fas fa-times"></i></button></div>'
        . '</div>';
}
?>

<div class="main proc-orders-main ems-unified-page-shell">
    <?php
    $header_title = 'أوامر الشراء';
    $header_icon  = 'fa fa-file-invoice-dollar';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'أمر جديد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <form id="procForm" action="orders_proc.php" method="post" class="allforms<?php echo $edit ? ' allforms-visible' : ''; ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?php echo $edit ? 'تعديل أمر شراء' : 'أمر شراء جديد'; ?></h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" value="<?php echo $edit ? intval($edit['id']) : ''; ?>">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>المورد التشغيلي</label>
                        <select name="supplier_id"><?php echo proc_suppliers_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['supplier_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label>مرجع طلب الشراء</label>
                        <select name="request_id"><?php echo proc_options_from_query($conn, $requests_sql, $edit ? intval($edit['request_id']) : 0, '— بلا طلب —'); ?></select>
                    </div>
                    <div class="form-group">
                        <label>مرجع الاعتماد المالي <span class="required">*</span> <small>(شرط الإصدار)</small></label>
                        <input type="text" name="fin_approval_ref" value="<?php echo $edit ? htmlspecialchars((string)$edit['fin_approval_ref']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>التصنيف التشغيلي</label>
                        <select name="op_classification">
                            <?php foreach ($classifications as $c): $sel = ($edit && $edit['op_classification'] === $c) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>العملة</label>
                        <select name="currency">
                            <?php foreach ($currencies as $c): $sel = ($edit && $edit['currency'] === $c) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>سعر الصرف</label>
                        <input type="number" step="0.0001" name="fx_rate" value="<?php echo $edit ? htmlspecialchars((string)$edit['fx_rate']) : '1'; ?>">
                    </div>
                    <div class="form-group">
                        <label>وقت الدفع</label>
                        <select name="payment_time">
                            <?php foreach ($pay_times as $p): $sel = ($edit && $edit['payment_time'] === $p) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>نوع الاستلام المتوقع</label>
                        <select name="expected_receipt_type">
                            <?php foreach ($recv_types as $rt): $sel = ($edit && $edit['expected_receipt_type'] === $rt) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($rt); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($rt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>حالة الأمر</label>
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
                <div class="card-header"><h5><i class="fas fa-list"></i> سطور الأصناف</h5></div>
                <div id="linesBody">
                    <?php
                    if ($edit && !empty($edit_lines)) {
                        foreach ($edit_lines as $l) { echo proc_ord_line_row($conn, $is_super_admin, $company_id, $classifications, $l); }
                    } else {
                        echo proc_ord_line_row($conn, $is_super_admin, $company_id, $classifications, null);
                    }
                    ?>
                </div>
                <button type="button" id="addLine" class="add-btn" style="margin-top:6px"><i class="fas fa-plus"></i> إضافة سطر</button>
                <div style="margin-top:10px;font-weight:700">الإجمالي: <span id="ordTotal">0.00</span></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <a href="orders_proc.php" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>

    <template id="lineTemplate">
        <?php echo proc_ord_line_row($conn, $is_super_admin, $company_id, $classifications, null); ?>
    </template>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>المورد</th><th>التصنيف</th><th>العملة</th>
                    <th>الإجمالي</th><th>الحالة</th><th>مرجع الاعتماد</th><th>أُنشئ</th>
                </tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT o.id, o.code, o.op_classification, o.currency, o.total_amount, o.state, o.fin_approval_ref, o.created_at,
                            s.name AS supplier_name
                            FROM proc_order o LEFT JOIN proc_supplier s ON s.id=o.supplier_id
                            WHERE " . proc_scope('o.company_id', $is_super_admin, $company_id) . " AND COALESCE(o.is_deleted,0)=0
                            ORDER BY o.id DESC";
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
                        echo "<td>" . htmlspecialchars((string)($row['code'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['supplier_name'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['op_classification']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['currency']) . "</td>";
                        echo "<td>" . htmlspecialchars(number_format((float)$row['total_amount'], 2)) . "</td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string)$row['state']) . "</span></td>";
                        echo "<td>" . htmlspecialchars((string)($row['fin_approval_ref'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['created_at']) . "</td>";
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
            var p = parseFloat($(this).find('.line-price').val()) || 0;
            t += q * p;
        });
        $('#ordTotal').text(t.toFixed(2));
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
        $(document).on('input', '.line-qty, .line-price', recalcTotal);
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
