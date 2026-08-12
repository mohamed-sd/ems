<?php
/**
 * tests/fixc_finance_nav_test.php — شاهدُ FIXC-0002 (P0) و FIXC-0008
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: FIXC-0002-أ · FIXC-0002-ب · FIXC-0002-ج · FIXC-0008-أ · FIXC-0008-ب
 *
 * بُني هذا الفاحصُ لأنَّ **616 حكمًا من 619** في حزمِ التصحيحِ كان بلا شاهدٍ يذكره،
 * فنسبةُ إنجازِها غيرُ قابلةٍ للقياس. وهذه أوّلُ دفعةٍ من الشواهدِ المبنيةِ لأحكامٍ
 * بعينِها — **بما تشترطه نصًّا لا بما أظنُّه**.
 *
 * ── نصُّ اختبارِ القبولِ كما في السجل ──────────────────────────────────────────
 * · **FIXC-0002** (عيبُ المالية FN-01 · خطورةٌ **P0**): «سايدبارُ الأدوارِ الثلاثةِ
 *   الجديدةِ فارغ» في الأدوار **31 · 32 · 33** — «فالأدوارُ أُنشئت والقوائمُ لم
 *   تُبنَ». واختبارُ القبولِ: «**الأدوارُ الثلاثةُ تفتح سايدبارًا غيرَ فارغٍ
 *   بمجموعاتِها ومراحلِها**».
 * · **FIXC-0008**: «صفرُ صفِّ تنقلٍ ميتٍ · وصفرُ صفٍّ بوحدةٍ فارغةٍ ورمزٍ غيرِ فارغ».
 *
 * ── وحدُّ هذا الفاحصِ مُعلَنٌ لا مُخفًى ────────────────────────────────────────
 * ◆ يفحص **ما اشترطه الحكمُ**: «غيرُ فارغٍ بمجموعاتِه» — لا «كاملٌ». فالدورُ 32
 *   له رابطان في مجموعتين (و31 له 24 في 5 · و33 له 42 في 7) — فهو **غيرُ فارغٍ**
 *   فالحكمُ متحقّقٌ بنصِّه. ورقّةُ سايدبارِه تُعلَن هنا **ملاحظةً للمالك** لا
 *   رسوبًا، لأنَّ سببَها أنَّ صلاحياتِه سبعٌ (مقابل 26 و47) — ومنحُ صلاحياتٍ
 *   قرارُ أمنٍ لا إصلاحُ فاحص.
 * ◆ وقِيس أنَّ الشاشاتِ «بلا رابطٍ» في الدور 32 هي **بعينِها** التي تنقص الدورَ 33
 *   (بطاقةُ المستخدمِ · البحثُ الموحَّدُ · «قريبًا» · المعاونون) — وكلُّها شاشاتُ
 *   شريطٍ أعلى لا سايدبار. فليست عيبًا، وقد ظننتُها عيبًا أوّلَ قياسٍ فصحّحتُ.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$conn = $GLOBALS['conn'];
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $extra = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($extra !== '' ? "  ⟵ {$extra}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
/** عددٌ بفحصِ المُرجَع — الفشلُ يعود null لا صفرًا */
$one = function ($sql) use ($conn) {
    $r = $conn->query($sql);
    if ($r === false) { return null; }
    $x = $r->fetch_row();
    return $x === null ? null : $x[0];
};
$say('══ FIXC-0002 (P0) و FIXC-0008 — سايدبارُ المالية وسلامةُ التنقّل');

/* ══ ① FIXC-0002: الأدوارُ الثلاثةُ سايدبارُها غيرُ فارغٍ بمجموعاتِه ═══════════ */
$ROLES = array(31 => 'رئيس الحسابات', 32 => 'المدير المالي', 33 => 'المراجع الداخلي المستقل');
$thin = array();
foreach ($ROLES as $rid => $name) {
    $links = $one("SELECT COUNT(*) FROM nav_items WHERE role_id = {$rid} AND active = 1");
    $groups = $one("SELECT COUNT(DISTINCT group_id) FROM nav_items
                     WHERE role_id = {$rid} AND active = 1 AND group_id IS NOT NULL");
    $ok($links !== null && (int) $links > 0,
        "الدور {$rid} ({$name}): سايدبارٌ **غيرُ فارغٍ** — " . var_export($links, true) . ' رابطًا',
        'نصُّ الحكم: «الأدوارُ الثلاثةُ تفتح سايدبارًا غيرَ فارغٍ»');
    $ok($groups !== null && (int) $groups > 0,
        "   وبمجموعاتِه — " . var_export($groups, true) . ' مجموعةً');
    /* مراحلُ المجموعاتِ: «بمجموعاتِها **ومراحلِها**» */
    $stages = $one("SELECT COUNT(DISTINCT g.stage_no) FROM nav_items n
                     JOIN link_groups g ON g.id = n.group_id
                    WHERE n.role_id = {$rid} AND n.active = 1 AND g.stage_no IS NOT NULL");
    $ok($stages !== null && (int) $stages > 0, '   وبمراحلِها — ' . var_export($stages, true) . ' مرحلةً');
    if ((int) $links < 5) { $thin[$rid] = array('name' => $name, 'links' => (int) $links); }
}
/* ◆ الرقّةُ تُعلَن ولا تُرسِب: الحكمُ يشترط «غيرَ فارغٍ» لا «كاملًا» */
if ($thin) {
    foreach ($thin as $rid => $t) {
        $perm = $one("SELECT COUNT(*) FROM role_permissions WHERE role_id = {$rid} AND can_view = 1");
        $say('  ○ ملاحظةٌ للمالك: الدور ' . $rid . ' (' . $t['name'] . ') سايدبارُه رقيقٌ — '
           . $t['links'] . ' رابطًا و' . var_export($perm, true) . ' شاشةً بعرضٍ '
           . '(مقابل 26 للدور 31 و47 للدور 33). وسببُه قلّةُ الصلاحياتِ لا نقصُ الروابط — '
           . 'ومنحُها قرارُ أمنٍ لا إصلاحُ فاحص.');
    }
}

/* ══ ② FIXC-0008: صفرُ صفِّ تنقّلٍ **ميتٍ** ═══════════════════════════════════
     الميتُ = رابطٌ نشطٌ مقصدُه ملفٌّ لا وجودَ له على القرص. والمسارُ يُطبَّع من
     البادئةِ النسبيةِ `../` قبل الفحص (وهي مرفوعةٌ بهجرةِ INJ-0061 لكنَّ الفحصَ
     لا يفترض ذلك — يُطبّع ثم يفحص). */
$dead = array();
$q = $conn->query("SELECT n.id, n.role_id, n.label_ar, n.route FROM nav_items n
                    WHERE n.active = 1 AND n.route IS NOT NULL AND n.route <> ''");
$checked = 0;
while ($q && ($x = $q->fetch_assoc())) {
    $r = ltrim((string) $x['route']);
    if (preg_match('~^(https?:)?//~i', $r)) { continue; }      /* رابطٌ خارجيٌّ لا ملفّ */
    if (strpos($r, '#') === 0) { continue; }
    $r = preg_replace('~^(\.\./)+~', '', $r);
    $r = preg_replace('~[?#].*$~', '', $r);
    if ($r === '' ) { continue; }
    $checked++;
    if (!is_file($ROOT . '/' . $r)) { $dead[] = $x['role_id'] . ':' . $r; }
}
$ok($checked > 100, 'فُحص ' . $checked . ' رابطًا نشطًا (مقصدًا محليًّا)',
    'عددٌ صغيرٌ يعني أنَّ الفحصَ لم يرَ الروابطَ فيصير خاويًا');
$ok(count($dead) === 0, 'صفرُ صفِّ تنقّلٍ **ميتٍ** — كلُّ مقصدٍ له ملفٌّ (' . count($dead) . ')',
    implode(' · ', array_slice(array_unique($dead), 0, 6)));

/* ── وصفرُ صفٍّ «بوحدةٍ فارغةٍ ورمزٍ غيرِ فارغ» ─────────────────────────────
     الوحدةُ = المجموعةُ الحاضنةُ (group_id) · والرمزُ = المقصدُ (route). فرابطٌ له
     مقصدٌ ولا مجموعةَ **لا يُصيَّر في أيِّ منطقةٍ** — فهو موجودٌ ولا يُرى. */
$orphanLinks = $one("SELECT COUNT(*) FROM nav_items
                      WHERE active = 1 AND (group_id IS NULL OR group_id = 0)
                        AND route IS NOT NULL AND route <> ''");
$ok($orphanLinks !== null && (int) $orphanLinks === 0,
    'وصفرُ رابطٍ بمقصدٍ ولا مجموعةَ تحضنه (' . var_export($orphanLinks, true) . ')',
    'رابطٌ بلا مجموعةٍ لا يُصيَّر في أيِّ منطقةٍ — موجودٌ ولا يُرى');

/* ══ ③ القياسُ الحيُّ على HTTP — فالحكمُ يقول «**تفتح** سايدبارًا» ═══════════════ */
$BASE = 'http://localhost/ems';
$probe = function ($user) use ($BASE) {
    $J = sys_get_temp_dir() . '/fxc_' . md5($user) . '.txt';
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
    preg_match('~name=.csrf_token.\s+value=.([^"\']+)~', $p, $t);
    list($lc, ) = $rq($BASE . '/login.php', array('username' => $user, 'password' => '12345678',
                                                  'csrf_token' => isset($t[1]) ? $t[1] : ''));
    list(, $body) = $rq($BASE . '/main/dashboard.php');
    @unlink($J);
    /* عددُ روابطِ السايدبارِ المُصيَّرةِ — بمرساةِ الحاضنِ لا بأوّلِ ذكرٍ */
    $links = 0; $groups = 0;
    if (preg_match('~<(?:nav|aside|div)[^>]*(?:sidebar|insidebar)[^>]*>.*</(?:nav|aside|div)>~si', $body, $m)) {
        $links = preg_match_all('~<a\s[^>]*href~i', $m[0]);
    }
    $groups = preg_match_all('~navgrp-~i', $body);
    return array('login' => $lc, 'title' => preg_match('~<title>([^<]{0,44})~i', $body, $tt) ? trim($tt[1]) : '?',
                 'links' => $links, 'groups' => $groups, 'len' => strlen($body),
                 'boom' => preg_match('~(Fatal error|Parse error|Uncaught)~', $body) === 1);
};
/* ◆ **الحسابُ يُختار بقدرتِه على الدخولِ لا باسمِه.** أوّلُ صياغةٍ لي ثبَّتت
     `FIN-CTRL@equipation.sd` وأختيها فسقطت ثلاثةُ فروعٍ — والسببُ أنَّ كلمةَ هذه
     الحساباتِ هي المحرفُ `!` وحدَه (طولٌ 1)، و`login.php:60` يستعمل
     `password_verify` فيفشل حتمًا. أي أنها **كلمةٌ معطَّلةٌ** بالعُرف، والنظامُ
     مغلقٌ آمنًا — لكنَّ الدورَ لا يستطيع الدخول.
   ◆ فيُبحَث عن حسابٍ **بتعميةٍ سليمةٍ** (`$2y$`)؛ وإن لم يوجد يُعلَن الفرعُ
     غيرَ قابلٍ للحكمِ **بسببِه** ولا يُقرأ رسوبًا — فوضعُ كلمةِ مرورٍ فعلٌ
     يملكه المالكُ لا أنا.
   ◆ وهي فجوةٌ مقيسةٌ تُرفع للمالك: **24 حسابًا** بكلمةٍ معطَّلةٍ في الأدوار
     18 · 31 · 32 · 33 · 34 · 35. */
$USERS = array();
foreach (array_keys($ROLES) as $rid) {
    $q = $conn->query("SELECT username FROM users
                        WHERE role = {$rid} AND COALESCE(is_deleted,0) = 0 AND status = 'active'
                          AND password LIKE '\$2y\$%' LIMIT 1");
    $USERS[$rid] = ($q && $q->num_rows) ? (string) $q->fetch_assoc()['username'] : null;
}
$noLogin = array();
foreach ($USERS as $rid => $u) {
    if ($u === null) {
        $cnt = $one("SELECT COUNT(*) FROM users WHERE role = {$rid} AND COALESCE(is_deleted,0) = 0");
        $noLogin[] = $rid;
        $say('  ○ الدور ' . $rid . ': لا حسابَ بكلمةٍ صالحةٍ (' . var_export($cnt, true)
           . ' حسابًا كلمتُها معطَّلةٌ «!») — فرعُ HTTP لا يُحكَم عليه، ووضعُ كلمةٍ قرارُ المالك');
        continue;
    }
    $r = $probe($u);
    $ok($r['boom'] === false, "الدور {$rid}: لوحتُه تُصيَّر بلا انفجارٍ (" . $r['len'] . ' بايتًا)');
    $ok((int) $r['groups'] > 0,
        "   و**تفتح سايدبارًا** فيه مجموعاتٌ مُصيَّرةٌ — " . $r['groups'] . ' حاضنَ مجموعةٍ',
        'العنوانُ: ' . $r['title'] . ' — ولو كان تحويلًا إلى login لصار الفرعُ خاويًا');
}

$say('');
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL === 0 ? 0 : 1);
