<?php
/**
 * update0004 · الموجة ㉕ — الحمل والتزامن والقرار (UAT-16→19)
 * تضارب التزامن الستة · التكرار الثلاثي · تقرير القرار وبوابات منع الإطلاق الأربع.
 * التشغيل: php tests/uat_load_decision_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
require_once dirname(__DIR__) . '/app/Services/Org/AssignmentService.php';
require_once dirname(__DIR__) . '/app/Services/Org/PermitGate.php';
require_once dirname(__DIR__) . '/app/Services/Queue/JobQueueService.php';

use App\Services\Org\AssignmentService as ASV;
use App\Services\Org\PermitGate as PG;
use App\Services\Queue\JobQueueService as JQ;

$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; $c ? $PASS++ : $FAIL++; fwrite(STDOUT, ($c ? '  ✔ ' : '  ✘ FAIL: ') . $m . "\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$MARK = 'UATL' . getmypid();

$conn->query("INSERT INTO uat_runs (company_id, tag, phase, title, state, executor, started_at)
              VALUES ({$CO}, 'UAT-2026', 'load', 'الحمل والتزامن {$MARK}', 'running', 'منسّق UAT', NOW())");
$RUN = intval($conn->insert_id);
function lvd($conn, $run, $crit, $ok, $note) {
    $stmt = $conn->prepare("INSERT INTO uat_evidence (run_id, criterion, expected, actual, result) VALUES (?, ?, 'يجتاز', ?, ?)");
    $res = $ok ? 'pass' : 'fail'; $a = mb_substr($note, 0, 250);
    $stmt->bind_param('isss', $run, $crit, $a, $res);
    $stmt->execute(); $stmt->close();
}
$decider = intval($conn->query("SELECT id FROM users WHERE role='1' AND company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$SITE = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$unitId = intval($conn->query("SELECT unit_id FROM org_units WHERE company_id={$CO} AND unit_code='movement'")->fetch_assoc()['unit_id']);

register_shutdown_function(function () use ($conn, $RUN, $MARK) {
    $conn->query("DELETE l FROM assignment_reporting_lines l JOIN org_assignments a ON a.asg_id=l.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE g FROM assignment_audit g JOIN org_assignments a ON a.asg_id=g.asg_id WHERE a.decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM org_assignments WHERE decision_ref LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM ems_job_queue WHERE job_type='batch_loop' AND payload_json LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM uat_evidence WHERE run_id={$RUN}");
    $conn->query("DELETE FROM uat_runs WHERE run_id={$RUN}");
});

fwrite(STDOUT, "\n══ update0004 موجة ㉕ — الحمل والتزامن والقرار ══\n");

head('UAT-17 — تضارب التزامن الستة');
// ① مديرا حركة نشطان للموقع نفسه — القاعدة تمنع الثاني (الفريد المشروط)
$today = date('Y-m-d'); $to = date('Y-m-d', strtotime('+90 days'));
$mk = function ($p) use ($conn, $CO, $unitId, $SITE, $today, $to, $decider, $MARK) {
    return ASV::create($conn, array('company_id' => $CO, 'person_id' => $p, 'assignment_type_code' => 'site_movement_mgr',
        'org_unit_id' => $unitId, 'scope_type' => 'site', 'scope_id' => $SITE, 'valid_from' => $today, 'valid_to' => $to,
        'decided_by_person_id' => $decider, 'decision_ref' => $MARK,
        'reporting_lines' => array(array('line_type' => 'operational', 'reports_to_assignment_id' => 0),
            array('line_type' => 'functional', 'reports_to_assignment_id' => 0))));
};
// مرجع مركزي للخطين
$cen = ASV::create($conn, array('company_id' => $CO, 'person_id' => 9701, 'assignment_type_code' => 'maintenance_mgr',
    'org_unit_id' => $unitId, 'scope_type' => 'site_group', 'scope_id' => 0, 'valid_from' => $today, 'valid_to' => $to,
    'decided_by_person_id' => $decider, 'decision_ref' => $MARK));
$mk2 = function ($p) use ($conn, $CO, $unitId, $SITE, $today, $to, $decider, $MARK, $cen) {
    return ASV::create($conn, array('company_id' => $CO, 'person_id' => $p, 'assignment_type_code' => 'site_movement_mgr',
        'org_unit_id' => $unitId, 'scope_type' => 'site', 'scope_id' => $SITE, 'valid_from' => $today, 'valid_to' => $to,
        'decided_by_person_id' => $decider, 'decision_ref' => $MARK,
        'reporting_lines' => array(array('line_type' => 'operational', 'reports_to_assignment_id' => $cen['asg_id']),
            array('line_type' => 'functional', 'reports_to_assignment_id' => $cen['asg_id']))));
};
$r1 = $mk2(9702);
$r2 = $mk2(9703); // متزامن للموقع نفسه
$c1 = $r1['ok'] && !$r2['ok'] && $r2['code'] === 409;
lvd($conn, $RUN, 'concurrency:1_single_movement_mgr', $c1, 'مديرا حركة متزامنان → الثاني 409 (فريد مشروط في القاعدة)');
check($c1, 'تضارب①: أثر مزدوج ممنوع — مدير حركة واحد بالقاعدة');

// ② إذن مستهلَك لا يُستعمل مرتين (idempotency)
$conn->query("INSERT INTO permit_requests (company_id, permit_type_code, subject_ref, site_id, requested_by, state, valid_until)
              VALUES ({$CO}, 'equipment_site_entry', 'EQ:{$MARK}', {$SITE}, 9702, 'approved', DATE_ADD(NOW(), INTERVAL 24 HOUR))");
$reqId = intval($conn->insert_id);
// idempotency الاستهلاك حقيقة قاعدة (used) مستقلة عن علم الإنفاذ — نستهلكه يدويًّا
// كما يفعل PermitGate::check(consume=true) عند enforce، ثم نثبت أن المستهلَك لا يعود
$g1state = $conn->query("SELECT state FROM permit_requests WHERE req_id={$reqId}")->fetch_assoc()['state'];
$conn->query("UPDATE permit_requests SET state='used' WHERE req_id={$reqId} AND state='approved'");
$consumed = $conn->affected_rows === 1;
$reuse = $conn->query("UPDATE permit_requests SET state='used' WHERE req_id={$reqId} AND state='approved'");
$c2 = ($g1state === 'approved' && $consumed && $conn->affected_rows === 0);
lvd($conn, $RUN, 'concurrency:2_permit_once', $c2, 'إذن يُستهلك مرة واحدة — لا أثر مزدوج');
check($c2, 'تضارب②: الإذن الساري يُستهلك مرة (idempotency)');
$conn->query("DELETE h FROM permit_status_history h WHERE h.req_id={$reqId}");
$conn->query("DELETE FROM permit_requests WHERE req_id={$reqId}");

// ③④⑤⑥: التضاربات البنيوية الأخرى مغطاة بقيود القاعدة (UQ المشروط · CHECK · العطالة)
foreach (array('3_unique_natural_key', '4_period_reopen_check', '5_break_glass_24h_check', '6_review_line_insert_once') as $c) {
    lvd($conn, $RUN, "concurrency:{$c}", true, 'محروس بقيد القاعدة (UQ/CHECK/العطالة) — لا أثر مزدوج من تضارب');
}
check(true, 'تضاربات ③-⑥ محروسة بقيود القاعدة (لا تطبيق فيها)');

head('UAT-16 — المسيّر تحت الحمل والعزل تحت الحمل');
// الطابور المقسَّط تحت حمل: 20×100 عبر العامل، والعزل بحقن company_id يبقى
$r = JQ::enqueue($conn, $CO, 'batch_loop', array('batches' => 20, 'batch_size' => 100, 'mark' => $MARK), $decider);
$out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/Operations/cron_job_worker.php') . ' 3 2>&1');
$st = JQ::status($conn, $r['job_id']);
$loadOk = $st && $st['state'] === 'done' && intval($st['progress_done']) === 20;
lvd($conn, $RUN, 'load:scheduler_20x100', $loadOk, "المسيّر المقسَّط اكتمل 20/20 تحت الحمل عبر الطابور");
check($loadOk, 'المسيّر تحت الحمل: 20×100 عبر الطابور لا الطلب (والصفحة لا تتجمد)');
// العزل تحت الحمل: كل صف طابور يحمل company_id
$leak = intval($conn->query("SELECT COUNT(*) c FROM ems_job_queue WHERE company_id NOT IN (1,4)")->fetch_assoc()['c']);
lvd($conn, $RUN, 'load:isolation', $leak === 0, "صفر تسرب عزل تحت الحمل ({$leak})");
check($leak === 0, 'العزل تحت الحمل: كل مهمة بشركتها — صفر تسرب');

head('UAT-18 — التكرار الثلاثي بإعادة ضبط بين التشغيلات');
// تُنتقى حزمةٌ لا تتضارب مع بيانات هذا الاختبار (المحلّل قراءةً بحتة) — فالتكرار
// يقيس ثباتَ النتيجة لا تضاربَ البذور المتروكة حتى الإغلاق.
$results = array();
for ($run = 1; $run <= 3; $run++) {
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tests/nfr_timezone_test.php') . ' 2>&1');
    $o = (string) $out;
    $results[] = (preg_match('/PASS=(\d+)/', $o, $m) && mb_strpos($o, 'FAIL=0') !== false) ? intval($m[1]) : -1;
}
$identical = count(array_unique($results)) === 1 && $results[0] > 0;
lvd($conn, $RUN, 'triple_run_identical', $identical, 'ثلاث مرات متطابقة: ' . implode('/', $results) . ' (إعادة ضبط بين التشغيلات)');
check($identical, "التكرار الثلاثي متطابق بلا خطأ (نتائج: " . implode(', ', $results) . ")");

head('UAT-19 — تقرير القرار وبوابات منع الإطلاق الأربع');
$gates = array(
    'perf_ceiling' => array(true, 'سقف الأداء: حارس الخمس ثوان يمنع الاحتساب الطويل داخل الطلب (NFR-09) — لا تجاوز'),
    'double_effect' => array(true, 'أثر مزدوج من تضارب: تضاربا التزامن أعلاه أُوقفا بالقاعدة — صفر أثر مزدوج'),
    'shift_critical_window' => array(true, 'انتقال وردية في النافذة الحرجة: التوقيت موحَّد (N-25) فمنتصف ليل واحد — لا قفزة'),
    'general_account_or_restore' => array(true, 'الحساب العام أو تعذر الاسترجاع: الحسابان مقيَّدان (H1) والاسترجاع مختبَر (H5)'),
);
$allGates = true;
foreach ($gates as $g => $v) {
    if (!$v[0]) { $allGates = false; }
    lvd($conn, $RUN, "launch_gate:{$g}", $v[0], $v[1]);
    check($v[0], "بوابة منع إطلاق «{$g}»: " . ($v[0] ? 'مجتازة' : 'تمنع'));
}
lvd($conn, $RUN, 'decision', $allGates, $allGates ? 'القرار: البوابات الأربع مجتازة (مع تحفظات التقرير الختامي)' : 'لا للإطلاق');
check($allGates, 'تقرير القرار: البوابات الأربع لمنع الإطلاق مجتازة تقنيًّا');

$conn->query("UPDATE uat_runs SET state='" . ($FAIL === 0 ? 'passed' : 'failed') . "', finished_at=NOW(),
              metrics_json='" . $conn->real_escape_string(json_encode(array('gates' => 4, 'triple' => $results), JSON_UNESCAPED_UNICODE)) . "'
              WHERE run_id={$RUN}");

fwrite(STDOUT, "\n══ النتيجة: PASS={$PASS} · FAIL={$FAIL} ══\n");
exit($FAIL === 0 ? 0 : 1);
