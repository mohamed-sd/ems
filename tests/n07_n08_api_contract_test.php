<?php
/**
 * tests/n07_n08_api_contract_test.php — طبقة API والمزامنة الأوفلاين (البوابة ③④)
 * ═══════════════════════════════════════════════════════════════════════════
 * N-07 (عقد FES §10.1): غلاف JSON موحَّد {success,message,data} · Bearer حصرًا
 * (401 موحَّدة بلا توكن) · 404 موحَّدة لمسار مجهول · الأخطاء بدلالتها لا 200 عامة.
 * N-08: **يوم موقع كامل دون اتصال ثم مزامنة بلا تكرار** — دفعة فترات تُرفع،
 * ثم تُعاد الدفعة نفسها (انقطاع مفتعل): صفر تكرار بالمفتاح الطبيعي
 * (معدة×تاريخ×وردية×فترة)، والمكرر يُرَدّ duplicate ولا يوقف الدفعة.
 * التشغيل: php tests/n07_n08_api_contract_test.php (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);

$BASE = 'http://localhost/ems/api';
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $EQ = 999888; $DATE = '2031-05-10';
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }
function hit($url, $method = 'GET', $body = null, $token = null) {
    $ch = curl_init($url);
    $hdr = array('Content-Type: application/json');
    if ($token !== null) { $hdr[] = 'Authorization: Bearer ' . $token; }
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $hdr, CURLOPT_TIMEOUT => 30));
    if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE)); }
    $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return array($c, json_decode((string) $b, true), (string) $b);
}

$teardown = function () use ($conn, $EQ, $DATE) {
    $conn->query("DELETE FROM shift_period_logs WHERE equipment_id = {$EQ} AND work_date = '{$DATE}'");
    $conn->query("DELETE FROM api_tokens WHERE device = 'n07-test'");
};
register_shutdown_function($teardown);
$teardown();

// توكن مبذور لمستخدم حي (المستخدم 9 — مدخل ساعات co4)
$token = bin2hex(random_bytes(24));
$hash = hash('sha256', $token);
$conn->query("INSERT INTO api_tokens (user_id, token_hash, device, expires_at) VALUES (9, '{$hash}', 'n07-test', DATE_ADD(NOW(), INTERVAL 1 DAY))");

head('N-07 — عقد الغلاف الموحَّد');
list($code, $j) = hit($BASE . '/board');
check($code === 401 && is_array($j) && $j['success'] === false && isset($j['message']),
      'بلا توكن ⇒ 401 بغلاف موحَّد {success:false, message}');
list($code, $j) = hit($BASE . '/board', 'GET', null, 'not-a-real-token');
check($code === 401 && $j['success'] === false, 'توكن باطل ⇒ 401 موحَّدة');
list($code, $j) = hit($BASE . '/board', 'GET', null, $token);
check($code === 200 && $j['success'] === true && array_key_exists('data', $j),
      'بالتوكن ⇒ 200 و{success:true, data} — العقد واحد للنجاح والفشل');
list($code, $j) = hit($BASE . '/no-such-resource', 'GET', null, $token);
check($code === 404 && $j['success'] === false, 'مسار مجهول ⇒ 404 موحَّدة لا صفحة HTML');

head('N-08 — يوم دون اتصال ثم مزامنة بلا تكرار');
$batch = array('periods' => array(
    array('work_date' => $DATE, 'equipment_id' => $EQ, 'shift_no' => 1, 'period_no' => 1, 'operator_person_id' => 999301, 'qty' => 62, 'run_minutes' => 330, 'period_minutes' => 360),
    array('work_date' => $DATE, 'equipment_id' => $EQ, 'shift_no' => 1, 'period_no' => 2, 'operator_person_id' => 999302, 'qty' => 41, 'run_minutes' => 240, 'period_minutes' => 240),
    array('work_date' => $DATE, 'equipment_id' => $EQ, 'shift_no' => 2, 'period_no' => 1, 'operator_person_id' => 999301, 'qty' => 48, 'run_minutes' => 300, 'period_minutes' => 300),
));
list($code, $j) = hit($BASE . '/sync/periods', 'POST', $batch, $token);
check($code === 200 && $j['success'] === true && $j['data']['applied'] === 3 && $j['data']['duplicates'] === 0,
      'دفعة يوم كامل (3 فترات بمشغّليها) طُبّقت: ' . $j['message']);

// انقطاع مفتعل: إعادة الدفعة نفسها كاملة
list($code, $j) = hit($BASE . '/sync/periods', 'POST', $batch, $token);
check($code === 200 && $j['data']['applied'] === 0 && $j['data']['duplicates'] === 3,
      'إعادة الدفعة بعد الانقطاع: صفر تطبيق و3 duplicates — العطالة بالمفتاح الطبيعي (معدة×تاريخ×وردية×فترة)');
$q = $conn->query("SELECT COUNT(*) c FROM shift_period_logs WHERE equipment_id={$EQ} AND work_date='{$DATE}'")->fetch_assoc();
check((int) $q['c'] === 3, 'وفي القاعدة 3 صفوف لا 6 — صفر تكرار فعليًّا');

// دفعة مختلطة: جديد + مكرر + مرفوض بسببه — البند يقف وحده والدفعة تمضي
$mixed = array('periods' => array(
    array('work_date' => $DATE, 'equipment_id' => $EQ, 'shift_no' => 2, 'period_no' => 2, 'operator_person_id' => 999302, 'qty' => 39, 'run_minutes' => 280, 'period_minutes' => 300),
    array('work_date' => $DATE, 'equipment_id' => $EQ, 'shift_no' => 1, 'period_no' => 1, 'operator_person_id' => 999301),
    array('work_date' => $DATE, 'equipment_id' => $EQ, 'shift_no' => 3, 'period_no' => 1, 'operator_person_id' => 0),
));
list($code, $j) = hit($BASE . '/sync/periods', 'POST', $mixed, $token);
check($j['data']['applied'] === 1 && $j['data']['duplicates'] === 1 && $j['data']['rejected'] === 1,
      'دفعة مختلطة: مطبَّق 1 · مكرر 1 · مرفوض بسببه 1 (فترة بلا مشغّل) — البند يقف وحده');
$rej = null;
foreach ($j['data']['results'] as $x) { if ($x['status'] === 'rejected') { $rej = $x; } }
check($rej !== null && mb_strpos((string) $rej['reason'], 'مشغّل') !== false, 'والرفض بسببه المحكوم لا بصمت');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
