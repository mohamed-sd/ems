<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-15 — اختبار قبول: طبقةُ الصفات (USR-01 §2 · §9.1 · PLAN-01 §6.4)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/h15_capacity_test.php
 *
 * ما يُثبته:
 *   ① **الاشتقاقُ من القائم عاطل**: يغطي الحسابات النشطة وإعادتُه صفرُ صفٍّ
 *      جديد — والتجريبُ لا يكتب.
 *   ② **لا صفةَ بلا مصدرٍ ولا نطاقَ بلا معرّف**: CHECKاتُ البنية تحرس.
 *   ③ **الانتهاءُ تجميدٌ لا حذف** (U8): عقدٌ لم يعد نشطًا ⇒ الصفةُ تُجمَّد
 *      عند القراءة بسببها **ويبقى صفُّها**.
 *   ④ **التبديلُ بين صفات الحساب نفسِه حصرًا**: صفةُ غيرِه 403 · المجمَّدةُ
 *      409 · والدورُ الفعّالُ في الجلسة **دورُ الصفة**.
 *   ⑤ **استيعابُ H-20**: حسابُ موردٍ له صفاتٌ ولا صفةَ نشطةً بنطاق مورده ⇒
 *      `supplierOf` ترجع null (**fail-closed**) — وبصفةٍ مطابقةٍ يمرّ.
 *
 * البذرُ معزول بعلامة H15T — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '15', 'company_id' => 4, 'name' => 'H15 capacity test');

require_once dirname(__DIR__) . '/app/Services/Portal/CapacityService.php';
require_once dirname(__DIR__) . '/app/Services/Portal/SupplierPortalGuard.php';

use App\Services\Portal\CapacityService as CAP;
use App\Services\Portal\SupplierPortalGuard as SPG;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999151;
$MARK  = 'H15T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE c FROM user_capacities c JOIN users u ON u.id = c.account_id
                   WHERE u.username LIKE '{$MARK}%'");
    $conn->query("DELETE ec FROM employee_contracts ec JOIN employees e ON e.id = ec.employee_id
                   WHERE e.name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM users WHERE username LIKE '{$MARK}%'");
    $conn->query("DELETE FROM employees WHERE name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-15 — طبقةُ الصفات: شخصٌ × صفةٌ × نطاقٌ × مدةٌ × مصدر ══\n");

head('البذر — موظفٌ بعقدٍ نشطٍ وحسابُه · وحسابُ موردٍ خارجي');

$conn->query("INSERT INTO employees (company_id, name, employee_code, employee_type, created_at)
              VALUES ({$CO}, 'موظفُ {$MARK}', 'E-{$MARK}', 'permanent', NOW())");
$EMP = intval($conn->insert_id);
// pay_model_id مفتاحٌ أجنبيٌّ إلزامي — يُقرأ من القائم لا يُخترع؛
// وproject_id FK إلى `project` — فيُبذر مشروعٌ حقيقي
$PM = intval($conn->query("SELECT id FROM pay_models LIMIT 1")->fetch_assoc()['id'] ?? 0);
$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'ع', 'م', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO employee_contracts (company_id, employee_id, category, relation_type,
              pay_model_id, project_id, start_date, state, version, created_at)
              VALUES ({$CO}, {$EMP}, 'project', 'H15-test', {$PM}, {$PRJ}, '2026-01-01', 'active', 1, NOW())");
$EC = intval($conn->insert_id);
$conn->query("INSERT INTO users (name, username, email, password, role, company_id, employee_id, status, created_at)
              VALUES ('حسابُ {$MARK}', '{$MARK}-emp', '{$MARK}e@t.t', 'x', '5', {$CO}, {$EMP}, 'active', NOW())");
$U_EMP = intval($conn->insert_id);
$conn->query("INSERT INTO suppliers (company_id, name, phone, status, created_at)
              VALUES ({$CO}, 'موردُ {$MARK}', '0900000000', 'active', NOW())");
$SUP = intval($conn->insert_id);
$conn->query("INSERT INTO users (name, username, email, password, role, company_id, supplier_entity_id, status, created_at)
              VALUES ('موردُ {$MARK}', '{$MARK}-sup', '{$MARK}s@t.t', 'x', '8', {$CO}, {$SUP}, 'active', NOW())");
$U_SUP = intval($conn->insert_id);
check($EMP > 0 && $EC > 0 && $U_EMP > 0 && $U_SUP > 0 && $U_SUP !== $U_EMP && $SUP > 0,
      "موظفٌ #{$EMP} بعقدٍ نشطٍ #{$EC} وحسابُه #{$U_EMP} · وحسابُ موردٍ #{$U_SUP} (مورد #{$SUP})");

// ═══ ① الاشتقاق ═══
head('① الاشتقاقُ من القائم — عاطلٌ والتجريبُ لا يكتب');

$before = intval($conn->query("SELECT COUNT(*) n FROM user_capacities")->fetch_assoc()['n']);
$dry = CAP::derive($conn, $gate, $CO, $ACTOR, true);
$afterDry = intval($conn->query("SELECT COUNT(*) n FROM user_capacities")->fetch_assoc()['n']);
check($dry['ok'] && $dry['created'] > 0 && $afterDry === $before,
      '★ التجريبُ عدّ ' . $dry['created'] . ' صفةً مرشحةً **وصفرُ صفٍّ كُتب**');

$run1 = CAP::derive($conn, $gate, $CO, $ACTOR, false);
check($run1['created'] > 0, '★ الاشتقاقُ الفعلي أنشأ ' . $run1['created'] . ' صفةً');
$run2 = CAP::derive($conn, $gate, $CO, $ACTOR, false);
check($run2['created'] === 0 && $run2['skipped'] > 0,
      '★★ **الإعادةُ صفرُ صفٍّ جديد** (' . $run2['skipped'] . ' قائمةً تُخطّيت) — العطالةُ قاعدةٌ لا صدفة');

// الموظفُ المشروعي: صفتُه من فئة عقده ونطاقُه مشروعُه ومصدرُه العقد
$r = $conn->query("SELECT * FROM user_capacities WHERE account_id = {$U_EMP}")->fetch_assoc();
check($r && $r['capacity_type'] === 'project_employee' && $r['scope_type'] === 'project'
      && intval($r['scope_id']) === $PRJ && $r['source_type'] === 'contract'
      && intval($r['source_id']) === $EC && $r['role'] === '5',
      '★★ صفةُ الموظف اشتُقت من عقده: `project_employee` × مشروعه × عقد #' . $EC . ' × دور 5 — **لا من ظنّ**');

$r2 = $conn->query("SELECT * FROM user_capacities WHERE account_id = {$U_SUP}")->fetch_assoc();
check($r2 && $r2['capacity_type'] === 'supplier_supervisor' && $r2['scope_type'] === 'supplier'
      && intval($r2["scope_id"]) === $SUP && $r2['source_type'] === 'delegation'
      && (string)$r2['source_note'] !== '',
      '★ وصفةُ المورد الخارجي: `supplier_supervisor` × مورد حقيقي — تفويضٌ **بملاحظته المعلَنة** لا عقدٌ ملفَّق');

// ═══ ② CHECKات البنية ═══
head('② البنيةُ تحرس — لا نطاقَ بلا معرّفٍ ولا عقدَ بلا مرجعٍ ولا تجميدَ صامتًا');

$bad1 = $conn->query("INSERT INTO user_capacities (company_id, person_id, account_id, capacity_type,
    role, scope_type, scope_id, source_type, source_id, valid_from)
    VALUES ({$CO}, NULL, {$U_EMP}, 'auditor', '5', 'project', NULL, 'delegation', NULL, '2026-01-01')");
check($bad1 === false, '★ نطاقُ مشروعٍ بلا `scope_id` ⇒ **يرفضه CHECK**');

$bad2 = $conn->query("INSERT INTO user_capacities (company_id, person_id, account_id, capacity_type,
    role, scope_type, scope_id, source_type, source_id, valid_from)
    VALUES ({$CO}, NULL, {$U_EMP}, 'auditor', '5', 'company', NULL, 'contract', NULL, '2026-01-01')");
check($bad2 === false, '★ مصدرُ عقدٍ بلا `source_id` ⇒ **يرفضه CHECK** — لا صفةَ بلا مصدر');

$bad3 = $conn->query("INSERT INTO user_capacities (company_id, person_id, account_id, capacity_type,
    role, scope_type, scope_id, source_type, source_id, valid_from, valid_to)
    VALUES ({$CO}, NULL, {$U_EMP}, 'auditor', '5', 'company', NULL, 'delegation', NULL, '2026-06-01', '2026-01-01')");
check($bad3 === false, 'ونافذةٌ معكوسة ⇒ يرفضها CHECK');

$capId = intval($r['id']);
$bad4 = $conn->query("UPDATE user_capacities SET state='frozen', state_reason=NULL, state_at=NULL
                       WHERE id = {$capId}");
check($bad4 === false, '★ وتجميدٌ بلا سببٍ ووقتٍ ⇒ **يرفضه CHECK** — لا إسقاطَ صامتًا لصفة');

// ═══ ③ الانتهاءُ الآلي ═══
head('③ الانتهاءُ تجميدٌ لا حذف (U8) — المصدرُ يسقط فتسقط الصفة');

$conn->query("UPDATE employee_contracts SET state = 'terminated' WHERE id = {$EC}");
$caps = CAP::activeOf($conn, $gate, $U_EMP);
$mine = null; foreach ($caps as $c) { if (intval($c['id']) === $capId) { $mine = $c; } }
check($mine !== null && (string)$mine['state'] === 'frozen'
      && strpos((string)$mine['state_reason'], 'terminated') !== false,
      '★★ عقدُ المصدر انتهى ⇒ الصفةُ **جُمّدت عند القراءة بسببها**: '
      . mb_substr((string)$mine['state_reason'], 0, 60));
$still = intval($conn->query("SELECT COUNT(*) n FROM user_capacities WHERE id = {$capId}")->fetch_assoc()['n']);
check($still === 1, '★ **والصفُّ باقٍ للقراءة** — تجميدٌ لا حذف (U8)');

// ═══ ④ التبديل ═══
head('④ التبديلُ بين صفات الحساب نفسِه حصرًا — والدورُ الفعّالُ دورُ الصفة');

$sess = array('user' => array('id' => $U_SUP, 'role' => '8'));
$supCapId = intval($r2['id']);
$sw1 = CAP::switchTo($conn, $gate, $CO, $U_SUP, $supCapId, $sess, $U_SUP);
check($sw1['ok'] && $sess['active_capacity']['id'] === $supCapId
      && $sess['user']['role'] === '8',
      '★ التبديلُ إلى صفتي يقع — والجلسةُ تحمل الصفةَ الفعّالة ودورَها');

$sw2 = CAP::switchTo($conn, $gate, $CO, $U_EMP, $supCapId, $sess, $U_EMP);
check(!$sw2['ok'] && $sw2['code'] === 403,
      '★★ وصفةُ حسابٍ آخر ⇒ **403 مسجَّلة** — لا لبسَ صفةِ الغير');

$sw3 = CAP::switchTo($conn, $gate, $CO, $U_EMP, $capId, $sess, $U_EMP);
check(!$sw3['ok'] && $sw3['code'] === 409,
      '★ والمجمَّدةُ ⇒ **409** — «تُقرأ ولا تُلبَس»');

// ═══ ⑤ استيعاب H-20 ═══
head('⑤ استيعابُ H-20 — طبقةُ الصفات هي الحكمُ لحساب الموردين');

$supSession = array('id' => $U_SUP, 'role' => '8');
$sid = SPG::supplierOf($conn, $supSession);
check($sid === $SUP, '★ صفةٌ نشطةٌ بنطاق المورد ⇒ `supplierOf` = مورده (الحقنُ يمرّ)');

$conn->query("UPDATE user_capacities SET state='frozen',
              state_reason='تجميدُ اختبار الاستيعاب', state_at=NOW() WHERE id = {$supCapId}");
$sid2 = SPG::supplierOf($conn, $supSession);
check($sid2 === null,
      '★★★ الصفةُ جُمّدت ⇒ `supplierOf` = null (**fail-closed**) — '
      . '**طبقةُ الصفات صارت الحكمَ فوق الربط المباشر** (استيعابُ H-20)');

$conn->query("UPDATE user_capacities SET state='active', state_reason=NULL, state_at=NULL
              WHERE id = {$supCapId}");
$sid3 = SPG::supplierOf($conn, $supSession);
check($sid3 === $SUP, 'وإعادةُ التنشيط تعيد الحقن — الحارسُ القائمُ لم يُهدم');

// ═══ الخاتمة ═══
fwrite(STDOUT, "\n══════════════════════════════════════\n");
fwrite(STDOUT, "  النتيجة: {$PASS} نجاح · {$FAIL} فشل\n");
exit($FAIL > 0 ? 1 : 0);
