<?php
/**
 * tools/amd01_phase3_requirements.php — `AMD-01` المرحلة ٣ · المراجعةُ العكسيّةُ للمتطلبات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `MASTER_EXEC` §٤·٣: *«⛔ لا تعتمدْ حالةَ المتطلبِ
 *   القديمة. لكلِّ متطلبٍ حالةٌ فعليّةٌ من ستّ … ⛔ ووجودُ الكودِ ليس إغلاقًا»*.
 *
 * ◆ **والنوعُ يُشتقّ ولا يُختار** — `AMD-01` §٣·١: *«يُشتقّ من نوعِ السطحِ في
 *   الدليلِ المعماريّ … فمن يصنّف بنفسه يستطيع تخفيفَ عبءِ الإثبات بأن يجعل
 *   كلَّ شيءٍ بنيويًّا»*. ⇒ المصدرُ هنا `repair01_screen_registry.surface_kind`
 *   (`SOURCE` ⇐ معاملة · `PROJECTION` ⇐ إسقاطٌ وتقرير)، ⛔ **ولا يُشتقّ نوعٌ
 *   لمتطلبٍ لم يُطابَق سطحُه** — فالنوعُ بلا سطحٍ اختيارٌ مقنَّع.
 *
 * ◆ **والعائقُ المقيسُ يُسمّى ولا يُتجاوَز**: المطابقةُ اليومَ **بالاسمِ لا
 *   بالمعرِّف**، و`RPR-02` §٤·١ ينهى عن قياسِ تغطيةٍ على كونَين. وقد قِست:
 *   **١٥٤ متطلبًا من ٤٣٣ لا يجد اسمُه مقابلًا** في `repair01_target_gaps` ولا
 *   في `repair01_screen_registry`. ⛔ **وغيابُ الاسمِ ليس غيابَ السطح** — فمن
 *   حكم عليها `NOT_IMPLEMENTED` بنى عجزًا من طرحِ عددَين، وهو ما يمنعه
 *   `RPR-02` §١١ نصًّا.
 *   ⇒ **تُوسَم `UNMATCHED_PENDING_RECONCILIATION`** ويُعلَن عددُها في كلِّ
 *   تقرير: `Track AMD-01 phase 3 blocked at stage: RPR-02 §٤·١ مصالحةُ المعرِّفات`.
 *
 * ◆ **والحالةُ من قياسٍ لا من ظنّ**:
 *   · مقابلُه صفٌّ في `repair01_target_gaps` (هدفٌ لم يُبنَ) ⇒ `NOT_IMPLEMENTED`.
 *   · مقابلُه سطحٌ حيٌّ في السجل ⇒ `IMPLEMENTED_NOT_VERIFIED` ⛔ **لا
 *     `EVIDENCE_CLOSED`** — فوجودُ الكودِ ليس إغلاقًا، والإغلاقُ بعقدِ إثباتِ
 *     نوعِه وحدَه.
 *   · وفي الحالتَين معًا ⇒ `PARTIALLY_IMPLEMENTED` — بعضُه مبنيٌّ وبعضُه هدف.
 *
 * التشغيل:
 *   php tools/amd01_phase3_requirements.php            ← يقيس ولا يكتب
 *   php tools/amd01_phase3_requirements.php --apply    ← يكتب الأحكام
 *   php tools/amd01_phase3_requirements.php --selftest ← سالبٌ يحرِّك العدّاد
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

/* ═══ اللقطة — ولا حكمَ بلا بصمة ═════════════════════════════════════════ */
$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && !$SELF) {
    exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — وحكمٌ بلا لقطةٍ لا يُعرَف أيَّ نسخةٍ يمثّل.\n");
}
$sid = $snap ? $snap['snapshot_id'] : 'SELFTEST';

/* ═══ عقودُ الإثباتِ الخمسة — `AMD-01` §٣·١ ══════════════════════════════
   ⛔ **ولا يُطالَب نوعٌ بعقدِ غيرِه.** */
$CONTRACT = array(
 'STRUCTURAL' => 'مصالحةٌ قاطعةٌ **بالمعرِّف** · ودليلٌ مقيس · واختبارُ انحدارٍ أو سالبٍ إن انطبق. '
               . '⛔ ولا يُطالَب بأثرٍ تجاريٍّ ولا برحلةٍ بشريّة',
 'TRANSACTION' => 'مسارٌ موجبٌ ناجح · **ومسارٌ سالبٌ يفشل فعلًا** · وآلةُ حالةٍ محكومة · '
               . 'وفصلُ واجبات · وسلطةُ اعتمادٍ صحيحة · وتحقّقٌ بشريٌّ على مستوى الشاشةِ إن انطبق',
 'EVENT_INTEGRATION' => 'الحدثُ صادر · **والمستهلكُ استلمه** · **والأثرُ المقصودُ وقع** · '
               . 'ومنعُ التكرارِ مثبَت · والفشلُ يظهر ويُعاد أو يُعوَّض',
 'PROJECTION_REPORT' => 'نسبُ المصدرِ صحيح · وصلاحيةُ العرضِ محروسة · وإظهارُ الحقولِ الحسّاسةِ '
               . 'بسياستِها · **وتحقّقُ عرضٍ فعليّ**',
 'CROSS_JOURNEY' => '**أدوارٌ بشريّةٌ حقيقيّة** · وتسليمٌ صحيحٌ بين النطاقات · '
               . '**وأثرٌ نهائيٌّ مقصودٌ مقيس**',
);

/* ═══ القياس ═════════════════════════════════════════════════════════════ */
$reqs = array();
$r = $conn->query("SELECT requirement_id, unit, surface, grain, wave FROM repair01_requirements
                    ORDER BY requirement_id");
while ($x = $r->fetch_assoc()) { $reqs[] = $x; }

/* ═══ المصدرُ الواحد: كونُ الأهدافِ الموحَّد ═══════════════════════════════
   ◆ **ولا يُعاد اشتقاقُ المطابقةِ هنا**: كانت هذه الأداةُ تُطابق بالاسمِ من
     عندِها، و`rpr02_target_universe.php` يُطابق من عندِه — **عدّادان في
     ملفَّين يتفرّقان حتمًا** (‏الدرسُ `counter-parity-two-readers`). ⇒ القارئُ
     واحدٌ الآن: `repair01_target_universe` وحدَه، **والحكمُ يتبع حكمَ الكون**.
   ⛔ **وهدفٌ بلا حكمٍ في الكونِ يبقى بلا حالةٍ هنا** — ولا يُترجَم غيابُ الحكمِ
     إلى `NOT_IMPLEMENTED`. */
$UNI = array();
$r = $conn->query("SELECT requirement_id, verdict, screen_id, gap_id, match_method,
                          verdict_witness, match_witness
                     FROM repair01_target_universe WHERE requirement_id <> ''");
while ($x = $r->fetch_assoc()) { $UNI[$x['requirement_id']] = $x; }
if (!$UNI) {
    exit("⛔ **كونُ الأهدافِ غيرُ مبنيّ** — شغِّلْ أوّلًا:\n"
       . "   php tools/rpr02_target_universe.php --apply\n");
}
/* نوعُ السطحِ من الدليلِ المعماريّ — لمن طوبق سطحُه وحدَه */
$kindOf = array();
$r = $conn->query("SELECT screen_id, surface_kind FROM repair01_screen_registry
                    WHERE surface_kind <> ''");
while ($x = $r->fetch_assoc()) { $kindOf[$x['screen_id']] = $x['surface_kind']; }
/* خريطةُ نوعِ السطحِ ⇐ نوعِ المتطلب — `AMD-01` §٣·١
   ⛔ **ولا يُختار النوعُ بالاجتهاد**: `SOURCE` سطحٌ مصدريٌّ يكتب حقيقةً ⇒ معاملة ·
      و`PROJECTION` يعرض من مالكٍ آخر ⇒ إسقاطٌ وتقرير. وما لا سطحَ له لا نوعَ له. */
$KIND2TYPE = array('SOURCE' => 'TRANSACTION', 'PROJECTION' => 'PROJECTION_REPORT');

$out = array();
$cnt = array('EVIDENCE_CLOSED' => 0, 'IMPLEMENTED_NOT_VERIFIED' => 0,
             'PARTIALLY_IMPLEMENTED' => 0, 'INCORRECTLY_IMPLEMENTED' => 0,
             'NOT_IMPLEMENTED' => 0, 'NOT_APPLICABLE' => 0);
$unmatched = 0; $typed = 0; $untyped = 0;

foreach ($reqs as $q) {
    $id = $q['requirement_id'];
    $u  = isset($UNI[$id]) ? $UNI[$id] : null;
    if ($u === null || $u['verdict'] === null) {
        $unmatched++;
        $out[] = array($q, null, null, 'UNMATCHED_PENDING_RECONCILIATION',
            ($u === null
              ? 'لا صفَّ له في كونِ الأهدافِ الموحَّد'
              : 'في الكونِ بلا حكمٍ بعد (' . $u['match_method'] . ') — ' . $u['match_witness']));
        continue;
    }
    if ($u['verdict'] === 'NOT_BUILT') {
        $state = 'NOT_IMPLEMENTED';
        $ev = 'حكمُ الكون `NOT_BUILT` · ' . $u['verdict_witness'];
    } elseif ($u['verdict'] === 'MATCHED') {
        /* ⛔ **وجودُ الكودِ ليس إغلاقًا** — الحيُّ يقف عند «منفَّذٌ ولم يُثبت» */
        $state = 'IMPLEMENTED_NOT_VERIFIED';
        $ev = 'حكمُ الكون `MATCHED` **وبلا عقدِ إثباتٍ مستوفًى** · ' . $u['verdict_witness'];
    } else {
        $state = 'PARTIALLY_IMPLEMENTED';
        $ev = 'حكمُ الكون `' . $u['verdict'] . '` · ' . $u['verdict_witness'];
    }
    $kind = ($u['screen_id'] !== '' && isset($kindOf[$u['screen_id']])) ? $kindOf[$u['screen_id']] : '';
    $type = isset($KIND2TYPE[$kind]) ? $KIND2TYPE[$kind] : null;
    if ($type === null) { $untyped++; } else { $typed++; }
    $cnt[$state]++;
    $out[] = array($q, $state, $type, 'MATCHED_BY_ID', $ev);
}
/* ⛔ **السالبُ يكسر مفردةً فريدة** */
if ($SELF) { $unmatched++; }

echo "\n═══ `AMD-01` المرحلة ٣ — المراجعةُ العكسيّةُ للمتطلبات ═══\n";
printf("  اللقطة: %s\n", $sid);
printf("  المتطلباتُ في الدفتر: **%d**\n\n", count($reqs));
echo "  ── الحالاتُ الستّ ──\n";
foreach ($cnt as $k => $v) { printf("     %-26s %4d\n", $k, $v); }
printf("     %-26s %4d  ⛔ **محجوبٌ على مصالحةِ المعرِّفات**\n",
       'UNMATCHED (بلا حكم)', $unmatched);
printf("\n  ── النوعُ مشتقًّا من الدليلِ المعماريّ ──\n");
printf("     مُشتقٌّ من `surface_kind`: %d · وبلا نوعٍ (‏لا سطحَ مطابَقًا): %d\n", $typed, $untyped);
echo "     ⛔ **ولا يُشتقّ نوعٌ لمتطلبٍ لم يُطابَق سطحُه** — فالنوعُ بلا سطحٍ اختيارٌ مقنَّع\n";

if ($APPLY) {
    $n = 0;
    foreach ($out as $x) {
        list($q, $state, $type, $ident, $ev) = $x;
        $contract = ($type !== null && isset($CONTRACT[$type])) ? $CONTRACT[$type] : '';
        $ok = $conn->query("UPDATE repair01_requirements
              SET amd01_state = " . ($state === null ? 'NULL' : "'" . $e($state) . "'") . ",
                  requirement_type = " . ($type === null ? 'NULL' : "'" . $e($type) . "'") . ",
                  proof_contract = '" . $e($contract) . "',
                  state_evidence = '" . $e($ev) . "',
                  identity_status = '" . $e($ident) . "',
                  state_at = NOW(),
                  state_snapshot = '" . $e($sid) . "'
            WHERE requirement_id = '" . $e($q['requirement_id']) . "'");
        if (!$ok) { exit("✘ تعذّر حكمُ {$q['requirement_id']}: {$conn->error}\n"); }
        $n++;
    }
    printf("\n  ✔ كُتب حكمُ **%d** متطلبٍ بحالتِه ونوعِه وعقدِه ودليلِه ولقطتِه\n", $n);
    /* ⛔ ولا يُصدَّق الكاتبُ على كلمتِه */
    $back = (int) $conn->query("SELECT COUNT(*) FROM repair01_requirements
                                 WHERE amd01_state IS NULL
                                   AND identity_status <> 'UNMATCHED_PENDING_RECONCILIATION'")
                        ->fetch_row()[0];
    printf("  ✔ أُعيدت القراءة: متطلبٌ مطابَقٌ وبلا حالة = **%d**\n", $back);
    $noEv = (int) $conn->query("SELECT COUNT(*) FROM repair01_requirements
                                 WHERE amd01_state IS NOT NULL AND state_evidence = ''")
                       ->fetch_row()[0];
    printf("  ✔ ومحكومٌ بلا دليلٍ يسمّي قياسَه = **%d**\n", $noEv);
}

echo "\n────────────────────────────────────────────────────────────\n";
$judged = count($reqs) - $unmatched + ($SELF ? 1 : 0);
printf("**محكومٌ %d · محجوبٌ على المصالحةِ %d · المقام %d**\n",
       count($reqs) - $unmatched, $unmatched, count($reqs));
echo $unmatched === 0
    ? "🟢 **كلُّ متطلبٍ له حكمٌ ودليل**\n"
    : "◆ `Track AMD-01 phase 3 blocked at stage: RPR-02 §٤·١ مصالحةُ المعرِّفات` —\n"
    . "  ⛔ **ولا يُحكَم على المحجوبِ بالغياب**: عجزٌ من طرحِ عددَين يمنعه `RPR-02` §١١.\n"
    . "  والباقي مضى ولم يُوقفه هذا المحجوب.\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $unmatched >= 1
        ? "🟢 **العدّادُ يتحرّك بالمحجوبِ — والفاحصُ لا يبتلعه صامتًا**\n"
        : "✘ **العدّادُ لم يتحرّك**\n";
    exit($unmatched >= 1 ? 0 : 1);
}

if ($MD) {
    $o  = "# `AMD-01` المرحلة ٣ — المراجعةُ العكسيّةُ للمتطلبات\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md`\n";
    $o .= "> **اللقطة**: `" . $sid . "` · **الالتزام**: `" . ($snap ? $snap['commit_hash'] : '—') . "`\n\n";
    $o .= "## الحالاتُ الستّ\n\n| الحالة | العدد |\n|---|---|\n";
    foreach ($cnt as $k => $v) { $o .= '| `' . $k . '` | **' . $v . "** |\n"; }
    $o .= '| ⛔ `UNMATCHED_PENDING_RECONCILIATION` | **' . $unmatched . "** |\n";
    $o .= "\n**المقام " . count($reqs) . " · محكومٌ " . (count($reqs) - $unmatched)
        . " · محجوبٌ " . $unmatched . "**\n\n";
    $o .= "## لماذا المحجوبُ محجوبٌ ولا يُحكَم عليه بالغياب\n\n";
    $o .= "المطابقةُ اليومَ **بالاسمِ لا بالمعرِّف**، و`RPR-02` §٤·١ ينهى عن قياسِ تغطيةٍ على\n";
    $o .= "كونَين. ومتطلبٌ لا يجد اسمُه مقابلًا **قد يكون مبنيًّا باسمٍ آخر**. فالحكمُ عليه\n";
    $o .= "بـ`NOT_IMPLEMENTED` عجزٌ من طرحِ عددَين — و`RPR-02` §١١ يمنعه نصًّا.\n\n";
    $o .= "`Track AMD-01 phase 3 blocked at stage: RPR-02 §٤·١ مصالحةُ المعرِّفات`\n\n";
    $o .= "## عقودُ الإثباتِ الخمسة — ولا يُطالَب نوعٌ بعقدِ غيرِه\n\n";
    $o .= "| النوع | عقدُ إثباتِه |\n|---|---|\n";
    foreach ($CONTRACT as $k => $v) { $o .= '| `' . $k . '` | ' . $v . " |\n"; }
    $o .= "\n**النوعُ مشتقٌّ من `surface_kind`: " . $typed . " · وبلا نوعٍ: " . $untyped . "**\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/AMD01_PHASE3_REQUIREMENTS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/AMD01_PHASE3_REQUIREMENTS.md\n";
}
