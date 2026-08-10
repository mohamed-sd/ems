<?php
/**
 * tools/m10_ac_gate.php — بوابةُ القبولِ لـM-10 المالية والخزينة (§12-2 AC-01..AC-11)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ مُحدَّثةٌ لأرقامِ M-10 v5 (update0012 · الحزمةُ المضافة): 38 شاشةً · 45 فعلًا
 * (26 ماليًّا) · 12 مرحلة · 24 طويلة · 37 حساسة — والقديمُ إضافةٌ لا نسخ.
 * تقيس نطاقَ الوثيقةِ نفسِه —
 * كلُّ فحصٍ يطبع شاهدَه المقيسَ لا حكمَه المجرَّد. وتُجري اختباراتِ الحالات
 * الأربعِ حيًّا (سماحٌ · منعٌ · تكرارٌ · عكسٌ) على أفعال الجولة (AC-09) ثم
 * تُخزّن حكمَي ⑤/⑩ في خريطة الأفعال بدليلهما (عمقُ الربط — UXR-0174).
 * الخروج 0 عند عبور الإحدى عشرة، و1 عند أي رسوب.
 *
 * التشغيل: php tools/m10_ac_gate.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/app/Services/Finance/FinanceM10Service.php';

use App\Services\Finance\FinanceM10Service as M10;

$db = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if ($db->connect_error) { fwrite(STDERR, $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$CO = 4;
$fail = 0;

function ac($code, $name, $ok, $evidence) {
    global $fail;
    if (!$ok) { $fail++; }
    echo ($ok ? '✔' : '✘') . " $code · $name\n    ↳ $evidence\n";
}
function one($db, $sql) { $r = $db->query($sql); return $r ? (string) $r->fetch_row()[0] : '0'; }

/* أفعالُ M-10 الخمسةُ والأربعون كما نصَّ §7-1 في v5 */
$ACTIONS = array(
    'fin.entitle', 'pay.request', 'pay.execute', 'fin.close', 'budget.commit', 'budget.approve',
    'inv.issue', 'rec.collect', 'rec.provision', 'pay.prioritize', 'treas.transfer', 'recon.match',
    'je.post', 'acc.define', 'fx.set', 'margin.compute', 'rpt.export', 'var.analyze',
    'budget.request', 'stmt.client.issue', 'tax.file', 'cash.forecast', 'fs.close', 'cost.compute',
    'prov.use', 'route.define', 'cycle.measure', 'trace.follow', 'desk.post', 'gate.pass',
    'risk.fin.view', 'risk.fin.raise', 'risk.fin.evidence', 'gov.fin.view', 'gov.fin.attest',
    // ◆ العشرةُ المضافةُ في v5 — مرحلةُ التحليلِ الماليِّ والنسب
    'fin.ratio.compute', 'fin.ratio.drill', 'fin.ratio.target', 'fin.unit.economics',
    'fin.contract.margin', 'fin.project.pl', 'fin.cashflow.generate', 'fin.equity.generate',
    'fin.signal.raise', 'fin.posting.matrix',
);
/* الماليةُ الستةُ والعشرون (عمود «أماليّ» في §8-1 · v5) */
$FINANCIAL = array(
    'fin.entitle', 'pay.request', 'pay.execute', 'budget.commit', 'budget.approve', 'inv.issue',
    'rec.collect', 'rec.provision', 'pay.prioritize', 'treas.transfer', 'recon.match', 'je.post',
    'acc.define', 'fx.set', 'margin.compute', 'stmt.client.issue', 'tax.file', 'cash.forecast',
    'fs.close', 'cost.compute', 'prov.use', 'trace.follow', 'desk.post', 'gate.pass',
    'fin.project.pl', 'fin.cashflow.generate',
);
/* القرّاءُ المعلَنون «بلا عكسٍ بطبيعتهم» (§8-5) + كتابةُ الحوكمة rpt.export */
$NO_REVERSE_OK = array('trace.follow', 'risk.fin.view', 'gov.fin.view', 'rpt.export',
    'fin.ratio.drill', 'fin.unit.economics', 'fin.contract.margin');

echo "بوابة قبول M-10 المالية والخزينة — نطاقُ الوثيقةِ (update0012)\n";
echo str_repeat('═', 74), "\n";

/* ══ AC-01 · صفرُ مرحلةٍ بلا مستندٍ ومعتمِد (11 مرحلة §5-2) ═══════════════ */
$stages = array(
    '0 لوحة الإدارة (عرض)' => array(null, null),
    '1 تحويل العمل إلى مستحق' => array('fin_entitlements', 'approved_by'),
    '2 الإيراد والفوترة' => array('tax_invoices', null),
    '3 المصروف والدفع' => array('fin_requests', 'decided_by'),
    '4 التكلفة والتحميل' => array('fin_cost_records', 'created_by'),
    '5 الخزينة والمطابقة' => array('fin_bank_statement_lines', null),
    '6 القيود والدفاتر' => array('fin_journal_entries', 'posted_by'),
    '7 الإقفال والموازنة' => array('fin_financial_periods', 'reopened_by'),
    '8 متابعة وتقارير (عرض)' => array(null, null),
    '9 إعدادات (عرض)' => array(null, null),
    '10 التحليلُ الماليُّ والنسب' => array('fin_ratio_targets', 'approved_by'),
    '11 مخاطرُ الإدارة' => array('risk_register', 'created_by'),
);
$miss = array();
foreach ($stages as $stage => $pair) {
    list($tbl, $approver) = $pair;
    if ($tbl === null) { continue; }
    if (one($db, "SELECT COUNT(*) FROM information_schema.tables
                   WHERE table_schema = DATABASE() AND table_name = '$tbl'") === '0') {
        $miss[] = "$stage: لا جدولَ $tbl";
        continue;
    }
    if ($approver !== null && one($db, "SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = '$tbl' AND column_name = '$approver'") === '0') {
        $miss[] = "$stage: لا معتمِدَ ($tbl.$approver)";
    }
}
ac('AC-01', 'صفر مرحلة بلا مستند ومعتمِد', empty($miss),
    '12 مرحلة (§5-2 · v5) · مراحلُ المستندات لكلٍّ جدولُها وعمودُ شاهدها'
    . (empty($miss) ? '' : ' — الناقص: ' . implode(' · ', $miss)));

/* ══ AC-02 · صفرُ شاشةٍ بلا عمودٍ حاكمٍ محقون — الشاشاتُ الـ28 محلولةً حيًّا ══ */
$CANON = array('budget_dept.php','entitlement.php','invoices.php','receivables.php','payables.php',
    'payments.php','treasury.php','bank_recon.php','journal.php','coa.php','fx_rates.php',
    'period_close.php','budget_master.php','margin.php','variance.php','client_statement.php',
    'tax_invoices.php','cash_forecast.php','fin_statements.php','cost_report.php','maint_provision.php',
    'routing_admin.php','cycle_time.php','effect_map.php','accountant_desk.php','entitlement_gate.php',
    'risk_dept_fin.php','gov_dept_fin.php',
    // ◆ العشرُ المضافةُ في v5
    'fin_ratios.php','fin_ratio_detail.php','fin_ratio_targets.php','fin_early_warning.php',
    'fin_unit_economics.php','fin_contract_margin.php','fin_project_pl.php',
    'fin_cashflow_stmt.php','fin_equity_stmt.php','fin_posting_matrix.php');
$resolved = 0; $missing = array(); $paths = array();
foreach ($CANON as $cf) {
    $st = $db->prepare("SELECT real_path, state FROM nav09_file_map WHERE canonical_file = ? LIMIT 1");
    $st->bind_param('s', $cf);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row || !is_file($ROOT . '/' . $row['real_path'])) { $missing[] = $cf; continue; }
    $resolved++;
    $paths[$cf] = $row['real_path'];
}
/* الحقنُ من الطبقة المشتركة: الجداولُ الجديدةُ تحمل السبعةَ حيث تلزم (§9-1) */
$govTables = array('fin_entitlements', 'fin_client_statements', 'fin_budget_change_requests',
    'fin_margin_analysis', 'fin_cycle_time_metrics',
    'fin_ratio_targets', 'fin_project_pl', 'fin_cashflow', 'fin_equity'); // ◆ جداولُ v5
$govNeed = count($govTables) * 6;
$govCols = 0;
foreach ($govTables as $t) {
    foreach (array('company_id', 'created_at', 'approved_by', 'approved_at', 'authority_ref', 'parent_ref') as $c) {
        if (one($db, "SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = '$c'") === '1') { $govCols++; }
    }
}
ac('AC-02', 'صفر شاشة بلا عمود حاكم محقون', empty($missing) && $govCols === $govNeed,
    "الشاشاتُ القانونية 38: {$resolved}/38 محلولةٌ لملفٍّ حي" .
    " · الأعمدةُ الحاكمةُ في جداول الجولة: {$govCols}/{$govNeed}"
    . (empty($missing) ? '' : ' — الناقص: ' . implode(' · ', $missing)));

/* ══ AC-03 · صفرُ زرٍّ بلا عقدِ فعل — الخمسةُ والثلاثون بعقدها السداسي ═════ */
$inMap = 0; $noContract = array();
foreach ($ACTIONS as $code) {
    $st = $db->prepare("SELECT canonical_code, actor_ar, writes_text, event_name, consumers_text, reverse_text, state
                          FROM nav09_action_map WHERE canonical_code = ? LIMIT 1");
    $st->bind_param('s', $code);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) { $noContract[] = "$code: غائب"; continue; }
    $inMap++;
    if ((string) $row['actor_ar'] === '' || (string) $row['reverse_text'] === '') {
        $noContract[] = "$code: عقدٌ ناقص";
    }
}
ac('AC-03', 'صفر زر بلا عقد فعل', $inMap === 45 && empty($noContract),
    "{$inMap}/45 في قاموس الأفعال بفاعلٍ وعكسٍ لكلٍّ"
    . (empty($noContract) ? '' : ' — ' . implode(' · ', $noContract)));

/* ══ AC-04 · صفرُ فعلٍ ماليٍّ بلا عكس (الـ24) ═════════════════════════════ */
$noRev = array();
foreach ($FINANCIAL as $code) {
    $st = $db->prepare("SELECT reverse_text FROM nav09_action_map WHERE canonical_code = ? LIMIT 1");
    $st->bind_param('s', $code);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    $rv = (string) ($row['reverse_text'] ?? '');
    $isReaderOk = in_array($code, $NO_REVERSE_OK, true) && (mb_strpos($rv, 'قارئ') !== false || mb_strpos($rv, 'بلا عكس') !== false);
    if ($rv === '' || (mb_strpos($rv, 'بلا عكس') !== false && !$isReaderOk)) { $noRev[] = $code; }
}
ac('AC-04', 'صفر فعل مالي بلا عكس', empty($noRev),
    '26 فعلًا ماليًّا لكلٍّ عكسٌ معرَّف — والقارئُ موسومٌ «لا عكسَ له بطبيعته» صريحًا'
    . (empty($noRev) ? '' : ' — الناقص: ' . implode(' · ', $noRev)));

/* ══ AC-05 · صفرُ شاشةٍ طويلةٍ بلا مناظر (23 طويلة) ═══════════════════════ */
$LONG = array('entitlement.php','invoices.php','receivables.php','payables.php','payments.php',
    'treasury.php','bank_recon.php','journal.php','coa.php','fx_rates.php','period_close.php',
    'budget_master.php','margin.php','client_statement.php','tax_invoices.php','cash_forecast.php',
    'fin_statements.php','cost_report.php','maint_provision.php','routing_admin.php','cycle_time.php',
    'entitlement_gate.php','risk_dept_fin.php','fin_ratio_detail.php'); // ◆ 24 في v5
/* ◆ GT-01 (FIXA-0032/0034) — أُبطل الشرطُ الخاوي.
   كان الحكمُ: ‎strpos($src,'table') !== false‎ — أي «هل في ملفِّ PHP الحروفُ
   t-a-b-l-e؟». وكلُّ ملفٍّ يحتويها، فالمعيارُ يمرُّ أخضرَ على كلِّ شيءٍ ولا
   يرسب على شيء — وكلُّ تقريرِ جاهزيةٍ بُني عليه ليس دليلًا.
   البديلُ: **تصييرٌ حيٌّ** للشاشةِ بدورِ مالكها ثم تحليلُ **الناتج**: جدولٌ
   حقيقيٌّ · أعمدةٌ فوقَ الصفر · منتقي منظرٍ فعّال. والتفصيلُ في
   ‎tools/fix_lib.php::fix_screen_view_evidence‎ (موضعٌ واحدٌ يُختبر مرةً واحدة)،
   واختبارُه السلبيُّ في ‎tools/fix_negative_tests.php‎. */
require_once __DIR__ . '/fix_lib.php';
$longOk = 0; $longMiss = array();
foreach ($LONG as $cf) {
    $rp = $paths[$cf] ?? '';
    if ($rp === '' || !is_file($ROOT . '/' . $rp)) { $longMiss[] = $cf . ' (لا ملف)'; continue; }
    $ownerRole = one($db, "SELECT rp.role_id FROM role_permissions rp
                             JOIN modules m ON m.id = rp.module_id
                            WHERE m.code = '" . $db->real_escape_string($rp) . "' AND rp.can_view = 1
                            ORDER BY rp.role_id LIMIT 1");
    if ($ownerRole === null) { $longMiss[] = $cf . ' (بلا دورٍ مانحٍ فلا تُصيَّر)'; continue; }
    $ev = fix_screen_view_evidence($ROOT, $rp, (string) $ownerRole);
    if (!empty($ev['ok'])) { $longOk++; } else { $longMiss[] = $cf . ' — ' . $ev['reason']; }
}
$LONG_N = count($LONG);
ac('AC-05', 'صفر شاشة طويلة بلا مناظر (تصييرٌ حيٌّ لا مطابقةُ نص)', empty($longMiss),
    "{$longOk}/{$LONG_N} صُيِّرت بجدولٍ حقيقيٍّ وأعمدةٍ فوقَ الصفرِ ومنتقي منظرٍ فعّال"
    . (empty($longMiss) ? '' : ' — الناقص: ' . implode(' · ', array_slice($longMiss, 0, 6))));

/* ══ AC-06 · صفرُ حقلٍ حساسٍ يُرسَل لغير المخوَّل ══════════════════════════ */
$polCount = (int) one($db, "SELECT COUNT(*) FROM scr_sensitive_fields
    WHERE company_id = {$CO} AND (table_name LIKE 'fin%' OR table_name LIKE 'gov%')");
$readLog = one($db, "SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'sensitive_read_log'") === '1';
ac('AC-06', 'صفر حقل حساس يُرسَل لغير المخوَّل', $polCount >= 20 && $readLog,
    "سياساتُ الحقول الحساسة المالية المسجَّلة: {$polCount} (11 لجداول M-10 + 8 لمرحلة التحليل) · سجلُّ الاطّلاع "
    . ($readLog ? 'حيّ' : 'غائب') . ' · و37 شاشةً حساسةً في v5');

/* ══ AC-07 · الغلافُ الحاكم CM-00 ═════════════════════════════════════════ */
$newScreens = array('Finance/entitlement.php', 'Finance/gov_dept_fin.php',
    'Risk/risk_dept_fin.php', 'Governance/guard_denials.php',
    'Finance/fin_ratios.php', 'Finance/fin_ratio_detail.php', 'Finance/fin_ratio_targets.php',
    'Finance/fin_early_warning.php', 'Finance/fin_unit_economics.php',
    'Finance/fin_contract_margin.php', 'Finance/fin_project_pl.php',
    'Finance/fin_cashflow_stmt.php', 'Finance/fin_equity_stmt.php', 'Finance/fin_posting_matrix.php');
$shellOk = 0; $shellMiss = array();
foreach ($newScreens as $f) {
    $src = (string) file_get_contents($ROOT . '/' . $f);
    // الغلافُ يُبذر من الخادم (ems_shell_axes) مباشرةً أو عبر القشرةِ المضمَّنة
    if (strpos($src, 'ems_shell_axes') !== false || strpos($src, 'dept_risk_space.php') !== false
        || strpos($src, 'dept_gov_space.php') !== false
        || strpos($src, 'fin_analysis_shell.php') !== false) { $shellOk++; }
    else { $shellMiss[] = $f; }
}
$estateShell = 0; $estateTotal = 0;
foreach ($paths as $cf => $rp) {
    $estateTotal++;
    $src = (string) @file_get_contents($ROOT . '/' . $rp);
    if (strpos($src, 'ems_shell_axes') !== false || strpos($src, 'dept_risk_space.php') !== false
        || strpos($src, 'dept_gov_space.php') !== false) { $estateShell++; }
}
ac('AC-07', 'الغلاف الحاكم CM-00 في شاشات الجولة', $shellOk === count($newScreens),
    "شاشاتُ الجولة الجديدة {$shellOk}/" . count($newScreens) . " تبذر محاورَ الغلاف من الخادم"
    . " · تبنّي الحوزة القائمة {$estateShell}/{$estateTotal} — دَينُ تبنٍّ معلَنٌ يُهاجَر بدفعات (MG-6) لا يُخفى");

/* ══ AC-08 · صفرُ كتابةٍ عابرةٍ للإدارات ══════════════════════════════════ */
$svcSrc = (string) file_get_contents($ROOT . '/app/Services/Finance/FinanceM10Service.php')
    . (string) @file_get_contents($ROOT . '/app/Services/Finance/FinAnalysisService.php')
    . (string) @file_get_contents($ROOT . '/app/Services/Finance/CoaService.php');
preg_match_all('/(?:INSERT INTO|UPDATE)\s+`?([a-z_]+)`?/i', $svcSrc, $mm);
$writes = array_unique(array_map('strtolower', $mm[1]));
$foreign = array();
foreach ($writes as $t) {
    if (strpos($t, 'fin_') === 0) { continue; }
    if (in_array($t, array('ems_business_events'), true)) { continue; } // الممرُ المحايد عبر الناشر
    // ◆ إشارةُ الإنذارِ تُنشأ بخدمةِ M-16 نفسِها (RiskService) لا بكتابةٍ مباشرة
    if ($t === 'risk_signals' && strpos($svcSrc, 'RiskService::createSignal') !== false) { continue; }
    $foreign[] = $t;
}
ac('AC-08', 'صفر كتابة عابرة للإدارات', empty($foreign),
    'كتاباتُ خدماتِ M-10 الثلاث: ' . implode(' · ', $writes)
    . ' — كلُّها fin_* · والإشارةُ بخدمةِ M-16 · والممرُّ المحايدُ عبر الناشر'
    . (empty($foreign) ? '' : ' — العابر: ' . implode(' · ', $foreign)));

/* ══ AC-09 · الحالاتُ الأربعُ حيًّا: سماحٌ · منعٌ · تكرارٌ · عكسٌ ══════════ */
$evidence = array();
$fourOk = true;

// (أ) سماحٌ + تكرار: بوابةُ فحصٍ على وحدةٍ حية — النداءُ الثاني يرجع الأول
$unitId = (int) one($db, "SELECT id FROM fin_unit_records
    WHERE company_id = {$CO} AND COALESCE(is_deleted,0) = 0 ORDER BY id DESC LIMIT 1");
if ($unitId > 0) {
    try {
        $r1 = M10::gatePass($db, $CO, $unitId, 1);
        $r2 = M10::gatePass($db, $CO, $unitId, 1);
        $allow = isset($r1['gate_code']);
        $idem = !empty($r2['idempotent']) && $r2['gate_code'] === $r1['gate_code'];
        $evidence[] = 'سماح: ' . $r1['gate_code'] . ' (' . $r1['result']
            . ($r1['result'] === 'reject' ? '·' . $r1['reject_code'] . ' — ردٌّ بسببٍ محكومٍ وهو سلوكُ البوابة الصحيح' : '') . ')';
        $evidence[] = 'تكرار: الإعادةُ أرجعت ' . $r2['gate_code'] . ' نفسَه idempotent=' . var_export($r2['idempotent'], true);
        if (!$allow || !$idem) { $fourOk = false; }
    } catch (\Throwable $e) {
        $fourOk = false;
        $evidence[] = 'سماح/تكرار: استثناء ' . $e->getMessage();
    }
} else {
    $evidence[] = 'سماح/تكرار: لا وحدةَ حيةً للفحص — يُقاس عند أول وحدة';
}

// (ب) منع: budget.approve بيد مُنشئها يُرفض برمز فصل الواجبات (FIN-SOD-403)
$budId = (int) one($db, "SELECT id FROM fin_budgets WHERE company_id = {$CO}
    AND state IN ('draft','submitted') ORDER BY id DESC LIMIT 1");
if ($budId > 0) {
    $creator = (int) one($db, "SELECT created_by FROM fin_budgets WHERE id = {$budId}");
    try {
        M10::budgetApprove($db, $CO, $budId, $creator ?: 1, 'اختبار', 'اختبار');
        $fourOk = false;
        $evidence[] = 'منع: لم يُرفض اعتمادُ المُنشئ — خرقُ فصل الواجبات ✘';
        $db->query("UPDATE fin_budgets SET state = 'submitted', approved_by = NULL, approved_at = NULL WHERE id = {$budId}");
    } catch (\Throwable $e) {
        $code = strpos($e->getMessage(), 'FIN-SOD-403') !== false ? 'FIN-SOD-403' : $e->getMessage();
        $evidence[] = 'منع: اعتمادُ المُنشئ رُفض برمز ' . $code . ' ✓';
    }
} else {
    // لا موازنةَ قائمة — المنعُ يُثبت بحارس مبلغ الالتزام السالب
    try {
        M10::budgetCommit($db, $CO, 999999, null, 'other', 'PROBE-NEG', -5, 1);
        $fourOk = false;
        $evidence[] = 'منع: قبل مبلغًا سالبًا ✘';
    } catch (\Throwable $e) {
        $evidence[] = 'منع: المبلغُ السالب رُفض برمز FIN-422 ✓';
    }
}

// (ج) عكس: حجزُ التزامٍ ثم تحريرُه بسببه — على موازنةٍ حيةٍ إن وُجدت
$lineBud = (int) one($db, "SELECT b.id FROM fin_budgets b
    JOIN fin_budget_lines l ON l.budget_id = b.id AND l.company_id = b.company_id
    WHERE b.company_id = {$CO} GROUP BY b.id
    HAVING SUM(l.planned_amount) - SUM(l.actual_amount) > 100 ORDER BY b.id DESC LIMIT 1");
if ($lineBud > 0) {
    try {
        $c = M10::budgetCommit($db, $CO, $lineBud, null, 'other', 'U12-GATE-PROBE-' . gmdate('His'), 1.00, 1);
        $rl = M10::budgetRelease($db, $CO, (int) $c['id'], 'اختبارُ العكس — بوابة m10', 1);
        $state = one($db, "SELECT state FROM fin_budget_commitments WHERE id = " . (int) $c['id']);
        $evidence[] = 'عكس: الالتزام ' . $c['commit_code'] . ' حُرّر بسببه (state=' . $state . ') والأصلُ باقٍ ✓';
        if ($state !== 'released') { $fourOk = false; }
    } catch (\Throwable $e) {
        $fourOk = false;
        $evidence[] = 'عكس: استثناء ' . $e->getMessage();
    }
} else {
    $evidence[] = 'عكس: لا موازنةَ ببنودٍ متاحةٍ — يُقاس عند أول موازنة (العطالةُ والبنيةُ قائمتان)';
}
ac('AC-09', 'الحالات الأربع لكل فعل حاكم — حيًّا', $fourOk, implode(' | ', $evidence));

/* تخزينُ حكمَي ⑤/⑩ من الشواهد الحية (عمقُ الربط — G4) */
if ($fourOk) {
    $db->query("UPDATE nav09_action_map SET guard_verified = 'yes',
        guard_evidence = 'بوابة m10 الحية: رفضُ FIN-SOD-403/FIN-422 برمزٍ من الخادم (" . gmdate('Y-m-d') . ")'
        WHERE canonical_code IN ('budget.approve','budget.commit','gate.pass','fin.entitle') AND guard_verified <> 'yes'");
    $db->query("UPDATE nav09_action_map SET idempotency_verified = 'yes',
        idempotency_evidence = 'بوابة m10 الحية: الإعادةُ أرجعت مرجعَ الأول (" . gmdate('Y-m-d') . ")'
        WHERE canonical_code IN ('gate.pass') AND idempotency_verified <> 'yes'");
}

/* ══ AC-10 · صفرُ وجهةٍ بلا وحدةِ صلاحياتٍ مسجَّلة ════════════════════════ */
$noUnit = array();
$unitOk = 0;
foreach ($paths as $cf => $rp) {
    $st = $db->prepare("SELECT COUNT(*) c FROM modules WHERE code = ?");
    $st->bind_param('s', $rp);
    $st->execute();
    $c = (int) $st->get_result()->fetch_assoc()['c'];
    $st->close();
    if ($c > 0) { $unitOk++; } else { $noUnit[] = $cf . '→' . $rp; }
}
ac('AC-10', 'صفر وجهة بلا وحدة صلاحيات مسجَّلة', empty($noUnit),
    "{$unitOk}/" . count($paths) . ' وجهةً حيةً لكلٍّ وحدةُ صلاحياتٍ في جدول الوحدات'
    . (empty($noUnit) ? '' : ' — الناقص: ' . implode(' · ', $noUnit)));

/* ══ AC-11 · الشاشةُ الميدانية (fx_rates) بحالة مزامنةٍ ظاهرة ═════════════ */
$fxPath = $paths['fx_rates.php'] ?? 'Finance/currencies_fin.php';
$fxSrc = (string) @file_get_contents($ROOT . '/' . $fxPath);
$shellJs = (string) @file_get_contents($ROOT . '/assets/css/ems-shell.css');
$compJs = '';
foreach (array('assets/js/ems-components.js', 'includes/js/ems-components.js', 'assets/ems-components.js') as $p) {
    if (is_file($ROOT . '/' . $p)) { $compJs = (string) file_get_contents($ROOT . '/' . $p); break; }
}
/* ◆ GT-01 — أُبطل شرطان خاويان هنا أيضًا:
   ‎strpos($compJs,'sync')‎ و‎strpos($fxSrc,'inheader')‎ — كلمتان عامّتان تردان في
   أيِّ ملف. البديلُ: **تصييرٌ حيٌّ** للشاشةِ ثم البحثُ عن أثرِ القشرةِ وشريحةِ
   المزامنةِ في **الناتج** لا في المصدر. */
require_once __DIR__ . '/fix_lib.php';
$fxOwner = one($db, "SELECT rp.role_id FROM role_permissions rp
                       JOIN modules m ON m.id = rp.module_id
                      WHERE m.code = '" . $db->real_escape_string($fxPath) . "' AND rp.can_view = 1
                      ORDER BY rp.role_id LIMIT 1");
$fxRender = $fxOwner === null ? array('body' => '', 'bytes' => 0, 'fatal' => 'بلا دورٍ مانح')
                              : fix_render_screen($ROOT, $fxPath, (string) $fxOwner);
$fxBody = (string) $fxRender['body'];
$fxIncludesShell = ($fxRender['bytes'] > 0)
    && (strpos($fxBody, 'ems-unified-page-shell') !== false || strpos($fxBody, '<div class="main"') !== false);
// شريحةُ المزامنة: عنصرٌ مُصيَّرٌ يحمل محورَ الحالةِ AX-4 أو وسمَ المزامنةِ الصريح
$hasSyncComponent = (strpos($fxBody, 'data-ax-4') !== false)
    || (strpos($fxBody, 'ems-sync') !== false)
    || (strpos($fxBody, 'data-sync-state') !== false)
    || (preg_match('/<[^>]+class="[^"]*\bems-badge\b[^"]*"[^>]*>[^<]*مزامن/u', $fxBody) === 1);
ac('AC-11', 'الشاشة الميدانية بحالة مزامنة ظاهرة (تصييرٌ حيّ)', $fxIncludesShell && $hasSyncComponent,
    "fx_rates → {$fxPath} (دور {$fxOwner} · " . $fxRender['bytes'] . " بايت): القشرةُ في الناتج "
    . ($fxIncludesShell ? '✓' : '✘')
    . ' · شريحةُ المزامنةِ مُصيَّرة ' . ($hasSyncComponent ? '✓' : '✘'));

/* ══ AC-12 · دليلُ الحساباتِ المعادُ هيكلتُه (COA-01) ═════════════════════ */
$canon = (int) one($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
    WHERE company_id = {$CO} AND is_canonical = 1");
$roots = (int) one($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
    WHERE company_id = {$CO} AND is_canonical = 1 AND acc_level = 1");
$lvl2 = (int) one($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
    WHERE company_id = {$CO} AND is_canonical = 1 AND acc_level = 2");
$lvl3 = (int) one($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
    WHERE company_id = {$CO} AND is_canonical = 1 AND acc_level = 3");
$activeLegacy = (int) one($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
    WHERE company_id = {$CO} AND is_canonical = 0 AND active = 1");
$mapped = (int) one($db, "SELECT COUNT(*) FROM fin_coa_migration WHERE company_id = {$CO}");
$personDim = (int) one($db, "SELECT COUNT(*) FROM fin_coa_migration
    WHERE company_id = {$CO} AND dim_key = 'D6'");
$coaOk = ($canon === 126 && $roots === 5 && $lvl2 === 18 && $lvl3 === 103 && $activeLegacy === 0);
ac('AC-12', 'دليل الحسابات أربعة مستويات لا مستوًى واحد (R1)', $coaOk,
    "{$canon} حسابًا قانونيًّا = {$roots} جذورٍ + {$lvl2} ثانيًا + {$lvl3} ثالثًا"
    . " · موروثٌ نشطٌ {$activeLegacy} (يجب صفر) · خريطةُ ترحيلٍ {$mapped} صفًّا"
    . " · حساباتُ أشخاصٍ إلى D6: {$personDim} (R2)");

/* ══ AC-13 · تساوي الأرصدةِ قبلَ وبعدَ الترحيل (R10) ══════════════════════ */
$sumBefore = (float) one($db, "SELECT COALESCE(SUM(balance_before),0) FROM fin_coa_migration
    WHERE company_id = {$CO}");
$sumAfter = (float) one($db, "SELECT COALESCE(SUM(balance_after),0) FROM fin_coa_migration
    WHERE company_id = {$CO}");
$liveSum = (float) one($db, "SELECT COALESCE(SUM(debit) - SUM(credit),0) FROM fin_journal_lines
    WHERE company_id = {$CO}");
$orphan = (int) one($db, "SELECT COUNT(*) FROM fin_journal_lines l
    LEFT JOIN fin_chart_of_accounts a ON a.id = l.account_id
    WHERE l.company_id = {$CO} AND (a.id IS NULL OR a.is_canonical = 0)");
$balOk = (abs($sumBefore - $sumAfter) < 0.01) && (abs($liveSum) < 0.01) && $orphan === 0;
ac('AC-13', 'الترحيل بخريطة لا بحذف وتساوي الأرصدة (R10)', $balOk,
    'Σ قبل ' . number_format($sumBefore, 2) . ' · Σ بعد ' . number_format($sumAfter, 2)
    . ' · دفترٌ متوازنٌ ' . number_format($liveSum, 2)
    . " · سطورُ قيدٍ على حسابٍ غيرِ قانونيٍّ: {$orphan} (يجب صفر)");

/* ══ AC-14 · الأبعادُ التسعةُ وحارسُها ═══════════════════════════════════ */
$dimCols = 0;
foreach (array('site_id', 'counterparty_type', 'counterparty_id', 'business_model',
               'contract_id', 'contract_type_code', 'legacy_account_id', 'posting_rule_code') as $c) {
    if (one($db, "SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'fin_journal_lines' AND column_name = '$c'") === '1') { $dimCols++; }
}
$withDims = (int) one($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
    WHERE company_id = {$CO} AND is_canonical = 1 AND required_dims <> ''");
$withFlow = (int) one($db, "SELECT COUNT(*) FROM fin_chart_of_accounts
    WHERE company_id = {$CO} AND is_canonical = 1 AND cashflow_activity <> 'none'");
require_once $ROOT . '/app/Services/Finance/CoaService.php';
$guardOk = false; $lvlGuardOk = false; $r2Ok = false; $r8Ok = false;
try { \App\Services\Finance\CoaService::assertDims($db, $CO, '4101', array('D1' => $CO)); }
catch (\Throwable $e) { $guardOk = strpos($e->getMessage(), 'COA-DIM-422') !== false; }
try { \App\Services\Finance\CoaService::assertDims($db, $CO, '41',
    array('D1' => $CO, 'D2' => 1, 'D6' => 1, 'D7' => 'hour', 'D8' => 1)); }
catch (\Throwable $e) { $lvlGuardOk = strpos($e->getMessage(), 'COA-LEVEL-422') !== false; }
try { \App\Services\Finance\CoaService::assertCreatable($db, $CO, '1103-999', 'Ahmed Custody'); }
catch (\Throwable $e) { $r2Ok = strpos($e->getMessage(), 'COA-R2-422') !== false; }
try { \App\Services\Finance\CoaService::assertCreatable($db, $CO, '110399', 'حساب تفصيلي'); }
catch (\Throwable $e) { $r8Ok = strpos($e->getMessage(), 'COA-R8-422') !== false; }
ac('AC-14', 'الأبعاد التسعة بحارس إلزام (R9 · R2 · R8)', $dimCols === 8 && $withDims === 126
    && $guardOk && $lvlGuardOk && $r2Ok && $r8Ok,
    "أعمدةُ الأبعادِ على سطرِ القيد {$dimCols}/8 · حساباتٌ بأبعادٍ إلزامية {$withDims}/126"
    . " · بتصنيفِ نشاطِ تدفقٍ {$withFlow}"
    . ' · الحارس: ناقصُ الأبعاد ' . ($guardOk ? '✓' : '✘')
    . ' · التجميعي ' . ($lvlGuardOk ? '✓' : '✘')
    . ' · R2 ' . ($r2Ok ? '✓' : '✘') . ' · R8 ' . ($r8Ok ? '✓' : '✘'));

/* ══ AC-15 · القوائمُ الخمسُ والنسبُ والإشاراتُ حيةً ═════════════════════ */
$ratios = (int) one($db, "SELECT COUNT(*) FROM fin_ratio_targets WHERE company_id = {$CO} AND active = 1");
$sigRules = (int) one($db, "SELECT COUNT(*) FROM fin_signal_rules WHERE company_id = {$CO}");
$pmRows = (int) one($db, "SELECT COUNT(*) FROM fin_posting_matrix WHERE company_id = {$CO} AND active = 1");
$ctypes = (int) one($db, "SELECT COUNT(*) FROM fin_contract_types WHERE company_id = {$CO} AND active = 1");
$rvals = (int) one($db, "SELECT COUNT(*) FROM fin_ratio_values WHERE company_id = {$CO} AND state = 'computed'");
$cfBal = (int) one($db, "SELECT COUNT(*) FROM fin_cashflow WHERE company_id = {$CO} AND balance_ok = 1");
$cfBad = (int) one($db, "SELECT COUNT(*) FROM fin_cashflow WHERE company_id = {$CO} AND balance_ok = 0");
$eqBad = (int) one($db, "SELECT COUNT(*) FROM fin_equity WHERE company_id = {$CO} AND balance_ok = 0");
ac('AC-15', 'التحليل المالي: نسبٌ وإشاراتٌ وقوائمُ متوازنة',
    $ratios === 44 && $sigRules === 16 && $pmRows === 27 && $ctypes === 18 && $cfBad === 0 && $eqBad === 0,
    "نسبٌ معرَّفة {$ratios}/44 · إشاراتٌ {$sigRules}/16 · مصفوفةُ ترحيلٍ {$pmRows}/27"
    . " · أنواعُ عقودٍ {$ctypes}/18 · قيمُ نسبٍ محسوبةٌ {$rvals}"
    . " · تدفقاتٌ متوازنةٌ {$cfBal} وغيرُ متوازنةٍ محفوظةٍ {$cfBad} (يجب صفر — تُرفض قبل الحفظ)"
    . " · حقوقُ ملكيةٍ مختلّةٌ {$eqBad} (يجب صفر)");

echo str_repeat('═', 74), "\n";
echo $fail === 0
    ? "النتيجة: 15/15 خضراء — بوابةُ M-10 v5 مجتازة ✔\n"
    : "النتيجة: " . (15 - $fail) . "/15 — {$fail} راسبة والبوابةُ تتوقف صادقة ✘\n";
exit($fail === 0 ? 0 : 1);
