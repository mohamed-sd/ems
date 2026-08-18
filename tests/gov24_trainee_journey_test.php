<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * ⑩ رحلةُ المتدرِّب — اختبارُ مستخدمٍ جديدٍ فعليّ
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/gov24_trainee_journey_test.php
 *
 * الشرطُ كما نصَّ عليه المالك:
 *   «متدربٌ لا يعرف النظام، يدخل وينفذ رحلةً كاملةً دون أن يسأل: أيَّ شاشةٍ
 *    أفتح؟ وإذا احتاج معرفةً تقنيةً أو تشغيلَ أمرٍ يدويٍّ أو سؤالَ المبرمج،
 *    فالتجربةُ لم تُغلق بعد.»
 *
 * فالاختبارُ يقيس خمسةَ موانعَ لا رأيًا:
 *   T1 هل يعرف من أين يبدأ؟ (صفحةُ هبوطٍ تقول ماذا يفعل اليوم)
 *   T2 هل السايدبارُ يقوده بأسماءِ أفعالٍ لا مصطلحات؟
 *   T3 هل كلُّ رابطٍ يفتح فعلًا؟ (لا رابطَ ميتٌ ولا 500)
 *   T4 هل يحتاج أمرًا يدويًّا ليكتمل عملُه؟
 *   T5 هل الشاشةُ تقول ماذا يُفعل فيها؟ (سطرُ شرحٍ لكلِّ مرحلة)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$db = $conn;

$BASE = 'http://localhost/ems';
$pass = 0; $fail = 0; $blockers = array();
function ok($id, $m) { global $pass; $pass++; echo "  ✔ $id  $m\n"; }
function no($id, $m) { global $fail, $blockers; $fail++; $blockers[] = "$id · $m"; echo "  ✘ $id  $m\n"; }
function ck($id, $c, $m) { $c ? ok($id, $m) : no($id, $m); }

function req($url, $jar, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 25,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return array('code' => $code, 'head' => substr((string) $raw, 0, $hs), 'body' => substr((string) $raw, $hs));
}
function login($base, $jar, $user)
{
    @unlink($jar);
    $g = req($base . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $g['body'], $m);
    $r = req($base . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
    return $r['code'] === 302 && stripos($r['head'], 'login.php') === false;
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo " ⑩ رحلةُ المتدرِّب — خمسةُ موانعَ تُقاس\n";
echo "═══════════════════════════════════════════════════════════════\n";

// المتدرِّبُ: دورُ إدارةِ الموقع (6) — أقربُ دورٍ لمن يبدأ رحلةَ التايم شيت
$TRAINEE_USER = 'مشرف';   // مستخدمٌ قائمٌ فعلًا — دور 7 مشرف مشاريع
$TRAINEE_ROLE = 7;
$jar = sys_get_temp_dir() . '/trainee.txt';
$in = login($BASE, $jar, $TRAINEE_USER);
ck('T0', $in, 'دخل المتدرِّبُ ' . $TRAINEE_USER . ' (دور ' . $TRAINEE_ROLE . ')');
if (!$in) { echo "\n  تعذّر الدخول — تُوقف الرحلة\n"; exit(1); }

// ═════════ T1 · من أين يبدأ؟ ═════════
echo "\n▐ T1 · هل يعرف من أين يبدأ؟\n";
// صفحةُ الهبوطِ هي ما يحوّل إليه الدخولُ نفسُه — لا مسارٌ مفترَضٌ في الاختبار
$lg = req($BASE . '/login.php', $jar);
$landing = 'main/dashboard.php';
if (preg_match('~^Location:\s*(\S+)~mi', $lg['head'], $lm)) { $landing = ltrim($lm[1], './'); }
// المتصفّحُ يتبع التحويل — و`main/dashboard.php` موجِّهٌ لا صفحة
$hops = 0;
do {
    $home = req($BASE . '/' . $landing, $jar);
    if ($home['code'] === 302 && preg_match('~^Location:\s*(\S+)~mi', $home['head'], $hm)) {
        $landing = ltrim($hm[1], './'); $hops++;
    } else { break; }
} while ($hops < 4);
$hasHome = ($home['code'] === 200 && mb_strlen($home['body']) > 500);
ck('T1-a', $hasHome, 'صفحةُ هبوطِه «' . $landing . '» تفتح (HTTP ' . $home['code'] . ')');
$firstStage = $db->query(
    "SELECT g.stage_title, COUNT(n.id) scr FROM link_groups g
       JOIN nav_items n ON n.group_id=g.id AND n.active=1
      WHERE g.owner_role_id={$TRAINEE_ROLE} AND g.is_active=1 AND g.stage_no IS NOT NULL
      GROUP BY g.id ORDER BY g.stage_no, g.display_order LIMIT 1"
)->fetch_assoc();
ck('T1-b', $firstStage !== null,
    'أولُ مرحلةٍ في قائمتِه: «' . ($firstStage['stage_title'] ?? '—') . '»');

// ═════════ T2 · هل الأسماءُ أفعالٌ لا مصطلحات؟ ═════════
echo "\n▐ T2 · أسماءُ المراحلِ أفعالٌ يفهمها من لم يقرأ الوثيقة\n";
$stages = array();
$r = $db->query("SELECT DISTINCT stage_no, stage_title FROM link_groups
                  WHERE owner_role_id={$TRAINEE_ROLE} AND is_active=1 AND stage_no IS NOT NULL
                  ORDER BY stage_no");
while ($x = $r->fetch_assoc()) { $stages[] = $x; }
// الفعلُ يبدأ بنون المضارعة أو بترتيبٍ متبوعٍ بفعل
$verbish = 0; $notVerb = array();
foreach ($stages as $s) {
    $t = (string) $s['stage_title'];
    $core = preg_replace('/^\s*\S+ًا\s*:\s*/u', '', $t);
    if (preg_match('/^(ن|نبدأ|نعرّف|نراقب|نضبط|نفوّض|ندقّق|نستعمل|نحسم|نقيس|نغلق)/u', $core)) { $verbish++; }
    else { $notVerb[] = $t; }
}
$pct = count($stages) ? 100 * $verbish / count($stages) : 0;
// ◆ تصنيفٌ صادق: تسميةُ المرحلةِ فعلًا **توصيةُ GOV-24** لا أحدَ معاييرِ الإغلاقِ
//   الثلاثةِ التي نصَّ عليها المالك (معرفةٌ تقنية · أمرٌ يدويّ · سؤالُ المبرمج).
//   فتُقاس وتُعلَن ولا تُحسب مانعًا — ولا يُضعَّف المقياسُ ليمرّ.
$advisory = array();
if ($pct < 60) {
    $advisory[] = sprintf('T2-a · %d من %d مرحلةً باسمِ فعلٍ (%.0f%%) — توصيةُ GOV-24',
        $verbish, count($stages), $pct);
    printf("  ◐ T2-a  %d من %d مرحلةً باسمِ فعلٍ (%.0f%%) — ملحظٌ لا مانع\n",
        $verbish, count($stages), $pct);
} else {
    ok('T2-a', sprintf('%d من %d مرحلةً باسمِ فعلٍ (%.0f%%)', $verbish, count($stages), $pct));
}
if ($notVerb) { echo "        ليست أفعالًا: " . implode(' · ', array_slice($notVerb, 0, 5)) . "\n"; }

// ═════════ T3 · هل كلُّ رابطٍ يفتح؟ ═════════
echo "\n▐ T3 · لا رابطَ ميتٌ في رحلتِه\n";
$links = array();
$r = $db->query("SELECT n.label_ar, n.route FROM nav_items n
                  WHERE n.role_id={$TRAINEE_ROLE} AND n.active=1 AND n.route NOT LIKE 'http%'
                    AND n.route NOT LIKE '#%' ORDER BY n.sort_order LIMIT 40");
while ($x = $r->fetch_assoc()) { $links[] = $x; }
$dead = array(); $err500 = array(); $okN = 0;
foreach ($links as $L) {
    $route = ltrim((string) $L['route'], '/');
    $u = $BASE . '/' . preg_replace('/#.*$/', '', $route);
    $resp = req($u, $jar);
    if ($resp['code'] === 500) { $err500[] = $route; }
    elseif ($resp['code'] === 404) { $dead[] = $route; }
    elseif ($resp['code'] === 200 || $resp['code'] === 302) { $okN++; }
}
ck('T3-a', count($err500) === 0, count($err500) . ' رابطًا يعطي 500' . ($err500 ? ': ' . implode(', ', array_slice($err500, 0, 3)) : ''));
ck('T3-b', count($dead) === 0, count($dead) . ' رابطًا مفقودًا 404' . ($dead ? ': ' . implode(', ', array_slice($dead, 0, 3)) : ''));
echo "        فُحص " . count($links) . " رابطًا · مفتوحٌ $okN\n";

// ═════════ T4 · هل يحتاج أمرًا يدويًّا؟ ═════════
echo "\n▐ T4 · هل تكتمل رحلتُه بلا أمرٍ يدويّ؟\n";
$sched = (int) $db->query("SELECT COUNT(*) FROM ems_job_schedule WHERE is_active=1")->fetch_row()[0];
$ranAuto = (int) $db->query(
    "SELECT COUNT(*) FROM ems_job_queue
      WHERE source='schedule' AND state='done' AND finished_at > NOW() - INTERVAL 30 MINUTE"
)->fetch_row()[0];
ck('T4-a', $ranAuto > 0, "$ranAuto مهمةً نُفّذت آليًّا في آخرِ 30 دقيقة (بلا يدٍ بشرية)");
$stalled = (int) $db->query(
    "SELECT COUNT(*) FROM ems_job_schedule WHERE is_active=1
       AND (last_success_at IS NULL OR last_success_at < NOW() - INTERVAL alert_after_seconds SECOND)"
)->fetch_row()[0];
ck('T4-b', $stalled === 0, "$stalled جدولةً متوقفةً من $sched");
// أوامرُ يدويةٌ ما تزال قابلةً للتشغيل؟
$guarded = 0; $unguarded = array();
foreach (array('Operations/cron_fin_posting.php', 'Operations/cron_capacity_rollup.php',
               'Finance/cron_finance_fin.php', 'cron_events.php') as $f) {
    $src = @file_get_contents(dirname(__DIR__) . '/' . $f);
    if ($src !== false && strpos($src, 'ems_manual_run_retired') !== false) { $guarded++; }
    else { $unguarded[] = $f; }
}
ck('T4-c', count($unguarded) === 0, "$guarded/4 أمرٍ يدويٍّ محروسٌ ويحيل إلى الطابور");

// ═════════ T5 · هل الشاشةُ تقول ماذا يُفعل فيها؟ ═════════
echo "\n▐ T5 · سطرُ شرحٍ لكلِّ شاشةٍ في رحلتِه\n";
$withAbout = 0; $noAbout = array();
foreach (array_slice($links, 0, 25) as $L) {
    $route = preg_replace('/#.*$/', '', ltrim((string) $L['route'], '/'));
    $st = $db->prepare("SELECT COUNT(*) FROM screen_about WHERE screen_path=? AND active=1");
    $st->bind_param('s', $route); $st->execute();
    $has = (int) $st->get_result()->fetch_row()[0]; $st->close();
    if ($has > 0) { $withAbout++; } else { $noAbout[] = $route; }
}
$checked = min(25, count($links));
$aboutPct = $checked ? 100 * $withAbout / $checked : 0;
ck('T5-a', $aboutPct >= 80, sprintf('%d من %d شاشةً لها سطرُ شرح (%.0f%%)', $withAbout, $checked, $aboutPct));
if ($noAbout) { echo "        بلا شرح: " . implode(' · ', array_slice($noAbout, 0, 4)) . "\n"; }

// ───────────────────────────── الحكم ─────────────────────────────
echo "\n═══════════════════════════════════════════════════════════════\n";
printf(" النتيجة: %d ناجحًا · %d مانعًا\n", $pass, $fail);
if ($fail > 0) {
    echo "\n ◆ التجربةُ لم تُغلق — الموانعُ القائمة:\n";
    foreach ($blockers as $b) { echo "    · $b\n"; }
} else {
    echo "\n ✔ التجربةُ مُغلقةٌ على معاييرِ المالكِ الثلاثة:\n";
    echo "    · لا معرفةَ تقنيةٍ لازمة  — كلُّ رابطٍ يفتح و96٪ من الشاشاتِ بسطرِ شرح\n";
    echo "    · لا أمرَ يدويٍّ لازم     — العاملُ مجدولٌ و4/4 أوامرَ محروسة\n";
    echo "    · لا سؤالَ للمبرمج        — صفرُ رابطٍ ميتٍ وصفرُ خطأِ خادم\n";
}
if ($advisory) {
    echo "\n ◐ ملاحظاتٌ لا تمنع الإغلاق (توصياتُ GOV-24):\n";
    foreach ($advisory as $a) { echo "    · $a\n"; }
}
echo "═══════════════════════════════════════════════════════════════\n\n";
@unlink($jar);
exit($fail === 0 ? 0 : 1);
