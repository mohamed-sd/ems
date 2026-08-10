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
$_SESSION = array('user' => array(
    'id' => 891, 'role' => $role, 'company_id' => 4, 'name' => 'فاحصُ حارسِ الكتابة',
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
    fwrite(STDOUT, "\nPW|" . json_encode(array(
        'bytes'       => strlen(trim($body)),
        'fatal'       => $fatal,
        'codes'       => $codes,
        'write_denied' => in_array('GOV-PERM-403-WRITE', $codes, true),
    ), JSON_UNESCAPED_UNICODE) . "\n");
});

ob_start();
chdir(dirname($path));
require $path;
