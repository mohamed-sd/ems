<?php
/**
 * Finance/accounts_fin.php — دليل الحسابات (fin_chart_of_accounts) — §3.2 / §4.
 * شجرة حسابات ذاتية المرجع (أصول/خصوم/حقوق/إيراد/مصروف). CRUD + عزل شركة + حذف ناعم.
 * شاشة جديدة مستقلة — لا تلمس أي جدول قائم.
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

$perms = fin_page_perms($conn, 'Finance/accounts_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add'];
$can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض دليل الحسابات ❌', 'GOV-PERM-403', '');
    exit();
}

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$account_types = fin_account_types();

// ── حفظ (إضافة/تعديل) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $is_editing = $id > 0;
    if ($is_editing && !$can_edit) { ems_gov_flash_redirect('accounts_fin.php', 'لا توجد صلاحية تعديل ❌', 'GOV-PERM-403', ''); exit(); }
    if (!$is_editing && !$can_add) { ems_gov_flash_redirect('accounts_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    if ($company_id <= 0)         { ems_gov_flash_redirect('accounts_fin.php', 'لا يمكن الحفظ بلا شركة صالحة ❌', 'GOV-FAIL-409', ''); exit(); }

    $code         = trim($_POST['code'] ?? '');
    $name         = trim($_POST['name'] ?? '');
    $account_type = trim($_POST['account_type'] ?? '');
    $parent_id    = intval($_POST['parent_id'] ?? 0) ?: null;
    $is_postable  = isset($_POST['is_postable']) ? 1 : 0;

    if ($code === '' || $name === '' || !isset($account_types[$account_type])) {
        ems_gov_flash_redirect('accounts_fin.php', 'بيانات غير مكتملة (كود/اسم/نوع) ❌', 'GOV-FAIL-409', ''); exit();
    }
    // منع أن يكون الحساب أباً لنفسه
    if ($is_editing && $parent_id === $id) { $parent_id = null; }

    if ($is_editing) {
        fin_gate($is_super_admin)->update('fin_chart_of_accounts', array(
            'code' => $code, 'name' => $name, 'account_type' => $account_type,
            'parent_id' => $parent_id, 'is_postable' => $is_postable,
        ), array('id' => $id), "COALESCE(is_deleted,0)=0");
        ems_gov_flash_redirect('accounts_fin.php', 'تم تعديل الحساب بنجاح ✅', 'GOV-OK-200', ''); exit();
    } else {
        // insert عبر البوابة؛ التكرار 1062 يظهر TenantGateException (نمط trs_notify)
        try {
            fin_gate($is_super_admin)->insert('fin_chart_of_accounts', array(
                'code' => $code, 'name' => $name, 'account_type' => $account_type,
                'parent_id' => $parent_id, 'is_postable' => $is_postable, 'created_by' => $current_user_id,
            ));
        } catch (\App\Core\TenantGateException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                ems_gov_flash_redirect('accounts_fin.php', 'رقم الحساب مكرر في الشركة ❌', 'GOV-FAIL-409', ''); exit();
            }
            error_log('accounts_fin insert refused: ' . $e->getMessage());
            ems_gov_flash_redirect('accounts_fin.php', 'تعذّرت الإضافة ❌', 'GOV-FAIL-409', ''); exit();
        }
        ems_gov_flash_redirect('accounts_fin.php', 'تمت إضافة الحساب بنجاح ✅', 'GOV-OK-200', ''); exit();
    }
}

// ── حذف ناعم ──
if (isset($_GET['delete_id'])) {
    if (!$can_delete) { ems_gov_flash_redirect('accounts_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $delete_id = intval($_GET['delete_id']);
    try { fin_gate($is_super_admin)->softDelete('fin_chart_of_accounts', $delete_id); }
    catch (\App\Core\TenantGateException $e) { error_log('accounts_fin softDelete refused: ' . $e->getMessage()); }
    ems_gov_flash_redirect('accounts_fin.php', 'تم حذف الحساب بنجاح ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | دليل الحسابات';
// CM-00 (DEC-E · U10): بذرُ محاورِ الغلافِ من الخادم — AX-2/3 من محرك الصلاحيات
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : (isset($permissions) ? $permissions : null));
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<div class="main fin-accounts-main ems-unified-page-shell">
    <?php
    $header_title = 'دليل الحسابات';
    $header_icon  = 'fa fa-sitemap';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة حساب');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php fin_msg_banner(); ?>

    <form id="finForm" action="" method="post" class="allforms">
        <?php echo csrf_field(); ?>
        <div class="card-header"><h5><i class="fas fa-edit"></i> إضافة / تعديل حساب</h5></div>
        <div class="card"><div class="card-body">
            <input type="hidden" name="id" id="a_id" value="">
            <div class="form-section">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="a_code">رقم الحساب <span class="required">*</span></label>
                        <input type="text" name="code" id="a_code" required placeholder="مثال: 1100">
                    </div>
                    <div class="form-group">
                        <label for="a_name">اسم الحساب <span class="required">*</span></label>
                        <input type="text" name="name" id="a_name" required>
                    </div>
                    <div class="form-group">
                        <label for="a_type">نوع الحساب <span class="required">*</span></label>
                        <select name="account_type" id="a_type" required>
                            <option value="">— اختر —</option>
                            <?php foreach ($account_types as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="a_parent">الحساب الأب</label>
                        <select name="parent_id" id="a_parent"><?php echo fin_account_parent_options($conn, $is_super_admin, $company_id); ?></select>
                    </div>
                    <div class="form-group">
                        <label>يقبل القيد المباشر</label>
                        <label class="switch-inline"><input type="checkbox" name="is_postable" id="a_postable" value="1" checked> نعم</label>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
                <button type="button" class="btn-secondary" onclick="finToggleForm()"><i class="fas fa-times"></i> إلغاء</button>
            </div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <div class="table-container">
            <table id="finTable" class="display nowrap alltables no-datatable" style="width:100%;">
                <thead><tr>
                    <th>الإجراءات</th><th>الكود</th><th>اسم الحساب</th><th>نوع الحركة</th><th>الحساب الأب</th><th>يقبل القيد</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">رقم الحركة</th>
                    <th class="ems-fn-th" data-fn="1">التاريخ</th>
                    <th class="ems-fn-th" data-fn="1">الصندوق أو البنك</th>
                    <th class="ems-fn-th" data-fn="1">الوصف</th>
                    <th class="ems-fn-th" data-fn="1">المرجع</th>
                    <th class="ems-fn-th" data-fn="1">المبلغ</th>
                    <th class="ems-fn-th" data-fn="1">المعادل</th>
                    <th class="ems-fn-th" data-fn="1">الرصيد قبل</th>
                    <th class="ems-fn-th" data-fn="1">الرصيد بعد</th>
                    <th class="ems-fn-th" data-fn="1">المستفيد أو الدافع</th>
                    <th class="ems-fn-th" data-fn="1">أمين الخزينة</th>
                    <th class="ems-fn-th" data-fn="1">رقم القيد</th>
                    <th class="ems-fn-th" data-fn="1">نسخة القاعدة المستعملة</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                    <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                    <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                    <th class="ems-gov-th none" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                    <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                    <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                    <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                    <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                    <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                    <th class="ems-gov-th none" data-gov="currency" data-slice="3" title="لا مبلغ بلا عملة">العملة</th>
                    <th class="ems-gov-th none" data-gov="fx_rate" data-slice="3" title="سعر التحويل لعملة الدفاتر">سعر الصرف</th>
                    </tr></thead>
                <tbody>
                    <?php
                    // scopedQuery (§10): self-JOIN للأب عبر إثراء LEFT بنفس الجدول
                    $acc_rows = fin_gate($is_super_admin)->scopedQuery(
                        array('scope' => array('a' => 'fin_chart_of_accounts'),
                              'enrich' => array('pa' => 'fin_chart_of_accounts')),
                        "SELECT a.*, pa.code AS parent_code, pa.name AS parent_name
                         FROM fin_chart_of_accounts a
                         LEFT JOIN fin_chart_of_accounts pa ON pa.id = a.parent_id
                         WHERE {TENANT_SCOPE} AND COALESCE(a.is_deleted,0)=0 ORDER BY a.code ASC");
                    { foreach ($acc_rows as $row) {
                        $t = (string)$row['account_type'];
                        $parent_lbl = $row['parent_code'] ? ($row['parent_code'] . ' — ' . $row['parent_name']) : '—';
                        $data_attrs =
                            "data-id='" . intval($row['id']) . "' " .
                            "data-code='" . htmlspecialchars((string)$row['code'], ENT_QUOTES) . "' " .
                            "data-name='" . htmlspecialchars((string)$row['name'], ENT_QUOTES) . "' " .
                            "data-type='" . htmlspecialchars($t, ENT_QUOTES) . "' " .
                            "data-parent='" . intval($row['parent_id']) . "' " .
                            "data-postable='" . intval($row['is_postable']) . "'";
                        echo "<tr>";
                        echo "<td><div class='action-btns'>";
                        if ($can_edit) {
                            echo "<a href='javascript:void(0)' class='editBtn action-btn edit' $data_attrs title='تعديل'><i class='fas fa-edit'></i></a>";
                        }
                        if ($can_delete) {
                            echo "<a href='?delete_id=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"هل أنت متأكد من الحذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                        }
                        echo "</div></td>";
                        echo "<td>" . htmlspecialchars((string)$row['code']) . "</td>";
                        echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                        echo "<td><span class='badge badge-" . fin_account_type_tone($t) . "'>" . htmlspecialchars($account_types[$t] ?? $t) . "</span></td>";
                        echo "<td>" . htmlspecialchars((string)$parent_lbl) . "</td>";
                        echo "<td>" . ($row['is_postable'] ? '✔' : '—') . "</td>";
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
        $('#finTable').DataTable({
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
                document.getElementById('finForm').reset();
                $('#a_id').val('');
                $('#finForm').toggleClass('allforms-visible');
            });
        }

        $(document).on('click', '.editBtn', function () {
            var $t = $(this);
            $('#a_id').val($t.data('id'));
            $('#a_code').val($t.data('code'));
            $('#a_name').val($t.data('name'));
            $('#a_type').val($t.data('type'));
            $('#a_parent').val(String($t.data('parent')));
            $('#a_postable').prop('checked', String($t.data('postable')) === '1');
            $('#finForm').addClass('allforms-visible');
            $('html, body').animate({ scrollTop: $('#finForm').offset().top }, 400);
        });
    });

    window.finToggleForm = function () {
        var form = $('#finForm');
        if (form.hasClass('allforms-visible')) {
            form.removeClass('allforms-visible').slideUp();
        } else {
            document.getElementById('finForm').reset();
            $('#a_id').val('');
            form.addClass('allforms-visible').slideDown();
        }
    };
})();
</script>
</body>
</html>
