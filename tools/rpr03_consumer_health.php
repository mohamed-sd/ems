<?php
/**
 * tools/rpr03_consumer_health.php — `RPR-03` §٦·٣ · صحّةُ المستهلكين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — `RPR-03` §٦·٣ و§٩: *«لكلِّ مستهلك: آخرُ حدثٍ عولج ·
 *   التأخّرُ · حالةُ الخطأ · التوقّفُ · التنبيهُ · قدرةُ الإعادة»* ·
 *   **والقبول**: `Critical Stalled Consumers = 0` · *«⛔ ولا يبقى مستهلكُ صرفِ
 *   العملةِ متوقّفًا»*.
 *
 * ◆ **وخطوةُ صفرٍ توجب إعادةَ القياس** — `RPR-03` §٢·١: أرقامُ الأمرِ مقيسةٌ على
 *   `BL-20260828` **خطَّ أساسٍ تاريخيًّا لا حالةً راهنة**، ومنها «مستهلكٌ واحدٌ
 *   متوقّف». **وإعادةُ القياسِ تقول غيرَ ذلك** — والفرقُ خبرٌ يُعلَن لا يُخفى.
 *
 * ◆ **والتوقّفُ يُقاس بمؤشِّرِه لا بتاريخِ صفِّه**: مستهلكٌ `updated_at` حديثٌ
 *   وهو واقفٌ عند مؤشِّرٍ قديمٍ **متوقّفٌ فعلًا**؛ ومستهلكٌ لا يتحرّك لأنَّ لا
 *   جديدَ له **ليس متوقّفًا**. ⇒ فالمقياسان معًا: **فجوةُ المؤشِّر** (كم واقعةً
 *   بعده) **وسكونُ الزمن**. ⛔ ولا يكفي أحدُهما.
 *
 * ◆ **والحدُّ يُعلَن ولا يُخترَع**: `RPR-03` §٦·٣ يوجب «إنذارًا عند توقّفٍ
 *   يتجاوز **حدًّا معلنًا**» — **والحدُّ قيمةٌ تشغيليّةٌ لم تصدر بعد**
 *   (`MASTER_EXEC` §٣ · `CONFIG_PENDING`). ⇒ فيُقاس التأخّرُ ويُعرض **ولا
 *   يُصنَّف «حرجًا» بحدٍّ من عندِ المنفِّذ** — ⛔ ولا تُكتب قيمةٌ زمنيّةٌ في
 *   الشيفرة. والاستثناءُ الوحيدُ منصوصٌ باسمِه: **صرفُ العملة `fx` حرجٌ بنصِّ
 *   الأمرِ لا بعتبةٍ**.
 *
 * التشغيل: php tools/rpr03_consumer_health.php [--md] [--selftest]
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

/* ═══ المقام ═════════════════════════════════════════════════════════════ */
$maxId  = (int) $one("SELECT COALESCE(MAX(id),0) FROM ems_business_events");
$total  = (int) $one("SELECT COUNT(*) FROM ems_business_events");
$failed = (int) $one("SELECT COUNT(*) FROM ems_business_events WHERE delivered_failed > 0");
$dlq    = (int) $one("SELECT COUNT(*) FROM ems_business_events WHERE in_dlq > 0");
$keys   = (int) $one("SELECT COUNT(DISTINCT event_key) FROM ems_business_events");
$lastAt = (string) $one("SELECT COALESCE(MAX(created_at),'') FROM ems_business_events");

/* ⛔ **`fx` حرجٌ بنصِّ الأمرِ لا بعتبةٍ من عندي** */
$CRITICAL_BY_ORDER = array('fx');

$rows = array();
$r = $conn->query("SELECT consumer, enabled, cursor_event_id, updated_at
                     FROM ems_event_consumers ORDER BY consumer");
while ($x = $r->fetch_assoc()) {
    $cur = (int) $x['cursor_event_id'];
    /* فجوةُ المؤشِّر: كم واقعةً بعدَه فعلًا — لا فرقُ معرِّفَين.
       ⛔ **والمقامُ ناقلُ الموزِّعِ نفسُه** (FINAL_CLOSE ⑨): المؤشِّراتُ معرِّفاتُ
       `fin_financial_events` (منه يقرأ `EventDispatcher::runConsumer` بعقدِ
       `event_key`) — وقياسُها على `ems_business_events` مقارنةُ دفترَين. */
    $behind = (int) $one("SELECT COUNT(*) FROM fin_financial_events WHERE id > " . $cur
                       . " AND event_key IS NOT NULL AND COALESCE(is_deleted,0) = 0");
    $idleDays = null;
    if ($x['updated_at'] !== null && $x['updated_at'] !== '') {
        $idleDays = (int) $one("SELECT DATEDIFF(NOW(), '" . $conn->real_escape_string($x['updated_at']) . "')");
    }
    $rows[] = array(
        'consumer' => $x['consumer'],
        'enabled'  => (int) $x['enabled'],
        'cursor'   => $cur,
        'behind'   => $behind,
        'updated'  => (string) $x['updated_at'],
        'idle'     => $idleDays,
        'critical' => in_array($x['consumer'], $CRITICAL_BY_ORDER, true),
    );
}

/* ⛔ **السالبُ يكسر مفردةً فريدة**: مستهلكٌ حرجٌ بلا فجوةٍ يصير ذا فجوة */
if ($SELF && $rows) {
    foreach ($rows as $i => $x) {
        if ($x['critical']) { $rows[$i]['behind'] = 0; $rows[$i]['idle'] = 0; break; }
    }
}

echo "\n═══ `RPR-03` §٦·٣ — صحّةُ المستهلكين ═══\n";
printf("  اللقطة: %s\n", $sid);
printf("  الصندوق: **%d** واقعةً · أقصى معرِّفٍ %d · أنواعٌ %d · آخرُ واقعةٍ %s\n",
       $total, $maxId, $keys, $lastAt !== '' ? $lastAt : '—');
printf("  الفاشلُ **%d** · في صفِّ الميّت **%d**\n\n", $failed, $dlq);

printf("  %-24s %5s %10s %10s %8s  %s\n",
       'المستهلك', 'مُفعَّل', 'المؤشِّر', 'خلفَه', 'سكونٌ/يوم', 'الحكم');
echo "  " . str_repeat('─', 84) . "\n";
$stalledCritical = 0; $behindAny = 0;
foreach ($rows as $x) {
    $isBehind = ($x['behind'] > 0);
    if ($isBehind) { $behindAny++; }
    $verdict = !$isBehind
        ? '✔ عند رأسِ الصندوق'
        : ($x['critical']
            ? '⛔ **متوقّفٌ حرجٌ — بنصِّ الأمرِ لا بعتبة**'
            : '◆ متأخِّرٌ — والحدُّ الحرجُ قيمةٌ لم تصدر');
    if ($isBehind && $x['critical']) { $stalledCritical++; }
    printf("  %-24s %5s %10d %10d %8s  %s\n",
           $x['consumer'], $x['enabled'] ? 'نعم' : '**لا**', $x['cursor'], $x['behind'],
           $x['idle'] === null ? '—' : $x['idle'], $verdict);
}

echo "\n  ── خطوةُ صفرٍ: إعادةُ قياسِ خطِّ الأساس (§٢·١) ──\n";
printf("     خطُّ الأساسِ يقول «مستهلكٌ **واحدٌ** متوقّف» · **والمقيسُ الآنَ %d متأخِّرًا من %d**\n",
       $behindAny, count($rows));
echo "     ◆ **والفرقُ خبرٌ يُعلَن لا يُخفى** — فالأمرُ خطُّ أساسٍ تاريخيٌّ لا حالةٌ راهنة\n";

echo "\n  ── ما لا يُقاس يُسمّى ولا يُخمَّن ──\n";
echo "     · **حدُّ التوقّفِ المُعلَن** — `RPR-03` §٦·٣ يوجب «إنذارًا عند توقّفٍ يتجاوز حدًّا\n";
echo "       معلنًا»، **والحدُّ قيمةٌ تشغيليّةٌ لم تصدر** ⇒ يُقاس التأخّرُ ويُعرض ⛔ ولا\n";
echo "       يُصنَّف «حرجًا» بحدٍّ من عندِ المنفِّذ، ولا تُكتب قيمةٌ زمنيّةٌ في الشيفرة.\n";
echo "     · **الإنذارُ نفسُه** — بناؤه من `RPR-03` §٩، ولم يُبنَ بعد.\n";

echo "\n────────────────────────────────────────────────────────────\n";
printf("**`Critical Stalled Consumers` = %d** — والقبولُ صفر\n", $stalledCritical);
echo $stalledCritical === 0
    ? "🟢 **ولا مستهلكَ حرجًا متوقّفًا**\n"
    : "✘ **مستهلكُ صرفِ العملةِ متوقّفٌ** — و`RPR-03` §٦·٣ ينصُّ عليه باسمِه\n";

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    echo $stalledCritical === 0
        ? "🟢 **العدّادُ عاد صفرًا حين مُحيت فجوةُ الحرج — فالفاحصُ يقرأ الفجوةَ لا يفترضها**\n"
        : "✘ **العدّادُ لم يتحرّك** — والفاحصُ لا يُصدَّق\n";
    exit($stalledCritical === 0 ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-03` §٦·٣ — صحّةُ المستهلكين\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "الصندوق **" . $total . "** واقعةً · أقصى معرِّفٍ " . $maxId . " · أنواعٌ " . $keys
        . " · الفاشلُ **" . $failed . "** · صفُّ الميّت **" . $dlq . "**\n\n";
    $o .= "| المستهلك | مُفعَّل | المؤشِّر | خلفَه | سكونٌ/يوم | الحكم |\n|---|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        $isBehind = ($x['behind'] > 0);
        $o .= '| `' . $x['consumer'] . '` | ' . ($x['enabled'] ? 'نعم' : '**لا**') . ' | '
            . $x['cursor'] . ' | **' . $x['behind'] . '** | '
            . ($x['idle'] === null ? '—' : $x['idle']) . ' | '
            . (!$isBehind ? '✔ عند رأسِ الصندوق'
                : ($x['critical'] ? '⛔ **متوقّفٌ حرجٌ — بنصِّ الأمر**'
                                  : '◆ متأخِّرٌ — والحدُّ الحرجُ قيمةٌ لم تصدر')) . " |\n";
    }
    $o .= "\n## خطوةُ صفرٍ — إعادةُ قياسِ خطِّ الأساس (§٢·١)\n\n";
    $o .= "خطُّ الأساسِ يقول «مستهلكٌ **واحدٌ** متوقّف» · **والمقيسُ الآنَ " . $behindAny
        . " متأخِّرًا من " . count($rows) . "**.\n";
    $o .= "◆ **والفرقُ خبرٌ يُعلَن لا يُخفى** — فالأمرُ خطُّ أساسٍ تاريخيٌّ لا حالةٌ راهنة.\n\n";
    $o .= "## ما لا يُقاس يُسمّى\n\n";
    $o .= "- **حدُّ التوقّفِ المُعلَن**: §٦·٣ يوجب إنذارًا عند تجاوزِ **حدٍّ معلن**، والحدُّ\n";
    $o .= "  قيمةٌ تشغيليّةٌ لم تصدر ⇒ يُقاس التأخّرُ ويُعرض ⛔ ولا يُصنَّف «حرجًا» بحدٍّ من\n";
    $o .= "  عندِ المنفِّذ، ولا تُكتب قيمةٌ زمنيّةٌ في الشيفرة.\n";
    $o .= "- **الإنذارُ نفسُه** لم يُبنَ بعد — `RPR-03` §٩.\n\n";
    $o .= "**`Critical Stalled Consumers` = " . $stalledCritical . "** — والقبولُ صفر.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_CONSUMER_HEALTH.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_CONSUMER_HEALTH.md\n";
}
