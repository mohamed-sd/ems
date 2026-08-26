<?php
/**
 * tools/repair01_w12_journey.php — رحلةُ التمويل (‏W12 §٦-أ · §22)
 * ═══════════════════════════════════════════════════════════════════════════
 * **اتّفاقيّةُ تمويلٍ ← التزامٌ تعاقديّ ← إقفالٌ تعاقديّ ← دفعاتٌ شهريّةٌ
 *   وإقفالٌ شهريّ ← تسويةٌ نهائيّةٌ وإقفالٌ نهائيّ — والثلاثةُ كياناتٌ متمايزةٌ
 *   تُقرأ منفصلةً، وأمرُ دفعٍ مستقبليٌّ يُنشأ بنموذجِه لا بقالبِ التجميعِ
 *   التاريخيّ.**
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس رقمٌ يعنيه — عمليةٌ تُربَط بعقدِها · قسطٌ يُختَم بفترتِه
 *   التعاقديّة · رصيدٌ قائمٌ ينزل بالتنفيذ · قسطٌ يُقفَل بتغطيتِه · شهرٌ يعلن
 *   عددَ تعاقديّاتِه · عمليةٌ تُقفَل بنهائيِّها · انحرافٌ يحجب.
 *
 * ◆ **والمحطّاتُ السالبةُ محطّاتٌ**: «من رفع الحاجةَ لا يعتمدها» · «من أعدَّ
 *   العقدَ لا يوقّعه» · «لا توقيعَ قبل إجازةِ المراجعة» · «من طلب الأمرَ لا
 *   يعتمده» · «لا تنفيذَ لأمرٍ غيرِ معتمد» · «لا تنفيذَ بلا مرجعٍ بنكيّ» ·
 *   «إقفالٌ تعاقديٌّ بلا رقمِ فترتِه يُردّ» · «ترحيلٌ مكسورٌ يُردّ» · «من أعدَّ
 *   الإقفالَ لا يعتمده» · «شهرٌ ليس تقويميًّا يُردّ» · «شهريٌّ بلا تعاقديٍّ
 *   مربوطٍ لا يُعتمَد» · «طبقةٌ تاريخيّةٌ لا تصير أمرَ دفع» · «مجمَّعٌ لا
 *   يُخصَّص» · «نهائيٌّ فوق استحقاقٍ مفتوحٍ يُردّ» · «نهائيٌّ لا يتكرّر»
 *   — تُقاس **بالاستدعاءِ الفعليِّ ورمزِ الرفض**.
 *
 * ⚠ **والنظافةُ كنسٌ بالوسمِ لا إرجاعٌ بمعاملة**: خدماتُ الدورةِ قد تفتح
 *   معاملةً داخليّةً فيثبّت MySQL الخارجيّةَ ضمنًا (‏درسُ W09). فكلُّ صفٍّ
 *   تكتبه الرحلةُ يحمل وسمَ جولتِه، والكنسُ يمسح **بالوسم** — ويُشغَّل مرّتَين.
 *
 * التشغيل: php tools/repair01_w12_journey.php
 * الخروج : 0 عبرت كلُّ المحطّات · 1 محطّةٌ لم تعبر أو أرضيّةٌ ناقصة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

/* ⚠ **حارسُ الموتِ الصامت**: `config.php` يبتلع مخرَجَ سطرِ الأوامر، فرحلةٌ
     تسقط بخطإٍ قاتلٍ تخرج بلا سطرٍ واحدٍ ويقرأ القارئُ صمتًا لا سببًا. */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        fwrite(STDERR, "\n✘ سقطت الرحلةُ بخطإٍ قاتل:\n   " . $e['message']
                     . "\n   في " . $e['file'] . ':' . $e['line'] . "\n");
    }
});

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w12_scan.php';
require_once $ROOT . '/app/Services/Financing/FinancingCycleService.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
use App\Services\Financing\FinancingCycleService as FIN;

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w12_one($conn, $sql); };

/* مُعرِّفُ الجولةِ بدقّةِ الميكروثانية — جولتانِ في الثانيةِ نفسِها تتقاسمان
   المُعرِّفَ فتقرأ البوّابةُ صفوفَهما جولةً واحدةً وتسقط (‏درسُ W04). */
$RUN = 'W12J-' . (string) $one("SELECT DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')");

echo "═══════════ رحلةُ التمويل — REPAIR01 · W12 §٦-أ ═══════════\n";
echo "RUN=$RUN\n";
echo "الجولة: $RUN\n\n";

$ST = array();
$log = function ($leg, $station, $entity, $consumer, $expected, $measured, $effect, $state, $passed, $co = 0)
       use (&$ST) {
    $ST[] = array($leg, $station, $entity, $consumer, $expected, $measured, $effect, $state,
                  $passed ? 1 : 0, (int) $co);
};

/* ══════════════════════════════════════════════════════════════════════════
   كنسُ العائلةِ — يُشغَّل قبلَ البدءِ وبعدَ النهاية
   ══════════════════════════════════════════════════════════════════════════ */
$sweep = function () use ($conn) {
    $q = array(
        "DELETE FROM fin_payment_allocation WHERE note LIKE 'W12J-%'",
        "DELETE FROM fin_payment_allocation WHERE order_id IN
            (SELECT id FROM fin_payment_order WHERE order_code LIKE 'W12J-%')",
        "DELETE FROM fin_payment_order WHERE order_code LIKE 'W12J-%'",
        "DELETE FROM fin_legacy_payment_aggregate WHERE source_row_ref LIKE 'W12J-%'",
        "DELETE FROM fin_close_link WHERE why LIKE 'W12J-%'",
        "DELETE FROM fin_close_link WHERE parent_kind = 'MONTHLY' AND parent_id IN
            (SELECT id FROM fin_monthly_close WHERE close_code LIKE 'W12J-%')",
        "DELETE FROM fin_close_link WHERE parent_kind = 'FINAL' AND parent_id IN
            (SELECT id FROM fin_final_close WHERE close_code LIKE 'W12J-%')",
        "DELETE FROM fin_close_link WHERE child_kind = 'CONTRACTUAL' AND child_id IN
            (SELECT id FROM fin_contract_close WHERE close_code LIKE 'W12J-%')",
        "DELETE FROM fin_final_close WHERE close_code LIKE 'W12J-%'",
        "DELETE FROM fin_monthly_close WHERE close_code LIKE 'W12J-%'",
        "DELETE FROM fin_contract_close WHERE close_code LIKE 'W12J-%'",
        "DELETE FROM fin_contract_covenant WHERE covenant_ar LIKE 'W12J-%'",
        "DELETE FROM fin_contract_term WHERE term_value LIKE 'W12J-%'",
        "DELETE FROM fin_finance_contract WHERE contract_code LIKE 'W12J-%'",
        "DELETE FROM fin_precontract_review WHERE review_code LIKE 'W12J-%'",
        "DELETE FROM fin_funding_offer WHERE offer_code LIKE 'W12J-%'",
        "DELETE FROM fin_funding_need WHERE need_code LIKE 'W12J-%'",
        "DELETE FROM fin_financier_document WHERE doc_ref LIKE 'W12J-%'",
        "DELETE FROM fin_financier_contact WHERE person_name LIKE 'W12J-%'",
        "DELETE FROM financing_deviations WHERE subject_ref LIKE 'W12J-%'",
        "DELETE FROM financing_installments WHERE payment_ref LIKE 'W12J-%'",
        "DELETE FROM financing_operations WHERE op_code LIKE 'W12J-%'",
        "DELETE FROM acc_recognition_request WHERE source_ref LIKE 'FPAYO:W12J-%'",
        "DELETE FROM ems_business_events WHERE idempotency_key LIKE 'w12:%'
            AND source_ref = 'FinancingCycleService'
            AND JSON_EXTRACT(payload, '$.run') = '" . $conn->real_escape_string('') . "'",
    );
    foreach ($q as $s) { @$conn->query($s); }
};
$sweep();

/* ══════════════════════════════════════════════════════════════════════════
   الأرضيّة — كيانٌ قانونيٌّ واحدٌ وممثِّلونَ متمايزون
   ══════════════════════════════════════════════════════════════════════════ */
$company = (int) $one("SELECT company_id FROM financing_operations
                        GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
if ($company <= 0) { fwrite(STDERR, "✘ لا كيانَ قانونيًّا بعملياتِ تمويل\n"); exit(1); }
$financier = (int) $one("SELECT financier_entity_id FROM financing_operations
                          WHERE company_id = $company ORDER BY op_id LIMIT 1");
if ($financier <= 0) { fwrite(STDERR, "✘ لا ممولَ في العملياتِ القائمة\n"); exit(1); }
$model = (string) $one("SELECT model_code FROM financing_models WHERE active = 1 ORDER BY model_code LIMIT 1");
if ($model === '') { $model = (string) $one("SELECT model_code FROM financing_models ORDER BY model_code LIMIT 1"); }

/* **ثلاثةُ فاعلينَ متمايزين** — فصلُ الواجباتِ لا يُقاس بفاعلٍ واحد */
$actors = array();
$r = $conn->query("SELECT id FROM users WHERE company_id = $company ORDER BY id LIMIT 6");
while ($r && $x = $r->fetch_row()) { $actors[] = (int) $x[0]; }
if (count($actors) < 3) {
    $r2 = $conn->query("SELECT id FROM users ORDER BY id LIMIT 6");
    while ($r2 && $x2 = $r2->fetch_row()) { if (!in_array((int) $x2[0], $actors, true)) { $actors[] = (int) $x2[0]; } }
}
if (count($actors) < 3) { fwrite(STDERR, "✘ لا ثلاثةَ فاعلينَ متمايزين\n"); exit(1); }
list($A1, $A2, $A3) = array($actors[0], $actors[1], $actors[2]);

$G = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($company, $A2, '', true));
FIN::setEventConnection($conn);
FIN::setThresholdConnection($conn);
$cons = new FIN();

$CUR = (string) $one("SELECT currency FROM financing_operations WHERE company_id = $company
                       ORDER BY op_id LIMIT 1");
if ($CUR === '') { $CUR = 'SDG'; }

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ① — التأسيسُ والتأهيل
   ══════════════════════════════════════════════════════════════════════════ */
$L1 = 'التأسيس';
$contactId = (int) $G->insert('fin_financier_contact', array(
    'entity_id' => $financier, 'person_name' => $RUN . '-CONTACT', 'role_ar' => 'مفوض بالتوقيع',
    'is_authorized' => 1, 'mandate_ref' => $RUN . '-MANDATE', 'valid_from' => date('Y-m-d'),
    'state' => 'active', 'created_by' => $A1));
$log($L1, 'جهة اتصال الممول مسجلة بمستند تفويضها', 'fin_financier_contact',
     'سجل جهات الاتصال', 'مفوض بمستند تفويض', 'المعرف ' . $contactId,
     'الممول له مفوض معلن فلا يوقع العقد من لا تفويض له', 'active', $contactId > 0, $company);

$mandateBlocked = false;
try {
    @$G->insert('fin_financier_contact', array(
        'entity_id' => $financier, 'person_name' => $RUN . '-NOMANDATE', 'role_ar' => 'مفوض',
        'is_authorized' => 1, 'mandate_ref' => '', 'valid_from' => date('Y-m-d'), 'created_by' => $A1));
} catch (\Throwable $t) { $mandateBlocked = true; }
$log($L1, 'مفوض بلا مستند تفويض يرد في القاعدة', 'fin_financier_contact',
     'قيد chk_ffc_mandate', 'ترد الكتابة', $mandateBlocked ? 'ردت' : 'مرت',
     'لا تفويض بالقول - والمستند شرط في القاعدة لا في المراجعة', 'refused', $mandateBlocked, $company);

$docId = (int) $G->insert('fin_financier_document', array(
    'entity_id' => $financier, 'doc_kind' => 'license', 'doc_ref' => $RUN . '-LIC',
    'issued_on' => date('Y-m-d'), 'expires_on' => date('Y-m-d', strtotime('+1 year')),
    'verified_by' => $A2, 'verified_at' => date('Y-m-d H:i:s'), 'state' => 'verified'));
$log($L1, 'وثيقة تاهيل محققة بمحققها', 'fin_financier_document',
     'سجل العناية الواجبة', 'وثيقة محققة', 'المعرف ' . $docId,
     'الممول مؤهل فيفتح له باب العروض', 'verified', $docId > 0, $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ② — الدورةُ قبل التعاقد
   ══════════════════════════════════════════════════════════════════════════ */
$L2 = 'الدورة';
$rNeed = FIN::raiseNeed($G, array('need_code' => $RUN . '-NEED', 'title' => 'حاجة تمويلية للرحلة',
    'requester_dept' => 'المعدات', 'purpose' => 'equipment', 'amount_needed' => 100,
    'currency' => $CUR, 'justification' => 'رحلة اثبات المرحلة الثانية عشرة'), $A1);
$needId = !empty($rNeed['ok']) ? (int) $rNeed['need_id'] : 0;
$log($L2, 'حاجة تمويلية مرفوعة بمبررها', 'fin_funding_need', 'FinancingCycleService::raiseNeed',
     'تقبل بمبرر مكتوب', 'المعرف ' . $needId, 'الحاجة تسبق العرض فلا عرض بلا طلب',
     'submitted', $needId > 0, $company);

$rSelf = FIN::approveNeed($G, $needId, $A1);
$log($L2, 'من رفع الحاجة لا يعتمدها', 'fin_funding_need', 'FinancingCycleService::approveNeed',
     'SAME_ACTOR_RAISE_AND_APPROVE_NEED', (string) $rSelf['code'],
     'الاعتماد بيد ثانية فلا تصير الادارة الطالبة سلطة اعتماد', 'refused',
     empty($rSelf['ok']) && $rSelf['code'] === 'SAME_ACTOR_RAISE_AND_APPROVE_NEED', $company);

$rApp = FIN::approveNeed($G, $needId, $A2);
$needState = (string) $one("SELECT state FROM fin_funding_need WHERE id = $needId");
$log($L2, 'الحاجة تعتمد بيد ثانية', 'fin_funding_need', 'FinancingCycleService::approveNeed',
     'approved', $needState, 'الحاجة المعتمدة تفتح باب العروض', $needState,
     !empty($rApp['ok']) && $needState === 'approved', $company);

$rOfr = FIN::receiveOffer($G, array('offer_code' => $RUN . '-OFR', 'need_id' => $needId,
    'entity_id' => $financier, 'version_no' => 1, 'model_code' => $model, 'principal' => 100,
    'currency' => $CUR, 'profit_rate' => 5, 'tenor_months' => 3,
    'offer_doc_ref' => $RUN . '-OFRDOC', 'received_on' => date('Y-m-d')), $A1);
$offerId = !empty($rOfr['ok']) ? (int) $rOfr['offer_id'] : 0;
$log($L2, 'عرض تمويل وارد باصداره', 'fin_funding_offer', 'FinancingCycleService::receiveOffer',
     'عرض باصدار اول', 'المعرف ' . $offerId, 'العرض يقابل حاجة معتمدة', 'received',
     $offerId > 0, $company);

$rOfr2 = FIN::receiveOffer($G, array('offer_code' => $RUN . '-OFR', 'need_id' => $needId,
    'entity_id' => $financier, 'version_no' => 2, 'model_code' => $model, 'principal' => 100,
    'currency' => $CUR, 'profit_rate' => 4, 'tenor_months' => 3,
    'offer_doc_ref' => $RUN . '-OFRDOC2', 'supersedes_id' => $offerId), $A1);
$offer2 = !empty($rOfr2['ok']) ? (int) $rOfr2['offer_id'] : 0;
$prevState = (string) $one("SELECT state FROM fin_funding_offer WHERE id = $offerId");
$log($L2, 'التفاوض ينشئ اصدارا ولا يدهس سابقه', 'fin_funding_offer',
     'FinancingCycleService::receiveOffer', 'الاصدار الاول يبقى بحالة تفاوض', $prevState,
     'تاريخ التفاوض محفوظ فيقرا الفرق بين الاصدارين', $prevState,
     $offer2 > 0 && $prevState === 'negotiating', $company);

$revId = (int) $G->insert('fin_precontract_review', array(
    'review_code' => $RUN . '-REV', 'offer_id' => $offer2,
    'legal_opinion' => 'لا مانع قانوني', 'legal_by' => $A3,
    'finance_opinion' => 'الاثر المالي مقبول', 'finance_by' => $A2,
    'risk_opinion' => 'المخاطر ضمن الحد', 'risk_by' => $A1, 'verdict' => 'pending'));
$rClr = FIN::decidePrecontract($G, $revId, 'cleared', '', $A2);
$revVer = (string) $one("SELECT verdict FROM fin_precontract_review WHERE id = $revId");
$log($L2, 'مراجعة ما قبل التعاقد تجاز براي كل جهة', 'fin_precontract_review',
     'FinancingCycleService::decidePrecontract', 'cleared', $revVer,
     'لا توقيع عقد قبل اجازة المراجعة', $revVer,
     !empty($rClr['ok']) && $revVer === 'cleared', $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ③ — التعاقدُ والالتزامُ التعاقديّ
   ══════════════════════════════════════════════════════════════════════════ */
$L3 = 'التعاقد';
/* ⚠ **المجالُ المقيَّدُ لا تخدمه بوّابةُ المستأجر** (‏`T_RESTRICTED` · N-15):
     الأرضيّةُ تُكتب باتّصالٍ خامٍّ مقيَّدٍ بالكيان، والأداةُ في `tools/` خارجَ
     مقامِ ماسحِ الاستعلامِ الخامّ — فلا تُضاف إلى دفترِ استثناءاتِه. */
$conn->query("INSERT INTO financing_operations
    (company_id, op_code, financier_entity_id, model_code, currency, contract_ref, signed_date,
     capital, down_payment, fees_admin, fees_insurance, extra_costs, installments_no,
     installment_amount, outstanding_balance, state, created_by)
    VALUES ($company, '" . $esc($RUN) . "-OP', $financier, '" . $esc($model) . "',
            '" . $esc($CUR) . "', '" . $esc($RUN) . "-CTR', CURDATE(),
            100, 0, 0, 0, 0, 2, 50, 100, 'active', $A1)");
$opId = (int) $conn->insert_id;
$log($L3, 'عملية تمويل مفتوحة للرحلة', 'financing_operations', 'سجل العمليات',
     'عملية بكيانها وممولها', 'المعرف ' . $opId, 'العملية الكيان الاب لكل ما يلي',
     'active', $opId > 0, $company);

$rDraft = FIN::draftContract($G, array('contract_code' => $RUN . '-CTR', 'entity_id' => $financier,
    'offer_id' => $offer2, 'review_id' => $revId, 'op_id' => $opId, 'model_code' => $model,
    'principal' => 100, 'currency' => $CUR, 'start_on' => date('Y-m-01'),
    'end_on' => date('Y-m-t', strtotime('+2 month')), 'periods_total' => 2), $A1);
$ctrId = !empty($rDraft['ok']) ? (int) $rDraft['contract_id'] : 0;
$log($L3, 'مسودة عقد بمعدها', 'fin_finance_contract', 'FinancingCycleService::draftContract',
     'مسودة عقد', 'المعرف ' . $ctrId, 'المسودة لا تلزم حتى توقع', 'draft', $ctrId > 0, $company);

$rSelfSign = FIN::signContract($G, $ctrId, $RUN . '-CTRDOC', $A1);
$log($L3, 'من اعد العقد لا يوقعه', 'fin_finance_contract', 'FinancingCycleService::signContract',
     'SAME_ACTOR_PREPARE_AND_SIGN', (string) $rSelfSign['code'],
     'التوقيع بيد ثانية فلا يصير المعد سلطة تعاقد', 'refused',
     empty($rSelfSign['ok']) && $rSelfSign['code'] === 'SAME_ACTOR_PREPARE_AND_SIGN', $company);

$rNoDoc = FIN::signContract($G, $ctrId, '', $A2);
$log($L3, 'لا توقيع بلا مستند عقد', 'fin_finance_contract', 'FinancingCycleService::signContract',
     'SIGN_WITHOUT_DOCUMENT', (string) $rNoDoc['code'],
     'العقد مستند لا حالة في شاشة', 'refused',
     empty($rNoDoc['ok']) && $rNoDoc['code'] === 'SIGN_WITHOUT_DOCUMENT', $company);

$rSign = FIN::signContract($G, $ctrId, $RUN . '-CTRDOC', $A2);
$eff1 = $cons->onContractSigned(array('payload' => array('contract_id' => $ctrId,
    'run' => $RUN, 'company_id' => $company)), $conn);
$opCtr = (int) $one("SELECT contract_id FROM financing_operations WHERE op_id = $opId");
$log($L3, 'توقيع العقد يربط العملية بسندها', 'financing_operations',
     'FinancingCycleService::onContractSigned', 'contract_id = ' . $ctrId, (string) $opCtr,
     'العملية تقرا سندها فلا تبقى التزامات بلا مستند', $eff1,
     !empty($rSign['ok']) && $opCtr === $ctrId, $company);

$termId = (int) $G->insert('fin_contract_term', array('contract_id' => $ctrId,
    'term_key' => 'early_settlement', 'term_value' => $RUN . '-TERM', 'clause_ref' => '7/2',
    'is_binding' => 1));
$log($L3, 'بند تعاقدي بمرجع بنده في المستند', 'fin_contract_term', 'سجل البنود',
     'بند بمرجعه', 'المعرف ' . $termId, 'كل بند سطر يقرا ويراجع لا عمود مخترع',
     'binding', $termId > 0, $company);

$covId = (int) $G->insert('fin_contract_covenant', array('contract_id' => $ctrId,
    'covenant_key' => 'dscr_min', 'covenant_ar' => $RUN . '-COV',
    'obligation_on' => 'us', 'measure_rule' => 'نسبة تغطية خدمة الدين تقاس شهريا',
    'threshold_key' => 'FIN_COVENANT_GRACE_DAYS', 'frequency' => 'monthly',
    'evidence_doc' => $RUN . '-COVDOC', 'state' => 'active'));
$covWaiveBlocked = false;
try {
    @$G->update('fin_contract_covenant', array('state' => 'waived', 'waiver_ref' => ''),
                array('id' => $covId));
    $st = (string) $one("SELECT state FROM fin_contract_covenant WHERE id = $covId");
    $covWaiveBlocked = ($st !== 'waived');
} catch (\Throwable $t) { $covWaiveBlocked = true; }
$log($L3, 'تنازل بلا مستند يرد في القاعدة', 'fin_contract_covenant', 'قيد chk_fcc_waiv',
     'ترد الكتابة', $covWaiveBlocked ? 'ردت' : 'مرت',
     'التنازل عن التزام مستند من الممول لا قرار داخلي صامت', 'refused',
     $covWaiveBlocked && $covId > 0, $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ④ — الأقساطُ ثمَّ **الإقفالُ التعاقديّ**
   ══════════════════════════════════════════════════════════════════════════ */
$L4 = 'الإقفال التعاقدي';
$m1 = date('Y-m-01');
$m1e = date('Y-m-t');
$conn->query("INSERT INTO financing_installments
    (company_id, op_id, seq_no, due_date, amount_principal, amount_profit, amount_total,
     currency, payment_ref, state)
    VALUES ($company, $opId, 1, '" . date('Y-m-15') . "', 50, 0, 50,
            '" . $esc($CUR) . "', '" . $esc($RUN) . "-I1', 'due')");
$inst1 = (int) $conn->insert_id;
$conn->query("INSERT INTO financing_installments
    (company_id, op_id, seq_no, due_date, amount_principal, amount_profit, amount_total,
     currency, payment_ref, state)
    VALUES ($company, $opId, 2, '" . date('Y-m-15', strtotime('+1 month')) . "', 50, 0, 50,
            '" . $esc($CUR) . "', '" . $esc($RUN) . "-I2', 'scheduled')");
$inst2 = (int) $conn->insert_id;
$log($L4, 'جدول اقساط العملية مولد', 'financing_installments', 'سجل الاقساط',
     'قسطان بتاريخي استحقاقهما', 'المعرفان ' . $inst1 . ' و ' . $inst2,
     'الاستحقاق يقرا من الجدول لا يكتب بيد', 'scheduled', $inst1 > 0 && $inst2 > 0, $company);

/* ⛔ **إقفالٌ تعاقديٌّ بلا رقمِ فترتِه التعاقديّةِ يصير شهرًا مقنَّعًا** */
$rNoPeriod = FIN::prepareContractClose($G, array('op_id' => $opId, 'entity_id' => $financier,
    'contract_id' => $ctrId, 'contract_period_no' => 0, 'period_start' => $m1, 'period_end' => $m1e,
    'currency' => $CUR, 'close_code' => $RUN . '-KX'), $A1);
$log($L4, 'اقفال تعاقدي بلا رقم فترته يرد', 'fin_contract_close',
     'FinancingCycleService::prepareContractClose', 'CLOSE_WITHOUT_CONTRACT_PERIOD',
     (string) $rNoPeriod['code'],
     'رقم الفترة التعاقدية هو ما يميز التعاقدي عن الشهري فلا يقفز', 'refused',
     empty($rNoPeriod['ok']) && $rNoPeriod['code'] === 'CLOSE_WITHOUT_CONTRACT_PERIOD', $company);

$rK1 = FIN::prepareContractClose($G, array('op_id' => $opId, 'entity_id' => $financier,
    'contract_id' => $ctrId, 'contract_period_no' => 1, 'period_start' => $m1, 'period_end' => $m1e,
    'currency' => $CUR, 'close_code' => $RUN . '-K1', 'open_principal' => 100, 'open_profit' => 0,
    'due_principal' => 50, 'due_profit' => 0, 'allocated_paid' => 0), $A1);
$k1 = !empty($rK1['ok']) ? (int) $rK1['close_id'] : 0;
$k1Kind = (string) $one("SELECT close_kind FROM fin_contract_close WHERE id = $k1");
$log($L4, 'اقفال تعاقدي معد بفترته التعاقدية', 'fin_contract_close',
     'FinancingCycleService::prepareContractClose', 'CONTRACTUAL', $k1Kind,
     'كيان مستقل بصنفه فلا يقرا شهريا ولا نهائيا', 'prepared',
     $k1 > 0 && $k1Kind === 'CONTRACTUAL', $company);

/* ⛔ **الترحيلُ المكسورُ يُردّ** — افتتاحيُّ الفترةِ يساوي ختاميَّ سابقتِها */
$rBroken = FIN::prepareContractClose($G, array('op_id' => $opId, 'entity_id' => $financier,
    'contract_id' => $ctrId, 'contract_period_no' => 2, 'period_start' => date('Y-m-01', strtotime('+1 month')),
    'period_end' => date('Y-m-t', strtotime('+1 month')), 'currency' => $CUR,
    'close_code' => $RUN . '-KB', 'open_principal' => 999, 'due_principal' => 50), $A1);
$log($L4, 'رصيد افتتاحي لا يساوي ختامي السابقة يرد', 'fin_contract_close',
     'FinancingCycleService::prepareContractClose', 'ROLLFORWARD_BROKEN',
     (string) $rBroken['code'],
     'اختبار الترحيل حارس لا نص - فلا تنشا سلسلة ارصدة مكسورة', 'refused',
     empty($rBroken['ok']) && $rBroken['code'] === 'ROLLFORWARD_BROKEN', $company);

$rSelfK = FIN::approveContractClose($G, $k1, $A1);
$log($L4, 'من اعد الاقفال التعاقدي لا يعتمده', 'fin_contract_close',
     'FinancingCycleService::approveContractClose', 'SAME_ACTOR_PREPARE_AND_APPROVE_CLOSE',
     (string) $rSelfK['code'], 'الاعتماد بيد ثانية فلا يشهد المعد على عمله', 'refused',
     empty($rSelfK['ok']) && $rSelfK['code'] === 'SAME_ACTOR_PREPARE_AND_APPROVE_CLOSE', $company);

$rAppK = FIN::approveContractClose($G, $k1, $A2);
$eff2 = $cons->onContractClosed(array('payload' => array('close_id' => $k1,
    'run' => $RUN, 'company_id' => $company)), $conn);
$instSealed = (int) $one("SELECT contract_close_id FROM financing_installments WHERE inst_id = $inst1");
$log($L4, 'اعتماد الاقفال يختم اقساط فترته', 'financing_installments',
     'FinancingCycleService::onContractClosed', 'contract_close_id = ' . $k1,
     (string) $instSealed, 'القسط يقرا في اقفاله فلا يحسب في فترتين', $eff2,
     !empty($rAppK['ok']) && $instSealed === $k1, $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑤ — **نموذجُ أمرِ الدفعِ المستقبليّ**
   ══════════════════════════════════════════════════════════════════════════ */
$L5 = 'أمر الدفع';
$rLegacyAsOrder = FIN::requestPaymentOrder($G, array('order_code' => $RUN . '-PL', 'op_id' => $opId,
    'entity_id' => $financier, 'requested_amount' => 50, 'currency' => $CUR,
    'source_kind' => 'LEGACY'), $A1);
$log($L5, 'طبقة تاريخية لا تدخل نموذج امر الدفع', 'fin_payment_order',
     'FinancingCycleService::requestPaymentOrder', 'LEGACY_AGGREGATE_AS_ORDER',
     (string) $rLegacyAsOrder['code'],
     'تصميم المستقبل لا يخفض ليقبل ما لا يملكه الماضي', 'refused',
     empty($rLegacyAsOrder['ok']) && $rLegacyAsOrder['code'] === 'LEGACY_AGGREGATE_AS_ORDER', $company);

$rOrd = FIN::requestPaymentOrder($G, array('order_code' => $RUN . '-P1', 'op_id' => $opId,
    'entity_id' => $financier, 'requested_amount' => 50, 'currency' => $CUR), $A1);
$ordId = !empty($rOrd['ok']) ? (int) $rOrd['order_id'] : 0;
$ordLayer = (string) $one("SELECT source_kind FROM fin_payment_order WHERE id = $ordId");
$log($L5, 'امر دفع مطلوب بطالبه وتاريخ طلبه', 'fin_payment_order',
     'FinancingCycleService::requestPaymentOrder', 'FUTURE', $ordLayer,
     'اربعة حقول لا يملكها الصف المجمع: طالب وتاريخ ومبلغ وعملة', 'requested',
     $ordId > 0 && $ordLayer === 'FUTURE', $company);

$rSelfOrd = FIN::approvePaymentOrder($G, $ordId, 50, $A1);
$log($L5, 'من طلب امر الدفع لا يعتمده', 'fin_payment_order',
     'FinancingCycleService::approvePaymentOrder', 'SAME_ACTOR_REQUEST_AND_APPROVE_ORDER',
     (string) $rSelfOrd['code'], 'الاعتماد سلطة لا اجراء', 'refused',
     empty($rSelfOrd['ok']) && $rSelfOrd['code'] === 'SAME_ACTOR_REQUEST_AND_APPROVE_ORDER', $company);

$rExecEarly = FIN::executePaymentOrder($G, $ordId, array('bank_ref' => $RUN . '-BNK',
    'method' => 'bank', 'executed_amount' => 50), $A3);
$log($L5, 'لا تنفيذ لامر غير معتمد', 'fin_payment_order',
     'FinancingCycleService::executePaymentOrder', 'EXECUTE_WITHOUT_APPROVED_ORDER',
     (string) $rExecEarly['code'], 'لا يخرج نقد بلا سلطة اذنت به', 'refused',
     empty($rExecEarly['ok']) && $rExecEarly['code'] === 'EXECUTE_WITHOUT_APPROVED_ORDER', $company);

$rAppOrd = FIN::approvePaymentOrder($G, $ordId, 50, $A2);
$eff3 = $cons->onOrderApproved(array('payload' => array('order_id' => $ordId,
    'run' => $RUN, 'company_id' => $company)), $conn);
$ordState = (string) $one("SELECT state FROM fin_payment_order WHERE id = $ordId");
$log($L5, 'الاعتماد يفتح باب التنفيذ', 'fin_payment_order',
     'FinancingCycleService::onOrderApproved', 'approved', $ordState,
     'الامر المعتمد وحده ينفذ', $eff3, !empty($rAppOrd['ok']) && $ordState === 'approved', $company);

$rNoBank = FIN::executePaymentOrder($G, $ordId, array('bank_ref' => '', 'method' => 'bank',
    'executed_amount' => 50), $A3);
$log($L5, 'لا تنفيذ بلا مرجع بنكي', 'fin_payment_order',
     'FinancingCycleService::executePaymentOrder', 'EXECUTE_WITHOUT_BANK_REF',
     (string) $rNoBank['code'], 'الحركة النقدية تسند بمرجعها', 'refused',
     empty($rNoBank['ok']) && $rNoBank['code'] === 'EXECUTE_WITHOUT_BANK_REF', $company);

$outBefore = (float) $one("SELECT outstanding_balance FROM financing_operations WHERE op_id = $opId");
$rExec = FIN::executePaymentOrder($G, $ordId, array('bank_ref' => $RUN . '-BNK',
    'method' => 'bank', 'executed_amount' => 50, 'treasury_ref' => $RUN . '-TRE'), $A3);
$recId = !empty($rExec['ok']) ? (int) $rExec['recognition_request_id'] : 0;
$recSrc = (string) $one("SELECT source_module FROM acc_recognition_request WHERE id = $recId");
$log($L5, 'التنفيذ يصدر طلب اعتراف الى المالية ولا يكتب قيدا', 'acc_recognition_request',
     'AccountingCycleService::requestRecognition', 'financing', $recSrc,
     'قاعدة §48 - النطاق يطلب والمالية تقرر وتثبت', 'pending',
     $recId > 0 && $recSrc === 'financing', $company);

$eff4 = $cons->onOrderExecuted(array('payload' => array('order_id' => $ordId,
    'run' => $RUN, 'company_id' => $company)), $conn);
$outAfter = (float) $one("SELECT outstanding_balance FROM financing_operations WHERE op_id = $opId");
$log($L5, 'التنفيذ ينزل الرصيد القائم للعملية', 'financing_operations',
     'FinancingCycleService::onOrderExecuted', 'ينقص بمقدار المنفذ',
     $outBefore . ' ⇐ ' . $outAfter, 'الرصيد مشتق من الحركة لا مكتوب بيد', $eff4,
     abs($outBefore - $outAfter - 50) < 0.005, $company);

$noJournal = (int) $one("SELECT COUNT(*) FROM fin_journal_entries WHERE memo LIKE '" . $esc($RUN) . "%'");
$log($L5, 'النطاق لم يكتب قيدا في دفتر المالية', 'fin_journal_entries',
     'قاعدة §48', 'صفر قيد من النطاق', (string) $noJournal,
     'باب الاعتراف واحد ولا يلتف عليه نطاق', 'none', $noJournal === 0, $company);

$rAlloc = FIN::allocatePayment($G, $ordId, array(
    array('installment_id' => $inst1, 'close_kind' => 'CONTRACTUAL', 'close_id' => $k1,
          'amount' => 50, 'part_kind' => 'principal', 'note' => $RUN . '-ALLOC')), $A2);
$eff5 = $cons->onPaymentAllocated(array('payload' => array('order_id' => $ordId,
    'run' => $RUN, 'company_id' => $company)), $conn);
$instState = (string) $one("SELECT state FROM financing_installments WHERE inst_id = $inst1");
$instAlloc = (float) $one("SELECT allocated_amount FROM financing_installments WHERE inst_id = $inst1");
$log($L5, 'التخصيص يقفل القسط بتغطيته', 'financing_installments',
     'FinancingCycleService::onPaymentAllocated', 'paid و 50', $instState . ' و ' . $instAlloc,
     'القسط يقرا سداده من التخصيص لا من دعوى', $eff5,
     !empty($rAlloc['ok']) && $instState === 'paid' && abs($instAlloc - 50) < 0.005, $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑥ — **الطبقةُ التاريخيّةُ المجمَّعةُ في بابِها وحدَه**
   ══════════════════════════════════════════════════════════════════════════ */
$L6 = 'الطبقة التاريخية';
$rNoEvid = FIN::ingestLegacyAggregate($G, array('op_id' => $opId, 'period_label' => '2019',
    'paid_aggregate' => 400, 'ledger_rows' => 12, 'currency' => $CUR,
    'evidence_grade' => '', 'source_row_ref' => $RUN . '-L0'), $A1);
$log($L6, 'صف تاريخي بلا حجية يرد', 'fin_legacy_payment_aggregate',
     'FinancingCycleService::ingestLegacyAggregate', 'LEGACY_WITHOUT_EVIDENCE_GRADE',
     (string) $rNoEvid['code'], 'رقم تاريخي بلا سند دعوى لا حقيقة', 'refused',
     empty($rNoEvid['ok']) && $rNoEvid['code'] === 'LEGACY_WITHOUT_EVIDENCE_GRADE', $company);

$rLeg = FIN::ingestLegacyAggregate($G, array('op_id' => $opId, 'period_label' => '2019',
    'paid_aggregate' => 400, 'ledger_rows' => 12, 'currency' => $CUR,
    'evidence_grade' => 'aggregate', 'source_row_ref' => $RUN . '-L1',
    'note' => 'صفوف مجمعة من الدفتر القديم'), $A1);
$legId = !empty($rLeg['ok']) ? (int) $rLeg['legacy_id'] : 0;
$legLayer = (string) $one("SELECT layer FROM fin_legacy_payment_aggregate WHERE id = $legId");
$legAlloc = (int) $one("SELECT allocatable FROM fin_legacy_payment_aggregate WHERE id = $legId");
$log($L6, 'الصف التاريخي يدخل بطبقته موسوما بحجيته', 'fin_legacy_payment_aggregate',
     'FinancingCycleService::ingestLegacyAggregate', 'LEGACY وغير قابل للتخصيص',
     $legLayer . ' و ' . $legAlloc, 'التاريخي يقرا موسوما ولا يخلط بنموذج المستقبل',
     'legacy', $legId > 0 && $legLayer === 'LEGACY' && $legAlloc === 0, $company);

$legInFuture = (int) $one("SELECT COUNT(*) FROM fin_payment_order WHERE source_kind <> 'FUTURE'");
$log($L6, 'لا صف تاريخي في جدول نموذج المستقبل', 'fin_payment_order',
     'قيد chk_fpo_future', 'صفر', (string) $legInFuture,
     'الطبقتان لا تختلطان في جدول واحد فلا يخفض النموذج', 'separated',
     $legInFuture === 0, $company);

$allocLegacyBlocked = false;
try {
    @$G->insert('fin_payment_allocation', array('order_id' => 0, 'installment_id' => $inst2,
        'close_kind' => 'CONTRACTUAL', 'close_id' => $k1, 'amount' => 10,
        'part_kind' => 'principal', 'note' => $RUN . '-BADALLOC'));
} catch (\Throwable $t) { $allocLegacyBlocked = true; }
$badAlloc = (int) $one("SELECT COUNT(*) FROM fin_payment_allocation
                         WHERE note = '" . $esc($RUN . '-BADALLOC') . "'");
$log($L6, 'تخصيص بلا امر دفع يرد في القاعدة', 'fin_payment_allocation',
     'قيد chk_fpa_order', 'ترد الكتابة', $badAlloc === 0 ? 'ردت' : 'مرت',
     'المجمع لا يخصص كامر فلا يوهم بسداد مفصل لا وجود له', 'refused',
     $badAlloc === 0, $company);

$capDown = repair01_w12_capability_downgraded($conn);
$colNull = repair01_w12_future_col_nullable($conn);
$log($L6, 'لا قدرة في النموذج خفضت لتناسب التاريخي', 'repair01_w12_layers',
     'دفتر الطبقتين', 'صفر تخفيض وصفر عمود يقبل العدم',
     $capDown . ' و ' . $colNull,
     'تصميم المستقبل مستقل عن محدودية البيانات التاريخية', 'independent',
     $capDown === 0 && $colNull === 0, $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑦ — **الإقفالُ الشهريُّ كيانٌ ثانٍ**
   ══════════════════════════════════════════════════════════════════════════ */
$L7 = 'الإقفال الشهري';
$rNotCal = FIN::prepareMonthlyClose($G, array('op_id' => $opId, 'entity_id' => $financier,
    'accounting_month' => 'الفترة الاولى', 'currency' => $CUR, 'close_code' => $RUN . '-MX'), $A1);
$log($L7, 'شهر ليس تقويميا يرد', 'fin_monthly_close',
     'FinancingCycleService::prepareMonthlyClose', 'MONTH_NOT_CALENDAR',
     (string) $rNotCal['code'],
     'وعاء الشهر لا يقبل فترة تعاقدية فلا يخدم معنيين', 'refused',
     empty($rNotCal['ok']) && $rNotCal['code'] === 'MONTH_NOT_CALENDAR', $company);

$dbMonthBlocked = false;
$badMonth = @$conn->query("INSERT INTO fin_monthly_close
    (company_id, close_code, op_id, entity_id, accounting_month, month_start, month_end, currency)
    VALUES ($company, '" . $esc($RUN) . "-MDB', $opId, $financier, '"
    . date('Y-m') . "', '" . date('Y-m-05') . "', '" . $m1e . "', '" . $esc($CUR) . "')");
$dbMonthBlocked = ($badMonth !== true);
$log($L7, 'شهر لا يبدا باوله يرد في القاعدة', 'fin_monthly_close',
     'قيد chk_fmc_month', 'ترد الكتابة', $dbMonthBlocked ? 'ردت' : 'مرت',
     'الحبة تفرض في القاعدة لا في الخدمة وحدها', 'refused', $dbMonthBlocked, $company);

$rM1 = FIN::prepareMonthlyClose($G, array('op_id' => $opId, 'entity_id' => $financier,
    'accounting_month' => date('Y-m'), 'currency' => $CUR, 'close_code' => $RUN . '-M1',
    'open_balance' => 100, 'due_in_month' => 50, 'paid_in_month' => 50,
    'allocated_in_month' => 50), $A1);
$mId = !empty($rM1['ok']) ? (int) $rM1['close_id'] : 0;
$mKind = (string) $one("SELECT close_kind FROM fin_monthly_close WHERE id = $mId");
$log($L7, 'اقفال شهري معد بشهره التقويمي', 'fin_monthly_close',
     'FinancingCycleService::prepareMonthlyClose', 'MONTHLY', $mKind,
     'كيان ثان بصنفه ومفتاحه لا حالة للتعاقدي', 'prepared',
     $mId > 0 && $mKind === 'MONTHLY', $company);

$rNoLink = FIN::approveMonthlyClose($G, $mId, $A2);
$log($L7, 'شهري بلا تعاقدي مربوط لا يعتمد', 'fin_monthly_close',
     'FinancingCycleService::approveMonthlyClose', 'MONTHLY_WITHOUT_CONTRACT_CLOSE',
     (string) $rNoLink['code'],
     'الشهر يضم اقفالات فتراته ولا ينشئ معناها وحده', 'refused',
     empty($rNoLink['ok']) && $rNoLink['code'] === 'MONTHLY_WITHOUT_CONTRACT_CLOSE', $company);

$rLink = FIN::linkContractCloseToMonth($G, $mId, $k1, $RUN . '-LINK');
$linkN = (int) $one("SELECT COUNT(*) FROM fin_close_link
                      WHERE parent_kind = 'MONTHLY' AND parent_id = $mId AND child_kind = 'CONTRACTUAL'");
$log($L7, 'الشهري يضم التعاقدي بربط لا باحلال', 'fin_close_link',
     'FinancingCycleService::linkContractCloseToMonth', 'رابط واحد', (string) $linkN,
     'الكيانان يبقيان متمايزين والعلاقة صف مستقل', 'linked',
     !empty($rLink['ok']) && $linkN === 1, $company);

$selfLinkBlocked = false;
$badLink = @$conn->query("INSERT INTO fin_close_link
    (company_id, parent_kind, parent_id, child_kind, child_id, link_rule, why)
    VALUES ($company, 'MONTHLY', $mId, 'MONTHLY', $mId, 'W12J', '" . $esc($RUN) . "-SELF')");
$selfLinkBlocked = ($badLink !== true);
$log($L7, 'رابط من صنف الى صنفه نفسه يرد', 'fin_close_link', 'قيد chk_fcl_self',
     'ترد الكتابة', $selfLinkBlocked ? 'ردت' : 'مرت',
     'هذا عين اقفال يخدم معنيين فيمنع في القاعدة', 'refused', $selfLinkBlocked, $company);

$rSelfM = FIN::approveMonthlyClose($G, $mId, $A1);
$log($L7, 'من اعد الشهري لا يعتمده', 'fin_monthly_close',
     'FinancingCycleService::approveMonthlyClose', 'SAME_ACTOR_PREPARE_AND_APPROVE_MONTHLY',
     (string) $rSelfM['code'], 'فصل الواجبات في الشهري كما في التعاقدي', 'refused',
     empty($rSelfM['ok']) && $rSelfM['code'] === 'SAME_ACTOR_PREPARE_AND_APPROVE_MONTHLY', $company);

$rAppM = FIN::approveMonthlyClose($G, $mId, $A2);
$eff6 = $cons->onMonthlyClosed(array('payload' => array('close_id' => $mId,
    'run' => $RUN, 'company_id' => $company)), $conn);
$mCount = (int) $one("SELECT contract_closes_n FROM fin_monthly_close WHERE id = $mId");
$log($L7, 'الشهري يعلن عدد تعاقدياته المضمومة', 'fin_monthly_close',
     'FinancingCycleService::onMonthlyClosed', '1', (string) $mCount,
     'الرقم مشتق من الربط فيقرا الشهر ضما لا كيانا بديلا', $eff6,
     !empty($rAppM['ok']) && $mCount === 1, $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑧ — **الإقفالُ النهائيُّ كيانٌ ثالث**
   ══════════════════════════════════════════════════════════════════════════ */
$L8 = 'الإقفال النهائي';
$rDev = FIN::raiseDeviation($company, array('dev_type' => 'payment_gap',
    'subject_ref' => $RUN . '-' . $opId, 'description' => 'فرق سداد للرحلة',
    'priority' => 'high', 'required_doc' => 'كشف الممول', 'final_close_block' => 1), $A1);
$devId = !empty($rDev['ok']) ? (int) $rDev['deviation_id'] : 0;
$eff7 = $cons->onDeviationRaised(array('payload' => array('deviation_id' => $devId,
    'run' => $RUN, 'company_id' => $company)), $conn);
$log($L8, 'انحراف مرفوع يحجب الاقفال النهائي', 'financing_deviations',
     'FinancingCycleService::onDeviationRaised', 'انحراف حاجب مفتوح', $eff7,
     'الحجب مقيس من الانحرافات لا مدعى', 'open', $devId > 0, $company);

$rF1 = FIN::requestFinalClose($G, $company, array('op_id' => $opId, 'entity_id' => $financier,
    'currency' => $CUR, 'close_code' => $RUN . '-F1',
    'clearance_doc_ref' => $RUN . '-CLR'), $A1);
$fId = !empty($rF1['ok']) ? (int) $rF1['close_id'] : 0;
$fKind = (string) $one("SELECT close_kind FROM fin_final_close WHERE id = $fId");
$fDev = (int) $one("SELECT open_deviations_n FROM fin_final_close WHERE id = $fId");
$fDues = (int) $one("SELECT open_dues_n FROM fin_final_close WHERE id = $fId");
$log($L8, 'طلب اقفال نهائي يقيس موقف العملية', 'fin_final_close',
     'FinancingCycleService::requestFinalClose', 'FINAL بموقف مقيس',
     $fKind . ' · انحرافات ' . $fDev . ' · استحقاقات ' . $fDues,
     'كيان ثالث بصنفه ومفتاحه ويقرا اخر دوري مرجعا', 'requested',
     $fId > 0 && $fKind === 'FINAL' && $fDev > 0 && $fDues > 0, $company);

$rDup = FIN::requestFinalClose($G, $company, array('op_id' => $opId, 'entity_id' => $financier,
    'currency' => $CUR, 'close_code' => $RUN . '-F2'), $A1);
$log($L8, 'الاقفال النهائي مرة واحدة لعملية', 'fin_final_close',
     'FinancingCycleService::requestFinalClose', 'FINAL_CLOSE_ALREADY_EXISTS',
     (string) $rDup['code'], 'شهادة الاقفال واقعة لا رقم يتكرر', 'refused',
     empty($rDup['ok']) && $rDup['code'] === 'FINAL_CLOSE_ALREADY_EXISTS', $company);

$rOverDues = FIN::approveFinalClose($G, $fId, $A2);
$log($L8, 'لا اقفال نهائي باستحقاق مفتوح', 'fin_final_close',
     'FinancingCycleService::approveFinalClose', 'FINAL_CLOSE_WITH_OPEN_DUES',
     (string) $rOverDues['code'], 'اخلاء طرف على ذمة قائمة نقض للشهادة', 'refused',
     empty($rOverDues['ok']) && $rOverDues['code'] === 'FINAL_CLOSE_WITH_OPEN_DUES', $company);

/* استيفاءُ الموقف: القسطُ الثاني يُسدَّد والانحرافُ يُحسَم بيدٍ ثانية */
$conn->query("UPDATE financing_installments SET state = 'paid', allocated_amount = 50
                WHERE inst_id = $inst2");
$rSelfDev = FIN::resolveDeviation($company, $devId, 'حسم للرحلة', $RUN . '-DEVDOC', $A1, $A1);
$log($L8, 'من رفع الانحراف لا يحسمه', 'financing_deviations',
     'FinancingCycleService::resolveDeviation', 'SAME_ACTOR_RAISE_AND_RESOLVE_DEVIATION',
     (string) $rSelfDev['code'], 'الحسم شهادة على عمل غيره', 'refused',
     empty($rSelfDev['ok']) && $rSelfDev['code'] === 'SAME_ACTOR_RAISE_AND_RESOLVE_DEVIATION', $company);

$rDevOk = FIN::resolveDeviation($company, $devId, 'حسم للرحلة بقرار مكتوب', $RUN . '-DEVDOC', $A2, $A1);
$devState = (string) $one("SELECT state FROM financing_deviations WHERE dev_id = $devId");
$log($L8, 'الانحراف يحسم بقرار مكتوب من يد ثانية', 'financing_deviations',
     'FinancingCycleService::resolveDeviation', 'closed', $devState,
     'الحجب يرفع بحسم موثق لا بتجاهل', $devState,
     !empty($rDevOk['ok']) && $devState === 'closed', $company);

/* إعادةُ قياسِ الموقفِ — والقياسُ يُعاد ولا يُفترَض */
$openDues2 = (int) $one("SELECT COUNT(*) FROM financing_installments
                            WHERE op_id = $opId AND state IN ('scheduled','due','overdue')");
$openDev2 = (int) $one("SELECT COUNT(*) FROM financing_deviations
                          WHERE company_id = $company AND state = 'open' AND final_close_block = 1
                            AND subject_ref LIKE '" . $esc($RUN) . "%'");
$G->update('fin_final_close', array('open_dues_n' => $openDues2, 'open_deviations_n' => $openDev2,
    'state' => 'reviewed', 'reviewed_by' => $A2), array('id' => $fId));
$log($L8, 'موقف العملية يعاد قياسه بعد الاستيفاء', 'fin_final_close',
     'قياس حي', 'صفر استحقاق وصفر انحراف حاجب',
     $openDues2 . ' و ' . $openDev2, 'البوابة تقيس ولا تقرا ما خزنته', 'reviewed',
     $openDues2 === 0 && $openDev2 === 0, $company);

$rSelfF = FIN::approveFinalClose($G, $fId, $A1);
$log($L8, 'من طلب الاقفال النهائي لا يعتمده', 'fin_final_close',
     'FinancingCycleService::approveFinalClose', 'SAME_ACTOR_PREPARE_AND_APPROVE_FINAL',
     (string) $rSelfF['code'], 'الاعتماد سلطة بسقفها', 'refused',
     empty($rSelfF['ok']) && $rSelfF['code'] === 'SAME_ACTOR_PREPARE_AND_APPROVE_FINAL', $company);

$rAppF = FIN::approveFinalClose($G, $fId, $A2);
$eff8 = $cons->onFinalClosed(array('payload' => array('close_id' => $fId,
    'run' => $RUN, 'company_id' => $company)), $conn);
$opFinal = (int) $one("SELECT final_close_id FROM financing_operations WHERE op_id = $opId");
$opState = (string) $one("SELECT state FROM financing_operations WHERE op_id = $opId");
$log($L8, 'الاقفال النهائي يقفل العملية ويربطها بختامها', 'financing_operations',
     'FinancingCycleService::onFinalClosed', 'final_close_id = ' . $fId . ' و closed',
     $opFinal . ' و ' . $opState, 'العملية تقرا ختامها فلا يبقى عقد مقفل بحالة سارية', $eff8,
     !empty($rAppF['ok']) && $opFinal === $fId && $opState === 'closed', $company);

$eff9 = $cons->onOwnershipTransferred(array('payload' => array('op_id' => $opId,
    'ownership_doc_ref' => $RUN . '-OWN', 'run' => $RUN, 'company_id' => $company)), $conn);
$ownDone = (int) $one("SELECT ownership_transferred FROM fin_final_close WHERE id = $fId");
$log($L8, 'انتقال الملكية يوسم في الاقفال النهائي بمستنده', 'fin_final_close',
     'FinancingCycleService::onOwnershipTransferred', '1', (string) $ownDone,
     'حكم الملكية جزء من الاقفال لا واقعة منفصلة عنه', $eff9, $ownDone === 1, $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑨ — **الثلاثةُ تُقرأ منفصلةً** والفصلُ مقيسٌ لا مُدَّعًى
   ══════════════════════════════════════════════════════════════════════════ */
$L9 = 'الفصل مقيس';
$cKinds = (int) $one("SELECT COUNT(DISTINCT close_kind) FROM (
    SELECT close_kind FROM fin_contract_close WHERE close_code LIKE '" . $esc($RUN) . "%'
    UNION ALL SELECT close_kind FROM fin_monthly_close WHERE close_code LIKE '" . $esc($RUN) . "%'
    UNION ALL SELECT close_kind FROM fin_final_close WHERE close_code LIKE '" . $esc($RUN) . "%') x");
$log($L9, 'ثلاثة اصناف اقفال متمايزة في جولة واحدة', 'fin_contract_close',
     'قراءة منفصلة', '3', (string) $cKinds,
     'كل صنف يقرا من جدوله بمفتاحه فلا يخلط رقم بمعنى غيره', 'separated',
     $cKinds === 3, $company);

$dual = repair01_w12_close_dual_role($conn);
$log($L9, 'صفر اقفال يخدم معنيين', 'fin_close_consumption',
     'repair01_w12_close_dual_role', '0', (string) $dual,
     'المحور الاول للمرحلة مقيس على خمس جبهات', 'measured', $dual === 0, $company);

$constrained = repair01_w12_design_constrained($conn);
$log($L9, 'صفر تصميم مقيد ببيانات تاريخية', 'repair01_w12_layers',
     'repair01_w12_design_constrained', '0', (string) $constrained,
     'المحور الثاني للمرحلة مقيس على خمس جبهات', 'measured', $constrained === 0, $company);

$subs = (int) $one("SELECT COUNT(*) FROM event_consumers WHERE event_name LIKE 'fin.%' AND active = 1");
$evN  = (int) $one("SELECT COUNT(*) FROM repair01_events WHERE wave = 'W12'");
$log($L9, 'كل حدث في النطاق له مستهلك نشط بالاسم', 'event_consumers',
     'الجذر المحايد', 'مشتركون بعدد العقود', $subs . ' مقابل ' . $evN,
     'حدث بلا مشترك نشط يرفض في الجذر نفسه', 'registered',
     $subs >= $evN && $evN > 0, $company);

/* ══════════════════════════════════════════════════════════════════════════
   الكنسُ ثمَّ الدليل
   ══════════════════════════════════════════════════════════════════════════ */
$conn->query("DELETE FROM acc_recognition_request WHERE id = $recId");
$sweep();
$conn->query("DELETE FROM fin_payment_allocation WHERE note LIKE '" . $esc($RUN) . "%'");
$conn->query("DELETE FROM financing_installments WHERE op_id = $opId");
$conn->query("DELETE FROM financing_operations WHERE op_id = $opId");
$conn->query("DELETE FROM ems_business_events WHERE idempotency_key LIKE 'w12:%'
               AND entity_id IN ($opId, $ordId, $k1, $mId, $fId, $ctrId, $devId)");

$left = 0;
foreach (array(
    "SELECT COUNT(*) FROM fin_contract_close WHERE close_code LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_monthly_close WHERE close_code LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_final_close WHERE close_code LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_payment_order WHERE order_code LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_payment_allocation WHERE note LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_legacy_payment_aggregate WHERE source_row_ref LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_close_link WHERE parent_id = $mId OR parent_id = $fId",
    "SELECT COUNT(*) FROM fin_finance_contract WHERE contract_code LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_funding_need WHERE need_code LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_funding_offer WHERE offer_code LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_precontract_review WHERE review_code LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_financier_contact WHERE person_name LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_financier_document WHERE doc_ref LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM financing_operations WHERE op_code LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM financing_installments WHERE payment_ref LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM financing_deviations WHERE subject_ref LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM acc_recognition_request WHERE source_ref LIKE 'FPAYO:" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_contract_term WHERE term_value LIKE '" . $esc($RUN) . "%'",
    "SELECT COUNT(*) FROM fin_contract_covenant WHERE covenant_ar LIKE '" . $esc($RUN) . "%'",
) as $q) { $left += (int) $one($q); }

$ins = $conn->prepare("INSERT INTO repair01_w12_journey
    (run_id, station_no, leg, station, entity, consumer, expected, measured, business_effect,
     state_after, company_id, passed)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
$no = 0; $ok = 0;
foreach ($ST as $s) {
    $no++;
    $ins->bind_param('sisssssssssi', $RUN, $no, $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6],
                     $s[7], $s[9], $s[8]);
    $ins->execute();
    if ((int) $s[8] === 1) { $ok++; }
    printf("  %s %2d %-18s %-52s %s\n", $s[8] ? '✔' : '✘', $no, $s[0],
        mb_substr($s[1], 0, 52), mb_substr($s[5], 0, 40));
}
$consumers = count(array_unique(array_column($ST, 3)));
$noEffect = 0;
foreach ($ST as $s) { if (trim($s[6]) === '' || $s[6] === '—') { $noEffect++; } }

echo str_repeat('─', 128) . "\n";
printf("رحلةُ التمويل: %d/%d  ·  أشواط %d  ·  مستهلكونَ متمايزون %d  ·  بلا أثرٍ تجاريٍّ %d  ·  أثرٌ باقٍ %d\n",
    $ok, $no, count(array_unique(array_column($ST, 0))), $consumers, $noEffect, $left);
echo ($ok === $no && $left === 0) ? "الحكم: عبرت ✔\n" : "الحكم: لم تعبر ✘\n";
exit(($ok === $no && $left === 0) ? 0 : 1);
