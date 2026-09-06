<?php
/**
 * tools/bl_j_gap_register.php — كتابةُ فجواتِ لقطةِ J في الدفترِ الرسميّ
 * ═══════════════════════════════════════════════════════════════════════════
 * **الدرسُ المحروقُ للمرّةِ الرابعة** (`gap-codes-register-not-prose`): جدولُ
 * الفجواتِ سكَّ `GAP-84/85/86` ولم يُكتب منها واحدٌ في الدفتر، فيَتِمَت كشوفُها
 * (`F-J12` · `F-J03` · `F-J08`) ورسَب `FR-GOV-006` **فحُجب كلُّ التزام**.
 *
 * ⛔ **ولا تُعدَّل الكشوفُ لتمرَّ البوّابة** — الكشفُ دليلٌ مقيس. بل يُكتب
 *   المطلبُ الذي تطالب به الفجوة، **بمضمونٍ منقولٍ من جدولِ الفجواتِ ومن
 *   `FINDINGS.md`** لا مؤلَّفٍ هنا. وحالتُها `OPEN` فلا إغلاقَ بلا شاهد.
 *
 * التشغيل: php tools/bl_j_gap_register.php [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT  = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/xlsx_io.php';
$XLSX  = $ROOT . '/docs/sources/INJ-FRD-REM-01/workbook.xlsx';
$APPLY = in_array('--apply', $argv, true);
$SHEET = 'سجل المتطلبات';
$SRC   = 'لقطةُ الأساسِ J · INJ-ARCH-ASBUILT_ar.md §جدولُ الفجوات';
if (!is_file($XLSX)) { exit("⛔ الدفترُ الرسميُّ مفقود\n"); }

$SPEC = array(
    'FR-EVT-010' => array(
        'gap' => 'GAP-84', 'dom' => 'الأحداث', 'type' => 'ARCHITECTURE_CONTROL', 'prio' => 'P2',
        'now'  => 'مصدران للـDLQ يتناقضان: `ems_event_dead_letter` **صفرُ صفٍّ** بينما صفوفُ التسليمِ '
                . 'بحالةِ `dlq` **37 ⇒ 43** — **والتراكمُ بلا إنذار**',
        'want' => 'سؤالُ «كم رسالةً ماتت؟» **مصدرُ حقيقةٍ واحد**، **وللتراكمِ حاجبٌ يرصده**: '
                . 'إمّا يُملأ جدولُ الرسائلِ الميّتةِ من حالةِ التسليم، وإمّا يُعلَن الجدولُ مهجورًا '
                . 'ويُقرأ العدُّ من `ems_event_deliveries.status` — وفي الحالَين إنذارٌ عند النموّ',
        'trigger' => 'انتقالُ صفِّ تسليمٍ إلى الحالةِ `dlq`',
        'input' => 'صفُّ التسليمِ وحالتُه', 'proc' => 'الكتابةُ في مصدرِ الحقيقةِ المُعلَنِ وحدَه ثمَّ قياسُ التراكم',
        'out' => 'عدٌّ واحدٌ للرسائلِ الميّتةِ وإنذارٌ عند تجاوزِ الحدّ',
        'fail' => 'فرقُ العدَّين يُسمّى برقمَيه ومصدرَيه · وتراكمٌ بلا إنذارٍ يُرصد',
        'tables' => 'ems_event_dead_letter · ems_event_deliveries.status',
        'pos' => 'عدُّ `dlq` من الجدولِ = عدُّه من حالةِ التسليم · أو قرارُ هجرٍ مكتوبٌ لأحدِهما',
        'neg' => 'صفُّ تسليمٍ يصير `dlq` بلا نظيرٍ في مصدرِ الحقيقةِ ← يُرصد',
        'accept' => 'فرقُ عدِّ الرسائلِ الميّتةِ بين المصدرَين = صفر · وتراكمٌ بلا إنذارٍ = صفر '
                  . '(‏المقيسُ عندَ الكشف: **٠ مقابل ٤٣**)',
        'ev' => 'FINDINGS.md `F-J12` · جدولُ الفجوات `GAP-84`',
    ),
    'FR-GOV-021' => array(
        'gap' => 'GAP-85', 'dom' => 'الحوكمة', 'type' => 'ARCHITECTURE_CONTROL', 'prio' => 'P1',
        'now'  => 'شاشاتٌ جديدةٌ بُنيت خارجَ السجلِّ الرسميّ: **275 حقلًا على 14 شاشةً** بلا `SCR-nnnn` '
                . '— **تسعٌ منها `Governance/perm_*`** وأربعٌ `main/org_*`/`sec_*`. والسجلُّ ثابتٌ '
                . 'عند **910** بينما القرصُ **906 ⇒ 917**',
        'want' => 'شاشةٌ تُبنى ⇒ **تُسجَّل في السجلِّ الرسميِّ قبلَ أن تُصيَّر**. '
                . '**والسجلُّ لا يُعفي كاتبَه**: شاشاتُ الحوكمةِ نفسُها تخضع لحوكمتِها',
        'trigger' => 'إضافةُ ملفِّ شاشةٍ إلى شجرةِ الإنتاج',
        'input' => 'مسارُ الشاشةِ وحقولُها', 'proc' => 'تسجيلُها في السجلِّ الرسميِّ ثمَّ ربطُ حقولِها',
        'out' => 'صفرُ حقلٍ بلا `SCR-nnnn`',
        'fail' => 'شاشةٌ بلا صفٍّ تُسمّى بمسارِها وعددِ حقولِها — ولا تُبتلع في فارقِ مقام',
        'tables' => 'repair01_screen_registry · سجلُّ الحقول',
        'pos' => 'حقولٌ بلا `SCR-nnnn` = صفر',
        'neg' => 'إضافةُ شاشةٍ بلا تسجيلٍ ← يُرصد الحقلُ اليتيمُ ويُسمّى',
        'accept' => 'حقلٌ بلا `SCR-nnnn` = صفر (‏المقيسُ عندَ الكشف: **275 حقلًا على 14 شاشة**)',
        'ev' => 'FINDINGS.md `F-J03` · جدولُ الفجوات `GAP-85`',
    ),
    'FR-GOV-022' => array(
        'gap' => 'GAP-86', 'dom' => 'الحوكمة', 'type' => 'ARCHITECTURE_CONTROL', 'prio' => 'P2',
        'now'  => 'تصادمُ معرِّفاتِ فجوات: `GAP-82` كان مُستعمَلًا **مرّتَين** (ساعةُ الجهاز · '
                . 'ومصدرا الـDLQ) — **ونقضٌ لفحصٍ كان أخضرَ عشرَ لقطات**؛ صُحِّح بترقيمِ الثاني `GAP-84`',
        'want' => 'رمزُ فجوةٍ **معرِّفٌ فريدٌ في سجلِّ الفجوات**، وفحصُ التصادمِ يقيسه في كلِّ لقطة. '
                . '**والفحصُ الأخضرُ عشرَ مرّاتٍ لا يُغني عن قياسِه في الحادية عشرة**',
        'trigger' => 'سكُّ رمزِ فجوةٍ جديدٍ في جدولِ الفجوات',
        'input' => 'جدولُ الفجواتِ في `INJ-ARCH-ASBUILT_ar.md`',
        'proc' => 'عدُّ تكرارِ كلِّ رمزٍ ورفضُ المكرَّر',
        'out' => 'سجلُّ فجواتٍ بمعرِّفاتٍ فريدة',
        'fail' => 'رمزٌ مكرَّرٌ يُسمّى بموضعَيه — ولا يُصحَّح صامتًا',
        'tables' => 'docs/baseline_20260821/INJ-ARCH-ASBUILT_ar.md §جدولُ الفجوات',
        'pos' => 'رمزٌ مكرَّرٌ في سجلِّ الفجوات = صفر',
        'neg' => 'سكُّ رمزٍ مستعمَلٍ سلفًا ← يُرصد ويُسمّى بموضعَيه',
        'accept' => 'رمزُ فجوةٍ مكرَّرٌ = صفر (‏المقيسُ عندَ الكشف: `GAP-82` مرّتَين)',
        'ev' => 'FINDINGS.md `F-J08` · جدولُ الفجوات `GAP-86`',
    ),
);

$wb = xlsx_read($XLSX);
if (!isset($wb[$SHEET])) { exit("⛔ ورقةُ «{$SHEET}» مفقودة\n"); }
$wr = $wb[$SHEET]; $hdr = $wr[3]; $ix = array();
foreach ($hdr as $i => $h) { $ix[trim(str_replace('◆ ', '', (string) $h))] = $i; }
$haveIds = array(); $haveGaps = array(); $maxNum = 0;
foreach ($wr as $i => $r) {
    if ($i < 4) { continue; }
    $id = trim((string) ($r[$ix['المعرِّف']] ?? ''));
    if (!preg_match('~^[A-Z]{2,4}-[A-Z]{2,4}-\d{3}$~', $id)) { continue; }
    $haveIds[$id] = 1;
    $maxNum = max($maxNum, (int) trim((string) ($r[$ix['#']] ?? 0)));
    foreach (preg_split('~[\s·]+~u', trim((string) ($r[$ix['الفجوة']] ?? ''))) as $g) {
        $g = trim($g, "* `\t");
        if (preg_match('~^GAP-?(\d+)$~u', $g, $gm)) { $haveGaps['GAP-' . $gm[1]] = $id; }
    }
}
printf("الدفترُ الآن: **%d** معرِّفًا · أقصى ترقيمٍ **%d**\n", count($haveIds), $maxNum);

$rows = array(); $num = $maxNum;
foreach ($SPEC as $id => $s) {
    if (isset($haveIds[$id]))        { echo "  ⟳ مسجَّلٌ سلفًا: $id\n"; continue; }
    if (isset($haveGaps[$s['gap']])) { echo "  ⟳ {$s['gap']} مربوطٌ بـ{$haveGaps[$s['gap']]}\n"; continue; }
    $num++;
    $c = array();
    $c[$ix['#']] = (string) $num;                    $c[$ix['المعرِّف']] = $id;
    $c[$ix['الفجوة']] = $s['gap'];                    $c[$ix['المجال']] = $s['dom'];
    $c[$ix['المصدرُ الحاكم']] = $SRC . ' · ' . $s['ev'];
    $c[$ix['الحالُ الآن']] = $s['now'];               $c[$ix['السلوكُ المطلوب']] = $s['want'];
    $c[$ix['المُطلِق']] = $s['trigger'];               $c[$ix['الفاعل']] = 'النظام';
    $c[$ix['الشروطُ السابقة']] = 'الكشفُ مسجَّلٌ في `FINDINGS.md` والفجوةُ مسكوكةٌ في جدولِ الفجوات';
    $c[$ix['المُدخَل']] = $s['input'];                 $c[$ix['المعالجة']] = $s['proc'];
    $c[$ix['المُخرَج']] = $s['out'];                   $c[$ix['انتقالُ الحالة']] = '—';
    $c[$ix['قاعدةُ الصلاحية']] = '—';                  $c[$ix['سلوكُ الفشل']] = $s['fail'];
    $c[$ix['واقعةُ السجل']] = 'سجلُّ الكشوفِ `docs/baseline_20260821/FINDINGS.md`';
    $c[$ix['الجداولُ والحقول']] = $s['tables'];        $c[$ix['الخدماتُ والأحداث']] = '—';
    $c[$ix['اختبارٌ موجب']] = $s['pos'];               $c[$ix['اختبارٌ سالب']] = $s['neg'];
    $c[$ix['معيارُ القبول']] = $s['accept'];           $c[$ix['الدليل']] = 'FINDINGS.md · ' . $s['ev'];
    $c[$ix['التبعيات']] = '—';                        $c[$ix['الأولوية']] = $s['prio'];
    $c[$ix['الحالة']] = 'مُعلَن';                      $c[$ix['Requirement_Type']] = $s['type'];
    $c[$ix['Atomicity_Level']] = 'ATOMIC';            $c[$ix['Parent_Requirement_ID']] = '—';
    $c[$ix['Change_Set_ID']] = 'CHG-BL-J';            $c[$ix['Test_Applicability']] = 'موجب=YES · سالب=YES';
    $c[$ix['N/A_Reason']] = '—';
    $c[$ix['Threshold_Source']] = 'المقيسُ عندَ الكشفِ في `FINDINGS.md` — ولا عتبةَ مؤلَّفةً هنا';
    $c[$ix['Legacy_Alias']] = '—';                    $c[$ix['Closure_State']] = 'OPEN';
    $rows[] = $c;
    printf("  + %s ⇐ %s · %s\n", $id, $s['gap'], mb_substr($s['now'], 0, 50));
}
if (!$rows) { echo "\nلا جديدَ يُكتب.\n"; exit(0); }
if (!$APPLY) { echo "\nقياسٌ فقط — أعِد بـ`--apply`\n"; exit(0); }
$n = xlsx_append_rows($XLSX, $SHEET, $rows);
printf("\n✔ أُلحق **%d** صفًّا\n", $n);
$wb2 = xlsx_read($XLSX); $wr2 = $wb2[$SHEET]; $ok = 0;
foreach ($wr2 as $i => $r) {
    if ($i < 4) { continue; }
    $id = trim((string) ($r[$ix['المعرِّف']] ?? ''));
    if (isset($SPEC[$id]) && trim((string) ($r[$ix['الفجوة']] ?? '')) === $SPEC[$id]['gap']) { $ok++; }
}
printf("✔ أُعيدت القراءةُ: **%d من %d**\n", $ok, count($SPEC));
exit($ok === count($SPEC) ? 0 : 1);
