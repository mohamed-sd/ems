<?php
/**
 * tools/repair01_w11_negative.php — الفحصُ السلبيُّ لبوّابةِ المرحلةِ الحاديةَ عشرة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الأخضرُ لا يُثبت شيئًا وحدَه**: بوّابةٌ تفحص ما اخترتُ فحصَه تُخضِرُّ على
 *   العدم. فهنا يُكسَر كلُّ حاجبٍ على حِدةٍ ويُطلَب من البوّابةِ أن تسقط — ثمَّ
 *   تُرجَع الحالة. والحاجبُ الذي لا يسقط عند كسرِه **أعمى**.
 *
 * ◆ **والرسوُّ على رمزِ الحاجبِ لا على عبارتِه**: نصُّ حالةِ الخطأِ يطابق
 *   العبارةَ العربيّةَ فيُخضِرُّ كذبًا — فالالتقاطُ على `✘ W11-nn`.
 *
 * ◆ **وصنفٌ ثانٍ من الحالات: ما يمنعه المخطَّطُ** (`DB_REFUSED`). قيدُ القاعدةِ
 *   لا يُكسَر من حسابِ التطبيق — فيُختبَر بالاتّجاهِ المعاكس: **تُحاوَل الكتابةُ
 *   المخالفةُ ويُشترط أن تُرَدّ**. وهذا فحصٌ سلبيٌّ للقيدِ لا إعفاءٌ منه، والفرقُ
 *   يُعلَن بعددِه ولا يُخلَط بعددِ الحواجبِ الموقظة.
 *
 * ◆ **وحالتانِ تُكسَرانِ في الشيفرةِ لا في القاعدة** (`W11-19` و`W11-22`): ما
 *   تمنعه **بنيةُ ملفٍّ** لا يُختبَر إلّا بتشويهِ تلك البنيةِ ثمَّ إرجاعِها
 *   بايتًا ببايت — والإرجاعُ يُتحقَّق منه بالمقارنة.
 *
 * التشغيل: php tools/repair01_w11_negative.php
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

$PHP   = PHP_BINARY;
$GATE  = $ROOT . '/tools/repair01_w11_gate.php';
$ASVC  = $ROOT . '/app/Services/Finance/AccountingCycleService.php';
$CHILD = $ROOT . '/Finance/ar_claim_invoice.php';

/** يشغّل البوّابةَ ويعيد [رمزُ الخروج، أيُّ حاجبٍ سقط] */
function run_gate($PHP, $GATE)
{
    $out = array(); $code = 0;
    exec('"' . $PHP . '" "' . $GATE . '" 2>&1', $out, $code);
    $failed = array();
    foreach ($out as $l) {
        if (mb_strpos($l, '✘ W11-') !== false && preg_match('/W11-\d+/', $l, $m)) { $failed[] = $m[0]; }
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
$aOrig = (string) file_get_contents($ASVC);
$cOrig = (string) file_get_contents($CHILD);

$scopeReq  = (string) $one("SELECT requirement_id FROM repair01_w11_scope ORDER BY requirement_id LIMIT 1");
$scopeRule = (string) $one("SELECT map_rule FROM repair01_w11_scope WHERE requirement_id = '" . $esc($scopeReq) . "'");
$scopeGrp  = (string) $one("SELECT group_name FROM repair01_w11_scope WHERE requirement_id = 'ACC-17'");
$misRows   = (int) $one("SELECT scope_rows FROM repair01_w11_decisions WHERE decision_id = 'W11-D-04'");
$d08Why    = (string) $one("SELECT rationale FROM repair01_w11_decisions WHERE decision_id = 'W11-D-08'");
$sbSid     = (string) $one("SELECT screen_id FROM repair01_w11_sidebar ORDER BY screen_id LIMIT 1");
$sbPerm    = (int) $one("SELECT s6_perm_rows FROM repair01_w11_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbStep    = (int) $one("SELECT s4_cycle_step FROM repair01_w11_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbLabel   = (string) $one("SELECT s2_verdict FROM repair01_w11_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$sbLink    = (int) $one("SELECT s7_linked FROM repair01_w11_sidebar WHERE screen_id = '" . $esc($sbSid) . "'");
$growSid   = (string) $one("SELECT screen_id FROM repair01_screen_registry WHERE origin = 'W11' ORDER BY screen_id LIMIT 1");
$growRoute = (string) $one("SELECT route FROM repair01_screen_registry WHERE screen_id = '" . $esc($growSid) . "'");
$stEntity  = (string) $one("SELECT entity FROM repair01_w11_states WHERE allowed = 0 ORDER BY entity LIMIT 1");
$sodKey    = 'acc.entry.post';
$sodEnf    = (string) $one("SELECT enforced_by FROM repair01_w11_sod WHERE process_key = '$sodKey'");
$evCode    = 'acc.period.closed';
$evCons    = (string) $one("SELECT consumer_list FROM repair01_events
                             WHERE event_code = '$evCode' AND wave = 'W11'");
$dictCode  = (string) $one("SELECT raw_code FROM repair01_w6_code_dict
                             WHERE src_ref LIKE 'RPR-W11%' ORDER BY raw_code LIMIT 1");
$dictAr    = (string) $one("SELECT display_ar FROM repair01_w6_code_dict
                             WHERE raw_code = '" . $esc($dictCode) . "'");
$consolKey = 'w11.group.executive_board';
$consolTag = (string) $one("SELECT tag FROM repair01_w11_consolidated WHERE figure_key = '$consolKey'");
$entryId   = (int) $one("SELECT id FROM fin_journal_entries WHERE state = 'posted' ORDER BY id DESC LIMIT 1");
$entryScope = (string) $one("SELECT entity_scope FROM fin_journal_entries WHERE id = $entryId");
$company   = (int) $one("SELECT company_id FROM fin_financial_periods
                          GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
$decWhy    = (string) $one("SELECT rationale FROM repair01_w11_decisions WHERE decision_id = 'W11-D-01'");
$fixKey    = (string) $one("SELECT fix_key FROM repair01_w11_fixes ORDER BY fix_key LIMIT 1");
$fixRev    = (string) $one("SELECT revealed_by FROM repair01_w11_fixes WHERE fix_key = '" . $esc($fixKey) . "'");
$thKey     = 'ACC_CLOSE_LAG_DAYS';
$thWhy     = (string) $one("SELECT why FROM repair01_w11_thresholds WHERE threshold_key = '$thKey'");
/* ⚠ **الكسرُ يلزمه مفتاحٌ قائم**: `fin_closing_items.period_id` مفتاحٌ أجنبيٌّ،
     والصفرُ يرفضه — فيُقرأ الحاجبُ «لم يقع الكسر» وهو لم يُختبَر. */
$anyPeriod = (int) $one("SELECT id FROM fin_financial_periods WHERE company_id = $company ORDER BY id LIMIT 1");

if ($scopeReq === '' || $sbSid === '' || $growSid === '' || $stEntity === ''
    || $evCode === '' || $dictCode === '' || $entryId <= 0 || $company <= 0) {
    echo "✘ أرضيّةٌ ناقصةٌ للكسر — شغّلْ tools/repair01_w11_apply.php أوّلًا\n";
    exit(1);
}

$NEG_MARK = 'W11NEG';

/* ══════════════════════════════════════════════════════════════════════════
   حالاتُ الكسر — لكلٍّ: الحاجبُ المتوقَّعُ سقوطُه · كسرٌ · إرجاع
   ══════════════════════════════════════════════════════════════════════════ */
$CASES = array(
    array('W11-01', 'نزعُ قاعدةِ الربطِ عن متطلَّبٍ في النطاق',
        "UPDATE repair01_w11_scope SET map_rule = '' WHERE requirement_id = '" . $esc($scopeReq) . "'",
        "UPDATE repair01_w11_scope SET map_rule = '" . $esc($scopeRule) . "'
           WHERE requirement_id = '" . $esc($scopeReq) . "'"),

    array('W11-02', 'وسمُ مِرساةٍ مبنيّةٍ بأنّها ليست على القرص',
        "UPDATE repair01_screen_registry SET on_disk = 0 WHERE route = 'Finance/acc_trial_balance.php'",
        "UPDATE repair01_screen_registry SET on_disk = 1 WHERE route = 'Finance/acc_trial_balance.php'"),

    array('W11-03', 'تغييرُ عددِ المالكِ المخالفِ في قرارِه',
        "UPDATE repair01_w11_decisions SET scope_rows = " . ($misRows + 5) . "
           WHERE decision_id = 'W11-D-04'",
        "UPDATE repair01_w11_decisions SET scope_rows = $misRows WHERE decision_id = 'W11-D-04'"),

    array('W11-04', 'نزعُ حكمِ خطوةٍ من خطواتِ السايدبارِ السبع',
        "UPDATE repair01_w11_sidebar SET s5_verdict = '' WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w11_sidebar SET s5_verdict = 'MENU_ITEM' WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W11-05', 'إعادةُ انحرافِ الاسمِ بعد تصحيحِه',
        "UPDATE repair01_w11_sidebar SET s2_verdict = 'LABEL_DRIFT' WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w11_sidebar SET s2_verdict = '" . $esc($sbLabel) . "'
           WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W11-06', 'تصفيرُ منحِ الصلاحيةِ عن سطحٍ في النطاق',
        "UPDATE repair01_w11_sidebar SET s6_perm_rows = 0 WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w11_sidebar SET s6_perm_rows = $sbPerm WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W11-07', 'إزاحةُ موضعِ السطحِ عن ترتيبِ دورةِ العمل',
        "UPDATE repair01_w11_sidebar SET s4_cycle_step = " . ($sbStep + 3) . "
           WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w11_sidebar SET s4_cycle_step = $sbStep WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W11-08', 'فكُّ ربطِ بندٍ عن مُعرِّفِ شاشتِه المعياريّ',
        "UPDATE repair01_w11_sidebar SET s7_linked = 0 WHERE screen_id = '" . $esc($sbSid) . "'",
        "UPDATE repair01_w11_sidebar SET s7_linked = $sbLink WHERE screen_id = '" . $esc($sbSid) . "'"),

    array('W11-09', 'إخفاءُ ختمِ الموجةِ عن سطحِ نموّ',
        "UPDATE repair01_screen_registry SET origin = 'W11X' WHERE screen_id = '" . $esc($growSid) . "'",
        "UPDATE repair01_screen_registry SET origin = 'W11' WHERE screen_id = '" . $esc($growSid) . "'"),

    array('W11-10', 'نزعُ كلِّ ممنوعٍ صريحٍ عن كيانٍ رئيسيّ',
        "UPDATE repair01_w11_states SET entity = '" . $esc($stEntity . '_neg') . "'
           WHERE entity = '" . $esc($stEntity) . "' AND allowed = 0",
        "UPDATE repair01_w11_states SET entity = '" . $esc($stEntity) . "'
           WHERE entity = '" . $esc($stEntity . '_neg') . "'"),

    array('W11-11', 'تغييرُ رمزِ الردِّ الذي ينفّذ فصلَ الواجبات',
        "UPDATE repair01_w11_sod SET enforced_by = 'NOT_ENFORCED_ANYWHERE'
           WHERE process_key = '$sodKey'",
        "UPDATE repair01_w11_sod SET enforced_by = '" . $esc($sodEnf) . "'
           WHERE process_key = '$sodKey'"),

    array('W11-12', 'إدراجُ فترةٍ محاسبيّةٍ بلا كيانٍ قانونيّ',
        "INSERT INTO fin_financial_periods
            (company_id, fiscal_year, period_type, period_no, start_date, end_date, state,
             posting_allowed, reopen_reason, created_by, created_at)
          VALUES (0, 2098, 'month', 12, '2098-12-01', '2098-12-31', 'open', 1,
                  '$NEG_MARK', 0, NOW())",
        "DELETE FROM fin_financial_periods WHERE reopen_reason = '$NEG_MARK'"),

    array('W11-13', 'إسنادُ قيدٍ إلى طلبِ اعترافٍ لم يُقبَل',
        "INSERT INTO acc_recognition_request
            (company_id, request_no, source_module, source_ref, event_type, amount, currency,
             fx_rate, base_amount, finance_decision, journal_entry_id, idem_key, created_at)
          VALUES ($company, '$NEG_MARK-R', 'sales', '$NEG_MARK', 'revenue', 10, 'SDG',
                  1, 10, 'pending', $entryId, '$NEG_MARK', NOW())",
        "DELETE FROM acc_recognition_request WHERE idem_key = '$NEG_MARK'"),

    array('W11-15', 'فتحُ الترحيلِ على فترةٍ مقفلة',
        "INSERT INTO fin_financial_periods
            (company_id, fiscal_year, period_type, period_no, start_date, end_date, state,
             posting_allowed, reopen_reason, created_by, created_at)
          VALUES ($company, 2098, 'month', 11, '2098-11-01', '2098-11-30', 'closed', 1,
                  '$NEG_MARK', 0, NOW())",
        "DELETE FROM fin_financial_periods WHERE reopen_reason = '$NEG_MARK'"),

    /* ⚠ **والفرقُ المفتوحُ سطرٌ لا عدّاد**: الحاجبُ يبحث عن سطرٍ حالتُه مفتوحةٌ
       تحت جلسةٍ مقفلة، فكسرٌ يرفع العدّادَ وحدَه لا يصنع الحالةَ المخالفة. */
    array('W11-16', 'إقفالُ مطابقةِ حسابٍ وفيها سطرُ فرقٍ مفتوح',
        array("INSERT INTO acc_account_recon
                  (company_id, period_id, account_code, control_source, gl_balance, source_balance,
                   difference, open_diffs, state, note, created_at)
                VALUES ($company, 0, '$NEG_MARK', '$NEG_MARK', 10, 5, 5, 1, 'closed', '$NEG_MARK', NOW())",
              "INSERT INTO acc_account_recon_line
                  (company_id, recon_id, line_kind, cause, amount, responsible_role, action_taken, state)
                SELECT $company, id, 'timing', '$NEG_MARK', 5, 'المحاسب', 'متابعة', 'open'
                  FROM acc_account_recon WHERE account_code = '$NEG_MARK'"),
        array("DELETE FROM acc_account_recon_line WHERE cause = '$NEG_MARK'",
              "DELETE FROM acc_account_recon WHERE account_code = '$NEG_MARK'")),

    array('W11-17', 'فتحُ عهدتَي نثريّةٍ لأمينٍ واحد',
        "INSERT INTO tre_petty_custody
            (company_id, custody_no, holder_id, ceiling_amount, currency, state, note, created_at)
          VALUES ($company, '$NEG_MARK-1', 990099, 100, 'SDG', 'open', '$NEG_MARK', NOW()),
                 ($company, '$NEG_MARK-2', 990099, 100, 'SDG', 'open', '$NEG_MARK', NOW())",
        "DELETE FROM tre_petty_custody WHERE note = '$NEG_MARK'"),

    array('W11-18', 'وسمُ قيدٍ بنطاقِ كيانٍ غيرِ معروف',
        "UPDATE fin_journal_entries SET entity_scope = 'UNKNOWN_SCOPE' WHERE id = $entryId",
        "UPDATE fin_journal_entries SET entity_scope = '" . $esc($entryScope) . "' WHERE id = $entryId"),

    array('W11-20', 'تعطيلُ مستهلكِ حدثٍ في النطاق',
        "UPDATE event_consumers SET active = 0 WHERE event_name = '$evCode'",
        "UPDATE event_consumers SET active = 1 WHERE event_name = '$evCode'"),

    array('W11-21', 'إعادةُ التسوياتِ إلى مجموعةِ الذممِ الدائنة',
        "UPDATE repair01_w11_scope SET group_name = 'الذمم الدائنة' WHERE requirement_id = 'ACC-17'",
        "UPDATE repair01_w11_scope SET group_name = '" . $esc($scopeGrp) . "'
           WHERE requirement_id = 'ACC-17'"),

    array('W11-23', 'رفعُ الحجبِ عن بندِ إقفالٍ بلا سببٍ مكتوب',
        "INSERT INTO fin_closing_items
            (company_id, period_id, step, required, item_state, note, blocks_close,
             exception_reason, created_at)
          VALUES ($company, $anyPeriod, 'reports_issued', 1, 'pending', '$NEG_MARK', 0, '', NOW())",
        "DELETE FROM fin_closing_items WHERE note = '$NEG_MARK'"),

    array('W11-24', 'اعتمادُ طالبِ إعادةِ الفتحِ طلبَه بنفسِه',
        "INSERT INTO acc_period_reopen_request
            (company_id, period_id, request_no, justification, scope_units, authority_rule_id,
             requested_by, state, approved_by, approved_at)
          VALUES ($company, 0, '$NEG_MARK-RO', '$NEG_MARK', 'كل الوحدات', 'AAM-ACC-06',
                  4242, 'approved', 4242, NOW())",
        "DELETE FROM acc_period_reopen_request WHERE request_no = '$NEG_MARK-RO'"),

    array('W11-25', 'نزعُ مسمّى رمزٍ من قاموسِ العرض',
        "UPDATE repair01_w6_code_dict SET display_ar = '' WHERE raw_code = '" . $esc($dictCode) . "'
           AND src_ref LIKE 'RPR-W11%'",
        "UPDATE repair01_w6_code_dict SET display_ar = '" . $esc($dictAr) . "'
           WHERE raw_code = '" . $esc($dictCode) . "'"),

    array('W11-26', 'إدراجُ صفٍّ في السجلِّ بختمٍ غيرِ معروف',
        "INSERT INTO repair01_screen_registry
            (screen_id, screen_file, route, owner_code, on_disk, origin, w2_why, src_ref)
          VALUES ('SCR-9999', '$NEG_MARK.php', 'Finance/$NEG_MARK.php', 'DEP-05', 1,
                  'WILD', '$NEG_MARK', '$NEG_MARK')",
        "DELETE FROM repair01_screen_registry WHERE screen_id = 'SCR-9999'"),

    array('W11-27', 'نزعُ وسمِ الرقمِ المجمَّعِ الذي تقيسه الرحلة',
        "UPDATE repair01_w11_consolidated SET tag = 'SINGLE_ENTITY', entity_count = 1
           WHERE figure_key = '$consolKey'",
        "UPDATE repair01_w11_consolidated SET tag = '" . $esc($consolTag) . "', entity_count = 2
           WHERE figure_key = '$consolKey'"),

    array('W11-28', 'نزعُ علّةِ قرارٍ من قراراتِ المرحلة',
        "UPDATE repair01_w11_decisions SET rationale = '' WHERE decision_id = 'W11-D-01'",
        "UPDATE repair01_w11_decisions SET rationale = '" . $esc($decWhy) . "'
           WHERE decision_id = 'W11-D-01'"),

    array('W11-28b', 'إسنادُ إصلاحٍ إلى متطلَّبٍ خارجَ المرحلة',
        "UPDATE repair01_w11_fixes SET revealed_by = 'PRC-99' WHERE fix_key = '" . $esc($fixKey) . "'",
        "UPDATE repair01_w11_fixes SET revealed_by = '" . $esc($fixRev) . "'
           WHERE fix_key = '" . $esc($fixKey) . "'"),

    array('W11-24b', 'نزعُ إعلانِ الخلاءِ عن قرارِ المرحلة',
        "UPDATE repair01_w11_decisions SET rationale = '' WHERE decision_id = 'W11-D-08'",
        "UPDATE repair01_w11_decisions SET rationale = '" . $esc($d08Why) . "'
           WHERE decision_id = 'W11-D-08'"),
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
    if ($hit) { $awake++; printf("  ✔ %-9s %-52s سقط ورجع\n", $expect, mb_substr($what, 0, 52)); }
    else { $blind[] = $expect; printf("  ✘ %-9s %-52s **لم يسقط**\n", $expect, mb_substr($what, 0, 52)); }
}

/* ══════════════════════════════════════════════════════════════════════════
   الصنفُ الثاني — ما يمنعه المخطَّطُ: تُحاوَل الكتابةُ ويُشترط الردّ
   ══════════════════════════════════════════════════════════════════════════ */
echo "\nما يمنعه المخطَّطُ — الكتابةُ تُحاوَل ويُشترط الردّ:\n";
$DBCASES = array(
    array('W11-14', 'قيدٌ مرحَّلٌ غيرُ متوازنٍ (ck_je_balanced)',
        "INSERT INTO fin_journal_entries
            (company_id, entry_no, posting_date, currency, fx_rate, base_amount,
             total_debit, total_credit, memo, state, created_by, created_at, manual_gov_state)
          VALUES ($company, '$NEG_MARK-UB', CURDATE(), 'SDG', 1, 100, 100, 90,
                  '$NEG_MARK', 'posted', 1, NOW(), 'PRE_GOVERNANCE')",
        "DELETE FROM fin_journal_entries WHERE entry_no = '$NEG_MARK-UB'"),
    array('W11-13', 'طلبُ اعترافٍ مصدرُه الماليّةُ نفسُها (chk_recreq_src)',
        "INSERT INTO acc_recognition_request
            (company_id, request_no, source_module, source_ref, event_type, amount, currency,
             finance_decision, idem_key, created_at)
          VALUES ($company, '$NEG_MARK-F', 'finance', '$NEG_MARK', 'expense', 5, 'SDG',
                  'pending', '$NEG_MARK-F', NOW())",
        "DELETE FROM acc_recognition_request WHERE idem_key = '$NEG_MARK-F'"),
    array('W11-17', 'جردٌ نقديٌّ بلجنةٍ من عضوٍ واحد (chk_cc_committee)',
        "INSERT INTO tre_cash_count
            (company_id, count_no, box_id, count_kind, committee_size, state, created_at)
          VALUES ($company, '$NEG_MARK-C', 1, 'surprise', 1, 'approved', NOW())",
        "DELETE FROM tre_cash_count WHERE count_no = '$NEG_MARK-C'"),
    array('W11-16', 'بندُ فرقٍ بلا مسؤولٍ ولا إجراء (chk_reconl_full)',
        "INSERT INTO acc_account_recon_line
            (company_id, recon_id, line_kind, cause, amount, responsible_role, action_taken, state)
          VALUES ($company, 1, 'timing', '$NEG_MARK', 5, '', '', 'open')",
        "DELETE FROM acc_account_recon_line WHERE cause = '$NEG_MARK'"),
    array('W11-18', 'رقمٌ مجمَّعٌ بلا وسمٍ ولا مالكِ قراءة (chk_w11cons_tag)',
        "INSERT INTO repair01_w11_consolidated
            (figure_key, surface, figure_name, entity_count, tag, tag_label_ar, read_owner, why, src_ref)
          VALUES ('$NEG_MARK', 'x', 'x', 3, '', '', '', '', '$NEG_MARK')",
        "DELETE FROM repair01_w11_consolidated WHERE figure_key = '$NEG_MARK'"),
    array('W11-19', 'عتبةٌ مسجَّلةٌ بلا سببٍ ولا قرار (chk_w11th_why)',
        "UPDATE repair01_w11_thresholds SET why = '' WHERE threshold_key = '$thKey'",
        "UPDATE repair01_w11_thresholds SET why = '" . $esc($thWhy) . "' WHERE threshold_key = '$thKey'"),
    array('W11-24', 'طلبُ إعادةِ فتحٍ بلا مبرّرٍ ولا سلطة (chk_reopen_full)',
        "INSERT INTO acc_period_reopen_request
            (company_id, period_id, request_no, justification, scope_units, authority_rule_id, state)
          VALUES ($company, 0, '$NEG_MARK-X', '', '', '', 'pending')",
        "DELETE FROM acc_period_reopen_request WHERE request_no = '$NEG_MARK-X'"),
);
$refused = 0; $slipped = array();
foreach ($DBCASES as $d) {
    list($gid, $what, $sql, $clean) = $d;
    $ok = @$conn->query($sql);
    if ($ok === true) {
        $slipped[] = $gid . ' ⇐ ' . $what;
        printf("  ✘ %-9s %-56s **مرَّت**\n", $gid, mb_substr($what, 0, 56));
        @$conn->query($clean);
    } else {
        $refused++;
        printf("  ✔ %-9s %-56s رُدَّت\n", $gid, mb_substr($what, 0, 56));
    }
}

/* ══════════════════════════════════════════════════════════════════════════
   الصنفُ الثالث — ما تمنعه بنيةُ ملفٍّ: يُشوَّه ثمَّ يُرجَع بايتًا ببايت
   ══════════════════════════════════════════════════════════════════════════ */
echo "\nما تمنعه بنيةُ ملفٍّ — تشويهٌ ثمَّ إرجاعٌ متحقَّقٌ منه:\n";
$FILECASES = array(
    array('W11-19', 'زرعُ مقارنةِ عتبةٍ صلبةٍ في خدمةِ الدورة', $ASVC, $aOrig,
        function ($src) {
            return str_replace('    private static function fail($code, $detail = \'\')',
                "    /* سطرُ كسرٍ مؤقَّتٌ للفحصِ السلبيّ */\n"
              . "    public static function negBreak(\$amount) { return \$amount > 250000; }\n\n"
              . '    private static function fail($code, $detail = \'\')', $src, $n);
        }),
    array('W11-22', 'نزعُ السجلِّ التابعِ من شاشةِ أبيه', $CHILD, $cOrig,
        function ($src) {
            return str_replace('acc_invoice_line', 'acc_invoice_line_removed', $src);
        }),
);
$fileAwake = 0; $fileBlind = array();
foreach ($FILECASES as $fc) {
    list($gid, $what, $path, $orig, $mut) = $fc;
    $broken = $mut($orig);
    if ($broken === $orig) {
        $fileBlind[] = $gid . ' (لم يقع التشويه)';
        printf("  ✘ %-9s %-56s **لم يقع التشويه**\n", $gid, mb_substr($what, 0, 56));
        continue;
    }
    file_put_contents($path, $broken);
    list($code, $failed) = run_gate($PHP, $GATE);
    file_put_contents($path, $orig);
    $restored = ((string) file_get_contents($path) === $orig);
    if (!$restored) { $revertFail[] = $gid . ' (إرجاعُ الملفِّ فشل)'; }
    if (in_array($gid, $failed, true)) {
        $fileAwake++;
        printf("  ✔ %-9s %-56s سقط ورجع\n", $gid, mb_substr($what, 0, 56));
    } else {
        $fileBlind[] = $gid;
        printf("  ✘ %-9s %-56s **لم يسقط**\n", $gid, mb_substr($what, 0, 56));
    }
}

/* ══ الحكمُ النهائيُّ — والبوّابةُ تُعاد قياسًا لا افتراضًا ═══════════════ */
list($cEnd, $fEnd) = run_gate($PHP, $GATE);
echo "\n" . str_repeat('─', 110) . "\n";
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
