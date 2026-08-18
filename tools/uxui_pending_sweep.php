<?php
/**
 * tools/uxui_pending_sweep.php — كنّاسُ المعلَّقِ على **محورَين** لا محورٍ واحد
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكمُ النافذُ (تعديلُ المالكِ 2026-08-18 · أولًا) — والقديمُ مسجَّلٌ بتاريخِه
 *   وسببِه في `gov_policy_changes` ولا يُمحى:
 *   «أوقفْ ترقيةَ PENDING_OWNER إلى APPROVED بالصمت. **غيابُ القرارِ ليس قرارًا**».
 *
 * ◆ فالكنّاسُ لم يعد يُرقِّي قرارًا — يحرّك محورَين مستقلَّين:
 *   ① حالةُ القرار: PENDING_OWNER ⇒ OWNER_REVIEW_OVERDUE (بعدَ مهلةِ المالكِ)
 *      ولا يبلغ APPROVED إلا بفاعلٍ بشريٍّ أو مصدرٍ حاكمٍ موثَّق.
 *   ② حالةُ التطبيق: CURRENT ⇒ PROVISIONALLY_APPLIED_NO_OBJECTION
 *      **بعدَ** تصعيدٍ للنائبِ المختصِّ يومَين — تطبيقٌ مؤقَّتٌ قابلٌ للعكس،
 *      وسجلُّ القرارِ يبقى صادقًا أنه لم يُحسَم.
 *
 * ◆ النطاقُ محصورٌ بنيويًّا (قادحُ `trg_nav_provisional_scope`): التسميةُ والموضعُ
 *   وحدَهما لأنهما يُعكسان. ولا تطبيقَ بلا اعتراضٍ في الصلاحياتِ أو سلاليمِ
 *   الاعتمادِ أو السقوفِ الماليةِ أو فصلِ الواجباتِ أو الالتزاماتِ القانونيةِ
 *   أو القراراتِ المالية — والقادحُ يرفضها لا التوصية.
 *
 * ◆ والتصعيدُ إلى **موضعِ سلطةٍ لا اسمِ شخص** (عاشرًا): الدورُ 9 «الإدارة
 *   التنفيذية». وعندَ غيابِ حاملِه لا تنتقل اليدُ إلا بإنابةٍ نافذةٍ محددةِ
 *   المدةِ والنطاقِ مسجَّلةٍ في النظام — ولا تنتقل تلقائيًّا ولا ضمنًا.
 *
 * التشغيل:
 *   php tools/uxui_pending_sweep.php            تقريرٌ بلا كتابة (جرد)
 *   php tools/uxui_pending_sweep.php --apply    تحريكُ المحورَين وفق المهل
 *   php tools/uxui_pending_sweep.php --rollback=<route>  عكسُ تطبيقٍ مؤقَّت
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال\n"); }
$conn->set_charset('utf8mb4');

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $args[$m[1]] = isset($m[2]) ? $m[2] : '1'; }
}
$APPLY = isset($args['apply']);
define('DEPUTY_ROLE', 9);          /* موضعُ السلطةِ المختصُّ بالتصعيد */
define('ESCALATION_DAYS', 2);      /* مهلةُ النائبِ بنصِّ القرار */

/* ── عكسُ تطبيقٍ مؤقَّتٍ بأمرٍ واحد (قابليةُ العكسِ شرطُ السياسة) ── */
if (!empty($args['rollback'])) {
    $rt = $args['rollback'];
    $st = $conn->prepare("UPDATE nav_canonical
                             SET application_state = 'ROLLED_BACK', provisional_since = NULL
                           WHERE route = ? AND application_state = 'PROVISIONALLY_APPLIED_NO_OBJECTION'");
    $st->bind_param('s', $rt);
    $st->execute();
    $n = $st->affected_rows;
    $conn->query("UPDATE nav_pending_closure SET application_state='ROLLED_BACK', rolled_back_at=NOW()
                   WHERE route = '" . $conn->real_escape_string($rt) . "'");
    echo $n > 0 ? "✔ عُكس التطبيقُ المؤقَّتُ لـ{$rt} — وحالةُ القرارِ لم تُمَسّ\n"
                : "لا تطبيقَ مؤقَّتًا لـ{$rt}\n";
    exit(0);
}

/* ── الجرد ── */
$now = date('Y-m-d H:i:s');
$rows = array();
$q = $conn->query("SELECT c.id, c.route, c.owner_dept, c.due_at, c.decision, c.decision_state,
                          c.application_state, c.escalated_at, c.escalation_due_at,
                          n.canonical_ar, n.provisional_reversible
                     FROM nav_pending_closure c
                     LEFT JOIN nav_canonical n ON n.route = c.route
                    WHERE c.decision = 'pending'
                    ORDER BY c.due_at");
while ($q && ($x = $q->fetch_assoc())) { $rows[] = $x; }

$stat = array('total' => count($rows), 'within' => 0, 'overdue_new' => 0, 'escalating' => 0,
              'provisional_new' => 0, 'provisional_already' => 0, 'blocked_scope' => 0);
$acts = array();

foreach ($rows as $r) {
    $due = strtotime((string) $r['due_at']);
    $isOverdue = $due > 0 && $due <= time();
    if (!$isOverdue) { $stat['within']++; continue; }

    /* ① مهلةُ المالكِ انقضت ⇒ حالةُ القرارِ «متأخِّرةُ المراجعة» — لا اعتماد */
    if ($r['decision_state'] === 'PENDING_OWNER') {
        $stat['overdue_new']++;
        $acts[] = array('route' => $r['route'], 'do' => 'overdue+escalate',
            'why' => 'مهلةُ المالكِ انقضت — الحالةُ متأخِّرةُ المراجعةِ لا معتمَدة، وتُصعَّد للنائبِ ' . ESCALATION_DAYS . ' يومَين');
        continue;
    }

    /* ② مهلةُ النائبِ — فإن انقضت: تطبيقٌ مؤقَّتٌ قابلٌ للعكس */
    if ($r['decision_state'] === 'OWNER_REVIEW_OVERDUE') {
        if ($r['application_state'] === 'PROVISIONALLY_APPLIED_NO_OBJECTION') {
            $stat['provisional_already']++;
            continue;
        }
        $escDue = $r['escalation_due_at'] ? strtotime($r['escalation_due_at']) : 0;
        if ($escDue === 0 || $escDue > time()) { $stat['escalating']++; continue; }
        if ((int) $r['provisional_reversible'] !== 1) {
            $stat['blocked_scope']++;
            $acts[] = array('route' => $r['route'], 'do' => 'blocked',
                'why' => 'خارجَ نطاقِ السياسة (غيرُ قابلٍ للعكس) — لا تطبيقَ بلا اعتراضٍ ويبقى معلَّقًا بموضعِه');
            continue;
        }
        $stat['provisional_new']++;
        $acts[] = array('route' => $r['route'], 'do' => 'provisional',
            'why' => 'مهلةُ النائبِ انقضت — تطبيقٌ مؤقَّتٌ قابلٌ للعكس، وسجلُّ القرارِ يبقى غيرَ محسوم');
    }
}

/* ── التنفيذ ── */
$done = array('overdue' => 0, 'provisional' => 0);
if ($APPLY) {
    foreach ($acts as $a) {
        $rt = $conn->real_escape_string($a['route']);
        if ($a['do'] === 'overdue+escalate') {
            $escDue = date('Y-m-d H:i:s', strtotime('+' . ESCALATION_DAYS . ' days'));
            $conn->query("UPDATE nav_pending_closure
                             SET decision_state='OWNER_REVIEW_OVERDUE',
                                 escalated_at=NOW(), escalated_to_role=" . DEPUTY_ROLE . ",
                                 escalation_due_at='{$escDue}',
                                 modification_note = CONCAT(COALESCE(modification_note,''),
                                   ' | " . $conn->real_escape_string(date('Y-m-d')) . ": مهلةُ المالكِ انقضت — صُعِّد للموضعِ " . DEPUTY_ROLE . " ولم يُعتمد')
                           WHERE route='{$rt}' AND decision='pending'");
            /* حالةُ القرارِ في السجلِّ المعياريِّ — و decided_by يبقى NULL بالتصميم */
            $conn->query("UPDATE nav_canonical SET decision_state='OWNER_REVIEW_OVERDUE'
                           WHERE route='{$rt}' AND decision_state='PENDING_OWNER'");
            $done['overdue']++;
        } elseif ($a['do'] === 'provisional') {
            $conn->query("UPDATE nav_canonical
                             SET application_state='PROVISIONALLY_APPLIED_NO_OBJECTION',
                                 provisional_since=NOW()
                           WHERE route='{$rt}' AND provisional_reversible=1");
            $conn->query("UPDATE nav_pending_closure
                             SET application_state='PROVISIONALLY_APPLIED_NO_OBJECTION',
                                 provisional_since=NOW()
                           WHERE route='{$rt}'");
            $done['provisional']++;
        }
    }
}

/* ── التقرير: المطبَّقُ مؤقَّتًا **منفصلًا** عن المعتمَدِ فعلًا (نصُّ القرار) ── */
echo "════ كنّاسُ المعلَّقِ — محورا القرارِ والتطبيقِ منفصلان ════\n";
echo "  الوقت: {$now} · مهلةُ النائبِ: " . ESCALATION_DAYS . " يومان · موضعُ التصعيد: الدور " . DEPUTY_ROLE . "\n";
echo "  معلَّقٌ كلُّه: {$stat['total']} · داخلَ المهلة: {$stat['within']}\n";
echo "  ينتقل الآن إلى «متأخِّرُ المراجعة» + تصعيد: {$stat['overdue_new']}\n";
echo "  في مهلةِ النائبِ الآن: {$stat['escalating']}\n";
echo "  يستحقُّ تطبيقًا مؤقَّتًا: {$stat['provisional_new']} · مطبَّقٌ مؤقَّتًا سلفًا: {$stat['provisional_already']}\n";
echo "  ممنوعٌ لخروجِه عن النطاق: {$stat['blocked_scope']}\n";
if ($APPLY) { echo "  ▸ نُفِّذ: متأخِّر={$done['overdue']} · مؤقَّت={$done['provisional']}\n"; }
else { echo "  ▸ جردٌ بلا كتابة — أضِفْ --apply للتنفيذ\n"; }

$r = $conn->query("SELECT decision_state, application_state, COUNT(*) n FROM nav_canonical
                    GROUP BY decision_state, application_state ORDER BY decision_state");
echo "\n▐ الحصيلةُ الحاكمة — ولا يُخلط المحوران\n";
$approved = 0; $prov = 0;
while ($r && ($x = $r->fetch_assoc())) {
    printf("  قرار=%-22s تطبيق=%-38s %s\n", $x['decision_state'], $x['application_state'], $x['n']);
    if ($x['decision_state'] === 'APPROVED') { $approved += (int) $x['n']; }
    if ($x['application_state'] === 'PROVISIONALLY_APPLIED_NO_OBJECTION') { $prov += (int) $x['n']; }
}
echo "\n  ◆ **اعتُمد فعلًا: {$approved}**  ·  **طُبِّق مؤقَّتًا بلا اعتراضٍ: {$prov}**\n";
echo "  (والرقمانِ لا يُجمعان في نسبةٍ واحدةٍ أبدًا — نصُّ القرارِ: يُعلَنان منفصلَين)\n";
$noActor = (int) $conn->query("SELECT COUNT(*) c FROM nav_canonical WHERE decision_state='APPROVED'
                                AND decided_by IS NULL AND (decision_source IS NULL OR decision_source='')")->fetch_assoc()['c'];
echo "  محسومٌ بلا فاعلٍ ولا مصدر: {$noActor}" . ($noActor === 0 ? " ✔\n" : " ✗\n");
exit(0);
