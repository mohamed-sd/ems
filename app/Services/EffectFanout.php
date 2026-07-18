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

    /** تسمية وحدة الفوترة في سطر العقد → وحدة المحرّك. الفارغ = تعذّرٌ معلَن (ح-11):
     *  المفتاح '' حُذف — كان يفوتر الوحدةَ الفارغة بالساعة صامتًا، مناقضًا مبدأ
     *  «لا تسعير ملفَّق» أدناه. الفراغ الآن يسقط إلى null ⇒ «وحدة فوترةٍ غير معروفة». */
    const CONTRACT_UNIT = array('ساعة' => 'hour', 'متر طولي' => 'meter', 'متر' => 'meter', 'طن' => 'ton');

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
                       t.standby_hours, t.dependence_hours, t.maintenance_fault, t.hr_fault,
                       t.marketing_fault, t.approval_fault, t.other_fault_hours,
                       t.ts_supplier_stop_hours, t.ts_planned_stop_hours, t.ts_force_majeure_hours,
                       o.id AS op_id, o.project_id, o.contract_id, o.equipment_type,
                       e.id AS equipment_id, e.suppliers AS supplier_id,
                       emp.id AS employee_id,
                       c.price_currency_contract AS client_cur_label,
                       ce.equip_price AS client_price, ce.equip_unit AS client_unit_label,
                       ce.equip_price_currency AS client_line_cur_label
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

        // ── الكميات المسجَّلة كلُّها (D02 §2.6): الواقعة تحمل ما سُجّل، ولكلِّ
        //    طرفٍ أن يقرأ منها ما يوافق **وحدة عقده هو**. لا سلّمَ أولويةٍ يفرض
        //    وحدةً واحدةً على الجميع — فالحالة الغالبة في البيانات (184 صفًّا)
        //    أن العميل يفوتر بالمتر والمورد يُدفع بالساعة في الواقعة نفسها.
        $recorded = array(
            'hour'  => (float) $t['executed_hours'],
            'ton'   => (float) $t['tons_count'],
            'meter' => (float) $t['meters_count'],
        );

        $ctx = array(
            'id' => $tsId,
            'company_id' => intval($t['company_id']),
            'work_date' => strval($t['work_date']),
            'period' => date('Y-m', strtotime($t['work_date'])),
            'source_ref' => 'TS-' . $tsId,
            'recorded' => $recorded,
            // توزيع زمن الوردية على حالاتها (D02 §3.5) — مدخل سياسة الاستحقاق
            // لعقود الساعة. الأعمدة القائمة **هي بالفعل** توزيعُ ساعاتٍ على
            // حالاتٍ بمسؤولياتها، فلا يلزم سجلُّ فتراتٍ منفصلٌ لحساب المال.
            'states' => array(
                'actual_work'         => (float) $t['executed_hours'],
                'standby'             => (float) $t['standby_hours'],       // «الاستعداد بسبب العميل» في الشاشة
                'pending_approval'    => (float) $t['dependence_hours'] + (float) $t['approval_fault'],
                'tech_breakdown'      => (float) $t['maintenance_fault'],
                'operator_stop'       => (float) $t['hr_fault'],
                'client_stop'         => (float) $t['marketing_fault'],     // «عطل تسويق» = توقفٌ سببه العميل
                'supplier_stop'       => (float) $t['ts_supplier_stop_hours'],
                'planned_stop'        => (float) $t['ts_planned_stop_hours'],
                'force_majeure'       => (float) $t['ts_force_majeure_hours'],
                'fuel_logistics_stop' => 0.0,                                // لا عمودَ له بعد
                'other'               => (float) $t['other_fault_hours'],
            ),
            // ⚠️ unit/qty الموروثان = **حكم العميل** (فالإيراد هو ما يقيسه حارس
            //    المراجعة في fin_financial_events.quantity). لا تستعملهما لطرفٍ
            //    آخر: مستحق المورد وتكلفته يقرآن من $ctx['supplier'] حصرًا.
            'unit' => null, 'qty' => 0.0,
            'operator_hours' => (float) $t['operator_hours'],
            'project_id' => !empty($t['project_id']) ? intval($t['project_id']) : null,
            'equipment_id' => $t['equipment_id'] !== null ? intval($t['equipment_id']) : null,
            'employee_id' => $t['employee_id'] !== null ? intval($t['employee_id']) : null,
            'supplier_id' => !empty($t['supplier_id']) ? intval($t['supplier_id']) : null,
            // كل طرفٍ يحمل وحدتَه وكميتَه المستقلتين (unit/qty) — لا رقمَ مشتركًا
            // عملة الطرف: عملة سطر المعدة إن سُجّلت، وإلا ارتدادٌ لعملة رأس العقد (ح-4).
            // شاشتا الإدخال تطلبان عملةً لكل سطر وتعرضانها؛ إهمالُها كان يحجز العقد
            // #2 بـSDG (الرأس «جنيه») والسطرُ «دولار» — فرقُ سعر الصرف على كل وحدة.
            'client' => array('ok' => false, 'reason' => '', 'price' => null, 'currency' => null,
                              'unit' => null, 'qty' => 0.0,
                              'currency_label' => (trim((string) $t['client_line_cur_label']) !== '')
                                                  ? trim((string) $t['client_line_cur_label'])
                                                  : (string) $t['client_cur_label'],
                              'contract_id' => !empty($t['contract_id']) ? intval($t['contract_id']) : null,
                              'unit_label' => trim((string) $t['client_unit_label'])),
            'supplier' => array('ok' => false, 'reason' => '', 'price' => null, 'currency' => null,
                                'unit' => null, 'qty' => 0.0,
                                'currency_label' => '', 'contract_id' => null, 'unit_label' => '', 'resolved_by' => null),
        );

        // ── جانب العميل: وحدةُ عقده تختار العمود المقروء، ثم سعرٌ موجبٌ وعملةٌ معروفة ──
        $cl = &$ctx['client'];
        $clUnit = isset(self::CONTRACT_UNIT[$cl['unit_label']]) ? self::CONTRACT_UNIT[$cl['unit_label']] : null;
        if ($t['op_id'] === null)                       { $cl['reason'] = 'لا تشغيلَ مربوطًا بصف الدوام'; }
        elseif ($cl['contract_id'] === null)            { $cl['reason'] = 'لا عقدَ عميلٍ على التشغيل'; }
        elseif ($t['client_price'] === null || (float) $t['client_price'] <= 0) { $cl['reason'] = 'لا سعر وحدةٍ لنوع المعدة في عقد العميل'; }
        elseif ($clUnit === null)                       { $cl['reason'] = 'وحدة فوترةٍ غير معروفة في عقد العميل: ' . $cl['unit_label']; }
        elseif (!isset($recorded[$clUnit]))             { $cl['reason'] = 'وحدة عقد العميل «' . $cl['unit_label'] . '» لا يسجّلها سجلّ الدوام بعد'; }
        elseif ($recorded[$clUnit] <= 0) {
            // ⚠️ الوحدة مطابقةٌ لكن خانتها فارغة: تعذّرٌ معلَنٌ لا اشتقاقٌ من عمودٍ آخر.
            $cl['reason'] = 'عقد العميل يفوتر بـ«' . ($cl['unit_label'] !== '' ? $cl['unit_label'] : 'ساعة')
                . '» ولا كميةَ مسجّلةً بهذه الوحدة على الصف — لا تسعير ملفَّق';
        }
        elseif (!isset(self::CONTRACT_CURRENCY[trim($cl['currency_label'])])) { $cl['reason'] = 'عملة عقد العميل غير معروفة: ' . $cl['currency_label']; }
        else {
            $cl['ok'] = true;
            $cl['unit'] = $clUnit;
            $cl['qty'] = round($recorded[$clUnit], 2);
            $cl['price'] = (float) $t['client_price'];
            $cl['currency'] = self::CONTRACT_CURRENCY[trim($cl['currency_label'])];
            // الموروثان يتبعان حكم العميل (الإيراد) — انظر تعليق البناء أعلاه
            $ctx['unit'] = $clUnit;
            $ctx['qty'] = $cl['qty'];
        }
        unset($cl);

        // ── جانب المورد: سلّم الحسم — الساري بالتاريخ ← النشط الوحيد ← تعذّر ──
        $sp = &$ctx['supplier'];
        if ($ctx['supplier_id'] === null || $t['op_id'] === null) { $sp['reason'] = 'لا موردَ على معدة التشغيل'; }
        else {
            $sq = $conn->prepare(
                "SELECT sc.id, sc.price_currency_contract AS cur_label, sce.equip_price, sce.equip_unit,
                        sce.equip_price_currency AS line_cur_label,
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
                // عملة سطر عقد المورد إن سُجّلت، وإلا ارتدادٌ للرأس (ح-4، تناظر جانب العميل)
                $sp['currency_label'] = (trim((string) $pick['line_cur_label']) !== '')
                                        ? trim((string) $pick['line_cur_label'])
                                        : (string) $pick['cur_label'];
                // وحدةُ عقد المورد تختار عمودَه المقروء — **مستقلةً عن وحدة العميل**
                $spUnit = isset(self::CONTRACT_UNIT[$sp['unit_label']]) ? self::CONTRACT_UNIT[$sp['unit_label']] : null;
                if ($spUnit === null) { $sp['reason'] = 'وحدة فوترةٍ غير معروفة في عقد المورد: ' . $sp['unit_label']; }
                elseif (!isset($recorded[$spUnit])) { $sp['reason'] = 'وحدة عقد المورد «' . $sp['unit_label'] . '» لا يسجّلها سجلّ الدوام بعد'; }
                elseif ($recorded[$spUnit] <= 0) {
                    $sp['reason'] = 'عقد المورد يفوتر بـ«' . ($sp['unit_label'] !== '' ? $sp['unit_label'] : 'ساعة')
                        . '» ولا كميةَ مسجّلةً بهذه الوحدة على الصف — لا تسعير ملفَّق';
                }
                elseif (!isset(self::CONTRACT_CURRENCY[trim($sp['currency_label'])])) { $sp['reason'] = 'عملة عقد المورد غير معروفة: ' . $sp['currency_label']; }
                else {
                    $sp['ok'] = true;
                    $sp['unit'] = $spUnit;
                    $sp['qty'] = round($recorded[$spUnit], 2);
                    $sp['price'] = (float) $pick['equip_price'];
                    $sp['currency'] = self::CONTRACT_CURRENCY[trim($sp['currency_label'])];
                }
            }
        }
        unset($sp);
        return $ctx;
    }

    /**
     * سياسة استحقاق عقد الساعة السارية بتاريخ العمل (D02 §3.8).
     * الأخصّ يغلب: قاعدةُ العقد نفسه تسبق الافتراضية (contract_ref = NULL)،
     * والسارية بالتاريخ تسبق المفتوحة. قراءةٌ خالصةٌ بلا كتابة.
     *
     * @return array<string,array{ruling:string,pct:?float,note:?string,scope:string}>
     */
    public static function hourPolicy($gate, $company, $partyScope, $contractRef, $workDate)
    {
        $rows = $gate->scopedQuery(
            array('scope' => array('p' => 'contract_hour_policies')),
            "SELECT p.ops_state, p.ruling, p.pct, p.note, p.contract_ref
               FROM contract_hour_policies p
              WHERE {TENANT_SCOPE} AND p.deleted_at IS NULL
                AND p.party_scope = ?
                AND (p.contract_ref = ? OR p.contract_ref IS NULL)
                AND (p.effective_from IS NULL OR p.effective_from <= ?)
                AND (p.effective_to   IS NULL OR p.effective_to   >= ?)
              ORDER BY (p.contract_ref IS NULL) ASC, p.effective_from DESC",
            array($partyScope, intval($contractRef), $workDate, $workDate)
        );
        $out = array();
        foreach ($rows as $r) {
            $st = strval($r['ops_state']);
            if (isset($out[$st])) { continue; } // الأوّل هو الأخصّ (الترتيب أعلاه)
            $out[$st] = array(
                'ruling' => strval($r['ruling']),
                'pct' => ($r['pct'] === null) ? null : (float) $r['pct'],
                'note' => $r['note'],
                'scope' => ($r['contract_ref'] === null) ? 'company_default' : 'contract',
            );
        }
        return $out;
    }

    /**
     * حكمُ طرفٍ واحدٍ عن واقعةٍ واحدة (D02 §2.6 + §3.8).
     *
     * القاعدتان الحاكمتان:
     *   • عقدُ الطن/المتر: الاستحقاق = **الكمية المنجزة كما هي** — لا سياسةَ
     *     ولا حالات (قرار الإدارة: «استحقاقه يُحسب بالطن أو المتر المنجز»).
     *   • عقدُ الساعة: الاستحقاق = Σ (ساعاتُ الحالة × نسبتُها) وفق سياسة عقده.
     *     وما حكمُه `case_by_case` أو `pending` **لا يُحسب ولا يُخترع له رقم** —
     *     يُرصد في اللقطة ويُعلَن، فتُرى الفجوةُ لا تُبتلع (قاعدة عدم التلفيق).
     *
     * @return array{award_qty:float,pct:float,state:string,rule:string,snapshot:array}
     */
    public static function partyAward($gate, array $ctx, $party)
    {
        $side = $ctx[$party];
        $unit = $side['unit'];

        // ① الطن والمتر: الكمية المنجزة هي الاستحقاق — لا مسارَ سياسة
        if ($unit !== 'hour') {
            return array(
                'award_qty' => (float) $side['qty'], 'pct' => 100.00, 'state' => 'due',
                'rule' => 'delivered_qty',
                'snapshot' => array('basis' => 'delivered_qty', 'unit' => $unit,
                                    'qty' => (float) $side['qty'],
                                    'note' => 'عقدُ إنتاجٍ: الاستحقاق بالكمية المنجزة لا بتوزيع الزمن'),
            );
        }

        // ② عقد الساعة: تُطبَّق السياسة حالةً حالة
        $policy = self::hourPolicy($gate, $ctx['company_id'], $party, $side['contract_id'], $ctx['work_date']);
        $billable = 0.0; $pendingHrs = 0.0; $excluded = 0.0; $undecided = 0.0;
        $lines = array();
        foreach ($ctx['states'] as $state => $hours) {
            $hours = round((float) $hours, 2);
            if ($hours <= 0) { continue; }
            $rule = isset($policy[$state]) ? $policy[$state] : array('ruling' => 'case_by_case', 'pct' => null, 'note' => 'لا قاعدةَ مسجَّلةٌ لهذه الحالة', 'scope' => 'missing');
            $applied = 0.0;
            switch ($rule['ruling']) {
                case 'full': $applied = $hours; $billable += $applied; break;
                case 'pct':  $applied = round($hours * ((float) $rule['pct']) / 100, 2); $billable += $applied; break;
                case 'none': $excluded += $hours; break;
                case 'pending': $pendingHrs += $hours; break;
                default: $undecided += $hours; // case_by_case — يلزم نصُّ العقد
            }
            $lines[] = array('state' => $state, 'hours' => $hours, 'ruling' => $rule['ruling'],
                             'pct' => $rule['pct'], 'applied' => $applied, 'scope' => $rule['scope']);
        }

        // حالةُ الاستحقاق تصف الواقع بأمانة: معلَّقٌ إن بقي ما ينتظر حسمًا
        $state = 'due';
        if ($undecided > 0 || $pendingHrs > 0) { $state = ($billable > 0) ? 'partial' : 'pending'; }

        return array(
            'award_qty' => round($billable, 2), 'pct' => 100.00, 'state' => $state,
            'rule' => 'hour_policy',
            'snapshot' => array(
                'basis' => 'hour_policy', 'contract_ref' => $side['contract_id'],
                'work_date' => $ctx['work_date'], 'lines' => $lines,
                'billable_hours' => round($billable, 2), 'excluded_hours' => round($excluded, 2),
                'pending_hours' => round($pendingHrs, 2), 'undecided_hours' => round($undecided, 2),
            ),
        );
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

        // ── حسابُ أحكام الأطراف مرةً واحدةً قبل الحلقة (D02 §2.6) ──────────────
        // الأحكام هي المرجع الذي يقرؤه المالُ، فيجب أن تُحسب **قبله** لا بعده.
        // وحسابُها هنا يفكّ الارتباط بترتيب الخريطة نهائيًّا: لو نُقل party_award
        // إلى ذيل display_order لظلّ الإيراد يقرأ حكمًا صحيحًا. حسابٌ خالصٌ بلا
        // كتابة — والكتابةُ في فرع party_award وحده.
        $ctx['awards'] = array();
        foreach (array('client', 'supplier') as $party) {
            $ctx['awards'][$party] = $ctx[$party]['ok'] ? self::partyAward($gate, $ctx, $party) : null;
        }

        /**
         * الكميةُ الحاكمة لطرفٍ في التحويل المالي.
         * سلطةُ الحكم (D02 §2.6): المال يُحسب من `qty_due` في الحكم لا من الكمية
         * الخام — فسياسةُ العقد تسري على الفوترة فعلًا لا على الورق. والرجوعُ إلى
         * الخام يبقى ممكنًا بمفتاح البيئة حتى يُتحقّق من التطابق في كل بيئة.
         */
        $ruledQty = function ($party) use ($ctx) {
            $raw = (float) $ctx[$party]['qty'];
            $authority = strtolower((string) (function_exists('ems_env') ? ems_env('EMS_AWARD_AUTHORITY', 'on') : 'on'));
            if ($authority !== 'on' || empty($ctx['awards'][$party])) { return $raw; }
            $aw = $ctx['awards'][$party];
            return round((float) $aw['award_qty'] * ((float) $aw['pct']) / 100, 2);
        };

        // ── حارس المراجعة: أثرٌ قائمٌ وكميةُ اليوم تغيّرت بعده ⇒ لا كتابة —
        //    التصحيح بعد التوليد يخصّ محرّك العكسيات (D02 §8، مرحلة لاحقة).
        //    ⚠️ يقع **بعد** حساب الأحكام لا قبله: المخزَّن في الحدث صار الكميةَ
        //    المحكومة، فمقارنتُه بالخام تُطلق مراجعةً كاذبةً كلما استبعدت السياسةُ
        //    ساعةً. المقارنة بكمية العميل المحكومة تحديدًا (هي مصدر quantity).
        if (isset($done['revenue_event'])) {
            $revLink = $done['revenue_event'];
            $old = null;
            try { $old = $gate->selectOne('fin_financial_events', array('where' => array('id' => intval($revLink['target_id'])), 'includeDeleted' => true)); }
            catch (\Throwable $t) { /* قراءة حارسٍ فقط */ }
            $nowQty = $ruledQty('client');
            if ($old && round((float) $old['quantity'], 2) !== round($nowQty, 2)) {
                $out['revision_pending'] = true;
                $out['skipped'][] = array('effect' => 'revenue_event', 'label' => 'مراجعة كمية',
                    'reason' => 'الكمية تغيّرت بعد توليد المروحة (' . $old['quantity'] . ' ← ' . $nowQty . ') — التصحيح لمحرّك العكسيات لا للتوليد');
                if (function_exists('log_security_event')) {
                    log_security_event('FANOUT_REVISION_PENDING', 'timesheet=' . $tsId
                        . ' old_qty=' . $old['quantity'] . ' new_qty=' . $nowQty);
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
                    $clQty  = $ruledQty('client');   // ← من حكم العميل لا من الكمية الخام
                    $amount = round($clQty * $ctx['client']['price'], 2);
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
                        'quantity' => $clQty, 'unit' => $ctx['client']['unit'],
                        'source_ref' => $ctx['source_ref'],
                        'project_id' => $ctx['project_id'], 'equipment_id' => $ctx['equipment_id'],
                        'supplier_entity_id' => $ctx['supplier_id'],
                        'operator_employee_id' => $ctx['employee_id'],
                        'notes' => 'مروحة أثر يوم الدوام ' . $ctx['source_ref'],
                        'payload' => array( // لقطة التسعير لحظة التوليد — لا يتغيّر أثرٌ بتغيّر عقدٍ لاحق
                            'source' => 'timesheet', 'work_date' => $ctx['work_date'],
                            'qty' => $clQty, 'unit' => $ctx['client']['unit'],
                            'qty_recorded' => $ctx['client']['qty'],   // الخام للمقارنة والتدقيق
                            'entitlement' => isset($ctx['awards']['client']['state']) ? $ctx['awards']['client']['state'] : null,
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
                    // ⚠️ بوحدة عقد المورد وكميتِه المحكومة — لا بكمية العميل ولا بالخام
                    $spQty  = $ruledQty('supplier');
                    $amount = round($spQty * $ctx['supplier']['price'], 2);
                    $dueId = intval($gate->insert('fin_dues', array(
                        'party_type' => 'supplier', 'party_ref' => $ctx['supplier_id'],
                        'due_type' => self::WORK_MODEL_DUE[$ctx['supplier']['unit']], 'direction' => 'credit',
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
                    $spCostQty = $ruledQty('supplier');
                    $totalCost = round($spCostQty * $ctx['supplier']['price'], 2);
                    // الإيراد داخل سجلّ التكلفة (للربحية) بشرطين: اتحاد العملة **واتحاد
                    // الوحدة**. فربحٌ يطرح تكلفةَ ساعاتٍ من إيراد أمتارٍ رقمٌ بلا معنى —
                    // ويُحجب الإيراد حينئذٍ بدل أن يُعرض ربحٌ ملفَّق (قاعدة عدم التلفيق).
                    $sameBasis = $ctx['client']['ok']
                        && $ctx['client']['currency'] === $ctx['supplier']['currency']
                        && $ctx['client']['unit'] === $ctx['supplier']['unit'];
                    $revenue = $sameBasis ? round($ruledQty('client') * $ctx['client']['price'], 2) : null;
                    $costId = intval($gate->insert('fin_cost_records', array(
                        'cost_type' => $ctx['equipment_id'] ? 'equipment' : 'project',
                        'equipment_id' => $ctx['equipment_id'], 'project_id' => $ctx['project_id'],
                        'period_ref' => $ctx['period'], 'qty' => $spCostQty, 'unit' => $ctx['supplier']['unit'],
                        'unit_cost' => $ctx['supplier']['price'], 'total_cost' => $totalCost,
                        'currency' => $ctx['supplier']['currency'],
                        'revenue' => $revenue,
                        'event_id' => $revId,
                        'created_by' => intval($actor) ?: null,
                    )));
                    self::link($gate, $company, $tsId, $type, 'fin_cost_records', $costId, $revId, self::SOURCE_TIMESHEET);
                    $out['effects'][] = array('effect' => $type, 'target_id' => $costId, 'amount' => $totalCost, 'currency' => $ctx['supplier']['currency']);
                    break;

                case 'party_award':
                    // ── أحكام الأطراف (D02 §2.6): حكمٌ لكل طرفٍ بوحدة عقده ──
                    // يُكتب **قبل** المال ولا يُنشئه: هو القرار التعاقدي الذي
                    // يقرؤه التحويلُ لاحقًا. والطرفُ المتعذّر يُسجَّل بسببه معلنًا
                    // فتُرى الفجوةُ في سجلٍّ لا في صمت.
                    //
                    // ⚠️ لكن «صفٌّ بلا كميةٍ لا يولّد شيئًا» (قاعدة المصدر الواحد):
                    // إن تعذّر الطرفان معًا فلا واقعةَ يُحكم فيها أصلًا — يُعلَن
                    // الأثر متعذّرًا ولا يُكتب صفٌّ فارغٌ يوهم بوجود حكم.
                    if (!$ctx['client']['ok'] && !$ctx['supplier']['ok']) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                            'reason' => 'لا طرفَ قابلًا للحكم — العميل: ' . $ctx['client']['reason']
                                      . ' · المورد: ' . $ctx['supplier']['reason']);
                        break;
                    }
                    $written = 0;
                    foreach (array('client', 'supplier') as $party) {
                        $side = $ctx[$party];
                        $partyRef = ($party === 'client') ? $ctx['project_id'] : $ctx['supplier_id'];
                        $row = array(
                            'source_kind' => 'timesheet', 'source_ref' => $tsId,
                            'party' => $party, 'party_ref' => $partyRef,
                            'contract_ref' => $side['contract_id'],
                            'created_by' => intval($actor) ?: null,
                        );
                        if (!$side['ok']) {
                            // لا حكمَ ملفَّق: الوحدة والكمية صفرٌ والسببُ منصوص
                            $row += array('award_unit_type' => 'hour', 'award_qty' => 0.00,
                                'entitlement_state' => 'not_due', 'entitlement_pct' => 0.00,
                                'unavailable_reason' => mb_substr($side['reason'], 0, 200));
                        } else {
                            $aw = self::partyAward($gate, $ctx, $party);
                            $row += array(
                                'award_unit_type' => $side['unit'], 'award_qty' => $aw['award_qty'],
                                'entitlement_state' => $aw['state'], 'entitlement_pct' => $aw['pct'],
                                'unit_price' => $side['price'], 'currency' => $side['currency'],
                                'policy_rule' => $aw['rule'],
                                'policy_snapshot' => json_encode($aw['snapshot'], JSON_UNESCAPED_UNICODE),
                            );
                        }
                        $awardId = intval($gate->insert('unit_party_awards', $row));
                        if ($written === 0) { // رابطٌ أبويٌّ واحدٌ للأثر (العطالة على النوع لا على الطرف)
                            self::link($gate, $company, $tsId, $type, 'unit_party_awards', $awardId, $revId, self::SOURCE_TIMESHEET);
                        }
                        $written++;
                    }
                    $out['effects'][] = array('effect' => $type, 'target_id' => $written, 'amount' => null, 'currency' => null);
                    break;

                case 'metric_update':
                    // مخصّص الصيانة المحمَّل على المعدة — أساسه **ساعات التشغيل الفعلية**
                    // (executed_hours · قرار المستخدم 2026-07-18)، ومعدّله param_value من
                    // خريطة الآثار (يضبطه المدير المالي في شاشة «مخصّص الصيانة»). قاعدة
                    // عدم التلفيق: معدّلٌ غير مضبوط أو لا معدةَ أو لا ساعاتِ تشغيلٍ ⇒ يُعلَن
                    // متعذّرًا في السجل ولا يُخترع رقم.
                    $rate = ($eff['param_value'] !== null) ? (float) $eff['param_value'] : 0.0;
                    $mhHours = round((float) $ctx['recorded']['hour'], 2); // executed_hours
                    if ($rate <= 0 || $ctx['equipment_id'] === null || $mhHours <= 0) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                            'reason' => $rate <= 0 ? 'معدّل المخصّص غير مضبوط (param_value)'
                                : ($ctx['equipment_id'] === null ? 'لا معدةَ على صف الدوام' : 'لا ساعاتِ تشغيلٍ فعليةً على الصف'));
                        break;
                    }
                    $prov = round($mhHours * $rate, 2);
                    $mid = intval($gate->insert('fin_cost_records', array(
                        'cost_type' => 'maintenance', 'equipment_id' => $ctx['equipment_id'], 'project_id' => $ctx['project_id'],
                        'period_ref' => $ctx['period'], 'qty' => $mhHours, 'unit' => 'hour',
                        'unit_cost' => $rate, 'total_cost' => $prov, 'currency' => 'SDG',
                        'event_id' => $revId,
                        'created_by' => intval($actor) ?: null,
                    )));
                    self::link($gate, $company, $tsId, $type, 'fin_cost_records', $mid, $revId, self::SOURCE_TIMESHEET);
                    $out['effects'][] = array('effect' => $type, 'target_id' => $mid, 'amount' => $prov, 'currency' => 'SDG');
                    break;

                case 'employee_due':
                    // مستحق المشغّل — قرار المستخدم: لكل مشغّلٍ وضعٌ في fin_operator_pay:
                    //   • «بالراتب» (أو غياب صفٍّ) ⇒ المستحق 0 (تدفعه الرواتب) — لا ازدواج.
                    //   • «بالمستحق» ⇒ المروحة تدفعه: operator_hours × المعدّل، تصنيف overtime.
                    // قاعدة عدم التلفيق: لا مشغّل / بالراتب / معدّلٌ غير مضبوط / لا ساعات ⇒ يُعلَن.
                    $empId = $ctx['employee_id'];
                    if ($empId === null) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                            'reason' => 'لا مشغّلَ (employee_id) على صف الدوام');
                        break;
                    }
                    $mode = 'salary'; // الافتراض الآمن: بالراتب ⇒ لا مستحق
                    try {
                        $pm = $gate->selectOne('fin_operator_pay', array('columns' => array('pay_mode'), 'where' => array('employee_id' => $empId)));
                        if ($pm) { $mode = strval($pm['pay_mode']); }
                    } catch (\Throwable $t) { /* غياب الصف = بالراتب */ }
                    if ($mode !== 'due') {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                            'reason' => 'المشغّل «بالراتب» — لا مستحقَ من المروحة (تدفعه الرواتب)');
                        break;
                    }
                    $rate = ($eff['param_value'] !== null) ? (float) $eff['param_value'] : 0.0;
                    $ohours = round((float) $ctx['operator_hours'], 2);
                    if ($rate <= 0 || $ohours <= 0) {
                        $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                            'reason' => $rate <= 0 ? 'معدّل حافز المشغّل غير مضبوط (param_value)' : 'لا ساعاتِ مشغّلٍ على الصف');
                        break;
                    }
                    $empAmount = round($ohours * $rate, 2);
                    $empDueId = intval($gate->insert('fin_dues', array(
                        'party_type' => 'employee', 'party_ref' => $empId,
                        'due_type' => 'overtime', 'direction' => 'credit',
                        'amount' => $empAmount, 'currency' => 'SDG',
                        'period_ref' => $ctx['period'], 'event_id' => $revId,
                        'created_by' => intval($actor) ?: null,
                    )));
                    self::link($gate, $company, $tsId, $type, 'fin_dues', $empDueId, $revId, self::SOURCE_TIMESHEET);
                    $out['effects'][] = array('effect' => $type, 'target_id' => $empDueId, 'amount' => $empAmount, 'currency' => 'SDG');
                    break;

                default:
                    $out['skipped'][] = array('effect' => $type, 'label' => $eff['effect_label'],
                        'reason' => 'لا مولّدَ مسجَّلٌ لهذا النوع في المحرّك');
            }
        }
        return $out;
    }
}
