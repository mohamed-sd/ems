<?php
/**
 * tools/injint01/event_observe.php — إعادةُ قياسِ الأحداثِ بلا مساسٍ بالحكم (EXE-01 §4)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المقامُ متقادمٌ أربعةَ عشرَ يومًا** (آخرُ قياسٍ 2026-08-21) — فيُعاد.
 *   **ولا يُكتب حرفٌ في `gov_event_rulings`**: القياسُ في سجلِّ الملاحظاتِ وحدَه.
 *
 * ⛔ **ومستهلكُ الأثرِ ليس المراقب**: `GovernanceWatchConsumer` يرصد ولا يُنتِج
 *   أثرًا، و`EffectLinkConsumer` **يتحقّقُ ولا يكتب**. فمن عدَّهما «مستهلكَي
 *   أثرٍ» أخرج تغطيةً كاذبة. ⇐ يُفرَز المستهلكون **بما يكتبون** لا بوجودِهم.
 *
 * ⛔ **والانحرافُ يُرفَع ولا يُصحَّح** (‏§4 حرفًا): تُكتب `drift='DRIFTED'`
 *   ويبقى الحكمُ كما قرّره المالك. فالقياسُ يُنبِّه ولا يَحكم.
 *
 * التشغيل: php tools/injint01/event_observe.php [--write]
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
$rows = function ($q) use ($c) { $r = $c->query($q); $o = array(); if (!$r) { echo 'SQL:' . $c->error . "\n"; return $o; } while ($x = $r->fetch_assoc()) { $o[] = $x; } return $o; };
$one  = function ($q) use ($c) { $r = $c->query($q); if (!$r) { return null; } $x = $r->fetch_row(); return $x ? $x[0] : null; };

/* ═══ ⓪ اللقطة — ولا ملاحظةَ بلا معرِّفِها ═══════════════════════════════ */
$head = trim((string) @shell_exec('git -C ' . escapeshellarg($ROOT) . ' rev-parse --short HEAD 2>&1'));
if ($head === '' || strlen($head) > 12) { $head = 'nogit'; }
$SNAP = $head . '-' . gmdate('YmdHis');
echo "◆ اللقطة: $SNAP\n";

/* ═══ ① فرزُ المستهلكين بما يكتبون — لا بوجودِهم ═════════════════════════ */
/* ⛔ **وعددُ الكتاباتِ لا يُصنِّف — هدفُها يُصنِّف**: `GovernanceWatchConsumer`
      كتابتُه الوحيدةُ `INSERT INTO fin_notifications` — **إشعارٌ لا أثرٌ نطاقيّ**.
      فمن عدَّه مُنتِجَ أثرٍ لأنَّ فيه كتابةً أخرج أربعينَ انحرافًا وهمًا.
      ⇐ فتُستثنى أهدافُ الإشعارِ والسجلِّ والتدقيقِ من حسابِ الأثر. */
$NON_EFFECT = '~(notification|_log\b|_logs\b|_audit\b|_watch\b|_trace\b|activity_log)~i';
$classOf = array();
foreach ($rows('SELECT DISTINCT consumer_class FROM event_consumers') as $r) {
    $cls = $r['consumer_class'];
    $rel = str_replace('\\', '/', $cls) . '.php';
    $src = is_file("$ROOT/$rel") ? (string) file_get_contents("$ROOT/$rel") : '';
    if ($src === '') { $classOf[$cls] = 'MISSING'; continue; }
    $eff = 0;
    if (preg_match_all('~(?:->insert\(|->update\(|->upsert\(|INSERT\s+INTO|UPDATE)\s*[`\'"]?([a-z0-9_]+)~i', $src, $mm)) {
        foreach ($mm[1] as $target) { if (!preg_match($NON_EFFECT, $target)) { $eff++; } }
    }
    $classOf[$cls] = $eff > 0 ? 'EFFECT' : 'WATCH';
}
echo "◆ أصنافُ المستهلكين: ";
$tally = array_count_values($classOf);
foreach ($tally as $k => $v) { echo "$k=$v "; }
echo "\n\n";

/* ═══ ② القياسُ لكلِّ مفتاحِ حدثٍ محكوم ═══════════════════════════════ */
$obs = array(); $drifted = 0;
foreach ($rows('SELECT event_key, ruling, decided_by FROM gov_event_rulings ORDER BY event_key') as $r) {
    $k  = $c->real_escape_string($r['event_key']);
    $prod = (int) $one("SELECT COUNT(*) FROM ems_business_events WHERE event_key='$k'");
    $cons = $rows("SELECT consumer_class, active FROM event_consumers WHERE event_name='$k'");
    $tot = count($cons); $act = 0; $eff = 0; $wat = 0; $disk = 0;
    foreach ($cons as $x) {
        if ((int) $x['active'] === 1) { $act++; }
        $cl = isset($classOf[$x['consumer_class']]) ? $classOf[$x['consumer_class']] : 'MISSING';
        if ($cl !== 'MISSING') { $disk = 1; }
        if ((int) $x['active'] !== 1) { continue; }
        if ($cl === 'EFFECT') { $eff++; } elseif ($cl === 'WATCH') { $wat++; }
    }
    /* التصنيفُ المرصود: ما يُنتَج ويُستهلَك بأثرٍ عملٌ · وما يُرصَد فقط تدقيق */
    $observed = 'UNKNOWN';
    if ($prod === 0) { $observed = 'UNKNOWN'; }
    elseif ($eff > 0) { $observed = 'BUSINESS'; }
    elseif ($wat > 0) { $observed = 'AUDIT'; }
    /* الانحرافُ: حكمٌ «عمل» بلا مستهلكِ أثرٍ نشِط — يُرفَع ولا يُصحَّح */
    $drift = 'UNDETERMINED';
    if ($observed !== 'UNKNOWN') {
        $drift = (strtolower($r['ruling']) === strtolower($observed)) ? 'ALIGNED' : 'DRIFTED';
    }
    if ($drift === 'DRIFTED') { $drifted++; }
    $obs[] = array('key' => $r['event_key'], 'ruling' => $r['ruling'], 'prod' => $prod,
        'tot' => $tot, 'act' => $act, 'eff' => $eff, 'wat' => $wat, 'disk' => $disk,
        'observed' => $observed, 'drift' => $drift,
        'ev' => "produced=$prod · نشِط=$act · أثر=$eff · مراقب=$wat");
}

printf("%-44s %-9s %-9s %-8s %s\n", 'المفتاح', 'الحكم', 'المرصود', 'أثر/مراقب', 'الانحراف');
echo str_repeat('-', 92) . "\n";
foreach ($obs as $o) {
    if ($o['drift'] !== 'DRIFTED' && $o['prod'] === 0) { continue; }
    printf("%-44s %-9s %-9s %-8s %s\n", mb_substr($o['key'], 0, 42), $o['ruling'], $o['observed'],
        $o['eff'] . '/' . $o['wat'], $o['drift'] === 'DRIFTED' ? '⚠ منحرف' : '');
}

printf("\n══ الحصيلة ══\n  ملاحظات=%d · منحرفٌ عن حكمِه=%d\n", count($obs), $drifted);
$byObs = array_count_values(array_map(function ($x) { return $x['observed']; }, $obs));
foreach ($byObs as $k => $v) { printf("  مرصودٌ %-10s %d\n", $k, $v); }

if (!$WRITE) { echo "\n(‏عرضٌ فقط — أضف ‎--write‎)\n"; exit(0); }

$st = $c->prepare('INSERT INTO `gov_event_observations`
    (event_key, ruling_version, snapshot_id, measured_at, produced_count, consumers_total,
     consumers_active, effect_consumers, watch_consumers, handler_on_disk, observed_class,
     ruling_at_measure, drift, evidence_ref)
    VALUES (?,1,?,UTC_TIMESTAMP(),?,?,?,?,?,?,?,?,?,?)');
if (!$st) { exit('⛔ prepare: ' . $c->error . "\n"); }
$n = 0;
foreach ($obs as $o) {
    /* ثلاثةَ عشرَ متغيّرًا وثلاثةَ عشرَ حرفًا — والعدُّ يُطابَق لا يُقدَّر */
    $st->bind_param('ssiiiiiissss',
        $o['key'], $SNAP, $o['prod'], $o['tot'], $o['act'], $o['eff'], $o['wat'], $o['disk'],
        $o['observed'], $o['ruling'], $o['drift'], $o['ev']);
    if ($st->execute()) { $n++; } else { echo '  ⛔ ' . $st->error . "\n"; break; }
}
$st->close();
printf("\n✔ كُتبت %d ملاحظةً بلقطة %s\n", $n, $SNAP);
echo "⛔ ولم يُمَسَّ `gov_event_rulings` — الحكمُ كما قرّره المالك.\n";
