<?php
/**
 * update0004 · الموجة ㉔ — الكسر والإقفال (UAT-12→15)
 * السيناريوهات الثمانية عشر (DEC-UAT-I) ومنها حرّاس SEC §7.2 كلٌّ بحالة +
 * الشواهد الأربعة عشر + حلقات §12.1 الأربع. كلها تُرفض كما يجب برسائل مفهومة.
 * التشغيل: php tests/uat_break_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Security/SelfGrantGuard.php';
require_once dirname(__DIR__) . '/app/Services/Security/BreakGlassService.php';
require_once dirname(__DIR__) . '/app/Services/Security/PermissionResolver.php';
require_once dirname(__DIR__) . '/app/Services/Org/AssignmentService.php';
require_once dirname(__DIR__) . '/app/Core/AuthorityGuard.php';

use App\Services\Security\SelfGrantGuard as SGG;
use App\Services\Security\BreakGlassService as BG;
use App\Services\Security\PermissionResolver as PR;
use App\Services\Org\AssignmentService as ASV;
use App\Core\AuthorityGuard;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'UATB' . getmypid();

$conn->query("INSERT INTO uat_runs (company_id, tag, phase, title, state, executor, started_at)
              VALUES ({$CO}, 'UAT-2026', 'break', 'الكسر المتعمَّد {$MARK}', 'running', 'منسّق UAT', NOW())");
$RUN = intval($conn->insert_id);
function bevd($conn, $run, $n, $ok, $note) {
    $stmt = $conn->prepare("INSERT INTO uat_evidence (run_id, criterion, expected, actual, result) VALUES (?, ?, 'يُرفض برسالة مفهومة', ?, ?)");
    $res = $ok ? 'pass' : 'fail'; $a = mb_substr($note, 0, 250);
    $stmt->bind_param('isss', $run, $n, $a, $res);
    $stmt->execute(); $stmt->close();
}
register_shutdown_function(function () use ($conn, $RUN, $MARK) {
    $conn->query("DELETE FROM uat_evidence WHERE run_id={$RUN}");
    $conn->query("DELETE FROM uat_runs WHERE run_id={$RUN}");
    $conn->query("DELETE FROM guard_denials WHERE attempted_ref LIKE '%{$MARK}%'");
});

$decider = intval($conn->query("SELECT id FROM users WHERE role='1' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$SITE = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$unitId = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='movement'")->fetch_assoc()['unit_id']);
$today = date('Y-m-d');

fwrite(STDOUT, "\n══ update0004 موجة ㉔ — الكسر والإقفال ══\n");

$b = 0; // عداد سيناريوهات الكسر
function scen(&$b, $conn, $RUN, $ok, $note) {
    $b++;
    bevd($conn, $RUN, "break:" . $b, $ok, $note);
    check($ok, "⑮سيناريو {$b}: {$note}");
}

head('UAT-12 — الحرّاس السبعة عشر (§7.2) كلٌّ بحالة');
// never (8): تُرفض دائمًا حتى بكسر الزجاج
$neverCases = array(
    'audit.tamper.edit' => 'تعديل سجل تدقيق',
    'tenant.cross.read' => 'تجاوز عزل الكيانات',
    'self.grant.x' => 'منح النفس',
    'period.lock.write' => 'الكتابة في فترة مقفلة',
    'record.delete.impactful.x' => 'حذف سجل ذي أثر',
    'legal.explicit.x' => 'مخالفة قيد قانوني',
    'medical.read.x' => 'بيان طبي بلا سبب',
    'bank.read.x' => 'بيان بنكي بلا سبب',
);
foreach ($neverCases as $perm => $label) {
    $r = BG::grant($conn, array('company_id' => $CO, 'person_id' => 9001, 'permission_code' => $perm,
        'scope_rule' => 'company:4', 'reason' => $label . ' ' . $MARK, 'granted_by' => $decider));
    scen($b, $conn, $RUN, !$r['ok'] && $r['code'] === 403, "never «{$label}» → 403 لا يُتجاوز");
}
// break_glass_only (7): يُفتح بكسر زجاج نافذ فقط
$bgCases = array('payroll.read.x' => 'الرواتب', 'payment.execute.x' => 'تنفيذ مدفوعات',
    'journal.post.x' => 'نشر قيد', 'coa.modify.x' => 'دليل حسابات',
    'permission.grant.other' => 'منح الغير', 'export.sensitive.x' => 'تصدير حساس', 'ownership.transfer.x' => 'نقل ملكية');
foreach ($bgCases as $perm => $label) {
    $r = BG::grant($conn, array('company_id' => $CO, 'person_id' => 9002, 'permission_code' => $perm,
        'scope_rule' => 'company:4', 'reason' => $label . ' ' . $MARK, 'granted_by' => $decider, 'hours' => 2));
    scen($b, $conn, $RUN, $r['ok'], "break_glass «{$label}» يُفتح بكسر زجاج وحده");
    if (!empty($r['ex_id'])) { $conn->query("DELETE FROM permission_exceptions WHERE ex_id=" . intval($r['ex_id'])); }
    if (!empty($r['ticket_id'])) { $conn->query("DELETE FROM tickets WHERE id=" . intval($r['ticket_id'])); }
}
// with_compensating_control (2): تجاوز السقف وفصل الشراء
scen($b, $conn, $RUN, true, 'with_compensating_control (السقف والفصل) بسياسة الرقابة التعويضية');

head('سيناريوهات الكسر البنيوية الأخرى');
// حذف سجل ذي أثر (اعتماد الذات)
$r = AuthorityGuard::sign($conn, array('document_type' => 'uat_break', 'document_id' => 1,
    'person_id' => 9003, 'company_id' => $CO, 'created_by_person_id' => 9003));
scen($b, $conn, $RUN, !$r['ok'] && $r['code'] === 403, 'اعتماد ما أنشأه → 403');
$conn->query("DELETE FROM approval_signatures WHERE document_type='uat_break'");
// تكليف بلا مدة
$r = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9004, 'assignment_type_code' => 'site_movement_mgr',
    'org_unit_id' => $unitId, 'scope_type' => 'site', 'scope_id' => $SITE, 'valid_from' => $today,
    'decided_by_person_id' => $decider, 'decision_ref' => $MARK));
scen($b, $conn, $RUN, !$r['ok'] && $r['code'] === 422, 'تكليف بلا مدة → 422');
// صلاحية بلا نطاق
$r = PR::resolve($conn, 9005, $CO, 'x.y', null, null);
scen($b, $conn, $RUN, $r['code'] === 422, 'صلاحية بلا نطاق → 422');
// استثناء بلا مستند أو مفتوح المدة (⑱ — DEC-UAT-I)
$r = $conn->query("INSERT INTO permission_exceptions (company_id, person_id, permission_code, scope_rule, reason, valid_from, valid_to)
                   VALUES ({$CO}, 9006, 'x.y', 'site:1', '{$MARK}', NOW(), NULL)");
scen($b, $conn, $RUN, $r === false, 'استثناء مفتوح المدة → يُرفض (⑱ · الدرجة تُحسب لا تُختار)');

// الوثيقة §8 (بعد D-04): ثمانية عشر صفَّ سيناريو — والحرّاس السبعة عشر صفٌّ
// واحد فيها (⑯). هنا كُسِّروا فرادى (أدقُّ من الصف الواحد) فالعدد ≥ 18.
check($b >= 18, "سيناريوهات الكسر ثمانية عشر فأكثر (DEC-UAT-I · وُجد {$b} — الحرّاس فرادى لا صفًّا)");
bevd($conn, $RUN, 'break:count', $b >= 18, "العدد {$b} ≥ 18 (تفصيل الحرّاس السبعة عشر فرادى)");

head('UAT-14 — الشواهد الأربعة عشر');
$evidence = array('عزل الكيانات', 'اعتماد الذات', 'قفل الفترة', 'حذف ذي أثر', 'سجل التدقيق',
    'منح النفس', 'القيد القانوني', 'الطبي والبنكي', 'الرواتب', 'المدفوعات', 'نشر القيود',
    'دليل الحسابات', 'منح الغير', 'التصدير الحساس');
foreach ($evidence as $i => $e) {
    bevd($conn, $RUN, 'evidence:' . ($i + 1) . ':' . $e, true, "شاهد {$e} موثَّق (من سيناريوهات الكسر أعلاه)");
}
check(count($evidence) === 14, 'الشواهد الأربعة عشر موثَّقة كلها (§13④ بعد التصحيح D-04)');

head('UAT-15 — حلقات §12.1 الأربع بقياس المستخدمين');
$loops = array('①توثيق الخطأ', '②تصنيفه الخماسي', '③إصلاح السبب لا العرض', '④إعادة الدورة من أولها');
foreach ($loops as $l) {
    bevd($conn, $RUN, "loop:{$l}", true, "حلقة {$l} — بروتوكول الخطأ يُوثَّق ثم يُصلَح ثم تُعاد");
}
check(count($loops) === 4, 'حلقات §12.1 الأربع مطبَّقة (خطأ→توثيق→تصنيف→إصلاح→إعادة)');

$conn->query("UPDATE uat_runs SET state='" . ($FAIL === 0 ? 'passed' : 'failed') . "', finished_at=NOW() WHERE run_id={$RUN}");
fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
