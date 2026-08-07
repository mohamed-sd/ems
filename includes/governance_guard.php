<?php
/**
 * حارسُ الحوكمة — الطبقةُ التي لا تسقط بانحراف البيانات (SEC-GOV · 2026-08-06)
 * ═══════════════════════════════════════════════════════════════════════════
 * وُلد من ثلاث ثغراتٍ حرجةٍ كشفَتها جولةُ فحصٍ بدور المبيعات:
 *
 *   ح-01  Settings/modules.php كان حارسُها الوحيد «أنك مسجَّلُ الدخول». ولأنها
 *         غيرُ مسجَّلةٍ في modules فالحارسُ المركزي يمرُّ عليها شفافًا:
 *         get_current_page_permissions() تُرجع كلَّ الصلاحيات true حين لا
 *         تُحَلّ الشاشةُ إلى موديول (permissions_helper.php:451) وenforce
 *         لا يحجب إلا حين id !== null (‏:601). فكانت مفتوحةً لكل مستخدم.
 *
 *   ح-02  عشرةُ أدوارٍ تملك add/edit/delete على main/users.php، وقائمةُ الأدوار
 *         فيها تعرض كلَّ الأدوار الجذرية — فحسابُ مبيعاتٍ يُنشئ حسابًا بدور
 *         «إدارة الصلاحيات». مسارُ تصعيدٍ مكتمل.
 *
 *   ح-03  صلاحياتُ كتابةٍ خارج القائمة الجانبية في كل الأدوار الموروثة.
 *
 * ─── لماذا لا يكفي تصحيحُ البيانات؟ ────────────────────────────────────────
 * لأن شاشةَ تسجيل الشاشات قادرةٌ على **حذف صفِّ الموديول الخاصِّ بها**، فأيُّ
 * حارسٍ يستمدُّ قوَّته من وجود صفٍّ في القاعدة يمكن أن يُنزَع من داخل الشاشة
 * التي يحرسها. لذلك أرضيةُ هذا الحارس **في الكود**: أدوارُ إدارةِ الحسابات
 * تدخل دائمًا، ومن سواها لا يدخل إلا بمنحٍ صريحٍ على موديولٍ مسجَّل — والشاشةُ
 * غيرُ المسجَّلة تُغلق بدل أن تُفتح (عكسُ الافتراض المركزي، عمدًا).
 *
 * ─── لماذا الدورُ 1 مع الدور 15؟ ───────────────────────────────────────────
 * قياسُ القاعدة الحيّة: الشركةُ 1 ليس فيها أيُّ حسابٍ بالدور 15 — أرضيةٌ
 * بالدور 15 وحدَه تقفلها خارج نظامها إلى الأبد. فالأرضيةُ دوران: مالكُ
 * main/users.php (1) ومديرُ الصلاحيات (15).
 */

require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/permissions_helper.php';

if (!function_exists('ems_governance_admin_roles')) {
    /**
     * أدوارُ إدارةِ الحسابات — الأرضيةُ الصلبة التي لا تسقط بانحراف البيانات.
     * @return string[]
     */
    function ems_governance_admin_roles()
    {
        return array(EMS_ROLE_OPERATIONS_MGR, EMS_ROLE_PERMISSIONS_MGR); // '1' · '15'
    }
}

if (!function_exists('ems_is_account_admin')) {
    function ems_is_account_admin($role)
    {
        return in_array((string) $role, ems_governance_admin_roles(), true);
    }
}

if (!function_exists('ems_current_role')) {
    function ems_current_role()
    {
        return isset($_SESSION['user']['role']) ? (string) $_SESSION['user']['role'] : '';
    }
}

if (!function_exists('ems_gov_log')) {
    function ems_gov_log($event, $detail)
    {
        if (function_exists('log_security_event')) {
            log_security_event($event, $detail);
            return;
        }
        error_log('[SEC-GOV] ' . $event . ' ' . $detail);
    }
}

if (!function_exists('ems_require_governance_screen')) {
    /**
     * بوابةُ شاشةِ حوكمةٍ — fail-closed بالبناء.
     *
     * الترتيب: (١) جلسةٌ حيّة  (٢) السوبر أدمن يمرّ  (٣) أرضيةُ إدارة الحسابات
     * تمرّ بصلاحياتٍ كاملة  (٤) من سواهم: موديولٌ مسجَّلٌ + can_view صريح، وإلا
     * حجبٌ مُسجَّل. الشاشةُ غيرُ المسجَّلة تُغلق — لا تُفتح.
     *
     * @param  mysqli $conn
     * @param  string $redirect وجهةُ الحجب (نسبيةٌ لموضع الشاشة)
     * @return array{id:?int,can_view:bool,can_add:bool,can_edit:bool,can_delete:bool}
     */
    function ems_require_governance_screen($conn, $redirect = '../main/dashboard.php')
    {
        $full = array('id' => null, 'can_view' => true, 'can_add' => true,
                      'can_edit' => true, 'can_delete' => true);

        if (!isset($_SESSION['user'])) {
            header('Location: ../login.php');
            exit();
        }
        $role = ems_current_role();
        if ($role === EMS_ROLE_SUPER_ADMIN) {
            return $full;
        }
        if (ems_is_account_admin($role)) {
            return $full;
        }

        $perms = get_current_page_permissions($conn);
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';

        // شاشةٌ لا تُحَلّ إلى موديول = لا منحَ صريحًا يمكن الاستناد إليه ⇒ تُغلق.
        if ($perms['id'] === null) {
            ems_gov_log('GOVERNANCE_SCREEN_DENY_UNREGISTERED',
                'path=' . $script . ' role=' . $role);
            ems_gov_flash_redirect($redirect, 'الشاشة غير مسجَّلة في سجل الوحدات فلا تُمنح صلاحيتها لأحد',
                'GOV-UNREG-403', 'راجع مدير الصلاحيات لتسجيل الشاشة قبل طلب الوصول');
        }
        if (empty($perms['can_view'])) {
            ems_gov_log('GOVERNANCE_SCREEN_DENY',
                'path=' . $script . ' role=' . $role . ' module=' . $perms['id']);
            ems_gov_flash_redirect($redirect, 'لا تملك صلاحية عرض هذه الشاشة',
                'GOV-VIEW-403', 'اطلب منحة العرض من مدير الصلاحيات إن كانت ضمن عملك');
        }
        return $perms;
    }
}

if (!function_exists('ems_require_governance_write')) {
    /**
     * فحصُ فعلِ كتابةٍ على شاشةِ حوكمة — يُستدعى قبل أيِّ INSERT/UPDATE/DELETE.
     * يُنهي الطلبَ بإعادةِ توجيهٍ برسالةٍ حين لا صلاحية.
     *
     * @param array  $perms  ناتجُ ems_require_governance_screen()
     * @param string $action add|edit|delete
     * @param string $back   وجهةُ العودة
     */
    function ems_require_governance_write($perms, $action, $back)
    {
        $field = 'can_' . $action;
        if (!empty($perms[$field])) {
            return;
        }
        ems_gov_log('GOVERNANCE_WRITE_DENY',
            'path=' . (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')
            . ' role=' . ems_current_role() . ' action=' . $action);
        ems_gov_flash_redirect($back, 'لا تملك صلاحية هذا الإجراء (' . $action . ') على الشاشة',
            'GOV-ACT-403', 'الإجراء يتطلب منحة كتابة من مدير الصلاحيات');
    }
}

if (!function_exists('ems_assignable_role_ids')) {
    /**
     * الأدوارُ التي يجوز للفاعل إسنادُها لحساب — «لا تمنح ما لا تملك».
     *
     * السوبر أدمن وأدوارُ إدارةِ الحسابات: بلا قيد (تُرجع null).
     * من سواهم: دورُه نفسُه + ذريّتُه في شجرة roles.parent_role_id حصرًا —
     * وهي القاعدةُ ذاتُها المطبَّقة في main/project_users.php منذ البداية،
     * تُنقل هنا ليشترك فيها كلُّ مسارٍ يُسنِد دورًا.
     *
     * @param  mysqli $conn
     * @param  string $actorRole
     * @return string[]|null  null = بلا قيد
     */
    function ems_assignable_role_ids($conn, $actorRole)
    {
        $actorRole = (string) $actorRole;
        if ($actorRole === EMS_ROLE_SUPER_ADMIN || ems_is_account_admin($actorRole)) {
            return null;
        }
        if ($actorRole === '' || !ctype_digit($actorRole)) {
            return array();
        }

        // شجرةُ الأبناء بالكامل (عمقٌ محدودٌ عمليًّا؛ الحدُّ يقي من دورةٍ في البيانات)
        $children = array();
        $res = @mysqli_query($conn, "SELECT id, parent_role_id FROM roles
            WHERE (status = '1' OR status = 1)");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $parent = (string) (isset($row['parent_role_id']) ? $row['parent_role_id'] : '');
                if ($parent === '' || $parent === '0') {
                    continue;
                }
                $children[$parent][] = (string) $row['id'];
            }
        }

        $allowed = array($actorRole);
        $queue = array($actorRole);
        $guard = 0;
        while ($queue && $guard++ < 64) {
            $node = array_shift($queue);
            if (!isset($children[$node])) {
                continue;
            }
            foreach ($children[$node] as $child) {
                if (!in_array($child, $allowed, true)) {
                    $allowed[] = $child;
                    $queue[] = $child;
                }
            }
        }
        return $allowed;
    }
}

if (!function_exists('ems_role_is_assignable')) {
    /**
     * تحققٌ خادميٌّ من دورٍ مُرسَل — تقييدُ القائمة في الواجهة وحدَه لا يكفي.
     */
    function ems_role_is_assignable($conn, $actorRole, $targetRole)
    {
        $allowed = ems_assignable_role_ids($conn, $actorRole);
        if ($allowed === null) {
            return true;
        }
        return in_array((string) $targetRole, $allowed, true);
    }
}
