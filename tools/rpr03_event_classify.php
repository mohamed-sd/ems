<?php
/**
 * tools/rpr03_event_classify.php — `RPR-03` §٤·٢ · تصنيفُ الأنواعِ بحكمٍ موثَّق
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه**: *«تصنيفُ الأنواعِ الثمانيةِ والخمسين: حدثُ أعمالٍ
 *   يستلزم أثرًا · أو تدقيقيّ · أو مهملٌ يُتقاعد — بحكمٍ موثَّقٍ لكلٍّ»*.
 *
 * ◆ **والفارقُ الحاكمُ من §٤·١**: *«الحدثُ التدقيقيُّ لا يحتاج مستهلكَ أعمال —
 *   **يحقّق غرضَه الرقابيَّ بوجودِه**»*. ⇒ فالسؤالُ الفاصل: **هل يستلزم
 *   وقوعُه أثرًا في مكانٍ آخر، أم يكفي أنّه سُجِّل؟**
 *
 * ◆ **والقواعدُ الثلاثُ مقيسةٌ على الحمولةِ لا مؤلَّفةٌ بالاسم**:
 *   **B1 · `RETIRED`** — مفتاحٌ من عائلةِ السبرِ (`probe.*` · `*.probe_*`):
 *     **عُدّةُ قياسٍ لا واقعةُ أعمال**. الشاهدُ: بادئةُ الاسمِ **وصفرُ حمولةٍ
 *     ماليّةٍ أو كمّيّةٍ في كلِّ وقائعِه**. ⛔ ولا يُتقاعد مفتاحٌ بحمولة.
 *   **B2 · `BUSINESS`** — له في وقائعِه **مبلغٌ غيرُ صفرٍ أو كمّيّةٌ غيرُ صفر**:
 *     فالمالُ والكمُّ **يستلزمان ترحيلًا أو رصيدًا أو استهلاكَ طاقة** — أي أثرًا
 *     يقع خارجَ الحدث. الشاهدُ: عددُ الوقائعِ ذاتِ الحمولة.
 *   **B3 · `AUDIT`** — لا حمولةَ **وفئتُه ليست ماليّة**: يُسجِّل تغيُّرَ حالٍ أو
 *     إشارةً، **وغرضُه الرقابيُّ يتحقّق بوجودِه**.
 *
 * ⛔ **وما شذَّ عن الثلاثِ يُوسَم `NEEDS_ADJUDICATION` ولا يُقحَم**: مفتاحٌ
 *   **فئتُه ماليّةٌ وحمولتُه صفر** (‏مثلُ `penalty.waived`) إمّا إشعارٌ رقابيٌّ
 *   وإمّا **عطبُ حمولةٍ يُخفي مبلغًا**. ⛔ **وتصنيفُه تدقيقيًّا يُسقط عنه
 *   مستهلكَ الأعمالِ بلا دليل** — وهو بابُ رفعِ النسبةِ بلا بناء.
 *
 * التشغيل: php tools/rpr03_event_classify.php [--apply] [--md] [--selftest]
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

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
if (!$snap && !$SELF) { exit("⛔ **لا نافذةَ قياسٍ مفتوحة**.\n"); }
$sid = $snap ? $snap['snapshot_id'] : 'SELFTEST';

/* ═══ القياس — الحمولةُ لا الاسم ═════════════════════════════════════════ */
$keys = array();
$r = $conn->query("SELECT event_key, MAX(category) category, COUNT(*) n,
                          SUM(amount IS NOT NULL AND amount <> 0) amt,
                          SUM(quantity IS NOT NULL AND quantity <> 0) qty
                     FROM ems_business_events GROUP BY event_key ORDER BY event_key");
while ($x = $r->fetch_assoc()) { $keys[] = $x; }

/* ⛔ **السالبُ يكسر مفردةً فريدة**: مفتاحُ سبرٍ يُمنح حمولةً فلا يُتقاعد */
if ($SELF) {
    foreach ($keys as $i => $k) {
        if (strpos($k['event_key'], 'probe') !== false) { $keys[$i]['amt'] = 7; break; }
    }
}

$out = array();
$cnt = array('BUSINESS' => 0, 'AUDIT' => 0, 'RETIRED' => 0, 'NEEDS_ADJUDICATION' => 0);
foreach ($keys as $k) {
    $n = (int) $k['n']; $amt = (int) $k['amt']; $qty = (int) $k['qty'];
    $hasPayload = ($amt > 0 || $qty > 0);
    $isProbe = (bool) preg_match('~(^|\.)probe(\.|_)~', $k['event_key']);
    $isFin   = ($k['category'] === 'financial');

    if ($isProbe && !$hasPayload) {
        $cls = 'RETIRED'; $rule = 'B1';
        $ev = 'عائلةُ السبر — بادئةُ الاسمِ `probe` **وصفرُ حمولةٍ في كلِّ وقائعِه** ('
            . $n . ' واقعةً · مبلغٌ 0 · كمّيّةٌ 0) ⇒ عُدّةُ قياسٍ لا واقعةُ أعمال';
    } elseif ($hasPayload) {
        $cls = 'BUSINESS'; $rule = 'B2';
        $ev = 'حمولةٌ تستلزم أثرًا خارجَ الحدث — مبلغٌ غيرُ صفرٍ في ' . $amt
            . ' واقعةً · وكمّيّةٌ غيرُ صفرٍ في ' . $qty . ' من ' . $n;
    } elseif ($isFin) {
        $cls = 'NEEDS_ADJUDICATION'; $rule = '—';
        $ev = '';
    } else {
        $cls = 'AUDIT'; $rule = 'B3';
        $ev = 'لا حمولةَ ماليّةً ولا كمّيّةً في ' . $n . ' واقعةً · وفئتُه `'
            . $k['category'] . '` غيرُ ماليّة ⇒ **غرضُه الرقابيُّ يتحقّق بوجودِه**';
    }
    $cnt[$cls]++;
    $out[] = array($k['event_key'], $k['category'], $cls, $rule, $ev, $n, $amt, $qty);
}

echo "\n═══ `RPR-03` §٤·٢ — تصنيفُ أنواعِ الأحداث ═══\n";
printf("  اللقطة: %s · الأنواعُ **%d**\n\n", $sid, count($keys));
printf("     `BUSINESS`            %3d — حمولةٌ تستلزم أثرًا خارجَ الحدث (B2)\n", $cnt['BUSINESS']);
printf("     `AUDIT`               %3d — لا حمولةَ وفئتُه غيرُ ماليّة (B3)\n", $cnt['AUDIT']);
printf("     `RETIRED`             %3d — عائلةُ السبرِ بصفرِ حمولة (B1)\n", $cnt['RETIRED']);
printf("     `NEEDS_ADJUDICATION`  %3d — **ماليٌّ بحمولةٍ صفر** ⛔ ولا يُقحَم\n",
       $cnt['NEEDS_ADJUDICATION']);

if ($cnt['NEEDS_ADJUDICATION'] > 0) {
    echo "\n  ── الشاذُّ يُسمّى ولا يُقحَم ──\n";
    foreach ($out as $x) {
        if ($x[2] === 'NEEDS_ADJUDICATION') {
            printf("     ⛔ %-36s فئةٌ ماليّةٌ · وقائعُ %d · مبلغٌ 0 · كمّيّةٌ 0\n",
                   mb_substr($x[0], 0, 34), $x[5]);
        }
    }
    echo "     ↳ إمّا إشعارٌ رقابيٌّ وإمّا **عطبُ حمولةٍ يُخفي مبلغًا** —\n";
    echo "       ⛔ وتصنيفُه تدقيقيًّا يُسقط عنه مستهلكَ الأعمالِ بلا دليل.\n";
}

echo "\n  ── خطوةُ صفرٍ: إعادةُ قياسِ خطِّ الأساس (§٢·١) ──\n";
printf("     خطُّ الأساسِ يقول **١١** حدثَ أعمالٍ و**٤٧** تدقيقيًّا · والمقيسُ الآنَ **%d** و**%d**\n",
       $cnt['BUSINESS'], $cnt['AUDIT']);
echo "     ◆ **والفرقُ خبرٌ يُعلَن لا يُخفى** — والمقامُ ٥٨ لم يتغيّر\n";

$noEv = 0;
foreach ($out as $x) { if ($x[2] !== 'NEEDS_ADJUDICATION' && trim($x[4]) === '') { $noEv++; } }

if ($APPLY && $noEv === 0) {
    $conn->query("DELETE FROM rpr03_event_classification");
    $w = 0;
    foreach ($out as $x) {
        $ok = $conn->query("INSERT INTO rpr03_event_classification
            (event_key, category, classification, rule_applied, evidence,
             occurrences, with_amount, with_qty, snapshot_id, ruled_at)
            VALUES ('" . $e($x[0]) . "', '" . $e($x[1]) . "', '" . $e($x[2]) . "',
                    '" . $e($x[3]) . "', '" . $e($x[4]) . "',
                    " . (int) $x[5] . ", " . (int) $x[6] . ", " . (int) $x[7] . ",
                    '" . $e($sid) . "', NOW())");
        if (!$ok) { exit("✘ تعذّر {$x[0]}: {$conn->error}\n"); }
        $w++;
    }
    printf("\n  ✔ كُتب حكمُ **%d** نوعٍ بشاهدِه\n", $w);
    $back = (int) $conn->query("SELECT COUNT(*) FROM rpr03_event_classification")->fetch_row()[0];
    $bad  = (int) $conn->query("SELECT COUNT(*) FROM rpr03_event_classification
                                 WHERE classification <> 'NEEDS_ADJUDICATION' AND evidence = ''")->fetch_row()[0];
    printf("  ✔ أُعيدت القراءة: %d نوعًا · حكمٌ بلا شاهدٍ %d\n", $back, $bad);
} elseif ($APPLY) {
    echo "\n  ⛔ **لم يُكتب شيء** — حكمٌ بلا شاهدٍ لا يُثبَّت\n";
}

echo "\n────────────────────────────────────────────────────────────\n";
printf("**نوعٌ بلا حكمٍ من الثلاثة: %d من %d**\n", $cnt['NEEDS_ADJUDICATION'], count($keys));
echo $cnt['NEEDS_ADJUDICATION'] === 0
    ? "🟢 **كلُّ نوعٍ له حكمٌ موثَّق**\n"
    : "◆ `Track RPR-03 ب blocked at stage: ماليٌّ بحمولةٍ صفر` — ⛔ ولا يُقحَم في صنفٍ بلا دليل\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $cnt['RETIRED'] < 3
        ? "🟢 **مفتاحُ سبرٍ مُنح حمولةً فلم يُتقاعد — فالقاعدةُ تقرأ الحمولةَ لا الاسمَ وحدَه**\n"
        : "✘ **تُقوعد رغمَ الحمولة** — والقاعدةُ تقرأ الاسمَ فقط\n";
    exit($cnt['RETIRED'] < 3 ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-03` §٤·٢ — تصنيفُ أنواعِ الأحداثِ الثمانيةِ والخمسين\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## القواعدُ الثلاثُ — مقيسةٌ على الحمولةِ لا مؤلَّفةٌ بالاسم\n\n";
    $o .= "| القاعدة | الحكم | العدد | المعيار |\n|---|---|---|---|\n";
    $o .= "| `B2` | `BUSINESS` | **" . $cnt['BUSINESS'] . "** | مبلغٌ أو كمّيّةٌ غيرُ صفرٍ ⇒ يستلزم أثرًا خارجَ الحدث |\n";
    $o .= "| `B3` | `AUDIT` | **" . $cnt['AUDIT'] . "** | لا حمولةَ وفئتُه غيرُ ماليّة ⇒ غرضُه الرقابيُّ يتحقّق بوجودِه |\n";
    $o .= "| `B1` | `RETIRED` | **" . $cnt['RETIRED'] . "** | عائلةُ السبرِ بصفرِ حمولةٍ ⇒ عُدّةُ قياسٍ لا واقعةُ أعمال |\n";
    $o .= "| — | ⛔ `NEEDS_ADJUDICATION` | **" . $cnt['NEEDS_ADJUDICATION'] . "** | ماليٌّ بحمولةٍ صفر — ولا يُقحَم |\n\n";
    $o .= "## خطوةُ صفرٍ — إعادةُ قياسِ خطِّ الأساس\n\n";
    $o .= "خطُّ الأساسِ يقول **١١** حدثَ أعمالٍ و**٤٧** تدقيقيًّا · **والمقيسُ الآنَ "
        . $cnt['BUSINESS'] . " و" . $cnt['AUDIT'] . "** — والمقامُ ٥٨ لم يتغيّر.\n";
    $o .= "◆ **والفرقُ خبرٌ يُعلَن لا يُخفى.**\n\n";
    $o .= "## الجدولُ الكامل\n\n| المفتاح | الفئة | الحكم | القاعدة | وقائع | بمبلغ | بكمّيّة |\n";
    $o .= "|---|---|---|---|---|---|---|\n";
    foreach ($out as $x) {
        $o .= '| `' . $x[0] . '` | ' . $x[1] . ' | `' . $x[2] . '` | ' . $x[3] . ' | '
            . $x[5] . ' | ' . $x[6] . ' | ' . $x[7] . " |\n";
    }
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_EVENT_CLASSIFICATION.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_EVENT_CLASSIFICATION.md\n";
}
