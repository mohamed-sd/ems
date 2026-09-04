<?php
/**
 * tools/injint01/retry_disposition.php — حكمُ التصرُّفِ في كلِّ تسليمٍ عالق (‏§21)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأمرُ يمنع إعادةَ الإرسالِ دفعةً واحدة**: «لا يعاد إرسالُ الـ11 دفعةً
 *   واحدة» — بل يُسأل عن **كلِّ تسليمٍ فردًا**: أَقابلٌ مصدرُه للحلّ؟ أَمُحقَّقٌ
 *   أثرُه؟ أَمقفلةٌ فترتُه؟ ثمَّ يُكتب حكمُه من سبعةٍ لا ثامنَ لها.
 *
 * ⛔ **ورقمُ الـDLQ ليس عائلةً واحدة**: سبعةٌ وثلاثون صفًّا فيها **قرارٌ مكتوبٌ
 *   سابقٌ** (`CONSUMER_RETIRED` — «أُغلق ولا يُعاد») وعطبٌ حقيقيٌّ
 *   (`HANDLER_ERROR`). فمن عدَّها رقمًا واحدًا ضخَّم الأهونَ وأخفى الأخطر.
 *
 * ⛔ **ونوعُ الكيانِ ليس اسمَ جدول**: `fin_asset` كيانٌ و`fin_assets` جدولٌ —
 *   فمن سأل بالاسمِ الخامِّ أخرج «لا جدولَ» وحكم بغيرِ علم. تُجرَّب هنا صيغُ
 *   الجمعِ الثلاثُ، **ولا يُحكم بعدمِ الحلِّ حتى تُستنفَد**.
 *
 * ⛔ **ولا تُكتب مفردةٌ خارج ENUM**: تُشتقُّ المفرداتُ من المخطَّطِ نفسِه،
 *   فالقيمةُ الغريبةُ تصير `''` صامتًا ويُقرأ الصفُّ حكمًا وهو فارغ.
 *
 * ◆ **والحكمُ لا يُنفِّذ شيئًا**: هذه الأداةُ تكتب في سجلِّها وحدَه. لا تُعيد
 *   إرسالًا ولا تحذف تسليمًا ولا تمسُّ حدثًا.
 *
 * التشغيل: php tools/injint01/retry_disposition.php [--write]
 *          بلا ‎--write‎ تعرض ولا تكتب.
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

/* ═══ ⓪ حارسُ المفردات — لا يُكتب حكمٌ لا تعرفه ENUM ═══════════════════════ */
$col = $rows("SHOW COLUMNS FROM `injint01_retry_disposition` LIKE 'disposition'");
if (!$col) { exit("⛔ سجلُّ التصرُّفِ غيرُ موجود — شغِّل الهجرة 2028_04_29 أوّلًا\n"); }
preg_match_all("/'([^']+)'/", $col[0]['Type'], $m);
$VOCAB = $m[1];
echo '◆ مفرداتُ الحكمِ من المخطَّط (' . count($VOCAB) . '): ' . implode(' · ', $VOCAB) . "\n\n";

/* ═══ ① بصمةُ اللقطة ═══════════════════════════════════════════════════ */
$head = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD 2>&1'));
if ($head === '' || strlen($head) > 12) { $head = 'nogit'; }
$SNAP = $head . '-' . gmdate('YmdHis');
echo "◆ بصمةُ اللقطة: $SNAP\n\n";

/* ═══ ② حلُّ نوعِ الكيانِ إلى جدولٍ حقيقيّ — ثلاثُ صيغٍ قبلَ اليأس ═════════ */
$tables = array();
foreach ($rows("SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()") as $r) {
    $tables[strtolower($r['t'])] = $r['t'];
}
$resolveTable = function ($entity) use ($tables) {
    $e = strtolower(trim((string) $entity));
    if ($e === '') { return ''; }
    foreach (array($e, $e . 's', $e . 'es', rtrim($e, 'y') . 'ies') as $cand) {
        if (isset($tables[$cand])) { return $tables[$cand]; }
    }
    return '';
};

/* ═══ ③ التسليماتُ العالقةُ — كلُّ حالةٍ غيرِ processed ═══════════════════ */
$stuck = $rows("SELECT d.id, d.event_id, d.state, d.attempt_no, d.attempts,
                       COALESCE(d.consumer_key, d.consumer, '') ck,
                       COALESCE(d.fail_code, '') fc,
                       LEFT(COALESCE(d.fail_text, d.last_error, ''), 300) ft,
                       COALESCE(b.event_key, '') ek, COALESCE(b.entity_type, '') et,
                       b.entity_id, b.amount, b.occurred_at
                  FROM ems_event_deliveries d
                  LEFT JOIN ems_business_events b ON b.id = d.event_id
                 WHERE d.state <> 'processed'
                 ORDER BY d.id");

printf("◆ تسليماتٌ عالقة: %d\n\n", count($stuck));

$tally = array_fill_keys($VOCAB, 0);
$batch = array();

foreach ($stuck as $d) {
    $tbl   = $resolveTable($d['et']);
    $alive = 0;
    if ($tbl !== '' && $d['entity_id'] !== null) {
        $alive = (int) $one("SELECT COUNT(*) FROM `$tbl` WHERE id = " . (int) $d['entity_id']);
    }
    $links = $d['event_id'] ? (int) $one('SELECT COUNT(*) FROM fin_event_links   WHERE event_id = ' . (int) $d['event_id']) : 0;
    $effs  = $d['event_id'] ? (int) $one('SELECT COUNT(*) FROM fin_event_effects WHERE event_id = ' . (int) $d['event_id']) : 0;

    /* ── الحكمُ بالترتيبِ: القرارُ المكتوبُ أوّلًا، ثمَّ الأثرُ، ثمَّ المصدر ── */
    if ($d['fc'] === 'CONSUMER_RETIRED') {
        $verdict  = 'NON_RETRYABLE';
        $evidence = 'قرارٌ مكتوبٌ سابق: المستهلكُ مُتقاعِدٌ ونوعُ الحدثِ مغطًّى بمستهلكٍ عاملٍ آخر — أُغلق ولا يُعاد.';
    } elseif ($d['fc'] === 'NO_SUB') {
        $verdict  = 'NON_RETRYABLE';
        $evidence = 'لا اشتراكَ نشِطًا لهذا المستهلكِ على هذا المفتاح — الإعادةُ تفشل بالخطأِ نفسِه حتمًا.';
    } elseif ($effs > 0 || $links > 0) {
        $verdict  = 'EFFECT_ALREADY_REALIZED';
        $evidence = "الأثرُ محقَّقٌ سلفًا: روابط=$links · آثار=$effs — والإعادةُ تُنتِج ازدواجًا.";
    } elseif ($tbl === '') {
        $verdict  = 'SOURCE_UNRESOLVABLE';
        $evidence = "نوعُ الكيانِ «{$d['et']}» لا يُحَلُّ إلى جدولٍ بأيٍّ من صيغِ الجمعِ الثلاث.";
    } elseif ($alive === 0) {
        $mx = $one("SELECT CONCAT(COALESCE(MIN(id),'—'),'..',COALESCE(MAX(id),'—'),' /',COUNT(*)) FROM `$tbl`");
        $verdict  = 'SOURCE_UNRESOLVABLE';
        $evidence = "كيانُ المصدرِ {$d['et']}#{$d['entity_id']} غيرُ موجودٍ في `$tbl` (المدى $mx) — الأبُ محذوفٌ والحدثُ يتيم.";
    } else {
        $verdict  = 'SAFE_RETRY';
        $evidence = "المصدرُ حيٌّ في `$tbl` ولا أثرَ محقَّقًا (روابط=0 · آثار=0) — الإعادةُ آمنةٌ بمفتاحِ اللاتكرار.";
    }

    if (!in_array($verdict, $VOCAB, true)) { exit("⛔ حكمٌ خارجَ المفردات: $verdict\n"); }
    $tally[$verdict]++;

    $batch[] = array(
        'delivery_id' => (int) $d['id'], 'event_id' => $d['event_id'] !== null ? (int) $d['event_id'] : null,
        'event_key' => $d['ek'], 'consumer_key' => $d['ck'], 'delivery_state' => $d['state'],
        'fail_code' => $d['fc'], 'entity_type' => $d['et'],
        'entity_id' => $d['entity_id'] !== null ? (int) $d['entity_id'] : null,
        'resolved_table' => $tbl, 'source_resolvable' => $alive > 0 ? 1 : 0,
        'existing_links' => $links, 'existing_effects' => $effs, 'amount' => $d['amount'],
        'disposition' => $verdict, 'evidence' => $evidence,
    );
}

/* ═══ ④ العرضُ مجموعًا بالعائلة ═════════════════════════════════════════ */
$byFamily = array();
foreach ($batch as $b) { $byFamily[$b['consumer_key'] . ' · ' . $b['fail_code']][$b['disposition']][] = $b; }
foreach ($byFamily as $fam => $groups) {
    echo "◆ $fam\n";
    foreach ($groups as $v => $items) {
        printf("   %-26s %3d صفًّا\n", $v, count($items));
        echo '      ' . $items[0]['evidence'] . "\n";
        $keys = array_unique(array_map(function ($x) { return $x['event_key']; }, $items));
        echo '      مفاتيح: ' . implode(' · ', $keys) . "\n";
    }
    echo "\n";
}

echo "══ الحصيلة ══\n";
foreach ($tally as $v => $n) { if ($n) { printf("  %-26s %3d\n", $v, $n); } }
printf("\n  SAFE_RETRY = %d  ⇐ مُدخَلُ الـCanary وحدَه (‏§22)\n", $tally['SAFE_RETRY']);
if ($tally['SAFE_RETRY'] === 0) {
    echo "  ⇐ فمجموعةُ الإعادةِ الآمنةِ **فارغة**: البندانِ 9 و10 من الدفعةِ الأولى بلا مُدخَل.\n";
}

/* ═══ ⑤ الكتابةُ — بـ--write وحدَها ═════════════════════════════════════ */
if (!$WRITE) { echo "\n(‏عرضٌ فقط — أضف ‎--write‎ للكتابةِ في السجلّ)\n"; exit(0); }

$st = $c->prepare(
    'INSERT INTO `injint01_retry_disposition`
       (delivery_id, event_id, event_key, consumer_key, delivery_state, fail_code,
        entity_type, entity_id, resolved_table, source_resolvable, existing_links,
        existing_effects, amount, disposition, evidence, snapshot_id, measured_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
if (!$st) { exit('⛔ prepare: ' . $c->error . "\n"); }
$n = 0;
foreach ($batch as $b) {
    /* ستةَ عشرَ متغيّرًا وستةَ عشرَ حرفًا — والعدُّ يُطابَق ولا يُقدَّر */
    $st->bind_param('iisssssisiiidsss',
        $b['delivery_id'], $b['event_id'], $b['event_key'], $b['consumer_key'], $b['delivery_state'],
        $b['fail_code'], $b['entity_type'], $b['entity_id'], $b['resolved_table'], $b['source_resolvable'],
        $b['existing_links'], $b['existing_effects'], $b['amount'], $b['disposition'], $b['evidence'], $SNAP);
    if ($st->execute()) { $n++; }
}
$st->close();
printf("\n✔ كُتب %d صفًّا في injint01_retry_disposition بلقطة %s\n", $n, $SNAP);
echo "⛔ ولم يُعَد إرسالُ تسليمٍ واحدٍ ولم يُمَسَّ حدثٌ — حكمٌ لا تنفيذ.\n";
