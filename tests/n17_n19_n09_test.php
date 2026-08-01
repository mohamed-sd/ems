<?php
/**
 * tests/n17_n19_n09_test.php — مطابقة الأصول · فصل المصدر · الإعارة (البوابة ③)
 * ═══════════════════════════════════════════════════════════════════════════
 * N-17: سجل مقابل تايم شيت بفرق ⇒ صف open · التفسير إلزامي (422 بلا سبب +
 *   CHECK) · ضمن السماح يُفسَّر آليًّا · معدة عملت ولم تُهلك ⇒ علم التشويه ·
 *   معدل الإهلاك بالساعة من الفعلي.
 * N-19: قيمتان لا ثالثة (422) · الإقفال بقرار مسبَّب لكل معدة · «غير المحددة»
 *   تتقلص — حالة نقص لا صنف دائم.
 * N-09: الكيان لا يتعاقد مع نفسه (422+CHECK) · الطرف غير الداخلي 422 ·
 *   الإعارة تفتح النمط ② (governance_flags) · مستحق متبادل بنسب التحمل عاطل.
 * التشغيل: php tests/n17_n19_n09_test.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__) . '/app/Services/Fleet/AssetReconciliationService.php';
require_once dirname(__DIR__) . '/app/Services/Fleet/IntercompanyLoanService.php';

use App\Services\Fleet\AssetReconciliationService as AR;
use App\Services\Fleet\IntercompanyLoanService as IL;

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $MARK = 'N179T'; $EQA = 0; $EQB = 999902; $PERIOD = '2031-03';
$PASS = 0; $FAIL = 0;
function check($c, $m) { global $PASS, $FAIL; if ($c) { $PASS++; echo "  ✔ {$m}\n"; } else { $FAIL++; echo "  ✘ FAIL: {$m}\n"; } }
function head($m) { echo "\n── {$m}\n"; }

$teardown = function () use ($conn, $MARK, $EQB, $PERIOD) {
    $EQA = isset($GLOBALS['EQA_LIVE']) ? (int) $GLOBALS['EQA_LIVE'] : 0;
    $OPX = isset($GLOBALS['OPX']) ? (int) $GLOBALS['OPX'] : 0;
    if ($OPX) { $conn->query("DELETE FROM timesheet WHERE operator = {$OPX}"); $conn->query("DELETE FROM operations WHERE id = {$OPX}"); }
    if ($EQA) { $conn->query("DELETE FROM asset_hour_reconciliations WHERE equipment_id = {$EQA} AND period = '{$PERIOD}'"); }
    $conn->query("DELETE FROM asset_hour_reconciliations WHERE equipment_id = {$EQB}");
    $conn->query("DELETE FROM meter_readings WHERE note LIKE '%{$MARK}%'");
    $conn->query("DELETE d FROM fin_depreciation d JOIN fin_assets a ON a.id=d.asset_id WHERE a.code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_financial_events WHERE source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM ems_business_events WHERE source_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM fin_assets WHERE code LIKE '{$MARK}%'");
    $conn->query("DELETE FROM equipment_ownership_registry WHERE equipment_id IN ({$EQA},{$EQB}) AND (source_decision_note LIKE '%{$MARK}%' OR note LIKE '%N-19%')");
    $conn->query("DELETE FROM intercompany_dues WHERE loan_id IN (SELECT loan_id FROM intercompany_loans WHERE doc_ref LIKE '{$MARK}%')");
    $conn->query("DELETE FROM intercompany_loans WHERE doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM governance_flags WHERE reason LIKE '%{$MARK}%' OR (element_code='internal_parties' AND reason LIKE 'N-09%')");
    $conn->query("DELETE FROM entity_roles WHERE doc_ref LIKE '{$MARK}%'");
    $conn->query("DELETE FROM tenants WHERE entity_id IN (SELECT entity_id FROM legal_entities WHERE legal_name LIKE '%{$MARK}%')");
    $conn->query("DELETE FROM legal_entities WHERE legal_name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

head('N-17 — السجل مقابل التايم شيت');
// المعدة أ: معدة حية (FK العدّادات) وعملية تربط التايم شيت بها
$EQA = (int) $conn->query("SELECT id FROM equipments WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id'];
$GLOBALS['EQA_LIVE'] = $EQA;
// عملية صورية للربط (timesheet.operator → operations.id → equipment)
$conn->query("INSERT INTO operations (company_id, equipment, status) VALUES ({$CO},{$EQA},'0')");
if ($conn->error) { echo "  ! بذر العملية: {$conn->error}\n"; }
$OPX = (int) $conn->insert_id;
$GLOBALS['OPX'] = $OPX;
// عدّاد 100 ساعة · تايم شيت 80 (فرق 20 — يفتح) · بلا إهلاك (علم التشويه)
$conn->query("INSERT INTO meter_readings (company_id, equipment_id, meter_type, chain_no, reading_date, value, delta, source, is_reset, note, recorded_by)
              VALUES ({$CO},{$EQA},'hour',1,'{$PERIOD}-10',5100,60,'manual',0,'{$MARK}',1), ({$CO},{$EQA},'hour',1,'{$PERIOD}-20',5140,40,'manual',0,'{$MARK}',1)");
if ($conn->error) { echo "  ! بذر العدادات: {$conn->error}\n"; }
$conn->query("INSERT INTO timesheet (company_id, operator, employee_id, shift, date, type, user_id, time_notes, total_work_hours) VALUES ({$CO},{$OPX},1,'صباحية','{$PERIOD}-15','عادي',1,'',80)");
if ($conn->error) { echo "  ! بذر التايم شيت: {$conn->error}\n"; }

$r = AR::buildPeriod($conn, $CO, $PERIOD, 1.0);
check($r['ok'] && $r['rows'] >= 1, 'المطابقة بُنيت: ' . $r['reason']);
$q = $conn->query("SELECT * FROM asset_hour_reconciliations WHERE equipment_id={$EQA} AND period='{$PERIOD}'")->fetch_assoc();
check($q && (float) $q['register_hours'] === 100.0 && (float) $q['timesheet_hours'] === 80.0 && (float) $q['diff_hours'] === 20.0,
      'السجل 100 والتايم شيت 80 والفرق 20 — عمود مولَّد لا مصدر ثانٍ');
check($q['state'] === 'open', 'فرق فوق السماح ⇒ صف مفتوح يلزمه تفسير');
check((int) $q['undepreciated_flag'] === 1, 'معدة عملت ولم تُهلك — علم تشويه التكلفة معلَن');
$REC = (int) $q['rec_id'];
$r = AR::explainDiff($conn, $CO, $REC, '', 1);
check(!$r['ok'] && $r['code'] === 422, 'تفسير فارغ ⇒ 422 — لا فرق بلا سبب');
$e = $conn->query("UPDATE asset_hour_reconciliations SET state='explained', explanation=NULL, explained_by=NULL WHERE rec_id={$REC}");
check($e === false, 'وCHECK الجدول يرفض الإغلاق بلا تفسير حتى بالالتفاف');
$r = AR::explainDiff($conn, $CO, $REC, 'ساعات نقل داخلي بلا تايم شيت — مذكرة الحركة 5511', 1);
check($r['ok'], 'وبالسبب يُفسَّر');

// معدل الإهلاك بالساعة: أصل بإهلاك فترة 500 وساعات 100 → 5/ساعة
$conn->query("INSERT INTO fin_assets (company_id, code, name, method, acquisition_cost, salvage_value, useful_life_months, acquisition_date, accumulated_depreciation, state, equipment_id, created_by)
              VALUES ({$CO},'{$MARK}-AS','أصل {$MARK}','straight_line',60000,0,120,'2030-01-01',0,'active',{$EQA},1)");
$AS = (int) $conn->insert_id;
$conn->query("INSERT INTO fin_depreciation (company_id, asset_id, period_ref, depreciation_amount, run_date, method, source, created_by)
              VALUES ({$CO},{$AS},'{$PERIOD}',500,CURDATE(),'straight_line','cron',1)");
if ($conn->error) { $conn->query("INSERT INTO fin_depreciation (asset_id, period_ref, depreciation_amount, run_date, method, source, created_by) VALUES ({$AS},'{$PERIOD}',500,CURDATE(),'straight_line','cron',1)"); }
AR::buildPeriod($conn, $CO, $PERIOD, 1.0);
$q = $conn->query("SELECT depreciation_per_hour, undepreciated_flag FROM asset_hour_reconciliations WHERE equipment_id={$EQA} AND period='{$PERIOD}'")->fetch_assoc();
check(abs((float) $q['depreciation_per_hour'] - 5.0) < 0.001 && (int) $q['undepreciated_flag'] === 0,
      'معدل الإهلاك بالساعة 500/100 = 5 — من الفعلي لا التقدير، والعلم انطفأ');

head('N-19 — فصل المصدر عن الملكية');
$r = AR::decideSource($conn, $CO, $EQB, 'mystery', 'س', 1);
check(!$r['ok'] && $r['code'] === 422, 'قيمة ثالثة ⇒ 422 — قيمتان لا ثالثة');
$r = AR::decideSource($conn, $CO, $EQB, 'supplier_external', '', 1);
check(!$r['ok'] && $r['code'] === 422, 'إقفال بلا سبب ⇒ 422');
$before = count(AR::undecidedSources($conn, $CO));
$r = AR::decideSource($conn, $CO, $EQB, 'supplier_external', $MARK . ': عقد التوريد 2029-11 يثبت المصدر', 1);
check($r['ok'], 'حُسم المصدر بقرار موثق لكل معدة');
$q = $conn->query("SELECT operational_source, source_decided_by FROM equipment_ownership_registry WHERE equipment_id={$EQB}")->fetch_assoc();
check($q['operational_source'] === 'supplier_external' && (int) $q['source_decided_by'] === 1, 'المحور التشغيلي في المجال المقيَّد بقراره — حالة نقص أُغلقت');

head('N-09 — الإعارة بين كيانين (النمط ②)');
$conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg, is_tenant) VALUES ('قابضة {$MARK}','SD','السجل التجاري','{$MARK}-H',1)");
$EH = (int) $conn->insert_id;
$conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg, is_tenant) VALUES ('تشغيلية {$MARK}','SD','السجل التجاري','{$MARK}-O',1)");
$EO = (int) $conn->insert_id;
$conn->query("INSERT INTO legal_entities (legal_name, country, registry_authority, commercial_reg, is_tenant) VALUES ('عميل خارجي {$MARK}','SD','السجل التجاري','{$MARK}-X',0)");
$EX = (int) $conn->insert_id;

$r = IL::open($conn, $CO, array('equipment_id' => $EQA, 'lender_entity_id' => $EH, 'borrower_entity_id' => $EH, 'date_from' => '2026-08-01', 'monthly_value' => 5000, 'bearing_split' => array((string) $EH => 100)), 1);
check(!$r['ok'] && $r['code'] === 422, 'الكيان يتعاقد مع نفسه ⇒ 422 (وCHECK بنيوي يسنده)');
$r = IL::open($conn, $CO, array('equipment_id' => $EQA, 'lender_entity_id' => $EH, 'borrower_entity_id' => $EX, 'date_from' => '2026-08-01', 'monthly_value' => 5000, 'bearing_split' => array((string) $EX => 100)), 1);
check(!$r['ok'] && $r['code'] === 422, 'طرف خارجي في إعارة داخلية ⇒ 422 — النمط ③ له بابه');
$r = IL::open($conn, $CO, array('equipment_id' => $EQA, 'lender_entity_id' => $EH, 'borrower_entity_id' => $EO, 'date_from' => '2026-08-01', 'monthly_value' => 5000, 'bearing_split' => array((string) $EH => 30, (string) $EO => 60), 'doc_ref' => $MARK . '-L1'), 1);
check(!$r['ok'] && $r['code'] === 422, 'Σ نسب تحمل 90 ⇒ 422');
$r = IL::open($conn, $CO, array('equipment_id' => $EQA, 'lender_entity_id' => $EH, 'borrower_entity_id' => $EO, 'date_from' => '2026-08-01', 'monthly_value' => 5000, 'currency' => 'SDG', 'bearing_split' => array((string) $EH => 30, (string) $EO => 70), 'doc_ref' => $MARK . '-L1'), 1);
check($r['ok'], 'إعارة بين قابضة وتشغيلية فُتحت: ' . $r['reason']);
$LOAN = $r['loan_id'];
$q = $conn->query("SELECT COUNT(*) c FROM governance_flags WHERE element_code='internal_parties' AND scope_type='entity' AND scope_id IN ({$EH},{$EO}) AND enabled=1")->fetch_assoc();
check((int) $q['c'] === 2, 'والنمط ② فُتح لنطاق الكيانين — ترقية بلا هجرة بيانات (G8)');
$r = IL::accruePeriod($conn, $CO, $LOAN, '2026-08');
check($r['ok'] && abs($r['amount'] - 3500.0) < 0.005, 'المستحق المتبادل: للمعير على المستعير 5000×70% = 3500 بنسبة تحمّله');
$r2 = IL::accruePeriod($conn, $CO, $LOAN, '2026-08');
check($r2['created'] === 0, 'واستحقاق الفترة عاطل — لا ذمة مزدوجة');

echo PHP_EOL . "══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══" . PHP_EOL;
exit($FAIL === 0 ? 0 : 1);
