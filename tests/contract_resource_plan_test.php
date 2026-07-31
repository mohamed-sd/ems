<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-04 — اختبار قبول: خطةُ الموارد بحصص الأنواع (PLAN-03 §2 · الملحق §3-P-04)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/contract_resource_plan_test.php
 *
 * ما يُثبته:
 *   ★ **برهانُ P-04**: خطةُ مواردَ كاملةً على بندِ طنٍّ ⇒ **قيمةُ العقد لم
 *     تتغيّر بمقدار ذرة** — «تُغذّي الحاويات ولا تدخل القيمة».
 *   ★ **وبنيةً**: `contract_resource_plan` **لا تحمل عمودَ مالٍ واحدًا** —
 *     لا سعرَ ولا عملةَ ولا مبلغ. (الفصلُ في الجدول لا في الاتفاق.)
 *   ① **Σ الحصص ≤ 100** ⇒ 409 بالفائض · و`CHECK` يحرس العدّاد.
 *   ② **والطاقةُ Σ = المتعاقَد بالضبط** — وآخرُ نوعٍ يبتلع باقيَ التقريب.
 *   ③ **وصفرُ الحصة يُفسَّر باسمه** — والمنتجُ لا يكون بصفر.
 *   ④ **والتعديلُ إنهاءٌ وإضافةٌ لا محو** — والمنتهيةُ تبقى للتاريخ.
 *
 * البذرُ معزول: عقدٌ وبندان في 2082 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'P04 resource test');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractResourcePlanService.php';

use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractResourcePlanService as CRP;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999041;
$MARK  = 'P04T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE p FROM contract_resource_plan p JOIN contracts c ON c.id = p.contract_id
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

fwrite(STDOUT, "\n══ P-04 — خطةُ الموارد بحصص الأنواع ══\n");

// ═══ البنيةُ نفسُها برهان ═══
head('★ **البنيةُ لا تحمل مالًا** — والفصلُ في الجدول لا في الاتفاق');

$moneyish = array();
$rs = $conn->query(
    "SELECT COLUMN_NAME c FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contract_resource_plan'");
$allCols = array();
while ($x = $rs->fetch_assoc()) {
    $allCols[] = $x['c'];
    foreach (array('price', 'amount', 'currency', 'cost', 'value', 'rate', 'tax', 'total') as $w) {
        if (stripos($x['c'], $w) !== false) { $moneyish[] = $x['c']; }
    }
}
check(count($allCols) > 0, 'الجدولُ قائمٌ بـ' . count($allCols) . ' عمودًا');
check(count($moneyish) === 0,
      '★★ **صفرُ عمودِ مالٍ** في `contract_resource_plan` — '
      . (count($moneyish) ? ('وُجد: ' . implode(', ', $moneyish)) : 'لا سعرَ ولا عملةَ ولا مبلغ'));

$reg = \App\Core\TenantRegistry::get('contract_resource_plan');
check($reg !== null && $reg['type'] === \App\Core\TenantRegistry::T_TENANT,
      'ومسجَّلٌ في سجل المستأجر بنوعِ `tenant` — فلا يمرّ بلا عزل');

// ═══ البذر ═══
head('البذر — عقدٌ وبندُ طنٍّ 12,000 × 5 = 60,000 USD');

$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id, created_at)
              VALUES ({$CO}, '2082-01-01', 365, '2082-01-01', '2082-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, NOW())");
$CID = intval($conn->insert_id);
$TC = intval($conn->query("SELECT id FROM fin_tax_codes WHERE company_id={$CO} AND active=1 LIMIT 1")
                  ->fetch_assoc()['id']);

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'ton', 'description' => 'نقلُ خامٍ — بندُ ' . $MARK,
    'qty_contracted' => 12000, 'unit_price' => 5.00, 'currency' => 'USD',
    'tax_status' => 'taxable', 'tax_code_id' => $TC,
    'valid_from' => '2082-01-01', 'valid_to' => '2082-12-31'), $ACTOR);
$LID = (int) $r['line_id'];
check($CID > 0 && $LID > 0, "عقدٌ #{$CID} · بندُ طنٍّ #{$LID}");

$v0 = CLS::contractValue($gate, $CID);
$val0 = isset($v0['by_currency']['USD']) ? $v0['by_currency']['USD'] : 0.0;
check(abs($val0 - 60000.0) < 0.005, 'قيمةُ العقد قبل الخطة = **60,000 USD**');

$types = CRP::activeTypes($gate);
check(count($types) >= 2, 'أنواعُ المعدات النشطة: ' . count($types) . ' (' . implode(' · ', $types) . ')');
$tids = array_keys($types);
$T1 = (int) $tids[0]; $T2 = (int) $tids[1];
$T3 = isset($tids[2]) ? (int) $tids[2] : 0;

// ═══ ① Σ الحصص ≤ 100 ═══
head('① **Σ الحصص لا تتجاوز المائة**');

$over = CRP::savePlan($conn, $gate, $CO, $LID, array(
    array('equipment_type_id' => $T1, 'capacity_share_percent' => 70, 'count_basic' => 3),
    array('equipment_type_id' => $T2, 'capacity_share_percent' => 45, 'count_basic' => 2),
), $ACTOR);
check(!$over['ok'] && $over['code'] === 409 && mb_strpos($over['reason'], 'تتجاوز المائة') !== false,
      '★★ 70% + 45% = **115% ⇒ 409 بالفائض 15%**');
$n = intval($conn->query("SELECT COUNT(*) c FROM contract_resource_plan WHERE line_id={$LID}")
                  ->fetch_assoc()['c']);
check($n === 0, 'وصفرُ صفٍّ كُتب في المحاولة المرفوضة — **الفحصُ قبل الكتابة**');

$conn->query("UPDATE client_contract_lines SET resource_share_total=150 WHERE id={$LID}");
$q = $conn->query("SELECT resource_share_total FROM client_contract_lines WHERE id={$LID}")->fetch_assoc();
check(abs((float) $q['resource_share_total']) < 0.005,
      '★★ ورفعُ العدّاد فوق المائة **بكتابةٍ مباشرة يرفضه `CHECK`**');

$dup = CRP::savePlan($conn, $gate, $CO, $LID, array(
    array('equipment_type_id' => $T1, 'capacity_share_percent' => 50),
    array('equipment_type_id' => $T1, 'capacity_share_percent' => 50),
), $ACTOR);
check(!$dup['ok'] && $dup['code'] === 409 && mb_strpos($dup['reason'], 'مكرَّر') !== false,
      'ونوعٌ مكرَّرٌ في الخطة الواحدة ⇒ **409**');

// ═══ ③ صفرُ الحصة يُفسَّر باسمه ═══
head('③ **وصفرُ الحصة يُفسَّر باسمه**');

$mix = CRP::savePlan($conn, $gate, $CO, $LID, array(
    array('equipment_type_id' => $T1, 'capacity_share_percent' => 0, 'share_kind' => 'backup_only',
          'count_backup' => 2),
), $ACTOR);
check($mix['ok'] && abs($mix['share']) < 0.0005,
      'احتياطيٌّ بحصةِ صفرٍ **مقبول** — «جاهزيةٌ لا إنتاجٌ مخطَّط»');
$row = $conn->query("SELECT share_kind FROM contract_resource_plan
                      WHERE line_id={$LID} AND state='active' LIMIT 1")->fetch_assoc();
check($row && $row['share_kind'] === 'backup_only', 'وحالُه المخزَّن `backup_only`');

$auto = CRP::savePlan($conn, $gate, $CO, $LID, array(
    array('equipment_type_id' => $T2, 'capacity_share_percent' => 0, 'share_kind' => 'productive'),
), $ACTOR);
$rk = $conn->query("SELECT share_kind FROM contract_resource_plan
                     WHERE line_id={$LID} AND state='active' AND equipment_type_id={$T2}")->fetch_assoc();
check($auto['ok'] && $rk && $rk['share_kind'] === 'backup_only',
      '★ و**منتجٌ بحصةِ صفرٍ يُصحَّح آليًّا إلى احتياطي** — فلا صفرَ بلا تفسير');

$badKind = CRP::savePlan($conn, $gate, $CO, $LID, array(
    array('equipment_type_id' => $T1, 'capacity_share_percent' => 40, 'share_kind' => 'support'),
), $ACTOR);
check(!$badKind['ok'] && $badKind['code'] === 422 && mb_strpos($badKind['reason'], 'الحصةُ للمنتج') !== false,
      'ومساندٌ بحصةِ 40% ⇒ **422** — والحصةُ للمنتج وحدَه');

$badShift = CRP::savePlan($conn, $gate, $CO, $LID, array(
    array('equipment_type_id' => $T1, 'capacity_share_percent' => 100,
          'shifts_per_day' => 3, 'hours_per_shift' => 10),
), $ACTOR);
check(!$badShift['ok'] && $badShift['code'] === 422 && mb_strpos($badShift['reason'], 'أربعٌ وعشرون') !== false,
      'و3 ورديّاتٍ × 10 ساعاتٍ = 30 ⇒ **422 — واليومُ أربعٌ وعشرون**');

// ═══ ★ برهانُ P-04 ═══
head('★ **برهانُ P-04: خطةُ مواردَ كاملةً — والقيمةُ لم تتغيّر**');

$full = CRP::savePlan($conn, $gate, $CO, $LID, array(
    array('equipment_type_id' => $T1, 'capacity_share_percent' => 60, 'count_basic' => 6,
          'count_backup' => 2, 'shifts_per_day' => 2, 'hours_per_shift' => 10,
          'operators_count' => 12, 'supervisors_count' => 2, 'technicians_count' => 3,
          'assistants_count' => 4, 'note' => 'الحفاراتُ — العمودُ الفقري'),
    array('equipment_type_id' => $T2, 'capacity_share_percent' => 40, 'count_basic' => 10,
          'count_backup' => 3, 'shifts_per_day' => 2, 'hours_per_shift' => 10,
          'operators_count' => 20, 'supervisors_count' => 1, 'technicians_count' => 4,
          'assistants_count' => 6, 'note' => 'القلاباتُ — النقلُ المرافق'),
), $ACTOR);
check($full['ok'] && $full['complete'] && abs($full['share'] - 100.0) < 0.0005,
      '★ خطةٌ **مكتملة**: 60% + 40% = **100%** على نوعين');

$v1 = CLS::contractValue($gate, $CID);
$val1 = isset($v1['by_currency']['USD']) ? $v1['by_currency']['USD'] : 0.0;
check(abs($val1 - 60000.0) < 0.005 && abs($val1 - $val0) < 0.005,
      '★★★ **قيمةُ العقد بعد الخطة = 60,000 USD — لم تتغيّر بمقدار ذرة**');
check(count($v1['lines']) === count($v0['lines']),
      'وعددُ بنود القيمة كما هو (' . count($v1['lines']) . ') — **الخطةُ ليست بندًا**');

$cap = CRP::plannedCapacity($gate, $LID);
check($cap['ok'] && abs($cap['total'] - 12000.0) < 0.005,
      '★★ والطاقةُ المخطَّطة **Σ = 12,000 طنًّا = المتعاقَد بالضبط**');
$byType = array();
foreach ($cap['rows'] as $cr) { $byType[(int) $cr['equipment_type_id']] = $cr['planned_qty']; }
check(abs($byType[$T1] - 7200.0) < 0.005 && abs($byType[$T2] - 4800.0) < 0.005,
      'و**7,200 + 4,800** لا نصفان متساويان — «الحصةُ تُقاس لا تُفترض»');
check($cap['unit'] === 'ton', 'ووحدةُ الطاقة `ton` مشتقّةً من نموذج التسعير');

$crew = CRP::crewDemand($gate, $LID);
check($crew['operators'] === 32 && $crew['equipment_basic'] === 16 && $crew['equipment_backup'] === 5,
      'وطلبُ العمالة 32 مشغّلًا و16 معدةً أساسيةً و5 احتياطية — **طلبٌ لا استحقاق**');

$seed = CRP::containerSeed($gate, $LID);
$sum = 0.0;
foreach ($seed['seed'] as $s) { $sum = round($sum + $s['cap_qty'], 2); }
check(count($seed['seed']) === 2 && abs($sum - 12000.0) < 0.005,
      '★ وبذرُ الحاويات **حاويتان بسقفٍ مجموعُه 12,000** — «تُغذّي الحاويات»');
check(isset($seed['seed'][0]['resource_plan_id']) && $seed['seed'][0]['resource_plan_id'] > 0,
      'وكلُّ بذرةٍ تحمل مصدرَها `resource_plan_id` — فالحاويةُ تُعرَف من أين جاءت');

// ═══ ② باقي التقريب ═══
head('② **وآخرُ نوعٍ يبتلع باقيَ التقريب** (درسُ M-30)');

if ($T3 > 0) {
    $r3 = CLS::add($conn, $gate, $CO, $CID, array(
        'pricing_model' => 'hour', 'description' => 'ساعاتٌ — بندُ التقريب ' . $MARK,
        'qty_contracted' => 10000, 'unit_price' => 3.00, 'currency' => 'USD',
        'tax_status' => 'exempt', 'tax_code_id' => null,
        'valid_from' => '2082-01-01', 'valid_to' => '2082-12-31'), $ACTOR);
    $LID3 = (int) $r3['line_id'];
    $p3 = CRP::savePlan($conn, $gate, $CO, $LID3, array(
        array('equipment_type_id' => $T1, 'capacity_share_percent' => 33.333, 'count_basic' => 1),
        array('equipment_type_id' => $T2, 'capacity_share_percent' => 33.333, 'count_basic' => 1),
        array('equipment_type_id' => $T3, 'capacity_share_percent' => 33.334, 'count_basic' => 1),
    ), $ACTOR);
    check($p3['ok'] && $p3['complete'], 'ثلاثةُ أنواعٍ بـ33.333 + 33.333 + 33.334 = **100%**');
    $c3 = CRP::plannedCapacity($gate, $LID3);
    check(abs($c3['total'] - 10000.0) < 0.0005,
          '★★ و**Σ الطاقة 10,000 بالضبط — لا 9,999.99**: الكسرُ لا يضيع');
} else {
    check(false, 'يلزم ثلاثةُ أنواعِ معداتٍ نشطةٍ لبرهان التقريب');
}

// ═══ ④ التعديلُ إنهاءٌ وإضافة ═══
head('④ **والتعديلُ إنهاءٌ وإضافةٌ لا محو**');

$before = intval($conn->query("SELECT COUNT(*) c FROM contract_resource_plan WHERE line_id={$LID}")
                       ->fetch_assoc()['c']);
$amend = CRP::savePlan($conn, $gate, $CO, $LID, array(
    array('equipment_type_id' => $T1, 'capacity_share_percent' => 75, 'count_basic' => 8),
    array('equipment_type_id' => $T2, 'capacity_share_percent' => 25, 'count_basic' => 5),
), $ACTOR, '2082-07-01');
check($amend['ok'] && $amend['complete'], 'خطةٌ أحدثُ من 2082-07-01: 75% + 25%');
$after = intval($conn->query("SELECT COUNT(*) c FROM contract_resource_plan WHERE line_id={$LID}")
                      ->fetch_assoc()['c']);
check($after > $before, "★ والصفوفُ **زادت** ({$before} ⇒ {$after}) — **المنتهيةُ باقيةٌ للتاريخ**");
$ended = intval($conn->query("SELECT COUNT(*) c FROM contract_resource_plan
                               WHERE line_id={$LID} AND state='ended'")->fetch_assoc()['c']);
check($ended > 0, "و{$ended} صفًّا حالُه `ended` **بسببٍ مسجَّل** لا محذوفًا");
$live = CRP::liveRows($gate, $LID);
check(count($live) === 2 && abs(CRP::shareTotal($gate, $LID) - 100.0) < 0.0005,
      'والنافذُ نوعان بمجموعِ 100% — **ولا صفَّان يتنازعان النوعَ نفسَه**');
$cap2 = CRP::plannedCapacity($gate, $LID);
$byType2 = array();
foreach ($cap2['rows'] as $cr) { $byType2[(int) $cr['equipment_type_id']] = $cr['planned_qty']; }
check(abs($byType2[$T1] - 9000.0) < 0.005 && abs($byType2[$T2] - 3000.0) < 0.005,
      'والطاقةُ صارت **9,000 + 3,000** — والمجموعُ ما زال 12,000');

$v2 = CLS::contractValue($gate, $CID);
check(abs($v2['by_currency']['USD'] - $val0 - 30000.0) < 0.005,
      '★★ وقيمةُ العقد = 60,000 + 30,000 (بندُ الساعات) — **ولا أثرَ لتعديل الخطة فيها**');

$noReason = CRP::endRow($conn, $gate, $CO, (int) $live[0]['id'], '   ', $ACTOR);
check(!$noReason['ok'] && $noReason['code'] === 422 && mb_strpos($noReason['reason'], 'سببُ الإنهاء') !== false,
      'وإنهاءٌ **بلا سبب** ⇒ **422** — ولا صفَّ يخرج صامتًا');
$endOk = CRP::endRow($conn, $gate, $CO, (int) $live[0]['id'], 'خرج النوعُ من نطاق العمل', $ACTOR);
check($endOk['ok'] && abs($endOk['share'] - (100.0 - (float) $live[0]['capacity_share_percent'])) < 0.0005,
      '★ وبالسبب: أُنهي الصفُّ **والحصةُ عادت للعدّاد** (Σ = ' . $endOk['share'] . '%)');
$still = $conn->query("SELECT state, end_reason FROM contract_resource_plan
                        WHERE id=" . (int) $live[0]['id'])->fetch_assoc();
check($still && $still['state'] === 'ended' && $still['end_reason'] !== null,
      'والصفُّ **ما زال موجودًا** بحالِ `ended` وسببِه — **لا حذف**');
$again = CRP::endRow($conn, $gate, $CO, (int) $live[0]['id'], 'محاولةٌ ثانية', $ACTOR);
check(!$again['ok'] && $again['code'] === 409, 'وإنهاءُ المنتهي ⇒ **409** — والفعلُ عاطل');

// ═══ حدودٌ أخرى ═══
head('حدودٌ أخرى — بندٌ مقطوعٌ وموقعُ عقدٍ آخر');

$rl = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'lump_sum', 'description' => 'تعبئةُ موقعٍ مقطوعة ' . $MARK,
    'qty_contracted' => 1, 'unit_price' => 25000.00, 'currency' => 'USD',
    'tax_status' => 'exempt', 'tax_code_id' => null,
    'valid_from' => '2082-01-01', 'valid_to' => '2082-12-31'), $ACTOR);
$LUMP = (int) $rl['line_id'];
$capL = CRP::plannedCapacity($gate, $LUMP);
check(!$capL['ok'] && $capL['code'] === 422 && mb_strpos($capL['reason'], 'لا طاقةَ تُقاس') !== false,
      'وبندٌ **مقطوع** ⇒ **422 لا طاقةَ تُقاس** — والمقطوعُ يُفوتَر بالإنجاز');

$foreign = intval($conn->query("SELECT id FROM contract_operational_sites
                                 WHERE contract_id <> {$CID} AND company_id={$CO}
                                   AND COALESCE(is_deleted,0)=0 LIMIT 1")->fetch_assoc()['id']);
if ($foreign > 0) {
    $bs = CRP::savePlan($conn, $gate, $CO, $LID, array(
        array('equipment_type_id' => $T1, 'capacity_share_percent' => 100,
              'operational_site_id' => $foreign),
    ), $ACTOR);
    check(!$bs['ok'] && $bs['code'] === 422 && mb_strpos($bs['reason'], 'ليس من نطاقات هذا العقد') !== false,
          'وموقعٌ **من عقدٍ آخر** ⇒ **422** — والنطاقُ لا يُستعار');
} else {
    check(true, '(لا موقعَ من عقدٍ آخرَ في النطاق — الفحصُ متروك)');
}

$rEnded = $conn->query("UPDATE client_contract_lines SET state='ended' WHERE id={$LUMP}");
$onEnded = CRP::savePlan($conn, $gate, $CO, $LUMP, array(
    array('equipment_type_id' => $T1, 'capacity_share_percent' => 100)), $ACTOR);
check(!$onEnded['ok'] && $onEnded['code'] === 409,
      'وخطةٌ على بندٍ **منتهٍ** ⇒ **409** — ولا خطةَ لما انتهى');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══\n");
exit($FAIL === 0 ? 0 : 1);
