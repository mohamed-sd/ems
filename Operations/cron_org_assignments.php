<?php
/**
 * Operations/cron_org_assignments.php — دورية التكليفات التنظيمية (ORG-01 §7 ③)
 * ───────────────────────────────────────────────────────────────────────────
 * «انتهاء مدة التكليف يُسقط الصلاحية في اللحظة نفسها» — مسح يومي يُنهي المنقضي
 * آليًّا (AssignmentExpired) ويولّد تنبيهات الثلاثين يومًا.
 * التشغيل: php Operations/cron_org_assignments.php
 *          (يوميًّا مجدولًا — والمهل بساعة القاعدة لا PHP)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_org_assignments.php'); // INJ-0025: لا تُشغَّل من المتصفّح
require_once dirname(__DIR__) . '/app/Services/Org/AssignmentExpiryJob.php';
require_once dirname(__DIR__) . '/app/Services/Org/PermitGate.php';

use App\Services\Org\AssignmentExpiryJob;
use App\Services\Org\PermitGate;

while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$r = AssignmentExpiryJob::run($conn);
$p = PermitGate::sweepExpired($conn);
fwrite(STDOUT, "التكليفات: أنهي آليا " . intval($r['expired'])
    . " · ونبه على " . intval($r['notified']) . " قادمة على الانتهاء"
    . " · وانتهت صلاحية " . intval($p) . " إذنا (PermitExpired).\n");
exit(0);
