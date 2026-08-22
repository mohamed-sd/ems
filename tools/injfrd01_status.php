<?php
/**
 * tools/injfrd01_status.php — تحديثُ حالةِ المتطلباتِ في الدفترِ نفسِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لا متعقِّبَ جديدًا خارجَ الحزمة** — بنصِّ §13: الحالةُ النهائيةُ تعود إلى
 *   دفترِ المتطلباتِ نفسِه. فهذه الأداةُ تكتب فيه ولا تُنشئ سجلًّا موازيًا.
 *
 * ◆ **وأعمدةُ التنفيذِ الخمسةُ تُضاف مرةً واحدة**: `Commit` · `Test_Result` ·
 *   `Evidence_Status` · `Blocker` · `Closure_State`.
 *
 * ◆ **والإغلاقُ لا يُكتب بالادعاء**: `EVIDENCE_CLOSED` لا تُقبل إلا إذا حملت
 *   الشروطَ السبعةَ مجتمعةً (§الحادي عشر) — والأداةُ ترفض ما دونها.
 *
 * التشغيل:
 *   php tools/injfrd01_status.php --list [--cs=CHG-…]
 *   php tools/injfrd01_status.php --set=FR-NAV-001 --status=منفَّذ --commit=abc1234 \
 *       --test=PASS --evidence=EV-NAV-001 --closure=EVIDENCE_CLOSED [--blocker=…]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/xlsx_io.php';
$XLSX = $ROOT . '/docs/sources/INJ-FRD-REM-01/workbook.xlsx';
$SHEET = 'سجل المتطلبات';

$arg = array();
foreach ($argv as $a) { if (preg_match('~^--([a-z]+)(?:=(.*))?$~s', $a, $m)) { $arg[$m[1]] = isset($m[2]) ? $m[2] : true; } }

/* الأعمدةُ التنفيذيةُ الخمسة — تُنشأ إن لم تكن */
$EXEC = array('Commit', 'Test_Result', 'Evidence_Status', 'Blocker', 'Closure_State');
/* حالاتُ الإغلاقِ المشروعةُ — ولا رابعَ لها بلا قرار */
$CLOSURES = array('EVIDENCE_CLOSED', 'IMPLEMENTED_NOT_CLOSED', 'BLOCKED_GOVERNING_SOURCE',
                  'BLOCKED_OWNER_DECISION', 'OPEN', 'REGRESSION_CONSTRAINT');

$z = new ZipArchive();
if ($z->open($XLSX) !== true) { exit("⛔ تعذّر فتحُ الدفتر\n"); }
$ent = array();
for ($i = 0; $i < $z->numFiles; $i++) { $ent[$z->getNameIndex($i)] = $z->getFromIndex($i); }
$z->close();
$s1 = $ent['xl/worksheets/sheet1.xml'];

/* خريطةُ الرأسِ تُقرأ من الورقةِ لا تُفترَض */
$HEAD = array();
if (preg_match('~<row r="4"[^>]*>(.*?)</row>~su', $s1, $hm)) {
    preg_match_all('~<c r="([A-Z]+)4"[^>]*>.*?<t[^>]*>(.*?)</t>~su', $hm[1], $cm, PREG_SET_ORDER);
    foreach ($cm as $c) {
        $HEAD[trim(str_replace('◆ ', '', html_entity_decode($c[2], ENT_QUOTES | ENT_XML1, 'UTF-8')))] = $c[1];
    }
}
if (count($HEAD) < 30) { exit("⛔ تعذّر قراءةُ الرأس\n"); }

function next_col($letter) {
    $n = 0;
    foreach (str_split($letter) as $ch) { $n = $n * 26 + (ord($ch) - 64); }
    return xlsx_col_letter($n);   /* xlsx_col_letter يأخذ فهرسًا صفريًّا فيُعطي التالي */
}

/* إنشاءُ الأعمدةِ الناقصة */
$last = '';
foreach ($HEAD as $L) { if (strlen($L) > strlen($last) || ($L > $last && strlen($L) === strlen($last))) { $last = $L; } }
$made = array();
foreach ($EXEC as $name) {
    if (isset($HEAD[$name])) { continue; }
    $L = next_col($last);
    $cell = '<c r="' . $L . '4" t="inlineStr"><is><t xml:space="preserve">' . $name . '</t></is></c>';
    $pat = '~(<c r="' . $last . '4"(?:\s[^>]*)?(?:/>|>.*?</c>))~su';
    if (!preg_match($pat, $s1)) { exit("⛔ تعذّر إيجادُ العمودِ الأخير {$last}\n"); }
    $s1 = preg_replace($pat, '$1' . $cell, $s1, 1);
    $HEAD[$name] = $L;
    $made[] = $name . '→' . $L;
    $last = $L;
}

/* فهرسُ الصفوفِ بالمعرِّف */
preg_match_all('~<row r="(\d+)"[^>]*>(.*?)</row>~su', $s1, $rm, PREG_SET_ORDER);
$rowOf = array();
foreach ($rm as $r) {
    $rn = (int) $r[1];
    if ($rn < 5) { continue; }
    if (preg_match('~<c r="' . $HEAD['المعرِّف'] . $rn . '"[^>]*>.*?<t[^>]*>(.*?)</t>~su', $r[2], $im)) {
        $id = trim(html_entity_decode($im[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
        if ($id !== '') { $rowOf[$id] = $rn; }
    }
}

/* ── العرض ─────────────────────────────────────────────────────────── */
if (isset($arg['list']) || !isset($arg['set'])) {
    $wb = xlsx_read($XLSX);
    $rows = $wb[$SHEET];
    $hdr = $rows[3];
    $ix = array();
    foreach ($hdr as $i => $h) { $ix[trim(str_replace('◆ ', '', (string) $h))] = $i; }
    $filter = isset($arg['cs']) && is_string($arg['cs']) ? $arg['cs'] : '';
    $tally = array();
    printf("%-14s %-22s %-8s %-10s %-24s %s\n", 'المعرِّف', 'الحزمة', 'أول', 'الحالة', 'الإغلاق', 'الدليل');
    foreach ($rows as $i => $r) {
        if ($i < 4) { continue; }
        $id = trim((string) ($r[$ix['المعرِّف']] ?? ''));
        if (!preg_match('~^FR-~', $id)) { continue; }
        $cs = (string) ($r[$ix['Change_Set_ID']] ?? '');
        if ($filter !== '' && $cs !== $filter) { continue; }
        $cl = isset($ix['Closure_State']) ? (string) ($r[$ix['Closure_State']] ?? '') : '';
        if ($cl === '') { $cl = 'OPEN'; }
        $tally[$cl] = (isset($tally[$cl]) ? $tally[$cl] : 0) + 1;
        printf("%-14s %-22s %-8s %-10s %-24s %s\n", $id, $cs,
               (string) ($r[$ix['الأولوية']] ?? ''), (string) ($r[$ix['الحالة']] ?? ''), $cl,
               isset($ix['Evidence_Status']) ? (string) ($r[$ix['Evidence_Status']] ?? '') : '');
    }
    echo "\n";
    foreach ($tally as $k => $v) { printf("  %-26s %d\n", $k, $v); }
    exit(0);
}

/* ── الكتابة ───────────────────────────────────────────────────────── */
$id = (string) $arg['set'];
if (!isset($rowOf[$id])) { exit("⛔ معرِّفٌ غيرُ موجود: {$id}\n"); }
$rn = $rowOf[$id];

$closure = isset($arg['closure']) ? (string) $arg['closure'] : '';
if ($closure !== '' && !in_array($closure, $CLOSURES, true)) {
    exit("⛔ حالةُ إغلاقٍ غيرُ مشروعة: {$closure}\n   المشروعُ: " . implode(' · ', $CLOSURES) . "\n");
}
/* ◆ **الشروطُ السبعةُ مجتمعةً أو لا إغلاق** — §الحادي عشر */
if ($closure === 'EVIDENCE_CLOSED') {
    $need = array('commit' => 'هاشُ الالتزام', 'test' => 'نتيجةُ الاختبار', 'evidence' => 'الدليل');
    foreach ($need as $k => $lbl) {
        if (empty($arg[$k])) { exit("⛔ لا يُكتب EVIDENCE_CLOSED بلا {$lbl}\n"); }
    }
    if (stripos((string) $arg['test'], 'PASS') === false) {
        exit("⛔ لا يُكتب EVIDENCE_CLOSED ونتيجةُ الاختبارِ ليست PASS\n");
    }
}

$set = array();
if (isset($arg['status']))   { $set['الحالة'] = (string) $arg['status']; }
if (isset($arg['commit']))   { $set['Commit'] = (string) $arg['commit']; }
if (isset($arg['test']))     { $set['Test_Result'] = (string) $arg['test']; }
if (isset($arg['evidence'])) { $set['Evidence_Status'] = (string) $arg['evidence']; }
if (isset($arg['blocker']))  { $set['Blocker'] = (string) $arg['blocker']; }
if ($closure !== '')         { $set['Closure_State'] = $closure; }

foreach ($set as $name => $val) {
    if (!isset($HEAD[$name])) { exit("⛔ عمودٌ غيرُ موجود: {$name}\n"); }
    $ref = $HEAD[$name] . $rn;
    $rep = '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
         . htmlspecialchars($val, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</t></is></c>';
    $pat = '~<c r="' . $ref . '"(?:\s[^>]*)?(?:/>|>.*?</c>)~su';
    if (preg_match($pat, $s1)) {
        $s1 = preg_replace($pat, $rep, $s1, 1);
    } else {
        /* الخليةُ غيرُ موجودةٍ — تُضاف في آخرِ صفِّها */
        $pr = '~(<row r="' . $rn . '"[^>]*>)(.*?)(</row>)~su';
        $s1 = preg_replace_callback($pr, function ($m) use ($rep) { return $m[1] . $m[2] . $rep . $m[3]; }, $s1, 1);
    }
}
$s1 = preg_replace('~<dimension ref="A1:[A-Z]+(\d+)"/>~', '<dimension ref="A1:' . $last . '$1"/>', $s1);
$ent['xl/worksheets/sheet1.xml'] = $s1;

$tmp = $XLSX . '.tmp';
$zz = new ZipArchive();
if ($zz->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { exit("⛔ تعذّر الكتابة\n"); }
foreach ($ent as $n => $d) { $zz->addFromString($n, $d); }
$zz->close();
if (!@rename($tmp, $XLSX)) { @copy($tmp, $XLSX); @unlink($tmp); }

/* ◆ **قراءةٌ ثانيةٌ إلزامٌ** — الكتابةُ التي لا تُقرأ بعدَها كتابةٌ مزعومة */
$chk = xlsx_read($XLSX);
$hdr2 = $chk[$SHEET][3];
$ix2 = array();
foreach ($hdr2 as $i => $h) { $ix2[trim(str_replace('◆ ', '', (string) $h))] = $i; }
$got = $chk[$SHEET][$rn - 1];
$bad = array();
foreach ($set as $name => $val) {
    if (!isset($ix2[$name]) || trim((string) ($got[$ix2[$name]] ?? '')) !== $val) { $bad[] = $name; }
}
if ($bad) { exit("⛔ كتابةٌ مزعومة — لم تُقرأ: " . implode(' · ', $bad) . "\n"); }

if ($made) { echo "  ✔ أُنشئت أعمدةٌ: " . implode(' · ', $made) . "\n"; }
printf("  ✔ %s (صف %d) — %s\n", $id, $rn,
       implode(' · ', array_map(function ($k, $v) { return "{$k}={$v}"; }, array_keys($set), $set)));
