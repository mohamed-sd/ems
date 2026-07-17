<?php
/**
 * cron_requests.php — الحوكمة الزمنية لبوابة الطلب المالي D05 (§8.2/§8.4)
 * ───────────────────────────────────────────────────────────────────────────
 * (أ) مصفوفة التصعيد: تذكير عند 75% من مدة المرحلة → تنبيه عند الانقضاء →
 *     تصعيد عند ضعف المدة — يُدوَّن escalate في السجل الإلحاقي ويُرفع
 *     escalation_level (0..3). بداية المرحلة تُستنبط: due - مدة المرحلة.
 * (ب) الانتهاء الآلي: مسودة راكدة 30 يومًا أو مُعادٌ بلا استكمالٍ 14 يومًا →
 *     expired (بملاحظة إنذارٍ قبلها بثلاثة أيام).
 * قناة نظامٍ عابرة للشركات (نمط cron_events): CLI أو مفتاح REQUESTS_CRON_KEY
 * من .env — fail-closed: مفتاحٌ غير مضبوط = لا مسار ويب إطلاقًا.
 */

$IS_CLI = (PHP_SAPI === 'cli');
require_once __DIR__ . '/config.php';

if (!$IS_CLI) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    $expected = (string) ems_env('REQUESTS_CRON_KEY', '');
    if ($expected === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        exit('forbidden');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

require_once __DIR__ . '/FinRequests/_finreq_helpers.php';

$now = time();
$escalated = 0; $reminded = 0; $expired = 0; $prenoticed = 0;

/** قيد سجلٍّ إلحاقي بهوية النظام (بلا جلسة) */
function cronreq_log(mysqli $conn, $company_id, $request_id, $event_type, $body, $old = null, $new = null)
{
    $stmt = $conn->prepare(
        'INSERT INTO fin_request_events (company_id, request_id, event_type, actor_user_id, body, old_value, new_value)
         VALUES (?, ?, ?, NULL, ?, ?, ?)'
    );
    $stmt->bind_param('iissss', $company_id, $request_id, $event_type, $body, $old, $new);
    $stmt->execute();
    $stmt->close();
}

/** إشعارٌ موجَّهٌ بهوية النظام (fin_notifications) بمنع تكرارٍ يومي — قناة cron العابرة */
function cronreq_notify(mysqli $conn, $company_id, $target_level, $title, $link = null)
{
    $title = mb_substr($title, 0, 200);
    $q = $conn->prepare('SELECT COUNT(*) FROM fin_notifications WHERE company_id = ? AND target_level = ? AND title = ? AND DATE(created_at) = CURDATE()');
    $q->bind_param('iss', $company_id, $target_level, $title);
    $q->execute();
    $dup = intval($q->get_result()->fetch_row()[0]);
    $q->close();
    if ($dup > 0) { return; }
    $stmt = $conn->prepare('INSERT INTO fin_notifications (company_id, target_level, title, link) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $company_id, $target_level, $title, $link);
    $stmt->execute();
    $stmt->close();
}

// ═══ (أ) التصعيد الزمني — الحالات النشطة ذات استحقاقٍ مختوم ═══
$res = $conn->query(
    "SELECT id, company_id, request_no, request_type, source_module, amount, currency,
            project_id, contract_id, equipment_id, created_by,
            state, need_class, sla_due_at, escalation_level, event_id
       FROM fin_requests
      WHERE sla_due_at IS NOT NULL
        AND state IN ('under_review', 'pending_approval')"
);
while ($r = $res->fetch_assoc()) {
    $stage = ($r['state'] === 'under_review') ? 'dept_review'
        : (empty($r['event_id']) ? 'acct_review' : 'finance');
    $hours = finreq_sla_hours($stage, strval($r['need_class']));
    if ($hours === null) { continue; }
    $due = strtotime($r['sla_due_at']);
    $level = 0;
    if ($now >= $due + $hours * 3600) { $level = 3; }          // ضعف المدة → تصعيد
    elseif ($now >= $due) { $level = 2; }                       // الانقضاء → تنبيه
    elseif ($now >= $due - intval(0.25 * $hours * 3600)) { $level = 1; } // 75% → تذكير
    if ($level > intval($r['escalation_level'])) {
        $upd = $conn->prepare('UPDATE fin_requests SET escalation_level = ? WHERE id = ? AND escalation_level < ?');
        $upd->bind_param('iii', $level, $r['id'], $level);
        $upd->execute();
        $changed = $upd->affected_rows;
        $upd->close();
        if ($changed === 1) {
            $labels = array(1 => 'تذكير (75% من المدة)', 2 => 'تنبيه (انقضت المدة — المالك ورئيسه)', 3 => 'تصعيد (ضعف المدة — لجنة المراجعة)');
            cronreq_log($conn, intval($r['company_id']), intval($r['id']),
                'escalate', $labels[$level] . ' — مرحلة ' . $stage,
                strval($r['escalation_level']), strval($level));
            // §9.3: حقيقة request.escalated على الجذر عند التنبيه/التصعيد (لا التذكير)
            if ($level >= 2) {
                finreq_publish_fact($conn, $r, 'request.escalated',
                    'fact:esc:' . intval($r['id']) . ':l' . $level,
                    array('level' => $level, 'stage' => $stage));
            }
            if ($level === 3) {
                cronreq_notify($conn, intval($r['company_id']), 'finance_manager',
                    'تصعيدٌ للمستوى الثالث: ' . $r['request_no'] . ' تجاوز ضعف مدة مرحلته — يتصدر لوحة الاختناق',
                    'FinRequests/cycle_time_board.php');
            }
            if ($level === 1) { $reminded++; } else { $escalated++; }
        }
    }
}

// ═══ (ب) الانتهاء الآلي + إنذاره المسبق (§8.4) ═══
$expiry_rules = array(
    array('state' => 'draft',    'days' => 30),
    array('state' => 'returned', 'days' => 14),
);
foreach ($expiry_rules as $rule) {
    // إنذارٌ مسبق: قبل الانتهاء بثلاثة أيام (مرةً واحدة — يُتحقق من عدم تكراره في السجل)
    $pre = $conn->query(
        "SELECT fr.id, fr.company_id, fr.request_no
           FROM fin_requests fr
          WHERE fr.state = '{$rule['state']}'
            AND fr.updated_at <= DATE_SUB(NOW(), INTERVAL " . ($rule['days'] - 3) . " DAY)
            AND fr.updated_at > DATE_SUB(NOW(), INTERVAL {$rule['days']} DAY)
            AND NOT EXISTS (
                SELECT 1 FROM fin_request_events fe
                WHERE fe.request_id = fr.id AND fe.event_type = 'note'
                  AND fe.body LIKE 'إنذار انتهاء%'
            )"
    );
    while ($p = $pre->fetch_assoc()) {
        cronreq_log($conn, intval($p['company_id']), intval($p['id']), 'note',
            'إنذار انتهاء: الطلب راكدٌ وسينتهي آليًّا خلال ثلاثة أيامٍ ما لم يُستكمل');
        $prenoticed++;
    }
    // الانتهاء الفعلي — انتقالٌ آليٌّ محروسٌ بالحالة (هوية النظام، خارج أدوار المحرّك)
    $ex = $conn->query(
        "SELECT id, company_id, state FROM fin_requests
          WHERE state = '{$rule['state']}'
            AND updated_at <= DATE_SUB(NOW(), INTERVAL {$rule['days']} DAY)"
    );
    while ($e = $ex->fetch_assoc()) {
        $upd = $conn->prepare("UPDATE fin_requests SET state = 'expired' WHERE id = ? AND state = ?");
        $upd->bind_param('is', $e['id'], $e['state']);
        $upd->execute();
        $done = $upd->affected_rows;
        $upd->close();
        if ($done === 1) {
            cronreq_log($conn, intval($e['company_id']), intval($e['id']), 'expire',
                'انتهاءٌ آليٌّ: ركودٌ تجاوز ' . $rule['days'] . ' يومًا', $e['state'], 'expired');
            $expired++;
        }
    }
}

// ═══ (ج) كنس الاشتقاق: حالة الطلب تلحق حالة حدثها دوريًّا (لا انتظار فتح شاشة) ═══
//        وعند بلوغ «مغلق» تُنشر حقيقة request.closed (§9.3) — ختام خيط الدورة.
$synced = 0; $closed_facts = 0;
$sweep = $conn->query(
    "SELECT fr.id, fr.company_id, fr.request_no, fr.request_type, fr.source_module,
            fr.amount, fr.currency, fr.project_id, fr.contract_id, fr.equipment_id,
            fr.created_by, fr.need_class, fr.state, fe.state AS ev_state, fe.id AS ev_id
       FROM fin_requests fr
       JOIN fin_financial_events fe ON fe.id = fr.event_id
      WHERE fr.state IN ('pending_approval', 'approved', 'posted', 'paid', 'collected')
        AND fe.state IN ('approved', 'posted', 'settled', 'closed', 'rejected')"
);
while ($s = $sweep->fetch_assoc()) {
    $map = array(
        'approved' => 'approved', 'posted' => 'posted',
        'settled' => (strval($s['request_type']) === 'collection') ? 'collected' : 'paid',
        'closed' => 'closed', 'rejected' => 'rejected',
    );
    $derived = $map[strval($s['ev_state'])];
    if ($derived === strval($s['state'])) { continue; }
    // قاعدة المسار المركّب (§6.2): لا يُغلق الأصلُ وفروعُه معلّقة — يُمسَك الإقفال
    if ($derived === 'closed') {
        $lc = $conn->prepare("SELECT COUNT(*) FROM fin_requests WHERE parent_request_id = ? AND state NOT IN
            ('closed','archived','rejected','withdrawn','cancelled','expired','merged','paid','collected')");
        $lc->bind_param('i', $s['id']);
        $lc->execute();
        $live = intval($lc->get_result()->fetch_row()[0]);
        $lc->close();
        if ($live > 0) { continue; }
    }
    $upd = $conn->prepare('UPDATE fin_requests SET state = ? WHERE id = ? AND state = ?');
    $upd->bind_param('sis', $derived, $s['id'], $s['state']);
    $upd->execute();
    $done = $upd->affected_rows;
    $upd->close();
    if ($done === 1) {
        cronreq_log($conn, intval($s['company_id']), intval($s['id']), 'system',
            'اشتقاق الحالة من حدث D04 #' . intval($s['ev_id']) . ' (كنس دوري)', strval($s['state']), $derived);
        $synced++;
        if ($derived === 'closed') {
            finreq_publish_fact($conn, $s, 'request.closed', 'fact:closed:' . intval($s['id']));
            $closed_facts++;
        }
    }
}

// ═══ (د) مهلة الطارئ (§8.3 — الشرط الثالث): استكمال الدورة رجعيًّا خلال 72 ساعة ═══
//        استثناءٌ معتمدٌ ودورته لم تكتمل بعد المهلة → المستوى الثالث + مساءلة.
$exc_overdue = 0;
$exq = $conn->query(
    "SELECT fr.id, fr.company_id, fr.request_no, fr.escalation_level,
            (SELECT MAX(x.created_at) FROM fin_request_events x
              WHERE x.request_id = fr.id AND x.event_type = 'exception') AS exc_at
       FROM fin_requests fr
      WHERE fr.is_exception = 1 AND fr.exception_type = 'emergency_execute'
        AND fr.state IN ('draft', 'returned', 'under_review', 'pending_approval', 'suspended')
        AND NOT EXISTS (
            SELECT 1 FROM fin_request_events oe
            WHERE oe.request_id = fr.id AND oe.event_type = 'exception_overdue'
        )"
);
while ($x = $exq->fetch_assoc()) {
    if ($x['exc_at'] === null || strtotime($x['exc_at']) > $now - 72 * 3600) { continue; }
    if (intval($x['escalation_level']) < 3) {
        $upd = $conn->prepare('UPDATE fin_requests SET escalation_level = 3 WHERE id = ? AND escalation_level < 3');
        $upd->bind_param('i', $x['id']);
        $upd->execute();
        $upd->close();
    }
    cronreq_log($conn, intval($x['company_id']), intval($x['id']), 'exception_overdue',
        'خرق مهلة الطارئ: 72 ساعةً انقضت منذ الاعتماد والدورة لم تُستكمل رجعيًّا — مساءلةٌ إلزامية (§8.3)');
    cronreq_notify($conn, intval($x['company_id']), 'finance_manager',
        'خرق مهلة الطارئ: ' . $x['request_no'] . ' لم تُستكمل دورته خلال 72 ساعة',
        'FinRequests/cycle_time_board.php');
    $exc_overdue++;
}

// ═══ (هـ) الأرشفة الآلية — المرحلة ⑬ (§3.3): «مغلق/مرفوض ← مؤرشف · النظام» ═══
//        سجلٌّ محفوظٌ للاطلاع والتدقيق لا يُحذف: الطلب المنقضية دورته (مغلق أو
//        مرفوض) الراكد 30 يومًا يُؤرشف بهوية النظام — والذاكرة المؤسسية تبقى.
$ARCHIVE_AFTER_DAYS = 30;
$archived_count = 0;
$arq = $conn->query(
    "SELECT id, company_id, request_no, state FROM fin_requests
      WHERE state IN ('closed', 'rejected')
        AND updated_at <= DATE_SUB(NOW(), INTERVAL {$ARCHIVE_AFTER_DAYS} DAY)"
);
while ($a = $arq->fetch_assoc()) {
    $upd = $conn->prepare("UPDATE fin_requests SET state = 'archived' WHERE id = ? AND state = ?");
    $upd->bind_param('is', $a['id'], $a['state']);
    $upd->execute();
    $done = $upd->affected_rows;
    $upd->close();
    if ($done === 1) {
        cronreq_log($conn, intval($a['company_id']), intval($a['id']), 'archive',
            'أرشفةٌ آلية (المرحلة ⑬): انقضت الدورة وركد ' . $ARCHIVE_AFTER_DAYS
            . ' يومًا — سجلٌّ محفوظٌ للاطلاع والتدقيق لا يُحذف', $a['state'], 'archived');
        $archived_count++;
    }
}

echo "cron_requests: تذكير={$reminded} · تصعيد/تنبيه={$escalated} · إنذار مسبق={$prenoticed} · منتهٍ={$expired}"
    . " · اشتقاق={$synced} · إقفالات منشورة={$closed_facts} · خرق طارئ={$exc_overdue} · مؤرشف={$archived_count}\n";
