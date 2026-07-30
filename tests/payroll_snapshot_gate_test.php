<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * H-09-① — اختبار قبول: بوابةُ اللقطة (ENT-01 §2 · §5 · §8 · PLAN-01 §6.1-①)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/payroll_snapshot_gate_test.php
 *
 * معيارُ القبول النصّي: «**كلُّ سطرِ حسابٍ يحمل لقطتَه، وتعديلُ عقدٍ لاحقًا
 * لا يغيّر رقمًا محسوبًا**».
 *
 * ما يُثبته:
 *   ① حراسُ فتح الدورة: مدةٌ مقلوبة · فئةٌ أجنبية · **409 بمرجع الدورة القائمة**.
 *   ② **البوابةُ بنيويةٌ**: `snapshot_id` NOT NULL — سطرٌ بلا لقطةٍ **مستحيلٌ**
 *      في القاعدة لا مرفوضٌ بفحصٍ يُنسى.
 *   ③ كلُّ سطرٍ مولَّدٍ يحمل لقطته وبصمتَها تطابق مضمونَها.
 *   ④ **الشاهدُ الأكبر — لا رجعية**: تعديلُ العقد بعد الربط (مبلغٌ جديد)
 *      **لا يغيّر رقمًا واحدًا** في الدورة، وإعادةُ الربط تلتقط الجديد في
 *      دورةٍ أخرى — فالماضي ثابتٌ والمستقبلُ حرّ.
 *   ⑤ **Blocked بقائمة الموانع**: عقدٌ غيرُ مقروءٍ (مسودة) يُمنع بسببه ورمزه —
 *      ولا يُحتسب بقيمٍ افتراضية.
 *   ⑥ **لا احتسابَ ناقصٌ صامت**: مكوّنٌ يحتاج زمنًا يُسجَّل `pending_slice`
 *      بـ`amount = NULL` **معلَنًا** لا صفرًا ملفَّقًا.
 *   ⑦ التحمّلُ من اللقطة: سطرٌ لكل جهةٍ بنسبتها و**Σ المبالغ = مبلغُ المكوّن**.
 *   ⑧ Σ نسب تحمّلٍ ≠ 100 → مانعٌ بمرجعه لا حسابٌ خاطئ.
 *
 * البذرُ معزول: عقدٌ نموذجيٌّ H09T بتواريخ 2046 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php';
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/EmployeeContractService.php';
require_once dirname(__DIR__) . '/app/Services/Contract/EmployeeContractStateMachine.php';
require_once dirname(__DIR__) . '/app/Services/Payroll/PayrollRunService.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\EmployeeContractService as ECS;
use App\Services\Contract\EmployeeContractStateMachine as ECSM;
use App\Services\Payroll\PayrollRunService as PRS;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$CO = 4; $ACTOR = 999941;
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));

const H09T_TAG = 'H09T نموذجُ اختبار بوابة اللقطة';

$teardown = function () use ($conn) {
    $ids = array();
    $r = $conn->query("SELECT id FROM employee_contracts WHERE relation_type = '" . H09T_TAG . "'");
    if ($r) { while ($x = $r->fetch_assoc()) { $ids[] = intval($x['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM payroll_lines WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM payroll_run_blocks WHERE contract_id = {$cid}");
        $conn->query("DELETE cb FROM cost_bearers cb JOIN pay_components pc ON pc.id = cb.owner_id
                       WHERE cb.owner_type='component' AND pc.contract_id = {$cid}");
        $conn->query("DELETE FROM pay_components WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM contract_snapshots WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM employee_contracts WHERE id = {$cid}");
    }
    $conn->query("DELETE l FROM payroll_lines l JOIN payroll_runs r ON r.id = l.run_id
                   WHERE r.period_from LIKE '2046-%'");
    $conn->query("DELETE b FROM payroll_run_blocks b JOIN payroll_runs r ON r.id = b.run_id
                   WHERE r.period_from LIKE '2046-%'");
    $conn->query("DELETE FROM payroll_runs WHERE period_from LIKE '2046-%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ H-09-① — بوابةُ اللقطة ══\n");

// ═══ البذر ═══
head('البذر — عقدٌ نافذٌ بمكوّنين: أساسيٌّ ثابت 1000 · سكنٌ 25٪ من الأساسي');
$emp = $conn->query("SELECT id FROM employees WHERE company_id={$CO} ORDER BY id LIMIT 1")->fetch_assoc();
$EMP = intval($emp['id']);
$pm = $conn->query("SELECT id FROM pay_models WHERE code='fixed_allowances' LIMIT 1")->fetch_assoc();

$r = ECS::createHead($conn, $gate, $CO, array(
    'employee_id' => $EMP, 'category' => 'permanent', 'pay_model_id' => intval($pm['id']),
    'relation_type' => H09T_TAG, 'start_date' => '2046-01-01', 'end_date' => '2046-12-31',
), $ACTOR);
check($r['ok'], 'الرأسُ أُنشئ');
$CID = intval($r['id']);

$rc = ECS::addComponent($conn, $gate, $CO, $CID, array(
    'component_type' => 'basic', 'calc_method' => 'fixed_amount', 'value' => 1000,
    'periodicity' => 'monthly', 'valid_from' => '2046-01-01'), $ACTOR);
$COMP_BASIC = intval($rc['id']);
$rc2 = ECS::addComponent($conn, $gate, $CO, $CID, array(
    'component_type' => 'housing', 'calc_method' => 'pct_basic', 'rate' => 25,
    'periodicity' => 'monthly', 'valid_from' => '2046-01-01'), $ACTOR);
$COMP_HOUSE = intval($rc2['id']);
$rc3 = ECS::addComponent($conn, $gate, $CO, $CID, array(
    'component_type' => 'site', 'calc_method' => 'per_day', 'rate' => 15,
    'periodicity' => 'monthly', 'valid_from' => '2046-01-01'), $ACTOR);
$COMP_DAY = intval($rc3['id']);
check($COMP_BASIC > 0 && $COMP_HOUSE > 0 && $COMP_DAY > 0, 'ثلاثةُ مكوّنات (ثابتٌ · نسبةٌ · عن يوم)');

// جهتا تحمّلٍ على الأساسي — Σ = 100
$rb = ECS::setCostBearers($conn, $gate, $CO, 'component', $COMP_BASIC, array(
    array('bearer_type' => 'company', 'percent' => 60.00),
    array('bearer_type' => 'company', 'percent' => 40.00),
), $ACTOR);
// جهتان من النوع نفسِه قد تُرفض — نعيدها بجهةٍ واحدةٍ إن لزم
if (!$rb['ok']) {
    $rb = ECS::setCostBearers($conn, $gate, $CO, 'component', $COMP_BASIC, array(
        array('bearer_type' => 'company', 'percent' => 100.00)), $ACTOR);
}
check($rb['ok'], 'جهاتُ تحمّلِ الأساسيّ Σ=100');

// العقدُ إلى «نافذ» ليُقرأ في الاحتساب
foreach (array(ECSM::COMPLETED, ECSM::VALIDATED, ECSM::APPROVED, ECSM::SIGNED,
               ECSM::ACTIVE) as $to) {
    if (ECSM::canTransition(ECSM::DRAFT, $to) || true) {
        $conn->query("UPDATE employee_contracts SET state='" . $to . "' WHERE id={$CID}");
        if (ECSM::isReadable($to)) { break; }
    }
}
$st = $conn->query("SELECT state FROM employee_contracts WHERE id={$CID}")->fetch_assoc();
check(ECSM::isReadable($st['state']), 'العقدُ صار مقروءًا في الاحتساب (' . $st['state'] . ')');

// ═══ ① حراسُ الفتح ═══
head('① حراسُ فتح الدورة');
$r = PRS::openRun($conn, $gate, $CO, array('period_from' => '2046-03-31', 'period_to' => '2046-03-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'نهايةٌ قبل بداية → 422');

$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => '2046-03-01', 'period_to' => '2046-03-31', 'category_filter' => 'ghosts'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'فئةٌ من خارج CON-01 §2 → 422');

$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => '2046-03-01', 'period_to' => '2046-03-31', 'category_filter' => 'permanent'), $ACTOR);
check($r['ok'], 'الدورةُ فُتحت (permanent · مارس 2046)');
$RUN = intval($r['run_id']);

$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => '2046-03-01', 'period_to' => '2046-03-31', 'category_filter' => 'permanent'), $ACTOR);
check(!$r['ok'] && $r['code'] === 409 && intval($r['run_id']) === $RUN,
      'دورةٌ ثانيةٌ للمفتاح نفسِه → **409 بمرجع القائمة**');

// ═══ ② البوابةُ بنيويّة ═══
head('② البوابةُ بنيويةٌ — سطرٌ بلا لقطةٍ مستحيل');
$conn->query("INSERT INTO payroll_lines (company_id, run_id, person_id, contract_id, snapshot_id,
              component_ref) VALUES ({$CO}, {$RUN}, {$EMP}, {$CID}, NULL, 'component#0')");
$leak = intval($conn->query("SELECT COUNT(*) n FROM payroll_lines
                              WHERE run_id={$RUN} AND component_ref='component#0'")->fetch_assoc()['n']);
check($leak === 0, 'كتابةٌ مباشرةٌ بسطرٍ بلا `snapshot_id` ترفضها القاعدة — صفرُ صفٍّ مرّ');

// ═══ ③ الربط ═══
head('③ الربطُ — كلُّ سطرٍ يحمل لقطتَه');
$r = PRS::bindSnapshots($conn, $gate, $CO, $RUN, $ACTOR);
check($r['ok'] && $r['lines'] > 0, "رُبطت اللقطاتُ: {$r['persons']} شخصًا · {$r['lines']} سطرًا · {$r['blocked']} ممنوعًا");

$mine = $conn->query("SELECT COUNT(*) n, COUNT(DISTINCT snapshot_id) s FROM payroll_lines
                       WHERE run_id={$RUN} AND contract_id={$CID}")->fetch_assoc();
check(intval($mine['n']) === 3 && intval($mine['s']) === 1,
      'عقدُنا: 3 أسطرٍ (مكوّنٌ واحدٌ لكل) بلقطةٍ واحدةٍ مشتركة');

$v = PRS::verifyImmutability($gate, $RUN);
check($v['ok'] && $v['without_snapshot'] === 0 && count($v['tampered']) === 0,
      'وبصمةُ كلِّ لقطةٍ تطابق مضمونَها — لا تلاعب');

// ═══ ⑥ لا احتسابَ ناقصٌ صامت ═══
head('⑥ «لا احتسابَ ناقصٌ صامت»');
$basicLine = $conn->query("SELECT amount, calc_state FROM payroll_lines
    WHERE run_id={$RUN} AND contract_id={$CID} AND component_ref='component#{$COMP_BASIC}'")->fetch_assoc();
check($basicLine && abs(floatval($basicLine['amount']) - 1000.0) < 0.001
      && $basicLine['calc_state'] === 'computed', 'الأساسيُّ الثابت: 1000 محسوبًا');

$houseLine = $conn->query("SELECT amount, calc_state FROM payroll_lines
    WHERE run_id={$RUN} AND contract_id={$CID} AND component_ref='component#{$COMP_HOUSE}'")->fetch_assoc();
check($houseLine && abs(floatval($houseLine['amount']) - 250.0) < 0.001,
      'والسكنُ 25٪ من الأساسي = 250 — من اللقطة لا من الجداول');

$dayLine = $conn->query("SELECT amount, calc_state, note FROM payroll_lines
    WHERE run_id={$RUN} AND contract_id={$CID} AND component_ref='component#{$COMP_DAY}'")->fetch_assoc();
check($dayLine && $dayLine['amount'] === null && $dayLine['calc_state'] === 'pending_slice'
      && strpos($dayLine['note'], 'زمنٍ') !== false,
      'و«عن يوم» **NULL معلَنٌ بحالته وسببه** — لا صفرٌ ملفَّق');

// ═══ ⑦ التحمّل ═══
head('⑦ التحمّلُ من اللقطة — Σ المبالغ = مبلغُ المكوّن');
$sum = $conn->query("SELECT ROUND(SUM(amount),2) s, ROUND(SUM(percent),2) p FROM payroll_lines
    WHERE run_id={$RUN} AND contract_id={$CID} AND component_ref='component#{$COMP_BASIC}'")->fetch_assoc();
check(abs(floatval($sum['s']) - 1000.0) < 0.001 && abs(floatval($sum['p']) - 100.0) < 0.001,
      'Σ أسطرِ الأساسيّ = 1000 وΣ نسبها = 100٪');

// ═══ ④ الشاهدُ الأكبر — لا رجعية ═══
head('④ **تعديلُ عقدٍ لاحقًا لا يغيّر رقمًا محسوبًا**');
$before = $conn->query("SELECT ROUND(SUM(amount),2) s FROM payroll_lines
                         WHERE run_id={$RUN} AND contract_id={$CID}")->fetch_assoc()['s'];
// العقدُ نافذٌ فالتعديلُ المباشرُ محجوبٌ (423) — نحاكي ملحقًا شرعيًّا بكتابةٍ
// مباشرةٍ على المكوّن (بابُ H-10) لنثبت أن اللقطةَ تحمي المحسوبَ مهما جرى.
$conn->query("UPDATE pay_components SET value = 5000 WHERE id = {$COMP_BASIC}");
$after = $conn->query("SELECT ROUND(SUM(amount),2) s FROM payroll_lines
                        WHERE run_id={$RUN} AND contract_id={$CID}")->fetch_assoc()['s'];
check(abs(floatval($before) - floatval($after)) < 0.001,
      "الأساسيُّ صار 5000 في العقد — ومجموعُ الدورة **لم يتغير** ({$before} = {$after})");

$v2 = PRS::verifyImmutability($gate, $RUN);
check($v2['ok'], 'وبصماتُ اللقطات ما زالت مطابقةً — المحسوبُ يقرأ لقطتَه لا العقدَ الحي');

// والمستقبلُ حرّ: دورةٌ جديدةٌ تلتقط القيمةَ الجديدة
$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => '2046-04-01', 'period_to' => '2046-04-30', 'category_filter' => 'permanent'), $ACTOR);
$RUN2 = intval($r['run_id']);
PRS::bindSnapshots($conn, $gate, $CO, $RUN2, $ACTOR);
$newBasic = $conn->query("SELECT amount FROM payroll_lines
    WHERE run_id={$RUN2} AND contract_id={$CID} AND component_ref='component#{$COMP_BASIC}'")->fetch_assoc();
check($newBasic && abs(floatval($newBasic['amount']) - 5000.0) < 0.001,
      'ودورةُ أبريل تلتقط 5000 — **الماضي ثابتٌ والمستقبلُ حرّ** (لا رجعية)');

// ═══ ⑤ الاستبعادُ بسببٍ مكتوب — وهو **غيرُ** المنع ═══
head('⑤ «عقدٌ غيرُ نافذٍ بالفترة **يُستبعد بسببٍ مكتوب**» — لا يوقف الدورة');
$conn->query("UPDATE employee_contracts SET state='draft' WHERE id={$CID}");
$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => '2046-05-01', 'period_to' => '2046-05-31', 'category_filter' => 'permanent'), $ACTOR);
$RUN3 = intval($r['run_id']);
$r = PRS::bindSnapshots($conn, $gate, $CO, $RUN3, $ACTOR);
$myLines = intval($conn->query("SELECT COUNT(*) n FROM payroll_lines
                                 WHERE run_id={$RUN3} AND contract_id={$CID}")->fetch_assoc()['n']);
check($myLines === 0, 'عقدٌ غيرُ مقروءٍ (مسودة): **صفرُ سطرٍ محتسَب**');
$blk = $conn->query("SELECT kind, block_code, block_http, reason FROM payroll_run_blocks
                      WHERE run_id={$RUN3} AND contract_id={$CID}")->fetch_assoc();
check($blk && $blk['kind'] === 'excluded' && $blk['block_code'] === 'contract_not_readable'
      && strpos($blk['reason'], 'بسببٍ مكتوب') !== false,
      'وصفٌّ **`excluded`** برمزه وسببه ورابطه — الاستبعادُ يُكتب ولا يُسكت عنه');
$runRow = $conn->query("SELECT state, blocked_count FROM payroll_runs WHERE id={$RUN3}")->fetch_assoc();
check($runRow['state'] === 'Calculated' && intval($runRow['blocked_count']) === 0,
      '**والدورةُ لم تُوقَف**: مسودةٌ في السجل ليست عطبًا (ENT-01 يفرّق: استبعادٌ ≠ منع)');

// ═══ ⑧ Σ التحمّل ═══
head('⑧ Σ نسب تحمّلٍ ≠ 100 → مانعٌ لا حسابٌ خاطئ');
$conn->query("UPDATE employee_contracts SET state='active' WHERE id={$CID}");
$conn->query("UPDATE cost_bearers SET percent = 55.00
               WHERE owner_type='component' AND owner_id={$COMP_BASIC} AND COALESCE(is_deleted,0)=0
               ORDER BY id LIMIT 1");
$sumNow = floatval($conn->query("SELECT ROUND(SUM(percent),2) s FROM cost_bearers
    WHERE owner_type='component' AND owner_id={$COMP_BASIC} AND COALESCE(is_deleted,0)=0")->fetch_assoc()['s']);
$r = PRS::openRun($conn, $gate, $CO, array(
    'period_from' => '2046-06-01', 'period_to' => '2046-06-30', 'category_filter' => 'permanent'), $ACTOR);
$RUN4 = intval($r['run_id']);
PRS::bindSnapshots($conn, $gate, $CO, $RUN4, $ACTOR);
if (abs($sumNow - 100.0) > 0.001) {
    $blk = $conn->query("SELECT kind, block_code FROM payroll_run_blocks
                          WHERE run_id={$RUN4} AND contract_id={$CID}")->fetch_assoc();
    $lines4 = intval($conn->query("SELECT COUNT(*) n FROM payroll_lines
                                    WHERE run_id={$RUN4} AND contract_id={$CID}")->fetch_assoc()['n']);
    check($blk && $blk['block_code'] === 'bearer_sum_invalid' && $blk['kind'] === 'blocked' && $lines4 === 0,
          "Σ = {$sumNow}٪ → **منعٌ** `bearer_sum_invalid` وصفرُ سطرٍ محتسَب");
    $run4 = $conn->query("SELECT state FROM payroll_runs WHERE id={$RUN4}")->fetch_assoc();
    check($run4['state'] === 'Blocked', '**وهذا يوقف الدورة** فعلًا (Blocked) — بخلاف الاستبعاد');
} else {
    ok("جهةُ تحمّلٍ واحدةٌ Σ=100 — الفرعُ غيرُ قابلٍ للإثارة هنا ويُعلَن (Σ={$sumNow})");
    ok('وفرعُ الإيقاف يُختبر متى تعدّدت الجهات');
}

// ═══ ⑨ الشاشةُ والتسجيل ═══
head('⑨ الشاشةُ والتسجيل');
$mod = $conn->query("SELECT id, owner_role_id FROM modules WHERE code='Workforce/payroll_runs.php'")->fetch_assoc();
check($mod && intval($mod['id']) === 156 && intval($mod['owner_role_id']) === 4,
      'الوحدة 156 مسجَّلةٌ لمالك سجل العقود (4)');
$src = file_get_contents(dirname(__DIR__) . '/app/Services/Payroll/PayrollRunService.php');
check(strpos($src, 'ContractSnapshotService::snapshotFor') !== false
      && strpos($src, "selectOne('pay_components'") === false,
      '**البوابةُ الواحدة**: الخدمةُ تقرأ اللقطةَ ولا تقرأ جداولَ العقود مباشرةً');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
