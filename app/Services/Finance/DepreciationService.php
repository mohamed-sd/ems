<?php
/**
 * app/Services/Finance/DepreciationService.php — الإهلاكُ حدثًا دوريًّا (M-30)
 * ═══════════════════════════════════════════════════════════════════════════
 * SPEC-01 #32: «**الإهلاكُ حدثٌ دوريٌّ بمفتاح (الأصل × الفترة)** بطريقةٍ من
 * إعداده» · «أثر: **قيدُ الإهلاك الدوري آليًّا بمرجع الأصل والفترة**».
 *
 * ── خمسُ قواعد ──────────────────────────────────────────────────────────────
 * ① **لا إهلاكَ قبل الاقتناء**: فترةٌ سابقةٌ لشهر `acquisition_date` تُتخطّى
 *    **بسببٍ معلَن** — ولا يُهلَك أصلٌ لم يكن بعدُ. (الخللُ المقيس: الشاشةُ كانت
 *    تتجاهل التاريخَ تجاهلًا تامًّا.)
 * ② **والفترةُ مُدخَلٌ لا حاضر**: أيُّ شهرٍ يُحتسب، و`catchUp` يستدرك ما فات.
 * ③ **وقفلُ الفترة يحكم**: `ems_period_check` ⇒ **423 بلا كتابة** (M-39).
 * ④ **والمفتاحُ يمنع التكرار مرتين**: `UNIQUE` في الجدول **ومفتاحُ عطالةٍ
 *    حتميٌّ** في الناشر — فإعادةُ التشغيل تعيد مرجعَ الحدث القائم لا حدثًا ثانيًا.
 * ⑤ **ولا تُهلَك خردةٌ ولا زيادة**: القسطُ مقصوصٌ بالمتبقّي، وببلوغه
 *    `fully_depreciated`.
 */

namespace App\Services\Finance;

class DepreciationService
{
    /** الحالةُ التي يقع عليها الإهلاك. */
    const DEPRECIABLE_STATE = 'active';

    // ═════════════════════════════════════════════════════════════════════
    // ① الاحتساب — قراءةٌ خالصةٌ تصلح للمعاينة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * قسطُ شهرٍ لأصلٍ — أو سببُ عدم الاحتساب.
     * @return array{ok:bool,code:int,reason:string,amount:float,basis:array}
     */
    public static function computeFor(array $asset, $period)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'amount' => 0.0, 'basis' => array());
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            $out['code'] = 422; $out['reason'] = 'الفترةُ بصيغة YYYY-MM'; return $out;
        }
        $life = (int) $asset['useful_life_months'];
        if ($life <= 0) {
            $out['code'] = 422;
            $out['reason'] = 'العمرُ الإنتاجيُّ غيرُ مكتوب — ولا يُفترض له رقم';
            return $out;
        }

        // ① لا إهلاكَ قبل الاقتناء — والتاريخُ الغائبُ يُعلَن ولا يُتجاوز صامتًا
        $acq = ($asset['acquisition_date'] !== null && (string) $asset['acquisition_date'] !== '0000-00-00')
               ? substr((string) $asset['acquisition_date'], 0, 7) : '';
        if ($acq === '') {
            $out['code'] = 422;
            $out['reason'] = 'تاريخُ الاقتناء غيرُ مكتوب — ولا يُعرف متى يبدأ الإهلاك';
            return $out;
        }
        if ((string) $period < $acq) {
            $out['code'] = 422;
            $out['reason'] = 'الفترةُ ' . $period . ' **قبل شهر الاقتناء** ' . $acq . ' — لا إهلاكَ لما لم يُقتنَ بعد';
            return $out;
        }

        $cost = round((float) $asset['acquisition_cost'], 2);
        $salv = round((float) $asset['salvage_value'], 2);
        $acc  = round((float) $asset['accumulated_depreciation'], 2);
        $depreciable = round($cost - $salv, 2);
        if ($depreciable <= 0) {
            $out['code'] = 422;
            $out['reason'] = 'القيمةُ القابلةُ للإهلاك صفرٌ أو سالبة (التكلفة ' . $cost . ' والخردة ' . $salv . ')';
            return $out;
        }
        $remaining = round($depreciable - $acc, 2);
        if ($remaining <= 0) {
            $out['code'] = 409;
            $out['reason'] = 'الأصلُ مُهلَكٌ بالكامل — ولا تُهلَك خردة';
            return $out;
        }

        $monthly = round($depreciable / $life, 2);

        // ⑤ **وآخرُ قسطٍ يستوعب فروقَ التقريب**: القسطُ المدوَّرُ يترك كسرًا
        //    يتيمًا لا يُهلَك أبدًا (مقيس: 10000 ÷ 3 = 3333.33 × 3 = 9999.99 —
        //    قرشٌ يبقى في الدفاتر إلى الأبد). فرقمُ القسط داخل العمر مدوَّر،
        //    و**القسطُ الأخير هو المتبقّي كلُّه** — فالمجموعُ يساوي القابلَ
        //    للإهلاك بالضبط. والترتيبُ يُحسب من شهر الاقتناء لا من عدد الصفوف
        //    (فشهرٌ فات ثم استُدرك لا يزيح الأخير).
        $index = self::monthIndex($acq, (string) $period);   // 1 = شهرُ الاقتناء
        $isFinal = ($index >= $life);
        $amount = $isFinal ? $remaining : min($monthly, $remaining);

        $out['basis'] = array(
            'period' => (string) $period, 'acquired' => $acq,
            'cost' => $cost, 'salvage' => $salv, 'depreciable' => $depreciable,
            'life_months' => $life, 'monthly' => $monthly,
            'instalment_no' => $index, 'is_final' => $isFinal,
            'accumulated_before' => $acc, 'remaining_before' => $remaining,
            'clamped' => (round($amount, 2) !== round($monthly, 2)),
            'method' => (string) $asset['method'],
        );
        $out['ok'] = true; $out['code'] = 200; $out['amount'] = round($amount, 2);
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② التشغيل الدوري
    // ═════════════════════════════════════════════════════════════════════

    /**
     * إهلاكُ فترةٍ للأصول النافذة — كلِّها أو مجموعةٍ مسمّاة.
     *
     * @param array $only معرّفاتُ أصولٍ بعينها (فارغٌ = الكل). النطاقُ المسمّى
     *        بابٌ حقيقيٌّ («أعِد احتساب هذا الأصل») **وشرطُ عزلٍ للاختبار**:
     *        تشغيلٌ بلا نطاقٍ يمسّ كلَّ أصول الشركة — وهو ما يجب أن يقع في
     *        الإنتاج وأن **لا** يقع في حزمةٍ تبذر أصلَين.
     * @return array{ok:bool,code:int,reason:string,posted:int,skipped:array,
     *               total:float,events:int}
     */
    public static function runPeriod($conn, $gate, $companyId, $period, $actor, $source = 'screen', array $only = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'posted' => 0,
                     'skipped' => array(), 'total' => 0.0, 'events' => 0);
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
            $out['code'] = 422; $out['reason'] = 'الفترةُ بصيغة YYYY-MM'; return $out;
        }
        $lastDay = date('Y-m-t', strtotime($period . '-01'));

        // ③ قفلُ الفترة يحكم — قبل أي كتابة
        require_once dirname(__DIR__, 3) . '/includes/period_guard.php';
        $pc = ems_period_check($conn, (int) $companyId, $lastDay);
        if (!$pc['ok']) {
            $out['code'] = 423; $out['reason'] = $pc['reason']; return $out;
        }

        $assets = array();
        try {
            $assets = $gate->select('fin_assets', array(
                'where' => array('state' => self::DEPRECIABLE_STATE),
                'orderBy' => 'id',
            ));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّرت قراءةُ الأصول: ' . $t->getMessage(); return $out;
        }

        $scope = array();
        foreach ($only as $x) { $scope[(int) $x] = true; }

        foreach ($assets as $a) {
            if ($scope && !isset($scope[(int) $a['id']])) { continue; }
            $r = self::postOne($conn, $gate, $companyId, $a, (string) $period, $lastDay, $actor, $source);
            if ($r['ok']) {
                $out['posted']++;
                $out['total'] = round($out['total'] + $r['amount'], 2);
                if ($r['event_id'] !== null) { $out['events']++; }
            } else {
                $out['skipped'][] = array('asset_id' => (int) $a['id'], 'code' => $r['code'],
                                          'reason' => $r['reason']);
            }
        }
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'الفترة ' . $period . ': ' . $out['posted'] . ' أصلًا بمجموع '
                       . $out['total'] . ' · متخطًّى ' . count($out['skipped']);
        return $out;
    }

    /**
     * إهلاكُ أصلٍ واحدٍ لفترة — الكتابةُ الذرّية: صفٌّ + مجمّعٌ + حدث.
     * @return array{ok:bool,code:int,reason:string,amount:float,row_id:?int,event_id:?int}
     */
    public static function postOne($conn, $gate, $companyId, array $asset, $period, $lastDay, $actor, $source = 'screen')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'amount' => 0.0,
                     'row_id' => null, 'event_id' => null);
        $aid = (int) $asset['id'];

        // ④ العطالةُ **قبل** الحساب — «العطالةُ قبل فحص الرصيد»
        $ex = null;
        try {
            $rows = $gate->scopedQuery(array('scope' => array('d' => 'fin_depreciation')),
                "SELECT d.id, d.depreciation_amount, d.event_id FROM fin_depreciation d
                  WHERE {TENANT_SCOPE} AND d.asset_id = ? AND d.period_ref = ? LIMIT 1",
                array($aid, (string) $period));
            $ex = $rows ? $rows[0] : null;
        } catch (\Throwable $t) { $ex = null; }
        if ($ex) {
            $out['code'] = 409;
            $out['reason'] = 'محتسَبٌ سلفًا للفترة ' . $period . ' (صف #' . (int) $ex['id'] . ')';
            $out['amount'] = round((float) $ex['depreciation_amount'], 2);
            $out['row_id'] = (int) $ex['id'];
            $out['event_id'] = $ex['event_id'] !== null ? (int) $ex['event_id'] : null;
            return $out;
        }

        $calc = self::computeFor($asset, $period);
        if (!$calc['ok']) { return array_merge($out, array('code' => $calc['code'], 'reason' => $calc['reason'])); }
        $amount = $calc['amount'];
        if ($amount <= 0) { $out['code'] = 422; $out['reason'] = 'قسطٌ صفريّ'; return $out; }

        $newAcc = round((float) $asset['accumulated_depreciation'] + $amount, 2);
        $newState = ($newAcc >= round($calc['basis']['depreciable'], 2))
                    ? 'fully_depreciated' : (string) $asset['state'];

        // ── الحدثُ أولًا داخل معاملةٍ واحدة: صفرُ صفٍّ بلا حدثٍ وصفرُ حدثٍ يتيم
        $rowId = null; $eventId = null;
        try {
            $gate->runInTransaction(function ($g) use (&$rowId, &$eventId, $conn, $companyId, $asset,
                                                       $aid, $period, $amount, $lastDay, $calc,
                                                       $newAcc, $newState, $actor, $source) {
                $eventId = self::publishEvent($conn, $companyId, $asset, $period, $amount, $lastDay, $actor);
                $rowId = (int) $g->insert('fin_depreciation', array(
                    'asset_id'   => $aid,
                    'period_ref' => (string) $period,
                    'depreciation_amount' => $amount,
                    'run_date'   => date('Y-m-d'),
                    'event_id'   => $eventId,
                    'method'     => (string) $asset['method'],
                    'basis_json' => json_encode($calc['basis'], JSON_UNESCAPED_UNICODE),
                    'source'     => in_array($source, array('screen', 'cron'), true) ? $source : 'screen',
                    'created_by' => (int) $actor ?: null,
                ));
                if ($rowId <= 0) { throw new \RuntimeException('تعذّر إدراجُ صفّ الإهلاك'); }
                $g->update('fin_assets',
                    array('accumulated_depreciation' => $newAcc, 'state' => $newState),
                    array('id' => $aid));
            }, 'إهلاك أصل ' . $aid . ' فترة ' . $period);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الاحتساب: ' . $t->getMessage(); return $out;
        }

        $out['ok'] = true; $out['code'] = 200; $out['amount'] = $amount;
        $out['row_id'] = $rowId; $out['event_id'] = $eventId;
        return $out;
    }

    /**
     * الاستدراك — من شهر الاقتناء حتى آخر شهرٍ **منقضٍ** (لا يُهلَك شهرٌ لم ينتهِ).
     * @return array{ok:bool,code:int,reason:string,periods:array,posted:int,total:float}
     */
    public static function catchUp($conn, $gate, $companyId, $actor, $upTo = null, $source = 'cron', array $only = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'periods' => array(),
                     'posted' => 0, 'total' => 0.0);
        $end = ($upTo !== null && preg_match('/^\d{4}-\d{2}$/', (string) $upTo))
               ? (string) $upTo : date('Y-m', strtotime('first day of last month'));

        $assets = array();
        try {
            $assets = $gate->select('fin_assets', array(
                'where' => array('state' => self::DEPRECIABLE_STATE), 'orderBy' => 'id'));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّرت قراءةُ الأصول'; return $out;
        }
        $scope = array();
        foreach ($only as $x) { $scope[(int) $x] = true; }
        $earliest = null;
        foreach ($assets as $a) {
            if ($scope && !isset($scope[(int) $a['id']])) { continue; }
            if ($a['acquisition_date'] === null) { continue; }
            $m = substr((string) $a['acquisition_date'], 0, 7);
            if ($earliest === null || $m < $earliest) { $earliest = $m; }
        }
        if ($earliest === null) {
            $out['ok'] = true; $out['code'] = 200;
            $out['reason'] = 'لا أصلَ بتاريخ اقتناءٍ مكتوب — لا استدراك';
            return $out;
        }

        $cur = $earliest;
        $guard = 0;
        while ($cur <= $end && $guard++ < 600) {
            $r = self::runPeriod($conn, $gate, $companyId, $cur, $actor, $source, $only);
            $out['periods'][] = array('period' => $cur, 'code' => $r['code'],
                                      'posted' => $r['posted'], 'total' => $r['total'],
                                      'reason' => $r['reason']);
            if ($r['ok']) {
                $out['posted'] += $r['posted'];
                $out['total'] = round($out['total'] + $r['total'], 2);
            }
            $cur = date('Y-m', strtotime($cur . '-01 +1 month'));
        }
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'استُدرك من ' . $earliest . ' إلى ' . $end . ': '
                       . $out['posted'] . ' قسطًا بمجموع ' . $out['total'];
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ قراءات
    // ═════════════════════════════════════════════════════════════════════

    /**
     * الشهورُ غيرُ المحتسَبة لأصلٍ — «الفجوةُ تُرى».
     * والمُهلَكُ بالكامل **بلا فجوة**: شهرٌ لا قسطَ له ليس شهرًا ناقصًا.
     * وكذلك ما تجاوز عمرَه الإنتاجي — فالعمرُ ينتهي والزمنُ يمضي.
     */
    public static function missingPeriods($gate, array $asset, $upTo = null)
    {
        if ((string) $asset['state'] !== self::DEPRECIABLE_STATE) { return array(); }
        $end = ($upTo !== null && preg_match('/^\d{4}-\d{2}$/', (string) $upTo))
               ? (string) $upTo : date('Y-m', strtotime('first day of last month'));
        if ($asset['acquisition_date'] === null) { return array(); }
        $cur = substr((string) $asset['acquisition_date'], 0, 7);
        // لا يمتد الطلبُ أبعدَ من آخر شهرٍ في العمر الإنتاجي
        $lifeEnd = date('Y-m', strtotime($cur . '-01 +' . (max(1, (int) $asset['useful_life_months']) - 1) . ' month'));
        if ($lifeEnd < $end) { $end = $lifeEnd; }

        $done = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('d' => 'fin_depreciation')),
                "SELECT d.period_ref FROM fin_depreciation d
                  WHERE {TENANT_SCOPE} AND d.asset_id = ?", array((int) $asset['id']));
            foreach ($rows as $r) { $done[(string) $r['period_ref']] = true; }
        } catch (\Throwable $t) { return array(); }

        $missing = array(); $guard = 0;
        while ($cur <= $end && $guard++ < 600) {
            if (!isset($done[$cur])) { $missing[] = $cur; }
            $cur = date('Y-m', strtotime($cur . '-01 +1 month'));
        }
        return $missing;
    }

    public static function rowsOf($gate, $assetId = 0, $limit = 300)
    {
        try {
            $where = (int) $assetId > 0 ? ' AND d.asset_id = ?' : '';
            $params = (int) $assetId > 0 ? array((int) $assetId) : array();
            return $gate->scopedQuery(
                array('scope' => array('d' => 'fin_depreciation'), 'enrich' => array('a' => 'fin_assets')),
                "SELECT d.*, a.code AS asset_code, a.name AS asset_name
                   FROM fin_depreciation d
                   LEFT JOIN fin_assets a ON a.id = d.asset_id
                  WHERE {TENANT_SCOPE}" . $where . "
                  ORDER BY d.period_ref DESC, d.asset_id LIMIT " . max(1, (int) $limit), $params);
        } catch (\Throwable $t) { error_log('M-30 rowsOf: ' . $t->getMessage()); return array(); }
    }

    // ═════════════════════════════════════════════════════════════════════

    /** ترتيبُ القسط من شهر الاقتناء — 1 = شهرُ الاقتناء نفسُه. */
    private static function monthIndex($fromMonth, $period)
    {
        $a = (int) substr($fromMonth, 0, 4) * 12 + (int) substr($fromMonth, 5, 2);
        $b = (int) substr($period, 0, 4) * 12 + (int) substr($period, 5, 2);
        return ($b - $a) + 1;
    }

    /** «قيدُ الإهلاك الدوري آليًّا **بمرجع الأصل والفترة**» — بمفتاحٍ حتمي. */
    private static function publishEvent($conn, $companyId, array $asset, $period, $amount, $lastDay, $actor)
    {
        require_once dirname(__DIR__, 2) . '/Core/EventPublisher.php';
        $res = \App\Core\EventPublisher::publish($conn, array(
            'event_key'         => 'expense.depreciation.recorded',
            'category'          => 'financial',
            'source_module'     => 'assets',
            'company_id'        => (int) $companyId,
            'entity_type'       => 'fin_asset',
            'entity_id'         => (int) $asset['id'],
            'occurred_at'       => $lastDay . ' 23:59:59',
            'created_by'        => (int) $actor ?: 1,
            // «بمفتاح (الأصل × الفترة)» — حتميٌّ بلا زمنٍ فيه
            'idempotency_key'   => 'dep:' . (int) $asset['id'] . ':' . (string) $period,
            'legacy_event_type' => 'expense',
            'amount'            => round((float) $amount, 2),
            'currency'          => 'SDG',
            'source_ref'        => (string) $asset['code'],
            'equipment_id'      => ($asset['equipment_id'] !== null && (int) $asset['equipment_id'] > 0)
                                   ? (int) $asset['equipment_id'] : null,
            'notes'             => 'إهلاكُ ' . (string) $asset['code'] . ' — الفترة ' . $period,
            'payload'           => array(
                'asset_id' => (int) $asset['id'], 'asset_code' => (string) $asset['code'],
                'period' => (string) $period, 'amount' => round((float) $amount, 2),
                'method' => (string) $asset['method'],
            ),
        ));
        return (is_array($res) && isset($res['id'])) ? (int) $res['id'] : null;
    }
}
