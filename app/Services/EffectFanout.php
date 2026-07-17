<?php
/**
 * محرّك تفريع الأثر — EffectFanout (D05 §6.1 · الحدث الواحد والآثار المتعددة)
 * ───────────────────────────────────────────────────────────────────────────
 * «النشاط التشغيلي الواحد قد يولد عدة آثارٍ ماليةٍ مترابطةٍ في اللحظة نفسها»:
 * ساعةُ تشغيلٍ واحدةٌ معتمدة ⇒ إيرادُ العميل + مستحقُّ المورد + تكلفةُ المعدة
 * والمشروع + (مستحقُّ المشغّل ومخصّصُ الصيانة متى توفّرت مدخلاتهما).
 *
 * القواعد الأربع الحاكمة (§6.1) وكيف تُنفَّذ هنا:
 *   ① خريطة الآثار لكل مصدر — تصريحيةٌ في `fin_effect_map` (قواعدُ بياناتٍ لا
 *      كودًا): ما يتولّد، وفي أي جدول، وبأي معامل، ومتى يُعلن غير متاح.
 *   ② الذرّية (All-or-Nothing) — تُستدعى **داخل معاملة المستدعي** حصرًا (نمط
 *      EventPublisher): فشل أي أثرٍ يُرجع المروحة كلها. لا إيرادَ بلا توأمه.
 *   ③ الربط الأبوي الموثَّق — كل أثرٍ يكتب صفَّه في `fin_event_links`
 *      (المصدر ← الأثر ← جدوله ومعرّفه)، وهو **نفسه دفترُ العطالة**: وجود
 *      الصف يعني أن الأثر تولّد، فإعادة التشغيل لا تُكرّره (بلا أعمدة جديدة).
 *   ④ وحدةٌ واحدةٌ للمروحة — الكمية والوحدة تُقرآن من المصدر مرةً واحدةً
 *      وتُمرَّران كما هما لكل أثر؛ اختلافُ وحدةٍ خللٌ يوقف التوليد.
 *
 * ⚠️ قاعدة عدم التلفيق: أثرٌ لا تتوفّر مدخلاته (مشغّلٌ بلا مصدر تكليف، معدّلٌ
 * غير مضبوط) **لا يُخترع له رقم**: يُعلَن `skipped` بسببه ويُسجَّل في السجل —
 * فالفجوة معروفةٌ لا صامتة (مبدأ «لا حدود صامتة»).
 *
 * التبنّي (Adoption) — لا ازدواج مال: مروحةٌ سابقةٌ ولّدت توأميها بطريقٍ قديم
 * (revenue_event_id/supplier_due_id مختومان على الوحدة) تُتبنّى صفوفُها كما هي
 * ويُكتب لها رابطُها الأبوي، بدل إنشاء صفٍّ ثانٍ يضاعف المبلغ.
 *
 * الجذر المحايد (ADR-15): تُنشر حقيقةٌ واحدةٌ محايدة `operations.unit.approved`
 * تصف الوحدة المعتمدة — فيقرؤها أي مستهلكٍ مستقبليٍّ من الممر لا من دفتر المالية.
 */

namespace App\Services;

require_once __DIR__ . '/../Core/EventPublisher.php';

use App\Core\EventPublisher;

class EffectFanout
{
    /** أنواع المصادر المدعومة (تتوسّع بإضافة صفوفٍ للخريطة ودالة استخراج). */
    const SOURCE_UNIT_RECORD = 'unit_record';

    /** خريطة نموذج العمل → وحدة القياس اللاتينية ونوع المستحق (وحدةٌ واحدةٌ للمروحة). */
    const WORK_MODEL_UNIT = array('hour' => 'hour', 'ton' => 'ton', 'meter' => 'meter');
    const WORK_MODEL_DUE  = array('hour' => 'hours', 'ton' => 'tons', 'meter' => 'meters');

    /**
     * توليد مروحة أثر وحدةٍ معتمدة. تُستدعى **داخل معاملة المستدعي** حصرًا.
     *
     * @param \mysqli                 $conn  اتصال المعاملة (للناشر)
     * @param \App\Core\TenantDb      $gate  البوابة داخل المعاملة نفسها
     * @param array                   $unit  صفّ fin_unit_records كاملًا
     * @param int                     $actor المستخدم الفاعل
     * @return array{effects:array,skipped:array,adopted:array,fact_id:?int}
     * @throws \RuntimeException عند خرقٍ بنيويٍّ يوقف التوليد (قاعدة ④).
     */
    public static function forUnitRecord(\mysqli $conn, $gate, array $unit, $actor)
    {
        $unitId = intval($unit['id']);
        $company = intval($unit['company_id']);
        $model = strval($unit['work_model']);
        if (!isset(self::WORK_MODEL_UNIT[$model])) {
            throw new \RuntimeException('EffectFanout: نموذج عملٍ غير معروف: ' . $model);
        }
        // ④ وحدةٌ واحدةٌ للمروحة — تُقرأ مرةً وتُمرَّر كما هي
        $uom = self::WORK_MODEL_UNIT[$model];
        $qty = round((float) ($unit['approved_qty'] !== null ? $unit['approved_qty'] : $unit['ops_qty']), 4);
        if ($qty <= 0) {
            throw new \RuntimeException('EffectFanout: كميةٌ معتمدةٌ غير موجبة للوحدة #' . $unitId);
        }
        $clientPrice = ($unit['client_unit_price'] !== null) ? (float) $unit['client_unit_price'] : null;
        $supplierPrice = ($unit['supplier_unit_price'] !== null) ? (float) $unit['supplier_unit_price'] : null;
        $projectId = !empty($unit['project_id']) ? intval($unit['project_id']) : null;
        $equipmentId = !empty($unit['equipment_id']) ? intval($unit['equipment_id']) : null;
        $supplierId = !empty($unit['supplier_entity_id']) ? intval($unit['supplier_entity_id']) : null;
        $period = date('Y-m', strtotime($unit['record_date']));
        $recordNo = strval($unit['record_no']);

        $map = self::mapFor($gate, $company, self::SOURCE_UNIT_RECORD);
        $done = self::existingEffects($gate, $unitId);

        $out = array('effects' => array(), 'skipped' => array(), 'adopted' => array(), 'fact_id' => null);

        // ── ⓪ الحقيقة المحايدة: الوحدة اعتُمدت (ADR-15) — قبل إسقاطاتها ──
        $fact = EventPublisher::publishFact($conn, array(
            'event_key' => 'operations.unit.approved',
            'category' => 'operational',
            'source_module' => 'projects',
            'company_id' => $company,
            'entity_type' => 'fin_unit_record',
            'entity_id' => $unitId,
            'occurred_at' => gmdate('Y-m-d H:i:s', strtotime($unit['record_date'])),
            'created_by' => intval($actor) ?: 1,
            'idempotency_key' => 'fanout:unit:' . $unitId . ':fact',
            'quantity' => $qty,
            'unit' => $uom,
            'amount' => ($clientPrice !== null) ? round($qty * $clientPrice, 2) : 0.00,
            'currency' => 'SDG',
            'source_ref' => $recordNo,
            'project_id' => $projectId,
            'equipment_id' => $equipmentId,
            'supplier_entity_id' => $supplierId,
            'payload' => array(
                'record_no' => $recordNo, 'work_model' => $model, 'qty' => $qty, 'unit' => $uom,
                'client_unit_price' => $clientPrice, 'supplier_unit_price' => $supplierPrice,
            ),
        ));
        $out['fact_id'] = $fact ? intval($fact['id']) : null;

        foreach ($map as $eff) {
            $type = strval($eff['effect_type']);
            if (isset($done[$type])) {
                continue; // ③ العطالة: الأثر مولَّدٌ ومربوطٌ مسبقًا
            }
            if (intval($eff['is_active']) !== 1) {
                $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                    'reason' => strval($eff['unavailable_reason'] ?: 'معطّل في خريطة الآثار'));
                continue;
            }

            switch ($type) {
                case 'revenue_event':
                    if ($clientPrice === null) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                            'reason' => 'لا سعر عقدِ عميلٍ على الوحدة');
                        break;
                    }
                    // تبنٍّ: توأمٌ ولّده المسار القديم — يُربط ولا يُضاعف
                    if (!empty($unit['revenue_event_id'])) {
                        self::link($gate, $company, $unitId, $type, 'fin_financial_events',
                            intval($unit['revenue_event_id']), intval($unit['revenue_event_id']));
                        $out['adopted'][] = array('effect' => $type, 'target_id' => intval($unit['revenue_event_id']));
                        break;
                    }
                    $res = EventPublisher::publish($conn, array(
                        'event_key' => 'revenue.unit.recognized',
                        'category' => 'financial',
                        'source_module' => 'projects',
                        'company_id' => $company,
                        'entity_type' => 'fin_unit_record',
                        'entity_id' => $unitId,
                        'occurred_at' => gmdate('Y-m-d H:i:s', strtotime($unit['record_date'])),
                        'created_by' => intval($actor) ?: 1,
                        'idempotency_key' => 'fanout:unit:' . $unitId . ':revenue',
                        'legacy_event_type' => 'revenue',
                        'amount' => round($qty * $clientPrice, 2),
                        'currency' => 'SDG',
                        'quantity' => $qty, 'unit' => $uom,
                        'source_ref' => $recordNo,
                        'project_id' => $projectId, 'equipment_id' => $equipmentId,
                        'notes' => 'مروحة أثر الوحدة ' . $recordNo,
                        'payload' => array('record_no' => $recordNo, 'unit_price' => $clientPrice, 'qty' => $qty, 'unit' => $uom),
                    ));
                    $gate->update('fin_unit_records', array('revenue_event_id' => intval($res['id'])), array('id' => $unitId));
                    self::link($gate, $company, $unitId, $type, 'fin_financial_events', intval($res['id']), intval($res['id']));
                    $out['effects'][] = array('effect' => $type, 'target_id' => intval($res['id']), 'amount' => round($qty * $clientPrice, 2));
                    break;

                case 'supplier_due':
                    if ($supplierId === null || $supplierPrice === null) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                            'reason' => 'لا شريك إنتاجٍ أو لا سعر عقدِ مورد على الوحدة');
                        break;
                    }
                    if (!empty($unit['supplier_due_id'])) {
                        self::link($gate, $company, $unitId, $type, 'fin_dues',
                            intval($unit['supplier_due_id']), !empty($unit['revenue_event_id']) ? intval($unit['revenue_event_id']) : null);
                        $out['adopted'][] = array('effect' => $type, 'target_id' => intval($unit['supplier_due_id']));
                        break;
                    }
                    $dueId = intval($gate->insert('fin_dues', array(
                        'party_type' => 'supplier', 'party_ref' => $supplierId,
                        'due_type' => self::WORK_MODEL_DUE[$model], 'direction' => 'credit',
                        'amount' => round($qty * $supplierPrice, 2), 'currency' => 'SDG',
                        'period_ref' => $period,
                        'event_id' => !empty($unit['revenue_event_id']) ? intval($unit['revenue_event_id']) : null,
                        'created_by' => intval($actor) ?: null,
                    )));
                    $gate->update('fin_unit_records', array('supplier_due_id' => $dueId), array('id' => $unitId));
                    self::link($gate, $company, $unitId, $type, 'fin_dues', $dueId,
                        !empty($unit['revenue_event_id']) ? intval($unit['revenue_event_id']) : null);
                    $out['effects'][] = array('effect' => $type, 'target_id' => $dueId, 'amount' => round($qty * $supplierPrice, 2));
                    break;

                case 'cost_record':
                    if ($supplierPrice === null) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                            'reason' => 'لا سعر تكلفةٍ (سعر عقد المورد) على الوحدة');
                        break;
                    }
                    // تكلفة الوحدة = ما ندفعه للمورد؛ وإيرادها = ما نقبضه من العميل
                    // فيحسب العمود المولَّد profit ربح الوحدة في القاعدة نفسها.
                    $totalCost = round($qty * $supplierPrice, 2);
                    $revenue = ($clientPrice !== null) ? round($qty * $clientPrice, 2) : null;
                    $costId = intval($gate->insert('fin_cost_records', array(
                        'cost_type' => $equipmentId ? 'equipment' : 'project',
                        'equipment_id' => $equipmentId, 'project_id' => $projectId,
                        'period_ref' => $period, 'qty' => $qty, 'unit' => $uom,
                        'unit_cost' => $supplierPrice, 'total_cost' => $totalCost, 'revenue' => $revenue,
                        'event_id' => !empty($unit['revenue_event_id']) ? intval($unit['revenue_event_id']) : null,
                        'created_by' => intval($actor) ?: null,
                    )));
                    self::link($gate, $company, $unitId, $type, 'fin_cost_records', $costId,
                        !empty($unit['revenue_event_id']) ? intval($unit['revenue_event_id']) : null);
                    $out['effects'][] = array('effect' => $type, 'target_id' => $costId, 'amount' => $totalCost);
                    break;

                case 'employee_due':
                    // بنيويًّا غير متاح اليوم — الخريطة تحمل سببه ولا يُلفَّق رقم.
                    $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                        'reason' => strval($eff['unavailable_reason'] ?: 'لا مصدر تكليفِ مشغّل'));
                    break;

                case 'metric_update': // مخصّص الصيانة المحمَّل على المعدة (معامله في الخريطة)
                    $rate = ($eff['param_value'] !== null) ? (float) $eff['param_value'] : 0.0;
                    if ($rate <= 0 || $equipmentId === null) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                            'reason' => $rate <= 0 ? 'معدّل المخصّص غير مضبوط (param_value)' : 'لا معدةَ على الوحدة');
                        break;
                    }
                    $prov = round($qty * $rate, 2);
                    $mid = intval($gate->insert('fin_cost_records', array(
                        'cost_type' => 'maintenance', 'equipment_id' => $equipmentId, 'project_id' => $projectId,
                        'period_ref' => $period, 'qty' => $qty, 'unit' => $uom,
                        'unit_cost' => $rate, 'total_cost' => $prov,
                        'event_id' => !empty($unit['revenue_event_id']) ? intval($unit['revenue_event_id']) : null,
                        'created_by' => intval($actor) ?: null,
                    )));
                    self::link($gate, $company, $unitId, $type, 'fin_cost_records', $mid,
                        !empty($unit['revenue_event_id']) ? intval($unit['revenue_event_id']) : null);
                    $out['effects'][] = array('effect' => $type, 'target_id' => $mid, 'amount' => $prov);
                    break;

                default:
                    $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                        'reason' => 'لا مولّدَ مسجَّلٌ لهذا النوع في المحرّك');
            }
        }
        return $out;
    }

    /** خريطة الآثار المُعرَّفة لمصدرٍ في شركة (مرتَّبةً للعرض). */
    public static function mapFor($gate, $company_id, $source_kind)
    {
        try {
            return $gate->select('fin_effect_map', array(
                'where' => array('source_kind' => strval($source_kind)),
                'orderBy' => 'display_order ASC, id ASC',
            ));
        } catch (\Throwable $t) {
            return array();
        }
    }

    /** الآثار المولَّدة سلفًا لمصدرٍ — دفترُ العطالة نفسه (fin_event_links). */
    public static function existingEffects($gate, $parent_ref, $parent_kind = self::SOURCE_UNIT_RECORD)
    {
        $out = array();
        try {
            foreach ($gate->select('fin_event_links', array(
                'where' => array('parent_kind' => $parent_kind, 'parent_ref' => intval($parent_ref)),
            )) as $l) {
                $out[strval($l['effect_type'])] = $l;
            }
        } catch (\Throwable $t) { /* لا روابط = لا آثار */ }
        return $out;
    }

    /** الرابط الأبوي الموثَّق: المصدر ← الأثر ← جدوله ومعرّفه (§6.1 ③). */
    private static function link($gate, $company_id, $parent_ref, $effect_type, $target_table, $target_id, $event_id = null)
    {
        $gate->insert('fin_event_links', array(
            'parent_kind' => self::SOURCE_UNIT_RECORD,
            'parent_ref' => intval($parent_ref),
            'effect_type' => strval($effect_type),
            'target_table' => strval($target_table),
            'target_id' => intval($target_id),
            'event_id' => $event_id !== null ? intval($event_id) : null,
        ));
    }
}
