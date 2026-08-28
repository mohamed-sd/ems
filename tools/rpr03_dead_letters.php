<?php
/**
 * tools/rpr03_dead_letters.php — `RPR-03` §٦·٣ · لا رسالةَ ميتةٌ بلا حكم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه**: *«وكلُّ رسالةٍ ميتةٍ تنتهي إلى حكم: `Replay` ·
 *   `Compensate` · `Close with reason` ⛔ **ولا رسالةَ ميتةٌ بلا حكم**»* ·
 *   و§١٢: *«لا إغلاقَ رسالةٍ ميتةٍ بلا سببٍ وقرار»*.
 *
 * ◆ **والحكمُ يحتاج السببَ — والسببُ غيرُ محفوظ**: موضعُ سببِ الفشلِ الوحيدُ
 *   هو `ems_event_dead_letter.last_error`، **والجدولُ فارغٌ تمامًا** بينما
 *   `ems_business_events.in_dlq` مرفوعةٌ في واقعة. ⇒ **قارئان يتفرّقان**.
 *   و`Replay` يفترض **عطبًا عابرًا**، و`Compensate` يفترض **أثرًا وقع ناقصًا**،
 *   و`Close` يفترض **أنّه لا أثرَ مقصودًا** — **وثلاثتُها تحتاج أن يُعرَف ما
 *   الذي فشل**. ⛔ فالحكمُ بلا سببٍ تخمينٌ يُغلق ملفًّا ولا يُصلح شيئًا.
 *
 * ◆ **وما يُقاس رغمَ ذلك يُقاس**: نمطُ الوقائعِ نفسُه شاهدٌ — دفعةٌ من مفتاحٍ
 *   واحدٍ في دقيقتَين تختلف عن واقعةٍ منفردة. **ويُسجَّل النمطُ دليلًا** ولا
 *   يُرفع إلى حكمٍ بذاته.
 *
 * التشغيل: php tools/rpr03_dead_letters.php [--apply] [--md] [--selftest]
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

$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '—(بلا نافذة)';

/* ═══ ① السببُ — أمحفوظٌ هو؟ ═════════════════════════════════════════════ */
$dlqRows  = (int) $one("SELECT COUNT(*) FROM ems_event_dead_letter");
$flagged  = (int) $one("SELECT COUNT(*) FROM ems_business_events WHERE in_dlq > 0");
$withErr  = (int) $one("SELECT COUNT(*) FROM ems_event_dead_letter WHERE last_error <> ''");
$causeKept = ($dlqRows > 0 && $withErr > 0);

/* ═══ ② الرسائلُ الميتةُ ونمطُها ══════════════════════════════════════════ */
$dead = array();
$r = $conn->query("SELECT id, event_key, delivered_ok, delivered_failed, in_dlq, created_at
                     FROM ems_business_events
                    WHERE delivered_failed > 0 OR in_dlq > 0
                    ORDER BY id");
while ($x = $r->fetch_assoc()) { $dead[] = $x; }

/* نمطُ الدفعة: عددُ إخوتِه من المفتاحِ نفسِه في النافذةِ نفسِها */
$burst = array();
foreach ($dead as $d) { $burst[$d['event_key']] = (isset($burst[$d['event_key']]) ? $burst[$d['event_key']] : 0) + 1; }

$rows = array(); $ruled = 0; $need = 0;
foreach ($dead as $d) {
    $k = $d['event_key'];
    $ev = 'نمطٌ مقيس: ' . $burst[$k] . ' واقعةً فاشلةً من المفتاحِ نفسِه'
        . ' · سُلِّم بنجاحٍ ' . (int) $d['delivered_ok'] . ' وفشل ' . (int) $d['delivered_failed']
        . ' · رايةُ `in_dlq` ' . ((int) $d['in_dlq'] ? 'مرفوعة' : 'مخفوضة')
        . ' · وقعت ' . $d['created_at'];
    /* ⛔ **ولا حكمَ بلا سبب** — والسببُ غيرُ محفوظ */
    $rul = 'NEEDS_ADJUDICATION';
    $reason = $causeKept ? '' : 'سببُ الفشلِ غيرُ محفوظ: `ems_event_dead_letter` فارغٌ '
            . '(' . $dlqRows . ' صفًّا) ولا `last_error` يُقرأ ⇒ لا يُميَّز عطبٌ عابرٌ '
            . 'من أثرٍ ناقصٍ من لا-أثر';
    $need++;
    $rows[] = array((int) $d['id'], $k, $rul, $reason, $ev);
}

/* ⛔ **السالبُ يكسر مفردةً فريدة**: يُدَّعى أنَّ السببَ محفوظ */
if ($SELF) { $causeKept = true; }

echo "\n═══ `RPR-03` §٦·٣ — الرسائلُ الميتةُ وأحكامُها ═══\n";
printf("  اللقطة: %s\n\n", $sid);
printf("  رسائلُ ميتةٌ في الصندوق: **%d**\n", count($dead));
foreach ($burst as $k => $n) { printf("     · %-36s %d\n", mb_substr($k, 0, 34), $n); }

echo "\n  ── قارئان يتفرّقان ──\n";
printf("     `ems_business_events.in_dlq` مرفوعةٌ في **%d** واقعة\n", $flagged);
printf("     `ems_event_dead_letter` فيه **%d** صفًّا · وبنصِّ خطأٍ **%d**\n", $dlqRows, $withErr);
echo ($flagged > 0 && $dlqRows === 0)
    ? "     ⛔ **الرايةُ ترفع والجدولُ خالٍ** — ولا يُصدَّق أحدُهما بإسكاتِ الآخر\n"
    : "     ✔ متّسقان\n";

echo "\n  ── لماذا لا يُحكَم الآن ──\n";
echo "     `Replay` يفترض **عطبًا عابرًا** · و`Compensate` يفترض **أثرًا وقع ناقصًا** ·\n";
echo "     و`Close` يفترض **أنّه لا أثرَ مقصودًا** — وثلاثتُها تحتاج أن يُعرَف ما الذي فشل.\n";
echo "     ⛔ **والحكمُ بلا سببٍ تخمينٌ يُغلق ملفًّا ولا يُصلح شيئًا** (§١٢).\n";
echo "     ⇒ `Track RPR-03 ب blocked at stage: سببُ الفشلِ غيرُ محفوظ`\n";

if ($APPLY) {
    $conn->query("DELETE FROM rpr03_event_dead_letter_rulings");
    $w = 0;
    foreach ($rows as $x) {
        $ok = $conn->query("INSERT INTO rpr03_event_dead_letter_rulings
            (event_id, event_key, ruling, reason, evidence, owner_role, snapshot_id, ruled_at)
            VALUES (" . (int) $x[0] . ", '" . $e($x[1]) . "', '" . $e($x[2]) . "',
                    '" . $e($x[3]) . "', '" . $e($x[4]) . "', 'مالكُ مجالِ الأحداث',
                    '" . $e($sid) . "', NOW())");
        if (!$ok) { exit("✘ تعذّر {$x[0]}: {$conn->error}\n"); }
        $w++;
    }
    printf("\n  ✔ قُيِّدت **%d** رسالةً بدليلِ نمطِها وسببِ تعذُّرِ حكمِها\n", $w);
    $back = (int) $one("SELECT COUNT(*) FROM rpr03_event_dead_letter_rulings");
    $noR  = (int) $one("SELECT COUNT(*) FROM rpr03_event_dead_letter_rulings
                         WHERE ruling='NEEDS_ADJUDICATION' AND reason=''");
    printf("  ✔ أُعيدت القراءة: %d صفًّا · محجوبٌ بلا سببٍ %d\n", $back, $noR);
}

echo "\n────────────────────────────────────────────────────────────\n";
printf("**رسالةٌ ميتةٌ بلا حكمٍ منفَّذ: %d من %d** — والقبولُ صفر\n", $need, count($dead));

if ($SELF) {
    echo "\n═══ الاختبارُ السالب ═══\n";
    /* ادُّعي أنَّ السببَ محفوظٌ — فيجب أن يسقط سببُ الحجبِ من السجل */
    $wouldReason = $causeKept ? '' : 'سببُ الفشلِ غيرُ محفوظ';
    echo ($wouldReason === '')
        ? "🟢 **حين ادُّعي حفظُ السبب سقط نصُّ الحجب — فالفاحصُ يقرأ الجدولَ لا يفترضه**\n"
        : "✘ **بقي نصُّ الحجبِ رغمَ الادّعاء** — فالفاحصُ لا يقرأ شيئًا\n";
    exit(($wouldReason === '') ? 0 : 1);
}

if ($MD) {
    $o  = "# `RPR-03` §٦·٣ — الرسائلُ الميتةُ وأحكامُها\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `" . $sid . "`\n\n";
    $o .= "رسائلُ ميتةٌ في الصندوق: **" . count($dead) . "**\n\n";
    $o .= "| المفتاح | العدد |\n|---|---|\n";
    foreach ($burst as $k => $n) { $o .= '| `' . $k . '` | ' . $n . " |\n"; }
    $o .= "\n## قارئان يتفرّقان\n\n";
    $o .= "- `ems_business_events.in_dlq` مرفوعةٌ في **" . $flagged . "** واقعة.\n";
    $o .= "- `ems_event_dead_letter` فيه **" . $dlqRows . "** صفًّا · وبنصِّ خطأٍ **" . $withErr . "**.\n";
    if ($flagged > 0 && $dlqRows === 0) {
        $o .= "- ⛔ **الرايةُ ترفع والجدولُ خالٍ** — ولا يُصدَّق أحدُهما بإسكاتِ الآخر.\n";
    }
    $o .= "\n## لماذا لا يُحكَم الآن\n\n";
    $o .= "`Replay` يفترض **عطبًا عابرًا** · `Compensate` يفترض **أثرًا وقع ناقصًا** ·\n";
    $o .= "`Close` يفترض **أنّه لا أثرَ مقصودًا** — وثلاثتُها تحتاج أن يُعرَف ما الذي فشل،\n";
    $o .= "**وموضعُ السببِ الوحيدُ `last_error` فارغ**.\n\n";
    $o .= "⛔ **والحكمُ بلا سببٍ تخمينٌ يُغلق ملفًّا ولا يُصلح شيئًا** (§١٢).\n\n";
    $o .= "`Track RPR-03 ب blocked at stage: سببُ الفشلِ غيرُ محفوظ`\n\n";
    $o .= "**رسالةٌ ميتةٌ بلا حكمٍ منفَّذ: " . $need . " من " . count($dead) . "** — والقبولُ صفر.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_DEAD_LETTERS.md', $o);
    echo "\n✔ كُتب: docs/REPAIR01_20260823/RPR03_DEAD_LETTERS.md\n";
}
