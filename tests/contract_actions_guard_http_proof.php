<?php
/**
 * tests/contract_actions_guard_http_proof.php — برهانٌ حيٌّ: يدُ المبيعاتِ على
 * ملفِّ عقدِ المشروع (`Contracts/contracts_details.php`)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * ◆ **الواقعةُ المقيسة**
 *   دورُ المبيعاتِ (12) يملك `can_view/add/edit/delete = 1` على الوحدةِ #21
 *   `Contracts/contracts_details.php` — ومع ذلك كان كلُّ إجراءٍ في الشاشةِ يُردُّ
 *   بـ403 «ليس لديك صلاحية تنفيذ هذا الإجراء.» (`includes/action_guard.php`).
 *
 * ◆ **الجذر**: سجلُّ حارسِ الأفعالِ كان يربط المعالجَ ببادئةِ **مجلدٍ**
 *   (`'Contracts/'`) لا بكودِ **شاشة**. والبادئةُ لا تُطابق أيَّ `code` تطابقًا
 *   دقيقًا، فيسقط `check_page_permissions` إلى آخرِ محاولاته
 *   (`code LIKE '%Contracts/%' ORDER BY CHAR_LENGTH(code) ASC` —
 *   `includes/permissions_helper.php:575`) فيعيد **أقصرَ** كودٍ تحت المجلد:
 *   `Contracts/claims.php` (المستخلصات · #142). فقيست صلاحيةُ ملفِّ العقدِ على
 *   المستخلصات — وهناك `can_edit = 0`.
 *
 * ◆ **ولمَ لم يُكتشف بالعين**: الأفعالُ اثنا عشر، ونجا منها `renewal` وحدَه —
 *   لأن «renewal» تحوي «new» فصنّفها `ems_action_verb_map` فعلَ `add`،
 *   والمستخلصاتُ تحمل `can_add = 1`. **نجاةُ فعلٍ واحدٍ ليست صلاحيةً جزئية —
 *   بل دليلٌ على أن المقيسَ شاشةٌ أخرى.**
 *
 * ◆ **ما يقيسه هذا الفاحص**
 *   ① ساكنًا: أكوادُ سجلِّ الحارسِ لمعالجاتِ العقودِ **شاشاتٌ كاملةٌ** لا بادئاتُ
 *      مجلدٍ، وكلُّ كودٍ يحلُّ إلى وحدةٍ كودُها **مطابقٌ حرفيًّا** لما في السجل.
 *   ② حيًّا موجبًا: جلسةُ مبيعاتٍ حقيقيةٌ تعبر السلسلةَ الكاملةَ لكلِّ فعلٍ من
 *      الاثني عشر — حارسُ الأفعالِ المركزيُّ ثم `ems_guard_handler` ثم
 *      `enforce_module_permission_json` — **بصفرِ كتابة**: `contract_id`
 *      غيرُ موجودٍ فيُردُّ الطلبُ عند فحصِ وجودِ العقدِ **بعد** الحرّاسِ كلِّهم.
 *   ③ حيًّا سالبًا: دورٌ بلا صلاحيةٍ على الشاشةِ ما يزال يُردُّ 403 —
 *      **فالإصلاحُ لم يفتح البابَ للجميع.** بوابةٌ حكمُها يستحيل رفضُه خضراءُ
 *      أبدًا؛ فتُجرَّب رافضةً قبل تصديقِ سماحها.
 *   ④ إعلانًا: بقيةُ بادئاتِ المجلدِ في السجلِّ تُعرَض بما تحلُّ إليه فعلًا —
 *      **تُذكر ولا يُسكت عنها**، فلا يُقرأ «صفرُ رسوبٍ» على أنه «السجلُّ كلُّه سليم».
 *
 * ◆ ما لا يقيسه — مُعلَنٌ لا مسكوتٌ عنه: لا ينفّذ إجراءً على عقدٍ حقيقيٍّ (لا
 *   يوقف عقدًا ولا يُنهيه)، فلا يقيس صحةَ أثرِ الإجراءِ في القاعدة — يقيس
 *   **قرارَ الحرّاس** وحدَه. ولا يقيس شاشاتِ المجموعاتِ الأخرى (Suppliers ·
 *   Employees · Timesheet …) إلا إعلانًا في ④.
 *
 * ◆ يتطلّب Apache حيًّا على http://localhost/ems وقاعدةَ co4
 *   php tests/contract_actions_guard_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

/* السلسلةُ تُحمَّل **قبلَ أيِّ طباعة**: `config.php` يبدأ جلسةً، وبدءُ الجلسةِ
   بعد إرسالِ بايتٍ واحدٍ يُغرق المخرَجَ بتحذيراتِ «headers already sent» فتُخفي
   حكمَ الفاحصِ نفسَه — والمخرَجُ الذي لا يُقرأ لا يُصدَّق. */
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/permissions_helper.php';
require_once dirname(__DIR__) . '/includes/action_guard.php';
mysqli_report(MYSQLI_REPORT_OFF);
while (ob_get_level() > 0) { ob_end_clean(); }

$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  \xE2\x9C\x94 {$m}\n"); }
function bad($m, $d = '') { global $FAIL; $FAIL++; fwrite(STDOUT, "  \xE2\x9C\x96 {$m}" . ($d !== '' ? " — {$d}" : '') . "\n"); }
function ck($m, $c, $d = '') { $c ? ok($m) : bad($m, $d); }

function req($url, $jar, $post = null, $xhr = false)
{
    $ch = curl_init($url);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    );
    if ($xhr) { $opts[CURLOPT_HTTPHEADER] = array('X-Requested-With: XMLHttpRequest'); }
    curl_setopt_array($ch, $opts);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) { return array(0, '', ''); }
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}

/** رمزُ CSRF يُقرأ أولًا — بدونه يُردُّ الطلبُ فيُقرأ رفضُ الرمزِ حجبَ صلاحية. */
function login($base, $user, $jar)
{
    if (file_exists($jar)) { @unlink($jar); }
    list(, , $b) = req($base . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return req($base . '/login.php', $jar, array(
        'username'   => $user,
        'password'   => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : '',
    ));
}

/** رمزُ CSRF لطلباتِ XHR يُلتقط من الصفحةِ الأمِّ كما يفعل الحقنُ الآليُّ. */
function csrf_of($base, $path, $jar)
{
    list($c, , $b) = req($base . '/' . $path, $jar);
    if ($c !== 200) { return array('', $c); }
    if (preg_match('~name="csrf-token"\s+content="([^"]+)"~', $b, $m)) { return array($m[1], $c); }
    if (preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m)) { return array($m[1], $c); }
    return array('', $c);
}

const DENY_MSG   = 'ليس لديك صلاحية تنفيذ هذا الإجراء.';
const ABSENT_MSG = 'العقد غير موجود';
/** مُعرِّفٌ لا وجودَ له ⇒ المعالجُ يخرج بعد الحرّاسِ وقبلَ أيِّ كتابة. */
const GHOST_ID   = 99999999;

/* الأفعالُ الاثنا عشرَ كما تُنادى من `Contracts/contracts_details.php` حرفيًّا
   (`performAction(...)` وأربعةُ `action:` في نداءاتِ التحرير). */
$ACTIONS = array(
    'renewal', 'settlement', 'pause', 'resume', 'terminate', 'complete', 'merge',
    'change_obligation', 'update_project_info', 'update_services', 'update_parties',
    'update_payment',
);

echo "\xE2\x95\x90\xE2\x95\x90\xE2\x95\x90 برهانٌ حيٌّ · حارسُ أفعالِ ملفِّ عقدِ المشروع \xE2\x95\x90\xE2\x95\x90\xE2\x95\x90\n\n";

/* ══ ① السجلُّ الساكن: شاشةٌ كاملةٌ لا بادئةُ مجلد ═══════════════════════ */
echo "── ① سجلُّ حارسِ الأفعال — أكوادُ معالجاتِ العقود\n";
$registry = ems_action_guard_registry();
$contractEntries = array();
foreach ($registry as $path => $def) {
    if (strpos($path, 'contracts/') === 0) { $contractEntries[$path] = $def; }
}
ck('معالجاتُ العقودِ مسجَّلةٌ (٤ نقاط)', count($contractEntries) === 4,
   'وُجد ' . count($contractEntries));

foreach ($contractEntries as $path => $def) {
    foreach ($def['modules'] as $code) {
        $isScreen = (substr($code, -4) === '.php');
        ck("«{$path}» → «{$code}» كودُ شاشةٍ لا بادئةُ مجلد", $isScreen,
           'بادئةُ مجلدٍ تُحلُّ إلى أقصرِ كودٍ تحتها — لا إلى الشاشةِ الأم');
        if (!$isScreen) { continue; }
        $r = $conn->query("SELECT id, code FROM modules WHERE code = '"
                          . $conn->real_escape_string($code) . "' LIMIT 1");
        $row = $r ? $r->fetch_assoc() : null;
        ck("«{$code}» يحلُّ إلى وحدةٍ كودُها مطابقٌ حرفيًّا", $row !== null && $row['code'] === $code,
           $row === null ? 'غيرُ مسجَّلةٍ في `modules`' : 'حُلَّ إلى «' . $row['code'] . '»');
    }
}

/* ══ ② حيًّا موجبًا: جلسةُ مبيعاتٍ تعبر السلسلةَ الكاملة ══════════════════ */
echo "\n── ② الحيُّ الموجب — حسابُ «مبيعات» (الدور 12) على الأفعالِ الاثني عشر\n";
list($ping) = req($BASE . '/login.php', sys_get_temp_dir() . '/cag_ping.jar');
if ($ping === 0) { exit("  \xE2\x9C\x96 Apache لا يستجيب على {$BASE} — البرهانُ الحيُّ متعذِّر\n"); }

$jarS = sys_get_temp_dir() . '/cag_sales.jar';
list($lc) = login($BASE, 'مبيعات', $jarS);
if ($lc !== 200 && $lc !== 302) { exit("  \xE2\x9C\x96 تعذّر الدخولُ بـ«مبيعات» — HTTP {$lc}\n"); }

list($tokS, $pc) = csrf_of($BASE, 'Contracts/contracts_details.php?id=1', $jarS);
ck('شاشةُ ملفِّ عقدِ المشروعِ تُفتح لحسابِ المبيعات', $pc === 200, "HTTP {$pc}");

foreach ($ACTIONS as $a) {
    list($code, , $body) = req($BASE . '/Contracts/contract_actions_handler.php', $jarS, array(
        'action' => $a, 'contract_id' => GHOST_ID, 'csrf_token' => $tokS,
    ), true);
    $j = json_decode($body, true);
    $msg = is_array($j) && isset($j['message']) ? $j['message'] : trim(strip_tags($body));
    $blocked = ($code === 403) || (mb_strpos($msg, DENY_MSG) !== false);
    $passedGuards = ($code === 200) && (mb_strpos($msg, ABSENT_MSG) !== false);
    ck(sprintf('%-20s يعبر الحرّاسَ الثلاثة', $a), !$blocked && $passedGuards,
       "HTTP {$code} · " . mb_substr($msg, 0, 90));
}

/* ══ ③ حيًّا سالبًا: البابُ لم يُفتح للجميع ═══════════════════════════════ */
echo "\n── ③ الحيُّ السالب — حسابُ «صيانة» (الدور 13 · بلا صلاحيةٍ على #21)\n";
$jarM = sys_get_temp_dir() . '/cag_mnt.jar';
list($lm) = login($BASE, 'صيانة', $jarM);
if ($lm !== 200 && $lm !== 302) {
    bad('تعذّر الدخولُ بـ«صيانة» — الحزامُ السالبُ لم يُشغَّل', "HTTP {$lm}");
} else {
    list($tokM) = csrf_of($BASE, 'main/profile.php', $jarM);
    foreach (array('pause', 'update_payment') as $a) {
        list($code, , $body) = req($BASE . '/Contracts/contract_actions_handler.php', $jarM, array(
            'action' => $a, 'contract_id' => GHOST_ID, 'csrf_token' => $tokM,
        ), true);
        $j = json_decode($body, true);
        $msg = is_array($j) && isset($j['message']) ? $j['message'] : trim(strip_tags($body));
        /* **الرفضُ بسببِه لا بحالتِه**: 403 قد يأتي من رفضِ CSRF أو من حجبِ
           النقطةِ المباشر — فيُقرأ منعُ صلاحيةٍ وهو غيرُه. فيُشترط النصُّ نفسُه. */
        ck(sprintf('%-20s ما يزال يُردُّ بمنعِ الصلاحيةِ نفسِه', $a),
           $code === 403 && mb_strpos($msg, DENY_MSG) !== false,
           "HTTP {$code} · " . mb_substr($msg, 0, 90));
    }
}

/* ══ ④ إعلانٌ: بادئاتُ المجلدِ الباقيةُ وما تحلُّ إليه فعلًا ═════════════ */
echo "\n── ④ إعلانٌ (خارجَ حكمِ الفاحص) — بادئاتُ مجلدٍ ما تزال في السجل\n";
$_SESSION['user'] = array('id' => 13, 'role' => '12', 'company_id' => 4);
$prefixes = array();
foreach ($registry as $path => $def) {
    if (empty($def['modules'])) { continue; }
    foreach ($def['modules'] as $code) {
        if (substr($code, -4) === '.php') { continue; }
        $p = check_page_permissions($conn, $code);
        $rid = isset($p['id']) ? intval($p['id']) : 0;
        $rc = '?';
        if ($rid) {
            $r = $conn->query("SELECT code FROM modules WHERE id = {$rid} LIMIT 1");
            $rc = $r ? $r->fetch_row()[0] : '?';
        }
        $prefixes[$code] = $rc;
    }
}
if (!$prefixes) {
    echo "     (لا بادئةَ مجلدٍ باقية)\n";
} else {
    foreach ($prefixes as $code => $rc) {
        printf("     %-16s ⇒ %s\n", $code, $rc);
    }
    echo "     ○ كلُّ بادئةٍ أعلاه تحلُّ إلى **أقصرِ** كودٍ تحتها — فتسجيلُ وحدةٍ\n"
       . "       جديدةٍ بكودٍ أقصرَ يُزيح المقياسَ صامتًا (وقع في `Approvals/` ثم `Contracts/`).\n";
}

/* ══ الحصيلة ══════════════════════════════════════════════════════════════ */
echo "\n" . str_repeat('=', 74) . "\n";
printf("الحصيلة: %d ناجح · %d راسب\n", $PASS, $FAIL);
echo $FAIL === 0
    ? "\xE2\x9C\x85 يدُ المبيعاتِ تعمل على ملفِّ عقدِ المشروع، والبابُ ما يزال مغلقًا على من لا يملكه.\n"
    : "\xE2\x9A\xA0 ثمّةَ رسوبٌ — راجع أعلاه.\n";
exit($FAIL === 0 ? 0 : 1);
