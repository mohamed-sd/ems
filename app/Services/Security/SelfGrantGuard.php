<?php
/**
 * SelfGrantGuard — حارس المنح الذاتي واعتماد الذات (SEC-01 §12 خدمة ④ · SEC-19)
 * ───────────────────────────────────────────────────────────────────────────
 * «يرفض منح الشخص نفسه أو اعتماد ما أنشأه 403 بنيويًّا — ولو كان مدير
 * الصلاحيات» (§8 الممنوعات الثلاثة · حارس never رقم ⑥) — وكل رفض مسجَّل.
 */

namespace App\Services\Security;

class SelfGrantGuard
{
    /**
     * فحص منح: المانح لا يمنح نفسه.
     * @return array{ok:bool, code:int, reason:string}
     */
    public static function checkGrant(\mysqli $conn, $granterPersonId, $granteePersonId, $companyId, $ref = '')
    {
        if (intval($granterPersonId) === intval($granteePersonId)) {
            self::record($conn, intval($companyId), intval($granterPersonId), $ref, 'self_grant');
            return array('ok' => false, 'code' => 403,
                'reason' => 'لا يمنح أحد نفسه صلاحية — ولو كان مدير الصلاحيات (403 بنيويًّا · حارس never)');
        }
        return array('ok' => true, 'code' => 200, 'reason' => '');
    }

    /**
     * فحص اعتماد: لا يعتمد المرء ما أنشأه.
     */
    public static function checkApproval(\mysqli $conn, $approverPersonId, $createdByPersonId, $companyId, $ref = '')
    {
        if (intval($approverPersonId) === intval($createdByPersonId)) {
            self::record($conn, intval($companyId), intval($approverPersonId), $ref, 'self_approval');
            return array('ok' => false, 'code' => 403,
                'reason' => 'لا يعتمد المرء ما أنشأه — 403 بنيويًّا (حارس never)');
        }
        return array('ok' => true, 'code' => 200, 'reason' => '');
    }

    private static function record(\mysqli $conn, $companyId, $personId, $ref, $reasonCode)
    {
        $stmt = $conn->prepare(
            "INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code)
             VALUES (?, 'self.grant', ?, ?, ?)");
        $stmt->bind_param('iiss', $companyId, $personId, $ref, $reasonCode);
        $stmt->execute();
        $stmt->close();
    }
}
