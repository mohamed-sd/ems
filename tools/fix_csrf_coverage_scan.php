<?php
/**
 * tools/fix_csrf_coverage_scan.php — المسافةُ إلى تعميمِ إنفاذِ CSRF
 * ═══════════════════════════════════════════════════════════════════════════
 * عشراتُ اختباراتِ القبولِ في عائلةِ الصلاحياتِ تطلب «POST بلا رمزٍ صالحٍ يُرفض».
 * والإنفاذُ اليومَ على **خمسةِ مجلداتٍ** فقط (`CSRF_ENFORCE_PATHS`)، فالشرطُ
 * غيرُ محقَّقٍ **بنيويًّا** خارجَها.
 *
 * ◆ والتوسيعُ بلا تحضيرٍ فخٌّ مسجَّل: حين وُسِّع سابقًا رُدَّت ٥٥ شاشةً بـ١٣٤
 *   نموذجٍ عاديٍّ بـ403 **لكلِّ مستخدم** — لأنَّ الحقنَ الآليَّ يشمل
 *   `fetch`/`XHR` وحدَها، والنموذجُ العاديُّ يحتاج `csrf_field()` في جسمِه.
 *
 * فهذه الأداةُ تقيس **الفجوةَ قبل التوسيع**: أيُّ نموذجِ POST عاديٍّ في الشجرةِ
 * الحيّةِ لا يحمل رمزًا في جسمِه؟ وأيُّ مجلدٍ جاهزٌ للإنفاذِ وأيُّه ليس؟
 *
 *   php tools/fix_csrf_coverage_scan.php [--fix] [--dir=Financing]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
/* ◆ المسارُ يُسوَّى بالشرطةِ الأمامية: `dirname(__DIR__)` يعود بشرطاتٍ خلفيةٍ على
     ويندوز، فيفشل قصُّ الجذرِ ويبقى المسارُ مطلقًا — وهو فخٌّ وقع مرّتين قبلُ
     (أعطى «مجلدًا» اسمُه `C:` لكلِّ الملفات). */
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$FIX = in_array('--fix', $argv, true);
$DIR = null;
foreach ($argv as $a) { if (strpos($a, '--dir=') === 0) { $DIR = substr($a, 6); } }

require_once $ROOT . '/includes/env.php';
$enforced = array_filter(array_map('trim', explode(',', (string) ems_env('CSRF_ENFORCE_PATHS', ''))));

$dead = '~/(storage/backups|\.claude|vendor|node_modules|tests|tools|docs)/~';
$byDir = array();     /* مجلد => array(forms, missing, files[]) */
$missingList = array();

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $p) {
    $path = str_replace('\\', '/', $p->getPathname());
    if (substr($path, -4) !== '.php' || preg_match($dead, $path)) { continue; }
    $rel = str_replace($ROOT . '/', '', $path);
    if ($DIR !== null && strpos($rel, $DIR . '/') !== 0) { continue; }
    $s = (string) @file_get_contents($path);
    if (stripos($s, '<form') === false) { continue; }
    $dir = (strpos($rel, '/') !== false) ? substr($rel, 0, strpos($rel, '/')) : '(الجذر)';
    if (!isset($byDir[$dir])) { $byDir[$dir] = array('forms' => 0, 'missing' => 0, 'files' => array()); }

    /* كلُّ نموذجٍ بـmethod=post */
    if (preg_match_all('~<form\b[^>]*>(.*?)</form>~si', $s, $m, PREG_SET_ORDER)) {
        foreach ($m as $f) {
            $tag = $f[0];
            if (!preg_match('~method\s*=\s*["\']?post~i', $tag)) { continue; }
            $byDir[$dir]['forms']++;
            $body = $f[1];
            $hasTok = (stripos($body, 'csrf_token') !== false)
                   || (stripos($body, 'csrf_field') !== false);
            if (!$hasTok) {
                $byDir[$dir]['missing']++;
                if (!in_array($rel, $byDir[$dir]['files'], true)) { $byDir[$dir]['files'][] = $rel; }
                $missingList[] = $rel;
            }
        }
    }
}

uasort($byDir, function ($a, $b) { return $b['missing'] - $a['missing']; });

echo "══ تغطيةُ رمزِ الحمايةِ في النماذجِ العادية ══\n\n";
echo "  المُنفَذُ اليوم: " . (count($enforced) ? implode(' · ', $enforced) : 'لا شيء') . "\n\n";
printf("  %-18s %7s %9s %s\n", 'المجلد', 'نماذج', 'بلا رمز', 'الحكم');
echo '  ' . str_repeat('─', 62) . "\n";
$totF = 0; $totM = 0; $ready = array(); $notReady = array();
foreach ($byDir as $d => $v) {
    if ($v['forms'] === 0) { continue; }
    $totF += $v['forms']; $totM += $v['missing'];
    $isEnf = false;
    foreach ($enforced as $e) { if (stripos('/' . $d . '/', $e) !== false) { $isEnf = true; break; } }
    $verdict = $isEnf ? 'مُنفَذٌ سلفًا' : ($v['missing'] === 0 ? '**جاهزٌ للإنفاذ**' : 'يحتاج ' . $v['missing'] . ' حقنًا');
    if (!$isEnf) { if ($v['missing'] === 0) { $ready[] = $d; } else { $notReady[] = $d; } }
    printf("  %-18s %7d %9d %s\n", $d, $v['forms'], $v['missing'], $verdict);
}
echo "\n  المجموع: {$totF} نموذجًا · بلا رمزٍ {$totM}\n";
echo "\n  مجلداتٌ **جاهزةٌ للإنفاذِ فورًا** (صفرُ نموذجٍ بلا رمز):\n     "
   . (count($ready) ? implode(' · ', $ready) : '—') . "\n";
if ($notReady) {
    echo "\n  مجلداتٌ تحتاج حقنًا قبل الإنفاذ:\n     " . implode(' · ', $notReady) . "\n";
}
exit(0);
