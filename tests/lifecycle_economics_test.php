<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-11 — اختبار قبول: اقتصادُ دورة الحياة (PLAN-03 §6 · §6.1 · §9-⑥ · §9-⑦)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/lifecycle_economics_test.php
 *
 * ما يُثبته:
 *   ★★★ **معيارُ §9-⑥**: «إنهاءٌ بخطأ العميل: **المقدمُ رُد والمنفَّذُ فُوتر
 *       والتعويضُ احتُسب بنصه**».
 *   ★★★ **ومعيارُ §9-⑦**: «إلغاءٌ قبل البدء: **الحاوياتُ أُلغيت ولم تُقفل
 *       والمقدمُ رُد كاملًا**».
 *   ① **الأثرُ محكومٌ بالحالة لا يُختار** — و`CHECK` يحرس الثمانيةَ حرفيًّا.
 *   ② **والمنفَّذُ غيرُ المفوتر لا يسقط بالإنهاء** (§6.1-③).
 *   ③ **ولا خصمَ إلا بمادةٍ ومستند** (§6.1-④).
 *   ④ **ولا أثرَ رجعي** — تاريخُ الأثر إلزامي (§6.1-⑤).
 *
 * البذرُ معزول: عقودُ 2093 — تُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'P11 lifecycle test');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractLifecycleService.php';

use App\Services\Contract\ContractLineService as CLS;
use App\Services\Contract\ContractMonthlyPlanService as CMP;
use App\Services\Contract\PlanActualLinkService as PAL;
use App\Services\Contract\ContractLifecycleService as CLC;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999111;
$MARK  = 'P11T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE e FROM contract_lifecycle_events e JOIN contracts c ON c.id = e.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE u FROM unit_entries u JOIN contracts c ON c.id = u.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE a FROM contract_advances a JOIN contracts c ON c.id = a.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE p FROM contract_monthly_plan p JOIN contracts c ON c.id = p.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM client_contract_lines l JOIN contracts c ON c.id = l.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE s FROM contract_operational_sites s JOIN contracts c ON c.id = s.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ P-11 — اقتصادُ دورة الحياة: الحالاتُ الثماني ══\n");

head('① **الأثرُ محكومٌ بالحالة لا يُختار** — وجدولُ §6 ثمانيةٌ لا تاسعَ لها');

check(count(CLC::STATES) === 8 && count(CLC::MATRIX) === 8,
      '★★ **ثمانيةُ حالاتٍ وثمانيةُ صفوفِ أثر** — لا زيادةَ ولا نقصان');
$diff = 0;
foreach (CLC::MATRIX as $s => $e) { if (count($e) === 5) { $diff++; } }
check($diff === 8, 'ولكلِّ حالةٍ **خمسةُ آثارٍ** (مقدَّم · ضمان · منفَّذٌ غيرُ مفوتر · غرامات · حاويات)');

// **الفرقُ المتعاكس** بين ⑤ و⑥ — وهو جوهرُ المهمة
$c5 = CLC::effectsOf('client_fault_end');
$c6 = CLC::effectsOf('our_fault_end');
check($c5['advance'] === 'refund_all_after_offset' && $c6['advance'] === 'refund_after_dues',
      '★★★ والمقدَّمُ: بخطأ العميل **يُرد كاملًا** · وبخطئنا **بعد خصم ما استُحق**');
check($c5['retention'] === 'release' && $c6['retention'] === 'may_forfeit',
      '★★★ والضمانُ: بخطأ العميل **يُرد** · وبخطئنا **قد يُصادر**');
check($c5['unbilled'] === 'bill_all' && $c6['unbilled'] === 'bill_accepted_only',
      '★★★ والمنفَّذُ: بخطأ العميل **كاملًا** · وبخطئنا **المقبولُ فقط**');
check($c5['penalty'] === 'company_claims_compensation' && $c6['penalty'] === 'breach_penalties_capped',
      '★★★ والغراماتُ: بخطأ العميل **الشركةُ تُطالِب** · وبخطئنا **الإخلالُ علينا** — **اتجاهُ المال متعاكس**');

$c7 = CLC::effectsOf('pre_start_cancel');
check($c7['container'] === 'cancel', '★★ و**الإلغاءُ قبل البدء وحدَه يُلغي شجرةَ الحاويات**');
$cancels = 0;
foreach (CLC::MATRIX as $s => $e) { if ($e['container'] === 'cancel') { $cancels++; } }
check($cancels === 1, 'ولا حالةَ أخرى تُلغيها — **وإلغاءُ ما استُهلك محوٌ للتاريخ**');
check(CLC::effectsOf('natural_end')['container'] === 'close_readonly',
      'والإنهاءُ الطبيعيُّ **يُقفل للتسجيل ويُبقي للقراءة** — لا يُلغي');

head('البذر — عقدٌ منفَّذٌ (⑤ و⑥) وعقدٌ لم يبدأ (⑦)');

$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$mk = function ($tag) use ($conn, $CO, $PRJ, $MARK) {
    $conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
                  actual_start, actual_end, first_party, second_party, contract_status, project_id,
                  price_currency_contract, retention_pct, created_at)
                  VALUES ({$CO}, '2093-01-01', 365, '2093-01-01', '2093-12-31',
                          'طرفُ {$tag} {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', 5.00, NOW())");
    return intval($conn->insert_id);
};
$CID = $mk('EXE');     // منفَّذ
$CID0 = $mk('ZERO');   // لم يبدأ

$r = CLS::add($conn, $gate, $CO, $CID, array(
    'pricing_model' => 'ton', 'description' => 'نقلُ خامٍ — بندُ ' . $MARK,
    'qty_contracted' => 12000, 'unit_price' => 5.00, 'currency' => 'USD',
    'tax_status' => 'exempt', 'tax_code_id' => null,
    'valid_from' => '2093-01-01', 'valid_to' => '2093-12-31'), $ACTOR);
$LID = (int) $r['line_id'];
$months = array();
for ($i = 1; $i <= 12; $i++) { $months[] = array('period_month' => sprintf('2093-%02d', $i), 'qty_planned' => 1000); }
CMP::savePlan($conn, $gate, $CO, $LID, 1, '2093-01-01', $months, $ACTOR);
$MAR = intval($conn->query("SELECT id FROM contract_monthly_plan
                             WHERE line_id={$LID} AND period_month='2093-03'")->fetch_assoc()['id']);
$conn->query("INSERT INTO unit_entries (company_id, entry_no, entry_date, project_id, contract_id,
              contract_line_id, plan_period_id, unit_type, qty, record_basis, capacity_flag, state,
              revision_no, current_round, created_at, updated_at)
              VALUES ({$CO}, 'UE-{$MARK}-1', '2093-03-10', {$PRJ}, {$CID},
                      {$LID}, {$MAR}, 'ton', 800, 'contract', 0, 'sales_approved', 1, 1, NOW(), NOW())");

// مقدَّمٌ مقبوضٌ 20,000
require_once dirname(__DIR__) . '/Contracts/advance_helpers.php';
$ar = advance_record($conn, $gate, $CID, 20000, '2093-01-05', 'RCPT-' . $MARK, 'مقدَّمُ ' . $MARK, $ACTOR);
$bal = advance_balance($gate, $CID);
check($ar['ok'] && abs((float) $bal['balance'] - 20000.0) < 0.005,
      "عقدٌ منفَّذٌ #{$CID} · مقدَّمٌ **20,000** · ووحدةٌ 800 طنًّا في آذار");
check($CID0 > 0 && abs(CLC::executedQty($gate, $CID0)) < 0.005,
      "وعقدٌ #{$CID0} **لم يبدأ تنفيذُه** — صفرُ وحدة");

// ═══ ④ لا أثرَ رجعي ═══
head('④ **ولا أثرَ رجعي** — تاريخُ الأثر إلزامي (§6.1-⑤)');

$noDate = CLC::record($conn, $gate, $CO, $CID, array('state' => 'natural_end'), $ACTOR);
check(!$noDate['ok'] && $noDate['code'] === 422 && mb_strpos($noDate['reason'], 'تاريخُ الأثر إلزامي') !== false,
      '★★ واقعةٌ **بلا تاريخِ أثر** ⇒ **422**: «وما قبله بحكمه القديم»');
$noRef = CLC::record($conn, $gate, $CO, $CID,
    array('state' => 'natural_end', 'effect_date' => '2093-12-31'), $ACTOR);
check(!$noRef['ok'] && $noRef['code'] === 422 && mb_strpos($noRef['reason'], 'مرجعُ القرار') !== false,
      '★★ وإنهاءٌ **بلا مرجعِ قرار** ⇒ **422** — ولا يخرج عقدٌ صامتًا');
$bogus = CLC::record($conn, $gate, $CO, $CID,
    array('state' => 'حالة تاسعة', 'effect_date' => '2093-12-31'), $ACTOR);
check(!$bogus['ok'] && $bogus['code'] === 422 && mb_strpos($bogus['reason'], 'ثمانيةٌ لا تاسعَ لها') !== false,
      'وحالةٌ **خارج الثماني** ⇒ **422**');

// ═══ ③ لا خصمَ إلا بمادةٍ ومستند ═══
head('③ **ولا خصمَ إلا بمادةٍ ومستند** (§6.1-④)');

$noArt = CLC::record($conn, $gate, $CO, $CID, array(
    'state' => 'client_fault_end', 'effect_date' => '2093-06-30',
    'decision_ref' => 'DEC-' . $MARK, 'claim_amount' => 15000, 'claim_currency' => 'USD'), $ACTOR);
check(!$noArt['ok'] && $noArt['code'] === 422
      && mb_strpos($noArt['reason'], 'مطالبةٌ تفاوضيةٌ لا خصمٌ نظامي') !== false,
      '★★★ وتعويضٌ **بلا مادةِ عقدٍ ومستند** ⇒ **422**: «وإلا فهي **مطالبةٌ تفاوضيةٌ لا خصمٌ نظامي**»');
$n = intval($conn->query("SELECT COUNT(*) c FROM contract_lifecycle_events
                           WHERE contract_id={$CID}")->fetch_assoc()['c']);
check($n === 0, 'وصفرُ واقعةٍ كُتبت في المحاولات المرفوضة');

// ═══ ★★★ معيارُ §9-⑥ ═══
head('★★★ **معيارُ §9-⑥: إنهاءٌ بخطأ العميل** — المقدمُ رُد والمنفَّذُ فُوتر والتعويضُ بنصه');

$r5 = CLC::record($conn, $gate, $CO, $CID, array(
    'state' => 'client_fault_end', 'effect_date' => '2093-06-30',
    'decision_ref' => 'DEC-' . $MARK,
    'claim_amount' => 15000, 'claim_currency' => 'USD',
    'contract_article' => 'المادة 12-4: تعويضُ التعبئة والإخلاء والتوقف',
    'claim_doc_ref' => 'CALC-' . $MARK,
    'note' => 'إنهاءٌ بخطأ العميل — ' . $MARK), $ACTOR);
check($r5['ok'], '★ سُجّلت الواقعة: ' . mb_substr($r5['reason'], 0, 110));
$q = $conn->query("SELECT advance_effect, retention_effect, unbilled_effect, penalty_effect,
                          container_effect, claim_amount, contract_article
                     FROM contract_lifecycle_events WHERE id=" . (int) $r5['id'])->fetch_assoc();
check($q['advance_effect'] === 'refund_all_after_offset'
      && $q['retention_effect'] === 'release'
      && $q['unbilled_effect'] === 'bill_all'
      && $q['penalty_effect'] === 'company_claims_compensation'
      && $q['container_effect'] === 'close_with_ref',
      '★★★ و**الآثارُ الخمسةُ مكتوبةٌ من جدول §6 لا من الطلب**');
check(abs((float) $q['claim_amount'] - 15000.0) < 0.005 && $q['contract_article'] !== null,
      '★★ و**التعويضُ 15,000 بمادته المكتوبة** — «احتُسب بنصه»');

$p5 = CLC::plan($gate, $CID, 'client_fault_end');
check($p5['ok'] && abs($p5['figures']['advance_balance'] - 20000.0) < 0.005,
      '★★★ وخطةُ العمل: **المقدَّمُ 20,000 يُرد المتبقي كاملًا بعد المقاصّة**');
check(abs($p5['figures']['unbilled'] - 800.0) < 0.005,
      '★★★ و**المنفَّذُ غيرُ المفوتر 800 يُفوتر كاملًا** — «المنفَّذُ فُوتر»');
$areas = array();
foreach ($p5['actions'] as $a) { $areas[] = $a['area']; }
check(count($p5['actions']) >= 6 && in_array('⚠ حقٌّ مكتسب', $areas, true),
      '★★★ و**تحذيرُ «المنفَّذُ غيرُ المفوتر لا يسقط بالإنهاء»** حاضرٌ (§6.1-③)');
check(mb_strpos($p5['note'], 'تُقرّر ولا تُنفّذ') !== false,
      'و**الخدمةُ تُقرّر ولا تُنفّذ** معلَنةً: لكلِّ فعلٍ بيتُه');

// ═══ ★★★ معيارُ §9-⑦ ═══
head('★★★ **معيارُ §9-⑦: إلغاءٌ قبل البدء** — الحاوياتُ أُلغيت ولم تُقفل والمقدمُ رُد كاملًا');

$badCancel = CLC::record($conn, $gate, $CO, $CID, array(
    'state' => 'pre_start_cancel', 'effect_date' => '2093-07-01',
    'decision_ref' => 'DEC2-' . $MARK), $ACTOR);
check(!$badCancel['ok'] && $badCancel['code'] === 409
      && mb_strpos($badCancel['reason'], 'التنفيذُ بدأ فعلًا') !== false,
      '★★★ و**إلغاءٌ «قبل البدء» لعقدٍ بدأ ⇒ 409**: «ولا يُلغى قبل البدء ما بدأ»');

$r7 = CLC::record($conn, $gate, $CO, $CID0, array(
    'state' => 'pre_start_cancel', 'effect_date' => '2093-02-01',
    'decision_ref' => 'DEC3-' . $MARK, 'note' => 'إلغاءٌ قبل البدء — ' . $MARK), $ACTOR);
check($r7['ok'], '★ وعقدٌ لم يبدأ: سُجّل الإلغاء');
$q = $conn->query("SELECT advance_effect, container_effect, unbilled_effect
                     FROM contract_lifecycle_events WHERE id=" . (int) $r7['id'])->fetch_assoc();
check($q['container_effect'] === 'cancel',
      '★★★ و**الحاوياتُ تُلغى ولا تُقفل** — «لم تُستهلك»');
check($q['advance_effect'] === 'refund_full', '★★★ و**المقدَّمُ يُرد كاملًا**');
check($q['unbilled_effect'] === 'none', 'و**المنفَّذُ غيرُ المفوتر: لا شيء**');
$p7 = CLC::plan($gate, $CID0, 'pre_start_cancel');
$rules = '';
foreach ($p7['actions'] as $a) { $rules .= $a['rule'] . ' | '; }
check(mb_strpos($rules, 'تُلغى شجرةُ الحاويات ولا تُقفل') !== false,
      '★★ وخطةُ العمل تقولها نصًّا: «**تُلغى شجرةُ الحاويات ولا تُقفل**»');
check(mb_strpos($rules, 'إن نصّ العقدُ عليها') !== false,
      'و**كلفةُ التعبئة والإخلاء «إن نصّ العقدُ عليها»** — لا بالافتراض');

// ═══ حرَّاسُ البنية ═══
head('★★ **وحرَّاسُ البنية** — لا تركيبةَ خارج جدول §6');

$conn->query("UPDATE contract_lifecycle_events SET advance_effect='refund_full'
               WHERE id=" . (int) $r5['id']);
$q = $conn->query("SELECT advance_effect FROM contract_lifecycle_events
                    WHERE id=" . (int) $r5['id'])->fetch_assoc();
check($q['advance_effect'] === 'refund_all_after_offset',
      '★★★ و**تبديلُ الأثر بكتابةٍ مباشرة يرفضه `CHECK`** — «من أراد أثرًا مخالفًا يلزمه تغييرُ الحالة»');
$conn->query("UPDATE contract_lifecycle_events SET container_effect='cancel'
               WHERE id=" . (int) $r5['id']);
$q = $conn->query("SELECT container_effect FROM contract_lifecycle_events
                    WHERE id=" . (int) $r5['id'])->fetch_assoc();
check($q['container_effect'] === 'close_with_ref',
      '★★★ و**إلغاءُ شجرةٍ استُهلكت مرفوضٌ بنيويًّا** — محوُ التاريخ ممنوع');
$conn->query("UPDATE contract_lifecycle_events SET claim_amount=5000, contract_article=NULL
               WHERE id=" . (int) $r7['id']);
$q = $conn->query("SELECT claim_amount FROM contract_lifecycle_events
                    WHERE id=" . (int) $r7['id'])->fetch_assoc();
check($q['claim_amount'] === null,
      '★★ و**مبلغٌ بلا مادةٍ بكتابةٍ مباشرة يرفضه `CHECK`** كذلك');

$again = CLC::record($conn, $gate, $CO, $CID0, array(
    'state' => 'pre_start_cancel', 'effect_date' => '2093-02-01',
    'decision_ref' => 'DEC3-' . $MARK), $ACTOR);
check($again['ok'] && mb_strpos($again['reason'], 'عاطل') !== false,
      'وإعادةُ التسجيل بالتاريخ نفسِه **فعلٌ عاطل**');

// ═══ الحالاتُ غيرُ المنتهية ═══
head('★ **والحالاتُ غيرُ المنتهية** — تعليقٌ وتمديدٌ ونزاع');

$sus = CLC::record($conn, $gate, $CO, $CID, array(
    'state' => 'suspension', 'effect_date' => '2093-04-01', 'note' => 'تعليقٌ ' . $MARK), $ACTOR);
check($sus['ok'], 'وتعليقٌ **بلا مرجعِ قرار** مقبول — فليس إنهاءً');
check($sus['effects']['advance'] === 'pause_recovery'
      && $sus['effects']['penalty'] === 'pause_time_not_performance',
      '★★ و**استهلاكُ المقدَّم يتوقف** و**الغراماتُ الزمنيةُ لا الأدائية**');
$dis = CLC::record($conn, $gate, $CO, $CID, array(
    'state' => 'dispute', 'effect_date' => '2093-05-01'), $ACTOR);
check($dis['ok'] && $dis['effects']['unbilled'] === 'freeze_disputed_bill_rest',
      '★★ والنزاعُ **يُجمّد المتنازَعَ عليه ويُفوتر الباقي** — لا يوقف الفوترةَ كلَّها');
$ext = CLC::record($conn, $gate, $CO, $CID, array(
    'state' => 'extension', 'effect_date' => '2093-11-01'), $ACTOR);
check($ext['ok'] && $ext['effects']['container'] === 'extend',
      'والتمديدُ **يمدّ الحاويةَ نفسَها ورصيدُها كما هو** — لا حاويةَ جديدة');
$ren = CLC::record($conn, $gate, $CO, $CID, array(
    'state' => 'renewal', 'effect_date' => '2094-01-01'), $ACTOR);
check($ren['ok'] && $ren['effects']['container'] === 'new_tree',
      'والتجديدُ **حاويةٌ جديدةٌ ولا تُخلط الأرصدة** — والفرقُ عن التمديد جوهري');

$ev = CLC::eventsOf($gate, $CID);
check(count($ev) >= 5, '★ وسجلُّ العقد يحمل ' . count($ev) . ' واقعةً بتواريخ آثارها');

fwrite(STDOUT, "\n══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══\n");
exit($FAIL === 0 ? 0 : 1);
