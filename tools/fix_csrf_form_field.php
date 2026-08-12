<?php
/**
 * tools/fix_csrf_form_field.php — حقلُ الحماية المركزيُّ في كلِّ نموذجٍ عاديّ
 * ═══════════════════════════════════════════════════════════════════════════
 * **العطبُ المقيس** (وهو أخطرُ ما كُشف في مسحِ 2026-08-12):
 *
 * `ems_enforce_csrf_protection()` (`includes/security.php:404`) يفحص **كلَّ**
 * طلبِ POST/PUT/PATCH/DELETE — لا الطلباتِ غيرَ المتزامنةِ وحدَها — ويقرأ الرمزَ
 * من `$_POST['csrf_token']` **أو** ترويسةِ `X-CSRF-Token`. فإن غاب الاثنان
 * وكان المسارُ في `CSRF_ENFORCE_PATHS` **حجبَ الطلبَ بـ403**.
 *
 * و`assets/js/csrf.js` — المعلَنُ أنه «مركزيٌّ محقونٌ آليًّا» — يضع الترويسةَ على
 * **`fetch`/`XHR` وحدَهما** بنصِّ ترويستِه. و**إرسالُ نموذجِ HTML عاديٍّ ليس
 * أيًّا منهما**، فلا ترويسةَ ولا حقلَ ⇒ كلُّ ضغطةِ «حفظ» في نموذجٍ عاديٍّ تحت
 * المساراتِ المُنفَذة تُردُّ **403 بصفحةِ خطأ**.
 *
 * والمقيسُ حيًّا: `CSRF_ENFORCE_PATHS = /Finance/,/FinRequests/,/Contracts/,`
 * `/Approvals/,/Tickets/` · و**46 شاشةً بـ97 نموذجًا عاديًّا** بلا الحقل ·
 * و**2670 مخالفةَ `csrf_violation`** في `logs/security.log` منها **326** على
 * `Contracts/claims.php` وحدَها. ولم يُشعَر به لأن التشغيلَ التفاعليَّ متوقفٌ
 * في طورِ التصحيح — والفواحصُ التي مرّت كانت تُرسل الترويسةَ أو الرمزَ يدويًّا.
 *
 * **ما تفعله هذه الأداة**: تُدرج `<?php echo csrf_field(); ?>`
 * (`includes/security.php:244`) بعد وسمِ الفتحِ لكلِّ نموذجٍ `method="post"`
 * **لا يحمل الرمزَ المركزيَّ في جسمِه** — بلا مسِّ حقلِ الشاشةِ الخاصِّ
 * (`clm_csrf` وأمثاله) فهو حارسٌ ثانٍ يبقى.
 *
 * التشغيل: php tools/fix_csrf_form_field.php            (جسٌّ يُعلن ولا يمسّ)
 *          php tools/fix_csrf_form_field.php --apply    (تنفيذ)
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');

$APPLY = in_array('--apply', $argv, true);
$root  = dirname(__DIR__);

/* مساراتُ الإنفاذِ تُقرأ من البيئةِ لا تُكتب في الأداة */
require_once $root . '/includes/env.php';
$csv = (string) ems_env('CSRF_ENFORCE_PATHS', '');
$dirs = array();
foreach (explode(',', $csv) as $p) {
    $p = trim($p, " \t/");
    if ($p !== '') { $dirs[] = $p; }
}
if (empty($dirs)) { fwrite(STDERR, "لا CSRF_ENFORCE_PATHS في البيئة — لا عمل\n"); exit(1); }

echo ($APPLY ? "══ تنفيذ" : "══ جسٌّ بلا مسّ (أضف --apply)") . "\n";
echo '  مساراتُ الإنفاذ: ' . implode(' · ', $dirs) . "\n\n";

$FIELD = '<?php echo csrf_field(); ?>';
$totalForms = 0; $totalFiles = 0; $skipped = array();

foreach ($dirs as $d) {
    foreach (glob($root . '/' . $d . '/*.php') as $f) {
        $src = file_get_contents($f);
        $rel = $d . '/' . basename($f);

        // وسومُ فتحِ النماذجِ التي تُرسل POST (وسمٌ قد يمتدُّ أسطرًا)
        if (!preg_match_all('~<form\b[^>]*?>~is', $src, $m, PREG_OFFSET_CAPTURE)) { continue; }

        $inserts = array();
        foreach ($m[0] as $hit) {
            $tag = $hit[0];
            $at  = $hit[1];
            if (!preg_match('~method\s*=\s*[\'"]?post~i', $tag)) { continue; }

            // جسمُ النموذجِ حتى </form> — للتحقُّقِ من غيابِ الرمزِ فيه
            $close = stripos($src, '</form>', $at);
            $body  = ($close === false) ? substr($src, $at) : substr($src, $at, $close - $at);
            if (preg_match('~name\s*=\s*[\'"]csrf_token[\'"]~', $body)) { continue; }
            if (strpos($body, 'csrf_field()') !== false) { continue; }

            $inserts[] = $at + strlen($tag);
        }
        if (empty($inserts)) { continue; }

        // إدراجٌ من الآخرِ إلى الأول كي لا تنزاح المواضع
        $out = $src;
        foreach (array_reverse($inserts) as $pos) {
            $out = substr($out, 0, $pos) . "\n        " . $FIELD . substr($out, $pos);
        }

        // حارسٌ: لا يُكتب ملفٌّ لا يُصرَّف
        $tmp = sys_get_temp_dir() . '/ems_csrf_probe_' . getmypid() . '.php';
        file_put_contents($tmp, $out);
        $lint = array(); $code = 1;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $lint, $code);
        @unlink($tmp);
        if ($code !== 0) {
            $skipped[] = $rel . ' (لا يُصرَّف بعد الإدراج — يُترك ويُعلَن)';
            continue;
        }

        printf("  %-48s %d نموذجًا\n", $rel, count($inserts));
        $totalForms += count($inserts);
        $totalFiles++;
        if ($APPLY) { file_put_contents($f, $out); }
    }
}

echo "\n  المجموع: {$totalFiles} شاشةً · {$totalForms} نموذجًا\n";
foreach ($skipped as $s) { echo "  ⚠ {$s}\n"; }
echo $APPLY ? "\nاكتمل — افحص التصريفَ ثم جُسَّ بـHTTP.\n" : "\n○ جسٌّ فقط.\n";
