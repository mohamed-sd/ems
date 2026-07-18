-- ═══════════════════════════════════════════════════════════════════════════
-- S12 — طبقة الاستحقاق والتصعيد والدوري (المراحل 5+6) — T6.1
--
-- ① tkt_notifications: جدولٌ مملوكٌ للوحدة على نمط trs_notifications حرفيًا
--    (dedupe_key فريدٌ لكل شركة ⇒ التذكير/التصعيد مرةً واحدةً يوميًّا؛ التكرار
--    يُبتلَع كسلوك INSERT IGNORE). صفر مساسٍ بجرس النظام أو fin_*.
-- ② وحدات شاشات الإعداد الثلاث (134-136) + صلاحيات الدور 24.
-- idempotent: CREATE IF NOT EXISTS + حارس NOT EXISTS.
-- التسجيل في بوابة العزل: app/Core/TenantRegistry.php (نفس الدفعة — إلزامي).
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS tkt_notifications (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id  INT UNSIGNED NOT NULL,
  ticket_id   INT UNSIGNED NULL,
  notif_type  ENUM('due_soon','overdue','escalation','recurring_created') NOT NULL,
  target_role INT UNSIGNED NULL,                    -- D4: الدور المُخطَر (لا نص إدارة)
  title       VARCHAR(160) NOT NULL,
  body        VARCHAR(255) NULL,
  link_url    VARCHAR(160) NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  dedupe_key  VARCHAR(80) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dedupe (company_id, dedupe_key),
  KEY ix_company_read (company_id, is_read),
  KEY ix_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── وحدات شاشات الإعداد (المستوى الأخير من سايدبار الدور 24) ────────────────
INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 134, 'سياسات الاستحقاق (SLA)', 'Tickets/ticket_sla_config.php', 24, '1', 'fa fa-stopwatch', 4
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `id` = 134);

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 135, 'سلّم التصعيد', 'Tickets/ticket_escalation_config.php', 24, '1', 'fa fa-arrow-up-right-dots', 5
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `id` = 135);

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 136, 'القوالب الدورية', 'Tickets/ticket_recurrence.php', 24, '1', 'fa fa-repeat', 6
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `id` = 136);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 24, 134, 1, 1, 1, 1 WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE `role_id`=24 AND `module_id`=134);
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 24, 135, 1, 1, 1, 1 WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE `role_id`=24 AND `module_id`=135);
INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 24, 136, 1, 1, 1, 1 WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE `role_id`=24 AND `module_id`=136);

-- ═══════════════════════════════════════════════════════════════════════════
-- ROLLBACK (يدويًا):
--   DELETE FROM role_permissions WHERE role_id=24 AND module_id IN (134,135,136);
--   DELETE FROM modules WHERE id IN (134,135,136);
--   DROP TABLE IF EXISTS tkt_notifications;
--   + إزالة قيد tkt_notifications من TenantRegistry
-- ═══════════════════════════════════════════════════════════════════════════
