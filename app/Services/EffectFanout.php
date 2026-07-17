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
    private static function link($gate, $company_id, $parent_ref, $effect_type, $target_table, $target_id, $event_id = null, $parent_kind = self::SOURCE_UNIT_RECORD)
    {
        $gate->insert('fin_event_links', array(
            'parent_kind' => strval($parent_kind),
            'parent_ref' => intval($parent_ref),
            'effect_type' => strval($effect_type),
            'target_table' => strval($target_table),
            'target_id' => intval($target_id),
            'event_id' => $event_id !== null ? intval($event_id) : null,
        ));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // D02 م1-①: المروحة من سجلّ الدوام (timesheet) — «كل تجميعٍ لاحقٍ قراءةٌ
    // مشتقةٌ من سجلّ الوحدات اليومي». صفُّ الدوام ببلوغه الاعتمادَ الرابع يولّد
    // مروحته بنفس قواعد المحرّك الأربع، وبقرارَي التسعير المُقرَّين:
    //   • السعر من سطر معدة العقد والعملة من بيانات العقد الأساسية — يُكتب
    //     الأثر بعملة عقده كما وقعت (لا تحويل ولا سعر صرفٍ مخترَع).
    //   • عقد المورد: الساري بتاريخ العمل ← وإلا النشطُ الوحيد ← وإلا تعذّرٌ
    //     معلن (عقود المورد الواحد بأسعارٍ مختلفة لا تُخمَّن).
    //   • وحدة الفوترة تُطابَق المسجَّل: عقدٌ بالمتر وصفٌّ سجّل ساعاتٍ فقط
    //     ⇒ لا تسعير ملفَّق — تعذّرٌ معلَنٌ يكشف فجوة البيانات لأصحابها.
    // ═══════════════════════════════════════════════════════════════════════

    const SOURCE_TIMESHEET = 'timesheet';

    /** عملة العقد كما في بياناته الأساسية → رمزها. غير المعروف = تعذّر معلن. */
    const CONTRACT_CURRENCY = array('جنيه' => 'SDG', 'دولار' => 'USD', 'يورو' => 'EUR', 'ريال' => 'SAR');

    /** تسمية وحدة الفوترة في سطر العقد → وحدة المحرّك. الفارغ = ساعة (افتراض العمود نفسه). */
    const CONTRACT_UNIT = array('ساعة' => 'hour', 'متر طولي' => 'meter', 'متر' => 'meter', 'طن' => 'ton', '' => 'hour');

    /**
     * مترجم الدوام: صفُّ timesheet → سياقُ وحدةٍ جاهزٌ للمروحة.
     * قراءةٌ خالصة (لا كتابة): يشتقّ الكمية والوحدة من المسجَّل فعلًا، ويحلّ
     * السعرين من العقدين بعملتيهما، ويعلن كلَّ متعذّرٍ بسببه — لا تخمين.
     */
    public static function resolveTimesheet(\mysqli $conn, $tsId)
    {
        $tsId = intval($tsId);
        // سطر معدة العقد الحاسم: MIN(id) عند تعدد أسطرٍ لنفس النوع (حتمية)
        $sql = "SELECT t.id, t.company_id, t.`date` AS work_date,
                       t.executed_hours, t.tons_count, t.meters_count, t.operator_hours,
                       o.id AS op_id, o.project_id, o.contract_id, o.equipment_type,
                       e.id AS equipment_id, e.suppliers AS supplier_id,
                       emp.id AS employee_id,
                       c.price_currency_contract AS client_cur_label,
                       ce.equip_price AS client_price, ce.equip_unit AS client_unit_label
                FROM timesheet t
                LEFT JOIN operations o ON o.id = t.operator
                LEFT JOIN equipments e ON e.id = o.equipment
                LEFT JOIN employees emp ON emp.id = t.employee_id
                LEFT JOIN contracts c ON c.id = o.contract_id
                LEFT JOIN contractequipments ce ON ce.id = (
                    SELECT MIN(x.id) FROM contractequipments x
                    WHERE x.contract_id = o.contract_id AND x.equip_type = o.equipment_type)
                WHERE t.id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { throw new \RuntimeException('resolveTimesheet prepare: ' . $conn->error); }
        $stmt->bind_param('i', $tsId);
        $stmt->execute();
        $t = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$t) { return null; }

        // ── الكمية والوحدة من المسجَّل فعلًا (لا من افتراض «ساعات») ──
        $unit = null; $qty = 0.0;
        if ((float) $t['executed_hours'] > 0)      { $unit = 'hour';  $qty = (float) $t['executed_hours']; }
        elseif ((float) $t['meters_count'] > 0)    { $unit = 'meter'; $qty = (float) $t['meters_count']; }
        elseif ((float) $t['tons_count'] > 0)      { $unit = 'ton';   $qty = (float) $t['tons_count']; }

        $ctx = array(
            'id' => $tsId,
            'company_id' => intval($t['company_id']),
            'work_date' => strval($t['work_date']),
            'period' => date('Y-m', strtotime($t['work_date'])),
            'source_ref' => 'TS-' . $tsId,
            'unit' => $unit, 'qty' => round($qty, 2),
            'operator_hours' => (float) $t['operator_hours'],
            'project_id' => !empty($t['project_id']) ? intval($t['project_id']) : null,
            'equipment_id' => $t['equipment_id'] !== null ? intval($t['equipment_id']) : null,
            'employee_id' => $t['employee_id'] !== null ? intval($t['employee_id']) : null,
            'supplier_id' => !empty($t['supplier_id']) ? intval($t['supplier_id']) : null,
            'client' => array('ok' => false, 'reason' => '', 'price' => null, 'currency' => null,
                              'currency_label' => (string) $t['client_cur_label'], 'contract_id' => !empty($t['contract_id']) ? intval($t['contract_id']) : null,
                              'unit_label' => trim((string) $t['client_unit_label'])),
            'supplier' => array('ok' => false, 'reason' => '', 'price' => null, 'currency' => null,
                                'currency_label' => '', 'contract_id' => null, 'unit_label' => '', 'resolved_by' => null),
        );

        // ── جانب العميل: سعرٌ موجب + وحدة فوترةٍ تطابق المسجَّل + عملةٌ معروفة ──
        $cl = &$ctx['client'];
        if ($t['op_id'] === null)                       { $cl['reason'] = 'لا تشغيلَ مربوطًا بصف الدوام'; }
        elseif ($cl['contract_id'] === null)            { $cl['reason'] = 'لا عقدَ عميلٍ على التشغيل'; }
        elseif ($t['client_price'] === null || (float) $t['client_price'] <= 0) { $cl['reason'] = 'لا سعر وحدةٍ لنوع المعدة في عقد العميل'; }
        elseif ($unit === null)                         { $cl['reason'] = 'لا كميةَ مسجّلةً موجبة على الصف'; }
        elseif (!isset(self::CONTRACT_UNIT[$cl['unit_label']]) ) { $cl['reason'] = 'وحدة فوترةٍ غير معروفة في عقد العميل: ' . $cl['unit_label']; }
        elseif (self::CONTRACT_UNIT[$cl['unit_label']] !== $unit) {
            $cl['reason'] = 'عقد العميل يفوتر بـ«' . ($cl['unit_label'] !== '' ? $cl['unit_label'] : 'ساعة') . '» والمسجَّل ' . $unit . ' — لا تسعير ملفَّق';
        }
        elseif (!isset(self::CONTRACT_CURRENCY[trim($cl['currency_label'])])) { $cl['reason'] = 'عملة عقد العميل غير معروفة: ' . $cl['currency_label']; }
        else {
            $cl['ok'] = true;
            $cl['price'] = (float) $t['client_price'];
            $cl['currency'] = self::CONTRACT_CURRENCY[trim($cl['currency_label'])];
        }
        unset($cl);

        // ── جانب المورد: سلّم الحسم — الساري بالتاريخ ← النشط الوحيد ← تعذّر ──
        $sp = &$ctx['supplier'];
        if ($ctx['supplier_id'] === null || $t['op_id'] === null) { $sp['reason'] = 'لا موردَ على معدة التشغيل'; }
        elseif ($unit === null) { $sp['reason'] = 'لا كميةَ مسجّلةً موجبة على الصف'; }
        else {
            $sq = $conn->prepare(
                "SELECT sc.id, sc.price_currency_contract AS cur_label, sce.equip_price, sce.equip_unit,
                        (sc.actual_start IS NOT NULL AND sc.actual_start <= ?
                         AND (sc.actual_end IS NULL OR sc.actual_end >= ?)) AS in_force
                 FROM supplierscontracts sc
                 JOIN suppliercontractequipments sce ON sce.contract_id = sc.id
                 WHERE sc.supplier_id = ? AND sc.status = 1
                   AND sce.equip_type = ? AND sce.equip_price > 0
                 ORDER BY sc.id ASC");
            if (!$sq) { throw new \RuntimeException('resolveTimesheet supplier prepare: ' . $conn->error); }
            $etype = strval($t['equipment_type']);
            $sq->bind_param('ssis', $ctx['work_date'], $ctx['work_date'], $ctx['supplier_id'], $etype);
            $sq->execute();
            $cands = $sq->get_result()->fetch_all(MYSQLI_ASSOC);
            $sq->close();
            $inForce = array();
            foreach ($cands as $cand) { if (intval($cand['in_force']) === 1) { $inForce[] = $cand; } }
            $pick = null;
            if (count($inForce) === 1)      { $pick = $inForce[0]; $sp['resolved_by'] = 'in_force_at_date'; }
            elseif (count($inForce) > 1)    { $sp['reason'] = 'أكثر من عقد موردٍ سارٍ بتاريخ العمل — يلزم حسمٌ يدوي'; }
            elseif (count($cands) === 1)    { $pick = $cands[0]; $sp['resolved_by'] = 'single_active'; }
            elseif (count($cands) > 1)      { $sp['reason'] = 'عقودُ موردٍ متعددةٌ ولا ساريَ بتاريخ العمل — لا يُخمَّن سعر'; }
            else                            { $sp['reason'] = 'لا سعر وحدةٍ لنوع المعدة في أي عقد موردٍ نشط'; }
            if ($pick !== null) {
                $sp['contract_id'] = intval($pick['id']);
                $sp['unit_label'] = trim((string) $pick['equip_unit']);
                $sp['currency_label'] = (string) $pick['cur_label'];
                if (!isset(self::CONTRACT_UNIT[$sp['unit_label']])) { $sp['reason'] = 'وحدة فوترةٍ غير معروفة في عقد المورد: ' . $sp['unit_label']; }
                elseif (self::CONTRACT_UNIT[$sp['unit_label']] !== $unit) {
                    $sp['reason'] = 'عقد المورد يفوتر بـ«' . ($sp['unit_label'] !== '' ? $sp['unit_label'] : 'ساعة') . '» والمسجَّل ' . $unit . ' — لا تسعير ملفَّق';
                }
                elseif (!isset(self::CONTRACT_CURRENCY[trim($sp['currency_label'])])) { $sp['reason'] = 'عملة عقد المورد غير معروفة: ' . $sp['currency_label']; }
                else {
                    $sp['ok'] = true;
                    $sp['price'] = (float) $pick['equip_price'];
                    $sp['currency'] = self::CONTRACT_CURRENCY[trim($sp['currency_label'])];
                }
            }
        }
        unset($sp);
        return $ctx;
    }

    /**
     * مروحة يوم الدوام المعتمد — بنفس قواعد forUnitRecord الأربع + قرارَي
     * التسعير. تُستدعى داخل معاملة البوابة (خطّاف الاعتماد الرابع أو كنس cron).
     * لا حقيقة جذرٍ جديدة هنا: equipment.hour_logged منشورٌ سلفًا من الخطّاف.
     */
    public static function forTimesheetId(\mysqli $conn, $gate, $tsId, $actor)
    {
        $ctx = self::resolveTimesheet($conn, $tsId);
        if ($ctx === null) { throw new \RuntimeException('EffectFanout: صفّ دوامٍ غير موجود #' . intval($tsId)); }
        $tsId = intval($ctx['id']);
        $company = $ctx['company_id'];
        $map = self::mapFor($gate, $company, self::SOURCE_TIMESHEET);
        $done = self::existingEffects($gate, $tsId, self::SOURCE_TIMESHEET);
        $out = array('effects' => array(), 'skipped' => array(), 'adopted' => array(), 'revision_pending' => false, 'ctx' => $ctx);

        // ── حارس المراجعة: أثرٌ قائمٌ وكمية اليوم تغيّرت بعده ⇒ لا كتابة —
        //    التصحيح بعد التوليد يخصّ محرّك العكسيات (D02 §8، مرحلة لاحقة). ──
        if (isset($done['revenue_event'])) {
            $revLink = $done['revenue_event'];
            $old = null;
            try { $old = $gate->selectOne('fin_financial_events', array('where' => array('id' => intval($revLink['target_id'])), 'includeDeleted' => true)); }
            catch (\Throwable $t) { /* قراءة حارسٍ فقط */ }
            if ($old && round((float) $old['quantity'], 2) !== round((float) $ctx['qty'], 2)) {
                $out['revision_pending'] = true;
                $out['skipped'][] = array('effect' => 'revenue_event', 'label' => 'مراجعة كمية',
                    'reason' => 'الكمية تغيّرت بعد توليد المروحة (' . $old['quantity'] . ' ← ' . $ctx['qty'] . ') — التصحيح لمحرّك العكسيات لا للتوليد');
                if (function_exists('log_security_event')) {
                    log_security_event('FANOUT_REVISION_PENDING', 'timesheet=' . $tsId
                        . ' old_qty=' . $old['quantity'] . ' new_qty=' . $ctx['qty']);
                }
                return $out;
            }
        }

        $revId = isset($done['revenue_event']) ? intval($done['revenue_event']['target_id']) : null;
        foreach ($map as $eff) {
            $type = strval($eff['effect_type']);
            if (isset($done[$type])) { continue; } // ③ العطالة
            if (intval($eff['is_active']) !== 1) {
                $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                    'reason' => strval($eff['unavailable_reason'] ?: 'معطّل في خريطة الآثار'));
                continue;
            }

            switch ($type) {
                case 'revenue_event':
                    if (!$ctx['client']['ok']) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'], 'reason' => $ctx['client']['reason']);
                        break;
                    }
                    $amount = round($ctx['qty'] * $ctx['client']['price'], 2);
                    $res = EventPublisher::publish($conn, array(
                        'event_key' => 'revenue.unit.recognized',
                        'category' => 'financial',
                        'source_module' => 'movement',
                        'company_id' => $company,
                        'entity_type' => 'timesheet',
                        'entity_id' => $tsId,
                        'occurred_at' => $ctx['work_date'] . ' 00:00:00',
                        'created_by' => intval($actor) ?: 1,
                        'idempotency_key' => 'fanout:ts:' . $tsId . ':revenue',
                        'legacy_event_type' => 'revenue',
                        'amount' => $amount,
                        'currency' => $ctx['client']['currency'],
                        'quantity' => $ctx['qty'], 'unit' => $ctx['unit'],
                        'source_ref' => $ctx['source_ref'],
                        'project_id' => $ctx['project_id'], 'equipment_id' => $ctx['equipment_id'],
                        'supplier_entity_id' => $ctx['supplier_id'],
                        'operator_employee_id' => $ctx['employee_id'],
                        'notes' => 'مروحة أثر يوم الدوام ' . $ctx['source_ref'],
                        'payload' => array( // لقطة التسعير لحظة التوليد — لا يتغيّر أثرٌ بتغيّر عقدٍ لاحق
                            'source' => 'timesheet', 'work_date' => $ctx['work_date'],
                            'qty' => $ctx['qty'], 'unit' => $ctx['unit'],
                            'client_contract_id' => $ctx['client']['contract_id'],
                            'unit_price' => $ctx['client']['price'],
                            'currency' => $ctx['client']['currency'], 'currency_label' => $ctx['client']['currency_label'],
                        ),
                    ));
                    $revId = intval($res['id']);
                    self::link($gate, $company, $tsId, $type, 'fin_financial_events', $revId, $revId, self::SOURCE_TIMESHEET);
                    $out['effects'][] = array('effect' => $type, 'target_id' => $revId, 'amount' => $amount, 'currency' => $ctx['client']['currency']);
                    break;

                case 'supplier_due':
                    if (!$ctx['supplier']['ok']) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'], 'reason' => $ctx['supplier']['reason']);
                        break;
                    }
                    $amount = round($ctx['qty'] * $ctx['supplier']['price'], 2);
                    $dueId = intval($gate->insert('fin_dues', array(
                        'party_type' => 'supplier', 'party_ref' => $ctx['supplier_id'],
                        'due_type' => self::WORK_MODEL_DUE[$ctx['unit']], 'direction' => 'credit',
                        'amount' => $amount, 'currency' => $ctx['supplier']['currency'],
                        'period_ref' => $ctx['period'],
                        'event_id' => $revId,
                        'created_by' => intval($actor) ?: null,
                    )));
                    self::link($gate, $company, $tsId, $type, 'fin_dues', $dueId, $revId, self::SOURCE_TIMESHEET);
                    $out['effects'][] = array('effect' => $type, 'target_id' => $dueId, 'amount' => $amount, 'currency' => $ctx['supplier']['currency']);
                    break;

                case 'cost_record':
                    if (!$ctx['supplier']['ok']) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                            'reason' => 'لا سعر تكلفةٍ: ' . $ctx['supplier']['reason']);
                        break;
                    }
                    $totalCost = round($ctx['qty'] * $ctx['supplier']['price'], 2);
                    // الإيراد داخل سجلّ التكلفة (للربحية) بشرط اتحاد العملة — لا جمع عملتين
                    $revenue = ($ctx['client']['ok'] && $ctx['client']['currency'] === $ctx['supplier']['currency'])
                        ? round($ctx['qty'] * $ctx['client']['price'], 2) : null;
                    $costId = intval($gate->insert('fin_cost_records', array(
                        'cost_type' => $ctx['equipment_id'] ? 'equipment' : 'project',
                        'equipment_id' => $ctx['equipment_id'], 'project_id' => $ctx['project_id'],
                        'period_ref' => $ctx['period'], 'qty' => $ctx['qty'], 'unit' => $ctx['unit'],
                        'unit_cost' => $ctx['supplier']['price'], 'total_cost' => $totalCost,
                        'currency' => $ctx['supplier']['currency'],
                        'revenue' => $revenue,
                        'event_id' => $revId,
                        'created_by' => intval($actor) ?: null,
                    )));
                    self::link($gate, $company, $tsId, $type, 'fin_cost_records', $costId, $revId, self::SOURCE_TIMESHEET);
                    $out['effects'][] = array('effect' => $type, 'target_id' => $costId, 'amount' => $totalCost, 'currency' => $ctx['supplier']['currency']);
                    break;

                case 'employee_due':
                case 'metric_update':
                    // معطّلان في الخريطة اليوم (لا قاعدة أجرٍ مُقرّة / لا معدّل مخصّص) —
                    // بلوغ هذا الفرع يعني تفعيلًا بلا مولّد دوامٍ بعد: إعلانٌ لا تلفيق.
                    $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                        'reason' => strval($eff['unavailable_reason'] ?: 'لا مولّدَ لهذا الأثر من الدوام بعد'));
                    break;

                default:
                    $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                        'reason' => 'لا مولّدَ مسجَّلٌ لهذا النوع في المحرّك');
            }
        }
        return $out;
    }
}
