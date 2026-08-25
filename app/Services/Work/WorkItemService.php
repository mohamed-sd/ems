<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): WFM-002 · WFM-004 · WFM-006 · WFM-012 · WFM-013 · WFM-014 · WFM-017 · WFM-021 · WFM-023 · WFM-024 · WFM-025 · WFM-027 · WFM-028 · WFM-029 · WFM-030 · WFM-031 · WFM-033 · WFM-034 · WFM-036 · WFM-037 · WFM-039 · WFM-040 · WFM-041 · WFM-073 · WFM-074 · WFM-077 · WFM-079 · WFM-082 · WFM-098 · WFM-099 · WFM-100 · WFM-109 · WFM-111
// شواهد المتطلبات (AC-E06-03 · موجة ٣ · تتمة): WFM-036 · WFM-037 · WFM-039 · WFM-040 · WFM-041 · WFM-073 · WFM-074 · WFM-077 · WFM-079 · WFM-082 · WFM-098 · WFM-099 · WFM-100 · WFM-109 · WFM-111
/**
 * محرّك العمل الشخصي — WorkItemService (WFM-01 · المبادئ WF-01..11)
 * ───────────────────────────────────────────────────────────────────────────
 * «مساحة عملي واجهةٌ لا مصدر»: هذا المحرّك هو المصدر الذي تقرأ منه الشاشات.
 *
 *  - WF-02  لا عنصرَ بلا سبعة: مصدرٌ ومالكٌ ومنفِّذٌ ونطاقٌ وموعدٌ ومخرَجٌ
 *           ودليلُ إغلاق — الحارس create() يرفض الناقص ويسمّيه.
 *  - WF-04  الإغلاق من غير المنفِّذ: verifyClose() ترفض إغلاق المرء عملَه،
 *           وإن كان المكلِّفُ مستفيدًا رُفع التحقق لمديره (resolve_manager).
 *  - WF-06  التنبيه لا يصير مهمةً إلا بفعل مطلوب: notifyUser() تولّد المهمة
 *           حين requires_action.
 *  - WF-07  التوجيه بقاعدة (request_routes) واليدويُّ استثناءٌ مسجَّل.
 *  - WF-08  انتهاء التفويض يوقف التوليد (isDelegationLive) ولا يلغي المفتوح.
 *  - الورقة 02: خمس عشرة حالة بمخوَّلها وشرط سببها وأثر عدّادها وإخطارها.
 *  - الورقة 05: أولويات P0..P4 بمهلتي الاستجابة والإنجاز.
 *
 * الأثر المالي: لا شيء هنا يكتب مالًا — عناصر العمل تشغيلية (E-01 حدُّه).
 */

namespace App\Services\Work;

/* سجلُّ المسمّياتِ المركزيُّ ونواةُ النقاء (‏REPAIR01 · W06 §٤-٣ و§٤-٤).
   ◆ **بمسارِهما لا بالمحمِّلِ وحدَه**: هذا المحرِّكُ يُستدعى من مسارَي كرونٍ
     وواجهةٍ يُضمَّن فيهما بـ`require_once` مباشرةً قبل إقلاعِ المحمِّل. */
require_once dirname(__DIR__) . '/Ui/UiPurity.php';
require_once dirname(__DIR__) . '/Ui/UiLabelRegistry.php';

class WorkItemService
{
    /** الحالات الخمس عشرة (الورقة 02) */
    const STATES = array(
        'draft', 'scheduled', 'assigned', 'accepted', 'in_progress', 'blocked',
        'done_pending_verify', 'closed_accepted', 'returned', 'rejected',
        'cancelled', 'reassigned', 'delegated', 'overdue', 'reopened',
    );

    /** المصادر الأربعة عشر (الورقة 03) — ولا مصدرَ خارجها */
    const SOURCES = array(
        'SRC-01', 'SRC-02', 'SRC-03', 'SRC-04', 'SRC-05', 'SRC-06', 'SRC-07',
        'SRC-08', 'SRC-09', 'SRC-10', 'SRC-11', 'SRC-12', 'SRC-13', 'SRC-14',
    );

    /** الأولويات (الورقة 05): [مهلة الاستجابة بالساعات، مهلة الإنجاز بالساعات] */
    const PRIORITY_SLA = array(
        'P0' => array(0.5, 4), 'P1' => array(2, 24), 'P2' => array(4, 48),
        'P3' => array(24, 120), 'P4' => array(72, 0),
    );

    /** أسباب التوقف الموقفة للعدّ وحدها (الورقة 05 — «ما عداها يُسجَّل ولا يوقف») */
    const PAUSE_REASONS = array('force_majeure', 'awaiting_part', 'awaiting_client',
                                'awaiting_higher_approval', 'awaiting_external');

    /** أفعال بلا عكسٍ بطبيعتها (قرار 1 — قرائية لا أثر بيانات يُعكس) */
    const REVERSAL_NA = array(
        'board.drill', 'portal.render', 'ach.range', 'msg.send', 'cert.issue',
        'pwd.change', 'kpi.view', 'search.run', 'export.run', 'report.view',
        'timeline.view', 'help.open', 'notif.read', 'filter.save', 'space.enter', 'profile.view',
    );

    /** انتقالات الحالة: state => [الحالات المقبولة قبلها، من يغيّر، أيشترط سببًا] */
    const TRANSITIONS = array(
        'assigned'            => array('from' => array('draft', 'scheduled', 'returned', 'reopened', 'reassigned'), 'by' => 'assigner', 'reason' => false),
        'accepted'            => array('from' => array('assigned'), 'by' => 'executor', 'reason' => false),
        'in_progress'         => array('from' => array('accepted', 'blocked', 'returned', 'reopened'), 'by' => 'executor', 'reason' => false),
        'blocked'             => array('from' => array('accepted', 'in_progress'), 'by' => 'executor', 'reason' => true),
        'done_pending_verify' => array('from' => array('in_progress', 'accepted'), 'by' => 'executor', 'reason' => false),
        'closed_accepted'     => array('from' => array('done_pending_verify'), 'by' => 'verifier', 'reason' => false),
        'returned'            => array('from' => array('done_pending_verify'), 'by' => 'verifier', 'reason' => true),
        'rejected'            => array('from' => array('assigned'), 'by' => 'executor', 'reason' => true),
        'cancelled'           => array('from' => array('draft', 'scheduled', 'assigned', 'accepted', 'in_progress', 'blocked'), 'by' => 'owner', 'reason' => true),
        'reassigned'          => array('from' => array('assigned', 'accepted', 'in_progress', 'blocked', 'overdue'), 'by' => 'owner', 'reason' => true),
        'reopened'            => array('from' => array('closed_accepted'), 'by' => 'governance', 'reason' => true),
    );

    /* ──────────────── مُحدِّدُ المتحقِّق (WF-04 · تتمّةُ INJ-0486) ──────────── */

    /**
     * متحقِّقُ العنصر — «ولا يشهد أحدٌ على عملِه».
     * ───────────────────────────────────────────────────────────────────────
     * الحارسُ السباعيُّ صار يلزم `verifier_user_id`، **والمُغذّياتُ لم تعرف مَن
     * يتحقّق** — فكانت تُنشئ بلا متحقِّقٍ فتُردُّ 422 صامتةً وتموتُ السلسلة.
     * فالتحديدُ قاعدةٌ واحدةٌ هنا لا اجتهادٌ في كلِّ شاشة:
     *   المالكُ إن غايرَ المنفِّذ ← مديرُ المنفِّذ ← حوكمةُ شركته (15 ثم 9)
     *   ← مُنشئُ العنصرِ إن غايرَه.
     * ◆ ولا يُرجَع المنفِّذُ نفسُه أبدًا؛ وحين ينقطع السُّلَّمُ تُرجَع `null`
     *   فيُرفض الإنشاءُ صراحةً — **ولا يُلفَّق شاهدٌ لتمرير عنصر**.
     *
     * @return int|null
     */
    public static function resolveVerifier(\mysqli $conn, $companyId, $executorUserId, $ownerUserId = 0, $createdBy = 0)
    {
        $exec = (int) $executorUserId;
        if ($exec <= 0) { return null; }
        $owner = (int) $ownerUserId;
        if ($owner > 0 && $owner !== $exec) { return $owner; }
        require_once dirname(__DIR__, 2) . '/../includes/resolve_manager.php';
        $v = ems_resolve_verifier($conn, $exec, (int) $companyId);
        if ($v !== null && (int) $v !== $exec) { return (int) $v; }
        $by = (int) $createdBy;
        return ($by > 0 && $by !== $exec) ? $by : null;
    }

    /* ─────────────────────────── الإنشاء (WF-02) ─────────────────────────── */

    /**
     * إنشاء عنصر عمل — الحارس السباعي: يرفض الناقص ويبيّنه بالاسم.
     * @return array{ok:bool,id?:int,code:int,reason?:string}
     */
    public static function create(\mysqli $conn, array $a)
    {
        $missing = array();
        $need = array(
            'company_id' => 'الكيان', 'source_type' => 'المصدر', 'source_ref' => 'مرجع المصدر',
            'owner_user_id' => 'المالك', 'title' => 'العنوان',
            'due_at' => 'الموعد', 'deliverable' => 'المخرج المطلوب',
        );
        foreach ($need as $k => $label) {
            if (!isset($a[$k]) || trim((string) $a[$k]) === '' || (is_numeric($a[$k]) && intval($a[$k]) === 0 && in_array($k, array('company_id', 'owner_user_id'), true))) {
                $missing[] = $label;
            }
        }
        // المنفِّذ: شخصٌ أو دورٌ مستقبِل — أحدهما (AC-WFM-02)
        if (empty($a['assigned_user_id']) && empty($a['assigned_role_id'])) { $missing[] = 'المنفذ أو الدور المستقبل'; }
        // النطاق: إدارةٌ أو مشروعٌ أو موقع — أحدها
        if (empty($a['org_unit_id']) && empty($a['project_id']) && empty($a['site_id'])) { $missing[] = 'النطاق (إدارة/مشروع/موقع)'; }
        if (!in_array((string) ($a['source_type'] ?? ''), self::SOURCES, true)) { $missing[] = 'مصدر من الأربعة عشر'; }

        /* ══ INJ-0486 · الحارسُ السباعيُّ كان خمسةً ونصفًا ═══════════════════════
             نصُّ القبول: «إنشاءُ عنصرِ عملٍ **بلا `evidence_required` أو بلا
             `verifier_user_id` يُرفض** برسالةٍ تبيّن الناقص؛ **وبمتحقِّقٍ =
             المنفِّذِ يُرفض أيضًا**».
             والمقيسُ قبلَه: الحقلانِ **اختياريانِ حقلًا لا حكمًا** —
             `evidence_required` له افتراضٌ نصيٌّ يملأ الفراغ، و`verifier_user_id`
             يقبل `null`. فعنصرُ عملٍ يُنشأ بلا دليلٍ مطلوبٍ وبلا متحقِّقٍ يُقفل
             بشهادةِ منفِّذه وحدَه — وهو نقضُ «اليدِ الثانية» في أصلِه.
           ◆ **والمتحقِّقُ غيرُ المنفِّذ**: من ينفّذ لا يشهد على نفسِه. وهذا
             الشرطُ لا يُقاس بحقلٍ بل بمقارنةِ اثنين. */
        if (!isset($a['evidence_required']) || trim((string) $a['evidence_required']) === '') {
            $missing[] = 'الدليل المطلوب (evidence_required)';
        }
        if (empty($a['verifier_user_id']) || (int) $a['verifier_user_id'] <= 0) {
            $missing[] = 'المتحقق (verifier_user_id)';
        }
        if ($missing) {
            return array('ok' => false, 'code' => 422,
                'reason' => 'لا عنصر بلا سبعة (WF-02) — الناقص: ' . implode(' · ', $missing));
        }
        $__exec = !empty($a['assigned_user_id']) ? (int) $a['assigned_user_id'] : 0;
        if ($__exec > 0 && (int) $a['verifier_user_id'] === $__exec) {
            return array('ok' => false, 'code' => 422,
                'reason' => 'WF-422-SELFVERIFY: المتحقق هو المنفذ نفسه — ولا يشهد أحد على عمله');
        }

        // WF-08: تكليفٌ منتهٍ لا يولّد — إن جاء العنصر بمرجع تفويض يُفحص سريانه
        if (!empty($a['delegation_ref']) && !self::isDelegationLive($conn, $a['delegation_ref'])) {
            return array('ok' => false, 'code' => 422,
                'reason' => 'التفويض ' . $a['delegation_ref'] . ' منتهٍ — انتهاء التكليف يوقف التوليد (WF-08)');
        }

        $priority = isset(self::PRIORITY_SLA[$a['priority'] ?? '']) ? (string) $a['priority'] : 'P3';
        $sla = self::PRIORITY_SLA[$priority];
        $status = !empty($a['assigned_user_id']) ? 'assigned' : (($a['status'] ?? '') === 'scheduled' ? 'scheduled' : 'draft');

        $st = $conn->prepare("INSERT INTO work_items
            (company_id, item_type, title, details, source_type, source_ref, source_screen, action_code,
             event_ref, org_unit_id, project_id, site_id, assigned_person_id, assigned_role_id, due_at,
             status, owner_user_id, assigned_user_id, deliverable, evidence_required, verifier_user_id,
             priority, response_due_at, delegation_ref, parent_ref, created_by, created_capacity)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $co = intval($a['company_id']);
        $itemType = (isset($a['item_type']) && in_array($a['item_type'], array('task', 'assignment'), true))
                  ? (string) $a['item_type'] : 'task';
        $title = mb_substr(trim((string) $a['title']), 0, 300);
        $details = isset($a['details']) ? (string) $a['details'] : null;
        $srcT = (string) $a['source_type'];
        $srcR = mb_substr((string) $a['source_ref'], 0, 120);
        $srcS = isset($a['source_screen']) ? mb_substr((string) $a['source_screen'], 0, 120) : null;
        $act = isset($a['action_code']) ? (string) $a['action_code'] : null;
        $evR = isset($a['event_ref']) ? (string) $a['event_ref'] : null;
        $org = !empty($a['org_unit_id']) ? intval($a['org_unit_id']) : null;
        $prj = !empty($a['project_id']) ? intval($a['project_id']) : null;
        $site = !empty($a['site_id']) ? intval($a['site_id']) : null;
        $person = !empty($a['assigned_person_id']) ? intval($a['assigned_person_id']) : null;
        $roleId = !empty($a['assigned_role_id']) ? intval($a['assigned_role_id']) : null;
        $due = (string) $a['due_at'];
        $owner = intval($a['owner_user_id']);
        $assignee = !empty($a['assigned_user_id']) ? intval($a['assigned_user_id']) : null;
        $deliv = mb_substr((string) $a['deliverable'], 0, 300);
        $evReq = mb_substr((string) ($a['evidence_required'] ?? 'أثر الفعل في سجل التدقيق'), 0, 200);
        $verifier = !empty($a['verifier_user_id']) ? intval($a['verifier_user_id']) : null;
        $respDue = ($sla[0] > 0 && $status === 'assigned') ? date('Y-m-d H:i:s', time() + intval($sla[0] * 3600)) : null;
        $dref = isset($a['delegation_ref']) && $a['delegation_ref'] !== '' ? (string) $a['delegation_ref'] : null;
        $pref = isset($a['parent_ref']) && $a['parent_ref'] !== '' ? (string) $a['parent_ref'] : null;
        $by = intval($a['created_by'] ?? 0);
        $cap = isset($a['created_capacity']) ? (string) $a['created_capacity'] : null;

        // بناءُ الأنواع مقطعًا مقطعًا — عدُّ 27 وسيطًا يدويًّا مصيدةُ أخطاءٍ صامتة
        $types = 'i' . str_repeat('s', 8)   // co · (itemType title details srcT srcR srcS act evR)
               . str_repeat('i', 5)         // org prj site person roleId
               . 'ss' . 'ii' . 'ss'         // due status · owner assignee · deliv evReq
               . 'i' . 'ssss' . 'i' . 's';  // verifier · priority respDue dref pref · by · cap
        $st->bind_param($types,
            $co, $itemType, $title, $details, $srcT, $srcR, $srcS, $act, $evR,
            $org, $prj, $site, $person, $roleId, $due, $status, $owner, $assignee,
            $deliv, $evReq, $verifier, $priority, $respDue, $dref, $pref, $by, $cap);
        if (!$st->execute()) { $e = $st->error; $st->close(); return array('ok' => false, 'code' => 422, 'reason' => $e); }
        $id = $st->insert_id;
        $st->close();

        if ($assignee) {
            self::logAssignment($conn, $co, $id, 'assign', null, $assignee, null, $by);
            // `no_notify`: العنصرُ المولَّدُ **عن تنبيهٍ** لا يُخطِر مرتين —
            // الإخطارُ وقع سلفًا وهذه المهمةُ أثرُه لا حدثٌ جديد.
            if (empty($a['no_notify'])) {
                self::notifyUser($conn, $co, $assignee, 'مهمة مسندة إليك', $title,
                    'Portal/my_tasks.php?item=' . $id, false, $by);
            }
        }
        return array('ok' => true, 'code' => 200, 'id' => $id, 'status' => $status);
    }

    /* ───────────────────────── الانتقالات (الورقة 02) ───────────────────── */

    /**
     * انتقال حالة — مخوَّلٌ وشرطٌ وسببٌ وأثرُ عدٍّ وإخطار.
     * @return array{ok:bool,code:int,reason?:string}
     */
    public static function transition(\mysqli $conn, $itemId, $to, $actorUserId, $reason = '', array $opts = array())
    {
        $itemId = intval($itemId);
        $actor = intval($actorUserId);
        if (!isset(self::TRANSITIONS[$to])) { return array('ok' => false, 'code' => 422, 'reason' => 'انتقال غير معرف: ' . $to); }
        $t = self::TRANSITIONS[$to];

        $it = self::fetch($conn, $itemId);
        if (!$it) { return array('ok' => false, 'code' => 404, 'reason' => 'العنصر غير موجود'); }
        $fromEff = ($it['status'] === 'overdue' && !in_array('overdue', $t['from'], true) && isset($opts['pre_overdue']))
                 ? (string) $opts['pre_overdue'] : $it['status'];
        if (!in_array($fromEff, $t['from'], true) && !in_array($it['status'], $t['from'], true)) {
            return array('ok' => false, 'code' => 409, 'reason' => 'لا انتقال من ' . $it['status'] . ' إلى ' . $to);
        }
        if ($t['reason'] && trim($reason) === '') {
            return array('ok' => false, 'code' => 422, 'reason' => 'السبب إلزامي لهذا الانتقال');
        }

        // المخوَّل (الورقة 02) — والحارس هنا خادميٌّ لا زينة واجهة
        $owner = intval($it['owner_user_id']);
        $executor = intval($it['assigned_user_id']);
        $verifier = intval($it['verifier_user_id']);
        $authorized = false;
        switch ($t['by']) {
            case 'executor':  $authorized = ($actor === $executor); break;
            case 'owner':     $authorized = ($actor === $owner || $actor === intval($it['created_by'])); break;
            case 'assigner':  $authorized = ($actor === $owner || $actor === intval($it['created_by'])); break;
            case 'verifier':
                // WF-04: لا يُغلق أحدٌ مهمتَه — المتحقِّق غيرُ المنفِّذ حكمًا
                $authorized = ($verifier > 0) ? ($actor === $verifier)
                                              : ($actor !== $executor && ($actor === $owner || $actor === intval($it['created_by'])));
                if ($actor === $executor) { $authorized = false; }
                break;
            case 'governance': $authorized = !empty($opts['governance_ok']) || $actor === $owner; break;
        }
        if (!$authorized && empty($opts['system'])) {
            return array('ok' => false, 'code' => 403, 'reason' => 'غير مخول بهذا الانتقال (' . $t['by'] . ')');
        }
        if ($to === 'blocked' && !in_array($reason, self::PAUSE_REASONS, true) && empty($opts['reason_listed'])) {
            // سببٌ خارج القائمة: يُسجَّل ولا يوقف العدّ (الورقة 05)
            $opts['no_pause'] = true;
        }

        $sets = array("status = ?", "status_reason = ?");
        $vals = array($to, mb_substr($reason, 0, 300));
        $types = 'ss';
        $now = date('Y-m-d H:i:s');
        if ($to === 'accepted')            { $sets[] = "accepted_at = ?"; $vals[] = $now; $types .= 's'; }
        if ($to === 'done_pending_verify') { $sets[] = "completed_at = ?"; $vals[] = $now; $types .= 's'; }
        if ($to === 'closed_accepted')     { $sets[] = "closed_at = ?"; $vals[] = $now; $types .= 's';
                                             $sets[] = "approved_by = ?"; $vals[] = $actor; $types .= 'i';
                                             $sets[] = "approved_at = ?"; $vals[] = $now; $types .= 's'; }
        if ($to === 'blocked' && empty($opts['no_pause'])) { $sets[] = "sla_paused_at = ?"; $vals[] = $now; $types .= 's';
                                             $sets[] = "sla_pause_reason = ?"; $vals[] = $reason; $types .= 's'; }
        if ($to === 'in_progress')         { $sets[] = "sla_paused_at = NULL"; $sets[] = "sla_pause_reason = NULL"; }
        $vals[] = $itemId; $types .= 'i';

        $st = $conn->prepare("UPDATE work_items SET " . implode(', ', $sets) . " WHERE id = ?");
        $st->bind_param($types, ...$vals);
        if (!$st->execute()) { $e = $st->error; $st->close(); return array('ok' => false, 'code' => 422, 'reason' => $e); }
        $st->close();

        self::afterTransition($conn, $it, $to, $actor, $reason, $opts);
        return array('ok' => true, 'code' => 200);
    }

    /** إخطارات ما بعد الانتقال + اشتقاق/عكس الإنجاز (الورقتان 02 و11) */
    private static function afterTransition(\mysqli $conn, array $it, $to, $actor, $reason, array $opts)
    {
        $co = intval($it['company_id']);
        $id = intval($it['id']);
        $title = (string) $it['title'];
        $link = 'Portal/my_tasks.php?item=' . $id;
        $owner = intval($it['owner_user_id']);
        $executor = intval($it['assigned_user_id']);
        $verifier = intval($it['verifier_user_id']);

        switch ($to) {
            case 'accepted':
                self::notifyUser($conn, $co, $owner, 'تم الاستلام', $title, $link, false, $actor); break;
            case 'blocked':
                self::notifyUser($conn, $co, $owner, 'مهمة متعطلة تحتاج تدخلا', $title . ' — ' . $reason, $link, true, $actor); break;
            case 'done_pending_verify':
                $target = $verifier ?: $owner;
                if ($target && $target !== $executor) {
                    self::notifyUser($conn, $co, $target, 'مهمة تنتظر تحققك', $title, $link, true, $actor);
                }
                break;
            case 'closed_accepted':
                self::notifyUser($conn, $co, $executor, 'اكتملت المهمة ونسب الإنجاز', $title, $link, false, $actor);
                require_once __DIR__ . '/AchievementService.php';
                AchievementService::deriveFromTask($conn, $it, $actor);
                break;
            case 'returned':
                self::notifyUser($conn, $co, $executor, 'مهمة أعيدت إليك بسبب: ' . $reason, $title, $link, true, $actor); break;
            case 'rejected':
                self::notifyUser($conn, $co, $owner, 'رفض المنفذ المهمة: ' . $reason, $title, $link, true, $actor); break;
            case 'cancelled':
                if ($executor) { self::notifyUser($conn, $co, $executor, 'ألغيت المهمة: ' . $reason, $title, $link, false, $actor); }
                break;
            case 'reopened':
                require_once __DIR__ . '/AchievementService.php';
                AchievementService::reverseForSource($conn, $co, 'task', (string) $id, 'أعيد فتح المهمة: ' . $reason, $actor);
                self::notifyUser($conn, $co, $executor, 'أعيد فتح المهمة', $title, $link, true, $actor);
                if ($owner !== $executor) { self::notifyUser($conn, $co, $owner, 'أعيد فتح المهمة', $title, $link, false, $actor); }
                break;
        }
    }

    /* ───────────────────── الإسناد والتفويض (الورقة 08) ─────────────────── */

    public static function reassign(\mysqli $conn, $itemId, $toUserId, $actor, $reason)
    {
        $it = self::fetch($conn, intval($itemId));
        if (!$it) { return array('ok' => false, 'code' => 404, 'reason' => 'العنصر غير موجود'); }
        if (trim($reason) === '') { return array('ok' => false, 'code' => 422, 'reason' => 'سبب إعادة الإسناد إلزامي'); }
        $actor = intval($actor);
        if ($actor !== intval($it['owner_user_id']) && $actor !== intval($it['created_by'])) {
            return array('ok' => false, 'code' => 403, 'reason' => 'إعادة الإسناد للمكلف أو المالك');
        }
        $to = intval($toUserId);
        $from = intval($it['assigned_user_id']);
        $st = $conn->prepare("UPDATE work_items SET assigned_user_id = ?, status = 'assigned', status_reason = ? WHERE id = ?");
        $r = mb_substr('أعيد إسنادها: ' . $reason, 0, 300);
        $iid = intval($it['id']);
        $st->bind_param('isi', $to, $r, $iid);
        $st->execute();
        $st->close();
        self::logAssignment($conn, intval($it['company_id']), $iid, 'reassign', $from, $to, $reason, $actor);
        // العدُّ يستمر ولا يُصفَّر (الورقة 02) — لا مساس بأعمدة العدّ
        self::notifyUser($conn, intval($it['company_id']), $to, 'نقل مهمة إليك', (string) $it['title'], 'Portal/my_tasks.php?item=' . $iid, true, $actor);
        if ($from) { self::notifyUser($conn, intval($it['company_id']), $from, 'نقلت مهمة منك', (string) $it['title'], 'Portal/my_tasks.php?item=' . $iid, false, $actor); }
        return array('ok' => true, 'code' => 200);
    }

    /** سريان تفويض/إنابة — WF-08 */
    public static function isDelegationLive(\mysqli $conn, $ref)
    {
        $ref = trim((string) $ref);
        if ($ref === '') { return false; }
        if (ctype_digit($ref)) {
            $st = $conn->prepare("SELECT id FROM work_delegations
                                   WHERE id = ? AND status = 'active' AND NOW() BETWEEN starts_at AND ends_at LIMIT 1");
            $i = intval($ref);
            $st->bind_param('i', $i);
        } else {
            $st = $conn->prepare("SELECT id FROM work_delegations
                                   WHERE scope_ref = ? AND status = 'active' AND NOW() BETWEEN starts_at AND ends_at LIMIT 1");
            $st->bind_param('s', $ref);
        }
        $st->execute();
        $ok = (bool) $st->get_result()->fetch_assoc();
        $st->close();
        return $ok;
    }

    /* ───────────── المهل والتصعيد (الورقة 05 · AC-WFM-09) ───────────────── */

    /**
     * كنسُ المهل: يوسم المتأخر overdue ويصعّده درجةً لمدير المنفِّذ —
     * صفرُ مهمةٍ متأخرةٍ بلا تصعيد. يُستدعى من كرون WFM.
     */
    public static function sweepSla(\mysqli $conn)
    {
        require_once dirname(__DIR__, 2) . '/../includes/resolve_manager.php';
        $out = array('overdue' => 0, 'escalated' => 0);
        $r = mysqli_query($conn,
            "SELECT id, company_id, title, assigned_user_id, owner_user_id, escalation_level, status
               FROM work_items
              WHERE status IN ('assigned','accepted','in_progress')
                AND due_at IS NOT NULL AND due_at < NOW()
                AND sla_paused_at IS NULL
              LIMIT 500");
        while ($r && ($it = mysqli_fetch_assoc($r))) {
            $id = intval($it['id']);
            $co = intval($it['company_id']);
            mysqli_query($conn, "UPDATE work_items SET status = 'overdue', status_reason = CONCAT('تجاوز الموعد — كان ', status) WHERE id = {$id} AND status IN ('assigned','accepted','in_progress')");
            if (mysqli_affected_rows($conn) < 1) { continue; }
            $out['overdue']++;
            $target = ems_resolve_manager($conn, intval($it['assigned_user_id']));
            if ($target === null) { $target = intval($it['owner_user_id']); }
            $lvl = intval($it['escalation_level']) + 1;
            $st = $conn->prepare("INSERT INTO work_escalations (company_id, item_kind, item_ref, from_user_id, to_user_id, level, reason, note)
                                  VALUES (?,?,?,?,?,?,?,?)");
            $kind = 'work_item';
            $fromU = intval($it['assigned_user_id']);
            $why = 'sla_completion';
            $note = mb_substr((string) $it['title'], 0, 300);
            $st->bind_param('isiiiiss', $co, $kind, $id, $fromU, $target, $lvl, $why, $note);
            $st->execute();
            $st->close();
            mysqli_query($conn, "UPDATE work_items SET escalation_level = {$lvl} WHERE id = {$id}");
            self::notifyUser($conn, $co, $target, 'تجاوز يحتاج تصعيدا', (string) $it['title'],
                'Portal/my_tasks.php?item=' . $id, true, 0);
            $out['escalated']++;
        }
        return $out;
    }

    /* ───────────────────── تفسير الظهور (WFM-120..126) ──────────────────── */

    /**
     * السلسلة الخماسية: أصلُ العنصر ← قاعدةُ التوجيه ← الدور ← النطاق ← التفويض.
     * سلسلةٌ ناقصة ⇒ 'complete'=false — والعنصر يُحجب (AC-WFM-13).
     */
    public static function explainAppearance(\mysqli $conn, $itemId, $userId)
    {
        $it = self::fetch($conn, intval($itemId));
        if (!$it) { return array('complete' => false, 'steps' => array(), 'reason' => 'العنصر غير موجود'); }
        $steps = array();
        $complete = true;

        // ① الأصل
        $src = (string) $it['source_type'];
        $screen = (string) ($it['source_screen'] ?: '—');
        $ok1 = in_array($src, self::SOURCES, true) && (string) $it['source_ref'] !== '';
        $steps[] = array('q' => 'ما أصل هذا العنصر؟',
            'a' => $ok1 ? "نشأ من المصدر {$src} (مرجع {$it['source_ref']}) في شاشة «{$screen}»" : 'بلا مصدر — يحجب ويبلغ',
            'ok' => $ok1);
        $complete = $complete && $ok1;

        // ② قاعدة التوجيه
        $rule = null;
        $st = $conn->prepare("SELECT rule_text FROM request_routes WHERE item_kind = 'nav_action' AND trigger_key IN (?, '*') AND active = 1 ORDER BY (trigger_key = '*') LIMIT 1");
        $ac = (string) ($it['action_code'] ?: $src);
        $st->bind_param('s', $ac);
        $st->execute();
        $rr = $st->get_result()->fetch_assoc();
        $st->close();
        if ($rr) { $rule = (string) $rr['rule_text']; }
        $steps[] = array('q' => 'بأي قاعدة وجه إلي؟',
            'a' => $rule !== null ? 'وجه بقاعدة: ' . $rule : 'توجيه يدوي مسجل بمبرره (استثناء WF-07)',
            'ok' => true);

        // ③ الدور
        $uid = intval($userId);
        $st = $conn->prepare("SELECT u.role, r.name AS role_name FROM users u LEFT JOIN roles r ON r.id = u.role WHERE u.id = ? LIMIT 1");
        $st->bind_param('i', $uid);
        $st->execute();
        $u = $st->get_result()->fetch_assoc();
        $st->close();
        $ok3 = (bool) $u;
        $steps[] = array('q' => 'بأي دور أستقبله؟',
            'a' => $ok3 ? 'لأنك تحمل دور «' . ($u['role_name'] ?: $u['role']) . '»' : 'لا دور مطابقا — يحجب ويراجع', 'ok' => $ok3);
        $complete = $complete && $ok3;

        // ④ النطاق
        $scope = array();
        if (!empty($it['org_unit_id'])) { $scope[] = 'إدارة ' . $it['org_unit_id']; }
        if (!empty($it['project_id'])) { $scope[] = 'مشروع ' . $it['project_id']; }
        if (!empty($it['site_id'])) { $scope[] = 'موقع ' . $it['site_id']; }
        $inScope = ($uid === intval($it['assigned_user_id']) || $uid === intval($it['owner_user_id'])
                 || $uid === intval($it['verifier_user_id']) || $uid === intval($it['created_by']));
        $steps[] = array('q' => 'ما نطاقي فيه؟',
            'a' => $inScope ? ('نطاقك: ' . ($scope ? implode(' · ', $scope) : 'شخصي — أنت طرفه')) : 'خارج النطاق — يحجب فورا',
            'ok' => $inScope);
        $complete = $complete && $inScope;

        // ⑤ التفويض
        $dref = (string) ($it['delegation_ref'] ?? '');
        if ($dref === '') {
            $steps[] = array('q' => 'أهو بتفويض أم أصالة؟', 'a' => 'أصالة', 'ok' => true);
        } else {
            $live = self::isDelegationLive($conn, $dref);
            $steps[] = array('q' => 'أهو بتفويض أم أصالة؟',
                'a' => $live ? 'بتفويض نافذ (' . $dref . ')' : 'تفويض منته — يعاد للأصل وينبه', 'ok' => $live);
            $complete = $complete && $live;
        }
        return array('complete' => $complete, 'steps' => $steps);
    }

    /* ───────────── الاشتقاق من الفعل (WF-10 · WFM-114/115/116) ───────────── */

    /**
     * تصنيف نص الفعل بقواعد الوثيقة الثابتة — «يُستنبط لا يُجتهد»:
     * اعتماد/توقيع/إقرار ⇒ موافقة · طلب/تقديم ⇒ طلب · بلاغ/تذكرة ⇒ بلاغ ·
     * تنبيه/إشعار ⇒ تنبيه · وما عداه مهمة. والأولوية: توقف/منع/إيقاف ⇒ P1 ·
     * اعتماد/إقفال/نشر ⇒ P2 · وما عداه P3.
     */
    public static function classifyAction($actionText)
    {
        $t = (string) $actionText;
        $type = 'task';
        if (preg_match('/اعتماد|توقيع|إقرار|موافق/u', $t)) { $type = 'approval'; }
        elseif (preg_match('/طلب|تقديم/u', $t)) { $type = 'request'; }
        elseif (preg_match('/بلاغ|تذكرة/u', $t)) { $type = 'ticket'; }
        elseif (preg_match('/تنبيه|إشعار/u', $t)) { $type = 'notification'; }
        $priority = 'P3';
        if (preg_match('/توقف|منع|إيقاف|طارئ/u', $t)) { $priority = 'P1'; }
        elseif (preg_match('/اعتماد|إقفال|نشر|توقيع/u', $t)) { $priority = 'P2'; }
        return array('type' => $type, 'priority' => $priority);
    }

    /**
     * اشتقاق عنصر عملٍ من فعل NAV-09 (WF-10: «لا يُخترع عنصرٌ بلا فعلٍ يقابله»):
     * يقرأ صفَّ الفعل من nav09_action_map (الشاشة · الملف · الفعل) ويستنبط
     * النوعَ والأولوية، ودليلُ الإكمال من مستند المرحلة وإلا أثرُ الفعل في
     * سجل التدقيق (WFM-116). عطالة: (source_type,source_ref) القائم لا يتكرر.
     */
    public static function fromNavAction(\mysqli $conn, $actionCode, array $a)
    {
        $actionCode = trim((string) $actionCode);
        $st = $conn->prepare("SELECT canonical_code, label_ar, screen_title, canonical_file
                                FROM nav09_action_map WHERE canonical_code = ? LIMIT 1");
        $st->bind_param('s', $actionCode);
        $st->execute();
        $act = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$act) {
            return array('ok' => false, 'code' => 422,
                'reason' => 'فعل خارج خريطة NAV-09: ' . $actionCode . ' — لا يخترع عنصر (WF-10)');
        }
        $cls = self::classifyAction((string) $act['label_ar']);
        // عطالة الاشتقاق: العنصر القائم لنفس الفعل والمرجع يُعاد مرجعُه
        $srcRef = (string) ($a['source_ref'] ?? '');
        $stq = $conn->prepare("SELECT id FROM work_items
                                WHERE source_type = 'SRC-03' AND action_code = ? AND source_ref = ?
                                  AND status NOT IN ('cancelled') LIMIT 1");
        $stq->bind_param('ss', $actionCode, $srcRef);
        $stq->execute();
        $ex = $stq->get_result()->fetch_assoc();
        $stq->close();
        if ($ex) { return array('ok' => true, 'code' => 200, 'id' => intval($ex['id']), 'duplicate' => true); }

        /* ══ نقاءُ لغةِ الواجهة (‏REPAIR01 · W06 §٤-٤) ═══════════════════════
           كان العنوانُ يُركَّب `label_ar . ' — ' . screen_title` — **فكلُّ فعلٍ
           في النظامِ يولّد نصًّا مخالفًا** بشرطةِ ربطٍ من مصدرٍ مشكول، ويُقاس
           الدَّينُ في ١٧٦٤ عنوانًا مولَّدًا سلفًا. ورُفعت الشرطةُ ورُفع الضمُّ:
           **الشاشةُ تُحفَظ في `source_screen`** حيث كانت تُحفَظ، فلا تُفقَد
           معلومةٌ — والاسمُ يُقرأ من **السجلِّ المركزيِّ** لا من الجدولِ رأسًا،
           فلا يظهر المصطلحُ بصيغتين في شاشتين. والغائبُ عن السجلِّ يُقيَّد
           رفضُه ويُعاد مُنقّى — فلا تنكسر شاشةٌ ولا يمرُّ اسمٌ بلا أثر. */
        $titleAr = \App\Services\Ui\UiLabelRegistry::label(
            $conn, 'action:' . $actionCode, (string) $act['label_ar']);
        if ($titleAr === '') { $titleAr = \App\Services\Ui\UiPurity::purifyGenerated((string) $act['label_ar']); }

        return self::create($conn, array_merge($a, array(
            'source_type' => $a['source_type'] ?? 'SRC-03',
            'source_screen' => (string) $act['canonical_file'],
            'action_code' => $actionCode,
            'title' => $a['title'] ?? $titleAr,
            'priority' => $a['priority'] ?? $cls['priority'],
            'evidence_required' => $a['evidence_required'] ?? 'أثر الفعل في سجل التدقيق (WFM-116)',
        )));
    }

    /* ─────────────────────────── مساعدات داخلية ─────────────────────────── */

    public static function fetch(\mysqli $conn, $id)
    {
        $st = $conn->prepare("SELECT * FROM work_items WHERE id = ? LIMIT 1");
        $id = intval($id);
        $st->bind_param('i', $id);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        return $r ?: null;
    }

    private static function logAssignment(\mysqli $conn, $co, $itemId, $kind, $from, $to, $reason, $by)
    {
        $st = $conn->prepare("INSERT INTO task_assignments (company_id, item_id, kind, from_user_id, to_user_id, reason, created_by)
                              VALUES (?,?,?,?,?,?,?)");
        $reason = $reason !== null ? mb_substr($reason, 0, 300) : null;
        $st->bind_param('iisiisi', $co, $itemId, $kind, $from, $to, $reason, $by);
        $st->execute();
        $st->close();
    }

    /**
     * تنبيه شخصي (WF-06): requires_action يولّد مهمةً مرتبطةً — التنبيهُ الذي
     * يتطلب فعلًا ولا يتحول مهمةً خرقٌ (AC-WFM-08). التوليد هنا للربط لا للتنفيذ.
     */
    public static function notifyUser(\mysqli $conn, $co, $userId, $title, $body, $link, $requiresAction, $by)
    {
        $userId = intval($userId);
        if ($userId <= 0) { return null; }
        $st = $conn->prepare("INSERT INTO personal_notifications
            (company_id, user_id, kind, title, body, link, requires_action, created_by)
            VALUES (?,?,?,?,?,?,?,?)");
        $kind = $requiresAction ? 'action' : 'info';
        $title = mb_substr((string) $title, 0, 300);
        $body = mb_substr((string) $body, 0, 600);
        $link = mb_substr((string) $link, 0, 300);
        $ra = $requiresAction ? 1 : 0;
        $by = intval($by);
        $st->bind_param('iissssii', $co, $userId, $kind, $title, $body, $link, $ra, $by);
        $st->execute();
        $nid = $st->insert_id;
        $st->close();

        /* ══ INJ-0404 · «يتطلب فعلًا» يولّد مهمتَه في اللحظةِ نفسِها ═══════════════
             نصُّ القبول: «كلُّ صفٍّ في `personal_notifications` بـ`requires_action=1`
             يحمل `task_item_id` **غيرَ فارغٍ خلال الثانيةِ نفسِها**، ويظهر عنصرُه
             في `Portal/my_tasks.php`».
             والمقيسُ قبلَه: العمودُ يُكتب ١ **ولا مهمةَ تُولد** — والتعليقُ فوقَ
             الدالّةِ يقول «التوليدُ هنا للربط لا للتنفيذ»، أي أنَّ الوعدَ مكتوبٌ
             والفعلُ غائب. فالمستخدمُ يرى تنبيهًا «يتطلب فعلًا» ولا يجد في مهامِّه
             شيئًا — **وشاشةٌ تعرض حقلًا لا يُكتب أبدًا تكذب**.
           ◆ والربطُ **في اللحظةِ نفسِها**: تُنشأ المهمةُ ثم يُكتب مفتاحُها في
             التنبيه — فلا تنبيهَ يتطلب فعلًا بلا مهمةٍ ولو لثانية.
           ◆ وتعثّرُ التوليدِ **لا يُسقط التنبيه**: الإخطارُ وقع، والمهمةُ أثرُه —
             فيُسجَّل التعثّرُ ولا يُبتلع صامتًا. */
        if ($ra === 1 && $nid > 0) {
            try {
                $taskId = self::createTaskForNotification($conn, $co, $userId, $title, $body, $link, $by, (int) $nid);
                if ($taskId > 0) {
                    $up = $conn->prepare('UPDATE personal_notifications SET task_item_id = ? WHERE id = ?');
                    if ($up) {
                        $up->bind_param('ii', $taskId, $nid);
                        $up->execute();
                        $up->close();
                    }
                }
            } catch (\Throwable $t) {
                error_log('notify task link failed #' . $nid . ': ' . $t->getMessage());
            }
        }
        return $nid;
    }

    /**
     * مهمةُ عنصرِ عملٍ عن تنبيهٍ يتطلب فعلًا — ⇐ INJ-0404.
     * ◆ **ولا تُنشأ مهمتان لتنبيهٍ واحد**: العطالةُ بمرجعِ التنبيهِ في `source_ref`.
     */
    private static function createTaskForNotification(\mysqli $conn, $co, $userId, $title, $body, $link, $by, $notifId)
    {
        $ref = 'notification#' . (int) $notifId;
        $q = $conn->prepare("SELECT id FROM work_items WHERE company_id = ? AND source_ref = ? LIMIT 1");
        if ($q) {
            $co2 = (int) $co;
            $q->bind_param('is', $co2, $ref);
            $q->execute();
            $ex = $q->get_result()->fetch_row();
            $q->close();
            if ($ex) { return (int) $ex[0]; }
        }
        /* ◆ **ولا شكلَ ثانيًا للعنصر** — كان هذا الموضعُ يُدرج في `work_items`
             رأسًا **متجاوزًا `create()`** بـ`source_type='notification'`
             و`status='open'`: الأولُ خارجَ الأربعةَ عشرَ والثاني خارجَ الخمسَ
             عشرةَ. فالصفُّ يُكتب ولا يطابق **عرضًا واحدًا** من عروضِ «مهامي» —
             مهمةٌ قائمةٌ لا يراها صاحبُها أبدًا، وهو أسوأُ من ألّا تُنشأ.
             والعلّةُ في التجاوزِ نفسِه: ما لا يمرُّ بالحارسِ لا يلزمه شكلٌ.
           ◆ فالتوليدُ يمرُّ بالحارسِ السباعيِّ كغيرِه: SRC-14 «طارئةٌ تشغيلية»
             — وهو مصدرُ التنبيهِ المحوَّلِ نفسُه في `Portal/notifications.php`
             فلا مصدرانِ لأصلٍ واحد — بحالةٍ `assigned` ومتحقِّقٍ بالقاعدة. */
        $res = self::create($conn, array(
            'company_id' => (int) $co, 'source_type' => 'SRC-14', 'source_ref' => $ref,
            'source_screen' => mb_substr((string) $link, 0, 120),
            'owner_user_id' => (int) $userId, 'assigned_user_id' => (int) $userId,
            'verifier_user_id' => self::resolveVerifier($conn, $co, $userId, 0, $by),
            'org_unit_id' => 1,
            'title' => mb_substr((string) $title, 0, 200),
            'details' => mb_substr((string) $body, 0, 600),
            'deliverable' => mb_substr('تنفيذ ما يطلبه الإخطار: ' . (string) $title, 0, 300),
            'evidence_required' => 'أثر الفعل المطلوب في سجل التدقيق',
            'priority' => 'P3',
            'due_at' => date('Y-m-d H:i:s', time() + 172800),
            'created_by' => (int) $by, 'no_notify' => true,
        ));
        if (empty($res['ok'])) {
            error_log('notification task rejected #' . (int) $notifId . ': ' . (string) ($res['reason'] ?? ''));
            return 0;
        }
        return (int) $res['id'];
    }
}
