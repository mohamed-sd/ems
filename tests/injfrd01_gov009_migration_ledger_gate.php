<?php
/**
 * tests/injfrd01_gov009_migration_ledger_gate.php — FR-GOV-009 · GAP-69
 * ═══════════════════════════════════════════════════════════════════════════
 * «رسوبٌ صلبٌ فوريٌّ — ولا نافذةَ ظلّ»: أيُّ هجرةٍ لا تسجّل نفسَها في
 * `schema_migrations` ترسُب على البوّابة. **والمعيارُ إجماليُّ غيرِ المصالَحِ
 * = صفر** لا «الجديدُ فقط».
 *
 * ثلاثةُ فحوص:
 *   ① الموجب — البوّابةُ `tools/repair01_migration_gate.php` خضراءُ 4/4 الآن.
 *   ② المقام — `gov_migration_settlement.verified=0` إجماليُّه صفر.
 *   ③ السالبُ بالكسر — هجرةٌ صوريّةٌ تُحقن على القرصِ لا تسجّل نفسَها
 *     ⇒ **البوّابةُ ترسُب برمزٍ غيرِ صفريٍّ** · ثم تُكنس ويعود الأخضر.
 *     ⛔ وكنسٌ يفشل يُسمّى فورًا — لا بقايا حقنٍ تُقرأ غدًا عطبَ إنتاج
 *     (درسُ كنسِ التسويةِ الذي خلَّف EFFECT_MISSING).
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$GATE = $ROOT . '/tools/repair01_migration_gate.php';
$PROBE = $ROOT . '/database/migrations/2099_12_31_gov009_negative_probe.php';

$pass = 0; $fail = 0;
function ok($c, $l, $d = '')
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
function runGate($GATE)
{
    $out = array(); $rc = 0;
    @exec('"' . PHP_BINARY . '" ' . escapeshellarg($GATE) . ' 2>&1', $out, $rc);
    return array($rc, implode("\n", $out));
}

/* الكنسُ مضمونٌ ولو انفجر فحصٌ في المنتصف — والفشلُ يُسمّى */
register_shutdown_function(function () use ($PROBE) {
    if (is_file($PROBE) && !@unlink($PROBE)) {
        fwrite(STDERR, "⛔ كنسُ المِجَسِّ فشل — احذفْ يدويًّا: {$PROBE}\n");
    }
});

echo "══ FR-GOV-009 — بوّابةُ دفترِ الهجرات ══\n";

/* ── ① الموجب ────────────────────────────────────────────────────────────── */
list($rc0, $out0) = runGate($GATE);
ok($rc0 === 0, '① البوّابةُ خضراءُ الآن برمزِ خروجٍ صفريّ', "rc={$rc0}");
ok(strpos($out0, '4/4') !== false, 'والحواجبُ الأربعةُ كلُّها خضراء');

/* ── ② المقام — إجماليُّ غيرِ المصالَح ──────────────────────────────────── */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/config.php';
$conn2 = $GLOBALS['conn'];
$q = $conn2->query('SELECT COUNT(*) FROM `gov_migration_settlement` WHERE `verified` = 0');
$unrec = $q ? (int) $q->fetch_row()[0] : -1;
ok($unrec === 0, '② إجماليُّ الهجراتِ غيرِ المصالَحةِ صفر — لا «الجديدُ فقط»', "غيرُ مصالَحٍ={$unrec}");

/* ── ③ السالبُ بالكسر ────────────────────────────────────────────────────── */
$w = @file_put_contents($PROBE,
    "<?php\n/* مِجَسُّ FR-GOV-009 السالب — هجرةٌ صوريّةٌ لا تسجّل نفسَها في الدفتر. تُكنس فورَ القياس. */\n\$x = 1;\n");
ok($w !== false, '③ حُقنت هجرةٌ صوريّةٌ لا تسجّل نفسَها', basename($PROBE));

list($rcN, $outN) = runGate($GATE);
ok($rcN !== 0, '**البوّابةُ رسبت فعلًا على الحقن** — برمزٍ غيرِ صفريّ', "rc={$rcN}");
ok(strpos($outN, 'gov009_negative_probe') !== false, 'والرسوبُ يسمّي المِجَسَّ باسمِه لا بعدٍّ مجرّد');

if (!@unlink($PROBE)) { ok(false, 'كُنس المِجَسُّ', 'فشل الحذفُ — لا يُبتلع'); }
else { ok(true, 'كُنس المِجَسُّ — صفرُ بقايا'); }

list($rc2, $out2) = runGate($GATE);
ok($rc2 === 0, 'وعادت البوّابةُ خضراءَ بعد الكنس', "rc={$rc2}");

echo str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
