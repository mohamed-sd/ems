<?php
/**
 * Operations/cron_wfm_engine.php — نبض محرّك العمل الشخصي (WFM-01)
 * ───────────────────────────────────────────────────────────────────────────
 * دوريًّا (كل ساعة) أربعُ كنسات:
 *  ① مهل المهام (الورقة 05): المتجاوز يُوسم overdue ويُصعَّد لمدير المنفِّذ —
 *     صفرُ مهمةٍ متأخرةٍ بلا تصعيد (AC-WFM-09).
 *  ② المهام الدورية (SRC-08): قوالبُ recurring_tasks المستحقةُ تولّد مهامَّها
 *     وتُدفع مواعيدُها — «مجدولة تنتظر موعد توليدها».
 *  ③ التفويضات المنتهية (WF-08): تُغلق فور انقضائها — توقفُ التوليد آليٌّ
 *     «في اللحظة نفسها» (AC-WFM-10) والمفتوحُ يبقى بقراره.
 *  ④ مهل الطلبات: المتجاوزُ يُصعَّد لمدير حامله ويُخطَر (SlaBreached).
 * التشغيل: php Operations/cron_wfm_engine.php
 */
if (!defined('EMS_CLI')) { define('EMS_CLI', true); } // قد يُركَّب على كرونٍ مضيف
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/resolve_manager.php';
require_once __DIR__ . '/../app/Services/Work/WorkItemService.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

use App\Services\Work\WorkItemService as WI;

fwrite(STDOUT, "══ نبض WFM · " . date('Y-m-d H:i') . " ══\n");

/* ① مهل المهام */
$sla = WI::sweepSla($conn);
fwrite(STDOUT, "① المهل: وُسم متأخرًا {$sla['overdue']} · صُعّد {$sla['escalated']}\n");

/* ② المهام الدورية */
$made = 0;
$r = mysqli_query($conn,
    "SELECT rt.*, tt.title, tt.details, tt.org_unit_id, tt.owner_role_id, tt.priority,
            tt.deliverable, tt.evidence_required, tt.code
       FROM recurring_tasks rt
       JOIN task_templates tt ON tt.id = rt.template_id AND tt.active = 1
      WHERE rt.active = 1 AND rt.next_run_at IS NOT NULL AND rt.next_run_at <= NOW()
      LIMIT 100");
while ($r && ($t = mysqli_fetch_assoc($r))) {
    $co = intval($t['company_id']);
    // «تُعاد للدور لا للشخص»: أول حساب نشط بالدور المالك
    $assignee = null;
    if (!empty($t['owner_role_id'])) {
        $q = mysqli_query($conn, "SELECT id FROM users WHERE role = '" . intval($t['owner_role_id']) . "'
                                   AND company_id = {$co} AND COALESCE(status,'active')='active' ORDER BY id LIMIT 1");
        if ($q && ($u = mysqli_fetch_row($q))) { $assignee = intval($u[0]); }
    }
    if ($assignee === null) { continue; } // لا مالكَ حيًّا — يُترك مستحقًّا للجولة القادمة
    // قرار 10: المُجاز لا يُسنَد إليه آليًّا — النائب أو التأجيل للجولة القادمة
    $pick = ems_pick_available($conn, array($assignee));
    if ($pick['on_leave']) {
        WI::notifyUser($conn, $co, intval($t['owner_role_id']) > 0 ? $assignee : $assignee,
            'مهمةٌ دوريةٌ مؤجلة — المكلَّف في إجازة', (string) $t['title'], 'Portal/my_tasks.php', false, 0);
        continue;
    }
    $assignee = $pick['user_id'];
    $res = WI::create($conn, array(
        'company_id' => $co, 'source_type' => 'SRC-08',
        'source_ref' => 'TPL-' . $t['code'] . '-' . date('Ymd'),
        'source_screen' => 'Portal/my_tasks.php',
        'owner_user_id' => $assignee, 'assigned_user_id' => $assignee,
        'assigned_role_id' => intval($t['owner_role_id']),
        'org_unit_id' => intval($t['org_unit_id']) ?: 1,
        'title' => (string) $t['title'], 'details' => (string) $t['details'],
        'deliverable' => (string) $t['deliverable'],
        'evidence_required' => (string) $t['evidence_required'],
        'priority' => (string) $t['priority'],
        'due_at' => date('Y-m-d H:i:s', time() + 86400 * 3),
        'created_by' => 0, 'parent_ref' => 'RT-' . intval($t['id']),
    ));
    if ($res['ok']) { $made++; }
    $next = array('daily' => '+1 day', 'weekly' => '+7 day', 'monthly' => '+1 month', 'quarterly' => '+3 month');
    $step = $next[$t['freq']] ?? '+1 month';
    $nx = date('Y-m-d H:i:s', strtotime($step, strtotime($t['next_run_at'])));
    mysqli_query($conn, "UPDATE recurring_tasks SET last_run_at = NOW(), next_run_at = '{$nx}' WHERE id = " . intval($t['id']));
}
fwrite(STDOUT, "② الدورية: وُلّد {$made}\n");

/* ③ التفويضات المنتهية — الإغلاق في اللحظة (AC-WFM-10) */
$ended = 0;
$r = mysqli_query($conn, "SELECT id, company_id, from_user_id, to_user_id, kind, scope_ref
                            FROM work_delegations WHERE status = 'active' AND ends_at < NOW() LIMIT 200");
while ($r && ($d = mysqli_fetch_assoc($r))) {
    mysqli_query($conn, "UPDATE work_delegations SET status = 'ended' WHERE id = " . intval($d['id']) . " AND status = 'active'");
    if (mysqli_affected_rows($conn) < 1) { continue; }
    $ended++;
    $co = intval($d['company_id']);
    WI::notifyUser($conn, $co, intval($d['from_user_id']), 'انتهى تفويضُك (' . $d['kind'] . ')',
        'النطاق: ' . $d['scope_ref'] . ' — المفتوحُ يبقى بقراره والتوليدُ توقف', 'Portal/my_tasks.php', false, 0);
    WI::notifyUser($conn, $co, intval($d['to_user_id']), 'انتهت صلاحيةُ تفويضٍ إليك (' . $d['kind'] . ')',
        'النطاق: ' . $d['scope_ref'], 'Portal/my_tasks.php', false, 0);
}
fwrite(STDOUT, "③ التفويضات المنتهية: {$ended}\n");

/* ④ مهل الطلبات — تصعيدٌ لمدير الحامل */
$esc = 0;
$r = mysqli_query($conn,
    "SELECT rq.id, rq.company_id, rq.request_no, rq.title, rq.current_holder_user_id
       FROM requests rq
      WHERE rq.status IN ('submitted','routed','in_approval','approved','executing')
        AND rq.sla_due_at IS NOT NULL AND rq.sla_due_at < NOW()
        AND rq.current_holder_user_id IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM work_escalations we
                         WHERE we.item_kind = 'request' AND we.item_ref = rq.id
                           AND we.escalated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY))
      LIMIT 100");
while ($r && ($q = mysqli_fetch_assoc($r))) {
    $co = intval($q['company_id']);
    $holder = intval($q['current_holder_user_id']);
    $target = ems_resolve_manager($conn, $holder);
    if ($target === null) { $target = $holder; }
    $st = $conn->prepare("INSERT INTO work_escalations (company_id, item_kind, item_ref, from_user_id, to_user_id, level, reason, note)
                          VALUES (?, 'request', ?, ?, ?, 1, 'sla_completion', ?)");
    $rid = intval($q['id']);
    $note = mb_substr($q['request_no'] . ' · ' . $q['title'], 0, 300);
    $st->bind_param('iiiis', $co, $rid, $holder, $target, $note);
    $st->execute();
    $st->close();
    WI::notifyUser($conn, $co, $target, 'تجاوزُ مهلةِ طلبٍ يحتاج تصعيدًا (' . $q['request_no'] . ')',
        (string) $q['title'], 'Portal/my_requests.php', true, 0);
    $esc++;
}
fwrite(STDOUT, "④ مهل الطلبات: صُعّد {$esc}\n");

/* ⑤ حوكمة M-14 · BR-GOV-05: الاستثناء ينقضي بانقضاء مدته ولا يمتد بالسكوت.
   (الشاشة على المخزن البيني — الكنس على طبقتها ويرتحل مع اللحاق) */
$expired = 0;
$r = mysqli_query($conn, "SELECT id, company_id, payload, status FROM cmp03_screen_rows
                           WHERE canonical_file = 'exceptions.php'
                             AND status NOT IN ('منتهٍ','منته','ملغى','مرفوض') LIMIT 500");
while ($r && ($x = mysqli_fetch_assoc($r))) {
    $p = json_decode((string) $x['payload'], true) ?: array();
    $until = trim((string) ($p['المدة إلى'] ?? ''));
    if ($until === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $until)) { continue; }
    if (strtotime(substr($until, 0, 10)) >= strtotime(date('Y-m-d'))) { continue; }
    $p['تاريخ الانتهاء'] = $p['تاريخ الانتهاء'] ?? date('Y-m-d');
    $p['الحالة'] = 'منتهٍ';
    $st = $conn->prepare("UPDATE cmp03_screen_rows SET status = 'منتهٍ', payload = ? WHERE id = ?");
    $json = json_encode($p, JSON_UNESCAPED_UNICODE);
    $rid = intval($x['id']);
    $st->bind_param('si', $json, $rid);
    if ($st->execute() && $st->affected_rows > 0) { $expired++; }
    $st->close();
}
fwrite(STDOUT, "⑤ استثناءاتٌ انقضت آليًّا: {$expired}\n");
fwrite(STDOUT, "✔ اكتمل النبض\n");
