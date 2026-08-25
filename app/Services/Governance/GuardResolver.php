<?php
/**
 * فاصل قرار الحماية — GuardResolver (GOV-01 §10-①)
 * ───────────────────────────────────────────────────────────────────────────
 * يُستدعى من داخل الحارس نفسه: يفحص الصنف ثم يبحث عن استثناء نافذ يغطي
 * النطاق والوقت — **ويسجّل العبور أو المنع دائمًا**.
 *   • absolute: deny دائمًا — لا مسار استثناء مهما بلغت الموافقات.
 *   • exception_allowed: استثناء نافذ (Active · المدة · النطاق) → allow_by_exception
 *     ويُسجَّل الاستعمال؛ وإلا deny بسطر منع.
 *   • advisory: allow مع تسجيل القرار.
 */

namespace App\Services\Governance;

class GuardResolver
{
    /**
     * @return array{decision:string,req_id:?int,reason:string}
     *         decision: allow | deny | allow_by_exception
     */
    public static function resolve(\mysqli $conn, $companyId, $guardCode, $personId, array $ctx = array())
    {
        $companyId = intval($companyId); $personId = intval($personId);
        $guardCode = (string) $guardCode;
        $ref = isset($ctx['ref']) ? (string) $ctx['ref'] : null;

        $stmt = $conn->prepare('SELECT guard_class FROM guard_policies WHERE guard_code = ? LIMIT 1');
        $stmt->bind_param('s', $guardCode);
        $stmt->execute();
        $g = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$g) {
            // لا حماية بلا صنف معلن — والحارس غير المصنَّف يمنع (fail-closed)
            self::deny($conn, $companyId, $guardCode, $personId, $ref, 'unclassified');
            return array('decision' => 'deny', 'req_id' => null, 'reason' => 'حماية بلا صنف — لا تقلب ولا تستثنى قبل التصنيف');
        }
        $class = (string) $g['guard_class'];

        if ($class === 'advisory') {
            return array('decision' => 'allow', 'req_id' => null, 'reason' => 'تنبيه مسجل — القرار للمدير');
        }
        if ($class === 'absolute') {
            self::deny($conn, $companyId, $guardCode, $personId, $ref, 'absolute');
            return array('decision' => 'deny', 'req_id' => null, 'reason' => 'منع مطلق — لا مسار استثناء مهما بلغت الموافقات');
        }

        // exception_allowed: استثناء نافذ يغطي النطاق والوقت؟
        $scopeType = isset($ctx['scope_type']) ? (string) $ctx['scope_type'] : null;
        $scopeId = isset($ctx['scope_id']) ? (string) $ctx['scope_id'] : null;
        if ($scopeType !== null && $scopeId !== null) {
            $stmt = $conn->prepare(
                "SELECT req_id FROM exception_requests
                  WHERE company_id = ? AND guard_code = ? AND state = 'Active'
                    AND scope_type = ? AND scope_id = ?
                    AND valid_from <= CURDATE() AND valid_to >= CURDATE()
                  LIMIT 1");
            $stmt->bind_param('isss', $companyId, $guardCode, $scopeType, $scopeId);
            $stmt->execute();
            $ex = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($ex) {
                $reqId = intval($ex['req_id']);
                // كل عبور باستثناء يُسجَّل — Insert-only + عدّاد الاستعمال
                $stmt = $conn->prepare('INSERT INTO exception_usages (req_id, operation_ref, person_id) VALUES (?, ?, ?)');
                $opRef = $ref !== null ? $ref : 'n/a';
                $stmt->bind_param('isi', $reqId, $opRef, $personId);
                $stmt->execute();
                $stmt->close();
                $conn->query('UPDATE exception_requests SET usage_count = usage_count + 1 WHERE req_id = ' . $reqId);
                return array('decision' => 'allow_by_exception', 'req_id' => $reqId,
                             'reason' => 'استثناء نافذ #' . $reqId . ' — لهذا النطاق وحده وفي المدة وحدها');
            }
        }
        self::deny($conn, $companyId, $guardCode, $personId, $ref, 'no_exception');
        return array('decision' => 'deny', 'req_id' => null, 'reason' => 'ممنوع افتراضا — يرفع استثناء محكوم بموافقاته');
    }

    private static function deny(\mysqli $conn, $companyId, $guardCode, $personId, $ref, $reasonCode)
    {
        $stmt = $conn->prepare('INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('isiss', $companyId, $guardCode, $personId, $ref, $reasonCode);
        $stmt->execute();
        $stmt->close();
    }
}
