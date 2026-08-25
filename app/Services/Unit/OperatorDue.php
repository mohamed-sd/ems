<?php
/**
 * محرّك مستحقّات المشغّلين — OperatorDue (UX-02 §8.2)
 * ───────────────────────────────────────────────────────────────────────────
 * «تُعاد جدولَ سياساتٍ يدعم النماذج الثلاثة والأسس السبعة» — هذا المحرّك يقرأ
 * `contract_hour_policies` (وضعُ party_scope=operator · §15.2-ج) ويحسب مستحقَّ مشغّلِ يومٍ واحدٍ من واقعته:
 *
 *   المستحق = Σ لكل سياسةٍ منطبقة: (كمية أساسها × معدلها) مقصوصةً بحدَّيها
 *
 * كمياتُ الأسس من الواقعة نفسها:
 *   • actual / standby       ← ساعاتُ الحالة من سطور الزمن (§3.3)
 *   • attendance             ← يومُ حضورٍ واحدٌ إن وُجد أيُّ سطرِ زمن
 *   • ton / trip / meter     ← كميةُ وحدة العقد إن طابقت وحدةَ الأساس
 *   • composite              ← محجوزٌ بلا صيغةٍ بعد — يُتخطّى معلَنًا لا يُحسب
 *
 * الأخصّية عند تعدد سياسات الأساس الواحد: مشروعٌ محدد > نوعُ معدةٍ محدد >
 * افتراضية — الأخصُّ وحده يُحسب (لا جمعَ لسياستين على أساسٍ واحد).
 *
 * حكم §8.2 النصي: «الاستعدادُ والتوقفُ لا يتحولان وحداتِ إنتاجٍ أبدًا —
 * ويجوز احتسابهما للمشغّل وحده وفق عقده» — فالمشغّل قد يستحق عن ساعة استعدادٍ
 * لا يُفوتَر بها العميل، والعكس. سياسةُ كل طرفٍ مستقلة.
 *
 * قاعدة عدم التلفيق: لا سياسةَ منطبقة ⇒ النتيجة «لا حكم» معلَنةً — والمستدعي
 * (المروحة) يسقط إلى المسار القديم (fin_operator_pay) بقرار المالك:
 * «القديمان يبقيان والسياسةُ تغلبهما».
 *
 * عملة: صفوفُ السياسات المنطبقة يجب أن تتفق عملتُها — اختلافُها تعذّرٌ معلَن
 * (لا جمعَ جنيهٍ فوق دولار — القاعدة الراسخة في المروحة).
 */

namespace App\Services\Unit;

class OperatorDue
{
    /**
     * السياساتُ المنطبقة على مشغّلٍ في يومٍ وسياق — بعد حسم الأخصّية.
     *
     * @param array $ctx {employee_id, work_date, project_id?, equipment_type?, work_model?}
     * @return array صفوفُ سياساتٍ (صفٌّ لكل أساسٍ هو الأخصُّ لأساسه)
     */
    public static function applicablePolicies(\mysqli $conn, $companyId, array $ctx)
    {
        $companyId = (int) $companyId;
        $empId = (int) $ctx['employee_id'];
        $date = (string) $ctx['work_date'];
        $projectId = isset($ctx['project_id']) ? (int) $ctx['project_id'] : 0;
        $equipType = isset($ctx['equipment_type']) ? (int) $ctx['equipment_type'] : 0;
        $workModel = isset($ctx['work_model']) ? (string) $ctx['work_model'] : '';

        // ── المصدرُ الموحّد (UX-02 §15.2-ج · تصحيحُ 2026-07-27) ──
        // الجدولُ نفسُه يحمل وضعَين: حكمُ الساعة (client/supplier بـops_state)
        // وسياسةُ المشغّل (operator بـpay_basis) — ويميّزهما party_scope.
        // القيمُ بأسماء الوثيقة: pay_basis · scope_type/scope_id · rate.
        $st = $conn->prepare(
            "SELECT id, pay_basis AS basis, work_model, rate, min_amount, max_amount, currency,
                    CASE WHEN scope_type = 'project'    THEN scope_id END AS scope_project_id,
                    CASE WHEN scope_type = 'equip_type' THEN scope_id END AS scope_equipment_type,
                    is_trial
               FROM contract_hour_policies
              WHERE company_id=? AND party_scope='operator' AND operator_id=?
                AND COALESCE(is_deleted,0)=0 AND deleted_at IS NULL
                -- E-24 · UX-06 §8.2: **المسودةُ لا تسعّر شيئًا**. و`superseded`
                -- و`expired` تُقرآن عمدًا — القراءةُ بالتاريخ، و«أثرُ الماضي
                -- بحكم سياسته النافذة يومَها»؛ فإسقاطُهما يفقد حكمَ ما مضى.
                AND policy_state <> 'draft'
                AND (effective_from IS NULL OR effective_from <= ?)
                AND (effective_to   IS NULL OR effective_to   >= ?)");
        $st->bind_param('iiss', $companyId, $empId, $date, $date);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        // الترشيح بالنموذج والنطاق ثم حسم الأخصّية لكل أساس.
        // نموذجُ العمل يحدّد أسسَه (§8.2): سياسةُ «ساعة» لا تنطبق على واقعةِ
        // «طن» — وإلا تضاعف المستحق (ساعاتُ يوم الطن فوق أطنانه). خلٌل وقع
        // وقيس فعلًا (2026-07-26: 900 زيادةً على كل واقعة طن) فصار الترشيح نصًّا.
        $byBasis = array();
        foreach ($rows as $r) {
            if ($workModel !== '' && $r['work_model'] !== $workModel) { continue; }
            $pScope = $r['scope_project_id'] !== null ? (int) $r['scope_project_id'] : null;
            $eScope = $r['scope_equipment_type'] !== null ? (int) $r['scope_equipment_type'] : null;
            if ($pScope !== null && $pScope !== $projectId) { continue; }
            if ($eScope !== null && $eScope !== $equipType) { continue; }
            // درجة الأخصّية: مشروع=2 · نوع معدة=1 · افتراضية=0
            $spec = ($pScope !== null) ? 2 : (($eScope !== null) ? 1 : 0);
            $b = $r['basis'];
            if (!isset($byBasis[$b]) || $spec > $byBasis[$b]['spec']) {
                $r['spec'] = $spec;
                $byBasis[$b] = $r;
            }
        }
        return array_values($byBasis);
    }

    /**
     * حسابُ مستحقّ يومٍ واحد.
     *
     * @param array $ctx {
     *   employee_id, work_date, project_id?, equipment_type?,
     *   states: array<ops_state, hours>,         // توزيع زمن الوردية
     *   unit_type: hour|ton|meter|...|null,      // وحدة عقد الواقعة
     *   unit_qty: float,                          // كميتها
     * }
     * @return array {
     *   ok: bool,               // وُجد حكمٌ صادق (سياسةٌ منطبقةٌ واحدةٌ فأكثر حُسبت)
     *   amount: float, currency: ?string,
     *   lines: array,           // تفصيل كل سياسةٍ حُسبت (الشفافية أمام المراجع)
     *   skipped: array,         // ما تُخطّي معلَنًا (composite · عملة مختلفة)
     *   reason: ?string,        // سببُ اللاحكم حين ok=false
     *   is_trial: bool,         // فيه سياسةٌ تجريبيةٌ واحدةٌ على الأقل
     * }
     */
    public static function compute(\mysqli $conn, $companyId, array $ctx)
    {
        $out = array('ok' => false, 'amount' => 0.0, 'currency' => null,
                     'lines' => array(), 'skipped' => array(), 'reason' => null, 'is_trial' => false);

        if (empty($ctx['employee_id'])) {
            $out['reason'] = 'لا مشغل على الواقعة';
            return $out;
        }
        // نموذجُ عمل الواقعة من وحدة عقدها — يرشّح السياسات (§8.2)
        if (!isset($ctx['work_model']) || $ctx['work_model'] === '') {
            $modelMap = array('hour' => 'hour', 'ton' => 'ton', 'meter' => 'meter', 'trip' => 'trip');
            $ut = isset($ctx['unit_type']) ? (string) $ctx['unit_type'] : '';
            if (!isset($modelMap[$ut])) {
                $out['reason'] = 'نموذج عمل غير معروف لوحدة الواقعة «' . $ut . '» — لا حكم';
                return $out;
            }
            $ctx['work_model'] = $modelMap[$ut];
        }
        $policies = self::applicablePolicies($conn, $companyId, $ctx);
        if (empty($policies)) {
            $out['reason'] = 'لا سياسة منطبقة لهذا المشغل في هذا التاريخ والنطاق';
            return $out;
        }

        $states = isset($ctx['states']) && is_array($ctx['states']) ? $ctx['states'] : array();
        $unitType = isset($ctx['unit_type']) ? (string) $ctx['unit_type'] : '';
        $unitQty = isset($ctx['unit_qty']) ? (float) $ctx['unit_qty'] : 0.0;
        $anyPresence = false;
        foreach ($states as $h) { if ((float) $h > 0) { $anyPresence = true; break; } }

        foreach ($policies as $p) {
            $basis = $p['basis'];
            $qty = null;
            switch ($basis) {
                case 'actual':        // اسمُ الوثيقة (§15.2-ج) — والقديمُ actual_work
                case 'actual_work':
                    $qty = isset($states['actual_work']) ? (float) $states['actual_work'] : 0.0;
                    break;
                case 'standby':
                    $qty = isset($states['standby']) ? (float) $states['standby'] : 0.0;
                    break;
                case 'attendance':
                    $qty = $anyPresence ? 1.0 : 0.0; // يومُ حضورٍ واحد
                    break;
                case 'ton':
                case 'trip':
                case 'meter':
                    // وحدةُ الإنتاج تُحسب للمشغّل فقط إذا كانت وحدةَ عقد الواقعة
                    $qty = ($unitType === $basis) ? $unitQty : 0.0;
                    break;
                case 'composite':
                    $out['skipped'][] = array('policy_id' => (int) $p['id'],
                        'reason' => 'الأساس المركب بلا صيغة معتمدة بعد — لا يحسب ولا يلفق');
                    continue 2;
                default:
                    $out['skipped'][] = array('policy_id' => (int) $p['id'],
                        'reason' => 'أساس غير معروف: ' . $basis);
                    continue 2;
            }
            if ($qty <= 0) { continue; } // لا كميةَ لأساسه اليوم — صفرٌ صامتٌ مشروع

            // اتفاق العملة — لا جمعَ عملتين
            if ($out['currency'] !== null && $out['currency'] !== $p['currency']) {
                $out['skipped'][] = array('policy_id' => (int) $p['id'],
                    'reason' => "عملة السياسة {$p['currency']} تخالف {$out['currency']} — لا جمع عملتين");
                continue;
            }

            $amount = round($qty * (float) $p['rate'], 2);
            $clamped = null;
            if ($p['min_amount'] !== null && $amount < (float) $p['min_amount']) {
                $clamped = 'min'; $amount = (float) $p['min_amount'];
            }
            if ($p['max_amount'] !== null && $amount > (float) $p['max_amount']) {
                $clamped = 'max'; $amount = (float) $p['max_amount'];
            }

            $out['currency'] = $p['currency'];
            $out['amount'] = round($out['amount'] + $amount, 2);
            $out['is_trial'] = $out['is_trial'] || ((int) $p['is_trial'] === 1);
            $out['lines'][] = array(
                'policy_id' => (int) $p['id'], 'basis' => $basis,
                'qty' => round($qty, 2), 'rate' => (float) $p['rate'],
                'amount' => $amount, 'clamped' => $clamped,
                'scope' => $p['spec'] === 2 ? 'project' : ($p['spec'] === 1 ? 'equipment_type' : 'default'),
                'is_trial' => (int) $p['is_trial'] === 1,
            );
            $out['ok'] = true;
        }

        if (!$out['ok'] && $out['reason'] === null) {
            $out['reason'] = 'سياسات منطبقة لكن لا كمية لأي أساس منها في هذا اليوم';
        }
        return $out;
    }

    /**
     * سياقُ الحساب من واقعةِ السجل القانوني (unit_entries + unit_time_log).
     * القراءة بالمعرّف — يبني states من سطور الزمن مجمّعةً بالحالة.
     */
    public static function ctxFromUnitEntry(\mysqli $conn, $companyId, $entryId)
    {
        $companyId = (int) $companyId;
        $entryId = (int) $entryId;
        $st = $conn->prepare(
            "SELECT e.entry_date, e.project_id, e.operator_employee_id, e.unit_type, e.qty,
                    o.equipment_type
               FROM unit_entries e
               LEFT JOIN operations o ON o.equipment = e.equipment_id AND o.company_id = e.company_id
              WHERE e.company_id=? AND e.id=?
              ORDER BY o.id DESC LIMIT 1");
        $st->bind_param('ii', $companyId, $entryId);
        $st->execute();
        $e = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$e) { return null; }

        $st = $conn->prepare(
            "SELECT ops_state, COALESCE(SUM(hours),0) h FROM unit_time_log
              WHERE company_id=? AND entry_id=? GROUP BY ops_state");
        $st->bind_param('ii', $companyId, $entryId);
        $st->execute();
        $states = array();
        $res = $st->get_result();
        while ($r = $res->fetch_assoc()) { $states[$r['ops_state']] = (float) $r['h']; }
        $st->close();

        return array(
            'employee_id' => $e['operator_employee_id'] !== null ? (int) $e['operator_employee_id'] : 0,
            'work_date' => (string) $e['entry_date'],
            'project_id' => (int) $e['project_id'],
            'equipment_type' => $e['equipment_type'] !== null ? (int) $e['equipment_type'] : 0,
            'states' => $states,
            'unit_type' => (string) $e['unit_type'],
            'unit_qty' => (float) $e['qty'],
        );
    }
}
