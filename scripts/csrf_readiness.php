<?php
/**
 * csrf_readiness.php — جاهزيةُ كلِّ وحدةٍ لإنفاذ CSRF
 * ───────────────────────────────────────────────────────────────────────────
 * إنفاذُ CSRF متدرِّجٌ بالتصميم (ADR-05): `CSRF_ENFORCE_PATHS` في .env تُحدّد
 * المساراتِ المحجوبة، وما دونها في وضع «مراقبة» يسجّل ولا يحجب. التدرّجُ
 * حكيمٌ لكنه بلا مقياس — فيبقى المسار في المراقبة سنواتٍ لأن أحدًا لا يعرف
 * أيَّ وحدةٍ تنكسر لو حُجبت.
 *
 * هذه الأداةُ تعطي المقياس. الخطرُ الوحيدُ عند الإنفاذ هو نداءُ XHR بـPOST
 * لا يحمل رمزًا: الفورماتُ المولَّدةُ خادميًّا يحقن فيها `ems_inject_csrf_fields`
 * الرمزَ تلقائيًّا، أما `fetch`/`XMLHttpRequest` فعلى الشاشة أن ترسله بنفسها
 * (الحاقنُ يبثّه في `window.csrfToken`).
 *
 * التشغيل:  php scripts/csrf_readiness.php
 */

$root = dirname(__DIR__);

$modules = array();
foreach (scandir($root) as $entry) {
    if ($entry[0] === '.' || !is_dir($root . '/' . $entry)) { continue; }
    if (in_array($entry, array('vendor', 'node_modules', 'storage', 'logs', 'docs', 'tests',
                               'database', 'assets', 'scripts', 'install', 'tools', 'examples'), true)) { continue; }
    $modules[] = $entry;
}
sort($modules);

// المساراتُ المحجوبة الآن
$envFile = $root . '/.env';
$enforced = array();
if (is_readable($envFile)) {
    foreach (file($envFile) as $line) {
        if (strpos($line, 'CSRF_ENFORCE_PATHS=') === 0) {
            foreach (explode(',', trim(substr($line, strlen('CSRF_ENFORCE_PATHS=')))) as $p) {
                $p = trim($p);
                if ($p !== '') { $enforced[] = $p; }
            }
        }
    }
}

printf("%-16s %7s %7s %9s  %s\n", 'الوحدة', 'POST', 'XHR', 'محجوبة', 'الحكم');
echo str_repeat('─', 78), "\n";

$ready = array(); $blocked = array();
foreach ($modules as $mod) {
    $files = glob($root . '/' . $mod . '/*.php');
    if (!$files) { continue; }

    $postFiles = 0; $riskFiles = array();
    foreach ($files as $f) {
        $src = file_get_contents($f);
        if ($src === false) { continue; }
        $handlesPost = (bool) preg_match('/REQUEST_METHOD[^;]{0,40}POST/i', $src);
        if ($handlesPost) { $postFiles++; }

        // نداءُ XHR بـPOST — الخطرُ الوحيد عند الإنفاذ
        $hasXhrPost = (bool) preg_match('/(method\s*:\s*[\'"]POST|type\s*:\s*[\'"]POST|\.open\(\s*[\'"]POST)/i', $src);
        if (!$hasXhrPost) { continue; }
        $sendsToken = (bool) preg_match('/(csrfToken|csrf_token|X-CSRF-Token)/i', $src);
        if (!$sendsToken) { $riskFiles[] = basename($f); }
    }
    if ($postFiles === 0 && !$riskFiles) { continue; }

    $isEnforced = false;
    foreach ($enforced as $p) {
        if (stripos('/' . $mod . '/', $p) !== false) { $isEnforced = true; break; }
    }

    if ($riskFiles) {
        $verdict = 'يحتاج عملًا — ' . count($riskFiles) . ' ملفًا: ' . implode(', ', array_slice($riskFiles, 0, 3));
        $blocked[$mod] = $riskFiles;
    } elseif ($isEnforced) {
        $verdict = 'محجوبةٌ سلفًا ✅';
    } else {
        $verdict = 'جاهزةٌ للحجب ← أضِفها إلى CSRF_ENFORCE_PATHS';
        $ready[] = $mod;
    }

    printf("%-16s %7d %7d %9s  %s\n", $mod, $postFiles, count($riskFiles),
           $isEnforced ? 'نعم' : 'لا', $verdict);
}

echo "\n";
if ($ready) {
    echo "جاهزةٌ للحجب فورًا (فوراتُها مولَّدةٌ خادميًّا فالحاقن يغطّيها):\n  "
        . implode(' · ', $ready) . "\n\n";
    echo "السطرُ المقترح في .env:\n  CSRF_ENFORCE_PATHS="
        . implode(',', array_merge($enforced, array_map(function ($m) { return '/' . $m . '/'; }, $ready))) . "\n\n";
}
if ($blocked) {
    echo "تحتاج عملًا قبل الحجب — أضِف الرمزَ إلى نداءات XHR فيها:\n";
    foreach ($blocked as $mod => $files) {
        echo '  ' . $mod . ': ' . implode(', ', $files) . "\n";
    }
    echo "\n  النمطُ المطلوب في كل نداء:\n";
    echo "    headers: { 'X-CSRF-Token': window.csrfToken }   // أو حقل csrf_token في الجسم\n";
}
