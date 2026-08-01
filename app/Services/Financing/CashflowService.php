<?php
/**
 * التمويل والتدفق — CashflowService (N-18 · FIN-01 §6 · PLAN-04 §4.3)
 * ───────────────────────────────────────────────────────────────────────────
 * ① أقساط التمويل تدخل التدفق المتوقع **مقابل خطة التحصيل** (ENT-03/P-05) —
 *   فيُكشف عجز السيولة قبل وقوعه لا يوم الاستحقاق. **ولا تقرير تدفق ثانٍ**:
 *   هذه الخدمة تُطعم تقرير التدفق القائم بأسطر التمويل.
 * ② توزيع تكلفة التمويل على المشاريع **بنسبة ساعات تشغيل الأصول المموَّلة** —
 *   قراءةً من الساعات لا بتقدير؛ والمخرج سطور تحميل مقترحة تمر ببوابة
 *   الاستحقاق (POL-01 §6) — **وتُعرض للمشروع كلفةً مجرَّدة بلا كشف الممول**.
 */

namespace App\Services\Financing;

class CashflowService
{
    /**
     * نظرة 30/60/90: أقساط مستحقة مقابل تحصيل متوقع — والعجز بتاريخه ومقداره.
     * @return array{buckets:array,deficits:array}
     */
    public static function financingOutlook(\mysqli $conn, $companyId, $fromDate = null)
    {
        $companyId = intval($companyId);
        $from = ($fromDate !== null) ? (string) $fromDate : date('Y-m-d');
        $buckets = array();
        foreach (array(30, 60, 90) as $days) {
            $to = date('Y-m-d', strtotime($from . ' +' . $days . ' days'));
            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(i.amount_total),0) s, i.currency
                   FROM financing_installments i
                   JOIN financing_operations o ON o.op_id = i.op_id
                  WHERE o.company_id = ? AND i.state IN ('scheduled','due','overdue')
                    AND i.due_date BETWEEN ? AND ?
                  GROUP BY i.currency");
            $stmt->bind_param('iss', $companyId, $from, $to);
            $stmt->execute();
            $inst = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(p.remaining_amount),0) s, p.currency
                   FROM contract_payment_schedule p
                  WHERE p.company_id = ? AND COALESCE(p.is_deleted,0)=0 AND p.effective_to IS NULL
                    AND p.due_date BETWEEN ? AND ? AND p.state NOT IN ('completed')
                  GROUP BY p.currency");
            $stmt->bind_param('iss', $companyId, $from, $to);
            $stmt->execute();
            $coll = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // لا تُجمع عملتان في رقم — العجز لكل عملة على حدة
            $byCur = array();
            foreach ($inst as $r) { $byCur[(string) $r['currency']]['out'] = (float) $r['s']; }
            foreach ($coll as $r) { $byCur[(string) $r['currency']]['in'] = (float) $r['s']; }
            $rows = array();
            foreach ($byCur as $cur => $v) {
                $in = isset($v['in']) ? $v['in'] : 0.0;
                $outv = isset($v['out']) ? $v['out'] : 0.0;
                $rows[] = array('currency' => $cur, 'installments_due' => $outv,
                                'expected_collection' => $in, 'gap' => round($in - $outv, 2));
            }
            $buckets[$days] = $rows;
        }
        // العجز بتاريخه: أول قسط لا يغطيه تحصيل متراكم قبله (لكل عملة)
        $deficits = array();
        $stmt = $conn->prepare(
            "SELECT i.due_date, i.amount_total, i.currency, o.op_code, i.seq_no
               FROM financing_installments i JOIN financing_operations o ON o.op_id = i.op_id
              WHERE o.company_id = ? AND i.state IN ('scheduled','due','overdue') AND i.due_date >= ?
              ORDER BY i.due_date");
        $stmt->bind_param('is', $companyId, $from);
        $stmt->execute();
        $due = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($due as $d) {
            $cur = (string) $d['currency'];
            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(p.remaining_amount),0) s FROM contract_payment_schedule p
                  WHERE p.company_id = ? AND COALESCE(p.is_deleted,0)=0 AND p.effective_to IS NULL
                    AND p.currency = ? AND p.due_date <= ? AND p.state NOT IN ('completed')");
            $dd = (string) $d['due_date'];
            $stmt->bind_param('iss', $companyId, $cur, $dd);
            $stmt->execute();
            $cum = (float) $stmt->get_result()->fetch_assoc()['s'];
            $stmt->close();
            $stmt = $conn->prepare(
                "SELECT COALESCE(SUM(i.amount_total),0) s FROM financing_installments i
                   JOIN financing_operations o ON o.op_id = i.op_id
                  WHERE o.company_id = ? AND i.currency = ? AND i.state IN ('scheduled','due','overdue') AND i.due_date <= ?");
            $stmt->bind_param('iss', $companyId, $cur, $dd);
            $stmt->execute();
            $cumOut = (float) $stmt->get_result()->fetch_assoc()['s'];
            $stmt->close();
            if ($cum - $cumOut < -0.005) {
                $deficits[] = array('date' => $dd, 'currency' => $cur,
                    'shortfall' => round($cumOut - $cum, 2),
                    'trigger' => $d['op_code'] . ' قسط ' . $d['seq_no'],
                    'note' => 'عجز يُكشف قبل وقوعه لا يوم الاستحقاق');
            }
        }
        return array('buckets' => $buckets, 'deficits' => $deficits);
    }

    /**
     * FinancingCostAllocator — توزيع تكلفة تمويل فترةٍ على المشاريع بنسبة
     * ساعات تشغيل الأصول المموَّلة فيها (من دفتر استهلاك الحاويات — قراءة لا
     * تقدير). المخرج سطور تحميل **مقترحة** بمرجعها — كلفة مجرَّدة بلا ممول.
     * @param array $financingCost {amount, currency} تكلفة الفترة (أرباح الأقساط المستحقة)
     * @return array{ok:bool,lines:array,total_hours:float,reason:string}
     */
    public static function allocateFinancingCost(\mysqli $conn, $companyId, $period, array $financingCost)
    {
        $companyId = intval($companyId);
        $period = (string) $period;
        // ساعات تشغيل الأصول المموَّلة (لها حصة نافذة في الفترة) لكل مشروع
        $sql =
            "SELECT oc.project_id, cc.qty, oc.equipment_id
               FROM container_consumption cc
               JOIN op_containers oc ON oc.id = cc.container_id
              WHERE cc.company_id = ? AND cc.unit_type = 'hour'
                AND DATE_FORMAT(cc.consumed_on, '%Y-%m') = ?
                AND oc.equipment_id IS NOT NULL AND oc.project_id IS NOT NULL
                AND EXISTS (SELECT 1 FROM asset_ownership_shares s
                             WHERE s.company_id = cc.company_id AND s.asset_kind = 'equipment'
                               AND s.asset_id = oc.equipment_id
                               AND s.valid_from <= LAST_DAY(CONCAT(?, '-01'))
                               AND (s.valid_to IS NULL OR s.valid_to >= CONCAT(?, '-01'))
                               AND s.financier_entity_id NOT IN (SELECT t.entity_id FROM tenants t))";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('isss', $companyId, $period, $period, $period);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $byProject = array(); $total = 0.0;
        foreach ($rows as $r) {
            $p = intval($r['project_id']);
            $q = (float) $r['qty'];
            if ($q <= 0) { continue; }
            $byProject[$p] = (isset($byProject[$p]) ? $byProject[$p] : 0) + $q;
            $total += $q;
        }
        if ($total <= 0) {
            return array('ok' => true, 'lines' => array(), 'total_hours' => 0.0,
                'reason' => 'لا ساعات تشغيل لأصول مموَّلة في ' . $period . ' — لا تحميل (لا يُحمَّل مشروع كلفة أصول لم يستعملها)');
        }
        $amount = (float) $financingCost['amount'];
        $lines = array();
        foreach ($byProject as $p => $h) {
            $lines[] = array(
                'project_id' => $p,
                'hours' => round($h, 2),
                'share_pct' => round($h / $total * 100, 2),
                'amount' => round($amount * $h / $total, 2),
                'currency' => (string) $financingCost['currency'],
                'state' => 'Proposed',
                'note' => 'تكلفة تمويل ' . $period . ' بنسبة ساعات التشغيل — كلفة مجرَّدة بلا كشف الممول، وتمر ببوابة الاستحقاق',
            );
        }
        return array('ok' => true, 'lines' => $lines, 'total_hours' => round($total, 2),
            'reason' => count($lines) . ' مشروعًا بنسبة ساعاته من ' . $total);
    }
}
