<?php
/**
 * REV-08 · البند 1 — برهان التصحيح وبصمة الناشر (يُشغَّل CLI فقط)
 * ───────────────────────────────────────────────────────────────────────────
 * يُثبت — عبر الخطّاف والناشر الحيَّيْن، لا محاكاة — أن بصمة الناشر
 * (idempotency_key) تتضمّن **جيل الاعتماد** فتحقّق ثلاثة أمور معًا:
 *   (أ) إعادةُ إرسال نفس الجيل  = مكرَّرٌ مُهمَل (لا صفَّ ثانٍ).
 *   (ب) التصحيح بجيلٍ جديد      = حدثٌ مستقلٌّ جديد (لا يُبتلَع).
 *   (ج) الحدثُ الجديد يحمل القيمة المصحَّحة (لا القديمة).
 *
 * البصمة الفعلية المقيسة من الكود (timesheet_event_hook.php:83):
 *   equipment.hour_logged:timesheet:{tsId}:a{level4_approval_row_id}
 *
 * ذاتيُّ التنظيف (register_shutdown_function): يُعيد الدفتر لحالته السابقة
 * بالضبط (لا يمسّ الأحداث الحيّة العشرة) — يُعيد صفوفَه التجريبية حصرًا عبر
 * اتصال root، ويُصفّر AUTO_INCREMENT. يُرفَق مخرَجُه في ردّ REV-08.
 *
 * التشغيل:  php tests/proof_correction.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/config.php'; // $conn (التطبيق) + ems_env + الخطّاف
require_once dirname(__DIR__) . '/includes/timesheet_event_hook.php';
require_once dirname(__DIR__) . '/app/Core/ServerId.php';
require_once dirname(__DIR__) . '/app/Core/EventValidationException.php';
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
require_once dirname(__DIR__) . '/app/Core/EventDispatcher.php';

$hooks = (string) ems_env('EMS_EVENT_HOOKS', 'off');
if ($hooks !== 'publish') {
    fwrite(STDERR, "SKIP: EMS_EVENT_HOOKS='$hooks' (يلزم 'publish' — لا برهانَ نشرٍ بلا نشر)\n");
    exit(2);
}

// اتصال root للبذر/التنظيف (نفس نمط tests/golden_run.php) — الدفتر محصَّنٌ عبر البوابة
$root = new mysqli(ems_env('DB_HOST'), 'root', '', ems_env('DB_NAME'));
if ($root->connect_errno) { fwrite(STDERR, "FATAL: root connect\n"); exit(1); }
$root->set_charset('utf8mb4');

$COMPANY = 4;
$ledgerBefore = (int) $root->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0];
$tsId = 0;

$cleanup = function () use ($root, &$tsId, $ledgerBefore) {
    if ($tsId > 0) {
        $root->query("DELETE FROM fin_financial_events
            WHERE idempotency_key LIKE 'equipment.hour_logged:timesheet:{$tsId}:%'");
        $root->query("DELETE FROM timesheet WHERE id = {$tsId}");
        $mx = (int) $root->query("SELECT COALESCE(MAX(id),0) FROM fin_financial_events")->fetch_row()[0];
        $root->query("ALTER TABLE fin_financial_events AUTO_INCREMENT = " . ($mx + 1));
    }
    // مستهلك التسليم الرمّي (المرحلة 4) — صفّه وتسليماته حصرًا؛ مستهلك المالية لا يُمَسّ
    $root->query("DELETE FROM ems_event_consumers  WHERE consumer = 'zz_proof_probe'");
    $root->query("DELETE FROM ems_event_deliveries WHERE consumer = 'zz_proof_probe'");
    $after = (int) $root->query("SELECT COUNT(*) FROM fin_financial_events")->fetch_row()[0];
    echo "تنظيف: الدفتر عاد إلى {$after} (المتوقع {$ledgerBefore}) " . ($after === $ledgerBefore ? "✓" : "✗ !!") . "\n";
};
register_shutdown_function($cleanup);

// ── بذر ساعةٍ تجريبيّة (operator/employee فارغان → لا مراجع، النشر يمرّ) ──
$stmt = $root->prepare("INSERT INTO timesheet
    (operator, employee_id, shift, `date`, `type`, time_notes, company_id, executed_hours, total_work_hours, shift_hours)
    VALUES ('', '', 'ص', '2026-07-14', 'عادي', '', ?, 8, 10, 10)");
$stmt->bind_param('i', $COMPANY);
$stmt->execute();
$tsId = (int) $root->insert_id;
$stmt->close();
echo "بذر ساعةٍ تجريبيّة: timesheet #{$tsId} (شركة {$COMPANY}، executed=8)\n\n";

$maxBefore = (int) $root->query("SELECT COALESCE(MAX(id),0) FROM fin_financial_events")->fetch_row()[0];

// ── (1) الاعتماد الأصلي: الجيل 1 (8 ساعات) ──
ems_timesheet_event_hook($conn, $tsId, 1, 1);
// ── (2) إعادةُ إرسال النسخة نفسها: الجيل 1 مرّةً أخرى — يجب أن يُهمَل ──
ems_timesheet_event_hook($conn, $tsId, 1, 1);
// ── (3) التصحيح: تُحدَّث الساعة إلى 6 ثم اعتمادٌ جديد (الجيل 2) ──
$root->query("UPDATE timesheet SET executed_hours = 6 WHERE id = {$tsId}");
ems_timesheet_event_hook($conn, $tsId, 2, 1);

// ── التحقّق ──
$rows = $root->query("SELECT idempotency_key, quantity FROM fin_financial_events
    WHERE idempotency_key LIKE 'equipment.hour_logged:timesheet:{$tsId}:%'
    ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$n = count($rows);

$keys = array_column($rows, 'idempotency_key');
$qtys = array_map('floatval', array_column($rows, 'quantity'));
$distinctKeys = count(array_unique($keys)) === $n;
$hasA1 = in_array("equipment.hour_logged:timesheet:{$tsId}:a1", $keys, true);
$hasA2 = in_array("equipment.hour_logged:timesheet:{$tsId}:a2", $keys, true);
$correctedValue = in_array(8.0, $qtys, true) && in_array(6.0, $qtys, true);

echo "الأحداث المنشورة لهذه الساعة: {$n} (المتوقع 2)\n";
foreach ($rows as $r) { echo "  - {$r['idempotency_key']}  quantity={$r['quantity']}\n"; }

// ── (4) التسليم: مستهلكٌ رمّيٌّ عبر الموزّع الحقيقي يستلم الجيلين (سطرا التسليم) ──
// يبدأ من MAX(id) قبل البذر فلا يستلم إلا حدثَي البرهان؛ لا ينشر مشتقًّا (يرصد فقط)،
// ومستهلك المالية الحيّ لا يُلمَس (معالجه غير مسجَّلٍ في هذه العملية أصلًا).
echo "\nالتسليم عبر EventDispatcher (مستهلك رمّي zz_proof_probe من المؤشّر {$maxBefore}):\n";
$delivered = array();
$dispatcher = new \App\Core\EventDispatcher($conn);
$dispatcher->register('zz_proof_probe', function (array $event, \mysqli $c) use (&$delivered, $tsId) {
    if ($event['event_key'] === 'equipment.hour_logged' && intval($event['entity_id']) === $tsId) {
        $delivered[] = $event['idempotency_key'];
        echo "  تسليم → {$event['idempotency_key']}  quantity={$event['quantity']}\n";
    }
}, $maxBefore);
$stats = $dispatcher->runOnce();
foreach ($stats as $cName => $s) {
    echo "  [dispatcher] {$cName}: processed={$s['processed']} failed={$s['failed']} dead_lettered={$s['dead_lettered']} cursor={$s['cursor']}\n";
}
$deliveredBoth = (count($delivered) === 2)
    && in_array("equipment.hour_logged:timesheet:{$tsId}:a1", $delivered, true)
    && in_array("equipment.hour_logged:timesheet:{$tsId}:a2", $delivered, true);

$pass = ($n === 2) && $distinctKeys && $hasA1 && $hasA2 && $correctedValue && $deliveredBoth;
echo "\n";
echo "  الجيلان مستقلّان (a1 + a2)          " . (($hasA1 && $hasA2) ? "✓" : "✗") . "\n";
echo "  إعادةُ إرسال الجيل 1 أُهمِلت (n=2 لا 3) " . ($n === 2 ? "✓" : "✗") . "\n";
echo "  التصحيح حمل القيمة الجديدة (8 و6)     " . ($correctedValue ? "✓" : "✗") . "\n";
echo "  الموزّع سلّم الجيلين لمستهلكٍ مشترِك    " . ($deliveredBoth ? "✓" : "✗") . "\n";
echo "\n" . ($pass
    ? "PASS: حدثان مستقلّان، والتكرار مُهمَل، والتصحيح لم يُبتلَع وحمل قيمته الجديدة، وسُلِّما معًا\n"
    : "FAIL: n={$n} delivered=" . count($delivered) . " — التصحيح ابتُلع أو التكرار مرّ أو القيمة/التسليم اختلّ\n");

exit($pass ? 0 : 1);
