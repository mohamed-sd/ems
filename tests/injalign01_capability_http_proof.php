<?php
/**
 * tests/injalign01_capability_http_proof.php
 *   شاهدُ HTTP للقدراتِ الخمسِ — INJ-SAL-ALIGN-01 · INJ-SUP-ALIGN-01 §8
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الملفُّ على القرصِ ليس شاشةً**: الشاشةُ ما يفتحه صاحبُ الدورِ بمتصفِّحه
 *   فيُصيَّر بالقشرةِ والحارس. فيُقاس هنا **كما يصل المتصفحَ**:
 *     ① صاحبُ الدورِ يفتحها بـ200 ولا يُحوَّل
 *     ② القشرةُ الموحَّدةُ حاضرةٌ · ③ **صفرُ تنسيقٍ محليّ**
 *     ④ كلُّ نموذجِ POST فيه رمزُ الحماية
 *     ⑤ **التبويبُ يوجد لا يُعلَن**: شريطُ العائلةِ حاضرٌ في الأبِ وفي الابن،
 *        ورابطُ الابنِ مُصيَّرٌ في شريطِ أبيه فعلًا — فالمنفذُ مُثبَتٌ لا مزعوم
 *     ⑥ **الحارسُ يمنع مَن لا يملكها** — حزامٌ سلبيٌّ بدورٍ آخر
 *
 * ◆ ويلزمه خادمُ WAMP يعمل. وإن تعذّر الاتصالُ **يُعلَن ولا يُدَّعى نجاح**.
 *
 * التشغيل: php tests/injalign01_capability_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
while (ob_get_level() > 0) { ob_end_clean(); }

$BASE = 'http://localhost/ems';
$PW   = '12345678';

/* route, عنوانٌ يُبحث عنه, حسابُ صاحبِها, الأبُ الذي يجب أن يحمل رابطَها */
$SCREENS = array(
 array('Opportunities/client_need_rfq.php', 'احتياج العميل وطلب العرض', 'مشرف المبيعات', 'Opportunities/opportunities.php'),
 array('Clients/quotation_lines.php',       'بنود العروض',              'مشرف المبيعات', 'Clients/quotations.php'),
 array('Clients/quotation_negotiation.php', 'التفاوض ومراجعات العرض',   'مشرف المبيعات', 'Clients/quotations.php'),
 /* ◆ **مالكُهما الدورُ 2 ولا حسابَ له في النظامِ كلِّه** (مقيس: صفر) — فلا
  *   يُختلق حسابٌ ليخضرَّ القياس. ويُقاسان بحسابَي **قارئٍ حقيقيَّين**
  *   ورثا الرؤيةَ من الأب: الماليةُ ترى التسويةَ فترى تبويبَها، والمبيعاتُ
  *   ترى سجلَّ المورّدين فترى لوحتَه. **والكتابةُ مقيسةٌ في شاهدِ الخدمة**. */
 array('Suppliers/supplier_violations.php', 'المخالفات والجزاءات',      'مشرف المالية',   'Suppliers/settlements.php'),
 array('Suppliers/supplier_board.php',      'لوحة إدارة الموردين',      'مشرف المبيعات',  ''),
);
/** دورُ الحزامِ السلبيّ — التشغيلُ لا يملك مبيعاتٍ ولا موردين. */
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
    if ($g['code'] === 0) { return null; }
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $g['body'], $m);
    $r = req($base . '/login.php', $jar, array(
        'username' => $user, 'password' => $pw, 'csrf_token' => isset($m[1]) ? $m[1] : ''));
    return $r['code'] === 302 && stripos($r['head'], 'login.php') === false;
}

echo "══ القدراتُ الخمسُ — كما تصل المتصفحَ ══\n";
$jarDir = sys_get_temp_dir();
$probe = req($BASE . '/login.php', $jarDir . '/injcap_probe.txt');
if ($probe['code'] === 0) {
    echo "  ⛔ **لا خادمَ على {$BASE}** — {$probe['err']}\n";
    echo "     ولا يُدَّعى نجاحٌ بلا قياس. شغّل WAMP ثم أعد الشاهد.\n";
    exit(2);
}

/* ── ① كلُّ شاشةٍ تُفتح لصاحبِها · ② التبويبُ موجودٌ في الأبِ فعلًا ─────────── */
$byUser = array();
foreach ($SCREENS as $s) { $byUser[$s[2]][] = $s; }

foreach ($byUser as $user => $list) {
    $jar = $jarDir . '/injcap_' . md5($user) . '.txt';
    $li = login($BASE, $jar, $user, $PW);
    if ($li !== true) { no("تعذّر دخولُ «{$user}» — لا تُقاس شاشاتُه"); continue; }
    echo "\n── الحسابُ «{$user}» ──\n";
    foreach ($list as $s) {
        list($route, $title, $u2, $parent) = $s;
        $r = req($BASE . '/' . $route, $jar);
        if ($r['code'] !== 200) { no("{$route} — رمزُ الردِّ {$r['code']} لا 200"); continue; }
        $b = $r['body'];
        $isRedir  = stripos($r['head'], 'Location:') !== false;
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
                   $route, $hasShell ? '✔' : '✘', $hasTitle ? '✔' : '✘', $localCss, $forms, $noCsrf));

        /* ◆ **المنفذُ يُثبَت في الأبِ لا يُعلَن**: رابطُ الابنِ مُصيَّرٌ في شريطِ أبيه */
        if ($parent !== '') {
            $rp = req($BASE . '/' . $parent, $jar);
            $child = basename($route);
            $inParent = ($rp['code'] === 200 && strpos($rp['body'], $child) !== false);
            ck($inParent, "**والتبويبُ موجودٌ في أبيه** — {$child} مُصيَّرٌ في " . basename($parent));
        }
    }
}

/* ── ③ الحزامُ السلبيّ — الحارسُ يمنع مَن لا يملكها ───────────────────────── */
echo "\n── ③ الحزامُ السلبيّ — مَن لا يملكها لا يفتحها ──\n";
$jar = $jarDir . '/injcap_out.txt';
if (login($BASE, $jar, $OUTSIDER, $PW) === true) {
    $blocked = 0; $tried = 0; $open = array();
    foreach ($SCREENS as $s) {
        $tried++;
        $r = req($BASE . '/' . $s[0], $jar);
        if ($r['code'] !== 200 || stripos($r['head'], 'dashboard.php') !== false) { $blocked++; }
        else { $open[] = $s[0]; }
    }
    ck($tried > 0 && $blocked === $tried,
       "**دورُ التشغيلِ يُحجَب عن قدراتِ المبيعاتِ والموردين** — {$blocked} من {$tried}");
    if ($open) {
        echo "     ◆ وهذا **ثقبُ وصولٍ حقيقيّ**: " . implode(' · ', $open) . "\n";
    }
} else { no('تعذّر دخولُ حسابِ الحزامِ السلبيّ — لم يُثبت الحجب'); }

/* ── ④ الحزامُ السلبيُّ الثاني — **بوابةُ المورّدِ الخارجيةُ لا تدخل** ──────────
 * ◆ الدورُ 8 «مشرف موردين» **حسابُ بوابةٍ خارجيّ** لا مشرفُ الإدارة، و
 *   `SupplierPortalGuard` يردُّ ما خرج عن قائمتِه الضيقةِ بـ404. وكنتُ منحتُه
 *   اعتمادَ المخالفةِ ظنًّا أنه الداخليّ — **فكشفه هذا القياسُ حيًّا** فأُزيل. */
echo "\n── ④ الحزامُ السلبيُّ الثاني — بوابةُ المورّدِ الخارجيةُ لا تدخل ──\n";
$jar2 = $jarDir . '/injcap_portal.txt';
if (login($BASE, $jar2, 'مشرف الموردين', $PW) === true) {
    $b2 = 0; $t2 = 0;
    foreach ($SCREENS as $s) {
        $t2++;
        $r = req($BASE . '/' . $s[0], $jar2);
        if ($r['code'] !== 200) { $b2++; }
    }
    ck($t2 > 0 && $b2 === $t2,
       "**حسابُ بوابةِ المورّدِ الخارجيةِ يُردُّ عن الخمسِ كلِّها** — {$b2} من {$t2}");
} else { no('تعذّر دخولُ حسابِ البوابةِ الخارجيةِ — لم يُثبت الردّ'); }

/* ── ⑤ إعلانٌ صريح: مالكُ سطحَي المورّدين بلا حسابٍ في النظام ───────────────── */
echo "\n── ⑤ ما لم يُقَس ولماذا ──\n";
echo "  ◆ **الدورُ 2 «ادارة الموردين» بصفرِ حسابٍ في النظامِ كلِّه** — فمسارُ\n";
echo "    الكتابةِ في `supplier_violations.php` **غيرُ مقيسٍ عبر المتصفح**،\n";
echo "    ومقيسٌ كاملًا في `tests/injalign01_capability_service_proof.php`.\n";
echo "    وهذا `BLOCKED_NO_ACTOR` يُعلَن ولا يُغطّى بحسابٍ مختلَق.\n";

echo "\n" . str_repeat('─', 66) . "\n";
printf("النتيجة: %d نجاح · %d رسوب\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
