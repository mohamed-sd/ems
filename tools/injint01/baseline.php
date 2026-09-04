<?php
/**
 * tools/injint01/baseline.php — الأساسُ الذرّيُّ قبلَ التغييرِ وبعده (EXE-01 §2·2 · §8)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأساسُ نقطةُ مقارنةٍ لا تقرير**: يُثبَّت قبلَ أوّلِ تعديلٍ ويُعاد بعدَ آخرِه،
 *   فيصير الفرقُ **مقيسًا** لا مرويًّا. ولا يُقاس تغييرٌ بلا أساسٍ قبله.
 *
 * ⛔ **ولا تُخلَط بصمةُ المخطَّطِ ببصمةِ البيانات**: جدولٌ يُنشأ يغيّر الأولى،
 *   وصفٌّ يُدرَج يغيّر الثانية. فمن جمعهما في رقمٍ واحدٍ لم يعرف أيَّهما تحرّك.
 *
 * ⛔ **والأختامُ تُقاس بأساسِها**: التطبيقُ يكتب بـ`NOW()` المحلّيّةِ (+180د هنا)
 *   لا بـUTC — فمقارنةُ `created_at` بـ`UTC_TIMESTAMP()` تَعُدُّ كلَّ صفٍّ كُتب
 *   في الساعاتِ الثلاثِ الأخيرةِ «مستقبليًّا». وهذا انحرافُ مقياسٍ لا ساعة.
 *
 * التشغيل: php tools/injint01/baseline.php --tag=PRE_CHANGE_CONTROL
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8'); mysqli_report(MYSQLI_REPORT_OFF);
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';

$TAG = 'BASELINE';
foreach (array_slice($argv, 1) as $a) { if (preg_match('/^--tag=([A-Z_0-9]+)$/', $a, $m)) { $TAG = $m[1]; } }

$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
$c = new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
if ($c->connect_errno) { exit('تعذّر الاتصال: ' . $c->connect_error . "\n"); }
$c->set_charset('utf8mb4'); $DB = ems_env('DB_NAME');
$one  = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };
$rows = function ($q) use ($c) { $r = $c->query($q); $o = array(); if (!$r) { return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };

$B = array('tag' => $TAG, 'at_utc' => gmdate('Y-m-d H:i:s'), 'at_app' => $one('SELECT NOW()'));

/* ═══ ① مصدرُ الشيفرةِ ══════════════════════════════════════════════════ */
$git = function ($cmd) use ($ROOT) { return trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' ' . $cmd . ' 2>&1')); };
$B['git'] = array(
    'head'        => $git('rev-parse HEAD'),
    'head_short'  => $git('rev-parse --short HEAD'),
    'branch'      => $git('rev-parse --abbrev-ref HEAD'),
    'upstream'    => $git('rev-parse --abbrev-ref @{u}'),
    'tracking_ref' => $git('rev-parse @{u}'),
    'dirty_files' => (int) count(array_filter(explode("\n", $git('status --porcelain')))),
);
/* ⛔ ومساواةُ الرأسِ بمرجعِ التتبُّعِ **دليلٌ محلّيٌّ على الدفع** لا شهادةٌ من
   البعيد: مَن لا يملك المفتاحَ لا يستطيع سؤالَ البعيدِ مباشرة. */
$B['git']['head_equals_tracking'] = ($B['git']['head'] !== '' && $B['git']['head'] === $B['git']['tracking_ref']);

/* ═══ ② بصمةُ المخطَّطِ — بنيةٌ لا بيانات ═══════════════════════════════ */
$sig = array();
foreach ($rows("SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty, IS_NULLABLE n, COLUMN_KEY k
                  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB'
                 ORDER BY t, ORDINAL_POSITION") as $r) {
    $sig[] = $r['t'] . '.' . $r['c'] . ':' . $r['ty'] . ':' . $r['n'] . ':' . $r['k'];
}
$B['schema'] = array(
    'tables'      => (int) $one("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='BASE TABLE'"),
    'views'       => (int) $one("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='VIEW'"),
    'columns'     => count($sig),
    'fingerprint' => sha1(implode("\n", $sig)),
);

/* ═══ ③ المقاييسُ الحاكمةُ للدفعة ═════════════════════════════════════ */
$M = array();
$M['deliveries_total']      = (int) $one('SELECT COUNT(*) FROM ems_event_deliveries');
$M['deliveries_dlq']        = (int) $one("SELECT COUNT(*) FROM ems_event_deliveries WHERE state='dlq'");
$M['deliveries_processed']  = (int) $one("SELECT COUNT(*) FROM ems_event_deliveries WHERE state='processed'");
$M['dead_letter_rows']      = (int) $one('SELECT COUNT(*) FROM ems_event_dead_letter');
/* ⛔ **ولا يُثبَّت هذا المقياسُ رقمًا**: يُقاس بعدِّ الكتّابِ الذين يمسّون حالةَ
   `ems_event_deliveries` في الشيفرةِ الحيّة. فمن كتبه ثابتًا وصف أمسًا لا يومًا. */
$M['dlq_state_sources'] = 0;
foreach (array($ROOT . '/App/Core/EventDispatcher.php', $ROOT . '/App/Services/Bus/EventDeliveryWorker.php') as $w) {
    $src = (string) @file_get_contents($w);
    if ($src === '') { continue; }
    if (preg_match('~(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?ems_event_deliveries~i', $src)) { $M['dlq_state_sources']++; }
}
$M['business_events']       = (int) $one('SELECT COUNT(*) FROM ems_business_events');
$M['event_keys_distinct']   = (int) $one('SELECT COUNT(DISTINCT event_key) FROM ems_business_events');
$M['rulings_rows']          = (int) $one('SELECT COUNT(*) FROM gov_event_rulings');
$M['rulings_measured_at']   = $one('SELECT MAX(measured_at) FROM gov_event_rulings');
$M['journal_entries']       = (int) $one('SELECT COUNT(*) FROM fin_journal_entries');
$M['journal_lines']         = (int) $one('SELECT COUNT(*) FROM fin_journal_lines');
$M['journal_balance_diff']  = $one('SELECT ROUND(SUM(debit)-SUM(credit),2) FROM fin_journal_lines');
$M['fin_event_links']       = (int) $one('SELECT COUNT(*) FROM fin_event_links');
$M['fin_event_effects']     = (int) $one('SELECT COUNT(*) FROM fin_event_effects');
/* الأختامُ بأساسِها المحلّيِّ لا بـUTC */
$M['created_at_future']     = (int) $one('SELECT COUNT(*) FROM ems_business_events WHERE created_at  > NOW()');
$M['occurred_at_future']    = (int) $one('SELECT COUNT(*) FROM ems_business_events WHERE occurred_at > NOW()');
$M['app_utc_offset_min']    = (int) round((strtotime($one('SELECT NOW()')) - strtotime($one('SELECT UTC_TIMESTAMP()'))) / 60);
$B['metrics'] = $M;

/* ═══ ④ عدُّ الصفوفِ لكلِّ جدولٍ حاكمٍ في نطاقِ الدفعة ═════════════════ */
$watch = array('ems_event_deliveries', 'ems_event_dead_letter', 'ems_business_events',
    'gov_event_rulings', 'event_consumers', 'ems_event_subscriptions', 'fin_event_links',
    'fin_event_effects', 'fin_financial_events', 'fin_journal_entries', 'fin_journal_lines');
$B['row_counts'] = array();
foreach ($watch as $t) { $B['row_counts'][$t] = (int) $one("SELECT COUNT(*) FROM `$t`"); }

/* ═══ ⑤ الكتابة ══════════════════════════════════════════════════════ */
$dir = $ROOT . '/docs/injint01/baselines';
if (!is_dir($dir)) { mkdir($dir, 0777, true); }
$B['baseline_id'] = $TAG . '-' . $B['git']['head_short'] . '-' . gmdate('YmdHis');
$json = json_encode($B, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$B['self_fingerprint'] = sha1($json);
$file = $dir . '/' . $B['baseline_id'] . '.json';
file_put_contents($file, json_encode($B, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

printf("══ %s ══\n", $B['baseline_id']);
printf("  الالتزام   : %s (%s) · فرع %s\n", $B['git']['head_short'], substr($B['git']['head'], 0, 12), $B['git']['branch']);
printf("  التتبُّع     : %s · الرأس = التتبُّع؟ %s\n", $B['git']['upstream'], $B['git']['head_equals_tracking'] ? 'نعم ✔' : '⛔ لا');
printf("  شجرةٌ نظيفة : %s\n", $B['git']['dirty_files'] === 0 ? 'نعم ✔' : $B['git']['dirty_files'] . ' ملفًّا');
printf("  المخطَّط     : %d جدولًا · %d رؤيةً · %d عمودًا · بصمة %s\n",
    $B['schema']['tables'], $B['schema']['views'], $B['schema']['columns'], substr($B['schema']['fingerprint'], 0, 12));
echo "\n  المقاييس:\n";
foreach ($M as $k => $v) { printf("    %-24s %s\n", $k, $v === null ? '—' : $v); }
printf("\n  بصمةُ الأساس: %s\n  الملف: docs/injint01/baselines/%s.json\n", substr($B['self_fingerprint'], 0, 16), $B['baseline_id']);
