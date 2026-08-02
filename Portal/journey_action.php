<?php
/**
 * Portal/journey_action.php — تصعيدٌ وتذكيرٌ من شريط الرحلة (NAV-01 §11-⑥)
 * ───────────────────────────────────────────────────────────────────────────
 * «إجراءان لا أكثر، ويُسجَّلان» — كلُّ ضغطةٍ سطرٌ في سجل تنفيذ الأفعال،
 * والتصعيدُ يبذر سطرَ أثرٍ لوحدة المستهدَف فيظهر في لوحته.
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../app/Services/Actions/ImpactResolver.php';

$company_id = intval($_SESSION['user']['company_id'] ?? 0);
$uid = intval($_SESSION['user']['id'] ?? 0);
$act = ($_POST['jact'] ?? '') === 'escalate' ? 'journey.escalate' : 'journey.remind';
$subject = mb_substr(trim($_POST['subject'] ?? ''), 0, 120);
$back = $_POST['back'] ?? '';
if ($subject === '') { http_response_code(422); die('مرجعُ المعاملة إلزامي'); }

$es = mysqli_real_escape_string($conn, $subject);
$ea = mysqli_real_escape_string($conn, $act);
mysqli_query($conn, "INSERT INTO action_execution_log (company_id, action_code, person_id, subject_ref, result)
                     VALUES ($company_id, '$ea', $uid, '$es', 'allowed')");
\App\Services\Actions\ImpactResolver::apply($conn, $company_id, $act, $subject, $uid);

// عودةٌ آمنةٌ إلى صفحة المعاملة نفسها (مسارٌ داخليٌّ فقط)
$safe = (is_string($back) && $back !== '' && $back[0] === '/' && strpos($back, '//') !== 0) ? $back : '../main/my_workspace.php';
header('Location: ' . $safe . (strpos($safe, '?') === false ? '?' : '&') . 'jmsg=' . ($act === 'journey.escalate' ? 'esc' : 'rem'));
exit();
