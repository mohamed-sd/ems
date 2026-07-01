<?php
/**
 * Procurement/requests_proc.php — طلبات الشراء التشغيلية (proc_request + proc_request_line) — §15.1.
 * مستند رأس + سطور في صفحة واحدة (سطور ديناميكية عبر <template>). عزل شركة + حذف ناعم.
 * التصنيف التشغيلي إلزامي (وقائية/تصحيحية/رأسمالية/استهلاكية). شاشة جديدة مستقلة.
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

$perms = proc_page_perms($conn, 'Procurement/requests_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+طلبات+الشراء+❌");
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);
$classifications = proc_classifications();
$need_sources   = proc_need_sources();
$priorities     = proc_priorities();
$states         = proc_request_states();
$fin_states     = array('بانتظار', 'معتمد مالياً', 'مرفوض');

// ── حفظ (إضافة/تعديل) رأس + سطور ضمن معاملة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['need_source'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { header("Location: requests_proc.php?msg=لا+توجد+صلاحية+تعديل+❌"); exit(); }
    if (!$is_editing && !$can_add) { header("Location: requests_proc.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0)         { header("Location: requests_proc.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $need_source = trim($_POST['need_source'] ?? '');
    $source_ref  = trim($_POST['source_ref'] ?? '');
    $op_classification = trim($_POST['op_classification'] ?? '');
    $requesting_dept   = trim($_POST['requesting_dept'] ?? '');
    $equipment_id = ($_POST['equipment_id'] ?? '') !== '' ? intval($_POST['equipment_id']) : null;
    $project_id   = ($_POST['project_id'] ?? '') !== '' ? intval($_POST['project_id']) : null;
    $priority     = trim($_POST['priority'] ?? 'عادي');
    $fin_state    = trim($_POST['fin_approval_state'] ?? 'بانتظار');
    $state        = trim($_POST['state'] ?? 'مسودة');
    $notes        = trim($_POST['notes'] ?? '');

    if (!in_array($need_source, $need_sources, true) || !in_array($op_classification, $classifications, true)) {
        header("Location: requests_proc.php?msg=بيانات+غير+مكتملة+(المصدر+والتصنيف+إلزاميان)+❌"); exit();
    }
    if (!in_array($priority, $priorities, true)) { $priority = 'عادي'; }
    if (!in_array($state, $states, true)) { $state = 'مسودة'; }
    if (!in_array($fin_state, $fin_states, true)) { $fin_state = 'بانتظار'; }

    mysqli_begin_transaction($conn);
    try {
        if ($is_editing) {
            $sql = "UPDATE proc_request SET need_source=?, source_ref=?, op_classification=?, requesting_dept=?,
                    equipment_id=?, project_id=?, priority=?, fin_approval_state=?, state=?, notes=?
                    WHERE id=? AND company_id=? AND COALESCE(is_deleted,0)=0";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ssssiisssssii', $need_source, $source_ref, $op_classification, $requesting_dept,
                $equipment_id, $project_id, $priority, $fin_state, $state, $notes, $id, $company_id);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            $req_id = $id;
            // حذف السطور القديمة (مقيّد بالشركة) ثم إعادة الإدراج
            $d = mysqli_prepare($conn, "DELETE FROM proc_request_line WHERE request_id=? AND company_id=?");
            mysqli_stmt_bind_param($d, 'ii', $req_id, $company_id);
            mysqli_stmt_execute($d); mysqli_stmt_close($d);
        } else {
            $code = proc_gen_code($conn, 'proc_request', 'PRC-REQ', $company_id);
            $sql = "INSERT INTO proc_request (company_id, code, need_source, source_ref, op_classification, requesting_dept,
                    equipment_id, project_id, priority, fin_approval_state, state, notes, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'isssssiissssi', $company_id, $code, $need_source, $source_ref, $op_classification,
                $requesting_dept, $equipment_id, $project_id, $priority, $fin_state, $state, $notes, $current_user_id);
            mysqli_stmt_execute($stmt);
            $req_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
        }

        // إدراج السطور
        $item_ids = $_POST['line_item_id'] ?? array();
        $item_names = $_POST['line_item_name'] ?? array();
        $qtys = $_POST['line_qty'] ?? array();
        $classes = $_POST['line_class'] ?? array();
        $lnotes = $_POST['line_note'] ?? array();
        $ln = mysqli_prepare($conn, "INSERT INTO proc_request_line (company_id, request_id, item_id, item_name, qty, op_classification, note)
                                     VALUES (?,?,?,?,?,?,?)");
        for ($i = 0; $i < count($item_names); $i++) {
            $iname = trim($item_names[$i] ?? '');
            if ($iname === '') { continue; }
            $iid = (isset($item_ids[$i]) && $item_ids[$i] !== '') ? intval($item_ids[$i]) : null;
            $qty = (float)($qtys[$i] ?? 1);
            $cls = trim($classes[$i] ?? '');
            if (!in_array($cls, $classifications, true)) { $cls = $op_classification; }
            $lnote = trim($lnotes[$i] ?? '');
            mysqli_stmt_bind_param($ln, 'iissdss', $company_id, $req_id, $iid, $iname, $qty, $cls, $lnote);
            mysqli_stmt_execute($ln);
        }
        mysqli_stmt_close($ln);
        mysqli_commit($conn);
    } catch (\Throwable $e) {
        mysqli_rollback($conn);
        header("Location: requests_proc.php?msg=تعذّر+الحفظ+❌"); exit();
    }
    header("Location: requests_proc.php?msg=" . ($is_editing ? 'تم+تعديل+الطلب+بنجاح+✅' : 'تمت+إضافة+الطلب+بنجاح+✅')); exit();
}

// ── حذف ناعم (السطور تُحذف بالـ CASCADE عند الحذف الصلب، لكن هنا حذف ناعم للرأس فقط) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: requests_proc.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    $delete_id = intval($_GET['delete_id']);
    $sql = "UPDATE proc_request SET is_deleted=1, deleted_at=NOW(), deleted_by=? WHERE id=? AND company_id=?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iii', $current_user_id, $delete_id, $company_id);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
    }
    header("Location: requests_proc.php?msg=تم+حذف+الطلب+بنجاح+✅"); exit();
}

// ── تحميل طلب للتعديل ──
$edit = null; $edit_lines = array();
if (isset($_GET['edit_id']) && $can_edit) {
    $eid = intval($_GET['edit_id']);
    $q = mysqli_prepare($conn, "SELECT * FROM proc_request WHERE id=? AND " . proc_scope('company_id', $is_super_admin, $company_id) . " AND COALESCE(is_deleted,0)=0 LIMIT 1");
    mysqli_stmt_bind_param($q, 'i', $eid);
    mysqli_stmt_execute($q);
    $r = mysqli_stmt_get_result($q);
    $edit = $r ? mysqli_fetch_assoc($r) : null;
    mysqli_stmt_close($q);
    if ($edit) {
        $lq = mysqli_prepare($conn, "SELECT * FROM proc_request_line WHERE request_id=? ORDER BY id ASC");
        mysqli_stmt_bind_param($lq, 'i', $eid);
        mysqli_stmt_execute($lq);
        $lr = mysqli_stmt_get_result($lq);
        while ($lr && ($lrow = mysqli_fetch_assoc($lr))) { $edit_lines[] = $lrow; }
        mysqli_stmt_close($lq);
    }
}

$page_title = 'إيكوبيشن | طلبات الشراء';
include '../inheader.php';
include '../insidebar.php';

/** يبني صف سطر واحد (للسطور المحمّلة عند التعديل). */
function proc_req_line_row($conn, $is_super_admin, $company_id, $classifications, $line = null)
{
    $iid = $line ? intval($line['item_id']) : 0;
    $iname = $line ? htmlspecialchars((string)$line['item_name'], ENT_QUOTES) : '';
    $qty = $line ? htmlspecialchars((string)$line['qty'], ENT_QUOTES) : '1';
    $cls = $line ? (string)($line['op_classification'] ?? '') : '';
    $note = $line ? htmlspecialchars((string)($line['note'] ?? ''), ENT_QUOTES) : '';
    $opts = proc_items_options($conn, $is_super_admin, $company_id, $iid);
    $clsopts = '<option value="">— تصنيف السطر —</option>';
    foreach ($classifications as $c) {
        $sel = ($c === $cls) ? ' selected' : '';
        $clsopts .= '<option value="' . htmlspecialchars($c) . '"' . $sel . '>' . htmlspecialchars($c) . '</option>';
    }
    return '<div class="proc-line form-grid" style="align-items:end;margin-bottom:8px">'
        . '<div class="form-group"><label>الصنف (كتالوج)</label><select name="line_item_id[]" class="line-item">' . $opts . '</select></div>'
        . '<div class="form-group"><label>اسم الصنف <span class="required">*</span></label><input type="text" name="line_item_name[]" class="line-name" value="' . $iname . '" required></div>'
        . '<div class="form-group"><label>الكمية</label><input type="number" step="0.01" name="line_qty[]" value="' . $qty . '"></div>'
        . '<div class="form-group"><label>تصنيف السطر</label><select name="line_class[]">' . $clsopts . '</select></div>'
        . '<div class="form-group"><label>ملاحظة</label><input type="text" name="line_note[]" value="' . $note . '"></div>'
        . '<div class="form-group"><button type="button" class="btn-cancel removeLine"><i class="fas fa-times"></i></button></div>'
        . '</div>';
}
?>

<div class="main proc-requests-main ems-unified-page-shell">
    <?php
    $header_title = 'طلبات الشراء التشغيلية';
    $header_icon  = 'fa fa-file-lines';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'طلب جديد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php proc_msg_banner(); ?>

    <form id="procForm" action="requests_proc.php" method="post" class="allforms<?php echo $edit ? ' allforms-visible' : ''; ?>">
        <div class="card-header"><h5><i class="fas fa-edit"></i> <?php echo $edit ? 'تعديل طلب شراء' : 'طلب شراء جديد'; ?></h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" value="<?php echo $edit ? intval($edit['id']) : ''; ?>">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>مصدر الاحتياج <span class="required">*</span></label>
                        <select name="need_source" required>
                            <?php foreach ($need_sources as $s): $sel = ($edit && $edit['need_source'] === $s) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>مرجع المصدر (خطة/أمر/نقطة طلب)</label>
                        <input type="text" name="source_ref" value="<?php echo $edit ? htmlspecialchars((string)$edit['source_ref']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>التصنيف التشغيلي <span class="required">*</span></label>
                        <select name="op_classification" required>
                            <?php foreach ($classifications as $c): $sel = ($edit && $edit['op_classification'] === $c) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>الإدارة الطالبة</label>
                        <input type="text" name="requesting_dept" value="<?php echo $edit ? htmlspecialchars((string)$edit['requesting_dept']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>المعدة</label>
                        <select name="equipment_id"><?php echo proc_equipment_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['equipment_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label>المشروع</label>
                        <select name="project_id"><?php echo proc_project_options($conn, $is_super_admin, $company_id, $edit ? intval($edit['project_id']) : 0); ?></select>
                    </div>
                    <div class="form-group">
                        <label>الأولوية</label>
                        <select name="priority">
                            <?php foreach ($priorities as $p): $sel = ($edit && $edit['priority'] === $p) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($p); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>حالة الطلب</label>
                        <select name="state">
                            <?php foreach ($states as $st): $sel = ($edit && $edit['state'] === $st) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($st); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>حالة الاعتماد المالي</label>
                        <select name="fin_approval_state">
                            <?php foreach ($fin_states as $fs): $sel = ($edit && $edit['fin_approval_state'] === $fs) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($fs); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($fs); ?></option>
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
                <div class="card-header"><h5><i class="fas fa-list"></i> الأصناف المطلوبة</h5></div>
                <div id="linesBody">
                    <?php
                    if ($edit && !empty($edit_lines)) {
                        foreach ($edit_lines as $l) { echo proc_req_line_row($conn, $is_super_admin, $company_id, $classifications, $l); }
                    } else {
                        echo proc_req_line_row($conn, $is_super_admin, $company_id, $classifications, null);
                    }
                    ?>
                </div>
                <button type="button" id="addLine" class="add-btn" style="margin-top:6px"><i class="fas fa-plus"></i> إضافة سطر</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ</button>
                <a href="requests_proc.php" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>

    <template id="lineTemplate">
        <?php echo proc_req_line_row($conn, $is_super_admin, $company_id, $classifications, null); ?>
    </template>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>المصدر</th><th>التصنيف</th><th>الأولوية</th>
                    <th>الحالة</th><th>الاعتماد المالي</th><th>عدد الأصناف</th><th>أُنشئ</th>
                </tr></thead>
                <tbody>
                    <?php
                    $sql = "SELECT r.id, r.code, r.need_source, r.op_classification, r.priority, r.state, r.fin_approval_state, r.created_at,
                            (SELECT COUNT(*) FROM proc_request_line l WHERE l.request_id=r.id) AS line_count
                            FROM proc_request r WHERE " . proc_scope('r.company_id', $is_super_admin, $company_id) . "
                            AND COALESCE(r.is_deleted,0)=0 ORDER BY r.id DESC";
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
                        echo "<td>" . htmlspecialchars((string)$row['need_source']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['op_classification']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['priority']) . "</td>";
                        echo "<td><span class='action-btn'>" . htmlspecialchars((string)$row['state']) . "</span></td>";
                        echo "<td>" . htmlspecialchars((string)$row['fin_approval_state']) . "</td>";
                        echo "<td>" . intval($row['line_count']) . "</td>";
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
            toggleBtn.addEventListener('click', function () { $('#procForm').toggleClass('allforms-visible'); });
        }

        // إضافة سطر من القالب
        $('#addLine').on('click', function () {
            var tpl = document.getElementById('lineTemplate');
            var clone = document.importNode(tpl.content, true);
            document.getElementById('linesBody').appendChild(clone);
        });

        // حذف سطر
        $(document).on('click', '.removeLine', function () {
            var rows = $('#linesBody .proc-line');
            if (rows.length > 1) { $(this).closest('.proc-line').remove(); }
            else { $(this).closest('.proc-line').find('input,select').val(''); }
        });

        // عند اختيار صنف من الكتالوج: انسخ اسمه إلى اسم الصنف إن كان فارغاً
        $(document).on('change', '.line-item', function () {
            var txt = $(this).find('option:selected').text().trim();
            var $name = $(this).closest('.proc-line').find('.line-name');
            if (txt && !$name.val()) {
                // أزل بادئة الكود إن وُجدت (code — name)
                var parts = txt.split(' — ');
                $name.val(parts.length > 1 ? parts[1] : txt);
            }
        });
    });
})();
</script>
</body>
</html>
