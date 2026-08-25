<?php
/**
 * app/Services/Contract/PlanActualLinkService.php — ربطُ الخطة بالفعلي (P-09)
 * ═══════════════════════════════════════════════════════════════════════════
 * الملحق §3-`P-09`: «**مفاتيحُ ربط الخطة بالفعلي**: `contract_line_id` ·
 * `plan_period_id` · `operational_site_id` **على الوحدة وسطر المستخلص**» ·
 * §4: «`P-12` تعرض **الأرقام الأربعة** (مخططٌ · منفَّذٌ · مفوترٌ · محصَّل)» —
 * **ولا سبيلَ إليها بلا هذه المفاتيح**.
 *
 * ── لماذا مفاتيحُ وليست تقاريرَ ─────────────────────────────────────────────
 * «المخطَّطُ» في `contract_monthly_plan` و«المنفَّذُ» في `unit_entries`
 * و«المفوتَرُ» في `claim_lines` — **ثلاثةُ أرقامٍ لا تلتقي على مفتاح**.
 * فمقارنتُها اليوم **تخمينٌ بالتاريخ والعقد**: وحدةٌ في آذارَ لعقدٍ فيه بندان
 * لا يُعرف لأيِّهما هي. **والتخمينُ يُنتج تقريرًا يبدو صحيحًا وهو ليس كذلك.**
 *
 * ── ثلاثُ قواعد ─────────────────────────────────────────────────────────────
 * ① **الوصلُ يُشتقّ ولا يُخمَّن**: البندُ يُختار **بمطابقةِ العقد والنموذج
 *    والنافذة**، وإن صلح **أكثرُ من بند** ⇒ **يُعلَن الالتباسُ ولا يُختار**.
 * ② **ولا يُستعار مفتاحٌ من عقدٍ آخر** ⇒ 422 لكلِّ طرفٍ من الثلاثة.
 * ③ **والفجوةُ تُعلَن عدًّا** — `coverage()` تقول كم وُصل وكم بقي، **ولا قيمةَ
 *    افتراضيةٌ تُخفي غيرَ الموصول**.
 */

namespace App\Services\Contract;

require_once __DIR__ . '/../../../includes/catch_log.php';

require_once __DIR__ . '/ContractLineService.php';
require_once __DIR__ . '/ContractMonthlyPlanService.php';

class PlanActualLinkService
{
    /** نموذجُ التسعير المقابلُ لوحدة القياس — «الطنُّ للطن والساعةُ للساعة». */
    const MODEL_OF_UNIT = array(
        'hour' => 'hour', 'ton' => 'ton', 'meter' => 'meter', 'cbm' => 'cbm',
        'day' => 'day', 'shift' => 'shift', 'trip' => 'trip',
    );

    // ═════════════════════════════════════════════════════════════════════
    // ① الاشتقاق — ولا تخمين
    // ═════════════════════════════════════════════════════════════════════

    /**
     * اشتقاقُ المفاتيح الثلاثة لواقعةٍ (وحدةٍ أو سطرِ مستخلص).
     *
     * @return array{ok:bool,code:int,reason:string,contract_line_id:?int,
     *               plan_period_id:?int,operational_site_id:?int,candidates:int}
     */
    public static function resolve($gate, $contractId, $onDate, $unitType, $siteId = 0)
    {
        $o = array('ok' => false, 'code' => 0, 'reason' => '', 'contract_line_id' => null,
                   'plan_period_id' => null, 'operational_site_id' => null, 'candidates' => 0);
        $contractId = (int) $contractId;
        $day = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $onDate)) ? (string) $onDate : null;
        if ($contractId <= 0 || $day === null) {
            $o['code'] = 422; $o['reason'] = '**العقد والتاريخ إلزاميان للاشتقاق**'; return $o;
        }
        $model = isset(self::MODEL_OF_UNIT[(string) $unitType]) ? self::MODEL_OF_UNIT[(string) $unitType] : null;

        // البندُ: **مطابقةُ العقد والنموذج والنافذة** — لا أقرب ولا أول
        $hits = array();
        foreach (ContractLineService::linesOf($gate, $contractId, true) as $l) {
            if (!in_array((string) $l['state'], array('active', 'superseded', 'ended'), true)) { continue; }
            if ($model !== null && (string) $l['pricing_model'] !== $model) { continue; }
            $from = (string) $l['valid_from'];
            $to = ($l['valid_to'] !== null && (string) $l['valid_to'] !== '') ? (string) $l['valid_to'] : null;
            if ($day < $from) { continue; }
            if ($to !== null && $day > $to) { continue; }
            $hits[] = $l;
        }
        $o['candidates'] = count($hits);
        if (!$hits) {
            $o['code'] = 404;
            $o['reason'] = '**لا بند بيع يطابق** العقد ' . $contractId . ' ونموذج «'
                . (string) $unitType . '» في ' . $day . ' — والوصل يشتق ولا يخترع';
            return $o;
        }
        if (count($hits) > 1) {
            $ids = array();
            foreach ($hits as $h) { $ids[] = '#' . (int) $h['id']; }
            $o['code'] = 409;
            $o['reason'] = '**التباس: ' . count($hits) . ' بنود تصلح** (' . implode(' · ', $ids)
                . ') — **يعلن ولا يختار بالحدس**؛ حدد البند صراحة';
            return $o;
        }
        $line = $hits[0];
        $o['contract_line_id'] = (int) $line['id'];

        // الشهرُ المخطَّط: **النسخةُ التي حكمت ذلك اليوم** (P-03) لا نسخةُ اليوم
        $mm = substr($day, 0, 7);
        $plan = ContractMonthlyPlanService::effectivePlan($gate, (int) $line['id'], $day);
        foreach ($plan['rows'] as $r) {
            if ((string) $r['period_month'] === $mm) { $o['plan_period_id'] = (int) $r['id']; break; }
        }

        // النطاقُ: المحدَّدُ إن صحَّ، وإلا **النطاقُ الوحيدُ للعقد** إن كان واحدًا
        $sites = array();
        try {
            $sites = $gate->scopedQuery(array('scope' => array('s' => 'contract_operational_sites')),
                "SELECT s.id FROM contract_operational_sites s
                  WHERE {TENANT_SCOPE} AND s.contract_id = ? AND COALESCE(s.is_deleted,0)=0
                    AND s.state <> 'closed' ORDER BY s.id", array($contractId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $sites'); $sites = array(); }
        $ids = array();
        foreach ($sites as $s) { $ids[] = (int) $s['id']; }
        if ((int) $siteId > 0 && in_array((int) $siteId, $ids, true)) {
            $o['operational_site_id'] = (int) $siteId;
        } elseif (count($ids) === 1) {
            $o['operational_site_id'] = $ids[0];
        }

        $o['ok'] = true; $o['code'] = 200;
        $o['reason'] = 'بند #' . $o['contract_line_id']
            . ($o['plan_period_id'] !== null ? (' · شهر خطة #' . $o['plan_period_id'])
                                             : ' · **لا شهر مخطط لهذا التاريخ**')
            . ($o['operational_site_id'] !== null ? (' · نطاق #' . $o['operational_site_id'])
                                                  : ' · **النطاق ملتبس أو غير محدد**');
        return $o;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② الكتابة — ولا يُستعار مفتاح
    // ═════════════════════════════════════════════════════════════════════

    /** وصلُ وحدةٍ — و`$auto` يشتقّ ما لم يُمرَّر. */
    public static function linkUnit($conn, $gate, $companyId, $entryId, array $keys, $actor, $auto = true)
    {
        return self::linkRow($conn, $gate, $companyId, 'unit_entries', (int) $entryId, $keys, $actor, $auto);
    }

    /** وصلُ سطرِ مستخلص. */
    public static function linkClaimLine($conn, $gate, $companyId, $lineId, array $keys, $actor, $auto = true)
    {
        return self::linkRow($conn, $gate, $companyId, 'claim_lines', (int) $lineId, $keys, $actor, $auto);
    }

    private static function linkRow($conn, $gate, $companyId, $table, $id, array $keys, $actor, $auto)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'keys' => array());
        $row = null;
        try { $row = $gate->selectOne($table, array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $row'); $row = null; }
        if (!$row) { $out['code'] = 404; $out['reason'] = 'الصف غير موجود في نطاقك'; return $out; }

        // العقدُ والتاريخُ من الصفِّ نفسِه — ولكلِّ جدولٍ اسمُه
        if ($table === 'unit_entries') {
            $contractId = (int) $row['contract_id'];
            $day = (string) $row['entry_date'];
            $unitType = (string) $row['unit_type'];
        } else {
            $claim = null;
            try { $claim = $gate->selectOne('claims', array('where' => array('id' => (int) $row['claim_id']))); }
            catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $claim'); $claim = null; }
            if (!$claim) { $out['code'] = 404; $out['reason'] = 'مستخلص السطر غير موجود'; return $out; }
            $contractId = (int) $claim['contract_id'];
            $day = ($row['work_date'] !== null && (string) $row['work_date'] !== '')
                   ? (string) $row['work_date'] : (string) $claim['period_from'];
            $unitType = (string) $row['unit_type'];
        }
        if ($contractId <= 0) {
            $out['code'] = 422;
            $out['reason'] = '**الصف بلا عقد** — ولا يوصل بخطة عقد لا ينتمي إليه'; return $out;
        }

        $lineId = isset($keys['contract_line_id']) ? (int) $keys['contract_line_id'] : 0;
        $periodId = isset($keys['plan_period_id']) ? (int) $keys['plan_period_id'] : 0;
        $siteId = isset($keys['operational_site_id']) ? (int) $keys['operational_site_id'] : 0;

        if ($lineId <= 0 && $auto) {
            $r = self::resolve($gate, $contractId, $day, $unitType, $siteId);
            if (!$r['ok']) { $out['code'] = $r['code']; $out['reason'] = $r['reason']; return $out; }
            $lineId = (int) $r['contract_line_id'];
            if ($periodId <= 0) { $periodId = (int) $r['plan_period_id']; }
            if ($siteId <= 0) { $siteId = (int) $r['operational_site_id']; }
        }
        if ($lineId <= 0) {
            $out['code'] = 422; $out['reason'] = '**بند البيع إلزامي للوصل**'; return $out;
        }

        // ② **ولا يُستعار مفتاحٌ من عقدٍ آخر** — الأطرافُ الثلاثةُ تُفحص
        $line = ContractLineService::lineOf($gate, $lineId);
        if (!$line) { $out['code'] = 404; $out['reason'] = 'بند البيع غير موجود'; return $out; }
        if ((int) $line['contract_id'] !== $contractId) {
            $out['code'] = 422;
            $out['reason'] = '**البند #' . $lineId . ' من عقد آخر** (' . (int) $line['contract_id']
                . ' لا ' . $contractId . ') — ولا يستعار مفتاح';
            return $out;
        }
        if ($periodId > 0) {
            $pr = null;
            try { $pr = $gate->selectOne('contract_monthly_plan', array('where' => array('id' => $periodId))); }
            catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $pr'); $pr = null; }
            if (!$pr) { $out['code'] = 404; $out['reason'] = 'شهر الخطة غير موجود'; return $out; }
            if ((int) $pr['line_id'] !== $lineId) {
                $out['code'] = 422;
                $out['reason'] = '**شهر الخطة #' . $periodId . ' لبند آخر** — والشهر يتبع بنده';
                return $out;
            }
        }
        if ($siteId > 0) {
            $st = null;
            try { $st = $gate->selectOne('contract_operational_sites', array('where' => array('id' => $siteId))); }
            catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كغياب للسجل — $st'); $st = null; }
            if (!$st || (int) $st['contract_id'] !== $contractId) {
                $out['code'] = 422;
                $out['reason'] = '**النطاق #' . $siteId . ' ليس من نطاقات هذا العقد**'; return $out;
            }
        }

        try {
            $gate->update($table, array(
                'contract_line_id' => $lineId,
                'plan_period_id' => ($periodId > 0) ? $periodId : null,
                'operational_site_id' => ($siteId > 0) ? $siteId : null,
            ), array('id' => (int) $id));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذر الوصل: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'link_plan_actual', (int) $id, array(),
            array('table' => $table, 'line' => $lineId, 'period' => $periodId, 'site' => $siteId));

        $out['ok'] = true; $out['code'] = 200;
        $out['keys'] = array('contract_line_id' => $lineId,
                             'plan_period_id' => ($periodId > 0) ? $periodId : null,
                             'operational_site_id' => ($siteId > 0) ? $siteId : null);
        $out['reason'] = 'وصل بالبند #' . $lineId
            . ($periodId > 0 ? (' وشهر الخطة #' . $periodId) : ' · **بلا شهر خطة**')
            . ($siteId > 0 ? (' والنطاق #' . $siteId) : ' · **بلا نطاق**');
        return $out;
    }

    /** وصلُ كلِّ ما يمكن وصلُه في عقد — **والملتبسُ يُعلَن ولا يُخمَّن**. */
    public static function linkContract($conn, $gate, $companyId, $contractId, $actor, $apply = true)
    {
        $o = array('units' => 0, 'claim_lines' => 0, 'skipped' => 0, 'reasons' => array(), 'note' => '');
        $units = array();
        try {
            $units = $gate->scopedQuery(array('scope' => array('u' => 'unit_entries')),
                "SELECT u.id FROM unit_entries u
                  WHERE {TENANT_SCOPE} AND u.contract_id = ? AND u.contract_line_id IS NULL
                  ORDER BY u.id LIMIT 500", array((int) $contractId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $units'); $units = array(); }
        foreach ($units as $u) {
            if (!$apply) { $o['units']++; continue; }
            $r = self::linkUnit($conn, $gate, $companyId, (int) $u['id'], array(), $actor, true);
            if ($r['ok']) { $o['units']++; }
            else { $o['skipped']++; $o['reasons'][$r['code']] = isset($o['reasons'][$r['code']])
                                                                ? $o['reasons'][$r['code']] + 1 : 1; }
        }
        $lines = array();
        try {
            $lines = $gate->scopedQuery(
                array('scope' => array('l' => 'claim_lines'), 'enrich' => array('c' => 'claims')),
                "SELECT l.id FROM claim_lines l
                   LEFT JOIN claims c ON c.id = l.claim_id
                  WHERE {TENANT_SCOPE} AND c.contract_id = ? AND l.contract_line_id IS NULL
                  ORDER BY l.id LIMIT 500", array((int) $contractId));
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $lines'); $lines = array(); }
        foreach ($lines as $l) {
            if (!$apply) { $o['claim_lines']++; continue; }
            $r = self::linkClaimLine($conn, $gate, $companyId, (int) $l['id'], array(), $actor, true);
            if ($r['ok']) { $o['claim_lines']++; }
            else { $o['skipped']++; $o['reasons'][$r['code']] = isset($o['reasons'][$r['code']])
                                                               ? $o['reasons'][$r['code']] + 1 : 1; }
        }
        $parts = array();
        foreach ($o['reasons'] as $code => $n) { $parts[] = $n . '×' . $code; }
        $o['note'] = ($apply ? 'وصل ' : 'مرشح للوصل ') . $o['units'] . ' وحدة و'
            . $o['claim_lines'] . ' سطر مستخلص'
            . ($o['skipped'] > 0 ? (' · **وترك ' . $o['skipped'] . ' معلنا** (' . implode(' · ', $parts) . ')') : '');
        return $o;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الفجوةُ تُرى — والأرقامُ تلتقي على مفتاح
    // ═════════════════════════════════════════════════════════════════════

    /** كم وُصل وكم بقي — **ولا قيمةَ افتراضيةٌ تُخفي غيرَ الموصول**. */
    public static function coverage($gate, $contractId = 0)
    {
        $o = array('units_total' => 0, 'units_linked' => 0, 'claims_total' => 0,
                   'claims_linked' => 0, 'note' => '');
        try {
            $w = ((int) $contractId > 0) ? ' AND u.contract_id = ?' : '';
            $p = ((int) $contractId > 0) ? array((int) $contractId) : array();
            $r = $gate->scopedQuery(array('scope' => array('u' => 'unit_entries')),
                "SELECT COUNT(*) AS t, SUM(CASE WHEN u.contract_line_id IS NOT NULL THEN 1 ELSE 0 END) AS l
                   FROM unit_entries u WHERE {TENANT_SCOPE}" . $w, $p);
            if ($r) { $o['units_total'] = (int) $r[0]['t']; $o['units_linked'] = (int) $r[0]['l']; }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'صفر'); /* صفر */ }
        try {
            $w = ((int) $contractId > 0) ? ' AND c.contract_id = ?' : '';
            $p = ((int) $contractId > 0) ? array((int) $contractId) : array();
            $r = $gate->scopedQuery(
                array('scope' => array('l' => 'claim_lines'), 'enrich' => array('c' => 'claims')),
                "SELECT COUNT(*) AS t, SUM(CASE WHEN l.contract_line_id IS NOT NULL THEN 1 ELSE 0 END) AS l
                   FROM claim_lines l LEFT JOIN claims c ON c.id = l.claim_id
                  WHERE {TENANT_SCOPE}" . $w, $p);
            if ($r) { $o['claims_total'] = (int) $r[0]['t']; $o['claims_linked'] = (int) $r[0]['l']; }
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'صفر'); /* صفر */ }
        $o['note'] = 'وحدات ' . $o['units_linked'] . '/' . $o['units_total']
            . ' · أسطر مستخلص ' . $o['claims_linked'] . '/' . $o['claims_total']
            . ' — **وغير الموصول يعد ولا يخفى**';
        return $o;
    }

    /**
     * **المخطَّطُ والمنفَّذُ والمفوتَر على مفتاحٍ واحد** — وهو ما تعذّر قبل P-09.
     * @return array{ok:bool,rows:array,totals:array,note:string}
     */
    public static function planVsActual($gate, $contractId, $fromMonth = '', $toMonth = '')
    {
        $o = array('ok' => true, 'rows' => array(),
                   'totals' => array('planned' => 0.0, 'actual' => 0.0, 'billed' => 0.0), 'note' => '');
        foreach (ContractLineService::linesOf($gate, (int) $contractId, false) as $l) {
            $lid = (int) $l['id'];
            // **النسخةُ التي حكمت الفترةَ المسؤولَ عنها** لا نسخةَ اليوم — درسُ
            // `P-03` حرفيًّا: الافتراضُ «اليوم» يجعل تقريرَ سنةٍ يتغيّر كلما مرّ
            // يوم، **ويُفرِغ تقريرَ سنةٍ مستقبليةٍ من كل صفوفه**.
            $onDate = ($fromMonth !== '' && preg_match('/^\d{4}-\d{2}$/', (string) $fromMonth))
                      ? ((string) $fromMonth . '-01')
                      : (string) $l['valid_from'];
            $plan = ContractMonthlyPlanService::effectivePlan($gate, $lid, $onDate);
            foreach ($plan['rows'] as $r) {
                $mm = (string) $r['period_month'];
                if ($fromMonth !== '' && $mm < $fromMonth) { continue; }
                if ($toMonth !== '' && $mm > $toMonth) { continue; }
                $planned = round((float) $r['qty_planned'], 2);
                $actual = self::sumUnits($gate, $lid, (int) $r['id']);
                $billed = self::sumClaimLines($gate, $lid, (int) $r['id']);
                $o['rows'][] = array(
                    'line_id' => $lid, 'line_no' => (int) $l['line_no'],
                    'description' => (string) $l['description'],
                    'period_month' => $mm, 'plan_period_id' => (int) $r['id'],
                    'unit' => (string) $l['pricing_model'],
                    'planned' => $planned, 'actual' => $actual, 'billed' => $billed,
                    'gap_exec' => round($actual - $planned, 2),
                    'gap_bill' => round($billed - $actual, 2),
                );
                $o['totals']['planned'] = round($o['totals']['planned'] + $planned, 2);
                $o['totals']['actual'] = round($o['totals']['actual'] + $actual, 2);
                $o['totals']['billed'] = round($o['totals']['billed'] + $billed, 2);
            }
        }
        $t = $o['totals'];
        $o['note'] = 'مخطط ' . $t['planned'] . ' · منفذ ' . $t['actual'] . ' · مفوتر ' . $t['billed']
            . ' — **ثلاثة التقت على مفتاح واحد لا على تاريخ متقارب**';
        return $o;
    }

    public static function sumUnits($gate, $lineId, $planPeriodId = 0)
    {
        try {
            $w = ((int) $planPeriodId > 0) ? ' AND u.plan_period_id = ?' : '';
            $p = array((int) $lineId);
            if ((int) $planPeriodId > 0) { $p[] = (int) $planPeriodId; }
            $r = $gate->scopedQuery(array('scope' => array('u' => 'unit_entries')),
                "SELECT ROUND(COALESCE(SUM(u.qty),0),2) AS s FROM unit_entries u
                  WHERE {TENANT_SCOPE} AND u.contract_line_id = ?
                    AND u.state NOT IN ('rejected','cancelled','reversed','superseded')" . $w, $p);
            return $r ? round((float) $r[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    public static function sumClaimLines($gate, $lineId, $planPeriodId = 0)
    {
        try {
            $w = ((int) $planPeriodId > 0) ? ' AND l.plan_period_id = ?' : '';
            $p = array((int) $lineId);
            if ((int) $planPeriodId > 0) { $p[] = (int) $planPeriodId; }
            $r = $gate->scopedQuery(array('scope' => array('l' => 'claim_lines')),
                "SELECT ROUND(COALESCE(SUM(l.qty),0),2) AS s FROM claim_lines l
                  WHERE {TENANT_SCOPE} AND l.contract_line_id = ?" . $w, $p);
            return $r ? round((float) $r[0]['s'], 2) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    /**
     * ملءُ `fin_financial_events.contract_line_id` من الوحدة الموصولة —
     * **وعدُ 2026-08-08 يُوفَّى**. عاطلٌ وبوضعِ تجريب.
     */
    public static function fillEventLines($conn, $gate, $companyId, $apply = false)
    {
        $o = array('candidates' => 0, 'filled' => 0, 'note' => '');
        $rows = array();
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('e' => 'fin_financial_events'),
                      'enrich' => array('u' => 'unit_entries')),
                "SELECT e.id, u.contract_line_id FROM fin_financial_events e
                   LEFT JOIN unit_entries u ON u.id = e.entity_id AND e.entity_type = 'unit_entry'
                  WHERE {TENANT_SCOPE} AND e.contract_line_id IS NULL
                    AND u.contract_line_id IS NOT NULL LIMIT 500");
        } catch (\Throwable $t) { ems_catch_log($t, __METHOD__); ems_catch_ignored($t, __METHOD__, 'قراءة/كتابة فاشلة تعامل كقائمة فارغة — $rows'); $rows = array(); }
        $o['candidates'] = count($rows);
        foreach ($rows as $r) {
            if (!$apply) { continue; }
            try {
                $gate->update('fin_financial_events',
                    array('contract_line_id' => (int) $r['contract_line_id']),
                    array('id' => (int) $r['id']));
                $o['filled']++;
            } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'حدث لا يكتب لا يسقط الباقي'); /* حدثٌ لا يُكتب لا يُسقط الباقي */ }
        }
        $o['note'] = ($apply ? 'ملئ ' : 'مرشح ') . ($apply ? $o['filled'] : $o['candidates'])
            . ' حدثا — **ووعد `contract_line_id` منذ 2026-08-08 يجد مرجعه**';
        return $o;
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'plan_actual_link', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
