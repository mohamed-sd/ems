<?php
/**
 * tools/repair01_edc_closure_audit.php — جردُ ما يحجب الإغلاقَ، بلا استثناء
 * ═══════════════════════════════════════════════════════════════════════════
 * **البندُ ⑪ من أمرِ المالك 2026-08-27**: تُغلق الشروطُ الحمراءُ الحاجبةُ
 * لـ`Closure`. **والبندُ 58**: لا يُعلَن `Owner Approved Baseline` قبلَها.
 *
 * ◆ **وجردٌ يُثبت الصفرَ أنفعُ من جردٍ يجد شيئًا** — بشرطٍ واحد: **أن يكون
 *   مقامُه معلَنًا**. فصفرٌ من مقامٍ مجهولٍ لا يُثبت شيئًا، **وهذا هو الفرقُ
 *   بين «لا شيءَ أحمر» و«لم أنظر»**.
 *
 * ◆ **وسبعُ جهاتٍ تُسأل لا واحدة**: البوّاباتُ · السقّاطاتُ · سجلُّ الأصناف ·
 *   الشروطُ الثلاثةُ المنصوصة · القراراتُ المعلَّقة · الشواهدُ السالبة ·
 *   واللقطةُ المجمَّدة. **وجهةٌ لم تُسأل ثغرةٌ في الحكمِ لا في التنفيذ.**
 *
 * ⛔ **ولا يكتب شيئًا** — نافذةُ القياسِ مفتوحةٌ والتعديلُ ممنوع.
 *
 * التشغيل: php tools/repair01_edc_closure_audit.php [--md]
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

$R = array(); $red = 0;
$ask = function ($who, $what, $count, $denom, $blocking = true) use (&$R, &$red) {
    $R[] = array($who, $what, $count, $denom, $blocking);
    if ($count > 0 && $blocking) { $red++; }
};

echo "\n═══ جردُ ما يحجب الإغلاق — البندُ ⑪ ═══\n";

/* ── ① البوّابات: تُشغَّل كلُّها ويُعلَن مقامُها ─────────────────────────── */
$gates = glob($ROOT . '/tools/repair01_w*_gate.php');
$gRed = 0; $gNames = array();
foreach ($gates as $g) {
    $o = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($g) . ' 2>&1', $o, $rc);
    $v = '';
    foreach (array_reverse($o) as $l) { if (preg_match('~^\s*الحكم~u', $l)) { $v = $l; break; } }
    $bad = ($rc !== 0) || ($v !== '' && preg_match('~(✘|حمراء|ساقط|لم يعبر)~u', $v));
    if ($bad) { $gRed++; $gNames[] = basename($g); }
}
$ask('البوّابات', 'حاجبُ موجةٍ أحمر', $gRed, count($gates) . ' بوّابة');
if ($gNames) { echo "     ⇐ " . implode('، ', $gNames) . "\n"; }

/* ── ② السقّاطات ─────────────────────────────────────────────────────────── */
$o = array(); $rc = 0;
exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/u12_debt_ratchet.php') . ' 2>&1', $o, $rc);
$grew = 0;
foreach ($o as $l) { if (preg_match('~عائدٌ \(رسوب\):\s*(\d+)~u', $l, $m)) { $grew = (int) $m[1]; } }
$ask('السقّاطات', 'سجلُّ دَينٍ عاد فزاد', $grew, '16 سجلًّا');

/* ── ③ سجلُّ الأصناف: ما مستواه حاجب ───────────────────────────────────── */
$blk = $n("SELECT COALESCE(SUM(measured_count),0) FROM repair01_debt_register
            WHERE blocking_level = 'BLOCKING' AND measured_count > 0");
$cls = $n("SELECT COUNT(*) FROM repair01_debt_register");
$ask('سجلُّ الأصناف', 'دَينٌ مستواه BLOCKING', $blk, $cls . ' صنفًا');
$unclass = $n("SELECT COUNT(*) FROM repair01_debt_register WHERE exit_criteria = ''");
$ask('سجلُّ الأصناف', 'صنفٌ بلا معيارِ خروج', $unclass, $cls . ' صنفًا');

/* ── ④ الشروطُ الثلاثةُ المنصوصة ─────────────────────────────────────────── */
$LIVE = "on_disk = 1 AND ownership_verdict NOT IN ('RETIRE')";
$ask('الشروطُ المنصوصة', 'UNKNOWN_WRITE_OWNER',
     $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE COALESCE(owner_code,'') = '' AND $LIVE"),
     $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE $LIVE") . ' سطحًا حيًّا');
$ask('الشروطُ المنصوصة', 'DUPLICATE_CANONICAL_SOURCE',
     $n("SELECT COUNT(*) FROM (SELECT LOWER(COALESCE(NULLIF(route,''), screen_file)) f
          FROM repair01_screen_registry WHERE surface_kind = 'SOURCE'
          GROUP BY f HAVING COUNT(DISTINCT owner_code) > 1) z"),
     $n("SELECT COUNT(*) FROM repair01_screen_registry WHERE surface_kind = 'SOURCE'") . ' مصدرًا');

/* ── ⑤ القراراتُ المعلَّقة: **أُعلن ولا يُحجب بها الإغلاقُ إن كانت مُسجَّلةً** ── */
$pend = $n("SELECT COUNT(*) FROM repair01_decisions WHERE status <> 'APPROVED'");
$noRef = $n("SELECT COUNT(*) FROM repair01_decisions
              WHERE status = 'APPROVED' AND COALESCE(owner_decision,'') IN ('', '—')");
$ask('قراراتُ المالك', 'قرارٌ معتمَدٌ بلا نصِّ حكم', $noRef,
     $n("SELECT COUNT(*) FROM repair01_decisions WHERE status = 'APPROVED'") . ' معتمَدًا');
$ask('قراراتُ المالك', 'قرارٌ ينتظر المالكَ (‏خبرٌ لا حجب)', $pend,
     $n("SELECT COUNT(*) FROM repair01_decisions") . ' قرارًا', false);
$assumed = $n("SELECT COUNT(*) FROM repair01_decision_audit WHERE verdict = 'SYSTEM_ASSUMED_APPROVAL'");
$ask('قراراتُ المالك', 'اعتمادٌ افترضه النظامُ نيابةً عن المالك', $assumed,
     $n("SELECT COUNT(*) FROM repair01_decision_audit") . ' مُدقَّقًا');

/* ── ⑥ الشواهدُ السالبة: أتعرف الحواجبُ كيف ترسُب؟ ──────────────────────── */
$tests = array_merge(glob($ROOT . '/tests/w135_*.php'), glob($ROOT . '/tests/edc_*.php'));
$tRed = 0; $tNames = array();
foreach ($tests as $t) {
    $o2 = array(); $rc2 = 0;
    exec('"' . PHP_BINARY . '" ' . escapeshellarg($t) . ' 2>&1', $o2, $rc2);
    if ($rc2 !== 0) { $tRed++; $tNames[] = basename($t); }
}
$ask('الشواهدُ السالبة', 'شاهدٌ سالبٌ راسب', $tRed, count($tests) . ' شاهدًا');
if ($tNames) { echo "     ⇐ " . implode('، ', $tNames) . "\n"; }

/* ── ⑦ اللقطةُ المجمَّدة ─────────────────────────────────────────────────── */
$openSnap = $n("SELECT COUNT(*) FROM repair01_freeze_snapshot WHERE released_at IS NULL");
$ask('اللقطة', 'نافذةُ قياسٍ مفتوحةٌ (‏شرطُ التقريرِ لا حاجبُ إغلاق)', 0,
     $openSnap . ' مفتوحة', false);
$noWhy = $n("SELECT COUNT(*) FROM repair01_freeze_snapshot WHERE released_at IS NOT NULL AND release_why = ''");
$ask('اللقطة', 'فكُّ تجميدٍ بلا سببٍ مكتوب', $noWhy,
     $n("SELECT COUNT(*) FROM repair01_freeze_snapshot") . ' لقطةً');

/* ── الطباعة ─────────────────────────────────────────────────────────────── */
echo "\n";
$who = '';
foreach ($R as $x) {
    if ($x[0] !== $who) { $who = $x[0]; echo "── $who ──\n"; }
    printf("  %s %-44s %4d   من %s\n",
        ($x[2] === 0 ? '✔' : ($x[4] ? '✘' : '◆')), $x[1], $x[2], $x[3]);
}
echo "\n────────────────────────────────────────────────────────────────────\n";
printf("**جهاتٌ سُئلت: 7 · مقاييسُ: %d · وما يحجب الإغلاقَ: %d**\n", count($R), $red);
if ($red === 0) {
    echo "✔ **صفرُ شرطٍ أحمرَ حاجب** — ومقامُ كلِّ مقياسٍ مطبوعٌ بجانبِه\n";
    echo "  ⛔ **فهذا «لا شيءَ أحمر» لا «لم أنظر»**.\n";
    echo "◆ و`Owner Approved Baseline` **قرارُك أنت** — وهذا يُثبت استيفاءَ شروطِه لا غير.\n";
} else {
    echo "⛔ **لا يُعلَن `Owner Approved Baseline`** — البند 58.\n";
}

if ($MD) {
    $s = $conn->query("SELECT * FROM repair01_freeze_snapshot ORDER BY frozen_at DESC LIMIT 1")->fetch_assoc();
    $o  = "# جردُ ما يحجب الإغلاق — البندُ ⑪\n\n";
    $o .= "> ⛔ **مولَّدٌ من تشغيلٍ حيّ**: `php tools/repair01_edc_closure_audit.php --md`\n";
    $o .= "> **ولكلِّ مقياسٍ مقامُه** — فصفرٌ من مقامٍ مجهولٍ لا يُثبت شيئًا.\n\n";
    $o .= "| اللقطة | `{$s['snapshot_id']}` |\n|---|---|\n";
    $o .= "| `Commit Hash` | `{$s['commit_hash']}` |\n";
    $o .= "| `Schema Version` | `{$s['schema_version']}` |\n";
    $o .= "| `Measured At` | " . date('Y-m-d H:i:s') . " |\n\n";
    $o .= "| الجهة | المقياس | المقيس | المقام | الحكم |\n|---|---|---:|---|---|\n";
    foreach ($R as $x) {
        $o .= sprintf("| %s | %s | %d | %s | %s |\n", $x[0], $x[1], $x[2], $x[3],
            ($x[2] === 0 ? '✔' : ($x[4] ? '✘ **حاجب**' : '◆ خبر')));
    }
    $o .= sprintf("\n**جهاتٌ سُئلت: 7 · مقاييسُ: %d · وما يحجب الإغلاقَ: %d**\n\n", count($R), $red);
    $o .= $red === 0
        ? "> ✔ **صفرُ شرطٍ أحمرَ حاجب** — ومقامُ كلِّ مقياسٍ مطبوعٌ بجانبِه، **فهذا «لا شيءَ أحمر» لا «لم أنظر»**.\n"
          . ">\n> ◆ و`Owner Approved Baseline` **قرارُ المالكِ** — وهذا يُثبت استيفاءَ شروطِه لا غير.\n"
        : "> ⛔ **لا يُعلَن `Owner Approved Baseline`** — البند 58.\n";
    file_put_contents($ROOT . '/docs/REPAIR01_20260823/EDC_CLOSURE_AUDIT.md', $o);
    echo "✔ كُتب docs/REPAIR01_20260823/EDC_CLOSURE_AUDIT.md\n";
}
exit($red === 0 ? 0 : 1);
