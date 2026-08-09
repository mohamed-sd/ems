<?php
/**
 * tools/u13_screen_smoke.php — مسحُ شاشاتِ update0013 حيًّا عبر HTTP
 * ═══════════════════════════════════════════════════════════════════════════
 * `php -l` يثبت أن الملفَّ **يُقرأ** لا أن الصفحةَ **تعمل**. وهذه الأداةُ تسجّل
 * الدخولَ بحسابٍ حقيقيٍّ ثم تطلب كلَّ شاشةٍ من البيانِ وتحكم عليها:
 *   ✘ خطأٌ قاتلٌ · ✘ رمزٌ ≥500 · ✘ صفحةٌ فارغة · ✘ بلا رأسٍ موحَّد
 *   ◆ تحويلٌ حوكميٌّ (لا صلاحيةَ لهذا الدور) — سلوكٌ صحيحٌ لا عطب
 *   ✔ صُيّرت
 *
 * ◆ التغطيةُ باتحادِ الأدوار: دورٌ واحدٌ لا يرى الكل، فالحكمُ على **اتحادِ** ما
 *   صُيّر عبر الأدوارِ المُختبَرة — ولا يُدَّعى مسحٌ بدورٍ لم يدخل.
 *
 * التشغيل: php tools/u13_screen_smoke.php [--users=أ,ب] [--pass=..] [--base=..]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/u13_screens_manifest.php';
$MAN = u13_screens_manifest();

$base  = 'http://localhost/ems';
$users = array('محمد');            // السوبر/التشغيل — يرى الأوسع
$pass  = '12345678';
foreach ($argv as $a) {
    if (strpos($a, '--base=')  === 0) { $base  = rtrim(substr($a, 7), '/'); }
    if (strpos($a, '--users=') === 0) { $users = array_filter(array_map('trim', explode(',', substr($a, 8)))); }
    if (strpos($a, '--pass=')  === 0) { $pass  = substr($a, 7); }
}

/* ═══ وضعُ التصييرِ المحقونِ — الافتراضيُّ ═════════════════════════════════
   يُصيَّر كلُّ ملفٍّ بدورِه المالكِ في عمليةٍ مستقلة، ثم يُختبَر أن دورًا لا
   يملكها **يُرفض**. وهذا أقوى من تسجيلِ الدخول: يبلغ كلَّ دورٍ لا الأدوارَ
   التي لها كلمةُ مرور — وحاملو الأدوارِ الجديدةِ بلا كلماتِ مرورٍ عمدًا. */
if (!in_array('--http', $argv, true)) {
    $php  = PHP_BINARY;
    $co   = 4;
    foreach ($argv as $a) { if (strpos($a, '--company=') === 0) { $co = (int) substr($a, 10); } }
    $okN = 0; $govN = 0; $badN = array(); $denyOk = 0; $denyBad = array();
    echo "وضعُ التصييرِ المحقون — " . count($MAN) . " شاشة · الكيان $co\n\n";
    foreach ($MAN as $s) {
        $rel = $s['dir'] . '/' . $s['file'];
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($ROOT . '/tools/u13_render_one.php')
             . ' ' . escapeshellarg($rel) . ' ' . escapeshellarg((string) $s['role'])
             . ' 891 ' . $co . ' 2>&1';
        $out = (string) shell_exec($cmd);
        $verdict = '';
        foreach (explode("\n", $out) as $ln) { if (strpos($ln, 'VERDICT|') === 0) { $verdict = trim(substr($ln, 8)); break; } }
        list($kind, $why) = array_pad(explode('|', $verdict, 2), 2, '');
        if ($kind === 'ok')       { $okN++; }
        elseif ($kind === 'gov')  { $govN++; $badN[$rel] = 'الدورُ المالكُ ' . $s['role'] . ' لا يراها — منحةٌ ناقصة'; }
        else                      { $badN[$rel] = ($why !== '' ? $why : ('حكمٌ غيرُ مفهوم: ' . $verdict)); }

        /* الوجهُ الآخر: دورٌ لا يملكها يجب أن **يُرفض** — والحارسُ يُثبت بالمنعِ
           لا بالسماحِ وحدَه. الدورُ 11 «مشغل اسطول» تشغيليٌّ محضٌ لا شأنَ له. */
        $cmd2 = escapeshellarg($php) . ' ' . escapeshellarg($ROOT . '/tools/u13_render_one.php')
              . ' ' . escapeshellarg($rel) . ' 11 891 ' . $co . ' 2>&1';
        $out2 = (string) shell_exec($cmd2);
        $v2 = '';
        foreach (explode("\n", $out2) as $ln) { if (strpos($ln, 'VERDICT|') === 0) { $v2 = trim(substr($ln, 8)); break; } }
        if (strpos($v2, 'gov|') === 0) { $denyOk++; } else { $denyBad[$rel] = $v2; }
    }
    echo str_repeat('═', 74) . "\n";
    printf("  ✔ صُيّرت لدورِها المالك: %d/%d\n", $okN, count($MAN));
    printf("  ✔ رُفضت لدورٍ لا يملكها:  %d/%d\n", $denyOk, count($MAN));
    if ($badN)   { echo "\n  ✘ لم تُصيَّر:\n"; foreach ($badN as $f => $w) { printf("     %-42s %s\n", $f, mb_substr($w, 0, 80)); } }
    if ($denyBad) { echo "\n  ✘ لم تُرفض لغيرِ مالكها (خرقُ حارس):\n"; foreach ($denyBad as $f => $w) { printf("     %-42s %s\n", $f, mb_substr($w, 0, 60)); } }
    echo str_repeat('═', 74) . "\n";
    exit(($badN || $denyBad) ? 1 : 0);
}

$jar = sys_get_temp_dir() . '/ems_u13_' . getmypid() . '.cookie';
function sm_get($url, $jar, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 30,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return array((string) $body, (int) $info['http_code'], (string) $info['url']);
}

$fatalRe = '~(Fatal error|Parse error|Uncaught \w*(Error|Exception)|Allowed memory size|Warning:|Notice:|Deprecated:)~i';
$rendered = array(); $gov = array(); $bad = array();

foreach ($users as $user) {
    @unlink($jar);
    list($lp) = sm_get($base . '/login.php', $jar);
    $csrf = '';
    if (preg_match('~name="csrf_token"\s+value="([^"]+)"~', $lp, $m)) { $csrf = $m[1]; }
    $post = array('username' => $user, 'password' => $pass);
    if ($csrf !== '') { $post['csrf_token'] = $csrf; }
    list($lb, , $lu) = sm_get($base . '/login.php', $jar, $post);
    if (strpos($lu, 'login.php') !== false && strpos($lb, 'insidebar') === false && strpos($lb, 'dashboard') === false) {
        echo "تعذّر الدخولُ بـ«{$user}» — يُتخطّى ولا يُدَّعى مسحٌ بلا جلسة\n";
        continue;
    }
    echo "دخل «{$user}» — يمسح " . count($MAN) . " شاشة\n";

    foreach ($MAN as $s) {
        $rel = $s['dir'] . '/' . $s['file'];
        if (isset($rendered[$rel])) { continue; }
        list($body, $code, $url) = sm_get($base . '/' . $rel, $jar);
        /* ◆ گوتشا: الحكمُ على «صُيّرت» بوجودِ اسمِ صفحةٍ بعينِها في العنوانِ
             النهائيِّ **كاذب** — فوجهةُ التحويلِ الحوكميِّ في هذه المنصةِ
             `main/role_board.php` لا `dashboard.php`، فمرَّ الارتدادُ خضراءَ.
             والحكمُ الصادقُ: أن ينتهيَ الطلبُ **عند المسارِ المطلوبِ نفسِه**. */
        $isGov  = (strpos($url, $rel) === false);
        $fatal  = preg_match($fatalRe, $body) === 1;
        $hasHdr = (strpos($body, 'ems-unified-page-shell') !== false);
        if ($fatal)              { $bad[$rel] = 'خطأٌ في التصيير: ' . trim(preg_replace('~\s+~', ' ', mb_substr(strip_tags($body), 0, 160))); }
        elseif ($code >= 500)    { $bad[$rel] = "رمزُ حالة $code"; }
        elseif ($isGov)          { $gov[$rel] = 1; }
        elseif (trim($body) === '') { $bad[$rel] = 'صفحةٌ فارغة'; }
        elseif (!$hasHdr)        { $bad[$rel] = 'بلا الغلافِ الموحَّد'; }
        else                     { $rendered[$rel] = 1; }
    }
}
@unlink($jar);

/* ═══ التقرير ═════════════════════════════════════════════════════════════ */
$total = count($MAN);
echo "\n" . str_repeat('═', 74) . "\n";
printf("  مسحُ شاشاتِ update0013 — %d شاشة\n", $total);
echo str_repeat('═', 74) . "\n";
printf("  ✔ صُيّرت: %d\n", count($rendered));
printf("  ◆ تحويلٌ حوكميٌّ (لا صلاحيةَ للدورِ المُختبَر): %d\n", count(array_diff_key($gov, $rendered)));
printf("  ✘ معطوبة: %d\n", count($bad));
foreach ($bad as $f => $why) { printf("     ✘ %-40s %s\n", $f, mb_substr($why, 0, 90)); }
if ($gov) {
    $only = array_diff_key($gov, $rendered);
    if ($only) {
        echo "\n  الشاشاتُ التي لم يرها الدورُ المُختبَر (تحتاج دورَها لتُمسَح):\n";
        foreach (array_keys($only) as $f) { echo "     ◆ $f\n"; }
    }
}
echo str_repeat('═', 74) . "\n";
exit(count($bad) > 0 ? 1 : 0);
