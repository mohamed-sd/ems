-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ⑥ · SEC-01 §12/§15 — بنية الشؤون الوظيفية والصلاحيات
-- SEC-07 persons + person_relationships
-- SEC-08 hr_dictionaries + توسيع job_titles + بذر الـ13 عائلة والعلاقات والمستويات
-- SEC-09 person_positions (المستقبل فوق user_capacities وجسر positions — لا ترحيل بيانات)
-- SEC-10 permission_templates + permission_template_versions + template_permissions
-- SEC-11 permission_exceptions + sensitive_access_grants + permission_change_requests
--        + permission_approval_steps (أسلاف exception_* تبقى لGOV-01 — 0 صف فلا ترحيل)
-- SEC-12 sod_conflicts (بذر الثمانية §5) + guard_override_policies (الـ17 §7.2)
--        + sensitive_field_policies
-- SEC-13 effective_permissions + permission_audit_events + permission_review_cycles/lines
--        + founding_mode
-- الأسماء حرفيًّا من §15 — «اسم واحد لكل شيء ولا صيغة بديلة».
-- ═══════════════════════════════════════════════════════════════════════════

-- ── SEC-07 · ① الشخص — سجل للإنسان لا للموظف ────────────────────────────────
CREATE TABLE IF NOT EXISTS persons (
  person_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name    VARCHAR(190) NOT NULL,
  national_ref VARCHAR(60) NULL COMMENT 'مرجع هوية — معرّف دائم لا يُعاد استعماله',
  contact_json JSON NULL,
  docs_json    JSON NULL,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (person_id),
  UNIQUE KEY uq_persons_national (national_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §14: سجل الإنسان — حساب واحد عبر المنصة والصفات متعددة';

-- ── SEC-07 · ② العلاقات الوظيفية — علاقة أو أكثر بكيانها ومدتها ─────────────
CREATE TABLE IF NOT EXISTS person_relationships (
  rel_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id     INT UNSIGNED NOT NULL,
  company_id    INT NOT NULL COMMENT 'الكيان — والعزل يمنع تسرب كيان إلى آخر ولو كان الشخص واحدًا',
  relation_code VARCHAR(40) NOT NULL COMMENT 'hr_dictionaries layer=relation',
  employee_id   INT NULL COMMENT 'جسر صفوف employees — تبقى بياناتِ الموظف الإدارية لا الهوية',
  valid_from    DATE NOT NULL,
  valid_to      DATE NULL COMMENT 'NULL = علاقة قائمة (الدائم) — والمؤقتة بنهاية',
  state         ENUM('active','suspended','ended') NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (rel_id),
  KEY idx_prel_person (person_id, state),
  KEY idx_prel_company (company_id, relation_code, state),
  CONSTRAINT fk_prel_person FOREIGN KEY (person_id) REFERENCES persons (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §14②: موظف المورد لا يُنشأ له موظف داخلي وهمي';

-- ── SEC-08 · ③ قواميس الطبقات الثلاث البسيطة ────────────────────────────────
CREATE TABLE IF NOT EXISTS hr_dictionaries (
  code    VARCHAR(40) NOT NULL,
  name_ar VARCHAR(190) NOT NULL,
  layer   ENUM('relation','family','level') NOT NULL,
  `rank`  INT NULL COMMENT 'للمستوى — درجة السلطة تصاعديًّا',
  active  TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §12: تُضاف قيمها بصف لا بكود';

-- ── SEC-09 · ④ المراكز الوظيفية — الطبقات مجموعةً بنطاق إلزامي ─────────────
CREATE TABLE IF NOT EXISTS person_positions (
  p_id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id         INT UNSIGNED NOT NULL,
  company_id        INT NOT NULL,
  relation_code     VARCHAR(40) NOT NULL,
  family_code       VARCHAR(40) NOT NULL COMMENT 'ولا موظف بلا عائلة (DEC-SEC-F)',
  level_code        VARCHAR(40) NOT NULL,
  title_code        VARCHAR(40) NOT NULL COMMENT 'job_titles.title_code',
  org_unit_id       INT UNSIGNED NULL,
  manager_person_id INT UNSIGNED NULL,
  scope_type        ENUM('company','department','section','unit','project','site','site_group','shift','own_records') NOT NULL,
  scope_id          INT NOT NULL COMMENT 'قيد: لا صف بلا نطاق — الصلاحية بلا نطاق مرفوضة بنيويًّا',
  is_primary        TINYINT(1) NOT NULL DEFAULT 1,
  valid_from        DATE NOT NULL,
  valid_to          DATE NULL,
  state             ENUM('active','suspended','ended') NOT NULL DEFAULT 'active',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (p_id),
  UNIQUE KEY uq_pp_natural (person_id, title_code, scope_type, scope_id, valid_from),
  KEY idx_pp_person (person_id, state),
  KEY idx_pp_company (company_id, state),
  CONSTRAINT fk_pp_person FOREIGN KEY (person_id) REFERENCES persons (person_id),
  CONSTRAINT fk_pp_relation FOREIGN KEY (relation_code) REFERENCES hr_dictionaries (code),
  CONSTRAINT fk_pp_family FOREIGN KEY (family_code) REFERENCES hr_dictionaries (code),
  CONSTRAINT fk_pp_level FOREIGN KEY (level_code) REFERENCES hr_dictionaries (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §12: منع تداخل فترتين لنفس (المسمى×النطاق) يحرسه PositionService — ومركزان مشروعان يبدآن معًا مقبولان';

-- ── SEC-10 · ⑤ القوالب: هوية ثابتة بلا محتوى ولا إصدار ─────────────────────
CREATE TABLE IF NOT EXISTS permission_templates (
  tpl_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tpl_kind   ENUM('relation','family','level','title','assignment') NOT NULL,
  key_code   VARCHAR(60) NOT NULL,
  is_ceiling TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'لقوالب العلاقة: سقف لا أرضية',
  active     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (tpl_id),
  UNIQUE KEY uq_tpl_kind_key (tpl_kind, key_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §4: القالب جدول يُعدل بقرار لا كود يُبرمج';

-- ── SEC-10 · ⑥ إصدارات القالب — مصدر الحقيقة الوحيد للمحتوى والسريان ────────
CREATE TABLE IF NOT EXISTS permission_template_versions (
  ver_id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tpl_id               INT UNSIGNED NOT NULL,
  version              INT NOT NULL,
  effective_from       DATE NULL,
  effective_to         DATE NULL,
  state                ENUM('draft','tested','published','superseded') NOT NULL DEFAULT 'draft',
  approval_ref         VARCHAR(120) NULL,
  change_reason        VARCHAR(255) NULL,
  impact_preview_json  JSON NULL COMMENT 'أثر التغيير قبل النشر: كم مستخدمًا وأي صلاحية',
  superseded_by        INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ver_id),
  UNIQUE KEY uq_ver (tpl_id, version),
  CONSTRAINT fk_ver_tpl FOREIGN KEY (tpl_id) REFERENCES permission_templates (tpl_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §4⑥: لا يُعدل إصدار نافذ بأثر رجعي — النشر إصدار جديد بسريان مستقبلي';

-- ── SEC-10 · ⑦ محتوى النسخة — deny يغلب grant دائمًا ────────────────────────
CREATE TABLE IF NOT EXISTS template_permissions (
  tp_id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_version_id INT UNSIGNED NOT NULL COMMENT 'FK للنسخة لا للقالب — فمحتوى القديمة لا يتغير عند نشر جديدة',
  dimension           ENUM('visibility','action','approval','scope') NOT NULL,
  permission_code     VARCHAR(120) NOT NULL,
  scope_rule          VARCHAR(120) NULL,
  amount_cap          DECIMAL(18,2) NULL,
  currency            VARCHAR(8) NULL,
  effect              ENUM('grant','deny') NOT NULL DEFAULT 'grant',
  PRIMARY KEY (tp_id),
  KEY idx_tp_ver (template_version_id, dimension),
  CONSTRAINT fk_tp_ver FOREIGN KEY (template_version_id) REFERENCES permission_template_versions (ver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §12: الأبعاد الأربعة — وdeny يغلب grant دائمًا';

-- ── SEC-11 · ⑧ الاستثناءات المؤقتة — ولا استثناء مفتوح المدة ────────────────
CREATE TABLE IF NOT EXISTS permission_exceptions (
  ex_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id      INT NOT NULL,
  person_id       INT NOT NULL,
  permission_code VARCHAR(120) NOT NULL,
  scope_rule      VARCHAR(120) NOT NULL,
  effect          ENUM('grant','deny') NOT NULL DEFAULT 'grant',
  reason          VARCHAR(255) NOT NULL,
  valid_from      DATETIME NOT NULL,
  valid_to        DATETIME NOT NULL COMMENT 'إلزامي — ويسقط آليًّا',
  is_break_glass  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'كسر الزجاج: مدة ≤ 24 ساعة بمراجعة لاحقة إلزامية',
  approvals_ref   VARCHAR(120) NULL,
  state           ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ex_id),
  KEY idx_ex_person (company_id, person_id, state),
  KEY idx_ex_expiry (state, valid_to),
  CONSTRAINT chk_bg_24h CHECK (is_break_glass = 0 OR TIMESTAMPDIFF(HOUR, valid_from, valid_to) <= 24)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §8⑥⑦ — والسلفان exception_requests/approvals يبقيان لمسار GOV-01 §7 (0 صف فلا ترحيل)';

-- ── SEC-11 · ⑨ المنح الحساس — وظيفي دائم لا استثناء مؤقت ────────────────────
CREATE TABLE IF NOT EXISTS sensitive_access_grants (
  gr_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id      INT NOT NULL,
  person_id       INT NOT NULL,
  domain          ENUM('ownership','financing','payroll','bank','medical','pricing') NOT NULL,
  permission_code VARCHAR(120) NOT NULL,
  scope_rule      VARCHAR(120) NULL,
  reason          VARCHAR(255) NOT NULL COMMENT 'إلزامي',
  approvals_ref   VARCHAR(120) NULL,
  granted_from    DATE NOT NULL,
  review_due_at   DATE NULL,
  renewal_policy  ENUM('periodic','on_role_change','none') NOT NULL DEFAULT 'periodic',
  state           ENUM('active','suspended','revoked') NOT NULL DEFAULT 'active',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (gr_id),
  KEY idx_sag_person (company_id, person_id, state),
  KEY idx_sag_domain (domain, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §1.1②: دائم ما دامت الوظيفة قائمة · كل قراءة به تُسجَّل · ويُعرض في المراجعة الدورية';

-- ── SEC-11 · ⑩ دورة تغيير الصلاحيات ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS permission_change_requests (
  req_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id  INT NOT NULL,
  person_id   INT NOT NULL,
  change_kind ENUM('within_role','supervisor','section_mgr','dept_mgr_or_high') NOT NULL,
  from_json   JSON NULL,
  to_json     JSON NULL,
  reason      VARCHAR(255) NOT NULL,
  doc_ref     VARCHAR(120) NULL,
  risk_level  ENUM('low','medium','high') NOT NULL COMMENT 'محسوب من النوع',
  state       ENUM('draft','pending','approved','rejected','applied') NOT NULL DEFAULT 'draft',
  created_by  INT NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (req_id),
  KEY idx_pcr_state (company_id, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §8: الموافقات بدرجة المخاطرة لا بمسار واحد';

CREATE TABLE IF NOT EXISTS permission_approval_steps (
  st_id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  req_id             INT UNSIGNED NOT NULL,
  seq_no             INT NOT NULL,
  approver_rule      ENUM('hr','functional_owner','requester_department_manager','finance_owner_if_financial','security_manager','executive') NOT NULL
                     COMMENT 'قاعدة ديناميكية لا دور ثابت — functional_owner يُحل من ORG-01 بحسب المجال والنطاق والتاريخ',
  mandatory          TINYINT(1) NOT NULL DEFAULT 1,
  approver_person_id INT NULL COMMENT 'يُحل لحظة الفتح',
  auth_id            INT UNSIGNED NULL,
  decision           ENUM('approve','reject') NULL,
  reason             VARCHAR(255) NULL,
  at                 DATETIME NULL,
  PRIMARY KEY (st_id),
  UNIQUE KEY uq_step (req_id, seq_no),
  CONSTRAINT fk_step_req FOREIGN KEY (req_id) REFERENCES permission_change_requests (req_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §12: لا تُفتح خطوة قبل سابقتها — يحرسه PermissionChangeWorkflow';

-- ── SEC-12 · ⑪ فصل الواجبات — الثمانية صفوف تُفحص عند الحساب لا بعده ────────
CREATE TABLE IF NOT EXISTS sod_conflicts (
  sod_id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  conflict_code        VARCHAR(40) NOT NULL,
  name_ar              VARCHAR(255) NOT NULL,
  permission_a         VARCHAR(120) NOT NULL,
  permission_b         VARCHAR(120) NOT NULL,
  severity             ENUM('high','critical') NOT NULL DEFAULT 'high',
  compensating_control VARCHAR(255) NULL COMMENT 'الاستثناء بموافقة التنفيذي ورقابة تعويضية معلنة — ولا يُمنح صامتًا',
  active               TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (sod_id),
  UNIQUE KEY uq_sod_code (conflict_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §5: الثمانية صفوف هنا — 409 مع عرض التعارض';

-- ── SEC-12 · ⑫ سياسات تجاوز الحراس — الـ17 صفوف تُقرأ ولا تُستثنى ───────────
CREATE TABLE IF NOT EXISTS guard_override_policies (
  guard_code        VARCHAR(64) NOT NULL,
  name_ar           VARCHAR(190) NOT NULL,
  overridable       ENUM('never','break_glass_only','with_compensating_control') NOT NULL,
  environments_json JSON NULL COMMENT 'بيئات السريان — production·founding·test',
  PRIMARY KEY (guard_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §7.2: الاسم يصف السياسة لا النتيجة — ويقرؤها كسر الزجاج فلا يتجاوز never';

-- ── SEC-12 · ⑬ سياسات الحقول الحساسة — الإخفاء في الخادم لا في العرض ────────
CREATE TABLE IF NOT EXISTS sensitive_field_policies (
  pol_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  field_code         VARCHAR(120) NOT NULL,
  classification     ENUM('payroll','bank','medical','personal','ownership','pricing') NOT NULL,
  masking_rule       ENUM('full','partial','none') NOT NULL DEFAULT 'full',
  allowed_roles_json JSON NULL,
  PRIMARY KEY (pol_id),
  UNIQUE KEY uq_sfp_field (field_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §10⑦: الحقل الذي لا يُملك لا يُجلب أصلًا';

-- ── SEC-13 · ⑭ الصلاحيات المشتقة — يُعاد بناؤه ولا يُحرَّر يدويًّا ──────────
CREATE TABLE IF NOT EXISTS effective_permissions (
  ep_id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id      INT NOT NULL,
  person_id       INT NOT NULL,
  permission_code VARCHAR(120) NOT NULL,
  scope_rule      VARCHAR(120) NOT NULL,
  amount_cap      DECIMAL(18,2) NULL,
  source_kind     ENUM('relation','family','level','title','assignment','exception','grant') NOT NULL,
  source_ref      VARCHAR(120) NOT NULL,
  computed_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ep_id),
  KEY idx_ep_person (company_id, person_id, permission_code),
  KEY idx_ep_code (permission_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §12: جدول مشتق — ومنه يُجاب «لماذا يملكها؟»';

-- ── SEC-13 · ⑮ سجل تغيير الصلاحيات المستقل — Insert-only ────────────────────
CREATE TABLE IF NOT EXISTS permission_audit_events (
  ev_id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id      INT NOT NULL,
  event_type      ENUM('granted','elevated','reduced','revoked','expired','suspended','break_glass') NOT NULL,
  person_id       INT NOT NULL,
  permission_code VARCHAR(120) NOT NULL,
  scope_rule      VARCHAR(120) NULL,
  before_json     JSON NULL,
  after_json      JSON NULL,
  requested_by    INT NULL,
  approved_by     INT NULL,
  executed_by     INT NULL,
  request_ref     VARCHAR(120) NULL,
  reason          VARCHAR(255) NULL,
  source          ENUM('template','assignment','exception','grant','break_glass') NOT NULL,
  founding_mode   TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'وسم أفعال التأسيس §7-④',
  at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ev_id),
  KEY idx_pae_person (company_id, person_id, at),
  KEY idx_pae_type (event_type, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §12: لا يُعدَّل ولا يُحذف — ولا يُخلط بمراجعة المدير الدورية';

-- ── SEC-13 · ⑯ المراجعة الدورية النصف سنوية ─────────────────────────────────
CREATE TABLE IF NOT EXISTS permission_review_cycles (
  cycle_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id        INT NOT NULL,
  org_unit_id       INT UNSIGNED NOT NULL,
  period            VARCHAR(10) NOT NULL COMMENT 'مثال 2026-H2',
  manager_person_id INT NOT NULL,
  due_at            DATE NOT NULL,
  state             ENUM('open','signed','escalated') NOT NULL DEFAULT 'open',
  signed_at         DATETIME NULL,
  PRIMARY KEY (cycle_id),
  UNIQUE KEY uq_prc (org_unit_id, period),
  KEY idx_prc_due (state, due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §10⑥: ما لم يُوقَّع خلال مهلته يُصعَّد للإدارة العامة';

CREATE TABLE IF NOT EXISTS permission_review_lines (
  line_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cycle_id        INT UNSIGNED NOT NULL,
  person_id       INT NOT NULL,
  permission_code VARCHAR(120) NOT NULL,
  scope_rule      VARCHAR(120) NULL,
  decision        ENUM('confirm','reduce','revoke') NULL,
  reason          VARCHAR(255) NULL,
  decided_at      DATETIME NULL,
  PRIMARY KEY (line_id),
  KEY idx_prl_cycle (cycle_id, person_id),
  CONSTRAINT fk_prl_cycle FOREIGN KEY (cycle_id) REFERENCES permission_review_cycles (cycle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §12: سطر لكل (موظف × صلاحية) — Insert-only';

-- ── SEC-13 · ⑰ وضع التأسيس — وضعان ولا enabled=1 بلا ends_at ────────────────
CREATE TABLE IF NOT EXISTS founding_mode (
  mode_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mode        ENUM('discovery','permission_test') NOT NULL,
  enabled     TINYINT(1) NOT NULL DEFAULT 0,
  started_at  DATETIME NULL,
  ends_at     DATETIME NULL COMMENT 'إلزامي عند التفعيل — لا وضع تأسيس مفتوح المدة',
  banner_text VARCHAR(255) NULL,
  closed_by   INT NULL,
  closed_at   DATETIME NULL,
  closure_ref VARCHAR(120) NULL,
  PRIMARY KEY (mode_id),
  UNIQUE KEY uq_fm_mode (mode),
  CONSTRAINT chk_fm_ends CHECK (enabled = 0 OR ends_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SEC-01 §7: التوسيع في discovery وحده — والحراس لا يُعطَّلون مهما اتسع التأسيس';

-- ═══ SEC-08 · توسيع job_titles القائم (16 صفًّا · DEC-SEC-H) ════════════════
-- MySQL بلا ADD COLUMN IF NOT EXISTS — النمط الحارس عبر information_schema
SET @add_col = NULL;
SELECT COUNT(*) INTO @add_col FROM information_schema.columns
 WHERE table_schema = DATABASE() AND table_name = 'job_titles' AND column_name = 'title_code';
SET @ddl = IF(@add_col = 0, 'ALTER TABLE job_titles
  ADD COLUMN title_code VARCHAR(40) NULL COMMENT ''SEC-01 §12: الكود المعتمد'' AFTER id,
  ADD COLUMN family_code VARCHAR(40) NULL COMMENT ''العائلة — hr_dictionaries'' AFTER name,
  ADD COLUMN level_code VARCHAR(40) NULL AFTER family_code,
  ADD COLUMN org_unit_id INT UNSIGNED NULL AFTER level_code,
  ADD COLUMN duties_json JSON NULL AFTER description,
  ADD COLUMN default_manager_position_id INT UNSIGNED NULL AFTER duties_json,
  ADD COLUMN functional_line_unit_id INT UNSIGNED NULL AFTER default_manager_position_id,
  ADD COLUMN operational_line_unit_id INT UNSIGNED NULL AFTER functional_line_unit_id,
  ADD COLUMN template_id INT UNSIGNED NULL AFTER operational_line_unit_id,
  ADD COLUMN allowed_scopes_json JSON NULL AFTER template_id,
  ADD COLUMN amount_cap DECIMAL(18,2) NULL AFTER allowed_scopes_json,
  ADD COLUMN currency VARCHAR(8) NULL AFTER amount_cap,
  ADD COLUMN prohibitions_json JSON NULL AFTER currency,
  ADD COLUMN qualifications_json JSON NULL AFTER prohibitions_json,
  ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER qualifications_json,
  ADD UNIQUE KEY uq_jt_code (title_code)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ═══ البذور ═════════════════════════════════════════════════════════════════

-- العلاقات الست (§2①)
INSERT IGNORE INTO hr_dictionaries (code, name_ar, layer, `rank`) VALUES
  ('rel_employee',    'موظف دائم',   'relation', NULL),
  ('rel_trainee',     'متدرب',       'relation', NULL),
  ('rel_temp_worker', 'عامل مؤقت',   'relation', NULL),
  ('rel_consultant',  'مستشار',      'relation', NULL),
  ('rel_contractor',  'مقاول',       'relation', NULL),
  ('rel_supplier_emp','موظف مورد',   'relation', NULL);

-- العائلات الثلاث عشرة (§2② · DEC-SEC-F)
INSERT IGNORE INTO hr_dictionaries (code, name_ar, layer, `rank`) VALUES
  ('fam_ops',         'التشغيل',            'family', NULL),
  ('fam_maintenance', 'الصيانة',            'family', NULL),
  ('fam_operators',   'المشغّلون',          'family', NULL),
  ('fam_procurement', 'المشتريات',          'family', NULL),
  ('fam_warehouse',   'المخازن',            'family', NULL),
  ('fam_transport',   'النقل والترحيل',     'family', NULL),
  ('fam_finance',     'المالية',            'family', NULL),
  ('fam_hr',          'الموارد البشرية',    'family', NULL),
  ('fam_sales',       'المبيعات',           'family', NULL),
  ('fam_financing',   'التمويل',            'family', NULL),
  ('fam_fleet',       'الأسطول',            'family', NULL),
  ('fam_governance',  'الحوكمة والامتثال',  'family', NULL),
  ('fam_tickets',     'الرصد والبلاغات',    'family', NULL);

-- المستويات السبعة (§3) برتبها
INSERT IGNORE INTO hr_dictionaries (code, name_ar, layer, `rank`) VALUES
  ('lvl_executor',    'منفِّذ',        'level', 1),
  ('lvl_officer',     'مسؤول',        'level', 2),
  ('lvl_supervisor',  'مشرف',         'level', 3),
  ('lvl_unit_head',   'رئيس وحدة',    'level', 4),
  ('lvl_section_mgr', 'مدير قسم',     'level', 5),
  ('lvl_dept_mgr',    'مدير إدارة',   'level', 6),
  ('lvl_executive',   'مدير تنفيذي',  'level', 7);

-- ترميز المسميات الستة عشر القائمة + عائلتها ومستواها من مسودة SEC-D1 (★ تنتظر الاعتماد)
UPDATE job_titles SET title_code = CONCAT('jt_', id) WHERE title_code IS NULL;
UPDATE job_titles SET
  family_code = CASE id
    WHEN 2 THEN 'fam_maintenance' WHEN 3 THEN 'fam_maintenance' WHEN 4 THEN 'fam_maintenance'
    WHEN 14 THEN 'fam_maintenance'
    WHEN 6 THEN 'fam_operators' WHEN 7 THEN 'fam_operators' WHEN 8 THEN 'fam_operators'
    WHEN 9 THEN 'fam_operators' WHEN 10 THEN 'fam_operators' WHEN 11 THEN 'fam_operators'
    WHEN 13 THEN 'fam_hr'
    ELSE 'fam_ops' END,
  level_code = CASE id
    WHEN 1 THEN 'lvl_dept_mgr' WHEN 12 THEN 'lvl_supervisor' WHEN 5 THEN 'lvl_officer'
    ELSE 'lvl_executor' END
WHERE family_code IS NULL;

-- قوالب الهوية: 6 علاقات (سقوف) + 13 عائلة + 7 مستويات — بلا محتوى (الإصدارات موجة ⑨)
INSERT IGNORE INTO permission_templates (tpl_kind, key_code, is_ceiling)
SELECT 'relation', code, 1 FROM hr_dictionaries WHERE layer = 'relation';
INSERT IGNORE INTO permission_templates (tpl_kind, key_code, is_ceiling)
SELECT 'family', code, 0 FROM hr_dictionaries WHERE layer = 'family';
INSERT IGNORE INTO permission_templates (tpl_kind, key_code, is_ceiling)
SELECT 'level', code, 0 FROM hr_dictionaries WHERE layer = 'level';
INSERT IGNORE INTO permission_templates (tpl_kind, key_code, is_ceiling)
SELECT 'title', title_code, 0 FROM job_titles WHERE title_code IS NOT NULL;

-- فصل الواجبات — الثمانية (§5)
INSERT IGNORE INTO sod_conflicts (conflict_code, name_ar, permission_a, permission_b, severity, compensating_control) VALUES
  ('sod_supplier_cycle',  'إنشاء مورد + تعديل حسابه البنكي + اعتماد دفعه', 'supplier.create+supplier.bank.update', 'supplier.payment.approve', 'critical', 'مراجعة عينة شهرية موثقة بموافقة التنفيذي'),
  ('sod_procure_cycle',   'طلب شراء + ترسية + استلام + صرف', 'proc.request+proc.award', 'proc.receive+proc.disburse', 'critical', 'مراجعة عينة شهرية موثقة'),
  ('sod_hours_claim',     'إدخال ساعات + اعتمادها + إنشاء مستخلصها', 'timesheet.entry+timesheet.approve', 'claim.create', 'high', 'مراجعة عينة شهرية'),
  ('sod_payroll_cycle',   'إنشاء موظف + تعديل راتبه + تشغيل مسيّره', 'employee.create+payroll.salary.update', 'payroll.run', 'critical', 'مراجعة عينة شهرية'),
  ('sod_collection_hide', 'فاتورة + سند قبض + مطابقة البنك', 'invoice.create+receipt.create', 'bank.reconcile', 'critical', 'مراجعة عينة شهرية'),
  ('sod_self_privilege',  'إنشاء صلاحية + اعتمادها + تنفيذها', 'permission.create+permission.approve', 'permission.apply', 'critical', NULL),
  ('sod_ownership_move',  'تسجيل حصة ملكية + اعتماد نقلها', 'ownership.share.create', 'ownership.transfer.approve', 'high', 'مراجعة عينة شهرية'),
  ('sod_period_reopen',   'فتح فترة + إدخال قيد + اعتماد الإقفال', 'period.open+journal.entry', 'period.close.approve', 'critical', NULL);

-- الحراس السبعة عشر (§7.2) — الاسم يصف السياسة
INSERT IGNORE INTO guard_override_policies (guard_code, name_ar, overridable, environments_json) VALUES
  ('tenant.isolation',        'عزل الكيانات',                        'never', JSON_ARRAY('production','founding','test')),
  ('self.approval',           'منع اعتماد الذات',                    'never', JSON_ARRAY('production','founding','test')),
  ('period.lock',             'قفل الفترة',                          'never', JSON_ARRAY('production','founding','test')),
  ('record.delete.impactful', 'منع حذف سجل ذي أثر',                  'never', JSON_ARRAY('production','founding','test')),
  ('audit.tamper',            'منع تعديل سجل التدقيق',               'never', JSON_ARRAY('production','founding','test')),
  ('self.grant',              'منع منح المرء نفسه',                  'never', JSON_ARRAY('production','founding','test')),
  ('legal.explicit',          'القيود القانونية الصريحة',            'never', JSON_ARRAY('production','founding','test')),
  ('data.medical_bank',       'البيانات الطبية والبنكية بلا سبب وظيفي', 'never', JSON_ARRAY('production','founding','test')),
  ('data.payroll',            'بيانات الرواتب',                      'break_glass_only', JSON_ARRAY('production','founding','test')),
  ('payment.execute',         'تنفيذ المدفوعات',                     'break_glass_only', JSON_ARRAY('production','founding','test')),
  ('journal.post',            'نشر القيود',                          'break_glass_only', JSON_ARRAY('production','founding','test')),
  ('coa.modify',              'تعديل دليل الحسابات',                 'break_glass_only', JSON_ARRAY('production','founding','test')),
  ('permission.grant_others', 'منح الصلاحيات لغيره',                 'break_glass_only', JSON_ARRAY('production','founding','test')),
  ('export.sensitive',        'التصدير الحساس',                      'break_glass_only', JSON_ARRAY('production','founding','test')),
  ('asset.ownership.change',  'تغيير ملكية أصل',                     'break_glass_only', JSON_ARRAY('production','founding','test')),
  ('approval.cap.exceed',     'تجاوز سقف الاعتماد',                  'with_compensating_control', JSON_ARRAY('production','founding','test')),
  ('sod.procure_receive_pay', 'فصل واجبات الشراء والاستلام والصرف',  'with_compensating_control', JSON_ARRAY('production','founding','test'));

-- سياسات الحقول الحساسة الأساس (§10⑦ — تُثرى لاحقًا بصفوف لا كود)
INSERT IGNORE INTO sensitive_field_policies (field_code, classification, masking_rule, allowed_roles_json) VALUES
  ('employees.salary',        'payroll',   'full',    JSON_ARRAY('4','17','19')),
  ('employees.bank_account',  'bank',      'partial', JSON_ARRAY('4','17','21')),
  ('employees.medical_notes', 'medical',   'full',    JSON_ARRAY('4')),
  ('equipment.owner_entity',  'ownership', 'full',    JSON_ARRAY('26','17')),
  ('financing.terms',         'financing', 'full',    JSON_ARRAY('26')),
  ('contract.unit_price',     'pricing',   'full',    JSON_ARRAY('12','17','19'));

-- صفا وضع التأسيس — مطفآن (لا تفعيل بلا ends_at · والقلب قرار مالك)
INSERT IGNORE INTO founding_mode (mode_id, mode, enabled, banner_text) VALUES
  (1, 'discovery',       0, 'وضع التأسيس — بيانات تجريبية'),
  (2, 'permission_test', 0, 'وضع اختبار الصلاحيات — حسابات ممثلة بصلاحياتها الحقيقية');
