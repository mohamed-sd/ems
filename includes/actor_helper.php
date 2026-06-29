<?php
/**
 * actor_helper — تسمية الفاعل الموحّدة (مَن فعل ماذا).
 *
 * يحوّل «رقم المستخدم» المخزَّن في أعمدة *_by (created_by/updated_by/...) إلى
 * تسمية احترافية موحّدة تُجيب «من الموظف الذي يقف خلف هذا الحساب» عبر الربط
 * الجديد users.employee_id.
 *
 * الصيغة: «اسم الموظف — (الحساب · الدور)». إن لم يكن الحساب مرتبطاً بموظف،
 * تُظهر تنبيهاً «بلا موظف». مُخزَّن مؤقتاً لكل user_id (آمن للاستدعاء لكل صف).
 *
 * @see [[users-employees-link]]
 */

if (!function_exists('ems_actor_resolve')) {
    /**
     * يجلب صف الفاعل (الحساب + الموظف + الدور) مع تخزين مؤقت.
     * @return array<string,mixed>|null
     */
    function ems_actor_resolve($conn, $user_id)
    {
        static $cache = [];
        $uid = intval($user_id);
        if ($uid <= 0) {
            return null;
        }
        if (array_key_exists($uid, $cache)) {
            return $cache[$uid];
        }

        $row = null;
        if ($conn instanceof mysqli) {
            $stmt = @mysqli_prepare(
                $conn,
                "SELECT u.name AS account_name, u.username, u.role, u.employee_id,
                        e.name AS employee_name,
                        r.name AS role_name
                 FROM users u
                 LEFT JOIN employees e ON e.id = u.employee_id
                 LEFT JOIN roles r ON r.id = u.role
                 WHERE u.id = ? LIMIT 1"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $uid);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
            }
        }

        return $cache[$uid] = $row;
    }
}

if (!function_exists('ems_actor_label')) {
    /**
     * تسمية الفاعل الموحّدة.
     *
     * @param mysqli          $conn
     * @param int|string|null $user_id  رقم المستخدم (من عمود *_by)
     * @param bool            $html     true = HTML بسطرين (موظف غامق + حساب/دور)
     * @return string
     */
    function ems_actor_label($conn, $user_id, $html = false)
    {
        $uid = intval($user_id);
        if ($uid <= 0) {
            return $html ? '<span style="color:#999;">—</span>' : '—';
        }

        $row = ems_actor_resolve($conn, $user_id);
        if (!$row) {
            return $html ? '<span style="color:#999;">مستخدم #' . $uid . '</span>' : ('مستخدم #' . $uid);
        }

        $employee = trim((string) ($row['employee_name'] ?? ''));
        $account  = trim((string) ((($row['account_name'] ?? '') !== '') ? $row['account_name'] : ($row['username'] ?? '')));
        $roleRaw  = (string) ($row['role'] ?? '');
        $role     = trim((string) ($row['role_name'] ?? ''));
        if ($role === '') {
            $role = ($roleRaw === '-1') ? 'مدير أعلى' : ($roleRaw !== '' ? ('دور #' . $roleRaw) : '—');
        }
        if ($account === '') {
            $account = 'مستخدم #' . $uid;
        }

        if ($html) {
            $esc = function ($s) {
                return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
            };
            if ($employee !== '') {
                return '<strong>' . $esc($employee) . '</strong>'
                     . '<br><small style="color:#777;"><i class="fa fa-user-circle"></i> '
                     . $esc($account) . ' · ' . $esc($role) . '</small>';
            }
            return $esc($account) . '<br><small style="color:#c0392b;">— بلا موظف · ' . $esc($role) . '</small>';
        }

        if ($employee !== '') {
            return $employee . ' — (' . $account . ' · ' . $role . ')';
        }
        return $account . ' — (بلا موظف · ' . $role . ')';
    }
}
