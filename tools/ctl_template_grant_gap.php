<?php
/**
 * tools/ctl_template_grant_gap.php — فجوةُ الثباتِ بين القوالبِ والمنح
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ طبقةُ GOV-AUTH-01 بُذرت من `role_permissions` (`seeded_from`) ثم صارت
 *   حاكمةً («لا شاشةَ خارجَ القالب») — وشاشاتٌ ممنوحةٌ اليومَ في المنحِ
 *   غائبةٌ عن قوالبِ أدوارِها فيمنعها القالبُ وحدَه.
 * ◆ **والفجوةُ جنسان لا يُحسمان آليًّا** (قِيس ٢٠٢٦-٠٨-٣١):
 *   قديمُ المعرِّفِ قد يكون **انتقاءَ قالبٍ مقصودًا** (روحُ الطبقةِ نفسُها)،
 *   وحديثُه انجرافَ ما بعدَ البذرِ (مُنح ولم يُبذَر بندُه). فالمزجُ ملؤُهما
 *   معًا **تغييرُ سياسةِ وصولٍ** — قرارُ مالكٍ يُرفَع فئةً بهذا السجلِّ
 *   المقيسِ، لا 557 صفًّا.
 * ◆ المقياسُ بالمستخدمِ الحيِّ لا بالدورِ المجرَّد (قاعدةُ القياسِ القائمة).
 *
 * التشغيل: php tools/ctl_template_grant_gap.php [--md]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$MD = in_array('--md', $argv, true);

$rows = array();
$r = $conn->query("
 SELECT p.profile_code, CAST(u.role AS UNSIGNED) role_id, m.id mid, m.code
   FROM gov_authority_grants g
   JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
   JOIN users u ON u.id = g.user_id AND u.company_id = 4
   JOIN role_permissions rp ON rp.role_id = CAST(u.role AS UNSIGNED) AND rp.can_view = 1
   JOIN modules m ON m.id = rp.module_id
  WHERE g.revoked_at IS NULL
    AND NOT EXISTS(SELECT 1 FROM gov_profile_items i
                    WHERE i.profile_id = p.profile_id AND i.item_kind = 'screen'
                      AND i.item_ref = m.code AND i.allow = 1)
  GROUP BY p.profile_code, role_id, m.id
  ORDER BY p.profile_code, m.id");
while ($x = $r->fetch_assoc()) { $rows[] = $x; }
/* حدُّ الجنسَين: أكبرُ وحدةٍ لها بندُ بذرٍ أصليٍّ (`role_permissions:%`) —
   ما بعدها مُنح بعد عصرِ البذرِ فهو انجرافٌ ظاهرُ الجنس */
$seedMax = 0;
$q = $conn->query("SELECT MAX(m.id) FROM gov_profile_items i JOIN modules m ON m.code = i.item_ref
                    WHERE i.item_kind = 'screen' AND i.seeded_from LIKE 'role_permissions:%'");
if ($q && ($z = $q->fetch_row())) { $seedMax = (int) $z[0]; }

$old = array(); $new = array();
foreach ($rows as $x) {
    if ((int) $x['mid'] <= $seedMax) { $old[] = $x; } else { $new[] = $x; }
}
printf("═══ فجوةُ الثباتِ قالبٌ/منح — بالمستخدمِ الحيّ ═══\n");
printf("مجموعُ الفجوات: %d · حدُّ عصرِ البذرِ m<=%d\n", count($rows), $seedMax);
printf("جنسُ الانتقاءِ المحتملِ (قديمُ العصر): %d · جنسُ الانجرافِ الظاهرِ (بعده): %d\n", count($old), count($new));
$agg = array();
foreach ($rows as $x) { $k = $x['profile_code'] . ' (دور ' . $x['role_id'] . ')'; $agg[$k] = 1 + (isset($agg[$k]) ? $agg[$k] : 0); }
arsort($agg);
foreach (array_slice($agg, 0, 12, true) as $k => $n0) { printf("  %-24s %d\n", $k, $n0); }

if ($MD) {
    $o = "# فجوةُ الثباتِ بين قوالبِ GOV-AUTH-01 والمنح\n\n";
    $o .= "> مولَّدٌ من تشغيلٍ حيّ: `php tools/" . basename(__FILE__) . " --md`\n\n";
    $o .= "| المجموع | حدُّ عصرِ البذر | انتقاءٌ محتمل (قديم) | انجرافٌ ظاهر (حديث) |\n|---|---|---|---|\n";
    $o .= '| ' . count($rows) . " | m<=$seedMax | " . count($old) . ' | ' . count($new) . " |\n\n";
    $o .= "## الانجرافُ الظاهرُ (مُنح بعد عصرِ البذرِ ولم يُبذَر بندُه)\n\n| القالب | الدور | الشاشة |\n|---|---|---|\n";
    foreach ($new as $x) { $o .= '| ' . $x['profile_code'] . ' | ' . $x['role_id'] . ' | `' . $x['code'] . "` |\n"; }
    $o .= "\n## الانتقاءُ المحتملُ (قديمُ العصرِ — قد يكون قصدَ قالبٍ)\n\n| القالب | الدور | الشاشة |\n|---|---|---|\n";
    foreach ($old as $x) { $o .= '| ' . $x['profile_code'] . ' | ' . $x['role_id'] . ' | `' . $x['code'] . "` |\n"; }
    $o .= "\n⛔ **الملءُ قرارُ سياسةِ وصولٍ** — يُرفَع فئةً (`OA-TEMPLATE-SYNC`) لا صفوفًا.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/TEMPLATE_GRANT_GAP.md', $o);
    echo "✔ كُتب docs/REPAIR01_20260823/TEMPLATE_GRANT_GAP.md\n";
}
