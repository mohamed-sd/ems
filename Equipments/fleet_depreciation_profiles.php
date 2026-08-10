<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';

$perms = get_page_permissions($conn);
if (!$perms['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', '❌ لا توجد صلاحية لعرض هذه الصفحة', 'GOV-PERM-403', '');
    exit();
}

// صلاحية الاعتماد = صلاحية الحذف (مستوى الإدارة لا الإشراف) — لا يوجد دور مالي مستقل في النظام
$can_approve = !empty($perms['can_delete']);

// ── عزل الشركة ───────────────────────────────────────────────────────
$current_role   = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id     = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$user_id        = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}
$company_val   = $is_super_admin ? null : $company_id;
$company_scope = $is_super_admin ? '' : " AND company_id = $company_id";
// بوابة الوحدة: super → forAllTenants (يوافق company_scope='')؛ غير super → سياق الجلسة
$dep_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('fleet depreciation super') : ems_tenant_db();

$has_model_table = function_exists('db_table_has_column') ? db_table_has_column($conn, 'fleet_model', 'id') : true;

/** أثر تدقيقي: يحفظ لقطة قبل/بعد كل تغيير */
function dep_audit($conn, $profile_id, $company_val, $action, $old, $new, $changed_by, $note = null)
{
    $oj = ($old !== null) ? json_encode($old, JSON_UNESCAPED_UNICODE) : null;
    $nj = ($new !== null) ? json_encode($new, JSON_UNESCAPED_UNICODE) : null;
    // company_id يُحقنه البوابة (super = crossTenant). audit soft=false — أثر تدقيقي best-effort كالأصل.
    $g = ($company_val === null) ? ems_tenant_db()->forAllTenants('dep audit super') : ems_tenant_db();
    try {
        $g->insert('fleet_depreciation_profile_audit', array(
            'profile_id' => $profile_id, 'action' => $action, 'changed_by' => $changed_by,
            'old_data' => $oj, 'new_data' => $nj, 'note' => $note));
    } catch (\Throwable $t) { /* بلا فحص كالأصل */ }
}

/** توليد كود تسلسلي تلقائي DEP-### ضمن الشركة (includeDeleted — يوافق الأصل بلا فلتر حذف) */
function dep_next_code($conn, $is_super, $company_id)
{
    $g = $is_super ? ems_tenant_db()->forAllTenants('dep code super') : ems_tenant_db();
    $rows = $g->select('fleet_depreciation_profile', array('columns' => array('code'), 'whereRaw' => "code REGEXP '^DEP-[0-9]+$'", 'includeDeleted' => true));
    $max = 0;
    foreach ($rows as $r) { $n = intval(substr($r['code'], 4)); if ($n > $max) $max = $n; }
    return 'DEP-' . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
}

/** قراءة صف ملف ضمن نطاق الشركة (company_scope==='' ⇒ super) */
function dep_fetch($conn, $id, $company_scope)
{
    $g = ($company_scope === '') ? ems_tenant_db()->forAllTenants('dep fetch super') : ems_tenant_db();
    return $g->selectOne('fleet_depreciation_profile', array('where' => array('id' => intval($id))));
}

$errors = [];
$flash  = isset($_GET['msg']) ? $_GET['msg'] : '';

// ── حذف ناعم (تعطيل) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!$perms['can_delete']) {
        ems_gov_flash_redirect('fleet_depreciation_profiles.php', '❌ لا توجد صلاحية للحذف', 'GOV-PERM-403', '');
        exit();
    }
    $del_id = (int) $_POST['delete_id'];
    $old = dep_fetch($conn, $del_id, $company_scope);
    if ($old) {
        // حذف ناعم (is_deleted=1 فقط كالأصل، بلا deleted_at/by)
        $dep_gate->update('fleet_depreciation_profile', array('is_deleted' => 1), array('id' => $del_id));
        dep_audit($conn, $del_id, $company_val, 'disabled', $old, null, $user_id, 'تعطيل ناعم');
    }
    ems_gov_flash_redirect('fleet_depreciation_profiles.php', '🗑️ تم تعطيل الملف', 'GOV-OK-200', '');
    exit();
}

// ── اعتماد ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    if (!$can_approve) {
        ems_gov_flash_redirect('fleet_depreciation_profiles.php', '❌ لا توجد صلاحية للاعتماد', 'GOV-PERM-403', '');
        exit();
    }
    $app_id = (int) $_POST['approve_id'];
    $old = dep_fetch($conn, $app_id, $company_scope);
    // P1-B — «من أنشأ لا يعتمد»: ملفُّ الإهلاكِ يحكم قيمةَ الأصلِ في الدفاتر.
    if ($old && $old['state'] !== 'approved') {
        require_once __DIR__ . '/../includes/self_approval_guard.php';
        $__sa = ems_no_self_approval($conn, intval($old['created_by'] ?? 0), intval($user_id),
            'ملفُّ إهلاكٍ #' . $app_id, intval($old['company_id'] ?? 0));
        if ($__sa !== null) {
            ems_gov_flash_redirect('fleet_depreciation_profiles.php', $__sa['reason'], 'GOV-PERM-403', '');
            exit();
        }
    }
    if ($old && $old['state'] !== 'approved') {
        $dep_gate->update('fleet_depreciation_profile',
            array('state' => 'approved', 'approved_by' => $user_id, 'approved_at' => date('Y-m-d H:i:s')),
            array('id' => $app_id), "state = 'draft'");
        $new = dep_fetch($conn, $app_id, $company_scope);
        dep_audit($conn, $app_id, $company_val, 'approved', $old, $new, $user_id, 'اعتماد الملف');
        ems_gov_flash_redirect('fleet_depreciation_profiles.php', '✅ تم اعتماد الملف', 'GOV-OK-200', '');
        exit();
    }
    ems_gov_flash_redirect('fleet_depreciation_profiles.php', 'الملف معتمد مسبقاً', 'GOV-INFO-200', '');
    exit();
}

// ── إضافة / تعديل ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id        = !empty($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;
    $asset_category = trim($_POST['asset_category'] ?? '');
    $brand          = trim($_POST['brand'] ?? '');
    $model_id       = (isset($_POST['model_id']) && $_POST['model_id'] !== '') ? (int) $_POST['model_id'] : null;
    $method         = (($_POST['method'] ?? 'uop') === 'sl') ? 'sl' : 'uop';
    $useful_life    = ($_POST['useful_life'] ?? '') !== '' ? (float) $_POST['useful_life'] : null;
    $salvage_pct    = ($_POST['salvage_pct'] ?? '') !== '' ? (float) $_POST['salvage_pct'] : null;
    $notes          = trim($_POST['notes'] ?? '');

    if ($asset_category === '') $errors[] = 'فئة الأصل مطلوبة';
    if ($useful_life === null || $useful_life <= 0) $errors[] = 'العمر الإنتاجي يجب أن يكون أكبر من صفر';
    if ($salvage_pct === null || $salvage_pct < 0 || $salvage_pct > 1) $errors[] = 'نسبة التخريد يجب أن تكون بين 0 و 1';

    // التحقّق من الملكية + حماية الملفات المعتمدة
    $old_row = null;
    if (empty($errors) && $edit_id > 0) {
        $old_row = dep_fetch($conn, $edit_id, $company_scope);
        if (!$old_row) {
            $errors[] = 'الملف غير موجود أو لا يخصّ شركتك';
        } elseif ($old_row['state'] === 'approved' && !$can_approve) {
            // تعديل قيم ملف معتمد يتطلّب صلاحية الاعتماد (مستوى الإدارة)
            $errors[] = 'تعديل ملف معتمد يتطلّب صلاحية الاعتماد';
        }
    }

    if (empty($errors)) {
        if ($edit_id > 0) {
            $dep_gate->update('fleet_depreciation_profile', array(
                'asset_category' => $asset_category, 'brand' => $brand, 'model_id' => $model_id, 'method' => $method,
                'useful_life' => $useful_life, 'salvage_pct' => $salvage_pct, 'notes' => $notes,
            ), array('id' => $edit_id), "is_deleted = 0");
            $new_row = dep_fetch($conn, $edit_id, $company_scope);
            // أثر تدقيقي بأثر مستقبلي: لا حذف صامت للقيمة القديمة
            dep_audit($conn, $edit_id, $company_val, 'updated', $old_row, $new_row, $user_id, 'تعديل يسري مستقبلاً فقط');
            ems_gov_flash_redirect('fleet_depreciation_profiles.php', '✅ تم تحديث الملف (يسري على الحساب اللاحق فقط)', 'GOV-OK-200', '');
            exit();
        } else {
            $code = dep_next_code($conn, $is_super_admin, $company_id);
            $new_id = $dep_gate->insert('fleet_depreciation_profile', array(
                'code' => $code, 'asset_category' => $asset_category, 'brand' => $brand, 'model_id' => $model_id,
                'method' => $method, 'useful_life' => $useful_life, 'salvage_pct' => $salvage_pct, 'notes' => $notes,
                'state' => 'draft', 'created_by' => $user_id)); // company_id يُحقن بالبوابة
            $new_row = dep_fetch($conn, $new_id, $company_scope);
            dep_audit($conn, $new_id, $company_val, 'created', null, $new_row, $user_id, 'إنشاء ملف (مسودة)');
            ems_gov_flash_redirect('fleet_depreciation_profiles.php', '✅ تم إضافة الملف (مسودة)', 'GOV-OK-200', '');
            exit();
        }
    }
}

// ── بيانات التعديل ───────────────────────────────────────────────────
$editData = null;
if (isset($_GET['edit_id'])) {
    $editData = dep_fetch($conn, (int) $_GET['edit_id'], $company_scope);
}

// ── قائمة الموديلات (لربط اختياري داخل الملف) ────────────────────────
$models = [];
if ($has_model_table) {
    // fleet_model soft=true فالبوابة تستبعد is_deleted تلقائيًّا (يوافق الأصل)
    $models = $dep_gate->select('fleet_model', array('columns' => array('id', 'code', 'model_name'), 'orderBy' => 'code ASC'));
}

$page_title = "إيكوبيشن | إعداد الإهلاك";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($perms) ? $perms : null);
include("../inheader.php");
include("../insidebar.php");
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }

$e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$method_label = function ($m) { return $m === 'sl' ? 'زمني (سنوات)' : 'بالساعة التشغيلية'; };
?>

<div class="main fleet-dep-main" style="padding:15px;background:#fff;">

    <?php
    $header_title   = 'إعداد الإهلاك';
    $header_icon    = 'fas fa-coins';
    $header_actions = array();
    if ($perms['can_add']) {
        $header_actions[] = array('tag' => 'button', 'id' => 'toggleForm', 'class' => 'add', 'icon' => 'fa-solid fa-plus-circle', 'label' => 'إضافة ملف جديد');
    }
    require_once __DIR__ . '/../includes/excel_ui.php';
    foreach (ems_excel_header_actions('fleet_depreciation_profiles', 'ملف الإهلاك المالي', $perms['can_add']) as $__xlAction) { $header_actions[] = $__xlAction; }
    $header_back = array('href' => '../main/dashboard.php', 'class' => '', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include('../includes/page_header.php');
    ?>

    <?php if (!empty($flash)): ?>
        <div class="success-message is-success" style="margin:10px 0;"><?= $e($flash); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="success-message is-error" style="margin:10px 0;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= $e(implode(' — ', $errors)); ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info" style="background:#eef6ff;border:1px solid #cfe3fb;border-radius:8px;padding:10px 14px;margin:10px 0;font-size:13px;color:#1c4e80;">
        <i class="fa-solid fa-circle-info"></i>
        الملف ملك الإدارة المالية واعتماده لمستوى الإدارة. تعديل الافتراض <b>يسري على الحساب اللاحق فقط</b> ولا يُعدّل الإهلاك المُرحَّل، وكل تعديل يُسجَّل في الأثر التدقيقي.
    </div>

    <!-- نموذج إضافة / تعديل -->
    <form id="projectForm" method="post" class="allforms<?= (!empty($editData) || !empty($errors)) ? ' allforms-visible' : ''; ?>" style="margin:10px;">
    <div class="card-header">
                <h5><i class="fas fa-coins"></i> <?= !empty($editData) ? 'تعديل الملف' : 'إضافة ملف جديد'; ?>
                    <?php if (!empty($editData) && $editData['state'] === 'approved'): ?>
                        <span class="status-active" style="margin-inline-start:10px"><i class="fas fa-check-circle"></i> معتمد</span>
                    <?php elseif (!empty($editData)): ?>
                        <span class="status-inactive" style="margin-inline-start:10px">مسودة</span>
                    <?php endif; ?>
                </h5>
            </div>
    <div class="card">
            <div class="card-body">
                <?php if (!empty($editData)): ?>
                    <input type="hidden" name="edit_id" value="<?= (int) $editData['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <?php if (!empty($editData)): ?>
                    <div>
                        <label for="emsf_121_5c01c">كود الملف</label>
                        <input type="text" value="<?= $e($editData['code']); ?>" readonly style="background:#f5f5f5" id="emsf_121_5c01c">
                    </div>
                    <?php else: ?>
                    <div>
                        <label for="emsf_122_6d615">كود الملف</label>
                        <input type="text" value="(يُولّد تلقائياً)" readonly style="background:#f5f5f5;color:#888" id="emsf_122_6d615">
                    </div>
                    <?php endif; ?>

                    <div>
                        <label for="emsf_123_c0aa3">فئة الأصل <span style="color:#c0392b">*</span></label>
                        <input type="text" name="asset_category" required
                               placeholder="مثال: حفّار 22ط جديد"
                               value="<?= $e($editData['asset_category'] ?? ''); ?>" id="emsf_123_c0aa3">
                    </div>

                    <div>
                        <label for="emsf_124_da80e">الماركة (اختياري)</label>
                        <input type="text" name="brand" value="<?= $e($editData['brand'] ?? ''); ?>" id="emsf_124_da80e">
                    </div>

                    <div>
                        <label for="emsf_125_6bf97">الموديل المرتبط (اختياري)</label>
                        <select name="model_id" id="emsf_125_6bf97">
                            <option value="">-- بدون --</option>
                            <?php foreach ($models as $m): ?>
                                <option value="<?= (int) $m['id']; ?>"
                                    <?= (!empty($editData) && (int) $editData['model_id'] === (int) $m['id']) ? 'selected' : ''; ?>>
                                    <?= $e($m['code'] . ' — ' . $m['model_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="methodSelect">طريقة الإهلاك <span style="color:#c0392b">*</span></label>
                        <select name="method" id="methodSelect" required>
                            <option value="uop" <?= (!empty($editData) && $editData['method'] === 'uop') ? 'selected' : ''; ?>>بالساعة التشغيلية (UOP)</option>
                            <option value="sl"  <?= (!empty($editData) && $editData['method'] === 'sl') ? 'selected' : ''; ?>>زمني بالسنوات (SL)</option>
                        </select>
                    </div>

                    <div>
                        <label id="usefulLifeLabel" for="emsf_126_93c46">العمر الإنتاجي <span style="color:#c0392b">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="useful_life" required
                               value="<?= $e($editData['useful_life'] ?? ''); ?>" id="emsf_126_93c46">
                    </div>

                    <div>
                        <label for="emsf_127_6b647">نسبة التخريد (0 إلى 1) <span style="color:#c0392b">*</span></label>
                        <input type="number" step="0.0001" min="0" max="1" name="salvage_pct" required
                               placeholder="مثال: 0.08"
                               value="<?= $e($editData['salvage_pct'] ?? ''); ?>" id="emsf_127_6b647">
                    </div>

                    <div style="grid-column:1/-1">
                        <label for="emsf_128_508e5">ملاحظات / سياسات مالية</label>
                        <textarea name="notes" rows="2" id="emsf_128_508e5"><?= $e($editData['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="pu-form-actions">
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> حفظ</button>
                    <button type="button" id="depFormCancel" class="btn-cancel"<?= !empty($editData) ? ' data-redirect="fleet_depreciation_profiles.php"' : ''; ?>>
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول الملفات -->
    <div class="card">
         <div class="card-body">
            <div class="table-container">
                <table id="projectsTable" class="display fleet-dep-table">
                    <thead>
                        <tr>
                            <th>الإجراءات</th>
                            <th>#</th>
                            <th>كود السياسة</th>
                            <th>فئة الأصل</th>
                            <th>الماركة/الموديل</th>
                            <th>طريقة الإهلاك</th>
                            <th>العمر الإنتاجي</th>
                            <th>التخريد</th>
                            <th>الحالة</th>
                            <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                            <th class="ems-fn-th" data-fn="1">فئة الأصول</th>
                            <th class="ems-fn-th" data-fn="1">وحدة العمر</th>
                            <th class="ems-fn-th" data-fn="1">نسبة قيمة الخردة</th>
                            <th class="ems-fn-th" data-fn="1">بداية الإهلاك</th>
                            <th class="ems-fn-th" data-fn="1">معالجة الإضافات</th>
                            <th class="ems-fn-th" data-fn="1">معالجة الإخراج</th>
                            <th class="ems-fn-th" data-fn="1">الحساب المحاسبي</th>
                            <th class="ems-fn-th" data-fn="1">حساب المجمع</th>
                            <th class="ems-fn-th" data-fn="1">من تاريخ</th>
                            <th class="ems-fn-th" data-fn="1">اعتمدها</th>
                            <th class="ems-fn-th" data-fn="1">المرجع المحاسبي</th>
                            <th class="ems-fn-th" data-fn="1">نسخة القاعدة المستعملة</th>
                            <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                            <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                            <th class="ems-gov-th none" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                            <th class="ems-gov-th none" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                            <th class="ems-gov-th none" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                            <th class="ems-gov-th none" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                            <th class="ems-gov-th none" data-gov="idem_key" data-slice="2" title="يمنع وقوع الأثر مرتين بمفتاح مركب">مفتاح منع التكرار</th>
                            <th class="ems-gov-th none" data-gov="reversed_by" data-slice="2" title="مرجع الحركة التي عكسته">معكوس بـ</th>
                            <th class="ems-gov-th none" data-gov="reversal_of" data-slice="2" title="مرجع الحركة التي عكسها">عكس عن</th>
                            <th class="ems-gov-th none" data-gov="impact_grade" data-slice="2" title="مبدئي أم نهائي — فلا يقفل مبدئي ماليًّا">درجة الأثر</th>
                            <th class="ems-gov-th none" data-gov="view_log" data-slice="2" title="من قرأ البيان الحساس ومتى">سجل الاطّلاع</th>
                            <th class="ems-gov-th none" data-gov="attachment" data-slice="3" title="مستند الإثبات الخارجي">المرفق</th>
                            <th class="ems-gov-th none" data-gov="cost_center" data-slice="3" title="وجهة التحميل">مركز التكلفة</th>
                            <th class="ems-gov-th none" data-gov="fx_rate_source" data-slice="3" title="ما خالف عملة الدفاتر يحمل السعر ومصدره">سعر الصرف ومصدره</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php
                        // القائمة عبر scopedQuery §10: عزل على p + إثراء LEFT بالموديل
                        $dep_list = $dep_gate->scopedQuery(
                            array('scope' => array('p' => 'fleet_depreciation_profile'), 'enrich' => array('fm' => 'fleet_model')),
                            "SELECT p.*, fm.code AS model_code, fm.model_name AS model_name
                               FROM fleet_depreciation_profile p
                               LEFT JOIN fleet_model fm ON fm.id = p.model_id
                              WHERE {TENANT_SCOPE} AND p.is_deleted = 0
                              ORDER BY p.id DESC");
                        $i = 1;
                        foreach ($dep_list as $row):
                            $unit = $row['method'] === 'sl' ? 'سنة' : 'ساعة';
                            $brand_model = trim(($row['brand'] ?? '') . (!empty($row['model_code']) ? (($row['brand'] ? ' / ' : '') . $row['model_code']) : ''));
                            ?>
                            <tr>
                                <td class="text-center">
                                    <div class="action-btns">
                                        <?php if ($perms['can_edit']): ?>
                                            <a href="fleet_depreciation_profiles.php?edit_id=<?= (int) $row['id']; ?>" class="action-btn edit" title="تعديل">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($can_approve && $row['state'] === 'draft'): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('اعتماد هذا الملف؟ سيصبح متاحاً للربط بالموديلات.');">
                                                <input type="hidden" name="approve_id" value="<?= (int) $row['id']; ?>">
                                                <button type="submit" class="action-btn" style="color:#1f9d55" title="اعتماد">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($perms['can_delete']): ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('تعطيل هذا الملف؟');">
                                                <input type="hidden" name="delete_id" value="<?= (int) $row['id']; ?>">
                                                <button type="submit" class="action-btn delete" title="تعطيل"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= $i++; ?></td>
                                <td><?= $e($row['code']); ?></td>
                                <td><?= $e($row['asset_category']); ?></td>
                                <td><?= $e($brand_model !== '' ? $brand_model : '—'); ?></td>
                                <td><?= $e($method_label($row['method'])); ?></td>
                                <td><?= $e(rtrim(rtrim(number_format((float) $row['useful_life'], 2, '.', ''), '0'), '.')); ?> <?= $e($unit); ?></td>
                                <td><?= $e(rtrim(rtrim(number_format((float) $row['salvage_pct'] * 100, 2, '.', ''), '0'), '.')); ?>%</td>
                                <td>
                                    <?= $row['state'] === 'approved'
                                        ? "<span class='status-active'><i class='fas fa-check-circle'></i> معتمد</span>"
                                        : "<span class='status-inactive'>مسودة</span>"; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../includes/js/jquery-3.7.1.main.js"></script>
<script src="../includes/js/jquery.dataTables.main.js"></script>
<script>
    $(document).ready(function () {
        $('#projectsTable').DataTable({
            language: { url: "/ems/assets/i18n/datatables/ar.json" }
        });

        $('#toggleForm').on('click', function () {
            const $form = $('#projectForm');
            if ($form.hasClass('allforms-visible')) {
                $form.removeClass('allforms-visible').slideUp(200);
            } else {
                $form.addClass('allforms-visible').hide().slideDown(250);
            }
        });

        // زر الإلغاء: في وضع التعديل يعود للقائمة، وفي وضع الإضافة يطوي النموذج
        $('#depFormCancel').on('click', function () {
            const redirect = $(this).data('redirect');
            if (redirect) { window.location.href = redirect; return; }
            $('#projectForm').removeClass('allforms-visible').slideUp(200);
        });

        // تبديل وحدة العمر الإنتاجي حسب الطريقة
        function syncUnit() {
            var m = document.getElementById('methodSelect').value;
            var lbl = document.getElementById('usefulLifeLabel');
            lbl.innerHTML = (m === 'sl' ? 'العمر الإنتاجي (سنوات)' : 'العمر الإنتاجي (ساعة تشغيلية)') + ' <span style="color:#c0392b">*</span>';
        }
        var ms = document.getElementById('methodSelect');
        if (ms) { ms.addEventListener('change', syncUnit); syncUnit(); }
    });
</script>
<?php if (function_exists('ems_excel_render')) { ems_excel_render(); } ?>
</body>

</html>
