<?php
/**
 * app/Services/Payroll/OffsetService.php — المقاصّةُ والسلف (H-09-④)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-01 §4:
 *  · «**بوابةٌ واحدةٌ لكل ما يُصرف خارج المسيّر**: سلفةٌ نقديةٌ · دفعٌ نيابةً عن
 *    العامل (علاجٌ · تذاكرُ · رسوم) · مصروفٌ محمَّلٌ عليه — **كلٌّ بمستنده
 *    وجدولِ استرداده**».
 *  · «خصمُ أقساط السلف والمدفوعاتِ نيابةً والجزاءاتِ والغيابِ من الصافي — كلٌّ
 *    بمرجعه؛ **ولا خصمَ بلا مستند، ولا يتجاوز الصافي حدَّ الحماية المقرَّر**».
 * ENT-01 §8-E4: «سلفةٌ بجدول استرداد ← خصمُ القسط **مرةً واحدةً (UQ)** والرصيدُ ينقص».
 *
 * ── ثلاثُ قواعدَ ─────────────────────────────────────────────────────────────
 * ① **لا خصمَ بلا مستند**: `doc_ref` إلزاميٌّ على السلفة **وعلى سطر الخصم** —
 *    خدمةً وقيدًا بنيويًّا معًا. والخصمُ يرث مستندَ مصدره فلا يوجد خصمٌ يتيم.
 * ② **حدُّ الحماية يقصّ ويعيد الجدولة لا يُلغي**: ما لا يسعه الحدُّ يبقى في
 *    رصيد السلفة ويُخصم في الفترة التالية — والسطرُ يُوسم `rescheduled` بسببه.
 *    وحدٌّ **لم يُقرَّر** (`NULL`) يُعلَن ولا يُفترض له رقم.
 * ③ **العطالة بإعادة البناء**: إعادةُ التشغيل تعكس خصومَ الدورة عن الأرصدة ثم
 *    تعيد الحساب — فلا يتضاعف استردادٌ ولا يُخصم بندٌ مرتين (UQ حزامًا).
 */

namespace App\Services\Payroll;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/PayrollRunService.php';

class OffsetService
{
    const ADVANCE_TYPES = array('cash', 'on_behalf', 'charged');

    /** نوعُ الخصم المقابلُ لنوع السلفة (ENT-01 §8: `source_type`). */
    const TYPE_TO_SOURCE = array('cash' => 'advance', 'on_behalf' => 'on_behalf', 'charged' => 'advance');

    /** الحالاتُ التي تُخصم منها السلفة. */
    const DEDUCTIBLE_STATES = array('active', 'approved');

    // ═════════════════════════════════════════════════════════════════════
    // ① بوابةُ السلفيات
    // ═════════════════════════════════════════════════════════════════════

    /**
     * فتحُ سلفةٍ (مسودة) — «كلٌّ بمستنده وجدولِ استرداده».
     * @return array{ok:bool,code:int,reason:string,advance_id:?int}
     */
    public static function openAdvance($conn, $gate, $companyId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'advance_id' => null);

        $person = isset($args['person_id']) ? (int) $args['person_id'] : 0;
        if ($person <= 0) { $out['code'] = 422; $out['reason'] = 'المستفيد إلزامي'; return $out; }

        $type = isset($args['advance_type']) ? trim((string) $args['advance_type']) : 'cash';
        if (!in_array($type, self::ADVANCE_TYPES, true)) {
            $out['code'] = 422; $out['reason'] = 'نوع صرف خارج الثلاثة (نقدية · نيابة · محمل)'; return $out;
        }
        $amount = isset($args['amount']) ? round((float) $args['amount'], 2) : 0.0;
        if ($amount <= 0) { $out['code'] = 422; $out['reason'] = 'مبلغ السلفة موجب'; return $out; }

        // ── «لا خصمَ بلا مستند» يبدأ من هنا: لا سلفةَ بلا سند ──────────────
        $doc = isset($args['doc_ref']) ? trim((string) $args['doc_ref']) : '';
        if ($doc === '') {
            $out['code'] = 422;
            $out['reason'] = 'مستند الصرف إلزامي — «كل بمستنده» (§4)، ومال يخرج بلا سند لا يخصم لاحقا';
            return $out;
        }
        $issued = isset($args['issued_date']) ? trim((string) $args['issued_date']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issued)) {
            $out['code'] = 422; $out['reason'] = 'تاريخ الصرف إلزامي'; return $out;
        }
        $count = isset($args['installments_count']) ? (int) $args['installments_count'] : 1;
        if ($count < 1) { $out['code'] = 422; $out['reason'] = 'عدد الأقساط واحد فأكثر'; return $out; }

        // جدولُ الاسترداد يُقترح آليًّا (§7-بوابة السلفيات) ويُعدَّل بالمرسل
        $inst = (isset($args['installment_amount']) && trim((string) $args['installment_amount']) !== '')
                ? round((float) $args['installment_amount'], 2)
                : round($amount / $count, 2);
        if ($inst <= 0) { $out['code'] = 422; $out['reason'] = 'قسط الاسترداد موجب'; return $out; }
        if ($inst > $amount) { $out['code'] = 422; $out['reason'] = 'القسط يتجاوز أصل السلفة'; return $out; }

        // المستفيدُ من النطاق
        $emp = null;
        try { $emp = $gate->selectOne('employees', array('columns' => array('id'), 'where' => array('id' => $person))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $emp'); $emp = null; }
        if (!$emp) { $out['code'] = 422; $out['reason'] = 'المستفيد غير موجود في نطاقك'; return $out; }

        try {
            // ⚠ `balance` عمودٌ مولَّد — لا يُكتب (كتابتُه ترفض الصفَّ كلَّه)
            $out['advance_id'] = (int) $gate->insert('employee_advances', array(
                'person_id' => $person, 'advance_type' => $type,
                'amount' => $amount,
                'currency' => isset($args['currency']) && trim((string) $args['currency']) !== ''
                              ? strtoupper(trim((string) $args['currency'])) : null,
                'doc_ref' => mb_substr($doc, 0, 120), 'issued_date' => $issued,
                'installments_count' => $count, 'installment_amount' => $inst,
                'first_deduction_period' => isset($args['first_deduction_period'])
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['first_deduction_period'])
                    ? (string) $args['first_deduction_period'] : $issued,
                'recovered' => 0.00, 'state' => 'draft',
                'note' => isset($args['note']) && trim((string) $args['note']) !== ''
                          ? mb_substr(trim((string) $args['note']), 0, 255) : null,
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذر الفتح: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'employee_advances', 'open', (int) $out['advance_id'],
            array(), array('person' => $person, 'amount' => $amount, 'doc' => $doc));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** اعتمادُ سلفةٍ — **«من أنشأ لا يعتمد»** (403) وبها تصير قابلةً للخصم. */
    public static function approveAdvance($conn, $gate, $companyId, $advanceId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $a = self::advanceOf($gate, $advanceId);
        if (!$a) { $out['code'] = 404; $out['reason'] = 'السلفة غير موجودة في نطاقك'; return $out; }
        if ((string) $a['state'] !== 'draft') {
            $out['code'] = 422; $out['reason'] = 'السلفة «' . $a['state'] . '» — الاعتماد للمسودة'; return $out;
        }
        if ((int) $a['created_by'] > 0 && (int) $a['created_by'] === (int) $actor) {
            $out['code'] = 403; $out['reason'] = 'من أنشأ لا يعتمد — الفصل بنيوي لا اختياري'; return $out;
        }
        try {
            $gate->update('employee_advances', array(
                'state' => 'active', 'approved_by' => (int) $actor ?: null,
                'approved_at' => date('Y-m-d H:i:s')), array('id' => (int) $advanceId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذر الاعتماد: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'employee_advances', 'approve', (int) $advanceId,
            array('state' => 'draft'), array('state' => 'active'));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② المقاصّة داخل الدورة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * احتسابُ المقاصّة لدورةٍ — بعد المسارين ②③.
     *
     * @return array{ok:bool,code:int,reason:string,persons:int,deducted:int,
     *               rescheduled:int,total:float,protection:?float}
     */
    public static function computeOffsets($conn, $gate, $companyId, $runId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'persons' => 0,
                     'deducted' => 0, 'rescheduled' => 0, 'total' => 0.0, 'protection' => null);
        $runId = (int) $runId;
        $run = PayrollRunService::runOf($gate, $runId);
        if (!$run) { $out['code'] = 404; $out['reason'] = 'الدورة غير موجودة في نطاقك'; return $out; }
        if (!in_array((string) $run['state'], array('Calculated', 'Blocked'), true)) {
            $out['code'] = 423;
            $out['reason'] = 'المقاصة تعمل بعد الاحتساب — الدورة «' . $run['state'] . '»';
            return $out;
        }
        $pTo = (string) $run['period_to'];

        // ── العطالة: اعكس خصومَ هذه الدورة عن الأرصدة ثم أعد البناء ───────
        self::reverseRunDeductions($conn, $gate, $runId);

        $protection = self::protectionPercent($gate);
        $out['protection'] = $protection;

        // إجماليُّ كل شخصٍ من أسطر الدورة (يشمل خصمَ الغياب سالبًا — الشريحة ②)
        $totals = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('l' => 'payroll_lines')),
                "SELECT l.person_id, ROUND(SUM(COALESCE(l.amount,0)),2) AS gross
                   FROM payroll_lines l
                  WHERE {TENANT_SCOPE} AND l.run_id = ?
                  GROUP BY l.person_id", array($runId));
            foreach ($rows as $r) { $totals[(int) $r['person_id']] = (float) $r['gross']; }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $totals'); $totals = array(); }
        if (!$totals) {
            $out['ok'] = true; $out['code'] = 200;
            $out['reason'] = 'لا أسطر محتسبة — لا مقاصة';
            return $out;
        }

        $deducted = 0; $rescheduled = 0; $total = 0.0;

        foreach ($totals as $personId => $gross) {
            if ($gross <= 0) { continue; }

            // ── حدُّ الحماية: ما يجوز خصمُه في هذه الفترة ──────────────────
            $allowed = ($protection === null)
                       ? $gross                                   // لم يُقرَّر حدٌّ — يُعلَن ولا يُفترض
                       : round($gross * (1 - ($protection / 100.0)), 2);
            if ($allowed < 0) { $allowed = 0.0; }
            $remaining = $allowed;

            $advances = self::dueAdvances($gate, $personId, $pTo);
            foreach ($advances as $adv) {
                $balance = round((float) $adv['amount'] - (float) $adv['recovered'], 2);
                if ($balance <= 0) { continue; }
                $requested = min(round((float) $adv['installment_amount'], 2), $balance);
                if ($requested <= 0) { continue; }

                $take = min($requested, $remaining);
                $wasCut = ($take < $requested);
                if ($take <= 0) {
                    // لا مساحةَ في هذه الفترة — يُرحَّل كاملًا بسببه المكتوب
                    self::writeDeduction($gate, $runId, $personId, $adv, 0.0, $requested, 1,
                        'حد الحماية (' . $protection . '٪ من ' . $gross . ') لا يسع قسطا هذه الفترة — '
                        . 'يرحل كاملا والرصيد باق ' . $balance);
                    $rescheduled++;
                    continue;
                }

                $okWrite = self::writeDeduction($gate, $runId, $personId, $adv, $take, $requested,
                    $wasCut ? 1 : 0,
                    $wasCut
                        ? ('قص بحد الحماية: المستحق ' . $requested . ' والمسموح ' . $take
                           . ' — الباقي ' . round($requested - $take, 2) . ' يرحل للفترة التالية')
                        : ('قسط سلفة بمستندها ' . $adv['doc_ref']));
                if (!$okWrite) { continue; }

                self::applyRecovery($gate, (int) $adv['id'], $take);
                $remaining = round($remaining - $take, 2);
                $total += $take;
                $deducted++;
                if ($wasCut) { $rescheduled++; }
                if ($remaining <= 0) { break; }
            }
            $out['persons']++;
        }

        self::audit($conn, $companyId, $actor, 'payroll_runs', 'offsets', $runId, array(),
            array('deducted' => $deducted, 'rescheduled' => $rescheduled,
                  'total' => round($total, 2), 'protection' => $protection));

        $out['ok'] = true; $out['code'] = 200;
        $out['deducted'] = $deducted; $out['rescheduled'] = $rescheduled;
        $out['total'] = round($total, 2);
        $out['reason'] = 'المقاصة: ' . $deducted . ' خصما بمجموع ' . round($total, 2)
                       . ' · ' . $rescheduled . ' مرحلا بحد الحماية'
                       . ($protection === null ? ' · **لا حد حماية مقررا** (يعلن ولا يفترض)'
                                               : (' · الحد ' . $protection . '٪'));
        return $out;
    }

    /** صافي شخصٍ في دورة = إجماليُّ أسطره − خصومُه. */
    public static function netOf($gate, $runId, $personId)
    {
        $gross = 0.0; $ded = 0.0;
        try {
            $r = $gate->scopedQuery(array('scope' => array('l' => 'payroll_lines')),
                "SELECT ROUND(SUM(COALESCE(l.amount,0)),2) g FROM payroll_lines l
                  WHERE {TENANT_SCOPE} AND l.run_id = ? AND l.person_id = ?",
                array((int) $runId, (int) $personId));
            $gross = $r ? (float) $r[0]['g'] : 0.0;
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل بقيمة 0.0 — $gross'); $gross = 0.0; }
        try {
            $r = $gate->scopedQuery(array('scope' => array('d' => 'payroll_deductions')),
                "SELECT ROUND(SUM(d.amount),2) s FROM payroll_deductions d
                  WHERE {TENANT_SCOPE} AND d.run_id = ? AND d.person_id = ?",
                array((int) $runId, (int) $personId));
            $ded = $r && $r[0]['s'] !== null ? (float) $r[0]['s'] : 0.0;
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل بقيمة 0.0 — $ded'); $ded = 0.0; }
        return round($gross - $ded, 2);
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ قراءاتٌ ومساعدات
    // ═════════════════════════════════════════════════════════════════════

    /** حدُّ الحماية المقرَّر — أو `null` حين **لم يُقرَّر** (لا يُفترض رقم). */
    public static function protectionPercent($gate)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('s' => 'payroll_settings')),
                "SELECT s.protection_percent p FROM payroll_settings s WHERE {TENANT_SCOPE} LIMIT 1");
            if ($rows && $rows[0]['p'] !== null) { return round((float) $rows[0]['p'], 2); }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'لا حد مقروءا'); /* لا حدَّ مقروءًا */ }
        return null;
    }

    /** السلفُ المستحقُّ خصمُها لشخصٍ حتى نهاية الفترة — الأقدمُ أولًا. */
    public static function dueAdvances($gate, $personId, $periodTo)
    {
        $states = "'" . implode("','", self::DEDUCTIBLE_STATES) . "'";
        try {
            return $gate->scopedQuery(array('scope' => array('a' => 'employee_advances')),
                "SELECT a.* FROM employee_advances a
                  WHERE {TENANT_SCOPE} AND a.person_id = ? AND COALESCE(a.is_deleted,0)=0
                    AND a.state IN ({$states})
                    AND (a.first_deduction_period IS NULL OR a.first_deduction_period <= ?)
                    AND a.recovered < a.amount
                  ORDER BY a.issued_date, a.id", array((int) $personId, (string) $periodTo));
        } catch (\Throwable $t) { return array(); }
    }

    public static function advanceOf($gate, $advanceId)
    {
        try { return $gate->selectOne('employee_advances', array('where' => array('id' => (int) $advanceId))); }
        catch (\Throwable $t) { return null; }
    }

    public static function deductionsOf($gate, $runId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('d' => 'payroll_deductions')),
                "SELECT d.* FROM payroll_deductions d
                  WHERE {TENANT_SCOPE} AND d.run_id = ?
                  ORDER BY d.person_id, d.id", array((int) $runId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function advancesOf($gate, $personId = 0)
    {
        try {
            $where = (int) $personId > 0 ? ' AND a.person_id = ?' : '';
            $params = (int) $personId > 0 ? array((int) $personId) : array();
            return $gate->scopedQuery(array('scope' => array('a' => 'employee_advances')),
                "SELECT a.* FROM employee_advances a
                  WHERE {TENANT_SCOPE} AND COALESCE(a.is_deleted,0)=0" . $where . "
                  ORDER BY a.id DESC", $params);
        } catch (\Throwable $t) { return array(); }
    }

    /** سطرُ خصمٍ — يرث مستندَ مصدره فلا يوجد خصمٌ يتيم. */
    private static function writeDeduction($gate, $runId, $personId, array $adv, $amount, $requested, $resched, $note)
    {
        $srcType = isset(self::TYPE_TO_SOURCE[(string) $adv['advance_type']])
                   ? self::TYPE_TO_SOURCE[(string) $adv['advance_type']] : 'advance';
        try {
            $gate->insert('payroll_deductions', array(
                'run_id' => (int) $runId, 'person_id' => (int) $personId,
                'source_type' => $srcType, 'source_id' => (int) $adv['id'],
                'amount' => round((float) $amount, 2),
                'requested_amount' => round((float) $requested, 2),
                'doc_ref' => mb_substr((string) $adv['doc_ref'], 0, 120),
                'rescheduled' => (int) $resched,
                'note' => mb_substr((string) $note, 0, 255),
            ));
            return true;
        } catch (\Throwable $t) {
            // UQ: «فلا يُخصم بندٌ مرتين» — التكرارُ يُتجاهل بلا ضجيج
            return false;
        }
    }

    /** تحريكُ المستردّ — و`balance` مولَّدٌ يتبعه (لا يُكتب). */
    private static function applyRecovery($gate, $advanceId, $amount)
    {
        $a = self::advanceOf($gate, $advanceId);
        if (!$a) { return; }
        $rec = round((float) $a['recovered'] + (float) $amount, 2);
        if ($rec > (float) $a['amount']) { $rec = round((float) $a['amount'], 2); }
        $data = array('recovered' => $rec);
        if ($rec >= (float) $a['amount']) { $data['state'] = 'settled'; }
        try { $gate->update('employee_advances', $data, array('id' => (int) $advanceId)); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'H-09-4 recovery #'); error_log('H-09-4 recovery #' . $advanceId . ': ' . $t->getMessage()); }
    }

    /** عكسُ خصوم دورةٍ عن الأرصدة ثم كنسُها — عطالةُ إعادة التشغيل. */
    private static function reverseRunDeductions($conn, $gate, $runId)
    {
        $rows = self::deductionsOf($gate, $runId);
        foreach ($rows as $d) {
            if ((float) $d['amount'] <= 0) { continue; }
            $a = self::advanceOf($gate, (int) $d['source_id']);
            if (!$a) { continue; }
            $rec = round((float) $a['recovered'] - (float) $d['amount'], 2);
            if ($rec < 0) { $rec = 0.0; }
            try {
                $gate->update('employee_advances',
                    array('recovered' => $rec, 'state' => $rec >= (float) $a['amount'] ? 'settled' : 'active'),
                    array('id' => (int) $a['id']));
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'أفضل جهد'); /* أفضلُ جهد */ }
        }
        try { $conn->query("DELETE FROM payroll_deductions WHERE run_id = " . (int) $runId); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'لا يوقف'); /* لا يوقف */ }
    }

    private static function audit($conn, $companyId, $actor, $table, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', $table, $action, (int) $rowId, $before, $after,
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
