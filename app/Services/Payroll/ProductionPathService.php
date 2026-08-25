<?php
/**
 * app/Services/Payroll/ProductionPathService.php — المسارُ الإنتاجي (H-09-③)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-01 §3-②: «المشروعي التشغيلي — للمشروعيين والمشغّلين: **سجلُّ الوحدات
 * القانوني بعد اكتمال أحكام الأطراف** · أساسُ عقده (منفَّذٌ · استعدادٌ · حضورٌ ·
 * طنٌ · نقلةٌ · مترٌ · مركّب) والحافزُ من قاعدته».
 * ENT-01 §4-الحوافز: «الأساسُ (وحداتٌ أو عتبةٌ …) ثم **السقفُ والحدُّ الأدنى**،
 * ثم **توزيعُه على مستفيديه بنسبهم (Σ=100٪)**».
 *
 * ── ثلاثُ قواعدَ تحكم كلَّ سطرٍ هنا ─────────────────────────────────────────
 * ① **الكميةُ من السجل القانوني لا من سجل الدوام**: `unit_party_awards` بحكم
 *    الطرف (`party='operator'`) — و«**بعد اكتمال أحكام الأطراف**» شرطٌ حرفيّ:
 *    واقعةٌ لم تبلغ `parties_approved` **لا تدخل أجرًا** ويُعلَن استبعادُها.
 * ② **الوحدةُ يحددها نموذجُ أجر العقد** (`pay_models.code`): بالطن ⇒ أطنانُه ·
 *    بالنقلة ⇒ نقلاتُه. ولا تُخلط وحدةُ عقدٍ بوحدةِ آخر — «كلٌّ يُقرأ بعقده».
 * ③ **لا تسعيرَ ملفَّق**: كميةٌ بلا معدلٍ في اللقطة، أو نموذجٌ زمنيٌّ في مسارٍ
 *    إنتاجي، أو صفرُ وحداتٍ محكومة — كلُّها **تُعلَن سطرًا بسببها** ولا تُصفَّر.
 */

namespace App\Services\Payroll;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/PayrollRunService.php';

class ProductionPathService
{
    /**
     * «**بعد اكتمال أحكام الأطراف**» — الحالاتُ التي بلغت فيها الواقعةُ حكمَ
     * أطرافها (`parties_approved` هي عينُ اكتمال بطاقات الأطراف في محرّك
     * الوحدات)، وما بعدها. وما دونها **لا يدخل أجرًا**.
     */
    const RULED_STATES = array('parties_approved', 'sales_approved', 'converted');

    /** نموذجُ الأجر ⇒ وحدةُ القياس التي يُدفع بها (CON-01 §3.1). */
    const MODEL_UNIT = array(
        'per_ton' => 'ton', 'per_trip' => 'trip', 'per_meter' => 'meter',
        'hourly' => 'hour', 'daily' => 'day', 'per_shift' => 'shift',
    );

    /** طريقةُ الاحتساب المقبولةُ لكل وحدة — المعدلُ يُقرأ منها. */
    const UNIT_METHODS = array(
        'ton' => array('per_unit'), 'trip' => array('per_unit'), 'meter' => array('per_unit'),
        'cbm' => array('per_unit'), 'hour' => array('per_hour'), 'day' => array('per_day'),
        'shift' => array('per_shift'),
    );

    /** أسسُ الحوافز التي تُحتسب من كميةٍ منجزة. */
    const QTY_BASES = array('unit', 'threshold');

    /**
     * احتسابُ المسار الإنتاجي لدورةٍ — يُشغَّل بعد `bindSnapshots`.
     *
     * @return array{ok:bool,code:int,reason:string,persons:int,produced:int,
     *               incentives:int,declared:int}
     */
    public static function compute($conn, $gate, $companyId, $runId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'persons' => 0,
                     'produced' => 0, 'incentives' => 0, 'declared' => 0);
        $runId = (int) $runId;
        $run = PayrollRunService::runOf($gate, $runId);
        if (!$run) { $out['code'] = 404; $out['reason'] = 'الدورة غير موجودة في نطاقك'; return $out; }
        if (!in_array((string) $run['state'], array('Calculated', 'Blocked'), true)) {
            $out['code'] = 423;
            $out['reason'] = 'المسار الإنتاجي يعمل بعد ربط اللقطات — الدورة «' . $run['state'] . '»';
            return $out;
        }
        $pFrom = (string) $run['period_from'];
        $pTo   = (string) $run['period_to'];

        // أسطرُ المسار المشروعي وحدَها (المؤسسيُّ للشريحة ②)
        $lines = array();
        try {
            $lines = $gate->scopedQuery(array('scope' => array('l' => 'payroll_lines')),
                "SELECT l.* FROM payroll_lines l
                  WHERE {TENANT_SCOPE} AND l.run_id = ? AND l.path = 'project'
                    AND l.line_kind = 'component'
                  ORDER BY l.person_id, l.id", array($runId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $lines'); $lines = array(); }
        if (!$lines) {
            $out['ok'] = true; $out['code'] = 200;
            $out['reason'] = 'لا أسطر مشروعية في هذه الدورة — لا شيء للمسار الإنتاجي';
            return $out;
        }

        // مولَّداتُ التشغيل السابق تُبنى من الصفر (مشتقّةٌ لا وقائعُ مستقلة)
        try {
            $conn->query("DELETE FROM payroll_lines WHERE run_id = {$runId}
                           AND line_kind IN ('production','incentive')");
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'لا يوقف'); /* لا يوقف */ }

        $byPerson = array();
        foreach ($lines as $l) { $byPerson[(int) $l['person_id']][] = $l; }

        $produced = 0; $incentives = 0; $declared = 0;

        foreach ($byPerson as $personId => $rows) {
            $contractId = (int) $rows[0]['contract_id'];
            $snapshotId = (int) $rows[0]['snapshot_id'];
            $payload = self::payloadOf($gate, $snapshotId);
            if ($payload === null) { continue; }

            $head = isset($payload['head']) ? $payload['head'] : array();
            $components = isset($payload['components']) ? $payload['components'] : array();
            $rules = isset($payload['incentives']) ? $payload['incentives'] : array();
            $bearersMap = isset($payload['cost_bearers']) ? $payload['cost_bearers'] : array();

            $model = self::payModel($conn, (int) (isset($head['pay_model_id']) ? $head['pay_model_id'] : 0));
            if ($model === null) {
                self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'production',
                    'production:no_model', null, null, null, null, 'pending_slice',
                    'لا نموذج أجر على العقد — لا وحدة تقاس ولا معدل يقرأ');
                $declared++; continue;
            }

            $unit = isset(self::MODEL_UNIT[$model['code']]) ? self::MODEL_UNIT[$model['code']] : null;

            // ── ① الكميةُ المحكومةُ من السجل القانوني ──────────────────────
            $awarded = null;
            if ($unit !== null) {
                $awarded = self::awardedQty($gate, $personId, $unit, $pFrom, $pTo);
            }

            if ($unit === null) {
                // نماذجُ الحافزِ المحض (incentive_only · commission · performance_bonus)
                // لا وحدةَ أجرٍ لها — أجرُها كلُّه من قاعدة الحافز أدناه.
                if ((string) $model['calc_path'] !== 'production' && (string) $model['calc_path'] !== 'mixed') {
                    self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'production',
                        'production:time_model', null, null, null, null, 'pending_slice',
                        'نموذج «' . $model['label_ar'] . '» زمني في عقد مشروعي — '
                        . 'لا يحتسب بمسار إنتاجي (§3: كل يقرأ بمساره)');
                    $declared++;
                }
            } elseif ($awarded['qty'] <= 0) {
                self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'production',
                    'production:' . $unit, 0.0, null, null, null, 'pending_slice',
                    'صفر وحدات **محكومة** ب«' . $unit . '» في الفترة — ' . $awarded['why']);
                $declared++;
            } else {
                $rate = self::unitRate($components, $unit);
                if ($rate === null) {
                    self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'production',
                        'production:' . $unit, $awarded['qty'], null, null, null, 'pending_slice',
                        $awarded['qty'] . ' ' . $unit . ' محكومة — **ولا معدل وحدة في اللقطة**: '
                        . 'لا تسعير ملفق (§4: من العقد لا من اجتهاد)');
                    $declared++;
                } else {
                    $bearers = self::bearersFor($bearersMap, $rate['component_ref'], $components, $rate['component_id']);
                    $gross = round($awarded['qty'] * $rate['rate'], 2);
                    foreach ($bearers as $b) {
                        $pct = round((float) $b['percent'], 2);
                        self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'production',
                            $rate['component_ref'], $awarded['qty'], $rate['rate'],
                            $b['bearer_type'], $b['bearer_id'],
                            'computed',
                            $awarded['qty'] . ' ' . $unit . ' × ' . $rate['rate'] . ' (من اللقطة) — '
                            . $awarded['refs'], round($gross * $pct / 100.0, 2), $pct);
                    }
                    $produced++;
                }
            }

            // ── ② الحوافزُ من قاعدتها بسقفها وحدِّها ───────────────────────
            foreach ($rules as $rule) {
                $basis = (string) $rule['basis'];
                if (!in_array($basis, self::QTY_BASES, true)) {
                    self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'incentive',
                        'rule#' . $rule['id'], null, null, null, null, 'pending_slice',
                        'حافز بأساس «' . $basis . '» — مصدر قياسه ليس كمية منجزة؛ يعلن ولا يخترع');
                    $declared++;
                    continue;
                }
                $qty = ($unit !== null && $awarded !== null) ? $awarded['qty'] : 0.0;
                $rateV = $rule['rate'] !== null ? (float) $rule['rate'] : null;
                if ($rateV === null || $rateV <= 0) {
                    self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'incentive',
                        'rule#' . $rule['id'], $qty, null, null, null, 'pending_slice',
                        'قاعدة حافز بلا معدل — لا تصير مالا');
                    $declared++;
                    continue;
                }
                $countable = $qty;
                if ($basis === 'threshold') {
                    $th = $rule['threshold'] !== null ? (float) $rule['threshold'] : 0.0;
                    $countable = max(0.0, $qty - $th);
                }
                $amount = round($countable * $rateV, 2);
                $capNote = '';
                if ($rule['cap'] !== null && (float) $rule['cap'] > 0 && $amount > (float) $rule['cap']) {
                    $amount = round((float) $rule['cap'], 2); $capNote = ' · مقصوص بالسقف';
                }
                if ($rule['floor'] !== null && (float) $rule['floor'] > 0 && $amount < (float) $rule['floor']) {
                    $amount = round((float) $rule['floor'], 2); $capNote = ' · رفع للحد الأدنى';
                }
                if ($amount <= 0) { continue; }

                // ── التوزيعُ على المستفيدين بنسبهم (Σ=100٪) ────────────────
                $allocs = isset($rule['allocations']) && $rule['allocations']
                          ? $rule['allocations']
                          : array(array('beneficiary_type' => 'employee',
                                        'beneficiary_id' => $personId, 'percent' => 100.00));
                foreach ($allocs as $a) {
                    if ((string) $a['beneficiary_type'] !== 'employee') {
                        self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'incentive',
                            'rule#' . $rule['id'], $countable, $rateV, null, null, 'pending_slice',
                            'مستفيد بمسمى وظيفي «' . $a['beneficiary_id'] . '» — يحل إلى أشخاص '
                            . 'وقت الاحتساب وبيته شريحة المسير ⑤');
                        $declared++;
                        continue;
                    }
                    $share = round($amount * ((float) $a['percent']) / 100.0, 2);
                    self::addLine($gate, $runId, (int) $a['beneficiary_id'], $contractId, $snapshotId,
                        'incentive', 'rule#' . $rule['id'], $countable, $rateV, null, null, 'computed',
                        'حافز «' . $rule['incentive_type'] . '» بأساس ' . $basis . ': '
                        . $countable . ' × ' . $rateV . $capNote . ' × ' . $a['percent'] . '٪',
                        $share, (float) $a['percent']);
                    $incentives++;
                }
            }
        }

        self::audit($conn, $companyId, $actor, $runId, $produced, $incentives, $declared);

        $out['ok'] = true; $out['code'] = 200;
        $out['persons'] = count($byPerson);
        $out['produced'] = $produced; $out['incentives'] = $incentives; $out['declared'] = $declared;
        $out['reason'] = 'المسار الإنتاجي: ' . $produced . ' سطر إنتاج · ' . $incentives
                       . ' حافزا · ' . $declared . ' معلنا بلا مصدر';
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // المصادر
    // ═════════════════════════════════════════════════════════════════════

    /**
     * الكميةُ المحكومةُ لمشغّلٍ بوحدةٍ في فترة — **من السجل القانوني بعد
     * اكتمال أحكام الأطراف** حصرًا.
     * @return array{qty:float,why:string,refs:string,excluded:int}
     */
    public static function awardedQty($gate, $personId, $unit, $pFrom, $pTo)
    {
        $out = array('qty' => 0.0, 'why' => '', 'refs' => '', 'excluded' => 0);
        $ruled = "'" . implode("','", self::RULED_STATES) . "'";
        $rows = array();
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('a' => 'unit_party_awards'), 'enrich' => array('e' => 'unit_entries')),
                "SELECT a.id, a.qty_due, a.source_ref, e.state, e.entry_date
                   FROM unit_party_awards a
                   LEFT JOIN unit_entries e ON e.id = a.source_ref AND a.source_kind = 'unit_record'
                  WHERE {TENANT_SCOPE} AND a.party = 'operator' AND a.party_ref = ?
                    AND a.award_unit_type = ? AND a.deleted_at IS NULL
                    AND e.entry_date BETWEEN ? AND ?
                  ORDER BY a.id",
                array((int) $personId, (string) $unit, (string) $pFrom, (string) $pTo));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $rows'); $rows = array(); }

        if (!$rows) {
            $out['why'] = 'لا وحدات مسجلة لهذا المشغل بهذه الوحدة في الفترة';
            return $out;
        }
        $refs = array();
        foreach ($rows as $r) {
            if (!in_array((string) $r['state'], self::RULED_STATES, true)) {
                $out['excluded']++;
                continue;
            }
            $out['qty'] += (float) $r['qty_due'];
            $refs[] = 'واقعة#' . (int) $r['source_ref'];
        }
        $out['qty'] = round($out['qty'], 2);
        $out['refs'] = $refs ? ('مراجعها: ' . implode(' · ', array_slice($refs, 0, 8))
                                . (count($refs) > 8 ? ' …' : '')) : '';
        if ($out['excluded'] > 0) {
            $out['why'] = $out['excluded'] . ' واقعة **لم تكتمل أحكام أطرافها** فلا تدخل أجرا (§3-②)';
            if ($out['qty'] > 0) { $out['refs'] .= ' · و' . $out['why']; }
        } elseif ($out['qty'] <= 0) {
            $out['why'] = 'الوقائع محكومة وكمية استحقاقها صفر';
        }
        return $out;
    }

    /** معدلُ الوحدة من اللقطة — بطريقةٍ تطابق وحدةَ النموذج. */
    public static function unitRate(array $components, $unit)
    {
        $methods = isset(self::UNIT_METHODS[$unit]) ? self::UNIT_METHODS[$unit] : array('per_unit');
        foreach ($components as $c) {
            if (in_array((string) $c['calc_method'], $methods, true)
                && $c['rate'] !== null && (float) $c['rate'] > 0) {
                return array('rate' => round((float) $c['rate'], 4),
                             'component_id' => (int) $c['id'],
                             'component_ref' => 'component#' . (int) $c['id']);
            }
        }
        return null;
    }

    /** جهاتُ تحمّل مكوّنٍ من اللقطة — أو إشارتُه المفردة. */
    private static function bearersFor(array $bearersMap, $ref, array $components, $componentId)
    {
        if (!empty($bearersMap[$ref])) { return $bearersMap[$ref]; }
        foreach ($components as $c) {
            if ((int) $c['id'] === (int) $componentId) {
                return array(array(
                    'bearer_type' => isset($c['cost_bearer_type']) ? $c['cost_bearer_type'] : null,
                    'bearer_id' => isset($c['cost_bearer_id']) ? $c['cost_bearer_id'] : null,
                    'percent' => 100.00));
            }
        }
        return array(array('bearer_type' => null, 'bearer_id' => null, 'percent' => 100.00));
    }

    private static function payModel($conn, $modelId)
    {
        if ((int) $modelId <= 0) { return null; }
        $st = $conn->prepare("SELECT code, label_ar, calc_path FROM pay_models WHERE id = ? LIMIT 1");
        if (!$st) { return null; }
        $mid = (int) $modelId;
        $st->bind_param('i', $mid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    private static function payloadOf($gate, $snapshotId)
    {
        $s = null;
        try { $s = $gate->selectOne('contract_snapshots', array('where' => array('id' => (int) $snapshotId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $s'); $s = null; }
        if (!$s) { return null; }
        $p = json_decode((string) $s['snapshot_json'], true);
        return is_array($p) ? $p : null;
    }

    private static function addLine($gate, $runId, $personId, $contractId, $snapshotId, $kind,
                                    $ref, $qty, $rate, $bearerType, $bearerId, $calcState, $note,
                                    $amount = null, $percent = 100.00)
    {
        try {
            $gate->insert('payroll_lines', array(
                'run_id' => (int) $runId, 'person_id' => (int) $personId,
                'contract_id' => (int) $contractId, 'snapshot_id' => (int) $snapshotId,
                'path' => 'project', 'component_ref' => (string) $ref,
                'line_kind' => (string) $kind,
                'qty' => $qty, 'rate' => $rate, 'amount' => $amount,
                'bearer_type' => $bearerType !== null ? (string) $bearerType : null,
                'bearer_id' => !empty($bearerId) ? (int) $bearerId : null,
                'percent' => $percent,
                'calc_state' => (string) $calcState,
                'note' => mb_substr((string) $note, 0, 255),
            ));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'H-09-3 addLine'); error_log('H-09-3 addLine: ' . $t->getMessage()); }
    }

    private static function audit($conn, $companyId, $actor, $runId, $produced, $incentives, $declared)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'payroll_runs', 'production_path', (int) $runId, array(),
            array('produced' => $produced, 'incentives' => $incentives, 'declared' => $declared),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
