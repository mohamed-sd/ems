<?php
/**
 * tools/fix_covered_campaign.php — حملةُ أدلةٍ على الملاحظاتِ «المُغطّاة»
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ تكليفُ حملةِ الأدلةِ الثانية (2026-08-13)
 *
 * ── الفجوةُ التي تُغلقها ────────────────────────────────────────────────────
 * سبعٌ وخمسون ملاحظةً نوعُها `Permission Gap` أو `Governance Gap` بخطورةِ P0/P1.
 * آليتُها المشتركةُ مُصلَحةٌ بشاهدٍ مُشغَّلٍ (AC-P1A · AC-P1B في `fix_gate.php`)
 * ولكلٍّ اختبارٌ سلبيٌّ في `fix_negative_tests.php`. والناقصُ **شاهدُ كلِّ
 * ملاحظةٍ منفردة** — وهو ما تُعلنه البوابةُ عن نفسِها حرفيًّا في `gate_fix.md`:
 *   «AC-P1B لا يقيس صحةَ الطرفِ المقارَنِ به — ذاك يحتاج تشغيلًا بحسابين لكلِّ مسار».
 * فهذه الأداةُ تُشغّل ما أعلنت البوابةُ أنَّها لا تقيسه.
 *
 * ── الشاهدُ: حسابان على مسارٍ واحد ─────────────────────────────────────────
 *   ① حسابٌ بـ`can_view=1` و`can_edit=0` يُرسل **نموذجَ الشاشةِ نفسَه** ⇒
 *      يُردُّ (403 أو تحويلٌ) **وصفرُ تغيُّرٍ** في الجداولِ المسمّاةِ في نصِّ
 *      اختبارِ القبول. فالشاهدُ **أثرُ الرفضِ في القاعدةِ لا رسالتُه** — وعطلُ
 *      RF-02 كان بالضبط **تنفيذًا يسبق الرسالة**.
 *   ② وحسابٌ بـ`can_edit=1` يُرسل النموذجَ نفسَه ⇒ **يعبر بوابةَ الصلاحية**.
 *      فبلا هذا الشوطِ يكون المنعُ **شللًا عامًّا** لا حكمَ صلاحية.
 *
 * ── وتمييزُ عطلِ الحمايةِ من حكمِ الصلاحية ───────────────────────────────────
 * نموذجُ POST عاديٌّ بلا `csrf_field()` يُردُّ **403 للجميع** — وهو عطلُ حمايةٍ
 * لا حكمُ صلاحية، وخلطُهما يُنتج خضرةً كاذبة. فالأداةُ:
 *   · تسحب رمزَ الجلسةِ من الصفحةِ نفسِها وتُرسله،
 *   · وتُجري **شوطًا ثالثًا بلا رمزٍ** بالحسابِ المخوَّل: إن رُدَّ بلا رمزٍ وعبر
 *     برمزٍ فالحمايةُ سليمةٌ والقياسُ يخصُّ الصلاحيةَ وحدَها.
 *
 * ── ما لا تقيسه — مُعلَنًا ──────────────────────────────────────────────────
 * سلسلةُ اعتمادٍ · رقمٌ محسوب · منحةُ حقلٍ حساسٍ في مُخرَجِ تصدير. وما زاد على
 * الشقِّ المشترَكِ **يُعلَن «غيرُ مقيسٍ بسببِه»** ولا يُدَّعى. والبندُ لا يُغلق
 * بقياسٍ جزئيٍّ يُقدَّم كاملًا.
 *
 * ── والوسمُ **ثابتٌ لا بـgetmypid** ─────────────────────────────────────────
 * وسمٌ متغيّرٌ يجعل كلَّ جولةٍ عمياءَ عمّا تركته سابقتُها. فالوسمُ عائلةٌ واحدةٌ
 * `COVCAMP` والكنسُ بها، ويُفحص **مُرجَعُ كلِّ حذفٍ** لأنَّ FK يردُّ صامتًا،
 * ويُعلَن ما لم يُكنس.
 *
 * التشغيل:
 *   php tools/fix_covered_campaign.php              (يُعلن القائمةَ فقط)
 *   php tools/fix_covered_campaign.php --run [--md=<path>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
$RUN  = in_array('--run', $argv, true);
$MD   = null;
/* ◆ `--only=INJ-####[,…]` — لقياسِ بندٍ بعينِه. والجولةُ الكاملةُ تتجاوز عشرَ
     دقائقَ (57 بندًا × ثلاثةِ أشواطٍ بـHTTP)، والاختبارُ السلبيُّ يحتاج جولتين
     متعاقبتين؛ فبلا مُرشِّحٍ يصير إثباتُ الرسوبِ نصفَ ساعة. ولا يُستعمل بديلًا
     عن الجولةِ الكاملةِ في التسليم. */
$ONLY = array();
foreach ($argv as $a) {
    if (strpos($a, '--md=') === 0) { $MD = substr($a, 5); }
    if (strpos($a, '--only=') === 0) {
        foreach (explode(',', substr($a, 7)) as $t) { $t = trim($t); if ($t !== '') { $ONLY[] = $t; } }
    }
}

$lines = array();
$say = function ($s = '') use (&$lines) { fwrite(STDOUT, $s . "\n"); $lines[] = $s; };

/* ══ ① اشتقاقُ القائمة ═══════════════════════════════════════════════════ */
$state = array();
$tsvState = $ROOT . '/docs/fix_progress/INJ_findings_state.tsv';
if (!is_file($tsvState)) { exit("✘ لا ملفَّ حالة — شغّل fix_progress_report.php أوّلًا\n"); }
foreach (file($tsvState) as $ln) {
    $p = explode("\t", rtrim($ln, "\r\n"));
    if (count($p) >= 4 && strpos($p[0], 'INJ-') === 0) { $state[trim($p[0])] = trim($p[3]); }
}

$COVERED_KINDS = array('Permission Gap', 'Governance Gap');
$todo = array(); $kinds = array();
$fh = fopen($ROOT . '/docs/fix_2026-08/master_register.tsv', 'r');
$n = 0;
while (($l = fgets($fh)) !== false) {
    $n++;
    if ($n <= 3) { continue; }                        /* ثلاثةُ أسطرِ ترويسة */
    $c = explode("\t", rtrim($l, "\r\n"));
    if (count($c) < 22 || strpos($c[0], 'INJ-') !== 0) { continue; }
    if (!in_array(trim($c[9]), $COVERED_KINDS, true)) { continue; }
    if (!in_array(trim($c[10]), array('P0', 'P1'), true)) { continue; }
    $id = trim($c[0]);
    $st = isset($state[$id]) ? $state[$id] : 'غيرُ مقيس';
    if ($st === 'مُغلقٌ بشاهد') { continue; }
    if ($ONLY && !in_array($id, $ONLY, true)) { continue; }
    $todo[$id] = array('kind' => trim($c[9]), 'sev' => trim($c[10]), 'dept' => trim($c[3]),
                       'screen' => trim($c[4]), 'url' => trim($c[5]), 'test' => trim($c[20]),
                       'state' => $st);
    $kinds[trim($c[9])] = (isset($kinds[trim($c[9])]) ? $kinds[trim($c[9])] : 0) + 1;
}
fclose($fh);

$say('══════════════════════════════════════════════════════════════════');
$say(' حملةُ أدلةٍ — الملاحظاتُ المُغطّاة (حسابان على مسارٍ واحد)');
$say('══════════════════════════════════════════════════════════════════');
$say('');
$say('  المقام: Permission/Governance Gap · P0/P1 · ليست «مُغلقٌ بشاهد»');
$say('  ── العدد: **' . count($todo) . '**');
foreach ($kinds as $k => $v) { $say('       ' . $k . ' : ' . $v); }
$say('');
if (!$RUN) {
    foreach ($todo as $id => $r) {
        $say(sprintf('   %-10s %-4s %-22s %s', $id, $r['sev'],
             mb_substr($r['dept'], 0, 20), mb_substr($r['screen'], 0, 40)));
    }
    exit(0);
}

/* ══ ② العُدّة ═══════════════════════════════════════════════════════════ */
ob_start();
require_once $ROOT . '/config.php';
ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$CO   = 4;
$BASE = 'http://localhost/ems';
$FAMILY = 'COVCAMP';                   /* وسمٌ **ثابتٌ** — لا getmypid */

$jar = sys_get_temp_dir() . '/covc_' . $FAMILY . '.txt';
$http = function ($url, $post = null, $follow = false) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,       /* الاتّباعُ يقلب الحجبَ نجاحًا */
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 90,
        CURLOPT_POST => $post !== null));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loc  = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return array('body' => (string) $b, 'code' => $code, 'loc' => $loc);
};
$login = function ($user) use ($http, $BASE, $jar) {
    @unlink($jar);
    $r = $http($BASE . '/login.php', null, true);
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $r['body'], $t);
    $r = $http($BASE . '/login.php', array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($t[1]) ? $t[1] : ''), true);
    return mb_strpos($r['body'], 'name="password"') === false;
};
/* حسابٌ لدورٍ بعينِه */
$userOfRole = function ($role) use ($conn, $CO) {
    $st = $conn->prepare("SELECT username FROM users
                           WHERE role = ? AND company_id = ? AND username <> '' ORDER BY id LIMIT 1");
    $r2 = (string) $role;
    $st->bind_param('si', $r2, $CO);
    $st->execute();
    $x = $st->get_result()->fetch_row();
    $st->close();
    return $x ? (string) $x[0] : '';
};
/* أدوارُ الشاشةِ من سجلِّ الشاشات — الربطُ بـ`code` لا بمسار */
$rolesOf = function ($rel) use ($conn) {
    $out = array('edit' => array(), 'viewOnly' => array());
    $st = $conn->prepare('SELECT rp.role_id, rp.can_view, rp.can_edit, rp.can_add
                            FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                           WHERE m.code = ? ORDER BY rp.role_id');
    $st->bind_param('s', $rel);
    $st->execute();
    $res = $st->get_result();
    while ($res && ($x = $res->fetch_assoc())) {
        $canW = ((int) $x['can_edit'] === 1 || (int) $x['can_add'] === 1);
        if ($canW) { $out['edit'][] = (int) $x['role_id']; }
        elseif ((int) $x['can_view'] === 1) { $out['viewOnly'][] = (int) $x['role_id']; }
    }
    $st->close();
    return $out;
};
/* الجداولُ المسمّاةُ في نصِّ اختبارِ القبول — وتُقبَل الموجودةُ فقط */
$tablesOf = function ($txt) use ($conn) {
    $out = array();
    if (preg_match_all('~\b([a-z][a-z0-9_]{4,40})\b~', $txt, $m)) {
        foreach (array_unique($m[1]) as $t) {
            $r = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
            if ($r && $r->num_rows) { $out[] = $t; }
        }
    }
    return $out;
};
$countRows = function (array $tables) use ($conn, $CO) {
    $c = array();
    foreach ($tables as $t) {
        $q = $conn->query("SELECT COUNT(*) FROM `{$t}`");
        $c[$t] = $q ? (int) $q->fetch_row()[0] : null;   /* null = تعذّر السؤال */
    }
    return $c;
};
$maxIds = function (array $tables) use ($conn) {
    $m = array();
    foreach ($tables as $t) {
        $q = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE 'id'");
        if (!$q || !$q->num_rows) { $m[$t] = null; continue; }
        $q = $conn->query("SELECT COALESCE(MAX(id),0) FROM `{$t}`");
        $m[$t] = $q ? (int) $q->fetch_row()[0] : null;
    }
    return $m;
};
/* نموذجُ POST من الصفحةِ نفسِها — إعادةُ إرسالِ نموذجِ الشاشةِ لا حمولةٍ مخترَعة */
$formOf = function ($html) {
    if (!preg_match('~<form\b[^>]*method\s*=\s*["\']?post["\']?[^>]*>(.*?)</form>~is', $html, $m)) {
        return null;
    }
    $body = $m[1];
    $f = array();
    if (preg_match_all('~<input\b[^>]*>~i', $body, $ins)) {
        foreach ($ins[0] as $tag) {
            if (preg_match('~type\s*=\s*["\']?(submit|button|file|image)["\']?~i', $tag)) { continue; }
            if (!preg_match('~name\s*=\s*["\']([^"\']+)["\']~i', $tag, $nm)) { continue; }
            $v = preg_match('~value\s*=\s*["\']([^"\']*)["\']~i', $tag, $vm) ? $vm[1] : '';
            if (preg_match('~type\s*=\s*["\']?(checkbox|radio)["\']?~i', $tag)
                && !preg_match('~\bchecked\b~i', $tag)) { continue; }
            $f[$nm[1]] = $v;
        }
    }
    if (preg_match_all('~<select\b[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)</select>~is', $body, $ss, PREG_SET_ORDER)) {
        foreach ($ss as $s) {
            $val = '';
            if (preg_match('~<option[^>]*value\s*=\s*["\']([^"\']+)["\'][^>]*>~i', $s[2], $om)) { $val = $om[1]; }
            $f[$s[1]] = $val;
        }
    }
    if (preg_match_all('~<textarea\b[^>]*name\s*=\s*["\']([^"\']+)["\']~i', $body, $ta)) {
        foreach ($ta[1] as $nm2) { $f[$nm2] = 'COVCAMP'; }
    }
    /* زرُّ الإرسالِ الأوّل — كثيرٌ من المعالجاتِ تشترط اسمَه */
    if (preg_match('~<button\b[^>]*name\s*=\s*["\']([^"\']+)["\'][^>]*value\s*=\s*["\']([^"\']*)["\']~i', $body, $bm)) {
        $f[$bm[1]] = $bm[2];
    } elseif (preg_match('~<input\b[^>]*type\s*=\s*["\']?submit["\']?[^>]*name\s*=\s*["\']([^"\']+)["\']~i', $body, $bm2)) {
        $f[$bm2[1]] = '1';
    }
    return $f ? $f : null;
};
$csrfOf = function ($html) {
    if (preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']~', $html, $m)) { return $m[1]; }
    if (preg_match('~csrfToken\s*=\s*["\']([^"\']+)["\']~', $html, $m)) { return $m[1]; }
    return null;
};

/* ══ ③ القياس ═══════════════════════════════════════════════════════════ */
$say('── القياسُ الحيُّ (اتّباعُ التحويلِ مُطفأ · وسمٌ ثابتٌ ' . $FAMILY . ')');
$say('');
$rows = array(); $pass = 0; $unmeasured = 0;
$writtenRanges = array();          /* جدولٌ ⇒ أعلى معرِّفٍ قبل الشوطِ المخوَّل */

foreach ($todo as $id => $r) {
    /* الشاشة */
    $rel = null;
    if (preg_match('~localhost/ems/([A-Za-z0-9_/\-]+\.php)~', $r['url'], $m)
        && is_file($ROOT . '/' . $m[1])) { $rel = $m[1]; }
    if ($rel === null) {
        $rows[] = array($id, $r['dept'], '—', '—', 'غيرُ مقيس', 'الرابطُ لا يشير إلى ملفٍّ حيّ');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-32s لا مسارَ يُقاس', $id, mb_substr($r['screen'], 0, 30)));
        continue;
    }
    $roles = $rolesOf($rel);
    if (!$roles['edit']) {
        $rows[] = array($id, $r['dept'], $rel, '—', 'غيرُ مقيس',
            'لا دورَ بصلاحيةِ كتابةٍ على الشاشة — فلا شوطَ مخوَّلَ يُقارَن به');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-32s لا دورَ كاتبًا', $id, mb_substr($rel, 0, 30)));
        continue;
    }
    /* ── الطرفُ المقارَنُ به: دورٌ بـ**عرضٍ بلا كتابة** حصرًا ────────────────────
       جُرِّبت صياغةٌ أوسعُ («أيُّ دورٍ بلا can_edit») لرفعِ التغطية، فقُيست وسقطت:
       دورٌ **بلا منحٍ أصلًا** يردُّه حارسُ **العرضِ** بـ302 قبل بلوغِ حارسِ
       الكتابة — فيُقاس الحارسُ الخطأُ ويُعلَن إغلاقًا. والدليلُ حاسمٌ: خُفِّض
       `ems_post_contract` من `can_edit` إلى `can_view` في
       `Transport/transfer_close_cost.php` **فبقيت النتيجةُ 302 خضراء** — أي أنَّ
       الفاحصَ لم يكن يرى حارسَ الكتابةِ إطلاقًا (GT-01).
       فالمقارَنُ به **يجب أن يعبر العرضَ ويُردَّ عند الكتابة** — وذلك يقتضي
       دورًا بـ`can_view=1` و`can_edit=0`. وحيث لا يوجد، يُعلَن البندُ غيرَ
       مقيسٍ بسببِه — ولا يُخترع منحٌ لتمرير فاحص. */
    $uEdit = ''; $rEdit = 0;
    foreach ($roles['edit'] as $ro) { $u = $userOfRole($ro); if ($u !== '') { $uEdit = $u; $rEdit = $ro; break; } }
    $uView = ''; $rView = 0;
    foreach ($roles['viewOnly'] as $ro) { $u = $userOfRole($ro); if ($u !== '') { $uView = $u; $rView = $ro; break; } }
    if (!$roles['viewOnly']) {
        $rows[] = array($id, $r['dept'], $rel, '—', 'غيرُ مقيس',
            'لا دورَ بـ`can_view=1` و`can_edit=0` على الشاشة — فلا طرفَ يعبر العرضَ ويُردُّ عند الكتابة');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-32s لا دورَ عرضٍ بلا كتابة', $id, mb_substr($rel, 0, 30)));
        continue;
    }
    if ($uEdit === '' || $uView === '') {
        $rows[] = array($id, $r['dept'], $rel, '—', 'غيرُ مقيس',
            'لا حسابَ لأحدِ الطرفين (كتابة: ' . implode(',', $roles['edit']) . ')');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-32s لا حسابَ لأحدِ الطرفين', $id, mb_substr($rel, 0, 30)));
        continue;
    }
    $tables = $tablesOf($r['test']);

    /* ── الشوطُ ①: **حصادُ النموذجِ** من الحسابِ المخوَّل ── */
    $form = null;
    if ($login($uEdit)) {
        $g2 = $http($BASE . '/' . $rel);
        $form = $formOf($g2['body']);
    }
    /* ── ومُقدِحُ المعالجِ **من عقدِ الشاشةِ نفسِها** ────────────────────────────
         `ems_post_contract` لا يشتعل إلا إذا حملت الحمولةُ حقلَ `trigger` الذي
         **تُصرّح به الشاشةُ** في عقدِها. والنموذجُ المحصودُ قد لا يحمله (زرٌّ
         يُملأ بـJS · حقلٌ يُضاف عند الاختيار). وبلا المُقدِحِ لا يعمل الحارسُ
         أصلًا — فيُقرأ تحويلٌ لسببٍ آخرَ **رفضَ صلاحيةٍ** وهو ليس كذلك.
         وقد قِيس هذا الفخُّ: بلا المُقدِحِ كان الوميضُ خاليًا من `GOV-PERM-403`،
         ومعه ظهر الرمزُ صريحًا. والقراءةُ من مصدرِ الشاشةِ **نقلٌ لا اختراع**. */
    $src = (string) @file_get_contents($ROOT . '/' . $rel);
    if ($form !== null && preg_match_all("~'trigger'\s*=>\s*'([A-Za-z0-9_]+)'~", $src, $tg)) {
        foreach (array_unique($tg[1]) as $tf) {
            if (!isset($form[$tf]) || $form[$tf] === '') { $form[$tf] = '1'; }
        }
    }
    if ($form === null) {
        $rows[] = array($id, $r['dept'], $rel, $uView . ' / ' . $uEdit, 'غيرُ مقيس',
            'لا نموذجَ POST في الصفحةِ حتى للمخوَّل — والفعلُ قد يكون AJAX (يحتاج حمولةً مخصَّصة)');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-32s لا نموذجَ يُحصَد', $id, mb_substr($rel, 0, 30)));
        continue;
    }
    if (!$tables) {
        $rows[] = array($id, $r['dept'], $rel, $uView . ' / ' . $uEdit, 'غيرُ مقيس',
            'اختبارُ القبولِ لا يُسمّي جدولًا قائمًا — فأثرُ الرفضِ لا يُقاس');
        $unmeasured++;
        $say(sprintf('  ○ %-10s %-32s لا جدولَ مسمًّى', $id, mb_substr($rel, 0, 30)));
        continue;
    }

    /* ── الشوطُ ②: إعادةُ إرسالِه من **غيرِ المخوَّل** ──
         ورمزُ الجلسةِ **من جلستِه هو** — يُسحب من اللوحةِ لأنَّه قد يُحجَب عن
         الشاشةِ نفسِها؛ ورمزُ غيرِه لا يصلح فالرمزُ لكلِّ جلسةٍ على حدة. */
    $p1 = null; $delta = 0; $unknownTable = false;
    if ($login($uView)) {
        $dash = $http($BASE . '/main/dashboard.php', null, true);
        $tok1 = $csrfOf($dash['body']);
        $send = $form;
        if ($tok1 !== null) { $send['csrf_token'] = $tok1; }
        $before = $countRows($tables);
        $p1 = $http($BASE . '/' . $rel, $send);
        $after = $countRows($tables);
        foreach ($before as $t => $v) {
            if ($v === null || $after[$t] === null) { $unknownTable = true; continue; }
            $delta += abs($after[$t] - $v);
        }
    }

    /* ── الشوطُ ③: المخوَّلُ يُرسل النموذجَ نفسَه — فالمنعُ انتقائيٌّ لا شللٌ عام
         ومعه شوطٌ **بلا رمزٍ** لتمييزِ عطلِ الحمايةِ من حكمِ الصلاحية. */
    $p2 = null; $p2NoTok = null;
    if ($login($uEdit)) {
        $g3 = $http($BASE . '/' . $rel);
        $tok2 = $csrfOf($g3['body']);
        $bare = $form;
        unset($bare['csrf_token']);
        $p2NoTok = $http($BASE . '/' . $rel, $bare);
        $form2 = $form;
        if ($tok2 !== null) { $form2['csrf_token'] = $tok2; }
        foreach ($tables as $t) { $writtenRanges[$t] = true; }
        $p2 = $http($BASE . '/' . $rel, $form2);
    }

    /* ── الحكم ── */
    /* ── الرفضُ يُعرَف **بوجهةِ التحويلِ** لا برمزِه ──────────────────────────
         أوّلُ صياغةٍ قبلت أيَّ 3xx رفضًا، فلم تكن تميّز رفضَ الصلاحيةِ من
         إعادةِ توجيهٍ عاديةٍ (نمطُ PRG أو ردُّ تحقُّقٍ). وأُثبِت ذلك بالقياس:
         خُفِّض حارسُ الكتابةِ من `can_edit` إلى `can_view` **فبقيت النتيجةُ
         302 خضراء** — أي أنَّ الفاحصَ لم يكن يرى الحارسَ (GT-01).
         والحدُّ الفاصلُ مقيسٌ: رفضُ الصلاحيةِ يُحوِّل إلى **`dashboard.php`**
         (عبر `ems_gov_flash_redirect`)، والعبورُ يعطي 200 أو تحويلًا إلى
         الشاشةِ نفسِها. فالشرطُ صار الوجهةَ لا الرمزَ وحدَه. */
    /* ◆ ويُشترط **رمزُ السببِ في الوميض**: `GOV-PERM-403`. فوجهةُ التحويلِ وحدَها
         لا تكفي — والوميضُ يُقرأ من **الجسمِ الخام** لأنَّ `strip_tags` تمحو
         جسمَ `<script>` وفيه الرسالة. وهكذا يُميَّز رفضُ **الصلاحية** من
         `CSRF-403` (عطلُ حماية) ومن `ACT-403` (فعلٌ غيرُ مسجَّل) — وخلطُها
         يُنتج خضرةً كاذبة. */
    $isDeny = function ($resp) use ($http, $BASE) {
        if ($resp === null) { return false; }
        if ($resp['code'] === 403) { return true; }
        if ($resp['code'] < 300 || $resp['code'] >= 400) { return false; }
        $t = basename((string) parse_url($resp['loc'], PHP_URL_PATH));
        if ($t !== 'dashboard.php' && $t !== 'login.php') { return false; }
        $land = $http($resp['loc'] !== '' ? $resp['loc'] : ($BASE . '/main/dashboard.php'), null, true);
        return (mb_strpos($land['body'], 'GOV-PERM-403') !== false);
    };
    $denied  = $isDeny($p1);
    $allowed = ($p2 !== null) && !$isDeny($p2);
    $csrfSane = ($p2NoTok !== null) && ($p2NoTok['code'] === 403);

    $ok = $denied && $delta === 0 && $allowed && !$unknownTable;
    $witness = 'دور ' . $rView . ' ⇒ ' . ($p1 ? $p1['code'] : '—')
             . ' · دور ' . $rEdit . ' ⇒ ' . ($p2 ? $p2['code'] : '—')
             . ' · Δ=' . $delta;
    if ($ok) {
        $rows[] = array($id, $r['dept'], $rel, $uView . ' / ' . $uEdit, 'مُغلقٌ بشاهد',
            $csrfSane ? '' : 'ملحوظة: لم يُثبَت ردُّ الطلبِ بلا رمزِ جلسةٍ — فالتمييزُ بين عطلِ الحمايةِ وحكمِ الصلاحيةِ ناقص');
        $pass++;
        $say(sprintf('  ✔ %-10s %-32s %s', $id, mb_substr($rel, 0, 30), $witness));
    } else {
        $why = !$denied ? 'لم يُردَّ طلبُ حسابِ العرضِ (الرمز ' . ($p1 ? $p1['code'] : '—') . ')'
             : ($delta !== 0 ? '**أثرٌ في القاعدةِ رغمَ الرفض**: Δ=' . $delta . ' في ' . implode(',', $tables)
             : ($unknownTable ? 'تعذّرت قراءةُ أحدِ الجداولِ المسمّاة'
             : 'لم يعبر الحسابُ المخوَّلُ (الرمز ' . ($p2 ? $p2['code'] : '—') . ') — فالمنعُ شللٌ عامٌّ لا حكمُ صلاحية'));
        $rows[] = array($id, $r['dept'], $rel, $uView . ' / ' . $uEdit, 'غيرُ مقيس', $why);
        $unmeasured++;
        $say(sprintf('  ✘ %-10s %-32s %s', $id, mb_substr($rel, 0, 30), mb_substr($why, 0, 44)));
    }
}
@unlink($jar);

/* ══ ④ الكنسُ بعائلةِ الوسمِ — ويُفحص مُرجَعُ كلِّ حذف ═══════════════════════ */
$say('');
$say('── الكنس (عائلةُ ' . $FAMILY . ')');
$swept = 0; $unswept = array();
foreach (array_keys($writtenRanges) as $t) {
    $cols = array();
    $q = $conn->query("SHOW COLUMNS FROM `{$t}`");
    while ($q && ($x = $q->fetch_assoc())) { $cols[] = $x['Field']; }
    $textCols = array();
    foreach ($cols as $cn) {
        if (preg_match('~(note|notes|remark|desc|description|reason|title|name|ref)~i', $cn)) { $textCols[] = $cn; }
    }
    if (!$textCols) { continue; }
    $whr = array();
    foreach ($textCols as $cn) { $whr[] = "`{$cn}` LIKE '%{$FAMILY}%'"; }
    $sql = "DELETE FROM `{$t}` WHERE " . implode(' OR ', $whr);
    $okDel = $conn->query($sql);
    if ($okDel === false) {                      /* FK يردُّ صامتًا — يُفحص المُرجَع */
        $unswept[] = $t . ' (' . $conn->error . ')';
        continue;
    }
    $swept += max(0, $conn->affected_rows);
}
$say('  صفوفٌ كُنست: ' . $swept);
if ($unswept) { $say('  ⚠ **لم يُكنس**: ' . implode(' · ', $unswept)); }
else { $say('  ولا جدولَ تعذّر كنسُه.'); }

$say('');
$say('══ الحصيلة');
$say('  اجتاز القياسَ المشترَك : ' . $pass);
$say('  بقي غيرَ مقيسٍ        : ' . $unmeasured);
$say('');
$say('  ◆ المقيسُ: حسابان على مسارٍ واحد — الردُّ **وصفرُ أثرٍ** في الجداولِ');
$say('    المسمّاة، **وعبورُ** المخوَّلِ فالمنعُ انتقائيٌّ لا شللٌ عام.');
$say('  ◆ ولم يُقَس: سلسلةُ اعتمادٍ · رقمٌ محسوب · منحةُ حقلٍ حساسٍ في مُخرَجِ');
$say('    تصدير. فالبندُ لا يُغلق بقياسٍ جزئيٍّ يُقدَّم كاملًا.');

if ($MD !== null) {
    $md = "# حملةُ أدلةٍ — الملاحظاتُ المُغطّاة · " . date('Y-m-d') . "\n\n";
    $md .= "> **الفرع** `fix/remediation-2026-08` · مُولَّدٌ بـ`php tools/fix_covered_campaign.php --run`\n\n";
    $md .= "| المعرِّف | الإدارة | المسار | الحسابان (عرض / كتابة) | النتيجة | ما لم يُقَس وسببه |\n";
    $md .= "|---|---|---|---|---|---|\n";
    foreach ($rows as $x) {
        $md .= '| ' . $x[0] . ' | ' . $x[1] . ' | `' . $x[2] . '` | ' . $x[3] . ' | '
             . ($x[4] === 'مُغلقٌ بشاهد' ? '**مُغلقٌ بشاهد**' : $x[4]) . ' | ' . $x[5] . " |\n";
    }
    $md .= "\n**اجتاز: {$pass} · بقي غيرَ مقيس: {$unmeasured}**\n\n";
    $md .= "الكنس: {$swept} صفًّا" . ($unswept ? ' · **لم يُكنس**: ' . implode(' · ', $unswept) : ' · ولا جدولَ تعذّر') . "\n";
    $path = (strpos($MD, ':') !== false || $MD[0] === '/') ? $MD : ($ROOT . '/' . $MD);
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $md);
    fwrite(STDOUT, "\n   · كُتب: {$MD}\n");
}

/* ══ ⑤ عقدُ رمزِ الخروج — وإلا صادقت على نفسِها (GT-01) ═══════════════════ */
$claimed = array();
$head = mb_substr((string) file_get_contents(__FILE__), 0, 6000);
if (preg_match('~شواهدُ أحكامٍ\s*:(.{0,1600})~su', $head, $mm)) {
    $keep = array();
    foreach (preg_split('~\r?\n~', $mm[1]) as $i => $l2) {
        if ($i > 0 && !preg_match('~\bINJ-\d{4}\b~', $l2)) { break; }
        $keep[] = $l2;
    }
    if (preg_match_all('~\bINJ-\d{4}\b~', implode("\n", $keep), $m3)) { $claimed = array_unique($m3[0]); }
}
$broke = array();
foreach ($rows as $x) { if (in_array($x[0], $claimed, true) && $x[4] !== 'مُغلقٌ بشاهد') { $broke[] = $x[0]; } }
if ($claimed) {
    $say('');
    $say('  تدّعي هذه الأداةُ الشهادةَ لـ' . count($claimed) . ' معرِّفًا');
    $say($broke ? ('  ✘ سقط منها: ' . implode(' · ', $broke)) : '  ✔ كلُّها اجتازت');
}
exit($broke ? 1 : 0);
