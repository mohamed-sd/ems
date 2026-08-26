<?php
/**
 * tools/repair01_w11_journey.php — رحلةُ الإقفال (‏W11 §٦-أ · §23)
 * ═══════════════════════════════════════════════════════════════════════════
 * **طلبُ اعترافٍ من نطاقٍ مصدريّ ← الماليّةُ تقرّر وتثبّت القيد ← ترحيلٌ إلى
 *   الأستاذ ← تسويات ← مطابقةٌ بنكيّة ← ميزانُ مراجعة ← قائمةُ فحصِ الإقفال ←
 *   إقفالُ الفترة ← قوائمُ مالية — لكيانٍ قانونيٍّ واحدٍ من طرفٍ إلى طرف.**
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس رقمٌ يعنيه — طلبٌ يقع في فترتِه · واقعةُ اعترافٍ تنشأ عند
 *   القبولِ وحدَه · تعرُّضٌ ائتمانيٌّ يتحرّك بالقيد · بندُ قائمةِ إقفالٍ يُغلَق
 *   بالتسويةِ وبالمطابقةِ وبالميزان · فترةٌ تمنع الترحيلَ بعد إقفالها · ذمّةٌ
 *   تُقفَل بتغطيتِها · نقدٌ يخرج من وعائِه · فرقُ جردٍ يصير حركةَ تسوية.
 *
 * ◆ **والمحطّاتُ السالبةُ محطّاتٌ**: «الماليّةُ لا تصدر لنفسها طلبَ اعتراف»
 *   و«من طلب لا يقرّر» و«لا قيدَ على طلبٍ لم يُقبَل» و«قيدٌ لا يتوازن» و«من
 *   قرّر لا يرحّل» و«من أعدَّ لا يعتمد» و«لا إقفالَ وفيه فرقٌ مفتوح» و«لا دفعَ
 *   لمستفيدٍ غيرِ محقَّق» و«من أعدَّ لا ينفّذ» و«جردٌ بلا لجنة» و«من عدَّ لا
 *   يعتمد» و«لا إقفالَ بلا ميزان» و«لا إقفالَ وفيه بندٌ حاجب» و«لا قيدَ على
 *   فترةٍ مقفلة» و«لا قوائمَ قبل الإقفال» و«رقمٌ يخلط كيانَين بلا وسم»
 *   — تُقاس **بالاستدعاءِ الفعليِّ ورمزِ الرفض**.
 *
 * ⚠ **والنظافةُ كنسٌ بالعائلةِ لا إرجاعٌ بمعاملة**: خدماتُ الدورةِ تستدعي
 *   `runInTransaction`، و**MySQL يُثبّت المعاملةَ الخارجيّةَ ضمنًا** عند بدءِ
 *   داخليّة — فيثبت كلُّ ما كُتب قبلَها ويُرجع `ROLLBACK` لا شيء (‏درسُ W09).
 *   فكلُّ صفٍّ تكتبه الرحلةُ يحمل وسمَ جولتِه، والكنسُ يمسح **بالوسمِ لا
 *   بالمعاملة** — ويُشغَّل مرّتَين: قبلَ البدءِ وبعدَ النهاية.
 *
 * التشغيل: php tools/repair01_w11_journey.php
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
require_once $ROOT . '/tools/lib/repair01_w11_scan.php';
require_once $ROOT . '/app/Services/Finance/AccountingCycleService.php';
require_once $ROOT . '/app/Services/Treasury/TreasuryCycleService.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
use App\Services\Finance\AccountingCycleService as ACC;
use App\Services\Treasury\TreasuryCycleService as TRE;

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w11_one($conn, $sql); };

/* مُعرِّفُ الجولةِ بدقّةِ الميكروثانية — جولتانِ في الثانيةِ نفسِها تتقاسمان
   المُعرِّفَ فتقرأ البوّابةُ صفوفَهما جولةً واحدةً وتسقط (‏درسُ W04). */
$RUN  = 'W11J-' . (string) $one("SELECT DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')");

echo "═══════════ رحلةُ الإقفال — REPAIR01 · W11 §٦-أ ═══════════\n";
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
        "DELETE FROM acc_trial_balance_line WHERE run_id IN
            (SELECT id FROM acc_trial_balance_run WHERE run_ref LIKE 'W11J-%')",
        "DELETE FROM acc_trial_balance_run WHERE run_ref LIKE 'W11J-%'",
        "DELETE FROM acc_account_recon_line WHERE recon_id IN
            (SELECT id FROM acc_account_recon WHERE control_source LIKE 'W11J-%')",
        "DELETE FROM acc_account_recon WHERE control_source LIKE 'W11J-%'",
        "DELETE FROM acc_period_adjustment WHERE adj_no LIKE 'W11J-%'",
        "DELETE FROM acc_period_reopen_request WHERE request_no LIKE 'W11J-%'",
        "DELETE FROM acc_credit_limit WHERE why LIKE 'W11J-%'",
        "DELETE FROM acc_invoice_line WHERE description LIKE 'W11J-%'",
        "DELETE FROM acc_supplier_accrual_line WHERE gate_ref LIKE 'W11J-%'",
        "DELETE FROM fin_journal_lines WHERE entry_id IN
            (SELECT id FROM fin_journal_entries WHERE entry_no LIKE 'W11J-%')",
        "DELETE FROM fin_journal_entries WHERE entry_no LIKE 'W11J-%'",
        "DELETE FROM fin_financial_events WHERE event_no LIKE 'W11-W11J-%'",
        "DELETE FROM acc_recognition_request WHERE request_no LIKE 'W11J-%'",
        "DELETE FROM fin_closing_items WHERE note LIKE 'W11J-%'",
        /* ⚠ **الأثرُ يُكنَس قبل مرجعِه**: حركةُ تسويةِ الجردِ تحمل `ref_id` جلسةً،
             فحذفُ الجلسةِ أوّلًا يقطع الخيطَ ويترك الحركةَ يتيمةً في القاعدةِ
             الحيّة — وهو «أثرٌ باقٍ» يقرؤه الحاجبُ ولا يجد له سببًا. */
        "DELETE FROM tre_cash_move WHERE ref_kind = 'cash_count' AND ref_id IN
            (SELECT id FROM tre_cash_count WHERE count_no LIKE 'W11J-%')",
        "DELETE FROM tre_cash_count_line WHERE count_id IN
            (SELECT id FROM tre_cash_count WHERE count_no LIKE 'W11J-%')",
        "DELETE FROM tre_cash_count WHERE count_no LIKE 'W11J-%'",
        "DELETE FROM tre_petty_expense WHERE custody_id IN
            (SELECT id FROM tre_petty_custody WHERE custody_no LIKE 'W11J-%')",
        "DELETE FROM tre_petty_custody WHERE custody_no LIKE 'W11J-%'",
        "DELETE FROM tre_recon_difference WHERE cause LIKE 'W11J-%'",
        "DELETE FROM tre_guarantee WHERE doc_no LIKE 'W11J-%'",
        "DELETE FROM tre_fx_deal WHERE deal_no LIKE 'W11J-%'",
        "DELETE FROM tre_transfer WHERE transfer_no LIKE 'W11J-%'",
        "DELETE FROM tre_instrument WHERE instrument_no LIKE 'W11J-%'",
        "DELETE FROM tre_cash_move WHERE move_no LIKE 'W11J-%' OR note LIKE 'W11J-%'",
        "DELETE FROM tre_cash_move WHERE ref_kind IN ('payment','cash_count') AND ref_id IN
            (SELECT id FROM fin_payments WHERE payment_no LIKE 'W11J-%')",
        /* ويتيمُ الجولاتِ السابقةِ يُكنَس بمرجعِه المفقودِ لا بنصِّ بيانِه —
           فحركةٌ تشير إلى جلسةٍ لا وجودَ لها أثرٌ باقٍ مهما كان بيانُها. */
        "DELETE m FROM tre_cash_move m LEFT JOIN tre_cash_count c ON c.id = m.ref_id
           WHERE m.ref_kind = 'cash_count' AND c.id IS NULL",
        "DELETE m FROM tre_cash_move m LEFT JOIN fin_payments p ON p.id = m.ref_id
           WHERE m.ref_kind = 'payment' AND p.id IS NULL",
        "DELETE FROM tre_cash_box WHERE code LIKE 'W11J-%'",
        "DELETE FROM tre_beneficiaries WHERE beneficiary_ar LIKE 'W11J-%'",
        "DELETE FROM fin_collection_allocations WHERE note LIKE 'W11J-%'",
        "DELETE FROM fin_payments WHERE payment_no LIKE 'W11J-%'",
        "DELETE FROM fin_receivables WHERE doc_ref LIKE 'W11J-%'",
        "DELETE FROM bank_statements WHERE statement_ref LIKE 'W11J-%'",
        "DELETE FROM fin_financial_periods WHERE reopen_reason LIKE 'W11J-%'",
        "DELETE FROM ems_business_events WHERE idempotency_key LIKE 'w11:%'
           AND JSON_EXTRACT(payload, '$.run') IS NOT NULL",
    );
    foreach ($q as $s) { @$conn->query($s); }
};
$sweep();

/* ══════════════════════════════════════════════════════════════════════════
   الأرضيّة — كيانٌ قانونيٌّ واحدٌ وممثِّلونَ متمايزون
   ══════════════════════════════════════════════════════════════════════════ */
$company = (int) $one("SELECT company_id FROM fin_financial_periods
                        GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
if ($company <= 0) { fwrite(STDERR, "✘ لا كيانَ قانونيًّا بفتراتٍ محاسبيّة\n"); exit(1); }
$actors = array();
$r = $conn->query("SELECT id FROM users WHERE company_id = $company ORDER BY id LIMIT 6");
while ($r && $x = $r->fetch_row()) { $actors[] = (int) $x[0]; }
if (count($actors) < 5) { fwrite(STDERR, "✘ ممثِّلونَ متمايزونَ أقلُّ من خمسة\n"); exit(1); }
list($srcActor, $finManager, $accountant, $treasurer, $reviewer) = $actors;

$accDebit  = (int) $one("SELECT id FROM fin_chart_of_accounts WHERE company_id = $company
                          AND is_postable = 1 AND active = 1 ORDER BY code LIMIT 1");
$accCredit = (int) $one("SELECT id FROM fin_chart_of_accounts WHERE company_id = $company
                          AND is_postable = 1 AND active = 1 AND id <> $accDebit ORDER BY code LIMIT 1");
$bankAcc   = (int) $one("SELECT id FROM fin_bank_accounts WHERE company_id = $company LIMIT 1");
if ($accDebit <= 0 || $accCredit <= 0 || $bankAcc <= 0) {
    fwrite(STDERR, "✘ أرضيّةٌ ناقصة: حسابانِ قابلانِ للترحيلِ وحسابٌ بنكيّ\n"); exit(1);
}

$G  = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($company, $finManager, '', true));
ACC::setEventConnection($conn); ACC::setThresholdConnection($conn);
TRE::setEventConnection($conn); TRE::setThresholdConnection($conn);

/* فترةٌ محاسبيّةٌ مفتوحةٌ لهذه الجولةِ وحدَها — تُكنَس بوسمِها */
$conn->query("INSERT INTO fin_financial_periods
    (company_id, fiscal_year, period_type, period_no, start_date, end_date, state,
     posting_allowed, reopen_reason, created_by, created_at)
  VALUES ($company, 2099, 'month', 1, '2099-01-01', '2099-01-31', 'open', 1,
          '" . $esc($RUN) . "', $finManager, NOW())");
$periodId = (int) $conn->insert_id;
$conn->query("INSERT INTO fin_financial_periods
    (company_id, fiscal_year, period_type, period_no, start_date, end_date, state,
     posting_allowed, reopen_reason, created_by, created_at)
  VALUES ($company, 2099, 'month', 2, '2099-02-01', '2099-02-28', 'open', 1,
          '" . $esc($RUN) . "', $finManager, NOW())");
$periodOpen2 = (int) $conn->insert_id;
if ($periodId <= 0) { fwrite(STDERR, "✘ تعذّر إنشاءُ فترةِ الجولة\n"); exit(1); }

/* بنودُ قائمةِ الإقفالِ لهذه الفترة — الخطواتُ الحيّةُ من تعريفِ الجدولِ نفسِه */
/* ◆ **وبندُ إصدارِ التقاريرِ لاحقٌ للإقفالِ لا شرطٌ له**: القوائمُ تُشتقُّ **بعد**
     الإقفالِ من الميزانِ المقفل، فجعلُه حاجبًا يصنع حلقةً مغلقةً — إقفالٌ ينتظر
     تقريرًا لا يصدر إلّا بعد الإقفال. فيُسجَّل غيرَ حاجبٍ بسببِه المكتوب. */
/* ⛔ **ولا بندَ يكفُّ عن الحجبِ بلا سببٍ مكتوب** — سواءٌ أكان استثناءَ قرارٍ أم
     بندًا لاحقًا للإقفالِ بطبيعتِه. فالسببُ يُكتب في الحالتَين، والعدّادُ يقرؤه. */
$POST_CLOSE_WHY = 'بند لاحق للاقفال بطبيعته: القوائم تشتق بعد الاقفال من الميزان المقفل';
$STEPS = array('reconcile_bank' => '', 'reconcile_ar' => '', 'post_accruals' => '',
               'variance_reviewed' => '', 'reports_issued' => $POST_CLOSE_WHY);
foreach ($STEPS as $s => $why) {
    $conn->query("INSERT INTO fin_closing_items
        (company_id, period_id, step, required, item_state, note, blocks_close,
         exception_reason, created_at)
      VALUES ($company, $periodId, '" . $esc($s) . "', 1, 'pending', '" . $esc($RUN) . "',
              " . ($why === '' ? 1 : 0) . ", '" . $esc($why) . "', NOW())");
}
$blockStep = 'intercompany_settled';
$conn->query("INSERT INTO fin_closing_items
    (company_id, period_id, step, required, item_state, note, blocks_close, created_at)
  VALUES ($company, $periodId, '$blockStep', 1, 'pending', '" . $esc($RUN) . "', 1, NOW())");
$blockItemId = (int) $conn->insert_id;

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ① — طلبُ الاعترافِ: النطاقُ يطلب والماليّةُ تقرّر (§48)
   ══════════════════════════════════════════════════════════════════════════ */
echo "① طلبُ الاعترافِ والقيد\n";
$L1 = 'RECOGNITION';

$selfReq = ACC::requestRecognition($G, array(
    'source_module' => 'finance', 'source_ref' => $RUN . ':self', 'amount' => 100,
    'currency' => 'SDG', 'event_type' => 'expense'), $srcActor);
$log($L1, 'المالية لا تصدر لنفسها طلب اعتراف', 'acc_recognition_request', 'AccountingCycleService',
     'SCOPE_WRITES_ENTRY', $selfReq['code'], 'صفر طلب اعتراف مصدره المالية', 'مردود',
     $selfReq['ok'] === false && $selfReq['code'] === 'SCOPE_WRITES_ENTRY', $company);

$req = ACC::requestRecognition($G, array(
    'request_no' => $RUN . '-R1', 'source_module' => 'procurement',
    'source_screen' => 'Procurement/po_match.php', 'source_ref' => $RUN . ':po',
    'amount' => 1500, 'currency' => 'SDG', 'fx_rate' => 1, 'event_type' => 'payable'), $srcActor);
$reqId = isset($req['request_id']) ? (int) $req['request_id'] : 0;
$log($L1, 'نطاق مصدري يصدر طلب اعتراف بواقعته', 'acc_recognition_request', 'المشتريات',
     'طلب واحد بمرجع واقعته', 'request_id ' . $reqId, 'طلب اعتراف قائم بمبلغه وعملته',
     'قيد الدراسة', $req['ok'] && $reqId > 0, $company);

$cons = new ACC();
$eff1 = $cons->onRecognitionRequested(array('payload' => array('request_id' => $reqId, 'run' => $RUN, 'company_id' => $company)), $conn);
$periodCode = (string) $one("SELECT period_code FROM acc_recognition_request WHERE id = $reqId");
$log($L1, 'المستهلك يحل فترة الطلب من تقويم الفترات', 'acc_recognition_request',
     'AccountingCycleService::onRecognitionRequested', 'period_code غير فارغ',
     'الرد ' . $eff1, 'الطلب صار في فترة معرفة: ' . ($periodCode !== '' ? $periodCode : 'لا شيء'),
     'قيد الدراسة', strpos($eff1, 'W11:PERIOD_RESOLVED') === 0 || $periodCode !== '', $company);

$selfDec = ACC::decideRecognition($G, $reqId, 'accepted', '', $srcActor);
$log($L1, 'من طلب الاعتراف لا يقرره', 'acc_recognition_request', 'AccountingCycleService',
     'SAME_ACTOR_REQUEST_AND_DECIDE', $selfDec['code'], 'صفر طلب قرره طالبه', 'قيد الدراسة',
     $selfDec['ok'] === false && $selfDec['code'] === 'SAME_ACTOR_REQUEST_AND_DECIDE', $company);

$noWhy = ACC::decideRecognition($G, $reqId, 'rejected', '', $finManager);
$log($L1, 'الرد بلا سبب مكتوب لا يقع', 'acc_recognition_request', 'AccountingCycleService',
     'REJECT_WITHOUT_REASON', $noWhy['code'], 'صفر رد بلا سبب', 'قيد الدراسة',
     $noWhy['ok'] === false && $noWhy['code'] === 'REJECT_WITHOUT_REASON', $company);

$dec = ACC::decideRecognition($G, $reqId, 'accepted', 'مطابقة ثلاثية مقبولة', $finManager);
$log($L1, 'المالية تقرر القبول', 'acc_recognition_request', 'مدير المالية',
     'الحالة مقبول', $dec['code'] . ' ' . (isset($dec['decision']) ? $dec['decision'] : ''),
     'قرار قبول مسجل بقراره وصاحبه', 'مقبول', $dec['ok'], $company);

$eff2 = $cons->onRecognitionDecided(array('payload' => array('request_id' => $reqId, 'run' => $RUN, 'company_id' => $company)), $conn);
$evId = (int) $one("SELECT event_id FROM acc_recognition_request WHERE id = $reqId");
$log($L1, 'المستهلك ينشئ واقعة الاعتراف المالية عند القبول', 'fin_financial_events',
     'AccountingCycleService::onRecognitionDecided', 'event_id أكبر من صفر',
     'الرد ' . $eff2, 'واقعة اعتراف مالية قائمة رقم ' . $evId, 'مقبول',
     strpos($eff2, 'W11:FACT_CREATED') === 0 && $evId > 0, $company);

/* طلبٌ ثانٍ يُردّ — ليُقاس أنَّ الترحيلَ لا يمرُّ عليه */
$req2 = ACC::requestRecognition($G, array(
    'request_no' => $RUN . '-R2', 'source_module' => 'maintenance',
    'source_ref' => $RUN . ':mnt', 'amount' => 900, 'currency' => 'SDG',
    'event_type' => 'expense'), $srcActor);
$req2Id = isset($req2['request_id']) ? (int) $req2['request_id'] : 0;
ACC::decideRecognition($G, $req2Id, 'rejected', 'لا سند صرف مقابل الواقعة', $finManager);
$eff2b = $cons->onRecognitionDecided(array('payload' => array('request_id' => $req2Id, 'run' => $RUN, 'company_id' => $company)), $conn);
$ev2 = (int) $one("SELECT event_id FROM acc_recognition_request WHERE id = $req2Id");
$log($L1, 'الرد لا ينشئ واقعة اعتراف', 'fin_financial_events',
     'AccountingCycleService::onRecognitionDecided', 'W11:REJECTED_NO_FACT',
     'الرد ' . $eff2b, 'صفر واقعة عن طلب مردود (event_id ' . $ev2 . ')', 'مردود',
     $eff2b === 'W11:REJECTED_NO_FACT' && $ev2 === 0, $company);

$LINES = array(
    array('account_id' => $accDebit,  'debit' => 1500, 'credit' => 0, 'memo' => $RUN),
    array('account_id' => $accCredit, 'debit' => 0, 'credit' => 1500, 'memo' => $RUN),
);
$noAccept = ACC::postAccepted($G, $req2Id, $LINES, $periodId, $accountant);
$log($L1, 'لا قيد على طلب لم يقبل', 'fin_journal_entries', 'AccountingCycleService',
     'POST_WITHOUT_ACCEPTED_REQUEST', $noAccept['code'], 'صفر قيد على طلب مردود', 'مردود',
     $noAccept['ok'] === false && $noAccept['code'] === 'POST_WITHOUT_ACCEPTED_REQUEST', $company);

$unbal = ACC::postAccepted($G, $reqId, array(
    array('account_id' => $accDebit, 'debit' => 1500, 'credit' => 0),
    array('account_id' => $accCredit, 'debit' => 0, 'credit' => 1400)), $periodId, $accountant);
$log($L1, 'القيد لا يمر بلا توازن', 'fin_journal_entries', 'AccountingCycleService',
     'ENTRY_UNBALANCED', $unbal['code'], 'صفر قيد مرحل غير متوازن', 'مقبول',
     $unbal['ok'] === false && $unbal['code'] === 'ENTRY_UNBALANCED', $company);

$selfPost = ACC::postAccepted($G, $reqId, $LINES, $periodId, $finManager);
$log($L1, 'من قرر الاعتراف لا يرحل قيده', 'fin_journal_entries', 'AccountingCycleService',
     'SAME_ACTOR_PREPARE_AND_POST', $selfPost['code'], 'صفر قيد رحله من قرره', 'مقبول',
     $selfPost['ok'] === false && $selfPost['code'] === 'SAME_ACTOR_PREPARE_AND_POST', $company);

$post = ACC::postAccepted($G, $reqId, $LINES, $periodId, $accountant);
$entryId = isset($post['entry_id']) ? (int) $post['entry_id'] : 0;
$conn->query("UPDATE fin_journal_entries SET entry_no = '" . $esc($RUN . '-E1') . "' WHERE id = $entryId");
$lineN = (int) $one("SELECT COUNT(*) FROM fin_journal_lines WHERE entry_id = $entryId");
$dr = (float) $one("SELECT COALESCE(SUM(debit),0) FROM fin_journal_lines WHERE entry_id = $entryId");
$cr = (float) $one("SELECT COALESCE(SUM(credit),0) FROM fin_journal_lines WHERE entry_id = $entryId");
$log($L1, 'القيد يثبت متوازنا في فترة مفتوحة', 'fin_journal_entries', 'المحاسب',
     'مدين يساوي دائن وسطران', 'مدين ' . $dr . ' دائن ' . $cr . ' اسطر ' . $lineN,
     'قيد مرحل في دفتر الاستاذ رقم ' . $entryId, 'مرحل',
     $post['ok'] && $entryId > 0 && $lineN === 2 && abs($dr - $cr) < 0.005, $company);

$backLink = (int) $one("SELECT journal_entry_id FROM acc_recognition_request WHERE id = $reqId");
$log($L1, 'الخيط يعود من القيد الى واقعته', 'acc_recognition_request', 'FinRequests/effect_map.php',
     'journal_entry_id يساوي القيد', 'الرابط ' . $backLink,
     'كل قيد يرد الى واقعته وكل واقعة الى قيدها', 'مرحل',
     $backLink === $entryId && $entryId > 0, $company);

/* حدٌّ ائتمانيٌّ وذمّةٌ — ليقيسَ المستهلكُ أثرًا يعنيه */
$custId = 9911;
$conn->query("INSERT INTO acc_credit_limit
    (company_id, customer_entity_id, limit_amount, currency, breach_action,
     authority_rule_id, why, is_active, created_at)
  VALUES ($company, $custId, 5000, 'SDG', 'block', 'AAM-ACC-07',
          '" . $esc($RUN . ' حد رحلة الاثبات') . "', 1, NOW())");
/* ⚠ **الذمّةُ لا تُكتب بلا سندٍ مصدريّ** (`chk_recv_source_doc` حيًّا): إمّا
     `source_doc_id` وإمّا وسمُ «بلا مرجعٍ موروث». وأرضيّةُ الرحلةِ تحمل سندَها
     — فلا تُبنى الرحلةُ على صفٍّ يخرق قاعدةً حيّةً ثمَّ تدَّعي عبورًا. */
$conn->query("INSERT INTO fin_receivables
    (company_id, customer_entity_id, doc_type, doc_ref, source_doc_id, amount, currency,
     base_amount, collected, outstanding, state, created_by, created_at)
  VALUES ($company, $custId, 'invoice', '" . $esc($RUN . '-INV') . "', $reqId, 2000, 'SDG', 2000,
          0, 2000, 'open', $finManager, NOW())");
$recvId = (int) $conn->insert_id;
if ($recvId <= 0) { fwrite(STDERR, "✘ تعذّر بناءُ ذمّةِ الأرضيّة: " . $conn->error . "\n"); exit(1); }

$eff3 = $cons->onEntryPosted(array('payload' => array('entry_id' => $entryId, 'run' => $RUN, 'company_id' => $company)), $conn);
$expo = (float) $one("SELECT exposure_amount FROM acc_credit_limit
                       WHERE company_id = $company AND customer_entity_id = $custId");
$log($L1, 'القيد يحرك التعرض الائتماني للعميل', 'acc_credit_limit',
     'AccountingCycleService::onEntryPosted', 'التعرض يساوي الذمم القائمة',
     'الرد ' . $eff3 . ' والتعرض ' . $expo, 'اتاحة العميل تقرا من ذممه القائمة: ' . $expo,
     'مرحل', strpos($eff3, 'W11:EXPOSURE_REFRESHED') === 0 && abs($expo - 2000) < 0.005, $company);

$over = ACC::assertCreditLimit($G, $custId, 4000);
$log($L1, 'البيع فوق الحد الائتماني يحجب', 'acc_credit_limit', 'AccountingCycleService',
     'CREDIT_LIMIT_EXCEEDED', $over['code'], 'صفر عملية تتجاوز الحد بلا اعتماد', 'مرحل',
     $over['ok'] === false && $over['code'] === 'CREDIT_LIMIT_EXCEEDED', $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ② — التسوياتُ وبندُ قائمةِ الإقفال
   ══════════════════════════════════════════════════════════════════════════ */
echo "② التسويات\n";
$L2 = 'ADJUSTMENT';

$conn->query("INSERT INTO acc_period_adjustment
    (company_id, period_id, adj_no, adj_kind, account_code, amount, currency, base_amount,
     basis_doc, reverse_next, state, prepared_by, why, created_at)
  VALUES ($company, $periodId, '" . $esc($RUN . '-ADJ1') . "', 'accrual', '5109', 700, 'SDG', 700,
          '" . $esc($RUN . ' كشف استحقاق لم يفوتر') . "', 1, 'draft', $accountant,
          '" . $esc('خدمة استلمت ولم ترد فاتورتها') . "', NOW())");
$adjId = (int) $conn->insert_id;
$log($L2, 'تسوية استحقاق تعد بمستند اساسها', 'acc_period_adjustment', 'المحاسب',
     'صف تسوية بمستند وسبب', 'adjustment_id ' . $adjId, 'قيد تسوية معد بمستنده', 'مسودة',
     $adjId > 0, $company);

$selfAdj = ACC::postAdjustment($G, $adjId, $accountant);
$log($L2, 'من اعد التسوية لا يعتمدها', 'acc_period_adjustment', 'AccountingCycleService',
     'SAME_ACTOR_PREPARE_AND_APPROVE_ADJ', $selfAdj['code'], 'صفر تسوية اعتمدها معدها', 'مسودة',
     $selfAdj['ok'] === false && $selfAdj['code'] === 'SAME_ACTOR_PREPARE_AND_APPROVE_ADJ', $company);

$adjOk = ACC::postAdjustment($G, $adjId, $finManager);
$adjState = (string) $one("SELECT state FROM acc_period_adjustment WHERE id = $adjId");
$log($L2, 'التسوية ترحل باعتماد غير معدها', 'acc_period_adjustment', 'مدير المالية',
     'الحالة مرحل', $adjOk['code'] . ' والحالة ' . $adjState, 'تسوية مرحلة في فترتها', 'مرحل',
     $adjOk['ok'] && $adjState === 'posted', $company);

$eff4 = $cons->onAdjustmentPosted(array('payload' => array('adjustment_id' => $adjId,
    'period_id' => $periodId, 'run' => $RUN, 'company_id' => $company)), $conn);
$doneAcc = (string) $one("SELECT item_state FROM fin_closing_items
                           WHERE period_id = $periodId AND step = 'post_accruals'");
$log($L2, 'المستهلك يغلق بند ترحيل التسويات في قائمة الاقفال', 'fin_closing_items',
     'AccountingCycleService::onAdjustmentPosted', 'حالة البند مكتمل',
     'الرد ' . $eff4 . ' والحالة ' . $doneAcc, 'شرط اقفال تقدم خطوة واحدة', 'مرحل',
     $doneAcc === 'done', $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ③ — مطابقةُ الحسابِ الرقابيِّ وبنودُ فروقِها
   ══════════════════════════════════════════════════════════════════════════ */
echo "③ المطابقات\n";
$L3 = 'RECONCILIATION';

$rec = ACC::openAccountRecon($G, array('period_id' => $periodId, 'account_code' => '1104',
    'control_source' => $RUN . ' دفتر ذمم العملاء', 'gl_balance' => 2000,
    'source_balance' => 1850), $accountant);
$reconId = isset($rec['recon_id']) ? (int) $rec['recon_id'] : 0;
$diffVal = (float) $one("SELECT difference FROM acc_account_recon WHERE id = $reconId");
$log($L3, 'جلسة مطابقة حساب رقابي تفتح بفرقها المشتق', 'acc_account_recon', 'المحاسب',
     'الفرق مشتق لا مكتوب', 'recon_id ' . $reconId . ' والفرق ' . $diffVal,
     'فرق مقيس بين الدفتر والمصدر التفصيلي', 'مفتوح',
     $rec['ok'] && abs($diffVal - 150) < 0.005, $company);

$badDiff = ACC::addReconDifference($G, $reconId, array('line_kind' => 'timing',
    'cause' => $RUN . ' ايداع في الطريق', 'amount' => 150, 'responsible_role' => '', 'action_taken' => ''));
$log($L3, 'فرق بلا مسؤول ولا اجراء لا يقيد', 'acc_account_recon_line', 'AccountingCycleService',
     'DIFFERENCE_WITHOUT_OWNER', $badDiff['code'], 'صفر فرق مدفون بلا مسؤول', 'مفتوح',
     $badDiff['ok'] === false && $badDiff['code'] === 'DIFFERENCE_WITHOUT_OWNER', $company);

$okDiff = ACC::addReconDifference($G, $reconId, array('line_kind' => 'timing',
    'cause' => $RUN . ' ايداع في الطريق', 'amount' => 150,
    'responsible_role' => 'أمين الخزينة', 'action_taken' => 'متابعة الايداع حتى ظهوره في الكشف'));
$diffLineId = isset($okDiff['line_id']) ? (int) $okDiff['line_id'] : 0;
$openN = (int) $one("SELECT open_diffs FROM acc_account_recon WHERE id = $reconId");
$log($L3, 'بند الفرق يقيد بنوعه وسببه ومسؤوله واجرائه', 'acc_account_recon_line', 'المحاسب',
     'عداد الفروق المفتوحة يساوي واحدا', 'line_id ' . $diffLineId . ' والمفتوح ' . $openN,
     'فرق مسمى بمسؤوله لا رقم في حقل', 'مفتوح', $okDiff['ok'] && $openN === 1, $company);

$closeOpen = ACC::closeAccountRecon($G, $reconId, $finManager);
$log($L3, 'لا تقفل مطابقة وفيها فرق مفتوح', 'acc_account_recon', 'AccountingCycleService',
     'RECON_CLOSE_WITH_OPEN_DIFF', $closeOpen['code'], 'صفر جلسة مقفلة بفرق مفتوح', 'مفتوح',
     $closeOpen['ok'] === false && $closeOpen['code'] === 'RECON_CLOSE_WITH_OPEN_DIFF', $company);

ACC::resolveReconDifference($G, $diffLineId, $finManager);
$selfClose = ACC::closeAccountRecon($G, $reconId, $accountant);
$log($L3, 'من اعد المطابقة لا يقفلها', 'acc_account_recon', 'AccountingCycleService',
     'SAME_ACTOR_PREPARE_AND_CLOSE_RECON', $selfClose['code'], 'صفر جلسة اقفلها معدها', 'مفتوح',
     $selfClose['ok'] === false && $selfClose['code'] === 'SAME_ACTOR_PREPARE_AND_CLOSE_RECON', $company);

$recClose = ACC::closeAccountRecon($G, $reconId, $finManager);
$recState = (string) $one("SELECT state FROM acc_account_recon WHERE id = $reconId");
$log($L3, 'المطابقة تقفل بصفر فرق مفتوح', 'acc_account_recon', 'مدير المالية',
     'الحالة مقفل', $recClose['code'] . ' والحالة ' . $recState, 'حساب رقابي مطابق ومقفل',
     'مقفل', $recClose['ok'] && $recState === 'closed', $company);

$eff5 = $cons->onAccountReconciled(array('payload' => array('recon_id' => $reconId,
    'period_id' => $periodId, 'account_code' => '1104', 'run' => $RUN, 'company_id' => $company)), $conn);
$doneAr = (string) $one("SELECT item_state FROM fin_closing_items
                          WHERE period_id = $periodId AND step = 'reconcile_ar'");
$log($L3, 'المستهلك يغلق بند مطابقة ذمم العملاء', 'fin_closing_items',
     'AccountingCycleService::onAccountReconciled', 'حالة البند مكتمل',
     'الرد ' . $eff5 . ' والحالة ' . $doneAr, 'شرط اقفال تقدم خطوة ثانية', 'مقفل',
     $doneAr === 'done', $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ④ — الخزينة: قبضٌ وتخصيصٌ ودفعٌ ومطابقةٌ بنكيّةٌ وجرد
   ══════════════════════════════════════════════════════════════════════════ */
echo "④ الخزينة\n";
$L4 = 'TREASURY';

$noRef = TRE::recordReceipt($G, array('payment_no' => $RUN . '-RCV0', 'party_type' => 'customer',
    'party_ref' => $custId, 'method' => 'bank', 'amount' => 1200, 'currency' => 'SDG'), $treasurer);
$log($L4, 'سند قبض بلا مرجع بنكي لا يقبل', 'fin_payments', 'TreasuryCycleService',
     'RECEIPT_WITHOUT_BANK_REF', $noRef['code'], 'صفر سند قبض بلا مرجع موثق', 'مردود',
     $noRef['ok'] === false && $noRef['code'] === 'RECEIPT_WITHOUT_BANK_REF', $company);

$rcv = TRE::recordReceipt($G, array('payment_no' => $RUN . '-RCV', 'party_type' => 'customer',
    'party_ref' => $custId, 'method' => 'bank', 'bank_ref' => $RUN . '-BREF',
    'amount' => 1200, 'currency' => 'SDG'), $treasurer);
$rcvId = isset($rcv['receipt_id']) ? (int) $rcv['receipt_id'] : 0;
$log($L4, 'سند قبض يسجل بمبلغه وعملته', 'fin_payments', 'أمين الخزينة',
     'سند قبض واحد', 'receipt_id ' . $rcvId, 'نقد وارد مسجل بسنده', 'معتمد',
     $rcv['ok'] && $rcvId > 0, $company);

$overAlloc = TRE::allocateReceipt($G, $rcvId, array(
    array('receivable_id' => $recvId, 'amount' => 1500, 'target_kind' => 'invoice')), $treasurer);
$log($L4, 'التخصيص لا يتجاوز مبلغ السند', 'fin_collection_allocations', 'TreasuryCycleService',
     'ALLOCATION_EXCEEDS_RECEIPT', $overAlloc['code'], 'صفر تخصيص يتجاوز سنده', 'معتمد',
     $overAlloc['ok'] === false && $overAlloc['code'] === 'ALLOCATION_EXCEEDS_RECEIPT', $company);

$alloc = TRE::allocateReceipt($G, $rcvId, array(
    array('receivable_id' => $recvId, 'amount' => 1200, 'target_kind' => 'invoice')), $treasurer);
$conn->query("UPDATE fin_collection_allocations SET note = '" . $esc($RUN) . "' WHERE payment_id = $rcvId");
$log($L4, 'التخصيص يقيد سطرا لكل فاتورة', 'fin_collection_allocations', 'أمين الخزينة',
     'سطر تخصيص واحد', $alloc['code'] . ' اسطر ' . (isset($alloc['lines']) ? $alloc['lines'] : 0),
     'دفعة موزعة على فواتيرها بسطورها', 'معتمد', $alloc['ok'], $company);

$effT1 = (new TRE())->onReceiptAllocated(array('payload' => array('receipt_id' => $rcvId, 'run' => $RUN, 'company_id' => $company)), $conn);
$outst = (float) $one("SELECT outstanding FROM fin_receivables WHERE id = $recvId");
$rvState = (string) $one("SELECT state FROM fin_receivables WHERE id = $recvId");
$log($L4, 'المستهلك يحدث المحصل والمتبقي وحالة الذمة', 'fin_receivables',
     'TreasuryCycleService::onReceiptAllocated', 'المتبقي 800 والحالة جزئية',
     'الرد ' . $effT1 . ' والمتبقي ' . $outst . ' والحالة ' . $rvState,
     'ذمة العميل انخفضت بالتحصيل فعلا', 'جزئي',
     abs($outst - 800) < 0.005 && $rvState === 'partial', $company);

$conn->query("INSERT INTO tre_beneficiaries
    (company_id, party_type, party_ref, beneficiary_ar, bank_name, iban, account_no,
     currency, is_active, created_by, created_at)
  VALUES ($company, 'supplier', 771, '" . $esc($RUN . ' مستفيد الرحلة') . "', 'بنك الرحلة',
          'SD00', '0001', 'SDG', 1, $treasurer, NOW())");
$benId = (int) $conn->insert_id;
$conn->query("INSERT INTO fin_payments
    (company_id, payment_no, direction, party_type, party_ref, method, amount,
     allocated_amount, unallocated_amount, currency, fx_rate, base_amount, state, created_by, created_at)
  VALUES ($company, '" . $esc($RUN . '-PAY') . "', 'disbursement', 'supplier', 771, 'bank', 500,
          0, 500, 'SDG', 1, 500, 'approved', $accountant, NOW())");
$payId = (int) $conn->insert_id;

$unver = TRE::executePayment($G, $payId, $benId, $bankAcc, $treasurer);
$log($L4, 'لا دفع لمستفيد غير محقق', 'tre_beneficiaries', 'TreasuryCycleService',
     'BENEFICIARY_NOT_VERIFIED', $unver['code'], 'صفر دفعة لمستفيد غير محقق', 'معتمد',
     $unver['ok'] === false && $unver['code'] === 'BENEFICIARY_NOT_VERIFIED', $company);

$vfyNoDoc = TRE::verifyBeneficiary($G, $benId, '', $finManager);
$log($L4, 'التحقق بلا مصدر توثيق لا يقع', 'tre_beneficiaries', 'TreasuryCycleService',
     'VERIFY_WITHOUT_DOC', $vfyNoDoc['code'], 'صفر مستفيد محقق بلا مستند', 'غير محقق',
     $vfyNoDoc['ok'] === false && $vfyNoDoc['code'] === 'VERIFY_WITHOUT_DOC', $company);

TRE::verifyBeneficiary($G, $benId, $RUN . ' خطاب بنكي معتمد', $finManager);
$locked = (string) $one("SELECT locked_at FROM tre_beneficiaries WHERE id = $benId");
$log($L4, 'التحقق يقفل الحساب البنكي ضد التعديل', 'tre_beneficiaries', 'مدير المالية',
     'locked_at غير فارغ', 'القفل ' . ($locked !== '' ? 'واقع' : 'غائب'),
     'حساب مستفيد موثق ومقفل ضد التبديل الصامت', 'محقق', $locked !== '', $company);

$chg = TRE::changeBeneficiaryAccount($G, $benId, array('iban' => 'SD99'), '', $treasurer);
$log($L4, 'الحساب المقفل لا يبدل بلا توثيق جديد', 'tre_beneficiaries', 'TreasuryCycleService',
     'BENEFICIARY_ACCOUNT_LOCKED', $chg['code'], 'صفر تبديل صامت لحساب محقق', 'محقق',
     $chg['ok'] === false && $chg['code'] === 'BENEFICIARY_ACCOUNT_LOCKED', $company);

$selfExec = TRE::executePayment($G, $payId, $benId, $bankAcc, $accountant);
$log($L4, 'من اعد الدفعة لا ينفذها', 'fin_payments', 'TreasuryCycleService',
     'SAME_ACTOR_PREPARE_AND_EXECUTE', $selfExec['code'], 'صفر دفعة نفذها معدها', 'معتمد',
     $selfExec['ok'] === false && $selfExec['code'] === 'SAME_ACTOR_PREPARE_AND_EXECUTE', $company);

$exec = TRE::executePayment($G, $payId, $benId, $bankAcc, $treasurer);
$effT2 = (new TRE())->onPaymentExecuted(array('payload' => array('payment_id' => $payId,
    'vessel_id' => $bankAcc, 'run' => $RUN, 'company_id' => $company)), $conn);
$outMove = (int) $one("SELECT COUNT(*) FROM tre_cash_move
                        WHERE ref_kind = 'payment' AND ref_id = $payId AND direction = 'out'");
$log($L4, 'التنفيذ يخرج نقدا من وعائه', 'tre_cash_move',
     'TreasuryCycleService::onPaymentExecuted', 'حركة خروج واحدة',
     'الرد ' . $effT2 . ' وحركات الخروج ' . $outMove, 'رصيد الوعاء انخفض بالتنفيذ فعلا',
     'منفذ', $exec['ok'] && $outMove === 1, $company);

$conn->query("INSERT INTO bank_statements
    (company_id, bank_account_id, statement_ref, period_from, period_to, opening_balance,
     closing_balance, currency, lines_count, state, created_by, created_at)
  VALUES ($company, $bankAcc, '" . $esc($RUN . '-ST') . "', '2099-01-01', '2099-01-31',
          0, 700, 'SDG', 0, 'matching', $treasurer, NOW())");
$stId = (int) $conn->insert_id;

$badBd = TRE::openBankDifference($G, $stId, array('diff_kind' => 'timing',
    'cause' => $RUN . ' شيك لم يقدم', 'amount' => 500, 'responsible_role' => '',
    'action_taken' => ''), $treasurer);
$log($L4, 'فرق بنكي بلا مسؤول ولا اجراء لا يقيد', 'tre_recon_difference', 'TreasuryCycleService',
     'DIFFERENCE_WITHOUT_OWNER', $badBd['code'], 'صفر فرق بنكي مدفون', 'قيد المطابقة',
     $badBd['ok'] === false && $badBd['code'] === 'DIFFERENCE_WITHOUT_OWNER', $company);

$bd = TRE::openBankDifference($G, $stId, array('diff_kind' => 'timing',
    'cause' => $RUN . ' شيك لم يقدم للبنك', 'amount' => 500,
    'responsible_role' => 'أمين الخزينة', 'action_taken' => 'متابعة تقديم الشيك'), $treasurer);
$bdId = isset($bd['difference_id']) ? (int) $bd['difference_id'] : 0;
$stDiff = (int) $one("SELECT diff_count FROM bank_statements WHERE id = $stId");
$log($L4, 'بند فرق المطابقة البنكية يقيد بمسؤوله واجرائه', 'tre_recon_difference', 'أمين الخزينة',
     'عداد الفروق المفتوحة واحد', 'difference_id ' . $bdId . ' والعداد ' . $stDiff,
     'فرق بنكي مسمى بمسؤوله حتى الاغلاق', 'مفتوح', $bd['ok'] && $stDiff === 1, $company);

$bClose1 = TRE::closeBankRecon($G, $stId, $finManager);
$log($L4, 'لا يقفل كشف وفيه فرق مفتوح', 'bank_statements', 'TreasuryCycleService',
     'BANK_CLOSE_WITH_OPEN_DIFF', $bClose1['code'], 'صفر كشف مقفل بفرق مفتوح', 'قيد المطابقة',
     $bClose1['ok'] === false && $bClose1['code'] === 'BANK_CLOSE_WITH_OPEN_DIFF', $company);

TRE::resolveBankDifference($G, $bdId, $finManager);
$selfRev = TRE::closeBankRecon($G, $stId, $treasurer);
$log($L4, 'من اعد المطابقة البنكية لا يراجعها', 'bank_statements', 'TreasuryCycleService',
     'SAME_ACTOR_PREPARE_AND_REVIEW_BANK', $selfRev['code'], 'صفر كشف راجعه معده', 'قيد المطابقة',
     $selfRev['ok'] === false && $selfRev['code'] === 'SAME_ACTOR_PREPARE_AND_REVIEW_BANK', $company);

$bClose = TRE::closeBankRecon($G, $stId, $finManager);
$effT3 = (new TRE())->onBankReconciled(array('payload' => array('statement_id' => $stId, 'run' => $RUN, 'company_id' => $company)), $conn);
$doneBank = (string) $one("SELECT item_state FROM fin_closing_items
                            WHERE period_id = $periodId AND step = 'reconcile_bank'");
$log($L4, 'المطابقة تقفل والمستهلك يغلق بندها في قائمة الاقفال', 'fin_closing_items',
     'TreasuryCycleService::onBankReconciled', 'حالة البند مكتمل',
     $bClose['code'] . ' والرد ' . $effT3 . ' والحالة ' . $doneBank,
     'شرط اقفال تقدم خطوة ثالثة', 'مقفل', $bClose['ok'] && $doneBank === 'done', $company);

$conn->query("INSERT INTO tre_cash_box (company_id, code, name, currency, custodian_id,
     opening_balance, is_active, created_by, created_at)
  VALUES ($company, '" . $esc($RUN . '-BOX') . "', 'صندوق رحلة الاثبات', 'SDG', $treasurer,
          1000, 1, $treasurer, NOW())");
$boxId = (int) $conn->insert_id;

$noCom = TRE::openCashCount($G, array('count_no' => $RUN . '-C0', 'box_id' => $boxId,
    'count_kind' => 'surprise', 'counted_balance' => 950, 'committee_size' => 1), $treasurer);
$log($L4, 'الجرد بلجنة لا بامين الصندوق وحده', 'tre_cash_count', 'TreasuryCycleService',
     'COUNT_WITHOUT_COMMITTEE', $noCom['code'], 'صفر جرد بعضو واحد', 'مسودة',
     $noCom['ok'] === false && $noCom['code'] === 'COUNT_WITHOUT_COMMITTEE', $company);

$cnt = TRE::openCashCount($G, array('count_no' => $RUN . '-C1', 'box_id' => $boxId,
    'count_kind' => 'surprise', 'counted_balance' => 950, 'committee_size' => 3), $treasurer);
$cntId = isset($cnt['count_id']) ? (int) $cnt['count_id'] : 0;
$cntDiff = (float) $one("SELECT difference FROM tre_cash_count WHERE id = $cntId");
$log($L4, 'الجرد يفتح ورصيده الدفتري مشتق من الحركات', 'tre_cash_count', 'لجنة الجرد',
     'الفرق مشتق', 'count_id ' . $cntId . ' والفرق ' . $cntDiff,
     'عجز مقيس بين الدفتري والمعدود', 'مسودة', $cnt['ok'] && abs($cntDiff + 50) < 0.005, $company);

$selfCnt = TRE::approveCashCount($G, $cntId, 'محضر معالجة', $treasurer);
$log($L4, 'من عد لا يعتمد جرده', 'tre_cash_count', 'TreasuryCycleService',
     'SAME_ACTOR_COUNT_AND_APPROVE', $selfCnt['code'], 'صفر جرد اعتمده عاده', 'مسودة',
     $selfCnt['ok'] === false && $selfCnt['code'] === 'SAME_ACTOR_COUNT_AND_APPROVE', $company);

$noAct = TRE::approveCashCount($G, $cntId, '', $finManager);
$log($L4, 'فرق الجرد لا يمر بلا معالجة مكتوبة', 'tre_cash_count', 'TreasuryCycleService',
     'COUNT_DIFF_WITHOUT_ACTION', $noAct['code'], 'صفر فرق مدفون بلا معالجة', 'مسودة',
     $noAct['ok'] === false && $noAct['code'] === 'COUNT_DIFF_WITHOUT_ACTION', $company);

$cntOk = TRE::approveCashCount($G, $cntId, $RUN . ' محضر عجز محال الى التحقيق', $finManager);
$effT4 = (new TRE())->onCashCountApproved(array('payload' => array('count_id' => $cntId, 'run' => $RUN, 'company_id' => $company)), $conn);
$adjMove = (int) $one("SELECT COUNT(*) FROM tre_cash_move
                        WHERE ref_kind = 'cash_count' AND ref_id = $cntId");
$log($L4, 'المستهلك يحول فرق الجرد الى حركة تسوية مسماة', 'tre_cash_move',
     'TreasuryCycleService::onCashCountApproved', 'حركة تسوية واحدة',
     'الرد ' . $effT4 . ' وحركات التسوية ' . $adjMove,
     'الرصيد الدفتري صار يطابق المعدود بحركة مسماة', 'معتمد',
     $cntOk['ok'] && $adjMove === 1, $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑤ — الميزانُ وقائمةُ الإقفالِ والإقفالُ والقوائم
   ══════════════════════════════════════════════════════════════════════════ */
echo "⑤ الميزانُ والإقفالُ والقوائم\n";
$L5 = 'CLOSE';

$noTb = ACC::closePeriod($G, $periodId, $finManager);
$log($L5, 'لا اقفال بلا جولة ميزان', 'fin_financial_periods', 'AccountingCycleService',
     'CLOSE_WITHOUT_TRIAL_BALANCE', $noTb['code'], 'صفر فترة مقفلة بلا ميزان', 'مفتوح',
     $noTb['ok'] === false && $noTb['code'] === 'CLOSE_WITHOUT_TRIAL_BALANCE', $company);

$tb = ACC::runTrialBalance($G, $periodId, $accountant);
$tbId = isset($tb['run_id']) ? (int) $tb['run_id'] : 0;
$conn->query("UPDATE acc_trial_balance_run SET run_ref = '" . $esc($RUN . '-TB') . "' WHERE id = $tbId");
$tbLines = (int) $one("SELECT COUNT(*) FROM acc_trial_balance_line WHERE run_id = $tbId");
$log($L5, 'جولة ميزان تشتق من القيود المنشورة', 'acc_trial_balance_run', 'المحاسب',
     'متوازن واسطر مشتقة', 'run_id ' . $tbId . ' متوازن ' . (isset($tb['balanced']) ? $tb['balanced'] : 0)
     . ' اسطر ' . $tbLines, 'لقطة ميزان بزمنها يستند اليها الاقفال', 'متوازن',
     $tb['ok'] && (int) $tb['balanced'] === 1 && $tbLines >= 2, $company);

$eff6 = $cons->onTrialBalanced(array('payload' => array('run_id' => $tbId, 'period_id' => $periodId,
    'balanced' => 1, 'run' => $RUN, 'company_id' => $company)), $conn);
$doneVar = (string) $one("SELECT item_state FROM fin_closing_items
                           WHERE period_id = $periodId AND step = 'variance_reviewed'");
$log($L5, 'المستهلك يغلق بند مراجعة الفروق عند التوازن', 'fin_closing_items',
     'AccountingCycleService::onTrialBalanced', 'حالة البند مكتمل',
     'الرد ' . $eff6 . ' والحالة ' . $doneVar, 'شرط اقفال تقدم خطوة رابعة', 'متوازن',
     $doneVar === 'done', $company);

$blocked = ACC::closePeriod($G, $periodId, $finManager);
$blockN = ACC::blockingChecklistItems($G, $periodId);
$log($L5, 'لا اقفال وفي القائمة بند حاجب', 'fin_closing_items', 'AccountingCycleService',
     'CLOSE_WITH_BLOCKING_ITEM', $blocked['code'] . ' والحاجب ' . $blockN,
     'صفر فترة مقفلة ببند ناقص', 'مفتوح',
     $blocked['ok'] === false && $blocked['code'] === 'CLOSE_WITH_BLOCKING_ITEM' && $blockN > 0, $company);

$excNo = ACC::documentChecklistException($G, $blockItemId, '', $finManager);
$log($L5, 'الاستثناء بلا قرار مكتوب لا يقع', 'fin_closing_items', 'AccountingCycleService',
     'EXCEPTION_WITHOUT_REASON', $excNo['code'], 'صفر استثناء بلا قرار', 'مفتوح',
     $excNo['ok'] === false && $excNo['code'] === 'EXCEPTION_WITHOUT_REASON', $company);

ACC::documentChecklistException($G, $blockItemId,
    $RUN . ' لا معاملات بين الكيانات في هذه الفترة — استثناء موثق', $finManager);
$blockAfter = ACC::blockingChecklistItems($G, $periodId);
$log($L5, 'توثيق الاستثناء يرفع الحجب لا يمحو البند', 'fin_closing_items', 'مدير المالية',
     'الحاجب صفر والبند قائم', 'الحاجب بعد التوثيق ' . $blockAfter,
     'بند ناقص مر باستثناء موثق لا بحذف', 'مفتوح', $blockAfter === 0, $company);

$selfCl = ACC::closePeriod($G, $periodId, $accountant);
$log($L5, 'من اجرى الميزان لا يقفل الفترة', 'fin_financial_periods', 'AccountingCycleService',
     'SAME_ACTOR_PREPARE_AND_CLOSE', $selfCl['code'], 'صفر فترة اقفلها من اجرى ميزانها', 'مفتوح',
     $selfCl['ok'] === false && $selfCl['code'] === 'SAME_ACTOR_PREPARE_AND_CLOSE', $company);

$stmtEarly = ACC::issueStatements($G, $periodId, $finManager);
$log($L5, 'لا قوائم قبل الاقفال', 'fin_financial_periods', 'AccountingCycleService',
     'STATEMENTS_BEFORE_CLOSE', $stmtEarly['code'], 'صفر قائمة صدرت قبل اقفال فترتها', 'مفتوح',
     $stmtEarly['ok'] === false && $stmtEarly['code'] === 'STATEMENTS_BEFORE_CLOSE', $company);

$cl = ACC::closePeriod($G, $periodId, $finManager);
$eff7 = $cons->onPeriodClosed(array('payload' => array('period_id' => $periodId,
    'run_id' => $tbId, 'run' => $RUN, 'company_id' => $company)), $conn);
$postAllow = (int) $one("SELECT posting_allowed FROM fin_financial_periods WHERE id = $periodId");
$log($L5, 'الاقفال يقع والمستهلك يمنع الترحيل', 'fin_financial_periods',
     'AccountingCycleService::onPeriodClosed', 'posting_allowed صفر',
     $cl['code'] . ' والرد ' . $eff7 . ' والسماح ' . $postAllow,
     'الفترة صارت لا تقبل قيدا — اثبات لا اعلان', 'مقفل',
     $cl['ok'] && $postAllow === 0, $company);

$req3 = ACC::requestRecognition($G, array('request_no' => $RUN . '-R3', 'source_module' => 'warehouse',
    'source_ref' => $RUN . ':wh', 'amount' => 300, 'currency' => 'SDG', 'event_type' => 'expense'), $srcActor);
$req3Id = isset($req3['request_id']) ? (int) $req3['request_id'] : 0;
ACC::decideRecognition($G, $req3Id, 'accepted', 'سند ادخال مطابق', $finManager);
$closedPost = ACC::postAccepted($G, $req3Id, array(
    array('account_id' => $accDebit, 'debit' => 300, 'credit' => 0),
    array('account_id' => $accCredit, 'debit' => 0, 'credit' => 300)), $periodId, $accountant);
$log($L5, 'لا قيد على فترة مقفلة', 'fin_journal_entries', 'AccountingCycleService',
     'POST_TO_CLOSED_PERIOD', $closedPost['code'], 'صفر قيد دخل فترة مقفلة', 'مقفل',
     $closedPost['ok'] === false && $closedPost['code'] === 'POST_TO_CLOSED_PERIOD', $company);

$stmt = ACC::issueStatements($G, $periodId, $finManager);
$eff8 = $cons->onStatementsIssued(array('payload' => array('period_id' => $periodId,
    'run_id' => $tbId, 'run' => $RUN, 'company_id' => $company)), $conn);
$doneRep = (string) $one("SELECT item_state FROM fin_closing_items
                           WHERE period_id = $periodId AND step = 'reports_issued'");
$log($L5, 'القوائم تشتق بعد الاقفال من ميزانه', 'fin_financial_periods',
     'AccountingCycleService::onStatementsIssued', 'حالة بند التقارير مكتمل',
     $stmt['code'] . ' والرد ' . $eff8 . ' والحالة ' . $doneRep,
     'قوائم صادرة عن ميزان مقفل متوازن', 'مقفل', $stmt['ok'] && $doneRep === 'done', $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑥ — حوكمةُ إعادةِ الفتح
   ══════════════════════════════════════════════════════════════════════════ */
echo "⑥ حوكمةُ إعادةِ الفتح\n";
$L6 = 'REOPEN';

$roBad = ACC::requestReopen($G, array('period_id' => $periodId, 'request_no' => $RUN . '-RO0',
    'justification' => '', 'authority_rule_id' => '', 'scope_units' => ''), $accountant);
$log($L6, 'طلب اعادة فتح بلا مبرر ولا قاعدة ولا نطاق يرد', 'acc_period_reopen_request',
     'AccountingCycleService', 'REOPEN_WITHOUT_AUTHORITY', $roBad['code'],
     'صفر طلب اعادة فتح بلا حوكمة', 'مقفل',
     $roBad['ok'] === false && $roBad['code'] === 'REOPEN_WITHOUT_AUTHORITY', $company);

$ro = ACC::requestReopen($G, array('period_id' => $periodId, 'request_no' => $RUN . '-RO1',
    'justification' => 'قيد مورد وصل بعد الاقفال بمستنده', 'authority_rule_id' => 'AAM-ACC-06',
    'scope_units' => 'الادارة المالية وحدها', 'scope_from' => '2099-01-01',
    'scope_to' => '2099-01-31'), $accountant);
$roId = isset($ro['reopen_id']) ? (int) $ro['reopen_id'] : 0;
$log($L6, 'طلب اعادة فتح بمبرره ونطاقه وقاعدته', 'acc_period_reopen_request', 'المحاسب',
     'طلب واحد معلق', 'reopen_id ' . $roId, 'استثناء محكوم مسجل لا فعل صامت', 'قيد الدراسة',
     $ro['ok'] && $roId > 0, $company);

$selfRo = ACC::approveReopen($G, $roId, $accountant);
$log($L6, 'من طلب اعادة الفتح لا يعتمدها', 'acc_period_reopen_request', 'AccountingCycleService',
     'SAME_ACTOR_REQUEST_AND_APPROVE_REOPEN', $selfRo['code'], 'صفر طلب اعتمده طالبه', 'قيد الدراسة',
     $selfRo['ok'] === false && $selfRo['code'] === 'SAME_ACTOR_REQUEST_AND_APPROVE_REOPEN', $company);

$roOk = ACC::approveReopen($G, $roId, $finManager);
$eff9 = $cons->onPeriodReopened(array('payload' => array('reopen_id' => $roId,
    'period_id' => $periodId, 'run' => $RUN, 'company_id' => $company)), $conn);
$allowAfter = (int) $one("SELECT posting_allowed FROM fin_financial_periods WHERE id = $periodId");
$roState = (string) $one("SELECT state FROM acc_period_reopen_request WHERE id = $roId");
$log($L6, 'الاعتماد يعيد الفتح والمستهلك يعيد السماح بالترحيل', 'fin_financial_periods',
     'AccountingCycleService::onPeriodReopened', 'posting_allowed واحد',
     $roOk['code'] . ' والرد ' . $eff9 . ' والسماح ' . $allowAfter . ' والحالة ' . $roState,
     'الترحيل عاد في النطاق المعتمد وحده', 'مطبق',
     $roOk['ok'] && $allowAfter === 1 && $roState === 'applied', $company);

/* ══════════════════════════════════════════════════════════════════════════
   الشوطُ ⑦ — الكيانُ الواحدُ والرقمُ المجمَّع
   ══════════════════════════════════════════════════════════════════════════ */
echo "⑦ الكيانُ الواحدُ والرقمُ المجمَّع\n";
$L7 = 'ENTITY';

$mix = ACC::assertSingleEntity($conn, 'w11.entity.trial_balance', array($company, $company + 1));
$log($L7, 'رقم يخلط كيانين بلا وسم يرفض', 'repair01_w11_consolidated', 'AccountingCycleService',
     'CROSS_ENTITY_UNTAGGED', $mix['code'], 'صفر رقم مختلط غير موسوم', 'مرفوض',
     $mix['ok'] === false && $mix['code'] === 'CROSS_ENTITY_UNTAGGED', $company);

$grp = ACC::assertSingleEntity($conn, 'w11.group.executive_board', array($company, $company + 1));
$log($L7, 'الرقم المجمع الموسوم يمر بوسمه', 'repair01_w11_consolidated', 'مدير المالية',
     'الوسم GROUP_PROJECTION', $grp['code'] . ' ' . (isset($grp['tag']) ? $grp['tag'] : ''),
     'رقم مجموعة معلن بوسمه لا يقرا رقم كيان', 'موسوم',
     $grp['ok'] && isset($grp['tag']) && $grp['tag'] === ACC::TAG_GROUP, $company);

$single = ACC::assertSingleEntity($conn, 'w11.entity.period_close', array($company));
$log($L7, 'رقم الكيان الواحد يمر بلا وسم', 'repair01_w11_consolidated', 'مدير المالية',
     'الوسم SINGLE_ENTITY', $single['code'] . ' ' . (isset($single['tag']) ? $single['tag'] : ''),
     'كل رقم في الرحلة لكيان واحد من طرف الى طرف', 'كيان واحد',
     $single['ok'] && $single['tag'] === ACC::TAG_SINGLE, $company);

$entryScope = (string) $one("SELECT entity_scope FROM fin_journal_entries WHERE id = $entryId");
$entryCo = (int) $one("SELECT company_id FROM fin_journal_entries WHERE id = $entryId");
$log($L7, 'القيد يحمل كيانه ووسم نطاقه', 'fin_journal_entries', 'مدير المالية',
     'كيان الرحلة ووسم كيان واحد', 'company_id ' . $entryCo . ' ووسم ' . $entryScope,
     'لا قيد بلا كيان ولا رقم يخلط كيانين صامتا', 'مرحل',
     $entryCo === $company && $entryScope === ACC::TAG_SINGLE, $company);

$subs = (int) $one("SELECT COUNT(*) FROM event_consumers
                     WHERE event_name LIKE 'acc.%' AND active = 1");
$subsT = (int) $one("SELECT COUNT(*) FROM event_consumers
                      WHERE event_name LIKE 'tre.%' AND active = 1");
$log($L7, 'الجذر يرفض النشر لحدث بلا مشترك نشط', 'event_consumers', 'الجذر المحايد',
     'مشتركون نشطون في الطرفين', 'محاسبة ' . $subs . ' وخزينة ' . $subsT,
     'كل حدث في النطاق له مستهلك نشط بالاسم', 'مسجل',
     $subs >= 9 && $subsT >= 4, $company);

/* ══════════════════════════════════════════════════════════════════════════
   الكنسُ ثمَّ الدليل
   ══════════════════════════════════════════════════════════════════════════ */
$conn->query("DELETE FROM fin_closing_items WHERE period_id IN ($periodId, $periodOpen2)");
$conn->query("DELETE FROM acc_credit_limit WHERE company_id = $company AND customer_entity_id = $custId");
$conn->query("DELETE FROM fin_receivables WHERE id = $recvId");
$conn->query("DELETE FROM tre_beneficiaries WHERE id = $benId");
$conn->query("DELETE FROM fin_financial_periods WHERE id IN ($periodId, $periodOpen2)");
$sweep();

$left = 0;
foreach (array(
    "SELECT COUNT(*) FROM acc_recognition_request WHERE request_no LIKE 'W11J-%'",
    "SELECT COUNT(*) FROM fin_journal_entries WHERE entry_no LIKE 'W11J-%'",
    "SELECT COUNT(*) FROM acc_period_adjustment WHERE adj_no LIKE 'W11J-%'",
    "SELECT COUNT(*) FROM acc_account_recon WHERE control_source LIKE 'W11J-%'",
    "SELECT COUNT(*) FROM acc_trial_balance_run WHERE run_ref LIKE 'W11J-%'",
    "SELECT COUNT(*) FROM tre_cash_move WHERE note LIKE 'W11%' AND ref_kind IN ('payment','cash_count')",
    "SELECT COUNT(*) FROM tre_cash_count WHERE count_no LIKE 'W11J-%'",
    "SELECT COUNT(*) FROM tre_cash_box WHERE code LIKE 'W11J-%'",
    "SELECT COUNT(*) FROM bank_statements WHERE statement_ref LIKE 'W11J-%'",
    "SELECT COUNT(*) FROM fin_payments WHERE payment_no LIKE 'W11J-%'",
    "SELECT COUNT(*) FROM fin_financial_periods WHERE reopen_reason LIKE 'W11J-%'",
    "SELECT COUNT(*) FROM acc_period_reopen_request WHERE request_no LIKE 'W11J-%'",
) as $q) { $left += (int) $one($q); }

$ins = $conn->prepare("INSERT INTO repair01_w11_journey
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
    printf("  %s %2d %-12s %-52s %s\n", $s[8] ? '✔' : '✘', $no, $s[0],
        mb_substr($s[1], 0, 52), mb_substr($s[5], 0, 48));
}
$consumers = count(array_unique(array_column($ST, 3)));
$noEffect = 0;
foreach ($ST as $s) { if (trim($s[6]) === '' || $s[6] === '—') { $noEffect++; } }

echo str_repeat('─', 118) . "\n";
printf("رحلةُ الإقفال: %d/%d  ·  أشواط %d  ·  مستهلكونَ متمايزون %d  ·  بلا أثرٍ تجاريٍّ %d  ·  أثرٌ باقٍ %d\n",
    $ok, $no, count(array_unique(array_column($ST, 0))), $consumers, $noEffect, $left);
echo ($ok === $no && $left === 0) ? "الحكم: عبرت ✔\n" : "الحكم: لم تعبر ✘\n";
exit(($ok === $no && $left === 0) ? 0 : 1);
