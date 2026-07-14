<?php
/**
 * REV-08 · البند 2 — برهان المفقود (اليتيم) وجدولة المُصالِح (يُشغَّل CLI فقط)
 * ───────────────────────────────────────────────────────────────────────────
 * يُثبت — عبر المُصالِح الحيّ في cron_events.php، لا محاكاة — أن اعتمادًا نهائيًّا
 * (المستوى 4) نُفِّذ دون نشرِ حدثه (انهيار/فشلٌ بعد الاعتماد) **يُعاد نشرُه تلقائيًّا**
 * في دورة cron التالية، وأن عدّاد اليتيم يبقى صفرًا بعد المصالحة.
 *
 * الجداول والبصمة الفعلية (مقيسة من cron_events.php:42-72 وtimesheet_event_hook.php:83):
 *   المصدر: timesheet_approvals (approval_level=4, status=1, approved_at ضمن 7 أيام)
 *   البصمة: equipment.hour_logged:timesheet:{timesheet_id}:a{approval.id}
 *
 * ذاتيُّ التنظيف: يُعيد الدفتر وجداول الاعتماد لحالتها السابقة بالضبط عبر root
 * (المؤشّر=141 متقدّمٌ على أحداث الدفتر، فالموزّع لا ينشر مشتقًّا في هذه الدورة —
 * التنظيف حدثُ المُصالِح وحده). يُرفَق مخرَجُه في ردّ REV-08.
 *
 * التشغيل:  php tests/proof_orphan.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/config.php';

$hooks = (string) ems_env('EMS_EVENT_HOOKS', 'off');
if ($hooks !== 'publish') {
    fwrite(STDERR, "SKIP: EMS_EVENT_HOOKS='$hooks' (يلزم 'publish' لعمل المُصالِح)\n");
    exit(2);
}

$root = new mysqli(ems_env('DB_HOST'), 'root', '', ems_env('DB_NAME'));
if ($root->connect_errno) { fwrite(STDERR, "FATAL: root connect\n"); exit(1); }
$root->set_charset('utf8mb4');

$COMPANY = 4;
$ledgerBefore = (int) $root->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0];
$tsId = 0; $apprId = 0;

$cleanup = function () use ($root, &$tsId, &$apprId, $ledgerBefore) {
    if ($tsId > 0) {
        $root->query("DELETE FROM fin_financial_events
            WHERE idempotency_key LIKE 'equipment.hour_logged:timesheet:{$tsId}:%'");
        $root->query("DELETE FROM timesheet_approvals WHERE id = {$apprId}");
        $root->query("DELETE FROM timesheet WHERE id = {$tsId}");
        foreach (array('fin_financial_events', 'timesheet_approvals', 'timesheet') as $t) {
            $mx = (int) $root->query("SELECT COALESCE(MAX(id),0) FROM `$t`")->fetch_row()[0];
            $root->query("ALTER TABLE `$t` AUTO_INCREMENT = " . ($mx + 1));
        }
    }
    $after = (int) $root->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0];
    echo "تنظيف: الدفتر عاد إلى {$after} (المتوقع {$ledgerBefore}) " . ($after === $ledgerBefore ? "✓" : "✗ !!") . "\n";
};
register_shutdown_function($cleanup);

// ── بذر ساعةٍ + اعتمادٍ نهائيٍّ (المستوى 4) بلا نشرِ حدثٍ (محاكاة الانهيار بعد الاعتماد) ──
$stmt = $root->prepare("INSERT INTO timesheet
    (operator, employee_id, shift, `date`, `type`, time_notes, company_id, executed_hours, total_work_hours, shift_hours)
    VALUES ('', '', 'ص', '2026-07-14', 'عادي', '', ?, 5.5, 8, 8)");
$stmt->bind_param('i', $COMPANY);
$stmt->execute();
$tsId = (int) $root->insert_id;
$stmt->close();

$stmt = $root->prepare("INSERT INTO timesheet_approvals
    (timesheet_id, company_id, approval_level, approved_by, approved_by_name, approved_at, status)
    VALUES (?, ?, 4, 1, 'PROOF', NOW(), 1)");
$stmt->bind_param('ii', $tsId, $COMPANY);
$stmt->execute();
$apprId = (int) $root->insert_id;
$stmt->close();

$fp = "equipment.hour_logged:timesheet:{$tsId}:a{$apprId}";
$before = (int) $root->query("SELECT COUNT(*) FROM fin_financial_events WHERE idempotency_key = '{$fp}'")->fetch_row()[0];
echo "بذر: timesheet #{$tsId} + اعتماد نهائيّ #{$apprId} (بلا حدث)\n";
echo "  حدثُ البصمة قبل المُصالِح: {$before} (المتوقع 0)\n\n";

// ── تشغيل المُصالِح كما يشغّله cron تمامًا ──
echo "── تشغيل cron_events.php (المُصالِح) ──\n";
passthru(PHP_BINARY . ' ' . escapeshellarg(dirname(__DIR__) . '/cron_events.php'));
echo "──────────────────────────────────────\n\n";

// ── التحقّق (1): أعاد المُصالِح النشر ──
$after = (int) $root->query("SELECT COUNT(*) FROM fin_financial_events WHERE idempotency_key = '{$fp}'")->fetch_row()[0];
$q = $root->query("SELECT quantity FROM fin_financial_events WHERE idempotency_key = '{$fp}'")->fetch_row();
echo "حدثُ البصمة بعد المُصالِح: {$after} (المتوقع 1)" . ($q ? " · quantity={$q[0]}" : "") . "\n";

// ── التحقّق (2): عدّاد اليتيم (استعلام bus_monitor نفسه) = 0 لهذه الساعة ──
$orphans = (int) $root->query(
    "SELECT COUNT(*) FROM fin_financial_events fe
     WHERE fe.event_key = 'equipment.hour_logged' AND COALESCE(fe.is_deleted,0)=0
       AND fe.entity_id = {$tsId}
       AND NOT EXISTS (
             SELECT 1 FROM timesheet_approvals ta
             WHERE ta.timesheet_id = fe.entity_id AND ta.approval_level = 4 AND ta.status = 1
               AND CONCAT('equipment.hour_logged:timesheet:', ta.timesheet_id, ':a', ta.id) = fe.idempotency_key)"
)->fetch_row()[0];
echo "عدّاد اليتيم لهذه الساعة (منطق bus_monitor): {$orphans} (المتوقع 0)\n\n";

$pass = ($before === 0) && ($after === 1) && ($orphans === 0);
echo ($pass
    ? "PASS: المُصالِح أعاد نشر الحدث المفقود، ولا يتيم\n"
    : "FAIL: before={$before} after={$after} orphans={$orphans}\n");

exit($pass ? 0 : 1);
