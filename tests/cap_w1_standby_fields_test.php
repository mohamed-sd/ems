<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0005 · الموجة ① — حقولُ الالتزام والاحتياطي (CAP-01 §4/§8 · CAP-01→06)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/cap_w1_standby_fields_test.php
 *
 * ما يُثبته:
 *   ① البنية: حقولُ §8.1 على contract_commitments وop_containers ·
 *      وحقولُ §8.2 على supplier_contract_lines · وخطةُ §8.3 على seat_assignments.
 *   ② المفتاحُ المشروط uq_obl_type_from: التزامان لنوعٍ واحدٍ بسريانٍ واحدٍ
 *      مستحيلان — وصفوفُ الالتزامات العامة (بلا نوع) خارج القيد.
 *   ③ CAP-02: حصةُ موردٍ بمرجع التزامٍ ميت → 422 «لا حصةَ بلا التزام».
 *   ④ CAP-03: الاحتياطيُّ pending بساعاتٍ مخططة → مرفوضٌ بنيويًّا (CHECK).
 *   ⑤ CAP-04 · C17: تجاوزُ السقف → 409 بعرض السقفين · وبلا سقفٍ → 422.
 *   ⑥ CAP-06 · DEC-CAP-A: مقابلُ احتياطيٍّ بلا نصٍّ عقدي → 422 — وبنصٍّ يُقبل.
 *   ⑦ CAP-05: الشاشتان تُدخلان الحقولَ ولا تفترضان مقابلًا للاحتياطي.
 *
 * البذرُ معزول: أكوادٌ CAPW1T وتواريخ 2042 — يُكنس كاملًا قبل وبعد.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractStateMachine.php';
require_once dirname(__DIR__) . '/app/Services/Contract/SupplierContractService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/StandbyCapService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\SupplierContractService as SCS;
use App\Services\Contract\StandbyCapService as CAP;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999905;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn) {
    $conn->query("DELETE FROM seat_assignments WHERE replace_reason LIKE 'CAPW1T%'");
    $conn->query("DELETE l FROM supplier_contract_lines l
                   JOIN supplier_contracts h ON h.id = l.contract_id
                  WHERE h.notes LIKE 'CAPW1T%'");
    $conn->query("DELETE FROM supplier_contracts WHERE notes LIKE 'CAPW1T%'");
    $conn->query("DELETE FROM contract_commitments WHERE commitment_code LIKE 'CAPW1T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0005 · الموجة ① — حقولُ الالتزام والاحتياطي ══\n");

// ═══ ① البنية ═══
head('① البنيةُ — الحقولُ في مواضعها الأربعة');
$colsOf = function ($table) use ($conn) {
    $out = array();
    $r = $conn->query("SELECT column_name AS c FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = '{$table}'");
    while ($x = $r->fetch_assoc()) { $out[] = strtolower($x['c']); }
    return $out;
};
$cc = $colsOf('contract_commitments');
$need81 = array('equipment_type_code', 'primary_units_contracted', 'standby_units_required',
    'standby_units_allowed', 'qty_per_primary_unit_month', 'measure_code',
    'standby_compensation_type', 'standby_activation_rule', 'standby_hours_treatment',
    'valid_from', 'valid_to', 'obl_type_uq_key');
check(count(array_diff($need81, $cc)) === 0, 'حقولُ §8.1 كاملةً على contract_commitments (' . count($need81) . ')');
$oc = $colsOf('op_containers');
check(in_array('primary_units_contracted', $oc, true) && in_array('standby_units_allowed', $oc, true),
      'أعدادُ درجة «نوع» على op_containers — والسياسةُ في العقد وحدَه (المصدرُ الواحد §5-⑦)');
$sl = $colsOf('supplier_contract_lines');
$need82 = array('contract_obligation_ref', 'equipment_type_code', 'primary_units_committed',
    'standby_units_required', 'standby_units_allowed', 'replacement_sla_hours',
    'standby_activation_terms', 'standby_payment_terms');
check(count(array_diff($need82, $sl)) === 0, 'حقولُ §8.2 كاملةً على supplier_contract_lines (' . count($need82) . ')');
$sa = $colsOf('seat_assignments');
$need83 = array('planned_qty_month', 'planned_qty_total', 'measure_code', 'activation_state', 'supplier_contract_line_id');
check(count(array_diff($need83, $sa)) === 0, 'حقولُ خطة §8.3 كاملةً على seat_assignments (' . count($need83) . ')');
$uq = intval($conn->query("SELECT COUNT(*) n FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'contract_commitments'
      AND index_name = 'uq_obl_type_from' AND non_unique = 0")->fetch_assoc()['n']);
check($uq > 0, 'uq_obl_type_from فهرسٌ فريدٌ مشروطٌ على عمودٍ مولَّد (DEC-CAP-C)');
$ck = intval($conn->query("SELECT COUNT(*) n FROM information_schema.check_constraints
    WHERE constraint_schema = DATABASE() AND constraint_name = 'ck_sa_standby_zero'")->fetch_assoc()['n']);
check($ck > 0, 'ck_sa_standby_zero قائمٌ — الاحتياطيُّ صفرُ ساعاتٍ قبل التفعيل بنيويًّا');

// ═══ ② المفتاحُ المشروط ═══
head('② UQ(contract, equipment_type_code, valid_from) — مشروطٌ لا شامل');
$CC_ID = intval($conn->query("SELECT id FROM contracts WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0
                              ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$ins = function ($code, $etype, $from) use ($conn, $CO, $CC_ID) {
    $e = $etype === null ? 'NULL' : "'{$etype}'";
    $f = $from === null ? 'NULL' : "'{$from}'";
    return $conn->query("INSERT INTO contract_commitments
        (company_id, commitment_code, party_scope, contract_ref, commitment_type,
         equipment_type_code, primary_units_contracted, standby_units_required, standby_units_allowed,
         qty_per_primary_unit_month, measure_code, valid_from)
        VALUES ({$CO}, '{$code}', 'client', {$CC_ID}, 'equipment_count',
                {$e}, 6, 2, 3, 600, 'hour', {$f})");
};
check($ins('CAPW1T-A1', 'CAPW1T_EXC', '2042-01-01'), 'التزامُ نوعٍ أولُ يُقبل');
$OBL_ID = intval($conn->insert_id);
check(!$ins('CAPW1T-A2', 'CAPW1T_EXC', '2042-01-01'), 'التزامٌ ثانٍ للنوع نفسِه بالسريان نفسِه → مرفوضٌ بنيويًّا (Duplicate)');
check($ins('CAPW1T-A3', 'CAPW1T_EXC', '2042-06-01'), 'C20: السريانُ المختلف فترةٌ جديدةٌ تُقبل والماضي كما هو — التعديلُ فترةٌ لا مسُّ ماضٍ (§5-④)');
check($ins('CAPW1T-B1', null, null) && $ins('CAPW1T-B2', null, null),
      'التزامان عامّان بلا نوعٍ خارج القيد — الفهرسُ مشروطٌ لا خانق');

// ═══ ③ CAP-02: لا حصةَ بلا التزام ═══
head('③ حصةُ المورد بمرجع الالتزام — «لا حصةَ بلا التزامٍ في عقدٍ نافذ»');
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$r = SCS::createContract($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'client_contract_id' => $CC_ID,
    'start_date' => '2042-01-01', 'end_date' => '2042-12-31',
    'currency' => 'USD', 'notes' => 'CAPW1T عقدُ اختبار'), $ACTOR);
check($r['ok'], 'عقدُ موردٍ صوريٌّ أُنشئ');
$SCID = intval($r['contract_id']);

$r = SCS::saveLine($conn, $gate, $CO, $SCID, array(
    'work_model' => 'hour', 'unit' => 'ساعة', 'unit_price' => 100,
    'contract_obligation_ref' => 99999999), $ACTOR);
check(!$r['ok'] && intval($r['code']) === 422, 'مرجعُ التزامٍ ميت → 422: ' . $r['reason']);

$r = SCS::saveLine($conn, $gate, $CO, $SCID, array(
    'work_model' => 'hour', 'unit' => 'ساعة', 'unit_price' => 100,
    'contract_obligation_ref' => $OBL_ID, 'equipment_type_code' => 'CAPW1T_EXC',
    'primary_units_committed' => 3, 'standby_units_required' => 1, 'standby_units_allowed' => 2,
    'replacement_sla_hours' => 48, 'standby_activation_terms' => 'عند التعطل بإذن مدير الحركة'), $ACTOR);
check($r['ok'], 'بندٌ بمرجع التزامٍ حيٍّ وسقفِ احتياطي 2 حُفظ');
$LINE_ID = intval($r['line_id']);
$row = $conn->query("SELECT standby_payment_terms FROM supplier_contract_lines WHERE id={$LINE_ID}")->fetch_assoc();
check($row && $row['standby_payment_terms'] === null,
      'مقابلُ الاحتياطي غيرُ المدخل بقي NULL — لا يُفترض (DEC-CAP-A)');

// ═══ ④ CAP-03: الاحتياطيُّ صفرٌ قبل التفعيل ═══
head('④ خطةُ المعدات — الاحتياطيُّ pending بساعاتٍ مرفوضٌ بنيويًّا');
$CONT = intval($conn->query("SELECT id FROM op_containers WHERE company_id={$CO} AND level='معدة'
                             ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$EQ = intval($conn->query("SELECT id FROM equipments ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$bad = $conn->query("INSERT INTO seat_assignments
    (company_id, container_id, equipment_id, date_from, assignment_role, activation_state,
     planned_qty_month, measure_code, supplier_contract_line_id, replace_reason)
    VALUES ({$CO}, {$CONT}, {$EQ}, '2042-01-01', 'احتياطي', 'pending', 600, 'hour', {$LINE_ID}, 'CAPW1T')");
check(!$bad, 'احتياطيٌّ pending بـ600 ساعةٍ مخططة → CHECK يرفض: ' . $conn->error);
$good = $conn->query("INSERT INTO seat_assignments
    (company_id, container_id, equipment_id, date_from, assignment_role, activation_state,
     planned_qty_month, measure_code, supplier_contract_line_id, replace_reason)
    VALUES ({$CO}, {$CONT}, {$EQ}, '2042-01-01', 'احتياطي', 'pending', 0, 'hour', {$LINE_ID}, 'CAPW1T ①')");
check($good, 'C2: احتياطيٌّ pending بصفر ساعاتٍ يُقبل ولا يدخل Σ ولا يُحتسب مدفوعًا بلا نص — تغطيةُ جاهزيةٍ لا وحدةٌ مباعة (§4-②)');

// ═══ ⑤ CAP-04 · C17: السقفان ═══
head('⑤ C17 — سقفُ الاحتياطي بعرض السقفين');
$r = CAP::checkRegistration($gate, $CO, $LINE_ID, 1);
check($r['ok'] && $r['supplier_cap'] === 2 && $r['client_cap'] === 3,
      'احتياطيٌّ ثانٍ ضمن السقفين (مورد 2/2 · عميل 2/3): ' . $r['reason']);
$conn->query("INSERT INTO seat_assignments
    (company_id, container_id, equipment_id, date_from, assignment_role, activation_state,
     planned_qty_month, supplier_contract_line_id, replace_reason)
    VALUES ({$CO}, {$CONT}, {$EQ}, '2042-02-01', 'احتياطي', 'pending', 0, {$LINE_ID}, 'CAPW1T ②')");
$r = CAP::checkRegistration($gate, $CO, $LINE_ID, 1);
check(!$r['ok'] && intval($r['code']) === 409, 'احتياطيٌّ ثالثٌ والمسموحُ اثنان → 409 (C17)');
check(strpos($r['reason'], 'سقفُ عقد المورد: 2') !== false && strpos($r['reason'], 'سقفُ عقد العميل: 3') !== false,
      'الرسالةُ تعرض السقفين معًا: ' . $r['reason']);

// بلا سقفٍ معرَّف → 422
$r = SCS::saveLine($conn, $gate, $CO, $SCID, array(
    'work_model' => 'ton', 'unit' => 'طن', 'unit_price' => 50,
    'contract_obligation_ref' => $OBL_ID), $ACTOR);
$NOCAPLINE = intval($r['line_id']);
$r = CAP::checkRegistration($gate, $CO, $NOCAPLINE, 1);
check(!$r['ok'] && intval($r['code']) === 422, 'بندٌ بلا سقفٍ معرَّفٍ → 422 fail-closed: ' . $r['reason']);

// ═══ ⑥ CAP-06 · DEC-CAP-A ═══
head('⑥ حارسُ DEC-CAP-A — لا مقابلَ للاحتياطي بلا نصٍّ عقدي');
$r = CAP::compensationBasisOrFail('client', array('standby_compensation_type' => null));
check(!$r['ok'] && intval($r['code']) === 422, 'عقدُ عميلٍ بلا نصٍّ → 422: ' . $r['reason']);
$r = CAP::compensationBasisOrFail('client', array('standby_compensation_type' => 'none'));
check(!$r['ok'] && intval($r['code']) === 422, '«بلا مقابل» صريحةٌ → 422 أيضًا — لا بندَ إيراد');
$r = CAP::compensationBasisOrFail('client', array('standby_compensation_type' => 'readiness_allowance'));
check($r['ok'] && $r['compensation_type'] === 'readiness_allowance', 'بدلُ جاهزيةٍ منصوصٌ → يُقبل');
$r = CAP::compensationBasisOrFail('supplier', array('standby_payment_terms' => null, 'standby_basis' => 'none', 'standby_rate' => null));
check(!$r['ok'] && intval($r['code']) === 422, 'عقدُ موردٍ بلا نصٍّ → 422');
$r = CAP::compensationBasisOrFail('supplier', array('standby_payment_terms' => 'بدلُ جاهزيةٍ شهري', 'standby_basis' => 'rate', 'standby_rate' => 25));
check($r['ok'], 'نصٌّ وأساسٌ ومعدلٌ → يُقبل');

// ═══ ⑦ CAP-05: الشاشتان ═══
head('⑦ الشاشتان تُدخلان الحقولَ ولا تفترضان');
$src = file_get_contents(dirname(__DIR__) . '/Clients/contract_commitments.php');
check(strpos($src, 'standby_compensation_type') !== false
   && strpos($src, 'لم يُنَصَّ في العقد') !== false,
      'شاشةُ التزامات العميل: الحقولُ حاضرةٌ والمقابلُ بلا قيمةٍ افتراضية');
check(strpos($src, 'qty_per_primary_unit_month') !== false && strpos($src, 'standby_hours_treatment') !== false,
      'حقولُ §8.1 السبعةُ كلُّها في الفورم');
$src = file_get_contents(dirname(__DIR__) . '/Suppliers/supplier_contract_lines.php');
check(strpos($src, 'contract_obligation_ref') !== false && strpos($src, 'standby_payment_terms') !== false,
      'شاشةُ بنود المورد: مرجعُ الالتزام وحقولُ §8.2 حاضرة');
check(strpos($src, 'SCS::saveLine') !== false, 'الحفظُ عبر الخدمة — الحارسُ في موضعٍ واحد');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
