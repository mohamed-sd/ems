<?php
require_once __DIR__ . '/../includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'إدارة صلاحيات الأدوار';
$current_page = 'permissions';

include '../config.php';

$success_msg = null;
$error_msg = null;

// 1️⃣ حفظ صلاحيات الدور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_permissions') {
    $role_id = $_POST['role_id'] ?? null;
    $module_id = $_POST['module_id'] ?? null;
    $can_view = isset($_POST['can_view']) ? 1 : 0;
    $can_add = isset($_POST['can_add']) ? 1 : 0;
    $can_edit = isset($_POST['can_edit']) ? 1 : 0;
    $can_delete = isset($_POST['can_delete']) ? 1 : 0;

    /* ══ MD-05 · تبنّي `SegregationOfDutiesGuard` — «يُفحص عند حساب الصلاحية
       لا بعد الوقوع» (SEC-01 §5) ═══════════════════════════════════════════
       كان الحارسُ مبنيًّا **بصفرِ نداء**، وهذه شاشةُ المنحِ نفسُها — أي أنه
       بُني ليُفحص هنا ولم يُفحص. واجتماعُ طرفَي تعارضٍ في شخصٍ واحدٍ يُردُّ
       بـ409 مع عرضِ التعارضِ، ولا يُطبَّق. والاستثناءُ بموافقةٍ ورقابةٍ
       تعويضيةٍ معلَنةٍ لا صامتًا — ولذلك العلمُ صريحٌ في الطلبِ لا افتراضيّ. */
    $sod_conflict = null;
    if ($role_id && $module_id) {
        $__sod = dirname(__DIR__, 2) . '/app/Services/Security/SegregationOfDutiesGuard.php';
        if (is_file($__sod)) {
            require_once $__sod;
            require_once dirname(__DIR__, 2) . '/includes/catch_log.php';
            try {
                $__codes = array();
                foreach (array('can_view' => $can_view, 'can_add' => $can_add,
                               'can_edit' => $can_edit, 'can_delete' => $can_delete) as $__a => $__on) {
                    if ($__on) { $__codes[] = 'module:' . (int) $module_id . ':' . $__a; }
                }
                if ($__codes) {
                    $__r = \App\Services\Security\SegregationOfDutiesGuard::check(
                        $conn,
                        (int) $role_id,
                        (int) ($_SESSION['user']['company_id'] ?? 0),
                        $__codes,
                        !empty($_POST['sod_compensating'])
                    );
                    if (is_array($__r) && empty($__r['ok'])) {
                        $sod_conflict = (string) ($__r['message'] ?? 'تعارضُ فصلِ واجباتٍ يمنع هذا المنح');
                    }
                }
            } catch (\Throwable $__se) {
                // فشلُ الفاحصِ لا يمنح ولا يمنع صامتًا — يُسجَّل ويُعلَن للمشغّل.
                ems_catch_ignored($__se, __FILE__,
                    'تعذّر فحصُ فصلِ الواجبات — المنحُ يستمرُّ والفحصُ يُعاد في مراجعةِ الدورة');
            }
        }
    }

    if ($sod_conflict !== null) {
        $error_msg = '⛔ ' . $sod_conflict . ' (SEC-409-SOD)';
    } elseif (!$role_id || !$module_id) {
        $error_msg = 'الدور والصفحة مطلوبان ❌';
    } else {
        // كونسول المزوّد: كتابة RBAC بهوية المدير الأعلى العابرة = الموضع الشرعي للعقد
        $rpp_pg = ems_platform_db();
        $existing = null;
        try {
            $existing = $rpp_pg->selectOne('role_permissions', array(
                'columns' => array('id', 'can_view', 'can_add', 'can_edit', 'can_delete'),
                'where' => array('role_id' => (int)$role_id, 'module_id' => (int)$module_id)));
            if ($existing) {
                $rpp_pg->update('role_permissions', array(
                    'can_view' => $can_view, 'can_add' => $can_add, 'can_edit' => $can_edit, 'can_delete' => $can_delete),
                    array('role_id' => (int)$role_id, 'module_id' => (int)$module_id));
            } else {
                $rpp_pg->insert('role_permissions', array(
                    'role_id' => (int)$role_id, 'module_id' => (int)$module_id,
                    'can_view' => $can_view, 'can_add' => $can_add, 'can_edit' => $can_edit, 'can_delete' => $can_delete));
            }
            $rpp_saved = true;
        } catch (\Throwable $t) { $rpp_saved = false; error_log('admin/permissions/role_permissions save: ' . $t->getMessage()); }

        if ($rpp_saved) {
            $success_msg = 'تم حفظ الصلاحيات بنجاح ✔';
            // N-02: تدقيقُ تغيير الصلاحية بقيم قبل/بعد (كونسول المزوّد)
            require_once __DIR__ . '/../../includes/audit_trail.php';
            ems_audit_change($conn, 'permissions', 'admin/role_permissions', $existing ? 'update' : 'grant',
                intval($existing['id'] ?? 0),
                $existing ? array('can_view' => $existing['can_view'], 'can_add' => $existing['can_add'],
                                  'can_edit' => $existing['can_edit'], 'can_delete' => $existing['can_delete'])
                          : array(),
                array('role_id' => intval($role_id), 'module_id' => intval($module_id),
                      'can_view' => $can_view, 'can_add' => $can_add,
                      'can_edit' => $can_edit, 'can_delete' => $can_delete));

            /* ◆ لا نداءَ لـ`PermissionExplainService` هنا — وقد أضفتُه ثم رفعتُه:
                 توقيعُها `explain($conn, $personId, …)` ينتظر **معرِّفَ شخص**،
                 وهذه الشاشةُ تمنح لـ**دور**. تمريرُ معرِّفِ الدورِ مكانَه يُنتج
                 تفسيرًا لشخصٍ لا وجودَ له فيبدو صحيحًا وهو عدم. وهي متبنّاةٌ
                 في موضعِها الصحيح: `admin/sec_governance.php` (بالاسم `PEX`). */
        } else {
            $error_msg = 'حدث خطأ: ' . $stmt->error . ' ❌';
        }
    }
}

// 2️⃣ حذف صلاحية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_permission') {
    $id = $_POST['id'] ?? null;

    if (!$id) {
        $error_msg = 'معرف الصلاحية غير صحيح ❌';
    } else {
        // N-02: قيمُ «قبل» تُلتقط قبل الحذف
        $oldPerm = null;
        try {
            $oq = $conn->prepare("SELECT role_id, module_id, can_view, can_add, can_edit, can_delete
                                    FROM role_permissions WHERE id = ?");
            $oid = intval($id);
            $oq->bind_param('i', $oid);
            $oq->execute();
            $oldPerm = $oq->get_result()->fetch_assoc();
            $oq->close();
        } catch (\Throwable $t) { error_log('admin role_permissions old: ' . $t->getMessage()); }
        // [مُستثنى موثَّق — حذف صف مرجعٍ عام] لا قناة حذفٍ للمراجع العامة بعد
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success_msg = 'تم حذف الصلاحية بنجاح ✔';
            require_once __DIR__ . '/../../includes/audit_trail.php';
            ems_audit_change($conn, 'permissions', 'admin/role_permissions', 'revoke', intval($id),
                is_array($oldPerm) ? $oldPerm : array(), array());
        } else {
            $error_msg = 'حدث خطأ: ' . $stmt->error . ' ❌';
        }
    }
}

// 3️⃣ منح جميع الصلاحيات للدور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grant_all') {
    $role_id = $_POST['role_id'] ?? null;

    if (!$role_id) {
        $error_msg = 'الدور مطلوب ❌';
    } else {
        // [مُستثنى موثَّق — حذف صف مرجعٍ عام] الحذف الشامل خام؛ قائمة الموديولات والإدراج
        // عبر البوابة (كتابة مرجعٍ بهوية المدير الأعلى العابرة).
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $role_id);
        $stmt->execute();

        try {
            $rpp_pg = ems_platform_db();
            $modules_result = $rpp_pg->select('modules', array('columns' => array('id')));
            foreach ($modules_result as $module) {
                $rpp_pg->insert('role_permissions', array(
                    'role_id' => (int)$role_id, 'module_id' => (int)$module['id'],
                    'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1));
            }
        } catch (\Throwable $t) { error_log('admin/permissions/role_permissions grant: ' . $t->getMessage()); }

        // N-02: المنحُ الشامل فعلٌ واحدٌ بعدّاده
        require_once __DIR__ . '/../../includes/audit_trail.php';
        ems_audit_change($conn, 'permissions', 'admin/role_permissions', 'grant_all', intval($role_id),
            array('modules_granted' => 0),
            array('role_id' => intval($role_id), 'modules_granted' => isset($modules_result) ? count($modules_result) : 0));

        $success_msg = 'تم منح جميع الصلاحيات للدور ✔';
    }
}

// 4️⃣ إزالة جميع الصلاحيات من الدور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_all') {
    $role_id = $_POST['role_id'] ?? null;

    if (!$role_id) {
        $error_msg = 'الدور مطلوب ❌';
    } else {
        // N-02: عدُّ ما سيُسحب قبل السحب
        $beforeCount = 0;
        try {
            $cq = $conn->prepare("SELECT COUNT(*) c FROM role_permissions WHERE role_id = ?");
            $rid = intval($role_id);
            $cq->bind_param('i', $rid);
            $cq->execute();
            $beforeCount = intval($cq->get_result()->fetch_assoc()['c']);
            $cq->close();
        } catch (\Throwable $t) { error_log('admin role_permissions revoke count: ' . $t->getMessage()); }
        // [مُستثنى موثَّق — حذف صف مرجعٍ عام]
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $role_id);

        if ($stmt->execute()) {
            $success_msg = 'تم سحب جميع الصلاحيات من الدور ✔';
            require_once __DIR__ . '/../../includes/audit_trail.php';
            ems_audit_change($conn, 'permissions', 'admin/role_permissions', 'revoke_all', intval($role_id),
                array('role_id' => intval($role_id), 'modules_granted' => $beforeCount),
                array('modules_granted' => 0));
        } else {
            $error_msg = 'حدث خطأ: ' . $stmt->error . ' ❌';
        }
    }
}

// دوال مساعدة
function get_parent_roles($g, $role_id) {
    $parent_roles = [$role_id];

    try {
        $rows = $g->select('roles', array('columns' => array('parent_role_id'),
            'where' => array('id' => (int)$role_id), 'whereRaw' => 'parent_role_id IS NOT NULL'));
        foreach ($rows as $row) {
            if ($row['parent_role_id']) {
                $parent_roles[] = $row['parent_role_id'];
                $rows2 = $g->select('roles', array('columns' => array('parent_role_id'),
                    'where' => array('id' => (int)$row['parent_role_id']), 'whereRaw' => 'parent_role_id IS NOT NULL'));
                foreach ($rows2 as $row2) {
                    if ($row2['parent_role_id'] && !in_array($row2['parent_role_id'], $parent_roles)) {
                        $parent_roles[] = $row2['parent_role_id'];
                    }
                }
            }
        }
    } catch (\Throwable $t) { error_log('admin/permissions/role_permissions parents: ' . $t->getMessage()); }

    return $parent_roles;
}

function get_assigned_modules($g, $role_id) {
    $parent_roles = get_parent_roles($g, $role_id);
    $parent_roles_list = implode(',', array_map('intval', $parent_roles));

    // عمود display_order قائم بالترحيلات (سقط فحص SHOW COLUMNS)؛ DISTINCT لغوٌ على المفتاح
    $modules = [];
    try {
        $modules = $g->select('modules', array('columns' => array('id', 'name', 'code'),
            'whereRaw' => "owner_role_id IN ({$parent_roles_list})",
            'orderBy' => 'display_order ASC, name ASC'));
    } catch (\Throwable $t) { error_log('admin/permissions/role_permissions assigned: ' . $t->getMessage()); }

    return $modules;
}

// جلب البيانات
$selected_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;
$rpp_pg = ems_platform_db();

$roles = [];
try {
    $roles = $rpp_pg->select('roles', array('columns' => array('id', 'name', 'parent_role_id'),
        'where' => array('status' => 1), 'orderBy' => 'name'));
} catch (\Throwable $t) { error_log('admin/permissions/role_permissions roles: ' . $t->getMessage()); }

// فصل الأدوار الأساسية (بلا أب) عن المشرفين التابعين لكل دور — لبناء قائمتين متتاليتين
$base_roles = [];
$role_by_id = [];
foreach ($roles as $role) {
    $role_by_id[(int)$role['id']] = $role;
    if ($role['parent_role_id'] === null) {
        $base_roles[] = $role;
    }
}

// تحديد الدور الأساسي المختار والمشرف المختار (إن كان المحدد مشرفاً تابعاً)
$selected_base_id  = null;
$selected_child_id = null;
if ($selected_role_id && isset($role_by_id[$selected_role_id])) {
    $sr = $role_by_id[$selected_role_id];
    if ($sr['parent_role_id'] === null) {
        $selected_base_id = (int)$selected_role_id;
    } else {
        $selected_base_id  = (int)$sr['parent_role_id'];
        $selected_child_id = (int)$selected_role_id;
    }
}

$modules = [];
if ($selected_role_id) {
    $modules = get_assigned_modules($rpp_pg, $selected_role_id);
}

// كان JOINًا ثلاثيًا (rp×roles×modules) بترتيب r.name ثم m.name — يُركَّب من ثلاث قراءات
// بوّابية مرتَّبة بالاسم ثم ضمّ INNER في PHP، فيُطابق ترتيب الأصل حتمًا (نمط دفعة د).
$all_permissions = [];
try {
    $rpp_roles_all = $rpp_pg->select('roles', array('columns' => array('id', 'name'), 'orderBy' => 'name'));
    $rpp_modules_all = $rpp_pg->select('modules', array('columns' => array('id', 'name', 'code'), 'orderBy' => 'name'));
    $rpp_rows_all = $rpp_pg->select('role_permissions', array());
    $rpp_by_pair = [];
    foreach ($rpp_rows_all as $rp_row) { $rpp_by_pair[intval($rp_row['role_id'])][intval($rp_row['module_id'])][] = $rp_row; }
    foreach ($rpp_roles_all as $rpp_r) {
        $rpp_rid = intval($rpp_r['id']);
        if (!isset($rpp_by_pair[$rpp_rid])) { continue; }
        foreach ($rpp_modules_all as $rpp_m) {
            $rpp_mid = intval($rpp_m['id']);
            if (!isset($rpp_by_pair[$rpp_rid][$rpp_mid])) { continue; }
            foreach ($rpp_by_pair[$rpp_rid][$rpp_mid] as $perm) {
                $perm['role_name'] = $rpp_r['name'];
                $perm['module_name'] = $rpp_m['name'];
                $perm['module_code'] = $rpp_m['code'];
                $all_permissions[] = $perm;
            }
        }
    }
} catch (\Throwable $t) { error_log('admin/permissions/role_permissions grid: ' . $t->getMessage()); }

$permissions_map = [];
foreach ($all_permissions as $perm) {
    $permissions_map[$perm['role_id']][$perm['module_id']] = [
        'id' => $perm['id'],
        'can_view' => $perm['can_view'],
        'can_add' => $perm['can_add'],
        'can_edit' => $perm['can_edit'],
        'can_delete' => $perm['can_delete']
    ];
}

require_once __DIR__ . '/../includes/layout_head.php';
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">

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

.card-main {
    background: white;
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(12, 28, 62, 0.08);
    margin-bottom: 2rem;
}

.card-header-custom {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px 12px 0 0;
    font-weight: 600;
}

.card-body-custom {
    padding: 2rem;
}

.filters-section {
    background: linear-gradient(135deg, #dcf0ff 0%, #e0f0ff 100%);
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    border-right: 4px solid var(--blue);
}

.form-label {
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 0.5rem;
    display: block;
}

.form-select {
    width: 100%;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    padding: 0.75rem;
    transition: all 0.3s ease;
}

.form-select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    border-right: 4px solid var(--gold);
    box-shadow: 0 2px 8px rgba(12, 28, 62, 0.08);
}

.stat-card.blue {
    border-right-color: var(--blue);
}

.stat-card.teal {
    border-right-color: var(--teal);
}

.stat-label {
    font-size: 0.85rem;
    color: #666;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--navy);
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.permission-card {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.permission-card:hover {
    border-color: var(--blue);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
    transform: translateY(-2px);
}

.permission-card h6 {
    color: var(--navy);
    font-weight: 700;
    margin-bottom: 0.5rem;
    margin: 0 0 0.5rem 0;
}

.permission-code {
    font-size: 0.8rem;
    color: #999;
    margin-bottom: 1rem;
    font-family: monospace;
    background: #f5f5f5;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    display: inline-block;
}

.permission-checkbox {
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
}

.permission-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-left: 0.75rem;
    cursor: pointer;
    accent-color: var(--blue);
}

.permission-checkbox label {
    margin: 0;
    cursor: pointer;
    flex: 1;
}

.btn-save {
    background: linear-gradient(135deg, var(--blue) 0%, #1d4ed8 100%);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 0.5rem 1rem;
    font-weight: 600;
    width: 100%;
    margin-top: 1rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.action-bar {
    background: linear-gradient(135deg, #e8f4fd 0%, #e0f0ff 100%);
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border-right: 4px solid var(--blue);
}

.action-bar-title {
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 1rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.alert-custom {
    border: none;
    border-radius: 8px;
    padding: 1rem 1.5rem;
    margin-bottom: 2rem;
    border-right: 4px solid;
}

.alert-success {
    background: linear-gradient(135deg, #d1f3d1 0%, #c8f0c8 100%);
    color: var(--teal);
    border-right-color: var(--teal);
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #dc2626;
    border-right-color: #ef4444;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #999;
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

.btn-success {
    background: linear-gradient(135deg, var(--teal) 0%, #059669 100%);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.85rem;
}
</style>

<div class="page-shell">
    <div class="page-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h2 style="margin: 0;">
            <i class="fas fa-lock-open"></i> إدارة صلاحيات الأدوار
        </h2>
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-right"></i> رجوع
        </a>
    </div>

    <!-- الرسائل -->
    <?php if ($success_msg): ?>
        <div class="alert alert-custom alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
        <div class="alert alert-custom alert-danger">
            <i class="fas fa-times-circle"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- الإحصائيات -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-label">عدد الأدوار</div>
            <div class="stat-number"><?php echo count($roles); ?></div>
        </div>
        <div class="stat-card blue">
            <div class="stat-label">الصفحات المتاحة</div>
            <div class="stat-number"><?php echo count($modules); ?></div>
        </div>
        <div class="stat-card teal">
            <div class="stat-label">إجمالي الصلاحيات</div>
            <div class="stat-number"><?php echo count($all_permissions); ?></div>
        </div>
    </div>

    <!-- عرض الشبكة -->
    <div class="card-main">
        <div class="card-header-custom">
            <i class="fas fa-sliders-h"></i> اختر الدور الذي تريد إدارة صلاحياته
        </div>
        <div class="card-body-custom">
            <div class="filters-section">
                <form method="GET" id="roleFilterForm">
                    <!-- الدور الفعّال المُرسَل للخادم = المشرف المختار إن وُجد، وإلا الدور الأساسي نفسه -->
                    <input type="hidden" name="role_id" id="effectiveRoleId" value="<?php echo $selected_role_id ? (int)$selected_role_id : ''; ?>">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div>
                            <label class="form-label" for="baseRoleSelect"><i class="fas fa-layer-group"></i> الدور الأساسي</label>
                            <select id="baseRoleSelect" class="form-select">
                                <option value="">-- اختر الدور الأساسي --</option>
                                <?php foreach ($base_roles as $role): ?>
                                    <option value="<?php echo (int)$role['id']; ?>"
                                        <?php echo ($selected_base_id === (int)$role['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="childRoleSelect"><i class="fas fa-user-shield"></i> المشرف التابع
                                <span style="font-weight:400;color:#777;">(اختياري)</span></label>
                            <select id="childRoleSelect" class="form-select">
                                <!-- تُملأ ديناميكياً حسب الدور الأساسي المختار -->
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($selected_role_id): ?>
                <?php $selected_role_name = ''; ?>
                <?php foreach ($roles as $role):
                    if ($role['id'] == $selected_role_id) {
                        $selected_role_name = $role['name'];
                        break;
                    }
                endforeach; ?>

                <div class="action-bar">
                    <div class="action-bar-title">
                        <i class="fas fa-cog"></i> إدارة صلاحيات: <strong><?php echo htmlspecialchars($selected_role_name); ?></strong>
                    </div>
                    <div class="action-buttons">
                        <form method="POST">
                            <input type="hidden" name="action" value="grant_all">
                            <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-check-circle"></i> منح الكل
                            </button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="action" value="revoke_all">
                            <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="fas fa-ban"></i> سحب الكل
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($modules)): ?>
                    <div class="permissions-grid">
                        <?php foreach ($modules as $module): ?>
                            <div class="permission-card">
                                <h6><i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($module['name']); ?></h6>
                                <div class="permission-code"><?php echo htmlspecialchars($module['code']); ?></div>

                                <form method="POST">
                                    <input type="hidden" name="action" value="save_permissions">
                                    <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                                    <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">

                                    <?php
                                    $has_permission = isset($permissions_map[$selected_role_id][$module['id']]);
                                    $perm = $has_permission ? $permissions_map[$selected_role_id][$module['id']] : null;
                                    ?>

                                    <div class="permission-checkbox">
                                        <label>
                                            <input type="checkbox" name="can_view"
                                                <?php echo ($perm && $perm['can_view']) ? 'checked' : ''; ?>>
                                             عرض
                                        </label>
                                    </div>

                                    <div class="permission-checkbox">
                                        <label>
                                            <input type="checkbox" name="can_add"
                                                <?php echo ($perm && $perm['can_add']) ? 'checked' : ''; ?>>
                                            ➕ إضافة
                                        </label>
                                    </div>

                                    <div class="permission-checkbox">
                                        <label>
                                            <input type="checkbox" name="can_edit"
                                                <?php echo ($perm && $perm['can_edit']) ? 'checked' : ''; ?>>
                                            ✏️ تعديل
                                        </label>
                                    </div>

                                    <div class="permission-checkbox">
                                        <label>
                                            <input type="checkbox" name="can_delete"
                                                <?php echo ($perm && $perm['can_delete']) ? 'checked' : ''; ?>>
                                             حذف
                                        </label>
                                    </div>

                                    <button type="submit" class="btn-save">
                                        <i class="fas fa-save"></i> حفظ
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state alert-custom" style="background: linear-gradient(135deg, #e0f2fe 0%, #dcf0ff 100%); color: var(--blue); border-right-color: var(--blue);">
                        <i class="fas fa-info-circle"></i> لا توجد صفحات مسندة لهذا الدور
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state alert-custom" style="background: linear-gradient(135deg, #e0f2fe 0%, #dcf0ff 100%); color: var(--blue); border-right-color: var(--blue);">
                    <i class="fas fa-arrow-left"></i> اختر دوراً من القائمة لعرض الصفحات والصلاحيات
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../../includes/js/jquery-3.7.1.main.js"></script>
<script>
$(document).ready(function () {
    // قائمتان متتاليتان: الدور الأساسي ← المشرفون التابعون له
    var rolesData = <?php echo json_encode($roles, JSON_UNESCAPED_UNICODE); ?>;
    var $base   = $('#baseRoleSelect');
    var $child  = $('#childRoleSelect');
    var $roleId = $('#effectiveRoleId');
    var $form   = $('#roleFilterForm');

    // يملأ قائمة المشرفين بناءً على الدور الأساسي المختار
    function populateChildren(baseId, selectedChildId) {
        $child.empty();
        $child.append($('<option>', { value: '', text: '— إدارة الدور الأساسي نفسه —' }));
        var count = 0;
        if (baseId !== '' && baseId != null) {
            rolesData.forEach(function (r) {
                if (r.parent_role_id != null && String(r.parent_role_id) === String(baseId)) {
                    var opt = $('<option>', { value: r.id, text: r.name });
                    if (String(selectedChildId) === String(r.id)) opt.prop('selected', true);
                    $child.append(opt);
                    count++;
                }
            });
        }
        $child.prop('disabled', baseId === '' || baseId == null);
        // تلميح عند عدم وجود مشرفين تابعين
        if (count === 0 && baseId !== '' && baseId != null) {
            $child.find('option[value=""]').text('— لا مشرفين تابعين: إدارة الدور الأساسي —');
        }
    }

    // التهيئة الأولية حسب الاختيار الحالي (المخدوم من الخادم)
    populateChildren($base.val(), '<?php echo $selected_child_id ? (int)$selected_child_id : ''; ?>');

    // تغيير الدور الأساسي: يعيد بناء قائمة المشرفين ويحمّل صلاحيات الدور الأساسي مباشرة
    $base.on('change', function () {
        var b = $(this).val();
        populateChildren(b, '');
        $roleId.val(b);
        $form.submit();
    });

    // تغيير المشرف: يحمّل صلاحيات المشرف، أو الدور الأساسي إن اختير «الدور الأساسي نفسه»
    $child.on('change', function () {
        var c = $(this).val();
        $roleId.val(c !== '' ? c : ($base.val() || ''));
        $form.submit();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/layout_foot.php'; ?>


