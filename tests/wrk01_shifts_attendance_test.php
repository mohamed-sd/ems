<?php
/**
 * tests/wrk01_shifts_attendance_test.php — WRK-01 النواة (البوابة ②)
 * ═══════════════════════════════════════════════════════════════════════════
 * W1 فترتان بمشغّلين = استحقاقان منفصلان · W2 فترة بلا مشغّل 422 · W3 الليلية
 * ليوم بدئها · W4 الإضافي بند مستقل (في تعريف النمط) · W5/W6 ST بالإسناد ·
 * W7 التصنيف الآلي A2 بعد إشعار · W9 لا ترحيل مباشر (بنيويًّا) · W10 الإعفاء
 * بلا حذف · W11 بصمة ناقصة نصف يوم مقترحًا · W12 لا سياسة مطابقة 422 ·
 * + الرموز الأحد عشر · حد ثلث الصافي على الاختيارية (DEC ②).
 * التشغيل: php tests/wrk01_shifts_attendance_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
$_SESSION['user'] = array('id' => 1, 'role' => '3', 'company_id' => 4, 'name' => 'WRK test');
require_once dirname(__DIR__) . '/app/Services/Workforce/AttendanceService.php';

use App\Services\Workforce\AttendanceService as AS_;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate = ems_tenant_db();
$CO = 4;
$P1 = 999301; $P2 = 999302;
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn) {
    $conn->query("DELETE FROM shift_period_logs WHERE equipment_id = 999888");
    $conn->query("DELETE FROM attendance_days WHERE person_id IN (999301,999302)");
    $conn->query("DELETE FROM attendance_sweep_notices WHERE person_id IN (999301,999302)");
    $conn->query("DELETE FROM deduction_proposals WHERE person_id IN (999301,999302)");
    $conn->query("DELETE FROM fin_notifications WHERE title LIKE '%999301%' OR title LIKE '%999302%'");
    $conn->query("DELETE FROM waivers_reversals WHERE source_type='deduction_proposal' AND reason LIKE '%WRK1T%'");
};
register_shutdown_function($teardown);
$teardown();

head('القاموس — الرموز الأحد عشر بأثرها الخماسي');
$q = $conn->query("SELECT COUNT(*) c FROM payroll_absence_types WHERE company_id=4 AND code IS NOT NULL AND active=1")->fetch_assoc();
check((int) $q['c'] === 11, 'الرموز 1·0·10·11·ST·S·M·A1·A2·EM·UP كلها في القاموس الموحَّد (' . $q['c'] . ')');
$q = $conn->query("SELECT COUNT(*) c FROM payroll_absence_types WHERE company_id=4 AND code='A2' AND deducts=1 AND conduct_violation=1")->fetch_assoc();
check((int) $q['c'] === 1, 'A2: يُخصم يومًا بيوم **ومخالفة سلوكية** — أثران لا أثر واحد');
$q = $conn->query("SELECT COUNT(*) c FROM payroll_absence_types WHERE company_id=4 AND code='A1' AND deducts=0")->fetch_assoc();
check((int) $q['c'] === 1, 'A1 المبرَّر لا يُخصم — الفصل يمنع الخصم الظالم (DEC ①)');

head('W1·W2 — الفترة وحدة الحقيقة');
$r = AS_::logPeriod($gate, $CO, array('work_date' => '2026-08-02', 'equipment_id' => 999888, 'shift_no' => 1, 'period_no' => 1, 'operator_person_id' => $P1, 'qty' => 62, 'run_minutes' => 330, 'standby_minutes' => 30, 'period_minutes' => 360), 1);
check($r['ok'], 'الفترة 1 بمشغّلها أحمد (#' . $P1 . ')');
$r = AS_::logPeriod($gate, $CO, array('work_date' => '2026-08-02', 'equipment_id' => 999888, 'shift_no' => 1, 'period_no' => 2, 'operator_person_id' => $P2, 'qty' => 41, 'run_minutes' => 240, 'period_minutes' => 240), 1);
check($r['ok'] && $r['periods_logged'] === 2, 'W1: الفترة 2 بمشغّل مختلف — سطران واستحقاقان منفصلان');
$q = $conn->query("SELECT COUNT(DISTINCT operator_person_id) c, SUM(qty) q FROM shift_period_logs WHERE equipment_id=999888 AND work_date='2026-08-02'")->fetch_assoc();
check((int) $q['c'] === 2 && (float) $q['q'] === 103.0, 'وإنتاج المعدة مجموعهما (103) والأجر لمشغّل الفترة التي أنتجت');

$r = AS_::logPeriod($gate, $CO, array('work_date' => '2026-08-03', 'equipment_id' => 999888, 'shift_no' => 1, 'period_no' => 1, 'operator_person_id' => 0), 1);
check(!$r['ok'] && $r['code'] === 422, 'W2: فترة بلا مشغّل ⇒ 422 ولا تُحفظ');
$r = AS_::logPeriod($gate, $CO, array('work_date' => '2026-08-03', 'equipment_id' => 999888, 'shift_no' => 1, 'period_no' => 1, 'operator_person_id' => $P1, 'run_minutes' => 400, 'period_minutes' => 360), 1);
check(!$r['ok'] && $r['code'] === 422, 'أزمنة تتجاوز مدة الفترة ⇒ 422');
$r = AS_::logPeriod($gate, $CO, array('work_date' => '2026-08-03', 'equipment_id' => 999888, 'shift_no' => 1, 'period_no' => 1, 'operator_person_id' => $P1, 'stop_minutes' => 60, 'period_minutes' => 360), 1);
check(!$r['ok'] && $r['code'] === 422, 'توقف بلا سبب ⇒ 422');
$r = AS_::logPeriod($gate, $CO, array('work_date' => '2026-08-02', 'equipment_id' => 999888, 'shift_no' => 1, 'period_no' => 1, 'operator_person_id' => $P2), 1);
check(!$r['ok'] && $r['code'] === 409, '409 بمرجعه: المفتاح (معدة×تاريخ×وردية×فترة) يمنع تكرار المزامنة');

head('W3 — الليلية تُنسب ليوم بدئها');
$r = AS_::logPeriod($gate, $CO, array('work_date' => '2026-08-02', 'equipment_id' => 999888, 'shift_no' => 2, 'period_no' => 2, 'operator_person_id' => $P1, 'run_minutes' => 300, 'period_minutes' => 300), 1);
check($r['ok'], 'W3: فترة 01:00–06:00 (بعد منتصف الليل) سُجّلت بمفتاح يوم بدء الوردية 2026-08-02 — لا تُقسَّم يومين');

head('W12·② — السياستان لا سياسة واحدة');
$r = AS_::resolvePolicy($gate, $CO, array('employee_scope' => 'hq'));
check($r['ok'] && (int) $r['policy']['grace_minutes'] === 15, 'سياسة المقر: سماح 15 دقيقة (حتى 8:15) وبصمة');
$r = AS_::resolvePolicy($gate, $CO, array('employee_scope' => 'field'));
check($r['ok'] && $r['policy']['grace_minutes'] === null, 'سياسة المشاريع: لا بصمة ولا تأخر مكتبي');
$r = AS_::resolvePolicy($gate, $CO, array('employee_scope' => 'moon_base'));
check(!$r['ok'] && $r['code'] === 422, 'W12: لا سياسة مطابقة ⇒ 422 ولا يُفترض');

head('W5·W6 — ST تقرأ الإسناد: الحالة واحدة والأثر يتبعه');
$r = AS_::impactOf($gate, $CO, 'ST', array('bearer_party' => 'client'));
check($r['ok'] && $r['pay'] === 'full' && !$r['incentive'] && $r['billable'] && $r['supplier_due'],
      'W5: انتظار بمسؤولية العميل — راتب نعم · حافز لا · مفوترة · يستحقها المورد والمشغّل');
$r = AS_::impactOf($gate, $CO, 'ST', array('bearer_party' => 'supplier'));
check($r['ok'] && $r['pay'] === 'full' && !$r['billable'] && !$r['supplier_due'],
      'W6: انتظار بعطل معدة المورد — راتب نعم · غير مفوترة · لا يستحقها المورد ويُجزى');
$r = AS_::impactOf($gate, $CO, 'ST', array());
check(!$r['ok'] && $r['code'] === 422, 'ST بلا إسناد ⇒ 422 — لا يُفترض الطرف');
$r = AS_::impactOf($gate, $CO, 'XX', array());
check(!$r['ok'] && $r['code'] === 422, 'حالة خارج الرموز ⇒ 422 — «لا تُحتسب حالة ليست في §4»');

head('W7 — التصنيف الآلي (DEC-01 ④ الموقَّع): 48 ساعة إشعار + 24 مهلة ثم A2');
$pending = array(array('person_id' => $P1, 'att_date' => date('Y-m-d', strtotime('-4 days'))));
$r = AS_::sweepUnclassified($conn, $gate, $CO, $pending, 1);
check($r['notified'] === 1 && $r['classified'] === 0, 'المرحلة ①: تجاوز 48 ساعة → إشعار ومهلة 24 ساعة — ولا A2 بعدُ');
$q = $conn->query("SELECT COUNT(*) c FROM attendance_days WHERE person_id={$P1}")->fetch_assoc();
check((int) $q['c'] === 0, 'اليوم لم يُصنَّف في مرحلة الإشعار — لا A2 بصمت ولا بلا مهلة');
$q = $conn->query("SELECT COUNT(*) c FROM fin_notifications WHERE title LIKE '%{$P1}%'")->fetch_assoc();
check((int) $q['c'] === 1, 'وإشعار المسؤول مسجَّل ببدء المهلة');
// إعادة الكنس قبل انقضاء المهلة — لا شيء
$r = AS_::sweepUnclassified($conn, $gate, $CO, $pending, 1);
check($r['notified'] === 0 && $r['classified'] === 0, 'إعادة الكنس داخل المهلة — عاطلة (لا إشعار ثانٍ ولا تصنيف)');
// انقضاء المهلة الإضافية (24 ساعة) → A2 آليًّا بوسمه
$conn->query("UPDATE attendance_sweep_notices SET notified_at = DATE_SUB(NOW(), INTERVAL 25 HOUR) WHERE person_id={$P1}");
$r = AS_::sweepUnclassified($conn, $gate, $CO, $pending, 1);
check($r['classified'] === 1, 'المرحلة ②: انقضت المهلة الإضافية → A2 آليًّا');
$q = $conn->query("SELECT status_code, auto_reclassified FROM attendance_days WHERE person_id={$P1}")->fetch_assoc();
check($q['status_code'] === 'A2' && (int) $q['auto_reclassified'] === 1, 'موسومًا آليًّا — قابلًا للتمييز في المراجعة');
$r = AS_::sweepUnclassified($conn, $gate, $CO, array(array('person_id' => $P2, 'att_date' => date('Y-m-d'))), 1);
check($r['notified'] === 0 && $r['classified'] === 0, 'وغياب اليوم (دون 48 ساعة) لا يُمسّ');

head('W9·W11 + حد الثلث — دورة الخصم');
$r = AS_::proposeDeduction($gate, $CO, array('person_id' => $P1, 'period' => '2026-08', 'source' => 'missing_punch', 'source_ref' => 'DAY:2026-08-02', 'proposed_amount' => 50.00));
check($r['ok'] && $r['state'] === 'Proposed', 'W11: بصمة ناقصة ⇒ خصم نصف يوم **مقترحًا** لا مرحَّلًا');
$D1 = $r['ded_id'];
$r = AS_::postDeduction($gate, $CO, $D1, null, null, 3000);
check(!$r['ok'] && $r['code'] === 403, 'W9: ترحيل بلا اعتماد ⇒ 403 بنيويًّا ولا يظهر في المسيّر');
$e = $conn->query("UPDATE deduction_proposals SET state='Posted' WHERE ded_id={$D1}");
check($e === false, 'وCHECK الجدول يرفض Posted بلا مرجع سلّم — حتى بالالتفاف');
$conn->query("UPDATE deduction_proposals SET state='Approved' WHERE ded_id={$D1}");
$r = AS_::postDeduction($gate, $CO, $D1, 'GOV-CHAIN-771', 55, 3000);
check($r['ok'] && $r['posted_amount'] === 50.0, 'وبالاعتماد الثلاثي (مرجع السلّم) يُرحَّل ببنده');

// حد الثلث على الاختيارية (DEC ②): صافي 3000 → سقف 1000
AS_::proposeDeduction($gate, $CO, array('person_id' => $P2, 'period' => '2026-08', 'source' => 'advance_installment', 'source_ref' => 'ADV:1', 'proposed_amount' => 800.00, 'is_voluntary' => 1));
AS_::proposeDeduction($gate, $CO, array('person_id' => $P2, 'period' => '2026-08', 'source' => 'advance_installment', 'source_ref' => 'ADV:2', 'proposed_amount' => 400.00, 'is_voluntary' => 1));
$ids = array();
$q = $conn->query("SELECT ded_id FROM deduction_proposals WHERE person_id={$P2} ORDER BY ded_id");
while ($row = $q->fetch_assoc()) { $ids[] = (int) $row['ded_id']; }
$conn->query("UPDATE deduction_proposals SET state='Approved' WHERE person_id={$P2}");
$r = AS_::postDeduction($gate, $CO, $ids[0], 'GOV-CHAIN-772', 55, 3000);
check($r['ok'] && $r['posted_amount'] === 800.0, 'قسط سلفة 800 ≤ ثلث الصافي (1000) يُرحَّل كاملًا');
$r = AS_::postDeduction($gate, $CO, $ids[1], 'GOV-CHAIN-773', 55, 3000);
check($r['ok'] && $r['posted_amount'] === 200.0, 'والقسط الثاني يُقصّ إلى 200 — الحد يقص ويعيد الجدولة لا يُلغي (DEC ②)');
$q = $conn->query("SELECT SUM(proposed_amount) s FROM deduction_proposals WHERE person_id={$P2} AND is_voluntary=1 AND state='Posted'")->fetch_assoc();
check((float) $q['s'] === 1000.0, 'Σ الاختيارية المرحَّلة = ثلث الصافي بالضبط — والجزاءات خارج الحد');

head('W10 — الإعفاء بلا حذف');
$conn->query("INSERT INTO waivers_reversals (company_id, action, source_type, source_id, amount_before, amount_after, reason, created_by)
              VALUES ({$CO}, 'waive', 'deduction_proposal', {$D1}, 50.00, 0.00, 'WRK1T إعفاء بقرار مستقل بعد الترحيل', 6)");
$conn->query("UPDATE deduction_proposals SET state='Waived', waiver_ref=" . (int) $conn->insert_id . " WHERE ded_id={$D1}");
$q = $conn->query("SELECT state, waiver_ref, proposed_amount FROM deduction_proposals WHERE ded_id={$D1}")->fetch_assoc();
check($q['state'] === 'Waived' && (int) $q['waiver_ref'] > 0 && (float) $q['proposed_amount'] === 50.0,
      'W10: الأصل باقٍ معلَّمًا «مُعفًى» بقرار مستقل يشير إليه — صفر سطر محذوف');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
