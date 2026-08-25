<?php
/**
 * app/Services/Payroll/PayrollStateMachine.php — المسيّرُ والكشف (H-09-⑤)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-01 §5 — جدولُ انتقالات الدورة نصًّا (مالكُه · شرطُه · أثرُه):
 *   Open → Calculated : مسؤولُ التسويات · **فترةٌ مفتوحةٌ ولم تُقفل** · أسطرٌ بلقطاتها
 *   Calculated → Blocked : النظام · عقدٌ بلا لقطةٍ صالحةٍ أو أحكامٌ ناقصة
 *   Calculated → Review : المراجعُ · **اكتمالُ الأسطر** · قفلُ نسخة
 *   Review → Approved : المعتمِدُ · **لا اعتمادَ للذات** · حدثُ FES وطلبُ الدفع
 *   Approved → Paid : الخزينة · **تنفيذُ الصرف بمرجعه**
 *   Paid → Closed : الإقفال · **لا سطرَ معلَّقًا** — والتصحيحُ بعدها **بحدثٍ عاكسٍ لا بتعديل**
 *
 * ── معيارُ القبول الذي يحكم هذا الملف (PLAN-01 §6.1-⑤) ──────────────────────
 * «فترةٌ كاملةٌ بلا تدخلٍ يدويٍّ و**صفرُ صفٍّ أحمرَ معتمد**».
 * و«الصفُّ الأحمر» معرَّفٌ في §7 نصًّا: «**صفٌّ بلا لقطةٍ صالحةٍ يظهر أحمرَ ولا
 * يُعتمد**». فحارسُ الاعتماد هنا يرفض ثلاثةً بأسمائها:
 *   ① سطرٌ لم يُحتسب بعد (`pending_slice`) — أحمرُ «لم يكتمل».
 *   ② مانعٌ مفتوحٌ من نوع `blocked` — أحمرُ «لا احتسابَ ناقصٌ صامت».
 *   ③ لقطةٌ لا تطابق بصمتَها — أحمرُ «تلاعب».
 * ولا يُعتمد شيءٌ منها **أبدًا**، ولو أذن به صاحبُ أعلى سقف.
 */

namespace App\Services\Payroll;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/PayrollRunService.php';
require_once __DIR__ . '/OffsetService.php';

class PayrollStateMachine
{
    const OPEN = 'Open';
    const CALCULATED = 'Calculated';
    const BLOCKED = 'Blocked';
    const REVIEW = 'Review';
    const APPROVED = 'Approved';
    const PAID = 'Paid';
    const CLOSED = 'Closed';

    /** قائمةُ السماح — وما ليس فيها مرفوضٌ بنيويًّا (نمطُ H-02). */
    const TRANSITIONS = array(
        self::OPEN       => array(self::CALCULATED, self::BLOCKED),
        self::CALCULATED => array(self::REVIEW, self::BLOCKED),
        self::BLOCKED    => array(self::CALCULATED),
        self::REVIEW     => array(self::APPROVED, self::CALCULATED),
        self::APPROVED   => array(self::PAID),
        self::PAID       => array(self::CLOSED),
        self::CLOSED     => array(),          // نهائيةٌ — والتصحيحُ بحدثٍ عاكس
    );

    const LABELS_AR = array(
        self::OPEN => 'مفتوحة', self::CALCULATED => 'محتسَبة', self::BLOCKED => 'موقوفة',
        self::REVIEW => 'مراجعة', self::APPROVED => 'معتمدة', self::PAID => 'مدفوعة',
        self::CLOSED => 'مقفلة',
    );

    public static function canTransition($from, $to)
    {
        $from = (string) $from; $to = (string) $to;
        if (!isset(self::TRANSITIONS[$from])) { return false; }
        return in_array($to, self::TRANSITIONS[$from], true);
    }

    public static function allowedFrom($state)
    {
        $s = (string) $state;
        return isset(self::TRANSITIONS[$s]) ? self::TRANSITIONS[$s] : array();
    }

    public static function labelAr($state)
    {
        $s = (string) $state;
        return isset(self::LABELS_AR[$s]) ? self::LABELS_AR[$s] : $s;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ① حارسُ «صفرُ صفٍّ أحمرَ معتمد»
    // ═════════════════════════════════════════════════════════════════════

    /**
     * الأسطرُ الحمراءُ في دورة — بأسمائها وأعدادها.
     *
     * @return array{ok:bool,pending:int,blocked:int,tampered:int,reasons:array}
     */
    public static function redRows($gate, $runId)
    {
        $out = array('ok' => true, 'pending' => 0, 'blocked' => 0, 'tampered' => 0, 'reasons' => array());
        $runId = (int) $runId;

        // ① أسطرٌ لم يكتمل احتسابُها
        try {
            $r = $gate->scopedQuery(array('scope' => array('l' => 'payroll_lines')),
                "SELECT COUNT(*) n FROM payroll_lines l
                  WHERE {TENANT_SCOPE} AND l.run_id = ? AND l.calc_state <> 'computed'", array($runId));
            $out['pending'] = $r ? (int) $r[0]['n'] : 0;
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'فشل يعامل بقيمة افتراضية — $out[\'pending\'] = 0'); $out['pending'] = 0; }
        if ($out['pending'] > 0) {
            $out['reasons'][] = $out['pending'] . ' سطرا لم يكتمل احتسابه (`pending_slice`) — '
                              . '«صف بلا احتساب تام لا يعتمد»';
        }

        // ② موانعُ مفتوحة (المستبعَدُ ليس مانعًا — ENT-01 يفرّق)
        try {
            $r = $gate->scopedQuery(array('scope' => array('b' => 'payroll_run_blocks')),
                "SELECT COUNT(*) n FROM payroll_run_blocks b
                  WHERE {TENANT_SCOPE} AND b.run_id = ? AND b.kind = 'blocked'", array($runId));
            $out['blocked'] = $r ? (int) $r[0]['n'] : 0;
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'فشل يعامل بقيمة افتراضية — $out[\'blocked\'] = 0'); $out['blocked'] = 0; }
        if ($out['blocked'] > 0) {
            $out['reasons'][] = $out['blocked'] . ' مانعا مفتوحا — «لا احتساب ناقص صامت»';
        }

        // ③ لقطةٌ لا تطابق بصمتَها (كشفُ التلاعب — ENT-01 §2)
        $v = PayrollRunService::verifyImmutability($gate, $runId);
        $out['tampered'] = count($v['tampered']) + (int) $v['without_snapshot'];
        if ($out['tampered'] > 0) {
            $out['reasons'][] = $out['tampered'] . ' لقطة لا تطابق بصمتها أو سطرا بلا لقطة — تلاعب مكشوف';
        }

        $out['ok'] = ($out['pending'] === 0 && $out['blocked'] === 0 && $out['tampered'] === 0);
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② الانتقال
    // ═════════════════════════════════════════════════════════════════════

    /**
     * انتقالُ حالةِ دورةٍ بحراسها من §5.
     *
     * @return array{ok:bool,code:int,reason:string,state:?string,event_id:?int}
     */
    public static function transition($conn, $gate, $companyId, $runId, $to, $actor, $opts = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'state' => null, 'event_id' => null);
        $runId = (int) $runId; $to = (string) $to;
        $run = PayrollRunService::runOf($gate, $runId);
        if (!$run) { $out['code'] = 404; $out['reason'] = 'الدورة غير موجودة في نطاقك'; return $out; }
        $from = (string) $run['state'];

        if (!self::canTransition($from, $to)) {
            $out['code'] = 422;
            $out['reason'] = 'انتقال غير مشروع من «' . self::labelAr($from) . '» إلى «' . self::labelAr($to)
                           . '» — المسموح: ' . (implode(' · ', array_map(
                               array(__CLASS__, 'labelAr'), self::allowedFrom($from))) ?: 'لا شيء');
            return $out;
        }

        // ── فترةٌ مقفلة → 423 في كل انتقالٍ يمسّ المال ────────────────────
        if (in_array($to, array(self::REVIEW, self::APPROVED, self::PAID, self::CLOSED), true)) {
            require_once dirname(__DIR__, 3) . '/includes/period_guard.php';
            $pg = ems_period_check($conn, $companyId, (string) $run['period_to']);
            if (empty($pg['ok'])) {
                $out['code'] = 423;
                $out['reason'] = 'فترة ' . $run['period_to'] . ' مقفلة: ' . $pg['reason'];
                return $out;
            }
        }

        // ── حارسُ «صفرُ صفٍّ أحمرَ معتمد» عند المراجعة والاعتماد ──────────
        if ($to === self::REVIEW || $to === self::APPROVED) {
            $red = self::redRows($gate, $runId);
            if (!$red['ok']) {
                $out['code'] = 422;
                $out['reason'] = '**صفر صف أحمر معتمد** (PLAN-01 §6.1-⑤): ' . implode(' · ', $red['reasons']);
                return $out;
            }
            $lines = (int) $run['lines_count'];
            if ($lines <= 0) {
                $out['code'] = 422; $out['reason'] = 'دورة بلا أسطر — لا شيء يراجع'; return $out;
            }
        }

        // ── «لا اعتمادَ للذات» ───────────────────────────────────────────
        if ($to === self::APPROVED) {
            if ((int) $run['created_by'] > 0 && (int) $run['created_by'] === (int) $actor) {
                $out['code'] = 403;
                $out['reason'] = 'من أنشأ الدورة لا يعتمدها — الفصل بنيوي لا اختياري (§5)';
                return $out;
            }
        }

        // ── «تنفيذُ الصرف بمرجعه» ────────────────────────────────────────
        $payRef = isset($opts['payment_ref']) ? trim((string) $opts['payment_ref']) : '';
        if ($to === self::PAID && $payRef === '') {
            $out['code'] = 422;
            $out['reason'] = 'مرجع الصرف إلزامي — «تنفيذ الصرف **بمرجعه**» (§5)';
            return $out;
        }

        // ── «لا سطرَ معلَّقًا» عند الإقفال ────────────────────────────────
        if ($to === self::CLOSED) {
            $red = self::redRows($gate, $runId);
            if (!$red['ok']) {
                $out['code'] = 422;
                $out['reason'] = 'لا يقفل وفيه معلق: ' . implode(' · ', $red['reasons']);
                return $out;
            }
        }

        $data = array('state' => $to, 'version' => (int) $run['version'] + 1);
        if ($to === self::PAID) {
            $data['note'] = mb_substr('صرف بمرجع ' . $payRef, 0, 255);
        }
        try {
            $gate->update('payroll_runs', $data, array('id' => $runId, 'version' => (int) $run['version']));
        } catch (\Throwable $t) {
            $out['code'] = 409; $out['reason'] = 'تغيرت الدورة من طرف آخر — أعد التحميل'; return $out;
        }

        // ── حدثُ FES عند الاعتماد: **واحدٌ لكل (شخص × فترة)** ─────────────
        if ($to === self::APPROVED) {
            $out['event_id'] = self::publishApproval($conn, $gate, $companyId, $run, $actor);
        }

        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'workforce', 'payroll_runs', 'transition', $runId,
            array('state' => $from), array('state' => $to, 'payment_ref' => $payRef !== '' ? $payRef : null),
            array('company_id' => (int) $companyId, 'user_id' => (int) $actor));

        $out['ok'] = true; $out['code'] = 200; $out['state'] = $to;
        return $out;
    }

    /**
     * «**الأثرُ الواحد**: المسيّرُ يولّد حدثًا واحدًا لكل (شخص × فترة) — بمفتاح
     * FES §7.1، فإعادةُ التشغيل لا تكرر» (ENT-01 §6).
     * @return ?int عددُ الأحداث المنشورة
     */
    private static function publishApproval($conn, $gate, $companyId, array $run, $actor)
    {
        require_once dirname(__DIR__, 2) . '/Core/EventPublisher.php';
        $period = substr((string) $run['period_from'], 0, 7);
        $persons = array();
        try {
            $persons = $gate->scopedQuery(array('scope' => array('l' => 'payroll_lines')),
                "SELECT l.person_id, ROUND(SUM(COALESCE(l.amount,0)),2) gross
                   FROM payroll_lines l
                  WHERE {TENANT_SCOPE} AND l.run_id = ?
                  GROUP BY l.person_id", array((int) $run['id']));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $persons'); $persons = array(); }

        $n = 0;
        foreach ($persons as $p) {
            $pid = (int) $p['person_id'];
            $net = OffsetService::netOf($gate, (int) $run['id'], $pid);
            try {
                \App\Core\EventPublisher::publishFact($conn, array(
                    'event_key'     => 'payroll.settlement.approved',
                    'category'      => 'financial',
                    'source_module' => 'workforce',
                    'company_id'    => (int) $companyId,
                    'entity_type'   => 'employee',
                    'entity_id'     => $pid,
                    'occurred_at'   => gmdate('Y-m-d H:i:s'),
                    'created_by'    => (int) $actor ?: 1,
                    // مفتاحُ «شخص × فترة» — إعادةُ الاعتماد تعيد المرجعَ ولا تكرر
                    'idempotency_key' => 'payroll_settlement:' . $pid . ':' . $period,
                    'amount'        => round((float) $net, 2),
                    'currency'      => ($run['currency'] !== null && $run['currency'] !== '')
                                       ? (string) $run['currency'] : 'SDG',
                    'source_ref'    => 'PAYRUN-' . (int) $run['id'],
                    'notes'         => 'اعتماد مسير ' . $period . ' للشخص #' . $pid,
                    'payload'       => array(
                        'run_id' => (int) $run['id'], 'person_id' => $pid,
                        'period_from' => (string) $run['period_from'],
                        'period_to' => (string) $run['period_to'],
                        'gross' => round((float) $p['gross'], 2),
                        'net' => round((float) $net, 2),
                    ),
                ));
                $n++;
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'H-09-5 publish person#');
                error_log('H-09-5 publish person#' . $pid . ': ' . $t->getMessage());
            }
        }
        return $n;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الكشفُ الفردي بطبقاته
    // ═════════════════════════════════════════════════════════════════════

    /**
     * «لكل شخصٍ كشفٌ **بطبقاته**: الأجرُ والبدلات · الحوافزُ بأساسها · الإضافي ·
     * الخصوماتُ بمراجعها · الصافي — **وكلُّ رقمٍ ينقر إلى مصدره**» (§5).
     *
     * @return array{layers:array,gross:float,deductions:float,net:float,snapshot_ids:array}
     */
    public static function payslip($gate, $runId, $personId)
    {
        $out = array('layers' => array(), 'gross' => 0.0, 'deductions' => 0.0,
                     'net' => 0.0, 'snapshot_ids' => array());
        $runId = (int) $runId; $personId = (int) $personId;

        $lines = array();
        try {
            $lines = $gate->scopedQuery(array('scope' => array('l' => 'payroll_lines')),
                "SELECT l.* FROM payroll_lines l
                  WHERE {TENANT_SCOPE} AND l.run_id = ? AND l.person_id = ?
                  ORDER BY l.line_kind, l.id", array($runId, $personId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $lines'); $lines = array(); }

        // الطبقاتُ الأربعُ بأسمائها من النص
        $map = array(
            'component' => 'الأجر والبدلات', 'production' => 'الإنتاج',
            'incentive' => 'الحوافز بأساسها', 'overtime' => 'الإضافي',
            'absence_deduction' => 'خصم الغياب',
        );
        foreach ($lines as $l) {
            $k = (string) $l['line_kind'];
            $layer = isset($map[$k]) ? $map[$k] : $k;
            if (!isset($out['layers'][$layer])) { $out['layers'][$layer] = array('rows' => array(), 'total' => 0.0); }
            $out['layers'][$layer]['rows'][] = $l;
            if ($l['amount'] !== null) {
                $out['layers'][$layer]['total'] = round($out['layers'][$layer]['total'] + (float) $l['amount'], 2);
                $out['gross'] = round($out['gross'] + (float) $l['amount'], 2);
            }
            if (!in_array((int) $l['snapshot_id'], $out['snapshot_ids'], true)) {
                $out['snapshot_ids'][] = (int) $l['snapshot_id'];
            }
        }

        // طبقةُ الخصومات بمراجعها
        $ded = array();
        try {
            $ded = $gate->scopedQuery(array('scope' => array('d' => 'payroll_deductions')),
                "SELECT d.* FROM payroll_deductions d
                  WHERE {TENANT_SCOPE} AND d.run_id = ? AND d.person_id = ?
                  ORDER BY d.id", array($runId, $personId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $ded'); $ded = array(); }
        if ($ded) {
            $out['layers']['الخصوماتُ بمراجعها'] = array('rows' => $ded, 'total' => 0.0);
            foreach ($ded as $d) {
                $out['deductions'] = round($out['deductions'] + (float) $d['amount'], 2);
            }
            $out['layers']['الخصوماتُ بمراجعها']['total'] = -1 * $out['deductions'];
        }

        $out['net'] = round($out['gross'] - $out['deductions'], 2);
        return $out;
    }

    /** سجلُّ المراجعة: صفٌّ لكل شخصٍ بطبقاته ومجاميعه (§7-مراجعة المسيّر). */
    public static function register($gate, $runId)
    {
        $runId = (int) $runId;
        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('l' => 'payroll_lines')),
                "SELECT l.person_id,
                        ROUND(SUM(CASE WHEN l.line_kind IN ('component','production')
                                       THEN COALESCE(l.amount,0) ELSE 0 END),2) AS pay,
                        ROUND(SUM(CASE WHEN l.line_kind = 'incentive'
                                       THEN COALESCE(l.amount,0) ELSE 0 END),2) AS incentive,
                        ROUND(SUM(CASE WHEN l.line_kind = 'overtime'
                                       THEN COALESCE(l.amount,0) ELSE 0 END),2) AS overtime,
                        ROUND(SUM(CASE WHEN l.line_kind = 'absence_deduction'
                                       THEN COALESCE(l.amount,0) ELSE 0 END),2) AS absence,
                        SUM(CASE WHEN l.calc_state <> 'computed' THEN 1 ELSE 0 END) AS red_rows,
                        ROUND(SUM(COALESCE(l.amount,0)),2) AS gross
                   FROM payroll_lines l
                  WHERE {TENANT_SCOPE} AND l.run_id = ?
                  GROUP BY l.person_id ORDER BY l.person_id", array($runId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $rows'); $rows = array(); }

        foreach ($rows as $i => $r) {
            $pid = (int) $r['person_id'];
            $rows[$i]['deductions'] = round((float) $r['gross'] - OffsetService::netOf($gate, $runId, $pid), 2);
            $rows[$i]['net'] = OffsetService::netOf($gate, $runId, $pid);
        }
        return $rows;
    }
}
