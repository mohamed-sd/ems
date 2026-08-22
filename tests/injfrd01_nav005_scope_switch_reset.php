<?php
/**
 * tests/injfrd01_nav005_scope_switch_reset.php
 *   شاهدُ FR-NAV-005 — تبديلُ المساحةِ يُعيد نطاقَ ثمانيةٍ لا السايدبارِ وحدَه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: «تبديلُ المساحةِ يُعيد نطاقَ **ثمانيةٍ** لا السايدبارِ
 *   وحدَه — **ومنها المناظرُ المحفوظة**» · ومعيارُ القبول «**صفرُ مكوِّنٍ يقرأ
 *   نطاقَه مرةً واحدة**» · وسالبُه «منظرٌ محفوظٌ من مساحةٍ سابقةٍ ← **صفرُ صفٍّ
 *   بعدَ التبديل**».
 *
 * ◆ **والعطبُ الذي يمنعه**: مكوِّنٌ يقرأ نطاقَه **مرّةً عندَ أوّلِ طلبٍ ويحتفظ
 *   به** — فيبقى يعرض بياناتِ المساحةِ القديمةِ بعدَ التبديل. **والسايدبارُ
 *   وحدَه يتغيّر فيبدو التبديلُ ناجحًا وهو ناقصٌ سبعةَ أثمان.**
 *
 * ◆ **وثلاثةُ أوجهٍ تُقاس**:
 *   ① **الثمانيةُ مسمّاةٌ ومُبطَلةٌ** في دالّةِ التبديلِ نفسِها — نصًّا لا ثقةً.
 *   ② **عدّادُ الحقبةِ يتقدّم** (`scope_epoch`) — فمن أراد كشفَ التقادمِ وجد
 *     علامةً، ولا يعتمد على مسحِ المفاتيحِ وحدَه.
 *   ③ **والذاكرةُ الساكنةُ مفهرسةٌ بالمساحةِ لا عامّة** — وهذا هو الموضعُ الذي
 *     ينجو من مسحِ الجلسة. يُقاس **وظيفيًّا**: نداءان بمساحتَين مختلفتَين
 *     يُخرجان مجموعتَين مختلفتَين.
 *
 * التشغيل: php tests/injfrd01_nav005_scope_switch_reset.php
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
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$GLOBALS['conn'] = $conn;
if (!isset($_SESSION)) { $_SESSION = array(); }

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function n(mysqli $d, $q) { $r = @$d->query($q); return $r ? (int) $r->fetch_row()[0] : -1; }

echo "══ FR-NAV-005 — التبديلُ يُعيد نطاقَ ثمانية ══\n";

/* ── ① الثمانيةُ مسمّاةٌ ومُبطَلةٌ — نصًّا ───────────────────────────────── */
$src = (string) @file_get_contents($ROOT . '/includes/space_scope.php');
$EIGHT = array('nav_cache', 'board_cache', 'search_scope', 'saved_views',
               'default_filters', 'work_queues', 'restricted_views', 'last_page');
$missing = array();
foreach ($EIGHT as $k) { if (strpos($src, "'{$k}'") === false) { $missing[] = $k; } }
chk(empty($missing), '**الثمانيةُ مسمّاةٌ في دالّةِ التبديل**',
    empty($missing) ? implode(' · ', array_slice($EIGHT, 0, 4)) . ' …' : 'ناقصٌ: ' . implode(' · ', $missing));
chk(strpos($src, 'unset($_SESSION[$k])') !== false,
    'و**تُبطَل فعلًا** لا تُذكر فقط', 'unset على المفاتيحِ الثمانية');
chk(strpos($src, 'saved_views') !== false,
    'و**المناظرُ المحفوظةُ منها** — وهي المذكورةُ نصًّا في المطلب');

/* ── ② عدّادُ الحقبةِ يتقدّم ────────────────────────────────────────────── */
chk(strpos($src, 'scope_epoch') !== false,
    '**وعدّادُ الحقبةِ يتقدّم** — فمن أراد كشفَ التقادمِ وجد علامة', 'scope_epoch');

require_once $ROOT . '/includes/space_scope.php';
$_SESSION['scope_epoch'] = 5;
$_SESSION['saved_views'] = array('من مساحةٍ سابقة');
$_SESSION['user'] = array('role' => 4, 'id' => 40);
$home = ems_scope_home(4);
chk($home !== '', 'ومساحةُ الدورِ تُقرأ من الربطِ المقيس', "الدور 4 ⇒ «{$home}»");

$switched = ems_scope_switch($home);
chk($switched === true, 'والتبديلُ إلى مساحةٍ مسموحةٍ **ينجح**', $home);
chk(!isset($_SESSION['saved_views']),
    'FR-NAV-005 سالب · **المنظرُ المحفوظُ من مساحةٍ سابقةٍ زال بعدَ التبديل**',
    isset($_SESSION['saved_views']) ? '**باقٍ ✘**' : 'صفرُ صفٍّ ✔');
chk((int) $_SESSION['scope_epoch'] === 6,
    'و**الحقبةُ تقدَّمت** 5 ⇒ ' . (int) $_SESSION['scope_epoch']);

$denied = ems_scope_switch('مساحةٌ لا يملكها الدور');
chk($denied === false, 'و**التبديلُ إلى مساحةٍ غيرِ مسموحةٍ يُردّ** — فالبابُ ليس مفتوحًا');

/* ── ③ الذاكرةُ الساكنةُ مفهرسةٌ بالمساحةِ — يُقاس وظيفيًّا ───────────────── */
echo "\n── ③ الذاكرةُ الساكنة: أتنجو من التبديل؟ ──\n";
$two = array();
$r = $conn->query("SELECT `space_ar`, COUNT(*) c FROM `gov_space_appearances`
                    WHERE `cls` = 'FORBIDDEN' GROUP BY `space_ar`
                    HAVING c > 0 ORDER BY c DESC LIMIT 2");
while ($r && $x = $r->fetch_row()) { $two[] = $x[0]; }
if (count($two) < 2) {
    chk(false, '**مقامٌ صفريّ** — لا مساحتَين بممنوعاتٍ تُقاس عليهما الذاكرة');
} else {
    $a = ems_scope_forbidden_set($two[0]);
    $b = ems_scope_forbidden_set($two[1]);
    printf("  «%s»=%d ممنوعًا · «%s»=%d ممنوعًا\n", $two[0], count($a), $two[1], count($b));
    chk($a !== $b,
        '**الذاكرةُ مفهرسةٌ بالمساحةِ لا عامّة** — نداءان بمساحتَين يُخرجان مجموعتَين',
        $a !== $b ? 'مختلفتان ✔' : '**متطابقتان ✘ — الذاكرةُ تنجو من التبديل**');
    /* والنداءُ الثاني للأولى يعود كما كان — فالفهرسةُ صحيحةٌ لا عشوائية */
    $a2 = ems_scope_forbidden_set($two[0]);
    chk($a === $a2, 'وإعادةُ النداءِ للمساحةِ نفسِها **تعود كما هي** — فهرسةٌ لا عشوائية');
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
