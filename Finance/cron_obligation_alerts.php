<?php
/**
 * Finance/cron_obligation_alerts.php — كرونُ تنبيهاتِ الالتزامات (FIN-OBL-01 §٤-٢٢)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكمُ الذي يُنفذه — OBL-0125:
 *   «◆ التنبيهُ المُهمَلُ بعد مهلتِه ينشر إشارةَ خطرٍ إلى إدارةِ المخاطرِ تدخل
 *    الفرزَ الرباعيَّ — **فالتنبيهُ الذي لا يُصعَّد لا يُنذر**.»
 *
 *   وسجلُّ التنبيهاتِ الاثني عشرَ (`fin_obl_alerts`) كان قائمًا بلا مُطلِق:
 *   جدولٌ يصف متى يُطلق كلُّ تنبيهٍ ووجهتَه وخطرَ إهماله — ولا شيء يفحص
 *   الاستحقاقاتِ ويُطلقه. فهذا هو المُطلِق.
 *
 * ما يفعله في كلِّ دورة:
 *   ① يفحص جدولَ الاستحقاقاتِ الحيَّ ويُطلق ما حلَّ وقتُه من AL-01..AL-12
 *   ② OR-05: يُرحِّل المستحقَّ المتأخرَ إلى الذممِ الدائنة
 *   ③ OR-03: يُعيد التصنيفَ قصيرًا وطويلًا (يُعاد كلَّ إقفال)
 *   ④ OBL-0125: يُصعِّد التنبيهَ المُهمَلَ بعد مهلتِه إشارةَ خطر
 *
 * ◆ العطالة: `fin_obl_alert_log` عليه مفتاحٌ فريدٌ (كيان · رمز · موضوع) —
 *   فتشغيلُه مرتين في اليومِ لا يُكرِّر تنبيهًا ولا مهمة.
 *
 * التشغيل: php Finance/cron_obligation_alerts.php
 *          أو GET ?key=<OBL_ALERTS_CRON_KEY من .env>
 * fail-closed: مفتاحٌ غيرُ مضبوطٍ = لا مسارَ ويبَّ إطلاقًا (CLI لا يتأثر).
 */

$IS_CLI = (PHP_SAPI === 'cli');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_obligation_alerts.php'); // INJ-0025: لا تُشغَّل من المتصفّح

if (!$IS_CLI) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    $expected = function_exists('ems_env') ? (string) ems_env('OBL_ALERTS_CRON_KEY', '') : '';
    if ($expected === '' || !hash_equals($expected, $key)) { http_response_code(403); exit('forbidden'); }
    header('Content-Type: text/plain; charset=UTF-8');
}

require_once __DIR__ . '/../app/Services/Work/WorkItemService.php';
require_once __DIR__ . '/../app/Services/Finance/ObligationEngine.php';

$OE  = 'App\Services\Finance\ObligationEngine';
$WIS = 'App\Services\Work\WorkItemService';
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');
$fired = 0; $escalated = 0; $moved = 0; $reclassified = 0;

/** التنبيهاتُ المعرَّفةُ بمهلةِ كلٍّ ووجهته. */
$ALERTS = array();
$r = $conn->query("SELECT code, title, fires_when, destination, risk_if_ignored, lead_days
                     FROM fin_obl_alerts WHERE active = 1");
while ($r && $x = $r->fetch_assoc()) { $ALERTS[$x['code']] = $x; }
if (!$ALERTS) { echo "[obl-alerts] لا تنبيهات معرفة — شغل u13_seed أولا\n"; exit(0); }

/** الكياناتُ التي لها جدولُ التزاماتٍ حيّ. */
$COMPANIES = array();
$r = $conn->query("SELECT DISTINCT company_id FROM fin_obl_register WHERE state = 'active'");
while ($r && $x = $r->fetch_row()) { $COMPANIES[] = (int) $x[0]; }

/** وحدةٌ تنظيميةٌ للنطاق — `work_items` يرفض عنصرًا بلا نطاق (WF-02). */
function obl_org($conn, $co)
{
    static $c = array();
    if (isset($c[$co])) { return $c[$co]; }
    $r = $conn->query("SELECT unit_id FROM org_units WHERE company_id = " . (int) $co . " AND active = 1 ORDER BY unit_id LIMIT 1");
    $x = $r ? $r->fetch_row() : null;
    if (!$x) { $r = $conn->query("SELECT unit_id FROM org_units ORDER BY unit_id LIMIT 1"); $x = $r ? $r->fetch_row() : null; }
    $c[$co] = $x ? (int) $x[0] : null;
    return $c[$co];
}

/** محاسبُ التخصصِ المسؤولُ عن التزامٍ — وجهةُ التنبيهِ حين تكون «محاسبَ التخصص». */
function obl_owner($conn, $co, $oblId)
{
    $r = $conn->query("SELECT l.accountant_id FROM fin_obl_register o
                         LEFT JOIN fin_routing_log l ON l.source_ref = o.contract_ref AND l.company_id = o.company_id
                        WHERE o.id = " . (int) $oblId . " LIMIT 1");
    $x = $r ? $r->fetch_assoc() : null;
    if ($x && !empty($x['accountant_id'])) { return (int) $x['accountant_id']; }
    /* وإلا فرئيسُ الحسابات — ولا يُترك التنبيهُ بلا مستلِم. */
    $r = $conn->query("SELECT id FROM users WHERE company_id = " . (int) $co
                    . " AND (role_id = 31 OR role = '31') AND status = 'active' ORDER BY id LIMIT 1");
    $x = $r ? $r->fetch_row() : null;
    return $x ? (int) $x[0] : 0;
}

/**
 * يُطلق تنبيهًا بعطالةٍ — ويولّد مهمةً حين يستوجب فعلًا.
 * @return bool أأُطلق جديدًا؟
 */
function obl_fire($conn, $co, $code, $alert, $subjectRef, $title, $oblId, $schedId, $dueAt)
{
    $st = $conn->prepare("INSERT IGNORE INTO fin_obl_alert_log
            (company_id, alert_code, obligation_id, schedule_id, subject_ref, to_user_id,
             work_item_id, fired_at, due_at, state, created_by)
            VALUES (?,?,?,?,?,?,NULL,NOW(),?,'open',0)");
    if (!$st) { return false; }
    $to = obl_owner($conn, $co, $oblId);
    $toN = $to > 0 ? $to : null;
    $st->bind_param('isiisis', $co, $code, $oblId, $schedId, $subjectRef, $toN, $dueAt);
    if (!$st->execute()) { $st->close(); return false; }
    $isNew = $st->affected_rows > 0;
    $logId = $st->insert_id;
    $st->close();
    if (!$isNew || $to <= 0) { return false; }

    /* BR-02 · OBL-0003: ما يستوجب فعلًا يصير مهمةً بمهلةٍ ومسؤول. */
    $res = \App\Services\Work\WorkItemService::create($conn, array(
        'company_id'       => $co,
        'item_type'        => 'task',
        'title'            => mb_substr($alert['title'] . ' — ' . $title, 0, 300),
        'details'          => $alert['fires_when'] . ' · الوجهة: ' . $alert['destination']
                            . ' · الخطر عند الإهمال: ' . $alert['risk_if_ignored'],
        'source_type'      => 'SRC-07',
        'source_ref'       => 'obl_alert:' . $code . ':' . $subjectRef,
        'action_code'      => 'fin.alert.' . strtolower(str_replace('-', '', $code)),
        'org_unit_id'      => obl_org($conn, $co),
        'assigned_user_id' => $to,
        'owner_user_id'    => $to,
        'due_at'           => $dueAt ?: date('Y-m-d H:i:s', strtotime('+3 days')),
        'deliverable'      => 'معالجة ما نبه عليه التنبيه قبل مهلته',
        'evidence_required' => 'أثر المعالجة في جدول الاستحقاق أو الذمم',
        'priority'         => 'P2',
        'created_by'       => 0,
        'created_capacity' => 'كرون تنبيهات الالتزامات',
    ));
    if (!empty($res['ok']) && $logId > 0) {
        $u = $conn->prepare("UPDATE fin_obl_alert_log SET work_item_id = ? WHERE id = ?");
        if ($u) { $wid = intval($res['id']); $u->bind_param('ii', $wid, $logId); $u->execute(); $u->close(); }
    }
    return true;
}

foreach ($COMPANIES as $co) {
    /* ── ① AL-01/AL-04: استحقاقٌ قادمٌ بمهلةِ التنبيهِ المعلَنة ─────────── */
    foreach (array('AL-01' => 'receivable', 'AL-04' => 'payable') as $code => $side) {
        if (!isset($ALERTS[$code])) { continue; }
        $lead = max(1, (int) $ALERTS[$code]['lead_days']);
        $q = $conn->query(
            "SELECT s.id, s.obligation_id, s.due_date, s.l1_commitment, o.contract_ref, o.counterparty
               FROM fin_obl_schedule s JOIN fin_obl_register o ON o.id = s.obligation_id
              WHERE s.company_id = {$co} AND o.side = '{$side}' AND o.state = 'active'
                AND s.state = 'scheduled'
                AND s.due_date >= '{$today}'
                AND s.due_date <= DATE_ADD('{$today}', INTERVAL {$lead} DAY)");
        while ($q && $x = $q->fetch_assoc()) {
            if (obl_fire($conn, $co, $code, $ALERTS[$code], 'SCH-' . $x['id'],
                    $x['contract_ref'] . ' · ' . $x['due_date'] . ' · ' . $x['l1_commitment'],
                    (int) $x['obligation_id'], (int) $x['id'], $x['due_date'] . ' 12:00:00')) { $fired++; }
        }
    }

    /* ── ② AL-03/AL-05: المتأخرُ — وOR-05 يُرحِّله إلى الذممِ الدائنة ───── */
    foreach (array('AL-03' => 'receivable', 'AL-05' => 'payable') as $code => $side) {
        if (!isset($ALERTS[$code])) { continue; }
        $q = $conn->query(
            "SELECT s.id, s.obligation_id, s.due_date, s.l1_commitment, o.contract_ref
               FROM fin_obl_schedule s JOIN fin_obl_register o ON o.id = s.obligation_id
              WHERE s.company_id = {$co} AND o.side = '{$side}' AND o.state = 'active'
                AND s.state IN ('scheduled','recognized','invoiced')
                AND s.due_date < '{$today}' AND s.settled < s.l1_commitment");
        while ($q && $x = $q->fetch_assoc()) {
            if (obl_fire($conn, $co, $code, $ALERTS[$code], 'SCH-' . $x['id'],
                    $x['contract_ref'] . ' · تأخر منذ ' . $x['due_date'],
                    (int) $x['obligation_id'], (int) $x['id'], $now)) { $fired++; }
        }
    }
    $sw = $OE::sweepOverdue($conn, $co, $today);
    $moved += (int) ($sw['moved'] ?? 0);

    /* ── ③ OR-03: يُعاد التصنيفُ كلَّ دورةٍ آليًّا ──────────────────────── */
    $rc = $OE::reclassify($conn, $co, $today);
    $reclassified += (int) ($rc['moved_to_short'] ?? 0);

    /* ── ④ AL-10: الكسريةُ تُراجَع عند التوليد ─────────────────────────── */
    if (isset($ALERTS['AL-10'])) {
        $q = $conn->query(
            "SELECT s.id, s.obligation_id, s.period_start, s.period_end, o.contract_ref
               FROM fin_obl_schedule s JOIN fin_obl_register o ON o.id = s.obligation_id
              WHERE s.company_id = {$co} AND o.state = 'active' AND s.is_partial = 1
                AND s.state = 'scheduled'
                AND o.generated_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)");
        while ($q && $x = $q->fetch_assoc()) {
            if (obl_fire($conn, $co, 'AL-10', $ALERTS['AL-10'], 'SCH-' . $x['id'],
                    $x['contract_ref'] . ' · كسر ' . $x['period_start'] . '→' . $x['period_end'],
                    (int) $x['obligation_id'], (int) $x['id'], $now)) { $fired++; }
        }
    }

    /* ── ⑤ OBL-0125: المُهمَلُ بعد مهلتِه يُصعَّد إشارةَ خطر ───────────── */
    $q = $conn->query(
        "SELECT id, alert_code, subject_ref, obligation_id FROM fin_obl_alert_log
          WHERE company_id = {$co} AND state = 'open'
            AND due_at IS NOT NULL AND due_at < NOW()");
    while ($q && $x = $q->fetch_assoc()) {
        $conn->query("UPDATE fin_obl_alert_log SET state='escalated', escalated_at=NOW() WHERE id=" . (int) $x['id']);
        if (function_exists('log_security_event')) {
            log_security_event('OBL_ALERT_ESCALATED',
                $x['alert_code'] . ' · ' . $x['subject_ref'] . ' — تنبيه مهمل بعد مهلته (OBL-0125)');
        }
        $escalated++;
    }
}

echo sprintf("[obl-alerts %s] كيانات=%d أطلق=%d صعد=%d رحل للذمم=%d أعيد تصنيفه=%d\n",
    date('Y-m-d H:i:s'), count($COMPANIES), $fired, $escalated, $moved, $reclassified);
