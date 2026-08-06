<?php
/**
 * resolveManager — المدير المباشر من هرم users.parent_id (قرار المالك 2026-08-05)
 * ───────────────────────────────────────────────────────────────────────────
 * كان دَينًا معلنًا في E-04 («resolveManager() لم تُبنَ»). القمة (u881) بلا أب.
 * والمتحقق البديل (قرار 6 في UPDATE0008_DECISIONS_LOG): مدير المكلِّف، فإن
 * انعدم فالحوكمة (مدير الصلاحيات 15 ثم التنفيذي 9).
 */

if (!function_exists('ems_resolve_manager')) {
    /** المدير المباشر لمستخدم — null عند القمة أو الغياب. */
    function ems_resolve_manager(mysqli $conn, $userId)
    {
        $userId = intval($userId);
        if ($userId <= 0) { return null; }
        $st = $conn->prepare("SELECT parent_id FROM users WHERE id = ? LIMIT 1");
        $st->bind_param('i', $userId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        $p = $r ? intval($r['parent_id']) : 0;
        return ($p > 0 && $p !== $userId) ? $p : null;
    }
}

if (!function_exists('ems_resolve_verifier')) {
    /**
     * متحقِّقُ مهمةٍ مكلِّفُها مستفيدُها (WF-04 + قرار 6): مديرُ المكلِّف،
     * فإن انعدم فأولُ حسابٍ نشطٍ من أدوار الحوكمة (15 ثم 9) في شركته.
     */
    function ems_resolve_verifier(mysqli $conn, $assignerUserId, $companyId)
    {
        $mgr = ems_resolve_manager($conn, $assignerUserId);
        if ($mgr !== null) { return $mgr; }
        $companyId = intval($companyId);
        foreach (array('15', '9') as $roleId) {
            $st = $conn->prepare("SELECT id FROM users
                                   WHERE role = ? AND company_id = ? AND COALESCE(status,'active') = 'active'
                                     AND id <> ? ORDER BY id LIMIT 1");
            $aid = intval($assignerUserId);
            $st->bind_param('sii', $roleId, $companyId, $aid);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r) { return intval($r['id']); }
        }
        return null;
    }
}

if (!function_exists('ems_user_on_leave')) {
    /**
     * أهليةُ التلقي من الموارد (قرار 10 · التزام M-13): إجازةٌ ساريةٌ للمستخدم؟
     * تُقرأ من worker_leave_absence عبر جسر users.employee_id — تُرجع صفَّ
     * الإجازة (وفيه substitute_id) أو null. «الجديد لا يُسنَد إليه آليًّا
     * أثناء الإجازة — والمفتوح يبقى بتنبيه».
     */
    function ems_user_on_leave(mysqli $conn, $userId)
    {
        $userId = intval($userId);
        if ($userId <= 0) { return null; }
        $st = $conn->prepare(
            "SELECT wla.id, wla.event_type, wla.date_from, wla.date_to, wla.substitute_id,
                    su.id AS substitute_user_id
               FROM users u
               JOIN worker_leave_absence wla ON wla.employee_id = u.employee_id
               LEFT JOIN users su ON su.employee_id = wla.substitute_id
                                 AND COALESCE(su.status,'active') = 'active'
              WHERE u.id = ? AND u.employee_id IS NOT NULL
                AND wla.state IN ('معتمد','مفتوح','مُغطًّى')
                AND CURDATE() BETWEEN wla.date_from AND COALESCE(wla.date_to, CURDATE())
              ORDER BY wla.date_to DESC LIMIT 1");
        $st->bind_param('i', $userId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ?: null;
    }
}

if (!function_exists('ems_pick_available')) {
    /**
     * يرشّح مرشّحًا متاحًا من قائمةٍ مرتّبة. سلّمُ الأولوية المعتمد:
     *   المرشّحُ المتاحُ بترتيبه ← **نائبُ المُجاز الرسميُّ النافذ** (الإنابةُ
     *   قرارٌ رسميٌّ يغلب «التالي بالترتيب» الاعتباطي) ← التالي المتاح.
     * لا متاحَ إطلاقًا ⇒ يُعاد الأول بعلَم on_leave (فلا يضيع العنصر بلا
     * جهة — AC-WFM-02) ويُنبَّه المكلِّف.
     * @return array{user_id:?int, on_leave:bool, leave:?array}
     */
    function ems_pick_available(mysqli $conn, array $candidateUserIds)
    {
        $first = null;
        $firstLeave = null;
        foreach ($candidateUserIds as $uid) {
            $uid = intval($uid);
            if ($uid <= 0) { continue; }
            $leave = ems_user_on_leave($conn, $uid);
            if ($leave === null) { return array('user_id' => $uid, 'on_leave' => false, 'leave' => null); }
            if ($first === null) { $first = $uid; $firstLeave = $leave; }
            // نائبُ المُجاز النافذ مرشّحٌ مقدَّم
            if (!empty($leave['substitute_user_id'])) {
                $sub = intval($leave['substitute_user_id']);
                if ($sub > 0 && ems_user_on_leave($conn, $sub) === null) {
                    return array('user_id' => $sub, 'on_leave' => false, 'leave' => null);
                }
            }
        }
        return array('user_id' => $first, 'on_leave' => $first !== null, 'leave' => $firstLeave);
    }
}

if (!function_exists('ems_manager_scope_user_ids')) {
    /**
     * نطاق رؤية المدير (قرار 9): المستوى المباشر افتراضًا؛ $depth أكبر للتعمّق.
     * يُرجع معرّفات المرؤوسين (بلا المدير نفسه) — من الهيكل لا من قوائم ثابتة.
     */
    function ems_manager_scope_user_ids(mysqli $conn, $managerId, $depth = 1)
    {
        $out = array();
        $frontier = array(intval($managerId));
        $depth = max(1, min(6, intval($depth)));
        for ($i = 0; $i < $depth && $frontier; $i++) {
            $in = implode(',', array_map('intval', $frontier));
            $r = mysqli_query($conn, "SELECT id FROM users WHERE parent_id IN ($in) AND COALESCE(status,'active')='active'");
            $frontier = array();
            while ($r && ($x = mysqli_fetch_row($r))) {
                $id = intval($x[0]);
                if (!isset($out[$id])) { $out[$id] = true; $frontier[] = $id; }
            }
        }
        return array_keys($out);
    }
}
