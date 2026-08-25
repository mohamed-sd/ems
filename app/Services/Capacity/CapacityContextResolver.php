<?php
/**
 * app/Services/Capacity/CapacityContextResolver.php — مفاتيحُ الربط لقطةً (CAP-31/32/33)
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §12.1 · تكليفُ UX-03 (§15-⑥):
 *   ① جلبُ المفاتيح الثمانية آليًّا عند الإدخال — لا يُدخل المستخدمُ ما يعرفه النظام.
 *   ② عرضُها للتأكيد لا للإدخال (proposed → confirmed).
 *   ③ تثبيتُها لقطةً عند الاعتماد (locked) — **ولا تُحلّ ثانيةً أبدًا** (C29).
 *   ④ منعُ فتح التايم شيت إذا كانت التخصيصاتُ ناقصة — يُرفض بقائمة الناقص
 *     **وروابطِه** (درسُ E-08-أ: رسالةٌ بلا رابطٍ تُوقف الميدانَ بلا مخرج).
 */

namespace App\Services\Capacity;

class CapacityContextResolver
{
    /**
     * حلُّ المفاتيح الثمانية من واقع التخصيصات — عند الإدخال لا عند التسوية.
     * @param array $ctx contract_id · equipment_id · entry_date · unit_type
     * @return array{keys:array,missing:array,links:array,resolved:bool}
     */
    public static function resolve($gate, $companyId, array $ctx)
    {
        $contractId = isset($ctx['contract_id']) ? (int) $ctx['contract_id'] : 0;
        $equipmentId = isset($ctx['equipment_id']) ? (int) $ctx['equipment_id'] : 0;
        $date = isset($ctx['entry_date']) ? (string) $ctx['entry_date'] : '';
        $unitType = isset($ctx['unit_type']) ? (string) $ctx['unit_type'] : 'hour';

        $keys = array('cap_obligation_id' => null, 'cap_supplier_share_id' => null,
                      'cap_seat_id' => null, 'cap_assignment_id' => null,
                      'cap_supplier_line_id' => null, 'cap_role_snapshot' => null,
                      'cap_coverage_id' => null, 'cap_measure_code' => null);
        $missing = array(); $links = array();

        // ④ فترةُ الإسناد الفعّالة للمعدة يومَ الواقعة — ومنها المقعدُ والدور
        $asg = $gate->scopedQuery(array(
                'scope' => array('s' => 'seat_assignments', 'c' => 'op_containers')),
            "SELECT s.id AS asg_id, s.assignment_role, s.activation_state, s.supplier_contract_line_id,
                    c.id AS seat_id, c.parent_id, c.obl_id, c.contract_id AS seat_contract_id
               FROM seat_assignments s
               JOIN op_containers c ON c.id = s.container_id AND c.is_deleted = 0
              WHERE {TENANT_SCOPE} AND s.equipment_id = ? AND s.state = 'active'
                AND s.date_from <= ? AND (s.date_to IS NULL OR s.date_to >= ?)
                AND (? = 0 OR c.contract_id = ?)
              ORDER BY s.id DESC LIMIT 1",
            array($equipmentId, $date, $date, $contractId, $contractId));
        if (!$asg) {
            $missing[] = 'لا تخصيص فعالا للمعدة #' . $equipmentId . ' في مقعد تعاقدي يوم ' . $date;
            $links[] = 'Contracts/contract_containers.php?contract_id=' . $contractId . ' — خصص المعدة لمقعدها أولا';
        } else {
            $a = $asg[0];
            $keys['cap_assignment_id'] = (int) $a['asg_id'];
            $keys['cap_seat_id'] = (int) $a['seat_id'];
            // §12.1-⑥: الدورُ لحظةَ الواقعة — والاحتياطيُّ غيرُ المفعَّل لا يعمل أصلًا
            if ((string) $a['assignment_role'] === 'احتياطي') {
                if ((string) $a['activation_state'] !== 'active') {
                    $missing[] = 'المعدة احتياطية غير مفعلة — لا تسجل لها ساعات عقد قبل التفعيل (§4-④)';
                    $links[] = 'Operations/containers.php — فعل الاحتياطي بحدث له سبب ومعتمد';
                } else {
                    $keys['cap_role_snapshot'] = 'standby';
                }
            } else {
                $keys['cap_role_snapshot'] = 'primary';
            }
            if ($a['supplier_contract_line_id'] !== null) {
                $keys['cap_supplier_line_id'] = (int) $a['supplier_contract_line_id'];
            }
            // ② حصةُ المورد — أبو المقعد درجةُ «مورد»
            if ($a['parent_id'] !== null) {
                $p = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
                    "SELECT c.id, c.level, c.obl_id FROM op_containers c WHERE {TENANT_SCOPE} AND c.id = ?",
                    array((int) $a['parent_id']));
                if ($p && (string) $p[0]['level'] === 'مورد') {
                    $keys['cap_supplier_share_id'] = (int) $p[0]['id'];
                    if ($keys['cap_obligation_id'] === null && $p[0]['obl_id'] !== null) {
                        $keys['cap_obligation_id'] = (int) $p[0]['obl_id'];
                    }
                }
            }
            // ① الالتزام — من المقعد أو من بند المورد
            if ($a['obl_id'] !== null) { $keys['cap_obligation_id'] = (int) $a['obl_id']; }
        }
        if ($keys['cap_obligation_id'] === null && $keys['cap_supplier_line_id'] !== null) {
            $l = $gate->scopedQuery(array('scope' => array('l' => 'supplier_contract_lines')),
                "SELECT l.contract_obligation_ref FROM supplier_contract_lines l WHERE {TENANT_SCOPE} AND l.id = ?",
                array((int) $keys['cap_supplier_line_id']));
            if ($l && $l[0]['contract_obligation_ref'] !== null) {
                $keys['cap_obligation_id'] = (int) $l[0]['contract_obligation_ref'];
            }
        }
        if ($keys['cap_obligation_id'] === null) {
            $missing[] = 'لا التزام نوع معدة مربوطا بالمقعد أو ببند المورد (§12.1-①)';
            $links[] = 'Clients/contract_commitments.php — عرف التزام النوع واربطه بالشجرة';
        }

        // ⑦ تغطيةٌ بديلةٌ فعّالةٌ على المقعد يومَ الواقعة
        if ($keys['cap_seat_id'] !== null) {
            $cov = $gate->scopedQuery(array('scope' => array('v' => 'substitute_coverages')),
                "SELECT v.cov_id FROM substitute_coverages v
                  WHERE {TENANT_SCOPE} AND v.covered_seat_id = ?
                    AND v.state IN ('approved','active')
                    AND v.valid_from <= ? AND v.valid_to >= ?
                  ORDER BY v.cov_id DESC LIMIT 1",
                array((int) $keys['cap_seat_id'], $date, $date));
            if ($cov) { $keys['cap_coverage_id'] = (int) $cov[0]['cov_id']; }
        }

        // ⑧ المقياس — من الالتزام أولًا (فلا يُخصم الطنُّ من حصة ساعات)
        if ($keys['cap_obligation_id'] !== null) {
            $o = $gate->scopedQuery(array('scope' => array('c' => 'contract_commitments')),
                "SELECT c.measure_code FROM contract_commitments c WHERE {TENANT_SCOPE} AND c.id = ?",
                array((int) $keys['cap_obligation_id']));
            if ($o && $o[0]['measure_code'] !== null) { $keys['cap_measure_code'] = (string) $o[0]['measure_code']; }
        }
        if ($keys['cap_measure_code'] === null
            && in_array($unitType, array('hour', 'ton', 'trip', 'meter'), true)) {
            $keys['cap_measure_code'] = $unitType;
        }
        if ($keys['cap_measure_code'] === null) {
            $missing[] = 'مقياس القدرة غير محدد — الوحدة «' . $unitType . '» خارج مقاييس §16 الأربعة';
            $links[] = 'Clients/contract_commitments.php — حدد measure_code في التزام النوع';
        }

        return array('keys' => $keys, 'missing' => $missing, 'links' => $links,
                     'resolved' => empty($missing));
    }

    /**
     * CAP-32: منعُ فتح التايم شيت إذا كانت التخصيصاتُ ناقصة — يُرفض بقائمة
     * الناقص وروابطه. (يوصَل خلف قائمة مواقع EMS_CONTAINER_GATE بوضعها.)
     * @return array{ok:bool,code:int,reasons:array,links:array,keys:array}
     */
    public static function assertTimesheetOpenable($gate, $companyId, array $ctx)
    {
        $r = self::resolve($gate, $companyId, $ctx);
        if ($r['resolved']) {
            return array('ok' => true, 'code' => 200, 'reasons' => array(), 'links' => array(), 'keys' => $r['keys']);
        }
        return array('ok' => false, 'code' => 422,
            'reasons' => $r['missing'], 'links' => $r['links'], 'keys' => $r['keys']);
    }

    /**
     * CAP-31: ختمُ المفاتيح مقترحةً على الواقعة — **ولا تُحلّ ثانيةً إن قُفلت** (C29).
     * @return array{ok:bool,code:int,reason:string}
     */
    public static function stampProposed($gate, $entryId, array $keys, $state = 'proposed')
    {
        $rows = $gate->scopedQuery(array('scope' => array('e' => 'unit_entries')),
            "SELECT e.cap_context_state FROM unit_entries e WHERE {TENANT_SCOPE} AND e.id = ?",
            array((int) $entryId));
        if (!$rows) { return array('ok' => false, 'code' => 404, 'reason' => 'الواقعة غير موجودة في نطاقك'); }
        if ((string) $rows[0]['cap_context_state'] === 'locked') {
            return array('ok' => false, 'code' => 423,
                'reason' => 'اللقطة مثبتة عند الاعتماد — لا تحل المراجع ثانية أبدا (C29)');
        }
        $upd = array('cap_context_state' => in_array((string) $state, array('proposed', 'confirmed'), true) ? $state : 'proposed');
        foreach (array('cap_obligation_id', 'cap_supplier_share_id', 'cap_seat_id', 'cap_assignment_id',
                       'cap_supplier_line_id', 'cap_role_snapshot', 'cap_coverage_id', 'cap_measure_code') as $k) {
            if (array_key_exists($k, $keys)) { $upd[$k] = $keys[$k]; }
        }
        $gate->update('unit_entries', $upd, array('id' => (int) $entryId));
        return array('ok' => true, 'code' => 200, 'reason' => 'ختمت المفاتيح (' . $upd['cap_context_state'] . ') — تعرض للتأكيد لا للإدخال');
    }

    /**
     * CAP-33: تثبيتُ اللقطة عند الاعتماد — revision_no هو النسخة.
     * تُستدعى ضمن معاملةِ اكتمال السلسلة (البوابةُ المعاملاتيةُ نفسُها).
     */
    public static function lockSnapshot($g, $entryId)
    {
        $g->update('unit_entries', array('cap_context_state' => 'locked'), array('id' => (int) $entryId));
        return array('ok' => true, 'code' => 200, 'reason' => 'ثبتت اللقطة — لا تحل ثانية (C29)');
    }
}
