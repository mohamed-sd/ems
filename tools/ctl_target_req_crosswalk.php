<?php
/**
 * tools/ctl_target_req_crosswalk.php — أمرُ الضبطِ §٤ · جسرُ الأهدافِ والمتطلبات
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه**: *«يُنشأ TARGET_REQUIREMENT_CROSSWALK بين كونِ الأهدافِ
 *   وكونِ المتطلبات. ولا يُستدلُّ من تشابهِ الأعداد. تُسمح علاقةُ متعدّدٍ
 *   لمتعدّد. معيارُ القبول: UNEXPLAINED_UNMAPPED = 0»*.
 *
 * ◆ **والجسرُ قائمٌ في المخزنِ لا يُخترع ثانيةً**: `repair01_target_universe`
 *   يحمل `requirement_id` لكلِّ هدفٍ جُسر، و`verdict`+`verdict_witness`
 *   تصريفًا موثَّقًا لمن لم يُجسَر. ⛔ **فبناءُ جدولِ جسرٍ ثانٍ قارئان
 *   يتفرّقان** — هذه الأداةُ **تقيس** اكتمالَ الجسرِ باتجاهَيه وتُسمّي كلَّ
 *   غيرِ مفسَّرٍ باسمِه.
 *
 * ◆ **الاتجاهان**:
 *   · **هدفٌ ⇒ متطلب**: لكلِّ هدفٍ إمّا `requirement_id` قائمٌ في الدفترِ
 *     وإمّا **تصريفٌ موثَّق** (`verdict` بشاهدِه). وما عداهما `UNEXPLAINED`.
 *   · **متطلبٌ ⇒ هدف**: لكلِّ متطلبٍ هدفٌ واحدٌ فأكثرُ أو سببٌ موثَّق.
 *
 * ⛔ **والعلاقةُ المقيسةُ اليومَ ١:١ (٤٣٣↔٤٣٣) — وهذا خبرٌ لا قيد**: المتعدّدُ
 *   لمتعدّدٍ مسموحٌ متى ولّده البناءُ، والأداةُ تعدُّه لا تمنعه.
 *
 * التشغيل: php tools/ctl_target_req_crosswalk.php [--md] [--selftest]
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

$MD   = in_array('--md', $argv, true);
$SELF = in_array('--selftest', $argv, true);
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : (int) $x[0];
};
$snap = '';
$r = $conn->query("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && ($x = $r->fetch_row())) { $snap = $x[0]; }

/* ═══ ① هدفٌ ⇒ متطلب ════════════════════════════════════════════════════ */
$tgtAll = $one("SELECT COUNT(*) FROM repair01_target_universe");
$tgtMapped = $one("SELECT COUNT(*) FROM repair01_target_universe u
                    WHERE COALESCE(u.requirement_id,'') <> ''
                      AND EXISTS(SELECT 1 FROM repair01_requirements r WHERE r.requirement_id = u.requirement_id)");
$tgtDanglingReq = $one("SELECT COUNT(*) FROM repair01_target_universe u
                    WHERE COALESCE(u.requirement_id,'') <> ''
                      AND NOT EXISTS(SELECT 1 FROM repair01_requirements r WHERE r.requirement_id = u.requirement_id)");
/* تصريفٌ موثَّق = بلا متطلبٍ لكن بحكمٍ وشاهدٍ غيرِ فارغَين */
$tgtDisposed = $one("SELECT COUNT(*) FROM repair01_target_universe
                      WHERE COALESCE(requirement_id,'') = ''
                        AND COALESCE(verdict,'') <> '' AND COALESCE(verdict_witness,'') <> ''");
$tgtUnexplained = $one("SELECT COUNT(*) FROM repair01_target_universe
                      WHERE COALESCE(requirement_id,'') = ''
                        AND (COALESCE(verdict,'') = '' OR COALESCE(verdict_witness,'') = '')");
/* ⚠ مرجعُ فجوةٍ متقادم — خبرُ سلامةٍ لا فجوةُ جسر */
$staleGap = $one("SELECT COUNT(*) FROM repair01_target_universe u
                   WHERE COALESCE(u.gap_id,'') <> ''
                     AND NOT EXISTS(SELECT 1 FROM repair01_target_gaps g WHERE g.id = u.gap_id + 0)");

/* ═══ ② متطلبٌ ⇒ هدف ════════════════════════════════════════════════════ */
$reqAll = $one("SELECT COUNT(*) FROM repair01_requirements");
$reqMapped = $one("SELECT COUNT(*) FROM repair01_requirements r
                    WHERE EXISTS(SELECT 1 FROM repair01_target_universe u WHERE u.requirement_id = r.requirement_id)");
$reqUnmapped = $reqAll - $reqMapped;
$reqMulti = $one("SELECT COUNT(*) FROM (SELECT requirement_id FROM repair01_target_universe
                   WHERE COALESCE(requirement_id,'') <> '' GROUP BY requirement_id HAVING COUNT(*) > 1) x");

$UNEXPLAINED = $tgtUnexplained + $tgtDanglingReq + $reqUnmapped;

/* ═══ الاختبارُ السالب ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if ($tgtAll < 100 || $reqAll < 100) { echo "  X مقامٌ لم يُقرأ\n"; $fail++; }
    if ($tgtMapped + $tgtDanglingReq + $tgtDisposed + $tgtUnexplained !== $tgtAll) {
        echo "  X تفكيكُ الأهدافِ لا يساوي مقامَه\n"; $fail++;
    }
    /* **الكاسر**: متطلبٌ وهميٌّ داخلَ معاملةٍ تُلغى يجب أن يظهر غيرَ مجسور */
    $conn->query('START TRANSACTION');
    $conn->query("INSERT INTO repair01_requirements (requirement_id, wave, unit, surface, grain, source_of_truth)
                  VALUES ('ZZQ-PROBE-1','W99','DEP-01','مجسُّ فحصٍ سالب','probe','probe')");
    $u2 = $one("SELECT COUNT(*) FROM repair01_requirements r
                 WHERE NOT EXISTS(SELECT 1 FROM repair01_target_universe u WHERE u.requirement_id = r.requirement_id)");
    $conn->query('ROLLBACK');
    if ($u2 !== $reqUnmapped + 1) { echo "  X المجسُّ لم يحرّك عدّادَ غيرِ المجسور ($reqUnmapped ⇒ $u2)\n"; $fail++; }
    else { echo "  ◆ العدّادُ تحرّك بالمجسِّ +" . ($u2 - $reqUnmapped) . " ثمَّ عاد بالإلغاء\n"; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — التفكيكُ يساوي المقامَ والعدّادُ يتحرّك\n";
    exit($fail ? 1 : 0);
}

/* ═══ العرض ═════════════════════════════════════════════════════════════ */
echo "\n═══ أمرُ الضبطِ §٤ — `TARGET_REQUIREMENT_CROSSWALK` مقيسًا باتجاهَيه ═══\n";
printf("  اللقطة %s\n\n", $snap !== '' ? $snap : 'DRY');
echo "  ── هدفٌ ⇒ متطلب (المقام $tgtAll) ──\n";
printf("     مجسورٌ بمتطلبٍ قائم              **%4d**\n", $tgtMapped);
printf("     مصرَّفٌ بحكمٍ وشاهدٍ موثَّقَين      **%4d** (‏أشباحُ `THEIRS` `NOT_BUILT` بشاهدِ W00)\n", $tgtDisposed);
printf("     ⛔ متطلبُه لا وجودَ له في الدفتر   **%4d**\n", $tgtDanglingReq);
printf("     ⛔ بلا متطلبٍ ولا تصريف           **%4d**\n", $tgtUnexplained);
echo "\n  ── متطلبٌ ⇒ هدف (المقام $reqAll) ──\n";
printf("     له هدفٌ واحدٌ فأكثر               **%4d**\n", $reqMapped);
printf("     ⛔ بلا هدفٍ ولا سبب               **%4d**\n", $reqUnmapped);
printf("     متطلبٌ بأكثرَ من هدفٍ (متعدّدٌ مسموح) %4d\n", $reqMulti);
echo "\n────────────────────────────────────────────────────────────\n";
printf("**`UNEXPLAINED_UNMAPPED` = %d** — والقبولُ صفر %s\n", $UNEXPLAINED, $UNEXPLAINED === 0 ? '🟢' : '✘');
printf("⚠ خبرُ سلامةٍ لا فجوةُ جسر: **%d** هدفًا مرجعُ فجوتِه (`gap_id`) متقادمٌ — دفترُ الفجواتِ أُعيد بناؤه بعدَ الجسرِ، والحكمُ والشاهدُ **على الهدفِ نفسِه** فلا يتيتَّم\n", $staleGap);
echo "⛔ **ولا يُستدلُّ من تشابهِ الأعداد**: العلاقةُ المقيسةُ اليومَ ١:١ ($tgtMapped↔$reqMapped) — والمتعدّدُ مسموحٌ متى ولّده البناء.\n";

if ($MD) {
    $o  = "# أمرُ الضبطِ §٤ — `TARGET_REQUIREMENT_CROSSWALK`\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `$snap`\n\n";
    $o .= "**الجسرُ قائمٌ في `repair01_target_universe` ولا يُبنى ثانيةً** — قارئان يتفرّقان.\n\n";
    $o .= "| الاتجاه | المقام | مجسورٌ/مفسَّر | ⛔ غيرُ مفسَّر |\n|---|---:|---|---:|\n";
    $o .= "| هدفٌ ⇒ متطلب | $tgtAll | مجسورٌ $tgtMapped · مصرَّفٌ موثَّقًا $tgtDisposed | **" . ($tgtUnexplained + $tgtDanglingReq) . "** |\n";
    $o .= "| متطلبٌ ⇒ هدف | $reqAll | له هدفٌ $reqMapped | **$reqUnmapped** |\n\n";
    $o .= "## معيارُ القبول\n\n**`UNEXPLAINED_UNMAPPED` = $UNEXPLAINED** — والقبولُ صفر " . ($UNEXPLAINED === 0 ? '🟢 **متحقِّق**' : '✘') . "\n\n";
    $o .= "⚠ **خبرُ سلامة**: $staleGap هدفًا `gap_id` فيه متقادمٌ (دفترُ الفجواتِ أُعيد بناؤه) — والحكمُ على الهدفِ نفسِه فلا أثرَ للتقادمِ على الجسر.\n\n";
    $o .= "⛔ العلاقةُ المقيسةُ ١:١ والمتعدّدُ لمتعدّدٍ مسموحٌ متى ولّده البناء — يُعدُّ ولا يُمنع (اليوم: $reqMulti).\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/CTL_TARGET_REQ_CROSSWALK.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/CTL_TARGET_REQ_CROSSWALK.md\n";
}
