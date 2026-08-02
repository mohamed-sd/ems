<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0005 · الموجة ③ — قلبُ الأرصدة والقيودُ في القاعدة (CAP-13→17)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/cap_w3_balance_test.php
 *
 * ما يُثبته:
 *   ① البنية: plan_state · obl_id · القيودُ المولَّدة · capacity_shadow_diffs.
 *   ② CAP-13: BalanceCalculator يحسب من الدفتر والإعكاسات **ولا يقرأ عمودَ
 *      رصيدٍ مخزَّنًا** — عمودٌ مسمومٌ بقيمةٍ كاذبةٍ لا يغيّر الحساب.
 *   ③ CAP-14: rebuildContainerCache يعيد بناءَ العمود مخبأً من الدفتر.
 *   ④ CAP-15: العلمُ columns الآن · الظلُّ يرصد الفرقَ صفًّا يوميًّا ·
 *      وميزانُ القلب غيرُ مؤهلٍ ما دام فرقٌ في الأربعة عشر يومًا.
 *   ⑤ الكتابةُ المزدوجة: consume() تخصم العمودَ وتقيّد سطرَ الدفتر معًا —
 *      والعطالةُ تمنع الثاني في المسارين.
 *   ⑥ C1/C15/C16: قيدُ Σ الواعي بالحالة — 409 بقيمة الفارق · المسودةُ تُحفظ
 *      بفجوتها · والاعتمادُ يُرفض إلا بقرار استثناءٍ موقَّع.
 *   ⑦ C21: مقعدٌ لالتزامٍ غيرِ التزامِ أبيه → يُرفض بقيد FK المركّب ·
 *      وUQ(obl, seat_no) · وC4: تخصيصان مفتوحان فعّالان → مرفوض والاحتياطيُّ
 *      pending خارج القيد.
 *
 * البذرُ معزول: أكوادٌ CAPW3T وسجلٌّ صوري 99942043 — يُكنس قبل وبعد.
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
require_once dirname(__DIR__) . '/app/Services/Capacity/BalanceCalculator.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/CapacitySourceService.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/SigmaGuard.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractStateMachine.php';
require_once dirname(__DIR__) . '/app/Services/Contract/SupplierContractService.php';
require_once dirname(__DIR__) . '/app/Services/Operations/OperationalTransformService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Capacity\CapacityLedgerService as LED;
use App\Services\Capacity\BalanceCalculator as BAL;
use App\Services\Capacity\CapacitySourceService as SRC;
use App\Services\Capacity\SigmaGuard as SIG;
use App\Services\Contract\SupplierContractService as SCS;
use App\Services\Operations\OperationalTransformService as OTS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999903; $REC = 99942043;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn, $REC) {
    $conn->query("DELETE FROM capacity_shadow_diffs
                   WHERE container_id IN (SELECT id FROM op_containers WHERE container_no LIKE 'CAPW3T%')");
    $conn->query("DELETE FROM capacity_consumption_ledger WHERE unit_record_id = {$REC} AND reverses_led_id IS NOT NULL");
    $conn->query("DELETE FROM capacity_consumption_ledger WHERE unit_record_id = {$REC}");
    $conn->query("DELETE FROM container_consumption WHERE idem_key LIKE 'CAPW3T%'");
    $conn->query("DELETE FROM seat_assignments WHERE replace_reason LIKE 'CAPW3T%'");
    $conn->query("UPDATE op_containers SET parent_id = NULL WHERE container_no LIKE 'CAPW3T%' AND level <> 'رئيسية'");
    $conn->query("DELETE FROM op_containers WHERE container_no LIKE 'CAPW3T%' AND level <> 'رئيسية'");
    $conn->query("DELETE FROM op_containers WHERE container_no LIKE 'CAPW3T%'");
    $conn->query("DELETE l FROM supplier_contract_lines l
                   JOIN supplier_contracts h ON h.id = l.contract_id WHERE h.notes LIKE 'CAPW3T%'");
    $conn->query("DELETE FROM supplier_contracts WHERE notes LIKE 'CAPW3T%'");
    $conn->query("DELETE FROM contract_commitments WHERE commitment_code LIKE 'CAPW3T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0005 · الموجة ③ — قلبُ الأرصدة والقيود ══\n");

// ═══ ① البنية ═══
head('① البنية');
$has = function ($t, $c) use ($conn) {
    return intval($conn->query("SELECT COUNT(*) n FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = '{$t}' AND column_name = '{$c}'")->fetch_assoc()['n']) > 0;
};
check($has('contract_commitments', 'plan_state') && $has('contract_commitments', 'sigma_exception_ref'),
      'حالةُ الخطة ومرجعُ الاستثناء على الالتزام (CAP-17)');
check($has('op_containers', 'obl_id') && $has('op_containers', 'seat_obl_uq_key'),
      'obl_id ومفتاحُ UQ(obl, seat_no) المولَّد على الشجرة (CAP-16)');
check($has('seat_assignments', 'active_open_seat_key'), 'قيدُ التخصيص المفتوح الواحد (CAP-16)');
$fk = intval($conn->query("SELECT COUNT(*) n FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_oc_parent_obl'
      AND constraint_type = 'FOREIGN KEY'")->fetch_assoc()['n']);
check($fk === 1, 'قيدُ تطابق التزامِ الابن والتزامِ أبيه FK مركّبٌ في القاعدة (C21)');

// ═══ ② CAP-13 ═══
head('② BalanceCalculator — من الدفتر لا من العمود');
$CC_ID = intval($conn->query("SELECT id FROM contracts WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0
                              ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, contract_id, unit_type, cap_qty)
              VALUES ({$CO}, 'CAPW3T-ROOT', 'رئيسية', {$CC_ID}, 'hour', 100)");
$ROOT = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, unit_type, cap_qty, seat_no)
              VALUES ({$CO}, 'CAPW3T-EQ', 'معدة', {$ROOT}, {$CC_ID}, 'hour', 50, 9931)");
$EQC = intval($conn->insert_id);
check($EQC > 0, 'شجرةُ اختبارٍ صورية (رئيسية ← معدة) بُذرت');

// عمودٌ مسمومٌ بقيمةٍ كاذبة — والدفترُ هو الحقيقة
$conn->query("UPDATE op_containers SET consumed_qty = 44 WHERE id = {$EQC}");
$r = LED::appendLine($gate, array(
    'unit_record_id' => $REC, 'unit_record_version' => 1,
    'contract_seat_id' => $EQC, 'effect_type' => 'client_obligation',
    'effect_target_type' => 'client', 'effect_target_ref' => 'contract:' . $CC_ID,
    'measure_code' => 'hour', 'qty' => 9.5, 'period' => '2042-04'), $ACTOR);
check($r['ok'], 'سطرُ دفترٍ 9.5 ساعةٍ قُيّد للورقة');
$L1 = intval($r['led_id']);
$b = BAL::balanceOf($gate, $EQC);
check(abs($b['consumed'] - 9.5) < 0.001 && abs($b['remaining'] - 40.5) < 0.001,
      'الرصيدُ من الدفتر (9.5/50) — والعمودُ المسمومُ (44) لم يُقرأ (صفرُ قراءةٍ لعمود رصيد)');
$bRoot = BAL::balanceOf($gate, $ROOT);
check(abs($bRoot['consumed'] - 9.5) < 0.001, 'استهلاكُ الأم Σ ذريتها من الدفتر');
$rv = LED::reverse($gate, $L1, $ACTOR);
$b2 = BAL::balanceOf($gate, $EQC);
check($rv['ok'] && abs($b2['consumed']) < 0.001 && abs($b2['remaining'] - 50) < 0.001,
      'العكسُ أعاد الرصيدَ محسوبًا لا معدَّلًا (C26) — 0/50');
$srcCode = file_get_contents(dirname(__DIR__) . '/app/Services/Capacity/BalanceCalculator.php');
check(strpos($srcCode, 'SELECT c.cap_qty') !== false
   && !preg_match('/SELECT[^"\']*consumed_qty/u', $srcCode),
      'المصدرُ لا يقرأ عمودَ رصيدٍ مخزَّنًا — يكتبه مخبأً فقط');

// ═══ ③ CAP-14 ═══
head('③ إعادةُ بناء المخبأ');
$rb = BAL::rebuildContainerCache($gate, $CC_ID);
$col = floatval($conn->query("SELECT consumed_qty FROM op_containers WHERE id = {$EQC}")->fetch_assoc()['consumed_qty']);
check($rb['ok'] && abs($col) < 0.001, 'المخبأُ أُعيد بناؤه من الدفتر — العمودُ المسمومُ (44) صار 0 (صافي العكس)');

// ═══ ④ CAP-15 ═══
head('④ العلمُ والظل');
check(SRC::currentSource() === 'columns', 'المصدرُ الحاكم columns — والقلبُ مؤجَّلٌ بشرط الأربعة عشر يومًا');
$conn->query("UPDATE op_containers SET consumed_qty = 7 WHERE id = {$EQC}");
$sh = SRC::shadowCompare($conn, $gate, $EQC);
check($sh['diff'] && abs($sh['stored'] - 7) < 0.001 && abs($sh['ledger']) < 0.001,
      'الظلُّ رصد الفرقَ (مخزَّن 7 × دفتر 0)');
$n = intval($conn->query("SELECT COUNT(*) n FROM capacity_shadow_diffs WHERE container_id = {$EQC}")->fetch_assoc()['n']);
SRC::shadowCompare($conn, $gate, $EQC);
$n2 = intval($conn->query("SELECT COUNT(*) n FROM capacity_shadow_diffs WHERE container_id = {$EQC}")->fetch_assoc()['n']);
check($n === 1 && $n2 === 1, 'صفٌّ يوميٌّ واحدٌ لكل حاوية (uq_shadow_daily)');
$fr = SRC::flipReadiness($gate);
check(!$fr['eligible'] && $fr['days_with_diff'] >= 1, 'ميزانُ القلب: غيرُ مؤهلٍ — يومُ فرقٍ مرصود');
$conn->query("UPDATE op_containers SET consumed_qty = 0 WHERE id = {$EQC}");

// ═══ ⑤ الكتابةُ المزدوجة ═══
head('⑤ consume() تكتب العمودَ والدفترَ معًا');
$r = OTS::consume($conn, $gate, $CO, $EQC, 5.0, 'CAPW3T-consume-1',
    array('source_kind' => 'unit_entry', 'source_ref' => $REC, 'revision_no' => 2,
          'unit_type' => 'hour', 'consumed_on' => '2042-04-10', 'actor' => $ACTOR));
check($r['ok'], 'الخصمُ نجح عبر السلسلة (' . $r['levels'] . ' درجات)');
$col = floatval($conn->query("SELECT consumed_qty FROM op_containers WHERE id = {$EQC}")->fetch_assoc()['consumed_qty']);
$led = intval($conn->query("SELECT COUNT(*) n FROM capacity_consumption_ledger
    WHERE unit_record_id = {$REC} AND unit_record_version = 2")->fetch_assoc()['n']);
check(abs($col - 5) < 0.001 && $led === 1, 'العمودُ خُصم (5) وسطرُ الدفتر قُيّد (v2) — في معاملةٍ واحدة');
$r2 = OTS::consume($conn, $gate, $CO, $EQC, 5.0, 'CAPW3T-consume-1',
    array('source_kind' => 'unit_entry', 'source_ref' => $REC, 'revision_no' => 2, 'unit_type' => 'hour'));
$led2 = intval($conn->query("SELECT COUNT(*) n FROM capacity_consumption_ledger
    WHERE unit_record_id = {$REC} AND unit_record_version = 2")->fetch_assoc()['n']);
check($r2['ok'] && $r2['existing'] && $led2 === 1, 'العطالةُ في المسارين — لا خصمَ ثانيًا ولا سطرَ ثانيًا');
$sh = SRC::shadowCompare($conn, $gate, $EQC);
check(!$sh['diff'], 'الظلُّ متطابقٌ بعد الكتابة المزدوجة (5 = 5)');

// ═══ ⑥ C1/C15/C16 ═══
head('⑥ قيدُ Σ الواعي بحالة الخطة');
$conn->query("INSERT INTO contract_commitments
    (company_id, commitment_code, party_scope, contract_ref, commitment_type,
     equipment_type_code, primary_units_contracted, standby_units_allowed, standby_units_required,
     qty_per_primary_unit_month, measure_code, valid_from)
    VALUES ({$CO}, 'CAPW3T-OBL', 'client', {$CC_ID}, 'equipment_count', 'CAPW3T_EXC', 6, 3, 2, 600, 'hour', '2042-01-01')");
$OBL = intval($conn->insert_id);
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$h = SCS::createContract($conn, $gate, $CO, array('supplier_id' => $SUP, 'client_contract_id' => $CC_ID,
    'start_date' => '2042-01-01', 'end_date' => '2042-12-31', 'currency' => 'USD',
    'notes' => 'CAPW3T عقد'), $ACTOR);
$SC = intval($h['contract_id']);
SCS::saveLine($conn, $gate, $CO, $SC, array('work_model' => 'hour', 'unit' => 'ساعة', 'unit_price' => 100,
    'contract_obligation_ref' => $OBL, 'primary_units_committed' => 4), $ACTOR);
$r = SIG::check($gate, $OBL);
check($r['ok'] && $r['sigma'] === 4 && $r['gap'] === 2,
      'C15: مسودةٌ ناقصة (4/6) تُحفظ وتظهر فجوتُها 2: ' . $r['reason']);
$r = SIG::transition($gate, $OBL, 'approved');
check(!$r['ok'] && intval($r['code']) === 409, 'C16: اعتمادٌ بفجوةٍ بلا استثناء → 409: ' . $r['reason']);
$r = SIG::transition($gate, $OBL, 'approved', 'DEC-EXC-2042-01');
check($r['ok'], 'C16: الاعتمادُ بقرار استثناءٍ موقَّعٍ يمرّ بفجوةٍ ظاهرة: ' . $r['reason']);
$conn->query("UPDATE contract_commitments SET plan_state='draft', sigma_exception_ref=NULL WHERE id={$OBL}");
SCS::saveLine($conn, $gate, $CO, $SC, array('work_model' => 'ton', 'unit' => 'طن', 'unit_price' => 50,
    'contract_obligation_ref' => $OBL, 'primary_units_committed' => 3), $ACTOR);
$r = SIG::check($gate, $OBL);
check(!$r['ok'] && intval($r['code']) === 409 && strpos($r['reason'], 'بفارق 1') !== false,
      'C1: Σ (7) فوق المتعاقد (6) → 409 بقيمة الفارق: ' . $r['reason']);

// ═══ ⑦ C21 · C4 ═══
head('⑦ قيودُ الدرجات في القاعدة');
$conn->query("INSERT INTO contract_commitments
    (company_id, commitment_code, party_scope, contract_ref, commitment_type, equipment_type_code,
     primary_units_contracted, valid_from)
    VALUES ({$CO}, 'CAPW3T-OBL2', 'client', {$CC_ID}, 'equipment_count', 'CAPW3T_LDR', 2, '2042-01-01')");
$OBL2 = intval($conn->insert_id);
$conn->query("UPDATE op_containers SET obl_id = {$OBL} WHERE id = {$ROOT}");
$okSame = $conn->query("UPDATE op_containers SET obl_id = {$OBL} WHERE id = {$EQC}");
check($okSame, 'مقعدٌ بالتزام أبيه نفسِه يُقبل');
$badObl = $conn->query("UPDATE op_containers SET obl_id = {$OBL2} WHERE id = {$EQC}");
check(!$badObl, 'C21: مقعدٌ لالتزامٍ غيرِ التزامِ أبيه → يُرفض بقيد FK المركّب: ' . $conn->error);
$dupSeat = $conn->query("INSERT INTO op_containers
    (company_id, container_no, level, parent_id, contract_id, obl_id, unit_type, cap_qty, seat_no)
    VALUES ({$CO}, 'CAPW3T-EQ2', 'معدة', {$ROOT}, {$CC_ID}, {$OBL}, 'hour', 50, 9931)");
check(!$dupSeat, 'UQ(obl_id, seat_no): مقعدٌ 9931 ثانٍ للالتزام نفسِه → مرفوض: ' . $conn->error);
$EQ = intval($conn->query("SELECT id FROM equipments ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$a1 = $conn->query("INSERT INTO seat_assignments
    (company_id, container_id, equipment_id, date_from, assignment_role, activation_state, replace_reason)
    VALUES ({$CO}, {$EQC}, {$EQ}, '2042-01-01', 'أساسي', 'active', 'CAPW3T ①')");
$a2 = $conn->query("INSERT INTO seat_assignments
    (company_id, container_id, equipment_id, date_from, assignment_role, activation_state, replace_reason)
    VALUES ({$CO}, {$EQC}, {$EQ}, '2042-02-01', 'أساسي', 'active', 'CAPW3T ②')");
check($a1 && !$a2, 'C4: تخصيصان مفتوحان فعّالان لمقعدٍ → الثاني مرفوضٌ بنيويًّا');
$a3 = $conn->query("INSERT INTO seat_assignments
    (company_id, container_id, equipment_id, date_from, assignment_role, activation_state, planned_qty_month, replace_reason)
    VALUES ({$CO}, {$EQC}, {$EQ}, '2042-02-01', 'احتياطي', 'pending', 0, 'CAPW3T ③')");
check($a3, 'الاحتياطيُّ غيرُ المفعَّل خارج القيد — يجلس مع الأساسي (C4 الاستثناء)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
