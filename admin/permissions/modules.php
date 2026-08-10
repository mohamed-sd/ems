<?php
require_once __DIR__ . '/../includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'إدارة الصفحات والمديولات';
$current_page = 'permissions';

include '../config.php';

// أعمدة icon/display_order/is_quick قائمة كلها (مثبَّتة من القاعدة) — سقط فحص SHOW COLUMNS
$module_has_icon_column = true;
$module_has_display_order_column = true;
$module_has_is_quick_column = true;
$default_module_icon = 'fa fa-link';
// كونسول المزوّد: modules مرجعٌ عام، وكتابته من هنا (بهوية المدير الأعلى العابرة) هي
// الموضع الشرعي في العقد. القراءة والكتابة عبر ems_platform_db().
$mp_pg = ems_platform_db();
$common_sidebar_icons = array(
    array('class' => 'fa fa-link', 'label' => 'رابط عام'),
    array('class' => 'fa fa-home', 'label' => 'الرئيسية'),
    array('class' => 'fa fa-users', 'label' => 'العملاء'),
    array('class' => 'fa fa-user-shield', 'label' => 'الصلاحيات'),
    array('class' => 'fa fa-users-cog', 'label' => 'المستخدمون'),
    array('class' => 'fa fa-folder-open', 'label' => 'المشاريع'),
    array('class' => 'fa fa-list-alt', 'label' => 'القوائم'),
    array('class' => 'fa fa-mountain', 'label' => 'المناجم'),
    array('class' => 'fa fa-truck-loading', 'label' => 'الموردون'),
    array('class' => 'fa fa-tractor', 'label' => 'المعدات'),
    array('class' => 'fa fa-truck-moving', 'label' => 'الأسطول'),
    array('class' => 'fa fa-id-card', 'label' => 'المشغلون'),
    array('class' => 'fa fa-cogs', 'label' => 'التشغيل'),
    array('class' => 'fa fa-business-time', 'label' => 'ساعات العمل'),
    array('class' => 'fa fa-calendar-days', 'label' => 'ساعات اليوم'),
    array('class' => 'fa fa-file-contract', 'label' => 'العقود'),
    array('class' => 'fa fa-check-double', 'label' => 'الموافقات'),
    array('class' => 'fa fa-chart-pie', 'label' => 'التقارير'),
    array('class' => 'fa fa-chart-line', 'label' => 'تحليل'),
    array('class' => 'fa fa-chart-column', 'label' => 'إحصائيات'),
    array('class' => 'fa fa-gear', 'label' => 'الإعدادات'),
    array('class' => 'fa fa-key', 'label' => 'الأمان'),
    array('class' => 'fa fa-clipboard-list', 'label' => 'نماذج'),
    array('class' => 'fa fa-screwdriver-wrench', 'label' => 'الأنواع'),
    array('class' => 'fa fa-hard-hat', 'label' => 'الموقع'),
    array('class' => 'fa fa-layer-group', 'label' => 'مديولات'),
    array('class' => 'fa fa-database', 'label' => 'بيانات'),
    array('class' => 'fa fa-bell', 'label' => 'تنبيهات')
);

// جلب role_id من URL إن وجد (للانتقال من صفحة الأدوار)
$selected_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

/* جلب بيانات التعديل */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    try {
        $editData = $mp_pg->selectOne('modules', array(
            'columns' => array('id', 'name', 'code', 'owner_role_id', 'group_id', 'is_link', 'icon', 'display_order', 'is_quick'),
            'where' => array('id' => $id)));
    } catch (\Throwable $t) { $editData = null; error_log('admin/permissions/modules edit: ' . $t->getMessage()); }
    if ($editData && !isset($editData['icon'])) {
        $editData['icon'] = $default_module_icon;
    }
}

/* إضافة / تعديل */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $owner_role_id = !empty($_POST['owner_role_id']) ? (int)$_POST['owner_role_id'] : null;
    // مجموعة الرابط — فارغةٌ تعني «المستوى الأعلى» (السلوك القديم)
    $group_id = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $is_link = isset($_POST['is_link']) && $_POST['is_link'] == '1' ? 1 : 0;
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    $icon = trim($_POST['icon'] ?? $default_module_icon);
    $icon = preg_replace('/[^a-zA-Z0-9\-\s]/', '', $icon);
    $icon = trim(preg_replace('/\s+/', ' ', $icon));
    if ($icon === '') {
        $icon = $default_module_icon;
    }

    // التحقق من صحة البيانات
    if (empty($name) || empty($code)) {
        $error_msg = 'اسم الصفحة والكود مطلوبان ❌';
    } else {
        $mp_is_quick = (isset($_POST['is_quick']) && $_POST['is_quick'] == '1') ? 1 : 0;
        try {
            if (!empty($_POST['edit_id'])) {
                // كتابة مرجعٍ عام بهوية المدير الأعلى العابرة (الموضع الشرعي للعقد)
                $mp_pg->update('modules', array(
                    'name' => $name, 'code' => $code, 'owner_role_id' => $owner_role_id,
                    'group_id' => $group_id,
                    'is_link' => $is_link, 'icon' => $icon, 'display_order' => $display_order,
                    'is_quick' => $mp_is_quick), array('id' => (int) $_POST['edit_id']));
                header("Location: modules.php?msg=تم+البحفاظ+على+البيانات+بنجاح+✔");
                exit;
            } else {
                // التحقق من عدم تكرار نفس الصفحة لنفس الدور (قراءة عبر البوابة)
                $mp_dup = $mp_pg->selectOne('modules', array('columns' => array('id'),
                    'whereRaw' => '`code` = ? AND `owner_role_id` <=> ?', 'params' => array($code, $owner_role_id)));
                if ($mp_dup !== null) {
                    $error_msg = 'هذه الصفحة مضافة مسبقاً لنفس الدور المسؤول ❌';
                } else {
                    $mp_pg->insert('modules', array(
                        'name' => $name, 'code' => $code, 'owner_role_id' => $owner_role_id,
                        'group_id' => $group_id,
                        'is_link' => $is_link, 'icon' => $icon, 'display_order' => $display_order,
                        'is_quick' => $mp_is_quick));
                    header("Location: modules.php?msg=تم+البحفاظ+على+البيانات+بنجاح+✔");
                    exit;
                }
            }
        } catch (\Throwable $t) {
            error_log('admin/permissions/modules save: ' . $t->getMessage());
            if (!isset($error_msg)) { $error_msg = 'حدث خطأ في الحفظ ❌'; }
        }
    }
}

/* حذف */
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    // [مُستثنى موثَّق — حذف صف مرجعٍ عام] لا قناة حذفٍ للمراجع العامة بعد
    $stmt = $conn->prepare("DELETE FROM `modules` WHERE `id` = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: modules.php?msg=تم+حذف+الصفحة+بنجاح+✔");
    } else {
        header("Location: modules.php?msg=حدث+خطأ+في+الحذف+❌");
    }
    exit;
}

// جلب جميع الأدوار (عبر البوابة)
$roles = array();
try {
    $roles = $mp_pg->select('roles', array('columns' => array('id', 'name'),
        'where' => array('parent_role_id' => null), 'orderBy' => 'level, name'));
} catch (\Throwable $t) { error_log('admin/permissions/modules roles: ' . $t->getMessage()); }

// مجموعات الروابط — تُعرض في النموذج مفلترةً بدور الشاشة (لكل دورٍ مجموعاته)،
// وفي الجدول كاسمٍ بجانب كل شاشة.
$link_groups = array();
try {
    $link_groups = $mp_pg->select('link_groups', array(
        'columns' => array('id', 'name', 'owner_role_id', 'is_active'),
        'orderBy' => 'display_order ASC, name ASC'));
} catch (\Throwable $t) { error_log('admin/permissions/modules link_groups: ' . $t->getMessage()); }

$link_group_names = array();
foreach ($link_groups as $lg) { $link_group_names[(int) $lg['id']] = $lg['name']; }

require_once __DIR__ . '/../includes/layout_head.php';
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/buttons.dataTables.min.css">

<style>
.page-shell {
    background: linear-gradient(135deg, #f5f7fa 0%, #f0f2f5 100%);
    min-height: calc(100vh - 100px);
    padding: 2rem;
}

.page-header h2 {
    color: var(--navy);
    font-weight: 700;
    margin-bottom: 2rem;
}

.card {
    background: white;
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(12, 28, 62, 0.08);
    margin-bottom: 2rem;
}

.card-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px 12px 0 0;
    font-weight: 600;
}

.card-body {
    padding: 2rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.form-grid label {
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 0.5rem;
    display: block;
}

.form-grid input,
.form-grid select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'Cairo', sans-serif;
}

.success-message {
    border-radius: 8px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    border-right: 4px solid;
}

.success-message.is-success {
    background: linear-gradient(135deg, #d1f3d1 0%, #c8f0c8 100%);
    color: #059669;
    border-right-color: #059669;
}

.success-message.is-error {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #dc2626;
    border-right-color: #ef4444;
}

.table-container {
    overflow-x: auto;
}

.display thead th {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);
    color: white;
    font-weight: 600;
    border: none;
}

.display tbody tr:hover {
    background-color: #f8f9fa;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.back-btn {
    background: #e5e7eb;
    color: var(--navy);
}

.back-btn:hover {
    background: #d1d5db;
}

.add-btn {
    background: linear-gradient(135deg, var(--blue) 0%, #1d4ed8 100%);
    color: white;
}

.add-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-primary {
    background: var(--blue);
    color: white;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
}

.text-center {
    text-align: center;
}

#moduleForm {
    display: none;
    margin-bottom: 2rem;
}

#moduleForm.show {
    display: block;
}

/* Icon Preview Styles */
#icon_preview {
    transition: all 0.3s ease;
}

#icon_preview:hover {
    border-color: var(--blue);
    background: #f0f4ff;
}

#icon_preview i {
    transition: all 0.3s ease;
}

input[type="radio"] {
    cursor: pointer;
}
</style>

<div class="page-shell">
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h2 style="margin: 0;">
            <i class="fas fa-layer-group"></i> إدارة الصفحات والمديولات
        </h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn">
                <i class="fas fa-plus-circle"></i> إضافة صفحة جديدة
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✔') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="success-message is-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <!-- نموذج إضافة / تعديل -->
    <form id="moduleForm" action="" method="post" class="ems-form">
        <div class="card">
            <div class="card-header">
                <h5 style="margin: 0;">
                    <i class="fas fa-edit"></i> 
                    <?= !empty($editData) ? 'تعديل الصفحة' : 'إضافة صفحة جديدة'; ?>
                </h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="edit_id" id="edit_id" value="<?= htmlspecialchars($editData['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-grid">
                    <!-- اسم الصفحة -->
                    <div>
                        <label for="name"><i class="fas fa-book"></i> اسم الصفحة *</label>
                        <input type="text" name="name" id="name" placeholder="مثال: إدارة العملاء" 
                               value="<?= htmlspecialchars($editData['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    </div>

                    <!-- كود الصفحة -->
                    <div>
                        <label for="code"><i class="fas fa-code"></i> كود الصفحة *</label>
                        <input type="text" name="code" id="code" placeholder="مثال: clients" 
                               value="<?= htmlspecialchars($editData['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    </div>

                    <!-- الدور المسؤول -->
                    <div>
                        <label for="owner_role_id"><i class="fas fa-user-tie"></i> الدور المسؤول *</label>
                        <select name="owner_role_id" id="owner_role_id" required>
                            <option value="">-- اختر الدور المسؤول --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id']; ?>" 
                                    <?= ($selected_role_id && $selected_role_id == $role['id'] && !$editData) ? 'selected' : ''; ?>
                                    <?= (!empty($editData) && $editData['owner_role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- مجموعة الرابط -->
                    <div>
                        <label for="group_id"><i class="fas fa-folder-tree"></i> المجموعة</label>
                        <select name="group_id" id="group_id">
                            <option value="" data-role="">— بلا مجموعة (المستوى الأعلى) —</option>
                            <?php foreach ($link_groups as $lg): ?>
                                <option value="<?= (int) $lg['id']; ?>"
                                        data-role="<?= (int) $lg['owner_role_id']; ?>"
                                    <?= (!empty($editData) && (int) ($editData['group_id'] ?? 0) === (int) $lg['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($lg['name'], ENT_QUOTES, 'UTF-8'); ?><?= ((int) $lg['is_active'] === 0) ? ' (معطّلة)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> تُعرض مجموعات الدور المسؤول المختار فقط —
                            <a href="link_groups.php" style="color: var(--blue);">إدارة المجموعات</a>
                        </small>
                    </div>

                    <!-- رابط -->
                    <div style="display: flex; align-items: center; padding-top: 1.5rem;">
                        <input type="checkbox" name="is_link" id="is_link" value="1"
                               <?= (!empty($editData) && $editData['is_link'] == 1) ? 'checked' : ''; ?> />
                        <label for="is_link" style="margin: 0; margin-right: 8px; cursor: pointer;">
                            <i class="fas fa-link"></i> رابط
                        </label>
                    </div>

                    <!-- الوصول السريع -->
                    <div style="display: flex; align-items: center; padding-top: 1.5rem;">
                        <input type="checkbox" name="is_quick" id="is_quick" value="1"
                               <?= (!empty($editData) && isset($editData['is_quick']) && $editData['is_quick'] == 1) ? 'checked' : ''; ?> />
                        <label for="is_quick" style="margin: 0; margin-right: 8px; cursor: pointer;">
                            <i class="fas fa-bolt"></i> يظهر في الوصول السريع
                        </label>
                    </div>

                    <!-- الأيقونة -->
                    <div>
                        <label><i class="fas fa-icons"></i> الأيقونة</label>
                        <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px;">
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; margin: 0;">
                                <input type="radio" name="icon_type" value="list" id="icon_type_list" checked>
                                <span>من القائمة</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; margin: 0;">
                                <input type="radio" name="icon_type" value="custom" id="icon_type_custom">
                                <span>إدخال يدوي</span>
                            </label>
                        </div>
                        
                        <!-- القائمة المنسدلة -->
                        <select name="icon_select" id="icon_select" style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'Cairo', sans-serif;">
                            <option value="">-- اختر الأيقونة --</option>
                            <?php foreach ($common_sidebar_icons as $iconOption): ?>
                                <option value="<?= htmlspecialchars($iconOption['class'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?= (!empty($editData) && $editData['icon'] == $iconOption['class']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($iconOption['label'], ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars($iconOption['class'], ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <!-- حقل الإدخال اليدوي -->
                        <input type="text" name="icon_custom" id="icon_custom" 
                               placeholder="مثال: fas fa-star أو fa fa-chart-line" 
                               style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'Cairo', sans-serif; display: none;"
                               value="<?= htmlspecialchars($editData['icon'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        
                        <!-- الحقل المخفي الذي يُرسل القيمة الفعلية -->
                        <input type="hidden" name="icon" id="icon" value="<?= htmlspecialchars($editData['icon'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                        
                        <!-- معاينة الأيقونة -->
                        <div id="icon_preview" style="margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center; border: 2px dashed #dee2e6;">
                            <i class="<?= htmlspecialchars($editData['icon'] ?? 'fa fa-link', ENT_QUOTES, 'UTF-8'); ?>" 
                               style="font-size: 2.5rem; color: var(--navy);"></i>
                            <div style="margin-top: 8px; font-size: 0.85rem; color: #666;">
                                <code id="icon_preview_text"><?= htmlspecialchars($editData['icon'] ?? 'fa fa-link', ENT_QUOTES, 'UTF-8'); ?></code>
                            </div>
                        </div>
                        
                        <small style="color: #666; display: block; margin-top: 8px;">
                            <i class="fas fa-info-circle"></i> يمكنك استخدام أيقونات من 
                            <a href="https://fontawesome.com/icons" target="_blank" style="color: var(--blue);">FontAwesome</a>
                            (مثل: fas fa-star, far fa-bell, fab fa-github)
                        </small>
                    </div>

                    <!-- الترتيب -->
                    <div>
                        <label><i class="fas fa-sort-numeric-down"></i> الترتيب</label>
                        <input type="number" name="display_order" id="display_order" 
                               placeholder="0" min="0" step="1"
                               value="<?= htmlspecialchars($editData['display_order'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" />
                        <small style="color: #666; display: block; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> الرقم الأصغر يظهر أولاً
                        </small>
                    </div>

                    <button type="submit" class="add-btn" style="grid-column: 1 / -1; justify-self: center;">
                        <i class="fas fa-save"></i> حفظ الصفحة
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول الصفحات -->
    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <h5 style="margin:0;"><i class="fas fa-list"></i> جميع الصفحات والمديولات</h5>
            <div style="display:flex; align-items:center; gap:10px;">
                <label style="font-weight:700; margin:0;" for="roleFilterSelect"><i class="fas fa-user-tie"></i> فلترة حسب الدور:</label>
                <select id="roleFilterSelect" style="padding:7px 14px; border:1.5px solid var(--border); border-radius:var(--radius); font-family:'Cairo',sans-serif; font-size:.88rem; min-width:180px;">
                    <option value="">-- جميع الأدوار --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button id="clearRoleFilter" class="back-btn" style="display:none;" title="مسح الفلتر">
                    <i class="fas fa-times"></i> مسح
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="modulesTable" class="display">
                    <thead>
                        <tr>
                            <th width="80"><i class="fas fa-barcode"></i> #</th>
                            <th width="80"><i class="fas fa-sort-numeric-down"></i> الترتيب</th>
                            <th width="80"><i class="fas fa-icons"></i> الأيقونة</th>
                            <th><i class="fas fa-book"></i> اسم الصفحة</th>
                            <th width="150"><i class="fas fa-code"></i> الكود</th>
                            <th><i class="fas fa-user-tie"></i> الدور المسؤول</th>
                            <th width="140"><i class="fas fa-folder-tree"></i> المجموعة</th>
                            <th width="80"><i class="fas fa-link"></i> رابط</th>
                            <th width="90"><i class="fas fa-bolt"></i> وصول سريع</th>
                            <th width="150"><i class="fas fa-cogs"></i> إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // اسم الدور يُشتق من خريطة id→name (self-JOIN عالمي لا يمرّ scopedQuery)
                        $result = array();
                        try {
                            $result = $mp_pg->select('modules', array(
                                'columns' => array('id', 'name', 'code', 'owner_role_id', 'group_id', 'is_link', 'display_order', 'icon', 'is_quick'),
                                'orderBy' => 'display_order ASC, name ASC'));
                            $mp_role_names = array();
                            foreach ($roles as $mp_r) { $mp_role_names[intval($mp_r['id'])] = $mp_r['name']; }
                            // خريطة كاملة من كل الأدوار (لا الرئيسية فقط) لاسم المالك
                            foreach ($mp_pg->select('roles', array('columns' => array('id', 'name'))) as $mp_ra) {
                                $mp_role_names[intval($mp_ra['id'])] = $mp_ra['name'];
                            }
                            foreach ($result as $rk => $rrow) {
                                $result[$rk]['role_name'] = ($rrow['owner_role_id'] !== null && isset($mp_role_names[intval($rrow['owner_role_id'])]))
                                    ? $mp_role_names[intval($rrow['owner_role_id'])] : null;
                            }
                        } catch (\Throwable $t) { error_log('admin/permissions/modules list: ' . $t->getMessage()); }

                        if ($result) {
                            $i = 1;
                            foreach ($result as $row):
                                ?>
                                <tr>
                                    <td><strong><?= $i++; ?></strong></td>
                                    <td class="text-center">
                                        <span style="display: inline-block; background: var(--blue-soft); color: var(--blue); padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.9rem;">
                                            <?= htmlspecialchars($row['display_order'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        $icon_class = !empty($row['icon']) ? $row['icon'] : 'fa fa-link';
                                        ?>
                                        <i class="<?= htmlspecialchars($icon_class, ENT_QUOTES, 'UTF-8'); ?>" 
                                           style="font-size: 1.5rem; color: var(--navy);" 
                                           title="<?= htmlspecialchars($icon_class, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td>
                                        <code style="background: var(--gold-soft); color: var(--navy); padding: 4px 8px; border-radius: 6px; font-weight: 600;">
                                            <?= htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8'); ?>
                                        </code>
                                    </td>
                                    <td data-search="<?= htmlspecialchars($row['role_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <a href="role_permissions.php?role_id=<?= $row['owner_role_id']; ?>" 
                                           style="color: var(--blue); text-decoration: none; font-weight: 600; transition: all var(--ease);"
                                           title="عرض صلاحيات هذا الدور">
                                            <i class="fas fa-user-shield"></i> 
                                            <?= htmlspecialchars($row['role_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $mp_gid = isset($row['group_id']) && $row['group_id'] !== null ? (int) $row['group_id'] : 0;
                                        if ($mp_gid > 0 && isset($link_group_names[$mp_gid])):
                                            ?>
                                            <span style="display: inline-block; background: var(--gold-soft, #fff4d6); color: #8a6a10; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.85rem;">
                                                <i class="fas fa-folder"></i> <?= htmlspecialchars($link_group_names[$mp_gid], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #bbb; font-weight: 600;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($row['is_link'] == 1): ?>
                                            <span style="display: inline-block; background: var(--teal-soft); color: var(--teal); padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                ✔ نعم
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-block; background: #f0f0f0; color: #999; padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                ✖ لا
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (isset($row['is_quick']) && $row['is_quick'] == 1): ?>
                                            <span style="display: inline-block; background: var(--gold-soft, #fff4d6); color: #b8860b; padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                <i class="fas fa-bolt"></i> نعم
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-block; background: #f0f0f0; color: #999; padding: 4px 8px; border-radius: 4px; font-weight: 600;">
                                                ✖ لا
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="?edit_id=<?= $row['id']; ?>&action=edit" class="btn btn-sm btn-primary" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete_id=<?= $row['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذه الصفحة؟');" class="btn btn-sm btn-danger" title="حذف">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php
                            endforeach;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../../includes/js/jquery-3.7.1.main.js"></script>
<script src="../../includes/js/jquery.dataTables.main.js"></script>
<script>
$(document).ready(function () {
    // تهيئة DataTable
    var modulesTable = $('#modulesTable').DataTable({
        language: {
            url: "/ems/assets/i18n/datatables/ar.json"
        },
        order: [[1, 'asc']], // الترتيب الافتراضي حسب عمود الترتيب
        columnDefs: [
            { "orderable": false, "targets": [9] } // منع ترتيب عمود الإجراءات (تحرّك بعد إضافة عمودي الوصول السريع والمجموعة)
        ]
    });

    // مجموعات الدور المسؤول وحدها تُعرض: الشاشة مملوكةٌ لدورٍ ومجموعتها لنفس
    // الدور، وإسنادها لمجموعة دورٍ آخر يجعلها غير مرئيةٍ في السايدبار.
    var $groupSelect = $('#group_id');
    var groupOptions = $groupSelect.find('option').clone();

    function syncGroupOptions() {
        var roleId = $('#owner_role_id').val();
        var current = $groupSelect.val();
        $groupSelect.empty();
        groupOptions.each(function () {
            var optRole = $(this).attr('data-role') || '';
            if (optRole === '' || optRole === roleId) {
                $groupSelect.append($(this).clone());
            }
        });
        if ($groupSelect.find('option[value="' + current + '"]').length) {
            $groupSelect.val(current);
        } else {
            $groupSelect.val('');
        }
    }

    $('#owner_role_id').on('change', syncGroupOptions);
    syncGroupOptions();

    // فلترة حسب الدور المسؤول
    $('#roleFilterSelect').on('change', function () {
        var val = $.trim($(this).val());
        modulesTable.column(5).search(val, false, false).draw(); // عمود الدور المسؤول الآن رقم 5
        $('#clearRoleFilter').toggle(val !== '');
    });

    $('#clearRoleFilter').on('click', function () {
        $('#roleFilterSelect').val('');
        modulesTable.column(5).search('', false, false).draw(); // عمود الدور المسؤول الآن رقم 5
        $(this).hide();
    });

    // إظهار/إخفاء النموذج
    $('#toggleForm').on('click', function () {
        $('#moduleForm').slideToggle(300);
        $('html, body').animate({
            scrollTop: $('#moduleForm').offset().top - 100
        }, 500);
    });

    // التبديل بين القائمة المنسدلة والإدخال اليدوي للأيقونة
    $('input[name="icon_type"]').on('change', function () {
        var selectedType = $(this).val();
        if (selectedType === 'list') {
            $('#icon_select').show();
            $('#icon_custom').hide();
            // تحديث القيمة من القائمة
            $('#icon').val($('#icon_select').val());
            updateIconPreview($('#icon_select').val());
        } else {
            $('#icon_select').hide();
            $('#icon_custom').show();
            // تحديث القيمة من الحقل اليدوي
            $('#icon').val($('#icon_custom').val());
            updateIconPreview($('#icon_custom').val());
        }
    });

    // معاينة الأيقونة من القائمة المنسدلة
    $('#icon_select').on('change', function () {
        var iconClass = $(this).val();
        $('#icon').val(iconClass);
        updateIconPreview(iconClass);
    });

    // معاينة الأيقونة من الحقل اليدوي
    $('#icon_custom').on('input', function () {
        var iconClass = $(this).val().trim();
        $('#icon').val(iconClass);
        updateIconPreview(iconClass);
    });

    // دالة لتحديث معاينة الأيقونة
    function updateIconPreview(iconClass) {
        if (!iconClass) {
            iconClass = 'fa fa-link';
        }
        $('#icon_preview i').attr('class', iconClass);
        $('#icon_preview_text').text(iconClass);
    }

    // تحديد النوع المناسب عند التحميل (للتعديل)
    $(document).ready(function() {
        var currentIcon = $('#icon').val();
        if (currentIcon) {
            // التحقق من وجود الأيقونة في القائمة
            var existsInList = $('#icon_select option[value="' + currentIcon + '"]').length > 0;
            if (existsInList) {
                $('#icon_type_list').prop('checked', true);
                $('#icon_select').val(currentIcon).show();
                $('#icon_custom').hide();
            } else {
                $('#icon_type_custom').prop('checked', true);
                $('#icon_select').hide();
                $('#icon_custom').val(currentIcon).show();
            }
            updateIconPreview(currentIcon);
        }
    });

    // إذا كان هناك edit_id في URL، أعرض النموذج
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('edit_id')) {
        $('#moduleForm').addClass('show');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout_foot.php'; ?>


