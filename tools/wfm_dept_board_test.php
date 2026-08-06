<?php
/**
 * tools/wfm_dept_board_test.php — إثبات ورقة الإدارات الـ17 (الموجة ١) بدورين HTTP
 * ───────────────────────────────────────────────────────────────────────────
 * ① التنفيذي (تنفيذ · دور 9): يرى لوحة الإدارات الجامعة ويتعمق بإدارة بنقرة.
 * ② الصيانة (صيانة · دور إدارة): يرى ورقة إدارته وحدها — ومحاولة التعمق
 *    بإدارة غيره تُثبَّت على وحدته (لا تسريب عبر ?unit).
 * التشغيل: php tools/wfm_dept_board_test.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();

$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/deptboard_' . getmypid() . '.jar';
$pass = 0; $fail = 0;
function ok($cond, $label) {
    global $pass, $fail;
    if ($cond) { $pass++; fwrite(STDOUT, "  ✅ $label\n"); }
    else { $fail++; fwrite(STDOUT, "  ❌ $label\n"); }
}
function req($url, $post = null) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR, CURLOPT_TIMEOUT => 30,
    ));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $body = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return array('code' => $code, 'body' => $body);
}
function csrfOf($html) {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/u', $html, $m)) { return $m[1]; }
    if (preg_match('/window\.csrfToken\s*=\s*[\'"]([^\'"]+)[\'"]/u', $html, $m)) { return $m[1]; }
    return '';
}
function loginAs($user, $passWord) {
    global $BASE, $JAR;
    @unlink($JAR);
    $lg = req($BASE . '/login.php');
    req($BASE . '/login.php', array('username' => $user, 'password' => $passWord, 'csrf_token' => csrfOf($lg['body'])));
    $home = req($BASE . '/dashboard.php');
    return mb_strpos($home['body'], 'logout') !== false || mb_strpos($home['body'], 'login.php') === false;
}

fwrite(STDOUT, "═══ ① التنفيذي: لوحة الإدارات الجامعة ═══\n");
ok(loginAs('تنفيذ', '12345678'), 'دخول حساب التنفيذ (دور 9)');
$pg = req($BASE . '/Portal/dept_board.php');
ok($pg['code'] === 200, 'الشاشة تفتح 200');
ok(mb_strpos($pg['body'], 'لوحة الإدارات') !== false, 'اللوحة الجامعة تُعرض للدور 9');
ok(mb_strpos($pg['body'], 'المالية والخزينة') !== false, 'صف المالية حاضر');
ok(mb_strpos($pg['body'], 'إدارة الصيانة') !== false, 'صف الصيانة حاضر');

$pg = req($BASE . '/Portal/dept_board.php?unit=9');
ok(mb_strpos($pg['body'], 'ورقة الإدارة') !== false
   && mb_strpos($pg['body'], 'إدارة الصيانة') !== false, 'التعمق بإدارة الصيانة يفتح ورقتها');
ok(mb_strpos($pg['body'], 'مهام أعضاء الإدارة') !== false, 'قسم مهام الأعضاء حاضر');
ok(mb_strpos($pg['body'], 'طلبات بيد الإدارة') !== false, 'قسم الطلبات حاضر');
ok(mb_strpos($pg['body'], 'إنجازات الإدارة') !== false, 'قسم الإنجازات حاضر');
ok(mb_strpos($pg['body'], 'المتأخرات') !== false, 'قسم المتأخرات حاضر');

fwrite(STDOUT, "═══ ② دور إدارةٍ: ورقته وحدها ولا تسريب عبر ?unit ═══\n");
ok(loginAs('صيانة', '12345678'), 'دخول حساب الصيانة');
$pg = req($BASE . '/Portal/dept_board.php');
ok($pg['code'] === 200, 'الشاشة تفتح 200');
ok(mb_strpos($pg['body'], 'ورقة الإدارة') !== false
   && mb_strpos($pg['body'], 'إدارة الصيانة') !== false, 'يرى ورقة إدارته (الصيانة)');
ok(mb_strpos($pg['body'], 'لوحة الإدارات — الاكتمال') === false, 'اللوحة الجامعة محجوبة عنه');
$pg = req($BASE . '/Portal/dept_board.php?unit=3');
ok(mb_strpos($pg['body'], 'ورقة الإدارة — المالية والخزينة') === false
   && mb_strpos($pg['body'], 'إدارة الصيانة') !== false, '?unit=3 لا يسرب المالية — ثُبِّت على وحدته');

@unlink($JAR);
fwrite(STDOUT, "\nالنتيجة: {$pass} نجاح · {$fail} فشل\n");
exit($fail > 0 ? 1 : 0);
