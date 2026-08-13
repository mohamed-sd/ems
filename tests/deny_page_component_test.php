<?php
/**
 * tests/deny_page_component_test.php — شاهدُ صفحةِ الحجبِ الموحَّدة
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0500
 *
 * **العيب**: ستُّ صفحاتِ حجبٍ مبنيةٌ بتسلسلِ نصوصٍ وأنماطٍ داخلَ `body` في
 * `includes/security.php` و`app/Services/Portal/SupplierPortalGuard.php` —
 * بلا **رمزٍ** يميّز سببَ الحجب، وبلا **المسارِ المطلوب**، وأربعٌ منها بلا
 * وسمِ `viewport` فتظهر مصغَّرةً على الهاتف. وهي أوّلُ ما يراه المستخدَمُ عند
 * الخطأ — وأسوأُ موضعٍ يُترك فيه بلا معلومةٍ يبلّغ بها.
 *
 * **الإصلاح**: مكوّنٌ واحدٌ `ems_deny_page()` يحمل الرمزَ والسببَ والمسارَ
 * ومخرجًا، ويعمل من عرضِ ٣٢٠px صعودًا.
 *
 * ── ويُقاس **حيًّا** لا بقراءةِ المصدر ─────────────────────────────────────
 * تُستفزُّ حالةُ حجبٍ حقيقيةٌ (POST بلا رمزِ CSRF إلى مسارٍ تحت الإنفاذ) ويُقرأ
 * ما يعود فعلًا. فوجودُ الدالةِ في الملفِّ ليس دليلًا على أنَّ أحدًا يناديها.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ INJ-0500 · صفحةُ الحجبِ: رمزٌ وسببٌ ومسارٌ ومقاسٌ يعمل على الهاتف');

/* ── ① لا صفحةَ حجبٍ مبنيةً بيدٍ خارجَ المكوّن ─────────────────────────────── */
$hand = array();
foreach (array('includes', 'app') as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/' . $dir,
        FilesystemIterator::SKIP_DOTS));
    foreach ($it as $p) {
        $path = $p->getPathname();
        /* ◆ مداخلُ القشرةِ المعتمدةُ تُصدر المستندَ **بحكمِها** (INJ-0236): صفحةُ
             الحجبِ ومدخلُ ما قبلَ الدخول. المقصودُ هنا صفحةُ حجبٍ **مبنيةٌ بيدٍ**
             خارجَ المكوّن، لا كلُّ مُصدِرِ مستند. */
        if (substr($path, -4) !== '.php'
            || strpos($path, 'deny_page.php') !== false
            || strpos($path, 'public_shell.php') !== false) { continue; }
        $src = (string) @file_get_contents($path);
        if (stripos($src, '<!DOCTYPE') !== false) {
            $hand[] = str_replace($ROOT . DIRECTORY_SEPARATOR, '', $path);
        }
    }
}
$ok(empty($hand), '**صفرُ صفحةِ حجبٍ مبنيةٍ بيدٍ** في `includes/` و`app/`',
    implode(' · ', $hand));

$calls = 0;
foreach (array('includes/security.php', 'app/Services/Portal/SupplierPortalGuard.php') as $f) {
    $calls += preg_match_all('~ems_deny_page\(~', (string) file_get_contents($ROOT . '/' . $f));
}
$ok($calls >= 6, "والمكوّنُ مُنادًى في {$calls} موضعًا — لا معرَّفًا بلا نداء");

/* ── ② والمكوّنُ يُلزم بالرمزِ والمسارِ والمقاس ────────────────────────────── */
$cmp = (string) file_get_contents($ROOT . '/includes/deny_page.php');
$ok(strpos($cmp, 'name="viewport"') !== false, 'ويحمل وسمَ `viewport`');
$ok(strpos($cmp, 'المسارُ المطلوب') !== false, 'ويعرض المسارَ المطلوب');
/* ◆ `\bwidth:` يطابق داخلَ `max-width:` — فالشرطةُ حدُّ كلمة. فأوّلُ صياغةٍ
     أعلنت «عرضٌ ثابت» على `max-width:560px` نفسِها. يُشترط ألّا يسبقها شَرطة. */
$ok(preg_match('~max-width:\s*\d+px~', $cmp) === 1
    && preg_match('~(?<![-a-z])width:\s*\d{3,}px~', $cmp) === 0,
    'وبعرضٍ أقصى لا عرضٍ ثابتٍ — فيتقلص إلى ٣٢٠px بلا تمريرٍ أفقيّ');
$ok(strpos($cmp, 'clamp(') !== false, 'وحشوتُه بـ`clamp` لا بخمسينَ بكسلًا ثابتة');

/* ── ③ القياسُ الحيُّ: حالةُ حجبٍ حقيقيةٌ تُستفزُّ ويُقرأ ما يعود ─────────────── */
$jar = sys_get_temp_dir() . '/denypg_' . getmypid() . '.txt';
$http = function ($url, $f = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    if ($f !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $f); }
    $b = (string) curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => $b, 'code' => $c);
};
$st = $conn->prepare("SELECT username FROM users WHERE role = '1' AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
$st->bind_param('i', $CO); $st->execute();
$x = $st->get_result()->fetch_row(); $st->close();
$user = $x ? (string) $x[0] : '';
$b = $http($BASE . '/login.php');
preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b['body'], $t);
$lb = $http($BASE . '/login.php', http_build_query(array(
    'username' => $user, 'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : '')));
$ok(mb_strpos($lb['body'], 'name="password"') === false, "دخل ({$user})");

/* مسارٌ تحت `CSRF_ENFORCE_PATHS` وPOST بلا رمزٍ ⇒ حجبٌ حقيقيّ */
$deny = $http($BASE . '/Contracts/contracts.php', http_build_query(array('probe' => 1)));
@unlink($jar);
$h = $deny['body'];
$ok($deny['code'] === 403, 'واستُفزَّت حالةُ حجبٍ حقيقيةٌ (POST بلا رمزِ CSRF ⇒ 403)',
    'الرمزُ ' . $deny['code']);
$ok(strpos($h, 'CSRF-403') !== false, '**والصفحةُ تحمل رمزًا مميِّزًا** `CSRF-403`');
$ok(strpos($h, 'name="viewport"') !== false, 'ووسمَ `viewport` — فتظهر بمقاسها على الهاتف');
$ok(strpos($h, 'dp-path') !== false && strpos($h, 'contracts.php') !== false,
    '**وتعرض المسارَ المطلوبَ** — فالمحجوبُ يعرف أيَّ رابطٍ ضغط');
$ok(strpos($h, 'class="dp"') !== false, 'وهي المكوّنُ الموحَّدُ نفسُه لا نصًّا خاصًّا');
$ok(strlen($h) < 6000, 'وخفيفةٌ (' . strlen($h) . ' بايت) — تُصيَّر بلا قشرةٍ ولا اتصالٍ بالقاعدة');

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
