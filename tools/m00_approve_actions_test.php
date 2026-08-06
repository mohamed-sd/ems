<?php
/**
 * m00_approve_actions_test — رباعية الاعتماد الأعلى (M-00 ④-٢ + القبول ١٤).
 *
 * بحساب التنفيذ: صفٌّ قيد المراجعة ثم:
 *  رفضُ الناقص: رد بلا سبب · مشروط بلا شرط · تأجيل بلا تاريخ (ثلاثتها لا تُقيَّد)
 *  تأجيلٌ بتاريخ ✓ ثم اعتمادُ المؤجَّل ✓ (يُعاد بتُّه) وتُنشر الحقيقة مرةً واحدة
 *  لا قرارَ على قرار (409 منطقي) · وغير التنفيذي محجوب (403/302)
 *
 * التشغيل: php tools/m00_approve_actions_test.php
 */

if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();

$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/m00_appr_' . getmypid() . '.jar';
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
function statusOf(mysqli $conn, $id) {
    // اللحاق CMP03_FOLLOWUP: الشاشة صارت على جدولها الأصلي exec_approvals —
    // وتُعاد الأعمدة بمفاتيح المستند نفسها لتبقى إثباتات الرباعية كما هي
    $w = $conn->query("SELECT * FROM exec_approvals WHERE id = " . (int) $id)->fetch_assoc();
    if ($w) {
        $w['payload'] = array(
            'قراري'                  => (string) ($w['decision'] ?? ''),
            'سبب القرار'             => (string) ($w['decision_reason'] ?? ''),
            'المعتمِد — الاسم والصفة' => (string) ($w['approver_name'] ?? ''),
        );
    }
    return $w;
}

fwrite(STDOUT, "═══ رباعية الاعتماد الأعلى (④-٢) ═══\n");
loginAs('تنفيذ', '12345678');
$pg = req($BASE . '/Portal/ceo_approvals.php');
$tok = csrfOf($pg['body']);
ok($tok !== '', 'دخول التنفيذ وفتح الشاشة');

// صف تجربة قيد المراجعة
$reqNo = 'APR-M00-' . date('His');
req($BASE . '/Portal/ceo_approvals.php', array(
    'cmp03_action' => 'add', 'csrf_token' => $tok,
    'f0' => $reqNo, 'f1' => date('Y-m-d'), 'f2' => 'عقد', 'f3' => 'مستند تجربة الرباعية',
    'f4' => 'إدارة التشغيل', 'f5' => 'تجاوز سقف', 'f6' => '5000', 'f7' => 'USD',
    'f8' => '3000', 'f9' => '2000', 'f11' => '48 ساعة', 'f17' => 'قيد المراجعة',
));
$rw = $conn->query("SELECT id FROM exec_approvals
                     WHERE request_no='" . $conn->real_escape_string($reqNo) . "'
                     ORDER BY id DESC LIMIT 1")->fetch_assoc();
ok($rw !== null, 'صف التجربة حُفظ (قيد المراجعة)');
if (!$rw) { fwrite(STDOUT, "═══ توقف — لا صف ═══\n"); exit(1); }
$rid = (int) $rw['id'];
$lbl = 'EXAP-' . $rid;

// ① الناقص يُرفض ولا يُقيَّد
req($BASE . '/Portal/ceo_approvals.php', array('cmp03_action' => 'decide', 'csrf_token' => $tok,
    'row' => $rid, 'decision' => 'رد', 'reason' => ''), false);
$s = statusOf($conn, $rid);
ok($s['status'] === 'قيد المراجعة', 'رد بلا سبب — لم يُقيَّد');
req($BASE . '/Portal/ceo_approvals.php', array('cmp03_action' => 'decide', 'csrf_token' => $tok,
    'row' => $rid, 'decision' => 'اعتماد بشرط', 'reason' => ''), false);
$s = statusOf($conn, $rid);
ok($s['status'] === 'قيد المراجعة', 'مشروط بلا شرط — لم يُقيَّد');
req($BASE . '/Portal/ceo_approvals.php', array('cmp03_action' => 'decide', 'csrf_token' => $tok,
    'row' => $rid, 'decision' => 'تأجيل', 'until' => ''), false);
$s = statusOf($conn, $rid);
ok($s['status'] === 'قيد المراجعة', 'تأجيل بلا تاريخ — لم يُقيَّد');

// ② تأجيل بتاريخ — يُقيَّد مؤجلًا ولا حقيقة تُنشر
$untilD = date('Y-m-d', time() + 3 * 86400);
req($BASE . '/Portal/ceo_approvals.php', array('cmp03_action' => 'decide', 'csrf_token' => $tok,
    'row' => $rid, 'decision' => 'تأجيل', 'until' => $untilD));
$s = statusOf($conn, $rid);
ok($s['status'] === 'مؤجل', 'التأجيل بتاريخ قُيّد (مؤجل)');
ok(mb_strpos((string) ($s['payload']['سبب القرار'] ?? ''), $untilD) !== false, 'التاريخ في سبب القرار');
$n0 = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
                           WHERE idempotency_key='exec_approval:{$lbl}'")->fetch_assoc()['c'];
ok($n0 === 0, 'لا حقيقة اعتمادٍ للتأجيل');

// ③ اعتماد المؤجَّل (يُعاد بتُّه) — يُقيَّد وتُنشر الحقيقة
req($BASE . '/Portal/ceo_approvals.php', array('cmp03_action' => 'decide', 'csrf_token' => $tok,
    'row' => $rid, 'decision' => 'اعتماد', 'reason' => ''));
$s = statusOf($conn, $rid);
ok($s['status'] === 'معتمد', 'اعتماد المؤجَّل قُيّد (معتمد)');
ok(($s['payload']['قراري'] ?? '') === 'اعتماد', 'عمود «قراري» مختوم');
ok(mb_strpos((string) ($s['payload']['المعتمِد — الاسم والصفة'] ?? ''), 'الإدارة التنفيذية') !== false, 'المعتمِد بالاسم والصفة');
$f = $conn->query("SELECT payload FROM ems_business_events
                    WHERE idempotency_key='exec_approval:{$lbl}' LIMIT 1")->fetch_assoc();
ok($f !== null, 'حقيقة exec.approval.granted نُشرت (' . $lbl . ')');
if ($f) {
    $p = json_decode((string) $f['payload'], true) ?: array();
    ok(($p['request_no'] ?? '') === $reqNo, 'الحمولة تحمل رقم الطلب');
    ok(($p['decision'] ?? '') === 'اعتماد', 'الحمولة تحمل القرار');
}

// ④ لا قرارَ على قرار
req($BASE . '/Portal/ceo_approvals.php', array('cmp03_action' => 'decide', 'csrf_token' => $tok,
    'row' => $rid, 'decision' => 'رد', 'reason' => 'محاولة بعد الاعتماد'), false);
$s = statusOf($conn, $rid);
ok($s['status'] === 'معتمد', 'المقرَّر لا يُقرَّر ثانية');
$n1 = (int) $conn->query("SELECT COUNT(*) c FROM ems_business_events
                           WHERE idempotency_key='exec_approval:{$lbl}'")->fetch_assoc()['c'];
ok($n1 === 1, 'الحقيقة واحدة لا تتضاعف');

// ⑤ غير التنفيذي محجوب
loginAs('موقع', '12345678');
$pg2 = req($BASE . '/Portal/ceo_approvals.php');
$tok2 = csrfOf($pg2['body']);
$r = req($BASE . '/Portal/ceo_approvals.php', array('cmp03_action' => 'decide', 'csrf_token' => $tok2,
    'row' => $rid, 'decision' => 'اعتماد'), false);
$s = statusOf($conn, $rid);
ok($s['status'] === 'معتمد' && (string) ($s['payload']['قراري'] ?? '') === 'اعتماد',
   'دور 6 لم يغيّر شيئًا (رد ' . $r['code'] . ')');

// نظافة: صف التجربة (الحقيقة تبقى — واقعة وقعت)
$conn->query("DELETE FROM exec_approvals WHERE id={$rid} AND is_seed=0");

@unlink($JAR);
fwrite(STDOUT, "═══ الحصيلة: {$pass} ناجح · {$fail} فاشل ═══\n");
exit($fail > 0 ? 1 : 0);
