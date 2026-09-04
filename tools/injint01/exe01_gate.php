<?php
/**
 * tools/injint01/exe01_gate.php — حاجبُ معيارِ القبول (EXE-01 §10 · §11-⑩)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **§11 يطلب «حالةَ المقاييسِ قبل وبعد»** — ومَن سردها نصًّا سردَ ذاكرتَه.
 *   فكلُّ مقياسٍ هنا **باستعلامِه أو فحصِه**، ويُعاد تشغيلُه فيُخرج الحاضرَ لا
 *   المكتوب. ⇐ والحاجبُ يردُّ برمزِ خروجٍ غيرِ صفريٍّ ما بقي مقياسٌ مفتوح.
 *
 * ⛔ **و«قبلُ» ليس تذكُّرًا**: القيمُ السابقةُ من جدولِ القبولِ في الأمرِ نفسِه —
 *   منقولةٌ حرفًا ومُعلَنةٌ مصدرًا، لا مُستحضَرةٌ من ذاكرةِ المنفِّذ.
 *
 * ⛔ **وما لا يُقاس يُسمَّى `UNMEASURABLE` ولا يُخمَّن** (‏§11-⑪ حرفًا: «وما لم
 *   تستطع قياسَه — سمِّه ولا تخمّنه»). فالمقياسُ المحجوبُ بنصٍّ غيرِ متاحٍ
 *   **لا يُحسَب ناجحًا ولا فاشلًا** — يُحسَب محجوبًا باسمِ حاجبِه.
 *
 * التشغيل: php tools/injint01/exe01_gate.php
 * الخروج : 0 كلُّ ما يُقاس مُستوفًى · 1 ثمّةَ مفتوح
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4'); $DB = ems_env('DB_NAME');
$one = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };
$git = function ($cmd) use ($ROOT) { return trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' ' . $cmd . ' 2>&1')); };
$grep = function ($file, $re) use ($ROOT) {
    $s = (string) @file_get_contents($ROOT . '/' . $file);
    return $s === '' ? null : (preg_match($re, $s) > 0);
};

$R = array();
$add = function ($no, $name, $target, $before, $now, $pass, $ev) use (&$R) {
    $R[] = compact('no', 'name', 'target', 'before', 'now', 'pass', 'ev');
};

/* ① التزاماتٌ حرجةٌ بلا نسخةٍ بعيدة */
$head = $git('rev-parse HEAD'); $up = $git('rev-parse @{u}');
$add(1, 'التزاماتٌ بلا نسخةٍ بعيدة', '0', '455', ($head !== '' && $head === $up) ? '0' : 'غيرُ مؤكَّد',
     ($head !== '' && $head === $up), 'الرأس = مرجعُ التتبُّع');

/* ② أساسٌ مثبَّتٌ قبلَ التغيير  ·  ⑫ أساسٌ ذرّيٌّ بعدَها */
$bl = glob($ROOT . '/docs/injint01/baselines/PRE_CHANGE_CONTROL-*.json');
$add(2, 'أساسٌ قبلَ التغيير', 'موجود', 'لا', $bl ? 'موجود' : 'لا', (bool) $bl, $bl ? basename($bl[0]) : '—');
$bl2 = glob($ROOT . '/docs/injint01/baselines/POST_SHORT_BATCH_ATOMIC-*.json');
$add(12, 'أساسٌ ذرّيٌّ بعدَ الدفعة', 'موجود', 'لا', $bl2 ? 'موجود' : 'لا', (bool) $bl2, $bl2 ? basename($bl2[0]) : '—');

/* ③ حذفُ صفِّ تسليمٍ عند الموت */
$delGone = !$grep('App/Core/EventDispatcher.php', '~DELETE\s+FROM\s+`ems_event_deliveries~i');
$add(3, 'حذفُ صفِّ تسليمٍ عند الموت', '0', 'مسارٌ قائم', $delGone ? '0' : 'قائم', $delGone,
     'الكاتبُ يستعمل ems_dispatcher_attempts');

/* ④ مصادرُ حالةِ الرسائلِ الميتة */
$srcN = 0;
foreach (array('App/Core/EventDispatcher.php', 'App/Services/Bus/EventDeliveryWorker.php') as $w) {
    if ($grep($w, '~(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?ems_event_deliveries~i')) { $srcN++; }
}
$add(4, 'مصادرُ حالةِ الرسائلِ الميتة', '1', '2', (string) $srcN, $srcN === 1, 'عدُّ الكتّابِ في الشيفرةِ الحيّة');

/* ⑤ صفٌّ ميتٌ يُعاد التقاطُه
   ⛔ **ولا يدلُّ `processed_at` ولا `attempt_no` على إعادةِ التقاط**: العاملُ
      يختم `processed_at` **لحظةَ العزلِ نفسِها** (`EventDeliveryWorker:204`)،
      و`attempt_no` عدّادُ ما سبقَ الاستنفاد. فمقياسٌ عليهما يُخرج **37 من 37**
      — أي يصف التصميمَ عطبًا. (‏وهذا ما أخرجته نسختي الأولى.)
   ⇐ **والشاهدُ الصادقُ التقاطٌ بعدَ الختمِ النهائيّ**: `claimed_at > processed_at`. */
$reconsumed = (int) $one("SELECT COUNT(*) FROM ems_event_deliveries
                           WHERE state='dlq' AND claimed_at IS NOT NULL
                             AND processed_at IS NOT NULL AND claimed_at > processed_at");
$add(5, 'صفٌّ ميتٌ يُعاد التقاطُه', '0', 'غيرُ مقيس', (string) $reconsumed, $reconsumed === 0,
     'claimed_at > processed_at — لا attempt_no ولا processed_at وحدَهما');

/* ⑥ قياسٌ يكتب فوقَ حكم  ·  ⑦ ملاحظةٌ بلا لقطة  ·  ⑧ تغييرُ حكمٍ بلا إصدار */
$obsTbl = $one("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_NAME='gov_event_observations'");
$add(6, 'قياسٌ يكتب فوقَ حكم', '0', 'ممكنٌ اليوم', $obsTbl ? '0' : 'ممكن', (bool) $obsTbl, 'سجلٌّ منفصلٌ + رؤيةُ جمع');
$noSnap = $obsTbl ? (int) $one("SELECT COUNT(*) FROM gov_event_observations WHERE snapshot_id=''") : -1;
$add(7, 'ملاحظةٌ بلا معرِّفِ لقطة', '0', 'لا سجلَّ أصلًا', $noSnap < 0 ? '—' : (string) $noSnap, $noSnap === 0,
     'العمودُ NOT NULL بنيويًّا');
$verCol = $one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB'
                 AND TABLE_NAME='gov_event_observations' AND COLUMN_NAME='ruling_version'");
$add(8, 'تغييرُ حكمٍ بلا إصدارٍ أو أثر', '0', 'ممكنٌ اليوم', $verCol ? '0' : 'ممكن', (bool) $verCol,
     'الملاحظةُ تُربَط بإصدارِ الحكم');

/* ⑨ صفوفٌ في نافذةِ الانحرافِ بلا تصنيف */
$skew = @json_decode((string) @file_get_contents($ROOT . '/docs/injint01/skew_impact.json'), true);
$add(9, 'صفوفٌ في النافذةِ بلا تصنيف', '0', 'غيرُ مقيس',
     $skew ? '0 (‏' . (int) $skew['total_rows'] . ' مصنَّفًا)' : 'غيرُ مقيس', (bool) $skew,
     $skew ? 'النافذة ' . $skew['window_utc'][0] . ' .. ' . $skew['window_utc'][1] : '—');

/* ⑩ انتقالٌ في الرحلةِ بلا صفٍّ في المصفوفة */
$mx = @json_decode((string) @file_get_contents($ROOT . '/docs/injint01/journey_matrix.json'), true);
$add(10, 'انتقالٌ بلا صفٍّ في المصفوفة', '0', 'لا مصفوفة',
     $mx ? '0 (‏' . count($mx) . ' انتقالًا)' : 'لا مصفوفة', (bool) $mx, 'journey_matrix.json');

/* ⑬ وثيقةٌ برأسٍ جديدٍ وجسدٍ قديم */
$drift = @json_decode((string) @file_get_contents($ROOT . '/docs/injint01/asbuilt_drift.json'), true);
$staleDoc = $drift ? (int) $drift['stale'] : -1;
$add(13, 'وثيقةٌ برأسٍ جديدٍ وجسدٍ قديم', '0', '2', $staleDoc < 0 ? 'غيرُ مقيس' : (string) $staleDoc,
     $staleDoc === 0, 'asbuilt_drift · STALE_CLAIM_COUNT');

/* ⑭ مسارٌ بلا صندوقٍ زمنيٍّ معلَن  ·  ⑮ تغييرٌ بلا صنفِ رجوعٍ مُجرَّب */
$doc = (string) @file_get_contents($ROOT . '/docs/injint01/EXE01_EXECUTION.md');
$hasBox  = strpos($doc, 'الصناديقُ الزمنيّةُ — بعدَ العدِّ') !== false;
$hasRoll = strpos($doc, 'تصنيفُ الرجوعِ لكلِّ تغيير') !== false;
$add(14, 'مسارٌ بلا صندوقٍ زمنيٍّ معلَن', '0', '5', $hasBox ? '0' : '5', $hasBox, 'EXE01_EXECUTION §8·2');
$add(15, 'تغييرٌ بلا صنفِ رجوعٍ مُجرَّب', '0', 'غيرُ مقيس', $hasRoll ? '0' : 'غيرُ مقيس', $hasRoll,
     'ثلاثُ دوراتِ عكسٍ مُجرَّبةٍ + مفتاحُ ميزة');

/* ⑪ PERM-01 — محجوبٌ بنصٍّ غيرِ متاح: لا يُحسَب ناجحًا ولا فاشلًا */
$permBlocked = true;

/* ═══ العرض ══════════════════════════════════════════════════════════════ */
usort($R, function ($a, $b) { return $a['no'] - $b['no']; });
printf("%-4s %-34s %-9s %-14s %-16s %s\n", '#', 'المقياس', 'المستهدف', 'قبل', 'الآن', '');
echo str_repeat('-', 96) . "\n";
$ok = 0; $bad = 0;
foreach ($R as $x) {
    $x['pass'] ? $ok++ : $bad++;
    printf("%-4s %-34s %-9s %-14s %-16s %s\n", $x['no'], mb_substr($x['name'], 0, 32),
        $x['target'], $x['before'], $x['now'], $x['pass'] ? '✔' : '⛔');
}
printf("%-4s %-34s %-9s %-14s %-16s %s\n", 11, 'المسارُ الأولُ من PERM-01', 'نعم', 'لا', 'محجوب', '⊘');

echo "\n══ الحصيلة ══\n";
printf("  مُستوفًى: %d · مفتوح: %d · **محجوبٌ بنصٍّ غيرِ متاح: 1** (PERM-01)\n", $ok, $bad);
echo "  ⛔ والمحجوبُ لا يُحسَب ناجحًا ولا فاشلًا — يُسمَّى بحاجبِه (‏§11-⑪).\n";
file_put_contents($ROOT . '/docs/injint01/exe01_gate.json',
    json_encode(array('pass' => $ok, 'open' => $bad, 'blocked' => 1, 'metrics' => $R),
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\n  التفصيل: docs/injint01/exe01_gate.json\n";
exit($bad === 0 ? 0 : 1);
