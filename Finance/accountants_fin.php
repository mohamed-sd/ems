<?php
/**
 * Finance/accountants_fin.php — المحاسبون الموزّعون والوحدات المالية — §2.1/§3.3/§3.4.
 * الوحدات المالية (fin_units) + محاسب لكل إدارة بتبعيّة مزدوجة (fin_accountants).
 * شاشة مستقلة — عزل شركة + حذف ناعم. الموظفون يُقرأون قراءةً فقط من employees.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) { header("Location: ../login.php"); exit(); }
include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/fin_helpers.php';

$ctx = fin_ctx();
$is_super_admin = $ctx['is_super']; $company_id = $ctx['company_id']; $current_user_id = $ctx['user_id'];
if (!$is_super_admin && $company_id <= 0) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة ❌', 'GOV-SCOPE-403', ''); exit(); }

$perms = fin_page_perms($conn, 'Finance/accountants_fin.php', $is_super_admin);
$can_view = $perms['can_view']; $can_add = $perms['can_add']; $can_edit = $perms['can_edit']; $can_delete = $perms['can_delete'];
if (!$can_view) { ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المحاسبين ❌', 'GOV-PERM-403', ''); exit(); }

$company_scope_sql = fin_scope('company_id', $is_super_admin, $company_id);
$modules_lbl = fin_source_modules();

// ── حفظ وحدة مالية ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unit_code'])) {
    if (!$can_add) { ems_gov_flash_redirect('accountants_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    $code = trim($_POST['unit_code'] ?? ''); $name = trim($_POST['unit_name'] ?? ''); $note = trim($_POST['role_note'] ?? '');
    if ($code === '' || $name === '') { ems_gov_flash_redirect('accountants_fin.php', 'بيانات الوحدة غير مكتملة ❌', 'GOV-FAIL-409', ''); exit(); }
    try {
        fin_gate($is_super_admin)->insert('fin_units', array(
            'code' => $code, 'name' => $name, 'role_note' => $note, 'created_by' => $current_user_id,
        ));
    } catch (\App\Core\TenantGateException $e) {
        // نمط 1062 المعتمد (M2b): التكرار برسالته، وغيره يُسجَّل
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) { ems_gov_flash_redirect('accountants_fin.php', 'كود الوحدة مكرر ❌', 'GOV-FAIL-409', ''); exit(); }
        error_log('fin_units insert refused: ' . $e->getMessage());
        ems_gov_flash_redirect('accountants_fin.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
    ems_gov_flash_redirect('accountants_fin.php', 'تمت إضافة الوحدة ✅', 'GOV-OK-200', ''); exit();
}

// ── حفظ محاسب إدارة ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_module'])) {
    if (!$can_add) { ems_gov_flash_redirect('accountants_fin.php', 'لا توجد صلاحية إضافة ❌', 'GOV-PERM-403', ''); exit(); }
    $emp = intval($_POST['employee_id'] ?? 0);
    $mod = isset($modules_lbl[$_POST['admin_module'] ?? '']) ? $_POST['admin_module'] : '';
    $unit = intval($_POST['finance_unit_id'] ?? 0);
    $spec = trim($_POST['specialization'] ?? '');
    $limit = ($_POST['review_limit_usd'] ?? '') === '' ? null : round(floatval($_POST['review_limit_usd']), 2);
    if ($emp <= 0 || $mod === '' || $unit <= 0) { ems_gov_flash_redirect('accountants_fin.php', 'بيانات المحاسب غير مكتملة ❌', 'GOV-FAIL-409', ''); exit(); }
    try {
        fin_gate($is_super_admin)->insert('fin_accountants', array(
            'employee_id' => $emp, 'admin_module' => $mod, 'finance_unit_id' => $unit,
            'specialization' => $spec, 'review_limit_usd' => $limit, 'created_by' => $current_user_id,
        ));
    } catch (\App\Core\TenantGateException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) { ems_gov_flash_redirect('accountants_fin.php', 'المحاسب مسند لهذه الإدارة مسبقا ❌', 'GOV-FAIL-409', ''); exit(); }
        error_log('fin_accountants insert refused: ' . $e->getMessage());
        ems_gov_flash_redirect('accountants_fin.php', 'حدث خطأ أثناء الحفظ ❌', 'GOV-FAIL-409', ''); exit();
    }
    ems_gov_flash_redirect('accountants_fin.php', 'تمت إضافة المحاسب ✅', 'GOV-OK-200', ''); exit();
}

// ── حذف ناعم ──
if (isset($_GET['del_unit'])) {
    if (!$can_delete) { ems_gov_flash_redirect('accountants_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $d = intval($_GET['del_unit']);
    fin_gate($is_super_admin)->softDelete('fin_units', $d);
    ems_gov_flash_redirect('accountants_fin.php', 'تم حذف الوحدة ✅', 'GOV-OK-200', ''); exit();
}
if (isset($_GET['del_acct'])) {
    if (!$can_delete) { ems_gov_flash_redirect('accountants_fin.php', 'لا توجد صلاحية حذف ❌', 'GOV-PERM-403', ''); exit(); }
    $d = intval($_GET['del_acct']);
    fin_gate($is_super_admin)->softDelete('fin_accountants', $d);
    ems_gov_flash_redirect('accountants_fin.php', 'تم حذف المحاسب ✅', 'GOV-OK-200', ''); exit();
}

$page_title = 'إيكوبيشن | المحاسبون والوحدات';
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<style>
/* UXW-01 ٢: أنماطُ هذه الشاشةِ الثابتةُ صارتْ أصنافًا ببادئةِ الشاشة */
.fin-acct-wide { grid-column: 1 / -1; }
.fin-acct-h5 { margin: 0 0 10px; }
.fin-acct-h5-next { margin: 18px 0 10px; }
.fin-acct-tbl { width: 100%; }
</style>
<div class="main fin-acct-main ems-unified-page-shell">
    <?php
    $header_title = 'المحاسبون والوحدات المالية'; $header_icon = 'fa fa-users-gear';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleUnit', 'class' => 'add-btn', 'icon' => 'fas fa-plus-circle', 'label' => 'وحدة مالية');
        $header_actions[] = array('id' => 'toggleAcct', 'class' => 'add-btn', 'icon' => 'fas fa-user-plus', 'label' => 'محاسب إدارة');
    }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    // UXW-01 ٩: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا وحدات مالية ولا محاسبي إدارات مسجلين بعد', 'أنشئ وحدة بزر «وحدة مالية» ثم اربط بها محاسبا بزر «محاسب إدارة»');
    ?>
    <?php fin_msg_banner(); ?>

    <form id="unitForm" action="" method="post" class="allforms">
        <?php echo csrf_field(); ?>
        <div class="card-header"><h5><i class="fas fa-building-columns"></i> وحدة مالية</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label for="emsf_320_a5811">الكود <span class="required">*</span></label><input type="text" name="unit_code" required placeholder="gl / ar / ap / treasury" id="emsf_320_a5811"></div>
            <div class="form-group"><label for="emsf_321_37a48">الاسم <span class="required">*</span></label><input type="text" name="unit_name" required id="emsf_321_37a48"></div>
            <div class="form-group fin-acct-wide"><label for="emsf_322_9e127">دور الوحدة</label><input type="text" name="role_note" id="emsf_322_9e127"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-secondary" onclick="$('#unitForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <form id="acctForm" action="" method="post" class="allforms">
        <?php echo csrf_field(); ?>
        <div class="card-header"><h5><i class="fas fa-user-tie"></i> محاسب إدارة (تبعية مزدوجة)</h5></div>
        <div class="card"><div class="card-body"><div class="form-section"><div class="form-grid">
            <div class="form-group"><label for="emsf_323_77a22">الموظف المحاسب <span class="required">*</span></label><select name="employee_id" required id="emsf_323_77a22"><?php echo fin_employee_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label for="emsf_324_ffb09">الإدارة المتبوعة <span class="required">*</span></label><select name="admin_module" required id="emsf_324_ffb09"><option value="">— اختر —</option><?php foreach ($modules_lbl as $k => $v) echo "<option value='" . htmlspecialchars($k) . "'>" . htmlspecialchars($v) . "</option>"; ?></select></div>
            <div class="form-group"><label for="emsf_325_34494">الوحدة المالية <span class="required">*</span></label><select name="finance_unit_id" required id="emsf_325_34494"><?php echo fin_unit_options($conn, $is_super_admin, $company_id); ?></select></div>
            <div class="form-group"><label for="emsf_326_01cdf">التخصص</label><input type="text" name="specialization" placeholder="مبيعات/موردين/قوى..." id="emsf_326_01cdf"></div>
            <div class="form-group"><label for="emsf_327_122d1">حد المراجعة (USD)</label><input type="number" step="0.01" name="review_limit_usd" id="emsf_327_122d1"></div>
        </div></div>
        <div class="form-actions"><button type="submit" class="btn-primary"><i class="fas fa-save"></i> حفظ</button>
            <button type="button" class="btn-secondary" onclick="$('#acctForm').removeClass('allforms-visible')">إلغاء</button></div>
        </div></div>
    </form>

    <div class="card"><div class="card-body">
        <h5 class="fin-acct-h5"><i class="fas fa-building-columns"></i> الوحدات المالية الداخلية</h5>
        <div class="table-container">
            <table id="uTable" class="display nowrap alltables fin-acct-tbl" data-scroll-x="1" data-state-save="false">
                <thead><tr><th>الإجراءات</th><th>الكود</th><th>الاسم</th><th>الدور</th></tr></thead>
                <tbody>
                <?php
                $unit_rows = fin_gate($is_super_admin)->select('fin_units', array('orderBy' => 'code ASC'));
                { foreach ($unit_rows as $row) {
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) echo "<a href='?del_unit=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td><strong>" . htmlspecialchars((string)$row['code']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars((string)$row['name']) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['role_note'] ?? '—')) . "</td>";
                    echo "</tr>";
                } }
                ?>
                </tbody>
            </table>
        </div>

        <h5 class="fin-acct-h5-next"><i class="fas fa-user-tie"></i> محاسبو الإدارات (النموذج الموزع)</h5>
        <div class="table-container">
            <table id="aTable" class="display nowrap alltables fin-acct-tbl" data-scroll-x="1" data-state-save="false">
                <thead><tr><th>الإجراءات</th><th>المحاسب</th><th>الإدارة المتبوعة</th><th>الوحدة المالية</th><th>التخصص</th><th>حد المراجعة</th>
              <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
              <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
              <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المنشئ — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمد — الاسم والصفة</th>
              <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمد — تفويض أو سلطة أصلية">مرجع التفويض</th>
              <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
              <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
              <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
              <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
              </tr></thead>
                <tbody>
                <?php
                $acct_rows = fin_gate($is_super_admin)->scopedQuery(
                    array('scope' => array('a' => 'fin_accountants'),
                          'enrich' => array('e' => 'employees', 'u' => 'fin_units')),
                    "SELECT a.*, e.name AS emp_name, u.code AS unit_code, u.name AS unit_name
                     FROM fin_accountants a
                     LEFT JOIN employees e ON e.id = a.employee_id
                     LEFT JOIN fin_units u ON u.id = a.finance_unit_id
                     WHERE {TENANT_SCOPE} AND COALESCE(a.is_deleted,0)=0
                     ORDER BY a.admin_module ASC");
                { foreach ($acct_rows as $row) {
                    echo "<tr><td><div class='action-btns'>";
                    if ($can_delete) echo "<a href='?del_acct=" . intval($row['id']) . "' class='action-btn delete' onclick='return confirm(\"حذف؟\")' title='حذف'><i class='fas fa-trash-alt'></i></a>";
                    echo "</div></td>";
                    echo "<td>" . htmlspecialchars((string)($row['emp_name'] ?? ('#' . $row['employee_id']))) . "</td>";
                    echo "<td><span class='badge badge-primary'>" . htmlspecialchars($modules_lbl[$row['admin_module']] ?? $row['admin_module']) . "</span></td>";
                    echo "<td>" . htmlspecialchars((string)($row['unit_code'] ? $row['unit_code'] . ' — ' . $row['unit_name'] : '—')) . "</td>";
                    echo "<td>" . htmlspecialchars((string)($row['specialization'] ?? '—')) . "</td>";
                    echo "<td>" . ($row['review_limit_usd'] !== null ? number_format((float)$row['review_limit_usd'], 2) . ' USD' : '—') . "</td>";
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
$(document).ready(function () {
    // جداولُ العرضِ يهيّئُها المكوّنُ المركزيُّ (assets/js/ui-unification.js)
    $('#toggleUnit').on('click', function () { $('#unitForm').toggleClass('allforms-visible'); });
    $('#toggleAcct').on('click', function () { $('#acctForm').toggleClass('allforms-visible'); });
});
</script>
</body>
</html>
