<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-17 — اختبار قبول: حارسُ الظهور بمستوى العنصر (USR-01 §4 · U1/U2/U3)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/h17_visibility_guard_test.php
 *
 * ما يُثبته — الأبعادُ الثلاثة وفشلُ كلٍّ بأثره المنصوص:
 *   ① الصفةُ والصلاحية: عنصرٌ داخليٌّ لمشرف مورد ⇒ **Hide Node** صامت (U1).
 *   ② العلاقةُ بالمعروض: شخصٌ خارج العلاقة ⇒ **deny 403 مسجَّلة** (U2).
 *   ③ مفتاحُ الموارد البشرية: إغلاقٌ موثَّق ⇒ **hide بمصدر القرار** (U3) —
 *      والافتراضُ المغلقُ للحساس **لا يحجب صاحبَ البيانات عن بياناته**.
 *   + «لا يُصيَّر عنصرٌ لم يُفحص»: filterElements يعيد المسموحَ فقط
 *     وdeny واحدٌ يوقف الصفحة.
 *
 * البذرُ معزول بعلامة H17T — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '4', 'company_id' => 4, 'name' => 'H17 guard test');

require_once dirname(__DIR__) . '/app/Services/Portal/VisibilityGuard.php';
require_once dirname(__DIR__) . '/app/Services/Portal/VisibilityPolicyService.php';

use App\Services\Portal\VisibilityGuard as VG;
use App\Services\Portal\VisibilityPolicyService as VPS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$MARK  = 'H17T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM visibility_keys WHERE reason LIKE '%{$MARK}%'");
    $conn->query("DELETE c FROM user_capacities c JOIN users u ON u.id = c.account_id
                   WHERE u.username LIKE '{$MARK}%'");
    $conn->query("DELETE FROM users WHERE username LIKE '{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-17 — الحارسُ ثلاثيُّ الأبعاد: فشلُ أيٍّ منها يمنع ══\n");

head('البذر — مديرٌ ومرؤوسُه وغريبٌ ومشرفُ مورد');

$mk = function ($sfx, $role, $parent = null) use ($conn, $CO, $MARK) {
    $p = $parent === null ? "''" : "'" . intval($parent) . "'"; // parent_id NOT NULL varchar
    $conn->query("INSERT INTO users (name, username, email, password, role, company_id,
                  parent_id, status, created_at)
                  VALUES ('{$sfx} {$MARK}', '{$MARK}-{$sfx}', '{$MARK}{$sfx}@t.t', 'x',
                          '{$role}', {$CO}, {$p}, 'active', NOW())");
    return intval($conn->insert_id);
};
$U_MGR = $mk('mgr', '5');
$U_SUB = $mk('sub', '5', $U_MGR);
$U_STR = $mk('str', '5');
$U_EXT = $mk('ext', '8');
foreach (array($U_MGR, $U_SUB, $U_STR) as $u) {
    $conn->query("INSERT INTO user_capacities (company_id, account_id, capacity_type, role,
                  scope_type, source_type, source_note, valid_from, state)
                  VALUES ({$CO}, {$u}, 'employee', '5', 'company', 'delegation',
                          'بذر {$MARK}', '2026-01-01', 'active')");
}
$conn->query("INSERT INTO user_capacities (company_id, account_id, capacity_type, role,
              scope_type, scope_id, source_type, source_note, valid_from, state)
              VALUES ({$CO}, {$U_EXT}, 'supplier_supervisor', '8', 'supplier', 7701,
                      'delegation', 'بذر {$MARK}', '2026-01-01', 'active')");
check($U_MGR && $U_SUB && $U_STR && $U_EXT,
      "مديرٌ #{$U_MGR} ومرؤوسُه #{$U_SUB} وغريبٌ #{$U_STR} ومشرفُ موردٍ #{$U_EXT}");

$VIEW_MGR = array('account_id' => $U_MGR, 'role' => '5', 'capacity_type' => 'employee');
$VIEW_EXT = array('account_id' => $U_EXT, 'role' => '8',
                  'capacity_type' => 'supplier_supervisor',
                  'scope_type' => 'supplier', 'scope_id' => 7701);

// ═══ ① البُعد الأول ═══
head('① الصفةُ والصلاحية — الخارجيُّ لا يرى الداخليَّ (U1) · Hide Node صامت');

$v = VG::check($conn, $gate, $CO, $VIEW_EXT, 'card.payroll', array('account_id' => $U_EXT));
check($v['decision'] === 'hide' && $v['dimension'] === 1,
      '★★ بطاقةُ الراتب لمشرف المورد ⇒ **Hide Node بالبُعد ①** — لا خطأَ يُعرض ولا عنصرَ يُصيَّر');

$v = VG::check($conn, $gate, $CO, $VIEW_EXT, 'card.units', array('account_id' => $U_EXT));
check($v['decision'] === 'allow',
      'ووحداتُه (من قائمة سماح الخارجي) تمرّ — القائمةُ سماحٌ لا منع');

// ═══ ② البُعد الثاني ═══
head('② العلاقةُ بالمعروض — خارجُها deny 403 مسجَّلة (U2)');

$v = VG::check($conn, $gate, $CO, $VIEW_MGR, 'card.units', array('account_id' => $U_SUB));
check($v['decision'] === 'allow',
      '★ المديرُ عن مرؤوسه المباشر (parent_id) ⇒ يمرّ بالبُعد ②');

$before = intval($conn->query("SELECT COUNT(*) n FROM activity_logs")->fetch_assoc()['n']);
$v = VG::check($conn, $gate, $CO,
    array('account_id' => $U_STR, 'role' => '5', 'capacity_type' => 'employee'),
    'card.units', array('account_id' => $U_SUB));
$after = intval($conn->query("SELECT COUNT(*) n FROM activity_logs")->fetch_assoc()['n']);
check($v['decision'] === 'deny' && $v['dimension'] === 2,
      '★★★ الغريبُ عن شخصٍ خارج علاقته ⇒ **deny بالبُعد ②** — «محاولةُ قراءةٍ بمعرّفٍ صريح 403»');
check($after > $before, '★ والمحاولةُ **مسجَّلةٌ في سجل الأمن** — U2 نصًّا');

$v = VG::check($conn, $gate, $CO,
    array('account_id' => 1, 'role' => '4', 'capacity_type' => 'employee'),
    'card.units', array('account_id' => $U_SUB));
check($v['decision'] === 'allow', 'والمواردُ البشرية (كلُّ الكيان) تمرّ بالبُعد ②');

// ═══ ③ البُعد الثالث ═══
head('③ مفتاحُ الموارد البشرية — إغلاقٌ موثَّقٌ يغلب (U3) والذاتُ ترى بياناتها');

// الحساسُ الافتراضيُّ المغلق: المديرُ لا يرى راتبَ مرؤوسه بلا منح
$v = VG::check($conn, $gate, $CO, $VIEW_MGR, 'card.payroll', array('account_id' => $U_SUB));
check($v['decision'] === 'hide' && $v['dimension'] === 3,
      '★★ راتبُ المرؤوس لمديره **بلا منح** ⇒ hide — «لا يرى رواتبَهم إلا بمنحٍ صريح» (§4-②)');

// والذاتُ ترى بياناتها رغم الافتراض المغلق
$v = VG::check($conn, $gate, $CO,
    array('account_id' => $U_SUB, 'role' => '5', 'capacity_type' => 'employee'),
    'card.payroll', array('account_id' => $U_SUB));
check($v['decision'] === 'allow',
      '★★ وصاحبُ الراتب يرى راتبَه — **الافتراضُ المغلق يحجب الغيرَ لا الذات** (§4-①)');

// U3: إغلاقٌ صريحٌ لفئةٍ يحجب حتى الذات
$r = VPS::setKey($conn, $gate, $CO, array(
    'element_code' => 'card.units', 'scope_type' => 'account', 'scope_id' => (string) $U_SUB,
    'mode' => 'closed', 'reason' => 'قرارُ إغلاقٍ ' . $MARK), 1);
$v = VG::check($conn, $gate, $CO,
    array('account_id' => $U_SUB, 'role' => '5', 'capacity_type' => 'employee'),
    'card.units', array('account_id' => $U_SUB));
check($r['ok'] && $v['decision'] === 'hide' && $v['dimension'] === 3
      && strpos($v['reason'], 'account') !== false,
      '★★★ إغلاقٌ **مضبوطٌ صراحةً** يحجب حتى صاحبَ البيانات — '
      . '«مغلقةٌ لهذا الحساب بقرار الموارد البشرية» (U3) والمصدرُ معلَن');

// ومنحٌ مؤقتٌ يفتح الحساسَ للمدير
$r = VPS::setKey($conn, $gate, $CO, array(
    'element_code' => 'card.payroll', 'scope_type' => 'account', 'scope_id' => (string) $U_MGR,
    'mode' => 'open', 'reason' => 'منحةُ ' . $MARK,
    'expires_at' => date('Y-m-d H:i:s', time() + 3600)), 1);
$v = VG::check($conn, $gate, $CO, $VIEW_MGR, 'card.payroll', array('account_id' => $U_SUB));
check($r['ok'] && $v['decision'] === 'allow',
      '★ وبمنحٍ مؤقتٍ بمدةٍ وسبب **يفتح** — المنعُ افتراضيٌّ حتى يُمنح');

// ═══ filterElements ═══
head('«لا يُصيَّر عنصرٌ لم يُفحص» — الفحصُ الجُمليّ');

$f = VG::filterElements($conn, $gate, $CO, $VIEW_EXT, array('account_id' => $U_EXT),
    array('card.units', 'card.payroll', 'card.tickets', 'card.documents'));
check($f['ok'] && $f['allowed'] === array('card.units', 'card.tickets'),
      '★ لمشرف المورد: من أربعة عناصرَ لا يُصيَّر إلا وحداتُه وبلاغاتُه — والباقي Hide صامت');

$f = VG::filterElements($conn, $gate, $CO,
    array('account_id' => $U_STR, 'role' => '5', 'capacity_type' => 'employee'),
    array('account_id' => $U_SUB), array('card.units', 'card.tickets'));
check(!$f['ok'] && $f['code'] === 403,
      '★★ وdeny واحدٌ (علاقةٌ مقطوعة) **يوقف الصفحةَ كلَّها 403** — لا تصييرَ جزئيًّا لبيانات الغير');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
