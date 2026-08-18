<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';

// ── RF-02 · CS-01 — حارسُ الشاشةِ فوقَ أيِّ معالجٍ يكتب ────────────────────
// كان هذا السطحُ يعتمد على insidebar.php وحدَه في الحجب، وinsidebar يقع
// **بعدَ** معالجِ الكتابة — فيُرحَّل الأثرُ ثم يُعاد التوجيهُ برسالةِ «لا صلاحية».
// الدالةُ نفسُها ولا تغييرَ في مَن يُمنع — التغييرُ في **متى**: قبلَ الكتابة.
if (function_exists('enforce_current_page_view_permission') && isset($conn)) {
    enforce_current_page_view_permission($conn, '../main/dashboard.php');
}
$page_title = "صلاحيات الأدوار";

// ════════════════════════════════════════════════════════════════════════════
// [ح-1] حصر شاشة إدارة الصلاحيات — تصعيد امتياز (ثغرة UAT §15) مؤكد بتنفيذ حقيقي
// ────────────────────────────────────────────────────────────────────────────
// الشاشة الآن مسجَّلة موديولًا (owner 15)؛ الوصول لمن يملك can_view، والكتابة لمن
// يملك can_edit. الفحص هنا صريحٌ لأن معالجات POST أدناه تسبق insidebar (الحارس
// المركزي). المخوَّلون بالمنح: الدور 15 (مدير الصلاحيات) + الدور 1 (التشغيل).
// ════════════════════════════════════════════════════════════════════════════
$rp_perms = get_current_page_permissions($conn);
if ($rp_perms['id'] !== null && !$rp_perms['can_view']) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية لهذه الصفحة ❌', 'GOV-PERM-403', '');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $rp_perms['id'] !== null && !$rp_perms['can_edit']) {
    ems_gov_flash_redirect('role_permissions.php', 'لا توجد صلاحية للتعديل ❌', 'GOV-PERM-403', '');
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// ðŸ“Š معالجة الطلبات الأساسية (CRUD)
// ════════════════════════════════════════════════════════════════════════════

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

    if (!$role_id || !$module_id) {
        $error_msg = 'الدور والصفحة مطلوبان ❌';
    } else {
        // ═══ E-04 RB-04: فصلُ الواجبات بنيويٌّ عند المنح لا مسحًا بعديًّا ═══
        // الزوج الدقيق المكتمل يمنع الحفظَ باسمه وضابطِه؛ والتقريبي يمضي مُبلَّغًا
        // ومسجَّلًا (قرار المالك: «تُبلَّغ ولا تُمنع» · «مسحٌ يكشف ثم منع»).
        require_once __DIR__ . '/../includes/sod_guard.php';
        $sod = ems_sod_check_grant($conn, intval($role_id), intval($module_id), array(
            'can_view' => $can_view, 'can_add' => $can_add,
            'can_edit' => $can_edit, 'can_delete' => $can_delete));
        if ($sod['block'] !== null) {
            $b = $sod['block'];
            if (function_exists('log_security_event')) {
                log_security_event('SOD_GRANT_BLOCKED', 'role=' . intval($role_id)
                    . ' module=' . intval($module_id) . ' pair=' . $b['code']
                    . ' by=' . intval($_SESSION['user']['id'] ?? 0));
            }
            $error_msg = 'فصل الواجبات يمنع هذا المنح: يكتمل به الزوج المحظور «' . $b['name']
                . '» (' . $b['code'] . ' · ' . $b['severity'] . ') — الضابط: '
                . ($b['control'] ?: 'فصل اليدين') . ' ❌';
        }
        if (!isset($error_msg)) {
            foreach ($sod['warnings'] as $w) {
                if (function_exists('log_security_event')) {
                    log_security_event('SOD_GRANT_WARN', 'role=' . intval($role_id)
                        . ' module=' . intval($module_id) . ' pair=' . $w['code'] . ' (approx)');
                }
            }
        }
    }
    if (!isset($error_msg) && $role_id && $module_id) {
        // القراءة عبر البوابة (role_permissions مرجع عام) — الأعمدةُ الأربعة تُقرأ
        // كاملةً لأن سجل التدقيق (N-02) يحتاج قيمَ «قبل» لا الوجودَ فقط.
        $existing = null;
        try {
            $existing = ems_tenant_db()->selectOne('role_permissions', array(
                'columns' => array('id', 'can_view', 'can_add', 'can_edit', 'can_delete'),
                'where' => array('role_id' => intval($role_id), 'module_id' => intval($module_id))));
        } catch (\Throwable $t) { error_log('role_permissions.php existing: ' . $t->getMessage()); }

        // ═══ update0013 · حارسُ سريانِ التكليف (CEO-Y0121) ═══════════════════
        // «◆ ولا يسري تكليفُ شخصٍ على أيِّ مسمًّى قياديٍّ أو رقابيٍّ في المنصةِ
        //   كلِّها قبلَ موافقةِ الرئيسِ الموثَّقة · **ولا يمنح التكليفُ صلاحيةً
        //   واحدةً قبلها**.»
        //
        // فهنا موضعُ الحراسة: منحُ صلاحيةٍ لدورٍ قياديٍّ أو رقابيٍّ **بلا موافقةٍ
        // ساريةٍ لحامله** يُنشئ سلطةً لم يقرَّها الرئيس. والفحصُ على حاملي الدورِ
        // الفعليين — فالمنحةُ للدورِ لا للشخص، وسريانُها يُقاس بحامليه.
        //
        // ◆ `monitor` يُسجِّل و`enforce` يمنع — والانتقالُ قرارُ مالكٍ بعد استكمالِ
        //   موافقاتِ التكليفِ للأدوارِ القائمة.
        $__u13AsgMode = function_exists('ems_env') ? (string) ems_env('EMS_U13_ASSIGNMENT_GATE', 'monitor') : 'monitor';
        if ($__u13AsgMode !== 'off' && intval($can_view) + intval($can_add) + intval($can_edit) + intval($can_delete) > 0) {
            require_once __DIR__ . '/../app/Services/Exec/AssignmentGate.php';
            $__kind = \App\Services\Exec\AssignmentGate::kindOfRole(intval($role_id));
            if (in_array($__kind, \App\Services\Exec\AssignmentGate::NEEDS_APPROVAL, true)) {
                $__co = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
                $__holders = array();
                $__hq = $conn->prepare("SELECT id FROM users
                                         WHERE company_id = ? AND (role_id = ? OR role = ?)
                                           AND COALESCE(is_deleted,0) = 0 AND status = 'active'");
                if ($__hq) {
                    $__rs = (string) $role_id;
                    $__rid = intval($role_id);
                    $__hq->bind_param('iis', $__co, $__rid, $__rs);
                    $__hq->execute();
                    $__r = $__hq->get_result();
                    while ($__x = $__r->fetch_assoc()) { $__holders[] = intval($__x['id']); }
                    $__hq->close();
                }
                $__unapproved = array();
                foreach ($__holders as $__uid) {
                    $__eff = \App\Services\Exec\AssignmentGate::isEffective($conn, $__co, $__uid, $__rid);
                    if (empty($__eff['ok'])) { $__unapproved[] = $__uid; }
                }
                if ($__unapproved) {
                    $__msg = 'الدورُ ' . $__kind . ' وحاملوه بلا موافقةِ تكليفٍ سارية: #'
                           . implode(' · #', array_slice($__unapproved, 0, 5));
                    if (function_exists('log_security_event')) {
                        log_security_event('U13_ASSIGNMENT_NOT_EFFECTIVE',
                            'role=' . $__rid . ' module=' . intval($module_id) . ' — ' . $__msg . ' [' . $__u13AsgMode . ']');
                    }
                    if ($__u13AsgMode === 'enforce') {
                        $error_msg = 'لا تُمنح صلاحية: تكليفٌ بلا موافقةِ الرئيسِ لا يمنح صلاحيةً واحدة (CEO-Y0121) — ' . $__msg;
                    }
                }
            }
        }

        // [مُستثنى موثَّق — كتابة RBAC مجمَّدة] role_permissions مرجعٌ عام جمَّد عقدُ البوابة
        // كتابته على المدير الأعلى حصرًا، والشاشة الحالية تتيحه لمديري الشركات (ثغرة UAT §15
        // المفتوحة). حفاظًا على السلوك حرفيًا تبقى كتابات هذه الشاشة الأربع خامًا بانتظار
        // قرار التحصين الأمني — عندها تُنقَل للبوابة أو تُحصَر بالسوبر.
        if ($existing) {
            $stmt = $conn->prepare(
                "UPDATE role_permissions SET can_view = ?, can_add = ?, can_edit = ?, can_delete = ? 
                 WHERE role_id = ? AND module_id = ?"
            );
            $stmt->bind_param("iiiiii", $can_view, $can_add, $can_edit, $can_delete, $role_id, $module_id);
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iiiiii", $role_id, $module_id, $can_view, $can_add, $can_edit, $can_delete);
        }

        if ($stmt->execute()) {
            $success_msg = 'تم حفظ الصلاحيات بنجاح ✅';
            if (!empty($sod['warnings'])) {
                $wNames = array();
                foreach ($sod['warnings'] as $w) { $wNames[] = $w['name']; }
                $success_msg .= ' — تنبيه فصل واجبات (تقريبي · مسجَّل): ' . implode(' · ', $wNames) . ' ⚠️';
            }
            // N-02: تدقيقُ تغيير الصلاحية بقيم قبل/بعد (من غيّر ماذا ومتى)
            require_once __DIR__ . '/../includes/audit_trail.php';
            ems_audit_change($conn, 'permissions', 'role_permissions', $existing ? 'update' : 'grant',
                intval($existing['id'] ?? 0),
                $existing ? array('can_view' => $existing['can_view'], 'can_add' => $existing['can_add'],
                                  'can_edit' => $existing['can_edit'], 'can_delete' => $existing['can_delete'])
                          : array(),
                array('role_id' => intval($role_id), 'module_id' => intval($module_id),
                      'can_view' => $can_view, 'can_add' => $can_add,
                      'can_edit' => $can_edit, 'can_delete' => $can_delete));
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
        // N-02: قيمُ «قبل» تُلتقط قبل الحذف — وإلا ضاع ما حُذف بلا أثر
        $oldPerm = null;
        try {
            $oldPerm = ems_tenant_db()->selectOne('role_permissions', array(
                'columns' => array('role_id', 'module_id', 'can_view', 'can_add', 'can_edit', 'can_delete'),
                'where' => array('id' => intval($id))));
        } catch (\Throwable $t) { error_log('role_permissions.php old perm: ' . $t->getMessage()); }
        // [مُستثنى موثَّق — كتابة RBAC مجمَّدة] (انظر تعليق الحفظ أعلاه)
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success_msg = 'تم حذف الصلاحية بنجاح ✅';
            require_once __DIR__ . '/../includes/audit_trail.php';
            ems_audit_change($conn, 'permissions', 'role_permissions', 'revoke', intval($id),
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
        // [مُستثنى موثَّق — كتابة RBAC مجمَّدة] (انظر تعليق الحفظ أعلاه) — الحذف والإدراج
        // الشاملان خامان؛ قائمة الموديولات نفسها تُقرأ عبر البوابة.
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $role_id);
        $stmt->execute();

        $modules_result = array();
        try { $modules_result = ems_tenant_db()->select('modules', array('columns' => array('id'))); }
        catch (\Throwable $t) { error_log('role_permissions.php grant modules: ' . $t->getMessage()); }
        $stmt = $conn->prepare(
            "INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
             VALUES (?, ?, 1, 1, 1, 1)"
        );

        foreach ($modules_result as $module) {
            $module_id = $module['id'];
            $stmt->bind_param("ii", $role_id, $module_id);
            $stmt->execute();
        }

        // N-02: منحٌ شامل — يُدوَّن فعلًا واحدًا بعدّاده (لا صفًّا لكل موديول)
        require_once __DIR__ . '/../includes/audit_trail.php';
        ems_audit_change($conn, 'permissions', 'role_permissions', 'grant_all', intval($role_id),
            array('modules_granted' => 0),
            array('role_id' => intval($role_id), 'modules_granted' => count($modules_result)));

        $success_msg = 'تم منح جميع الصلاحيات للدور ✅';
    }
}

// 4️⃣ إزالة جميع الصلاحيات من الدور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_all') {
    $role_id = $_POST['role_id'] ?? null;

    if (!$role_id) {
        $error_msg = 'الدور مطلوب ❌';
    } else {
        // N-02: عدُّ ما سيُسحب قبل السحب — قيمةُ «قبل»
        $beforeCount = 0;
        try {
            $cq = $conn->prepare("SELECT COUNT(*) c FROM role_permissions WHERE role_id = ?");
            $rid = intval($role_id);
            $cq->bind_param('i', $rid);
            $cq->execute();
            $beforeCount = intval($cq->get_result()->fetch_assoc()['c']);
            $cq->close();
        } catch (\Throwable $t) { error_log('role_permissions.php revoke count: ' . $t->getMessage()); }
        // [مُستثنى موثَّق — كتابة RBAC مجمَّدة] (انظر تعليق الحفظ أعلاه)
        $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->bind_param("i", $role_id);

        if ($stmt->execute()) {
            $success_msg = 'تم سحب جميع الصلاحيات من الدور ✅';
            require_once __DIR__ . '/../includes/audit_trail.php';
            ems_audit_change($conn, 'permissions', 'role_permissions', 'revoke_all', intval($role_id),
                array('role_id' => intval($role_id), 'modules_granted' => $beforeCount),
                array('modules_granted' => 0));
        } else {
            $error_msg = 'حدث خطأ: ' . $stmt->error . ' ❌';
        }
    }
}

// ════════════════════════════════════════════════════════════════════════════
// ðŸ› ️ دوال مساعدة
// ════════════════════════════════════════════════════════════════════════════

function get_parent_roles($g, $role_id) {
    $parent_roles = [$role_id];

    try {
        $rows = $g->select('roles', array('columns' => array('parent_role_id'),
            'where' => array('id' => intval($role_id)), 'whereRaw' => 'parent_role_id IS NOT NULL'));
        foreach ($rows as $row) {
            if ($row['parent_role_id']) {
                $parent_roles[] = $row['parent_role_id'];
                $rows2 = $g->select('roles', array('columns' => array('parent_role_id'),
                    'where' => array('id' => intval($row['parent_role_id'])), 'whereRaw' => 'parent_role_id IS NOT NULL'));
                foreach ($rows2 as $row2) {
                    if ($row2['parent_role_id'] && !in_array($row2['parent_role_id'], $parent_roles)) {
                        $parent_roles[] = $row2['parent_role_id'];
                    }
                }
            }
        }
    } catch (\Throwable $t) { error_log('role_permissions.php parents: ' . $t->getMessage()); }

    return $parent_roles;
}

function get_assigned_modules($g, $role_id) {
    $parent_roles = get_parent_roles($g, $role_id);
    $parent_roles_list = implode(',', array_map('intval', $parent_roles));

    // عمود display_order قائم بالترحيلات — سقط فحص SHOW COLUMNS الذاتي.
    // (DISTINCT الأصلية لغوٌ على المفتاح الأساسي id فسقطت مع select البوابة)
    $modules = [];
    try {
        $modules = $g->select('modules', array('columns' => array('id', 'name', 'code'),
            'whereRaw' => "owner_role_id IN ({$parent_roles_list})",
            'orderBy' => 'display_order ASC, name ASC'));
    } catch (\Throwable $t) { error_log('role_permissions.php assigned: ' . $t->getMessage()); }

    return $modules;
}

// ════════════════════════════════════════════════════════════════════════════
// ðŸ“¥ جلب البيانات
// ════════════════════════════════════════════════════════════════════════════

$selected_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

$rp_gate = ems_tenant_db();
$roles = [];
try {
    $roles = $rp_gate->select('roles', array('columns' => array('id', 'name'),
        'where' => array('status' => 1), 'orderBy' => 'name'));
} catch (\Throwable $t) { error_log('role_permissions.php roles: ' . $t->getMessage()); }

$modules = [];
if ($selected_role_id) {
    $modules = get_assigned_modules($rp_gate, $selected_role_id);
}

// كان JOINًا ثلاثيًا بين مراجع عامة (rp×roles×modules) بترتيب r.name ثم m.name —
// يُركَّب من ثلاث قراءات بوابةٍ: أدوارٌ وموديولات مرتَّبةٌ بالاسم (ترتيب MySQL نفسه)
// ثم ضمّ INNER في PHP بالمرور دورًا-فموديولًا — فيُطابق ترتيب الأصل حتمًا.
$all_permissions = [];
try {
    $rp_roles_all = $rp_gate->select('roles', array('columns' => array('id', 'name'), 'orderBy' => 'name'));
    $rp_modules_all = $rp_gate->select('modules', array('columns' => array('id', 'name', 'code'), 'orderBy' => 'name'));
    $rp_rows_all = $rp_gate->select('role_permissions', array());
    $rp_by_pair = [];
    foreach ($rp_rows_all as $rp_row) { $rp_by_pair[intval($rp_row['role_id'])][intval($rp_row['module_id'])][] = $rp_row; }
    foreach ($rp_roles_all as $rp_r) {
        $rp_rid = intval($rp_r['id']);
        if (!isset($rp_by_pair[$rp_rid])) { continue; }
        foreach ($rp_modules_all as $rp_m) {
            $rp_mid = intval($rp_m['id']);
            if (!isset($rp_by_pair[$rp_rid][$rp_mid])) { continue; }
            foreach ($rp_by_pair[$rp_rid][$rp_mid] as $perm) {
                $perm['role_name'] = $rp_r['name'];
                $perm['module_name'] = $rp_m['name'];
                $perm['module_code'] = $rp_m['code'];
                $all_permissions[] = $perm;
            }
        }
    }
} catch (\Throwable $t) { error_log('role_permissions.php grid: ' . $t->getMessage()); }

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

// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(isset($permissions) ? $permissions : null);
include("../inheader.php");
include('../insidebar.php');
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<style>
    :root {
        --navy: var(--c-0c1c3e, #0c1c3e);
        --navy-m: var(--c-132050, #132050);
        --gold: var(--c-e8b800, #e8b800);
        --gold-l: var(--c-ffd740, #ffd740);
        --blue: var(--c-state-info, #2563eb);
        --teal: var(--c-0d9488, #0d9488);
    }

    .main {
        background: linear-gradient(135deg, var(--c-f5f7fa, #f5f7fa) 0%, var(--c-f0f2f5, #f0f2f5) 100%);
        min-height: 100vh;
        padding: 2rem;
    }

    .header-title {
        color: var(--navy);
        font-weight: 700;
        font-size: 1.8rem;
        margin-bottom: 2rem;
    }

    .card-main {
        background: white;
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 8px var(--c-rgba122862008, rgba(12, 28, 62, 0.08));
        margin-bottom: 2rem;
    }

    .card-header-custom {
        background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        font-weight: 600;
        font-size: 1.1rem;
    }

    .card-body-custom {
        padding: 2rem;
    }

    .filters-section {
        background: linear-gradient(135deg, var(--c-dcf0ff, #dcf0ff) 0%, var(--c-e0f0ff, #e0f0ff) 100%);
        padding: 1.5rem;
        border-radius: 8px;
        margin-bottom: 2rem;
        border-right: 4px solid var(--blue);
    }

    .form-label {
        font-weight: 600;
        color: var(--navy);
        margin-bottom: 0.5rem;
    }

    .form-select {
        border: 2px solid var(--c-e0e0e0, #e0e0e0);
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
        box-shadow: 0 2px 8px var(--c-rgba122862008, rgba(12, 28, 62, 0.08));
    }

    .stat-card.blue {
        border-right-color: var(--blue);
    }

    .stat-card.teal {
        border-right-color: var(--teal);
    }

    .stat-label {
        font-size: 0.85rem;
        color: var(--c-s-666, #666);
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
        border: 2px solid var(--c-e0e0e0, #e0e0e0);
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
    }

    .permission-code {
        font-size: 0.8rem;
        color: var(--c-ink-400, #999);
        margin-bottom: 1rem;
        font-family: monospace;
        background: var(--c-f5f5f5, #f5f5f5);
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

    .btn-primary {
        background: linear-gradient(135deg, var(--blue) 0%, var(--c-state-info-deep, #1d4ed8) 100%);
        color: white;
        border: none;
        border-radius: 6px;
        padding: 0.5rem 1rem;
        font-weight: 600;
        width: 100%;
        margin-top: 1rem;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px var(--c-rgba379923503, rgba(37, 99, 235, 0.3));
    }

    .action-bar {
        background: linear-gradient(135deg, var(--c-e8f4fd, #e8f4fd) 0%, var(--c-e0f0ff, #e0f0ff) 100%);
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

    .action-buttons form {
        display: inline;
    }

    .alert-custom {
        border: none;
        border-radius: 8px;
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        border-right: 4px solid;
    }

    .alert-success {
        background: linear-gradient(135deg, var(--c-d1f3d1, #d1f3d1) 0%, var(--c-c8f0c8, #c8f0c8) 100%);
        color: var(--teal);
        border-right-color: var(--teal);
    }

    .alert-danger {
        background: linear-gradient(135deg, var(--c-fee2e2, #fee2e2) 0%, var(--c-fecaca, #fecaca) 100%);
        color: var(--c-badge-danger-a, #dc2626);
        border-right-color: var(--c-ef4444, #ef4444);
    }

    .alert-info {
        background: linear-gradient(135deg, var(--c-e0f2fe, #e0f2fe) 0%, var(--c-dcf0ff, #dcf0ff) 100%);
        color: var(--blue);
        border-right-color: var(--blue);
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--c-ink-400, #999);
    }

    .table-custom thead th {
        background: linear-gradient(135deg, var(--navy) 0%, var(--navy-m) 100%);
        color: white;
        font-weight: 600;
        border: none;
        padding: 1rem;
    }

    .table-custom tbody tr {
        border-bottom: 1px solid var(--c-e0e0e0, #e0e0e0);
    }

    .table-custom tbody tr:hover {
        background-color: var(--c-f8f9fa, #f8f9fa);
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .status-badge.active {
        background-color: var(--c-d1f3d1, #d1f3d1);
        color: var(--teal);
    }

    .status-badge.inactive {
        background-color: var(--c-f0f0f0, #f0f0f0);
        color: var(--c-ink-400, #999);
    }

    .nav-link {
        color: var(--navy);
        border: none;
        border-bottom: 3px solid transparent;
        padding: 1rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .nav-link:hover {
        color: var(--blue);
    }

    .nav-link.active {
        color: var(--blue);
        border-bottom-color: var(--blue);
    }

    /* UXW-01 ②: أنماطٌ ثابتةٌ منقولةٌ من السماتِ الموضعيةِ إلى أصنافٍ بادئتُها rp- */
    .rp-header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .rp-tabs { border-bottom: 2px solid var(--c-e0e0e0, #e0e0e0); }
    .rp-inline-form { margin: 0; }
    .rp-inline-block-form { display: inline; }
    .rp-btn-grant { background: linear-gradient(135deg, var(--teal) 0%, var(--c-badge-success-a, #059669) 100%); color: white; border: none; }
    .rp-btn-revoke { background: linear-gradient(135deg, var(--c-ef4444, #ef4444) 0%, var(--c-badge-danger-a, #dc2626) 100%); color: white; border: none; }
    .rp-btn-del { background: linear-gradient(135deg, var(--c-ef4444, #ef4444) 0%, var(--c-badge-danger-a, #dc2626) 100%); color: white; border: none; padding: 0.3rem 0.8rem; }
    .rp-head-grant { background: linear-gradient(135deg, var(--teal) 0%, var(--c-badge-success-a, #059669) 100%); }
    .rp-head-revoke { background: linear-gradient(135deg, var(--c-ef4444, #ef4444) 0%, var(--c-badge-danger-a, #dc2626) 100%); }
    .rp-btn-grant-lg { background: linear-gradient(135deg, var(--teal) 0%, var(--c-badge-success-a, #059669) 100%); margin-top: 0.5rem; }
    .rp-btn-revoke-lg { background: linear-gradient(135deg, var(--c-ef4444, #ef4444) 0%, var(--c-badge-danger-a, #dc2626) 100%); margin-top: 0.5rem; }

    @media (max-width: 768px) {
        .permissions-grid {
            grid-template-columns: 1fr;
        }

        .main {
            padding: 1rem;
        }

        .card-body-custom {
            padding: 1rem;
        }
    }
</style>

<div class="main">
<?php
/* AS-04/AS-05 (UXR-01): رأسُ الصفحةِ الموحَّدُ — الشاشةُ كانت بلا رأسٍ معلَن. */
$header_icon = 'fas fa-window-maximize';
$header_title_html = htmlspecialchars('صلاحيات الأدوار', ENT_QUOTES, 'UTF-8');
$header_actions = array();
$header_back = false;
include __DIR__ . '/../includes/page_header.php';
// UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
echo ems_states_bundle('لا صلاحياتٍ مسجَّلةً لهذا الدورِ بعدُ', 'اختر الدورَ من القائمةِ ثم امنحْ صلاحياتِ شاشاتِه واحفظْ');
?>

    <!-- الرأس -->
    <div class="header-title">
        <i class="fas fa-lock-open"></i> إدارة صلاحيات الأدوار
     <div class="rp-header-actions">
            <a href="settings.php" class="back-btn">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
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
            <div class="stat-label">الشاشات المتاحة</div>
            <div class="stat-number"><?php echo count($modules); ?></div>
        </div>
        <div class="stat-card teal">
            <div class="stat-label">إجمالي الصلاحيات</div>
            <div class="stat-number"><?php echo count($all_permissions); ?></div>
        </div>
    </div>

    <!-- التبويبات -->
    <ul class="nav nav-tabs rp-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#grid-view">
                <i class="fas fa-th"></i> عرض الشبكة
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#table-view">
                <i class="fas fa-table"></i> عرض الجدول
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#quick-actions">
                <i class="fas fa-bolt"></i> إجراءات سريعة
            </button>
        </li>
    </ul>

    <!-- محتويات التبويبات -->
    <div class="tab-content mt-4">
        <!-- 1️⃣ عرض الشبكة -->
        <div class="tab-pane fade show active" id="grid-view">
            <div class="card-main">
                <div class="card-header-custom">
                    <i class="fas fa-sliders-h"></i> اختر الدور
                </div>
                <div class="card-body-custom">
                    <div class="filters-section">
                        <form method="GET" class="row">
                            <div class="col-md-6">
                                <label class="form-label" for="emsf_469_d5aff">الدور</label>
                                <select name="role_id" class="form-select" onchange="this.form.submit()" id="emsf_469_d5aff">
                                    <option value="">-- اختر الدور --</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>" 
                                            <?php echo ($selected_role_id == $role['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
                                <form method="POST" class="rp-inline-form">
        <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="grant_all">
                                    <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                                    <button type="submit" class="btn btn-sm btn-primary rp-btn-grant">
                                        <i class="fas fa-check-circle"></i> منح الكل
                                    </button>
                                </form>
                                <form method="POST" class="rp-inline-form">
        <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="revoke_all">
                                    <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger rp-btn-revoke">
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
        <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="save_permissions">
                                            <input type="hidden" name="role_id" value="<?php echo $selected_role_id; ?>">
                                            <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">

                                            <?php
                                            $has_permission = isset($permissions_map[$selected_role_id][$module['id']]);
                                            $perm = $has_permission ? $permissions_map[$selected_role_id][$module['id']] : null;
                                            ?>

                                            <div class="permission-checkbox">
                                                <label>
                                                    <input type="checkbox" name="can_view" aria-label="صلاحيةُ عرضِ هذه الشاشة"
                                                        <?php echo ($perm && $perm['can_view']) ? 'checked' : ''; ?>>
                                                    ðŸ‘️ عرض
                                                </label>
                                            </div>

                                            <div class="permission-checkbox">
                                                <label>
                                                    <input type="checkbox" name="can_add" aria-label="صلاحيةُ الإضافةِ في هذه الشاشة"
                                                        <?php echo ($perm && $perm['can_add']) ? 'checked' : ''; ?>>
                                                    ➕ إضافة
                                                </label>
                                            </div>

                                            <div class="permission-checkbox">
                                                <label>
                                                    <input type="checkbox" name="can_edit" aria-label="صلاحيةُ التعديلِ في هذه الشاشة"
                                                        <?php echo ($perm && $perm['can_edit']) ? 'checked' : ''; ?>>
                                                    ✏️ تعديل
                                                </label>
                                            </div>

                                            <div class="permission-checkbox">
                                                <label>
                                                    <input type="checkbox" name="can_delete" aria-label="صلاحيةُ الحذفِ في هذه الشاشة"
                                                        <?php echo ($perm && $perm['can_delete']) ? 'checked' : ''; ?>>
                                                    ðŸ—‘️ حذف
                                                </label>
                                            </div>

                                            <button type="submit" class="btn-primary">
                                                <i class="fas fa-save"></i> حفظ
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state alert-custom alert-info">
                                <i class="fas fa-info-circle"></i> لا توجد شاشات مسندة لهذا الدور
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state alert-custom alert-info">
                            <i class="fas fa-arrow-left"></i> اختر دوراً من القائمة لعرض الشاشات والصلاحيات
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2️⃣ عرض الجدول -->
        <div class="tab-pane fade" id="table-view">
            <div class="card-main">
                <div class="card-header-custom">
                    <i class="fas fa-list"></i> جميع الصلاحيات
                </div>
                <div class="card-body-custom">
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>الدور</th>
                                    <th>الشاشة</th>
                                    <th>ðŸ‘️ عرض</th>
                                    <th>➕ إضافة</th>
                                    <th>✏️ تعديل</th>
                                    <th>ðŸ—‘️ حذف</th>
                                    <th>الإجراء</th>
                                    <!-- E-03 موجة ٤: النواة الحاكمة (gov_columns) — الخلايا يحشوها ui-unification.js -->
                                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ المستند وبأي صفة — لا اسم مجرد">المُنشئ — الاسم والصفة</th>
                                    <th class="ems-gov-th" data-gov="approver" data-slice="1" title="من اعتمده وبأي صفة">المعتمِد — الاسم والصفة</th>
                                    <th class="ems-gov-th" data-gov="authority_ref" data-slice="1" title="سند صلاحية المعتمِد — تفويض أو سلطة أصلية">مرجع التفويض</th>
                                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="المستند الذي تولد عنه — خيط التتبع">المرجع الأب</th>
                                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء بالتاريخ والوقت">تاريخ الإنشاء</th>
                                    <th class="ems-gov-th" data-gov="approved_at" data-slice="1" title="لحظة الاعتماد — وبها يقاس زمن الدورة">تاريخ الاعتماد</th>
                                    <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                                    </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_permissions as $perm): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($perm['role_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($perm['module_name']); ?></td>
                                        <td><span class="status-badge <?php echo $perm['can_view'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $perm['can_view'] ? '✓' : '✗'; ?>
                                        </span></td>
                                        <td><span class="status-badge <?php echo $perm['can_add'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $perm['can_add'] ? '✓' : '✗'; ?>
                                        </span></td>
                                        <td><span class="status-badge <?php echo $perm['can_edit'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $perm['can_edit'] ? '✓' : '✗'; ?>
                                        </span></td>
                                        <td><span class="status-badge <?php echo $perm['can_delete'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $perm['can_delete'] ? '✓' : '✗'; ?>
                                        </span></td>
                                        <td>
                                            <form method="POST" class="rp-inline-block-form">
        <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_permission">
                                                <input type="hidden" name="id" value="<?php echo $perm['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger rp-btn-del">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3️⃣ إجراءات سريعة -->
        <div class="tab-pane fade" id="quick-actions">
            <div class="row">
                <div class="col-md-6">
                    <div class="card-main">
                        <div class="card-header-custom rp-head-grant">
                            <i class="fas fa-check-circle"></i> منح جميع الصلاحيات
                        </div>
                        <div class="card-body-custom">
                            <form method="POST">
        <?= csrf_field() ?>
                                <input type="hidden" name="action" value="grant_all">
                                <div class="mb-3">
                                    <label class="form-label" for="rp_grant_role_id">اختر الدور</label>
                                    <select name="role_id" class="form-select" required id="rp_grant_role_id">
                                        <option value="">-- اختر الدور --</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>">
                                                <?php echo htmlspecialchars($role['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary rp-btn-grant-lg"
                                    onclick="return confirm('سيتم منح جميع الصلاحيات. هل أنت متأكد؟')">
                                    <i class="fas fa-check-circle"></i> منح الكل
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card-main">
                        <div class="card-header-custom rp-head-revoke">
                            <i class="fas fa-ban"></i> سحب جميع الصلاحيات
                        </div>
                        <div class="card-body-custom">
                            <form method="POST">
        <?= csrf_field() ?>
                                <input type="hidden" name="action" value="revoke_all">
                                <div class="mb-3">
                                    <label class="form-label" for="emsf_470_22ae4">اختر الدور</label>
                                    <select name="role_id" class="form-select" required id="emsf_470_22ae4">
                                        <option value="">-- اختر الدور --</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>">
                                                <?php echo htmlspecialchars($role['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary rp-btn-revoke-lg"
                                    onclick="return confirm('سيتم سحب جميع الصلاحيات. هل أنت متأكد؟')">
                                    <i class="fas fa-ban"></i> سحب الكل
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>



