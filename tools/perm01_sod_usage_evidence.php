<?php
/**
 * tools/perm01_sod_usage_evidence.php — أَقدرةٌ بلا استعمالٍ أم خرقٌ واقع؟
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المشكلةُ التي يحلُّها**: الصنفُ أ (تنفيذُ الطرفَين) **39 حالةً** — وإغلاقُها
 *   نزعُ قدرةِ كتابةٍ عن أشخاصٍ يعملون. وأعلى الجدولِ قالبٌ يحمله عشرون فردًا.
 *   فالسؤالُ قبلَ النزع: **أاستُعملت القدرةُ فعلًا، أم هي ورقٌ لم يُلمَس؟**
 *
 * ◆ **والمصدرُ سجلُّ الفعلِ لا سجلُّ النيّة**: `activity_logs` يقيّد الفاعلَ
 *   والمسارَ ونوعَ الفعل — وفيه أكثرُ من تسعين ألفَ فعلِ كتابةٍ حقيقيّ
 *   (`create`/`update`/`delete`). أمّا `ems_business_events` فلا يربط الواقعةَ
 *   بشاشةٍ، و`created_by` فيه أرقامٌ لا يقابل أكثرُها مستخدمًا — فلا يصلح.
 *
 * ⛔ **والغيابُ ليس براءةً بل صمتٌ يُسمّى**: «لم يُستعمَل في المدى المسجَّل» غيرُ
 *   «لا يُستعمَل». فيُطبع **مدى السجلِّ** ونصيبُ الفاعلِ منه، ويُترك الحكمُ
 *   للمالكِ ببيّنةٍ لا بظنّ.
 * ⛔ **والفاعلُ الصامتُ يُفرَز عن القدرةِ الصامتة**: من لم يفعل شيئًا قطُّ لا
 *   يُقاس عليه — يُعَدُّ في خانتِه («بلا أثرٍ في السجلّ») ولا يُخلَط بمن يعمل
 *   ولم يمسّ الطرفَين.
 *
 * التشغيل: php tools/perm01_sod_usage_evidence.php [--csv=مسار]
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
$CSV = null;
foreach ($argv as $a) { if (strpos($a, '--csv=') === 0) { $CSV = substr($a, 6); } }

/* ═══ ① مدى السجلِّ — يُعلَن قبلَ أيِّ استنتاجٍ منه ═══════════════════════ */
$w = $db->query("SELECT COUNT(*) n, MIN(created_at) a, MAX(created_at) b,
                        COUNT(DISTINCT user_id) u
                   FROM activity_logs
                  WHERE action_type IN ('create','update','delete')")->fetch_assoc();
printf("══ مدى سجلِّ الفعلِ ═══════════════════════════════════════════════\n");
printf("   أفعالُ كتابةٍ مسجَّلة: %s · فاعلون: %s\n", number_format((int) $w['n']), $w['u']);
printf("   من %s إلى %s\n\n", substr((string) $w['a'], 0, 10), substr((string) $w['b'], 0, 10));

/* ═══ ② مَن كتب على أيِّ شاشة — المسارُ يُستخرَج من الرابط ═══════════════ */
$wrote = array();   /* user_id => [screenCode => 1] */
$acted = array();   /* user_id => عددُ أفعالِه الكاتبة */
$r = $db->query("SELECT user_id, url, screen_name FROM activity_logs
                  WHERE action_type IN ('create','update','delete') AND user_id > 0");
while ($x = $r->fetch_assoc()) {
    $uid = (int) $x['user_id'];
    $acted[$uid] = ($acted[$uid] ?? 0) + 1;
    $path = (string) $x['url'];
    if ($path !== '') {
        $path = preg_replace('~^https?://[^/]+/ems/~i', '', $path);
        $path = preg_replace('/[?#].*$/', '', $path);
        if ($path !== '' && substr($path, -4) === '.php') { $wrote[$uid][$path] = 1; }
    }
}
printf("   فاعلون لهم أثرُ كتابةٍ يُقرأ: %d\n\n", count($wrote));

/* ═══ ③ الصنفُ أ — كما يعُدُّه معيارُ القبول ════════════════════════════ */
$pairs = array();
$pq = $db->query("SELECT code, func_a, func_b, roles_a, roles_b FROM sec_sod_pairs
                   WHERE active=1 AND severity='block' AND scope='role'
                     AND roles_a<>'' AND roles_b<>'' AND roles_b<>'*'");
while ($x = $pq->fetch_assoc()) { $pairs[] = $x; }
$screensOf = function ($csv) use ($db) {
    $ids = array_filter(array_map('intval', explode(',', (string) $csv)));
    if (!$ids) { return array(); }
    $r = $db->query("SELECT DISTINCT m.code FROM role_permissions rp JOIN modules m ON m.id=rp.module_id
                      WHERE rp.role_id IN (" . implode(',', $ids) . ") AND rp.can_view=1");
    $o = array(); while ($z = $r->fetch_row()) { $o[$z[0]] = 1; } return $o;
};
$prof = array(); $code = array(); $holders = array();
$r = $db->query("SELECT p.profile_id, p.profile_code, i.item_ref,
                        (i.can_add|i.can_edit|i.can_delete) w
                   FROM gov_role_profiles p
                   LEFT JOIN gov_profile_items i ON i.profile_id=p.profile_id
                        AND i.item_kind='screen' AND i.allow=1
                  WHERE p.state='active'");
while ($x = $r->fetch_assoc()) {
    $pid = (int) $x['profile_id']; $code[$pid] = $x['profile_code'];
    if (!isset($prof[$pid])) { $prof[$pid] = array(); }
    if ($x['item_ref'] !== null) { $prof[$pid][$x['item_ref']] = (int) $x['w']; }
}
$r = $db->query("SELECT g.profile_id, g.user_id FROM gov_authority_grants g
                   JOIN users u ON u.id=g.user_id AND u.is_deleted=0 AND u.status='active'
                  WHERE g.revoked_at IS NULL");
while ($x = $r->fetch_assoc()) { $holders[(int) $x['profile_id']][] = (int) $x['user_id']; }

$MATERIAL = '/مالي|خزين|مشتر|صلاحي|بنك|دفع|مطابق/u';
$rows = array();
foreach ($pairs as $p) {
    $sa = $screensOf($p['roles_a']); $sb = $screensOf($p['roles_b']);
    $exA = array_diff_key($sa, $sb); $exB = array_diff_key($sb, $sa);
    if (!$exA || !$exB) { continue; }
    $fam = (string) $p['func_a'] . ' / ' . (string) $p['func_b'];
    foreach ($prof as $pid => $items) {
        $hitA = array_intersect_key($items, $exA);
        $hitB = array_intersect_key($items, $exB);
        if (!$hitA || !$hitB) { continue; }
        $wA = 0; foreach ($hitA as $z) { $wA |= $z; }
        $wB = 0; foreach ($hitB as $z) { $wB |= $z; }
        if (!($wA && $wB)) { continue; }

        /* الشاشاتُ الكاتبةُ على كلِّ طرفٍ في هذا القالب. */
        $wsA = array(); foreach ($hitA as $k => $z) { if ($z) { $wsA[$k] = 1; } }
        $wsB = array(); foreach ($hitB as $k => $z) { if ($z) { $wsB[$k] = 1; } }

        $usedBoth = array(); $usedOne = 0; $silent = 0; $noTrace = 0;
        foreach ((isset($holders[$pid]) ? $holders[$pid] : array()) as $uid) {
            if (!isset($acted[$uid])) { $noTrace++; continue; }
            $mine = isset($wrote[$uid]) ? $wrote[$uid] : array();
            $a = (bool) array_intersect_key($mine, $wsA);
            $b = (bool) array_intersect_key($mine, $wsB);
            if ($a && $b) { $usedBoth[] = $uid; }
            elseif ($a || $b) { $usedOne++; }
            else { $silent++; }
        }
        $verdict = $usedBoth ? 'خرق واقع' : ($usedOne > 0 ? 'قدرة بلا استعمال' : 'صامت');
        $rows[] = array(
            'material' => preg_match($MATERIAL, $fam) ? 1 : 0,
            'pair' => (string) $p['code'], 'fam' => $fam,
            'profile' => $code[$pid],
            'people' => count(isset($holders[$pid]) ? $holders[$pid] : array()),
            'both' => count($usedBoth), 'one' => $usedOne,
            'silent' => $silent, 'no_trace' => $noTrace,
            'verdict' => $verdict,
            'who' => implode(' ', array_slice($usedBoth, 0, 6)),
        );
    }
}
usort($rows, function ($x, $y) {
    $o = array('خرق واقع' => 0, 'قدرة بلا استعمال' => 1, 'صامت' => 2);
    if ($o[$x['verdict']] !== $o[$y['verdict']]) { return $o[$x['verdict']] - $o[$y['verdict']]; }
    if ($x['material'] !== $y['material']) { return $y['material'] - $x['material']; }
    return $y['people'] - $x['people'];
});

$c = array('خرق واقع' => 0, 'قدرة بلا استعمال' => 0, 'صامت' => 0);
foreach ($rows as $x) { $c[$x['verdict']]++; }
printf("══ فرزُ الصنفِ أ بدليلِ الاستعمال ═══════════════════════════════\n");
printf("   المجموع: %d\n", count($rows));
printf("   ✘ **خرقٌ واقعٌ** — فاعلٌ كتب على الطرفَين فعلًا      : %d\n", $c['خرق واقع']);
printf("   ◆ **قدرةٌ بلا استعمال** — يعمل ولم يمسّ الطرفَين معًا : %d\n", $c['قدرة بلا استعمال']);
printf("   ◦ **صامت** — لا أثرَ كتابةٍ لحامليه في السجلّ         : %d\n\n", $c['صامت']);

printf("   %-3s %-16s %-4s %-10s %-5s %-5s %s\n", '#', 'الحكم', 'مادي', 'القالب', 'أفراد', 'طرفان', 'التركيبة');
echo "   " . str_repeat('-', 96) . "\n";
$i = 0;
foreach ($rows as $x) {
    $i++;
    printf("   %-3d %-16s %-4s %-10s %-5d %-5d %s\n", $i, $x['verdict'], $x['material'] ? 'نعم' : '—',
        $x['profile'], $x['people'], $x['both'], mb_substr($x['fam'], 0, 46));
}
echo "\n   ⛔ **والغيابُ صمتٌ لا براءة**: «صامت» تعني أنَّ حامليه لا أثرَ كتابةٍ لهم\n";
echo "     في هذا المدى — لا أنَّهم لن يستعملوها. والحكمُ للمالكِ ببيّنةٍ لا بظنّ.\n";

if ($CSV !== null) {
    $fh = fopen($CSV, 'w'); fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, array('الحكم', 'مادي', 'التركيبة', 'الوظيفتان', 'القالب', 'أفراد',
                       'كتبوا على الطرفين', 'كتبوا على طرف', 'يعملون ولم يمسوا', 'بلا أثر', 'من'));
    foreach ($rows as $x) {
        fputcsv($fh, array($x['verdict'], $x['material'] ? 'نعم' : 'لا', $x['pair'], $x['fam'],
                           $x['profile'], $x['people'], $x['both'], $x['one'], $x['silent'],
                           $x['no_trace'], $x['who']));
    }
    fclose($fh);
    echo "\n   ✔ كُتب الجدول: {$CSV}\n";
}
