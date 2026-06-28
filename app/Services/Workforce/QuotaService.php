<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — محرّك الحصص (L4 ≤ L3).
 *
 * قرار 9: L3 = operations (الآلية↔المشروع)، L4 = إسناد العامل (worker_allocation
 * فوق equipment_drivers). يمنع المحرّك تجاوز عدد العاملين النشطين على عمليةٍ
 * واحدةٍ سقفَها — نقطة حقيقةٍ واحدةٍ تُستدعى من كل مسار إسناد.
 *
 * مرجع السقف: drivercontracts.daily_operators (عدد المشغّلين المتعاقد عليهم لكل
 * معدةٍ يومياً). يُستنتَج عقد المعدة من العملية بالهندسة العكسية: نفس المشروع
 * (operations.project_id) مع تفضيل تطابق نوع المعدة والعقد، ثم نقرأ daily_operators.
 * إن تعذّر الربط (لا عقد مطابق أو قيمة غير صالحة) نسقط للافتراض حسب الوردية.
 */

if (!function_exists('ems_quota_parse_int_leading')) {
    /** يستخرج أوّل عددٍ صحيحٍ من نصٍّ (daily_operators قد يكون «2» أو «2 مشغل»). */
    function ems_quota_parse_int_leading($value)
    {
        if ($value === null) return 0;
        if (preg_match('/\d+/', (string) $value, $m)) {
            return (int) $m[0];
        }
        return 0;
    }
}

if (!function_exists('ems_quota_daily_operators_for_operation')) {
    /**
     * daily_operators المرتبط بعملية، أو null إن تعذّر الربط.
     * الربط (مستنبطٌ من بنية operations/drivercontracts ومن نمط get_contract_stats.php
     * الذي يطابق العمليات بالمشروع/النوع): نفس المشروع وحالةٌ فعّالة، مع تفضيل
     * تطابق نوع المعدة (equip_type) ثم العقد (project_contract_id).
     * @return int|null
     */
    function ems_quota_daily_operators_for_operation($conn, $operation_id)
    {
        $operation_id = (int) $operation_id;
        if ($operation_id <= 0) return null;

        // 1) تفاصيل العملية (المشروع/العقد/نوع المعدة/الشركة)
        $stmt = $conn->prepare(
            "SELECT company_id, project_id, contract_id, equipment_type
             FROM operations WHERE id = ? LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('i', $operation_id);
        $stmt->execute();
        $op = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$op) return null;

        $project_id   = (int) $op['project_id'];   // operations.project_id نصّيٌّ يحمل معرّفاً
        $contract_id  = (int) $op['contract_id'];
        $equip_type   = (int) $op['equipment_type'];
        $company_id   = isset($op['company_id']) ? (int) $op['company_id'] : 0;
        if ($project_id <= 0) return null;

        // 2) أنسب عقدٍ مطابقٍ في drivercontracts (نفس المشروع، فعّال) مع تفضيل النوع والعقد
        $sql = "SELECT daily_operators
                FROM drivercontracts
                WHERE status = 1 AND project_id = ?";
        $params = [$project_id];
        $types  = 'i';
        if ($company_id > 0) {
            $sql .= " AND (company_id = ? OR company_id IS NULL)";
            $params[] = $company_id;
            $types   .= 'i';
        }
        $sql .= " ORDER BY
                    (CAST(equip_type AS UNSIGNED) = ?) DESC,
                    (project_contract_id = ?) DESC,
                    id DESC
                  LIMIT 1";
        $params[] = $equip_type;
        $params[] = $contract_id;
        $types   .= 'ii';

        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $bind = [$types];
        for ($i = 0; $i < count($params); $i++) { $bind[] = &$params[$i]; }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;

        $n = ems_quota_parse_int_leading($row['daily_operators']);
        return ($n >= 1) ? $n : null;
    }
}

if (!function_exists('ems_quota_ceiling_for_operation')) {
    /**
     * سقف عدد العاملين المسموح على عمليةٍ (معدة↔مشروع).
     * أولاً: daily_operators المرتبط بالعملية من drivercontracts (المرجع المعتمد).
     * احتياطياً: افتراضٌ حسب نوع الوردية (B = نهار+ليل = 2، وإلا 1) عند تعذّر الربط.
     */
    function ems_quota_ceiling_for_operation($conn, $operation_id)
    {
        $operation_id = (int) $operation_id;
        if ($operation_id <= 0) return 0;

        // المرجع المعتمد: daily_operators من عقد المعدة المرتبط بالعملية.
        $daily = ems_quota_daily_operators_for_operation($conn, $operation_id);
        if ($daily !== null) {
            return $daily;
        }

        // احتياطيٌّ آمن: لا عقد مطابقٌ ذو daily_operators صالح → افتراضٌ حسب الوردية.
        $shift = 'B';
        $stmt = $conn->prepare("SELECT shift_type FROM operations WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $operation_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && !empty($row['shift_type'])) $shift = $row['shift_type'];
        }
        return ($shift === 'B') ? 2 : 1; // احتياطيٌّ حتى يتوفّر daily_operators في عقد المعدة
    }
}

if (!function_exists('ems_quota_current_for_operation')) {
    /** عدد العاملين المخصَّصين فعلياً (نشط/معتمد) على العملية، مع استثناء صفٍّ عند التعديل. */
    function ems_quota_current_for_operation($conn, $operation_id, $exclude_allocation_id = 0)
    {
        $operation_id = (int) $operation_id;
        $exclude = (int) $exclude_allocation_id;
        if ($operation_id <= 0) return 0;
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM worker_allocation
             WHERE operation_id = ? AND state IN ('معتمد','نشط') AND id <> ?"
        );
        if (!$stmt) return 0;
        $stmt->bind_param('ii', $operation_id, $exclude);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['c'] : 0;
    }
}

if (!function_exists('ems_quota_check_allocation')) {
    /**
     * يفحص أنّ إضافة/تفعيل تخصيصٍ لا يتجاوز سقف العملية (L4 ≤ L3).
     * @return array ['allowed'=>bool,'ceiling'=>int,'current'=>int,'message'=>string]
     */
    function ems_quota_check_allocation($conn, $operation_id, $exclude_allocation_id = 0)
    {
        $ceiling = ems_quota_ceiling_for_operation($conn, $operation_id);
        $current = ems_quota_current_for_operation($conn, $operation_id, $exclude_allocation_id);
        $allowed = ($current < $ceiling);
        return [
            'allowed' => $allowed,
            'ceiling' => $ceiling,
            'current' => $current,
            'message' => $allowed
                ? "ضمن السقف ($current/$ceiling)"
                : "تجاوزٌ للسقف: المخصَّص $current والسقف $ceiling لهذه العملية",
        ];
    }
}
