<?php
/**
 * Governance/cron_permissions.php — دورية الصلاحيات (SEC-01 §12 ③ · SEC-18)
 * ───────────────────────────────────────────────────────────────────────────
 * يُسقط الاستثناءات والمراكز المنقضية في لحظتها ويعيد بناء المشتق —
 * بساعة القاعدة لا PHP. التشغيل: php Governance/cron_permissions.php (يوميًّا)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Services/Security/ExpiryJob.php';
require_once dirname(__DIR__) . '/app/Services/Security/BreakGlassService.php';

use App\Services\Security\ExpiryJob;
use App\Services\Security\BreakGlassService;

while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$r = ExpiryJob::run($conn);
$bg = BreakGlassService::sweepUnreviewed($conn);
fwrite(STDOUT, "الصلاحيات: أُسقط " . intval($r['exceptions']) . " استثناءً و" . intval($r['positions'])
    . " مركزًا · وأُعيد بناء المشتق لـ" . intval($r['rebuilt']) . " شخصًا · تنبيهات: " . intval($r['notified'])
    . " · وكسر زجاج ساقط بلا مراجعة: " . intval($bg) . "\n");
exit(0);
