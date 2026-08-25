<?php
/**
 * خدمة الأداء الشهري والإسناد — MonthlyPerformanceService (N-12 · PLAN-04 §2.2)
 * ───────────────────────────────────────────────────────────────────────────
 * الأحكام المنفَّذة:
 *   ① السجل **مشتق مجمَّع فوق container_consumption** — المنفَّذ يُجمع منه لا يُدخل.
 *   ② الإسناد: السبب من قائمة محكومة حصرًا · **لكل سبب بند التزام مقابل
 *     إلزامي** في مصفوفة العقد المُجازة · **والطرف المتحمل يُشتق منه آليًّا
 *     ولا يُكتب حرًّا** · وسبب بلا بند مقابل → 422.
 *   ③ **إقفال شهر وفيه ساعات تعطل بلا طرف متحمل → يُرفض** (شرط إغلاق البوابة ①).
 *   ④ **ليس مصدر كمية الفوترة**: لا خدمة فوترة تقرأ من هذا الجدول — مصدر
 *     الكمية سجل الوحدات القانوني حصرًا (يثبته الاختبار بفحص القارئين).
 */

namespace App\Services;

class MonthlyPerformanceService
{
    /**
     * فتح/تحديث سجل شهرٍ لمقعد — المنفَّذ مجمَّعٌ من دفتر الاستهلاك.
     * @return array{ok:bool,code:int,reason:string,perf_id:int,executed_hours:float}
     */
    public static function openOrRefresh($gate, $companyId, $containerId, $period, array $extra, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'perf_id' => 0, 'executed_hours' => 0.0);
        $containerId = (int) $containerId;
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            $out['code'] = 422; $out['reason'] = 'الفترة بصيغة YYYY-MM حصرا'; return $out;
        }
        $seat = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
            "SELECT c.id, c.contract_id, c.seat_no, c.contract_hours_monthly FROM op_containers c
              WHERE {TENANT_SCOPE} AND c.id = ? AND c.seat_no IS NOT NULL", array($containerId));
        if (!$seat) { $out['code'] = 404; $out['reason'] = 'ليست حاوية مقعد معرف'; return $out; }
        $s = $seat[0];

        // ① المنفَّذ يُجمع من دفتر الاستهلاك — لا يُدخل يدويًّا (مصدر واحد)
        $agg = $gate->scopedQuery(array('scope' => array('cc' => 'container_consumption')),
            "SELECT COALESCE(SUM(CASE WHEN cc.unit_type='hour' THEN cc.qty ELSE 0 END),0) hours,
                    COALESCE(SUM(CASE WHEN cc.unit_type='trip' THEN cc.qty ELSE 0 END),0) trips,
                    COALESCE(SUM(CASE WHEN cc.unit_type='ton' THEN cc.qty ELSE 0 END),0) tons,
                    COALESCE(SUM(CASE WHEN cc.unit_type='meter' THEN cc.qty ELSE 0 END),0) meters
               FROM container_consumption cc
              WHERE {TENANT_SCOPE} AND cc.container_id = ? AND DATE_FORMAT(cc.consumed_on, '%Y-%m') = ?",
            array($containerId, (string) $period));
        $a = $agg ? $agg[0] : array('hours' => 0, 'trips' => 0, 'tons' => 0, 'meters' => 0);
        $executed = (float) $a['hours'];

        $existing = $gate->scopedQuery(array('scope' => array('m' => 'monthly_performance')),
            "SELECT m.id, m.state FROM monthly_performance m WHERE {TENANT_SCOPE} AND m.container_id = ? AND m.period = ?",
            array($containerId, (string) $period));
        $data = array(
            'contract_hours' => ($s['contract_hours_monthly'] !== null) ? (float) $s['contract_hours_monthly'] : 0,
            'executed_hours' => $executed,
            'executed_base_hours' => isset($extra['executed_base_hours']) ? (float) $extra['executed_base_hours'] : $executed,
            'standby_hours' => isset($extra['standby_hours']) ? (float) $extra['standby_hours'] : 0,
            'available_hours' => isset($extra['available_hours']) ? (float) $extra['available_hours'] : 0,
            'trips' => (float) $a['trips'], 'tons' => (float) $a['tons'], 'meters' => (float) $a['meters'],
            'fuel_consumed' => isset($extra['fuel_consumed']) ? (float) $extra['fuel_consumed'] : 0,
        );
        if ($existing) {
            if ((string) $existing[0]['state'] === 'closed') {
                $out['code'] = 423; $out['reason'] = 'الشهر مقفل — التصحيح بعكس موثق لا بتعديل'; return $out;
            }
            $gate->update('monthly_performance', $data, array('id' => (int) $existing[0]['id']));
            $out['perf_id'] = (int) $existing[0]['id'];
        } else {
            $data['contract_id'] = (int) $s['contract_id'];
            $data['container_id'] = $containerId;
            $data['period'] = (string) $period;
            $data['created_by'] = (int) $actor ?: null;
            $out['perf_id'] = (int) $gate->insert('monthly_performance', $data);
        }
        $out['ok'] = true; $out['code'] = 200; $out['executed_hours'] = $executed;
        $out['reason'] = 'سجل ' . $period . ' للمقعد #' . $s['seat_no'] . ' — المنفذ ' . $executed . ' ساعة (مجمع من دفتر الاستهلاك)';
        return $out;
    }

    /**
     * تسجيل ساعات تعطلٍ بسببها — **الطرف المتحمل يُشتق من مصفوفة الالتزامات**.
     * @return array{ok:bool,code:int,reason:string,bearer:string}
     */
    public static function addDowntime($gate, $companyId, $perfId, $reasonCode, $hours, $opt, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'bearer' => '');
        $perfId = (int) $perfId; $hours = (float) $hours;
        if ($hours <= 0) { $out['code'] = 422; $out['reason'] = 'الساعات موجبة حصرا'; return $out; }

        // ② السبب من القائمة المحكومة حصرًا — لا نص حر؛ يُقرأ القاموس العالمي
        //    بضمّه لاستعلام السجل (T_GLOBAL لا يحتاج إعلانًا ولا عزلًا)
        $rc = (string) $reasonCode;
        $perf = $gate->scopedQuery(array('scope' => array('m' => 'monthly_performance')),
            "SELECT m.id, m.contract_id, m.period, m.state, r.code AS reason_ok, r.obligation_type
               FROM monthly_performance m
               LEFT JOIN stop_reason_codes r ON r.code = ? AND r.active = 1
              WHERE {TENANT_SCOPE} AND m.id = ?",
            array($rc, $perfId));
        if (!$perf) { $out['code'] = 404; $out['reason'] = 'سجل الأداء غير موجود'; return $out; }
        if ((string) $perf[0]['state'] === 'closed') { $out['code'] = 423; $out['reason'] = 'الشهر مقفل'; return $out; }
        $reason = ($perf[0]['reason_ok'] !== null) ? array('code' => $perf[0]['reason_ok'], 'obligation_type' => $perf[0]['obligation_type']) : null;
        if (!$reason) { $out['code'] = 422; $out['reason'] = 'سبب تعطل خارج القائمة المحكومة: ' . $rc; return $out; }

        $obType = $reason['obligation_type'];
        if ($obType === null) {
            // سبب «أخرى» يُلزم ببند صريح — لا افتراض صامتًا
            $obType = isset($opt['obligation_type']) ? (string) $opt['obligation_type'] : '';
            if ($obType === '') {
                $out['code'] = 422; $out['reason'] = 'سبب «أخرى» يلزم ببند التزام صريح — لا افتراض صامتا'; return $out;
            }
        }

        // ②-ب البند المقابل الإلزامي: من مصفوفة العقد **المُجازة** النافذة في الفترة
        $onDate = (string) $perf[0]['period'] . '-01';
        $ob = $gate->scopedQuery(array('scope' => array('o' => 'contract_obligations')),
            "SELECT o.id, o.obligor, o.effect_on_billing FROM contract_obligations o
              WHERE {TENANT_SCOPE} AND o.client_contract_id = ? AND o.obligation_type = ?
                AND o.approval_state = 'approved' AND COALESCE(o.is_deleted,0)=0
                AND o.valid_from <= ? AND (o.valid_to IS NULL OR o.valid_to >= ?)
              ORDER BY o.valid_from DESC LIMIT 1",
            array((int) $perf[0]['contract_id'], $obType, $onDate, $onDate));
        if (!$ob) {
            $out['code'] = 422;
            $out['reason'] = 'سبب بلا بند مقابل لا يقبل — لا التزام مجازا من نوع «' . $obType
                . '» نافذا في ' . $perf[0]['period'] . ' لهذا العقد';
            return $out;
        }
        $bearer = (string) $ob[0]['obligor'];

        try {
            $gate->insert('monthly_performance_downtime', array(
                'perf_id' => $perfId,
                'reason_code' => $rc,
                'hours' => $hours,
                'obligation_id' => (int) $ob[0]['id'],
                'bearer_party' => $bearer,
                'effect_on_billing' => (string) $ob[0]['effect_on_billing'],
                'note' => isset($opt['note']) ? (string) $opt['note'] : null,
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'السبب مسجل لهذا الشهر — عدل صفه لا تكرره'; return $out;
            }
            throw $t;
        }
        $out['ok'] = true; $out['code'] = 201; $out['bearer'] = $bearer;
        $out['reason'] = $hours . ' ساعة «' . $reason['code'] . '» — الطرف المتحمل: ' . $bearer . ' (مشتق من البند #' . $ob[0]['id'] . ')';
        return $out;
    }

    /**
     * إقفال شهر — **يُرفض إن وُجدت ساعات تعطل بلا طرفٍ متحمل** أو عجزٌ غير مفسَّر.
     * @return array{ok:bool,code:int,reason:string}
     */
    public static function closeMonth($gate, $companyId, $perfId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $perfId = (int) $perfId;
        $perf = $gate->scopedQuery(array('scope' => array('m' => 'monthly_performance')),
            "SELECT m.* FROM monthly_performance m WHERE {TENANT_SCOPE} AND m.id = ?", array($perfId));
        if (!$perf) { $out['code'] = 404; $out['reason'] = 'سجل الأداء غير موجود'; return $out; }
        $p = $perf[0];
        if ((string) $p['state'] === 'closed') { $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'مقفل سلفا — فعل عاطل'; return $out; }

        // ③ العجز عن التعاقد لا بد أن يفسّره التعطل المسنَد — ساعة تعطل بلا طرف لا وجود
        // لها بنيويًّا (bearer_party NOT NULL)، فيبقى فحص التغطية: العجز > Σ المسنَد → رفض.
        $dt = $gate->scopedQuery(array('scope' => array('d' => 'monthly_performance_downtime')),
            "SELECT COALESCE(SUM(d.hours),0) attributed FROM monthly_performance_downtime d
              WHERE {TENANT_SCOPE} AND d.perf_id = ?", array($perfId));
        $attributed = (float) $dt[0]['attributed'];
        $shortfall = (float) $p['shortfall_hours'];
        if ($shortfall > 0.005 && $attributed + 0.005 < $shortfall) {
            $out['code'] = 422;
            $out['reason'] = 'لا يقفل شهر وفيه ساعات عجز بلا طرف متحمل: العجز ' . $shortfall
                . ' والمسند ' . $attributed . ' — أسند الفرق (' . round($shortfall - $attributed, 2) . ') بسببه وبنده';
            return $out;
        }
        $gate->update('monthly_performance', array(
            'state' => 'closed', 'closed_by' => (int) $actor ?: null, 'closed_at' => date('Y-m-d H:i:s'),
        ), array('id' => $perfId));
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'أقفل شهر ' . $p['period'] . ' — كل ساعة تعطل بطرفها المتحمل';
        return $out;
    }

    /** الأحكام الثلاثة المشتقة من الإسناد (PLAN-04 §2.2) — للعرض والجزاءات لا للفوترة. */
    public static function billingImplications($gate, $companyId, $perfId)
    {
        $rows = $gate->scopedQuery(array('scope' => array('d' => 'monthly_performance_downtime')),
            "SELECT d.reason_code, d.hours, d.bearer_party, d.effect_on_billing FROM monthly_performance_downtime d
              WHERE {TENANT_SCOPE} AND d.perf_id = ? ORDER BY d.id", array((int) $perfId));
        $o = array();
        foreach ($rows as $r) {
            $bearer = (string) $r['bearer_party'];
            $eff = (string) $r['effect_on_billing'];
            $o[] = array(
                'reason' => (string) $r['reason_code'],
                'hours' => (float) $r['hours'],
                'bearer' => $bearer,
                'billable' => ($eff === 'billable_standby') || ($eff === 'per_clause' && $bearer === 'client'),
                'supplier_entitled' => ($bearer === 'client'),
                'supplier_penalized' => ($bearer === 'supplier'),
            );
        }
        return $o;
    }
}
