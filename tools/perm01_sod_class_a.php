<?php
/**
 * tools/perm01_sod_class_a.php — جدولُ الصنفِ أ (تنفيذُ الطرفَين) · ق-٧
 * ═══════════════════════════════════════════════════════════════════════════
 * PERM-01-DEC ق-٧ يشقُّ الـ120 صنفَين ويجعل البوّابةَ على الأوّلِ وحدَه:
 *   **أ · تنفيذُ الطرفَين** — القالبُ يمنحه **أعلامَ كتابةٍ** على طرفَي التعارضِ
 *     معًا، فيستطيع تنفيذَ الفعلَين ولا يمنعه إلّا الحارسُ على الفعل.
 *     ⇒ «ترفع أولويّةً وتُغلق»، والعائلاتُ المادّيّةُ منها **تُغلق قبلَ غيرِها**.
 *   **ب · تداخلُ رؤيةٍ فقط** — يرى الشاشتَين ولا يملك تنفيذَ الطرفَين.
 *     ⇒ «مؤشِّرٌ فصليٌّ لا حاجزُ إغلاق».
 *
 * ⛔ **والمقامُ يُقاس قبلَ أن يُقرأ صفرُه**: قِيس الصنفُ أ صفرًا مرّةً وكان صفرًا
 *   **بنيويًّا** — أعلامُ الكتابةِ كانت ساقطةً في القوالبِ كلِّها فلا أحدَ ينفّذ
 *   فعلًا واحدًا فضلًا عن طرفَين. ورُدَّت في هجرة 2028_05_12.
 *
 * ◆ **والجدولُ يُخرِج ما يُغلَق لا رقمًا مجرَّدًا**: لكلِّ حالةٍ تركيبتُها ووظيفتاها
 *   والقالبُ ودورُه — فالإغلاقُ يحتاج اسمًا لا عددًا.
 *
 * التشغيل: php tools/perm01_sod_class_a.php [--csv=مسار]
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

/* العائلاتُ المادّيّةُ بنصِّ الأمر: مالية · خزينة · مشتريات · منحُ صلاحيات. */
$MATERIAL = '/مالي|خزين|مشتر|صلاحي|بنك|دفع|مطابق/u';

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

$prof = array(); $meta = array();
$r = $db->query("SELECT p.profile_id, p.profile_code, i.item_ref,
                        (i.can_add|i.can_edit|i.can_delete) w
                   FROM gov_role_profiles p
                   LEFT JOIN gov_profile_items i ON i.profile_id=p.profile_id
                        AND i.item_kind='screen' AND i.allow=1
                  WHERE p.state='active'");
while ($x = $r->fetch_assoc()) {
    $pid = (int) $x['profile_id'];
    $meta[$pid] = $x['profile_code'];
    if (!isset($prof[$pid])) { $prof[$pid] = array(); }
    if ($x['item_ref'] !== null) { $prof[$pid][$x['item_ref']] = (int) $x['w']; }
}
/* دورُ كلِّ قالبٍ وعددُ حاملِيه — فالإغلاقُ يمسُّ أشخاصًا لا صفوفًا. */
$roleOf = array(); $usersOf = array();
$r = $db->query("SELECT g.profile_id, u.role, COUNT(DISTINCT g.user_id) n
                   FROM gov_authority_grants g
                   JOIN users u ON u.id=g.user_id AND u.is_deleted=0 AND u.status='active'
                  WHERE g.revoked_at IS NULL GROUP BY g.profile_id, u.role");
while ($x = $r->fetch_assoc()) {
    $roleOf[(int) $x['profile_id']] = (int) $x['role'];
    $usersOf[(int) $x['profile_id']] = (int) $x['n'];
}

$rowsA = array(); $countB = 0;
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
        if (!($wA && $wB)) { $countB++; continue; }
        $wrA = array(); foreach ($hitA as $k => $z) { if ($z) { $wrA[] = $k; } }
        $wrB = array(); foreach ($hitB as $k => $z) { if ($z) { $wrB[] = $k; } }
        $rowsA[] = array(
            'material'  => preg_match($MATERIAL, $fam) ? 1 : 0,
            'pair'      => (string) $p['code'],
            'func_a'    => (string) $p['func_a'],
            'func_b'    => (string) $p['func_b'],
            'profile'   => $meta[$pid],
            'role'      => isset($roleOf[$pid]) ? $roleOf[$pid] : 0,
            'users'     => isset($usersOf[$pid]) ? $usersOf[$pid] : 0,
            'writes_a'  => implode(' | ', array_slice($wrA, 0, 3)),
            'writes_b'  => implode(' | ', array_slice($wrB, 0, 3)),
        );
    }
}
usort($rowsA, function ($x, $y) {
    if ($x['material'] !== $y['material']) { return $y['material'] - $x['material']; }
    if ($x['users'] !== $y['users']) { return $y['users'] - $x['users']; }
    return strcmp($x['pair'], $y['pair']);
});

$mat = 0; foreach ($rowsA as $x) { $mat += $x['material']; }
echo "══ جدولُ الصنفِ أ — تنفيذُ الطرفَين (بوّابةُ الإغلاق) ═══════════════════\n";
printf("   الصنفُ أ: %d · منها في العائلاتِ المادّيّةِ (تُغلق أوّلًا): %d\n", count($rowsA), $mat);
printf("   الصنفُ ب (تداخلُ رؤيةٍ فقط · مؤشِّرٌ لا حاجز): %d\n", $countB);
printf("   المجموعُ بالمقامِ القديم: %d\n\n", count($rowsA) + $countB);
printf("   %-3s %-4s %-10s %-5s %-5s %s\n", '#', 'مادي', 'القالب', 'دور', 'أفراد', 'التركيبة');
echo "   " . str_repeat('-', 92) . "\n";
$i = 0;
foreach ($rowsA as $x) {
    $i++;
    printf("   %-3d %-4s %-10s %-5s %-5s %s\n", $i, $x['material'] ? 'نعم' : '—',
        $x['profile'], $x['role'], $x['users'],
        mb_substr($x['func_a'] . ' ⟂ ' . $x['func_b'], 0, 54));
}
echo "\n   ◆ الإغلاقُ يكون بنزعِ أعلامِ الكتابةِ عن **أحدِ الطرفَين** في القالبِ —\n";
echo "     ولا يُنزَع العرضُ: تداخلُ الرؤيةِ صنفٌ ب ومؤشِّرٌ لا حاجز.\n";
echo "   ⛔ وكلُّ نزعٍ يمسُّ أفرادًا — فالعمودُ «أفراد» يقول كم شخصًا يتأثّر.\n";

if ($CSV !== null) {
    $fh = fopen($CSV, 'w');
    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, array('مادي', 'التركيبة', 'وظيفة أ', 'وظيفة ب', 'القالب', 'الدور',
                       'أفراد', 'شاشات كتابة أ', 'شاشات كتابة ب'));
    foreach ($rowsA as $x) {
        fputcsv($fh, array($x['material'] ? 'نعم' : 'لا', $x['pair'], $x['func_a'], $x['func_b'],
                           $x['profile'], $x['role'], $x['users'], $x['writes_a'], $x['writes_b']));
    }
    fclose($fh);
    echo "\n   ✔ كُتب الجدول: {$CSV}\n";
}
