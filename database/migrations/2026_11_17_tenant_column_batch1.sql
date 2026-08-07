-- DEC-D · الدفعة ① (2026-08-06 · تفويض المالك جلسة update0009): عمودُ الكيان
-- ═══════════════════════════════════════════════════════════════════════════
-- بدءُ إغلاق DEF-009 بدفعاتٍ كما اعتُمد: هذه الدفعةُ جداولُ الأبناءِ التي يُشتق
-- كيانُها من أبيها بعلاقةٍ لا لبسَ فيها (12 جدولًا: تمويل ×2 · بلاغات ×7 ·
-- تكليفات ×3). عمودٌ NULL + backfill + فهرس — ولا NOT NULL قبل اكتمال التحقق
-- في دفعةٍ لاحقة. e05_checks ② يقيس التقدم (71 → 59).
-- idempotent: الإضافةُ مشروطةٌ والحشوُ على NULL وحدَه.

-- ── ① financing_installments ← financing_operations (op_id) ────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='financing_installments' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `financing_installments` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من financing_operations.op_id'', ADD KEY `ix_fininst_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE financing_installments t JOIN financing_operations p ON p.op_id = t.op_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ② financed_assets ← financing_operations (op_id) ───────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='financed_assets' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `financed_assets` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من financing_operations.op_id'', ADD KEY `ix_finasset_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE financed_assets t JOIN financing_operations p ON p.op_id = t.op_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ③ ticket_workstreams ← tickets (tk_id) ──────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_workstreams' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `ticket_workstreams` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من tickets.tk_id'', ADD KEY `ix_tkws_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE ticket_workstreams t JOIN tickets p ON p.id = t.tk_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ④ ticket_communications ← tickets (tk_id) ───────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_communications' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `ticket_communications` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من tickets.tk_id'', ADD KEY `ix_tkcm_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE ticket_communications t JOIN tickets p ON p.id = t.tk_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ⑤ ticket_participants ← tickets (tk_id) ─────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_participants' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `ticket_participants` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من tickets.tk_id'', ADD KEY `ix_tkpp_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE ticket_participants t JOIN tickets p ON p.id = t.tk_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ⑥ ticket_responses ← tickets (tk_id) ────────────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_responses' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `ticket_responses` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من tickets.tk_id'', ADD KEY `ix_tkrd_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE ticket_responses t JOIN tickets p ON p.id = t.tk_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ⑦ ticket_escalations ← ticket_workstreams (ws_id) — بعد حشو ③ ──────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_escalations' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `ticket_escalations` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من ticket_workstreams.ws_id'', ADD KEY `ix_tkesc_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE ticket_escalations t JOIN ticket_workstreams p ON p.ws_id = t.ws_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ⑧ ticket_effects ← ticket_workstreams (ws_id) ───────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_effects' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `ticket_effects` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من ticket_workstreams.ws_id'', ADD KEY `ix_tkef_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE ticket_effects t JOIN ticket_workstreams p ON p.ws_id = t.ws_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ⑨ ticket_holds ← ticket_workstreams (ws_id) ─────────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_holds' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `ticket_holds` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من ticket_workstreams.ws_id'', ADD KEY `ix_tkhl_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE ticket_holds t JOIN ticket_workstreams p ON p.ws_id = t.ws_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ⑩ assignment_capabilities ← org_assignments (asg_id) ────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assignment_capabilities' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `assignment_capabilities` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من org_assignments.asg_id'', ADD KEY `ix_asgcap_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE assignment_capabilities t JOIN org_assignments p ON p.asg_id = t.asg_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ⑪ assignment_audit ← org_assignments (asg_id) ───────────────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assignment_audit' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `assignment_audit` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من org_assignments.asg_id'', ADD KEY `ix_asgaud_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE assignment_audit t JOIN org_assignments p ON p.asg_id = t.asg_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;

-- ── ⑫ assignment_reporting_lines ← org_assignments (asg_id) ─────────────────
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assignment_reporting_lines' AND COLUMN_NAME='company_id');
SET @ddl := IF(@c=0, 'ALTER TABLE `assignment_reporting_lines` ADD COLUMN `company_id` INT NULL COMMENT ''DEC-D ① — مشتق من org_assignments.asg_id'', ADD KEY `ix_asgrl_co` (`company_id`)', 'SELECT 1');
PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE assignment_reporting_lines t JOIN org_assignments p ON p.asg_id = t.asg_id
   SET t.company_id = p.company_id WHERE t.company_id IS NULL;
