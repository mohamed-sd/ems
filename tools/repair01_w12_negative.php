<?php
/**
 * tools/repair01_w12_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الثانيةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويُطلَب من البوّابةِ أن تسقط — ثمَّ
 *   تُرجَع الحالة. والحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على رمزِ الحاجبِ لا على عبارتِه**: نصُّ حالةِ الخطأِ يطابق
 *   العبارةَ العربيّةَ فيُخضِرُّ كذبًا — فالالتقاطُ على `✘ W12-nn`.
 *
 * ◆ **وصنفٌ ثانٍ: ما يمنعه المخطَّط** (`DB_REFUSED`). قيدُ القاعدةِ لا يُكسَر
 *   من حسابِ التطبيق — فيُختبَر بالاتّجاهِ المعاكس: **تُحاوَل الكتابةُ المخالفةُ
 *   ويُشترط أن تُرَدّ**. وهذا فحصٌ سلبيٌّ للقيدِ لا إعفاءٌ منه، والفرقُ يُعلَن
 *   بعددِه ولا يُخلَط بعددِ الحواجبِ الموقظة.
 *
 * ◆ **وصنفٌ ثالث: ما تمنعه بنيةُ ملفّ** — يُشوَّه ثمَّ يُرجَع بايتًا ببايت،
 *   والإرجاعُ يُتحقَّق منه بالمقارنة.
 *
 * التشغيل: php tools/repair01_w12_negative.php
 * الخروج : 0 كلُّ الحواجبِ يقظة · 1 حاجبٌ أعمى أو إرجاعٌ فاشل
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn'];
$conn->set_charset('utf8mb4');
$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) {
    $r = @$conn->query($sql); if (!$r) { return null; }
    $x = $r->fetch_row(); return $x ? $x[0] : null;
};

$PHP  = PHP_BINARY;
$GATE = $ROOT . '/tools/repair01_w12_gate.php';
$SVC  = $ROOT . '/app/Services/Financing/FinancingCycleService.php';
$SCR  = $ROOT . '/Financing/fin_contract_close.php';
$VIEW = $ROOT . '/Financing/w12_view.php';

/** يشغّل البوّابةَ ويعيد [رمزُ الخروج، أيُّ حاجبٍ سقط] */
function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W12-') !== false && preg_match('/W12-\d+/', $l, $m)) { $failed[] = $m[0]; }
    }
    return array($code, $failed);
}

list($c0, $f0) = run_gate($PHP, $GATE);
if ($c0 !== 0) {
    echo "✘ البوّابةُ ساقطةٌ قبل الكسر (" . implode(',', $f0) . ") — لا معنى لفحصٍ سلبيٍّ على أساسٍ أحمر.\n";
    exit(1);
}
echo "الأساس: البوّابةُ خضراء ✔\n\n";

/* ── قيمٌ تُلتقَط قبل الكسرِ ليكون الإرجاعُ بالقيمةِ لا بالتخمين ────────── */
$svcOrig  = (string) file_get_contents($SVC);
$scrOrig  = (string) file_get_contents($SCR);
$viewOrig = (string) file_get_contents($VIEW);

$scopeReq  = (string) $one("SELECT requirement_id FROM repair01_w12_scope ORDER BY requirement_id LIMIT 1");
$scopeRule = (string) $one("SELECT map_rule FROM repair01_w12_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$scopeOwn  = (string) $one("SELECT owner_verdict FROM repair01_w12_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$sbSid     = (string) $one("SELECT screen_id FROM repair01_w12_sidebar ORDER BY screen_id LIMIT 1");
$sbPerm    = (int) $one("SELECT s6_perm_rows FROM repair01_w12_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbStep    = (int) $one("SELECT s4_cycle_step FROM repair01_w12_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbLabel   = (string) $one("SELECT s2_verdict FROM repair01_w12_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbS5      = (string) $one("SELECT s5_verdict FROM repair01_w12_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbS4      = (string) $one("SELECT s4_verdict FROM repair01_w12_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$growSid   = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE origin = 'W12' ORDER BY screen_id LIMIT 1");
$growRoute = (string) $one("SELECT route FROM repair01_screen_registry WHERE screen_id = '" . $esc($growSid) . "'");
$stEntity  = (string) $one("SELECT entity FROM repair01_w12_states WHERE allowed = 0 ORDER BY entity LIMIT 1");
$stFrom    = (string) $one("SELECT from_state FROM repair01_w12_states WHERE allowed = 0
                             AND entity = '" . $esc($stEntity) . "' ORDER BY from_state LIMIT 1");
$stWhy     = (string) $one("SELECT forbid_why FROM repair01_w12_states WHERE allowed = 0
                             AND entity = '" . $esc($stEntity) . "' AND from_state = '" . $esc($stFrom) . "' LIMIT 1");
$sodKey    = 'fin.order.approve';
$sodEnf    = (string) $one("SELECT enforced_by FROM repair01_w12_sod WHERE process_key = '$sodKey'");
$evCode    = 'fin.final.closed';
$evCons    = (string) $one("SELECT consumer_list FROM repair01_events
                             WHERE event_code = '$evCode' AND wave = 'W12'");
$dictCode  = (string) $one("SELECT raw_code FROM repair01_w6_code_dict
                             WHERE src_ref LIKE 'RPR-W12%' ORDER BY raw_code LIMIT 1");
$dictAr    = (string) $one("SELECT display_ar FROM repair01_w6_code_dict
                             WHERE raw_code = '" . $esc($dictCode) . "'");
$thKey     = 'FIN_CLOSE_LAG_DAYS';
$thWhy     = (string) $one("SELECT why FROM repair01_w12_thresholds WHERE threshold_key = '$thKey'");
$layKey    = (string) $one("SELECT capability_key FROM repair01_w12_layers ORDER BY capability_key LIMIT 1");
$layWhy    = (string) $one("SELECT why FROM repair01_w12_layers WHERE capability_key = '" . $esc($layKey) . "'");
$decWhy    = (string) $one("SELECT rationale FROM repair01_w12_decisions WHERE decision_id = 'W12-D-01'");
$d07Why    = (string) $one("SELECT rationale FROM repair01_w12_decisions WHERE decision_id = 'W12-D-07'");
$d09Rows   = (int) $one("SELECT scope_rows FROM repair01_w12_decisions WHERE decision_id = 'W12-D-09'");
$fixKey    = (string) $one("SELECT fix_key FROM repair01_w12_fixes ORDER BY fix_key LIMIT 1");
$fixRev    = (string) $one("SELECT revealed_by FROM repair01_w12_fixes WHERE fix_key = '" . $esc($fixKey) . "'");
$gapId     = (int) $one("SELECT id FROM repair01_target_gaps WHERE unit = 'DEP-03' AND wave_stage = 'W12'
                          ORDER BY id LIMIT 1");
$gapBc     = (string) $one("SELECT built_counterpart FROM repair01_target_gaps WHERE id = $gapId");
$conKey    = 'w12.balance.capital';
$conPurp   = (string) $one("SELECT purpose FROM fin_close_consumption WHERE consumer_key = '$conKey' LIMIT 1");
$company   = (int) $one("SELECT company_id FROM financing_operations
                          GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
$opId      = (int) $one("SELECT op_id FROM financing_operations WHERE company_id = $company ORDER BY op_id LIMIT 1");
$fin       = (int) $one("SELECT financier_entity_id FROM financing_operations WHERE op_id = $opId");

if ($scopeReq === '' || $sbSid === '' || $growSid === '' || $stEntity === ''
    || $dictCode === '' || $company <= 0 || $opId <= 0 || $gapId <= 0 || $layKey === '') {
    echo "✘ أرضيّةٌ ناقصةٌ للكسر — شغّلْ tools/repair01_w12_apply.php أوّلًا\n";
    exit(1);
}

$NEG = 'W12NEG';

/* ══════════════════════════════════════════════════════════════════════════
   حالاتُ الكسر — لكلٍّ: الحاجبُ المتوقَّعُ سقوطُه · كسرٌ · إرجاع
   ══════════════════════════════════════════════════════════════════════════ */
$CASES = array(
    array('W12-01', 'نزعُ قاعدةِ الربطِ عن متطلَّبٍ في النطاق',
        "UPDATE repair01_w12_scope SET map_rule = '' WHERE requirement_id = '" . $esc($scopeReq) . "'",
        "UPDATE repair01_w12_scope SET map_rule = '" . $esc($scopeRule) . "'
           WHERE requirement_id = '" . $esc($scopeReq) . "'"),

    array('W12-02', 'وسمُ مِرساةٍ مبنيّةٍ بأنّها ليست على القرص',
        "UPDATE repair01_screen_registry SET on_disk = 0 WHERE route = 'Financing/fin_contract_close.php'",
        "UPDATE repair01_screen_registry SET on_disk = 1 WHERE route = 'Financing/fin_contract_close.php'"),

    array('W12-03', 'وسمُ سطحٍ بمالكٍ غيرِ مالكِ متطلَّبِه',
        "UPDATE repair01_w12_scope SET owner_verdict = 'MISMATCH'
           WHERE requirement_id = '" . $esc($scopeReq) . "'",
        "UPDATE repair01_w12_scope SET owner_verdict = '" . $esc($scopeOwn) . "'
           WHERE requirement_id = '" . $esc($scopeReq) . "'"),

    array('W12-04', 'نزعُ حكمِ خطوةٍ من خطواتِ السايدبارِ السبع',
        "UPDATE repair01_w12_sidebar SET s5_verdict = '' WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w12_sidebar SET s5_verdict = '" . $esc($sbS5) . "'
           WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W12-05', 'إعادةُ انحرافِ الاسمِ بعد تصحيحِه',
        "UPDATE repair01_w12_sidebar SET s2_verdict = 'LABEL_DRIFT' WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w12_sidebar SET s2_verdict = '" . $esc($sbLabel) . "'
           WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W12-06', 'نزعُ منحِ الصلاحيةِ عن سطحٍ في النطاق',
        "UPDATE repair01_w12_sidebar SET s6_perm_rows = 0 WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w12_sidebar SET s6_perm_rows = $sbPerm WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W12-07', 'تغييرُ موضعِ السطحِ من الدورةِ في الدفترِ دون المُعادِ اشتقاقُه',
        "UPDATE repair01_w12_sidebar SET s4_cycle_step = " . ($sbStep + 77) . "
           WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w12_sidebar SET s4_cycle_step = $sbStep WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W12-08', 'نزعُ ختمِ الموجةِ عن سطحِ نموّ',
        "UPDATE repair01_screen_registry SET origin = 'GROWN' WHERE screen_id = '" . $esc($growSid) . "'",
        "UPDATE repair01_screen_registry SET origin = 'W12' WHERE screen_id = '" . $esc($growSid) . "'"),

    array('W12-09a', 'دسُّ صنفٍ غريبٍ في جدولِ الإقفالِ التعاقديّ',
        array("SET @old_ck = @@SESSION.check_constraint_checks",
              "SET SESSION check_constraint_checks = 0",
              "INSERT INTO fin_contract_close
                  (company_id, close_kind, close_code, op_id, entity_id, contract_period_no,
                   period_start, period_end, currency)
                VALUES ($company, 'MONTHLY', '$NEG-K', $opId, $fin, 1, CURDATE(), CURDATE(), 'SDG')",
              "SET SESSION check_constraint_checks = 1"),
        "DELETE FROM fin_contract_close WHERE close_code = '$NEG-K'"),

    array('W12-09b', 'تسجيلُ غرضٍ واحدٍ يقرأ صنفَي إقفالٍ معًا',
        "INSERT INTO fin_close_consumption
            (consumer_key, consumer_surface, close_kind, purpose, read_table, why, src_ref)
          VALUES ('$conKey', 'x', 'MONTHLY', '" . $esc($conPurp) . "', 'fin_monthly_close',
                  '$NEG', '$NEG')",
        "DELETE FROM fin_close_consumption WHERE src_ref = '$NEG'"),

    array('W12-10a', 'دسُّ صفٍّ تاريخيٍّ في جدولِ نموذجِ المستقبل',
        array("SET SESSION check_constraint_checks = 0",
              "INSERT INTO fin_payment_order
                  (company_id, source_kind, order_code, op_id, entity_id, requested_at,
                   requested_by, requested_amount, currency, state)
                VALUES ($company, 'LEGACY', '$NEG-P', $opId, $fin, NOW(), 1, 10, 'SDG', 'requested')",
              "SET SESSION check_constraint_checks = 1"),
        "DELETE FROM fin_payment_order WHERE order_code = '$NEG-P'"),

    array('W12-10b', 'إعلانُ قدرةٍ خُفِّضت لتناسبَ الطبقةَ التاريخيّة',
        "UPDATE repair01_w12_layers SET constrained_by_legacy = 1, future_required = 0,
                legacy_can_supply = 0 WHERE capability_key = '" . $esc($layKey) . "'",
        "UPDATE repair01_w12_layers SET constrained_by_legacy = 0
           WHERE capability_key = '" . $esc($layKey) . "'"),

    array('W12-11', 'تغييرُ عددِ المِرساةِ بلا كيانٍ في قرارِها',
        "UPDATE repair01_w12_decisions SET scope_rows = " . ($d09Rows + 5) . "
           WHERE decision_id = 'W12-D-09'",
        "UPDATE repair01_w12_decisions SET scope_rows = $d09Rows WHERE decision_id = 'W12-D-09'"),

    array('W12-16', 'كسرُ سلسلةِ ترحيلِ الرصيدِ بين فترتَين تعاقديّتَين',
        array("INSERT INTO fin_contract_close
                  (company_id, close_kind, close_code, op_id, entity_id, contract_period_no,
                   period_start, period_end, currency, open_principal, close_principal, state, prepared_by)
                VALUES ($company, 'CONTRACTUAL', '$NEG-R1', $opId, $fin, 91,
                        '2019-01-01', '2019-03-31', 'SDG', 0, 100, 'prepared', 1)",
              "INSERT INTO fin_contract_close
                  (company_id, close_kind, close_code, op_id, entity_id, contract_period_no,
                   period_start, period_end, currency, open_principal, close_principal, state, prepared_by)
                VALUES ($company, 'CONTRACTUAL', '$NEG-R2', $opId, $fin, 92,
                        '2019-04-01', '2019-06-30', 'SDG', 999, 999, 'prepared', 1)"),
        "DELETE FROM fin_contract_close WHERE close_code IN ('$NEG-R1','$NEG-R2')"),

    array('W12-17', 'اعتمادُ إقفالٍ شهريٍّ يعلن ضمًّا لا رابطَ له',
        "INSERT INTO fin_monthly_close
            (company_id, close_kind, close_code, op_id, entity_id, accounting_month,
             month_start, month_end, currency, contract_closes_n, state, prepared_by, approved_by, approved_at)
          VALUES ($company, 'MONTHLY', '$NEG-M', $opId, $fin, '2019-01',
                  '2019-01-01', '2019-01-31', 'SDG', 3, 'approved', 1, 2, NOW())",
        "DELETE FROM fin_monthly_close WHERE close_code = '$NEG-M'"),

    array('W12-18', 'اعتمادُ إقفالٍ نهائيٍّ فوق استحقاقٍ مفتوح',
        array("SET SESSION check_constraint_checks = 0",
              "INSERT INTO fin_final_close
                  (company_id, close_kind, close_code, op_id, entity_id, currency,
                   open_dues_n, open_deviations_n, clearance_doc_ref, last_periodic_close_id,
                   state, prepared_by, approved_by, approved_at)
                VALUES ($company, 'FINAL', '$NEG-F', 999999, $fin, 'SDG',
                        4, 0, '$NEG-CLR', 1, 'approved', 1, 2, NOW())",
              "SET SESSION check_constraint_checks = 1"),
        "DELETE FROM fin_final_close WHERE close_code = '$NEG-F'"),

    array('W12-21', 'وسمُ أمرٍ منفَّذٍ بلا طلبِ اعترافٍ',
        array("SET SESSION check_constraint_checks = 0",
              "INSERT INTO fin_payment_order
                  (company_id, source_kind, order_code, op_id, entity_id, requested_at, requested_by,
                   requested_amount, currency, approved_amount, approved_by, approved_at, state,
                   executed_on, executed_amount, method, bank_ref, recognition_request_id)
                VALUES ($company, 'FUTURE', '$NEG-X', $opId, $fin, NOW(), 1, 10, 'SDG',
                        10, 2, NOW(), 'executed', CURDATE(), 10, 'bank', '$NEG-B', 0)",
              "SET SESSION check_constraint_checks = 1"),
        "DELETE FROM fin_payment_order WHERE order_code = '$NEG-X'"),

    array('W12-22', 'نزعُ سببِ المنعِ عن انتقالٍ ممنوعٍ صراحةً',
        array("SET SESSION check_constraint_checks = 0",
              "UPDATE repair01_w12_states SET forbid_why = ''
                WHERE entity = '" . $esc($stEntity) . "' AND from_state = '" . $esc($stFrom) . "'
                  AND allowed = 0",
              "SET SESSION check_constraint_checks = 1"),
        "UPDATE repair01_w12_states SET forbid_why = '" . $esc($stWhy) . "'
           WHERE entity = '" . $esc($stEntity) . "' AND from_state = '" . $esc($stFrom) . "' AND allowed = 0"),

    array('W12-23', 'تغييرُ رمزِ الردِّ الذي يُنفِّذ تركيبةً ممنوعة',
        array("SET SESSION check_constraint_checks = 0",
              "UPDATE repair01_w12_sod SET enforced_by = '$NEG' WHERE process_key = '$sodKey'",
              "SET SESSION check_constraint_checks = 1"),
        "UPDATE repair01_w12_sod SET enforced_by = '" . $esc($sodEnf) . "' WHERE process_key = '$sodKey'"),

    array('W12-24', 'إبهامُ قائمةِ مستهلكي حدثٍ',
        "UPDATE repair01_events SET consumer_list = 'كل المستهلكين'
           WHERE event_code = '$evCode' AND wave = 'W12'",
        "UPDATE repair01_events SET consumer_list = '" . $esc($evCons) . "'
           WHERE event_code = '$evCode' AND wave = 'W12'"),

    array('W12-24b', 'تعطيلُ مشتركِ حدثٍ من أحداثِ النطاق',
        "UPDATE event_consumers SET active = 0 WHERE event_name = '$evCode'",
        "UPDATE event_consumers SET active = 1 WHERE event_name = '$evCode'"),

    /* ⚠ **حزامانِ لا حزام**: `chk_fcon_appr` يمنع اعتمادَ الإقفالِ من مُعِدِّه
         **في القاعدة**، فالكسرُ من حسابِ التطبيقِ لا يقع أصلًا ويُقرأ الحاجبُ
         «لم يُختبَر» لا «يقظ». والقياسُ في `W12-25` **حزامٌ ثانٍ** يلتقط صفًّا
         دخل والقيدُ معطَّلٌ — أو صفًّا سبق القيدَ. فيُعطَّل الحزامُ الأوّلُ عمدًا
         ليُختبَر الثاني، ⛔ **لا يُحذَف القيدُ ولا يُخفَّف**. والقيدُ نفسُه
         مُختبَرٌ في صنفِ `DB_REFUSED` أدناه بالاتّجاهِ المعاكس. */
    array('W12-25', 'اعتمادُ إقفالٍ تعاقديٍّ من مُعِدِّه نفسِه (‏والقيدُ معطَّلٌ عمدًا)',
        array("SET SESSION check_constraint_checks = 0",
              "INSERT INTO fin_contract_close
                  (company_id, close_kind, close_code, op_id, entity_id, contract_period_no,
                   period_start, period_end, currency, rollforward_ok, state,
                   prepared_by, approved_by, approved_at)
                VALUES ($company, 'CONTRACTUAL', '$NEG-S', $opId, $fin, 95,
                        '2018-01-01', '2018-03-31', 'SDG', 1, 'approved', 7, 7, NOW())",
              "SET SESSION check_constraint_checks = 1"),
        "DELETE FROM fin_contract_close WHERE close_code = '$NEG-S'"),

    array('W12-25b', 'اعتمادُ إقفالٍ شهريٍّ بلا معتمِدٍ (‏والقيدُ معطَّلٌ عمدًا)',
        array("SET SESSION check_constraint_checks = 0",
              "INSERT INTO fin_monthly_close
                  (company_id, close_kind, close_code, op_id, entity_id, accounting_month,
                   month_start, month_end, currency, contract_closes_n, state, prepared_by, approved_by)
                VALUES ($company, 'MONTHLY', '$NEG-S2', $opId, $fin, '2018-02',
                        '2018-02-01', '2018-02-28', 'SDG', 0, 'approved', 3, 0)",
              "SET SESSION check_constraint_checks = 1"),
        "DELETE FROM fin_monthly_close WHERE close_code = '$NEG-S2'"),

    array('W12-26', 'نزعُ الموفّى عن فجوةٍ من فجواتِ النطاق',
        "UPDATE repair01_target_gaps SET built_counterpart = '' WHERE id = $gapId",
        "UPDATE repair01_target_gaps SET built_counterpart = '" . $esc($gapBc) . "' WHERE id = $gapId"),

    array('W12-26b', 'إسنادُ فجوةٍ إلى ملفٍّ غيرِ موجودٍ على القرص',
        "UPDATE repair01_target_gaps SET built_counterpart = 'Financing/no_such_file.php' WHERE id = $gapId",
        "UPDATE repair01_target_gaps SET built_counterpart = '" . $esc($gapBc) . "' WHERE id = $gapId"),

    array('W12-27', 'نزعُ المسمّى العربيِّ عن رمزٍ يُعرَض في النطاق',
        "UPDATE repair01_w6_code_dict SET display_ar = '' WHERE raw_code = '" . $esc($dictCode) . "'",
        "UPDATE repair01_w6_code_dict SET display_ar = '" . $esc($dictAr) . "'
           WHERE raw_code = '" . $esc($dictCode) . "'"),

    array('W12-28', 'نزعُ علّةِ قرارٍ من قراراتِ المرحلة',
        "UPDATE repair01_w12_decisions SET rationale = '' WHERE decision_id = 'W12-D-01'",
        "UPDATE repair01_w12_decisions SET rationale = '" . $esc($decWhy) . "'
           WHERE decision_id = 'W12-D-01'"),

    array('W12-28b', 'إسنادُ إصلاحٍ إلى متطلَّبٍ خارجَ المرحلة',
        "UPDATE repair01_w12_fixes SET revealed_by = 'ACC-99' WHERE fix_key = '" . $esc($fixKey) . "'",
        "UPDATE repair01_w12_fixes SET revealed_by = '" . $esc($fixRev) . "'
           WHERE fix_key = '" . $esc($fixKey) . "'"),

    array('W12-30', 'تسجيلُ حكمٍ يخالف ما يقيسه ماسحُ W03',
        "UPDATE repair01_key_alias SET verdict = 'ALTERNATE_ID'
           WHERE wave_stage = 'W12' AND alias_kind = 'LABEL_ONLY'",
        "UPDATE repair01_key_alias SET verdict = 'SEED_NO_REFERENT'
           WHERE wave_stage = 'W12' AND alias_kind = 'LABEL_ONLY'"),

    array('W12-30b', 'نزعُ مرشَّحِ نموِّ المرحلةِ من دفترِ W03',
        "DELETE FROM repair01_key_alias WHERE wave_stage = 'W12' AND alias_kind = 'LABEL_ONLY'",
        "INSERT INTO repair01_key_alias
            (key_code,alias_table,alias_column,alias_kind,verdict,verdict_rule,verdict_why,
             rows_total,rows_seed,rows_resolvable,link_column,rows_linked,resolved_at,wave_stage,src_ref)
          SELECT 'Person_ID','fin_financier_contact','person_name','LABEL_ONLY','SEED_NO_REFERENT',
                 'MEASURED_EMPTY_TABLE','جدول فارغ — لا صف يعرف حقيقة أم بنص',
                 0,0,0,'',0,NULL,'W12','قياسٌ حيّ من ماسح W03: fin_financier_contact.person_name'
           FROM DUAL"),

    array('W12-15b', 'نزعُ إعلانِ الخلاءِ عن قرارِ المرحلة',
        "UPDATE repair01_w12_decisions SET rationale = '' WHERE decision_id = 'W12-D-07'",
        "UPDATE repair01_w12_decisions SET rationale = '" . $esc($d07Why) . "'
           WHERE decision_id = 'W12-D-07'"),
);

$awake = 0; $blind = array(); $revertFail = array();
foreach ($CASES as $c) {
    list($expect, $what, $breakSql, $fixSql) = $c;
    $expectId = preg_replace('/[a-z]$/', '', $expect);
    /* ⚠ **الكسرُ قد يلزمه أكثرُ من عبارة**: صفٌّ تابعٌ لا يُدرَج قبل أبيه،
       وكسرٌ بعبارةٍ واحدةٍ يترك الحالةَ سليمةً — فيُقرأ الحاجبُ يقظًا وهو لم
       يُختبَر. فتُقبَل قائمةُ عباراتٍ ويُشترط نجاحُها كلُّها. */
    $brokeOk = true;
    foreach ((array) $breakSql as $bs) {
        if (@$conn->query($bs) !== true) { $brokeOk = false; break; }
    }
    @$conn->query('SET SESSION check_constraint_checks = 1');
    if (!$brokeOk) {
        echo "  ⚠ تعذّر الكسرُ ($expect · $what): " . $conn->error . "\n";
        $blind[] = $expect . ' (لم يقع الكسر)';
        foreach ((array) $fixSql as $fs) { @$conn->query($fs); }
        continue;
    }
    list($code, $failed) = run_gate($PHP, $GATE);
    $hit = in_array($expectId, $failed, true);
    foreach ((array) $fixSql as $fs) { @$conn->query($fs); }
    list($code2, $failed2) = run_gate($PHP, $GATE);
    if ($code2 !== 0) { $revertFail[] = $expect . ' (' . implode(',', $failed2) . ')'; }
    if ($hit) { $awake++; printf("  ✔ %-9s %-54s سقط ورجع\n", $expect, mb_substr($what, 0, 54)); }
    else { $blind[] = $expect; printf("  ✘ %-9s %-54s **لم يسقط**\n", $expect, mb_substr($what, 0, 54)); }
}

/* ══════════════════════════════════════════════════════════════════════════
   الصنفُ الثاني — ما يمنعه المخطَّطُ: تُحاوَل الكتابةُ ويُشترط الردّ
   ══════════════════════════════════════════════════════════════════════════ */
echo "\nما يمنعه المخطَّطُ — الكتابةُ تُحاوَل ويُشترط الردّ:\n";
$DBCASES = array(
    array('W12-13', 'إقفالٌ تعاقديٌّ بصنفِ الشهريِّ (chk_fcon_kind)',
        "INSERT INTO fin_contract_close
            (company_id, close_kind, close_code, op_id, entity_id, contract_period_no,
             period_start, period_end, currency)
          VALUES ($company, 'MONTHLY', '$NEG-D1', $opId, $fin, 1, CURDATE(), CURDATE(), 'SDG')",
        "DELETE FROM fin_contract_close WHERE close_code = '$NEG-D1'"),
    array('W12-13', 'إقفالٌ تعاقديٌّ بلا رقمِ فترتِه (chk_fcon_period)',
        "INSERT INTO fin_contract_close
            (company_id, close_code, op_id, entity_id, contract_period_no,
             period_start, period_end, currency)
          VALUES ($company, '$NEG-D2', $opId, $fin, 0, CURDATE(), CURDATE(), 'SDG')",
        "DELETE FROM fin_contract_close WHERE close_code = '$NEG-D2'"),
    array('W12-13', 'شهريٌّ لا يبدأ بأوّلِ الشهرِ (chk_fmc_month)',
        "INSERT INTO fin_monthly_close
            (company_id, close_code, op_id, entity_id, accounting_month, month_start, month_end, currency)
          VALUES ($company, '$NEG-D3', $opId, $fin, '2019-05', '2019-05-07', '2019-05-31', 'SDG')",
        "DELETE FROM fin_monthly_close WHERE close_code = '$NEG-D3'"),
    array('W12-13', 'رابطٌ من صنفٍ إلى صنفِه نفسِه (chk_fcl_self)',
        "INSERT INTO fin_close_link
            (company_id, parent_kind, parent_id, child_kind, child_id, link_rule, why)
          VALUES ($company, 'FINAL', 1, 'FINAL', 2, '$NEG', '$NEG')",
        "DELETE FROM fin_close_link WHERE link_rule = '$NEG'"),
    array('W12-13', 'رابطٌ بزوجٍ غيرِ مسموحٍ (chk_fcl_pair)',
        "INSERT INTO fin_close_link
            (company_id, parent_kind, parent_id, child_kind, child_id, link_rule, why)
          VALUES ($company, 'CONTRACTUAL', 1, 'MONTHLY', 2, '$NEG', '$NEG')",
        "DELETE FROM fin_close_link WHERE link_rule = '$NEG'"),
    array('W12-14', 'صفٌّ تاريخيٌّ في نموذجِ أمرِ الدفعِ (chk_fpo_future)',
        "INSERT INTO fin_payment_order
            (company_id, source_kind, order_code, op_id, entity_id, requested_at,
             requested_by, requested_amount, currency, state)
          VALUES ($company, 'LEGACY', '$NEG-D4', $opId, $fin, NOW(), 1, 10, 'SDG', 'requested')",
        "DELETE FROM fin_payment_order WHERE order_code = '$NEG-D4'"),
    array('W12-14', 'أمرٌ معتمَدٌ من طالبِه نفسِه (chk_fpo_sod)',
        "INSERT INTO fin_payment_order
            (company_id, order_code, op_id, entity_id, requested_at, requested_by,
             requested_amount, currency, approved_amount, approved_by, approved_at, state)
          VALUES ($company, '$NEG-D5', $opId, $fin, NOW(), 9, 10, 'SDG', 10, 9, NOW(), 'approved')",
        "DELETE FROM fin_payment_order WHERE order_code = '$NEG-D5'"),
    array('W12-14', 'أمرٌ منفَّذٌ بلا مرجعٍ بنكيٍّ (chk_fpo_exec)',
        "INSERT INTO fin_payment_order
            (company_id, order_code, op_id, entity_id, requested_at, requested_by,
             requested_amount, currency, approved_amount, approved_by, approved_at, state,
             executed_on, executed_amount, method, bank_ref)
          VALUES ($company, '$NEG-D6', $opId, $fin, NOW(), 1, 10, 'SDG', 10, 2, NOW(), 'executed',
                  CURDATE(), 10, 'bank', '')",
        "DELETE FROM fin_payment_order WHERE order_code = '$NEG-D6'"),
    array('W12-14', 'مجمَّعٌ تاريخيٌّ قابلٌ للتخصيصِ (chk_flpa_alloc)',
        "INSERT INTO fin_legacy_payment_aggregate
            (company_id, op_id, evidence_grade, source_row_ref, allocatable)
          VALUES ($company, $opId, 'aggregate', '$NEG-D7', 1)",
        "DELETE FROM fin_legacy_payment_aggregate WHERE source_row_ref = '$NEG-D7'"),
    array('W12-14', 'مجمَّعٌ تاريخيٌّ بلا حجّيّةٍ (chk_flpa_evid)',
        "INSERT INTO fin_legacy_payment_aggregate
            (company_id, op_id, evidence_grade, source_row_ref)
          VALUES ($company, $opId, '', '')",
        "DELETE FROM fin_legacy_payment_aggregate WHERE op_id = $opId AND evidence_grade = ''"),
    array('W12-14', 'تخصيصٌ بلا أمرِ دفعٍ (chk_fpa_order)',
        "INSERT INTO fin_payment_allocation (company_id, order_id, amount, note)
          VALUES ($company, 0, 10, '$NEG')",
        "DELETE FROM fin_payment_allocation WHERE note = '$NEG'"),
    array('W12-18', 'إقفالٌ نهائيٌّ معتمَدٌ بلا إخلاءِ طرفٍ (chk_ffin_appr)',
        "INSERT INTO fin_final_close
            (company_id, close_code, op_id, entity_id, currency, state,
             prepared_by, approved_by, clearance_doc_ref, last_periodic_close_id)
          VALUES ($company, '$NEG-D8', 999998, $fin, 'SDG', 'approved', 1, 2, '', 1)",
        "DELETE FROM fin_final_close WHERE close_code = '$NEG-D8'"),
    array('W12-19', 'عتبةٌ مسجَّلةٌ بلا سببٍ ولا قرارٍ (chk_w12th_why)',
        "UPDATE repair01_w12_thresholds SET why = '' WHERE threshold_key = '$thKey'",
        "UPDATE repair01_w12_thresholds SET why = '" . $esc($thWhy) . "' WHERE threshold_key = '$thKey'"),
    array('W12-10', 'إعلانُ تخفيضٍ على قدرةٍ إلزاميّةٍ (chk_w12layer_free)',
        "UPDATE repair01_w12_layers SET constrained_by_legacy = 1, future_required = 1
           WHERE capability_key = '" . $esc($layKey) . "'",
        "UPDATE repair01_w12_layers SET constrained_by_legacy = 0
           WHERE capability_key = '" . $esc($layKey) . "'"),
    array('W12-22', 'انتقالٌ ممنوعٌ بلا سببٍ (chk_w12st_forbid)',
        "INSERT INTO repair01_w12_states (entity, from_state, to_state, allowed, forbid_why)
          VALUES ('$NEG', 'a', 'b', 0, '')",
        "DELETE FROM repair01_w12_states WHERE entity = '$NEG'"),
    array('W12-23', 'عمليةٌ حرِجةٌ بلا رمزِ ردٍّ (chk_w12sod_full)',
        "INSERT INTO repair01_w12_sod
            (process_key, process_name, initiator_role, approver_role, forbidden_combo,
             enforced_by, authority_rule_id, effective_date)
          VALUES ('$NEG', 'x', 'y', 'z', 'w', '', 'r', CURDATE())",
        "DELETE FROM repair01_w12_sod WHERE process_key = '$NEG'"),
    array('W12-28', 'إصلاحٌ بلا متطلَّبٍ كاشفٍ (chk_w12fix_rev)',
        "INSERT INTO repair01_w12_fixes (fix_key, title, revealed_by) VALUES ('$NEG', 'x', '')",
        "DELETE FROM repair01_w12_fixes WHERE fix_key = '$NEG'"),
);
$refused = 0; $slipped = array();
foreach ($DBCASES as $d) {
    list($gid, $what, $sql, $clean) = $d;
    $ok = @$conn->query($sql);
    if ($ok === true) {
        $slipped[] = $gid . ' ⇐ ' . $what;
        printf("  ✘ %-9s %-58s **مرَّت**\n", $gid, mb_substr($what, 0, 58));
        @$conn->query($clean);
    } else {
        $refused++;
        printf("  ✔ %-9s %-58s رُدَّت\n", $gid, mb_substr($what, 0, 58));
    }
}

/* ══════════════════════════════════════════════════════════════════════════
   الصنفُ الثالث — ما تمنعه بنيةُ ملفٍّ: يُشوَّه ثمَّ يُرجَع بايتًا ببايت
   ══════════════════════════════════════════════════════════════════════════ */
echo "\nما تمنعه بنيةُ ملفٍّ — تشويهٌ ثمَّ إرجاعٌ متحقَّقٌ منه:\n";
$FILECASES = array(
    /* ⚠ **رقمُ العتبةِ يُركَّب ولا يُكتب حرفيًّا هنا**: حمولةُ الكسرِ نصٌّ في هذا
         الملفّ، وكاشفُ `W12-19` يمسح **أدواتِ النطاقِ كلَّها** — ومنها هذه —
         فكتابةُ المقارنةِ حرفيّةً تُسقط البوّابةَ **قبل** أن يبدأ الفحصُ أصلًا،
         ويقرأ القارئُ «أساسًا أحمرَ» لا عطبًا في النطاق. والعلاجُ **تركيبُ
         الرقمِ لا استثناءُ الملفِّ من المسح** (‏المقامُ كاملٌ لا مختار)،
         والكسرُ المزروعُ في الخدمةِ يبقى مقارنةً صلبةً حقيقيّةً تُسقط الحاجب. */
    array('W12-19', 'زرعُ مقارنةِ عتبةٍ صلبةٍ في خدمةِ الدورة', $SVC, $svcOrig,
        function ($src) {
            $lit = (string) (25 * 10000);
            return str_replace('    private static function fail($code, $detail = \'\')',
                "    /* سطرُ كسرٍ مؤقَّتٌ للفحصِ السلبيّ */\n"
              . "    public static function negBreak(\$amount) { return \$amount " . '>' . ' ' . $lit . "; }\n\n"
              . '    private static function fail($code, $detail = \'\')', $src);
        }),
    array('W12-20', 'جعلُ خدمةِ الدورةِ تكتب في دفترِ القيدِ مباشرةً', $SVC, $svcOrig,
        function ($src) {
            return str_replace('    private static function fail($code, $detail = \'\')',
                "    /* سطرُ كسرٍ مؤقَّتٌ للفحصِ السلبيّ */\n"
              . "    public static function negPost(\\mysqli \$c) {\n"
              . "        return \$c->query(\"INSERT INTO fin_journal_entries (memo) VALUES ('x')\");\n"
              . "    }\n\n"
              . '    private static function fail($code, $detail = \'\')', $src);
        }),
    array('W12-02', 'نزعُ مِرساةِ الجدولِ من شاشةِ الإقفالِ التعاقديّ', $SCR, $scrOrig,
        function ($src) {
            return str_replace("'fin_contract_close',", "'fin_contract_close_removed',", $src);
        }),
    array('W12-27', 'قطعُ المُصيِّرِ عن قاموسِ الرموزِ المركزيّ', $VIEW, $viewOrig,
        function ($src) {
            return str_replace('return ems_w7_ar($code, $c);', 'return (string) $code;', $src);
        }),
);
$fileAwake = 0; $fileBlind = array();
foreach ($FILECASES as $fc) {
    list($gid, $what, $path, $orig, $mut) = $fc;
    $broken = $mut($orig);
    if ($broken === $orig) {
        $fileBlind[] = $gid . ' (لم يقع التشويه)';
        printf("  ✘ %-9s %-58s **لم يقع التشويه**\n", $gid, mb_substr($what, 0, 58));
        continue;
    }
    file_put_contents($path, $broken);
    list($code, $failed) = run_gate($PHP, $GATE);
    file_put_contents($path, $orig);
    $restored = ((string) file_get_contents($path) === $orig);
    if (!$restored) { $revertFail[] = $gid . ' (إرجاعُ الملفِّ فشل)'; }
    if (in_array($gid, $failed, true)) {
        $fileAwake++;
        printf("  ✔ %-9s %-58s سقط ورجع\n", $gid, mb_substr($what, 0, 58));
    } else {
        $fileBlind[] = $gid;
        printf("  ✘ %-9s %-58s **لم يسقط**\n", $gid, mb_substr($what, 0, 58));
    }
}

/* ══ الحكمُ النهائيُّ — والبوّابةُ تُعاد قياسًا لا افتراضًا ═══════════════ */
list($cEnd, $fEnd) = run_gate($PHP, $GATE);
echo "\n" . str_repeat('─', 112) . "\n";
printf("الفحصُ السلبيّ: حواجبُ يقظة %d/%d  ·  عمياء %d  ·  قيودُ مخطَّطٍ رُدَّت %d/%d"
     . "  ·  حالاتُ ملفٍّ %d/%d  ·  إرجاعٌ فاشل %d\n",
    $awake, count($CASES), count($blind), $refused, count($DBCASES),
    $fileAwake, count($FILECASES), count($revertFail));
echo 'البوّابةُ بعد الإرجاع: ' . ($cEnd === 0 ? "خضراء ✔\n" : ('ساقطة ✘ ⇐ ' . implode(',', $fEnd) . "\n"));
if ($blind) { echo 'حواجبُ عمياء: ' . implode('، ', $blind) . "\n"; }
if ($fileBlind) { echo 'حالاتُ ملفٍّ عمياء: ' . implode('، ', $fileBlind) . "\n"; }
if ($slipped) { echo 'قيودٌ لم تردّ: ' . implode('، ', $slipped) . "\n"; }
if ($revertFail) { echo 'إرجاعٌ فاشل: ' . implode('، ', $revertFail) . "\n"; }

$allOk = count($blind) === 0 && count($fileBlind) === 0 && count($slipped) === 0
      && count($revertFail) === 0 && $cEnd === 0;
echo $allOk ? "الحكم: كلُّ الحواجبِ يقظة ✔\n" : "الحكم: فيها أعمى أو إرجاعٌ فاشل ✘\n";
exit($allOk ? 0 : 1);
