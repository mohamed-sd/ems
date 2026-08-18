<?php
/** مسبارُ HTML: يجلب شاشةً بحسابٍ ويعدُّ نمطًا فيها — tools/_uxw_html_probe.php <حساب> <مسار> <نمط> */
$BASE = 'http://localhost/ems';
$user = $argv[1]; $route = $argv[2]; $needle = $argv[3] ?? 'csrf_token';
$jar = sys_get_temp_dir() . '/uxw_html_' . md5($user) . '.txt'; @unlink($jar);
$http = function ($url, $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 40));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); curl_close($ch); return (string) $b;
};
$login = $http("$BASE/login.php");
preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $login, $m);
$http("$BASE/login.php", array('username' => $user, 'password' => '12345678', 'csrf_token' => $m[1] ?? ''));
$page = $http("$BASE/$route");
echo 'طولُ الصفحة: ', strlen($page), "\n";
echo "«$needle» في الصفحة: ", substr_count($page, $needle), "\n";
/* هل هو داخلَ قيمةِ سمةٍ (مبتلَع) أم وسمًا حقيقيًّا؟ */
echo 'حقولُ csrf_token وسومًا حقيقية: ', preg_match_all('~<input[^>]*name=["\']csrf_token["\'][^>]*>~i', $page), "\n";
echo 'سماتُ style غيرُ مُغلَقةٍ تبتلع وسمًا: ', preg_match_all('~style="[^"]*<input~i', $page), "\n";
