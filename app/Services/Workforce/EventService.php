<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — محرّك الأحداث (الحوافز/الجزاءات).
 *
 * يشتقّ الحافز/الجزاء من التقييمات المعتمدة (worker_evaluation.state معتمد/مرحّل)
 * ويجمعها للعامل/الفترة؛ يغذّي السجل التشغيلي المجمّع (8.9). نقطة حقيقةٍ واحدة.
 *
 * المالية يدويةٌ بالكامل (قرار 5): لا قيدٌ محاسبيٌّ فعلي هنا — تجميعٌ للعرض فقط.
 * نمط دوالٍ خفيفٌ. Prepared Statements. صفر لمسٍ للقائم.
 */

if (!function_exists('ems_event_approved_states')) {
    /**
     * الحالات التي تُعدّ «معتمدة» للأثر المالي. «معتمد» = أُقرّ، «مرحّل» = رُحِّل للمالية
     * (مرحلةٌ لاحقةٌ لاعتماده) — كلاهما يدخل التجميع. «مسودة» تُستثنى.
     */
    function ems_event_approved_states()
    {
        return ['معتمد', 'مرحّل'];
    }
}

if (!function_exists('ems_events_for_worker')) {
    /**
     * تجميع حوافز/جزاءات عاملٍ من التقييمات المعتمدة، ضمن فترةٍ اختيارية.
     * @param string|null $from فترة البداية (worker_evaluation.period >= from)
     * @param string|null $to   فترة النهاية (worker_evaluation.period <= to)
     * @return array ['incentive'=>float,'penalty'=>float,'net'=>float,'count'=>int]
     */
    function ems_events_for_worker($conn, $worker_id, $from = null, $to = null)
    {
        $worker_id = (int) $worker_id;
        $result = ['incentive' => 0.0, 'penalty' => 0.0, 'net' => 0.0, 'count' => 0];
        if ($worker_id <= 0) {
            return $result;
        }
        $sql = "SELECT
                  COALESCE(SUM(CASE WHEN incentive_penalty_type = 'حافز' THEN amount ELSE 0 END),0) AS incentive,
                  COALESCE(SUM(CASE WHEN incentive_penalty_type = 'جزاء' THEN amount ELSE 0 END),0) AS penalty,
                  COUNT(*) AS cnt
                FROM worker_evaluation
                WHERE employee_id = ?
                  AND state IN ('معتمد','مرحّل')
                  AND incentive_penalty_type <> 'بلا'";
        $params = [$worker_id];
        $types  = 'i';
        if (!empty($from)) { $sql .= " AND period >= ?"; $params[] = $from; $types .= 's'; }
        if (!empty($to))   { $sql .= " AND period <= ?"; $params[] = $to;   $types .= 's'; }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $result;
        }
        $bind = [$types];
        for ($i = 0; $i < count($params); $i++) { $bind[] = &$params[$i]; }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $result['incentive'] = (float) $row['incentive'];
            $result['penalty']   = (float) $row['penalty'];
            $result['net']       = $result['incentive'] - $result['penalty'];
            $result['count']     = (int) $row['cnt'];
        }
        return $result;
    }
}

if (!function_exists('ems_events_map')) {
    /**
     * خريطةٌ بجملةٍ واحدةٍ: [worker_id => ['incentive'=>,'penalty'=>,'net'=>,'count'=>]]
     * للتقييمات المعتمدة فقط — تُستعمل في السجل التشغيلي دون استعلامٍ لكل صف.
     *
     * @param int|null $company_id قيد العزل (null = تجاوز).
     */
    function ems_events_map($conn, $company_id = null)
    {
        $map = [];
        $sql = "SELECT employee_id,
                  COALESCE(SUM(CASE WHEN incentive_penalty_type = 'حافز' THEN amount ELSE 0 END),0) AS incentive,
                  COALESCE(SUM(CASE WHEN incentive_penalty_type = 'جزاء' THEN amount ELSE 0 END),0) AS penalty,
                  COUNT(*) AS cnt
                FROM worker_evaluation
                WHERE state IN ('معتمد','مرحّل') AND incentive_penalty_type <> 'بلا'";
        $params = [];
        $types  = '';
        if ($company_id !== null) {
            $sql .= " AND company_id = ?";
            $params[] = (int) $company_id;
            $types   .= 'i';
        }
        $sql .= " GROUP BY employee_id";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $map;
        }
        if ($types !== '') {
            $bind = [$types];
            for ($i = 0; $i < count($params); $i++) { $bind[] = &$params[$i]; }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $inc = (float) $row['incentive'];
            $pen = (float) $row['penalty'];
            $map[(int) $row['employee_id']] = [
                'incentive' => $inc,
                'penalty'   => $pen,
                'net'       => $inc - $pen,
                'count'     => (int) $row['cnt'],
            ];
        }
        $stmt->close();
        return $map;
    }
}

if (!function_exists('ems_events_summary')) {
    /**
     * إجماليّات الشركة (للوحة المؤشّرات): حوافز/جزاءات/صافٍ من المعتمد فقط.
     * @return array ['incentive'=>,'penalty'=>,'net'=>,'count'=>]
     */
    function ems_events_summary($conn, $company_id = null, $from = null, $to = null)
    {
        $sum = ['incentive' => 0.0, 'penalty' => 0.0, 'net' => 0.0, 'count' => 0];
        $sql = "SELECT
                  COALESCE(SUM(CASE WHEN incentive_penalty_type = 'حافز' THEN amount ELSE 0 END),0) AS incentive,
                  COALESCE(SUM(CASE WHEN incentive_penalty_type = 'جزاء' THEN amount ELSE 0 END),0) AS penalty,
                  COUNT(*) AS cnt
                FROM worker_evaluation
                WHERE state IN ('معتمد','مرحّل') AND incentive_penalty_type <> 'بلا'";
        $params = [];
        $types  = '';
        if ($company_id !== null) { $sql .= " AND company_id = ?"; $params[] = (int) $company_id; $types .= 'i'; }
        if (!empty($from))        { $sql .= " AND period >= ?";    $params[] = $from;             $types .= 's'; }
        if (!empty($to))          { $sql .= " AND period <= ?";    $params[] = $to;               $types .= 's'; }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $sum;
        }
        if ($types !== '') {
            $bind = [$types];
            for ($i = 0; $i < count($params); $i++) { $bind[] = &$params[$i]; }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $sum['incentive'] = (float) $row['incentive'];
            $sum['penalty']   = (float) $row['penalty'];
            $sum['net']       = $sum['incentive'] - $sum['penalty'];
            $sum['count']     = (int) $row['cnt'];
        }
        return $sum;
    }
}
