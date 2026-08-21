<?php
/**
 * tests/injchain01_screens_http_proof.php
 *   شاهدُ HTTP لشاشاتِ عقدِ السلسلةِ المبنية — INJ-CHAIN-CLOSE-01
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الملفُّ على القرصِ ليس شاشةً**: الشاشةُ ما يفتحه صاحبُ الدورِ بمتصفِّحه
 *   فيُصيَّر بالقشرةِ والحارس. فيُقاس هنا **كما يصل المتصفحَ**:
 *     ① صاحبُ الدورِ يفتحها بـ200 ولا يُحوَّل
 *     ② القشرةُ الموحَّدةُ حاضرةٌ (السايدبارُ ورأسُ الصفحة)
 *     ③ **صفرُ تنسيقٍ محليّ** — لا `<style>` في جسدِ الشاشة
 *     ④ كلُّ نموذجِ POST فيه رمزُ الحماية
 *     ⑤ **الحارسُ يمنع مَن لا يملكها** — حزامٌ سلبيٌّ بدورٍ آخر
 *
 * ◆ ويلزمه خادمُ WAMP يعمل. وإن تعذّر الاتصالُ **يُعلَن ولا يُدَّعى نجاح**.
 *
 * التشغيل: php tests/injchain01_screens_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
while (ob_get_level() > 0) { ob_end_clean(); }

$ROOT = dirname(__DIR__);
$BASE = 'http://localhost/ems';
$PW   = '12345678';

$SCREENS = array(
    array('Finance/unit_fin_final.php',     'الاعتماد المالي النهائي',           'مشرف المالية', 9),
    array('Finance/ar_accrual_gen.php',     'توليد استحقاقات عقد العميل',        'مشرف المالية', 16),
    array('Finance/ar_completion_cert.php', 'شهادة الإنجاز الشهرية',             'مشرف المالية', 17),
    array('Finance/ar_claim_invoice.php',   'فاتورة المطالبة وإحالتها',          'مشرف المالية', 18),
    array('Finance/tre_beneficiary.php',    'سجل المستفيدين والحسابات البنكية',  'مشرف المالية', 0),
    array('Finance/tre_pay_batch.php',      'دفعات الدفع والتنفيذ',              'مشرف المالية', 25),
    array('Operations/unit_correction.php', 'تصحيح الوحدات بالسلسلة الثلاثية',   'محمد',        13),
);
/** دورُ الحزامِ السلبيّ — لا يملك شاشاتِ المالية. */
$OUTSIDER = 'محمد';

$pass = 0; $fail = 0;
function ok($m) { global $pass; $pass++; echo "  ✔ {$m}\n"; }
function no($m) { global $fail; $fail++; echo "  ✘ {$m}\n"; }
function ck($c, $m) { $c ? ok($m) : no($m); }

function req($url, $jar, $post = null, $follow = false)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => 30,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err  = curl_error($ch);
    curl_close($ch);
    return array('code' => $code, 'head' => substr((string) $raw, 0, $hs),
                 'body' => substr((string) $raw, $hs), 'err' => $err);
}

function login($base, $jar, $user, $pw)
{
    @unlink($jar);
    $g = req($base . '/login.php', $jar);
    if ($g['code'] === 0) { return null; }              /* لا خادم — يُعلَن ولا يُدَّعى */
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $g['body'], $m);
    $r = req($base . '/login.php', $jar, array(
        'username' => $user, 'password' => $pw, 'csrf_token' => isset($m[1]) ? $m[1] : ''));
    return $r['code'] === 302 && stripos($r['head'], 'login.php') === false;
}

echo "══ شاشاتُ عقدِ السلسلةِ — كما تصل المتصفحَ ══\n";
$jarDir = sys_get_temp_dir();
$probe = req($BASE . '/login.php', $jarDir . '/injchain_probe.txt');
if ($probe['code'] === 0) {
    echo "  ⛔ **لا خادمَ على {$BASE}** — {$probe['err']}\n";
    echo "     ولا يُدَّعى نجاحٌ بلا قياس. شغّل WAMP ثم أعد الشاهد.\n";
    exit(2);
}

/* ── ① كلُّ شاشةٍ تُفتح لصاحبِها ─────────────────────────────────────────── */
$byUser = array();
foreach ($SCREENS as $s) { $byUser[$s[2]][] = $s; }

foreach ($byUser as $user => $list) {
    $jar = $jarDir . '/injchain_' . md5($user) . '.txt';
    $li = login($BASE, $jar, $user, $PW);
    if ($li !== true) { no("تعذّر دخولُ «{$user}» — لا تُقاس شاشاتُه"); continue; }
    echo "\n── الحسابُ «{$user}» ──\n";
    foreach ($list as $s) {
        list($route, $title, $u2, $node) = $s;
        $r = req($BASE . '/' . $route, $jar);
        $lbl = ($node ? "العقدة {$node} · " : '') . $route;
        if ($r['code'] !== 200) { no("{$lbl} — رمزُ الردِّ {$r['code']} لا 200"); continue; }
        $b = $r['body'];
        $isRedir = stripos($r['head'], 'Location:') !== false;
        $hasShell = strpos($b, 'sidebarNavList') !== false;
        $hasTitle = mb_strpos($b, $title) !== false;
        $bodyPart = strpos($b, '<div class="main"') !== false
                  ? substr($b, strpos($b, '<div class="main"')) : $b;
        $localCss = preg_match('/<style[\s>]/i', $bodyPart) ? 1 : 0;
        $forms = preg_match_all('/<form\b[^>]*method=["\']post["\'][^>]*>(.*?)<\/form>/is', $b, $fm);
        $noCsrf = 0;
        if ($forms) { foreach ($fm[1] as $inner) { if (strpos($inner, 'csrf_token') === false) { $noCsrf++; } } }
        ck(!$isRedir && $hasShell && $hasTitle && $localCss === 0 && $noCsrf === 0,
           sprintf('%s — 200 · قشرة=%s · عنوان=%s · تنسيقٌ محليّ=%d · نماذجُ POST=%d بلا رمزِ حماية=%d',
                   $lbl, $hasShell ? '✔' : '✘', $hasTitle ? '✔' : '✘', $localCss, $forms, $noCsrf));
    }
}

/* ── ② الحزامُ السلبيّ — الحارسُ يمنع مَن لا يملكها ───────────────────────── */
echo "\n── ② الحزامُ السلبيّ — مَن لا يملكها لا يفتحها ──\n";
$jar = $jarDir . '/injchain_out.txt';
if (login($BASE, $jar, $OUTSIDER, $PW) === true) {
    $blocked = 0; $tried = 0;
    foreach ($SCREENS as $s) {
        if ($s[2] === $OUTSIDER) { continue; }           /* هذه شاشتُه فعلًا */
        $tried++;
        $r = req($BASE . '/' . $s[0], $jar);
        /* الحجبُ إمّا تحويلٌ إلى اللوحةِ أو ردٌّ غيرُ 200 */
        if ($r['code'] !== 200 || stripos($r['head'], 'dashboard.php') !== false) { $blocked++; }
    }
    ck($tried > 0 && $blocked === $tried,
       "**دورٌ لا يملك شاشاتِ الماليةِ يُحجَب عنها** — {$blocked} من {$tried}");
    if ($blocked !== $tried) {
        echo "     ◆ وهذا **ثقبُ وصولٍ حقيقيّ**: المنحُ وقع لدورٍ لا يملك العملية.\n";
    }
} else { no('تعذّر دخولُ حسابِ الحزامِ السلبيّ — لم يُثبت الحجب'); }

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
