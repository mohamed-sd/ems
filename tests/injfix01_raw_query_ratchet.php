<?php
/**
 * tests/injfix01_raw_query_ratchet.php
 *   سقّاطةُ الاستعلامِ الخامِّ على جداولِ المستأجِر — INJ-FIX-01 · GAP-29
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبول بنصِّه**: «خطةُ هجرةٍ مرقَّمةٌ للبوابةِ بأولويةِ جداولِ
 *   المستأجِر · **فحصٌ يمنع ملفًّا جديدًا باستعلامٍ خامٍّ** + تقدُّمٌ مقيس».
 *   ⇐ فالمطلوبُ **سقّاطةٌ ومقياسُ تقدُّمٍ**، لا تحويلُ ألفٍ ومئتَي ملفٍّ دفعةً.
 *     «والخطةُ وحدَها حالةُ عملٍ لا إغلاق» — فالسقّاطةُ هي ما يمنع الازدياد.
 *
 * ◆ **الخطر**: عزلُ المستأجِرِ في هذه الملفاتِ **بانضباطِ المطوِّرِ لا بالبوابة**.
 *   فمن ينسى `company_id` في استعلامٍ خامٍّ يفتح بيانَ كيانٍ لآخرَ بلا إنذار.
 *
 * ◆ **والسقّاطةُ تقيس العددَ ولا تسمح بازديادِه**: خطُّ الأساسِ مكتوبٌ هنا،
 *   وأيُّ ارتفاعٍ يُرسِّب باسمِ الملفِّ الجديد. والانخفاضُ **يُرسِّب أيضًا**
 *   ويطلب خفضَ الخطّ — فسقّاطةٌ لا تُشدّ تصير سقفًا يُنسى.
 *
 * التشغيل: php tests/injfix01_raw_query_ratchet.php
 *          php tests/injfix01_raw_query_ratchet.php --list
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/config.php';

$LIST = in_array('--list', $argv, true);

/**
 * خطُّ الأساسِ المقيسُ يومَ 2026-08-21 — يُخفَّض ولا يُرفع.
 *
 * ◆ **وتصحيحُ مقامٍ يخصُّ البطاقة**: البطاقةُ تقول «١٬٢٥٧ ملفًّا يمرّر استعلامًا
 *   خامًّا». والمقيسُ **على شجرةِ الإنتاجِ وحدَها 608** — والفارقُ أن ذلك العددَ
 *   يَعُدُّ `tests/` و`tools/` و`database/migrations/` و`seeds/` أسطحَ تسريب.
 *   **وأداةُ قياسٍ تقرأ جدولًا ليست سطحَ تسريب**، وهجرةٌ تُنفَّذ مرةً بحسابٍ
 *   إداريٍّ ليست مسارَ مستخدم. وعدُّها يُضخِّم المقامَ فيصير التقدُّمُ غيرَ
 *   مقروء: خفضُ خمسينَ ملفًّا من 1257 يبدو ٤٪ ومن 608 يبدو ٨٪.
 * ◆ ولا يُقرأ هذا تخفيفًا: **608 ملفَّ إنتاجٍ** يمسُّ جدولَ مستأجِرٍ بلا بوابةٍ
 *   عددٌ كبيرٌ بذاتِه — والعزلُ فيها بانضباطِ المطوِّرِ لا بالبوابة.
 */
$BASELINE = 608;

$okN = 0; $badN = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

echo "════ سقّاطةُ الاستعلامِ الخامّ — GAP-29 ════\n";

/* ── جداولُ المستأجِرِ تُقرأ من المخطَّطِ لا من قائمةٍ يدوية ──────────────── */
$tenant = array();
$q = $conn->query("SELECT TABLE_NAME FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'company_id'");
while ($q && $x = $q->fetch_row()) { $tenant[strtolower($x[0])] = true; }
echo "  جداولُ مستأجِرٍ في المخطَّط: " . count($tenant) . "\n";
ok(count($tenant) > 0, 'قُرئت جداولُ المستأجِرِ من المخطَّط', $okN, $badN);

/* ── المسحُ: ملفُّ إنتاجٍ يذكر جدولَ مستأجِرٍ في عبارةِ SQL خام ─────────────
   ◆ ويُستثنى ما ليس إنتاجًا — والأدواتُ والفاحصاتُ منها: ملفٌّ يقيس ليس
     ملفًّا يُسرِّب، وعدُّه يُضخِّم المقامَ فيصير التقدُّمُ غيرَ مقروء. */
$SKIP = array('/storage/', '/vendor/', '/.git/', '/docs/', '/node_modules/',
              '/examples/', '/tests/', '/tools/', '/database/migrations/', '/database/seeds/');
$rx = '/\b(?:FROM|JOIN|UPDATE|INSERT\s+INTO|DELETE\s+FROM)\s+`?([a-z_][a-z0-9_]*)`?/i';

$scanned = 0; $hits = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') { continue; }
    $p = str_replace('\\', '/', $f->getPathname());
    $bad = false;
    foreach ($SKIP as $s) { if (strpos($p, $s) !== false) { $bad = true; break; } }
    if ($bad) { continue; }
    $scanned++;
    $src = @file_get_contents($p);
    if ($src === false) { continue; }
    if (!preg_match_all($rx, $src, $m)) { continue; }
    foreach ($m[1] as $t) {
        if (isset($tenant[strtolower($t)])) { $hits[str_replace($ROOT . '/', '', $p)] = true; break; }
    }
}
ksort($hits);
$now = count($hits);

echo "\n── المقياس ──\n";
echo "  ملفاتُ إنتاجٍ مسحت: {$scanned}\n";
echo "  **تلمس جدولَ مستأجِرٍ باستعلامٍ خام: {$now}** · خطُّ الأساس: {$BASELINE}\n";

if ($LIST) {
    $byDir = array();
    foreach (array_keys($hits) as $r) {
        $d = strpos($r, '/') !== false ? substr($r, 0, strpos($r, '/')) : '(جذر)';
        $byDir[$d] = isset($byDir[$d]) ? $byDir[$d] + 1 : 1;
    }
    arsort($byDir);
    echo "\n  ── التوزيعُ بالمجلد (أولويةُ الهجرة) ──\n";
    $i = 0;
    foreach ($byDir as $d => $n) { printf("     %-24s %s\n", $d, $n); if (++$i >= 14) { break; } }
}

/* ── الحكم ─────────────────────────────────────────────────────────────── */
echo "\n── الحكم ──\n";
ok($now <= $BASELINE,
   '**لا ملفَّ جديدًا باستعلامٍ خامٍّ على جدولِ مستأجِر**', $okN, $badN,
   $now <= $BASELINE ? "{$now} ≤ {$BASELINE}" : 'ارتفع ' . ($now - $BASELINE) . ' ملفًّا فوقَ الأساس');

ok($now >= $BASELINE,
   'وخطُّ الأساسِ ما يزال مطابقًا — والانخفاضُ يطلب شدَّ السقّاطة', $okN, $badN,
   $now < $BASELINE ? "انخفض إلى {$now}: **اخفِض \$BASELINE إلى {$now}**" : 'مطابق');

echo "───────────────────────────────────────────────────────────────\n";
echo ($badN === 0 ? "✔" : "✘") . " النتيجة: نجح {$okN} · رسب {$badN}\n";
echo "◆ والسقّاطةُ لا تُغلق GAP-29 — تمنع ازديادَه وتقيس تقدُّمَه.\n";
echo "  والإغلاقُ بسجلِّ استثناءاتٍ معتمَدٍ لكلِّ ما بقي، بسببٍ ومالكٍ وتاريخِ مراجعة.\n";

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-29', false, 'سقّاطةٌ تمنع الازديادَ ولا تُغلق — ستُّمئةٍ وثمانيةُ ملفاتٍ باستعلامٍ خامٍّ على جدولِ مستأجِر', $badN);

exit($badN === 0 ? 0 : 1);
