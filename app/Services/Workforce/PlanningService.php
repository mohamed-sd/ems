<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — محرّك التخطيط (Planning).
 *
 * يقابل workforce_requirement.required_qty بالمتوفّر المحسوب من التخصيصات النشطة،
 * ويعيد العجز/الفائض/الوظائف الحرجة لكل مشروعٍ وفئة. نقطة حقيقةٍ واحدةٍ تُستدعى من
 * شاشة الاحتياج (8.10) لحساب available_qty آلياً.
 *
 * «المتوفّر» = عدد العاملين المخصَّصين فعلياً (worker_allocation.state='نشط') المطابقين
 * للمشروع (عبر operations.project_id) والفئة (employees.worker_category).
 * نمط دوالٍ خفيفٌ. Prepared Statements. صفر لمسٍ للقائم.
 */

if (!function_exists('ems_planning_available')) {
    /**
     * عدد العاملين المتوفّرين (تخصيصٌ نشطٌ) لمشروعٍ وفئةٍ معيّنة.
     * يربط التخصيص بالمشروع عبر operations.project_id (نصّيٌّ → CAST)، والفئة عبر
     * employees.worker_category. يُحسَب العاملون المتمايزون (DISTINCT) لا التخصيصات.
     *
     * @param int      $project_id      project.id
     * @param string   $worker_category قيمة employees.worker_category
     * @param int|null $company_id      قيد العزل (null = تجاوز — للمشرف العام)
     * @return int
     */
    function ems_planning_available($conn, $project_id, $worker_category, $company_id = null)
    {
        $project_id = (int) $project_id;
        $worker_category = (string) $worker_category;
        if ($project_id <= 0 || $worker_category === '') {
            return 0;
        }
        $sql = "SELECT COUNT(DISTINCT wa.employee_id) AS c
                FROM worker_allocation wa
                INNER JOIN employees wp ON wp.id = wa.employee_id  /* unified: worker = employee */
                INNER JOIN operations o ON o.id = wa.operation_id
                WHERE wa.state = 'نشط'
                  AND CAST(o.project_id AS UNSIGNED) = ?
                  AND wp.worker_category = ?";
        $params = [$project_id, $worker_category];
        $types  = 'is';
        if ($company_id !== null) {
            $sql .= " AND wa.company_id = ?";
            $params[] = (int) $company_id;
            $types   .= 'i';
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $bind = [$types];
        for ($i = 0; $i < count($params); $i++) { $bind[] = &$params[$i]; }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['c'] : 0;
    }
}

if (!function_exists('ems_planning_derive')) {
    /**
     * يشتقّ العجز/الفائض/الحالة من المطلوب والمتوفّر (نفس منطق شاشة الاحتياج).
     * @return array ['shortage'=>int,'surplus'=>int,'state'=>string]
     */
    function ems_planning_derive($required, $available)
    {
        $required  = (int) $required;
        $available = (int) $available;
        $shortage  = max($required - $available, 0);
        $surplus   = max($available - $required, 0);
        $state     = ($shortage > 0) ? 'عجز' : (($surplus > 0) ? 'فائض' : 'متوازن');
        return ['shortage' => $shortage, 'surplus' => $surplus, 'state' => $state];
    }
}

if (!function_exists('ems_planning_for_project')) {
    /**
     * لكلّ بند احتياجٍ في مشروعٍ: المطلوب مقابل المتوفّر المحسوب + العجز/الفائض.
     * @return array صفوف [requirement_id, project_id, worker_category, required, available,
     *               shortage, surplus, state, is_critical]
     */
    function ems_planning_for_project($conn, $project_id, $company_id = null)
    {
        $project_id = (int) $project_id;
        $rows = [];
        if ($project_id <= 0) {
            return $rows;
        }
        $sql = "SELECT id, project_id, worker_category, required_qty, is_critical
                FROM workforce_requirement
                WHERE project_id = ?";
        $params = [$project_id];
        $types  = 'i';
        if ($company_id !== null) {
            $sql .= " AND company_id = ?";
            $params[] = (int) $company_id;
            $types   .= 'i';
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $rows;
        }
        $bind = [$types];
        for ($i = 0; $i < count($params); $i++) { $bind[] = &$params[$i]; }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $res = $stmt->get_result();
        $reqs = [];
        while ($r = $res->fetch_assoc()) { $reqs[] = $r; }
        $stmt->close();

        foreach ($reqs as $r) {
            $available = ems_planning_available($conn, $r['project_id'], $r['worker_category'], $company_id);
            $d = ems_planning_derive($r['required_qty'], $available);
            $rows[] = [
                'requirement_id'  => (int) $r['id'],
                'project_id'      => (int) $r['project_id'],
                'worker_category' => $r['worker_category'],
                'required'        => (int) $r['required_qty'],
                'available'       => $available,
                'shortage'        => $d['shortage'],
                'surplus'         => $d['surplus'],
                'state'           => $d['state'],
                'is_critical'     => (int) $r['is_critical'],
            ];
        }
        return $rows;
    }
}

if (!function_exists('ems_planning_gaps')) {
    /**
     * كل فجوات القوى (عجز/فائض) عبر المشاريع — للوحة التخطيط؛ يبرز الوظائف الحرجة.
     * @return array صفوف ممتدّةٌ بـ project_name، مرتّبةٌ: الحرج فالعجز الأكبر.
     */
    function ems_planning_gaps($conn, $company_id = null)
    {
        $rows = [];
        $sql = "SELECT wr.id, wr.project_id, wr.worker_category, wr.required_qty, wr.is_critical,
                       p.name AS project_name
                FROM workforce_requirement wr
                LEFT JOIN project p ON p.id = wr.project_id";
        $params = [];
        $types  = '';
        if ($company_id !== null) {
            $sql .= " WHERE wr.company_id = ?";
            $params[] = (int) $company_id;
            $types   .= 'i';
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $rows;
        }
        if ($types !== '') {
            $bind = [$types];
            for ($i = 0; $i < count($params); $i++) { $bind[] = &$params[$i]; }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $reqs = [];
        while ($r = $res->fetch_assoc()) { $reqs[] = $r; }
        $stmt->close();

        foreach ($reqs as $r) {
            $available = ems_planning_available($conn, $r['project_id'], $r['worker_category'], $company_id);
            $d = ems_planning_derive($r['required_qty'], $available);
            $rows[] = [
                'requirement_id'  => (int) $r['id'],
                'project_id'      => (int) $r['project_id'],
                'project_name'    => $r['project_name'],
                'worker_category' => $r['worker_category'],
                'required'        => (int) $r['required_qty'],
                'available'       => $available,
                'shortage'        => $d['shortage'],
                'surplus'         => $d['surplus'],
                'state'           => $d['state'],
                'is_critical'     => (int) $r['is_critical'],
            ];
        }
        // الحرج أولاً، ثم الأكبر عجزاً
        usort($rows, function ($a, $b) {
            if ($a['is_critical'] !== $b['is_critical']) {
                return $b['is_critical'] <=> $a['is_critical'];
            }
            return $b['shortage'] <=> $a['shortage'];
        });
        return $rows;
    }
}
