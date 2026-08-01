<?php
/**
 * tests/fin26_http_proof.php — برهان HTTP حي لدور «إدارة التمويل» (FIN-26)
 * ═══════════════════════════════════════════════════════════════════════════
 * شاهدٌ لا تقرير — دخول فعلي بالحساب الحقيقي «تمويل» (PHP cURL لا Git Bash):
 *   ① الدخول يهبط على لوحته (dashboard → financing_board) والقائمة تعرض باب
 *      التمويل بشاشتيه.
 *   ② الشاشتان 210/211 ترجعان 200 بمحتواهما.
 *   ③ sensitive_read_log كتب سطر اطّلاع للفتحين (اللوحة والسجل).
 *   ④ مستخدم من الدور نفسه **بلا منحة** (مسبار مؤقت): الباب لا يُصيَّر في
 *      قائمته، وشاشة 210 ترد 403 — fail-closed حيًّا.
 *   ⑤ شاشة المنح 213 (بجلسة الدور 19): المنح من الشاشة يقع بسطر توقيعه،
 *      والإلغاء بسببه يعلّم لا يحذف.
 * ثم يكنس كل ما بذر. التشغيل: php tests/fin26_http_proof.php (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = 'http://localhost/ems';
$TMP = sys_get_temp_dir();
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

function f26_req($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 40,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr((string) $raw, 0, $hs), substr((string) $raw, $hs));
}
function f26_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = f26_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return f26_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$PROBE_USER = 'fin26_probe_nogrant';
$teardown = function () use ($db, $PROBE_USER) {
    $pid = $db->query("SELECT id FROM users WHERE username = '{$PROBE_USER}'")->fetch_assoc();
    if ($pid) {
        $p = intval($pid['id']);
        $g = $db->query("SELECT grant_id FROM ownership_access_grants WHERE person_id = {$p}");
        while ($g && ($x = $g->fetch_assoc())) {
            $db->query("DELETE FROM approval_signatures WHERE document_type = 'ownership_grant' AND document_id = " . intval($x['grant_id']));
        }
        $db->query("DELETE FROM ownership_access_grants WHERE person_id = {$p}");
        $db->query("DELETE FROM sensitive_read_log WHERE person_id = {$p}");
        $db->query("DELETE FROM users WHERE id = {$p}");
    }
    $db->query("DELETE FROM employees WHERE name = 'مسبار تمويل FIN26 مؤقت'");
};
register_shutdown_function($teardown);
$teardown();

$T0 = date('Y-m-d H:i:s');
$FIN_UID = intval($db->query("SELECT id FROM users WHERE username = 'تمويل'")->fetch_assoc()['id']);
check($FIN_UID > 0, "الحساب الحقيقي «تمويل» قائم (#{$FIN_UID}) مربوطًا بموظف");

head('① الدخول بالحساب الحقيقي — يهبط على لوحته والقائمة تعرض باب التمويل');
$jar = $TMP . '/fin26_live.cookie';
list($c, $h, $b) = f26_login('تمويل', $jar);
check($c === 302 && strpos($h, 'main/dashboard.php') !== false, "الدخول نجح (302 → dashboard) [{$c}]");
list($c, $h, $b) = f26_req($BASE . '/main/dashboard.php', $jar);
check($c === 302 && strpos($h, 'Financing/financing_board.php') !== false, 'dashboard يحوّله للوحة دوره مباشرة (UX-01 §4)');
list($c, $h, $b) = f26_req($BASE . '/Financing/financing_board.php', $jar);
check($c === 200 && strpos($b, 'لوحة إدارة التمويل') !== false, "لوحته 200 بعنوانها [{$c}]");
check(strpos($b, 'financiers_registry.php') !== false && strpos($b, 'سجل الممولين') !== false,
    'القائمة تعرض باب التمويل بشاشته (سجل الممولين مُصيَّر)');
check(strpos($b, 'financing_operation_new.php') !== false, 'وإنشاء عملية تمويل مُصيَّر');
check(strpos($b, 'ownership_grants.php') === false, 'وشاشة المنح 213 ليست في قائمته — ملكيتها للحوكمة (1 · 19)');

head('② الشاشتان 210/211 ترجعان 200');
list($c, $h, $b) = f26_req($BASE . '/Financing/financiers_registry.php', $jar);
check($c === 200 && strpos($b, 'سجل الممولين') !== false, "210 سجل الممولين: 200 [{$c}]");
check(strpos($b, 'محجوب — يلزم ownership.finance_terms') === false, 'وصاحب منحة الشروط يرى عمود الاستحقاق لا الحجب');
list($c, $h, $b) = f26_req($BASE . '/Financing/financing_operation_new.php', $jar);
check($c === 200, "211 إنشاء عملية تمويل: 200 [{$c}]");

head('③ سطر الاطّلاع مكتوب — «التسرّب يقع بالاطّلاع لا بالتغيير»');
$n = $db->query("SELECT COUNT(*) c FROM sensitive_read_log WHERE person_id = {$FIN_UID}
                  AND element_code = 'ownership.financing_board' AND result = 'allowed' AND at >= '{$T0}'")->fetch_assoc();
check(intval($n['c']) >= 1, 'فتح اللوحة بسطر اطّلاع');
$n = $db->query("SELECT COUNT(*) c FROM sensitive_read_log WHERE person_id = {$FIN_UID}
                  AND element_code = 'ownership.financiers_registry' AND result = 'allowed' AND at >= '{$T0}'")->fetch_assoc();
check(intval($n['c']) >= 1, 'وفتح سجل الممولين بسطر اطّلاع');

head('④ مستخدم الدور بلا منحة — الباب لا يُصيَّر وشاشته 403');
$hash = password_hash('12345678', PASSWORD_BCRYPT);
// قاعدة الدخول: لا حساب بلا موظف مُسنَد — فالمسبار بموظف مسبار مؤقت يُكنس معه
$db->query("INSERT INTO employees (name, company_id, phone, employment_classification, status)
            VALUES ('مسبار تمويل FIN26 مؤقت', 4, '0', 'مقبول', 1)");
$probeEmp = intval($db->insert_id);
$st = $db->prepare("INSERT INTO users (name, username, password, role, company_id, employee_id, status) VALUES ('مسبار تمويل بلا منحة', ?, ?, '26', 4, ?, 'active')");
$st->bind_param('ssi', $PROBE_USER, $hash, $probeEmp);
$st->execute();
$probeId = intval($st->insert_id);
$st->close();
check($probeId > 0, "مسبار مؤقت من الدور 26 بلا أي منحة (#{$probeId})");
$jar2 = $TMP . '/fin26_probe.cookie';
list($c, $h, $b) = f26_login($PROBE_USER, $jar2);
check($c === 302, 'دخول المسبار نجح');
list($c, $h, $b) = f26_req($BASE . '/Financing/financing_board.php', $jar2);
check($c === 200 && strpos($b, 'بوابة المجال المقيَّد') !== false, 'لوحته (مسكنه) تفتح بإعلان الحجب — ولا رقم يُجلب');
check(strpos($b, 'financiers_registry.php?') === false && strpos($b, 'سجل الممولين') === false,
    'باب FIN غير مُصيَّر في قائمته أصلًا — لا معطَّلًا ولا مخفيَّ المحتوى');
list($c, $h, $b) = f26_req($BASE . '/Financing/financiers_registry.php', $jar2);
check($c === 403, "وقصْد الشاشة 210 مباشرة يرد 403 [{$c}]");
list($c, $h, $b) = f26_req($BASE . '/Financing/financing_operation_new.php', $jar2);
check($c === 403, "و211 كذلك 403 [{$c}]");

head('⑤ شاشة المنح 213 بجلسة الدور 19 — منح بتوقيع وإلغاء بتعليم');
$jar3 = $TMP . '/fin26_gov.cookie';
list($c, $h, $b) = f26_login('fin.deptmgr@equipation.sd', $jar3);
check($c === 302, 'دخول مدير الإدارة المالية (19)');
list($c, $h, $b) = f26_req($BASE . '/Governance/ownership_grants.php', $jar3);
check($c === 200 && strpos($b, 'منح المجال المقيَّد') !== false, "شاشة المنح 200 [{$c}]");
list($c, $h, $b) = f26_req($BASE . '/Governance/ownership_grants.php', $jar3, array(
    'op' => 'grant', 'person_id' => $probeId, 'permission_code' => 'ownership.owner_view',
    'reason' => 'برهان fin26 — منحة مسبار مؤقتة'));
$g = $db->query("SELECT grant_id FROM ownership_access_grants WHERE person_id = {$probeId} AND state = 'active'")->fetch_assoc();
check($c === 200 && $g !== null, 'المنح من الشاشة وقع (منحة نافذة للمسبار)');
$gid = $g ? intval($g['grant_id']) : 0;
$n = $db->query("SELECT COUNT(*) c FROM approval_signatures WHERE document_type = 'ownership_grant' AND document_id = {$gid} AND result = 'signed'")->fetch_assoc();
check(intval($n['c']) >= 1, 'وبسطر توقيع AuthorityGuard');
list($c, $h, $b) = f26_req($BASE . '/Financing/financiers_registry.php', $jar2);
check($c === 200, 'والمسبار بعد منحته يفتح 210 فورًا (200) — المنحة هي المفتاح لا الدور');
list($c, $h, $b) = f26_req($BASE . '/Governance/ownership_grants.php', $jar3, array(
    'op' => 'revoke', 'grant_id' => $gid, 'revoke_reason' => 'انتهاء برهان fin26'));
$g = $db->query("SELECT state FROM ownership_access_grants WHERE grant_id = {$gid}")->fetch_assoc();
check($c === 200 && $g && $g['state'] === 'revoked', 'الإلغاء بسببه علّم الحالة revoked — والصف باقٍ للتدقيق');
list($c, $h, $b) = f26_req($BASE . '/Financing/financiers_registry.php', $jar2);
check($c === 403, 'وبعد الإلغاء عاد 403 فورًا — fail-closed حي');

// شاشة المنح محجوبة عن الدور 26 نفسه
list($c, $h, $b) = f26_req($BASE . '/Governance/ownership_grants.php', $jar);
check($c === 403, 'وشاشة المنح 213 ترد 403 لحساب «تمويل» نفسه — المانح غير الممنوح');

@unlink($jar); @unlink($jar2); @unlink($jar3);
echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
