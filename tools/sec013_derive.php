<?php
/**
 * tools/sec013_derive.php — اشتقاق البعد الرباعي baseline لكل بند قالب (SEC-013)
 * ───────────────────────────────────────────────────────────────────────────
 * «اختبار حساب: بمعطى مدخلات معلومة، وعند إعادة الحساب من المصدر، فإن الناتج
 * يطابق المعروض بلا فرق» — الاشتقاق حتمي من لاحقة بند القالب:
 *   screen_view→screen_view · create→create · update→edit · delete_draft→draft_delete
 * والنطاق الافتراضي company (مرآة الرايات القائمة — التضييق تنقيح لاحق بيد
 * الحوكمة)، وبنود approval تحمل نوع المستند وسقفها إن وُجدا.
 * idempotent: derived_from='baseline4' يُعاد بناؤه بلا لمس التنقيح اليدوي.
 * التشغيل: php tools/sec013_derive.php [--rebuild]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$REBUILD = in_array('--rebuild', $argv, true);

if ($REBUILD) {
    mysqli_query($conn, "DELETE FROM template_permission_dims WHERE derived_from = 'baseline4'");
    fwrite(STDOUT, "أُفرغ المشتق السابق: " . mysqli_affected_rows($conn) . "\n");
}

$MAP = array(
    'screen_view'  => 'screen_view',
    'create'       => 'create',
    'update'       => 'edit',
    'delete_draft' => 'draft_delete',
);

$r = mysqli_query($conn, "SELECT tp_id, dimension, permission_code, scope_rule, amount_cap, currency, effect
                            FROM template_permissions");
$made = 0; $skipped = 0; $unknown = array();
$ins = $conn->prepare("INSERT IGNORE INTO template_permission_dims
    (tp_id, action_code, scope_code, doc_type, amount_cap, currency, effect, derived_from)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'baseline4')");
while ($x = mysqli_fetch_assoc($r)) {
    $suffix = strtolower((string) substr(strrchr($x['permission_code'], ':'), 1));
    if (!isset($MAP[$suffix])) { $unknown[$suffix] = ($unknown[$suffix] ?? 0) + 1; continue; }
    $action = $MAP[$suffix];
    // النطاق: scope_rule الصريح إن حمل رمزًا معروفًا، وإلا مرآة الراية = company
    $scope = 'company';
    $sr = strtolower(trim((string) $x['scope_rule']));
    if (in_array($sr, array('company', 'dept', 'section', 'unit', 'project', 'site', 'site_group', 'shift', 'own'), true)) {
        $scope = $sr;
    }
    $doc = $x['dimension'] === 'approval' ? (string) $x['permission_code'] : null;
    $cap = $x['amount_cap'] !== null ? $x['amount_cap'] : null;
    $cur = $x['currency'] !== null ? $x['currency'] : null;
    $eff = $x['effect'] === 'deny' ? 'deny' : 'grant';
    $tp = intval($x['tp_id']);
    $ins->bind_param('isssdss', $tp, $action, $scope, $doc, $cap, $cur, $eff);
    if ($ins->execute() && $ins->affected_rows > 0) { $made++; } else { $skipped++; }
}
$ins->close();
fwrite(STDOUT, "اشتُق: {$made} · قائم (عطالة): {$skipped}\n");
foreach ($unknown as $s => $n) { fwrite(STDOUT, "⚠ لاحقة خارج الخريطة: {$s} × {$n}\n"); }

/* فحص الحساب النصي: إعادة الاشتقاق تطابق المعروض بلا فرق */
$r = mysqli_query($conn, "SELECT COUNT(*) FROM template_permissions tp
    WHERE SUBSTRING_INDEX(tp.permission_code, ':', -1) IN ('screen_view','create','update','delete_draft')
      AND NOT EXISTS (SELECT 1 FROM template_permission_dims d
                       WHERE d.tp_id = tp.tp_id AND d.derived_from = 'baseline4')");
$gap = intval(mysqli_fetch_row($r)[0]);
fwrite(STDOUT, "بنود بلا بعدٍ مشتق: {$gap}" . ($gap === 0 ? " ✔ (الناتج يطابق المصدر بلا فرق)" : " ✘") . "\n");
exit($gap === 0 ? 0 : 1);
