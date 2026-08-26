<?php
/**
 * tools/repair01_w10_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ العاشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويُطلَب من البوّابةِ أن تسقط — ثمَّ
 *   تُرجَع الحالة. والحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على رمزِ الحاجبِ لا على عبارتِه**: نصُّ حالةِ الخطأِ يطابق العبارةَ
 *   العربيّةَ فيُخضِرُّ كذبًا — فالالتقاطُ على `✘ W10-nn`.
 *
 * ◆ **وحاجبانِ يُكسَرانِ في الشيفرةِ لا في القاعدة** (`W10-15` و`W10-19`): عطبُ
 *   المولِّدِ وشكلُ المقارنةِ الصلبةِ **بنيةُ ملفٍّ** لا صفُّ جدول — ولا يُختبَرانِ
 *   إلّا بتشويهِ تلك البنيةِ ثمَّ إرجاعِها بايتًا ببايت.
 *
 * ◆ **والكسرُ من زاويةٍ يحرسها المخطَّطُ لا يختبر شيئًا**: `chk_w10st_forbid` يمنع
 *   تفريغَ سببِ المنع، فالزاويةُ المكشوفةُ **حذفُ الممنوعِ كلِّه** عن كيان.
 *
 * التشغيل: php tools/repair01_w10_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x ? $x[0] : null;
};

$PHP   = PHP_BINARY;
$GATE  = $ROOT . '/tools/repair01_w10_gate.php';
$APPLY = $ROOT . '/tools/repair01_w10_apply.php';
$W2    = $ROOT . '/tools/repair01_w2_apply.php';
$SCAN  = $ROOT . '/tools/lib/repair01_w10_scan.php';

function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W10-') !== false && preg_match('/W10-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ── قيمٌ تُلتقَط قبل الكسرِ ليكون الإرجاعُ بالقيمةِ لا بالتخمين ────────── */
$w2Orig   = (string) file_get_contents($W2);
$scanOrig = (string) file_get_contents($SCAN);

$xRule  = (string) $one("SELECT split_rule FROM repair01_dept_crosswalk
                          WHERE verdict = 'SPLIT' ORDER BY id LIMIT 1");
$xId    = (int) $one("SELECT id FROM repair01_dept_crosswalk WHERE verdict = 'SPLIT' ORDER BY id LIMIT 1");
$spKey  = (string) $one("SELECT scope_key FROM repair01_w10_split
                          WHERE split_rule = 'W10_REQ_SURFACE_MATCH' ORDER BY scope_key LIMIT 1");
$spRule = (string) $one("SELECT split_rule FROM repair01_w10_split WHERE scope_key = '" . $esc($spKey) . "'");
$spWhy  = (string) $one("SELECT split_why FROM repair01_w10_split WHERE scope_key = '" . $esc($spKey) . "'");
$spCode = (string) $one("SELECT resolved_code FROM repair01_w10_split WHERE scope_key = '" . $esc($spKey) . "'");
$shKey  = (string) $one("SELECT scope_key FROM repair01_w10_split
                          WHERE in_surfaces = 1 AND in_registry = 1 ORDER BY scope_key LIMIT 1");
$shCode = (string) $one("SELECT owner_code FROM repair01_screen_registry WHERE screen_id = '" . $esc($shKey) . "'");
$surfId = (int) $one("SELECT id FROM repair01_surfaces
                       WHERE dept_legacy = 'المالية والخزينة' AND canonical_code = 'DEP-05' ORDER BY id LIMIT 1");
$surfCd = (string) $one("SELECT canonical_code FROM repair01_surfaces WHERE id = $surfId");
$brId   = (int) $one("SELECT id FROM repair01_w10_bridge ORDER BY id LIMIT 1");
$brProbe= (string) $one("SELECT probe_sql FROM repair01_w10_bridge WHERE id = $brId");
$navRt  = (string) $one("SELECT route FROM nav_canonical
                          WHERE owner_dept = 'المالية والخزينة' ORDER BY id LIMIT 1");
$sbSid  = (string) $one("SELECT screen_id FROM repair01_w10_sidebar ORDER BY screen_id LIMIT 1");
$sbS1   = (string) $one("SELECT s1_verdict FROM repair01_w10_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$crKey  = (string) $one("SELECT scope_key FROM repair01_w10_split
                          WHERE split_rule = 'W10_CROSS_UNIT_KEPT' ORDER BY scope_key LIMIT 1");
$d03    = (int) $one("SELECT scope_rows FROM repair01_w10_decisions WHERE decision_id = 'W10-D-03'");
$d08    = (int) $one("SELECT scope_rows FROM repair01_w10_decisions WHERE decision_id = 'W10-D-08'");
$d09    = (int) $one("SELECT scope_rows FROM repair01_w10_decisions WHERE decision_id = 'W10-D-09'");
$stEnt  = (string) $one("SELECT entity FROM repair01_w10_states WHERE allowed = 0 ORDER BY entity LIMIT 1");
$sodKey = (string) $one("SELECT process_key FROM repair01_w10_sod ORDER BY process_key LIMIT 1");
$sodEnf = (string) $one("SELECT enforced_by FROM repair01_w10_sod WHERE process_key = '" . $esc($sodKey) . "'");
$evCode = (string) $one("SELECT event_code FROM repair01_events WHERE wave = 'W10' ORDER BY event_code LIMIT 1");
$evCons = (string) $one("SELECT consumer_list FROM repair01_events
                          WHERE event_code = '" . $esc($evCode) . "' AND wave = 'W10'");
$gapId  = (int) $one("SELECT id FROM repair01_target_gaps WHERE split_code <> '' ORDER BY id LIMIT 1");
$vocTok = (string) $one("SELECT token_norm FROM repair01_w10_vocab ORDER BY token_norm LIMIT 1");
$vocSide= (string) $one("SELECT side FROM repair01_w10_vocab WHERE token_norm = '" . $esc($vocTok) . "' LIMIT 1");

if ($spKey === '' || $shKey === '' || $surfId <= 0 || $brId <= 0 || $navRt === ''
    || $sbSid === '' || $crKey === '' || $stEnt === '' || $sodKey === ''
    || $evCode === '' || $gapId <= 0 || $vocTok === '') {
    echo "✘ أرضيّةٌ ناقصةٌ للكسر — شغّلْ tools/repair01_w10_apply.php أوّلًا\n";
    exit(1);
}

/* ══════════════════════════════════════════════════════════════════════════
   حالاتُ الكسر — لكلٍّ: الحاجبُ المتوقَّعُ سقوطُه · كسرٌ · إرجاع
   ══════════════════════════════════════════════════════════════════════════ */
$CASES = array(
    array('W10-01', 'نزعُ قاعدةِ الشقِّ عن أحدِ شقَّي وحدةٍ مشقوقة',
        "UPDATE repair01_dept_crosswalk SET split_rule = '' WHERE id = $xId",
        "UPDATE repair01_dept_crosswalk SET split_rule = '" . $esc($xRule) . "' WHERE id = $xId"),

    /* ⚠ **و`chk_w10sp_full` يمنع تفريغَ العذر** — فالكسرُ من تلك الزاويةِ يُردُّ
         ولا يختبر شيئًا. والزاويةُ المكشوفةُ **حذفُ الصفِّ** فينقص الدفترُ عن
         المقامِ المُعادِ اشتقاقُه. (‏درسُ W02: حاجبٌ يُكسَر ممّا يحرسه المخطَّطُ أعمى) */
    array('W10-02', 'حذفُ سطحٍ من دفترِ الشقِّ فينقص عن مقامِه المُعادِ اشتقاقُه',
        "DELETE FROM repair01_w10_split WHERE scope_key = '" . $esc($spKey) . "'", 'APPLY'),

    array('W10-03', 'تغييرُ حكمِ سطحٍ في الدفترِ فيخالف المشتقَّ من المخزن',
        "UPDATE repair01_w10_split SET resolved_code = 'DEP-06'
          WHERE scope_key = '" . $esc($spKey) . "' AND resolved_code = 'DEP-05'",
        "UPDATE repair01_w10_split SET resolved_code = '" . $esc($spCode) . "'
          WHERE scope_key = '" . $esc($spKey) . "'"),

    array('W10-04', 'حذفُ مفردةٍ من سجلِّ المفرداتِ المشتقّ',
        "DELETE FROM repair01_w10_vocab WHERE token_norm = '" . $esc($vocTok) . "' AND side = '" . $esc($vocSide) . "'", 'APPLY'),

    array('W10-05', 'إسنادُ مالكٍ مخالفٍ في سجلِّ الشاشاتِ لسطحٍ في السجلَّين',
        "UPDATE repair01_screen_registry SET owner_code = 'DEP-13' WHERE screen_id = '" . $esc($shKey) . "'",
        "UPDATE repair01_screen_registry SET owner_code = '" . $esc($shCode) . "'
          WHERE screen_id = '" . $esc($shKey) . "'"),

    array('W10-06', 'إخراجُ سطحٍ من شقَّي وحدتِه فينقص المجموعُ عن المقام',
        "UPDATE repair01_surfaces SET canonical_code = 'DEP-07' WHERE id = $surfId",
        "UPDATE repair01_surfaces SET canonical_code = '" . $esc($surfCd) . "' WHERE id = $surfId"),

    array('W10-07', 'إنشاءُ مُعرِّفٍ بديلٍ لرمزِ شقّ',
        "INSERT INTO repair01_key_alias (key_code, alias_table, alias_column, alias_kind, verdict,
             verdict_rule, verdict_why, rows_total, rows_seed, rows_resolvable, link_column,
             rows_linked, wave_stage, src_ref)
         VALUES ('DEP-05', 'w10neg', 'w10neg', 'PARALLEL_REGISTER', 'ALTERNATE_ID',
                 'W10NEG', 'كسر سلبي', 0, 0, 0, 'x', 0, 'W10', 'W10NEG')",
        "DELETE FROM repair01_key_alias WHERE src_ref = 'W10NEG'"),

    /* ⚠ **والمفتاحُ هنا مفتاحُ أعمالٍ لا رقمُ صفّ**: إرجاعُ الجسرِ بالأداةِ يعيد
         بناءَه فتتغيّر أرقامُ صفوفِه — وكسرٌ يمسك رقمَ صفٍّ قديمًا **لا يمسُّ شيئًا
         ويُقرأ حاجبًا أعمى وهو لم يُكسَر أصلًا**. */
    array('W10-08', 'حذفُ صفِّ ترجمةٍ من الجسرِ لمؤشِّرٍ حيٍّ قائم',
        "DELETE FROM repair01_w10_bridge WHERE host_table = 'nav_canonical'
           AND pointer_key = '" . $esc($navRt) . "'", 'APPLY'),

    array('W10-09', 'تشويهُ استعلامِ الإثباتِ فلا يجد صفَّه الحيّ',
        "UPDATE repair01_w10_bridge SET probe_sql = 'SELECT 0'
          WHERE host_table = 'nav_canonical' AND pointer_key = '" . $esc($navRt) . "'", 'APPLY'),

    array('W10-10', 'دهسُ الاسمِ القديمِ برمزٍ معياريٍّ في جدولٍ حيّ',
        "UPDATE nav_canonical SET owner_dept = 'DEP-05' WHERE route = '" . $esc($navRt) . "'",
        "UPDATE nav_canonical SET owner_dept = 'المالية والخزينة' WHERE route = '" . $esc($navRt) . "'"),

    array('W10-11', 'نزعُ حكمِ خطوةٍ من خطواتِ السايدبارِ السبع',
        "UPDATE repair01_w10_sidebar SET s1_verdict = '' WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w10_sidebar SET s1_verdict = '" . $esc($sbS1) . "'
          WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W10-12', 'تغييرُ عددِ الأسطحِ بلا حارسٍ في قرارِه',
        "UPDATE repair01_w10_decisions SET scope_rows = " . ($d08 + 5) . " WHERE decision_id = 'W10-D-08'",
        "UPDATE repair01_w10_decisions SET scope_rows = $d08 WHERE decision_id = 'W10-D-08'"),

    array('W10-13', 'دهسُ مالكِ شاشةٍ عابرةٍ للوحدات',
        "UPDATE repair01_w10_split SET moved_registry = 1 WHERE scope_key = '" . $esc($crKey) . "'",
        "UPDATE repair01_w10_split SET moved_registry = 0 WHERE scope_key = '" . $esc($crKey) . "'"),

    array('W10-14', 'تغييرُ عددِ المحسومِ بترتيبِ الصفوفِ في قرارِه',
        "UPDATE repair01_w10_decisions SET scope_rows = " . ($d03 + 7) . " WHERE decision_id = 'W10-D-03'",
        "UPDATE repair01_w10_decisions SET scope_rows = $d03 WHERE decision_id = 'W10-D-03'"),

    /* ⚠ العطبُ في المولِّدِ بنيةُ ملفٍّ لا صفُّ جدول — والكسرُ إعادةُ الصيغةِ العمياء */
    array('W10-15', 'إرجاعُ خريطةِ الجسرِ العمياءِ إلى مولِّدِ السجلّ',
        'CODE_W2', 'CODE_RESTORE'),

    array('W10-16', 'حذفُ الممنوعِ الصريحِ كلِّه عن كيانٍ في آلةِ حالتِه',
        "DELETE FROM repair01_w10_states WHERE entity = '" . $esc($stEnt) . "' AND allowed = 0", 'APPLY'),

    array('W10-17', 'إعلانُ رمزِ ردٍّ لا وجودَ له في الخدمة',
        "UPDATE repair01_w10_sod SET enforced_by = 'W10_GHOST_CODE_NOT_IN_SOURCE'
          WHERE process_key = '" . $esc($sodKey) . "'",
        "UPDATE repair01_w10_sod SET enforced_by = '" . $esc($sodEnf) . "'
          WHERE process_key = '" . $esc($sodKey) . "'"),

    array('W10-18', 'إبهامُ مستهلكي حدثٍ إلى «كلُّ المستهلكين»',
        "UPDATE repair01_events SET consumer_list = 'كل المستهلكين'
          WHERE event_code = '" . $esc($evCode) . "' AND wave = 'W10'",
        "UPDATE repair01_events SET consumer_list = '" . $esc($evCons) . "'
          WHERE event_code = '" . $esc($evCode) . "' AND wave = 'W10'"),

    array('W10-18', 'تعطيلُ مشتركِ حدثٍ نشطٍ فيصير النشرُ عملًا ضائعًا',
        "UPDATE event_consumers SET active = 0 WHERE event_name = '" . $esc($evCode) . "'",
        "UPDATE event_consumers SET active = 1 WHERE event_name = '" . $esc($evCode) . "'"),

    array('W10-19', 'كتابةُ مقارنةِ عتبةٍ صلبةٍ في مكتبةِ النطاق',
        'CODE_HARD', 'CODE_RESTORE'),

    /* و`chk_w10th_full` يمنع تفريغَ العذر — فالزاويةُ المكشوفةُ حذفُ العتبةِ نفسِها */
    array('W10-19', 'حذفُ عتبةٍ من سجلِّ العتباتِ فينقص المقام',
        "DELETE FROM repair01_w10_thresholds WHERE threshold_key = 'W10_MIN_TOKEN_WORDS'", 'APPLY'),

    array('W10-20', 'إقحامُ صفٍّ بلا ختمِ موجةٍ في سجلِّ الشاشات',
        "INSERT INTO repair01_screen_registry (screen_id, screen_file, route, owner_code, on_disk, origin, src_ref)
         VALUES ('SCR-W1NG','w10neg.php','Neg/w10neg.php','DEP-05',1,'NEG','W10NEG')",
        "DELETE FROM repair01_screen_registry WHERE origin = 'NEG'"),

    array('W10-21', 'نزعُ شقِّ فجوةٍ من دفترِ الفجوات',
        "UPDATE repair01_target_gaps SET split_code = '', split_rule = '', split_why = '' WHERE id = $gapId",
        'APPLY'),

    array('W10-22', 'فكُّ ربطِ بندِ قائمةٍ بمُعرِّفِه المعياريِّ فتسقط محطّةُ الرحلة',
        "UPDATE repair01_w10_sidebar SET s7_linked = 0", 'APPLY'),
);

$done = 0; $blind = 0; $skipped = 0;
foreach ($CASES as $c) {
    list($want, $label, $break, $restore) = $c;

    if ($break === 'CODE_W2') {
        /* الصيغةُ العمياء: خريطةٌ بمفتاحِ الاسمِ بلا استثناءِ المشقوق */
        $hacked = str_replace(
            "    if (\$x['verdict'] === 'SPLIT') { \$splitNames[\$nm] = true; continue; }",
            "    /* w10neg */",
            $w2Orig);
        if ($hacked === $w2Orig) { printf("  ↷ %-8s تعذَّر الحقنُ — مُتخطًّى\n", $want); $skipped++; continue; }
        $hacked = str_replace('$cross[$nm] = $x[\'canonical_code\'];',
                              '$cross[trim($x[\'legacy_name\'])] = $x[\'canonical_code\'];', $hacked);
        file_put_contents($W2, $hacked);
    } elseif ($break === 'CODE_HARD') {
        $hacked = str_replace('$minWords = $TH[\'W10_MIN_TOKEN_WORDS\'][\'v\'];',
            '$minWords = $TH[\'W10_MIN_TOKEN_WORDS\'][\'v\']; if ($minWords > 99) { $minWords = 2; }', $scanOrig);
        if ($hacked === $scanOrig) { printf("  ↷ %-8s تعذَّر الحقنُ — مُتخطًّى\n", $want); $skipped++; continue; }
        file_put_contents($SCAN, $hacked);
    } elseif (is_string($break)) {
        if ($conn->query($break) === false) {
            printf("  ⛔ %-8s فشلَ الكسرُ: %s\n", $want, $conn->error); $blind++; continue;
        }
    }

    list($cx, $fx) = run_gate($PHP, $GATE);
    $fell = in_array($want, $fx, true);
    printf("  %s %-8s %-54s %s\n", $fell ? '✔' : '⛔', $want, mb_substr($label, 0, 54),
           $fell ? 'سقطت كما يجب' : '**لم تسقط — الحاجبُ أعمى**');
    if (!$fell) { $blind++; }

    /* ── الإرجاع ─────────────────────────────────────────────────────── */
    if ($restore === 'CODE_RESTORE') {
        file_put_contents($W2, $w2Orig);
        file_put_contents($SCAN, $scanOrig);
        if ((string) file_get_contents($W2) !== $w2Orig || (string) file_get_contents($SCAN) !== $scanOrig) {
            printf("  ⛔ %-8s فشلَ إرجاعُ الملفّ\n", $want); $blind++;
        }
    } elseif ($restore === 'APPLY') {
        $o = array(); $cc = 0;
        exec('"' . $PHP . '" "' . $APPLY . '" 2>&1', $o, $cc);
        if ($cc !== 0) { printf("  ⛔ %-8s فشلَ إرجاعُ السجلِّ بالأداة\n", $want); $blind++; }
    } elseif (is_string($restore) && $conn->query($restore) === false) {
        printf("  ⛔ %-8s فشلَ الإرجاع: %s\n", $want, $conn->error); $blind++;
    }
    $done++;
}

/* ── التحقّقُ من الإرجاعِ بإعادةِ تشغيلِ البوّابة ─────────────────────── */
echo "\n";
list($cz, $fz) = run_gate($PHP, $GATE);
if ($cz === 0) { echo "الإرجاع: البوّابةُ عادت خضراء ✔\n"; }
else { echo "⛔ الإرجاع فاشل — البوّابةُ ما زالت ساقطةً في: " . implode(',', $fz) . "\n"; $blind++; }

$leftover = (int) $one("SELECT (SELECT COUNT(*) FROM repair01_screen_registry WHERE origin = 'NEG')
                             + (SELECT COUNT(*) FROM repair01_key_alias WHERE src_ref = 'W10NEG')");
if ($leftover > 0) { echo "⛔ بقيَ $leftover صفَّ كسرٍ لم يُنزَع\n"; $blind++; }
else { echo "النظافة: لا صفَّ كسرٍ باقٍ ✔\n"; }
if ((string) file_get_contents($W2) !== $w2Orig || (string) file_get_contents($SCAN) !== $scanOrig) {
    echo "⛔ ملفُّ شيفرةٍ لم يُرجَع بايتًا ببايت\n"; $blind++;
} else { echo "الشيفرة: الملفّانِ عادا بايتًا ببايت ✔\n"; }

printf("\nالفحصُ السلبيّ: %d حالةَ كسرٍ · مُتخطًّى %d · أعمى %d\n", $done, $skipped, $blind);
echo ($blind === 0 && $skipped === 0 ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: يوجد حاجبٌ أعمى أو غيرُ مُختبَر ✘\n");
exit(($blind === 0 && $skipped === 0) ? 0 : 1);
