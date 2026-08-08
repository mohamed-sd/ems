<?php
/**
 * مساعد التحقق من الصلاحيات - Permission Check Helper
 * استخدم هذه الدوال في صفحاتك للتحقق من صلاحيات المستخدم
 * 
 * @package EMS
 * @version 1.1
 */

// ════════════════════════════════════════════════════════════════════════════
// 🔒 التحقق من صلاحية محددة
// ════════════════════════════════════════════════════════════════════════════

if (!function_exists('ems_flash_to')) {
    /**
     * UI-13: يبني وجهةَ تحويلٍ نظيفةً بعد نزعِ الرسالةِ من الرابط.
     * الوجهةُ الأصليةُ كانت «X?msg=…&a=1» فبقيتْ لواحقُها تبدأ بـ«&» — وهذه
     * تصير أولَ معاملٍ فتُقلَب إلى «?». وإن كانت اللاحقةُ فارغةً رجعتِ الوجهةُ
     * وحدَها. تُستدعى من المُرحِّل الآليِّ ومن الشيفرةِ اليدويةِ سواء.
     *
     * @param string $base  الوجهةُ بلا معاملات (أو بمعاملاتٍ سابقة)
     * @param string $extra اللاحقةُ كما كانت («&a=1» أو «?a=1» أو '')
     * @return string وجهةٌ صالحةٌ بلا رسالة
     */
    function ems_flash_to($base, $extra = '')
    {
        $b = (string) $base;
        $e = (string) $extra;
        if ($e === '') { return $b; }
        $e = ltrim($e, '&?');
        if ($e === '') { return $b; }
        return $b . (strpos($b, '?') === false ? '?' : '&') . $e;
    }
}

if (!function_exists('ems_gov_msg_code')) {
    /**
     * UI-13: رمزُ الرسالةِ يُشتق من نصِّها — واللونُ في الحاملِ يتبع الرمزَ لا
     * النصَّ، فالمستخدمُ يقرأ الحكمَ قبل أن يقرأ الحرف. مصدرٌ واحدٌ للاشتقاق
     * يستعمله الحاملُ والمُرحِّلاتُ وأدواتُ التدقيق.
     */
    function ems_gov_msg_code($m)
    {
        $m = (string) $m;
        if ($m === '') { return 'GOV-INFO-200'; }
        if (mb_strpos($m, '✅') !== false || mb_strpos($m, 'تمّ') !== false
            || mb_strpos($m, 'تم ') !== false || mb_strpos($m, 'حُفظ') !== false) { return 'GOV-OK-200'; }
        if (mb_strpos($m, 'صلاحي') !== false || mb_strpos($m, 'مصرح') !== false
            || mb_strpos($m, 'مصرّح') !== false) { return 'GOV-PERM-403'; }
        if (mb_strpos($m, 'نطاق') !== false || mb_strpos($m, 'بيئة شركة') !== false) { return 'GOV-SCOPE-403'; }
        if (mb_strpos($m, 'غير موجود') !== false || mb_strpos($m, 'غير صحيح') !== false
            || mb_strpos($m, 'لم يُعثر') !== false) { return 'GOV-REF-404'; }
        if (mb_strpos($m, 'تعذّر') !== false || mb_strpos($m, 'تعذر') !== false
            || mb_strpos($m, 'فشل') !== false || mb_strpos($m, 'خطأ') !== false
            || mb_strpos($m, '❌') !== false) { return 'GOV-FAIL-409'; }
        return 'GOV-INFO-200';
    }
}

if (!function_exists('ems_gov_redirect')) {
    /**
     * UI-DEF-06 (المصبُّ الواحد): تحويلٌ ينزع الرسالةَ من الرابطِ وقتَ التنفيذ.
     *
     * لماذا هذا بدلَ جراحةِ التعبير: بقيةُ النداءاتِ الحيةِ تبني الوجهةَ بأشكالٍ
     * لا يحكمها تفكيكٌ آمنٌ — شرطيّاتٌ داخلَ النصِّ ونصوصٌ مُقحَمةٌ ولواحقُ
     * متغيّرة. فبدلَ أن يخمّن مُرحِّلٌ آليٌّ شكلَ التعبير، يمرُّ التعبيرُ كما هو
     * على مصبٍّ واحدٍ يفصل msg عن بقيةِ المعاملات: الرسالةُ تذهب لحاملِ الشاشةِ
     * برمزٍ مشتقٍّ، وبقيةُ المعاملاتِ تبقى في الوجهة. فلا يصل المتصفحَ msg أبدًا.
     *
     * @param string $location الوجهةُ كاملةً (بـ«Location: » أو بدونها)
     */
    function ems_gov_redirect($location)
    {
        $loc = preg_replace('~^\s*Location:\s*~i', '', (string) $location);
        $hash = '';
        if (($h = strpos($loc, '#')) !== false) { $hash = substr($loc, $h); $loc = substr($loc, 0, $h); }
        $cut  = strpos($loc, '?');
        $base = $cut === false ? $loc : substr($loc, 0, $cut);
        $qs   = $cut === false ? '' : substr($loc, $cut + 1);
        $msg  = '';
        if ($qs !== '') {
            $keep = array();
            foreach (explode('&', $qs) as $kv) {
                if ($kv === '') { continue; }
                $p = explode('=', $kv, 2);
                if (urldecode($p[0]) === 'msg') { $msg = isset($p[1]) ? urldecode($p[1]) : ''; continue; }
                $keep[] = $kv;
            }
            $qs = implode('&', $keep);
        }
        $to = $base . ($qs !== '' ? '?' . $qs : '') . $hash;
        $msg = trim($msg);
        if ($msg !== '') { ems_gov_flash_redirect($to, $msg, ems_gov_msg_code($msg), ''); }
        header('Location: ' . $to);
        exit();
    }
}

if (!function_exists('ems_absorb_url_msg')) {
    /**
     * UI-DEF-06 (الشقُّ المُستقبِل): أيُّ رسالةٍ وصلتْ في الرابطِ — من رابطٍ
     * محفوظٍ أو مسارٍ لم يُرحَّل بعد — تُنقَل إلى حاملِ رسائلِ الشاشةِ وتُنزَع
     * من $_GET، فلا تعرضها الكتلُ القديمةُ المتناثرةُ ولا تبقى في شريطِ العنوان.
     * تُستدعى مرةً واحدةً عند تحميلِ هذا الملفِّ (يشمل كلَّ شاشةٍ حيةٍ عبر
     * insidebar.php) — ولا تحذف شيفرةً قائمة، بل تُصيّرها خاملةً بلا ضرر.
     */
    function ems_absorb_url_msg()
    {
        if (PHP_SAPI === 'cli') { return; }
        if (empty($_GET['msg']) || !is_scalar($_GET['msg'])) { return; }
        $txt = trim((string) $_GET['msg']);
        unset($_GET['msg'], $_REQUEST['msg']);
        if ($txt === '' || session_status() !== PHP_SESSION_ACTIVE) { return; }
        $code = 'GOV-INFO-200';
        if (mb_strpos($txt, '✅') !== false) { $code = 'GOV-OK-200'; }
        elseif (mb_strpos($txt, 'صلاحي') !== false) { $code = 'GOV-PERM-403'; }
        elseif (mb_strpos($txt, '❌') !== false) { $code = 'GOV-FAIL-409'; }
        if (!isset($_SESSION['ems_flash_gov']) || !is_array($_SESSION['ems_flash_gov'])) {
            $_SESSION['ems_flash_gov'] = array();
        }
        $_SESSION['ems_flash_gov'][] = array(
            'text' => $txt, 'code' => $code, 'hint' => '', 'at' => time(),
        );
    }
    ems_absorb_url_msg();
}

if (!function_exists('ems_gov_flash_redirect')) {
    /**
     * UI-DEF-06 → UI-13: رسالةُ الحوكمة تُودَع الجلسةَ وتُعرض داخل الشاشة
     * (الحاملُ المركزي في inheader.php) — وصفرُ رسالةٍ في الرابط.
     *
     * @param string $to      وجهةُ التحويل (بلا msg=)
     * @param string $message ما حدث — بلغة المستخدم لا نصًّا تقنيًّا
     * @param string $code    رمزُ الخطأ المعلن (يظهر في الشاشة للدعم)
     * @param string $hint    كيف يُصحَّح
     */
    function ems_gov_flash_redirect($to, $message, $code = 'GOV-403', $hint = '')
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!isset($_SESSION['ems_flash_gov']) || !is_array($_SESSION['ems_flash_gov'])) {
                $_SESSION['ems_flash_gov'] = array();
            }
            $_SESSION['ems_flash_gov'][] = array(
                'text' => (string) $message, 'code' => (string) $code,
                'hint' => (string) $hint, 'at' => time(),
            );
        }
        header('Location: ' . $to);
        exit();
    }
}

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
    // المطابقة الدقيقة أولًا (code = المسار) — فالمطابقة التقريبية قد تحل موديولًا خطأ
    $stmt = $conn->prepare("SELECT id FROM modules WHERE code = ? LIMIT 1");
    $stmt->bind_param("s", $module_code);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        // الاسم القصير القديم يعني شاشتَه لا أول شبيه: الذيلُ الدقيق «/الاسم.php»
        // أولًا (equipments ⇒ Equipments/equipments.php لا equipments_types)
        $stmt = $conn->prepare("SELECT id FROM modules WHERE code LIKE ?
                                 ORDER BY CHAR_LENGTH(code) ASC, id ASC LIMIT 1");
        $tail = '%/' . $module_code . '.php';
        $stmt->bind_param("s", $tail);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
    }

    if (!$result) {
        // توافق أخير مع الأكواد القصيرة القديمة (equipments · suppliers · timesheet …)
        // — بترتيبٍ حتميٍّ لا «أدنى id» صدفةً
        $stmt = $conn->prepare("SELECT id FROM modules WHERE code LIKE ? OR name LIKE ?
                                 ORDER BY CHAR_LENGTH(code) ASC, id ASC LIMIT 1");
        $search_pattern1 = '%' . $module_code . '%';
        $search_pattern2 = '%' . $module_code . '%';
        $stmt->bind_param("ss", $search_pattern1, $search_pattern2);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
    }

    if (!$result) {
        // الشاشة غير المسجَّلة تُرفض — الحارس في الخادم لا في الواجهة (M-14 BR-GOV-01).
        // كان الافتراض القديم فتحَ كل الصلاحيات، وأُغلق بقرار المالك 2026-08-05.
        return [
            'id' => null,
            'can_view' => false,
            'can_add' => false,
            'can_edit' => false,
            'can_delete' => false,
            'unregistered' => true
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
    $relative_path = function_exists('ems_relative_path')
        ? ems_relative_path($normalized)
        : ltrim($normalized, '/');
    $basename = basename($relative_path);

    // المطابقة بحدود المسار حصرًا: مسار نسبي تام، أو اسم ملف تام، أو ذيل
    // مسبوق بـ«/». النمط الفضفاض السابق '%basename%' كان يأسر صفحاتٍ لموديولات
    // مشابهة الاسم (main/dashboard.php أسرها Transport/transfer_dashboard.php
    // فحُجبت اللوحة عن كل الأدوار عدا النقل — حادثة 2026-07-10). صفحة بلا
    // موديول مسجَّل = null = شفافة الصلاحية (السلوك التاريخي المقصود).
    // ⚠️ المسارُ التام يغلب الذيلَ: LIMIT 1 بلا ترتيبٍ كان يُرجع أدنى id فيأسر
    //    ذيلُ «%/my_requests.php» موديولَ FinRequests (#115) قبل المطابقة
    //    الدقيقة لـPortal/my_requests.php (#249) — فتُحجب الشاشةُ الجديدة بصلاحية
    //    شاشةٍ قديمةٍ تشاركها الاسم (گوتشا «أدنى id» الموثقة · وحادثة 2026-07-10).
    // ⚠️ [ح-16] وعند تساوي المطابقة: الشاشةُ المشتركة لها صفٌّ لكلِّ مالك
    //    (main/project_users.php ستةُ صفوف · Reports/reports.php خمسة)، فـ id ASC
    //    وحدَه يختار موديولَ دورٍ آخر ⇒ تُقاس صلاحيةُ الدور على موديولٍ ليس له
    //    فيُحجب عن شاشةٍ في قائمته. الترتيبُ يطابق get_page_permissions أدناه:
    //    المطابقةُ التامة · ثم موديولُ دوري · ثم غيرُ المملوك · ثم الأقدم.
    $current_role_id = isset($_SESSION['user']['role']) ? intval($_SESSION['user']['role']) : 0;
    $stmt = $conn->prepare(
        "SELECT id FROM modules
         WHERE code = ?
            OR code = ?
            OR code LIKE ?
         ORDER BY (code = ?) DESC,
            CASE
                WHEN owner_role_id = ? THEN 0
                WHEN owner_role_id IS NULL OR owner_role_id = 0 THEN 1
                ELSE 2
            END,
            id ASC
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $pattern1 = '%/' . $basename;
    $stmt->bind_param("ssssi", $relative_path, $basename, $pattern1, $relative_path, $current_role_id);
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
/**
 * REV-08 §5 — صمّام Fail-Closed للشاشات غير المسجَّلة (id===null).
 * يُطابق مسار السكربت الكامل ضدّ قائمتَي بادئاتٍ من .env:
 *   EMS_FAILCLOSED_ENFORCE_PREFIXES  → 403 + UNREGISTERED_SCREEN_DENY (حجبٌ صريح)
 *   EMS_FAILCLOSED_MONITOR_PREFIXES  → UNREGISTERED_SCREEN_WOULD_DENY (رصدٌ بلا حجب)
 * المطابقة substring (تحصينٌ ضد بادئة التطبيق الأساس مثل /ems/…). الإنفاذ فارغٌ
 * افتراضًا ⇒ مطابقٌ تمامًا للسلوك السابق (شفافية). الرصد يجرد الشاشات المسجَّلة
 * بكودٍ قصيرٍ لا بالمسار (تُحَلّ عبر check_page_permissions لا عبر المسار) قبل أيّ
 * إنفاذ. النواة القديمة غير المطابِقة تبقى شفافة؛ المهلة تُرفَق في سطر الرصد.
 * الاستدعاء من مُنفِّذ عرض الصفحة وحده (شاشات السايدبار) — النقاط والمساعدون
 * لا يُضمّنون insidebar فلا يبلغون هذا الصمّام أصلًا.
 */
function ems_failclosed_screen_guard($script_name) {
    $script = strtolower(str_replace('\\', '/', (string) $script_name));
    if ($script === '') {
        return;
    }

    $matchPrefix = function ($envKey) use ($script) {
        $raw = function_exists('ems_env') ? (string) ems_env($envKey, '') : '';
        foreach (explode(',', $raw) as $p) {
            $p = strtolower(trim($p));
            if ($p !== '' && strpos($script, $p) !== false) {
                return $p;
            }
        }
        return null;
    };

    // (أ) إنفاذ — شاشةٌ غير مسجَّلةٍ تحت بادئةٍ مُهاجَرة: 403 محجوبٌ مُسجَّل.
    $p = $matchPrefix('EMS_FAILCLOSED_ENFORCE_PREFIXES');
    if ($p !== null) {
        if (function_exists('log_security_event')) {
            log_security_event('UNREGISTERED_SCREEN_DENY',
                'path=' . $script . ' prefix=' . $p
                . ' role=' . (isset($_SESSION['user']['role']) ? intval($_SESSION['user']['role']) : 0));
        }
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode(array('error' => 'unregistered_screen', 'path' => $script), JSON_UNESCAPED_UNICODE));
    }

    // (ب) رصد — تسجيلٌ بلا حجب (جرد ما قبل الإنفاذ للشاشات المسجَّلة بكودٍ قصير).
    $p = $matchPrefix('EMS_FAILCLOSED_MONITOR_PREFIXES');
    if ($p !== null && function_exists('log_security_event')) {
        $deadline = function_exists('ems_env') ? (string) ems_env('EMS_LEGACY_TRANSPARENCY_DEADLINE', '') : '';
        log_security_event('UNREGISTERED_SCREEN_WOULD_DENY',
            'path=' . $script . ' prefix=' . $p . ' (monitor'
            . ($deadline !== '' ? '; deadline=' . $deadline : '') . ')');
    }
}

function enforce_current_page_view_permission($conn, $redirect_path = '../main/dashboard.php') {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        return;
    }

    // صفحات نظام التقارير الجديد تعتمد على جدول report_role_permissions
    // وليس على جدول modules العام.
    $script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $relative_script = function_exists('ems_relative_path')
        ? ems_relative_path($script_name)
        : ltrim(str_replace('\\', '/', $script_name), '/');

    // H-20 · بوابة مشرف المورد (UX-05 §8.1): الجلسةُ المقيَّدةُ بمورد تُحصر
    // في شاشات بوابتها — مسارٌ خارجها 404 مسجَّلةٌ «في الطبقة لا في الشاشات».
    // قبل إعفاءات المراسلات/البلاغات عمدًا: المفتوحُ للجميع مُدرجٌ في القائمة.
    require_once dirname(__DIR__) . '/app/Services/Portal/SupplierPortalGuard.php';
    \App\Services\Portal\SupplierPortalGuard::gateScreen($conn, $_SESSION['user'], $relative_script);

    // لوحة التحكم هي صفحة الهبوط **وهدف التحويل الافتراضي لهذا المُنفِذ نفسه**:
    // حجبها = حلقة تحويل لا نهائية لكل دورٍ محجوب (حادثة 2026-07-10). هدف
    // التحويل لا يجوز أن يحوّل — إعفاء صريح بنمط إعفاءات المراسلات/البلاغات.
    if (strpos($relative_script, 'main/dashboard.php') !== false) {
        return;
    }

    // صفحات المراسلات متاحة لجميع المستخدمين المسجّلين دخولهم
    if (strpos($relative_script, 'chats/') !== false) {
        return;
    }

    // شاشة البلاغات موحّدة لكل الإدارات (نمط المراسلات): يصلها كل مستخدم مسجّل
    // عبر أيقونة التوبار. صلاحياتها لا تُفحص على مستوى الموديول هنا.
    if (strpos($relative_script, 'Maintenance/breakdowns.php') !== false) {
        return;
    }

    // شاشتا البلاغات (القائمة والاستمارة) بنفس نمط المراسلات: أيُّ مستخدمٍ
    // مسجّلٍ يصلهما عبر الشريط العلوي ليُبلّغ ويتابع؛ ونطاقُ الرؤية والأزرار
    // يُفرضان داخل الشاشتين نفسيهما. أمّا شاشات الإعداد فتبقى خلف هذا الحارس
    // لمدير البلاغات حصرًا.
    if (strpos($relative_script, 'Tickets/tickets_list.php') !== false
        || strpos($relative_script, 'Tickets/ticket_form.php') !== false) {
        return;
    }

    if (strpos($relative_script, 'emsreports/') === 0) {
        $role_id = intval($_SESSION['user']['role']);
        $has_reports_permission = false;

        $query = "SELECT id FROM report_role_permissions WHERE role_id = $role_id LIMIT 1";
        $result = @mysqli_query($conn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $has_reports_permission = true;
        }

        if (!$has_reports_permission) {
            ems_gov_flash_redirect($redirect_path, 'لا تملك صلاحية عرض التقارير', 'GOV-PERM-403', 'اطلب منحة تقارير دورك من مدير الصلاحيات');
        }

        return;
    }

    $current = get_current_page_permissions($conn);

    // REV-08 §5 — الشاشة لا تُحَلّ إلى موديول: صمّام Fail-Closed (إنفاذٌ/رصدٌ حسب البادئة).
    if ($current['id'] === null) {
        ems_failclosed_screen_guard($script_name);
    }

    if ($current['id'] !== null && !$current['can_view']) {
        ems_gov_flash_redirect($redirect_path, 'لا تملك صلاحية عرض هذه الصفحة', 'GOV-PERM-403', 'اطلب منحة العرض من مدير الصلاحيات إن كانت ضمن عملك');
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

// ════════════════════════════════════════════════════════════════════════════
// 🌐 الحصول على صلاحيات الصفحة عن طريق رابط URL  ← جديد v1.1
// ════════════════════════════════════════════════════════════════════════════

/**
 * دالة مساعدة داخلية - إرجاع الصلاحيات الكاملة الافتراضية
 * تُستخدم للتوافقية مع الصفحات غير المسجلة في قاعدة البيانات
 * 
 * @internal لا تُستخدم مباشرة من خارج هذا الملف
 * @return array
 */
function _default_full_permissions() {
    return [
        'id'         => null,
        'can_view'   => true,
        'can_add'    => true,
        'can_edit'   => true,
        'can_delete' => true
    ];
}

/**
 * الحصول على صلاحيات صفحة معينة بناءً على رابطها (URL)
 * 
 * ✅ دالة جديدة - لا تؤثر على أي كود قديم
 * ✅ تعيد نفس بنية المصفوفة المستخدمة في باقي الدوال
 * ✅ إذا لم توجد الصفحة في قاعدة البيانات تُرجع كل الصلاحيات للتوافقية
 * 
 * @param mysqli      $conn - اتصال قاعدة البيانات
 * @param string|null $url  - رابط الصفحة كاملاً أو جزء منه
 *                            إذا كان null يستخدم REQUEST_URI الحالي تلقائياً
 * @return array - ['id', 'can_view', 'can_add', 'can_edit', 'can_delete']
 * 
 * @example
 * // ✅ تمرير رابط محدد
 * $perms = get_page_permissions($conn, '/suppliers/index.php');
 *
 * // ✅ رابط مع query string - يتجاهلها تلقائياً
 * $perms = get_page_permissions($conn, '/suppliers/index.php?id=5&action=edit');
 *
 * // ✅ استخدام الرابط الحالي تلقائياً (بدون تمرير أي شيء)
 * $perms = get_page_permissions($conn);
 *
 * // التحقق من الصلاحيات
 * if (!$perms['can_view'])   die("❌ لا توجد صلاحية عرض");
 * if ($perms['can_add'])     { /* عرض زر الإضافة *\/ }
 * if ($perms['can_edit'])    { /* عرض زر التعديل *\/ }
 * if ($perms['can_delete'])  { /* عرض زر الحذف   *\/ }
 */
function get_page_permissions($conn, $url = null ) {
    // إذا لم يُمرَّر رابط، استخدم الرابط الحالي
    if ($url === null) {
        $url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    }

    if (empty($url)) {
        return _default_full_permissions();
    }

    // تنظيف الرابط - إزالة query string و fragment
    $parsed = parse_url($url);
    $path   = isset($parsed['path']) ? $parsed['path'] : $url;

    // تطبيع الفواصل
    $normalized = str_replace('\\', '/', $path);

    // استخراج المسار النسبي داخل المشروع بشكل ديناميكي
    $relative_path = function_exists('ems_relative_path')
        ? ems_relative_path($normalized)
        : ltrim($normalized, '/');

    // اسم الملف فقط بدون المسار
    $basename = basename($relative_path);

    if (empty($basename)) {
        return _default_full_permissions();
    }

    // الدور الحالي للمستخدم (المالك المطلوب مطابقته أولاً)
    $current_role_id = isset($_SESSION['user']['role']) ? intval($_SESSION['user']['role']) : 0;

    // البحث في قاعدة البيانات بأكثر من طريقة، مع تفضيل owner_role_id المطابق للدور الحالي
    $stmt = $conn->prepare(
        "SELECT id, owner_role_id FROM modules 
         WHERE code = ?
            OR code = ?
            OR code LIKE ?
            OR code LIKE ?
         ORDER BY
            CASE
                WHEN owner_role_id = ? THEN 0
                WHEN owner_role_id IS NULL OR owner_role_id = 0 THEN 1
                ELSE 2
            END,
            id ASC
         LIMIT 1"
    );

    if (!$stmt) {
        return _default_full_permissions();
    }

    $pattern_end = '%/' . $basename;      // مطابقة نهاية المسار   مثال: %/index.php
    $pattern_any = '%' . $basename . '%'; // مطابقة جزئية          مثال: %index.php%

    $stmt->bind_param("ssssi", $relative_path, $basename, $pattern_end, $pattern_any, $current_role_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    // إذا لم توجد الوحدة في قاعدة البيانات، إرجاع كل الصلاحيات للتوافقية
    if (!$result) {
        return _default_full_permissions();
    }

    $module_id = intval($result['id']);
    $perms     = get_module_permissions($conn, $module_id);

    return [
        'id'         => $module_id,
        'owner_role_id' => isset($result['owner_role_id']) ? intval($result['owner_role_id']) : null,
        'can_view'   => $perms['can_view'],
        'can_add'    => $perms['can_add'],
        'can_edit'   => $perms['can_edit'],
        'can_delete' => $perms['can_delete']
    ];
}
?>