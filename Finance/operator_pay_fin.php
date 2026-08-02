<?php
/**
 * Finance/operator_pay_fin.php — وضع دفع المشغّل + معدّل مستحقه (مروحة الأثر §12/§6.1).
 * ───────────────────────────────────────────────────────────────────────────
 * قرار المستخدم: لكل مشغّلٍ وضعٌ يقرّره المدير المالي —
 *   • «بالراتب»  ⇒ تدفعه الرواتب، فالمستحق من المروحة = 0 (لا ازدواج).
 *   • «بالمستحق» ⇒ المروحة تدفعه: ساعات المشغّل × المعدّل (تصنيف overtime).
 * الافتراض «بالراتب» (غياب صفٍّ) — لا يُولَّد مستحقٌّ حتى يُفعَّل مشغّلٌ صراحةً + يُضبط
 * المعدّل. المعدّل حقلٌ فارغٌ يملؤه المدير المالي (يُكتب في fin_effect_map.employee_due).
 * شاشة مستقلة — عزل شركة عبر البوابة + صلاحية §15 (المدير المالي يضبط السياسة).
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
    header("Location: ../login.php?msg=لا+توجد+بيئة+شركة+صالحة+للمستخدم+❌");
    exit();
}

$perms = fin_page_perms($conn, 'Finance/operator_pay_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_edit = $perms['can_edit'];
if (!$can_view) {
    header("Location: ../main/dashboard.php?msg=لا+توجد+صلاحية+عرض+وضع+دفع+المشغّل+❌");
    exit();
}

// ── تبديل وضع مشغّل (بالراتب ⇄ بالمستحق) ──
if (isset($_GET['toggle_emp'])) {
    if (!$can_edit) { header("Location: operator_pay_fin.php?msg=لا+توجد+صلاحية+الضبط+❌"); exit(); }
    $eid  = intval($_GET['toggle_emp']);
    $mode = (($_GET['mode'] ?? '') === 'due') ? 'due' : 'salary';
    $gate = fin_gate($is_super_admin);
    $existing = $gate->selectOne('fin_operator_pay', array('columns' => array('id'), 'where' => array('employee_id' => $eid)));
    if ($existing) {
        $gate->update('fin_operator_pay', array('pay_mode' => $mode), array('id' => intval($existing['id'])));
    } else {
        $gate->insert('fin_operator_pay', array('employee_id' => $eid, 'pay_mode' => $mode, 'created_by' => $current_user_id));
    }
    $lbl = $mode === 'due' ? 'بالمستحق' : 'بالراتب';
    header("Location: operator_pay_fin.php?msg=تم+ضبط+وضع+المشغّل:+$lbl+✅"); exit();
}

// ── حفظ معدّل مستحق المشغّل (يُكتب على صفَّي employee_due) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate'])) {
    if (!$can_edit) { header("Location: operator_pay_fin.php?msg=لا+توجد+صلاحية+الضبط+❌"); exit(); }
    $raw = trim($_POST['rate']);
    if ($raw === '') {
        $rate = 0.0;
    } elseif (!is_numeric($raw) || (float)$raw < 0) {
        header("Location: operator_pay_fin.php?msg=المعدّل+يجب+أن+يكون+رقماً+غير+سالب+❌"); exit();
    } else {
        $rate = round((float)$raw, 4);
    }
    fin_gate($is_super_admin)->update('fin_effect_map', array(
        'param_value' => $rate,
        'is_active'   => $rate > 0 ? 1 : 0,
    ), array('effect_type' => 'employee_due'));
    $msg = $rate > 0
        ? 'تم+ضبط+معدّل+مستحق+المشغّل+وتفعيله+✅'
        : 'تم+إفراغ+المعدّل+—+مستحق+المشغّل+معطّل+الآن+✅';
    header("Location: operator_pay_fin.php?msg=$msg"); exit();
}

// المعدّل الحالي (من صف employee_due)
$cur = fin_gate($is_super_admin)->selectOne('fin_effect_map', array(
    'columns' => array('param_value', 'is_active'),
    'where'   => array('effect_type' => 'employee_due'),
));
$cur_rate   = $cur ? (float)$cur['param_value'] : 0.0;
$cur_active = $cur ? intval($cur['is_active']) === 1 : false;
$cur_rate_display = $cur_rate > 0 ? rtrim(rtrim(number_format($cur_rate, 4, '.', ''), '0'), '.') : '';

// خريطة أوضاع المشغّلين (employee_id → pay_mode)
$mode_map = array();
foreach (fin_gate($is_super_admin)->select('fin_operator_pay', array('columns' => array('employee_id', 'pay_mode'))) as $r) {
    $mode_map[intval($r['employee_id'])] = strval($r['pay_mode']);
}

// المشغّلون (من سجلّ الدوام) — نمط scopedQuery (t نطاق · employees إثراء LEFT)
$operators = fin_gate($is_super_admin)->scopedQuery(
    array('scope' => array('t' => 'timesheet'), 'enrich' => array('e' => 'employees')),
    "SELECT DISTINCT t.employee_id, e.name
     FROM timesheet t LEFT JOIN employees e ON e.id = t.employee_id
     WHERE {TENANT_SCOPE} AND t.employee_id IS NOT NULL
     ORDER BY e.name ASC");

$cnt_due = 0;
foreach ($operators as $op) { if (($mode_map[intval($op['employee_id'])] ?? 'salary') === 'due') { $cnt_due++; } }
$cnt_salary = count($operators) - $cnt_due;

$page_title = 'إيكوبيشن | قواعد مستحقات المشغّلين';
include '../inheader.php';
include '../insidebar.php';
?>

<div class="main fin-oppay-main ems-unified-page-shell">
    <?php
    $header_title = 'قواعد مستحقات المشغّلين';
    $header_icon  = 'fa fa-user-clock';
    $header_actions = array();
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php fin_msg_banner(); ?>

    <div class="card"><div class="card-body">
        <p style="color:#4b5563;margin:0 0 12px;line-height:1.7;">
            <i class="fas fa-circle-info"></i>
            لكل مشغّلٍ وضعٌ تقرّره: <strong>«بالراتب»</strong> (تدفعه الرواتب — لا مستحقَ من محرك الآثار)، أو
            <strong>«بالمستحق»</strong> (محرك الآثار المالية يدفعه: <code>ساعات المشغّل × المعدّل</code>، تصنيف «إضافي»).
            الافتراض «بالراتب» حتى تُفعّل مشغّلًا صراحةً وتضبط المعدّل — فلا يُحتسب رقمٌ قبل ذلك.
        </p>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span class="badge badge-<?php echo $cur_active ? 'success' : 'secondary'; ?>" style="font-size:13px;padding:6px 12px;">
                <i class="fas fa-<?php echo $cur_active ? 'circle-check' : 'circle-pause'; ?>"></i>
                المعدّل <?php echo $cur_active ? 'مفعّل' : 'غير مفعّل (فارغ)'; ?>
            </span>
            <span class="badge badge-info" style="padding:6px 12px;"><?php echo $cnt_due; ?> بالمستحق</span>
            <span class="badge badge-secondary" style="padding:6px 12px;"><?php echo $cnt_salary; ?> بالراتب</span>
        </div>

        <?php if ($can_edit): ?>
        <form action="" method="post" class="allforms allforms-visible" style="box-shadow:none;padding:0;margin-top:10px;">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label>معدّل مستحق المشغّل لكل ساعة (SDG)</label>
                    <input type="number" name="rate" step="0.0001" min="0" value="<?php echo htmlspecialchars($cur_rate_display); ?>" placeholder="فارغ = معطّل — اكتب المعدّل لتفعيله">
                </div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> حفظ المعدّل</button>
            </div>
        </form>
        <?php else: ?>
            <p style="color:#9ca3af;margin-top:8px;"><i class="fas fa-lock"></i> العرض فقط — ضبط السياسة من صلاحية المدير المالي.</p>
        <?php endif; ?>
    </div></div>

    <div class="card"><div class="card-body">
        <h5 style="margin:0 0 10px;"><i class="fas fa-users"></i> المشغّلون وأوضاعهم</h5>
        <div class="table-container">
            <table id="opTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <?php if ($can_edit) echo '<th>تبديل</th>'; ?>
                    <th>المشغّل</th><th>الوضع</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($operators as $op) {
                        $eid  = intval($op['employee_id']);
                        $mode = $mode_map[$eid] ?? 'salary';
                        $isDue = ($mode === 'due');
                        echo "<tr>";
                        if ($can_edit) {
                            $target = $isDue ? 'salary' : 'due';
                            $tlabel = $isDue ? 'اجعله بالراتب' : 'اجعله بالمستحق';
                            $ticon  = $isDue ? 'fa-money-check-dollar' : 'fa-hand-holding-dollar';
                            echo "<td><a href='?toggle_emp=" . $eid . "&mode=" . $target . "' class='badge badge-" . ($isDue ? 'secondary' : 'info') . "' style='text-decoration:none;padding:5px 10px;'><i class='fas " . $ticon . "'></i> " . $tlabel . "</a></td>";
                        }
                        echo "<td>" . htmlspecialchars($op['name'] !== null && $op['name'] !== '' ? $op['name'] : ('#' . $eid)) . "</td>";
                        echo "<td><span class='badge badge-" . ($isDue ? 'success' : 'secondary') . "'>" . ($isDue ? 'بالمستحق' : 'بالراتب') . "</span></td>";
                        echo "</tr>";
                    } ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($operators)): ?>
            <p style="color:#6b7280;text-align:center;padding:16px;"><i class="fas fa-circle-info"></i> لا مشغّلين في سجلّ الدوام بعد.</p>
        <?php endif; ?>
    </div></div>
</div>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    $('#opTable').DataTable({ scrollX: true, autoWidth: false, stateSave: false, pageLength: 25, order: [],
        "language": { "url": "/ems/assets/i18n/datatables/ar.json" } });
});
</script>
</body>
</html>
