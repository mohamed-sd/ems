<?php
/**
 * app/Services/Operations/OperationsBoardService.php — لوحاتُ التشغيل والجاهزية
 * ═══════════════════════════════════════════════════════════════════════════
 * M-27 غرفةُ العمليات (SPEC-03 بطاقة 1): «Views فوق سجل الوحدات والتوزيع —
 *      **لا كتابة**؛ قراءةٌ وقفز» بتبويباتها الأربعة.
 * M-28 مساحةُ التوزيع (بطاقة 5): «شبكةٌ معدة×وردية×مشغّل — **والفحصُ في
 *      الخادم لا في المتصفح وحده**» بتعارضاته الثلاثة (كان لحالةٍ واحدة).
 * M-29 سجلُّ الوحدات (بطاقة 6): «أعمدةٌ تحليلية … بوحداتها المنفصلة —
 *      قراءةٌ واشتقاق».
 * M-26 لوحةُ الجاهزية (UX-10 §5.2): «شبكةُ خلايا كلُّ معدةٍ بلونها الآن ·
 *      جاهزية٪ حية · كلُّ خليةٍ تنقر لبطاقتها — لا طريقَ مسدودًا».
 */

namespace App\Services\Operations;

class OperationsBoardService
{
    // ═════════════════════════════════════════════════════════════════════
    // M-27 — تبويباتُ الغرفة الأربعة (قراءةٌ وقفز)
    // ═════════════════════════════════════════════════════════════════════

    /** ① الورديات واليوم: مَن رفع ومَن تأخر — لكل مشروعٍ نشِطٍ حالُ رفعِه. */
    public static function sitesToday($conn, $companyId, $date)
    {
        $co = (int) $companyId;
        $d = $conn->real_escape_string((string) $date);
        $out = array();
        // المشاريعُ الحية = ما رفع خلال آخر 14 يومًا — ومن غاب اليومَ متأخر
        $r = $conn->query("SELECT u.project_id, p.name,
                                  SUM(CASE WHEN u.entry_date = '{$d}' THEN 1 ELSE 0 END) today_n,
                                  MAX(CASE WHEN u.entry_date = '{$d}' THEN u.created_at END) last_at
                             FROM unit_entries u
                             LEFT JOIN project p ON p.id = u.project_id
                            WHERE u.company_id={$co}
                              AND u.entry_date BETWEEN DATE_SUB('{$d}', INTERVAL 14 DAY) AND '{$d}'
                            GROUP BY u.project_id, p.name ORDER BY today_n ASC");
        while ($r && ($x = $r->fetch_assoc())) {
            $out[] = array('project_id' => (int) $x['project_id'],
                'project' => (string) ($x['name'] ?: ('#' . $x['project_id'])),
                'raised_today' => (int) $x['today_n'],
                'last_at' => $x['last_at'] !== null ? (string) $x['last_at'] : null,
                'late' => (int) $x['today_n'] === 0);
        }
        return $out;
    }

    /** ② تايم شيتات اليوم بحالاتها — قفزٌ للإدخال والمعاد. */
    public static function timesheetToday($conn, $companyId, $date)
    {
        $co = (int) $companyId;
        $d = $conn->real_escape_string((string) $date);
        $rows = array(); $counts = array();
        $r = $conn->query("SELECT u.id, u.entry_no, u.equipment_id, e.name equip_name, u.shift,
                                  u.unit_type, u.qty, u.state, u.project_id
                             FROM unit_entries u LEFT JOIN equipments e ON e.id = u.equipment_id
                            WHERE u.company_id={$co} AND u.entry_date = '{$d}'
                            ORDER BY u.id DESC LIMIT 200");
        while ($r && ($x = $r->fetch_assoc())) {
            $rows[] = $x;
            $counts[(string) $x['state']] = ((int) ($counts[$x['state']] ?? 0)) + 1;
        }
        return array('rows' => $rows, 'counts' => $counts);
    }

    /** ③ صندوقُ الاعتماد بعدّاده — ما ينتظر كلَّ مرحلة. */
    public static function approvalBox($conn, $companyId)
    {
        $co = (int) $companyId;
        $out = array();
        $r = $conn->query("SELECT state, COUNT(*) n FROM unit_entries
                            WHERE company_id={$co} AND state <> 'sales_approved'
                            GROUP BY state");
        while ($r && ($x = $r->fetch_assoc())) { $out[(string) $x['state']] = (int) $x['n']; }
        return $out;
    }

    /**
     * ④ الالتزام: المنفَّذُ على خطة كل عقدٍ هذا الشهرَ والفجوةُ **والوتيرةُ
     * اللازمة** (المتبقي ÷ الأيام الباقية) — والانحرافُ بعقده لا مجهولًا.
     */
    public static function commitmentTab($conn, $companyId, $month = '')
    {
        $co = (int) $companyId;
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $month) ? (string) $month : date('Y-m');
        $m = $conn->real_escape_string($month);
        $daysInMonth = (int) date('t', strtotime($month . '-01'));
        $dayNow = ($month === date('Y-m')) ? (int) date('j') : $daysInMonth;
        $daysLeft = max(1, $daysInMonth - $dayNow);
        $out = array();
        $r = $conn->query("SELECT p.contract_id, ROUND(SUM(p.qty_planned),2) planned,
                                  (SELECT ROUND(COALESCE(SUM(u.qty),0),2) FROM unit_entries u
                                    WHERE u.company_id={$co} AND u.contract_id = p.contract_id
                                      AND DATE_FORMAT(u.entry_date,'%Y-%m') = '{$m}') executed
                             FROM contract_monthly_plan p
                            WHERE p.company_id={$co} AND p.period_month = '{$m}'
                            GROUP BY p.contract_id");
        while ($r && ($x = $r->fetch_assoc())) {
            $planned = (float) $x['planned']; $executed = (float) $x['executed'];
            $gap = round($executed - $planned * ($dayNow / $daysInMonth), 2);
            $out[] = array('contract_id' => (int) $x['contract_id'],
                'planned' => $planned, 'executed' => $executed,
                'gap_to_date' => $gap,
                'required_pace' => round(max(0, $planned - $executed) / $daysLeft, 2),
                'owner' => 'التشغيل (1)');
        }
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // M-28 — مساحةُ التوزيع: الشبكةُ والتعارضاتُ الثلاثة في الخادم
    // ═════════════════════════════════════════════════════════════════════

    /** الشبكة: الصفُّ معدةٌ · العمودُ ورديةٌ · الخليةُ مشغّلُها اليوم. */
    public static function distributionGrid($conn, $companyId, $date)
    {
        $co = (int) $companyId;
        $d = $conn->real_escape_string((string) $date);
        $grid = array();
        $r = $conn->query("SELECT u.equipment_id, e.name equip_name, u.shift,
                                  u.operator_employee_id, emp.name op_name, u.state
                             FROM unit_entries u
                             LEFT JOIN equipments e ON e.id = u.equipment_id
                             LEFT JOIN employees emp ON emp.id = u.operator_employee_id
                            WHERE u.company_id={$co} AND u.entry_date = '{$d}'");
        while ($r && ($x = $r->fetch_assoc())) {
            $eq = (int) $x['equipment_id'];
            if (!isset($grid[$eq])) {
                $grid[$eq] = array('equipment' => (string) ($x['equip_name'] ?: ('#' . $eq)),
                                   'day' => null, 'night' => null);
            }
            $shift = in_array((string) $x['shift'], array('day', 'night'), true)
                   ? (string) $x['shift'] : 'day';
            $grid[$eq][$shift] = array(
                'operator_id' => $x['operator_employee_id'] !== null ? (int) $x['operator_employee_id'] : null,
                'operator' => (string) ($x['op_name'] ?: '—'), 'state' => (string) $x['state']);
        }
        return $grid;
    }

    /**
     * فحصُ التعارض **الخادميُّ الشامل** — الحالاتُ الثلاث (كان لواحدة):
     * ① مشغّلٌ في ورديتين/معدتين بالوقت نفسِه · ② معدةٌ عاملةٌ بلا مشغّل ·
     * ③ مشغّلٌ برخصةٍ منتهيةٍ (أو بلا سجل رخصة — يُعلَن).
     */
    public static function conflicts($conn, $companyId, $date)
    {
        $co = (int) $companyId;
        $d = $conn->real_escape_string((string) $date);
        $out = array();

        // ① المشغّلُ الواحد في معدتين بالوردية نفسها
        $r = $conn->query("SELECT u.operator_employee_id, emp.name, u.shift,
                                  COUNT(DISTINCT u.equipment_id) n,
                                  GROUP_CONCAT(DISTINCT u.equipment_id) eqs
                             FROM unit_entries u LEFT JOIN employees emp ON emp.id = u.operator_employee_id
                            WHERE u.company_id={$co} AND u.entry_date='{$d}'
                              AND u.operator_employee_id IS NOT NULL
                            GROUP BY u.operator_employee_id, u.shift
                           HAVING n > 1");
        while ($r && ($x = $r->fetch_assoc())) {
            $out[] = array('kind' => 'double_booking',
                'message' => 'المشغل ' . ($x['name'] ?: ('#' . $x['operator_employee_id']))
                    . ' في ' . $x['n'] . ' معدات (' . $x['eqs'] . ') بوردية ' . $x['shift'] . ' الواحدة');
        }

        // ② معدةٌ عاملةٌ بلا مشغّلٍ مسمًّى
        $r = $conn->query("SELECT u.equipment_id, e.name FROM unit_entries u
                             LEFT JOIN equipments e ON e.id = u.equipment_id
                            WHERE u.company_id={$co} AND u.entry_date='{$d}'
                              AND u.operator_employee_id IS NULL
                            GROUP BY u.equipment_id, e.name");
        while ($r && ($x = $r->fetch_assoc())) {
            $out[] = array('kind' => 'no_operator',
                'message' => 'المعدة ' . ($x['name'] ?: ('#' . $x['equipment_id']))
                    . ' عاملة اليوم **بلا مشغل مسمى**');
        }

        // ③ مشغّلٌ برخصةٍ منتهية — أو بلا سجل رخصةٍ (يُعلَن ولا يُتجاهل)
        $r = $conn->query("SELECT DISTINCT u.operator_employee_id, emp.name,
                                  eo.license_expiry_date
                             FROM unit_entries u
                             LEFT JOIN employees emp ON emp.id = u.operator_employee_id
                             LEFT JOIN equipment_operators eo ON eo.employee_id = u.operator_employee_id
                            WHERE u.company_id={$co} AND u.entry_date='{$d}'
                              AND u.operator_employee_id IS NOT NULL
                              AND (eo.id IS NULL OR eo.license_expiry_date < '{$d}')");
        while ($r && ($x = $r->fetch_assoc())) {
            $out[] = array('kind' => 'license',
                'message' => 'المشغل ' . ($x['name'] ?: ('#' . $x['operator_employee_id']))
                    . ($x['license_expiry_date'] === null
                        ? ' **بلا سجل رخصة** — يعلن ولا يتجاهل'
                        : ' برخصة **منتهية منذ ' . $x['license_expiry_date'] . '**'));
        }
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // M-29 — سجلُّ الوحدات التحليلي (قراءةٌ واشتقاق)
    // ═════════════════════════════════════════════════════════════════════

    public static function dailyUnitsReport($conn, $companyId, $from, $to, $projectId = 0)
    {
        $co = (int) $companyId;
        $f = $conn->real_escape_string((string) $from);
        $t = $conn->real_escape_string((string) $to);
        $pw = (int) $projectId > 0 ? (' AND u.project_id = ' . (int) $projectId) : '';
        $rows = array();
        $totals = array(); // بوحداتها المنفصلة — «لا تُجمع وحدتان في رقم»
        $r = $conn->query("SELECT u.entry_date, u.project_id, p.name project,
                                  u.equipment_id, e.name equipment,
                                  u.supplier_entity_id, s.name supplier,
                                  u.operator_employee_id, emp.name operator,
                                  u.unit_type, ROUND(SUM(u.qty),2) qty, u.state
                             FROM unit_entries u
                             LEFT JOIN project p ON p.id = u.project_id
                             LEFT JOIN equipments e ON e.id = u.equipment_id
                             LEFT JOIN suppliers s ON s.id = u.supplier_entity_id
                             LEFT JOIN employees emp ON emp.id = u.operator_employee_id
                            WHERE u.company_id={$co} AND u.entry_date BETWEEN '{$f}' AND '{$t}'{$pw}
                            GROUP BY u.entry_date, u.project_id, p.name, u.equipment_id, e.name,
                                     u.supplier_entity_id, s.name, u.operator_employee_id, emp.name,
                                     u.unit_type, u.state
                            ORDER BY u.entry_date DESC LIMIT 500");
        while ($r && ($x = $r->fetch_assoc())) {
            $rows[] = $x;
            $ut = (string) $x['unit_type'];
            $totals[$ut] = round(((float) ($totals[$ut] ?? 0)) + (float) $x['qty'], 2);
        }
        return array('rows' => $rows, 'totals_by_unit' => $totals);
    }

    // ═════════════════════════════════════════════════════════════════════
    // M-26 — لوحةُ الجاهزية: الشبكةُ الحية
    // ═════════════════════════════════════════════════════════════════════

    /**
     * خليةٌ لكل معدةٍ بلونها الآن — الحالُ من آخر واقعةِ اليومِ (يعمل/متوقف
     * بمسؤوله) وإلا فمن `availability_state` — **Views محسوبةٌ لا حقولًا**.
     */
    public static function readinessGrid($conn, $companyId, $projectId = 0, $type = '')
    {
        $co = (int) $companyId;
        $cells = array(); $working = 0; $total = 0;
        $pw = (int) $projectId > 0 ? (' AND ue.project_id = ' . (int) $projectId) : '';
        $tw = $type !== '' ? (" AND e.type = '" . $conn->real_escape_string($type) . "'") : '';
        $r = $conn->query("SELECT e.id, e.name, e.code, e.type, e.availability_state, e.status,
                                  (SELECT ue.state FROM unit_entries ue
                                    WHERE ue.company_id={$co} AND ue.equipment_id = e.id
                                      AND ue.entry_date = CURDATE(){$pw}
                                    ORDER BY ue.id DESC LIMIT 1) today_state,
                                  (SELECT COUNT(*) FROM tickets t
                                    WHERE t.company_id={$co} AND t.equipment_id = e.id
                                      AND t.close_date IS NULL) open_tickets,
                                  /* ⇐ INJ-0074 · «مرجعُ آخر شهادةِ جاهزيةٍ وتاريخُها
                                       ومُصدرُها — والنقرُ عليه يفتح الأمرَ الذي أصدرها».
                                       والمصدرُ أمرُ الصيانةِ المقفَل: المرجعُ في
                                       `readiness_cert_ref` والتاريخُ في `closed_at`
                                       والمُصدرُ في `closed_by`. */
                                  (SELECT m.id FROM mnt_order m
                                    WHERE m.company_id={$co} AND m.equipment_id = e.id
                                      AND m.state='Closed' AND COALESCE(m.is_deleted,0)=0
                                    ORDER BY m.closed_at DESC, m.id DESC LIMIT 1) cert_order_id,
                                  (SELECT m.readiness_cert_ref FROM mnt_order m
                                    WHERE m.company_id={$co} AND m.equipment_id = e.id
                                      AND m.state='Closed' AND COALESCE(m.is_deleted,0)=0
                                    ORDER BY m.closed_at DESC, m.id DESC LIMIT 1) cert_ref,
                                  (SELECT m.closed_at FROM mnt_order m
                                    WHERE m.company_id={$co} AND m.equipment_id = e.id
                                      AND m.state='Closed' AND COALESCE(m.is_deleted,0)=0
                                    ORDER BY m.closed_at DESC, m.id DESC LIMIT 1) cert_at,
                                  (SELECT u.name FROM mnt_order m
                                     LEFT JOIN users u ON u.id = m.closed_by
                                    WHERE m.company_id={$co} AND m.equipment_id = e.id
                                      AND m.state='Closed' AND COALESCE(m.is_deleted,0)=0
                                    ORDER BY m.closed_at DESC, m.id DESC LIMIT 1) cert_by
                             FROM equipments e
                            WHERE e.company_id={$co}{$tw}
                            ORDER BY e.name LIMIT 300");
        while ($r && ($x = $r->fetch_assoc())) {
            $total++;
            $status = 'idle'; // استعداد
            if ((int) $x['open_tickets'] > 0) { $status = 'maintenance'; }
            elseif ($x['today_state'] !== null) { $status = 'working'; $working++; }
            elseif (in_array((string) $x['availability_state'], array('stopped', 'down', 'out_of_service'), true)) {
                $status = 'stopped';
            }
            $cells[] = array('id' => (int) $x['id'],
                'name' => (string) ($x['name'] ?: $x['code']),
                'type' => (string) $x['type'], 'status' => $status,
                'open_tickets' => (int) $x['open_tickets'],
                /* INJ-0074: شهادةُ الجاهزيةِ مرجعًا وتاريخًا ومُصدرًا — ورابطُ أمرِها */
                'cert_ref'  => (string) ($x['cert_ref'] ?? ''),
                'cert_at'   => (string) ($x['cert_at'] ?? ''),
                'cert_by'   => (string) ($x['cert_by'] ?? ''),
                'cert_link' => ((int) ($x['cert_order_id'] ?? 0) > 0)
                    ? ('Maintenance/orders.php?id=' . (int) $x['cert_order_id']) : '',
                'link' => 'Equipments/equipment_profile.php?id=' . (int) $x['id']);
        }
        return array('cells' => $cells, 'total' => $total,
            'readiness_pct' => $total > 0 ? round(100.0 * ($total - self::countBy($cells, 'maintenance')
                               - self::countBy($cells, 'stopped')) / $total, 1) : null);
    }

    private static function countBy(array $cells, $status)
    {
        $n = 0;
        foreach ($cells as $c) { if ($c['status'] === $status) { $n++; } }
        return $n;
    }
}
