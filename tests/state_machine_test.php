<?php
/**
 * اختبارات محرّك الحالات — K7 (النطاق الضيّق المُقرّ)
 * التشغيل: php tests/state_machine_test.php — رمز الخروج 0/1.
 * كيانات الاختبار: صفوف حدثٍ اختبارية على fin_financial_events (حالاتها
 * اللاتينية القائمة draft/dept_review/dept_approved — لا مساس بأي ENUM).
 * التشديد المُقرّ: «غير المخوَّل بالمنصب» ثنائي الاتجاه + fallback الـ31.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
mysqli_report(MYSQLI_REPORT_OFF);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/positions.php';
require_once dirname(__DIR__) . '/app/Core/EventPublisher.php';
require_once dirname(__DIR__) . '/app/Core/StateMachine.php';

use App\Core\EventPublisher;
use App\Core\StateMachine;
use App\Core\StateMachineException;

$PASS = 0; $FAIL = 0;
function ok($label, $cond) {
    global $PASS, $FAIL;
    if ($cond) { $PASS++; echo "  ✔ {$label}\n"; }
    else { $FAIL++; echo "  ✘ FAIL: {$label}\n"; }
}
function throws($label, $fn, $needle = '') {
    try { $fn(); ok($label . ' (توقعنا رفضًا فمرّ!)', false); }
    catch (StateMachineException $e) {
        ok($label, $needle === '' || mb_strpos($e->getMessage(), $needle) !== false);
    }
}

$conn = new mysqli(ems_env('DB_HOST'), ems_env('DB_APP_USER'), ems_env('DB_APP_PASS'), ems_env('DB_NAME'));
if ($conn->connect_errno) { fwrite(STDERR, "FATAL: db connect\n"); exit(1); }
$conn->set_charset('utf8mb4');

$MARK = 'K7TEST_' . getmypid();

/** صف كيان اختباري (حدث عقدٍ حالته draft) عبر الناشر نفسه. */
function mk_entity(mysqli $conn, $MARK, $n)
{
    $r = EventPublisher::publish($conn, array(
        'event_key' => 'probe.k_seven', 'category' => 'operational', 'source_module' => 'system',
        'company_id' => 4, 'entity_type' => 'k7probe', 'entity_id' => 770000 + $n,
        'occurred_at' => '2026-07-08 12:00:00', 'created_by' => 1,
        'payload' => array('n' => $n), 'notes' => $MARK,
    ));
    return $r['id'];
}

$GUARD_FLIP = null; // للحالة 10: حارس يقلب الحالة خلف ظهر المحرك (سباق حقيقي)

$def = array(
    'workflow' => 'k7_test_flow',
    'entity_table' => 'fin_financial_events',
    'state_column' => 'state',
    'company_column' => 'company_id',
    'states' => array('draft' => 'مسودة', 'dept_review' => 'مراجعة القسم', 'dept_approved' => 'اعتماد القسم'),
    'initial' => 'draft',
    'transitions' => array(
        'submit' => array('from' => 'draft', 'to' => 'dept_review', 'roles' => array('17')),
        'approve_dept' => array('from' => 'dept_review', 'to' => 'dept_approved', 'roles' => array('19')),
        'guarded_never' => array('from' => 'draft', 'to' => 'dept_review', 'roles' => array('17'),
            'guard' => function ($row) { return false; }),
        'race_probe' => array('from' => 'draft', 'to' => 'dept_review', 'roles' => array('17'),
            'guard' => function ($row) use (&$GUARD_FLIP) {
                if (is_callable($GUARD_FLIP)) { call_user_func($GUARD_FLIP, $row); }
                return true; // الحارس يقبل — لكن الحالة تغيّرت خلفه (سباق مُصطنع)
            }),
    ),
);

$cleanupUsers = array(); $cleanupPos = array();
function mk_user(mysqli $conn, $MARK, $suffix, $role, $positionId = null)
{
    $pos = $positionId === null ? 'NULL' : intval($positionId);
    $conn->query("INSERT INTO users (name, username, password, role, company_id, status, position_id)
                  VALUES ('{$MARK}_{$suffix}', '{$MARK}_{$suffix}', 'x', '{$role}', 4, 'active', {$pos})");
    return intval($conn->insert_id);
}

try {

echo "── 0) صرامة التعريف ──\n";
try {
    new StateMachine($conn, array('workflow' => 'x'));
    ok('تعريف ناقص يُرفض (توقعنا رفضًا!)', false);
} catch (StateMachineException $e) { ok('تعريف ناقص يُرفض', true); }

$sm = new StateMachine($conn, $def);

// مناصب الاختبار: مانح 17 · مانح 6
$conn->query("INSERT INTO positions (company_id, name, role_id) VALUES (4, '{$MARK}_grant17', 17)");
$posGrant17 = intval($conn->insert_id); $cleanupPos[] = $posGrant17;
$conn->query("INSERT INTO positions (company_id, name, role_id) VALUES (4, '{$MARK}_grant6', 6)");
$posGrant6 = intval($conn->insert_id); $cleanupPos[] = $posGrant6;

$uRole17 = mk_user($conn, $MARK, 'r17', '17');                    // كالـ31: بلا منصب — fallback
$uPos17  = mk_user($conn, $MARK, 'p17', '6', $posGrant17);        // المنصب يمنح ما لا يملكه الدور
$uPosDeny = mk_user($conn, $MARK, 'pdeny', '17', $posGrant6);     // المنصب يحجب ما يملكه الدور
$uRole6  = mk_user($conn, $MARK, 'r6', '6');                      // غير مخوَّل أصلًا
$cleanupUsers = array($uRole17, $uPos17, $uPosDeny, $uRole6);

echo "── 1) الانتقال المشروع + fallback الـ31 (بلا منصب = الدور القائم) ──\n";
$e1 = mk_entity($conn, $MARK, 1);
ok('currentState = draft (اللاتينية القائمة)', $sm->currentState($e1) === 'draft');
$r = $sm->apply($e1, 'submit', $uRole17, array('note' => $MARK));
ok('submit نجح draft→dept_review', $r['from'] === 'draft' && $r['to'] === 'dept_review' && $sm->currentState($e1) === 'dept_review');
ok('المصدر role (مستخدم بلا منصب = سلوك الـ31 القائم حرفيًا)', $r['actor_source'] === 'role' && $r['actor_role'] === '17');
$aud = $conn->query("SELECT * FROM ems_state_transitions WHERE id={$r['transition_id']}")->fetch_assoc();
ok('قيد التدقيق كامل (شركة/من/إلى/فاعل/مصدر)', $aud && intval($aud['company_id']) === 4
    && $aud['from_state'] === 'draft' && $aud['to_state'] === 'dept_review'
    && intval($aud['actor_user_id']) === $uRole17 && $aud['actor_source'] === 'role');

echo "── 2) الحالة الفائتة والفعل المجهول ──\n";
throws('إعادة submit على dept_review تُرفض (حالة فائتة)', function () use ($sm, $e1, $uRole17) {
    $sm->apply($e1, 'submit', $uRole17);
}, 'حالة فائتة');
throws('فعل غير معرّف يُرفض', function () use ($sm, $e1, $uRole17) {
    $sm->apply($e1, 'no_such_action', $uRole17);
});

echo "── 3) التخويل عبر جسر K6 — التشديد المُقرّ ثنائي الاتجاه ──\n";
$e2 = mk_entity($conn, $MARK, 2);
throws('غير مخوَّل بالدور (6 بلا منصب) يُرفض', function () use ($sm, $e2, $uRole6) {
    $sm->apply($e2, 'submit', $uRole6);
}, 'غير مخوَّل');
throws('★ غير المخوَّل بالمنصب: دوره 17 لكن منصبه يمنح 6 ⇒ رفض (المنصب يتقدّم)', function () use ($sm, $e2, $uPosDeny) {
    $sm->apply($e2, 'submit', $uPosDeny);
}, 'position');
$r2 = $sm->apply($e2, 'submit', $uPos17, array('note' => $MARK));
ok('★ المخوَّل بالمنصب: دوره 6 لكن منصبه يمنح 17 ⇒ قبول بمصدر position', $r2['actor_source'] === 'position' && $r2['actor_role'] === '17');
$aud2 = $conn->query("SELECT actor_source FROM ems_state_transitions WHERE id={$r2['transition_id']}")->fetch_assoc();
ok('التدقيق سجّل المصدر position (أثر الجسر موثَّق)', $aud2['actor_source'] === 'position');

echo "── 4) الحارس الرافض ──\n";
$e3 = mk_entity($conn, $MARK, 3);
throws('guard=false يرفض والحالة سليمة', function () use ($sm, $e3, $uRole17) {
    $sm->apply($e3, 'guarded_never', $uRole17);
}, 'الحارس');
ok('الحالة لم تتحرك بعد رفض الحارس', $sm->currentState($e3) === 'draft');

echo "── 5) السباق: القفل التفاؤلي يصدّ تغييرًا خلف ظهر المحرك ──\n";
$e4 = mk_entity($conn, $MARK, 4);
$GUARD_FLIP = function ($row) use ($conn, $e4) {
    $conn->query("UPDATE fin_financial_events SET state='dept_review' WHERE id={$e4}"); // منافس يسبقنا
};
throws('انتقال متزامن يُرفض (0 صفوف من WHERE state=from)', function () use ($sm, $e4, $uRole17) {
    $sm->apply($e4, 'race_probe', $uRole17);
}, 'متزامن');
$GUARD_FLIP = null;
$audCount = intval($conn->query("SELECT COUNT(*) FROM ems_state_transitions WHERE workflow='k7_test_flow' AND entity_id={$e4}")->fetch_row()[0]);
ok('لا قيد تدقيقٍ لانتقالٍ لم يقع', $audCount === 0);

echo "── 6) النشر على الانتقال يرث correlation ──\n";
$e5 = mk_entity($conn, $MARK, 5);
$rootCorr = $conn->query("SELECT correlation_id FROM fin_financial_events WHERE id={$e5}")->fetch_row()[0];
$r5 = $sm->apply($e5, 'submit', $uRole17, array('event' => array(
    'event_key' => 'workflow.state_changed', 'category' => 'operational', 'source_module' => 'system',
    'company_id' => 4, 'entity_type' => 'k7probe_transition', 'entity_id' => $e5,
    'occurred_at' => '2026-07-08 12:30:00', 'created_by' => $uRole17,
    'correlation_id' => $rootCorr, // وراثة سلسلة الكيان
    'payload' => array('from' => 'draft', 'to' => 'dept_review'), 'notes' => $MARK,
)));
ok('حدث الانتقال نُشر ويرث correlation الكيان', $r5['event'] !== null && $r5['event']['duplicate'] === false
    && $r5['event']['correlation_id'] === $rootCorr);

} catch (\Throwable $t) {
    $FAIL++;
    echo "  ✘ استثناء غير متوقع: " . $t->getMessage() . "\n";
}

// ═════ teardown كامل ═════
@$conn->rollback();
$conn->query("DELETE FROM ems_state_transitions WHERE workflow='k7_test_flow'");
$conn->query("DELETE FROM fin_financial_events WHERE notes LIKE 'K7TEST_%'");
foreach ($cleanupUsers as $u) { $conn->query("DELETE FROM users WHERE id=" . intval($u)); }
foreach ($cleanupPos as $p) { $conn->query("DELETE FROM positions WHERE id=" . intval($p)); }
// ⚠️ متتالية EV **لا تُحذف**: إعادتها للصفر تصطدم بأرقامٍ إنتاجيةٍ قائمة
// (uq_fin_event_no) منذ صارت بوابة D05 تلد أحداثًا حقيقية. فجوات الترقيم مقبولة.
$left = intval($conn->query("SELECT COUNT(*) FROM fin_financial_events WHERE notes LIKE 'K7TEST%'")->fetch_row()[0])
      + intval($conn->query("SELECT COUNT(*) FROM users WHERE username LIKE 'K7TEST%'")->fetch_row()[0])
      + intval($conn->query("SELECT COUNT(*) FROM positions WHERE name LIKE 'K7TEST%'")->fetch_row()[0]);
ok('teardown: صفر بقايا', $left === 0);

echo str_repeat('═', 50) . "\n";
echo "النتيجة: {$PASS} ناجح · {$FAIL} فاشل\n";
exit($FAIL === 0 ? 0 : 1);
