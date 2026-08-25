<?php
/**
 * app/Services/Capacity/SubstituteCoverageService.php — سلّمُ التغطية البديلة
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-20/23/24 — CAP-01 §6/§7: «مساحةُ مرونةٍ لمدير الحركة عند العجز بأربع
 * درجاتٍ لا درجةٍ واحدة … والدرجةُ تُحدَّد بأثرها لا بمن يطلبها».
 *
 * الدرجاتُ وموافقوها (§6):
 *   ① own_standby    — مديرُ الحركة وحدَه · بمدةٍ ≤ مهلةِ الإحلال · لا أثرَ على الحصة.
 *   ② cross_supplier — الحركةُ + الصيانةُ (شهادةُ جاهزية) + مسؤولُ المشغّلين · أثرُها
 *                      تغطيةٌ استثنائيةٌ لا نقلُ حصة.
 *   ③ source_change  — الماليُّ + التنفيذيُّ — ولا يملكه مديرُ الحركة ولا التشغيل.
 *   (والدرجةُ ④ «زيادةٌ فوق المتعاقد» ليست تغطيةً — بابُها SigmaGuard وملحقُ
 *    العقد وسلّمُ GOV-01 §4 — §10-④.)
 *
 * قيودُ §6.1 الستة (CAP-24):
 *   ① سببٌ محكومٌ (ENUM بنيوي) · ② مدةٌ مغلقةٌ (NOT NULL بنيوي) · ③ الجاهزيةُ
 *   شرطٌ فنيٌّ (DocumentGuard على المعدة البديلة) · ④ المشغّلُ لا يُنقل تلقائيًّا
 *   (لا كتابةَ تكليفٍ هنا أصلًا) · ⑤ الأثرُ يُحسب قبل الإرسال (impact_json
 *   إلزاميٌّ للتقديم) · ⑥ التغطيةُ لا تُخفي العجز (محاولةُ التصفير → 403).
 *
 * محاسبةُ §7 (CAP-23): عند التسوية أربعةُ بنودٍ ظاهرة — العميلُ يُفوتر كاملًا ·
 * عجزُ المتعطل باقٍ بجزائه · المغطِّي بندٌ استثنائيٌّ لا حصةٌ تُرفع · والمشغّلُ بعقده.
 */

namespace App\Services\Capacity;

class SubstituteCoverageService
{
    /** موافقو كل درجة — «الدرجةُ تُحدَّد بأثرها لا بمن يطلبها» (§6). */
    const REQUIRED_APPROVALS = array(
        'own_standby'    => array('movement_mgr'),
        'cross_supplier' => array('movement_mgr', 'maintenance_officer', 'operators_officer'),
        'source_change'  => array('finance_mgr', 'executive_mgr'),
    );

    /** وضعُ السلّم: off · monitor · enforce (EMS_SUBSTITUTE_LADDER). */
    public static function mode()
    {
        $m = function_exists('ems_env')
            ? strtolower(trim((string) ems_env('EMS_SUBSTITUTE_LADDER', 'off'))) : 'off';
        return in_array($m, array('off', 'monitor', 'enforce'), true) ? $m : 'off';
    }

    /**
     * إنشاءُ طلب تغطيةٍ (مسودة) — الأثرُ يُحسب هنا قبل أي إرسال (§6.1-⑤).
     * @return array{ok:bool,code:int,reason:string,cov_id:?int,impact:?array}
     */
    public static function create($gate, $companyId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'cov_id' => null, 'impact' => null);

        $level = isset($a['level']) ? (string) $a['level'] : '';
        if (!isset(self::REQUIRED_APPROVALS[$level])) {
            $out['code'] = 422; $out['reason'] = 'درجة خارج سلم §6 الثلاث — والزيادة فوق المتعاقد بابها الملحق وSigmaGuard (§10-④)';
            return $out;
        }
        $seatId = isset($a['covered_seat_id']) ? (int) $a['covered_seat_id'] : 0;
        $seats = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
            "SELECT c.id, c.supplier_id, c.parent_id, c.contract_id, c.contract_hours_monthly
               FROM op_containers c WHERE {TENANT_SCOPE} AND c.id = ? AND c.is_deleted = 0",
            array($seatId));
        if (!$seats) { $out['code'] = 404; $out['reason'] = 'المقعد المغطى غير موجود في نطاقك'; return $out; }
        $seat = $seats[0];

        // الموردُ المتعطل — لقطةٌ من شجرة المقعد (المقعدُ أو أبوه «مورد»)
        $failedSupplier = $seat['supplier_id'] !== null ? (int) $seat['supplier_id'] : null;
        if ($failedSupplier === null && $seat['parent_id'] !== null) {
            $p = $gate->scopedQuery(array('scope' => array('c' => 'op_containers')),
                "SELECT c.supplier_id FROM op_containers c WHERE {TENANT_SCOPE} AND c.id = ?",
                array((int) $seat['parent_id']));
            if ($p && $p[0]['supplier_id'] !== null) { $failedSupplier = (int) $p[0]['supplier_id']; }
        }
        $covering = isset($a['covering_supplier_id']) ? (int) $a['covering_supplier_id'] : 0;
        if ($level === 'own_standby') { $covering = $covering ?: (int) $failedSupplier; }
        if ($covering <= 0) { $out['code'] = 422; $out['reason'] = 'المورد المغطي إلزامي'; return $out; }
        if ($level === 'own_standby' && $failedSupplier !== null && $covering !== $failedSupplier) {
            $out['code'] = 422;
            $out['reason'] = 'الدرجة ① احتياطي المورد نفسه — مغط مختلف درجته ② بموافقاتها الثلاث';
            return $out;
        }

        $from = isset($a['valid_from']) ? (string) $a['valid_from'] : '';
        $to = isset($a['valid_to']) ? (string) $a['valid_to'] : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $out['code'] = 422; $out['reason'] = 'لا تغطية مفتوحة المدة — البداية والنهاية إلزاميتان (§6.1-②)';
            return $out;
        }

        // الدرجةُ ①: المدةُ لا تتجاوز مهلةَ الإحلال في عقد المورد (§6)
        if ($level === 'own_standby') {
            $sla = self::replacementSlaDays($gate, $seat, $failedSupplier);
            if ($sla !== null) {
                $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
                if ($days > $sla) {
                    $out['code'] = 422;
                    $out['reason'] = 'مدة الدرجة ① (' . $days . ' يوما) تتجاوز مهلة الإحلال ('
                                   . $sla . ' يوما) — فوقها الدرجة ② أو إحلال دائم';
                    return $out;
                }
            }
        }

        // §6.1-③: الجاهزيةُ شرطٌ فنيٌّ — المعدةُ البديلةُ بوثيقةٍ منتهية → 403
        $coveringEq = isset($a['covering_equipment_id']) ? (int) $a['covering_equipment_id'] : null;
        if ($coveringEq) {
            $doc = self::readinessOf($gate, $coveringEq, $from);
            if (!$doc['ok']) {
                $out['code'] = 403;
                $out['reason'] = 'الجاهزية شرط فني لا إداري — ' . $doc['reason'] . ' (§6.1-③)';
                return $out;
            }
        }

        // §6.1-⑤: الأثرُ على الأطراف الأربعة يُحسب قبل الإرسال
        $hours = isset($a['estimated_hours']) && $a['estimated_hours'] !== '' ? round((float) $a['estimated_hours'], 2) : null;
        if ($hours === null || $hours <= 0) {
            $out['code'] = 422;
            $out['reason'] = 'الأثر يحسب قبل الاعتماد — الساعات المقدرة إلزامية ولا تقدر بعد التنفيذ (§6.1-⑤)';
            return $out;
        }
        $impact = self::computeImpact($level, $hours, $failedSupplier, $covering);

        try {
            $covId = (int) $gate->insert('substitute_coverages', array(
                'level'                => $level,
                'covered_seat_id'      => $seatId,
                'covering_supplier_id' => $covering,
                'failed_supplier_id'   => $failedSupplier,
                'covering_equipment_id'=> $coveringEq,
                'reason_code'          => isset($a['reason_code']) ? (string) $a['reason_code'] : null,
                'reason_ref'           => isset($a['reason_ref']) && $a['reason_ref'] !== '' ? (string) $a['reason_ref'] : null,
                'valid_from'           => $from,
                'valid_to'             => $to,
                'estimated_hours'      => $hours,
                'impact_json'          => json_encode($impact, JSON_UNESCAPED_UNICODE),
                'approvals_json'       => json_encode(new \stdClass()),
                'state'                => 'pending_approvals',
                'note'                 => isset($a['note']) ? mb_substr((string) $a['note'], 0, 255) : null,
                'created_by'           => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422;
            $out['reason'] = 'رفض بنيوي: ' . $t->getMessage();
            return $out;
        }
        $out['ok'] = true; $out['code'] = 201; $out['cov_id'] = $covId; $out['impact'] = $impact;
        $out['reason'] = 'طلب تغطية درجة «' . $level . '» أنشئ بأثر محسوب معروض على موافقيه ('
                       . implode(' + ', self::REQUIRED_APPROVALS[$level]) . ')';
        return $out;
    }

    /**
     * موافقةُ دورٍ على التغطية — والاعتمادُ لا يكتمل حتى تكتمل مصفوفةُ درجتها.
     * C8: الدرجةُ ① بموافقة مدير الحركة وحدَه.
     * C9: الدرجةُ ② تبقى معلقةً حتى تكتمل الثلاث.
     * C10: الدرجةُ ③ لا يملكها مديرُ الحركة ولا التشغيل → 403.
     * @return array{ok:bool,code:int,reason:string,state:?string,granted:array,missing:array}
     */
    public static function approve($gate, $covId, $role, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'state' => null,
                     'granted' => array(), 'missing' => array());
        $rows = $gate->scopedQuery(array('scope' => array('v' => 'substitute_coverages')),
            "SELECT v.* FROM substitute_coverages v WHERE {TENANT_SCOPE} AND v.cov_id = ?",
            array((int) $covId));
        if (!$rows) { $out['code'] = 404; $out['reason'] = 'التغطية غير موجودة في نطاقك'; return $out; }
        $cov = $rows[0];
        if ((string) $cov['state'] !== 'pending_approvals') {
            $out['code'] = 409; $out['reason'] = 'التغطية ليست في انتظار الموافقات — حالتها: ' . $cov['state'];
            return $out;
        }
        $required = self::REQUIRED_APPROVALS[(string) $cov['level']];
        if (!in_array((string) $role, $required, true)) {
            $out['code'] = 403;
            $out['reason'] = 'الدرجة «' . $cov['level'] . '» لا يعتمدها هذا الدور — موافقوها: '
                           . implode(' + ', $required) . ' (§6)';
            return $out;
        }
        $granted = json_decode((string) $cov['approvals_json'], true);
        if (!is_array($granted)) { $granted = array(); }
        $granted[(string) $role] = array('by' => (int) $actor, 'at' => self::dbNow($gate));
        $missing = array_values(array_diff($required, array_keys($granted)));
        $newState = empty($missing) ? 'approved' : 'pending_approvals';
        $gate->update('substitute_coverages', array(
            'approvals_json' => json_encode($granted, JSON_UNESCAPED_UNICODE),
            'state'          => $newState,
        ), array('cov_id' => (int) $covId));
        $out['ok'] = true; $out['code'] = 200; $out['state'] = $newState;
        $out['granted'] = array_keys($granted); $out['missing'] = $missing;
        $out['reason'] = empty($missing)
            ? 'اكتملت موافقات الدرجة — التغطية معتمدة'
            : 'موافقة «' . $role . '» سجلت — الناقص: ' . implode(' + ', $missing) . ' (لا اعتماد حتى تكتمل)';
        return $out;
    }

    /**
     * تسويةُ §7 (CAP-23): أربعةُ بنودٍ ظاهرةٍ لا سطرٌ مدموج — تُكتب مرةً واحدة.
     * @return array{ok:bool,code:int,reason:string,lines:int}
     */
    public static function settle($gate, $covId, $qty, $coveragePrice = null, $currency = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'lines' => 0);
        $rows = $gate->scopedQuery(array('scope' => array('v' => 'substitute_coverages')),
            "SELECT v.* FROM substitute_coverages v WHERE {TENANT_SCOPE} AND v.cov_id = ?",
            array((int) $covId));
        if (!$rows) { $out['code'] = 404; $out['reason'] = 'التغطية غير موجودة'; return $out; }
        $cov = $rows[0];
        if (!in_array((string) $cov['state'], array('approved', 'active'), true)) {
            $out['code'] = 409; $out['reason'] = 'لا تسوية لتغطية غير معتمدة — الحالة: ' . $cov['state'];
            return $out;
        }
        $prior = $gate->scopedQuery(array('scope' => array('l' => 'coverage_settlement_lines')),
            "SELECT COUNT(*) n FROM coverage_settlement_lines l WHERE {TENANT_SCOPE} AND l.cov_id = ?",
            array((int) $covId));
        if ($prior && (int) $prior[0]['n'] > 0) {
            $out['code'] = 409; $out['reason'] = 'بنود التسوية مقيدة من قبل — لا ازدواج';
            return $out;
        }
        $qty = round((float) $qty, 2);
        $amount = $coveragePrice !== null ? round($qty * (float) $coveragePrice, 2) : null;
        // §7: العميلُ يُفوتر كاملًا · عجزُ المتعطل باقٍ بجزائه · المغطِّي بندٌ
        // مستقلٌّ بسعر التغطية المتفق · والمشغّلُ يستحق بعقده هو
        $lines = array(
            array('party' => 'client', 'effect' => 'billable',
                  'note' => 'تفوتر ساعات التغطية كأنها نفذت — الخدمة وصلت (§7-①)'),
            array('party' => 'failed_supplier', 'effect' => 'gap_kept',
                  'note' => 'العجز باق كاملا وجزاؤه بقاعدة عقده ومهلته من لحظة التعطل (§7-②)'),
            array('party' => 'covering_supplier', 'effect' => 'exceptional_line',
                  'note' => 'بند تغطية استثنائية بسعره ومرجع قراره — لا يرفع حصته (§7-③)'),
            array('party' => 'operator', 'effect' => 'entitlement',
                  'note' => 'يستحق أجره وحافزه على ما شغل بعقده هو (§7-④)'),
        );
        foreach ($lines as $ln) {
            $gate->insert('coverage_settlement_lines', array(
                'cov_id'       => (int) $covId,
                'party'        => $ln['party'],
                'effect'       => $ln['effect'],
                'qty'          => $qty,
                'measure_code' => 'hour',
                'amount'       => $ln['party'] === 'covering_supplier' ? $amount : null,
                'currency'     => $ln['party'] === 'covering_supplier' ? $currency : null,
                'settlement_ref' => 'cov:' . (int) $covId,
                'note'         => $ln['note'],
            ));
            $out['lines']++;
        }
        $out['ok'] = true; $out['code'] = 201;
        $out['reason'] = 'قيدت بنود الأطراف الأربعة ظاهرة — والعجز محفوظ والحصة لم ترفع';
        return $out;
    }

    /**
     * §6.1-⑥ (CAP-24): لا تُخفي التغطيةُ العجز — محاولةُ تصفير عجز المتعطل → 403.
     * @return array{ok:bool,code:int,reason:string}
     */
    public static function zeroFailedGap()
    {
        return array('ok' => false, 'code' => 403,
            'reason' => 'التغطية البديلة لا تصفر عجز المتعطل ولا تسقط جزاءه — العجز يبقى مسجلا ظاهرا (§6.1-⑥)');
    }

    /** «محاولةُ تعديل الحصة الأصلية» عبر التغطية → 423 (§16-Validation). */
    public static function modifyOriginalShare()
    {
        return array('ok' => false, 'code' => 423,
            'reason' => 'التغطية لا تعدل الحصة الأصلية — أثرها بند استثنائي مستقل (§6-②)');
    }

    /** الأثرُ على الأطراف الأربعة — يُعرض على الموافقين قبل القرار (§6.1-⑤). */
    public static function computeImpact($level, $hours, $failedSupplier, $coveringSupplier)
    {
        return array(
            'client'            => array('effect' => 'billable', 'hours' => $hours,
                                         'note' => 'يفوتر كاملا — الخدمة تصل'),
            'failed_supplier'   => array('ref' => $failedSupplier, 'effect' => 'gap_kept', 'hours' => $hours,
                                         'note' => 'عجزه يبقى وجزاؤه بقاعدة عقده'),
            'covering_supplier' => array('ref' => $coveringSupplier,
                                         'effect' => $level === 'own_standby' ? 'share_execution_standby' : 'exceptional_line',
                                         'hours' => $hours,
                                         'note' => $level === 'own_standby'
                                             ? 'تنفيذ حصته باحتياطيه — لا أثر على الحصة (§6-①)'
                                             : 'بند استثنائي مستقل لا حصة ترفع'),
            'operator'          => array('effect' => 'entitlement',
                                         'note' => 'تكليفه قرار مستقل بتأهيله — لا ينقل تلقائيا (§6.1-④)'),
        );
    }

    /** مهلةُ الإحلال بالأيام من بنود عقد المورد المتعطل (أدنى مهلةٍ معلنة). */
    private static function replacementSlaDays($gate, array $seat, $failedSupplier)
    {
        if ($failedSupplier === null) { return null; }
        $rows = $gate->scopedQuery(array(
                'scope' => array('l' => 'supplier_contract_lines', 'h' => 'supplier_contracts')),
            "SELECT MIN(l.replacement_sla_hours) sla
               FROM supplier_contract_lines l
               JOIN supplier_contracts h ON h.id = l.contract_id AND COALESCE(h.is_deleted,0) = 0
              WHERE {TENANT_SCOPE} AND h.supplier_id = ? AND l.is_deleted = 0
                AND l.replacement_sla_hours IS NOT NULL",
            array((int) $failedSupplier));
        if (!$rows || $rows[0]['sla'] === null) { return null; }
        return (int) ceil(((float) $rows[0]['sla']) / 24);
    }

    /** جاهزيةُ المعدة البديلة — حارسُ الوثائق القائمُ نافذٌ عليها كغيرها. */
    private static function readinessOf($gate, $equipmentId, $onDate)
    {
        $rows = $gate->scopedQuery(array('scope' => array('d' => 'equipment_documents')),
            "SELECT d.doc_type, d.expiry_date FROM equipment_documents d
              WHERE {TENANT_SCOPE} AND d.subject_type = 'equipment' AND d.subject_id = ?
                AND d.expiry_date < ?
                AND d.doc_type IN ('استمارة','تأمين','فحص دوري','رخصة تشغيل')",
            array((int) $equipmentId, (string) $onDate));
        if ($rows) {
            $names = array_map(function ($r) { return $r['doc_type'] . ' (انتهت ' . $r['expiry_date'] . ')'; }, $rows);
            return array('ok' => false, 'reason' => 'المعدة البديلة بوثائق منتهية: ' . implode(' · ', $names));
        }
        return array('ok' => true, 'reason' => '');
    }

    /** الوقتُ بساعة القاعدة — عُرفُ المهل. */
    private static function dbNow($gate)
    {
        $r = $gate->scopedQuery(array('scope' => array('v' => 'substitute_coverages')),
            "SELECT NOW() n FROM substitute_coverages v WHERE {TENANT_SCOPE} LIMIT 1", array());
        return $r ? (string) $r[0]['n'] : date('Y-m-d H:i:s');
    }
}
