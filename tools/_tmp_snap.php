<?php
/* لقطةُ HTML للمعاينةِ البصريةِ المحلية (تُحذف بعد المعاينة) */
$BASE = 'http://localhost/ems';
$jar = sys_get_temp_dir() . '/ems_snap_' . getmypid() . '.cookie';
function req($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER=>true, CURLOPT_HEADER=>false,
        CURLOPT_COOKIEJAR=>$jar, CURLOPT_COOKIEFILE=>$jar, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_TIMEOUT=>60));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); curl_close($ch); return $b;
}
$b = req($BASE . '/login.php', $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
req($BASE . '/login.php', $jar, array('username'=>'محمد','password'=>'12345678','csrf_token'=>$m[1] ?? ''));

foreach (array('list'=>'/FinRequests/request_form.php',
               'draft'=>'/FinRequests/request_form.php?id=184') as $k=>$u) {
    $html = req($BASE . $u, $jar);
    $out = dirname(__DIR__) . '/FinRequests/_preview_' . $k . '.html';
    file_put_contents($out, $html);
    echo "{$k}: " . strlen($html) . " بايت\n";
}
@unlink($jar);
