<?php
/**
 * حارسُ الاثنتَي عشرةَ مجموعة — عقدُ السايدبار (2026-08-17)
 * ═══════════════════════════════════════════════════════════════════════════
 * يحرس ستةَ بنودٍ من نصِّ المالك، **كلُّها مقيسةٌ من الشجرةِ المُصيَّرةِ فعلًا**
 * لا من الجداول (فالصفُّ قد يوجد والرابطُ لا يظهر):
 *   ① لا يزيد عددُ المجموعاتِ عن اثنتَي عشرةَ لأيِّ إدارة.
 *   ② القسمُ المقروءُ لا يبلغ عشرةً (ف٧-٢ حيث ينفع).
 *   ③ **المسارُ الواحدُ في مجموعةٍ واحدةٍ عبرَ كلِّ الإدارات** — «الصفحةُ التي
 *      تظهر في أكثرِ من إدارةٍ تكون في المكانِ نفسِه دائمًا».
 *   ④ «الرئيسية» و«المراسلات» في كلِّ إدارةٍ، في «مساحتي»، باسمٍ واحد.
 *   ⑤ لا تكرارَ لمسارٍ داخلَ سايدبارِ الدورِ الواحد.
 *   ⑥ أيقونةُ كلِّ مجموعةٍ **موجودةٌ في مكتبةِ الأيقوناتِ المحمَّلة** — واسمُ
 *      أيقونةٍ غيرِ موجودٍ يُصيَّر فراغًا صامتًا (سبعةُ أسماءٍ في المُصيِّرِ
 *      القديمِ كانت كذلك: `fa-chart-pie` و`fa-cog` و`fa-folder` …).
 *
 * التشغيل: php tests/nav_ten_groups_test.php — رمز الخروج 0/1.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
$_SERVER['SCRIPT_NAME'] = '/ems/main/dashboard.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'];
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once $ROOT . '/config.php';
require_once $ROOT . '/includes/unified_nav.php';
require_once $ROOT . '/includes/uxui_nav_probe.php';
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

$PASS = 0; $FAIL = 0;
function ok($label, $cond, $detail = '') {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n"; }
}

/* ── الأدوارُ الحيّةُ ومستخدمٌ حقيقيٌّ لكلٍّ ─────────────────────────────────
      **الهويةُ الحقيقيةُ شرطُ قياس**: منحُ الوحداتِ ومنحُ المجالِ المقيَّدِ
      تُقاس بالمستخدمِ لا بالدورِ وحدَه — وجلسةُ uid=0 تُسقط روابطَ التمويلِ
      كلَّها فتُعطي قياسًا أخضرَ كاذبًا. */
$roles = array();
$res = $conn->query("SELECT r.id, r.name,
                       (SELECT u.id FROM users u
                         WHERE u.role = CAST(r.id AS CHAR) AND u.company_id = 4
                           AND (u.is_deleted IS NULL OR u.is_deleted = 0)
                         ORDER BY u.id LIMIT 1) AS uid
                     FROM roles r WHERE r.status = 1 ORDER BY r.id");
while ($res && ($x = $res->fetch_assoc())) {
    if ($x['uid'] === null) { continue; }   /* دورٌ بلا مستخدمٍ حيٍّ = «غير مقيس» لا «مجتاز» */
    $roles[] = array((int) $x['id'], $x['name'], (int) $x['uid']);
}

$tax = emsNavTaxonomy($conn);
echo "── ⓪ التبويبُ مبذورٌ وفعّال ──\n";
ok('جدولُ المجموعاتِ مبذورٌ (' . count($tax) . ' صفًّا)', count($tax) > 0 && count($tax) <= 12,
   'العدد=' . count($tax));
if (empty($tax)) {
    echo "\nالنتيجة: التبويبُ غيرُ مفعَّلٍ — لا يُقاس ما ليس حيًّا.\n";
    exit(1);
}

echo "── ⑥ الأيقوناتُ موجودةٌ في المكتبةِ المحمَّلة ──\n";
$fa = @file_get_contents($ROOT . '/assets/css/all.min.css');
if ($fa === false || $fa === '') {
    ok('مكتبةُ الأيقوناتِ مقروءة', false, 'assets/css/all.min.css');
} else {
    $missing = array();
    foreach ($tax as $code => $m) {
        $cls = trim(preg_replace('/^fa\s+/', '', $m['icon']));
        if (strpos($fa, '.' . $cls . '{--fa:') === false && strpos($fa, '.' . $cls . ':before') === false) {
            $missing[] = $code . '⇐' . $cls;
        }
    }
    ok('أيقونةُ كلِّ مجموعةٍ لها صفٌّ في المكتبة', empty($missing), implode(' · ', $missing));
}

/* ── المسحُ المُصيَّرُ لكلِّ دور ──────────────────────────────────────────── */
$overTen = array(); $overNine = array(); $dupInRole = array();
$placement = array();  /* route => group => 1 — بندُ «المكانِ نفسِه دائمًا» */
$missAnchor = array(); $anchorGroup = array(); $anchorLabel = array();
$badOpen = array(); $outsideGroup = array(); $notHomeFirst = array();
$measured = 0;

foreach ($roles as $r) {
    list($rid, $rname, $uid) = $r;
    /* حارسُ التكرارِ `static` لكلِّ عملية — بلا تصفيرٍ ينكمش عدُّ الأدوارِ التالية */
    ems_nav_mark_printed('', true);
    $_SESSION['user'] = array('id' => $uid, 'role' => (string) $rid, 'company_id' => 4, 'name' => 'nav-ten-test');

    ob_start();
    $rendered = (bool) renderUnifiedNavigationV2($conn, (string) $rid, '../', array(), '');
    $html = ob_get_clean();
    if (!$rendered || trim($html) === '') { continue; }
    $measured++;

    /* ⑧ **لا رابطَ خارجَ مجموعة** (قرارُ المالك): يُقاس بالشجرةِ لا بالنصّ —
         رابطٌ ليس له سلفٌ `li.nav-group` هو رابطٌ طافٍ بلا نسب. */
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><ul>' . $html . '</ul>');
    libxml_clear_errors();
    $dx = new DOMXPath($dom);
    $inGrp = "contains(concat(' ',normalize-space(@class),' '),' nav-group ')";
    foreach ($dx->query('//a[@href][not(ancestor::li[' . $inGrp . '])]') as $a) {
        $hh = trim($a->getAttribute('href'));
        if ($hh === '' || $hh === '#' || strpos($hh, 'javascript:') === 0) { continue; }
        $outsideGroup[] = "دور {$rid}: {$hh}";
    }
    /* ⑨ **«الرئيسية» أولُ رابطٍ دائمًا** (قرارُ المالك) — أوّلُ `<a>` في الشجرة */
    $firstA = $dx->query('//a[@href]');
    if ($firstA->length) {
        $ft = trim(preg_replace('/\s+/u', ' ', $firstA->item(0)->textContent));
        if ($ft !== 'الرئيسية') { $notHomeFirst[] = "دور {$rid} ({$rname}) → «{$ft}»"; }
    }

    $pos = uxp_parse_nav_html($html);
    $heads = array(); $sections = array(); $seen = array(); $seenBase = array();
    foreach ($pos as $p) {
        $route = mb_strtolower(preg_replace('~^(\.\./)+~', '', preg_replace('/[?#].*$/u', '', trim($p['href']))));
        if ($route === '') { continue; }
        $heads[$p['group']] = true;
        /* ◆ **القسمُ يُعدُّ داخلَ مجموعتِه لا عبرَ القائمة**: اسمُ قسمٍ قد يتكرر
             في مجموعتين (محورُ المجالِ يضع «المالية والخزينة» قسمًا داخلَ
             «التقارير» بينما هي رأسُ مجموعةٍ أيضًا) — وجمعُهما يخترع مخالفةً
             لا وجودَ لها على الشاشة. */
        $sec = $p['group'] . ' ▸ ' . (isset($p['section']) ? $p['section'] : $p['group']);
        $sections[$sec] = (isset($sections[$sec]) ? $sections[$sec] : 0) + 1;
        /* ◆ **وحدةُ التكرارِ الرابطُ بمرساتِه لا ملفُّه**: ملفٌّ بمرساتين مختلفتين
             **مدخلانِ مقصودانِ** لقسمين (INJ-0459) — ومن جرَّدَ المرساةَ قبلَ العدِّ
             أعلن تكرارًا حيث لا تكرار. والحكمُ هنا كحكمِ `ems_nav_mark_printed`. */
        $ident = mb_strtolower(preg_replace('~^(\.\./)+~', '', trim($p['href'])));
        if (isset($seen[$ident])) { $dupInRole[] = "دور {$rid}: {$ident}"; }
        $seen[$ident] = true;
        $seenBase[$route] = true;
        $placement[$route][$p['group']] = true;
        if ($route === 'main/role_board.php' || $route === 'chats/index.php') {
            $anchorGroup[$route][$p['group']] = true;
            $anchorLabel[$route][$p['label']] = true;
        }
    }
    if (count($heads) > 12) { $overTen[] = "دور {$rid} ({$rname}) = " . count($heads) . " مجموعة"; }

    /* ⑦ الفتحُ الافتراضيُّ = **كلُّ المجموعاتِ مطويّة** (قرارُ المالك 2026-08-17،
         مُنفَّذٌ في `unified_nav.php`: `$open = false` وفوقَه السطرُ السابقُ معلَّقًا).
         ويُقرأ من الشجرةِ المُصيَّرةِ لا من التعريف — فلو أُعيد الفتحُ الافتراضيُّ
         يومًا رسَبَ هذا البندُ فورًا وأعلن التغييرَ بدل أن يمرَّ صامتًا. */
    if (preg_match_all('~<li class="nav-group( open)?"[^>]*>.*?<span class="nav-group-name">([^<]*)</span>~us', $html, $om, PREG_SET_ORDER)) {
        $openNames = array();
        foreach ($om as $o) {
            if (trim($o[1]) !== 'open') { continue; }
            $openNames[trim(html_entity_decode($o[2], ENT_QUOTES, 'UTF-8'))] = true;
        }
        if (!empty($openNames)) {
            $badOpen[] = "دور {$rid}: مفتوحةٌ ابتداءً=[" . implode('،', array_keys($openNames)) . '] والمتوقَّع: لا شيء';
        }
    }
    foreach ($sections as $s => $c) { if ($c >= 10) { $overNine[] = "دور {$rid}: «{$s}» = {$c}"; } }
    foreach (array('main/role_board.php', 'chats/index.php') as $a) {
        if (!isset($seenBase[$a])) { $missAnchor[] = "دور {$rid} ({$rname}) بلا {$a}"; }
    }
}

echo "── القياسُ على {$measured} دورًا مُصيَّرًا ──\n";
ok('أدوارٌ مقيسةٌ فعلًا (المقامُ غيرُ صفريّ)', $measured >= 15, "المقيس={$measured}");

echo "── ① سقفُ الاثنتَي عشرةَ مجموعة ──\n";
ok('لا إدارةَ تتجاوز اثنتَي عشرةَ مجموعة', empty($overTen), implode(' · ', array_slice($overTen, 0, 5)));

echo "── ② حدُّ التسعةِ للقسمِ المقروء (ف٧-٢) ──\n";
ok('لا قسمَ مقروءٌ يبلغ عشرةَ روابط', empty($overNine), implode(' · ', array_slice($overNine, 0, 5)));

echo "── ③ المكانُ نفسُه دائمًا ──\n";
$split = array();
foreach ($placement as $route => $gs) {
    if (count($gs) > 1) { $split[] = $route . ' ⇐ ' . implode(' ⁄ ', array_keys($gs)); }
}
ok('لا مسارَ يسكن مجموعتين في إدارتين', empty($split), implode(' · ', array_slice($split, 0, 5)));

echo "── ④ مرساتا كلِّ سايدبار ──\n";
ok('«الرئيسية» و«المراسلات» في كلِّ إدارةٍ مقيسة', empty($missAnchor), implode(' · ', array_slice($missAnchor, 0, 5)));
foreach (array('main/role_board.php' => 'الرئيسية', 'chats/index.php' => 'المراسلات') as $a => $want) {
    $g = isset($anchorGroup[$a]) ? array_keys($anchorGroup[$a]) : array();
    $l = isset($anchorLabel[$a]) ? array_keys($anchorLabel[$a]) : array();
    ok("«{$want}» في مجموعةٍ واحدةٍ لكلِّ الإدارات", count($g) === 1, implode(' ⁄ ', $g));
    ok("«{$want}» باسمٍ واحدٍ لكلِّ الإدارات", count($l) === 1 && $l[0] === $want, implode(' ⁄ ', $l));
}

echo "── ⑤ لا تكرارَ داخلَ الدور ──\n";
ok('لا مسارَ يُطبع مرتين في سايدبارِ دورٍ واحد', empty($dupInRole), implode(' · ', array_slice($dupInRole, 0, 5)));

echo "── ⑦ أوّلُ دخولٍ: كلُّ المجموعاتِ مطويّة ──\n";
ok('لا مجموعةَ مفتوحةٌ عند أوّلِ دخولٍ في أيِّ إدارة', empty($badOpen),
   implode(' · ', array_slice($badOpen, 0, 5)));

echo "── ⑧ لا رابطَ خارجَ مجموعة ──\n";
ok('كلُّ رابطٍ يسكن مجموعةً برأسٍ واسم', empty($outsideGroup), implode(' · ', array_slice($outsideGroup, 0, 5)));

echo "── ⑨ «الرئيسية» أولُ رابطٍ في كلِّ شاشة ──\n";
ok('أولُ رابطٍ في سايدبارِ كلِّ دورٍ هو «الرئيسية»', empty($notHomeFirst), implode(' · ', array_slice($notHomeFirst, 0, 5)));

echo "\nالنتيجة: {$PASS} ناجح · {$FAIL} راسب\n";
exit($FAIL === 0 ? 0 : 1);
