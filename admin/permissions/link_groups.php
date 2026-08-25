<?php
require_once __DIR__ . '/../includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'إدارة مجموعات الروابط';
$current_page = 'permissions';

include '../config.php';

// كونسول المزوّد: link_groups مرجعٌ عام أسوةً بـmodules، وكتابته من هنا (بهوية
// المدير الأعلى العابرة) هي الموضع الشرعي في العقد. القراءة والكتابة عبر
// ems_platform_db().
$mp_pg = ems_platform_db();
$default_group_icon = 'fa fa-folder';

$common_group_icons = array(
    array('class' => 'fa fa-folder', 'label' => 'مجموعة عامة'),
    array('class' => 'fa fa-handshake', 'label' => 'علاقات العملاء'),
    array('class' => 'fa fa-users', 'label' => 'العملاء'),
    array('class' => 'fa fa-folder-open', 'label' => 'المشاريع'),
    array('class' => 'fa fa-file-contract', 'label' => 'العقود'),
    array('class' => 'fa fa-truck-loading', 'label' => 'الموردون'),
    array('class' => 'fa fa-tractor', 'label' => 'المعدات والأسطول'),
    array('class' => 'fa fa-id-card', 'label' => 'الموظفون'),
    array('class' => 'fa fa-business-time', 'label' => 'الدوام والساعات'),
    array('class' => 'fa fa-coins', 'label' => 'المالية'),
    array('class' => 'fa fa-file-invoice-dollar', 'label' => 'الطلبات المالية'),
    array('class' => 'fa fa-cart-shopping', 'label' => 'المشتريات'),
    array('class' => 'fa fa-screwdriver-wrench', 'label' => 'الصيانة'),
    array('class' => 'fa fa-route', 'label' => 'النقل والحركة'),
    array('class' => 'fa fa-headset', 'label' => 'البلاغات'),
    array('class' => 'fa fa-chart-pie', 'label' => 'التقارير'),
    array('class' => 'fa fa-check-double', 'label' => 'الاعتمادات'),
    array('class' => 'fa fa-gear', 'label' => 'الإعدادات'),
    array('class' => 'fa fa-user-shield', 'label' => 'الصلاحيات')
);

$selected_role_id = isset($_GET['role_id']) ? (int) $_GET['role_id'] : null;

/* جلب بيانات التعديل */
$editData = null;
if (isset($_GET['edit_id'])) {
    $id = (int) $_GET['edit_id'];
    try {
        $editData = $mp_pg->selectOne('link_groups', array(
            'columns' => array('id', 'name', 'owner_role_id', 'icon', 'display_order', 'is_active'),
            'where' => array('id' => $id)));
    } catch (\Throwable $t) { $editData = null; error_log('admin/permissions/link_groups edit: ' . $t->getMessage()); }
}

/* إضافة / تعديل */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $owner_role_id = !empty($_POST['owner_role_id']) ? (int) $_POST['owner_role_id'] : null;
    $display_order = isset($_POST['display_order']) ? (int) $_POST['display_order'] : 0;
    $is_active     = (isset($_POST['is_active']) && $_POST['is_active'] == '1') ? 1 : 0;

    // تعقيم الأيقونة بنفس قاعدة شاشة الشاشات حرفيًّا (حروف/أرقام/شرطة/مسافة).
    $icon = trim($_POST['icon'] ?? $default_group_icon);
    $icon = preg_replace('/[^a-zA-Z0-9\-\s]/', '', $icon);
    $icon = trim(preg_replace('/\s+/', ' ', $icon));
    if ($icon === '') {
        $icon = $default_group_icon;
    }

    if ($name === '' || $owner_role_id === null) {
        $error_msg = 'اسم المجموعة والدور المسؤول مطلوبان ❌';
    } else {
        try {
            if (!empty($_POST['edit_id'])) {
                $mp_pg->update('link_groups', array(
                    'name' => $name, 'owner_role_id' => $owner_role_id, 'icon' => $icon,
                    'display_order' => $display_order, 'is_active' => $is_active),
                    array('id' => (int) $_POST['edit_id']));
                ems_flash_set('تم حفظ المجموعة بنجاح ✔');
                header('Location: link_groups.php');
                exit;
            }

            // لا مجموعتان بنفس الاسم لنفس الدور — يمنع الالتباس في السايدبار.
            $dup = $mp_pg->selectOne('link_groups', array('columns' => array('id'),
                'whereRaw' => '`name` = ? AND `owner_role_id` <=> ?', 'params' => array($name, $owner_role_id)));
            if ($dup !== null) {
                $error_msg = 'توجد مجموعة بهذا الاسم لنفس الدور مسبقا ❌';
            } else {
                $mp_pg->insert('link_groups', array(
                    'name' => $name, 'owner_role_id' => $owner_role_id, 'icon' => $icon,
                    'display_order' => $display_order, 'is_active' => $is_active));
                ems_flash_set('تم حفظ المجموعة بنجاح ✔');
                header('Location: link_groups.php');
                exit;
            }
        } catch (\Throwable $t) {
            error_log('admin/permissions/link_groups save: ' . $t->getMessage());
            if (!isset($error_msg)) { $error_msg = 'حدث خطأ في الحفظ ❌'; }
        }
    }
}

/* حذف — قيد modules.group_id هو ON DELETE SET NULL، فالروابط تعود للمستوى
   الأعلى ولا تُفقد شاشة. */
if (isset($_GET['delete_id'])) {
    $id = (int) $_GET['delete_id'];
    // [مُستثنى موثَّق — حذف صف مرجعٍ عام] لا قناة حذفٍ للمراجع العامة بعد
    $stmt = $conn->prepare('DELETE FROM `link_groups` WHERE `id` = ?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        ems_flash_set('تم حذف المجموعة وعادت روابطها للمستوى الأعلى ✔');
        header('Location: link_groups.php');
    } else {
        ems_flash_set('حدث خطأ في الحذف ❌');
        header('Location: link_groups.php');
    }
    exit;
}

// الأدوار الرئيسية (بلا أب) — نفس قائمة شاشة الشاشات كي تتطابق الملكية
$roles = array();
try {
    $roles = $mp_pg->select('roles', array('columns' => array('id', 'name'),
        'where' => array('parent_role_id' => null), 'orderBy' => 'level, name'));
} catch (\Throwable $t) { error_log('admin/permissions/link_groups roles: ' . $t->getMessage()); }

require_once __DIR__ . '/../includes/layout_head.php';
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">

<style>
.page-shell {
    background: linear-gradient(135deg, #f5f7fa 0%, #f0f2f5 100%);
    min-height: calc(100vh - 100px);
    padding: 2rem;
}

.page-header h2 { color: var(--navy); font-weight: 700; margin-bottom: 2rem; }

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

.card-body { padding: 2rem; }

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.form-grid label { font-weight: 600; color: var(--navy); margin-bottom: 0.5rem; display: block; }

.form-grid input,
.form-grid select {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    font-family: 'Cairo', sans-serif;
}

.success-message { border-radius: 8px; padding: 1rem 1.5rem; margin-bottom: 2rem; border-right: 4px solid; }
.success-message.is-success { background: linear-gradient(135deg, #d1f3d1 0%, #c8f0c8 100%); color: #059669; border-right-color: #059669; }
.success-message.is-error { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #dc2626; border-right-color: #ef4444; }

.table-container { overflow-x: auto; }

.display thead th {
    border: none;}

.btn {
    padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.5rem;
    transition: all 0.3s ease; border: none; cursor: pointer;
}

.back-btn { background: #e5e7eb; color: var(--navy); }
.back-btn:hover { background: #d1d5db; }
.add-btn { background: linear-gradient(135deg, var(--blue) 0%, #1d4ed8 100%); color: white; }
.add-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
.btn-primary { background: var(--blue); color: white; }
.btn-danger { background: #ef4444; color: white; }
.btn-sm { padding: 0.4rem 0.8rem; font-size: 0.85rem; }
.text-center { text-align: center; }

#groupForm { display: none; margin-bottom: 2rem; }
#groupForm.show { display: block; }

#icon_preview { transition: all 0.3s ease; }
#icon_preview:hover { border-color: var(--blue); background: #f0f4ff; }
input[type="radio"], input[type="checkbox"] { cursor: pointer; }

.hint-box {
    background: var(--gold-soft, #fff4d6);
    border-right: 4px solid #E0AE2E;
    border-radius: 8px;
    padding: 1rem 1.25rem;
    margin-bottom: 2rem;
    color: #6b5310;
    line-height: 1.9;
}
</style>

<div class="page-shell">
    <div class="page-header" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:2rem; flex-wrap:wrap; gap:1rem;">
        <h2 style="margin:0;">
            <i class="fas fa-folder-tree"></i> إدارة مجموعات الروابط
        </h2>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="index.php" class="back-btn"><i class="fas fa-arrow-right"></i> رجوع</a>
            <a href="modules.php" class="back-btn"><i class="fas fa-layer-group"></i> الصفحات والمديولات</a>
            <a href="javascript:void(0)" id="toggleForm" class="add-btn"><i class="fas fa-plus-circle"></i> إضافة مجموعة</a>
        </div>
    </div>

    <div class="hint-box">
        <strong><i class="fas fa-circle-info"></i> كيف تعمل المجموعات؟</strong><br>
        المجموعة تظهر في الشريط الجانبي كاسم واحد، وروابطها تحته في قائمة قابلة للطي.
        تنشئ المجموعة هنا، ثم تسند إليها الروابط من شاشة <a href="modules.php" style="color:var(--blue); font-weight:700;">الصفحات والمديولات</a> عبر حقل «المجموعة».
        <br>
        لكل دور مجموعاته الخاصة: أنشئ المجموعة باسم الدور المسؤول نفسه الذي تملكه روابطه.
        والرابط بلا مجموعة يظل ظاهرا في المستوى الأعلى كما هو اليوم — فلا شيء ينكسر.
        <br>
        بلاطات «الوصول السريع» في لوحة التحكم تعرض روابط المجموعة المختارة، وتتبدل بنقر رأس أي مجموعة في الشريط الجانبي.
    </div>

    <?php if (!empty($_GET['msg'])):
        $isSuccess = strpos($_GET['msg'], '✔') !== false;
        ?>
        <div class="success-message <?= $isSuccess ? 'is-success' : 'is-error' ?>">
            <i class="fas <?= $isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($_GET['msg']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="success-message is-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <!-- نموذج إضافة / تعديل -->
    <form id="groupForm" action="" method="post" class="ems-form">
        <div class="card">
            <div class="card-header">
                <h5 style="margin:0;">
                    <i class="fas fa-edit"></i>
                    <?= !empty($editData) ? 'تعديل المجموعة' : 'إضافة مجموعة جديدة'; ?>
                </h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="edit_id" id="edit_id" value="<?= htmlspecialchars($editData['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-grid">
                    <div>
                        <label for="name"><i class="fas fa-folder"></i> اسم المجموعة *</label>
                        <input type="text" name="name" id="name" placeholder="مثال: علاقات العملاء"
                               value="<?= htmlspecialchars($editData['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    </div>

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

                    <div>
                        <label for="display_order"><i class="fas fa-sort-numeric-down"></i> الترتيب</label>
                        <input type="number" name="display_order" id="display_order" placeholder="0" min="0" step="1"
                               value="<?= htmlspecialchars($editData['display_order'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>" />
                        <small style="color:#666; display:block; margin-top:5px;">
                            <i class="fas fa-info-circle"></i> الرقم الأصغر يظهر أولا — ويقارن بترتيب الروابط المفردة أيضا
                        </small>
                    </div>

                    <div style="display:flex; align-items:center; padding-top:1.5rem;">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               <?= (empty($editData) || (int) ($editData['is_active'] ?? 1) === 1) ? 'checked' : ''; ?> />
                        <label for="is_active" style="margin:0; margin-right:8px; cursor:pointer;">
                            <i class="fas fa-toggle-on"></i> مفعلة
                        </label>
                    </div>

                    <div>
                        <label><i class="fas fa-icons"></i> الأيقونة</label>
                        <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                            <label style="display:flex; align-items:center; gap:5px; cursor:pointer; margin:0;">
                                <input type="radio" name="icon_type" value="list" id="icon_type_list" checked>
                                <span>من القائمة</span>
                            </label>
                            <label style="display:flex; align-items:center; gap:5px; cursor:pointer; margin:0;">
                                <input type="radio" name="icon_type" value="custom" id="icon_type_custom">
                                <span>إدخال يدوي</span>
                            </label>
                        </div>

                        <select id="icon_select" style="width:100%; padding:0.75rem 1rem; border:1.5px solid var(--border); border-radius:8px; font-family:'Cairo',sans-serif;">
                            <option value="">-- اختر الأيقونة --</option>
                            <?php foreach ($common_group_icons as $iconOption): ?>
                                <option value="<?= htmlspecialchars($iconOption['class'], ENT_QUOTES, 'UTF-8'); ?>"
                                    <?= (!empty($editData) && $editData['icon'] === $iconOption['class']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($iconOption['label'], ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars($iconOption['class'], ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input type="text" id="icon_custom" placeholder="مثال: fas fa-star"
                               style="width:100%; padding:0.75rem 1rem; border:1.5px solid var(--border); border-radius:8px; font-family:'Cairo',sans-serif; display:none;"
                               value="<?= htmlspecialchars($editData['icon'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />

                        <input type="hidden" name="icon" id="icon" value="<?= htmlspecialchars($editData['icon'] ?? $default_group_icon, ENT_QUOTES, 'UTF-8'); ?>" />

                        <div id="icon_preview" style="margin-top:10px; padding:15px; background:#f8f9fa; border-radius:8px; text-align:center; border:2px dashed #dee2e6;">
                            <i class="<?= htmlspecialchars($editData['icon'] ?? $default_group_icon, ENT_QUOTES, 'UTF-8'); ?>" style="font-size:2.5rem; color:var(--navy);"></i>
                            <div style="margin-top:8px; font-size:0.85rem; color:#666;">
                                <code id="icon_preview_text"><?= htmlspecialchars($editData['icon'] ?? $default_group_icon, ENT_QUOTES, 'UTF-8'); ?></code>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="add-btn" style="grid-column:1 / -1; justify-self:center;">
                        <i class="fas fa-save"></i> حفظ المجموعة
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- جدول المجموعات -->
    <div class="card">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
            <h5 style="margin:0;"><i class="fas fa-list"></i> جميع المجموعات</h5>
            <div style="display:flex; align-items:center; gap:10px;">
                <label style="font-weight:700; margin:0;"><i class="fas fa-user-tie"></i> فلترة حسب الدور:</label>
                <select id="roleFilterSelect" style="padding:7px 14px; border:1.5px solid var(--border); border-radius:var(--radius); font-family:'Cairo',sans-serif; font-size:.88rem; min-width:180px;">
                    <option value="">-- جميع الأدوار --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table id="groupsTable" class="display">
                    <thead>
                        <tr>
                            <th width="140"><i class="fas fa-cogs"></i> إجراءات</th>
                            <th width="80"><i class="fas fa-sort-numeric-down"></i> الترتيب</th>
                            <th width="80"><i class="fas fa-icons"></i> الأيقونة</th>
                            <th><i class="fas fa-folder"></i> اسم المجموعة</th>
                            <th><i class="fas fa-user-tie"></i> الدور المسؤول</th>
                            <th width="90"><i class="fas fa-link"></i> عدد الروابط</th>
                            <th width="80"><i class="fas fa-toggle-on"></i> الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $groupRows = array();
                        try {
                            $groupRows = $mp_pg->select('link_groups', array(
                                'columns' => array('id', 'name', 'owner_role_id', 'icon', 'display_order', 'is_active'),
                                'orderBy' => 'display_order ASC, name ASC'));

                            // اسم الدور من خريطة id→name (self-JOIN عالمي لا يمرّ scopedQuery)
                            $roleNames = array();
                            foreach ($mp_pg->select('roles', array('columns' => array('id', 'name'))) as $ra) {
                                $roleNames[(int) $ra['id']] = $ra['name'];
                            }

                            // عدد الروابط المسنَدة لكل مجموعة
                            $linkCounts = array();
                            foreach ($mp_pg->select('modules', array('columns' => array('group_id'))) as $mrow) {
                                $gid = isset($mrow['group_id']) && $mrow['group_id'] !== null ? (int) $mrow['group_id'] : 0;
                                if ($gid > 0) {
                                    $linkCounts[$gid] = ($linkCounts[$gid] ?? 0) + 1;
                                }
                            }
                        } catch (\Throwable $t) {
                            error_log('admin/permissions/link_groups list: ' . $t->getMessage());
                            $roleNames = array(); $linkCounts = array();
                        }

                        foreach ($groupRows as $row):
                            $gid = (int) $row['id'];
                            $roleName = isset($roleNames[(int) $row['owner_role_id']]) ? $roleNames[(int) $row['owner_role_id']] : '-';
                            $cnt = $linkCounts[$gid] ?? 0;
                            ?>
                            <tr>
                                <td class="text-center">
                                    <a href="?edit_id=<?= $gid; ?>&action=edit" class="btn btn-sm btn-primary" title="تعديل"><i class="fas fa-edit"></i></a>
                                    <a href="?delete_id=<?= $gid; ?>"
                                       onclick="return confirm('حذف المجموعة سيعيد روابطها إلى المستوى الأعلى في الشريط الجانبي (لن تحذف أي شاشة). متابعة؟');"
                                       class="btn btn-sm btn-danger" title="حذف"><i class="fas fa-trash"></i></a>
                                </td>
                                <td class="text-center">
                                    <span style="display:inline-block; background:var(--blue-soft); color:var(--blue); padding:4px 10px; border-radius:6px; font-weight:700; font-size:0.9rem;">
                                        <?= (int) $row['display_order']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <i class="<?= htmlspecialchars($row['icon'], ENT_QUOTES, 'UTF-8'); ?>"
                                       style="font-size:1.5rem; color:var(--navy);"
                                       title="<?= htmlspecialchars($row['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                </td>
                                <td><strong><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td data-search="<?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8'); ?>">
                                    <a href="modules.php?role_id=<?= (int) $row['owner_role_id']; ?>"
                                       style="color:var(--blue); text-decoration:none; font-weight:600;"
                                       title="عرض شاشات هذا الدور">
                                        <i class="fas fa-user-shield"></i> <?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <?php if ($cnt > 0): ?>
                                        <span style="display:inline-block; background:var(--teal-soft); color:var(--teal); padding:4px 10px; border-radius:6px; font-weight:700;"><?= $cnt; ?></span>
                                    <?php else: ?>
                                        <span style="display:inline-block; background:#fee2e2; color:#dc2626; padding:4px 10px; border-radius:6px; font-weight:700;" title="مجموعة فارغة لا تظهر في الشريط الجانبي">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int) $row['is_active'] === 1): ?>
                                        <span style="display:inline-block; background:var(--teal-soft); color:var(--teal); padding:4px 8px; border-radius:4px; font-weight:600;">✔ مفعلة</span>
                                    <?php else: ?>
                                        <span style="display:inline-block; background:#f0f0f0; color:#999; padding:4px 8px; border-radius:4px; font-weight:600;">✖ معطلة</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
    var groupsTable = $('#groupsTable').DataTable({
        language: { url: "/ems/assets/i18n/datatables/ar.json" },
        order: [[1, 'asc']],
        columnDefs: [{ "orderable": false, "targets": [0] }]
    });

    $('#roleFilterSelect').on('change', function () {
        groupsTable.column(4).search($.trim($(this).val()), false, false).draw();
    });

    $('#toggleForm').on('click', function () {
        $('#groupForm').slideToggle(300);
        $('html, body').animate({ scrollTop: $('#groupForm').offset().top - 100 }, 500);
    });

    function updateIconPreview(iconClass) {
        if (!iconClass) { iconClass = 'fa fa-folder'; }
        $('#icon_preview i').attr('class', iconClass);
        $('#icon_preview_text').text(iconClass);
    }

    $('input[name="icon_type"]').on('change', function () {
        if ($(this).val() === 'list') {
            $('#icon_select').show();
            $('#icon_custom').hide();
            $('#icon').val($('#icon_select').val());
            updateIconPreview($('#icon_select').val());
        } else {
            $('#icon_select').hide();
            $('#icon_custom').show();
            $('#icon').val($('#icon_custom').val());
            updateIconPreview($('#icon_custom').val());
        }
    });

    $('#icon_select').on('change', function () {
        $('#icon').val($(this).val());
        updateIconPreview($(this).val());
    });

    $('#icon_custom').on('input', function () {
        var v = $(this).val().trim();
        $('#icon').val(v);
        updateIconPreview(v);
    });

    var currentIcon = $('#icon').val();
    if (currentIcon) {
        if ($('#icon_select option[value="' + currentIcon + '"]').length > 0) {
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

    if (new URLSearchParams(window.location.search).has('edit_id')) {
        $('#groupForm').addClass('show');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout_foot.php'; ?>
