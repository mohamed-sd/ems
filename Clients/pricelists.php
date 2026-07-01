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
if (!function_exists('pl_e')) {
    function pl_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('pl_money')) {
    function pl_money($value)
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, 2);
    }
}
if (!function_exists('pl_redirect_with_msg')) {
    function pl_redirect_with_msg($msg)
    {
        header('Location: pricelists.php?msg=' . urlencode($msg));
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
$scope_sql        = "p.company_id = $company_id";
$scope_update_sql = "company_id = $company_id";
$not_deleted_sql  = "p.is_deleted = 0";

// رمز CSRF
if (empty($_SESSION['pl_csrf_token'])) {
    $_SESSION['pl_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$pl_csrf_token = $_SESSION['pl_csrf_token'];

// القوائم الثابتة
$PL_CURRENCIES = array('USD', 'SDG');
$PL_REVENUE_MODELS = array(
    'hourly' => 'تأجير بالساعة',
    'ton'    => 'نقل بالطن',
    'meter'  => 'تخريم بالمتر',
);

// توليد الكود المقترح التالي (PL-NNNN) — للعرض فقط
$next_pl_code = 'PL-0001';
$last_code_sql = "SELECT pricelist_code FROM pricelists
                  WHERE pricelist_code REGEXP '^PL-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(pricelist_code, 4) AS UNSIGNED) DESC LIMIT 1";
$last_code_res = @mysqli_query($conn, $last_code_sql);
if ($last_code_res && mysqli_num_rows($last_code_res) > 0) {
    $last_code_row = mysqli_fetch_assoc($last_code_res);
    $last_num = intval(substr($last_code_row['pricelist_code'], 3));
    $next_pl_code = 'PL-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة قوائم الأسعار
$module_query = "SELECT id FROM modules WHERE code = 'Clients/pricelists.php' LIMIT 1";
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
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض قوائم الأسعار ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل قائمة أسعار عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($pl_csrf_token, $posted_csrf)) {
        pl_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $pl_id      = isset($_POST['pl_id']) ? intval($_POST['pl_id']) : 0;
    $is_editing = $pl_id > 0;

    if ($is_editing && !$can_edit) {
        pl_redirect_with_msg('لا توجد صلاحية تعديل قوائم الأسعار ❌');
    } elseif (!$is_editing && !$can_add) {
        pl_redirect_with_msg('لا توجد صلاحية إضافة قوائم أسعار جديدة ❌');
    }

    // الكود
    $pl_code_raw = isset($_POST['pricelist_code']) ? trim($_POST['pricelist_code']) : '';
    if ($pl_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $pl_code_raw)) {
        pl_redirect_with_msg('كود قائمة الأسعار غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // الاسم
    $name_raw = isset($_POST['name']) ? trim($_POST['name']) : '';
    if ($name_raw === '') {
        pl_redirect_with_msg('اسم قائمة الأسعار مطلوب ❌');
    }

    // العملة
    $currency_raw = isset($_POST['currency']) ? trim($_POST['currency']) : 'USD';
    if (!in_array($currency_raw, $PL_CURRENCIES, true)) {
        $currency_raw = 'USD';
    }

    // نموذج الإيراد (اختياري)
    $rev_raw = isset($_POST['revenue_model']) ? trim($_POST['revenue_model']) : '';
    if ($rev_raw !== '' && !isset($PL_REVENUE_MODELS[$rev_raw])) {
        pl_redirect_with_msg('نموذج التسعير غير صالح ❌');
    }
    $rev_sql = $rev_raw !== '' ? "'" . mysqli_real_escape_string($conn, $rev_raw) . "'" : 'NULL';

    // السعر الأساس
    $base_price_raw = isset($_POST['base_price']) ? trim($_POST['base_price']) : '';
    $base_price_sql = ($base_price_raw === '') ? '0' : "'" . (float) $base_price_raw . "'";

    // المعاملات الرقمية — NULL إن تُركت فارغة، وإلا float
    $factor_cols = array('distance_factor', 'shift_factor', 'volume_factor', 'duration_factor');
    $factor_sql = array();
    foreach ($factor_cols as $fc) {
        $val = isset($_POST[$fc]) ? trim($_POST[$fc]) : '';
        $factor_sql[$fc] = ($val === '') ? 'NULL' : "'" . (float) $val . "'";
    }

    // تنظيف بقية الحقول
    $pricelist_code = mysqli_real_escape_string($conn, $pl_code_raw);
    $name           = mysqli_real_escape_string($conn, $name_raw);
    $currency       = mysqli_real_escape_string($conn, $currency_raw);
    $notes          = mysqli_real_escape_string($conn, isset($_POST['notes']) ? trim($_POST['notes']) : '');
    $created_by     = intval($_SESSION['user']['id']);

    if ($is_editing) {
        $owner = mysqli_query($conn, "SELECT id FROM pricelists WHERE id = $pl_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$owner || mysqli_num_rows($owner) === 0) {
            pl_redirect_with_msg('لا يمكنك تعديل قائمة أسعار لا تتبع لشركتك ❌');
        }
        $dup = mysqli_query($conn, "SELECT id FROM pricelists WHERE pricelist_code = '$pricelist_code' AND id != $pl_id AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            pl_redirect_with_msg('كود قائمة الأسعار موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE pricelists SET
            pricelist_code = '$pricelist_code', name = '$name', currency = '$currency',
            revenue_model = $rev_sql, base_price = $base_price_sql,
            distance_factor = {$factor_sql['distance_factor']}, shift_factor = {$factor_sql['shift_factor']},
            volume_factor = {$factor_sql['volume_factor']}, duration_factor = {$factor_sql['duration_factor']},
            notes = '$notes'
            WHERE id = $pl_id AND $scope_update_sql AND is_deleted = 0";

        if (mysqli_query($conn, $update_query)) {
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('pricelists', 'pricelists', $pl_id, null, ['pricelist_code' => $pl_code_raw]);
            }
            pl_redirect_with_msg('تم تعديل قائمة الأسعار بنجاح ✅');
        }
        error_log('pricelists.php update failed: ' . mysqli_error($conn));
        pl_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
    } else {
        $dup = mysqli_query($conn, "SELECT id FROM pricelists WHERE pricelist_code = '$pricelist_code' AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            pl_redirect_with_msg('كود قائمة الأسعار موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO pricelists
            (company_id, pricelist_code, name, currency, revenue_model, base_price,
             distance_factor, shift_factor, volume_factor, duration_factor, notes, created_by)
            VALUES
            ('$company_id', '$pricelist_code', '$name', '$currency', $rev_sql, $base_price_sql,
             {$factor_sql['distance_factor']}, {$factor_sql['shift_factor']}, {$factor_sql['volume_factor']}, {$factor_sql['duration_factor']}, '$notes', '$created_by')";

        if (mysqli_query($conn, $insert_query)) {
            $new_id = (int) mysqli_insert_id($conn);
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('pricelists', 'pricelists', $new_id, ['pricelist_code' => $pl_code_raw]);
            }
            pl_redirect_with_msg('تم إضافة قائمة الأسعار بنجاح ✅');
        }
        error_log('pricelists.php insert failed: ' . mysqli_error($conn));
        pl_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

    if (!$can_delete) {
        pl_redirect_with_msg('لا توجد صلاحية حذف قوائم الأسعار ❌');
    }
    if (empty($delete_csrf) || !hash_equals($pl_csrf_token, $delete_csrf)) {
        pl_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    $chk = mysqli_query($conn, "SELECT id FROM pricelists WHERE id = $delete_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        pl_redirect_with_msg('لا يمكنك حذف قائمة أسعار لا تتبع لشركتك ❌');
    }
    $deleted_by = intval($_SESSION['user']['id']);
    $del = "UPDATE pricelists SET is_deleted = 1, deleted_at = NOW(), deleted_by = $deleted_by
            WHERE id = $delete_id AND $scope_update_sql AND is_deleted = 0";
    if (mysqli_query($conn, $del)) {
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('pricelists', 'pricelists', $delete_id);
        }
        pl_redirect_with_msg('تم حذف قائمة الأسعار بنجاح ✅');
    }
    error_log('pricelists.php soft delete failed: ' . mysqli_error($conn));
    pl_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

// ══════════════════════════════════════════════════════════════════════════════
// جلب قوائم الأسعار + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_hourly = 0;
$stat_ton = 0;
$stat_meter = 0;

$q = "SELECT p.*, u.name AS creator_name
      FROM pricelists p
      LEFT JOIN users u ON u.id = p.created_by
      WHERE $scope_sql AND $not_deleted_sql
      ORDER BY p.id DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $stat_total++;
        if ($row['revenue_model'] === 'hourly') $stat_hourly++;
        elseif ($row['revenue_model'] === 'ton') $stat_ton++;
        elseif ($row['revenue_model'] === 'meter') $stat_meter++;
    }
}

$page_title = "نماذج التسعير";
include("../inheader.php");
include('../insidebar.php');

function pl_revenue_label($model, $map)
{
    return ($model !== null && isset($map[$model])) ? $map[$model] : '';
}
?>

<div class="main pl-main ems-unified-page-shell">

    <?php
    $header_title = 'نماذج التسعير';
    $header_icon = 'fas fa-tags';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'pl-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'pl-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo pl_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section pl-hidden" id="plStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-tags"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي القوائم</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-clock"></i></div>
                <div class="stats-value"><?php echo $stat_hourly; ?></div>
                <div class="stats-title">بالساعة</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-truck-moving"></i></div>
                <div class="stats-value"><?php echo $stat_ton; ?></div>
                <div class="stats-title">بالطن</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-ruler-horizontal"></i></div>
                <div class="stats-value"><?php echo $stat_meter; ?></div>
                <div class="stats-title">بالمتر</div>
            </div>
        </div>
    </div>

    <!-- فورم إضافة / تعديل قائمة أسعار -->
    <form id="plForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة قائمة أسعار جديدة</span></h5>
        </div>
        <input type="hidden" name="pl_id" id="pl_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo pl_e($pl_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label><i class="fas fa-magic"></i> الكود المولد <i class="fas fa-info-circle pl-info-icon"></i></label>
                        <input type="text" id="generated_pl_code" class="generated-code-field" value="<?php echo pl_e($next_pl_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل الكود" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> الكود *</label>
                        <input type="text" name="pricelist_code" id="pricelist_code" placeholder="مثال: PL-001" required pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-heading"></i> اسم قائمة الأسعار *</label>
                        <input type="text" name="name" id="name" placeholder="اسم قائمة الأسعار" required />
                    </div>
                    <div>
                        <label><i class="fas fa-coins"></i> العملة</label>
                        <select name="currency" id="currency">
                            <?php foreach ($PL_CURRENCIES as $cur): ?>
                                <option value="<?php echo pl_e($cur); ?>" <?php echo $cur === 'USD' ? 'selected' : ''; ?>><?php echo pl_e($cur); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-diagram-project"></i> نموذج التسعير</label>
                        <select name="revenue_model" id="revenue_model">
                            <option value="">-- غير محدد --</option>
                            <?php foreach ($PL_REVENUE_MODELS as $k => $v): ?>
                                <option value="<?php echo pl_e($k); ?>"><?php echo pl_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-money-bill-wave"></i> السعر الأساس</label>
                        <input type="number" step="0.01" name="base_price" id="base_price" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-route"></i> أثر المسافة</label>
                        <input type="number" step="0.001" name="distance_factor" id="distance_factor" placeholder="" />
                    </div>
                    <div>
                        <label><i class="fas fa-business-time"></i> أثر الورديات</label>
                        <input type="number" step="0.001" name="shift_factor" id="shift_factor" placeholder="" />
                    </div>
                    <div>
                        <label><i class="fas fa-cubes"></i> أثر الحجم</label>
                        <input type="number" step="0.001" name="volume_factor" id="volume_factor" placeholder="" />
                    </div>
                    <div>
                        <label><i class="fas fa-hourglass-half"></i> أثر المدة</label>
                        <input type="number" step="0.001" name="duration_factor" id="duration_factor" placeholder="" />
                    </div>
                    <div class="pl-col-full">
                        <label><i class="fas fa-note-sticky"></i> ملاحظات</label>
                        <textarea name="notes" id="notes" rows="2" placeholder="أي ملاحظات إضافية"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ القائمة</span></button>
                    <button type="button" id="plFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
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
                <label><i class="fa fa-diagram-project"></i> النموذج</label>
                <select id="filterModel" class="form-control">
                    <option value="">-- كل النماذج --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-coins"></i> العملة</label>
                <select id="filterCurrency" class="form-control">
                    <option value="">-- الكل --</option>
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
                <table id="plTable" class="display pl-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>الاسم</th>
                            <th>النموذج</th>
                            <th>العملة</th>
                            <th>السعر الأساس</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $model_label = pl_revenue_label($row['revenue_model'], $PL_REVENUE_MODELS);
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewPlBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo pl_e($row['pricelist_code']); ?>"
                                            data-name="<?php echo pl_e($row['name']); ?>"
                                            data-model="<?php echo pl_e($row['revenue_model']); ?>"
                                            data-model-label="<?php echo pl_e($model_label); ?>"
                                            data-currency="<?php echo pl_e($row['currency']); ?>"
                                            data-base-price="<?php echo pl_e($row['base_price']); ?>"
                                            data-distance="<?php echo $row['distance_factor'] !== null ? pl_e($row['distance_factor']) : ''; ?>"
                                            data-shift="<?php echo $row['shift_factor'] !== null ? pl_e($row['shift_factor']) : ''; ?>"
                                            data-volume="<?php echo $row['volume_factor'] !== null ? pl_e($row['volume_factor']) : ''; ?>"
                                            data-duration="<?php echo $row['duration_factor'] !== null ? pl_e($row['duration_factor']) : ''; ?>"
                                            data-notes="<?php echo pl_e($row['notes']); ?>"
                                            data-created="<?php echo pl_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editPlBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo pl_e($row['pricelist_code']); ?>"
                                                data-name="<?php echo pl_e($row['name']); ?>"
                                                data-model="<?php echo pl_e($row['revenue_model']); ?>"
                                                data-currency="<?php echo pl_e($row['currency']); ?>"
                                                data-base-price="<?php echo pl_e($row['base_price']); ?>"
                                                data-distance="<?php echo $row['distance_factor'] !== null ? pl_e($row['distance_factor']) : ''; ?>"
                                                data-shift="<?php echo $row['shift_factor'] !== null ? pl_e($row['shift_factor']) : ''; ?>"
                                                data-volume="<?php echo $row['volume_factor'] !== null ? pl_e($row['volume_factor']) : ''; ?>"
                                                data-duration="<?php echo $row['duration_factor'] !== null ? pl_e($row['duration_factor']) : ''; ?>"
                                                data-notes="<?php echo pl_e($row['notes']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($pl_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف قائمة الأسعار هذه؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="pl-code-cell"><?php echo pl_e($row['pricelist_code']); ?></strong></td>
                                <td><?php echo pl_e($row['name']); ?></td>
                                <td><?php echo $model_label !== '' ? pl_e($model_label) : '<span class="pl-muted">—</span>'; ?></td>
                                <td><?php echo pl_e($row['currency']); ?></td>
                                <td class="pl-num"><?php echo pl_money($row['base_price']); ?></td>
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
        const plTable = $('#plTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            plTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && text !== '—' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(3, '#filterModel');
        fillFilterOptions(4, '#filterCurrency');

        $('#filterModel').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            plTable.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterCurrency').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            plTable.column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const plForm = $('#plForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#plFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#plStatsSection');

    function setAddMode() { formTitle.text('إضافة قائمة أسعار جديدة'); submitBtnText.text('حفظ القائمة'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل قائمة الأسعار'); submitBtnText.text('تحديث القائمة'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!plForm.length) return; plForm[0].reset(); $('#pl_id').val(''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.pl-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(plForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!plForm.length) return;
        if (plForm.is(':visible')) {
            plForm.stop(true, true).slideUp(250, function () { plForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            plForm.addClass('allforms-visible').hide();
            plForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!plForm.length || !plForm.is(':visible')) return;
        plForm.stop(true, true).slideUp(250, function () { plForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('pl-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('pl-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillPlForm(d) {
        $('#pl_id').val(d.id);
        $('#pricelist_code').val(d.code);
        $('#name').val(d.name || '');
        $('#currency').val(d.currency || 'USD');
        $('#revenue_model').val(d.model || '');
        $('#base_price').val(d.basePrice || '');
        $('#distance_factor').val(d.distance || '');
        $('#shift_factor').val(d.shift || '');
        $('#volume_factor').val(d.volume || '');
        $('#duration_factor').val(d.duration || '');
        $('#notes').val(d.notes || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!plForm.is(':visible')) {
            plForm.addClass('allforms-visible').hide();
            plForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#plForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editPlBtn', function () {
        fillPlForm({
            id: $(this).data('id'), code: $(this).data('code'), name: $(this).data('name'),
            model: $(this).data('model'), currency: $(this).data('currency'), basePrice: $(this).data('base-price'),
            distance: $(this).data('distance'), shift: $(this).data('shift'), volume: $(this).data('volume'),
            duration: $(this).data('duration'), notes: $(this).data('notes')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewPlBtn', function () {
        const d = $(this).data();
        const cur = d.currency || '';
        const basePrice = (d.basePrice !== undefined && d.basePrice !== null && d.basePrice !== '')
            ? Number(d.basePrice).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (cur ? ' ' + cur : '')
            : '—';
        const fields = [
            { label: 'الكود', value: d.code, icon: 'fas fa-barcode' },
            { label: 'الاسم', value: d.name || '—', icon: 'fas fa-heading', size: 'lg' },
            { label: 'النموذج', value: d.modelLabel || '—', icon: 'fas fa-diagram-project' },
            { label: 'العملة', value: cur || '—', icon: 'fas fa-coins' },
            { label: 'السعر الأساس', value: basePrice, icon: 'fas fa-money-bill-wave', size: 'lg' },
            { label: 'أثر المسافة', value: (d.distance !== undefined && d.distance !== '') ? d.distance : '—', icon: 'fas fa-route' },
            { label: 'أثر الورديات', value: (d.shift !== undefined && d.shift !== '') ? d.shift : '—', icon: 'fas fa-business-time' },
            { label: 'أثر الحجم', value: (d.volume !== undefined && d.volume !== '') ? d.volume : '—', icon: 'fas fa-cubes' },
            { label: 'أثر المدة', value: (d.duration !== undefined && d.duration !== '') ? d.duration : '—', icon: 'fas fa-hourglass-half' },
            { label: 'ملاحظات', value: d.notes || '—', icon: 'fas fa-note-sticky', size: 'lg' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل القائمة', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editPlBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل قائمة الأسعار', icon: 'fas fa-tags', fields: fields, actions: actions });
    });
</script>

<style>
    .pl-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .pl-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .pl-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .pl-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .pl-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .pl-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .pl-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .pl-main .stats-grid { grid-template-columns: 1fr; } }

    .pl-main .pl-hidden { display: none; }
    .pl-main .pl-col-full { grid-column: 1 / -1; }
    .pl-main .table-container { overflow-x: auto; }
    #plTable.pl-table-nowrap, #plTable.pl-table-nowrap th, #plTable.pl-table-nowrap td { white-space: nowrap; }
    #plTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .pl-main .pl-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .pl-main .pl-muted { color: #999; }
</style>

</body>

</html>
