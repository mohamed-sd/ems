<?php
/**
 * Procurement/suppliers_proc.php — الموردون التشغيليون (proc_supplier).
 * نمط موحّد: ترويسة + توبار + DataTables + فورم .allforms + عزل الشركة + حذف ناعم.
 * شاشة جديدة مستقلة تماماً — لا تلمس أي جدول قائم.
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

$perms = proc_page_perms($conn, 'Procurement/suppliers_proc.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الموردين ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = proc_scope('company_id', $is_super_admin, $company_id);

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('suppliers_proc.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('suppliers_proc.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('suppliers_proc.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $name = trim($_POST['name'] ?? '');
    $supply_role = 'تشغيلي';
    $dealing_nature = trim($_POST['dealing_nature'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $payment_terms = trim($_POST['payment_terms'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($name === '') {
        ems_gov_flash_redirect('suppliers_proc.php', 'بيانات غير مكتملة ❌', 'GOV-FAIL-409', ''); exit();
    }

    // K9-M1: الكتابة عبر البوابة (عزل الشركة والحذف الناعم مسؤوليتها)
    $data = array(
        'name' => $name, 'supply_role' => $supply_role, 'dealing_nature' => $dealing_nature,
        'contact_person' => $contact_person, 'phone' => $phone, 'email' => $email,
        'payment_terms' => $payment_terms, 'address' => $address, 'notes' => $notes,
    );
    try {
        if ($is_editing) {
            proc_gate(false)->update('proc_supplier', $data, array('id' => $id, 'is_deleted' => 0));
            ems_gov_flash_redirect('suppliers_proc.php', 'تم تعديل المورد بنجاح ✅', 'GOV-OK-200', ''); exit();
        } else {
            $data['code'] = proc_gen_code($conn, 'proc_supplier', 'PRC-SUP', $company_id);
            $data['created_by'] = $current_user_id;
            proc_gate(false)->insert('proc_supplier', $data);
            ems_gov_flash_redirect('suppliers_proc.php', 'تمت إضافة المورد بنجاح ✅', 'GOV-OK-200', ''); exit();
        }
    } catch (\App\Core\TenantGateException $e) {
        error_log('suppliers_proc gate refused: ' . $e->getMessage());
        ems_gov_flash_redirect('suppliers_proc.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('suppliers_proc.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    try {
        proc_gate(false)->softDelete('proc_supplier', $delete_id);
    } catch (\App\Core\TenantGateException $e) {
        error_log('suppliers_proc softDelete refused: ' . $e->getMessage());
    }
    ems_gov_flash_redirect('suppliers_proc.php', 'تم حذف المورد بنجاح ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | موردو المشتريات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01 ②: أنماطُ هذه الشاشةِ الثابتةُ أصنافًا ببادئةِ الشاشة */
.proc-sup-table    { width: 100%; }
.proc-sup-fullspan { grid-column: 1 / -1; }
</style>

<div class="main proc-suppliers-main ems-unified-page-shell">
    <?php
    $header_title = 'موردو المشتريات';
    $header_icon  = 'fa fa-truck-field';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة مورد');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا موردَ مسجَّلًا لهذه الشركة',
                           'أضفْ أولَ موردٍ بزرِّ «إضافة مورد» — والمورّدُ شرطُ أمرِ الشراءِ وفاتورتِه');
    ?>

    <?php proc_msg_banner(); ?>

    <!-- فورم إضافة/تعديل -->
    <form id="procForm" action="" method="post" class="allforms">
        <?= csrf_field() ?>
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل مورد</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="p_id" value="">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="p_name">اسم المورد <span class="required">*</span></label>
                        <input type="text" name="name" id="p_name" required>
                    </div>
                    <div class="form-group">
                        <label for="p_dealing">طبيعة التعامل</label>
                        <input type="text" name="dealing_nature" id="p_dealing" placeholder="قطع / زيوت / فلاتر / خدمات إصلاح">
                    </div>
                    <div class="form-group">
                        <label for="p_contact">الشخص المسؤول</label>
                        <input type="text" name="contact_person" id="p_contact">
                    </div>
                    <div class="form-group">
                        <label for="p_phone">الهاتف</label>
                        <input type="text" name="phone" id="p_phone">
                    </div>
                    <div class="form-group">
                        <label for="p_email">البريد الإلكتروني</label>
                        <input type="email" name="email" id="p_email">
                    </div>
                    <div class="form-group">
                        <label for="p_payment">شروط السداد</label>
                        <input type="text" name="payment_terms" id="p_payment">
                    </div>
                    <div class="form-group proc-sup-fullspan">
                        <label for="p_address">العنوان</label>
                        <input type="text" name="address" id="p_address">
                    </div>
                    <div class="form-group proc-sup-fullspan">
                        <label for="p_notes">ملاحظات</label>
                        <input type="text" name="notes" id="p_notes">
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" onclick="procToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="procTable" class="display nowrap alltables proc-sup-table"
                   data-scroll-x="1" data-state-save="false">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>الاسم</th><th>طبيعة التعامل</th>
                    <th>الشخص المسؤول</th><th>الهاتف</th><th>شروط السداد</th>
                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    </tr></thead>
                <tbody>
                    <?php
                    $supplier_rows = proc_gate($is_super_admin)->select('proc_supplier', array(
                        'columns' => array('id', 'code', 'name', 'dealing_nature', 'contact_person', 'phone', 'email', 'payment_terms', 'address', 'notes'),
                        'orderBy' => 'name ASC',
                    ));
                    { foreach ($supplier_rows as $row) {
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' " .
                            "data-dealing='" . htmlspecialchars((string)($row['dealing_nature'] ?? ''), ENT_QUOTES) . "' " .
                            "data-contact='" . htmlspecialchars((string)($row['contact_person'] ?? ''), ENT_QUOTES) . "' " .
                            "data-phone='" . htmlspecialchars((string)($row['phone'] ?? ''), ENT_QUOTES) . "' " .
                            "data-email='" . htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES) . "' " .
                            "data-payment='" . htmlspecialchars((string)($row['payment_terms'] ?? ''), ENT_QUOTES) . "' " .
                            "data-address='" . htmlspecialchars((string)($row['address'] ?? ''), ENT_QUOTES) . "' " .
                            "data-notes='" . htmlspecialchars((string)($row['notes'] ?? ''), ENT_QUOTES) . "'";
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        // M-49: بطاقةُ المورد بتبويباتها السبعة — بدل الجدول المسطّح
                        echo "<a href='supplier_card_proc.php?id=" . intval($row['id']) . "' class='action-btn view' title='البطاقة بتبويباتها السبعة'><i class='fas fa-id-card-clip'></i></a>";
                        if ($can_edit) {
                            echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)($row['code'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['dealing_nature'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['contact_person'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['phone'] ?? '')) . "</td>";
                        echo "<td>" . htmlspecialchars((string)($row['payment_terms'] ?? '')) . "</td>";
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
        // يلتقط الجدولَ ويقرأ سلوكَه من سماتِ data-* على وسمِ <table>.

        var toggleBtn = document.getElementById('toggleForm');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                document.getElementById('procForm').reset();
                $('#p_id').val('');
                $('#procForm').toggleClass('allforms-visible');
            });
        }

        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#p_id').val($t.data('id'));
            $('#p_name').val($t.data('name'));
            $('#p_dealing').val($t.data('dealing'));
            $('#p_contact').val($t.data('contact'));
            $('#p_phone').val($t.data('phone'));
            $('#p_email').val($t.data('email'));
            $('#p_payment').val($t.data('payment'));
            $('#p_address').val($t.data('address'));
            $('#p_notes').val($t.data('notes'));
            $('#procForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#procForm').offset().top }, 400);
        });
    });

    window.procToggleForm = function () {
        var form = $('#procForm');
        if (form.hasClass('allforms-visible')) {
            form.removeClass('allforms-visible').slideUp();
        } else {
            document.getElementById('procForm').reset();
            $('#p_id').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
