<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once __DIR__ . '/../includes/excel_ui.php'; // ح-09 · أزرار Excel الموحّدة
include '../includes/permissions_helper.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}

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
        ems_gov_flash_redirect('products.php', $msg, 'GOV-INFO-200', '');
        exit();
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// التحقق من معرف الشركة (عزل الشركات)
// ══════════════════════════════════════════════════════════════════════════════
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
if ($company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'الحساب غير مرتبط بشركة.', 'GOV-INFO-200', '');
    exit();
}

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15) — النطاق والحذف الناعم مسؤولية البوابة
$prod_gate = ems_tenant_db();

// رمز CSRF
// [ع-0أ] اعتماد الرمز المركزي بدل رمزٍ محلّيٍّ منفصل: الحاقنُ المركزي يضع حقل
// csrf_token برمز الجلسة، وهذا كان يطبع حقلًا ثانيًا بالاسم نفسه وقيمةٍ أخرى —
// وPHP يأخذ الأخير فيفشل الحارس المركزي (403). توحيدُ القيمة يُبقي الفحص المحلّي
// أدناه فعّالًا ويُمرّر الحارس المركزي أيًّا كان الفائز.
$prod_csrf_token = generate_csrf_token();

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
try {
    $last_code_rows = $prod_gate->scopedQuery(array(
        'scope' => array('p' => 'products'),
    ), "SELECT p.product_code FROM products p
        WHERE {TENANT_SCOPE} AND p.product_code REGEXP '^PRD-[0-9]+$' AND p.is_deleted = 0
        ORDER BY CAST(SUBSTRING(p.product_code, 5) AS UNSIGNED) DESC LIMIT 1");
} catch (\Throwable $t) {
    $last_code_rows = array();
}
if (!empty($last_code_rows)) {
    $last_num = intval(substr($last_code_rows[0]['product_code'], 4));
    $next_prod_code = 'PRD-' . str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
}

// صلاحيات المستخدم على وحدة المنتجات والخدمات (modules جدول عام — قراءة عبر البوابة)
try {
    $module_info = $prod_gate->selectOne('modules', array(
        'columns' => array('id'),
        'where'   => array('code' => 'Clients/products.php'),
    ));
} catch (\Throwable $t) {
    $module_info = null;
}
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
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض المنتجات والخدمات ❌', 'GOV-PERM-403', '');
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
    if ($prod_code_raw === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $prod_code_raw)) {
        prod_redirect_with_msg('كود المنتج غير صالح. استخدم أحرفا وأرقاما و - أو _ فقط ❌');
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
    $rev_val = ($rev_raw !== '') ? $rev_raw : null;

    // السعر المرجعي
    $price_raw = isset($_POST['standard_price']) ? trim($_POST['standard_price']) : '';
    $price_val = ($price_raw === '') ? 0 : (float) $price_raw;

    // القيم تُمرَّر خامًا — البوابة prepared بالكامل (لا escape يدوي)
    $default_uom_raw = isset($_POST['default_uom']) ? trim($_POST['default_uom']) : '';
    $description_raw = isset($_POST['description']) ? trim($_POST['description']) : '';
    $created_by      = intval($_SESSION['user']['id']);

    if ($is_editing) {
        try {
            $owner = $prod_gate->selectOne('products', array(
                'columns' => array('id'), 'where' => array('id' => $prod_id),
            ));
        } catch (\Throwable $t) { $owner = null; }
        if (!$owner) {
            prod_redirect_with_msg('لا يمكنك تعديل منتج لا يتبع لشركتك ❌');
        }
        try {
            $dup = $prod_gate->scopedQuery(array(
                'scope' => array('p' => 'products'),
            ), "SELECT p.id FROM products p
                WHERE {TENANT_SCOPE} AND p.product_code = ? AND p.id != ? AND p.is_deleted = 0",
                array($prod_code_raw, $prod_id));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            prod_redirect_with_msg('كود المنتج موجود مسبقا داخل شركتك ❌');
        }

        try {
            $prod_gate->update('products', array(
                'product_code'   => $prod_code_raw,
                'name'           => $name_raw,
                'product_type'   => $type_raw,
                'revenue_model'  => $rev_val,
                'default_uom'    => $default_uom_raw,
                'standard_price' => $price_val,
                'currency'       => $currency_raw,
                'description'    => $description_raw,
            ), array('id' => $prod_id), 'is_deleted = 0');
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logUpdate('products', 'products', $prod_id, null, ['product_code' => $prod_code_raw]);
            }
            prod_redirect_with_msg('تم تعديل المنتج بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('products.php update failed: ' . $t->getMessage());
            prod_redirect_with_msg('حدث خطأ أثناء التعديل ❌');
        }
    } else {
        try {
            $dup = $prod_gate->scopedQuery(array(
                'scope' => array('p' => 'products'),
            ), "SELECT p.id FROM products p
                WHERE {TENANT_SCOPE} AND p.product_code = ? AND p.is_deleted = 0",
                array($prod_code_raw));
        } catch (\Throwable $t) { $dup = array(); }
        if (!empty($dup)) {
            prod_redirect_with_msg('كود المنتج موجود مسبقا داخل شركتك ❌');
        }

        try {
            $new_id = (int) $prod_gate->insert('products', array(
                'product_code'   => $prod_code_raw,
                'name'           => $name_raw,
                'product_type'   => $type_raw,
                'revenue_model'  => $rev_val,
                'default_uom'    => $default_uom_raw,
                'standard_price' => $price_val,
                'currency'       => $currency_raw,
                'description'    => $description_raw,
                'created_by'     => $created_by,
            ));
            if (class_exists('\\App\\Services\\ActivityLogService')) {
                \App\Services\ActivityLogService::logCreate('products', 'products', $new_id, ['product_code' => $prod_code_raw]);
            }
            prod_redirect_with_msg('تم إضافة المنتج بنجاح ✅');
        } catch (\Throwable $t) {
            error_log('products.php insert failed: ' . $t->getMessage());
            prod_redirect_with_msg('حدث خطأ أثناء الإضافة ❌');
        }
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
    try {
        $chk = $prod_gate->selectOne('products', array(
            'columns' => array('id'), 'where' => array('id' => $delete_id),
        ));
    } catch (\Throwable $t) { $chk = null; }
    if (!$chk) {
        prod_redirect_with_msg('لا يمكنك حذف منتج لا يتبع لشركتك ❌');
    }
    try {
        $prod_gate->softDelete('products', $delete_id); // يختم deleted_at/deleted_by من السياق
        if (class_exists('\\App\\Services\\ActivityLogService')) {
            \App\Services\ActivityLogService::logDelete('products', 'products', $delete_id);
        }
        prod_redirect_with_msg('تم حذف المنتج بنجاح ✅');
    } catch (\Throwable $t) {
        error_log('products.php soft delete failed: ' . $t->getMessage());
        prod_redirect_with_msg('حدث خطأ أثناء الحذف ❌');
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// جلب المنتجات + الإحصائيات
// ══════════════════════════════════════════════════════════════════════════════
$rows = array();
$stat_total = 0;
$stat_services = 0;
$stat_hourly = 0;
$stat_ton = 0;

try {
    $prod_list = $prod_gate->scopedQuery(array(
        'scope'  => array('p' => 'products'),
        'enrich' => array('u' => 'users'), // إثراء اسم المُنشئ — LEFT JOIN بلا تنطيق (سلوك الأصل)
    ), "SELECT p.*, u.name AS creator_name
        FROM products p
        LEFT JOIN users u ON u.id = p.created_by
        WHERE {TENANT_SCOPE} AND p.is_deleted = 0
        ORDER BY p.id DESC");
} catch (\Throwable $t) {
    $prod_list = array();
    error_log('products.php list load: ' . $t->getMessage()); // [م-5]
}
foreach ($prod_list as $row) {
    $rows[] = $row;
    $stat_total++;
    if ($row['product_type'] === 'خدمة') $stat_services++;
    if ($row['revenue_model'] === 'hourly') $stat_hourly++;
    elseif ($row['revenue_model'] === 'ton') $stat_ton++;
}

$page_title = "كتالوج المنتجات والخدمات";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

function prod_revenue_label($model, $map)
{
    return ($model !== null && isset($map[$model])) ? $map[$model] : '';
}
?>

<div class="main prod-main ems-unified-page-shell">

    <?php
    $header_title = 'كتالوج المنتجات والخدمات';
    $header_icon = 'fas fa-box';
    $header_actions = array();
    if ($can_add) {
        $header_actions[] = array('id' => 'toggleForm', 'class' => 'add-btn', 'icon' => 'fa fa-solid fa-plus', 'label' => '', 'label_class' => 'prod-toggle-form-text');
    } else {
        $header_actions[] = array('tag' => 'button', 'class' => '', 'disabled' => true, 'icon' => 'fas fa-plus-circle', 'label' => 'إضافة (بدون صلاحيات)');
    }
    $header_actions[] = array('id' => 'toggleStats', 'class' => 'btn', 'title' => 'إظهار أو إخفاء الإحصائيات', 'icon' => 'fas fa-eye', 'label' => 'إظهار الإحصائيات', 'label_class' => 'prod-toggle-stats-text');
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fa-solid fa-share', 'label' => '');
    // ح-09 · نموذج + تصدير + استيراد (الإطار الموحّد)
    foreach (ems_excel_header_actions('products', 'كتالوج الخدمات', $can_add) as $__xl) { $header_actions[] = $__xl; }
    
/* شريطُ تبويباتِ العائلة — قرارُ وثيقةِ المواءمة (مكوّنٌ مركزيّ) */
$sft_family = 'reference'; $sft_active = 'products';
include __DIR__ . '/../includes/sales_family_tabs.php';
include('../includes/page_header.php');
    ?>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✅') !== false; ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo prod_e($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php echo ems_states_bundle('لا منتجات ولا خدمات مسجلة ضمن هذا الترشيح', 'أضف منتجا أو خدمة جديدة أو غير المرشحات'); ?>

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
                        <label for="generated_prod_code"><i class="fas fa-magic"></i> الكود المولد <i class="fas fa-info-circle prod-info-icon"></i></label>
                        <input type="text" id="generated_prod_code" class="generated-code-field" value="<?php echo prod_e($next_prod_code); ?>" readonly tabindex="-1" title="هذا الكود للعرض فقط، يمكنك نسخه واستخدامه في حقل الكود" />
                        <div class="generated-code-hint"></div>
                    </div>

                    <div>
                        <label for="product_code"><i class="fas fa-barcode"></i> الكود *</label>
                        <!-- مكتوبٌ سلفًا بالكودِ المولَّد **وقابلٌ للتعديل** (نظيرُ كودِ العميلِ والمشروع):
                             أكثرُ الحالاتِ تقبله كما هو، ومَن أراد كودَه الخاصَّ كتبه فوقه. ووضعُه في
                             السمةِ `value` لا بجافاسكربت مقصود: `resetForm()` تستدعي `reset()` الأصليَّ
                             وهو يعيد كلَّ حقلٍ إلى سمتِه — فيعود الكودُ المولَّدُ تلقائيًّا بعد كلِّ
                             إلغاءٍ أو خروجٍ من وضعِ التعديل. -->
                        <input type="text" name="product_code" id="product_code" placeholder="مثال: PRD-001" required
                            value="<?php echo prod_e($next_prod_code); ?>"
                            pattern="[A-Za-z0-9_\-]+" />
                    </div>
                    <div>
                        <label for="name"><i class="fas fa-heading"></i> اسم المنتج/الخدمة *</label>
                        <input type="text" name="name" id="name" placeholder="اسم المنتج/الخدمة" required />
                    </div>
                    <div>
                        <label for="product_type"><i class="fas fa-tag"></i> النوع</label>
                        <select name="product_type" id="product_type">
                            <?php foreach ($PROD_TYPES as $t): ?>
                                <option value="<?php echo prod_e($t); ?>" <?php echo $t === 'خدمة' ? 'selected' : ''; ?>><?php echo prod_e($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="revenue_model"><i class="fas fa-diagram-project"></i> نموذج الإيراد</label>
                        <select name="revenue_model" id="revenue_model">
                            <option value="">-- غير محدد --</option>
                            <?php foreach ($PROD_REVENUE_MODELS as $k => $v): ?>
                                <option value="<?php echo prod_e($k); ?>"><?php echo prod_e($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="default_uom"><i class="fas fa-ruler"></i> وحدة القياس الافتراضية</label>
                        <input type="text" name="default_uom" id="default_uom" placeholder="مثال: ساعة / طن / متر" />
                    </div>
                    <div>
                        <label for="standard_price"><i class="fas fa-money-bill-wave"></i> السعر المرجعي</label>
                        <input type="number" step="0.01" name="standard_price" id="standard_price" placeholder="0.00" />
                    </div>
                    <div>
                        <label for="currency"><i class="fas fa-coins"></i> العملة</label>
                        <select name="currency" id="currency">
                            <?php foreach ($PROD_CURRENCIES as $cur): ?>
                                <option value="<?php echo prod_e($cur); ?>" <?php echo $cur === 'USD' ? 'selected' : ''; ?>><?php echo prod_e($cur); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="prod-col-full">
                        <label for="description"><i class="fas fa-align-left"></i> الوصف</label>
                        <textarea name="description" id="description" rows="2" placeholder="أي وصف إضافي"></textarea>
                    </div>
                </div>
                <div class="pu-form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-save"></i> <span id="submitBtnText">حفظ المنتج</span></button>
                    <button type="button" id="prodFormCancelBtn" class="btn-secondary"><i class="fas fa-times"></i> إلغاء</button>
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
                <label for="filterType"><i class="fa fa-tag"></i> النوع</label>
                <select id="filterType" class="form-control">
                    <option value="">-- كل الأنواع --</option>
                </select>
            </div>
            <div class="filter-field">
                <label for="filterModel"><i class="fa fa-diagram-project"></i> نموذج الإيراد</label>
                <select id="filterModel" class="form-control">
                    <option value="">-- كل النماذج --</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="button" class="btn-primary"><i class="fa fa-search"></i> تطبيق</button>
                <button type="button" class="btn-secondary" title="إعادة تعيين"><i class="fa fa-rotate-right"></i></button>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-container">
                <table id="prodTable" class="display prod-table-nowrap no-datatable" data-state-save="false">
                    <thead>
                        <tr>
                            <th>إجراءات</th>
                            <th width="90">كود البند</th>
                            <th>اسم الخدمة</th>
                            <th>النوع</th>
                            <th>نموذج الإيراد</th>
                            <th>وحدة القياس</th>
                            <th>السعر المرجعي</th>
                            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                            <th class="ems-fn-th" data-fn="1">الفئة</th>
                            <th class="ems-fn-th" data-fn="1">نموذج التسعير</th>
                            <th class="ems-fn-th" data-fn="1">الحساب الإيرادي</th>
                            <th class="ems-fn-th" data-fn="1">مركز الإيراد</th>
                            <th class="ems-fn-th" data-fn="1">نسبة الضريبة</th>
                            <th class="ems-fn-th" data-fn="1">قابل للخصم؟</th>
                            <th class="ems-fn-th" data-fn="1">الحد الأدنى للسعر</th>
                            <th class="ems-fn-th" data-fn="1">العملة الافتراضية</th>
                            <th class="ems-fn-th" data-fn="1">العقود المستعملة</th>
                            <th class="ems-fn-th" data-fn="1">عرفه</th>
                            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                            <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                            <th class="ems-gov-th" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                            <th class="ems-gov-th" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
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
        // تهيئةُ الجدول انتقلت إلى المكوّنِ المركزي (ui-unification.js) —
        // وتعطيلُ حفظِ الحالة بقي بسمةِ data-state-save="false" على وسمِ الجدول.
        function bindProdFilters() {
        const prodTable = $('#prodTable').DataTable();

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
        }

        // الربط بعد تهيئة المكون المركزي للجدول (أو فورا إن سبقنا)
        if ($.fn.dataTable && $.fn.dataTable.isDataTable('#prodTable')) {
            bindProdFilters();
        } else {
            $('#prodTable').one('init.dt', bindProdFilters);
        }
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

    /**
     * إظهار حقل الكود المولد وإخفاؤه.
     *
     * ⚠️ **لا تستعمل `jQuery.hide()` هنا** — `assets/css/ems-forms.css` يحمل:
     *     :is(.allforms, .ems-form) .form-grid > div { display: block !important }
     * والغلاف ابن مباشر ل`.form-grid`، ف`!important` من ورقة الأنماط تهزم
     * الإخفاء السطري **بلا أولوية**: السمة تكتب فعلا والحقل يبقى ظاهرا، بلا
     * خطأ في وحدة التحكم ولا سطر في أي سجل. (نظير شاشتي العملاء والمشاريع.)
     */
    function setGeneratedCodeShown(shown) {
        var el = generatedCodeWrapper[0];
        if (!el) { return; }
        if (shown) { el.style.removeProperty('display'); }
        else       { el.style.setProperty('display', 'none', 'important'); }
    }
    function setAddMode() {
        formTitle.text('إضافة منتج جديد'); submitBtnText.text('حفظ المنتج');
        setGeneratedCodeShown(true);
        // الكود المولد يعود إلى خانته كلما دخلنا وضع الإضافة — ومصدره حقل
        // العرض نفسه لا نسخة ثانية منه (مصدر حقيقة واحد). و`reset()` يكفي
        // للإلغاء، لكن الانتقال من «تعديل» إلى «إضافة» قد يقع بلا reset.
        var genCode = $('#generated_prod_code').val();
        if (genCode) { $('#product_code').val(genCode); }
    }
    function setEditMode() { formTitle.text('تعديل المنتج'); submitBtnText.text('تحديث المنتج'); setGeneratedCodeShown(false); }
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

    // ── عرض التفاصيل عبر EmsDetailsModal الموحد ──
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

<?php /* نُقلت أنماطُ هذه الشاشةِ إلى assets/css/ems-screens.css (UXUI-01 البند ٦: صفرُ نمطٍ محليّ) */ ?>

</body>

</html>

<?php
// ح-09 · نافذةُ معالج الاستيراد وأصولُ الإطار (مرة واحدة)
if (function_exists('ems_excel_render')) { ems_excel_render('products'); }
