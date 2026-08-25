<?php
/**
 * خدمة المقعد التعاقدي — ContractSeatService (N-11 · PLAN-04 §2.1)
 * ───────────────────────────────────────────────────────────────────────────
 * «الحاويةُ مقعدٌ تعاقديٌّ مرقَّم تجلس فيه معدةٌ فعليةٌ لفترةٍ وقد يتعاقب
 * عليها أكثرُ من معدة — والكميةُ نتيجةٌ لا تعريف».
 *
 * الأحكام المنفَّذة:
 *   ① تعريف المقعد على حاوية «معدة» قائمة (توسعة لا مستوى جديد) — seat_no
 *     فريد داخل العقد (UNIQUE بنيوي) وseat_kind **يُشتق من بند البيع** لا يُختار.
 *   ② تعاقب المعدات: لا تداخل فترتين لمعدتين في مقعد واحد → 409.
 *   ③ فجوة المقاعد: seats_contracted (أساسية+احتياطية) · seats_filled محسوبًا ·
 *     seat_gap — مؤشرٌ يُحسب ولا يُخزَّن.
 *   ④ «الفجوة لا تصير مطالبةً تلقائيًّا»: gap يحمل نوع كل مقعد فارغ —
 *     contractual_seat = بند مطالبة محتمل بنص العقد · operational_resource_slot
 *     = مؤشر تغطية داخلي (PLAN-04 §2.1).
 */

namespace App\Services;

class ContractSeatService
{
    /** اشتقاق نوع المقعد من نموذج تسعير بند البيع (PLAN-03 §4 — لا تصنيف مستقل) */
    public static function deriveSeatKind($pricingModel, $isSupplierAllocation = false)
    {
        if ($isSupplierAllocation) {
            return 'supplier_allocation';
        }
        // عقود الساعة والجاهزية: العميل اشترى المقعد نفسه — contractual_seat.
        // عقود الكمية (طن · متر · نقلة · متر مكعب): المقعد مورد داخلي للتنفيذ.
        return in_array((string) $pricingModel, array('hour', 'day', 'shift'), true)
            ? 'contractual_seat' : 'operational_resource_slot';
    }

    /**
     * تعريف مقعدٍ على حاوية معدةٍ قائمة.
     * @return array{ok:bool,code:int,reason:string}
     */
    public static function defineSeat($gate, $companyId, $containerId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $rows = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
            "SELECT c.* FROM op_containers c WHERE {TENANT_SCOPE} AND c.id = ?", array((int) $containerId));
        if (!$rows) { $out['code'] = 404; $out['reason'] = 'الحاوية غير موجودة في نطاقك'; return $out; }
        $c = $rows[0];
        if ((string) $c['level'] !== 'معدة') {
            $out['code'] = 422; $out['reason'] = 'المقعد توسعة لمستوى «معدة» حصرا — المستوى الحالي: ' . $c['level'];
            return $out;
        }
        $seatNo = (int) (isset($a['seat_no']) ? $a['seat_no'] : 0);
        if ($seatNo <= 0) { $out['code'] = 422; $out['reason'] = 'seat_no رقم موجب إلزامي'; return $out; }
        $kind = self::deriveSeatKind(isset($a['pricing_model']) ? $a['pricing_model'] : 'hour',
                                     !empty($a['is_supplier_allocation']));
        try {
            $gate->update('op_containers', array(
                'seat_no' => $seatNo,
                'seat_kind' => $kind,
                'seat_equipment_type_id' => isset($a['equipment_type_id']) ? (int) $a['equipment_type_id'] : null,
                'contract_hours_monthly' => isset($a['contract_hours_monthly']) ? (float) $a['contract_hours_monthly'] : null,
                'seat_unit_price' => isset($a['unit_price']) ? (float) $a['unit_price'] : null,
                'seat_currency' => isset($a['currency']) ? (string) $a['currency'] : null,
            ), array('id' => (int) $containerId));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'uq_seat_no') !== false || strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'seat_no ' . $seatNo . ' مأخوذ في هذا العقد — الفرادة بنيوية';
                return $out;
            }
            throw $t;
        }
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'مقعد #' . $seatNo . ' (' . $kind . ') عرف على الحاوية ' . $c['container_no'];
        return $out;
    }

    /**
     * إسناد معدةٍ لمقعدٍ لفترة — **لا تداخل فترتين لمعدتين في مقعدٍ واحد**.
     * @return array{ok:bool,code:int,reason:string,assignment_id:int}
     */
    public static function assignEquipment($gate, $companyId, $containerId, $equipmentId, $dateFrom, $dateTo, array $opt, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'assignment_id' => 0);
        $containerId = (int) $containerId; $equipmentId = (int) $equipmentId;
        $dateTo = ($dateTo !== null && $dateTo !== '') ? (string) $dateTo : null;

        $seat = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
            "SELECT c.id, c.seat_no FROM op_containers c WHERE {TENANT_SCOPE} AND c.id = ? AND c.seat_no IS NOT NULL",
            array($containerId));
        if (!$seat) { $out['code'] = 404; $out['reason'] = 'ليست حاوية مقعد معرف'; return $out; }

        // قيد عدم التداخل: فترةٌ مفتوحة (date_to NULL) تُعد ممتدةً بلا نهاية.
        $overlap = $gate->scopedQuery(array('scope' => array('a' => 'seat_assignments')),
            "SELECT a.id, a.equipment_id, a.date_from, a.date_to FROM seat_assignments a
              WHERE {TENANT_SCOPE} AND a.container_id = ? AND a.state = 'active'
                AND (a.date_to IS NULL OR a.date_to >= ?)
                AND (? IS NULL OR a.date_from <= ?)",
            array($containerId, (string) $dateFrom, $dateTo, $dateTo === null ? '9999-12-31' : $dateTo));
        if ($overlap) {
            $o = $overlap[0];
            $out['code'] = 409;
            $out['reason'] = 'تداخل فترتين في المقعد الواحد مرفوض — المعدة #' . $o['equipment_id']
                . ' جالسة من ' . $o['date_from'] . ' إلى ' . ($o['date_to'] !== null ? $o['date_to'] : 'مفتوح')
                . '؛ أنه إسنادها أولا بسبب استبدال مكتوب';
            return $out;
        }

        // سبب الاستبدال إلزاميٌّ لغير الإسناد الأول
        $prior = $gate->scopedQuery(array('scope' => array('a' => 'seat_assignments')),
            "SELECT COUNT(*) n FROM seat_assignments a WHERE {TENANT_SCOPE} AND a.container_id = ?",
            array($containerId));
        $isFirst = ((int) $prior[0]['n'] === 0);
        $reason = isset($opt['replace_reason']) ? trim((string) $opt['replace_reason']) : '';
        if (!$isFirst && $reason === '') {
            $out['code'] = 422; $out['reason'] = 'سبب الاستبدال إلزامي لكل تعاقب بعد الأول'; return $out;
        }

        $drivers = isset($opt['drivers']) && is_array($opt['drivers']) ? array_values(array_map('intval', $opt['drivers'])) : array();
        $id = (int) $gate->insert('seat_assignments', array(
            'container_id' => $containerId,
            'equipment_id' => $equipmentId,
            'date_from' => (string) $dateFrom,
            'date_to' => $dateTo,
            'replace_reason' => $reason !== '' ? $reason : null,
            'assignment_role' => isset($opt['assignment_role']) ? (string) $opt['assignment_role'] : 'أساسي',
            'drivers_count' => count($drivers),
            'drivers_json' => $drivers ? json_encode($drivers) : null,
            'state' => 'active',
            'created_by' => (int) $actor ?: null,
        ));
        $out['ok'] = true; $out['code'] = 201; $out['assignment_id'] = $id;
        $out['reason'] = 'أسندت المعدة #' . $equipmentId . ' للمقعد من ' . $dateFrom . ' إلى ' . ($dateTo !== null ? $dateTo : 'مفتوح');
        return $out;
    }

    /** إنهاء إسنادٍ قائم بتاريخٍ — تمهيدًا للتعاقب. */
    public static function endAssignment($gate, $companyId, $assignmentId, $endDate, $actor)
    {
        $gate->update('seat_assignments',
            array('date_to' => (string) $endDate, 'state' => 'ended'),
            array('id' => (int) $assignmentId));
        return array('ok' => true, 'code' => 200, 'reason' => 'أنهي الإسناد بتاريخ ' . $endDate);
    }

    /**
     * فجوة المقاعد لعقد — **محسوبةٌ لا مخزَّنة**، وكل مقعدٍ فارغٍ بنوعه
     * (المطالبة تُقرأ من العقد لا تُفترض — PLAN-04 §2.1).
     * @return array{seats_contracted:int,seats_filled:int,seat_gap:int,empty_seats:array}
     */
    public static function seatGap($gate, $companyId, $contractId, $onDate = null)
    {
        $onDate = ($onDate !== null) ? (string) $onDate : date('Y-m-d');
        $seats = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
            "SELECT c.id, c.seat_no, c.seat_kind, c.role_kind, c.container_no
               FROM op_containers c
              WHERE {TENANT_SCOPE} AND c.contract_id = ? AND c.seat_no IS NOT NULL
                AND COALESCE(c.is_deleted,0)=0 AND c.state <> 'مقفلة'
              ORDER BY c.seat_no", array((int) $contractId));
        $contracted = count($seats);
        $filled = 0; $empty = array();
        foreach ($seats as $s) {
            $occ = $gate->scopedQuery(array('scope' => array('a' => 'seat_assignments')),
                "SELECT a.id FROM seat_assignments a
                  WHERE {TENANT_SCOPE} AND a.container_id = ? AND a.state = 'active'
                    AND a.date_from <= ? AND (a.date_to IS NULL OR a.date_to >= ?) LIMIT 1",
                array((int) $s['id'], $onDate, $onDate));
            if ($occ) { $filled++; }
            else {
                $empty[] = array(
                    'seat_no' => (int) $s['seat_no'],
                    'seat_kind' => (string) $s['seat_kind'],
                    'implication' => ((string) $s['seat_kind'] === 'contractual_seat')
                        ? 'بند مطالبة أو غرامة محتمل — بنص العقد لا تلقائيا'
                        : 'مؤشر تغطية داخلي — لا حق للعميل',
                );
            }
        }
        return array('seats_contracted' => $contracted, 'seats_filled' => $filled,
                     'seat_gap' => $contracted - $filled, 'empty_seats' => $empty, 'on_date' => $onDate);
    }

    /** تعاقب المعدات على مقعد — التاريخ الكامل بلا فجوات خفية. */
    public static function successionOf($gate, $companyId, $containerId)
    {
        return $gate->scopedQuery(array('scope' => array('a' => 'seat_assignments')),
            "SELECT a.* FROM seat_assignments a WHERE {TENANT_SCOPE} AND a.container_id = ?
              ORDER BY a.date_from", array((int) $containerId));
    }
}
