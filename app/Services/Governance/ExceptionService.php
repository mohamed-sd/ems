<?php
/**
 * خدمة الاستثناء المحكوم — ExceptionService (GOV-01 §7 · §10-②③④)
 * ───────────────────────────────────────────────────────────────────────────
 * الدورة: طلب بمدته ونطاقه ومستنداته → درجة محسوبة (تُرفع لا تُخفض) → موافقات
 * بالتسلسل كلٌّ بتفويضه → نافذ بمدته → الانتهاء آليًّا والمنع يعود بلا تدخل.
 * Validation: absolute → 422 · legal_forbidden → 422 بإحالة · مدة مفتوحة → 422 ·
 * موافق = طالب → 403 · دور مكرر → 409.
 */

namespace App\Services\Governance;

require_once __DIR__ . '/../../Core/AuthorityGuard.php';

use App\Core\AuthorityGuard;

class ExceptionService
{
    /** الموافقون المطلوبون بحسب الدرجة (GOV-01 §4.1) */
    const REQUIRED_BY_RISK = array(
        'normal' => array('ops_manager', 'competent_authority'),
        'operational' => array('movement_manager', 'dept_manager', 'finance'),
        'financial' => array('dept_manager', 'finance', 'regulator'),
        'high' => array('movement_manager', 'dept_manager', 'finance', 'general_management'),
    );

    /** RiskCalculator — الدرجة تُشتق ويُقترح رفعها لا خفضها. */
    public static function riskOf(\mysqli $conn, $guardCode, $requestedLevel = null)
    {
        $stmt = $conn->prepare('SELECT guard_class, default_risk FROM guard_policies WHERE guard_code = ? LIMIT 1');
        $guardCode = (string) $guardCode;
        $stmt->bind_param('s', $guardCode);
        $stmt->execute();
        $g = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$g) { return null; }
        $order = array('normal' => 0, 'operational' => 1, 'financial' => 2, 'high' => 3, 'legal_forbidden' => 4);
        $base = (string) $g['default_risk'];
        if ($requestedLevel !== null && isset($order[$requestedLevel]) && $order[$requestedLevel] > $order[$base]) {
            $base = (string) $requestedLevel; // الرفع مسموح — والخفض بقرار حوكمة لا بطلب
        }
        return array('guard_class' => (string) $g['guard_class'], 'risk' => $base);
    }

    /**
     * إنشاء طلب استثناء.
     * @return array{ok:bool,code:int,reason:string,req_id:int,risk_level:string,required_approvals:array}
     */
    public static function create(\mysqli $conn, $companyId, array $a)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'req_id' => 0, 'risk_level' => '', 'required_approvals' => array());
        foreach (array('guard_code', 'requester_person_id', 'reason', 'scope_type', 'scope_id', 'valid_from', 'valid_to') as $f) {
            if (!isset($a[$f]) || $a[$f] === '' || $a[$f] === null) {
                $out['code'] = 422; $out['reason'] = 'حقل إلزامي مفقود: ' . $f . ' — ولا استثناء مفتوح المدة ولا عام';
                return $out;
            }
        }
        $r = self::riskOf($conn, $a['guard_code'], isset($a['risk_level']) ? $a['risk_level'] : null);
        if ($r === null) { $out['code'] = 422; $out['reason'] = 'حماية غير مصنَّفة — لا استثناء قبل التصنيف'; return $out; }
        if ($r['guard_class'] === 'absolute') {
            $out['code'] = 422; $out['reason'] = 'لا استثناءَ لهذه الحماية — صنفها منعٌ مطلق';
            return $out;
        }
        if ($r['risk'] === 'legal_forbidden') {
            $out['code'] = 422; $out['reason'] = 'محظور قانونًا — يُمنع ويُحال إلى المستشار القانوني، وسُجّل الطلب والرفض';
            $stmt = $conn->prepare('INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code) VALUES (?, ?, ?, ?, ?)');
            $companyId2 = intval($companyId); $gc = (string) $a['guard_code']; $p = intval($a['requester_person_id']);
            $ref = 'exception_request'; $rc = 'legal_forbidden_referred';
            $stmt->bind_param('isiss', $companyId2, $gc, $p, $ref, $rc);
            $stmt->execute(); $stmt->close();
            return $out;
        }
        $companyId = intval($companyId);
        $gc = (string) $a['guard_code']; $req = intval($a['requester_person_id']);
        $reason = (string) $a['reason']; $risk = $r['risk'];
        $st = (string) $a['scope_type']; $sid = (string) $a['scope_id'];
        $vf = (string) $a['valid_from']; $vt = (string) $a['valid_to'];
        $ot = !empty($a['one_time']) ? 1 : 0;
        $docs = isset($a['documents']) ? json_encode($a['documents'], JSON_UNESCAPED_UNICODE) : null;
        $imp = isset($a['expected_impact']) ? (string) $a['expected_impact'] : null;
        $stmt = $conn->prepare(
            'INSERT INTO exception_requests (company_id, guard_code, requester_person_id, reason, risk_level, scope_type, scope_id, valid_from, valid_to, one_time, documents_json, expected_impact)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isissssssiss', $companyId, $gc, $req, $reason, $risk, $st, $sid, $vf, $vt, $ot, $docs, $imp);
        if (!$stmt->execute()) { $out['code'] = 422; $out['reason'] = 'تعذّر الإنشاء: ' . $stmt->error; $stmt->close(); return $out; }
        $out['req_id'] = intval($stmt->insert_id);
        $stmt->close();
        $out['ok'] = true; $out['code'] = 201; $out['risk_level'] = $risk;
        $out['required_approvals'] = isset(self::REQUIRED_BY_RISK[$risk]) ? self::REQUIRED_BY_RISK[$risk] : self::REQUIRED_BY_RISK['high'];
        $out['reason'] = 'طلب #' . $out['req_id'] . ' بدرجة ' . $risk . ' — الموافقون: ' . implode(' ← ', $out['required_approvals']);
        return $out;
    }

    /**
     * موافقة بالتسلسل — approver ≠ requester (403) · دور مكرر (409) · اكتمالها يفعّل.
     * @return array{ok:bool,code:int,reason:string,state:string}
     */
    public static function approve(\mysqli $conn, $companyId, $reqId, $personId, $role, $decision = 'approve', $note = '')
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'state' => '');
        $reqId = intval($reqId); $personId = intval($personId); $role = (string) $role;
        $stmt = $conn->prepare('SELECT * FROM exception_requests WHERE req_id = ? LIMIT 1');
        $stmt->bind_param('i', $reqId);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$req) { $out['code'] = 404; $out['reason'] = 'الطلب غير موجود'; return $out; }
        if (!in_array((string) $req['state'], array('Pending'), true)) {
            $out['code'] = 409; $out['reason'] = 'الطلب ليس معلَّقًا — حاله ' . $req['state']; return $out;
        }
        if (intval($req['requester_person_id']) === $personId) {
            $out['code'] = 403; $out['reason'] = 'الطالب لا يوافق على طلبه — بنيويًّا'; return $out;
        }
        $stmt = $conn->prepare('SELECT approver_person_id, approver_role FROM exception_approvals WHERE req_id = ?');
        $stmt->bind_param('i', $reqId);
        $stmt->execute();
        $prior = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($prior as $p) {
            if ((string) $p['approver_role'] === $role) {
                $out['code'] = 409; $out['reason'] = 'دور مكرر في الموافقات — استقلال الموافقين شرط صحتها'; return $out;
            }
            if (intval($p['approver_person_id']) === $personId) {
                $out['code'] = 409; $out['reason'] = 'لا موافقتان من الشخص نفسه'; return $out;
            }
        }
        // التوقيع بمرجع التفويض (LEG-01 §6-③-ب)
        $sig = AuthorityGuard::sign($conn, array(
            'company_id' => intval($companyId), 'document_type' => 'exception_request', 'document_id' => $reqId,
            'step' => $role, 'person_id' => $personId,
            'created_by_person_id' => intval($req['requester_person_id']),
        ));
        if (!$sig['ok']) { return array_merge($out, array('code' => $sig['code'], 'reason' => $sig['reason'])); }

        $seq = count($prior) + 1;
        $stmt = $conn->prepare('INSERT INTO exception_approvals (req_id, approver_person_id, approver_role, auth_id, seq_no, decision, reason) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $authId = $sig['auth_id'];
        $stmt->bind_param('iisiiss', $reqId, $personId, $role, $authId, $seq, $decision, $note);
        $stmt->execute();
        $stmt->close();

        if ($decision === 'reject') {
            $conn->query("UPDATE exception_requests SET state = 'Rejected', closed_reason = 'رفض " . $conn->real_escape_string($role) . "' WHERE req_id = {$reqId}");
            $out['ok'] = true; $out['code'] = 200; $out['state'] = 'Rejected';
            $out['reason'] = 'رُفض الطلب — رفض أي موافق يغلقه بسببه';
            return $out;
        }
        $required = isset(self::REQUIRED_BY_RISK[(string) $req['risk_level']])
            ? self::REQUIRED_BY_RISK[(string) $req['risk_level']] : self::REQUIRED_BY_RISK['high'];
        $state = ($seq >= count($required)) ? 'Active' : 'Pending';
        if ($state === 'Active') {
            $conn->query("UPDATE exception_requests SET state = 'Active' WHERE req_id = {$reqId}");
        }
        $out['ok'] = true; $out['code'] = 200; $out['state'] = $state;
        $out['reason'] = ($state === 'Active')
            ? 'اكتملت الموافقات — الاستثناء نافذ بمدته ونطاقه'
            : 'موافقة ' . $seq . '/' . count($required) . ' — بانتظار البقية';
        return $out;
    }

    /** ExpiryJob — إنهاء المنقضي آليًّا: المنع يعود بلا تدخل بشري. */
    public static function expireSweep(\mysqli $conn)
    {
        $conn->query("UPDATE exception_requests SET state = 'Expired', closed_reason = 'انقضاء المدة آليًّا'
                       WHERE state = 'Active' AND valid_to < CURDATE()");
        return $conn->affected_rows;
    }
}
