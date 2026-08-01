<?php
/**
 * BreakGlassService — صلاحية الطوارئ (SEC-01 §1.1④ · §8⑦ · §12 خدمة ⑩ · SEC-22)
 * ───────────────────────────────────────────────────────────────────────────
 * «يمنح صلاحية طوارئ بمدة لا تتجاوز 24 ساعة · يقرأ guard_override_policies
 * فلا يتجاوز never · يسجّل كل فعل · ويفتح بلاغ حوكمة آليًّا · وبلا مراجعة
 * خلال 48 ساعة تسقط ويُصعَّد».
 */

namespace App\Services\Security;

require_once __DIR__ . '/PermissionResolver.php';
require_once __DIR__ . '/PositionService.php';
require_once __DIR__ . '/SelfGrantGuard.php';

class BreakGlassService
{
    /**
     * منح كسر زجاج.
     * @return array{ok:bool, code:int, reason:string, ex_id:int, ticket_id:int}
     */
    public static function grant(\mysqli $conn, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'ex_id' => 0, 'ticket_id' => 0);
        foreach (array('company_id', 'person_id', 'permission_code', 'scope_rule', 'reason', 'granted_by') as $k) {
            if (empty($d[$k])) { $out['code'] = 422; $out['reason'] = "الحقل {$k} إلزامي — كسر الزجاج موثَّق بالكامل"; return $out; }
        }
        $co = intval($d['company_id']);
        $pid = intval($d['person_id']);
        $by = intval($d['granted_by']);

        // لا يمنح أحد نفسه — ولا بكسر الزجاج (حارس never ⑥)
        $sg = SelfGrantGuard::checkGrant($conn, $by, $pid, $co, 'break_glass:' . $d['permission_code']);
        if (!$sg['ok']) { return array_merge($out, array('code' => 403, 'reason' => $sg['reason'])); }

        // كسر الزجاج لا يتجاوز حارسًا never — القائمة تُقرأ ولا تُستثنى (§7.2)
        $guard = self::guardFor($conn, (string) $d['permission_code']);
        if ($guard && $guard['overridable'] === 'never') {
            $stmt = $conn->prepare(
                "INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code)
                 VALUES (?, ?, ?, ?, 'break_glass_never')");
            $ref = 'bg:' . $d['permission_code'];
            $stmt->bind_param('isis', $co, $guard['guard_code'], $by, $ref);
            $stmt->execute();
            $stmt->close();
            $out['code'] = 403;
            $out['reason'] = 'كسر الزجاج لا يفتح «' . $guard['name_ar'] . '» — سياسته never تُقرأ ولا تُستثنى (S22)';
            return $out;
        }

        $hours = isset($d['hours']) ? min(24, max(1, intval($d['hours']))) : 24;
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO permission_exceptions (company_id, person_id, permission_code, scope_rule,
                    effect, reason, valid_from, valid_to, is_break_glass, approvals_ref, state)
                 VALUES (?, ?, ?, ?, 'grant', ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR), 1, ?, 'active')");
            $ref = isset($d['approvals_ref']) ? (string) $d['approvals_ref'] : ('executive:' . $by);
            $stmt->bind_param('iisssis', $co, $pid, $d['permission_code'], $d['scope_rule'], $d['reason'], $hours, $ref);
            $stmt->execute();
            $exId = intval($conn->insert_id);
            $stmt->close();

            // سطر التدقيق — break_glass
            $stmt = $conn->prepare(
                "INSERT INTO permission_audit_events (company_id, event_type, person_id, permission_code,
                    scope_rule, requested_by, approved_by, executed_by, reason, source)
                 VALUES (?, 'break_glass', ?, ?, ?, ?, ?, ?, ?, 'break_glass')");
            $stmt->bind_param('iissiiis', $co, $pid, $d['permission_code'], $d['scope_rule'], $pid, $by, $by, $d['reason']);
            $stmt->execute();
            $stmt->close();

            // بلاغ حوكمة آليًّا (§12 خدمة ⑩)
            $ticketId = self::openGovernanceTicket($conn, $co, $pid, $exId, (string) $d['permission_code'], (string) $d['reason']);
            $conn->commit();
            PermissionResolver::rebuild($conn, $pid, $co);
            $out['ok'] = true; $out['code'] = 201; $out['ex_id'] = $exId; $out['ticket_id'] = $ticketId;
            $out['reason'] = 'مُنح كسر زجاج #' . $exId . ' لمدة ' . $hours . ' ساعة — بمراجعة إلزامية خلال 48 ساعة وإلا سقط وصُعِّد';
            return $out;
        } catch (\Throwable $e) {
            $conn->rollback();
            $out['code'] = 500; $out['reason'] = 'استثناء: ' . $e->getMessage();
            return $out;
        }
    }

    /**
     * مسح 48 ساعة: كسر زجاج بلا مراجعة → يسقط آليًّا ويُصعَّد (S23).
     * المراجعة = approvals_ref يبدأ بـ'reviewed:'.
     * @return int عدد الساقط
     */
    public static function sweepUnreviewed(\mysqli $conn, $companyId = null)
    {
        $coF = $companyId !== null ? ' AND company_id = ' . intval($companyId) : '';
        $res = $conn->query(
            "SELECT ex_id, company_id, person_id, permission_code FROM permission_exceptions
              WHERE is_break_glass = 1 AND state = 'active'
                AND (approvals_ref IS NULL OR approvals_ref NOT LIKE 'reviewed:%')
                AND valid_from < DATE_SUB(NOW(), INTERVAL 48 HOUR){$coF}");
        $n = 0;
        while ($res && ($x = $res->fetch_assoc())) {
            $id = intval($x['ex_id']);
            $conn->query("UPDATE permission_exceptions SET state = 'revoked' WHERE ex_id = {$id}");
            PositionService::audit($conn, intval($x['company_id']), intval($x['person_id']),
                'revoked', $x['permission_code'], null,
                'كسر زجاج بلا مراجعة خلال 48 ساعة — سقط آليًّا وصُعِّد (S23)', 0);
            $stmt = $conn->prepare(
                "INSERT INTO fin_notifications (company_id, target_level, title, link)
                 VALUES (?, 'all', ?, 'admin/org_assignments.php')");
            $co = intval($x['company_id']);
            $title = 'تصعيد: كسر زجاج #' . $id . ' سقط بلا مراجعة خلال 48 ساعة';
            $stmt->bind_param('is', $co, $title);
            $stmt->execute();
            $stmt->close();
            PermissionResolver::rebuild($conn, intval($x['person_id']), intval($x['company_id']));
            $n++;
        }
        return $n;
    }

    /** مراجعة كسر الزجاج — تُثبت وتوقف عدّاد التصعيد. */
    public static function review(\mysqli $conn, $exId, $reviewerPersonId, $note = '')
    {
        $exId = intval($exId);
        $ref = 'reviewed:' . intval($reviewerPersonId) . ':' . mb_substr($note, 0, 80);
        $stmt = $conn->prepare("UPDATE permission_exceptions SET approvals_ref = ? WHERE ex_id = ? AND is_break_glass = 1");
        $stmt->bind_param('si', $ref, $exId);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return array('ok' => $ok, 'reason' => $ok ? 'رُوجع' : 'غير موجود');
    }

    /** أي حارس §7.2 تنتمي له الصلاحية؟ — بمطابقة بادئة الرمز. */
    private static function guardFor(\mysqli $conn, $permissionCode)
    {
        // خريطة بادئات الصلاحيات إلى رموز الحراس — تُقرأ السياسة من الجدول لا من الكود
        $map = array(
            'tenant.' => 'tenant.isolation', 'approve.self' => 'self.approval',
            'period.' => 'period.lock', 'record.delete' => 'record.delete.impactful',
            'audit.' => 'audit.tamper', 'self.grant' => 'self.grant',
            'legal.' => 'legal.explicit', 'medical.' => 'data.medical_bank',
            'bank.read' => 'data.medical_bank', 'payroll.' => 'data.payroll',
            'payment.execute' => 'payment.execute', 'journal.post' => 'journal.post',
            'coa.' => 'coa.modify', 'permission.grant' => 'permission.grant_others',
            'export.sensitive' => 'export.sensitive', 'ownership.transfer' => 'asset.ownership.change',
        );
        foreach ($map as $prefix => $guardCode) {
            if (strpos($permissionCode, $prefix) === 0) {
                $stmt = $conn->prepare('SELECT guard_code, name_ar, overridable FROM guard_override_policies WHERE guard_code = ? LIMIT 1');
                $stmt->bind_param('s', $guardCode);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                return $row;
            }
        }
        return null;
    }

    /** فتح بلاغ حوكمة — نوع «بلاغ حوكمة» يُبذر إن غاب (صف لا كود). */
    private static function openGovernanceTicket(\mysqli $conn, $co, $personId, $exId, $permission, $reason)
    {
        $t = $conn->query("SELECT id FROM ticket_types WHERE name = 'بلاغ حوكمة وصلاحيات' LIMIT 1");
        $type = $t ? $t->fetch_assoc() : null;
        if (!$type) {
            $conn->query("INSERT INTO ticket_types (name) VALUES ('بلاغ حوكمة وصلاحيات')");
            $typeId = intval($conn->insert_id);
        } else {
            $typeId = intval($type['id']);
        }
        if ($typeId <= 0) { return 0; }
        $no = date('y-m') . '-BG' . $exId;
        $complaint = 'كسر زجاج #' . $exId . ' — الصلاحية: ' . $permission . ' · السبب: ' . $reason
            . ' · مراجعة إلزامية خلال 48 ساعة';
        $stmt = $conn->prepare(
            "INSERT INTO tickets (company_id, ticket_no, ticket_type_id, stage, ticket_nature, priority,
                business_impact, call_date, reporting_person, complaint, owner_role_id, created_by)
             VALUES (?, ?, ?, 'new', 'incident', 'critical', 'admin', CURDATE(), 'النظام — كسر زجاج', ?, 15, ?)");
        $stmt->bind_param('isisi', $co, $no, $typeId, $complaint, $personId);
        $ok = $stmt->execute();
        $id = $ok ? intval($conn->insert_id) : 0;
        $stmt->close();
        return $id;
    }
}
