<?php
/**
 * tools/fix_probe_employee_data.php — نداءٌ حيٌّ لنقطةِ بياناتِ الموظف (INJ-0004)
 * ═══════════════════════════════════════════════════════════════════════════
 * يُشغِّل ‎Employees/get_employee_data.php‎ بجلسةِ دورٍ محقونةٍ ثم يطبع **جسمَ
 * الاستجابةِ الخامَّ كما هو** — والحكمُ يقع في المُستدعي.
 *
 * ◆ گوتشا مدفوعُ ثمنِها: المحاولةُ الأولى قرأت الجسمَ من ‎ob_get_clean()‎ في
 *   ‎register_shutdown_function‎ — والنقطةُ تنادي ‎ob_end_clean()‎ ثم ‎die()‎،
 *   فلا يبقى مخزنٌ يُقرأ. النتيجةُ: خرجٌ فارغٌ يُقرأ **«لا راتبَ في الاستجابة»**
 *   ⇒ **نجاحٌ كاذبٌ للاختبار**. فالجسمُ يُقرأ من stdout الحقيقيِّ في عمليةٍ
 *   منفصلة، ويُشترط أن تكون الاستجابةُ ناجحةً وذاتَ حقولٍ وإلا رسب الاختبار.
 *
 * الاستعمال: php tools/fix_probe_employee_data.php <الدور> <معرّف الموظف>
 * الخرج: JSON الاستجابةِ الخامُّ على stdout بلا زيادة.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
mysqli_report(MYSQLI_REPORT_OFF);

$role = isset($argv[1]) ? (string) $argv[1] : '11';
$eid  = isset($argv[2]) ? (int) $argv[2] : 0;
$ROOT = dirname(__DIR__);

$_SERVER['SCRIPT_NAME'] = '/ems/Employees/get_employee_data.php';
$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . '?id=' . $eid;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET = array('id' => (string) $eid);

require_once $ROOT . '/includes/session_bootstrap.php';
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
$_SESSION = array('user' => array(
    'id' => 891, 'role' => $role, 'company_id' => 4, 'name' => 'فاحصُ الحقولِ الحساسة',
));

chdir($ROOT . '/Employees');
require $ROOT . '/Employees/get_employee_data.php';
