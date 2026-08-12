<?php
/**
 * Finance/variance_monitor_fin.php — مراقبة الانحراف ومعالجته (§3.10 · §7.3 · §4).
 * ───────────────────────────────────────────────────────────────────────────
 * يغلق فجوة «الانحراف المُدار»: الأعمدة قائمةٌ على fin_budget_lines
 * (cause · corrective_action · responsible_id · var_state) والانحراف محسوبٌ
 * (variance/variance_pct مولَّدان)؛ هذه الشاشة **واجهة المعالجة** — توثيق السبب
 * والإجراء التصحيحي وتعيين المالك ونقل الحالة open → in_progress → closed.
 * لا جدول جديد ولا تغيير مخطّط. شاشة مستقلة — عزل شركة عبر البوابة + صلاحيات §15.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = fin_page_perms($conn, 'Finance/variance_monitor_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_edit = $perms['can_edit'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض مراقبة الانحراف ❌', 'GOV-PERM-403', '');
    exit();
}

// تعليم إشعارٍ مقروءًا (قبل أي إخراج)
fin_handle_notif_read($conn, $company_id, 'variance_monitor_fin.php');

$dept_modules = fin_dept_modules();
$categories   = fin_budget_categories();
$var_states   = array('open' => 'مفتوح', 'in_progress' => 'قيد المعالجة', 'closed' => 'مغلق');
$var_tone     = array('open' => 'danger', 'in_progress' => 'warn', 'closed' => 'success');

// ── حفظ معالجة الانحراف (السبب · الإجراء · المالك · الحالة) على بند الموازنة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['line_id'])) {
    if (!$can_edit) { ems_gov_flash_redirect('variance_monitor_fin.php', 'لا توجد صلاحية المعالجة ❌', 'GOV-PERM-403', ''); exit(); }

    $line_id     = intval($_POST['line_id']);
    $cause       = mb_substr(trim($_POST['cause'] ?? ''), 0, 200);
    $corrective  = mb_substr(trim($_POST['corrective_action'] ?? ''), 0, 200);
    $responsible = ($_POST['responsible_id'] ?? '') === '' ? null : intval($_POST['responsible_id']);
    $vstate      = in_array(($_POST['var_state'] ?? ''), array('open', 'in_progress', 'closed'), true)
                     ? $_POST['var_state'] : 'open';

    // البوابة تعزل الشركة في WHERE ⇒ لا معالجةَ لبندٍ خارج شركة المستخدم
    $affected = fin_gate($is_super_admin)->update('fin_budget_lines', array(
        'cause'             => $cause !== '' ? $cause : null,
        'corrective_action' => $corrective !== '' ? $corrective : null,
        'responsible_id'    => $responsible,
        'var_state'         => $vstate,
    ), array('id' => $line_id));

    if ($affected > 0) {
        ems_gov_flash_redirect('variance_monitor_fin.php', 'تم حفظ معالجة الانحراف ✅', 'GOV-OK-200', ''); exit();
    }
    ems_gov_flash_redirect('variance_monitor_fin.php', 'تعذّر الحفظ (بندٌ غير موجود في شركتك) ❌', 'GOV-REF-404', ''); exit();
}

// ── تصفية العرض ──
$flt = in_array(($_GET['flt'] ?? 'all'), array('all', 'breaches', 'unresolved'), true) ? $_GET['flt'] : 'all';
$flt_sql = '';
if ($flt === 'breaches')   { $flt_sql = ' AND l.variance_pct IS NOT NULL AND ABS(l.variance_pct) > 10'; }
if ($flt === 'unresolved') { $flt_sql = " AND l.variance <> 0 AND (l.var_state IS NULL OR l.var_state <> 'closed')"; }

// خريطة الموظفين (اسم المالك) — قراءةٌ واحدةٌ بدل ربطٍ في الاستعلام المعزول
$emp_map = array();
foreach (fin_gate($is_super_admin)->select('employees', array('columns' => array('id', 'name'))) as $e) {
    $emp_map[intval($e['id'])] = $e['name'];
}

// بنود موازناتٍ معتمدة/نشطة بانحرافها (نمط budget_form: INNER→LEFT + b.id IS NOT NULL)
$var_rows = fin_gate($is_super_admin)->scopedQuery(
    array('scope' => array('l' => 'fin_budget_lines'), 'enrich' => array('b' => 'fin_budgets')),
    "SELECT l.id, l.category, l.line_kind, l.planned_amount, l.actual_amount,
            l.variance, l.variance_pct, l.cause, l.corrective_action, l.responsible_id, l.var_state,
            b.budget_no, b.dept_module
     FROM fin_budget_lines l
     LEFT JOIN fin_budgets b ON b.id = l.budget_id
     WHERE {TENANT_SCOPE} AND b.id IS NOT NULL AND COALESCE(b.is_deleted,0)=0
       AND b.state IN('approved','active')" . $flt_sql . "
     ORDER BY (l.variance_pct IS NULL) ASC, ABS(l.variance_pct) DESC, l.id DESC");

// عدّادات موجزة (تُحسب من الصفوف المعروضة)
$sum_open = $sum_prog = $sum_closed = $sum_breach = 0;
foreach ($var_rows as $r) {
    $st = $r['var_state'] ?: 'open';
    if ($st === 'closed') { $sum_closed++; } elseif ($st === 'in_progress') { $sum_prog++; } else { $sum_open++; }
    if ($r['variance_pct'] !== null && abs((float)$r['variance_pct']) > 10) { $sum_breach++; }
}

// ── وضع التحرير: تحميل بندٍ لتعبئة النموذج ──
$editLine = null;
if (isset($_GET['edit_id']) && $can_edit) {
    $editLine = fin_gate($is_super_admin)->selectOne('fin_budget_lines', array('where' => array('id' => intval($_GET['edit_id']))));
}

$page_title = 'إيكوبيشن | متابعة الانحراف';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main fin-variance-main ems-unified-page-shell">
    <?php
    $header_title = 'متابعة الانحراف';
    $header_icon  = 'fa fa-triangle-exclamation';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php fin_msg_banner(); ?>
    <?php fin_notifications_panel($conn, $ctx, 'variance_monitor_fin.php'); ?>

    <!-- بطاقات موجزة -->
    <div class="stats-row" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <?php
        $cards = array(
            array('تجاوزات فوق 10%', $sum_breach, '#b91c1c', 'fa-triangle-exclamation'),
            array('مفتوحة', $sum_open, '#b45309', 'fa-folder-open'),
            array('قيد المعالجة', $sum_prog, '#2563eb', 'fa-spinner'),
            array('مغلقة', $sum_closed, '#166534', 'fa-circle-check'),
        );
        foreach ($cards as $c) {
            echo '<div class="card" style="flex:1;min-width:160px;"><div class="card-body" style="padding:12px 14px;">';
            echo '<div style="font-size:12px;color:#6b7280;"><i class="fas ' . $c[3] . '"></i> ' . $c[0] . '</div>';
            echo '<div style="font-size:24px;font-weight:800;color:' . $c[2] . ';">' . intval($c[1]) . '</div>';
            echo '</div></div>';
        }
        ?>
    </div>

    <?php if ($can_edit): ?>
    <!-- نموذج المعالجة (يظهر عند التحرير) -->
    <form id="finForm" action="" method="post" class="allforms<?php echo $editLine ? ' allforms-visible' : ''; ?>
        <?php echo csrf_field(); ?>">
        <div class="card-header"><h5><i class="fas fa-notes-medical"></i> معالجة انحراف بند الموازنة</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="line_id" value="<?php echo $editLine ? intval($editLine['id']) : ''; ?>">
            <div class="form-section">
                <?php if ($editLine): ?>
                <p style="margin:0 0 10px;color:#374151;">
                    <i class="fas fa-info-circle"></i>
                    الفئة: <strong><?php echo htmlspecialchars($categories[$editLine['category']] ?? $editLine['category']); ?></strong>
                    · الانحراف: <strong><?php echo number_format((float)$editLine['variance'], 2); ?></strong>
                    (<?php echo $editLine['variance_pct'] === null ? '—' : number_format((float)$editLine['variance_pct'], 1) . '%'; ?>)
                </p>
                <?php endif; ?>
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1">
                        <label for="emsf_272_3ca83">سبب الانحراف</label>
                        <input type="text" name="cause" maxlength="200" value="<?php echo htmlspecialchars($editLine['cause'] ?? ''); ?>" placeholder="ما الذي سبّب الفرق بين المخطّط والفعلي؟" id="emsf_272_3ca83">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label for="emsf_273_0f631">الإجراء التصحيحي</label>
                        <input type="text" name="corrective_action" maxlength="200" value="<?php echo htmlspecialchars($editLine['corrective_action'] ?? ''); ?>" placeholder="ما الإجراء المتّخذ لمعالجته؟" id="emsf_273_0f631">
                    </div>
                    <div class="form-group">
                        <label for="emsf_274_dcb83">المسؤول (المالك)</label>
                        <select name="responsible_id" id="emsf_274_dcb83">
                            <?php echo fin_employee_options($conn, $is_super_admin, $company_id, intval($editLine['responsible_id'] ?? 0)); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="emsf_275_a2950">الحالة</label>
                        <select name="var_state" id="emsf_275_a2950">
                            <?php
                            $cur = $editLine['var_state'] ?? 'open';
                            foreach ($var_states as $k => $v) {
                                echo "<option value='" . htmlspecialchars($k) . "'" . ($k === $cur ? ' selected' : '') . ">" . htmlspecialchars($v) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ المعالجة</button>
                <a href="variance_monitor_fin.php" class="btn-secondary"><i class="fas fa-times"></i> إلغاء</a>
            </div>
        </div></div>
    </form>
    <?php endif; ?>

    <div class="card"><div class="card-body">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin:0 0 10px;">
            <h5 style="margin:0"><i class="fas fa-list"></i> انحرافات الموازنات المعتمدة</h5>
            <div class="var-filters" style="display:flex;gap:6px;">
                <?php
                $chips = array('all' => 'الكل', 'breaches' => 'تجاوزات > 10%', 'unresolved' => 'غير المُغلقة');
                foreach ($chips as $k => $lbl) {
                    $active = ($flt === $k) ? ' badge-primary' : ' badge-secondary';
                    echo "<a href='?flt=" . $k . "' class='badge" . $active . "' style='text-decoration:none;padding:6px 12px;'>" . htmlspecialchars($lbl) . "</a>";
                }
                ?>
            </div>
        </div>
        <div class="table-container">
            <table id="varTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <?php if ($can_edit) echo '<th>إجراء المعالجة</th>'; ?>
                    <th>الميزانية</th><th>الإدارة</th><th>الفئة</th><th>النوع</th>
                    <th>الكمية المخططة</th><th>فعلي</th><th>انحراف التنفيذ</th><th>النسبة %</th>
                    <th>سبب انحراف التنفيذ</th><th>الإجراء التصحيحي</th><th>مالك الفجوة</th><th>الحالة</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">الفترة</th>
                    <th class="ems-fn-th" data-fn="1">العقد</th>
                    <th class="ems-fn-th" data-fn="1">الوحدة</th>
                    <th class="ems-fn-th" data-fn="1">المنفَّذة</th>
                    <th class="ems-fn-th" data-fn="1">المفوترة</th>
                    <th class="ems-fn-th" data-fn="1">المحصَّلة</th>
                    <th class="ems-fn-th" data-fn="1">انحراف الفوترة</th>
                    <th class="ems-fn-th" data-fn="1">انحراف التحصيل</th>
                    <th class="ems-fn-th" data-fn="1">تاريخ المتابعة</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th none" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    </tr></thead>
                <tbody>
                    <?php foreach ($var_rows as $l) {
                        $var  = (float)$l['variance'];
                        $kind = $l['line_kind'] === 'revenue' ? 'إيراد' : 'مصروف';
                        // للمصروف: تجاوز (فعلي>مخطّط)=خطر · للإيراد: نقص (فعلي<مخطّط)=خطر
                        $bad  = ($l['line_kind'] === 'expense') ? ($var > 0) : ($var < 0);
                        $tone = ($var == 0) ? 'secondary' : ($bad ? 'danger' : 'success');
                        $pct  = $l['variance_pct'] === null ? '—' : number_format((float)$l['variance_pct'], 1) . '%';
                        $st   = $l['var_state'] ?: 'open';
                        $owner = !empty($l['responsible_id']) ? ($emp_map[intval($l['responsible_id'])] ?? ('#' . intval($l['responsible_id']))) : '—';
                        echo "<tr>";
                        if ($can_edit) {
                            echo "<td><div class='action-btns'><a href='?edit_id=" . intval($l['id']) . "' class='action-btn edit' title='معالجة'><i class='fas fa-notes-medical'></i></a></div></td>";
                        }
                        echo "<td>" . htmlspecialchars((string)$l['budget_no']) . "</td>";
                        echo "<td>" . htmlspecialchars($dept_modules[$l['dept_module']] ?? (string)$l['dept_module']) . "</td>";
                        echo "<td>" . htmlspecialchars($categories[$l['category']] ?? (string)$l['category']) . "</td>";
                        echo "<td>" . $kind . "</td>";
                        echo "<td>" . number_format((float)$l['planned_amount'], 2) . "</td>";
                        echo "<td>" . number_format((float)$l['actual_amount'], 2) . "</td>";
                        echo "<td><span class='badge badge-" . $tone . "'>" . number_format($var, 2) . "</span></td>";
                        echo "<td>" . $pct . "</td>";
                        echo "<td>" . ($l['cause'] !== null && $l['cause'] !== '' ? htmlspecialchars($l['cause']) : "<span style='color:#9ca3af'>—</span>") . "</td>";
                        echo "<td>" . ($l['corrective_action'] !== null && $l['corrective_action'] !== '' ? htmlspecialchars($l['corrective_action']) : "<span style='color:#9ca3af'>—</span>") . "</td>";
                        echo "<td>" . htmlspecialchars($owner) . "</td>";
                        echo "<td><span class='badge badge-" . ($var_tone[$st] ?? 'secondary') . "'>" . htmlspecialchars($var_states[$st] ?? $st) . "</span></td>";
                        echo "</tr>";
                    } ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($var_rows)): ?>
            <p style="color:#6b7280;text-align:center;padding:16px;"><i class="fas fa-circle-info"></i> لا بنودَ مطابقةً للتصفية الحالية. (تظهر بنود الموازنات المعتمدة/النشطة فقط.)</p>
        <?php endif; ?>
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
$(document).ready(function () {
    $('#varTable').DataTable({
        scrollX: true, autoWidth: false, stateSave: false, dom: 'Bfrtip',
        order: [],
        buttons: [ { extend: 'copy', text: '📋 نسخ' }, { extend: 'excel', text: '📊 Excel' }, { extend: 'print', text: '🖨️ طباعة' } ],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" }
    });
});
</script>
</body>
</html>
