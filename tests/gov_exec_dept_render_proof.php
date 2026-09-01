<?php
/**
 * tests/gov_exec_dept_render_proof.php — برهانُ تصييرِ مواضعِ الدليلِ المبنيّة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا**: أمرُ `GOV_EXEC` §5 يشترط بعدَ البناءِ «**تصييرٌ وقياسٌ فظهور**»،
 *   ودرسُ «الرندر لا المخزن» بنصِّه: *لا يُقاس سطحٌ بما في جدولِه بل بما يظهر
 *   في الجلسة*. فصفٌّ في `nav_placements` ليس برهانَ شاشة.
 *
 * ◆ **ما يُثبته لكلِّ سطحٍ مبنيٍّ في مواصفةِ الإدارة**:
 *   ① دخولٌ بدورِ المساحةِ الحقيقيِّ (لا سوبر) ثمَّ `GET` للمسار ⇒ **200**.
 *   ② الصفحةُ تحمل **عنوانَ الشاشة** — فليست تحويلًا ولا صفحةَ منع.
 *   ③ الصفحةُ تحمل **رؤوسَ أعمدةِ الورقةِ** المسجَّلةَ في `gov_field_class`
 *      (عيّنةٌ منها) — فالحقلُ المقيَّدُ **ظاهرٌ فعلًا** لا مقيَّدٌ في مخزن.
 *   ④ ولا تحمل نصَّ عجزِ العُدّة «لا عمود مصنف لهذه الشاشة».
 *
 * ⛔ **والسالبُ يُقاس**: مستخدمٌ بدورٍ لا يملك الشاشةَ يُردُّ عنها — فالبرهانُ
 *   ليس «تفتح لأيِّ أحد».
 *
 * التشغيل: php tests/gov_exec_dept_render_proof.php --spec=tools/specs/dep04_fleet.php
 *          [--user=<اسم المستخدم>] [--deny-user=<اسم مستخدم بلا صلاحية>]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$BASE = getenv('EMS_BASE_URL') ?: 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function chk($c, $m) { $c ? ok($m) : bad($m); }

$SPEC = null; $USER = null; $DENY = null;
foreach ($argv as $a) {
    if (strpos($a, '--spec=') === 0)       { $SPEC = substr($a, 7); }
    elseif (strpos($a, '--user=') === 0)   { $USER = substr($a, 7); }
    elseif (strpos($a, '--deny-user=') === 0) { $DENY = substr($a, 12); }
}
if ($SPEC === null || !is_file($SPEC)) { exit("⛔ --spec=<file.php> مطلوب\n"); }
$S = require $SPEC;

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("⛔ DB: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

/* مستخدمُ الدورِ المالكِ — من القاعدةِ لا من قائمةٍ في الملف. */
if ($USER === null) {
    $q = $conn->query("SELECT username FROM users WHERE role = '" . (int) $S['role_id'] . "'
                         AND (is_deleted IS NULL OR is_deleted = 0) ORDER BY id LIMIT 1");
    $r = $q ? $q->fetch_row() : null;
    if (!$r) { exit("⛔ لا مستخدمَ حيًّا بالدور {$S['role_id']} — البرهانُ محجوبٌ BLOCKED_ROLE_BINDING\n"); }
    $USER = $r[0];
}

function gp_req($url, $jar, $post = null)
{
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
    $raw = curl_exec($ch);
    $hs  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, (int) $hs), substr($raw, (int) $hs));
}
function gp_login($BASE, $user, $jar)
{
    @unlink($jar);
    list($c, $h, $b) = gp_req($BASE . '/login.php', $jar);
    $tok = '';
    if (preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m)) { $tok = $m[1]; }
    list($c, $h, $b) = gp_req($BASE . '/login.php', $jar,
        array('username' => $user, 'password' => '12345678', 'csrf_token' => $tok));
    return ($c === 302 || $c === 200) && stripos($h, 'Location:') !== false;
}

$jar = sys_get_temp_dir() . '/gp_' . getmypid() . '.txt';
fwrite(STDOUT, "═ برهانُ تصييرِ {$S['dept']} · بدورِ «{$USER}» ═\n");
if (!gp_login($BASE, $USER, $jar)) { exit("⛔ تعذّر الدخولُ بـ{$USER}\n"); }
ok("دخولٌ بدورِ المساحةِ الحيِّ: {$USER}");

foreach ($S['screens'] as $sc) {
    $rel = $S['dir'] . '/' . $sc['file'];
    list($code, $hdr, $body) = gp_req($BASE . '/' . $rel, $jar);
    if ($code !== 200) { bad("{$rel} ⇐ HTTP {$code}"); continue; }
    $titleSeen = (mb_strpos($body, $sc['title']) !== false);
    $noCols    = (mb_strpos($body, 'لا عمود مصنف لهذه الشاشة') !== false);
    /* عيّنةُ رؤوسِ الأعمدةِ من الدفترِ نفسِه — لا قائمةٍ تُكتب هنا فتنجرف. */
    $lab = array();
    $st = $conn->prepare("SELECT label_ar FROM gov_field_class
                            WHERE screen_code = ? AND active = 1 ORDER BY id LIMIT 60");
    $st->bind_param('s', $sc['code']);
    $st->execute();
    $rs = $st->get_result();
    while ($x = $rs->fetch_row()) { $lab[] = $x[0]; }
    $st->close();
    $hit = 0;
    foreach ($lab as $L) { if (mb_strpos($body, htmlspecialchars($L, ENT_QUOTES, 'UTF-8')) !== false) { $hit++; } }
    $shown = count($lab) ? round($hit * 100 / count($lab)) : 0;
    if ($titleSeen && !$noCols && $hit > 0 && $shown >= 80) {
        ok(sprintf('%-38s 200 · رؤوسٌ ظاهرةٌ %d/%d', $rel, $hit, count($lab)));
    } else {
        bad(sprintf('%-38s 200 لكن: %s%s رؤوسٌ %d/%d', $rel,
            $titleSeen ? '' : 'لا عنوان · ', $noCols ? 'لا عمود مصنف · ' : '', $hit, count($lab)));
    }
}

/* ⛔ السالب: دورٌ آخرُ لا يملك الشاشةَ يُردّ */
/* ⛔ **والصلاحيةُ مساران لا مسارٌ واحد** (درسُ «الظلُّ بمسارَين»): منحةُ الدورِ
   في `role_permissions` **وقالبُ المستخدمِ** في `gov_profile_items`. فمرشَّحُ
   السالبِ من لا يملكها **بأيٍّ منهما** — وإلّا قِسنا منحةً قائمةً وسمّيناها عطبًا. */
if ($DENY === null) {
    $rel0 = $conn->real_escape_string($S['dir'] . '/' . $S['screens'][0]['file']);
    $q = $conn->query("SELECT u.username FROM users u
        WHERE u.role NOT IN ('" . (int) $S['role_id'] . "', '-1')
          AND (u.is_deleted IS NULL OR u.is_deleted = 0)
          AND NOT EXISTS (SELECT 1 FROM role_permissions rp JOIN modules m ON m.id = rp.module_id
                           WHERE rp.role_id = u.role AND rp.can_view = 1 AND m.code = '{$rel0}')
          AND NOT EXISTS (SELECT 1 FROM gov_authority_grants g
                            JOIN gov_profile_items gi ON gi.profile_id = g.profile_id
                           WHERE g.user_id = u.id AND g.revoked_at IS NULL
                             AND (g.valid_to IS NULL OR g.valid_to > NOW())
                             AND gi.item_kind = 'screen' AND gi.item_ref = '{$rel0}' AND gi.allow = 1)
        ORDER BY u.id LIMIT 1");
    $r = $q ? $q->fetch_row() : null;
    $DENY = $r ? $r[0] : null;
}
if ($DENY !== null) {
    $jar2 = sys_get_temp_dir() . '/gpd_' . getmypid() . '.txt';
    if (gp_login($BASE, $DENY, $jar2)) {
        $rel = $S['dir'] . '/' . $S['screens'][0]['file'];
        list($code, $hdr, $body) = gp_req($BASE . '/' . $rel, $jar2);
        chk($code === 302 || mb_strpos($body, $S['screens'][0]['title']) === false,
            "السالب: «{$DENY}» بلا صلاحيةٍ لا يرى {$rel} (HTTP {$code})");
    } else { fwrite(STDOUT, "  ○ تعذّر دخولُ مستخدمِ السالب «{$DENY}» — لا حكمَ سالبًا\n"); }
    @unlink($jar2);
} else { fwrite(STDOUT, "  ○ لا مستخدمَ بلا صلاحيةٍ للسالب\n"); }

@unlink($jar);
fwrite(STDOUT, sprintf("\n── الحصيلة: ✔ %d · ✘ %d\n", $PASS, $FAIL));
exit($FAIL > 0 ? 1 : 0);
