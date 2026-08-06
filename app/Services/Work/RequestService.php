<?php
/**
 * محرّك الطلبات — RequestService (WFM-01 §5-4/5-5 · WF-05/WF-07)
 * ───────────────────────────────────────────────────────────────────────────
 *  - القاموس حاكم: لا طلبَ بنوعٍ خارج request_types النافذة (AC-WFM-03).
 *  - التوجيه بقاعدة النوع (المستقبِل من القاموس) واليدويُّ استثناءٌ مسجَّل.
 *  - «من هو عنده الآن» عمودٌ حيٌّ (current_holder) — صفرُ طلبٍ لا يُعرف
 *    أين توقف (AC-WFM-07).
 *  - الإغلاق لا يقع بتغيير حالة: تسعةُ عناصر الرد صفٌّ إلزاميٌّ في
 *    request_responses قبل closed (WF-05) — والحارس بنيوي.
 *  - التنفيذ يشتق إنجازًا للمنفِّذ (الورقة 11) ويُعكس بإلغاء الطلب.
 */

namespace App\Services\Work;

require_once __DIR__ . '/WorkItemService.php';
require_once __DIR__ . '/AchievementService.php';

class RequestService
{
    const STATES = array('draft', 'submitted', 'routed', 'in_approval', 'approved',
                         'rejected', 'executing', 'executed', 'closed', 'returned', 'cancelled');

    /** جلب نوعٍ نافذ */
    public static function typeOf(\mysqli $conn, $code)
    {
        $st = $conn->prepare("SELECT * FROM request_types WHERE code = ? LIMIT 1");
        $code = (string) $code;
        $st->bind_param('s', $code);
        $st->execute();
        $t = $st->get_result()->fetch_assoc();
        $st->close();
        return $t ?: null;
    }

    /**
     * تقديم طلب — RequestSubmitted ثم RequestRouted بقاعدة القاموس.
     * @return array{ok:bool,code:int,id?:int,reason?:string}
     */
    public static function submit(\mysqli $conn, array $a)
    {
        $type = self::typeOf($conn, (string) ($a['request_type_code'] ?? ''));
        if (!$type) { return array('ok' => false, 'code' => 422, 'reason' => 'نوعُ طلبٍ خارج القاموس'); }
        if ((string) $type['status'] !== 'active') {
            return array('ok' => false, 'code' => 422, 'reason' => 'النوع ' . $type['code'] . ' ' . ($type['status'] === 'proposed' ? 'مقترحٌ ينتظر الاعتماد' : 'متقاعد'));
        }
        foreach (array('company_id' => 'الكيان', 'requester_user_id' => 'المقدّم', 'title' => 'الموضوع') as $k => $l) {
            if (empty($a[$k])) { return array('ok' => false, 'code' => 422, 'reason' => 'حقل إلزامي: ' . $l); }
        }
        if (empty($a['org_unit_id']) && empty($a['project_id']) && empty($a['site_id'])) {
            return array('ok' => false, 'code' => 422, 'reason' => 'النطاق إلزامي (إدارة/مشروع/موقع)');
        }

        $co = intval($a['company_id']);
        $sla = max(1, intval($type['sla_hours']));
        $st = $conn->prepare("INSERT INTO requests
            (company_id, request_type_code, requester_user_id, beneficiary_ref, org_unit_id, project_id, site_id,
             title, fields_json, status, submitted_at, sla_due_at, created_by, created_capacity, parent_ref)
            VALUES (?,?,?,?,?,?,?,?,?,'submitted',NOW(),DATE_ADD(NOW(), INTERVAL ? HOUR),?,?,?)");
        $tc = (string) $type['code'];
        $ru = intval($a['requester_user_id']);
        $ben = isset($a['beneficiary_ref']) ? (string) $a['beneficiary_ref'] : null;
        $org = !empty($a['org_unit_id']) ? intval($a['org_unit_id']) : null;
        $prj = !empty($a['project_id']) ? intval($a['project_id']) : null;
        $site = !empty($a['site_id']) ? intval($a['site_id']) : null;
        $ti = mb_substr((string) $a['title'], 0, 300);
        $fj = isset($a['fields_json']) ? (string) $a['fields_json'] : null;
        $by = intval($a['created_by'] ?? $ru);
        $cap = isset($a['created_capacity']) ? (string) $a['created_capacity'] : null;
        $pref = isset($a['parent_ref']) ? (string) $a['parent_ref'] : null;
        $st->bind_param('isisiiissiiss', $co, $tc, $ru, $ben, $org, $prj, $site, $ti, $fj, $sla, $by, $cap, $pref);
        if (!$st->execute()) { $e = $st->error; $st->close(); return array('ok' => false, 'code' => 422, 'reason' => $e); }
        $id = $st->insert_id;
        $st->close();
        $no = 'REQ-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $conn->query("UPDATE requests SET request_no = '{$no}' WHERE id = " . intval($id));

        // التوجيه بقاعدة القاموس (WF-07): المستقبِل حامل الطلب الآن
        $holder = self::resolveReceiverUser($conn, $co, $type, $ru);
        if ($holder) {
            $u = intval($holder);
            $conn->query("UPDATE requests SET status = 'routed', current_holder_user_id = {$u}, current_step = 1 WHERE id = " . intval($id));
            WorkItemService::notifyUser($conn, $co, $u, 'طلبٌ جديدٌ لإدارتك (' . $type['name_ar'] . ')', $ti,
                'Portal/my_requests.php?req=' . $id, true, $by);
            // SRC-05: مهمة معالجةٍ للمستقبِل
            WorkItemService::create($conn, array(
                'company_id' => $co, 'source_type' => 'SRC-05', 'source_ref' => $no,
                'source_screen' => 'Portal/my_requests.php',
                'owner_user_id' => $u, 'assigned_user_id' => $u,
                'org_unit_id' => $org ?: 0, 'project_id' => $prj ?: 0, 'site_id' => $site ?: 0,
                'title' => 'معالجة ' . $type['name_ar'] . ' — ' . $no,
                'deliverable' => (string) $type['deliverable'],
                'evidence_required' => 'صف الرد التسعة على ' . $no,
                'due_at' => date('Y-m-d H:i:s', time() + $sla * 3600),
                'priority' => $sla <= 8 ? 'P1' : ($sla <= 48 ? 'P2' : 'P3'),
                'created_by' => $by, 'parent_ref' => $no,
            ));
        }
        WorkItemService::notifyUser($conn, $co, $ru, 'قُدّم طلبك ' . $no, $ti, 'Portal/my_requests.php?req=' . $id, false, $by);
        return array('ok' => true, 'code' => 200, 'id' => $id, 'request_no' => $no, 'holder' => $holder);
    }

    /**
     * قرار على طلب: approve/reject/return — بالسبب حيث يلزم، ويُخطَر المقدّم.
     */
    public static function decide(\mysqli $conn, $requestId, $decision, $actorUserId, $note = '')
    {
        $rq = self::fetch($conn, $requestId);
        if (!$rq) { return array('ok' => false, 'code' => 404, 'reason' => 'الطلب غير موجود'); }
        if (!in_array($rq['status'], array('submitted', 'routed', 'in_approval'), true)) {
            return array('ok' => false, 'code' => 409, 'reason' => 'لا قرارَ على حالة ' . $rq['status']);
        }
        $actor = intval($actorUserId);
        // فصل الواجبات: لا يعتمد المرءُ طلبَه (RB/SoD)
        if ($decision === 'approve' && $actor === intval($rq['requester_user_id'])) {
            return array('ok' => false, 'code' => 403, 'reason' => 'لا اعتمادَ للذات — من قدَّم لا يعتمد');
        }
        if (in_array($decision, array('reject', 'return'), true) && trim($note) === '') {
            return array('ok' => false, 'code' => 422, 'reason' => 'السبب إلزاميٌّ للرفض والإعادة');
        }
        $map = array('approve' => 'approved', 'reject' => 'rejected', 'return' => 'returned');
        if (!isset($map[$decision])) { return array('ok' => false, 'code' => 422, 'reason' => 'قرارٌ غير معروف'); }
        $to = $map[$decision];
        $id = intval($rq['id']);
        $co = intval($rq['company_id']);
        $st = $conn->prepare("UPDATE requests SET status = ?, status_reason = ?, approved_by = ?, approved_at = NOW(),
                                current_holder_user_id = CASE WHEN ? = 'returned' THEN requester_user_id ELSE current_holder_user_id END
                              WHERE id = ? AND status IN ('submitted','routed','in_approval')");
        $noteS = mb_substr($note, 0, 300);
        $st->bind_param('ssisi', $to, $noteS, $actor, $to, $id);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();
        if (!$ok) { return array('ok' => false, 'code' => 409, 'reason' => 'سباق حالة'); }

        $msg = array('approved' => 'اعتُمد طلبُك — والخطوةُ التالية التنفيذ',
                     'rejected' => 'رُفض طلبُك — والسبب: ' . $note,
                     'returned' => 'أُعيد طلبُك لاستكمال: ' . $note);
        WorkItemService::notifyUser($conn, $co, intval($rq['requester_user_id']), $msg[$to],
            (string) $rq['title'], 'Portal/my_requests.php?req=' . $id, $to === 'returned', $actor);
        return array('ok' => true, 'code' => 200, 'status' => $to);
    }

    /**
     * تنفيذ وإغلاق — WF-05: لا إغلاقَ إلا بصف الرد التسعة كاملًا.
     * $nine: decision·decided_capacity·notes·action_required·result_doc_ref·
     *        executed_summary·next_step (والمقرر والتاريخ يُحقنان).
     */
    public static function executeAndClose(\mysqli $conn, $requestId, $actorUserId, array $nine)
    {
        $rq = self::fetch($conn, $requestId);
        if (!$rq) { return array('ok' => false, 'code' => 404, 'reason' => 'الطلب غير موجود'); }
        if (!in_array($rq['status'], array('approved', 'executing'), true)) {
            return array('ok' => false, 'code' => 409, 'reason' => 'التنفيذ بعد الاعتماد — الحالة ' . $rq['status']);
        }
        foreach (array('decision' => '① القرار', 'result_doc_ref' => '⑦ المستند الناتج',
                       'executed_summary' => '⑧ التنفيذ الذي تم') as $k => $l) {
            if (trim((string) ($nine[$k] ?? '')) === '') {
                return array('ok' => false, 'code' => 422, 'reason' => 'الردُّ ناقص — ' . $l . ' إلزامي (WF-05)');
            }
        }
        $id = intval($rq['id']);
        $co = intval($rq['company_id']);
        $actor = intval($actorUserId);
        $st = $conn->prepare("INSERT INTO request_responses
            (company_id, request_id, decision, decided_by, decided_capacity, notes,
             action_required, result_doc_ref, executed_summary, next_step, origin_link)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $dec = mb_substr((string) $nine['decision'], 0, 24);
        $cap = isset($nine['decided_capacity']) ? (string) $nine['decided_capacity'] : null;
        $notes = isset($nine['notes']) ? mb_substr((string) $nine['notes'], 0, 400) : null;
        $act = isset($nine['action_required']) ? mb_substr((string) $nine['action_required'], 0, 300) : null;
        $doc = mb_substr((string) $nine['result_doc_ref'], 0, 200);
        $exs = mb_substr((string) $nine['executed_summary'], 0, 300);
        $nxt = isset($nine['next_step']) ? mb_substr((string) $nine['next_step'], 0, 200) : null;
        $ol = 'Portal/my_requests.php?req=' . $id;
        $st->bind_param('iisisssssss', $co, $id, $dec, $actor, $cap, $notes, $act, $doc, $exs, $nxt, $ol);
        if (!$st->execute()) { $e = $st->error; $st->close(); return array('ok' => false, 'code' => 422, 'reason' => $e); }
        $st->close();

        $conn->query("UPDATE requests SET status = 'closed', executed_at = NOW(), closed_at = NOW(),
                             current_holder_user_id = NULL WHERE id = {$id}");
        WorkItemService::notifyUser($conn, $co, intval($rq['requester_user_id']),
            'نُفِّذ طلبُك — والمستندُ الناتج: ' . $doc, (string) $rq['title'], $ol, false, $actor);
        // إنجاز المنفِّذ (الورقة 11: «طلبٌ نُفِّذ وأُغلق»)
        AchievementService::derive($conn, array(
            'company_id' => $co, 'source_kind' => 'request', 'source_ref' => (string) ($rq['request_no'] ?: $id),
            'person_user_id' => $actor, 'attribution' => 'executive',
            'title' => 'تنفيذ ' . $rq['title'], 'evidence_ref' => $doc, 'created_by' => $actor,
        ));
        return array('ok' => true, 'code' => 200);
    }

    /** إلغاء/عكس: يسحب إنجازه إن اشتُق (AC-WFM-14) */
    public static function cancel(\mysqli $conn, $requestId, $actorUserId, $reason)
    {
        $rq = self::fetch($conn, $requestId);
        if (!$rq) { return array('ok' => false, 'code' => 404, 'reason' => 'الطلب غير موجود'); }
        if (trim($reason) === '') { return array('ok' => false, 'code' => 422, 'reason' => 'السبب إلزامي'); }
        $id = intval($rq['id']);
        $co = intval($rq['company_id']);
        $conn->query("UPDATE requests SET status = 'cancelled', status_reason = '" .
            $conn->real_escape_string(mb_substr($reason, 0, 300)) . "' WHERE id = {$id}");
        AchievementService::reverseForSource($conn, $co, 'request',
            (string) ($rq['request_no'] ?: $id), 'أُلغي الطلب: ' . $reason, intval($actorUserId));
        return array('ok' => true, 'code' => 200);
    }

    /** حل المستقبِل: أول حساب نشطٍ يحمل دور استقبال النوع (fallback مدير الإدارة) */
    private static function resolveReceiverUser(\mysqli $conn, $co, array $type, $requesterId)
    {
        require_once dirname(__DIR__, 2) . '/../includes/resolve_manager.php';
        $recv = (string) $type['receiver'];
        // «مديره المباشر» قاعدة هرمية لا دورية — والمُجاز يقدَّم نائبُه (قرار 10)
        if (mb_strpos($recv, 'مدير') !== false && mb_strpos($recv, 'المباشر') !== false) {
            $m = ems_resolve_manager($conn, intval($requesterId));
            if ($m) {
                $pick = ems_pick_available($conn, array($m, ems_resolve_manager($conn, $m)));
                if ($pick['user_id']) { return $pick['user_id']; }
                return $m;
            }
        }
        // خريطة استقبال الإدارات → أدوار الاستقبال المعتمدة
        $map = array(
            'الموارد' => array('4'), 'المالية' => array('19', '17', '18'),
            'التشغيل' => array('1', '6'), 'الصيانة' => array('13', '14'),
            'المخازن' => array('25'), 'المشتريات' => array('16'),
            'النقل' => array('23'), 'الحوكمة' => array('15'),
            'المبيعات' => array('12'), 'الموردون' => array('2', '8'),
            'الأسطول' => array('3', '10'), 'التمويل' => array('26'), 'التنفيذية' => array('9'),
        );
        $roles = array();
        foreach ($map as $needle => $rr) {
            if (mb_strpos($recv, $needle) !== false || mb_strpos((string) $type['owner_dept'], $needle) !== false) {
                $roles = $rr;
                break;
            }
        }
        if (!$roles) { $roles = array('15'); } // الحوكمة قاعُ السلّم — لا طلبَ بلا جهة
        $candidates = array();
        foreach ($roles as $role) {
            $st = $conn->prepare("SELECT id FROM users WHERE role = ? AND company_id = ? AND COALESCE(status,'active') = 'active' ORDER BY id LIMIT 3");
            $st->bind_param('si', $role, $co);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) { $candidates[] = intval($r['id']); }
            $st->close();
        }
        if (!$candidates) { return null; }
        // قرار 10: المُجاز لا يُسنَد إليه آليًّا — يُقدَّم المتاح أو نائبُ المُجاز
        $pick = ems_pick_available($conn, $candidates);
        return $pick['user_id'];
    }

    public static function fetch(\mysqli $conn, $id)
    {
        $st = $conn->prepare("SELECT * FROM requests WHERE id = ? LIMIT 1");
        $id = intval($id);
        $st->bind_param('i', $id);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ?: null;
    }
}
