<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-07 — اختبار قبول: عقدُ المورد ببنوده وبندِ الاستعداد (CON-03 §2-②④ · §6)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/supplier_contract_lines_test.php
 *
 * ما يُثبته:
 *   ① الترحيلُ **قراءةً** مطابق: 7 رؤوسٍ = 7 صفوفٍ موروثة · البنودُ المشتقةُ
 *      صدقًا وحدَها · والمتعذّرُ **معلَنٌ لا ملفَّق**.
 *   ② حراسُ §6-Validation: نموذجٌ خارج القائمة · وحدةٌ مجهولة · سعرٌ ≤ 0 ·
 *      أساسُ استعدادٍ بلا معدل (والعكس) · نسبةٌ > 100 · مكررٌ 409.
 *   ③ **423 مزدوج**: المرحَّلُ بمصدره · والنافذُ «التغيير بملحق».
 *   ④ **قيدُ CHECK بنيويٌّ**: «قاعدةٌ بلا سعرٍ مكتوب» مستحيلةٌ ولو التفّ أحدٌ
 *      على الخدمة وكتب مباشرةً.
 *   ⑤ **الوصلُ الحي**: بندٌ بأساسٍ يحوّل ساعاتِ الاستعداد إلى مالٍ بسعرٍ مقرَّر
 *      (rate وpercent) — وبلا بندٍ يبقى `standby_needs_rate` كما هو.
 *   ⑥ **صفرُ انحدارٍ على البيانات الحية**: كلُّ المرحَّل `standby_basis='none'`
 *      فلا واقعةَ حيةٌ يتغير حكمُها.
 *
 * البذرُ معزول: عقودُ موردٍ صوريةٌ بملاحظة H07T وتواريخ 2042 — تُكنس كاملةً.
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

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\SupplierContractService as SCS;
use App\Services\Contract\ContractStateMachine as CSM;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999907;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

/* ◆ **البنودُ قبلَ الالتزامِ في الكنس** (البند ٢-١): المفتاحُ الأجنبيُّ
 *   `fk_sup_line_obligation` بـ`RESTRICT` — فحذفُ التزامٍ تحتَه بندٌ يُردّ.
 *   والترتيبُ هنا ليس ذوقًا: بندٌ ← رأسٌ ← التزام. */
$teardown = function () use ($conn) {
    $conn->query("DELETE l FROM supplier_contract_lines l
                   JOIN supplier_contracts h ON h.id = l.contract_id
                  WHERE h.notes LIKE 'H07T%'");
    $conn->query("DELETE FROM supplier_contracts WHERE notes LIKE 'H07T%'");
    $conn->query("DELETE FROM contract_commitments WHERE commitment_code LIKE 'H07T%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-07 — عقدُ المورد ببنوده وبندِ الاستعداد ══\n");

// ═══ ① الترحيلُ قراءةً ═══
head('① الترحيلُ **قراءةً** — المصدرُ يبقى الكاتب (N-04 مرحلة ①)');
$legacyHeads = intval($conn->query("SELECT COUNT(*) n FROM supplierscontracts")->fetch_assoc()['n']);
$mirrored = intval($conn->query("SELECT COUNT(*) n FROM supplier_contracts
                                  WHERE source_table='supplierscontracts'")->fetch_assoc()['n']);
check($legacyHeads > 0 && $mirrored === $legacyHeads,
      "كلُّ رأسٍ موروثٍ له مرآةٌ: {$mirrored}/{$legacyHeads}");

$orphan = intval($conn->query("SELECT COUNT(*) n FROM supplier_contracts h
    WHERE h.source_table='supplierscontracts'
      AND NOT EXISTS (SELECT 1 FROM supplierscontracts s WHERE s.id = h.source_id)")->fetch_assoc()['n']);
check($orphan === 0, 'صفرُ مرآةٍ بلا مصدرٍ حي (لا صفَّ يتيم — §10-④)');

// كلُّ بندٍ مرحَّلٍ يطابق سعرَ مصدره حرفيًّا — لا إعادةَ حكم
$mismatch = intval($conn->query("SELECT COUNT(*) n FROM supplier_contract_lines l
    JOIN suppliercontractequipments sce ON sce.id = l.source_id
   WHERE l.source_table='suppliercontractequipments'
     AND ROUND(l.unit_price,2) <> ROUND(sce.equip_price,2)")->fetch_assoc()['n']);
check($mismatch === 0, 'صفرُ بندٍ مرحَّلٍ يخالف سعرَ مصدره — مرآةٌ لا إعادةُ حكم');

// المتعذّرُ معلَنٌ لا ملفَّق: صفوفُ الموروث بلا وحدةٍ معروفةٍ أو بسعرٍ صفريٍّ لا تُرحَّل
$undecidable = intval($conn->query("SELECT COUNT(*) n FROM suppliercontractequipments sce
   WHERE sce.equip_price <= 0
      OR TRIM(sce.equip_unit) NOT IN ('ساعة','طن','نقلة','متر','متر طولي')")->fetch_assoc()['n']);
$undecidableProjected = intval($conn->query("SELECT COUNT(*) n FROM supplier_contract_lines l
    JOIN suppliercontractequipments sce ON sce.id = l.source_id
   WHERE l.source_table='suppliercontractequipments'
     AND (sce.equip_price <= 0
          OR TRIM(sce.equip_unit) NOT IN ('ساعة','طن','نقلة','متر','متر طولي'))")->fetch_assoc()['n']);
check($undecidable > 0 && $undecidableProjected === 0,
      "صفرُ بندٍ مُخترعٍ من مصدرٍ متعذّر ({$undecidable} صفًّا موروثًا تُرك معلَنًا)");

// كلُّ مرحَّلٍ standby_basis='none' — حقيقةٌ مقيسة لا افتراض
$nonNone = intval($conn->query("SELECT COUNT(*) n FROM supplier_contract_lines
    WHERE source_table='suppliercontractequipments' AND standby_basis <> 'none'")->fetch_assoc()['n']);
check($nonNone === 0, 'كلُّ مرحَّلٍ بلا استعدادٍ مشترط — الموروثُ لا يحمل المفهوم أصلًا');

// ═══ ② الحراس على الإنشاء ═══
head('② حراسُ §6-Validation');
$SUP = intval($conn->query("SELECT id FROM suppliers WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$CC  = intval($conn->query("SELECT id FROM contracts WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc()['id']);

/* ◆ **«لا حصةَ بلا التزام» صارت مُنفَذةً لا مكتوبةً** (البند ٢-١ من
 *   INJ-EXEC-CLOSE-01): `saveLine` تردُّ ٤٢٢ على غيابِ `contract_obligation_ref`
 *   بلا شرط. فالتجهيزةُ تبذر التزامَ نوعِ معدةٍ حقيقيًّا على عقدِ العميلِ نفسِه —
 *   **وليس تليينًا للفحصِ بل استيفاءً لعقدٍ صار نافذًا**. والفحوصُ السابقةُ
 *   للالتزامِ في الترتيب (نموذجُ العمل · الوحدةُ · السعرُ · الاستعداد) تبقى
 *   كما هي، فالمرجعُ لا يبتلع أحكامَها. */
$conn->query("INSERT INTO contract_commitments
    (company_id, commitment_code, party_scope, contract_ref, commitment_type,
     equipment_type_code, primary_units_contracted, standby_units_required,
     standby_units_allowed, valid_from)
    VALUES ({$CO}, 'H07T-CMT', 'client', {$CC}, 'equipment_count',
            'H07T_EXC', 5, 1, 2, '2042-01-01')");
$H07T_OBL = intval($conn->insert_id);
check($H07T_OBL > 0, 'بُذر التزامُ نوعِ معدةٍ للاختبار — «لا حصةَ بلا التزام»');

$r = SCS::createContract($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'client_contract_id' => $CC,
    'start_date' => '2042-01-01', 'end_date' => '2042-12-31',
    'currency' => 'USD', 'notes' => 'H07T عقدٌ صوريٌّ للاختبار'), $ACTOR);
check($r['ok'] && $r['contract_id'] > 0, 'أُنشئ رأسُ عقدٍ مسودةً');
$CID = intval($r['contract_id']);

$r = SCS::createContract($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'client_contract_id' => $CC,
    'start_date' => '2042-01-01', 'notes' => 'H07T مكرر'), $ACTOR);
check(!$r['ok'] && $r['code'] === 409, 'رأسٌ مكررٌ على (مورد × عقد عميل × بدء) → 409');

$r = SCS::createContract($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'start_date' => '2042-02-01', 'currency' => 'XYZ', 'notes' => 'H07T عملة'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'عملةٌ لا يعرفها محرّكُ الفوترة → 422');

$r = SCS::saveLine($conn, $gate, $CO, $CID, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'kilogram', 'unit' => 'كجم', 'unit_price' => 5), $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && strpos($r['reason'], '§2-②') !== false,
      'نموذجُ تشغيلٍ خارج الأربعة → 422 بنص المصدر');

$r = SCS::saveLine($conn, $gate, $CO, $CID, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'hour', 'unit' => 'ساعه', 'unit_price' => 5), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'وحدةٌ لا يعرفها المحرّك (إملاءٌ مغاير) → 422');

$r = SCS::saveLine($conn, $gate, $CO, $CID, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'hour', 'unit' => 'ساعة', 'unit_price' => 0), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'سعرُ وحدةٍ صفريٌّ → 422 («صفرُ بندٍ بلا سعرٍ مكتوب»)');

$r = SCS::saveLine($conn, $gate, $CO, $CID, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'hour', 'unit' => 'ساعة', 'unit_price' => 20, 'standby_basis' => 'rate'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'أساسُ استعدادٍ بلا معدل → 422');

$r = SCS::saveLine($conn, $gate, $CO, $CID, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'hour', 'unit' => 'ساعة', 'unit_price' => 20,
    'standby_basis' => 'none', 'standby_rate' => 7), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'معدلٌ بلا أساسٍ مشترط → 422');

$r = SCS::saveLine($conn, $gate, $CO, $CID, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'ton', 'unit' => 'طن', 'unit_price' => 30,
    'standby_basis' => 'percent', 'standby_rate' => 140), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'نسبةُ استعدادٍ > 100٪ من سعر الوحدة → 422');

// ── السليم ──
$r = SCS::saveLine($conn, $gate, $CO, $CID, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'hour', 'unit' => 'ساعة', 'unit_price' => 20, 'currency' => 'USD',
    'standby_basis' => 'rate', 'standby_rate' => 6.5, 'valid_from' => '2042-01-01'), $ACTOR);
check($r['ok'], 'بندُ ساعةٍ بمعدل استعدادٍ 6.5 حُفظ');
$L_HOUR = intval($r['line_id']);

$r = SCS::saveLine($conn, $gate, $CO, $CID, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'ton', 'unit' => 'طن', 'unit_price' => 40, 'currency' => 'USD',
    'standby_basis' => 'percent', 'standby_rate' => 25, 'valid_from' => '2042-01-01'), $ACTOR);
check($r['ok'], 'بندُ طنٍّ باستعدادٍ 25٪ من سعر الوحدة حُفظ');
$L_TON = intval($r['line_id']);

$r = SCS::saveLine($conn, $gate, $CO, $CID, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'ton', 'unit' => 'طن', 'unit_price' => 55), $ACTOR);
check(!$r['ok'] && $r['code'] === 409, 'بندٌ مكررٌ على (عقد × نموذج × وحدة) → 409');

// ═══ ③ 423 المزدوج ═══
head('③ الحارسان 423: المرحَّلُ بمصدره · والنافذُ «التغيير بملحق»');
$migrated = $conn->query("SELECT id FROM supplier_contracts WHERE source_table='supplierscontracts' ORDER BY id LIMIT 1")->fetch_assoc();
$MIG = intval($migrated['id']);
$r = SCS::saveLine($conn, $gate, $CO, $MIG, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'hour', 'unit' => 'ساعة', 'unit_price' => 99), $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && strpos($r['reason'], 'supplierscontracts') !== false,
      'بندٌ على عقدٍ مرحَّل → 423 **بمصدره**');

$r = SCS::transition($conn, $gate, $CO, $MIG, CSM::NEGOTIATION, 0, $ACTOR);
check(!$r['ok'] && $r['code'] === 423, 'انتقالُ حالةٍ على مرحَّل → 423 (حالتُه مرآةُ مصدره)');

// العقدُ الصوريُّ إلى «نافذ» بقائمة سماح H-02 نفسِها
$r = SCS::transition($conn, $gate, $CO, $CID, CSM::EFFECTIVE, 0, $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'قفزٌ من «مسودة» إلى «نافذ» مباشرةً → 422 (قائمةُ السماح)');
foreach (array(CSM::NEGOTIATION, CSM::APPROVED, CSM::SIGNED, CSM::EFFECTIVE) as $to) {
    SCS::transition($conn, $gate, $CO, $CID, $to, 0, $ACTOR);
}
$now = SCS::head($gate, $CID);
check((string) $now['state'] === CSM::EFFECTIVE, 'السلسلةُ المشروعة بلغت «نافذ» بأربع خطوات');

$r = SCS::saveLine($conn, $gate, $CO, $CID, array(
    'contract_obligation_ref' => $H07T_OBL, 'line_id' => $L_HOUR, 'work_model' => 'hour', 'unit' => 'ساعة', 'unit_price' => 21,
    'standby_basis' => 'rate', 'standby_rate' => 6.5), $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && strpos($r['reason'], 'بملحق') !== false,
      'تعديلُ بندٍ في عقدٍ **نافذ** → 423 «التغيير بملحق»');
$still = intval($conn->query("SELECT unit_price FROM supplier_contract_lines WHERE id={$L_HOUR}")->fetch_assoc()['unit_price']);
check($still === 20, 'والسعرُ القائم لم يُمسّ (الرفضُ لا يكتب شيئًا)');

// ═══ ④ قيدُ CHECK البنيوي ═══
head('④ «قاعدةٌ بلا سعرٍ مكتوب» مستحيلةٌ **بنيويًّا** لا بفحص الخدمة وحدَه');
$conn->query("INSERT INTO supplier_contract_lines (company_id, contract_id, work_model, unit, unit_price,
              standby_basis, standby_rate, state) VALUES ({$CO}, {$CID}, 'trip', 'نقلة', 10, 'rate', NULL, 'active')");
$leaked = intval($conn->query("SELECT COUNT(*) n FROM supplier_contract_lines
    WHERE contract_id={$CID} AND work_model='trip'")->fetch_assoc()['n']);
check($leaked === 0, 'كتابةٌ مباشرةٌ (أساسٌ بلا معدل) يرفضها CHECK — صفرُ صفٍّ مرّ');
$conn->query("INSERT INTO supplier_contract_lines (company_id, contract_id, work_model, unit, unit_price,
              standby_basis, state) VALUES ({$CO}, {$CID}, 'meter', 'متر طولي', 0, 'none', 'active')");
$leaked2 = intval($conn->query("SELECT COUNT(*) n FROM supplier_contract_lines
    WHERE contract_id={$CID} AND work_model='meter'")->fetch_assoc()['n']);
check($leaked2 === 0, 'كتابةٌ مباشرةٌ بسعرٍ صفريٍّ يرفضها CHECK كذلك');

// ═══ ⑤ الوصلُ الحي — الاستعدادُ يصير مالًا بسعرٍ مقرَّر ═══
head('⑤ الوصلُ الحي: الفجوةُ المدوَّنةُ في EffectFanout::qtyAward تُسدّ');
$map = SCS::standbyBasisMap($conn, $CO, $SUP, $CC, '2042-06-01');
check(!empty($map['hour']['ok']) && $map['hour']['basis'] === 'rate' && abs($map['hour']['rate'] - 6.5) < 0.001,
      'خريطةُ الأسس تحلّ بندَ الساعة بمعدله من العقد النافذ');
check(!empty($map['ton']['ok']) && $map['ton']['basis'] === 'percent' && abs($map['ton']['unit_price'] - 40) < 0.001,
      'وبندَ الطن بنسبته وسعرِ وحدته');
check(empty($map['trip']['ok']) && strpos($map['trip']['reason'], 'لا بندَ') !== false,
      'ونموذجٌ بلا بندٍ **يُعلَن ولا يُخترع له سعر**');

$a = SCS::standbyAmount($map['hour'], 8);
check($a['amount'] !== null && abs($a['amount'] - 52.0) < 0.001,
      '8 ساعاتِ استعدادٍ × 6.5 = 52 (أساسُ rate)');
$a = SCS::standbyAmount($map['ton'], 10);
check($a['amount'] !== null && abs($a['amount'] - 100.0) < 0.001,
      '10 ساعاتٍ × 40 × 25٪ = 100 (أساسُ percent)');
$a = SCS::standbyAmount($map['trip'], 5);
check($a['amount'] === null && strpos($a['note'], 'بلا سعرٍ مقرَّر') !== false,
      'وبلا أساسٍ: **null معلَنٌ لا صفرٌ ملفَّق**');

// خارجَ سريان العقد: لا أساسَ ولو وُجد البند
$mapOut = SCS::standbyBasisMap($conn, $CO, $SUP, $CC, '2041-06-01');
check(empty($mapOut['hour']['ok']), 'تاريخٌ خارج سريان العقد → لا أساس (السريانُ يحكم)');

// موردٌ آخر لا يرث أساسَ غيره
$other = $conn->query("SELECT id FROM suppliers WHERE company_id={$CO} AND id <> {$SUP} ORDER BY id LIMIT 1")->fetch_assoc();
if ($other) {
    $mapOther = SCS::standbyBasisMap($conn, $CO, intval($other['id']), $CC, '2042-06-01');
    check(empty($mapOther['hour']['ok']), 'موردٌ آخر لا يرث أساسَ استعداد غيره');
} else { ok('لا موردَ ثانٍ للمقارنة — يُتخطى معلَنًا'); }

// ═══ ⑥ صفرُ انحدارٍ على الحي ═══
head('⑥ صفرُ انحدارٍ على البيانات الحية');
$liveWithBasis = intval($conn->query("SELECT COUNT(*) n FROM supplier_contract_lines l
    JOIN supplier_contracts h ON h.id = l.contract_id
   WHERE h.notes IS NULL OR h.notes NOT LIKE 'H07T%'")->fetch_assoc()['n']);
$liveNone = intval($conn->query("SELECT COUNT(*) n FROM supplier_contract_lines l
    JOIN supplier_contracts h ON h.id = l.contract_id
   WHERE (h.notes IS NULL OR h.notes NOT LIKE 'H07T%') AND l.standby_basis = 'none'")->fetch_assoc()['n']);
check($liveWithBasis === $liveNone,
      "كلُّ بندٍ حيٍّ ({$liveNone}/{$liveWithBasis}) بلا أساسٍ ⇒ صفرُ واقعةٍ حيةٍ يتغير حكمُها");

// ═══ ⑦ الإنهاءُ بسريانٍ لا حذفًا ═══
head('⑦ لا حذفَ — الإنهاءُ بسريانه');
$r2 = SCS::createContract($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'start_date' => '2042-03-01', 'notes' => 'H07T للإنهاء'), $ACTOR);
$CID2 = intval($r2['contract_id']);
$r2 = SCS::saveLine($conn, $gate, $CO, $CID2, array(
    'contract_obligation_ref' => $H07T_OBL, 'work_model' => 'meter', 'unit' => 'متر طولي', 'unit_price' => 15), $ACTOR);
$L2 = intval($r2['line_id']);
$r2 = SCS::endLine($conn, $gate, $CO, $CID2, $L2, '2042-05-31', $ACTOR);
$row = $conn->query("SELECT state, valid_to FROM supplier_contract_lines WHERE id={$L2}")->fetch_assoc();
check($r2['ok'] && $row && $row['state'] === 'ended' && $row['valid_to'] === '2042-05-31',
      'البندُ صار «منتهٍ» بتاريخه — والصفُّ قائمٌ لا ممحوّ');

// ═══ ⑧ الشاشةُ والتسجيل ═══
head('⑧ الشاشةُ مسجَّلةٌ ولا تكتب بيدها');
$mod = $conn->query("SELECT id, owner_role_id FROM modules WHERE code='Suppliers/supplier_contract_lines.php'")->fetch_assoc();
check($mod && intval($mod['id']) === 153 && intval($mod['owner_role_id']) === 2,
      'الوحدة 153 مسجَّلةٌ لمالك عقود الموردين (2)');
$navs = intval($conn->query("SELECT COUNT(*) n FROM nav_items
    WHERE route='Suppliers/supplier_contract_lines.php' AND active=1")->fetch_assoc()['n']);
check($navs >= 2, "الشاشةُ معلَنةٌ في القائمة الموحّدة ({$navs} دورًا)");
$src = file_get_contents(dirname(__DIR__) . '/Suppliers/supplier_contract_lines.php');
check(strpos($src, 'SCS::saveLine') !== false && strpos($src, "insert('supplier_contract_lines'") === false,
      'كلُّ حفظٍ عبر الخدمة — لا إدراجَ خامٍ في الشاشة (الحارسُ في موضعٍ واحدٍ يسري على الجميع)');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
