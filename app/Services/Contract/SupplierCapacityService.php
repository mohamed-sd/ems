<?php
/**
 * app/Services/Contract/SupplierCapacityService.php — الطاقةُ والجاهزيةُ ومهلةُ الإحلال (M-16)
 * ═══════════════════════════════════════════════════════════════════════════
 * CON-03 §3:
 *   · «لكل معدةٍ مخصَّصةٍ **طاقةٌ نظريةٌ يوميةٌ بنموذجها** … **تُثبَّت في العقد** —
 *     ومنها يُقاس أداءُ المورد **لا من تقديرٍ لاحق**».
 *   · «نسبةُ الجاهزية = **زمنُ الصلاحية للعمل ÷ الزمنِ المخطط**، تُقرأ من **سجل
 *     الزمن الموحّد** — ونقصُها عن **الحد التعاقدي** يفعّل الجزاءَ بقاعدته».
 *   · «**مهلةُ إحلالِ المعدة المعطلة مكتوبةٌ بالساعات**؛ وتجاوزُها **يحوّل التوقفَ
 *     إلى عجزِ تغطيةٍ بجزائه الأشد**».
 *
 * ── ثلاثُ قواعدَ تحكم كلَّ رقمٍ هنا ─────────────────────────────────────────
 * ① **القياسُ من السجل لا من التقدير** — والزمنُ غيرُ المسجَّل `unlogged`
 *    **يُعلَن عددًا مستقلًّا** ولا يُطرح صامتًا (إخراجُه من المقام يرفع النسبةَ،
 *    فمن حقِّ القارئ أن يرى كم أُخرج). **وبلا زمنٍ مخطط: لا قياسَ ولا جزاء.**
 * ② **الساعةُ تُجزى مرةً واحدة** — ومهلةُ الإحلال **تنقل** الزائدَ من جزاء
 *    الجاهزية إلى جزاء التغطية الأشد، **لا تضيفه إليه** («**يحوّل** التوقفَ»).
 * ③ **الحدُّ يفعّل والقاعدةُ تحتسب** — الحدُّ التعاقديُّ في بطاقة الطاقة هو
 *    **بوابةُ التفعيل**، والمبلغُ بقاعدة الجزاء (M-15) بمعدلها **وسقفها**؛
 *    **وبلا حدٍّ مكتوبٍ لا جزاءَ جاهزيةٍ ألبتة** ويُعلَن السبب. وإن اختلف
 *    الحدّان **يُعلَن الاختلافُ نصًّا** ولا يُخفى.
 */

namespace App\Services\Contract;

class SupplierCapacityService
{
    /** نماذجُ التشغيل الأربعة (CON-03 §2-②) نصًّا. */
    const WORK_MODELS = array('hour', 'ton', 'trip', 'meter');
    const WORK_MODEL_LABELS = array('hour' => 'ساعة', 'ton' => 'طن', 'trip' => 'نقلة', 'meter' => 'متر');

    /**
     * **زمنُ عدمِ الصلاحية للعمل** — «الصلاحيةُ» وصفٌ للمعدة: عطلٌ فنيٌّ أو
     * توقفٌ بسبب موردها ⇒ **غيرُ صالحةٍ للعمل**.
     */
    const UNFIT_STATES = array('tech_breakdown', 'supplier_stop');

    /**
     * **خارجَ الزمن المخطط** — الوقفةُ المخططةُ والقوةُ القاهرة: محاسبةُ المورد
     * عليها ظلمٌ مكتوب. و`unlogged` يخرج كذلك **لكنه يُعلَن عددًا مستقلًّا**.
     */
    const OUT_OF_PLAN_STATES = array('planned_stop', 'force_majeure');
    const UNLOGGED_STATE = 'unlogged';

    // ═════════════════════════════════════════════════════════════════════
    // ① الكتابة — بطاقةُ الطاقة تُثبَّت في العقد
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,capacity_id:?int} */
    public static function saveCapacity($conn, $gate, $companyId, $contractId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'capacity_id' => null);
        $contractId = (int) $contractId;

        $head = self::contractOf($gate, $contractId);
        if (!$head) { $out['code'] = 404; $out['reason'] = 'عقد المورد غير موجود في نطاقك'; return $out; }

        $equipmentId = isset($args['equipment_id']) ? (int) $args['equipment_id'] : 0;
        if ($equipmentId <= 0 || !self::equipmentOf($gate, $equipmentId)) {
            $out['code'] = 422; $out['reason'] = 'معدة غير موجودة في نطاقك — والطاقة لمعدة بعينها'; return $out;
        }

        $model = isset($args['work_model']) ? trim((string) $args['work_model']) : 'hour';
        if (!in_array($model, self::WORK_MODELS, true)) {
            $out['code'] = 422;
            $out['reason'] = 'نموذج تشغيل خارج الأربعة (§2-②): ' . implode(' · ', self::WORK_MODELS);
            return $out;
        }

        // ── «ومنها يُقاس أداءُ المورد» — طاقةٌ صفرٌ تجعل القياسَ قسمةً على عدم ──
        $daily = (isset($args['theoretical_daily']) && trim((string) $args['theoretical_daily']) !== '')
                 ? round((float) $args['theoretical_daily'], 2) : 0.0;
        if ($daily <= 0) {
            $out['code'] = 422;
            $out['reason'] = 'الطاقة النظرية اليومية موجبة إلزاما — «**ومنها يقاس أداء المورد**» (§3)';
            return $out;
        }

        $minReady = (isset($args['min_readiness_percent']) && trim((string) $args['min_readiness_percent']) !== '')
                    ? round((float) $args['min_readiness_percent'], 2) : null;
        if ($minReady !== null && ($minReady <= 0 || $minReady > 100)) {
            $out['code'] = 422; $out['reason'] = 'نسبة الجاهزية الدنيا في (0، 100] — أو تترك فارغة «لم يشترط»'; return $out;
        }

        $replace = (isset($args['replace_hours']) && trim((string) $args['replace_hours']) !== '')
                   ? (int) $args['replace_hours'] : null;
        if ($replace !== null && $replace <= 0) {
            $out['code'] = 422; $out['reason'] = 'مهلة الإحلال ساعات موجبة — أو تترك فارغة'; return $out;
        }

        $from = isset($args['valid_from']) ? trim((string) $args['valid_from']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $out['code'] = 422; $out['reason'] = 'سريان البطاقة إلزامي'; return $out;
        }

        try {
            $out['capacity_id'] = (int) $gate->insert('supplier_capacity', array(
                'contract_id'           => $contractId,
                'equipment_id'          => $equipmentId,
                'work_model'            => $model,
                'theoretical_daily'     => $daily,
                'min_readiness_percent' => $minReady,
                'replace_hours'         => $replace,
                'valid_from'            => $from,
                'valid_to'              => (isset($args['valid_to'])
                                            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['valid_to']))
                                           ? (string) $args['valid_to'] : null,
                'state'                 => 'active',
                'note'                  => (isset($args['note']) && trim((string) $args['note']) !== '')
                                           ? mb_substr(trim((string) $args['note']), 0, 255) : null,
                'created_by'            => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'للعقد بطاقة طاقة لهذه المعدة بهذا السريان (UQ)'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذر الحفظ: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'supplier_capacity', 'create', (int) $out['capacity_id'],
            array(), array('equipment_id' => $equipmentId, 'daily' => $daily,
                           'min_readiness' => $minReady, 'replace_hours' => $replace));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② القياس — «تُقرأ من سجل الزمن الموحّد»
    // ═════════════════════════════════════════════════════════════════════

    /**
     * قياسُ جاهزية المورد في فترة — لكل معدةٍ ثم مجموعًا.
     *
     * @return array{
     *   equipment:array, planned_hours:float, unfit_hours:float, unlogged_hours:float,
     *   coverage_hours:float, readiness:?float, contract_min:?float, notes:array}
     */
    public static function measure($gate, $supplierId, $from, $to)
    {
        $out = array('equipment' => array(), 'planned_hours' => 0.0, 'unfit_hours' => 0.0,
                     'unlogged_hours' => 0.0, 'coverage_hours' => 0.0,
                     'readiness' => null, 'contract_min' => null, 'notes' => array());

        $cards = self::activeCapacities($gate, $supplierId, $from, $to);
        if (!$cards) {
            $out['notes'][] = '⚠ لا بطاقة طاقة سارية لهذا المورد في الفترة — **لا قياس ولا جزاء جاهزية** (§3 يوجب تثبيتها في العقد)';
            return $out;
        }

        $byEquipment = array();
        foreach ($cards as $c) { $byEquipment[(int) $c['equipment_id']] = $c; }
        $log = self::timeLog($gate, array_keys($byEquipment), $from, $to);

        $minWeighted = 0.0; $minWeight = 0.0;
        foreach ($byEquipment as $eqId => $card) {
            $rows = isset($log[$eqId]) ? $log[$eqId] : array();
            $m = self::measureOne($rows, $card);
            $m['equipment_id']   = $eqId;
            $m['equipment_name'] = (string) ($card['equipment_name'] ?? '');
            $m['work_model']     = (string) $card['work_model'];
            $m['theoretical_daily'] = (float) $card['theoretical_daily'];
            $m['min_readiness']  = ($card['min_readiness_percent'] !== null)
                                   ? (float) $card['min_readiness_percent'] : null;
            $m['replace_hours']  = ($card['replace_hours'] !== null) ? (int) $card['replace_hours'] : null;
            $m['capacity_id']    = (int) $card['id'];
            $m['contract_id']    = (int) $card['contract_id'];

            $out['equipment'][]      = $m;
            $out['planned_hours']    = round($out['planned_hours'] + $m['planned_hours'], 2);
            $out['unfit_hours']      = round($out['unfit_hours'] + $m['unfit_hours'], 2);
            $out['unlogged_hours']   = round($out['unlogged_hours'] + $m['unlogged_hours'], 2);
            $out['coverage_hours']   = round($out['coverage_hours'] + $m['coverage_hours'], 2);

            if ($m['min_readiness'] !== null && $m['planned_hours'] > 0) {
                $minWeighted += $m['min_readiness'] * $m['planned_hours'];
                $minWeight   += $m['planned_hours'];
            }
        }

        if ($out['planned_hours'] > 0) {
            $out['readiness'] = round(($out['planned_hours'] - $out['unfit_hours'])
                                      / $out['planned_hours'] * 100, 2);
        } else {
            $out['notes'][] = '⚠ لا زمن مخططا في الفترة — **لا قياس** (ورقم من قسمة على صفر أسوأ من لا رقم)';
        }
        if ($minWeight > 0) { $out['contract_min'] = round($minWeighted / $minWeight, 2); }
        else {
            $out['notes'][] = '⚠ لا حد جاهزية مكتوبا في أي بطاقة طاقة — **لا جزاء جاهزية**: «نقصها عن **الحد التعاقدي** يفعل الجزاء» (§3)، ولا حد فلا نقص';
        }
        if ($out['unlogged_hours'] > 0) {
            $out['notes'][] = 'ℹ ' . $out['unlogged_hours'] . ' ساعة **غير مسجلة** أخرجت من المقام — تعلن ولا تطرح صامتا';
        }
        if ($out['coverage_hours'] > 0) {
            $out['notes'][] = '⚠ ' . $out['coverage_hours'] . ' ساعة **نقلت** من الجاهزية إلى **عجز التغطية** '
                            . 'بتجاوز مهلة الإحلال — «وتجاوزها **يحول** التوقف إلى عجز تغطية بجزائه الأشد» (§3)';
        }
        $out['notes'][] = 'ℹ «إخلال المورد **بإحضار** معدة أو مشغل» لا سطر له في سجل الزمن — '
                        . '**لا يقاس اليوم ويعلن**، والمقيس مسار الإحلال وحده';
        return $out;
    }

    /** قياسُ معدةٍ واحدة من صفوف يومها/حالتها. */
    private static function measureOne($rows, $card)
    {
        $planned = 0.0; $unfitRaw = 0.0; $unlogged = 0.0;
        $unfitByDate = array();
        foreach ($rows as $r) {
            $state = (string) $r['ops_state'];
            $h = (float) $r['h'];
            if ($state === self::UNLOGGED_STATE) { $unlogged += $h; continue; }
            if (in_array($state, self::OUT_OF_PLAN_STATES, true)) { continue; }
            $planned += $h;
            if (in_array($state, self::UNFIT_STATES, true)) {
                $unfitRaw += $h;
                $d = (string) $r['log_date'];
                $unfitByDate[$d] = (isset($unfitByDate[$d]) ? $unfitByDate[$d] : 0.0) + $h;
            }
        }

        // ── نوبُ التوقف: أيامٌ متصلةٌ لمعدةٍ واحدة ──────────────────────────
        ksort($unfitByDate);
        $episodes = array(); $cur = null; $prevTs = null;
        foreach ($unfitByDate as $d => $h) {
            $ts = strtotime($d);
            if ($cur !== null && $prevTs !== null && ($ts - $prevTs) <= 86400 + 3600) {
                $cur['hours'] = round($cur['hours'] + $h, 2); $cur['to'] = $d;
            } else {
                if ($cur !== null) { $episodes[] = $cur; }
                $cur = array('from' => $d, 'to' => $d, 'hours' => round($h, 2));
            }
            $prevTs = $ts;
        }
        if ($cur !== null) { $episodes[] = $cur; }

        // ── «يحوّل التوقفَ إلى عجزِ تغطية»: نقلٌ لا إضافة ────────────────────
        $replace = ($card['replace_hours'] !== null) ? (int) $card['replace_hours'] : null;
        $coverage = 0.0;
        foreach ($episodes as $i => $ep) {
            $over = ($replace !== null && $ep['hours'] > $replace) ? round($ep['hours'] - $replace, 2) : 0.0;
            $episodes[$i]['converted'] = $over;
            $coverage = round($coverage + $over, 2);
        }
        $unfit = round($unfitRaw - $coverage, 2);
        if ($unfit < 0) { $unfit = 0.0; }

        $planned  = round($planned, 2);
        $readiness = ($planned > 0) ? round(($planned - $unfit) / $planned * 100, 2) : null;

        return array(
            'planned_hours'  => $planned,
            'unfit_raw'      => round($unfitRaw, 2),
            'unfit_hours'    => $unfit,
            'unlogged_hours' => round($unlogged, 2),
            'coverage_hours' => $coverage,
            'readiness'      => $readiness,
            'episodes'       => $episodes,
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الوصلُ الحي — «Ledger عند التسوية خصمًا ظاهرًا» (§6.1-Q3)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * أسطرُ جزاءِ الجاهزية والتغطية لفترةٍ — بصيغة بنود التسوية.
     *
     * **والجزاءُ لا يُولَّد إلا بقاعدةٍ مكتوبة** (M-15-①): بلا قاعدةٍ سارية ⇒
     * صفرُ سطر. والأساسُ الذي يُقاس عليه السقفُ **بعملة الأساس** — ولا تُجمع
     * عملتان في رقم.
     *
     * @return array أسطرٌ جاهزةٌ لـ`settlement_lines`
     */
    public static function penaltyLines($gate, $supplierId, $from, $to, $baseAmount, $baseCurrency)
    {
        $lines = array();
        require_once dirname(__DIR__) . '/Settlement/SupplierRuleService.php';

        $m = self::measure($gate, $supplierId, $from, $to);

        // ── جزاءُ الجاهزية: الحدُّ يفعّل والقاعدةُ تحتسب ─────────────────────
        if ($m['readiness'] !== null && $m['contract_min'] !== null) {
            $a = \App\Services\Settlement\SupplierRuleService::assessPenalty(
                $gate, $supplierId, 'readiness', $m['readiness'], (float) $baseAmount, (string) $to);
            if ($a['triggered'] && $a['amount'] > 0) {
                $desc = 'جزاء جاهزية — القياس ' . $m['readiness'] . '٪ والحد التعاقدي '
                      . $m['contract_min'] . '٪ (' . $m['planned_hours'] . ' ساعة مخططة · '
                      . $m['unfit_hours'] . ' غير صالحة) · ' . $a['note'];
                $ruleThreshold = self::penaltyThreshold($gate, $supplierId, 'readiness', $to);
                if ($ruleThreshold !== null && abs($ruleThreshold - (float) $m['contract_min']) > 0.005) {
                    $desc .= ' · ⚠ الحد مكتوب في موضعين ويختلفان (بطاقة الطاقة '
                           . $m['contract_min'] . '٪ · قاعدة الجزاء ' . $ruleThreshold . '٪) — والمال بقاعدة الجزاء';
                }
                $lines[] = array(
                    'line_kind'   => 'charge',
                    'charge_type' => 'penalty',
                    'source_kind' => 'capacity',
                    'source_ref'  => 'readiness:' . (int) $supplierId . ':' . (string) $from,
                    'description' => mb_substr($desc, 0, 255),
                    'work_date'   => (string) $to,
                    'amount'      => (float) $a['amount'],
                    'currency'    => (string) $baseCurrency,
                );
            }
        }

        // ── جزاءُ التغطية: الساعاتُ **المنقولةُ** بتجاوز مهلة الإحلال ────────
        if ($m['coverage_hours'] > 0) {
            $a = \App\Services\Settlement\SupplierRuleService::assessPenalty(
                $gate, $supplierId, 'coverage', $m['coverage_hours'], (float) $baseAmount, (string) $to);
            if ($a['triggered'] && $a['amount'] > 0) {
                $lines[] = array(
                    'line_kind'   => 'charge',
                    'charge_type' => 'penalty',
                    'source_kind' => 'capacity',
                    'source_ref'  => 'coverage:' . (int) $supplierId . ':' . (string) $from,
                    'description' => mb_substr('جزاء تغطية — ' . $m['coverage_hours']
                                    . ' ساعة **نقلت** من الجاهزية بتجاوز مهلة الإحلال · ' . $a['note'], 0, 255),
                    'work_date'   => (string) $to,
                    'amount'      => (float) $a['amount'],
                    'currency'    => (string) $baseCurrency,
                );
            }
        }
        return $lines;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ قراءات
    // ═════════════════════════════════════════════════════════════════════

    /** بطاقاتُ عقدٍ للشاشة. */
    public static function capacityOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('c' => 'supplier_capacity'),
                      'enrich' => array('e' => 'equipments')),
                "SELECT c.*, e.name AS equipment_name, e.code AS equipment_code
                   FROM supplier_capacity c
                   LEFT JOIN equipments e ON e.id = c.equipment_id
                  WHERE {TENANT_SCOPE} AND c.contract_id = ? AND COALESCE(c.is_deleted,0)=0
                  ORDER BY c.equipment_id, c.valid_from DESC", array((int) $contractId));
        } catch (\Throwable $t) { return array(); }
    }

    /**
     * بطاقاتُ الطاقة الساريةُ لموردٍ في فترة — **بالسريان المزدوج**: سريانُ
     * البطاقة **ومدةُ عقدها** معًا (لا عقدَ ⇒ لا قياسَ بطاقته — نظيرُ M-15).
     */
    public static function activeCapacities($gate, $supplierId, $from, $to)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('c' => 'supplier_capacity'),
                      'enrich' => array('h' => 'supplier_contracts', 'e' => 'equipments')),
                "SELECT c.*, e.name AS equipment_name, e.code AS equipment_code
                   FROM supplier_capacity c
                   LEFT JOIN supplier_contracts h ON h.id = c.contract_id
                   LEFT JOIN equipments e ON e.id = c.equipment_id
                  WHERE {TENANT_SCOPE} AND h.supplier_id = ? AND COALESCE(h.is_deleted,0)=0
                    AND (h.start_date IS NULL OR h.start_date <= ?)
                    AND (h.end_date IS NULL OR h.end_date >= ?)
                    AND c.state = 'active' AND COALESCE(c.is_deleted,0)=0
                    AND c.valid_from <= ? AND (c.valid_to IS NULL OR c.valid_to >= ?)
                  ORDER BY c.equipment_id, c.valid_from DESC",
                array((int) $supplierId, (string) $to, (string) $from, (string) $to, (string) $from));
        } catch (\Throwable $t) { return array(); }
    }

    /** سجلُّ الزمن مجمَّعًا (معدة × يوم × حالة) — «تُقرأ من سجل الزمن الموحّد». */
    private static function timeLog($gate, $equipmentIds, $from, $to)
    {
        $ids = array();
        foreach ($equipmentIds as $i) { if ((int) $i > 0) { $ids[] = (int) $i; } }
        if (!$ids) { return array(); }
        $in = implode(',', $ids);
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('t' => 'unit_time_log')),
                "SELECT t.equipment_id, t.log_date, t.ops_state, SUM(t.hours) AS h
                   FROM unit_time_log t
                  WHERE {TENANT_SCOPE} AND t.equipment_id IN ({$in})
                    AND t.log_date BETWEEN ? AND ?
                  GROUP BY t.equipment_id, t.log_date, t.ops_state
                  ORDER BY t.equipment_id, t.log_date", array((string) $from, (string) $to));
        } catch (\Throwable $t) { return array(); }

        $out = array();
        foreach ($rows as $r) { $out[(int) $r['equipment_id']][] = $r; }
        return $out;
    }

    /** عتبةُ قاعدة الجزاء — لإعلان اختلاف الحدّين لا لاحتسابه. */
    private static function penaltyThreshold($gate, $supplierId, $kind, $date)
    {
        require_once dirname(__DIR__) . '/Settlement/SupplierRuleService.php';
        $rule = \App\Services\Settlement\SupplierRuleService::activePenaltyRule(
            $gate, $supplierId, $kind, $date);
        return ($rule && $rule['threshold'] !== null) ? (float) $rule['threshold'] : null;
    }

    private static function contractOf($gate, $contractId)
    {
        try { return $gate->selectOne('supplier_contracts', array('where' => array('id' => (int) $contractId))); }
        catch (\Throwable $t) { return null; }
    }

    private static function equipmentOf($gate, $equipmentId)
    {
        try { return $gate->selectOne('equipments', array('where' => array('id' => (int) $equipmentId))); }
        catch (\Throwable $t) { return null; }
    }

    private static function audit($conn, $companyId, $actor, $table, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'suppliers', $table, $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
