<?php
/**
 * tools/govauth_parity_probe.php — تكافؤُ القرار: القالبُ مقابلَ القائمِ
 * ───────────────────────────────────────────────────────────────────────────
 * قبلَ التبديلِ وبعدَه: لكلِّ مستخدمٍ مغطًّى بقالبٍ مبذورٍ × كلِّ شاشةٍ يمسُّها
 * أيُّ الطرفين — يقارن قرارَ القالبِ (gov_profile_items بالكود) بقرارِ القائمِ
 * (role_permissions لدورِه). الفرقُ يُعَدُّ ويُفصَّل — وصفرُه شرطُ قلبٍ آمن.
 * يخرج 1 عند وجودِ فرقِ can_view (فروقُ الأعلامِ الفرعيةِ تُعلَن ولا تُرسِّب).
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

/* المقارنةُ بالكودِ لا بمعرِّفِ الوحدة: التسجيلاتُ المكررةُ (كودٌ واحدٌ بمعرِّفين)
   تُجمَع بـMAX في الطرفين — فالإنفاذُ الفعليُّ للشاشةِ بكودِها (گوتشا «أدنى id») */
$sql = "
SELECT u.id user_id, u.username, u.role, mc.code screen_code,
       MAX(COALESCE(rp.can_view,0)) legacy_view,
       MAX(CASE WHEN i.item_id IS NULL THEN 0 ELSE i.allow END) tpl_view,
       MAX(COALESCE(rp.can_add,0)) legacy_add, MAX(COALESCE(i.can_add,0)) tpl_add,
       MAX(COALESCE(rp.can_edit,0)) legacy_edit, MAX(COALESCE(i.can_edit,0)) tpl_edit
  FROM gov_authority_grants g
  JOIN users u  ON u.id = g.user_id AND u.status = 1
  JOIN gov_role_profiles p ON p.profile_id = g.profile_id
  JOIN (SELECT DISTINCT profile_id FROM gov_profile_items) seeded ON seeded.profile_id = p.profile_id
  JOIN (SELECT DISTINCT code FROM modules) mc ON 1=1
  LEFT JOIN modules m ON m.code = mc.code
  LEFT JOIN role_permissions rp ON rp.role_id = u.role AND rp.module_id = m.id
  LEFT JOIN gov_profile_items i ON i.profile_id = p.profile_id AND i.item_kind='screen' AND i.item_ref = mc.code
 WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())
 GROUP BY u.id, u.username, u.role, mc.code
HAVING legacy_view = 1 OR tpl_view = 1";
$r = $conn->query($sql);
if ($r === false) { fwrite(STDERR, $conn->error . "\n"); exit(2); }

$total = 0; $viewMismatch = 0; $flagMismatch = 0; $rows = array();
while ($x = $r->fetch_assoc()) {
    $total++;
    if ((int) $x['legacy_view'] !== (int) $x['tpl_view']) {
        $viewMismatch++;
        $rows[] = "view\t{$x['username']} (دور {$x['role']})\t{$x['screen_code']}\tقائم={$x['legacy_view']} قالب={$x['tpl_view']}";
    } elseif ((int) $x['legacy_add'] !== (int) $x['tpl_add'] || (int) $x['legacy_edit'] !== (int) $x['tpl_edit']) {
        $flagMismatch++;
    }
}
echo "════ تكافؤُ القالبِ والقائم ════\n";
echo "أزواجُ (مستخدمٌ مغطًّى × شاشة): {$total}\n";
echo "فرقُ can_view: {$viewMismatch}   [شرطُ القلب: 0]\n";
echo "فرقُ أعلامٍ فرعية (add/edit): {$flagMismatch}   [يُعلَن]\n";
foreach (array_slice($rows, 0, 25) as $ln) { echo "  ✘ {$ln}\n"; }
if (count($rows) > 25) { echo "  … و" . (count($rows) - 25) . " أخرى\n"; }
exit($viewMismatch === 0 ? 0 : 1);
