-- ═══════════════════════════════════════════════════════════════════════════
-- update0004 · الموجة ⑪ · TKT-01 v1.1 §12 — توسيع وحدة بلاغات مكتملة (186/186)
-- TKT-01 الطبائع الثمانية وclosure_policy على ticket_types القائم
-- TKT-02 ticket_type_workstreams بالتفعيل الشرطي + بذر مسارات عطل المعدة
-- TKT-03 توسيع tickets: head_state مشتق · السرية بحقلين · السياق المحمول · الروابط
-- TKT-04 ticket_workstreams — المسار وحدة العمل
-- TKT-05 ticket_holds + ticket_escalations + ticket_participants + ticket_responses
-- TKT-06 ticket_effects (is_provisional) + ticket_communications — والاطلاع في
--        sensitive_read_log القائم بنطاق ticket.secret
-- بناء فوق القائم لا استبدال: ticket_watchers/escalation_rules/events تبقى.
-- ═══════════════════════════════════════════════════════════════════════════

-- ── TKT-01 · توسيع ticket_types بالطبيعة الثمانية وسياسة الإغلاق ────────────
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'ticket_types' AND column_name = 'nature');
SET @ddl = IF(@c = 0, 'ALTER TABLE ticket_types
  ADD COLUMN nature ENUM(''incident'',''problem'',''request'',''complaint'',''information'',''risk'',''emergency'',''suggestion'') NULL
      COMMENT ''TKT-01 §3: الطبيعة غير المجال — تحدد الدورة والسرية والإغلاق'' AFTER default_nature,
  ADD COLUMN category VARCHAR(40) NULL COMMENT ''§4: المجال — منه تشتق الإدارة المختصة'' AFTER nature,
  ADD COLUMN default_confidentiality ENUM(''normal'',''protected'',''secret'') NOT NULL DEFAULT ''normal'' AFTER category,
  ADD COLUMN closure_policy ENUM(''reporter_confirm'',''owner_approve'',''auto'',''admin_only'',''committee'') NOT NULL DEFAULT ''reporter_confirm''
      COMMENT ''§5-⑥: ولا إغلاق آلي للسلامة والحوادث وشكاوى العاملين'' AFTER default_confidentiality,
  ADD COLUMN allow_anonymous TINYINT(1) NOT NULL DEFAULT 0 AFTER closure_policy,
  ADD COLUMN default_priority ENUM(''normal'',''high'',''critical'') NOT NULL DEFAULT ''normal'' AFTER allow_anonymous', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- خرط الطبيعة من السلف default_nature حيث لم تُملأ (recurring → problem §9)
UPDATE ticket_types SET nature = CASE default_nature
    WHEN 'incident' THEN 'incident' WHEN 'recurring' THEN 'problem' ELSE 'request' END
 WHERE nature IS NULL;
UPDATE ticket_types SET category = 'equipment' WHERE category IS NULL AND (name LIKE '%عطل%' OR name LIKE '%صيانة%' OR name LIKE '%معدة%');
UPDATE ticket_types SET category = COALESCE(category, 'commercial');

-- سياسات إغلاق النوع (§5-⑥): العطل الحرج والسلامة اعتماد المختص — لا آلي
UPDATE ticket_types SET closure_policy = 'owner_approve' WHERE nature IN ('incident', 'emergency');
UPDATE ticket_types SET closure_policy = 'committee' WHERE name LIKE '%سلامة%';

-- أنواع الطبائع الناقصة (شكوى · معلومة · خطر · طارئ · مقترح) — صفوف لا كود
INSERT INTO ticket_types (company_id, code, name, owner_role_id, default_nature, nature, category,
                          default_confidentiality, closure_policy, allow_anonymous, default_priority, active)
SELECT NULL, t.code, t.name, t.owner_role, 'request', t.nature, t.category, t.conf, t.pol, t.anon, t.prio, 1
FROM (SELECT 'complaint_hr' code, 'شكوى موظف' name, 4 owner_role, 'complaint' nature, 'hr' category,
             'protected' conf, 'owner_approve' pol, 1 anon, 'normal' prio UNION ALL
      SELECT 'complaint_gov', 'شكوى سرية (احتيال أو تهديد)', 15, 'complaint', 'governance', 'secret', 'committee', 1, 'high' UNION ALL
      SELECT 'information_note', 'معلومة أو إخطار', 24, 'information', 'commercial', 'normal', 'auto', 0, 'normal' UNION ALL
      SELECT 'risk_report', 'خطر محتمل', 1, 'risk', 'safety', 'normal', 'owner_approve', 0, 'high' UNION ALL
      SELECT 'safety_emergency', 'طارئ سلامة', 1, 'emergency', 'safety', 'normal', 'committee', 0, 'critical' UNION ALL
      SELECT 'suggestion', 'مقترح تحسين', 24, 'suggestion', 'commercial', 'normal', 'admin_only', 0, 'normal') t
WHERE NOT EXISTS (SELECT 1 FROM ticket_types x WHERE x.code = t.code);

-- ── TKT-02 · مسارات النوع بالتفعيل الشرطي ───────────────────────────────────
CREATE TABLE IF NOT EXISTS ticket_type_workstreams (
  ws_def_id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_type_id             INT UNSIGNED NOT NULL,
  workstream_type            VARCHAR(40) NOT NULL COMMENT 'maintenance·movement·operators·warehouse·procurement·hr·governance·support…',
  seq_no                     INT NOT NULL DEFAULT 1,
  target_org_unit_code       VARCHAR(40) NULL COMMENT 'org_units.unit_code — والمكلف يُحل من ORG-01 لا من شخص ثابت',
  target_role                VARCHAR(60) NULL COMMENT 'دور الحل في PermitGate/TicketRouter (movement·maintenance·…)',
  mandatory                  TINYINT(1) NOT NULL DEFAULT 1,
  activation_mode            ENUM('immediate','conditional') NOT NULL DEFAULT 'immediate',
  trigger_event              VARCHAR(60) NULL COMMENT 'مثال StockUnavailable — الشرطي يفتح بوقوعه لا بالإنشاء',
  depends_on_workstream_type VARCHAR(40) NULL,
  response_sla_minutes       INT NULL,
  resolve_sla_minutes        INT NULL,
  sla_clock                  ENUM('absolute','business') NOT NULL DEFAULT 'absolute' COMMENT '§6: الحرج مطلق وما دونه بساعات العمل',
  PRIMARY KEY (ws_def_id),
  UNIQUE KEY uq_ttws (ticket_type_id, workstream_type, seq_no),
  CONSTRAINT fk_ttws_type FOREIGN KEY (ticket_type_id) REFERENCES ticket_types (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TKT-01 §12: فمسار المشتريات يُفتح عند إعلان نفاد القطعة لا عند إنشاء البلاغ';

-- بذر مسارات عطل المعدة (§3 المثال الحاكم) لكل نوع معدة حي
INSERT INTO ticket_type_workstreams (ticket_type_id, workstream_type, seq_no, target_org_unit_code, target_role,
                                     mandatory, activation_mode, trigger_event, response_sla_minutes, resolve_sla_minutes, sla_clock)
SELECT tt.id, w.wt, 1, w.unit, w.role, w.mand, w.mode, w.trig, w.resp, w.solve, 'absolute'
FROM ticket_types tt
JOIN (SELECT 'maintenance' wt, 'maintenance' unit, 'maintenance' role, 1 mand, 'immediate' mode, NULL trig, 120 resp, 1440 solve UNION ALL
      SELECT 'movement',    'movement',    'movement',    1, 'immediate', NULL, 60, 240 UNION ALL
      SELECT 'operators',   'operators',   'operators',   0, 'immediate', NULL, 240, 1440 UNION ALL
      SELECT 'warehouse',   'warehouse',   'warehouse',   1, 'immediate', NULL, 120, 720 UNION ALL
      SELECT 'procurement', 'procurement_ops', 'procurement', 1, 'conditional', 'StockUnavailable', 240, 1440) w
WHERE tt.category = 'equipment' AND tt.nature IN ('incident', 'problem')
  AND NOT EXISTS (SELECT 1 FROM ticket_type_workstreams x
                   WHERE x.ticket_type_id = tt.id AND x.workstream_type = w.wt AND x.seq_no = 1);

-- مسار واحد افتراضي لكل نوع بلا مسارات (فلا بلاغ بلا مالك)
INSERT INTO ticket_type_workstreams (ticket_type_id, workstream_type, seq_no, target_org_unit_code, target_role,
                                     mandatory, activation_mode, response_sla_minutes, resolve_sla_minutes)
SELECT tt.id,
       CASE tt.category WHEN 'hr' THEN 'hr' WHEN 'governance' THEN 'governance'
            WHEN 'safety' THEN 'movement' ELSE 'support' END,
       1,
       CASE tt.category WHEN 'hr' THEN 'hr' WHEN 'governance' THEN 'governance'
            WHEN 'safety' THEN 'movement' ELSE 'tickets' END,
       CASE tt.category WHEN 'hr' THEN 'hr' WHEN 'governance' THEN 'governance'
            WHEN 'safety' THEN 'movement' ELSE 'support' END,
       1, 'immediate', 480, 4320
FROM ticket_types tt
WHERE NOT EXISTS (SELECT 1 FROM ticket_type_workstreams x WHERE x.ticket_type_id = tt.id);

-- ── TKT-03 · توسيع رأس البلاغ ───────────────────────────────────────────────
SET @c = (SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'tickets' AND column_name = 'head_state');
SET @ddl = IF(@c = 0, 'ALTER TABLE tickets
  ADD COLUMN confidentiality ENUM(''normal'',''protected'',''secret'') NOT NULL DEFAULT ''normal'' AFTER priority,
  ADD COLUMN operational_summary VARCHAR(255) NULL COMMENT ''يراه الجميع — الفصل البنيوي §8'' AFTER complaint,
  ADD COLUMN private_details TEXT NULL COMMENT ''خلف ConfidentialityGuard — لا يُجلب بلا صلاحية'' AFTER operational_summary,
  ADD COLUMN source_screen VARCHAR(120) NULL COMMENT ''§2: السياق محمول لا مُدخل'' AFTER private_details,
  ADD COLUMN source_entity_type VARCHAR(40) NULL AFTER source_screen,
  ADD COLUMN source_entity_id BIGINT UNSIGNED NULL AFTER source_entity_type,
  ADD COLUMN site_id INT NULL AFTER project_id,
  ADD COLUMN contract_id INT NULL AFTER site_id,
  ADD COLUMN shift_no INT NULL AFTER contract_id,
  ADD COLUMN period_no INT NULL AFTER shift_no,
  ADD COLUMN is_anonymous TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''§8-④: الهوية محفوظة للحوكمة'' AFTER reporter_user_id,
  ADD COLUMN head_state ENUM(''open'',''closed'') NOT NULL DEFAULT ''open''
      COMMENT ''ذاكرة مشتقة لا مصدر حقيقة — لا يكتبها إلا معيد الحساب (TicketStateService)'' AFTER stage,
  ADD COLUMN duplicate_of_ticket_id INT UNSIGNED NULL AFTER parent_id,
  ADD COLUMN related_ticket_id INT UNSIGNED NULL AFTER duplicate_of_ticket_id,
  ADD COLUMN recurrence_group_id INT UNSIGNED NULL AFTER related_ticket_id,
  ADD KEY idx_tickets_head (head_state, priority, created_at),
  ADD KEY idx_tickets_dup (duplicate_of_ticket_id)', 'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- خرط الموروث: الرؤوس المقفلة قديمًا head_state=closed (TKT-14 يكمل الخرط خدمةً)
UPDATE tickets SET head_state = 'closed' WHERE stage IN ('done', 'closed', 'cancelled') AND head_state = 'open';

-- ── TKT-04 · المسار وحدة العمل ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ticket_workstreams (
  ws_id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tk_id              INT UNSIGNED NOT NULL,
  workstream_type    VARCHAR(40) NOT NULL,
  seq_no             INT NOT NULL DEFAULT 1,
  org_unit_id        INT UNSIGNED NULL,
  assignee_person_id INT NULL COMMENT 'يُحل من تكليفات ORG-01 النافذة لا من جدول النوع',
  mandatory          TINYINT(1) NOT NULL DEFAULT 1,
  state              ENUM('new','received','in_progress','on_hold','done_pending','closed','reopened','admin_closed') NOT NULL DEFAULT 'new',
  activation_state   ENUM('pending','opened','skipped') NOT NULL DEFAULT 'opened' COMMENT 'الشرطي pending حتى حدث تفعيله',
  response_due_at    DATETIME NULL,
  resolve_due_at     DATETIME NULL,
  received_at        DATETIME NULL,
  resolved_at        DATETIME NULL,
  closed_at          DATETIME NULL,
  reopen_count       INT NOT NULL DEFAULT 0 COMMENT 'ظاهر — وثلاث إعادات ترفعه للمركز',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ws_id),
  UNIQUE KEY uq_tws (tk_id, workstream_type, seq_no),
  KEY idx_tws_assignee (assignee_person_id, state),
  KEY idx_tws_due (state, response_due_at),
  CONSTRAINT fk_tws_ticket FOREIGN KEY (tk_id) REFERENCES tickets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='TKT-01 §12: UQ على (البلاغ×نوع المسار×التسلسل) — فللإدارة الواحدة مساران مختلفان';

-- ── TKT-05 · التعليق والتصعيد والمشاركون والردود ────────────────────────────
CREATE TABLE IF NOT EXISTS ticket_holds (
  hold_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ws_id          INT UNSIGNED NOT NULL COMMENT 'على المسار لا الرأس — فالمهلة تتوقف لمسار ولا توقف الباقي',
  reason_code    ENUM('awaiting_part','awaiting_approval','awaiting_technician','awaiting_reporter','awaiting_external') NOT NULL
                 COMMENT 'قائمة محكومة لا نص حر — وإلا صار التعليق بابًا للتهرب',
  expected_until DATETIME NOT NULL COMMENT 'ولا تعليق بلا مدة متوقعة — وتجاوزها يصعد التعليق نفسه',
  started_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at       DATETIME NULL,
  PRIMARY KEY (hold_id),
  KEY idx_holds_open (ws_id, ended_at),
  CONSTRAINT fk_tkhold_ws FOREIGN KEY (ws_id) REFERENCES ticket_workstreams (ws_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_escalations (
  esc_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ws_id        INT UNSIGNED NOT NULL,
  level        ENUM('mgr','ops_mgr','exec') NOT NULL,
  triggered_by ENUM('sla_breach','reopen_threshold','safety','hold_overdue') NOT NULL,
  to_person_id INT NULL,
  at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (esc_id),
  KEY idx_esc_ws (ws_id, at),
  CONSTRAINT fk_tkesc_ws FOREIGN KEY (ws_id) REFERENCES ticket_workstreams (ws_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Insert-only — ولا تصعيد يدوي يسجَّل هنا (§6: آلي لا بطلب)';

CREATE TABLE IF NOT EXISTS ticket_participants (
  p_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tk_id     INT UNSIGNED NOT NULL,
  person_id INT NOT NULL,
  role      ENUM('reporter','assignee','watcher','duplicate_reporter') NOT NULL,
  added_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (p_id),
  UNIQUE KEY uq_tp (tk_id, person_id, role),
  CONSTRAINT fk_tktp_ticket FOREIGN KEY (tk_id) REFERENCES tickets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ومبلغ المكرر يضاف متابعًا للأصل فلا يُفقد أنه أبلغ (§9)';

CREATE TABLE IF NOT EXISTS ticket_responses (
  rd_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tk_id         INT UNSIGNED NOT NULL,
  ws_id         INT UNSIGNED NULL COMMENT 'إلزامي لردود المسار وفارغ للرد المركزي على الرأس',
  person_id     INT NOT NULL,
  response_type ENUM('reply','acknowledge','info_added','no_action_decision') NOT NULL,
  body          TEXT NULL,
  at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (rd_id),
  KEY idx_tr_ticket (tk_id, at),
  CONSTRAINT fk_tktr_ticket FOREIGN KEY (tk_id) REFERENCES tickets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TKT-06 · الأثر والتواصل ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ticket_effects (
  lnk_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ws_id          INT UNSIGNED NOT NULL,
  effect_type    ENUM('inspection_request','work_order','issue_request','purchase_request',
                      'stoppage_attribution','decision','reply','acknowledge','info_added','no_action') NOT NULL,
  effect_ref     VARCHAR(120) NOT NULL,
  is_provisional TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'للإسناد قبل اعتماد الأثر — الخطوات الأربع §7',
  at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (lnk_id),
  KEY idx_te_ws (ws_id),
  CONSTRAINT fk_tkte_ws FOREIGN KEY (ws_id) REFERENCES ticket_workstreams (ws_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ولا يُغلق مسار بلا سطر هنا (عدا الإغلاق الإداري)';

CREATE TABLE IF NOT EXISTS ticket_communications (
  cm_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tk_id     INT UNSIGNED NOT NULL,
  person_id INT NOT NULL,
  channel   ENUM('system','phone','field') NOT NULL DEFAULT 'system',
  note      VARCHAR(255) NOT NULL,
  at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (cm_id),
  KEY idx_tc_ticket (tk_id, at),
  CONSTRAINT fk_tktc_ticket FOREIGN KEY (tk_id) REFERENCES tickets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='تواصل مركز البلاغات يسجَّل فيبقى أثره (§10-③)';
