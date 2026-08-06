<?php
/**
 * m00_drill_test — رباعية «التعمّقُ في مؤشر» board.drill (FLW-066 · القبول ١٤).
 *
 * عقد الفعل (§6-6): يُفتح المصدرُ التفصيليُّ للرقم بنطاق الإدارة العليا —
 * **بلا أثرٍ على البيانات** · وعكسُه «—» (لا أثرَ فلا عكس):
 *   سماح : التنفيذ (دور 9) يفتح اللوحة ويتعمّق لمصدر التفصيل (التقارير الثمانية)
 *   منع  : دورٌ بلا صلاحيةٍ لا يُصيَّر له المصدر ويُرد نداؤه في الخادم
 *   تكرار: التعمّقُ مرتين قراءتان متطابقتان — وصفرُ واقعةٍ جديدةٍ في الجذر
 *   عكس  : لا أثرَ على البيانات أصلًا — لقطاتُ الجداول قبل/بعد متطابقة
 *
 * التشغيل: php tools/m00_drill_test.php
 */

if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();

$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/m00_drill_' . getmypid() . '.jar';
$pass = 0; $fail = 0;
function ok($cond, $label) {
    global $pass, $fail;
    if ($cond) { $pass++; fwrite(STDOUT, "  ✅ $label\n"); }
    else { $fail++; fwrite(STDOUT, "  ❌ $label\n"); }
}
function req($url, $post = null, $follow = true) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_HEADER => !$follow,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR, CURLOPT_TIMEOUT => 30,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = (string) curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return array('code' => $code, 'body' => $body);
}
function csrfOf($html) {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/u', $html, $m)) { return $m[1]; }
    if (preg_match('/window\.csrfToken\s*=\s*[\'"]([^\'"]+)[\'"]/u', $html, $m)) { return $m[1]; }
    return '';
}
function loginAs($user, $passWord) {
    global $BASE, $JAR;
    @unlink($JAR);
    $lg = req($BASE . '/login.php');
    req($BASE . '/login.php', array('username' => $user, 'password' => $passWord, 'csrf_token' => csrfOf($lg['body'])));
    return true;
}
/** لقطةُ أثرٍ: عدُّ الجداول التي قد يلمسها فعلُ قراءةٍ لو انحرف */
function snapshot(mysqli $conn) {
    $s = array();
    foreach (array('ems_business_events', 'exec_approvals', 'exec_decisions',
                   'exec_board_snapshots', 'exec_contract_signings', 'exec_project_charters') as $t) {
        $r = $conn->query("SELECT COUNT(*) c FROM `{$t}`");
        $s[$t] = $r ? (int) $r->fetch_assoc()['c'] : -1;
    }
    return $s;
}

fwrite(STDOUT, "═══ رباعية board.drill (FLW-066) ═══\n");

/* ── سماح: التنفيذ يفتح اللوحة ثم يتعمّق للمصدر التفصيلي ────────────────── */
loginAs('تنفيذ', '12345678');
$before = snapshot($conn);
$bd = req($BASE . '/Portal/ceo_board.php');
ok($bd['code'] === 200 && mb_strpos($bd['body'], 'لوحة المدير التنفيذي') !== false, 'سماح: اللوحة تُصيَّر للدور 9');
ok(mb_strpos($bd['body'], 'ceo_reports.php') !== false, 'سماح: رابط التعمّق للمصدر التفصيلي ظاهر');
$d1 = req($BASE . '/Portal/ceo_reports.php');
ok($d1['code'] === 200 && mb_strpos($d1['body'], 'login.php?') === false
   && mb_strpos($d1['body'], 'dashboard.php') === false || mb_strpos($d1['body'], 'تقارير') !== false,
   'سماح: المصدر التفصيلي يُفتح بنطاق الإدارة العليا');

/* ── تكرار: التعمّق مرتين — قراءتان وصفر واقعة جديدة ────────────────────── */
$d2 = req($BASE . '/Portal/ceo_reports.php');
ok($d2['code'] === $d1['code'], 'تكرار: النداء الثاني قراءةٌ كالأول');
$afterReads = snapshot($conn);
ok($afterReads['ems_business_events'] === $before['ems_business_events'],
   'تكرار: صفرُ واقعةٍ جديدةٍ في الجذر بعد قراءتين');

/* ── عكس: لا أثرَ فلا عكس — لقطات الجداول لم تتحرك ──────────────────────── */
ok($afterReads === $before, 'عكس: بلا أثرٍ على البيانات — اللقطتان متطابقتان (عكسُه «—»)');

/* ── منع: دورٌ بلا صلاحية — لا يُصيَّر ولا يُقبل نداؤه ──────────────────── */
loginAs('موقع', '12345678');
$dx = req($BASE . '/Portal/ceo_board.php', null, false);
$deniedBoard = ($dx['code'] === 302 || $dx['code'] === 403);
ok($deniedBoard, 'منع: اللوحة لا تُصيَّر للدور 6 (رد ' . $dx['code'] . ')');
$dy = req($BASE . '/Portal/ceo_reports.php', null, false);
$deniedRep = ($dy['code'] === 302 || $dy['code'] === 403);
ok($deniedRep, 'منع: المصدر التفصيلي مردودٌ في الخادم (رد ' . $dy['code'] . ')');
$afterDenied = snapshot($conn);
ok($afterDenied === $before, 'منع: المحاولة المرفوضة بلا أثرٍ على البيانات');

@unlink($JAR);
fwrite(STDOUT, "═══ الحصيلة: {$pass} ناجح · {$fail} فاشل ═══\n");
exit($fail > 0 ? 1 : 0);
