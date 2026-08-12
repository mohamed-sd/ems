<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * M-10 — اختبار قبول: توليدُ صفوف الالتزام عند اعتماد الملحق + موازنةُ الحاويات
 * (CON-02 §6 · A6 §8.1 · UX-05 §8.2)
 * ───────────────────────────────────────────────────────────────────────────
 * التشغيل:  php tests/m10_amendment_effects_test.php
 *
 * ما يُثبته:
 *   ① التوليد: ملحقُ «تغيير التزامات» يولّد صفَّ مصفوفةٍ مسودةً بسريانه ذريًّا —
 *      والأنواعُ/الأطرافُ الأجنبية والسريانُ الغائب تُرفض، والملتزمُ نفسُه لا ملحقَ له.
 *   ② A6: إجازةُ الصف الجديد تقفل سلفَه المعتمدَ المفتوح على (السريان − 1) —
 *      «ما قبله بحكمه القديم وما بعده بالجديد» — والأقدمُ المقفولُ سلفًا لا يُمسّ.
 *   ③ الموازنة: زيادةُ نطاقٍ ترفع cap الجذرِ الساعيِّ الواحد · تخفيضٌ دون
 *      المخصَّص/المستهلَك يُرفض بمرجع الحاوية · تعددُ الجذور موازنةٌ يدويةٌ معلَنة.
 *   ④ الوصل: المعالجُ يضم عمليات الموازنة لمعاملة الملحق، وشاشةُ المصفوفة
 *      تستدعي إقفالَ الأسلاف في مسارَي الإجازة (فحصُ مصدر).
 *
 * البذرُ معزول: عقدٌ صوريٌّ بوسم M10T_<pid> والتزاماتٌ وحاوياتٌ تحته تُكنس كاملةً.
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/app/Core/TenantRegistry.php';
require_once dirname(__DIR__) . '/app/Core/TenantContext.php';
require_once dirname(__DIR__) . '/app/Core/TenantGateException.php'; // deny() ترميها — بدونها «Class not found» يبتلع رسالة UQ
require_once dirname(__DIR__) . '/app/Core/TenantDb.php';
require_once dirname(__DIR__) . '/app/Services/Contract/ClientAmendmentEffects.php';

use App\Core\TenantDb;
use App\Core\TenantContext;
use App\Services\Contract\ClientAmendmentEffects as CAE;

while (ob_get_level() > 0) { ob_end_clean(); }

$PASS = 0; $FAIL = 0;
function ok($m)  { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m) { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ {$m}\n"); }
function check($c, $m) { $c ? ok($m) : bad($m); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$MARK = 'M10T_' . getmypid();
$CO = 4; $ACTOR = 999901;

$teardown = function () use ($conn) {
    $ids = array();
    $r = $conn->query("SELECT id FROM contracts WHERE first_party LIKE 'M10T_%'");
    if ($r) { while ($row = $r->fetch_assoc()) { $ids[] = intval($row['id']); } }
    foreach ($ids as $cid) {
        $conn->query("DELETE FROM contract_obligations WHERE client_contract_id = {$cid}");
        $conn->query("DELETE FROM contract_amendments WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM op_containers WHERE contract_id = {$cid}");
        $conn->query("DELETE FROM contracts WHERE id = {$cid}");
    }
    /* **وبعائلةِ الوسمِ أيضًا**: الكنسُ بـ`contract_id` وحدَه لا يمسّ حاويةً
       وُلدت بعقدٍ = 0 (أو بقيت بعد حذفِ عقدها) — والقياسُ وجد **22 حاويةً**
       بهذه العائلةِ باقيةً من أشواطٍ سابقة، نصفُها جذورٌ بعدّادٍ موزَّعٍ وبلا
       ابنٍ فتُحسب خللًا في ثابتِ «الأبُ يحمل العدّاد». والأبناءُ قبلَ الآباء. */
    /* والسلسلةُ ثلاثُ طبقاتٍ (تسويةُ التغطيةِ ← التغطيةُ ← الحاوية) والمفتاحُ
       الأبويُّ ذاتيٌّ — فالكنسُ في مكانٍ واحدٍ يعرفها كلَّها. */
    require_once __DIR__ . '/_container_sweep.php';
    ems_sweep_container_family($conn, 'M10T_%');
};
register_shutdown_function($teardown);
$teardown();

fwrite(STDOUT, "\n══ M-10 — توليدُ صفوف الالتزام من الملحق وموازنةُ الحاويات ══\n");

// ═══ بذرٌ صوري ═══
head('البذر — عقدٌ صوريٌّ بالتزام وقودٍ معتمدٍ وجذرِ حاوية');
$gate = new TenantDb($conn, TenantContext::forSystem($CO, $ACTOR, '', true));
/* ◆ **عيبٌ مقيس**: `contracts.project_id` عمودٌ **إلزاميٌّ بمفتاحٍ أجنبيٍّ**
     إلى `project(id)` (`fk_contracts_project`)، وكانت البذرةُ تُغفله فيهبط
     إلى صفرٍ فترفضه القاعدةُ: «Cannot add or update a child row». فيعود
     `insert_id = 0` ويتساقط كلُّ ما بعده (13 فحصًا) — والعطلُ في البذرةِ
     لا في الحكمِ المفحوص. فيُؤخذ مشروعٌ حقيقيٌّ لهذه الشركة. */
$PRJ = (int) $conn->query("SELECT id FROM project WHERE company_id={$CO} ORDER BY id LIMIT 1")
                  ->fetch_row()[0];
$ok = $conn->query("INSERT INTO contracts (company_id, project_id, contract_signing_date,
                      first_party, forecasted_contracted_hours)
                    VALUES ({$CO}, {$PRJ}, '2026-01-01', '{$MARK}', 1000)");
$CID = intval($conn->insert_id);
if (!$ok) { fwrite(STDOUT, '  ⚠ ' . mb_substr($conn->error, 0, 120) . "\n"); }
check($ok === true && $CID > 0, "عقدٌ صوري #{$CID} (يُكنس في النهاية)");
$conn->query("INSERT INTO contract_obligations
    (company_id, client_contract_id, obligation_type, obligor, effect_on_billing,
     approval_state, valid_from, created_by)
    VALUES ({$CO}, {$CID}, 'fuel', 'client', 'billable_standby', 'approved', '2026-01-01', {$ACTOR})");
$OBL_OLD = intval($conn->insert_id);
$conn->query("INSERT INTO op_containers
    (company_id, container_no, level, parent_id, contract_id, unit_type, cap_qty, allocated_qty, consumed_qty,
     valid_from, state, origin, created_by)
    VALUES ({$CO}, '{$MARK}-ROOT', 'رئيسية', NULL, {$CID}, 'hour', 1000.00, 300.00, 100.00,
     '2026-01-01', 'نشطة', 'عقد', {$ACTOR})");
$ROOT = intval($conn->insert_id);
check($OBL_OLD > 0 && $ROOT > 0, "التزامُ وقودٍ معتمدٌ #{$OBL_OLD} (client · مفتوح) + جذرٌ #{$ROOT} (cap 1000 · مخصَّص 300)");

// ═══ ① التوليد ═══
head('① ملحقُ «تغيير التزامات» يولّد صفَّ المصفوفة مسودةً');
$r = CAE::changeObligation($conn, $gate, $CO, $CID, array('obligation_type' => 'insurance',
    'obligor' => 'company', 'effect_on_billing' => 'non_billable', 'effective_from' => '2026-09-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'بندٌ من خارج قائمة §4 → 422');
$r = CAE::changeObligation($conn, $gate, $CO, $CID, array('obligation_type' => 'fuel',
    'obligor' => 'partner', 'effect_on_billing' => 'non_billable', 'effective_from' => '2026-09-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'طرفٌ من خارج القائمة → 422');
$r = CAE::changeObligation($conn, $gate, $CO, $CID, array('obligation_type' => 'fuel',
    'obligor' => 'company', 'effect_on_billing' => 'non_billable', 'effective_from' => ''), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'سريانٌ غائب → 422 «ملحقٌ بسريان»');
$r = CAE::changeObligation($conn, $gate, $CO, $CID, array('obligation_type' => 'fuel',
    'obligor' => 'client', 'effect_on_billing' => 'billable_standby', 'effective_from' => '2026-09-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 422, 'الملتزمُ الجديد هو النافذُ نفسُه → 422 (لا ملحقَ بلا تغيير)');

// A6 حرفيًّا: ملحقٌ يغيّر ملتزمَ الوقود من العميل إلينا بسريان الأول
$r = CAE::changeObligation($conn, $gate, $CO, $CID, array('obligation_type' => 'fuel',
    'obligor' => 'company', 'effect_on_billing' => 'non_billable',
    'effective_from' => '2026-09-01', 'reason' => 'اتفاقٌ جديدٌ على الوقود'), $ACTOR);
check($r['ok'] && intval($r['amendment_id']) > 0 && intval($r['obligation_id']) > 0,
      'الملحقُ وصفُّ المصفوفة وُلدا ذريًّا (A6: وقودٌ من العميل إلينا بسريان الأول)');
$AMD = intval($r['amendment_id']); $OBL_NEW = intval($r['obligation_id']);
$am = $conn->query("SELECT amend_type, effective_from, old_value, new_value FROM contract_amendments WHERE id = {$AMD}")->fetch_assoc();
check($am['amend_type'] === 'تغيير التزامات' && $am['effective_from'] === '2026-09-01'
      && $am['old_value'] === 'client' && $am['new_value'] === 'company',
      'الملحقُ بنوعه وسريانه وقبل/بعد الصادقَين (قبل = النافذُ الحي)');
$ob = $conn->query("SELECT approval_state, obligor, valid_from FROM contract_obligations WHERE id = {$OBL_NEW}")->fetch_assoc();
check($ob['approval_state'] === 'draft' && $ob['obligor'] === 'company' && $ob['valid_from'] === '2026-09-01',
      'الصفُّ المولَّد **مسودةٌ** بسريان الملحق — الإجازةُ بيد المالية (ق-18)');
$r = CAE::changeObligation($conn, $gate, $CO, $CID, array('obligation_type' => 'fuel',
    'obligor' => 'supplier', 'effect_on_billing' => 'per_clause', 'effective_from' => '2026-09-01'), $ACTOR);
check(!$r['ok'] && $r['code'] === 409, 'سريانٌ مكررٌ للبند → 409 (UQ) — والملحقُ لم يُدرَج (الذرّية)');
$n = intval($conn->query("SELECT COUNT(*) c FROM contract_amendments WHERE contract_id = {$CID}")->fetch_assoc()['c']);
check($n === 1, 'ملحقٌ واحدٌ فقط — المرفوضُ تراجع كلُّه');

// ═══ ② A6 — الإجازةُ تقفل السلف ═══
head('② الإجازةُ تقفل السلفَ على (السريان − 1) — ما قبله بحكمه');
$conn->query("UPDATE contract_obligations SET approval_state='approved', approved_by={$ACTOR}, approved_at=NOW()
              WHERE id = {$OBL_NEW}");
$row = $conn->query("SELECT * FROM contract_obligations WHERE id = {$OBL_NEW}")->fetch_assoc();
$closed = CAE::closePredecessors($gate, $row);
check($closed === 1, 'أُقفل سلفٌ واحد');
$old = $conn->query("SELECT valid_to, obligor, valid_from FROM contract_obligations WHERE id = {$OBL_OLD}")->fetch_assoc();
check($old['valid_to'] === '2026-08-31' && $old['obligor'] === 'client' && $old['valid_from'] === '2026-01-01',
      'السلفُ أُقفل على 2026-08-31 وحكمُه (client من 2026-01-01) لم يُمسّ — A6 نصًّا');
$closed = CAE::closePredecessors($gate, $row);
check($closed === 0, 'إعادةُ الاستدعاء عاطلة — المقفولُ لا يُقفل ثانية');

// ═══ ③ الموازنة ═══
head('③ موازنةُ الجذر — الزيادةُ ترفع والتخفيضُ دون المخصَّص يُرفض');
$contract = $gate->selectOne('contracts', array('where' => array('id' => $CID)));
$r = CAE::rebalanceOps($gate, $contract, 200);
check($r['ok'] && count($r['ops']) === 1 && floatval($r['ops'][0]['data']['cap_qty']) === 1200.0,
      'زيادةُ 200 → عمليةُ رفع cap إلى 1200 (تنضم لمعاملة الملحق)');
$r = CAE::rebalanceOps($gate, $contract, -800);
check(!$r['ok'] && strpos($r['reason'], '#' . $ROOT) !== false,
      'تخفيضُ 800 (cap 200 < مخصَّص 300) → رفضٌ **بمرجع الحاوية** (UX-05 §8.2)');
$r = CAE::rebalanceOps($gate, $contract, -600);
check($r['ok'] && floatval($r['ops'][0]['data']['cap_qty']) === 400.0,
      'تخفيضُ 600 → cap 400 ≥ المخصَّص 300 — يمضي');
// تعددُ الجذور → موازنةٌ يدويةٌ معلَنة (لا اجتهادَ في التوزيع)
$conn->query("INSERT INTO op_containers
    (company_id, container_no, level, parent_id, contract_id, unit_type, cap_qty, allocated_qty, consumed_qty,
     valid_from, state, origin, created_by)
    VALUES ({$CO}, '{$MARK}-ROOT2', 'رئيسية', NULL, {$CID}, 'hour', 500.00, 0.00, 0.00,
     '2026-01-01', 'نشطة', 'عقد', {$ACTOR})");
$r = CAE::rebalanceOps($gate, $contract, 100);
check($r['ok'] && count($r['ops']) === 0 && strpos($r['note'], 'يدوية') !== false,
      'جذران → صفرُ عملياتٍ وموازنةٌ يدويةٌ **معلَنة** (لا تلفيقَ توزيع)');

// ═══ ④ الوصلُ بالمسار الحي ═══
head('④ الوصل — المعالجُ والشاشةُ (فحصُ مصدر)');
$h = file_get_contents(dirname(__DIR__) . '/Contracts/contract_actions_handler.php');
check(strpos($h, 'rebalanceOps') !== false && strpos($h, "action === 'change_obligation'") !== false
      && strpos($h, 'changeObligation') !== false,
      'المعالج: موازنةُ settlement داخل معاملته + فعلُ change_obligation');
check(strpos($h, "if (!\$rebalance['ok'])") !== false,
      'فشلُ الفحص المسبق يُسقط الملحقَ كلَّه — لا ملحقَ بلا موازنته');
$s = file_get_contents(dirname(__DIR__) . '/Contracts/contract_obligations.php');
check(substr_count($s, 'closePredecessors') >= 2,
      'شاشةُ المصفوفة: مسارا الإجازة (one/all) يقفلان الأسلاف');

// ═══ النتيجة ═══
fwrite(STDOUT, "\n══ النتيجة: {$PASS} نجاح · {$FAIL} فشل ══\n");
exit($FAIL > 0 ? 1 : 0);
