<?php
/** اختبارٌ حيٌّ لتسجيلِ قرارٍ في مركزِ الحوكمةِ التقنيِّ ثم إرجاعِه — ذهابًا وإيابًا */
error_reporting(E_ALL);
$BASE = 'http://localhost/ems';
$jar = sys_get_temp_dir() . '/uxw_dec_jar.txt';
@unlink($jar);
$http = function ($url, $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 40));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); curl_close($ch); return (string) $b;
};
$login = $http("$BASE/login.php");
$csrf = preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $login, $m) ? $m[1] : '';
$http("$BASE/login.php", array('username' => 'مشرف الصلاحيات', 'password' => '12345678', 'csrf_token' => $csrf));
$page = $http("$BASE/Governance/tech_gov_center.php");
if (!preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $page, $m)) { exit("✗ لا CSRF في الشاشة (أوصلت الشاشة؟ " . strlen($page) . " بايت)\n"); }
$resp = $http("$BASE/Governance/tech_gov_center.php",
    array('action' => 'record_decision', 'orphan_id' => 1, 'decision' => 'approved', 'csrf_token' => $m[1]));
echo (mb_strpos($resp, 'سُجِّل القرارُ') !== false ? "✔ سُجِّل القرارُ (HTTP)\n" : "✗ لم يظهر تأكيدُ التسجيل\n");

// القياسُ من القاعدة ثم الإرجاع
require_once __DIR__ . '/../includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
$r = $conn->query("SELECT owner_decision, decided_at FROM gov_orphan_links WHERE id=1")->fetch_assoc();
echo "· القاعدة: {$r['owner_decision']} @ {$r['decided_at']}\n";
$ok = ($r['owner_decision'] === 'approved' && $r['decided_at'] !== null);
$conn->query("UPDATE gov_orphan_links SET owner_decision='pending', decided_at=NULL WHERE id=1");
$r2 = $conn->query("SELECT owner_decision FROM gov_orphan_links WHERE id=1")->fetch_assoc();
echo "· أُرجع إلى: {$r2['owner_decision']}\n";
exit($ok ? 0 : 1);
