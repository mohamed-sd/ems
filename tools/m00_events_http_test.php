<?php
/**
 * m00_events_http_test — إثبات ③ project.chartered و④ exec.decision.made من الشاشتين.
 *
 * ④ بحساب التنفيذ (u881): حارس BR-CEO-04 يرفض المحسوم الناقص، والكامل تُنشر
 *   حقيقته وتُجدول متابعته (SRC-10) — canonical_file يُخزَّن بالاسم المجرد.
 * ③ بحساب «موقع» (دور 6 يملك الإضافة): إضافة مشروع من الشاشة → project.chartered.
 *
 * التشغيل: php tools/m00_events_http_test.php
 * (POST العربي عبر PHP cURL — گوتشا Git Bash يفسد argv العربي)
 */

if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();

$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/m00_http_' . getmypid() . '.jar';
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
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_HEADER => !$follow,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR,
        CURLOPT_TIMEOUT => 30,
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
    $tok = csrfOf($lg['body']);
    req($BASE . '/login.php', array('username' => $user, 'password' => $passWord, 'csrf_token' => $tok));
    $home = req($BASE . '/dashboard.php');
    return mb_strpos($home['body'], 'login.php') === false || mb_strpos($home['body'], 'logout') !== false;
}

/* ── ④ BR-CEO-04 + exec.decision.made بحساب التنفيذ ──────────────────── */
fwrite(STDOUT, "═══ ④ ceo_risk: الحارس ثم الحقيقة والمتابعة (u881) ═══\n");
ok(loginAs('تنفيذ', '12345678'), 'دخول حساب التنفيذ');
$pg = req($BASE . '/Portal/ceo_risk.php');
$tok = csrfOf($pg['body']);
ok($tok !== '', 'رمز CSRF لشاشة القرارات');

// ④-أ: محسوم بلا مكلَّف/مهلة → يُرفض باسم الناقص (لا صف يُحفظ)
$guardNo = 'DEC-M00-G' . date('His');
$r1 = req($BASE . '/Portal/ceo_risk.php', array(
    'cmp03_action' => 'add', 'csrf_token' => $tok,
    'f0' => $guardNo, 'f4' => 'قضية تجربة الحارس', 'f8' => 'الخيار أ',
    'f14' => date('Y-m-d'), 'f15' => 'محسوم',
), false);
$saved1 = $conn->query("SELECT id FROM exec_decisions
                         WHERE decision_no='" . $conn->real_escape_string($guardNo) . "' LIMIT 1")->fetch_assoc();
ok($saved1 === null, 'BR-CEO-04: الصف الناقص لم يُحفظ');
ok(mb_strpos(rawurldecode((string) $r1['body']), 'BR-CEO-04') !== false, 'الرفض مسمّى بالحارس والناقصَين');

// ④-ب: محسوم كامل → يُحفظ وتُنشر الحقيقة وتُجدول المتابعة
$decNo = 'DEC-M00-' . date('His');
req($BASE . '/Portal/ceo_risk.php', array(
    'cmp03_action' => 'add', 'csrf_token' => $tok,
    'f0' => $decNo, 'f1' => date('Y-m-d'), 'f2' => 'الإدارة التنفيذية',
    'f3' => 'تشغيلية', 'f4' => 'قضية تجربة §11 كاملة', 'f7' => 'أ · ب',
    'f8' => 'الخيار أ', 'f9' => 'أسرع أثرًا',
    'f10' => 'إدارة التشغيل', 'f11' => date('Y-m-d', time() + 14 * 86400),
    'f12' => date('Y-m-d', time() + 7 * 86400),
    'f13' => 'المدير التنفيذي', 'f14' => date('Y-m-d'), 'f15' => 'محسوم',
));
$rw = $conn->query("SELECT id, is_seed FROM exec_decisions
                     WHERE company_id=4
                       AND decision_no='" . $conn->real_escape_string($decNo) . "'
                     ORDER BY id DESC LIMIT 1")->fetch_assoc();
ok($rw !== null && (int) $rw['is_seed'] === 0, 'الصف الكامل حُفظ حقيقيًّا (is_seed=0)');
if ($rw) {
    $ref = 'EXDC-' . (int) $rw['id'];
    $f2 = $conn->query("SELECT payload FROM ems_business_events
                         WHERE event_key='exec.decision.made'
                           AND idempotency_key='exec_decision:{$ref}' LIMIT 1")->fetch_assoc();
    ok($f2 !== null, 'حقيقة exec.decision.made في الجذر (' . $ref . ')');
    if ($f2) {
        $p2 = json_decode((string) $f2['payload'], true) ?: array();
        ok(($p2['assignee'] ?? '') === 'إدارة التشغيل', 'الحمولة تسمّي الجهة المكلَّفة');
    }
    $wi = $conn->query("SELECT id, priority, owner_user_id FROM work_items
                         WHERE source_type='SRC-10' AND source_ref='{$ref}' LIMIT 1")->fetch_assoc();
    ok($wi !== null, 'مهمة متابعة SRC-10 مجدولة' . ($wi ? ' #' . $wi['id'] : ''));
    if ($wi) {
        ok((int) $wi['owner_user_id'] === 881, 'المتابعة على صاحب القرار (u881)');
        $conn->query("UPDATE work_items SET status='cancelled', status_reason='اختبار §11' WHERE id=" . (int) $wi['id']);
    }
    $conn->query("DELETE FROM exec_decisions WHERE id=" . (int) $rw['id'] . " AND is_seed=0");
}

/* ── ③ project.chartered بحساب «موقع» (دور 6 يملك الإضافة) ───────────── */
fwrite(STDOUT, "═══ ③ project.chartered من Projects/projects.php (دور 6) ═══\n");
ok(loginAs('موقع', '12345678'), 'دخول حساب «موقع»');
$pg = req($BASE . '/Projects/projects.php');
$tok = csrfOf($pg['body']);
ok($tok !== '' && mb_strpos($pg['body'], 'project_name') !== false, 'الشاشة مفتوحة وفيها فورم الإضافة');
$mark = 'مشروع تجربة §11 — ' . date('His');
$before = (int) $conn->query("SELECT COALESCE(MAX(id),0) m FROM project")->fetch_assoc()['m'];
// الشاشة تُلزم عميلًا قائمًا (FK fk_project_client) — خذ أول عميل co4 حي
$cli = $conn->query("SELECT id FROM clients WHERE company_id=4 AND COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 1")->fetch_assoc();
req($BASE . '/Projects/projects.php', array(
    'project_name' => $mark, 'name' => $mark,
    'client_id' => (int) ($cli['id'] ?? 0),
    'location' => 'موقع التجربة',
    'project_code' => 'PRJ-M00-' . date('His'), 'status' => 'نشط',
    'csrf_token' => $tok,
));
$row = $conn->query("SELECT id, name FROM project WHERE id > {$before} AND name = '"
    . $conn->real_escape_string($mark) . "' LIMIT 1")->fetch_assoc();
ok($row !== null, 'أُدرج المشروع عبر الشاشة' . ($row ? ' #' . $row['id'] : ''));
if ($row) {
    $pid = (int) $row['id'];
    $f = $conn->query("SELECT idempotency_key, payload FROM ems_business_events
                        WHERE event_key='project.chartered' AND entity_id={$pid} LIMIT 1")->fetch_assoc();
    ok($f !== null, 'حقيقة project.chartered في الجذر');
    if ($f) {
        $p = json_decode((string) $f['payload'], true) ?: array();
        ok(($p['name'] ?? '') === $mark, 'الحمولة تحمل اسم المشروع المدخل');
        ok(($f['idempotency_key'] ?? '') === 'project_chartered:' . $pid, 'مفتاح العطالة بالمشروع');
    }
    $conn->query("UPDATE project SET is_deleted=1, deleted_at=NOW() WHERE id={$pid}");
}

@unlink($JAR);
fwrite(STDOUT, "═══ الحصيلة: {$pass} ناجح · {$fail} فاشل ═══\n");
exit($fail > 0 ? 1 : 0);
