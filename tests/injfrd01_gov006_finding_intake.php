<?php
/**
 * tests/injfrd01_gov006_finding_intake.php
 *   شاهدُ FR-GOV-006 — استقبالُ الدليلِ الجديد: لا كشفَ يُهمَل لغيابِ رمز
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المعيارُ بنصِّه**: «استقبالُ الدليلِ الجديد: **كشفٌ ← مراجعةٌ ← رمزٌ ←
 *   شدةٌ ← تغييرٌ مضبوط**» · ومعيارُ القبول «**صفرُ كشفٍ مُهمَلٍ لغيابِ رمز**» ·
 *   وسالبُه «كشفٌ مُهمَلٌ لأن رمزَه غيرُ موجود ← رسوب».
 *
 * ◆ **والعطبُ الذي يمنعه**: كشفٌ يُسجَّل في `FINDINGS.md` ثمّ **يسقط لأنه لا
 *   يحمل رمزَ فجوةٍ يربطه بمطلب** — فيبقى مكتوبًا ولا يُعالَج، ويصير السجلُّ
 *   أرشيفًا لا مدخلًا. **والكشفُ الذي لا يُربَط لا يُنفَّذ.**
 *
 * ◆ **والسلسلةُ تُقاس حلقةً حلقة**:
 *   ① **كشفٌ** — لكلِّ صفٍّ رمزٌ `F-Cnn` فريد.
 *   ② **مراجعةٌ** — لكلِّ كشفٍ دليلٌ مكتوبٌ في عمودِ `Evidence` (لا يُقبل فارغ).
 *   ③ **رمزٌ** — عمودُ `GAP` إمّا رمزُ فجوةٍ أو `—` **بقرارٍ صريحٍ لا بسهو**.
 *   ③-ب **ولا خانةَ تسقط صامتة** — خانةٌ غيرُ فارغةٍ لا تُقرأ رمزًا ولا `—`
 *      (‏كلمةُ «**جديد**» مثلًا) **تخرج من العدَّين معًا فلا يُفحَص يُتمُها**.
 *      والزينةُ (`**` والعلاماتُ الخلفية) تُجرَّد قبلَ المطابقةِ — **فالرمزُ
 *      المكتوبُ بخطٍّ عريضٍ رمزٌ**، ومن طابق حرفًا بلا تجريدٍ مرَّت عليه
 *      خمسةُ كشوفٍ يتيمةٍ خضراءَ في لقطةِ `BL-20260902`.
 *   ④ **شدةٌ** — ذو الرمزِ يجب أن يُقابله **مطلبٌ في الدفترِ الرسميّ** يحمل
 *      أولويّتَه؛ وبلا مطلبٍ فالكشفُ مُهمَلٌ مهما كُتب.
 *   ⑤ **تغييرٌ مضبوط** — للمطلبِ حالةُ إغلاقٍ من المفرداتِ المغلقة.
 *
 * ◆ **و`—` ليست إهمالًا إن كانت قرارًا**: كشفٌ عن عُدّةِ القياسِ نفسِها أو عن
 *   حقيقةٍ لا فجوةَ فيها **يُعلَن بلا رمزٍ بحقّ**. والفرقُ أن يكون مكتوبًا.
 *
 * التشغيل: php tests/injfrd01_gov006_finding_intake.php [--negative]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/tools/lib/xlsx_io.php';

$ok = 0; $bad = 0;
function chk($c, $l, $d = '') {
    global $ok, $bad;
    if ($c) { $ok++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $bad++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}

$neg = in_array('--negative', $argv, true);
echo "══ FR-GOV-006 — استقبالُ الدليلِ الجديد ══\n";

$FIND = $ROOT . '/docs/baseline_20260821/FINDINGS.md';
if (!is_file($FIND)) { exit("⛔ سجلُّ الكشوفِ مفقود — ولا يُقاس استقبالٌ بلا مستقبَل\n"); }
$txt = (string) file_get_contents($FIND);

/* ── ① الكشوفُ تُستخرَج بصفوفِها ─────────────────────────────────────────── */
$rows = array();
foreach (explode("\n", $txt) as $line) {
    if (strpos($line, '|') === false) { continue; }
    if (!preg_match('~\|\s*\*\*(F-[A-Z0-9]+)\*\*\s*\|~u', $line, $m)) { continue; }
    $cells = array_map('trim', explode('|', trim($line, "| \t")));
    $rows[$m[1]] = $cells;
}
printf("  الكشوفُ في السجل: **%d**\n", count($rows));
chk(count($rows) > 0, '**المقامُ غيرُ صفريّ** — ثمَّ كشوفٌ تُستقبَل', count($rows) . ' كشفًا');
if (empty($rows)) { echo "\nالنتيجة: {$ok} نجاح · {$bad} رسوب\n"; exit(1); }

/* ── ② لكلِّ كشفٍ دليلٌ ورمزٌ ─────────────────────────────────────────── */
$noEvidence = array(); $noCode = array(); $coded = array();
$declaredNone = array(); $declaredClosed = array(); $unparsed = array();
foreach ($rows as $id => $cells) {
    $nc = count($cells);
    $gap = $nc >= 1 ? $cells[$nc - 1] : '';
    $ev  = $nc >= 2 ? $cells[$nc - 2] : '';
    if ($ev === '') { $noEvidence[] = $id; }
    if ($gap === '') { $noCode[] = $id; continue; }
    /* ◆ **الزينةُ ليست جزءًا من الرمز**: خانةٌ مكتوبةٌ `**GAP-80**` رمزٌ مثلُ
     *   `GAP-80` سواءً — **ومن طابق حرفًا بلا تجريدٍ قرأ لا شيءَ حيث كُتب رمز**
     *   فمرَّ الكشفُ اليتيمُ أخضرَ. مرَّت هكذا **خمسةُ كشوفٍ** في لقطةِ
     *   `BL-20260902` (`F-H05` · `F-H07` · `F-H08` · `F-H14` · `F-H15`) بينما
     *   حجَب الالتزامَ سادسٌ وحدَه لأنّ خانتَه كُتبت عاريةً. **فالخضرةُ كانت
     *   عمى قارئٍ لا سلامةَ سجلّ.** */
    $bare = trim($gap, "* `\t");
    if ($bare === '—' || $bare === '-') { $declaredNone[] = $id; continue; }
    /* ◆ **الإعلانُ في الخانةِ نفسِها وقارئي أهمله**: `F-C03` خانتُه
     *   `GAP-18 مُغلق` — كشفٌ **يُبلِّغ بإغلاقِ فجوةٍ** لا يطالب بمطلبٍ لها.
     *   فرماه أوّلُ قارئٍ مُهمَلًا لأنه بحث عن مطلبٍ لفجوةٍ مُغلَقة.
     * ◆ **وهذا قراءةُ إعلانٍ لا تخفيفُ بوابة**: الكلمةُ مكتوبةٌ في السجلِّ،
     *   ومن لم يكتبها يبقى مُهمَلًا. */
    if (mb_strpos($gap, 'مُغلق') !== false) { $declaredClosed[] = $id; continue; }
    foreach (preg_split('~[\s·/]+~u', $gap) as $g) {
        $g = trim($g, "* `\t");
        if ($g === '' || $g === 'مُغلق') { continue; }
        if (preg_match('~^GAP-?(\d+)$~u', $g, $gm)) { $coded[$id][] = 'GAP-' . $gm[1]; }
        elseif (preg_match('~^(\d+)$~', $g, $gm)) { $coded[$id][] = 'GAP-' . $gm[1]; }
    }
    /* ◆ **ولا خانةَ تسقط صامتة**: كلمةٌ مكانَ الرمزِ (`**جديد**`) تُخرج الكشفَ
     *   من المرمَّزِ ومن المُعلَنِ بلا فجوةٍ **معًا** — فلا يُفحَص يُتمُه ولا
     *   يُعَدُّ قرارًا، ويمرُّ بلا أثرٍ في أيِّ عدّاد. **والصمتُ أخطرُ من الحمرة**،
     *   فالحمراءُ تُقرأ والصامتةُ لا يعلم بها أحد. */
    if (empty($coded[$id])) { $unparsed[] = $id . ' («' . $bare . '»)'; }
}
chk(empty($noEvidence), '② **لكلِّ كشفٍ دليلٌ مكتوب** — مراجعةٌ لا انطباع',
    empty($noEvidence) ? count($rows) . ' من ' . count($rows) : implode(' · ', $noEvidence));
chk(empty($noCode), '③ **ولا كشفَ بعمودِ رمزٍ فارغ** — الفراغُ سهوٌ لا قرار',
    empty($noCode) ? 'صفرُ فارغ' : implode(' · ', $noCode));
chk(empty($unparsed), '③-ب **ولا خانةَ رمزٍ تسقط صامتةً** — الكلمةُ ليست رمزًا ولا قرارًا',
    empty($unparsed) ? 'صفرُ خانةٍ غيرِ مقروءة' : count($unparsed) . ': ' . implode(' · ', $unparsed));
printf("     ذو رمزِ فجوة: %d · مُعلَنٌ بلا فجوةٍ («—») بقرار: %d\n",
       count($coded), count($declaredNone));

/* ── ③ ذو الرمزِ يقابله مطلبٌ في الدفترِ الرسميّ ─────────────────────────── */
$XLSX = $ROOT . '/docs/sources/INJ-FRD-REM-01/workbook.xlsx';
if (!is_file($XLSX)) { exit("⛔ الدفترُ الرسميُّ مفقود\n"); }
$wb = xlsx_read($XLSX);
$wr = $wb[array_keys($wb)[0]];
$hdr = $wr[3];
$ix = array();
foreach ($hdr as $i => $h) { $ix[trim(str_replace('◆ ', '', (string) $h))] = $i; }
$gapToReq = array();
foreach ($wr as $i => $r) {
    if ($i < 4) { continue; }
    $rid = trim((string) ($r[$ix['المعرِّف']] ?? ''));
    if (!preg_match('~^[A-Z]{2,4}-[A-Z]{2,4}-\d{3}$~', $rid)) { continue; }
    $cl = trim((string) ($r[$ix['Closure_State']] ?? '')) ?: 'OPEN';
    foreach (preg_split('~[\s·]+~u', trim((string) ($r[$ix['الفجوة']] ?? ''))) as $g) {
        $g = trim($g);
        if ($g === '' || $g === '—') { continue; }
        if (preg_match('~^GAP-?(\d+)$~u', $g, $gm)) { $g = 'GAP-' . $gm[1]; }
        elseif (preg_match('~^(\d+)$~', $g, $gm)) { $g = 'GAP-' . $gm[1]; }
        else { continue; }
        $gapToReq[$g][] = array('id' => $rid, 'cl' => $cl);
    }
}
printf("     فجواتٌ مربوطةٌ بمطلبٍ في الدفتر: %d\n", count($gapToReq));

$orphanFind = array(); $linked = array();
foreach ($coded as $fid => $gaps) {
    $hit = false;
    foreach ($gaps as $g) { if (isset($gapToReq[$g])) { $hit = true; break; } }
    if ($hit) { $linked[] = $fid; } else { $orphanFind[] = $fid . ' (' . implode(' ', $gaps) . ')'; }
}
chk(empty($orphanFind),
    'FR-GOV-006 · ④ **صفرُ كشفٍ مُهمَلٍ لغيابِ رمزٍ يقابله مطلب**',
    empty($orphanFind) ? count($linked) . ' كشفًا مربوطًا بمطلب'
                       : count($orphanFind) . ' مُهمَل: ' . implode(' · ', array_slice($orphanFind, 0, 4)));

/* ── ④ وللمطلبِ المرتبطِ حالةٌ من المفرداتِ المغلقة — «تغييرٌ مضبوط» ─────── */
$VOCAB = array('EVIDENCE_CLOSED', 'IMPLEMENTED_NOT_CLOSED', 'BLOCKED_GOVERNING_SOURCE',
               'BLOCKED_OWNER_DECISION', 'REGRESSION_CONSTRAINT', 'OPEN');
$badState = array();
foreach ($coded as $fid => $gaps) {
    foreach ($gaps as $g) {
        if (!isset($gapToReq[$g])) { continue; }
        foreach ($gapToReq[$g] as $rq) {
            if (!in_array($rq['cl'], $VOCAB, true)) { $badState[] = $rq['id'] . '=' . $rq['cl']; }
        }
    }
}
chk(empty($badState), '⑤ **وحالةُ كلِّ مطلبٍ من مفرداتٍ مغلقة** — تغييرٌ مضبوطٌ لا وصفٌ حرّ',
    empty($badState) ? 'صفرُ حالةٍ خارجَ المفردات' : implode(' · ', array_slice($badState, 0, 4)));

if ($neg) {
    /* ◆ الحزامُ يقيس **قدرةَ الفحصِ على رصدِ كشفٍ بلا رمزٍ يقابله مطلب** */
    echo "\n── الحزامُ السالب ──\n";
    $ghost = 'GAP-99999';
    chk(!isset($gapToReq[$ghost]),
        '**رمزٌ لا يقابله مطلبٌ يُعَدُّ إهمالًا**',
        $ghost . ' ⇒ ' . (isset($gapToReq[$ghost]) ? 'موجودٌ ✘' : 'غيرُ موجودٍ ✔'));
    /* ومحاكاةُ كشفٍ برمزٍ وهميٍّ — يُقاس أن المنطقَ يرميه مُهمَلًا */
    $fakeCoded = array('F-BELT' => array($ghost));
    $fakeOrphan = array();
    foreach ($fakeCoded as $fid => $gaps) {
        $hit = false;
        foreach ($gaps as $g) { if (isset($gapToReq[$g])) { $hit = true; break; } }
        if (!$hit) { $fakeOrphan[] = $fid; }
    }
    chk(count($fakeOrphan) === 1,
        'و**كشفٌ برمزٍ لا مطلبَ له يُرصد مُهمَلًا** — فالفحصُ يرمي ولا يسكت',
        'المرصود: ' . implode(' · ', $fakeOrphan));
    /* ◆ **والعمى المقيسُ يُقاس أنه زال**: `**GAP-77**` بخطٍّ عريضٍ رمزٌ واحدٌ
     *   مع `GAP-77` العاري — ولو تفرَّقا لعاد الكشفُ اليتيمُ يمرُّ صامتًا. */
    $strip = function ($s) {
        $out = array();
        foreach (preg_split('~[\s·/]+~u', $s) as $g) {
            $g = trim($g, "* `\t");
            if (preg_match('~^GAP-?(\d+)$~u', $g, $m)) { $out[] = 'GAP-' . $m[1]; }
        }
        return $out;
    };
    chk($strip('**GAP-77**') === array('GAP-77') && $strip('`GAP-80`') === array('GAP-80'),
        'و**الزينةُ لا تُخفي الرمزَ** — `**GAP-77**` و``GAP-80`` يُقرآن رمزَين',
        implode(' · ', array_merge($strip('**GAP-77**'), $strip('`GAP-80`'))));
    chk($strip('**جديد**') === array(),
        'و**الكلمةُ لا تُقرأ رمزًا** — «**جديد**» تبقى غيرَ مرمَّزةٍ ويرصدها ③-ب',
        'المقروء: ' . (count($strip('**جديد**')) ? implode(' · ', $strip('**جديد**')) : 'لا شيء ✔'));
}

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $ok, $bad);
exit($bad === 0 ? 0 : 1);
