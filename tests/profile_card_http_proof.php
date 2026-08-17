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

/* ── البطاقاتُ السبعُ وحساباتُها ───────────────────────────────────────────
   ◆ **حسابٌ لكلِّ بطاقةٍ لا حسابٌ واحدٌ للجميع**: بطاقةُ المورِّدِ لا تُفتح
     لحساب المبيعات، وبطاقةُ المعدةِ لا تُفتح لحسابِ المورِّدين — والحكمُ على
     بطاقةٍ بحسابٍ لا يملكها يقيس **تحويلةً** لا تصميمًا. الخريطةُ هنا مرآةُ
     `tools/uxw_accounts.txt` (كلمةُ المرورِ الموحَّدة 12345678 — دليلُ UAT).
   ◆ وما تعذّر التقاطُ عيّنتِه **يُعلَن ولا يُسكت عنه**: بطاقةٌ لم تُقَس تُذكر
     في الحصيلةِ صراحةً، فلا يُقرأ «صفرُ رسوبٍ» على أنه «الكلُّ مقيسٌ سليم». */
$CARDS = array(
    'بطاقة العميل'  => array('user' => 'مبيعات', 'list' => 'Clients/clients.php',
        'pat' => '~client_profile\.php\?id=(\d+)~',       'card' => 'Clients/client_profile.php?id='),
    'بطاقة الموظف'  => array('user' => 'مبيعات', 'list' => 'Employees/employees.php',
        'pat' => '~employee_profile\.php\?id=(\d+)~',     'card' => 'Employees/employee_profile.php?id='),
    'بطاقة المشروع' => array('user' => 'محمد', 'list' => 'Projects/projects.php',
        'pat' => '~project_profile\.php\?id=(\d+)~',      'card' => 'Projects/project_profile.php?id='),
    'بطاقة المورِّد' => array('user' => 'مصعب', 'list' => 'Suppliers/suppliers.php',
        'pat' => '~supplier_profile\.php\?id=(\d+)~',     'card' => 'Suppliers/supplier_profile.php?id='),
    'بطاقة المعدة'  => array('user' => 'محمد', 'list' => 'Equipments/equipments.php',
        'pat' => '~equipment_profile\.php\?id=(\d+)~',    'card' => 'Equipments/equipment_profile.php?id='),
    'بطاقة المستخدم' => array('user' => 'محمد', 'list' => 'main/users.php',
        'pat' => '~user_profile\.php\?id=(\d+)~',         'card' => 'main/user_profile.php?id='),
    /* عمليةُ التمويلِ مجالٌ مقيَّدٌ (FIN-01 §1.1): لا تُفتح إلا بمنحِ ownership
       الفرديِّ أو بدورِ التمويلِ 26 — فحسابُها «تمويل» لا غيرُه. وعيّنتُها
       تُلتقط من شاشةِ الأقساطِ لأنها الوحيدةُ التي تربط إليها. */
    'ملف عملية التمويل' => array('user' => 'تمويل', 'list' => 'Financing/installments.php',
        'pat' => '~operation_profile\.php\?id=(\d+)~',    'card' => 'Financing/operation_profile.php?id='),
    'الملف الشخصي'  => array('user' => 'محمد', 'fixed' => 'main/profile.php'),
);

$targets = array(); $skipped = array();
foreach ($CARDS as $label => $c) {
    $u  = $c['user'];
    $cj = sys_get_temp_dir() . '/prof_card_' . md5($u) . '.jar';
    list($lc) = login($BASE, $u, $cj);
    if ($lc !== 200 && $lc !== 302) { $skipped[] = "{$label}: تعذّر الدخولُ بـ«{$u}»"; continue; }
    if (isset($c['fixed'])) {
        $targets[$label] = array('path' => $c['fixed'], 'jar' => $cj, 'user' => $u);
        continue;
    }
    $id = pick_sample($BASE, $cj, $c['list'], $c['pat'], $c['card']);
    if (!$id) { $skipped[] = "{$label}: لا عيّنةَ — الشاشةُ الأمُّ محجوبةٌ عن «{$u}» أو بلا سجلات"; continue; }
    $targets[$label] = array('path' => $c['card'] . $id, 'jar' => $cj, 'user' => $u);
}

if (!$targets) { exit("\xE2\x9C\x96 لم تُلتقط أيُّ عيّنة\n"); }

foreach ($targets as $label => $t) {
    $path = $t['path']; $jar = $t['jar'];
    echo "\xE2\x94\x80\xE2\x94\x80 {$label}  ({$path})  بحساب «{$t['user']}»\n";

    list($code, $head, $body) = req($BASE . '/' . $path, $jar);
    $loc = preg_match('~^Location:\s*(.+)$~mi', $head, $m) ? trim($m[1]) : '';
    ck('الشاشةُ تُفتح (200 لا تحويل)', $code === 200, "HTTP {$code}" . ($loc ? " \xE2\x86\x92 {$loc}" : ''));
    if ($code !== 200) { continue; }

    /* ① المكوّنُ مُصيَّرٌ أصلًا — وإلا صحَّت الفحوصُ التاليةُ على العدم */
    $hero = substr_count($body, 'ems-profile__hero"');
    ck('لوحُ الهويةِ مُصيَّرٌ مرةً واحدة', $hero === 1, "المقيس={$hero}");
    /* ◆ شريطُ المؤشراتِ **مكوّنانِ لا واحد**: `ems_profile_stats` للعددِ
         المجرَّد، و`ems_kpi_card` للمؤشرِ ذي العقدِ السباعيِّ (وحدةٌ · فترةٌ ·
         مقارنةٌ · حالةٌ · مصدرٌ · رابطُ تعمّق). وبطاقةُ المورِّدِ تستعمل الثاني
         عمدًا — واستبدالُه بالأولِ يُسقط ستَّ حقائقَ حوكميةٍ من كلِّ مؤشر.
         فالعقدُ المقيس: **شريطُ مؤشراتٍ مُصيَّرٌ بأحدِ المكوّنَين** لا بواحدٍ
         بعينِه. (كان الفحصُ يطلب الأولَ وحدَه فرسّب تصميمًا سليمًا.) */
    ck('شريطُ المؤشراتِ مُصيَّرٌ (stats أو kpi-card)',
       strpos($body, 'ems-profile__stat') !== false || strpos($body, 'ems-kpi-card') !== false);

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

    /* ④ صفرُ بقايا: أصنافُ اللغاتِ البصريةِ السبعِ التي حلَّ المكوّنُ محلَّها */
    $legacy = array(
        /* بطاقةُ العميل */   'cp-band', 'cp-group', 'opp-stage', 'tnd-badge', 'cp-via',
        /* بطاقةُ الموظف */   'identity-card', 'section-card', 'stats-grid', 'info-grid',
                              'driver-badge', 'assignment-status', 'timeline-list',
        /* المشترَكُ بينها */ 'profile-card', 'profile-hero', 'profile-row', 'profile-pill',
                              'profile-badge', 'profile-avatar', 'profile-card-title',
                              'profile-stats', 'stat-meta', 'act-badge',
        /* بطاقةُ المعدة */   'ep-hero', 'ep-chip', 'ep-pill', 'ep-fact',
        /* بطاقةُ المورِّد */  'spf-lead-card', 'spf-section-card',
        /* عمليةُ التمويل */  'fin-op-badge-state', 'fin-op-inst-paid', 'fin-op-inst-due',
        /* البلاغاتُ المتصلة */ 'related-tickets-tab',
    );
    $found = array();
    foreach ($legacy as $c) {
        if (preg_match('~class="[^"]*\b' . preg_quote($c, '~') . '\b~', $body)) { $found[] = $c; }
    }
    ck('صفرُ صنفٍ من اللغةِ القديمة', !$found, $found ? implode(' · ', $found) : '');

    /* ⑤ ورقةُ الأنماطِ موصولةٌ فعلًا — مكوّنٌ بلا أنماطٍ ترميزٌ عارٍ */
    ck('`ems-profile.css` موصولةٌ بالصفحة',
       preg_match('~<link[^>]+ems-profile\.css~', $body) === 1);

    /* ⑥ «البلاغاتُ المتصلة» — حيث تُضمَّن تكون **داخلَ** الغلافِ لا خلفَه.
         كانت تُضمَّن بعد إغلاقِ `.main` في ثلاثِ بطاقاتٍ فتظهر بجانبِ الشاشة؛
         والفحصُ يقيس أنّ قسمَها من نسلِ الغلافِ لا شقيقًا له. */
    $rtPos = mb_strpos($body, 'البلاغاتُ المتصلة');
    if ($rtPos !== false) {
        $rtNodes = $xp->query('//*[contains(@class, "ems-profile__section")]'
                            . '[.//*[contains(text(), "البلاغاتُ المتصلة")]'
                            . ' or contains(., "البلاغاتُ المتصلة")]');
        $rtIn = false;
        foreach ($rtNodes as $n) {
            $p = $n;
            while ($p = $p->parentNode) {
                if ($p->nodeType === XML_ELEMENT_NODE
                 && preg_match('~(^|\s)ems-profile(\s|$)~', (string) $p->getAttribute('class'))) { $rtIn = true; break 2; }
            }
        }
        ck('«البلاغاتُ المتصلة» داخلَ غلافِ البطاقة', $rtIn, 'مُصيَّرةٌ خارجَ `.ems-profile`');
    }
}

echo "\n" . str_repeat("\xE2\x94\x80", 60) . "\n";
echo '  بطاقاتٌ مقيسة: ' . count($targets) . ' من ' . count($CARDS) . "\n";
if ($skipped) {
    /* «ما لم يُقَس يُعلَن»: بطاقةٌ سقطت من الجولةِ ليست بطاقةً ناجحة */
    echo "  \xE2\x9A\xA0 لم تُقَس (" . count($skipped) . "):\n";
    foreach ($skipped as $s) { echo "      \xC2\xB7 {$s}\n"; }
}
echo "  نجح: {$PASS}   \xC2\xB7   رسب: {$FAIL}\n";
exit($FAIL > 0 ? 1 : 0);
