<?php
/**
 * app/Services/Transport/TransferTariffService.php — تعرفةُ الترحيل (M-52)
 * ═══════════════════════════════════════════════════════════════════════════
 * ENT-02 §3 · التحميل ④: «**أمرُ الترحيل المسلَّم · بتعرفته**».
 * §3 الحاكمة: «**لا إدخالَ حرًّا** — كلُّ تحميلٍ سطرٌ برابط مستنده؛ و**ما لا
 * مستندَ له لا يُحمَّل** — والمبلغُ **يُقرأ من مصدره لا يُكتب**».
 *
 * ── أربعُ قواعدَ ─────────────────────────────────────────────────────────────
 * ① **لا تحميلَ بلا تعرفةٍ مكتوبة**: بلا تعرفةٍ منطبقةٍ **422 بسببه** — ولا
 *    تُستعمل `actual_cost_usd` بديلًا: **تلك تكلفتُنا لا تعرفتُه**.
 * ② **والمسلَّمُ وحدَه يُسعَّر**: مرحلةٌ خارج (`arrived`·`closed`) ⇒ **423**.
 * ③ **والأخصُّ يغلب**: موردٌ(4) > مسارٌ(2) > نوعٌ(1) > الأعمّ — والأخصُّ وحدَه.
 * ④ **ولا يُسعَّر مرتين**: **409** بمبلغه ومرجعه، والتصحيحُ بإعادةِ تسعيرٍ
 *    صريحةٍ **بسببٍ مكتوب** لا بكتابةٍ صامتة.
 */

namespace App\Services\Transport;

class TransferTariffService
{
    /** «أمرُ الترحيل **المسلَّم**» — ولا يُسعَّر ما لم يصل. */
    const DELIVERED_STAGES = array('arrived', 'closed');

    const MODELS = array('per_trip', 'per_km', 'per_ton', 'per_equipment');

    const MODEL_LABEL_AR = array(
        'per_trip' => 'بالنقلة', 'per_km' => 'بالكيلومتر',
        'per_ton' => 'بالطن', 'per_equipment' => 'بالمعدة',
    );

    // ═════════════════════════════════════════════════════════════════════
    // ① التعرفةُ المنطبقة — والأخصُّ يغلب
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,code:int,reason:string,tariff:?array,candidates:int}
     */
    public static function resolve($gate, array $order, $onDate = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'tariff' => null, 'candidates' => 0);
        $day = ($onDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $onDate))
               ? (string) $onDate : self::deliveredOn($order);
        if ($day === '') {
            $out['code'] = 422; $out['reason'] = 'لا تاريخَ تسليمٍ للأمر — والتعرفةُ تُقرأ بتاريخها'; return $out;
        }
        $supplier = isset($order['charge_supplier_id']) && $order['charge_supplier_id'] !== null
                    ? (int) $order['charge_supplier_id'] : 0;
        if ($supplier <= 0) {
            $out['code'] = 422;
            $out['reason'] = 'الأمرُ بلا موردٍ محمَّل — «على من يُحمَّل» يُكتب قبل التسعير';
            return $out;
        }

        $rows = array();
        try {
            $rows = $gate->scopedQuery(array('scope' => array('t' => 'transfer_tariffs')),
                "SELECT t.* FROM transfer_tariffs t
                  WHERE {TENANT_SCOPE} AND COALESCE(t.is_deleted,0)=0 AND t.state = 'active'
                    AND t.effective_from <= ?
                    AND (t.effective_to IS NULL OR t.effective_to >= ?)
                    AND (t.supplier_id IS NULL OR t.supplier_id = ?)
                    AND (t.transfer_type_id IS NULL OR t.transfer_type_id = ?)
                    AND (t.from_location_id IS NULL OR t.from_location_id = ?)
                    AND (t.to_location_id IS NULL OR t.to_location_id = ?)
                  ORDER BY t.effective_from DESC, t.id DESC",
                array($day, $day, $supplier,
                      (int) $order['transfer_type_id'],
                      (int) $order['from_location_id'], (int) $order['to_location_id']));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّرت قراءةُ التعرفات: ' . $t->getMessage(); return $out;
        }
        $out['candidates'] = count($rows);
        if (!$rows) {
            $out['code'] = 422;
            $out['reason'] = '**لا تعرفةَ مكتوبةً منطبقة** على هذا الأمر في ' . $day
                . ' (مورد ' . $supplier . ' · نوع ' . (int) $order['transfer_type_id']
                . ' · مسار ' . (int) $order['from_location_id'] . '←' . (int) $order['to_location_id']
                . ') — ولا يُخترع سعر';
            return $out;
        }

        // ③ الأخصُّ يغلب — والأوّلُ الواصلُ عند التعادل (الأحدثُ سريانًا)
        $best = null; $bestScore = -1;
        foreach ($rows as $r) {
            $score = ($r['supplier_id'] !== null ? 4 : 0)
                   + (($r['from_location_id'] !== null || $r['to_location_id'] !== null) ? 2 : 0)
                   + ($r['transfer_type_id'] !== null ? 1 : 0);
            if ($score > $bestScore) { $bestScore = $score; $best = $r; }
        }
        $out['ok'] = true; $out['code'] = 200; $out['tariff'] = $best;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ② الاحتساب — «المبلغُ يُقرأ من مصدره لا يُكتب»
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,code:int,reason:string,amount:float,qty:float,
     *               currency:string,note:string,clamped:?string}
     */
    public static function computeAmount($gate, array $order, array $tariff)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'amount' => 0.0,
                     'qty' => 0.0, 'currency' => (string) $tariff['currency'],
                     'note' => '', 'clamped' => null);
        $model = (string) $tariff['pricing_model'];
        $qty = 0.0;

        switch ($model) {
            case 'per_trip':
                $qty = 1.0;
                break;
            case 'per_km':
                if ($order['distance_km'] === null || (float) $order['distance_km'] <= 0) {
                    $out['code'] = 422;
                    $out['reason'] = 'تعرفةٌ **بالكيلومتر** وأمرٌ **بلا مسافةٍ مكتوبة** — '
                                   . 'اكتب `distance_km` أو اختر تعرفةً أخرى؛ ولا تُقدَّر مسافة';
                    return $out;
                }
                $qty = round((float) $order['distance_km'], 2);
                break;
            case 'per_ton':
                $qty = self::qtyOfLines($gate, (int) $order['id'], 'material');
                if ($qty <= 0) {
                    $out['code'] = 422;
                    $out['reason'] = 'تعرفةٌ **بالطن** والأمرُ بلا بنودِ موادَّ بكمياتها — لا كميةَ تُسعَّر';
                    return $out;
                }
                break;
            case 'per_equipment':
                $qty = self::qtyOfLines($gate, (int) $order['id'], 'equipment');
                if ($qty <= 0) {
                    $out['code'] = 422;
                    $out['reason'] = 'تعرفةٌ **بالمعدة** والأمرُ بلا بندِ معدةٍ واحد';
                    return $out;
                }
                break;
            default:
                $out['code'] = 422; $out['reason'] = 'نموذجُ تسعيرٍ غير معروف: ' . $model; return $out;
        }

        $amount = round($qty * (float) $tariff['rate'], 2);
        $raw = $amount;
        if ($tariff['min_amount'] !== null && $amount < (float) $tariff['min_amount']) {
            $amount = round((float) $tariff['min_amount'], 2); $out['clamped'] = 'min';
        }
        if ($tariff['max_amount'] !== null && $amount > (float) $tariff['max_amount']) {
            $amount = round((float) $tariff['max_amount'], 2); $out['clamped'] = 'max';
        }

        $note = 'تعرفة #' . (int) $tariff['id'] . ' ' . self::MODEL_LABEL_AR[$model]
              . ': ' . $qty . ' × ' . rtrim(rtrim(number_format((float) $tariff['rate'], 4, '.', ''), '0'), '.')
              . ' = ' . $raw;
        if ($out['clamped'] !== null) {
            $note .= ' · **قُصّ بالحدّ ال' . ($out['clamped'] === 'min' ? 'أدنى' : 'أقصى')
                   . '** إلى ' . $amount;
        }
        $out['ok'] = true; $out['code'] = 200; $out['amount'] = $amount;
        $out['qty'] = $qty; $out['note'] = $note;
        return $out;
    }

    /**
     * تسعيرُ أمرٍ مسلَّمٍ بتعرفته — الكتابةُ الوحيدةُ لـ`tariff_amount`.
     * @return array{ok:bool,code:int,reason:string,amount:float,tariff_id:?int,note:string}
     */
    public static function priceOrder($conn, $gate, $companyId, $orderId, $actor, $force = '', $onDate = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'amount' => 0.0,
                     'tariff_id' => null, 'note' => '');
        $o = self::orderOf($gate, (int) $orderId);
        if (!$o) { $out['code'] = 404; $out['reason'] = 'أمرُ الترحيل غيرُ موجودٍ في نطاقك'; return $out; }

        // ② المسلَّمُ وحدَه يُسعَّر
        if (!in_array((string) $o['stage'], self::DELIVERED_STAGES, true)) {
            $out['code'] = 423;
            $out['reason'] = 'الأمرُ في مرحلة «' . $o['stage'] . '» — و«أمرُ الترحيل **المسلَّم**» '
                           . '(ENT-02 §3-④): وأمرٌ لم يصل ليس مستندَ تحميل';
            return $out;
        }
        // ④ ولا يُسعَّر مرتين
        $reason = trim((string) $force);
        if ($o['tariff_amount'] !== null && $reason === '') {
            $out['code'] = 409;
            $out['amount'] = round((float) $o['tariff_amount'], 2);
            $out['tariff_id'] = $o['tariff_id'] !== null ? (int) $o['tariff_id'] : null;
            $out['reason'] = 'الأمرُ مسعَّرٌ سلفًا بـ' . $out['amount'] . ' ' . (string) $o['tariff_currency']
                           . ' (تعرفة #' . (int) $o['tariff_id'] . ') — وإعادةُ التسعير تلزمها **حجّةٌ مكتوبة**';
            return $out;
        }

        $res = self::resolve($gate, $o, $onDate);
        if (!$res['ok']) { return array_merge($out, array('code' => $res['code'], 'reason' => $res['reason'])); }
        $calc = self::computeAmount($gate, $o, $res['tariff']);
        if (!$calc['ok']) { return array_merge($out, array('code' => $calc['code'], 'reason' => $calc['reason'])); }

        $note = $calc['note'];
        if ($reason !== '') { $note .= ' · **أُعيد التسعيرُ**: ' . mb_substr($reason, 0, 90); }

        try {
            $gate->update('transfer_orders', array(
                'tariff_id'       => (int) $res['tariff']['id'],
                'tariff_amount'   => $calc['amount'],
                'tariff_currency' => $calc['currency'],
                'tariff_note'     => mb_substr($note, 0, 255),
                'priced_at'       => date('Y-m-d H:i:s'),
                'priced_by'       => (int) $actor ?: null,
            ), array('id' => (int) $orderId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر التسعير: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'price', (int) $orderId,
            array('tariff_amount' => $o['tariff_amount']),
            array('tariff_amount' => $calc['amount'], 'tariff_id' => (int) $res['tariff']['id'],
                  'note' => $note));

        $out['ok'] = true; $out['code'] = 200; $out['amount'] = $calc['amount'];
        $out['tariff_id'] = (int) $res['tariff']['id']; $out['note'] = $note;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ③ الوصلُ الحي — المصدرُ السادس في تسوية المورد
    // ═════════════════════════════════════════════════════════════════════

    /**
     * أسطرُ تحميل النقل لموردٍ في فترة — «سطرٌ برابط مستنده».
     * @return array
     */
    public static function chargeLines($gate, $supplierId, $from, $to)
    {
        $lines = array();
        $rows = array();
        try {
            $stages = "'" . implode("','", self::DELIVERED_STAGES) . "'";
            $rows = $gate->scopedQuery(array('scope' => array('o' => 'transfer_orders')),
                "SELECT o.id, o.order_no, o.tariff_amount, o.tariff_currency, o.tariff_note,
                        DATE(COALESCE(o.arrival_datetime, o.planned_date, o.request_date)) AS d_date
                   FROM transfer_orders o
                  WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0
                    AND o.charge_supplier_id = ?
                    AND o.stage IN ({$stages})
                    AND o.tariff_amount IS NOT NULL AND o.tariff_amount > 0
                    AND DATE(COALESCE(o.arrival_datetime, o.planned_date, o.request_date)) BETWEEN ? AND ?
                  ORDER BY o.id", array((int) $supplierId, (string) $from, (string) $to));
        } catch (\Throwable $t) {
            error_log('M-52 chargeLines: ' . $t->getMessage());
            return $lines;
        }
        if (!$rows) { return $lines; }

        require_once dirname(__DIR__) . '/Settlement/SupplierRuleService.php';
        foreach ($rows as $x) {
            // M-15: التكلفةُ تمرّ بقاعدة التحميل المكتوبة إن وُجدت — وبلا قاعدةٍ
            // **تُحمَّل كما هي موسومةً**، لا نسبةٌ مخترَعة ولا حجب.
            $priced = \App\Services\Settlement\SupplierRuleService::priceCharge(
                $gate, $supplierId, 'transport', (float) $x['tariff_amount'], (string) $x['d_date']);
            $lines[] = array(
                'line_kind'   => 'charge',
                'charge_type' => 'transport',
                'source_kind' => 'transfer_order',
                'source_ref'  => (string) $x['id'],
                'description' => 'ترحيل — أمر ' . (string) $x['order_no']
                                 . ' · ' . (string) $x['tariff_note']
                                 . ' · ' . $priced['note'],
                'work_date'   => (string) $x['d_date'],
                'amount'      => (float) $priced['amount'],
                'currency'    => ($x['tariff_currency'] !== null && (string) $x['tariff_currency'] !== '')
                                 ? (string) $x['tariff_currency'] : 'SDG',
            );
        }
        return $lines;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ④ قراءات
    // ═════════════════════════════════════════════════════════════════════

    public static function orderOf($gate, $orderId)
    {
        try { return $gate->selectOne('transfer_orders', array('where' => array('id' => (int) $orderId))); }
        catch (\Throwable $t) { return null; }
    }

    public static function tariffs($gate, $limit = 200)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('t' => 'transfer_tariffs'),
                      'enrich' => array('s' => 'suppliers', 'ty' => 'transfer_types',
                                        'lf' => 'trs_locations', 'lt' => 'trs_locations')),
                "SELECT t.*, s.name AS supplier_name, ty.name AS type_name,
                        lf.name AS from_name, lt.name AS to_name
                   FROM transfer_tariffs t
                   LEFT JOIN suppliers s ON s.id = t.supplier_id
                   LEFT JOIN transfer_types ty ON ty.id = t.transfer_type_id
                   LEFT JOIN trs_locations lf ON lf.id = t.from_location_id
                   LEFT JOIN trs_locations lt ON lt.id = t.to_location_id
                  WHERE {TENANT_SCOPE} AND COALESCE(t.is_deleted,0)=0
                  ORDER BY t.state, t.effective_from DESC, t.id DESC LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { error_log('M-52 tariffs: ' . $t->getMessage()); return array(); }
    }

    /** الأوامرُ المسلَّمةُ المحمَّلةُ على موردٍ — بحالة تسعيرها (الفجوةُ ظاهرة). */
    public static function deliveredOrders($gate, $limit = 200)
    {
        $stages = "'" . implode("','", self::DELIVERED_STAGES) . "'";
        try {
            return $gate->scopedQuery(
                array('scope' => array('o' => 'transfer_orders'),
                      'enrich' => array('s' => 'suppliers')),
                "SELECT o.id, o.order_no, o.stage, o.charge_supplier_id, o.transfer_type_id,
                        o.from_location_id, o.to_location_id, o.distance_km,
                        o.tariff_id, o.tariff_amount, o.tariff_currency, o.tariff_note,
                        DATE(COALESCE(o.arrival_datetime, o.planned_date, o.request_date)) AS d_date,
                        s.name AS supplier_name
                   FROM transfer_orders o
                   LEFT JOIN suppliers s ON s.id = o.charge_supplier_id
                  WHERE {TENANT_SCOPE} AND COALESCE(o.is_deleted,0)=0 AND o.stage IN ({$stages})
                  ORDER BY (o.tariff_amount IS NOT NULL) ASC, o.id DESC LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { error_log('M-52 deliveredOrders: ' . $t->getMessage()); return array(); }
    }

    private static function qtyOfLines($gate, $orderId, $itemType)
    {
        try {
            $r = $gate->scopedQuery(array('scope' => array('l' => 'transfer_lines')),
                "SELECT ROUND(SUM(COALESCE(l.quantity,1)),2) AS q FROM transfer_lines l
                  WHERE {TENANT_SCOPE} AND l.order_id = ? AND l.item_type = ?
                    AND COALESCE(l.is_deleted,0)=0",
                array((int) $orderId, (string) $itemType));
            return ($r && $r[0]['q'] !== null) ? round((float) $r[0]['q'], 2) : 0.0;
        } catch (\Throwable $t) { return 0.0; }
    }

    private static function deliveredOn(array $o)
    {
        foreach (array('arrival_datetime', 'planned_date', 'request_date') as $k) {
            if (isset($o[$k]) && $o[$k] !== null && (string) $o[$k] !== '') {
                return substr((string) $o[$k], 0, 10);
            }
        }
        return '';
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'transport', 'transfer_orders', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
