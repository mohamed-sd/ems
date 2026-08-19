<?php
/**
 * tests/profile_card_http_proof.php — برهانٌ حيٌّ: البطاقةُ تُصيَّر متعشِّشةً
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ◆ **لماذا برهانٌ حيٌّ لا فحصٌ ساكن**
 *   العطبُ الذي وقع مرّتين في جولةِ التوحيد لا يظهر في المصدرِ إطلاقًا:
 *   وسمٌ فُتح `<section>` وأُغلق `</div>`. PHP لا تشتكي، والصفحةُ تُصيَّر
 *   بحالةِ 200، والفاحصُ الساكنُ يمرّ — لأنّ نصفَ الترميزِ يخرج من `echo`
 *   في PHP لا من HTML الحرفيّ، فلا سبيلَ لموازنتِه في المصدر.
 *
 *   والنتيجةُ ظهرت في المتصفحِ وحدَه: خرجت ثلاثُ مجموعاتٍ من غلافِ `.main`
 *   وصارت أبناءَ `<body>` — و`<body>` شبكةٌ مرنة، فانكمش عرضُ المحتوى إلى
 *   **48 بكسلًا** وامتدَّ الجسدُ إلى **3825**. صفحةٌ سليمةُ الحالةِ ومنهارةُ
 *   الشكل.
 *
 * ◆ فيُقاس هنا **مخرَجُ الخادمِ نفسُه** بمُحلِّلِ DOM: هل كلُّ عنصرِ بطاقةٍ
 *   داخلَ `.main`؟ وهل يوازن مكدّسُ الوسومِ داخلَه؟
 *
 * ◆ ما لا يقيسه — مُعلَنٌ لا مسكوتٌ عنه: لا يقيس لونًا محسوبًا ولا وزنَ
 *   تتالٍ (يلزمه متصفحٌ حقيقيّ)، ولا يفتح كلَّ بطاقةٍ في النظامِ بل عيّنةً
 *   من كلِّ نوع.
 *
 * ◆ يتطلّب Apache حيًّا على http://localhost/ems
 *   php tests/profile_card_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$BASE = 'http://localhost/ems';
$USER = isset($argv[1]) ? $argv[1] : 'مسؤول المبيعات';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  \xE2\x9C\x94 {$m}\n"); }
function bad($m, $d = '') { global $FAIL; $FAIL++; fwrite(STDOUT, "  \xE2\x9C\x96 {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"); }
function ck($m, $c, $d = '') { $c ? ok($m) : bad($m, $d); }

function req($url, $jar, $post = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) { return array(0, '', ''); }
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}

/** رمزُ CSRF يُقرأ أولًا — بدونه يُردُّ الطلبُ فتُقرأ الجلسةُ الغائبةُ حجبًا. */
function login($base, $user, $jar)
{
    if (file_exists($jar)) { @unlink($jar); }
    list(, , $b) = req($base . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return req($base . '/login.php', $jar, array(
        'username'   => $user,
        'password'   => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : '',
    ));
}

echo "\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90 برهانٌ حيٌّ · بطاقاتُ الكيانات \xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\n\n";

list($c0) = req($BASE . '/login.php', sys_get_temp_dir() . '/prof_ping.jar');
if ($c0 === 0) { exit("\xE2\x9C\x96 Apache لا يستجيب على {$BASE} — البرهانُ الحيُّ متعذِّر\n"); }

$jar = sys_get_temp_dir() . '/prof_card_' . md5($USER) . '.jar';
list($lc) = login($BASE, $USER, $jar);
if ($lc !== 200 && $lc !== 302) { exit("\xE2\x9C\x96 تعذّر الدخولُ بـ«{$USER}» — HTTP {$lc}\n"); }

/* ── العيّناتُ تُلتقط من الشاشاتِ الأمِّ فلا يُثبَّت مُعرِّفٌ في فاحص ─────────
   ◆ **وتُنتقى العيّنةُ ذاتُ البيانات**: أولُ مُعرِّفٍ في القائمةِ قد يكون كِيانًا
     بلا سجلاتٍ — وبطاقتُه تُصيَّر حالةَ فراغٍ بلا أقسامٍ **بالتصميم**. فالحكمُ
     عليها بـ«لا أقسام» فشلٌ كاذبٌ يُدين منتجًا سليمًا. تُجرَّب عدةُ مُعرِّفاتٍ
     ويُؤخذ أولُ ما يحمل بنيةً؛ فإن خلت كلُّها قِيس عقدُ الفراغِ بدلَه. */
function pick_sample($base, $jar, $listPath, $linkPat, $cardPath, $limit = 8)
{
    list($code, , $body) = req($base . '/' . $listPath, $jar);
    if ($code !== 200) { return null; }
    if (!preg_match_all($linkPat, $body, $m)) { return null; }
    $ids = array_slice(array_values(array_unique($m[1])), 0, $limit);
    $firstOpen = null;
    foreach ($ids as $id) {
        list($c, , $b) = req($base . '/' . $cardPath . $id, $jar);
        if ($c !== 200) { continue; }
        if ($firstOpen === null) { $firstOpen = (int) $id; }
        if (strpos($b, 'ems-profile__section') !== false) { return (int) $id; }
    }
    return $firstOpen;
}

$targets = array();
$cid = pick_sample($BASE, $jar, 'Clients/clients.php', '~client_profile\.php\?id=(\d+)~',
                   'Clients/client_profile.php?id=');
if ($cid) { $targets['بطاقة العميل'] = "Clients/client_profile.php?id={$cid}"; }
$eid = pick_sample($BASE, $jar, 'Employees/employees.php', '~employee_profile\.php\?id=(\d+)~',
                   'Employees/employee_profile.php?id=');
if ($eid) { $targets['بطاقة الموظف'] = "Employees/employee_profile.php?id={$eid}"; }

if (!$targets) { exit("\xE2\x9C\x96 لم تُلتقط أيُّ عيّنة — الشاشاتُ الأمُّ محجوبةٌ عن «{$USER}»\n"); }

foreach ($targets as $label => $path) {
    echo "\xE2\x94\x80\xE2\x94\x80 {$label}  ({$path})\n";

    list($code, $head, $body) = req($BASE . '/' . $path, $jar);
    $loc = preg_match('~^Location:\s*(.+)$~mi', $head, $m) ? trim($m[1]) : '';
    ck('الشاشةُ تُفتح (200 لا تحويل)', $code === 200, "HTTP {$code}" . ($loc ? " \xE2\x86\x92 {$loc}" : ''));
    if ($code !== 200) { continue; }

    /* ① المكوّنُ مُصيَّرٌ أصلًا — وإلا صحَّت الفحوصُ التاليةُ على العدم */
    $hero = substr_count($body, 'ems-profile__hero"');
    ck('لوحُ الهويةِ مُصيَّرٌ مرةً واحدة', $hero === 1, "المقيس={$hero}");
    ck('شريطُ المؤشراتِ مُصيَّر', strpos($body, 'ems-profile__stat') !== false);

    /* ◆ كِيانٌ بلا سجلاتٍ **لا يُصيَّر له قسمٌ بالتصميم** — «لا عنوانَ فوقَ
         فراغ». فالعقدُ المقيس: أقسامٌ، أو حالةُ فراغٍ **مُعلَنةٌ** بسببِها
         وبابِها. والصمتُ وحدَه هو الرسوب. */
    $hasSections = strpos($body, 'ems-profile__section') !== false;
    $hasEmpty    = strpos($body, 'ems-state-empty') !== false
                && preg_match('~ems-state-empty[^>]*>(?!\s*</)~', str_replace("\n", ' ', $body)) === 1;
    ck('أقسامٌ مُصيَّرةٌ أو فراغٌ مُعلَنٌ بسببِه',
       $hasSections || $hasEmpty,
       'لا أقسامَ ولا حالةَ فراغٍ — بطاقةٌ صامتة');
    if (!$hasSections) { echo "    \xE2\x84\xB9 العيّنةُ بلا سجلاتٍ — قِيس عقدُ الفراغ\n"; }

    /* ② التعشيشُ الحقيقيّ — هنا يقع العطبُ الذي لا يُرى في المصدر */
    $prev = libxml_use_internal_errors(true);
    $doc  = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $body);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $xp = new DOMXPath($doc);

    $main = $xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " ems-profile ")]');
    ck('غلافُ `.ems-profile` موجودٌ مرةً واحدة', $main->length === 1, "المقيس={$main->length}");

    /* كلُّ عنصرِ بطاقةٍ يجب أن يكون **من نسلِ الغلاف** — والشاردُ يعني وسمًا
       أُغلق بنوعٍ آخرَ فصحَّحه المُحلِّلُ بإخراجِ ما بعدَه. */
    $all = $xp->query('//*[contains(@class, "ems-profile__")]');
    $stray = array();
    foreach ($all as $el) {
        $p = $el->parentNode; $inside = false;
        while ($p && $p->nodeType === XML_ELEMENT_NODE) {
            $cl = ' ' . preg_replace('~\s+~', ' ', (string) $p->getAttribute('class')) . ' ';
            if (strpos($cl, ' ems-profile ') !== false) { $inside = true; break; }
            $p = $p->parentNode;
        }
        if (!$inside) { $stray[] = $el->getAttribute('class'); }
    }
    ck('صفرُ عنصرٍ شاردٍ خارجَ الغلاف', !$stray,
       $stray ? (count($stray) . ' شاردًا: ' . implode(' | ', array_slice(array_unique($stray), 0, 4))) : '');

    /* ③ المجموعةُ تحوي أقسامَها — لا مجموعةٌ فرغت لأنّ محتواها هرب */
    $groups = $xp->query('//*[contains(@class, "ems-profile__group")][not(contains(@class, "group-"))]');
    $empty  = 0;
    foreach ($groups as $g) {
        if ($xp->query('.//*[contains(@class, "ems-profile__section")]', $g)->length === 0
            && $xp->query('.//*[contains(@class, "ems-profile__stat")]', $g)->length === 0) { $empty++; }
    }
    ck('لا مجموعةَ عنوانُها فوقَ فراغ' . ($groups->length ? " ({$groups->length} مجموعة)" : ' (لا مجموعاتٍ)'),
       $empty === 0, "الفارغ={$empty}");

    /* ④ صفرُ بقايا: أصنافُ اللغةِ البصريةِ القديمة */
    $legacy = array('cp-band', 'cp-group', 'profile-card', 'opp-stage', 'tnd-badge', 'cp-via',
                    'identity-card', 'section-card', 'stats-grid', 'info-grid', 'driver-badge',
                    'assignment-status', 'timeline-list');
    $found = array();
    foreach ($legacy as $c) {
        if (preg_match('~class="[^"]*\b' . preg_quote($c, '~') . '\b~', $body)) { $found[] = $c; }
    }
    ck('صفرُ صنفٍ من اللغةِ القديمة', !$found, $found ? implode(' · ', $found) : '');

    /* ⑤ ورقةُ الأنماطِ موصولةٌ فعلًا — مكوّنٌ بلا أنماطٍ ترميزٌ عارٍ */
    ck('`ems-profile.css` موصولةٌ بالصفحة',
       preg_match('~<link[^>]+ems-profile\.css~', $body) === 1);
}

echo "\n" . str_repeat("\xE2\x94\x80", 60) . "\n";
echo "  نجح: {$PASS}   \xC2\xB7   رسب: {$FAIL}\n";
exit($FAIL > 0 ? 1 : 0);
