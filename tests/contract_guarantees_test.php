<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * P-06 — اختبار قبول: فصلُ المحتجَز عن خطاب الضمان (PLAN-03 §3.1 · §9-⑯)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/contract_guarantees_test.php
 *
 * ما يُثبته:
 *   ★★★ **برهانُ P-06**: خطابُ ضمانٍ بنكيٍّ **لا يُخصم من مستخلصٍ ولا يظهر
 *       أصلًا** — ومحتجزٌ نقديٌّ **يفعل الاثنين**.
 *   ① **الطبيعةُ محكومةٌ بالنوع** — ولا تُختار (والخلطُ خطأٌ محاسبيٌّ).
 *   ② **ولكلِّ أداةٍ أفقُها**: المحتجَزُ تاريخُ ردٍّ أو شرطُه · وغيرُه انتهاءُ سريان.
 *   ③ **والرصيدُ يُقرأ من مصدره** — `claims` لا سجلُّ الضمانات.
 *   ④ **والنثرُ نُقل ولم يُمحَ** — وكلُّ منقولٍ ينتظر إقرارَ المالك.
 *
 * البذرُ معزول: عقدٌ في 2086 — يُكنس كاملًا.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
while (ob_get_level() > 0) { ob_end_clean(); }
$_SESSION['user'] = array('id' => 1, 'role' => '12', 'company_id' => 4, 'name' => 'P06 guarantee test');

require_once dirname(__DIR__) . '/app/Services/Contract/ContractGuaranteeService.php';

use App\Services\Contract\ContractGuaranteeService as CGS;

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$gate  = ems_tenant_db();
$CO    = 4;
$ACTOR = 999061;
$MARK  = 'P06T' . getmypid();

$teardown = function () use ($conn, $MARK) {
    $conn->query("DELETE g FROM contract_guarantees g JOIN contracts c ON c.id = g.contract_id
                   WHERE c.first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE l FROM claim_lines l JOIN claims c ON c.id = l.claim_id
                   WHERE c.claim_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM claims WHERE claim_no LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM contracts WHERE first_party LIKE '%{$MARK}%'");
    $conn->query("DELETE FROM project WHERE name LIKE '%{$MARK}%'");
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ P-06 — الضماناتُ: الأصلُ والالتزامُ المحتمل لا يختلطان ══\n");

head('البذر — عقدٌ باحتجازٍ 10%');
$conn->query("INSERT INTO project (company_id, name, client, location, total)
              VALUES ({$CO}, 'مشروعُ {$MARK}', 'عميلُ {$MARK}', 'موقعُ {$MARK}', '0')");
$PRJ = intval($conn->insert_id);
$conn->query("INSERT INTO contracts (company_id, contract_signing_date, contract_duration_days,
              actual_start, actual_end, first_party, second_party, contract_status, project_id,
              price_currency_contract, retention_pct, created_at)
              VALUES ({$CO}, '2086-01-01', 365, '2086-01-01', '2086-12-31',
                      'طرفُ {$MARK}', 'عميلُ {$MARK}', 'نافذ', {$PRJ}, 'دولار', 10.00, NOW())");
$CID = intval($conn->insert_id);
check($CID > 0, "عقدٌ #{$CID}");

// ═══ ① الطبيعةُ محكومةٌ بالنوع ═══
head('① **الطبيعةُ محكومةٌ بالنوع** — والخلطُ خطأٌ محاسبيٌّ لا خيارُ مستخدم');

$b1 = CGS::add($conn, $gate, $CO, $CID, array(
    'kind' => 'bank_guarantee', 'nature' => 'asset', 'amount' => 50000,
    'expiry_date' => '2087-06-30'), $ACTOR);
check(!$b1['ok'] && $b1['code'] === 422 && mb_strpos($b1['reason'], 'خطأٌ محاسبيٌّ') !== false,
      '★★ خطابُ ضمانٍ **بطبيعةِ أصل** ⇒ **422**: ' . mb_substr($b1['reason'], 0, 90));

$b2 = CGS::add($conn, $gate, $CO, $CID, array(
    'kind' => 'bank_guarantee', 'amount' => 50000, 'expiry_date' => '2087-06-30',
    'deductible_from_claim' => 1), $ACTOR);
check(!$b2['ok'] && $b2['code'] === 422 && mb_strpos($b2['reason'], 'لا يُخصم من مستخلصٍ أبدًا') !== false,
      '★★ و**بطلبِ الخصم من المستخلص** ⇒ **422** — وليس نقدًا محجوزًا حتى يُخصم');

$b3 = CGS::add($conn, $gate, $CO, $CID, array(
    'kind' => 'cash_retention', 'nature' => 'off_balance', 'percent_value' => 10,
    'due_release_date' => '2087-06-30'), $ACTOR);
check(!$b3['ok'] && $b3['code'] === 422,
      'ومحتجزٌ نقديٌّ **بطبيعةِ خارج الميزانية** ⇒ **422** — وهو أصلٌ حتمًا');

$b4 = CGS::add($conn, $gate, $CO, $CID, array('kind' => 'حاجة', 'amount' => 1), $ACTOR);
check(!$b4['ok'] && $b4['code'] === 422, 'ونوعٌ غيرُ معروف ⇒ **422**');

$n = intval($conn->query("SELECT COUNT(*) c FROM contract_guarantees WHERE contract_id={$CID}
                            AND source_text IS NULL AND kind <> 'cash_retention'")->fetch_assoc()['c']);
check($n === 0, 'وصفرُ أداةٍ كُتبت في المحاولات المرفوضة');

// ═══ ② لكلِّ أداةٍ أفقُها ═══
head('② **ولكلِّ أداةٍ أفقُها** — فلا التزامَ معلَّقٌ إلى الأبد ولا أصلٌ بلا عودة');

$b5 = CGS::add($conn, $gate, $CO, $CID, array(
    'kind' => 'bank_guarantee', 'amount' => 50000, 'issuer' => 'بنكُ ' . $MARK), $ACTOR);
check(!$b5['ok'] && $b5['code'] === 422 && mb_strpos($b5['reason'], 'انتهاء سريان') !== false,
      '★ خطابُ ضمانٍ **بلا تاريخِ انتهاء** ⇒ **422**');

$b6 = CGS::add($conn, $gate, $CO, $CID, array(
    'kind' => 'cash_retention', 'percent_value' => 10, 'amount' => 6000), $ACTOR);
check(!$b6['ok'] && $b6['code'] === 422 && mb_strpos($b6['reason'], 'أصلٌ بلا موعدِ عودةٍ') !== false,
      '★★ ومحتجزٌ **بلا تاريخِ ردٍّ ولا شرطِه** ⇒ **422** — «أصلٌ بلا موعدِ عودةٍ رقمٌ معلَّق»');

// ═══ التسجيلُ الصحيح ═══
head('★ والتسجيلُ الصحيحُ يقع — وكلُّ أداةٍ تُعلن طبيعتَها');

$bg = CGS::add($conn, $gate, $CO, $CID, array(
    'kind' => 'bank_guarantee', 'amount' => 50000, 'currency' => 'USD',
    'issuer' => 'بنكُ الخرطوم — ' . $MARK, 'instrument_ref' => 'LG-' . $MARK,
    'issue_date' => '2086-01-15', 'expiry_date' => '2087-06-30',
    'note' => 'ضمانُ حسن التنفيذ'), $ACTOR);
check($bg['ok'] && mb_strpos($bg['reason'], 'لا يُخصم من مستخلص') !== false, '★ ' . $bg['reason']);

$cr = CGS::add($conn, $gate, $CO, $CID, array(
    'kind' => 'cash_retention', 'percent_value' => 10, 'amount' => 6000, 'currency' => 'USD',
    'due_release_date' => '2087-06-30',
    'release_condition' => 'بعد انقضاء فترة الضمان وقبولِ الأعمال نهائيًّا'), $ACTOR);
check($cr['ok'] && mb_strpos($cr['reason'], 'أصلٌ لدى العميل') !== false, '★ ' . $cr['reason']);

$ins = CGS::add($conn, $gate, $CO, $CID, array(
    'kind' => 'insurance', 'amount' => 20000, 'currency' => 'USD',
    'issuer' => 'شركةُ تأمينِ ' . $MARK, 'expiry_date' => '2087-01-01'), $ACTOR);
check($ins['ok'], 'وتأمينٌ 20,000 — **خارجَ الميزانية أيضًا**');

$q = $conn->query("SELECT kind, nature, deductible_from_claim FROM contract_guarantees
                    WHERE id=" . (int) $bg['id'])->fetch_assoc();
check($q['nature'] === 'off_balance' && (int) $q['deductible_from_claim'] === 0,
      'وخطابُ الضمان مخزَّنٌ **خارجَ الميزانية وغيرَ قابلٍ للخصم**');
$q = $conn->query("SELECT nature, deductible_from_claim FROM contract_guarantees
                    WHERE id=" . (int) $cr['id'])->fetch_assoc();
check($q['nature'] === 'asset' && (int) $q['deductible_from_claim'] === 1,
      'والمحتجَزُ **أصلٌ قابلٌ للخصم** — والفرقُ مخزَّنٌ لا محكيّ');

$conn->query("UPDATE contract_guarantees SET nature='asset' WHERE id=" . (int) $bg['id']);
$q = $conn->query("SELECT nature FROM contract_guarantees WHERE id=" . (int) $bg['id'])->fetch_assoc();
check($q['nature'] === 'off_balance',
      '★★ و**قلبُ خطاب الضمان أصلًا بكتابةٍ مباشرة يرفضه `CHECK`** — الفصلُ بنيويّ');
$conn->query("UPDATE contract_guarantees SET deductible_from_claim=1 WHERE id=" . (int) $bg['id']);
$q = $conn->query("SELECT deductible_from_claim FROM contract_guarantees
                    WHERE id=" . (int) $bg['id'])->fetch_assoc();
check((int) $q['deductible_from_claim'] === 0,
      '★★ و**جعلُه قابلًا للخصم بكتابةٍ مباشرة يرفضه `CHECK`** كذلك');

// ═══ ★★★ البرهان ═══
head('★★★ **البرهان: خطابُ الضمان لا يُخصم من مستخلصٍ ولا يظهر أصلًا**');

$ded = CGS::deductibleInstruments($gate, $CID);
$kinds = array();
foreach ($ded as $d) { $kinds[] = (string) $d['kind']; }
check(count($ded) === 1 && $kinds[0] === 'cash_retention',
      '★★★ وقائمةُ **ما يجوز خصمُه من مستخلص** فيها **المحتجَزُ وحدَه** — '
      . 'لا خطابَ الضمان ولا التأمين (' . count($ded) . ' أداة)');

$exp = CGS::exposure($gate, $CID);
check(abs($exp['asset']) < 0.005,
      '★★★ و**الأصلُ صفرٌ** رغم وجود خطابِ ضمانٍ بـ50,000 وتأمينٍ بـ20,000 — «ولا يظهر أصلًا»');
check(abs($exp['off_balance'] - 70000.0) < 0.005,
      '★★ و**الالتزامُ المحتملُ خارج الميزانية 70,000** — بابُه غيرُ باب الأصل');
check(mb_strpos($exp['note'], 'لا يُجمعان') !== false,
      'و**الرقمان لا يُجمعان** معلَنًا: ' . $exp['note']);

// ═══ ③ الرصيدُ يُقرأ من مصدره ═══
head('③ **والرصيدُ يُقرأ من مصدره** — `claims` لا سجلُّ الضمانات');

$bal0 = CGS::retentionBalance($gate, $CID);
check(abs($bal0['balance']) < 0.005, 'ولا مستخلصَ بعدُ ⇒ رصيدُ المحتجَز **صفر**');

$conn->query("INSERT INTO claims (company_id, claim_no, contract_id, client_id, project_id,
              period_from, period_to, currency, gross_amount, retention_amount, net_amount,
              tax_amount, state, version, created_at)
              VALUES ({$CO}, 'CLM-{$MARK}', {$CID}, 1, {$PRJ}, '2086-01-01', '2086-01-31',
                      'USD', 10000, 1000, 9000, 0, 'approved', 1, NOW())");
$CLM = intval($conn->insert_id);
$bal1 = CGS::retentionBalance($gate, $CID);
check(abs($bal1['withheld'] - 1000.0) < 0.005 && abs($bal1['balance'] - 1000.0) < 0.005,
      '★★ ومستخلصٌ باحتجازِ 1,000 ⇒ **الرصيدُ 1,000 مقروءًا من المستخلص**');

$exp2 = CGS::exposure($gate, $CID);
check(abs($exp2['asset'] - 1000.0) < 0.005,
      '★★ و**الأصلُ صار 1,000** — وهو المحتجَزُ الفعليُّ لا سقفُ سطرِ السجل (6,000)');

$conn->query("INSERT INTO claim_lines (company_id, claim_id, source_kind, source_ref, work_date,
              unit_type, qty, unit_price, amount, created_at)
              VALUES ({$CO}, {$CLM}, 'retention_release', 0, '2086-06-30', 'lump', 1, -400, -400, NOW())");
$bal2 = CGS::retentionBalance($gate, $CID);
check(abs($bal2['released'] - 400.0) < 0.005 && abs($bal2['balance'] - 600.0) < 0.005,
      '★★ وردٌّ جزئيٌّ 400 ⇒ **الرصيدُ 600** — من المصدرَين القائمَين **لا من جدولٍ ثالث**');
$q = $conn->query("SELECT amount FROM contract_guarantees WHERE id=" . (int) $cr['id'])->fetch_assoc();
check(abs((float) $q['amount'] - 6000.0) < 0.005,
      'و**سطرُ السجل لم يتغيّر** (سقفُه 6,000) — فهو شروطٌ لا رصيد');

// ═══ ④ الحالُ والخروج ═══
head('④ **والخروجُ بسببه** — ولا أداةَ تخرج صامتة');

$noR = CGS::setState($conn, $gate, $CO, (int) $bg['id'], 'released', '  ', '2087-06-30', $ACTOR);
check(!$noR['ok'] && $noR['code'] === 422, 'إفراجٌ **بلا سبب** ⇒ **422**');
$rel = CGS::setState($conn, $gate, $CO, (int) $bg['id'], 'released',
                     'أُعيد الخطابُ للبنك بعد القبول النهائي', '2087-06-30', $ACTOR);
check($rel['ok'], '★ وبالسبب: ' . $rel['reason']);
$q = $conn->query("SELECT state, state_reason, needs_review FROM contract_guarantees
                    WHERE id=" . (int) $bg['id'])->fetch_assoc();
check($q['state'] === 'released' && $q['state_reason'] !== null,
      'والصفُّ **باقٍ** بحالِه وسببِه — **لا حذف**');
$exp3 = CGS::exposure($gate, $CID);
check(abs($exp3['off_balance'] - 20000.0) < 0.005,
      '★ و**الالتزامُ المحتملُ نزل إلى 20,000** بخروج الخطاب — والتأمينُ باقٍ');
$again = CGS::setState($conn, $gate, $CO, (int) $bg['id'], 'released', 'مرةً ثانية', '2087-06-30', $ACTOR);
check($again['ok'] && mb_strpos($again['reason'], 'عاطل') !== false, 'وتكرارُ الحال **فعلٌ عاطل**');

// ═══ ⑤ النثرُ نُقل ولم يُمحَ ═══
head('⑤ **والنثرُ نُقل ولم يُمحَ** — والآلةُ تقترح والمالكُ يُقرّ');

$moved = intval($conn->query("SELECT COUNT(*) c FROM contract_guarantees
                               WHERE source_text IS NOT NULL")->fetch_assoc()['c']);
check($moved >= 9, "★ **{$moved} أداةً** نُقلت من نص `contracts.guarantees` إلى السجل");
$still = intval($conn->query("SELECT COUNT(*) c FROM contracts
                               WHERE guarantees IS NOT NULL AND TRIM(guarantees) <> ''")
                      ->fetch_assoc()['c']);
check($still >= 9, "★★ و**النصُّ الأصليُّ باقٍ في {$still} عقدًا** — نُقل ولم يُمحَ");
$review = CGS::pendingReview($gate);
check(count($review) > 0,
      '★★ و**كلُّ منقولٍ يحمل `needs_review`** — الآلةُ تقترح والمالكُ يُقرّ (' . count($review) . ' صفًّا)');
$asset = intval($conn->query("SELECT COUNT(*) c FROM contract_guarantees
                               WHERE source_text IS NOT NULL AND nature='asset'")->fetch_assoc()['c']);
check($asset === 0,
      '★★★ و**صفرُ منقولٍ صار أصلًا** — والافتراضُ الآمن «خارجَ الميزانية»: لا يصير شيءٌ أصلًا بالصدفة');
$pledge = intval($conn->query("SELECT COUNT(*) c FROM contract_guarantees
                                WHERE source_text LIKE '%رهن%' AND kind='pledge'")->fetch_assoc()['c']);
check($pledge > 0, "و«رهن سيارة» صُنّف **رهنًا** ({$pledge} صفًّا) — بمطابقةٍ حرفيةٍ لا تخمين");

fwrite(STDOUT, "\n══ النتيجة: {$PASS} ناجحة · {$FAIL} فاشلة ══\n");
exit($FAIL === 0 ? 0 : 1);
