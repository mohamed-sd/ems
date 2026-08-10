<?php
/**
 * app/Services/Contract/ContractMonthlyPlanService.php — الجدولُ الشهري (P-03)
 * ═══════════════════════════════════════════════════════════════════════════
 * PLAN-03 §2 (الملحق §3-`P-03`): «**الجدولُ الشهري** بنسخه (`plan_version` +
 * `effective_from`) و**قيدِ Σ الكميات = المتعاقَد**».
 * §4 القبول: «جدولٌ شهريٌّ لعقدٍ فيه **شهرُ تعبئةٍ وشهرُ توقف**: **القيمةُ
 * السنوية صحيحة**».
 *
 * ── لماذا جدولٌ لا عمودُ دورية ───────────────────────────────────────────────
 * القائمُ **كميةٌ واحدة + دورية**، فـ«20,000 طنٍّ على مدى العقد» تُقسَّم
 * **بالتساوي ضمنًا** أو لا تُقسَّم. ولا موضعَ يقول «آذارُ تعبئةٌ بـ500 وتموزُ
 * توقفٌ بصفر» — **وهو واقعُ كل عقدٍ موسمي**.
 *
 * ── أربعُ قواعد ─────────────────────────────────────────────────────────────
 * ① **Σ لا يتجاوز المتعاقَد أبدًا** — عدّادٌ على البند بـ`CHECK`، والكتابةُ
 *    **معاملةٌ واحدة**. و**المساواةُ التامة شرطُ الختم** لا شرطُ الإدراج.
 * ② **والشهرُ داخل مدة البند** ⇒ 422 بتاريخيه.
 * ③ **وصفرٌ يعني توقفًا معلَنًا** لا غيابَ بيان — و**الشهرُ الغائبُ يُعلَن ناقصًا**.
 * ④ **والملحقُ يفتح نسخةً لا يعدّل** — والنسخةُ السابقةُ تبقى **بحكم فترتها**.
 */

namespace App\Services\Contract;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/ContractLineService.php';

class ContractMonthlyPlanService
{
    const MONTH_KINDS = array('normal', 'mobilization', 'ramp_up', 'shutdown', 'maintenance');

    const KIND_AR = array(
        'normal' => 'اعتيادي', 'mobilization' => 'تعبئة', 'ramp_up' => 'تصاعد',
        'shutdown' => 'توقف', 'maintenance' => 'صيانة',
    );

    // ═════════════════════════════════════════════════════════════════════
    // ① الكتابة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * حفظُ جدولٍ شهريٍّ كاملٍ لنسخةٍ — **يستبدل أشهرَ النسخة ويعيد بناءَ العدّاد**.
     *
     * @param array $months كلٌّ: {period_month, qty_planned, month_kind?, note?}
     * @return array{ok:bool,code:int,reason:string,version:int,months:int,
     *               planned:float,contracted:float,gap:float}
     */
    public static function savePlan($conn, $gate, $companyId, $lineId, $version, $effectiveFrom, array $months, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'version' => 0,
                     'months' => 0, 'planned' => 0.0, 'contracted' => 0.0, 'gap' => 0.0);
        $l = ContractLineService::lineOf($gate, (int) $lineId);
        if (!$l) { $out['code'] = 404; $out['reason'] = 'بندُ البيع غيرُ موجودٍ في نطاقك'; return $out; }
        if ((string) $l['state'] !== 'active') {
            $out['code'] = 409;
            $out['reason'] = 'الجدولُ للبند النافذ (حالُه: ' . $l['state'] . ')'; return $out;
        }
        if ($l['plan_sealed_version'] !== null && (int) $version === (int) $l['plan_sealed_version']) {
            $out['code'] = 423;
            $out['reason'] = 'النسخةُ ' . (int) $version . ' **مختومة** — والتغييرُ **بنسخةٍ جديدة** لا بتعديلها';
            return $out;
        }
        $version = max(1, (int) $version);
        $eff = self::dateOrNull($effectiveFrom);
        if ($eff === null) {
            $out['code'] = 422; $out['reason'] = '**تاريخُ سريان النسخة إلزامي**'; return $out;
        }
        if (!$months) { $out['code'] = 422; $out['reason'] = 'لا جدولَ فارغ'; return $out; }

        $contracted = round((float) $l['qty_contracted'], 2);
        $from = substr((string) $l['valid_from'], 0, 7);
        $to   = ($l['valid_to'] !== null && (string) $l['valid_to'] !== '')
                ? substr((string) $l['valid_to'], 0, 7) : null;

        // ② الشهرُ داخل مدة البند · وصفرُ تكرارٍ · والمجموعُ يُفحص **قبل الكتابة**
        $clean = array(); $sum = 0.0;
        foreach ($months as $m) {
            $mm = trim((string) (isset($m['period_month']) ? $m['period_month'] : ''));
            if (!preg_match('/^\d{4}-\d{2}$/', $mm)) {
                $out['code'] = 422; $out['reason'] = 'شهرٌ بصيغةٍ غير صالحة: ' . $mm; return $out;
            }
            if ($mm < $from) {
                $out['code'] = 422;
                $out['reason'] = 'الشهرُ ' . $mm . ' **قبل سريان البند** ' . $from; return $out;
            }
            if ($to !== null && $mm > $to) {
                $out['code'] = 422;
                $out['reason'] = 'الشهرُ ' . $mm . ' **بعد نهاية البند** ' . $to; return $out;
            }
            if (isset($clean[$mm])) {
                $out['code'] = 409; $out['reason'] = 'الشهرُ ' . $mm . ' مكرَّرٌ في الجدول'; return $out;
            }
            $q = round((float) (isset($m['qty_planned']) ? $m['qty_planned'] : 0), 2);
            if ($q < 0) { $out['code'] = 422; $out['reason'] = 'كميةٌ سالبةٌ في ' . $mm; return $out; }
            $kind = (string) (isset($m['month_kind']) ? $m['month_kind'] : 'normal');
            if (!in_array($kind, self::MONTH_KINDS, true)) { $kind = 'normal'; }
            // ③ صفرٌ يعني **توقفًا معلَنًا** — فيُلزَم بنوعٍ يفسّره
            if ($q == 0.0 && $kind === 'normal') { $kind = 'shutdown'; }
            $clean[$mm] = array('qty' => $q, 'kind' => $kind,
                                'note' => isset($m['note']) ? mb_substr((string) $m['note'], 0, 255) : null);
            $sum = round($sum + $q, 2);
        }

        // ① Σ لا يتجاوز المتعاقَد **أبدًا**
        if ($sum > $contracted + 0.0001) {
            $out['code'] = 409;
            $out['reason'] = '**Σ الأشهر ' . $sum . ' تتجاوز المتعاقَد ' . $contracted . '** — '
                           . 'والفائضُ ' . round($sum - $contracted, 2) . ' لا يُخطَّط لما لم يُتعاقد عليه';
            $out['planned'] = $sum; $out['contracted'] = $contracted;
            return $out;
        }

        ksort($clean);
        try {
            $gate->runInTransaction(function ($g) use ($lineId, $version, $eff, $clean, $sum, $actor, $l) {
                // النسخةُ تُستبدل كاملةً — «الجدولُ وحدةٌ واحدة لا أسطرٌ متفرقة»
                $old = $g->scopedQuery(array('scope' => array('p' => 'contract_monthly_plan')),
                    "SELECT p.id FROM contract_monthly_plan p
                      WHERE {TENANT_SCOPE} AND p.line_id = ? AND p.plan_version = ?",
                    array((int) $lineId, (int) $version));
                foreach ($old as $o) { $g->deleteRow('contract_monthly_plan', (int) $o['id']); }

                foreach ($clean as $mm => $row) {
                    $g->insert('contract_monthly_plan', array(
                        'contract_id' => (int) $l['contract_id'], 'line_id' => (int) $lineId,
                        'plan_version' => (int) $version, 'effective_from' => $eff,
                        'period_month' => $mm, 'qty_planned' => $row['qty'],
                        'month_kind' => $row['kind'], 'note' => $row['note'],
                        'created_by' => (int) $actor ?: null,
                    ));
                }
                // العدّادُ يُعاد بناؤه ذرّيًّا — و`CHECK` يحرس السقف
                $g->update('client_contract_lines', array('qty_planned_total' => $sum),
                    array('id' => (int) $lineId));
            }, 'جدول شهري للبند ' . $lineId . ' نسخة ' . $version);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الحفظ: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'save_plan', (int) $lineId, array(),
            array('version' => $version, 'months' => count($clean), 'planned' => $sum));

        $out['ok'] = true; $out['code'] = 200; $out['version'] = $version;
        $out['months'] = count($clean); $out['planned'] = $sum; $out['contracted'] = $contracted;
        $out['gap'] = round($contracted - $sum, 2);
        $out['reason'] = 'النسخة ' . $version . ': ' . count($clean) . ' شهرًا بمجموع ' . $sum
                       . ' من ' . $contracted
                       . ($out['gap'] > 0.0001 ? (' · **ناقصٌ ' . $out['gap'] . ' — لا يُختم**') : ' · **مكتمل**');
        return $out;
    }

    /**
     * ختمُ النسخة — **وهنا وحدَها تُشترط المساواةُ التامة**.
     * @return array{ok:bool,code:int,reason:string,gap:float}
     */
    public static function seal($conn, $gate, $companyId, $lineId, $version, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'gap' => 0.0);
        $l = ContractLineService::lineOf($gate, (int) $lineId);
        if (!$l) { $out['code'] = 404; $out['reason'] = 'بندُ البيع غيرُ موجود'; return $out; }
        $sum = self::versionSum($gate, (int) $lineId, (int) $version);
        $contracted = round((float) $l['qty_contracted'], 2);
        $gap = round($contracted - $sum, 2);
        $out['gap'] = $gap;
        if (abs($gap) > 0.0001) {
            $out['code'] = 422;
            $out['reason'] = '**Σ الأشهر ' . $sum . ' ≠ المتعاقَد ' . $contracted . '** — '
                . ($gap > 0 ? ('ناقصٌ ' . $gap) : ('زائدٌ ' . abs($gap)))
                . ' · «قيدُ Σ الكميات = المتعاقَد» شرطُ الختم';
            return $out;
        }
        try {
            $gate->update('client_contract_lines', array('plan_sealed_version' => (int) $version),
                array('id' => (int) $lineId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الختم: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'seal_plan', (int) $lineId, array(),
            array('version' => (int) $version, 'planned' => $sum));
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'خُتمت النسخة ' . (int) $version . ' بـΣ = ' . $sum . ' = المتعاقَد';
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② القراءة — القيمةُ الشهرية والسنوية
    // ═════════════════════════════════════════════════════════════════════

    /**
     * الخطةُ النافذةُ لبندٍ في تاريخ — «أثرُ ما قبله بالنسخة السابقة».
     * @return array{version:?int,rows:array}
     */
    public static function effectivePlan($gate, $lineId, $onDate = null)
    {
        $day = ($onDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $onDate))
               ? (string) $onDate : date('Y-m-d');
        $ver = null;
        try {
            $r = $gate->scopedQuery(array('scope' => array('p' => 'contract_monthly_plan')),
                "SELECT p.plan_version, p.effective_from FROM contract_monthly_plan p
                  WHERE {TENANT_SCOPE} AND p.line_id = ? AND p.effective_from <= ?
                  ORDER BY p.effective_from DESC, p.plan_version DESC LIMIT 1",
                array((int) $lineId, $day));
            if ($r) { $ver = (int) $r[0]['plan_version']; }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءةٌ/كتابةٌ فاشلةٌ تُعامَل كغيابٍ للسجل — $ver'); $ver = null; }
        if ($ver === null) { return array('version' => null, 'rows' => array()); }
        return array('version' => $ver, 'rows' => self::rowsOf($gate, $lineId, $ver));
    }

    /**
     * قيمةُ الفترة من الجدول الشهري — **«القيمةُ السنوية صحيحة»** بشهر التعبئة
     * وشهر التوقف معًا.
     *
     * @return array{ok:bool,by_currency:array,months:array,note:string}
     */
    public static function periodValue($gate, $contractId, $fromMonth, $toMonth, $onDate = null)
    {
        $out = array('ok' => true, 'by_currency' => array(), 'months' => array(), 'note' => '');
        // **الافتراضُ ليس «اليوم» بل بدايةُ الفترة المسؤولِ عنها**: قيمةُ 2081
        // تُقرأ بالنسخة التي حكمت 2081 — لا بالتي تحكم يومَ التشغيل. والخلطُ
        // بينهما يجعل تقريرَ سنةٍ ماضيةٍ يتغير كلما مرّ يوم.
        if ($onDate === null && preg_match('/^\d{4}-\d{2}$/', (string) $fromMonth)) {
            $onDate = (string) $fromMonth . '-01';
        }
        $lines = ContractLineService::linesOf($gate, (int) $contractId, false);
        foreach ($lines as $l) {
            $plan = self::effectivePlan($gate, (int) $l['id'], $onDate);
            if (!$plan['rows']) { continue; }
            $cur = (string) $l['currency'];
            $price = (float) $l['unit_price'];
            foreach ($plan['rows'] as $r) {
                $mm = (string) $r['period_month'];
                if ($mm < (string) $fromMonth || $mm > (string) $toMonth) { continue; }
                $amt = round((float) $r['qty_planned'] * $price, 2);
                if (!isset($out['by_currency'][$cur])) { $out['by_currency'][$cur] = 0.0; }
                $out['by_currency'][$cur] = round($out['by_currency'][$cur] + $amt, 2);
                if (!isset($out['months'][$mm])) { $out['months'][$mm] = array(); }
                if (!isset($out['months'][$mm][$cur])) { $out['months'][$mm][$cur] = 0.0; }
                $out['months'][$mm][$cur] = round($out['months'][$mm][$cur] + $amt, 2);
            }
        }
        ksort($out['months']);
        $parts = array();
        foreach ($out['by_currency'] as $c => $v) { $parts[] = $v . ' ' . $c; }
        $out['note'] = ($parts ? implode(' · ', $parts) : 'صفر') . ' على ' . count($out['months']) . ' شهرًا'
                     . (count($out['by_currency']) > 1 ? ' · **تعدُّدُ عملاتٍ: لا يُجمع في رقم**' : '');
        return $out;
    }

    /** الأشهرُ الغائبةُ عن نسخةٍ داخل مدة البند — «الفجوةُ تُرى». */
    public static function missingMonths($gate, $lineId, $version)
    {
        $l = ContractLineService::lineOf($gate, (int) $lineId);
        if (!$l) { return array(); }
        $from = substr((string) $l['valid_from'], 0, 7);
        $to = ($l['valid_to'] !== null && (string) $l['valid_to'] !== '')
              ? substr((string) $l['valid_to'], 0, 7) : $from;
        $have = array();
        foreach (self::rowsOf($gate, $lineId, $version) as $r) { $have[(string) $r['period_month']] = true; }
        $miss = array(); $cur = $from; $guard = 0;
        while ($cur <= $to && $guard++ < 240) {
            if (!isset($have[$cur])) { $miss[] = $cur; }
            $cur = date('Y-m', strtotime($cur . '-01 +1 month'));
        }
        return $miss;
    }

    public static function rowsOf($gate, $lineId, $version)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('p' => 'contract_monthly_plan')),
                "SELECT p.* FROM contract_monthly_plan p
                  WHERE {TENANT_SCOPE} AND p.line_id = ? AND p.plan_version = ?
                  ORDER BY p.period_month", array((int) $lineId, (int) $version));
        } catch (\Throwable $t) { return array(); }
    }

    public static function versionsOf($gate, $lineId)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('p' => 'contract_monthly_plan')),
                "SELECT p.plan_version, p.effective_from, COUNT(*) AS months,
                        ROUND(SUM(p.qty_planned),2) AS planned
                   FROM contract_monthly_plan p
                  WHERE {TENANT_SCOPE} AND p.line_id = ?
                  GROUP BY p.plan_version, p.effective_from
                  ORDER BY p.plan_version DESC", array((int) $lineId));
        } catch (\Throwable $t) { return array(); }
    }

    public static function versionSum($gate, $lineId, $version)
    {
        try {
            $r = $gate->scopedQuery(array('scope' => array('p' => 'contract_monthly_plan')),
                "SELECT ROUND(COALESCE(SUM(p.qty_planned),0),2) AS s FROM contract_monthly_plan p
                  WHERE {TENANT_SCOPE} AND p.line_id = ? AND p.plan_version = ?",
                array((int) $lineId, (int) $version));
            return $r ? round((float) $r[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    // ═════════════════════════════════════════════════════════════════════

    private static function dateOrNull($v)
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'contract_monthly_plan', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
