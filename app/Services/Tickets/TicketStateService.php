<?php
/**
 * TicketStateService — دورة حياة المسار والرأس المشتق (TKT-01 §5 · §12 خدمة ③ · TKT-11)
 * ───────────────────────────────────────────────────────────────────────────
 * «الحالات للمسار لا للرأس — والرأس مشتق: مفتوح ما دام إلزامي مفتوحًا».
 * Validation: إغلاق المكلف لمساره → 403 · تعليق بسبب حر أو بلا مدة → 422 ·
 * إغلاق بلا أثر → 422 · إغلاق رأس ومسار إلزامي مفتوح → 423 · إغلاق آلي
 * لنوع سياسته تمنعه → 403 · إغلاق إداري من غير المركز → 403 · إعادة ثالثة
 * → رفع آلي للمركز.
 */

namespace App\Services\Tickets;

require_once __DIR__ . '/WorkstreamActivator.php';
/* ── INJ-0071 · أثرُ انتقالِ حالةِ المسار ────────────────────────────────────────
     كلُّ انتقالٍ هنا يغيّر حالةَ بلاغٍ — ومَن غيّرها ومتى ومن أيِّ حالةٍ إلى أيّ
     كان يضيع. والمصدرُ مُضمَّنٌ **هنا** عند موضعِ الاستعمالِ لا في الشاشة، وإلا
     كان `function_exists` كاذبًا حين تُستدعى الخدمةُ من مسارٍ آخر. */
require_once dirname(dirname(dirname(__DIR__))) . '/includes/audit_trail.php';

class TicketStateService
{
    /** أثرُ انتقالِ حالةٍ — لا يرمي أبدًا، ولا يُسجَّل إلا عند تغيُّرٍ فعليّ. */
    private static function auditState(\mysqli $conn, $wsId, $from, $to, $extra = array())
    {
        try {
            if (!function_exists('ems_audit_change')) { return; }
            if ($conn->affected_rows <= 0) { return; }
            $uid = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
            $co  = isset($_SESSION['user']['company_id']) ? (int) $_SESSION['user']['company_id'] : 0;
            ems_audit_change($conn, 'tickets', 'ticket_workstreams', 'state_transition', (int) $wsId,
                array_merge(array('state' => $from), array()),
                array_merge(array('state' => $to), $extra),
                array('company_id' => $co, 'user_id' => $uid));
        } catch (\Throwable $e) { error_log('ticket state audit: ' . $e->getMessage()); }
    }

    /** استلام المسار — مهلة الإنجاز تُقاس من هنا. */
    public static function receive(\mysqli $conn, $wsId, $personId)
    {
        $wsId = intval($wsId);
        $conn->query("UPDATE ticket_workstreams SET state = 'received', received_at = NOW(),
                      assignee_person_id = COALESCE(assignee_person_id, " . intval($personId) . ")
                      WHERE ws_id = {$wsId} AND state = 'new'");
        self::auditState($conn, $wsId, 'new', 'received', array('assignee' => intval($personId)));
        return array('ok' => $conn->affected_rows === 1, 'code' => 200);
    }

    public static function startWork(\mysqli $conn, $wsId)
    {
        $wsId = intval($wsId);
        $conn->query("UPDATE ticket_workstreams SET state = 'in_progress' WHERE ws_id = {$wsId} AND state IN ('received','reopened')");
        self::auditState($conn, $wsId, 'received', 'in_progress');
        return array('ok' => $conn->affected_rows === 1, 'code' => 200);
    }

    /** التعليق المحكوم — سبب من قائمة ومدة متوقعة إلزامية (T4). */
    public static function hold(\mysqli $conn, $wsId, $reasonCode, $expectedUntil)
    {
        $allowed = array('awaiting_part', 'awaiting_approval', 'awaiting_technician', 'awaiting_reporter', 'awaiting_external');
        if (!in_array((string) $reasonCode, $allowed, true)) {
            return array('ok' => false, 'code' => 422, 'reason' => 'تعليق بسبب حر → 422 — القائمة محكومة وإلا صار بابا للتهرب');
        }
        if ($expectedUntil === null || $expectedUntil === '') {
            return array('ok' => false, 'code' => 422, 'reason' => 'لا تعليق بلا مدة متوقعة → 422');
        }
        $wsId = intval($wsId);
        $stmt = $conn->prepare("INSERT INTO ticket_holds (ws_id, reason_code, expected_until) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $wsId, $reasonCode, $expectedUntil);
        $stmt->execute();
        $stmt->close();
        $conn->query("UPDATE ticket_workstreams SET state = 'on_hold' WHERE ws_id = {$wsId}");
        self::auditState($conn, $wsId, 'in_progress', 'on_hold', array('reason' => (string) $reasonCode));
        return array('ok' => true, 'code' => 200, 'reason' => 'علق — والمهلة واقفة ما دام السبب قائما');
    }

    /** إنجاز المسار — أثر مسجَّل إلزامي (T7) ولا يغلق المكلف بنفسه (T6). */
    public static function markDone(\mysqli $conn, $wsId, $personId)
    {
        $wsId = intval($wsId);
        $eff = intval($conn->query("SELECT COUNT(*) c FROM ticket_effects WHERE ws_id = {$wsId}")->fetch_assoc()['c']);
        if ($eff === 0) {
            return array('ok' => false, 'code' => 422,
                'reason' => 'إغلاق بلا أثر مسجل → 422 — أثر يناسب الطبيعة: أمر عمل أو رد موثق أو قرار عدم إجراء بسببه');
        }
        $conn->query("UPDATE ticket_workstreams SET state = 'done_pending', resolved_at = NOW()
                      WHERE ws_id = {$wsId} AND state IN ('in_progress','received','reopened','on_hold')");
        return array('ok' => $conn->affected_rows === 1, 'code' => 200,
            'reason' => 'منجز بانتظار التأكيد — ولا يغلق المكلف بلاغا بنفسه');
    }

    /**
     * إغلاق المسار — بحسب closure_policy للنوع (T17) وبيد غير المكلف (T6).
     * $mode: reporter_confirm | owner_approve | auto | admin
     */
    public static function closeWorkstream(\mysqli $conn, $wsId, $byPersonId, $mode = 'reporter_confirm')
    {
        $wsId = intval($wsId);
        $byPersonId = intval($byPersonId);
        $w = $conn->query("SELECT w.*, t.ticket_type_id, t.company_id, tt.closure_policy
                             FROM ticket_workstreams w
                             JOIN tickets t ON t.id = w.tk_id
                             JOIN ticket_types tt ON tt.id = t.ticket_type_id
                            WHERE w.ws_id = {$wsId}")->fetch_assoc();
        if (!$w) { return array('ok' => false, 'code' => 404, 'reason' => 'مسار غير موجود'); }

        // T6: لا يغلق المكلف مساره بنفسه (عدا الإغلاق الإداري من المركز)
        if ($mode !== 'admin' && intval($w['assignee_person_id']) === $byPersonId) {
            return array('ok' => false, 'code' => 403,
                'reason' => 'محاولة المكلف إغلاق بلاغه → 403 — من أصلح وأغلق أغلق على رأيه لا على أثره');
        }
        // T17: الإغلاق الآلي محظور لسياسات تمنعه
        if ($mode === 'auto' && in_array($w['closure_policy'], array('owner_approve', 'committee', 'admin_only'), true)) {
            return array('ok' => false, 'code' => 403,
                'reason' => 'إغلاق آلي لنوع سياسته ' . $w['closure_policy'] . ' → 403 — لا آلي للسلامة والحوادث والشكاوى');
        }
        // الإغلاق الإداري لمركز البلاغات وحده (T10)
        if ($mode === 'admin') {
            $u = $conn->query("SELECT role FROM users WHERE id = {$byPersonId}")->fetch_assoc();
            if (!$u || !in_array((string) $u['role'], array('24', '-1'), true)) {
                return array('ok' => false, 'code' => 403, 'reason' => 'الإغلاق الإداري من غير المركز → 403');
            }
        }
        $eff = intval($conn->query("SELECT COUNT(*) c FROM ticket_effects WHERE ws_id = {$wsId}")->fetch_assoc()['c']);
        if ($eff === 0 && $mode !== 'admin') {
            return array('ok' => false, 'code' => 422, 'reason' => 'لا إغلاق بلا أثر مسجل (عدا الإداري بسببه)');
        }
        $newState = $mode === 'admin' ? 'admin_closed' : 'closed';
        $conn->query("UPDATE ticket_workstreams SET state = '{$newState}', closed_at = NOW() WHERE ws_id = {$wsId}");
        self::recomputeHead($conn, intval($w['tk_id']));
        return array('ok' => true, 'code' => 200, 'reason' => 'أغلق المسار وأعيد حساب الرأس');
    }

    /** إعادة الفتح — عداد ظاهر وثالثة ترفع للمركز آليًّا (T8). */
    public static function reopen(\mysqli $conn, $wsId, $byPersonId)
    {
        $wsId = intval($wsId);
        $conn->query("UPDATE ticket_workstreams SET state = 'reopened', reopen_count = reopen_count + 1,
                      closed_at = NULL WHERE ws_id = {$wsId} AND state IN ('closed','done_pending')");
        if ($conn->affected_rows !== 1) { return array('ok' => false, 'code' => 409, 'reason' => 'ليس في حالة تقبل الإعادة'); }
        $w = $conn->query("SELECT tk_id, reopen_count FROM ticket_workstreams WHERE ws_id = {$wsId}")->fetch_assoc();
        self::recomputeHead($conn, intval($w['tk_id']));
        $raised = false;
        if (intval($w['reopen_count']) >= 3) {
            $stmt = $conn->prepare("INSERT INTO ticket_escalations (ws_id, level, triggered_by, to_person_id) VALUES (?, 'mgr', 'reopen_threshold', NULL)");
            $stmt->bind_param('i', $wsId);
            $stmt->execute();
            $stmt->close();
            $co = intval($conn->query("SELECT company_id FROM tickets WHERE id = " . intval($w['tk_id']))->fetch_assoc()['company_id']);
            $stmt = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link) VALUES (?, 'all', ?, 'Tickets/tickets_list.php')");
            $title = 'إعادة فتح ثالثة: بلاغ #' . intval($w['tk_id']) . ' — رفع لمركز البلاغات آليا';
            $stmt->bind_param('is', $co, $title);
            $stmt->execute();
            $stmt->close();
            $raised = true;
        }
        return array('ok' => true, 'code' => 200, 'reopen_count' => intval($w['reopen_count']), 'raised_to_center' => $raised);
    }

    /**
     * إعادة حساب الرأس — الكاتب الوحيد لhead_state (ذاكرة مشتقة لا مصدر حقيقة).
     * قيد: لا closed ومسار إلزامي مفتوح (423 عند محاولة الإغلاق القسري).
     */
    public static function recomputeHead(\mysqli $conn, $tkId)
    {
        $tkId = intval($tkId);
        // البلاغُ بلا مسارٍ واحدٍ أصلًا (بلاغاتُ الشاشة اليدوية) ليس «مكتملَ
        // المسارات» بل خارجَ هذا النموذج — واعتبارُه مكتملًا كان يغلقه فورًا
        // بغير حق. رأسُه يتبع مرحلتَه عبر tkt_sync_head_state لا هذا المشتقّ.
        $total = intval($conn->query(
            "SELECT COUNT(*) c FROM ticket_workstreams WHERE tk_id = {$tkId}")->fetch_assoc()['c']);
        if ($total === 0) { return 0; }
        $open = intval($conn->query(
            "SELECT COUNT(*) c FROM ticket_workstreams
              WHERE tk_id = {$tkId} AND mandatory = 1 AND activation_state = 'opened'
                AND state NOT IN ('closed','admin_closed')")->fetch_assoc()['c']);
        if ($open === 0) {
            WorkstreamActivator::skipUnactivated($conn, $tkId);
            $conn->query("UPDATE tickets SET head_state = 'closed', stage = 'closed', close_date = CURDATE()
                          WHERE id = {$tkId} AND head_state = 'open'");
        } else {
            $conn->query("UPDATE tickets SET head_state = 'open' WHERE id = {$tkId} AND head_state = 'closed'");
        }
        return $open;
    }

    /** محاولة إغلاق الرأس صراحة — 423 ومسار إلزامي مفتوح (T13). */
    public static function closeHead(\mysqli $conn, $tkId)
    {
        $open = self::recomputeHead($conn, intval($tkId));
        if ($open > 0) {
            return array('ok' => false, 'code' => 423,
                'reason' => 'لا يغلق الرأس و' . $open . ' مسارا إلزاميا مفتوحا → 423');
        }
        return array('ok' => true, 'code' => 200, 'reason' => 'الرأس مغلق — كل الإلزامية مغلقة');
    }

    /* ── مرحلة الرأس: باب واحد ───────────────────────────────────────────────
       كانت شاشتا «الإقفال الإداري» و«التصنيف والتوجيه» تكتبان stage على tickets
       بجملة UPDATE خام في ملفَّيهما، فصار للرأس ثلاثة مُنشئين مستقلّين —
       وهو ما تمنعه §5·9 (مالك قانوني واحد لكل حقيقة). الحكم (من يجوز له،
       ومتى) يبقى في الشاشة لأنه سياستها؛ **الكتابة** وحدها تمرّ من هنا. */

    /** إلغاء إداري لرأس البلاغ — والشرط في الجملة لا في المستدعي. */
    public static function adminCancel(\mysqli $conn, $tkId, $duplicateOf = 0)
    {
        $tkId = intval($tkId);
        $dup  = intval($duplicateOf);
        $ok = $conn->query("UPDATE tickets SET stage = 'cancelled'"
            . ($dup > 0 ? ", duplicate_of_ticket_id = {$dup}" : '')
            . " WHERE id = {$tkId}");
        return array('ok' => (bool) $ok, 'changed' => $conn->affected_rows);
    }

    /** نقض الإلغاء الإداري — لا يردّ إلا ما هو ملغى، والمرحلة السابقة من الأثر. */
    public static function revertAdminCancel(\mysqli $conn, $tkId, $prevStage)
    {
        $tkId = intval($tkId);
        $ok = $conn->query("UPDATE tickets SET stage = '" . $conn->real_escape_string((string) $prevStage)
            . "' WHERE id = {$tkId} AND stage = 'cancelled'");
        return array('ok' => (bool) $ok, 'changed' => $conn->affected_rows);
    }

    /** تصنيف الرأس وتوجيهه — النوع يحدّد الإدارة المالكة والمرحلة تصير «محالة». */
    public static function classifyAndRoute(\mysqli $conn, $tkId, $companyId, $categoryId, $typeId, $ownerRoleId)
    {
        $st = $conn->prepare("UPDATE tickets
                                 SET category_id = ?, ticket_type_id = ?, stage = 'routed',
                                     owner_role_id = COALESCE(NULLIF(?, 0), owner_role_id)
                               WHERE id = ? AND company_id = ? AND stage IN ('new','classified')");
        if (!$st) { return array('ok' => false, 'changed' => 0); }
        $cat = intval($categoryId); $typ = intval($typeId); $own = intval($ownerRoleId);
        $tk  = intval($tkId);       $co  = intval($companyId);
        $st->bind_param('iiiii', $cat, $typ, $own, $tk, $co);
        $st->execute();
        $n = $st->affected_rows;
        $st->close();
        return array('ok' => true, 'changed' => $n);
    }
}
