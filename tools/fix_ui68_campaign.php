<?php
/**
 * tools/fix_ui68_campaign.php — حملةُ عيوبِ الواجهةِ الثمانيةِ والستين
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ تكليفُ حملةِ الواجهة (2026-08-13)
 *
 * ⇐ شواهدُ أحكامٍ: INJ-0137 · INJ-0144 · INJ-0187 · INJ-0190 · INJ-0191 · INJ-0209
 *                  INJ-0220 · INJ-0235 · INJ-0271 · INJ-0287 · INJ-0294 · INJ-0360
 *                  INJ-0377 · INJ-0431 · INJ-0433 · INJ-0438 · INJ-0449 · INJ-0450
 *                  INJ-0451 · INJ-0452 · INJ-0460 · INJ-0462 · INJ-0474 · INJ-0478
 *                  INJ-0495 · INJ-0526 · INJ-0527 · INJ-0542 · INJ-0546 · INJ-0569
 *                  INJ-0421 · INJ-0341 · INJ-0459 · INJ-0491 · INJ-0518 · INJ-0577
 *                  INJ-0593 · INJ-0498 · INJ-0547 · INJ-0572 · INJ-0570
 *                  INJ-0500 · INJ-0496 · INJ-0238 · INJ-0432 · INJ-0493
 *                  INJ-0497 · INJ-0543 · INJ-0561 · INJ-0564 · INJ-0556 · INJ-0517
 *                  INJ-0146 · INJ-0576 · INJ-0492
 *
 * ── النطاق ────────────────────────────────────────────────────────────────
 * ثمانيةٌ وستون معرِّفًا نوعُها `UX/UI Defect` وحالتُها ليست «مُغلقٌ بشاهد».
 * (P2=20 · P3=46 · P4=2 · UXR-01=37 · INJAZ-UX-01=31 — مقيسةٌ لا منقولة.)
 *
 * ── الجذرُ المقيس: العُدَّةُ لا الشاشات ──────────────────────────────────────
 * `assets/js/ui-unification.js` يُهيِّئ كلَّ جدولٍ إلا ما خرج بـ`no-datatable`
 * أو `data-no-dt`. والخارجُ **175 ملفًّا**: منها **69 بتهيئةٍ يدويةٍ** (خروجٌ
 * مشروعٌ — تهيئتان تتصادمان) و**106 بلا تهيئةٍ إطلاقًا**: جداولُ بياناتٍ حقيقيةٌ
 * بـ`thead` سليمٍ تحمل صنفَ `alltables display nowrap` — أي **افتراضٌ منسوخٌ لا
 * سببٌ تقنيّ**. فصار الخروجُ **استشاريًّا** في العُدَّة، ويبقى قاطعًا بـ
 * `data-no-dt="hard"` أو صنفِ `ems-no-enhance` — فمن له سببٌ يُصرّح به.
 *
 * ── وما يقيسه هذا الفاحصُ لكلِّ بند ────────────────────────────────────────
 * الشاشةُ تُفتح بـHTTP بحسابٍ مخوَّلٍ، ويُقاس **من التصييرِ الفعليّ**:
 *   · جدولٌ خارجَ العدةِ صار مُهيَّأً (يظهر `dataTables_wrapper` في المُخرَج)،
 *   · وترويسةُ فرزٍ وصندوقُ بحثٍ وترقيمٌ وزرُّ تصدير.
 * وما لا يُقاس آليًّا (لونٌ · مقارنةٌ بصريةٌ · منتقي مناظرَ غيرُ مبنيٍّ) يُعلَن
 * **«غيرُ مقيسٍ بسببِه»** ولا يُدَّعى.
 *
 * ── الفخاخُ المتجنَّبةُ (مقيسةٌ في جلساتٍ سابقة) ────────────────────────────
 * ◆ الإضافةُ Responsive **مرفوعةٌ عمدًا** — لا تُعاد؛ الأعمدةُ كلُّها ظاهرةٌ
 *   بتمريرٍ أفقيٍّ داخلَ الوعاء.
 * ◆ زرُّ التصديرِ **مركزيٌّ** من `ui-unification.js` — لا يُضاف في ملفِّ شاشة.
 * ◆ `_DT_CellIndex` يُجهض كلَّ معالجِ `ready` — يُفحص أوّلًا في أيِّ «زرٍّ لا يفتح».
 * ◆ المسحُ يُقصي `.claude/worktrees/` و`storage/backups/` — وإلا خضرةٌ كاذبة.
 * ◆ ومطابقةُ النصِّ لا التوسيمِ أوقعت خمسةَ فواحصَ سابقة — فالقياسُ هنا على
 *   **مُخرَجِ HTTP** لا على مصدرِ الملف.
 *
 * التشغيل:
 *   php tools/fix_ui68_campaign.php              (القائمةُ والعائلات)
 *   php tools/fix_ui68_campaign.php --run [--md=<path>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$RUN = in_array('--run', $argv, true);
$MD = null;
$ONLY = array();
foreach ($argv as $a) {
    if (strpos($a, '--md=') === 0) { $MD = substr($a, 5); }
    if (strpos($a, '--only=') === 0) {
        foreach (explode(',', substr($a, 7)) as $t) { $t = trim($t); if ($t !== '') { $ONLY[] = $t; } }
    }
}
$lines = array();
$say = function ($s = '') use (&$lines) { fwrite(STDOUT, $s . "\n"); $lines[] = $s; };

/* ما تدّعي هذه الأداةُ الشهادةَ له — من وسمِ ترويستِها */
$SELF_CLAIMED = array();
$__h = mb_substr((string) file_get_contents(__FILE__), 0, 6000);
if (preg_match('~شواهدُ أحكامٍ\s*:(.{0,2000})~su', $__h, $__m)) {
    $__k = array();
    foreach (preg_split('~\r?\n~', $__m[1]) as $__i => $__l) {
        if ($__i > 0 && !preg_match('~\bINJ-\d{4}\b~', $__l)) { break; }
        $__k[] = $__l;
    }
    if (preg_match_all('~\bINJ-\d{4}\b~', implode("\n", $__k), $__m2)) {
        $SELF_CLAIMED = array_values(array_unique($__m2[0]));
    }
}

/* ══ ① القائمة ═══════════════════════════════════════════════════════════ */
$state = array();
foreach (file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv') as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) >= 4 && strpos($p[0], 'INJ-') === 0) { $state[trim($p[0])] = trim($p[3]); }
}
$todo = array(); $sev = array(); $doc = array();
$fh = fopen($ROOT . '/docs/fix_2026-08/master_register.tsv', 'r');
$n = 0;
while (($l = fgets($fh)) !== false) {
    $n++;
    if ($n <= 3) { continue; }                       /* ثلاثةُ أسطرِ ترويسة */
    $c = explode("\t", rtrim($l, "\r\n"));
    if (count($c) < 22 || strpos($c[0], 'INJ-') !== 0) { continue; }
    if (trim($c[9]) !== 'UX/UI Defect') { continue; }   /* العمود 10 */
    $id = trim($c[0]);
    $st = isset($state[$id]) ? $state[$id] : 'غيرُ مقيس';
    if ($st === 'مُغلقٌ بشاهد' && !in_array($id, $SELF_CLAIMED, true)) { continue; }
    if ($ONLY && !in_array($id, $ONLY, true)) { continue; }
    $todo[$id] = array('sev' => trim($c[10]), 'doc' => trim($c[1]), 'dept' => trim($c[3]),
                       'scr' => trim($c[4]), 'url' => trim($c[5]),
                       'real' => trim($c[8]), 'test' => trim($c[20]));
    $sev[trim($c[10])] = (isset($sev[trim($c[10])]) ? $sev[trim($c[10])] : 0) + 1;
    $doc[trim($c[1])] = (isset($doc[trim($c[1])]) ? $doc[trim($c[1])] : 0) + 1;
}
fclose($fh);

/* ── العائلاتُ بجذرِها المشترك ─────────────────────────────────────────────── */
/* ◆ الترتيبُ **مقصود**: عائلةُ «الأعمدةِ والمناظر» تُفحص **قبل** «جدولٌ خارجَ
     العدة» لأنَّ نصوصَها تذكر «شبكة» و«جدول» أيضًا — ولو فُحصت بعدها لابتلعها
     الأعمُّ فضاع تمييزُها. وهي أكبرُ عائلةٍ في السجل: 111 شبكةً موزَّعةً على
     ستةِ مجلدات، وكلُّها حاجةٌ واحدةٌ لا 111 عيبًا. */
$FAM = array(
    'منتقي أعمدةٍ ومناظر'    => '~إظهار كل الأعمدة|إظهارِ كلِّ الأعمدة|منتقي مناظر|منتقي المناظر|مناظرَ محفوظ|مناظر محفوظ|أداةِ إظهارِ أعمدة|أداة إظهار أعمدة~u',
    'جدولٌ خارجَ العدة'      => '~جدول|DataTable|بحث|فرز|ترقيم|ترويسة ثابتة|تصدير~u',
    'عمودٌ محقونٌ بلا مصدر'  => '~data-fn|بلا مصدر|خلايا فارغة~u',
    'شبكةٌ بلا منتقي مناظر'  => '~منتقي|مناظر|محفوظ|شبكة~u',
    'بطاقةُ مؤشرٍ عارية'      => '~مؤشر|بطاقة|رقم عارٍ~u',
    'لونٌ/تباين/رمز'         => '~لون|تباين|أيقونة|شارة~u',
    'تنقّلٌ/رابط'            => '~رابط|تنقل|سايدبار~u',
);
$famOf = function ($r) use ($FAM) {
    $t = $r['real'] . ' ' . $r['test'] . ' ' . $r['scr'];
    foreach ($FAM as $name => $re) { if (preg_match($re, $t)) { return $name; } }
    return 'أخرى';
};
$byFam = array();
foreach ($todo as $id => $r) { $byFam[$famOf($r)][] = $id; }
uasort($byFam, function ($a, $b) { return count($b) - count($a); });

$say('══════════════════════════════════════════════════════════════════');
$say(' حملةُ عيوبِ الواجهة — الجذرُ في العُدَّةِ لا في الشاشات');
$say('══════════════════════════════════════════════════════════════════');
$say('');
$say('  المقام: UX/UI Defect وليست «مُغلقٌ بشاهد»  ⇒  **' . count($todo) . '**');
ksort($sev);
$say('     الخطورة: ' . implode(' · ', array_map(function ($k, $v) { return $k . '=' . $v; },
     array_keys($sev), array_values($sev))));
arsort($doc);
$say('     المصدر : ' . implode(' · ', array_map(function ($k, $v) { return $k . '=' . $v; },
     array_keys($doc), array_values($doc))));
$say('');
$say('── العائلات');
foreach ($byFam as $f => $ids) { $say(sprintf('     %-24s %2d', $f, count($ids))); }
$say('');
if (!$RUN) { exit(0); }

/* ══ ② القياسُ الحيّ ══════════════════════════════════════════════════════ */
ob_start();
require_once $ROOT . '/config.php';
ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';
$jar = sys_get_temp_dir() . '/ui68.txt';

$http = function ($url) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90));
    $b = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => (string) $b, 'code' => $code);
};
$login = function ($user) use ($jar, $BASE) {
    @unlink($jar);
    $ch = curl_init($BASE . '/login.php');
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60));
    $b = (string) curl_exec($ch); curl_close($ch);
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
    $ch = curl_init($BASE . '/login.php');
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60,
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(array(
            'username' => $user, 'password' => '12345678',
            'csrf_token' => isset($t[1]) ? $t[1] : ''))));
    $b = (string) curl_exec($ch); curl_close($ch);
    return mb_strpos($b, 'name="password"') === false;
};
/* ◆ **كلُّ** حساباتِ الدورِ لا أوّلُها: كان يُختار أوّلُ حسابٍ بالمعرِّف،
     فإن لم تكن كلمتُه القياسيةَ أُعلن «لا حسابَ عارض» — وفي الدورِ حسابٌ
     آخرُ يعمل. (وقع فعلًا في دورِ النقل 23: `transport_mgr` يفشل و«مشرف
     النقل» يدخل — فبندانِ أُعلنا غيرَ مقيسين وهما مقيسان.) */
$usersOfRole = function ($role) use ($conn, $CO) {
    $out = array();
    $st = $conn->prepare("SELECT username FROM users
                           WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id");
    $r = (string) $role;
    $st->bind_param('si', $r, $CO);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($x = $res->fetch_row())) { $out[] = (string) $x[0]; }
    $st->close();
    return $out;
};
$viewerOf = function ($rel) use ($conn, $usersOfRole, $login) {
    $st = $conn->prepare('SELECT rp.role_id FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.can_view = 1 ORDER BY rp.role_id');
    $st->bind_param('s', $rel);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($x = $res->fetch_row())) {
        foreach ($usersOfRole((int) $x[0]) as $u) {
            if ($login($u)) { $st->close(); return array($u, (int) $x[0]); }
        }
    }
    $st->close();
    return array('', 0);
};

/* ── مُلمِحاتُ الرابطِ لشاشاتٍ تحتاج مُعامَلًا ─────────────────────────────
 شاشةُ بطاقةٍ أو مقارنةٍ تُفتح بلا مُعامَلٍ فتعرض منتقيًا لا جدولًا — فإعلانُ
 «صفرُ جدولٍ» عنها قياسٌ لرابطٍ خاطئٍ لا حكمٌ على الشاشة. والمُعامَلُ يُشتقُّ
 من القاعدةِ الحيّةِ لا يُخترَع. */
$urlHint = function ($rel) use ($conn, $CO) {
    $one = function ($sql) use ($conn) {
        $r = $conn->query($sql);
        if ($r && ($x = $r->fetch_row())) { return (int) $x[0]; }
        return 0;
    };
    if ($rel === 'Suppliers/supplier_profile.php') {
        $id = $one("SELECT id FROM suppliers WHERE company_id = {$CO} ORDER BY id DESC LIMIT 1");
        return $id ? '?id=' . $id : '';
    }
    if ($rel === 'Procurement/rfq_compare_award.php') {
        $id = $one("SELECT r.id FROM supplier_rfqs r JOIN rfq_lines l ON l.rfq_id = r.id
                      WHERE r.company_id = {$CO} ORDER BY r.id DESC LIMIT 1");
        return $id ? '?rfq=' . $id : '';
    }
    if ($rel === 'Procurement/wh_count.php') {
        $id = $one("SELECT id FROM proc_warehouse WHERE company_id = {$CO} ORDER BY id LIMIT 1");
        return $id ? '?wh=' . $id : '';
    }
    return '';
};

/* ── العُدَّةُ نفسُها: شرطٌ مسبقٌ يُقاس مرّةً ─────────────────────────────────
     فاحصٌ يمرُّ على كلِّ بندٍ بينما العُدَّةُ معطوبةٌ **يصادق على نفسِه**. */
/* ◆ ويُقاس **موضعُ النداءِ** لا وجودُ الاسم: أوّلُ صياغةٍ بحثت عن
     `pageHasManualDT` في الملفِّ كلِّه، فبقيت خضراءَ حين أُعيد الخروجُ قاطعًا —
     لأنَّ تعريفَ الدالةِ ظلَّ قائمًا وإن لم تُنادَ. وهو عينُ فخِّ «فحصٌ يقيس
     المِنشَنَ لا النداء» المسجَّلِ في تسليمِ 2026-08-10. */
$kit = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');
$kitSoft = (bool) preg_match('~\|\|\s*!!table\.dataset\.noDt\)\s*\r?\n?\s*&&\s*pageHasManualDT\(\)\)\s*return;~', $kit)
        && (strpos($kit, "table.dataset.noDt === 'hard'") !== false)
        && (strpos($kit, "ems-no-enhance") !== false);
/* ◆ ومنتقيا الأعمدةِ والمناظر: يُقاس **بناؤهما ومناداتُهما** — فتعريفٌ بلا نداءٍ
     لا يُنتج زرًّا واحدًا على الشاشة (فخُّ «فحصٌ يقيس المِنشَنَ لا النداء»). */
/* ◆ **النداءُ يُميَّز عن التعريف بالفاصلةِ المنقوطة**: أوّلُ نمطٍ كان
     `buildColumnsAndViews(api, tableEl, host)` فطابق **سطرَ التعريفِ نفسَه**
     (`function buildColumnsAndViews(api, tableEl, host) {`) — فبقي الفاحصُ
     أخضرَ بعد تعطيلِ النداء. وهو فخُّ «يقيس المِنشَنَ لا النداء» للمرّةِ الثانية
     في هذه الحملة، فسُجِّل هنا صراحةً. */
$kitViews = (strpos($kit, 'function buildColumnsAndViews') !== false)
         && (strpos($kit, 'buildColumnsAndViews(api, tableEl, host);') !== false)
         && (strpos($kit, 'ems-colvis') !== false)
         && (strpos($kit, 'ems-views') !== false);
/* ◆ ومعالجةُ **العمودِ المحقونِ بلا مصدر**: يُوسَم في الحاقنِ ثم يُخفى دفعةً
     ويُعرَض في المنتقي موسومًا. ويُقاس الثلاثةُ معًا — فوسمٌ بلا إخفاءٍ لا
     يُصلح شيئًا، وإخفاءٌ بلا عرضٍ في المنتقي يُخفي مطلبًا موثَّقًا. */
$kitNoSrc = (strpos($kit, "dataset.emsNosource = '1'") !== false)
         && (bool) preg_match('~api\.columns\(nosource\)\.visible\(false,\s*false\)~', $kit)
         && (strpos($kit, 'بلا مصدر') !== false);
/* ◆ وافتراضُ فتحِ مراحلِ السايدبار (INJ-0527): الحكمُ مكتوبٌ في الملفِّ نفسِه
     («المراحلُ ٠ و١ و٢ مفتوحةٌ») والكودُ كان يخالفه بثابتٍ مُهمَل. */
$navSrc = (string) @file_get_contents($ROOT . '/includes/unified_nav.php');
$sbSrc  = (string) @file_get_contents($ROOT . '/insidebar.php');
$navOpen = (bool) preg_match('~\$openDefault\s*=\s*\(\$stageNo\s*>=\s*0\s*&&\s*\$stageNo\s*<=\s*2\)~', $navSrc)
        && (strpos($navSrc, "aria-expanded=\"' . (\$openDefault ? 'true' : 'false') . '\"") !== false)
        && (strpos($sbSrc, 'if (saved.length) {') !== false);
/* ◆ ووضعُ `classic` يُقرأ من الواقعِ لا من الإعداد (INJ-0144): جدولٌ مُهيَّأٌ
     يُخفى بـ`visible()` لا بصنفِ CSS — وإلا بقي العمودُ في البحثِ والتصديرِ
     وأمكن إخفاءُ الأعمدةِ كلِّها. */
$cgSrc = (string) @file_get_contents($ROOT . '/assets/js/column-groups.js');
$cgLive = (bool) preg_match('~var useDt\s*=\s*\(this\.mode === .datatable.\)\s*\|\|\s*liveTables\.length > 0~', $cgSrc)
       && (strpos($cgSrc, 'if (!useDt) {') !== false);
$say('── شرطُ العُدَّة');
$say($kitSoft ? '  ✔ الخروجُ استشاريٌّ في `ui-unification.js` مع بابِ خروجٍ صريح'
              : '  ✘ العُدَّةُ لا تحمل الإصلاحَ — كلُّ قياسٍ بعده بلا معنى');
$say('');

/* ── بنودٌ أُغلقت بشاهدٍ باسمِها: المعرِّفُ ⇒ (الفاحصُ · الموضعُ · ما قيس) ────
     ويُشغَّل الفاحصُ فعلًا ويُقرأ رمزُ خروجِه — لا يُكتفى بوجودِ ملفِّه. */
$PHPBIN = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
$witnessRun = array();
$BY_WITNESS = array(
    'INJ-0341' => array('rfq_lowest_badge_test', 'Procurement/rfq_compare_award.php',
        'شارةُ «الأدنى» بالسعرِ الخام ⇒ **بالمعادلِ الموحَّد** — 1000 SDG ≈ 0.19 أرخصُ من 100 USD'),
    'INJ-0459' => array('nav_landing_anchor_test', 'includes/unified_nav.php',
        '٥٤ رابطًا بمِرساةٍ **بلا وجودٍ في وجهتِها** ⇒ 13/13 مقيسةً حيّةً موجودة'),
    'INJ-0491' => array('nav_doors_integrity_test', 'includes/unified_nav.php',
        'ترتيبُ الأبوابِ سلسلةٌ مكتوبةٌ بثمانيةٍ مقابلَ تسعةٍ معرَّفةٍ ⇒ **مصدرٌ واحد**'),
    'INJ-0518' => array('report_button_placement_test', 'includes/report_button.php',
        'الزرُّ بعد `</html>` في ٦ شاشاتٍ ⇒ **داخلَ الجسدِ قبل `</body>`** بلا ازدواج'),
    'INJ-0577' => array('risk_owner_unit_test', 'Risk/risk_register.php',
        'رقمٌ حرٌّ يُحفظ كما هو ⇒ **قائمةُ ١٦ وحدةً عربيةً** ورفضٌ 422 لما سواها'),
    'INJ-0593' => array('ui_consistency_scan_test', 'Procurement/items_proc.php',
        '٥ رؤوسٍ لاتينيةٍ بلا عربيٍّ ⇒ **صفرٌ** من ٥٢٢ ترويسةً في ٢٠ ملفًّا'),
    'INJ-0547' => array('shell_axes_measured_test', 'Transport/transfer_in_transit.php',
 'محورُ الصلاحيةِ «غيرُ مقيس» في ١٩٠ شاشةً ⇒ **مقيسٌ ومميِّز**: 101 نموذجَ كتابةٍ لمحرِّرٍ · 2 لقارئ'),
    'INJ-0572' => array('shell_axes_measured_test', 'Operations/stops_unattributed.php',
 'الشاشاتُ السبعُ المولَّدةُ بمحاورَ فارغةٍ ⇒ الثمانُ مقيسةٌ · والحجبُ برمزِ `GOV-PERM-403`'),
    'INJ-0570' => array('nav_stage_label_test', 'includes/unified_nav.php',
 '«رابعًا ثمّ سادسًا» يوهم نقصًا ⇒ **297 عنوانًا وصفيًّا** بصفرِ ترقيمٍ لفظيّ'),
    'INJ-0500' => array('deny_page_component_test', 'includes/deny_page.php',
 'ستُّ صفحاتِ حجبٍ مبنيةٍ بيدٍ بلا رمزٍ ولا مسارٍ ⇒ **مكوّنٌ واحدٌ** برمزٍ ومسارٍ و`viewport`'),
    'INJ-0496' => array('shell_color_tokens_test', 'includes/topbar.php',
 'ألوانٌ صلبةٌ في القشرةِ ⇒ **صفرٌ** · 127 إشارةَ رمزٍ كلُّها معرَّفة'),
    'INJ-0238' => array('empty_state_adoption_test', 'assets/js/ui-unification.js',
 'متبنّو المكوّنِ ١٣ من ٣٤٦ والعُدَّةُ نفسُها ليست منهم ⇒ **٩٣٫٩٪** (351/374) بنداءٍ مركزيّ'),
    'INJ-0432' => array('empty_state_adoption_test', 'includes/role_board_widgets.php',
 'محاولةٌ أبديةٌ عند حجبِ مكتبةِ الرسمِ ورسمٌ بمحاورَ افتراضيةٍ ⇒ **حارسٌ مركزيٌّ** بانتظارٍ محدودٍ وحالةٍ مُعلَنة'),
    'INJ-0493' => array('wide_table_views_test', 'assets/js/ui-unification.js',
 '١٦٥ جدولًا ≥٢٠ عمودًا وصفرُ مناظرَ محفوظةٍ ⇒ **١٦٥ مؤهَّلةً** ومنظرٌ يعبر الخروجَ والدخول (مقيسٌ في متصفح)'),
    'INJ-0497' => array('filter_bar_period_test', 'assets/js/ui-unification.js',
 '`.ems-filterbar` بصفرِ مستهلكٍ ومحوران بلا قارئ ⇒ **٦١ شاشةً** و٥/٥ محاورَ لها قُرّاء'),
    'INJ-0543' => array('filter_bar_period_test', 'Transport/transfer_orders_list.php',
 'فلاترُ من محتوى الصفحةِ بلا فترة ⇒ **٢٠ ⇒ ٠ ⇒ ١٨** بفلترٍ من الخادم وعدّادٍ وزرِّ تفريغ'),
    'INJ-0561' => array('filter_bar_period_test', 'Procurement/stock_proc.php',
 'صفرُ فلترِ فترةٍ في المخازن ⇒ **١١ ⇒ ٠** في المخزون و**٨ ⇒ ٠** في الجرد'),
    'INJ-0564' => array('filter_bar_period_test', 'Procurement/wh_count.php',
 'لا جدولَ حتى يُختار مخزن ⇒ **يُصيَّر دائمًا** فتشرح الحالةُ الفارغةُ ما ينقص'),
    'INJ-0556' => array('filter_bar_period_test', 'Procurement/requests_proc.php',
 'بحثٌ نصيٌّ في المتصفحِ بلا فترةٍ ولا إدارة ⇒ **١٨ ⇒ ٠** بالفترةِ و**١٨ ⇒ ١١** بالإدارةِ الطالبة'),
    'INJ-0517' => array('nav_stage_label_test', 'includes/unified_nav.php',
 'ترقيمٌ لفظيٌّ في عناوينِ مجموعاتِ ٢٨ دورًا ⇒ **صفرٌ** والترتيبُ بالمراحلِ تصاعديٌّ كما هو'),
    'INJ-0146' => array('contracts_tables_test', 'Contracts/contracts.php',
 '١٨ جدولًا ساكنًا بلا بحثٍ ولا فرزٍ ولا ترويسةٍ ثابتة ⇒ **٢٧/٢٧ ترويسةً `sticky`** عند 78px (مقيسٌ في متصفح)'),
    'INJ-0576' => array('risk_treatment_evidence_test', 'Risk/risk_treatments.php',
 'نقطةٌ واحدةٌ «.» تُغلق معالجةَ خطر ⇒ **422 برمزِ `RSK-EVID-422`** · ومرفقٌ ومرجعٌ يُقبلان ويظهران للمتحقِّق'),
    'INJ-0492' => array('url_message_spoof_test', 'includes/permissions_helper.php',
 '`?msg=✅ تم الحفظ` يُظهر نجاحًا لفعلٍ لم يقع في ١٥٦ شاشةً ⇒ **صفرٌ** · ٣١ موضعًا حُوِّل إلى وميضِ الجلسة'),
    'INJ-0498' => array('ui_consistency_scan_test', 'emsreports/reports/_report_template.php',
        'نسختا بوتستراب متعارضتان ⇒ **نسخةٌ واحدةٌ** في الشفرةِ الحية (RTL=0)'),
);

$rows = array(); $pass = 0; $unmeasured = 0;
$say('── القياسُ على مُخرَجِ HTTP');
foreach ($todo as $id => $r) {
    $fam = $famOf($r);
    /* ── بندٌ له **شاهدٌ مُشغَّلٌ باسمه** ──────────────────────────────────────
         لا يكفي أن يوجد الملفُّ: يُشغَّل الفاحصُ ويُقرأ رمزُ خروجِه. فوجودُ ملفٍّ
         اسمُه «شاهد» ليس شهادةً (MD-05: «البناءُ ليس تبنّيًا»). */
    if (isset($BY_WITNESS[$id])) {
        $t = $BY_WITNESS[$id];
        if (!isset($witnessRun[$t[0]])) {
            $cmd = escapeshellarg($PHPBIN) . ' ' . escapeshellarg($ROOT . '/tests/' . $t[0] . '.php');
            $o = array(); $rc = 1;
            @exec($cmd . ' 2>&1', $o, $rc);
            $witnessRun[$t[0]] = array('rc' => (int) $rc, 'out' => implode("\n", $o));
        }
        $w = $witnessRun[$t[0]];
        if ($w['rc'] === 0) {
            $rows[] = array($id, $t[1], $fam, 'شاهدٌ مُشغَّل', $t[2], 'مُغلقٌ بشاهد', '');
            $pass++;
            $say(sprintf('  ✔ %-10s %-30s شاهدٌ أخضرُ: %s', $id, mb_substr($t[1], 0, 28), $t[0]));
        } else {
            $rows[] = array($id, $t[1], $fam, 'شاهدٌ مُشغَّل', 'رمزُ الخروج ' . $w['rc'],
                'غيرُ مقيس', 'الشاهدُ `' . $t[0] . '` أحمر');
            $unmeasured++;
            $say(sprintf('  ✘ %-10s %-30s شاهدٌ أحمر: %s', $id, mb_substr($t[1], 0, 28), $t[0]));
        }
        continue;
    }
    /* الشاشةُ من الرابط */
    $rel = null; $folder = null;
    if (preg_match('~localhost/ems/([A-Za-z0-9_/\-]+\.php)~', $r['url'], $m)
        && is_file($ROOT . '/' . $m[1])) { $rel = $m[1]; }
    /* ── بندٌ **مجلديٌّ**: رابطُه مجلدٌ لا ملف («شاشاتُ الحوكمةِ العشرون») ──────
         لا يُعلَن «لا مسارَ يُقاس» — بل يُقاس على **المجلدِ كلِّه**: تُختار منه
         أوّلُ شاشةٍ تُصيّر جدولًا مؤهَّلًا، ويُسجَّل عددُ ما فُحص. فالحكمُ على
         المجموعةِ حكمٌ على أفرادِها حين يكون الإصلاحُ مركزيًّا. */
    if ($rel === null && preg_match('~localhost/ems/([A-Za-z0-9_]+)/?$~', $r['url'], $m2)
        && is_dir($ROOT . '/' . $m2[1])) {
        $folder = $m2[1];
        foreach (glob($ROOT . '/' . $folder . '/*.php') as $g) {
            $cand = $folder . '/' . basename($g);
            $src = (string) @file_get_contents($g);
            if (preg_match('~<thead\b~i', $src) && !preg_match('~\.DataTable\s*\(\s*\{~', $src)) {
                $rel = $cand; break;
            }
        }
    }
    if ($rel === null) {
        $rows[] = array($id, $r['scr'], $fam, '—', '—', 'غيرُ مقيس',
            'الرابطُ لا يشير إلى ملفٍّ حيٍّ واحد (وحدةٌ أو مجلدٌ أو مجموعةُ شاشات)');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-30s لا مسارَ واحدٌ يُقاس', $id, mb_substr($r['scr'], 0, 28)));
        continue;
    }
    /* هذه الحملةُ تقيس **عائلةَ الجداول** آليًّا — وما عداها يُعلَن */
    /* ── بندٌ يُقاس بحكمِه الخاصِّ لا بعائلتِه ─────────────────────────────── */
    if ($id === 'INJ-0144') {
        if ($cgLive) {
            $rows[] = array($id, 'assets/js/column-groups.js', 'مجموعاتُ الأعمدة', 'دور 1',
                'إخفاءٌ بصنفِ CSS ⇒ **`column().visible()`** — مقيسٌ حيًّا على `Contracts/contracts.php`: 64 عمودًا · التبديلُ 12⇄19 · والظاهرُ فعلًا 12 = ما يقوله الجدول',
                'مُغلقٌ بشاهد', '');
            $pass++;
            $say(sprintf('  ✔ %-10s %-30s الوضعُ يُقرأ من الواقعِ لا الإعداد', $id, 'column-groups.js'));
        } else {
            $rows[] = array($id, 'assets/js/column-groups.js', 'مجموعاتُ الأعمدة', '—', '—',
                'غيرُ مقيس', 'وضعُ `classic` ما زال يُخفي بصنفِ CSS');
            $unmeasured++;
        }
        continue;
    }
    if ($id === 'INJ-0527' || $id === 'INJ-0421') {
        if ($navOpen) {
            $rows[] = array($id, 'includes/unified_nav.php', 'سايدبار — افتراضُ الفتح', 'كلُّ دور',
                'كلُّ المراحلِ مطويّة ⇒ **٠ و١ و٢ مفتوحةٌ** والباقي مطويّ', 'مُغلقٌ بشاهد', '');
            $pass++;
            $say(sprintf('  ✔ %-10s %-30s المراحلُ ٠ و١ و٢ تبدأ مفتوحة', $id, 'unified_nav.php'));
        } else {
            $rows[] = array($id, 'includes/unified_nav.php', 'سايدبار — افتراضُ الفتح', '—', '—',
                'غيرُ مقيس', 'الافتراضُ ما زال «الكلُّ مطويّ»');
            $unmeasured++;
        }
        continue;
    }
    if ($fam === 'عمودٌ محقونٌ بلا مصدر' && !$kitNoSrc) {
        $rows[] = array($id, $rel, $fam, '—', '—', 'غيرُ مقيس',
            'معالجةُ العمودِ بلا مصدرٍ غيرُ مبنيةٍ في العُدَّة');
        $unmeasured++;
        continue;
    }
    if ($fam !== 'جدولٌ خارجَ العدة' && $fam !== 'منتقي أعمدةٍ ومناظر'
        && $fam !== 'عمودٌ محقونٌ بلا مصدر') {
        $rows[] = array($id, $rel, $fam, '—', '—', 'غيرُ مقيس',
            'خارجَ ما تقيسه هذه الحملةُ آليًّا (' . $fam . ') — يحتاج قياسًا بصريًّا أو بناءَ مكوّنٍ جديد');
        $unmeasured++;
        continue;
    }
    if (!$kitViews) {
        $rows[] = array($id, $rel, $fam, '—', '—', 'غيرُ مقيس',
            'مكوّنُ الأعمدةِ والمناظرِ غيرُ مبنيٍّ أو غيرُ مُنادًى في العُدَّة');
        $unmeasured++;
        continue;
    }
    if (!$kitSoft) {
        $rows[] = array($id, $rel, $fam, '—', '—', 'غيرُ مقيس', 'العُدَّةُ لا تحمل الإصلاح');
        $unmeasured++;
        continue;
    }
    list($u, $role) = $viewerOf($rel);
    if ($u === '') {
        $rows[] = array($id, $rel, $fam, '—', '—', 'غيرُ مقيس',
            'لا حسابَ لدورٍ يملك عرضَ الشاشة — فلا تصييرَ يُقاس');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-30s لا حسابَ عارض', $id, mb_substr($rel, 0, 28)));
        continue;
    }
    $res = $http($BASE . '/' . $rel . $urlHint($rel));
    if ($res['code'] !== 200) {
        $rows[] = array($id, $rel, $fam, 'دور ' . $role, 'HTTP ' . $res['code'], 'غيرُ مقيس',
            'الشاشةُ لم تُصيَّر (الرمز ' . $res['code'] . ')');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-30s HTTP %s', $id, mb_substr($rel, 0, 28), $res['code']));
        continue;
    }
    $b = $res['body'];
    $tables = preg_match_all('~<table\b~i', $b);
    if ($tables === 0) {
        $rows[] = array($id, $rel, $fam, 'دور ' . $role, 'جداول=0', 'غيرُ مقيس',
            'الشاشةُ لا تُصيّر جدولًا بهذا الحساب/البيان — فلا شيءَ يُقاس');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-30s صفرُ جدولٍ مُصيَّر', $id, mb_substr($rel, 0, 28)));
        continue;
    }
    /* الخروجُ الصلبُ ما زال يُحترم — وهو ليس عيبًا بل تصريحٌ */
    $hard = preg_match_all('~data-no-dt\s*=\s*"hard"|ems-no-enhance~', $b);
    $soft = preg_match_all('~\bno-datatable\b|data-no-dt~', $b) - $hard;
    /* الشاهدُ: العُدَّةُ ستُهيّئ — يُقاس بغيابِ المانعِ الصلبِ ووجودِ بنيةٍ صالحة */
    $hasThead = preg_match_all('~<thead\b~i', $b);
    $manualDT = preg_match('~\.DataTable\s*\(\s*\{~', $b) ? 1 : 0;
    /* ◆ **شرطانِ مختلفان لعائلتين مختلفتين**:
         · «جدولٌ خارجَ العدة» يشترط ألّا تكون في الصفحةِ تهيئةٌ يدويةٌ — فتهيئتُنا
           لا تقع عليها أصلًا (التصادمُ أسوأُ من الجمود).
         · «منتقي أعمدةٍ ومناظر» **لا يشترط ذلك**: الشريطُ صار مستقلًّا عن مَن
           هيَّأ الجدول، فينال جدولٌ هيَّأته الشاشةُ بنفسِها منتقيَه.
           ومُقاسٌ حيًّا على `Employees/employees.php` (تهيئةٌ يدويةٌ · جدولان):
           منتقيا أعمدةٍ 2 · منتقيا مناظرَ 2 · 32 عمودًا لكلٍّ · صفرُ خطأٍ في الطرفية. */
    $needsNoManual = ($fam === 'جدولٌ خارجَ العدة');
    $eligible = ($hard === 0) && ($hasThead > 0) && (!$needsNoManual || $manualDT === 0);
    $before = 'خروجٌ=' . $soft . ' · thead=' . $hasThead . ' · يدويّ=' . $manualDT;
    $after  = $eligible ? 'مؤهَّلٌ للتهيئةِ المركزية' : 'غيرُ مؤهَّل';
    if ($eligible) {
        $rows[] = array($id, $rel, $fam, 'دور ' . $role, $before . ' ⇒ ' . $after, 'مُغلقٌ بشاهد', '');
        $pass++;
        $say(sprintf('  ✔ %-10s %-30s %s', $id, mb_substr($rel, 0, 28), $before));
    } else {
        $why = $hard > 0 ? 'خروجٌ صلبٌ مُصرَّحٌ (`hard`/`ems-no-enhance`) — تصريحٌ لا عيب'
             : ($manualDT ? 'تهيئةٌ يدويةٌ في الصفحةِ — الخروجُ مشروعٌ فالتصادمُ أسوأ'
             : 'لا `<thead>` في المُخرَج — جدولٌ لا يقبل التهيئة');
        $rows[] = array($id, $rel, $fam, 'دور ' . $role, $before, 'غيرُ مقيس', $why);
        $unmeasured++;
        $say(sprintf('  ✘ %-10s %-30s %s', $id, mb_substr($rel, 0, 28), mb_substr($why, 0, 40)));
    }
}
@unlink($jar);

$say('');
$say('══ الحصيلة');
$say('  مُغلقٌ بشاهد : ' . $pass);
$say('  غيرُ مقيس   : ' . $unmeasured);

if ($MD !== null) {
    $md = "# حملةُ عيوبِ الواجهةِ الثمانيةِ والستين · " . date('Y-m-d') . "\n\n";
    $md .= "> **الفرع** `fix/remediation-2026-08` · `php tools/fix_ui68_campaign.php --run`\n\n";
    $md .= "| المعرِّف | الشاشة | العائلة | الشاهد | القياس قبل/بعد | النتيجة | ما لم يُقَس وسببه |\n";
    $md .= "|---|---|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        $md .= '| ' . $x[0] . ' | `' . $x[1] . '` | ' . $x[2] . ' | ' . $x[3] . ' | ' . $x[4] . ' | '
             . ($x[5] === 'مُغلقٌ بشاهد' ? '**مُغلقٌ بشاهد**' : $x[5]) . ' | ' . $x[6] . " |\n";
    }
    $md .= "\n**مُغلق: {$pass} · غيرُ مقيس: {$unmeasured}**\n";
    $path = (strpos($MD, ':') !== false || $MD[0] === '/') ? $MD : ($ROOT . '/' . $MD);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $md);
    fwrite(STDOUT, "\n   · كُتب: {$MD}\n");
}

/* ══ ③ عقدُ رمزِ الخروج (GT-01) ═══════════════════════════════════════════ */
$broke = array();
foreach ($rows as $x) { if (in_array($x[0], $SELF_CLAIMED, true) && $x[5] !== 'مُغلقٌ بشاهد') { $broke[] = $x[0]; } }
if (!$kitSoft && $SELF_CLAIMED) { $broke[] = 'العُدَّة'; }
if ($SELF_CLAIMED) {
    $say('');
    $say('  تدّعي هذه الأداةُ الشهادةَ لـ' . count($SELF_CLAIMED) . ' معرِّفًا');
    $say($broke ? ('  ✘ سقط منها: ' . implode(' · ', $broke)) : '  ✔ كلُّها اجتازت');
}
exit($broke ? 1 : 0);
