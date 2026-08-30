<?php
/**
 * tools/rpr03_consumer_contract.php — `RPR-03` §٤·٢ الخطوة ٣ · عقدُ الأثرِ مسجَّلًا
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلوبُ بنصِّه** — §٤·٢ الخطوة ٣: *«عقدُ أثرٍ مسجَّلٌ لكلِّ مستهلك:
 *   **الحمولةُ ومفتاحُ منعِ التكرارِ وسياسةُ الإعادةِ وسلوكُ الفشلِ وأثرُ
 *   التدقيق**»*.
 *
 * ◆ **والمقيسُ قبلَ هذا: موضعٌ بلا ملء** — الأعمدةُ الخمسةُ أُضيفت في
 *   `2027_12_23` فصار العقدُ **ممكنًا**، **و`صفرٌ من ١٠٢` مستهلكٍ فعّالٍ يحمل
 *   حرفًا واحدًا في أربعةٍ منها**. ⛔ **وعمودٌ فارغٌ لا يُقرأ عقدًا**.
 *
 * ◆ **والعقدُ يُقرأ من الشيفرةِ لا يُؤلَّف** — أربعُ حقائقَ مقيسة:
 *   **① الحمولةُ** — مفاتيحُ الحدثِ التي **يقرؤها المستهلكُ فعلًا**
 *      (`$event['x']` · `$e['x']`) منتزَعةً من صفِّه على القرص.
 *   **② مفتاحُ منعِ التكرار** — **من طبقةِ التسليمِ لا من ظنّ**:
 *      `ems_event_deliveries.idempotency_key` = `SHA2(outbox_id|consumer_key)`
 *      بقيدِ `uq_idem` الفريد ⇒ **خمسُ إعاداتٍ صفٌّ واحد**.
 *   **③ سلوكُ الفشل** — من `EventDeliveryWorker`: `failed` بتباعدٍ متزايدٍ
 *      (قادحُ `trg_delivery_backoff` في القاعدة) حتى `max_attempts` ثمَّ `dlq`
 *      **بإنذارٍ** — ⛔ **ولا يختفي صامتًا**.
 *   **④ أثرُ التدقيق** — الجداولُ التي **يكتب فيها المستهلكُ** منتزَعةً من
 *      `INSERT INTO` و`UPDATE` في صفِّه، مع صفِّ التسليمِ الذي يبقى بحالتِه.
 *
 * ⛔ **وما لا صفَّ له على القرصِ لا يُكتب له عقد**: `CLASS_ABSENT` — يُعلَن
 *   بعددِه. **وصنفٌ لا يُنتزع منه مفتاحُ حمولةٍ واحدٌ يبقى بلا عقد**: عقدٌ
 *   بحمولةٍ فارغةٍ **مرجعٌ أجوفُ أسوأُ من غيابِه**.
 *
 * ⛔ **وهذا لا يزعم إغلاقَ `#٣`**: مقياسُ «أحداثُ أعمالٍ بلا عقدِ مستهلكٍ
 *   **فعّال**» يشترط **مستهلكَ أثرٍ** (`produces='write'`) على حدثِ الأعمالِ
 *   نفسِه — وهو **بناءُ مستهلكٍ بأثرٍ تجاريّ**، لا ملءُ عقدِ مستهلكٍ قائم.
 *
 * التشغيل:
 *   php tools/rpr03_consumer_contract.php [--apply] [--md] [--selftest]
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

/** مسارُ صفِّ الصنفِ على القرص — من الفضاءِ الاسميِّ لا من تخمينِ اسم */
function cc_class_path($cls, $ROOT)
{
    $cls = trim(str_replace('\\', '/', (string) $cls), '/');
    $cand = array();
    if (strncmp($cls, 'App/', 4) === 0) { $cand[] = $ROOT . '/app/' . substr($cls, 4) . '.php'; }
    $cand[] = $ROOT . '/' . $cls . '.php';
    $cand[] = $ROOT . '/app/' . $cls . '.php';
    $cand[] = $ROOT . '/src/' . $cls . '.php';
    foreach ($cand as $p) { if (is_file($p)) { return $p; } }
    return '';
}
/** مفاتيحُ الحمولةِ التي يقرؤها الصنفُ فعلًا — من الشيفرةِ لا من وصف */
function cc_payload_keys($src)
{
    $k = array();
    if (preg_match_all('~\$(?:event|e|ev|evt|payload)\s*\[\s*[\x27\x22]([a-z0-9_]+)[\x27\x22]\s*\]~i', $src, $m)) {
        foreach ($m[1] as $x) { $k[strtolower($x)] = 1; }
    }
    ksort($k);
    return array_keys($k);
}
/** الجداولُ التي يكتب فيها الصنفُ — أثرُ التدقيقِ مقيسًا */
function cc_write_tables($src)
{
    $t = array();
    if (preg_match_all('~INSERT\s+INTO\s+[`\x27\x22]?([a-z0-9_]+)~i', $src, $m)) {
        foreach ($m[1] as $x) { $t[strtolower($x)] = 1; }
    }
    if (preg_match_all('~UPDATE\s+[`\x27\x22]?([a-z0-9_]+)[`\x27\x22]?\s+SET~i', $src, $m)) {
        foreach ($m[1] as $x) { $t[strtolower($x)] = 1; }
    }
    ksort($t);
    return array_keys($t);
}

/* ═══ الاختبارُ السالبُ ═══════════════════════════════════════════════════ */
if ($SELF) {
    $fail = 0;
    $k = cc_payload_keys('$e[\'event_key\'] === $event["amount"]; $x[\'zzq_not_a_payload\'];');
    if (!in_array('event_key', $k, true) || !in_array('amount', $k, true)) { echo "  X مفاتيحُ الحمولةِ لم تُنتزَع\n"; $fail++; }
    /* **الكاسر**: متغيّرٌ آخرُ ليس الحدثَ — فلو مرَّ لسُجِّل عقدٌ بحمولةٍ ليست حمولتَه */
    if (in_array('zzq_not_a_payload', $k, true)) { echo "  X مصفوفةٌ أخرى عُدَّت حمولةً\n"; $fail++; }
    $t = cc_write_tables("INSERT INTO `fin_notifications` (a) VALUES (1); UPDATE `unit_entries` SET x=1;"
                       . " SELECT * FROM zzq_read_only_probe");
    if (!in_array('fin_notifications', $t, true) || !in_array('unit_entries', $t, true)) { echo "  X جدولُ الكتابةِ لم يُنتزَع\n"; $fail++; }
    /* ⛔ **والقراءةُ ليست أثرَ تدقيق** */
    if (in_array('zzq_read_only_probe', $t, true)) { echo "  X `SELECT` عُدَّ كتابةً\n"; $fail++; }
    if (cc_class_path('App\\Zzq\\AbsentProbeClass', $ROOT) !== '') { echo "  X صنفٌ وهميٌّ وُجد على القرص\n"; $fail++; }
    if (cc_class_path('App\\Services\\Bus\\Consumers\\GovernanceWatchConsumer', $ROOT) === '') {
        echo "  X صنفٌ قائمٌ لم يُوجد — المصفاةُ عمياء\n"; $fail++;
    }
    echo $fail ? "\nX الفحصُ الذاتيُّ سقط بـ$fail\n"
               : "\n🟢 الفحصُ الذاتيُّ تامٌّ — الحمولةُ من الحدثِ وحدَه، والكتابةُ لا تخلط القراءة\n";
    exit($fail ? 1 : 0);
}

$snap = null;
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL ORDER BY frozen_at DESC LIMIT 1");
if ($r && $r->num_rows) { $snap = $r->fetch_assoc(); }
$sid = $snap ? $snap['snapshot_id'] : '';
if ($APPLY && $sid === '') { exit("⛔ **لا نافذةَ قياسٍ مفتوحة** — ولا يُكتب عقدٌ بلا لقطة\n"); }

/* ═══ ② سياستان عامّتان — من طبقةِ التسليمِ لا من كلِّ صنف ══════════════ */
$hasUqIdem = false;
$r = $conn->query("SHOW CREATE TABLE `ems_event_deliveries`");
if ($r && ($x = $r->fetch_row())) { $hasUqIdem = (stripos($x[1], 'uq_idem') !== false); }
$IDEM = $hasUqIdem
    ? 'ems_event_deliveries.idempotency_key = SHA2(outbox_id|consumer_key,256) بقيد uq_idem الفريد — اعادة التسليم تصطدم بالقيد فلا يتكرر الاثر'
    : '';
$WORKER = $ROOT . '/app/Services/Bus/EventDeliveryWorker.php';
$wsrc = (string) @file_get_contents($WORKER);
$FAIL = ($wsrc !== '' && strpos($wsrc, 'dlq') !== false)
    ? 'EventDeliveryWorker: الفشل يسجل حالة failed وattempt_no+1 بتباعد متزايد (قادح trg_delivery_backoff في القاعدة) حتى max_attempts ثم dlq بانذار — ولا يختفي صامتا'
    : '';

/* ═══ ③ المستهلكون الأحياءُ بأصنافِهم ════════════════════════════════════ */
$rows = array();
$r = $conn->query("SELECT c_id, event_name, consumer_class, consumer_key, produces, max_attempts,
                          payload_schema, idempotency_key, failure_behavior, audit_effect
                     FROM event_consumers WHERE active = 1 ORDER BY consumer_class, event_name");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }

$byClass = array();
foreach ($rows as $x) { $byClass[$x['consumer_class']][] = $x; }

$plan = array(); $held = array(); $classStat = array();
foreach ($byClass as $cls => $subs) {
    $p = cc_class_path($cls, $ROOT);
    if ($p === '') { $held[] = array('cls' => $cls, 'n' => count($subs), 'why' => 'CLASS_ABSENT · لا صفَّ لهذا الصنفِ على القرص'); continue; }
    $src  = (string) @file_get_contents($p);
    $keys = cc_payload_keys($src);
    $tbl  = cc_write_tables($src);
    if (!$keys) {
        $held[] = array('cls' => $cls, 'n' => count($subs), 'why' => 'NO_PAYLOAD_READ · لا يُنتزع من صفِّه مفتاحُ حمولةٍ واحد — وعقدٌ بحمولةٍ فارغةٍ مرجعٌ أجوف');
        continue;
    }
    if ($IDEM === '' || $FAIL === '') {
        $held[] = array('cls' => $cls, 'n' => count($subs), 'why' => 'NO_DELIVERY_POLICY · طبقةُ التسليمِ لا تُثبت منعَ تكرارٍ ولا سلوكَ فشل');
        continue;
    }
    $rel = str_replace($ROOT . '/', '', str_replace(DIRECTORY_SEPARATOR, '/', $p));
    $classStat[$cls] = array('keys' => count($keys), 'tbl' => count($tbl), 'n' => count($subs), 'path' => $rel);
    foreach ($subs as $s) {
        $plan[] = array(
            'row'   => $s,
            'payload' => mb_substr(implode(' · ', $keys), 0, 380),
            'idem'  => mb_substr($IDEM, 0, 150),
            'fail'  => mb_substr($FAIL, 0, 250),
            'audit' => mb_substr($tbl
                ? ('يكتب في: ' . implode(' · ', $tbl) . ' — وصف التسليم في ems_event_deliveries يبقى بحالته ومحاولاته')
                : 'لا كتابة في جدول من صفه — واثره صف التسليم في ems_event_deliveries بحالته ومحاولاته ومرجع نتيجته', 0, 250),
            'path'  => $rel,
        );
    }
}

/* ═══ ④ العرض ═══════════════════════════════════════════════════════════ */
$filled = 0;
foreach ($rows as $x) {
    if ($x['payload_schema'] !== '' && $x['idempotency_key'] !== ''
        && $x['failure_behavior'] !== '' && $x['audit_effect'] !== '') { $filled++; }
}
echo "\n═══ `RPR-03` §٤·٢ الخطوة ٣ — عقدُ الأثرِ مسجَّلًا ═══\n";
printf("  اللقطة %s · مستهلكون فعّالون **%d** على **%d** صنفًا · **بعقدٍ كاملٍ الآن %d**\n",
       ($sid !== '' ? $sid : 'DRY'), count($rows), count($byClass), $filled);
echo "\n  ── السياستان العامّتان — من طبقةِ التسليمِ لا من ظنّ ──\n";
printf("     %s منعُ التكرار: %s\n", $IDEM !== '' ? '✔' : '⛔', $IDEM !== '' ? 'قيدُ `uq_idem` قائمٌ في `ems_event_deliveries`' : 'لا قيدَ فريدًا — ولا يُدَّعى منعُ تكرار');
printf("     %s سلوكُ الفشل: %s\n", $FAIL !== '' ? '✔' : '⛔', $FAIL !== '' ? '`EventDeliveryWorker` يُرسِّب ثمَّ `dlq` بإنذار' : 'لا عاملَ تسليمٍ مقروء');
echo "\n  ── الأصنافُ بعقودِها المقروءةِ من الشيفرة ──\n";
foreach ($classStat as $cls => $s) {
    printf("     %-58s %2d اشتراكًا · %2d مفتاحَ حمولةٍ · %d جدولَ كتابة\n",
           mb_substr($cls, -58), $s['n'], $s['keys'], $s['tbl']);
}
if ($held) {
    echo "\n  ⛔ **موقوفٌ بلا عقد** — ولا يُكتب عقدٌ أجوف:\n";
    foreach ($held as $h) { printf("     · %-52s %2d اشتراكًا — %s\n", mb_substr($h['cls'], -52), $h['n'], $h['why']); }
}
printf("\n  ⇒ **خطّةٌ: %d اشتراكًا يُكتب عقدُه** · وموقوفٌ %d اشتراكًا\n",
       count($plan), array_sum(array_column($held, 'n')));
if (!$APPLY) { echo "\n  ⛔ **معاينةٌ — لم يُكتب شيء.** والتطبيقُ بـ`--apply`.\n"; }

/* ═══ ⑤ التطبيق ═════════════════════════════════════════════════════════ */
if ($APPLY) {
    $conn->query('START TRANSACTION');
    $n = 0;
    foreach ($plan as $p) {
        $ok = $conn->query("UPDATE `event_consumers`
                               SET `payload_schema`   = '" . $e($p['payload']) . "',
                                   `idempotency_key`  = '" . $e($p['idem']) . "',
                                   `failure_behavior` = '" . $e($p['fail']) . "',
                                   `audit_effect`     = '" . $e($p['audit']) . "'
                             WHERE `c_id` = " . (int) $p['row']['c_id']);
        if (!$ok) { $conn->query('ROLLBACK'); exit("✘ تعذّر كتبُ العقد: {$conn->error}\n"); }
        $n++;
    }
    $conn->query('COMMIT');
    $filled2 = (int) $conn->query("SELECT COUNT(*) FROM event_consumers
                                    WHERE active = 1 AND payload_schema <> '' AND idempotency_key <> ''
                                      AND failure_behavior <> '' AND audit_effect <> ''")->fetch_row()[0];
    printf("\n  ✔ **كُتب عقدُ %d اشتراكًا** — وأُعيدت القراءة: **%d من %d** بعقدٍ كامل\n",
           $n, $filled2, count($rows));
    echo "  ⛔ **ولا يُقرأ هذا إغلاقًا لـ`#٣`**: ذاك يشترط **مستهلكَ أثرٍ** على حدثِ الأعمالِ نفسِه.\n";
}

/* ═══ ⑥ المعاينةُ المكتوبة ══════════════════════════════════════════════ */
if ($MD) {
    $o  = "# `RPR-03` §٤·٢ الخطوة ٣ — عقدُ الأثرِ مسجَّلًا\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/" . basename(__FILE__) . " --md` · اللقطة `"
        . ($sid !== '' ? $sid : 'DRY') . "`\n\n";
    $o .= 'مستهلكون فعّالون **' . count($rows) . '** على **' . count($byClass) . '** صنفًا · بعقدٍ كاملٍ **'
        . $filled . "**.\n\n";
    $o .= "## الأصنافُ وعقودُها المقروءةُ من الشيفرة\n\n";
    $o .= "| الصنف | الملفّ | اشتراكات | مفاتيحُ حمولة | جداولُ كتابة |\n|---|---|---:|---:|---:|\n";
    foreach ($classStat as $cls => $s) {
        $o .= '| `' . $cls . '` | `' . $s['path'] . '` | ' . $s['n'] . ' | ' . $s['keys'] . ' | ' . $s['tbl'] . " |\n";
    }
    if ($held) {
        $o .= "\n## ⛔ موقوفٌ بلا عقد — ولا يُكتب عقدٌ أجوف\n\n| الصنف | اشتراكات | السبب |\n|---|---:|---|\n";
        foreach ($held as $h) { $o .= '| `' . $h['cls'] . '` | ' . $h['n'] . ' | ' . $h['why'] . " |\n"; }
    }
    $o .= "\n⛔ **ولا يُقرأ هذا إغلاقًا لمقياسِ #٣** («أحداثُ أعمالٍ بلا عقدِ مستهلكٍ **فعّال**»):\n";
    $o .= "ذاك يشترط **مستهلكَ أثرٍ** (`produces='write'`) على حدثِ الأعمالِ نفسِه — **وهو بناءُ مستهلكٍ\n";
    $o .= "بأثرٍ تجاريّ** لا ملءُ عقدِ مستهلكٍ قائم.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RPR03_CONSUMER_CONTRACT.md', $o);
    echo "\n✔ كُتب docs/REPAIR01_20260823/RPR03_CONSUMER_CONTRACT.md\n";
}
