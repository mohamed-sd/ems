<?php
/**
 * tests/injfrd01_evt003_orphan_no_alarm.php
 *   شاهدُ FR-EVT-003 — يُنذَر للمتعثر · **ولا يُنذَر لليتيمِ المحكوم**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بشطرَيه**: «كاشفُ توقفِ المستهلكِ موصولٌ بالمجدولِ **ويُنذر** —
 *   **ولا يُنذر لمستهلكٍ بلا معالج**». والشطرُ الأولُ كان محقَّقًا والثاني
 *   مخروقًا: 42 من 252 إنذارًا (16.7٪) عن يتيمَين لا يتقدّمان أبدًا.
 *
 * ◆ **والإعفاءُ بقيدٍ مكتوبٍ لا بغيابِ سجل**: اليتيمُ يُعفى من الإنذارِ الدوريِّ
 *   **إن كان له حكمٌ** في `gov_orphan_consumer_rulings` بمالكِه وسببِه.
 *   واليتيمُ بلا حكمٍ **ما يزال يُنذَر عنه** — فالسكوتُ بلا قيدٍ هو العطبُ
 *   نفسُه بوجهٍ آخر. وهذا هو ما يقيسه هذا الشاهد.
 *
 * ◆ **ولا يُعبَث بالطوابعِ الزمنيةِ لقياس**: أوّلُ حزامٍ كتبتُه أزاح
 *   `created_at` ساعتَين ليتجاوز كتمَ الساعة — **فأفسد أثرًا تدقيقيًّا حيًّا**
 *   (صفّان، رُدّا بدقّة). والصوابُ: يُقاس **قرارُ التصفيةِ نفسُه** لا مخرَجُ
 *   الإنذار، ويُستعمل مستهلكٌ اصطناعيٌّ يُكنَس.
 *
 * التشغيل: php tests/injfrd01_evt003_orphan_no_alarm.php [--negative]
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
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$db = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($db->connect_errno) { exit("تعذّر الاتصال: {$db->connect_error}\n"); }
$db->set_charset('utf8mb4');
$GLOBALS['conn'] = $db;

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

$neg  = in_array('--negative', $argv, true);
$MARK = 'evt003_belt_orphan';

echo "══ FR-EVT-003 — يُنذَر للمتعثر ولا يُنذَر لليتيمِ المحكوم ══\n";

/* ── ① الكاشفُ موصولٌ بالمجدولِ ويعمل حيًّا — أثرٌ لا ادّعاء ─────────────── */
$alerts = n($db, "SELECT COUNT(*) FROM `fin_notifications` WHERE `title` LIKE '[%STALL:%'");
$recent = n($db, "SELECT COUNT(*) FROM `fin_notifications`
                   WHERE `title` LIKE '[%STALL:%' AND `created_at` > NOW() - INTERVAL 24 HOUR");
chk($alerts > 0 && $recent > 0,
    'الكاشفُ **موصولٌ بالمجدولِ ويُنذر فعلًا** — أثرٌ مكتوبٌ لا ادّعاء',
    "إنذاراتٌ إجمالًا={$alerts} · في آخرِ 24 ساعة={$recent}");

$wired = 0;
foreach (array('/cron_events.php' => 'alertStalledConsumers',
               '/cron_jobs.php'   => 'alertStalled') as $file => $call) {
    $src = (string) @file_get_contents($ROOT . $file);
    if (strpos($src, $call . '(') !== false) { $wired++; }
}
chk($wired === 2, 'ونداؤُه من عاملَي المجدولِ كليهما', "موصولٌ في {$wired} من 2");

/* ── ② لكلِّ يتيمٍ معفًى حكمٌ بمالكِه وسببِه ─────────────────────────────── */
$hasTbl = n($db, "SELECT COUNT(*) FROM information_schema.TABLES
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_orphan_consumer_rulings'");
chk($hasTbl === 1, 'سجلُّ أحكامِ اليتامى موجود');
$ruled = n($db, "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings`");
$thin  = n($db, "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings`
                  WHERE TRIM(`reason`) = '' OR TRIM(`owner`) = '' OR TRIM(`evidence`) = ''");
chk($thin === 0, 'ولكلِّ حكمٍ **مالكٌ وسببٌ ودليل**', "محكومون={$ruled} · ناقصٌ={$thin}");

/* ── ③ قرارُ التصفيةِ يُقاس مباشرةً — لا عبثَ بالطوابع ────────────────────── */
require_once $ROOT . '/app/Core/EventDispatcher.php';
$d = new \App\Core\EventDispatcher($db);
$ref = new \ReflectionClass($d);
$m = $ref->getMethod('orphanIsRuled');
$m->setAccessible(true);

$ruledKeys = array();
$r = $db->query("SELECT `consumer_key` FROM `gov_orphan_consumer_rulings`");
while ($r && $x = $r->fetch_row()) { $ruledKeys[] = $x[0]; }

if (empty($ruledKeys)) {
    chk(false, '**مقامٌ صفرٌ ليس نجاحًا** — لا يتيمَ محكومًا فلا شيءَ يُقاس');
} else {
    $allRuled = true;
    foreach ($ruledKeys as $k) { if (!$m->invoke($d, $k)) { $allRuled = false; } }
    chk($allRuled, 'FR-EVT-003 · **اليتيمُ المحكومُ يُستثنى من الإنذارِ الدوريّ**',
        count($ruledKeys) . ' محكومًا: ' . implode(' · ', $ruledKeys));
}

/* ── ④ **واليتيمُ بلا حكمٍ ما يزال يُنذَر عنه** — الإعفاءُ بقيدٍ لا بغياب ─── */
$unruledSeen = $m->invoke($d, '__no_such_consumer_' . getmypid());
chk($unruledSeen === false,
    'ويتيمٌ **بلا حكمٍ لا يُعفى** — فالإعفاءُ بقيدٍ مكتوبٍ لا بغيابِ سجل',
    'مستهلكٌ مجهولٌ ⇒ ' . ($unruledSeen ? 'أُعفي ✘' : 'يُنذَر عنه ✔'));

if ($neg) {
    /* ◆ الحزامُ يقيّد حكمًا لمستهلكٍ اصطناعيٍّ ثم يكنسه — ويُثبت قيدَه أوّلًا */
    $st = $db->prepare("INSERT INTO `gov_orphan_consumer_rulings`
        (`consumer_key`,`event_codes`,`ruling`,`owner`,`reason`,`evidence`,`ruled_at`)
        VALUES (?, 'belt', 'NO_HANDLER_ON_DISK', 'حزام', 'حزامٌ سالب', 'belt', NOW())");
    $st->bind_param('s', $MARK);
    if (!$st->execute()) { exit("  ⛔ **رُفض دسُّ الحزام** — " . $st->error . "\n"); }
    $st->close();
    $planted = n($db, "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings`
                        WHERE `consumer_key` = '{$MARK}'");
    if ($planted !== 1) { echo "  ⛔ **لم يُدَسَّ شيء** — أُوقِف\n"; exit(1); }
    echo "  ◆ دُسَّ حكمٌ اصطناعيّ — **ووجودُه مُثبَتٌ قبلَ القياس**\n";
    $now = $m->invoke($d, $MARK);
    chk($now === true, 'والحكمُ المدسوسُ **يُعفي فورًا** — فالتصفيةُ تقرأ السجلَّ حيًّا',
        $now ? 'أُعفي ✔' : 'لم يُعفَ ✘ — التصفيةُ لا تقرأ');
    $db->query("DELETE FROM `gov_orphan_consumer_rulings` WHERE `consumer_key` = '{$MARK}'");
    $left = n($db, "SELECT COUNT(*) FROM `gov_orphan_consumer_rulings` WHERE `consumer_key` = '{$MARK}'");
    chk($left === 0, 'وكُنس الحزامُ أثرَه', "المتبقي: {$left}");
    $after = $m->invoke($d, $MARK);
    chk($after === false, '**وبزوالِ الحكمِ يعود الإنذار** — فالإعفاءُ لا يعلق',
        $after ? 'ما زال معفًى ✘' : 'عاد يُنذَر عنه ✔');
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
