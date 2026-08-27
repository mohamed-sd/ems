<?php
/**
 * tools/repair01_reconcile_full.php — المصالحةُ الكاملةُ على لقطةٍ مجمَّدة
 * ═══════════════════════════════════════════════════════════════════════════
 * **البندُ ⑩ من أمرِ المالك 2026-08-27**، وتمييزُه الحاسم:
 * «**`Reconciliation Run` ليست `Reconciliation Closure`**».
 *
 * ◆ **فالجولةُ تُنتج أرقامًا، والإغلاقُ يحتاج شروطًا** — وثلاثةٌ منها منصوصةٌ
 *   بأسمائها: `UNKNOWN_WRITE_OWNER = 0` · `DUPLICATE_CANONICAL_SOURCE = 0` ·
 *   `CRITICAL_DOMAIN_BOUNDARY_VIOLATION = 0`. **وجولةٌ خضراءُ تُعلَن إغلاقًا
 *   دعوى لا حكم.**
 *
 * ⛔ **ولا تُصالح إلّا على لقطةٍ مجمَّدةٍ مفتوحةِ النافذة**، **وتُتحقَّق البصمةُ
 *   الآنَ**: إن تغيّر الالتزامُ أو المخطَّطُ أو السجلُّ عمّا خُتم **فالتقريرُ لا
 *   يمثّل اللقطةَ التي يزعم** — ويُرسَّب ولا يُنشَر (‏البند ⑬).
 *
 * ⛔ **وهذه الأداةُ لا تكتب في النظام** — نافذةُ القياسِ تمنع التعديل، **وأداةُ
 *   قياسٍ تُصلح ما تقيسه تُفسد قياسَها**.
 *
 * التشغيل: php tools/repair01_reconcile_full.php [--md]
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
$MD = in_array('--md', $argv, true);
$n = function ($sql) use ($conn) { $r = @$conn->query($sql); return $r ? (int) $r->fetch_row()[0] : -1; };
$git = function ($a) use ($ROOT) {
    $o = array(); exec('git -C ' . escapeshellarg($ROOT) . ' ' . $a . ' 2>&1', $o);
    return trim(implode(' ', $o));
};

/* ══ ① اللقطةُ المجمَّدةُ وبصمتُها ═════════════════════════════════════════ */
$r = $conn->query("SELECT * FROM repair01_freeze_snapshot WHERE released_at IS NULL
                    ORDER BY frozen_at DESC LIMIT 1");
if (!$r || !$r->num_rows) {
    exit("⛔ **لا لقطةَ مجمَّدةً مفتوحة** — والمصالحةُ الكاملةُ لا تجري على حالٍ متحرّك.\n"
       . "   شغّل: php tools/repair01_freeze.php --purpose=\"…\"\n");
}
$S = $r->fetch_assoc();

$tbl = $n("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
$col = $n("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()");
$reg = $n("SELECT COUNT(*) FROM repair01_screen_registry");
$now = array(
    'commit' => $git('rev-parse HEAD'),
    'schema' => $tbl . 'T/' . $col . 'C',
    'reg'    => $reg,
    'dirty'  => ($git('status --porcelain') !== ''),
);
$drift = array();
if ($now['commit'] !== $S['commit_hash']) { $drift[] = 'الالتزام ' . substr($S['commit_hash'], 0, 8) . ' ← ' . substr($now['commit'], 0, 8); }
if ($now['schema'] !== $S['schema_version']) { $drift[] = 'المخطَّط ' . $S['schema_version'] . ' ← ' . $now['schema']; }
if ($now['reg'] !== (int) $S['registry_rows']) { $drift[] = 'السجل ' . $S['registry_rows'] . ' ← ' . $now['reg']; }
if ($now['dirty']) { $drift[] = 'الشجرةُ متّسخة'; }

echo "\n═══ المصالحةُ الكاملة — البندُ ⑩ ═══\n";
printf("  اللقطة: %s · جُمِّدت %s\n", $S['snapshot_id'], $S['frozen_at']);
printf("  الغرض: %s\n\n", $S['purpose']);
if ($drift) {
    echo "⛔ **البصمةُ انحرفت عن اللقطةِ المختومة** — والتقريرُ لا يمثّل ما يزعم:\n";
    foreach ($drift as $d) { echo "   · $d\n"; }
    echo "   ⇒ إمّا يُعاد الحالُ إلى اللقطة، أو تُفَكُّ وتُختَم لقطةٌ جديدة.\n";
    exit(2);
}
echo "✔ **البصمةُ مطابقةٌ للقطةِ المختومة** — التقريرُ يمثّلها بعينِها.\n\n";

/* ══ ② المقاييس ═══════════════════════════════════════════════════════════ */
$LIVE = "on_disk = 1 AND ownership_verdict NOT IN ('RETIRE')";
$M = array();
$m = function ($code, $label, $count, $kind) use (&$M) { $M[] = array($code, $label, $count, $kind); };

/* ── الشروطُ الثلاثةُ المنصوصةُ للإغلاق ── */
$m('UNKNOWN_WRITE_OWNER', 'سطحٌ حيٌّ بلا مالكٍ مسجَّل',
   $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(owner_code,'') = '' AND $LIVE"), 'BLOCKING');
$m('DUPLICATE_CANONICAL_SOURCE', 'حقيقةٌ واحدةٌ بمصدرَين',
   $n("SELECT COUNT(*) FROM (SELECT LOWER(COALESCE(NULLIF(route,''), screen_file)) f
        FROM repair01_screen_registry WHERE surface_kind = 'SOURCE'
        GROUP BY f HAVING COUNT(DISTINCT owner_code) > 1) z"), 'BLOCKING');
/* **خرقُ حدودِ النطاقِ يُقاس كتابةً لا قراءة** — ونصُّ المالك: لمسُ نطاقٍ آخرَ
   قراءةً `Cross Domain Reference` لا خرق. فالخرقُ سطحٌ **مصدرٌ** لنطاقٍ ويكتب
   في جداولِ نطاقٍ آخرَ **دون أن يُعلَن ذلك في قاعدةِ حكمِه**. */
$idx = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    if (substr($p->getFilename(), -4) !== '.php') { continue; }
    $s = strtr($p->getPathname(), DIRECTORY_SEPARATOR, '/');
    if (strpos($s, '/.git/') !== false || strpos($s, '/vendor/') !== false) { continue; }
    if (!isset($idx[$p->getFilename()])) { $idx[$p->getFilename()] = $s; }
}
$PREF = array('tkt_' => 'DEP-10', 'mnt_' => 'DEP-14', 'trp_' => 'DEP-15', 'proc_' => 'DEP-16',
              'wh_' => 'DEP-17', 'acc_' => 'DEP-05', 'tre_' => 'DEP-06', 'fin_' => 'DEP-03',
              'hr_' => 'DEP-07', 'gov_' => 'DEP-08', 'risk_' => 'DEP-09');
$breach = 0; $breachList = array();
$q = $conn->query("SELECT screen_file, owner_code, verdict_rule FROM repair01_screen_registry
                    WHERE surface_kind = 'SOURCE' AND $LIVE");
while ($q && ($x = $q->fetch_assoc())) {
    $b = basename($x['screen_file']);
    if (!isset($idx[$b])) { continue; }
    $c = (string) @file_get_contents($idx[$b]);
    foreach ($PREF as $pre => $dep) {
        if ($dep === $x['owner_code']) { continue; }
        if (!preg_match('~\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?' . preg_quote($pre, '~') . '\w*~i', $c)) { continue; }
        /* مُعلَنٌ في قاعدةِ الحكمِ ⇒ مقصودٌ لا خرقٌ صامت */
        if (strpos((string) $x['verdict_rule'], $dep) !== false) { continue; }
        $breach++; $breachList[] = $b . ' → ' . $dep;
        break;
    }
}
$m('CRITICAL_DOMAIN_BOUNDARY_VIOLATION', 'مصدرٌ يكتب في نطاقٍ آخرَ بلا إعلان', $breach, 'BLOCKING');

/* ── ما يبقى دَينًا مُدارًا — من سجلِّ الأصناف ── */
$dq = $conn->query("SELECT class_code, class_name_ar, measured_count, blocking_level, assigned_wave
                      FROM repair01_debt_register
                     ORDER BY FIELD(blocking_level,'BLOCKING','MAJOR','MINOR','INFORMATIONAL'), class_code");
$debtRows = array(); $debtTot = 0; $debtBlk = 0;
while ($dq && ($x = $dq->fetch_assoc())) {
    $debtRows[] = $x;
    if ((int) $x['measured_count'] > 0) {
        $debtTot += (int) $x['measured_count'];
        if ($x['blocking_level'] === 'BLOCKING') { $debtBlk += (int) $x['measured_count']; }
    }
}

/* ══ ③ الحكم ══════════════════════════════════════════════════════════════ */
echo "── الشروطُ الثلاثةُ المنصوصةُ للإغلاق ──\n";
$blockOpen = 0;
foreach ($M as $x) {
    printf("  %s %-38s %5d\n", ($x[2] === 0 ? '✔' : '✘'), $x[0], $x[2]);
    if ($x[2] !== 0) { $blockOpen++; }
}
if ($breachList) {
    echo "     ⇐ " . implode('، ', array_slice($breachList, 0, 5)) . "\n";
}
printf("\n── الدَّينُ المُدار: %d صفًّا في %d صنفًا · وما يحجب منها %d ──\n",
    $debtTot, count($debtRows), $debtBlk);
foreach ($debtRows as $x) {
    if ((int) $x['measured_count'] === 0) { continue; }
    printf("  ◆ %-7s %-38s %5d  %s → %s\n", $x['class_code'], $x['class_name_ar'],
        $x['measured_count'], $x['blocking_level'], $x['assigned_wave']);
}

echo "\n────────────────────────────────────────────────────────────────────\n";
$closure = ($blockOpen === 0 && $debtBlk === 0);
echo "**`Reconciliation Run` ✔ تمّت** — على اللقطةِ " . $S['snapshot_id'] . "\n";
if ($closure) {
    echo "**`Reconciliation Closure` ✔ شروطُها الثلاثةُ مستوفاةٌ وصفرُ دَينٍ حاجب.**\n";
    echo "◆ **والإغلاقُ قرارُ مالكٍ لا نتيجةُ أداة** — هذه تُثبت استيفاءَ الشروطِ لا غير.\n";
} else {
    printf("⛔ **`Reconciliation Closure` لم تُستوفَ** — شروطٌ مفتوحةٌ %d · دَينٌ حاجبٌ %d\n",
        $blockOpen, $debtBlk);
}

if ($MD) {
    $o  = "# المصالحةُ الكاملة — البندُ ⑩\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيٍّ على لقطةٍ مجمَّدة**: `php tools/repair01_reconcile_full.php --md`\n\n";
    $o .= "## بصمةُ اللقطة (‏البند ⑬)\n\n| الحقل | القيمة |\n|---|---|\n";
    $o .= "| `Snapshot ID` | `{$S['snapshot_id']}` |\n";
    $o .= "| `Commit Hash` | `{$S['commit_hash']}` |\n";
    $o .= "| `Schema Version` | `{$S['schema_version']}` |\n";
    $o .= "| `Registry Version` | {$S['registry_rows']} صفًّا |\n";
    $o .= "| `Config Baseline` | `{$S['config_baseline']}` |\n";
    $o .= "| `Frozen At` | {$S['frozen_at']} |\n";
    $o .= "| `Measured At` | " . date('Y-m-d H:i:s') . " |\n";
    $o .= "| **مطابقةُ البصمة** | ✔ مطابقةٌ للمختومِ حرفًا |\n\n";
    $o .= "## الشروطُ الثلاثةُ المنصوصةُ للإغلاق\n\n| الشرط | المقيس | الحكم |\n|---|---:|---|\n";
    foreach ($M as $x) { $o .= sprintf("| `%s` | %d | %s |\n", $x[0], $x[2], $x[2] === 0 ? '✔' : '✘'); }
    $o .= sprintf("\n## الدَّينُ المُدار — %d صفًّا · وما يحجب منها %d\n\n", $debtTot, $debtBlk);
    $o .= "| الصنف | الاسم | العدد | الحجب | الموجة |\n|---|---|---:|---|---|\n";
    foreach ($debtRows as $x) {
        if ((int) $x['measured_count'] === 0) { continue; }
        $o .= sprintf("| `%s` | %s | %d | %s | %s |\n", $x['class_code'], $x['class_name_ar'],
            $x['measured_count'], $x['blocking_level'], $x['assigned_wave']);
    }
    $o .= "\n## الحكم\n\n";
    $o .= "- **`Reconciliation Run`** ✔ تمّت على اللقطةِ المجمَّدة\n";
    $o .= $closure
        ? "- **`Reconciliation Closure`** ✔ **شروطُها الثلاثةُ مستوفاةٌ وصفرُ دَينٍ حاجب**\n"
          . "\n> ◆ **والإغلاقُ قرارُ مالكٍ لا نتيجةُ أداة** — هذه تُثبت استيفاءَ الشروطِ لا غير.\n"
        : sprintf("- **`Reconciliation Closure`** ⛔ لم تُستوفَ — شروطٌ مفتوحةٌ %d · دَينٌ حاجبٌ %d\n",
                  $blockOpen, $debtBlk);
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/RECONCILIATION_FULL.md', $o);
    echo "✔ كُتب docs/REPAIR01_20260823/RECONCILIATION_FULL.md\n";
}
exit($closure ? 0 : 1);
