-- ═══════════════════════════════════════════════════════════════════════════
-- S12 — إدارة البلاغات (المرحلة 2 · T2.1): وحدتا القائمة والاستمارة + الصلاحيات
--
-- 132 قائمة البلاغات الموحّدة (سايدبار الدور 24) · 133 استمارة البلاغ (بلا رابط
-- سايدبار — تُفتح من القائمة/التوبار). وصول بقية المستخدمين للشاشتين بنمط
-- «المراسلات» (كسابقة Maintenance/breakdowns.php): أي مستخدم مسجَّل يُنشئ بلاغًا
-- ويرى نطاقه (D2/D3) — الإنفاذ داخل الشاشة، لا عبر role_permissions لكل دور.
-- idempotent: حارس NOT EXISTS.
-- ═══════════════════════════════════════════════════════════════════════════

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 132, 'قائمة البلاغات الموحّدة', 'Tickets/tickets_list.php', 24, '1', 'fa fa-tower-observation', 0
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `id` = 132);

INSERT INTO `modules` (`id`, `name`, `code`, `owner_role_id`, `is_link`, `icon`, `display_order`)
SELECT 133, 'استمارة بلاغ', 'Tickets/ticket_form.php', 24, '0', 'fa fa-file-circle-plus', 3
WHERE NOT EXISTS (SELECT 1 FROM `modules` WHERE `id` = 133);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 24, 132, 1, 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE `role_id` = 24 AND `module_id` = 132);

INSERT INTO `role_permissions` (`role_id`, `module_id`, `can_view`, `can_add`, `can_edit`, `can_delete`)
SELECT 24, 133, 1, 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM `role_permissions` WHERE `role_id` = 24 AND `module_id` = 133);

-- ═══════════════════════════════════════════════════════════════════════════
-- ROLLBACK (يدويًا عند الطلب):
--   DELETE FROM role_permissions WHERE role_id = 24 AND module_id IN (132, 133);
--   DELETE FROM modules WHERE id IN (132, 133);
-- ═══════════════════════════════════════════════════════════════════════════
