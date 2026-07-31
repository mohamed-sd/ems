<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * E-24 — اختبار قبول: آلةُ حالات سياسة الأجر (UX-06 §8.2 · §8.3-W4)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/pay_policy_state_test.php
 *
 * ما يُثبته:
 *   ① **المسودةُ لا تسعّر**: سياسةُ `draft` ⇒ `OperatorDue` «لا حكم» — وبعد
 *      التفعيل تحسب. (والوصلُ في موضعين: حكمُ الساعة كذلك)
 *   ② **التفعيلُ يلزمه سريانٌ ومعدلٌ وعملة** — 422 بكلٍّ منها ناقصًا.
 *   ③ **والتفعيلُ يُخلِف**: القديمةُ `superseded` **بخَلَفها مسمًّى** و
 *      `effective_to` = سريانُ الجديدة **− يوم**.
 *   ④ **ولا رجعية**: يومٌ قبل السريان يُحسب **بالقديمة**، وبعدَه بالجديدة.
 *   ⑤ **ولا تعديلَ على نافذة** (423) · **ولا سريانان متطابقان** (409).
 *   ⑥ **الإنهاءُ بسببه** (422 بلا سبب · و`CHECK` يرفض الكتابةَ المباشرة)
 *      · **ولا عودةَ من منتهية** (423).
 *   ⑦ **و`superseded` بلا خَلَفٍ يرفضها `CHECK`** — بنيويًّا لا بفحصٍ يُنسى.
 *   ⑧ **والكنسُ تصريحٌ بما وقع**: ما انقضى سريانُه يصير `expired`.
 *
 * البذرُ معزول: مشغّلٌ وسياساتُه في 2092 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '17', 'company_id' => 4, 'name' => 'E24 policy test');

require_once dirname(__DIR__) . '/app/Services/Payroll/PayPolicyStateMachine.php';
require_once dirname(__DIR__) . '/app/Services/Unit/OperatorDue.php';

use App\Services\Payroll\PayPolicyStateMachine as PPS;
use App\Services\Unit\OperatorDue as OD;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999241;
$MARK  = 'E24T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    // فكُّ مرجع الخَلَف قبل الحذف — و`ck_chp_superseded` يمنع تفريغَه وحدَه،
    // فتُعاد الحالةُ مسودةً معه في الكتابة نفسِها (القيدُ يحرس حتى في الكنس)
    $conn->query("UPDATE contract_hour_policies SET superseded_by=NULL, policy_state='draft',
                         state_note=NULL WHERE note LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contract_hour_policies WHERE note LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM employees WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ E-24 — آلةُ حالات سياسة الأجر ══\n");

head('البذر — مشغّلٌ وسياسةُ ساعةٍ بمعدل 10');

$conn->query("INSERT INTO employees (employee_type, company_id, name, phone, is_workforce, created_at)
              VALUES ('عامل', {$CO}, 'مشغّلُ {$MARK}', '0100000000', 1, NOW())");
$EMP = intval($conn->insert_id);

$mkPolicy = function ($from, $rate, $to = null, $basis = 'actual') use ($conn, $CO, $EMP, $MARK) {
    $toSql = $to === null ? 'NULL' : "'{$to}'";
    $conn->query("INSERT INTO contract_hour_policies
        (company_id, party_scope, operator_id, work_model, pay_basis, rate, currency,
         ruling, effective_from, effective_to, note, policy_state, created_at, updated_at)
        VALUES ({$CO}, 'operator', {$EMP}, 'hour', '{$basis}', {$rate}, 'SDG',
                'full', '{$from}', {$toSql}, 'سياسةُ {$MARK}', 'draft', NOW(), NOW())");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); return 0; }
    return intval($conn->insert_id);
};

$P1 = $mkPolicy('2092-01-01', 10);
check($EMP > 0 && $P1 > 0, "مشغّلٌ #{$EMP} وسياسةٌ #{$P1} **مسودةً** (الافتراضُ fail-closed)");

$ctx = function ($date) use ($EMP) {
    return array('employee_id' => $EMP, 'work_date' => $date, 'work_model' => 'hour',
                 'states' => array('actual_work' => 8.0), 'unit_type' => 'hour', 'unit_qty' => 8.0);
};

// ═══ ① المسودةُ لا تسعّر ═══
head('① **المسودةُ لا تسعّر شيئًا**');

$r = OD::compute($conn, $CO, $ctx('2092-06-01'));
check(!$r['ok'] && mb_strpos((string) $r['reason'], 'لا سياسةَ منطبقةً') !== false,
      '★★ سياسةٌ مسودةٌ ⇒ **«لا حكم»** — المحرّكُ لا يراها أصلًا');

// ═══ ② التفعيلُ بشروطه ═══
head('② **التفعيلُ يلزمه سريانٌ ومعدلٌ وعملة**');

$conn->query("UPDATE contract_hour_policies SET effective_from=NULL WHERE id={$P1}");
$a = PPS::activate($conn, $gate, $CO, $P1, $ACTOR);
check(!$a['ok'] && $a['code'] === 422 && mb_strpos($a['reason'], 'تاريخُ السريان إلزامي') !== false,
      'بلا تاريخِ سريانٍ ⇒ **422**');
$conn->query("UPDATE contract_hour_policies SET effective_from='2092-01-01', rate=NULL WHERE id={$P1}");
$a = PPS::activate($conn, $gate, $CO, $P1, $ACTOR);
check(!$a['ok'] && $a['code'] === 422 && mb_strpos($a['reason'], 'معدلُ الاستحقاق') !== false,
      'وبلا معدلٍ ⇒ **422** — «سياسةٌ بلا معدلٍ لا تسعّر شيئًا»');
$conn->query("UPDATE contract_hour_policies SET rate=10, currency=NULL WHERE id={$P1}");
$a = PPS::activate($conn, $gate, $CO, $P1, $ACTOR);
check(!$a['ok'] && $a['code'] === 422 && mb_strpos($a['reason'], 'العملةُ إلزامية') !== false,
      'وبلا عملةٍ ⇒ **422**');
$conn->query("UPDATE contract_hour_policies SET currency='SDG' WHERE id={$P1}");

$a = PPS::activate($conn, $gate, $CO, $P1, $ACTOR);
check($a['ok'] && count($a['superseded']) === 0, 'وفُعّلت — ولا سابقةَ تُخلَف');

$r = OD::compute($conn, $CO, $ctx('2092-06-01'));
check($r['ok'] && abs($r['amount'] - 80.0) < 0.005,
      '★★ وبعد التفعيل **80** = 8 ساعاتٍ × 10 — والحارسُ ليس زخرفة');

// ═══ ③④ الإخلافُ ولا رجعية ═══
head('③ **والتفعيلُ يُخلِف** ④ **ولا رجعية** (§8.3-W4)');

$P2 = $mkPolicy('2092-07-01', 20);
check($P2 > 0, "سياسةٌ ثانيةٌ #{$P2} بسريان 2092-07-01 ومعدل 20");

$a = PPS::activate($conn, $gate, $CO, $P2, $ACTOR);
check($a['ok'] && count($a['superseded']) === 1 && (int) $a['superseded'][0] === $P1,
      '★ التفعيلُ **أخلف السياسةَ الأولى** بمرجعها');

$q = $conn->query("SELECT policy_state, superseded_by, effective_to
                     FROM contract_hour_policies WHERE id={$P1}")->fetch_assoc();
check((string) $q['policy_state'] === 'superseded' && (int) $q['superseded_by'] === $P2
      && (string) $q['effective_to'] === '2092-06-30',
      '★★ والقديمةُ **`superseded` بخَلَفها** و`effective_to` = **2092-06-30** (سريانُ الجديدة − يوم)');

$r = OD::compute($conn, $CO, $ctx('2092-06-01'));
check($r['ok'] && abs($r['amount'] - 80.0) < 0.005,
      '★★ ويومٌ **قبل** السريان ما زال **80** — «أثرُ الماضي بحكم سياسته النافذة يومَها»');
$r = OD::compute($conn, $CO, $ctx('2092-07-15'));
check($r['ok'] && abs($r['amount'] - 160.0) < 0.005,
      '★★ ويومٌ **بعدَه** **160** = 8 × 20 — الجديدةُ تحكم ما بعدها وحدَه');

// ═══ ⑤ لا تعديلَ ولا سريانان متطابقان ═══
head('⑤ **ولا تعديلَ على نافذة** — ولا سريانان متطابقان');

$g = PPS::guardEdit($gate, $P2);
check(!$g['ok'] && $g['code'] === 423 && mb_strpos($g['reason'], 'لا تعديلَ رجعيًّا') !== false,
      '★ تعديلُ النافذة ⇒ **423** ونصُّه يسمّي البديل');
$g = PPS::guardEdit($gate, $P1);
check(!$g['ok'] && $g['code'] === 423, 'وكذلك المستبدَلة — الماضي لا يُحرَّر');

// «Active **بسريانٍ UQ**»: التطابقُ ممنوعٌ **بنيويًّا** بـ`uq_operator_policy`
// (شركة × مشغّل × نموذج × أساس × سريان) — فالإدراجُ الثاني لا يقع أصلًا،
// وحارسُ 409 في الخدمة حزامٌ فوق القيد لا بديلٌ عنه.
$before = intval($conn->query("SELECT COUNT(*) c FROM contract_hour_policies
                                WHERE note LIKE '%{$MARK}%'")->fetch_assoc()['c']);
$dup = $conn->query("INSERT INTO contract_hour_policies
    (company_id, party_scope, operator_id, work_model, pay_basis, rate, currency,
     ruling, effective_from, note, policy_state, created_at, updated_at)
    VALUES ({$CO}, 'operator', {$EMP}, 'hour', 'actual', 99, 'SDG',
            'full', '2092-07-01', 'سياسةُ {$MARK} مكررة', 'draft', NOW(), NOW())");
$after = intval($conn->query("SELECT COUNT(*) c FROM contract_hour_policies
                               WHERE note LIKE '%{$MARK}%'")->fetch_assoc()['c']);
check($dup === false && $after === $before,
      '★★ وسياسةٌ ثانيةٌ **بالسريان نفسِه** يرفضها `uq_operator_policy` — «بسريانٍ UQ» بنيويًّا');

$P3 = $mkPolicy('2092-07-01', 30, null, 'standby');
check($P3 > 0, "ومسودةٌ ثالثةٌ #{$P3} بأساسٍ آخر (استعداد) — المفتاحُ مختلفٌ فتُقبل");

// ═══ ⑥ الإنهاءُ بسببه ═══
head('⑥ **الإنهاءُ بسببه** — ولا عودةَ من منتهية');

$e = PPS::expire($conn, $gate, $CO, $P2, '   ', $ACTOR);
check(!$e['ok'] && $e['code'] === 422 && mb_strpos($e['reason'], 'سببُ الإنهاء إلزامي') !== false,
      'إنهاءٌ بلا سببٍ ⇒ **422**');

$conn->query("UPDATE contract_hour_policies SET policy_state='expired', state_note=NULL WHERE id={$P2}");
$q = $conn->query("SELECT policy_state FROM contract_hour_policies WHERE id={$P2}")->fetch_assoc();
check((string) $q['policy_state'] === 'active',
      '★★ وإنهاءٌ مكتوبٌ مباشرةً بلا سببٍ **يرفضه CHECK** — بنيويًّا لا بفحصٍ يُنسى');

$e = PPS::expire($conn, $gate, $CO, $P2, 'انتهى مشروعُ المشغّل', $ACTOR);
check($e['ok'], 'وبسببٍ مكتوبٍ تنتهي');
$q = $conn->query("SELECT policy_state, state_note FROM contract_hour_policies WHERE id={$P2}")->fetch_assoc();
check((string) $q['policy_state'] === 'expired' && (string) $q['state_note'] !== '',
      'والسببُ محفوظٌ في الصفّ');

$a = PPS::activate($conn, $gate, $CO, $P2, $ACTOR);
check(!$a['ok'] && $a['code'] === 423 && mb_strpos($a['reason'], 'نهائيةٌ بلا رجوع') !== false,
      '★ وتفعيلُ منتهيةٍ ⇒ **423** — «سياسةٌ جديدةٌ بسريانٍ جديد»');

// ═══ ⑦ CHECK الخَلَف ═══
head('⑦ **و`superseded` بلا خَلَفٍ يرفضها CHECK**');

$conn->query("UPDATE contract_hour_policies SET policy_state='superseded', superseded_by=NULL WHERE id={$P3}");
$q = $conn->query("SELECT policy_state FROM contract_hour_policies WHERE id={$P3}")->fetch_assoc();
check((string) $q['policy_state'] === 'draft',
      '★★ استبدالٌ بلا خَلَفٍ مسمًّى **يرفضه CHECK** — والصفُّ بقي مسودةً');

// ═══ ⑧ الكنس ═══
head('⑧ **والكنسُ تصريحٌ بما وقع**');

$P4 = $mkPolicy('2020-01-01', 15, '2020-03-31', 'attendance');
$conn->query("UPDATE contract_hour_policies SET policy_state='active' WHERE id={$P4}");
$n = PPS::sweepExpired($gate);
$q = $conn->query("SELECT policy_state, state_note FROM contract_hour_policies WHERE id={$P4}")->fetch_assoc();
check($n >= 1 && (string) $q['policy_state'] === 'expired'
      && mb_strpos((string) $q['state_note'], 'انقضى سريانُها') !== false,
      '★ ما انقضى سريانُه صار **منتهيًا بسببه المكتوب** — لا قرارٌ جديد');

// ═══ العزل ═══
head('العزلُ محفوظ');
$_SESSION['user']['company_id'] = 1;
$otherGate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::fromSession());
$leak = $otherGate->selectOne('contract_hour_policies', array('where' => array('id' => $P1)));
check($leak === null, '★ سياسةُ شركةٍ لا تُقرأ من نطاقٍ آخر — صفرُ تسريب');
$_SESSION['user']['company_id'] = $CO;

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
