<?php
/** مسبارُ جلسةِ النيابة: فتحٌ حي عبر HTTP ← الشريطُ ← النسبةُ المزدوجةُ ← الإغلاق */
error_reporting(E_ALL);
$BASE = 'http://localhost/ems';
$jar = sys_get_temp_dir() . '/uxw_imp.txt';
@unlink($jar);
$http = function ($url, $post = null) use ($jar) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 40));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $b = curl_exec($ch); curl_close($ch); return (string) $b;
};
$csrf = function ($html) {
    return preg_match('~name=["\']csrf_token["\']\s+value=["\']([^"\']+)~', $html, $m) ? $m[1] : '';
};
// ① دخولُ «تنفيذ» (الدور 9)
$login = $http("$BASE/login.php");
$http("$BASE/login.php", array('username' => 'تنفيذ', 'password' => '12345678', 'csrf_token' => $csrf($login)));

// ② فتحُ جلسةٍ موضعَ «محمد» (الدور 1 — تحت التنفيذية بالسلطة العريضة)
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../config.php';
while (ob_get_level()) ob_end_clean();
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');
$tid = (int) $conn->query("SELECT id FROM users WHERE username='محمد' AND status=1 LIMIT 1")->fetch_row()[0];

$scr = $http("$BASE/Governance/impersonations.php");
$open = $http("$BASE/Governance/impersonations.php", array(
    'imp_action' => 'open', 'target_user' => $tid, 'reason' => 'مسبار إثبات النيابة — يُغلق فورًا', 'hours' => 1,
    'csrf_token' => $csrf($scr)));
echo '① فُتحت: ', (mb_strpos($open, 'فُتحت الجلسةُ') !== false ? '✔' : '✗'), "\n";
echo '② الشريطُ الظاهر: ', (mb_strpos($open, 'ems-imp-strip') !== false && mb_strpos($open, 'تعمل الآن موضعَ') !== false ? '✔' : '✗'), "\n";

// ③ الشريطُ على شاشةٍ أخرى (القشرةُ الموحدة)
$other = $http("$BASE/Portal/my_tasks.php");
echo '③ الشريطُ يلاحق كلَّ الشاشات: ', (mb_strpos($other, 'ems-imp-strip') !== false ? '✔' : '✗'), "\n";

// ④ الصفُّ الحي والإخطار
$row = $conn->query("SELECT imp_id, notified_at IS NOT NULL n FROM impersonation_sessions
                      WHERE closed_at IS NULL ORDER BY imp_id DESC LIMIT 1")->fetch_assoc();
echo '④ الجلسةُ في السجلِّ وأُخطر صاحبُها: ', ($row && $row['n'] ? "✔ (#{$row['imp_id']})" : '✗'), "\n";
$notif = (int) $conn->query("SELECT COUNT(*) FROM fin_notifications
                              WHERE target_user_id = {$tid} AND title LIKE '%جلسةُ نيابةٍ%'")->fetch_row()[0];
echo '⑤ إخطارُ fin_notifications: ', ($notif > 0 ? '✔' : '✗'), "\n";

// ⑥ النسبةُ المزدوجةُ في دفترِ الأفعال (كتبها imp.open نفسُه بجلسةٍ نشطة؟ الفتحُ سبق التخزين —
//    فالأصدقُ فعلٌ لاحقٌ: الإغلاقُ يُدوَّن والجلسةُ ما تزال نشطةً لحظةَ التدوين)
$scr2 = $http("$BASE/Governance/impersonations.php");
$http("$BASE/Governance/impersonations.php", array('imp_action' => 'close', 'csrf_token' => $csrf($scr2)));
$attr = $conn->query("SELECT acted_by, acted_for, impersonation_id FROM activity_logs
                       WHERE action_type = 'imp.close' ORDER BY id DESC LIMIT 1")->fetch_assoc();
echo '⑥ النسبةُ المزدوجةُ (imp.close): ',
    ($attr && $attr['acted_by'] !== null && $attr['acted_for'] !== null && $attr['impersonation_id'] !== null
        ? "✔ فعل {$attr['acted_by']} عن {$attr['acted_for']} بجلسة {$attr['impersonation_id']}" : '✗ ' . json_encode($attr)), "\n";
$closed = $conn->query("SELECT closed_at IS NOT NULL c FROM impersonation_sessions
                         WHERE imp_id = " . (int) $row['imp_id'])->fetch_row()[0];
echo '⑦ أُغلقت: ', ($closed ? '✔' : '✗'), "\n";

// ⑧ سلبي: فتحٌ على المراجعِ المستقل يُرفَض بالقادح
$aud = (int) ($conn->query("SELECT id FROM users WHERE role = 33 AND status = 1 LIMIT 1")->fetch_row()[0] ?? 0);
if ($aud > 0) {
    $scr3 = $http("$BASE/Governance/impersonations.php");
    $neg = $http("$BASE/Governance/impersonations.php", array(
        'imp_action' => 'open', 'target_user' => $aud, 'reason' => 'مسبار سلبي', 'hours' => 1,
        'csrf_token' => $csrf($scr3)));
    echo '⑧ السلبي — نيابةٌ على رقابيٍّ تُرفض: ', (mb_strpos($neg, 'فُتحت الجلسةُ') === false ? '✔' : '✗ مرَّت!'), "\n";
}
// تنظيفُ أثرِ المسبار
$conn->query("DELETE FROM impersonation_sessions WHERE reason LIKE 'مسبار %'");
$conn->query("DELETE FROM fin_notifications WHERE title LIKE '%جلسةُ نيابةٍ%' AND target_user_id = {$tid}");
echo "✔ نُظِّف أثرُ المسبار\n";
