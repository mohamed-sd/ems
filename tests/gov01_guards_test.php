<?php
/**
 * tests/gov01_guards_test.php — GOV-01 التصنيف والاستثناءات (البوابة ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * حالات GOV-01 §10.1 المنفَّذة: X1 الاستثناء بنطاقه ومدته والعبور مسجَّل ·
 * X2 المطلق لا يُستثنى · X3 المحظور قانونًا يُحال · X4 استقلال الموافقين ·
 * X5 اعتماد الذات · X6 الانتهاء الآلي والمنع يعود · X7 الإعفاء بلا حذف ·
 * X8 سجل المنع · X11 تغيير الحالة بمدير الحركة أولًا · X13 المقيَّد يُعكس 423 ·
 * X14 ترتيب السلّم 409 · X15 الطوارئ و48 ساعة · «لا حماية بلا صنف» fail-closed.
 * التشغيل: php tests/gov01_guards_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/app/Services/Governance/GuardResolver.php';
require_once dirname(__DIR__) . '/app/Services/Governance/ExceptionService.php';
require_once dirname(__DIR__) . '/app/Services/Governance/UnitStateChangeService.php';

use App\Services\Governance\GuardResolver as GR;
use App\Services\Governance\ExceptionService as ES;
use App\Services\Governance\UnitStateChangeService as USC;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn) {
    $conn->query("DELETE u FROM exception_usages u JOIN exception_requests r ON r.req_id=u.req_id WHERE r.reason LIKE '%GOV1T%'");
    $conn->query("DELETE a FROM exception_approvals a JOIN exception_requests r ON r.req_id=a.req_id WHERE r.reason LIKE '%GOV1T%'");
    $conn->query("DELETE FROM exception_requests WHERE reason LIKE '%GOV1T%'");
    $conn->query("DELETE a FROM change_approvals a JOIN unit_state_changes c ON c.chg_id=a.chg_id WHERE c.reason LIKE '%GOV1T%'");
    $conn->query("DELETE FROM unit_state_changes WHERE reason LIKE '%GOV1T%'");
    $conn->query("DELETE FROM guard_denials WHERE attempted_ref LIKE 'GOV1T%'");
    $conn->query("DELETE FROM waivers_reversals WHERE reason LIKE '%GOV1T%'");
    $conn->query("DELETE FROM approval_signatures WHERE document_type IN ('exception_request','unit_state_change') AND company_id=4");
};
register_shutdown_function($teardown);
$teardown();

head('التصنيف — لا حماية بلا صنف');
$q = $conn->query("SELECT COUNT(*) c FROM guard_policies WHERE guard_class IS NOT NULL")->fetch_assoc();
check((int) $q['c'] >= 9, 'الحمايات السبع + بوابة الاستحقاق مصنَّفة (' . $q['c'] . ')');
$r = GR::resolve($conn, $CO, 'guard.unknown', 1, array('ref' => 'GOV1T-u'));
check($r['decision'] === 'deny', 'حماية بلا صنف ⇒ deny (fail-closed) وسطر منع');

head('X2·X3 — المطلق والمحظور');
$r = ES::create($conn, $CO, array('guard_code' => 'tenant.isolation', 'requester_person_id' => 4, 'reason' => 'GOV1T تجربة', 'scope_type' => 'person', 'scope_id' => '1', 'valid_from' => date('Y-m-d'), 'valid_to' => date('Y-m-d', strtotime('+7 days'))));
check(!$r['ok'] && $r['code'] === 422 && (mb_strpos($r['reason'], 'مطلق') !== false || mb_strpos($r['reason'], 'محظور') !== false), 'X2/X3: عزل الكيانات لا يُستثنى مهما بلغت الموافقات: ' . $r['reason']);
$r = GR::resolve($conn, $CO, 'tenant.isolation', 4, array('ref' => 'GOV1T-abs'));
check($r['decision'] === 'deny', 'والحارس المطلق يمنع دائمًا');

head('X1·X4·X5 — دورة الاستثناء (وثيقة سائق منتهية)');
$r = ES::create($conn, $CO, array(
    'guard_code' => 'driver.doc.expiry', 'requester_person_id' => 4,
    'reason' => 'GOV1T تشغيل اضطراري حتى وصول الرخصة المجددة',
    'scope_type' => 'person', 'scope_id' => '412',
    'valid_from' => date('Y-m-d'), 'valid_to' => date('Y-m-d', strtotime('+14 days')),
    'documents' => array('RCPT-2291'), 'expected_impact' => 'operational'));
check($r['ok'] && $r['risk_level'] === 'high', 'طلب بدرجة high محسوبة (لا تُختار) وموافقيها: ' . implode('·', $r['required_approvals']));
$REQ = $r['req_id'];
$r = ES::create($conn, $CO, array('guard_code' => 'driver.doc.expiry', 'requester_person_id' => 4, 'reason' => 'GOV1T بلا نهاية', 'scope_type' => 'person', 'scope_id' => '1', 'valid_from' => date('Y-m-d'), 'valid_to' => ''));
check(!$r['ok'] && $r['code'] === 422, 'مدة مفتوحة ⇒ 422 — لا استثناء مفتوح المدة');

$r = ES::approve($conn, $CO, $REQ, 4, 'movement_manager');
check(!$r['ok'] && $r['code'] === 403, 'X5: الطالب يوافق على طلبه ⇒ 403 بنيويًّا');
$r = ES::approve($conn, $CO, $REQ, 6, 'movement_manager');
check($r['ok'] && $r['state'] === 'Pending', 'الموافقة الأولى (مدير الحركة) تقع');
$r = ES::approve($conn, $CO, $REQ, 5, 'movement_manager');
check(!$r['ok'] && $r['code'] === 409, 'X4: دور مكرر ⇒ 409 — استقلال الموافقين شرط صحتها');
$r = ES::approve($conn, $CO, $REQ, 6, 'dept_manager');
check(!$r['ok'] && $r['code'] === 409, 'X4-ب: الشخص نفسه بدورين ⇒ 409');
ES::approve($conn, $CO, $REQ, 5, 'dept_manager');
ES::approve($conn, $CO, $REQ, 7, 'finance');
$r = ES::approve($conn, $CO, $REQ, 8, 'general_management');
check($r['ok'] && $r['state'] === 'Active', 'X1: باكتمال الأربع (درجة high) صار الاستثناء نافذًا');

$r = GR::resolve($conn, $CO, 'driver.doc.expiry', 9, array('scope_type' => 'person', 'scope_id' => '412', 'ref' => 'GOV1T-unit-90'));
check($r['decision'] === 'allow_by_exception', 'الحارس يسمح **لهذا السائق وحده** بالاستثناء النافذ');
$r = GR::resolve($conn, $CO, 'driver.doc.expiry', 9, array('scope_type' => 'person', 'scope_id' => '413', 'ref' => 'GOV1T-unit-91'));
check($r['decision'] === 'deny', 'وسائق آخر خارج النطاق يُمنع — لا استثناء عام');
$q = $conn->query("SELECT usage_count FROM exception_requests WHERE req_id={$REQ}")->fetch_assoc();
check((int) $q['usage_count'] === 1, 'كل عبور مسجَّل بعدّاده (X1)');

head('X6 — الانتهاء الآلي');
$conn->query("UPDATE exception_requests SET valid_to = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE req_id={$REQ}");
$n = ES::expireSweep($conn);
check($n >= 1, 'المسح الدوري أنهى المنقضي آليًّا');
$r = GR::resolve($conn, $CO, 'driver.doc.expiry', 9, array('scope_type' => 'person', 'scope_id' => '412', 'ref' => 'GOV1T-after'));
check($r['decision'] === 'deny', 'X6: وأي عبور بعده يُرفض — المنع يعود بلا تدخل');

head('X7·X8 — لا حذف وسجل المنع');
$conn->query("INSERT INTO waivers_reversals (company_id, action, source_type, source_id, amount_before, amount_after, reason, created_by)
              VALUES ({$CO}, 'waive', 'deduction', 501, 150.00, 0.00, 'GOV1T إعفاء بقرار مستقل', 6)");
check($conn->error === '', 'X7: الإعفاء قرار مستقل يشير للأصل — والأصل باقٍ (Insert-only)');
$q = $conn->query("SELECT COUNT(*) c FROM guard_denials WHERE attempted_ref LIKE 'GOV1T%'")->fetch_assoc();
check((int) $q['c'] >= 3, 'X8: سجل المنع يتراكم (' . $q['c'] . ') — مقياس ملاءمة الحماية');

head('X11·X13·X14 — تغيير حالة الوحدات بالسلّم الرباعي');
$r = USC::request($conn, $CO, array(
    'scope_type' => 'equipment', 'scope_id' => 16, 'date_from' => '2026-08-12', 'date_to' => '2026-08-14',
    'field_changed' => 'time_state', 'value_before' => 'توقف بمسؤولية المورد', 'value_after' => 'استعداد بمسؤولية العميل',
    'reason' => 'GOV1T ثبت من مراسلة العميل أن المادة لم تصل', 'doc_ref' => 'MSG-4471',
    'estimated_impact' => array('client' => '+18 ساعة مفوترة', 'supplier' => '+18', 'operator' => '+18'),
    'requested_by' => 4, 'touches_material_right' => true));
check($r['ok'] && $r['needs_gm'], 'X12: طلب بأثر يمس حق العميل — تُضاف خطوة الإدارة العامة');
$CHG = $r['chg_id'];
$r = USC::request($conn, $CO, array('scope_type' => 'equipment', 'scope_id' => 16, 'date_from' => '2026-08-12', 'date_to' => '2026-08-14', 'field_changed' => 'time_state', 'value_before' => 'أ', 'value_after' => 'ب', 'reason' => 'GOV1T ناقص', 'doc_ref' => 'D', 'estimated_impact' => array(), 'requested_by' => 4));
check(!$r['ok'] && $r['code'] === 422, 'طلب بلا أثر مقدَّر ⇒ 422');

$r = USC::approveStep($conn, $CO, $CHG, 3, 7, 'approve', '', true);
check(!$r['ok'] && $r['code'] === 422, 'X11: المالية قبل مدير الحركة ⇒ لا يبدأ الطلب بدونه');
$r = USC::approveStep($conn, $CO, $CHG, 1, 6, 'approve', '', true);
check($r['ok'], 'مدير الحركة أولًا — يؤكد الواقعة');
$r = USC::approveStep($conn, $CO, $CHG, 3, 7, 'approve', '', true);
check(!$r['ok'] && $r['code'] === 409, 'X14: المالية قبل الإدارة المعنية ⇒ 409 وتُعلَّق');
USC::approveStep($conn, $CO, $CHG, 2, 5, 'approve', '', true);
USC::approveStep($conn, $CO, $CHG, 3, 7, 'approve', '', true);
$r = USC::apply($conn, $CO, $CHG, 1);
check(!$r['ok'] && $r['code'] === 422, 'التطبيق قبل اكتمال السلّم (تنقص الإدارة العامة) ⇒ 422');
$r = USC::approveStep($conn, $CO, $CHG, 4, 8, 'approve', '', true);
check($r['ok'] && $r['state'] === 'Approved', 'وبالرابعة اعتُمد');
$r = USC::apply($conn, $CO, $CHG, 1, true, null);
check(!$r['ok'] && $r['code'] === 423, 'X13: أثر مقيَّد سابقًا ⇒ 423 «يُعكس ولا يُعدَّل»');
$r = USC::apply($conn, $CO, $CHG, 1, true, 'REV-EVT-9001');
check($r['ok'], 'وبمرجع العكس يُطبَّق — الأصل باقٍ والقديم معكوس ببنده');
$q = $conn->query("SELECT value_before, value_after FROM unit_state_changes WHERE chg_id={$CHG}")->fetch_assoc();
check($q['value_before'] !== '' && $q['value_after'] !== '', 'الحالتان محفوظتان معًا — لا يُكتب الجديد فوق القديم');

head('X15 — الطوارئ');
$r = USC::request($conn, $CO, array('scope_type' => 'site', 'scope_id' => 10, 'date_from' => '2026-08-01', 'date_to' => '2026-08-01', 'field_changed' => 'classification', 'value_before' => 'س', 'value_after' => 'ص', 'reason' => 'GOV1T طوارئ ميدانية', 'doc_ref' => 'EMR-1', 'estimated_impact' => array('company' => 'تشغيلي'), 'requested_by' => 4));
$EMR = $r['chg_id'];
$r = USC::emergencyApply($conn, $CO, $EMR, 8);
check($r['ok'], 'الإدارة العامة تعتمد مباشرة في الطوارئ');
$conn->query("UPDATE unit_state_changes SET applied_at = DATE_SUB(NOW(), INTERVAL 49 HOUR) WHERE chg_id={$EMR}");
$n = USC::sweepEmergency($conn);
$q = $conn->query("SELECT state FROM unit_state_changes WHERE chg_id={$EMR}")->fetch_assoc();
check($n >= 1 && $q['state'] === 'Reversed', 'X15: 48 ساعة بلا توثيق الثلاث ⇒ عُكس آليًّا ورُفع للمراجعة');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
