<?php
/**
 * Transport/transfer_cost_rules_config.php — إعداد قواعد المتحمِّل (transfer_cost_rules) — §4/§7.2.
 * قاعدة الستين يوماً = صفّان على demob بعتبة 60. محرّك التكلفة يقرأ منها لا من الكود.
 * نمط موحّد — شاشة جديدة مستقلة، لا تلمس أي جدول قائم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = trs_page_perms($conn, 'Transport/transfer_cost_rules_config.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض قواعد المتحمِّل ❌', 'GOV-PERM-403', '');
    exit();
}

$movement_types = trs_movement_types();
$operators      = trs_duration_operators();
$bearers        = trs_bearers();

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['movement_type'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('transfer_cost_rules_config.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('transfer_cost_rules_config.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('transfer_cost_rules_config.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $movement_type = trim($_POST['movement_type'] ?? '');
    $duration_operator = trim($_POST['duration_operator'] ?? 'any');
    $duration_threshold_days = ($_POST['duration_threshold_days'] ?? '') !== '' ? intval($_POST['duration_threshold_days']) : null;
    $default_bearer = trim($_POST['default_bearer'] ?? '');
    $basis_note = trim($_POST['basis_note'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;

    if (!array_key_exists($movement_type, $movement_types) || !array_key_exists($duration_operator, $operators) || !array_key_exists($default_bearer, $bearers)) {
        ems_gov_flash_redirect('transfer_cost_rules_config.php', 'بيانات غير مكتملة ❌', 'GOV-FAIL-409', ''); exit();
    }
    if ($duration_operator === 'any') { $duration_threshold_days = null; }

    $data = array(
        'movement_type' => $movement_type, 'duration_operator' => $duration_operator,
        'duration_threshold_days' => $duration_threshold_days, 'default_bearer' => $default_bearer,
        'basis_note' => $basis_note, 'active' => $active,
    );
    try {
        if ($is_editing) {
            trs_gate(false)->update('transfer_cost_rules', $data, array('id' => $id));
            ems_gov_flash_redirect('transfer_cost_rules_config.php', 'تم تعديل القاعدة بنجاح ✅', 'GOV-OK-200', ''); exit();
        } else {
            trs_gate(false)->insert('transfer_cost_rules', $data);
            ems_gov_flash_redirect('transfer_cost_rules_config.php', 'تمت إضافة القاعدة بنجاح ✅', 'GOV-OK-200', ''); exit();
        }
    } catch (\App\Core\TenantGateException $e) {
        error_log('cost_rules save refused: ' . $e->getMessage());
        ems_gov_flash_redirect('transfer_cost_rules_config.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
}

// ── حذف — سياسة «الأرشفة لا الحذف» (نفس دلالة types الموثقة) ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('transfer_cost_rules_config.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    try {
        trs_gate(false)->softDelete('transfer_cost_rules', $delete_id);
    } catch (\App\Core\TenantGateException $e) {
        error_log('cost_rules softDelete refused: ' . $e->getMessage());
        ems_gov_flash_redirect('transfer_cost_rules_config.php', 'تعذّر الحذف ❌', 'GOV-FAIL-409', ''); exit();
    }
    ems_gov_flash_redirect('transfer_cost_rules_config.php', 'تم حذف القاعدة بنجاح ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | قواعد تحميل تكلفة الترحيل';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main trs-rules-main ems-unified-page-shell">
    <?php
    $header_title = 'إعداد قواعد تحميل تكلفة الترحيل';
    $header_icon  = 'fa fa-scale-balanced';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة قاعدة');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php trs_msg_banner(); ?>

    <div class="success-message is-success" style="background:#fff8e6;color:#8a6d1a;border-color:#e0ae2e">
        <i class="fas fa-circle-info"></i>
        قاعدة الستين يوماً: عودة (demob) لمشروع <b>أقل من 60 يوماً ← العميل</b>، و<b>≥ 60 يوماً ← الشركة</b>. يحسب المحرّك مدة المشروع تلقائياً ويختار الصف الصحيح.
    </div>

    <form id="trsForm" action="" method="post" class="allforms">
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل قاعدة متحمِّل</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="r_id" value="">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label>نوع الحركة <span class="required">*</span></label>
                        <select name="movement_type" id="r_movement" required>
                            <?php foreach ($movement_types as $k => $v): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>عامل المدة <span class="required">*</span></label>
                        <select name="duration_operator" id="r_operator" required>
                            <?php foreach ($operators as $k => $v): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="r_threshold_wrap">
                        <label>عتبة المدة (أيام)</label>
                        <input type="number" name="duration_threshold_days" id="r_threshold" value="" placeholder="مثال: 60">
                    </div>
                    <div class="form-group">
                        <label>المتحمِّل <span class="required">*</span></label>
                        <select name="default_bearer" id="r_bearer" required>
                            <?php foreach ($bearers as $k => $v): ?>
                                <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label>الأساس / ملاحظة</label>
                        <input type="text" name="basis_note" id="r_basis" placeholder="تعاقدي / قاعدة الستين / داخلي ...">
                    </div>
                    <div class="form-group">
                        <label>مفعّلة؟</label>
                        <label class="switch-inline"><input type="checkbox" name="active" id="r_active" value="1" checked> نعم، مفعّلة</label>
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
                    <th>الإجراءات</th><th>نوع الحركة</th><th>عامل المدة</th><th>العتبة (يوم)</th><th>المتحمِّل</th><th>الأساس</th><th>الحالة</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    </tr></thead>
                <tbody>
                    <?php
                    $rule_rows = trs_gate($is_super_admin)->select('transfer_cost_rules', array(
                        'columns' => array('id', 'movement_type', 'duration_operator', 'duration_threshold_days', 'default_bearer', 'basis_note', 'active'),
                        'orderBy' => 'movement_type ASC, duration_operator ASC',
                    ));
                    { foreach ($rule_rows as $row) {
                        $mv_ar = trs_label($movement_types, $row['movement_type']);
                        $op_ar = trs_label($operators, $row['duration_operator']);
                        $bearer_ar = trs_label($bearers, $row['default_bearer']);
                        $thr = $row['duration_threshold_days'] !== null ? intval($row['duration_threshold_days']) : '—';
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-movement='" . htmlspecialchars((string)$row['movement_type'], ENT_QUOTES) . "' " .
                            "data-operator='" . htmlspecialchars((string)$row['duration_operator'], ENT_QUOTES) . "' " .
                            "data-threshold='" . htmlspecialchars((string)($row['duration_threshold_days'] ?? ''), ENT_QUOTES) . "' " .
                            "data-bearer='" . htmlspecialchars((string)$row['default_bearer'], ENT_QUOTES) . "' " .
                            "data-basis='" . htmlspecialchars((string)($row['basis_note'] ?? ''), ENT_QUOTES) . "' " .
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
                        echo "<td>" . htmlspecialchars($mv_ar) . "</td>";
                        echo "<td>" . htmlspecialchars($op_ar) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$thr) . "</td>";
                        echo "<td>" . htmlspecialchars($bearer_ar) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['basis_note'] ?? '—')) . "</td>";
                        echo "<td>" . ((int)$row['active'] === 1 ? "<span class='action-btn' style='color:#1e7e34'>مفعّلة</span>" : "<span class='action-btn' style='color:#c0392b'>معطّلة</span>") . "</td>";
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

        function toggleThreshold() {
            if ($('#r_operator').val() === 'any') { $('#r_threshold_wrap').hide(); $('#r_threshold').val(''); }
            else { $('#r_threshold_wrap').show(); }
        }
        $('#r_operator').on('change', toggleThreshold);
        toggleThreshold();

        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                document.getElementById('trsForm').reset();
                $('#r_id').val('');
                toggleThreshold();
                $('#trsForm').toggleClass('allforms-visible');
            });
        }

        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#r_id').val($t.data('id'));
            $('#r_movement').val($t.data('movement'));
            $('#r_operator').val($t.data('operator'));
            $('#r_threshold').val($t.data('threshold'));
            $('#r_bearer').val($t.data('bearer'));
            $('#r_basis').val($t.data('basis'));
            $('#r_active').prop('checked', String($t.data('active')) === '1');
            toggleThreshold();
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
            $('#r_id').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
