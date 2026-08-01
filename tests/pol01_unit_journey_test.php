<?php
/**
 * tests/pol01_unit_journey_test.php — POL-01 المسار والبوابتان + الإطار (البوابة ①)
 * ═══════════════════════════════════════════════════════════════════════════
 * حالات POL-01 §12.1: P1 الأثر الأولي بأربع إدارات وصفر قيد · P2 ترتيب
 * السلسلة 409 · P3 التخطي الآلي · P4 الاعتراض ببند · P5 بوابة الاستحقاق 403 ·
 * P6 الاعتماد يولّد FES · P7 توحيد الخصومات Proposed · P8 لا افتراض صامتًا 422 ·
 * P9 اكتمال المصفوفة · P10 الأسباب المحكومة · + أولوية العقد على الإدارة.
 * التشغيل: php tests/pol01_unit_journey_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/app/Services/Policy/PolicyResolver.php';
require_once dirname(__DIR__) . '/app/Services/Policy/UnitJourneyService.php';

use App\Core\EventPublisher;
use App\Services\Policy\PolicyResolver as PR;
use App\Services\Policy\UnitJourneyService as UJ;

EventPublisher::$rootModeOverride = 'publish';

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $UNIT = 990112; $UNIT2 = 990113;
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn, $UNIT, $UNIT2) {
    $conn->query("DELETE FROM approval_signatures WHERE document_type IN ('unit_chain','entitlement') AND document_id IN ({$UNIT},{$UNIT2})");
    $conn->query("DELETE FROM unit_effects WHERE source_unit_id IN ({$UNIT},{$UNIT2})");
    $conn->query("DELETE FROM fin_financial_events WHERE idempotency_key LIKE 'entitlement:pe:%' AND entity_type='unit_effect'");
    $conn->query("DELETE FROM ems_business_events WHERE entity_type='unit_effect'");
};
register_shutdown_function($teardown);
$teardown();

head('P8 — لا افتراض صامتًا');
// الثابت لا العدد: شركة بلا سياسات إطلاقًا (الإدارات الثماني في الشركة 4
// صارت كلها بسياسة نافذة بعد pol01_remaining_policies — وهذا صحيح لا فشل)
$r = PR::resolve($conn, 999999, 'treasury');
check(!$r['ok'] && $r['code'] === 422, 'إدارة بلا سياسة نافذة ⇒ 422 ولا يُفترض شيء');
$r = PR::resolve($conn, $CO, 'sales');
check($r['ok'] && count($r['matrix']) === 18 && count($r['chain']) === 5, 'سياسة التشغيل/المبيعات نافذة: مصفوفة 6 حالات × 3 أطراف + سلسلة خمسية');
$SALES = $r;
$r2 = PR::resolve($conn, $CO, 'suppliers');
check($r2['ok'] && count($r2['matrix']) === 10, 'سياسة الموردين نافذة بمصفوفتها (مشتقة من الأم)');

head('P9 — اكتمال المصفوفة');
$pid = intval($SALES['policy']['policy_id']);
$r = PR::impactOf($conn, $pid, 'standby_client', 'supplier');
check($r['ok'] && $r['effect'] === 'countable', 'استعداد بمسؤولية العميل ⇒ وحدة للمورد (من المصفوفة لا استنتاجًا)');
$r = PR::impactOf($conn, $pid, 'lunch_break', 'client');
check(!$r['ok'] && $r['code'] === 422, 'حالة ليست في المصفوفة ⇒ تُرفض ولا يُستنتج أثرها');

head('P2·P3 — السلسلة: الترتيب والتخطي');
$CHAIN = $SALES['chain'];
$ctx = array('applicable_roles' => array('site', 'operations', 'suppliers', 'workforce', 'finance'), 'entered_by' => 9);
$r = UJ::approveLink($conn, $CO, $UNIT, $CHAIN, 'suppliers', 5, $ctx);
check(!$r['ok'] && $r['code'] === 409, 'P2: اعتماد الموردين قبل التشغيل ⇒ 409 وتُعلَّق حتى تكتمل السابقة');
$r = UJ::approveLink($conn, $CO, $UNIT, $CHAIN, 'site', 8, $ctx);
check($r['ok'] && $r['next_link'] === 'operations', 'الموقع أولًا — أول إثبات للواقعة');
UJ::approveLink($conn, $CO, $UNIT, $CHAIN, 'operations', 4, $ctx);
UJ::approveLink($conn, $CO, $UNIT, $CHAIN, 'suppliers', 5, $ctx);
UJ::approveLink($conn, $CO, $UNIT, $CHAIN, 'workforce', 7, $ctx);
$r = UJ::approveLink($conn, $CO, $UNIT, $CHAIN, 'finance', 6, $ctx);
check($r['ok'] && $r['chain_state'] === 'completed', 'اكتملت الحلقات الخمس بتوقيعاتها');

// P3: يوم بلا معدة مورد — حلقتا الموردين والقوى تُتخطيان
$ctx2 = array('applicable_roles' => array('site', 'operations', 'finance'), 'entered_by' => 9);
UJ::approveLink($conn, $CO, $UNIT2, $CHAIN, 'site', 8, $ctx2);
UJ::approveLink($conn, $CO, $UNIT2, $CHAIN, 'operations', 4, $ctx2);
$r = UJ::approveLink($conn, $CO, $UNIT2, $CHAIN, 'finance', 6, $ctx2);
check($r['ok'] && $r['chain_state'] === 'completed', 'P3: يوم بلا معدة مورد — الحلقة تُتخطى آليًّا ولا تعطّل السلسلة');
$r = UJ::approveLink($conn, $CO, $UNIT2, $CHAIN, 'suppliers', 5, $ctx2);
check(!$r['ok'] && $r['code'] === 422, 'والحلقة المتخطاة لا تُعتمد أصلًا — غير منطبقة');

head('P4·P10 — الاعتراض ببند بسبب محكوم');
$r = UJ::objectLine($conn, $CO, $UNIT, 'L-3', 'qty_mismatch', 'operations', 4);
check($r['ok'] && mb_strpos($r['reason'], 'البقية تمضي') !== false, 'P4: البند المعترَض معلَّق والبقية تمضي');
$r = UJ::objectLine($conn, $CO, $UNIT, 'L-4', 'سبب من رأسي', 'operations', 4);
check(!$r['ok'] && $r['code'] === 422, 'P10: سبب نصي حر يُرفض — القائمة المحكومة حصرًا');

head('P1 — الأثر الأولي: أربع إدارات وصفر قيد');
$ledgerBefore = intval($conn->query("SELECT COUNT(*) c FROM fin_journal_entries")->fetch_assoc()['c']);
$r = UJ::applyPrimary($conn, $CO, $UNIT, array(
    array('domain' => 'suppliers', 'effect_kind' => 'container_consumption', 'quantity' => 10),
    array('domain' => 'sales', 'effect_kind' => 'container_consumption', 'quantity' => 10),
    array('domain' => 'workforce', 'effect_kind' => 'production', 'quantity' => 62),
    array('domain' => 'fleet', 'effect_kind' => 'hours', 'quantity' => 10),
), '2026-08');
$ledgerAfter = intval($conn->query("SELECT COUNT(*) c FROM fin_journal_entries")->fetch_assoc()['c']);
check(count($r['primary_effects']) === 4 && $r['ledger_entries'] === 0, 'P1: آثار أولية في أربع إدارات');
check($ledgerBefore === $ledgerAfter, 'وLedger: صفر قيد — القياس فوري والمال مؤجَّل');
$r2 = UJ::applyPrimary($conn, $CO, $UNIT, array(array('domain' => 'fleet', 'effect_kind' => 'hours', 'quantity' => 10)), '2026-08');
check(count($r2['primary_effects']) === 0, 'إعادة التطبيق عاطلة (UQ) — لا أثر مكرر');

head('P5·P6 — بوابة الاستحقاق');
$r = UJ::proposeEntitlements($conn, $CO, '2026-08');
check($r['proposed'] >= 4, 'التجميع مقترحًا (Proposed) — ' . $r['proposed'] . ' أثرًا ماليًّا مقترحًا');
$PE = intval($conn->query("SELECT pe_id FROM unit_effects WHERE source_unit_id={$UNIT} AND stage='financial' AND effect_kind='production' LIMIT 1")->fetch_assoc()['pe_id']);
$r = UJ::postEntitlement($conn, $CO, $PE, null, null, 1);
check(!$r['ok'] && $r['code'] === 403, 'P5: قيد بلا اعتماد ⇒ 403 بنيويًّا ولا يظهر في تسوية ولا مستخلص');
$e = $conn->query("UPDATE unit_effects SET state='Posted' WHERE pe_id={$PE}");
check($e === false, 'وCHECK الجدول يرفض Posted بلا اعتماد ومرجع حدث — حتى بالالتفاف على الخدمة');
$r = UJ::postEntitlement($conn, $CO, $PE, 5, 5, 1);
check(!$r['ok'] && $r['code'] === 403, 'الاعتمادان من شخص واحد ⇒ 403');
$r = UJ::postEntitlement($conn, $CO, $PE, 5, 6, 1);
check($r['ok'] && strpos($r['reason'], 'FES') !== false, 'P6: باعتماد الإدارة + المالية يُقيَّد ويولَّد حدث FES: ' . $r['reason']);
$q = $conn->query("SELECT state, fin_event_ref FROM unit_effects WHERE pe_id={$PE}")->fetch_assoc();
check($q['state'] === 'Posted' && intval($q['fin_event_ref']) > 0, 'fin_event_ref مملوء — الخيط متصل ولا جدول مال ثانٍ');
$r2 = UJ::postEntitlement($conn, $CO, $PE, 5, 6, 1);
check($r2['ok'] && mb_strpos($r2['reason'], 'عاطل') !== false, 'إعادة القيد عاطلة');

head('P7 — توحيد الخصومات');
$r = UJ::proposeDeduction($conn, $CO, $UNIT, 'suppliers', 'readiness_penalty', 3, '2026-08');
check($r['state'] === 'Proposed', 'P7: خصم المورد Proposed حصرًا — كخصم الموظف تمامًا، ولا ترحيل بلا سلّم GOV-01');
$q = $conn->query("SELECT COUNT(*) c FROM deduction_types WHERE requires_approval = 0")->fetch_assoc();
check((int) $q['c'] === 0, 'وصفر نوع خصم آلي الترحيل في القاموس (CHECK بنيوي)');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
