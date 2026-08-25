<?php
/**
 * WorkstreamActivator — التفعيل الشرطي (TKT-01 §12 خدمة ①-ب · TKT-08)
 * ───────────────────────────────────────────────────────────────────────────
 * «يفتح المسار الشرطي عند وقوع حدث تفعيله (StockUnavailable → المشتريات)
 *  ويعلّمه skipped إن انتفى شرطه عند إغلاق الرأس».
 * Events: WorkstreamActivated · WorkstreamSkipped (سطور ticket_effects/الردود).
 */

namespace App\Services\Tickets;

require_once __DIR__ . '/TicketRouter.php';

class WorkstreamActivator
{
    /** وقوع حدث تفعيل على بلاغ — يفتح كل مسار pending يترقبه. */
    public static function onEvent(\mysqli $conn, $tkId, $eventName, $actorPersonId = 0)
    {
        $tkId = intval($tkId);
        $tk = $conn->query("SELECT company_id, ticket_type_id, site_id FROM tickets WHERE id = {$tkId}")->fetch_assoc();
        if (!$tk) { return array('ok' => false, 'code' => 404, 'reason' => 'بلاغ غير موجود', 'activated' => 0); }
        $ev = $conn->real_escape_string((string) $eventName);
        $res = $conn->query(
            "SELECT w.ws_id, d.target_role, d.response_sla_minutes, d.resolve_sla_minutes, w.workstream_type
               FROM ticket_workstreams w
               JOIN ticket_type_workstreams d ON d.ticket_type_id = " . intval($tk['ticket_type_id']) . "
                AND d.workstream_type = w.workstream_type AND d.seq_no = w.seq_no
              WHERE w.tk_id = {$tkId} AND w.activation_state = 'pending' AND d.trigger_event = '{$ev}'");
        $n = 0;
        while ($res && ($w = $res->fetch_assoc())) {
            $assignee = TicketRouter::resolveAssignee($conn, intval($tk['company_id']), (string) $w['target_role'], intval($tk['site_id']));
            $resp = $w['response_sla_minutes'] !== null ? "DATE_ADD(NOW(), INTERVAL " . intval($w['response_sla_minutes']) . " MINUTE)" : 'NULL';
            $solve = $w['resolve_sla_minutes'] !== null ? "DATE_ADD(NOW(), INTERVAL " . intval($w['resolve_sla_minutes']) . " MINUTE)" : 'NULL';
            $conn->query("UPDATE ticket_workstreams SET activation_state = 'opened', state = 'new',
                          assignee_person_id = " . ($assignee !== null ? intval($assignee) : 'NULL') . ",
                          response_due_at = {$resp}, resolve_due_at = {$solve}
                          WHERE ws_id = " . intval($w['ws_id']));
            $stmt = $conn->prepare("INSERT INTO ticket_responses (tk_id, ws_id, person_id, response_type, body) VALUES (?, ?, ?, 'info_added', ?)");
            $wsId = intval($w['ws_id']);
            $actor = intval($actorPersonId);
            $body = 'WorkstreamActivated — فتح مسار ' . $w['workstream_type'] . ' بحدث ' . $eventName;
            $stmt->bind_param('iiis', $tkId, $wsId, $actor, $body);
            $stmt->execute();
            $stmt->close();
            $n++;
        }
        return array('ok' => true, 'code' => 200, 'activated' => $n,
            'reason' => $n > 0 ? "فتح {$n} مسارا شرطيا بحدث {$eventName}" : 'لا مسار يترقب هذا الحدث');
    }

    /** عند إغلاق الرأس: الشرطي الذي لم يقع حدثه يُعلَّم skipped (WorkstreamSkipped). */
    public static function skipUnactivated(\mysqli $conn, $tkId)
    {
        $tkId = intval($tkId);
        $conn->query("UPDATE ticket_workstreams SET activation_state = 'skipped', state = 'closed', closed_at = NOW()
                      WHERE tk_id = {$tkId} AND activation_state = 'pending'");
        return $conn->affected_rows;
    }
}
