<?php
/**
 * tools/perm01_target_shadow.php — الظلُّ: ما سيتغيّر قبل أن يتغيّر (PERM-01 §3-⑥)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **يُقاس ولا يُنفَّذ**: الأداةُ قراءةٌ محضةٌ — لا تكتب حرفًا في قالبٍ ولا منحةٍ
 *   ولا صلاحيّة. غايتُها أن يُعرف **ما يُفتَح وما يُغلَق وما يُمنَع خطأً** قبلَ
 *   أيِّ تحوّل، وهو نصُّ §3-⑥.
 *
 * ◆ **المقارنةُ بالمستخدمِ لا بالدور**: القالبُ يُمنح للفردِ فمقارنةٌ بالدورِ
 *   لا ترى الفرقَ أصلًا. ولكلِّ مستخدمٍ حيٍّ حالتان:
 *     القائمُ = حكمُ قالبِه النافذِ إن كان مغطًّى، وإلا صفوفُ دورِه.
 *     الهدفُ  = بنودُ مساحةِ دورِه في `perm01_target_item` (الدليلُ + «مساحتي»).
 *
 * ⛔ **وحارسُ القفلِ يسبق كلَّ رقم**: مستخدمٌ يخرج بصفرِ شاشةٍ ليس «تضييقًا»
 *   بل قفلٌ خارجَ النظام. فيُعدُّ ويُسمّى، ولا يُطوى في متوسِّط.
 *
 * ◆ **والوحدةُ الكودُ المتمايز** لا صفُّ الوحدة — `modules.code` غيرُ فريد.
 *
 * التشغيل: php tools/perm01_target_shadow.php [--company=4] [--role=NN]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

$CO = 4; $ONLY = 0;
foreach ($argv as $a) {
    if (preg_match('/^--company=(\d+)$/', $a, $m)) { $CO = (int) $m[1]; }
    if (preg_match('/^--role=(\d+)$/', $a, $m))    { $ONLY = (int) $m[1]; }
}
$one = function ($sql) use ($db) { $r = $db->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };

if ($one("SELECT COUNT(*) FROM information_schema.TABLES
           WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='perm01_target_item'") < 1) {
    exit("سجلُّ الأهدافِ غيرُ مبنيّ — شغِّل database/migrations/2028_05_04_perm01_target_profiles.php\n");
}

/* ── الأهدافُ بالمساحة ─────────────────────────────────────────────────── */
$target = array();
$r = $db->query("SELECT workspace_id, module_code FROM perm01_target_item");
while ($x = $r->fetch_assoc()) { $target[$x['workspace_id']][$x['module_code']] = 1; }

/* ── مساحاتُ كلِّ دور ──────────────────────────────────────────────────── */
$wsOfRole = array();
$r = $db->query("SELECT role_id, workspace_id FROM nav_ws_roles");
while ($x = $r->fetch_assoc()) { $wsOfRole[(int) $x['role_id']][] = $x['workspace_id']; }

/* ── المستخدمون الأحياء ───────────────────────────────────────────────── */
$users = array();
$r = $db->query("SELECT u.id, u.username, u.name, u.role, ro.name role_name
                   FROM users u LEFT JOIN roles ro ON ro.id = u.role
                  WHERE u.is_deleted = 0 AND u.status = 'active' AND u.company_id = $CO
                  ORDER BY u.role, u.id");
while ($x = $r->fetch_assoc()) { $users[] = $x; }

/* ── القائمُ لكلِّ مستخدم ─────────────────────────────────────────────── */
function currentOf($db, $uid, $role)
{
    /* طبقةُ القالبِ أولًا — فالمغطّى يُحكَم بقالبِه حصرًا. */
    $out = array(); $covered = false;
    $q = $db->query("SELECT DISTINCT i.item_ref
                       FROM gov_authority_grants g
                       JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                       JOIN gov_profile_items i ON i.profile_id = p.profile_id
                            AND i.item_kind = 'screen' AND i.allow = 1
                      WHERE g.user_id = " . (int) $uid . " AND g.revoked_at IS NULL
                        AND (g.valid_to IS NULL OR g.valid_to > NOW())");
    if ($q) { while ($x = $q->fetch_row()) { $out[$x[0]] = 1; $covered = true; } }
    if ($covered) { return array($out, 'قالب'); }

    $q = $db->query("SELECT DISTINCT m.code FROM role_permissions rp
                       JOIN modules m ON m.id = rp.module_id
                      WHERE rp.role_id = " . (int) $role . " AND rp.can_view = 1");
    if ($q) { while ($x = $q->fetch_row()) { $out[$x[0]] = 1; } }
    return array($out, 'دور');
}

echo "══ الظلُّ — لكلِّ مستخدمٍ حيٍّ: القائمُ مقابلَ الهدف ═══════════════════\n";
printf("   %-6s %-22s %-3s %-7s %7s %7s %7s %7s\n",
    'مستخدم', 'الدور', 'م', 'يحكمه', 'قائم', 'هدف', 'يُغلق', 'يُفتح');

$sumClose = 0; $sumOpen = 0; $locked = array(); $noWs = array();
$byRole = array();
foreach ($users as $u) {
    $role = (int) $u['role'];
    if ($ONLY && $role !== $ONLY) { continue; }
    list($cur, $src) = currentOf($db, (int) $u['id'], $role);

    $tgt = array();
    $wss = isset($wsOfRole[$role]) ? $wsOfRole[$role] : array();
    foreach ($wss as $ws) { if (isset($target[$ws])) { $tgt += $target[$ws]; } }
    if (!$wss) { $noWs[] = $u['id'] . ' (دور ' . $role . ')'; }

    $close = count(array_diff_key($cur, $tgt));
    $open  = count(array_diff_key($tgt, $cur));
    $sumClose += $close; $sumOpen += $open;
    if (!$tgt) { $locked[] = $u['id'] . ' (دور ' . $role . ')'; }

    if (!isset($byRole[$role])) {
        $byRole[$role] = array('name' => $u['role_name'], 'n' => 0, 'cur' => 0, 'tgt' => 0, 'c' => 0, 'o' => 0,
                               'ws' => implode(',', $wss));
    }
    $byRole[$role]['n']++; $byRole[$role]['cur'] += count($cur); $byRole[$role]['tgt'] += count($tgt);
    $byRole[$role]['c'] += $close; $byRole[$role]['o'] += $open;
}

foreach ($byRole as $rid => $b) {
    printf("   %-6s %-22s %-3s %-7s %7s %7s %7s %7s\n",
        'دور ' . $rid, mb_substr((string) $b['name'], 0, 20), $b['n'], $b['ws'],
        intdiv($b['cur'], max(1, $b['n'])), intdiv($b['tgt'], max(1, $b['n'])),
        intdiv($b['c'], max(1, $b['n'])), intdiv($b['o'], max(1, $b['n'])));
}

echo "\n──────────────────────────────────────────────────────────────────────\n";
printf("   مستخدمون في الظلّ: %d\n", $ONLY ? array_sum(array_column($byRole, 'n')) : count($users));
printf("   أبوابٌ **تُغلق** (مجموعُ المستخدمين): %s\n", number_format($sumClose));
printf("   أبوابٌ **تُفتح**: %s\n", number_format($sumOpen));
printf("   مستخدمٌ يخرج بصفرِ شاشة: %d %s\n", count($locked),
    $locked ? '⇒ ' . implode(' · ', array_slice($locked, 0, 6)) : '');
printf("   مستخدمٌ بلا مساحةٍ حاكمة: %d %s\n", count($noWs),
    $noWs ? '⇒ ' . implode(' · ', array_slice($noWs, 0, 6)) : '');
/* ══ حارسُ الوصول — ورقةُ الدليلِ تعرّف **السايدبار** لا كلَّ ما يُبلَغ ══════
   ⛔ **وهذا أخطرُ ما في التحوّل**: شاشةُ تفاصيلَ تُبلَغ بالنقرِ من شاشةٍ أخرى
     (بطاقةُ عميلٍ · سجلُّ أحداثِ عقدٍ · صندوقُ اعتماد) ليس لها رابطٌ في القائمة،
     فلا تظهر في ورقةِ الدليلِ ولا في الهدفِ — وإغلاقُها **يكسر مسارَ عملٍ
     صامتًا**: الرابطُ يعمل والوجهةُ تردُّ 403.
   ◆ فالهدفُ **ناقصٌ طبقةً** حتى تُحسَم هذه: إمّا تُضاف الشاشاتُ المبلوغةُ
     بالرابط، وإمّا يُعلَن قصرُ الوصولِ على القائمة. ولا يُطبَّق التحوّلُ قبلَها. */
echo "\n══ حارسُ الوصول — الشاشاتُ المُغلَقةُ التي لا رابطَ لها ═══════════════\n";
$closedAll = array();
$r = $db->query("SELECT DISTINCT i.item_ref FROM gov_profile_items i
                   JOIN gov_role_profiles p ON p.profile_id = i.profile_id AND p.state = 'active'
                  WHERE i.item_kind = 'screen' AND i.allow = 1
                    AND i.item_ref NOT IN (SELECT module_code FROM perm01_target_item)");
while ($x = $r->fetch_row()) { $closedAll[$x[0]] = 1; }
$navSet = array();
$r = $db->query("SELECT DISTINCT route FROM nav_items WHERE active = 1");
while ($x = $r->fetch_row()) { $navSet[$x[0]] = 1; }
$noLink = array_diff_key($closedAll, $navSet);
printf("   شاشاتٌ متمايزةٌ يغلقها الهدفُ: %d\n", count($closedAll));
printf("   منها لها رابطٌ نشِطٌ في القائمة: %d\n", count($closedAll) - count($noLink));
printf("   ومنها **بلا رابطٍ — تُبلَغ بالنقرِ من شاشةٍ أخرى**: %d\n", count($noLink));
$sample = array_slice(array_keys($noLink), 0, 8);
foreach ($sample as $c) { echo "      · " . $c . "\n"; }
echo "   ⛔ لا يُطبَّق التحوّلُ قبلَ حسمِ هذه — إغلاقُها يردُّ 403 على رابطٍ يعمل.\n";

echo "\n   الأداةُ قراءةٌ محضة — لم تُكتب صلاحيّةٌ واحدة.\n";
