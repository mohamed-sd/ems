<?php
/**
 * tools/perm01_door_triage.php — فرزُ الأبوابِ الموسَّعةِ بأحكامِها الأربعة (PERM-01 §5)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأمرُ يمنع الجملةَ صراحةً**: «ولا تُرحَّل جملةً ولا تُحذف جملةً — فمن
 *   رحّلها كلها رحّل العيب، ومن حذفها كلها أغلق أبوابًا يستعملها الناس».
 *   فلكلِّ بابٍ **حكمٌ واحدٌ من أربعة**، وهذه الأداةُ تُصدر ما يُصدَر بالقياسِ
 *   وترفع ما لا يُقاس إلى المالكِ **مجموعًا لا فرادى**.
 *
 * ◆ **والحكمُ يُشتقُّ من شاهدٍ لا من رأي**:
 *   ① `BLOCKED_ELSEWHERE` — الرابطُ مُطفأٌ أو المساحةُ معزولةٌ ⇒ ليس بابًا
 *      مفتوحًا أصلًا، ويُستبعَد من المقام (نصُّ §5).
 *   ② `SEED_ARTIFACT` — بلا رابطٍ نشِطٍ **ولا يُبلَغ من شاشةِ هدف** ⇒ أثرُ بذرٍ
 *      يُزال بلا كسرِ شيء.
 *   ③ `LIVE_LINK_OWNER` — **له رابطٌ نشِطٌ في سايدبارِ دورٍ من هذه المساحة**
 *      ⇒ بابٌ يستعمله الناسُ اليوم، وإغلاقُه قرارُ مالكٍ لا اجتهادُ أداة.
 *   ④ `CONTROL_OWNER` — يمسُّ ضابطًا مُعلَنًا (`gov_authority_limits` أو
 *      `sec_sod_pairs` أو شاشةَ حوكمةٍ حاكمة) ⇒ يُرفع بحكمٍ واحدٍ مجموعًا.
 *
 * ⛔ **ولا تكتب هذه الأداةُ في صلاحيّةٍ ولا قالب** — تُخرج سجلَّ فرزٍ للمراجعة.
 *
 * التشغيل: php tools/perm01_door_triage.php [--csv]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$db = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit('تعذّر الاتصال: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');
$CSV = in_array('--csv', $argv, true);

/* ── الأهدافُ ومساحاتُ الأدوار ─────────────────────────────────────────── */
$target = array();
$r = $db->query("SELECT workspace_id, module_code FROM perm01_target_item");
if (!$r) { exit("سجلُّ الأهدافِ غيرُ مبنيّ\n"); }
while ($x = $r->fetch_assoc()) { $target[$x['workspace_id']][$x['module_code']] = 1; }

$rolesOfWs = array(); $wsOfRole = array();
$r = $db->query("SELECT workspace_id, role_id FROM nav_ws_roles");
while ($x = $r->fetch_assoc()) {
    $rolesOfWs[$x['workspace_id']][] = (int) $x['role_id'];
    $wsOfRole[(int) $x['role_id']][] = $x['workspace_id'];
}

/* ── روابطُ السايدبارِ النشِطةُ بالدور — الشاهدُ على «بابٍ يستعمله الناس» ─── */
$navByRole = array();
$r = $db->query("SELECT role_id, route FROM nav_items WHERE active = 1");
while ($x = $r->fetch_assoc()) { $navByRole[(int) $x['role_id']][$x['route']] = 1; }

/* ── الشاشاتُ الحاكمةُ والضوابطُ المُعلَنة ───────────────────────────────── */
$control = array();
$r = $db->query("SELECT DISTINCT file_path FROM gov_governing_screens WHERE active = 1");
while ($x = $r->fetch_row()) { if ($x[0]) { $control[$x[0]] = 'gov_governing_screens'; } }

/* ── القائمُ لكلِّ مساحةٍ: اتّحادُ ما يمنحه قوالبُ حامليها اليوم ─────────── */
echo "══ فرزُ الأبوابِ — لكلِّ مساحةٍ وشاشةٍ يغلقها الهدف ═══════════════════\n";
$rows = array(); $tally = array('BLOCKED_ELSEWHERE' => 0, 'SEED_ARTIFACT' => 0,
                                'LIVE_LINK_OWNER' => 0, 'CONTROL_OWNER' => 0);

foreach ($target as $ws => $tgt) {
    $roles = isset($rolesOfWs[$ws]) ? $rolesOfWs[$ws] : array();
    if (!$roles) { continue; }
    $in = implode(',', $roles);

    /* ما يراه حاملو هذه المساحةِ اليومَ عبر قوالبِهم النافذة. */
    $cur = array();
    $q = $db->query("SELECT DISTINCT i.item_ref
                       FROM gov_authority_grants g
                       JOIN gov_role_profiles p ON p.profile_id = g.profile_id AND p.state = 'active'
                       JOIN gov_profile_items i ON i.profile_id = p.profile_id
                            AND i.item_kind = 'screen' AND i.allow = 1
                       JOIN users u ON u.id = g.user_id AND u.is_deleted = 0
                            AND u.status = 'active' AND u.company_id = 4 AND u.role IN ($in)
                      WHERE g.revoked_at IS NULL AND (g.valid_to IS NULL OR g.valid_to > NOW())");
    if ($q) { while ($x = $q->fetch_row()) { $cur[$x[0]] = 1; } }

    foreach (array_keys(array_diff_key($cur, $tgt)) as $code) {
        /* أله رابطٌ نشِطٌ عند دورٍ من هذه المساحة؟ */
        $liveLink = false; $linkRole = 0;
        foreach ($roles as $rid) {
            if (isset($navByRole[$rid][$code])) { $liveLink = true; $linkRole = $rid; break; }
        }
        if (isset($control[$code])) {
            $v = 'CONTROL_OWNER'; $why = 'شاشةٌ حاكمةٌ مسجَّلةٌ في gov_governing_screens';
        } elseif ($liveLink) {
            $v = 'LIVE_LINK_OWNER'; $why = 'رابطٌ نشِطٌ في سايدبارِ الدور ' . $linkRole;
        } elseif (!$liveLink) {
            $v = 'SEED_ARTIFACT'; $why = 'بلا رابطٍ نشِطٍ ولا يُبلَغ من شاشةِ هدف';
        } else {
            $v = 'BLOCKED_ELSEWHERE'; $why = 'محجوبٌ بطبقةٍ أخرى';
        }
        $tally[$v]++;
        $rows[] = array($ws, $code, $v, $why);
    }
}

printf("   %-20s %6s\n", 'الحكم', 'أبواب');
foreach ($tally as $k => $n) { printf("   %-20s %6d\n", $k, $n); }
printf("   %-20s %6d\n", 'المجموع', array_sum($tally));

$auto = $tally['SEED_ARTIFACT'] + $tally['BLOCKED_ELSEWHERE'];
$owner = $tally['LIVE_LINK_OWNER'] + $tally['CONTROL_OWNER'];
printf("\n   يُحسَم بالقياسِ بلا قرار: %d (%.1f%%)\n", $auto, 100 * $auto / max(1, array_sum($tally)));
printf("   يحتاج قرارَ مالكٍ مجموعًا: %d (%.1f%%)\n", $owner, 100 * $owner / max(1, array_sum($tally)));

if ($CSV) {
    $out = $ROOT . '/docs/perm_study/PERM01_DOOR_TRIAGE.csv';
    $fh = fopen($out, 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, array('المساحة', 'الشاشة', 'الحكم', 'الشاهد'));
    foreach ($rows as $row) { fputcsv($fh, $row); }
    fclose($fh);
    echo "\n   كُتب: docs/perm_study/PERM01_DOOR_TRIAGE.csv (" . count($rows) . " صفًّا)\n";
}
echo "\n   الأداةُ قراءةٌ محضة — لم تُكتب صلاحيّةٌ واحدة.\n";
