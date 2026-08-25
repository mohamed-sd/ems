<?php
/**
 * Operations/cron_rotation_transfer.php — النقلُ الآليُّ لحصص التناوب (N-05)
 * ───────────────────────────────────────────────────────────────────────────
 * «الحصةُ تنتقل آليًّا بين المشغّلين في مواعيد التناوب» (OPM-01 §5.2-③).
 * التشغيل: php Operations/cron_rotation_transfer.php [YYYY-MM-DD]
 *          (يوميًّا مجدولًا — واليومُ الافتراضيُّ تاريخُ التشغيل)
 * النطاق: مشاريعُ علم `EMS_ROTATION_AUTOTRANSFER` حصرًا (قائمةٌ لا ثنائي —
 * فارغٌ = مطفأ)، وكلُّ نقلٍ حركةُ swap ذريةٌ بسجل قرارٍ آلي.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_rotation_transfer.php'); // INJ-0025: لا تُشغَّل من المتصفّح
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Operations/RotationSwapService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Operations\RotationSwapService as RSS;

while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$date = isset($argv[1]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $argv[1]) ? $argv[1] : date('Y-m-d');
$raw = trim((string) ems_env('EMS_ROTATION_AUTOTRANSFER', ''));
if ($raw === '') { fwrite(STDOUT, "العلم فارغ — النقل الآلي مطفأ.\n"); exit(0); }

$total = 0;
foreach (explode(',', $raw) as $p) {
    $pid = (int) trim($p);
    if ($pid <= 0) { continue; }
    // شركةُ المشروع — بوابةُ النظام بشركة الوحدة (نمطُ cron المعتمد)
    $st = $conn->prepare("SELECT company_id FROM project WHERE id = ? LIMIT 1");
    $st->bind_param('i', $pid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) { fwrite(STDOUT, "المشروع {$pid}: غير موجود — يتخطى.\n"); continue; }
    $co = (int) $row['company_id'];
    $gate = new TenantDb($conn, TenantContext::forSystem($co, 0, '', true));
    $r = RSS::autoTransferForDate($conn, $gate, $co, $pid, $date, 0);
    $total += (int) $r['transferred'];
    fwrite(STDOUT, "المشروع {$pid} ({$date}): نقل " . intval($r['transferred']) . " حصة"
        . ($r['skipped'] ? ' · تخطى: ' . implode(' | ', $r['skipped']) : '') . "\n");
}
fwrite(STDOUT, "الإجمالي: {$total} نقلا آليا.\n");
exit(0);
