<?php
/**
 * tests/injfix01_projection_conformance_proof.php — INJ-FIX-01 · GAP-06
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيار**: «مطابقةٌ لقرارِ مجالِ الأحداثِ المعلَن». والقرارُ المعلَنُ في
 *   `gov_event_rulings`: **أحدَ عشرَ `business` وسبعةٌ وأربعون `audit`** —
 *   والنوعُ الذي يبلغ الإسقاطَ الماليَّ له مستهلكٌ بأثرٍ مقيسٍ ⇒ `business`،
 *   والذي لا يبلغه يُكتب في الجذرِ ولا يقرؤه أحدٌ ⇒ `audit`.
 *
 * ◆ **والمطابقةُ تُقاس حيًّا لا تُقرأ من عمودٍ محفوظ**: `in_projection` عمودٌ
 *   **مقيسٌ لحظةَ الحكم**، وإن لم يُعَد قياسُه صار ادعاءً. فيُعاد عدُّ الإسقاطِ
 *   من `fin_financial_events` في كلِّ تشغيل، ويُقارَن بالحكمِ المكتوب.
 *
 * ◆ **والانحرافُ في الاتجاهَين عيب**: نوعُ `audit` بلغ الإسقاطَ ⇒ حكمُه صار
 *   خطأً · ونوعُ `business` لم يبلغه ⇒ **إسقاطٌ منقطع**. وكلاهما يُرسِّب.
 *
 * التشغيل: php tests/injfix01_projection_conformance_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $prt = 3306;
if (strpos($h, ':') !== false) { list($h, $prt) = explode(':', $h); $prt = (int) $prt; }
$conn = new mysqli($h, ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER'),
    ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS'),
    ems_env('DB_NAME'), $prt);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$ok = 0; $bad = 0;
function chk($cond, $msg)
{
    global $ok, $bad;
    if ($cond) { $ok++; echo "  ✔ {$msg}\n"; } else { $bad++; echo "  ✘ {$msg}\n"; }
}

/* ══ ① القرارُ المعلَن ═════════════════════════════════════════════════════ */
echo "══ ① قرارُ مجالِ الأحداثِ المعلَن ══\n";
$by = array();
$q = $conn->query("SELECT `ruling`, COUNT(*) n FROM `gov_event_rulings` GROUP BY `ruling`");
$tot = 0;
while ($q && $x = $q->fetch_assoc()) { $by[$x['ruling']] = (int) $x['n']; $tot += (int) $x['n']; }
foreach ($by as $k => $v) { printf("     %-10s %d\n", $k, $v); }
chk($tot > 0, "أنواعٌ محكومة: {$tot}");
$noWhy = (int) $conn->query("SELECT COUNT(*) FROM `gov_event_rulings`
                              WHERE COALESCE(`reason`,'') = ''")->fetch_row()[0];
chk($noWhy === 0, "صفرُ حكمٍ بلا سبب — {$noWhy}");

/* ══ ② لا نوعَ منشورٌ بلا حكم ══════════════════════════════════════════════ */
echo "\n══ ② كلُّ نوعٍ منشورٍ له حكم ══\n";
$q = $conn->query("SELECT COUNT(*) FROM (
        SELECT DISTINCT e.`event_key` FROM `ems_business_events` e
         WHERE NOT EXISTS (SELECT 1 FROM `gov_event_rulings` g WHERE g.`event_key` = e.`event_key`)) t");
$unruled = $q ? (int) $q->fetch_row()[0] : -1;
chk($unruled === 0, "أنواعٌ منشورةٌ بلا حكم: {$unruled}");

/* ══ ③ المطابقةُ تُقاس حيًّا ═══════════════════════════════════════════════ */
echo "\n══ ③ المطابقةُ — يُعاد عدُّ الإسقاطِ لا يُقرأ من عمود ══\n";
$rows = array();
$q = $conn->query("SELECT `event_key`, `ruling`, `in_projection` FROM `gov_event_rulings`");
while ($q && $x = $q->fetch_assoc()) { $rows[] = $x; }

$stale = array(); $breach = array(); $bizLive = 0; $audLive = 0;
foreach ($rows as $r) {
    $k = $conn->real_escape_string($r['event_key']);
    /* ◆ **العمودُ `event_key` لا `event_type`**: الثاني يحمل **تصنيفًا** (revenue ·
     expense · enterprise · payroll) لا مفتاحَ النوع. والمطابقةُ عليه تُخرج صفرًا
     لكلِّ الأحدَ عشرَ فتبدو الإسقاطاتُ منقطعةً وهي سليمة. */
    $q2 = $conn->query("SELECT COUNT(*) FROM `fin_financial_events` WHERE `event_key` = '{$k}'");
    $live = $q2 ? (int) $q2->fetch_row()[0] : 0;
    $liveIn = ($live > 0) ? 1 : 0;

    /* ◆ العمودُ المحفوظُ يتقادم — يُقارَن بالحيِّ ويُبلَّغ عن الفرق */
    if ($liveIn !== (int) $r['in_projection']) {
        $stale[] = $r['event_key'] . " (محفوظ={$r['in_projection']} حيّ={$liveIn})";
    }
    if ($r['ruling'] === 'business') {
        if ($liveIn === 1) { $bizLive++; }
        else { $breach[] = "◆ business بلا إسقاطٍ حيّ: {$r['event_key']}"; }
    } elseif ($r['ruling'] === 'audit') {
        if ($liveIn === 0) { $audLive++; }
        else { $breach[] = "◆ audit بلغ الإسقاطَ: {$r['event_key']} ({$live})"; }
    }
}
printf("     business مطابقٌ حيًّا: %d من %d · audit مطابقٌ: %d من %d\n",
    $bizLive, $by['business'] ?? 0, $audLive, $by['audit'] ?? 0);
chk(count($breach) === 0, '**صفرُ انحرافٍ عن القرارِ المعلَن** — ' . count($breach)
    . (count($breach) ? ' — ' . implode(' · ', array_slice($breach, 0, 4)) : ''));
chk(count($stale) === 0, 'العمودُ المحفوظُ يطابق الحيَّ — لا حكمَ متقادم (' . count($stale) . ')'
    . (count($stale) ? ' — ' . implode(' · ', array_slice($stale, 0, 3)) : ''));

echo "\n  ◆ والانحرافُ في الاتجاهَين عيبٌ: `audit` بلغ الإسقاطَ ⇒ حكمُه خطأ ·\n";
echo "     و`business` لم يبلغه ⇒ **إسقاطٌ منقطع**. وكلاهما يُرسِّب هنا.\n";
echo "  ◆ والحكمُ يُنقض بدليل: `decided_at`/`decided_by` محفوظان — **فالتدقيقُ حكمٌ لا مقبرة**.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-06', true, 'الإسقاطُ يُقرأ من الجذرِ المحايدِ ems_business_events لا من إسقاطٍ آخر', $bad);

exit($bad === 0 ? 0 : 1);
