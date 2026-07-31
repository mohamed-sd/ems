<?php
/**
 * app/Services/Contract/ContractResourcePlanService.php — خطةُ الموارد (P-04)
 * ═══════════════════════════════════════════════════════════════════════════
 * PLAN-03 §2 (الملحق §3-`P-04`): «**خطةُ الموارد** `contract_resource_plan`
 * بحصص الأنواع (`capacity_share_percent`) — **تُغذّي الحاويات ولا تدخل القيمة**».
 *
 * ── لماذا جدولٌ جديدٌ والقديمُ قائم ─────────────────────────────────────────
 * `contractequipments` تحمل الخطةَ **والسعرَ معًا** (`equip_price` ·
 * `equip_price_currency` · `equip_total_contract`)، والحاوياتُ الجذرُ تُبذَر
 * منها حرفيًّا (قياسٌ على الحيّ: `contract_item_id` = `contractequipments.id`
 * و`cap_qty` = `equip_total_contract` **بالضبط**). فخطةُ المعدات اليوم
 * **مصدرُ المال ومصدرُ الطاقة معًا** — وهو عينُ الازدواج الذي حسمته `P-02`.
 * وهنا يُحسَم في الجانب الآخر: **بنيةٌ لا تحمل سعرًا أصلًا**، فالفصلُ في
 * الجدول لا في الاتفاق. والقديمُ **لا يُمَسّ** ويبقى يغذّي القائم.
 *
 * ── أربعُ قواعد ─────────────────────────────────────────────────────────────
 * ① **الخطةُ لا تحمل مالًا** — لا عمودَ سعرٍ ولا عملةٍ ولا مبلغ، **ولا كتابةَ
 *    في `fin_*` من هذه الخدمة بتاتًا**. وإضافةُ خطةٍ لا تحرّك `contractValue`.
 * ② **Σ الحصص ≤ 100 أبدًا** — عدّادٌ على البند بـ`CHECK` والكتابةُ **معاملةٌ
 *    واحدة**. و**المائةُ التامة شرطُ الاكتمال** لا شرطُ الإدراج.
 * ③ **وصفرُ الحصة يُفسَّر باسمه** — المنتجُ لا يكون بصفر؛ فالصفرُ إمّا
 *    احتياطيٌّ وإمّا مساند. (نظيرُ «شهرِ التوقف» في `P-03`.)
 * ④ **والطاقةُ تُشتَقّ من الكمية لا من السعر** — `qty_contracted × الحصة`،
 *    و**آخرُ نوعٍ يبتلع باقيَ التقريب** فلا يضيع كسرٌ في البذر (درسُ `M-30`).
 */

namespace App\Services\Contract;

require_once __DIR__ . '/ContractLineService.php';

class ContractResourcePlanService
{
    const SHARE_KINDS = array('productive', 'backup_only', 'support');

    const KIND_AR = array(
        'productive' => 'منتج', 'backup_only' => 'احتياطي', 'support' => 'مساند',
    );

    /** نماذجُ تسعيرٍ لا حاويةَ لها — «لا طاقةَ تُقاس لبندٍ مقطوع». */
    const NO_CONTAINER_MODELS = array('lump_sum', 'standby');

    /** خريطةُ نموذج التسعير إلى وحدة الحاوية. */
    const UNIT_OF_MODEL = array(
        'hour' => 'hour', 'ton' => 'ton', 'trip' => 'trip', 'meter' => 'meter',
        'cbm' => 'cbm', 'day' => 'day', 'shift' => 'shift',
    );

    // ═════════════════════════════════════════════════════════════════════
    // ① الكتابة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * حفظُ خطةِ مواردَ كاملةٍ لبند — **تُنهي النافذَ وتكتب الجديد في معاملةٍ واحدة**.
     *
     * @param array $rows كلٌّ: {equipment_type_id, capacity_share_percent, count_basic,
     *                          count_backup, shifts_per_day, hours_per_shift,
     *                          operators_count, supervisors_count, technicians_count,
     *                          assistants_count, equipment_size?, share_kind?,
     *                          operational_site_id?, note?}
     * @return array{ok:bool,code:int,reason:string,rows:int,share:float,gap:float,complete:bool}
     */
    public static function savePlan($conn, $gate, $companyId, $lineId, array $rows, $actor, $validFrom = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'rows' => 0,
                     'share' => 0.0, 'gap' => 100.0, 'complete' => false);

        $l = ContractLineService::lineOf($gate, (int) $lineId);
        if (!$l) { $out['code'] = 404; $out['reason'] = 'بندُ البيع غيرُ موجودٍ في نطاقك'; return $out; }
        if ((string) $l['state'] === 'ended') {
            $out['code'] = 409; $out['reason'] = 'البندُ منتهٍ — ولا خطةَ مواردَ لما انتهى'; return $out;
        }
        if (!$rows) { $out['code'] = 422; $out['reason'] = 'لا خطةَ فارغة'; return $out; }

        $from = self::dateOrNull($validFrom);
        if ($from === null) { $from = (string) $l['valid_from']; }
        $to = ($l['valid_to'] !== null && (string) $l['valid_to'] !== '') ? (string) $l['valid_to'] : null;
        if ($to !== null && $from > $to) {
            $out['code'] = 422;
            $out['reason'] = '**سريانُ الخطة ' . $from . ' بعد نهاية البند ' . $to . '**'; return $out;
        }

        $types = self::activeTypes($gate);
        $clean = array(); $sum = 0.0;
        foreach ($rows as $r) {
            $tid = (int) (isset($r['equipment_type_id']) ? $r['equipment_type_id'] : 0);
            if ($tid <= 0 || !isset($types[$tid])) {
                $out['code'] = 422; $out['reason'] = 'نوعُ معدةٍ غيرُ معروفٍ أو غيرُ نشط: ' . $tid; return $out;
            }
            if (isset($clean[$tid])) {
                $out['code'] = 409;
                $out['reason'] = 'النوعُ «' . $types[$tid] . '» مكرَّرٌ في الخطة — '
                               . '**ونوعٌ واحدٌ نافذٌ لكل بند**'; return $out;
            }
            $share = round((float) (isset($r['capacity_share_percent']) ? $r['capacity_share_percent'] : 0), 3);
            if ($share < 0 || $share > 100) {
                $out['code'] = 422; $out['reason'] = 'حصةٌ خارج [0,100] للنوع «' . $types[$tid] . '»'; return $out;
            }
            $kind = (string) (isset($r['share_kind']) ? $r['share_kind'] : 'productive');
            if (!in_array($kind, self::SHARE_KINDS, true)) { $kind = 'productive'; }
            // ③ صفرُ الحصة **يُفسَّر باسمه** — والمنتجُ لا يكون بصفر
            if ($share == 0.0 && $kind === 'productive') { $kind = 'backup_only'; }
            // ولا حصةَ لمن ليس منتجًا — «الاحتياطيُّ جاهزيةٌ لا إنتاجٌ مخطَّط»
            if ($kind !== 'productive' && $share > 0) {
                $out['code'] = 422;
                $out['reason'] = '**' . self::KIND_AR[$kind] . ' بحصةٍ ' . $share . '%** — '
                    . 'والحصةُ للمنتج وحدَه؛ فإمّا أن يُعلَن منتجًا وإمّا أن تكون حصتُه صفرًا';
                return $out;
            }
            $shifts = (int) (isset($r['shifts_per_day']) ? $r['shifts_per_day'] : 1);
            if ($shifts < 1) { $shifts = 1; }
            if ($shifts > 4) {
                $out['code'] = 422; $out['reason'] = 'ورديّاتٌ فوق أربع لليوم الواحد'; return $out;
            }
            $hrs = round((float) (isset($r['hours_per_shift']) ? $r['hours_per_shift'] : 0), 2);
            if ($hrs < 0 || $hrs > 24) {
                $out['code'] = 422; $out['reason'] = 'ساعاتُ الوردية خارج [0,24]'; return $out;
            }
            if ($shifts * $hrs > 24.0001) {
                $out['code'] = 422;
                $out['reason'] = '**' . $shifts . ' ورديّاتٍ × ' . $hrs . ' ساعةً = '
                    . round($shifts * $hrs, 2) . ' — واليومُ أربعٌ وعشرون**'; return $out;
            }
            $clean[$tid] = array(
                'equipment_type_id' => $tid,
                'equipment_size' => self::intOrNull(isset($r['equipment_size']) ? $r['equipment_size'] : null),
                'count_basic' => max(0, (int) (isset($r['count_basic']) ? $r['count_basic'] : 0)),
                'count_backup' => max(0, (int) (isset($r['count_backup']) ? $r['count_backup'] : 0)),
                'shifts_per_day' => $shifts,
                'hours_per_shift' => $hrs,
                'operators_count' => max(0, (int) (isset($r['operators_count']) ? $r['operators_count'] : 0)),
                'supervisors_count' => max(0, (int) (isset($r['supervisors_count']) ? $r['supervisors_count'] : 0)),
                'technicians_count' => max(0, (int) (isset($r['technicians_count']) ? $r['technicians_count'] : 0)),
                'assistants_count' => max(0, (int) (isset($r['assistants_count']) ? $r['assistants_count'] : 0)),
                'capacity_share_percent' => $share,
                'share_kind' => $kind,
                'operational_site_id' => self::intOrNull(isset($r['operational_site_id']) ? $r['operational_site_id'] : null),
                'note' => isset($r['note']) ? mb_substr((string) $r['note'], 0, 255) : null,
            );
            $sum = round($sum + $share, 3);
        }

        // ② Σ لا يتجاوز المائةَ **أبدًا** — والفحصُ قبل أي كتابة
        if ($sum > 100.0001) {
            $out['code'] = 409;
            $out['reason'] = '**Σ الحصص ' . $sum . '% تتجاوز المائة** — والفائضُ '
                           . round($sum - 100, 3) . '%: لا طاقةَ فوق كمية البند';
            $out['share'] = $sum;
            return $out;
        }

        // ولا موقعَ من عقدٍ آخر
        foreach ($clean as $c) {
            if ($c['operational_site_id'] !== null
                && !self::siteBelongs($gate, (int) $c['operational_site_id'], (int) $l['contract_id'])) {
                $out['code'] = 422;
                $out['reason'] = 'الموقعُ ' . $c['operational_site_id'] . ' **ليس من نطاقات هذا العقد**';
                return $out;
            }
        }

        try {
            $gate->runInTransaction(function ($g) use ($lineId, $clean, $sum, $actor, $l, $from, $to) {
                // النافذُ **يُنهى ولا يُمحى** — «التعديلُ إنهاءٌ وإضافةٌ لا محو»
                $live = $g->scopedQuery(array('scope' => array('p' => 'contract_resource_plan')),
                    "SELECT p.id FROM contract_resource_plan p
                      WHERE {TENANT_SCOPE} AND p.line_id = ? AND p.state <> 'ended'
                        AND COALESCE(p.is_deleted,0) = 0", array((int) $lineId));
                foreach ($live as $o) {
                    $g->update('contract_resource_plan',
                        array('state' => 'ended', 'end_reason' => 'استُبدلت بخطةٍ أحدثَ بتاريخ ' . $from),
                        array('id' => (int) $o['id']));
                }
                foreach ($clean as $row) {
                    $row['contract_id'] = (int) $l['contract_id'];
                    $row['line_id'] = (int) $lineId;
                    $row['valid_from'] = $from;
                    $row['valid_to'] = $to;
                    $row['state'] = 'active';
                    $row['created_by'] = (int) $actor ?: null;
                    $g->insert('contract_resource_plan', $row);
                }
                // العدّادُ يُعاد بناؤه ذرّيًّا — و`CHECK` يحرس المائة
                $g->update('client_contract_lines', array('resource_share_total' => $sum),
                    array('id' => (int) $lineId));
            }, 'خطة موارد للبند ' . $lineId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الحفظ: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'save_resource_plan', (int) $lineId, array(),
            array('rows' => count($clean), 'share' => $sum));

        $out['ok'] = true; $out['code'] = 200; $out['rows'] = count($clean);
        $out['share'] = $sum; $out['gap'] = round(100 - $sum, 3);
        $out['complete'] = (abs($out['gap']) < 0.0005);
        $out['reason'] = count($clean) . ' نوعًا بحصةٍ مجموعُها ' . $sum . '%'
            . ($out['complete'] ? ' · **مكتملة**' : (' · **ناقصٌ ' . $out['gap'] . '% — الخطةُ غيرُ مكتملة**'));
        return $out;
    }

    /** إنهاءُ صفٍّ بسببه — **ولا حذف**؛ والحصةُ تعود إلى العدّاد. */
    public static function endRow($conn, $gate, $companyId, $rowId, $reason, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'share' => 0.0);
        $reason = trim((string) $reason);
        if ($reason === '') {
            $out['code'] = 422; $out['reason'] = '**سببُ الإنهاء إلزامي** — ولا صفَّ يخرج صامتًا'; return $out;
        }
        $row = self::rowOf($gate, (int) $rowId);
        if (!$row) { $out['code'] = 404; $out['reason'] = 'صفُّ الخطة غيرُ موجود'; return $out; }
        if ((string) $row['state'] === 'ended') {
            $out['code'] = 409; $out['reason'] = 'الصفُّ منتهٍ سلفًا'; return $out;
        }
        $lineId = (int) $row['line_id'];
        try {
            $gate->runInTransaction(function ($g) use ($rowId, $reason, $lineId) {
                $g->update('contract_resource_plan',
                    array('state' => 'ended', 'end_reason' => mb_substr($reason, 0, 200)),
                    array('id' => (int) $rowId));
                $s = $g->scopedQuery(array('scope' => array('p' => 'contract_resource_plan')),
                    "SELECT ROUND(COALESCE(SUM(p.capacity_share_percent),0),3) AS s
                       FROM contract_resource_plan p
                      WHERE {TENANT_SCOPE} AND p.line_id = ? AND p.state <> 'ended'
                        AND COALESCE(p.is_deleted,0) = 0", array($lineId));
                $g->update('client_contract_lines',
                    array('resource_share_total' => $s ? (float) $s[0]['s'] : 0.0),
                    array('id' => $lineId));
            }, 'إنهاء صف خطة موارد ' . $rowId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإنهاء: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'end_resource_row', (int) $rowId,
            array('state' => $row['state']), array('state' => 'ended', 'reason' => $reason));
        $out['ok'] = true; $out['code'] = 200; $out['share'] = self::shareTotal($gate, $lineId);
        $out['reason'] = 'أُنهي الصفُّ ' . (int) $rowId . ' · وΣ الحصص صارت ' . $out['share'] . '%';
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② القراءة — الطاقةُ المخطَّطة وبذرُ الحاويات
    // ═════════════════════════════════════════════════════════════════════

    /**
     * الطاقةُ المخطَّطة لكل نوع = `qty_contracted × الحصة ÷ 100`.
     * **وآخرُ نوعٍ منتجٍ يبتلع باقيَ التقريب** — فـ10,000 على ثلاثة أنواعٍ
     * تعطي 3333.33 + 3333.33 + **3333.34** = 10,000 لا 9,999.99 (درسُ `M-30`).
     *
     * @return array{ok:bool,code:int,reason:string,total:float,unit:string,rows:array,share:float}
     */
    public static function plannedCapacity($gate, $lineId)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'total' => 0.0,
                     'unit' => '', 'rows' => array(), 'share' => 0.0);
        $l = ContractLineService::lineOf($gate, (int) $lineId);
        if (!$l) { $out['code'] = 404; $out['reason'] = 'بندُ البيع غيرُ موجود'; return $out; }
        $model = (string) $l['pricing_model'];
        if (in_array($model, self::NO_CONTAINER_MODELS, true)) {
            $out['code'] = 422;
            $out['reason'] = '**لا طاقةَ تُقاس لبندٍ بنموذج «' . $model . '»** — '
                . 'والمقطوعُ يُفوتَر بالإنجاز لا بالكمية';
            return $out;
        }
        $qty = round((float) $l['qty_contracted'], 2);
        $rows = self::liveRows($gate, (int) $lineId);
        $share = 0.0;
        $prod = array();
        foreach ($rows as $r) {
            $share = round($share + (float) $r['capacity_share_percent'], 3);
            if ((string) $r['share_kind'] === 'productive') { $prod[] = $r; }
        }
        $out['share'] = $share;
        $running = 0.0; $n = count($prod); $i = 0;
        foreach ($prod as $r) {
            $i++;
            // آخرُ منتجٍ **حين تكون الحصصُ مائةً تامة** يأخذ الباقيَ كلَّه
            if ($i === $n && abs($share - 100.0) < 0.0005) {
                $cap = round($qty - $running, 2);
            } else {
                $cap = round($qty * ((float) $r['capacity_share_percent'] / 100), 2);
            }
            $running = round($running + $cap, 2);
            $out['rows'][] = array(
                'plan_id' => (int) $r['id'],
                'equipment_type_id' => (int) $r['equipment_type_id'],
                'type_name' => (string) $r['type_name'],
                'share' => (float) $r['capacity_share_percent'],
                'planned_qty' => $cap,
                'count_basic' => (int) $r['count_basic'],
                'count_backup' => (int) $r['count_backup'],
            );
        }
        $out['ok'] = true; $out['code'] = 200;
        $out['total'] = $running;
        $out['unit'] = isset(self::UNIT_OF_MODEL[$model]) ? self::UNIT_OF_MODEL[$model] : $model;
        $out['reason'] = $running . ' ' . $out['unit'] . ' موزَّعةً على ' . $n . ' نوعٍ منتج'
            . (abs($share - 100.0) < 0.0005
               ? ' · **Σ = المتعاقَد ' . $qty . '**'
               : (' · **الحصصُ ' . $share . '% — والباقي ' . round($qty - $running, 2) . ' غيرُ مخطَّط**'));
        return $out;
    }

    /**
     * صفوفُ بذرِ الحاويات الجذر — **تُغذّي الحاويات ولا تنشئها**.
     * (الإنشاءُ بيتُه وحدةُ الحاويات وبوابتُها `EMS_CONTAINER_GATE_MODE`.)
     */
    public static function containerSeed($gate, $lineId)
    {
        $cap = self::plannedCapacity($gate, $lineId);
        if (!$cap['ok']) { return $cap; }
        $l = ContractLineService::lineOf($gate, (int) $lineId);
        $seed = array();
        foreach ($cap['rows'] as $r) {
            if ($r['planned_qty'] <= 0) { continue; }
            $seed[] = array(
                'contract_id' => (int) $l['contract_id'],
                'line_id' => (int) $lineId,
                'resource_plan_id' => (int) $r['plan_id'],
                'equipment_type_id' => (int) $r['equipment_type_id'],
                'unit_type' => $cap['unit'],
                'cap_qty' => (float) $r['planned_qty'],
                'label' => (string) $r['type_name'],
            );
        }
        $cap['seed'] = $seed;
        return $cap;
    }

    /** طلبُ العمالة المخطَّط — **عددٌ يُخطَّط له لا استحقاقٌ يُصرف**. */
    public static function crewDemand($gate, $lineId)
    {
        $sum = array('operators' => 0, 'supervisors' => 0, 'technicians' => 0,
                     'assistants' => 0, 'equipment_basic' => 0, 'equipment_backup' => 0);
        foreach (self::liveRows($gate, (int) $lineId) as $r) {
            $sum['operators'] += (int) $r['operators_count'];
            $sum['supervisors'] += (int) $r['supervisors_count'];
            $sum['technicians'] += (int) $r['technicians_count'];
            $sum['assistants'] += (int) $r['assistants_count'];
            $sum['equipment_basic'] += (int) $r['count_basic'];
            $sum['equipment_backup'] += (int) $r['count_backup'];
        }
        $sum['note'] = '**طلبٌ مخطَّط** — ولا يُنشئ استحقاقًا ولا كلفة';
        return $sum;
    }

    public static function shareTotal($gate, $lineId)
    {
        try {
            $r = $gate->scopedQuery(array('scope' => array('p' => 'contract_resource_plan')),
                "SELECT ROUND(COALESCE(SUM(p.capacity_share_percent),0),3) AS s
                   FROM contract_resource_plan p
                  WHERE {TENANT_SCOPE} AND p.line_id = ? AND p.state <> 'ended'
                    AND COALESCE(p.is_deleted,0) = 0", array((int) $lineId));
            return $r ? round((float) $r[0]['s'], 3) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    /** الفجوةُ تُرى: كم بقي من المائة. */
    public static function gapOf($gate, $lineId)
    {
        return round(100 - self::shareTotal($gate, $lineId), 3);
    }

    public static function liveRows($gate, $lineId)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('p' => 'contract_resource_plan'),
                      'enrich' => array('t' => 'equipments_types')),
                "SELECT p.*, t.type AS type_name
                   FROM contract_resource_plan p
                   LEFT JOIN equipments_types t ON t.id = p.equipment_type_id
                  WHERE {TENANT_SCOPE} AND p.line_id = ? AND p.state <> 'ended'
                    AND COALESCE(p.is_deleted,0) = 0
                  ORDER BY p.capacity_share_percent DESC, p.id", array((int) $lineId));
        } catch (\Throwable $t) { error_log('P-04 liveRows: ' . $t->getMessage()); return array(); }
    }

    /** كلُّ الصفوف بما فيها المنتهيةُ — «المنتهيةُ تبقى للتاريخ». */
    public static function allRows($gate, $lineId)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('p' => 'contract_resource_plan'),
                      'enrich' => array('t' => 'equipments_types')),
                "SELECT p.*, t.type AS type_name
                   FROM contract_resource_plan p
                   LEFT JOIN equipments_types t ON t.id = p.equipment_type_id
                  WHERE {TENANT_SCOPE} AND p.line_id = ?
                  ORDER BY p.state, p.id", array((int) $lineId));
        } catch (\Throwable $t) { return array(); }
    }

    /** خطةُ العقد كلِّه مبوَّبةً ببنوده — للشاشة والتقرير. */
    public static function planOfContract($gate, $contractId)
    {
        $out = array();
        foreach (ContractLineService::linesOf($gate, (int) $contractId, false) as $l) {
            $rows = self::liveRows($gate, (int) $l['id']);
            $out[] = array(
                'line' => $l,
                'rows' => $rows,
                'share' => self::shareTotal($gate, (int) $l['id']),
                'gap' => self::gapOf($gate, (int) $l['id']),
                'capacity' => self::plannedCapacity($gate, (int) $l['id']),
            );
        }
        return $out;
    }

    public static function rowOf($gate, $id)
    {
        try { return $gate->selectOne('contract_resource_plan', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    /** أنواعُ المعدات النشطة — `id => الاسم`. */
    public static function activeTypes($gate)
    {
        $o = array();
        try {
            // `equipments_types` مرجعٌ **عامّ** (T_GLOBAL بلا `company_id`) —
            // فقراءتُه بـ`select` لا بـ`scopedQuery`: الأخيرةُ تحقن شرطَ نطاقٍ
            // على عمودٍ لا وجودَ له.
            $r = $gate->select('equipments_types',
                array('where' => array('status' => 'active'), 'orderBy' => 'type'));
            foreach ($r as $x) { $o[(int) $x['id']] = (string) $x['type']; }
        } catch (\Throwable $t) { error_log('P-04 activeTypes: ' . $t->getMessage()); }
        return $o;
    }

    // ═════════════════════════════════════════════════════════════════════

    private static function siteBelongs($gate, $siteId, $contractId)
    {
        try {
            $r = $gate->scopedQuery(array('scope' => array('s' => 'contract_operational_sites')),
                "SELECT s.id FROM contract_operational_sites s
                  WHERE {TENANT_SCOPE} AND s.id = ? AND s.contract_id = ?
                    AND COALESCE(s.is_deleted,0) = 0", array((int) $siteId, (int) $contractId));
            return (bool) $r;
        } catch (\Throwable $t) { return false; }
    }

    private static function intOrNull($v)
    {
        if ($v === null || $v === '' || (int) $v <= 0) { return null; }
        return (int) $v;
    }

    private static function dateOrNull($v)
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'contract_resource_plan', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
