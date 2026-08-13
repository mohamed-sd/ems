<?php
/**
 * tools/fix_missing_screen_campaign.php — حملةُ أدلةٍ على «الشاشاتِ المفقودة»
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ تكليفُ حملةِ الأدلة (2026-08-13)
 *
 * ⇐ شواهدُ أحكامٍ: INJ-0034 · INJ-0048 · INJ-0049 · INJ-0102 · INJ-0188 · INJ-0201
 *                  INJ-0218 · INJ-0229 · INJ-0230 · INJ-0231 · INJ-0269 · INJ-0284
 *                  INJ-0293 · INJ-0321 · INJ-0350 · INJ-0357 · INJ-0392 · INJ-0393
 *                  INJ-0444 · INJ-0472 · INJ-0487 · INJ-0488 · INJ-0515 · INJ-0516
 *                  INJ-0560
 *
 * ── ما تفعله ──────────────────────────────────────────────────────────────
 * ① تشتقُّ القائمةَ: كلُّ ملاحظةٍ نوعُها `Missing Screen` **وحالتُها ليست
 *    «مُغلقٌ بشاهد»** في `docs/fix_progress/INJ_findings_state.tsv`.
 * ② تُخرج لكلٍّ **اختبارَ القبولِ المكتوبَ** (العمود 21) وشاشتَها (5) وإدارتَها.
 * ③ وتقيس ما **يمكن قياسُه آليًّا** من كلِّ اختبار: الشاشةُ تُصيَّر بدورٍ مخوَّلٍ
 *    وتُحجَب عن غيرِه — وهو الحدُّ الأدنى المشترَكُ بين هذه الاختباراتِ كلِّها.
 *
 * ── وحدودُها مُعلَنةٌ لا مسكوتٌ عنها ────────────────────────────────────────
 * اختباراتُ القبولِ **سيناريوهاتٌ وظيفيةٌ** لا فحصُ وجودِ ملف. وهذه الأداةُ
 * تقيس **الشقَّ المشترَك** منها (وصولٌ محكومٌ + تصييرٌ بمضمون)، وما زاد عليه
 * (فعلٌ كاتبٌ · سلسلةُ اعتمادٍ · رقمٌ محسوب) **يُعلَن غيرَ مقيسٍ بسببِه** ولا
 * يُدَّعى. فالبندُ لا يُغلق بقياسٍ جزئيٍّ يُقدَّم كاملًا.
 *
 * ── الفخاخُ المتجنَّبة (مقيسةٌ في جلساتٍ سابقة) ──────────────────────────────
 * ◆ `CURLOPT_FOLLOWLOCATION` يُحوّل الحجبَ (302 ⇒ لوحة) إلى **200** فيُقرأ
 *   نجاحًا — فالاتّباعُ **مُطفأ** والحكمُ على الرمزِ وعلى الجسدِ معًا.
 * ◆ `modules` **لا يحمل** عمودَ `module_path` — الربطُ بـ`code`.
 * ◆ `nav_items.route` قد يحمل بادئةَ `../` — تُجرَّد قبل أيِّ مقارنة.
 * ◆ `config.php` يبتلع مخرَجَ CLI — يُلَفُّ بـ`ob_start`/`ob_end_clean`.
 * ◆ المسحُ يُقصي `.claude/worktrees/` و`storage/backups/` — وإلا خضرةٌ كاذبة.
 *
 * التشغيل:
 *   php tools/fix_missing_screen_campaign.php            (يُعلن القائمةَ فقط)
 *   php tools/fix_missing_screen_campaign.php --run      (يُشغّل القياسَ الحيّ)
 *   php tools/fix_missing_screen_campaign.php --run --md=<path>
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));

$RUN = in_array('--run', $argv, true);
$MD = null;
foreach ($argv as $a) { if (strpos($a, '--md=') === 0) { $MD = substr($a, 5); } }

$lines = array();
$say = function ($s = '') use (&$lines) { fwrite(STDOUT, $s . "\n"); $lines[] = $s; };

/* ══ ① اشتقاقُ القائمة ═══════════════════════════════════════════════════ */
$state = array();
$tsv = $ROOT . '/docs/fix_progress/INJ_findings_state.tsv';
if (!is_file($tsv)) { exit("✘ لا ملفَّ حالةٍ — شغّل tools/fix_progress_report.php أوّلًا\n"); }
foreach (file($tsv) as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) >= 4 && strpos($p[0], 'INJ-') === 0) { $state[trim($p[0])] = trim($p[3]); }
}

$reg = array();
$fh = fopen($ROOT . '/docs/fix_2026-08/master_register.tsv', 'r');
$n = 0;
while (($l = fgets($fh)) !== false) {
    $n++;
    if ($n <= 3) { continue; }                       /* ثلاثةُ أسطرِ ترويسة */
    $c = explode("\t", rtrim($l, "\r\n"));
    if (count($c) < 22 || strpos($c[0], 'INJ-') !== 0) { continue; }
    if (trim($c[9]) !== 'Missing Screen') { continue; }   /* العمود 10 = نوعُ الفجوة */
    $reg[trim($c[0])] = array(
        'dept' => trim($c[3]), 'screen' => trim($c[4]), 'url' => trim($c[5]),
        'sev' => trim($c[10]), 'test' => trim($c[20]),   /* العمود 21 = اختبارُ القبول */
    );
}
fclose($fh);

$todo = array();
foreach ($reg as $id => $r) {
    $st = isset($state[$id]) ? $state[$id] : 'غيرُ مقيس';
    if ($st === 'مُغلقٌ بشاهد') { continue; }
    $r['state'] = $st;
    $todo[$id] = $r;
}

$say('══════════════════════════════════════════════════════════════════');
$say(' حملةُ أدلةٍ — الشاشاتُ المفقودة');
$say('══════════════════════════════════════════════════════════════════');
$say('');
$say('  المقامُ (نوعُها Missing Screen في السجل) : ' . count($reg));
$say('  منها **مُغلقٌ بشاهدٍ** سلفًا              : ' . (count($reg) - count($todo)));
$say('  ── **قائمةُ الحملةِ**                     : ' . count($todo));
$say('');
$byState = array();
foreach ($todo as $r) { $byState[$r['state']] = (isset($byState[$r['state']]) ? $byState[$r['state']] : 0) + 1; }
foreach ($byState as $k => $v) { $say('     ' . $k . ' : ' . $v); }
$say('');

if (!$RUN) {
    $say('── القائمة (شغّل بـ--run للقياس)');
    foreach ($todo as $id => $r) {
        $say(sprintf('   %-10s %-4s %-24s %s', $id, $r['sev'],
             mb_substr($r['dept'], 0, 22), mb_substr($r['screen'], 0, 42)));
    }
    exit(0);
}

/* ══ ② القياسُ الحيُّ ══════════════════════════════════════════════════════ */
ob_start();
require_once $ROOT . '/config.php';
ob_end_clean();                                        /* config يبتلع/يلوّث CLI */
while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$CO = 4;
$BASE = 'http://localhost/ems';

$J = sys_get_temp_dir() . '/msc_' . getmypid() . '.txt';
$rq = function ($url, $post = null) use ($J) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,               /* الاتّباعُ يقلب الحجبَ نجاحًا */
        CURLOPT_COOKIEJAR => $J, CURLOPT_COOKIEFILE => $J, CURLOPT_TIMEOUT => 90,
        CURLOPT_POST => $post !== null));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('body' => (string) $b, 'code' => $code);
};
$login = function ($user) use ($rq, $BASE, $J) {
    @unlink($J);
    $ch = curl_init($BASE . '/login.php');
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $J, CURLOPT_COOKIEFILE => $J, CURLOPT_TIMEOUT => 60));
    $b = (string) curl_exec($ch); curl_close($ch);
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $b, $t);
    $ch = curl_init($BASE . '/login.php');
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $J, CURLOPT_COOKIEFILE => $J, CURLOPT_TIMEOUT => 60,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array('username' => $user,
            'password' => '12345678', 'csrf_token' => isset($t[1]) ? $t[1] : ''))));
    $b = (string) curl_exec($ch); curl_close($ch);
    return mb_strpos($b, 'name="password"') === false;
};
$userOfRole = function ($role) use ($conn, $CO) {
    $st = $conn->prepare("SELECT username FROM users
                           WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
    $st->bind_param('si', $role, $CO);
    $st->execute();
    $r = $st->get_result()->fetch_row();
    $st->close();
    return $r ? (string) $r[0] : '';
};

/* الشاشةُ التي يشير إليها البند — من الرابطِ أوّلًا ثم من نصِّ الاختبار */
$screenOf = function ($r) use ($ROOT) {
    if (preg_match('~localhost/ems/([A-Za-z0-9_/\-]+\.php)~', $r['url'], $m)
        && is_file($ROOT . '/' . $m[1])) { return $m[1]; }
    if (preg_match_all('~\b([A-Za-z][A-Za-z0-9_]*\.php)~', $r['test'] . ' ' . $r['url'], $m)) {
        foreach (array_unique($m[1]) as $bn) {
            foreach (glob($ROOT . '/*/' . $bn) as $hit) {
                $rel = str_replace($ROOT . '/', '', str_replace('\\', '/', $hit));
                if (preg_match('~^(\.claude|storage|vendor|node_modules|tests|tools|database|docs)/~', $rel)) { continue; }
                return $rel;
            }
        }
    }
    return null;
};

/* أدوارُ المنحِ على شاشةٍ — من سجلِّ الشاشاتِ لا من تخمين */
$grantsOf = function ($rel) use ($conn) {
    $out = array();
    $st = $conn->prepare('SELECT rp.role_id FROM role_permissions rp
                            JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? AND rp.can_view = 1 ORDER BY rp.role_id');
    $st->bind_param('s', $rel);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($x = $res->fetch_row())) { $out[] = (int) $x[0]; }
    $st->close();
    return $out;
};

$say('── القياسُ الحيّ (اتّباعُ التحويلِ مُطفأ)');
$say('');
$rowsOut = array();
$pass = 0; $fail = 0;
foreach ($todo as $id => $r) {
    $rel = $screenOf($r);
    if ($rel === null) {
        $rowsOut[] = array($id, $r['screen'], '—', 'غيرُ مقيس',
            'اختبارُ القبولِ لا يُسمّي ملفًّا حيًّا يُقاس');
        $fail++;
        $say(sprintf('  ○ %-10s %-34s لا ملفَّ يُقاس', $id, mb_substr($r['screen'], 0, 32)));
        continue;
    }
    $grants = $grantsOf($rel);
    if (!$grants) {
        $rowsOut[] = array($id, $rel, '—', 'غيرُ مقيس',
            'الشاشةُ غيرُ مسجَّلةٍ في `modules` أو بلا منحِ عرض — والوصولُ لا يُقاس بلا حارس');
        $fail++;
        $say(sprintf('  ○ %-10s %-34s بلا تسجيلٍ/منح', $id, mb_substr($rel, 0, 32)));
        continue;
    }
    /* دورٌ مخوَّلٌ له حسابٌ · ودورٌ **غيرُ** مخوَّلٍ له حساب */
    $okUser = ''; $okRole = 0;
    foreach ($grants as $role) {
        $u = $userOfRole((string) $role);
        if ($u !== '') { $okUser = $u; $okRole = $role; break; }
    }
    $noUser = ''; $noRole = 0;
    for ($role = 1; $role <= 35; $role++) {
        if (in_array($role, $grants, true)) { continue; }
        $u = $userOfRole((string) $role);
        if ($u !== '') { $noUser = $u; $noRole = $role; break; }
    }
    if ($okUser === '') {
        $rowsOut[] = array($id, $rel, '—', 'غيرُ مقيس',
            'لا حسابَ لأيٍّ من الأدوارِ المخوَّلة (' . implode(',', $grants) . ') — فالوصولُ لا يُجرَّب');
        $fail++;
        $say(sprintf('  ○ %-10s %-34s لا حسابَ مخوَّل', $id, mb_substr($rel, 0, 32)));
        continue;
    }

    $allow = null; $deny = null;
    if ($login($okUser)) { $allow = $rq($BASE . '/' . $rel); }
    if ($noUser !== '' && $login($noUser)) { $deny = $rq($BASE . '/' . $rel); }

    $rendered = ($allow !== null && $allow['code'] === 200 && strlen($allow['body']) > 4000);
    $blocked  = ($deny === null) ? null : ($deny['code'] >= 300 && $deny['code'] < 400);

    if ($rendered && $blocked === true) {
        $rowsOut[] = array($id, $rel,
            'دور ' . $okRole . ' ⇒ 200 · دور ' . $noRole . ' ⇒ ' . $deny['code'],
            'مُغلقٌ بشاهد', '');
        $pass++;
        $say(sprintf('  ✔ %-10s %-34s دور %-3s⇒200 · دور %-3s⇒%s', $id,
             mb_substr($rel, 0, 32), $okRole, $noRole, $deny['code']));
    } else {
        $why = !$rendered
            ? ('لم تُصيَّر لدورٍ مخوَّلٍ (' . $okRole . ') — الرمز '
               . ($allow ? $allow['code'] : 'لا استجابة'))
            : ($blocked === null ? 'لا حسابَ لدورٍ غيرِ مخوَّلٍ — الحجبُ لا يُقاس'
                                 : ('لم يُحجَب عن دورٍ غيرِ مخوَّلٍ (' . $noRole . ') — الرمز ' . $deny['code']));
        $rowsOut[] = array($id, $rel,
            'دور ' . $okRole . ' ⇒ ' . ($allow ? $allow['code'] : '—'), 'غيرُ مقيس', $why);
        $fail++;
        $say(sprintf('  ✘ %-10s %-34s %s', $id, mb_substr($rel, 0, 32), mb_substr($why, 0, 46)));
    }
}
@unlink($J);

$say('');
$say('══ الحصيلة');
$say('  اجتاز القياسَ المشترَك : ' . $pass);
$say('  بقي غيرَ مقيسٍ        : ' . $fail);
$say('');
$say('  ◆ والقياسُ المشترَكُ **وصولٌ محكومٌ وتصييرٌ بمضمون** — وهو حدٌّ أدنى.');
$say('    وما زاد عليه في اختبارِ القبولِ (فعلٌ كاتبٌ · سلسلةُ اعتمادٍ · رقمٌ');
$say('    محسوب) لم يُقَس، فلا يُدَّعى. والبندُ لا يُغلق بقياسٍ جزئيٍّ يُقدَّم كاملًا.');

if ($MD !== null) {
    $md = "# حملةُ أدلةٍ — الشاشاتُ المفقودة\n\n";
    $md .= "> مُولَّدٌ بـ`php tools/fix_missing_screen_campaign.php --run`\n\n";
    $md .= "| المعرِّف | الشاشة | الشاهد | النتيجة | السبب عند الرسوب |\n";
    $md .= "|---|---|---|---|---|\n";
    foreach ($rowsOut as $x) {
        $md .= '| ' . $x[0] . ' | `' . $x[1] . '` | ' . $x[2] . ' | '
             . ($x[3] === 'مُغلقٌ بشاهد' ? '**مُغلقٌ بشاهد**' : $x[3]) . ' | ' . $x[4] . " |\n";
    }
    $md .= "\n**اجتاز: {$pass} · بقي غيرَ مقيس: {$fail}**\n";
    $path = (strpos($MD, ':') !== false || $MD[0] === '/') ? $MD : ($ROOT . '/' . $MD);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $md);
    fwrite(STDOUT, "\n   · كُتب: {$MD}\n");
}

/* ═══════════════════════════════════════════════════════════════════════════
 * عقدُ رمزِ الخروج — **وإلا فهي أداةُ تقريرٍ تصادق على نفسِها** (GT-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * كانت هذه الأداةُ تخرج بصفرٍ مهما كانت النتيجة، وهي في الوقتِ نفسِه **الشاهدُ**
 * الذي تُغلق به بنودٌ (وسمُها في ترويستِها). فأداةٌ لا ترسب أبدًا تُغلق ما تشاء
 * ولا يكشفها إفسادُ مفحوصِها — وهو عينُ ما تمنعه GT-01.
 *
 * ◆ فالأداةُ **تقرأ وسمَها من ترويستِها** ثم ترسب إن سقط أيُّ معرِّفٍ **تدّعي
 *   الشهادةَ له**. وما ليس في وسمِها لا يُسقطها — فهي لا تدّعي شهادتَه.
 * ◆ وهكذا: رفعُ منحِ عرضٍ عن دورٍ مخوَّلٍ ⇒ الشاشةُ تُحجَب عنه ⇒ الأداةُ حمراء
 *   ⇒ البندُ يخرج من «مُغلقٌ بشاهد» آليًّا. مُثبَتٌ بالتشغيل.
 * ═══════════════════════════════════════════════════════════════════════════ */
$claimed = array();
$selfHead = mb_substr((string) file_get_contents(__FILE__), 0, 6000);
if (preg_match('~شواهدُ أحكامٍ\s*:(.{0,1200})~su', $selfHead, $mm)) {
    $keep = array();
    foreach (preg_split('~\r?\n~', $mm[1]) as $i => $l) {
        if ($i > 0 && !preg_match('~\bINJ-\d{4}\b~', $l)) { break; }
        $keep[] = $l;
    }
    if (preg_match_all('~\bINJ-\d{4}\b~', implode("\n", $keep), $m2)) {
        $claimed = array_unique($m2[0]);
    }
}
$brokeClaim = array();
foreach ($rowsOut as $x) {
    if (in_array($x[0], $claimed, true) && $x[3] !== 'مُغلقٌ بشاهد') { $brokeClaim[] = $x[0]; }
}
if ($claimed) {
    $say('');
    $say('  معرِّفاتٌ تدّعي هذه الأداةُ الشهادةَ لها : ' . count($claimed));
    if ($brokeClaim) {
        $say('  ✘ سقط منها : ' . implode(' · ', $brokeClaim));
    } else {
        $say('  ✔ كلُّها اجتازت');
    }
}
exit($brokeClaim ? 1 : 0);
