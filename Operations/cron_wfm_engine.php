<?php
// شواهد المتطلبات (AC-E06-03 · موجة ٣): WFM-035 · WFM-038 · WFM-108
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
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_wfm_engine.php'); // INJ-0025: لا تُشغَّل من المتصفّح
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
   (الموجة ٢: الشاشة تحررت إلى جدولها الأصلي scr_exceptions — ارتحل الكنس معها) */
$expired = 0;
$r = mysqli_query($conn, "SELECT id, period_to FROM scr_exceptions
                           WHERE status NOT IN ('منتهٍ','منته','ملغى','مرفوض') LIMIT 500");
while ($r && ($x = mysqli_fetch_assoc($r))) {
    $until = trim((string) ($x['period_to'] ?? ''));
    if ($until === '' || !preg_match('/^\d{4}-\d{2}-\d{2}/', $until)) { continue; }
    if (strtotime(substr($until, 0, 10)) >= strtotime(date('Y-m-d'))) { continue; }
    $st = $conn->prepare("UPDATE scr_exceptions
                             SET status = 'منتهٍ', status_label = 'منتهٍ',
                                 date_expiry = COALESCE(date_expiry, CURDATE())
                           WHERE id = ?");
    $rid = intval($x['id']);
    $st->bind_param('i', $rid);
    if ($st->execute() && $st->affected_rows > 0) { $expired++; }
    $st->close();
}
fwrite(STDOUT, "⑤ استثناءاتٌ انقضت آليًّا: {$expired}\n");

/* ⑥ SRC-09 · M-14 (SCN-728): «المنعُ المتكرر يكشف: حاجةَ استثناءٍ أو خطأَ
   تصنيفٍ أو محاولةَ تجاوز» — ≥5 لمحاولةٍ واحدةٍ (مستخدم×حدث) في أسبوعٍ
   تفتح إجراءً تصحيحيًّا للحوكمة. عطالة: مرجعُ (مستخدم×حدث×أسبوع). */
$corrective = 0;
$logF = __DIR__ . '/../logs/security.log';
if (is_file($logF)) {
    $h = fopen($logF, 'rb');
    $sz = filesize($logF);
    if ($sz > 800000) { fseek($h, -800000, SEEK_END); fgets($h); }
    $since = date('Y-m-d H:i:s', time() - 7 * 86400);
    $tally = array();
    while (($ln = fgets($h)) !== false) {
        if (!preg_match('/^\[([\d\- :]+)\] \[([^\]]+)\] IP: \S+ \| User: (.*?) \(/u', $ln, $m)) { continue; }
        if ($m[1] < $since) { continue; }
        if (!preg_match('/DENY|DENIED|BLOCKED|REFUSED|403/i', $m[2])) { continue; }
        $k = $m[3] . '|' . $m[2];
        $tally[$k] = ($tally[$k] ?? 0) + 1;
    }
    fclose($h);
    // حامل الإجراء: الحوكمة (15) — بأهلية الإجازة
    $govUser = null;
    $q = mysqli_query($conn, "SELECT id FROM users WHERE role = '15' AND COALESCE(status,'active')='active' ORDER BY id LIMIT 3");
    $govCands = array();
    while ($q && ($u = mysqli_fetch_row($q))) { $govCands[] = intval($u[0]); }
    if ($govCands) { $pk = ems_pick_available($conn, $govCands); $govUser = $pk['user_id']; }
    if ($govUser) {
        $week = date('oW');
        foreach ($tally as $k => $n) {
            if ($n < 5) { continue; }
            list($who, $ev) = explode('|', $k, 2);
            $ref = 'DENY-' . substr(md5($k), 0, 8) . '-' . $week;
            $st = $conn->prepare("SELECT 1 FROM work_items WHERE source_type='SRC-09' AND source_ref=? LIMIT 1");
            $st->bind_param('s', $ref);
            $st->execute();
            $dup = (bool) $st->get_result()->fetch_row();
            $st->close();
            if ($dup) { continue; }
            $res = WI::create($conn, array(
                'company_id' => 4, 'source_type' => 'SRC-09', 'source_ref' => $ref,
                'source_screen' => 'Governance/gov_reports.php',
                'owner_user_id' => $govUser, 'assigned_user_id' => $govUser,
                'org_unit_id' => 1,
                'title' => 'إجراء تصحيحي: منعٌ متكرر (' . $n . '×) — «' . mb_substr($who, 0, 30) . '» على ' . mb_substr($ev, 0, 40),
                'details' => 'المنع المتكرر يكشف: حاجةَ استثناءٍ أو خطأَ تصنيف حمايةٍ أو محاولةَ تجاوز — يُحسم أحدها ويوثَّق.',
                'deliverable' => 'قرارٌ موثَّق: استثناءٌ أو تصحيحُ تصنيفٍ أو تصعيدٌ أمني',
                'evidence_required' => 'مرجع القرار في سجل الحوكمة',
                'priority' => 'P2', 'due_at' => date('Y-m-d H:i:s', time() + 72 * 3600),
                'created_by' => 0, 'parent_ref' => 'gov_reports:denials',
            ));
            if ($res['ok']) { $corrective++; }
        }
    }
}
fwrite(STDOUT, "⑥ إجراءاتٌ تصحيحيةٌ من المنع المتكرر: {$corrective}\n");

/* ⑦ SRC-13 · ورقة المخازن: العهدةُ المصروفة غيرُ المسوّاة تولّد مهمةَ تسويةٍ
 * لحاملها المربوط (holder_id ← users.employee_id) — idempotent بمرجع العهدة.
 * بلا ربطٍ لا مهمة (لا تلفيقَ مسؤولية) — والمتبقي غير المربوط يُعدُّ ويُعلن. */
$custTasks = 0; $custUnlinked = 0;
$cq = $conn->query("SELECT pc.id, pc.item_name, pc.qty_issued, pc.qty_returned, pc.qty_consumed,
                           pc.holder_id, pc.holder_name, pc.company_id, u.id AS holder_user
                      FROM proc_custody pc
                 LEFT JOIN users u ON u.employee_id = pc.holder_id AND u.company_id = pc.company_id
                     WHERE COALESCE(pc.is_deleted,0) = 0 AND pc.state = 'مصروفة'
                       AND (COALESCE(pc.qty_issued,0) - COALESCE(pc.qty_returned,0) - COALESCE(pc.qty_consumed,0)) > 0");
if ($cq) {
    while ($c = $cq->fetch_assoc()) {
        if (empty($c['holder_user'])) { $custUnlinked++; continue; }
        $ref = 'CUSTODY-' . intval($c['id']);
        $st = $conn->prepare("SELECT 1 FROM work_items WHERE source_type='SRC-13' AND source_ref=? LIMIT 1");
        $st->bind_param('s', $ref);
        $st->execute();
        $dupe = $st->get_result()->fetch_assoc();
        $st->close();
        if ($dupe) { continue; }
        $hu = intval($c['holder_user']);
        $remain = (float) $c['qty_issued'] - (float) $c['qty_returned'] - (float) $c['qty_consumed'];
        $res = \App\Services\Work\WorkItemService::create($conn, array(
            'company_id' => intval($c['company_id']), 'source_type' => 'SRC-13', 'source_ref' => $ref,
            'source_screen' => 'Procurement/custody.php',
            'owner_user_id' => $hu, 'assigned_user_id' => $hu, 'org_unit_id' => 1,
            'title' => 'تسوية عهدة: ' . mb_substr((string) $c['item_name'], 0, 60) . ' (متبقٍ ' . $remain . ')',
            'details' => 'عهدة مصروفة بلا تسوية — الإرجاع أو إثبات الاستهلاك بمستنده.',
            'deliverable' => 'عهدة مسوّاة: إرجاع أو استهلاك موثق',
            'evidence_required' => 'مستند الإرجاع/الاستهلاك على ' . $ref,
            'priority' => 'P3', 'due_at' => date('Y-m-d H:i:s', time() + 7 * 86400),
            'created_by' => 0, 'parent_ref' => $ref,
        ));
        if (!empty($res['ok'])) { $custTasks++; }
    }
}
fwrite(STDOUT, "⑦ مهامُّ تسوية عهدةٍ وُلدت: {$custTasks}" . ($custUnlinked ? " · بلا ربط حامل: {$custUnlinked}" : '') . "\n");
/* ⑧ M-14 · حملة المراجعة الدورية للوصول (الموجة ٦): كل ربع سنةٍ تُفتح دورة
   AR-<سنة>Q<ربع> في scr_access_review وتُولَّد مهامها: قائدُ الحملة (الحوكمة
   15) + مهمةُ مراجعةٍ لمدير كل إدارةٍ من خريطة الـ17 على أعضائها (SRC-08 —
   المهمةُ الدوريةُ المنصوصة M-14 §الأدوار). العطالة بمرجع الدورة في source_ref.
   «الصمتُ سحبٌ» الآلي مؤجلٌ لما بعد قلب EMS_PERM_SOURCE (بند النافذة 6) —
   فالسحب فوق مصدرين متزامنين خطرُ ازدواج. */
require_once __DIR__ . '/../Tickets/dept_inbox_map.php';
$q = intval(ceil(intval(date('n')) / 3));
$cycle = 'AR-' . date('Y') . 'Q' . $q;
$campTasks = 0;
$coRows = array();
$r = mysqli_query($conn, "SELECT DISTINCT company_id FROM users WHERE COALESCE(status,'active')='active' AND company_id > 0");
while ($r && ($x = mysqli_fetch_row($r))) { $coRows[] = intval($x[0]); }
foreach ($coRows as $co) {
    $ex = mysqli_query($conn, "SELECT id FROM scr_access_review WHERE company_id = {$co}
                                AND no_cycle = '" . $conn->real_escape_string($cycle) . "' LIMIT 1");
    if ($ex && mysqli_fetch_row($ex)) { continue; } // الدورة مفتوحة — عطالة
    // قائد الحملة: أول حساب حوكمة (15) نشط، وإلا التنفيذي (9)
    $lead = null;
    foreach (array(15, 9) as $rid) {
        $u = mysqli_query($conn, "SELECT id FROM users WHERE role = '{$rid}' AND company_id = {$co}
                                   AND COALESCE(status,'active')='active' ORDER BY id LIMIT 1");
        if ($u && ($uu = mysqli_fetch_row($u))) { $lead = intval($uu[0]); break; }
    }
    if ($lead === null) { continue; }
    $nAcc = 0;
    $c = mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE company_id = {$co} AND COALESCE(status,'active')='active'");
    if ($c) { $nAcc = intval(mysqli_fetch_row($c)[0]); }
    $st = $conn->prepare("INSERT INTO scr_access_review
        (company_id, no_cycle, period_ref, date_launch, dept_name, count_accounts_review,
         status, status_label, is_seed, created_by, created_by_name)
        VALUES (?, ?, ?, CURDATE(), 'كل الإدارات', ?, 'مفتوحة', 'مفتوحة', 0, 0, 'نبض المحرك ⑧ — M-14')");
    $period = date('Y') . '-Q' . $q;
    $st->bind_param('isss', $co, $cycle, $period, $nAcc);
    $st->execute();
    $st->close();
    // مهمة قائد الحملة
    $resL = WI::create($conn, array(
        'company_id' => $co, 'source_type' => 'SRC-08',
        'source_ref' => 'ARC-' . $cycle . '-LEAD', 'source_screen' => 'Governance/access_review.php',
        'owner_user_id' => $lead, 'assigned_user_id' => $lead, 'org_unit_id' => 6,
        'title' => 'حملة المراجعة الدورية للوصول ' . $cycle . ' — قُد الدورة وأقفلها',
        'details' => 'M-14: مراجعة كل الحسابات النشطة (' . $nAcc . ') — والصمت يُعد طلب سحبٍ يقيد يدويًّا حتى قلب المصدر.',
        'deliverable' => 'دورة ' . $cycle . ' مقفلة بنسب استجابتها في شاشة المراجعة',
        'evidence_required' => 'صف الدورة محدثًا بأعداده وتاريخ إقفاله',
        'priority' => 'P2', 'due_at' => date('Y-m-d H:i:s', time() + 86400 * 14), 'created_by' => 0,
        'parent_ref' => 'ARC-' . $cycle,
    ));
    if (!empty($resL['ok'])) { $campTasks++; }
    // مهمة مراجعة لمدير كل إدارة على أعضائها (من الهيكل لا من قوائم)
    for ($unit = 1; $unit <= 15; $unit++) {
        $unitRoles = array();
        for ($rid = 1; $rid <= 40; $rid++) { if (ems_dept_unit_of_role($rid) === $unit) { $unitRoles[] = $rid; } }
        if (!$unitRoles) { continue; }
        $rin = implode(',', array_map('intval', $unitRoles));
        $mgr = null;
        $u = mysqli_query($conn, "SELECT id FROM users WHERE company_id = {$co} AND role IN ({$rin})
                                   AND COALESCE(status,'active')='active' ORDER BY id LIMIT 1");
        if ($u && ($uu = mysqli_fetch_row($u))) { $mgr = intval($uu[0]); }
        if ($mgr === null) { continue; }
        $res = WI::create($conn, array(
            'company_id' => $co, 'source_type' => 'SRC-08',
            'source_ref' => 'ARC-' . $cycle . '-U' . $unit, 'source_screen' => 'Governance/access_review.php',
            'owner_user_id' => $lead, 'assigned_user_id' => $mgr, 'org_unit_id' => $unit,
            'title' => 'راجع وصول أعضاء إدارتك — حملة ' . $cycle,
            'details' => 'أكّد حاجة كل عضوٍ لصلاحياته الحالية أو اطلب سحبها — M-14 §المراجعة الدورية.',
            'deliverable' => 'تأكيد أو طلبات سحبٍ لكل أعضاء الإدارة',
            'evidence_required' => 'ملاحظة الإقفال بأسماء من رُوجعوا',
            'priority' => 'P3', 'due_at' => date('Y-m-d H:i:s', time() + 86400 * 14), 'created_by' => 0,
            'parent_ref' => 'ARC-' . $cycle,
        ));
        if (!empty($res['ok'])) { $campTasks++; }
    }
}
fwrite(STDOUT, "⑧ حملة المراجعة الدورية {$cycle}: مهام وُلدت {$campTasks}\n");

fwrite(STDOUT, "✔ اكتمل النبض\n");
