<?php
/**
 * tests/injfrd66_sup28_board_test.php — شاهدُ SUP-28: لوحةُ إدارةِ الموردين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الوثيقةُ تعدُّها «القدرةَ الغائبةَ الوحيدةَ في الحوكمة» — والقياسُ يقول
 *   إنها موجودةٌ وتعمل**: لوحةُ الدورِ 2 مُعدَّةٌ في `roleBoardGenericConfig(2)`
 *   باسمِ «لوحة ادارة الموردين» نفسِه، وتُصيَّر داخلَ `main/dashboard.php`
 *   بقرارِ المالكِ 2026-08-21 «بدل شاشةٍ ثانية».
 *
 * ◆ **إيجابيٌّ ①**: اللوحةُ قائمةٌ بإعدادِها ووجهةُ هبوطِ الدورِ إليها.
 * ◆ **إيجابيٌّ ②**: **بجلسةِ HTTP حيّة** — تُصيَّر ببطاقاتٍ فعلًا لا بالإعدادِ وحدَه.
 * ◆ **إيجابيٌّ ③**: «صفرُ حقلِ إدخالٍ فيها» — والمقياسُ على المُصيَّرِ لا المصدر.
 * ◆ **سالبٌ ④**: كلُّ بطاقةٍ لها **وجهةُ نقر** — «ورقمٌ لا يُتعمَّق فيه لا
 *   يُقرَّر عليه». وبطاقةٌ بلا وجهةٍ رقمٌ معلَّق.
 * ◆ **سالبٌ ⑤**: السطحُ الثاني `supplier_board.php` **معلومُ الخَلَف** ولم
 *   يبقَ يدّعي أنه صفحةُ الهبوط — وما ينفرد به **مقيَّدٌ نصًّا** فلا يُفقد صامتًا.
 *
 * التشغيل: php tests/injfrd66_sup28_board_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$BASE = 'http://localhost/ems';
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/role_board.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$pass = 0; $fail = 0;
$check = static function (bool $ok, string $msg) use (&$pass, &$fail): void {
    if ($ok) { $pass++; echo "   ✔ {$msg}\n"; } else { $fail++; echo "   ✘ {$msg}\n"; }
};
$req = static function (string $url, string $jar, array $post = null): array {
    $ch = curl_init($url);
    $o = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
               CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 30);
    if ($post !== null) { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = http_build_query($post); }
    curl_setopt_array($ch, $o);
    $raw = (string) curl_exec($ch);
    $hs  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $c   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($c, substr($raw, 0, $hs), substr($raw, $hs));
};

echo "① إيجابيٌّ — اللوحةُ قائمةٌ بإعدادِها:\n";
$cfg = roleBoardGenericConfig(2);
$check(is_array($cfg) && !empty($cfg['cards']),
    sprintf('«%s» · %d بطاقة', $cfg['title'] ?? '—', is_array($cfg) ? count($cfg['cards'] ?? array()) : 0));
$check(roleBoardRoute(2) === 'main/dashboard.php',
    'وجهةُ هبوطِ الدورِ 2 = main/dashboard.php (قرارُ المالك 2026-08-21)');

echo "\n② إيجابيٌّ — تُصيَّر بجلسةِ HTTP حيّة:\n";
$acct = null;
$r = @mysqli_query($conn, "SELECT username FROM users WHERE CAST(role AS UNSIGNED)=2 AND is_deleted=0
                            AND status IN ('active','1') ORDER BY id LIMIT 1");
if ($r) { $acct = mysqli_fetch_assoc($r); }
if (!$acct) { $fail++; echo "   ✘ لا حسابَ للدورِ 2 — تعذّر القياسُ الحيّ\n"; }
else {
    $jar = tempnam(sys_get_temp_dir(), 'sup28');
    list(, , $form) = $req($BASE . '/login.php', $jar);
    $tok = preg_match('~name="csrf_token"\s+value="([^"]+)"~', $form, $m) ? $m[1] : '';
    $req($BASE . '/login.php', $jar, array('username' => $acct['username'], 'password' => '12345678', 'csrf_token' => $tok));
    list($c, , $body) = $req($BASE . '/main/dashboard.php', $jar);
    $check($c === 200, "لوحةُ الهبوطِ تُصيَّر برمزِ 200 (جاء {$c})");
    $cards = preg_match_all('~ems-statcard|statcard|stat-card~u', $body);
    $check($cards > 0, "بطاقاتُ مؤشراتٍ مُصيَّرة: {$cards}");

    echo "\n③ إيجابيٌّ — صفرُ حقلِ إدخالٍ في المُصيَّر:\n";
    /* ◆ **القشرةُ ليست اللوحة**: `navFilterInput` حقلُ بحثِ السايدبارِ حاضرٌ
         في كلِّ صفحةٍ من صفحاتِ النظام، وعدُّه «حقلَ إدخالٍ في اللوحة»
         يُرسِّب كلَّ لوحةٍ في المنصّةِ بعطبٍ ليس فيها. فيُستبعد صراحةً
         بمعرِّفِه، ويُستبعد الخفيُّ — والمقياسُ على **جسمِ اللوحة**. */
    $bodyOnly = $body;
    if (($p0 = mb_strpos($bodyOnly, 'ems-statcard')) !== false) { $bodyOnly = mb_substr($bodyOnly, $p0); }
    $SHELL = '~<input\b[^>]*id="navFilterInput"[^>]*>~iu';
    $bodyOnly = preg_replace($SHELL, '', $bodyOnly);
    $inputs = preg_match_all('~<(input|select|textarea)\b(?![^>]*type="hidden")[^>]*>~iu', $bodyOnly, $IM);
    $check($inputs === 0, "حقولُ إدخالٍ في جسمِ اللوحة: {$inputs} (بعدَ استبعادِ بحثِ القشرة)");
    foreach ((array) ($IM[0] ?? array()) as $one) { echo '      · ' . mb_substr(preg_replace('~\s+~u', ' ', $one), 0, 110) . "
"; }
    @unlink($jar);
}

echo "\n④ سالبٌ — كلُّ بطاقةٍ لها وجهةُ نقر:\n";
$noDest = array();
foreach (($cfg['cards'] ?? array()) as $cd) {
    $dest = $cd[4] ?? '';
    if (!is_string($dest) || trim($dest) === '') { $noDest[] = (string) $cd[0]; }
}
$check(empty($noDest), sprintf('%d بطاقةً · صفرُ بطاقةٍ بلا وجهة', count($cfg['cards'] ?? array())));
foreach ($noDest as $x) { echo "      ✘ «{$x}» بلا وجهة\n"; }

echo "\n⑤ سالبٌ — السطحُ الثاني معلومُ الخَلَفِ وما ينفرد به مقيَّد:\n";
$r = @mysqli_query($conn, "SELECT IFNULL(retirement_status,'') rs, IFNULL(view_of,'') vo,
                                  IFNULL(placement_basis,'') pb
                             FROM nav_canonical WHERE LOWER(route)='suppliers/supplier_board.php'");
$x = $r ? mysqli_fetch_assoc($r) : null;
$check($x !== null && $x['rs'] === 'RETIRE_AFTER_PROOF', 'حالتُه RETIRE_AFTER_PROOF لا «صفحةُ هبوط»');
$check($x !== null && $x['vo'] === 'main/dashboard.php', 'وخَلَفُه مسمًّى: main/dashboard.php');
$check($x !== null && mb_strpos($x['pb'], 'ينفرد') !== false && mb_strpos($x['pb'], 'المخالفات') !== false,
    'وما ينفرد به مقيَّدٌ نصًّا (المخالفات · التقييمات · الطاقة)');

printf("\n%s  ناجح %d · راسب %d\n", $fail === 0 ? '✔ SUP-28' : '✘ SUP-28', $pass, $fail);
exit($fail === 0 ? 0 : 1);
