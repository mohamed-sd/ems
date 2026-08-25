<?php
/**
 * PermissionChangeWorkflow — دورة تغيير الصلاحيات (SEC-01 §8 · §12 خدمة ⑦ · SEC-24)
 * ───────────────────────────────────────────────────────────────────────────
 * «يحسب risk_level من نوع التغيير فيفتح خطوات الموافقة المطلوبة بترتيبها —
 * ولا يُطبَّق تغيير قبل اكتمال الإلزامي منها» · والمصفوفة الرباعية لمدير
 * الإدارة لا تُختصر (S4/S19) · وfunctional_owner يُحل من ORG-01 بحسب المجال.
 * Events: ChangeRequested · ChangeApproved · ChangeApplied (أسطر التدقيق).
 */

namespace App\Services\Security;

require_once __DIR__ . '/PositionService.php';
require_once __DIR__ . '/SelfGrantGuard.php';

class PermissionChangeWorkflow
{
    /** فتح طلب تغيير بخطواته — ChangeRequested. */
    public static function open(\mysqli $conn, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'req_id' => 0);
        foreach (array('company_id', 'person_id', 'change_kind', 'reason', 'created_by') as $k) {
            if (empty($d[$k])) { $out['code'] = 422; $out['reason'] = "الحقل {$k} إلزامي"; return $out; }
        }
        $kind = (string) $d['change_kind'];
        $risk = $kind === 'dept_mgr_or_high' ? 'high' : ($kind === 'within_role' ? 'low' : 'medium');
        $co = intval($d['company_id']);
        $pid = intval($d['person_id']);
        $by = intval($d['created_by']);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO permission_change_requests (company_id, person_id, change_kind, from_json, to_json, reason, doc_ref, risk_level, state, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
            $from = isset($d['from_json']) ? json_encode($d['from_json'], JSON_UNESCAPED_UNICODE) : null;
            $to = isset($d['to_json']) ? json_encode($d['to_json'], JSON_UNESCAPED_UNICODE) : null;
            $doc = isset($d['doc_ref']) ? (string) $d['doc_ref'] : null;
            $stmt->bind_param('iissssssi', $co, $pid, $kind, $from, $to, $d['reason'], $doc, $risk, $by);
            $stmt->execute();
            $reqId = intval($conn->insert_id);
            $stmt->close();

            $seq = 0;
            foreach (PositionService::stepsForKind($kind) as $rule) {
                $seq++;
                $stmt = $conn->prepare('INSERT INTO permission_approval_steps (req_id, seq_no, approver_rule, mandatory) VALUES (?, ?, ?, 1)');
                $stmt->bind_param('iis', $reqId, $seq, $rule);
                $stmt->execute();
                $stmt->close();
            }
            PositionService::audit($conn, $co, $pid, 'granted', 'change_request:' . $reqId, $to,
                'ChangeRequested — ' . $kind . ' (' . $risk . ')', $by);
            $conn->commit();
            $out['ok'] = true; $out['code'] = 201; $out['req_id'] = $reqId;
            $out['reason'] = 'فتح الطلب بخطواته (' . $seq . ') بدرجة المخاطرة — ' . $risk;
            return $out;
        } catch (\Throwable $e) {
            $conn->rollback();
            $out['code'] = 500; $out['reason'] = $e->getMessage();
            return $out;
        }
    }

    /**
     * قرار خطوة — التسلسل محروس ولا يعتمد المرء طلبَه.
     */
    public static function decide(\mysqli $conn, $reqId, $approverPersonId, $decision = 'approve', $reason = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'state' => null, 'remaining' => null);
        $reqId = intval($reqId);
        $approverPersonId = intval($approverPersonId);
        $req = $conn->query("SELECT * FROM permission_change_requests WHERE req_id = {$reqId}")->fetch_assoc();
        if (!$req) { $out['code'] = 404; $out['reason'] = 'طلب غير موجود'; return $out; }
        if ($req['state'] !== 'pending') { $out['code'] = 409; $out['reason'] = 'الطلب ليس معلقا'; return $out; }

        // لا يعتمد المرء طلبه ولا يمنح نفسه
        $sg = SelfGrantGuard::checkApproval($conn, $approverPersonId, intval($req['created_by']), intval($req['company_id']), 'pcr:' . $reqId);
        if (!$sg['ok']) { return array_merge($out, array('code' => 403, 'reason' => $sg['reason'])); }
        if ($approverPersonId === intval($req['person_id'])) {
            $sg = SelfGrantGuard::checkGrant($conn, $approverPersonId, $approverPersonId, intval($req['company_id']), 'pcr:' . $reqId);
            return array_merge($out, array('code' => 403, 'reason' => 'المستفيد لا يعتمد ترفيعه — منح ذاتي'));
        }

        // الخطوة المفتوحة التالية
        $res = $conn->query("SELECT * FROM permission_approval_steps WHERE req_id = {$reqId} ORDER BY seq_no");
        $steps = $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
        $next = null;
        foreach ($steps as $s) {
            if ($s['decision'] === null) { $next = $s; break; }
            if ($s['decision'] === 'reject') { $out['code'] = 409; $out['reason'] = 'مرفوض سلفا'; return $out; }
        }
        if ($next === null) { $out['code'] = 409; $out['reason'] = 'الموافقات مكتملة'; return $out; }

        $dec = $decision === 'reject' ? 'reject' : 'approve';
        $sid = intval($next['st_id']);
        $stmt = $conn->prepare(
            "UPDATE permission_approval_steps SET decision = ?, approver_person_id = ?, reason = ?, at = NOW()
             WHERE st_id = ?");
        $stmt->bind_param('sisi', $dec, $approverPersonId, $reason, $sid);
        $stmt->execute();
        $stmt->close();

        if ($dec === 'reject') {
            $conn->query("UPDATE permission_change_requests SET state = 'rejected' WHERE req_id = {$reqId}");
            $out['ok'] = true; $out['code'] = 200; $out['state'] = 'rejected';
            $out['reason'] = 'رفض في الخطوة ' . $next['seq_no'];
            return $out;
        }

        $res = $conn->query("SELECT COUNT(*) c FROM permission_approval_steps
                              WHERE req_id = {$reqId} AND mandatory = 1 AND (decision IS NULL OR decision <> 'approve')");
        $remaining = intval($res->fetch_assoc()['c']);
        $out['remaining'] = $remaining;
        if ($remaining === 0) {
            $conn->query("UPDATE permission_change_requests SET state = 'approved' WHERE req_id = {$reqId}");
            PositionService::audit($conn, intval($req['company_id']), intval($req['person_id']),
                'granted', 'change_request:' . $reqId, $req['to_json'], 'ChangeApproved — اكتملت الإلزامية', $approverPersonId);
            $out['state'] = 'approved';
            $out['reason'] = 'اكتملت الموافقات الإلزامية — جاهز للتطبيق';
        } else {
            $out['state'] = 'pending';
            $out['reason'] = 'سجلت — بقي ' . $remaining . ' إلزامية (المصفوفة لا تختصر)';
        }
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** التطبيق — لا يُطبَّق قبل اكتمال الإلزامي (ChangeApplied). */
    public static function apply(\mysqli $conn, $reqId, $executedBy)
    {
        $reqId = intval($reqId);
        $req = $conn->query("SELECT * FROM permission_change_requests WHERE req_id = {$reqId}")->fetch_assoc();
        if (!$req) { return array('ok' => false, 'code' => 404, 'reason' => 'غير موجود'); }
        if ($req['state'] !== 'approved') {
            return array('ok' => false, 'code' => 409,
                'reason' => 'لا يطبق تغيير قبل اكتمال الموافقات الإلزامية — الحالة: ' . $req['state']);
        }
        $conn->query("UPDATE permission_change_requests SET state = 'applied' WHERE req_id = {$reqId}");
        PositionService::audit($conn, intval($req['company_id']), intval($req['person_id']),
            'elevated', 'change_request:' . $reqId, $req['to_json'], 'ChangeApplied', intval($executedBy));
        require_once __DIR__ . '/PermissionResolver.php';
        PermissionResolver::rebuild($conn, intval($req['person_id']), intval($req['company_id']));
        return array('ok' => true, 'code' => 200, 'reason' => 'طبق وأعيد بناء المشتق');
    }
}
