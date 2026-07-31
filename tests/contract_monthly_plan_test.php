<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-03 — اختبار قبول: الجدولُ الشهريُّ للعقد بنسخه (PLAN-03 §2 · §4)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/contract_monthly_plan_test.php
 *
 * ما يُثبته:
 *   ★ **برهانُ P-03**: عقدٌ فيه **شهرُ تعبئةٍ وشهرُ توقف** ⇒ **القيمةُ السنوية
 *     صحيحة** — لا قسمةٌ بالتساوي ولا شهرٌ ضائع.
 *   ① **Σ لا يتجاوز المتعاقَد** ⇒ 409 بالفائض · و`CHECK` يحرس العدّاد.
 *   ② **والمساواةُ التامة شرطُ الختم** ⇒ 422 بالنقص · وبعد الإكمال يُختم.
 *   ③ **والمختومةُ لا تُعدَّل** ⇒ 423 — «التغييرُ بنسخةٍ جديدة».
 *   ④ **والشهرُ داخل مدة البند** ⇒ 422 · **وصفرٌ يعني توقفًا معلَنًا**.
 *   ⑤ **والنسخةُ الأحدثُ تحكم من سريانها** — والقديمةُ تحكم ما قبله.
 *
 * البذرُ معزول: عقدٌ وبندُ طنٍّ في 2081 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'P03 plan test');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractMonthlyPlanService.php';

use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractMonthlyPlanService as CMP;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999031;
$MARK  = 'P03T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE p FROM contract_monthly_plan p JOIN contracts c ON c.id = p.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM client_contract_lines l JOIN contracts c ON c.id = l.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE o FROM contract_operational_sites o JOIN contracts c ON c.id = o.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ P-03 — الجدولُ الشهريُّ للعقد بنسخه ══\n");

head('البذر — بندُ طنٍّ 12,000 طن بسعر 5 على سنةٍ كاملة');

$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id, created_at)
              VALUES ({$CO}, '2081-01-01', 365, '2081-01-01', '2081-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, NOW())");
$CID = intval($conn->insert_id);
$TC = intval($conn->query("SELECT id FROM fin_tax_codes WHERE company_id={$CO} AND active=1 LIMIT 1")
                  ->fetch_assoc()['id']);
$r = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'ton', 'description' => 'نقلُ خامٍ — بندُ ' . $MARK,
    'qty_contracted' => 12000, 'unit_price' => 5.00, 'currency' => 'USD',
    'tax_status' => 'taxable', 'tax_code_id' => $TC,
    'valid_from' => '2081-01-01', 'valid_to' => '2081-12-31'), $ACTOR);
$LID = (int) $r['line_id'];
check($CID > 0 && $LID > 0, "عقدٌ #{$CID} · بندُ طنٍّ #{$LID} (12,000 × 5 = 60,000 USD)");

// ═══ ④ حدودُ الشهر ═══
head('④ **والشهرُ داخل مدة البند**');

$bad1 = CMP::savePlan($conn, $gate, $CO, $LID, 1, '2081-01-01',
    array(array('period_month' => '2080-12', 'qty_planned' => 100)), $ACTOR);
check(!$bad1['ok'] && $bad1['code'] === 422 && mb_strpos($bad1['reason'], 'قبل سريان البند') !== false,
      '★ شهرٌ **قبل سريان البند** ⇒ **422**');
$bad2 = CMP::savePlan($conn, $gate, $CO, $LID, 1, '2081-01-01',
    array(array('period_month' => '2082-01', 'qty_planned' => 100)), $ACTOR);
check(!$bad2['ok'] && $bad2['code'] === 422 && mb_strpos($bad2['reason'], 'بعد نهاية البند') !== false,
      '★ وشهرٌ **بعد نهايته** ⇒ **422**');

// ═══ ① Σ لا يتجاوز المتعاقَد ═══
head('① **Σ لا يتجاوز المتعاقَد**');

$over = array();
for ($i = 1; $i <= 12; $i++) {
    $over[] = array('period_month' => sprintf('2081-%02d', $i), 'qty_planned' => 1500);
}
$r = CMP::savePlan($conn, $gate, $CO, $LID, 1, '2081-01-01', $over, $ACTOR);
check(!$r['ok'] && $r['code'] === 409 && mb_strpos($r['reason'], 'تتجاوز المتعاقَد') !== false,
      '★★ 12 × 1500 = **18,000 > 12,000 ⇒ 409 بالفائض 6,000**');
$n = intval($conn->query("SELECT COUNT(*) c FROM contract_monthly_plan WHERE line_id={$LID}")
                  ->fetch_assoc()['c']);
check($n === 0, 'وصفرُ صفٍّ كُتب في المحاولة المرفوضة — **الفحصُ قبل الكتابة**');

$badCounter = $conn->query("UPDATE client_contract_lines SET qty_planned_total=99999 WHERE id={$LID}");
$q = $conn->query("SELECT qty_planned_total FROM client_contract_lines WHERE id={$LID}")->fetch_assoc();
check(abs((float) $q['qty_planned_total']) < 0.005,
      '★★ ورفعُ العدّاد فوق المتعاقَد **بكتابةٍ مباشرة يرفضه `CHECK`**');

// ═══ ★ برهانُ P-03 ═══
head('★ **برهانُ P-03: شهرُ تعبئةٍ وشهرُ توقف — والقيمةُ السنوية صحيحة**');

// كانون الثاني تعبئةٌ 200 · تموز توقفٌ 0 · والعشرةُ الباقية 1180 لكلٍّ
$plan = array(
    array('period_month' => '2081-01', 'qty_planned' => 200, 'month_kind' => 'mobilization',
          'note' => 'شهرُ تعبئةٍ — نصفُ الأسطول'),
    array('period_month' => '2081-07', 'qty_planned' => 0, 'month_kind' => 'shutdown',
          'note' => 'توقفٌ موسميٌّ — الخريف'),
);
foreach (array(2, 3, 4, 5, 6, 8, 9, 10, 11, 12) as $mn) {
    $plan[] = array('period_month' => sprintf('2081-%02d', $mn), 'qty_planned' => 1180);
}
$r = CMP::savePlan($conn, $gate, $CO, $LID, 1, '2081-01-01', $plan, $ACTOR);
check($r['ok'] && $r['months'] === 12 && abs($r['planned'] - 12000.0) < 0.005
      && abs($r['gap']) < 0.005,
      '★★★ **12 شهرًا بمجموع 12,000 = المتعاقَد بالضبط**: 200 تعبئةً + صفرٌ توقفًا + 10×1180');

$val = CMP::periodValue($gate, $CID, '2081-01', '2081-12');
check(abs($val['by_currency']['USD'] - 60000.0) < 0.005,
      '★★★ **والقيمةُ السنوية 60,000 USD صحيحة** — 12,000 طن × 5');

check(abs($val['months']['2081-01']['USD'] - 1000.0) < 0.005,
      '★★ وكانونُ الثاني **1,000** (200 × 5) — لا 5,000 بقسمةٍ متساوية');
check(isset($val['months']['2081-07']) === false
      || abs($val['months']['2081-07']['USD']) < 0.005,
      '★★ وتموزُ **صفر** — توقفٌ معلَنٌ لا شهرٌ ضائع');
check(abs($val['months']['2081-08']['USD'] - 5900.0) < 0.005,
      '★ وآبُ **5,900** (1180 × 5)');

// ولو قُسّمت بالتساوي لكان كلُّ شهرٍ 1000 طنٍّ = 5,000 — وهو ما لا يقع
$naiveJan = (12000.0 / 12.0) * 5.0;
check(abs($val['months']['2081-01']['USD'] - $naiveJan) > 0.005,
      '★★★ **والقسمةُ المتساوية (5,000 لكانون) لم تقع** — وهذا هو عينُ ما تمنعه P-03');

// ═══ ② المساواةُ التامة شرطُ الختم ═══
head('② **والمساواةُ التامة شرطُ الختم**');

$partial = array_slice($plan, 0, 6);
$r = CMP::savePlan($conn, $gate, $CO, $LID, 2, '2081-06-01', $partial, $ACTOR);
check($r['ok'] && $r['gap'] > 0.0001, 'نسخةٌ ناقصةٌ تُحفظ **بفجوةٍ معلَنة**: ' . $r['reason']);

$s = CMP::seal($conn, $gate, $CO, $LID, 2, $ACTOR);
check(!$s['ok'] && $s['code'] === 422 && mb_strpos($s['reason'], '≠ المتعاقَد') !== false,
      '★★ وختمُ الناقصة ⇒ **422 بالفجوة** — «قيدُ Σ = المتعاقَد شرطُ الختم»');

$miss = CMP::missingMonths($gate, $LID, 2);
check(count($miss) > 0, '★ والأشهرُ الغائبةُ **تُرى** (' . count($miss) . ' شهرًا)');

// ═══ ⑤ النسخةُ الأحدثُ تحكم من سريانها ═══
head('⑤ **والنسخةُ الأحدثُ تحكم من سريانها**');

// نسخةٌ 2 كاملةٌ بتوزيعٍ مختلف: تموز يعمل والتوقفُ في تشرين الأول
$plan2 = array(
    array('period_month' => '2081-01', 'qty_planned' => 200, 'month_kind' => 'mobilization'),
    array('period_month' => '2081-10', 'qty_planned' => 0, 'month_kind' => 'shutdown'),
);
foreach (array(2, 3, 4, 5, 6, 7, 8, 9, 11, 12) as $mn) {
    $plan2[] = array('period_month' => sprintf('2081-%02d', $mn), 'qty_planned' => 1180);
}
$r = CMP::savePlan($conn, $gate, $CO, $LID, 2, '2081-06-01', $plan2, $ACTOR);
check($r['ok'] && abs($r['planned'] - 12000.0) < 0.005, 'نسخةٌ 2 كاملةٌ بسريان 2081-06-01');

$s = CMP::seal($conn, $gate, $CO, $LID, 2, $ACTOR);
check($s['ok'], '★ وتُختم: ' . $s['reason']);

$eArly = CMP::effectivePlan($gate, $LID, '2081-03-01');
check((int) $eArly['version'] === 1,
      '★★ وقراءةُ **آذار (قبل سريان النسخة 2) تعطي النسخة 1** — «أثرُ ما قبله بالسابقة»');
$eLate = CMP::effectivePlan($gate, $LID, '2081-09-01');
check((int) $eLate['version'] === 2, '★★ وقراءةُ **أيلول تعطي النسخة 2**');

// ═══ ③ المختومةُ لا تُعدَّل ═══
head('③ **والمختومةُ لا تُعدَّل**');
$r = CMP::savePlan($conn, $gate, $CO, $LID, 2, '2081-06-01', $plan2, $ACTOR);
check(!$r['ok'] && $r['code'] === 423 && mb_strpos($r['reason'], 'مختومة') !== false,
      '★★ تعديلُ المختومة ⇒ **423** ونصُّه يسمّي البديل: نسخةٌ جديدة');

// ═══ العزل ═══
head('العزلُ محفوظ');
$_SESSION['user']['company_id'] = 1;
$otherGate = new \App\Core\TenantDb($conn, \App\Core\TenantContext::fromSession());
$rows = CMP::rowsOf($otherGate, $LID, 1);
check(count($rows) === 0, '★ جدولُ شركةٍ لا يُقرأ من نطاقٍ آخر — صفرُ تسريب');
$_SESSION['user']['company_id'] = $CO;

fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
