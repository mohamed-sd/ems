<?php
/**
 * PeopleCycleService — دورةُ الموظّفِ ودورةُ التذكرةِ بأطرافِها الأربعة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأطرافُ الأربعةُ لا تُكتب إلّا من بابٍ واحد** (§28): `recordParty` هي
 *   المنفذُ الوحيدُ إلى `tkt_party`، وكلُّ دورٍ يدخل منها بمفتاحِ فاعلِه لا
 *   باسمِه. **والدمجُ يُردُّ في القاعدةِ لا في النيّة**: `uq_tkp_actor` يمنع أن
 *   يشغل فاعلٌ واحدٌ دورَين في بلاغٍ واحد، فالخدمةُ تترجم الردَّ إلى رمزٍ
 *   يُقرأ (`MERGED_PARTY_ACTOR`) ولا تبتلعه.
 *
 * ◆ **والبلاغاتُ تملك دورةَ التذكرةِ ولا تملك تنفيذَ الحلّ** (§9): `route` و
 *   `assign` و`recordAction` و`resolve` كلُّها تردُّ `DEP-10` برمزٍ خاصٍّ بها،
 *   ومقابلُها `takeOwnership` **لا تقبل غيرَ `DEP-10`**. فالملكيّتانِ مفصولتانِ
 *   في الاتّجاهَين لا في اتّجاهٍ واحد.
 *
 * ◆ **ولا إغلاقَ بلا تحقّقٍ ولا تحقّقَ من المنفِّذِ نفسِه**: `verify` تردُّ
 *   `SAME_ACTOR_RESOLVE_AND_VERIFY` و`close` تردُّ `CLOSE_WITHOUT_VERIFICATION`.
 *   **والإغلاقُ الآليُّ بنافذةٍ ممتنعٌ للحرِج** (‏جوابُ `DEC-OPEN-05`).
 *
 * ◆ **والقضيةُ التأديبيّةُ ثلاثُ أيدٍ لا يدٌ واحدة**: من بلَّغ لا يحقّق، ومن
 *   حقّق لا يقرّر، **والخصمُ لا يُرفَع إلّا بمرجعِ قرارٍ قائمٍ** — فشاشةُ
 *   القضيّةِ تنتج قرارًا وشاشةُ الخصمِ تستهلكه (`HR-17` مقابل `HR-18`).
 *
 * ◆ **والحدثُ يُنشَر من الجذرِ المحايدِ وحدَه** (`ADR-15`): `EventPublisher`
 *   ولا كتابةَ حدثٍ خارجَه.
 *
 * ⛔ **ولا عتبةٌ رقميّةٌ في هذا الملفّ** — كلُّها في `repair01_w13_thresholds`.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\People;

use App\Core\TenantDb;

class PeopleCycleService
{
    const CRP_DEPT = 'DEP-10';

    const ROLE_REPORTER   = 'REPORTER';
    const ROLE_SUBJECT    = 'SUBJECT';
    const ROLE_OWNER      = 'TICKET_OWNER';
    const ROLE_RESOLUTION = 'RESOLUTION_OWNER';

    private static $company = 0;
    private static $eventConn = null;
    private static $thConn = null;
    private static $th = null;

    public static function setCompany($id) { self::$company = (int) $id; }
    public static function setEventConnection(\mysqli $conn) { self::$eventConn = $conn; }
    public static function setThresholdConnection(\mysqli $conn) { self::$thConn = $conn; self::$th = null; }

    /** العتبةُ من السجلِّ — ولا رقمَ مكتوبٌ في هذا الملفّ */
    public static function threshold($key)
    {
        if (self::$th === null) {
            self::$th = array();
            $c = self::$thConn;
            if ($c instanceof \mysqli) {
                $r = @$c->query("SELECT threshold_key, value_num FROM repair01_w13_thresholds");
                while ($r && $x = $r->fetch_assoc()) { self::$th[$x['threshold_key']] = (float) $x['value_num']; }
            }
        }
        return isset(self::$th[$key]) ? self::$th[$key] : null;
    }

    private static function fail($code, $detail = '')
    {
        return array('ok' => false, 'code' => $code, 'detail' => $detail);
    }
    private static function done(array $data = array())
    {
        return array_merge(array('ok' => true, 'code' => 'OK'), $data);
    }
    private static function s($row, $k, $d = '')
    {
        return isset($row[$k]) ? (string) $row[$k] : $d;
    }
    private static function i($row, $k, $d = 0)
    {
        return isset($row[$k]) ? (int) $row[$k] : $d;
    }

    /* ══════════════════════════════════════════════════════════════════════
       ① الأطرافُ الأربعةُ — بابٌ واحدٌ ومفتاحُ فاعلٍ لا اسمُه
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * ⛔ **المنفذُ الوحيدُ إلى سجلِّ الأطراف.**
     * والقاعدةُ هي التي تردُّ الدمجَ (`uq_tkp_actor`) — والخدمةُ تترجمه رمزًا.
     */
    public static function recordParty(TenantDb $gate, $ticketId, $role, array $actor, $recordedBy)
    {
        $role = (string) $role;
        if (!in_array($role, array(self::ROLE_REPORTER, self::ROLE_SUBJECT,
                                   self::ROLE_OWNER, self::ROLE_RESOLUTION), true)) {
            return self::fail('PARTY_ROLE_UNKNOWN', $role);
        }
        $kind = self::s($actor, 'kind', 'PERSON');
        $id   = self::i($actor, 'id');
        $dept = self::s($actor, 'dept');
        if ($id <= 0) { return self::fail('PARTY_WITHOUT_KEY', 'الطرف بلا مفتاح فاعل'); }
        if ($role !== self::ROLE_SUBJECT && $kind !== 'PERSON') {
            return self::fail('PARTY_NOT_A_PERSON', $role);
        }
        if ($role === self::ROLE_OWNER && $dept !== self::CRP_DEPT) {
            return self::fail('TICKET_OWNER_OUTSIDE_CENTER', $dept);
        }
        if ($role === self::ROLE_RESOLUTION && ($dept === self::CRP_DEPT || $dept === '')) {
            return self::fail('RESOLUTION_BY_TICKET_CENTER', 'مالك الحل لا يكون ادارة البلاغات');
        }
        if ($role === self::ROLE_SUBJECT && self::s($actor, 'subject_type') === '') {
            return self::fail('SUBJECT_WITHOUT_TYPE', 'محل البلاغ بلا نوع من الكتالوج');
        }
        try {
            $newId = $gate->insert('tkt_party', array(
                'ticket_id'         => (int) $ticketId,
                'party_role'        => $role,
                'actor_kind'        => $kind,
                'actor_id'          => $id,
                'actor_dept'        => $dept,
                'subject_type_code' => self::s($actor, 'subject_type'),
                'recorded_by'       => (int) $recordedBy,
                'why'               => self::s($actor, 'why'),
                'src_ref'           => self::s($actor, 'src_ref'),
            ));
        } catch (\Throwable $t) {
            $m = $t->getMessage();
            if (strpos($m, 'uq_tkp_actor') !== false) {
                return self::fail('MERGED_PARTY_ACTOR', 'الفاعل يشغل دورا اخر في البلاغ نفسه');
            }
            if (strpos($m, 'uq_tkp_role') !== false) {
                return self::fail('PARTY_ROLE_TAKEN', 'الدور مشغول بفاعل اخر');
            }
            return self::fail('PARTY_REFUSED', $m);
        }
        return self::done(array('party_id' => (int) $newId));
    }

    /** فتحُ البلاغِ — المُبلِّغُ ومحلُّ البلاغِ طرفانِ متمايزانِ منذ اللحظةِ الأولى */
    public static function openTicket(TenantDb $gate, $ticketId, array $reporter, array $subject, $recordedBy)
    {
        $r = self::recordParty($gate, $ticketId, self::ROLE_REPORTER, $reporter, $recordedBy);
        if (!$r['ok']) { return $r; }
        $s = self::recordParty($gate, $ticketId, self::ROLE_SUBJECT, $subject, $recordedBy);
        if (!$s['ok']) { return $s; }
        self::emit('tkt.reported', 'tkt_party', (int) $ticketId, array(
            'ticket_id' => (int) $ticketId, 'reporter' => self::i($reporter, 'id'),
            'subject_type' => self::s($subject, 'subject_type'),
        ), $gate);
        return self::done(array('reporter_party' => $r['party_id'], 'subject_party' => $s['party_id']));
    }

    /** ملكيّةُ دورةِ التذكرةِ للمركزِ وحدَه */
    public static function takeOwnership(TenantDb $gate, $ticketId, $ownerPersonId, $recordedBy)
    {
        return self::recordParty($gate, $ticketId, self::ROLE_OWNER, array(
            'kind' => 'PERSON', 'id' => (int) $ownerPersonId, 'dept' => self::CRP_DEPT,
            'why' => 'مالك دورة التذكرة في مركز البلاغات',
        ), $recordedBy);
    }

    /* ══════════════════════════════════════════════════════════════════════
       ② التوجيهُ والإسنادُ — والوجهةُ ليست المركزَ أبدًا
       ══════════════════════════════════════════════════════════════════════ */

    public static function route(TenantDb $gate, $ticketId, array $row, $actorId)
    {
        $to = self::s($row, 'to_dept');
        if ($to === '' || $to === self::CRP_DEPT) {
            return self::fail('ROUTED_TO_TICKET_CENTER', 'لا توجيه لادارة البلاغات كمالك حل');
        }
        $kind = self::s($row, 'route_kind', 'AUTO');
        $reason = self::s($row, 'reason');
        $rule = self::s($row, 'rule_ref');
        if ($kind === 'CENTER_CORRECTION' && $reason === '') {
            return self::fail('ROUTE_CORRECTION_WITHOUT_REASON', 'تصحيح التوجيه بلا سبب مكتوب');
        }
        if ($kind === 'AUTO' && $rule === '') {
            return self::fail('AUTO_ROUTE_WITHOUT_RULE', 'التوجيه الالي بلا قاعدة مرجعية');
        }
        $seq = 1 + (int) $gate->count('tkt_routing_history', array('where' => array('ticket_id' => (int) $ticketId)));
        try {
            $id = $gate->insert('tkt_routing_history', array(
                'ticket_id'  => (int) $ticketId,
                'seq_no'     => $seq,
                'route_kind' => $kind,
                'from_dept'  => self::s($row, 'from_dept'),
                'to_dept'    => $to,
                'rule_ref'   => $rule,
                'reason'     => $reason,
                'routed_by'  => (int) $actorId,
                'src_ref'    => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('ROUTE_REFUSED', $t->getMessage()); }
        self::emit('tkt.routed', 'tkt_routing_history', (int) $id, array(
            'ticket_id' => (int) $ticketId, 'to_dept' => $to, 'seq_no' => $seq,
        ), $gate);
        return self::done(array('routing_id' => (int) $id, 'seq_no' => $seq));
    }

    /** الإسنادُ يُسجِّل مالكَ الحلِّ طرفًا رابعًا — والمركزُ لا يُسنَد إليه */
    public static function assign(TenantDb $gate, $ticketId, array $row, $actorId)
    {
        $to = self::s($row, 'to_dept');
        $person = self::i($row, 'to_person_id');
        if ($to === '' || $to === self::CRP_DEPT) {
            return self::fail('ASSIGN_TO_TICKET_CENTER', 'لا اسناد معالجة لادارة البلاغات');
        }
        if ($person <= 0) { return self::fail('ASSIGN_WITHOUT_PERSON', ''); }
        if (self::s($row, 'reason') === '') {
            return self::fail('ASSIGN_WITHOUT_REASON', 'تغيير المكلف بلا سبب مكتوب');
        }
        $seq = 1 + (int) $gate->count('tkt_assignment_history', array('where' => array('ticket_id' => (int) $ticketId)));
        try {
            $id = $gate->insert('tkt_assignment_history', array(
                'ticket_id'      => (int) $ticketId,
                'seq_no'         => $seq,
                'from_person_id' => self::i($row, 'from_person_id'),
                'to_person_id'   => $person,
                'to_dept'        => $to,
                'reason'         => self::s($row, 'reason'),
                'assigned_by'    => (int) $actorId,
                'src_ref'        => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('ASSIGN_REFUSED', $t->getMessage()); }
        /* **والإسنادُ الأوّلُ يثبّت الطرفَ الرابع** — والدمجُ يُردُّ من القاعدة */
        $exists = $gate->selectOne('tkt_party', array('where' => array(
            'ticket_id' => (int) $ticketId, 'party_role' => self::ROLE_RESOLUTION)));
        if (!$exists) {
            $p = self::recordParty($gate, $ticketId, self::ROLE_RESOLUTION, array(
                'kind' => 'PERSON', 'id' => $person, 'dept' => $to,
                'why' => 'مالك الحل في ادارته المختصة',
            ), $actorId);
            if (!$p['ok']) { return $p; }
        }
        self::emit('tkt.assigned', 'tkt_assignment_history', (int) $id, array(
            'ticket_id' => (int) $ticketId, 'to_person_id' => $person, 'to_dept' => $to,
        ), $gate);
        return self::done(array('assignment_id' => (int) $id, 'seq_no' => $seq));
    }

    /** الاستلامُ وقتٌ لا حالة — ولا مكلَّفٌ بلا وقتِ استلام */
    public static function acknowledge(TenantDb $gate, $assignmentId, $actorId)
    {
        $a = $gate->selectOne('tkt_assignment_history', array('where' => array('id' => (int) $assignmentId)));
        if (!$a) { return self::fail('ASSIGNMENT_NOT_FOUND', ''); }
        if ((int) $a['to_person_id'] !== (int) $actorId) {
            return self::fail('ACKNOWLEDGE_BY_OTHER', 'الاستلام من المكلف نفسه');
        }
        $gate->update('tkt_assignment_history', array('received_at' => date('Y-m-d H:i:s')),
                      array('id' => (int) $assignmentId));
        return self::done(array('assignment_id' => (int) $assignmentId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ③ المعالجةُ — عند مالكِها لا عند المركز
       ══════════════════════════════════════════════════════════════════════ */

    public static function recordAction(TenantDb $gate, $ticketId, array $row, $actorId)
    {
        $dept = self::s($row, 'executor_dept');
        if ($dept === '' || $dept === self::CRP_DEPT) {
            return self::fail('RESOLUTION_BY_TICKET_CENTER', 'اجراء المعالجة لا ينفذه مركز البلاغات');
        }
        if (self::s($row, 'dept_screen_ref') === '') {
            return self::fail('ACTION_WITHOUT_DEPT_REF', 'الاجراء بلا مرجع في شاشة ادارته');
        }
        if (self::s($row, 'action_ar') === '') { return self::fail('ACTION_WITHOUT_TEXT', ''); }
        $seq = 1 + (int) $gate->count('tkt_resolution_action', array('where' => array('ticket_id' => (int) $ticketId)));
        try {
            $id = $gate->insert('tkt_resolution_action', array(
                'ticket_id'          => (int) $ticketId,
                'seq_no'             => $seq,
                'executor_dept'      => $dept,
                'executor_person_id' => (int) $actorId,
                'action_ar'          => self::s($row, 'action_ar'),
                'dept_screen_ref'    => self::s($row, 'dept_screen_ref'),
                'dept_doc_ref'       => self::s($row, 'dept_doc_ref'),
                'src_ref'            => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('ACTION_REFUSED', $t->getMessage()); }
        self::emit('tkt.action.recorded', 'tkt_resolution_action', (int) $id, array(
            'ticket_id' => (int) $ticketId, 'executor_dept' => $dept, 'seq_no' => $seq,
        ), $gate);
        return self::done(array('action_id' => (int) $id, 'seq_no' => $seq));
    }

    /** التواصلُ سطرٌ بقناتِه ووقتِه — دورُ المركزِ تواصلٌ موثَّق */
    public static function communicate(TenantDb $gate, $ticketId, array $row, $actorId)
    {
        if (self::s($row, 'note') === '') { return self::fail('COMMUNICATION_WITHOUT_TEXT', ''); }
        if (self::s($row, 'channel') === '') { return self::fail('COMMUNICATION_WITHOUT_CHANNEL', ''); }
        try {
            $id = $gate->insert('ticket_communications', array(
                'tk_id'     => (int) $ticketId,
                'person_id' => (int) $actorId,
                'channel'   => self::s($row, 'channel'),
                'note'      => self::s($row, 'note'),
                'at'        => date('Y-m-d H:i:s'),
            ));
        } catch (\Throwable $t) { return self::fail('COMMUNICATION_REFUSED', $t->getMessage()); }
        return self::done(array('communication_id' => (int) $id));
    }

    /** التصعيدُ سلّمٌ بمستوياتِه — وسقفُه من السجلِّ لا من الشيفرة */
    public static function escalate(TenantDb $gate, $workstreamId, $level, $toPersonId, $actorId)
    {
        $max = self::threshold('TKT_ESCALATION_MAX_LEVEL');
        if ($max === null) { return self::fail('THRESHOLD_MISSING', 'TKT_ESCALATION_MAX_LEVEL'); }
        if ((int) $level < 1 || (float) $level > $max) {
            return self::fail('ESCALATION_LEVEL_OUT_OF_LADDER', 'المستوى خارج سلم التصعيد المسجل');
        }
        try {
            $id = $gate->insert('ticket_escalations', array(
                'ws_id'        => (int) $workstreamId,
                'level'        => (int) $level,
                'triggered_by' => 'sla_breach',
                'to_person_id' => (int) $toPersonId,
                'at'           => date('Y-m-d H:i:s'),
            ));
        } catch (\Throwable $t) { return self::fail('ESCALATION_REFUSED', $t->getMessage()); }
        self::emit('tkt.escalated', 'ticket_escalations', (int) $id, array(
            'ws_id' => (int) $workstreamId, 'level' => (int) $level,
        ), $gate);
        return self::done(array('escalation_id' => (int) $id, 'level' => (int) $level));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ④ التحقّقُ والإغلاقُ وإعادةُ الفتح
       ══════════════════════════════════════════════════════════════════════ */

    /** المعالجةُ تفتح نافذةَ التحقّق — ونافذتُها من السجلِّ بحسبِ الأولويّة */
    public static function resolve(TenantDb $gate, $ticketId, array $row, $actorId)
    {
        $dept = self::s($row, 'resolved_dept');
        if ($dept === '' || $dept === self::CRP_DEPT) {
            return self::fail('RESOLUTION_BY_TICKET_CENTER', 'المعالجة عند مالكها لا عند المركز');
        }
        $priority = self::s($row, 'priority_code', 'normal');
        $key = ($priority === 'critical') ? 'TKT_VERIFY_WINDOW_CRITICAL_H' : 'TKT_VERIFY_WINDOW_NORMAL_H';
        $win = self::threshold($key);
        if ($win === null) { return self::fail('THRESHOLD_MISSING', $key); }
        $acts = (int) $gate->count('tkt_resolution_action', array('where' => array('ticket_id' => (int) $ticketId)));
        if ($acts === 0) { return self::fail('RESOLVE_WITHOUT_ACTION', 'لا معالجة معلنة بلا اجراء مسجل'); }
        $cycle = 1 + (int) $gate->count('tkt_verification', array('where' => array('ticket_id' => (int) $ticketId)));
        try {
            $id = $gate->insert('tkt_verification', array(
                'ticket_id'     => (int) $ticketId,
                'cycle_no'      => $cycle,
                'priority_code' => $priority,
                'resolved_at'   => date('Y-m-d H:i:s'),
                'resolved_by'   => (int) $actorId,
                'resolved_dept' => $dept,
                'window_hours'  => (int) $win,
                'state'         => 'verification',
                'note'          => self::s($row, 'note'),
                'src_ref'       => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('RESOLVE_REFUSED', $t->getMessage()); }
        self::emit('tkt.resolved', 'tkt_verification', (int) $id, array(
            'ticket_id' => (int) $ticketId, 'cycle_no' => $cycle, 'window_hours' => (int) $win,
        ), $gate);
        return self::done(array('verification_id' => (int) $id, 'cycle_no' => $cycle, 'window_hours' => (int) $win));
    }

    /** ⛔ **ولا يتحقّق المنفِّذُ من عملِه** — والآليُّ ممتنعٌ للحرِج */
    public static function verify(TenantDb $gate, $verificationId, $kind, $actorId)
    {
        $v = $gate->selectOne('tkt_verification', array('where' => array('id' => (int) $verificationId)));
        if (!$v) { return self::fail('VERIFICATION_NOT_FOUND', ''); }
        if ((string) $v['state'] !== 'verification') { return self::fail('VERIFICATION_NOT_OPEN', (string) $v['state']); }
        if (!in_array((string) $kind, array('REPORTER', 'SPECIALIST', 'AUTO_WINDOW'), true)) {
            return self::fail('VERIFY_KIND_UNKNOWN', (string) $kind);
        }
        if ((string) $kind === 'AUTO_WINDOW' && (string) $v['priority_code'] === 'critical') {
            return self::fail('AUTO_CLOSE_ON_CRITICAL', 'لا اغلاق الي للبلاغ الحرج');
        }
        if ((string) $kind !== 'AUTO_WINDOW' && (int) $actorId === (int) $v['resolved_by']) {
            return self::fail('SAME_ACTOR_RESOLVE_AND_VERIFY', 'من عالج لا يتحقق من عمله');
        }
        try {
            $gate->update('tkt_verification', array(
                'verified_at' => date('Y-m-d H:i:s'),
                'verified_by' => (int) $actorId,
                'verify_kind' => (string) $kind,
                'state'       => 'verified',
            ), array('id' => (int) $verificationId));
        } catch (\Throwable $t) { return self::fail('VERIFY_REFUSED', $t->getMessage()); }
        self::emit('tkt.verified', 'tkt_verification', (int) $verificationId, array(
            'verification_id' => (int) $verificationId, 'verify_kind' => (string) $kind,
        ), $gate);
        return self::done(array('verification_id' => (int) $verificationId));
    }

    /** ⛔ **ولا إغلاقَ بلا تحقّق** — والقاعدةُ تردُّه أيضًا بـ`chk_tkv_close` */
    public static function close(TenantDb $gate, $verificationId, $actorId)
    {
        $v = $gate->selectOne('tkt_verification', array('where' => array('id' => (int) $verificationId)));
        if (!$v) { return self::fail('VERIFICATION_NOT_FOUND', ''); }
        if ($v['verified_at'] === null || (string) $v['verified_at'] === '') {
            return self::fail('CLOSE_WITHOUT_VERIFICATION', 'لا اغلاق قبل التحقق');
        }
        try {
            $gate->update('tkt_verification', array(
                'closed_at' => date('Y-m-d H:i:s'), 'closed_by' => (int) $actorId, 'state' => 'closed',
            ), array('id' => (int) $verificationId));
        } catch (\Throwable $t) { return self::fail('CLOSE_REFUSED', $t->getMessage()); }
        self::emit('tkt.closed', 'tkt_verification', (int) $verificationId, array(
            'verification_id' => (int) $verificationId,
        ), $gate);
        return self::done(array('verification_id' => (int) $verificationId));
    }

    /** إعادةُ الفتحِ تعود بالبلاغِ إلى **مسارِ معالجتِه** لا إلى بدايتِه */
    public static function reopen(TenantDb $gate, $ticketId, array $row, $actorId)
    {
        $reason = self::s($row, 'reopen_reason');
        if (!in_array($reason, array('REPORTER_OBJECTION', 'RECURRENCE'), true)) {
            return self::fail('REOPEN_REASON_UNKNOWN', $reason);
        }
        if (self::s($row, 'note') === '') { return self::fail('REOPEN_WITHOUT_REASON', 'اعادة الفتح بلا سبب مكتوب'); }
        $back = self::s($row, 'back_to_dept');
        if ($back === '' || $back === self::CRP_DEPT) {
            return self::fail('REOPEN_BACK_TO_CENTER', 'العودة لمسار المعالجة لا لمركز البلاغات');
        }
        $last = $gate->selectOne('tkt_verification', array(
            'where' => array('ticket_id' => (int) $ticketId), 'orderBy' => 'cycle_no DESC'));
        if (!$last) { return self::fail('REOPEN_WITHOUT_CLOSURE', 'لا اقفال سابق يعاد فتحه'); }
        if ((string) $last['state'] !== 'closed') {
            return self::fail('REOPEN_ON_OPEN_CYCLE', 'الدورة الاخيرة ليست مغلقة');
        }
        $seq = 1 + (int) $gate->count('tkt_reopen', array('where' => array('ticket_id' => (int) $ticketId)));
        try {
            $id = $gate->insert('tkt_reopen', array(
                'ticket_id'      => (int) $ticketId,
                'seq_no'         => $seq,
                'prior_cycle_no' => (int) $last['cycle_no'],
                'reopen_reason'  => $reason,
                'note'           => self::s($row, 'note'),
                'raised_by'      => (int) $actorId,
                'back_to_dept'   => $back,
                'src_ref'        => self::s($row, 'src_ref'),
            ));
            $gate->update('tkt_verification', array('state' => 'reopened'),
                          array('id' => (int) $last['id']));
        } catch (\Throwable $t) { return self::fail('REOPEN_REFUSED', $t->getMessage()); }
        self::emit('tkt.reopened', 'tkt_reopen', (int) $id, array(
            'ticket_id' => (int) $ticketId, 'reopen_reason' => $reason, 'back_to_dept' => $back,
        ), $gate);
        return self::done(array('reopen_id' => (int) $id, 'seq_no' => $seq));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑤ دورةُ الموظّف — التهيئةُ والحركةُ والقضيّةُ والخصمُ والتصفية
       ══════════════════════════════════════════════════════════════════════ */

    public static function addEmployeeDocument(TenantDb $gate, array $row, $actorId)
    {
        if (self::s($row, 'file_ref') === '') { return self::fail('DOCUMENT_WITHOUT_FILE', ''); }
        if (self::i($row, 'is_mandatory') === 1 && self::s($row, 'expires_at') === '') {
            return self::fail('MANDATORY_DOC_WITHOUT_EXPIRY', 'المستند الالزامي يتابع بصلاحيته');
        }
        try {
            $id = $gate->insert('hr_employee_document', array(
                'employee_id'  => self::i($row, 'employee_id'),
                'doc_type'     => self::s($row, 'doc_type'),
                'doc_no'       => self::s($row, 'doc_no'),
                'issued_at'    => self::s($row, 'issued_at') !== '' ? self::s($row, 'issued_at') : null,
                'expires_at'   => self::s($row, 'expires_at') !== '' ? self::s($row, 'expires_at') : null,
                'is_mandatory' => self::i($row, 'is_mandatory'),
                'file_ref'     => self::s($row, 'file_ref'),
                'state'        => 'valid',
                'created_by'   => (int) $actorId,
                'src_ref'      => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('DOCUMENT_REFUSED', $t->getMessage()); }
        return self::done(array('document_id' => (int) $id));
    }

    public static function addOnboardingItem(TenantDb $gate, array $row)
    {
        try {
            $id = $gate->insert('hr_onboarding_item', array(
                'employee_id' => self::i($row, 'employee_id'),
                'item_code'   => self::s($row, 'item_code'),
                'item_ar'     => self::s($row, 'item_ar'),
                'mandatory'   => self::i($row, 'mandatory', 1),
                'state'       => 'pending',
                'src_ref'     => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('ONBOARDING_ITEM_REFUSED', $t->getMessage()); }
        return self::done(array('item_id' => (int) $id));
    }

    /** ⛔ **والاستثناءُ لا يمرُّ بلا مستند** — `chk_hron_waiver` يردُّه أيضًا */
    public static function settleOnboardingItem(TenantDb $gate, $itemId, $state, array $row, $actorId)
    {
        if (!in_array((string) $state, array('done', 'waived'), true)) {
            return self::fail('ONBOARDING_STATE_UNKNOWN', (string) $state);
        }
        if ((string) $state === 'waived' && self::s($row, 'waiver_doc_ref') === '') {
            return self::fail('WAIVER_WITHOUT_DOCUMENT', 'استثناء بند التهيئة بلا توثيق');
        }
        try {
            $gate->update('hr_onboarding_item', array(
                'state'           => (string) $state,
                'waiver_doc_ref'  => self::s($row, 'waiver_doc_ref'),
                'custody_doc_ref' => self::s($row, 'custody_doc_ref'),
                'done_at'         => date('Y-m-d H:i:s'),
                'done_by'         => (int) $actorId,
            ), array('id' => (int) $itemId));
        } catch (\Throwable $t) { return self::fail('ONBOARDING_REFUSED', $t->getMessage()); }
        return self::done(array('item_id' => (int) $itemId));
    }

    /** ⛔ **ولا مباشرةَ كاملةٌ ببندٍ إلزاميٍّ معلَّق** */
    public static function completeOnboarding(TenantDb $gate, $employeeId, $actorId)
    {
        $open = (int) $gate->count('hr_onboarding_item', array(
            'where' => array('employee_id' => (int) $employeeId, 'mandatory' => 1, 'state' => 'pending')));
        if ($open > 0) {
            return self::fail('ONBOARDING_INCOMPLETE', 'بنود تهيئة الزامية معلقة عددها ' . $open);
        }
        self::emit('hr.employee.onboarded', 'hr_onboarding_item', (int) $employeeId, array(
            'employee_id' => (int) $employeeId, 'by' => (int) $actorId,
        ), $gate);
        return self::done(array('employee_id' => (int) $employeeId));
    }

    public static function requestMovement(TenantDb $gate, array $row, $actorId)
    {
        if (self::s($row, 'doc_ref') === '') { return self::fail('MOVEMENT_WITHOUT_DOCUMENT', ''); }
        if (self::i($row, 'to_position_id') <= 0) { return self::fail('MOVEMENT_WITHOUT_TARGET', ''); }
        try {
            $id = $gate->insert('hr_job_movement', array(
                'employee_id'      => self::i($row, 'employee_id'),
                'movement_kind'    => self::s($row, 'movement_kind', 'transfer'),
                'from_position_id' => self::i($row, 'from_position_id'),
                'to_position_id'   => self::i($row, 'to_position_id'),
                'from_org_unit_id' => self::i($row, 'from_org_unit_id'),
                'to_org_unit_id'   => self::i($row, 'to_org_unit_id'),
                'effective_date'   => self::s($row, 'effective_date', date('Y-m-d')),
                'doc_ref'          => self::s($row, 'doc_ref'),
                'requested_by'     => (int) $actorId,
                'state'            => 'submitted',
                'note'             => self::s($row, 'note'),
                'src_ref'          => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('MOVEMENT_REFUSED', $t->getMessage()); }
        return self::done(array('movement_id' => (int) $id));
    }

    /** ⛔ **من طلب الحركةَ لا يعتمدها** */
    public static function approveMovement(TenantDb $gate, $movementId, $actorId)
    {
        $m = $gate->selectOne('hr_job_movement', array('where' => array('id' => (int) $movementId)));
        if (!$m) { return self::fail('MOVEMENT_NOT_FOUND', ''); }
        if ((string) $m['state'] !== 'submitted') { return self::fail('MOVEMENT_NOT_SUBMITTED', ''); }
        if ((int) $m['requested_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_REQUEST_AND_APPROVE_MOVEMENT', 'من طلب الحركة لا يعتمدها');
        }
        try {
            $gate->update('hr_job_movement', array(
                'state' => 'approved', 'approved_by' => (int) $actorId, 'approved_at' => date('Y-m-d H:i:s'),
            ), array('id' => (int) $movementId));
        } catch (\Throwable $t) { return self::fail('MOVEMENT_APPROVE_REFUSED', $t->getMessage()); }
        self::emit('hr.movement.approved', 'hr_job_movement', (int) $movementId, array(
            'movement_id' => (int) $movementId, 'employee_id' => (int) $m['employee_id'],
        ), $gate);
        return self::done(array('movement_id' => (int) $movementId));
    }

    /** ⛔ **ولا يبلّغ الموظّفُ عن نفسِه** */
    public static function openDisciplinaryCase(TenantDb $gate, array $row, $actorId)
    {
        $emp = self::i($row, 'employee_id');
        if ($emp <= 0) { return self::fail('CASE_WITHOUT_EMPLOYEE', ''); }
        if ($emp === (int) $actorId) { return self::fail('SAME_ACTOR_SUBJECT_AND_REPORTER', ''); }
        if (self::s($row, 'incident_ar') === '') { return self::fail('CASE_WITHOUT_INCIDENT', ''); }
        try {
            $id = $gate->insert('hr_disciplinary_case', array(
                'case_no'      => self::s($row, 'case_no'),
                'employee_id'  => $emp,
                'incident_at'  => self::s($row, 'incident_at', date('Y-m-d H:i:s')),
                'incident_ar'  => self::s($row, 'incident_ar'),
                'reported_by'  => (int) $actorId,
                'state'        => 'incident',
                'src_ref'      => self::s($row, 'src_ref'),
            ));
            $gate->insert('hr_disciplinary_stage', array(
                'case_id' => (int) $id, 'seq_no' => 1, 'stage' => 'incident',
                'actor_id' => (int) $actorId, 'actor_role' => self::s($row, 'reporter_role'),
                'note' => self::s($row, 'incident_ar'), 'src_ref' => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('CASE_REFUSED', $t->getMessage()); }
        return self::done(array('case_id' => (int) $id));
    }

    /** ⛔ **من بلَّغ لا يحقّق** — وتكليفُ المراجعةِ الداخليّةِ بمستندِه (`DEC-OPEN-16`) */
    public static function assignInvestigator(TenantDb $gate, $caseId, $investigatorId, array $row, $actorId)
    {
        $k = $gate->selectOne('hr_disciplinary_case', array('where' => array('id' => (int) $caseId)));
        if (!$k) { return self::fail('CASE_NOT_FOUND', ''); }
        if ((int) $investigatorId === (int) $k['reported_by']) {
            return self::fail('SAME_ACTOR_REPORT_AND_INVESTIGATE', 'من بلغ لا يحقق');
        }
        if ((int) $investigatorId === (int) $k['employee_id']) {
            return self::fail('SUBJECT_CANNOT_INVESTIGATE', '');
        }
        $dept = self::s($row, 'investigation_owner_dept', 'DEP-07');
        if (!in_array($dept, array('DEP-07', 'DEP-08', 'IAF'), true)) {
            return self::fail('INVESTIGATION_OWNER_UNKNOWN', $dept);
        }
        if ($dept === 'IAF' && self::s($row, 'assignment_doc_ref') === '') {
            return self::fail('IAF_WITHOUT_ASSIGNMENT_DOC', 'المراجعة الداخلية بتكليف موثق لا باختصاص اصيل');
        }
        try {
            $gate->update('hr_disciplinary_case', array(
                'investigator_id' => (int) $investigatorId,
                'investigation_owner_dept' => $dept,
                'assignment_doc_ref' => self::s($row, 'assignment_doc_ref'),
                'state' => 'investigation',
            ), array('id' => (int) $caseId));
            $gate->insert('hr_disciplinary_stage', array(
                'case_id' => (int) $caseId, 'seq_no' => 2, 'stage' => 'investigation',
                'actor_id' => (int) $investigatorId, 'actor_role' => self::s($row, 'investigator_role'),
                'note' => self::s($row, 'note', 'تكليف بالتحقيق'), 'src_ref' => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('INVESTIGATION_REFUSED', $t->getMessage()); }
        return self::done(array('case_id' => (int) $caseId));
    }

    /** ⛔ **ومن حقّق لا يقرّر** */
    public static function decideCase(TenantDb $gate, $caseId, array $row, $actorId)
    {
        $k = $gate->selectOne('hr_disciplinary_case', array('where' => array('id' => (int) $caseId)));
        if (!$k) { return self::fail('CASE_NOT_FOUND', ''); }
        if ((string) $k['state'] !== 'investigation') { return self::fail('CASE_NOT_UNDER_INVESTIGATION', ''); }
        if ((int) $actorId === (int) $k['investigator_id']) {
            return self::fail('SAME_ACTOR_INVESTIGATE_AND_DECIDE', 'من حقق لا يقرر');
        }
        $kind = self::s($row, 'decision_kind');
        if (!in_array($kind, array('none', 'warning', 'deduction', 'suspension', 'termination'), true)) {
            return self::fail('DECISION_KIND_UNKNOWN', $kind);
        }
        if (self::s($row, 'decision_ref') === '') { return self::fail('DECISION_WITHOUT_DOCUMENT', ''); }
        try {
            $gate->update('hr_disciplinary_case', array(
                'decision_kind' => $kind, 'decision_ref' => self::s($row, 'decision_ref'),
                'decided_by' => (int) $actorId, 'decided_at' => date('Y-m-d H:i:s'), 'state' => 'decided',
            ), array('id' => (int) $caseId));
            $gate->insert('hr_disciplinary_stage', array(
                'case_id' => (int) $caseId, 'seq_no' => 3, 'stage' => 'decision',
                'actor_id' => (int) $actorId, 'actor_role' => self::s($row, 'decider_role'),
                'note' => self::s($row, 'note', 'قرار تاديبي'), 'doc_ref' => self::s($row, 'decision_ref'),
                'src_ref' => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('DECISION_REFUSED', $t->getMessage()); }
        self::emit('hr.discipline.decided', 'hr_disciplinary_case', (int) $caseId, array(
            'case_id' => (int) $caseId, 'decision_kind' => $kind,
        ), $gate);
        return self::done(array('case_id' => (int) $caseId, 'decision_kind' => $kind));
    }

    /**
     * ⛔ **والخصمُ فرعٌ بمرجعِ قرارِه لا حكمٌ يُكتب في شاشتِه** (`HR-18`).
     * قضيّةٌ غيرُ محسومةٍ أو قرارٌ ليس خصمًا يردُّ الخصمَ برمزٍ يُقرأ.
     */
    public static function raiseDeduction(TenantDb $gate, array $row, $actorId)
    {
        $caseId = self::i($row, 'case_id');
        $k = $caseId > 0 ? $gate->selectOne('hr_disciplinary_case', array('where' => array('id' => $caseId))) : null;
        if (!$k || !in_array((string) $k['state'], array('decided', 'closed'), true)
            || (string) $k['decision_kind'] !== 'deduction') {
            return self::fail('DEDUCTION_WITHOUT_DECIDED_CASE', 'لا خصم بلا قرار قضية يسنده');
        }
        $amount = (float) self::s($row, 'amount', '0');
        if ($amount <= 0) { return self::fail('DEDUCTION_AMOUNT_INVALID', ''); }
        try {
            $id = $gate->insert('payroll_deductions', array(
                'run_id'      => self::i($row, 'run_id'),
                'person_id'   => (int) $k['employee_id'],
                'source_type' => 'penalty',
                'source_id'   => $caseId,
                'amount'      => $amount,
                'requested_amount' => $amount,
                'doc_ref'     => (string) $k['decision_ref'],
                'note'        => self::s($row, 'note'),
            ));
        } catch (\Throwable $t) { return self::fail('DEDUCTION_REFUSED', $t->getMessage()); }
        self::emit('hr.deduction.raised', 'payroll_deductions', (int) $id, array(
            'deduction_id' => (int) $id, 'case_id' => $caseId, 'amount' => $amount,
        ), $gate);
        return self::done(array('deduction_id' => (int) $id));
    }

    public static function enrollBenefit(TenantDb $gate, array $row)
    {
        if (self::s($row, 'payroll_component_ref') === '') {
            return self::fail('BENEFIT_WITHOUT_PAYROLL_REF', 'الميزة تصب في المسير بمرجعها');
        }
        try {
            $id = $gate->insert('hr_benefit_enrollment', array(
                'employee_id'           => self::i($row, 'employee_id'),
                'benefit_code'          => self::s($row, 'benefit_code'),
                'benefit_ar'            => self::s($row, 'benefit_ar'),
                'provider_ref'          => self::s($row, 'provider_ref'),
                'employer_share'        => (float) self::s($row, 'employer_share', '0'),
                'employee_share'        => (float) self::s($row, 'employee_share', '0'),
                'currency'              => self::s($row, 'currency', 'SDG'),
                'effective_from'        => self::s($row, 'effective_from', date('Y-m-d')),
                'payroll_component_ref' => self::s($row, 'payroll_component_ref'),
                'state'                 => 'active',
                'src_ref'               => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('BENEFIT_REFUSED', $t->getMessage()); }
        return self::done(array('benefit_id' => (int) $id));
    }

    public static function recordTraining(TenantDb $gate, array $row)
    {
        $state = self::s($row, 'state', 'planned');
        if ($state === 'completed' && self::s($row, 'certificate_ref') === '') {
            return self::fail('TRAINING_COMPLETED_WITHOUT_CERTIFICATE', '');
        }
        if ($state === 'completed' && self::i($row, 'mandatory') === 1 && self::s($row, 'valid_until') === '') {
            return self::fail('MANDATORY_TRAINING_WITHOUT_EXPIRY', 'التدريب الالزامي يتابع بانتهاء صلاحيته');
        }
        try {
            $id = $gate->insert('hr_training_record', array(
                'employee_id'     => self::i($row, 'employee_id'),
                'program_code'    => self::s($row, 'program_code'),
                'program_ar'      => self::s($row, 'program_ar'),
                'training_kind'   => self::s($row, 'training_kind', 'technical'),
                'mandatory'       => self::i($row, 'mandatory'),
                'started_at'      => self::s($row, 'started_at') !== '' ? self::s($row, 'started_at') : null,
                'completed_at'    => self::s($row, 'completed_at') !== '' ? self::s($row, 'completed_at') : null,
                'certificate_ref' => self::s($row, 'certificate_ref'),
                'valid_until'     => self::s($row, 'valid_until') !== '' ? self::s($row, 'valid_until') : null,
                'state'           => $state,
                'src_ref'         => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('TRAINING_REFUSED', $t->getMessage()); }
        return self::done(array('training_id' => (int) $id));
    }

    /** ⛔ **ولا يقيّم أحدٌ نفسَه** */
    public static function finalizeReview(TenantDb $gate, array $row, $actorId)
    {
        $emp = self::i($row, 'employee_id');
        if ($emp === (int) $actorId) { return self::fail('SAME_ACTOR_REVIEW_SELF', 'لا يقيم احد نفسه'); }
        if (self::s($row, 'criteria_ref') === '') { return self::fail('REVIEW_WITHOUT_CRITERIA', ''); }
        if (self::s($row, 'score') === '') { return self::fail('REVIEW_WITHOUT_SCORE', ''); }
        try {
            $id = $gate->insert('hr_performance_review', array(
                'employee_id'  => $emp,
                'cycle_code'   => self::s($row, 'cycle_code'),
                'review_kind'  => 'ADMIN_PERIODIC',
                'criteria_ref' => self::s($row, 'criteria_ref'),
                'score'        => (float) self::s($row, 'score'),
                'reviewer_id'  => (int) $actorId,
                'state'        => 'finalized',
                'final_at'     => date('Y-m-d H:i:s'),
                'src_ref'      => self::s($row, 'src_ref'),
            ));
        } catch (\Throwable $t) { return self::fail('REVIEW_REFUSED', $t->getMessage()); }
        return self::done(array('review_id' => (int) $id));
    }

    /** اعتمادُ المسيّرِ — والحدثُ يُصدَر ليقرأه المستهلكون */
    public static function approvePayroll(TenantDb $gate, $runId, $actorId)
    {
        $r = $gate->selectOne('payroll_runs', array('where' => array('id' => (int) $runId)));
        if (!$r) { return self::fail('PAYROLL_RUN_NOT_FOUND', ''); }
        if ((int) $r['created_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_APPROVE_PAYROLL', 'من اعد المسير لا يعتمده');
        }
        self::emit('hr.payroll.approved', 'payroll_runs', (int) $runId, array(
            'run_id' => (int) $runId, 'lines_count' => (int) $r['lines_count'],
        ), $gate);
        return self::done(array('run_id' => (int) $runId));
    }

    /** اعتمادُ التصفيةِ — ولا تصفيةَ قبل إبراءِ العهدِ وتسويةِ السلف */
    public static function approveSettlement(TenantDb $gate, $settlementId, $actorId)
    {
        $s = $gate->selectOne('employee_final_settlements', array('where' => array('id' => (int) $settlementId)));
        if (!$s) { return self::fail('SETTLEMENT_NOT_FOUND', ''); }
        if ((int) $s['prepared_by'] === (int) $actorId) {
            return self::fail('SAME_ACTOR_PREPARE_AND_APPROVE_SETTLEMENT', 'من اعد التصفية لا يعتمدها');
        }
        if ((string) $s['clearance_doc'] === '') {
            return self::fail('SETTLEMENT_WITHOUT_CLEARANCE', 'لا تصفية قبل ابراء العهد');
        }
        if ((float) $s['advances_remaining'] > 0) {
            return self::fail('SETTLEMENT_WITH_OPEN_ADVANCE', 'سلفة قائمة تمنع التصفية');
        }
        self::emit('hr.settlement.approved', 'employee_final_settlements', (int) $settlementId, array(
            'settlement_id' => (int) $settlementId, 'employee_id' => (int) $s['employee_id'],
        ), $gate);
        return self::done(array('settlement_id' => (int) $settlementId));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ⑥ النشرُ — من الجذرِ المحايدِ وحدَه
       ══════════════════════════════════════════════════════════════════════ */

    private static function emit($eventKey, $table, $entityId, array $payload, TenantDb $gate = null)
    {
        $conn = self::$eventConn;
        if (!($conn instanceof \mysqli)) { return null; }
        if (!class_exists('\App\Core\EventPublisher')) { return null; }
        $company = self::$company;
        try {
            return \App\Core\EventPublisher::publishFact($conn, array(
                'company_id'      => $company,
                'event_key'       => $eventKey,
                'category'        => 'people',
                'source_module'   => 'people',
                'entity_type'     => $table,
                'entity_id'       => (int) $entityId,
                'payload'         => $payload,
                'idempotency_key' => 'w13:' . $eventKey . ':' . (int) $entityId . ':'
                                     . substr(sha1(json_encode($payload)), 0, 12),
                'source_ref'      => 'PeopleCycleService',
            ));
        } catch (\Throwable $t) { return null; }
    }
}
