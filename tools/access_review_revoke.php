<?php
/**
 * tools/access_review_revoke.php — «الصمتُ سحبٌ» آليًّا (M-14 · بند النافذة 6)
 * ───────────────────────────────────────────────────────────────────────────
 * يقرأ حملة المراجعة الربعية الجارية (scr_access_review + مهام ARC-*):
 * مهمةُ مراجعة إدارةٍ فاتت مهلتُها بلا إغلاق = صمتٌ ⇒ منح كتابة أعضائها
 * مرشحة للسحب (تُعرض دائمًا dry-run بقائمتها المسماة).
 * --apply محكوم بنيويًّا: يرفض ما دام EMS_PERM_SOURCE=legacy — «السحب الآلي
 * فوق مصدرين متزامنين خطرُ ازدواج» (خريطة المعوقات بند 6)؛ فالأداة جاهزة
 * والقلب يوم 2026-08-19 يفكها بلا عمل إضافي. السحب تصفيرُ رايات كتابةٍ
 * موثقٌ في سجل الأمن — لا حذف صفوف (تفويض المالك: لا حذف).
 * التشغيل: php tools/access_review_revoke.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
require_once __DIR__ . '/../Tickets/dept_inbox_map.php';
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);

$src = strtolower(trim((string) (function_exists('env') ? env('EMS_PERM_SOURCE', 'legacy') : 'legacy')));
if ($APPLY && $src !== 'sec01') {
    fwrite(STDOUT, "✋ --apply محجوب بنيويًّا: EMS_PERM_SOURCE={$src} — السحب الآلي بعد قلب المصدر وحده (2026-08-19)\n");
    fwrite(STDOUT, "   الأداة تعمل dry-run كاملًا الآن، وتنفذ بلا تعديلٍ يوم القلب.\n");
    $APPLY = false;
}

$q = intval(ceil(intval(date('n')) / 3));
$cycle = 'AR-' . date('Y') . 'Q' . $q;
fwrite(STDOUT, "══ الدورة {$cycle} — الصمت سحب ══\n");

/* مهام مراجعة الإدارات الفائتة مهلتها بلا إغلاق */
$r = mysqli_query($conn, "SELECT wi.id, wi.company_id, wi.org_unit_id, wi.source_ref, wi.status, wi.due_at
                            FROM work_items wi
                           WHERE wi.source_ref LIKE 'ARC-{$cycle}-U%'
                             AND wi.status NOT IN ('closed_accepted','cancelled')
                             AND wi.due_at < NOW()");
$silent = array();
while ($r && ($x = mysqli_fetch_assoc($r))) { $silent[] = $x; }
fwrite(STDOUT, "إدارات صامتة (مهمتها فائتة بلا إغلاق): " . count($silent) . "\n");

$candidates = 0;
foreach ($silent as $t) {
    $unit = intval($t['org_unit_id']);
    $co = intval($t['company_id']);
    $roles = array();
    for ($rid = 1; $rid <= 40; $rid++) { if (ems_dept_unit_of_role($rid) === $unit) { $roles[] = $rid; } }
    if (!$roles) { continue; }
    $rin = implode(',', $roles);
    // منح الكتابة الحية لأدوار الإدارة الصامتة = مرشحة السحب (تصفير رايات لا حذف)
    $g = mysqli_query($conn, "SELECT rp.role_id, m.code, rp.can_add, rp.can_edit, rp.can_delete
                                FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                               WHERE rp.role_id IN ({$rin})
                                 AND (rp.can_add = 1 OR rp.can_edit = 1 OR rp.can_delete = 1)");
    $n = 0;
    while ($g && ($x = mysqli_fetch_assoc($g))) {
        $n++;
        $candidates++;
        if ($APPLY) {
            mysqli_query($conn, "UPDATE role_permissions rp JOIN modules m ON m.id = rp.module_id
                                    SET rp.can_add = 0, rp.can_edit = 0, rp.can_delete = 0
                                  WHERE rp.role_id = " . intval($x['role_id']) . " AND m.code = '"
                                  . $conn->real_escape_string($x['code']) . "'");
            error_log('[M14][AUTO_REVOKE] ' . $cycle . ' role=' . $x['role_id'] . ' ' . $x['code']);
        }
    }
    fwrite(STDOUT, "  · وحدة {$unit} (شركة {$co}): {$n} منح كتابةٍ مرشح" . ($APPLY ? ' — سُحب' : '') . "\n");
    if ($APPLY) {
        mysqli_query($conn, "UPDATE scr_access_review
                                SET required_revoke = '{$n}', revoked_auto = '{$n}'
                              WHERE no_cycle = '" . $conn->real_escape_string($cycle) . "' AND company_id = {$co}");
    }
}
fwrite(STDOUT, "────────────\nمرشحات السحب الكلية: {$candidates}" . ($APPLY ? ' — نُفذ وسُجل' : ' (dry-run)') . "\n");
