<?php
/**
 * tools/wfm_backfill_tickets.php — ردمُ مهامّ البلاغات المفتوحة (SRC-06 رجعيًّا)
 * ───────────────────────────────────────────────────────────────────────────
 * خطاف SRC-06 يغطي البلاغات الجديدة — وهذا يردم المفتوحةَ قبله: كلُّ مسارٍ
 * فوريٍّ حيٍّ (new/received/in_progress/reopened) بمكلَّفه وبلا مهمةِ عملٍ
 * تقابله يولّد «مهمةَ معالجةٍ بمهلته» بنفس عقد الخطاف حرفًا (عطول بمرجع
 * TKT-{tk}-WS{ws}). بلا مكلَّف ⇒ سلّم البديل (منسق البلاغات 24 ثم 15).
 * الاستعمال: php tools/wfm_backfill_tickets.php [--apply]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../app/Services/Work/WorkItemService.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);

use App\Services\Work\WorkItemService as WI;

/* ⚠️ لا CONCAT في مقارنة النصوص (خلط الترتيبات يُفشل الاستعلام صامتًا) —
   العطالة تُفحص صفًّا صفًّا بربطٍ مُعدٍّ، والأخطاء تُعلن لا تُبتلع. */
$rows = array();
$r = mysqli_query($conn,
    "SELECT ws.ws_id, ws.tk_id, ws.workstream_type, ws.assignee_person_id, ws.org_unit_id,
            ws.resolve_due_at, ws.response_due_at, t.company_id, t.complaint AS title, t.priority, t.site_id
       FROM ticket_workstreams ws
       JOIN tickets t ON t.id = ws.tk_id
      WHERE ws.state IN ('new','received','in_progress','reopened')
        AND ws.activation_state = 'opened'
        AND COALESCE(t.head_state,'open') = 'open'
      ORDER BY ws.tk_id, ws.ws_id") or die('query: ' . mysqli_error($conn) . "\n");
$dupSt = $conn->prepare("SELECT 1 FROM work_items WHERE source_type = 'SRC-06' AND source_ref = ? LIMIT 1");
while ($x = mysqli_fetch_assoc($r)) {
    $ref = 'TKT-' . intval($x['tk_id']) . '-WS' . intval($x['ws_id']);
    $dupSt->bind_param('s', $ref);
    $dupSt->execute();
    if ($dupSt->get_result()->fetch_row()) { continue; } // عطول — له مهمة
    $rows[] = $x;
}
fwrite(STDOUT, "════ ردم مهام البلاغات — " . ($APPLY ? 'تنفيذ' : 'معاينة') . " ════\n");
fwrite(STDOUT, "مساراتٌ حيةٌ بلا مهمة: " . count($rows) . "\n");

$made = 0; $skipped = 0;
$fallbackOf = array();
foreach ($rows as $w) {
    $co = intval($w['company_id']);
    $assignee = intval($w['assignee_person_id'] ?? 0) ?: null;
    if ($assignee === null) {
        if (!isset($fallbackOf[$co])) {
            $fallbackOf[$co] = null;
            foreach (array('24', '15') as $fbRole) {
                $q = mysqli_query($conn, "SELECT id FROM users WHERE role = '{$fbRole}' AND company_id = {$co}
                                           AND COALESCE(status,'active') = 'active' ORDER BY id LIMIT 1");
                if ($q && ($u = mysqli_fetch_row($q))) { $fallbackOf[$co] = intval($u[0]); break; }
            }
        }
        $assignee = $fallbackOf[$co];
    }
    if ($assignee === null) { $skipped++; continue; }
    $due = $w['resolve_due_at'] ?: date('Y-m-d H:i:s', time() + 86400);
    $p1 = ($w['response_due_at'] !== null && strtotime($w['response_due_at']) - time() <= 3600);
    fwrite(STDOUT, "  TKT-{$w['tk_id']}-WS{$w['ws_id']} ({$w['workstream_type']}) ← u{$assignee}\n");
    if (!$APPLY) { $made++; continue; }
    $res = WI::create($conn, array(
        'company_id' => $co, 'source_type' => 'SRC-06',
        'source_ref' => 'TKT-' . intval($w['tk_id']) . '-WS' . intval($w['ws_id']),
        'source_screen' => 'Tickets/tickets_list.php', 'action_code' => 'ticket.ack',
        'owner_user_id' => $assignee, 'assigned_user_id' => $assignee,
        'org_unit_id' => intval($w['org_unit_id'] ?? 0) ?: 1, 'site_id' => intval($w['site_id'] ?? 0),
        'title' => 'معالجة بلاغ #' . intval($w['tk_id']) . ' — مسار ' . $w['workstream_type']
                 . ' (' . mb_substr((string) $w['title'], 0, 60) . ')',
        'deliverable' => 'إغلاق المسار بتوثيق الحل وتأكيد المبلِّغ',
        'evidence_required' => 'توثيق الحل وتأكيد المبلِّغ (الورقة 11)',
        'priority' => $p1 ? 'P1' : 'P2',
        'due_at' => $due,
        'created_by' => 0, 'parent_ref' => 'TKT-' . intval($w['tk_id']),
    ));
    if ($res['ok']) { $made++; } else { $skipped++; fwrite(STDOUT, "   ✘ " . $res['reason'] . "\n"); }
}
fwrite(STDOUT, "──────────────────────────────\n");
fwrite(STDOUT, ($APPLY ? "وُلّد: {$made}" : "سيُولَّد: {$made}") . " · تعذّر: {$skipped}\n");
