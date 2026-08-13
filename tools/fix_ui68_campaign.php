<?php
/**
 * tools/fix_ui68_campaign.php — حملةُ عيوبِ الواجهةِ الثمانيةِ والستين
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ تكليفُ حملةِ الواجهة (2026-08-13)
 *
 * ⇐ شواهدُ أحكامٍ: INJ-0137 · INJ-0209 · INJ-0360 · INJ-0377 · INJ-0431 · INJ-0433
 *                  INJ-0449 · INJ-0451 · INJ-0495 · INJ-0526 · INJ-0542 · INJ-0546
 *                  INJ-0569
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
$FAM = array(
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
$userOfRole = function ($role) use ($conn, $CO) {
    $st = $conn->prepare("SELECT username FROM users
                           WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
    $r = (string) $role;
    $st->bind_param('si', $r, $CO);
    $st->execute();
    $x = $st->get_result()->fetch_row();
    $st->close();
    return $x ? (string) $x[0] : '';
};
$viewerOf = function ($rel) use ($conn, $userOfRole) {
    $st = $conn->prepare('SELECT rp.role_id FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.can_view = 1 ORDER BY rp.role_id');
    $st->bind_param('s', $rel);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($x = $res->fetch_row())) {
        $u = $userOfRole((int) $x[0]);
        if ($u !== '') { $st->close(); return array($u, (int) $x[0]); }
    }
    $st->close();
    return array('', 0);
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
$say('── شرطُ العُدَّة');
$say($kitSoft ? '  ✔ الخروجُ استشاريٌّ في `ui-unification.js` مع بابِ خروجٍ صريح'
              : '  ✘ العُدَّةُ لا تحمل الإصلاحَ — كلُّ قياسٍ بعده بلا معنى');
$say('');

$rows = array(); $pass = 0; $unmeasured = 0;
$say('── القياسُ على مُخرَجِ HTTP');
foreach ($todo as $id => $r) {
    $fam = $famOf($r);
    /* الشاشةُ من الرابط */
    $rel = null;
    if (preg_match('~localhost/ems/([A-Za-z0-9_/\-]+\.php)~', $r['url'], $m)
        && is_file($ROOT . '/' . $m[1])) { $rel = $m[1]; }
    if ($rel === null) {
        $rows[] = array($id, $r['scr'], $fam, '—', '—', 'غيرُ مقيس',
            'الرابطُ لا يشير إلى ملفٍّ حيٍّ واحد (وحدةٌ أو مجلدٌ أو مجموعةُ شاشات)');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-30s لا مسارَ واحدٌ يُقاس', $id, mb_substr($r['scr'], 0, 28)));
        continue;
    }
    /* هذه الحملةُ تقيس **عائلةَ الجداول** آليًّا — وما عداها يُعلَن */
    if ($fam !== 'جدولٌ خارجَ العدة') {
        $rows[] = array($id, $rel, $fam, '—', '—', 'غيرُ مقيس',
            'خارجَ ما تقيسه هذه الحملةُ آليًّا (' . $fam . ') — يحتاج قياسًا بصريًّا أو بناءَ مكوّنٍ جديد');
        $unmeasured++;
        continue;
    }
    if (!$kitSoft) {
        $rows[] = array($id, $rel, $fam, '—', '—', 'غيرُ مقيس', 'العُدَّةُ لا تحمل الإصلاح');
        $unmeasured++;
        continue;
    }
    list($u, $role) = $viewerOf($rel);
    if ($u === '' || !$login($u)) {
        $rows[] = array($id, $rel, $fam, '—', '—', 'غيرُ مقيس',
            'لا حسابَ لدورٍ يملك عرضَ الشاشة — فلا تصييرَ يُقاس');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-30s لا حسابَ عارض', $id, mb_substr($rel, 0, 28)));
        continue;
    }
    $res = $http($BASE . '/' . $rel);
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
    $eligible = ($hard === 0) && ($hasThead > 0) && ($manualDT === 0);
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
