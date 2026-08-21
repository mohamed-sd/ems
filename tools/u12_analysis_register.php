<?php
/**
 * tools/u12_analysis_register.php — تسجيلُ مرحلةِ التحليلِ في المنصة
 * ═══════════════════════════════════════════════════════════════════════════
 * ① عشرُ وحداتِ صلاحياتٍ (AC-10: صفرُ وجهةٍ بلا وحدة).
 * ② المنحُ بالأدوارِ بفصلِ الواجبات: اعتمادُ حدِّ النسبةِ للنائبِ الماليِّ
 *    (يمثّله الدورُ 17 حتى تُبنى طبقةُ النواب — الحكمُ المؤقتُ المؤرخ)،
 *    والقراءةُ لبقيةِ أدوارِ المالية، والرئيسُ (9) والمخاطرُ (28) قراءةً.
 * ③ nav09_file_map حيةٌ بمساراتها القانونية.
 * ④ nav09_action_map: الأفعالُ العشرةُ بعقودها السداسية.
 * ⑤ الحقولُ الحساسةُ لجداولِ المرحلة (AC-06).
 * idempotent.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$m = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if ($m->connect_error) { fwrite(STDERR, $m->connect_error . "\n"); exit(1); }
$m->set_charset('utf8mb4');
$CO = 4;
function say($s) { echo $s . "\n"; }

/* ══ ① وحداتُ الصلاحيات ═══════════════════════════════════════════════ */
$screens = array(
    array('لوحة النسب المالية', 'Finance/fin_ratios.php', 'fa fa-chart-line', 281),
    array('تفصيل نسبة مالية', 'Finance/fin_ratio_detail.php', 'fa fa-magnifying-glass-chart', 282),
    array('حدود النسب وأهدافها', 'Finance/fin_ratio_targets.php', 'fa fa-bullseye', 283),
    array('الإنذار المالي المبكر', 'Finance/fin_early_warning.php', 'fa fa-triangle-exclamation', 284),
    array('ربحية المعدة الواحدة', 'Finance/fin_unit_economics.php', 'fa fa-truck-monster', 285),
    array('هامش العقد ونموذج العمل', 'Finance/fin_contract_margin.php', 'fa fa-file-invoice-dollar', 286),
    array('قائمة دخل المشروع', 'Finance/fin_project_pl.php', 'fa fa-diagram-project', 287),
    array('قائمة التدفقات النقدية', 'Finance/fin_cashflow_stmt.php', 'fa fa-money-bill-transfer', 288),
    array('قائمة التغيرات في حقوق الملكية', 'Finance/fin_equity_stmt.php', 'fa fa-scale-balanced', 289),
    array('مصفوفة الترحيل من الإدارات', 'Finance/fin_posting_matrix.php', 'fa fa-table-cells', 290),
);
$modIds = array();
$r = $m->query("SELECT id, code FROM modules");
while ($x = $r->fetch_assoc()) { $modIds[$x['code']] = (int) $x['id']; }
$added = 0;
foreach ($screens as $s) {
    if (isset($modIds[$s[1]])) { continue; }
    $st = $m->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                       VALUES (?, ?, 17, 1, 0, ?, ?)");
    $st->bind_param('sssi', $s[0], $s[1], $s[2], $s[3]);
    $st->execute();
    $modIds[$s[1]] = (int) $m->insert_id;
    $st->close();
    $added++;
}
say("وحداتُ الصلاحيات: أُضيف {$added} · الإجمالي " . count($screens) . " مسجَّلة");

/* ══ ② المنحُ بفصلِ الواجبات ═════════════════════════════════════════ */
function grant($m, $roleId, $moduleId, $v, $a, $e) {
    $r = $m->query("SELECT COUNT(*) c FROM role_permissions WHERE role_id = $roleId AND module_id = $moduleId");
    if ((int) $r->fetch_assoc()['c'] > 0) { return false; }
    $m->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
               VALUES ($roleId, $moduleId, $v, $a, $e, 0)");
    return true;
}
$g = 0;
$finReaders = array(18, 19, 20, 21, 22);
foreach ($screens as $s) {
    $mid = $modIds[$s[1]];
    // ◆ حدودُ النسب: الاعتمادُ (edit) لسلطةِ النائبِ الماليِّ التي يمثّلها 17
    if ($s[1] === 'Finance/fin_ratio_targets.php' || $s[1] === 'Finance/fin_posting_matrix.php') {
        $g += grant($m, 17, $mid, 1, 0, 1) ? 1 : 0;
        foreach ($finReaders as $rr) { $g += grant($m, $rr, $mid, 1, 0, 0) ? 1 : 0; }
    } elseif (in_array($s[1], array('Finance/fin_ratios.php', 'Finance/fin_early_warning.php',
        'Finance/fin_project_pl.php', 'Finance/fin_cashflow_stmt.php', 'Finance/fin_equity_stmt.php'), true)) {
        // توليدٌ وحساب: المديرُ الماليُّ (17) كتابةً — والبقيةُ قراءة
        $g += grant($m, 17, $mid, 1, 1, 1) ? 1 : 0;
        foreach ($finReaders as $rr) { $g += grant($m, $rr, $mid, 1, 0, 0) ? 1 : 0; }
    } else {
        // قارئاتٌ خالصة
        $g += grant($m, 17, $mid, 1, 0, 0) ? 1 : 0;
        foreach ($finReaders as $rr) { $g += grant($m, $rr, $mid, 1, 0, 0) ? 1 : 0; }
    }
    // الرئيسُ التنفيذيُّ والمخاطرُ والحوكمةُ: قراءةٌ على ما يعنيهم
    foreach (array(9, 28, 15) as $rr) { $g += grant($m, $rr, $mid, 1, 0, 0) ? 1 : 0; }
}
// المبيعاتُ (12) والأسطولُ (3) والتشغيلُ (1): زاويتُهم من الربحيةِ والهامش
foreach (array('Finance/fin_unit_economics.php' => array(3, 1),
               'Finance/fin_contract_margin.php' => array(12, 1)) as $code => $roles) {
    foreach ($roles as $rr) { $g += grant($m, $rr, $modIds[$code], 1, 0, 0) ? 1 : 0; }
}
say("منحٌ أُضيفت: {$g}");

/* ══ ③ nav09_file_map ═══════════════════════════════════════════════════ */
foreach ($screens as $s) {
    $canon = basename($s[1]);
    $st = $m->prepare("INSERT INTO nav09_file_map (canonical_file, title_ar, owner_dept, state, real_path, note, updated_at)
                       VALUES (?, ?, 'المالية والخزينة', 'live', ?, 'update0012: مرحلةُ التحليلِ الماليِّ والنسب', NOW())
                       ON DUPLICATE KEY UPDATE state='live', real_path=VALUES(real_path),
                           title_ar=VALUES(title_ar), note=VALUES(note), updated_at=NOW()");
    $st->bind_param('sss', $canon, $s[0], $s[1]);
    $st->execute();
    $st->close();
}
say('خريطةُ الملفات: 10 شاشاتٍ حية');

/* ══ ④ الأفعالُ العشرةُ بعقودها ═══════════════════════════════════════ */
$actions = array(
    array('fin.ratio.compute', 'حسابُ النسبِ المالية', 'لوحة النسب المالية', 'fin_ratios.php',
        'محرّكُ النسب', 'fin_ratio_values', 'FinancialRatiosComputed',
        'الرئيسُ والنائبُ الماليُّ والمخاطر',
        '◆ النسبُ محسوبةٌ من القيودِ لا من إدخالٍ يدويّ — والبسطُ والمقامُ من أكوادِ الشجرةِ المعلنة',
        'إعادةُ الحسابِ لفترةٍ', 'Finance/fin_analysis_actions.php::ratio_compute'),
    array('fin.ratio.drill', 'التعمّقُ من نسبةٍ إلى قيودِها', 'تفصيل نسبة مالية', 'fin_ratio_detail.php',
        'المخوَّلُ بالقراءة', '—', '', '—',
        '◆ النقرُ يفتح الحساباتَ ثم القيودَ ثم الوقائعَ المصدر — ورقمٌ لا يُتعمَّق فيه لا يُقرَّر عليه',
        'لا يُعكس — قارئ', 'Finance/fin_analysis_actions.php::ratio_drill'),
    array('fin.ratio.target', 'اعتمادُ حدِّ نسبةٍ وهدفِها', 'حدود النسب وأهدافها', 'fin_ratio_targets.php',
        '◆ نائبُ الرئيس للشؤون المالية والاستثمار', 'fin_ratio_targets', 'RatioTargetApproved',
        'المالكون المعنيون',
        '◆ لكل نسبةٍ حدُّ إنذارٍ وحدٌّ حرجٌ ومالكٌ ودورية — ولا نسبةَ تُعرض بلا حد',
        'إعادةُ الحدِّ السابقِ بقرار', 'Finance/fin_analysis_actions.php::ratio_target_set'),
    array('fin.unit.economics', 'عرضُ ربحيةِ المعدةِ الواحدة', 'ربحية المعدة الواحدة', 'fin_unit_economics.php',
        'المالكون والأسطولُ والتشغيل', '—', '', '—',
        '◆ إيرادُ المعدةِ وتكلفتُها وهامشُها وتكلفةُ ساعتها ونقطةُ تعادلها — بالبُعد D5',
        'لا يُعكس — قارئ', 'Finance/fin_analysis_actions.php::unit_economics'),
    array('fin.contract.margin', 'عرضُ هامشِ العقدِ ونموذجِ العمل', 'هامش العقد ونموذج العمل', 'fin_contract_margin.php',
        'الماليةُ والمبيعات', '—', '', '—',
        '◆ الهامشُ لكل عقدٍ وكل نموذجٍ — والسالبُ يُنشر إشارةً للمبيعاتِ والمخاطر',
        'لا يُعكس — قارئ', 'Finance/fin_analysis_actions.php::contract_margin'),
    array('fin.project.pl', 'توليدُ قائمةِ دخلِ المشروع', 'قائمة دخل المشروع', 'fin_project_pl.php',
        'المحاسبُ بمراجعةِ المدير المالي', 'fin_project_pl', 'ProjectPLGenerated',
        'التشغيلُ والمخاطرُ والمالكون',
        '◆ الإيرادُ والتكلفةُ المباشرةُ بالبُعد D2 والحصةُ المحمَّلةُ بأساسِ تحميلٍ معلَن',
        'إعادةُ التوليدِ لفترة', 'Finance/fin_analysis_actions.php::project_pl'),
    array('fin.cashflow.generate', 'توليدُ قائمةِ التدفقاتِ النقدية', 'قائمة التدفقات النقدية', 'fin_cashflow_stmt.php',
        '◆ المدير المالي', 'fin_cashflow', 'CashFlowStatementGenerated',
        'الرئيسُ والنائبُ الماليُّ والممولون',
        '◆ بالطريقةِ غيرِ المباشرةِ — وتتوازن مع تغيرِ النقديةِ الفعليِّ أو تُرفض',
        'إعادةُ التوليد', 'Finance/fin_analysis_actions.php::cashflow_generate'),
    array('fin.equity.generate', 'توليدُ قائمةِ التغيراتِ في حقوق الملكية', 'قائمة التغيرات في حقوق الملكية', 'fin_equity_stmt.php',
        '◆ المدير المالي', 'fin_equity', 'EquityStatementGenerated',
        'الرئيسُ والشركاءُ والتمويل',
        '◆ الختاميُّ = الافتتاحيُّ + الحركاتُ لكل بندٍ أو تُرفض',
        'إعادةُ التوليد', 'Finance/fin_analysis_actions.php::equity_generate'),
    array('fin.signal.raise', 'نشرُ إشارةِ إنذارٍ مالي', 'الإنذار المالي المبكر', 'fin_early_warning.php',
        'محرّكُ الإشارات', 'risk_signals', 'RiskSignalRaised', '◆ إدارةُ المخاطر',
        '◆ الإشارةُ تُنشر إلى المخاطرِ لا تبقى في المالية — فتدخل الفرزَ الرباعي',
        'سحبُها قبل الفرز', 'Finance/fin_analysis_actions.php::signal_raise'),
    array('fin.posting.matrix', 'ضبطُ مصفوفةِ الترحيلِ للإدارات', 'مصفوفة الترحيل من الإدارات', 'fin_posting_matrix.php',
        '◆ المدير الماليُّ بمراجعةِ الحوكمة', 'fin_posting_matrix', 'PostingMatrixUpdated',
        'الإداراتُ كلُّها',
        '◆ الحسابُ يُشتق من نوعِ الواقعةِ ونموذجِ العملِ ونوعِ العقدِ — ولا يُختار يدويًّا',
        'إعادةُ المصفوفةِ السابقة', 'Finance/fin_analysis_actions.php::posting_matrix_set'),
);
$ac = 0;
foreach ($actions as $a) {
    list($code, $label, $screen, $file, $actor, $writes, $event, $consumers, $effect, $reverse, $live) = $a;
    $st = $m->prepare("INSERT INTO nav09_action_map
        (canonical_code, label_ar, screen_title, canonical_file, actor_ar, writes_text,
         event_name, consumers_text, effect_text, reverse_text, live_code, state, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?, 'bound_page', NOW())
        ON DUPLICATE KEY UPDATE label_ar=VALUES(label_ar), screen_title=VALUES(screen_title),
            canonical_file=VALUES(canonical_file), actor_ar=VALUES(actor_ar),
            writes_text=VALUES(writes_text), event_name=VALUES(event_name),
            consumers_text=VALUES(consumers_text), effect_text=VALUES(effect_text),
            reverse_text=VALUES(reverse_text), live_code=VALUES(live_code),
            state='bound_page', updated_at=NOW()");
    $st->bind_param('sssssssssss', $code, $label, $screen, $file, $actor, $writes,
        $event, $consumers, $effect, $reverse, $live);
    $st->execute();
    if ($st->errno) { fwrite(STDERR, 'ACT ' . $code . ': ' . $st->error . "\n"); }
    $st->close();
    $ac++;
}
say("الأفعالُ العشرة: {$ac} بعقودها السداسية");

/* أحكامُ عمقِ الربطِ الأولية: القارئُ لا مفتاحَ تكرارٍ له بطبيعته */
$m->query("UPDATE nav09_action_map SET idempotency_verified = 'n_a',
    idempotency_evidence = 'قارئٌ لا يكتب — لا مفتاحَ بطبيعته (M-10 §8-5)'
    WHERE canonical_code IN ('fin.ratio.drill','fin.unit.economics','fin.contract.margin')
      AND idempotency_verified = 'pending'");
$m->query("UPDATE nav09_action_map SET idempotency_verified = 'yes',
    idempotency_evidence = 'مفتاحٌ فريدٌ في جدوله — والإعادةُ نسخةٌ تشير لسابقتها'
    WHERE canonical_code IN ('fin.ratio.compute','fin.project.pl','fin.cashflow.generate',
                             'fin.equity.generate','fin.signal.raise')
      AND idempotency_verified = 'pending'");

/* ══ ⑤ الحقولُ الحساسة ═══════════════════════════════════════════════ */
$sens = array(
    array('U12-FIN-11', 'fin_ratio_values', 'result_value', 'قيمُ النسبِ قرارٌ إداريٌّ حساس'),
    array('U12-FIN-12', 'fin_ratio_targets', 'critical_value', 'الحدُّ الحرجُ يكشف شهيةَ المخاطر'),
    array('U12-FIN-13', 'fin_project_pl', 'operating_profit', 'ربحُ المشروعِ محجوبٌ عن التشغيل'),
    array('U12-FIN-14', 'fin_project_pl', 'allocated_overhead', 'أساسُ التحميلِ وحصتُه'),
    array('U12-FIN-15', 'fin_cashflow', 'operating_net', 'التدفقُ التشغيليُّ حساس'),
    array('U12-FIN-16', 'fin_cashflow', 'cash_close', 'نقديةُ آخرِ المدة'),
    array('U12-FIN-17', 'fin_equity', 'closing_balance', 'حقوقُ الملكيةِ محجوبةٌ إلا بمنحٍ فردي'),
    array('U12-FIN-18', 'fin_posting_matrix', 'cost_accounts', 'خريطةُ التكلفةِ تكشف بنيةَ الهامش'),
);
$sc = 0;
foreach ($sens as $s) {
    $st = $m->prepare("INSERT INTO scr_sensitive_fields
        (company_id, no_policy, table_name, field_name, classification_sensitivity,
         reason_classification, from_visible_to, policy_masking, log_views_flag, exportable_flag,
         date_effective, status, is_seed, created_by, created_by_name, created_at)
        SELECT 4, ?, ?, ?, 'سري — منح فردي', ?, 'المدير المالي والنائب المالي بمنح فردي',
               'يُحجب من الخادم', 'نعم', 'لا', CURDATE(), 'معتمد', 0, 1, 'update0012', NOW() FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM scr_sensitive_fields
                            WHERE company_id = 4 AND table_name = ? AND field_name = ?)");
    $st->bind_param('ssssss', $s[0], $s[1], $s[2], $s[3], $s[1], $s[2]);
    $st->execute();
    $sc += $m->affected_rows > 0 ? 1 : 0;
    $st->close();
}
say("سياساتُ حقولٍ حساسة: {$sc}");
say('اكتمل تسجيلُ مرحلةِ التحليل.');
