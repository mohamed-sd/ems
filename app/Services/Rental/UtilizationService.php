<?php
/**
 * app/Services/Rental/UtilizationService.php — الاستغلالُ والمردود (RENTAL-CORE ③)
 * ═══════════════════════════════════════════════════════════════════════════
 * لغةُ قطاع التأجير أربعةُ أرقام. واخترنا منها ما يصدُق على **هذا** الأسطول:
 *
 *   ① الاستغلالُ المادّي  = أيامُ التأجير ÷ أيام المدة        — يصدُق دائمًا
 *   ② هامشُ المعدة        = إيرادُ العميل − تكلفةُ المورد     — البديلُ الصادق
 *   ③ نسبةُ الهامش        = الهامش ÷ الإيراد
 *   ④ مردودُ رأس المال    = الإيراد ÷ تكلفة الاقتناء (OEC)    — حيث تتوفر فقط
 *
 * لماذا الهامشُ بديلًا لا مردودَ رأس المال أساسًا؟ لأن القياس قال: **212 من 219
 * معدةً مورَّدةٌ لا مملوكة**، و`acquisition_cost` ممتلئٌ في 14٪ فقط. فمردودُ رأس
 * المال على أسطولٍ مستأجَرٍ من الغير رقمٌ مضلِّل — والهامشُ هو ما يربح فعلًا.
 * ولذلك يُعرض ④ حيث توفّرت تكلفتُه ويُعلَن غيابُه صراحةً حيث لم تتوفر — لا صفرًا
 * مضلِّلًا (عرفُ «الإعلانُ لا الإخفاء»).
 *
 * المصادر: operations (زمنُ التأجير) · unit_party_awards (الاعترافُ الثلاثي) ·
 * equipments (التصنيفُ والتكلفة). كلُّها عبر البوابة.
 */

namespace App\Services\Rental;

class UtilizationService
{
    /**
     * مؤشراتُ كل معدةٍ في مدة.
     *
     * @return array صفوفٌ: equipment_id · code · name · type_name · rented_days ·
     *               util_pct · revenue · supplier_cost · margin · margin_pct ·
     *               oec · oec_yield_pct (null إن غابت التكلفة) · currency_mix
     */
    public static function byEquipment($gate, $from, $to, $typeId = 0)
    {
        if (!self::validDate($from) || !self::validDate($to)) { return array(); }
        $spanDays = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);

        // ① الأسطولُ والتصنيفُ والتكلفة
        $params = array(); $typeSql = '';
        if ((int) $typeId > 0) { $typeSql = ' AND e.type = ? '; $params[] = (int) $typeId; }
        $fleet = array();
        try {
            $fleet = $gate->scopedQuery(
                array('scope' => array('e' => 'equipments'), 'enrich' => array('t' => 'equipments_types')),
                "SELECT e.id, e.code, e.name, e.type, e.acquisition_cost, e.acquisition_currency,
                        e.availability_status, t.type AS type_name
                   FROM equipments e
                   LEFT JOIN equipments_types t ON t.id = e.type
                  WHERE {TENANT_SCOPE} $typeSql
                  ORDER BY e.code",
                $params
            );
        } catch (\Throwable $t) { error_log('UtilizationService fleet: ' . $t->getMessage()); return array(); }
        if (!count($fleet)) { return array(); }

        // ② أيامُ التأجير من التشغيلات المتقاطعة مع المدة (تقاطعٌ مقصوصٌ على النافذة)
        $rented = array();
        try {
            $ops = $gate->scopedQuery(
                array('scope' => array('o' => 'operations')),
                "SELECT o.equipment,
                        SUM(DATEDIFF(
                            LEAST(COALESCE(o.end, ?), ?),
                            GREATEST(o.start, ?)
                        ) + 1) AS d
                   FROM operations o
                  WHERE {TENANT_SCOPE}
                    AND o.status = '1' AND o.start IS NOT NULL
                    AND o.start <= ? AND COALESCE(o.end, ?) >= ?
                  GROUP BY o.equipment",
                array(AvailabilityService::OPEN_END, $to, $from, $to, AvailabilityService::OPEN_END, $from)
            );
            foreach ($ops as $r) { $rented[(int) $r['equipment']] = max(0, (int) $r['d']); }
        } catch (\Throwable $t) { error_log('UtilizationService ops: ' . $t->getMessage()); }

        // ③ الاعترافُ الثلاثي — إيرادُ العميل وتكلفةُ المورد لكل معدة
        //    unit_party_awards لا يحمل equipment مباشرةً، فنعبر إلى الوقائع المالية
        //    التي تحمل equipment_id (5,212 من 5,214 واقعة إيراد تحملها).
        $rev = array(); $cost = array(); $curr = array();
        try {
            $fe = $gate->scopedQuery(
                array('scope' => array('f' => 'fin_financial_events')),
                "SELECT f.equipment_id, f.event_type, f.currency,
                        ROUND(SUM(f.amount),2) AS amt
                   FROM fin_financial_events f
                  WHERE {TENANT_SCOPE}
                    AND f.equipment_id > 0
                    AND COALESCE(f.is_deleted,0) = 0
                    AND f.event_type IN ('revenue','expense')
                    AND DATE(COALESCE(f.occurred_at, f.created_at)) BETWEEN ? AND ?
                  GROUP BY f.equipment_id, f.event_type, f.currency",
                array($from, $to)
            );
            foreach ($fe as $r) {
                $eid = (int) $r['equipment_id'];
                $c   = (string) $r['currency'];
                if (!isset($curr[$eid])) { $curr[$eid] = array(); }
                $curr[$eid][$c] = true;
                if ($r['event_type'] === 'revenue') { $rev[$eid] = ($rev[$eid] ?? 0) + (float) $r['amt']; }
                else { $cost[$eid] = ($cost[$eid] ?? 0) + (float) $r['amt']; }
            }
        } catch (\Throwable $t) { error_log('UtilizationService fe: ' . $t->getMessage()); }

        // ④ التركيب
        $out = array();
        foreach ($fleet as $e) {
            $eid = (int) $e['id'];
            $rd  = isset($rented[$eid]) ? min($rented[$eid], $spanDays) : 0;
            $r   = isset($rev[$eid]) ? $rev[$eid] : 0.0;
            $c   = isset($cost[$eid]) ? $cost[$eid] : 0.0;
            $m   = $r - $c;
            $oec = (float) $e['acquisition_cost'];
            $mix = isset($curr[$eid]) ? array_keys($curr[$eid]) : array();

            $out[] = array(
                'equipment_id'  => $eid,
                'code'          => $e['code'],
                'name'          => $e['name'],
                'type_name'     => ($e['type_name'] !== null && $e['type_name'] !== '') ? $e['type_name'] : 'غير مصنَّفة',
                'rented_days'   => $rd,
                'span_days'     => $spanDays,
                'util_pct'      => $spanDays > 0 ? round(100.0 * $rd / $spanDays, 1) : 0.0,
                'revenue'       => round($r, 2),
                'supplier_cost' => round($c, 2),
                'margin'        => round($m, 2),
                'margin_pct'    => $r > 0 ? round(100.0 * $m / $r, 1) : null,
                'oec'           => $oec > 0 ? $oec : null,
                'oec_yield_pct' => ($oec > 0 && $r > 0) ? round(100.0 * $r / $oec, 1) : null,
                'currency_mix'  => $mix,
            );
        }
        return $out;
    }

    /** خلاصةٌ عليا للأسطول في المدة. */
    public static function summary(array $rows)
    {
        $n = count($rows);
        if (!$n) {
            return array('fleet' => 0, 'rented' => 0, 'idle' => 0, 'avg_util' => 0.0,
                'revenue' => 0.0, 'cost' => 0.0, 'margin' => 0.0, 'margin_pct' => null,
                'with_oec' => 0, 'mixed_currency' => 0, 'negative_margin' => 0);
        }
        $rented = 0; $util = 0.0; $rev = 0.0; $cost = 0.0; $withOec = 0; $mixed = 0; $neg = 0;
        foreach ($rows as $r) {
            if ($r['rented_days'] > 0) { $rented++; }
            $util += (float) $r['util_pct'];
            $rev  += (float) $r['revenue'];
            $cost += (float) $r['supplier_cost'];
            if ($r['oec'] !== null) { $withOec++; }
            if (count($r['currency_mix']) > 1) { $mixed++; }
            if ((float) $r['margin'] < 0) { $neg++; }
        }
        $margin = $rev - $cost;
        return array(
            'fleet' => $n, 'rented' => $rented, 'idle' => $n - $rented,
            'avg_util' => round($util / $n, 1),
            'revenue' => round($rev, 2), 'cost' => round($cost, 2), 'margin' => round($margin, 2),
            'margin_pct' => $rev > 0 ? round(100.0 * $margin / $rev, 1) : null,
            'with_oec' => $withOec, 'mixed_currency' => $mixed, 'negative_margin' => $neg,
        );
    }

    /** خلاصةٌ بالفئة — أيُّ فئةٍ تُطعم وأيُّها تُعطَّل. */
    public static function byType(array $rows)
    {
        $agg = array();
        foreach ($rows as $r) {
            $k = $r['type_name'];
            if (!isset($agg[$k])) {
                $agg[$k] = array('type_name' => $k, 'fleet' => 0, 'rented' => 0,
                    'rented_days' => 0, 'span_days' => $r['span_days'],
                    'revenue' => 0.0, 'cost' => 0.0);
            }
            $agg[$k]['fleet']++;
            if ($r['rented_days'] > 0) { $agg[$k]['rented']++; }
            $agg[$k]['rented_days'] += $r['rented_days'];
            $agg[$k]['revenue'] += (float) $r['revenue'];
            $agg[$k]['cost']    += (float) $r['supplier_cost'];
        }
        foreach ($agg as $k => $v) {
            $cap = $v['fleet'] * max(1, $v['span_days']);
            $agg[$k]['util_pct'] = $cap > 0 ? round(100.0 * $v['rented_days'] / $cap, 1) : 0.0;
            $agg[$k]['margin']   = round($v['revenue'] - $v['cost'], 2);
            $agg[$k]['margin_pct'] = $v['revenue'] > 0
                ? round(100.0 * ($v['revenue'] - $v['cost']) / $v['revenue'], 1) : null;
        }
        usort($agg, function ($a, $b) { return ($b['revenue'] <=> $a['revenue']); });
        return $agg;
    }

    private static function validDate($d)
    {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1;
    }
}
