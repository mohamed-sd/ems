<?php
/**
 * tests/finreq_merge_screen_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP: **شاشةُ الطلبِ الماليِّ صارت واحدةً** — `FinRequests/request_form.php`
 * تجمع بطاقاتِ الإحصاءِ وفلاترَ البحثِ وقائمةَ طلباتي ونموذجَ الإنشاءِ وسجلَّ
 * الطلب، و`my_requests.php` صارت مُحوِّلًا بعدّادِ نقرات (2026-08-21).
 *
 * ◆ **ولا يُغلق دمجٌ بإثباتِ أن الشاشةَ تُفتح** — فالفتحُ كان قائمًا قبلَه.
 *   الدمجُ يُغلق بأربعةِ أحكامٍ يُقاس كلٌّ منها على المُصيَّرِ الحيّ:
 *   ① **رابطٌ واحدٌ لا اثنان** في السايدبار — وبمرساةِ `href` لا بالنصِّ العربيّ،
 *      و**بعد نزعِ التعليقات** (مِلكيةٌ موروثةٌ داخلَ `<!-- … -->` يطبعها PHP
 *      ولا يعرضها المتصفح، فعدٌّ على الخامِ يرى ما لا يراه المستخدم).
 *   ② **المتقاعدُ يعبر ولا يصطدم** — 302 إلى الوجهة، و`?id=` يُمرَّر كما هو.
 *   ③ **الأرقامُ تُقاس لا تُصدَّق**: قيمةُ كلِّ بطاقةٍ = عدُّ القاعدةِ لمجموعتِها،
 *      وعددُ صفوفِ الجدولِ بعدَ الترشيح = عدُّ القاعدةِ للمرشَّح. فبطاقةٌ تعرض
 *      رقمًا لا يقابله صفٌّ خضراءُ كاذبة.
 *   ④ **ولا انحسارَ في الوصول**: دورٌ كان يرى «طلباتي» ولا صفَّ له على الوحدةِ
 *      الباقيةِ (25 أمين المستودع) يجب أن يفتحَها 200 لا 302 إلى اللوحة.
 *
 * ◆ **وضابطٌ سالبٌ في الجولةِ نفسِها**: وضعُ السجلِّ (`?id=N`) يجب أن **لا**
 *   يحمل قسمَ الإحصاءِ ولا صندوقَ الفلاتر — وإلا فالوضعان لم ينفصلا وإنما
 *   كُدِّسا، و«الشاشةُ الواحدة» اسمٌ بلا معنًى.
 *
 * التشغيل: php tests/finreq_merge_screen_http_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT  = dirname(__DIR__);
$BASE  = 'http://localhost/ems';
$KEEP  = 'FinRequests/request_form.php';
$GONE  = 'FinRequests/my_requests.php';

$CREATOR      = 'محمد';            /* الدور 1 · user 4 — له تسعةُ طلباتٍ بسبعِ حالاتٍ وعملتان */
$CREATOR_UID  = 4;
$NONCREATOR   = 'أمين المستودع';   /* الدور 25 · كان له «طلباتي» ولا صفَّ له على الوحدةِ الباقية */
$RECORD_ID    = 619;               /* FR-2026-0009 — من طلباتِ الحساب نفسِه */
$RECORD_NO    = 'FR-2026-0009';

$PASS_N = 0; $FAIL_N = 0;
function ok($m)  { global $PASS_N; $PASS_N++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL_N; $FAIL_N++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

require_once $ROOT . '/includes/env.php';
require_once $ROOT . '/FinRequests/_finreq_helpers.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { exit("تعذّر الاتصال بالقاعدة\n"); }
$db->set_charset('utf8mb4');
function one($sql) { global $db; $r = $db->query($sql); $x = $r ? $r->fetch_row() : null; return $x === null ? null : $x[0]; }

function req($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return req($BASE . '/login.php', $jar, array(
        'username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}

/** سايدبارُ الصفحةِ **كما يراه المتصفحُ**: قصٌّ على حدَّي القائمة ثم نزعُ التعليقات. */
function sidebar_html($body) {
    $s = strpos($body, '<ul id="sidebarNavList">');
    $e = strpos($body, '<!-- زر تسجيل الخروج -->');
    if ($s === false || $e === false || $e <= $s) { return null; }
    return preg_replace('~<!--.*?-->~s', '', substr($body, $s, $e - $s));
}
/** عددُ مرساتِ `href` لمسارٍ بعينِه — بحدِّ اسمِ الملفِّ فلا يبتلع مسارًا أطول. */
function href_hits($html, $route) {
    $file = basename($route);
    return preg_match_all('~href="[^"]*' . preg_quote($file, '~') . '(?:[?#"])~', (string) $html);
}
/** جسمُ جدولِ القائمةِ وحدَه — فالعدُّ على الصفحةِ كلِّها يبتلع جداولَ أخرى. */
function list_tbody($body) {
    $s = strpos($body, 'frq-table');
    if ($s === false) { return null; }
    $ts = strpos($body, '<tbody>', $s);
    $te = strpos($body, '</tbody>', $ts === false ? $s : $ts);
    if ($ts === false || $te === false) { return null; }
    return substr($body, $ts, $te - $ts);
}
function row_count($body) {
    $tb = list_tbody($body);
    return $tb === null ? -1 : preg_match_all('~<tr[\s>]~', $tb);
}
/** قيمةُ بطاقةٍ من مرساتِها: `href="request_form.php?state=KEY"` ثم أوّلُ stats-value. */
function card_value($body, $key) {
    $needle = $key === '' ? 'href="request_form.php"' : 'href="request_form.php?state=' . $key . '"';
    $p = strpos($body, $needle);
    if ($p === false) { return null; }
    if (!preg_match('~stats-value[^>]*>\s*([^<]*)~', substr($body, $p, 600), $m)) { return null; }
    return trim($m[1]);
}

fwrite(STDOUT, "═══ دمجُ شاشتَي الطلبِ الماليِّ — برهانٌ حيٌّ ═══\n");

$jar = sys_get_temp_dir() . '/ems_finreq_merge_' . getmypid() . '.cookie';
list($lc, $lh, $lb) = login($CREATOR, $jar);
check($lc === 302 || $lc === 200, "دخولُ «{$CREATOR}» (الدور 1) — رمزٌ {$lc}");

/* ══ ① رابطٌ واحدٌ لا اثنان ═════════════════════════════════════════════ */
head('① السايدبار — رابطٌ واحدٌ لرحلةٍ واحدة');
list($c, $h, $b) = req($BASE . '/main/dashboard.php', $jar);
$side = sidebar_html($b);
check($side !== null, 'قُصَّ السايدبارُ على حدَّيه (وإلا فالعدُّ على وهم)');
if ($side !== null) {
    $nKeep = href_hits($side, $KEEP);
    $nGone = href_hits($side, $GONE);
    check($nKeep === 1, "رابطُ الشاشةِ الباقيةِ يظهر مرةً واحدة (المقيس: {$nKeep})");
    check($nGone === 0, "لا رابطَ للمسارِ المتقاعد (المقيس: {$nGone})");
    check(strpos($side, 'طلباتي المالية') !== false, 'الاسمُ المعروضُ «طلباتي المالية»');
    /* **ضابطٌ موجبٌ في الجولةِ نفسِها**: صفرٌ في عدّادٍ معطوبٍ خضراءُ كاذبة.
       فيُقاس مسارٌ أخو المتقاعدِ في المجلَّدِ نفسِه **يجب أن يظهر** للدور 1 —
       فإن رجع صفرًا فالعطبُ في العدّادِ لا في الشاشة، ويُكشف هنا لا لاحقًا. */
    $nCtl = href_hits($side, 'FinRequests/dept_inbox.php');
    check($nCtl === 1, "ضابطٌ موجب: «موافقات إدارتي» يظهر مرةً واحدة (المقيس: {$nCtl})");

    /* ══ الموضعُ نفسُه لا موضعٌ جديد ═══════════════════════════════════════
       قرارُ المالك 2026-08-21: «أظهِر الصفحةَ الجديدةَ في نفسِ الأماكنِ التي
       كانت فيها القديمة». وكانت «طلب مالي جديد» تحت **«المالية والمحاسبة»**،
       فنُقلت في أوّلِ جولةِ الدمجِ إلى «مساحتي» باجتهادٍ لم يُطلَب ثم أُعيدت.
       ولا يُقاس الموضعُ من الوسمِ في الشفرةِ بل **من رأسِ المجموعةِ المُصيَّرِ
       الذي يسبق الرابطَ في السايدبار** — فالوسمُ نيّةٌ والمُصيَّرُ حكم. */
    $upto = strpos($side, 'FinRequests/request_form.php');
    $grp = '(لم يُعثر)';
    if ($upto !== false && preg_match_all('~<span class="nav-group-name">([^<]*)</span>~u',
            substr($side, 0, $upto), $gm)) {
        $grp = trim(html_entity_decode(end($gm[1]), ENT_QUOTES, 'UTF-8'));
    }
    check($grp === 'المالية والمحاسبة', "وموضعُه «المالية والمحاسبة» كما كان (المقيس: «{$grp}»)");
}

/* ══ ② المتقاعدُ يعبر ولا يصطدم ═════════════════════════════════════════ */
head('② المسارُ المتقاعد — تحويلٌ يحفظ المعامل');
$hitsBefore = (int) one("SELECT hits FROM nav_redirects WHERE old_route = '" . $db->real_escape_string($GONE) . "'");
list($c, $h, $b) = req($BASE . '/' . $GONE, $jar);
preg_match('~^Location:\s*(\S+)~mi', $h, $loc);
check($c === 302, "المتقاعدُ يردُّ 302 (المقيس: {$c})");
check(isset($loc[1]) && strpos($loc[1], 'request_form.php') !== false,
      'الوجهةُ هي الشاشةُ الموحَّدة: ' . (isset($loc[1]) ? $loc[1] : '—'));

list($c2, $h2) = req($BASE . '/' . $GONE . '?id=' . $RECORD_ID, $jar);
preg_match('~^Location:\s*(\S+)~mi', $h2, $loc2);
check(isset($loc2[1]) && strpos($loc2[1], 'id=' . $RECORD_ID) !== false,
      "المعاملُ `?id={$RECORD_ID}` يُمرَّر كما هو: " . (isset($loc2[1]) ? $loc2[1] : '—'));

$hitsAfter = (int) one("SELECT hits FROM nav_redirects WHERE old_route = '" . $db->real_escape_string($GONE) . "'");
check($hitsAfter >= $hitsBefore + 2, "كلُّ نقرةٍ تُعَدّ في nav_redirects ({$hitsBefore} ⇦ {$hitsAfter})");

/* ══ ②-ب المنحُ يتبع الشاشة ════════════════════════════════════════════ */
head('②-ب «الممنوحُ يُرى» — فما تقاعد لا يبقى ممنوحًا');
$mrId = (int) one("SELECT MIN(id) FROM modules WHERE code = '" . $db->real_escape_string($GONE) . "'");
$ghost = (int) one("SELECT COUNT(*) FROM role_permissions WHERE module_id = {$mrId}");
check($ghost === 0, "لا صفَّ صلاحيةٍ على الوحدةِ المتقاعدةِ #{$mrId} (المقيس: {$ghost})");
$ghostG = (int) one("SELECT COUNT(*) FROM gov_profile_items WHERE item_kind='screen'
                       AND item_ref = '" . $db->real_escape_string($GONE) . "'");
check($ghostG === 0, "ولا بندَ قالبٍ يمنحها (المقيس: {$ghostG})");
$rfId = (int) one("SELECT MIN(id) FROM modules WHERE code = '" . $db->real_escape_string($KEEP) . "'");
$keepGrants = (int) one("SELECT COUNT(*) FROM role_permissions WHERE module_id = {$rfId} AND can_view = 1");
check($keepGrants >= 26, "والمنحُ انتقل للباقيةِ: {$keepGrants} دورًا يرى الشاشةَ الموحَّدة");

/* ══ ③ وضعُ القائمة — العناصرُ الأربعةُ حاضرةٌ في مُصيَّرٍ واحد ══════════ */
head('③ وضعُ القائمة — إحصاءٌ وفلاترُ وجدولٌ ونموذجٌ في شاشةٍ واحدة');
list($c, $h, $body) = req($BASE . '/' . $KEEP, $jar);
check($c === 200, "الشاشةُ تُفتح 200 (المقيس: {$c})");
check(strpos($body, 'id="frqStatsSection"') !== false, 'قسمُ بطاقاتِ الإحصاء');
check(preg_match('~<div class="filter">~', $body) === 1, 'صندوقُ فلاترِ البحث (بنيةُ ems-filters)');
check(strpos($body, 'name="q"') !== false && strpos($body, 'name="state"') !== false
      && strpos($body, 'name="type"') !== false && strpos($body, 'name="from"') !== false,
      'مرشِّحاتُ الحالةِ والنوعِ والنصِّ والمدى');
check(strpos($body, 'frq-table') !== false, 'جدولُ الطلبات');
check(strpos($body, 'id="finreqForm"') !== false, 'نموذجُ الإنشاءِ في الشاشةِ نفسِها');
check(strpos($body, 'id="toggleForm"') !== false, 'زرُّ فتحِ النموذج');
check(strpos($body, 'id="toggleStats"') !== false, 'زرُّ طيِّ الإحصاء');

/* ══ ④ الأرقامُ تُقاس لا تُصدَّق ════════════════════════════════════════ */
head('④ بطاقةُ الإحصاءِ = عدُّ القاعدةِ لمجموعتِها');
$own = "(created_by = {$CREATOR_UID} OR requester_id = {$CREATOR_UID})";
$dbTotal = (int) one("SELECT COUNT(*) FROM fin_requests WHERE {$own}");
$uiTotal = card_value($body, '');
check($uiTotal !== null && (int) $uiTotal === $dbTotal,
      "«إجمالي طلباتي» = {$dbTotal} (المعروض: " . var_export($uiTotal, true) . ")");

foreach (finreq_state_groups() as $gk => $gv) {
    $inList = "'" . implode("','", array_map(array($db, 'real_escape_string'), $gv['states'])) . "'";
    $n  = (int) one("SELECT COUNT(*) FROM fin_requests WHERE {$own} AND state IN ({$inList})");
    $ui = card_value($body, $gk);
    check($ui !== null && (int) $ui === $n, "بطاقةُ «{$gv['label']}» = {$n} (المعروض: " . var_export($ui, true) . ")");
}

$rowsAll = row_count($body);
check($rowsAll === $dbTotal, "صفوفُ الجدولِ بلا ترشيحٍ = {$dbTotal} (المقيس: {$rowsAll})");

/* والعملتان لا تُجمعان في رقم */
$curN = (int) one("SELECT COUNT(DISTINCT currency) FROM fin_requests WHERE {$own}
                    AND state NOT IN ('rejected','cancelled','withdrawn','expired','merged','paid','collected','closed','archived')");
if ($curN > 1) {
    check(strpos($body, 'ems-statcard__value--text') !== false,
          "الطلباتُ الحيّةُ بـ{$curN} عملةٍ — فالقيمةُ نصٌّ لكلِّ عملةٍ لا رقمٌ مجموع");
} else {
    ok("عملةٌ حيّةٌ واحدةٌ ({$curN}) — لا مادّةَ لفحصِ الخلط");
}
/* **والعملةُ الفارغةُ ليست عملة**: تُسمّى ولا تُترك شرطةً تُقرأ «لا قيمة» */
$blank = (int) one("SELECT COUNT(*) FROM fin_requests WHERE {$own} AND TRIM(COALESCE(currency,'')) = ''");
if ($blank > 0) {
    check(strpos($body, 'بلا عملة') !== false,
          "{$blank} طلبًا بعملةٍ فارغة — والشاشةُ تسمّيها «بلا عملة» لا «—»");
} else {
    ok('لا طلبَ بعملةٍ فارغةٍ لهذا الحساب — لا مادّةَ للفحص');
}

/* ══ ⑤ الترشيحُ يعمل ════════════════════════════════════════════════════ */
head('⑤ الترشيحُ — الجدولُ يتبع المرشِّحَ والبطاقةُ لا');
foreach (array('draft', 'returned', 'refused') as $gk) {
    $g = finreq_state_groups();
    $inList = "'" . implode("','", $g[$gk]['states']) . "'";
    $n = (int) one("SELECT COUNT(*) FROM fin_requests WHERE {$own} AND state IN ({$inList})");
    list($fc, $fh, $fb) = req($BASE . '/' . $KEEP . '?state=' . $gk, $jar);
    $r = row_count($fb);
    check($fc === 200 && $r === $n, "?state={$gk} ⇒ {$n} صفًّا (المقيس: {$r})");
    /* والبطاقةُ تبقى على الإجمالِ — دليلُ تنقُّلٍ لا صدًى للمرشِّح */
    check((int) card_value($fb, '') === $dbTotal, "  وبطاقةُ الإجمالِ ما تزال {$dbTotal} تحت الترشيح");
}
list($qc, $qh, $qb) = req($BASE . '/' . $KEEP . '?q=' . urlencode($RECORD_NO), $jar);
check($qc === 200 && row_count($qb) === 1, 'البحثُ النصّيُّ برقمِ الطلبِ يُرجع صفًّا واحدًا (المقيس: ' . row_count($qb) . ')');

/* ══ ⑥ وضعُ السجل — والضابطُ السالبُ في الجولةِ نفسِها ═════════════════ */
head('⑥ وضعُ السجل — سجلٌّ لا قائمة');
list($rc, $rh, $rb) = req($BASE . '/' . $KEEP . '?id=' . $RECORD_ID, $jar);
check($rc === 200, "سجلُّ الطلبِ يُفتح 200 (المقيس: {$rc})");
check(strpos($rb, $RECORD_NO) !== false, "رقمُ الطلبِ «{$RECORD_NO}» في المُصيَّر");
/* الترويسةُ المشتركةُ تُحلُّ تسميةَ الرابطِ محلَّ عنوانِ الشاشة — فلولا
   `$header_title_html` لَقرأ فاتحُ السجلِّ عنوانَ القائمةِ ولا شيءَ يميّزه. */
check(preg_match('~<span class="fr-title-no">\s*' . preg_quote($RECORD_NO, '~') . '\s*</span>~u', $rb) === 1,
      'ورقمُه في الترويسةِ نفسِها — لم تمحُه تسميةُ الرابط');
check(strpos($rb, 'سجل الطلب الإلحاقي') !== false, 'السجلُّ الإلحاقيُّ حاضر');
check(strpos($rb, 'id="frqStatsSection"') === false, '⟂ ولا قسمَ إحصاءٍ في وضعِ السجل');
check(strpos($rb, 'frq-table') === false, '⟂ ولا جدولَ قائمةٍ في وضعِ السجل');

/* ══ ⑦ رقمٌ لا وجودَ له — يرجع للقائمةِ ويقول السبب ════════════════════ */
head('⑦ رقمٌ لا وجودَ له');
list($nc, $nh, $nb) = req($BASE . '/' . $KEEP . '?id=999999999', $jar);
check($nc === 200, "لا 500 ولا صفحةٌ فارغة (المقيس: {$nc})");
check(strpos($nb, 'ضمنَ نطاقِك') !== false, 'الشاشةُ تقول السببَ ولا تصمت');
check(strpos($nb, 'id="frqStatsSection"') !== false, 'وترجع إلى قائمةِ طلباتِه');

/* ══ ⑧ لا انحسارَ في الوصول ════════════════════════════════════════════ */
head('⑧ دورٌ كان يرى «طلباتي» ولا صفَّ له على الوحدةِ الباقيةِ قبلَ الهجرة');
$jar2 = sys_get_temp_dir() . '/ems_finreq_merge2_' . getmypid() . '.cookie';
list($lc2) = login($NONCREATOR, $jar2);
check($lc2 === 302 || $lc2 === 200, "دخولُ «{$NONCREATOR}» (الدور 25) — رمزٌ {$lc2}");
list($ac, $ah, $ab) = req($BASE . '/' . $KEEP, $jar2);
check($ac === 200, "يفتح الشاشةَ الموحَّدةَ 200 لا تحويلًا إلى اللوحة (المقيس: {$ac})");
check(strpos($ab, 'id="finreqForm"') === false || strpos($ab, 'id="toggleForm"') === false,
      'ولا يُفتح له نموذجُ الإنشاءِ — العرضُ مُنح والإضافةُ لم تُمنح');
@unlink($jar2);
@unlink($jar);

fwrite(STDOUT, "\n═══════════════════════════════════════════════════════════\n");
fwrite(STDOUT, "نجح: {$PASS_N} · أخفق: {$FAIL_N}\n");
exit($FAIL_N === 0 ? 0 : 1);
