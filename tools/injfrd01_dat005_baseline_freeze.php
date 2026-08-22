<?php
/**
 * tools/injfrd01_dat005_baseline_freeze.php
 *   FR-DAT-005 · CHG-DAT-BASELINE-01 — لقطةُ الأساسِ تُجمَّد ببصمةٍ تكشف المسّ
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المطلب** (الدفتر · GAP-58 · P2): «لقطةُ الأساسِ **مجلدٌ لا يُكتب فيه بعدَ
 *   تجميدِه** — وله بصمةُ محتوًى» · ومعيارُ قبولِه: «**محاولةُ كتابةٍ مرفوضةٌ +
 *   بصمةٌ مطابقة**».
 *
 * ◆ **والعطبُ وقع فعلًا لا احتمالًا**: أُعيد توليدُ واحدٍ وعشرين ملفًّا في
 *   `docs/baseline_20260821/` **في مكانِها على قاعدةِ اليوم** — والدليلُ أن
 *   استخراجَ الوحداتِ صار يحمل مساراتٍ لم تكن يومَ اللقطة. **ولقطةُ أساسٍ
 *   تُعاد كتابتُها في مكانِها تُلغي طرفَ «قبل» في كلِّ مقارنة**.
 *
 * ◆ **والمنعُ في نظامِ الملفاتِ ليس بيدِ هذه الأداة** (ولا تُغيَّر أذوناتُ
 *   القرصِ من فاحص) — فالتجميدُ **يُكشَف لا يُمنَع**: بصمةٌ لكلِّ ملفٍّ في
 *   بيانٍ مختوم، وأيُّ مسٍّ لاحقٍ يُكتشف **آليًّا** بأوّلِ تشغيل. وهذا نصُّ
 *   §خامسًا-10: «ممنوع الكتابة عليها · **وأي محاولة تعديل تُكتشف آليًا**».
 *
 * التشغيل:
 *   php tools/injfrd01_dat005_baseline_freeze.php --freeze=<مجلد>   يختم
 *   php tools/injfrd01_dat005_baseline_freeze.php --verify          يقيس
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$SEAL = $ROOT . '/docs/baseline_seals.json';

$freeze = '';
foreach ($argv as $a) { if (strpos($a, '--freeze=') === 0) { $freeze = substr($a, 9); } }
$verify = in_array('--verify', $argv, true);

/**
 * بصمةُ كلِّ ملفٍّ **كما هو مُلتزَمٌ في الالتزامِ الحاليّ** — لا كما في الشجرة.
 *
 * ◆ **ولماذا من الالتزامِ لا من القرص**: أوّلُ كتابةٍ ختمت الشجرةَ، وفيها
 *   تعديلاتٌ حيّةٌ غيرُ مُلتزَمةٍ لجلسةٍ أخرى تُعيد توليدَ اللقطة. فخُتم
 *   **محتوًى ملوَّثٌ** بوصفِه أساسًا. واللقطةُ المجمَّدةُ هي **ما التُزم**،
 *   وهو وحدَه ثابتٌ وقابلٌ لإعادةِ الإنتاجِ على نفسِ البناء.
 * ◆ وبهذا **لا تمسُّ الأداةُ ملفًّا** ولا تحتاج إلى مسِّه — تقرأ من الفهرس.
 */
function seal_tree($dir, $repoRoot, $relDir)
{
    $out = array();
    $cmd = 'git -C ' . escapeshellarg($repoRoot) . ' ls-tree -r --name-only HEAD -- '
         . escapeshellarg($relDir) . ' 2>&1';
    $ls = (string) @shell_exec($cmd);
    foreach (explode("\n", $ls) as $path) {
        $path = trim($path);
        if ($path === '') { continue; }
        $blob = (string) @shell_exec('git -C ' . escapeshellarg($repoRoot)
              . ' show ' . escapeshellarg('HEAD:' . $path) . ' 2>&1');
        if ($blob === '') { continue; }
        $rel = ltrim(substr($path, strlen(rtrim($relDir, '/'))), '/');
        $out[$rel] = hash('sha256', $blob);
    }
    ksort($out);
    return $out;
}

$seals = is_file($SEAL) ? (array) json_decode((string) file_get_contents($SEAL), true) : array();

if ($freeze !== '') {
    $dir = $ROOT . '/' . ltrim($freeze, '/');
    if (!is_dir($dir)) { exit("⛔ مجلدٌ غيرُ موجود: {$freeze}\n"); }
    $files = seal_tree($dir, $ROOT, $freeze);
    if (!$files) { exit("⛔ مجلدٌ فارغ — لا يُختم فراغ\n"); }
    $seals[$freeze] = array(
        'sealed_at'   => date('Y-m-d H:i:s'),
        'file_count'  => count($files),
        'tree_hash'   => hash('sha256', json_encode($files)),
        'files'       => $files,
    );
    file_put_contents($SEAL, json_encode($seals, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    printf("✔ خُتمت «%s» — %d ملفًّا · بصمةُ الشجرة %s\n",
           $freeze, count($files), substr($seals[$freeze]['tree_hash'], 0, 16));
    echo "  ◆ وأيُّ مسٍّ بعدَ هذه اللحظةِ يُكتشف بـ--verify.\n";
    exit(0);
}

if (!$verify) { exit("مرِّر --freeze=<مجلد> أو --verify\n"); }

if (!$seals) { exit("⛔ لا ختمَ مسجَّل — لا شيءَ يُقاس\n"); }

echo "════ FR-DAT-005 · تجميدُ لقطةِ الأساسِ — البصمةُ تكشف المسّ ════\n";
$bad = 0;
foreach ($seals as $rel => $seal) {
    $dir = $ROOT . '/' . ltrim($rel, '/');
    $now = seal_tree($dir, $ROOT, $rel);
    $nowHash = hash('sha256', json_encode($now));
    $same = ($nowHash === $seal['tree_hash']);
    printf("\n── %s (خُتمت %s · %d ملفًّا)\n", $rel, $seal['sealed_at'], (int) $seal['file_count']);
    if ($same) {
        printf("  ✔ **بصمةٌ مطابقة** — صفرُ مسٍّ منذ الختم\n");
        continue;
    }
    $bad++;
    $changed = array(); $added = array(); $removed = array();
    foreach ($seal['files'] as $f => $h) {
        if (!isset($now[$f]))      { $removed[] = $f; }
        elseif ($now[$f] !== $h)   { $changed[] = $f; }
    }
    foreach ($now as $f => $h) { if (!isset($seal['files'][$f])) { $added[] = $f; } }
    printf("  ✘ **البصمةُ اختلفت** — عُدِّل %d · أُضيف %d · حُذف %d\n",
           count($changed), count($added), count($removed));
    foreach (array_slice($changed, 0, 6) as $f) { echo "     ~ {$f}\n"; }
    if (count($changed) > 6) { echo "     … و" . (count($changed) - 6) . " غيرُها\n"; }
    foreach (array_slice($removed, 0, 4) as $f) { echo "     − {$f}\n"; }
    echo "  ◆ **ولقطةُ أساسٍ تُعاد كتابتُها في مكانِها تُلغي طرفَ «قبل» في كلِّ مقارنة.**\n";
}
echo "\n" . str_repeat('─', 66) . "\n";
printf("لقطاتٌ مختومة: %d · **مَمسوسةٌ: %d**\n", count($seals), $bad);
exit($bad === 0 ? 0 : 1);
