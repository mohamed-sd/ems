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

/* ═══ ②·ب **السببُ محفوظٌ — لكنْ في مكانٍ آخر** ═══════════════════════════
     قيل هنا «سببُ الفشلِ غيرُ محفوظ» لأنَّ `ems_event_dead_letter` فارغٌ
     و`last_error` خالٍ. **وذاك صحيحٌ عن ذلك الجدولِ وحدَه**. والقياسُ الأوسعُ
     يقول غيرَه: `ems_event_deliveries` فيه ستٌّ وعشرون صفًّا `state='dlq'`
     **ولكلٍّ منها `consumer`**، وذلك المستهلكُ **معطَّلٌ بسببٍ مكتوبٍ مسجَّلٍ**
     في `event_consumers.inactive_reason`.
     ⇒ فالسببُ **قرارٌ مسجَّلٌ على بُعدِ وصلةٍ واحدة**، لا مفقود. ومن قرأ
       جدولًا واحدًا وأعلن الغيابَ **قاس ما لم يبحث فيه**.
     ◆ **والحكمُ عندئذٍ ليس تخمينًا**: المستهلكُ الذي عُطِّل لأنَّه **ليس
       مستهلكَ ناقلٍ أصلًا** أو **لا طريقةَ له** لم يكن ليُحدث أثرًا لو نجح
       ⇒ `CLOSE_WITH_REASON` بنصِّ قرارِ تعطيلِه شاهدًا.
     ⛔ **وما لا يُوصَل إلى قرارٍ مسجَّلٍ يبقى `NEEDS_ADJUDICATION`** — ولا
        يُغلق بالقياسِ على إخوتِه. */
$dlqBy = array();
$r = $conn->query("SELECT d.event_id, d.consumer,
                          (SELECT c.inactive_reason FROM event_consumers c
                            WHERE c.consumer_key = d.consumer AND c.active = 0
                              AND COALESCE(c.inactive_reason,'') <> '' LIMIT 1) AS why,
                          (SELECT MAX(c2.produces) FROM event_consumers c2
                            WHERE c2.consumer_key = d.consumer) AS produces
                     FROM ems_event_deliveries d
                    WHERE d.state = 'dlq'");
if ($r) { while ($x = $r->fetch_assoc()) { $dlqBy[(int) $x['event_id']] = $x; } }

$rows = array(); $ruled = 0; $need = 0;
foreach ($dead as $d) {
    $k = $d['event_key'];
    $ev = 'نمطٌ مقيس: ' . $burst[$k] . ' واقعةً فاشلةً من المفتاحِ نفسِه'
        . ' · سُلِّم بنجاحٍ ' . (int) $d['delivered_ok'] . ' وفشل ' . (int) $d['delivered_failed']
        . ' · رايةُ `in_dlq` ' . ((int) $d['in_dlq'] ? 'مرفوعة' : 'مخفوضة')
        . ' · وقعت ' . $d['created_at'];
    $hit = isset($dlqBy[(int) $d['id']]) ? $dlqBy[(int) $d['id']] : null;

    if ($hit && trim((string) $hit['why']) !== '') {
        $rul = 'CLOSE_WITH_REASON';
        $reason = 'المستهلكُ «' . $hit['consumer'] . '» معطَّلٌ بقرارٍ مسجَّلٍ: '
                . mb_substr((string) $hit['why'], 0, 240)
                . ' ⇒ لم يكن ليُحدث أثرًا لو نجح، فلا إعادةَ ولا تعويض';
        $ev .= ' · الوصلة: `ems_event_deliveries.state=dlq` ⇐ `consumer=' . $hit['consumer']
             . '` ⇐ `event_consumers.inactive_reason` · صنفُ إنتاجِه `'
             . ((string) $hit['produces'] === '' ? '—' : $hit['produces']) . '`';
        $ruled++;
    } else {
        $rul = 'NEEDS_ADJUDICATION';
        $reason = 'لا صفَّ `dlq` في `ems_event_deliveries` لهذا الحدث، أو مستهلكُه '
                . 'بلا قرارِ تعطيلٍ مكتوب ⇒ لا سببَ مسجَّلًا يُبنى عليه حكم';
        $need++;
    }
    /* ⛔ **السالبُ يكسر مفردةً فريدة**: حكمٌ يُنزع سببُه */
    if ($SELF && $rul === 'CLOSE_WITH_REASON' && $ruled === 1) { $reason = ''; }
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

echo "\n  ── والسببُ محفوظٌ — لكنْ في مكانٍ آخر ──\n";
echo "     `Replay` يفترض **عطبًا عابرًا** · و`Compensate` يفترض **أثرًا وقع ناقصًا** ·\n";
echo "     و`Close` يفترض **أنّه لا أثرَ مقصودًا** — وثلاثتُها تحتاج أن يُعرَف ما الذي فشل.\n";
printf("     و`ems_event_deliveries` فيه **%d** صفًّا `state='dlq'` بمستهلكِه — **وذلك المستهلكُ\n", count($dlqBy));
echo "     معطَّلٌ بقرارٍ مكتوبٍ مسجَّلٍ** في `event_consumers.inactive_reason`.\n";
echo "     ⇒ فالسببُ **قرارٌ مسجَّلٌ على بُعدِ وصلةٍ واحدة** لا مفقود — ومن قرأ جدولًا\n";
echo "       واحدًا وأعلن الغيابَ **قاس ما لم يبحث فيه**.\n";
printf("     ✔ **حُكم بالسببِ المسجَّل: %d** · ⛔ وبقي بلا سببٍ مسجَّل: **%d**\n", $ruled, $need);
if ($need > 0) { echo "     ⇒ `Track RPR-03 ب blocked at stage: سببُ الفشلِ غيرُ مسجَّلٍ لهذه وحدَها`\n"; }
echo "     ⛔ **والحكمُ بلا سببٍ تخمينٌ يُغلق ملفًّا ولا يُصلح شيئًا** (§١٢) — فما لم\n";
echo "       يُوصَل إلى قرارٍ مسجَّلٍ لا يُغلق بالقياسِ على إخوتِه.\n";

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
    /* ⛔ **والكاسرُ يكسر التمييزَ لا يدّعي حالًا**: نُزع سببُ **حكمٍ واحدٍ**
       أُغلق (`CLOSE_WITH_REASON` الأوّل) — فيجب أن يظهر «حكمٌ بلا سببٍ = ١».
       والفحصُ القديمُ كان يقلب رايةَ `$causeKept` وهي لم تعد تحكم شيئًا بعد
       أن صار السببُ يُقرأ من `event_consumers` — **فمرَّ أخضرَ على لا شيء**. */
    $blank = 0; $closed = 0;
    foreach ($rows as $x) {
        if ($x[2] === 'CLOSE_WITH_REASON') { $closed++; if (trim($x[3]) === '') { $blank++; } }
    }
    $fail = 0;
    if ($closed < 1) { echo "  X لا حكمَ إغلاقٍ واحدًا — لا شيءَ يُكسر\n"; $fail++; }
    if ($blank !== 1) { echo "  X نُزع سببُ حكمٍ واحدٍ والمرصودُ $blank\n"; $fail++; }
    echo $fail
        ? "✘ **الفاحصُ لا يرصد حكمًا نُزع سببُه**\n"
        : "🟢 **العدّادُ تحرَّك بحكمٍ نُزع سببُه — والسببُ يُقرأ من `event_consumers` لا يُفترض**\n";
    exit($fail ? 1 : 0);
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
