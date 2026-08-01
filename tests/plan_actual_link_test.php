<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-09 — اختبار قبول: مفاتيحُ ربط الخطة بالفعلي (الملحق §3-P-09 · §4)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/plan_actual_link_test.php
 *
 * ما يُثبته:
 *   ★★★ **برهانُ P-09**: المخطَّطُ والمنفَّذُ والمفوتَرُ **يلتقون على مفتاحٍ
 *       واحدٍ** لا على تاريخٍ متقارب — وهو ما تعذّر قبلها.
 *   ① **الوصلُ يُشتقّ ولا يُخمَّن** — وبندان يصلحان ⇒ **التباسٌ يُعلَن 409**.
 *   ② **ولا يُستعار مفتاحٌ من عقدٍ آخر** ⇒ 422 للأطراف الثلاثة.
 *   ③ **والفجوةُ تُعلَن عدًّا** — لا قيمةَ افتراضيةٌ تُخفي غيرَ الموصول.
 *   ④ **ووعدُ `fin_financial_events.contract_line_id` منذ 2026-08-08 يُوفَّى**.
 *
 * البذرُ معزول: عقدان في 2091 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'P09 link test');

require_once dirname(__DIR__) . '/app/Services/Contract/PlanActualLinkService.php';

use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractMonthlyPlanService as CMP;
use App\Services\Contract\PlanActualLinkService as PAL;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999091;
$MARK  = 'P09T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE u FROM unit_entries u JOIN contracts c ON c.id = u.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM claim_lines l JOIN claims c ON c.id = l.claim_id
                   WHERE c.claim_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '%{$MARK}%'");
    $conn->query("DELETE p FROM contract_monthly_plan p JOIN contracts c ON c.id = p.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE ln FROM client_contract_lines ln JOIN contracts c ON c.id = ln.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE s FROM contract_operational_sites s JOIN contracts c ON c.id = s.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ P-09 — مفاتيحُ ربط الخطة بالفعلي ══\n");

head('البذر — عقدٌ ببندِ طنٍّ وجدولٍ شهري · وعقدٌ آخرُ للاستعارة');

$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$mk = function ($tag) use ($conn, $CO, $PRJ, $MARK) {
    $conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
                  actual_start, actual_end, first_party, second_party, contract_status, project_id,
                  price_currency_contract, created_at)
                  VALUES ({$CO}, '2091-01-01', 365, '2091-01-01', '2091-12-31',
                          'طرفُ {$tag} {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', NOW())");
    return intval($conn->insert_id);
};
$CID = $mk('A');
$CID2 = $mk('B');

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'ton', 'description' => 'نقلُ خامٍ — بندُ ' . $MARK,
    'qty_contracted' => 12000, 'unit_price' => 5.00, 'currency' => 'USD',
    'tax_status' => 'exempt', 'tax_code_id' => null,
    'valid_from' => '2091-01-01', 'valid_to' => '2091-12-31'), $ACTOR);
$LID = (int) $r['line_id'];
// بندُ ساعاتٍ في العقد نفسِه — **نموذجٌ مختلفٌ فلا يلتبس**
$r2 = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'hour', 'description' => 'ساعاتٌ — بندُ ' . $MARK,
    'qty_contracted' => 5000, 'unit_price' => 8.00, 'currency' => 'USD',
    'tax_status' => 'exempt', 'tax_code_id' => null,
    'valid_from' => '2091-01-01', 'valid_to' => '2091-12-31'), $ACTOR);
$LID_H = (int) $r2['line_id'];
// وبندُ طنٍّ في العقد الآخر — **للاستعارة الممنوعة**
$r3 = CLS::add($conn, $gate, $CO, $CID2, array(
    'pricing_model' => 'ton', 'description' => 'طنُّ عقدٍ آخر ' . $MARK,
    'qty_contracted' => 1000, 'unit_price' => 5.00, 'currency' => 'USD',
    'tax_status' => 'exempt', 'tax_code_id' => null,
    'valid_from' => '2091-01-01', 'valid_to' => '2091-12-31'), $ACTOR);
$LID_B = (int) $r3['line_id'];

$months = array();
for ($i = 1; $i <= 12; $i++) {
    $months[] = array('period_month' => sprintf('2091-%02d', $i), 'qty_planned' => 1000);
}
$mp = CMP::savePlan($conn, $gate, $CO, $LID, 1, '2091-01-01', $months, $ACTOR);
$MAR = intval($conn->query("SELECT id FROM contract_monthly_plan
                             WHERE line_id={$LID} AND period_month='2091-03'")->fetch_assoc()['id']);
// `site_id` **إلزاميٌّ** في `contract_operational_sites` — والموقعُ كيانٌ مستقل (P-01)
$SITE_REF = intval($conn->query("SELECT id FROM sites WHERE company_id={$CO} LIMIT 1")->fetch_assoc()['id']);
$conn->query("INSERT INTO contract_operational_sites (company_id, contract_id, site_id, scope_name,
              state, created_at) VALUES ({$CO}, {$CID}, {$SITE_REF}, 'نطاقُ {$MARK}', 'active', NOW())");
$SITE = intval($conn->insert_id);
$conn->query("INSERT INTO contract_operational_sites (company_id, contract_id, site_id, scope_name,
              state, created_at) VALUES ({$CO}, {$CID2}, {$SITE_REF}, 'نطاقُ عقدٍ آخر {$MARK}', 'active', NOW())");
$SITE_B = intval($conn->insert_id);
check($CID > 0 && $LID > 0 && $mp['ok'] && $MAR > 0 && $SITE > 0,
      "عقدٌ #{$CID} · بندُ طنٍّ #{$LID} · بندُ ساعاتٍ #{$LID_H} · شهرُ آذار #{$MAR} · نطاقٌ #{$SITE}");

// وحدةُ آذار — 400 طنًّا
$conn->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, contract_id,
              unit_type, qty, record_basis, capacity_flag, state, revision_no, current_round, created_at, updated_at)
              VALUES ({$CO}, 'UE-{$MARK}-1', '2091-03-10', {$PRJ}, {$CID},
                      'ton', 400, 'contract', 0, 'sales_approved', 1, 1, NOW(), NOW())");
$UE1 = intval($conn->insert_id);
$conn->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, contract_id,
              unit_type, qty, record_basis, capacity_flag, state, revision_no, current_round, created_at, updated_at)
              VALUES ({$CO}, 'UE-{$MARK}-2', '2091-03-20', {$PRJ}, {$CID},
                      'ton', 350, 'contract', 0, 'sales_approved', 1, 1, NOW(), NOW())");
$UE2 = intval($conn->insert_id);
check($UE1 > 0 && $UE2 > 0, 'ووحدتان في آذار: 400 + 350 = 750 طنًّا');

// ═══ ③ الفجوةُ قبل الوصل ═══
head('③ **والفجوةُ تُعلَن عدًّا** — قبل الوصل وبعده');

$cov0 = PAL::coverage($gate, $CID);
check($cov0['units_total'] === 2 && $cov0['units_linked'] === 0,
      '★ قبل الوصل: **0 من 2 وحدةٍ موصولة** — ' . $cov0['note']);

// ═══ ① الاشتقاق ═══
head('① **الوصلُ يُشتقّ ولا يُخمَّن**');

$res = PAL::resolve($gate, $CID, '2091-03-10', 'ton', 0);
check($res['ok'] && (int) $res['contract_line_id'] === $LID && (int) $res['plan_period_id'] === $MAR,
      '★★ اشتقاقٌ: **بندُ الطن #' . $LID . ' وشهرُ آذار #' . $MAR . '** — بمطابقة النموذج والنافذة');
check((int) $res['operational_site_id'] === $SITE,
      'والنطاقُ **الوحيدُ للعقد** يُشتقّ تلقائيًّا #' . $SITE);
check($res['candidates'] === 1, 'ومرشَّحٌ واحدٌ لا أكثر — و**بندُ الساعات لم يلتبس بالطن**');

$resH = PAL::resolve($gate, $CID, '2091-03-10', 'hour', 0);
check($resH['ok'] && (int) $resH['contract_line_id'] === $LID_H,
      'ووحدةُ ساعاتٍ تُشتقّ لبندِ الساعات #' . $LID_H . ' — **النموذجُ هو الفاصل**');

$noHit = PAL::resolve($gate, $CID, '2091-03-10', 'trip', 0);
check(!$noHit['ok'] && $noHit['code'] === 404 && mb_strpos($noHit['reason'], 'يُشتقّ ولا يُخترَع') !== false,
      '★ ونموذجٌ لا بندَ له ⇒ **404**: «الوصلُ يُشتقّ ولا يُخترَع»');

// بندُ طنٍّ ثانٍ في العقد نفسِه ⇒ **التباس**
// (يُبذَر **بإدراجٍ مباشر**: `ContractLineService::add` تمنع التداخل بقاعدتها —
//  والالتباسُ هنا واقعٌ موروثٌ يجب أن يُعلَن لا أن يُنشَأ من الخدمة)
$conn->query("INSERT INTO client_contract_lines (company_id, contract_id, line_no, pricing_model,
              description, qty_contracted, unit_price, currency, valid_from, valid_to,
              tax_status, state, created_at)
              VALUES ({$CO}, {$CID}, 99, 'ton', 'طنٌّ ثانٍ {$MARK}', 500, 6.0000, 'USD',
                      '2091-01-01', '2091-12-31', 'exempt', 'active', NOW())");
$LID_D = intval($conn->insert_id);
$amb = PAL::resolve($gate, $CID, '2091-03-10', 'ton', 0);
check(!$amb['ok'] && $amb['code'] === 409 && mb_strpos($amb['reason'], 'يُعلَن ولا يُختار بالحدس') !== false,
      '★★★ وبندان يصلحان ⇒ **409 التباسٌ يُعلَن ولا يُختار بالحدس**: ' . mb_substr($amb['reason'], 0, 90));
$conn->query("UPDATE client_contract_lines SET is_deleted=1 WHERE id={$LID_D}");
$amb2 = PAL::resolve($gate, $CID, '2091-03-10', 'ton', 0);
check($amb2['ok'] && (int) $amb2['contract_line_id'] === $LID, 'وبإزالة الملتبس عاد الاشتقاقُ واحدًا');

// ═══ ② لا يُستعار مفتاح ═══
head('② **ولا يُستعار مفتاحٌ من عقدٍ آخر**');

$b1 = PAL::linkUnit($conn, $gate, $CO, $UE1, array('contract_line_id' => $LID_B), $ACTOR, false);
check(!$b1['ok'] && $b1['code'] === 422 && mb_strpos($b1['reason'], 'من عقدٍ آخر') !== false,
      '★★ بندٌ **من عقدٍ آخر** ⇒ **422**');
$b2 = PAL::linkUnit($conn, $gate, $CO, $UE1,
    array('contract_line_id' => $LID, 'plan_period_id' => 999999), $ACTOR, false);
check(!$b2['ok'] && $b2['code'] === 404, 'وشهرُ خطةٍ **غيرُ موجود** ⇒ **404**');
$PH = intval($conn->query("SELECT id FROM contract_monthly_plan WHERE line_id={$LID}
                            AND period_month='2091-05'")->fetch_assoc()['id']);
$b3 = PAL::linkUnit($conn, $gate, $CO, $UE1,
    array('contract_line_id' => $LID_H, 'plan_period_id' => $PH), $ACTOR, false);
check(!$b3['ok'] && $b3['code'] === 422 && mb_strpos($b3['reason'], 'لبندٍ آخر') !== false,
      '★★ وشهرُ خطةٍ **لبندٍ آخر** ⇒ **422** — «والشهرُ يتبع بندَه»');
$b4 = PAL::linkUnit($conn, $gate, $CO, $UE1,
    array('contract_line_id' => $LID, 'operational_site_id' => $SITE_B), $ACTOR, false);
check(!$b4['ok'] && $b4['code'] === 422 && mb_strpos($b4['reason'], 'ليس من نطاقات هذا العقد') !== false,
      '★★ ونطاقٌ **من عقدٍ آخر** ⇒ **422**');
$q = $conn->query("SELECT contract_line_id FROM unit_entries WHERE id={$UE1}")->fetch_assoc();
check($q['contract_line_id'] === null, 'وصفرُ وصلٍ كُتب في المحاولات الأربع المرفوضة');

// ═══ الوصلُ الصحيح ═══
head('★ **والوصلُ الصحيحُ يقع** — بالاشتقاق التلقائي');

$l1 = PAL::linkUnit($conn, $gate, $CO, $UE1, array(), $ACTOR, true);
$l2 = PAL::linkUnit($conn, $gate, $CO, $UE2, array(), $ACTOR, true);
check($l1['ok'] && $l2['ok'], '★ وُصلت الوحدتان: ' . $l1['reason']);
$q = $conn->query("SELECT contract_line_id, plan_period_id, operational_site_id
                    FROM unit_entries WHERE id={$UE1}")->fetch_assoc();
check((int) $q['contract_line_id'] === $LID && (int) $q['plan_period_id'] === $MAR
      && (int) $q['operational_site_id'] === $SITE,
      '★★ و**المفاتيحُ الثلاثةُ مكتوبةٌ في الصف**: بند ' . $q['contract_line_id']
      . ' · شهر ' . $q['plan_period_id'] . ' · نطاق ' . $q['operational_site_id']);

// سطرُ مستخلصٍ في آذار
$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, client_id, project_id,
              period_from, period_to, currency, gross_amount, retention_amount, net_amount,
              tax_amount, state, version, created_at)
              VALUES ({$CO}, 'CLM-{$MARK}', {$CID}, 1, {$PRJ}, '2091-03-01', '2091-03-31',
                      'USD', 3000, 0, 3000, 0, 'approved', 1, NOW())");
$CLM = intval($conn->insert_id);
$conn->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref, work_date,
              unit_type, qty, unit_price, amount, created_at)
              VALUES ({$CO}, {$CLM}, 'unit', {$UE1}, '2091-03-10', 'ton', 600, 5, 3000, NOW())");
$CL1 = intval($conn->insert_id);
$lc = PAL::linkClaimLine($conn, $gate, $CO, $CL1, array(), $ACTOR, true);
check($lc['ok'], 'ووُصل سطرُ المستخلص: ' . $lc['reason']);

$cov1 = PAL::coverage($gate, $CID);
check($cov1['units_linked'] === 2 && $cov1['claims_linked'] === 1,
      '★★ وبعد الوصل: ' . $cov1['note']);

// ═══ ★★★ البرهان ═══
head('★★★ **البرهان: الثلاثةُ تلتقي على مفتاحٍ واحد**');

$pv = PAL::planVsActual($gate, $CID, '2091-03', '2091-03');
$mar = null;
foreach ($pv['rows'] as $r) { if ((int) $r['line_id'] === $LID) { $mar = $r; } }
check($mar !== null, 'صفُّ آذارَ لبند الطن حاضر');
check(abs($mar['planned'] - 1000.0) < 0.005, '★ **المخطَّطُ 1,000 طنًّا** — من `contract_monthly_plan`');
check(abs($mar['actual'] - 750.0) < 0.005,
      '★★ و**المنفَّذُ 750** (400 + 350) — من `unit_entries` **بمفتاح الشهر لا بتاريخٍ متقارب**');
check(abs($mar['billed'] - 600.0) < 0.005,
      '★★ و**المفوتَرُ 600** — من `claim_lines` بالمفتاح نفسِه');
check(abs($mar['gap_exec'] + 250.0) < 0.005 && abs($mar['gap_bill'] + 150.0) < 0.005,
      '★★★ و**الفجوتان محسوبتان**: تنفيذٌ −250 · فوترةٌ −150 — **ولا واحدةَ منهما كانت ممكنةً قبل P-09**');
check(mb_strpos($pv['note'], 'التقت على مفتاحٍ واحد') !== false, 'والنتيجةُ تقولها: ' . $pv['note']);

$other = PAL::planVsActual($gate, $CID2, '2091-03', '2091-03');
check(count($other['rows']) === 0 || $other['totals']['actual'] == 0.0,
      '★ و**العقدُ الآخرُ لم يلتقط شيئًا** — فالمفتاحُ يفصل ولا يخلط');

// ═══ ④ وعدُ 2026-08-08 ═══
head('④ **ووعدُ `fin_financial_events.contract_line_id` يُوفَّى**');

$col = $conn->query("SELECT COLUMN_COMMENT c FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fin_financial_events'
                        AND COLUMN_NAME='contract_line_id'")->fetch_assoc();
check(mb_strpos((string) $col['c'], 'P-09') !== false,
      '★★ وتعليقُ العمود صار يقول **مرجعَه** بعد أن كان «يُربط عند بناء سجل العقود الموحّد»');
$fill = PAL::fillEventLines($conn, $gate, $CO, false);
check(isset($fill['candidates']), '★ و`fillEventLines` **بوضعِ تجريبٍ**: ' . $fill['note']);
$before = intval($conn->query("SELECT COUNT(*) c FROM fin_financial_events
                                WHERE contract_line_id IS NOT NULL")->fetch_assoc()['c']);
$fill2 = PAL::fillEventLines($conn, $gate, $CO, false);
$after = intval($conn->query("SELECT COUNT(*) c FROM fin_financial_events
                               WHERE contract_line_id IS NOT NULL")->fetch_assoc()['c']);
check($before === $after, 'و**التجريبُ لا يكتب** — والعددُ كما هو');

// ═══ عطالةٌ وحدود ═══
head('حدودٌ أخرى');

$again = PAL::linkUnit($conn, $gate, $CO, $UE1, array(), $ACTOR, true);
check($again['ok'], '★ وإعادةُ الوصل **فعلٌ عاطل** لا يكسر شيئًا');
$q2 = $conn->query("SELECT contract_line_id, plan_period_id FROM unit_entries WHERE id={$UE1}")->fetch_assoc();
check((int) $q2['contract_line_id'] === $LID && (int) $q2['plan_period_id'] === $MAR,
      'والمفاتيحُ كما هي بعد الإعادة');
$ghost = PAL::linkUnit($conn, $gate, $CO, 99999999, array(), $ACTOR, true);
check(!$ghost['ok'] && $ghost['code'] === 404, 'ووحدةٌ **غيرُ موجودة** ⇒ **404**');

$batch = PAL::linkContract($conn, $gate, $CO, $CID, $ACTOR, false);
check(isset($batch['units']), '★ و`linkContract` **بوضعِ تجريب**: ' . $batch['note']);

fwrite(STDOUT, "\n══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══\n");
exit($FAIL === 0 ? 0 : 1);
