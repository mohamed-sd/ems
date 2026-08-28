<?php
/**
 * tools/amd01_phase2_decisions.php — `AMD-01` المرحلة ٢ · المراجعةُ العكسيّةُ للقرارات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `AMD-01` المرحلة ٢ و`MASTER_EXEC` §٤·٢: لكلِّ قرارٍ
 *   `Open`/`Pending`/`Needs Owner Decision` **حكمٌ واحدٌ من ستّة**: `OPEN_VALID` ·
 *   `ALREADY_DECIDED` · `SUPERSEDED` · `CONFIG_PENDING` · `WRONG_BLOCKER_CLASS` ·
 *   `CONFLICT`. **والقبول**: صفرُ قرارٍ مفتوحٍ بلا حكمٍ ومرجع.
 *
 * ◆ **وبدرجةٍ من الستِّ** — `MASTER_EXEC` §٣، **ولكلِّ `CONFIG_PENDING` تُذكر
 *   صراحةً المرحلةُ التي تصير عندها حاجزًا**. ⛔ فالقيمةُ المؤجَّلةُ تُؤجَّل ولا
 *   تُنسى، وقيمةٌ بلا مرحلةٍ تُقرأ كأنّها لا تحجب شيئًا أبدًا.
 *
 * ◆ **والترحيلُ ليس تبديلَ اسم** (§٣): الأحدَ عشرَ موسومةٌ اليومَ بمفرداتِ
 *   `blocker_type` الثلاث — **ويُحكَم على كلٍّ من جديدٍ** بمرجعٍ من نصِّ أمرٍ نافذ.
 *
 * ◆ **والمرجعُ من نصِّ الأمرِ لا من رأيي**: لكلِّ صفٍّ هنا **موضعٌ في `RPR-02`
 *   أو `RPR-03` أو `AMD-01`** يُسمّي المرحلةَ التي تلزم عندها القيمة. ⛔ **ولا
 *   تُكتب قيمةٌ ماليّةٌ ولا زمنيّةٌ من عندي** — المطلوبُ موضعُها لا مقدارُها.
 *
 * التشغيل:
 *   php tools/amd01_phase2_decisions.php            ← يعرض ولا يكتب
 *   php tools/amd01_phase2_decisions.php --apply    ← يكتب الأحكامَ والمراحل
 *   php tools/amd01_phase2_decisions.php --selftest ← سالبٌ يحرِّك العدّاد
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
$SELF  = in_array('--selftest', $argv, true);

/* ═══ الأحكامُ — حكمٌ ودرجةٌ ومرحلةٌ ومرجع ═══════════════════════════════
   ◆ **والدرجةُ الواحدةُ لكلٍّ**: كلُّها `CONFIG_PENDING` بحكمِ §٣ — *«لا يمنع
     التصميمَ ولا بناءَ المحرِّك · ويتحوّل إلى حاجزٍ فقط عند بلوغِ المرحلةِ التي
     تحتاج تلك القيمة»*. **والفارقُ بينها المرحلةُ لا الدرجة.** */
$JUDGE = array(
    'DEC-OPEN-01' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'نافذةُ الظلِّ ثمَّ الإنفاذ — `RPR-03` §٥ الخطوة ١: «وبلا رقمٍ واحدٍ منها لا تُشغَّل نافذةٌ ولا يُنفَّذ منع». '
      . 'وتصير `ENFORCEMENT_BLOCKER` عندها',
        'RPR-03 §٥ · MASTER_EXEC §٤·٧ «بناءُ المحرِّكِ وصحّةُ التوجيهِ وتحديدُ الدورِ المعتمِدِ كلُّها تمضي الآن»'),
    'DEC-OPEN-02' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'نافذةُ الظلِّ ثمَّ الإنفاذ — `Split Guard` يُبنى قابلَ القراءةِ الآن، ويحجب عند قياسِ النافذة',
        'RPR-03 §٥ · MASTER_EXEC §٣ «ابنِ المحرِّكاتِ كلَّها الآنَ واتركْ مواضعَ القيمِ تُقرأ من سجلِّ السياسات»'),
    'DEC-OPEN-04' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'قبولُ الإسقاطاتِ والتقارير — `UAT_BLOCKER` عند التحقّقِ من عرضِ التقاريرِ لا قبلَه. '
      . 'وعملةُ العرضِ إعدادُ تقريرٍ لا بنيةَ دفتر',
        'RPR-02 §٥·٨ الخطوة ١٨ · RPR-03 §٩ الشاشاتُ الذهبيّة'),
    'DEC-OPEN-05' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'تشغيلُ الإغلاقِ الآليِّ للبلاغ — `ENFORCEMENT_BLOCKER` عند تفعيلِ المؤقِّتِ لا عند بنائه',
        'RPR-03 §٤·٢ عقدُ المستهلكِ · MASTER_EXEC §٣'),
    'DEC-OPEN-06' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'تشغيلُ سياسةِ النقدِ قبلَ الإنتاج — `GO_LIVE_BLOCKER`. **وهذا مثالُ `AMD-01` §٥ بعينِه**: '
      . '«حدُّ النثرية: لا يمنع بناءَ محرِّكِ الخزينة · ويمنع تشغيلَ سياسةِ النقدِ قبل الإنتاج»',
        'AMD-01 §٥ — المثالُ المنصوصُ في جدولِ الدرجات'),
    'DEC-OPEN-07' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'إنفاذُ بوّابةِ الاعتمادِ في المشتريات — `ENFORCEMENT_BLOCKER` بعد نافذةِ الظلّ',
        'RPR-03 §٥ · RPR-02 §٧ الخطوة ١٠ «ربطُ الاعتمادِ من محرّكِ السلطةِ لا منطقٍ محليّ»'),
    'DEC-OPEN-08' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'إنفاذُ التصعيدِ في المخاطر — `ENFORCEMENT_BLOCKER` عند تفعيلِ عتبةِ الشهيّة',
        'RPR-03 §٤·٢ الأثرُ التجاريّ · MASTER_EXEC §٣'),
    'DEC-OPEN-09' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'إنفاذُ توجيهِ الطلباتِ إلى صندوقِ الرئيس — `ENFORCEMENT_BLOCKER` عند تفعيلِ قاعدةِ الرفع',
        'RPR-03 §٧ رحلةُ القرار · MASTER_EXEC §٤·٧ سجلُّ القدراتِ المنصّيّة'),
    'DEC-OPEN-10' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'إنفاذُ نافذةِ توثيقِ القرارِ العاجل — `ENFORCEMENT_BLOCKER` عند تفعيلِ المؤقِّت',
        'RPR-03 §٧ رحلةُ القرار · MASTER_EXEC §٣'),
    'DEC-OPEN-11' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'إنفاذُ محرِّكِ التفويض — `ENFORCEMENT_BLOCKER`. **واختبارُ التفويضِ المنتهي** أحدُ خمسةِ اختباراتِ الصلاحيةِ '
      . 'فيصير `UAT_BLOCKER` عند قياسِها',
        'RPR-03 §٦ «واختبرْ: … التفويضَ المنتهي» · RPR-03 §٥'),
    'DEC-OPEN-15' => array('CONFIG_PENDING', 'CONFIG_PENDING',
        'تفعيلُ تتبّعِ الأصنافِ في المخازن — `ENFORCEMENT_BLOCKER` عند بذرِ قائمةِ الفئات. '
      . '**والآليةُ محسومةٌ سلفًا** بـ`DEC-WH-01` والمفتوحُ قائمةُ الفئاتِ لا الآلية',
        'AMD-01 ملحق §ج · DEC-WH-01 (APPROVED)'),
);

/* ⛔ **السالبُ يكسر مفردةً فريدة**: قرارٌ بمرحلةٍ فارغة */
if ($SELF) { $JUDGE['DEC-OPEN-06'][2] = ''; }

$open = array();
$r = $conn->query("SELECT decision_id, domain, blocking_level, blocker_type,
                          amd01_verdict, config_pending_stage
                     FROM repair01_decisions
                    WHERE status = 'NEEDS_OWNER_DECISION' ORDER BY decision_id");
while ($x = $r->fetch_assoc()) { $open[$x['decision_id']] = $x; }

echo "\n═══ `AMD-01` المرحلة ٢ — المراجعةُ العكسيّةُ للقرارات ═══\n";
printf("  القراراتُ كلُّها %d · المفتوحةُ **%d**\n",
       (int) $conn->query("SELECT COUNT(*) FROM repair01_decisions")->fetch_row()[0],
       count($open));

/* ⛔ **المقامُ يُحرَس**: قرارٌ مفتوحٌ لا حكمَ له في الجدولِ **يُرسِّب** — ولا
     يُسكت عنه بأن يُحذف من القائمة. */
$noJudge = array();
foreach ($open as $id => $row) { if (!isset($JUDGE[$id])) { $noJudge[] = $id; } }
$ghost = array();
foreach ($JUDGE as $id => $j) { if (!isset($open[$id])) { $ghost[] = $id; } }

echo "\n  ── الحكمُ والدرجةُ والمرحلة ──\n";
$bad = 0; $rows = array();
foreach ($open as $id => $row) {
    if (!isset($JUDGE[$id])) {
        $bad++; printf("  ⛔ %-14s **بلا حكم**\n", $id); continue;
    }
    list($verdict, $degree, $stage, $ref) = $JUDGE[$id];
    $ok = ($stage !== '' && $ref !== '');
    if (!$ok) { $bad++; }
    $rows[] = array($id, $row['domain'], $row['blocker_type'], $verdict, $degree, $stage, $ref, $ok);
    printf("  %s %-14s %-16s ⇐ %s\n", $ok ? '✔' : '⛔', $id, $verdict, mb_substr($row['blocker_type'], 0, 24));
    printf("       المرحلة: %s\n", $ok ? mb_substr($stage, 0, 92) : '**فارغة — والقيمةُ المؤجَّلةُ بلا مرحلةٍ تُنسى**');
}
foreach ($noJudge as $id) { printf("  ⛔ %-14s مفتوحٌ في الجدولِ وبلا حكمٍ هنا\n", $id); }
foreach ($ghost as $id) { printf("  ◆ %-14s محكومٌ هنا وليس مفتوحًا في الجدولِ — خبرٌ لا خلل\n", $id); }

/* ── الكتابة ─────────────────────────────────────────────────────────── */
if ($APPLY && $bad === 0) {
    $n = 0;
    foreach ($rows as $x) {
        list($id, , , $verdict, $degree, $stage, $ref) = $x;
        $ok = $conn->query("UPDATE repair01_decisions
              SET amd01_verdict = '" . $e($verdict) . "',
                  amd01_verdict_ref = '" . $e($ref) . "',
                  blocking_level = '" . $e($degree) . "',
                  config_pending_stage = '" . $e($stage) . "'
            WHERE decision_id = '" . $e($id) . "'");
        if (!$ok) { exit("✘ تعذّر حكمُ $id: {$conn->error}\n"); }
        $n++;
    }
    printf("\n  ✔ كُتب حكمُ **%d** قرارٍ بدرجتِه ومرحلتِه ومرجعِه\n", $n);
    /* ⛔ **ولا يُصدَّق الكاتبُ على كلمتِه** — يُعاد القراءة */
    $chk = (int) $conn->query("SELECT COUNT(*) FROM repair01_decisions
                                WHERE status='NEEDS_OWNER_DECISION'
                                  AND (amd01_verdict IS NULL OR amd01_verdict_ref=''
                                       OR (blocking_level='CONFIG_PENDING' AND config_pending_stage=''))")
                      ->fetch_row()[0];
    printf("  ✔ أُعيدت القراءة: قرارٌ مفتوحٌ ناقصُ الحكمِ أو المرحلةِ أو المرجع = **%d**\n", $chk);
    $bad += $chk;
} elseif ($APPLY) {
    echo "\n  ⛔ **لم يُكتب شيء** — والحكمُ الناقصُ لا يُثبَّت\n";
}

/* ── القبول ──────────────────────────────────────────────────────────── */
$noLevel = (int) $conn->query("SELECT COUNT(*) FROM repair01_decisions
                                WHERE status='NEEDS_OWNER_DECISION'
                                  AND (blocking_level IS NULL OR blocking_level='')")->fetch_row()[0];
$noVerdict = (int) $conn->query("SELECT COUNT(*) FROM repair01_decisions
                                  WHERE status='NEEDS_OWNER_DECISION' AND amd01_verdict IS NULL")->fetch_row()[0];

echo "\n────────────────────────────────────────────────────────────\n";
printf("**قرارٌ مفتوحٌ بلا حكمٍ من الستّة: %d · بلا درجةِ حجب: %d · بلا مرحلةٍ مع `CONFIG_PENDING`: %d**\n",
       $noVerdict, $noLevel, $bad);
$pass = ($noVerdict === 0 && $noLevel === 0 && $bad === 0);
echo $pass
    ? "🟢 **المرحلةُ الثانيةُ مستوفيةٌ لقبولِها** — والثلاثةُ أصفار\n"
    : "◆ **لم تُستوفَ بعد** — والأصفارُ الثلاثةُ شرطُ `AMD-01` §٨\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $bad >= 1
        ? "🟢 **العدّادُ تحرَّك بقرارٍ نُزعت مرحلتُه — فالفاحصُ يَحمَرُّ فعلًا**\n"
        : "✘ **العدّادُ لم يتحرّك**\n";
    exit($bad >= 1 ? 0 : 1);
}

if ($MD) {
    $o  = "# `AMD-01` المرحلة ٢ — المراجعةُ العكسيّةُ للقرارات\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md`\n";
    $o .= "> **القبول**: صفرُ قرارٍ مفتوحٍ بلا حكمٍ من الستّة · وبلا درجةِ حجب · وبلا بيانِ مرحلتِه.\n\n";
    $o .= "| القرار | المجال | وسمُه السابق | الحكمُ من الستّة | الدرجةُ من الستّ | المرحلةُ التي يصير عندها حاجزًا | مرجعُ الحكم |\n";
    $o .= "|---|---|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        $o .= '| `' . $x[0] . '` | ' . $x[1] . ' | `' . $x[2] . '` | `' . $x[3] . '` | `' . $x[4]
            . '` | ' . $x[5] . ' | ' . $x[6] . " |\n";
    }
    $o .= "\n**قرارٌ مفتوحٌ بلا حكمٍ: " . $noVerdict . " · بلا درجة: " . $noLevel
        . " · بلا مرحلة: " . $bad . "**\n\n";
    $o .= "> ⛔ **ولم تُكتب قيمةٌ ماليّةٌ ولا زمنيّةٌ من عندِ المنفِّذ** — المطلوبُ **موضعُ**\n";
    $o .= "> القيمةِ ومرحلتُها لا **مقدارُها**. والمقاديرُ قرارُ المالكِ وحدَه.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/AMD01_PHASE2_DECISIONS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/AMD01_PHASE2_DECISIONS.md\n";
}
exit($pass ? 0 : 1);
