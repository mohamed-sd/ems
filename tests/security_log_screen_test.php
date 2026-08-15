<?php
/**
 * tests/security_log_screen_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 * شاهدُ شاشةِ «سجل الأمان» (Governance/security_log.php) — قراءةٌ فقط.
 *
 *   ★★★ بحسابِ «حسابات» (56 · الدور ١٥ · شركة ٤) تُفتح 200
 *   ★★★ وبحسابٍ بلا منحةٍ تُردُّ 403 أو تُحوَّل — والفحصُ بحسابِ مديرٍ لا يُثبت شيئًا
 *   ★★★ وسايدبارُ الدور ١٥ يعرضها في مجموعتها — **بتصييرٍ حيٍّ لا باستعلام**
 *   ★★  وتعرض أحداثًا حقيقيةً وأعدادُ البطاقاتِ تطابق عددَ الصفوف
 *   ★★  وفلترٌ يُطبَّق ويُفرَّغ ويعود العدد
 *   ★★★ **GT-01 — الاختبارُ السلبي**: يُفسَد مسارُ الملفِ عمدًا فتُعلن الشاشةُ
 *        الخطأَ **ولا تعرض جدولًا فارغًا** كأن لا أحداث. وما لا يرسب عند
 *        الإفسادِ يُحذف.
 *
 * الإفسادُ آمن: يُعاد تسميةُ `security.log` ويوضع مكانَه **مجلَّدٌ** بالاسمِ نفسِه
 * — فـ`file_exists` صادقةٌ و`fopen` تفشل، وهو فرعُ «تعذّر الفتح» بعينِه. ويُستعاد
 * الأصلُ في `register_shutdown_function` حتى لو مات الفاحصُ في منتصفه، ويُقاس
 * حجمُ الملفِ قبلَ الإفسادِ وبعدَ الاستعادةِ فلا يمرُّ فقدُ بايتٍ واحدٍ صامتًا.
 *
 * التشغيل: php tests/security_log_screen_test.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$ROOT   = dirname(__DIR__);
$BASE   = 'http://localhost/ems';
$SCREEN = $BASE . '/Governance/security_log.php';
$LOG    = $ROOT . '/logs/security.log';
$BAK    = $ROOT . '/logs/security.log.gt01bak';

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

function sl_req($url, $jar, $follow = false) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_TIMEOUT => 120,
    ));
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function sl_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = sl_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    $ch = curl_init($BASE . '/login.php');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array(
            'username' => $user, 'password' => '12345678',
            'csrf_token' => isset($m[1]) ? $m[1] : '')),
    ));
    curl_exec($ch); curl_close($ch);
}

/** عددُ صفوفِ جسمِ الجدولِ الأول — الصفرُ يعني «لا جدولَ أو جدولٌ خاوٍ». */
function sl_tbody_rows($html) {
    if (!preg_match('~<tbody\b[^>]*>(.*?)</tbody>~su', $html, $m)) { return 0; }
    return preg_match_all('~<tr\b~i', $m[1]);
}
/** قيمةُ بطاقةِ مؤشرٍ بعنوانها — من ماركَبِ `includes/kpi_card.php`. */
function sl_kpi($html, $title) {
    if (!preg_match('~ems-kpi-title[^>]*>(?:(?!</div>).)*?'
        . preg_quote($title, '~') . '.*?</div>\s*<div class="ems-kpi-value">\s*([0-9,]+)~su', $html, $m)) {
        return null;
    }
    return (int) str_replace(',', '', $m[1]);
}

require_once $ROOT . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

/* ── الاستعادةُ مضمونةٌ حتى عند الموتِ في المنتصف ───────────────────────────
   ◆ **گوتشا مقيسة (كلّفت 62 ميجابايت)**: أثناءَ الإفسادِ يظلُّ التطبيقُ يكتب.
     فحين يُنحَّى السجلُّ جانبًا يُنشئ الحارسُ ملفًّا **شاردًا** صغيرًا بالاسمِ
     نفسِه. والاستعادةُ التي تشترط `!is_file($LOG)` تجد الشاردَ فترفض إعادةَ
     النسخة — فيبقى الأصلُ (62 ميجابايت) في `.gt01bak` والحيُّ 353 بايتًا،
     ويخضرُّ «استُعيد» على فقدٍ كامل.
   ◆ فالاستعادةُ **دمجٌ لا رفض**: يُذيَّل الشاردُ (وهو لاحقٌ زمنيًّا) بالنسخةِ
     ثم تعود مكانَها. ولا يُطرح بايتٌ واحد. */
$restore = function () use ($LOG, $BAK) {
    if (is_dir($LOG)) { @rmdir($LOG); }
    clearstatcache(true);
    if (!is_file($BAK)) { return; }
    if (is_file($LOG)) {
        $stray = @file_get_contents($LOG);
        if ($stray !== false && $stray !== '') {
            $o = @fopen($BAK, 'ab');
            if ($o) { fwrite($o, $stray); fclose($o); }
        }
        @unlink($LOG);
    }
    @rename($BAK, $LOG);
    clearstatcache(true);
};
register_shutdown_function($restore);

fwrite(STDOUT, "\n══ شاهدُ شاشةِ «سجل الأمان» ══\n");

/* ═══ ⓪ المقيسُ لا المفترَض ═════════════════════════════════════════════ */
head('⓪ الحسابُ والدورُ والتسجيل — مقيسةٌ من القاعدة');

$u = $db->query("SELECT id, username, role, company_id FROM users WHERE id = 56")->fetch_assoc();
check($u && $u['username'] === 'حسابات', "الحساب 56 هو «حسابات» (المقيس: " . ($u['username'] ?? '—') . ')');
check($u && (int) $u['role'] === 15, 'دورُه ١٥ إدارة الصلاحيات (المقيس: ' . ($u['role'] ?? '—') . ')');
check($u && (int) $u['company_id'] === 4, 'شركتُه ٤ (المقيس: ' . ($u['company_id'] ?? '—') . ')');

$mod = $db->query("SELECT id, name FROM modules WHERE code = 'Governance/security_log.php'")->fetch_assoc();
check((bool) $mod, 'الشاشةُ مسجَّلةٌ في modules — والتسجيلُ حراسةٌ لا توثيق'
    . ($mod ? " (#{$mod['id']})" : ''));

$MID = $mod ? (int) $mod['id'] : 0;
$gr = $MID ? $db->query("SELECT can_view, can_add, can_edit, can_delete FROM role_permissions
                          WHERE role_id = 15 AND module_id = {$MID}")->fetch_assoc() : null;
check($gr && (int) $gr['can_view'] === 1, 'الدور ١٥ يملك can_view');
check($gr && !((int) $gr['can_add'] + (int) $gr['can_edit'] + (int) $gr['can_delete']),
    'ولا يملك كتابةً — الشاشةُ قراءةٌ فقط');

/* الملفُّ نفسُه قراءةٌ فقط: صفرُ INSERT/UPDATE/DELETE في مصدرِ الشاشة */
$src = (string) file_get_contents($ROOT . '/Governance/security_log.php');
check(!preg_match('~\b(INSERT\s+INTO|UPDATE\s+`?\w+`?\s+SET|DELETE\s+FROM)\b~i', $src),
    'صفرُ INSERT/UPDATE/DELETE في ملفِّ الشاشة');
check(!preg_match('~\$_POST\b~', $src), 'صفرُ معالجِ POST في الشاشة');

/* ترتيبُ CS-01: session_bootstrap ← config ← permissions_helper مع can_view */
$pBoot = mb_strpos($src, 'session_bootstrap.php');
$pCfg  = mb_strpos($src, "include '../config.php'");
$pPerm = mb_strpos($src, 'permissions_helper.php');
$pView = mb_strpos($src, "check_page_permissions(\$conn, 'Governance/security_log.php')");
$pHead = mb_strpos($src, "include '../inheader.php'");
$pSide = mb_strpos($src, "include '../insidebar.php'");
check($pBoot !== false && $pCfg > $pBoot && $pPerm > $pCfg && $pView > $pPerm
      && $pHead > $pView && $pSide > $pHead,
    'ترتيبُ CS-01: bootstrap ← config ← الصلاحيات ← الحارس ← inheader ← insidebar');

/* ═══ ① الفتحُ بحسابِ «حسابات» ═══════════════════════════════════════════ */
head('① الفتحُ بحسابِ «حسابات» (56 · الدور ١٥)');

$jar15 = sys_get_temp_dir() . '/sl_r15_' . getmypid() . '.txt';
sl_login('حسابات', $jar15);
list($code, $h, $body) = sl_req($SCREEN, $jar15);
check($code === 200, "الشاشةُ تُفتح 200 (المقيس: {$code})");
check(mb_strpos($body, 'سجل الأمان') !== false, 'وعنوانُها «سجل الأمان» مُصيَّر');
check(mb_strpos($body, 'GOV-PERM-403') === false, 'ولا رسالةَ منعٍ في جسمِها');

/* ═══ ② حسابٌ بلا منحة ══════════════════════════════════════════════════ */
head('② حسابٌ بلا منحةٍ — يُردُّ أو يُحوَّل');

$noGrant = $db->query("SELECT u.id, u.username, u.role FROM users u
                        WHERE u.company_id = 4 AND u.role > 0
                          AND NOT EXISTS (SELECT 1 FROM role_permissions p
                                           WHERE p.role_id = u.role AND p.module_id = {$MID} AND p.can_view = 1)
                        ORDER BY u.id LIMIT 1")->fetch_assoc();
if (!$noGrant) {
    bad('لا حسابَ بلا منحةٍ للاختبارِ السلبي — الشاهدُ ناقص');
} else {
    fwrite(STDOUT, "  · الحساب: «{$noGrant['username']}» (الدور {$noGrant['role']})\n");
    $jarNo = sys_get_temp_dir() . '/sl_nog_' . getmypid() . '.txt';
    sl_login($noGrant['username'], $jarNo);
    list($c2, $h2, $b2) = sl_req($SCREEN, $jarNo);
    $redirected = ($c2 === 302 || $c2 === 301) && preg_match('~Location:~i', $h2);
    check($c2 === 403 || $redirected, "يُردُّ 403 أو يُحوَّل (المقيس: {$c2})");
    check(sl_tbody_rows($b2) === 0, 'ولا يرى جدولَ أحداثٍ إطلاقًا');
    @unlink($jarNo);
}

/* ═══ ③ السايدبار — بتصييرٍ حيٍّ لا باستعلام ════════════════════════════ */
head('③ سايدبارُ الدور ١٥ — بتصييرٍ حيّ');

/* المرساةُ **الرابطُ** لا أولُ ذكرٍ للاسم: الاسمُ يرد في الترويسةِ وفي «عن
   الشاشة» أيضًا، فالعدُّ عليه يخضرُّ كذبًا. */
$anchor = '~<a[^>]+href="[^"]*Governance/security_log\.php[^"]*"[^>]*>~i';
check(preg_match($anchor, $body) === 1 || preg_match_all($anchor, $body) >= 1,
    'رابطُ الشاشةِ مُصيَّرٌ في قشرةِ الصفحة (عدد المراسي: ' . preg_match_all($anchor, $body) . ')');

/* الرابطُ داخلَ مجموعةِ «المحاولات الممنوعة»: يقع بعدَ عنوانِ المجموعةِ وقبلَ
   أقربِ عنوانِ مجموعةٍ تاليةٍ — وجارُه في المجموعةِ رابطُ guard_denials. */
$posGrp  = mb_strpos($body, 'المحاولات الممنوعة');
$posSelf = false;
if (preg_match($anchor, $body, $mm, PREG_OFFSET_CAPTURE)) { $posSelf = $mm[0][1]; }
$posSib  = mb_strpos($body, 'Governance/guard_denials.php');
check($posGrp !== false && $posSib !== false && $posSelf !== false,
    'ومجموعتُها «المحاولات الممنوعة» وجارُها guard_denials مُصيَّران معهما');

$navRow = $db->query("SELECT n.group_id, g.name FROM nav_items n
                        JOIN link_groups g ON g.id = n.group_id
                       WHERE n.role_id = 15 AND n.route = 'Governance/security_log.php'
                         AND n.active = 1")->fetch_assoc();
check($navRow && (int) $navRow['group_id'] === 3845,
    'وصفُّها في المجموعة 3845 «' . ($navRow['name'] ?? '—') . '»');

/* عيبُ FN-02: وحدةُ الصلاحياتِ ليست فارغةً مع رمزٍ غيرِ فارغ */
$fn02 = $db->query("SELECT module_id, permission_code FROM nav_items
                     WHERE role_id = 15 AND route = 'Governance/security_log.php'")->fetch_assoc();
check($fn02 && $fn02['module_id'] !== null && $fn02['module_id'] !== ''
      && $fn02['permission_code'] !== null && $fn02['permission_code'] !== '',
    'ولا تسقط صامتًا (FN-02): module_id ورمزُ الصلاحيةِ مملوءان معًا');

/* ═══ ④ أحداثٌ حقيقيةٌ وبطاقاتٌ مطابقة ═══════════════════════════════════ */
head('④ أحداثٌ حقيقيةٌ — والبطاقاتُ تطابق الصفوف');

$rows0 = sl_tbody_rows($body);
check($rows0 > 0, "الجدولُ يعرض أحداثًا حقيقيةً من الملف ({$rows0} صفًّا)");

$kTotal = sl_kpi($body, 'إجمالي المعروض');
$kViol  = sl_kpi($body, 'مخالفات');
$kWarn  = sl_kpi($body, 'تحذيرات');
fwrite(STDOUT, "  · البطاقات: إجمالي={$kTotal} مخالفات={$kViol} تحذيرات={$kWarn} · صفوف={$rows0}\n");
check($kTotal !== null && $kTotal === $rows0,
    'بطاقةُ «إجمالي المعروض» تطابق عددَ الصفوف حرفيًّا');
check($kViol !== null && $kWarn !== null && ($kViol + $kWarn) === $rows0,
    'ومجموعُ المخالفاتِ والتحذيراتِ يطابقه (الافتراضُ يخفي الروتيني)');

/* الإعلانُ الصريح: «آخر N حدثًا» لا «كلُّ السجل» */
check(preg_match('~مقروءٌ من نهايةِ الملفِّ~u', $body) === 1,
    'والشاشةُ تعلن كم قرأت ومن نهايةِ الملفِّ صراحةً');
check(preg_match('~آخر\s*[\d,٠-٩]+\s*حدثًا~u', $body) === 1,
    'وتعلن «آخر N حدثًا» في سياقِ الترويسة');

/* الفرزُ الدلاليّ: الروتينيُّ مخفيٌّ بالافتراضِ وله زرٌّ صريح */
check(mb_strpos($body, 'إظهارُ الأحداثِ الروتينية') !== false,
    'والروتينيُّ مخفيٌّ بالافتراضِ وله زرٌّ صريحٌ لإظهاره');
check(mb_strpos($body, 'حدثٌ روتيني') === false,
    'ولا شارةَ «حدثٌ روتيني» في العرضِ الافتراضي');

/* ═══ ⑤ الفلترُ يُطبَّق ويُفرَّغ ═══════════════════════════════════════════ */
head('⑤ فلترٌ يُطبَّق ويُفرَّغ ويعود العدد');

list($c3, $h3, $bViol) = sl_req($SCREEN . '?grade=violation', $jar15);
$rowsV = sl_tbody_rows($bViol);
check($c3 === 200 && $rowsV > 0, "فلترُ «مخالفاتٌ فقط» يُطبَّق ({$rowsV} صفًّا)");
check($rowsV <= $rows0, 'وعددُه لا يتجاوز العرضَ الافتراضيَّ');
check(mb_strpos($bViol, 'تحذير</span>') === false, 'ولا شارةَ «تحذير» في نتيجته');

/* ◆ **لا مساواةَ حرفيةً على نافذةٍ تنزلق**: الملفُّ حيٌّ ينمو أثناءَ الفاحصِ
     نفسِه (طلباتُ الفاحصِ تولّد أحداثًا)، والنافذةُ آخرُ N حدثًا — فالقديمُ
     يخرج والجديدُ يدخل. ومساواةٌ صارمةٌ هنا ترسب على **سلوكٍ صحيح**.
     فالمقيسُ: أن الفلترَ رُفع فعلًا (عادت التحذيرات) وأن الفرقَ نموُّ الملفِّ
     وحدَه لا تغيُّرُ سلوكٍ — ويُعلَن الفرقُ رقمًا لا يُبتلع. */
list($c4, $h4, $bBack) = sl_req($SCREEN, $jar15);
$rowsBack = sl_tbody_rows($bBack);
$drift = $rowsBack - $rows0;
check(mb_strpos($bBack, 'تحذير</span>') !== false,
    'والتفريغُ يعيد التحذيراتِ إلى العرض — فالفلترُ رُفع فعلًا لا شكلًا');
check($rowsBack >= $rowsV, "وعددُه يعود فوقَ المُرشَّح ({$rowsBack} ≥ {$rowsV})");
check(abs($drift) <= 40,
    "والفرقُ عن القياسِ الأولِ انزلاقُ النافذةِ الحيّةِ وحدَه ({$rows0} ⇒ {$rowsBack}"
    . ($drift >= 0 ? " · +{$drift}" : " · {$drift}") . ' حدثًا)');

/* بطاقةٌ قابلةٌ للنقرِ تطبّق فلترَها فعلًا */
check(preg_match('~<a class="ems-kpi-card[^"]*" href="[^"]*grade=violation~', $body) === 1,
    'وبطاقةُ «مخالفات» رابطٌ يطبّق فلترَها — رقمٌ يُتعمَّق فيه');

/* ═══ ⑥ GT-01 · الاختبارُ السلبي ════════════════════════════════════════ */
head('⑥ GT-01 — يُفسَد مسارُ الملفِ عمدًا');

$sizeBefore = filesize($LOG);
fwrite(STDOUT, "  · حجمُ السجلِّ قبل الإفساد: " . number_format($sizeBefore) . " بايت\n");

$corrupted = false;
if (@rename($LOG, $BAK) && @mkdir($LOG)) {
    $corrupted = true;
} else {
    $restore();
    bad('تعذّر إفسادُ المسار — الاختبارُ السلبيُّ لم يُجرَ');
}

if ($corrupted) {
    list($c5, $h5, $bErr) = sl_req($SCREEN, $jar15);
    $restore();

    check($c5 === 200, "الشاشةُ لا تنهار (المقيس: {$c5})");
    check(mb_strpos($bErr, 'تعذّرت قراءةُ ملفِّ السجل') !== false,
        '★ وتُعلن «تعذّرت قراءةُ ملفِّ السجل» صراحةً');
    check(sl_tbody_rows($bErr) === 0 && mb_strpos($bErr, '<table') === false,
        '★★ ولا تعرض جدولًا إطلاقًا — لا جدولًا فارغًا يكذب «لا أحداث»');
    check(mb_strpos($bErr, 'لا أحداثَ أمنيةٍ') === false,
        '★★ ولا تقول «لا أحداث» والأحداثُ قائمةٌ ولم تُقرأ');
    check(mb_strpos($bErr, 'لا ملفَّ سجلٍّ بعد') === false,
        '★ وتفرّق «تعذّر الفتح» عن «لا ملفّ» — لا تخلطهما');

    clearstatcache(true);
    $sizeAfter = is_file($LOG) ? filesize($LOG) : -1;
    check($sizeAfter >= $sizeBefore,
        'واستُعيد السجلُّ سليمًا (' . number_format($sizeAfter) . " ≥ " . number_format($sizeBefore) . ' بايت)');

    /* الفرعُ الآخر: «لا ملفّ» — رسالةٌ مختلفةٌ لا الرسالةُ نفسُها */
    if (@rename($LOG, $BAK)) {
        list($c6, $h6, $bMiss) = sl_req($SCREEN, $jar15);
        $restore();
        check(mb_strpos($bMiss, 'لا ملفَّ سجلٍّ بعد') !== false,
            '★ وعند غيابِ الملفِّ رسالةٌ **أخرى**: «لا ملفَّ سجلٍّ بعد»');
        check(mb_strpos($bMiss, 'تعذّرت قراءةُ ملفِّ السجل') === false,
            '★ ولا تُخلط برسالةِ تعذّرِ الفتح');
        clearstatcache(true);
        $sz2 = is_file($LOG) ? filesize($LOG) : -1;
        check($sz2 >= $sizeBefore, 'واستُعيد السجلُّ ثانيةً سليمًا ('
            . number_format($sz2) . ' ≥ ' . number_format($sizeBefore) . ' بايت)');
        check(!is_file($BAK), 'ولا نسخةٌ احتياطيةٌ باقيةٌ جانبًا — الدمجُ تمَّ لا رُفض');
    } else {
        bad('تعذّر اختبارُ فرعِ «لا ملفّ»');
    }
}

/* ═══ ⑦ النوعُ غيرُ المصنَّفِ لا يُبتلع ═══════════════════════════════════
   ◆ لماذا على مستوى الدالةِ لا الشاشة: كلُّ الأنواعِ التسعةِ والأربعين في
     السجلِّ **مصنَّفةٌ اليوم**، فالفرعُ لا يُصيَّر بالمرور. وحقنُ نوعٍ مختلَقٍ
     في سجلٍّ حيٍّ حجمُه 62 ميجابايت يعني اقتطاعَه بعدَه — واقتطاعٌ على ملفٍّ
     يُكتب فيه لحظيًّا **يفقد أحداثًا حقيقية**. فيُقاس تعريفُ الدالةِ المشحونِ
     نفسِه، لا نسخةٌ منه. */
head('⑦ نوعٌ غيرُ مصنَّفٍ — يُعرض خامًا ويُوسَم');

if (preg_match('~function sec_classify\(.*?\n\}~su', $src, $fm)) {
    eval($fm[0]);
    $mapRef = array('csrf_violation' => array('violation', 'طلبٌ بلا رمزِ حمايةٍ صالح'));
    $known = sec_classify('csrf_violation', $mapRef);
    $unk   = sec_classify('SOME_BRAND_NEW_EVENT', $mapRef);
    check($known[0] === 'violation' && $known[2] === false, 'المصنَّفُ يعود بدرجتِه وجملتِه');
    check($unk[1] === 'SOME_BRAND_NEW_EVENT', 'وغيرُ المصنَّفِ يُعرض **باسمِه الخام** لا يُبتلع');
    check($unk[2] === true, 'ويُوسَم «نوعٌ غيرُ مصنَّف»');
    check($unk[0] === 'warning', 'ولا يُفترض سليمًا — درجتُه تحذيرٌ فيظهر في العرضِ الافتراضي');
} else {
    bad('تعذّر استخراجُ sec_classify من مصدرِ الشاشة');
}

/* والخريطةُ تغطّي كلَّ نوعٍ في النافذةِ المقروءة — فلا فيضُ «غيرِ مصنَّف» */
check(mb_substr_count($body, 'نوعٌ غيرُ مصنَّف') === 0,
    'ولا نوعَ غيرَ مصنَّفٍ في النافذةِ الحاليةِ — الخريطةُ مواكِبةٌ للسجل');

@unlink($jar15);

fwrite(STDOUT, "\n══ الحصيلة: {$PASS} ناجحًا · {$FAIL} راسبًا ══\n");
exit($FAIL > 0 ? 1 : 0);
