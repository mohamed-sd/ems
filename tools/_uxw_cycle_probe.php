<?php
/** مسبارُ سطرِ الدورة: يجلب شاشةً بجلسةٍ مخوَّلةٍ ويقيس ظهورَ ems-cycle-line */
error_reporting(E_ALL);
$BASE = 'http://localhost/ems';
$user = isset($argv[1]) ? $argv[1] : 'مشرف الصلاحيات';
$route = isset($argv[2]) ? $argv[2] : 'Governance/break_glass.php';
$jar = sys_get_temp_dir() . '/uxw_cyc_' . md5($user) . '.txt';
@unlink($jar);
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
$page = $http("$BASE/" . $route);
echo 'ems-cycle-line: ', substr_count($page, 'ems-cycle-line'), "\n";
if (preg_match('~<div class="ems-cycle-line".*?</div>~su', $page, $c)) {
    echo mb_substr(strip_tags(str_replace('<span', ' <span', $c[0])), 0, 200), "\n";
}
