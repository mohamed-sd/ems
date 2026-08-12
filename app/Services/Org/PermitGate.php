<?php
/**
 * PermitGate — حارس الأذونات المشتركة (ORG-01 §5 · §7 خدمة ⑤ · ORG-11/12)
 * ───────────────────────────────────────────────────────────────────────────
 * «يُستدعى قبل كل دخول أو خروج أو تفعيل أو إيقاف: يفحص وجود إذن مكتمل
 *  الموافقات وساري المدة لهذا الموضوع وهذا الموقع — وبلا إذن مكتمل يُمنع
 *  الفعل 403 ويُسجَّل» (ORG-01 §7-⑤).
 *
 * الأحكام:
 *   • خطوة قبل سابقتها → 409 (لا تُفتح خطوة قبل اكتمال ما قبلها).
 *   • موافق بلا تفويض ساري لدوره → 403.
 *   • إذن منتهٍ يُستعمل → 423 ويُطلب تجديده.
 *   • الأحداث PermitApproved·PermitUsed·PermitExpired سطور permit_status_history.
 *   • المهل بساعة القاعدة (NOW() في MySQL) لا PHP.
 *
 * العلم EMS_PERMIT_GATE (off·monitor·enforce) + EMS_PERMIT_GATE_SITES
 * (قائمة مواقع للتجربة بموقع واحد — فارغة = كل المواقع).
 */

namespace App\Services\Org;

require_once __DIR__ . '/OrgAuthorityResolver.php';

class PermitGate
{
    /** المجال الوظيفي → أنواع التكليف الحاملة له (موقعيًّا فمركزيًّا). */
    private static $roleAssignmentTypes = array(
        /* ◆ **مجالُ الحركةِ كان يُسند إلى `site_manager` وحدَه** — فمن يحمل
             تكليفَ حركةِ الموقعِ صراحةً لا يستطيع أن يوافق عن مجالِه، ويقع
             القرارُ على مديرِ الموقعِ بالنيابةِ دائمًا.
             وقد سُجِّل `site_movement_mgr` نوعًا بقرارِ المالك (هجرة
             `2027_02_13`) — ونوعٌ مسجَّلٌ لا يحرسه أحدٌ **بناءٌ بلا تبنٍّ**
             (MD-05). فيُقدَّم على مديرِ الموقعِ كما تفعل سائرُ المجالات:
             الموقعيُّ أولًا ثم مَن يغطّيه.
             و`site_manager` يبقى في القائمةِ — فلا يُكسر ما يعمل. */
        'movement' => array('site_movement_mgr', 'site_manager'),
        'maintenance' => array('site_maintenance_officer', 'maintenance_mgr'),
        'operators' => array('site_operators_officer', 'operators_mgr'),
        'warehouse' => array('site_warehouse_keeper', 'warehouse_mgr'),
        'procurement' => array('site_procurement_receiver', 'procurement_ops_mgr'),
        'transport' => array('site_transport_coordinator', 'transport_mgr'),
    );

    /** المجالات الموازية (لا أنواع تكليف لها) → أدوار users.role. */
    private static $roleParallelRoles = array(
        'fleet' => array('3', '10'),          // إدارة الأسطول ومشرفه
        'hr' => array('4'),                   // الموارد البشرية
        'material_owner' => array('3', '2'),  // مالك المادة: الأسطول أو الموردون
    );

    /** إنشاء طلب إذن — draft ثم pending. */
    public static function request(\mysqli $conn, array $d)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'req_id' => 0);
        foreach (array('company_id', 'permit_type_code', 'subject_ref', 'site_id', 'requested_by') as $k) {
            if (!isset($d[$k]) || $d[$k] === '' || $d[$k] === null) {
                $out['code'] = 422; $out['reason'] = "الحقل {$k} إلزامي"; return $out;
            }
        }
        $stmt = $conn->prepare(
            "INSERT INTO permit_requests (company_id, permit_type_code, subject_ref, site_id,
                requested_by, reason, doc_ref, state)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $co = intval($d['company_id']); $ptc = (string) $d['permit_type_code'];
        $ref = (string) $d['subject_ref']; $site = intval($d['site_id']);
        $by = intval($d['requested_by']);
        $reason = isset($d['reason']) ? (string) $d['reason'] : null;
        $doc = isset($d['doc_ref']) ? (string) $d['doc_ref'] : null;
        $stmt->bind_param('issiiss', $co, $ptc, $ref, $site, $by, $reason, $doc);
        if (!$stmt->execute()) {
            $out['code'] = 422; $out['reason'] = 'نوع إذن غير معرَّف أو بيانات ناقصة';
            $stmt->close();
            return $out;
        }
        $reqId = intval($conn->insert_id);
        $stmt->close();
        self::history($conn, $reqId, 'draft', 'pending', $by);
        $out['ok'] = true; $out['code'] = 201; $out['req_id'] = $reqId;
        $out['reason'] = 'أُنشئ طلب الإذن #' . $reqId . ' — بانتظار موافقات مصفوفة §5 بترتيبها';
        return $out;
    }

    /**
     * موافقة أو رفض خطوة — التسلسل محروس (ORG-12).
     * @return array{ok:bool, code:int, reason:string, state:?string}
     */
    public static function approve(\mysqli $conn, $reqId, $personId, $decision = 'approve', $reason = null, $stepRqId = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'state' => null);
        $reqId = intval($reqId); $personId = intval($personId);
        $req = self::fetch($conn, $reqId);
        if (!$req) { $out['code'] = 404; $out['reason'] = 'طلب غير موجود'; return $out; }
        if ($req['state'] !== 'pending') {
            $out['code'] = 409; $out['reason'] = 'الطلب ليس بانتظار الموافقات (حالته: ' . $req['state'] . ')';
            return $out;
        }

        // الخطوات المطلوبة بترتيبها وما اكتمل منها
        $steps = self::steps($conn, $reqId);
        $next = null;
        foreach ($steps as $s) {
            if ($s['decision'] === null) { $next = $s; break; }
            if ($s['decision'] === 'reject') {
                $out['code'] = 409; $out['reason'] = 'الطلب مرفوض في خطوة سابقة'; return $out;
            }
        }
        if ($next === null) { $out['code'] = 409; $out['reason'] = 'الموافقات مكتملة أصلًا'; return $out; }

        // ① «لا تُفتح خطوة قبل اكتمال ما قبلها» — 409
        if ($stepRqId !== null && intval($stepRqId) !== intval($next['rq_id'])) {
            $out['code'] = 409;
            $out['reason'] = 'خطوة قبل سابقتها — الدور الآن للخطوة ' . $next['seq_no']
                . ' (' . $next['approver_role'] . ') · ORG-01 §7-⑤';
            return $out;
        }

        // ② «موافق بلا تفويض ساري» — 403
        if (!self::approverAuthorized($conn, $personId, intval($req['company_id']), (string) $next['approver_role'], intval($req['site_id']))) {
            $out['code'] = 403;
            $out['reason'] = 'لا تفويض ساريًا لهذا الشخص عن دور ' . $next['approver_role'] . ' في هذا الموقع';
            self::denyLog($conn, intval($req['company_id']), $personId, 'permit:' . $reqId, 'approver_without_authority');
            return $out;
        }

        // ③ تسجيل القرار — UQ(req_id, rq_id) يجعله عاطلًا
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO permit_approval_actions (req_id, rq_id, approver_person_id, decision, reason)
             VALUES (?, ?, ?, ?, ?)");
        $rq = intval($next['rq_id']); $dec = $decision === 'reject' ? 'reject' : 'approve';
        $stmt->bind_param('iiiss', $reqId, $rq, $personId, $dec, $reason);
        $stmt->execute();
        $stmt->close();

        if ($dec === 'reject') {
            $conn->query("UPDATE permit_requests SET state = 'rejected' WHERE req_id = {$reqId}");
            self::history($conn, $reqId, 'pending', 'rejected', $personId);
            $out['ok'] = true; $out['code'] = 200; $out['state'] = 'rejected';
            $out['reason'] = 'رُفض الطلب في الخطوة ' . $next['seq_no'];
            return $out;
        }

        // ④ أكتملت الإلزاميات كلها؟ → approved + valid_until بساعة القاعدة
        $remaining = 0;
        foreach (self::steps($conn, $reqId) as $s) {
            if (intval($s['mandatory']) === 1 && $s['decision'] === null) { $remaining++; }
        }
        if ($remaining === 0) {
            $conn->query(
                "UPDATE permit_requests r
                   JOIN permit_types t ON t.permit_type_code = r.permit_type_code
                    SET r.state = 'approved',
                        r.valid_until = DATE_ADD(NOW(), INTERVAL t.validity_hours HOUR)
                  WHERE r.req_id = {$reqId}");
            self::history($conn, $reqId, 'pending', 'approved', $personId); // PermitApproved
            $out['ok'] = true; $out['code'] = 200; $out['state'] = 'approved';
            $out['reason'] = 'اكتملت الموافقات — الإذن ساري المدة';
            return $out;
        }
        $out['ok'] = true; $out['code'] = 200; $out['state'] = 'pending';
        $out['reason'] = 'سُجّلت الموافقة — بقي ' . $remaining . ' خطوة';
        return $out;
    }

    /**
     * البوابة — قبل كل دخول/خروج/تفعيل/إيقاف (ORG-13 يستدعيها من المواضع التسعة).
     * تستهلك الإذن الساري (PermitUsed) عند consume=true.
     * @return array{ok:bool, code:int, reason:string, req_id:?int, mode:string}
     */
    public static function check(\mysqli $conn, $companyId, $permitTypeCode, $subjectRef, $siteId, $actorPersonId = 0, $consume = true)
    {
        $mode = strtolower((string) (function_exists('ems_env') ? ems_env('EMS_PERMIT_GATE', 'off') : 'off'));
        $out = array('ok' => true, 'code' => 200, 'reason' => '', 'req_id' => null, 'mode' => $mode);
        if ($mode !== 'monitor' && $mode !== 'enforce') { return $out; }

        // التجربة بموقع واحد: قائمة مواقع الإنفاذ — فارغة = الكل
        $sitesRaw = trim((string) (function_exists('ems_env') ? ems_env('EMS_PERMIT_GATE_SITES', '') : ''));
        if ($sitesRaw !== '') {
            $ids = array_map('intval', explode(',', $sitesRaw));
            if (!in_array(intval($siteId), $ids, true)) { return $out; }
        }

        $companyId = intval($companyId); $siteId = intval($siteId);
        $stmt = $conn->prepare(
            "SELECT req_id, state, valid_until, valid_until >= NOW() AS still_valid
               FROM permit_requests
              WHERE company_id = ? AND permit_type_code = ? AND subject_ref = ? AND site_id = ?
                AND state IN ('approved','expired','used')
              ORDER BY req_id DESC LIMIT 1");
        $stmt->bind_param('issi', $companyId, $permitTypeCode, $subjectRef, $siteId);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $deny = null;
        if (!$p || $p['state'] === 'used') {
            $deny = array(403, 'لا إذن مكتمل الموافقات لهذا الموضوع وهذا الموقع — الفعل ممنوع ويُطلب إذن ' . $permitTypeCode);
        } elseif ($p['state'] === 'expired' || intval($p['still_valid']) !== 1) {
            if ($p['state'] === 'approved') {
                $conn->query("UPDATE permit_requests SET state = 'expired' WHERE req_id = " . intval($p['req_id']));
                self::history($conn, intval($p['req_id']), 'approved', 'expired', 0); // PermitExpired
            }
            $deny = array(423, 'الإذن منتهي المدة — يُطلب تجديده (423)');
        }

        if ($deny !== null) {
            self::denyLog($conn, $companyId, intval($actorPersonId), $permitTypeCode . ':' . $subjectRef . '@' . $siteId,
                $deny[0] === 423 ? 'permit_expired' : 'permit_missing');
            if ($mode === 'enforce') {
                $out['ok'] = false; $out['code'] = $deny[0]; $out['reason'] = $deny[1];
                return $out;
            }
            $out['reason'] = '[مراقبة] ' . $deny[1];
            return $out; // monitor: يُسجَّل ويمضي
        }

        $out['req_id'] = intval($p['req_id']);
        if ($consume) {
            $conn->query("UPDATE permit_requests SET state = 'used' WHERE req_id = " . intval($p['req_id']));
            self::history($conn, intval($p['req_id']), 'approved', 'used', intval($actorPersonId)); // PermitUsed
        }
        $out['reason'] = 'إذن ساري #' . $p['req_id'];
        return $out;
    }

    /** مسح الانتهاء الدوري — PermitExpired بساعة القاعدة. */
    public static function sweepExpired(\mysqli $conn, $companyId = null)
    {
        $coFilter = $companyId !== null ? ' AND company_id = ' . intval($companyId) : '';
        $res = $conn->query("SELECT req_id FROM permit_requests
                              WHERE state = 'approved' AND valid_until IS NOT NULL AND valid_until < NOW(){$coFilter}");
        $n = 0;
        while ($res && ($r = $res->fetch_assoc())) {
            $id = intval($r['req_id']);
            $conn->query("UPDATE permit_requests SET state = 'expired' WHERE req_id = {$id}");
            self::history($conn, $id, 'approved', 'expired', 0);
            $n++;
        }
        return $n;
    }

    /** أيملك الشخص دورَ الموافقة لهذا الموقع؟ — من التكليفات النافذة أو الأدوار الموازية. */
    public static function approverAuthorized(\mysqli $conn, $personId, $companyId, $approverRole, $siteId)
    {
        if (isset(self::$roleAssignmentTypes[$approverRole])) {
            $types = self::$roleAssignmentTypes[$approverRole];
            $in = "'" . implode("','", $types) . "'";
            $active = OrgAuthorityResolver::resolve($conn, $personId, $companyId);
            foreach ($active as $a) {
                if (!in_array($a['assignment_type_code'], $types, true)) { continue; }
                if (OrgAuthorityResolver::scopeCovers($conn, $a, 'site', $siteId)) { return true; }
            }
            return false;
        }
        if (isset(self::$roleParallelRoles[$approverRole])) {
            $stmt = $conn->prepare('SELECT role FROM users WHERE id = ? AND company_id = ? LIMIT 1');
            $personId = intval($personId); $companyId = intval($companyId);
            $stmt->bind_param('ii', $personId, $companyId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row && in_array((string) $row['role'], self::$roleParallelRoles[$approverRole], true);
        }
        return false;
    }

    /** خطوات الطلب المطلوبة بترتيبها مع قراراتها. */
    public static function steps(\mysqli $conn, $reqId)
    {
        $reqId = intval($reqId);
        $res = $conn->query(
            "SELECT rq.rq_id, rq.seq_no, rq.approver_role, rq.mandatory,
                    a.decision, a.approver_person_id, a.at
               FROM permit_requests r
               JOIN permit_required_approvals rq ON rq.permit_type_code = r.permit_type_code
               LEFT JOIN permit_approval_actions a ON a.req_id = r.req_id AND a.rq_id = rq.rq_id
              WHERE r.req_id = {$reqId}
              ORDER BY rq.seq_no");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
    }

    public static function fetch(\mysqli $conn, $reqId)
    {
        $reqId = intval($reqId);
        $res = $conn->query("SELECT * FROM permit_requests WHERE req_id = {$reqId} LIMIT 1");
        return $res ? $res->fetch_assoc() : null;
    }

    private static function history(\mysqli $conn, $reqId, $from, $to, $by)
    {
        $stmt = $conn->prepare(
            'INSERT INTO permit_status_history (req_id, from_state, to_state, by_person_id) VALUES (?, ?, ?, ?)');
        $reqId = intval($reqId); $by = intval($by);
        $stmt->bind_param('issi', $reqId, $from, $to, $by);
        $stmt->execute();
        $stmt->close();
    }

    private static function denyLog(\mysqli $conn, $companyId, $personId, $ref, $reasonCode)
    {
        $stmt = $conn->prepare(
            "INSERT INTO guard_denials (company_id, guard_code, person_id, attempted_ref, reason_code)
             VALUES (?, 'permit_gate', ?, ?, ?)");
        $companyId = intval($companyId); $personId = intval($personId);
        $stmt->bind_param('iiss', $companyId, $personId, $ref, $reasonCode);
        $stmt->execute();
        $stmt->close();
    }
}
