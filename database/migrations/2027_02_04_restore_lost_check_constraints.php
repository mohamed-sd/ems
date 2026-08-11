<?php
/**
 * 2027_02_04 — ترميمُ 26 قيدَ CHECK فُقدت في إعادةِ بناءِ 2026-08-03
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **طبقةُ منعٍ كاملةٌ سقطت ولم تُعلَن.** أعادت القاعدةُ بناءَها يومَ 2026-08-03
 *   فذهبت قيودُ CHECK، وأُعيد منها **الحديثُ وحدَه** بهجراتٍ لاحقة. والباقي
 *   بقي **مذكورًا في الشيفرةِ والفواحصِ والمواصفاتِ** بلا وجودٍ في القاعدة.
 *
 * ◆ **وهذا أخطرُ من غيابِ قيد**: شيفرةٌ تُصرّح «القاعدةُ تحرس هذا» فلا يُضاف
 *   فحصٌ تطبيقيٌّ يعوّضها. مثالُه المقيس: `M-11` عاش شهرًا وسبعةُ مواضعَ تتكئ
 *   على فراغ (رُمِّم في `2027_02_03`). وهذه الهجرةُ تُنهي العنقودَ.
 *
 * ◆ **والنصوصُ أصليةٌ حرفيًّا لا صياغةً من عندي**، مستخرَجةٌ سطرًا سطرًا من
 *   `database/baseline/auto_pre_up_20260803_084927_equipation_manage.sql` —
 *   نسخةٌ أُخذت قبلَ إعادةِ البناءِ بساعات. **وقيدٌ يُعاد بصياغةٍ مختلفةٍ يحرس
 *   حكمًا آخرَ ويُقرأ ترميمًا** — فلا اجتهادَ في النص.
 *
 * ◆ **وصفرُ مخالفٍ حيٍّ لكلِّ الـ26** — مقيسًا قبل البناء بنفيِ شرطِ كلِّ قيدٍ
 *   على جدولِه (`WHERE NOT (<clause>)`). فلا بيانةَ تُمَسّ، ولا صفَّ يُعدَّل
 *   لإرضاء قيد. (وقيدٌ سابعٌ وعشرون — `chk_nav_door` — له **90 مخالفًا**
 *   فاستُثني وأُعلن: إضافتُه تفشل، وإرضاؤه قرارُ مالكٍ لا قرارُ مُرحِّل.)
 *
 * ◆ والأداتان اللتان قادتا إلى هذا يبقيان حارسَين دائمَين:
 *     `tools/fix_missing_checks.php`          — كلُّ اسمِ قيدٍ تدّعيه الشجرةُ
 *     `tools/fix_lost_constraints_classify.php` — يفصل المفقودَ من وهمِ الباحث
 *
 * ◆ مُتحمِّلٌ للتكرار · ويُجَسُّ **بعدَ الإضافةِ** أن كلَّ قيدٍ حاضرٌ فعلًا،
 *   وتُجَسُّ خمسةٌ ماليةٌ منها **وظيفيًّا في الاتجاهين** ثم تُراجَع.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);   // گوتشا: بلا config ينفُذ افتراضُ الرمي
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');

/* ══ النصوصُ الأصلية — منقولةٌ حرفيًّا من نسخةِ 2026-08-03 08:49 ══════════ */
$C = array(
'ck_container_alloc' => array('op_containers',
  '((`allocated_qty` >= 0) and (`allocated_qty` <= `cap_qty`))'),
/* ◆ **گوتشا مقيسة — النصُّ العربيُّ لا يعود بمُقدِّمِه.**
     نسخةُ `SHOW CREATE TABLE` تكتب الحرفيَّ `_utf8mb4'رئيسية'`، ونقلُه كما هو
     إلى `ADD CONSTRAINT` **يفشل**: `collation_connection = utf8mb4_general_ci`
     و`collation_server = utf8mb4_unicode_ci`، فالمُقدِّمُ يُلزم الحرفيَّ بترتيبٍ
     غيرِ ترتيبِ العمود (`utf8mb4_unicode_ci`) فيُقيَّم الشرطُ **كذبًا** على
     الـ155 صفًّا ذاتِ `level='رئيسية'` — رغم أن 776/776 تجتازه في SELECT.
     والنصُّ الخامُّ بلا مُقدِّمٍ يُقبل **ويميّز** (جُسَّ: يرفض ابنًا بلا أب).
     وهذا القيدُ **وحدَه** من الستةِ والعشرين يحمل نصًّا عربيًّا — ولذلك فشل وحدَه.
     (ولا يُستعمل ترتيبُ الـENUM بدلًا منه: يعمل، لكنه يربط الحكمَ بترتيبِ
      قيمٍ قد يُعاد ترتيبُه في هجرةٍ لاحقةٍ فينقلب المعنى صمتًا.) */
'ck_container_parent' => array('op_containers',
  '(((`level` = \'رئيسية\') and (`parent_id` is null)) or ((`level` <> \'رئيسية\') and (`parent_id` is not null)))'),
'ck_container_consumed' => array('op_containers',
  '((`consumed_qty` >= 0) and (`consumed_qty` <= `cap_qty`))'),
'ck_swap_differs' => array('container_swaps',
  '((`out_ref` is null) or (`in_ref` is null) or (`out_ref` <> `in_ref`))'),
'ck_rotation_cycle' => array('operator_rotations',
  '(`cycle_on_days` > 0)'),
'ck_sa_standby_zero' => array('seat_assignments',
  '((`activation_state` = _utf8mb4\'active\') or ((coalesce(`planned_qty_month`,0) = 0) and (coalesce(`planned_qty_total`,0) = 0)))'),
'ck_chp_superseded' => array('contract_hour_policies',
  '((`policy_state` <> _utf8mb4\'superseded\') or (`superseded_by` is not null))'),
'ck_sadv_inst' => array('supplier_advance_requests',
  '((`installments_count` >= 1) and (`installment_amount` > 0))'),
'ck_je_balanced' => array('fin_journal_entries',
  '(round(`total_debit`,2) = round(`total_credit`,2))'),
'ck_je_fx_pair' => array('fin_journal_entries',
  '(((`fx_rate` is null) and (`base_amount` is null)) or ((`fx_rate` is not null) and (`base_amount` = round((`total_debit` * `fx_rate`),2))))'),
'ck_settlement_invoice_diff' => array('settlements',
  '((`invoice_diff` is null) or (abs(`invoice_diff`) < 0.005) or ((`invoice_diff_reason` is not null) and (char_length(trim(`invoice_diff_reason`)) > 0) and (`invoice_diff_doc_ref` is not null) and (char_length(trim(`invoice_diff_doc_ref`)) > 0)))'),
'ck_led_qty_positive' => array('capacity_consumption_ledger',
  '(`qty` >= 0)'),
'ck_sup_line_standby' => array('supplier_contract_lines',
  '(((`standby_basis` = _utf8mb4\'none\') and (`standby_rate` is null)) or ((`standby_basis` <> _utf8mb4\'none\') and (`standby_rate` is not null) and (`standby_rate` > 0)))'),
'ck_alloc_target' => array('fin_collection_allocations',
  '((`target_ref` > 0) and (((`target_kind` = _utf8mb4\'invoice\') and (`receivable_id` is not null) and (`target_ref` = `receivable_id`)) or ((`target_kind` <> _utf8mb4\'invoice\') and (`receivable_id` is null))))'),
'ck_cg_nature' => array('contract_guarantees',
  '(((`kind` = _utf8mb4\'cash_retention\') and (`nature` = _utf8mb4\'asset\')) or ((`kind` <> _utf8mb4\'cash_retention\') and (`nature` = _utf8mb4\'off_balance\')))'),
'ck_cg_deduct' => array('contract_guarantees',
  '((`deductible_from_claim` = 0) or (`kind` = _utf8mb4\'cash_retention\'))'),
'ck_cg_dates' => array('contract_guarantees',
  '(((`kind` = _utf8mb4\'cash_retention\') and ((`due_release_date` is not null) or (`release_condition` is not null))) or ((`kind` <> _utf8mb4\'cash_retention\') and (`expiry_date` is not null)))'),
'ck_cg_state_reason' => array('contract_guarantees',
  '((`state` not in (_utf8mb4\'released\',_utf8mb4\'called\',_utf8mb4\'expired\')) or (`state_reason` is not null))'),
'ck_cps_treatment' => array('contract_payment_schedule',
  '(((`advance_type` is null) and (`treatment` is null)) or ((`advance_type` = _utf8mb4\'recoverable\') and (`treatment` = _utf8mb4\'liability\')) or ((`advance_type` = _utf8mb4\'non_refundable_booking\') and (`treatment` = _utf8mb4\'revenue\')) or ((`advance_type` = _utf8mb4\'milestone_earned\') and (`treatment` = _utf8mb4\'revenue\')) or ((`advance_type` = _utf8mb4\'mobilization\') and (`treatment` is not null) and (`treatment_basis` is not null)))'),
'ck_cps_advance_link' => array('contract_payment_schedule',
  '((`advance_id` is null) or (`treatment` = _utf8mb4\'liability\'))'),
'ck_pay_fx_pair' => array('fin_payments',
  '(((`fx_rate` is null) and (`base_amount` is null)) or ((`fx_rate` is not null) and (`base_amount` = round((`amount` * `fx_rate`),2))))'),
'ck_cb_actors' => array('contract_baseline',
  '(((`state` <> _utf8mb4\'reviewed\') or ((`reviewed_by` is not null) and (`reviewed_at` is not null))) and ((`state` not in (_utf8mb4\'approved\',_utf8mb4\'locked\')) or ((`approved_by` is not null) and (`approved_at` is not null))) and ((`state` <> _utf8mb4\'locked\') or ((`locked_by` is not null) and (`locked_at` is not null) and (`fingerprint` is not null))))'),
'ck_cle_effects' => array('contract_lifecycle_events',
  '(((`state` = _utf8mb4\'extension\') and (`advance_effect` = _utf8mb4\'continue\') and (`retention_effect` = _utf8mb4\'hold\') and (`unbilled_effect` = _utf8mb4\'bill_cycle\') and (`penalty_effect` = _utf8mb4\'continue\') and (`container_effect` = _utf8mb4\'extend\')) or ((`state` = _utf8mb4\'renewal\') and (`advance_effect` = _utf8mb4\'settle_and_new\') and (`retention_effect` = _utf8mb4\'release_after_grace\') and (`unbilled_effect` = _utf8mb4\'final_claim_old\') and (`penalty_effect` = _utf8mb4\'close_old_start_new\') and (`container_effect` = _utf8mb4\'new_tree\')) or ((`state` = _utf8mb4\'suspension\') and (`advance_effect` = _utf8mb4\'pause_recovery\') and (`retention_effect` = _utf8mb4\'hold\') and (`unbilled_effect` = _utf8mb4\'bill_before_pause\') and (`penalty_effect` = _utf8mb4\'pause_time_not_performance\') and (`container_effect` = _utf8mb4\'suspend\')) or ((`state` = _utf8mb4\'natural_end\') and (`advance_effect` = _utf8mb4\'consume_then_refund\') and (`retention_effect` = _utf8mb4\'release_after_grace\') and (`unbilled_effect` = _utf8mb4\'final_claim\') and (`penalty_effect` = _utf8mb4\'accrue_to_effect_date\') and (`container_effect` = _utf8mb4\'close_readonly\')) or ((`state` = _utf8mb4\'client_fault_end\') and (`advance_effect` = _utf8mb4\'refund_all_after_offset\') and (`retention_effect` = _utf8mb4\'release\') and (`unbilled_effect` = _utf8mb4\'bill_all\') and (`penalty_effect` = _utf8mb4\'company_claims_compensation\') and (`container_effect` = _utf8mb4\'close_with_ref\')) or ((`state` = _utf8mb4\'our_fault_end\') and (`advance_effect` = _utf8mb4\'refund_after_dues\') and (`retention_effect` = _utf8mb4\'may_forfeit\') and (`unbilled_effect` = _utf8mb4\'bill_accepted_only\') and (`penalty_effect` = _utf8mb4\'breach_penalties_capped\') and (`container_effect` = _utf8mb4\'close\')) or ((`state` = _utf8mb4\'pre_start_cancel\') and (`advance_effect` = _utf8mb4\'refund_full\') and (`retention_effect` = _utf8mb4\'release\') and (`unbilled_effect` = _utf8mb4\'none\') and (`penalty_effect` = _utf8mb4\'mobilization_cost_if_article\') and (`container_effect` = _utf8mb4\'cancel\')) or ((`state` = _utf8mb4\'dispute\') and (`advance_effect` = _utf8mb4\'freeze\') and (`retention_effect` = _utf8mb4\'hold\') and (`unbilled_effect` = _utf8mb4\'freeze_disputed_bill_rest\') and (`penalty_effect` = _utf8mb4\'suspend_until_resolution\') and (`container_effect` = _utf8mb4\'suspend\')))'),
'ck_cle_claim_article' => array('contract_lifecycle_events',
  '((`claim_amount` is null) or ((`contract_article` is not null) and (`claim_doc_ref` is not null) and (`claim_currency` is not null) and (`claim_amount` <> 0)))'),
'ck_cle_decision' => array('contract_lifecycle_events',
  '((`state` not in (_utf8mb4\'natural_end\',_utf8mb4\'client_fault_end\',_utf8mb4\'our_fault_end\',_utf8mb4\'pre_start_cancel\')) or (`decision_ref` is not null))'),
'ck_cle_cancel_tree' => array('contract_lifecycle_events',
  '((`container_effect` <> _utf8mb4\'cancel\') or (`state` = _utf8mb4\'pre_start_cancel\'))'),
);

function r4_exists(mysqli $db, $name)
{
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                         WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?");
    $st->bind_param('s', $name); $st->execute();
    $n = (int) $st->get_result()->fetch_row()[0]; $st->close();
    return $n > 0;
}

echo "══ ترميمُ قيودِ CHECK المفقودةِ (فُقدت 2026-08-03) ══\n";
$added = 0; $already = 0; $skipped = array(); $failed = array();

foreach ($C as $name => $spec) {
    list($tbl, $clause) = $spec;
    if (r4_exists($db, $name)) { $already++; echo "  ○ {$name} قائمٌ سلفًا\n"; continue; }

    /* المخالفونَ يُقاسون **قبل** كلِّ إضافةٍ — لا يُبنى على قياسٍ سابق */
    $q = $db->query("SELECT COUNT(*) FROM `{$tbl}` WHERE NOT ({$clause})");
    if (!$q) { $failed[$name] = 'تعذّر قياسُ المخالفين: ' . $db->error; echo "  ✘ {$name}: " . $failed[$name] . "\n"; continue; }
    $bad = (int) $q->fetch_row()[0];
    if ($bad > 0) {
        $skipped[$name] = $bad;
        echo "  ⚠ {$name}: **{$bad} مخالفًا** في `{$tbl}` — يُعلَن ولا تُمَسُّ بيانة\n";
        continue;
    }

    if ($db->query("ALTER TABLE `{$tbl}` ADD CONSTRAINT `{$name}` CHECK {$clause}") === false) {
        $failed[$name] = $db->error;
        echo "  ✘ {$name}: " . mb_substr($db->error, 0, 90) . "\n";
        continue;
    }
    $added++;
    echo "  ✔ {$name}  →  `{$tbl}`\n";
}

/* ══ الجسُّ ①: أكلُّ ما أُضيف حاضرٌ فعلًا؟ ═══════════════════════════════ */
echo "\n── جسٌّ ①: الحضورُ بعد الإضافة\n";
$absent = array();
foreach ($C as $name => $spec) {
    if (isset($skipped[$name]) || isset($failed[$name])) { continue; }
    if (!r4_exists($db, $name)) { $absent[] = $name; }
}
echo count($absent)
    ? '  ✘ أُعلن نجاحُها وهي غائبة: ' . implode(' · ', $absent) . "\n"
    : "  ✔ كلُّ ما أُضيف حاضرٌ في information_schema — لا نجاحَ مُدَّعًى\n";

/* ══ الجسُّ ②: خمسةٌ ماليةٌ في الاتجاهين ═════════════════════════════════ */
echo "\n── جسٌّ ②: تمييزٌ وظيفيٌّ (ثم تراجُع)\n";
$db->begin_transaction();
$probes = array(
    'ck_je_balanced' => array(
        'bad'  => "UPDATE fin_journal_entries SET total_debit = total_debit + 1
                    WHERE id = (SELECT id FROM (SELECT id FROM fin_journal_entries LIMIT 1) x)",
        'why'  => 'قيدٌ غيرُ متوازن',
    ),
    'ck_led_qty_positive' => array(
        'bad'  => "UPDATE capacity_consumption_ledger SET qty = -1
                    WHERE id = (SELECT id FROM (SELECT id FROM capacity_consumption_ledger LIMIT 1) x)",
        'why'  => 'كميةٌ سالبةٌ في دفترِ الاستهلاك',
    ),
    'ck_container_alloc' => array(
        'bad'  => "UPDATE op_containers SET allocated_qty = cap_qty + 1
                    WHERE id = (SELECT id FROM (SELECT id FROM op_containers LIMIT 1) x)",
        'why'  => 'مخصَّصٌ يتجاوز السعة',
    ),
    'ck_rotation_cycle' => array(
        'bad'  => "INSERT INTO operator_rotations (company_id, cycle_on_days) VALUES (4, 0)",
        'why'  => 'دورةُ تناوبٍ بصفرِ يوم',
    ),
    'ck_chp_superseded' => array(
        'bad'  => "UPDATE contract_hour_policies SET policy_state = 'superseded', superseded_by = NULL
                    WHERE id = (SELECT id FROM (SELECT id FROM contract_hour_policies LIMIT 1) x)",
        'why'  => 'سياسةٌ متجاوَزةٌ بلا خلَفٍ يشير إليها',
    ),
);
$probeOk = 0; $probeTotal = 0;
foreach ($probes as $name => $p) {
    if (!r4_exists($db, $name)) { echo "  ○ {$name} غيرُ مضافٍ — لا جسّ\n"; continue; }
    $probeTotal++;
    $res = $db->query($p['bad']);
    /* UPDATE قد ينجح بصفرِ صفٍّ مُتأثِّرٍ إن كان الجدولُ فارغًا — ذاك ليس تمييزًا */
    $rejected = ($res === false);
    if (!$rejected && $db->affected_rows === 0) {
        echo "  ○ {$name}: جدولٌ فارغٌ أو لا صفَّ مُتأثِّر — لا يُقرأ نجاحًا ولا فشلًا\n";
        $probeTotal--;
        continue;
    }
    if ($rejected) { $probeOk++; }
    echo '  ' . ($rejected ? '✔' : '✘') . ' ' . str_pad($name, 28) . $p['why'] . ' ⇒ '
       . ($rejected ? 'مرفوضٌ بنيويًّا' : '**مرَّ — القيدُ لا يعمل**') . "\n";
}
$db->rollback();
echo "  ○ تُراجِع الجسُّ — لا صفَّ بقي\n";

/* ══ الحصيلة ═══════════════════════════════════════════════════════════════ */
$live = (int) $db->query("SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS
                           WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME LIKE 'c%k\_%'")
                 ->fetch_row()[0];
echo "\n── الحصيلة\n";
echo "  أُضيف: {$added} · قائمٌ سلفًا: {$already} · مُعلَنٌ بمخالفين: " . count($skipped)
   . ' · فشل: ' . count($failed) . "\n";
foreach ($skipped as $n => $b) { echo "  ⚠ {$n}: {$b} مخالفًا — قرارُ مالك\n"; }
echo "  تمييزٌ وظيفيٌّ مجسوس: {$probeOk}/{$probeTotal}\n";

$ok = (count($failed) === 0) && (count($absent) === 0) && ($probeTotal === 0 || $probeOk === $probeTotal);
echo "\n" . ($ok
    ? "✅ طبقةُ المنعِ رُمِّمت — والمذكورُ في الشيفرةِ صار موجودًا في القاعدة.\n"
    : "⚠ راجِع المخرجاتِ أعلاه\n");
exit($ok ? 0 : 1);
