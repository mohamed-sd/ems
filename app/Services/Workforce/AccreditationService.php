<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — محرّك الاعتمادات والصلاحية (الموجة 1).
 *
 * يتتبّع رخص العامل وشهاداته واعتماداته بتواريخ انتهائها، ويصنّف صلاحيتها،
 * ويكشف الاعتمادات الحرجة المنتهية لمنع التخصيص (يُستدعى من تخصيص L4 في الموجة 2).
 *
 * نمط دوالٍ خفيفٌ بلا اعتماد autoloader. Prepared Statements في كل قراءة.
 * صفر لمسٍ للقائم — يعمل على جدول worker_qualification الجديد فقط.
 */

if (!function_exists('ems_qualification_validity')) {
    /**
     * يصنّف صلاحية اعتمادٍ واحدٍ من تاريخ انتهائه ومدّة التنبيه.
     * @return string 'دائم' | 'ساري' | 'قارب الانتهاء' | 'منتهٍ'
     */
    function ems_qualification_validity($expiry_date, $alert_lead_days = 30)
    {
        if (empty($expiry_date) || $expiry_date === '0000-00-00') {
            return 'دائم';
        }
        $today  = new DateTime('today');
        $expiry = DateTime::createFromFormat('Y-m-d', substr((string) $expiry_date, 0, 10));
        if (!$expiry) {
            return 'دائم';
        }
        if ($expiry < $today) {
            return 'منتهٍ';
        }
        $lead = max(0, (int) $alert_lead_days);
        $threshold = (clone $today)->modify("+{$lead} day");
        return ($expiry <= $threshold) ? 'قارب الانتهاء' : 'ساري';
    }
}

if (!function_exists('ems_worker_accreditations')) {
    /**
     * يعيد اعتمادات العامل مصنّفةً بصلاحيتها (للقراءة فقط).
     * @return array صفوف worker_qualification + مفتاح 'validity'.
     */
    function ems_worker_accreditations($conn, $worker_id)
    {
        $worker_id = (int) $worker_id;
        $out = [];
        if ($worker_id <= 0) {
            return $out;
        }
        $stmt = $conn->prepare(
            "SELECT id, record_type, title, issuer, equipment_type, issue_date, expiry_date,
                    accreditation_category, proficiency_level, is_critical, alert_lead_days, document, decision_ref
             FROM worker_qualification WHERE employee_id = ? ORDER BY expiry_date IS NULL, expiry_date ASC, id DESC"
        );
        if (!$stmt) {
            return $out;
        }
        $stmt->bind_param('i', $worker_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['validity'] = ems_qualification_validity($row['expiry_date'], $row['alert_lead_days']);
            $out[] = $row;
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('ems_worker_has_critical_expired')) {
    /**
     * هل لدى العامل اعتمادٌ حرجٌ منتهٍ؟ (يمنع التخصيص في الموجة 2 — محرّك الجاهزية).
     */
    function ems_worker_has_critical_expired($conn, $worker_id)
    {
        $worker_id = (int) $worker_id;
        if ($worker_id <= 0) {
            return false;
        }
        $stmt = $conn->prepare(
            "SELECT expiry_date, alert_lead_days FROM worker_qualification
             WHERE employee_id = ? AND is_critical = 1 AND expiry_date IS NOT NULL"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $worker_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (ems_qualification_validity($row['expiry_date'], $row['alert_lead_days']) === 'منتهٍ') {
                $stmt->close();
                return true;
            }
        }
        $stmt->close();
        return false;
    }
}

if (!function_exists('ems_worker_expiring_accreditations')) {
    /**
     * تقرير: الاعتمادات المنتهية/القاربة على الانتهاء على مستوى الشركة (للتنبيهات).
     * @param int|null $company_id قيد العزل (أو null لتجاوزه — للمشرف العام).
     */
    function ems_worker_expiring_accreditations($conn, $company_id = null)
    {
        $rows = [];
        $sql = "SELECT q.id, q.employee_id, q.record_type, q.title, q.expiry_date, q.alert_lead_days,
                       q.is_critical, e.worker_code AS code
                FROM worker_qualification q
                INNER JOIN employees e ON e.id = q.employee_id  /* unified: worker = employee */
                WHERE q.expiry_date IS NOT NULL";
        $params = [];
        $types = '';
        if ($company_id !== null) {
            $sql .= " AND q.company_id = ?";
            $types .= 'i';
            $params[] = (int) $company_id;
        }
        $sql .= " ORDER BY q.expiry_date ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $rows;
        }
        if ($types !== '') {
            $bind = [$types];
            for ($i = 0; $i < count($params); $i++) {
                $bind[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $v = ems_qualification_validity($row['expiry_date'], $row['alert_lead_days']);
            if ($v === 'منتهٍ' || $v === 'قارب الانتهاء') {
                $row['validity'] = $v;
                $rows[] = $row;
            }
        }
        $stmt->close();
        return $rows;
    }
}
