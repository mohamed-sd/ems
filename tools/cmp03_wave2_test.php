<?php
/**
 * tools/cmp03_wave2_test.php — إثبات تحرير الشاشات من المخزن البيني (الموجة ٢)
 * ① القراءة: الشاشة تعرض بذورها المرحَّلة من جدولها الأصلي.
 * ② الكتابة: إضافة من الفورم تهبط في scr_* لا في cmp03_screen_rows.
 * ③ الحارس المرتحل: m00_review_block يقرأ من scr_contract_review.
 * التشغيل: php tools/cmp03_wave2_test.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
require_once __DIR__ . '/../includes/m00_exec_helpers.php';

$BASE = 'http://localhost/ems';
$JAR  = sys_get_temp_dir() . '/w2_' . getmypid() . '.jar';
$pass = 0; $fail = 0;
function ok($cond, $label) {
    global $pass, $fail;
    if ($cond) { $pass++; fwrite(STDOUT, "  ✅ $label\n"); }
    else { $fail++; fwrite(STDOUT, "  ❌ $label\n"); }
}
function req($url, $post = null) {
    global $JAR;
    $ch = curl_init($url);
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $JAR, CURLOPT_COOKIEFILE => $JAR, CURLOPT_TIMEOUT => 30));
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
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
function loginAs($u, $p) {
    global $BASE, $JAR;
    @unlink($JAR);
    $lg = req($BASE . '/login.php');
    req($BASE . '/login.php', array('username' => $u, 'password' => $p, 'csrf_token' => csrfOf($lg['body'])));
    $home = req($BASE . '/dashboard.php');
    return mb_strpos($home['body'], 'logout') !== false || mb_strpos($home['body'], 'login.php') === false;
}

fwrite(STDOUT, "═══ ① قراءة البذور المرحَّلة (نماذج العمل — تنفيذ) ═══\n");
ok(loginAs('تنفيذ', '12345678'), 'دخول التنفيذ');
$pg = req($BASE . '/Portal/business_models.php');
ok($pg['code'] === 200, 'الشاشة 200');
$seed = $conn->query("SELECT code_model FROM scr_business_models WHERE is_seed=1 AND code_model IS NOT NULL LIMIT 1")->fetch_assoc();
ok($seed && mb_strpos($pg['body'], $seed['code_model']) !== false,
   'بذرة مرحَّلة تظهر (' . ($seed['code_model'] ?? '—') . ')');

fwrite(STDOUT, "═══ ② الكتابة تهبط في الجدول الأصلي ═══\n");
$tok = csrfOf($pg['body']);
$mark = 'W2-' . date('His');
req($BASE . '/Portal/business_models.php', array(
    'cmp03_action' => 'add', 'csrf_token' => $tok,
    'f0' => $mark, 'f1' => 'نموذج اختبار الموجة ٢ [تجريبي — ق-15]', 'f16' => 'مسودة',
));
$row = $conn->query("SELECT id, company_id, name_model FROM scr_business_models
                      WHERE code_model = '{$mark}' LIMIT 1")->fetch_assoc();
ok($row !== null, 'الصف في scr_business_models');
$leak = $conn->query("SELECT COUNT(*) c FROM cmp03_screen_rows WHERE canonical_file='business_models.php'")->fetch_assoc();
ok(intval($leak['c']) === 0, 'صفر تسرب للمخزن البيني');
$pg2 = req($BASE . '/Portal/business_models.php');
ok(mb_strpos($pg2['body'], $mark) !== false, 'الصف الجديد يظهر في الشاشة');

fwrite(STDOUT, "═══ ③ حارس BR-CEO-02 يقرأ من الجدول المحرر ═══\n");
$mk = 'RV-W2-' . date('His');
$conn->query("INSERT INTO scr_contract_review (company_id, no_note, contract_ref, blocks_approval_flag, status, status_label, is_seed, created_by_name)
              VALUES (4, '{$mk}', 'CT-W2-TEST', 'نعم — يحجب', 'مفتوحة', 'مفتوحة', 1, 'اختبار الموجة ٢')");
$blk = m00_review_block($conn, 4, 'CT-W2-TEST');
ok($blk === $mk, "الحارس أعاد رقم الملاحظة الحاجبة ({$mk})");
$conn->query("DELETE FROM scr_contract_review WHERE no_note = '{$mk}'");
$blk2 = m00_review_block($conn, 4, 'CT-W2-TEST');
ok($blk2 === null, 'وبعد إقفالها لا حجب');

@unlink($JAR);
fwrite(STDOUT, "\nالنتيجة: {$pass} نجاح · {$fail} فشل\n");
exit($fail > 0 ? 1 : 0);
