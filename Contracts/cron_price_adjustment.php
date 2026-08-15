<?php
/**
 * Contracts/cron_price_adjustment.php — مراجعةُ شروط تعديل السعر (M-09)
 * ───────────────────────────────────────────────────────────────────────────
 * «شروطُ تعديل السعر (وقودٌ · تضخمٌ · صرف)» (CON-02 §2-③) تُقيَّم دوريًّا،
 * وما تحرّك منها يولّد **ملحقَ «تغيير أسعار» بسريانه** (§6) — ولا يمسّ سعرًا
 * قائمًا ولا واقعةً ماضية. والاعتمادُ بيدٍ أخرى («من أنشأ لا يعتمد»).
 *
 * التشغيل: php Contracts/cron_price_adjustment.php [YYYY-MM-DD]
 * النطاق:  عقودُ علَم `EMS_PRICE_ADJUST` حصرًا (قائمةٌ لا ثنائي — فارغٌ = مطفأ).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/../includes/cron_guard.php';
ems_cron_guard('cron_price_adjustment.php'); // INJ-0025: لا تُشغَّل من المتصفّح
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/PriceAdjustmentService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\PriceAdjustmentService as PAS;

while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');

$date = isset($argv[1]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $argv[1]) ? $argv[1] : date('Y-m-d');
$raw = trim((string) ems_env('EMS_PRICE_ADJUST', ''));
if ($raw === '') { fwrite(STDOUT, "العلمُ فارغٌ — مراجعةُ الأسعار الدورية مطفأة.\n"); exit(0); }

$made = 0; $skipped = 0;
foreach (explode(',', $raw) as $c) {
    $cid = (int) trim($c);
    if ($cid <= 0) { continue; }
    // شركةُ العقد — بوابةُ النظام بشركة الوحدة (نمطُ cron المعتمد)
    $st = $conn->prepare("SELECT company_id FROM contracts WHERE id = ? LIMIT 1");
    $st->bind_param('i', $cid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) { fwrite(STDOUT, "العقد {$cid}: غيرُ موجود — يُتخطى.\n"); continue; }
    $co = (int) $row['company_id'];
    $gate = new TenantDb($conn, TenantContext::forSystem($co, 0, '', true));

    /* ◆ **يُصرّح بمنشئه**: كان يمرّر actor=0 فيُكتب `created_by` نُلًّا، والنُّلُّ
         يعني «آلةً» بالحادثِ لا بالتصريح — فيسكت حارسُ «من أنشأ لا يعتمد» على
         كلِّ صفوفِ الكرون. فالآن المنشأُ مُعلَنٌ في العمودِ وفي قيدِ القاعدة. */
    $r = PAS::applyDue($conn, $gate, $co, $cid, $date, 0, 'system');
    $made += (int) $r['created']; $skipped += (int) $r['skipped'];
    $kinds = array();
    foreach ($r['rows'] as $x) { $kinds[] = (string) $x['outcome']; }
    fwrite(STDOUT, "العقد {$cid} ({$date}): وُلّد " . intval($r['created']) . " مراجعةً · عاطلٌ "
        . intval($r['skipped']) . ($kinds ? (' · النتائج: ' . implode('،', array_unique($kinds))) : '') . "\n");
}
fwrite(STDOUT, "الإجمالي: {$made} مراجعةً جديدة · {$skipped} عاطلة (تنتظر الاعتماد لتصير مالًا).\n");
exit(0);
