<?php
/**
 * cron_proc_replenish.php — عاملُ تغذية المشتريات الدوري (③ · قرار المالك 2026-08-06)
 * ───────────────────────────────────────────────────────────────────────────
 * وقفُ نزيف التوقف من طرفَيه، لكل كيانٍ نشط:
 *   ① جسرُ الصيانة (MntProcBridgeService): أمرُ صيانةٍ مفتوحٌ بقطعٍ بلا طلبٍ
 *      → طلبُ شراءٍ بأولويته («حرج» للعطل) — بعطالة MNT#id.
 *   ② كنّاسُ حدود الطلب (ProcReorderService::run M-43): صنفٌ بلغ حدَّه بلا
 *      دورةٍ جارية → طلبٌ آلي.
 *
 * التشغيل: php cron_proc_replenish.php [--dry]
 *          أو GET ?key=<PROC_CRON_KEY من .env> — fail-closed بلا مفتاح.
 * الجدولة المقترحة: كل ساعةٍ (مهمة مجدولة EMS_PROC_REPLENISH على نسق EMS_E02_*).
 */

$IS_CLI = (PHP_SAPI === 'cli');
require_once __DIR__ . '/config.php';
if ($IS_CLI) { while (ob_get_level()) { ob_end_clean(); } }

if (!$IS_CLI) {
    $key = isset($_GET['key']) ? (string) $_GET['key'] : '';
    $expected = (string) ems_env('PROC_CRON_KEY', '');
    if ($expected === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        exit('forbidden');
    }
    header('Content-Type: text/plain; charset=UTF-8');
}

require_once __DIR__ . '/app/Core/TenantRegistry.php';
require_once __DIR__ . '/app/Core/TenantGateException.php';
require_once __DIR__ . '/app/Core/TenantContext.php';
require_once __DIR__ . '/app/Core/TenantDb.php';
require_once __DIR__ . '/Procurement/proc_helpers.php';
require_once __DIR__ . '/app/Services/Procurement/ProcReorderService.php';
require_once __DIR__ . '/app/Services/Procurement/MntProcBridgeService.php';

$DRY = $IS_CLI && in_array('--dry', $argv ?? array(), true);
$o = function ($s) { echo $s . "\n"; };
$o('══ proc-replenish ' . date('Y-m-d H:i:s') . ($DRY ? ' (DRY)' : '') . ' ══');

$companies = array();
$r = $conn->query("SELECT id FROM admin_companies WHERE status = 'active'");
if ($r === false || $r->num_rows === 0) { $r = $conn->query("SELECT id FROM admin_companies"); }
while ($r && ($x = $r->fetch_assoc())) { $companies[] = intval($x['id']); }

foreach ($companies as $cid) {
    // بوابةٌ معزولةٌ بشركة الدورة عبر السياق الخادمي (نمط cron_events حرفيًا)
    $gate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($cid, 0, '', true));

    $bridge = \App\Services\Procurement\MntProcBridgeService::run($conn, $gate, $cid, 0, $DRY);
    foreach ($bridge['generated'] as $g2) {
        $o("[co$cid] جسر الصيانة: {$g2['mnt']} → طلب" . (isset($g2['request_id']) ? ' #' . $g2['request_id'] : '')
            . " ({$g2['parts']} قطعة · {$g2['priority']})");
    }
    foreach ($bridge['skipped'] as $s2) { $o("[co$cid] جسر ⏭ {$s2['mnt']}: {$s2['reason']}"); }

    $reorder = \App\Services\Procurement\ProcReorderService::run($conn, $gate, $cid, 0, $DRY);
    foreach ($reorder['generated'] as $g2) {
        $o("[co$cid] حد الطلب: {$g2['item']} (رصيد {$g2['balance']} ≤ حد {$g2['trigger']}) → "
            . (isset($g2['request_id']) ? 'طلب #' . $g2['request_id'] : 'سيولَّد') . " بكمية {$g2['qty']}");
    }
    foreach ($reorder['skipped'] as $s2) { $o("[co$cid] حد ⏭ {$s2['item']}: {$s2['reason']}"); }

    if (!$bridge['generated'] && !$reorder['generated'] && !$bridge['skipped'] && !$reorder['skipped']) {
        $o("[co$cid] لا احتياجَ جديدًا");
    }
}
$o('══ اكتمل ══');
