<?php
/**
 * tests/injfrd66_xc05_supplier_account_http_proof.php
 *   XC-05 — «حسابٌ واحدٌ نشطٌ على الأقل يفتح مساحةَ إدارةِ الموردين»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الوثيقةُ تقول «صفر حساب» — والقياسُ يقول غيرَ ذلك.** فلا يُحسم الأمرُ
 *   بصفٍّ في `users`: المعيارُ **«يفتح مساحةَ الإدارة»** فعلٌ لا وجود.
 *   فيُقاس **بجلسةِ HTTP حقيقيةٍ**: دخولٌ بكلمةِ المرور، ثم فتحُ سطحٍ من
 *   أسطحِ الإدارة، ثم قراءةُ السايدبارِ المُصيَّرِ لهذا الحساب.
 *
 * ◆ **والمرساةُ بنيويةٌ لا عبارة**: يُرسى الفحصُ على `class`/`id` وترويسةِ
 *   `Location` — لا على نصٍّ عربيٍّ قد تطابقه رسالةُ الخطأِ نفسُها. وقاعدةُ
 *   المستودعِ المقيَّدة: «قياسُ الزرِّ بالعبارةِ أخضرُ كاذب».
 *
 * ◆ **وسالبُه شرطٌ**: حسابٌ بكلمةِ مرورٍ خاطئةٍ **يجب أن يُمنع** — وإلا كان
 *   «الدخولُ نجح» إعلانًا عن بابٍ مفتوحٍ لا عن حسابٍ صحيح.
 *
 * التشغيل: php tests/injfrd66_xc05_supplier_account_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$BASE = 'http://localhost/ems';
$PW   = '12345678';

$pass = 0; $fail = 0;
$check = static function (bool $ok, string $msg) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "   ✔ {$msg}\n"; } else { $fail++; echo "   ✘ {$msg}\n"; }
};

/** طلبٌ بلا اتّباعِ تحويل — فتُقرأ ترويسةُ `Location` كما هي */
$req = static function (string $url, string $jar, array $post = null): array {
    $ch = curl_init($url);
    $opt = array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 25,
    );
    if ($post !== null) { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = http_build_query($post); }
    curl_setopt_array($ch, $opt);
    $raw  = (string) curl_exec($ch);
    $hs   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
};

/* ── الحسابُ المرشَّحُ يُقرأ من القاعدةِ لا يُكتب في الشاهد ─────────────── */
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$res = @mysqli_query($conn, "SELECT id, username, name, status FROM users
                              WHERE CAST(role AS UNSIGNED) = 2 AND is_deleted = 0
                                AND status IN ('active','1') ORDER BY id LIMIT 1");
$acct = $res ? mysqli_fetch_assoc($res) : null;

echo "① الحسابُ المقيسُ لدورِ إدارةِ الموردين:\n";
if (!$acct) {
    $fail++;
    echo "   ✘ صفرُ حسابٍ نشطٍ للدورِ 2 — المعيارُ غيرُ مستوفٍ من أصلِه\n";
    printf("\n✘ XC-05  ناجح %d · راسب %d\n", $pass, $fail);
    exit(1);
}
$pass++;
printf("   ✔ «%s» (%s · #%d · %s)\n", $acct['name'], $acct['username'], $acct['id'], $acct['status']);

/* ── ② سالبٌ: كلمةُ مرورٍ خاطئةٍ تُمنع ───────────────────────────────── */
echo "\n② سالبٌ — كلمةُ مرورٍ خاطئةٍ يجب أن تُمنع:\n";
$jarBad = tempnam(sys_get_temp_dir(), 'xc05b');
list(, , $formBad) = $req($BASE . '/login.php', $jarBad);
/* نموذجٌ خامٌّ — الحقنُ الآليُّ لـfetch/XHR فقط، فبلا `csrf_token` يُردُّ 403 */
$tokBad = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $formBad, $tm) ? $tm[1] : '';
list($c, $h, $b) = $req($BASE . '/login.php', $jarBad,
    array('username' => $acct['username'], 'password' => 'كلمةٌ-خاطئةٌ-قطعًا', 'csrf_token' => $tokBad));
$loc = preg_match('~^Location:\s*(.+)$~mi', $h, $m) ? trim($m[1]) : '';
$landedIn = ($loc !== '' && mb_strpos($loc, 'login') === false);
$check(!$landedIn, 'الدخولُ بكلمةٍ خاطئةٍ لم يفتح جلسةً' . ($loc !== '' ? " (Location: {$loc})" : ''));
@unlink($jarBad);

/* ── ③ إيجابيٌّ: الدخولُ الصحيحُ يفتح جلسة ──────────────────────────── */
echo "\n③ إيجابيٌّ — الدخولُ الصحيحُ يفتح جلسةً:\n";
$jar = tempnam(sys_get_temp_dir(), 'xc05');
list(, , $form) = $req($BASE . '/login.php', $jar);
$tok = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $form, $tm2) ? $tm2[1] : '';
$check($tok !== '', 'رمزُ CSRF مقروءٌ من نموذجِ الدخول');
list($c, $h, $b) = $req($BASE . '/login.php', $jar,
    array('username' => $acct['username'], 'password' => $PW, 'csrf_token' => $tok));
$loc = preg_match('~^Location:\s*(.+)$~mi', $h, $m) ? trim($m[1]) : '';
$check($loc !== '' && mb_strpos($loc, 'login') === false,
    'الدخولُ حوّل إلى داخلِ النظام' . ($loc !== '' ? " (Location: {$loc})" : ' — لا تحويل'));

/* ── ④ إيجابيٌّ: مساحةُ الإدارةِ تُفتح ومرساتُها بنيوية ───────────────── */
echo "\n④ إيجابيٌّ — مساحةُ الإدارةِ تُفتح بمرساةٍ بنيوية:\n";
list($c2, $h2, $b2) = $req($BASE . '/Suppliers/suppliers.php', $jar);
$check($c2 === 200, "سجلُّ الموردينَ يُصيَّر برمزِ 200 (جاء {$c2})");
$check(mb_strpos($b2, 'ems-topbar') !== false || mb_strpos($b2, 'nav-group-name') !== false,
    'الصفحةُ تحمل قشرةَ النظامِ (`ems-topbar` أو `nav-group-name`) لا صفحةَ خطأ');

/* عددُ روابطِ السايدبارِ المُصيَّرِ لهذا الحساب — مرساةُ البنيةِ نفسُها */
$links = preg_match_all('~<a\b[^>]*href="[^"]*"[^>]*>~', $b2);
$check($links > 5, "السايدبارُ مُصيَّرٌ بـ{$links} رابطًا (>5)");

/* ── ⑤ سالبٌ: جلسةٌ بلا دخولٍ تُردُّ إلى الدخول ──────────────────────── */
echo "\n⑤ سالبٌ — بلا جلسةٍ يُردُّ السطحُ إلى الدخول:\n";
$jarNone = tempnam(sys_get_temp_dir(), 'xc05n');
list($c3, $h3, $b3) = $req($BASE . '/Suppliers/suppliers.php', $jarNone);
$loc3 = preg_match('~^Location:\s*(.+)$~mi', $h3, $m3) ? trim($m3[1]) : '';
$check(($c3 >= 300 && $c3 < 400 && mb_strpos($loc3, 'login') !== false) || $c3 === 403,
    "بلا جلسةٍ: رمزٌ {$c3}" . ($loc3 !== '' ? " ← {$loc3}" : ''));
@unlink($jarNone);
@unlink($jar);

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ XC-05' : '✘ XC-05', $pass, $fail);
exit($fail === 0 ? 0 : 1);
