<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0004 · الموجة ⑧ — حزام الحراس: S8·S9·S11·S18·S22·S23·S26 (SEC-19→22)
 * التشغيل: php tests/sec_guards_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Security/SelfGrantGuard.php';
require_once dirname(__DIR__) . '/app/Services/Security/SegregationOfDutiesGuard.php';
require_once dirname(__DIR__) . '/app/Services/Security/SensitiveFieldGuard.php';
require_once dirname(__DIR__) . '/app/Services/Security/BreakGlassService.php';
require_once dirname(__DIR__) . '/app/Core/AuthorityGuard.php';

use App\Services\Security\SelfGrantGuard as SGG;
use App\Services\Security\SegregationOfDutiesGuard as SOD;
use App\Services\Security\SensitiveFieldGuard as SFG;
use App\Services\Security\BreakGlassService as BG;
use App\Core\AuthorityGuard;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'SG8T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE FROM permission_exceptions WHERE reason LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM effective_permissions WHERE source_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM permission_audit_events WHERE reason LIKE '%{$MARK}%'");
    $conn->query("DELETE w FROM ticket_workstreams w JOIN tickets t ON t.id=w.tk_id WHERE t.reporting_person = 'النظام — كسر زجاج' AND t.complaint LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM tickets WHERE reporting_person = 'النظام — كسر زجاج' AND complaint LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM sensitive_read_log WHERE context LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM guard_denials WHERE attempted_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM approval_signatures WHERE document_type = 'sg8_test'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0004 موجة ⑧ — الحراس ══\n");

head('S9 — المنح الذاتي 403 ولو مدير الصلاحيات');
$r = SGG::checkGrant($conn, 777, 777, $CO, $MARK . ':self');
check(!$r['ok'] && $r['code'] === 403, 'منح النفس → 403 بنيويًّا');
$d = $conn->query("SELECT COUNT(*) c FROM guard_denials WHERE guard_code='self.grant' AND attempted_ref='{$MARK}:self'")->fetch_assoc();
check(intval($d['c']) === 1, 'والرفض مسجَّل');
$r = SGG::checkGrant($conn, 777, 778, $CO, $MARK);
check($r['ok'], 'ومنح غيره يمر لحارس التالي');

head('S8 — اعتماد الذات 403 (AuthorityGuard القائم)');
$r = AuthorityGuard::sign($conn, array('document_type' => 'sg8_test', 'document_id' => 5,
    'person_id' => 888, 'company_id' => $CO, 'created_by_person_id' => 888));
check(!$r['ok'] && $r['code'] === 403, 'لا يعتمد المرء ما أنشأه — 403');

head('S18 — فصل الواجبات 409 عند الحساب لا بعده');
// شخص يملك طرف A (إنشاء مورد + تعديل بنكي) — والمقترح يضيف طرف B (اعتماد دفع)
$conn->query("INSERT INTO effective_permissions (company_id, person_id, permission_code, scope_rule, source_kind, source_ref)
              VALUES ({$CO}, 9501, 'supplier.create', 'company:4', 'family', '{$MARK}'),
                     ({$CO}, 9501, 'supplier.bank.update', 'company:4', 'family', '{$MARK}')");
$r = SOD::check($conn, 9501, $CO, array('supplier.payment.approve'));
check(!$r['ok'] && $r['code'] === 409, 'اقتراح يجمع طرفي التعارض → 409 ولا يُطبَّق');
check(count($r['conflicts']) === 1 && mb_strpos($r['reason'], 'مورد') !== false, 'والتعارض معروض باسمه');
$r = SOD::check($conn, 9501, $CO, array('supplier.payment.approve'), true);
check($r['ok'] && count($r['conflicts']) >= 0 && mb_strpos($r['reason'], 'رقابة') !== false,
    'وبالرقابة التعويضية المعلنة يُسمح استثناءً — ولا يُمنح صامتًا');
$r = SOD::check($conn, 9501, $CO, array('mnt.order.view'));
check($r['ok'], 'واقتراح بريء يمر');
// تعارض بلا ضابط تعويضي (sod_self_privilege) لا يُستثنى حتى بالرقابة
$conn->query("INSERT INTO effective_permissions (company_id, person_id, permission_code, scope_rule, source_kind, source_ref)
              VALUES ({$CO}, 9502, 'permission.create', 'company:4', 'family', '{$MARK}'),
                     ({$CO}, 9502, 'permission.approve', 'company:4', 'family', '{$MARK}')");
$r = SOD::check($conn, 9502, $CO, array('permission.apply'), true);
check(!$r['ok'], 'تصعيد السلطة الذاتي لا يُستثنى ولو برقابة (ضابطه NULL)');

head('S11 — الحقل الحساس لا يُجلب أصلًا');
$row = array('name' => 'موظف تجربة', 'salary' => '5000', 'bank' => 'SD123456789012');
$map = array('salary' => 'employees.salary', 'bank' => 'employees.bank_account');
$filtered = SFG::filterRow($conn, $row, $map, 9503, '13', $CO, 'employee:' . $MARK, 'test');
check(!isset($filtered['salary']) && !isset($filtered['bank']) && isset($filtered['name']),
    'دور الصيانة: الراتب والبنك لا يُعادان أصلًا — لا معطَّلين ولا مخفيَّين');
$filtered = SFG::filterRow($conn, $row, $map, 9504, '4', $CO, 'employee:' . $MARK, 'test');
check(isset($filtered['salary']), 'الموارد البشرية ترى الراتب (السياسة)');
check(isset($filtered['bank']) && mb_strpos($filtered['bank'], '•') !== false && mb_substr($filtered['bank'], -4) === '9012',
    'والبنك جزئيًّا: آخر أربعة أرقام (§10⑦)');
$lg = $conn->query("SELECT COUNT(*) c FROM sensitive_read_log WHERE context LIKE '%employee:{$MARK}%'")->fetch_assoc();
check(intval($lg['c']) >= 2, 'وكل قراءة مسجَّلة في سجل الاطلاع (' . $lg['c'] . ')');

head('S26 — المنح الحساس يفتح المجال بسجل قراءة بمرجع المنح');
$conn->query("INSERT INTO sensitive_access_grants (company_id, person_id, domain, permission_code, reason, granted_from)
              VALUES ({$CO}, 9505, 'payroll', 'payroll.read', 'منح {$MARK}', CURDATE())");
$GR = intval($conn->insert_id);
$filtered = SFG::filterRow($conn, $row, array('salary' => 'employees.salary'), 9505, '13', $CO, 'employee:' . $MARK, 'test');
check(isset($filtered['salary']), 'صاحب المنح الحساس يقرأ الراتب — دائم ما دامت الوظيفة');
$lg = $conn->query("SELECT grant_ref FROM sensitive_read_log WHERE person_id=9505 AND context LIKE '%employee:{$MARK}%' ORDER BY read_id DESC LIMIT 1")->fetch_assoc();
check($lg && $lg['grant_ref'] === 'GR-' . $GR, 'والقراءة بمرجع منحها (GR-' . $GR . ')');
$conn->query("DELETE FROM sensitive_access_grants WHERE gr_id = {$GR}");

head('S22 — كسر الزجاج لا يتجاوز never');
$decider = intval($conn->query("SELECT id FROM users WHERE role='1' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$r = BG::grant($conn, array('company_id' => $CO, 'person_id' => 9506, 'permission_code' => 'audit.tamper.edit',
    'scope_rule' => 'company:4', 'reason' => 'محاولة ' . $MARK, 'granted_by' => $decider));
check(!$r['ok'] && $r['code'] === 403 && mb_strpos($r['reason'], 'never') !== false,
    'تجاوز سجل التدقيق → 403: never تُقرأ ولا تُستثنى');
$r = BG::grant($conn, array('company_id' => $CO, 'person_id' => $decider, 'permission_code' => 'payroll.read',
    'scope_rule' => 'company:4', 'reason' => 'ذاتي ' . $MARK, 'granted_by' => $decider));
check(!$r['ok'] && $r['code'] === 403, 'ولا يمنح نفسه كسر زجاج');

head('كسر زجاج مشروع — بلاغ حوكمة آلي');
$r = BG::grant($conn, array('company_id' => $CO, 'person_id' => 9506, 'permission_code' => 'payroll.read',
    'scope_rule' => 'company:4', 'reason' => 'طارئ ' . $MARK, 'granted_by' => $decider, 'hours' => 6));
check($r['ok'] && $r['ex_id'] > 0, "مُنح (#{$r['ex_id']}) لست ساعات");
check($r['ticket_id'] > 0, "وفُتح بلاغ حوكمة آليًّا (#{$r['ticket_id']})");
$t = $conn->query("SELECT priority, stage FROM tickets WHERE id=" . intval($r['ticket_id']))->fetch_assoc();
check($t && $t['priority'] === 'critical', 'البلاغ حرج');
$EX = $r['ex_id'];

head('S23 — بلا مراجعة خلال 48 ساعة يسقط ويُصعَّد');
$conn->query("UPDATE permission_exceptions SET valid_from = DATE_SUB(NOW(), INTERVAL 50 HOUR),
              valid_to = DATE_SUB(NOW(), INTERVAL 26 HOUR) WHERE ex_id = {$EX}");
$n = BG::sweepUnreviewed($conn, $CO);
check($n >= 1, "المسح أسقط غير المراجَع ({$n})");
$st = $conn->query("SELECT state FROM permission_exceptions WHERE ex_id = {$EX}")->fetch_assoc();
check($st['state'] === 'revoked', 'الحالة: revoked وصُعِّد بتنبيه');
// والمراجَع لا يسقط
$r2 = BG::grant($conn, array('company_id' => $CO, 'person_id' => 9507, 'permission_code' => 'payroll.read',
    'scope_rule' => 'company:4', 'reason' => 'طارئ2 ' . $MARK, 'granted_by' => $decider, 'hours' => 6));
BG::review($conn, $r2['ex_id'], $decider, 'مبرَّر');
$conn->query("UPDATE permission_exceptions SET valid_from = DATE_SUB(NOW(), INTERVAL 50 HOUR) WHERE ex_id = " . intval($r2['ex_id']));
$n = BG::sweepUnreviewed($conn, $CO);
$st = $conn->query("SELECT state FROM permission_exceptions WHERE ex_id = " . intval($r2['ex_id']))->fetch_assoc();
check($st['state'] === 'active', 'والمراجَع خلال المهلة لا يسقط بالمسح');

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
