<?php
/**
 * Finance/budget_form_fin.php — الميزانيات وبنودها والانحراف (fin_budgets + fin_budget_lines) — §3.5/§3.6/§7.2.
 * رأس + بنود ديناميكية. الانحراف = فعلي − مخطّط (عمود مولّد). اعتماد يجعلها مرجعاً حاكماً.
 * شاشة مستقلة — عزل شركة + حذف ناعم.
 */
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx             = fin_ctx();
$is_super_admin  = $ctx['is_super'];
$company_id      = $ctx['company_id'];
$current_user_id = $ctx['user_id'];

if (!$is_super_admin && $company_id <= 0) {
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$perms = fin_page_perms($conn, 'Finance/budget_form_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+الميزانيات+❌");
    exit();
}

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$dept_modules   = fin_dept_modules();
$period_types   = fin_period_types();
$budget_states  = fin_budget_states();
$categories     = fin_budget_categories();

// ── اعتماد الميزانية (مرجع حاكم) ──
if (isset($_GET['approve_id'])) {
    if (!$can_edit) { header("Location: budget_form_fin.php?msg=لا+توجد+صلاحية+الاعتماد+❌"); exit(); }
    $aid = intval($_GET['approve_id']);
    fin_gate($is_super_admin)->update('fin_budgets',
        array('state' => 'approved', 'approved_by' => $current_user_id),
        array('id' => $aid), "state IN('draft','submitted')");
    header("Location: budget_form_fin.php?msg=تم+اعتماد+الميزانية+(مرجع+حاكم)+✅"); exit();
}

// ── حذف ناعم (مسودة فقط) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { header("Location: budget_form_fin.php?msg=لا+توجد+صلاحية+حذف+❌"); exit(); }
    // حذف ناعم مشروط بالمسودة (عبر update لحفظ شرط state='draft' الأصلي — softDelete بلا شرط)
    $did = intval($_GET['delete_id']);
    fin_gate($is_super_admin)->update('fin_budgets',
        array('is_deleted' => 1, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $current_user_id),
        array('id' => $did), "state='draft'");
    header("Location: budget_form_fin.php?msg=تم+حذف+الميزانية+بنجاح+✅"); exit();
}

// ── حفظ ميزانية جديدة (رأس + بنود) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dept_module'])) {
    if (!$can_add) { header("Location: budget_form_fin.php?msg=لا+توجد+صلاحية+إضافة+❌"); exit(); }
    if ($company_id <= 0) { header("Location: budget_form_fin.php?msg=لا+يمكن+الحفظ+بلا+شركة+صالحة+❌"); exit(); }

    $dept    = trim($_POST['dept_module'] ?? '');
    $ptype   = trim($_POST['period_type'] ?? '');
    $fyear   = intval($_POST['fiscal_year'] ?? 0);
    $pno     = ($_POST['period_no'] ?? '') === '' ? null : intval($_POST['period_no']);
    $note    = trim($_POST['note'] ?? '');
    $kinds   = $_POST['line_kind'] ?? array();
    $cats    = $_POST['category'] ?? array();
    $planned = $_POST['planned'] ?? array();

    if (!isset($dept_modules[$dept]) || !isset($period_types[$ptype]) || $fyear < 2000) {
        header("Location: budget_form_fin.php?msg=بيانات+غير+صحيحة+(إدارة/فترة/سنة)+❌"); exit();
    }

    $lines = array(); $t_rev = 0; $t_exp = 0;
    for ($i = 0; $i < count($cats); $i++) {
        $kind = ($kinds[$i] ?? '') === 'revenue' ? 'revenue' : 'expense';
        $cat  = trim($cats[$i] ?? '');
        $amt  = round(floatval($planned[$i] ?? 0), 2);
        if (!isset($categories[$cat]) || $amt <= 0) { continue; }
        $lines[] = array('k' => $kind, 'c' => $cat, 'p' => $amt);
        if ($kind === 'revenue') { $t_rev += $amt; } else { $t_exp += $amt; }
    }
    if (count($lines) < 1) { header("Location: budget_form_fin.php?msg=الميزانية+تحتاج+بنداً+واحداً+فأكثر+❌"); exit(); }

    $budget_no = fin_gen_code($conn, 'fin_budgets', 'FIN-BG', $company_id);
    // الرأس + البنود زوجٌ مترابط ذرّيًا (رأسٌ بلا بنود = ميزانية مكسورة) — معاملة
    // مُدارة §9: الأصل كتابتان غير معاملتين؛ تقوية مسار الفشل فقط، السعيد مطابق
    try {
        fin_gate($is_super_admin)->runInTransaction(function ($g) use ($budget_no, $dept, $ptype, $fyear, $pno, $t_rev, $t_exp, $note, $current_user_id, $lines) {
            $budget_id = $g->insert('fin_budgets', array(
                'budget_no' => $budget_no, 'dept_module' => $dept, 'period_type' => $ptype,
                'fiscal_year' => $fyear, 'period_no' => $pno, 'total_revenue' => $t_rev,
                'total_expense' => $t_exp, 'note' => $note, 'state' => 'draft', 'created_by' => $current_user_id,
            ));
            foreach ($lines as $ln) {
                $g->insert('fin_budget_lines', array(
                    'budget_id' => $budget_id, 'line_kind' => $ln['k'], 'category' => $ln['c'], 'planned_amount' => $ln['p'],
                ));
            }
        }, 'budget_form save head+lines');
    } catch (\App\Core\TenantGateException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) { header("Location: budget_form_fin.php?msg=ميزانية+هذه+الفترة+موجودة+مسبقاً+❌"); exit(); }
        error_log('budget save refused: ' . $e->getMessage());
        header("Location: budget_form_fin.php?msg=حدث+خطأ+أثناء+الحفظ+❌"); exit();
    }
    header("Location: budget_form_fin.php?msg=تم+حفظ+الميزانية+بنجاح+✅"); exit();
}

$page_title = 'إيكوبيشن | الميزانية والانحراف';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main fin-budget-main ems-unified-page-shell">
    <?php
    $header_title = 'الميزانية والانحراف';
    $header_icon  = 'fa fa-chart-pie';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إنشاء ميزانية');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إنشاء ميزانية</h5></div>
        <div class="card"><div class="card-body">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>الإدارة المالكة <span class="required">*</span></label>
                        <select name="dept_module" id="b_dept" required>
                            <option value="">— اختر —</option>
                            <?php foreach ($dept_modules as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>نوع الفترة <span class="required">*</span></label>
                        <select name="period_type" id="b_ptype" required>
                            <?php foreach ($period_types as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>السنة المالية <span class="required">*</span></label>
                        <input type="number" name="fiscal_year" id="b_year" required value="<?php echo date('Y'); ?>">
                    </div>
                    <div class="form-group">
                        <label>رقم الفترة (ربع/شهر)</label>
                        <input type="number" name="period_no" id="b_pno" min="1" max="12" placeholder="اختياري">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>ملاحظة</label>
                        <input type="text" name="note" id="b_note">
                    </div>
                </div>
            </div>

            <div class="table-container" style="margin-top:10px;">
                <table class="alltables" style="width:100%;" id="b_lines">
                    <thead><tr><th>النوع</th><th>الفئة</th><th>المبلغ المخطّط</th><th></th></tr></thead>
                    <tbody id="b_lines_body"></tbody>
                </table>
                <button type="button" class="btn-cancel" onclick="bAddLine()"><i class="fas fa-plus"></i> إضافة بند</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ الميزانية</button>
                <button type="button" class="btn-cancel" onclick="finToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px"><i class="fas fa-list"></i> الميزانيات</h5>
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الرقم</th><th>الإدارة</th><th>الفترة</th><th>السنة</th>
                    <th>مخطّط إيراد</th><th>مخطّط مصروف</th><th>الحالة</th>
                </tr></thead>
                <tbody>
                    <?php
                    $budget_rows = fin_gate($is_super_admin)->select('fin_budgets', array('orderBy' => 'id DESC'));
                    { foreach ($budget_rows as $row) {
                        $st = (string)$row['state'];
                        $st_tone = in_array($st, array('approved','active')) ? 'success' : ($st === 'closed' ? 'dark' : 'secondary');
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit && in_array($st, array('draft','submitted'))) {
                            echo "<a href='?approve_id=" . intval($row['id']) . "' class='action-btn edit' title='اعتماد' onclick='return confirm(\"اعتماد الميزانية كمرجع حاكم؟\")'><i class='fas fa-gavel'></i></a>";
                        }
                        if ($can_delete && $st === 'draft') {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)$row['budget_no']) . "</td>";
                        echo "<td>" . htmlspecialchars($dept_modules[$row['dept_module']] ?? $row['dept_module']) . "</td>";
                        echo "<td>" . htmlspecialchars($period_types[$row['period_type']] ?? $row['period_type']) . ($row['period_no'] ? ' #' . intval($row['period_no']) : '') . "</td>";
                        echo "<td>" . intval($row['fiscal_year']) . "</td>";
                        echo "<td>" . number_format((float)$row['total_revenue'], 2) . "</td>";
                        echo "<td>" . number_format((float)$row['total_expense'], 2) . "</td>";
                        echo "<td><span class='badge badge-" . $st_tone . "'>" . htmlspecialchars($budget_states[$st] ?? $st) . "</span></td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>

        <h5 style="margin:18px 0 10px"><i class="fas fa-triangle-exclamation"></i> مراقبة الانحراف (المخطّط مقابل الفعلي)</h5>
        <div class="table-container">
            <table id="varTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الميزانية</th><th>النوع</th><th>الفئة</th><th>مخطّط</th><th>فعلي</th><th>الانحراف</th><th>النسبة %</th><th>الحالة</th>
                </tr></thead>
                <tbody>
                    <?php
                    // تكافؤ INNER→LEFT + b.id IS NOT NULL (نمط F1 المُثبَت)
                    $var_rows = fin_gate($is_super_admin)->scopedQuery(
                        array('scope' => array('l' => 'fin_budget_lines'), 'enrich' => array('b' => 'fin_budgets')),
                        "SELECT l.*, b.budget_no FROM fin_budget_lines l
                         LEFT JOIN fin_budgets b ON b.id = l.budget_id
                         WHERE {TENANT_SCOPE} AND b.id IS NOT NULL AND COALESCE(b.is_deleted,0)=0 ORDER BY l.id DESC");
                    { foreach ($var_rows as $l) {
                        $var = (float)$l['variance'];
                        $kind = $l['line_kind'] === 'revenue' ? 'إيراد' : 'مصروف';
                        // للمصروف: تجاوز (فعلي>مخطّط) = خطر. للإيراد: نقص (فعلي<مخطّط) = خطر.
                        $bad = ($l['line_kind'] === 'expense') ? ($var > 0) : ($var < 0);
                        $tone = ($var == 0) ? 'secondary' : ($bad ? 'danger' : 'success');
                        $pct = $l['variance_pct'] === null ? '—' : number_format((float)$l['variance_pct'], 1) . '%';
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars((string)$l['budget_no']) . "</td>";
                        echo "<td>" . $kind . "</td>";
                        echo "<td>" . htmlspecialchars($categories[$l['category']] ?? $l['category']) . "</td>";
                        echo "<td>" . number_format((float)$l['planned_amount'], 2) . "</td>";
                        echo "<td>" . number_format((float)$l['actual_amount'], 2) . "</td>";
                        echo "<td><span class='badge badge-" . $tone . "'>" . number_format($var, 2) . "</span></td>";
                        echo "<td>" . $pct . "</td>";
                        echo "<td>" . htmlspecialchars((string)$l['var_state']) . "</td>";
                        echo "</tr>";
                    } }
                    ?>
                </tbody>
            </table>
        </div>
    </div></div>
</div>

<template id="b_line_tpl">
    <tr class="b-line">
        <td><select name="line_kind[]" class="b-kind"><option value="expense">مصروف</option><option value="revenue">إيراد</option></select></td>
        <td><select name="category[]" class="b-cat"><?php foreach ($categories as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></td>
        <td><input type="number" step="0.01" min="0" name="planned[]" class="b-planned" value="0"></td>
        <td><a href="javascript:void(0)" class="action-btn delete b-del" title="حذف"><i class="fas fa-times"></i></a></td>
    </tr>
</template>

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
    window.bAddLine = function () {
        var tpl = document.getElementById('b_line_tpl');
        document.getElementById('b_lines_body').appendChild(document.importNode(tpl.content, true));
    };
    $(document).ready(function () {
        $('#finTable, #varTable').DataTable({
            scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
            buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
            "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
        });
        bAddLine(); bAddLine();
        $(document).on('click', '.b-del', function () { $(this).closest('tr').remove(); });
        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) { toggleBtn.addEventListener('click', function () { $('#finForm').toggleClass('allforms-visible'); }); }
    });
    window.finToggleForm = function () { $('#finForm').toggleClass('allforms-visible'); };
})();
</script>
</body>
</html>
