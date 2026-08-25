<?php
/**
 * app/Services/Capacity/SeatAssignmentService.php — تخصيصُ المعدة للمقعد (CAP-19)
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §2-④: «الوحدةُ التعاقديةُ ثابتةٌ وتتعاقب عليها معداتٌ مختلفة —
 * فالاستبدالُ يُنهي تخصيصًا ويفتح آخرَ **ولا يُنشئ حصةً جديدةً ولا يغيّر
 * قيمةَ العقد**» (C3).
 *
 * الأحكام:
 *   ① التخصيصُ يفحص الجاهزية: معدةٌ بوثيقةِ أهليةٍ منتهيةٍ → **403** —
 *     حارسُ الوثائق نافذٌ (وثائقُ الأهلية الأربع — قرارُ المالك في E-08).
 *   ② تداخلُ فترتين فعّالتين → 409 (القيدُ البنيويُّ uq_sa_active_open للمفتوح ·
 *     وفحصُ المدى هنا للمدَّد — J-09).
 *   ③ الاستبدالُ replace(): يقفل القائمَ بتاريخه ويفتح الجديدَ بسببٍ إلزامي —
 *     وصفرُ لمسٍ لأي حصةٍ أو سعرٍ أو سقف · ويُحسب زمنُ عدم التغطية ومدى
 *     الالتزام بمهلة الإحلال (C3).
 *   ④ خطةُ §8.3: التعديلُ فترةٌ جديدةٌ بسريان — لا UPDATE على كمياتِ فترةٍ
 *     قائمةٍ بدأت (C20) — الماضي كما هو.
 */

namespace App\Services\Capacity;

class SeatAssignmentService
{
    /** وثائقُ الأهلية الحاجبة — نصُّ ENUM حرفًا بحرف (نمطُ DocumentGuard). */
    const BLOCKING_DOCS = array('استمارة', 'تأمين', 'فحص دوري', 'رخصة تشغيل');

    /**
     * تخصيصُ معدةٍ لمقعدٍ لفترة — أو فترةُ خطةٍ جديدةٌ لمعدةٍ قائمة.
     * @return array{ok:bool,code:int,reason:string,assignment_id:?int}
     */
    public static function assign($gate, $companyId, $containerId, $equipmentId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'assignment_id' => null);
        $containerId = (int) $containerId; $equipmentId = (int) $equipmentId;

        $seats = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
            "SELECT c.id, c.level, c.container_no FROM op_containers c
              WHERE {TENANT_SCOPE} AND c.id = ? AND c.is_deleted = 0", array($containerId));
        if (!$seats) { $out['code'] = 404; $out['reason'] = 'المقعد غير موجود في نطاقك'; return $out; }

        $from = isset($a['date_from']) ? (string) $a['date_from'] : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $out['code'] = 422; $out['reason'] = 'تاريخ بداية التخصيص إلزامي'; return $out;
        }
        $to = isset($a['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $a['date_to'])
              ? (string) $a['date_to'] : null;

        // ① الجاهزية: وثيقةُ أهليةٍ منتهيةٌ يومَ البداية → 403 (C19-قبول)
        $docs = self::expiredBlockingDocs($gate, $equipmentId, $from);
        if (!empty($docs)) {
            $out['code'] = 403;
            $out['reason'] = 'معدة بوثيقة أهلية منتهية — لا تخصص: ' . implode(' · ', $docs);
            return $out;
        }

        // ② تداخلُ المدى (المفتوحُ يقفله القيدُ البنيوي uq_sa_active_open)
        $role = isset($a['assignment_role']) && in_array((string) $a['assignment_role'],
                array('أساسي', 'احتياطي', 'مؤقت'), true) ? (string) $a['assignment_role'] : 'أساسي';
        $activation = $role === 'احتياطي' ? 'pending' : 'active';
        if (isset($a['activation_state']) && in_array((string) $a['activation_state'], array('active', 'pending'), true)) {
            $activation = (string) $a['activation_state'];
        }
        if ($role !== 'احتياطي' || $activation === 'active') {
            $overlap = $gate->scopedQuery(array('scope' => array('s' => 'seat_assignments')),
                "SELECT s.id FROM seat_assignments s
                  WHERE {TENANT_SCOPE} AND s.container_id = ? AND s.state = 'active'
                    AND (s.assignment_role <> 'احتياطي' OR s.activation_state = 'active')
                    AND (s.date_to IS NULL OR s.date_to >= ?)
                    AND (? IS NULL OR s.date_from <= ?)",
                array($containerId, $from, $to, $to));
            if ($overlap) {
                $out['code'] = 409;
                $out['reason'] = 'تداخل فترتين فعالتين للمقعد — التخصيص القائم #' . $overlap[0]['id']
                               . ' (والاحتياطي غير المفعل وحده يجلس معه — C4)';
                return $out;
            }
        }

        // ④ C20: كمياتُ الخطة فترةٌ جديدةٌ دائمًا — لا تعديلَ على فترةٍ بدأت
        try {
            $id = (int) $gate->insert('seat_assignments', array(
                'container_id'      => $containerId,
                'equipment_id'      => $equipmentId,
                'date_from'         => $from,
                'date_to'           => $to,
                'assignment_role'   => $role,
                'activation_state'  => $activation,
                'planned_qty_month' => isset($a['planned_qty_month']) && $a['planned_qty_month'] !== ''
                                       ? round((float) $a['planned_qty_month'], 2) : null,
                'planned_qty_total' => isset($a['planned_qty_total']) && $a['planned_qty_total'] !== ''
                                       ? round((float) $a['planned_qty_total'], 2) : null,
                'measure_code'      => isset($a['measure_code']) && in_array((string) $a['measure_code'],
                                       array('hour', 'ton', 'trip', 'meter'), true) ? (string) $a['measure_code'] : null,
                'supplier_contract_line_id' => isset($a['supplier_contract_line_id']) && (int) $a['supplier_contract_line_id'] > 0
                                       ? (int) $a['supplier_contract_line_id'] : null,
                'replace_reason'    => isset($a['replace_reason']) ? mb_substr((string) $a['replace_reason'], 0, 200) : null,
                'created_by'        => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'uq_sa_active_open') !== false) {
                $out['code'] = 409;
                $out['reason'] = 'تخصيص مفتوح فعال قائم للمقعد — القيد بنيوي (C4)';
                return $out;
            }
            if (strpos($t->getMessage(), 'ck_sa_standby_zero') !== false) {
                $out['code'] = 422;
                $out['reason'] = 'الاحتياطي صفر كميات قبل التفعيل — بنيويا (§8.3)';
                return $out;
            }
            throw $t;
        }
        $out['ok'] = true; $out['code'] = 201; $out['assignment_id'] = $id;
        $out['reason'] = 'خصصت المعدة #' . $equipmentId . ' للمقعد من ' . $from
                       . ($role === 'احتياطي' && $activation === 'pending' ? ' — احتياطية غير مفعلة (صفر ساعات)' : '');
        return $out;
    }

    /**
     * الاستبدال (C3): يُنهي تخصيصًا ويفتح آخرَ — **ولا يُنشئ حصةً ولا يغيّر
     * قيمةَ عقد**؛ ويُحسب زمنُ عدم التغطية ومدى الالتزام بمهلة الإحلال.
     * @return array{ok:bool,code:int,reason:string,assignment_id:?int,uncovered_days:?int,sla_met:?bool}
     */
    public static function replace($gate, $companyId, $assignmentId, $newEquipmentId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'assignment_id' => null,
                     'uncovered_days' => null, 'sla_met' => null);
        $rows = $gate->scopedQuery(array('scope' => array('s' => 'seat_assignments')),
            "SELECT s.* FROM seat_assignments s WHERE {TENANT_SCOPE} AND s.id = ?",
            array((int) $assignmentId));
        if (!$rows) { $out['code'] = 404; $out['reason'] = 'التخصيص غير موجود في نطاقك'; return $out; }
        $cur = $rows[0];
        if ((string) $cur['state'] !== 'active') {
            $out['code'] = 409; $out['reason'] = 'التخصيص منته سلفا'; return $out;
        }
        $reason = isset($a['replace_reason']) ? trim((string) $a['replace_reason']) : '';
        if ($reason === '') {
            $out['code'] = 422; $out['reason'] = 'سبب الاستبدال إلزامي — لا استبدال بلا سبب';
            return $out;
        }
        $endDate = isset($a['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $a['end_date'])
                   ? (string) $a['end_date'] : null;
        $newFrom = isset($a['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $a['date_from'])
                   ? (string) $a['date_from'] : null;
        if ($endDate === null || $newFrom === null) {
            $out['code'] = 422; $out['reason'] = 'تاريخا الإقفال والفتح إلزاميان — فيقاس زمن عدم التغطية';
            return $out;
        }

        // إقفالُ القائم ثم فتحُ الجديد — وصفرُ لمسٍ لأي حصةٍ أو سعرٍ (C3)
        $gate->update('seat_assignments',
            array('state' => 'ended', 'date_to' => $endDate),
            array('id' => (int) $assignmentId));
        $r = self::assign($gate, $companyId, (int) $cur['container_id'], (int) $newEquipmentId, array(
            'date_from'         => $newFrom,
            'assignment_role'   => (string) $cur['assignment_role'],
            'activation_state'  => (string) $cur['activation_state'],
            'planned_qty_month' => $cur['planned_qty_month'],
            'planned_qty_total' => $cur['planned_qty_total'],
            'measure_code'      => $cur['measure_code'],
            'supplier_contract_line_id' => $cur['supplier_contract_line_id'],
            'replace_reason'    => $reason,
        ), $actor);
        if (!$r['ok']) {
            // فشلُ الفتح يعيد القائمَ كما كان — لا مقعدَ يُترك مقفولًا خطأً
            $gate->update('seat_assignments',
                array('state' => 'active', 'date_to' => $cur['date_to']),
                array('id' => (int) $assignmentId));
            return array_merge($out, array('code' => $r['code'], 'reason' => 'ألغي الاستبدال — ' . $r['reason']));
        }

        // زمنُ عدم التغطية ومدى الالتزام بمهلة الإحلال (C3)
        $uncovered = max(0, (int) ((strtotime($newFrom) - strtotime($endDate)) / 86400) - 1);
        $out['uncovered_days'] = $uncovered;
        if ($cur['supplier_contract_line_id'] !== null) {
            $sla = $gate->scopedQuery(array('scope' => array('l' => 'supplier_contract_lines')),
                "SELECT l.replacement_sla_hours FROM supplier_contract_lines l
                  WHERE {TENANT_SCOPE} AND l.id = ?", array((int) $cur['supplier_contract_line_id']));
            if ($sla && $sla[0]['replacement_sla_hours'] !== null) {
                $out['sla_met'] = ($uncovered * 24) <= (float) $sla[0]['replacement_sla_hours'];
            }
        }
        $out['ok'] = true; $out['code'] = 200; $out['assignment_id'] = $r['assignment_id'];
        $out['reason'] = 'استبدلت المعدة: خرجت #' . $cur['equipment_id'] . ' (' . $endDate . ') ودخلت #'
                       . (int) $newEquipmentId . ' (' . $newFrom . ') — زمن عدم التغطية ' . $uncovered
                       . ' يوما' . ($out['sla_met'] === null ? '' : ($out['sla_met'] ? ' ضمن مهلة الإحلال' : ' **متجاوزا مهلة الإحلال**'))
                       . ' · والحصة وقيمة العقد لم تتغيرا (C3)';
        return $out;
    }

    /** وثائقُ الأهلية المنتهيةُ يومَ البداية — القياسُ يومَ العمل (E-08 ③). */
    private static function expiredBlockingDocs($gate, $equipmentId, $onDate)
    {
        $rows = $gate->scopedQuery(array('scope' => array('d' => 'equipment_documents')),
            "SELECT d.doc_type, d.expiry_date FROM equipment_documents d
              WHERE {TENANT_SCOPE} AND d.subject_type = 'equipment' AND d.subject_id = ?
                AND d.expiry_date < ?
                AND d.doc_type IN ('استمارة','تأمين','فحص دوري','رخصة تشغيل')",
            array((int) $equipmentId, (string) $onDate));
        $names = array();
        foreach ($rows as $r) { $names[] = $r['doc_type'] . ' (انتهت ' . $r['expiry_date'] . ')'; }
        return $names;
    }
}
