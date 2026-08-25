<?php
/**
 * WatchTowerService — برج المراقبة (TKT-01 §11 · §12 خدمة ⑤ · TKT-16)
 * ───────────────────────────────────────────────────────────────────────────
 * «يحسب مؤشرات §11 ويولّد تقرير من يتأخر ومن لا يستجيب بالاسم والإدارة
 * دوريًّا — ولا يوجّه ولا يصعّد».
 */

namespace App\Services\Tickets;

class WatchTowerService
{
    /** المؤشرات الثمانية (§11) — على نافذة أيام. */
    public static function indicators(\mysqli $conn, $companyId, $days = 30)
    {
        $co = intval($companyId);
        $d = intval($days);
        $q1 = function ($sql) use ($conn) { $r = $conn->query($sql); $x = $r ? $r->fetch_assoc() : null; return $x; };
        $base = "FROM ticket_workstreams w JOIN tickets t ON t.id = w.tk_id
                 WHERE t.company_id = {$co} AND w.activation_state = 'opened'
                   AND w.created_at >= DATE_SUB(NOW(), INTERVAL {$d} DAY)";

        $resp = $q1("SELECT COUNT(*) total,
                            SUM(w.received_at IS NOT NULL AND (w.response_due_at IS NULL OR w.received_at <= w.response_due_at)) on_time,
                            SUM(w.received_at IS NULL AND w.response_due_at IS NOT NULL AND w.response_due_at < NOW()) never_responded
                     {$base}");
        $solve = $q1("SELECT SUM(w.resolved_at IS NOT NULL) done,
                             SUM(w.resolved_at IS NOT NULL AND (w.resolve_due_at IS NULL OR w.resolved_at <= w.resolve_due_at)) on_time
                      {$base}");
        $holds = $q1("SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, h.started_at, COALESCE(h.ended_at, NOW()))),0) avg_min,
                             COUNT(*) n
                        FROM ticket_holds h JOIN ticket_workstreams w ON w.ws_id = h.ws_id
                        JOIN tickets t ON t.id = w.tk_id
                       WHERE t.company_id = {$co} AND h.started_at >= DATE_SUB(NOW(), INTERVAL {$d} DAY)");
        $reopen = $q1("SELECT SUM(w.reopen_count > 0) reopened, COUNT(*) total {$base}");
        $recur = $q1("SELECT COUNT(*) c FROM tickets
                       WHERE company_id = {$co} AND recurrence_group_id IS NOT NULL
                         AND created_at >= DATE_SUB(NOW(), INTERVAL {$d} DAY)");
        $noEffect = $q1("SELECT COUNT(*) c {$base} AND w.state = 'closed'
                          AND NOT EXISTS (SELECT 1 FROM ticket_effects e WHERE e.ws_id = w.ws_id)");

        $total = max(1, intval($resp['total'] ?? 0));
        $doneN = max(1, intval($solve['done'] ?? 0));
        return array(
            '①_response_compliance_pct' => round(intval($resp['on_time'] ?? 0) * 100 / $total, 1),
            '①_target' => '95% فأعلى',
            '②_resolve_compliance_pct' => round(intval($solve['on_time'] ?? 0) * 100 / $doneN, 1),
            '②_target' => '90%',
            '③_never_responded' => intval($resp['never_responded'] ?? 0),
            '③_target' => 'صفر — مؤشر إهمال لا تأخير',
            '④_avg_hold_minutes' => round(floatval($holds['avg_min'] ?? 0)),
            '⑤_reopen_pct' => round(intval($reopen['reopened'] ?? 0) * 100 / $total, 1),
            '⑤_target' => 'أقل من 5%',
            '⑥_recurrence_tickets' => intval($recur['c'] ?? 0),
            '⑦_closed_without_effect' => intval($noEffect['c'] ?? 0),
            '⑦_target' => 'صفر',
            'window_days' => $d,
            'workstreams_measured' => intval($resp['total'] ?? 0),
        );
    }

    /** ⑧ تقرير «من يتأخر ومن لا يستجيب» بالاسم والإدارة — أساس تواصل المركز. */
    public static function latenessReport(\mysqli $conn, $companyId, $days = 30)
    {
        $co = intval($companyId);
        $d = intval($days);
        $res = $conn->query(
            "SELECT w.assignee_person_id, u.name person_name, ou.name_ar unit_name,
                    COUNT(*) assigned,
                    SUM(w.received_at IS NULL AND w.response_due_at IS NOT NULL AND w.response_due_at < NOW()) no_response,
                    SUM(w.received_at IS NOT NULL AND w.response_due_at IS NOT NULL AND w.received_at > w.response_due_at) late_response,
                    ROUND(AVG(CASE WHEN w.received_at IS NOT NULL AND w.response_due_at IS NOT NULL
                        THEN GREATEST(0, TIMESTAMPDIFF(MINUTE, w.response_due_at, w.received_at)) END), 0) avg_delay_min
               FROM ticket_workstreams w
               JOIN tickets t ON t.id = w.tk_id
               LEFT JOIN users u ON u.id = w.assignee_person_id
               LEFT JOIN org_units ou ON ou.unit_id = w.org_unit_id
              WHERE t.company_id = {$co} AND w.activation_state = 'opened'
                AND w.assignee_person_id IS NOT NULL
                AND w.created_at >= DATE_SUB(NOW(), INTERVAL {$d} DAY)
              GROUP BY w.assignee_person_id, u.name, ou.name_ar
             HAVING no_response > 0 OR late_response > 0
              ORDER BY no_response DESC, avg_delay_min DESC");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
    }

    /** إصدار التقرير الدوري وتسجيله تواصلًا — يُرفع لمدير التشغيل والإدارة العامة. */
    public static function issuePeriodicReport(\mysqli $conn, $companyId, $issuedBy = 0)
    {
        $co = intval($companyId);
        $rows = self::latenessReport($conn, $co);
        $summary = 'تقرير المركز الدوري: ' . count($rows) . ' مكلفا بين متأخر وغير مستجيب';
        $stmt = $conn->prepare("INSERT INTO fin_notifications (company_id, target_level, title, link)
                                VALUES (?, 'all', ?, 'Tickets/watchtower.php')");
        $title = $summary . ' — يرفع لمدير التشغيل والإدارة العامة';
        $stmt->bind_param('is', $co, $title);
        $stmt->execute();
        $stmt->close();
        return array('ok' => true, 'rows' => count($rows), 'summary' => $summary);
    }
}
