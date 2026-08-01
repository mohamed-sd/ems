<?php
/**
 * SlaMonitor — المهل والتصعيد الآلي (TKT-01 §6 · §12 خدمة ② · TKT-10)
 * ───────────────────────────────────────────────────────────────────────────
 * «دورية كل خمس دقائق: تفحص التجاوز وتصعّد آليًّا — وتوقف الساعة عند التعليق
 * وتعيدها عند رفعه» · «التصعيد إضافة رقيب لا نقل عبء — والمالك الأصلي يبقى» ·
 * «تعليق يتجاوز مهلته المتوقعة يُصعَّد هو نفسه» · المهل بساعة القاعدة.
 * Events: ResponseBreached · ResolveBreached · Escalated (سطور ticket_escalations).
 */

namespace App\Services\Tickets;

class SlaMonitor
{
    /** @return array{response_breach:int, resolve_breach:int, hold_overdue:int, resumed:int} */
    public static function run(\mysqli $conn, $companyId = null)
    {
        $coF = $companyId !== null ? ' AND t.company_id = ' . intval($companyId) : '';
        $out = array('response_breach' => 0, 'resolve_breach' => 0, 'hold_overdue' => 0, 'resumed' => 0);

        // ① تجاوز مهلة الاستجابة — مسار new تجاوز response_due_at (المعلق ساعتُه واقفة)
        $res = $conn->query(
            "SELECT w.ws_id, w.tk_id, t.company_id, t.priority
               FROM ticket_workstreams w JOIN tickets t ON t.id = w.tk_id
              WHERE w.state = 'new' AND w.activation_state = 'opened'
                AND w.response_due_at IS NOT NULL AND w.response_due_at < NOW()
                AND NOT EXISTS (SELECT 1 FROM ticket_holds h WHERE h.ws_id = w.ws_id AND h.ended_at IS NULL)
                AND NOT EXISTS (SELECT 1 FROM ticket_escalations e WHERE e.ws_id = w.ws_id AND e.triggered_by = 'sla_breach'
                                 AND e.at > w.response_due_at){$coF}");
        while ($res && ($w = $res->fetch_assoc())) {
            self::escalate($conn, intval($w['ws_id']), (string) $w['priority'], 'sla_breach', intval($w['company_id']), intval($w['tk_id']));
            $out['response_breach']++;
        }

        // ② تجاوز مهلة الإنجاز — قيد المعالجة تجاوز resolve_due_at
        $res = $conn->query(
            "SELECT w.ws_id, w.tk_id, t.company_id, t.priority
               FROM ticket_workstreams w JOIN tickets t ON t.id = w.tk_id
              WHERE w.state IN ('received','in_progress') AND w.resolve_due_at IS NOT NULL AND w.resolve_due_at < NOW()
                AND NOT EXISTS (SELECT 1 FROM ticket_holds h WHERE h.ws_id = w.ws_id AND h.ended_at IS NULL)
                AND NOT EXISTS (SELECT 1 FROM ticket_escalations e WHERE e.ws_id = w.ws_id AND e.triggered_by = 'sla_breach'
                                 AND e.at > w.resolve_due_at){$coF}");
        while ($res && ($w = $res->fetch_assoc())) {
            self::escalate($conn, intval($w['ws_id']), (string) $w['priority'], 'sla_breach', intval($w['company_id']), intval($w['tk_id']));
            $out['resolve_breach']++;
        }

        // ③ التعليق المتجاوز مهلته المتوقعة — يُصعَّد هو نفسه (§6)
        $res = $conn->query(
            "SELECT h.hold_id, h.ws_id, w.tk_id, t.company_id, t.priority
               FROM ticket_holds h
               JOIN ticket_workstreams w ON w.ws_id = h.ws_id
               JOIN tickets t ON t.id = w.tk_id
              WHERE h.ended_at IS NULL AND h.expected_until < NOW()
                AND NOT EXISTS (SELECT 1 FROM ticket_escalations e WHERE e.ws_id = h.ws_id
                                 AND e.triggered_by = 'hold_overdue' AND e.at > h.expected_until){$coF}");
        while ($res && ($h = $res->fetch_assoc())) {
            self::escalate($conn, intval($h['ws_id']), (string) $h['priority'], 'hold_overdue', intval($h['company_id']), intval($h['tk_id']));
            $out['hold_overdue']++;
        }

        return $out;
    }

    /**
     * سلسلة التصعيد بالأولوية (§6): مدير الإدارة ← مدير التشغيل ← الإدارة العامة —
     * الدرجة التالية غير المسجلة بعد. Insert-only ولا تصعيد يدوي.
     */
    public static function escalate(\mysqli $conn, $wsId, $priority, $trigger, $companyId, $tkId)
    {
        $wsId = intval($wsId);
        $chain = $priority === 'critical' ? array('mgr', 'ops_mgr', 'exec')
            : ($priority === 'high' ? array('mgr', 'ops_mgr') : array('mgr'));
        $done = array();
        $res = $conn->query("SELECT DISTINCT level FROM ticket_escalations WHERE ws_id = {$wsId}");
        while ($res && ($x = $res->fetch_assoc())) { $done[$x['level']] = true; }
        $next = null;
        foreach ($chain as $lvl) { if (!isset($done[$lvl])) { $next = $lvl; break; } }
        if ($next === null) { return false; } // اكتملت السلسلة — تقرير المركز يتولاها
        $to = null;
        if ($next === 'mgr') {
            $r = $conn->query("SELECT u.id FROM ticket_workstreams w
                                JOIN v_org_unit_heads h ON h.unit_id = w.org_unit_id
                                JOIN users u ON u.id = h.head_person_id WHERE w.ws_id = {$wsId} LIMIT 1")->fetch_assoc();
            $to = $r ? intval($r['id']) : null;
        } elseif ($next === 'ops_mgr') {
            $r = $conn->query("SELECT id FROM users WHERE role = '1' AND company_id = " . intval($companyId) . " LIMIT 1")->fetch_assoc();
            $to = $r ? intval($r['id']) : null;
        }
        $stmt = $conn->prepare("INSERT INTO ticket_escalations (ws_id, level, triggered_by, to_person_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('issi', $wsId, $next, $trigger, $to);
        $stmt->execute();
        $stmt->close();
        $conn->query("UPDATE tickets SET escalation_level = escalation_level + 1 WHERE id = " . intval($tkId));
        $stmt = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link) VALUES (?, 'all', ?, 'Tickets/tickets_list.php')");
        $title = 'تصعيد آلي (' . $next . '): بلاغ #' . intval($tkId) . ' مسار #' . $wsId . ' — ' . $trigger . ' · والمالك الأصلي يبقى مسؤولًا';
        $stmt->bind_param('is', $companyId, $title);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    /** التعليق يوقف الساعة — ورفعه يعيدها بإزاحة زمن التعليق (المهل بساعة القاعدة). */
    public static function resumeFromHold(\mysqli $conn, $holdId, $byPersonId = 0)
    {
        $holdId = intval($holdId);
        $h = $conn->query("SELECT * FROM ticket_holds WHERE hold_id = {$holdId} AND ended_at IS NULL")->fetch_assoc();
        if (!$h) { return array('ok' => false, 'code' => 404, 'reason' => 'تعليق غير قائم'); }
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE ticket_holds SET ended_at = NOW() WHERE hold_id = {$holdId}");
            // إعادة الساعة: المهل تُزاح بمدة التعليق — TIMESTAMPDIFF بساعة القاعدة
            $conn->query("UPDATE ticket_workstreams w
                          JOIN ticket_holds h ON h.hold_id = {$holdId} AND h.ws_id = w.ws_id
                            SET w.state = IF(w.state = 'on_hold', 'in_progress', w.state),
                                w.response_due_at = IF(w.response_due_at IS NULL, NULL,
                                    DATE_ADD(w.response_due_at, INTERVAL TIMESTAMPDIFF(SECOND, h.started_at, h.ended_at) SECOND)),
                                w.resolve_due_at = IF(w.resolve_due_at IS NULL, NULL,
                                    DATE_ADD(w.resolve_due_at, INTERVAL TIMESTAMPDIFF(SECOND, h.started_at, h.ended_at) SECOND))");
            $conn->commit();
            return array('ok' => true, 'code' => 200, 'reason' => 'رُفع التعليق وعادت الساعة بإزاحة مدته');
        } catch (\Throwable $e) {
            $conn->rollback();
            return array('ok' => false, 'code' => 500, 'reason' => $e->getMessage());
        }
    }
}
