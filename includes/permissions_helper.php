<?php
require_once __DIR__ . '/../includes/catch_log.php';
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
    /* ── INJ-0492 · نصُّ الرابطِ **يُطرح ولا يُعرض** ─────────────────────────────
         كان الماصُّ ينقل نصَّ `?msg=` إلى وميضِ الجلسةِ ثم يُعرض — فنقل موضعَ
         العرضِ ولم يُزل الثغرة: أيُّ رابطٍ مُعدَّلٍ بـ`?msg=✅ تم الحفظ بنجاح`
         يُظهر رسالةَ نجاحٍ **لم يقع فعلُها**. والنصُّ مهرَّبٌ فليس المخاطرُ حقنًا
         بل **انتحالَ حكمِ النظام** — وهو أخطرُ على الثقةِ من زخرفةٍ مكسورة.
         فصار النصُّ يُنزع ويُهمَل، والرسائلُ الحقيقيةُ تُودَع الجلسةَ عند مصدرِها
         بـ`ems_flash_set()` قبل التحويل. */
    function ems_absorb_url_msg()
    {
        if (PHP_SAPI === 'cli') { return; }
        if (!isset($_GET['msg'])) { return; }
        unset($_GET['msg'], $_REQUEST['msg']);   /* يُنزع فلا تجده كتلةٌ قديمة */
    }
    ems_absorb_url_msg();
}

if (!function_exists('ems_flash_set')) {
    /**
     * INJ-0492 — إيداعُ رسالةِ النظامِ **الجلسةَ** قبل التحويل، بدلَ حملِها في
     * الرابط. تُقرأ مرةً واحدةً في الحاملِ المركزيِّ ثم تُمحى، فلا تبقى بعد
     * التحديثِ الأول — وهو الشقُّ الثالثُ من نصِّ القبول.
     */
    function ems_flash_set($text, $code = null, $hint = '')
    {
        if (PHP_SAPI === 'cli') { return; }
        $txt = trim((string) $text);
        if ($txt === '') { return; }
        if (session_status() !== PHP_SESSION_ACTIVE) { return; }
        if ($code === null) {
            $code = 'GOV-INFO-200';
            if (mb_strpos($txt, '✅') !== false || mb_strpos($txt, '✔') !== false) { $code = 'GOV-OK-200'; }
            elseif (mb_strpos($txt, 'صلاحي') !== false) { $code = 'GOV-PERM-403'; }
            elseif (mb_strpos($txt, '❌') !== false || mb_strpos($txt, 'خطأ') !== false) { $code = 'GOV-FAIL-409'; }
        }
        if (!isset($_SESSION['ems_flash_gov']) || !is_array($_SESSION['ems_flash_gov'])) {
            $_SESSION['ems_flash_gov'] = array();
        }
        $_SESSION['ems_flash_gov'][] = array(
            'text' => $txt, 'code' => (string) $code, 'hint' => (string) $hint, 'at' => time(),
        );
    }
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
/**
 * حالةُ قالبِ المستخدمِ الحاليِّ (GOV-AUTH-01) — قارئُ طبقةِ المصدرِ للتصيير.
 *
 * تُطابق دلالةَ get_module_permissions حرفًا: التغطيةُ = منحةٌ نافذةٌ واحدةٌ
 * فأكثرُ لقالبٍ نشطٍ؛ والمسموحُ = أكوادُ الشاشاتِ التي MAX(allow)=1 عبر قوالبِه
 * (فمنعُ قالبٍ يغلبه سماحُ آخرَ — عينُ MAX هناك). سلامةُ الفشل: أيُّ خللٍ في
 * القراءةِ ⇒ غيرُ مغطًّى، فيبقى العرضُ على المنحِ القائمِ والحارسُ في الوجهة.
 * والمخبأُ بهويّةِ المستخدمِ لا بالوجودِ المجرَّد — فمجسّاتُ القياسِ تُصيِّر
 * أدوارًا عدّةً في عمليّةٍ واحدةٍ مبدِّلةً الجلسةَ، ومخبأٌ ساكنٌ أعمى يُلبس
 * الجميعَ قالبَ أوّلِهم.
 *
 * @return array{covered:bool, allowed:array<string,1>}
 */
function ems_template_nav_state($conn) {
    static $cache = array();
    $uid = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
    $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
    if (isset($cache[$uid])) { return $cache[$uid]; }
    $st = array('covered' => false, 'allowed' => array());
    if ($uid <= 0 || $role === '-1') { $cache[$uid] = $st; return $st; }
    $q = @mysqli_query($conn,
        "SELECT i.item_ref, MAX(i.allow) mx
           FROM gov_authority_grants g
           JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
           LEFT JOIN gov_profile_items i ON i.profile_id = p.profile_id AND i.item_kind = 'screen'
          WHERE g.user_id = " . $uid . " AND g.revoked_at IS NULL
            AND (g.valid_to IS NULL OR g.valid_to > NOW())
          GROUP BY i.item_ref");
    if (!$q) { $cache[$uid] = $st; return $st; }
    while ($x = mysqli_fetch_assoc($q)) {
        $st['covered'] = true;                       /* صفٌّ واحدٌ = منحةٌ نافذةٌ لقالبٍ نشط */
        if ($x['item_ref'] !== null && (int) $x['mx'] === 1) {
            $st['allowed'][(string) $x['item_ref']] = 1;
        }
    }
    $cache[$uid] = $st;
    return $st;
}

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

    /* ── GOV-AUTH-01 التبديلُ الجزئي (قرارُ المالك 2026-08-17) ─────────────
       المستخدمُ المغطًّى بقالبٍ نافذٍ يُحكَم بقالبِه حصرًا — «لا شاشةَ خارجَ
       القالب». غيرُ المغطَّى (قالبُه مسودةٌ أو بلا قالبٍ) على القائمِ كما هو.
       سلامةُ الفشل: أيُّ خللٍ في القراءةِ ⇒ يسقط للمسارِ القائمِ لا للفتح. */
    $gov_user_id = intval($_SESSION['user']['id'] ?? 0);
    if ($gov_user_id > 0 && strval($role_id) !== '-1') {
        $gst = $conn->prepare(
            "SELECT MAX(CASE WHEN i.item_id IS NULL THEN -1 ELSE i.allow END) t_view,
                    MAX(COALESCE(i.can_add,0)) t_add,
                    MAX(COALESCE(i.can_edit,0)) t_edit,
                    MAX(COALESCE(i.can_delete,0)) t_del
               FROM gov_authority_grants g
               JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
               LEFT JOIN gov_profile_items i ON i.profile_id = p.profile_id
                    AND i.item_kind = 'screen'
                    AND i.item_ref = (SELECT code FROM modules WHERE id = ? LIMIT 1)
              WHERE g.user_id = ? AND g.revoked_at IS NULL
                AND (g.valid_to IS NULL OR g.valid_to > NOW())"
        );
        if ($gst) {
            $gst->bind_param('ii', $module_id, $gov_user_id);
            if ($gst->execute()) {
                $gv = $gst->get_result()->fetch_assoc();
                // t_view: NULL = لا تغطيةَ بقالبٍ نافذٍ ⇒ المسارُ القائم
                //         -1  = مغطًّى والشاشةُ خارجَ قالبِه ⇒ منعٌ بالقالب
                if ($gv !== null && $gv['t_view'] !== null) {
                    $gst->close();
                    $allowed = ((int) $gv['t_view']) === 1;
                    return [
                        'can_view' => $allowed,
                        'can_add' => $allowed && (int) $gv['t_add'] === 1,
                        'can_edit' => $allowed && (int) $gv['t_edit'] === 1,
                        'can_delete' => $allowed && (int) $gv['t_del'] === 1,
                    ];
                }
            }
            $gst->close();
        }
    }

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

    // FIX-01 · RF-01 + CS-02 — الشاشةُ لا تُحَلُّ إلى موديول ⇒ منعٌ كاملٌ مُسجَّل.
    // ◆ كان هذا الفرعُ يرجع كلَّ الصلاحياتِ true فيصير الحارسُ زخرفةً على
    //   أربعينَ سطحًا حيًّا. سُجِّلت الأربعون في modules قبلَ هذا القلبِ
    //   (الترحيلات 2027_01_08..10) — والفاحصُ tools/fix_rf01_surfaces.php
    //   يرسب فوقَ صفر، فلا يعود سطحٌ غيرَ مسجَّلٍ بلا أن يُكشف.
    if (!$module_id) {
        return _deny_all_permissions('unresolved_script_path');
    }

    $perms = get_module_permissions($conn, $module_id);

    return [
        'id' => $module_id,
        'can_view' => $perms['can_view'],
        'can_add' => $perms['can_add'],
        'can_edit' => $perms['can_edit'],
        'can_delete' => $perms['can_delete'],
        'can_export' => $perms['can_view'],
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

        // FIX-01 · RF-01 — وبعدَ الصمّامِ ذي البادئات: المنعُ **مطلقٌ** لا مشروط.
        // ◆ كان الشرطُ ‎id !== null‎ يعني أن الشاشةَ غيرَ المسجَّلةِ تمرُّ بلا فحص
        //   — فالحلُّ الفاشلُ كان يُقرأ سماحًا. الآن يُقرأ منعًا: الشاشةُ التي لا
        //   تُحَلُّ إلى موديولٍ لا تُعرض، ويُسجَّل الرفضُ باسمِها ودورِها ووقتِه.
        //   الأسطحُ الأربعون سُجِّلت قبلَ هذا (2027_01_08..10) وفاحصُ
        //   tools/fix_rf01_surfaces.php يرسب فوقَ صفرٍ فلا تعود.
        ems_gov_flash_redirect(
            $redirect_path,
            'شاشة غير مسجلة في سجل الوحدات — الوصول ممنوع',
            'GOV-PERM-404-MODULE',
            'أبلغ مدير الصلاحيات لتسجيل الشاشة ومنح دورها'
        );
    }

    if (!$current['can_view']) {
        ems_gov_flash_redirect($redirect_path, 'لا تملك صلاحية عرض هذه الصفحة', 'GOV-PERM-403', 'اطلب منحة العرض من مدير الصلاحيات إن كانت ضمن عملك');
    }

    // INJ-0062 — «الوظيفةُ الرقابيةُ مراقَبةٌ أيضًا»: اطّلاعُ المراجعِ يُسجَّل.
    ems_log_auditor_access($conn, $relative_script);

    // P1-A — وبعدَ إذنِ العرض: إذنُ **الكتابة** على الطلبِ الكاتب.
    ems_enforce_write_permission($conn, $current, $redirect_path);
}

/**
 * INJ-0062 · تبنّي `InternalAuditService::logAccess` — كان بصفرِ نداء.
 * ═══════════════════════════════════════════════════════════════════════════
 * IAF-0036: «كلُّ اطّلاعٍ حساسٍ يُسجَّل — فالوظيفةُ الرقابيةُ مراقَبةٌ أيضًا».
 * والمراجعُ الداخليُّ يرى سجلاتِ الإداراتِ كلَّها بحكمِ عمله؛ فبلا سجلِّ اطّلاعٍ
 * لا يُعرف ما اطّلع عليه ولا متى — وهو نفسُه محلُّ مساءلةٍ أمام الجهةِ المشرفة.
 *
 * ◆ عطالةُ اليومِ الواحد: صفٌّ لكلِّ (مراجعٍ × شاشةٍ × يوم) لا لكلِّ تحديثِ صفحة —
 *   وإلا أغرق السجلُّ نفسَه فصار غيرَ مقروء.
 * ◆ ولا يُسجَّل اطّلاعُه على سجلِّه هو (`Audit/`): ذاك عملُه لا اطّلاعٌ عليه.
 * ◆ ولا يبتلع فشلَه صامتًا (CS-12).
 */
function ems_log_auditor_access($conn, $relative_script) {
    if (!isset($_SESSION['user']['role'])) { return; }
    $guardFile = dirname(__DIR__) . '/app/Services/Audit/InternalAuditService.php';
    if (!is_file($guardFile)) { return; }
    require_once $guardFile;
    if (intval($_SESSION['user']['role']) !== \App\Services\Audit\InternalAuditService::ROLE_AUDITOR) { return; }

    $rel = (string) $relative_script;
    if ($rel === '' || strpos($rel, 'Audit/') === 0) { return; }

    $uid = intval($_SESSION['user']['id'] ?? 0);
    $key = 'iaf_acc_' . md5($uid . '|' . $rel . '|' . date('Y-m-d'));
    if (!empty($_SESSION[$key])) { return; }
    $_SESSION[$key] = 1;

    try {
        \App\Services\Audit\InternalAuditService::logAccess($conn, array(
            'company_id' => intval($_SESSION['user']['company_id'] ?? 0),
            'auditor_id' => $uid,
            'scope_kind' => 'screen',
            'scope_ref'  => $rel,
            'purpose'    => 'اطلاع رقابي ضمن مهام المراجعة الداخلية',
        ));
    } catch (\Throwable $e) { ems_catch_ignored($e, __METHOD__, 'ems_log_auditor_access');
        error_log('ems_log_auditor_access: ' . $e->getMessage());
    }
}

/**
 * P1-A · حارسُ صلاحيةِ **الكتابة** المركزي — الجذرُ المشتركُ لعشرِ ملاحظاتٍ P1
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العيبُ المقيس: **سبعون سطحًا يكتب بلا فحصِ ‎can_add/can_edit‎ إطلاقًا**.
 *   و‎enforce_current_page_view_permission‎ يفحص ‎can_view‎ وحدَه — فمن يملك
 *   العرضَ فقط يستطيع أن يُرسل ‎POST‎ فيكتب. أي أن «القراءةَ» كانت تُعطي
 *   الكتابةَ في سبعين شاشة (INJ-0022 · 0023 · 0071 · 0093 · 0098 · 0100 …).
 *
 * ◆ الحكم: طلبٌ يغيّر الحالةَ لا يمرُّ بصلاحيةِ عرضٍ — بل بصلاحيةِ كتابةٍ
 *   واحدةٍ على الأقلّ (‎can_add‎ أو ‎can_edit‎ أو ‎can_delete‎). والفشلُ مغلق.
 *
 * ◆ ولماذا مركزيًّا لا في كلِّ شاشة (CS-05): «فالحكمُ في موضعٍ واحدٍ يُختبر
 *   مرةً واحدة» — وسبعون موضعًا تعني سبعين فرصةً للنسيان، وشاشةٌ جديدةٌ غدًا
 *   تُولد بالعيبِ نفسِه. هنا يرثه كلُّ سطحٍ بلا سطرٍ واحد.
 *
 * ◆ وقيسَ الخطرُ قبلَ التنفيذ: **صفرُ نموذجِ ترشيحٍ بـPOST** في الشجرةِ كلِّها
 *   (المرشِّحاتُ كلُّها ‎GET‎) — فلا يُحجب قارئٌ عن ترشيحِ قائمة.
 *
 * ◆ والإعفاءُ ممكنٌ ومُعلَن: سطحٌ يحتاج ‎POST‎ قرائيًّا يُعلن ذلك صراحةً قبلَ
 *   الحارسِ بـ‎define('EMS_SCREEN_POST_IS_READONLY', true)‎ — إعلانٌ يُقرأ في
 *   المراجعة، لا استثناءٌ صامت.
 */
function ems_enforce_write_permission($conn, array $current, $redirect_path = '../main/dashboard.php') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { return; }
    if (defined('EMS_SCREEN_POST_IS_READONLY') && EMS_SCREEN_POST_IS_READONLY) { return; }
    if (!isset($_SESSION['user']['role'])) { return; }

    // السوبر خارج الترشيح (كما في بقية الحرّاس).
    if ((string) $_SESSION['user']['role'] === '-1') { return; }

    /* ══ INJ-0062 · تبنّي `InternalAuditService::assertReadOnly` ═════════════
       الحارسُ كان **مبنيًّا بصفرِ نداء** — واستقلالُ المراجعةِ يقوم عليه:
       «المراجعُ الداخليُّ يقرأ ولا يكتب خارجَ سجلِّه» (IAF-0043). والدورُ 33
       يملك منحًا واسعةً على شاشاتِ المالية، فبلا هذا الحارسِ يكتب فيها.
       ◆ ويُنادى **الحارسُ المالكُ** لا يُعاد بناءُ حكمِه هنا: الرمزُ الممرَّرُ
         `iaf_screen` لشاشاتِ `Audit/` (فيمرُّ ببادئةِ `iaf_`)، واسمُ مجلَّدِ
         الشاشةِ لغيرِها (فلا يطابق بادئةً مسموحةً ⇒ 403 مغلقٌ صحيحًا). */
    $__auditGuard = dirname(__DIR__) . '/app/Services/Audit/InternalAuditService.php';
    if (is_file($__auditGuard)) {
        require_once $__auditGuard;
        $__rel = function_exists('ems_relative_path')
            ? ems_relative_path((string) ($_SERVER['SCRIPT_NAME'] ?? ''))
            : ltrim(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        $__hint = (strpos($__rel, 'Audit/') === 0)
            ? 'iaf_screen'
            : strtolower(basename(dirname($__rel)));
        $__ro = \App\Services\Audit\InternalAuditService::assertReadOnly(
            intval($_SESSION['user']['role']), $__hint);
        if (empty($__ro['ok'])) {
            if (function_exists('log_security_event')) {
                log_security_event('AUDITOR_WRITE_DENY', 'path=' . $__rel . ' — ' . (string) $__ro['reason']);
            }
            ems_gov_flash_redirect($redirect_path, (string) $__ro['reason'], 'GOV-PERM-403-AUDIT',
                'استقلال المراجعة يمنعها من الكتابة خارج سجلها');
        }
    }

    /* ══ MD-05 · تبنّي `GuardResolver` — «يُستدعى من داخل الحارس نفسه» ═══════
       نصُّ توثيقِه (GOV-01 §10-①) يحدّد موضعَه، وكان **بصفرِ نداء**: صنفُ
       الحمايةِ يُفحص ثم يُبحث عن استثناءٍ نافذٍ يغطي النطاقَ والوقت، و**يُسجَّل
       العبورُ أو المنعُ دائمًا**. فبلا نداءٍ لا استثناءَ نافذًا يُعتدُّ به ولا
       سجلَّ عبورٍ يُراجَع — والاستثناءاتُ المعتمدةُ حبرٌ على ورق.
       ◆ ويُنادى **قبل** فحصِ المنحة: `absolute` يمنع مهما بلغت الصلاحيات،
         و`allow_by_exception` يسمح لمن لا منحةَ له باستثناءٍ نافذ. وترتيبُه
         بعدَ فحصِ المنحةِ يُلغي الحالتين معًا. */
    $__resolver = dirname(__DIR__) . '/app/Services/Governance/GuardResolver.php';
    if (is_file($__resolver)) {
        require_once $__resolver;
        $__relPath = function_exists('ems_relative_path')
            ? ems_relative_path((string) ($_SERVER['SCRIPT_NAME'] ?? ''))
            : ltrim(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        /* ◆ الرمزُ **يُقرأ من `guard_policies` ولا يُخترَع.** أول محاولةٍ مرّرت
             رمزًا مُركَّبًا `screen_write:<path>` لا وجودَ له في الجدول، وسياسةُ
             الخدمةِ أن المجهولَ يُمنع مغلقًا — فصار كلُّ سطحٍ «حمايةً بلا صنف».
             و`GuardResolver` مصمَّمةٌ لعملياتٍ **مصنَّفةٍ** بأسمائها، لا لرمزٍ
             لكلِّ ملفّ. فإن لم يُصنَّف السطحُ حارسًا فلا شأنَ لها به، ويحكم
             فحصُ المنحةِ وحدَه — وهو fail-closed أصلًا. */
        $__gc = null;
        $__gp = $conn->prepare('SELECT guard_code FROM guard_policies WHERE guard_code = ? LIMIT 1');
        if ($__gp) {
            $__try = 'screen_write:' . $__relPath;
            $__gp->bind_param('s', $__try);
            if ($__gp->execute()) {
                $__gr = $__gp->get_result()->fetch_assoc();
                if ($__gr) { $__gc = $__try; }
            }
            $__gp->close();
        }
        try {
            if ($__gc === null) { goto __guardDone; }   // لا حارسَ مصنَّفًا لهذا السطح
            $__verdict = \App\Services\Governance\GuardResolver::resolve(
                $conn,
                intval($_SESSION['user']['company_id'] ?? 0),
                $__gc,
                intval($_SESSION['user']['id'] ?? 0),
                array('path' => $__relPath, 'module_id' => $current['id'] ?? null)
            );
            $__d = is_array($__verdict) ? (string) ($__verdict['decision'] ?? '') : '';
            if ($__d === 'deny') {
                ems_gov_flash_redirect($redirect_path,
                    (string) ($__verdict['reason'] ?? 'حماية مطلقة تمنع الكتابة في هذه الشاشة'),
                    'GOV-GUARD-403', 'صنف الحماية مطلق — ولا مسار استثناء له');
            }
            // استثناءٌ نافذٌ يعبر بمن لا منحةَ له — وقد سُجِّل استعمالُه في الخدمة
            if ($__d === 'allow_by_exception') { return; }
        } catch (\Throwable $__ge) {
            // فشلُ المُحكِّم لا يفتح البابَ: يُسجَّل ويستمرُّ الفحصُ بالمنحةِ وحدَها.
            require_once __DIR__ . '/catch_log.php';
            ems_catch_ignored($__ge, __FUNCTION__,
                'تعذر تحكيم صنف الحماية — يستمر الفحص بالمنحة وحدها ولا يفتح الباب');
        }
        __guardDone:
    }

    $mayWrite = !empty($current['can_add']) || !empty($current['can_edit']) || !empty($current['can_delete']);
    if ($mayWrite) { return; }

    if (function_exists('log_security_event')) {
        log_security_event('WRITE_WITHOUT_PERMISSION_DENY',
            'path=' . ($_SERVER['SCRIPT_NAME'] ?? '') . ' module=' . var_export($current['id'] ?? null, true)
            . ' role=' . intval($_SESSION['user']['role'])
            . ' user=' . intval($_SESSION['user']['id'] ?? 0));
    }
    ems_gov_flash_redirect(
        $redirect_path,
        'لا تملك صلاحية الكتابة في هذه الشاشة — صلاحية العرض لا تكفي لتغيير البيانات',
        'GOV-PERM-403-WRITE',
        'اطلب منحة الإضافة أو التعديل من مدير الصلاحيات إن كانت ضمن عملك'
    );
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
 * FIX-01 · RF-01 + CS-02 — الفشلُ مغلقٌ لا مفتوح.
 * ───────────────────────────────────────────────────────────────────────────
 * كان اسمُها ‎_default_full_permissions‎ وكانت ترجع كلَّ الصلاحياتِ true لأيِّ
 * شاشةٍ لا صفَّ لها في ‎modules‎ — فأربعون سطحًا حيًّا (منها ملفُّ العميلِ وملفُّ
 * الموردِ وملفُّ عمليةِ التمويل) كانت تُقرأ وتُكتب بلا تفويض. سُجِّلت الأربعون
 * في ‎modules‎ بالترحيلاتِ 2027_01_08..10 **قبلَ** هذا القلب (RSK-F1).
 *
 * ◆ الحكم: أيُّ فرعٍ لا يجد سجلَّ صلاحيةٍ يرجع منعًا لا سماحًا · والافتراضُ
 *   الأمنيُّ منعٌ دائمًا · وصفرُ فرعٍ يرجع true عند غيابِ الصف.
 * ◆ ويُسجَّل كلُّ إخفاقٍ بالغيابِ باسمِ الشاشةِ والدورِ والوقت (FIXA-0016).
 *
 * @internal لا تُستخدم مباشرة من خارج هذا الملف
 * @param string $reason سببُ المنعِ — يدخل سجلَّ الرفض
 * @return array
 */
function _deny_all_permissions($reason = 'unresolved_module') {
    ems_log_permission_denial($reason);
    return [
        'id'         => null,
        'can_view'   => false,
        'can_add'    => false,
        'can_edit'   => false,
        'can_delete' => false,
        'can_export' => false,
    ];
}

/**
 * FIX-01 · FIXA-0016 — سجلُّ الرفضِ عند إخفاقِ الحلِّ إلى موديول.
 * يكتب اسمَ الشاشةِ والدورَ والوقتَ في القناةِ الأمنيةِ الموحّدة، ثم في ملفٍّ
 * احتياطيٍّ إن غابت. ◆ ولا يبتلع فشلَه صامتًا (CS-12): إخفاقُ الكتابةِ يُسجَّل
 * في ‎error_log‎ ولا يُوقف المنع — فالمنعُ أهمُّ من سجلِّه.
 */
function ems_log_permission_denial($reason = 'unresolved_module') {
    static $seen = [];
    $script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '(cli)';
    $role   = isset($_SESSION['user']['role']) ? intval($_SESSION['user']['role']) : 0;
    $user   = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
    $key    = $script . '|' . $role . '|' . $reason;
    if (isset($seen[$key])) { return; }   // صفٌّ واحدٌ لكلِّ طلبٍ لا صفٌّ لكلِّ نداء
    $seen[$key] = true;

    $line = 'PERM_DENY_UNREGISTERED reason=' . $reason
          . ' path=' . $script . ' role=' . $role . ' user=' . $user
          . ' at=' . date('Y-m-d H:i:s');
    try {
        if (function_exists('log_security_event')) {
            log_security_event('PERM_DENY_UNREGISTERED', $line);
            return;
        }
        $dir = defined('EMS_LOGS_DIR') ? EMS_LOGS_DIR : dirname(__DIR__) . '/storage/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
        if (@file_put_contents($dir . '/permission_denials.log', '[' . date('c') . "] {$line}\n", FILE_APPEND | LOCK_EX) === false) {
            error_log('EMS permission denial log write failed: ' . $line);
        }
    } catch (\Throwable $e) { ems_catch_ignored($e, __METHOD__, 'EMS permission denial log error');
        error_log('EMS permission denial log error: ' . $e->getMessage() . ' | ' . $line);
    }
}

/**
 * توافقيةٌ خلفيةٌ للاسمِ القديم — يرجع المنعَ الكاملَ لا السماح.
 * ◆ يبقى الاسمُ ليُكسر البناءُ لا الاستدعاء: أيُّ نداءٍ قديمٍ يرث الفشلَ المغلق.
 * @deprecated استعملْ _deny_all_permissions
 */
function _default_full_permissions() {
    return _deny_all_permissions('legacy_default_call');
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
        return _deny_all_permissions('empty_url');
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
        return _deny_all_permissions('empty_basename');
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
        return _deny_all_permissions('prepare_failed');
    }

    $pattern_end = '%/' . $basename;      // مطابقة نهاية المسار   مثال: %/index.php
    $pattern_any = '%' . $basename . '%'; // مطابقة جزئية          مثال: %index.php%

    $stmt->bind_param("ssssi", $relative_path, $basename, $pattern_end, $pattern_any, $current_role_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    // FIX-01 · RF-01: الوحدةُ غيرُ موجودةٍ ⇒ منعٌ كاملٌ مُسجَّلٌ لا سماحٌ صامت.
    if (!$result) {
        return _deny_all_permissions('module_not_found');
    }

    $module_id = intval($result['id']);
    $perms     = get_module_permissions($conn, $module_id);

    return [
        'id'         => $module_id,
        'owner_role_id' => isset($result['owner_role_id']) ? intval($result['owner_role_id']) : null,
        'can_view'   => $perms['can_view'],
        'can_add'    => $perms['can_add'],
        'can_edit'   => $perms['can_edit'],
        'can_delete' => $perms['can_delete'],
        // CS-10: التصديرُ صلاحيةٌ مستقلةٌ منطقيًّا — لا عمودَ لها في الجدولِ بعد،
        // فتُشتقّ من القراءةِ **تضييقًا لا توسيعًا** حتى يُضاف العمود.
        'can_export' => $perms['can_view'],
    ];
}

/**
 * ems_require_action — حارسُ الكتابةِ في صفحةِ سطحٍ (AC-F2 · فِعليٌّ لا وسم)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ العلّةُ التي يسدُّها: ستةُ أسطحٍ تكتب في القاعدةِ وكلٌّ منها يدحرج حارسَه
 *   بيدِه (فحصُ can_edit ثم CSRF ثم رسالة) — ستُّ نسخٍ يسهل أن تنسى إحداها
 *   شرطًا. وهذا حارسٌ واحدٌ **يُنادى قبلَ أولِ كتابةٍ** فيجمع الشروطَ الأربعة:
 *     ① جلسةٌ قائمة  ② رمزُ الحمايةِ CSRF  ③ صلاحيةُ الفعلِ على الشاشة
 *     ④ تسجيلُ المنعِ في سجلِّ الحارس.
 * ◆ fail-closed بالتصميم: أيُّ شرطٍ لم يتحقق ⇒ 403 وتوقُّفٌ فورًا — فلا يبلغ
 *   التنفيذُ سطرَ الكتابةِ أصلًا. والفعلُ غيرُ المعروفِ لا يُشتقُّ منه سماح.
 * ◆ لا يُغني عن حارسِ العرضِ (check_page_permissions) بل يليه: ذاك يمنع
 *   الدخولَ وهذا يمنع **الكتابة**، والفصلُ مقصود.
 *
 * @param mysqli $conn
 * @param string $screen كودُ الشاشةِ كما في modules.code (مثال: Governance/auth_grants.php)
 * @param string $verb   edit|delete|approve|export — الفعلُ المطلوب
 * @param array  $opts   ['csrf'=>bool (افتراضيًّا true) · 'deny_msg'=>string]
 * @return array{allowed:bool, perms:array} ولا يعود إلا مسموحًا (وإلا خرج بـ403)
 */
if (!function_exists('ems_require_action')) {
    function ems_require_action($conn, $screen, $verb = 'edit', array $opts = array())
    {
        $isPost = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST');
        /* لا فعلَ بلا طلبِ كتابة — القراءةُ تمرُّ لحارسِ العرضِ وحدَه */
        if (!$isPost) { return array('allowed' => true, 'perms' => array()); }

        $role  = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
        $super = defined('EMS_ROLE_SUPER_ADMIN') ? EMS_ROLE_SUPER_ADMIN : '-1';
        $deny  = isset($opts['deny_msg']) ? $opts['deny_msg'] : 'لا صلاحية لهذا الفعل على هذه الشاشة';

        /* ◆ صيغةُ الردِّ تتبع صيغةَ الطلب: سطحٌ يردُّ JSON لا يُكسَر بنصٍّ خام.
             (وإلا صار الحارسُ نفسُه عطلًا في شاشةٍ تعمل بـAJAX.) */
        $wantsJson = !empty($opts['ajax'])
            || (isset($_POST['ajax']) && (string) $_POST['ajax'] === '1')
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        /* ◆ والمنعُ يُعلَن **برمزِه الحوكميِّ** لا بنصٍّ وحدَه: سجلُّ الحوكمةِ
             وفواحصُه يقرآن الرمزَ `GOV-PERM-403-WRITE`، فمنعٌ بلا رمزٍ منعٌ
             **لا يراه أحد**. وقد قِيس: خمسةُ أسطحٍ مُنع فيها القارئُ فعلًا
             وأعلن المسبارُ «لم يُمنع» لأن الرمزَ غائب. */
        $bail = function ($msg) use ($wantsJson) {
            http_response_code(403);
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                exit(json_encode(array('success' => false, 'message' => $msg,
                                       'code' => 'GOV-PERM-403-WRITE'), JSON_UNESCAPED_UNICODE));
            }
            exit('GOV-PERM-403-WRITE — ' . $msg);
        };

        /* ① جلسة */
        if (!isset($_SESSION['user'])) {
            ems_require_action_log($screen, $verb, 'no_session');
            $bail('انتهت الجلسة — أعد تسجيل الدخول');
        }

        /* ② رمزُ الحماية — قبلَ فحصِ الصلاحية: رمزٌ فاسدٌ يعني طلبًا مزوَّرًا أصلًا */
        $needCsrf = array_key_exists('csrf', $opts) ? (bool) $opts['csrf'] : true;
        if ($needCsrf) {
            $tok = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
            if (!function_exists('verify_csrf_token') || !verify_csrf_token($tok)) {
                ems_require_action_log($screen, $verb, 'csrf_failed');
                $bail('رمز الحماية غير صالح — أعد تحميل الصفحة');
            }
        }

        /* ③ الصلاحيةُ على الفعل — والسوبر يمرُّ بعدَ الرمزِ لا قبلَه */
        if ($role === $super) {
            return array('allowed' => true, 'perms' => array('can_edit' => 1, 'can_delete' => 1));
        }
        $perms = function_exists('check_page_permissions') ? check_page_permissions($conn, $screen) : array();
        /* ◆ فعلُ `write` الجامع — لبوابةٍ على **مستوى الشاشة** قبلَ كلِّ فرعٍ كاتب:
             تسأل «أله حقُّ كتابةٍ ما هنا؟»، والفرعُ بعدَها يسأل عن حقِّه بعينِه
             (إضافةٌ أم تعديلٌ أم حذف). واشتراطُ `edit` وحدَها على شاشةٍ فعلُها
             **إضافةٌ** يمنع كاتبًا صحيحًا — وقد قِيس: الدور 12 يملك `can_add`
             على شاشةِ الجزاءاتِ ولا يملك `can_edit`، فمُنع خطأً. */
        if (strtolower($verb) === 'write') {
            $ok = !empty($perms['can_add']) || !empty($perms['can_edit']) || !empty($perms['can_delete']);
        } else {
            $key = 'can_' . preg_replace('/[^a-z]/', '', strtolower($verb));
            $known = array('can_add', 'can_edit', 'can_delete', 'can_approve', 'can_export', 'can_view');
            $ok = in_array($key, $known, true) && !empty($perms[$key]);
        }
        if (!$ok) {
            ems_require_action_log($screen, $verb, 'denied');
            $bail($deny);
        }
        return array('allowed' => true, 'perms' => $perms);
    }

    /** تسجيلُ المنع — المعرِّفُ يُقرأ من الجلسةِ ولا يُكتب اسمُ أحدٍ نصًّا */
    function ems_require_action_log($screen, $verb, $kind)
    {
        $uid  = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
        $role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '-';
        $line = sprintf("[%s] require_action %s screen=%s verb=%s uid=%d role=%s\n",
            date('Y-m-d H:i:s'), $kind, $screen, $verb, $uid, $role);
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
        @file_put_contents($dir . '/action_guard.log', $line, FILE_APPEND);
    }
}
