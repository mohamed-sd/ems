<?php
/**
 * app/Services/Capacity/ObligationDistributor.php — التوزيعُ الواعي بالحالة (CAP-18)
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §16-①: «واعٍ بحالة الخطة لا قاعدةٌ واحدة: في Draft/Partial
 * Σ الأساسي ≤ المتعاقد فتُحفظ خطةٌ ناقصةٌ وتظهر فجوتُها · وفي Submitted/Approved
 * Σ = المتعاقد أو ExceptionApprovalRef إلزاميٌّ مع فجوةٍ ظاهرة ·
 * تجاوزُ المتعاقد في أي حالةٍ → 409 بقيمة الفارق · حصةٌ بلا التزامٍ → 422 ·
 * ولا إدخالَ يدويٍّ للكميات المشتقة».
 *
 * الكمياتُ المشتقة (§8.2): حصةُ المورد الشهرية = أساسيّاتُه × كميةِ الوحدة
 * الشهرية من الالتزام — تُحسب هنا **وتُرفض لو أُدخلت يدويًّا** (المصدرُ الواحد §5-⑦).
 */

namespace App\Services\Capacity;

require_once __DIR__ . '/SigmaGuard.php';
require_once __DIR__ . '/../Contract/SupplierContractService.php';

use App\Services\Contract\SupplierContractService as SCS;

class ObligationDistributor
{
    /**
     * حفظُ حصةِ موردٍ على التزامٍ — بفحص Σ قبل الكتابة وبكمياتٍ مشتقةٍ لا مدخلة.
     * @return array{ok:bool,code:int,reason:string,line_id:?int,sigma:?int,target:?int,gap:?int,derived:?array}
     */
    public static function saveShare($conn, $gate, $companyId, $supplierContractId, array $a, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'line_id' => null,
                     'sigma' => null, 'target' => null, 'gap' => null, 'derived' => null);

        // حصةٌ بلا التزامٍ → 422 — قبل أي حساب
        $oblId = isset($a['contract_obligation_ref']) ? (int) $a['contract_obligation_ref'] : 0;
        if ($oblId <= 0) {
            $out['code'] = 422; $out['reason'] = 'حصة بلا التزام — مرجع التزام نوع المعدة إلزامي (§2-①)';
            return $out;
        }
        $obls = $gate->scopedQuery(array('scope' => array('c' => 'contract_commitments')),
            "SELECT c.* FROM contract_commitments c WHERE {TENANT_SCOPE} AND c.id = ? AND c.is_deleted = 0",
            array($oblId));
        if (!$obls) { $out['code'] = 422; $out['reason'] = 'حصة بلا التزام حي — 422'; return $out; }
        $obl = $obls[0];

        // لا إدخالَ يدويٍّ للكميات المشتقة — من أدخلها أدخل مصدرَ حقيقةٍ ثانيًا
        foreach (array('supplier_share_hours_month', 'supplier_share_hours_total', 'hours_month', 'hours_total') as $k) {
            if (isset($a[$k]) && $a[$k] !== '' && $a[$k] !== null) {
                $out['code'] = 422;
                $out['reason'] = 'الكميات المشتقة لا تدخل يدويا (' . $k . ') — تحسب من الالتزام وخطته (§5-⑦)';
                return $out;
            }
        }

        $primary = isset($a['primary_units_committed']) ? (int) $a['primary_units_committed'] : 0;
        if ($primary <= 0) {
            $out['code'] = 422; $out['reason'] = 'عدد الأساسية الملتزم بها إلزامي موجب';
            return $out;
        }

        // Σ قبل الكتابة: الحاليُّ (بلا هذا البند إن كان تعديلًا) + الجديد
        $lineId = isset($a['line_id']) ? (int) $a['line_id'] : 0;
        $rows = $gate->scopedQuery(array('scope' => array('l' => 'supplier_contract_lines')),
            "SELECT COALESCE(SUM(l.primary_units_committed), 0) s FROM supplier_contract_lines l
              WHERE {TENANT_SCOPE} AND l.contract_obligation_ref = ? AND l.is_deleted = 0
                AND l.state = 'active' AND l.id <> ?",
            array($oblId, $lineId));
        $sigmaOthers = $rows ? (int) $rows[0]['s'] : 0;
        $sigma = $sigmaOthers + $primary;
        $target = $obl['primary_units_contracted'] !== null ? (int) $obl['primary_units_contracted'] : null;
        if ($target === null) {
            $out['code'] = 422; $out['reason'] = 'الالتزام بلا عدد متعاقد — لا توزيع بلا مستهدف';
            return $out;
        }
        $out['sigma'] = $sigma; $out['target'] = $target; $out['gap'] = $target - $sigma;
        $state = (string) $obl['plan_state'];
        if ($sigma > $target) {
            $out['code'] = 409;
            $out['reason'] = 'Σ الأساسي (' . $sigma . ') يتجاوز المتعاقد (' . $target . ') بفارق '
                           . ($sigma - $target) . ' — 409 (C1)';
            return $out;
        }
        if (($state === 'submitted' || $state === 'approved') && $sigma < $target
            && trim((string) $obl['sigma_exception_ref']) === '') {
            $out['code'] = 409;
            $out['reason'] = 'الخطة معتمدة وΣ (' . $sigma . ') دون المتعاقد (' . $target
                           . ') — أعدها مسودة أو أرفق قرار استثناء موقعا (C16)';
            return $out;
        }

        // الكمياتُ المشتقة — تُحسب ولا تُدخل
        $qtyMonth = $obl['qty_per_primary_unit_month'] !== null ? (float) $obl['qty_per_primary_unit_month'] : null;
        $derived = array(
            'share_qty_month' => $qtyMonth !== null ? round($primary * $qtyMonth, 2) : null,
            'measure_code'    => $obl['measure_code'],
        );
        $out['derived'] = $derived;

        $r = SCS::saveLine($conn, $gate, $companyId, $supplierContractId, array_merge($a, array(
            'contract_obligation_ref' => $oblId,
            'equipment_type_code'     => isset($a['equipment_type_code']) && $a['equipment_type_code'] !== ''
                                         ? $a['equipment_type_code'] : (string) $obl['equipment_type_code'],
        )), $actor);
        if (!$r['ok']) { return array_merge($out, array('code' => $r['code'], 'reason' => $r['reason'])); }
        $out['ok'] = true; $out['code'] = 200; $out['line_id'] = (int) $r['line_id'];
        $out['reason'] = 'حصة ' . $primary . ' أساسية حفظت — Σ ' . $sigma . '/' . $target
                       . ($out['gap'] > 0 ? ' والفجوة ' . $out['gap'] . ' ظاهرة (C15)' : ' بالضبط')
                       . ($derived['share_qty_month'] !== null
                          ? ' · الحصة الشهرية المشتقة ' . $derived['share_qty_month'] . ' ' . $derived['measure_code'] : '');
        return $out;
    }
}
