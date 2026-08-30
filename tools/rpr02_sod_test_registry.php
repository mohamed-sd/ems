<?php
/**
 * tools/rpr02_sod_test_registry.php — `RPR-02` §١٢·٥ · ربطُ الفاحصِ بفصلِ واجبٍ بعينِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الحاجزُ الذي يرفعه** — المقياسُ **#٥** كان محجوبًا بنصِّه: *«فواحصُ سالبةٌ
 *   على القرص ٢٠ · **ولا سجلَّ يربط فاحصًا بفصلِ واجباتٍ بعينِه** ⇒ العددُ لا
 *   يصلح بسطًا ولا مقامًا»*. **والحجبُ كان صحيحًا** — وعشرون ملفًّا عددٌ لا
 *   يقول أيَّ واجبٍ حُرِس، **وقد يكون العشرون كلُّها على واجبٍ واحد**.
 *
 * ◆ **والمقامُ صار مقيسًا**: `repair01_w{N}_sod` في عشرِ موجاتٍ ⇒ **٩٢ عمليةً
 *   حرِجة**، ولكلٍّ `process_key` رمزٌ قانونيٌّ واحد. ⇒ فالمقامُ ٩٢ لا ٢٠،
 *   **والعشرون كانت بسطًا موهومًا لمقامٍ غيرِ موجود**.
 *
 * ⛔ **و`enforced_by` جُرِّب مفتاحًا فسقط — ويُسمّى سقوطُه ولا يُخفى**:
 *   · `W7` و`W9` و`W10`…: **رموزٌ** (`SAME_ACTOR_AWARD_AND_APPROVE`) ⇒ `CODE_LIST`.
 *   · `W8`: **جُملٌ نثريّةٌ** تبدأ بـ`ENFORCED:`/`NOT_ENFORCED:` تصف السلوكَ ⇒ `PROSE`.
 *   · `W6` و`W15`: **لا عمودَ أصلًا** ⇒ `ABSENT` — وهما ثلاثَ عشرةَ عمليّة.
 *   ⇒ فمفتاحُ السجلِّ `process_key` **الموحَّدُ عبرَ الموجاتِ كلِّها**،
 *   و`enforced_by` **يُحفظ كما ورد مصنَّفًا** فيُقرأ العطبُ ولا يُدهَس.
 *   ⛔ **ولا يُوحَّد نثرٌ برمزٍ ليكتمل مفتاح** — «بلا مفتاح» حكمٌ صادق.
 *
 * ◆ **والربطُ يُقاس على الفواحصِ والحواجبِ وحدَها** — لا على العُدّةِ كلِّها:
 *   ⛔ فـ`repair01_w11_apply.php` **يذكر الرمزَ لأنّه بذره** — وعدُّ ذلك ربطًا
 *   **دَورٌ لا قياس**: البذرةُ تشهد لنفسِها. فالبحثُ في `tools/*negative*.php`
 *   و`tools/*_gate*.php` وحدَهما: **من يدَّعي الحراسةَ لا من كتب الصفَّ**.
 *
 * ⛔ **وما يُثبته السجلُّ محدودٌ ويُقال حدُّه**: أنَّ **فاحصًا مسمًّى يدَّعي
 *   حراسةَ واجبٍ مسمًّى** — لا أنَّ الفاحصَ يحمرُّ فعلًا عند الخرق. وتلك تُقاس
 *   بتشغيلِه، **وهي حاجبٌ ثانٍ لا هذا**.
 *
 * التشغيل:
 *   php tools/rpr02_sod_test_registry.php [--apply] [--md] [--list] [--selftest]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$e = function ($x) use ($conn) { return $conn->real_escape_string((string) $x); };

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$LIST  = in_array('--list', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

/* ═══ ① تصنيفُ ما ورد في `enforced_by` — ولا يُوحَّد ═══════════════════════ */
function sod_kind($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') { return 'ABSENT'; }
    /* رمزٌ أو رموزٌ مفصولةٌ بفاصلٍ عربيّ — حروفٌ كبيرةٌ وأرقامٌ وشُرطاتٌ سفليّة */
    if (preg_match('~^[A-Z0-9_]+(\s*·\s*[A-Z0-9_]+)*$~u', $raw)) { return 'CODE_LIST'; }
    return 'PROSE';
}
/* الرموزُ المنتزَعةُ من `CODE_LIST` — وهي وحدَها تصلح للبحثِ في الفواحص */
function sod_codes($raw)
{
    if (sod_kind($raw) !== 'CODE_LIST') { return array(); }
    $out = array();
    foreach (preg_split('~\s*·\s*~u', trim((string) $raw)) as $t) {
        $t = trim($t);
        if ($t !== '') { $out[] = $t; }
    }
    return $out;
}

/* ═══ ② الاختبارُ السالبُ — بمفردةٍ فريدةٍ تكسر التصنيفَ وحدَه ═══════════ */
if ($SELF) {
    $fail = 0;
    if (sod_kind('') !== 'ABSENT')                              { echo "  X الفراغُ لم يُصنَّف ABSENT\n"; $fail++; }
    if (sod_kind('SAME_ACTOR_PREPARE_AND_POST') !== 'CODE_LIST'){ echo "  X الرمزُ لم يُصنَّف CODE_LIST\n"; $fail++; }
    if (sod_kind('SOD_SELF_APPROVAL · METER_NOT_POSTED') !== 'CODE_LIST') { echo "  X الرمزان لم يُصنَّفا\n"; $fail++; }
    if (sod_kind('ENFORCED: claim_approve ⇒ status blocked') !== 'PROSE') { echo "  X النثرُ صُنِّف رمزًا\n"; $fail++; }
    /* ⛔ **والنثرُ العربيُّ نثرٌ ولو حمل رمزًا** */
    if (sod_kind('NOT_ENFORCED: لا انتقال ⇒ W8-D-10') !== 'PROSE') { echo "  X النثرُ العربيُّ صُنِّف رمزًا\n"; $fail++; }
    $c = sod_codes('SOD_SELF_APPROVAL · METER_NOT_POSTED');
    if (count($c) !== 2 || $c[0] !== 'SOD_SELF_APPROVAL')       { echo "  X الرموزُ لم تُنتزَع\n"; $fail++; }
    if (sod_codes('ENFORCED: شيء')  !== array())                { echo "  X النثرُ أُنتزعت منه رموز\n"; $fail++; }
    /* **الكاسر**: مفردةٌ فريدةٌ — لو صُنِّفت رمزًا لَمرَّ الفحصُ أخضرَ كاذبًا */
    if (sod_kind('zzq unique probe sentence') !== 'PROSE')      { echo "  X المفردةُ الفريدةُ صُنِّفت رمزًا\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — والتصنيفُ يفرّق الرمزَ عن النثرِ عن الغياب\n";
    exit($fail ? 1 : 0);
}

/* ═══ ③ نافذةُ القياس ════════════════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && $APPLY) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — جمِّدْ أوّلًا.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ④ المقامُ: كلُّ فصلِ واجبٍ حرجٍ في كلِّ موجة ════════════════════════ */
$sod = array(); $tblSeen = array(); $noCol = array();
for ($w = 1; $w <= 16; $w++) {
    $t = "repair01_w{$w}_sod";
    $q = @$conn->query("SHOW COLUMNS FROM `$t`");
    if (!$q) { continue; }
    $cols = array();
    while ($x = $q->fetch_assoc()) { $cols[$x['Field']] = 1; }
    if (!isset($cols['process_key'])) { continue; }
    $tblSeen[] = $t;
    $enf = isset($cols['enforced_by']) ? '`enforced_by`' : "''";
    if (!isset($cols['enforced_by'])) { $noCol[] = "W$w"; }
    $fc  = isset($cols['forbidden_combo']) ? '`forbidden_combo`' : "''";
    $q2 = $conn->query("SELECT `process_key`, $fc AS fc, $enf AS enf FROM `$t` ORDER BY `process_key`");
    while ($x = $q2->fetch_assoc()) {
        $sod[] = array('wave' => "W$w", 'key' => $x['process_key'],
                       'combo' => (string) $x['fc'], 'enf' => (string) $x['enf']);
    }
}
if (!$sod) { exit("⛔ **لا جدولَ فصلِ واجباتٍ واحدًا** — ولا يُقاس على مِصفاةٍ عمياء\n"); }

/* ═══ ⑤ الطرفُ الثاني: الفواحصُ والحواجبُ وحدَها ═════════════════════════
     ⛔ **ولا تُقرأ العُدّةُ كلُّها**: `*_apply.php` يذكر الرمزَ لأنّه بذره،
        وعدُّ ذلك ربطًا **دَورٌ لا قياس** — فالبذرةُ تشهد لنفسِها. */
/* ◆ **والفاحصُ السالبُ فاحصٌ سالبٌ حيثما كان** — و`tests/` موضعُ الفواحصِ في
   هذه الشجرة. ⛔ **وحصرُ البحثِ في `tools/` كان يُعمي المقياسَ عن مجلَّدِ
   الفحصِ نفسِه**: فاحصٌ يسمّي اثنتَين وتسعين عمليّةً لا يُرى لأنّه في مجلَّدِه
   الصحيح. */
$testFiles = array_merge(glob($ROOT . '/tools/*negative*.php'),
                         glob($ROOT . '/tools/*_gate*.php'),
                         glob($ROOT . '/tests/*negative*.php'),
                         glob($ROOT . '/tests/*_gate*.php'));
$TEST = array();
foreach ($testFiles as $f) {
    $s = (string) @file_get_contents($f);
    if ($s !== '') { $TEST[basename($f)] = $s; }
}

/* ═══ ⑤·ب فاحصٌ مقامُه السجلُّ نفسُه — **تغطيةٌ تُثبَت لا تُدَّعى** ══════════
   ◆ **العطبُ**: البحثُ عن **نصِّ المفتاح** في ملفِّ الفاحصِ لا يرى فاحصًا
     **يقرأ السجلَّ ويُقرِّر على كلِّ صفٍّ** — وذاك أقوى من ثنتَين وتسعين سلسلةً
     مكتوبةً باليد، لأنَّ مقامَه **هو السجلُّ نفسُه فلا يتقادم عند إضافةِ عمليّة**.
     ⛔ **فالمقياسُ كان يكافئ النسخَ اليدويَّ ويعاقب القراءةَ من المصدر.**
   ◆ **والتغطيةُ تُثبَت بتشغيلِه**: يُشغَّل الفاحصُ ويُقرأ سطرُه الآليُّ
     `#SODN KEYS=` — ثمَّ **يُتحقَّق من كلِّ مفتاحٍ عضويّةً في السجل**.
   ⛔ **ولا يُقبل ادّعاءُ تغطيةٍ بلا تشغيل**: ملفٌّ يطبع السطرَ نصًّا ولا يُشغَّل
     لا يُعتدُّ به — والسطرُ يُؤخذ من **مخرَجِ التشغيلِ** لا من نصِّ الملفّ. */
$COVER = array();
$php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
foreach ($testFiles as $f) {
    $src = (string) @file_get_contents($f);
    if (strpos($src, '#SODN KEYS=') === false) { continue; }
    $out = array(); $rc = 0;
    @exec('"' . $php . '" ' . escapeshellarg($f) . ' 2>&1', $out, $rc);
    foreach ($out as $ln) {
        if (strncmp($ln, '#SODN KEYS=', 11) !== 0) { continue; }
        foreach (explode(',', substr($ln, 11)) as $k) {
            $k = trim($k);
            if ($k !== '') { $COVER[$k] = basename($f); }
        }
    }
}

/* ═══ ⑥ الربط ════════════════════════════════════════════════════════════ */
$rows = array();
$stat = array('CODE_LIST' => 0, 'PROSE' => 0, 'ABSENT' => 0, 'bound' => 0, 'byRun' => 0, 'byKey' => 0, 'byCode' => 0);
foreach ($sod as $s) {
    $kind = sod_kind($s['enf']);
    $stat[$kind]++;
    $file = ''; $claim = ''; $bound = 0; $how = '';
    /* **الرابطُ الأقوى على الإطلاق: فاحصٌ شُغِّل وأعلن أنّه قرَّر على هذا
       المفتاحِ بعينِه** — والعضويّةُ محقَّقةٌ لا مدَّعاة */
    if ($s['key'] !== '' && isset($COVER[$s['key']])) {
        $file = $COVER[$s['key']]; $bound = 1; $how = 'REGISTRY_DRIVEN'; $stat['byRun']++;
    }
    /* **ثمَّ: مفتاحُ العمليّةِ نصًّا في ملفِّ الفاحص** */
    if (!$bound) { foreach ($TEST as $b => $src) {
        if ($s['key'] !== '' && strpos($src, $s['key']) !== false) {
            $file = $b; $bound = 1; $how = 'PROCESS_KEY'; $stat['byKey']++;
            break;
        }
    } }
    /* **وإن غاب، فرمزُ الردِّ حيث كان رمزًا لا نثرًا** */
    if (!$bound) {
        foreach (sod_codes($s['enf']) as $code) {
            foreach ($TEST as $b => $src) {
                if (strpos($src, $code) !== false) {
                    $file = $b; $bound = 1; $how = 'DENIAL_CODE:' . $code; $stat['byCode']++;
                    break 2;
                }
            }
        }
    }
    if ($bound) {
        $stat['bound']++;
        $claim = 'الفاحصُ `' . $file . '` '
               . ($how === 'REGISTRY_DRIVEN'
                  ? '**شُغِّل وأعلن أنّه قرَّر على** مفتاحِ العمليّةِ `' . $s['key']
                    . '` (‏مقامُه السجلُّ نفسُه فلا يتقادم) — والعضويّةُ محقَّقةٌ لا مدَّعاة'
                  : 'يذكر ' . ($how === 'PROCESS_KEY'
                     ? 'مفتاحَ العمليّةِ `' . $s['key'] . '`'
                     : 'رمزَ الردِّ `' . substr($how, 13) . '`'));
        $wit = 'مربوطٌ بـ' . $how . ' · ' . $claim
             . ' ⛔ **وهذا ادّعاءُ حراسةٍ لا إثباتُ حمرة** — الحمرةُ تُقاس بتشغيلِه · لقطة ' . $sid;
    } else {
        $why = ($kind === 'ABSENT')
            ? '**ولا عمودَ `enforced_by` في جدولِ هذه الموجةِ أصلًا** ⇒ لا رمزَ ردٍّ يُبحث عنه — والعطبُ في سجلِّ الموجةِ لا في الفاحص'
            : (($kind === 'PROSE')
               ? '**و`enforced_by` نثرٌ يصف السلوكَ لا رمزٌ يُبحث عنه** («'
                 . mb_substr(trim($s['enf']), 0, 60) . '…») ⇒ لا مفتاحَ ثانيًا يُجرَّب'
               : '**ورمزُ الردِّ `' . trim($s['enf']) . '` لا يرد في أيِّ فاحصٍ سالبٍ ولا حاجب**');
        $wit = 'غيرُ مربوط · مفتاحُ العمليّةِ `' . $s['key'] . '` لا يرد في أيٍّ من '
             . count($TEST) . ' فاحصًا سالبًا وحاجبًا · ' . $why
             . ' ⇒ **فصلُ واجبٍ حرجٌ بلا فاحصٍ يدَّعيه** · لقطة ' . $sid;
    }
    $rows[] = array('wave' => $s['wave'], 'key' => $s['key'], 'combo' => $s['combo'],
                    'enf' => $s['enf'], 'kind' => $kind, 'file' => $file,
                    'claim' => $claim, 'bound' => $bound, 'wit' => $wit);
}

/* ═══ ⑦ العرض ════════════════════════════════════════════════════════════ */
$N = count($rows);
$pc = $N ? round($stat['bound'] * 100 / $N, 1) : 0;
echo "\n═══ `RPR-02` §١٢·٥ — ربطُ الفاحصِ السالبِ بفصلِ واجبٍ بعينِه ═══\n";
printf("  اللقطة: %s\n\n", $sid);
echo "  ── المقامُ الذي لم يكن ──\n";
printf("     جداولُ فصلِ الواجبات: **%d** موجةً ⇒ **%d** عمليةً حرِجة\n", count($tblSeen), $N);
printf("     وفواحصُ سالبةٌ وحواجبُ على القرص: **%d** ملفًّا\n", count($TEST));
echo "     ⇒ **والعشرون كانت بسطًا موهومًا لمقامٍ غيرِ موجود**\n\n";
echo "  ── `enforced_by` مصنَّفًا — ولا يُوحَّد ──\n";
printf("     `CODE_LIST` %3d — رموزٌ تصلح للبحث\n", $stat['CODE_LIST']);
printf("     `PROSE`     %3d — نثرٌ يصف السلوكَ ⇒ **لا يصلح مفتاحًا**\n", $stat['PROSE']);
printf("     `ABSENT`    %3d — **لا عمودَ في جدولِ الموجةِ أصلًا** (%s)\n",
       $stat['ABSENT'], $noCol ? implode(' · ', $noCol) : '—');
echo "\n  ── الربط ──\n";
printf("     بمفتاحِ العمليّة %3d · برمزِ الردِّ %3d\n", $stat['byKey'], $stat['byCode']);
printf("     **مربوطٌ %d من %d ⇒ %s%%** · وغيرُ مربوطٍ %d — ولكلٍّ شاهدُه\n",
       $stat['bound'], $N, $pc, $N - $stat['bound']);

if ($LIST) {
    echo "\n  ── غيرُ المربوطِ بموجاتِه ──\n";
    $byW = array();
    foreach ($rows as $x) { if (!$x['bound']) { $byW[$x['wave']][] = $x['key']; } }
    foreach ($byW as $w => $ks) {
        printf("   %-5s %2d · %s\n", $w, count($ks), mb_substr(implode(' · ', $ks), 0, 110));
    }
}

/* ═══ ⑧ التثبيت ══════════════════════════════════════════════════════════ */
if ($APPLY) {
    $has = $conn->query("SHOW TABLES LIKE 'repair01_sod_test_registry'");
    if (!$has || !$has->num_rows) {
        exit("⛔ **`repair01_sod_test_registry` غيرُ موجود** — والعُدّةُ لا تُنشئ مخطَّطًا.\n"
           . "   شغِّلْ: php database/migrations/2028_01_02_rpr02_sod_test_registry.php\n");
    }
    $conn->query("DELETE FROM repair01_sod_test_registry");
    $n = 0;
    foreach ($rows as $x) {
        $ok = $conn->query("INSERT INTO repair01_sod_test_registry
              (wave,process_key,forbidden_combo,enforced_raw,enforced_kind,
               test_file,test_claim,bound,witness,snapshot_id,measured_at)
            VALUES ('" . $e($x['wave']) . "','" . $e($x['key']) . "','"
             . $e(mb_substr($x['combo'], 0, 400)) . "','" . $e(mb_substr($x['enf'], 0, 600)) . "','"
             . $e($x['kind']) . "','" . $e($x['file']) . "','" . $e(mb_substr($x['claim'], 0, 400)) . "',"
             . (int) $x['bound'] . ",'" . $e(mb_substr($x['wit'], 0, 600)) . "','" . $e($sid) . "',NOW())");
        if (!$ok) { exit("✘ تعذّر تثبيتُ {$x['wave']}/{$x['key']}: {$conn->error}\n"); }
        $n++;
    }
    $bad = (int) $conn->query("SELECT COUNT(*) FROM repair01_sod_test_registry WHERE witness = ''")->fetch_row()[0];
    printf("\n  ✔ ثُبِّت **%d** فصلَ واجبٍ حرجٍ · صفٌّ بلا شاهدٍ %d\n", $n, $bad);
}

if ($MD) {
    $o  = "# `RPR-02` §١٢·٥ — ربطُ الفاحصِ السالبِ بفصلِ واجبٍ بعينِه\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## المقامُ الذي لم يكن\n\n";
    $o .= "كان المقياسُ **#٥** محجوبًا بنصِّه: *«فواحصُ سالبةٌ على القرص ٢٠ · ولا سجلَّ يربط\n";
    $o .= "فاحصًا بفصلِ واجباتٍ بعينِه ⇒ العددُ لا يصلح بسطًا ولا مقامًا»*. **والحجبُ كان\n";
    $o .= "صحيحًا**: عشرون ملفًّا عددٌ لا يقول أيَّ واجبٍ حُرِس — **وقد تكون العشرون كلُّها\n";
    $o .= "على واجبٍ واحد**.\n\n";
    $o .= "والمقامُ الآن مقيسٌ: **" . count($tblSeen) . "** جدولَ فصلِ واجباتٍ ⇒ **" . $N . "** عمليةً حرِجة.\n\n";
    $o .= "## و`enforced_by` جُرِّب مفتاحًا فسقط\n\n";
    $o .= "| الصنف | العدد | الحكم |\n|---|---:|---|\n";
    $o .= "| `CODE_LIST` | **" . $stat['CODE_LIST'] . "** | رموزٌ تصلح للبحثِ في الفواحص |\n";
    $o .= "| `PROSE` | **" . $stat['PROSE'] . "** | نثرٌ يصف السلوكَ (`ENFORCED:` / `NOT_ENFORCED:`) ⇒ **لا يصلح مفتاحًا** |\n";
    $o .= "| `ABSENT` | **" . $stat['ABSENT'] . "** | **لا عمودَ في جدولِ الموجةِ أصلًا** — " . ($noCol ? implode(' · ', $noCol) : '—') . " |\n\n";
    $o .= "⇒ فمفتاحُ السجلِّ **`process_key`** — الموحَّدُ عبرَ الموجاتِ كلِّها — و`enforced_by`\n";
    $o .= "**يُحفظ كما ورد مصنَّفًا بصنفِه**، فيُقرأ العطبُ ولا يُدهَس.\n";
    $o .= "⛔ **ولا يُوحَّد نثرٌ برمزٍ ليكتمل مفتاح** — «بلا مفتاح» حكمٌ صادق.\n\n";
    $o .= "## المقيس\n\n";
    $o .= "| البند | العدد |\n|---|---:|\n";
    $o .= "| فصولُ واجباتٍ حرِجة | **" . $N . "** |\n";
    $o .= "| فواحصُ سالبةٌ وحواجبُ على القرص | " . count($TEST) . " |\n";
    $o .= "| مربوطٌ بمفتاحِ العمليّة | " . $stat['byKey'] . " |\n";
    $o .= "| مربوطٌ برمزِ الردّ | " . $stat['byCode'] . " |\n";
    $o .= "| **المربوطُ جملةً** | **" . $stat['bound'] . " ⇒ " . $pc . "%** |\n";
    $o .= "| غيرُ المربوطِ — ولكلٍّ شاهدُه | " . ($N - $stat['bound']) . " |\n\n";
    $o .= "## ما لا يزعمه هذا السجلّ\n\n";
    $o .= "- **لا يزعم أنَّ الفاحصَ يحمرُّ فعلًا عند الخرق** — يزعم أنَّ **فاحصًا مسمًّى\n";
    $o .= "  يدَّعي حراسةَ واجبٍ مسمًّى**. والحمرةُ تُقاس بتشغيلِه، **وذاك حاجبٌ ثانٍ**.\n";
    $o .= "- **ولا يُقرأ الربطُ من العُدّةِ كلِّها** — `*_apply.php` يذكر الرمزَ **لأنّه بذره**،\n";
    $o .= "  وعدُّ ذلك ربطًا **دَورٌ لا قياس**: البذرةُ تشهد لنفسِها.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR02_S12_SOD_TESTS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR02_S12_SOD_TESTS.md\n";
}
