<?php
/**
 * app/Services/Capacity/SigmaGuard.php — قيدُ Σ الواعي بحالة الخطة (CAP-17)
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §5-②: «لا تجاوزَ للمتاح — والقيدُ يختلف بحالة خطة التغطية:
 *   · المسودةُ والتغطيةُ الجزئية: Σ الأساسي ≤ المتعاقد — تُحفظ الناقصةُ
 *     وتظهر فجوتُها، ولا يُمنع تسجيلُ الفجوة التي بُني النظامُ لقياسها (C15).
 *   · عند اعتماد الخطة: Σ = المتعاقد بالضبط، أو اعتمادٌ بفجوةٍ ظاهرةٍ بقرار
 *     استثناءٍ موقَّع (C16).
 *   · ولا تجاوزَ فوق المتعاقد في أي حالة — 409 بقيمة الفارق (C1)؛ والزيادةُ
 *     المشروعةُ ملحقٌ يرفع الالتزامَ أو احتياطيٌّ لا يُحتسب أو درجةُ ④.»
 *
 * Σ الأساسي = مجموعُ primary_units_committed لبنود الموردين الحيّة المرتبطة
 * بالالتزام — والاحتياطيُّ خارج Σ بالبناء (لا يملك عدًّا في الأساسي أصلًا).
 */

namespace App\Services\Capacity;

class SigmaGuard
{
    const STATES = array('draft', 'partial', 'submitted', 'approved');

    /**
     * فحصُ Σ لالتزامٍ بحالته (أو بحالةٍ مستهدفةٍ عند الانتقال).
     * @return array{ok:bool,code:int,reason:string,sigma:int,target:int,gap:int,state:string}
     */
    public static function check($gate, $oblId, $targetState = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'sigma' => 0, 'target' => 0, 'gap' => 0, 'state' => '');

        $obls = $gate->scopedQuery(array('scope' => array('c' => 'contract_commitments')),
            "SELECT c.id, c.primary_units_contracted, c.plan_state, c.sigma_exception_ref
               FROM contract_commitments c WHERE {TENANT_SCOPE} AND c.id = ? AND c.is_deleted = 0",
            array((int) $oblId));
        if (!$obls) { $out['code'] = 404; $out['reason'] = 'الالتزام غير موجود في نطاقك'; return $out; }
        $obl = $obls[0];
        if ($obl['primary_units_contracted'] === null) {
            $out['code'] = 422;
            $out['reason'] = 'الالتزام بلا عدد متعاقد للأساسية — لا Σ بلا مستهدف';
            return $out;
        }
        $target = (int) $obl['primary_units_contracted'];
        $state = $targetState !== null ? (string) $targetState : (string) $obl['plan_state'];
        if (!in_array($state, self::STATES, true)) {
            $out['code'] = 422; $out['reason'] = 'حالة خطة خارج الأربع'; return $out;
        }

        $rows = $gate->scopedQuery(array('scope' => array('l' => 'supplier_contract_lines')),
            "SELECT COALESCE(SUM(l.primary_units_committed), 0) AS sigma
               FROM supplier_contract_lines l
              WHERE {TENANT_SCOPE} AND l.contract_obligation_ref = ?
                AND l.is_deleted = 0 AND l.state = 'active'",
            array((int) $oblId));
        $sigma = $rows ? (int) $rows[0]['sigma'] : 0;
        $out['sigma'] = $sigma; $out['target'] = $target;
        $out['gap'] = $target - $sigma; $out['state'] = $state;

        // C1: التجاوزُ فوق المتعاقد ممنوعٌ في كل حالة — 409 بقيمة الفارق
        if ($sigma > $target) {
            $out['code'] = 409;
            $out['reason'] = 'Σ الأساسي (' . $sigma . ') يتجاوز المتعاقد (' . $target . ') بفارق '
                           . ($sigma - $target) . ' — لا تجاوز إلا بملحق يرفع الالتزام أو احتياطي لا يحتسب (C1)';
            return $out;
        }
        // C16: الاعتمادُ يوجب المساواةَ أو استثناءً موقَّعًا بفجوةٍ ظاهرة
        if (($state === 'submitted' || $state === 'approved') && $sigma < $target) {
            $exc = trim((string) $obl['sigma_exception_ref']);
            if ($exc === '') {
                $out['code'] = 409;
                $out['reason'] = 'Σ الأساسي (' . $sigma . ') دون المتعاقد (' . $target . ') بفجوة '
                               . ($target - $sigma) . ' — الاعتماد يرفض إلا بقرار استثناء موقع (C16)';
                return $out;
            }
            $out['ok'] = true; $out['code'] = 200;
            $out['reason'] = 'اعتماد بفجوة ظاهرة (' . ($target - $sigma) . ') بقرار استثناء: ' . $exc;
            return $out;
        }
        // C15: المسودةُ والجزئيةُ تُحفظ وتظهر فجوتُها
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = $sigma === $target
            ? 'Σ الأساسي = المتعاقد (' . $target . ') بالضبط'
            : 'خطة ناقصة محفوظة — الفجوة ' . ($target - $sigma) . ' ظاهرة لا ممنوعة (C15)';
        return $out;
    }

    /**
     * انتقالُ حالة الخطة — يفحص Σ بالحالة المستهدفة ثم يكتبها.
     * @return array{ok:bool,code:int,reason:string,sigma:int,target:int,gap:int,state:string}
     */
    public static function transition($gate, $oblId, $newState, $exceptionRef = null)
    {
        if ($exceptionRef !== null && trim((string) $exceptionRef) !== '') {
            // مرجعُ الاستثناء يُثبَّت قبل الفحص — فالفحصُ يقرؤه
            $gate->update('contract_commitments',
                array('sigma_exception_ref' => mb_substr(trim((string) $exceptionRef), 0, 120)),
                array('id' => (int) $oblId));
        }
        $r = self::check($gate, $oblId, $newState);
        if (!$r['ok']) { return $r; }
        $gate->update('contract_commitments', array('plan_state' => (string) $newState),
            array('id' => (int) $oblId));
        $r['reason'] = 'انتقلت الخطة إلى «' . $newState . '» — ' . $r['reason'];
        return $r;
    }
}
