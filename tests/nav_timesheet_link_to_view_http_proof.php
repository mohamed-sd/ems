<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * رابطُ التايم شيت في السايدبار — إثباتٌ مُشغَّلٌ عبر HTTP
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/nav_timesheet_link_to_view_http_proof.php
 * الهجرة :  database/migrations/2027_08_17_nav_timesheet_link_to_view.php
 *
 *   لكلِّ دورٍ محوَّل (1 التشغيل · 2 الموردون):
 *     S1 ✚ سايدبارُه يحمل رابطًا **واحدًا** إلى `Timesheet/view_timesheet.php`.
 *     S2 ✖ ولا يحمل ولا رابطًا إلى `Timesheet/timesheet.php` — فالتوأمُ المصطنَعُ
 *          (طبقةُ «صفرِ الفقد») هو ما ظهر حين حُوِّل `nav_items` وحدَه.
 *     S3 ✚ اتّباعُ الرابطِ يُقدِّم 200 وعنوانَ «سجل الوحدات اليومية» — لا 403 ولا تحويل.
 *   ثم عامًّا:
 *     S4 ✖ الوجهةُ القديمةُ ما تزال تُعيد التوجيهَ إلى `timesheet_type.php` —
 *          وهي علّةُ الشكوى: الرابطُ كان يذكر `timesheet.php` والمستخدمُ يهبط غيرَها.
 *          (لولا هذا لكان الفحصُ يقيس تغييرًا بلا سبب.)
 *     S5 ✚ دورٌ خارجَ القائمةِ لم يُمَس: الدور 6 ما يزال على `Timesheet/timesheet.php`.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
while (ob_get_level() > 0) { ob_end_clean(); }

$BASE = 'http://localhost/ems';
$PW   = '12345678';

/** الأدوارُ المحوَّلةُ وحساباتُها (شركةُ العرض) — بالتوازي مع `$ROLES` في الهجرة. */
$CONVERTED = array(
    array('role' => 1, 'user' => 'محمد',  'title' => 'ادارة التشغيل'),
    array('role' => 2, 'user' => 'مصعب',  'title' => 'ادارة الموردين'),
);
/** دورُ الضبطِ — خارجَ القائمةِ فيجب أن يبقى على وجهتِه القديمة. */
$CONTROL = array('role' => 6, 'user' => 'حركة - الروسية', 'title' => 'إدارة الموقع');

$pass = 0; $fail = 0;
function ok($id, $m) { global $pass; $pass++; echo "  ✔ $id  $m\n"; }
function no($id, $m) { global $fail; $fail++; echo "  ✘ $id  $m\n"; }
function ck($id, $c, $m) { $c ? ok($id, $m) : no($id, $m); }

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
    curl_close($ch);
    return array('code' => $code, 'head' => substr((string) $raw, 0, $hs), 'body' => substr((string) $raw, $hs));
}

function login($base, $jar, $user, $pw)
{
    @unlink($jar);
    $g = req($base . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $g['body'], $m);
    $r = req($base . '/login.php', $jar, array(
        'username' => $user, 'password' => $pw,
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
    // الدخولُ الناجح تحويلٌ 302 لا 200 — و200 يعني عودةَ نموذجِ الدخولِ برفض.
    return $r['code'] === 302 && stripos($r['head'], 'login.php') === false;
}

/** جسدُ السايدبارِ وحدَه — `<ul id="sidebarNavList">` حتى إغلاقِ الحاوية.
 *  ◆ صفحةُ لوحةِ الدورِ تحمل بطاقاتِ إجراءٍ من `includes/role_board.php` فيها
 *    روابطُ تايم شيت، وعدُّ روابطِ الصفحةِ كلِّها يخلط لوحةً بسايدبار.
 *  ◆ والتعليقُ ليس رابطًا: كتلُ `insidebar.php` القديمةُ معطَّلةٌ بـ`<!-- -->`
 *    وتصل المتصفحَ نصًّا ميتًا — وعدُّها رصد «رابطًا باقيًا» لا وجودَ له
 *    (رُصد فعلًا في الدور 6: «ساعات اليوم» داخلَ تعليقٍ محسوبةً رابطًا). */
function sidebarHtml($html)
{
    $i = strpos($html, 'id="sidebarNavList"');
    if ($i === false) { return ''; }
    $j = strpos($html, '</div>', strpos($html, '</ul>', $i));
    $cut = substr($html, $i, ($j === false ? strlen($html) : $j) - $i);
    return preg_replace('/<!--.*?-->/s', '', $cut);
}

/** كلُّ href في المقطعِ يطابق مسارًا بعينِه — بالدليلِ لا بالاسمِ وحدَه:
 *  «timesheet.php» جزءٌ من «view_timesheet.php»، فالقياسُ بلا بادئةِ المجلدِ
 *  يعدُّ الجديدَ قديمًا ويُخضِّرُ الفحصَ كذبًا. */
function countHref($html, $route)
{
    if (!preg_match_all('/<a\s[^>]*href="([^"]+)"/i', $html, $m)) { return 0; }
    $n = 0;
    foreach ($m[1] as $h) {
        $p = preg_replace('~^(\.\./)+~', '', strtok($h, '?#'));
        if (strcasecmp(trim($p, '/'), $route) === 0) { $n++; }
    }
    return $n;
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo " رابطُ التايم شيت في السايدبار — إثباتٌ عبر HTTP\n";
echo "═══════════════════════════════════════════════════════════════\n";

$tmp = sys_get_temp_dir();

// ═════════ S1–S3 لكلِّ دورٍ محوَّل ═════════
foreach ($CONVERTED as $c) {
    echo "\n▐ الدور {$c['role']} — {$c['title']} ({$c['user']})\n";
    $jar = $tmp . '/nav_ts_r' . $c['role'] . '.txt';

    if (!login($BASE, $jar, $c['user'], $PW)) {
        no('S0-' . $c['role'], 'تعذَّر الدخول — لم يُقَس هذا الدور');
        continue;
    }
    ok('S0-' . $c['role'], 'دخل');

    $dash = req($BASE . '/main/role_board.php', $jar, null, true);
    $nav  = sidebarHtml($dash['body']);
    if ($nav === '') {
        no('S0b-' . $c['role'], 'لم يُستخرَج جسدُ السايدبارِ (HTTP ' . $dash['code'] . ')');
        continue;
    }

    $nView = countHref($nav, 'Timesheet/view_timesheet.php');
    ck('S1-' . $c['role'], $nView === 1, "روابطُ `view_timesheet.php` في السايدبار = {$nView} (المطلوب 1)");

    $nOld = countHref($nav, 'Timesheet/timesheet.php');
    ck('S2-' . $c['role'], $nOld === 0, "روابطُ `timesheet.php` في السايدبار = {$nOld} (المطلوب 0 — ولا توأمَ مصطنَع)");

    $dest = req($BASE . '/Timesheet/view_timesheet.php', $jar);
    ck('S3a-' . $c['role'], $dest['code'] === 200, 'اتّباعُ الرابط: HTTP ' . $dest['code'] . ' (المطلوب 200 — لا 302 ولا 403)');
    ck('S3b-' . $c['role'], strpos($dest['body'], 'سجل الوحدات اليومية') !== false,
        'عنوانُ «سجل الوحدات اليومية» ظاهرٌ في الصفحة');

    // ═════════ S4 ✖ الشاهدُ على علّةِ الشكوى (بجلسةِ أوّلِ دورٍ يكفي) ═════════
    if ($c === reset($CONVERTED)) {
        $oldDest = req($BASE . '/Timesheet/timesheet.php', $jar);
        $redir   = ($oldDest['code'] === 302 && stripos($oldDest['head'], 'timesheet_type.php') !== false);
        ck('S4', $redir, '`Timesheet/timesheet.php` بلا `?type=` ⇐ HTTP ' . $oldDest['code']
            . ($redir ? ' إلى timesheet_type.php (علّةُ الشكوى)' : ' — لم يُرصد التحويل'));
    }
}

// ═════════ S5 ✚ دورٌ خارجَ القائمةِ على حالِه ═════════
echo "\n▐ الضبط — الدور {$CONTROL['role']} ({$CONTROL['title']}) خارجَ القائمة\n";
$jarC = $tmp . '/nav_ts_ctrl.txt';
if (!login($BASE, $jarC, $CONTROL['user'], $PW)) {
    no('S5', 'تعذَّر الدخولُ بحسابِ الضبط — لم يُقَس أثرُ العزل');
} else {
    $dC   = req($BASE . '/main/role_board.php', $jarC, null, true);
    $navC = sidebarHtml($dC['body']);
    $nOld  = countHref($navC, 'Timesheet/timesheet.php');
    $nView = countHref($navC, 'Timesheet/view_timesheet.php');
    ck('S5', $nOld >= 1 && $nView === 0,
        "`timesheet.php` = {$nOld} · `view_timesheet.php` = {$nView} (المطلوب ≥1 و0)");
}

echo "\n───────────────────────────────────────────────────────────────\n";
echo " النتيجة: ✔ {$pass}   ✘ {$fail}\n";
echo "───────────────────────────────────────────────────────────────\n";
exit($fail === 0 ? 0 : 1);
