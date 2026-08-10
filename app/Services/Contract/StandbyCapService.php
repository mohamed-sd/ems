<?php
/**
 * app/Services/Contract/StandbyCapService.php — سقفُ الاحتياطي وحارسُ مقابلِه
 * ═══════════════════════════════════════════════════════════════════════════
 * CAP-01 §4-②ب: «عددُ الاحتياطيات المسجَّلة للمورد ≤ المسموحُ له في عقده،
 * ومجموعُها ≤ المسموحُ في عقد العميل — وإلا صار الاحتياطيُّ بابًا لإدخال
 * معداتٍ بلا حد».
 *
 * الأحكام المنفَّذة (CAP-04 · CAP-06):
 *   ① السقفان معًا: سقفُ عقد المورد (supplier_contract_lines.standby_units_allowed)
 *     وسقفُ عقد العميل (contract_commitments.standby_units_allowed) —
 *     تجاوزُ أيِّهما → 409 **بعرض السقفين** (C17).
 *   ② بلا سقفٍ معرَّفٍ في العقدين → 422 — fail-closed لا افتراض.
 *   ③ حارسُ DEC-CAP-A: احتسابُ مقابلِ احتياطيٍّ بلا قيمةٍ عقديةٍ صريحة →
 *     **422 مسجَّلة** — «بلا نصٍّ فهي التزامُ موردٍ لا بندُ إيراد» (§4-③).
 */

namespace App\Services\Contract;

require_once __DIR__ . '/../../../includes/catch_log.php';

use App\Services\ActivityLogService;

class StandbyCapService
{
    /**
     * فحصُ تسجيل احتياطيٍّ جديد — قبل الإدراج لا بعده.
     *
     * @param mixed $gate  بوابةُ العزل TenantDb
     * @param int   $companyId
     * @param int   $supplierContractLineId  بندُ عقد المورد الذي يُسجَّل عليه
     * @param int   $extra  كم احتياطيًّا يُراد تسجيله الآن (افتراضًا 1)
     * @return array{ok:bool,code:int,reason:string,supplier_cap:?int,client_cap:?int,supplier_count:int,total_count:int}
     */
    public static function checkRegistration($gate, $companyId, $supplierContractLineId, $extra = 1)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '',
                     'supplier_cap' => null, 'client_cap' => null,
                     'supplier_count' => 0, 'total_count' => 0);

        $lines = $gate->scopedQuery(array('scope' => array('l' => 'supplier_contract_lines')),
            "SELECT l.id, l.standby_units_allowed, l.contract_obligation_ref
               FROM supplier_contract_lines l
              WHERE {TENANT_SCOPE} AND l.id = ? AND l.is_deleted = 0",
            array((int) $supplierContractLineId));
        if (!$lines) { $out['code'] = 404; $out['reason'] = 'بندُ عقد المورد غيرُ موجودٍ في نطاقك'; return $out; }
        $line = $lines[0];

        // ② بلا سقفٍ معرَّف → 422 (fail-closed — لا يُفترض سقف)
        if ($line['standby_units_allowed'] === null) {
            $out['code'] = 422;
            $out['reason'] = 'لا سقفَ احتياطيٍّ معرَّفًا في عقد المورد (standby_units_allowed) — عرِّف السقفَ في العقد أولًا';
            return $out;
        }
        $supplierCap = (int) $line['standby_units_allowed'];
        $out['supplier_cap'] = $supplierCap;

        $oblRef = $line['contract_obligation_ref'] !== null ? (int) $line['contract_obligation_ref'] : 0;
        if ($oblRef <= 0) {
            $out['code'] = 422;
            $out['reason'] = 'البندُ بلا مرجعِ التزامٍ في عقد العميل (contract_obligation_ref) — لا حصةَ بلا التزام';
            return $out;
        }
        $obls = $gate->scopedQuery(array('scope' => array('c' => 'contract_commitments')),
            "SELECT c.id, c.standby_units_allowed FROM contract_commitments c
              WHERE {TENANT_SCOPE} AND c.id = ? AND c.is_deleted = 0", array($oblRef));
        if (!$obls) { $out['code'] = 404; $out['reason'] = 'التزامُ نوع المعدة غيرُ موجودٍ في نطاقك'; return $out; }
        if ($obls[0]['standby_units_allowed'] === null) {
            $out['code'] = 422;
            $out['reason'] = 'لا سقفَ احتياطيٍّ معرَّفًا في عقد العميل (standby_units_allowed) — عرِّف السقفَ في العقد أولًا';
            return $out;
        }
        $clientCap = (int) $obls[0]['standby_units_allowed'];
        $out['client_cap'] = $clientCap;

        // عدُّ المسجَّل — من خطة المعدات (seat_assignments) الحيّة بدور «احتياطي»
        $cnt = $gate->scopedQuery(array('scope' => array('sa' => 'seat_assignments')),
            "SELECT COUNT(*) n FROM seat_assignments sa
              WHERE {TENANT_SCOPE} AND sa.supplier_contract_line_id = ?
                AND sa.assignment_role = 'احتياطي' AND sa.state = 'active'",
            array((int) $supplierContractLineId));
        $out['supplier_count'] = $cnt ? (int) $cnt[0]['n'] : 0;

        $tot = $gate->scopedQuery(array(
                'scope' => array('sa' => 'seat_assignments', 'l2' => 'supplier_contract_lines')),
            "SELECT COUNT(*) n FROM seat_assignments sa
               JOIN supplier_contract_lines l2 ON l2.id = sa.supplier_contract_line_id AND l2.is_deleted = 0
              WHERE {TENANT_SCOPE} AND sa.assignment_role = 'احتياطي' AND sa.state = 'active'
                AND l2.contract_obligation_ref = ?",
            array($oblRef));
        $out['total_count'] = $tot ? (int) $tot[0]['n'] : 0;

        $extra = max(1, (int) $extra);
        if ($out['supplier_count'] + $extra > $supplierCap || $out['total_count'] + $extra > $clientCap) {
            $out['code'] = 409;
            $out['reason'] = 'تجاوزُ سقف الاحتياطي — سقفُ عقد المورد: ' . $supplierCap
                . ' (المسجَّلُ ' . $out['supplier_count'] . ') · سقفُ عقد العميل: ' . $clientCap
                . ' (المسجَّلُ ' . $out['total_count'] . ')';
            return $out;
        }

        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'ضمن السقفين — عقدُ المورد ' . ($out['supplier_count'] + $extra) . '/' . $supplierCap
            . ' · عقدُ العميل ' . ($out['total_count'] + $extra) . '/' . $clientCap;
        return $out;
    }

    /**
     * حارسُ DEC-CAP-A (CAP-06): مقابلُ الاحتياطي لا يُحتسب إلا بقيمةٍ عقديةٍ صريحة.
     *
     * يُستدعى قبل أي احتسابِ مقابلٍ لاحتياطيٍّ (فوترةً أو استحقاقًا):
     *   · عقدُ العميل: standby_compensation_type يجب أن يكون منصوصًا وغيرَ none.
     *   · عقدُ المورد: standby_payment_terms أو بندُ standby_basis/rate القائم.
     * وبلا نصٍّ → 422 **مسجَّلة** في سجل النشاط — «التزامُ موردٍ لا بندُ إيراد».
     *
     * @param string $side  'client' أو 'supplier'
     * @param array  $contractRow  صفُّ الالتزام أو البند كما قُرئ من قاعدة البيانات
     * @param array  $ctx  سياقُ التسجيل: record_id · screen
     * @return array{ok:bool,code:int,reason:string,compensation_type:?string}
     */
    public static function compensationBasisOrFail($side, array $contractRow, array $ctx = array())
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'compensation_type' => null);

        if ($side === 'client') {
            $type = isset($contractRow['standby_compensation_type']) ? $contractRow['standby_compensation_type'] : null;
            if ($type !== null && $type !== '' && $type !== 'none') {
                $out['ok'] = true; $out['code'] = 200; $out['compensation_type'] = (string) $type;
                $out['reason'] = 'مقابلُ الاحتياطي منصوصٌ في عقد العميل: ' . $type;
                return $out;
            }
        } else {
            $terms = isset($contractRow['standby_payment_terms']) ? trim((string) $contractRow['standby_payment_terms']) : '';
            $basis = isset($contractRow['standby_basis']) ? (string) $contractRow['standby_basis'] : 'none';
            $rate  = isset($contractRow['standby_rate']) ? $contractRow['standby_rate'] : null;
            if ($terms !== '' && $basis !== 'none' && $rate !== null && (float) $rate > 0) {
                $out['ok'] = true; $out['code'] = 200; $out['compensation_type'] = $basis;
                $out['reason'] = 'مقابلُ الاحتياطي منصوصٌ في عقد المورد: ' . $terms;
                return $out;
            }
        }

        // بلا نصٍّ عقديٍّ صريح → 422 مسجَّلة (DEC-CAP-A · §4-③)
        $out['code'] = 422;
        $out['reason'] = 'لا مقابلَ للاحتياطي بلا نصٍّ عقديٍّ صريح — بلا نصٍّ فهي التزامُ موردٍ لا بندُ إيراد (DEC-CAP-A)';
        try {
            ActivityLogService::log(array(
                'action_type' => 'blocked',
                'module_name' => 'capacity',
                'screen_name' => isset($ctx['screen']) ? (string) $ctx['screen'] : 'standby_compensation_guard',
                'record_id'   => isset($ctx['record_id']) ? (int) $ctx['record_id'] : null,
                'new_value'   => array('side' => $side, 'code' => 422, 'reason' => $out['reason']),
            ));
        } catch (\Throwable $t) { ems_catch_ignored($t, __METHOD__, 'التسجيلُ لا يُسقط الحكم'); /* التسجيلُ لا يُسقط الحكم */ }
        return $out;
    }
}
