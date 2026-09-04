<?php
/**
 * tools/injint01/idempotency_audit.php — تغطيةُ اللاتكرارِ لكلِّ مستهلك (‏§24)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأمرُ يمنع بناءَ نظامِ لاتكرارٍ جديد**: «النظامُ يحتوي بالفعل
 *   `idempotency_key` وقيودًا مثل `uq_ffe_idempotency` — فلا يُنشأ نظامٌ جديدٌ
 *   من الصفر». وهذه الأداةُ **تقيس القائمَ** ولا تبني شيئًا.
 *
 * ⛔ **والمقامُ ليس كلَّ مستهلك**: المعيارُ `SIDE_EFFECTING_CONSUMER_WITHOUT_
 *   IDEMPOTENCY = 0` — ومَن لا أثرَ له **خارجُ المقام**. و`EffectLinkConsumer`
 *   مثالُه: **مُتحقِّقٌ محضٌ لا يكتب سطرًا** — يفحص ثلاثةَ شواهدَ ويرمي
 *   `EFFECT_MISSING` إن غابت. فهو لاتكراريٌّ بطبيعتِه لا بحارس.
 *   ⇐ فمن عدَّه «مستهلكًا بلا لاتكرار» أنتج عطبًا وهميًّا.
 *
 * ⛔ **وقيدُ الفرادةِ لا يمسك NULL**: `uq_ffe_idempotency` على عمودٍ يقبل NULL
 *   يمرِّر صفَّين فارغَي المفتاحِ بلا اعتراض. فتُقاس **تغطيةُ المفتاحِ** لا
 *   وجودُ القيدِ وحدَه.
 *
 * ◆ **وستةُ أسئلةِ §24 لا سؤالٌ واحد**: أينشئ المنتِجُ مفتاحًا؟ أتعيد المحاولةُ
 *   المفتاحَ نفسَه؟ أيفحص المستهلكُ قبلَ الأثر؟ أفي مخزنِ الأثرِ فرادة؟ ما مآلُ
 *   التسليمِ المكرَّر؟ ما التعويض؟
 *
 * التشغيل: php tools/injint01/idempotency_audit.php [--write]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$WRITE = in_array('--write', array_slice($argv, 1), true);

$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4');
$one  = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };
$rows = function ($q) use ($c) { $r = $c->query($q); $o = array(); if (!$r) { echo '  SQL: ' . $c->error . "\n"; return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };

$col = $rows("SHOW COLUMNS FROM `injint01_idempotency_audit` LIKE 'verdict'");
if (!$col) { exit("⛔ سجلُّ التدقيقِ غيرُ موجود — شغِّل الهجرة 2028_04_29 أوّلًا\n"); }
preg_match_all("/'([^']+)'/", $col[0]['Type'], $m);
$VOCAB = $m[1];

$head = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD 2>&1'));
if ($head === '' || strlen($head) > 12) { $head = 'nogit'; }
$SNAP = $head . '-' . gmdate('YmdHis');
echo "◆ بصمةُ اللقطة: $SNAP\n";
echo '◆ مفرداتُ الحكم: ' . implode(' · ', $VOCAB) . "\n\n";

/* ═══ ① فرادةُ مخازنِ الأثرِ — تُقرأ من المخطَّطِ لا تُفترَض ═══════════════ */
$uniq = array();
foreach ($rows("SELECT TABLE_NAME t, INDEX_NAME i, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
                  FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'
                 GROUP BY t, i") as $r) { $uniq[$r['t']][$r['i']] = $r['cols']; }

/* ═══ ② المستهلكون النشِطون — من السجلِّ الحاكمِ `event_consumers` ═════════ */
$cons = $rows("SELECT COALESCE(consumer_key,'') ck, COALESCE(consumer_class,'') cls,
                      COUNT(DISTINCT event_name) k
                 FROM event_consumers WHERE active = 1
                 GROUP BY ck, cls ORDER BY k DESC");

$batch = array(); $tally = array_fill_keys($VOCAB, 0);

foreach ($cons as $r) {
    $cls = $r['cls'];
    $rel = str_replace('\\', '/', $cls) . '.php';
    $path = file_exists("$ROOT/$rel") ? "$ROOT/$rel" : '';
    $src  = $path ? (string) file_get_contents($path) : '';

    /* ── إشاراتٌ ساكنةٌ من الصنفِ نفسِه ── */
    $writes  = $src ? preg_match_all('/insert\(|upsert\(|INSERT\s+INTO|->update\(|UPDATE\s+`/i', $src) : 0;
    $idemHit = $src ? preg_match_all('/idempot/i', $src) : 0;
    $preChk  = $src ? preg_match_all('/INSERT\s+IGNORE|ON\s+DUPLICATE\s+KEY|NOT\s+EXISTS|->select\(/i', $src) : 0;
    $compens = $src ? preg_match_all('/revers|compensat|void|cancel/i', $src) : 0;

    /* ── تغطيةُ المفتاحِ على مفاتيحِ هذا المستهلكِ فعلًا ── */
    $keys = $rows("SELECT DISTINCT event_name k FROM event_consumers
                    WHERE active = 1 AND consumer_key = '" . $c->real_escape_string($r['ck']) . "'");
    $kl = array();
    foreach ($keys as $k) { $kl[] = "'" . $c->real_escape_string($k['k']) . "'"; }
    $prodTot = $prodKey = 0;
    if ($kl) {
        $in = implode(',', $kl);
        $prodTot = (int) $one("SELECT COUNT(*) FROM ems_business_events WHERE event_key IN ($in)");
        $prodKey = (int) $one("SELECT COUNT(*) FROM ems_business_events
                                WHERE event_key IN ($in) AND idempotency_key IS NOT NULL AND idempotency_key <> ''");
    }
    $prodOk = ($prodTot > 0 && $prodKey === $prodTot) ? 1 : 0;

    /* ── إعادةُ المحاولةِ تُبقي المفتاح: الصفُّ نفسُه يُعاد وقيدُ uq_idem قائم ── */
    $retryOk = isset($uniq['ems_event_deliveries']['uq_idem']) ? 1 : 0;

    /* ── فرادةُ مخزنِ الأثر: تُنسب لمن يكتب فقط ── */
    $storeUq = ''; $storeOk = 0;
    if ($writes > 0) {
        foreach (array('fin_financial_events', 'fin_event_effects', 'fin_event_links') as $t) {
            if (!empty($uniq[$t])) { $storeUq = $t . '.' . implode('+', array_keys($uniq[$t])); $storeOk = 1; break; }
        }
    }

    /* ── الحكم ── */
    if ($path === '') {
        $v = 'UNCOVERED'; $side = 1;
        $ev = "صنفُ المستهلكِ غيرُ موجودٍ على القرص ($rel) — لا يُقاس ما لا يُقرأ.";
        $dup = 'غيرُ معلوم'; $comp = 'غيرُ معلوم';
    } elseif ($writes === 0) {
        $v = 'NOT_APPLICABLE'; $side = 0;
        $ev = 'مُتحقِّقٌ محضٌ لا يكتب سطرًا — لاتكراريٌّ بطبيعتِه، وخارجُ مقامِ المعيار.';
        $dup = 'إعادةُ الفحصِ تُنتِج الحكمَ نفسَه'; $comp = 'لا أثرَ فلا تعويض';
    } else {
        $side = 1;
        $score = $prodOk + $retryOk + ($preChk > 0 ? 1 : 0) + $storeOk;
        $v = $score >= 4 ? 'COVERED' : ($score >= 2 ? 'PARTIAL' : 'UNCOVERED');
        /* ⛔ **و`0/0` ليست تغطيةً صفرًا بل مقامًا معدومًا**: مفاتيحُ هذا المستهلكِ
           لم تُنتَج قطُّ، فلا شاهدَ حيًّا يُحكَم به — وهو `BUILT_NOT_EXERCISED`
           بلسانِ الأمر (‏§25)، لا عطبَ لاتكرار. والخلطُ بينهما يُنتِج حمرةً كاذبة. */
        $prodTxt = $prodTot === 0
            ? 'مقامٌ معدوم — لم يُنتَج لهذه المفاتيحِ حدثٌ قطُّ (BUILT_NOT_EXERCISED)'
            : sprintf('تغطيةُ مفتاحِ المنتِج=%d/%d', $prodKey, $prodTot);
        $ev = sprintf('كتابات=%d · ذكرُ لاتكرار=%d · فحصٌ قبلَ الأثر=%d · %s · فرادةُ المخزنِ الماليِّ العامّ=%s',
            $writes, $idemHit, $preChk, $prodTxt, $storeUq ? 'قائمة' : 'غيرُ منسوبة');
        $dup = $storeOk ? 'يُردُّ بقيدِ الفرادة' : 'غيرُ مضمون';
        $comp = $compens > 0 ? 'ثمّةَ مسارُ عكسٍ في الصنف' : 'لا مسارَ عكسٍ ظاهر';
    }

    if (!in_array($v, $VOCAB, true)) { exit("⛔ حكمٌ خارجَ المفردات: $v\n"); }
    $tally[$v]++;
    $batch[] = array('unexercised' => ($writes > 0 && $prodTot === 0) ? 1 : 0,
        'ck' => $r['ck'], 'cls' => $cls, 'k' => (int) $r['k'], 'side' => $side,
        'prod' => $prodOk, 'retry' => $retryOk, 'pre' => $preChk > 0 ? 1 : 0, 'uqok' => $storeOk,
        'uqname' => $storeUq, 'dup' => $dup, 'comp' => $comp, 'v' => $v, 'ev' => $ev);
}

/* ═══ ③ العرض ══════════════════════════════════════════════════════════ */
printf("%-22s %-46s %-4s %-16s\n", 'المفتاح', 'الصنف', 'مفت', 'الحكم');
echo str_repeat('─', 100) . "\n";
foreach ($batch as $b) {
    printf("%-22s %-46s %-4s %-16s\n", $b['ck'], $b['cls'], $b['k'], $b['v']);
    echo '   ' . $b['ev'] . "\n";
}

echo "\n══ الحصيلة ══\n";
foreach ($tally as $v => $n) { if ($n) { printf("  %-16s %d\n", $v, $n); } }
$bad = 0; $unex = 0;
foreach ($batch as $b) {
    if ($b['side'] === 1 && $b['v'] === 'UNCOVERED') { $bad++; }
    if (!empty($b['unexercised'])) { $unex++; }
}
printf("\n  SIDE_EFFECTING_CONSUMER_WITHOUT_IDEMPOTENCY = %d\n", $bad);
echo $bad === 0 ? "  ✔ المعيارُ مستوفًى.\n" : "  ⛔ المعيارُ غيرُ مستوفًى.\n";
/* والرقمُ الأهمُّ ليس المعيارَ بل هذا: كم مستهلكًا موصولًا لم يمرَّ به شيءٌ قطُّ */
printf("\n  BUILT_NOT_EXERCISED_CONSUMERS = %d من %d مستهلكٍ ذي أثر\n",
    $unex, count($batch) - $tally['NOT_APPLICABLE']);
echo "  ⇐ موصولٌ ومسجَّلٌ ولم تمرَّ به معاملةٌ واحدة — وهذا مقامُ الشاهدِ لا مقامُ اللاتكرار.\n";

/* ═══ ④ الكتابة ════════════════════════════════════════════════════════ */
if (!$WRITE) { echo "\n(‏عرضٌ فقط — أضف ‎--write‎ للكتابة)\n"; exit($bad === 0 ? 0 : 1); }

$st = $c->prepare(
    'INSERT INTO `injint01_idempotency_audit`
       (consumer_key, consumer_class, event_keys, side_effecting, producer_generates_key,
        retry_reuses_key, consumer_checks_before_effect, effect_store_unique,
        unique_constraint_name, duplicate_delivery_outcome, compensation_behavior,
        verdict, evidence, snapshot_id, measured_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
if (!$st) { exit('⛔ prepare: ' . $c->error . "\n"); }
$n = 0;
foreach ($batch as $b) {
    /* أربعةَ عشرَ متغيّرًا وأربعةَ عشرَ حرفًا — والعدُّ يُطابَق ولا يُقدَّر */
    $st->bind_param('ssiiiiiissssss',
        $b['ck'], $b['cls'], $b['k'], $b['side'], $b['prod'], $b['retry'], $b['pre'], $b['uqok'],
        $b['uqname'], $b['dup'], $b['comp'], $b['v'], $b['ev'], $SNAP);
    if ($st->execute()) { $n++; } else { echo '  ⛔ ' . $st->error . "\n"; }
}
$st->close();
printf("\n✔ كُتب %d صفًّا في injint01_idempotency_audit بلقطة %s\n", $n, $SNAP);
exit($bad === 0 ? 0 : 1);
