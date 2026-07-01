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
if (!function_exists('prod_e')) {
    function prod_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('prod_money')) {
    function prod_money($value)
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float) $value, 2);
    }
}
if (!function_exists('prod_redirect_with_msg')) {
    function prod_redirect_with_msg($msg)
    {
        header('Location: products.php?msg=' . urlencode($msg));
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
if (empty($_SESSION['prod_csrf_token'])) {
    $_SESSION['prod_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}
$prod_csrf_token = $_SESSION['prod_csrf_token'];

// القوائم الثابتة
$PROD_CURRENCIES = array('USD', 'SDG');
$PROD_TYPES = array('خدمة', 'معدة', 'مادة');
$PROD_REVENUE_MODELS = array(
    'hourly' => 'تأجير بالساعة',
    'ton'    => 'نقل بالطن',
    'meter'  => 'تخريم بالمتر',
);

// توليد الكود المقترح التالي (PRD-NNNN) — للعرض فقط
$next_prod_code = 'PRD-0001';
$last_code_sql = "SELECT product_code FROM products
                  WHERE product_code REGEXP '^PRD-[0-9]+$' AND company_id = $company_id AND is_deleted = 0
                  ORDER BY CAST(SUBSTRING(product_code, 5) AS UNSIGNED) DESC LIMIT 1";
$last_code_res = @mysqli_query($conn, $last_code_sql);
if ($last_code_res && mysqli_num_rows($last_code_res) > 0) {
    $last_code_row = mysqli_fetch_assoc($last_code_res);
    $last_num = intval(substr($last_code_row['product_code'], 4));
    $next_prod_code = 'PRD-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة المنتجات والخدمات
$module_query = "SELECT id FROM modules WHERE code = 'Clients/products.php' LIMIT 1";
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
    header('Location: ../login.php?msg=' . urlencode('لا توجد صلاحية عرض المنتجات والخدمات ❌'));
    exit();
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة إضافة / تعديل منتج عبر POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['name'])) {
    $posted_csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($posted_csrf) || !hash_equals($prod_csrf_token, $posted_csrf)) {
        prod_redirect_with_msg('جلسة النموذج غير صالحة، يرجى إعادة المحاولة ❌');
    }

    $prod_id    = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : 0;
    $is_editing = $prod_id > 0;

    if ($is_editing && !$can_edit) {
        prod_redirect_with_msg('لا توجد صلاحية تعديل المنتجات والخدمات ❌');
    } elseif (!$is_editing && !$can_add) {
        prod_redirect_with_msg('لا توجد صلاحية إضافة منتجات جديدة ❌');
    }

    // الكود
    $prod_code_raw = isset($_POST['product_code']) ? trim($_POST['product_code']) : '';
    if ($prod_code_raw === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $prod_code_raw)) {
        prod_redirect_with_msg('كود المنتج غير صالح. استخدم أحرفًا وأرقامًا و - أو _ فقط ❌');
    }

    // الاسم
    $name_raw = isset($_POST['name']) ? trim($_POST['name']) : '';
    if ($name_raw === '') {
        prod_redirect_with_msg('اسم المنتج/الخدمة مطلوب ❌');
    }

    // النوع
    $type_raw = isset($_POST['product_type']) ? trim($_POST['product_type']) : 'خدمة';
    if (!in_array($type_raw, $PROD_TYPES, true)) {
        $type_raw = 'خدمة';
    }

    // العملة
    $currency_raw = isset($_POST['currency']) ? trim($_POST['currency']) : 'USD';
    if (!in_array($currency_raw, $PROD_CURRENCIES, true)) {
        $currency_raw = 'USD';
    }

    // نموذج الإيراد (اختياري)
    $rev_raw = isset($_POST['revenue_model']) ? trim($_POST['revenue_model']) : '';
    if ($rev_raw !== '' && !isset($PROD_REVENUE_MODELS[$rev_raw])) {
        prod_redirect_with_msg('نموذج الإيراد غير صالح ❌');
    }
    $rev_sql = $rev_raw !== '' ? "'" . mysqli_real_escape_string($conn, $rev_raw) . "'" : 'NULL';

    // السعر المرجعي
    $price_raw = isset($_POST['standard_price']) ? trim($_POST['standard_price']) : '';
    $price_sql = ($price_raw === '') ? '0' : "'" . (float) $price_raw . "'";

    // تنظيف بقية الحقول
    $product_code = mysqli_real_escape_string($conn, $prod_code_raw);
    $name         = mysqli_real_escape_string($conn, $name_raw);
    $product_type = mysqli_real_escape_string($conn, $type_raw);
    $currency     = mysqli_real_escape_string($conn, $currency_raw);
    $default_uom  = mysqli_real_escape_string($conn, isset($_POST['default_uom']) ? trim($_POST['default_uom']) : '');
    $description  = mysqli_real_escape_string($conn, isset($_POST['description']) ? trim($_POST['description']) : '');
    $created_by   = intval($_SESSION['user']['id']);

    if ($is_editing) {
        $owner = mysqli_query($conn, "SELECT id FROM products WHERE id = $prod_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
        if (!$owner || mysqli_num_rows($owner) === 0) {
            prod_redirect_with_msg('لا يمكنك تعديل منتج لا يتبع لشركتك ❌');
        }
        $dup = mysqli_query($conn, "SELECT id FROM products WHERE product_code = '$product_code' AND id != $prod_id AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            prod_redirect_with_msg('كود المنتج موجود مسبقاً داخل شركتك ❌');
        }

        $update_query = "UPDATE products SET
            product_code = '$product_code', name = '$name', product_type = '$product_type',
            revenue_model = $rev_sql, default_uom = '$default_uom', standard_price = $price_sql,
            currency = '$currency', description = '$description'
            WHERE id = $prod_id AND $scope_update_sql AND is_deleted = 0";

        if (mysqli_query($conn, $update_query)) {
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('products', 'products', $prod_id, null, ['product_code' => $prod_code_raw]);
            }
            prod_redirect_with_msg('تم تعديل المنتج بنجاح ✅');
        }
        error_log('products.php update failed: ' . mysqli_error($conn));
        prod_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
    } else {
        $dup = mysqli_query($conn, "SELECT id FROM products WHERE product_code = '$product_code' AND company_id = $company_id AND is_deleted = 0");
        if ($dup && mysqli_num_rows($dup) > 0) {
            prod_redirect_with_msg('كود المنتج موجود مسبقاً داخل شركتك ❌');
        }

        $insert_query = "INSERT INTO products
            (company_id, product_code, name, product_type, revenue_model, default_uom,
             standard_price, currency, description, created_by)
            VALUES
            ('$company_id', '$product_code', '$name', '$product_type', $rev_sql, '$default_uom',
             $price_sql, '$currency', '$description', '$created_by')";

        if (mysqli_query($conn, $insert_query)) {
            $new_id = (int) mysqli_insert_id($conn);
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('products', 'products', $new_id, ['product_code' => $prod_code_raw]);
            }
            prod_redirect_with_msg('تم إضافة المنتج بنجاح ✅');
        }
        error_log('products.php insert failed: ' . mysqli_error($conn));
        prod_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// معالجة الحذف الناعم
// ══════════════════════════════════════════════════════════════════════════════
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_csrf = isset($_GET['csrf_token']) ? $_GET['csrf_token'] : '';

    if (!$can_delete) {
        prod_redirect_with_msg('لا توجد صلاحية حذف المنتجات والخدمات ❌');
    }
    if (empty($delete_csrf) || !hash_equals($prod_csrf_token, $delete_csrf)) {
        prod_redirect_with_msg('جلسة الحذف غير صالحة، يرجى إعادة المحاولة ❌');
    }
    $chk = mysqli_query($conn, "SELECT id FROM products WHERE id = $delete_id AND company_id = $company_id AND is_deleted = 0 LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        prod_redirect_with_msg('لا يمكنك حذف منتج لا يتبع لشركتك ❌');
    }
    $deleted_by = intval($_SESSION['user']['id']);
    $del = "UPDATE products SET is_deleted = 1, deleted_at = NOW(), deleted_by = $deleted_by
            WHERE id = $delete_id AND $scope_update_sql AND is_deleted = 0";
    if (mysqli_query($conn, $del)) {
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('products', 'products', $delete_id);
        }
        prod_redirect_with_msg('تم حذف المنتج بنجاح ✅');
    }
    error_log('products.php soft delete failed: ' . mysqli_error($conn));
    prod_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
}

// ══════════════════════════════════════════════════════════════════════════════
// جلب المنتجات + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_services = 0;
$stat_hourly = 0;
$stat_ton = 0;

$q = "SELECT p.*, u.name AS creator_name
      FROM products p
      LEFT JOIN users u ON u.id = p.created_by
      WHERE $scope_sql AND $not_deleted_sql
      ORDER BY p.id DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $stat_total++;
        if ($row['product_type'] === 'خدمة') $stat_services++;
        if ($row['revenue_model'] === 'hourly') $stat_hourly++;
        elseif ($row['revenue_model'] === 'ton') $stat_ton++;
    }
}

$page_title = "المنتجات والخدمات";
include("../inheader.php");
include('../insidebar.php');

function prod_revenue_label($model, $map)
{
    return ($model !== null && isset($map[$model])) ? $map[$model] : '';
}
?>

<div class="main prod-main ems-unified-page-shell">

    <?php
    $header_title = 'المنتجات والخدمات';
    $header_icon = 'fas fa-box';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'prod-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'prod-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo prod_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="stats-section prod-hidden" id="prodStatsSection">
        <div class="stats-grid">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-box"></i></div>
                <div class="stats-value"><?php echo $stat_total; ?></div>
                <div class="stats-title">إجمالي المنتجات</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-concierge-bell"></i></div>
                <div class="stats-value"><?php echo $stat_services; ?></div>
                <div class="stats-title">خدمات</div>
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
        </div>
    </div>

    <!-- فورم إضافة / تعديل منتج -->
    <form id="prodForm" action="" method="post" class="allforms">
        <div class="card-header">
            <h5><i class="fas fa-edit"></i> <span id="formTitle">إضافة منتج جديد</span></h5>
        </div>
        <input type="hidden" name="prod_id" id="prod_id" value="">
        <input type="hidden" name="csrf_token" value="<?php echo prod_e($prod_csrf_token); ?>">
        <div class="card shadow-sm pu-form-card">
            <div class="card-body">
                <div class="form-grid">
                    <div id="generated_code_wrapper" class="auto">
                        <label><i class="fas fa-magic"></i> الكود المولد <i class="fas fa-info-circle prod-info-icon"></i></label>
                        <input type="text" id="generated_prod_code" class="generated-code-field" value="<?php echo prod_e($next_prod_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، انسخه إلى حقل الكود" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label><i class="fas fa-barcode"></i> الكود *</label>
                        <input type="text" name="product_code" id="product_code" placeholder="مثال: PRD-001" required pattern="[A-Za-z0-9-_]+" />
                    </div>
                    <div>
                        <label><i class="fas fa-heading"></i> اسم المنتج/الخدمة *</label>
                        <input type="text" name="name" id="name" placeholder="اسم المنتج/الخدمة" required />
                    </div>
                    <div>
                        <label><i class="fas fa-tag"></i> النوع</label>
                        <select name="product_type" id="product_type">
                            <?php foreach ($PROD_TYPES as $t): ?>
                                <option value="<?php echo prod_e($t); ?>" <?php echo $t === 'خدمة' ? 'selected' : ''; ?>><?php echo prod_e($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-diagram-project"></i> نموذج الإيراد</label>
                        <select name="revenue_model" id="revenue_model">
                            <option value="">-- غير محدد --</option>
                            <?php foreach ($PROD_REVENUE_MODELS as $k => $v): ?>
                                <option value="<?php echo prod_e($k); ?>"><?php echo prod_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label><i class="fas fa-ruler"></i> وحدة القياس الافتراضية</label>
                        <input type="text" name="default_uom" id="default_uom" placeholder="مثال: ساعة / طن / متر" />
                    </div>
                    <div>
                        <label><i class="fas fa-money-bill-wave"></i> السعر المرجعي</label>
                        <input type="number" step="0.01" name="standard_price" id="standard_price" placeholder="0.00" />
                    </div>
                    <div>
                        <label><i class="fas fa-coins"></i> العملة</label>
                        <select name="currency" id="currency">
                            <?php foreach ($PROD_CURRENCIES as $cur): ?>
                                <option value="<?php echo prod_e($cur); ?>" <?php echo $cur === 'USD' ? 'selected' : ''; ?>><?php echo prod_e($cur); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="prod-col-full">
                        <label><i class="fas fa-align-left"></i> الوصف</label>
                        <textarea name="description" id="description" rows="2" placeholder="أي وصف إضافي"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ المنتج</span></button>
                    <button type="button" id="prodFormCancelBtn" class="btn-cancel"><i class="fas fa-times"></i> إلغاء</button>
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
                <label><i class="fa fa-tag"></i> النوع</label>
                <select id="filterType" class="form-control">
                    <option value="">-- كل الأنواع --</option>
                </select>
            </div>
            <div class="filter-field">
                <label><i class="fa fa-diagram-project"></i> نموذج الإيراد</label>
                <select id="filterModel" class="form-control">
                    <option value="">-- كل النماذج --</option>
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
                <table id="prodTable" class="display prod-table-nowrap no-datatable">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">الكود</th>
                            <th>الاسم</th>
                            <th>النوع</th>
                            <th>نموذج الإيراد</th>
                            <th>وحدة القياس</th>
                            <th>السعر المرجعي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row):
                            $model_label = prod_revenue_label($row['revenue_model'], $PROD_REVENUE_MODELS);
                            $created_label = function_exists('ems_actor_label') ? ems_actor_label($conn, intval($row['created_by'])) : ($row['creator_name'] ?? '');
                            ?>
                            <tr>
                                <td>
                                    <div class="action-btns">
                                        <a href="javascript:void(0)" class="action-btn view viewProdBtn"
                                            data-id="<?php echo intval($row['id']); ?>"
                                            data-code="<?php echo prod_e($row['product_code']); ?>"
                                            data-name="<?php echo prod_e($row['name']); ?>"
                                            data-type="<?php echo prod_e($row['product_type']); ?>"
                                            data-model="<?php echo prod_e($row['revenue_model']); ?>"
                                            data-model-label="<?php echo prod_e($model_label); ?>"
                                            data-uom="<?php echo prod_e($row['default_uom']); ?>"
                                            data-price="<?php echo prod_e($row['standard_price']); ?>"
                                            data-currency="<?php echo prod_e($row['currency']); ?>"
                                            data-description="<?php echo prod_e($row['description']); ?>"
                                            data-created="<?php echo prod_e($created_label); ?>"
                                            title="عرض التفاصيل"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_edit): ?>
                                            <a href="javascript:void(0)" class="action-btn edit editProdBtn"
                                                data-id="<?php echo intval($row['id']); ?>"
                                                data-code="<?php echo prod_e($row['product_code']); ?>"
                                                data-name="<?php echo prod_e($row['name']); ?>"
                                                data-type="<?php echo prod_e($row['product_type']); ?>"
                                                data-model="<?php echo prod_e($row['revenue_model']); ?>"
                                                data-uom="<?php echo prod_e($row['default_uom']); ?>"
                                                data-price="<?php echo prod_e($row['standard_price']); ?>"
                                                data-currency="<?php echo prod_e($row['currency']); ?>"
                                                data-description="<?php echo prod_e($row['description']); ?>"
                                                title="تعديل"><i class="fas fa-edit"></i></a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                            <a href="?delete_id=<?php echo urlencode($row['id']); ?>&csrf_token=<?php echo urlencode($prod_csrf_token); ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('هل أنت متأكد من حذف هذا المنتج؟')" title="حذف"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong class="prod-code-cell"><?php echo prod_e($row['product_code']); ?></strong></td>
                                <td><?php echo prod_e($row['name']); ?></td>
                                <td><?php echo prod_e($row['product_type']); ?></td>
                                <td><?php echo $model_label !== '' ? prod_e($model_label) : '<span class="prod-muted">—</span>'; ?></td>
                                <td><?php echo $row['default_uom'] !== '' && $row['default_uom'] !== null ? prod_e($row['default_uom']) : '<span class="prod-muted">—</span>'; ?></td>
                                <td class="prod-num"><?php echo prod_money($row['standard_price']); ?> <?php echo prod_e($row['currency']); ?></td>
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
        const prodTable = $('#prodTable').DataTable({
            autoWidth: false,
            stateSave: false,
            language: { url: '/ems/assets/i18n/datatables/ar.json' }
        });

        function fillFilterOptions(columnIndex, selectId) {
            const select = $(selectId);
            const values = [];
            prodTable.column(columnIndex).data().each(function (value) {
                const text = $('<div>').html(value).text().trim();
                if (text !== '' && text !== '—' && values.indexOf(text) === -1) values.push(text);
            });
            values.sort();
            values.forEach(function (val) {
                select.append('<option value="' + val.replace(/"/g, '&quot;') + '">' + val + '</option>');
            });
        }
        fillFilterOptions(3, '#filterType');
        fillFilterOptions(4, '#filterModel');

        $('#filterType').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            prodTable.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
        });
        $('#filterModel').on('change', function () {
            const value = $.fn.dataTable.util.escapeRegex($(this).val());
            prodTable.column(4).search(value ? '^' + value + '$' : '', true, false).draw();
        });
    });

    // ── إظهار/إخفاء الفورم والإحصائيات ──
    const formToggleBtn = $('#toggleForm');
    const prodForm = $('#prodForm');
    const formTitle = $('#formTitle');
    const submitBtnText = $('#submitBtnText');
    const generatedCodeWrapper = $('#generated_code_wrapper');
    const formCancelBtn = $('#prodFormCancelBtn');
    const statsToggleBtn = $('#toggleStats');
    const statsSection = $('#prodStatsSection');

    function setAddMode() { formTitle.text('إضافة منتج جديد'); submitBtnText.text('حفظ المنتج'); generatedCodeWrapper.show(); }
    function setEditMode() { formTitle.text('تعديل المنتج'); submitBtnText.text('تحديث المنتج'); generatedCodeWrapper.hide(); }
    function resetForm() { if (!prodForm.length) return; prodForm[0].reset(); $('#prod_id').val(''); setAddMode(); if (window.EmsSelect) EmsSelect.refresh(); }

    function updateFormToggleState(isOpen) {
        if (!formToggleBtn.length) return;
        formToggleBtn.toggleClass('is-active', isOpen);
        formToggleBtn.attr('aria-expanded', isOpen ? 'true' : 'false');
    }
    function updateStatsToggleState(isVisible) {
        if (!statsToggleBtn.length) return;
        statsToggleBtn.toggleClass('is-active', isVisible);
        statsToggleBtn.find('.prod-toggle-stats-text').text('إظهار الإحصائيات');
        const icon = statsToggleBtn.find('i').first();
        icon.toggleClass('fa-chart-pie', isVisible);
        icon.toggleClass('fa-eye', !isVisible);
    }

    setAddMode();
    updateFormToggleState(prodForm.is(':visible'));
    updateStatsToggleState(statsSection.is(':visible'));

    formToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!prodForm.length) return;
        if (prodForm.is(':visible')) {
            prodForm.stop(true, true).slideUp(250, function () { prodForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
        } else {
            resetForm();
            prodForm.addClass('allforms-visible').hide();
            prodForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        }
    });
    formCancelBtn.on('click', function () {
        if (!prodForm.length || !prodForm.is(':visible')) return;
        prodForm.stop(true, true).slideUp(250, function () { prodForm.removeClass('allforms-visible'); resetForm(); updateFormToggleState(false); });
    });
    statsToggleBtn.on('click', function (e) {
        e.preventDefault();
        if (!statsSection.length) return;
        if (statsSection.is(':visible')) {
            statsSection.stop(true, true).slideUp(250, function () { statsSection.addClass('prod-hidden'); updateStatsToggleState(false); });
        } else {
            statsSection.removeClass('prod-hidden').hide();
            statsSection.stop(true, true).slideDown(250, function () { updateStatsToggleState(true); });
        }
    });

    // ── تعبئة الفورم للتعديل ──
    function fillProdForm(d) {
        $('#prod_id').val(d.id);
        $('#product_code').val(d.code);
        $('#name').val(d.name || '');
        $('#product_type').val(d.type || 'خدمة');
        $('#revenue_model').val(d.model || '');
        $('#default_uom').val(d.uom || '');
        $('#standard_price').val(d.price || '');
        $('#currency').val(d.currency || 'USD');
        $('#description').val(d.description || '');
        if (window.EmsSelect) EmsSelect.refresh();
        setEditMode();
        if (!prodForm.is(':visible')) {
            prodForm.addClass('allforms-visible').hide();
            prodForm.stop(true, true).slideDown(250, function () { updateFormToggleState(true); });
        } else { updateFormToggleState(true); }
        $('html, body').animate({ scrollTop: $('#prodForm').offset().top - 100 }, 500);
    }

    $(document).on('click', '.editProdBtn', function () {
        fillProdForm({
            id: $(this).data('id'), code: $(this).data('code'), name: $(this).data('name'),
            type: $(this).data('type'), model: $(this).data('model'), uom: $(this).data('uom'),
            price: $(this).data('price'), currency: $(this).data('currency'), description: $(this).data('description')
        });
    });

    // ── عرض التفاصيل عبر EmsDetailsModal الموحّد ──
    $(document).on('click', '.viewProdBtn', function () {
        const d = $(this).data();
        const cur = d.currency || '';
        const price = (d.price !== undefined && d.price !== null && d.price !== '')
            ? Number(d.price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (cur ? ' ' + cur : '')
            : '—';
        const fields = [
            { label: 'الكود', value: d.code, icon: 'fas fa-barcode' },
            { label: 'الاسم', value: d.name || '—', icon: 'fas fa-heading', size: 'lg' },
            { label: 'النوع', value: d.type || '—', icon: 'fas fa-tag' },
            { label: 'نموذج الإيراد', value: d.modelLabel || '—', icon: 'fas fa-diagram-project' },
            { label: 'وحدة القياس', value: d.uom || '—', icon: 'fas fa-ruler' },
            { label: 'السعر المرجعي', value: price, icon: 'fas fa-money-bill-wave', size: 'lg' },
            { label: 'العملة', value: cur || '—', icon: 'fas fa-coins' },
            { label: 'الوصف', value: d.description || '—', icon: 'fas fa-align-left', size: 'lg' },
            { label: 'أضيف بواسطة', value: d.created || '—', icon: 'fas fa-user-plus' }
        ];

        const actions = [];
        <?php if ($can_edit): ?>
            actions.push({ label: 'تعديل المنتج', icon: 'fas fa-edit', variant: 'primary', onClick: function () {
                EmsDetailsModal.close();
                $('.editProdBtn[data-id="' + d.id + '"]').trigger('click');
            }});
        <?php endif; ?>
        actions.push({ label: 'إغلاق', icon: 'fas fa-times', variant: 'secondary', close: true });

        EmsDetailsModal.open({ title: 'تفاصيل المنتج', icon: 'fas fa-box', fields: fields, actions: actions });
    });
</script>

<style>
    .prod-main .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(170px, 1fr)); gap: 12px; }
    .prod-main .stats-section {
        border: 1px solid var(--bdr); border-radius: var(--rl);
        background: linear-gradient(180deg, rgba(255,255,255,.95) 0%, var(--s2) 100%);
        box-shadow: var(--sh); padding: 14px; margin-bottom: 14px;
    }
    .prod-main .stats-card { background: #eee; border: 1px solid #aaa; border-radius: 35px; padding: 18px; box-shadow: 0 2px 8px rgba(26,18,8,.07); position: relative; overflow: hidden; }
    .prod-main .stats-card .stats-icon { width: 55px; height: 55px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px; float: left; margin-top: 15px; border: 1px solid #999; background:#fff; color:#000; }
    .prod-main .stats-card .stats-title { color: #555; font-size: .92rem; font-weight: 700; margin-top: 5px; line-height: 1.3; }
    .prod-main .stats-card .stats-value { color: #222; line-height: 1; font-weight: 900; font-variant-numeric: tabular-nums; margin-top: 10px; font-size: 30px; }
    @media (max-width: 900px) { .prod-main .stats-grid { grid-template-columns: repeat(2, minmax(150px,1fr)); } }
    @media (max-width: 560px) { .prod-main .stats-grid { grid-template-columns: 1fr; } }

    .prod-main .prod-hidden { display: none; }
    .prod-main .prod-col-full { grid-column: 1 / -1; }
    .prod-main .table-container { overflow-x: auto; }
    #prodTable.prod-table-nowrap, #prodTable.prod-table-nowrap th, #prodTable.prod-table-nowrap td { white-space: nowrap; }
    #prodTable .action-btns { flex-wrap: nowrap; white-space: nowrap; }
    .prod-main .prod-num { font-variant-numeric: tabular-nums; font-weight: 700; }
    .prod-main .prod-muted { color: #999; }
</style>

</body>

</html>
