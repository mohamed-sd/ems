<?php
/** مسبارُ شريطِ الرحلة: يجلب شاشةً ويقيس ظهورَ ems-entity-tabs ونشاطَ تبويبِها */
$BASE = 'http://localhost/ems';
$user = $argv[1] ?? 'مصعب';
$route = $argv[2] ?? 'Suppliers/supplier_advances.php';
$jar = sys_get_temp_dir() . '/uxw_tabs_' . md5($user) . '.txt';
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
$page = $http("$BASE/$route");
echo 'ems-entity-tabs: ', substr_count($page, 'ems-entity-tabs"'), "\n";
if (preg_match('~aria-current="page"[^>]*>([^<]+)~u', $page, $a)) { echo 'النشط: ', trim($a[1]), "\n"; }
echo 'معطَّلة معلَنة: ', substr_count($page, 'is-unbuilt'), "\n";
