<?php
/**
 * app/Services/Capacity/CapacityLedgerService.php — دفترُ استهلاك القدرات (CAP-11)
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §13: «لا يُعدَّل رصيدُ حصةٍ أو التزامٍ مباشرةً أبدًا — بل يُحسب من
 * الدفتر والإعكاسات. الأصلُ يبقى سطرًا والتصحيحُ سطرٌ عاكسٌ بمرجعه،
 * والرصيدُ نتيجةٌ لا مصدر».
 *
 * الأحكام المنفَّذة:
 *   ① الكتابةُ أسطرًا Insert-only — الحصانةُ بنيويةٌ عبر immutable_key
 *     (TenantRegistry): أيُّ تعديلٍ أو حذفٍ عبر البوابة يُرفض.
 *   ② مفتاحُ منع الخصم مرتين: UQ(سجلُّ الوحدة × نسختُه × الأثرُ × الطرفُ × مرجعُه)
 *     — إعادةُ الإرسال → **409 بمرجع السطر القائم** وصفرُ خصمٍ ثانٍ (C25).
 *   ③ العكسُ سطرٌ عاكسٌ بمرجع الأصل — **والأصلُ باقٍ** (C26) · عكسٌ بلا مرجعِ
 *     سطرٍ أصليٍّ → 422 · وعكسُ المعكوس أو تكرارُ العكس → 409 بمرجعه.
 *   ④ تعديلُ رصيدٍ مباشرةً → **403 بنيويًّا** — لا واجهةَ كتابةِ رصيدٍ هنا
 *     أصلًا، وأعمدةُ op_containers تصير مخبأً يُعاد بناؤه (الموجة ③).
 */

namespace App\Services\Capacity;

class CapacityLedgerService
{
    const EFFECT_TYPES = array('client_obligation', 'supplier_share', 'operator_entitlement', 'exceptional_coverage');
    const TARGET_TYPES = array('client', 'supplier', 'operator');
    const MEASURES = array('hour', 'ton', 'trip', 'meter');

    /**
     * كتابةُ سطرِ استهلاكٍ في الدفتر — يُستدعى داخل المعاملة الذرية (§14).
     *
     * @param mixed $gate بوابةُ العزل TenantDb
     * @param array $ln  السطر: unit_record_id · unit_record_version · effect_type ·
     *                   effect_target_type · effect_target_ref · measure_code · qty ·
     *                   period · والمفاتيحُ الثمانيةُ الاختيارية (§12.1)
     * @return array{ok:bool,code:int,reason:string,led_id:?int,existing_led_id:?int}
     */
    public static function appendLine($gate, array $ln, $actor = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'led_id' => null, 'existing_led_id' => null);

        $recId = isset($ln['unit_record_id']) ? (int) $ln['unit_record_id'] : 0;
        $recVer = isset($ln['unit_record_version']) ? (int) $ln['unit_record_version'] : -1;
        if ($recId <= 0 || $recVer < 0) {
            $out['code'] = 422; $out['reason'] = 'سجل الوحدة ونسخته إلزاميان — لا سطر دفتر بلا مصدر مرقم (§13.2)';
            return $out;
        }
        $effect = isset($ln['effect_type']) ? (string) $ln['effect_type'] : '';
        if (!in_array($effect, self::EFFECT_TYPES, true)) {
            $out['code'] = 422;
            $out['reason'] = 'أثر خارج الأربعة (' . implode('·', self::EFFECT_TYPES) . ') — والعكس عبر reverse() حصرا';
            return $out;
        }
        $targetType = isset($ln['effect_target_type']) ? (string) $ln['effect_target_type'] : '';
        $targetRef = isset($ln['effect_target_ref']) ? trim((string) $ln['effect_target_ref']) : '';
        if (!in_array($targetType, self::TARGET_TYPES, true) || $targetRef === '') {
            $out['code'] = 422; $out['reason'] = 'طرف الأثر ومرجعه إلزاميان — المفتاح عليهما';
            return $out;
        }
        $measure = isset($ln['measure_code']) ? (string) $ln['measure_code'] : '';
        if (!in_array($measure, self::MEASURES, true)) {
            $out['code'] = 422; $out['reason'] = 'مقياس خارج الأربعة (hour·ton·trip·meter) — فلا يخصم الطن من حصة ساعات';
            return $out;
        }
        $qty = isset($ln['qty']) ? (float) $ln['qty'] : -1;
        if ($qty < 0) { $out['code'] = 422; $out['reason'] = 'الكمية موجبة — والرد سطر عكس لا كمية سالبة'; return $out; }
        $period = isset($ln['period']) ? (string) $ln['period'] : '';
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $out['code'] = 422; $out['reason'] = 'الفترة YYYY-MM إلزامية'; return $out;
        }
        $role = isset($ln['role_snapshot']) && in_array((string) $ln['role_snapshot'], array('primary', 'standby'), true)
                ? (string) $ln['role_snapshot'] : null;

        $optInt = function ($key) use ($ln) {
            return isset($ln[$key]) && (int) $ln[$key] > 0 ? (int) $ln[$key] : null;
        };
        try {
            $ledId = (int) $gate->insert('capacity_consumption_ledger', array(
                'unit_record_id'            => $recId,
                'unit_record_version'       => $recVer,
                'contract_obligation_id'    => $optInt('contract_obligation_id'),
                'supplier_share_id'         => $optInt('supplier_share_id'),
                'contract_seat_id'          => $optInt('contract_seat_id'),
                'equipment_assignment_id'   => $optInt('equipment_assignment_id'),
                'supplier_contract_line_id' => $optInt('supplier_contract_line_id'),
                'operator_assignment_id'    => $optInt('operator_assignment_id'),
                'coverage_id'               => $optInt('coverage_id'),
                'effect_target_type'        => $targetType,
                'effect_target_ref'         => $targetRef,
                'measure_code'              => $measure,
                'qty'                       => $qty,
                'operational_hours'         => isset($ln['operational_hours']) && $ln['operational_hours'] !== '' && $ln['operational_hours'] !== null
                                               ? (float) $ln['operational_hours'] : null,
                'analytical_output_qty'     => isset($ln['analytical_output_qty']) && $ln['analytical_output_qty'] !== '' && $ln['analytical_output_qty'] !== null
                                               ? (float) $ln['analytical_output_qty'] : null,
                'effect_type'               => $effect,
                'role_snapshot'             => $role,
                'unit_decision_snapshot_id' => $optInt('unit_decision_snapshot_id'),
                'period'                    => $period,
                'created_by'                => $actor !== null ? (int) $actor : null,
            ));
        } catch (\Throwable $t) {
            if (self::isDuplicate($t)) {
                // C25: إعادةُ الإرسال → 409 بمرجع السطر القائم وصفرُ خصمٍ ثانٍ
                $existing = self::findByKey($gate, $recId, $recVer, $effect, $targetType, $targetRef);
                $out['code'] = 409;
                $out['existing_led_id'] = $existing;
                $out['reason'] = 'السطر مقيد من قبل — مرجعه led#' . ($existing ?: '?') . ' · صفر خصم ثان (C25)';
                return $out;
            }
            throw $t;
        }
        $out['ok'] = true; $out['code'] = 200; $out['led_id'] = $ledId;
        $out['reason'] = 'قيد سطر ' . $effect . ' للوحدة ' . $recId . '·v' . $recVer . ' — ' . $qty . ' ' . $measure;
        return $out;
    }

    /**
     * عكسُ سطرٍ معتمد — سطرٌ عاكسٌ بمرجع الأصل والأصلُ باقٍ (C26).
     * @return array{ok:bool,code:int,reason:string,led_id:?int,existing_led_id:?int}
     */
    public static function reverse($gate, $ledId, $actor = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'led_id' => null, 'existing_led_id' => null);
        $ledId = (int) $ledId;
        if ($ledId <= 0) { $out['code'] = 422; $out['reason'] = 'عكس بلا مرجع سطر أصلي — مرفوض (§16-Validation)'; return $out; }

        $rows = $gate->scopedQuery(array('scope' => array('l' => 'capacity_consumption_ledger')),
            "SELECT l.* FROM capacity_consumption_ledger l WHERE {TENANT_SCOPE} AND l.led_id = ?",
            array($ledId));
        if (!$rows) { $out['code'] = 404; $out['reason'] = 'سطر الدفتر غير موجود في نطاقك'; return $out; }
        $orig = $rows[0];
        if ((string) $orig['effect_type'] === 'reversal') {
            $out['code'] = 422; $out['reason'] = 'لا يعكس سطر عكس — التصحيح نسخة جديدة بأسطرها';
            return $out;
        }
        $prior = $gate->scopedQuery(array('scope' => array('l' => 'capacity_consumption_ledger')),
            "SELECT l.led_id FROM capacity_consumption_ledger l WHERE {TENANT_SCOPE} AND l.reverses_led_id = ?",
            array($ledId));
        if ($prior) {
            $out['code'] = 409; $out['existing_led_id'] = (int) $prior[0]['led_id'];
            $out['reason'] = 'السطر معكوس من قبل — مرجع العكس led#' . $prior[0]['led_id'];
            return $out;
        }
        try {
            $revId = (int) $gate->insert('capacity_consumption_ledger', array(
                'unit_record_id'            => (int) $orig['unit_record_id'],
                'unit_record_version'       => (int) $orig['unit_record_version'],
                'contract_obligation_id'    => $orig['contract_obligation_id'],
                'supplier_share_id'         => $orig['supplier_share_id'],
                'contract_seat_id'          => $orig['contract_seat_id'],
                'equipment_assignment_id'   => $orig['equipment_assignment_id'],
                'supplier_contract_line_id' => $orig['supplier_contract_line_id'],
                'operator_assignment_id'    => $orig['operator_assignment_id'],
                'coverage_id'               => $orig['coverage_id'],
                'effect_target_type'        => (string) $orig['effect_target_type'],
                'effect_target_ref'         => (string) $orig['effect_target_ref'],
                'measure_code'              => (string) $orig['measure_code'],
                'qty'                       => (float) $orig['qty'],
                'operational_hours'         => $orig['operational_hours'],
                'analytical_output_qty'     => $orig['analytical_output_qty'],
                'effect_type'               => 'reversal',
                'role_snapshot'             => $orig['role_snapshot'],
                'unit_decision_snapshot_id' => $orig['unit_decision_snapshot_id'],
                'period'                    => (string) $orig['period'],
                'reverses_led_id'           => $ledId,
                'created_by'                => $actor !== null ? (int) $actor : null,
            ));
        } catch (\Throwable $t) {
            if (self::isDuplicate($t)) {
                $out['code'] = 409;
                $out['reason'] = 'عكس هذه النسخة لهذا الطرف مقيد من قبل — المفتاح يمنع الازدواج';
                return $out;
            }
            throw $t;
        }
        $out['ok'] = true; $out['code'] = 200; $out['led_id'] = $revId;
        $out['reason'] = 'عكس led#' . $ledId . ' بالسطر العاكس led#' . $revId . ' — والأصل باق (C26)';
        return $out;
    }

    /**
     * ربطُ سطرِ دفترٍ بحدثٍ ماليٍّ بعد النشر (CAP-08) — Append-only.
     * @return array{ok:bool,code:int,reason:string,lnk_id:?int}
     */
    public static function linkFinancialEvent($gate, $ledId, $finEventId, $journalRef = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'lnk_id' => null);
        if ((int) $ledId <= 0 || (int) $finEventId <= 0) {
            $out['code'] = 422; $out['reason'] = 'سطر الدفتر والحدث المالي إلزاميان'; return $out;
        }
        try {
            $lnkId = (int) $gate->insert('capacity_financial_event_links', array(
                'led_id'       => (int) $ledId,
                'fin_event_id' => (int) $finEventId,
                'journal_ref'  => $journalRef !== null && $journalRef !== '' ? (string) $journalRef : null,
            ));
        } catch (\Throwable $t) {
            if (self::isDuplicate($t)) {
                $out['code'] = 409; $out['reason'] = 'الربط قائم — UQ(led,fin) يمنع الربط مرتين'; return $out;
            }
            throw $t;
        }
        $out['ok'] = true; $out['code'] = 200; $out['lnk_id'] = $lnkId;
        $out['reason'] = 'ربط led#' . $ledId . ' بالحدث المالي #' . $finEventId;
        return $out;
    }

    /** البحثُ بمفتاح منع التكرار — لمرجع 409. */
    public static function findByKey($gate, $recId, $recVer, $effect, $targetType, $targetRef)
    {
        $rows = $gate->scopedQuery(array('scope' => array('l' => 'capacity_consumption_ledger')),
            "SELECT l.led_id FROM capacity_consumption_ledger l
              WHERE {TENANT_SCOPE} AND l.unit_record_id = ? AND l.unit_record_version = ?
                AND l.effect_type = ? AND l.effect_target_type = ? AND l.effect_target_ref = ?",
            array((int) $recId, (int) $recVer, (string) $effect, (string) $targetType, (string) $targetRef));
        return $rows ? (int) $rows[0]['led_id'] : null;
    }

    private static function isDuplicate(\Throwable $t)
    {
        return strpos($t->getMessage(), 'Duplicate') !== false
            || strpos($t->getMessage(), '1062') !== false;
    }
}
