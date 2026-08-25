<?php
/**
 * app/Services/Finance/PeriodicEventService.php — الدورياتُ الثلاث (M-41)
 * ═══════════════════════════════════════════════════════════════════════════
 * SPEC-01 #23: «المخصصُ حدثٌ دوريٌّ آليٌّ بمفتاح (**المعدة × الفترة**) · **لا
 * كتابةَ يدويةً على الدفتر**» · «مخصصٌ دوريٌّ آليٌّ **من وحدات المعدة المعتمدة**».
 * SPEC-01 #30: «القسطُ المستحق حدثٌ آليٌّ بمفتاح (**الالتزام × القسط**)» ·
 * «أقساطٌ آليةٌ بمرجع الجدول **لحظةَ استحقاقها**».
 * SPEC-01 #22: «قيدُ الإقرار حدثٌ دوريٌّ بمفتاح **الفترة**».
 *
 * ── النمطُ واحدٌ للثلاث (وارثٌ عن M-30 حرفيًّا) ──────────────────────────────
 * **قفلُ الفترة أولًا (423)** ← **العطالةُ قبل الحساب** ← **معاملةٌ واحدة: حدثٌ
 * ثم صفّ** ← **مفتاحُ عطالةٍ حتميٌّ بلا زمنٍ فيه**.
 * وقاعدةُ عدم التلفيق نافذةٌ في الثلاث: بلا قاعدةٍ مكتوبةٍ **لا مخصص** · وقبل
 * تاريخ الاستحقاق **لا قسط** · وبلا حركاتٍ **إقرارٌ بأصفاره يُعلَن ولا يُخفى**.
 */

namespace App\Services\Finance;

require_once __DIR__ . '/../../../includes/catch_log.php';

class PeriodicEventService
{
    /** حالاتُ الوحدة التي **يُعتدّ بها** — «من وحدات المعدة **المعتمدة**». */
    const APPROVED_UNIT_STATES = array(
        'site_approved', 'parties_approved', 'sales_approved', 'converted');

    /** أنواعُ الوحدة التي تُعدُّ ساعاتٍ لأساس `hour`. */
    const HOUR_UNITS = array('hour');

    // ═════════════════════════════════════════════════════════════════════
    // ① مخصصُ الصيانة — بمفتاح (المعدة × الفترة)
    // ═════════════════════════════════════════════════════════════════════

    /** القاعدةُ المنطبقة — والأخصُّ يغلب: معدةٌ(2) > نوعُها(1) > الأعمّ(0). */
    public static function provisionRule($gate, $equipmentId, $equipmentType, $onDate)
    {
        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('r' => 'fin_maint_provision_rules')),
                "SELECT r.* FROM fin_maint_provision_rules r
                  WHERE {TENANT_SCOPE} AND COALESCE(r.is_deleted,0)=0 AND r.state='active'
                    AND r.effective_from <= ?
                    AND (r.effective_to IS NULL OR r.effective_to >= ?)
                    AND (r.equipment_id IS NULL OR r.equipment_id = ?)
                    AND (r.equipment_type IS NULL OR r.equipment_type = ?)
                  ORDER BY r.effective_from DESC, r.id DESC",
                array((string) $onDate, (string) $onDate, (int) $equipmentId, (int) $equipmentType));
        } catch (\Throwable $t) { return null; }
        $best = null; $score = -1;
        foreach ($rows as $r) {
            $s = ($r['equipment_id'] !== null ? 2 : 0) + ($r['equipment_type'] !== null ? 1 : 0);
            if ($s > $score) { $score = $s; $best = $r; }
        }
        return $best;
    }

    /**
     * مخصصُ فترةٍ لكل معدةٍ لها وحداتٌ معتمدة.
     * @return array{ok:bool,code:int,reason:string,posted:int,total:float,skipped:array}
     */
    public static function runProvisions($conn, $gate, $companyId, $period, $actor, $source = 'screen', array $only = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'posted' => 0,
                     'total' => 0.0, 'skipped' => array());
        $g = self::guardPeriod($conn, $companyId, $period);
        if (!$g['ok']) { return array_merge($out, array('code' => $g['code'], 'reason' => $g['reason'])); }
        $lastDay = $g['last_day'];
        $scope = array(); foreach ($only as $x) { $scope[(int) $x] = true; }

        // الكمياتُ من **المعتمَد وحدَه** — والمسودةُ لا تُخصَّص
        $states = "'" . implode("','", self::APPROVED_UNIT_STATES) . "'";
        $hours  = "'" . implode("','", self::HOUR_UNITS) . "'";
        $rows = array();
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('u' => 'unit_entries'), 'enrich' => array('o' => 'operations')),
                "SELECT u.equipment_id,
                        ROUND(SUM(CASE WHEN u.unit_type IN ({$hours}) THEN u.qty ELSE 0 END),2) AS hour_qty,
                        ROUND(SUM(u.qty),2) AS unit_qty,
                        MAX(o.equipment_type) AS equipment_type
                   FROM unit_entries u
                   LEFT JOIN operations o ON o.equipment = u.equipment_id
                  WHERE {TENANT_SCOPE} AND u.equipment_id IS NOT NULL
                    AND u.state IN ({$states})
                    AND DATE_FORMAT(u.entry_date, '%Y-%m') = ?
                  GROUP BY u.equipment_id", array((string) $period));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذرت قراءة الوحدات: ' . $t->getMessage(); return $out;
        }

        foreach ($rows as $r) {
            $eid = (int) $r['equipment_id'];
            if ($scope && !isset($scope[$eid])) { continue; }
            $etype = $r['equipment_type'] !== null ? (int) $r['equipment_type'] : 0;

            if (self::exists($gate, 'fin_maint_provisions',
                    'equipment_id = ? AND period_ref = ?', array($eid, (string) $period))) {
                $out['skipped'][] = array('equipment_id' => $eid, 'code' => 409, 'reason' => 'مخصص سلفا');
                continue;
            }
            $rule = self::provisionRule($gate, $eid, $etype, $lastDay);
            if (!$rule) {
                // «لا كتابةَ يدويةً على الدفتر» — وبلا قاعدةٍ لا مخصص ولا تقدير
                $out['skipped'][] = array('equipment_id' => $eid, 'code' => 422,
                    'reason' => 'لا قاعدة مخصص مكتوبة منطبقة على المعدة ' . $eid . ' في ' . $period);
                continue;
            }
            $qty = ((string) $rule['basis'] === 'hour')
                   ? round((float) $r['hour_qty'], 2) : round((float) $r['unit_qty'], 2);
            if ($qty <= 0) {
                $out['skipped'][] = array('equipment_id' => $eid, 'code' => 422,
                    'reason' => 'صفر كمية معتمدة لأساس «' . $rule['basis'] . '»');
                continue;
            }
            $amount = round($qty * (float) $rule['rate'], 2);
            $basis = array('period' => (string) $period, 'equipment_id' => $eid,
                           'equipment_type' => $etype, 'rule_id' => (int) $rule['id'],
                           'basis' => (string) $rule['basis'], 'qty' => $qty,
                           'rate' => (float) $rule['rate'], 'amount' => $amount,
                           'unit_states' => self::APPROVED_UNIT_STATES);

            $rowId = null; $eventId = null;
            try {
                $gate->runInTransaction(function ($gg) use (&$rowId, &$eventId, $conn, $companyId,
                                                            $eid, $period, $rule, $qty, $amount,
                                                            $lastDay, $basis, $actor, $source) {
                    $eventId = self::publish($conn, $companyId, array(
                        'event_key' => 'expense.maint_provision.accrued',
                        'source_module' => 'maintenance', 'entity_type' => 'equipment',
                        'entity_id' => $eid, 'occurred_at' => $lastDay . ' 23:59:59',
                        'idem' => 'mprov:' . $eid . ':' . $period,
                        'legacy' => 'expense', 'amount' => $amount,
                        'currency' => (string) $rule['currency'],
                        'equipment_id' => $eid,
                        'notes' => 'مخصص صيانة المعدة ' . $eid . ' — الفترة ' . $period,
                        'payload' => $basis,
                    ), $actor);
                    $rowId = (int) $gg->insert('fin_maint_provisions', array(
                        'equipment_id' => $eid, 'period_ref' => (string) $period,
                        'rule_id' => (int) $rule['id'], 'basis' => (string) $rule['basis'],
                        'qty' => $qty, 'rate' => (float) $rule['rate'], 'amount' => $amount,
                        'currency' => (string) $rule['currency'], 'event_id' => $eventId,
                        'basis_json' => json_encode($basis, JSON_UNESCAPED_UNICODE),
                        'source' => in_array($source, array('screen', 'cron'), true) ? $source : 'screen',
                        'created_by' => (int) $actor ?: null,
                    ));
                    if ($rowId <= 0) { throw new \RuntimeException('تعذر إدراج المخصص'); }
                }, 'مخصص صيانة ' . $eid . ' ' . $period);
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'مخصص صيانة معدة واحدة فشل — بقية المعدات تستمر، والفاشل يستدرك بالدورة التالية');
                $out['skipped'][] = array('equipment_id' => $eid, 'code' => 422, 'reason' => $t->getMessage());
                continue;
            }
            $out['posted']++;
            $out['total'] = round($out['total'] + $amount, 2);
        }
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'مخصص ' . $period . ': ' . $out['posted'] . ' معدة بمجموع ' . $out['total']
                       . ' · متخطى ' . count($out['skipped']);
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② قسطُ التمويل — بمفتاح (الالتزام × القسط)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * الاعترافُ بأقساطٍ **بلغ تاريخُ استحقاقها** حتى `asOf`.
     * @return array{ok:bool,code:int,reason:string,posted:int,total:float,skipped:array}
     */
    public static function accrueInstallments($conn, $gate, $companyId, $asOf, $actor, array $only = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'posted' => 0,
                     'total' => 0.0, 'skipped' => array());
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf) ? (string) $asOf : date('Y-m-d');
        $scope = array(); foreach ($only as $x) { $scope[(int) $x] = true; }

        $rows = array();
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('s' => 'fin_funding_schedules'),
                      'enrich' => array('f' => 'fin_funding_facilities')),
                "SELECT s.id, s.facility_id, s.installment_no, s.due_date, s.total_due,
                        s.principal_due, s.profit_due, s.event_id,
                        f.facility_no, f.currency, f.lender_name, f.state AS facility_state
                   FROM fin_funding_schedules s
                   LEFT JOIN fin_funding_facilities f ON f.id = s.facility_id
                  WHERE {TENANT_SCOPE} AND s.due_date <= ? AND s.event_id IS NULL
                  ORDER BY s.due_date, s.id", array($day));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذرت قراءة جدول السداد: ' . $t->getMessage(); return $out;
        }

        foreach ($rows as $r) {
            $sid = (int) $r['id'];
            if ($scope && !isset($scope[$sid])) { continue; }
            $amount = round((float) $r['total_due'], 2);
            if ($amount <= 0) {
                $out['skipped'][] = array('schedule_id' => $sid, 'code' => 422, 'reason' => 'قسط صفري');
                continue;
            }
            // قفلُ الفترة على **تاريخ الاستحقاق** لا على اليوم
            $g = self::guardPeriod($conn, $companyId, substr((string) $r['due_date'], 0, 7));
            if (!$g['ok']) {
                $out['skipped'][] = array('schedule_id' => $sid, 'code' => 423, 'reason' => $g['reason']);
                continue;
            }
            $cur = ($r['currency'] !== null && (string) $r['currency'] !== '') ? (string) $r['currency'] : 'SDG';
            $eventId = null;
            try {
                $gate->runInTransaction(function ($gg) use (&$eventId, $conn, $companyId, $r, $sid,
                                                            $amount, $cur, $actor) {
                    $eventId = self::publish($conn, $companyId, array(
                        'event_key' => 'payable.finance_installment.accrued',
                        'source_module' => 'finance', 'entity_type' => 'fin_funding_schedule',
                        'entity_id' => $sid,
                        'occurred_at' => substr((string) $r['due_date'], 0, 10) . ' 00:00:00',
                        'idem' => 'fund:' . (int) $r['facility_id'] . ':' . (int) $r['installment_no'],
                        'legacy' => 'payable', 'amount' => $amount, 'currency' => $cur,
                        'source_ref' => (string) $r['facility_no'],
                        'notes' => 'قسط تمويل ' . (int) $r['installment_no']
                                   . ' — ' . (string) $r['facility_no'],
                        'payload' => array(
                            'facility_id' => (int) $r['facility_id'],
                            'installment_no' => (int) $r['installment_no'],
                            'due_date' => (string) $r['due_date'],
                            'principal' => (float) $r['principal_due'],
                            'profit' => (float) $r['profit_due'], 'total' => $amount),
                    ), $actor);
                    $gg->update('fin_funding_schedules',
                        array('event_id' => $eventId, 'accrued_at' => date('Y-m-d H:i:s')),
                        array('id' => $sid));
                }, 'استحقاق قسط ' . $sid);
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'استحقاق قسط تمويل واحد فشل — بقية الأقساط تستمر، والفاشل يستدرك بالدورة التالية');
                $out['skipped'][] = array('schedule_id' => $sid, 'code' => 422, 'reason' => $t->getMessage());
                continue;
            }
            $out['posted']++;
            $out['total'] = round($out['total'] + $amount, 2);
        }
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'الأقساط حتى ' . $day . ': ' . $out['posted'] . ' قسطا بمجموع '
                       . $out['total'] . ' · متخطى ' . count($out['skipped']);
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الإقرارُ الضريبي — بمفتاح الفترة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * اشتقاقُ الإقرار وتقديمُه — «مشتقًّا من الفواتير والأوامر آليًّا بروابطها».
     * @return array{ok:bool,code:int,reason:string,return_id:?int,net:float}
     */
    public static function fileTaxReturn($conn, $gate, $companyId, $period, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'return_id' => null, 'net' => 0.0);
        $g = self::guardPeriod($conn, $companyId, $period);
        if (!$g['ok']) { return array_merge($out, array('code' => $g['code'], 'reason' => $g['reason'])); }
        $lastDay = $g['last_day'];

        $ex = null;
        try {
            $rows = $gate->scopedQuery(array('scope' => array('t' => 'fin_tax_returns')),
                "SELECT t.id, t.state, t.net_tax FROM fin_tax_returns t
                  WHERE {TENANT_SCOPE} AND t.period_ref = ? LIMIT 1", array((string) $period));
            $ex = $rows ? $rows[0] : null;
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $ex'); $ex = null; }
        if ($ex && (string) $ex['state'] === 'filed') {
            $out['code'] = 409; $out['return_id'] = (int) $ex['id'];
            $out['net'] = round((float) $ex['net_tax'], 2);
            $out['reason'] = 'إقرار الفترة ' . $period . ' مقدم سلفا (#' . (int) $ex['id'] . ')';
            return $out;
        }

        // الاشتقاق — والصفرُ يُعلَن ولا يُخفى
        $agg = array('taxable_sales' => 0.0, 'output_tax' => 0.0,
                     'taxable_purchases' => 0.0, 'input_tax' => 0.0, 'n' => 0);
        try {
            $rows = $gate->scopedQuery(array('scope' => array('x' => 'fin_tax_transactions')),
                "SELECT x.direction, ROUND(SUM(x.base_amount),2) AS base,
                        ROUND(SUM(x.tax_amount),2) AS tax, COUNT(*) AS n
                   FROM fin_tax_transactions x
                  WHERE {TENANT_SCOPE} AND COALESCE(x.is_deleted,0)=0 AND x.period_ref = ?
                  GROUP BY x.direction", array((string) $period));
            foreach ($rows as $r) {
                $agg['n'] += (int) $r['n'];
                if ((string) $r['direction'] === 'output') {
                    $agg['taxable_sales'] = round((float) $r['base'], 2);
                    $agg['output_tax'] = round((float) $r['tax'], 2);
                } else {
                    $agg['taxable_purchases'] = round((float) $r['base'], 2);
                    $agg['input_tax'] = round((float) $r['tax'], 2);
                }
            }
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذر الاشتقاق: ' . $t->getMessage(); return $out;
        }
        $net = round($agg['output_tax'] - $agg['input_tax'], 2);

        $rid = $ex ? (int) $ex['id'] : null;
        $eventId = null;
        try {
            $gate->runInTransaction(function ($gg) use (&$rid, &$eventId, $conn, $companyId, $period,
                                                        $agg, $net, $lastDay, $actor) {
                $eventId = self::publish($conn, $companyId, array(
                    'event_key' => 'tax.return.filed', 'source_module' => 'finance',
                    'entity_type' => 'fin_tax_return', 'entity_id' => 0, // يُستبدل أدناه
                    'occurred_at' => $lastDay . ' 23:59:59',
                    'idem' => 'taxret:' . $companyId . ':' . $period,
                    'legacy' => 'expense', 'amount' => abs($net), 'currency' => 'SDG',
                    'notes' => 'إقرار ضريبي للفترة ' . $period,
                    'payload' => array_merge($agg, array('period' => (string) $period, 'net' => $net)),
                    'entity_fallback' => true,
                ), $actor);
                $data = array(
                    'period_ref' => (string) $period,
                    'taxable_sales' => $agg['taxable_sales'], 'output_tax' => $agg['output_tax'],
                    'taxable_purchases' => $agg['taxable_purchases'], 'input_tax' => $agg['input_tax'],
                    'lines_count' => (int) $agg['n'], 'state' => 'filed',
                    'event_id' => $eventId, 'filed_at' => date('Y-m-d H:i:s'),
                    'filed_by' => (int) $actor ?: null,
                    'basis_json' => json_encode($agg, JSON_UNESCAPED_UNICODE),
                );
                if ($rid) { $gg->update('fin_tax_returns', $data, array('id' => $rid)); }
                else { $data['created_by'] = (int) $actor ?: null; $rid = (int) $gg->insert('fin_tax_returns', $data); }
                if (!$rid) { throw new \RuntimeException('تعذر حفظ الإقرار'); }
                // وسمُ الحركات `filed` — فلا تدخل إقرارًا ثانيًا
                $gg->update('fin_tax_transactions', array('state' => 'filed'),
                    array(), 'period_ref = ? AND state = ?', array((string) $period, 'draft'));
            }, 'إقرار ضريبي ' . $period);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذر التقديم: ' . $t->getMessage(); return $out;
        }

        $out['ok'] = true; $out['code'] = 200; $out['return_id'] = $rid; $out['net'] = $net;
        $out['reason'] = 'إقرار ' . $period . ': مخرجات ' . $agg['output_tax']
                       . ' − مدخلات ' . $agg['input_tax'] . ' = **' . $net . '**'
                       . ($agg['n'] === 0 ? ' · **صفر حركة في الفترة — يعلن ولا يخفى**' : '');
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // قراءات ومساعدات
    // ═════════════════════════════════════════════════════════════════════

    public static function provisionsOf($gate, $period = '', $limit = 200)
    {
        try {
            $w = $period !== '' ? ' AND p.period_ref = ?' : '';
            $params = $period !== '' ? array((string) $period) : array();
            return $gate->scopedQuery(array('scope' => array('p' => 'fin_maint_provisions')),
                "SELECT p.* FROM fin_maint_provisions p WHERE {TENANT_SCOPE}" . $w
                . " ORDER BY p.period_ref DESC, p.id DESC LIMIT " . max(1, (int) $limit), $params);
        } catch (\Throwable $t) { return array(); }
    }

    public static function returnsOf($gate, $limit = 60)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('t' => 'fin_tax_returns')),
                "SELECT t.* FROM fin_tax_returns t WHERE {TENANT_SCOPE}
                  ORDER BY t.period_ref DESC LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { return array(); }
    }

    public static function dueInstallments($gate, $asOf = null, $limit = 200)
    {
        $day = ($asOf !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $asOf))
               ? (string) $asOf : date('Y-m-d');
        try {
            return $gate->scopedQuery(
                array('scope' => array('s' => 'fin_funding_schedules'),
                      'enrich' => array('f' => 'fin_funding_facilities')),
                "SELECT s.*, f.facility_no, f.currency, f.lender_name
                   FROM fin_funding_schedules s
                   LEFT JOIN fin_funding_facilities f ON f.id = s.facility_id
                  WHERE {TENANT_SCOPE} AND s.due_date <= ?
                  ORDER BY (s.event_id IS NOT NULL) ASC, s.due_date DESC
                  LIMIT " . max(1, (int) $limit), array($day));
        } catch (\Throwable $t) { return array(); }
    }

    /** قفلُ الفترة على آخر يومٍ فيها — **قبل أي كتابة** (M-39). */
    private static function guardPeriod($conn, $companyId, $period)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            return array('ok' => false, 'code' => 422, 'reason' => 'الفترة بصيغة YYYY-MM', 'last_day' => '');
        }
        $lastDay = date('Y-m-t', strtotime($period . '-01'));
        require_once dirname(__DIR__, 3) . '/includes/period_guard.php';
        $pc = ems_period_check($conn, (int) $companyId, $lastDay);
        if (!$pc['ok']) {
            return array('ok' => false, 'code' => 423, 'reason' => $pc['reason'], 'last_day' => $lastDay);
        }
        return array('ok' => true, 'code' => 200, 'reason' => '', 'last_day' => $lastDay);
    }

    private static function exists($gate, $table, $whereRaw, array $params)
    {
        try {
            $row = $gate->selectOne($table, array('whereRaw' => $whereRaw, 'params' => $params));
            return $row !== null;
        } catch (\Throwable $t) { return false; }
    }

    /** ناشرٌ واحدٌ للثلاث — بمفتاحٍ حتميٍّ بلا زمنٍ فيه. */
    private static function publish($conn, $companyId, array $e, $actor)
    {
        require_once dirname(__DIR__, 2) . '/Core/EventPublisher.php';
        $entityId = (int) $e['entity_id'];
        // الإقرارُ يُنشر قبل أن يُعرف معرّفُ صفّه — فالكيانُ هو الشركةُ ومفتاحُه
        // الفترة (وهو ما تنصّ عليه #22: «بمفتاح **الفترة**» لا بمعرّف صف).
        if ($entityId <= 0 && !empty($e['entity_fallback'])) { $entityId = (int) $companyId; }
        $res = \App\Core\EventPublisher::publish($conn, array(
            'event_key'         => (string) $e['event_key'],
            'category'          => 'financial',
            'source_module'     => (string) $e['source_module'],
            'company_id'        => (int) $companyId,
            'entity_type'       => (string) $e['entity_type'],
            'entity_id'         => $entityId,
            'occurred_at'       => (string) $e['occurred_at'],
            'created_by'        => (int) $actor ?: 1,
            'idempotency_key'   => (string) $e['idem'],
            'legacy_event_type' => (string) $e['legacy'],
            'amount'            => round((float) $e['amount'], 2),
            'currency'          => (string) $e['currency'],
            'source_ref'        => isset($e['source_ref']) ? (string) $e['source_ref'] : null,
            'equipment_id'      => isset($e['equipment_id']) ? (int) $e['equipment_id'] : null,
            'notes'             => (string) $e['notes'],
            'payload'           => $e['payload'],
        ));
        return (is_array($res) && isset($res['id'])) ? (int) $res['id'] : null;
    }
}
