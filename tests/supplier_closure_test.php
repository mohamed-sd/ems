<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-18 — اختبار قبول: تصفيةُ إنهاء عقد المورد
 *        (ENT-02 §4-«تصفية إنهاء العقد» · CON-03 §2-⑦)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/supplier_closure_test.php
 *
 * ما يُثبته:
 *   ① **ضمانٌ بلا مهلةٍ مكتوبةٍ مستحيلٌ بنيويًّا** — «بعد مهلته» بلا مهلةٍ نصٌّ
 *      بلا معنى (`CHECK`).
 *   ② **التصفيةُ عند الإنهاء لا قبله** (422 بحالته) · و**تصفيةٌ واحدةٌ للعقد**
 *      (409 «بمفتاح العقد × التصفية»).
 *   ③ **إقفالُ حصةٍ لم تُستهلك يلزمه سببٌ مكتوب** (422) — وبه تُقفل الحاويات.
 *   ④ **لا إخلاءَ ورصيدُ سلفةٍ قائم** (423 برصيده) — والرقمُ لا يضيع بالإنهاء.
 *   ⑤ **ردُّ الضمان بعد مهلته**: قبلها **423 بتاريخ استحقاقه**؛ وبعدها **ذمّةٌ
 *      دائنةٌ باسمها** (`guarantee_release`) بمرجع تصفيتها — والردُّ **لا يتكرر**.
 *   ⑥ **شهادةُ إخلاءٍ موثَّقة**: بلا مستندٍ 422 · وخطوةٌ ناقصةٌ **423 باسمها** ·
 *      و`CHECK` يرفض الإقفالَ بلا مستندٍ ولو التفّ أحدٌ على الخدمة.
 *   ⑦ **الوصلُ الحي**: «مقفل» لا يقع إلا بتصفيةٍ مقفلة (423) — ثم يقع.
 *
 * البذرُ معزول: موردٌ وعقدٌ وحاويةٌ وسلفةٌ في 2095 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '2', 'company_id' => 4, 'name' => 'M18 closure test');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractStateMachine.php';
require_once dirname(__DIR__) . '/app/Services/Contract/SupplierContractService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/SupplierClosureService.php';

use App\Services\Contract\SupplierContractService as SCS;
use App\Services\Contract\SupplierClosureService as SCL;
use App\Services\Contract\ContractStateMachine as CSM;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999851;
$MARK  = 'M18T' . getmypid();
/* **وعائلةُ الوسمِ لا وسمُ الشوطِ وحدَه.** الوسمُ بـ`getmypid()` يجعل كلَّ شوطٍ
   أعمى عمّا تركه سابقُه: فإن أخفق كنسُ شوطٍ بقيت صفوفُه إلى الأبد. والقياسُ
   وجد **78 حاويةً** بهذه العائلةِ ما زالت في القاعدة (51 جذرًا و39 ابنًا)،
   لأن `DELETE … LIKE '{$MARK}%'` كان يستهدف الجذرَ وابنَه **في جملةٍ واحدة**
   فيردُّه `fk_container_parent` صامتًا (mysqli لا يرمي هنا) — فيُحذف الابنُ
   ويبقى الجذرُ بعدّادٍ موزَّعٍ 1000 **وبلا ابنٍ واحد**، وهو بذاته 51 من
   75 خللًا في ثابتِ «الأبُ يحمل العدّاد». */
$FAMILY = 'M18T';

$teardown = function () use ($conn, $MARK, $FAMILY) {
    $conn->query("DELETE d FROM fin_dues d JOIN suppliers s ON s.id = d.party_ref
                   WHERE d.party_type='supplier' AND s.name LIKE '%{$MARK}%'");
    $conn->query("DELETE c FROM supplier_contract_closures c JOIN suppliers s ON s.id = c.supplier_id
                   WHERE s.name LIKE '%{$MARK}%'");
    /* الحاوياتُ: **الأبناءُ قبلَ الآباءِ · بعائلةِ الوسمِ · بمُرجَعٍ مفحوصٍ ·
       وبسلسلةِ المُشيرين الثلاثيةِ** — كلُّها في الكنسِ المشترَك. */
    require_once __DIR__ . '/_container_sweep.php';
    ems_sweep_container_family($conn, $FAMILY . '%');
    $conn->query("DELETE a FROM supplier_advance_requests a JOIN suppliers s ON s.id = a.supplier_id
                   WHERE s.name LIKE '%{$MARK}%'");
    $ids = array();
    $r = $conn->query("SELECT id FROM supplier_contracts WHERE notes LIKE '{$MARK}%'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM supplier_contract_lines WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM supplier_contracts WHERE id = {$cid}");
    }
    $conn->query("DELETE FROM suppliers WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-18 — تصفيةُ إنهاء عقد المورد ══\n");

// ── البذر ─────────────────────────────────────────────────────────────────
head('البذر — موردٌ وعقدٌ بضمانٍ ومهلة');

$conn->query("INSERT INTO suppliers (company_id, name, phone, created_at)
              VALUES ({$CO}, 'موردُ {$MARK}', '0000000000', NOW())");
$SUP = intval($conn->insert_id);
check($SUP > 0, 'موردٌ مبذور');

$r = SCS::createContract($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'start_date' => '2095-01-01', 'end_date' => '2095-03-31',
    'currency' => 'USD', 'notes' => $MARK . ' عقدُ اختبار التصفية'), $ACTOR);
$C1 = intval($r['contract_id']);
check($C1 > 0, 'وعقدُ موردٍ أُنشئ');

// ═══ ① ضمانٌ بلا مهلة ═══
head('① **ضمانٌ بلا مهلةٍ مكتوبةٍ مستحيل** (CON-03 §2-⑦ · ENT-02 §4)');

$conn->query("UPDATE supplier_contracts SET performance_guarantee = 5000 WHERE id = {$C1}");
$g = $conn->query("SELECT performance_guarantee FROM supplier_contracts WHERE id={$C1}")->fetch_assoc();
check($g['performance_guarantee'] === null,
      '★ ضمانٌ بلا مهلةِ ردٍّ **يرفضه CHECK** — «بعد مهلته» بلا مهلةٍ نصٌّ بلا معنى');

$okW = $conn->query("UPDATE supplier_contracts SET performance_guarantee = 5000,
                     guarantee_retention_days = 60 WHERE id = {$C1}");
$g = $conn->query("SELECT performance_guarantee, guarantee_retention_days
                     FROM supplier_contracts WHERE id={$C1}")->fetch_assoc();
check($okW && abs((float)$g['performance_guarantee'] - 5000.0) < 0.005
      && intval($g['guarantee_retention_days']) === 60,
      'وبمهلةٍ مكتوبةٍ (60 يومًا): يُقبل');

// ═══ ② التصفيةُ عند الإنهاء ═══
head('② **التصفيةُ عند الإنهاء لا قبله** · وواحدةٌ للعقد');

$conn->query("UPDATE supplier_contracts SET state='نافذ' WHERE id={$C1}");
$r = SCL::open($conn, $gate, $CO, $C1, $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'عند الإنهاء لا قبله') !== false,
      '★ عقدٌ «نافذ» ⇒ **422** بحالته — «أنهِ العقدَ ثم صفِّه»');

$conn->query("UPDATE supplier_contracts SET state='منتهٍ' WHERE id={$C1}");
$r = SCL::open($conn, $gate, $CO, $C1, $ACTOR);
check($r['ok'], 'وبعد الإنهاء: التصفيةُ تُفتح');
$CL = intval($r['closure_id']);

$r2 = SCL::open($conn, $gate, $CO, $C1, $ACTOR);
check(!$r2['ok'] && $r2['code'] === 409 && intval($r2['closure_id']) === $CL,
      'وفتحٌ ثانٍ **409 بمرجع القائمة** — «بمفتاح (العقد × التصفية)»');

$cl = SCL::head($gate, $CL);
check($cl && (string) $cl['guarantee_due_date'] === '2095-05-30',
      '★ وموعدُ ردّ الضمان **محسوبٌ من نهاية العقد + المهلة** = 2095-05-30 (وُجد '
      . ($cl ? $cl['guarantee_due_date'] : '—') . ')');

// ═══ ③ إقفالُ الحصة ═══
head('③ **إقفالُ حصةٍ لم تُستهلك يلزمه سببٌ مكتوب**');

// قيودُ الهرم القائم (H-01) تُحترم لا يُلتفّ عليها: `allocated_qty ≤ cap_qty`
// و«غيرُ الرئيسية يلزمها أبٌ» — و**المستوياتُ والحالاتُ عربيةٌ في مصدرها**.
$conn->query("INSERT INTO op_containers (company_id, container_no, level, work_model, unit_type,
              cap_qty, allocated_qty, consumed_qty, state, origin, created_at)
              VALUES ({$CO}, '{$MARK}-ROOT', 'رئيسية', 'hour', 'hour',
                      1000, 1000, 0, 'نشطة', 'عقد', NOW())");
$ROOT = intval($conn->insert_id);
$okC = $conn->query("INSERT INTO op_containers (company_id, container_no, supplier_id, parent_id, level,
                     work_model, unit_type, cap_qty, allocated_qty, consumed_qty, state, origin, created_at)
                     VALUES ({$CO}, '{$MARK}-C1', {$SUP}, {$ROOT}, 'مورد', 'hour', 'hour',
                             1000, 1000, 400, 'نشطة', 'مشتقّة', NOW())");
check($okC, 'حاويةٌ مفتوحةٌ للمورد (1000 مخصَّصة · 400 مستهلَكة) ' . ($okC ? '' : $conn->error));

$r = SCL::closeQuota($conn, $gate, $CO, $CL, '', $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'سببٌ مكتوب') !== false,
      '★ إقفالٌ بلا سببٍ **422** — «كما لا يُتجاوز السقفُ صامتًا لا يُقفل الباقي صامتًا»');

$r = SCL::closeQuota($conn, $gate, $CO, $CL, 'انتهاءُ العقد وعدمُ تجديده — محضر 2095/4', $ACTOR);
check($r['ok'] && $r['closed'] === 1, 'وبسببٍ مكتوب: أُقفلت الحاوية');
$q = $conn->query("SELECT state, close_reason FROM op_containers
                    WHERE container_no='{$MARK}-C1'")->fetch_assoc();
check($q && (string) $q['state'] === 'مقفلة' && mb_strpos((string) $q['close_reason'], 'محضر 2095/4') !== false,
      'والسببُ **مكتوبٌ في الحاوية نفسِها** لا في التصفية فقط');

// ═══ ④ لا إخلاءَ ورصيدُ سلفةٍ قائم ═══
head('④ **لا إخلاءَ ورصيدُ سلفةٍ قائم** (ENT-02 §4)');

// `ck_sadv_inst` (M-12) يوجب جدولَ استردادٍ حقيقيًّا — والبذرُ يحترمه.
$okA = $conn->query("INSERT INTO supplier_advance_requests (company_id, supplier_id, advance_type,
                     amount, currency, doc_ref, issued_date, state, recovered,
                     installments_count, installment_amount, created_at)
                     VALUES ({$CO}, {$SUP}, 'cash', 1200, 'USD', 'SND-{$MARK}', '2095-02-01',
                             'approved', 200, 6, 200, NOW())");
check($okA, 'سلفةٌ معتمَدةٌ 1200 استُرد منها 200 ' . ($okA ? '' : $conn->error));

$r = SCL::settleAdvances($conn, $gate, $CO, $CL, $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && abs($r['balance'] - 1000.0) < 0.005
      && mb_strpos($r['reason'], 'يُحوَّل ذمّةً') !== false,
      '★ رصيدُ 1000 مفتوحٌ ⇒ **423 برصيده** — «الرقمُ لا يضيع بالإنهاء»');

$r = SCL::close($conn, $gate, $CO, $CL, 'CLR-' . $MARK, $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && mb_strpos($r['reason'], 'تسويةُ العهد والسلف') !== false,
      'والإخلاءُ **423 والخطوةُ الناقصةُ باسمها** — الرفضُ يقول ما يُفعل');

$conn->query("UPDATE supplier_advance_requests SET recovered = amount, state='settled'
               WHERE doc_ref = 'SND-{$MARK}'");
$r = SCL::settleAdvances($conn, $gate, $CO, $CL, $ACTOR);
check($r['ok'] && abs($r['balance']) < 0.005, 'وبعد الاسترداد الكامل: الخطوةُ تمرّ');

// ═══ ⑤ ردُّ الضمان بعد مهلته ═══
head('⑤ **ردُّ الضمان بعد مهلته** — وأثرُه ذمّةٌ باسمها');

$r = SCL::releaseGuarantee($conn, $gate, $CO, $CL, '2095-04-15', $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && mb_strpos($r['reason'], '2095-05-30') !== false,
      '★ ردٌّ قبل المهلة **423 بتاريخ استحقاقه** — لا رفضٌ أعمى');

$r = SCL::releaseGuarantee($conn, $gate, $CO, $CL, '2095-06-01', $ACTOR);
check($r['ok'] && intval($r['due_id']) > 0, 'وبعدها: يُردُّ وتُولَّد ذمّة');
$DUE = intval($r['due_id']);
$d = $conn->query("SELECT due_type, direction, amount, source_doc_type, source_doc_id
                     FROM fin_dues WHERE id={$DUE}")->fetch_assoc();
check($d && (string) $d['due_type'] === 'guarantee_release' && (string) $d['direction'] === 'credit'
      && abs((float) $d['amount'] - 5000.0) < 0.005,
      '★ والذمّةُ **دائنةٌ 5000 باسمها `guarantee_release`** — لا «أخرى» ولا «تسوية»');
check($d && (string) $d['source_doc_type'] === 'supplier_closure' && intval($d['source_doc_id']) === $CL,
      'وتحمل **مرجعَ تصفيتها** — أثرٌ لا وسمٌ في خانة');

$r = SCL::releaseGuarantee($conn, $gate, $CO, $CL, '2095-06-02', $ACTOR);
check(!$r['ok'] && $r['code'] === 409 && intval($r['due_id']) === $DUE,
      'وردٌّ ثانٍ **409 بمرجع الأول** — لا يُردُّ ضمانٌ مرتين');

// ═══ ⑥ شهادةُ الإخلاء ═══
head('⑥ **شهادةُ إخلاءٍ موثَّقة** — خدمةً وقيدًا');

$r = SCL::close($conn, $gate, $CO, $CL, '   ', $ACTOR);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'الإخلاءُ بلا مستندٍ كلامٌ') !== false,
      'إخلاءٌ بلا مستندٍ **422**');

$conn->query("UPDATE supplier_contract_closures SET state='closed' WHERE id={$CL}");
$st = $conn->query("SELECT state FROM supplier_contract_closures WHERE id={$CL}")->fetch_assoc();
check($st && (string) $st['state'] !== 'closed',
      '★ وإقفالٌ مباشرٌ بلا مستند **يرفضه CHECK** — بنيويًّا لا بفحصٍ يُنسى');

$r = SCL::close($conn, $gate, $CO, $CL, 'CLR-' . $MARK . '/2095', $ACTOR);
check($r['ok'], 'وبشهادةٍ موثَّقةٍ وخطواتٍ تامّة: أُقفلت التصفية');
$r2 = SCL::close($conn, $gate, $CO, $CL, 'CLR-2', $ACTOR);
check(!$r2['ok'] && $r2['code'] === 409, 'وإقفالٌ ثانٍ **409** — والتصحيحُ بعدها بعكسٍ موثَّق');

// ═══ ⑦ الوصلُ الحي ═══
head('⑦ **الوصلُ الحي** — «لا إقفالَ لعقدٍ بلا تصفيةِ إخلاء»');

$r = SCS::createContract($conn, $gate, $CO, array(
    'supplier_id' => $SUP, 'start_date' => '2095-06-01', 'end_date' => '2095-09-30',
    'currency' => 'USD', 'notes' => $MARK . ' عقدٌ ثانٍ بلا تصفية'), $ACTOR);
$C2 = intval($r['contract_id']);
$conn->query("UPDATE supplier_contracts SET state='منتهٍ' WHERE id={$C2}");
$r = SCS::transition($conn, $gate, $CO, $C2, CSM::CLOSED, 0, $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && mb_strpos($r['reason'], 'لا تصفيةَ إنهاءٍ') !== false,
      '★ عقدٌ بلا تصفيةٍ **لا يُقفل — 423** ونصُّه يقول ما يُفعل');

$r = SCS::transition($conn, $gate, $CO, $C1, CSM::CLOSED, 0, $ACTOR);
check($r['ok'] && (string) $r['state'] === 'مقفل',
      '★★ والعقدُ المصفَّى **يُقفل** — «إقفالُ الحصة وتسويةُ السلف وردُّ الضمان وشهادةُ الإخلاء» شرطًا حيًّا');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
