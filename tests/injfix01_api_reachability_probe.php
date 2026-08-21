<?php
/**
 * tests/injfix01_api_reachability_probe.php
 *   فحصُ بلوغِ الواجهةِ البرمجية — INJ-FIX-01 · الموجة أ ④ · بروتوكولُ القياس ③
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **نصُّ المالك**: «اختبر الوصولَ الحقيقيَّ لا الوصف … ولا ترفع ولا تخفض
 *   Severity قبل القياس.» فهذا فاحصٌ **يقيس ولا يحكم مسبقًا**، ومخرَجُه
 *   جوابٌ قاطعٌ يُصنَّف عليه.
 *
 * ◆ **وثلاثةُ أسئلةٍ لا سؤالٌ واحد** — والخلطُ بينها هو ما جعل هذه الفجوةَ
 *   تُوصَف ولا تُقاس:
 *     ① **أتردُّ الواجهةُ أصلًا؟** (حياةُ السطح)
 *     ② **مَن يُسمح له بالوصولِ إليها على مستوى الخادم؟** (طبقةُ التخويلِ في
 *        Apache — لا طبقةُ التطبيق)
 *     ③ **أتُسلِّم بيانًا بلا رمز؟** (انكشافُ بيانٍ غيرِ مُصادَق)
 *   والجوابُ عن ③ لا يُغني عن ②: سطحٌ مُصادَقٌ مفتوحٌ للعالمِ يبقى سطحَ هجومٍ
 *   كاملًا وإن لم يُسلِّم صفًّا واحدًا.
 *
 * ◆ **وحدُّ ما يُقاس من هنا**: هذه الآلةُ خلفَ NAT بعنوانٍ خاصّ. فبلوغُ
 *   الإنترنتِ سؤالُ محيطِ شبكةٍ **لا يُقاس من داخلِ المضيف** — ويُسجَّل
 *   `BLOCKED_EXTERNAL_INPUT` ولا يُخمَّن جوابُه في الاتجاهَين.
 *
 * التشغيل: php tests/injfix01_api_reachability_probe.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');

$ROOT = dirname(__DIR__);
$pass = 0; $fail = 0; $facts = array();
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }
function note($l, $v) { echo "  ◆ {$l}: {$v}\n"; }

echo "════ بلوغُ الواجهةِ البرمجية — بروتوكولُ القياس ③ ════\n";

/* ══════════ ① حياةُ السطحِ — نداءٌ حقيقيٌّ لا افتراض ═══════════════════════ */
echo "\n── ① أتردُّ الواجهةُ؟ ──\n";
function http_probe($url, $headers = array())
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false,
    ));
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return array('code' => $code, 'raw' => (string) $raw, 'err' => $err);
}

$base = 'http://127.0.0.1/ems/api';
$r = http_probe($base . '/me');
ok($r['code'] > 0, 'الواجهةُ حيةٌ وتردّ', $pass, $fail, "HTTP {$r['code']}" . ($r['err'] ? " · {$r['err']}" : ''));
$facts['live_http'] = $r['code'];

/* ══════════ ② طبقةُ التخويلِ في الخادم — القياسُ من التكوينِ الحيّ ═════════ */
echo "\n── ② مَن يُسمح له على مستوى الخادم؟ ──\n";
$apacheConf = glob('C:/wamp64/bin/apache/apache*/conf/httpd.conf');
$vhostConf  = glob('C:/wamp64/bin/apache/apache*/conf/extra/httpd-vhosts.conf');
$rootHt     = $ROOT . '/.htaccess';
$apiHt      = $ROOT . '/api/.htaccess';

$confSrc  = $apacheConf ? (string) @file_get_contents($apacheConf[0]) : '';
$vhostSrc = $vhostConf  ? (string) @file_get_contents($vhostConf[0])  : '';
$apiSrc   = (string) @file_get_contents($apiHt);

$rootRequireLocal = (bool) preg_match('/^\s*Require\s+local\s*$/mi', $confSrc)
                 || (bool) preg_match('/^\s*Require\s+local\s*$/mi', $vhostSrc);
$allowOverride    = (bool) preg_match('/^\s*AllowOverride\s+all\s*$/mi', $confSrc);
$apiGrantAll      = (bool) preg_match('/^\s*Require\s+all\s+granted\s*$/mi', $apiSrc);

note('جذرُ التطبيق', $rootRequireLocal ? '`Require local` — يمنع كلَّ عميلٍ غيرِ محلّيّ' : 'لا `Require local`');
note('AllowOverride', $allowOverride ? 'all — فـ.htaccess **يستطيع** نقضَ الجذر' : 'مقيَّد');
note('api/.htaccess', $apiGrantAll ? '`Require all granted` — **ينقض الجذرَ لمجلدِ الواجهةِ وحدَه**' : 'لا منحَ عامّ');

/* ◆ الحكم: البلوغُ الشبكيُّ **محجوبٌ بحجبٍ مثبَت** فقط إن كان الجذرُ يمنع
     **ولم يُنقَض**. والنقضُ هنا مقيسٌ لا مُستنتَج. */
$networkBlocked = $rootRequireLocal && !($allowOverride && $apiGrantAll);
ok($networkBlocked,
   'الواجهةُ محجوبةٌ شبكيًّا بحجبٍ مثبَت (طبقةُ الخادم)', $pass, $fail,
   $networkBlocked ? 'الجذرُ يمنع ولا ناقضَ له'
                   : 'الجذرُ يمنع بـ`Require local` **و`api/.htaccess` ينقضه بـ`Require all granted`**');
$facts['network_blocked'] = $networkBlocked;

$corsStar = (bool) preg_match("/Access-Control-Allow-Origin:\s*\*/", (string) @file_get_contents($ROOT . '/api/index.php'));
note('ترويسةُ الأصل', $corsStar ? '`*` — مفتوحةٌ للجميع (GAP-33)' : 'مضيَّقة');
$facts['cors_star'] = $corsStar;

/* ══════════ ③ أتُسلِّم بيانًا بلا رمزٍ أو برمزٍ باطل؟ ═══════════════════════ */
echo "\n── ③ انكشافُ بيانٍ غيرِ مُصادَق ──\n";
$cases = array(
    'بلا رمزٍ إطلاقًا'   => array(),
    'رمزٌ باطلٌ مختلَق' => array('Authorization: Bearer injfix01-invalid-token-probe'),
    'رمزٌ فارغ'          => array('Authorization: Bearer '),
);
$anyLeak = false;
foreach ($cases as $label => $hdrs) {
    $p = http_probe($base . '/me', $hdrs);
    $denied = in_array($p['code'], array(401, 403), true);
    if (!$denied) { $anyLeak = true; }
    ok($denied, "يُردُّ غيرُ المُصادَق — {$label}", $pass, $fail, "HTTP {$p['code']}");
}
ok(!$anyLeak, 'صفرُ تسليمِ بيانٍ بلا رمزٍ صحيح', $pass, $fail);

/* ══════════ ④ التصنيفُ — بعدَ القياسِ لا قبلَه ═══════════════════════════ */
echo "\n── ④ التصنيفُ الناتج ──\n";
if ($facts['live_http'] === 0) {
    $verdict = 'غيرُ حيّة — لا تُصنَّف حتى تُشغَّل';
} elseif (!$facts['network_blocked']) {
    $verdict = 'مبلوغةٌ من كلِّ عميلٍ يصل المضيفَ شبكيًّا — طبقةُ الحجبِ منقوضةٌ لمجلدِ الواجهة';
} else {
    $verdict = 'محجوبةٌ شبكيًّا بحجبٍ مثبَت — P1 قبلَ أيِّ فتحٍ خارجيّ';
}
note('الجواب القاطع', $verdict);
note('ما لا يُقاس من هنا', 'أيُمرِّر محيطُ الشبكةِ المنفذَ 80 من الإنترنت؟ — BLOCKED_EXTERNAL_INPUT');
note('البيانُ غيرُ المُصادَق', $anyLeak ? '**يتسرَّب**' : 'لا يتسرَّب (401/403 في الحالاتِ الثلاث)');

echo "───────────────────────────────────────────────────────────────\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";
echo "◆ والرسوبُ هنا **قياسٌ لا عطبُ فاحص**: يعني أن الحجبَ غيرُ مثبَت.\n";
exit($fail === 0 ? 0 : 1);
