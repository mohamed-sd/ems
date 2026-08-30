<?php
/**
 * tools/ctl_backlog_reconcile.php — مصالحةُ المتراكمِ بفارقِ صفر
 * ═══════════════════════════════════════════════════════════════════════════
 * **أمرُ الاستئنافِ الثاني**: «أكمل Backlog reconciliation بعددِ الأحداثِ
 * في كلِّ Disposition بفارقِ صفرٍ قبل أيِّ Canary Replay».
 *
 * ◆ الهويّةُ المقيسة لكلِّ مستهلِكٍ (بمؤشِّرِه الحيِّ وخاتمِ الحكم):
 *   عدُّ الأحداثِ الحيِّ في النافذةِ (cursor < id <= watermark) لكلِّ نوعٍ
 *   **يساوي** `backlog_count` المسجَّلَ — صفًّا صفًّا وفارقًا صفرًا.
 * ◆ وثلاثُ فضلاتٍ تُعَدُّ وتُسمّى ولا تُطوى:
 *   - `NEW_SINCE_RULING`: أحداثٌ بعد الخاتمِ — ليست خللًا بل وقتًا.
 *   - `UNRULED_TYPE`: نوعٌ في النافذةِ بلا صفِّ حكمٍ — **يجب أن يكون صفرًا**.
 *   - `ROW_DIFF`: فرقُ صفٍّ مسجَّلٍ عن الحيِّ — **يجب أن يكون صفرًا**.
 *
 * التشغيل: php tools/ctl_backlog_reconcile.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');

/* حكمُ السجلِّ: (مستهلِكٌ · نوعٌ) ⇒ عددٌ وخاتمٌ وتصريف */
$reg = array(); $wm = array();
$r = $conn->query("SELECT consumer_key, event_key, backlog_count, disposition, watermark_id FROM repair01_backlog_disposition");
while ($x = $r->fetch_assoc()) {
    $reg[$x['consumer_key']][$x['event_key']] = array((int) $x['backlog_count'], (string) $x['disposition']);
    $wm[$x['consumer_key']] = max(isset($wm[$x['consumer_key']]) ? $wm[$x['consumer_key']] : 0, (int) $x['watermark_id']);
}
$cur = array();
$r = $conn->query("SELECT consumer, cursor_event_id FROM ems_event_consumers");
while ($x = $r->fetch_assoc()) { $cur[$x['consumer']] = (int) $x['cursor_event_id']; }

$sumByDisp = array(); $regTotal = 0;
$rowDiff = 0; $unruled = 0; $newSince = array();
printf("\n═══ مصالحةُ المتراكم — %d مستهلِكًا ═══\n", count($reg));
foreach ($reg as $ck => $rows) {
    $c0 = isset($cur[$ck]) ? $cur[$ck] : 0;
    $w0 = $wm[$ck];
    /* الحيُّ في نافذةِ الحكم */
    $live = array();
    $q = $conn->query("SELECT event_key, COUNT(*) FROM ems_business_events
                        WHERE id > $c0 AND id <= $w0 GROUP BY event_key");
    while ($q && ($y = $q->fetch_row())) { $live[(string) $y[0]] = (int) $y[1]; }
    $q = $conn->query("SELECT COUNT(*) FROM ems_business_events WHERE id > $w0");
    $newSince[$ck] = (int) $q->fetch_row()[0];
    $cDiff = 0;
    foreach ($rows as $ek => $meta) {
        list($n0, $disp) = $meta;
        $regTotal += $n0;
        $sumByDisp[$disp] = (isset($sumByDisp[$disp]) ? $sumByDisp[$disp] : 0) + $n0;
        $lv = isset($live[$ek]) ? $live[$ek] : 0;
        if ($lv !== $n0) {
            $cDiff++;
            printf("  ✘ %s · %s: مسجَّل %d وحيٌّ %d (فارق %d)\n", $ck, $ek, $n0, $lv, $lv - $n0);
        }
        unset($live[$ek]);
    }
    foreach ($live as $ek => $lv) {
        $unruled += $lv;
        printf("  ⛔ %s · نوعٌ بلا حكمٍ في النافذة: %s ×%d\n", $ck, $ek, $lv);
    }
    $rowDiff += $cDiff;
    printf("  %s %-24s نافذة (%d..%d] · فروقُ صفوفٍ %d · بعد الخاتم %d\n",
           $cDiff === 0 ? '✔' : '✘', $ck, $c0, $w0, $cDiff, $newSince[$ck]);
}
echo "\n  ── الأحداثُ بكلِّ تصريفٍ (البسطُ من السجلِّ والمجموعُ هويّتُه) ──\n";
$s = 0;
foreach ($sumByDisp as $d => $n0) { printf("     %-24s %d\n", $d, $n0); $s += $n0; }
printf("     %-24s %d\n", 'المجموع', $s);
printf("  الفارق (مجموعُ التصريفاتِ − مجموعُ السجلِّ) = %d\n", $s - $regTotal);
printf("\n  الحكم: فروقُ صفوفٍ %d · أنواعٌ بلا حكمٍ %d · فارقُ تصريفٍ %d ⇒ %s\n",
       $rowDiff, $unruled, $s - $regTotal,
       ($rowDiff === 0 && $unruled === 0 && $s === $regTotal)
           ? '🟢 مصالَحٌ بفارقِ صفرٍ — شرطُ ما قبلَ الكناري قائم'
           : '🔴 غيرُ مصالَحٍ — لا كناري قبل الصفر');
echo "  ⛔ وما بعد الخاتمِ وقتٌ لا خلل — يُحكَم في جولةِ تصريفٍ تاليةٍ بخاتمٍ جديد\n";
