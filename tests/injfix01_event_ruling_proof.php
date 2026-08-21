<?php
/**
 * tests/injfix01_event_ruling_proof.php
 *   حكمُ نوعِ الحدث — INJ-FIX-01 · GAP-05
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبول**: «صفرُ نوعِ حدثٍ بلا حكم — وثلاثةُ مستهلكين لا تُثبت أن
 *   الناقلَ صار عمودَ التكامل».
 *
 * ◆ **والقياسُ صحَّح البطاقةَ في اتجاهَين معًا**:
 *   ① البطاقةُ تقول «اشتراكاتٌ كُتبت لمعماريةٍ **لم يُنفَّذ منتجوها**».
 *      والمقيس: **الأنواعُ الثمانيةُ والخمسون كلُّها منتَجةٌ فعلًا**، ولكلٍّ
 *      اشتراكٌ **نشطٌ** ومعالجُه **موجودٌ على القرص**. فالمنتِجون نُفِّذوا
 *      والمعالجون مكتوبون.
 *   ② **والعطبُ أعمقُ مما وُصف**: `ems_event_subscriptions` — سجلُّ الاشتراكاتِ
 *      كلِّه — **لا يقرؤه سطرُ إنتاجٍ واحد**. تقرؤه هجرتان وثلاثُ أدواتِ قياس،
 *      و`EventDispatcher` لا يعرفه أصلًا: يدور على ما سُجِّل بـ`register()` في
 *      `cron_events.php` (مستهلكان لا غير).
 *      ⇐ فليست الاشتراكاتُ «مكتوبةً لمعماريةٍ ناقصة» بل **سجلًّا كاملًا بلا
 *        قارئٍ في الإنتاج**. والفرقُ حاسمٌ: العلاجُ وصلُ قارئٍ لا كتابةُ معالجين.
 *
 * ◆ **ولا يخترع هذا الشاهدُ حكمًا**: يقيس الوقائعَ ويَعُدُّ ما لم يُحسم.
 *   والحكمُ (أعمالٌ أم تدقيق) قرارُ حوكمةٍ يُسجَّل في `gov_event_rulings.ruling`.
 *
 * التشغيل: php tests/injfix01_event_ruling_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/config.php';

$pass = 0; $fail = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }
function note($l, $v) { echo "  ◆ {$l}: {$v}\n"; }

echo "════ حكمُ نوعِ الحدث — GAP-05 ════\n";

/* ── ① السجلُّ قائمٌ ومقامُه مطابقٌ للمنتَج ─────────────────────────────── */
echo "\n── ① السجلُّ ومقامُه ──\n";
$q = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_event_rulings'");
ok($q && (int) $q->fetch_row()[0] === 1, 'سجلُّ الأحكامِ موجود', $pass, $fail);

$q = $conn->query("SELECT COUNT(DISTINCT `event_key`) FROM `ems_business_events`
                    WHERE `event_key` IS NOT NULL AND `event_key` <> ''");
$produced = $q ? (int) $q->fetch_row()[0] : -1;
$q = $conn->query("SELECT COUNT(*) FROM `gov_event_rulings`");
$registered = $q ? (int) $q->fetch_row()[0] : -1;
ok($produced === $registered && $produced > 0,
   'مقامُ السجلِّ = الأنواعُ المنتَجةُ فعلًا — لا نوعَ خارجَ السجل', $pass, $fail,
   "منتَج={$produced} · مسجَّل={$registered}");

/* ── ② الوقائعُ المقيسة ───────────────────────────────────────────────── */
echo "\n── ② الوقائعُ المقيسة ──\n";
foreach (array(
    'له اشتراكٌ مُعلَن'              => "`has_subscription` = 1",
    'اشتراكُه نشط'                   => "`subscription_active` = 1",
    'صنفُ معالجِه موجودٌ على القرص'  => "`handler_on_disk` = 1",
    'يظهر في الإسقاطِ الماليّ'       => "`in_projection` = 1",
) as $label => $cond) {
    $q = $conn->query("SELECT COUNT(*) FROM `gov_event_rulings` WHERE {$cond}");
    note($label, ($q ? $q->fetch_row()[0] : '?') . " من {$registered}");
}

/* ── ③ ◆ الجوهر: أيقرأ الإنتاجُ سجلَّ الاشتراكاتِ أصلًا؟ ─────────────────── */
echo "\n── ③ قارئُ سجلِّ الاشتراكاتِ في الإنتاج ──\n";
$SKIP = array('/storage/', '/vendor/', '/.git/', '/docs/', '/tests/', '/tools/',
              '/node_modules/', '/examples/', '/database/migrations/', '/database/seeds/');
$readers = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $p = str_replace('\\', '/', $f->getPathname());
    $bad = false;
    foreach ($SKIP as $s) { if (strpos($p, $s) !== false) { $bad = true; break; } }
    if ($bad) { continue; }
    $src = @file_get_contents($p);
    if ($src !== false && stripos($src, 'ems_event_subscriptions') !== false) {
        $readers[] = str_replace($ROOT . '/', '', $p);
    }
}
note('قرّاءُ `ems_event_subscriptions` في شجرةِ الإنتاج', count($readers)
     . (count($readers) ? ' — ' . implode(' · ', $readers) : ''));

/* ◆ **وهذا البندُ يُتوقَّع رسوبُه اليوم** — ورسوبُه هو الدليل: */
ok(count($readers) > 0,
   '**سجلُّ الاشتراكاتِ يقرؤه الإنتاج**', $pass, $fail,
   count($readers) ? 'نعم' : 'لا — ٩١ اشتراكًا نشطًا لا يُنفِّذها شيء');

/* ── ④ ما يُنفَّذ فعلًا — المستهلكون المسجَّلون بالكود ───────────────────── */
echo "\n── ④ المستهلكون المُنفَّذون فعلًا ──\n";
$cron = (string) @file_get_contents($ROOT . '/cron_events.php');
preg_match_all("/->register\(\s*(?:'([^']+)'|([A-Za-z\\\\]+::[A-Za-z_]+))/", $cron, $m);
$registeredConsumers = array_values(array_filter(array_merge($m[1], $m[2])));
note('مسجَّلون بـ`register()` في `cron_events.php`', count($registeredConsumers)
     . ' — ' . implode(' · ', $registeredConsumers));
ok(count($registeredConsumers) > 0, 'ثمَّ مستهلكون مسجَّلون بالكود', $pass, $fail);

/* ◆ «ثلاثةُ مستهلكين لا تُثبت أن الناقلَ صار عمودَ التكامل» — يُقاس المقام. */
$q = $conn->query("SELECT COUNT(*) FROM `gov_event_rulings` WHERE `in_projection` = 1");
$covered = $q ? (int) $q->fetch_row()[0] : 0;
note('تغطيةُ الإسقاطِ الماليّ', $covered . '/' . $registered
     . ' (' . ($registered ? round(100 * $covered / $registered, 1) : 0) . '٪)');
echo "     ⇐ والنسبةُ تُعلَن على مقامِها ولا تُجمع مع نسبةِ التسليم:\n";
echo "       ٩٩٫٩٪ تسليمًا مع صفرِ استهلاكٍ **رقمان لمشكلتَين** لا متوسطٌ واحد.\n";

/* ── ⑤ الحكمُ — صفرُ نوعٍ بلا حكم ─────────────────────────────────────── */
echo "\n── ⑤ الحكم ──\n";
$q = $conn->query("SELECT COUNT(*) FROM `gov_event_rulings` WHERE `ruling` IS NULL");
$undecided = $q ? (int) $q->fetch_row()[0] : -1;
ok($undecided === 0, '**صفرُ نوعِ حدثٍ بلا حكم**', $pass, $fail,
   "بلا حكم={$undecided} من {$registered}");
if ($undecided > 0) {
    echo "     ⇐ الحكمُ قرارُ حوكمةٍ لا استنتاجُ أداة: يُكتب في\n";
    echo "       `gov_event_rulings.ruling` إمّا `business` (بمستهلكٍ بأثرٍ مقيس)\n";
    echo "       وإمّا `audit` (معلَنٌ رسميًّا لا يحتاج مستهلكًا) — مع سببٍ مكتوب.\n";
}

echo "───────────────────────────────────────────────────────────────\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";
echo "◆ ورسوبُ هذا الشاهدِ اليومَ **قياسٌ لا عطبُ فاحص** — وهو ما يُغلقه\n";
echo "  قرارُ مجالِ الأحداثِ في الموجة ج.\n";
exit($fail === 0 ? 0 : 1);
