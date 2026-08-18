<?php
/** إثباتُ «بلاغاتي» حيًّا: يدخل بحسابٍ له بلاغاتٌ ويقيس ظهورَها */
error_reporting(E_ALL);
$BASE = 'http://localhost/ems';
$user = $argv[1] ?? 'محمد';
$jar = sys_get_temp_dir() . '/uxw_mt.txt'; @unlink($jar);
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
$page = $http("$BASE/Tickets/my_tickets.php");
$rows = preg_match_all('~<tr~u', $page) - 1;
echo "الحساب: $user · صفوفُ الجدول: ", max(0, $rows), "\n";
echo mb_strpos($page, 'ems-state-empty') !== false && $rows <= 0 ? "حالةُ الفراغِ ظاهرة\n" : "بلاغاتٌ ظاهرة ✔\n";
