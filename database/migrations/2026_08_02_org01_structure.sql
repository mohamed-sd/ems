-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ① · ORG-01 §7 — بنيةُ الهيكل التشغيلي والتكليفات والأذونات
-- ORG-01: الجداولُ الستة (org_units · org_assignment_types · org_assignments ·
--         assignment_capabilities · assignment_reporting_lines · assignment_audit)
-- ORG-02: head_person_id مشتقٌّ لا يُكتب — لا عمودَ له، والقراءةُ عبر v_org_unit_heads
-- ORG-03: بذرُ أنواع التكليف (٥ مركزية + ٨ موقعية — الموقعيُّ كلُّه requires_functional_line=1)
-- ORG-04: بذرُ الأربعَ عشرةَ وحدةً (ORG-01 §1.1) للشركة التشغيلية
-- ORG-05: جداولُ الأذونات الخمسة + بذرُ الأنواع التسعة ومصفوفةِ موافقاتها (§5)
-- القيدُ الحرج: «مديرُ حركةٍ واحدٌ نشطٌ لكل موقع» بعمودٍ مولَّدٍ NULL لغير النشط
-- + فهرسٍ فريد (نمطُ primary_flag/live_type_key القائم) — لا CHECK.
-- idempotent: كلُّ CREATE بـIF NOT EXISTS وكلُّ بذرٍ بمفتاحٍ طبيعيٍّ يمنع الازدواج.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ORG-01 · ① الوحدات التنظيمية ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS org_units (
  unit_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id     INT NOT NULL,
  unit_code      VARCHAR(40) NOT NULL COMMENT 'رمزٌ ثابتٌ للوحدة تُخاطَب به برمجيًّا',
  name_ar        VARCHAR(190) NOT NULL,
  layer          ENUM('operational','parallel','oversight') NOT NULL
                 COMMENT 'الطبقة: تشغيليةٌ تحت مدير التشغيل · موازيةٌ تحت التنفيذي · رقابية',
  parent_unit_id INT UNSIGNED NULL,
  owner_doc      VARCHAR(120) NULL COMMENT 'الوثيقةُ الحاكمة للوحدة',
  active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (unit_id),
  UNIQUE KEY uq_org_units_scope (company_id, unit_code),
  KEY idx_org_units_parent (parent_unit_id),
  CONSTRAINT fk_org_units_parent FOREIGN KEY (parent_unit_id) REFERENCES org_units (unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §7: الوحداتُ التنظيمية — head_person_id مشتقٌّ من org_assignments (v_org_unit_heads) ولا يُكتب';

-- ── ORG-01 · ② أنواع التكليف — صفٌّ لا Enum في الكود ───────────────────────
CREATE TABLE IF NOT EXISTS org_assignment_types (
  type_code                 VARCHAR(40) NOT NULL,
  name_ar                   VARCHAR(190) NOT NULL,
  level                     ENUM('central','site') NOT NULL,
  default_capabilities_json JSON NULL,
  requires_functional_line  TINYINT(1) NOT NULL DEFAULT 0
                            COMMENT 'الموقعيُّ كلُّه =1: خطّان تشغيليٌّ وفنيٌّ لا خطٌّ واحد (§2⑦)',
  is_unit_head              TINYINT(1) NOT NULL DEFAULT 0
                            COMMENT 'نوعٌ يجعل صاحبَه رأسَ وحدته — يغذي اشتقاق v_org_unit_heads',
  active                    TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (type_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §7: أنواعُ التكليف — يُضاف نوعٌ جديدٌ بصفٍّ لا بتعديل برمجة';

-- ── ORG-01 · ③ التكليفات التنظيمية — مصدرُ الحقيقة الوحيد ───────────────────
CREATE TABLE IF NOT EXISTS org_assignments (
  asg_id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id           INT NOT NULL,
  person_id            INT NOT NULL COMMENT 'users.id — كنمط signing_authorities.person_id',
  assignment_type_code VARCHAR(40) NOT NULL,
  org_unit_id          INT UNSIGNED NOT NULL,
  scope_type           ENUM('project','site','site_group') NOT NULL,
  scope_id             INT NOT NULL COMMENT 'المشروعُ أو الموقعُ أو مجموعةُ المواقع — ولا تكليفَ مفتوحُ النطاق',
  valid_from           DATE NOT NULL,
  valid_to             DATE NOT NULL COMMENT 'إلزاميٌّ — لا تكليفَ مفتوحُ المدة، وتمديدُه قرارٌ جديد',
  decided_by_person_id INT NOT NULL COMMENT 'مصدرُ القرار: مديرُ التشغيل أو المديرُ التنفيذي',
  decision_ref         VARCHAR(120) NULL,
  deputy_person_id     INT NULL COMMENT 'النائبُ المعتمَد — ولا نيابةَ شفويةٌ ولا مفتوحةُ المدة',
  state                ENUM('active','suspended','ended') NOT NULL DEFAULT 'active',
  active_site_mgr_key  VARCHAR(80) GENERATED ALWAYS AS (
                         IF(assignment_type_code = 'site_movement_mgr' AND state = 'active',
                            CONCAT(company_id, ':', scope_type, ':', scope_id), NULL)) STORED
                       COMMENT 'حيلةُ الفريد المشروط: NULL لغير مدير الحركة النشط — فينتج «واحدٌ نشطٌ لكل موقع»',
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (asg_id),
  UNIQUE KEY uq_asg_natural (company_id, assignment_type_code, scope_type, scope_id, valid_from),
  UNIQUE KEY uq_one_active_movement_mgr (active_site_mgr_key),
  KEY idx_asg_person (person_id, state),
  KEY idx_asg_scope (company_id, scope_type, scope_id, state),
  KEY idx_asg_validity (state, valid_to),
  CONSTRAINT fk_asg_type FOREIGN KEY (assignment_type_code) REFERENCES org_assignment_types (type_code),
  CONSTRAINT fk_asg_unit FOREIGN KEY (org_unit_id) REFERENCES org_units (unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §2/§7: التكليفُ سجلٌّ تنظيميٌّ بنطاقٍ ومدةٍ وسقفٍ ونائبٍ — ويسقط آليًّا بانتهائه';

-- ── ORG-01 · ④ صلاحيات التكليف وسقوفها ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS assignment_capabilities (
  cap_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asg_id           INT UNSIGNED NOT NULL,
  capability_code  VARCHAR(80) NOT NULL,
  scope_limit_json JSON NULL COMMENT 'المواقعُ والمشاريعُ المسموحة — السقفُ التشغيليُّ نطاقيّ',
  amount_cap       DECIMAL(18,2) NULL COMMENT 'NULL للتشغيلي — والسقفُ الماليُّ نقدي',
  currency         VARCHAR(8) NULL,
  PRIMARY KEY (cap_id),
  UNIQUE KEY uq_cap_per_asg (asg_id, capability_code),
  CONSTRAINT fk_cap_asg FOREIGN KEY (asg_id) REFERENCES org_assignments (asg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §7: صلاحياتُ التكليف — السقفُ التشغيليُّ نطاقيٌّ والماليُّ نقدي (DEC-01 ①)';

-- ── ORG-01 · ⑤ خطا التبعية — تشغيليٌّ وفنيٌّ لا خطٌّ واحد ──────────────────
CREATE TABLE IF NOT EXISTS assignment_reporting_lines (
  line_id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asg_id                    INT UNSIGNED NOT NULL,
  line_type                 ENUM('operational','functional') NOT NULL,
  reports_to_assignment_id  INT UNSIGNED NOT NULL,
  valid_from                DATE NULL,
  valid_to                  DATE NULL,
  PRIMARY KEY (line_id),
  UNIQUE KEY uq_line_per_asg (asg_id, line_type),
  KEY idx_line_reports_to (reports_to_assignment_id),
  CONSTRAINT fk_line_asg FOREIGN KEY (asg_id) REFERENCES org_assignments (asg_id),
  CONSTRAINT fk_line_target FOREIGN KEY (reports_to_assignment_id) REFERENCES org_assignments (asg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §2⑦: التبعيةُ المزدوجة — وقيدُ «الموقعيُّ له خطّان» يحرسه AssignmentService بـ422';

-- ── ORG-01 · ⑥ سجل التكليف — Insert-only ────────────────────────────────────
CREATE TABLE IF NOT EXISTS assignment_audit (
  log_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  asg_id       INT UNSIGNED NOT NULL,
  action       ENUM('created','amended','suspended','transferred','ended','delegated') NOT NULL,
  reason       VARCHAR(255) NULL,
  before_json  JSON NULL,
  after_json   JSON NULL,
  by_person_id INT NOT NULL,
  at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_audit_asg (asg_id, at),
  CONSTRAINT fk_audit_asg FOREIGN KEY (asg_id) REFERENCES org_assignments (asg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §2⑧: سجلُّ التعديلات والاعتمادات — للإدراج فقط لا يُعدَّل ولا يُحذف';

-- ── ORG-05 · ⑦ أنواع الأذونات — التسعةُ صفوفٌ لا كود ────────────────────────
CREATE TABLE IF NOT EXISTS permit_types (
  permit_type_code VARCHAR(40) NOT NULL,
  name_ar          VARCHAR(190) NOT NULL,
  subject_kind     ENUM('equipment','material','person','technician') NOT NULL,
  direction        ENUM('in','out','activate','deactivate') NOT NULL,
  validity_hours   INT NOT NULL DEFAULT 24,
  active           TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (permit_type_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §5/§7: الأنواعُ التسعةُ صفوفٌ هنا لا كودًا';

-- ── ORG-05 · ⑧ طلبات الأذونات ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS permit_requests (
  req_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id       INT NOT NULL,
  permit_type_code VARCHAR(40) NOT NULL,
  subject_ref      VARCHAR(120) NOT NULL COMMENT 'مرجعُ الموضوع: معدةٌ أو مادةٌ أو شخصٌ أو فني',
  site_id          INT NOT NULL,
  requested_by     INT NOT NULL,
  reason           VARCHAR(255) NULL,
  doc_ref          VARCHAR(120) NULL,
  state            ENUM('draft','pending','approved','rejected','expired','used') NOT NULL DEFAULT 'draft',
  valid_until      DATETIME NULL COMMENT 'يُحسب من validity_hours عند اكتمال الموافقات — بساعة القاعدة',
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (req_id),
  KEY idx_permit_state_site (state, site_id),
  KEY idx_permit_company (company_id, state),
  CONSTRAINT fk_preq_type FOREIGN KEY (permit_type_code) REFERENCES permit_types (permit_type_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §7: طلبُ الإذن — يمرّ بصندوق الاعتماد الجامع بندًا واحدًا لكل موافقٍ في دوره';

-- ── ORG-05 · ⑨ مصفوفة §5 منمذَجةً — من يوافق وبأي ترتيب ────────────────────
CREATE TABLE IF NOT EXISTS permit_required_approvals (
  rq_id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  permit_type_code VARCHAR(40) NOT NULL,
  seq_no           INT NOT NULL,
  approver_role    VARCHAR(60) NOT NULL COMMENT 'المجالُ الوظيفيُّ الموافق — يحلُّه PermitGate من التكليفات النافذة',
  mandatory        TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (rq_id),
  UNIQUE KEY uq_rq_seq (permit_type_code, seq_no),
  CONSTRAINT fk_permit_rq_type FOREIGN KEY (permit_type_code) REFERENCES permit_types (permit_type_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §5/§7: مصفوفةُ الموافقات المشتركة — يُقرأ منها من يوافق وبأي ترتيب';

-- ── ORG-05 · ⑩ قرارات الموافقة — خطوةٌ لا تُفتح قبل اكتمال ما قبلها ─────────
CREATE TABLE IF NOT EXISTS permit_approval_actions (
  act_id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  req_id             INT UNSIGNED NOT NULL,
  rq_id              INT UNSIGNED NOT NULL,
  approver_person_id INT NOT NULL,
  auth_id            INT UNSIGNED NULL COMMENT 'مرجعُ التفويض signing_authorities — LEG-01 §4',
  decision           ENUM('approve','reject') NOT NULL,
  reason             VARCHAR(255) NULL,
  at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (act_id),
  UNIQUE KEY uq_act_step (req_id, rq_id),
  CONSTRAINT fk_permit_act_req FOREIGN KEY (req_id) REFERENCES permit_requests (req_id),
  CONSTRAINT fk_permit_act_rq FOREIGN KEY (rq_id) REFERENCES permit_required_approvals (rq_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §7: قيدُ التسلسل «لا تُفتح خطوةٌ قبل اكتمال ما قبلها» يحرسه PermitGate بـ409';

-- ── ORG-05 · ⑪ تاريخ حالات الإذن — Insert-only ──────────────────────────────
CREATE TABLE IF NOT EXISTS permit_status_history (
  hist_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  req_id       INT UNSIGNED NOT NULL,
  from_state   VARCHAR(20) NOT NULL,
  to_state     VARCHAR(20) NOT NULL,
  by_person_id INT NOT NULL,
  at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (hist_id),
  KEY idx_hist_req (req_id, at),
  CONSTRAINT fk_permit_hist_req FOREIGN KEY (req_id) REFERENCES permit_requests (req_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ORG-01 §7: تاريخُ حالات الإذن — للإدراج فقط';

-- ── ORG-02 · الاشتقاق: رأسُ الوحدة من التكليف النافذ لا من عمودٍ يُكتب ──────
CREATE OR REPLACE VIEW v_org_unit_heads AS
SELECT u.unit_id,
       u.company_id,
       u.unit_code,
       u.name_ar,
       a.person_id  AS head_person_id,
       a.asg_id     AS head_assignment_id,
       a.scope_type AS head_scope_type,
       a.scope_id   AS head_scope_id
FROM org_units u
LEFT JOIN org_assignments a
       ON a.org_unit_id = u.unit_id
      AND a.state = 'active'
      AND CURDATE() BETWEEN a.valid_from AND a.valid_to
      AND a.assignment_type_code IN (
            SELECT t.type_code FROM org_assignment_types t WHERE t.is_unit_head = 1);

-- ═══ ORG-03 · بذر أنواع التكليف — ٥ مركزية + ٨ موقعية ══════════════════════
INSERT IGNORE INTO org_assignment_types
  (type_code, name_ar, level, requires_functional_line, is_unit_head, active) VALUES
  ('maintenance_mgr',          'مدير الصيانة',                'central', 0, 1, 1),
  ('operators_mgr',            'مدير المشغّلين',              'central', 0, 1, 1),
  ('procurement_ops_mgr',      'مدير المشتريات التشغيلية',    'central', 0, 1, 1),
  ('warehouse_mgr',            'مدير المخازن',                'central', 0, 1, 1),
  ('transport_mgr',            'مدير النقل والترحيل',         'central', 0, 1, 1),
  ('site_movement_mgr',        'مدير الحركة والتشغيل',        'site',    1, 1, 1),
  ('site_maintenance_officer', 'مسؤول صيانة الموقع',          'site',    1, 0, 1),
  ('site_operators_officer',   'مسؤول مشغّلي الموقع',         'site',    1, 0, 1),
  ('site_warehouse_keeper',    'أمين مخزن الموقع',            'site',    1, 0, 1),
  ('site_procurement_receiver','مستلم مشتريات الموقع',        'site',    1, 0, 1),
  ('site_transport_coordinator','منسّق النقل بالموقع',        'site',    1, 0, 1),
  ('site_accommodation_officer','مسؤول السكن والإعاشة',       'site',    1, 0, 1),
  ('gate_officer',             'ضابط البوابة',                'site',    1, 0, 1);

-- ═══ ORG-04 · بذر الوحدات الأربع عشرة (§1.1) — للشركة التشغيلية (4) ═════════
-- الأمهاتُ أولًا (بلا أب) ثم البناتُ التشغيلياتُ تحت «التشغيل».
INSERT INTO org_units (company_id, unit_code, name_ar, layer, parent_unit_id, owner_doc)
SELECT 4, t.code, t.name_ar, t.layer, NULL, 'ORG-01 §1.1'
FROM (SELECT 'ops' code, 'التشغيل' name_ar, 'operational' layer UNION ALL
      SELECT 'sales',      'المبيعات والعقود',   'parallel'  UNION ALL
      SELECT 'finance',    'المالية والخزينة',   'parallel'  UNION ALL
      SELECT 'financing',  'التمويل والملكية',   'parallel'  UNION ALL
      SELECT 'fleet',      'الأسطول',            'parallel'  UNION ALL
      SELECT 'governance', 'الحوكمة والصلاحيات', 'oversight' UNION ALL
      SELECT 'tickets',    'مركز البلاغات',      'oversight') t
WHERE NOT EXISTS (SELECT 1 FROM org_units u WHERE u.company_id = 4 AND u.unit_code = t.code);

INSERT INTO org_units (company_id, unit_code, name_ar, layer, parent_unit_id, owner_doc)
SELECT 4, t.code, t.name_ar, 'operational',
       (SELECT u.unit_id FROM org_units u WHERE u.company_id = 4 AND u.unit_code = 'ops'),
       'ORG-01 §1.1'
FROM (SELECT 'movement' code,       'الحركة والتشغيل' name_ar UNION ALL
      SELECT 'maintenance',         'الصيانة'                 UNION ALL
      SELECT 'operators',           'المشغّلون والقوى التشغيلية' UNION ALL
      SELECT 'procurement_ops',     'المشتريات التشغيلية'     UNION ALL
      SELECT 'warehouse',           'المخازن'                 UNION ALL
      SELECT 'transport',           'النقل والترحيل'          UNION ALL
      SELECT 'hr',                  'الموارد البشرية') t
WHERE NOT EXISTS (SELECT 1 FROM org_units u WHERE u.company_id = 4 AND u.unit_code = t.code);

-- ═══ ORG-05 · بذر أنواع الأذونات التسعة (§5) ════════════════════════════════
INSERT IGNORE INTO permit_types (permit_type_code, name_ar, subject_kind, direction, validity_hours, active) VALUES
  ('equipment_site_entry',   'دخول معدة إلى الموقع',      'equipment',  'in',         24, 1),
  ('equipment_service_entry','إدخال المعدة للخدمة',       'equipment',  'activate',   24, 1),
  ('equipment_service_exit', 'إخراج المعدة من الخدمة',    'equipment',  'deactivate', 24, 1),
  ('equipment_site_exit',    'خروج المعدة من الموقع',     'equipment',  'out',        24, 1),
  ('material_site_entry',    'دخول مشتريات للموقع',       'material',   'in',         24, 1),
  ('material_site_exit',     'خروج مواد من الموقع',       'material',   'out',        24, 1),
  ('operator_site_entry',    'دخول مشغّل',                'person',     'in',         24, 1),
  ('technician_site_entry',  'دخول فني',                  'technician', 'in',         24, 1),
  ('worker_final_exit',      'خروج عامل نهائيًّا',        'person',     'out',        48, 1);

-- ═══ ORG-05 · بذر مصفوفة الموافقات (§5) — بالترتيب المنصوص ═════════════════
INSERT INTO permit_required_approvals (permit_type_code, seq_no, approver_role, mandatory)
SELECT t.ptc, t.seq, t.role, 1
FROM (SELECT 'equipment_site_entry' ptc, 1 seq, 'movement' role UNION ALL
      SELECT 'equipment_site_entry',    2, 'fleet'          UNION ALL
      SELECT 'equipment_site_entry',    3, 'maintenance'    UNION ALL
      SELECT 'equipment_service_entry', 1, 'movement'       UNION ALL
      SELECT 'equipment_service_entry', 2, 'maintenance'    UNION ALL
      SELECT 'equipment_service_entry', 3, 'operators'      UNION ALL
      SELECT 'equipment_service_exit',  1, 'movement'       UNION ALL
      SELECT 'equipment_service_exit',  2, 'maintenance'    UNION ALL
      SELECT 'equipment_site_exit',     1, 'movement'       UNION ALL
      SELECT 'equipment_site_exit',     2, 'fleet'          UNION ALL
      SELECT 'equipment_site_exit',     3, 'warehouse'      UNION ALL
      SELECT 'equipment_site_exit',     4, 'transport'      UNION ALL
      SELECT 'material_site_entry',     1, 'procurement'    UNION ALL
      SELECT 'material_site_entry',     2, 'warehouse'      UNION ALL
      SELECT 'material_site_entry',     3, 'movement'       UNION ALL
      SELECT 'material_site_exit',      1, 'warehouse'      UNION ALL
      SELECT 'material_site_exit',      2, 'movement'       UNION ALL
      SELECT 'material_site_exit',      3, 'material_owner' UNION ALL
      SELECT 'operator_site_entry',     1, 'operators'      UNION ALL
      SELECT 'operator_site_entry',     2, 'movement'       UNION ALL
      SELECT 'technician_site_entry',   1, 'maintenance'    UNION ALL
      SELECT 'technician_site_entry',   2, 'movement'       UNION ALL
      SELECT 'worker_final_exit',       1, 'movement'       UNION ALL
      SELECT 'worker_final_exit',       2, 'hr') t
WHERE NOT EXISTS (SELECT 1 FROM permit_required_approvals r
                  WHERE r.permit_type_code = t.ptc AND r.seq_no = t.seq);
