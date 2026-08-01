<?php
/**
 * محلّل السياسة النافذة — PolicyResolver (POL-01 §12-①)
 * ───────────────────────────────────────────────────────────────────────────
 * Inputs: الإدارة والنطاق والتاريخ · Output: السياسة النافذة بقواعدها
 * ومصفوفتها وخصوماتها وسلسلتها · **بلا سياسة مطابقة → 422 ولا افتراض صامت** ·
 * الأولوية محسومة: العقد يغلب المشروع، والمشروع يغلب الإدارة.
 */

namespace App\Services\Policy;

class PolicyResolver
{
    /**
     * @return array{ok:bool,code:int,reason:string,policy:?array,rules:array,matrix:array,deductions:array,chain:array}
     */
    public static function resolve(\mysqli $conn, $companyId, $domain, array $scope = array(), $onDate = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'policy' => null,
                     'rules' => array(), 'matrix' => array(), 'deductions' => array(), 'chain' => array());
        $companyId = intval($companyId);
        $domain = (string) $domain;
        $onDate = ($onDate !== null) ? (string) $onDate : date('Y-m-d');

        // الأولوية المحسومة: العقد ← المشروع ← الإدارة
        $probes = array();
        if (!empty($scope['contract_id'])) { $probes[] = array('contract', intval($scope['contract_id'])); }
        if (!empty($scope['project_id'])) { $probes[] = array('project', intval($scope['project_id'])); }
        $probes[] = array('department', 0);

        $policy = null;
        foreach ($probes as $p) {
            $stmt = $conn->prepare(
                "SELECT * FROM dept_policies
                  WHERE company_id = ? AND domain = ? AND scope_type = ? AND scope_id = ?
                    AND state = 'active' AND valid_from <= ? AND (valid_to IS NULL OR valid_to >= ?)
                  ORDER BY version DESC LIMIT 1");
            $stmt->bind_param('ississ', $companyId, $domain, $p[0], $p[1], $onDate, $onDate);
            $stmt->execute();
            $policy = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($policy) { break; }
        }
        if (!$policy) {
            $out['code'] = 422;
            $out['reason'] = 'لا سياسةَ نافذةً مطابقةً لإدارة «' . $domain . '» في ' . $onDate . ' — **ولا يُفترض شيء**';
            return $out;
        }
        $pid = intval($policy['policy_id']);
        $out['policy'] = $policy;
        $out['rules'] = $conn->query("SELECT * FROM policy_rules WHERE policy_id = {$pid}")->fetch_all(MYSQLI_ASSOC);
        $out['matrix'] = $conn->query("SELECT * FROM impact_matrix WHERE policy_id = {$pid}")->fetch_all(MYSQLI_ASSOC);
        $out['deductions'] = $conn->query("SELECT * FROM deduction_types WHERE policy_id = {$pid}")->fetch_all(MYSQLI_ASSOC);
        $out['chain'] = $conn->query("SELECT * FROM approval_chains WHERE policy_id = {$pid} ORDER BY seq_no")->fetch_all(MYSQLI_ASSOC);
        $out['ok'] = true; $out['code'] = 200;
        $out['reason'] = 'السياسة #' . $pid . ' (' . $policy['name_ar'] . ') نافذة بنطاق ' . $policy['scope_type'];
        return $out;
    }

    /**
     * أثر حالةٍ على طرفٍ من مصفوفة السياسة — **حالة خارج المصفوفة تُرفض ولا
     * يُستنتج أثرها** (P9).
     */
    public static function impactOf(\mysqli $conn, $policyId, $stateCode, $partyType)
    {
        $stmt = $conn->prepare('SELECT effect FROM impact_matrix WHERE policy_id = ? AND state_code = ? AND party_type = ? LIMIT 1');
        $policyId = intval($policyId); $stateCode = (string) $stateCode; $partyType = (string) $partyType;
        $stmt->bind_param('iss', $policyId, $stateCode, $partyType);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return array('ok' => false, 'code' => 422,
                'reason' => 'الحالة «' . $stateCode . '» ليست في مصفوفة أثر السياسة للطرف «' . $partyType . '» — تُرفض ولا يُستنتج أثرها');
        }
        return array('ok' => true, 'code' => 200, 'effect' => (string) $row['effect']);
    }
}
