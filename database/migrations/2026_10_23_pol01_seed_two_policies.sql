-- ═══════════════════════════════════════════════════════════════════════════
-- POL-01 §7–§8: سياستان نافذتان على الإطار السباعي (شرط البوابة ① — المهمة ⑨)
-- ───────────────────────────────────────────────────────────────────────────
-- ① sales (المبيعات/التشغيل): المصفوفة الأم CON-02 §5 — الحالات الست بأطرافها
--   الثلاثة (يُفوتر للعميل؟ وحدة للمورد؟ أجر للمشغّل؟) + سلسلة الاعتماد الخمسية.
-- ② suppliers (الموردون): الست نفسها + نقص جاهزية + عجز إحلال — مشتقة من الأم
--   (derived_from) فالإسناد واحد والأثر يتبعه، ولا ثلاث قوائم أسباب.
-- الدورية (DEC-PENDING · توصية §12-⑨): يومية للحرج، أسبوعية للمعتاد، شهرية
-- للمستقر — هنا أسبوعية للسياسة العامة وقابلة للضبط لكل عقد/موقع بسياسة نطاق.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① سياسة المبيعات/التشغيل (الشركة 4 — الإدارة كلها)
INSERT INTO `dept_policies` (`company_id`, `domain`, `name_ar`, `scope_type`, `scope_id`, `valid_from`, `state`, `created_by`)
SELECT 4, 'sales', 'سياسة التشغيل والمبيعات — المصفوفة الأم', 'department', 0, '2026-08-01', 'active', 1
 WHERE NOT EXISTS (SELECT 1 FROM `dept_policies` WHERE company_id=4 AND domain='sales' AND scope_type='department' AND scope_id=0 AND valid_from='2026-08-01');

SET @P_SALES = (SELECT policy_id FROM dept_policies WHERE company_id=4 AND domain='sales' AND scope_type='department' AND scope_id=0 AND valid_from='2026-08-01');

-- المصفوفة الأم: الحالات الست × الأطراف الثلاثة (CON-02 §5)
INSERT IGNORE INTO `impact_matrix` (`policy_id`, `state_code`, `party_type`, `effect`, `derived_from`) VALUES
  (@P_SALES, 'actual_operation',   'client',   'billable',  'CON-02 §5'),
  (@P_SALES, 'actual_operation',   'supplier', 'countable', 'CON-02 §5'),
  (@P_SALES, 'actual_operation',   'operator', 'payable',   'CON-02 §5'),
  (@P_SALES, 'standby_client',     'client',   'billable',  'CON-02 §5'),
  (@P_SALES, 'standby_client',     'supplier', 'countable', 'CON-02 §5'),
  (@P_SALES, 'standby_client',     'operator', 'payable',   'CON-02 §5'),
  (@P_SALES, 'stop_supplier',      'client',   'none',      'CON-02 §5'),
  (@P_SALES, 'stop_supplier',      'supplier', 'penalized', 'CON-02 §5'),
  (@P_SALES, 'stop_supplier',      'operator', 'payable',   'CON-02 §5'),
  (@P_SALES, 'stop_company',       'client',   'none',      'CON-02 §5'),
  (@P_SALES, 'stop_company',       'supplier', 'countable', 'CON-02 §5'),
  (@P_SALES, 'stop_company',       'operator', 'payable',   'CON-02 §5'),
  (@P_SALES, 'force_majeure',      'client',   'none',      'CON-02 §5'),
  (@P_SALES, 'force_majeure',      'supplier', 'none',      'CON-02 §5'),
  (@P_SALES, 'force_majeure',      'operator', 'payable',   'CON-02 §5'),
  (@P_SALES, 'coverage_shortfall', 'client',   'none',      'CON-02 §5'),
  (@P_SALES, 'coverage_shortfall', 'supplier', 'penalized', 'CON-02 §5'),
  (@P_SALES, 'coverage_shortfall', 'operator', 'none',      'CON-02 §5');

-- سلسلة اعتماد الوحدة الخمسية (POL-01 §4) — أسبوعية، والموردون والقوى تُتخطى إن لم تنطبق
INSERT IGNORE INTO `approval_chains` (`policy_id`, `seq_no`, `approver_role`, `periodicity`, `sla_hours`, `skip_if_not_applicable`) VALUES
  (@P_SALES, 1, 'site',       'weekly', 24, 0),
  (@P_SALES, 2, 'operations', 'weekly', 24, 0),
  (@P_SALES, 3, 'suppliers',  'weekly', 48, 1),
  (@P_SALES, 4, 'workforce',  'weekly', 48, 1),
  (@P_SALES, 5, 'finance',    'weekly', 72, 0);

-- قواعد وخصومات نموذجية (الحد الأدنى المضمون وغرامة العجز — بيتها CON-02)
INSERT IGNORE INTO `policy_rules` (`policy_id`, `rule_kind`, `formula_json`, `periodicity`)
VALUES (@P_SALES, 'guaranteed_minimum', JSON_OBJECT('source', 'contract_hour_policies', 'basis', 'monthly_hours'), 'monthly');
INSERT IGNORE INTO `deduction_types` (`policy_id`, `ded_kind`, `formula_json`, `auto_propose`, `requires_approval`)
VALUES (@P_SALES, 'shortfall_penalty', JSON_OBJECT('basis', 'contract_clause'), 1, 1);

-- ② سياسة الموردين
INSERT INTO `dept_policies` (`company_id`, `domain`, `name_ar`, `scope_type`, `scope_id`, `valid_from`, `state`, `created_by`)
SELECT 4, 'suppliers', 'سياسة الموردين — الجاهزية والتغطية والتحميلات', 'department', 0, '2026-08-01', 'active', 1
 WHERE NOT EXISTS (SELECT 1 FROM `dept_policies` WHERE company_id=4 AND domain='suppliers' AND scope_type='department' AND scope_id=0 AND valid_from='2026-08-01');

SET @P_SUP = (SELECT policy_id FROM dept_policies WHERE company_id=4 AND domain='suppliers' AND scope_type='department' AND scope_id=0 AND valid_from='2026-08-01');

-- مصفوفة الموردين: مشتقة من الأم + حالتا الجاهزية والإحلال (أثر المورد والشركة)
INSERT IGNORE INTO `impact_matrix` (`policy_id`, `state_code`, `party_type`, `effect`, `derived_from`) VALUES
  (@P_SUP, 'actual_operation',    'supplier', 'countable', 'sales:actual_operation'),
  (@P_SUP, 'standby_client',      'supplier', 'countable', 'sales:standby_client'),
  (@P_SUP, 'stop_supplier',       'supplier', 'penalized', 'sales:stop_supplier'),
  (@P_SUP, 'stop_company',        'supplier', 'countable', 'sales:stop_company'),
  (@P_SUP, 'force_majeure',       'supplier', 'none',      'sales:force_majeure'),
  (@P_SUP, 'coverage_shortfall',  'supplier', 'penalized', 'sales:coverage_shortfall'),
  (@P_SUP, 'readiness_deficit',   'supplier', 'penalized', NULL),
  (@P_SUP, 'readiness_deficit',   'company',  'none',      NULL),
  (@P_SUP, 'replacement_overdue', 'supplier', 'penalized', NULL),
  (@P_SUP, 'replacement_overdue', 'company',  'none',      NULL);

INSERT IGNORE INTO `approval_chains` (`policy_id`, `seq_no`, `approver_role`, `periodicity`, `sla_hours`, `skip_if_not_applicable`) VALUES
  (@P_SUP, 1, 'site',      'weekly', 24, 0),
  (@P_SUP, 2, 'operations','weekly', 24, 0),
  (@P_SUP, 3, 'suppliers', 'weekly', 48, 0),
  (@P_SUP, 4, 'finance',   'monthly', 72, 0);

INSERT IGNORE INTO `policy_rules` (`policy_id`, `rule_kind`, `formula_json`, `periodicity`)
VALUES (@P_SUP, 'readiness_minimum', JSON_OBJECT('source', 'supplier_capacity', 'basis', 'availability_pct'), 'monthly');
INSERT IGNORE INTO `deduction_types` (`policy_id`, `ded_kind`, `formula_json`, `auto_propose`, `requires_approval`) VALUES
  (@P_SUP, 'readiness_penalty', JSON_OBJECT('basis', 'supplier_penalty_rules'), 1, 1),
  (@P_SUP, 'coverage_penalty',  JSON_OBJECT('basis', 'supplier_penalty_rules'), 1, 1);
