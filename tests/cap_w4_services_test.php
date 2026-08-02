<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0005 · الموجة ④ — الخدماتُ الثماني (CAP-18→25 · §6/§7/§9/§16)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/cap_w4_services_test.php
 *
 * ما يُثبته بأسماء الحالات:
 *   C3  الاستبدالُ لا يُنشئ حصةً ولا يغيّر قيمةَ عقد — وزمنُ عدم التغطية يُقاس.
 *   C8  الدرجةُ ① بموافقة مدير الحركة وحدَه.
 *   C9  الدرجةُ ② تُرفض حتى تكتمل الثلاث.
 *   C10 الدرجةُ ③ بلا الماليِّ والتنفيذيِّ → 403.
 *   C11 المحاسبة: العجزُ باقٍ · الحصةُ لم ترتفع · بندُ التغطية ظاهر · العميلُ فُوتر.
 *   C12 تعديلُ الحصة بعد الإقفال → 423.
 *   C13 الفجوةُ يوميًّا بالساعات وتصعيدُها الآلي.
 *   C18 احتياطيٌّ مفعَّلٌ يُحتسب تنفيذًا للحصة ولا يرفع المستهدف.
 *   C19 إدراجُ ساعات التغطية في تنفيذ الحصة → 403.
 *   + الموزّع: حصةٌ بلا التزامٍ 422 · الكمياتُ المشتقةُ لا تُدخل · والجاهزيةُ 403.
 *
 * البذرُ معزول: CAPW4T وسجلّاتٌ 999420xx — يُكنس قبل وبعد.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/CapacityLedgerService.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/SupplierPerformanceAggregator.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/SigmaGuard.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/ObligationDistributor.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/SeatAssignmentService.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/SubstituteCoverageService.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/GapMonitor.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Capacity\CapacityLedgerService as LED;
use App\Services\Capacity\SupplierPerformanceAggregator as AGG;
use App\Services\Capacity\ObligationDistributor as DIST;
use App\Services\Capacity\SeatAssignmentService as SEAT;
use App\Services\Capacity\SubstituteCoverageService as COV;
use App\Services\Capacity\GapMonitor as GAP;
use App\Services\Contract\SupplierContractService as SCS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999904; $REC = 99942044;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn, $REC) {
    $conn->query("DELETE FROM coverage_settlement_lines
                   WHERE cov_id IN (SELECT cov_id FROM substitute_coverages WHERE note LIKE 'CAPW4T%')");
    $conn->query("DELETE FROM substitute_coverages WHERE note LIKE 'CAPW4T%'");
    $conn->query("DELETE FROM capacity_gap_watch
                   WHERE obl_id IN (SELECT id FROM contract_commitments WHERE commitment_code LIKE 'CAPW4T%')
                      OR obl_id NOT IN (SELECT id FROM contract_commitments)");
    // ems_business_events لا تُحذف — الجذرُ append-only والعطالةُ تمنع الازدواج
    $conn->query("DELETE FROM capacity_consumption_ledger WHERE unit_record_id = {$REC} AND reverses_led_id IS NOT NULL");
    $conn->query("DELETE FROM capacity_consumption_ledger WHERE unit_record_id = {$REC}");
    $conn->query("DELETE FROM monthly_performance WHERE period = '2042-05' AND state = 'closed'
                   AND container_id IN (SELECT id FROM op_containers WHERE container_no LIKE 'CAPW4T%')");
    $conn->query("DELETE FROM seat_assignments WHERE replace_reason LIKE 'CAPW4T%'");
    $conn->query("DELETE FROM equipment_documents WHERE doc_no LIKE 'CAPW4T%'");
    // الحذفُ ورقةً فأبًا — FK الذاتي RESTRICT يرفض حذفَ أبٍ وله أبناء
    foreach (array('مشغّل', 'معدة', 'نوع', 'مورد', 'رئيسية') as $lvl) {
        $conn->query("DELETE FROM op_containers WHERE container_no LIKE 'CAPW4T%' AND level = '{$lvl}'");
    }
    $conn->query("DELETE l FROM supplier_contract_lines l
                   JOIN supplier_contracts h ON h.id = l.contract_id WHERE h.notes LIKE 'CAPW4T%'");
    $conn->query("DELETE FROM supplier_contracts WHERE notes LIKE 'CAPW4T%'");
    $conn->query("DELETE FROM contract_commitments WHERE commitment_code LIKE 'CAPW4T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0005 · الموجة ④ — الخدماتُ الثماني ══\n");

// ═══ البذر المشترك ═══
$CC_ID = intval($conn->query("SELECT id FROM contracts WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0
                              ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$SUP2 = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} AND id <> {$SUP} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
// معدتان بلا وثائقِ أهليةٍ منتهيةٍ حية — فحارسُ الجاهزية يُختبر ببذرنا وحدَه
$cleanEq = $conn->query("SELECT e.id FROM equipments e
    WHERE NOT EXISTS (SELECT 1 FROM equipment_documents d
                       WHERE d.subject_type = 'equipment' AND d.subject_id = e.id
                         AND d.doc_type IN ('استمارة','تأمين','فحص دوري','رخصة تشغيل'))
    ORDER BY e.id LIMIT 2")->fetch_all(MYSQLI_ASSOC);
$EQ = intval($cleanEq[0]['id']);
$EQ2 = intval($cleanEq[1]['id']);
$conn->query("INSERT INTO contract_commitments
    (company_id, commitment_code, party_scope, contract_ref, commitment_type, equipment_type_code,
     primary_units_contracted, standby_units_required, standby_units_allowed,
     qty_per_primary_unit_month, measure_code, valid_from)
    VALUES ({$CO}, 'CAPW4T-OBL', 'client', {$CC_ID}, 'equipment_count', 'CAPW4T_EXC', 2, 1, 2, 600, 'hour', '2042-01-01')");
$OBL = intval($conn->insert_id);
$h = SCS::createContract($conn, $gate, $CO, array('supplier_id' => $SUP, 'client_contract_id' => $CC_ID,
    'start_date' => '2042-01-01', 'end_date' => '2042-12-31', 'currency' => 'USD', 'notes' => 'CAPW4T عقد'), $ACTOR);
$SC = intval($h['contract_id']);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, contract_id, obl_id, unit_type, cap_qty)
              VALUES ({$CO}, 'CAPW4T-ROOT', 'رئيسية', {$CC_ID}, {$OBL}, 'hour', 2000)");
$ROOT = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, obl_id, supplier_id, unit_type, cap_qty)
              VALUES ({$CO}, 'CAPW4T-SUP', 'مورد', {$ROOT}, {$CC_ID}, {$OBL}, {$SUP}, 'hour', 1500)");
$SUPC = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, obl_id, unit_type, cap_qty, seat_no)
              VALUES ({$CO}, 'CAPW4T-S1', 'معدة', {$SUPC}, {$CC_ID}, {$OBL}, 'hour', 700, 9941)");
$S1 = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, obl_id, unit_type, cap_qty, seat_no)
              VALUES ({$CO}, 'CAPW4T-S2', 'معدة', {$SUPC}, {$CC_ID}, {$OBL}, 'hour', 700, 9942)");
$S2 = intval($conn->insert_id);

// ═══ ① CAP-18 ═══
head('① ObligationDistributor — واعٍ بالحالة وبلا إدخالٍ للمشتق');
$r = DIST::saveShare($conn, $gate, $CO, $SC, array('work_model' => 'hour', 'unit' => 'ساعة',
    'unit_price' => 100, 'primary_units_committed' => 1), $ACTOR);
check(!$r['ok'] && intval($r['code']) === 422, 'حصةٌ بلا التزامٍ → 422: ' . $r['reason']);
$r = DIST::saveShare($conn, $gate, $CO, $SC, array('work_model' => 'hour', 'unit' => 'ساعة',
    'unit_price' => 100, 'primary_units_committed' => 1, 'contract_obligation_ref' => $OBL,
    'hours_month' => 600), $ACTOR);
check(!$r['ok'] && intval($r['code']) === 422 && strpos($r['reason'], 'المشتقة') !== false,
      'كميةٌ مشتقةٌ أُدخلت يدويًّا → 422: ' . $r['reason']);
$r = DIST::saveShare($conn, $gate, $CO, $SC, array('work_model' => 'hour', 'unit' => 'ساعة',
    'unit_price' => 100, 'primary_units_committed' => 2, 'contract_obligation_ref' => $OBL,
    'standby_units_allowed' => 2, 'replacement_sla_hours' => 48), $ACTOR);
check($r['ok'] && $r['derived']['share_qty_month'] == 1200.0,
      'حصةُ 2 حُفظت والمشتقُّ حُسب (1200 hour شهريًّا): ' . $r['reason']);
$LINE = intval($r['line_id']);
$r = DIST::saveShare($conn, $gate, $CO, $SC, array('work_model' => 'ton', 'unit' => 'طن',
    'unit_price' => 50, 'primary_units_committed' => 1, 'contract_obligation_ref' => $OBL), $ACTOR);
check(!$r['ok'] && intval($r['code']) === 409, 'Σ 3 فوق المتعاقد 2 → 409 قبل الكتابة (C1)');

// ═══ ② CAP-19 ═══
head('② SeatAssignmentService — الجاهزيةُ والاستبدال (C3)');
$conn->query("INSERT INTO equipment_documents (company_id, subject_type, subject_id, doc_type, doc_no, expiry_date, status)
              VALUES ({$CO}, 'equipment', {$EQ2}, 'رخصة تشغيل', 'CAPW4T-DOC', '2041-01-01', 'منتهية')");
$r = SEAT::assign($gate, $CO, $S1, $EQ2, array('date_from' => '2042-01-01', 'supplier_contract_line_id' => $LINE), $ACTOR);
check(!$r['ok'] && intval($r['code']) === 403, 'معدةٌ بوثيقةِ أهليةٍ منتهية → 403: ' . $r['reason']);
$r = SEAT::assign($gate, $CO, $S1, $EQ, array('date_from' => '2042-01-01',
    'supplier_contract_line_id' => $LINE, 'replace_reason' => 'CAPW4T تخصيصٌ أول'), $ACTOR);
check($r['ok'], 'التخصيصُ الأول نجح: ' . $r['reason']);
$A1 = intval($r['assignment_id']);
$lineBefore = $conn->query("SELECT primary_units_committed, unit_price FROM supplier_contract_lines WHERE id={$LINE}")->fetch_assoc();
$conn->query("DELETE FROM equipment_documents WHERE doc_no LIKE 'CAPW4T%'");
$r = SEAT::replace($gate, $CO, $A1, $EQ2, array('end_date' => '2042-03-10', 'date_from' => '2042-03-13',
    'replace_reason' => 'CAPW4T عطلٌ مطوّل'), $ACTOR);
$lineAfter = $conn->query("SELECT primary_units_committed, unit_price FROM supplier_contract_lines WHERE id={$LINE}")->fetch_assoc();
check($r['ok'] && intval($r['uncovered_days']) === 2 && $r['sla_met'] === true,
      'C3: الاستبدالُ بتاريخي الخروج والدخول وزمنِ عدم التغطية (يومان = 48س = المهلة بالضبط): ' . $r['reason']);
check($lineBefore == $lineAfter, 'C3: الحصةُ وقيمةُ العقد لم تتغيرا بالاستبدال');
$old = $conn->query("SELECT state FROM seat_assignments WHERE id={$A1}")->fetch_assoc();
check($old['state'] === 'ended', 'التخصيصُ القديم أُنهي لا حُذف — التاريخُ محفوظ');

// ═══ ③ CAP-20 · الدرجات ═══
head('③ سلّمُ التغطية — C8 · C9 · C10');
$mk = function ($level, $covering, $note) use ($gate, $CO, $ACTOR, $S2, $SUP) {
    // الدرجةُ ①: المدةُ ≤ مهلةِ الإحلال (48س = يومان) — وغيرُها بمدةٍ أطول
    $to = $level === 'own_standby' ? '2042-05-02' : '2042-05-03';
    return COV::create($gate, $CO, array(
        'level' => $level, 'covered_seat_id' => $S2,
        'covering_supplier_id' => $covering,
        'reason_code' => 'breakdown', 'reason_ref' => 'TKT-CAPW4T',
        'valid_from' => '2042-05-01', 'valid_to' => $to,
        'estimated_hours' => 100, 'note' => $note), $ACTOR);
};
$r = $mk('own_standby', 0, 'CAPW4T د①');
check($r['ok'] && $r['impact'] !== null, 'الدرجةُ ① أُنشئت بأثرٍ محسوبٍ قبل الإرسال (§6.1-⑤)');
$C1ID = intval($r['cov_id']);
$r = COV::approve($gate, $C1ID, 'movement_mgr', $ACTOR);
check($r['ok'] && $r['state'] === 'approved', 'C8: مديرُ الحركة وحدَه يعتمد الدرجةَ ①');
$r = $mk('cross_supplier', $SUP2, 'CAPW4T د②');
$C2ID = intval($r['cov_id']);
COV::approve($gate, $C2ID, 'movement_mgr', $ACTOR);
$r = COV::approve($gate, $C2ID, 'maintenance_officer', $ACTOR);
check($r['ok'] && $r['state'] === 'pending_approvals' && in_array('operators_officer', $r['missing'], true),
      'C9: الدرجةُ ② بموافقتين تبقى معلقةً حتى تكتمل الثلاث: ' . $r['reason']);
$r = COV::approve($gate, $C2ID, 'operators_officer', $ACTOR);
check($r['ok'] && $r['state'] === 'approved', 'C9: الثالثةُ تُكمل فيُعتمد');
$r = $mk('source_change', $SUP2, 'CAPW4T د③');
$C3ID = intval($r['cov_id']);
$r = COV::approve($gate, $C3ID, 'movement_mgr', $ACTOR);
check(!$r['ok'] && intval($r['code']) === 403, 'C10: الدرجةُ ③ لا يملكها مديرُ الحركة → 403: ' . $r['reason']);
$r = COV::approve($gate, $C3ID, 'ops_mgr', $ACTOR);
check(!$r['ok'] && intval($r['code']) === 403, 'C10: ولا مديرُ التشغيل → 403');
COV::approve($gate, $C3ID, 'finance_mgr', $ACTOR);
$r = COV::approve($gate, $C3ID, 'executive_mgr', $ACTOR);
check($r['ok'] && $r['state'] === 'approved', 'الدرجةُ ③ بالماليِّ والتنفيذيِّ تُعتمد');
// قيودُ §6.1 المتممة
$r = COV::create($gate, $CO, array('level' => 'own_standby', 'covered_seat_id' => $S2,
    'covering_supplier_id' => $SUP, 'reason_code' => 'breakdown',
    'valid_from' => '2042-05-01', 'valid_to' => '2042-05-03', 'note' => 'CAPW4T بلا أثر'), $ACTOR);
check(!$r['ok'] && intval($r['code']) === 422, 'بلا أثرٍ محسوبٍ (ساعاتٍ مقدَّرة) → 422 (§6.1-⑤)');
$r = COV::zeroFailedGap();
check(intval($r['code']) === 403, '§6.1-⑥: محاولةُ تصفير عجز المتعطل → 403');
$r = COV::modifyOriginalShare();
check(intval($r['code']) === 423, 'محاولةُ تعديل الحصة الأصلية عبر التغطية → 423');

// ═══ ④ CAP-23 · C11 ═══
head('④ محاسبةُ §7 — C11');
$r = COV::settle($gate, $C2ID, 100, 55.0, 'USD');
check($r['ok'] && intval($r['lines']) === 4, 'أربعةُ بنودٍ ظاهرةٍ قُيّدت: ' . $r['reason']);
$rows = $conn->query("SELECT party, effect, amount FROM coverage_settlement_lines
    WHERE cov_id = {$C2ID} ORDER BY ln_id")->fetch_all(MYSQLI_ASSOC);
$byParty = array(); foreach ($rows as $x) { $byParty[$x['party']] = $x; }
check($byParty['client']['effect'] === 'billable', 'C11: العميلُ فُوتر كاملًا');
check($byParty['failed_supplier']['effect'] === 'gap_kept', 'C11: عجزُ المتعطل باقٍ وجزاؤه بقاعدة عقده');
check($byParty['covering_supplier']['effect'] === 'exceptional_line'
   && abs((float) $byParty['covering_supplier']['amount'] - 5500.0) < 0.01,
      'C11: بندُ المغطِّي الاستثنائيُّ بسعره (100×55) — لا حصةٌ تُرفع');
check($byParty['operator']['effect'] === 'entitlement', 'C11: المشغّلُ يستحق بعقده');
$lineNow = $conn->query("SELECT primary_units_committed FROM supplier_contract_lines WHERE id={$LINE}")->fetch_assoc();
check(intval($lineNow['primary_units_committed']) === 2, 'C11: حصةُ المغطِّي التعاقديةُ لم ترتفع');
$r = COV::settle($gate, $C2ID, 100);
check(!$r['ok'] && intval($r['code']) === 409, 'تسويةٌ ثانيةٌ → 409 — لا ازدواج');

// ═══ ⑤ CAP-22 · C18/C19 ═══
head('⑤ المؤشراتُ التسعة — C18 · C19');
LED::appendLine($gate, array('unit_record_id' => $REC, 'unit_record_version' => 1,
    'supplier_share_id' => $SUPC, 'supplier_contract_line_id' => $LINE, 'contract_seat_id' => $S1,
    'effect_type' => 'supplier_share', 'effect_target_type' => 'supplier', 'effect_target_ref' => 'sup:' . $SUP,
    'measure_code' => 'hour', 'qty' => 800, 'period' => '2042-05', 'role_snapshot' => 'primary'), $ACTOR);
LED::appendLine($gate, array('unit_record_id' => $REC, 'unit_record_version' => 2,
    'supplier_share_id' => $SUPC, 'supplier_contract_line_id' => $LINE, 'contract_seat_id' => $S2,
    'effect_type' => 'supplier_share', 'effect_target_type' => 'supplier', 'effect_target_ref' => 'sup:' . $SUP,
    'measure_code' => 'hour', 'qty' => 180, 'period' => '2042-05', 'role_snapshot' => 'standby'), $ACTOR);
LED::appendLine($gate, array('unit_record_id' => $REC, 'unit_record_version' => 3,
    'supplier_contract_line_id' => $LINE, 'contract_seat_id' => $S2, 'coverage_id' => $C2ID,
    'effect_type' => 'exceptional_coverage', 'effect_target_type' => 'supplier', 'effect_target_ref' => 'sup:' . $SUP,
    'measure_code' => 'hour', 'qty' => 120, 'period' => '2042-05'), $ACTOR);
$ind = AGG::nineIndicators($gate, $LINE, '2042-05');
check(abs($ind['planned'] - 1200) < 0.01, '① المخططُ مشتقٌّ (2×600=1200) لا مدخل');
check(abs($ind['executed_primary'] - 800) < 0.01 && abs($ind['executed_standby'] - 180) < 0.01,
      '②③ الأساسيُّ 800 والاحتياطيُّ المفعَّل 180 من الدفتر');
check(abs($ind['executed_share_total'] - 980) < 0.01,
      'C18: ④ تنفيذُ الحصة = ②+③ = 980 — الاحتياطيُّ يُحتسب ولا يرفع المستهدف');
check(abs($ind['share_gap'] - 220) < 0.01 && abs($ind['share_execution_pct'] - 81.7) < 0.1,
      '⑤⑥ العجزُ 220 والنسبةُ 81.7٪');
check(abs($ind['exceptional_coverage_given'] - 120) < 0.01,
      '⑦ التغطيةُ المعطاةُ 120 بندٌ مستقلٌّ — لا تدخل ④ ولا ترفع ⑥');
$r = AGG::assertNotCountingCoverage('exceptional_coverage', true);
check(!$r['ok'] && intval($r['code']) === 403, 'C19: إدراجُ التغطية في تنفيذ الحصة → 403: ' . $r['reason']);

// ═══ ⑥ CAP-25 · C12 ═══
head('⑥ الحصةُ بعد الإقفال — C12');
$conn->query("INSERT INTO monthly_performance (company_id, contract_id, container_id, period, contract_hours, state, closed_at)
              VALUES ({$CO}, {$CC_ID}, {$S1}, '2042-05', 1200, 'closed', NOW())");
$r = SCS::saveLine($conn, $gate, $CO, $SC, array('line_id' => $LINE, 'work_model' => 'hour', 'unit' => 'ساعة',
    'unit_price' => 100, 'contract_obligation_ref' => $OBL, 'primary_units_committed' => 3,
    'standby_units_allowed' => 2, 'replacement_sla_hours' => 48), $ACTOR);
check(!$r['ok'] && intval($r['code']) === 423,
      'C12: تعديلُ الحصة بعد إقفال 2042-05 → 423: ' . $r['reason']);
$still = $conn->query("SELECT primary_units_committed FROM supplier_contract_lines WHERE id={$LINE}")->fetch_assoc();
check(intval($still['primary_units_committed']) === 2, 'الانحرافُ التاريخيُّ محفوظ — الحصةُ 2 كما كانت');

// ═══ ⑦ CAP-21 · C13 ═══
head('⑦ GapMonitor — يوميٌّ بالساعات وتصعيدٌ آلي (C13)');
$conn->query("UPDATE seat_assignments SET state='ended', date_to='2042-03-10'
              WHERE container_id IN ({$S1},{$S2}) AND state='active'");
$r = GAP::runDaily($conn, $gate, $CO);
$g = null; foreach ($r['gaps'] as $x) { if ((int) $x['obl_id'] === $OBL) { $g = $x; } }
check($g !== null && intval($g['gap_units']) === 2 && abs($g['gap_hours'] - 1200) < 0.01,
      'C13: الفجوةُ بالساعات (وحدتان × 600 = 1200 ساعة) لا بالعدد فقط');
$conn->query("UPDATE capacity_gap_watch SET opened_on = DATE_SUB(CURDATE(), INTERVAL 5 DAY)
              WHERE obl_id = {$OBL} AND closed_on IS NULL");
$r = GAP::runDaily($conn, $gate, $CO);
$w = $conn->query("SELECT state, escalated_ops_at FROM capacity_gap_watch
                    WHERE obl_id = {$OBL} AND closed_on IS NULL")->fetch_assoc();
check($w && $w['state'] === 'escalated_ops' && $w['escalated_ops_at'] !== null,
      'C13: تجاوزُ المهلة (5 أيام > 3) → تصعيدٌ آليٌّ لمدير التشغيل');
$ev = intval($conn->query("SELECT COUNT(*) n FROM ems_business_events
    WHERE source_module = 'capacity' AND entity_id = {$OBL}")->fetch_assoc()['n']);
check($ev >= 2, 'الأحداثُ (فتحٌ + تصعيد) في الجذر المحايد عبر EventPublisher (' . $ev . ')');
// التغطيةُ تكتمل بتخصيصين فعّالين اليوم (المرقبُ يقيس يومَه لا المستقبل)
$conn->query("UPDATE seat_assignments SET state='ended', date_to='2042-12-31'
              WHERE container_id IN ({$S1},{$S2}) AND state='active'");
$ra = SEAT::assign($gate, $CO, $S1, $EQ, array('date_from' => '2026-01-01', 'replace_reason' => 'CAPW4T عودة'), $ACTOR);
$rb = SEAT::assign($gate, $CO, $S2, $EQ2, array('date_from' => '2026-01-01', 'replace_reason' => 'CAPW4T عودة'), $ACTOR);
check($ra['ok'] && $rb['ok'], 'تخصيصا العودة نجحا: ' . $ra['reason'] . ' · ' . $rb['reason']);
$r = GAP::runDaily($conn, $gate, $CO);
$w = $conn->query("SELECT state, closed_on FROM capacity_gap_watch
                    WHERE obl_id = {$OBL} ORDER BY gap_id DESC LIMIT 1")->fetch_assoc();
check($w && $w['state'] === 'closed' && $w['closed_on'] !== null, 'التغطيةُ اكتملت → الفجوةُ أُقفلت بيومها');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
