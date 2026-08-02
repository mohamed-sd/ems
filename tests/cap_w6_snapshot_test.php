<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0005 · الموجة ⑥ — اللقطةُ ومسارُ الوحدة (CAP-31→34 · §12/§12.1)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/cap_w6_snapshot_test.php
 *
 * ما يُثبته بأسماء الحالات:
 *   C22 مسوَّدةُ تايم شيت → صفرُ سطرٍ في الدفتر وصفرُ أثرٍ على أي رصيد —
 *       وحتى اعتمادُ الموقع وحدَه لا يستهلك (الاستدعاءُ عند اكتمال السلسلة).
 *   C23 وحدةٌ معادةٌ → صفرُ استهلاكٍ والرصيدُ كما كان.
 *   C24 اكتمالُ السلسلة → الأسطرُ تُكتب مرةً واحدةً بمفتاح الجولة.
 *   C29 اللقطةُ تُثبَّت عند الاعتماد ولا تُحلّ ثانيةً — تغييرُ التخصيص بعدها
 *       لا يغيّر لقطةَ الوحدة القديمة.
 *   + CAP-31 المفاتيحُ تُجلب آليًّا · CAP-32 الناقصُ يُرفض بقائمته وروابطه.
 *
 * البذرُ معزول: CAPW6T — يُكنس قبل وبعد.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Capacity/CapacityContextResolver.php';
require_once dirname(__DIR__) . '/app/Services/Unit/TimesheetEntryService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Capacity\CapacityContextResolver as CTX;
use App\Services\Unit\TimesheetEntryService as TES;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999901;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

$teardown = function () use ($conn) {
    $ids = array();
    $r = $conn->query("SELECT id FROM unit_entries WHERE entry_no LIKE 'CAPW6T%'");
    while ($r && ($x = $r->fetch_assoc())) { $ids[] = (int) $x['id']; }
    if ($ids) {
        $in = implode(',', $ids);
        $conn->query("DELETE FROM capacity_consumption_ledger WHERE unit_record_id IN ({$in}) AND reverses_led_id IS NOT NULL");
        $conn->query("DELETE FROM capacity_consumption_ledger WHERE unit_record_id IN ({$in})");
        $conn->query("DELETE FROM container_consumption WHERE source_ref IN ({$in}) AND source_kind='unit_entry'");
        $conn->query("DELETE FROM unit_approvals WHERE entry_id IN ({$in})");
        $conn->query("DELETE FROM unit_time_log WHERE entry_id IN ({$in})");
    }
    $conn->query("DELETE FROM unit_entries WHERE entry_no LIKE 'CAPW6T%'");
    $conn->query("DELETE FROM seat_assignments WHERE replace_reason LIKE 'CAPW6T%'");
    foreach (array('مشغّل', 'معدة', 'نوع', 'مورد', 'رئيسية') as $lvl) {
        $conn->query("DELETE FROM op_containers WHERE container_no LIKE 'CAPW6T%' AND level = '{$lvl}'");
    }
    $conn->query("DELETE l FROM supplier_contract_lines l
                   JOIN supplier_contracts h ON h.id = l.contract_id WHERE h.notes LIKE 'CAPW6T%'");
    $conn->query("DELETE FROM supplier_contracts WHERE notes LIKE 'CAPW6T%'");
    $conn->query("DELETE FROM contract_commitments WHERE commitment_code LIKE 'CAPW6T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ update0005 · الموجة ⑥ — اللقطةُ ومسارُ الوحدة ══\n");

// ═══ البذر ═══
$CC_ID = intval($conn->query("SELECT id FROM contracts WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0
                              ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$EQ = intval($conn->query("SELECT e.id FROM equipments e
    WHERE NOT EXISTS (SELECT 1 FROM equipment_documents d
                       WHERE d.subject_type='equipment' AND d.subject_id=e.id
                         AND d.doc_type IN ('استمارة','تأمين','فحص دوري','رخصة تشغيل'))
    ORDER BY e.id LIMIT 1")->fetch_assoc()['id']);
$OP = intval($conn->query("SELECT id FROM employees ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$conn->query("INSERT INTO contract_commitments
    (company_id, commitment_code, party_scope, contract_ref, commitment_type, equipment_type_code,
     primary_units_contracted, standby_units_allowed, qty_per_primary_unit_month, measure_code, valid_from)
    VALUES ({$CO}, 'CAPW6T-OBL', 'client', {$CC_ID}, 'equipment_count', 'CAPW6T_EXC', 1, 1, 600, 'hour', '2026-01-01')");
$OBL = intval($conn->insert_id);
$conn->query("INSERT INTO supplier_contracts (company_id, supplier_id, client_contract_id, start_date, state, notes)
              VALUES ({$CO}, {$SUP}, {$CC_ID}, '2026-01-01', 'draft', 'CAPW6T عقد')");
$SCID = intval($conn->insert_id);
$conn->query("INSERT INTO supplier_contract_lines (company_id, contract_id, contract_obligation_ref,
              work_model, unit, unit_price, primary_units_committed, standby_units_allowed, replacement_sla_hours)
              VALUES ({$CO}, {$SCID}, {$OBL}, 'hour', 'ساعة', 100, 1, 1, 48)");
$LINE = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, contract_id, obl_id, unit_type, cap_qty)
              VALUES ({$CO}, 'CAPW6T-ROOT', 'رئيسية', {$CC_ID}, {$OBL}, 'hour', 1000)");
$ROOT = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, obl_id, supplier_id, unit_type, cap_qty)
              VALUES ({$CO}, 'CAPW6T-SUP', 'مورد', {$ROOT}, {$CC_ID}, {$OBL}, {$SUP}, 'hour', 800)");
$SUPC = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, obl_id, equipment_id, unit_type, cap_qty, seat_no)
              VALUES ({$CO}, 'CAPW6T-EQ', 'معدة', {$SUPC}, {$CC_ID}, {$OBL}, {$EQ}, 'hour', 700, 9961)");
$SEATC = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers (company_id, container_no, level, parent_id, contract_id, obl_id, operator_employee_id, unit_type, cap_qty)
              VALUES ({$CO}, 'CAPW6T-OPR', 'مشغّل', {$SEATC}, {$CC_ID}, {$OBL}, {$OP}, 'hour', 600)");
$conn->query("INSERT INTO seat_assignments (company_id, container_id, equipment_id, date_from,
              assignment_role, activation_state, supplier_contract_line_id, replace_reason)
              VALUES ({$CO}, {$SEATC}, {$EQ}, '2026-01-01', 'أساسي', 'active', {$LINE}, 'CAPW6T ①')");
$ASG = intval($conn->insert_id);

// ═══ ① البنية + CAP-31 ═══
head('① المفاتيحُ الثمانيةُ تُحلّ آليًّا (CAP-31)');
$cols = 0;
$r = $conn->query("SELECT COUNT(*) n FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'unit_entries' AND column_name LIKE 'cap\\_%'");
$cols = intval($r->fetch_assoc()['n']);
check($cols === 9, 'أعمدةُ اللقطة التسعةُ (٨ مفاتيح + حالة) على unit_entries — ' . $cols);
$rs = CTX::resolve($gate, $CO, array('contract_id' => $CC_ID, 'equipment_id' => $EQ,
    'entry_date' => '2026-08-01', 'unit_type' => 'hour'));
check($rs['resolved'], 'الحلُّ اكتمل بلا ناقص');
$k = $rs['keys'];
check((int) $k['cap_obligation_id'] === $OBL && (int) $k['cap_seat_id'] === $SEATC
   && (int) $k['cap_supplier_share_id'] === $SUPC && (int) $k['cap_assignment_id'] === $ASG
   && (int) $k['cap_supplier_line_id'] === $LINE && $k['cap_role_snapshot'] === 'primary'
   && $k['cap_measure_code'] === 'hour',
      'المفاتيحُ السبعةُ المطلوبةُ حُلّت من التخصيصات — والمستخدمُ لا يُدخل ما يعرفه النظام');

// ═══ ② CAP-32 ═══
head('② الناقصُ يُرفض بقائمته وروابطه (CAP-32)');
$EQ_ORPHAN = intval($conn->query("SELECT id FROM equipments WHERE id NOT IN
    (SELECT equipment_id FROM seat_assignments WHERE state='active') ORDER BY id DESC LIMIT 1")->fetch_assoc()['id']);
$r = CTX::assertTimesheetOpenable($gate, $CO, array('contract_id' => $CC_ID,
    'equipment_id' => $EQ_ORPHAN, 'entry_date' => '2026-08-01', 'unit_type' => 'hour'));
check(!$r['ok'] && intval($r['code']) === 422 && !empty($r['reasons']) && !empty($r['links']),
      'معدةٌ بلا تخصيصٍ → 422 بقائمة الناقص (' . count($r['reasons']) . ') وروابطِه (' . count($r['links']) . ')');

// ═══ ③ C22 · C23 — صفرُ استهلاكٍ قبل اكتمال السلسلة ═══
head('③ C22/C23 — المسودةُ واعتمادُ الموقع والمعادةُ صفرُ استهلاك');
$mkEntry = function ($no, $state) use ($conn, $CO, $CC_ID, $EQ, $OP) {
    $conn->query("INSERT INTO unit_entries
        (company_id, entry_no, entry_date, project_id, contract_id, equipment_id, operator_employee_id,
         unit_type, qty, record_basis, shift, source_ref, state, revision_no, current_round, entered_by)
        VALUES ({$CO}, '{$no}', '2026-08-01', 10, {$CC_ID}, {$EQ}, {$OP},
                'hour', 8.5, 'contract', 'day', 'CAPW6T', '{$state}', 1, 1, 999901)");
    return intval($conn->insert_id);
};
$E1 = $mkEntry('CAPW6T-001', 'draft');
$led = intval($conn->query("SELECT COUNT(*) n FROM capacity_consumption_ledger WHERE unit_record_id={$E1}")->fetch_assoc()['n']);
$cc = intval($conn->query("SELECT COUNT(*) n FROM container_consumption WHERE source_kind='unit_entry' AND source_ref={$E1}")->fetch_assoc()['n']);
check($led === 0 && $cc === 0, 'C22: المسودةُ صفرُ سطرٍ في الدفتر وصفرُ استهلاك');

$E2 = $mkEntry('CAPW6T-002', 'submitted');
$balBefore = floatval($conn->query("SELECT consumed_qty FROM op_containers WHERE id={$ROOT}")->fetch_assoc()['consumed_qty']);
$r = TES::approve($conn, $gate, $CO, $E2, 'site', $ACTOR, array('enforce_capacity' => false, 'publish_events' => false));
check($r['ok'] && $r['state'] === 'site_approved', 'اعتمادُ الموقع مرّ — حالة site_approved');
$cc = intval($conn->query("SELECT COUNT(*) n FROM container_consumption WHERE source_kind='unit_entry' AND source_ref={$E2}")->fetch_assoc()['n']);
check($cc === 0, 'C22/CAP-34: اعتمادُ الموقع وحدَه **لا يستهلك** — الاستدعاءُ عند اكتمال السلسلة لا قبله');
$r = TES::returnToSite($conn, $gate, $CO, $E2, 'operator', 'CAPW6T تصحيحُ ساعات', $ACTOR, array('publish_events' => false));
check($r['ok'], 'الواقعةُ أُعيدت للموقع (الجولة ' . $r['round'] . ')');
$cc = intval($conn->query("SELECT COUNT(*) n FROM container_consumption WHERE source_kind='unit_entry' AND source_ref={$E2}")->fetch_assoc()['n']);
$balAfter = floatval($conn->query("SELECT consumed_qty FROM op_containers WHERE id={$ROOT}")->fetch_assoc()['consumed_qty']);
check($cc === 0 && abs($balAfter - $balBefore) < 0.001, 'C23: المعادةُ صفرُ استهلاكٍ والرصيدُ كما كان');

// ═══ ④ اكتمالُ السلسلة — الاستهلاكُ واللقطة ═══
head('④ اكتمالُ السلسلة يستهلك ويثبّت اللقطة (C24 · C29)');
$E3 = $mkEntry('CAPW6T-003', 'submitted');
// CAP-31: الختمُ الآلي (مسارُ submit يختم؛ والمزروعُ مباشرةً يُختم هنا)
$rs = CTX::resolve($gate, $CO, array('contract_id' => $CC_ID, 'equipment_id' => $EQ,
    'entry_date' => '2026-08-01', 'unit_type' => 'hour'));
CTX::stampProposed($gate, $E3, $rs['keys'], 'confirmed');
TES::approve($conn, $gate, $CO, $E3, 'site', $ACTOR, array('enforce_capacity' => false, 'publish_events' => false));
TES::approve($conn, $gate, $CO, $E3, 'supplier', $ACTOR, array('publish_events' => false));
TES::approve($conn, $gate, $CO, $E3, 'operator', $ACTOR, array('publish_events' => false));
$r = TES::approve($conn, $gate, $CO, $E3, 'sales', $ACTOR, array('publish_events' => false));
check($r['ok'] && $r['state'] === 'sales_approved', 'السلسلةُ اكتملت — sales_approved');
$row = $conn->query("SELECT cap_context_state, cap_assignment_id, cap_role_snapshot FROM unit_entries WHERE id={$E3}")->fetch_assoc();
check($row['cap_context_state'] === 'locked', 'C29: اللقطةُ ثُبّتت locked عند اكتمال الاعتماد (CAP-33)');
$cc = intval($conn->query("SELECT COUNT(*) n FROM container_consumption WHERE source_kind='unit_entry' AND source_ref={$E3}")->fetch_assoc()['n']);
check($cc === 1, 'C24: الاستهلاكُ وقع مرةً واحدةً عند اكتمال السلسلة (idem: entry:' . $E3 . ':r1)');
$led = $conn->query("SELECT effect_type, unit_record_version, role_snapshot FROM capacity_consumption_ledger
                      WHERE unit_record_id={$E3}")->fetch_assoc();
check($led && intval($led['unit_record_version']) === 1,
      'سطرُ الدفتر قُيّد بنسخة الواقعة (v1) — revision_no هو النسخة (§3.1)');
// الاعتمادُ المكرر لا يخصم ثانية
$r = TES::approve($conn, $gate, $CO, $E3, 'sales', $ACTOR, array('publish_events' => false));
$cc2 = intval($conn->query("SELECT COUNT(*) n FROM container_consumption WHERE source_kind='unit_entry' AND source_ref={$E3}")->fetch_assoc()['n']);
check(!empty($r['existing']) && $cc2 === 1, 'الاعتمادُ المكرَّرُ في الجولة نفسِها عطالةٌ — لا خصمَ ثانيًا (C24/C25)');

// ═══ ⑤ C29 — تغييرُ التخصيص بعد شهرٍ لا يمسّ اللقطة ═══
head('⑤ C29 — الوحدةُ القديمةُ بلقطتها ولا تتغير تسويتُها');
$oldAsg = intval($row['cap_assignment_id']);
$conn->query("UPDATE seat_assignments SET state='ended', date_to='2026-08-15' WHERE id={$ASG}");
$EQ2 = intval($conn->query("SELECT e.id FROM equipments e WHERE e.id <> {$EQ}
    AND NOT EXISTS (SELECT 1 FROM equipment_documents d WHERE d.subject_type='equipment' AND d.subject_id=e.id
                     AND d.doc_type IN ('استمارة','تأمين','فحص دوري','رخصة تشغيل'))
    ORDER BY e.id LIMIT 1")->fetch_assoc()['id']);
$conn->query("INSERT INTO seat_assignments (company_id, container_id, equipment_id, date_from,
              assignment_role, activation_state, supplier_contract_line_id, replace_reason)
              VALUES ({$CO}, {$SEATC}, {$EQ2}, '2026-08-16', 'أساسي', 'active', {$LINE}, 'CAPW6T استبدالٌ لاحق')");
$snap = $conn->query("SELECT cap_assignment_id FROM unit_entries WHERE id={$E3}")->fetch_assoc();
check(intval($snap['cap_assignment_id']) === $oldAsg && $oldAsg === $ASG,
      'C29: تغييرُ التخصيص بعد الاعتماد — لقطةُ الوحدة القديمة كما هي (asg#' . $oldAsg . ')');
$r = CTX::stampProposed($gate, $E3, array('cap_assignment_id' => 999999));
check(!$r['ok'] && intval($r['code']) === 423, 'C29: محاولةُ إعادة حلِّ لقطةٍ مقفلة → 423 — لا تُحلّ ثانيةً أبدًا');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
