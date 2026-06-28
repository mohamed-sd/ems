<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — محرّك التناوب (Rotation).
 *
 * نقطة حقيقةٍ واحدةٍ لمنطق التدوير: يقرأ rotation_pattern/work_days/leave_days من
 * worker_contract، يحتسب next_rotation_date آلياً، ويعيد قائمة «من اقترب تدويره»
 * خلال مدّةٍ. يُستدعى من شاشة العقد (لحساب الاستحقاق) ومن السجل التشغيلي (للتنبيه).
 *
 * نمط دوالٍ خفيفٌ بلا autoloader. صفر لمسٍ للقائم — يعمل على جداول الطبقة فقط.
 * Prepared Statements في كل قراءة.
 */

if (!function_exists('ems_rotation_effective_days')) {
    /**
     * يحلّ أيام العمل/الإجازة الفعّالة من نمط التناوب.
     * الأنماط المعتمدة (worker_contract.rotation_pattern):
     *   'بلا' · 'شهران+شهر' · 'ثلاثة أشهر+15 يوم' · 'مخصّص'
     * @return array ['work'=>int,'leave'=>int]  (0/0 إذا «بلا»)
     */
    function ems_rotation_effective_days($pattern, $work_days = null, $leave_days = null)
    {
        $p = trim((string) $pattern);
        switch ($p) {
            case 'شهران+شهر':
                return ['work' => 60, 'leave' => 30];
            case 'ثلاثة أشهر+15 يوم':
                return ['work' => 90, 'leave' => 15];
            case 'مخصّص':
                return [
                    'work'  => max(0, (int) $work_days),
                    'leave' => max(0, (int) $leave_days),
                ];
            case 'بلا':
            default:
                // نمطٌ غير معروفٍ أو «بلا»: نعتمد القيم اليدوية إن وُجدت، وإلا صفر.
                return [
                    'work'  => max(0, (int) $work_days),
                    'leave' => max(0, (int) $leave_days),
                ];
        }
    }
}

if (!function_exists('ems_rotation_compute_next')) {
    /**
     * يحتسب تاريخ الاستحقاق القادم للتدوير = تاريخ الأساس + أيام العمل الفعّالة.
     * @return string|null  Y-m-d أو null إذا تعذّر (لا أساس/لا أيام عمل/نمط «بلا»).
     */
    function ems_rotation_compute_next($base_date, $pattern, $work_days = null, $leave_days = null)
    {
        $p = trim((string) $pattern);
        if ($p === 'بلا' || $p === '') {
            return null;
        }
        if (empty($base_date) || substr((string) $base_date, 0, 10) === '0000-00-00') {
            return null;
        }
        $eff = ems_rotation_effective_days($pattern, $work_days, $leave_days);
        if ($eff['work'] <= 0) {
            return null;
        }
        $base = DateTime::createFromFormat('Y-m-d', substr((string) $base_date, 0, 10));
        if (!$base) {
            return null;
        }
        $base->modify('+' . (int) $eff['work'] . ' day');
        return $base->format('Y-m-d');
    }
}

if (!function_exists('ems_rotation_resolve_next_for_save')) {
    /**
     * مساعدٌ لشاشة العقد: يعيد next_rotation_date المناسب عند الحفظ.
     * إن أدخله المستخدم يدوياً يُحترَم؛ وإلّا يُحتسَب آلياً من البداية وأيام العمل.
     * @return string|null
     */
    function ems_rotation_resolve_next_for_save($manual_next, $date_start, $pattern, $work_days = null, $leave_days = null)
    {
        $manual = trim((string) $manual_next);
        if ($manual !== '' && substr($manual, 0, 10) !== '0000-00-00') {
            return substr($manual, 0, 10); // إدخالٌ يدويٌّ — يُحترَم كتجاوز
        }
        return ems_rotation_compute_next($date_start, $pattern, $work_days, $leave_days);
    }
}

if (!function_exists('ems_rotation_due_soon')) {
    /**
     * قائمة العقود التي اقترب تدويرها خلال $within_days يوماً (شاملاً المتأخّر).
     * نافذةٌ فقط (state='نافذ') وذات نمط تناوبٍ فعليٍّ وتاريخ استحقاقٍ محدَّد.
     *
     * @param int|null $company_id قيد العزل (null = تجاوز — للمشرف العام).
     * @param int      $within_days نافذة التنبيه (افتراضي 14).
     * @return array صفوف [worker_id, worker_name, contract_id, code, rotation_pattern,
     *               next_rotation_date, days_left] مرتّبةً بالأقرب.
     */
    function ems_rotation_due_soon($conn, $company_id = null, $within_days = 14)
    {
        $within = max(0, (int) $within_days);
        $rows = [];
        $sql = "SELECT wc.id AS contract_id, wc.code, wc.employee_id, wc.rotation_pattern,
                       wc.next_rotation_date,
                       DATEDIFF(wc.next_rotation_date, CURDATE()) AS days_left,
                       e.name AS worker_name
                FROM worker_contract wc
                INNER JOIN employees e ON e.id = wc.employee_id  /* unified: worker = employee */
                WHERE wc.state = 'نافذ'
                  AND wc.rotation_pattern <> 'بلا'
                  AND wc.next_rotation_date IS NOT NULL
                  AND wc.next_rotation_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
        $params = [$within];
        $types  = 'i';
        if ($company_id !== null) {
            $sql .= " AND wc.company_id = ?";
            $params[] = (int) $company_id;
            $types   .= 'i';
        }
        $sql .= " ORDER BY wc.next_rotation_date ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $rows;
        }
        $bind = [$types];
        for ($i = 0; $i < count($params); $i++) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}
