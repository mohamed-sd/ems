<?php
/**
 * tools/nav09_perm_backfill.php — تمريرةُ ربط القوائم بصلاحياتها (E-03 UX-07)
 * ───────────────────────────────────────────────────────────────────────────
 * تطبّق القاعدةَ الواحدة nav09_perm_link (نفسها التي يُخرجها المستورد) على
 * الصفوف القائمة — فلا يُفتح فرقُ الاستيراد الموروث المعلَّق، ويصمد الربطُ
 * أمام أي --apply قادم. --diff يقيس ولا يمسّ: كم يُربط، وكم رابطًا سيختفي
 * لكل دورٍ (مربوطٌ ولا can_view له — وهو نفسُه من يرفضه الحارسُ عند الفتح).
 * التشغيل: php tools/nav09_perm_backfill.php --diff | --apply
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/nav09_read.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$APPLY = in_array('--apply', $argv, true);

$r = mysqli_query($conn, "SELECT id, role_id, route, module_id, permission_code FROM nav_items");
$toLink = array();      // id => [module_id, code]
$willHide = array();    // role_id => count
$already = 0;
$unlinked = 0;
while ($x = mysqli_fetch_assoc($r)) {
    $pl = nav09_perm_link($conn, $x['route']);
    if ($pl['module_id'] === null) { $unlinked++; continue; }
    $same = ((int) $x['module_id'] === $pl['module_id']) && ($x['permission_code'] === $pl['permission_code']);
    if ($same) { $already++; }
    else { $toLink[(int) $x['id']] = $pl; }
    // أيختفي بعد الربط؟ (المصيّر: يظهر فقط إن كان can_view=1)
    $q = mysqli_query($conn, "SELECT 1 FROM role_permissions WHERE role_id=" . (int) $x['role_id']
        . " AND module_id={$pl['module_id']} AND can_view=1 LIMIT 1");
    if (!($q && mysqli_num_rows($q))) {
        $willHide[(int) $x['role_id']] = ($willHide[(int) $x['role_id']] ?? 0) + 1;
    }
}

echo "روابطُ القوائم: مربوطٌ سلفًا={$already} · سيُربط=" . count($toLink) . " · بلا موديول (يبقى بلا فحص)={$unlinked}\n";
echo "ما سيختفي لكل دورٍ بعد الربط (مربوطٌ ولا can_view — الحارسُ يرفضه أصلًا):\n";
ksort($willHide);
$tot = 0;
foreach ($willHide as $rid => $n) { echo sprintf("  دور %-3d ← %d رابطًا\n", $rid, $n); $tot += $n; }
echo "  الإجمالي: {$tot}\n";

if (!$APPLY) { echo "(معاينةٌ — التطبيق بـ --apply)\n"; exit(0); }

$done = 0;
foreach ($toLink as $id => $pl) {
    mysqli_query($conn, "UPDATE nav_items SET module_id={$pl['module_id']}, permission_code='"
        . mysqli_real_escape_string($conn, $pl['permission_code']) . "' WHERE id={$id}");
    $done++;
}
echo "طُبِّق: رُبط {$done} رابطًا.\n";
