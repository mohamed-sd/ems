<?php
/**
 * app/Services/Contract/SupplierEvaluationService.php — التقييمُ الدوريُّ (M-17)
 * ═══════════════════════════════════════════════════════════════════════════
 * CON-03 §4-التقييم: «دوريٌّ **بمؤشراتٍ من سجلات النظام لا انطباعًا**:
 * الجاهزيةُ · الالتزامُ بالتغطية · نسبةُ التوقفات المسندة إليه · جودةُ
 * المشغّلين · الحوادثُ — **ونتيجتُه شرطٌ في التجديد** وفي ترجيح عروضه القادمة».
 * UX-05 §5.1-⑦: «مؤشراتُ أدائه **من سجلاته** … لا انطباعًا».
 *
 * ── أربعُ قواعدَ تحكم كلَّ رقمٍ هنا ─────────────────────────────────────────
 * ① **لا انطباعَ ألبتة**: لا مدخلَ يدويًّا لقيمة مؤشرٍ ولا للنتيجة — كلُّ رقمٍ
 *    محسوبٌ من مصدره المسمّى، والسطرُ يحمل مصدرَه نصًّا («كلُّ رقمٍ ينقر لمصدره»).
 * ② **لا نتيجةَ بلا وزنٍ مكتوب**: الأوزانُ تُكتب و**Σ = 100** — وبغيرها 422.
 * ③ **المؤشرُ بلا مصدرٍ يُعلَن ولا يُقدَّر**: يُخزَّن `measurable=0`، و**تغطيةُ
 *    الوزن المقيس تُحفظ وتُعرض** فلا تختفي خلف نسبةٍ مطبَّعة؛ و**تقييمٌ أكثرُ
 *    من نصف وزنه بلا مصدرٍ لا يُعتمد**.
 * ④ **الرقمُ يخبر والإنسانُ يقرّر**: النتيجةُ محسوبة، و«شرطُ التجديد» قرارٌ
 *    صريحٌ يُتخذ عليها — و**منعُ التجديد يلزمه سببٌ مكتوب** (خدمةً وقيدًا).
 */

namespace App\Services\Contract;

class SupplierEvaluationService
{
    /** مؤشراتُ §4-التقييم الخمسةُ نصًّا. */
    const INDICATORS = array('readiness', 'coverage', 'attributed_stops', 'operator_quality', 'incidents');
    const INDICATOR_LABELS = array(
        'readiness'        => 'الجاهزية',
        'coverage'         => 'الالتزام بالتغطية',
        'attributed_stops' => 'نسبة التوقفات المسندة إليه',
        'operator_quality' => 'جودة المشغّلين',
        'incidents'        => 'الحوادث',
    );
    const RENEWAL_FLAGS = array('eligible', 'conditional', 'not_eligible');
    const RENEWAL_LABELS = array('eligible' => 'مؤهَّلٌ للتجديد',
                                 'conditional' => 'مؤهَّلٌ بشرط',
                                 'not_eligible' => 'غيرُ مؤهَّلٍ للتجديد');

    /** أدنى تغطيةِ وزنٍ يُعتمد عندها تقييم — «نصفُ وزنٍ بلا مصدرٍ ليس تقييمًا». */
    const MIN_COVERAGE = 50.0;

    /** نوعُ البلاغ الذي يُقرأ منه «الحوادث» — بلاغُ السلامة نفسُه لا أيُّ بلاغ. */
    const INCIDENT_TYPE_CODE = 'safety_incident';

    // ═════════════════════════════════════════════════════════════════════
    // ① الأوزان — «لا نتيجةَ بلا وزنٍ مكتوب»
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,weight_id:?int} */
    public static function saveWeight($conn, $gate, $companyId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'weight_id' => null);

        $ind = isset($args['indicator']) ? trim((string) $args['indicator']) : '';
        if (!in_array($ind, self::INDICATORS, true)) {
            $out['code'] = 422;
            $out['reason'] = 'مؤشرٌ خارج الخمسة (§4-التقييم): ' . implode(' · ', self::INDICATORS);
            return $out;
        }
        $w = (isset($args['weight']) && trim((string) $args['weight']) !== '')
             ? round((float) $args['weight'], 2) : 0.0;
        if ($w <= 0 || $w > 100) {
            $out['code'] = 422; $out['reason'] = 'الوزنُ في (0، 100]'; return $out;
        }
        $scale = (isset($args['scale_max']) && trim((string) $args['scale_max']) !== '')
                 ? round((float) $args['scale_max'], 2) : null;
        if ($scale !== null && $scale <= 0) {
            $out['code'] = 422; $out['reason'] = 'المقياسُ موجبٌ أو غيرُ مكتوب'; return $out;
        }

        $row = self::weightRow($gate, $ind);
        try {
            if ($row) {
                $gate->update('supplier_evaluation_weights',
                    array('weight' => $w, 'scale_max' => $scale,
                          'note' => isset($args['note']) && trim((string) $args['note']) !== ''
                                    ? mb_substr(trim((string) $args['note']), 0, 255) : null),
                    array('id' => (int) $row['id']));
                $out['weight_id'] = (int) $row['id'];
            } else {
                $out['weight_id'] = (int) $gate->insert('supplier_evaluation_weights', array(
                    'indicator' => $ind, 'weight' => $w, 'scale_max' => $scale,
                    'note' => isset($args['note']) && trim((string) $args['note']) !== ''
                              ? mb_substr(trim((string) $args['note']), 0, 255) : null,
                    'created_by' => (int) $actor ?: null,
                ));
            }
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الحفظ: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'supplier_evaluation_weights',
            $row ? 'update' : 'create', (int) $out['weight_id'], array(),
            array('indicator' => $ind, 'weight' => $w, 'scale_max' => $scale));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    public static function weights($gate)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('w' => 'supplier_evaluation_weights')),
                "SELECT w.* FROM supplier_evaluation_weights w
                  WHERE {TENANT_SCOPE} AND COALESCE(w.is_deleted,0)=0
                  ORDER BY w.indicator");
        } catch (\Throwable $t) { return array(); }
    }

    public static function weightsSum($gate)
    {
        $s = 0.0;
        foreach (self::weights($gate) as $w) { $s = round($s + (float) $w['weight'], 2); }
        return $s;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② التوليد — «من سجلات النظام لا انطباعًا»
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string,evaluation_id:?int,score:?float,coverage:float} */
    public static function generate($conn, $gate, $companyId, $supplierId, $from, $to, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'evaluation_id' => null,
                     'score' => null, 'coverage' => 0.0);
        $supplierId = (int) $supplierId;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to) || $from > $to) {
            $out['code'] = 422; $out['reason'] = 'فترةٌ غير صالحة'; return $out;
        }

        // ── «لا نتيجةَ بلا وزنٍ مكتوب» ─────────────────────────────────────
        $weights = self::weights($gate);
        if (!$weights) {
            $out['code'] = 422;
            $out['reason'] = 'لا أوزانَ مكتوبةً للتقييم — **والنتيجةُ بلا وزنٍ انطباعٌ برقم** (§4)';
            return $out;
        }
        $sum = self::weightsSum($gate);
        if (abs($sum - 100.0) > 0.005) {
            $out['code'] = 422;
            $out['reason'] = 'Σ أوزان المؤشرات = ' . $sum . ' والواجبُ **100** — اضبطها قبل التقييم';
            return $out;
        }

        // ── العطالة: تقييمٌ واحدٌ لكل (مورد × فترة) ────────────────────────
        $ex = null;
        try {
            $ex = $gate->selectOne('supplier_evaluations', array(
                'whereRaw' => 'supplier_id = ? AND period_from = ? AND period_to = ?',
                'params'   => array($supplierId, (string) $from, (string) $to)));
        } catch (\Throwable $t) { $ex = null; }
        if ($ex && (string) $ex['state'] === 'decided') {
            $out['code'] = 423;
            $out['reason'] = 'تقييمُ هذه الفترة **معتمَدٌ** — لا يُعاد توليدُه (التصحيحُ بتقييمِ فترةٍ تالية)';
            $out['evaluation_id'] = (int) $ex['id'];
            return $out;
        }

        $lines = self::measureIndicators($gate, $supplierId, $from, $to, $weights);

        $earned = 0.0; $covered = 0.0;
        foreach ($lines as $l) {
            if (!$l['measurable']) { continue; }
            $earned  = round($earned + (float) $l['earned'], 2);
            $covered = round($covered + (float) $l['weight'], 2);
        }
        $score = ($covered > 0) ? round($earned / $covered * 100, 2) : null;
        $out['score'] = $score; $out['coverage'] = $covered;

        $evalId = $ex ? (int) $ex['id'] : null;
        try {
            $gate->runInTransaction(function ($g) use (
                &$evalId, $ex, $supplierId, $from, $to, $score, $covered, $actor, $lines
            ) {
                if ($ex) {
                    $g->update('supplier_evaluations',
                        array('score' => $score, 'weight_measured' => $covered,
                              'generated_by' => (int) $actor ?: null),
                        array('id' => (int) $ex['id']));
                    // إعادةُ توليدٍ لمسودة: الأسطرُ تُكنس بمفاتيحها (لا حذفَ ناعمَ لها —
                    // سطرُ مؤشرٍ قياسٌ لا مستند، وقياسٌ قديمٌ يُستبدل لا يُؤرشف)
                    foreach ($g->select('supplier_evaluation_lines',
                             array('columns' => array('id'),
                                   'where' => array('evaluation_id' => (int) $ex['id']))) as $old) {
                        $g->deleteRow('supplier_evaluation_lines', (int) $old['id'], 'إعادة توليد تقييم');
                    }
                } else {
                    $evalId = (int) $g->insert('supplier_evaluations', array(
                        'supplier_id' => $supplierId, 'period_from' => (string) $from,
                        'period_to' => (string) $to, 'score' => $score,
                        'weight_measured' => $covered, 'state' => 'draft',
                        'generated_by' => (int) $actor ?: null));
                }
                foreach ($lines as $l) {
                    $g->insert('supplier_evaluation_lines', array(
                        'evaluation_id'  => $evalId,
                        'indicator'      => $l['indicator'],
                        'measurable'     => $l['measurable'] ? 1 : 0,
                        'measured_value' => $l['measured_value'],
                        'basis_value'    => $l['basis_value'],
                        'ratio'          => $l['ratio'],
                        'weight'         => $l['weight'],
                        'earned'         => $l['earned'],
                        'source_note'    => mb_substr((string) $l['source_note'], 0, 255),
                    ));
                }
            }, 'تقييم مورد ' . $supplierId . ' ' . $from);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر التوليد: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'supplier_evaluations', $ex ? 'update' : 'create',
            (int) $evalId, array(), array('score' => $score, 'coverage' => $covered));
        $out['ok'] = true; $out['code'] = 200; $out['evaluation_id'] = (int) $evalId;
        return $out;
    }

    /**
     * قياسُ المؤشرات الخمسة من مصادرها — **وكلُّ سطرٍ يحمل مصدرَه نصًّا**.
     * والمؤشرُ الذي لا مصدرَ له في الفترة يعود `measurable=false` **معلَنًا**.
     */
    public static function measureIndicators($gate, $supplierId, $from, $to, $weights = null)
    {
        require_once __DIR__ . '/SupplierCapacityService.php';
        if ($weights === null) { $weights = self::weights($gate); }
        $wMap = array();
        foreach ($weights as $w) { $wMap[(string) $w['indicator']] = $w; }

        $m = SupplierCapacityService::measure($gate, $supplierId, $from, $to);
        $planned = (float) $m['planned_hours'];
        $eqIds = array();
        foreach ($m['equipment'] as $e) { $eqIds[] = (int) $e['equipment_id']; }

        $stops = self::stopHours($gate, $eqIds, $from, $to);
        $incidents = self::incidentCount($gate, $eqIds, $from, $to);

        $lines = array();
        foreach (self::INDICATORS as $ind) {
            if (!isset($wMap[$ind])) { continue; }   // مؤشرٌ بلا وزنٍ مكتوبٍ لا يدخل
            $weight = (float) $wMap[$ind]['weight'];
            $scale  = ($wMap[$ind]['scale_max'] !== null) ? (float) $wMap[$ind]['scale_max'] : null;

            $measurable = false; $value = null; $basis = null; $ratio = null; $note = '';

            if ($ind === 'readiness') {
                if ($m['readiness'] !== null) {
                    $measurable = true; $value = (float) $m['readiness']; $basis = 100.0;
                    $ratio = $value / 100.0;
                    $note = 'جاهزيةُ الفترة ' . $value . '٪ من `unit_time_log` عبر بطاقات الطاقة (M-16)';
                } else {
                    $note = '⚠ لا قياسَ جاهزيةٍ في الفترة — **يُعلَن ولا يُقدَّر**';
                }
            } elseif ($ind === 'coverage') {
                if ($planned > 0) {
                    $measurable = true; $value = (float) $m['coverage_hours']; $basis = $planned;
                    $ratio = max(0.0, 1.0 - ($value / $planned));
                    $note = 'ساعاتُ عجزِ التغطية ' . $value . ' من ' . $planned
                          . ' مخططة — **بتجاوز مهلة الإحلال** (M-16)';
                } else {
                    $note = '⚠ لا زمنَ مخططًا — لا التزامَ تغطيةٍ يُقاس';
                }
            } elseif ($ind === 'attributed_stops') {
                if ($planned > 0) {
                    $measurable = true; $value = (float) $stops['supplier']; $basis = $planned;
                    $ratio = max(0.0, 1.0 - ($value / $planned));
                    $note = 'توقفاتٌ مسندةٌ إليه ' . $value . ' ساعةً من ' . $planned
                          . ' — `unit_time_log.resp_party = supplier`';
                } else {
                    $note = '⚠ لا زمنَ مخططًا — لا نسبةَ توقفاتٍ تُقاس';
                }
            } elseif ($ind === 'operator_quality') {
                if ($planned > 0) {
                    $measurable = true; $value = (float) $stops['operator']; $basis = $planned;
                    $ratio = max(0.0, 1.0 - ($value / $planned));
                    $note = 'توقفُ مشغّلٍ ' . $value . ' ساعةً من ' . $planned
                          . ' — `unit_time_log.ops_state = operator_stop`';
                } else {
                    $note = '⚠ لا زمنَ مخططًا — لا جودةَ مشغّلين تُقاس';
                }
            } else {   // incidents
                if ($scale === null) {
                    $note = '⚠ **بلا مقياسٍ مكتوب** لعدد الحوادث — عددٌ بلا مقياسٍ لا يصير نسبةً (يُعلَن)';
                } elseif (!$eqIds) {
                    $note = '⚠ لا معداتٍ مخصَّصةً في الفترة — لا وعاءَ للحوادث';
                } else {
                    $measurable = true; $value = (float) $incidents; $basis = $scale;
                    $ratio = max(0.0, 1.0 - min(1.0, $value / $scale));
                    $note = $incidents . ' بلاغَ سلامةٍ على معداته (`tickets` نوع '
                          . self::INCIDENT_TYPE_CODE . ') بمقياس ' . $scale;
                }
            }

            $lines[] = array(
                'indicator' => $ind, 'measurable' => $measurable,
                'measured_value' => $value, 'basis_value' => $basis,
                'ratio' => ($ratio !== null) ? round($ratio, 4) : null,
                'weight' => $weight,
                'earned' => ($ratio !== null) ? round($weight * $ratio, 2) : 0.0,
                'source_note' => $note,
            );
        }
        return $lines;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ القرار — «ونتيجتُه شرطٌ في التجديد»
    // ═════════════════════════════════════════════════════════════════════

    /** @return array{ok:bool,code:int,reason:string} */
    public static function decide($conn, $gate, $companyId, $evaluationId, $flag, $note, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $ev = self::head($gate, (int) $evaluationId);
        if (!$ev) { $out['code'] = 404; $out['reason'] = 'التقييمُ غيرُ موجودٍ في نطاقك'; return $out; }
        if ((string) $ev['state'] !== 'draft') {
            $out['code'] = 409; $out['reason'] = 'التقييمُ معتمَدٌ سلفًا — ولا يُعاد قرارُه'; return $out;
        }
        $flag = trim((string) $flag);
        if (!in_array($flag, self::RENEWAL_FLAGS, true)) {
            $out['code'] = 422; $out['reason'] = 'قرارُ التجديد خارج الثلاثة'; return $out;
        }
        $note = trim((string) $note);
        if ($flag === 'not_eligible' && $note === '') {
            $out['code'] = 422;
            $out['reason'] = 'منعُ التجديد **يلزمه سببٌ مكتوب** — قرارٌ يقطع تعاقدًا لا يكون صامتًا';
            return $out;
        }

        // ── «نصفُ وزنٍ بلا مصدرٍ ليس تقييمًا» ──────────────────────────────
        if ((float) $ev['weight_measured'] < self::MIN_COVERAGE) {
            $out['code'] = 422;
            $out['reason'] = 'التغطيةُ المقيسة ' . $ev['weight_measured'] . '٪ دون الحد '
                           . self::MIN_COVERAGE . '٪ — **تقييمٌ أكثرُ من نصف وزنه بلا مصدرٍ لا يُعتمد**';
            return $out;
        }

        try {
            $gate->update('supplier_evaluations', array(
                'state' => 'decided', 'renewal_flag' => $flag,
                'decision_note' => $note !== '' ? mb_substr($note, 0, 255) : null,
                'decided_by' => (int) $actor ?: null, 'decided_at' => date('Y-m-d H:i:s'),
            ), array('id' => (int) $evaluationId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الاعتماد: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'supplier_evaluations', 'decide', (int) $evaluationId,
            array('state' => 'draft'), array('state' => 'decided', 'renewal_flag' => $flag));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /**
     * بوابةُ التجديد — «**ونتيجتُه شرطٌ في التجديد**» (§4).
     *
     * @return array{ok:bool,reason:string,evaluation_id:?int,score:?float,flag:?string}
     */
    public static function renewalGate($gate, $supplierId, $asOf = null)
    {
        $out = array('ok' => false, 'reason' => '', 'evaluation_id' => null,
                     'score' => null, 'flag' => null);
        $ev = self::latestDecided($gate, $supplierId, $asOf);
        if (!$ev) {
            $out['reason'] = 'لا تقييمَ دوريًّا معتمَدًا لهذا المورد — و«**نتيجتُه شرطٌ في التجديد**» (CON-03 §4): '
                           . 'قيّمه بفترةٍ ثم جدّد';
            return $out;
        }
        $out['evaluation_id'] = (int) $ev['id'];
        $out['score'] = ($ev['score'] !== null) ? (float) $ev['score'] : null;
        $out['flag']  = (string) $ev['renewal_flag'];
        if ((string) $ev['renewal_flag'] === 'not_eligible') {
            $out['reason'] = 'آخرُ تقييمٍ معتمَدٍ (' . $ev['period_from'] . ' → ' . $ev['period_to']
                           . ' · نتيجة ' . $ev['score'] . ') يقضي بأنه **غيرُ مؤهَّلٍ للتجديد**: '
                           . (string) $ev['decision_note'];
            return $out;
        }
        $out['ok'] = true;
        $out['reason'] = 'تقييمٌ معتمَدٌ (' . $ev['period_to'] . ' · نتيجة ' . $ev['score'] . ' · '
                       . (self::RENEWAL_LABELS[(string) $ev['renewal_flag']] ?? '') . ')';
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ قراءات
    // ═════════════════════════════════════════════════════════════════════

    public static function latestDecided($gate, $supplierId, $asOf = null)
    {
        try {
            $params = array((int) $supplierId);
            $extra = '';
            if ($asOf !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf)) {
                $extra = ' AND e.period_to <= ?'; $params[] = (string) $asOf;
            }
            $rows = $gate->scopedQuery(array('scope' => array('e' => 'supplier_evaluations')),
                "SELECT e.* FROM supplier_evaluations e
                  WHERE {TENANT_SCOPE} AND e.supplier_id = ? AND e.state = 'decided'
                    AND COALESCE(e.is_deleted,0)=0" . $extra . "
                  ORDER BY e.period_to DESC, e.id DESC LIMIT 1", $params);
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    public static function evaluationsOf($gate, $supplierId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('e' => 'supplier_evaluations')),
                "SELECT e.* FROM supplier_evaluations e
                  WHERE {TENANT_SCOPE} AND e.supplier_id = ? AND COALESCE(e.is_deleted,0)=0
                  ORDER BY e.period_to DESC, e.id DESC", array((int) $supplierId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function linesOf($gate, $evaluationId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('l' => 'supplier_evaluation_lines')),
                "SELECT l.* FROM supplier_evaluation_lines l
                  WHERE {TENANT_SCOPE} AND l.evaluation_id = ?
                  ORDER BY l.id", array((int) $evaluationId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function head($gate, $evaluationId)
    {
        try { return $gate->selectOne('supplier_evaluations', array('where' => array('id' => (int) $evaluationId))); }
        catch (\Throwable $t) { return null; }
    }

    // ═════════════════════════════════════════════════════════════════════
    // ⑤ مصادرُ القياس
    // ═════════════════════════════════════════════════════════════════════

    /** ساعاتُ التوقف على معداته: المسندةُ إليه · وتوقفُ مشغّلٍ. */
    private static function stopHours($gate, $equipmentIds, $from, $to)
    {
        $out = array('supplier' => 0.0, 'operator' => 0.0);
        $ids = array();
        foreach ($equipmentIds as $i) { if ((int) $i > 0) { $ids[] = (int) $i; } }
        if (!$ids) { return $out; }
        $in = implode(',', $ids);
        try {
            $rows = $gate->scopedQuery(array('scope' => array('t' => 'unit_time_log')),
                "SELECT SUM(CASE WHEN t.resp_party = 'supplier' THEN t.hours ELSE 0 END) AS sup,
                        SUM(CASE WHEN t.ops_state  = 'operator_stop' THEN t.hours ELSE 0 END) AS opr
                   FROM unit_time_log t
                  WHERE {TENANT_SCOPE} AND t.equipment_id IN ({$in})
                    AND t.log_date BETWEEN ? AND ?", array((string) $from, (string) $to));
            if ($rows) {
                $out['supplier'] = round((float) $rows[0]['sup'], 2);
                $out['operator'] = round((float) $rows[0]['opr'], 2);
            }
        } catch (\Throwable $t) { /* يُعلَن صفرًا لا يُختلق */ }
        return $out;
    }

    /** بلاغاتُ السلامة على معداته في الفترة — «الحوادثُ» بمصدرها لا بتقدير. */
    private static function incidentCount($gate, $equipmentIds, $from, $to)
    {
        $ids = array();
        foreach ($equipmentIds as $i) { if ((int) $i > 0) { $ids[] = (int) $i; } }
        if (!$ids) { return 0; }
        $in = implode(',', $ids);
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('k' => 'tickets'), 'enrich' => array('y' => 'ticket_types')),
                "SELECT COUNT(*) AS n
                   FROM tickets k
                   LEFT JOIN ticket_types y ON y.id = k.ticket_type_id
                  WHERE {TENANT_SCOPE} AND k.equipment_id IN ({$in})
                    AND y.code = ? AND k.call_date BETWEEN ? AND ?
                    AND k.stage <> 'cancelled'",
                array(self::INCIDENT_TYPE_CODE, (string) $from, (string) $to));
            return $rows ? (int) $rows[0]['n'] : 0;
        } catch (\Throwable $t) { return 0; }
    }

    private static function weightRow($gate, $indicator)
    {
        try {
            return $gate->selectOne('supplier_evaluation_weights',
                array('whereRaw' => 'indicator = ?', 'params' => array((string) $indicator)));
        } catch (\Throwable $t) { return null; }
    }

    private static function audit($conn, $companyId, $actor, $table, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'suppliers', $table, $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
