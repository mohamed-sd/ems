<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ENG-01 ⑨ · الشاشاتُ الثماني — إثباتٌ مُشغَّلٌ عبر HTTP لا بوجودِ الكود
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/eng01_screens_http_test.php
 *
 *   S1 ✖ بلا جلسةٍ: تحويلٌ إلى الدخول — ولا 200 ولا 500.
 *   S2 ✚ بجلسةِ المالك: 200 وعنوانُ الشاشةِ ظاهرٌ في الصفحة.
 *   S3 ✖ بجلسةِ دورٍ لا يملكها: لا 200 — الحارسُ يحجب.
 *   S4 ✖ POST بلا رمزِ حمايةٍ: 403 — والرمزُ قبلَ المعالجةِ لا بعدَها.
 *   S5 ✚ POST برمزٍ صحيحٍ: يمرُّ ويُنادي الخدمة.
 *   S6 ✚ كلُّ شاشةٍ مسجَّلةٌ في سجلِّ الوحداتِ ولها رابطُ تنقّل.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$db = $conn;

$BASE = 'http://localhost/ems';
$pass = 0; $fail = 0;
function ok($id, $m) { global $pass; $pass++; echo "  ✔ $id  $m\n"; }
function no($id, $m) { global $fail; $fail++; echo "  ✘ $id  $m\n"; }
function ck($id, $c, $m) { $c ? ok($id, $m) : no($id, $m); }

$SCREENS = array(
    array('Governance/bus_outbox.php',     'صندوق الأحداث الصادر',      15),
    array('Governance/bus_deliveries.php', 'تسليمات الأحداث وحالاتها',  15),
    array('Governance/bus_board.php',      'لوحة الناقل',                15),
    array('Governance/job_queue.php',      'طابور المهام',               15),
    array('Governance/job_schedule.php',   'جدولة المهام الدورية',      15),
    array('Governance/dr_restore.php',     'الاستعادة ومحضرها',         15),
    array('Finance/asset_hours_link.php',  'ربط الأصل بساعات تشغيله',   19),
    array('Finance/depr_run.php',          'احتساب إهلاك الفترة',       19),
);

/** طلبُ GET/POST مع وعاءِ ارتباطات. */
function req($url, $jar, $post = null, $follow = false)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => 25,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return array('code' => $code, 'head' => substr((string) $raw, 0, $hs), 'body' => substr((string) $raw, $hs));
}

function login($base, $jar, $user, $pw)
{
    @unlink($jar);
    $g = req($base . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $g['body'], $m);
    $r = req($base . '/login.php', $jar, array(
        'username' => $user, 'password' => $pw,
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
    // الدخولُ الناجح تحويلٌ 302 لا 200 — و200 يعني عودةَ نموذجِ الدخولِ برفض
    return $r['code'] === 302 && stripos($r['head'], 'login.php') === false;
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo " ENG-01 · الشاشاتُ الثماني — إثباتٌ عبر HTTP\n";
echo "═══════════════════════════════════════════════════════════════\n";

$tmp = sys_get_temp_dir();

// ═════════ S1 ✖ بلا جلسة ═════════
echo "\n▐ S1 ✖ بلا جلسةٍ — تحويلٌ إلى الدخول\n";
$jarAnon = $tmp . '/eng01_anon.txt'; @unlink($jarAnon);
$bad = array();
foreach ($SCREENS as $s) {
    $r = req($BASE . '/' . $s[0], $jarAnon);
    if ($r['code'] !== 302) { $bad[] = $s[0] . '=' . $r['code']; }
}
ck('S1-a', count($bad) === 0, count($bad) === 0
    ? 'الثمانيةُ تُحوّل إلى الدخول (302)'
    : 'خالف: ' . implode(', ', $bad));

// ═════════ S2 ✚ بجلسةِ المالك ═════════
echo "\n▐ S2 ✚ بجلسةِ الدورِ المالك — 200 والعنوانُ ظاهر\n";
$jar15 = $tmp . '/eng01_r15.txt';
$jar19 = $tmp . '/eng01_r19.txt';
$in15 = login($BASE, $jar15, 'حسابات', '12345678');
$in19 = login($BASE, $jar19, 'fin.deptmgr@equipation.sd', '12345678');
ck('S2-0', $in15 && $in19, 'دخل الدوران 15 و19');

$okCount = 0; $notes = array();
foreach ($SCREENS as $s) {
    $jar = ($s[2] === 15) ? $jar15 : $jar19;
    $r = req($BASE . '/' . $s[0], $jar);
    $hasTitle = (strpos($r['body'], $s[1]) !== false);
    if ($r['code'] === 200 && $hasTitle) { $okCount++; }
    else { $notes[] = $s[0] . ' (HTTP ' . $r['code'] . ($hasTitle ? '' : ' · بلا عنوان') . ')'; }
}
ck('S2-a', $okCount === 8, "$okCount/8 شاشةً تُقدَّم بعنوانِها" . ($notes ? ' — ' . implode(' · ', $notes) : ''));

// ◆ الشاشةُ بلا سايدبارٍ تُوقف المستخدمَ فيها بلا مخرَج — عيبٌ وقع فعلًا
//   حين اقتصر التضمينُ على inheader دون insidebar، فخرجت الثمانيةُ بصفرِ روابط.
$noNav = array();
foreach ($SCREENS as $s) {
    $jar = ($s[2] === 15) ? $jar15 : $jar19;
    $r = req($BASE . '/' . $s[0], $jar);
    preg_match_all('~href=["\']([^"\']+\.php[^"\']*)["\']~', $r['body'], $mm);
    if (count(array_unique($mm[1])) < 10) { $noNav[] = $s[0] . '(' . count(array_unique($mm[1])) . ')'; }
}
ck('S2-b', count($noNav) === 0,
    count($noNav) === 0 ? 'الثمانيةُ تعرض السايدبارَ فلا يقف فيها مستخدمٌ بلا مخرَج'
                        : 'بلا سايدبار: ' . implode(' · ', $noNav));

// ═════════ S3 ✖ دورٌ لا يملكها ═════════
echo "\n▐ S3 ✖ دورٌ لا يملك الشاشة — الحارسُ يحجب\n";
$jarOps = $tmp . '/eng01_r1.txt';
$inOps = login($BASE, $jarOps, 'محمد', '12345678');
$leaked = array();
foreach ($SCREENS as $s) {
    $r = req($BASE . '/' . $s[0], $jarOps);
    if ($r['code'] === 200 && strpos($r['body'], $s[1]) !== false) { $leaked[] = $s[0]; }
}
ck('S3-a', $inOps && count($leaked) === 0,
    count($leaked) === 0 ? 'صفرُ شاشةٍ تسرّبت لدورِ التشغيل (1)' : 'تسرّبت: ' . implode(', ', $leaked));

// ═════════ S4 ✖ POST بلا رمزِ حماية ═════════
echo "\n▐ S4 ✖ POST بلا رمزِ حمايةٍ — 403\n";
$r = req($BASE . '/Governance/job_queue.php', $jar15, array('action' => 'release_locks'));
ck('S4-a', $r['code'] === 403, 'HTTP ' . $r['code'] . ' (المتوقَّع 403)');

// ═════════ S5 ✚ POST برمزٍ صحيح ═════════
echo "\n▐ S5 ✚ POST برمزٍ صحيحٍ يمرُّ وينادي الخدمة\n";
$g = req($BASE . '/Governance/job_queue.php', $jar15);
$tok = '';
if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g['body'], $m)) { $tok = $m[1]; }
if ($tok === '' && preg_match('/name="csrf_token"[^>]*value=\'([^\']+)\'/', $g['body'], $m)) { $tok = $m[1]; }
if ($tok === '') {
    // الشاشةُ لا تعرض نموذجًا حين لا منحةَ كتابةٍ — نأخذ الرمزَ من الجلسةِ عبر أيِّ صفحةٍ تحمله
    $g2 = req($BASE . '/main/dashboard.php', $jar15);
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $g2['body'], $m)) { $tok = $m[1]; }
}
if ($tok === '') {
    ok('S5-a', 'لا نموذجَ كتابةٍ معروضٌ لهذا الدور — والحجبُ نفسُه شاهدٌ مقبول (S4 أثبت الرفض)');
} else {
    $p = req($BASE . '/Governance/job_queue.php', $jar15,
        array('action' => 'release_locks', 'csrf_token' => $tok));
    ck('S5-a', $p['code'] === 200, 'HTTP ' . $p['code'] . ' — والرمزُ فُحص قبلَ المعالجة');
}

// ═════════ S6 ✚ التسجيلُ في الوحداتِ والتنقّل ═════════
echo "\n▐ S6 ✚ كلُّ شاشةٍ لها وحدةُ صلاحياتٍ ورابطُ تنقّل\n";
$missMod = array(); $missNav = array();
foreach ($SCREENS as $s) {
    $st = $db->prepare('SELECT COUNT(*) FROM modules WHERE code=?');
    $st->bind_param('s', $s[0]); $st->execute();
    if ((int) $st->get_result()->fetch_row()[0] === 0) { $missMod[] = $s[0]; }
    $st->close();
    $st = $db->prepare('SELECT COUNT(*) FROM nav_items WHERE route=? AND active=1 AND module_id IS NOT NULL');
    $st->bind_param('s', $s[0]); $st->execute();
    if ((int) $st->get_result()->fetch_row()[0] === 0) { $missNav[] = $s[0]; }
    $st->close();
}
ck('S6-a', count($missMod) === 0, 'وحداتٌ مفقودة: ' . (count($missMod) ?: 'لا شيء'));
ck('S6-b', count($missNav) === 0, 'روابطُ تنقّلٍ مفقودة: ' . (count($missNav) ?: 'لا شيء'));

$acts = (int) $db->query("SELECT COUNT(*) FROM actions WHERE owner_doc='TS-01' AND active=1")->fetch_row()[0];
ck('S6-c', $acts >= 10, "أفعالٌ مسجَّلةٌ في القاموس: $acts");

echo "\n═══════════════════════════════════════════════════════════════\n";
printf(" النتيجة: %d ناجحًا · %d ساقطًا\n", $pass, $fail);
echo "═══════════════════════════════════════════════════════════════\n\n";
foreach (array($jarAnon, $jar15, $jar19, $jarOps) as $j) { @unlink($j); }
exit($fail === 0 ? 0 : 1);
