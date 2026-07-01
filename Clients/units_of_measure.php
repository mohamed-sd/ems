<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// ══════════════════════════════════════════════════════════════════════════════
// دوال مساعدة
// ══════════════════════════════════════════════════════════════════════════════
if (!function_exists('uom_e')) {
    function uom_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('uom_redirect_with_msg')) {
    function uom_redirect_with_msg($msg)
    {
        header('Location: units_of_measure.php?msg=' . urlencode($msg));
        exit();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// التحقق من معرف الشركة (عزل الشركات)
// ══════════════════════════════════════════════════════════════════════════════
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    header('Location: ../login.php?msg=' . urlencode('الحساب غير مرتبط بشركة.'));
    exit();
}

// شروط النطاق والحذف الناعم
$scope_sql        = "u2.company_id = $company_id";
$scope_update_sql = "company_id = $company_id";
$not_deleted_sql  = "u2.is_deleted = 0";

// رمز CSRF
if (empty($_SESSION['uom_csrf_token'])) {
    $_SESSION['uom_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$uom_csrf_token = $_SESSION['uom_csrf_token'];

// القوائم الثابتة
$UOM_CATEGORIES = array('زمن', 'وزن', 'طول', 'حجم', 'عدد');

// توليد الكود المقترح التالي (UOM-NNNN) — للعرض فقط
$next_uom_code = 'UOM-0001';
$last_code_sql = "SELECT uom_code FROM units_of_measure
                  WHERE uom_code REGEXP '^UOM-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(uom_code, 5) AS UNSIGNED) DESC LIMIT 1";
$last_code_res = @mysqli_query($conn, $last_code_sql);
if ($last_code_res && mysqli_num_rows($last_code_res) > 0) {
    $last_code_row = mysqli_fetch_assoc($last_code_res);
    $last_num = intval(substr($last_code_row['uom_code'], 4));
    $next_uom_code = 'UOM-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة وحدات القياس
$module_query = "SELECT id FROM modules WHERE code = 'Clients/units_of_measure.php' LIMIT 1";
$module_result = $conn->query($module_query);
$module_info = $module_result ? $module_result->fetch_assoc() : null;
$module_id = $module_info ? $module_info['id'] : null;

$can_view = false;
$can_add = false;
$can_edit = false;
$can_delete = false;
if ($module_id) {
    $perms = get_module_permissions($conn, $module_id);
    $can_view   = $perms['can_view'];
    $can_add    = $perms['can_add'];
    $can_edit   = $perms['can_edit'];
    $can_delete = $perms['can_delete'];
}
if (!$can_view) {
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض وحدات القياس ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل وحدة قياس عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($uom_csrf_token, $posted_csrf)) {
        uom_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $uom_id     = isset($_POST['uom_id']) ? intval($_POST['uom_id']) : 0;
    $is_editing = $uom_id > 0;

    if ($is_editing && !$can_edit) {
        uom_redirect_with_msg('لا توجد صلاحية تعديل وحدات القياس ❌');
    } elseif (!$is_editing && !$can_add) {
        uom_redirect_with_msg('لا توجد صلاحية إضافة وحدات قياس جديدة ❌');
    }

    // الكود
    $uom_code_raw = isset($_POST['uom_code']) ? trim($_POST['uom_code']) : '';
    if ($uom_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $uom_code_raw)) {
        uom_redirect_with_msg('كود الوحدة غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // الاسم
    $name_raw = isset($_POST['name']) ? trim($_POST['name']) : '';
    if ($name_raw === '') {
        uom_redirect_with_msg('اسم الوحدة مطلوب ❌');
    }

    // الفئة
    $category_raw = isset($_POST['category']) ? trim($_POST['category']) : 'عدد';
    if (!in_array($category_raw, $UOM_CATEGORIES, true)) {
        $category_raw = 'عدد';
    }

    // معامل التحويل — float (افتراضي 1 إن تُرك فارغاً)
    $factor_raw = isset($_POST['factor']) ? trim($_POST['factor']) : '';
    $factor_sql = ($factor_raw === '') ? "'1'" : "'" . (float) $factor_raw . "'";

    // تنظيف بقية الحقول
    $uom_code   = mysqli_real_escape_string($conn, $uom_code_raw);
    $name       = mysqli_real_escape_string($conn, $name_raw);
    $symbol     = mysqli_real_escape_string($conn, isset($_POST['symbol']) ? trim($_POST['symbol']) : '');
    $category   = mysqli_real_escape_string($conn, $category_raw);
    $notes      = mysqli_real_escape_string($conn, isset($_POST['notes']) ? trim($_POST['notes']) : '');
    $created_by = intval($_SESSION['user']['id']);

    if ($is_editing) {
        $owner = mysqli_query($conn, "SELECT id FROM units_of_measure WHERE id = $uom_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$owner || mysqli_num_rows($owner) === 0) {
            uom_redirect_with_msg('لا يمكنك تعديل وحدة قياس لا تتبع لشركتك ❌');
        }
        $dup = mysqli_query($conn, "SELECT id FROM units_of_measure WHERE uom_code = '$uom_code' AND id != $uom_id AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            uom_redirect_with_msg('كود الوحدة موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE units_of_measure SET
            uom_code = '$uom_code', name = '$name', symbol = '$symbol',
            category = '$category', factor = $factor_sql, notes = '$notes'
            WHERE id = $uom_id AND $scope_update_sql AND is_deleted = 0";

        if (mysqli_query($conn, $update_query)) {
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('units_of_measure', 'units_of_measure', $uom_id, null, ['uom_code' => $uom_code_raw]);
            }
            uom_redirect_with_msg('تم تعديل وحدة القياس بنجاح ✅');
        }
        error_log('units_of_measure.php update failed: ' . mysqli_error($conn));
        uom_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
    } else {
        $dup = mysqli_query($conn, "SELECT id FROM units_of_measure WHERE uom_code = '$uom_code' AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            uom_redirect_with_msg('كود الوحدة موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO units_of_measure
            (company_id, uom_code, name, symbol, category, factor, notes, created_by)
            VALUES
            ('$company_id', '$uom_code', '$name', '$symbol', '$category', $factor_sql, '$notes', '$created_by')";

        if (mysqli_query($conn, $insert_query)) {
            $new_id = (int) mysqli_insert_id($conn);
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('units_of_measure', 'units_of_measure', $new_id, ['uom_code' => $uom_code_raw]);
            }
            uom_redirect_with_msg('تم إضافة وحدة القياس بنجاح ✅');
        }
        error_log('units_of_measure.php insert failed: ' . mysqli_error($conn));
        uom_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

    if (!$can_delete) {
        uom_redirect_with_msg('لا توجد صلاحية حذف وحدات القياس ❌');
    }
    if (empty($delete_csrf) || !hash_equals($uom_csrf_token, $delete_csrf)) {
        uom_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    $chk = mysqli_query($conn, "SELECT id FROM units_of_measure WHERE id = $delete_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        uom_redirect_with_msg('لا يمكنك حذف وحدة قياس لا تتبع لشركتك ❌');
    }
    $deleted_by = intval($_SESSION['user']['id']);
    $del = "UPDATE units_of_measure SET is_deleted = 1, deleted_at = NOW(), deleted_by = $deleted_by
            WHERE id = $delete_id AND $scope_update_sql AND is_deleted = 0";
    if (mysqli_query($conn, $del)) {
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('units_of_measure', 'units_of_measure', $delete_id);
        }
        uom_redirect_with_msg('تم حذف وحدة القياس بنجاح ✅');
    }
    error_log('units_of_measure.php soft delete failed: ' . mysqli_error($conn));
    uom_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

// ══════════════════════════════════════════════════════════════════════════════
// جلب وحدات القياس + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_time = 0;
$stat_weight = 0;
$stat_length = 0;

$q = "SELECT u2.*, u.name AS creator_name
      FROM units_of_measure u2
      LEFT JOIN users u ON u.id = u2.created_by
      WHERE $scope_sql AND $not_deleted_sql
      ORDER BY u2.id DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $stat_total++;
        if ($row['category'] === 'زمن') $stat_time++;
        elseif ($row['category'] === 'وزن') $stat_weight++;
        elseif ($row['category'] === 'طول') $stat_length++;
    }
}

$page_title = "وحدات القياس";
include("../inheader.php");
include('../insidebar.php');
?>

<div class="main uom-main ems-unified-page-shell">

    <?php
    $header_title = 'وحدات القياس';
    $header_icon = 'fas fa-ruler-combined';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'uom-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'uom-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo uom_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section uom-hidden" id="uomStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-ruler-combined"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي الوحدات</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-clock"></i></div>
                <div class="stats-value"><?php echo $stat_time; ?></div>
                <div class="stats-title">وحدات زمن</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-weight-hanging"></i></div>
                <div class="stats-value"><?php echo $stat_weight; ?></div>
                <div class="stats-title">وحدات وزن</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-ruler-horizontal"></i></div>
                <div class="stats-value"><?php echo $stat_length; ?></div>
                <div class="stats-title">وحدات طول</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل وحدة قياس -->
    <form id="uomForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة وحدة قياس جديدة</span></h5>
        </div>
        <input type="hidden" name="uom_id" id="uom_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo uom_e($uom_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label><i class="fas fa-magic"></i> الكود المولد <i class="fas fa-info-circle uom-info-icon"></i></label>
                        <input type="text" id="generated_uom_code" class="generated-code-field" value="<?php echo uom_e($next_uom_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل الكود" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> الكود *</label>
                        <input type="text" name="uom_code" id="uom_code" placeholder="مثال: UOM-001" required pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-heading"></i> اسم الوحدة *</label>
                        <input type="text" name="name" id="name" placeholder="اسم الوحدة" required />
                    </div>
                    <div>
                        <label><i class="fas fa-tag"></i> الرمز</label>
                        <input type="text" name="symbol" id="symbol" placeholder="مثال: س / طن / م" />
                    </div>
                    <div>
                        <label><i class="fas fa-layer-group"></i> الفئة</label>
                        <select name="category" id="category">
                            <?php foreach ($UOM_CATEGORIES as $cat): ?>
                                <option value="<?php echo uom_e($cat); ?>" <?php echo $cat === 'عدد' ? 'selected' : ''; ?>><?php echo uom_e($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-calculator"></i> معامل التحويل</label>
                        <input type="number" step="0.0001" name="factor" id="factor" placeholder="1" value="1" />
                    </div>
                    <div class="uom-col-full">
                        <label><i class="fas fa-note-sticky"></i> ملاحظات</label>
                        <textarea name="notes" id="notes" rows="2" placeholder="أي ملاحظات إضافية"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ الوحدة</span></button>
                    <button type="button" id="uomFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
                </div>
            </div>
        </div>
    </form>

    <div class="filter">
        <div class="filter-title">
            <span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span>
            فلاتر البحث
        </div>
        <div class="filter-body">
            <div class="filter-field">
                <label><i class="fa fa-layer-group"></i> الفئة</label>
                <select id="filterCategory" class="form-control">
                    <option value="">-- كل الفئات --</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-ok"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-reset" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table id="uomTable" class="display uom-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>الاسم</th>
                            <th>الرمز</th>
                            <th>الفئة</th>
                            <th>المعامل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewUomBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo uom_e($row['uom_code']); ?>"
                                            data-name="<?php echo uom_e($row['name']); ?>"
                                            data-symbol="<?php echo uom_e($row['symbol']); ?>"
                                            data-category="<?php echo uom_e($row['category']); ?>"
                                            data-factor="<?php echo $row['factor'] !== null ? uom_e($row['factor']) : ''; ?>"
                                            data-notes="<?php echo uom_e($row['notes']); ?>"
                                            data-created="<?php echo uom_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editUomBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo uom_e($row['uom_code']); ?>"
                                                data-name="<?php echo uom_e($row['name']); ?>"
                                                data-symbol="<?php echo uom_e($row['symbol']); ?>"
                                                data-category="<?php echo uom_e($row['category']); ?>"
                                                data-factor="<?php echo $row['factor'] !== null ? uom_e($row['factor']) : ''; ?>"
                                                data-notes="<?php echo uom_e($row['notes']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($uom_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف وحدة القياس هذه؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="uom-code-cell"><?php echo uom_e($row['uom_code']); ?></strong></td>
                                <td><?php echo uom_e($row['name']); ?></td>
                                <td><?php echo $row['symbol'] !== '' && $row['symbol'] !== null ? uom_e($row['symbol']) : '<span class="uom-muted">—</span>'; ?></td>
                                <td><?php echo uom_e($row['category']); ?></td>
                                <td class="uom-num"><?php echo $row['factor'] !== null ? uom_e($row['factor']) : '<span class="uom-muted">—</span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function () {
        const uomTable = $('#uomTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            uomTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && text !== '—' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(4, '#filterCategory');

        $('#filterCategory').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            uomTable.column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const uomForm = $('#uomForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#uomFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#uomStatsSection');

    function setAddMode() { formTitle.text('إضافة وحدة قياس جديدة'); submitBtnText.text('حفظ الوحدة'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل وحدة القياس'); submitBtnText.text('تحديث الوحدة'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!uomForm.length) return; uomForm[0].reset(); $('#uom_id').val(''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.uom-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(uomForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!uomForm.length) return;
        if (uomForm.is(':visible')) {
            uomForm.stop(true, true).slideUp(250, function () { uomForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            uomForm.addClass('allforms-visible').hide();
            uomForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!uomForm.length || !uomForm.is(':visible')) return;
        uomForm.stop(true, true).slideUp(250, function () { uomForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('uom-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('uom-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillUomForm(d) {
        $('#uom_id').val(d.id);
        $('#uom_code').val(d.code);
        $('#name').val(d.name || '');
        $('#symbol').val(d.symbol || '');
        $('#category').val(d.category || 'عدد');
        $('#factor').val(d.factor || '');
        $('#notes').val(d.notes || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!uomForm.is(':visible')) {
            uomForm.addClass('allforms-visible').hide();
            uomForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#uomForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editUomBtn', function () {
        fillUomForm({
            id: $(this).data('id'), code: $(this).data('code'), name: $(this).data('name'),
            symbol: $(this).data('symbol'), category: $(this).data('category'), factor: $(this).data('factor'),
            notes: $(this).data('notes')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewUomBtn', function () {
        const d = $(this).data();
        const fields = [
            { label: 'الكود', value: d.code, icon: 'fas fa-barcode' },
            { label: 'الاسم', value: d.name || '—', icon: 'fas fa-heading', size: 'lg' },
            { label: 'الرمز', value: (d.symbol !== undefined && d.symbol !== '') ? d.symbol : '—', icon: 'fas fa-tag' },
            { label: 'الفئة', value: d.category || '—', icon: 'fas fa-layer-group' },
            { label: 'معامل التحويل', value: (d.factor !== undefined && d.factor !== '') ? d.factor : '—', icon: 'fas fa-calculator' },
            { label: 'ملاحظات', value: d.notes || '—', icon: 'fas fa-note-sticky', size: 'lg' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل الوحدة', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editUomBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل وحدة القياس', icon: 'fas fa-ruler-combined', fields: fields, actions: actions });
    });
</script>

<style>
    .uom-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .uom-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .uom-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .uom-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .uom-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .uom-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .uom-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .uom-main .stats-grid { grid-template-columns: 1fr; } }

    .uom-main .uom-hidden { display: none; }
    .uom-main .uom-col-full { grid-column: 1 / -1; }
    .uom-main .table-container { overflow-x: auto; }
    #uomTable.uom-table-nowrap, #uomTable.uom-table-nowrap th, #uomTable.uom-table-nowrap td { white-space: nowrap; }
    #uomTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .uom-main .uom-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .uom-main .uom-muted { color: #999; }
</style>

</body>

</html>
