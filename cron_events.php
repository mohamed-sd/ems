<?php
/**
 * عامل توزيع الأحداث المؤسسي — cron (K4 · نمط CLI/GET بمفتاح كبقية عمال cron)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل: php cron_events.php   أو   GET ?key=<EVENTS_CRON_KEY من .env>
 * fail-closed: مفتاح غير مضبوط في .env = لا مسار ويب إطلاقًا (CLI لا يتأثر).
 *
 * ⚠ K4: لا مستهلك مسجَّلًا بعد — تسجيل مستهلك المالية الأول يجري في K5 حصرًا
 *   (الحدّ الصارم: صفر مستدعٍ حيٍّ للناقل قبل الهيكل العامل). تشغيل هذا الملف
 *   الآن لا-عمل آمن (يطبع الحالة وينصرف).
 */

$IS_CLI = (PHP_SAPI === 'cli');
require_once __DIR__ . '/config.php';

if (!$IS_CLI) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    $expected = (string) ems_env('EVENTS_CRON_KEY', '');
    if ($expected === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        exit('forbidden');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

require_once __DIR__ . '/app/Core/ServerId.php';
require_once __DIR__ . '/app/Core/EventValidationException.php';
require_once __DIR__ . '/app/Core/EventPublisher.php';
require_once __DIR__ . '/app/Core/EventDispatcher.php';

$dispatcher = new \App\Core\EventDispatcher($conn);

// ═══ K5 · المُصالِح (Reconciler) — Outbox نهائيّ التحقق دون لمس مسار الاعتماد ═══
// (أ) نشرٌ لاحق لما فاته الخطّاف (انهيار/فشل بعد الاعتماد): يعمل في publish فقط.
// (ب) كاشف الأحداث اليتيمة (قرار المستخدم — الخيار أ): hour_logged منشورٌ لجيل
//     اعتمادٍ زال بالرفض ⇒ إنذارٌ صريح EVENT_ORPHANED_ALERT (رصدٌ لا محاسبة —
//     المعالجة بحدثٍ عكسيٍّ معمَّض = بند K5.1 المستقل).
$hooksMode = (string) ems_env('EMS_EVENT_HOOKS', 'off');

if ($hooksMode === 'publish') {
    require_once __DIR__ . '/includes/timesheet_event_hook.php';
    $missed = $conn->query(
        "SELECT ta.id AS gen, ta.timesheet_id, ta.approved_by
         FROM timesheet_approvals ta
         WHERE ta.approval_level = 4 AND ta.status = 1
           AND ta.approved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
           AND NOT EXISTS (
                 SELECT 1 FROM fin_financial_events fe
                 WHERE fe.idempotency_key = CONCAT('equipment.hour_logged:timesheet:', ta.timesheet_id, ':a', ta.id))"
    );
    $nRec = 0;
    if ($missed) {
        while ($m = $missed->fetch_assoc()) {
            ems_timesheet_event_hook($conn, intval($m['timesheet_id']), intval($m['gen']), intval($m['approved_by']));
            $nRec++;
        }
    }
    if ($nRec > 0) {
        echo "[events-cron] reconciler: published {$nRec} missed hour_logged event(s)\n";
    }
}

if ($hooksMode === 'monitor' || $hooksMode === 'publish') {
    // اليتيمة: حدثُ ساعةٍ جيلُ اعتماده لم يعد قائمًا (رُفض السجل بعد الاكتمال)
    $orphans = $conn->query(
        "SELECT fe.id, fe.entity_id, fe.event_no, fe.occurred_at
         FROM fin_financial_events fe
         WHERE fe.event_key = 'equipment.hour_logged' AND COALESCE(fe.is_deleted, 0) = 0
           AND NOT EXISTS (
                 SELECT 1 FROM timesheet_approvals ta
                 WHERE ta.timesheet_id = fe.entity_id AND ta.approval_level = 4 AND ta.status = 1
                   AND CONCAT('equipment.hour_logged:timesheet:', ta.timesheet_id, ':a', ta.id) = fe.idempotency_key)"
    );
    if ($orphans && $orphans->num_rows > 0) {
        while ($o = $orphans->fetch_assoc()) {
            $msg = 'orphaned hour_logged: event_id=' . $o['id'] . ' no=' . $o['event_no']
                 . ' timesheet=' . $o['entity_id'] . ' (اعتمادُه زال بالرفض — بانتظار الحدث العكسي K5.1)';
            if (function_exists('log_security_event')) {
                log_security_event('EVENT_ORPHANED_ALERT', $msg);
            }
            echo "[events-cron] ⚠ ALERT {$msg}\n";
        }
    }
}

// ═══ K5 · مستهلك المالية الأول (Walking Skeleton) ═══
// يشترك في equipment.hour_logged وينشر أثره المشتق finance.hour_recognized
// وارثًا correlation الأصل. amount=0 هنا **placeholder واعٍ لا إيرادٌ صفري**:
// payload.valuation='unpriced' — محرّكات التسعير (عميل/مورد) منطق المرحلة 3،
// وevent_type الجسري 'enterprise' يُبقيه خارج مجاميع شاشات المالية القديمة أصلًا.
$financeHandler = function (array $event, \mysqli $conn) {
    if ($event['event_key'] !== 'equipment.hour_logged') {
        return; // خارج اشتراكه الحالي — نجاح صامت
    }
    \App\Core\EventPublisher::publish($conn, array(
        'event_key'      => 'finance.hour_recognized',
        'category'       => 'financial',
        'source_module'  => 'finance',
        'company_id'     => intval($event['company_id']),
        'entity_type'    => 'source_event',
        'entity_id'      => intval($event['id']), // عطالة حتمية من معرّف حدث الأصل
        'occurred_at'    => (string) $event['occurred_at'],
        'created_by'     => intval($event['created_by']),
        'quantity'       => $event['quantity'] !== null ? (float) $event['quantity'] : null,
        'unit'           => 'hour',
        'amount'         => 0, // placeholder — انظر payload.valuation
        'equipment_id'   => $event['equipment_id'] !== null ? intval($event['equipment_id']) : null,
        'supplier_entity_id' => $event['supplier_entity_id'] !== null ? intval($event['supplier_entity_id']) : null,
        'project_id'     => $event['project_id'] !== null ? intval($event['project_id']) : null,
        'operator_employee_id' => $event['operator_employee_id'] !== null ? intval($event['operator_employee_id']) : null,
        'correlation_id' => (string) $event['correlation_id'], // وراثة السلسلة من الجذر
        'source_ref'     => 'EV-' . intval($event['id']),
        'payload'        => array(
            'valuation'       => 'unpriced', // لم يُسعَّر — ليس إيرادًا/تكلفةً صفرية
            'source_event_id' => intval($event['id']),
            'source_key'      => 'equipment.hour_logged',
            'timesheet_id'    => intval($event['entity_id']),
        ),
    ));
};
// أول تسجيل يبدأ من MAX(id) الحالي (لا معالجة رجعية) — التسجيل idempotent بعدها.
$maxRow = $conn->query('SELECT COALESCE(MAX(id), 0) FROM fin_financial_events')->fetch_row();
$dispatcher->register('finance', $financeHandler, intval($maxRow[0]));

$stats = $dispatcher->runOnce();
foreach ($stats as $consumer => $s) {
    echo "[events-cron " . date('Y-m-d H:i:s') . "] {$consumer}: processed={$s['processed']} failed={$s['failed']} dead_lettered={$s['dead_lettered']} cursor={$s['cursor']}\n";
}
