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

    /**
     * تفكيك نص سلسلة الاعتماد (الورقة 04) إلى خطواتٍ قابلةِ الحل:
     * «المدير ← الموارد ← المالية» ⇒ [manager, roles(4), roles(19,17,18)].
     * الرموز غير المحلولة (سلّم السقوف · بحسب الدرجة …) تسقط للحوكمة (15)
     * معلَنةً — لا تُخترع جهة (WF-07).
     */
    public static function chainStepsOf($chainText)
    {
        $steps = array();
        $parts = preg_split('/[←→]|<-|->/u', (string) $chainText);
        $map = array(
            'المدير' => 'manager', 'مديره' => 'manager',
            'الموارد' => array('4'), 'المالية' => array('19', '17', '18'),
            'التنفيذي' => array('9'), 'الحوكمة' => array('15'),
            'التشغيل' => array('1', '6'), 'الصيانة' => array('13', '14'),
            'المخازن' => array('25'), 'المشتريات' => array('16'),
            'النقل' => array('23'), 'المبيعات' => array('12'),
            'الموردون' => array('2', '8'), 'الأسطول' => array('3', '10'),
            'التمويل' => array('26'), 'القوى' => array('4'),
            'المفوض' => 'requester_manager', 'القانوني' => array('15'),
        );
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '' || mb_strpos($p, 'لا اعتماد') !== false) { continue; }
            $resolved = null;
            foreach ($map as $needle => $target) {
                if (mb_strpos($p, $needle) !== false) { $resolved = $target; break; }
            }
            if ($resolved === null) { $resolved = array('15'); } // غير محلول ⇒ الحوكمة معلَنةً
            // «إن لزم» و«للتحقق» خطوات شرطية — تُنشأ وتُعلَّم بنصها
            $steps[] = array('who' => $resolved, 'label' => mb_substr($p, 0, 60));
        }
        return $steps;
    }

    /** حل خطوةٍ إلى مستخدمٍ متاح (المدير من الهرم · الدور بأهلية الإجازة) */
    private static function resolveStepUser(\mysqli $conn, $co, array $step, $requesterId)
    {
        require_once dirname(__DIR__, 2) . '/../includes/resolve_manager.php';
        if ($step['who'] === 'manager' || $step['who'] === 'requester_manager') {
            $m = ems_resolve_manager($conn, intval($requesterId));
            if ($m !== null) {
                $pick = ems_pick_available($conn, array($m));
                return $pick['user_id'] ?: $m;
            }
            return ems_resolve_verifier($conn, intval($requesterId), $co);
        }
        $candidates = array();
        foreach ((array) $step['who'] as $role) {
            $st = $conn->prepare("SELECT id FROM users WHERE role = ? AND company_id = ?
                                   AND COALESCE(status,'active') = 'active' ORDER BY id LIMIT 3");
            $st->bind_param('si', $role, $co);
            $st->execute();
            $rs = $st->get_result();
            while ($r = $rs->fetch_assoc()) { $candidates[] = intval($r['id']); }
            $st->close();
        }
        if (!$candidates) { return null; }
        $pick = ems_pick_available($conn, $candidates);
        return $pick['user_id'];
    }

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

        // ═══ مصفوفة الموافقات تشغيلًا (الورقة 09): السلسلة خطواتُ approval_links ═══
        // خطوةُ اليوم وحدَها pending والحاملُ معتمِدُها (SRC-04: خطوةُ اعتمادٍ
        // تنتظر قرارًا) — والتنفيذُ بعد اكتمالها للمستقبِل (SRC-05).
        $steps = self::chainStepsOf((string) $type['approval_chain']);
        $holder = null;
        if ($steps) {
            $stepUser = self::resolveStepUser($conn, $co, $steps[0], $ru);
            if ($stepUser !== null) {
                $holder = intval($stepUser);
                $stepSla = max(1, intval(ceil($sla / max(1, count($steps)))));
                $al = $conn->prepare("INSERT INTO approval_links
                    (company_id, source_kind, source_ref, action_code, step_no, approver_user_id,
                     approver_role, status, sla_due_at, created_by, parent_ref)
                    VALUES (?, 'request', ?, 'request.approve', 1, ?, ?, 'pending',
                            DATE_ADD(NOW(), INTERVAL ? HOUR), ?, ?)");
                $roleLbl = mb_substr((string) $steps[0]['label'], 0, 120);
                $al->bind_param('ssisiis', $co, $no, $holder, $roleLbl, $stepSla, $by, $no);
                $al->execute();
                $al->close();
                $conn->query("UPDATE requests SET status = 'in_approval', current_holder_user_id = {$holder}, current_step = 1 WHERE id = " . intval($id));
                WorkItemService::notifyUser($conn, $co, $holder,
                    'خطوةُ اعتمادٍ تنتظر قرارك (' . $type['name_ar'] . ' · ' . $steps[0]['label'] . ')',
                    $ti, 'Portal/approvals_inbox.php', true, $by);
            }
        }
        if ($holder === null) {
            // لا سلسلةَ (توجيه آلي) أو تعذّر حلُّ الخطوة ⇒ للمستقبِل مباشرة
            $holder = self::resolveReceiverUser($conn, $co, $type, $ru);
            if ($holder) {
                $u = intval($holder);
                $conn->query("UPDATE requests SET status = 'routed', current_holder_user_id = {$u}, current_step = 0 WHERE id = " . intval($id));
                WorkItemService::notifyUser($conn, $co, $u, 'طلبٌ جديدٌ لإدارتك (' . $type['name_ar'] . ')', $ti,
                    'Portal/my_requests.php?req=' . $id, true, $by);
            }
        }
        WorkItemService::notifyUser($conn, $co, $ru, 'قُدّم طلبك ' . $no, $ti, 'Portal/my_requests.php?req=' . $id, false, $by);
        return array('ok' => true, 'code' => 200, 'id' => $id, 'request_no' => $no, 'holder' => $holder,
                     'steps' => count($steps));
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
        // القرارُ لحاملِ الخطوة وحدَه (SRC-04) — لا «لأي أحدٍ غير المقدّم»
        $holderNow = intval($rq['current_holder_user_id']);
        if ($holderNow > 0 && $actor !== $holderNow) {
            return array('ok' => false, 'code' => 403,
                'reason' => 'القرارُ لحامل الخطوة الحالي (u' . $holderNow . ') — وأنت لست إياه');
        }
        if (in_array($decision, array('reject', 'return'), true) && trim($note) === '') {
            return array('ok' => false, 'code' => 422, 'reason' => 'السبب إلزاميٌّ للرفض والإعادة');
        }
        $map = array('approve' => 'approved', 'reject' => 'rejected', 'return' => 'returned');
        if (!isset($map[$decision])) { return array('ok' => false, 'code' => 422, 'reason' => 'قرارٌ غير معروف'); }
        $to = $map[$decision];
        $id = intval($rq['id']);
        $co = intval($rq['company_id']);
        $no = (string) ($rq['request_no'] ?: $id);
        $stepNo = intval($rq['current_step']);

        // ختم خطوة approval_links الجارية بقرارها
        $alStatus = array('approved' => 'approved', 'rejected' => 'rejected', 'returned' => 'returned');
        $st = $conn->prepare("UPDATE approval_links SET status = ?, decided_at = NOW(), decision_note = ?,
                                     approved_by = ?, approved_at = NOW()
                               WHERE source_kind = 'request' AND source_ref = ? AND step_no = ? AND status = 'pending'");
        $als = $alStatus[$to];
        $noteS = mb_substr($note, 0, 300);
        $st->bind_param('ssisi', $als, $noteS, $actor, $no, $stepNo);
        $st->execute();
        $st->close();

        // اعتمادُ خطوةٍ وسْطى: تُفتح التالية ويظل الطلب in_approval
        if ($to === 'approved' && $stepNo > 0) {
            $type = self::typeOf($conn, (string) $rq['request_type_code']);
            $steps = self::chainStepsOf((string) ($type['approval_chain'] ?? ''));
            if ($stepNo < count($steps)) {
                $next = $steps[$stepNo]; // الفهرس صفري والخطوة تالية
                $nextUser = self::resolveStepUser($conn, $co, $next, intval($rq['requester_user_id']));
                if ($nextUser !== null) {
                    $nu = intval($nextUser);
                    $ns = $stepNo + 1;
                    $al = $conn->prepare("INSERT INTO approval_links
                        (company_id, source_kind, source_ref, action_code, step_no, approver_user_id,
                         approver_role, status, sla_due_at, created_by, parent_ref)
                        VALUES (?, 'request', ?, 'request.approve', ?, ?, ?, 'pending',
                                DATE_ADD(NOW(), INTERVAL 48 HOUR), ?, ?)");
                    $roleLbl = mb_substr((string) $next['label'], 0, 120);
                    $al->bind_param('ssiisis', $co, $no, $ns, $nu, $roleLbl, $actor, $no);
                    $al->execute();
                    $al->close();
                    $conn->query("UPDATE requests SET current_holder_user_id = {$nu}, current_step = {$ns}
                                   WHERE id = {$id} AND status = 'in_approval'");
                    WorkItemService::notifyUser($conn, $co, $nu,
                        'خطوةُ اعتمادٍ تنتظر قرارك (' . $next['label'] . ') — ' . $no,
                        (string) $rq['title'], 'Portal/approvals_inbox.php', true, $actor);
                    WorkItemService::notifyUser($conn, $co, intval($rq['requester_user_id']),
                        'اجتاز طلبُك الخطوة ' . $stepNo . ' وانتقل إلى: ' . $next['label'],
                        (string) $rq['title'], 'Portal/my_requests.php?req=' . $id, false, $actor);
                    return array('ok' => true, 'code' => 200, 'status' => 'in_approval', 'next_step' => $ns);
                }
            }
        }

        $st = $conn->prepare("UPDATE requests SET status = ?, status_reason = ?, approved_by = ?, approved_at = NOW(),
                                current_holder_user_id = CASE WHEN ? = 'returned' THEN requester_user_id ELSE current_holder_user_id END
                              WHERE id = ? AND status IN ('submitted','routed','in_approval')");
        $st->bind_param('ssisi', $to, $noteS, $actor, $to, $id);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();
        if (!$ok) { return array('ok' => false, 'code' => 409, 'reason' => 'سباق حالة'); }

        if ($to === 'approved') {
            // اكتملت السلسلة ⇒ التنفيذ للمستقبِل (SRC-05: مهمةُ معالجةٍ بمهلتها)
            $type = isset($type) ? $type : self::typeOf($conn, (string) $rq['request_type_code']);
            $exec = self::resolveReceiverUser($conn, $co, $type ?: array('receiver' => '', 'owner_dept' => ''), intval($rq['requester_user_id']));
            if ($exec) {
                $eu = intval($exec);
                $conn->query("UPDATE requests SET current_holder_user_id = {$eu} WHERE id = {$id}");
                WorkItemService::create($conn, array(
                    'company_id' => $co, 'source_type' => 'SRC-05', 'source_ref' => $no,
                    'source_screen' => 'Portal/my_requests.php',
                    'owner_user_id' => $eu, 'assigned_user_id' => $eu,
                    'org_unit_id' => intval($rq['org_unit_id']) ?: 1,
                    'project_id' => intval($rq['project_id']) ?: 0, 'site_id' => intval($rq['site_id']) ?: 0,
                    'title' => 'تنفيذ ' . (($type['name_ar'] ?? 'طلب')) . ' — ' . $no,
                    'deliverable' => (string) ($type['deliverable'] ?? 'مخرَج الطلب'),
                    'evidence_required' => 'صف الرد التسعة على ' . $no,
                    'due_at' => date('Y-m-d H:i:s', time() + 48 * 3600),
                    'priority' => 'P2', 'created_by' => $actor, 'parent_ref' => $no,
                ));
                WorkItemService::notifyUser($conn, $co, $eu, 'طلبٌ معتمدٌ ينتظر تنفيذك — ' . $no,
                    (string) $rq['title'], 'Portal/my_requests.php?req=' . $id, true, $actor);
            }

            // M-00 §11 (ExecApproved): حين يكون صاحبُ القرار النهائي تنفيذيًّا (دور 9)
            // تُنشر حقيقةُ الاعتماد التنفيذي باسمها من نقطة الحدث — يستهلكها سجلُّ
            // الاعتمادات والتدقيق. الحارس أعلاه (409 سباق حالة) يضمن نشرةً واحدة
            // لكل بلوغِ «approved»، والمفتاح الموقوت يحفظ إعادةَ الاعتماد بعد إرجاعٍ حقيقةً جديدة.
            try {
                $rr = $conn->query('SELECT role FROM users WHERE id = ' . intval($actor) . ' LIMIT 1');
                $actorRole = ($rr && ($w = $rr->fetch_assoc())) ? (string) $w['role'] : '';
                if ($actorRole === '9') {
                    require_once dirname(dirname(__DIR__)) . '/Core/EventPublisher.php';
                    \App\Core\EventPublisher::publishFact($conn, array(
                        'event_key'       => 'exec.approval.granted',
                        'category'        => 'operational',
                        'source_module'   => 'system',
                        'company_id'      => $co,
                        'entity_type'     => 'request',
                        'entity_id'       => $id,
                        'occurred_at'     => gmdate('Y-m-d H:i:s'),
                        'created_by'      => intval($actor) ?: 1,
                        'idempotency_key' => 'exec_approval:req:' . $id . ':' . gmdate('YmdHis'),
                        'notes'           => 'اعتمادٌ تنفيذيٌّ نهائي — ' . $no,
                        'payload'         => array(
                            'request_id'   => $id,
                            'request_no'   => $no,
                            'request_type' => (string) $rq['request_type_code'],
                            'title'        => (string) $rq['title'],
                            'requester'    => intval($rq['requester_user_id']),
                            'approved_by'  => intval($actor),
                        ),
                    ));
                }
            } catch (\Throwable $t) { error_log('RequestService exec fact #' . $id . ': ' . $t->getMessage()); }
        }

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
        // التنفيذ لحامله (الإدارة المنفِّذة) — كما القرار لحامل خطوته
        $holderNow = intval($rq['current_holder_user_id']);
        if ($holderNow > 0 && intval($actorUserId) !== $holderNow) {
            return array('ok' => false, 'code' => 403,
                'reason' => 'التنفيذُ لحامل الطلب الحالي (u' . $holderNow . ')');
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

    /** إلغاء/عكس: يسحب إنجازه إن اشتُق (AC-WFM-14) — لمقدِّمه أو حامله وحدهما */
    public static function cancel(\mysqli $conn, $requestId, $actorUserId, $reason)
    {
        $rq = self::fetch($conn, $requestId);
        if (!$rq) { return array('ok' => false, 'code' => 404, 'reason' => 'الطلب غير موجود'); }
        if (trim($reason) === '') { return array('ok' => false, 'code' => 422, 'reason' => 'السبب إلزامي'); }
        $actor = intval($actorUserId);
        if ($actor !== intval($rq['requester_user_id']) && $actor !== intval($rq['current_holder_user_id'])) {
            return array('ok' => false, 'code' => 403,
                'reason' => 'الإلغاءُ لمقدِّم الطلب أو حامله الحالي وحدهما');
        }
        if (in_array((string) $rq['status'], array('closed', 'cancelled'), true)) {
            return array('ok' => false, 'code' => 409, 'reason' => 'لا إلغاءَ لطلبٍ ' . $rq['status']);
        }
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
