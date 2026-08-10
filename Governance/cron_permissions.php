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
require_once dirname(__DIR__) . '/includes/catch_log.php';
require_once dirname(__DIR__) . '/app/Services/Security/ExpiryJob.php';
require_once dirname(__DIR__) . '/app/Services/Security/BreakGlassService.php';
require_once dirname(__DIR__) . '/app/Services/Security/PermissionReviewService.php';
require_once dirname(__DIR__) . '/app/Services/Security/PermSourceService.php';
require_once dirname(__DIR__) . '/app/Services/Audit/InternalAuditService.php';
require_once dirname(__DIR__) . '/app/Core/AuthorityGuard.php';

use App\Services\Security\ExpiryJob;
use App\Services\Security\BreakGlassService;
use App\Services\Security\PermissionReviewService;
use App\Services\Security\PermSourceService;

while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$r  = ExpiryJob::run($conn);
$bg = BreakGlassService::sweepUnreviewed($conn);
fwrite(STDOUT, "الصلاحيات: أُسقط " . intval($r['exceptions']) . " استثناءً و" . intval($r['positions'])
    . " مركزًا · وأُعيد بناء المشتق لـ" . intval($r['rebuilt']) . " شخصًا · تنبيهات: " . intval($r['notified'])
    . " · وكسر زجاج ساقط بلا مراجعة: " . intval($bg) . "\n");

/* ══ MD-05 · تبنّي الكنّاساتِ الدورية — بناؤها ليس تبنّيها ══════════════════
   أربعُ خدماتٍ كانت مبنيةً **بصفرِ نداءٍ في الإنتاج**، وكلُّها دوريةٌ بطبعها:
   مهلةٌ تنقضي فيلزم تصعيدُها، أو سلطةٌ تنتهي فيلزم الإنذارُ قبل انتهائها.
   وخدمةٌ كهذه بلا جدولٍ يناديها ليست حارسًا — هي نيّةُ حارس.

   ◆ وكلٌّ منها معزولةٌ في محاولتها: فشلُ كنّاسةٍ لا يُسقط أخواتِها ولا يُسقط
     الدورةَ كلَّها، والسببُ يُعلَن ولا يُبتلع. */
$__sweeps = array(
    'تصعيدُ دوراتِ مراجعةِ الصلاحياتِ المتأخرة' => function () use ($conn) {
        return PermissionReviewService::escalateOverdue($conn);
    },
    /* ◆ توقيعُها يشترط الشركةَ صراحةً (`$co`) — والكنسُ يمرُّ على الشركاتِ
         النشطةِ واحدةً واحدة. وتمريرُ `null` كان يُسقطها صامتًا لولا العزل. */
    'تصعيدُ ملاحظاتِ المراجعةِ الداخليةِ المتأخرة' => function () use ($conn) {
        $n = 0;
        $rs = $conn->query('SELECT id FROM admin_companies WHERE COALESCE(is_deleted,0)=0');
        while ($rs && ($row = $rs->fetch_row())) {
            $n += (int) \App\Services\Audit\InternalAuditService::escalateOverdue($conn, (int) $row[0]);
        }
        return $n;
    },
    'إنذارُ السلطاتِ المشارفةِ على الانتهاء' => function () use ($conn) {
        return \App\Core\AuthorityGuard::sweepExpiring($conn);
    },
    'إسقاطُ استثناءاتِ الحوكمةِ المنقضية' => function () use ($conn) {
        require_once dirname(__DIR__) . '/app/Services/Governance/ExceptionService.php';
        return \App\Services\Governance\ExceptionService::expireSweep($conn);
    },
    'إسقاطُ ملكياتِ الكياناتِ المنقضية' => function () use ($conn) {
        require_once dirname(__DIR__) . '/app/Core/EntityGovernanceService.php';
        return \App\Core\EntityGovernanceService::expirySweep($conn);
    },
);
foreach ($__sweeps as $__label => $__fn) {
    try {
        $__n = $__fn();
        fwrite(STDOUT, '  · ' . $__label . ': ' . (is_numeric($__n) ? intval($__n) : 'تمّ') . "\n");
    } catch (\Throwable $__se) {
        ems_catch_ignored($__se, 'cron_permissions',
            'كنّاسةٌ دوريةٌ واحدةٌ فشلت — أخواتُها تستمرُّ وتُعاد في الدورةِ التالية: ' . $__label);
        fwrite(STDOUT, '  ✘ ' . $__label . ": تعذّرت — سُجِّل السبب\n");
    }
}

/* تقريرُ مصدرِ الصلاحية: أيُّ محرّكٍ يحكم اليوم وكم يومًا مضى بصفرِ فرق —
   وهو شرطُ العبورِ من التشغيلِ المزدوجِ إلى المصدرِ الواحد. */
try {
    $__ph = PermSourceService::phaseReport($conn);
    fwrite(STDOUT, '  · مصدرُ الصلاحية: ' . PermSourceService::currentSource()
        . ' · صفرُ فرقٍ منذ ' . intval($__ph['zero_diff_streak_days'] ?? 0) . " يومًا\n");
} catch (\Throwable $__pe) {
    ems_catch_ignored($__pe, 'cron_permissions',
        'تقريرُ مصدرِ الصلاحيةِ تعذّر — لا يؤثر في الكنسِ نفسِه');
}

exit(0);
