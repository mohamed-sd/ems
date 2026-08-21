<?php
/**
 * tools/u12_setup.php — تسجيلُ حزمةِ update0012 (M-10 + M-14) في المنصة
 * ───────────────────────────────────────────────────────────────────────────
 * ما يسجّله (كلُّه idempotent — يُعاد تشغيلُه بلا أثرٍ مزدوج):
 *   ① وحداتُ الصلاحياتِ الستُّ الجديدة (AC-10):
 *      Finance/entitlement.php · Finance/gov_dept_fin.php ·
 *      Governance/guard_denials.php · Governance/gov_dept_gov.php ·
 *      Risk/risk_dept_fin.php · Risk/risk_dept_gov.php
 *   ② المنحُ بالأدوار — بفصل الواجبات: التوليدُ للمالية (17) كتابةً وبقيةُ
 *      أدوارها قراءةً · مراجعةُ المنعِ للحوكمة (15) · شاشتا المخاطر النطاقية
 *      قراءةً + إبلاغًا لأدوار الإدارتين · والرئيسُ (9) قراءةً · صفرُ حذفٍ للجميع.
 *   ③ nav09_file_map: الأسماءُ القانونيةُ الست تصير live بمساراتها الحقيقية —
 *      وentitlement.php يفكُّ خطأَ ربطِه القديم ببوابة الفحص.
 *   ④ nav09_action_map: قلبُ الأفعالِ المبنيةِ في هذه الجولة إلى bound_page
 *      بمعالجها الحي (④ Handler_binding) — والحكمان ⑤/⑩ يبقيان pending حتى
 *      يشهد فحصُ البوابةِ الحي (لا يُدَّعى ما لم يُقَس — UXR-0131).
 *
 * التشغيل: php tools/u12_setup.php
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

$m = new mysqli('127.0.0.1', 'root', '', 'equipation_manage', 3307);
if ($m->connect_error) { fwrite(STDERR, $m->connect_error . "\n"); exit(1); }
$m->set_charset('utf8mb4');
$out = array();
function say(&$out, $s) { $out[] = $s; echo $s . "\n"; }

/* ══ ① وحداتُ الصلاحيات الست ═════════════════════════════════════════════ */
$screens = array(
    array('توليد المستحق من العمل المعتمد', 'Finance/entitlement.php', 17, 'fa fa-diagram-project', 271),
    array('حوكمة المالية والخزينة', 'Finance/gov_dept_fin.php', 17, 'fa fa-scale-balanced', 272),
    array('المحاولات الممنوعة', 'Governance/guard_denials.php', 15, 'fa fa-hand', 246),
    array('حوكمة الحوكمة والالتزام', 'Governance/gov_dept_gov.php', 15, 'fa fa-scale-unbalanced-flip', 247),
    array('المخاطر المالية', 'Risk/risk_dept_fin.php', 17, 'fa fa-building-shield', 521),
    array('مخاطر الحوكمة والالتزام', 'Risk/risk_dept_gov.php', 15, 'fa fa-building-shield', 522),
);
$modIds = array();
$r = $m->query("SELECT id, code FROM modules");
while ($x = $r->fetch_assoc()) { $modIds[$x['code']] = (int) $x['id']; }
foreach ($screens as $s) {
    if (isset($modIds[$s[1]])) { say($out, "وحدة {$s[1]}: قائمة #{$modIds[$s[1]]}"); continue; }
    $st = $m->prepare("INSERT INTO modules (name, code, owner_role_id, is_link, is_quick, icon, display_order)
                       VALUES (?, ?, ?, 1, 0, ?, ?)");
    $st->bind_param('ssisi', $s[0], $s[1], $s[2], $s[3], $s[4]);
    $st->execute();
    $modIds[$s[1]] = (int) $m->insert_id;
    say($out, "وحدة صلاحيات {$s[1]}: #{$modIds[$s[1]]}");
    $st->close();
}

/* ══ ② المنحُ بالأدوار — بفصل الواجبات وصفرِ حذف ═════════════════════════ */
function grant($m, $roleId, $moduleId, $v, $a, $e) {
    $r = $m->query("SELECT COUNT(*) c FROM role_permissions WHERE role_id = $roleId AND module_id = $moduleId");
    if ((int) $r->fetch_assoc()['c'] > 0) { return false; }
    $m->query("INSERT INTO role_permissions (role_id, module_id, can_view, can_add, can_edit, can_delete)
               VALUES ($roleId, $moduleId, $v, $a, $e, 0)");
    return true;
}
$g = 0;
$finRoles = array(17, 18, 19, 20, 21, 22);
// توليدُ المستحق: المديرُ المالي (17) كتابة — والبقيةُ قراءة (فصل §9-3)
$g += grant($m, 17, $modIds['Finance/entitlement.php'], 1, 1, 1) ? 1 : 0;
foreach (array(18, 19, 20, 21, 22) as $rr) { $g += grant($m, $rr, $modIds['Finance/entitlement.php'], 1, 0, 0) ? 1 : 0; }
// حوكمةُ المالية: 17 عرضٌ وتصديق (edit) — والحوكمةُ (15) والرئيسُ (9) قراءة
$g += grant($m, 17, $modIds['Finance/gov_dept_fin.php'], 1, 0, 1) ? 1 : 0;
$g += grant($m, 15, $modIds['Finance/gov_dept_fin.php'], 1, 0, 0) ? 1 : 0;
$g += grant($m, 9, $modIds['Finance/gov_dept_fin.php'], 1, 0, 0) ? 1 : 0;
// المحاولاتُ الممنوعة: الحوكمةُ (15) مراجعةً — والرئيسُ قراءة
$g += grant($m, 15, $modIds['Governance/guard_denials.php'], 1, 1, 1) ? 1 : 0;
$g += grant($m, 9, $modIds['Governance/guard_denials.php'], 1, 0, 0) ? 1 : 0;
// حوكمةُ الحوكمة: 15 عرضٌ وتصديق — والرئيسُ قراءة
$g += grant($m, 15, $modIds['Governance/gov_dept_gov.php'], 1, 0, 1) ? 1 : 0;
$g += grant($m, 9, $modIds['Governance/gov_dept_gov.php'], 1, 0, 0) ? 1 : 0;
// المخاطرُ المالية النطاقية: أدوارُ المالية قراءةً + إبلاغًا (add) — والمخاطرُ (28/29) والرئيسُ قراءةً كاملة
foreach ($finRoles as $rr) { $g += grant($m, $rr, $modIds['Risk/risk_dept_fin.php'], 1, 1, 0) ? 1 : 0; }
foreach (array(9, 28, 29, 30) as $rr) { $g += grant($m, $rr, $modIds['Risk/risk_dept_fin.php'], 1, 0, 0) ? 1 : 0; }
// مخاطرُ الحوكمة النطاقية: 15 قراءةً + إبلاغًا — والمخاطرُ والرئيس قراءة
$g += grant($m, 15, $modIds['Risk/risk_dept_gov.php'], 1, 1, 0) ? 1 : 0;
foreach (array(9, 28, 29, 30) as $rr) { $g += grant($m, $rr, $modIds['Risk/risk_dept_gov.php'], 1, 0, 0) ? 1 : 0; }
say($out, "منحٌ أُضيفت: $g");

/* ══ ③ nav09_file_map — الأسماءُ القانونيةُ تصير live ═════════════════════ */
$fileMap = array(
    array('entitlement.php', 'توليد المستحق من العمل المعتمد', 'المالية والخزينة', 'Finance/entitlement.php'),
    array('gov_dept_fin.php', 'حوكمة المالية والخزينة', 'المالية والخزينة', 'Finance/gov_dept_fin.php'),
    array('risk_dept_fin.php', 'المخاطر المالية', 'المالية والخزينة', 'Risk/risk_dept_fin.php'),
    array('guard_denials.php', 'المحاولات الممنوعة', 'الحوكمة والالتزام', 'Governance/guard_denials.php'),
    array('gov_dept_gov.php', 'حوكمة الحوكمة والالتزام', 'الحوكمة والالتزام', 'Governance/gov_dept_gov.php'),
    array('risk_dept_gov.php', 'مخاطر الحوكمة والالتزام', 'الحوكمة والالتزام', 'Risk/risk_dept_gov.php'),
);
foreach ($fileMap as $f) {
    $st = $m->prepare("INSERT INTO nav09_file_map (canonical_file, title_ar, owner_dept, state, real_path, note, updated_at)
                       VALUES (?, ?, ?, 'live', ?, 'update0012: بُنيت حية بمسارها القانوني', NOW())
                       ON DUPLICATE KEY UPDATE state = 'live', real_path = VALUES(real_path),
                           note = VALUES(note), updated_at = NOW()");
    $st->bind_param('ssss', $f[0], $f[1], $f[2], $f[3]);
    $st->execute();
    say($out, "خريطة الملفات {$f[0]} → {$f[3]} (live)");
    $st->close();
}

/* ══ ④ nav09_action_map — قلبُ المبنيِّ إلى bound_page بمعالجه الحي ═══════ */
$bind = array(
    'fin.entitle'       => 'Finance/fin_m10_actions.php::entitle_generate',
    'gate.pass'         => 'Finance/fin_m10_actions.php::gate_pass',
    'budget.commit'     => 'Finance/fin_m10_actions.php::budget_commit',
    'budget.approve'    => 'Finance/fin_m10_actions.php::budget_approve',
    'budget.request'    => 'Finance/fin_m10_actions.php::budget_change_request',
    'stmt.client.issue' => 'Finance/fin_m10_actions.php::stmt_client_issue',
    'margin.compute'    => 'Finance/fin_m10_actions.php::margin_compute',
    'cycle.measure'     => 'Finance/fin_m10_actions.php::cycle_measure',
    'trace.follow'      => 'FinRequests/effect_map.php (قارئ — صفحته الحية)',
    'approval.reject'   => 'Governance/gov_m14_actions.php::approval_reject',
    'approval.return'   => 'Governance/gov_m14_actions.php::approval_return',
    'denial.review'     => 'Governance/gov_m14_actions.php::denial_review',
    'org.change'        => 'Governance/gov_m14_actions.php::org_change',
);
$flipped = 0;
foreach ($bind as $code => $live) {
    $st = $m->prepare("UPDATE nav09_action_map SET state = 'bound_page', live_code = ?, updated_at = NOW()
                        WHERE canonical_code = ? AND state = 'declared_unbuilt'");
    $st->bind_param('ss', $live, $code);
    $st->execute();
    $flipped += $m->affected_rows;
    $st->close();
}
say($out, "أفعالٌ قُلبت bound_page: $flipped");

/* rpt.export على dept_achievement — قارئُ إطار Excel الموحد (بوابة /excel.php) */
$st = $m->prepare("UPDATE nav09_action_map SET state = 'bound_page',
                          live_code = 'excel.php (إطار التصدير الموحد — سجل تسعة بنود)', updated_at = NOW()
                    WHERE canonical_code = 'rpt.export' AND canonical_file = 'dept_achievement.php'
                      AND state = 'declared_unbuilt'");
$st->execute();
say($out, 'rpt.export (dept_achievement): ' . ($m->affected_rows ? 'رُبط بإطار التصدير' : 'قائم'));
$st->close();

/* العطالة المثبتة بنيويًّا للأفعال المبنية هنا (مفاتيح uq في جداولها) */
$idem = array('fin.entitle', 'gate.pass', 'budget.commit', 'stmt.client.issue', 'denial.review');
foreach ($idem as $code) {
    $st = $m->prepare("UPDATE nav09_action_map
                          SET idempotency_verified = 'yes',
                              idempotency_evidence = 'مفتاح UNIQUE في جدوله — الإعادة ترجع الأول (اختبار m10/m14 gate)'
                        WHERE canonical_code = ? AND idempotency_verified = 'pending'");
    $st->bind_param('s', $code);
    $st->execute();
    $st->close();
}
say($out, 'أحكامُ عطالةٍ أولية سُجلت للخمسة ذات المفاتيح البنيوية');

/* ══ ⑤ الحقولُ الحساسةُ لجداول update0012 في قاموس الحوكمة (AC-06) ════════ */
$sens = array(
    array('U12-FIN-01', 'fin_entitlements', 'client_amount', 'قيمُ الأثرِ المالي تُحجب من الخادم لغير المخوَّل'),
    array('U12-FIN-02', 'fin_entitlements', 'supplier_amount', 'قيمةُ استحقاق المورد حساسة'),
    array('U12-FIN-03', 'fin_entitlements', 'operator_amount', 'أجرُ المشغّل من بيانات الأجور'),
    array('U12-FIN-04', 'fin_entitlements', 'fx_rate', 'سعرُ الصرف ومصدره'),
    array('U12-FIN-05', 'fin_entitlement_gate_log', 'impact_amount', 'قيمةُ الأثر قبل التوليد'),
    array('U12-FIN-06', 'fin_client_statements', 'opening_balance', 'رصيدُ أول المدة'),
    array('U12-FIN-07', 'fin_client_statements', 'closing_balance', 'رصيدُ آخر المدة'),
    array('U12-FIN-08', 'fin_margin_analysis', 'margin', 'الهامشُ محجوبٌ عن التشغيل بنيويًّا'),
    array('U12-FIN-09', 'fin_margin_analysis', 'cost_operators', 'تكلفةُ المشغّلين من بيانات الأجور'),
    array('U12-FIN-10', 'fin_budget_commitments', 'available_before', 'المتاحُ قبل الحجز'),
    array('U12-GOV-01', 'gov_approval_decisions', 'reason_note', 'تسبيبُ القرار قد يحمل تفاصيلَ مستندٍ حساس'),
);
$sc = 0;
foreach ($sens as $s) {
    $st = $m->prepare("INSERT INTO scr_sensitive_fields
        (company_id, no_policy, table_name, field_name, classification_sensitivity,
         reason_classification, from_visible_to, policy_masking, log_views_flag, exportable_flag,
         date_effective, status, is_seed, created_by, created_by_name, created_at)
        SELECT 4, ?, ?, ?, 'سري — منح فردي', ?, 'المدير المالي والحوكمة بمنح فردي', 'يُحجب من الخادم',
               'نعم', 'لا', CURDATE(), 'معتمد', 0, 1, 'update0012', NOW() FROM DUAL
         WHERE NOT EXISTS (SELECT 1 FROM scr_sensitive_fields
                            WHERE company_id = 4 AND table_name = ? AND field_name = ?)");
    $st->bind_param('ssssss', $s[0], $s[1], $s[2], $s[3], $s[1], $s[2]);
    $st->execute();
    $sc += $m->affected_rows > 0 ? 1 : 0;
    $st->close();
}
say($out, "سياساتُ حقولٍ حساسةٍ سُجلت: $sc");

say($out, 'اكتمل تسجيل update0012.');
