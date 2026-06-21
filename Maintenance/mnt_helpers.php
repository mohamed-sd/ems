<?php
/**
 * ════════════════════════════════════════════════════════════════════════════
 * Maintenance module — shared helper functions (دوال قسم الصيانة المشتركة)
 * ════════════════════════════════════════════════════════════════════════════
 * يحتوي دوالاً نقيّة (بلا أي إخراج HTML) تُضمّن بعد config.php في كل شاشات الصيانة.
 * كل الدوال تحترم عزل الشركة (company_id) وتستخدم prepared statements للكتابة.
 *
 * مبادئ منطق الحالة (القسم 6 من المواصفات — لا تخالفها):
 *   • الصيانة لا تكتب equipments.availability_status عند الدخول إطلاقاً
 *     (دخول «تحت الصيانة» حصري لمسار move_oprators لمدير الحركة والتشغيل).
 *   • عند فتح أمر صيانة: تُضبط operations.equipment_health='معطلة' للتشغيل الساري فقط.
 *   • عند إغلاق أمر صيانة: equipment_health='سليمة' + availability_status='متاحة للعمل'
 *     + last_maintenance_date=اليوم. ولا تُمسّ operations.status (التشغيل) أبداً.
 */

if (!defined('MNT_HELPERS_LOADED')) {
    define('MNT_HELPERS_LOADED', true);

    /**
     * توليد مرجع تسلسلي مقيّد بالشركة، مثل BR-2026-0001 / MNT-2026-0007.
     *
     * @param mysqli $conn
     * @param string $table       اسم جدول الصيانة (يُمرّر داخلياً فقط — قائمة بيضاء)
     * @param string $prefix      البادئة (BR/MNT/INS/PLN)
     * @param int    $company_id  معرّف الشركة
     * @return string
     */
    function mnt_next_code($conn, $table, $prefix, $company_id)
    {
        // قائمة بيضاء صارمة لأسماء الجداول (لا تُبنى من مدخلات المستخدم).
        $allowed = array('mnt_breakdown', 'mnt_order', 'mnt_inspection', 'mnt_plan');
        if (!in_array($table, $allowed, true)) {
            return $prefix . '-' . date('Y') . '-0001';
        }

        $company_id = intval($company_id);
        $year = date('Y');

        // العدّاد = أعلى تسلسل لنفس البادئة/السنة/الشركة + 1 (تطبيق داخلي قليل التزامن).
        $like = $prefix . '-' . $year . '-%';
        $sql = "SELECT COUNT(*) AS c FROM `$table` WHERE company_id = ? AND code LIKE ?";
        $next = 1;
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'is', $company_id, $like);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($row = mysqli_fetch_assoc($res))) {
                $next = intval($row['c']) + 1;
            }
            mysqli_stmt_close($stmt);
        }

        return $prefix . '-' . $year . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * فتح أمر صيانة ⇐ ضبط الصحة الفنية «معطلة» للتشغيل الساري لهذه المعدة.
     * لا تُكتب availability_status ولا تُمسّ operations.status (القرار 4.5/6).
     *
     * @return int عدد صفوف operations المتأثرة
     */
    function mnt_mark_equipment_unhealthy($conn, $equipment_id, $company_id, $user_id)
    {
        $equipment_id = intval($equipment_id);
        $company_id   = intval($company_id);
        $user_id      = intval($user_id);
        if ($equipment_id <= 0) {
            return 0;
        }

        // فقط التشغيل الساري (status = 1) لهذه المعدة وداخل نفس الشركة.
        $sql = "UPDATE operations
                   SET equipment_health = 'معطلة',
                       health_reason     = 'صيانة',
                       health_updated_at = NOW(),
                       health_updated_by = ?
                 WHERE equipment = ?
                   AND status = 1
                   AND (company_id = ? OR company_id IS NULL)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'iii', $user_id, $equipment_id, $company_id);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            return max(0, (int) $affected);
        }
        return 0;
    }

    /**
     * إغلاق أمر صيانة ⇐ إعادة الصحة الفنية «سليمة» لما سبق وضعه «معطلة» لهذه المعدة.
     * نعيد فقط ما وضعناه (equipment_health='معطلة') دون لمس operations.status.
     */
    function mnt_mark_equipment_healthy($conn, $equipment_id, $company_id, $user_id)
    {
        $equipment_id = intval($equipment_id);
        $company_id   = intval($company_id);
        $user_id      = intval($user_id);
        if ($equipment_id <= 0) {
            return 0;
        }

        $sql = "UPDATE operations
                   SET equipment_health = 'سليمة',
                       health_reason     = NULL,
                       health_updated_at = NOW(),
                       health_updated_by = ?
                 WHERE equipment = ?
                   AND equipment_health = 'معطلة'
                   AND (company_id = ? OR company_id IS NULL)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'iii', $user_id, $equipment_id, $company_id);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            return max(0, (int) $affected);
        }
        return 0;
    }

    /**
     * إغلاق أمر صيانة ⇐ إعادة المعدة «متاحة للعمل» + تحديث آخر صيانة.
     * هذا هو الموضع الوحيد الذي تكتب فيه الصيانة availability_status / last_maintenance_date.
     * مقيّد بالشركة، ويحدّث availability_state إن وُجد (نموذج الحقلين القائم).
     */
    function mnt_return_equipment_available($conn, $equipment_id, $company_id)
    {
        $equipment_id = intval($equipment_id);
        $company_id   = intval($company_id);
        if ($equipment_id <= 0) {
            return false;
        }

        $has_state = function_exists('db_table_has_column')
            ? db_table_has_column($conn, 'equipments', 'availability_state')
            : false;
        $has_last  = function_exists('db_table_has_column')
            ? db_table_has_column($conn, 'equipments', 'last_maintenance_date')
            : false;

        $sets = array("availability_status = 'متاحة للعمل'");
        if ($has_state) {
            $sets[] = "availability_state = 'متوفرة'";
        }
        if ($has_last) {
            $sets[] = "last_maintenance_date = CURDATE()";
        }

        $sql = "UPDATE equipments SET " . implode(', ', $sets)
             . " WHERE id = ? AND (company_id = ? OR company_id IS NULL) LIMIT 1";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'ii', $equipment_id, $company_id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return (bool) $ok;
        }
        return false;
    }

    /**
     * إكمال تفتيش ⇐ كتابة نتيجته على كرت المعدة (equipment_condition/engine_condition).
     * مقيّد بالشركة. القيم النصّية تأتي من بنود التفتيش.
     */
    function mnt_apply_inspection_to_equipment($conn, $equipment_id, $company_id, $equipment_condition, $engine_condition)
    {
        $equipment_id = intval($equipment_id);
        $company_id   = intval($company_id);
        if ($equipment_id <= 0) {
            return false;
        }

        $has_eq_cond = function_exists('db_table_has_column') ? db_table_has_column($conn, 'equipments', 'equipment_condition') : false;
        $has_en_cond = function_exists('db_table_has_column') ? db_table_has_column($conn, 'equipments', 'engine_condition') : false;

        $sets = array();
        $types = '';
        $vals = array();
        if ($has_eq_cond && $equipment_condition !== null && $equipment_condition !== '') {
            $sets[] = 'equipment_condition = ?';
            $types .= 's';
            $vals[] = $equipment_condition;
        }
        if ($has_en_cond && $engine_condition !== null && $engine_condition !== '') {
            $sets[] = 'engine_condition = ?';
            $types .= 's';
            $vals[] = $engine_condition;
        }
        if (empty($sets)) {
            return false;
        }

        $sql = "UPDATE equipments SET " . implode(', ', $sets)
             . " WHERE id = ? AND (company_id = ? OR company_id IS NULL) LIMIT 1";
        $types .= 'ii';
        $vals[] = $equipment_id;
        $vals[] = $company_id;

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, $types, ...$vals);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return (bool) $ok;
        }
        return false;
    }

    /**
     * إعادة احتساب تكاليف أمر الصيانة من أسطر العمالة والقطع (مقيّد بالشركة).
     * total_cost = labor_cost + parts_cost + external_cost.
     */
    function mnt_recalc_order_totals($conn, $order_id, $company_id)
    {
        $order_id   = intval($order_id);
        $company_id = intval($company_id);
        if ($order_id <= 0) {
            return false;
        }

        $labor = 0.0;
        $parts = 0.0;

        if ($stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(cost),0) AS s FROM mnt_order_labor WHERE order_id = ? AND company_id = ?")) {
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $company_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($r = mysqli_fetch_assoc($res))) { $labor = floatval($r['s']); }
            mysqli_stmt_close($stmt);
        }
        if ($stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(subtotal),0) AS s FROM mnt_order_part WHERE order_id = ? AND company_id = ?")) {
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $company_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($r = mysqli_fetch_assoc($res))) { $parts = floatval($r['s']); }
            mysqli_stmt_close($stmt);
        }

        // external_cost يُدخل يدوياً على الأمر؛ نقرؤه لإدراجه في total_cost.
        $external = 0.0;
        if ($stmt = mysqli_prepare($conn, "SELECT COALESCE(external_cost,0) AS e FROM mnt_order WHERE id = ? AND company_id = ?")) {
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $company_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($r = mysqli_fetch_assoc($res))) { $external = floatval($r['e']); }
            mysqli_stmt_close($stmt);
        }

        $total = $labor + $parts + $external;
        if ($stmt = mysqli_prepare($conn, "UPDATE mnt_order SET labor_cost = ?, parts_cost = ?, total_cost = ? WHERE id = ? AND company_id = ?")) {
            mysqli_stmt_bind_param($stmt, 'dddii', $labor, $parts, $total, $order_id, $company_id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return (bool) $ok;
        }
        return false;
    }

    /**
     * إعادة جدولة خطة وقائية بعد إغلاق أمر مولّد منها (مقيّد بالشركة).
     * ساعات: next_due_meter = العدّاد الحالي + interval_value. زمن: next_due_date = اليوم + interval_value يوماً.
     */
    function mnt_reschedule_plan($conn, $plan_id, $company_id, $current_meter = null)
    {
        $plan_id    = intval($plan_id);
        $company_id = intval($company_id);
        if ($plan_id <= 0) {
            return false;
        }

        $plan = null;
        if ($stmt = mysqli_prepare($conn, "SELECT trigger_basis, interval_value, equipment_id FROM mnt_plan WHERE id = ? AND company_id = ? LIMIT 1")) {
            mysqli_stmt_bind_param($stmt, 'ii', $plan_id, $company_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $plan = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
        }
        if (!$plan) {
            return false;
        }

        $interval = intval($plan['interval_value']);
        if (trim((string) $plan['trigger_basis']) === 'زمن') {
            $sql = "UPDATE mnt_plan
                       SET last_done_date = CURDATE(),
                           next_due_date  = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                     WHERE id = ? AND company_id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, 'iii', $interval, $plan_id, $company_id);
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                return (bool) $ok;
            }
        } else {
            // ساعات: العدّاد الحالي من التايم‌شيت إن لم يُمرّر صراحةً (بدل افتراض صفر).
            if ($current_meter !== null) {
                $meter = floatval($current_meter);
            } elseif (intval($plan['equipment_id'] ?? 0) > 0) {
                $meter = mnt_equipment_actual_hours($conn, intval($plan['equipment_id']), $company_id);
            } else {
                $meter = 0.0;
            }
            $next_meter = $meter + $interval;
            $sql = "UPDATE mnt_plan
                       SET last_done_date  = CURDATE(),
                           last_done_meter = ?,
                           next_due_meter  = ?
                     WHERE id = ? AND company_id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, 'ddii', $meter, $next_meter, $plan_id, $company_id);
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                return (bool) $ok;
            }
        }
        return false;
    }

    /**
     * إجمالي ساعات التشغيل الفعلية لمعدة من التايم‌شيت (مصدر الوقائية، القرار 9).
     * timesheet لا يحوي equipment_id؛ يُربط عبر operations: timesheet.operator = operations.id
     * و operations.equipment = equipments.id. مقيّد بالشركة.
     */
    function mnt_equipment_actual_hours($conn, $equipment_id, $company_id)
    {
        $equipment_id = intval($equipment_id);
        $company_id   = intval($company_id);
        if ($equipment_id <= 0) {
            return 0.0;
        }

        $hours = 0.0;
        $sql = "SELECT COALESCE(SUM(t.operator_hours),0) AS h
                  FROM timesheet t
                  INNER JOIN operations o ON o.id = t.operator
                 WHERE o.equipment = ?
                   AND (t.company_id = ? OR t.company_id IS NULL)";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'ii', $equipment_id, $company_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($r = mysqli_fetch_assoc($res))) { $hours = floatval($r['h']); }
            mysqli_stmt_close($stmt);
        }
        return $hours;
    }

    /**
     * خيارات كتالوج mnt_lookup حسب النوع (مقيّد بالشركة، غير محذوف، نشط).
     * تُعيد مصفوفة [id => name].
     */
    function mnt_lookup_options($conn, $company_id, $type)
    {
        $company_id = intval($company_id);
        $out = array();
        $sql = "SELECT id, name FROM mnt_lookup
                 WHERE company_id = ? AND type = ? AND COALESCE(is_deleted,0)=0 AND COALESCE(is_active,1)=1
                 ORDER BY name ASC";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'is', $company_id, $type);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($r = mysqli_fetch_assoc($res))) {
                $out[intval($r['id'])] = $r['name'];
            }
            mysqli_stmt_close($stmt);
        }
        return $out;
    }

    /**
     * عدّاد البلاغات «الجديدة» لشركة المستخدم (للتوبار وقوائم الصيانة).
     */
    function mnt_new_breakdowns_count($conn, $company_id)
    {
        $company_id = intval($company_id);
        if ($company_id <= 0) {
            return 0;
        }
        $count = 0;
        $sql = "SELECT COUNT(*) AS c FROM mnt_breakdown
                 WHERE company_id = ? AND state = 'جديد' AND COALESCE(is_deleted,0)=0";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'i', $company_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && ($r = mysqli_fetch_assoc($res))) { $count = intval($r['c']); }
            mysqli_stmt_close($stmt);
        }
        return $count;
    }

    /**
     * هل المستخدم الحالي من دور الصيانة (مدير 13 أو مشرف 14)؟ يحدّد ظهور أزرار الصيانة.
     */
    function mnt_user_is_maintenance($conn)
    {
        if (!isset($_SESSION['user']['role'])) {
            return false;
        }
        $role = intval($_SESSION['user']['role']);
        // الأدوار المملوكة لشاشات الصيانة: المدير وأي دور فرعي تابع له.
        if ($role === -1) {
            return true; // السوبر أدمن يرى كل شيء
        }
        $is = false;
        $sql = "SELECT 1 FROM modules m
                  JOIN role_permissions rp ON rp.module_id = m.id
                 WHERE rp.role_id = ? AND m.code = 'Maintenance/orders.php' AND rp.can_view = 1 LIMIT 1";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'i', $role);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $is = ($res && mysqli_num_rows($res) > 0);
            mysqli_stmt_close($stmt);
        }
        return $is;
    }

    /**
     * المعدات «تحت الصيانة» المُسندة لمشروع معيّن عبر جدول التشغيل (operations).
     * الربط: operations.equipment = equipments.id ، operations.project_id = المشروع.
     * جدول التشغيل سجلّ تاريخي (history) — قد تتكرر المعدة في أكثر من سجل (سارٍ/منتهٍ)،
     * لذا نستخدم DISTINCT لعرض المعدة مرّة واحدة بصرف النظر عن حالة التشغيل (لا نقيّد بـ o.status).
     *
     * تعريف «تحت الصيانة» = حقل equipments.status = 1 (هو ما تعرضه شاشة الأسطول في عمود
     * «حالة المعدة»: 0 متاحة · 1 تحت الصيانة · 2 محجوزة · 3 معطلة · 5 مسحوبة). ونُبقي
     * availability_status='تحت الصيانة' كاحتياط لمسار move_oprators. مقيّد بالشركة.
     * include_id: يضمن ظهور معدة محدّدة دائماً (لفورم التحرير).
     * يُعيد مصفوفة صفوف [id, name, code].
     */
    function mnt_maint_equipment_in_project($conn, $company_id, $project_id, $include_id = 0)
    {
        $company_id = intval($company_id);
        $project_id = intval($project_id);
        $include_id = intval($include_id);
        $out = array();
        $seen = array();

        if ($project_id > 0) {
            // «تحت الصيانة» يُحدَّد عبر availability_status (الحالة الفعلية المعتمدة في كل النظام)،
            // وليس عبر العمود العام status (= علم «صف نشط» = 1 لكل معدة فعّالة). الاعتماد على status
            // كان يُظهر كل المعدات النشطة في القائمة ويمنع اختفاءها بعد إغلاق أمر الصيانة.
            $sql = "SELECT DISTINCT e.id, e.name, e.code
                      FROM equipments e
                      INNER JOIN operations o ON o.equipment = e.id AND o.project_id = ?
                     WHERE (e.company_id = ? OR e.company_id IS NULL)
                       AND e.availability_status IN ('تحت الصيانة', 'موقوفة للصيانة')
                     ORDER BY e.name";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, 'ii', $project_id, $company_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($res && ($r = mysqli_fetch_assoc($res))) { $out[] = $r; $seen[intval($r['id'])] = true; }
                mysqli_stmt_close($stmt);
            }
        }

        // ضمان ظهور المعدة المختارة حالياً في فورم التحرير (حتى لو لم تَعُد «تحت الصيانة»).
        if ($include_id > 0 && empty($seen[$include_id])) {
            if ($stmt = mysqli_prepare($conn, "SELECT id, name, code FROM equipments WHERE id = ? AND (company_id = ? OR company_id IS NULL) LIMIT 1")) {
                mysqli_stmt_bind_param($stmt, 'ii', $include_id, $company_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                if ($res && ($r = mysqli_fetch_assoc($res))) { array_unshift($out, $r); }
                mysqli_stmt_close($stmt);
            }
        }

        return $out;
    }
}
