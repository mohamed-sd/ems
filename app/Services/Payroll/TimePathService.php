<?php
/**
 * app/Services/Payroll/TimePathService.php — المسارُ الزمني (H-09-②)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-01 §3-①: «المؤسسي الزمني — للدائمين: الزمنُ وفترةُ الخدمة (شهرٌ · يومٌ ·
 * ساعةٌ إضافية) **والمكوّناتُ الشهرية**».
 * ENT-01 §4: «من اللقطة: مبلغٌ ثابتٌ أو محسوبٌ بطريقته … **× مدةِ الاستحقاق في
 * الفترة**» · «الإضافي … **بمعدّلاتها من العقد لا من اجتهاد**» · «خصمُ …
 * **الغياب** … كلٌّ بمرجعه؛ **ولا خصمَ بلا مستند**».
 *
 * ── ثلاثُ قواعدَ تحكم كلَّ سطرٍ هنا ─────────────────────────────────────────
 * ① **التناسبُ يُشتقّ ولا يُدخَل**: أيامُ الاستحقاق = تقاطعُ (مدةِ العقد ∩ سريانِ
 *    المكوّن ∩ مدةِ الدورة) — والأيامُ تُخزَّن على السطر فيُرى **لماذا** صار
 *    المبلغُ جزءًا من الشهر لا كلَّه.
 * ② **الخصمُ سطرٌ ظاهرٌ سالب** لا نقصٌ صامتٌ في مبلغٍ آخر — «بندًا ظاهرًا لا
 *    خصمًا صامتًا» (قاعدةُ الوثائق المتكررة).
 * ③ **ما لا مصدرَ له يُعلَن**: نوعُ غيابٍ غيرُ مصنَّفٍ لا يُخصم تخمينًا · وساعةُ
 *    إضافيٍّ بلا معدلٍ **في العقد** لا تصير مالًا — كلاهما سطرٌ بحالته وسببه.
 *
 * والمسارُ **المؤسسيُّ حصرًا** (`path='institutional'`): المشروعيُّ يقرأ من سجل
 * الوحدات وبيتُه الشريحة ③ — ولا تُحتسب فئةٌ بمسار غيرها.
 */

namespace App\Services\Payroll;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/PayrollRunService.php';

class TimePathService
{
    /** طرائقُ الاحتساب التي يعالجها المسارُ الزمني. */
    const TIME_METHODS = array('fixed_amount', 'pct_basic', 'per_day', 'per_shift');

    /**
     * احتسابُ المسار الزمني لدورةٍ — يُشغَّل بعد `bindSnapshots`.
     *
     * @return array{ok:bool,code:int,reason:string,persons:int,prorated:int,
     *               deductions:int,overtime:int,declared:int}
     */
    public static function compute($conn, $gate, $companyId, $runId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'persons' => 0,
                     'prorated' => 0, 'deductions' => 0, 'overtime' => 0, 'declared' => 0);
        $runId = (int) $runId;
        $run = PayrollRunService::runOf($gate, $runId);
        if (!$run) { $out['code'] = 404; $out['reason'] = 'الدورةُ غير موجودةٍ في نطاقك'; return $out; }
        if (!in_array((string) $run['state'], array('Calculated', 'Blocked'), true)) {
            $out['code'] = 423;
            $out['reason'] = 'المسارُ الزمنيُّ يعمل بعد ربط اللقطات — الدورةُ «' . $run['state'] . '»';
            return $out;
        }

        $pFrom = (string) $run['period_from'];
        $pTo   = (string) $run['period_to'];
        $periodDays = self::daysBetween($pFrom, $pTo);
        if ($periodDays <= 0) { $out['code'] = 422; $out['reason'] = 'مدةُ الدورة صفرية'; return $out; }

        // أسطرُ المكوّنات المؤسسية وحدَها (المشروعيُّ للشريحة ③)
        $lines = array();
        try {
            $lines = $gate->scopedQuery(array('scope' => array('l' => 'payroll_lines')),
                "SELECT l.* FROM payroll_lines l
                  WHERE {TENANT_SCOPE} AND l.run_id = ? AND l.path = 'institutional'
                    AND l.line_kind = 'component'
                  ORDER BY l.person_id, l.id", array($runId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $lines'); $lines = array(); }
        if (!$lines) {
            $out['ok'] = true; $out['code'] = 200;
            $out['reason'] = 'لا أسطرَ مؤسسيةً في هذه الدورة — لا شيءَ للمسار الزمني';
            return $out;
        }

        // نظّف مولَّدات التشغيل السابق (الإضافيُّ والخصمُ مشتقّان لا وقائعُ)
        try {
            $conn->query("DELETE FROM payroll_lines WHERE run_id = {$runId}
                           AND line_kind IN ('overtime','absence_deduction')");
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'لا يوقف'); /* لا يوقف */ }

        $byPerson = array();
        foreach ($lines as $l) { $byPerson[(int) $l['person_id']][] = $l; }

        $prorated = 0; $deductions = 0; $overtime = 0; $declared = 0;

        foreach ($byPerson as $personId => $rows) {
            $contractId = (int) $rows[0]['contract_id'];
            $snapshotId = (int) $rows[0]['snapshot_id'];
            $payload = self::payloadOf($gate, $snapshotId);
            if ($payload === null) { continue; }

            $head = isset($payload['head']) ? $payload['head'] : array();
            $components = isset($payload['components']) ? $payload['components'] : array();
            $compById = array();
            foreach ($components as $pc) { $compById[(int) $pc['id']] = $pc; }

            // ── ① التناسبُ لكل سطرِ مكوّن ─────────────────────────────────
            $personNet = 0.0;
            foreach ($rows as $l) {
                $compId = (int) str_replace('component#', '', (string) $l['component_ref']);
                $pc = isset($compById[$compId]) ? $compById[$compId] : null;
                if ($pc === null) { continue; }

                $win = self::entitlementWindow($head, $pc, $pFrom, $pTo);
                $entitled = $win['days'];

                if ((string) $l['calc_state'] === 'pending_slice'
                    && !in_array((string) $pc['calc_method'], self::TIME_METHODS, true)) {
                    // ليس من طرائق المسار الزمني — يبقى معلَنًا لشريحته
                    self::stamp($gate, (int) $l['id'], array(
                        'entitled_days' => $entitled, 'period_days' => $periodDays));
                    continue;
                }

                // المبلغُ الكاملُ قبل التناسب (من اللقطة كما ثبّتته الشريحة ①)
                $full = self::fullAmount($pc, $components, $l);
                if ($full === null) {
                    self::stamp($gate, (int) $l['id'], array(
                        'entitled_days' => $entitled, 'period_days' => $periodDays,
                        'calc_state' => 'pending_slice',
                        'note' => 'طريقةُ «' . $pc['calc_method'] . '» تحتاج مدخلًا لا مصدرَ له بعد — تُعلَن ولا تُخترع'));
                    $declared++;
                    continue;
                }

                $pct = (float) ($l['percent'] !== null ? $l['percent'] : 100.0);
                $factor = $periodDays > 0 ? ($entitled / $periodDays) : 0.0;
                $amount = round($full * $factor * $pct / 100.0, 2);
                $personNet += $amount;

                if ($entitled < $periodDays) { $prorated++; }

                self::stamp($gate, (int) $l['id'], array(
                    'amount' => $amount, 'entitled_days' => $entitled, 'period_days' => $periodDays,
                    'calc_state' => 'computed',
                    'note' => $entitled < $periodDays
                        ? ('تناسبٌ: ' . $entitled . ' من ' . $periodDays . ' يومًا — ' . $win['why'])
                        : 'شهرٌ كاملٌ من اللقطة'));
            }

            // ── ② الغيابُ — خصمٌ **سطرٌ ظاهرٌ** بمرجعه ────────────────────
            $abs = self::unpaidAbsence($conn, $gate, $companyId, $personId, $pFrom, $pTo);
            foreach ($abs['declared'] as $d) {
                self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'absence_deduction',
                    'absence:' . $d['event_id'], null, null, $periodDays, null,
                    'pending_slice', $d['reason']);
                $declared++;
            }
            if ($abs['days'] > 0 && $personNet > 0) {
                $dailyRate = $personNet / $periodDays;
                $amount = -1 * round($dailyRate * $abs['days'] * ($abs['percent'] / 100.0), 2);
                self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'absence_deduction',
                    'absence:unpaid', $abs['days'], round($dailyRate, 4), $periodDays, $amount,
                    'computed', 'غيابٌ غيرُ مدفوعٍ ' . $abs['days'] . ' يومًا × ' . round($dailyRate, 2)
                              . ' (' . $abs['percent'] . '٪) — ' . $abs['refs']);
                $deductions++;
            }

            // ── ③ الإضافيُّ — «بمعدّلاتها من العقد لا من اجتهاد» ──────────
            $ot = self::timeInput($gate, $runId, $personId, 'overtime_hours');
            if ($ot !== null) {
                $rate = self::overtimeRate($components);
                if ($rate === null) {
                    self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'overtime',
                        'overtime:hours', (float) $ot['qty'], null, $periodDays, null,
                        'pending_slice',
                        'ساعاتُ إضافيٍّ ' . $ot['qty'] . ' بمرجع ' . $ot['doc_ref']
                        . ' — **ولا معدلَ ساعةٍ في العقد**: لا يُحوَّل إلى مالٍ باجتهاد (§4)');
                    $declared++;
                } else {
                    $amount = round((float) $ot['qty'] * $rate, 2);
                    self::addLine($gate, $runId, $personId, $contractId, $snapshotId, 'overtime',
                        'overtime:hours', (float) $ot['qty'], $rate, $periodDays, $amount,
                        'computed', 'إضافيٌّ بمعدل العقد ' . $rate . ' — مرجع ' . $ot['doc_ref']);
                    $overtime++;
                }
            }
        }

        self::audit($conn, $companyId, $actor, $runId, $prorated, $deductions, $overtime, $declared);

        $out['ok'] = true; $out['code'] = 200;
        $out['persons'] = count($byPerson);
        $out['prorated'] = $prorated; $out['deductions'] = $deductions;
        $out['overtime'] = $overtime; $out['declared'] = $declared;
        $out['reason'] = 'المسارُ الزمني: ' . $prorated . ' سطرًا متناسبًا · ' . $deductions
                       . ' خصمَ غيابٍ · ' . $overtime . ' إضافيًّا · ' . $declared . ' معلَنًا بلا مصدر';
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // المكوّنات
    // ═════════════════════════════════════════════════════════════════════

    /**
     * نافذةُ الاستحقاق: تقاطعُ (مدةِ العقد ∩ سريانِ المكوّن ∩ مدةِ الدورة).
     * @return array{days:float,why:string}
     */
    public static function entitlementWindow(array $head, array $pc, $pFrom, $pTo)
    {
        $from = $pFrom; $to = $pTo; $why = array();

        $cs = isset($head['start_date']) ? $head['start_date'] : null;
        $ce = isset($head['end_date']) ? $head['end_date'] : null;
        if ($cs !== null && $cs > $from) { $from = $cs; $why[] = 'بدءُ العقد ' . $cs; }
        if ($ce !== null && $ce < $to)   { $to = $ce;   $why[] = 'نهايةُ العقد ' . $ce; }

        $vf = isset($pc['valid_from']) ? $pc['valid_from'] : null;
        $vt = isset($pc['valid_to']) ? $pc['valid_to'] : null;
        if ($vf !== null && $vf > $from) { $from = $vf; $why[] = 'سريانُ المكوّن ' . $vf; }
        if ($vt !== null && $vt < $to)   { $to = $vt;   $why[] = 'انتهاءُ المكوّن ' . $vt; }

        if ($to < $from) { return array('days' => 0.0, 'why' => 'لا تقاطعَ مع مدة الدورة'); }
        return array('days' => (float) self::daysBetween($from, $to),
                     'why' => $why ? implode(' · ', $why) : 'مدةٌ كاملة');
    }

    /** المبلغُ الكاملُ لمكوّنٍ قبل التناسب — من اللقطة حصرًا. */
    private static function fullAmount(array $pc, array $components, array $line)
    {
        $method = (string) $pc['calc_method'];
        if ($method === 'fixed_amount') { return round((float) $pc['value'], 2); }
        if ($method === 'pct_basic') {
            $basic = 0.0;
            foreach ($components as $c) {
                if ((string) $c['calc_method'] === 'fixed_amount' && (string) $c['component_type'] === 'basic') {
                    $basic += (float) $c['value'];
                }
            }
            return round($basic * ((float) $pc['rate'] / 100.0), 2);
        }
        // `per_day` / `per_shift` يلزمهما عددُ أيامٍ أو ورديّاتٍ مسجَّل — ولا
        // مصدرَ لهما في النظام اليوم؛ فيُعلَنان ولا يُخترع لهما عدّاد.
        return null;
    }

    /** معدلُ ساعة الإضافي **من العقد** — مكوّنٌ بطريقة `per_hour` وحدَه. */
    public static function overtimeRate(array $components)
    {
        foreach ($components as $c) {
            if ((string) $c['calc_method'] === 'per_hour' && $c['rate'] !== null && (float) $c['rate'] > 0) {
                return round((float) $c['rate'], 4);
            }
        }
        return null;
    }

    /**
     * الغيابُ غيرُ المدفوع في الفترة — من `worker_leave_absence` بكتالوجه.
     * نوعٌ غيرُ مصنَّفٍ **لا يُخصم**: يُعلَن سطرًا وينتظر قرارَ مالكه.
     * @return array{days:float,percent:float,refs:string,declared:array}
     */
    public static function unpaidAbsence($conn, $gate, $companyId, $personId, $pFrom, $pTo)
    {
        $out = array('days' => 0.0, 'percent' => 100.0, 'refs' => '', 'declared' => array());
        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('w' => 'worker_leave_absence')),
                "SELECT w.id, w.event_type, w.date_from, w.date_to, w.state
                   FROM worker_leave_absence w
                  WHERE {TENANT_SCOPE} AND w.employee_id = ?
                    AND w.date_from <= ? AND w.date_to >= ?
                  ORDER BY w.id", array((int) $personId, (string) $pTo, (string) $pFrom));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كقائمةٍ فارغة — $rows'); $rows = array(); }
        if (!$rows) { return $out; }

        $refs = array(); $pct = null;
        foreach ($rows as $w) {
            $type = trim((string) $w['event_type']);
            $cat = null;
            try {
                $cats = $gate->scopedQuery(array('scope' => array('a' => 'payroll_absence_types')),
                    "SELECT a.deducts, a.deduct_percent FROM payroll_absence_types a
                      WHERE {TENANT_SCOPE} AND a.event_type = ? AND a.active = 1 LIMIT 1", array($type));
                $cat = $cats ? $cats[0] : null;
            } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $cat'); $cat = null; }

            if ($cat === null) {
                $out['declared'][] = array('event_id' => (int) $w['id'],
                    'reason' => 'غيابٌ من نوع «' . $type . '» **غيرُ مصنَّفٍ في كتالوج الخصم** — '
                              . 'لا يُخصم تخمينًا وينتظر قرارَ التصنيف');
                continue;
            }
            if ((int) $cat['deducts'] !== 1) { continue; }   // إجازةٌ مدفوعة

            $f = max((string) $w['date_from'], (string) $pFrom);
            $t2 = min((string) $w['date_to'], (string) $pTo);
            $d = self::daysBetween($f, $t2);
            if ($d <= 0) { continue; }
            $out['days'] += $d;
            $refs[] = 'غياب#' . (int) $w['id'] . ' (' . $f . '→' . $t2 . ')';
            if ($pct === null) { $pct = (float) $cat['deduct_percent']; }
        }
        if ($pct !== null) { $out['percent'] = $pct; }
        $out['refs'] = implode(' · ', $refs);
        return $out;
    }

    /** مدخلُ زمنٍ يدويٌّ بمستنده. */
    public static function timeInput($gate, $runId, $personId, $kind)
    {
        try {
            $rows = $gate->scopedQuery(array('scope' => array('i' => 'payroll_time_inputs')),
                "SELECT i.qty, i.doc_ref FROM payroll_time_inputs i
                  WHERE {TENANT_SCOPE} AND i.run_id = ? AND i.person_id = ? AND i.kind = ? LIMIT 1",
                array((int) $runId, (int) $personId, (string) $kind));
            return $rows ? $rows[0] : null;
        } catch (\Throwable $t) { return null; }
    }

    /** تسجيلُ مدخلِ زمنٍ — المستندُ إلزاميٌّ خدمةً وبنيةً معًا. */
    public static function recordTimeInput($conn, $gate, $companyId, $runId, $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'id' => null);
        $kind = isset($args['kind']) ? trim((string) $args['kind']) : '';
        if (!in_array($kind, array('overtime_hours', 'unpaid_days', 'night_shifts'), true)) {
            $out['code'] = 422; $out['reason'] = 'نوعُ مدخلٍ غيرُ معروف'; return $out;
        }
        $qty = isset($args['qty']) ? round((float) $args['qty'], 2) : 0.0;
        if ($qty <= 0) { $out['code'] = 422; $out['reason'] = 'الكميةُ موجبة'; return $out; }
        $doc = isset($args['doc_ref']) ? trim((string) $args['doc_ref']) : '';
        if ($doc === '') {
            $out['code'] = 422;
            $out['reason'] = 'مرجعُ المستند إلزامي — «ولا خصمَ بلا مستند» (ENT-01 §4)، والزيادةُ مثلُه';
            return $out;
        }
        $person = isset($args['person_id']) ? (int) $args['person_id'] : 0;
        if ($person <= 0) { $out['code'] = 422; $out['reason'] = 'الشخصُ إلزامي'; return $out; }

        try {
            $out['id'] = (int) $gate->insert('payroll_time_inputs', array(
                'run_id' => (int) $runId, 'person_id' => $person, 'kind' => $kind,
                'qty' => $qty, 'doc_ref' => mb_substr($doc, 0, 120),
                'note' => isset($args['note']) && trim((string) $args['note']) !== ''
                          ? mb_substr(trim((string) $args['note']), 0, 255) : null,
                'created_by' => (int) $actor ?: null,
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false) {
                $out['code'] = 409; $out['reason'] = 'للشخص مدخلٌ بهذا النوع في الدورة سلفًا'; return $out;
            }
            $out['code'] = 422; $out['reason'] = 'تعذّر التسجيل: ' . $t->getMessage(); return $out;
        }
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // مساعدات
    // ═════════════════════════════════════════════════════════════════════

    /** عددُ الأيام شاملًا الطرفين. */
    public static function daysBetween($from, $to)
    {
        $a = strtotime((string) $from); $b = strtotime((string) $to);
        if ($a === false || $b === false || $b < $a) { return 0; }
        return (int) floor(($b - $a) / 86400) + 1;
    }

    private static function stamp($gate, $lineId, array $data)
    {
        try { $gate->update('payroll_lines', $data, array('id' => (int) $lineId)); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'H-09-2 stamp #'); error_log('H-09-2 stamp #' . $lineId . ': ' . $t->getMessage()); }
    }

    private static function addLine($gate, $runId, $personId, $contractId, $snapshotId, $kind,
                                    $ref, $qty, $rate, $periodDays, $amount, $calcState, $note)
    {
        try {
            $gate->insert('payroll_lines', array(
                'run_id' => (int) $runId, 'person_id' => (int) $personId,
                'contract_id' => (int) $contractId, 'snapshot_id' => (int) $snapshotId,
                'path' => 'institutional', 'component_ref' => (string) $ref,
                'line_kind' => (string) $kind,
                'qty' => $qty, 'rate' => $rate,
                'entitled_days' => null, 'period_days' => $periodDays,
                'amount' => $amount, 'percent' => 100.00,
                'calc_state' => (string) $calcState,
                'note' => mb_substr((string) $note, 0, 255),
            ));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'H-09-2 addLine'); error_log('H-09-2 addLine: ' . $t->getMessage()); }
    }

    private static function payloadOf($gate, $snapshotId)
    {
        $s = null;
        try { $s = $gate->selectOne('contract_snapshots', array('where' => array('id' => (int) $snapshotId))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $s'); $s = null; }
        if (!$s) { return null; }
        $p = json_decode((string) $s['snapshot_json'], true);
        return is_array($p) ? $p : null;
    }

    private static function audit($conn, $companyId, $actor, $runId, $prorated, $deductions, $overtime, $declared)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'payroll_runs', 'time_path', (int) $runId, array(),
            array('prorated' => $prorated, 'deductions' => $deductions,
                  'overtime' => $overtime, 'declared' => $declared),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
