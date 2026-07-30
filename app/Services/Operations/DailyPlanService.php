<?php
/**
 * app/Services/Operations/DailyPlanService.php — دورةُ التوزيع اليومية (H-03)
 * ═══════════════════════════════════════════════════════════════════════════
 * UX-03 §2.2 حرفيًّا: «احتياجُ الغد (معدة×وردية) ← توزيعُ المشغّلين بتحذير
 * تعارضٍ فوري ← اعتمادُ الحركة ← إشعارٌ ← فتحُ يوم الغد».
 *
 * القواعد الحاكمة:
 *  · الاحتياجُ من حاويات المعدات النشطة (OPM-01 §4) — لا من اليد.
 *  · «لا تخصيصَ خارج حاوية» (§4): المشغّلُ من سلسلة حاوية معدته حصرًا (422).
 *  · التعارضُ الفوري: مشغّلٌ على معدتين في (اليوم × الوردية) → 409 بمرجعه.
 *  · «لا اعتمادَ لمن أنشأ» (فصلُ الواجبات) → 403.
 *  · «لا يُفتح موقعٌ ناقصُ التخصيص»: فتحُ خطةٍ فيها سطرٌ بلا مشغّل → 422 بقائمته.
 */

namespace App\Services\Operations;

class DailyPlanService
{
    /**
     * احتياجُ الغد — خطةُ (المشروع × اليوم) وسطرٌ لكل (حاويةِ معدةٍ نشطةٍ × وردية).
     * الورديةُ من حاويات مشغّلي المعدة المميزة — والغائبةُ = 1. عطالةٌ: القائمُ لا يتكرر.
     * @return array{ok:bool,code:int,reason:string,plan_id:?int,created:int,existing:int}
     */
    public static function generateNeeds($conn, $gate, $companyId, $projectId, $date, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'plan_id' => null,
                     'created' => 0, 'existing' => 0);
        $projectId = (int) $projectId;
        $date = self::dateOrNull($date);
        if ($date === null) { $out['code'] = 422; $out['reason'] = 'تاريخُ الخطة إلزامي'; return $out; }

        $proj = null;
        try { $proj = $gate->selectOne('project', array('columns' => array('id'), 'where' => array('id' => $projectId))); }
        catch (\Throwable $t) { $proj = null; }
        if (!$proj) { $out['code'] = 404; $out['reason'] = 'المشروعُ غير موجودٍ في نطاقك'; return $out; }

        // حاوياتُ المعدات النشطةُ للمشروع — مصدرُ الاحتياج
        $eqContainers = array();
        try {
            $eqContainers = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
                "SELECT c.id, c.equipment_id FROM op_containers c
                 WHERE {TENANT_SCOPE} AND c.project_id = ? AND c.level = 'معدة'
                   AND c.state = 'نشطة' AND COALESCE(c.is_deleted,0)=0
                 ORDER BY c.id", array($projectId));
        } catch (\Throwable $t) { $eqContainers = array(); }
        if (!$eqContainers) {
            $out['code'] = 422;
            $out['reason'] = 'لا حاوياتِ معداتٍ نشطةً للمشروع — الاحتياجُ يُشتق منها لا من اليد (ابدأ بالحاويات)';
            return $out;
        }

        // الخطةُ (تُنشأ مسودةً إن غابت — UQ يمنع الثانية)
        $plan = self::planOf($gate, $projectId, $date);
        if (!$plan) {
            try {
                $pid = (int) $gate->insert('daily_plans', array(
                    'project_id' => $projectId, 'plan_date' => $date,
                    'state' => 'draft', 'created_by' => (int) $actor ?: null,
                ));
            } catch (\Throwable $t) { $pid = 0; }
            $plan = $pid > 0 ? self::planOf($gate, $projectId, $date) : null;
            if (!$plan) { $out['code'] = 422; $out['reason'] = 'تعذّر إنشاءُ الخطة'; return $out; }
        }
        if ((string) $plan['state'] !== 'draft') {
            $out['code'] = 423;
            $out['reason'] = 'الخطةُ ' . $plan['state'] . ' — التوليدُ على المسودة؛ أرجِعها أولًا إن لزم';
            $out['plan_id'] = (int) $plan['id'];
            return $out;
        }
        $planId = (int) $plan['id'];

        foreach ($eqContainers as $ec) {
            $ecId = (int) $ec['id'];
            // ورديّاتُ مشغّلي هذه المعدة المميزة — الغائبةُ وردية 1
            $shifts = array();
            try {
                $rows = $gate->scopedQuery(array('scope' => array('o' => 'op_containers')),
                    "SELECT DISTINCT COALESCE(o.shift_no, 1) AS s FROM op_containers o
                     WHERE {TENANT_SCOPE} AND o.parent_id = ? AND o.level = 'مشغّل'
                       AND o.state = 'نشطة' AND COALESCE(o.is_deleted,0)=0", array($ecId));
                foreach ($rows as $r) { $shifts[] = (int) $r['s']; }
            } catch (\Throwable $t) { $shifts = array(); }
            if (!$shifts) { $shifts = array(1); }

            foreach ($shifts as $shift) {
                $exists = null;
                try {
                    $exists = $gate->selectOne('daily_plan_lines', array(
                        'whereRaw' => 'plan_id = ? AND equipment_container_id = ? AND shift_no = ?',
                        'params' => array($planId, $ecId, $shift)));
                } catch (\Throwable $t) { $exists = null; }
                if ($exists) { $out['existing']++; continue; }
                try {
                    $gate->insert('daily_plan_lines', array(
                        'plan_id' => $planId,
                        'equipment_container_id' => $ecId,
                        'equipment_id' => !empty($ec['equipment_id']) ? (int) $ec['equipment_id'] : null,
                        'shift_no' => $shift,
                    ));
                    $out['created']++;
                } catch (\Throwable $t) { $out['existing']++; }
            }
        }

        $out['ok'] = true; $out['code'] = 200; $out['plan_id'] = $planId;
        return $out;
    }

    /**
     * التوزيع — «لا تخصيصَ خارج حاوية» والتعارضُ الفوري 409 بمرجعه.
     * @return array{ok:bool,code:int,reason:string}
     */
    public static function assign($conn, $gate, $companyId, $lineId, $operatorId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $lineId = (int) $lineId; $operatorId = (int) $operatorId;

        $line = null;
        try { $line = $gate->selectOne('daily_plan_lines', array('where' => array('id' => $lineId))); }
        catch (\Throwable $t) { $line = null; }
        if (!$line) { $out['code'] = 404; $out['reason'] = 'سطرُ الخطة غير موجود'; return $out; }
        $plan = null;
        try { $plan = $gate->selectOne('daily_plans', array('where' => array('id' => (int) $line['plan_id']))); }
        catch (\Throwable $t) { $plan = null; }
        if (!$plan) { $out['code'] = 404; $out['reason'] = 'خطةُ السطر غير موجودة'; return $out; }
        if ((string) $plan['state'] !== 'draft') {
            $out['code'] = 423;
            $out['reason'] = 'الخطةُ ' . $plan['state'] . ' — التوزيعُ على المسودة؛ أرجِعها بقرارٍ لتعديلها';
            return $out;
        }

        // «لا تخصيصَ خارج حاوية»: حاويةُ مشغّلٍ نشطةٌ له تحت حاوية معدة السطر
        $opContainer = null;
        if ($operatorId > 0) {
            try {
                $rows = $gate->scopedQuery(array('scope' => array('o' => 'op_containers')),
                    "SELECT o.id FROM op_containers o
                     WHERE {TENANT_SCOPE} AND o.parent_id = ? AND o.level = 'مشغّل'
                       AND o.operator_employee_id = ? AND o.state = 'نشطة'
                       AND COALESCE(o.is_deleted,0)=0
                     ORDER BY o.id LIMIT 1",
                    array((int) $line['equipment_container_id'], $operatorId));
                $opContainer = $rows ? (int) $rows[0]['id'] : null;
            } catch (\Throwable $t) { $opContainer = null; }
            if ($opContainer === null) {
                $out['code'] = 422;
                $out['reason'] = 'لا تخصيصَ خارج حاوية (OPM-01 §4) — المشغّلُ #' . $operatorId
                    . ' ليس في سلسلة حاوية هذه المعدة؛ وزّعه من شاشة الحاويات أولًا';
                return $out;
            }

            // التعارضُ الفوري: المشغّلُ نفسُه على معدةٍ أخرى في (اليوم × الوردية)
            $clash = null;
            try {
                $rows = $gate->scopedQuery(array(
                    'scope' => array('l' => 'daily_plan_lines'),
                    'enrich' => array('p' => 'daily_plans'),
                ), "SELECT l.id, l.equipment_id FROM daily_plan_lines l
                    LEFT JOIN daily_plans p ON p.id = l.plan_id
                    WHERE {TENANT_SCOPE} AND l.operator_employee_id = ?
                      AND l.shift_no = ? AND l.id <> ?
                      AND p.plan_date = ? AND p.state IN ('draft','approved','opened')
                      AND COALESCE(p.is_deleted,0)=0
                    ORDER BY l.id LIMIT 1",
                    array($operatorId, (int) $line['shift_no'], $lineId, (string) $plan['plan_date']));
                $clash = $rows ? $rows[0] : null;
            } catch (\Throwable $t) { $clash = null; }
            if ($clash) {
                $out['code'] = 409;
                $out['reason'] = 'تعارضٌ فوري: المشغّلُ موزَّعٌ في الوردية نفسِها على السطر #'
                    . (int) $clash['id'] . ' (معدة #' . (int) $clash['equipment_id'] . ') — أزل ذاك أولًا';
                return $out;
            }
        }

        $gate->update('daily_plan_lines', array(
            'operator_employee_id' => $operatorId > 0 ? $operatorId : null,
            'operator_container_id' => $opContainer,
        ), array('id' => $lineId));

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'operations', 'daily_plan_lines', 'assign', $lineId,
            array('operator_employee_id' => $line['operator_employee_id']),
            array('operator_employee_id' => $operatorId > 0 ? $operatorId : null),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** اعتمادُ الحركة — «لا اعتمادَ لمن أنشأ» 403. */
    public static function approve($conn, $gate, $companyId, $planId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $plan = self::planById($gate, $planId);
        if (!$plan) { $out['code'] = 404; $out['reason'] = 'الخطةُ غير موجودة'; return $out; }
        if ((string) $plan['state'] !== 'draft') {
            $out['code'] = 422; $out['reason'] = 'الخطةُ ' . $plan['state'] . ' — الاعتمادُ للمسودة'; return $out;
        }
        if ((int) $plan['created_by'] === (int) $actor && (int) $actor > 0) {
            $out['code'] = 403; $out['reason'] = 'لا اعتمادَ لمن أنشأ — فصلُ الواجبات بنيوي'; return $out;
        }
        $gate->update('daily_plans', array(
            'state' => 'approved', 'approved_by' => (int) $actor ?: null,
            'approved_at' => gmdate('Y-m-d H:i:s'),
        ), array('id' => (int) $planId));
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'operations', 'daily_plans', 'approve', (int) $planId,
            array('state' => 'draft'), array('state' => 'approved'),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * فتحُ يوم الغد — «لا يُفتح موقعٌ ناقصُ التخصيص»: سطرٌ بلا مشغّل → 422 بقائمته.
     * @return array{ok:bool,code:int,reason:string,missing:array}
     */
    public static function open($conn, $gate, $companyId, $planId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'missing' => array());
        $plan = self::planById($gate, $planId);
        if (!$plan) { $out['code'] = 404; $out['reason'] = 'الخطةُ غير موجودة'; return $out; }
        if ((string) $plan['state'] !== 'approved') {
            $out['code'] = 422;
            $out['reason'] = 'الفتحُ بعد اعتماد الحركة — الخطةُ ' . $plan['state'];
            return $out;
        }
        $missing = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('l' => 'daily_plan_lines')),
                "SELECT l.id, l.equipment_id, l.shift_no FROM daily_plan_lines l
                 WHERE {TENANT_SCOPE} AND l.plan_id = ? AND l.operator_employee_id IS NULL
                 ORDER BY l.id", array((int) $planId));
            foreach ($rows as $r) {
                $missing[] = 'السطر #' . (int) $r['id'] . ': معدة #' . (int) $r['equipment_id']
                           . ' وردية ' . (int) $r['shift_no'] . ' بلا مشغّل';
            }
        } catch (\Throwable $t) { /* قائمةٌ فارغة = لا نواقصَ مقروءة */ }
        if ($missing) {
            $out['code'] = 422;
            $out['reason'] = 'لا يُفتح موقعٌ ناقصُ التخصيص — ' . count($missing) . ' سطرًا بلا مشغّل';
            $out['missing'] = $missing;
            return $out;
        }
        $gate->update('daily_plans', array(
            'state' => 'opened', 'opened_at' => gmdate('Y-m-d H:i:s'),
        ), array('id' => (int) $planId));
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'operations', 'daily_plans', 'open', (int) $planId,
            array('state' => 'approved'), array('state' => 'opened'),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
        // الإشعار: حقيقةُ فتح الغد في الجذر المحايد (المواقعُ والمشغّلون يقرؤونها)
        try {
            require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';
            \App\Core\EventPublisher::publishFact($conn, array(
                'event_key' => 'daily_plan.opened', 'category' => 'operational',
                'source_module' => 'operations', 'company_id' => (int) $companyId,
                'entity_type' => 'daily_plan', 'entity_id' => (int) $planId,
                'occurred_at' => gmdate('Y-m-d H:i:s'), 'created_by' => (int) $actor ?: 1,
                'idempotency_key' => 'daily_plan_open:' . (int) $planId,
                'notes' => 'فُتح يومُ ' . $plan['plan_date'] . ' للمشروع #' . (int) $plan['project_id'],
                'payload' => array('plan_id' => (int) $planId,
                                   'project_id' => (int) $plan['project_id'],
                                   'plan_date' => (string) $plan['plan_date']),
            ));
        } catch (\Throwable $t) {
            error_log('DailyPlanService open publish #' . $planId . ': ' . $t->getMessage());
        }
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** إرجاعُ خطةٍ للمسودة بسببٍ إلزامي — تصحيحُ توزيعٍ قبل يومه. */
    public static function reopen($conn, $gate, $companyId, $planId, $reason, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $reason = trim((string) $reason);
        if ($reason === '') { $out['code'] = 422; $out['reason'] = 'سببُ الإرجاع إلزامي'; return $out; }
        $plan = self::planById($gate, $planId);
        if (!$plan) { $out['code'] = 404; $out['reason'] = 'الخطةُ غير موجودة'; return $out; }
        if (!in_array((string) $plan['state'], array('approved', 'opened'), true)) {
            $out['code'] = 422; $out['reason'] = 'الخطةُ ' . $plan['state'] . ' — لا إرجاعَ لها'; return $out;
        }
        $gate->update('daily_plans', array(
            'state' => 'draft', 'reopen_reason' => mb_substr($reason, 0, 255),
        ), array('id' => (int) $planId));
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'operations', 'daily_plans', 'reopen', (int) $planId,
            array('state' => $plan['state']), array('state' => 'draft', 'reason' => $reason),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * بوابةُ الاشتقاق: مشغّلُ (المعدة × اليوم × الوردية) من الخطة **المفتوحة** —
     * يقرؤها deriveFromEquipment (الفجوةُ المسمّاة في TimesheetEntryService).
     * @return int|null
     */
    public static function plannedOperatorFor($gate, $equipmentId, $date, $shiftNo = 1)
    {
        try {
            $rows = $gate->scopedQuery(array(
                'scope' => array('l' => 'daily_plan_lines'),
                'enrich' => array('p' => 'daily_plans'),
            ), "SELECT l.operator_employee_id FROM daily_plan_lines l
                LEFT JOIN daily_plans p ON p.id = l.plan_id
                WHERE {TENANT_SCOPE} AND l.equipment_id = ? AND l.shift_no = ?
                  AND p.plan_date = ? AND p.state = 'opened'
                  AND COALESCE(p.is_deleted,0)=0
                  AND l.operator_employee_id IS NOT NULL
                ORDER BY l.id LIMIT 1",
                array((int) $equipmentId, (int) $shiftNo, (string) $date));
            return $rows ? (int) $rows[0]['operator_employee_id'] : null;
        } catch (\Throwable $t) { return null; }
    }

    /** هل للمشروع خطةٌ مفتوحةٌ ليومٍ؟ — سببُ البوابة الرابع (no_open_plan). */
    public static function hasOpenPlan($gate, $projectId, $date)
    {
        try {
            $r = $gate->selectOne('daily_plans', array(
                'whereRaw' => "project_id = ? AND plan_date = ? AND state = 'opened' AND COALESCE(is_deleted,0)=0",
                'params' => array((int) $projectId, (string) $date)));
            return $r !== null;
        } catch (\Throwable $t) { return false; }
    }

    // ═══════════════════════════════════════════════════════════════════════

    private static function planOf($gate, $projectId, $date)
    {
        try {
            return $gate->selectOne('daily_plans', array(
                'whereRaw' => 'project_id = ? AND plan_date = ? AND COALESCE(is_deleted,0)=0',
                'params' => array((int) $projectId, (string) $date)));
        } catch (\Throwable $t) { return null; }
    }

    private static function planById($gate, $planId)
    {
        try { return $gate->selectOne('daily_plans', array('where' => array('id' => (int) $planId))); }
        catch (\Throwable $t) { return null; }
    }

    private static function dateOrNull($v)
    {
        $v = trim((string) $v);
        if ($v === '') { return null; }
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    }
}
