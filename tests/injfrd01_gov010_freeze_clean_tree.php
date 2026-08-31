<?php
/**
 * tests/injfrd01_gov010_freeze_clean_tree.php — FR-GOV-010 · GAP-70
 * ═══════════════════════════════════════════════════════════════════════════
 * «لا قياسَ أثناءَ بناءٍ ولا بناءَ أثناءَ قياس»: نافذةُ القياسِ تشترط شجرةً
 * مُلزَمةً نظيفةً وبصمةَ التزامٍ دقيقة.
 *   ① حارسُ النظافةِ قائمٌ في أداةِ التجميدِ نفسِها ويقرأ `status --porcelain`.
 *   ② آخرُ لقطةِ تجميدٍ مقيَّدةٌ ببصمةِ التزامٍ لا بساعةِ حائطٍ وحدَها.
 *   ③ السالبُ الحيّ: ملفٌّ يوسِّخ الشجرةَ ⇒ **التجميدُ يرفض** ويسمّي السببَ ·
 *     ثم يُكنس الملفُّ ويُثبَت الكنس.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$DIRT = $ROOT . '/_gov010_dirt_probe.tmp';

$pass = 0; $fail = 0;
function ok($c, $l, $d = '')
{
    global $pass, $fail;
    if ($c) { $pass++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
    else    { $fail++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; }
}
register_shutdown_function(function () use ($DIRT) {
    if (is_file($DIRT) && !@unlink($DIRT)) {
        fwrite(STDERR, "⛔ كنسُ ملفِّ التوسيخِ فشل — احذفْ يدويًّا: {$DIRT}\n");
    }
});

echo "══ FR-GOV-010 — لا تجميدَ على شجرةٍ متّسخة ══\n";

/* ── ① الحارسُ في الأداة ─────────────────────────────────────────────────── */
$src = (string) @file_get_contents($ROOT . '/tools/repair01_freeze.php');
ok(strpos($src, 'status --porcelain') !== false && mb_strpos($src, 'نظيفة') !== false,
   '① حارسُ النظافةِ قائمٌ في `repair01_freeze` ويقرأ حالَ الشجرةِ من git');

/* ── ② آخرُ لقطةٍ ببصمةِ التزام ──────────────────────────────────────────── */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
require_once $ROOT . '/config.php';
$conn = $GLOBALS['conn'];
/* الترتيبُ بمفتاحِ الإدراجِ لا بساعةِ الحائط — درسُ GAP-71 نفسُه */
$q = @$conn->query("SELECT `commit_hash`, `frozen_at` FROM `repair01_freeze_snapshot` ORDER BY `snapshot_id` DESC LIMIT 1");
$r = $q ? $q->fetch_assoc() : null;
$sha = $r ? trim((string) ($r['commit_hash'] ?? '')) : '';
ok($sha !== '', '② آخرُ لقطةِ تجميدٍ تحمل بصمةَ التزامٍ دقيقةً لا ساعةَ حائطٍ وحدَها',
   $sha !== '' ? mb_substr($sha, 0, 12) . ' @ ' . ($r['frozen_at'] ?? '؟') : 'لا بصمة');

/* ── ③ السالبُ الحيّ ─────────────────────────────────────────────────────── */
file_put_contents($DIRT, "مِجَسُّ توسيخٍ لشاهدِ FR-GOV-010 — يُكنس فورًا\n");
$out = array(); $rc = 0;
@exec('"' . PHP_BINARY . '" ' . escapeshellarg($ROOT . '/tools/repair01_freeze.php')
    . ' --purpose="مِجَسُّ سالبِ FR-GOV-010" --kind=diagnostic 2>&1', $out, $rc);
$txt = implode("\n", $out);
ok($rc !== 0, '③ **التجميدُ رفض الشجرةَ المتّسخةَ** — برمزٍ غيرِ صفريّ', "rc={$rc}");
ok(mb_strpos($txt, 'متّسخة') !== false || mb_strpos($txt, 'نظيفة') !== false,
   'والرفضُ يسمّي سببَه — النظافةُ شرطٌ مطبوع');

if (!@unlink($DIRT)) { ok(false, 'كُنس ملفُّ التوسيخ', 'فشل الحذف'); }
else { ok(true, 'كُنس ملفُّ التوسيخِ — صفرُ بقايا'); }

echo str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
