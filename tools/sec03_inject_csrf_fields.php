<?php
/**
 * sec03_inject_csrf_fields.php — إغلاقُ B3: نماذجُ POST بلا رمزِ حماية
 * ═══════════════════════════════════════════════════════════════════════════
 * القياس: 293 نموذجَ POST من 464 بلا `csrf_token` — تحت 27 مسارَ إنفاذ.
 *
 * ◆ وهي **ليست ثغرةً فقط بل شاشاتٌ مكسورة**: الحاقنُ المركزيُّ يغطي
 *   `fetch`/XHR ولا يغطي النموذجَ العاديّ، والمسارُ تحت الإنفاذِ يردُّ 403 لكلِّ
 *   مستخدم. فالمستخدمُ يضغط «احفظ» فلا يُحفظ شيءٌ ولا يُفهم السبب.
 *
 * ◆ و`csrf_field()` متاحةٌ حيثما حُمِّل `config.php` (يُضمِّن `includes/security.php`).
 *   فمن لم يُحمِّله يُتخطّى ويُبلَّغ — ولا يُحقن نداءٌ لدالةٍ غيرِ معرَّفة.
 *
 * ◆ عاطلٌ: نموذجٌ فيه الرمزُ سلفًا يُترك. ونسخةٌ قبلَ اللمس. وفحصُ بناءٍ بعدَه،
 *   ومن كُسر يُعاد أصلُه.
 *
 * التشغيل: php tools/sec03_inject_csrf_fields.php [--apply] [--limit=N]
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$ROOT = 'C:/wamp64/www/ems';
$apply = in_array('--apply', $argv, true);
$limit = 0;
foreach ($argv as $a) { if (preg_match('/^--limit=(\d+)$/', $a, $m)) { $limit = (int) $m[1]; } }

/* مساراتُ الإنفاذِ تُقرأ من البيئةِ لا تُكتب هنا */
$env = @file_get_contents($ROOT . '/.env');
$prefixes = array();
if ($env && preg_match('/^CSRF_ENFORCE_PATHS=(.*)$/m', $env, $m)) {
    foreach (explode(',', trim($m[1])) as $p) {
        $p = trim($p, " \t\r\n/");
        if ($p !== '') { $prefixes[] = $p; }
    }
}
if (!$prefixes) { exit("تعذّر قراءةُ CSRF_ENFORCE_PATHS من .env\n"); }

$files = array();
foreach ($prefixes as $p) {
    $dir = $ROOT . '/' . $p;
    if (!is_dir($dir)) { continue; }
    foreach (glob($dir . '/*.php') ?: array() as $f) { $files[] = str_replace('\\', '/', $f); }
}
sort($files);

echo "══ إغلاقُ B3: حقنُ رمزِ الحماية ══\n";
echo '  مساراتُ الإنفاذ: ' . count($prefixes) . ' · ملفاتٌ في نطاقِها: ' . count($files) . "\n";
echo $apply ? "◆ وضعُ التطبيق\n\n" : "◆ عرضٌ فقط (أضف --apply)\n\n";

$totForms = 0; $totInject = 0; $filesTouched = 0; $skipNoConfig = 0; $broke = 0;
$report = array();

foreach ($files as $path) {
    if ($limit && $filesTouched >= $limit) { break; }
    $src = (string) file_get_contents($path);
    $rel = str_replace($ROOT . '/', '', $path);

    /* نماذجُ POST — الوسمُ قد يمتدُّ أسطرًا */
    if (!preg_match_all('/<form\b[^>]*>/is', $src, $mm, PREG_OFFSET_CAPTURE)) { continue; }

    $inject = array();
    foreach ($mm[0] as $tagInfo) {
        $tag = $tagInfo[0]; $at = $tagInfo[1];
        if (!preg_match('/method\s*=\s*["\']?\s*post/i', $tag)) { continue; }   // GET لا يلزمه
        $totForms++;
        /* جسمُ النموذجِ حتى إغلاقِه — الرمزُ قد يكون في أيِّ موضعٍ منه */
        $end = stripos($src, '</form>', $at);
        $body = $end === false ? substr($src, $at) : substr($src, $at, $end - $at);
        if (stripos($body, 'csrf_token') !== false || stripos($body, 'csrf_field') !== false) { continue; }
        $inject[] = $at + strlen($tag);
    }
    if (!$inject) { continue; }

    /* لا يُحقن نداءٌ لدالةٍ قد لا تكون محمَّلة */
    $hasConfig = (bool) preg_match('/(require|include)(_once)?[^;]*config\.php/i', $src)
              || (bool) preg_match('/(require|include)(_once)?[^;]*(inheader|insidebar|session_bootstrap)\.php/i', $src);
    if (!$hasConfig) {
        printf("  ⚠ %-52s %d نموذجًا — بلا config فتُخطّى\n", $rel, count($inject));
        $skipNoConfig += count($inject);
        continue;
    }

    /* من الآخرِ إلى الأولِ كي لا تنزاح المواضع */
    $new = $src;
    rsort($inject);
    foreach ($inject as $pos) {
        $new = substr($new, 0, $pos) . "\n" . '        <?= csrf_field() ?>' . substr($new, $pos);
    }

    $report[] = array('file' => $rel, 'n' => count($inject));
    $totInject += count($inject);
    $filesTouched++;

    if (!$apply) { printf("  ⟳ %-52s %d نموذجًا\n", $rel, count($inject)); continue; }

    $bak = $path . '.b3bak';
    if (!file_exists($bak)) { copy($path, $bak); }
    file_put_contents($path, $new);
    $out = array(); $rc = 0;
    exec('"' . PHP_BINARY . '" -l ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
    if ($rc !== 0) {
        copy($bak, $path);
        printf("  ✘ %-52s كُسر البناءُ فأُعيد الأصل\n", $rel);
        $broke++; $totInject -= count($inject); $filesTouched--;
        continue;
    }
    printf("  ✔ %-52s %d نموذجًا\n", $rel, count($inject));
}

echo "\n── الحصيلة ──\n";
printf("  نماذجُ POST في النطاق: %d · حُقن فيها: %d نموذجًا في %d ملفًّا\n", $totForms, $totInject, $filesTouched);
if ($skipNoConfig) { printf("  تُخطّي (بلا config): %d نموذجًا\n", $skipNoConfig); }
if ($broke) { printf("  كُسر وأُعيد: %d ملفًّا\n", $broke); }
if (!$apply) { echo "\n  شغّلها بـ--apply للتطبيق.\n"; }
