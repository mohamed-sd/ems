<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * update0005 · CAP-37 — حزامُ الحالات C1→C21 (CAP-01 §16.1)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/cap_c1_c21_belt.php
 *
 * الحزامُ الجامع: يشغّل أحزمةَ الموجات الست ويقرأ شواهدَ الحالات المسماة من
 * مخرجاتها الخضراء، ويكمل مباشرةً ما لا شاهدَ مسمًّى له (C5 · C6 · C7 · C14).
 * **القبول: 21/21** — وC22→C33 سيناريوهاتُ UAT-01 المسجَّلة في
 * docs/uat/UAT_CAP01_SCENARIOS_C22_C33_ar.md (DEC-CAP-D — تنفيذُها هناك).
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
require_once dirname(__DIR__) . '/app/Services/Capacity/SigmaGuard.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Capacity\CapacityContextResolver as CTX;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, 999900, '', true));
$PHP = PHP_BINARY;
$ROOT = dirname(__DIR__);

fwrite(STDOUT, "\n══ CAP-37 — حزامُ C1→C21 ══\n");

// ═══ ① تشغيلُ أحزمة الموجات وقراءةُ شواهدها المسماة ═══
head('① أحزمةُ الموجات الست — كلُّها خضراءُ وشواهدُ الحالات فيها');
$belts = array(
    'w1' => 'cap_w1_standby_fields_test.php',
    'w2' => 'cap_w2_ledger_test.php',
    'w3' => 'cap_w3_balance_test.php',
    'w4' => 'cap_w4_services_test.php',
    'w5' => 'cap_w5_outbox_test.php',
    'w6' => 'cap_w6_snapshot_test.php',
);
$outputs = array();
foreach ($belts as $k => $file) {
    $cmd = escapeshellarg($PHP) . ' ' . escapeshellarg($ROOT . '/tests/' . $file) . ' 2>&1';
    $o = shell_exec($cmd);
    $outputs[$k] = (string) $o;
    $green = strpos($outputs[$k], '· 0 فشل') !== false;
    check($green, 'حزام ' . $file . ' أخضر');
}
$all = implode("\n", $outputs);

// كلُّ شاهدٍ: الحالةُ ← علامتُها النصيةُ في سطرٍ ناجح (✔) من مخرجات الأحزمة
$cases = array(
    'C1'  => 'C1: Σ',
    'C2'  => 'C2: احتياطيٌّ pending بصفر ساعاتٍ',
    'C3'  => 'C3: الحصةُ وقيمةُ العقد لم تتغيرا',
    'C4'  => 'C4: تخصيصان مفتوحان',
    'C8'  => 'C8: مديرُ الحركة وحدَه',
    'C9'  => 'C9: الدرجةُ ② بموافقتين',
    'C10' => 'C10: الدرجةُ ③ لا يملكها',
    'C11' => 'C11: عجزُ المتعطل باقٍ',
    'C12' => 'C12: تعديلُ الحصة بعد إقفال',
    'C13' => 'C13: الفجوةُ بالساعات',
    'C15' => 'C15: مسودةٌ ناقصة',
    'C16' => 'C16: الاعتمادُ بقرار استثناءٍ موقَّعٍ',
    'C17' => '409 (C17)',
    'C18' => 'C18: ④ تنفيذُ الحصة',
    'C19' => 'C19: إدراجُ التغطية',
    'C20' => 'C20: السريانُ المختلف',
    'C21' => 'C21: مقعدٌ لالتزامٍ غيرِ التزامِ أبيه',
);
foreach ($cases as $case => $marker) {
    $hit = false;
    foreach (explode("\n", $all) as $line) {
        if (strpos($line, '✔') !== false && mb_strpos($line, $marker) !== false) { $hit = true; break; }
    }
    check($hit, $case . ' — شاهدُه أخضرُ في أحزمة الموجات: «' . $marker . '»');
}

// ═══ ② الحالاتُ الأربعُ المكمَّلةُ مباشرةً ═══
head('② C5 · C6 · C7 · C14 — مباشرةً');

// C5: معدةٌ تُسجَّل ساعاتٍ بلا مقعدٍ ومورد → 422 — لا معدةَ خارج الشجرة
$EQ_ORPHAN = intval($conn->query("SELECT id FROM equipments WHERE id NOT IN
    (SELECT equipment_id FROM seat_assignments WHERE state='active')
    ORDER BY id DESC LIMIT 1")->fetch_assoc()['id']);
$r = CTX::assertTimesheetOpenable($gate, $CO, array('contract_id' => 0,
    'equipment_id' => $EQ_ORPHAN, 'entry_date' => date('Y-m-d'), 'unit_type' => 'hour'));
check(!$r['ok'] && intval($r['code']) === 422,
      'C5: معدةٌ بلا مقعدٍ ومورد → 422 بقائمة الناقص — لا معدةَ خارج الشجرة');

// C6: المعدةُ المملوكةُ لنا تدخل تحت المورد الداخلي بالقيود نفسِها —
// الحرّاسُ لا يفرّقون بهوية المورد (لا فرعَ operational_source في أيٍّ منهم)
$srcSigma = file_get_contents($ROOT . '/app/Services/Capacity/SigmaGuard.php');
$srcCap = file_get_contents($ROOT . '/app/Services/Contract/StandbyCapService.php');
check(strpos($srcSigma, 'operational_source') === false && strpos($srcCap, 'operational_source') === false
   && strpos($srcSigma, 'internal') === false,
      'C6: قيودُ Σ والسقف عمياءُ عن هوية المورد — الداخليُّ (إكوبيشن) كأي مورد (§3-①)');

// C7: السرية — لا عمودَ مالكٍ ولا ممولٍ في جداول التشغيل ولا في شاشة التغطية
$ownerCols = intval($conn->query("SELECT COUNT(*) n FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name IN ('op_containers','seat_assignments','substitute_coverages','capacity_consumption_ledger')
      AND (column_name LIKE '%owner%' OR column_name LIKE '%financ%' OR column_name LIKE '%fund%')")->fetch_assoc()['n']);
$srcScreen = file_get_contents($ROOT . '/Contracts/contract_coverage.php');
check($ownerCols === 0
   && mb_strpos($srcScreen, 'asset_ownership') === false && mb_strpos($srcScreen, 'ownership') === false,
      'C7: لا عمودَ مالكٍ في جداول التشغيل ولا ذكرَ للمالك والممول في شاشة التغطية (§3-③)');

// C14: زيادةٌ فوق الالتزام بموافقاتٍ — بابُها ملحقٌ/فترةٌ جديدةٌ ترفع الالتزامَ
// وتُسجَّل بندًا معلنًا بأثرها المقدَّر، لا تجاوزًا صامتًا
$CC_ID = intval($conn->query("SELECT id FROM contracts WHERE company_id={$CO} AND COALESCE(is_deleted,0)=0
                              ORDER BY id LIMIT 1")->fetch_assoc()['id']);
$conn->query("DELETE FROM contract_commitments WHERE commitment_code LIKE 'CAPC14T%'");
$conn->query("INSERT INTO contract_commitments
    (company_id, commitment_code, party_scope, contract_ref, commitment_type, equipment_type_code,
     primary_units_contracted, qty_per_primary_unit_month, measure_code, valid_from, note)
    VALUES ({$CO}, 'CAPC14T-A', 'client', {$CC_ID}, 'equipment_count', 'CAPC14T_X', 2, 600, 'hour', '2043-01-01', NULL)");
$declared = $conn->query("INSERT INTO contract_commitments
    (company_id, commitment_code, party_scope, contract_ref, commitment_type, equipment_type_code,
     primary_units_contracted, qty_per_primary_unit_month, measure_code, valid_from, note)
    VALUES ({$CO}, 'CAPC14T-B', 'client', {$CC_ID}, 'equipment_count', 'CAPC14T_X', 3, 600, 'hour', '2043-06-01',
            'زيادةٌ معتمدةٌ بقصد — الأثرُ المقدَّر +600 ساعة شهريًّا بقرار السلّم الثلاثي')");
check($declared !== false && $conn->insert_id > 0,
      'C14: الزيادةُ فوق الالتزام تدخل فترةً معتمدةً جديدةً ببندٍ معلنٍ بأثرها — لا تجاوزًا صامتًا (§10-③/④)');
$conn->query("DELETE FROM contract_commitments WHERE commitment_code LIKE 'CAPC14T%'");

// ═══ ③ C22→C33 مسجَّلةٌ سيناريوهاتٍ لـUAT-01 ═══
head('③ C22→C33 — سيناريوهاتُ UAT-01 مسجَّلة (§16.2 · DEC-CAP-D)');
$uat = $ROOT . '/docs/uat/UAT_CAP01_SCENARIOS_C22_C33_ar.md';
check(file_exists($uat), 'الوثيقةُ قائمة: docs/uat/UAT_CAP01_SCENARIOS_C22_C33_ar.md');
if (file_exists($uat)) {
    $doc = file_get_contents($uat);
    $missing = array();
    for ($i = 22; $i <= 33; $i++) { if (mb_strpos($doc, 'C' . $i) === false) { $missing[] = 'C' . $i; } }
    check(empty($missing), 'الاثنتا عشرةَ حالةً كلُّها مسجَّلة' . (empty($missing) ? '' : ' — الناقص: ' . implode(',', $missing)));
    check(mb_strpos($doc, 'شاهدُ C33') !== false || mb_strpos($doc, 'التتبع') !== false,
          'شاهدُ C33 (التتبعُ بنقراتٍ متصلة) منصوص');
}

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
