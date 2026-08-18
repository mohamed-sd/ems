<?php
/** إثباتُ شاشةِ حدودِ المبالغِ حيًّا — CLI أولًا (config قبلَ أيِّ إخراج) ثم HTTP */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$c = $GLOBALS['conn'];
mysqli_set_charset($c, 'utf8mb4');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);

$BASE = 'http://localhost/ems';
$jar = sys_get_temp_dir() . '/uxw_caps.txt'; @unlink($jar);
$http = function ($url, $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 40));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); curl_close($ch); return (string) $b;
};
$csrf = function ($html) { return preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $html, $m) ? $m[1] : ''; };

$login = $http("$BASE/login.php");
$http("$BASE/login.php", array('username' => 'تنفيذ', 'password' => '12345678', 'csrf_token' => $csrf($login)));
$scr = $http("$BASE/Governance/authority_caps.php");
echo '① الشاشةُ تفتح للمالك: ', (mb_strpos($scr, 'حدود المبالغ') !== false ? '✔' : '✗'), "\n";
echo '② التسعةُ ظاهرة: ', (substr_count($scr, 'LD-1') + substr_count($scr, 'LD-0') >= 9 ? '✔' : '✗'), "\n";
echo '③ خانةُ التحريرِ للمالك: ', (mb_strpos($scr, 'caps-edit-form') !== false ? '✔' : '✗'), "\n";

$post = $http("$BASE/Governance/authority_caps.php", array(
    'ladder_code' => 'LD-05', 'cap_amount' => '2500', 'cap_currency' => 'USD',
    'effective_from' => date('Y-m-d') . 'T' . date('H:i'),
    'reason' => 'مسبار إثبات المرونة — يُعاد فورًا', 'csrf_token' => $csrf($scr)));
echo '④ التعديلُ قُبل: ', (mb_strpos($post, 'سُجِّل سقفُ LD-05') !== false ? '✔' : '✗'), "\n";
$live = $c->query("SELECT cap_amount FROM gov_ladders WHERE ladder_code='LD-05'")->fetch_row()[0];
echo '⑤ الساري تحدَّث: ', ((float) $live === 2500.0 ? '✔ 2500' : '✗ ' . $live), "\n";
$histN = (int) $c->query("SELECT COUNT(*) FROM gov_cap_history WHERE ladder_code='LD-05'")->fetch_row()[0];
echo '⑥ السجلُّ حفظ القديمَ والجديد: ', ($histN >= 2 ? "✔ ({$histN})" : '✗'), "\n";

$scr2 = $http("$BASE/Governance/authority_caps.php");
$http("$BASE/Governance/authority_caps.php", array(
    'ladder_code' => 'LD-05', 'cap_amount' => '2000', 'cap_currency' => 'USD',
    'effective_from' => date('Y-m-d') . 'T' . date('H:i'),
    'reason' => 'إرجاعُ قيمةِ المسبارِ إلى المعتمدةِ الابتدائية', 'csrf_token' => $csrf($scr2)));
$back = $c->query("SELECT cap_amount FROM gov_ladders WHERE ladder_code='LD-05'")->fetch_row()[0];
echo '⑦ أُرجع بسطرِ سجلٍّ (لا حذف): ', ((float) $back === 2000.0 ? '✔' : '✗ ' . $back), "\n";

@unlink($jar);
$login2 = $http("$BASE/login.php");
$http("$BASE/login.php", array('username' => 'مشرف الصلاحيات', 'password' => '12345678', 'csrf_token' => $csrf($login2)));
$scr3 = $http("$BASE/Governance/authority_caps.php");
echo '⑧ الحوكمةُ ترى بلا تحرير: ',
    (mb_strpos($scr3, 'حدود المبالغ') !== false
     && substr_count($scr3, '<form method="post" class="caps-edit-form"') === 0 ? '✔' : '✗'), "\n";
$post2 = $http("$BASE/Governance/authority_caps.php", array(
    'ladder_code' => 'LD-05', 'cap_amount' => '9999', 'cap_currency' => 'USD',
    'effective_from' => date('Y-m-d') . 'T' . date('H:i'),
    'reason' => 'محاولة غير المالك', 'csrf_token' => $csrf($scr3)));
/* الرفضُ الشرعيُّ طبقتان: CSRF أولًا (صفحةُ غيرِ المالكِ بلا نموذجٍ فلا توكن —
   فأيُّ POST مفبركٍ يُصدُّ قبلَ منطقِ الدور) ثم شرطُ الدورِ ثم القادحُ البنيوي */
echo '⑨ ومحاولتُها تُرفض: ',
    (mb_strpos($post2, 'باعتمادِ المالكِ وحدَه') !== false
     || mb_strpos($post2, 'فشل التحقق الأمني') !== false ? '✔' : '✗'), "\n";
$still = $c->query("SELECT cap_amount FROM gov_ladders WHERE ladder_code='LD-05'")->fetch_row()[0];
echo '⑩ والساري لم يُمسّ: ', ((float) $still === 2000.0 ? '✔' : '✗ ' . $still), "\n";
$c->query("DELETE FROM gov_cap_history WHERE reason LIKE 'مسبار %' OR reason LIKE 'إرجاعُ قيمةِ المسبار%' OR reason='محاولة غير المالك'");
echo "✔ نُظِّف أثرُ المسبارِ من السجل\n";
