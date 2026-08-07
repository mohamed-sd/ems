<?php
/**
 * tools/u9_org_units_uat_cleanup.php — تعطيل تلوث UAT في org_units (update0009)
 * ───────────────────────────────────────────────────────────────────────────
 * المراجعة العكسية كشفت خمسةَ صفوفٍ بأسماء أشخاصٍ كوحداتٍ تنظيمية (16–20)
 * زرعها باذرُ UAT (owner_doc = storage/uat/org_units-N.pdf · تواريخُ عشوائية
 * 2024–2026 · أكواد ORG_-000NN) — وهي تكسر شاهدَ الهيكل في الورقة 12.
 * تعطيلٌ لا حذفٌ (PR-06) · ولا صفَّ منها مرجوعٌ من org_assignments (مقيس).
 *
 * php tools/u9_org_units_uat_cleanup.php [--apply]
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

$o('══ org_units UAT cleanup — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' ══');
$r = mysqli_query($conn,
    "SELECT u.unit_id, u.name_ar, u.owner_doc,
            (SELECT COUNT(*) FROM org_assignments a WHERE a.org_unit_id = u.unit_id) refs
       FROM org_units u
      WHERE u.active = 1 AND u.unit_code LIKE 'ORG\\_-000%' AND u.owner_doc LIKE 'storage/uat/%'");
$ids = array();
while ($x = mysqli_fetch_assoc($r)) {
    $o("  #{$x['unit_id']} «{$x['name_ar']}» refs={$x['refs']}" . ($x['refs'] > 0 ? ' ← مرجوعٌ فيُترك' : ' ← يُعطَّل'));
    if ((int) $x['refs'] === 0) { $ids[] = (int) $x['unit_id']; }
}
/* الصف 20 بلا owner_doc لكن بالكود الشاذ نفسه */
$r = mysqli_query($conn,
    "SELECT u.unit_id, u.name_ar,
            (SELECT COUNT(*) FROM org_assignments a WHERE a.org_unit_id = u.unit_id) refs
       FROM org_units u
      WHERE u.active = 1 AND u.unit_code LIKE 'ORG\\_-000%' AND (u.owner_doc IS NULL OR u.owner_doc = '')");
while ($x = mysqli_fetch_assoc($r)) {
    $o("  #{$x['unit_id']} «{$x['name_ar']}» (بلا مستند) refs={$x['refs']}" . ($x['refs'] > 0 ? ' ← مرجوعٌ فيُترك' : ' ← يُعطَّل'));
    if ((int) $x['refs'] === 0) { $ids[] = (int) $x['unit_id']; }
}
if (!$ids) { $o('لا صفوفَ ملوثةً نشطة — نظيف'); exit(0); }
if ($APPLY) {
    mysqli_query($conn, "UPDATE org_units SET active = 0 WHERE unit_id IN (" . implode(',', $ids) . ")")
        or die(mysqli_error($conn) . "\n");
    $o('✔ عُطِّل: ' . mysqli_affected_rows($conn) . ' — والتراجعُ UPDATE org_units SET active=1 WHERE unit_id IN (' . implode(',', $ids) . ')');
} else {
    $o('— dry-run: ' . count($ids) . ' مرشحًا للتعطيل');
}
