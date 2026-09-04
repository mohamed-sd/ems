<?php
/**
 * tools/injint01/asbuilt_drift.php — قياسُ تقادمِ وثيقةِ المبنيّ (EXE-01 §8·1)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ `STALE_SECTION_COUNT = 0` يحتاج مقامًا قبلَ بسطِه**: لا يُقال
 *   «الوثيقةُ متقادمة» بلا عدِّ ما تقادم. فتُعلَن ادّعاءاتُها الرقميّةُ صريحةً
 *   وتُقاس حيًّا، ويُطبع الفرقُ ادّعاءً ادّعاءً.
 *
 * ⛔ **ولا يُصحَّح رقمٌ بلا استعلامِه**: لكلِّ ادّعاءٍ هنا **استعلامُه بجانبِه** —
 *   فما لا يُقاس يُوسَم `UNMEASURABLE` ولا يُخمَّن. (‏§9: «لا رقم في تقريرٍ بلا
 *   استعلامِه وجدولِه ولقطتِه ونتيجتِه».)
 *
 * ⛔ **ونموُّ عددٍ ليس تقادمَ حكم**: جدولٌ زاد صفًّا رقمٌ يُحدَّث؛ أمّا
 *   «23 موصولة» و«11 كلُّها dlq» فهما **مقامانِ خاطئان** لا أرقامٌ نمت —
 *   والفرقُ بينهما هو الفرقُ بين تحديثِ وثيقةٍ وتصحيحِ حكم.
 *
 * التشغيل: php tools/injint01/asbuilt_drift.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4'); $DB = ems_env('DB_NAME');
$one = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };

$DOC = $ROOT . '/docs/baseline_20260821/INJ-ARCH-ASBUILT_ar.md';
$src = (string) @file_get_contents($DOC);
if ($src === '') { exit("⛔ الوثيقةُ غيرُ مقروءة\n"); }
$head = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD 2>&1'));
printf("◆ الوثيقةُ تُعلن لقطتَها: %s\n", preg_match('~\*\*اللقطة:\*\*\s*`([^`]+)`~u', $src, $m) ? $m[1] : '—');
printf("◆ الرأسُ الحاليّ        : %s\n\n", $head);

/* ═══ الادّعاءاتُ — تُنتزَع من الوثيقةِ ولا تُثبَّت في الفاحص ═════════════════
   ⛔ **وإلا تقادم الفاحصُ كما تقادمت الوثيقة**: نسخةٌ أولى من هذا المِسبارِ
      كتبت أرقامَ الوثيقةِ داخلَه، فلمّا صُحِّحت الوثيقةُ بقي يقول «متقادم» —
      **يقيس ذاكرتَه لا المكتوب**. ⇐ لكلِّ ادّعاءٍ **نمطُ انتزاعٍ** من النصِّ
      الحيِّ، وما لم يُنتزَع يُوسَم `NOT_IN_DOC` ولا يُفترَض له رقم. */
$pick = function ($re) use ($src) {
    if (!preg_match($re, $src, $m)) { return null; }
    return (int) str_replace(array(',', '٬'), '', $m[1]);
};
$CLAIMS = array(
 array('sec'=>'§0','what'=>'عددُ الجداول','re'=>'~جداول\s+[\d,]+\s*⇒\s*\*\*([\d,]+)\*\*~u',
       'q'=>"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='BASE TABLE'"),
 array('sec'=>'§0','what'=>'modules','re'=>'~`modules`\s+[\d,]+\s*⇒\s*\*\*([\d,]+)\*\*~u','q'=>'SELECT COUNT(*) FROM modules'),
 array('sec'=>'§0','what'=>'gov_field_class','re'=>'~`gov_field_class`\s+\*\*([\d,]+)\*\*~u','q'=>'SELECT COUNT(*) FROM gov_field_class'),
 array('sec'=>'§0','what'=>'أحداثُ الأعمال','re'=>'~الأحداث\s+[\d,]+\s*⇒\s*\*\*([\d,]+)\*\*~u','q'=>'SELECT COUNT(*) FROM ems_business_events'),
 array('sec'=>'§0','what'=>'مفاتيحُ الأحداث','re'=>'~مفاتيحُ الأحداثِ\s+\*\*([\d,]+)\*\*~u','q'=>'SELECT COUNT(DISTINCT event_key) FROM ems_business_events'),
 array('sec'=>'§0','what'=>'القيدُ (رأسٌ/سطر)','re'=>'~القيود\s+\*\*([\d,]+)/[\d,]+\*\*~u','q'=>'SELECT COUNT(*) FROM fin_journal_entries'),
 array('sec'=>'§0','what'=>'nav_placements/targets','re'=>'~`nav_placements`/`nav_targets`\s+[\d,]+\s*⇒\s*\*\*([\d,]+)\*\*~u','q'=>'SELECT COUNT(*) FROM nav_placements'),
 array('sec'=>'§6','what'=>'بنودُ الدور 8','re'=>'~بنودُ ملاحةٍ نشِطةٌ للدور 8 \| \*\*([\d,]+)\*\*~u','q'=>'SELECT COUNT(*) FROM nav_items WHERE role_id=8 AND active=1'),
);
foreach ($CLAIMS as $i => $x) { $CLAIMS[$i]['doc'] = $pick($x['re']); }
/* ═══ المقاماتُ الثلاثةُ — يُتحقَّق من **بقاءِ التصحيح** لا يُعادُ الاشتكاء ═════
   ◆ **والفرقُ بين تحديثِ رقمٍ وتصحيحِ مقامٍ هو مدارُ هذا القسم**: الرقمُ يُحدَّث
     بإعادةِ القياس؛ والمقامُ الخاطئُ **يبقى خاطئًا مهما أُعيد قياسُه**، لأنَّ
     العطبَ في سؤالِه لا في جوابِه. فيُفحَص بوجودِ نصِّ التصحيحِ في الوثيقة. */
$FRAMING = array(
 array('sec'=>'§8','need'=>'58 / 58 لها مستهلكٌ نشِط','re'=>'~\*\*58 / 58\*\*~u',
       'q'=>"SELECT COUNT(DISTINCT b.event_key) FROM ems_business_events b
               JOIN event_consumers ec ON ec.event_name=b.event_key AND ec.active=1",
       'why'=>'بدل «23 / 58» — الـ23 مفاتيحُ EffectLinkConsumer وحدَه'),
 array('sec'=>'§8','need'=>'37 dlq بثلاثِ عائلات','re'=>'~\*\*37 `dlq`\*\*~u',
       'q'=>"SELECT COUNT(*) FROM ems_event_deliveries WHERE state='dlq'",
       'why'=>'بدل «11 كلُّها dlq» — و25 منها قرارٌ مكتوبٌ لا عطب'),
 array('sec'=>'§8','need'=>'processed بعددِه','re'=>'~\*\*[\d,]+ `processed`\*\*~u',
       'q'=>"SELECT COUNT(*) FROM ems_event_deliveries WHERE state='processed'",
       'why'=>'بدل «صفرُ processed» — وصفُ مستهلكٍ قُرئ وصفَ ناقل'),
);

$stale = 0; $fresh = 0;
printf("%-8s %-26s %10s %10s  %s\n", 'القسم', 'الادّعاء', 'الوثيقة', 'الحيّ', 'الحكم');
echo str_repeat('-', 78) . "\n";
foreach ($CLAIMS as $x) {
    if ($x['doc'] === null) { $stale++; printf("%-8s %-26s %10s %10s  ⛔ NOT_IN_DOC\n", $x['sec'], $x['what'], '—', '—'); continue; }
    $live = $one($x['q']);
    if ($live === null) { printf("%-8s %-26s %10s %10s  UNMEASURABLE\n", $x['sec'], $x['what'], $x['doc'], '—'); continue; }
    $live = (int) $live; $ok = ($live === (int) $x['doc']);
    $ok ? $fresh++ : $stale++;
    printf("%-8s %-26s %10s %10s  %s\n", $x['sec'], $x['what'], $x['doc'], $live,
        $ok ? '✔ مطابق' : ('⚠ انحرف ' . sprintf('%+d', $live - (int) $x['doc'])));
}

echo "\n══ المقاماتُ الثلاثةُ — أَبقي التصحيحُ في الوثيقة؟ ══\n";
foreach ($FRAMING as $x) {
    $inDoc = preg_match($x['re'], $src) > 0;
    $inDoc ? $fresh++ : $stale++;
    printf("  %-4s %-28s في الوثيقة=%-8s الحيّ=%-8s %s\n", $x['sec'], $x['need'],
        $inDoc ? '✔ نعم' : '⛔ لا', $one($x['q']), $x['why']);
}

printf("\n══ الحصيلة ══\n  ادّعاءاتٌ مطابقة: %d · **متقادمة: %d**\n", $fresh, $stale);
echo "  STALE_CLAIM_COUNT = $stale\n";
echo "\n⛔ ولا يُصحَّح رقمٌ في الوثيقةِ إلا بهذا الجدولِ بجانبِه.\n";
file_put_contents($ROOT . '/docs/injint01/asbuilt_drift.json',
    json_encode(array('head' => $head, 'stale' => $stale, 'fresh' => $fresh,
        'claims' => $CLAIMS, 'framing' => $FRAMING), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
