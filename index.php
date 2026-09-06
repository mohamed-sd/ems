<?php
/**
 * index.php — مدخلُ الجذر.
 *
 * حُذفت صفحةُ اللاندنق (منصة إنجاز التعريفية)؛ صار فتحُ جذرِ التطبيق
 * يعرض صفحةَ تسجيلِ الدخول مباشرةً. محتوى اللاندنق محفوظٌ في تاريخِ
 * git عند الالتزام 352efb31 إن لُزم استرجاعُه.
 *
 * ملحوظتان تقنيّتان:
 *  - التحويلُ 302 (مؤقّت) لا 301: التحويلُ الدائمُ يُخزَّن في المتصفّحِ
 *    بلا انتهاءٍ فيصعب التراجعُ عنه لاحقًا.
 *  - no-store يمنع خدمةَ نسخةٍ مخزَّنةٍ من الصفحةِ السابقةِ في هذا المسار.
 */

$scriptName = isset($_SERVER['SCRIPT_NAME'])
    ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME'])
    : '/index.php';
$baseUrl = rtrim(dirname($scriptName), '/');
if ($baseUrl === '/' || $baseUrl === '\\') {
    $baseUrl = '';
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Location: ' . $baseUrl . '/login.php', true, 302);
exit;
