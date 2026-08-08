<?php
/**
 * tools/u12_screen_smoke.php — مسحُ الشاشاتِ الحيةِ عبر HTTP بجلسةٍ حقيقية
 * ═══════════════════════════════════════════════════════════════════════════
 * لماذا: فحصُ الصياغةِ (php -l) يثبت أنّ الملفَّ يُقرأ، لا أنّ الصفحةَ تعمل.
 * وبعد ترحيلٍ يمسُّ 354 شاشةً لا يكفي أن تُقرأ — يجب أن تُصيَّر. فهذه الأداةُ
 * تسجّل الدخولَ بحسابٍ حقيقيٍّ ثم تطلب كلَّ شاشةٍ حيةٍ وتحكم عليها:
 *   ✘ خطأٌ قاتلٌ أو استثناءٌ غيرُ ملتقَط · ✘ رمزُ حالةٍ ≥ 500 · ✘ صفحةٌ فارغة
 *   ◆ تحويلٌ حوكميٌّ (صلاحية) — سلوكٌ صحيحٌ لا عطب
 *   ✔ صُيّرت وفيها الرأسُ الموحَّدُ وسطرُ السياق
 *
 * التشغيل: php tools/u12_screen_smoke.php [--user=اسم] [--pass=كلمة] [--limit=N]
 *          [--base=http://localhost/ems] [--users=أ,ب,ج]
 *
 * ملاحظةُ تغطية: دورٌ واحدٌ لا يرى كلَّ الشاشات — ما لم يره يُحسب «تحويلًا
 * حوكميًّا» لا «صُيّرت». فالتغطيةُ الحقيقيةُ تُجمع بعدةِ أدوارٍ عبر --users،
 * والحكمُ النهائيُّ على اتحادِ ما صُيّر.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);

$ROOT = dirname(__DIR__);
$base = 'http://localhost/ems';
$users = array('محمد');
$pass = '12345678';
$limit = 0;
foreach ($argv as $a) {
    if (strpos($a, '--base=') === 0)  { $base  = rtrim(substr($a, 7), '/'); }
    if (strpos($a, '--user=') === 0)  { $users = array(substr($a, 7)); }
    if (strpos($a, '--users=') === 0) { $users = array_filter(array_map('trim', explode(',', substr($a, 8)))); }
    if (strpos($a, '--pass=') === 0)  { $pass  = substr($a, 7); }
    if (strpos($a, '--limit=') === 0) { $limit = (int) substr($a, 8); }
}

$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
    'Fleet','Governance','Maintenance','movement','Operations','Opportunities','Oprators','Portal',
    'Procurement','Projects','Reports','Risk','Settings','Suppliers','Tickets','Timesheet',
    'Transport','Workforce','main','admin','company','ActivityLogs','Clients','emsreports');

$screens = array();
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) @file_get_contents($f);
        if (strpos($src, 'insidebar') === false) { continue; }
        $screens[] = str_replace('\\', '/', substr($f, strlen($ROOT) + 1));
    }
}
sort($screens);

$jar = sys_get_temp_dir() . '/ems_smoke_' . getmypid() . '.cookie';
@unlink($jar);

function smoke_get($url, $jar, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HEADER         => false,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    return array((string) $body, (int) $info['http_code'], (string) $info['url'], $err);
}

$fatalRe = '~(Fatal error|Parse error|Uncaught \w*(Error|Exception)|call to a member function|Allowed memory size)~i';
$stat = array('ok' => 0, 'gov' => 0, 'login' => 0, 'fatal' => 0, 'http5' => 0, 'empty' => 0, 'nohead' => 0);
$bad = array();
$bounced = array();
$renderedUnion = array();   // اتحادُ ما صُيّر فعلًا عبر كلِّ الأدوار
$n = 0;

foreach ($users as $user) {
/* ── تسجيلُ الدخول ─────────────────────────────────────────────────────── */
@unlink($jar);
list($lp, , , ) = smoke_get($base . '/login.php', $jar);
$csrf = '';
if (preg_match('~name="csrf_token"\s+value="([^"]+)"~', $lp, $m)) { $csrf = $m[1]; }
$post = array('username' => $user, 'password' => $pass);
if ($csrf !== '') { $post['csrf_token'] = $csrf; }
list($lb, $lc, $lu, ) = smoke_get($base . '/login.php', $jar, $post);
$loggedIn = (strpos($lu, 'login.php') === false) || (strpos($lb, 'insidebar') !== false);
if (!$loggedIn && strpos($lb, 'dashboard') === false) {
    echo "تعذّر تسجيلُ الدخول بـ«{$user}» — يُتخطّى هذا الدور (لا يُدَّعى مسحٌ بلا جلسة)\n";
    continue;
}
echo "الجلسةُ مفتوحةٌ بـ«{$user}» ⇐ {$lu}\n";

/* ── المسح ─────────────────────────────────────────────────────────────── */
$i = 0;
foreach ($screens as $rel) {
    if ($limit > 0 && $i >= $limit) { break; }
    $i++; $n++;
    list($body, $code, $finalUrl, $err) = smoke_get($base . '/' . $rel, $jar);
    $len = strlen($body);

    if ($err !== '') { $stat['fatal']++; $bad[] = array($rel, 'شبكة: ' . $err); continue; }
    if ($code >= 500) { $stat['http5']++; $bad[] = array($rel, 'HTTP ' . $code); continue; }
    if (preg_match($fatalRe, $body, $mm)) {
        $stat['fatal']++;
        $snip = '';
        if (preg_match('~(Fatal error|Parse error|Uncaught)[^<\n]{0,160}~i', $body, $ms)) { $snip = trim($ms[0]); }
        $bad[] = array($rel, $snip !== '' ? $snip : $mm[0]);
        continue;
    }
    if ($len < 400) { $stat['empty']++; $bad[] = array($rel, 'جسمٌ أقصرُ من 400 بايت (' . $len . ')'); continue; }

    /* ارتدادٌ إلى شاشةِ الدخولِ ومستخدمُنا داخلٌ سلفًا: ليس عطبَ تصييرٍ لكنه
       عيبُ حوكمةٍ حقيقيّ — منعُ صلاحيةٍ يجب أن يعيدَ للوحةِ برسالةٍ محكومة،
       لا أن يوهمَ بانتهاءِ الجلسة (UI-13). يُعدُّ ويُسمَّى ولا يُخفى. */
    if (strpos($finalUrl, '/login.php') !== false) {
        $stat['login']++;
        $bounced[] = $rel;
        continue;
    }

    /* تحويلٌ حوكميٌّ: انتهى في لوحةٍ ومعه رمزُ حكم — سلوكٌ صحيح */
    $isGov = (strpos($finalUrl, basename($rel)) === false)
          && (strpos($body, 'emsGovFlash') !== false || strpos($body, 'GOV-') !== false
              || strpos($body, 'dashboard') !== false);
    if ($isGov) { $stat['gov']++; continue; }

    if (strpos($body, 'main_head') === false) {
        $stat['nohead']++;
        $bad[] = array($rel, 'صُيّرت بلا رأسٍ موحَّد');
        continue;
    }
    $stat['ok']++;
    $renderedUnion[$rel] = true;
}
}   /* نهايةُ حلقةِ الأدوار */
@unlink($jar);

$union = count($renderedUnion);
$den = count($screens);
echo str_repeat('─', 66), "\n";
echo 'أدوارٌ مُسحت: ' . count($users) . "\n";
echo "طلباتٌ نُفّذت: {$n}\n";
echo "✔ صُيّرت بالرأسِ الموحَّد: {$stat['ok']}\n";
echo "◎ اتحادُ الشاشاتِ التي صُيّرت فعلًا عبر الأدوار: {$union}/{$den}"
   . ($den > 0 ? ' = ' . round($union / $den * 100, 1) . '٪' : '') . "\n";
echo "◆ تحويلٌ حوكميٌّ للوحةِ (صلاحية) — سلوكٌ صحيح: {$stat['gov']}\n";
echo "◐ ارتدادٌ لشاشةِ الدخولِ ومستخدمُنا داخل — عيبُ حوكمةٍ معلَن: {$stat['login']}\n";
echo "✘ خطأٌ قاتل: {$stat['fatal']}\n";
echo "✘ HTTP ≥ 500: {$stat['http5']}\n";
echo "✘ جسمٌ فارغ: {$stat['empty']}\n";
echo "✘ بلا رأسٍ موحَّد: {$stat['nohead']}\n";
if ($bad) {
    echo "\nالتفصيل:\n";
    foreach (array_slice($bad, 0, 40) as $b) { echo '  · ' . $b[0] . ' — ' . $b[1] . "\n"; }
    if (count($bad) > 40) { echo '  … و' . (count($bad) - 40) . " أخرى\n"; }
}
if ($bounced) {
    echo "\nالمرتدَّةُ لشاشةِ الدخول (تُعالَج بتحويلها للوحةِ برسالةٍ محكومة):\n";
    foreach (array_slice($bounced, 0, 40) as $b) { echo '  ◐ ' . $b . "\n"; }
    if (count($bounced) > 40) { echo '  … و' . (count($bounced) - 40) . " أخرى\n"; }
}
$hard = $stat['fatal'] + $stat['http5'] + $stat['empty'] + $stat['nohead'];
echo "\nالحكم: " . ($hard === 0 ? '🟢 صفرُ عطبٍ في المسحِ الحي' : '🔴 ' . $hard . ' شاشةً تحتاج علاجًا') . "\n";
exit($hard === 0 ? 0 : 1);
