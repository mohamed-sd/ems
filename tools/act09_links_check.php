<?php
/**
 * tools/act09_links_check.php — ربط أفعال الورقة 09 المتخصصة بخطواتها (م-هـ)
 * ───────────────────────────────────────────────────────────────────────────
 * «السلاسلُ خطواتُ approval_links والقرارُ لحاملها» — الطلبات وُصلت سلفًا،
 * وهذا يقيس المتخصصة: كل فعلِ حكمٍ منقوطٍ (approve/reject/award/chain/gate)
 * يجب أن يمرَّ تنفيذُه بطبقة خطوةٍ مسجلة: approval_links أو آلةَ حالةٍ خادمية
 * تختم فاعلها (عرف unit.chain عبر UnitJourneyService). القياس على الكود
 * الحي: موضع تنفيذ الفعل يحمل الربط — والعاري يُسمى.
 * التشغيل: php tools/act09_links_check.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
$ROOT = dirname(__DIR__);
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

/* أفعال الحكم المتخصصة من القاموس الحي */
$acts = array();
$r = mysqli_query($conn, "SELECT action_code, name_ar, handler_path FROM actions
    WHERE action_code NOT LIKE 'ajax.%' AND action_code LIKE '%.%' AND active = 1
      AND (action_code LIKE '%.approve%' OR action_code LIKE '%.reject%'
           OR action_code LIKE 'chain.%' OR action_code LIKE '%.award'
           OR action_code LIKE '%gate.%' OR action_code LIKE 'swap.%')");
while ($x = mysqli_fetch_assoc($r)) { $acts[] = $x; }

/* مواضع التنفيذ المعروفة لكل عائلة (الخدمة أو المعالج الحي) */
$IMPL = array(
    'unit.chain.approve'          => array('app/Services/Unit/UnitJourneyService.php', 'Approvals/hours_approval_handler.php'),
    'unit.chain.reverse'          => array('app/Services/Unit/UnitJourneyService.php'),
    'chain.site'                  => array('app/Services/Unit/UnitJourneyService.php', 'Approvals/hours_approval_handler.php'),
    'settle.approve'              => array('Suppliers/settlements.php', 'app/Services/Supplier/SupplierSettlementService.php'),
    'supplier.settlement.approve' => array('app/Services/Contract/SupplierClosureService.php', 'Suppliers/settlements.php'),
    'supplier.settlement.reverse' => array('app/Services/Contract/SupplierClosureService.php', 'Suppliers/settlements.php'),
    'entitlement.gate.approve'    => array('app/Services/Finance/UnitConversionService.php'),
    'entitlement.gate.reverse'    => array('app/Services/Finance/UnitConversionService.php'),
    'rec.stage.reject'            => array('Workforce/recruitment_pipeline.php'),
    'rfq.award'                   => array('Procurement/rfq_compare_award.php'),
    'swap.request'                => array('Operations/swap_request.php'),
    'swap.approve.dept'           => array('Operations/swap_request.php'),
    'swap.approve.workforce'      => array('Operations/swap_request.php'),
    'swap.reject'                 => array('Operations/swap_request.php'),
    'swap.end'                    => array('Operations/swap_request.php'),
);
/* أنماط الربط المقبولة: خطوة صريحة أو آلة حالة خادمية تختم فاعلها —
   approvals_ref (تبديلات الموافقتين) · awarded_by (الترسية) · rec_stage_log
   (سجل انتقالات التوظيف) · sales_approved (اكتمال سلسلة بوابة الاستحقاق) */
$LINK_PATTERNS = array('approval_links', 'approval_chain', 'ChainSLA', 'approved_by', 'decide(', 'approve(',
    'approvals_ref', 'awarded_by', 'rec_stage_log', 'sales_approved');

$ok = 0; $bare = array();
foreach ($acts as $a) {
    $code = $a['action_code'];
    $files = isset($IMPL[$code]) ? $IMPL[$code] : array();
    if ($a['handler_path']) { $files[] = $a['handler_path']; }
    $linked = false; $where = '';
    foreach ($files as $f) {
        $p = $ROOT . '/' . $f;
        if (!is_file($p)) { continue; }
        $src = (string) file_get_contents($p);
        foreach ($LINK_PATTERNS as $pat) {
            if (strpos($src, $pat) !== false) { $linked = true; $where = $f . ' (' . $pat . ')'; break 2; }
        }
    }
    if ($linked) { $ok++; fwrite(STDOUT, "  ✔ {$code} ← {$where}\n"); }
    else { $bare[] = $code . ' — ' . $a['name_ar']; }
}
fwrite(STDOUT, "────────────\nموصول بخطوته: {$ok}/" . count($acts) . " · عارٍ: " . count($bare) . "\n");
foreach ($bare as $b) { fwrite(STDOUT, "  ✘ {$b}\n"); }
exit(count($bare) ? 1 : 0);
