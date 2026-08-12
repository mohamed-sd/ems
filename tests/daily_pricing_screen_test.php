<?php
/**
 * tests/daily_pricing_screen_test.php — شاشةُ التسعيرِ اليوميِّ للمالية
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالك 2026-08-12**: مصدرُ الأسعارِ «التحديثُ الوقتيُّ … **من الإدارةِ
 * المالية** … مع إمكانيةِ تحديدِ السعرِ لليومِ بشكلٍ يوميّ».
 *
 * ── ولماذا شاشةٌ مستقلّةٌ لا منحُ الماليةِ شاشةَ العقود ─────────────────────
 * `Contracts/price_terms.php` فيه أربعةُ أفعالٍ وبتَّان فقط، و`add_reading`
 * (عملُ المالية) يتقاسم `can_add` مع `save_term` (**بندُ العقدِ** · الدور 12).
 * فمنحُ الماليةِ `can_add` هناك يمنحُها كتابةَ بنودِ العقود — **منحٌ زائدٌ لا
 * يطلبه القرار**. وهذا الفاحصُ يحرس الفصلَ: يُثبِت أنَّ الماليةَ تُسعّر هنا
 * **ولا** تكتب بندَ عقدٍ هناك.
 *
 * ◆ ويُقاس على HTTP بثلاثةِ أدوارٍ — لا على المصدرِ وحدَه: المنحُ في جدولٍ
 *   والتصييرُ في ملفٍّ، وبينهما حارسٌ قد لا يُنادى.
 * ◆ وشاشةٌ **غيرُ مسجَّلةٍ في `modules` تصير مفتوحةً للجميع** (جذرُ INJ-0008)،
 *   فالتسجيلُ شرطُ أمنٍ يُفحَص لا ترتيبُ قوائم.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$CODE = 'Finance/daily_pricing_fin.php';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $extra = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($extra !== '' ? "  ⟵ {$extra}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ شاشةُ التسعيرِ اليوميِّ للمالية — قرارُ المالك 2026-08-12');

/* ══ ① الملفُّ والموديولُ والمنحُ ═════════════════════════════════════════════ */
$file = $ROOT . '/' . $CODE;
$ok(is_file($file), 'الملفُّ قائمٌ: ' . $CODE);
$src = is_file($file) ? (string) file_get_contents($file) : '';

$esc = $conn->real_escape_string($CODE);
$m = $conn->query("SELECT id, owner_role_id FROM modules WHERE code = '{$esc}'");
$m = ($m && $m->num_rows) ? $m->fetch_assoc() : null;
$ok($m !== null, 'الموديولُ مسجَّلٌ — وشاشةٌ غيرُ مسجَّلةٍ تصير مفتوحةً للجميع',
    'جذرُ INJ-0008: التسجيلُ شرطُ أمنٍ لا ترتيبُ قوائم');
if ($m === null) { $say(''); $say("PASS={$PASS} · FAIL={$FAIL}"); exit(1); }
$MID = (int) $m['id'];
$ok((int) $m['owner_role_id'] === 17, 'ومالكُها الدور 17 (إدارةُ المالية) — بنصِّ القرار',
    'المالكُ ' . $m['owner_role_id']);

$grants = array();
$r = $conn->query("SELECT role_id, can_view, can_add, can_edit, can_delete
                     FROM role_permissions WHERE module_id = {$MID}");
while ($r && ($x = $r->fetch_assoc())) { $grants[(string) $x['role_id']] = $x; }
$ok(isset($grants['17']) && (int) $grants['17']['can_add'] === 1,
    'الدور 17 يُسعّر (can_add)');
$ok(isset($grants['19']) && (int) $grants['19']['can_add'] === 1,
    'والدور 19 يُسعّر ويعتمد');
$ok(isset($grants['12']) && (int) $grants['12']['can_view'] === 1
    && (int) $grants['12']['can_add'] === 0 && (int) $grants['12']['can_edit'] === 0,
    'والدور 12 (العقود) **عرضٌ فقط** — يرى ما سُعِّر ولا يُسعّر');
foreach ($grants as $rid => $g) {
    if ((int) $g['can_delete'] === 1) {
        $ok(false, 'لا حذفَ لأحدٍ — السعرُ المسجَّلُ واقعة', 'الدور ' . $rid . ' يملك حذفًا');
    }
}
$ok(count(array_diff(array_keys($grants), array('17', '19', '12'))) === 0,
    'ولا دورَ رابعًا مُنح (' . implode(' · ', array_keys($grants)) . ') — المنحُ محدودٌ لا مفتوح');

/* ══ ② لا منحَ زائدًا: الماليةُ لا تكتب بندَ العقد ═══════════════════════════ */
$tm = $conn->query("SELECT id FROM modules WHERE code = 'Contracts/price_terms.php'");
$tm = ($tm && $tm->num_rows) ? (int) $tm->fetch_assoc()['id'] : 0;
$ok($tm > 0, 'شاشةُ بنودِ العقدِ مسجَّلةٌ (#' . $tm . ') — بغيرِها لا يُقاس الفصل');
if ($tm > 0) {
    $bad = array();
    $r = $conn->query("SELECT role_id FROM role_permissions
                        WHERE module_id = {$tm} AND role_id IN (17, 19)
                          AND (can_add = 1 OR can_edit = 1)");
    while ($r && ($x = $r->fetch_assoc())) { $bad[] = (string) $x['role_id']; }
    $ok(count($bad) === 0,
        '**الفصلُ قائمٌ**: لا دورَ ماليةٍ يكتب بندَ عقدٍ',
        'أدوارٌ بمنحٍ زائد: ' . implode(' · ', $bad));
    /* وغيرُ خاوٍ: الدورُ 12 **يملك** الكتابةَ هناك — وإلا كان الفحصُ يقيس جدولًا خاويًا */
    $has12 = $conn->query("SELECT COUNT(*) n FROM role_permissions
                            WHERE module_id = {$tm} AND role_id = 12 AND can_add = 1");
    $has12 = $has12 ? (int) $has12->fetch_assoc()['n'] : 0;
    $ok($has12 > 0, 'والدور 12 يملكها هناك — فالفحصُ يميّز ولا يقيس فراغًا',
        'لو لم يملكها أحدٌ لصدَق الفحصُ أعلاه تحصيلَ حاصل');
}

/* ══ ③ الحجبُ **قبلَ** أوّلِ استعلامٍ — لا بعده (درسُ INJ-0454) ═════════════════ */
$posGuard = strpos($src, 'enforce_current_page_view_permission');
$posQuery = strpos($src, '$conn->query');
$ok($posGuard !== false, 'الحارسُ المركزيُّ مُنادًى في الملف');
$ok($posGuard !== false && $posQuery !== false && $posGuard < $posQuery,
    'وهو **قبلَ** أوّلِ استعلامٍ في الملف',
    'حارسٌ بعد الاستعلامِ يعني قراءةً تمَّت ثم قيل «لا صلاحية»');
$ok(strpos($src, 'csrf_field()') !== false, 'ونماذجُ POST تحمل رمزَ الحماية',
    'الحقنُ الآليُّ يشمل fetch/XHR فقط — فالنموذجُ العاديُّ يُردُّ 403 بدونه');

/* ══ ④ جدولٌ فارغٌ لا يعني «لا بيانات» ═══════════════════════════════════════ */
$ok(strpos($src, '$qErrors') !== false && strpos($src, 'استعلامُ عرضٍ فشل') !== false,
    'الشاشةُ تميّز فشلَ الاستعلامِ من فراغِ النتيجةِ وتُعلنه',
    'وقع فعلًا: c.contract_no غيرُ موجودٍ فظهرت 3 بنودٍ صفرًا والصفحةُ 200');
/* ◆ **يُقرأ الكودُ لا التعليق.** أوّلُ صياغةٍ فحصت النصَّ الخامَ فسقطت على
     **تعليقي أنا** الذي يشرح العيبَ («كتبتُ `c.contract_no` …») — فحصٌ يقيس
     غيرَ ما يدّعي. فتُنزَع التعليقاتُ أولًا بـ`token_get_all` (لا بتعبيرٍ نمطيٍّ
     يخطئ في النصوصِ)، ثم يُفحَص ما يُنفَّذ فعلًا. */
$codeOnly = '';
foreach (token_get_all($src) as $tk) {
    if (is_array($tk)) {
        if ($tk[0] === T_COMMENT || $tk[0] === T_DOC_COMMENT) { continue; }
        $codeOnly .= $tk[1];
    } else { $codeOnly .= $tk; }
}
$ok(strpos($codeOnly, 'contract_no') === false,
    'ولا عمودَ contract_no في كودٍ منفَّذٍ (المعرِّفُ id والطرفُ second_party)',
    'ذكرُه في تعليقٍ مقصودٌ — يشرح العيبَ الذي وقع');

/* ══ ⑤ القياسُ الحيُّ على HTTP — ثلاثةُ أدوارٍ ═══════════════════════════════ */
$BASE = 'http://localhost/ems';
$probe = function ($who) use ($BASE) {
    $J = sys_get_temp_dir() . '/dps_' . md5($who) . '.txt';
    @unlink($J);
    $rq = function ($u, $post = null, $follow = true) use ($J) {
        $ch = curl_init($u);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_HEADER => !$follow, CURLOPT_COOKIEJAR => $J, CURLOPT_COOKIEFILE => $J,
            CURLOPT_TIMEOUT => 90, CURLOPT_POST => $post !== null));
        if ($post !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
        $b = curl_exec($ch);
        $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array($c, (string) $b);
    };
    list(, $p) = $rq($BASE . '/login.php');
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $p, $tk);
    $rq($BASE . '/login.php', array('username' => $who, 'password' => '12345678',
                                    'csrf_token' => isset($tk[1]) ? $tk[1] : ''));
    /* ◆ بلا متابعةٍ لرؤيةِ 302 الحقيقيةِ — فمع المتابعةِ يعود التحويلُ 200 */
    list($raw, ) = $rq($BASE . '/Finance/daily_pricing_fin.php', null, false);
    list(, $body) = $rq($BASE . '/Finance/daily_pricing_fin.php');
    @unlink($J);
    return array('code' => $raw, 'body' => $body,
                 'title' => preg_match('~<title>([^<]{0,50})~i', $body, $t) ? trim($t[1]) : '?',
                 'form' => strpos($body, 'record_price') !== false,
                 'qerr' => strpos($body, 'استعلامُ عرضٍ فشل') !== false,
                 'boom' => preg_match('~(Fatal error|Parse error|Uncaught|Undefined (variable|function))~', $body) === 1);
};
/** يجدُ مستخدمًا حيًّا لدورٍ — ولا يُختلق اسمٌ */
$userOf = function ($role) use ($conn) {
    $r = $conn->query("SELECT username FROM users WHERE company_id = 4 AND role = " . (int) $role
                      . " AND COALESCE(is_deleted,0) = 0 LIMIT 1");
    return ($r && $r->num_rows) ? (string) $r->fetch_assoc()['username'] : null;
};
$u17 = $userOf(17); $u12 = $userOf(12); $u25 = $userOf(25);
$ok($u17 !== null && $u12 !== null && $u25 !== null,
    'وُجد مستخدمٌ حيٌّ للأدوارِ الثلاثةِ (17 · 12 · 25)',
    '17=' . var_export($u17, true) . ' 12=' . var_export($u12, true) . ' 25=' . var_export($u25, true));

if ($u17 !== null && $u12 !== null && $u25 !== null) {
    $r17 = $probe($u17);
    $ok((int) $r17['code'] === 200, 'الماليةُ (17) تفتح الشاشةَ — كودٌ ' . $r17['code']);
    $ok(strpos($r17['title'], 'التسعير اليومي') !== false,
        'وعنوانُها هو المطلوبُ لا صفحةَ دخولٍ ولا داشبوردًا (' . $r17['title'] . ')');
    $ok($r17['form'] === true, '**ونموذجُ تسجيلِ السعرِ ظاهرٌ لها**');
    $ok($r17['boom'] === false, 'وصفرُ انفجارٍ أو متغيّرٍ غيرِ معرَّف');
    $ok($r17['qerr'] === false, 'وصفرُ استعلامِ عرضٍ فاشلٍ');

    $r12 = $probe($u12);
    $ok((int) $r12['code'] === 200, 'والعقودُ (12) تفتحها عرضًا — كودٌ ' . $r12['code']);
    $ok($r12['form'] === false,
        '**ولا نموذجَ تسجيلٍ لها** — عرضٌ بلا تسعير',
        'لو ظهر لها النموذجُ لكان المنحُ زائدًا في التصيير');

    $r25 = $probe($u25);
    $ok((int) $r25['code'] === 302,
        'ودورٌ بلا منحٍ (25) يُحجَب بـ302 — كودٌ ' . $r25['code'],
        'ولو كان 200 لكانت الشاشةُ مفتوحةً لغيرِ أهلِها');
} else {
    $say('  ○ نقصُ مستخدمٍ لأحدِ الأدوارِ — فروعُ HTTP لا يُحكَم عليها');
}

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
