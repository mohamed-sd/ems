<?php
/**
 * Procurement/master_data_proc.php — بيانات مرجعية للمشتريات — §15.
 * يدير على صفحة واحدة: (أ) القيم المرجعية (proc_lookup) + (ب) المخازن (proc_warehouse).
 * نمط موحّد: ترويسة + توبار + DataTables + فورم .allforms + عزل الشركة + حذف ناعم.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

$perms = proc_page_perms($conn, 'Procurement/master_data_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض البيانات المرجعية ❌', 'GOV-PERM-403', '');
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

    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('master_data_proc.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('master_data_proc.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)          { ems_gov_flash_redirect('master_data_proc.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    // ── (أ) قيمة مرجعية ──
    if ($entity === 'lookup') {
        $type  = trim($_POST['type'] ?? '');
        $name  = trim($_POST['name'] ?? '');
        $extra = trim($_POST['extra'] ?? '');

        if (!in_array($type, $lookup_types, true) || $name === '') {
            ems_gov_flash_redirect('master_data_proc.php', 'بيانات غير مكتملة ❌', 'GOV-FAIL-409', ''); exit();
        }

        try {
            if ($is_editing) {
                proc_gate(false)->update('proc_lookup', array('type' => $type, 'name' => $name, 'extra' => $extra), array('id' => $id, 'is_deleted' => 0));
                ems_gov_flash_redirect('master_data_proc.php', 'تم تعديل القيمة المرجعية بنجاح ✅', 'GOV-OK-200', ''); exit();
            } else {
                proc_gate(false)->insert('proc_lookup', array('type' => $type, 'name' => $name, 'extra' => $extra, 'created_by' => $current_user_id));
                ems_gov_flash_redirect('master_data_proc.php', 'تمت إضافة القيمة المرجعية بنجاح ✅', 'GOV-OK-200', ''); exit();
            }
        } catch (\App\Core\TenantGateException $e) {
            error_log('master_data lookup save refused: ' . $e->getMessage());
            ems_gov_flash_redirect('master_data_proc.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
        }
    }

    // ── (ب) مخزن ──
    if ($entity === 'warehouse') {
        $name     = trim($_POST['name'] ?? '');
        $type     = trim($_POST['type'] ?? 'مخزن');
        $location = trim($_POST['location'] ?? '');
        $notes    = trim($_POST['notes'] ?? '');

        if ($name === '' || !in_array($type, $warehouse_types, true)) {
            ems_gov_flash_redirect('master_data_proc.php', 'بيانات غير مكتملة ❌', 'GOV-FAIL-409', ''); exit();
        }

        try {
            if ($is_editing) {
                proc_gate(false)->update('proc_warehouse', array('name' => $name, 'type' => $type, 'location' => $location, 'notes' => $notes), array('id' => $id, 'is_deleted' => 0));
                ems_gov_flash_redirect('master_data_proc.php', 'تم تعديل المخزن بنجاح ✅', 'GOV-OK-200', ''); exit();
            } else {
                proc_gate(false)->insert('proc_warehouse', array(
                    'code' => proc_gen_code($conn, 'proc_warehouse', 'PRC-WH', $company_id),
                    'name' => $name, 'type' => $type, 'location' => $location, 'notes' => $notes,
                    'created_by' => $current_user_id,
                ));
                ems_gov_flash_redirect('master_data_proc.php', 'تمت إضافة المخزن بنجاح ✅', 'GOV-OK-200', ''); exit();
            }
        } catch (\App\Core\TenantGateException $e) {
            error_log('master_data warehouse save refused: ' . $e->getMessage());
            ems_gov_flash_redirect('master_data_proc.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
        }
    }

    ems_gov_flash_redirect('master_data_proc.php', 'كيان غير معروف ❌', 'GOV-FAIL-409', ''); exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم (مقيّد بالشركة)
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_lookup_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('master_data_proc.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_lookup_id']);
    try { proc_gate(false)->softDelete('proc_lookup', $delete_id); }
    catch (\App\Core\TenantGateException $e) { error_log('master_data lookup softDelete refused: ' . $e->getMessage()); }
    ems_gov_flash_redirect('master_data_proc.php', 'تم حذف القيمة المرجعية بنجاح ✅', 'GOV-OK-200', ''); exit();
}

if (isset($_GET['delete_wh_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('master_data_proc.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_wh_id']);
    try { proc_gate(false)->softDelete('proc_warehouse', $delete_id); }
    catch (\App\Core\TenantGateException $e) { error_log('master_data warehouse softDelete refused: ' . $e->getMessage()); }
    ems_gov_flash_redirect('master_data_proc.php', 'تم حذف المخزن بنجاح ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | البيانات المرجعية — المشتريات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01 ②: أنماطُ هذه الشاشةِ الثابتةُ أصنافًا ببادئةِ الشاشة */
.proc-md-table    { width: 100%; }
.proc-md-fullspan { grid-column: 1 / -1; }
</style>

<div class="main proc-master-main ems-unified-page-shell">
    <?php
    $header_title = 'البيانات المرجعية — المشتريات';
    $header_icon  = 'fa fa-sliders';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleLookupForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة قيمة مرجعية');
        $header_actions[] = array('id' => 'toggleWhForm', 'class' => 'add-btn', 'icon' => 'fas fa-warehouse', 'label' => 'إضافة مخزن');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا قيمَ مرجعيةً ولا مخازنَ مسجَّلةً لهذه الشركة',
                           'أضفْ قيمةً مرجعيةً أو مخزنًا بزرَّي الرأسِ — وبها تُبنى بقيةُ شاشاتِ المشتريات');
    ?>

    <?php proc_msg_banner(); ?>

    <!-- ══════════════ (أ) القيم المرجعية ══════════════ -->
    <form id="lookupForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <input type="hidden" name="entity" value="lookup">
        <div class="card-header"><h5><i class="fas fa-list"></i> إضافة / تعديل قيمة مرجعية</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="lk_id" value="">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label for="lk_type">النوع <span class="required">*</span></label>
                    <select name="type" id="lk_type" required>
                        <option value="">— اختر —</option>
                        <?php foreach ($lookup_types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="lk_name">الاسم <span class="required">*</span></label>
                    <input type="text" name="name" id="lk_name" required>
                </div>
                <div class="form-group">
                    <label for="lk_extra">وصف / تفصيل</label>
                    <input type="text" name="extra" id="lk_extra">
                </div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" onclick="procToggleForm('lookupForm')"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="card-header"><h5><i class="fas fa-list"></i> القيم المرجعية</h5></div>
        <div class="form-grid">
            <div class="form-group">
                <label for="filterLookupType">تصفية حسب النوع</label>
                <select id="filterLookupType">
                    <option value="">كل الأنواع</option>
                    <?php foreach ($lookup_types as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="table-container">
            <table id="lookupTable" class="display nowrap alltables proc-md-table"
                   data-scroll-x="1" data-state-save="false">
                <thead><tr>
                    <th>الإجراءات</th><th>نوع المخزن</th><th>اسم المخزن</th><th>وصف / تفصيل</th><th>مفعّل</th>
                </tr></thead>
                <tbody>
                    <?php
                    $lookup_rows = proc_gate($is_super_admin)->select('proc_lookup', array(
                        'columns' => array('id', 'type', 'name', 'extra', 'is_active'),
                        'orderBy' => 'type ASC, name ASC',
                    ));
                    { foreach ($lookup_rows as $row) {
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
        <?= csrf_field() ?>
        <input type="hidden" name="entity" value="warehouse">
        <div class="card-header"><h5><i class="fas fa-warehouse"></i> إضافة / تعديل مخزن</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="wh_id" value="">
            <div class="form-section"><div class="form-grid">
                <div class="form-group">
                    <label for="wh_name">اسم المخزن <span class="required">*</span></label>
                    <input type="text" name="name" id="wh_name" required>
                </div>
                <div class="form-group">
                    <label for="wh_type">النوع <span class="required">*</span></label>
                    <select name="type" id="wh_type" required>
                        <?php foreach ($warehouse_types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="wh_location">الموقع</label>
                    <input type="text" name="location" id="wh_location">
                </div>
                <div class="form-group proc-md-fullspan">
                    <label for="wh_notes">ملاحظات</label>
                    <input type="text" name="notes" id="wh_notes">
                </div>
            </div></div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" onclick="procToggleForm('whForm')"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="card-header"><h5><i class="fas fa-warehouse"></i> المخازن</h5></div>
        <div class="table-container">
            <table id="whTable" class="display nowrap alltables proc-md-table"
                   data-scroll-x="1" data-state-save="false">
                <thead><tr>
                    <th>الإجراءات</th><th>كود المخزن</th><th>الاسم</th><th>النوع</th><th>الموقع الجغرافي</th><th>ملاحظات</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">المواقع الداخلية</th>
                    <th class="ems-fn-th" data-fn="1">أمين المخزن</th>
                    <th class="ems-fn-th" data-fn="1">أسلوب الجرد</th>
                    <th class="ems-fn-th" data-fn="1">دورية الجرد</th>
                    <th class="ems-fn-th" data-fn="1">عهدة مزدوجة؟</th>
                    <th class="ems-fn-th" data-fn="1">ترخيص مطلوب؟</th>
                    <th class="ems-fn-th" data-fn="1">رقم الترخيص</th>
                    <th class="ems-fn-th" data-fn="1">تاريخ انتهائه</th>
                    <th class="ems-fn-th" data-fn="1">ضوابط الصرف</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    </tr></thead>
                <tbody>
                    <?php
                    $wh_rows = proc_gate($is_super_admin)->select('proc_warehouse', array(
                        'columns' => array('id', 'code', 'name', 'type', 'location', 'notes'),
                        'orderBy' => 'name ASC',
                    ));
                    { foreach ($wh_rows as $row) {
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
        // UXW-01 ⑤: لا تهيئةَ محليةً — المكوّنُ المركزيُّ في assets/js/ui-unification.js
        // يلتقط الجدولين ويقرأ سلوكَهما من سماتِ data-* على وسمَي <table>.

        // فلترة القيم المرجعية حسب النوع (العمود 1)
        $('#filterLookupType').on('change', function () {
            var v = this.value ? '^' + $.fn.dataTable.util.escapeRegex(this.value) + '$' : '';
            // النسخةُ من المكوّنِ المركزيِّ — ولا تُنشأ هنا إن لم يكن قد هيّأها بعد
            if (!$.fn.dataTable.isDataTable('#lookupTable')) { return; }
            $('#lookupTable').DataTable().column(1).search(v, true, false).draw();
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
