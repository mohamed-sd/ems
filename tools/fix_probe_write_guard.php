<?php
/**
 * tools/fix_probe_write_guard.php — نداءٌ حيٌّ لحارسِ صلاحيةِ الكتابة (P1-A)
 * ═══════════════════════════════════════════════════════════════════════════
 * يُصيِّر سطحًا بطلبِ **POST** بدورٍ معطًى، ويطبع حكمَ الحارسِ من الجسمِ نفسِه.
 * ◆ الشاهدُ ارتدادٌ (جسمٌ فارغ) لمن لا يملك الكتابةَ، وتصييرٌ لمن يملكها —
 *   والاتجاهان معًا، فالتصييرُ وحدَه لا يُثبت حارسًا.
 *
 * الاستعمال: php tools/fix_probe_write_guard.php <Dir/file.php> <role>
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
mysqli_report(MYSQLI_REPORT_OFF);

$rel  = isset($argv[1]) ? $argv[1] : '';
$role = isset($argv[2]) ? (string) $argv[2] : '1';
$ROOT = dirname(__DIR__);
$path = $ROOT . '/' . $rel;
if (!is_file($path)) { exit("PW|" . json_encode(array('err' => 'no file')) . "\n"); }

$_SERVER['SCRIPT_NAME'] = '/ems/' . $rel;
$_SERVER['REQUEST_URI']  = $_SERVER['SCRIPT_NAME'];
$_SERVER['PHP_SELF']     = $_SERVER['SCRIPT_NAME'];
$_SERVER['SCRIPT_FILENAME'] = $path;
$_SERVER['HTTP_HOST']    = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'POST';

require_once $ROOT . '/includes/session_bootstrap.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

/* ── جلسةُ **مستخدمٍ حقيقيٍّ من الدورِ المقيس** لا هُجنةٌ مستحيلة ──────────
   ◆ العيبُ الذي كان: مُعرِّفٌ مثبَّتٌ (891 · دورُه 17) يُبدَّل دورُه في الجلسةِ
     إلى الدورِ المقيس. وGOV-AUTH-01 يحكم **بقالبِ المستخدم** لا بدورِ الجلسة
     (وهو تصميمُه الصحيح: «المغطَّى بقالبٍ نافذٍ يُحكَم بقالبِه حصرًا»)، فكان
     الفاحصُ يقرأ منعَ قالبِ المستخدمِ 891 وينسبه للدورِ المقيسِ خطأً —
     فأعلن «الكاتبُ مُنع خطأً» في سطحَين لا عيبَ فيهما.
   ◆ والطبقاتُ ثلاثٌ لا اثنتان (قرارُ المالك 2026-08-18 ⑥): ① قالبُ الدور ·
     ② منحُ الدورِ الفعلية · ③ تصييرُ المستخدمِ الحقيقي. وهذا الفاحصُ يقيس ③،
     فيلزمه مستخدمٌ **دورُه هو الدورُ المقيس** حقًّا. */
/* اتصالٌ منفصلٌ للاستدلالِ وحدَه ثم يُغلَق — فتحميلُ config.php هنا يصطدم
   بتحميلِ السطحِ له بعدَ قليلٍ («لا يُعاد إعلانُ دالة»). */
$__uid = 0;
require_once $ROOT . '/includes/env.php';
$__h = ems_env('DB_HOST'); $__p = 3306;
if (strpos($__h, ':') !== false) { list($__h, $__p) = explode(':', $__h); $__p = (int) $__p; }
$__lk = @new mysqli($__h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $__p);
if ($__lk && !$__lk->connect_errno) {
    $__lk->set_charset('utf8mb4');
    $__rid = (int) $role;
    $__q = $__lk->query("SELECT id FROM users WHERE company_id = 4 AND CAST(role AS UNSIGNED) = {$__rid} ORDER BY id LIMIT 1");
    if ($__q && ($__r = $__q->fetch_assoc())) { $__uid = (int) $__r['id']; }
    $__lk->close();
}
if ($__uid === 0) {
    exit("PW|" . json_encode(array('err' => 'no real user for role ' . $role, 'role' => $role)) . "\n");
}
$_SESSION = array('user' => array(
    'id' => $__uid, 'role' => $role, 'company_id' => 4, 'name' => 'فاحصُ حارسِ الكتابة',
));
require_once $ROOT . '/includes/security.php';
// حمولةٌ لا تُطلق معالجًا بعينِه — المقصودُ الحارسُ لا الأثر.
$_POST = array('csrf_token' => function_exists('generate_csrf_token') ? generate_csrf_token() : '');

register_shutdown_function(static function () {
    $body = '';
    while (ob_get_level() > 0) { $body = ob_get_clean() . $body; }
    $last = error_get_last();
    $fatal = ($last && in_array($last['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR), true))
           ? preg_replace('~\s+~', ' ', mb_substr($last['message'], 0, 140)) : '';

    /* ◆ الحكمُ يُقرأ من **رمزِ الرسالةِ الحوكمية** لا من عددِ البايتات:
         عدُّ البايتاتِ يخلط منعَ الكتابةِ بمنعِ CSRF بمنعِ العرض — وثلاثتُها
         ارتدادٌ بجسمٍ صغير. والرمزُ يميّز السببَ تمييزًا لا لبسَ فيه. */
    $codes = array();
    if (isset($_SESSION['ems_flash_gov']) && is_array($_SESSION['ems_flash_gov'])) {
        foreach ($_SESSION['ems_flash_gov'] as $f) { $codes[] = (string) ($f['code'] ?? ''); }
    }
    /* ◆ والمنعُ قد يقع **في الجسمِ لا في الجلسة**: حارسُ الكتابةِ المركزيُّ
         (`ems_require_action`) يردُّ 403 فورًا برمزِه في الجسمِ ولا يرتدُّ بفلاشٍ
         في الجلسة — وهو الصوابُ لطلبٍ كاتبٍ ممنوع. وقراءةُ الجلسةِ وحدَها
         **تعمي المسبارَ عن منعٍ واقع**: قِيس خمسةُ أسطحٍ مُنع فيها القارئُ فعلًا
         (77 بايتًا مقابل 146,955 للكاتب) وأُعلنت «لم يُمنع». فيُقرأ الرمزُ من
         الموضعَين. */
    $bodyHasCode = (strpos($body, 'GOV-PERM-403-WRITE') !== false);
    if ($bodyHasCode) { $codes[] = 'GOV-PERM-403-WRITE'; }
    fwrite(STDOUT, "\nPW|" . json_encode(array(
        'bytes'       => strlen(trim($body)),
        'fatal'       => $fatal,
        'codes'       => array_values(array_unique(array_filter($codes))),
        'code_source' => $bodyHasCode ? 'body' : (in_array('GOV-PERM-403-WRITE', $codes, true) ? 'session' : '-'),
        'write_denied' => in_array('GOV-PERM-403-WRITE', $codes, true),
    ), JSON_UNESCAPED_UNICODE) . "\n");
});

ob_start();
chdir(dirname($path));
require $path;
