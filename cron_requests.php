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

// ═══ (أ) التصعيد الزمني — الحالات النشطة ذات استحقاقٍ مختوم ═══
$res = $conn->query(
    "SELECT id, company_id, request_no, state, need_class, sla_due_at, escalation_level, event_id
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

echo "cron_requests: تذكير={$reminded} · تصعيد/تنبيه={$escalated} · إنذار مسبق={$prenoticed} · منتهٍ={$expired}\n";
