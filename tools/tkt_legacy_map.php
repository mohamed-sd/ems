<?php
/**
 * tools/tkt_legacy_map.php — خرط البلاغات الموروثة إلى نموذج المسارات (TKT-14)
 * ───────────────────────────────────────────────────────────────────────────
 * لكل بلاغ قديم بلا مسارات: مسار واحد بحالة مخروطة من stage القديمة —
 * فالنموذج الجديد يقرأ الموروث بلا مساس بأعمدته. idempotent.
 * التشغيل: php tools/tkt_legacy_map.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

// stage القديمة → حالة المسار الجديدة
$map = array(
    'new' => 'new', 'classified' => 'new', 'routed' => 'received', 'in_progress' => 'in_progress',
    'waiting' => 'on_hold', 'follow_up' => 'done_pending', 'done' => 'closed',
    'closed' => 'closed', 'cancelled' => 'admin_closed',
);
$res = $conn->query(
    "SELECT t.id, t.stage, t.assigned_user_id, t.created_at, t.close_date
       FROM tickets t
      WHERE NOT EXISTS (SELECT 1 FROM ticket_workstreams w WHERE w.tk_id = t.id)");
$n = 0;
while ($res && ($t = $res->fetch_assoc())) {
    $state = isset($map[$t['stage']]) ? $map[$t['stage']] : 'new';
    $closed = in_array($state, array('closed', 'admin_closed'), true);
    $stmt = $conn->prepare(
        "INSERT INTO ticket_workstreams (tk_id, workstream_type, seq_no, assignee_person_id, mandatory,
            state, activation_state, received_at, closed_at)
         VALUES (?, 'legacy', 1, ?, 1, ?, 'opened', ?, ?)");
    $tk = intval($t['id']);
    $asg = $t['assigned_user_id'] !== null ? intval($t['assigned_user_id']) : null;
    $recv = $t['created_at'];
    $cls = $closed ? ($t['close_date'] ? $t['close_date'] . ' 00:00:00' : $t['created_at']) : null;
    $stmt->bind_param('iisss', $tk, $asg, $state, $recv, $cls);
    $stmt->execute();
    $stmt->close();
    $conn->query("UPDATE tickets SET head_state = '" . ($closed ? 'closed' : 'open') . "' WHERE id = {$tk}");
    $n++;
}
echo "خُرط {$n} بلاغًا موروثًا إلى نموذج المسارات (مسار legacy واحد لكلٍّ).\n";
