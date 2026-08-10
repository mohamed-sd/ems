<?php
/**
 * tools/fix_u11_breakpoints.php — توحيدُ نقاطِ الكسرِ إلى خمسٍ (AC-U11 · SH-10)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ 23 نقطةَ كسرٍ متمايزةً في ما نؤلّفه (360 · 480 · 560 · 575 · 640 · 700 …).
 *   وكلُّ نقطةٍ إضافيةٍ عرضٌ يجب اختبارُه: ثلاثةٌ وعشرون تعني ثلاثةً وعشرين
 *   حدًّا يمكن أن ينكسر عنده شيء، ولا أحدَ يختبرها كلَّها — فتُكتشف الأعطالُ
 *   على أجهزةِ المستخدمين.
 *
 * ◆ الخمسُ المعتمدةُ **ليست اختياري**: هي حدودُ بوتستراب المحمَّلِ في النظامِ
 *   أصلًا (576 · 768 · 992 · 1200 · 1400)، وأكثرُ نقاطِنا استعمالًا يقع عليها
 *   سلفًا (768 ×24 · 992 ×3 · 1200 ×1). فالتوحيدُ يُلحق الشاذَّ بالسائدِ لا
 *   يفرض معيارًا غريبًا.
 *
 * ◆ والحدُّ الكسريُّ `.98` من عرفِ بوتستراب نفسِه: `max-width: 767.98px` يقابل
 *   `min-width: 768px` بلا تراكبٍ ولا فجوةٍ عند البكسل الحدّيّ.
 *
 * ◆ والإسناد **بالحدِّ الأعلى لا بالأقرب**: نقطةٌ عند 640 تخدم الهواتفَ، فتُلحق
 *   بـ768 (الحدُّ الذي يليها) لا بـ576 (الأقرب رقمًا) — وإلحاقُها بالأصغر يترك
 *   الأجهزةَ بين 576 و640 بلا تنسيقٍ كانت تناله.
 *
 * التشغيل: php tools/fix_u11_breakpoints.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);
require_once $ROOT . '/tools/fix_lib.php';

$VENDOR = array('bootstrap', 'jquery', 'datatables', 'fontawesome', 'select2',
                'flatpickr', 'chart', 'leaflet', 'swiper', 'animate', '.min.css');
$CANON = array(576, 768, 992, 1200, 1400);

/**
 * حدُّ مدى `max-width` — **أصغرُ قانونيٍّ ≥ القيمة**.
 *
 * ◆ والفرقُ بين `>=` و`>` ليس تفصيلًا: بـ`>` كانت `max-width: 768` تُرحَّل إلى
 *   `991.98` — فقاعدةٌ مكتوبةٌ للهاتفِ تمتدُّ إلى اللوحيِّ وينقلب تنسيقُه.
 *   و768 حدٌّ قانونيٌّ أصلًا فمداه هو مداه، لا يُوسَّع.
 */
function u11_bound($px, array $canon)
{
    foreach ($canon as $c) {
        if ($px <= $c) { return $c; }
    }
    return end($canon);
}

$stamp = 'u11_' . gmdate('Ymd_His');
$backupDir = $ROOT . '/storage/backups/' . $stamp;
$files = 0; $edits = 0; $map = array();

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'css') { continue; }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($ROOT) + 1));
    if (fix_is_skipped($rel)) { continue; }
    $skip = false;
    foreach ($VENDOR as $v) { if (stripos($rel, $v) !== false) { $skip = true; break; } }
    if ($skip) { continue; }

    $src = (string) file_get_contents($f->getPathname());
    $fileEdits = 0;

    $new = preg_replace_callback(
        '/(\(\s*)(min|max)(-width\s*:\s*)(\d+(?:\.\d+)?)(px\s*\))/i',
        function ($m) use ($CANON, &$fileEdits, &$map) {
            $kind = strtolower($m[2]);
            $px   = (float) $m[4];
            $bound = u11_bound($px, $CANON);

            if ($kind === 'max') {
                // مدى «حتى الحدّ» — يُكتب كسريًّا فلا يتراكب مع min عند البكسل نفسِه
                $val = number_format($bound - 0.02, 2, '.', '');
            } else {
                // مدى «من الحدّ فصاعدًا» — و`min-width: 769` كان يعني «فوق 768»
                $val = (string) (($px > $bound) ? $bound : u11_min_bound($px, $CANON));
            }
            if ((float) $val === $px) { return $m[0]; }   // لا تغييرَ فلا تُمَسّ
            $fileEdits++;
            // المفتاحُ بالقيمةِ كاملةً: `rtrim('0')` كان يحوّل 360 إلى 36 و700 إلى 7.
            $map[$kind . ':' . rtrim(rtrim(number_format($px, 2, '.', ''), '0'), '.')] = $kind . ':' . $val;
            return $m[1] . $m[2] . $m[3] . $val . $m[5];
        },
        $src
    );

    if ($new === null || $fileEdits === 0) { continue; }
    if ($apply) {
        $bdir = $backupDir . '/' . dirname($rel);
        if (!is_dir($bdir)) { @mkdir($bdir, 0777, true); }
        @copy($f->getPathname(), $backupDir . '/' . $rel);
        file_put_contents($f->getPathname(), $new);
    }
    $files++; $edits += $fileEdits;
}

/** الحدُّ لمدى `min-width` — أكبرُ قانونيٍّ ≤ القيمة، وإلا أصغرُ القانونيات. */
function u11_min_bound($px, array $canon)
{
    $best = $canon[0];
    foreach ($canon as $c) { if ($c <= $px) { $best = $c; } }
    return $best;
}

ksort($map);
echo ($apply ? 'طُبِّق' : 'سيُطبَّق') . ": {$files} ملفًّا · {$edits} نقطة\n";
foreach ($map as $from => $to) { printf("  %-14s → %s\n", $from, $to); }
if ($apply) { echo "النسخُ الاحتياطيّ: storage/backups/{$stamp}\n"; }
