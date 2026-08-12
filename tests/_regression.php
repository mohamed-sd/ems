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
);

$files = $core;
if ($suite === 'all') {
    $files = array();
    foreach (glob($dir . '/*_test.php') as $f) { $files[] = basename($f, '.php'); }
    sort($files);
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
$SEEDS = array(
    'seed_unit_lines_50' => 'approval_box_test · timesheet_time_lines_test · qty_attribution_test',
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
