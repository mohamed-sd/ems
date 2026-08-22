<?php
/**
 * tools/injfrd01_reconcile.php
 *   بوابةُ مصالحةِ الحزمة — INJ-FRD-REM-01 §أولًا · وثوابتُ القبولِ من الدفتر
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الدفترُ هو المصدرُ القانونيُّ الوحيد** بنصِّ أمرِ التنفيذ، والوثيقةُ تمثيلٌ
 *   مقروءٌ مشتقٌّ منه. فكلُّ عددٍ هنا **يُشتقُّ من الدفترِ حيًّا** ولا يُكتب حرفًا
 *   — وهو نصُّ الأمر: «ولا يُكتب العدد يدويًا بعد ذلك؛ يُشتق من الدفتر».
 *
 * ◆ **والمصالحةُ اتجاهانِ لا اتجاهٌ واحد**: يُقاس ما في الدفترِ وليس في الوثيقة،
 *   وما في الوثيقةِ وليس في الدفتر. والثاني أخطرُ — فهو حكمٌ يُقرأ ولا سجلَّ له.
 *
 * التشغيل: php tools/injfrd01_reconcile.php [--json]
 * الخروج : 0 عند PASS · 1 عند أيِّ رسوب
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/xlsx_io.php';

$XLSX = $ROOT . '/docs/sources/INJ-FRD-REM-01/workbook.xlsx';
$DOCX = $ROOT . '/docs/sources/INJ-FRD-REM-01/document.docx';
$SH_REQ = 'سجل المتطلبات';
$SH_TRC = 'مصفوفة التتبع';
$SH_JRN = 'محطات الرحلتين';
$SH_ACC = 'ثوابت قبول الوثيقة';

/* ── نصُّ الوثيقةِ يُستخرَج حيًّا — ولا يُقرأ من ملفٍّ وسيطٍ قد يتقادم ─────── */
function docx_text($path)
{
    $z = new ZipArchive();
    if ($z->open($path) !== true) { return ''; }
    $xml = $z->getFromName('word/document.xml');
    $z->close();
    if ($xml === false) { return ''; }
    $xml = preg_replace('~</w:p>~', "\n", $xml);
    $xml = preg_replace('~</w:tc>~', ' | ', $xml);
    $xml = preg_replace('~</w:tr>~', "\n", $xml);
    $t = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    /* الوثيقةُ تحقن محارفَ اتجاهٍ غيرَ مرئيةٍ حولَ المعرِّفات — تُنزع للمطابقة */
    return str_replace(array("\u{2066}", "\u{2069}", "\u{200F}", "\u{200E}"), '', $t);
}

$wb = xlsx_read($XLSX);
$doc = docx_text($DOCX);
if (!$wb || $doc === '') { exit("⛔ تعذّر قراءةُ أحدِ المصدرَين\n"); }

/* ── استخراجُ المتطلباتِ من الدفتر ─────────────────────────────────────── */
$rows = isset($wb[$SH_REQ]) ? $wb[$SH_REQ] : array();
$HDR = isset($rows[3]) ? $rows[3] : array();
$col = array();
foreach ($HDR as $i => $h) { $col[trim(str_replace('◆ ', '', (string) $h))] = $i; }
function C($r, $col, $name) { return isset($col[$name], $r[$col[$name]]) ? trim((string) $r[$col[$name]]) : ''; }

$reqs = array();
foreach ($rows as $i => $r) {
    if ($i < 4) { continue; }
    $id = C($r, $col, 'المعرِّف');
    /* ◆ **صفُّ التعليقِ ليس متطلبًا**: في السجلِّ صفُّ ملاحظةٍ يمتدُّ على الورقةِ
     *   فيقع نصُّه في خانةِ المعرِّف. وعدُّه متطلبًا صنع **سبعةَ رسوباتٍ كاذبةً**
     *   («فارغ: 1» في كلِّ حقلٍ إلزاميّ) — والعيبُ في القارئِ لا في الدفتر.
     *   ⇒ المعرِّفُ يطابق نمطَه أو لا يُعَدّ. */
    if (!preg_match('~^[A-Z]{2,4}-[A-Z]{2,4}-\d{3}[^\s]*$~u', $id)) { continue; }
    $reqs[$id] = $r;
}

$pass = 0; $fail = 0; $lines = array();
function G($ok, $label, $ev)
{
    global $pass, $fail, $lines;
    if ($ok) { $pass++; } else { $fail++; }
    $lines[] = array('ok' => $ok, 'label' => $label, 'ev' => $ev);
    printf("  %s %-46s %s\n", $ok ? '✔' : '✘', mb_substr($label, 0, 46), $ev);
}

echo "════ بوابةُ مصالحةِ حزمةِ INJ-FRD-REM-01 ════\n";
printf("  الدفتر: %s\n  الوثيقة: %s\n  المتطلبات المستخرَجة: %d\n\n",
       substr(hash_file('sha256', $XLSX), 0, 12), substr(hash_file('sha256', $DOCX), 0, 12), count($reqs));

/* ══ ① التصحيحاتُ الخمسةُ لضبطِ الحزمة ═══════════════════════════════ */
echo "① تصحيحاتُ ضبطِ الحزمةِ الخمسة\n";

/* ①-1 اسمُ غيابِ المصدرِ الحاكم — قيمةٌ قانونيةٌ واحدةٌ بلا مرادفٍ تشغيليّ */
$NEEDLE_BAD = 'SOURCE_' . 'GOVERNING_' . 'NEEDS';
$badAlias = 0; $goodAlias = 0;
foreach ($wb as $sh => $rs) {
    foreach ($rs as $r) {
        foreach ($r as $v) {
            if (strpos((string) $v, $NEEDLE_BAD) !== false) { $badAlias++; }
            if (strpos((string) $v, 'NEEDS_GOVERNING_SOURCE') !== false) { $goodAlias++; }
        }
    }
}
$badDoc = substr_count($doc, $NEEDLE_BAD);
$badCode = 0;
foreach (array('/tools', '/tests', '/docs', '/app', '/includes', '/database') as $d) {
    $it = @new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . $d, FilesystemIterator::SKIP_DOTS));
    if (!$it) { continue; }
    foreach ($it as $f) {
        if (!$f->isFile()) { continue; }
        if (!preg_match('~\.(php|md|json|csv|txt)$~i', $f->getFilename())) { continue; }
        /* ◆ **الكاشفُ يرصد مفرداتِه هو** — گوتشا موثَّقةٌ من جولةٍ سابقة.
         *   فيُستثنى هذا الملفُّ نفسُه، ويُبنى الوترُ بالوصلِ فلا يطابق ذاتَه. */
        if (realpath($f->getPathname()) === realpath(__FILE__)) { continue; }
        if (strpos((string) @file_get_contents($f->getPathname()), $NEEDLE_BAD) !== false) { $badCode++; }
    }
}
G(($badAlias + $badDoc + $badCode) === 0, '1. اسمٌ قانونيٌّ واحدٌ لغيابِ المصدر',
  "الخاطئ: دفتر={$badAlias} · وثيقة={$badDoc} · كود={$badCode} · والصحيحُ مستعمَلٌ {$goodAlias} مرة");

/* ①-2 عددُ شروطِ قبولِ الوثيقة — **يُشتقُّ من الدفترِ لا يُكتب** */
$accRows = isset($wb[$SH_ACC]) ? $wb[$SH_ACC] : array();
$accN = 0;
foreach ($accRows as $i => $r) {
    if ($i < 4) { continue; }
    if (isset($r[0]) && ctype_digit(trim((string) $r[0]))) { $accN++; }
}
$w = array('صفر','واحد','اثنان','ثلاثة','أربعة','خمسة','ستة','سبعة','ثمانية','تسعة','عشرة',
           'أحدَ عشرَ','اثنا عشرَ','ثلاثةَ عشرَ','أربعةَ عشرَ','خمسةَ عشرَ','ستةَ عشرَ');
$eleven = (substr_count($doc, 'أحدَ عشرَ شرطًا') + substr_count($doc, 'أحد عشر شرطًا')
         + substr_count($doc, 'أحدَ عشرَ ثابتًا'));
foreach ($accRows as $r) { foreach ($r as $v) { if (mb_strpos((string) $v, 'أحدَ عشرَ ثابتًا') !== false) { $eleven++; } } }
G($eleven === 0, '2. صفرُ موضعٍ يقول أحدَ عشرَ شرطًا',
  "الثوابتُ المشتقّةُ من الدفتر: **{$accN}** · مواضعُ «أحدَ عشرَ» الباقية: {$eleven}");

/* ①-3 عدُّ متطلباتِ الاعتمادِ والسلطة — من الدفتر */
$appN = 0; $evtN = 0;
foreach ($reqs as $id => $r) {
    $dm = C($r, $col, 'المجال');
    if ($dm === 'الاعتماد' || $dm === 'السلطة') { $appN++; }
    if ($dm === 'الأحداث') { $evtN++; }
}
$appWord = (mb_strpos($doc, 'المتطلباتُ الأحدَ عشر') !== false);
G($appN === 12 && !$appWord, '3. متطلباتُ الاعتمادِ والسلطةِ اثنا عشر',
  "الدفتر: **{$appN}** · و«الأحدَ عشر» في الوثيقة: " . ($appWord ? 'باقية ✘' : 'أُزيلت ✔'));

/* ①-4 عدُّ متطلباتِ الأحداث */
$evtWord = (mb_strpos($doc, '١١-١ المتطلباتُ السبعة') !== false);
G($evtN === 8 && !$evtWord, '4. متطلباتُ الأحداثِ ثمانية',
  "الدفتر: **{$evtN}** · و«السبعة» في عنوانِ الفصل: " . ($evtWord ? 'باقية ✘' : 'أُزيلت ✔'));

/* ①-5 المعرِّفاتُ آليةٌ لا لغوية */
$nonAscii = array();
foreach (array_keys($reqs) as $id) {
    if (!preg_match('~^[A-Z]{2,4}-[A-Z]{2,4}-\d{3}$~', $id)) { $nonAscii[] = $id; }
}
preg_match_all('~FR-[A-Z]{3}-\d{3}[^\s`|,·)]+~u', $doc, $dm2);
$docNonAscii = array();
foreach (array_unique($dm2[0]) as $x) {
    if (preg_match('~[\x{0600}-\x{06FF}]~u', $x)) { $docNonAscii[] = $x; }
}
$hasLegacy = isset($col['Legacy_Alias']);
G(empty($nonAscii) && empty($docNonAscii) && $hasLegacy,
  '5. معرِّفاتٌ آليةٌ + عمودُ Legacy_Alias',
  'دفتر: ' . (empty($nonAscii) ? '✔' : implode(' · ', $nonAscii))
  . ' · وثيقة: ' . (empty($docNonAscii) ? '✔' : implode(' · ', $docNonAscii))
  . ' · العمود: ' . ($hasLegacy ? '✔' : '✘'));

/* ══ ② ثوابتُ قبولِ الوثيقةِ — تُقاس على الدفترِ لا تُدَّعى ═══════════════ */
echo "\n② ثوابتُ القبولِ مقيسةً على الدفتر ({$accN} ثابتًا)\n";

$blank = function ($name) use ($reqs, $col) {
    $n = 0;
    foreach ($reqs as $r) { if (C($r, $col, $name) === '') { $n++; } }
    return $n;
};
G($blank('المصدرُ الحاكم') === 0, 'كلُّ متطلبٍ له مصدرٌ حاكم', 'فارغ: ' . $blank('المصدرُ الحاكم'));
G($blank('معيارُ القبول') === 0, 'كلُّ متطلبٍ له معيارُ قبول', 'فارغ: ' . $blank('معيارُ القبول'));
G($blank('اختبارٌ موجب') === 0, 'كلُّ متطلبٍ له اختبارٌ موجب', 'فارغ: ' . $blank('اختبارٌ موجب'));
G($blank('اختبارٌ سالب') === 0, 'كلُّ متطلبٍ له اختبارٌ سالب', 'فارغ: ' . $blank('اختبارٌ سالب'));
G($blank('المجال') === 0, 'كلُّ متطلبٍ له مجال', 'فارغ: ' . $blank('المجال'));
G($blank('Change_Set_ID') === 0, 'كلُّ متطلبٍ له حزمةُ تغيير', 'فارغ: ' . $blank('Change_Set_ID'));
G($blank('Requirement_Type') === 0 && $blank('Atomicity_Level') === 0,
  'كلُّ متطلبٍ له صنفٌ ومستوى ذرّية',
  'صنف=' . $blank('Requirement_Type') . ' · ذرّية=' . $blank('Atomicity_Level'));

/* معرِّفٌ مكرَّر */
$ids = array();
foreach ($rows as $i => $r) {
    if ($i < 4) { continue; }
    $id = C($r, $col, 'المعرِّف');
    if ($id !== '') { $ids[] = $id; }
}
$dupIds = count($ids) - count(array_unique($ids));
G($dupIds === 0, 'صفرُ معرِّفٍ مكرَّر', "مكرَّر: {$dupIds} · المجموع " . count($ids));

/* سلوكٌ مطلوبٌ مزدوج */
$beh = array();
foreach ($reqs as $r) { $b = C($r, $col, 'السلوكُ المطلوب'); if ($b !== '') { $beh[] = $b; } }
$dupBeh = count($beh) - count(array_unique($beh));
G($dupBeh === 0, 'صفرُ متطلبٍ مزدوجِ السلوك', "مزدوج: {$dupBeh}");

/* الأبُ المشارُ إليه موجود */
$badParent = array();
foreach ($reqs as $id => $r) {
    $p = C($r, $col, 'Parent_Requirement_ID');
    if ($p !== '' && $p !== '—' && !isset($reqs[$p])) { $badParent[] = "{$id}→{$p}"; }
}
G(empty($badParent), 'كلُّ أبٍ مشارٍ إليه موجود',
  empty($badParent) ? 'صفر' : implode(' · ', array_slice($badParent, 0, 5)));

/* صفرُ دورةٍ في رسمِ التبعيات — DAG */
$dep = array();
foreach ($reqs as $id => $r) {
    $d = C($r, $col, 'التبعيات');
    $dep[$id] = array();
    if ($d !== '' && $d !== '—') {
        foreach (preg_split('~[·,]~u', $d) as $x) {
            $x = trim($x);
            if ($x !== '' && $x !== '—' && isset($reqs[$x])) { $dep[$id][] = $x; }
        }
    }
}
$state = array(); $cycles = array();
$visit = function ($n) use (&$visit, &$state, &$cycles, $dep) {
    if (isset($state[$n]) && $state[$n] === 1) { $cycles[] = $n; return; }
    if (isset($state[$n]) && $state[$n] === 2) { return; }
    $state[$n] = 1;
    foreach ($dep[$n] as $m) { $visit($m); }
    $state[$n] = 2;
};
foreach (array_keys($dep) as $n) { $visit($n); }
G(empty($cycles), 'رسمُ التبعياتِ DAG — صفرُ دورة',
  empty($cycles) ? 'صفر' : implode(' · ', array_unique($cycles)));

/* عتبةٌ بلا مصدر — تُكتب TBD ولا تمرُّ حكمًا */
$badTh = array();
foreach ($reqs as $id => $r) {
    $t = C($r, $col, 'Threshold_Source');
    if ($t === '') { $badTh[] = $id; }
}
G(empty($badTh), 'كلُّ عتبةٍ لها مصدرٌ أو تُعلَن TBD',
  empty($badTh) ? 'صفر' : count($badTh) . ' بلا خانة');

/* ══ ③ مصالحةُ الوثيقةِ بالدفترِ — اتجاهان ═══════════════════════════ */
echo "\n③ مصالحةُ الوثيقةِ بالدفتر — اتجاهان\n";
$inDocNotWb = array(); $inWbNotDoc = array();
preg_match_all('~\bFR-[A-Z]{3}-\d{3}\b~', $doc, $dm3);
$docIds = array_unique($dm3[0]);
foreach ($docIds as $x) { if (!isset($reqs[$x])) { $inDocNotWb[] = $x; } }
foreach (array_keys($reqs) as $x) { if (!in_array($x, $docIds, true)) { $inWbNotDoc[] = $x; } }
G(empty($inDocNotWb), 'صفرُ معرِّفٍ في الوثيقةِ بلا سجلٍّ في الدفتر',
  empty($inDocNotWb) ? 'صفر' : implode(' · ', $inDocNotWb));
G(empty($inWbNotDoc), 'صفرُ معرِّفٍ في الدفترِ لا يظهر في الوثيقة',
  empty($inWbNotDoc) ? 'صفر' : implode(' · ', array_slice($inWbNotDoc, 0, 6)));

/* الفجواتُ: السجلُّ ⇔ مصفوفةُ التتبُّع */
$gapsReq = array();
foreach ($reqs as $r) {
    foreach (preg_split('~[·,]~u', C($r, $col, 'الفجوة')) as $g) {
        $g = trim($g);
        if (preg_match('~^GAP-\d+$~', $g)) { $gapsReq[$g] = true; }
        elseif (preg_match('~^\d+$~', $g)) { $gapsReq['GAP-' . str_pad($g, 2, '0', STR_PAD_LEFT)] = true; }
    }
}
$gapsTrc = array();
foreach ((isset($wb[$SH_TRC]) ? $wb[$SH_TRC] : array()) as $i => $r) {
    if ($i < 4) { continue; }
    $g = trim((string) ($r[1] ?? ''));
    if (preg_match('~^GAP-\d+$~', $g)) { $gapsTrc[$g] = true; }
}
$onlyReq = array_diff(array_keys($gapsReq), array_keys($gapsTrc));
$onlyTrc = array_diff(array_keys($gapsTrc), array_keys($gapsReq));
G(empty($onlyTrc), 'كلُّ فجوةٍ في المصفوفةِ لها متطلبٌ في السجل',
  empty($onlyTrc) ? count($gapsTrc) . '/' . count($gapsTrc) : 'بلا متطلب: ' . implode(' · ', $onlyTrc));

echo "\n" . str_repeat('─', 78) . "\n";
printf("DOCX ↔ XLSX RECONCILIATION = %s   (%d مرَّ · %d رسب)\n",
       $fail === 0 ? '**PASS**' : '**FAIL**', $pass, $fail);
if ($fail > 0) { echo "◆ ولا يبدأ التنفيذُ قبلَ PASS — بنصِّ أمرِ التنفيذ.\n"; }
exit($fail === 0 ? 0 : 1);
