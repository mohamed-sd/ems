-- ═══════════════════════════════════════════════════════════════════════════
-- S12 — إدارة البلاغات والمتابعة التشغيلية (EQUIP-OPE-S12-EMS) — المرحلة 1
-- «برج المراقبة»: تذكرة واحدة تعبر كل الإدارات — الأساس المرجعي (T1.1).
--
-- الجداول العشرة (§3) بترتيب الاعتماديات + البذر المرجعي (الأنواع/التصنيفات).
-- قرارات دفتر الاتفاق (2026-07-18) المطبَّقة على مخطط §3 حرفيًا عداها:
--   • D4: التوجيه برقم الدور — owner_role_id بدل ENUM الإدارات الإحدى عشرة
--     (يستثمر شجرة roles.parent_role_id الحية للرؤية، ويُغني عن ALTER مستقبلي).
--     ينسحب على: tickets.owner_role_id · ticket_types.owner_role_id ·
--     ticket_transfers.from/to_role_id · ticket_events.actor_role_id ·
--     ticket_watchers.role_id · ticket_recurrence_templates.default_owner_role_id.
--   • D5: ticket_no بصيغة سنة-شهر-تسلسل (26-07-1001) يُسنِده الخادم حصريًا
--     عبر ems_sequences (المرحلة 2) — هنا العمود والقيد الفريد فقط.
--   • D8: الأنواع والتصنيفات كتالوج مشترك (T_CATALOG): بذر عام company_id=NULL
--     للجميع + إضافات كل شركة بمفتاحها (نمط mnt_inspection_template).
--   • لا حذف على التذكرة وسجلّاتها — الإلغاء حالة (§ب.7)؛ لذلك لا أعمدة حذفٍ
--     ناعم، وticket_events/ticket_transfers إلحاقيان (إدراج فقط في التطبيق).
-- المراجع الخارجية (equipment_id/project_id/driver_id/...) أعمدة مفهرسة مرنة
-- بلا FK صلب (§5 — البناء المرحلي والمزامنة)؛ الصلبة داخل الوحدة حصرًا.
-- التسجيل في بوابة العزل: app/Core/TenantRegistry.php (نفس الدفعة — إلزامي).
-- ═══════════════════════════════════════════════════════════════════════════

-- ── ① ticket_escalation_rules — سلّم التصعيد (§3.10) ────────────────────────
CREATE TABLE IF NOT EXISTS ticket_escalation_rules (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id           INT UNSIGNED NOT NULL,
  name                 VARCHAR(120) NOT NULL,
  level_no             TINYINT NOT NULL,                    -- 1..5
  escalate_after_hours DECIMAL(6,2) NOT NULL,
  escalate_to_role     ENUM('responsible','dept_head','dept_manager',
                            'ops_manager','top_mgmt') NOT NULL,
  notify_channel       ENUM('in_app','email','both') NOT NULL DEFAULT 'in_app',
  active               TINYINT(1) NOT NULL DEFAULT 1,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_level (company_id, level_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ② ticket_sla_policies — سياسات الاستحقاق (§3.9) ─────────────────────────
CREATE TABLE IF NOT EXISTS ticket_sla_policies (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id          INT UNSIGNED NOT NULL,
  name                VARCHAR(120) NOT NULL,
  ticket_type_id      INT UNSIGNED NULL,                   -- NULL = كل الأنواع
  priority            ENUM('normal','high','critical') NULL,
  business_impact     ENUM('production_critical','revenue','safety','admin') NULL,
  response_hours      DECIMAL(6,2) NOT NULL,
  resolution_hours    DECIMAL(6,2) NOT NULL,
  remind_before_hours DECIMAL(6,2) NULL,
  escalation_rule_id  INT UNSIGNED NULL,                   -- مرجع مرن → ..._rules
  active              TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_match (company_id, ticket_type_id, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ③ ticket_categories — التصنيف الفنّي (§3.4) · كتالوج مشترك (D8) ─────────
CREATE TABLE IF NOT EXISTS ticket_categories (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NULL,                            -- NULL = صف عام للجميع
  code       VARCHAR(30) NOT NULL,   -- engine, hydraulic, electrical, ...
  name       VARCHAR(80) NOT NULL,
  applies_to VARCHAR(40) NULL,
  active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
               ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cat_code (company_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ④ ticket_types — الأنواع ومسار التوجيه (§3.3) · كتالوج مشترك (D8) ───────
--    كل نوع يُسنِد الدور المالك (D4) وجدول التنفيذ الحقيقي في EMS (لا أسماء
--    المواصفة العامة): mnt_order · transfer_orders · proc_request · fin_requests ...
CREATE TABLE IF NOT EXISTS ticket_types (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id     INT UNSIGNED NULL,                        -- NULL = صف عام للجميع
  code           VARCHAR(40) NOT NULL,
  name           VARCHAR(120) NOT NULL,
  owner_role_id  INT UNSIGNED NOT NULL,                    -- D4: رأس الإدارة المالكة (roles level-1)
  default_nature ENUM('request','incident','recurring') NOT NULL DEFAULT 'request',
  ref_table      VARCHAR(40) NULL,                         -- جدول التنفيذ الحقيقي (whitelist تطبيقي)
  default_sla_id INT UNSIGNED NULL,                        -- مرجع مرن → ticket_sla_policies
  active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_type_code (company_id, code),
  KEY ix_owner_role (owner_role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ⑤ tickets — التذكرة التشغيلية الموحّدة (§3.2 + دفتر القرارات) ────────────
CREATE TABLE IF NOT EXISTS tickets (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id             INT UNSIGNED NOT NULL,
  ticket_no              VARCHAR(20)  NOT NULL,            -- D5: 26-07-1001 (خادمي حصرًا)
  ticket_type_id         INT UNSIGNED NOT NULL,
  category_id            INT UNSIGNED NULL,
  stage                  ENUM('new','classified','routed','in_progress',
                              'waiting','follow_up','done','closed','cancelled')
                           NOT NULL DEFAULT 'new',
  ticket_nature          ENUM('request','incident','recurring') NOT NULL,
  priority               ENUM('normal','high','critical') NOT NULL DEFAULT 'normal',
  business_impact        ENUM('production_critical','revenue','safety','admin')
                           NOT NULL DEFAULT 'admin',
  production_critical    TINYINT(1) NOT NULL DEFAULT 0,
  project_weight         ENUM('strategic','main','normal') NULL,  -- مخبّأ من project
  call_date              DATE NOT NULL,
  call_time              VARCHAR(10) NULL,
  reporting_person       VARCHAR(120) NOT NULL,
  reporter_contact       VARCHAR(40)  NULL,
  reporter_entity_id     INT UNSIGNED NULL,                -- clients (قراءة — مرجع مرن)
  reporter_user_id       INT UNSIGNED NULL,                -- users (المُبلِّغ الداخلي)
  project_id             INT UNSIGNED NULL,                -- project (S07/S05) — مرجع مرن
  equipment_id           INT UNSIGNED NULL,                -- equipments (S03) — مرجع مرن
  machine_type           VARCHAR(40)  NULL,
  machine_condition      ENUM('running','stopped') NULL,
  meter_reading          DECIMAL(12,2) NULL,
  complaint              TEXT NOT NULL,
  driver_id              INT UNSIGNED NULL,                -- employees (S04) — مرجع مرن
  helper_id              INT UNSIGNED NULL,                -- employees (S04) — مرجع مرن
  shift                  ENUM('morning','evening') NULL,
  owner_role_id          INT UNSIGNED NOT NULL,            -- D4: الدور المالك الحالي (بدل current_owner_dept)
  assigned_user_id       INT UNSIGNED NULL,                -- users — مرجع مرن
  service_team           ENUM('internal','external_workshop') NULL,
  issue_status           TEXT NULL,
  parent_id              INT UNSIGNED NULL,                -- ذاتي (التذكرة الأصل)
  is_parent              TINYINT(1) NOT NULL DEFAULT 0,
  ticket_role            ENUM('parent','child','standalone') NOT NULL DEFAULT 'standalone',
  sla_policy_id          INT UNSIGNED NULL,
  first_action_at        DATETIME NULL,
  response_due_at        DATETIME NULL,                    -- محسوب (§10)
  resolution_due_at      DATETIME NULL,                    -- محسوب (§10)
  close_date             DATE NULL,
  close_time             VARCHAR(10) NULL,
  closed_by              INT UNSIGNED NULL,
  is_recurring           TINYINT(1) NOT NULL DEFAULT 0,
  recurrence_template_id INT UNSIGNED NULL,                -- مرجع مرن → recurrence_templates
  linked_ref_table       VARCHAR(40) NULL,                 -- polymorphic → سجل التنفيذ
  linked_ref_id          INT UNSIGNED NULL,
  sync_uuid              CHAR(36) NULL,                    -- مطابقة Outbox (§أ)
  created_by             INT UNSIGNED NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ticket_no (company_id, ticket_no),
  UNIQUE KEY uq_sync      (company_id, sync_uuid),
  KEY ix_stage  (company_id, stage),
  KEY ix_owner  (company_id, owner_role_id),
  KEY ix_due    (company_id, resolution_due_at),
  KEY ix_type   (ticket_type_id),
  KEY ix_equip  (equipment_id),
  KEY ix_parent (parent_id),
  CONSTRAINT fk_tk_type   FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id)
                          ON DELETE RESTRICT,
  CONSTRAINT fk_tk_cat    FOREIGN KEY (category_id)    REFERENCES ticket_categories(id)
                          ON DELETE SET NULL,
  CONSTRAINT fk_tk_parent FOREIGN KEY (parent_id)      REFERENCES tickets(id)
                          ON DELETE RESTRICT,
  CONSTRAINT fk_tk_sla    FOREIGN KEY (sla_policy_id)  REFERENCES ticket_sla_policies(id)
                          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ⑥ ticket_transfers — سجلّ انتقال الملكية (§3.5) · إلحاقي ────────────────
CREATE TABLE IF NOT EXISTS ticket_transfers (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id        INT UNSIGNED NOT NULL,
  ticket_id         INT UNSIGNED NOT NULL,
  from_role_id      INT UNSIGNED NOT NULL,                 -- D4: بدل from_dept
  to_role_id        INT UNSIGNED NOT NULL,                 -- D4: بدل to_dept
  from_user_id      INT UNSIGNED NULL,
  to_user_id        INT UNSIGNED NULL,
  transfer_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  transferred_by    INT UNSIGNED NULL,
  reason            TEXT NOT NULL,                         -- إلزامي (§ب.8): يُرفَض الفارغ
  notes             TEXT NULL,
  sync_uuid         CHAR(36) NULL,
  PRIMARY KEY (id),
  KEY ix_ticket (company_id, ticket_id),
  CONSTRAINT fk_tr_ticket FOREIGN KEY (ticket_id)
    REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ⑦ ticket_events — سجلّ الأحداث والتواصل (§3.6) · إلحاقي ─────────────────
CREATE TABLE IF NOT EXISTS ticket_events (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id    INT UNSIGNED NOT NULL,
  ticket_id     INT UNSIGNED NOT NULL,
  event_type    ENUM('note','communication','status_change','transfer',
                     'escalation','attachment','reminder','system')
                  NOT NULL DEFAULT 'note',
  actor_user_id INT UNSIGNED NULL,
  actor_role_id INT UNSIGNED NULL,                         -- D4: بدل actor_dept
  body          TEXT NULL,
  old_value     VARCHAR(60) NULL,
  new_value     VARCHAR(60) NULL,
  sync_uuid     CHAR(36) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ticket_time (company_id, ticket_id, created_at),
  CONSTRAINT fk_ev_ticket FOREIGN KEY (ticket_id)
    REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ⑧ ticket_watchers — المتابِعون والإشعارات (§3.7) ────────────────────────
CREATE TABLE IF NOT EXISTS ticket_watchers (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id   INT UNSIGNED NOT NULL,
  ticket_id    INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  role_id      INT UNSIGNED NULL,                          -- D4: بدل dept
  watch_reason ENUM('reporter','owner','manager','subscribed') NOT NULL DEFAULT 'subscribed',
  notify       TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_watch (company_id, ticket_id, user_id),
  CONSTRAINT fk_wt_ticket FOREIGN KEY (ticket_id)
    REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ⑨ ticket_attachments — المرفقات الميدانية (§3.8) ────────────────────────
CREATE TABLE IF NOT EXISTS ticket_attachments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id  INT UNSIGNED NOT NULL,
  ticket_id   INT UNSIGNED NOT NULL,
  file_path   VARCHAR(255) NOT NULL,
  file_type   ENUM('photo','signature','document') NOT NULL DEFAULT 'photo',
  gps_lat     DECIMAL(10,7) NULL,
  gps_lng     DECIMAL(10,7) NULL,
  captured_at DATETIME NULL,
  uploaded_by INT UNSIGNED NULL,
  sync_uuid   CHAR(36) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ticket (company_id, ticket_id),
  CONSTRAINT fk_at_ticket FOREIGN KEY (ticket_id)
    REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ⑩ ticket_recurrence_templates — القوالب الدورية (§3.11) ─────────────────
CREATE TABLE IF NOT EXISTS ticket_recurrence_templates (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id            INT UNSIGNED NOT NULL,
  name                  VARCHAR(120) NOT NULL,
  ticket_type_id        INT UNSIGNED NOT NULL,             -- مرجع مرن → ticket_types
  category_id           INT UNSIGNED NULL,
  equipment_id          INT UNSIGNED NULL,                 -- equipments — مرجع مرن
  recurrence_interval   INT NOT NULL DEFAULT 1,
  recurrence_unit       ENUM('day','week','month','year') NOT NULL,
  next_occurrence_date  DATE NOT NULL,
  lead_time_days        INT NOT NULL DEFAULT 0,
  default_owner_role_id INT UNSIGNED NULL,                 -- D4: NULL = يرث دور النوع
  default_priority      ENUM('normal','high','critical') NOT NULL DEFAULT 'normal',
  active                TINYINT(1) NOT NULL DEFAULT 1,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_next (company_id, active, next_occurrence_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════
-- البذر المرجعي (صفوف عامة company_id = NULL — كتالوج D8) · idempotent
-- ═══════════════════════════════════════════════════════════════════════════

-- ── التصنيف الفنّي الثمانية (§3.4 — مشتق من سجلّ 2026) ──────────────────────
INSERT INTO ticket_categories (company_id, code, name)
SELECT NULL, 'engine', 'المحرك'
WHERE NOT EXISTS (SELECT 1 FROM ticket_categories WHERE company_id IS NULL AND code = 'engine');
INSERT INTO ticket_categories (company_id, code, name)
SELECT NULL, 'hydraulic', 'هيدروليك'
WHERE NOT EXISTS (SELECT 1 FROM ticket_categories WHERE company_id IS NULL AND code = 'hydraulic');
INSERT INTO ticket_categories (company_id, code, name)
SELECT NULL, 'electrical', 'كهرباء'
WHERE NOT EXISTS (SELECT 1 FROM ticket_categories WHERE company_id IS NULL AND code = 'electrical');
INSERT INTO ticket_categories (company_id, code, name)
SELECT NULL, 'welding', 'حدادة ولحام'
WHERE NOT EXISTS (SELECT 1 FROM ticket_categories WHERE company_id IS NULL AND code = 'welding');
INSERT INTO ticket_categories (company_id, code, name)
SELECT NULL, 'ac', 'مكيّف'
WHERE NOT EXISTS (SELECT 1 FROM ticket_categories WHERE company_id IS NULL AND code = 'ac');
INSERT INTO ticket_categories (company_id, code, name)
SELECT NULL, 'tire', 'بنشر وإطارات'
WHERE NOT EXISTS (SELECT 1 FROM ticket_categories WHERE company_id IS NULL AND code = 'tire');
INSERT INTO ticket_categories (company_id, code, name)
SELECT NULL, 'oil_change', 'غيار زيت'
WHERE NOT EXISTS (SELECT 1 FROM ticket_categories WHERE company_id IS NULL AND code = 'oil_change');
INSERT INTO ticket_categories (company_id, code, name)
SELECT NULL, 'other', 'غير ذلك'
WHERE NOT EXISTS (SELECT 1 FROM ticket_categories WHERE company_id IS NULL AND code = 'other');

-- ── خريطة التوجيه (§9.1 مترجمة لأدوار EMS الحية وجداولها الحقيقية) ──────────
--   صيانة→13 · نقل→23 · مشتريات→16 · تمويل→17 · قوى→4 · أسطول→3 ·
--   موردون→2 · مبيعات→12 · تشغيل→1 (السلامة/الطوارئ → التشغيل، بلا ref).
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'mnt_breakdown', 'بلاغ عطل / طلب صيانة', 13, 'incident', 'mnt_order'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'mnt_breakdown');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'mnt_periodic', 'مراجعة صيانة دورية', 13, 'recurring', 'mnt_order'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'mnt_periodic');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'transport_request', 'طلب نقل وترحيل', 23, 'request', 'transfer_orders'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'transport_request');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'parts_request', 'طلب قطعة / شراء / صرف', 16, 'request', 'proc_request'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'parts_request');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'finance_request', 'طلب تمويل / دفعة', 17, 'request', 'fin_requests'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'finance_request');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'workforce_request', 'طلب مشغّل / تغطية / إجازة', 4, 'request', 'employees'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'workforce_request');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'equipment_request', 'طلب معدة / إتاحة / استبدال', 3, 'request', 'equipments'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'equipment_request');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'fleet_inspection', 'تفتيش دوري / تجديد ترخيص وتأمين', 3, 'recurring', 'equipments'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'fleet_inspection');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'supplier_issue', 'مورد: تأخر / خلاف / مستحقات', 2, 'incident', 'suppliers'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'supplier_issue');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'client_complaint', 'شكوى عميل / نزاع تعاقدي', 12, 'incident', 'contracts'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'client_complaint');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'ops_support', 'دعم تشغيلي / تغطية وردية', 1, 'request', 'operations'
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'ops_support');
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, ref_table)
SELECT NULL, 'safety_incident', 'بلاغ سلامة / طوارئ', 1, 'incident', NULL
WHERE NOT EXISTS (SELECT 1 FROM ticket_types WHERE company_id IS NULL AND code = 'safety_incident');

-- ═══════════════════════════════════════════════════════════════════════════
-- ROLLBACK (نفّذ يدويًا فقط عند الطلب — الترتيب عكس الاعتماديات):
--   DROP TABLE IF EXISTS ticket_recurrence_templates;
--   DROP TABLE IF EXISTS ticket_attachments;
--   DROP TABLE IF EXISTS ticket_watchers;
--   DROP TABLE IF EXISTS ticket_events;
--   DROP TABLE IF EXISTS ticket_transfers;
--   DROP TABLE IF EXISTS tickets;
--   DROP TABLE IF EXISTS ticket_types;
--   DROP TABLE IF EXISTS ticket_categories;
--   DROP TABLE IF EXISTS ticket_sla_policies;
--   DROP TABLE IF EXISTS ticket_escalation_rules;
--   + إزالة قيود ticket_* من app/Core/TenantRegistry.php
-- ═══════════════════════════════════════════════════════════════════════════
