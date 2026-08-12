<?php
/**
 * tests/pay_policy_state_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لآلة حالات سياسة الأجر (E-24 · UX-06 §8.2) من الشاشة الحقيقية
 * `Finance/operator_pay_policies_fin.php` بالمدير المالي (17):
 *   ① الإضافةُ من الشاشة **تحفظ مسودةً** — والمسودةُ لا تسعّر شيئًا
 *   ② والشاشةُ تقول ذلك نصًّا وتعرض زرَّ «فعّل»
 *   ③ والتفعيلُ من الشاشة يجعلها نافذةً — والمحرّكُ يراها
 *   ④ وسياسةٌ أحدثُ تُخلِف السابقةَ **بخَلَفها مسمًّى** في الشاشة نفسِها
 *   ⑤ والإنهاءُ من الشاشة **يلزمه سببٌ** ويُسجَّل
 * ثم يكنس كلَّ ما بذر.
 *
 * التشغيل: php tests/pay_policy_state_http_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

$BASE = 'http://localhost/ems';
$TMP  = sys_get_temp_dir();
$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

function pp_req($url, $jar, $post = null) {
    /* الرمزُ المركزيُّ: الحارسُ يقبله في الحقولِ أو الترويسة، والمسبارُ
       يبني الحقولَ يدويًّا فلا يحمله ⇒ 403 صامتٌ يُقرأ فشلًا في المنتج. */
    if ($post !== null) {
        require_once __DIR__ . '/_http_flash.php';
        $post = ems_http_with_csrf($post, $url, $jar);
    }
    $GLOBALS['__ems_last_url'] = $url;   // لحلِّ Location النسبيّ
    $GLOBALS['__ems_last_jar'] = $jar;   // الجرَّةُ وسيطٌ صريحٌ لا حالةٌ عامّة
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
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, 0, $hs), substr($raw, $hs));
}
function pp_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $h, $b) = pp_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return pp_req($BASE . '/login.php', $jar, array('username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : ''));
}
/* ══ **الرسالةُ وميضُ جلسةٍ لا مُعامَلُ عنوان.** `ems_gov_flash_redirect`
     (`includes/permissions_helper.php:139`) يخزّن النصَّ في
     `$_SESSION['ems_flash_gov']` ويوجّه **بعنوانٍ مجرَّد** — فقراءةُ `?msg=`
     تعود فارغةً **دائمًا** فيُقرأ صمتُها فشلًا، والمنتجُ نفَّذ الفعلَ وكتب
     رسالتَه حيث ينبغي. ويبقى المُعامَلُ مقروءًا **أوّلًا** لأن شاشاتٍ أخرى
     (المشتريات · الفتراتُ الماليةُ · تسوياتُ الموردين) تضعها فيه فعلًا. */
function pp_msg($headers) {
    require_once __DIR__ . '/_http_flash.php';
    $__dir = isset($GLOBALS['__ems_last_url'])
        ? preg_replace('~/[^/]*(\?.*)?$~', '', (string) $GLOBALS['__ems_last_url'])
        : '';
    return ems_flash_or_msg($headers, $__dir, function ($u) {
        $r = pp_req($u, $GLOBALS['__ems_last_jar'], null);
        return is_array($r) ? (string) $r[count($r) - 1] : (string) $r;
    });
}

require_once dirname(__DIR__) . '/includes/env.php';
$db = new mysqli(ems_env('DB_HOST'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'));
if ($db->connect_error) { fwrite(STDERR, "DB: {$db->connect_error}\n"); exit(1); }
$db->set_charset('utf8mb4');

$CO   = 4;
$MARK = 'E24H' . getmypid();

$teardown = function () use ($db, $MARK) {
    // فكُّ مرجع الخَلَف قبل الحذف — و`ck_chp_superseded` يمنع تفريغَه وحدَه،
    // فتُعاد الحالةُ مسودةً معه في الكتابة نفسِها (القيدُ يحرس حتى في الكنس)
    $db->query("UPDATE contract_hour_policies SET superseded_by=NULL, policy_state='draft',
                       state_note=NULL WHERE note LIKE '%{$MARK}%'");
    $db->query("DELETE FROM contract_hour_policies WHERE note LIKE '%{$MARK}%'");
    $db->query("DELETE FROM employees WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ برهانُ HTTP — آلةُ حالات سياسة الأجر (E-24) ══\n");

head('البذر — مشغّلٌ في سجل الدوام (مصدرُ قائمة الشاشة)');
$db->query("INSERT INTO employees (employee_type, company_id, name, phone, is_workforce, created_at)
            VALUES ('عامل', {$CO}, 'مشغّلُ {$MARK}', '0100000000', 1, NOW())");
$EMP = intval($db->insert_id);
check($EMP > 0, "مشغّلٌ #{$EMP}");

$jar = $TMP . "/pp_{$MARK}.txt";
pp_login('مديرمالي', $jar);

// CSRF مركزيٌّ **مُنفَّذٌ على هذا المسار**: رمزُ الصفحة يُقرأ من الشاشة نفسِها
// (وهو لكل جلسةٍ لا لكل نموذج) — وبلا ذلك يُرفض كلُّ POST صامتًا بلا رسالة.
list($c, $h, $b) = pp_req($BASE . '/Finance/operator_pay_policies_fin.php', $jar);
preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $tk);
$TOK = isset($tk[1]) ? $tk[1] : '';
check($TOK !== '', 'رمزُ CSRF مقروءٌ من الشاشة');

head('① الإضافةُ من الشاشة **تحفظ مسودةً**');
list($c, $h, $b) = pp_req($BASE . '/Finance/operator_pay_policies_fin.php', $jar, array(
    'add_policy' => 1, 'employee_id' => $EMP, 'work_model' => 'hour', 'basis' => 'actual',
    'rate' => '12', 'currency' => 'SDG', 'effective_from' => '2091-01-01',
    'note' => 'سياسةُ ' . $MARK . ' أولى', 'csrf_token' => $TOK));
$msg = pp_msg($h);
check(mb_strpos($msg, 'مسودة') !== false, 'رسالةُ الحفظ تقول مسودةً: ' . $msg);
$p1 = $db->query("SELECT id, policy_state FROM contract_hour_policies
                   WHERE note LIKE '%{$MARK}% أولى'")->fetch_assoc();
check($p1 && (string) $p1['policy_state'] === 'draft',
      '★★ والصفُّ المكتوب **`draft`** — لا نافذًا في لحظة الحفظ');
$P1 = intval($p1['id']);

head('② والشاشةُ تعلن ذلك وتعرض زرَّ التفعيل');
list($c, $h, $b) = pp_req($BASE . '/Finance/operator_pay_policies_fin.php', $jar);
check($c === 200 && mb_strpos($b, 'لا تسعّر شيئًا حتى تُفعَّل') !== false,
      '★ الشاشةُ تقولها نصًّا: «لا تسعّر شيئًا حتى تُفعَّل»');
check(mb_strpos($b, 'activate_policy=' . $P1) !== false, 'وزرُّ «فعّل» حاضرٌ لهذه المسودة');

head('③ والتفعيلُ من الشاشة يجعلها نافذة');
list($c, $h, $b) = pp_req($BASE . '/Finance/operator_pay_policies_fin.php?activate_policy=' . $P1, $jar);
check(mb_strpos(pp_msg($h), 'فُعّلت') !== false, 'رسالةُ التفعيل: ' . pp_msg($h));
$q = $db->query("SELECT policy_state, approved_at FROM contract_hour_policies WHERE id={$P1}")->fetch_assoc();
check((string) $q['policy_state'] === 'active' && $q['approved_at'] !== null,
      '★ صارت **نافذةً** ومعتمدةً بوقتها — والاعتمادُ مع التفعيل لا مع الحفظ');

head('④ وسياسةٌ أحدثُ **تُخلِف** السابقة');
pp_req($BASE . '/Finance/operator_pay_policies_fin.php', $jar, array(
    'add_policy' => 1, 'employee_id' => $EMP, 'work_model' => 'hour', 'basis' => 'actual',
    'rate' => '25', 'currency' => 'SDG', 'effective_from' => '2091-07-01',
    'note' => 'سياسةُ ' . $MARK . ' ثانية', 'csrf_token' => $TOK));
$p2 = $db->query("SELECT id FROM contract_hour_policies
                   WHERE note LIKE '%{$MARK}% ثانية'")->fetch_assoc();
$P2 = intval($p2['id']);
list($c, $h, $b) = pp_req($BASE . '/Finance/operator_pay_policies_fin.php?activate_policy=' . $P2, $jar);
check(mb_strpos(pp_msg($h), 'أُخلفت 1 سياسةً') !== false, 'رسالةُ الإخلاف: ' . pp_msg($h));
$q = $db->query("SELECT policy_state, superseded_by, effective_to
                   FROM contract_hour_policies WHERE id={$P1}")->fetch_assoc();
check((string) $q['policy_state'] === 'superseded' && (int) $q['superseded_by'] === $P2
      && (string) $q['effective_to'] === '2091-06-30',
      '★★ والأولى **مستبدَلةٌ بخَلَفها** ومغلقةٌ في **2091-06-30** — «أثرُ ما قبل السريان بالقديمة»');

list($c, $h, $b) = pp_req($BASE . '/Finance/operator_pay_policies_fin.php', $jar);
check(mb_strpos($b, 'مستبدَلة') !== false && mb_strpos($b, '← #' . $P2) !== false,
      '★ والشاشةُ تُري «مستبدَلة ← #' . $P2 . '» — الخَلَفُ مسمًّى لا مضمَر');

head('⑤ والإنهاءُ يلزمه سببٌ ويُسجَّل');
list($c, $h, $b) = pp_req($BASE . '/Finance/operator_pay_policies_fin.php', $jar, array(
    'expire_policy' => 1, 'policy_id' => $P2, 'expire_reason' => '', 'csrf_token' => $TOK));
check(mb_strpos(pp_msg($h), '422') !== false, 'بلا سببٍ ⇒ 422 من الخادم: ' . pp_msg($h));
list($c, $h, $b) = pp_req($BASE . '/Finance/operator_pay_policies_fin.php', $jar, array(
    'expire_policy' => 1, 'policy_id' => $P2, 'expire_reason' => 'انتهى المشروع ' . $MARK,
    'csrf_token' => $TOK));
check(mb_strpos(pp_msg($h), 'أُنهيت') !== false, 'وبسببٍ تُنهى: ' . pp_msg($h));
$q = $db->query("SELECT policy_state, state_note FROM contract_hour_policies WHERE id={$P2}")->fetch_assoc();
check((string) $q['policy_state'] === 'expired'
      && mb_strpos((string) $q['state_note'], $MARK) !== false,
      '★ والسببُ محفوظٌ في الصفّ — لا محو');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
