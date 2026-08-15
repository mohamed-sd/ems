<?php
/**
 * tests/_regression.php — مشغّلُ حزمة الانحدار (عدّةٌ لا اختبار).
 * التشغيل: php tests/_regression.php [suite]   —  suite: core (افتراضي) · all
 * يطبع سطرًا لكل حزمة برمز خروجها وعدّادَي النجاح/الفشل المستخرَجَين من مخرجها.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$php = PHP_BINARY;   // نفسُ مفسِّر السطر الحالي — لا مسارَ نسخةٍ مثبَّتًا
$dir = __DIR__;
$suite = isset($argv[1]) ? $argv[1] : 'core';

$core = array(
    'tenant_leak_test', 'audit_trail_test', 'event_publisher_test', 'fes_event_contract_test',
    'period_lock_test', 'journal_head_columns_test', 'base_equivalent_test', 'fx_currency_test',
    'idempotency_resend_test', 'contract_state_machine_test', 'contract_snapshot_test',
    'employee_contract_registry_test', 'employee_contract_amendment_test', 'pay_components_test',
    'incentive_rules_test', 'cost_bearers_test', 'payroll_snapshot_gate_test',
    'payroll_time_path_test', 'payroll_production_path_test', 'payroll_offset_test',
    'payroll_run_cycle_test', 'final_settlement_test', 'employee_settlement_test',
    'pay_policy_state_test', 'operator_due_policy_test', 'timesheet_fanout_test',
    'attribution_test', 'effect_fanout_test',
    'transfer_tariff_test', 'depreciation_event_test', 'periodic_events_test', 'bank_reconciliation_test', 'rfq_cycle_test', 'contract_sites_test', 'contract_lines_test', 'contract_monthly_plan_test', 'contract_resource_plan_test', 'contract_payment_schedule_test', 'contract_guarantees_test', 'allocation_targets_test', 'three_currencies_test', 'plan_actual_link_test', 'contract_baseline_test', 'lifecycle_economics_test', 'commercial_board_test', 'daily_plan_test', 'dues_source_doc_test', 'settlement_test', 'settlement_invoice_close_test',
    'supplier_advances_test', 'supplier_statement_test', 'supplier_rules_test',
    'supplier_capacity_test', 'supplier_evaluation_test', 'supplier_closure_test',
    'supplier_documents_test', 'tax_invoice_test', 'client_statement_test',
    'collection_control_test', 'claim_dispute_test', 'claims_test', 'unified_nav_test',
    'csrf_client_helper_test', 'fin26_role_test',
    /* INJ-0587 — رقمُ بلاطةِ «موافقاتي» = صفوفُ صندوقِها، على HTML لا على
       الدالتين وحدَهما؛ ويُثبِت عدمَ خوائه بزرعِ رابطٍ موجَّهٍ بدورٍ. */
    'approvals_inbox_parity_test',
    /* منشأُ مراجعةِ السعرِ مُصرَّحٌ — فحارسُ «من أنشأ لا يعتمد» لا يسكت على
       نُلِّ المُنشئ، ولا يُسجَّل اعتمادٌ بلا معتمِدٍ مُعرَّف. */
    'price_revision_origin_test',
    /* سلسلةُ «سعرِ اليوم» من طرفٍ إلى طرف — قرارُ المالك 2026-08-12: الماليةُ
       تُسعّر يوميًّا، وسعرُ المعاملةِ يتبعُ يومَها، والسريانُ يومُه نفسُه. */
    'daily_pricing_chain_test',
    /* شاشةُ التسعيرِ اليوميِّ للمالية: مسجَّلةٌ ومنحُها محدودٌ، والماليةُ تُسعّر
       ولا تكتب بندَ عقدٍ — والقياسُ على HTTP بثلاثةِ أدوار. */
    'daily_pricing_screen_test',
    /* حارسُ فروقِ المطابقةِ البنكيةِ في إقفالِ الفترة: كان ميتًا بخمسةِ أسماءٍ
       خاطئةٍ فلم يشتعل قطُّ — ويُثبِت الفاحصُ اشتعالَه بزرعِ فرقٍ حقيقيٍّ في
       موضعَيه معًا ثم رفعِه. */
    'period_guard_bank_diff_test',
    /* سلامةُ أسطرِ الكشفِ البنكيّ: لا سندٌ يُطابَق مرتين ولا مرجعٌ يتيمٌ (بقيدَين
       يُجَسّان في الاتجاهين) · ولا عملةٌ مغروزةٌ · والمانفستُ مصدرٌ واحدٌ للمحرَّم. */
    'bank_lines_integrity_test',
    /* FIXC-0002 (P0) و FIXC-0008 — أوّلُ شواهدَ مبنيةٍ لأحكامِ حزمةِ الماليةِ
       بعينِها: سايدبارُ الأدوارِ 31·32·33 غيرُ فارغٍ بمجموعاتِه ومراحلِه ·
       وصفرُ صفِّ تنقّلٍ ميتٍ من 1,569 رابطًا. */
    'fixc_finance_nav_test',
    /* INJ-0149 — عقدُ رمزِ خروجِ هذا المُشغِّلِ نفسِه: صفرٌ عند النجاحِ وغيرُ صفرٍ
       عند سقوطِ اختبارٍ — مُثبَتٌ بجولةٍ حمراءَ حقيقيةٍ بمدخلِ `only`. */
    'regression_exit_contract_test',
    /* INJ-0239 — سقّاطةُ دوالِّ الحراسةِ الميتة: كان الختمُ يقول «كلُّ حارسٍ متبنًّى»
       و49 دالةً ميتةٌ تحته. صار يقول الحقيقتين، وتُحجَب البوابةُ عند دالةٍ ميتةٍ
       جديدةٍ — مُثبَتٌ بزرعِ دالةٍ في حارسٍ حيٍّ ثم استعادتِه بايتًا ببايت. */
    'guard_adoption_ratchet_test',
    /* INJ-0139 — أثرُ التدقيقِ في وحدةِ التشغيل: كانت Operations/ بصفرِ نداءٍ
       للمُوصِلِ المشتركِ بينما 259 ملفًّا تناديه. والفعلُ يقع عبر الشاشةِ بـHTTP،
       ويُشترط صفٌّ واحدٌ بالمعرّفِ والقيمتين، وصفرُ ضوضاءٍ عند اللاتغيير. */
    'operations_audit_trail_test',
    'supplier_audit_trail_test',
    'dept_space_screens_test',
    /* INJ-0341 — شارةُ «الأدنى» في مقارنةِ العروضِ بالمعادلِ الموحَّدِ لا بالسعرِ
       الخام: عرضٌ بعملةٍ منخفضةِ القيمةِ كان يُوسَم الأدنى وهو الأغلى فعلًا،
       و`<=` تمنحها لكلِّ المتساوين. والحالةُ مزروعةٌ بأرقامِ القاعدةِ الحيّة. */
    'rfq_lowest_badge_test',
    /* حملةُ الواجهةِ · الموجةُ الأولى — ستةُ عيوبٍ جذرُها واحدٌ لكلٍّ منها */
    'nav_landing_anchor_test',       /* INJ-0459 — مِرساةُ الرابطِ في وجهتِها */
    'nav_doors_integrity_test',      /* INJ-0491 — مصدرٌ واحدٌ لترتيبِ الأبواب */
    'report_button_placement_test',  /* INJ-0518 — الزرُّ داخلَ الجسدِ لا بعد </html> */
    'risk_owner_unit_test',          /* INJ-0577 — الإدارةُ المالكةُ قائمةٌ مُتحقَّقة */
    'ui_consistency_scan_test',      /* INJ-0593 · INJ-0498 — رؤوسٌ عربيةٌ ونسخةُ بوتستراب واحدة */
    /* حملةُ الواجهةِ · الموجتانِ الثانيةُ والثالثة */
    'shell_axes_measured_test',        /* INJ-0547 · INJ-0572 — محورُ الصلاحيةِ مقيسٌ ومميِّز */
    'nav_stage_label_test',            /* INJ-0570 — عنوانُ المرحلةِ يصف لا يَعُدّ */
    'deny_page_component_test',        /* INJ-0500 — صفحةُ حجبٍ واحدةٌ برمزٍ ومسارٍ ومقاس */
    'shell_color_tokens_test',         /* INJ-0496 — صفرُ لونٍ صلبٍ في القشرة */
    /* حملةُ الواجهةِ · الموجةُ الرابعة */
    'empty_state_adoption_test',       /* INJ-0238 · INJ-0432 — الحالةُ الفارغةُ مكوّنٌ واحد */
    'wide_table_views_test',           /* INJ-0493 — الجداولُ الطويلةُ ومناظرُها */
    'filter_bar_period_test',          /* INJ-0497 · 0543 · 0561 · 0564 · 0556 — الشريطُ وفلترُ الفترة */
    'contracts_tables_test',           /* INJ-0146 — جداولُ العقودِ وترويسةٌ ثابتة */
    /* حملةُ الواجهةِ · الموجةُ السادسة */
    'risk_treatment_evidence_test',    /* INJ-0576 — دليلُ الإنجازِ يُقرأ أو يُوثَّق */
    'url_message_spoof_test',          /* INJ-0492 — لا رسالةَ نجاحٍ يصنعها الرابط */
    /* حملةُ الصلاحياتِ والحوكمة · الموجةُ الأولى */
    'tenant_gate_audit_test',          /* التدقيقُ في البوابةِ لا في 17 شاشة */
    'csrf_enforcement_test',           /* الإنفاذُ من 5 مجلداتٍ إلى 27 */
    /* حملةُ الصلاحياتِ والحوكمة · الموجةُ الرابعة — سقفُ التفويضِ والتصعيد */
    'authority_cap_escalation_test',  /* INJ-0014 — فوقَ السقفِ يُرفع لا يُرفض صامتًا */
    'cap_state_guard_test',           /* INJ-0053 · INJ-0089 — السقفُ يحكم الحالة */
    'finance_source_guard_test',      /* INJ-0176 · 0178 · 0179 · 0180 — المالُ أثرٌ لا مصدر */
    'permission_guard_core_test',     /* INJ-0008 · 0202 · 0261 — الحارسُ المركزيُّ وحلُّ المودول */
    'field_visibility_test',          /* INJ-0159 — الحقلُ الحسّاسُ غائبٌ نصًّا بلا منحة */
    /* حملةُ الواجهةِ · الموجةُ السابعة */
    'inline_styles_test',              /* INJ-0442 · 0571 · 0237 · 0501 — الأنماطُ الموضعيةُ والرموز */
    'supplier_kpi_cards_test',         /* INJ-0158 — بطاقاتُ المؤشرِ بعقدِها السباعيّ */
    'contracts_layout_test',           /* INJ-0441 — البياناتُ أولًا وفعلٌ رئيسٌ واحد */
    'offline_outbox_test',             /* INJ-0378 · INJ-0548 — الحفظُ دونَ اتصالٍ وإرسالٌ مرةً واحدة */
    'template_layers_test',            /* INJ-0499 — الطبقاتُ الثلاثُ بمكوّناتٍ واحدة */
    'shell_entries_test',              /* INJ-0236 — مدخلُ القشرةِ واحدٌ لكلِّ عائلة */
);

$files = $core;
if ($suite === 'all') {
    $files = array();
    foreach (glob($dir . '/*_test.php') as $f) { $files[] = basename($f, '.php'); }
    sort($files);
}
/* ═══════════════════════════════════════════════════════════════════════════
 * سويتُ `only` — مدخلٌ للقياسِ لا لتخفيفِ الجولة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا**: اختبارُ قبولِ INJ-0149 شقّان: «يُرجع صفرًا عند النجاح» **و«خطُّ
 *   التسليمِ يفشل عند سقوطِ أيِّ اختبار»**. والشقُّ الثاني لا يُثبَت إلا بجولةٍ
 *   **حمراءَ حقيقيةٍ**، وإثباتُه بـ`all` يكلّف عشرين دقيقةً في كلِّ مرة.
 * ◆ فهذا المدخلُ يُشغّل قائمةً مسمّاةً بالضبط:
 *       php tests/_regression.php only tenant_leak_test,audit_trail_test
 *   ويسري عليه **كلُّ** ما يسري على الجولةِ الكاملة: حِمى الملفاتِ المحميةِ،
 *   وعدُّ الفحوص، **وعقدُ رمزِ الخروجِ نفسُه** — فما يُقاس هو المُشغِّلُ لا نسخةٌ منه.
 * ◆ ولا يُستعمل بديلًا عن `core`/`all` في التسليم: البوابةُ تبقى `core` والشجرةُ
 *   `all`؛ وهذا لقياسِ عقدِ الخروجِ وحدَه (`tests/regression_exit_contract_test.php`).
 * ═══════════════════════════════════════════════════════════════════════════ */
if ($suite === 'only') {
    $want = isset($argv[2]) ? (string) $argv[2] : '';
    $files = array();
    foreach (explode(',', $want) as $t) {
        $t = trim($t);
        if ($t !== '') { $files[] = basename($t, '.php'); }
    }
    if (!$files) {
        fwrite(STDERR, "السويتُ `only` تلزمها قائمةُ أسماءٍ مفصولةٌ بفاصلة.\n");
        exit(2);
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
 * INJ-0149 — حِمى الملفاتِ الحساسةِ حولَ كلِّ اختبار
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ كارثةٌ وقعت فعلًا وسُجِّلت في ترويسةِ `.env` نفسِه: «tests/proof_failclosed.php
 *   يكتب في .env ومسارُ استرجاعه غيرُ آمن؛ تشغيلُ حزمة الانحدار كاملةً دمّر
 *   الملفَّ (13,052 بايتًا من المسافات · صفر سطر)». أي أن **مشغِّلَ الاختبارات
 *   أهلك إعداداتَ النظام**.
 * ◆ فلا يُبنى مشغِّلٌ ثانٍ (سجلّان متنازعان أسوأُ من واحدٍ ناقص) — بل يُحمى هذا:
 *   تُلتقط بصمةُ كلِّ ملفٍّ حساسٍ **قبل** كلِّ اختبارٍ وتُقابَل **بعده**؛ فإن
 *   تغيّر ولم يُستعَد **يُستعاد من البصمةِ فورًا** ويُعلَن الاختبارُ الذي مسَّه.
 * ◆ والحمايةُ لكلِّ اختبارٍ لا لكلِّ الجولة: فمن يُفسد يُسمَّى، ولا يُنتظر آخرُ
 *   الحزمةِ ليُكتشف الفساد.
 * ═══════════════════════════════════════════════════════════════════════════ */
$ROOTP = dirname($dir);
$GUARDED = array($ROOTP . '/.env', $ROOTP . '/.env.example', $ROOTP . '/composer.json');
$snapshot = static function () use ($GUARDED) {
    $s = array();
    foreach ($GUARDED as $g) { if (is_file($g)) { $s[$g] = (string) file_get_contents($g); } }
    return $s;
};
$verify = static function (array $snap, $testName, array &$lines) {
    $harmed = array();
    foreach ($snap as $path => $orig) {
        if (!is_file($path) || (string) file_get_contents($path) !== $orig) {
            file_put_contents($path, $orig);
            $harmed[] = basename($path);
        }
    }
    if ($harmed) {
        $lines[] = '      ⚠ الاختبارُ «' . $testName . '» مسَّ ملفًّا محميًّا فاستُعيد فورًا: '
                 . implode('، ', $harmed) . ' — أصلِح الاختبارَ لا الحِمى';
    }
    return $harmed;
};

/* ═══════════════════════════════════════════════════════════════════════════
 * البذورُ المُعلَنةُ — شرطٌ مسبقٌ يُشغَّل مرةً قبلَ الجولة
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **عيبٌ مقيسٌ في المُشغِّلِ نفسِه**: `tests/seed_unit_lines_50.php` شرطٌ مسبقٌ
 *   لثلاثةِ فواحصَ على الأقل — و`approval_box_test` يقول ذلك **نصًّا** عند
 *   فشله («شغّل seed_unit_lines_50.php أولًا»). والمُشغِّلُ لا يجمع إلا
 *   `*_test.php`، فالبذرةُ لا تُشغَّل قطُّ ⇒ ثلاثةُ فواحصَ حمراءُ **بترتيبٍ لا
 *   بعيب**. والقياسُ: بتشغيلِ البذرةِ صار `approval_box` 0/0 ← **21/0** و
 *   `timesheet_time_lines` 8/9 ← **15/2** و`qty_attribution` 23/2 ← **33/1**.
 * ◆ فالبذرةُ تُعلَن هنا **بالاسمِ ومَن يحتاجها** لا تُخفى في وثيقة: مَن يقرأ
 *   المُشغِّلَ يرى شرطَه. وتُشغَّل **مرةً** قبلَ الجولةِ لا قبلَ كلِّ اختبار.
 * ◆ ولا تُحسَب في الخضراءِ ولا الحمراء — فهي بذرةٌ لا فاحص.
 * ═══════════════════════════════════════════════════════════════════════════ */
/* ◆ **ثلاثُ بذورٍ لا واحدة** — وكلُّها كانت مهملةً فتُقرأ حُمرتُها عيبًا:
     `seed_unit_reconcile_50` معلَنٌ في **ترويسةِ** `unit_reconcile_test` نصًّا
     («بعد seed_unit_reconcile_50.php») — و`unit_reconcile` كان 5/15 فصار
     **19/1** بتشغيلِها وحدَها. و`seed_operator_gap_50` غيرُ معلَنٍ في أيِّ
     ترويسةٍ ووُجد بالمسحِ — و`operator_due_policy` 19/2 ← **21/0** و
     `qty_attribution` 23/2 ← **34/0**.
   ◆ **والترتيبُ يهمّ**: نوافذُ البذورِ متعامدةٌ بقصدٍ (2027-03 للسطور ·
     2027-02 للمطابقة)، فبذرةٌ تكنس نافذةَ أختِها لا تفعل — لكنّ
     `unit_cycle_reseed` (أداةٌ لا بذرةُ حزمة) **يمحو الثلاثَ** لأنه يُفرِغ
     الجدولَ كلَّه؛ فمن شغّله يُعيد هذه بعده. مدوَّنٌ هنا لا في ذاكرةِ أحد. */
$SEEDS = array(
    'seed_unit_lines_50'     => 'approval_box_test · timesheet_time_lines_test',
    'seed_unit_reconcile_50' => 'unit_reconcile_test',
    'seed_operator_gap_50'   => 'operator_due_policy_test · qty_attribution_test',
);
$seedLines = array();
if ($suite === 'all') {
    foreach ($SEEDS as $seed => $needBy) {
        $sp = $dir . '/' . $seed . '.php';
        if (!is_file($sp)) { $seedLines[] = '  ⚠ بذرةٌ مُعلَنةٌ غيرُ موجودة: ' . $seed; continue; }
        $so = array(); $sc = 0;
        exec('"' . $php . '" "' . $sp . '" 2>&1', $so, $sc);
        $seedLines[] = '  ' . ($sc === 0 ? '✔' : '✘') . ' بذرة ' . $seed
                     . ' (شرطُ: ' . $needBy . ')' . ($sc === 0 ? '' : ' — رمزُ خروج ' . $sc);
    }
}
if ($seedLines) { echo "البذورُ المُعلَنةُ — شرطٌ مسبق:\n" . implode("\n", $seedLines) . "\n\n"; }

$green = 0; $red = 0; $lines = array(); $harmedBy = array(); $unreadableList = array();
foreach ($files as $t) {
    $path = $dir . '/' . $t . '.php';
    if (!is_file($path)) { $lines[] = sprintf('  %-42s  MISSING', $t); $red++; continue; }
    $snap = $snapshot();
    $out = array(); $code = 0;
    exec('"' . $php . '" "' . $path . '" 2>&1', $out, $code);
    $h = $verify($snap, $t, $lines);
    if ($h) { $harmedBy[$t] = $h; }
    $txt = implode("\n", $out);
    $p = 0; $f = 0;
    /* صيغُ الحزمِ الثلاث. و**الثالثةُ كانت عمياءَ**: خمسةُ اختباراتٍ على الأقلِّ
       تطبع «PASS=N · FAIL=M» فسُجّلت `0/0` وهي تعمل وتُنتج نتائج
       (org_structure 20/8 · permit_gate 13/11 · portability 7/2 ·
        uat_hardening 17/1 · tkt_structure 33/2). فرمزُ الخروجِ كان صادقًا
       والعدّادُ كاذبًا — ومُشغِّلٌ لا يعرف أن يعدَّ لا يُصادق على شيء. */
    if (preg_match('~النتيجة:\s*(\d+)\s*(?:نجاح|ناجح)\s*·\s*(\d+)\s*(?:فشل|فاشل)~u', $txt, $m)) {
        $p = (int) $m[1]; $f = (int) $m[2];
    } elseif (preg_match('~PASS\s*=\s*(\d+)\s*(?:·|\||,)?\s*FAIL\s*=\s*(\d+)~u', $txt, $m)) {
        $p = (int) $m[1]; $f = (int) $m[2];
    } elseif (preg_match('~(\d+)\s*(?:نجاح|ناجح|passed)\D{0,12}?(\d+)\s*(?:فشل|فاشل|failed)~u', $txt, $m)) {
        $p = (int) $m[1]; $f = (int) $m[2];
    }
    /* ◆ ولا يُقرأ «صفرٌ وصفر» صمتًا: اختبارٌ يخرج بخطأٍ بلا عدّادٍ مقروءٍ إمّا
         انفجر قبل ملخّصه وإمّا صيغتُه غيرُ معروفة — وكلٌّ منهما يستحق اسمًا. */
    $unreadable = ($p === 0 && $f === 0 && $code !== 0);
    $mark = ($code === 0) ? '✔' : '✘';
    if ($code === 0) { $green++; } else { $red++; }
    $lines[] = sprintf('  %s %-42s %3d/%-3d exit=%d%s', $mark, $t, $p, $f, $code,
        $unreadable ? '  ⟨عدّادٌ غيرُ مقروء — انفجارٌ قبل الملخّصِ أو صيغةٌ مجهولة⟩' : '');
    if ($unreadable) { $unreadableList[] = $t; }
    if ($code !== 0) {
        foreach ($out as $l) { if (strpos($l, 'FAIL') !== false || stripos($l, 'error') !== false) { $lines[] = '      ' . $l; } }
    }
}
echo implode("\n", $lines) . "\n";
echo str_repeat('─', 70) . "\n";
echo 'خضراء: ' . $green . ' · حمراء: ' . $red . ' · المجموع: ' . count($files) . "\n";
/* مجموعُ الفحوصِ لا عددُ الملفاتِ وحدَه — فملفٌّ بفحصٍ ساقطٍ واحدٍ من ستين
   ليس كملفٍّ ساقطٍ كلِّه، والرقمُ الواحدُ يخفي الفرق. */
$sp = 0; $sf = 0;
foreach ($lines as $l) {
    if (preg_match('~^\s*[✔✘]\s+\S+\s+(\d+)/(\d+)~u', $l, $m)) { $sp += (int) $m[1]; $sf += (int) $m[2]; }
}
echo 'مجموعُ الفحوص: ناجحٌ ' . $sp . ' · ساقطٌ ' . $sf . "\n";
if ($unreadableList) {
    echo 'عدّادٌ غيرُ مقروءٍ في ' . count($unreadableList) . " اختبارًا — تُشخَّص منفردةً:\n  "
       . implode(' · ', $unreadableList) . "\n";
}
if ($harmedBy) {
    echo 'اختباراتٌ مسَّت ملفاتٍ محميةً (استُعيدت): ' . count($harmedBy) . ' — '
       . implode('، ', array_keys($harmedBy)) . "\n";
} else {
    echo "الملفاتُ المحميةُ سليمةٌ — صفرُ اختبارٍ مسَّ `.env` أو `composer.json`\n";
}
/* ◆ عقدُ رمزِ الخروجِ مُعلَنٌ (اختبارُ قبولِ INJ-0149): **صفرٌ إن نجح الكلُّ
     وواحدٌ إن سقط واحد** — ومسُّ ملفٍّ محميٍّ يُسقط الجولةَ أيضًا، فالإفسادُ
     فشلٌ ولو نجحت تأكيداتُ الاختبار. */
exit(($red > 0 || $harmedBy) ? 1 : 0);
