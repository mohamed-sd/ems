-- ═══════════════════════════════════════════════════════════════════════════
-- POL-01 §7: تعبئة سياسات الإدارات الست الباقية (البوابة ② — «والباقي يُملأ هنا»)
-- workforce · fleet · maintenance · procurement · treasury · financiers
-- المضمون من POL-01 §7–§9 نصًّا — والبيت التفصيلي وثيقة كل إدارة.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `dept_policies` (`company_id`, `domain`, `name_ar`, `scope_type`, `scope_id`, `valid_from`, `state`, `created_by`)
SELECT 4, d.domain, d.name_ar, 'department', 0, '2026-08-01', 'active', 1
  FROM (SELECT 'workforce' AS domain, 'سياسة المشغّلين والقوى — الورديات والتناوب والحضور' AS name_ar
        UNION SELECT 'fleet', 'سياسة الأسطول — الجاهزية والعدّادات والوقائية بالساعات'
        UNION SELECT 'maintenance', 'سياسة الصيانة — أوامر العمل والتحميل'
        UNION SELECT 'procurement', 'سياسة المشتريات — المطابقة الثلاثية وحدود السماح'
        UNION SELECT 'treasury', 'سياسة المالية والخزينة — قفل الفترة والمطابقة البنكية'
        UNION SELECT 'financiers', 'سياسة الممولين — الأقساط والسداد المبكر') d
 WHERE NOT EXISTS (SELECT 1 FROM `dept_policies` p WHERE p.company_id=4 AND p.domain=d.domain AND p.scope_type='department' AND p.scope_id=0 AND p.valid_from='2026-08-01');

-- مصفوفة المشغّلين: الرموز بأطرافها الخمسة — مشتقة من قاموس WRK-01 (المرجع الأم للإسناد)
SET @P_WF = (SELECT policy_id FROM dept_policies WHERE company_id=4 AND domain='workforce' AND scope_type='department' AND scope_id=0 AND valid_from='2026-08-01');
INSERT IGNORE INTO `impact_matrix` (`policy_id`, `state_code`, `party_type`, `effect`, `derived_from`) VALUES
  (@P_WF, 'working',   'operator', 'payable',   'WRK-01 §4 رمز 1'),
  (@P_WF, 'working',   'client',   'billable',  'WRK-01 §4 رمز 1'),
  (@P_WF, 'working',   'supplier', 'countable', 'WRK-01 §4 رمز 1'),
  (@P_WF, 'standby',   'operator', 'payable',   'WRK-01 §4 رمز ST'),
  (@P_WF, 'standby',   'client',   'none',      'WRK-01 §4 رمز ST — بالإسناد (القاموس by_attribution)'),
  (@P_WF, 'standby',   'supplier', 'none',      'WRK-01 §4 رمز ST — بالإسناد'),
  (@P_WF, 'unexcused', 'operator', 'penalized', 'WRK-01 §4 رمز A2'),
  (@P_WF, 'unexcused', 'company',  'none',      'WRK-01 §4 رمز A2'),
  (@P_WF, 'excused',   'operator', 'payable',   'WRK-01 §4 رمز A1'),
  (@P_WF, 'unpaid',    'operator', 'none',      'WRK-01 §4 رمز UP — يوقف الاستحقاق');
INSERT IGNORE INTO `approval_chains` (`policy_id`, `seq_no`, `approver_role`, `periodicity`, `sla_hours`, `skip_if_not_applicable`) VALUES
  (@P_WF, 1, 'site', 'weekly', 24, 0), (@P_WF, 2, 'operations', 'weekly', 24, 0),
  (@P_WF, 3, 'workforce', 'weekly', 48, 0), (@P_WF, 4, 'finance', 'monthly', 72, 0);
INSERT IGNORE INTO `deduction_types` (`policy_id`, `ded_kind`, `formula_json`, `auto_propose`, `requires_approval`) VALUES
  (@P_WF, 'late_monthly_total', JSON_OBJECT('basis', 'attendance_policies.late_rule'), 1, 1),
  (@P_WF, 'missing_punch_half_day', JSON_OBJECT('basis', 'attendance_policies.missing_punch_rule'), 1, 1),
  (@P_WF, 'unexcused_daily', JSON_OBJECT('basis', 'payroll_absence_types.A2'), 1, 1),
  (@P_WF, 'advance_installment', JSON_OBJECT('cap', 'net/3', 'note', 'DEC ②: الاختيارية ≤ ثلث الصافي'), 1, 1);

-- الأسطول: لا جزاء مباشر — الأثر تكلفة وإهلاك (POL-01 §7-⑤)
SET @P_FL = (SELECT policy_id FROM dept_policies WHERE company_id=4 AND domain='fleet' AND scope_type='department' AND scope_id=0 AND valid_from='2026-08-01');
INSERT IGNORE INTO `impact_matrix` (`policy_id`, `state_code`, `party_type`, `effect`, `derived_from`) VALUES
  (@P_FL, 'operating',        'company', 'countable', 'POL-01 §8-⑤ — ساعات متراكمة وإهلاك'),
  (@P_FL, 'maintenance_stop', 'company', 'countable', 'POL-01 §8-⑤ — زمن تعطل مسنَد'),
  (@P_FL, 'doc_expired',      'company', 'none',      'POL-01 §8-⑤ — حجب اعتماد'),
  (@P_FL, 'out_of_service',   'company', 'none',      'POL-01 §8-⑤');
INSERT IGNORE INTO `approval_chains` (`policy_id`, `seq_no`, `approver_role`, `periodicity`, `sla_hours`, `skip_if_not_applicable`) VALUES
  (@P_FL, 1, 'operations', 'daily', 24, 0), (@P_FL, 2, 'finance', 'monthly', 72, 0);

-- الصيانة: التحميل على مالك المعدة — بموافقة الصيانة والموردين والمالية
SET @P_MN = (SELECT policy_id FROM dept_policies WHERE company_id=4 AND domain='maintenance' AND scope_type='department' AND scope_id=0 AND valid_from='2026-08-01');
INSERT IGNORE INTO `impact_matrix` (`policy_id`, `state_code`, `party_type`, `effect`, `derived_from`) VALUES
  (@P_MN, 'order_open',    'company',  'countable', 'POL-01 §8-⑥'),
  (@P_MN, 'awaiting_part', 'company',  'countable', 'POL-01 §8-⑥ — M-32'),
  (@P_MN, 'order_closed',  'supplier', 'penalized', 'POL-01 §8-⑥ — تحميل قطع على مالك المعدة'),
  (@P_MN, 'order_closed',  'company',  'countable', 'POL-01 §8-⑥');
INSERT IGNORE INTO `deduction_types` (`policy_id`, `ded_kind`, `formula_json`, `auto_propose`, `requires_approval`)
VALUES (@P_MN, 'owner_charge', JSON_OBJECT('basis', 'supplier_charge_rules'), 1, 1);
INSERT IGNORE INTO `approval_chains` (`policy_id`, `seq_no`, `approver_role`, `periodicity`, `sla_hours`, `skip_if_not_applicable`) VALUES
  (@P_MN, 1, 'operations', 'weekly', 48, 0), (@P_MN, 2, 'suppliers', 'weekly', 48, 1), (@P_MN, 3, 'finance', 'monthly', 72, 0);

-- المشتريات: فروق المطابقة الثلاثية ونواقص الاستلام
SET @P_PR = (SELECT policy_id FROM dept_policies WHERE company_id=4 AND domain='procurement' AND scope_type='department' AND scope_id=0 AND valid_from='2026-08-01');
INSERT IGNORE INTO `impact_matrix` (`policy_id`, `state_code`, `party_type`, `effect`, `derived_from`) VALUES
  (@P_PR, 'received_ok',    'supplier', 'countable', 'POL-01 §7-⑦'),
  (@P_PR, 'match_variance', 'supplier', 'penalized', 'POL-01 §9-⑤ — فروق المطابقة'),
  (@P_PR, 'short_receipt',  'supplier', 'penalized', 'POL-01 §9-⑤ — نواقص الاستلام');
INSERT IGNORE INTO `deduction_types` (`policy_id`, `ded_kind`, `formula_json`, `auto_propose`, `requires_approval`) VALUES
  (@P_PR, 'match_variance', JSON_OBJECT('basis', 'three_way_match'), 1, 1),
  (@P_PR, 'short_receipt', JSON_OBJECT('basis', 'receipt_lines'), 1, 1);
INSERT IGNORE INTO `approval_chains` (`policy_id`, `seq_no`, `approver_role`, `periodicity`, `sla_hours`, `skip_if_not_applicable`) VALUES
  (@P_PR, 1, 'operations', 'weekly', 48, 0), (@P_PR, 2, 'finance', 'monthly', 72, 0);

-- الخزينة: قفل الفترة والمطابقة — شهرية بإقفال
SET @P_TR = (SELECT policy_id FROM dept_policies WHERE company_id=4 AND domain='treasury' AND scope_type='department' AND scope_id=0 AND valid_from='2026-08-01');
INSERT IGNORE INTO `impact_matrix` (`policy_id`, `state_code`, `party_type`, `effect`, `derived_from`) VALUES
  (@P_TR, 'period_open',   'company', 'countable', 'POL-01 §7-⑧'),
  (@P_TR, 'period_locked', 'company', 'none',      'POL-01 §7-⑧ — لا كتابة بعد القفل (M-39)');
INSERT IGNORE INTO `policy_rules` (`policy_id`, `rule_kind`, `formula_json`, `periodicity`) VALUES
  (@P_TR, 'net_protection_third', JSON_OBJECT('cap', 'net/3', 'scope', 'voluntary_only', 'note', 'DEC ②: الجزاءات والغياب خارج الحد'), 'monthly'),
  (@P_TR, 'period_close', JSON_OBJECT('basis', 'fin_financial_periods'), 'monthly');
INSERT IGNORE INTO `approval_chains` (`policy_id`, `seq_no`, `approver_role`, `periodicity`, `sla_hours`, `skip_if_not_applicable`)
VALUES (@P_TR, 1, 'finance', 'monthly', 72, 0);

-- الممولون: السداد وإعادة الجدولة (بيت التفصيل FIN-01 — البوابة ③ تبني جداوله)
SET @P_FN = (SELECT policy_id FROM dept_policies WHERE company_id=4 AND domain='financiers' AND scope_type='department' AND scope_id=0 AND valid_from='2026-08-01');
INSERT IGNORE INTO `impact_matrix` (`policy_id`, `state_code`, `party_type`, `effect`, `derived_from`) VALUES
  (@P_FN, 'paid_on_time',  'financier', 'payable',   'POL-01 §8-④'),
  (@P_FN, 'paid_late',     'company',   'penalized', 'POL-01 §8-④ — غرامة تأخر سداد'),
  (@P_FN, 'early_paid',    'company',   'none',      'POL-01 §8-④ — بقاعدة العقد ببند باسمه'),
  (@P_FN, 'rescheduled',   'financier', 'countable', 'POL-01 §8-④'),
  (@P_FN, 'defaulted',     'company',   'penalized', 'POL-01 §8-④'),
  (@P_FN, 'share_sold',    'financier', 'none',      'POL-01 §8-④ — حصص N-15');
INSERT IGNORE INTO `deduction_types` (`policy_id`, `ded_kind`, `formula_json`, `auto_propose`, `requires_approval`) VALUES
  (@P_FN, 'late_payment_penalty', JSON_OBJECT('basis', 'financing_contract'), 1, 1),
  (@P_FN, 'early_settlement_fee', JSON_OBJECT('basis', 'financing_contract'), 1, 1);
INSERT IGNORE INTO `approval_chains` (`policy_id`, `seq_no`, `approver_role`, `periodicity`, `sla_hours`, `skip_if_not_applicable`) VALUES
  (@P_FN, 1, 'finance', 'monthly', 72, 0);
