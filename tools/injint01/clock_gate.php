<?php
/**
 * tools/injint01/clock_gate.php — حاجبُ سلامةِ الساعة (‏INJ-INT-01 §6.2)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأمرُ افترض انزياحًا قائمًا؛ والقياسُ يقول إنَّه مُصلَح.** فالحاجبُ هنا
 *   ليس لإصلاحِ شيءٍ بل **لمنعِ ارتدادِه صامتًا** — والارتدادُ وقع قبلًا
 *   (‏ساعةُ الجهازِ رجعت 11.4 ساعةً فانقلب حاجبُ «غيرِ متقادم»).
 *
 * ⛔ **ولا يُقاس الانزياحُ بختمِ صفٍّ مستقبليّ**: 2,224 صفًّا في هذا المخزنِ
 *   `occurred_at` منها في 2087 و2088 — وهي **جداولُ إهلاكٍ وأقساطٍ مُسقَطةٌ
 *   عمدًا** لا خللُ ساعة. والدليلُ أنَّ `created_at` المستقبليَّ **صفرٌ**:
 *   وقتُ الكتابةِ سليمٌ، والمستقبليُّ حقلُ أعمالٍ لا حقلُ تسجيل.
 *   ⇐ فالحاجبُ يقيس `created_at` وحدَه، ويعرض `occurred_at` خبرًا لا حكمًا.
 *
 * ◆ **ثلاثةُ مصادرَ تُقارَن في UTC**: نظامُ التشغيل · PHP · MariaDB.
 *   والمنطقةُ لا تُقارَن — `DB.tz=SYSTEM` و`PHP.tz=UTC` سليمانِ ما دام
 *   الوقتُ المطلقُ واحدًا.
 *
 * التشغيل: php tools/injint01/clock_gate.php [--tolerance=5]
 * الخروج : 0 مطابقٌ · 1 خارجَ السماحيّة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$TOL = 5;
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--tolerance=(\d+)$/', $a, $m)) { $TOL = (int) $m[1]; }
}

$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4');
$one = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };

/* ═══ ① الأزمنةُ الثلاثةُ في UTC ═════════════════════════════════════════ */
$osUtc  = gmdate('Y-m-d H:i:s');
$phpUtc = gmdate('Y-m-d H:i:s');
$dbUtc  = $one('SELECT UTC_TIMESTAMP()');

$dOsDb  = abs(strtotime($osUtc)  - strtotime($dbUtc));
$dPhpDb = abs(strtotime($phpUtc) - strtotime($dbUtc));

echo "══ سلامةُ الساعةِ — INJ-INT-01 §6.2 ══\n";
printf("  نظامُ التشغيل (UTC) : %s\n", $osUtc);
printf("  PHP           (UTC) : %s   [tz=%s]\n", $phpUtc, date_default_timezone_get());
printf("  MariaDB       (UTC) : %s   [tz=%s]\n", $dbUtc, $one('SELECT @@session.time_zone'));
printf("  |OS − DB|  = %d ثانية   (السماحيّة %d)\n", $dOsDb, $TOL);
printf("  |PHP − DB| = %d ثانية   (السماحيّة %d)\n", $dPhpDb, $TOL);

/* ═══ ② ختومُ الكتابةِ — وهي وحدَها ما يحكم ═════════════════════════════
   ⛔ **والأساسُ الزمنيُّ للتطبيقِ محلّيٌّ لا عالميّ**: `NOW()` = UTC+3 هنا،
      والتطبيقُ يكتب `created_at` بها. فمقارنتُها بـ`UTC_TIMESTAMP()` تَعُدُّ
      **كلَّ صفٍّ كُتب في الساعاتِ الثلاثِ الأخيرةِ «مستقبليًّا»** — وهو
      انحرافُ مقياسٍ لا انحرافُ ساعة. وهذا الحاجبُ أخرج صفرًا صدفةً في
      تشغيلةٍ لم يُكتب فيها شيءٌ حديثًا، ثمَّ أخرج واحدًا حين كُتب.
      ⇐ فيُقاس كلُّ عمودٍ **بالأساسِ الذي كُتب به**. */
$dbLocal = $one('SELECT NOW()');
$offset  = (int) round((strtotime($dbLocal) - strtotime($dbUtc)) / 60);
printf("\n  إزاحةُ أساسِ التطبيقِ عن UTC: %+d دقيقة (NOW=%s)\n", $offset, $dbLocal);
$futureCreated  = (int) $one('SELECT COUNT(*) FROM ems_business_events WHERE created_at  > NOW()');
$futureOccurred = (int) $one('SELECT COUNT(*) FROM ems_business_events WHERE occurred_at > NOW()');
echo "  ختمُ التسجيلِ (created_at) مستقبليٌّ بأساسِه : $futureCreated   ⇐ الحاكم\n";
printf("  ختمُ الواقعةِ (occurred_at) مستقبليّ        : %d   ⇐ خبرٌ لا حكم (جداولُ إهلاكٍ وأقساط)\n", $futureOccurred);

/* ═══ ③ الحكم ══════════════════════════════════════════════════════════ */
$fail = array();
if ($dOsDb  > $TOL) { $fail[] = "انزياحُ OS↔DB = {$dOsDb}s"; }
if ($dPhpDb > $TOL) { $fail[] = "انزياحُ PHP↔DB = {$dPhpDb}s"; }
if ($futureCreated > 0) { $fail[] = "$futureCreated صفًّا بختمِ تسجيلٍ مستقبليّ"; }

echo "\n";
if ($fail) {
    echo "⛔ CLOCK_INTEGRITY = FAIL\n";
    foreach ($fail as $f) { echo "   · $f\n"; }
    exit(1);
}
echo "✔ CLOCK_INTEGRITY = PASS — المصادرُ الثلاثةُ متطابقةٌ وختمُ التسجيلِ نظيف.\n";
exit(0);
