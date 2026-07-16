-- ═══════════════════════════════════════════════════════════════════════════
-- D05 — دورة الطلب المالي (الدستور التنفيذي EQUIP-FIN-D05-EMS) — المرحلتان 1+2
-- طبقة البوابة: الطلب الموحّد + بنوده + مستنداته + سجلّه الإلحاقي + روابط الأثر
-- + جدول توجيه الإدارات (قرار التنفيذ: صناديق أدوار بمفتاح تفعيلٍ لكل إدارة).
-- المخطط وفق §11 من الوثيقة حرفيًا؛ كل جدولٍ بـcompany_id ويُعزل عبر البوابة.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS fin_requests (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id       INT UNSIGNED NOT NULL,
  request_no       VARCHAR(30) NOT NULL,
  request_type     ENUM('purchase','disbursement','advance','supplier_payment',
                       'employee_payment','transfer','settlement','refund',
                       'discount','collection','other') NOT NULL,
  source_module    ENUM('sales','suppliers','workforce','procurement','warehouse',
                       'maintenance','projects','revenue','assets','treasury','general')
                     NOT NULL DEFAULT 'general',
  requester_id     INT UNSIGNED NULL,
  beneficiary_type ENUM('supplier','employee','customer','internal','other') NOT NULL,
  beneficiary_ref  INT UNSIGNED NULL,
  beneficiary_name VARCHAR(160) NULL,
  amount           DECIMAL(16,2) NOT NULL,
  currency         VARCHAR(8) NOT NULL DEFAULT 'SDG',
  payment_method   ENUM('cash','bank','transfer','cheque') NULL,
  statement        VARCHAR(255) NULL,
  justification    VARCHAR(255) NULL,
  source_ref       VARCHAR(60) NULL,
  project_id       INT UNSIGNED NULL,
  equipment_id     INT UNSIGNED NULL,
  contract_id      INT UNSIGNED NULL,
  cost_center      VARCHAR(60) NULL,
  account_id       INT UNSIGNED NULL,
  needed_by        DATE NULL,
  priority         ENUM('normal','high','critical') NOT NULL DEFAULT 'normal',
  need_class       ENUM('planned','unplanned','urgent','emergency')
                     NOT NULL DEFAULT 'planned',
  budget_line_id   INT UNSIGNED NULL,
  sla_due_at       DATETIME NULL,
  escalation_level TINYINT NOT NULL DEFAULT 0,
  is_exception     TINYINT(1) NOT NULL DEFAULT 0,
  exception_type   ENUM('urgent_bypass','emergency_execute') NULL,
  exception_approved_by INT UNSIGNED NULL,
  merged_into_id   INT UNSIGNED NULL,
  duplicate_flag   TINYINT(1) NOT NULL DEFAULT 0,
  rejection_class  ENUM('incomplete_docs','no_budget','policy_violation',
                       'duplicate','not_justified','other') NULL,
  decision_ref     VARCHAR(60) NULL,
  notes            VARCHAR(255) NULL,
  state            ENUM('draft','under_review','pending_approval','approved',
                       'rejected','returned','posted','paid','collected',
                       'closed','archived','withdrawn','cancelled','suspended',
                       'expired','merged') NOT NULL DEFAULT 'draft',
  event_id         INT UNSIGNED NULL,
  decided_by       INT UNSIGNED NULL,
  sync_uuid        CHAR(36) NULL,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_req_no (company_id, request_no),
  UNIQUE KEY uq_sync   (company_id, sync_uuid),
  KEY ix_state  (company_id, state),
  KEY ix_type   (company_id, request_type),
  KEY ix_module (company_id, source_module),
  KEY ix_event  (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin_request_lines (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id   INT UNSIGNED NOT NULL,
  request_id   INT UNSIGNED NOT NULL,
  item         VARCHAR(200) NOT NULL,
  qty          DECIMAL(12,2) NOT NULL DEFAULT 1,
  unit         VARCHAR(20) NULL,
  unit_price   DECIMAL(14,2) NOT NULL DEFAULT 0,
  line_total   DECIMAL(16,2) AS (qty * unit_price) STORED,
  note         VARCHAR(160) NULL,
  PRIMARY KEY (id),
  KEY ix_req (company_id, request_id),
  CONSTRAINT fk_frl_req FOREIGN KEY (request_id) REFERENCES fin_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin_request_documents (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id   INT UNSIGNED NOT NULL,
  request_id   INT UNSIGNED NOT NULL,
  doc_type     ENUM('quote','invoice','statement','contract','receipt',
                   'delivery_note','photo','other') NOT NULL,
  file_ref     VARCHAR(255) NOT NULL,
  original_kind ENUM('original','copy','electronic') NOT NULL DEFAULT 'electronic',
  retention_years TINYINT NOT NULL DEFAULT 10,
  confidentiality ENUM('normal','restricted','confidential') NOT NULL DEFAULT 'normal',
  note         VARCHAR(160) NULL,
  uploaded_by  INT UNSIGNED NULL,
  uploaded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sync_uuid    CHAR(36) NULL,
  PRIMARY KEY (id),
  KEY ix_req (company_id, request_id),
  CONSTRAINT fk_frd_req FOREIGN KEY (request_id) REFERENCES fin_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin_request_events (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id    INT UNSIGNED NOT NULL,
  request_id    INT UNSIGNED NOT NULL,
  event_type    ENUM('create','attach','submit','dept_review','dept_approve',
                    'acct_review','verify','fin_approve','reject','return',
                    'resubmit','post','pay','collect','settle','close',
                    'archive','withdraw','cancel','suspend','resume','expire',
                    'merge','duplicate_check','escalate','exception','publish',
                    'edit','note','system') NOT NULL DEFAULT 'note',
  actor_user_id INT UNSIGNED NULL,
  on_behalf_of  INT UNSIGNED NULL,
  body          TEXT NULL,
  old_value     VARCHAR(120) NULL,
  new_value     VARCHAR(120) NULL,
  sync_uuid     CHAR(36) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_req  (company_id, request_id, created_at),
  KEY ix_type (company_id, event_type),
  CONSTRAINT fk_fre_req FOREIGN KEY (request_id) REFERENCES fin_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fin_event_links (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id   INT UNSIGNED NOT NULL,
  parent_kind  ENUM('request','unit_record','event') NOT NULL,
  parent_ref   INT UNSIGNED NOT NULL,
  effect_type  ENUM('revenue_event','supplier_due','employee_due','cost_record',
                   'receivable','journal_entry','payment','metric_update') NOT NULL,
  target_table VARCHAR(40) NOT NULL,
  target_id    INT UNSIGNED NULL,
  event_id     INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_parent (company_id, parent_kind, parent_ref),
  KEY ix_target (company_id, target_table, target_id),
  KEY ix_event  (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول التوجيه: يحدّد لكل إدارةٍ أدوارَ طالبيها ومراجعها (الرئيس المباشر) ومعتمدها
-- (مدير الإدارة)، ومفتاح تفعيلها — قرار «الجميع ينشئ عبر مفتاح تفعيلٍ لكل إدارة».
-- المحاسب يُحلّ من fin_accountants (admin_module) — لا يُكرَّر هنا.
CREATE TABLE IF NOT EXISTS fin_request_routing (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id       INT UNSIGNED NOT NULL,
  source_module    ENUM('sales','suppliers','workforce','procurement','warehouse',
                       'maintenance','projects','revenue','assets','treasury','general')
                     NOT NULL,
  module_label     VARCHAR(80) NOT NULL,
  requester_roles  VARCHAR(60) NOT NULL,
  reviewer_role_id INT NOT NULL,
  manager_role_id  INT NOT NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_frr (company_id, source_module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- بذر رأس الحربة: الصيانة مفعّلة لشركة 4 (المراجع=مشرف الصيانة 14، المعتمد=ادارة الصيانة 13)
INSERT INTO fin_request_routing
  (company_id, source_module, module_label, requester_roles, reviewer_role_id, manager_role_id, is_active)
VALUES (4, 'maintenance', 'الصيانة', '13,14', 14, 13, 1)
ON DUPLICATE KEY UPDATE module_label = VALUES(module_label);
