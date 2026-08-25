<?php
/**
 * الحد الأدنى المضمون — GuaranteedMinimumService (N-10 · PLAN-05 البوابة ②)
 * ───────────────────────────────────────────────────────────────────────────
 * «بندٌ مستقلٌّ باسمه في المستخلص — لا يُدسّ في الكميات»:
 * إن نصّ العقد على حدٍّ أدنى مضمونٍ من الساعات وكان المنفَّذ المفوتَر دونه،
 * أُضيف **بند claim_lines مستقل** بنوعه `guaranteed_min` بكمية العجز وسعر
 * بند البيع — فيظهر للمراجع باسمه ومصدره، ولا تُضخَّم كميات التشغيل.
 *
 * مصدر الحد: `contracts.hours_monthly_target` (القائم الحي — رأس العقد).
 * الأحكام: عجز صفر = لا بند (درس M-01: صفر رصيد لا بند) · مستخلص معتمد = 423 ·
 * البنود المتنازَع عليها المقرَّة (upheld) لا تُحسب منفَّذًا · عاطل بمفتاح
 * (claim × guaranteed_min × claim_id).
 */

namespace App\Services\Revenue;

class GuaranteedMinimumService
{
    const LINE_KIND = 'guaranteed_min';

    /**
     * @return array{ok:bool,code:int,reason:string,line_id:int,shortfall:float,amount:float}
     */
    public static function appendLine(\mysqli $conn, $gate, $companyId, $claimId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'line_id' => 0, 'shortfall' => 0.0, 'amount' => 0.0);
        $claimId = (int) $claimId;

        $claims = $gate->scopedQuery(array('scope' => array('c' => 'claims')),
            "SELECT c.* FROM claims c WHERE {TENANT_SCOPE} AND c.id = ? AND COALESCE(c.is_deleted,0)=0",
            array($claimId));
        if (!$claims) { $out['code'] = 404; $out['reason'] = 'المستخلص غير موجود في نطاقك'; return $out; }
        $claim = $claims[0];
        if (!in_array((string) $claim['state'], array('draft', 'review'), true)) {
            $out['code'] = 423;
            $out['reason'] = 'المستخلص «' . $claim['state'] . '» — لا يعدل بعد الاعتماد؛ التصحيح بإشعار';
            return $out;
        }

        // الحد المضمون من رأس العقد (الشهري × عدد أشهر الفترة)
        $ct = $gate->scopedQuery(array('scope' => array('k' => 'contracts')),
            "SELECT k.hours_monthly_target FROM contracts k WHERE {TENANT_SCOPE} AND k.id = ?",
            array((int) $claim['contract_id']));
        $monthly = $ct ? (float) $ct[0]['hours_monthly_target'] : 0.0;
        if ($monthly <= 0) {
            $out['ok'] = true; $out['code'] = 200;
            $out['reason'] = 'لا حد أدنى مضمونا في العقد — لا بند (الغياب ليس صفر عجز مفترضا)';
            return $out;
        }
        $months = max(1, (int) round(
            ((strtotime((string) $claim['period_to']) - strtotime((string) $claim['period_from'])) / 86400 + 1) / 30
        ));
        $guaranteed = $monthly * $months;

        // المنفَّذ المفوتر بالساعة — المتنازَع المقرُّ (upheld) ساقط
        $sum = $gate->scopedQuery(array('scope' => array('l' => 'claim_lines')),
            "SELECT COALESCE(SUM(l.qty),0) q,
                    COALESCE(MAX(l.unit_price),0) price,
                    COALESCE(MAX(l.contract_line_id),0) cline
               FROM claim_lines l
              WHERE {TENANT_SCOPE} AND l.claim_id = ? AND l.unit_type = 'hour'
                AND l.source_kind <> 'guaranteed_min' AND l.dispute_flag = 0",
            array($claimId));
        $billed = (float) $sum[0]['q'];
        $price = (float) $sum[0]['price'];
        $shortfall = round(max(0, $guaranteed - $billed), 2);
        $out['shortfall'] = $shortfall;

        if ($shortfall <= 0.005) {
            $out['ok'] = true; $out['code'] = 200;
            $out['reason'] = 'المنفذ (' . $billed . ') بلغ المضمون (' . $guaranteed . ') — لا بند (لا بند بصفر)';
            return $out;
        }
        if ($price <= 0) {
            // سعر بند البيع النافذ إن لم يوجد سطر ساعة في المستخلص
            $ln = $gate->scopedQuery(array('scope' => array('b' => 'client_contract_lines')),
                "SELECT b.unit_price FROM client_contract_lines b
                  WHERE {TENANT_SCOPE} AND b.contract_id = ? AND b.pricing_model = 'hour' AND b.state = 'active'
                  ORDER BY b.id LIMIT 1", array((int) $claim['contract_id']));
            $price = $ln ? (float) $ln[0]['unit_price'] : 0.0;
        }
        if ($price <= 0) {
            $out['code'] = 422; $out['reason'] = 'لا سعر ساعة معتمدا للعقد — لا يخترع رقم (قاعدة عدم التلفيق)';
            return $out;
        }

        $amount = round($shortfall * $price, 2);
        try {
            $lineId = (int) $gate->insert('claim_lines', array(
                'claim_id' => $claimId,
                'source_kind' => self::LINE_KIND,
                'source_ref' => $claimId, // UQ(claim, kind, ref) → بند واحد لكل مستخلص — عاطل
                'unit_type' => 'hour',
                'qty' => $shortfall,
                'unit_price' => $price,
                'amount' => $amount,
                'work_date' => (string) $claim['period_to'],
            ));
        } catch (\Throwable $t) {
            if (strpos($t->getMessage(), 'Duplicate') !== false || strpos($t->getMessage(), 'uq_claim_line_src') !== false) {
                $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'بند الحد المضمون قائم — فعل عاطل';
                return $out;
            }
            throw $t;
        }
        // الإجماليات تتبع البند — بندٌ ظاهر لا تضخيمُ كمية تشغيل
        $gate->update('claims', array(
            'gross_amount' => round((float) $claim['gross_amount'] + $amount, 2),
            'net_amount' => round((float) $claim['net_amount'] + $amount, 2),
        ), array('id' => $claimId));

        $out['ok'] = true; $out['code'] = 201; $out['line_id'] = $lineId; $out['amount'] = $amount;
        $out['reason'] = 'بند «الحد الأدنى المضمون» مستقلا: عجز ' . $shortfall . ' ساعة × ' . $price
            . ' = ' . $amount . ' — باسمه لا مدسوسا في الكميات';
        return $out;
    }
}
