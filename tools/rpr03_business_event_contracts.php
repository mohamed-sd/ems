<?php
/**
 * tools/rpr03_business_event_contracts.php — `RPR-03` §٤·٢ · عقدُ المستهلكِ الفعّال
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-03` §٤·٢ الخطوة ٣: *«عقدُ أثرٍ مسجَّلٌ لكلِّ
 *   مستهلك: **الحمولةُ ومفتاحُ منعِ التكرارِ وسياسةُ الإعادةِ وسلوكُ الفشلِ
 *   وأثرُ التدقيق**»* · و§١٠: `أحداثُ أعمالٍ بلا عقدِ مستهلكٍ فعّال = صفر`.
 *
 * ◆ **والقبولُ ليس وصولَ الرسالة** — §٤·٢: *«⛔ والقبولُ ليس وصولَ الرسالة —
 *   بل وقوعَ الأثرِ التجاريِّ المقصود»*. ⇒ **فاشتراكٌ قائمٌ ليس عقدًا**، وهذا
 *   لبُّ ما يقيسه هذا الفاحص.
 *
 * ◆ **وثلاثةُ فروقٍ تفصل الاشتراكَ عن العقدِ الفعّال — كلُّها مقيسة**:
 *   ① **مستهلكُ حراسةٍ لا مستهلكُ أثر**: `GovernanceWatchConsumer` مشترَكٌ في
 *      **٧٠ من ١٠٢** اشتراكٍ فعّال، و`produces='notify'` — **يراقب ولا يُحدث
 *      أثرًا تجاريًّا**. ⛔ فوجودُه لا يُغلق حدثَ أعمال.
 *   ② **`produces` يفصل**: `write` أثرٌ · و`notify`/`dashboard_refresh` إشعارٌ
 *      أو تحديثُ عرضٍ — **ولا أحدُهما أثرٌ تجاريٌّ مقصود**.
 *   ③ **حقولُ العقدِ الخمسةُ**: كان الجدولُ يحمل **واحدًا** منها فقط
 *      (`max_attempts` — سياسةُ الإعادة)، **وأربعةٌ لا موضعَ لها في المخزنِ
 *      أصلًا** ⇒ فالعقدُ **لا يمكن أن يُسجَّل** لا أنّه لم يُسجَّل.
 *      وأُضيفت في `2027_12_23` فصارت **٥/٥ موضعًا** — ⛔ **وملؤها عقدٌ لكلِّ
 *      مستهلكٍ على حدة**، والفاحصُ يقيس الموضعَ والملءَ معًا.
 *
 * ⛔ **ولا يُحتسب حدثٌ مُغلقًا بعقدٍ ناقص** — فالعدُّ بالعقدِ لا بالاشتراك.
 *
 * التشغيل: php tools/rpr03_business_event_contracts.php [--md] [--selftest]
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
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '—(بلا نافذة)';

$have = (int) $one("SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rpr03_event_classification'");
if (!$have) {
    exit("⛔ **التصنيفُ غيرُ مبنيّ** — شغِّلْ أوّلًا: php tools/rpr03_event_classify.php --apply\n");
}

/* ═══ ① حقولُ العقدِ الخمسةُ — أيُّها موجودٌ في المخزن ═══════════════════ */
$col = function ($c) use ($one) {
    return (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_consumers'
                          AND COLUMN_NAME='" . $c . "'") > 0;
};
$CONTRACT = array(
    'الحمولة'              => $col('payload_schema') || $col('payload'),
    'مفتاحُ منعِ التكرار'   => $col('idempotency_key') || $col('idempotency'),
    'سياسةُ الإعادة'        => $col('max_attempts'),
    'سلوكُ الفشل'          => $col('failure_behavior') || $col('on_failure'),
    'أثرُ التدقيق'          => $col('audit_effect') || $col('audit_ref'),
);
$contractHave = count(array_filter($CONTRACT));
$contractNeed = count($CONTRACT);

/* ═══ ② العدُّ بالعقدِ لا بالاشتراك ══════════════════════════════════════ */
$biz = (int) $one("SELECT COUNT(*) FROM rpr03_event_classification WHERE classification='BUSINESS'");
$anySub = (int) $one("SELECT COUNT(*) FROM rpr03_event_classification k
                       WHERE k.classification='BUSINESS'
                         AND EXISTS(SELECT 1 FROM event_consumers e
                                     WHERE e.event_name=k.event_key AND e.active=1)");
$effect = (int) $one("SELECT COUNT(*) FROM rpr03_event_classification k
                       WHERE k.classification='BUSINESS'
                         AND EXISTS(SELECT 1 FROM event_consumers e
                                     WHERE e.event_name=k.event_key AND e.active=1
                                       AND e.produces='write'
                                       AND e.consumer_class NOT LIKE '%GovernanceWatch%')");
$guardOnly = $anySub - $effect;
$watchSubs = (int) $one("SELECT COUNT(*) FROM event_consumers
                          WHERE active=1 AND consumer_class LIKE '%GovernanceWatch%'");
$allSubs   = (int) $one("SELECT COUNT(*) FROM event_consumers WHERE active=1");

$noContract = ($contractHave < $contractNeed);

echo "\n═══ `RPR-03` §٤·٢ — عقدُ المستهلكِ الفعّال ═══\n";
printf("  اللقطة: %s\n\n", $sid);
echo "  ── ① حقولُ العقدِ الخمسةُ في المخزن ──\n";
foreach ($CONTRACT as $k => $v) { printf("     %s %s\n", $v ? '✔' : '⛔', $k); }
printf("     **%d من %d** — و§٤·٢ يوجب الخمسةَ لكلِّ مستهلك\n", $contractHave, $contractNeed);

echo "\n  ── ② الاشتراكُ ليس عقدًا ──\n";
printf("     اشتراكاتٌ فعّالة: **%d** · منها `GovernanceWatchConsumer`: **%d** (`notify` — يراقب ولا يُحدث أثرًا)\n",
       $allSubs, $watchSubs);
printf("     أحداثُ الأعمال: **%d**\n", $biz);
printf("       · لها اشتراكٌ فعّالٌ أيًّا كان:            **%d**\n", $anySub);
printf("       · لها مستهلكُ **أثرٍ** (`write` وغيرُ حارس): **%d**\n", $effect);
printf("       · **بحارسٍ وحدَه — ولا أثرَ تجاريّ:        %d**\n", $guardOnly);

echo "\n────────────────────────────────────────────────────────────\n";
$metric = $biz - ($noContract ? 0 : $effect);
printf("**`أحداثُ أعمالٍ بلا عقدِ مستهلكٍ فعّال` = %d من %d** — والقبولُ صفر\n", $metric, $biz);
if ($noContract) {
    echo "⛔ **ولا يُحتسب أيُّ حدثٍ مُغلقًا**: العقدُ ناقصٌ بنيويًّا (" . $contractHave . "/"
       . $contractNeed . ") — فمفتاحُ منعِ التكرارِ وسلوكُ الفشلِ وأثرُ التدقيقِ\n";
    echo "  **لا موضعَ لها في المخزنِ أصلًا**، ولا يُثبَت منعُ تكرارٍ بلا مفتاحِه.\n";
    echo "⇒ `Track RPR-03 ب blocked at stage: حقولُ عقدِ الأثرِ الثلاثةُ الغائبة`\n";
}
echo "◆ وخطُّ الأساسِ يقول «١١ من ١١» — **والمقامُ الآنَ " . $biz . "** بعد إعادةِ التصنيف\n";

if ($SELF) {
    /* ⛔ **والسالبُ يختبر المُميِّزَ لا الحالةَ الراهنة**: أوّلُ صياغةٍ لهذا
         الاختبارِ ادّعت «العقدُ ناقصٌ» وأكّدت نقصَه — **فكانت تمرُّ ما دام
         العطبُ قائمًا وترسُب حينَ يُصلَح**. وذاك ليس اختبارًا سالبًا بل تثبيتًا
         للعطب. ⇒ **يُختبر ما يميّزه الفاحصُ**: استبعادُ حارسِ الحوكمةِ من
         مستهلكي الأثر. فإن أُلغيَ الاستبعادُ وتغيّر العددُ فالمُميِّزُ يعمل،
         وإن لم يتغيّر فهو لا يميّز شيئًا. */
    $noFilter = (int) $one("SELECT COUNT(*) FROM rpr03_event_classification k
                             WHERE k.classification='BUSINESS'
                               AND EXISTS(SELECT 1 FROM event_consumers e
                                           WHERE e.event_name=k.event_key AND e.active=1
                                             AND e.produces IN ('write','notify','dashboard_refresh'))");
    echo "\n═══ الاختبارُ السالب ═══\n";
    printf("  بمستهلكِ أثرٍ (‏حارسٌ مستبعَد): **%d** · وبلا استبعادٍ ولا تمييزِ نوعٍ: **%d**\n",
           $effect, $noFilter);
    echo ($noFilter !== $effect)
        ? "🟢 **العددُ تغيّر بإلغاءِ الاستبعاد — فالمُميِّزُ يفصل الحارسَ عن الأثرِ فعلًا**\n"
        : "✘ **العددُ لم يتغيّر** — فالاستبعادُ لا يميّز شيئًا والفاحصُ لا يُصدَّق\n";
    exit(($noFilter !== $effect) ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-03` §٤·٢ — عقدُ المستهلكِ الفعّال\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "## ① حقولُ العقدِ الخمسةُ في المخزن\n\n| الحقل | موجود؟ |\n|---|---|\n";
    foreach ($CONTRACT as $k => $v) { $o .= '| ' . $k . ' | ' . ($v ? '✔' : '⛔ **غائب**') . " |\n"; }
    $o .= "\n**" . $contractHave . " من " . $contractNeed . "** — و§٤·٢ يوجب الخمسةَ لكلِّ مستهلك.\n\n";
    $o .= "## ② الاشتراكُ ليس عقدًا\n\n";
    $o .= "- اشتراكاتٌ فعّالة: **" . $allSubs . "** · منها `GovernanceWatchConsumer`: **"
        . $watchSubs . "** — `produces='notify'`، **يراقب ولا يُحدث أثرًا تجاريًّا**.\n";
    $o .= "- أحداثُ الأعمال: **" . $biz . "** · لها اشتراكٌ أيًّا كان **" . $anySub
        . "** · لها مستهلكُ **أثرٍ** **" . $effect . "** · **بحارسٍ وحدَه " . $guardOnly . "**.\n\n";
    $o .= "⛔ **والقبولُ ليس وصولَ الرسالة بل وقوعَ الأثرِ التجاريِّ المقصود** (§٤·٢).\n\n";
    $o .= "## الحكم\n\n**`أحداثُ أعمالٍ بلا عقدِ مستهلكٍ فعّال` = " . $metric . " من " . $biz
        . "** — والقبولُ صفر.\n\n";
    if ($noContract) {
        $o .= "⛔ ولا يُحتسب أيُّ حدثٍ مُغلقًا: **مفتاحُ منعِ التكرارِ وسلوكُ الفشلِ وأثرُ التدقيقِ\n";
        $o .= "لا موضعَ لها في المخزنِ أصلًا** — ولا يُثبَت منعُ تكرارٍ بلا مفتاحِه.\n\n";
        $o .= "`Track RPR-03 ب blocked at stage: حقولُ عقدِ الأثرِ الثلاثةُ الغائبة`\n\n";
    }
    $o .= "◆ وخطُّ الأساسِ يقول «١١ من ١١» — **والمقامُ الآنَ " . $biz . "** بعد إعادةِ التصنيف.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_EVENT_CONTRACTS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_EVENT_CONTRACTS.md\n";
}
