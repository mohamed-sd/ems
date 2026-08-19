<?php
/**
 * تصنيفُ فروقِ التكافؤِ الثلاثيُّ — على **كلِّ** الصفوفِ لا على المعروضِ منها
 * ◆ الفاحصُ القائمُ يقتطع عند 25 صفًّا في **العرضِ** — فقراءةُ مخرَجِه أعطت
 *   «25» بدل الإجماليّ. **ومقامٌ مقروءٌ من عرضٍ مقتطَعٍ كذبٌ صامت.**
 *   فيُعاد الاستعلامُ نفسُه حرفًا (لا يُعاد بناءُ منطقِه) ويُصنَّف كلُّ صفّ.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
define('EMS_CLI', true);
require_once dirname(__DIR__) . '/includes/session_bootstrap.php';
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$sql = "
SELECT u.id user_id, u.username, u.role, mc.code screen_code,
       MAX(COALESCE(rp.can_view,0)) legacy_view,
       MAX(CASE WHEN i.item_id IS NULL THEN 0 ELSE i.allow END) tpl_view
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
HAVING legacy_view <> tpl_view";
$r = $conn->query($sql);
if ($r === false) { exit("✗ " . $conn->error . "\n"); }

$dom = array();
$q = $conn->query("SELECT LOWER(route) rt, policy_domain, owner_dept FROM nav_canonical");
while ($q && ($x = $q->fetch_assoc())) { $dom[$x['rt']] = $x; }
$vis = array();
$q = $conn->query("SELECT role_id, LOWER(route) rt FROM nav_items WHERE active=1 AND route IS NOT NULL");
while ($q && ($x = $q->fetch_assoc())) { $vis[$x['role_id'] . '|' . $x['rt']] = true; }

$cat = array('INTENTIONAL' => 0, 'FIX_TEMPLATE' => 0, 'CREATE_GRANT' => 0);
$dir = array('قالب=1 قائم=0' => 0, 'قالب=0 قائم=1' => 0);
$samples = array(); $n = 0;
while ($x = $r->fetch_assoc()) {
    $n++;
    $rt = mb_strtolower($x['screen_code']);
    $d = isset($dom[$rt]) ? $dom[$rt] : null;
    $protected   = $d && $d['policy_domain'] !== 'NAVIGATION_NAMING_POSITION';
    $ownerIsOther = $d && stripos((string) $d['owner_dept'], 'دور ' . $x['role']) === false;
    $seen = isset($vis[$x['role'] . '|' . $rt]);
    $dir[((int) $x['tpl_view'] === 1) ? 'قالب=1 قائم=0' : 'قالب=0 قائم=1']++;

    if ($protected && $ownerIsOther) { $k = 'INTENTIONAL'; }
    elseif (!$seen)                  { $k = 'FIX_TEMPLATE'; }
    else                             { $k = 'CREATE_GRANT'; }
    $cat[$k]++;
    if (count($samples[$k] ?? array()) < 3) { $samples[$k][] = 'دور ' . $x['role'] . ' · ' . $x['screen_code']; }
}
$LBL = array('INTENTIONAL' => 'مقصودٌ ← استثناءٌ موثَّق',
             'FIX_TEMPLATE' => 'الواقعُ صحيحٌ ← عدّلِ القالب',
             'CREATE_GRANT' => 'القالبُ صحيحٌ ← أنشئ المنحة');
echo "════ تصنيفُ فروقِ التكافؤِ — على كلِّ الصفوف ════\n";
printf("  الإجماليُّ المقيسُ الآن: **%d**   (وv25 تقول 351)\n", $n);
echo "  ▐ الاتجاه\n";
foreach ($dir as $k => $v) { printf("    · %-16s %d\n", $k, $v); }
echo "\n  ▐ التصنيفُ الثلاثيّ\n";
foreach ($cat as $k => $v) {
    printf("    %-30s **%3d** = %.1f٪\n", $LBL[$k], $v, $n ? 100 * $v / $n : 0);
    foreach (($samples[$k] ?? array()) as $s) { echo "          · {$s}\n"; }
}
