<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-22 — اختبار قبول: تصفيةُ إنهاء خدمة الموظف المحسوبة (ENT-01 §5 · §6 · §8-E6)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/final_settlement_test.php
 *
 * ما يُثبته:
 *   ① **التصفيةُ يفتحها الإنهاء**: عقدٌ نافذٌ ⇒ 422 · وتصفيتان لعقدٍ ⇒ 409.
 *   ② **بلا قاعدةٍ مكتوبةٍ لا احتساب**: بندٌ **بصفرٍ و`computable=0` وسببٍ مكتوب**
 *      — لا رقمَ يُقدَّر ولا صفرٌ صامت.
 *   ③ **من اللقطة**: كلُّ تصفيةٍ تحمل `snapshot_id` وبصمتَه · وطريقةُ حسابٍ لا
 *      يحملها أساسٌ شهريٌّ ثابت **تُعلَن** ولا تدخل الأساسَ صامتة.
 *   ④ **الاحتسابُ يطابق اليدوي**: 30 يومًا/سنة × 0.999 × (3000 ÷ 30) = **2997**.
 *   ⑤ **والمقاصّةُ لا تتجاوز المستحق**: الباقي **يُعلَن** رصيدًا مفتوحًا.
 *   ⑥ **اليدان مفصولتان** (403) · **ولا اعتمادَ بلا مرفق إخلاء** (422 و`CHECK`).
 *   ⑦ **حدثٌ ماليٌّ واحدٌ لا يتكرر**: ذمّةٌ واحدةٌ **باسمها** · وحقيقةٌ واحدةٌ في
 *      الجذر بمفتاح (العقد × التصفية) · واعتمادٌ ثانٍ ⇒ 409.
 *   ⑧ **والمعتمَدةُ لا تُلغى** — التصحيحُ بحركةٍ عاكسة (423).
 *
 * البذرُ معزول: موظفان وعقدان في 2093 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '4', 'company_id' => 4, 'name' => 'M22 settlement test');

require_once dirname(__DIR__) . '/app/Services/Payroll/FinalSettlementService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ContractSnapshotService.php';

use App\Services\Payroll\FinalSettlementService as FS;
use App\Services\Contract\ContractSnapshotService as SNAP;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate    = ems_tenant_db();
$CO      = 4;
$PREP    = 999221;   // اليدُ التي تُعدّ
$APPR    = 999222;   // ويدُ الإجازة غيرُها
$MARK    = 'M22T' . getmypid();

// عملةٌ مسجَّلةٌ للشركة — `fin_dues` يحرسها بمفتاحٍ خارجي (لا اختراع)
$CUR = 'USD';
$rc = $conn->query("SELECT code FROM fin_currencies WHERE company_id={$CO} ORDER BY code LIMIT 1");
if ($rc && ($x = $rc->fetch_assoc())) { $CUR = (string) $x['code']; }

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE b FROM ems_business_events b
                    JOIN employee_final_settlements f ON f.id = b.entity_id
                    JOIN employees e ON e.id = f.employee_id
                   WHERE b.entity_type = 'employee_final_settlement' AND e.name LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM employee_final_settlement_lines l
                    JOIN employee_final_settlements f ON f.id = l.settlement_id
                    JOIN employees e ON e.id = f.employee_id WHERE e.name LIKE '%{$MARK}%'");
    $conn->query("DELETE f FROM employee_final_settlements f
                    JOIN employees e ON e.id = f.employee_id WHERE e.name LIKE '%{$MARK}%'");
    $conn->query("DELETE d FROM fin_dues d JOIN employees e ON e.id = d.party_ref
                   WHERE d.party_type='employee' AND e.name LIKE '%{$MARK}%'");
    $conn->query("DELETE a FROM employee_advances a JOIN employees e ON e.id = a.person_id
                   WHERE e.name LIKE '%{$MARK}%'");
    $conn->query("DELETE w FROM worker_leave_absence w JOIN employees e ON e.id = w.employee_id
                   WHERE e.name LIKE '%{$MARK}%'");
    $conn->query("DELETE s FROM contract_snapshots s
                    JOIN employee_contracts c ON c.id = s.contract_id
                    JOIN employees e ON e.id = c.employee_id WHERE e.name LIKE '%{$MARK}%'");
    $conn->query("DELETE p FROM pay_components p
                    JOIN employee_contracts c ON c.id = p.contract_id
                    JOIN employees e ON e.id = c.employee_id WHERE e.name LIKE '%{$MARK}%'");
    $conn->query("DELETE c FROM employee_contracts c JOIN employees e ON e.id = c.employee_id
                   WHERE e.name LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM employees WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-22 — تصفيةُ إنهاء خدمة الموظف المحسوبة ══\n");

head('البذر — موظفان بعقدين منتهيين (أساسٌ شهريٌّ 3000)');

$mkEmp = function ($suffix) use ($conn, $CO, $MARK) {
    $conn->query("INSERT INTO employees (employee_type, company_id, name, phone, is_workforce, created_at)
                  VALUES ('عامل', {$CO}, 'موظفُ {$MARK}-{$suffix}', '0100000000', 1, NOW())");
    return intval($conn->insert_id);
};
$mkContract = function ($empId, $start) use ($conn, $CO, $CUR) {
    $conn->query("INSERT INTO employee_contracts
                  (company_id, employee_id, category, pay_model_id, start_date, end_date,
                   currency, state, version, created_by, created_at)
                  VALUES ({$CO}, {$empId}, 'permanent', 1, '{$start}', '2095-12-31',
                          '{$CUR}', 'active', 1, 1, NOW())");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); }
    return intval($conn->insert_id);
};
$mkComp = function ($cid, $type, $method, $value, $rate, $eos, $lv) use ($conn, $CO) {
    $v = $value === null ? 'NULL' : (float) $value;
    $r = $rate === null ? 'NULL' : (float) $rate;
    $conn->query("INSERT INTO pay_components
                  (company_id, contract_id, component_type, calc_method, value, rate,
                   in_eos, in_leave_pay, state, created_at)
                  VALUES ({$CO}, {$cid}, '{$type}', '{$method}', {$v}, {$r}, {$eos}, {$lv}, 'active', NOW())");
    if ($conn->error) { fwrite(STDOUT, '  ! ' . $conn->error . "\n"); }
    return intval($conn->insert_id);
};

$E1 = $mkEmp('A'); $C1 = $mkContract($E1, '2093-01-01');
$E2 = $mkEmp('B'); $C2 = $mkContract($E2, '2093-01-01');
check($E1 > 0 && $C1 > 0 && $E2 > 0 && $C2 > 0, 'موظفان وعقدان');

$mkComp($C1, 'basic', 'fixed_amount', 3000, null, 1, 1);
// مكوّنٌ بطريقةٍ لا يحملها أساسٌ شهريٌّ ثابت — **يُعلَن ولا يدخل الأساس**
$PD = $mkComp($C1, 'site', 'per_day', null, 25, 1, 0);
$mkComp($C2, 'basic', 'fixed_amount', 3000, null, 1, 0);

// إجازةٌ اعتيادية 10 أيام داخل المدة (والتبادليةُ لا تستهلك الرصيد)
$conn->query("INSERT INTO worker_leave_absence
              (company_id, employee_id, event_class, event_type, date_from, date_to, state, created_at)
              VALUES ({$CO}, {$E1}, 'مخطّط', 'اعتيادية', '2093-03-01', '2093-03-10', 'معتمد', NOW())");
$conn->query("INSERT INTO worker_leave_absence
              (company_id, employee_id, event_class, event_type, date_from, date_to, state, created_at)
              VALUES ({$CO}, {$E1}, 'مخطّط', 'تبادلية', '2093-05-01', '2093-05-20', 'معتمد', NOW())");

// مستحقٌّ قائمٌ 500 (اعتُرف به في مصدره)
$conn->query("INSERT INTO fin_dues
              (company_id, party_type, party_ref, due_type, direction, amount, currency,
               period_ref, source_doc_type, created_at)
              VALUES ({$CO}, 'employee', {$E1}, 'salary', 'credit', 500, '{$CUR}',
                      '2093-12', 'pending_source', NOW())");
if ($conn->error) { fwrite(STDOUT, '  ! dues: ' . $conn->error . "\n"); }

// سلفةٌ 2000 لـA (تُقاصّ كاملةً) · و9000 لـB (تتجاوز المستحقَّ فتُقصّ)
$conn->query("INSERT INTO employee_advances
              (company_id, person_id, advance_type, amount, currency, doc_ref, issued_date,
               installments_count, installment_amount, recovered, state, created_at)
              VALUES ({$CO}, {$E1}, 'cash', 2000, '{$CUR}', 'ADV-{$MARK}-A', '2093-06-01',
                      4, 500, 0, 'active', NOW())");
$conn->query("INSERT INTO employee_advances
              (company_id, person_id, advance_type, amount, currency, doc_ref, issued_date,
               installments_count, installment_amount, recovered, state, created_at)
              VALUES ({$CO}, {$E2}, 'cash', 9000, '{$CUR}', 'ADV-{$MARK}-B', '2093-06-01',
                      9, 1000, 0, 'active', NOW())");

$AS_OF = '2094-01-01';   // 365 يومًا ⇒ 0.999 سنةَ خدمة

// ═══ ① التصفيةُ يفتحها الإنهاء ═══
head('① **التصفيةُ يفتحها الإنهاء** (§5)');

$r = FS::open($conn, $gate, $CO, $C1, $AS_OF, $PREP);
check(!$r['ok'] && $r['code'] === 422 && mb_strpos($r['reason'], 'يفتحها الإنهاء') !== false,
      '★ عقدٌ نافذٌ ⇒ **422** — «أنهِ العقدَ ثم صفِّ»');

$conn->query("UPDATE employee_contracts SET state='terminated' WHERE id IN ({$C1},{$C2})");

// ═══ ② بلا قاعدةٍ مكتوبةٍ لا احتساب ═══
head('② **بلا قاعدةٍ مكتوبةٍ لا احتساب** — يُعلَن ولا يُقدَّر');

$calc = FS::compute($conn, $gate, $CO, $C1, $AS_OF, $PREP);
check($calc['ok'], 'الاحتسابُ يعمل على عقدٍ منتهٍ (قناةُ التصفية)');

$byType = array();
foreach ($calc['lines'] as $l) { $byType[$l['line_type']] = $l; }
check(isset($byType['eos']) && (int) $byType['eos']['computable'] === 0
      && abs((float) $byType['eos']['amount']) < 0.005
      && mb_strpos($byType['eos']['source_note'], 'لا قاعدةَ نهاية خدمةٍ') !== false,
      '★★ بلا `eos_days_per_year`: البندُ **صفرٌ و`computable=0` وسببٌ مكتوب**');
check(isset($byType['leave']) && (int) $byType['leave']['computable'] === 0,
      'وكذلك رصيدُ الإجازة بلا قاعدته');

// ── تُكتب القاعدتان ──
$conn->query("UPDATE employee_contracts SET eos_days_per_year=30, leave_days_per_year=21 WHERE id={$C1}");
$conn->query("UPDATE employee_contracts SET eos_days_per_year=30 WHERE id={$C2}");
// اللقطةُ الملتقطةُ قبل الكتابة لا تعرف القاعدتين — والقاعدتان على **الرأس** لا في
// اللقطة، فالاحتسابُ يقرؤهما من العقد ويقرأ **الأسسَ** من اللقطة (§5 حرفيًّا).
$conn->query("DELETE s FROM contract_snapshots s WHERE s.contract_id IN ({$C1},{$C2})");

// ═══ ③ من اللقطة ═══
head('③ **من اللقطة** — والأساسُ من علَمِه لا من الجميع');

$calc = FS::compute($conn, $gate, $CO, $C1, $AS_OF, $PREP);
check($calc['ok'] && (int) $calc['snapshot_id'] > 0 && strlen((string) $calc['fingerprint']) === 40,
      'الاحتسابُ يحمل لقطتَه #' . intval($calc['snapshot_id']) . ' وبصمتَها');
$v = SNAP::verify($gate, (int) $calc['snapshot_id']);
check(!empty($v['ok']), 'والبصمةُ تطابق مضمونَها — لا تلاعبَ');

check(abs((float) $calc['basis']['eos_monthly_base'] - 3000.0) < 0.005,
      '★ أساسُ نهاية الخدمة **3000** — والبدلُ `per_day` **لم يدخله**');
$un = $calc['basis']['unreadable_components'];
check(is_array($un) && count($un) === 1 && (int) $un[0]['component_id'] === $PD
      && (string) $un[0]['calc_method'] === 'per_day',
      '★★ والمكوّنُ الذي لا يحمله أساسٌ شهريٌّ ثابت **يُعلَن باسمه** — لا يُبتلع صامتًا');

// ═══ ④ الاحتسابُ يطابق اليدوي ═══
head('④ **الاحتسابُ يطابق اليدوي** (0.999 سنةَ خدمة · أجرٌ يوميٌّ 100)');

check(abs((float) $calc['basis']['service_years'] - 0.999) < 0.0005,
      '365 يومًا ⇒ **0.999** سنةَ خدمة');
foreach ($calc['lines'] as $l) { $byType[$l['line_type']] = $l; }

check(abs((float) $byType['eos']['amount'] - 2997.0) < 0.005
      && abs((float) $byType['eos']['qty'] - 29.97) < 0.005
      && abs((float) $byType['eos']['rate'] - 100.0) < 0.005,
      '★★ نهايةُ الخدمة **2997** = 29.97 يومًا × 100 — يدويًّا بلا فرق');
check(abs((float) $byType['leave']['qty'] - 10.98) < 0.005
      && abs((float) $byType['leave']['amount'] - 1098.0) < 0.005,
      '★★ ورصيدُ الإجازة **10.98 يومًا (20.98 − 10 اعتيادية)** = **1098** — والتبادليةُ لم تُستهلك');
check(abs((float) $byType['dues']['amount'] - 500.0) < 0.005,
      'والمستحقُّ القائمُ **500** يُعرض في طبقته');

// ═══ ⑤ المقاصّةُ ظاهرة ═══
head('⑤ **والمقاصّةُ ظاهرةٌ ومحدودة**');

check(abs((float) $byType['advance_offset']['amount'] - 2000.0) < 0.005
      && abs((float) $calc['totals']['advances_remaining']) < 0.005,
      'سلفةُ 2000 تُقاصّ كاملةً (المستحقُّ 4595)');
check(abs((float) $calc['totals']['net'] - 2595.0) < 0.005,
      '★ الصافي **2595** = 500 + 1098 + 2997 − 2000');
check(abs((float) $calc['totals']['recognized'] - 4095.0) < 0.005,
      '★ والمعترَفُ به **جديدًا 4095** (إجازةٌ + نهايةُ خدمة) — والمستحقُّ السابقُ لا يُعاد الاعترافُ به');

$calcB = FS::compute($conn, $gate, $CO, $C2, $AS_OF, $PREP);
check($calcB['ok'] && abs((float) $calcB['totals']['advances'] - 2997.0) < 0.005
      && abs((float) $calcB['totals']['advances_remaining'] - 6003.0) < 0.005
      && abs((float) $calcB['totals']['net']) < 0.005,
      '★★ وسلفةُ 9000 على مستحقِّ 2997: تُقاصّ **2997** و**6003 تبقى رصيدًا مفتوحًا يُعلَن** — والصافي صفر');

// ═══ الفتح ═══
head('الفتحُ — «بمفتاح (العقد × التصفية) لا يتكرر»');

$o = FS::open($conn, $gate, $CO, $C1, $AS_OF, $PREP);
check($o['ok'] && abs($o['net'] - 2595.0) < 0.005, 'فُتحت التصفيةُ #' . intval($o['settlement_id']));
$S1 = (int) $o['settlement_id'];

$o2 = FS::open($conn, $gate, $CO, $C1, $AS_OF, $PREP);
check(!$o2['ok'] && $o2['code'] === 409 && (int) $o2['settlement_id'] === $S1,
      '★ وتصفيةٌ ثانيةٌ للعقد ⇒ **409** بمرجع القائمة');

$oB = FS::open($conn, $gate, $CO, $C2, $AS_OF, $PREP);
check($oB['ok'], 'وفُتحت تصفيةُ العقد الثاني #' . intval($oB['settlement_id']));
$S2 = (int) $oB['settlement_id'];

$h = FS::head($gate, $S1);
check($h && (int) $h['snapshot_id'] > 0 && (string) $h['snapshot_fingerprint'] !== ''
      && abs((float) $h['net_amount'] - 2595.0) < 0.005,
      'والرأسُ يحمل لقطتَه وبصمتَه وصافيَه المحسوب');
check(count(FS::linesOf($gate, $S1)) === 4, 'وأربعةُ بنودٍ كلٌّ بمصدره');

// ═══ ⑥ اليدان مفصولتان ═══
head('⑥ **اليدان مفصولتان** — ولا اعتمادَ بلا مرفق إخلاء');

$a = FS::approve($conn, $gate, $CO, $S1, 'محضر إخلاء 1', $PREP);
check(!$a['ok'] && $a['code'] === 403 && mb_strpos($a['reason'], 'فصلُ اليدين') !== false,
      '★ اعتمادُ من أعدّ ⇒ **403**');

$a = FS::approve($conn, $gate, $CO, $S1, '   ', $APPR);
check(!$a['ok'] && $a['code'] === 422 && mb_strpos($a['reason'], 'مرفقُ الإخلاء') !== false,
      'وبلا مرفق إخلاءٍ ⇒ **422**');

$conn->query("UPDATE employee_final_settlements SET state='approved', approved_by={$APPR}
               WHERE id={$S2}");
$st = $conn->query("SELECT state FROM employee_final_settlements WHERE id={$S2}")->fetch_assoc();
check((string) $st['state'] === 'draft',
      '★★ واعتمادٌ مكتوبٌ مباشرةً بلا مرفقٍ **يرفضه CHECK** — بنيويًّا لا بفحصٍ يُنسى');

// ═══ ⑦ حدثٌ ماليٌّ واحد ═══
head('⑦ **حدثٌ ماليٌّ واحدٌ لا يتكرر** (§5 · §8-E6)');

$a = FS::approve($conn, $gate, $CO, $S1, 'محضر إخلاء ' . $MARK, $APPR);
check($a['ok'] && (int) $a['due_id'] > 0, 'اعتُمدت التصفيةُ وأُنشئت ذمّةُ #' . intval($a['due_id']));

$d = $conn->query("SELECT due_type, direction, amount, source_doc_type, source_doc_id
                     FROM fin_dues WHERE id=" . intval($a['due_id']))->fetch_assoc();
check((string) $d['due_type'] === 'end_of_service' && (string) $d['direction'] === 'credit'
      && abs((float) $d['amount'] - 4095.0) < 0.005
      && (string) $d['source_doc_type'] === 'employee_closure' && (int) $d['source_doc_id'] === $S1,
      '★★ والذمّةُ **باسمها** بمبلغ المعترَف به جديدًا **4095** ومصدرِها المسمّى');

check(abs($a['recovered'] - 2000.0) < 0.005, 'واستُردّت السلفةُ **2000** فعليًّا');
$adv = $conn->query("SELECT recovered, state, balance FROM employee_advances
                      WHERE person_id={$E1}")->fetch_assoc();
check(abs((float) $adv['recovered'] - 2000.0) < 0.005 && (string) $adv['state'] === 'settled',
      'وصارت السلفةُ `settled` برصيدٍ صفر');

$ev = $conn->query("SELECT COUNT(*) c FROM ems_business_events
                     WHERE entity_type='employee_final_settlement' AND entity_id={$S1}")->fetch_assoc();
check((int) $ev['c'] === 1, '★★ وحقيقةٌ **واحدةٌ** في الجذر — «SettlementFinalized مرةً»');
$ek = $conn->query("SELECT idempotency_key, event_key FROM ems_business_events
                     WHERE entity_type='employee_final_settlement' AND entity_id={$S1}")->fetch_assoc();
check($ek && (string) $ek['idempotency_key'] === ('emp_fs:' . $C1 . ':' . $S1),
      'ومفتاحُ عطالتها **حتميٌّ بالعقد والتصفية** لا بالزمن');

$a2 = FS::approve($conn, $gate, $CO, $S1, 'محضر ثانٍ', $APPR);
check(!$a2['ok'] && $a2['code'] === 409, '★ واعتمادٌ ثانٍ ⇒ **409** — صفرُ ذمّةٍ ثانيةٍ وصفرُ حدثٍ ثانٍ');
$n = $conn->query("SELECT COUNT(*) c FROM fin_dues WHERE source_doc_type='employee_closure'
                    AND source_doc_id={$S1}")->fetch_assoc();
check((int) $n['c'] === 1, 'وذمّةٌ واحدةٌ في الدفتر بعد المحاولتين');

// ═══ ⑧ المعتمَدةُ لا تُلغى ═══
head('⑧ **والمعتمَدةُ لا تُلغى** — التصحيحُ بحركةٍ عاكسة');

$cx = FS::cancel($conn, $gate, $CO, $S1, 'ندمنا', $PREP);
check(!$cx['ok'] && $cx['code'] === 423 && mb_strpos($cx['reason'], 'عاكسة') !== false,
      '★ إلغاءُ معتمَدةٍ ⇒ **423** ونصُّه يسمّي طريقَ التصحيح');

$cx = FS::cancel($conn, $gate, $CO, $S2, '', $PREP);
check(!$cx['ok'] && $cx['code'] === 422, 'وإلغاءٌ بلا سببٍ ⇒ **422**');
$cx = FS::cancel($conn, $gate, $CO, $S2, 'أُعيد احتسابُ تاريخ الأثر', $PREP);
check($cx['ok'], 'ومسودةٌ تُلغى بسببها المكتوب');
$st = $conn->query("SELECT state, cancel_reason FROM employee_final_settlements
                     WHERE id={$S2}")->fetch_assoc();
check((string) $st['state'] === 'cancelled' && (string) $st['cancel_reason'] !== '',
      'والسببُ محفوظٌ في الصفّ — لا محو');

// ═══ العزل ═══
head('العزلُ محفوظ');
$leak = $gate->selectOne('employee_final_settlements', array('where' => array('id' => $S1)));
check($leak !== null, 'الصفُّ يُقرأ داخل نطاقه');
// بوابةٌ **جديدةٌ** بنطاقٍ آخر — `ems_tenant_db()` ساكنةٌ في العملية فلا تُعاد بناءً
$_SESSION['user']['company_id'] = 1;
$otherGate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::fromSession());
$leak2 = $otherGate->selectOne('employee_final_settlements', array('where' => array('id' => $S1)));
check($leak2 === null, '★ ولا يُقرأ من نطاقٍ آخر — صفرُ تسريب');
$_SESSION['user']['company_id'] = $CO;

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
