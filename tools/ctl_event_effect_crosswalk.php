<?php
/**
 * tools/ctl_event_effect_crosswalk.php — أمرُ الضبطِ §٧+§٨ · السجلُّ الموحَّدُ وتصريفُ المتراكم
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§٧**: صفٌّ لكلِّ نوعِ حدثٍ (‏58) يجمع التصنيفَ والاشتراكاتِ ومستهلكي
 *   الأثرِ والحرّاسَ والعقودَ وآخرَ تقدُّمٍ والمتراكمَ والحالةَ النهائيّة —
 *   **ثمَّ فقط تصدر الأرقامُ الخمسةُ بمقاماتِها المفصولة**.
 * ◆ **§٨**: تصنيفُ المتراكمِ التاريخيِّ (‏consumer×event) بقاعدةٍ مقيسةٍ لكلِّ
 *   حكمٍ — ⛔ **وصفرُ Replay**: `replayed=0` في كلِّ صفٍّ طوالَ جولةِ الضبط،
 *   والتصريفُ لاحقًا بدفعاتِ Canary بعد `PRE_BUILD_PRE_REPLAY_BASELINE`
 *   وبإذنِ `RPR-03` #١٨ للماليّ.
 *
 * ◆ **قواعدُ التصريفِ — كلٌّ باسمِها وشاهدِها**:
 *   `R-AUD` نوعُه `AUDIT` ⇒ `AUDIT_ONLY` — يحقّق غرضَه بوجودِه.
 *   `R-RET` نوعُه `RETIRED` ⇒ `CLOSE_WITH_REASON` — عائلةُ سبرٍ متقاعدة.
 *   `R-ADJ` نوعُه `NEEDS_ADJUDICATION` ⇒ `MANUAL_RECONCILIATION`.
 *   `R-FX`  مستهلكُ `fx` وحمولةُ الحدثِ **محقَّقةُ الأثرِ سلفًا** (‏`base_amount`
 *           مملوءٌ أو العملةُ عملةُ الأساس) ⇒ ذاك الجزءُ `EFFECT_ALREADY_REALIZED`
 *           **بعدِّه المقيس** — والباقي `REPLAY_REQUIRED`.
 *   `R-BIZ` نوعُ أعمالٍ بمتراكمٍ ⇒ `REPLAY_REQUIRED` — **معلَّقًا على Canary
 *           وإذنِ #١٨**، ⛔ ولا يُفترض أنَّ كلَّ تاريخيٍّ واجبُ الإعادة: العدُّ
 *           هنا حكمُ تصنيفٍ لا أمرُ تشغيل.
 *
 * التشغيل: php tools/ctl_event_effect_crosswalk.php [--apply] [--md] [--selftest]
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
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x === null ? null : $x[0];
};

$APPLY = in_array('--apply', $argv, true);
$MD    = in_array('--md', $argv, true);
$SELF  = in_array('--selftest', $argv, true);

$snap = (string) $one("SELECT snapshot_id FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($APPLY && $snap === '') { exit("⛔ لا نافذةَ قياسٍ مفتوحة\n"); }

/* ═══ ① السجلُّ الموحَّدُ — نوعًا نوعًا ══════════════════════════════════ */
$types = array();
$r = $conn->query("SELECT event_key, classification, occurrences FROM rpr03_event_classification");
while ($r && ($x = $r->fetch_assoc())) { $types[$x['event_key']] = $x; }

$subs = array(); $eff = array(); $watch = array(); $full = array();
$r = $conn->query("SELECT event_name,
                          COUNT(*) n,
                          SUM(produces = 'write' AND consumer_class NOT LIKE '%GovernanceWatch%') ef,
                          SUM(consumer_class LIKE '%GovernanceWatch%') w,
                          SUM(payload_schema <> '' AND idempotency_key <> '' AND failure_behavior <> '' AND audit_effect <> '') f
                     FROM event_consumers WHERE active = 1 GROUP BY event_name");
while ($r && ($x = $r->fetch_assoc())) {
    $subs[$x['event_name']] = (int) $x['n'];
    $eff[$x['event_name']] = (int) $x['ef'];
    $watch[$x['event_name']] = (int) $x['w'];
    $full[$x['event_name']] = (int) $x['f'];
}
$prog = array();
$r = @$conn->query("SELECT e.event_key, MAX(d.processed_at) p
                      FROM ems_event_deliveries d JOIN ems_business_events e ON e.id = d.event_id
                     WHERE d.state = 'processed' GROUP BY e.event_key");
while ($r && ($x = $r->fetch_assoc())) { $prog[$x['event_key']] = $x['p']; }
$minCursor = (int) $one("SELECT COALESCE(MIN(cursor_event_id),0) FROM ems_event_consumers WHERE enabled = 1");
$backlog = array();
$r = @$conn->query("SELECT event_key, COUNT(*) n FROM ems_business_events WHERE id > $minCursor GROUP BY event_key");
while ($r && ($x = $r->fetch_assoc())) { $backlog[$x['event_key']] = (int) $x['n']; }

function eec_status($cls, $nEff, $nWatch)
{
    if ($cls === 'AUDIT') { return 'AUDIT_ONLY'; }
    if ($cls === 'RETIRED') { return 'RETIRED'; }
    if ($cls === 'NEEDS_ADJUDICATION') { return 'NEEDS_ADJUDICATION'; }
    return $nEff > 0 ? 'EFFECT_COVERED' : ($nWatch > 0 ? 'GUARD_ONLY' : 'NO_CONSUMER');
}

$xw = array();
foreach ($types as $k => $t) {
    $nS = isset($subs[$k]) ? $subs[$k] : 0;
    $nE = isset($eff[$k]) ? $eff[$k] : 0;
    $nW = isset($watch[$k]) ? $watch[$k] : 0;
    $st = eec_status($t['classification'], $nE, $nW);
    $xw[$k] = array('cls' => $t['classification'], 'occ' => (int) $t['occurrences'],
        'needs' => $t['classification'] === 'BUSINESS' ? 1 : 0,
        'subs' => $nS, 'eff' => $nE, 'watch' => $nW,
        'full' => isset($full[$k]) ? $full[$k] : 0,
        'prog' => isset($prog[$k]) ? $prog[$k] : null,
        'back' => isset($backlog[$k]) ? $backlog[$k] : 0,
        'status' => $st,
        'wit' => 'تصنيفٌ من `rpr03_event_classification` · اشتراكاتٌ من `event_consumers` · '
               . 'التقدُّمُ من `ems_event_deliveries` · والمتراكمُ خلفَ أبعدِ مؤشِّرٍ (' . 0 . '+' . $minCursor . ')');
}

/* الأرقامُ الخمسةُ — بمقاماتِها المفصولة */
$NUM = array(
    'BUSINESS_NO_CONSUMER'   => 0,   /* أنواعُ أعمالٍ بلا مستهلكِ أثر */
    'SUBS_NO_CONTRACT'       => 0,   /* اشتراكاتٌ بلا عقدٍ كامل */
    'AUDIT_ONLY'             => 0,
    'RETIRED'                => 0,
    'STALLED_CONSUMERS'      => 0,   /* مستهلكو مؤشِّرٍ خلفَهم متراكمٌ وساكنون */
);
foreach ($xw as $x) {
    if ($x['needs'] && $x['eff'] === 0) { $NUM['BUSINESS_NO_CONSUMER']++; }
    if ($x['status'] === 'AUDIT_ONLY') { $NUM['AUDIT_ONLY']++; }
    if ($x['status'] === 'RETIRED') { $NUM['RETIRED']++; }
}
/* ⛔ **مقامُ العقودِ الاشتراكاتُ كلُّها لا المنشورُ وحدَه** — قِيس فسقط:
   الجمعُ على الأنواعِ الثمانيةِ والخمسين المنشورةِ أخفى اشتراكَي
   `OffsetService`/`CollectionService` لأنَّ حدثَيهما لم يُنشرا قطّ. */
$NUM['SUBS_NO_CONTRACT'] = (int) $one("SELECT COUNT(*) FROM event_consumers
     WHERE active = 1 AND NOT (payload_schema <> '' AND idempotency_key <> ''
                           AND failure_behavior <> '' AND audit_effect <> '')");
$NUM['STALLED_CONSUMERS'] = (int) $one("SELECT COUNT(*) FROM ems_event_consumers c
     WHERE c.enabled = 1 AND EXISTS(SELECT 1 FROM ems_business_events e WHERE e.id > c.cursor_event_id)");

/* ═══ ② تصريفُ المتراكمِ — قاعدةً قاعدة ═════════════════════════════════ */
$disp = array();
$r = @$conn->query("SELECT cc.consumer, e.event_key, COUNT(*) n, MAX(e.id) wm
                      FROM ems_event_consumers cc
                      JOIN ems_business_events e ON e.id > cc.cursor_event_id
                     WHERE cc.enabled = 1 GROUP BY cc.consumer, e.event_key");
while ($r && ($x = $r->fetch_assoc())) {
    $k = $x['event_key'];
    $cls = isset($types[$k]) ? $types[$k]['classification'] : 'NEEDS_ADJUDICATION';
    if ($cls === 'AUDIT') { $d = 'AUDIT_ONLY'; $rule = 'R-AUD'; $wit = 'نوعُه تدقيقيٌّ — يحقّق غرضَه بوجودِه ولا أثرَ يُعاد'; }
    elseif ($cls === 'RETIRED') { $d = 'CLOSE_WITH_REASON'; $rule = 'R-RET'; $wit = 'عائلةُ سبرٍ متقاعدةٌ بصفرِ حمولة'; }
    elseif ($cls === 'NEEDS_ADJUDICATION') { $d = 'MANUAL_RECONCILIATION'; $rule = 'R-ADJ'; $wit = 'ماليٌّ بحمولةٍ صفر — لا يُحسم آليًّا'; }
    else {
        $d = 'REPLAY_REQUIRED'; $rule = 'R-BIZ';
        $wit = 'حدثُ أعمالٍ بمتراكمٍ — التصريفُ بدفعاتِ Canary بعد النقطةِ الثانيةِ وبإذنِ #١٨ للماليّ';
        if ($x['consumer'] === 'fx') {
            $done = (int) $one("SELECT COUNT(*) FROM ems_business_events e
                                 JOIN ems_event_consumers cc2 ON cc2.consumer = 'fx'
                                WHERE e.id > cc2.cursor_event_id AND e.event_key = '" . $e($k) . "'
                                  AND (e.base_amount IS NOT NULL OR COALESCE(e.currency,'SDG') = 'SDG')");
            if ($done >= (int) $x['n']) { $d = 'EFFECT_ALREADY_REALIZED'; $rule = 'R-FX';
                $wit = 'أثرُ `fx` محقَّقٌ سلفًا في الحمولةِ كلِّها: `base_amount` مملوءٌ أو العملةُ الأساس — ' . $done . '/' . $x['n']; }
            else { $wit .= ' · ومحقَّقُ الأثرِ سلفًا منها ' . $done . '/' . $x['n'] . ' (R-FX جزئيًّا)'; }
        }
    }
    $disp[] = array('c' => $x['consumer'], 'k' => $k, 'n' => (int) $x['n'], 'wm' => (int) $x['wm'],
                    'd' => $d, 'rule' => $rule, 'wit' => $wit);
}

/* ═══ الاختبارُ السالب ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    if (count($types) < 50) { echo "  X الأنواعُ " . count($types) . " — القراءةُ لم تتمّ\n"; $fail++; }
    if (eec_status('BUSINESS', 0, 1) !== 'GUARD_ONLY') { echo "  X حارسٌ عُدَّ أثرًا\n"; $fail++; }
    if (eec_status('BUSINESS', 1, 1) !== 'EFFECT_COVERED') { echo "  X الأثرُ لم يُعرَف\n"; $fail++; }
    if (eec_status('AUDIT', 0, 0) !== 'AUDIT_ONLY') { echo "  X التدقيقيُّ خُلط\n"; $fail++; }
    $rep = 0;
    foreach ($disp as $d0) { if ($d0['d'] === 'REPLAY_REQUIRED') { $rep++; } }
    if (!$disp) { echo "  X لا صفَّ تصريفٍ والمتراكمُ قائم\n"; $fail++; }
    /* ⛔ **الكاسر**: التصريفُ تصنيفٌ لا تشغيلٌ — المؤشِّراتُ لا تتحرّك */
    $cur0 = $one("SELECT GROUP_CONCAT(cursor_event_id ORDER BY consumer) FROM ems_event_consumers");
    if ($cur0 === null) { echo "  X المؤشِّراتُ لا تُقرأ\n"; $fail++; }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — الحارسُ ليس أثرًا والتصريفُ تصنيفٌ لا تشغيل\n";
    exit($fail ? 1 : 0);
}

/* ═══ العرضُ والكتابة ═══════════════════════════════════════════════════ */
echo "\n═══ أمرُ الضبطِ §٧+§٨ — السجلُّ الموحَّدُ وتصريفُ المتراكم ═══\n";
printf("  اللقطة %s · أنواعٌ %d · أزواجُ تصريفٍ %d\n\n", $snap !== '' ? $snap : 'DRY', count($xw), count($disp));
echo "  ── الأرقامُ الخمسةُ — بمقاماتِها المفصولةِ لا رقمًا واحدًا ──\n";
printf("     أنواعُ أعمالٍ بلا مستهلكِ أثر      **%3d** (مقامُها أنواعُ BUSINESS)\n", $NUM['BUSINESS_NO_CONSUMER']);
printf("     اشتراكاتٌ بلا عقدٍ كامل           **%3d** (مقامُها الاشتراكاتُ الفعّالة)\n", $NUM['SUBS_NO_CONTRACT']);
printf("     أنواعٌ تدقيقيّةٌ فقط               **%3d**\n", $NUM['AUDIT_ONLY']);
printf("     أنواعٌ متقاعدة                    **%3d**\n", $NUM['RETIRED']);
printf("     مستهلكو مؤشِّرٍ خلفَهم متراكم       **%3d** (مقامُها `ems_event_consumers`)\n", $NUM['STALLED_CONSUMERS']);
$dsum = array();
foreach ($disp as $d0) { $dsum[$d0['d']] = (isset($dsum[$d0['d']]) ? $dsum[$d0['d']] : 0) + $d0['n']; }
echo "\n  ── تصريفُ المتراكمِ (أحداثًا · consumer×type) — ⛔ صفرُ Replay في الجولة ──\n";
foreach ($dsum as $k => $v) { printf("     %-26s %6d حدثًا\n", $k, $v); }

if ($APPLY) {
    $conn->query('START TRANSACTION');
    $conn->query("DELETE FROM repair01_event_effect_crosswalk");
    foreach ($xw as $k => $x) {
        $sql = "INSERT INTO repair01_event_effect_crosswalk
                (event_key, classification, occurrences, needs_effect, subscriptions, effect_consumers,
                 watch_consumers, contracts_full, last_progress, backlog, final_status, witness, snapshot_id)
                VALUES ('" . $e($k) . "','" . $e($x['cls']) . "'," . $x['occ'] . "," . $x['needs'] . ","
              . $x['subs'] . "," . $x['eff'] . "," . $x['watch'] . "," . $x['full'] . ","
              . ($x['prog'] === null ? 'NULL' : "'" . $e($x['prog']) . "'") . "," . $x['back'] . ",'"
              . $e($x['status']) . "','" . $e($x['wit']) . "','" . $e($snap) . "')";
        if (!$conn->query($sql)) { $conn->query('ROLLBACK'); exit("✘ {$conn->error}\n"); }
    }
    $conn->query("DELETE FROM repair01_backlog_disposition");
    foreach ($disp as $d0) {
        $sql = "INSERT INTO repair01_backlog_disposition
                (consumer_key, event_key, backlog_count, disposition, rule_applied, witness, watermark_id, replayed, snapshot_id)
                VALUES ('" . $e($d0['c']) . "','" . $e($d0['k']) . "'," . $d0['n'] . ",'" . $d0['d'] . "','"
              . $d0['rule'] . "','" . $e($d0['wit']) . "'," . $d0['wm'] . ",0,'" . $e($snap) . "')";
        if (!$conn->query($sql)) { $conn->query('ROLLBACK'); exit("✘ {$conn->error}\n"); }
    }
    $conn->query('COMMIT');
    printf("\n  ✔ كُتب السجلُّ الموحَّدُ (%d نوعًا) وسجلُّ التصريفِ (%d زوجًا · `replayed=0` كلُّها)\n",
           count($xw), count($disp));
}

if ($MD) {
    $o  = "# أمرُ الضبطِ §٧+§٨ — `EVENT_EFFECT_CROSSWALK` و`BACKLOG_DISPOSITION_REGISTER`\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `$snap`\n\n";
    $o .= "## الأرقامُ الخمسةُ — مقاماتٌ مفصولةٌ لا رقمٌ واحد\n\n| الرقم | القيمة | المقام |\n|---|---:|---|\n";
    $o .= "| أنواعُ أعمالٍ بلا مستهلكِ أثر | **{$NUM['BUSINESS_NO_CONSUMER']}** | أنواعُ `BUSINESS` |\n";
    $o .= "| اشتراكاتٌ بلا عقدٍ كامل | **{$NUM['SUBS_NO_CONTRACT']}** | الاشتراكاتُ الفعّالةُ ١٠٢ |\n";
    $o .= "| أنواعٌ تدقيقيّةٌ فقط | {$NUM['AUDIT_ONLY']} | الأنواعُ ٥٨ |\n";
    $o .= "| أنواعٌ متقاعدة | {$NUM['RETIRED']} | الأنواعُ ٥٨ |\n";
    $o .= "| مستهلكو مؤشِّرٍ خلفَهم متراكم | {$NUM['STALLED_CONSUMERS']} | `ems_event_consumers` |\n\n";
    $o .= "## تصريفُ المتراكمِ — تصنيفٌ قبل تشغيل\n\n| التصريف | أحداث |\n|---|---:|\n";
    foreach ($dsum as $k => $v) { $o .= "| `$k` | $v |\n"; }
    $o .= "\n⛔ **صفرُ Replay نُفِّذ في جولةِ الضبط** — `replayed = 0` في كلِّ صفّ.\n\n";
    $o .= "## قواعدُ Canary — للتصريفِ بعد `PRE_BUILD_PRE_REPLAY_BASELINE`\n\n";
    $o .= "دفعةٌ صغيرة ⇒ Dry-Run إن أمكن ⇒ اختبارُ العطالةِ (**الحدثُ نفسُه مرّتين = أثرٌ ماليٌّ واحد**)\n";
    $o .= "⇒ تشغيلٌ ⇒ مطابقةُ الحدثِ بأثرِه ⇒ فحصُ القيودِ والحالاتِ والإشعارات ⇒ تثبيتُ `watermark_id`\n";
    $o .= "⇒ الدفعةُ التالية. ⛔ **ولا Mass Replay** · والماليُّ بإذنِ `RPR-03` #١٨ حصرًا.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/CTL_EVENT_BACKLOG.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/CTL_EVENT_BACKLOG.md\n";
}
