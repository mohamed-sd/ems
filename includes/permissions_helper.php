<?php
/**
 * مساعد التحقق من الصلاحيات - Permission Check Helper
 * استخدم هذه الدوال في صفحاتك للتحقق من صلاحيات المستخدم
 * 
 * @package EMS
 * @version 1.0
 */

// ════════════════════════════════════════════════════════════════════════════
// 🔒 التحقق من صلاحية محددة
// ════════════════════════════════════════════════════════════════════════════

/**
 * التحقق من وجود صلاحية معينة للمستخدم الحالي
 * 
 * @param mysqli $conn - اتصال قاعدة البيانات
 * @param int $module_id - معرف الشاشة
 * @param string $permission - اسم الصلاحية (view, add, edit, delete)
 * @return bool - صحيح إذا كان للمستخدم الصلاحية
 * 
 * @example
 * // التحقق من صلاحية العرض
 * if (!check_permission($conn, 5, 'view')) {
 *     die("❌ لا توجد صلاحيات للوصول إلى هذه الشاشة");
 * }
 */
function check_permission($conn, $module_id, $permission = 'view') {
    // لا توجد جلسة؟
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        return false;
    }

    $role_id = $_SESSION['user']['role'];
    $permission_field = 'can_' . strtolower($permission);
    $allowed_permissions = ['can_view', 'can_add', 'can_edit', 'can_delete'];

    // تحقق من صحة اسم الصلاحية
    if (!in_array($permission_field, $allowed_permissions)) {
        trigger_error("❌ صلاحية غير معروفة: " . $permission_field, E_USER_WARNING);
        return false;
    }

    // استعلم القاعدة
    $stmt = $conn->prepare(
        "SELECT {$permission_field} FROM role_permissions 
         WHERE role_id = ? AND module_id = ? LIMIT 1"
    );

    if (!$stmt) {
        trigger_error("❌ خطأ في قاعدة البيانات: " . $conn->error, E_USER_WARNING);
        return false;
    }

    $stmt->bind_param("ii", $role_id, $module_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    return $result && (int)$result[$permission_field] === 1;
}

// ════════════════════════════════════════════════════════════════════════════
// 🛡️ التحقق والتوقف الفوري
// ════════════════════════════════════════════════════════════════════════════

/**
 * التحقق من صلاحية العرض - إذا لم تكن موجودة يتم التوقف الفوري
 * 
 * @param mysqli $conn - اتصال قاعدة البيانات
 * @param int $module_id - معرف الشاشة
 * @param string $message - رسالة الخطأ (اختياري)
 * 
 * @example
 * require_once 'permissions_helper.php';
 * require_once 'config.php';
 * 
 * // التحقق من صلاحية العرض أو التوقف الفوري
 * check_view_permission($conn, 5);
 */
function check_view_permission($conn, $module_id, $message = '❌ لا توجد صلاحيات للوصول إلى هذه الشاشة') {
    if (!check_permission($conn, $module_id, 'view')) {
        http_response_code(403);
        die($message);
    }
}

/**
 * التحقق من صلاحية الإضافة - إذا لم تكن موجودة يتم التوقف الفوري
 */
function check_add_permission($conn, $module_id, $message = '❌ لا توجد صلاحيات لإضافة بيانات') {
    if (!check_permission($conn, $module_id, 'add')) {
        http_response_code(403);
        die($message);
    }
}

/**
 * التحقق من صلاحية التعديل - إذا لم تكن موجودة يتم التوقف الفوري
 */
function check_edit_permission($conn, $module_id, $message = '❌ لا توجد صلاحيات لتعديل البيانات') {
    if (!check_permission($conn, $module_id, 'edit')) {
        http_response_code(403);
        die($message);
    }
}

/**
 * التحقق من صلاحية الحذف - إذا لم تكن موجودة يتم التوقف الفوري
 */
function check_delete_permission($conn, $module_id, $message = '❌ لا توجد صلاحيات لحذف البيانات') {
    if (!check_permission($conn, $module_id, 'delete')) {
        http_response_code(403);
        die($message);
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 🎯 الحصول على صلاحيات متعددة
// ════════════════════════════════════════════════════════════════════════════

/**
 * الحصول على جميع الصلاحيات لشاشة معينة
 * 
 * @param mysqli $conn - اتصال قاعدة البيانات
 * @param int $module_id - معرف الشاشة
 * @return array - مصفوفة الصلاحيات
 * 
 * @example
 * $perms = get_module_permissions($conn, 5);
 * echo $perms['can_view'] ? '✅' : '❌';  // عرض
 * echo $perms['can_add'] ? '✅' : '❌';   // إضافة
 * echo $perms['can_edit'] ? '✅' : '❌';  // تعديل
 * echo $perms['can_delete'] ? '✅' : '❌';// حذف
 */
function get_module_permissions($conn, $module_id) {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        return [
            'can_view' => false,
            'can_add' => false,
            'can_edit' => false,
            'can_delete' => false
        ];
    }

    $role_id = $_SESSION['user']['role'];

    $stmt = $conn->prepare(
        "SELECT can_view, can_add, can_edit, can_delete 
         FROM role_permissions 
         WHERE role_id = ? AND module_id = ? LIMIT 1"
    );

    if (!$stmt) {
        return [
            'can_view' => false,
            'can_add' => false,
            'can_edit' => false,
            'can_delete' => false
        ];
    }

    $stmt->bind_param("ii", $role_id, $module_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        return [
            'can_view' => false,
            'can_add' => false,
            'can_edit' => false,
            'can_delete' => false
        ];
    }

    return [
        'can_view' => (bool)$result['can_view'],
        'can_add' => (bool)$result['can_add'],
        'can_edit' => (bool)$result['can_edit'],
        'can_delete' => (bool)$result['can_delete']
    ];
}

/**
 * الحصول على جميع صلاحيات المستخدم الحالي
 * 
 * @param mysqli $conn - اتصال قاعدة البيانات
 * @return array - مصفوفة متداخلة [module_id => [permissions]]
 * 
 * @example
 * $all_perms = get_user_permissions($conn);
 * if ($all_perms[5]['can_view']) {
 *     echo "يمكن عرض الشاشة 5";
 * }
 */
function get_user_permissions($conn) {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        return [];
    }

    $role_id = $_SESSION['user']['role'];

    $stmt = $conn->prepare(
        "SELECT module_id, can_view, can_add, can_edit, can_delete 
         FROM role_permissions 
         WHERE role_id = ?"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[$row['module_id']] = [
            'can_view' => (bool)$row['can_view'],
            'can_add' => (bool)$row['can_add'],
            'can_edit' => (bool)$row['can_edit'],
            'can_delete' => (bool)$row['can_delete']
        ];
    }

    return $permissions;
}

// ════════════════════════════════════════════════════════════════════════════
// 🎨 المساعدات البصرية
// ════════════════════════════════════════════════════════════════════════════

/**
 * إخفاء الزر إذا لم تكن هناك صلاحية
 * 
 * @param mysqli $conn - اتصال قاعدة البيانات
 * @param int $module_id - معرف الشاشة
 * @param string $permission - اسم الصلاحية
 * @return string - فئة CSS ('' إذا كانت الصلاحية موجودة، 'd-none' إذا لم تكن)
 * 
 * @example
 * <button class="btn btn-primary <?php echo can_show_button($conn, 5, 'add') ? '' : 'd-none'; ?>">
 *     إضافة جديد
 * </button>
 */
function can_show_button($conn, $module_id, $permission) {
    return check_permission($conn, $module_id, $permission) ? '' : 'd-none';
}

/**
 * عرض رمز الصلاحية (✅ أو ❌)
 * 
 * @param mysqli $conn - اتصال قاعدة البيانات
 * @param int $module_id - معرف الشاشة
 * @param string $permission - اسم الصلاحية
 * @return string - HTML badge
 * 
 * @example
 * echo permission_badge($conn, 5, 'view');  // ✅ أو ❌
 */
function permission_badge($conn, $module_id, $permission, $tooltip = '') {
    $has_perm = check_permission($conn, $module_id, $permission);
    $icon = $has_perm ? '✅' : '❌';
    $class = $has_perm ? 'badge bg-success' : 'badge bg-danger';
    $title = $tooltip ? "title=\"{$tooltip}\"" : '';
    
    return "<span class=\"{$class}\" {$title}>{$icon}</span>";
}

/**
 * الحصول على نسبة الصلاحيات
 * 
 * @param mysqli $conn - اتصال قاعدة البيانات
 * @param int $module_id - معرف الشاشة
 * @return array - [متاح, من أصل, النسبة]
 * 
 * @example
 * [$available, $total, $percentage] = permission_percentage($conn, 5);
 * echo "$available من $total ($percentage%)";  // 2 من 4 (50%)
 */
function permission_percentage($conn, $module_id) {
    $perms = get_module_permissions($conn, $module_id);
    $total = 4; // 4 صلاحيات (view, add, edit, delete)
    $available = 0;

    if ($perms['can_view']) $available++;
    if ($perms['can_add']) $available++;
    if ($perms['can_edit']) $available++;
    if ($perms['can_delete']) $available++;

    $percentage = round(($available / $total) * 100);

    return [$available, $total, $percentage];
}

// ════════════════════════════════════════════════════════════════════════════
// 📊 التحقق المتقدم
// ════════════════════════════════════════════════════════════════════════════

/**
 * التحقق من وجود أي صلاحية على الشاشة
 * 
 * @param mysqli $conn - اتصال قاعدة البيانات
 * @param int $module_id - معرف الشاشة
 * @return bool - صحيح إذا كان هناك أي صلاحية
 */
function has_any_permission($conn, $module_id) {
    $perms = get_module_permissions($conn, $module_id);
    return $perms['can_view'] || $perms['can_add'] || $perms['can_edit'] || $perms['can_delete'];
}

/**
 * التحقق من وجود جميع الصلاحيات على الشاشة
 * 
 * @param mysqli $conn - اتصال قاعدة البيانات
 * @param int $module_id - معرف الشاشة
 * @return bool - صحيح إذا كان هناك جميع الصلاحيات
 */
function has_all_permissions($conn, $module_id) {
    $perms = get_module_permissions($conn, $module_id);
    return $perms['can_view'] && $perms['can_add'] && $perms['can_edit'] && $perms['can_delete'];
}

// ════════════════════════════════════════════════════════════════════════════
// 🔐 دالة عامة لحماية الصفحات
// ════════════════════════════════════════════════════════════════════════════

/**
 * التحقق من صلاحيات الوصول للصفحة - دالة عامة
 * تستخرج معرف الوحدة بناءً على اسم الملف وتفعل الصلاحيات
 * 
 * @param mysqli $conn - اتصال قاعدة البيانات
 * @param string $module_code - رمز الوحدة (عادة الملف أو الاسم)
 * @param string $permission - الصلاحية المطلوبة (view, add, edit, delete)
 * @return array - معلومات الصلاحيات ['can_view', 'can_add', 'can_edit', 'can_delete']
 * 
 * @example
 * // في بداية أي صفحة
 * $perms = check_page_permissions($conn, 'suppliers');
 * 
 * // التحقق من صلاحية محددة
 * if (!$perms['can_view']) {
 *     die("❌ ليس لديك صلاحية الوصول لهذه الصفحة");
 * }
 * 
 * // استخدام الصلاحيات في الواجهة
 * if ($perms['can_add']) {
 *     // عرض زر الإضافة
 * }
 */
function check_page_permissions($conn, $module_code) {
    // البحث عن الوحدة في جدول modules
    $stmt = $conn->prepare("SELECT id FROM modules WHERE code LIKE ? OR name LIKE ? LIMIT 1");
    $search_pattern1 = '%' . $module_code . '%';
    $search_pattern2 = '%' . $module_code . '%';
    $stmt->bind_param("ss", $search_pattern1, $search_pattern2);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        // إذا لم تجد الوحدة، افترض أن المستخدم لديه كل الصلاحيات (للتوافقية مع الصفحات القديمة)
        return [
            'id' => null,
            'can_view' => true,
            'can_add' => true,
            'can_edit' => true,
            'can_delete' => true
        ];
    }
    
    $module_id = $result['id'];
    $perms = get_module_permissions($conn, $module_id);
    
    return [
        'id' => $module_id,
        'can_view' => $perms['can_view'],
        'can_add' => $perms['can_add'],
        'can_edit' => $perms['can_edit'],
        'can_delete' => $perms['can_delete']
    ];
}

/**
 * جلب معرف الوحدة بناءً على مسار السكربت الحالي
 * 
 * @param mysqli $conn
 * @param string|null $script_path
 * @return int|null
 */
function get_module_id_by_script_path($conn, $script_path = null) {
    $script_name = $script_path;
    if ($script_name === null) {
        $script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    }

    if (empty($script_name)) {
        return null;
    }

    $normalized = str_replace('\\', '/', $script_name);
    $parts = explode('/ems/', $normalized, 2);
    $relative_path = isset($parts[1]) ? $parts[1] : ltrim($normalized, '/');
    $basename = basename($relative_path);

    $stmt = $conn->prepare(
        "SELECT id FROM modules 
         WHERE code = ? 
            OR code = ? 
            OR code LIKE ?
            OR code LIKE ?
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $pattern1 = '%/' . $basename;
    $pattern2 = '%' . $basename . '%';
    $stmt->bind_param("ssss", $relative_path, $basename, $pattern1, $pattern2);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    return $result ? intval($result['id']) : null;
}

/**
 * الحصول على صلاحيات الصفحة الحالية تلقائياً من مسار الملف
 * 
 * @param mysqli $conn
 * @param string|null $script_path
 * @return array
 */
function get_current_page_permissions($conn, $script_path = null) {
    $module_id = get_module_id_by_script_path($conn, $script_path);

    if (!$module_id) {
        return [
            'id' => null,
            'can_view' => true,
            'can_add' => true,
            'can_edit' => true,
            'can_delete' => true
        ];
    }

    $perms = get_module_permissions($conn, $module_id);

    return [
        'id' => $module_id,
        'can_view' => $perms['can_view'],
        'can_add' => $perms['can_add'],
        'can_edit' => $perms['can_edit'],
        'can_delete' => $perms['can_delete']
    ];
}

/**
 * فرض صلاحية العرض للصفحة الحالية (مفيد للتطبيق المركزي)
 * 
 * @param mysqli $conn
 * @param string $redirect_path
 * @return void
 */
function enforce_current_page_view_permission($conn, $redirect_path = '../main/dashboard.php') {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        return;
    }

    $current = get_current_page_permissions($conn);
    if ($current['id'] !== null && !$current['can_view']) {
        header('Location: ' . $redirect_path . '?msg=' . urlencode('لا توجد صلاحية عرض لهذه الصفحة ❌'));
        exit();
    }
}

/**
 * فرض صلاحية JSON على وحدة محددة (لـ AJAX Handlers)
 * 
 * @param mysqli $conn
 * @param string $module_code
 * @param string $permission
 * @param string $message
 * @return void
 */
function enforce_module_permission_json($conn, $module_code, $permission = 'view', $message = 'لا توجد صلاحية') {
    $page_permissions = check_page_permissions($conn, $module_code);
    $field = 'can_' . strtolower($permission);

    if (!isset($page_permissions['id'])) {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['success' => false, 'message' => 'تعذر تحديد الوحدة المطلوبة للصلاحيات']));
    }

    if ($page_permissions['id'] !== null && empty($page_permissions[$field])) {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['success' => false, 'message' => $message]));
    }
}

?>
