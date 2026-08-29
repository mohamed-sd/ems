<?php
/**
 * tools/rpr03_consumer_vocabulary.php — `RPR-03` §٤ · مفرداتُ المُنتِجِ ومفرداتُ المستهلك
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **العطبُ المقيس** — §٤ يقول: *«المشكلةُ ليست في الناقلِ بل في أنَّ أحدًا
 *   لا يستمع»*. وقياسُ اليوم يقول أدقَّ من ذلك ويقلبه: **المستهلكون موجودون
 *   ومسجَّلون ونشِطون — لكنَّهم يستمعون إلى أسماءٍ لم تُنطَق قطّ**.
 *
 * ◆ **والرقمُ قاطعٌ**: من **١٠٢** اشتراكٍ نشِط، **٥٨** اسمًا وقع فعلًا في
 *   `ems_business_events` — **وصفرٌ منها اشتراكُ كتابة**. أي أنَّ الثلاثين
 *   اشتراكَ **الأثرِ** كلَّها معلَّقةٌ على أسماءٍ **لم تقع مرّةً واحدة**:
 *   المُنتِجُ يقول `revenue.unit.recognized` والمستهلكُ ينتظر `acc.entry.posted`.
 *
 * ◆ **ولهذا لا يُقرأ «صفرُ مستهلكِ أثر» على أنَّه «لم يُبنَ مستهلك»** — بل
 *   **«بُني وعُلِّق على مفردةٍ ميّتة»**. والعلاجُ مصالحةُ المفردتَين لا كتابةُ
 *   ثلاثةٍ وعشرين مستهلكًا جديدًا فوقَ ثلاثين قائمًا لا يسمعون.
 *   ⇒ وهذا هو **درسُ كونَي الأهدافِ نفسُه** في طبقةِ الأحداث: سجلّانِ صادقان
 *      كلٌّ في نفسِه، ولا مفتاحَ يجمعهما، فيُقاس العجزُ حيث لا عجز.
 *
 * ◆ **وثلاثةُ أحكامٍ لكلِّ اشتراك**:
 *   `LIVE`   — اسمُه وقع فعلًا، فالاشتراكُ على مفردةٍ حيّة.
 *   `DEAD`   — اسمُه **لم يقع قطُّ** في السجلِّ كلِّه ⇒ اشتراكٌ على مفردةٍ ميّتة.
 *   `SILENT` — وقع قديمًا ولم يقع في آخرِ ألفِ حدث ⇒ حيٌّ خامد.
 *
 * ⛔ **ولا يُقترح هنا ربطٌ بين مفردتَين** — فربطُ `acc.entry.posted` بـ
 *   `finance.event.recorded` **حكمُ أعمالٍ لا تشابهُ أسماء**، ومن يربط بالحدسِ
 *   يُشغِّل أثرًا ماليًّا على حدثٍ غيرِ مقصودِه. تُعرض المفردتان متقابلتَين
 *   **ويُترك الربطُ لقرارٍ موثَّق**.
 *
 * التشغيل:
 *   php tools/rpr03_consumer_vocabulary.php [--md] [--list] [--selftest]
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
$LIST = in_array('--list', $argv, true);
$SELF = in_array('--selftest', $argv, true);

$q = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if (!$r) { fwrite(STDERR, "✘ استعلامٌ سقط: {$conn->error}\n   $sql\n"); exit(2); }
    return $r;
};
$one = function ($sql) use ($q) { $x = $q($sql)->fetch_row(); return $x ? $x[0] : null; };

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : 'DRY';

/* ═══ ① مفرداتُ المُنتِجِ — ما نُطق فعلًا ═════════════════════════════════ */
$spoken = array(); $recent = array();
$r = $q("SELECT event_key, COUNT(*) n, MAX(id) last_id FROM ems_business_events GROUP BY event_key");
while ($x = $r->fetch_assoc()) { $spoken[$x['event_key']] = array((int) $x['n'], (int) $x['last_id']); }
$maxId = (int) $one("SELECT COALESCE(MAX(id),0) FROM ems_business_events");
$window = max(0, $maxId - 1000);

/* ═══ ② مفرداتُ المستهلك — ما يُنتظَر ═══════════════════════════════════ */
$subs = array();
$r = $q("SELECT event_name, consumer_class, produces, active, consumer_key,
                payload_schema, idempotency_key, failure_behavior, audit_effect
           FROM event_consumers ORDER BY produces, event_name");
while ($x = $r->fetch_assoc()) { $subs[] = $x; }

/* ═══ ③ الحكمُ الثلاثيّ ═════════════════════════════════════════════════ */
function rpr03_vocab_verdict($name, $spoken, $window)
{
    if (!isset($spoken[$name])) { return 'DEAD'; }
    return ($spoken[$name][1] >= $window) ? 'LIVE' : 'SILENT';
}

/* ═══ ④ الاختبارُ السالب ═══════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if (count($spoken) < 10) { echo '  X مفرداتُ المُنتِجِ ' . count($spoken) . " — قراءةٌ عمياء\n"; $fail++; }
    if (count($subs)   < 10) { echo '  X الاشتراكاتُ ' . count($subs) . " — قراءةٌ عمياء\n"; $fail++; }
    /* **الكاسرُ**: مفردةٌ لا وجودَ لها يجب أن تُحكَم `DEAD` وحدَها */
    $probe = 'zzq.unique.vocab.probe';
    if (rpr03_vocab_verdict($probe, $spoken, $window) !== 'DEAD') { echo "  X المفردةُ الغائبةُ لم تُحكَم ميّتةً\n"; $fail++; }
    /* وأشهرُ مفردةٍ منطوقةٍ يجب ألّا تُحكَم ميّتةً — وإلّا فالمصفاةُ تُميت الكلّ */
    $top = ''; $topN = -1;
    foreach ($spoken as $k => $v) { if ($v[0] > $topN) { $topN = $v[0]; $top = $k; } }
    if ($top !== '' && rpr03_vocab_verdict($top, $spoken, $window) === 'DEAD') {
        echo "  X أكثرُ المفرداتِ وقوعًا («$top» ×$topN) حُكمت ميّتةً\n"; $fail++;
    }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — الحكمُ يميّز الغائبَ عن الأكثرِ وقوعًا\n";
    exit($fail ? 1 : 0);
}

/* ═══ ⑤ القياس ═══════════════════════════════════════════════════════════ */
$agg = array();
foreach ($subs as $s) {
    $v = rpr03_vocab_verdict($s['event_name'], $spoken, $window);
    $k = $s['produces'] . '|' . ((int) $s['active'] === 1 ? 'ACTIVE' : 'OFF') . '|' . $v;
    $agg[$k] = isset($agg[$k]) ? $agg[$k] + 1 : 1;
    $s['verdict'] = $v;
}
$deadWrite = array(); $liveWrite = 0; $contractFull = 0;
foreach ($subs as $s) {
    if ((int) $s['active'] !== 1 || $s['produces'] !== 'write') { continue; }
    $v = rpr03_vocab_verdict($s['event_name'], $spoken, $window);
    if ($v === 'DEAD') { $deadWrite[] = $s; } else { $liveWrite++; }
    $full = trim((string) $s['payload_schema']) !== '' && trim((string) $s['idempotency_key']) !== ''
         && trim((string) $s['failure_behavior']) !== '' && trim((string) $s['audit_effect']) !== '';
    if ($full) { $contractFull++; }
}

/* أحداثُ الأعمالِ المنطوقةُ بلا أيِّ اشتراكِ كتابة */
$bizNoWriter = array();
$r = $q("SELECT event_key FROM rpr03_event_classification WHERE classification = 'BUSINESS' ORDER BY occurrences DESC");
while ($x = $r->fetch_row()) {
    $n = (int) $one("SELECT COUNT(*) FROM event_consumers
                      WHERE active = 1 AND produces = 'write'
                        AND event_name = '" . $conn->real_escape_string($x[0]) . "'");
    if ($n === 0) { $bizNoWriter[] = $x[0]; }
}

/* ═══ ④·ب الأثرُ عند المُنتِجِ — قراءةٌ تُصحِّح «صفرَ أثر» ═════════════════
     ⛔ **و«بلا مستهلكِ أثر» ليست «بلا أثر»**: `App\Services\EffectFanout`
     يُنادى **داخلَ معاملةِ المُنتِجِ نفسِها** ويكتب أثرَه ويقيّده في دفترِ
     الآثارِ `fin_event_links` — واشتراكُه الناقليُّ **معطَّلٌ بسببٍ مكتوبٍ
     مسجَّلٍ في السجلِّ نفسِه**: «ليس مستهلكَ ناقلٍ أصلًا، وأثرُه الماليُّ باقٍ
     حيث كان». فلو قِيس الأثرُ بالمستهلكِ وحدَه لقيل «ثلاثةٌ وعشرون حدثًا بلا
     أثر» **وأحدُها له خمسةُ آلافٍ ومئةٌ وخمسةٌ وتسعون أثرًا مقيَّدًا**.
     ◆ **والمقياسُ يُسمّى بحدِّه**: «أثرٌ **مقيَّدٌ في دفترِ الآثار**» لا «أثرٌ
       موجود». فأثرٌ يقع ولا يُقيَّد **غيرُ مرئيٍّ للدفتر** — وذاك بعينِه ما
       تطلبه §٤·٢ الخطوة ٣ («عقدُ أثرٍ مسجَّلٌ لكلِّ مستهلك»). */
$KIND = "CASE b.entity_type WHEN 'timesheet' THEN 'timesheet'
              WHEN 'fin_unit_record' THEN 'unit_record'
              WHEN 'unit_record' THEN 'unit_record' ELSE 'event' END";
$prod = array();
$r = $q("SELECT b.event_key, COUNT(*) n,
                SUM(EXISTS(SELECT 1 FROM fin_event_links l
                            WHERE l.parent_ref = b.entity_id AND l.parent_kind = $KIND)) linked
           FROM ems_business_events b
          WHERE b.event_key IN (SELECT event_key FROM rpr03_event_classification
                                 WHERE classification = 'BUSINESS')
          GROUP BY b.event_key");
while ($x = $r->fetch_assoc()) {
    $k = $x['event_key'];
    if (!isset($prod[$k])) { $prod[$k] = array(0, 0); }
    $prod[$k][0] += (int) $x['n'];
    $prod[$k][1] += (int) $x['linked'];
}
$withProducerEffect = 0; $bizTot = count($prod);
foreach ($prod as $k => $v) { if ($v[0] > 0 && $v[1] >= $v[0] * 0.5) { $withProducerEffect++; } }

$actAll = 0; $actLive = 0;
foreach ($subs as $s) {
    if ((int) $s['active'] !== 1) { continue; }
    $actAll++;
    if (rpr03_vocab_verdict($s['event_name'], $spoken, $window) !== 'DEAD') { $actLive++; }
}

echo "\n═══ `RPR-03` §٤ — مفرداتُ المُنتِجِ ومفرداتُ المستهلك ═══\n";
printf("  اللقطة %s · وقائعُ الناقل %d · آخرُ معرِّفٍ %d\n\n", $sid, array_sum(array_map(function ($v) { return $v[0]; }, $spoken)), $maxId);
printf("  مفرداتٌ **نُطقت** فعلًا: **%d** · اشتراكاتٌ نشِطة: **%d** · منها على مفردةٍ حيّةٍ أو خامدة: **%d**\n",
       count($spoken), $actAll, $actLive);
printf("  ── **اشتراكاتُ الأثرِ (`write` النشِطة)** ──\n");
printf("     على مفردةٍ وقعت فعلًا: **%d**\n", $liveWrite);
printf("     ⛔ **على مفردةٍ لم تقع قطّ (`DEAD`): %d**\n", count($deadWrite));
printf("     وعقدُها الخماسيُّ كاملٌ في: **%d**\n", $contractFull);
printf("\n  أحداثُ أعمالٍ منطوقةٌ بلا اشتراكِ كتابةٍ نشِط: **%d**\n", count($bizNoWriter));
echo "\n  ── والأثرُ عند المُنتِجِ — قراءةٌ تُصحِّح «صفرَ أثر» ──\n";
printf("     حدثُ أعمالٍ له **أثرٌ مقيَّدٌ في `fin_event_links`** لأغلبِ وقائعِه: **%d من %d**\n",
       $withProducerEffect, $bizTot);
foreach ($prod as $k => $v) {
    if ($v[0] > 0 && $v[1] >= $v[0] * 0.5) { printf("       ✔ %-34s %d من %d واقعة\n", $k, $v[1], $v[0]); }
}
printf("     ⇒ **%d حدثًا بلا أثرٍ مقيَّدٍ بأيِّ طريقٍ — لا بمستهلكٍ ولا بمُنتِج**\n",
       $bizTot - $withProducerEffect);
echo "     ⛔ والحدُّ يُقال: «أثرٌ **مقيَّدٌ في الدفتر**» لا «أثرٌ موجود» — فأثرٌ\n";
echo "       يقع ولا يُقيَّد غيرُ مرئيٍّ للدفتر، وذاك ما تطلبه §٤·٢ الخطوة ٣.\n";

if ($LIST) {
    echo "\n  ── اشتراكاتُ أثرٍ على مفردةٍ ميّتة ──\n";
    foreach ($deadWrite as $s) {
        printf("     %-34s ⇐ %s\n", $s['event_name'], preg_replace('~^.*\\\\~', '', $s['consumer_class']));
    }
    echo "\n  ── أحداثُ أعمالٍ منطوقةٌ تنتظر أثرًا ──\n";
    foreach ($bizNoWriter as $k) {
        printf("     %-34s ×%d\n", $k, isset($spoken[$k]) ? $spoken[$k][0] : 0);
    }
}

echo "\n────────────────────────────────────────────────────────────\n";
echo "⛔ **والقراءةُ تُقلب**: ليست «لم يُبنَ مستهلك» بل **«بُني وعُلِّق على مفردةٍ ميّتة»**.\n";
echo "  والعلاجُ **مصالحةُ المفردتَين** لا كتابةُ مستهلكين جددٍ فوقَ قائمين لا يسمعون.\n";
echo "⛔ **ولا يُقترح ربطٌ هنا**: ربطُ اسمٍ باسمٍ **حكمُ أعمالٍ لا تشابهُ حروف**،\n";
echo "  ومن يربط بالحدسِ يُشغِّل أثرًا ماليًّا على حدثٍ غيرِ مقصودِه.\n";

if ($MD) {
    $o  = "# `RPR-03` §٤ — مفرداتُ المُنتِجِ ومفرداتُ المستهلك\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "§٤ يقول: «المشكلةُ ليست في الناقلِ بل في أنَّ أحدًا لا يستمع». **والقياسُ أدقُّ ويقلبه**:\n";
    $o .= "المستهلكون موجودون ونشِطون — **لكنَّهم يستمعون إلى أسماءٍ لم تُنطَق قطّ**.\n\n";
    $o .= "| المقياس | العدد |\n|---|---:|\n";
    $o .= "| مفرداتٌ نُطقت فعلًا | " . count($spoken) . " |\n";
    $o .= "| اشتراكاتٌ نشِطة | " . $actAll . " |\n";
    $o .= "| منها على مفردةٍ وقعت | " . $actLive . " |\n";
    $o .= "| **اشتراكُ أثرٍ على مفردةٍ حيّة** | **" . $liveWrite . "** |\n";
    $o .= "| ⛔ **اشتراكُ أثرٍ على مفردةٍ ميّتة** | **" . count($deadWrite) . "** |\n";
    $o .= "| حدثُ أعمالٍ منطوقٌ بلا كاتب | **" . count($bizNoWriter) . "** |\n\n";
    $o .= "## المفردتان متقابلتَين — ⛔ **ولا رَبطَ هنا**\n\n";
    $o .= "| ينتظره مستهلكُ أثرٍ ولم يقع | نُطق ولا كاتبَ له |\n|---|---|\n";
    $n = max(count($deadWrite), count($bizNoWriter));
    for ($i = 0; $i < $n; $i++) {
        $a = isset($deadWrite[$i]) ? '`' . $deadWrite[$i]['event_name'] . '` ⇐ '
           . preg_replace('~^.*\\\\~', '', $deadWrite[$i]['consumer_class']) : '';
        $b = isset($bizNoWriter[$i]) ? '`' . $bizNoWriter[$i] . '` ×'
           . (isset($spoken[$bizNoWriter[$i]]) ? $spoken[$bizNoWriter[$i]][0] : 0) : '';
        $o .= '| ' . $a . ' | ' . $b . " |\n";
    }
    $o .= "\n⛔ **والربطُ بينهما حكمُ أعمالٍ موثَّقٌ لا تشابهُ أسماء** — ومن يربط بالحدسِ\n";
    $o .= "يُشغِّل أثرًا ماليًّا على حدثٍ غيرِ مقصودِه.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_CONSUMER_VOCABULARY.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/RPR03_CONSUMER_VOCABULARY.md\n";
}
